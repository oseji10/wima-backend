<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Services;
use App\Models\Hubs;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;
use Illuminate\Support\Facades\Auth;

class ServicesController extends Controller
{
public function priceAlert(){
     $prices = Services::with('category', 'hubs.lga_info')->orderBy('serviceId', 'desc')->get();
        return response()->json($prices);
}

//     public function index(Request $request)
// {
//     $perPage = $request->query('per_page', 10);
//     $search = $request->query('search');

//     $query = Services::orderBy('serviceId', 'desc');


//     // Search functionality
//     if ($search) {
//         $query->where(function($q) use ($search) {
//             $q->where('serviceName', 'like', "%$search%")
//                 ->orWhere('measurementUnit', 'like', "%$search%")
//                 ->orWhere('costPerUnit', 'like', "%$search%")
//                 ->orWhere('addedBy', 'like', "%$search%")
//                 ->orWhere('status', 'like', "%$search%");
//         });
//     }

//     $services = $query->paginate($perPage);

//     return response()->json($services);
// }

    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $state = $request->query('state');
        $lga = $request->query('lga');

        $query = Services::with('hubs.lgas')->orderBy('serviceId', 'desc');

        $state_coordinators = StateCoordinators::where('userId', $user->id)->first();
        $community_lead = CommunityLead::where('userId', $user->id)->first();

        // Role-based filtering
        if ($user->role === 4) {
            // State coordinators see only their state's records
            $query->whereHas('hubs.lgas', function($q) use ($state_coordinators) {
                $q->where('state', $state_coordinators->stateId);
            });

            if ($lga) {
                $query->whereHas('hubs.lgas', function($q) use ($lga) {
                    $q->where('lga', $lga);
                });
            }
        } elseif ($user->role === 5) {
            // Community leads see only their community's records
            $query->whereHas('hubs.lgas', function($q) use ($community_lead) {
                $q->where('lga', $community_lead->lga);
            });
        } elseif ($user->role === 1 || $user->role === 3) {
            // Admin and National Coordinator can filter by state and LGA
            if ($state) {
                $query->whereHas('hubs.lgas', function($q) use ($state) {
                    $q->where('state', $state);
                });
            }
            if ($lga) {
                $query->whereHas('hubs.lgas', function($q) use ($lga) {
                    $q->where('lga', $lga);
                });
            }
        }

        // Search functionality with role-based restrictions
        if ($search) {
            $query->where(function($q) use ($search, $user, $state_coordinators, $community_lead) {
                // Apply state or LGA restriction for search
                if ($user->role === 4) {
                    $q->whereHas('hubs.lgas', function($hq) use ($state_coordinators) {
                        $hq->where('state', $state_coordinators->stateId);
                    });
                } elseif ($user->role === 5) {
                    $q->whereHas('hubs.lgas', function($hq) use ($community_lead) {
                        $hq->where('lga', $community_lead->lga);
                    });
                }
                // Search conditions
                $q->where(function($sq) use ($search) {
                    $sq->where('serviceName', 'like', "%{$search}%");
                    //    ->orWhere('measurementUnit', 'like', "%{$search}%")
                    //    ->orWhere('costPerUnit', 'like', "%{$search}%")
                    //    ->orWhere('addedBy', 'like', "%{$search}%")
                    //    ->orWhere('status', 'like', "%{$search}%")
                });
            });
        }

        $services = $query->paginate($perPage);

        return response()->json($services);
    }



    public function store(Request $request)
    {
        // Directly get the data from the request
        $data = $request->all();

        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $services = Services::create($data);

        // Return a response, typically JSON
        return response()->json($services, 201); // HTTP status code 201: Created
    }


      public function update(Request $request)
{


    $services = Services::where('serviceId', $request->serviceId)->first();
    if (!$services) {
        return response()->json(['message' => 'Service not found'], 404);
    }

    $services->update(['serviceName' => $request->serviceName, 'cost' => $request->cost, 'costPerUnit' => $request->costPerUnit, 'measuringUnit' => $request->measuringUnit]);

    //  $services->load('states', 'lgas');

    return response()->json([
        'serviceId' => $services->serviceId,
        'measuringUnit' => $services->measuringUnit,
        'cost' => $services->cost,
        'costPerUnit' => $services->costPerUnit,
        'serviceName' => $services->serviceName
    ], 200);
}

    public function destroy(Request $request)
    {
        $service = Services::where('serviceId', $request->serviceId)->first();
        if (!$service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        $service->delete();
        return response()->json(['message' => 'Service deleted successfully'], 200);
    }


     public function loadServices(Request $request)
    {

        $hub = Hubs::where('hubId', $request->hubId)->first();

        $services = Services::with('hubs.lgas')->orderBy('serviceId', 'desc')
        ->where('hub', $hub->hubId)
        ->orderBy('serviceId', 'desc')
        ->get();


        return response()->json($services);
    }

    public function loadServices2(Request $request)
    {

        $hub = Hubs::where('lga', $request->hubId)->first();

        $services = Services::with('hubs.lgas')->orderBy('serviceId', 'desc')
        ->where('hub', $hub->hubId)
        ->orderBy('serviceId', 'desc')
        ->get();


        return response()->json($services);
    }
}
