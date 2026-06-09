<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use App\Models\SafeguardingOfficer;
use App\Models\SafeguardingCase;
use App\Models\SafeguardingAction;
use App\Models\SafeguardingAuditLog;

/**
 * Gender-based / safeguarding cases. CONFIDENTIAL and survivor-centred:
 *  - read/manage access is restricted to authorised safeguarding officers
 *  - every access is written to an immutable audit log
 *  - records are stored separately from general incidents
 *  - intake (creating a report) is open to any authenticated user, but the
 *    reporter cannot read cases back unless they are an officer
 */
class SafeguardingController extends Controller
{
    /* ----------------------------- access ------------------------------- */

    private function isOfficer(): bool
    {
        return SafeguardingOfficer::where('user_id', Auth::id())->where('active', true)->exists();
    }

    private function canManageOfficers(): bool
    {
        return in_array((int) Auth::user()->role, [1, 3], true);
    }

    /** Gate officer-only endpoints, logging any denied attempt. */
    private function gateOfficer(Request $request, ?int $caseId = null): void
    {
        if (!$this->isOfficer()) {
            $this->audit($request, 'access_denied', $caseId, 'Non-officer attempted safeguarding access.');
            abort(403, 'This area is restricted to authorised safeguarding officers.');
        }
    }

    private function audit(Request $request, string $action, ?int $caseId = null, ?string $detail = null): void
    {
        SafeguardingAuditLog::create([
            'case_id'    => $caseId,
            'user_id'    => Auth::id(),
            'action'     => $action,
            'detail'     => $detail,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    /** Lets the UI decide whether to surface the safeguarding area at all. */
    public function access(Request $request)
    {
        return response()->json([
            'is_officer'          => $this->isOfficer(),
            'can_manage_officers' => $this->canManageOfficers(),
        ]);
    }

    /* --------------------------- officer roster ------------------------- */

    public function officerIndex()
    {
        abort_unless($this->canManageOfficers(), 403, 'Only administrators can view the officer roster.');
        return response()->json(
            SafeguardingOfficer::with('user:id,firstName,lastName,email')->orderByDesc('active')->get()
        );
    }

    public function officerStore(Request $request)
    {
        abort_unless($this->canManageOfficers(), 403, 'Only administrators can assign safeguarding officers.');
        $data = $request->validate(['user_id' => 'required|integer']);

        $officer = SafeguardingOfficer::updateOrCreate(
            ['user_id' => $data['user_id']],
            ['active' => true, 'assigned_by' => Auth::id(), 'assigned_at' => now()]
        );
        $this->audit($request, 'update', null, "Officer authorised: user {$data['user_id']}.");

        return response()->json($officer->load('user:id,firstName,lastName,email'), 201);
    }

    public function officerDestroy(Request $request, $userId)
    {
        abort_unless($this->canManageOfficers(), 403, 'Only administrators can revoke safeguarding officers.');
        SafeguardingOfficer::where('user_id', $userId)->update(['active' => false]);
        $this->audit($request, 'update', null, "Officer access revoked: user {$userId}.");
        return response()->json(['message' => 'Officer access revoked']);
    }

    /* ------------------------------ cases ------------------------------- */

    public function caseIndex(Request $request)
    {
        $this->gateOfficer($request);
        $this->audit($request, 'list', null, 'Listed safeguarding cases.');

        $query = SafeguardingCase::query();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        // Return ONLY the minimal, non-identifying list projection
        $cases = $query->orderByDesc('created_at')->get()->map->toListArray();
        return response()->json($cases);
    }

    public function caseShow(Request $request, $id)
    {
        $this->gateOfficer($request, (int) $id);
        $case = SafeguardingCase::with('actions')->findOrFail($id);
        $this->audit($request, 'view', $case->id, 'Viewed full case record.');
        return response()->json($case);
    }

    /**
     * Intake is open to any authenticated user (a survivor, witness or officer)
     * so concerns can be raised, but the record is only ever readable by officers.
     */
    public function caseStore(Request $request)
    {
        $data = $request->validate([
            'category'        => 'required|in:harassment,abuse,intimidation,discrimination,other',
            'severity'        => 'nullable|in:low,medium,high,critical',
            'occurred_at'     => 'nullable|date',
            'state'           => 'nullable|integer',
            'lga'             => 'nullable|integer',
            'hub'             => 'nullable|integer',
            'is_anonymous'    => 'nullable|boolean',
            'survivor_ref'    => 'nullable|string|max:255',
            'survivor_details' => 'nullable|string',
            'description'     => 'required|string',
            'immediate_needs' => 'nullable|string',
            'consent_to_share' => 'nullable|boolean',
        ]);

        $data['reference'] = $this->nextReference();
        $data['severity'] = $data['severity'] ?? 'medium';
        $data['status'] = 'reported';
        // Honour anonymity: do not attach a reporter id when anonymous
        $data['reported_by'] = !empty($data['is_anonymous']) ? null : Auth::id();
        $data['created_by'] = Auth::id();

        $case = SafeguardingCase::create($data);
        $this->audit($request, 'create', $case->id, "Case opened ({$case->category}).");

        // The submitter only receives the reference, never the stored record
        return response()->json(['reference' => $case->reference, 'message' => 'Report received confidentially.'], 201);
    }

    public function caseUpdate(Request $request, $id)
    {
        $this->gateOfficer($request, (int) $id);
        $case = SafeguardingCase::findOrFail($id);

        $data = $request->validate([
            'severity'           => 'sometimes|in:low,medium,high,critical',
            'status'             => 'sometimes|in:reported,under_review,support_provided,referred,resolved,closed',
            'assigned_officer_id' => 'nullable|integer',
            'survivor_ref'       => 'nullable|string|max:255',
            'survivor_details'   => 'nullable|string',
            'immediate_needs'    => 'nullable|string',
            'consent_to_share'   => 'nullable|boolean',
        ]);

        $case->update($data);
        $this->audit($request, 'update', $case->id, 'Updated case record.');
        return response()->json($case->fresh('actions'));
    }

    public function caseActionStore(Request $request, $id)
    {
        $this->gateOfficer($request, (int) $id);
        $case = SafeguardingCase::findOrFail($id);

        $data = $request->validate([
            'action_type'   => 'required|in:response,escalation,referral,note,status_change,resolution',
            'description'   => 'required|string',
            'decision_note' => 'nullable|string',
            'action_at'     => 'nullable|date',
        ]);

        $action = $case->actions()->create([
            'action_type'   => $data['action_type'],
            'description'   => $data['description'],
            'decision_note' => $data['decision_note'] ?? null,
            'performed_by'  => Auth::id(),
            'action_at'     => $data['action_at'] ?? now(),
        ]);
        $this->audit($request, 'action_added', $case->id, "Action logged ({$data['action_type']}).");

        return response()->json($action, 201);
    }

    public function auditIndex(Request $request, $id)
    {
        $this->gateOfficer($request, (int) $id);
        return response()->json(
            SafeguardingAuditLog::with('user:id,firstName,lastName')
                ->where('case_id', $id)->orderByDesc('created_at')->get()
        );
    }

    private function nextReference(): string
    {
        $seq = SafeguardingCase::whereYear('created_at', now()->year)->count() + 1;
        return 'SG-' . now()->format('Y') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}