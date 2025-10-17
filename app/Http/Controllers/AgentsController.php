<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agents;
use App\Models\State;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\StateCoordinators;
class AgentsController extends Controller
{
   public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        // $state = $request->query('state');
        // $lga = $request->query('lga');

        $query = Agents::orderBy('id', 'desc');
        $state_coordinators = StateCoordinators::where('userId', $user->id)->first();

        // // Role-based filtering
        // if ($user->role === 4) {
        //     // State coordinators see only their state's records
        //     $query->whereHas('hubs', function($q) use ($state_coordinators) {
        //         $q->where('state', $state_coordinators->stateId);
        //     });

        //     if ($lga) {
        //         $query->whereHas('hubs', function($q) use ($lga) {
        //             $q->where('lga', $lga);
        //         });
        //     }
        
        // } elseif ($user->role === 1 || $user->role === 3) {
        //     // Admin and National Coordinator can filter by state and LGA
        //     if ($state) {
        //         $query->whereHas('hubs', function($q) use ($state) {
        //             $q->where('state', $state);
        //         });
        //     }
        //     if ($lga) {
        //         $query->whereHas('hubs', function($q) use ($lga) {
        //             $q->where('lga', $lga);
        //         });
        //     }
        // }

        // // Project filtering for all roles
        // if ($project) {
        //     $query->where('project', $project);
        // }

        // Search functionality with role-based restrictions
        // if ($search) {
        //     $query->where(function($q) use ($search, $user, $state_coordinators, $community_lead) {
        //         // Apply state or LGA restriction for search
        //         if ($user->role === 4) {
        //             $q->whereHas('hubs', function($hq) use ($state_coordinators) {
        //                 $hq->where('state', $state_coordinators->stateId);
        //             });
        //         } elseif ($user->role === 5) {
        //             $q->whereHas('hubs', function($hq) use ($community_lead) {
        //                 $hq->where('lga', $community_lead->lga);
        //             });
        //         }
        //         // Search conditions
        //         $q->where(function($sq) use ($search) {
        //             $sq->where('farmerFirstName', 'like', "%$search%")
        //                ->orWhere('farmerLastName', 'like', "%$search%")
        //                ->orWhere('farmerOtherNames', 'like', "%$search%")
        //                ->orWhereRaw("CONCAT(farmerFirstName, ' ', farmerLastName, ' ', farmerOtherNames) LIKE ?", ["%{$search}%"])
        //                ->orWhere('phoneNumber', 'like', "%$search%");
        //         });
        //     });
        // }

        $agents = $query->paginate($perPage);

        return response()->json($agents);
    }

     


    public function store(Request $request)
    {
         $agentId = strtoupper(Str::random(10));
        // Directly get the data from the request
        $data = $request->all();
        $data['agentId'] = $agentId;
        $data['agentName']  = $request->firstName . ' ' . $request->lastName;

        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $agents = Agents::create($data);

        // Return a response, typically JSON
         return response()->json([
        'agentId' => $agents->agentId,
        'agentName' => $agents->agentName,
        'phoneNumber' => $agents->phoneNumber,
        'email' => $agents->email
    ], 200);
        // return response()->json($agents, 201); // HTTP status code 201: Created
    }

    public function validateAgentId(Request $request, $agentId)
    {
       

        $agent = Agents::where('agentId', $agentId)->first();

        return response()->json(['exists' => $agent ? true : false, 'fullname' => $agent ? $agent->agentName : null]);
    }
    
}
