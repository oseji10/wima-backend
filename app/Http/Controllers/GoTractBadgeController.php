<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoTractApplication;
use App\Models\GoTractBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoTractBadgeController extends Controller
{
    /**
     * Generate a batch of blank, anonymous badges for pre-printing.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:2000'],
            'batch' => ['nullable', 'string', 'max:50'],
        ]);

        $badges = GoTractBadge::generateBatch($data['count'], $data['batch'] ?? null);

        return response()->json([
            'message' => "{$badges->count()} badges generated.",
            'data'    => $badges->map(fn ($b) => [
                'serial' => $b->serial,
                'token'  => $b->token,
                'batch'  => $b->batch,
            ])->values(),
        ], 201);
    }

    /**
     * Badges for the print sheet. Defaults to unassigned only.
     * ?batch=…  ?include_assigned=1  ?limit=…
     */
    public function sheet(Request $request): JsonResponse
    {
        $badges = GoTractBadge::query()
            ->when($request->filled('batch'), fn ($q) => $q->where('batch', $request->input('batch')))
            ->when(! $request->boolean('include_assigned'), fn ($q) => $q->whereNull('application_id'))
            ->orderBy('id')
            ->limit($request->integer('limit', 1000))
            ->get(['serial', 'token', 'batch', 'application_id']);

        return response()->json([
            'data' => $badges->map(fn ($b) => [
                'serial'     => $b->serial,
                'token'      => $b->token,
                'batch'      => $b->batch,
                'isAssigned' => ! is_null($b->application_id),
            ])->values(),
        ]);
    }

    /**
     * Existing print batches, with counts — so staff can reprint a run.
     */
    public function batches(): JsonResponse
    {
        $batches = GoTractBadge::query()
            ->selectRaw('batch, count(*) as total, sum(case when application_id is null then 1 else 0 end) as unassigned')
            ->groupBy('batch')
            ->orderByDesc('batch')
            ->get();

        return response()->json(['data' => $batches]);
    }

    /**
     * Desk: bind a pre-printed badge serial to an accredited participant.
     */
    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serial'         => ['required', 'string'],
            'application_id' => ['required', 'integer', 'exists:gotract_applications,id'],
        ]);

        $badge = GoTractBadge::whereRaw('UPPER(TRIM(serial)) = ?', [strtoupper(trim($data['serial']))])->first();

        if (! $badge) {
            return response()->json(['message' => 'That badge serial does not exist.'], 404);
        }

        $application = GoTractApplication::findOrFail($data['application_id']);

        if (! $application->accredited_at) {
            return response()->json(['message' => 'Accredit the participant before assigning a badge.'], 422);
        }

        // Already taken by someone else?
        if ($badge->application_id && $badge->application_id !== $application->id) {
            return response()->json([
                'message' => 'That badge is already assigned to ' . optional($badge->application)->full_name . '.',
            ], 409);
        }

        // Does this participant already hold a different badge? Release it.
        GoTractBadge::where('application_id', $application->id)
            ->where('id', '!=', $badge->id)
            ->update(['application_id' => null, 'assigned_at' => null, 'assigned_by' => null]);

        $badge->update([
            'application_id' => $application->id,
            'assigned_at'    => now(),
            'assigned_by'    => optional(Auth::user())->id,
        ]);

        return response()->json([
            'message' => "Badge {$badge->serial} assigned to {$application->full_name}.",
            'data'    => [
                'serial'      => $badge->serial,
                'fullName'    => $application->full_name,
                'referenceId' => $application->reference_id,
                'lga'         => $application->lga,
            ],
        ]);
    }

    /**
     * Desk: release a badge (e.g. lost/damaged) so a new one can be issued.
     */
    public function unassign(Request $request): JsonResponse
    {
        $data = $request->validate(['serial' => ['required', 'string']]);

        $badge = GoTractBadge::whereRaw('UPPER(TRIM(serial)) = ?', [strtoupper(trim($data['serial']))])->firstOrFail();

        $badge->update(['application_id' => null, 'assigned_at' => null, 'assigned_by' => null]);

        return response()->json(['message' => "Badge {$badge->serial} released."]);
    }

    /**
     * Counters for the badge screen.
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => [
            'total'      => GoTractBadge::count(),
            'assigned'   => GoTractBadge::whereNotNull('application_id')->count(),
            'unassigned' => GoTractBadge::whereNull('application_id')->count(),
        ]]);
    }
}