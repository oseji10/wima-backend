<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoTractApplicationRequest;
use App\Http\Resources\GoTractApplicationResource;
use App\Models\GoTractApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoTractApplicationController extends Controller
{
    /**
     * Public: submit a new GoTRACT application.
     */
    public function store(StoreGoTractApplicationRequest $request): JsonResponse
    {
        $application = GoTractApplication::create($request->mappedData());

        return (new GoTractApplicationResource($application))
            ->additional(['message' => 'Application submitted successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Admin: paginated, filterable list of applications.
     * Filters: ?lga=, ?status=, ?search=, ?per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $applications = GoTractApplication::query()
            ->latest()
            ->when($request->filled('lga'), fn ($q) => $q->where('lga', $request->input('lga')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->input('search');
                $q->where(function ($w) use ($term) {
                    $w->where('full_name', 'like', "%{$term}%")
                        ->orWhere('phone_number', 'like', "%{$term}%")
                        ->orWhere('reference_id', 'like', "%{$term}%")
                        ->orWhere('national_id', 'like', "%{$term}%");
                });
            })
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return response()->json($applications);
    }

    /**
     * Admin: full record for a single application.
     */
    public function show(GoTractApplication $application): JsonResponse
    {
        return response()->json(['data' => $application]);
    }

    /**
     * Admin: move an application through the screening workflow.
     */
    public function updateStatus(Request $request, GoTractApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(config('gotract.statuses'))],
        ]);

        $application->update($validated);

        return response()->json([
            'message' => 'Application status updated.',
            'data'    => [
                'referenceId' => $application->reference_id,
                'status'      => $application->status,
            ],
        ]);
    }

    /**
     * Admin: move many applications through the workflow at once.
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
            'status' => ['required', Rule::in(config('gotract.statuses'))],
        ]);

        $updated = GoTractApplication::whereIn('id', $validated['ids'])
            ->update(['status' => $validated['status']]);

        return response()->json([
            'message' => "{$updated} application(s) updated.",
            'data'    => ['updated' => $updated, 'status' => $validated['status']],
        ]);
    }

    /**
     * Admin: programme dashboard counts vs. targets.
     */
    public function stats(): JsonResponse
    {
        // Single grouped query gives us both the overall status totals and the
        // per-LGA-per-status breakdown used by the "Enrollment by LGA" analysis.
        $rows = GoTractApplication::query()
            ->selectRaw('lga, status, count(*) as total')
            ->groupBy('lga', 'status')
            ->get();

        $byStatus = [];
        $perLga = [];
        foreach ($rows as $row) {
            $count = (int) $row->total;
            $byStatus[$row->status] = ($byStatus[$row->status] ?? 0) + $count;
            $perLga[$row->lga][$row->status] = $count;
            $perLga[$row->lga]['total'] = ($perLga[$row->lga]['total'] ?? 0) + $count;
        }

        $target   = (int) config('gotract.target_per_lga');
        $statuses = config('gotract.statuses');

        // One row per configured LGA so LGAs with no applications still appear.
        $lgas = collect(config('gotract.lgas'))->map(function ($lga) use ($perLga, $statuses, $target) {
            $counts = $perLga[$lga] ?? [];
            $row = ['lga' => $lga, 'total' => $counts['total'] ?? 0, 'target' => $target];
            foreach ($statuses as $status) {
                $row[$status] = $counts[$status] ?? 0;
            }
            return $row;
        })->values();

        return response()->json(['data' => [
            'total'        => GoTractApplication::count(),
            'targetPerLga' => $target,
            'totalTarget'  => config('gotract.total_target'),
            'byStatus'     => $byStatus,
            'lgas'         => $lgas,
        ]]);
    }
}