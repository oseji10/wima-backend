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
     * Public: which LGAs are still accepting applications. Capacity counts
     * only — no personal data — so the application form can grey out full LGAs.
     */
    public function lgaAvailability(): JsonResponse
    {
        $cap = (int) config('gotract.application_cap_per_lga');

        $counts = GoTractApplication::query()
            ->selectRaw('lga, count(*) as total')
            ->groupBy('lga')
            ->pluck('total', 'lga');

        $lgas = collect(config('gotract.lgas'))->map(function ($lga) use ($counts, $cap) {
            $received = (int) ($counts[$lga] ?? 0);
            $remaining = $cap > 0 ? max(0, $cap - $received) : null;
            return [
                'lga'       => $lga,
                'cap'       => $cap,
                'received'  => $received,
                'remaining' => $remaining,
                'open'      => $cap <= 0 || $received < $cap,
            ];
        })->values();

        return response()->json(['data' => $lgas]);
    }

    /**
     * Public (token-gated), read-only oversight figures for government partners.
     * Returns AGGREGATE data only — never any personal / identifying fields —
     * so it can safely sit behind a shareable, login-less link.
     */
    public function oversight(Request $request): JsonResponse
    {
        $expected = config('gotract.oversight_token');
        $provided = $request->query('token') ?: $request->bearerToken();

        abort_if(
            empty($expected) || ! is_string($provided) || ! hash_equals($expected, $provided),
            403,
            'Invalid or missing access token.'
        );

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

        $lgas = collect(config('gotract.lgas'))->map(function ($lga) use ($perLga, $statuses, $target) {
            $counts = $perLga[$lga] ?? [];
            $row = ['lga' => $lga, 'total' => $counts['total'] ?? 0, 'target' => $target];
            foreach ($statuses as $status) {
                $row[$status] = $counts[$status] ?? 0;
            }
            return $row;
        })->values();

        $total    = GoTractApplication::count();
        $approved = $byStatus['approved'] ?? 0;

        $gender = GoTractApplication::query()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        $ageBands = [
            '18-25' => GoTractApplication::whereBetween('age', [18, 25])->count(),
            '26-35' => GoTractApplication::whereBetween('age', [26, 35])->count(),
            '36+'   => GoTractApplication::where('age', '>=', 36)->count(),
        ];

        return response()->json(['data' => [
            'generatedAt'  => now()->toIso8601String(),
            'total'        => $total,
            'approved'     => $approved,
            'approvalRate' => $total ? (int) round(($approved / $total) * 100) : 0,
            'targetPerLga' => $target,
            'totalTarget'  => (int) config('gotract.total_target'),
            'byStatus'     => $byStatus,
            'gender'       => $gender,
            'ageBands'     => $ageBands,
            'lgas'         => $lgas,
        ]]);
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

    /**
     * PUBLIC, read-only programme overview for government partners.
     * Aggregate figures ONLY — no applicant records or personal data are
     * exposed here, so it is safe behind a shareable link. An optional shared
     * token (config gotract.public_token) keeps the link from being guessable.
     */
    public function publicOverview(Request $request): JsonResponse
    {
        $expected = config('gotract.public_token');
        if ($expected && ! hash_equals((string) $expected, (string) $request->query('token'))) {
            abort(403, 'Invalid or missing access token.');
        }

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

        $byGender = GoTractApplication::query()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        $target = (int) config('gotract.target_per_lga');

        $lgas = collect(config('gotract.lgas'))->map(fn ($lga) => [
            'lga'      => $lga,
            'total'    => $perLga[$lga]['total'] ?? 0,
            'approved' => $perLga[$lga]['approved'] ?? 0,
            'target'   => $target,
        ])->values();

        return response()->json(['data' => [
            'total'         => GoTractApplication::count(),
            'approvedTotal' => $byStatus['approved'] ?? 0,
            'byStatus'      => $byStatus,
            'byGender'      => $byGender,
            'targetPerLga'  => $target,
            'totalTarget'   => (int) config('gotract.total_target'),
            'lgas'          => $lgas,
            'generatedAt'   => now()->toIso8601String(),
        ]]);
    }
}