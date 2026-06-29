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
     * Admin: programme dashboard counts vs. targets.
     */
    public function stats(): JsonResponse
    {
        $byLga = GoTractApplication::query()
            ->selectRaw('lga, count(*) as total')
            ->groupBy('lga')
            ->pluck('total', 'lga');

        $byStatus = GoTractApplication::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json(['data' => [
            'total'         => GoTractApplication::count(),
            'targetPerLga'  => config('gotract.target_per_lga'),
            'totalTarget'   => config('gotract.total_target'),
            'byLga'         => $byLga,
            'byStatus'      => $byStatus,
        ]]);
    }
}