<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\RequestApproval;

class WorkflowEngine
{
    public function submit(ServiceRequest $request): void
    {
        $steps = $request->type->workflow?->steps ?? collect();

        // Only steps with no limit, or whose limit the request amount meets/exceeds, apply.
        $applicable = $steps->filter(fn ($s) => is_null($s->approval_limit) || $request->total_amount >= $s->approval_limit)
            ->values();

        if ($applicable->isEmpty()) {
            $request->update(['status' => 'approved', 'current_step' => 0, 'submitted_at' => now()]);
            return;
        }

        foreach ($applicable as $step) {
            RequestApproval::create([
                'request_id' => $request->id,
                'approval_step_id' => $step->id,
                'status' => 'pending',
            ]);
        }

        $request->update([
            'status' => 'pending_approval',
            'current_step' => $applicable->first()->step_order,
            'submitted_at' => now(),
        ]);
    }

    public function decide(ServiceRequest $request, int $approverId, string $decision, ?string $comments): RequestApproval
    {
        $approval = $request->approvals()
            ->whereHas('step', fn ($q) => $q->where('step_order', $request->current_step))
            ->where('status', 'pending')
            ->firstOrFail();

        $approval->update([
            'approver_id' => $approverId,
            'status' => $decision, // 'approved' | 'rejected' | 'clarification_requested' | 'returned'
            'comments' => $comments,
            'acted_at' => now(),
        ]);

        if ($decision === 'rejected') {
            $request->update(['status' => 'rejected']);
        } elseif ($decision === 'clarification_requested' || $decision === 'returned') {
            $request->update(['status' => 'draft', 'current_step' => 0]);
            $request->approvals()->where('status', 'pending')->delete();
        } elseif ($decision === 'approved') {
            $next = $request->approvals()
                ->whereHas('step', fn ($q) => $q->where('step_order', '>', $request->current_step))
                ->where('status', 'pending')
                ->with('step')
                ->get()
                ->sortBy(fn ($a) => $a->step->step_order)
                ->first();

            if ($next) {
                $request->update(['current_step' => $next->step->step_order]);
            } else {
                $request->update(['status' => 'approved']);
            }
        }

        return $approval;
    }
}