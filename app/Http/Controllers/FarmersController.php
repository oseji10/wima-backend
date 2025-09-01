<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Farmers;
use App\Models\Lgas;
use App\Models\Subhubs;
use App\Models\Hubs;
use Illuminate\Support\Str;

class FarmersController extends Controller
{
      public function index(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    $state = $request->query('state');
    $lga = $request->query('lga');

     $query = Farmers::with('hubs.states', 'hubs.lgas', 'hubs.subhub', 'msp')->orderBy('id', 'desc');
    
    if ($state) {
        $query->whereHas('hubs', function($q) use ($state) {
            $q->where('state', $state);
        });
    }

    if ($lga) {
        $query->whereHas('hubs.subhub', function($q) use ($lga) {
            $q->where('lga', $lga);
        });
    }

    if ($search) {
        $query->where(function($q) use ($search) {
            // $q->where('mspId', 'like', "%$search%")
            //   $q->whereHas('users', function($q) use ($search) {
                  $q->where('farmerFirstName', 'like', "%$search%")
                    ->orWhere('farmerLastName', 'like', "%$search%")
                    ->orWhere('farmerOtherNames', 'like', "%$search%")
                    ->orWhereRaw("CONCAT(farmerFirstName, ' ', farmerLastName , ' ', farmerOtherNames) LIKE ?", ["%{$search}%"])
                    ->orWhere('phoneNumber', 'like', "%$search%"); // Added phone number search
            //   });
        });
    }

    $farmers = $query->paginate($perPage);
    
    return response()->json($farmers);
}
    // public function index()
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
       $hub = Hubs::where('state', $request->hub)
        ->where('lga', $request->subHub)
        ->first();

    if (!$hub) {
        return response()->json([
            'error' => 'No active hub found for the selected state and LGA combination'
        ], 422);
    }

    // Generate farmerId
    $farmerId = strtoupper(Str::random(10));

    // Prepare data for creation
    $data = $request->all();
    $data['farmerId'] = $farmerId;
    $data['hub'] = $hub->hubId; // Use the actual hubId from the Hub model

    // Create farmer record
    $farmer = Farmers::create($data);

    return response()->json($farmer, 201);
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

    public function search(Request $request){
        $search = $request->query('search');
        $search_result = Farmers::where('farmerFirstName', 'like', "%$search%")
        ->orWhere('farmerLastName', 'like', "%$search%")
        ->orWhere('farmerId', 'like', "%$search%")
        ->get();
        return response()->json($search_result);
    }
    
}
