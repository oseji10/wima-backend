<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Services;

class ServicesController extends Controller
{
    public function index(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    
    $query = Services::orderBy('serviceId', 'desc');
      
    
    // Search functionality
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('serviceName', 'like', "%$search%")
                ->orWhere('measurementUnit', 'like', "%$search%")
                ->orWhere('costPerUnit', 'like', "%$search%")
                ->orWhere('addedBy', 'like', "%$search%")
                ->orWhere('status', 'like', "%$search%");
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
}
