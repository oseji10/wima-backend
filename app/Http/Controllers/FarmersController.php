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

        return response()->json(['exists' => $farmer ? true : false, 'fullname' => $farmer ? $farmer->farmerFirstName . ' ' . $farmer->farmerLastName : null]);
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


    
}
