<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lgas;
use App\Models\State;
class LgaController extends Controller
{
    public function index()
    {
        $lgas = Lgas::all();
        return response()->json($lgas);
       
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


    
    public function getSubHubsByHubs(Request $request)
    {
        // Validate the request
        $request->validate([
            'hubId' => 'required|string'
        ]);

        try {
            // Option 1: If you're passing state name
            $hub = Hubs::where('hubId', $request->hubId)->first();
            
            // Option 2: If you're passing state ID
            // $state = State::find($request->state);
            
            if (!$hub) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hub not found'
                ], 404);
            }

            // Get LGAs for the state
            $subhubs = SubHubs::where('hubId', $hub->hubId)
                      ->orderBy('subHubId')
                      ->get(['subHubId', 'subHubName']);

            return response()->json([
                'success' => true,
                'data' => $subhubs
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
        $roles = Roles::create($data);
    
        // Return a response, typically JSON
        return response()->json($roles, 201); // HTTP status code 201: Created
    }
    
}
