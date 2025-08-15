<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Commodity;

class CommodityController extends Controller
{
    public function index(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    
    $query = Commodity::orderBy('commodityId', 'desc');
      
    
    // Search functionality
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('commodityName', 'like', "%$search%");
        });
    }
    
    $commodity = $query->paginate($perPage);
    
    return response()->json($commodity);
}  

  

    public function store(Request $request)
    {
        // Directly get the data from the request
        $data = $request->all();
    
        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $commodity = Commodity::create($data);
    
        // Return a response, typically JSON
        return response()->json($commodity, 201); // HTTP status code 201: Created
    }


      public function update(Request $request)
{


    $commodity = Commodity::where('commodityId', $request->commodityId)->first();
    if (!$commodity) {
        return response()->json(['message' => 'Commodity not found'], 404);
    }

    $commodity->update(['commodityName' => $request->commodityName]);
    
    //  $commodity->load('states', 'lgas');

    return response()->json([
        'commodityName' => $commodity->commodityName
    ], 200);
}

    public function destroy(Request $request)
    {
        $commodity = Commodity::where('commodityId', $request->commodityId)->first();
        if (!$commodity) {
            return response()->json(['message' => 'Commodity not found'], 404);
        }

        $commodity->delete();
        return response()->json(['message' => 'Commodity deleted successfully'], 200);
    }
}
