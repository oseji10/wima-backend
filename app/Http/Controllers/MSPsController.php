<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MSPs;
use App\Models\Lgas;
use App\Models\User;
use Illuminate\Support\Str;


class MSPsController extends Controller
{
public function index(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    $state = $request->query('state');
    $lga = $request->query('lga'); 
    $project = $request->query('projectId');

    $query = MSPs::with(['users', 'hub.lgas', 'projects'])->orderBy('id', 'desc');

    // Filter by state
    if ($state) {
        $query->whereHas('hub', function($q) use ($state) {  // Changed $search to $state
            $q->where('state', $state);  // Changed to exact match instead of LIKE
        });
    }
    
    // Filter by LGA
    if ($lga) {
        $query->whereHas('hub', function($q) use ($lga) {
            $q->where('lga', $lga);  // Exact match for LGA
        });
    }
    
   if ($project) {
        $query->where('projectId', $project);
    }

    // Search functionality
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('mspId', 'like', "%$search%")
              ->orWhereHas('users', function($q) use ($search) {
                  $q->where('firstName', 'like', "%$search%")
                    ->orWhere('lastName', 'like', "%$search%")
                    ->orWhere('otherNames', 'like', "%$search%")
                    ->orWhere('phoneNumber', 'like', "%$search%")
                     ->orWhereRaw("CONCAT(firstName, ' ', lastName , ' ', otherNames) LIKE ?", ["%{$search}%"]);
              });
        });
    }
    
    $msps = $query->paginate($perPage);
    
    return response()->json($msps);
}  // public function index()
    // {
    //     $hubs = Hubs::with('states', 'lgas')->get();
    //     return response()->json($hubs);
       
    // }

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
        'hub' => 'nullable|string|max:255',
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
    $msp = MSPs::create([
        'userId' => $user->id,
        'alternatePhoneNumber' => $request->alternatePhoneNumber,
        'gender' => $request->gender,
        'projectId' => $request->projectId,
        'hub' => $request->hub,
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
    
}
