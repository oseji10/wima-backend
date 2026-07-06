<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMspCacRegistrationRequest;
use App\Models\MSPs;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class MspCacController extends Controller
{
    /**
     * TODO: set these to match your system before going live.
     */
    private const MSP_ROLE       = 'MSP'; // value stored in users.role for an MSP
    private const MSP_PROJECT_ID = 4;     // value stored in msps.project

    /**
     * Collect an existing MSP's CAC onboarding details.
     *
     * An MSP is identified across two tables:
     *   - users.phoneNumber            (primary phone / login)
     *   - msps.alternatePhoneNumber    (MSP profile)
     *
     * If found -> update the msps record. If not -> create a login (users) record
     * and a linked msps profile, then store the CAC details.
     */
    public function store(StoreMspCacRegistrationRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Store uploads on the "public" disk (needs `php artisan storage:link`).
        $dir = 'cac/' . now()->format('Y/m');
        $validIdPath   = $request->file('validId')->store($dir, 'public');
        $passportPath  = $request->file('passportPhoto')->store($dir, 'public');
        $signaturePath = $request->file('signature')->store($dir, 'public');

        try {
            $result = DB::transaction(function () use ($data, $validIdPath, $passportPath, $signaturePath) {
                $phone = $data['phoneNumber'];
                $name  = $this->splitFullName($data['fullName']);

                // 1) Look up the login by primary phone (users.phoneNumber)
                $user = User::where('phoneNumber', $phone)->first();

                // 2) Look up the MSP profile - by the user link, then by alternate phone
                $msp = $user ? MSPs::where('userId', $user->id)->first() : null;
                if (! $msp) {
                    $msp = MSPs::where('alternatePhoneNumber', $phone)->first();
                    if ($msp && ! $user && $msp->userId) {
                        $user = User::find($msp->userId);
                    }
                }

                // 3) New MSP -> create login details
                if (! $user) {
                    $user = User::create([
                        'phoneNumber'  => $phone,
                        'firstName'    => $name['firstName'],
                        'lastName'     => $name['lastName'],
                        'otherNames'   => $name['otherNames'],
                        'role'         => self::MSP_ROLE,
                        'password'     => Hash::make($phone), // default password = phone; require reset on first login
                        'status'       => 'active',
                        // 'registeredBy' => 'cac-self-service',
                    ]);
                }

                // 4) New MSP -> create the msps profile linked to the login
                if (! $msp) {
                    $msp = new MSPs([
                        'mspId'      => MSPs::generateMspId(),
                        // 'firstName'  => $name['firstName'],
                        // 'lastName'   => $name['lastName'],
                        // 'otherNames' => $name['otherNames'],
                        'userId'     => $user->id,
                        'project'    => self::MSP_PROJECT_ID,
                        'addedBy'    => $user->id,
                    ]);
                } elseif (! $msp->userId) {
                    $msp->userId = $user->id;
                }

                // 5) CAC details (these columns must be in the MSPs $fillable)
                $msp->fill([
                    'alternatePhoneNumber' => $phone,
                    'nin'                  => $data['nin'],
                    'cac_cohort'           => $data['cohort'],
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
                $msp->save();

                return ['user' => $user, 'msp' => $msp];
            });
        } catch (\Throwable $e) {
            // Roll back the uploaded files if the DB work failed.
            foreach ([$validIdPath, $passportPath, $signaturePath] as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        $msp = $result['msp'];

        return response()->json([
            'message' => 'CAC details submitted successfully.',
            'data'    => [
                'mspId'       => $msp->mspId,
                'fullName'    => $data['fullName'],
                'phoneNumber' => $data['phoneNumber'],
                'submittedAt' => optional($msp->cac_submitted_at)->toIso8601String() ?? now()->toIso8601String(),
            ],
        ], 201);
    }

    protected function splitFullName(?string $fullName): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $fullName))));
        $count = count($parts);

        if ($count === 0) return ['firstName' => '', 'lastName' => '', 'otherNames' => ''];
        if ($count === 1) return ['firstName' => $parts[0], 'lastName' => '', 'otherNames' => ''];

        return [
            'firstName'  => $parts[0],
            'lastName'   => $parts[$count - 1],
            'otherNames' => $count > 2 ? implode(' ', array_slice($parts, 1, $count - 2)) : '',
        ];
    }
}