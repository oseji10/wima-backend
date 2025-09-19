<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hubs;
use App\Models\Lgas;
use App\Models\Subhubs;
use App\Models\ActiveStates;
use App\Models\State;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;
use Illuminate\Support\Facades\Auth;

class HubsController extends Controller
{

  public function allActiveHubs()
{
    $hubs = Hubs::with('state_info', 'lga_info')->get();
    return response()->json($hubs);
}

 public function hubsInState($stateId)
{
    $hubs = Hubs::with('lga_info')->where('state', $stateId)->get();
    return response()->json($hubs);
}

public function index(Request $request)
{
    $user = Auth::user();
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    $state = $request->query('state');
    $lga = $request->query('lga');

    $query = Hubs::with('states', 'lgas', 'subhubs')->orderBy('hubId', 'desc');

    // Initialize variables to avoid "undefined variable" errors
    $stateCoordinator = null;
    $communityLead = null;

    // Role-based filtering
    if ($user->role === 4) {
        // State coordinators see only their state's hubs
        $stateCoordinator = StateCoordinators::where('userId', $user->id)->first();
        if ($stateCoordinator && $stateCoordinator->stateId) {
            $query->where('state', $stateCoordinator->stateId);
        } else {
            // Return empty result if stateId is not set
            return response()->json([
                'data' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => 1,
                'last_page' => 1,
            ]);
        }

        if ($lga) {
            $query->where('lga', $lga);
        }
    } elseif ($user->role === 5) {
        // Community leads see only their LGA's hubs
        $communityLead = CommunityLead::where('userId', $user->id)->first();
        if ($communityLead && $communityLead->lga) {
            $query->where('lga', $communityLead->lga);
        } else {
            // Return empty result if lga is not set
            return response()->json([
                'data' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => 1,
                'last_page' => 1,
            ]);
        }
    } elseif ($user->role === 1 || $user->role === 3) {
        // Admin and National Coordinator can filter by state and LGA
        if ($state) {
            $query->where('state', $state);
        }
        if ($lga) {
            $query->where('lga', $lga);
        }
    }

    // Search functionality with role-based restrictions
if ($search) {
    $query->where(function($q) use ($search, $user, $stateCoordinator, $communityLead) {
        // Apply role restrictions
        if ($user->role === 4 && $stateCoordinator && $stateCoordinator->stateId) {
            $q->where('state', $stateCoordinator->stateId);
        } elseif ($user->role === 5 && $communityLead && $communityLead->lga) {
            $q->where('lga', $communityLead->lga);
        }

        // Search by hub name from lgas table
        $q->orWhereHas('lgas', function($sub) use ($search) {
            $sub->where('lgaName', 'like', "%$search%");
        });

        // Optional: also allow searching by state name
        $q->orWhereHas('states', function($sub) use ($search) {
            $sub->where('stateName', 'like', "%$search%");
        });
    });
}


    $hubs = $query->paginate($perPage);

    return response()->json($hubs);
}

    public function activeHubs()
    {
        $hubs = ActiveStates::with('state_info')->get();
        return response()->json($hubs);
       
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
        // Directly get the data from the request
        $data = $request->all();
    
        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $hubs = Hubs::create($data);
    
        // Return a response, typically JSON
        return response()->json($hubs, 201); // HTTP status code 201: Created
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

    public function destroy(Request $request, $hubId)
    {
        $hub = Hubs::where('hubId', $request->hubId)->first();
        if (!$hub) {
            return response()->json(['message' => 'Hub not found'], 404);
        }

        $hub->delete();
        return response()->json(['message' => 'Hub deleted successfully'], 200);
    }
    
}
