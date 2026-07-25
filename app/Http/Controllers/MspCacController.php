<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMspCacRegistrationRequest;
use App\Models\Hubs;   // adjust if your hub model differs
use App\Models\Lgas;   // adjust if your lga model differs
use App\Models\MSPs;
use App\Models\State;  // adjust if your state model differs
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

use App\Exports\CacSubmissionsExport;
use Maatwebsite\Excel\Facades\Excel;


class MspCacController extends Controller
{
    /**
     * TODO: set these to match your system before going live.
     */
    private const MSP_ROLE       = 2;
    private const MSP_PROJECT_ID = 4;

    /* ------------------------- Reference data -------------------------- */

    public function states(): JsonResponse
    {
        // Return all columns so we don't error on an unknown column name;
        // the frontend maps id/name flexibly.
        return response()->json(['data' => State::orderBy('stateId')->get()]);
    }

    public function lgas(Request $request): JsonResponse
    {
        $request->validate(['state' => ['required']]);

        // Detect the state FK column on the lgas table (state vs stateId).
        $column = Schema::hasColumn('lgas', 'stateId') ? 'stateId' : 'state';

        return response()->json([
            'data' => Lgas::where($column, $request->query('state'))->orderBy('lgaId')->get(),
        ]);
    }

    /* ---------------------------- Lookup ------------------------------- */

    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'digits:11']]);

        [$user, $msp] = $this->resolveMsp($request->query('phone'));

        if (! $user && ! $msp) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'data'  => [
                'firstName'       => $msp->firstName  ?? $user->firstName  ?? '',
                'lastName'        => $msp->lastName    ?? $user->lastName   ?? '',
                'otherNames'      => $msp->otherNames  ?? $user->otherNames ?? '',
                'gender'          => $msp->gender      ?? '',
                'email'           => $user->email      ?? '',
                // 'state'           => $msp->state       ?? ($user->state ?? ''),
                // 'lga'             => $msp->lga         ?? ($user->lga ?? ''),
                'age'             => $msp->age         ?? null,
                'dateOfBirth'     => $msp && $msp->dateOfBirth ? \Carbon\Carbon::parse($msp->dateOfBirth)->format('Y-m-d') : '',
                'nin'             => $msp->nin         ?? '',
                'cohort'          => $msp->cac_cohort  ?? '',
                'validIdType'     => $msp->cac_valid_id_type ?? '',
                'businessAddress' => $msp->cac_business_address ?? '',
                'businessName1'   => $msp->cac_business_name_1 ?? '',
                'businessName2'   => $msp->cac_business_name_2 ?? '',
                'businessName3'   => $msp->cac_business_name_3 ?? '',
                'hasValidId'      => (bool) ($msp->cac_valid_id_path ?? false),
                'hasPassport'     => (bool) ($msp->cac_passport_path ?? false),
                'hasSignature'    => (bool) ($msp->cac_signature_path ?? false),
                'isExistingMsp'   => (bool) $msp,
                'cacStatus'       => $msp->cac_status ?? null,
            ],
        ]);
    }

    /* ---------------------------- Store -------------------------------- */

    public function store(StoreMspCacRegistrationRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $phone = $data['phoneNumber'];
        $email = $data['email'] ?? null;

        [$user, $msp] = $this->resolveMsp($phone);

        // Email is unique on users — reject early with a clean message instead of
        // letting the insert blow up with a 500 unique-constraint error.
        if ($email) {
            $emailOwner = User::where('email', $email)->first();
            if ($emailOwner && (! $user || $emailOwner->id !== $user->id)) {
                return response()->json([
                    'message' => 'That email is already registered to another account.',
                    'errors'  => ['email' => ['This email is already in use. Please use a different one.']],
                ], 422);
            }
        }

        // --- Date of birth + age (kept if already on file) ---
        $dob = null;
        $age = null;
        if (! empty($data['dateOfBirth'])) {
            $dob = $data['dateOfBirth'];
            $age = \Carbon\Carbon::parse($dob)->age;
        } elseif ($msp) {
            $dob = $msp->dateOfBirth ? \Carbon\Carbon::parse($msp->dateOfBirth)->format('Y-m-d') : null;
            $age = $msp->age;
        }

        // --- Files: new upload or keep what's on record ---
        $dir = 'cac/' . now()->format('Y/m');
        $newFiles = [];
        $replaced = [];
        $validIdPath   = $this->fileOrExisting($request, 'validId', $dir, $msp->cac_valid_id_path ?? null, $newFiles, $replaced);
        $passportPath  = $this->fileOrExisting($request, 'passportPhoto', $dir, $msp->cac_passport_path ?? null, $newFiles, $replaced);
        $signaturePath = $this->fileOrExisting($request, 'signature', $dir, $msp->cac_signature_path ?? null, $newFiles, $replaced);

        // --- Required-unless-on-file checks ---
        $missing = [];
        if (is_null($age))     $missing['dateOfBirth'] = ['Please provide your date of birth.'];
        if (! $validIdPath)    $missing['validId'] = ['Please upload your valid ID.'];
        if (! $passportPath)   $missing['passportPhoto'] = ['Please upload your passport photo.'];
        if (! $signaturePath)  $missing['signature'] = ['Please upload your scanned signature.'];
        if ($missing) {
            foreach ($newFiles as $p) Storage::disk('public')->delete($p);
            return response()->json(['message' => 'Some required details are missing.', 'errors' => $missing], 422);
        }

        // --- LGA -> hubId (single hop: hubs.lga = the submitted lgaId) ---
        $hubId = Hubs::where('lga', $data['lga'])->value('hubId');

        try {
            [$msp, $code, $freshCode] = DB::transaction(function () use ($data, $phone, $email, $user, $msp, $age, $dob, $hubId, $validIdPath, $passportPath, $signaturePath) {
                // New MSP -> create login
                if (! $user) {
                    $user = User::create([
                        'phoneNumber'  => $phone,
                        'email'        => $email,
                        'firstName'    => $data['firstName'],
                        'lastName'     => $data['lastName'],
                        'otherNames'   => $data['otherNames'] ?? null,
                        'role'         => self::MSP_ROLE,
                        // 'state'        => $data['state'],
                        // 'lga'          => $data['lga'],
                        'password'     => Hash::make($phone),
                        // 'status'       => 'active',
                        // 'registeredBy' => 'cac-self-service',
                    ]);
                } elseif ($email && $user->email !== $email) {
                    $user->email = $email;
                    $user->save();
                }

                // New MSP -> create profile
                if (! $msp) {
                    $msp = new MSPs([
                        'mspId'   => MSPs::generateMspId(),
                        'userId'  => $user->id,
                        'project' => self::MSP_PROJECT_ID,
                        'addedBy' => $user->id,
                    ]);
                } elseif (! $msp->userId) {
                    $msp->userId = $user->id;
                }

                // Generate a code once (kept on re-submission)
                $freshCode = empty($msp->code);
                $code = $msp->code ?: $this->generateCode();

                $msp->fill([
                    // 'firstName'            => $data['firstName'],
                    // 'lastName'             => $data['lastName'],
                    // 'otherNames'           => $data['otherNames'] ?? null,
                    'gender'               => $data['gender'],
                    'ageBracket'                  => $age,
                    'dateOfBirth'          => $dob,
                    // 'state'                => $data['state'],
                    // 'lga'                  => $data['lga'],
                    'hub'                  => $hubId,
                    'code'                 => $code,
                    'alternatePhoneNumber' => $phone,
                    'nin'                  => $data['nin'],
                    'cac_cohort'           => $data['cohort'],
                    'year'           => $data['cohort'],
                    'cac_valid_id_type'    => $data['validIdType'],
                    'cac_valid_id_path'    => $validIdPath,
                    'cac_passport_path'    => $passportPath,
                    'cac_signature_path'   => $signaturePath,
                    'cac_business_address' => $data['businessAddress'],
                    'cac_business_name_1'  => $data['businessName1'],
                    'cac_business_name_2'  => $data['businessName2'],
                    'cac_business_name_3'  => $data['businessName3'],
                    'cac_submitted_at'     => now(),
                    'cac_status'           => 'submitted',
                ]);
                // $msp->save();
                $msp->setConnection('mysql_direct');
                $msp->save();

                return [$msp, $code, $freshCode];
            });
        } catch (\Throwable $e) {
            foreach ($newFiles as $p) Storage::disk('public')->delete($p);
            throw $e;
        }

        foreach ($replaced as $p) Storage::disk('public')->delete($p);

        // --- Email the code if an address was supplied ---
        if ($email) {
            $this->sendCodeEmail($email, $data['firstName'], $code);
        }

        return response()->json([
            'message' => 'CAC details submitted successfully.',
            'data'    => [
                'mspId'       => $msp->mspId,
                'fullName'    => trim($data['firstName'] . ' ' . $data['lastName']),
                'phoneNumber' => $phone,
                'code'        => $code,
                'emailed'     => (bool) $email,
                'submittedAt' => optional($msp->cac_submitted_at)->toIso8601String() ?? now()->toIso8601String(),
            ],
        ], 201);
    }

    /* ---------------------------- Helpers ------------------------------ */

    protected function resolveMsp(string $phone): array
    {
        $user = User::where('phoneNumber', $phone)->first();
        $msp  = $user ? MSPs::where('userId', $user->id)->first() : null;

        if (! $msp) {
            $msp = MSPs::where('alternatePhoneNumber', $phone)->first();
            if ($msp && ! $user && $msp->userId) {
                $user = User::find($msp->userId);
            }
        }

        return [$user, $msp];
    }

    protected function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (MSPs::where('code', $code)->exists());

        return $code;
    }

    protected function sendCodeEmail(string $email, string $firstName, string $code): void
    {
        try {
            Mail::raw(
                "Hello {$firstName},\n\nYour MSP CAC registration code is: {$code}\n\nKeep this code safe — you may be asked for it during onboarding.\n\nThank you.",
                function ($message) use ($email) {
                    $message->to($email)->subject('Your MSP CAC Registration Code');
                }
            );
        } catch (\Throwable $e) {
            // Don't fail the submission if email delivery fails.
            Log::warning('MSP CAC code email failed', ['email' => $email, 'error' => $e->getMessage()]);
        }
    }

    protected function fileOrExisting(Request $request, string $key, string $dir, ?string $existing, array &$newFiles, array &$replaced): ?string
    {
        if ($request->hasFile($key)) {
            $path = $request->file($key)->store($dir, 'public');
            $newFiles[] = $path;
            if ($existing) $replaced[] = $existing;
            return $path;
        }
        return $existing;
    }


    public function checkName(Request $request): JsonResponse
{
    $request->validate(['name' => ['required', 'string', 'max:255']]);

    $name  = strtolower(trim($request->query('name')));
    $phone = $request->query('phone'); // exclude the current MSP's own names

    $query = MSPs::query()->where(function ($q) use ($name) {
        $q->whereRaw('LOWER(cac_business_name_1) = ?', [$name])
          ->orWhereRaw('LOWER(cac_business_name_2) = ?', [$name])
          ->orWhereRaw('LOWER(cac_business_name_3) = ?', [$name]);
    });

    if ($phone) {
        $query->where('alternatePhoneNumber', '!=', $phone);
    }

    return response()->json(['taken' => $query->exists()]);
}



public function suggestNames(Request $request): JsonResponse
{
    $request->validate(['name' => ['nullable', 'string', 'max:255']]);
    $input = trim($request->input('name')) ?: 'agriculture business';

    $apiKey = config('services.anthropic.key');
    if (! $apiKey) {
        return response()->json(['suggestions' => []]); // AI not configured
    }

    $prompt = "A Nigerian applicant proposed \"{$input}\" as a business name for CAC "
        . "(Corporate Affairs Commission) registration. CAC rejects names that are merely a "
        . "generic activity or common word (e.g. \"Farming\", \"Poultry\", \"Fishing\"). A valid "
        . "name needs a DISTINCTIVE element (a coined word, place, or personal name), optionally a "
        . "descriptor, and a suffix like \"Enterprises\", \"Ventures\", \"Global Services\", or "
        . "\"Nigeria\". Keep the applicant's line of business. Respond with ONLY a JSON array of 3 "
        . "distinctive, CAC-ready business name strings — no prose.";

    try {
        $resp = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-3-5-haiku-latest'),
            'max_tokens' => 300,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $text = trim(preg_replace('/```json|```/', '', data_get($resp->json(), 'content.0.text', '[]')));
        $list = array_slice(array_values(array_filter(array_map('strval', (array) json_decode($text, true)))), 0, 3);

        return response()->json(['suggestions' => $list]);
    } catch (\Throwable $e) {
        return response()->json(['suggestions' => []]);
    }
}



/* ------------------------- Admin: list submissions ------------------------- */

public function adminIndex(Request $request): JsonResponse
{
    $query = MSPs::query()
        ->whereNotNull('cac_business_name_1') // only actual submissions
        ->with(['users:id,firstName,lastName,phoneNumber,email', 'states', 'lgas', 'projects']);

    if ($status = $request->query('status')) {
        $query->where('cac_status', $status);
    }
    if ($state = $request->query('state')) {
        $query->where('state', $state);
    }
    if ($cohort = $request->query('cohort')) {
        $query->where('cac_cohort', $cohort);
    }
    if ($search = $request->query('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('cac_business_name_1', 'like', "%{$search}%")
              ->orWhere('cac_business_name_2', 'like', "%{$search}%")
              ->orWhere('cac_business_name_3', 'like', "%{$search}%")
              ->orWhere('alternatePhoneNumber', 'like', "%{$search}%")
              ->orWhereHas('users', function ($uq) use ($search) {
                  $uq->where('firstName', 'like', "%{$search}%")
                     ->orWhere('lastName', 'like', "%{$search}%");
              });
        });
    }

    $perPage = (int) $request->query('per_page', 10);
    $page = $query->orderByDesc('cac_submitted_at')->paginate($perPage);

    return response()->json($page);
}

public function adminShow(MSPs $msp): JsonResponse
{
    $msp->load(['users:id,firstName,lastName,phoneNumber,email', 'states', 'lgas', 'projects']);

    return response()->json([
        'data' => array_merge($msp->toArray(), [
            'validIdUrl'   => $msp->cac_valid_id_path ? Storage::disk('public')->url($msp->cac_valid_id_path) : null,
            'passportUrl'  => $msp->cac_passport_path ? Storage::disk('public')->url($msp->cac_passport_path) : null,
            'signatureUrl' => $msp->cac_signature_path ? Storage::disk('public')->url($msp->cac_signature_path) : null,
        ]),
    ]);
}

public function updateStatus(Request $request, MSPs $msp): JsonResponse
{
    $data = $request->validate([
        'status'       => ['required', 'in:submitted,approved,rejected,needs_revision'],
        'approvedName' => ['nullable', 'integer', 'in:1,2,3', 'required_if:status,approved'],
        'adminNote'    => ['nullable', 'string', 'max:1000'],
    ]);

    $msp->fill([
        'cac_status'         => $data['status'],
        'cac_approved_name'  => $data['approvedName'] ?? null,
        'cac_admin_note'     => $data['adminNote'] ?? null,
        'cac_reviewed_at'    => now(),
        'cac_reviewed_by'    => $request->user()?->id,
    ]);
    $msp->setConnection('mysql_direct');
    $msp->save();

    return response()->json(['message' => 'Status updated.', 'data' => $msp]);
}




public function exportSubmissions(Request $request)
{
    $filters = $request->only(['status', 'search']);
    return Excel::download(new CacSubmissionsExport($filters), 'cac-submissions-' . now()->format('Y-m-d') . '.xlsx');
}

}