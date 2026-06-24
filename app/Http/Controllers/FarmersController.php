<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Farmers;
use App\Models\Lgas;
use App\Models\Subhubs;
use App\Models\Hubs;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;
use App\Services\ZohoBooks;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;  // ✅ Add this
use Illuminate\Support\Facades\Log;  // ✅ Add this
use Illuminate\Http\JsonResponse;

class FarmersController extends Controller
{
public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $state = $request->query('state');
        $lga = $request->query('lga');
        $project = $request->query('projectId');

        $query = Farmers::with('hubs.states', 'hubs.lgas', 'hubs.subhub', 'msp', 'projects')->orderBy('id', 'desc');
        $state_coordinators = StateCoordinators::where('userId', $user->id)->first();
        $community_lead = CommunityLead::where('userId', $user->id)->first();

        // Role-based filtering
        if ($user->role === 4) {
            // State coordinators see only their state's records
            $query->whereHas('hubs', function($q) use ($state_coordinators) {
                $q->where('state', $state_coordinators->stateId);
            });

            if ($lga) {
                $query->whereHas('hubs', function($q) use ($lga) {
                    $q->where('lga', $lga);
                });
            }
        } elseif ($user->role === 5) {
            // Community leads see only their community's records
            $query->whereHas('hubs', function($q) use ($community_lead) {
                $q->where('lga', $community_lead->lga);
            });
        } elseif ($user->role === 1 || $user->role === 3) {
            // Admin and National Coordinator can filter by state and LGA
            if ($state) {
                $query->whereHas('hubs', function($q) use ($state) {
                    $q->where('state', $state);
                });
            }
            if ($lga) {
                $query->whereHas('hubs', function($q) use ($lga) {
                    $q->where('lga', $lga);
                });
            }
        }

        // Project filtering for all roles
        if ($project) {
            $query->where('project', $project);
        }

        // Search functionality with role-based restrictions
        if ($search) {
            $query->where(function($q) use ($search, $user, $state_coordinators, $community_lead) {
                // Apply state or LGA restriction for search
                if ($user->role === 4) {
                    $q->whereHas('hubs', function($hq) use ($state_coordinators) {
                        $hq->where('state', $state_coordinators->stateId);
                    });
                } elseif ($user->role === 5) {
                    $q->whereHas('hubs', function($hq) use ($community_lead) {
                        $hq->where('lga', $community_lead->lga);
                    });
                }
                // Search conditions
                $q->where(function($sq) use ($search) {
                    $sq->where('farmerFirstName', 'like', "%$search%")
                       ->orWhere('farmerLastName', 'like', "%$search%")
                       ->orWhere('farmerOtherNames', 'like', "%$search%")
                       ->orWhereRaw("CONCAT(farmerFirstName, ' ', farmerLastName, ' ', farmerOtherNames) LIKE ?", ["%{$search}%"])
                       ->orWhere('phoneNumber', 'like', "%$search%");
                });
            });
        }

        $farmers = $query->paginate($perPage);
        
        return response()->json($farmers);
    }

     public function getLgasByState(Request $request)
    {
        // Validate the request
        $request->validate([
            'state' => 'required|string'
        ]);

        try {
            // Option 1: If you're passing state name
            $state = State::where('stateId', $request->state)->first();
            
            // Option 2: If you're passing state ID
            // $state = State::find($request->state);
            
            if (!$state) {
                return response()->json([
                    'success' => false,
                    'message' => 'State not found'
                ], 404);
            }

            // Get LGAs for the state
            $lgas = Lgas::where('state', $state->stateId)
                      ->orderBy('lgaName')
                      ->get(['lgaId', 'lgaName']);

            return response()->json([
                'success' => true,
                'data' => $lgas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch LGAs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  

//     public function store(Request $request)
//     {
//        $hub = Hubs::where('lga', $request->hub)
//         // ->where('lga', $request->subHub)
//         ->first();

//     if (!$hub) {
//         return response()->json([
//             'error' => 'No active hub found for the selected state and LGA combination'
//         ], 422);
//     }

//     // Generate farmerId
//     $farmerId = strtoupper(Str::random(10));

//     // Prepare data for creation
//     $data = $request->all();
//     $data['farmerId'] = $farmerId;
//     $data['hub'] = $hub->hubId; // Use the actual hubId from the Hub model
//     $data['project'] = $request->projectId;
//     // Create farmer record
//     $farmer = Farmers::create($data);

//     return response()->json($farmer, 201);
// }


public function store(Request $request)
{
    // Find the hub by LGA
    $hub = Hubs::where('lga', $request->hub)->first();

    if (!$hub) {
        return response()->json([
            'error' => 'No active hub found for the selected state and LGA combination'
        ], 422);
    }

    // Generate unique farmerId
    $farmerId = strtoupper(Str::random(10));

    // Prepare data
    $data = $request->all();
    $data['farmerId'] = $farmerId;
    $data['hub'] = $hub->hubId;
    $data['project'] = $request->projectId;

    // Create farmer record
    $farmer = Farmers::create($data);

    // ---- Run farmerSearch immediately after storing ----
    $request->merge([
        'communityId' => $request->hub,
        'search' => $farmer->farmerId, // search by this unique ID
    ]);

    return $this->farmerSearch($request);
}



// public function zohotest(Request $request, ZohoBooks $zoho)
// {
//     // your local customer logic
//      $farmerId = strtoupper(Str::random(10));

//     // Prepare data
//     $data = $request->all();
//     $data['farmerId'] = $farmerId;
//     $farmers = Farmers::create($data);
    

//     // create customer in zoho books


//     // return response()->json(['message' => 'Customer created and synced to Zoho']);
// $contactName = trim($farmers->farmerFirstName . ' ' . $farmers->farmerLastName . ' ' . $farmers->farmerOtherNames);

// // fallback if both names are empty
// if (empty($contactName)) {
//     $contactName = $farmers->company ?? 'Default Customer';
// }

// $zoho->createCustomer([
//     "contact_name" => $contactName,
//     "company_name" => $farmers->company ?? '',
//     "billing_address" => [
//         "address" => $farmers->address ?? '',
//     ],
//     "contact_persons" => [ 
//         [
//             "first_name" => $farmers->farmerFirstName,
//             "last_name" => $farmers->farmerLastName,
//             "email" => $farmers->email,
//             "phone" => $farmers->phoneNumber,
//         ]
//     ],
//     "contact_type" => "customer"
// ]);

// }


//  public function zohotest(Request $request, ZohoBooks $zoho)
//     {
//         //-------------------------
//         // 1. Create Customer
//         //-------------------------

//          $farmerId = strtoupper(Str::random(10));

//     // Prepare data
//     $data = $request->all();
//     $data['farmerId'] = $farmerId;
//     $farmers = Farmers::create($data);
    

//     $contactName = trim($farmers->farmerFirstName . ' ' . $farmers->farmerLastName . ' ' . $farmers->farmerOtherNames);

// // fallback if both names are empty
// if (empty($contactName)) {
//     $contactName = $farmers->company ?? 'Default Customer';
// }

//         $customerPayload = [
//             "contact_name" => $contactName,
//             "company_name" => $farmers->company ?? '',
//             "billing_address" => [
//                 "address" => "No. 45 Allen Avenue",
//                 "city" => "Lagos",
//                 "state" => "Lagos",
                
//                 "country" => "Nigeria"
//             ],
//             "email" => $farmers->email,
//             "phone" => $farmers->phoneNumber
//         ];

//         $customer = $zoho->createCustomer($customerPayload);

//         if (!isset($customer['contact']['contact_id'])) {
//             return response()->json([
//                 'error' => 'Customer creation failed',
//                 'response' => $customer
//             ], 422);
//         }

//         $customerId = $customer['contact']['contact_id'];


//         //-------------------------
//         // 2. Create Invoice
//         //-------------------------
//         $invoicePayload = [
//             "customer_id" => $customerId,
//             "date" => now()->toDateString(),
//             "due_date" => now()->addDays(7)->toDateString(),

//             "line_items" => [
//                 [
//                     "item_id" => "7224461000000134033",
//                     "description" => "Corporate website design",
//                     "rate" => 150000,
//                     "quantity" => 1
//                 ]
//             ]
//         ];

//         $invoice = $zoho->createInvoice($invoicePayload);

//         return response()->json([
//             "customer" => $customer,
//             "invoice" => $invoice
//         ]);
//     }


       public function update(Request $request)
{


    $farmer = Farmers::where('farmerId', $request->farmerId)->first();
    if (!$farmer) {
        return response()->json(['message' => 'Farmer not found'], 404);
    }

    $farmer->update(['hubName' => $request->hubName, 'state' => $request->state, 'lga' => $request->lga]);

    $farmer->load('states', 'lgas');

    return response()->json([
        'activeLocationId' => $hubs->activeLocationId,
        'state' => $hubs->states->stateName,
        'lga' => $hubs->lgas->lgaName,
        'hubName' => $hubs->hubName
    ], 200);
}

    public function destroy(Request $request, $farmerId)
    {
        $farmer = Farmers::where('farmerId', $request->farmerId)->first();
        if (!$farmer) {
            return response()->json(['message' => 'Farmer not found'], 404);
        }

        $farmer->delete();
        return response()->json(['message' => 'Farmer deleted successfully'], 200);
    }

   public function search(Request $request)
{
    $user = Auth::user();
    $community_lead = CommunityLead::where('userId', $user->id)->first();
    $hub = Hubs::where('lga', $community_lead->lga)->first();

    $search = $request->query('search');

    $search_result = Farmers::with('hubs.states', 'hubs.lgas')
        ->where('hub', $hub->hubId) // hub filter applies globally
        ->where(function ($query) use ($search) {
            $query->where('farmerFirstName', 'like', "%$search%")
                  ->orWhere('farmerLastName', 'like', "%$search%")
                  ->orWhere('farmerId', 'like', "%$search%");
        })
        ->orderBy('id', 'desc')
        ->get();

    return response()->json($search_result);
}


 public function validateFarmer(Request $request, $phoneNumber)
    {
       

        $farmer = Farmers::where('phoneNumber', $phoneNumber)->first();

        return response()->json(['exists' => $farmer ? true : false, 'fullname' => $farmer ? $farmer->farmerFirstName . ' ' . $farmer->farmerLastName . ' ' . $farmer->farmerLastName : null, 'gender' => $farmer ? $farmer->gender : null, 'age' => $farmer ? $farmer->ageBracket : null, 'email' => $farmer ? $farmer->email : null]);
    }
    



    /**
     * Register a new farmer
     */
    public function register(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'fullName' => 'required|string|max:255',
                'phoneNumber' => 'required|string|max:15|unique:farmers,phoneNumber',
                'email' => 'nullable|email|max:255',
                'age' => 'required|integer|min:18|max:120',
                'gender' => 'nullable|string|in:Male,Female',
                'stateId' => 'required|integer',
                'addedBy' => 'required|integer',
                'lgaId' => 'required|integer',
                'mechanizedServices' => 'nullable|array',
                'mechanizedServices.*' => 'string',
            ], [
                'phoneNumber.unique' => 'This phone number is already registered.',
                'age.min' => 'You must be at least 18 years old.',
                'age.max' => 'Age cannot exceed 120 years.',
                'fullName.required' => 'Full name is required.',
                'phoneNumber.required' => 'Phone number is required.',
                'stateId.required' => 'Please select a state.',
                'lgaId.required' => 'Please select an LGA.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Check if farmer already exists
            $existingFarmer = Farmers::where('phoneNumber', $request->phoneNumber)->first();
            if ($existingFarmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Farmer with this phone number already exists.',
                    'data' => $existingFarmer
                ], 409);
            }

            // Generate farmer ID
            $farmerId = Farmers::generateFarmerId();

            // Determine age bracket
            $ageBracket = $this->getAgeBracket($request->age);

            // Split full name
            $nameParts = $this->splitFullName($request->fullName);

            // Find the hub by LGA
    $hub = Hubs::where('lga', $request->lgaId)->first();

    if (!$hub) {
        return response()->json([
            'error' => 'No active hub found for the selected state and LGA combination'
        ], 422);
    }

  
            // Create farmer
            $farmer = Farmers::create([
                'farmerId' => $farmerId,
                'farmerFirstName' => $nameParts['firstName'],
                'farmerLastName' => $nameParts['lastName'],
                'farmerOtherNames' => $nameParts['otherNames'],
                'phoneNumber' => $request->phoneNumber,
                'email' => $request->email,
                'gender' => $request->gender,
                'age' => $request->age,
                'ageBracket' => $ageBracket,
                'stateId' => $request->stateId,
                'hub' => $hub->hubId,
                'project' => 3,
                'addedBy' => $request->addedBy,
                'mechanized_services' => $request->mechanizedServices ?? [],
                'status' => 'active',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Farmer registered successfully.',
                'data' => $farmer
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Farmer registration failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    

    /**
     * Helper methods
     */
    private function getAgeBracket(int $age): string
    {
        if ($age < 18) return 'Under 18';
        if ($age <= 25) return '18-25';
        if ($age <= 35) return '26-35';
        if ($age <= 45) return '36-45';
        if ($age <= 55) return '46-55';
        return '55+';
    }

    private function splitFullName(string $fullName): array
    {
        $parts = array_values(array_filter(explode(' ', trim($fullName))));
        
        if (count($parts) === 0) {
            return [
                'firstName' => '',
                'lastName' => '',
                'otherNames' => null
            ];
        }

        if (count($parts) === 1) {
            return [
                'firstName' => $parts[0],
                'lastName' => '',
                'otherNames' => null
            ];
        }

        if (count($parts) === 2) {
            return [
                'firstName' => $parts[0],
                'lastName' => $parts[1],
                'otherNames' => null
            ];
        }

        return [
            'firstName' => $parts[0],
            'lastName' => end($parts),
            'otherNames' => implode(' ', array_slice($parts, 1, -1))
        ];
    }


//    public function farmerSearch(Request $request)
// {
//     // $user = Auth::user();
//     // $community_lead = CommunityLead::where('userId', $user->id)->first();
//     $hub = Hubs::where('lga', $request->communityId)->first();

//     $search = $request->query('search');

//     $search_result = Farmers::with('hubs.states', 'hubs.lgas')
//         ->where('hub', $hub->hubId) // hub filter applies globally
//         ->where(function ($query) use ($search) {
//             $query->where('farmerFirstName', 'like', "%$search%")
//                   ->orWhere('farmerLastName', 'like', "%$search%")
//                   ->orWhere('farmerId', 'like', "%$search%");
//         })
//         ->orderBy('id', 'desc')
//         ->get();

//     return response()->json($search_result);
// }

public function farmerSearch(Request $request)
{
    $hub = Hubs::where('lga', $request->communityId)->first();

    // ✅ Use input() instead of query() so it works for both GET and internal calls
    $search = $request->input('search');

    $search_result = Farmers::with('hubs.states', 'hubs.lgas')
        ->where('hub', $hub->hubId)
        ->where(function ($query) use ($search) {
            $query->where('farmerFirstName', 'like', "%$search%")
                  ->orWhere('farmerLastName', 'like', "%$search%")
                  ->orWhere('farmerId', 'like', "%$search%");
        })
        ->orderBy('id', 'desc')
        ->get();

    return response()->json($search_result);
}

public function totalFarmers(){
    $totalFarmers = Farmers::count();
    return response()->json(['totalFarmers' => $totalFarmers]);
}
    



}
