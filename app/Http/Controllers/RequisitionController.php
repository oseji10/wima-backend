<?php
namespace App\Http\Controllers;

use App\Models\RequestApproval;
use App\Models\RequestItem;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequisitionController extends Controller
{
    public function __construct(private WorkflowEngine $engine) {}


    public function index(Request $request): JsonResponse
    {
        $query = ServiceRequest::with(['type', 'employee:id,firstName,lastName'])
            ->whereHas('type', fn ($q) => $q->where('code', 'general_requisition'))
            ->where('employee_id', $request->user()->id);

        if ($status = $request->query('status')) $query->where('status', $status);
        if ($search = $request->query('search')) $query->where('title', 'like', "%{$search}%");

        return response()->json($query->orderByDesc('id')->paginate($request->query('per_page', 10)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'needed_by' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'justification' => ['nullable', 'string'],
            'preferred_vendor' => ['nullable', 'string'],
            'alternative_vendor' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'submit' => ['required', 'boolean'], // false = save as draft
        ]);

        $type = RequestType::where('code', 'general_requisition')->firstOrFail();

        $req = DB::transaction(function () use ($data, $type, $request) {
            $req = ServiceRequest::create([
                'request_no' => 'REQ-' . strtoupper(Str::random(8)),
                'request_type_id' => $type->id,
                'employee_id' => $request->user()->id,
                'department_id' => $request->user()->department_id ?? null,
                'title' => $data['title'],
                'priority' => $data['priority'],
                'needed_by' => $data['needed_by'] ?? null,
                'description' => $data['description'] ?? null,
                'justification' => $data['justification'] ?? null,
                'preferred_vendor' => $data['preferred_vendor'] ?? null,
                'alternative_vendor' => $data['alternative_vendor'] ?? null,
                'status' => 'draft',
                'state' => $request->user()->state,
                'lga' => $request->user()->lga,
            ]);

            foreach ($data['items'] as $item) {
                RequestItem::create([
                    'request_id' => $req->id,
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? null,
                    'unit_cost' => $item['unit_cost'],
                ]);
            }
            $req->recalcTotal();

            if ($data['submit']) {
                $this->engine->submit($req->fresh());
            }

            return $req->fresh(['items', 'approvals.step']);
        });

        return response()->json(['message' => 'Request saved.', 'data' => $req], 201);
    }

    public function show(ServiceRequest $req): JsonResponse
    {
        $req->load(['type', 'employee:id,firstName,lastName', 'items', 'attachments', 'approvals.step', 'approvals.approver:id,firstName,lastName']);
        return response()->json(['data' => $req]);
    }





    public function dashboard(Request $request): JsonResponse
{
    $userId = $request->user()->id;
    $base = ServiceRequest::where('employee_id', $userId);

    $awaitingMyApproval = DB::table('request_approvals')
        ->join('requests', 'requests.id', '=', 'request_approvals.request_id')
        ->join('approval_steps', 'approval_steps.id', '=', 'request_approvals.approval_step_id')
        ->where('request_approvals.status', 'pending')
        ->where('approval_steps.role_id', $request->user()->role)
        ->whereColumn('approval_steps.step_order', 'requests.current_step')
        ->count();

    return response()->json([
        'total' => (clone $base)->count(),
        'pending_approval' => (clone $base)->where('status', 'pending_approval')->count(),
        'approved' => (clone $base)->where('status', 'approved')->count(),
        'rejected' => (clone $base)->where('status', 'rejected')->count(),
        'completed' => (clone $base)->where('status', 'completed')->count(),
        'draft' => (clone $base)->where('status', 'draft')->count(),
        'awaiting_my_approval' => $awaitingMyApproval,
    ]);
}

public function myApprovals(Request $request): JsonResponse
{
    $roleId = $request->user()->role;

    $requestIds = DB::table('request_approvals')
        ->join('requests', 'requests.id', '=', 'request_approvals.request_id')
        ->join('approval_steps', 'approval_steps.id', '=', 'request_approvals.approval_step_id')
        ->where('request_approvals.status', 'pending')
        ->where('approval_steps.role_id', $roleId)
        ->whereColumn('approval_steps.step_order', 'requests.current_step')
        ->pluck('requests.id');

    $requests = ServiceRequest::whereIn('id', $requestIds)
        ->with(['type', 'employee:id,firstName,lastName'])
        ->orderByDesc('submitted_at')
        ->get();

    return response()->json(['data' => $requests]);
}

public function decide(Request $request, ServiceRequest $req): JsonResponse
{
    $data = $request->validate([
        'decision' => ['required', 'in:approved,rejected,clarification_requested,returned'],
        'comments' => ['nullable', 'string', 'max:1000'],
    ]);

    $pendingApproval = $req->approvals()
        ->where('status', 'pending')
        ->whereHas('step', fn ($q) => $q->where('step_order', $req->current_step))
        ->with('step')
        ->first();

    if (! $pendingApproval || (int) $pendingApproval->step->role_id !== (int) $request->user()->role) {
        return response()->json(['message' => 'You are not authorized to act on this request at its current step.'], 403);
    }

    $approval = $this->engine->decide($req, $request->user()->id, $data['decision'], $data['comments'] ?? null);

    return response()->json(['message' => 'Decision recorded.', 'data' => $approval]);
}


public function markPaid(Request $request, ServiceRequest $req): JsonResponse
{
    $financeRoleId = Role::where('roleName', 'Finance')->value('roleId');
    if ((int) $request->user()->role !== (int) $financeRoleId) {
        return response()->json(['message' => 'Only Finance can process payment.'], 403);
    }
    if ($req->status !== 'approved') {
        return response()->json(['message' => 'Request must be fully approved before payment.'], 422);
    }

    $data = $request->validate([
        'payment_reference' => ['required', 'string', 'max:100'],
        'amount_paid' => ['nullable', 'numeric', 'min:0'],
    ]);

    $req->update([
        'status' => 'completed',
        'payment_reference' => $data['payment_reference'],
        'paid_amount' => $data['amount_paid'] ?? $req->total_amount,
        'paid_at' => now(),
        'paid_by' => $request->user()->id,
    ]);

    return response()->json(['message' => 'Payment recorded.', 'data' => $req]);
}


public function allIndex(Request $request): JsonResponse
{
    $query = ServiceRequest::with(['type', 'employee:id,firstName,lastName', 'vendor:id,name'])
        ->whereHas('type', fn ($q) => $q->where('code', 'general_requisition'));

    if ($status = $request->query('status')) {
        $query->where('status', $status);
    }
    if ($priority = $request->query('priority')) {
        $query->where('priority', $priority);
    }
    if ($search = $request->query('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('request_no', 'like', "%{$search}%")
              ->orWhereHas('employee', function ($eq) use ($search) {
                  $eq->where('firstName', 'like', "%{$search}%")->orWhere('lastName', 'like', "%{$search}%");
              });
        });
    }
    if ($from = $request->query('from')) {
        $query->whereDate('submitted_at', '>=', $from);
    }
    if ($to = $request->query('to')) {
        $query->whereDate('submitted_at', '<=', $to);
    }

    return response()->json(
        $query->orderByDesc('id')->paginate($request->query('per_page', 10))
    );
}
}