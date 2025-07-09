<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Applications;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\Photo;
use App\Models\OlevelResult;

class ApplicationController extends Controller
{
    public function index()
    {
        // This method should return a list of hospitals
        $applications = Applications::with(['jamb', 'user'])->get();
        if ($applications->isEmpty()) {
            return response()->json(['message' => 'No applicants found'], 404);
        }
        return response()->json($applications);
    }


     public function store(Request $request)
    {
       $validated = $request->validate([
            'acronym' => 'required|string|max:255',
            'hospitalName' => 'required|string|max:255',
            'location' => 'nullable|max:255',
            
        ]);
        $validated['status'] = 'active';
        $hospitals = Hospital::create($validated);
        $hospitals->load(['contact_person', 'hospital_location']);
        return response()->json([
            'hospitalId' => $hospitals->hospitalId,
            'acronym' => $hospitals->acronym,
            'hospitalName' => $hospitals->hospitalName,
            // 'contactPerson' => $hospitals->contactPerson,
            'contactPerson' => $hospitals->contactPerson ? $hospitals->contact_person->firstName : null,
            'location' => $hospitals->location ? $hospitals->hospital_location->stateName : null,
    ], 201); // HTTP status code 201: Created

    }


      public function apply(Request $request)
    {
        try {
            // Decode olevelResults if sent as JSON string
            $olevelResults = $request->input('olevelResults');
            if (is_string($olevelResults)) {
                $olevelResults = json_decode($olevelResults, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new ValidationException(null, response()->json([
                        'status' => 'error',
                        'message' => 'Validation failed',
                        'errors' => ['olevelResults' => ['The olevel results field must be a valid JSON array']],
                    ], 422));
                }
            }

            // Validate request data
            $validated = $request->validate([
                'gender' => 'required|string|in:Male,Female,Other',
                'dateOfBirth' => 'required|date',
                'maritalStatus' => 'nullable|string|in:Single,Married,Divorced,Widowed',
                
                'examType' => 'required|string|in:WAEC,NECO,NABTEB',
                'examYear' => 'required|integer|min:1980|max:' . date('Y'),
                'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
            ]);

            // Validate olevelResults separately since it may have been decoded
            $request->merge(['olevelResults' => $olevelResults]); // Update request with decoded array
            $request->validate([
                'olevelResults' => 'required|array|min:5',
                'olevelResults.*.subject' => 'required|string|max:255',
                'olevelResults.*.grade' => 'required|string|in:A1,B2,B3,C4,C5,C6',
            ]);

            // Get authenticated user
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Prepare data for update or create
            $applicationData = [
                'gender' => $validated['gender'],
                'dateOfBirth' => $validated['dateOfBirth'],
                'maritalStatus' => $validated['maritalStatus'] ?? null,
                
                'examType' => $validated['examType'],
                'examYear' => $validated['examYear'],
                'olevelResults' => json_encode($olevelResults),
                'status' => 'unpaid',
            ];

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('photos', 'public');
                $applicationData['photo_path'] = $path;
            }

            // Update or create application
            $application = Applications::updateOrCreate(
                ['userId' => $user->id], // Find by user_id
                $applicationData // Update or create with these values
            );

             // Handle photo upload
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('photos', 'public');
                Photo::updateOrCreate(
                    ['userId' => $user->id, 'applicationId' => $application->applicationId],
                    ['photoPath' => $path]
                );
            }

            // Handle O'Level results
            // Delete existing results to avoid duplicates
            OlevelResult::where('applicationId', $application->applicationId)->delete();
            // Create new results
            foreach ($olevelResults as $result) {
                OlevelResult::create([
                    'examYear' => $validated['examYear'],
                    'examType' => $validated['examType'],
                    'applicationId' => $application->applicationId,
                    'subject' => $result['subject'],
                    'grade' => $result['grade'],
                ]);
            }


            Log::info('Application updated/created:', ['user_id' => $user->id, 'application_id' => $application->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Application submitted successfully',
                'applicationId' => $application->applicationId,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Application submission failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Application submission failed due to an unexpected error. Please try again later.',
            ], 500);
        }
    }

   public function edit(Request $request, $hospitalId)
{
    $validated = $request->validate([
        'hospitalName' => 'nullable|string|max:255',
        'acronym' => 'nullable|string|max:255',
        'location' => 'nullable|max:255',
        'contactPerson' => 'nullable|max:255',
    ]);

    $hospital = Hospital::with(['contact_person', 'hospital_location'])->where('hospitalId', $hospitalId)->first();
    if (!$hospital) {
        return response()->json(['message' => 'Hospital type not found'], 404);
    }

    $hospital->update($validated);
    
    return response()->json([
        'hospitalId' => $hospital->hospitalId,
        'hospitalName' => $hospital->hospitalName,
        'acronym' => $hospital->acronym,
        'status' => $hospital->status,
        // 'contactPerson' => $hospital->contactPerson ? $hospital->contact_person->firstName : null,
        'contactPerson' => $hospital->contact_person ? $hospital->contact_person->firstName : null, // Check relationship
        'location' => $hospital->hospital_location ? $hospital->hospital_location->stateName : null,
    ], 200);
}

    public function destroy($hospitalId)
    {
        $hospital = Hospital::where('hospitalId', $hospitalId)->first();
        if (!$hospital) {
            return response()->json(['message' => 'Hospital type not found'], 404);
        }

        $hospital->delete();
        return response()->json(['message' => 'Hospital type deleted successfully'], 200);
    }
    public function show($hospitalId)
    {
        $hospital = Hospital::where('hospitalId', $hospitalId)->first();
        if (!$hospital) {
            return response()->json(['message' => 'Hospital type not found'], 404);
        }
        return response()->json($hospital);
    }




    // Payment controller method
    public function initiateRemitaPayment(Request $request)
{
    $orderId = Str::uuid()->toString(); // unique
    // $amount = $request->amount;
    // $payerName = $request->name;
    // $payerEmail = $request->email;
    // $payerPhone = $request->phone;

    $amount = 5000;
    $payerName = "Victor Oseji";
    $payerEmail = "vctroseji@gmail.com";
    $payerPhone = "08137054875";
    
    $hash = generateRemitaHash($orderId, $amount);

    $payload = [
        'serviceTypeId' => config('remita.service_type_id'),
        'amount' => $amount,
        'orderId' => $orderId,
        'payerName' => $payerName,
        'payerEmail' => $payerEmail,
        'payerPhone' => $payerPhone,
    ];

    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'Authorization' => 'remitaConsumerKey=' . config('remita.merchant_id') . ',remitaConsumerToken=' . $hash,
    ])->post(config('remita.base_url'), $payload);

    $res = $response->json();

    if (isset($res['RRR'])) {
        return response()->json([
            'status' => 'success',
            'rrr' => $res['RRR'],
            'payment_url' => "https://login.remita.net/remita/ecomm/finalize.reg?rrr={$res['RRR']}&merchantId=" . config('remita.merchant_id')
        ]);
    } else {
        return response()->json(['status' => 'error', 'message' => $res]);
    }
}
   
}
