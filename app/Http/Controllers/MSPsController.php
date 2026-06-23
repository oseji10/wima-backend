<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MSPs;
use App\Models\Lgas;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;
use App\Models\Hubs;

// use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class MSPsController extends Controller
{
public function index(Request $request)
{
    $user = Auth::user();
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    $state = $request->query('state');
    $lga = $request->query('lga');
    $project = $request->query('projectId');

    $query = MSPs::with(['users', 'hub.states', 'hub.lgas', 'projects'])
        ->orderBy('id', 'desc');

    $state_coordinators = StateCoordinators::where('userId', $user->id)->first();
    $community_lead = CommunityLead::where('userId', $user->id)->first();

    // Role-based filtering
    if ($user->role === 4) {
        // State coordinators see only their state's records
        $query->whereHas('hub', function($q) use ($state_coordinators) {
            $q->where('state', $state_coordinators->stateId);
        });

        if ($lga) {
            $query->whereHas('hub', function($q) use ($lga) {
                $q->where('lga', $lga);
            });
        }

    } elseif ($user->role === 5) {
        // Community leads see only their community's records
        $query->whereHas('hub', function($q) use ($community_lead) {
            $q->where('lga', $community_lead->lga);
        });

    } elseif ($user->role === 1 || $user->role === 3) {
        // Admin and National Coordinator can filter by state and LGA
        if ($state) {
            $query->whereHas('hub', function($q) use ($state) {
                $q->where('state', $state);
            });
        }
        if ($lga) {
            $query->whereHas('hub', function($q) use ($lga) {
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
        $query->where(function($q) use ($search, $user, $state_coordinators, $community_lead, $lga) {
            // Apply state or LGA restriction for search
            if ($user->role === 4) {
                $q->whereHas('hub', function($hq) use ($state_coordinators, $lga) {
                    $hq->where('state', $state_coordinators->stateId);
                    if ($lga) {
                        $hq->where('lga', $lga);
                    }
                });
            } elseif ($user->role === 5) {
                $q->whereHas('hub', function($hq) use ($community_lead) {
                    $hq->where('lga', $community_lead->lga);
                });
            }

            // Search conditions
            $q->where(function($sq) use ($search) {
                $sq->where('mspId', 'like', "%$search%")
                   ->orWhereHas('users', function($uq) use ($search) {
                       $uq->where('firstName', 'like', "%$search%")
                          ->orWhere('lastName', 'like', "%$search%")
                          ->orWhere('otherNames', 'like', "%$search%")
                          ->orWhere('phoneNumber', 'like', "%$search%")
                          ->orWhereRaw("CONCAT(firstName, ' ', lastName , ' ', otherNames) LIKE ?", ["%{$search}%"]);
                   });
            });
        });
    }

    $msps = $query->paginate($perPage);
    
    return response()->json($msps);
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

  

 public function store(Request $request)
{
    $loggeInUser = Auth::user();
    $hub = Hubs::where('lga', $request->hub)
        // ->where('lga', $request->subHub)
        ->first();
    // $loggeInUser->load('community_lead');
    $default_password = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);
    // Validate request
    $request->validate([
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'otherNames' => 'nullable|string|max:255',
        'email' => 'required|string|email|max:255|unique:users,email',
        'phoneNumber' => 'required|string|max:20|unique:users,phoneNumber',
        'alternatePhoneNumber' => 'nullable|string|max:20',
        'gender' => 'nullable|string|in:Male,Female',
        'projectId' => 'required|integer|exists:projects,projectId',
        'hub' => 'nullable',
    ]);

    // Create User
    $user = User::create([
        'firstName' => $request->firstName,
        'lastName' => $request->lastName,
        'otherNames' => $request->otherNames,
        'phoneNumber' => $request->phoneNumber,
        'email' => $request->email,
        'password' => bcrypt($default_password), 
    ]);

    // Create MSP and link to User
    $mspId = strtoupper(Str::random(6));
    $msp = MSPs::create([
        'mspId' => $mspId,
        'userId' => $user->id,
        'alternatePhoneNumber' => $request->alternatePhoneNumber,
        'gender' => $request->gender,
        'project' => $request->projectId,
        'hub' => $hub->hubId,
        'addedBy' => $loggeInUser->id,
    ]);

    return response()->json([
        'message' => 'MSP created successfully',
        'user' => $user,
        'msp' => $msp,
    ], 201);
}


       public function update(Request $request)
{


    $hubs = Hubs::where('activeLocationId', $request->activeLocationId)->first();
    if (!$hubs) {
        return response()->json(['message' => 'Hub not found'], 404);
    }

    $hubs->update(['hubName' => $request->hubName, 'state' => $request->state, 'lga' => $request->lga]);
    
     $hubs->load('states', 'lgas');

    return response()->json([
        'activeLocationId' => $hubs->activeLocationId,
        'state' => $hubs->states->stateName,
        'lga' => $hubs->lgas->lgaName,
        'hubName' => $hubs->hubName
    ], 200);
}

    public function destroy(Request $request)
    {
        $hub = Hubs::where('activeLocationId', $request->activeLocationId)->first();
        if (!$hub) {
            return response()->json(['message' => 'Hub not found'], 404);
        }

        $hub->delete();
        return response()->json(['message' => 'Hub deleted successfully'], 200);
    }





    public function register(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'fullName' => 'required|string|max:255',
                'phoneNumber' => 'required|string|max:15|unique:users,phoneNumber|unique:msps,alternatePhoneNumber',
                'email' => 'nullable|email|max:255|unique:users,email',
                'age' => 'required|integer|min:18|max:120',
                'gender' => 'nullable|string|in:Male,Female,Other',
                'stateId' => 'required|numeric',
                'lgaId' => 'required|numeric',
                'trainingsAttended' => 'nullable|array',
                'trainingsAttended.*' => 'string',
            ], [
                'phoneNumber.unique' => 'This phone number is already registered.',
                'email.unique' => 'This email is already registered.',
                'age.min' => 'You must be at least 18 years old.',
                'age.max' => 'Age cannot exceed 120 years.',
                'fullName.required' => 'Full name is required.',
                'phoneNumber.required' => 'Phone number is required.',
                'stateId.required' => 'Please select a state.',
                'stateId.numeric' => 'Invalid state selection.',
                'lgaId.required' => 'Please select an LGA.',
                'lgaId.numeric' => 'Invalid LGA selection.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Check if phone number already exists in users table
            $existingUser = User::where('phoneNumber', $request->phoneNumber)->first();
            if ($existingUser) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This phone number is already registered as a user.',
                    'errors' => [
                        'phoneNumber' => ['This phone number is already registered.']
                    ]
                ], 409);
            }

            // Check if phone number already exists as alternative number in msps table
            $existingMSP = MSPs::where('alternatePhoneNumber', $request->phoneNumber)->first();
            if ($existingMSP) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This phone number is already registered as an alternative number.',
                    'errors' => [
                        'phoneNumber' => ['This phone number is already registered as an alternative number.']
                    ]
                ], 409);
            }

            // Split full name
            $nameParts = $this->splitFullName($request->fullName);

            // Convert to integers
            $stateId = (int) $request->stateId;
            $lgaId = (int) $request->lgaId;

            // Find the hub by LGA
            $hub = Hubs::where('lga', $lgaId)
                ->where('state', $stateId)
                ->where('status', 'active')
                ->first();

            if (!$hub) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No active hub found for the selected location.',
                    'errors' => [
                        'lgaId' => ['The selected LGA is not active or does not exist.']
                    ]
                ], 422);
            }

            // 1. Create User
            $user = User::create([
                'firstName' => $nameParts['firstName'],
                'lastName' => $nameParts['lastName'],
                'otherNames' => $nameParts['otherNames'],
                'phoneNumber' => $request->phoneNumber,
                'email' => $request->email,
                'password' => Hash::make('password'), // Default password
                'role' => 2, // MSP role (adjust based on your role IDs)
                // 'state' => $stateId,
                // 'lga' => $lgaId,
                // 'status' => 'active',
            ]);

            // Generate MSP ID
            $mspId = MSPs::generateMspId();

            // 2. Create MSP
            $msp = MSPs::create([
                'mspId' => $mspId,
                // 'firstName' => $nameParts['firstName'],
                // 'lastName' => $nameParts['lastName'],
                // 'otherNames' => $nameParts['otherNames'],
                // 'phoneNumber' => $request->phoneNumber,
                'alternatePhoneNumber' => null, // This can be updated later if needed
                // 'email' => $request->email,
                'gender' => $request->gender,
                'ageBracket' => $request->age,
                // 'address' => $request->address ?? null,
                // 'stateId' => $stateId,
                // 'lgaId' => $lgaId,
                'hub' => $hub->hubId,
                'project' => 3, // Default project ID
                'userId' => $user->id,
                'trainings_attended' => json_encode($request->trainingsAttended ?? []),
                'status' => 'active',
                'addedBy' => $user->id,
            ]);

            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'MSP registered successfully. Default password: password',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'firstName' => $user->firstName,
                        'lastName' => $user->lastName,
                        'phoneNumber' => $user->phoneNumber,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'msp' => [
                        'mspId' => $msp->mspId,
                        'fullName' => $nameParts['firstName'] . ' ' . $nameParts['lastName'],
                        'phoneNumber' => $msp->phoneNumber,
                        'alternatePhoneNumber' => $msp->alternatePhoneNumber,
                        'email' => $msp->email,
                        'age' => $msp->age,
                        'gender' => $msp->gender,
                        'stateId' => $msp->stateId,
                        'lgaId' => $msp->lgaId,
                        'trainingsAttended' => json_decode($msp->trainings_attended, true) ?? [],
                        'status' => $msp->status,
                    ]
                ]
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('Database error during MSP registration: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // Check for duplicate entry error
            if ($e->getCode() === '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'An MSP with this phone number or email already exists in our system.',
                    'errors' => [
                        'phoneNumber' => ['This phone number or email is already registered.']
                    ]
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete registration due to a system error. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MSP registration failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again or contact support if the issue persists.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Validate MSP by phone number
     * Checks both primary phone and alternative number
     */
    public function validateMSP(string $phoneNumber): JsonResponse
    {
        try {
            // Check in users table first
            $user = User::where('phoneNumber', $phoneNumber)->first();
            
            if ($user) {
                // Check if user is already an MSP
                $msp = MSPs::where('userId', $user->id)->first();
                
                if ($msp) {
                    return response()->json([
                        'exists' => true,
                        'fullname' => trim($msp->firstName . ' ' . $msp->lastName . ' ' . $msp->otherNames),
                        'email' => $msp->email,
                        'gender' => $msp->gender,
                        'age' => $msp->age,
                        'message' => 'Existing MSP found. Details have been prefilled.',
                        'data' => $msp
                    ]);
                } else {
                    // User exists but not an MSP
                    return response()->json([
                        'exists' => false,
                        'message' => 'Phone number belongs to an existing user but not registered as an MSP.'
                    ]);
                }
            }

            // Check in MSPs table - primary phone number
            $msp = MSPs::where('phoneNumber', $phoneNumber)->first();
            
            if ($msp) {
                return response()->json([
                    'exists' => true,
                    'fullname' => trim($msp->firstName . ' ' . $msp->lastName . ' ' . $msp->otherNames),
                    'email' => $msp->email,
                    'gender' => $msp->gender,
                    'age' => $msp->age,
                    'message' => 'Existing MSP found. Details have been prefilled.',
                    'data' => $msp
                ]);
            }

            // Check in MSPs table - alternative number
            $mspAlternative = MSPs::where('alternatePhoneNumber', $phoneNumber)->first();
            
            if ($mspAlternative) {
                return response()->json([
                    'exists' => true,
                    'fullname' => trim($mspAlternative->firstName . ' ' . $mspAlternative->lastName . ' ' . $mspAlternative->otherNames),
                    'email' => $mspAlternative->email,
                    'gender' => $mspAlternative->gender,
                    'age' => $mspAlternative->age,
                    'message' => 'Existing MSP found with this alternative number. Details have been prefilled.',
                    'data' => $mspAlternative
                ]);
            }

            return response()->json([
                'exists' => false,
                'message' => 'Phone number not registered. Please fill in your details to register.'
            ]);

        } catch (\Exception $e) {
            Log::error('MSP validation failed: ' . $e->getMessage());
            return response()->json([
                'exists' => false,
                'message' => 'Unable to validate phone number. Please try again.'
            ], 500);
        }
    }

    /**
     * Helper methods
     */
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
}
