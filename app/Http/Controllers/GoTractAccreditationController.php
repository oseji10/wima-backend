<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoTractApplication;
use App\Models\GoTractScan;
use App\Models\GoTractBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GoTractAccreditationController extends Controller
{
    /**
     * Desk: search approved applicants to accredit (by name, phone, reference).
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('search'));

        $applications = GoTractApplication::query()
            ->whereIn('status', config('gotract.accreditable_statuses', ['pending', 'screening', 'approved']))
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->where('full_name', 'like', "%{$term}%")
                        ->orWhere('phone_number', 'like', "%{$term}%")
                        ->orWhere('reference_id', 'like', "%{$term}%")
                        ->orWhere('national_id', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('lga'), fn ($q) => $q->where('lga', $request->input('lga')))
            ->when($request->boolean('pending_only'), fn ($q) => $q->whereNull('accredited_at'))
            ->orderBy('full_name')
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($a) => $this->participantPayload($a));

        return response()->json($applications);
    }

    /**
     * Desk: accredit a participant and issue their badge (QR) token.
     * Idempotent — re-accrediting returns the existing badge.
     */
    public function accredit(Request $request, GoTractApplication $application): JsonResponse
    {
        $allowed = config('gotract.accreditable_statuses', ['pending', 'screening', 'approved']);

        // Everyone starts as `pending`, so we accredit on status — turning away
        // only applicants who were explicitly rejected.
        if (! in_array($application->status, $allowed, true)) {
            return response()->json([
                'message' => "This applicant is {$application->status} and cannot be accredited.",
            ], 422);
        }

        if (! $application->accredited_at) {
            $newStatus = config('gotract.status_on_accreditation', 'approved');

            $application->forceFill(array_filter([
                'accredited_at' => now(),
                'accredited_by' => optional(Auth::user())->id,
                'status'        => $newStatus,
            ]))->save();
        }

        return response()->json([
            'message' => 'Participant accredited.',
            'data'    => $this->participantPayload($application->fresh()),
        ]);
    }

    /**
     * Scanner: verify a badge and record a meal / attendance scan.
     * The unique index on (application_id, type, session) is what actually
     * prevents a second meal — we surface it as a friendly "already claimed".
     */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'   => ['required', 'string'],
            'type'    => ['required', Rule::in(config('gotract.scan_types'))],
            'session' => ['required', 'string'],
        ]);

        // The QR carries a badge token. Accept the printed serial too, so the
        // manual-entry fallback works when a QR is damaged.
        $key = strtoupper(trim($data['token']));
        $badge = \App\Models\GoTractBadge::where('token', $key)
            ->orWhereRaw('UPPER(TRIM(serial)) = ?', [$key])
            ->first();

        if (! $badge) {
            return response()->json([
                'result'  => 'invalid',
                'message' => 'Badge not recognised. This QR code is not valid.',
            ], 404);
        }

        if (! $badge->application_id) {
            return response()->json([
                'result'  => 'unassigned',
                'message' => "Badge {$badge->serial} has not been assigned to anyone. Send them to the accreditation desk.",
                'serial'  => $badge->serial,
            ], 422);
        }

        $application = $badge->application;

        if (! $application || ! $application->accredited_at) {
            return response()->json([
                'result'  => 'not_accredited',
                'message' => 'This participant has not been accredited.',
                'data'    => $application ? $this->participantPayload($application) : null,
            ], 422);
        }

        $existing = GoTractScan::where('application_id', $application->id)
            ->where('type', $data['type'])
            ->where('session', $data['session'])
            ->first();

        if ($existing) {
            return response()->json([
                'result'  => 'duplicate',
                'message' => $data['type'] === 'meal'
                    ? 'Meal already collected for this session.'
                    : 'Attendance already recorded for this session.',
                'data'    => $this->participantPayload($application, $badge->serial),
                'scanned_at' => $existing->scanned_at->toIso8601String(),
            ], 409);
        }

        GoTractScan::create([
            'application_id' => $application->id,
            'type'           => $data['type'],
            'session'        => $data['session'],
            'scanned_at'     => now(),
            'scanned_by'     => optional(Auth::user())->id,
        ]);

        return response()->json([
            'result'  => 'ok',
            'message' => $data['type'] === 'meal' ? 'Meal approved.' : 'Attendance recorded.',
            'data'    => $this->participantPayload($application, $badge->serial),
        ]);
    }

    /**
     * Desk: the log of everyone accredited so far (most recent first).
     */
    public function accredited(Request $request): JsonResponse
    {
        $accredited = GoTractApplication::query()
            ->whereNotNull('accredited_at')
            ->when($request->filled('lga'), fn ($q) => $q->where('lga', $request->input('lga')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->input('search');
                $q->where(function ($w) use ($term) {
                    $w->where('full_name', 'like', "%{$term}%")
                        ->orWhere('phone_number', 'like', "%{$term}%")
                        ->orWhere('reference_id', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('accredited_at')
            ->paginate($request->integer('per_page', 10))
            ->through(fn ($a) => $this->participantPayload($a));

        return response()->json($accredited);
    }

    /**
     * Live counters for the desk / scanner screens.
     */
    public function stats(Request $request): JsonResponse
    {
        $eligible   = GoTractApplication::whereIn('status', config('gotract.accreditable_statuses', ['pending', 'screening', 'approved']))->count();
        // $accredited = GoTractApplication::whereNotNull('accredited_at')->count();
        $accredited = GoTractBadge::whereNotNull('application_id')->count();

        $session = $request->query('session');
        $type    = $request->query('type');

        $sessionCount = ($session && $type)
            ? GoTractScan::where('type', $type)->where('session', $session)->count()
            : null;

        // Per-LGA: registered (accreditable) vs actually accredited.
        $allowed = config('gotract.accreditable_statuses', ['pending', 'screening', 'approved']);

        $registeredByLga = GoTractApplication::query()
            ->whereIn('status', $allowed)
            ->selectRaw('lga, count(*) as total')
            ->groupBy('lga')
            ->pluck('total', 'lga');

        // $accreditedByLga = GoTractApplication::query()
        //     ->whereNotNull('accredited_at')
        //     ->selectRaw('lga, count(*) as total')
        //     ->groupBy('lga')
        //     ->pluck('total', 'lga');


            $accreditedByLga = GoTractBadge::query()
    ->join('gotract_applications', 'gotract_badges.application_id', '=', 'gotract_applications.id')
    ->selectRaw('gotract_applications.lga, COUNT(*) as total')
    ->groupBy('gotract_applications.lga')
    ->pluck('total', 'gotract_applications.lga');

        $lgas = collect(config('gotract.lgas', []))->map(fn ($lga) => [
            'lga'        => $lga,
            'registered' => (int) ($registeredByLga[$lga] ?? 0),
            'accredited' => (int) ($accreditedByLga[$lga] ?? 0),
        ])->values();

        return response()->json(['data' => [
            'eligible'      => $eligible,
            'accredited'    => $accredited,
            'pending'       => max(0, $eligible - $accredited),
            'sessionCount'  => $sessionCount,
            'sessions'      => config('gotract.sessions'),
            'lgas'          => $lgas,
        ]]);
    }

    /* ----------------------------- Helpers ----------------------------- */

    /**
     * Badge / scanner payload. No NIN, BVN or bank details — a scanner screen
     * is held up in public, so it only shows what's needed to identify someone.
     */
    protected function participantPayload(GoTractApplication $a, ?string $serial = null): array
    {
        $serial = $serial ?: optional(
            \App\Models\GoTractBadge::where('application_id', $a->id)->first()
        )->serial;

        return [
            'id'           => $a->id,
            'referenceId'  => $a->reference_id,
            'fullName'     => $a->full_name,
            'phoneNumber'  => $a->phone_number,
            'lga'          => $a->lga,
            'village'      => $a->village,
            'gender'       => $a->gender,
            'status'       => $a->status,
            'badgeSerial'  => $serial,
            'accreditedAt' => optional($a->accredited_at)->toIso8601String(),
            'isAccredited' => (bool) $a->accredited_at,
        ];
    }
}