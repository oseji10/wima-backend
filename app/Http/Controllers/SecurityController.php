<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\SecurityIncident;
use App\Models\IncidentAction;
use App\Models\SecurityVendor;
use App\Models\VendorCoverage;
use App\Models\Hubs;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;

/**
 * General security & safety incidents, the vendor register and location-based
 * risk mapping. Gender-based / safeguarding cases live in SafeguardingController
 * with separate storage and access control.
 */
class SecurityController extends Controller
{
    private const SEVERITY_WEIGHT = ['low' => 1, 'medium' => 3, 'high' => 7, 'critical' => 12];

    /* =======================================================================
     |  Role scoping  (incidents carry state / lga / hub)
     * ===================================================================== */

    private function scope(): array
    {
        $user = Auth::user();
        if (in_array((int) $user->role, [1, 3], true)) {
            return ['all' => true];
        }
        if ((int) $user->role === 4) {
            $stateId = optional(StateCoordinators::where('userId', $user->id)->first())->stateId;
            return ['all' => false, 'column' => 'state', 'value' => $stateId];
        }
        if ((int) $user->role === 5) {
            $lga = optional(CommunityLead::where('userId', $user->id)->first())->lga;
            return ['all' => false, 'column' => 'lga', 'value' => $lga];
        }
        return ['all' => false, 'column' => 'state', 'value' => -1];
    }

    private function applyScope($query)
    {
        $s = $this->scope();
        if (!$s['all']) {
            $query->where($s['column'], $s['value']);
        }
        return $query;
    }

    private function assertScope(SecurityIncident $incident): void
    {
        $s = $this->scope();
        if (!$s['all'] && (int) $incident->{$s['column']} !== (int) $s['value']) {
            abort(403, 'You do not have access to this incident.');
        }
    }

    private function isManager(): bool
    {
        return in_array((int) Auth::user()->role, [1, 3], true);
    }

    /* =======================================================================
     |  Location options (states / lgas / hubs), scoped
     * ===================================================================== */

    public function locations()
    {
        $query = Hubs::query()->with(['states', 'lgas']);
        $s = $this->scope();
        if (!$s['all']) {
            $query->where($s['column'], $s['value']);
        }
        $hubs = $query->get();

        $hubList = $hubs->map(fn ($h) => [
            'id'         => $h->hubId,
            'name'       => optional($h->lgas)->lgaName ?? optional($h->lgas)->name ?? "Hub {$h->hubId}",
            'state_id'   => $h->state,
            'state_name' => optional($h->states)->stateName ?? optional($h->states)->name,
            'lga_id'     => $h->lga,
            'lga_name'   => optional($h->lgas)->lgaName ?? optional($h->lgas)->name,
        ])->values();

        $states = $hubList->whereNotNull('state_id')->unique('state_id')
            ->map(fn ($h) => ['id' => $h['state_id'], 'name' => $h['state_name']])->values();
        $lgas = $hubList->whereNotNull('lga_id')->unique('lga_id')
            ->map(fn ($h) => ['id' => $h['lga_id'], 'name' => $h['lga_name'], 'state_id' => $h['state_id']])->values();

        return response()->json(['states' => $states, 'lgas' => $lgas, 'hubs' => $hubList]);
    }

    /* =======================================================================
     |  Dashboard
     * ===================================================================== */

    public function dashboard()
    {
        $incidents = $this->applyScope(SecurityIncident::query())->get();
        $open = $incidents->where('is_open', true);

        return response()->json([
            'total'        => $incidents->count(),
            'open'         => $open->count(),
            'under_investigation' => $incidents->where('status', 'under_investigation')->count(),
            'resolved'     => $incidents->whereIn('status', ['resolved', 'closed'])->count(),
            'by_severity'  => $incidents->groupBy('severity')->map->count(),
            'by_type'      => $incidents->groupBy('type')->map->count(),
            'critical_open' => $open->where('severity', 'critical')->count(),
            'vendors'      => SecurityVendor::where('status', 'active')->count(),
            'high_risk_zones' => $this->riskZones($incidents)->where('band', 'high')->count()
                + $this->riskZones($incidents)->where('band', 'critical')->count(),
        ]);
    }

    /* =======================================================================
     |  Incidents
     * ===================================================================== */

    public function incidentIndex(Request $request)
    {
        $query = $this->applyScope(SecurityIncident::with('assignee:id,firstName,lastName', 'hubInfo.lgas'));

        foreach (['type', 'severity', 'status', 'state', 'lga', 'hub'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->query($f));
            }
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderByDesc('occurred_at')->paginate($request->query('per_page', 25))
        );
    }

    public function incidentShow($id)
    {
        $incident = SecurityIncident::with('actions.performer:id,firstName,lastName', 'assignee:id,firstName,lastName', 'hubInfo.lgas', 'hubInfo.states')
            ->findOrFail($id);
        $this->assertScope($incident);
        return response()->json($incident);
    }

    public function incidentStore(Request $request)
    {
        $data = $request->validate([
            'type'         => 'required|in:bandit_attack,terrorism,community_unrest,theft_vandalism,equipment_accident,health_safety,other',
            'severity'     => 'required|in:low,medium,high,critical',
            'occurred_at'  => 'required|date',
            'state'        => 'nullable|integer',
            'lga'          => 'nullable|integer',
            'hub'          => 'nullable|integer',
            'location_note' => 'nullable|string|max:255',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'affected_persons'       => 'nullable|string',
            'affected_persons_count' => 'nullable|integer|min:0',
            'affected_assets'        => 'nullable|string',
            'equipment_id'           => 'nullable|integer',
            'assigned_to'  => 'nullable|integer',
            'assigned_team' => 'nullable|string|max:255',
        ]);

        // Enforce scope on write
        $s = $this->scope();
        if (!$s['all'] && (int) ($data[$s['column']] ?? 0) !== (int) $s['value']) {
            abort(403, "You can only report incidents in your assigned {$s['column']}.");
        }

        $data['reference'] = $this->nextReference();
        $data['status'] = 'open';
        $data['reported_by'] = Auth::id();
        $data['created_by'] = Auth::id();

        $incident = SecurityIncident::create($data);
        $this->logAction($incident, 'note', 'Incident reported.');

        return response()->json($incident->fresh('actions'), 201);
    }

    public function incidentUpdate(Request $request, $id)
    {
        $incident = SecurityIncident::findOrFail($id);
        $this->assertScope($incident);

        $data = $request->validate([
            'severity'      => 'sometimes|in:low,medium,high,critical',
            'status'        => 'sometimes|in:open,under_investigation,resolved,closed',
            'assigned_to'   => 'nullable|integer',
            'assigned_team' => 'nullable|string|max:255',
            'resolution'    => 'nullable|string',
            'title'         => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'affected_persons'       => 'nullable|string',
            'affected_persons_count' => 'nullable|integer|min:0',
            'affected_assets'        => 'nullable|string',
        ]);

        $wasStatus = $incident->status;
        $wasAssignee = $incident->assigned_to;

        // Stamp resolution
        if (($data['status'] ?? null) === 'resolved' && $incident->status !== 'resolved') {
            $data['resolved_at'] = now();
        }

        $incident->update($data);

        // Timeline entries for meaningful transitions
        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] != $wasAssignee) {
            $this->logAction($incident, 'assignment', 'Incident assigned.');
        }
        if (($data['status'] ?? $wasStatus) !== $wasStatus) {
            $this->logAction($incident, $data['status'] === 'resolved' ? 'resolution' : 'status_change',
                "Status changed from {$wasStatus} to {$incident->status}.",
                $data['resolution'] ?? null);
        }

        return response()->json($incident->fresh('actions.performer:id,firstName,lastName', 'assignee:id,firstName,lastName'));
    }

    public function incidentActionStore(Request $request, $id)
    {
        $incident = SecurityIncident::findOrFail($id);
        $this->assertScope($incident);

        $data = $request->validate([
            'action_type'   => 'required|in:response,escalation,note,assignment,status_change,resolution',
            'description'   => 'required|string',
            'decision_note' => 'nullable|string',
            'action_at'     => 'nullable|date',
        ]);

        if ($data['action_type'] === 'escalation') {
            $incident->increment('escalation_level');
        }

        $action = $this->logAction($incident, $data['action_type'], $data['description'], $data['decision_note'] ?? null, $data['action_at'] ?? null);
        return response()->json($action->load('performer:id,firstName,lastName'), 201);
    }

    public function incidentDestroy($id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can delete incidents.');
        $incident = SecurityIncident::findOrFail($id);
        $incident->actions()->delete();
        $incident->delete();
        return response()->json(['message' => 'Incident removed']);
    }

    private function logAction(SecurityIncident $incident, string $type, string $desc, ?string $note = null, $at = null): IncidentAction
    {
        return $incident->actions()->create([
            'action_type'   => $type,
            'description'   => $desc,
            'decision_note' => $note,
            'performed_by'  => Auth::id(),
            'action_at'     => $at ?: now(),
        ]);
    }

    private function nextReference(): string
    {
        $seq = SecurityIncident::whereYear('created_at', now()->year)->count() + 1;
        return 'SEC-' . now()->format('Y') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /* =======================================================================
     |  Security vendor register
     * ===================================================================== */

    public function vendorIndex(Request $request)
    {
        $query = SecurityVendor::with('coverage');
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($state = $request->query('state')) {
            $query->whereHas('coverage', fn ($q) => $q->where('state', $state));
        }
        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        return response()->json($query->orderBy('name')->get());
    }

    public function vendorStore(Request $request)
    {
        abort_unless($this->isManager() || (int) Auth::user()->role === 4, 403, 'You cannot manage vendors.');
        $data = $this->validateVendor($request);

        $vendor = DB::transaction(function () use ($data) {
            $vendor = SecurityVendor::create(collect($data)->except('coverage')->merge(['created_by' => Auth::id()])->toArray());
            foreach ($data['coverage'] ?? [] as $c) {
                $vendor->coverage()->create([
                    'state' => $c['state'] ?? null,
                    'lga'   => $c['lga'] ?? null,
                    'hub'   => $c['hub'] ?? null,
                ]);
            }
            return $vendor;
        });

        return response()->json($vendor->load('coverage'), 201);
    }

    public function vendorUpdate(Request $request, $id)
    {
        abort_unless($this->isManager() || (int) Auth::user()->role === 4, 403, 'You cannot manage vendors.');
        $vendor = SecurityVendor::findOrFail($id);
        $data = $this->validateVendor($request, false);

        DB::transaction(function () use ($vendor, $data) {
            $vendor->update(collect($data)->except('coverage')->toArray());
            if (array_key_exists('coverage', $data)) {
                $vendor->coverage()->delete();
                foreach ($data['coverage'] as $c) {
                    $vendor->coverage()->create([
                        'state' => $c['state'] ?? null,
                        'lga'   => $c['lga'] ?? null,
                        'hub'   => $c['hub'] ?? null,
                    ]);
                }
            }
        });

        return response()->json($vendor->fresh('coverage'));
    }

    public function vendorDestroy($id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can delete vendors.');
        $vendor = SecurityVendor::findOrFail($id);
        $vendor->coverage()->delete();
        $vendor->delete();
        return response()->json(['message' => 'Vendor removed']);
    }

    private function validateVendor(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'name'           => ($creating ? 'required' : 'sometimes') . '|string|max:255',
            'type'           => 'nullable|in:public,private',
            'contact_name'   => 'nullable|string|max:255',
            'contact_phone'  => 'nullable|string|max:40',
            'contact_email'  => 'nullable|email',
            'service_scope'  => 'nullable|string',
            'status'         => 'nullable|in:active,inactive',
            'notes'          => 'nullable|string',
            'coverage'       => 'nullable|array',
            'coverage.*.state' => 'nullable|integer',
            'coverage.*.lga'   => 'nullable|integer',
            'coverage.*.hub'   => 'nullable|integer',
        ]);
    }

    /* =======================================================================
     |  Location-based risk mapping
     * ===================================================================== */

    public function riskMap(Request $request)
    {
        $query = $this->applyScope(SecurityIncident::query());
        if ($from = $request->query('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }
        $incidents = $query->get();

        $zones = $this->riskZones($incidents);

        // Attach location names + vendor coverage counts
        $locations = $this->locations()->getData(true);
        $stateNames = collect($locations['states'])->keyBy('id');
        $vendorCoverage = VendorCoverage::whereNotNull('state')->get()->groupBy('state')->map->count();

        $zones = $zones->map(function ($z) use ($stateNames, $vendorCoverage) {
            $z['state_name'] = $stateNames[$z['state']]['name'] ?? "State {$z['state']}";
            $z['vendor_coverage'] = $vendorCoverage[$z['state']] ?? 0;
            $z['coverage_gap'] = in_array($z['band'], ['high', 'critical'], true) && ($vendorCoverage[$z['state']] ?? 0) === 0;
            return $z;
        })->sortByDesc('score')->values();

        return response()->json([
            'zones'           => $zones,
            'high_risk_zones' => $zones->whereIn('band', ['high', 'critical'])->values(),
            'coverage_gaps'   => $zones->where('coverage_gap', true)->values(),
        ]);
    }

    /** Aggregate incidents by state into a severity-weighted risk score + band. */
    private function riskZones($incidents)
    {
        return $incidents->whereNotNull('state')->groupBy('state')->map(function ($group, $state) {
            $score = $group->sum(fn ($i) => self::SEVERITY_WEIGHT[$i->severity] ?? 1);
            $band = $score >= 40 ? 'critical' : ($score >= 20 ? 'high' : ($score >= 8 ? 'medium' : 'low'));
            return [
                'state'        => (int) $state,
                'incidents'    => $group->count(),
                'open'         => $group->where('is_open', true)->count(),
                'score'        => $score,
                'band'         => $band,
                'last_incident' => optional($group->max('occurred_at'))->toDateString(),
                'top_type'     => $group->groupBy('type')->map->count()->sortDesc()->keys()->first(),
            ];
        })->values();
    }
}