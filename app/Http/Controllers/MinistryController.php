<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ministry;
class MinistryController extends Controller
{
    public function index()
    {
        $ministries = Ministry::all();
        return response()->json($ministries);
    }
    public function show($ministryId)
    {
        $ministry = Ministry::find($ministryId);
        if (!$ministry) {
            return response()->json(['message' => 'Ministry not found'], 404);
        }
        return response()->json($ministry);
    }

    public function store(Request $request)
    {
        // Directly get the data from the request
        $data = $request->all();
    
        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $ministries = Ministry::create($data);
        
        // Return a response, typically JSON
        return response()->json([
            'message' => 'Ministry created successfully',
            'ministryId' => $ministries->ministryId,
            'ministryName' => $ministries->ministryName], 201); // HTTP status code 201: Created
    }

    public function update(Request $request, $ministryId)
    {
        $ministry = Ministry::find($ministryId);
        if (!$ministry) {
            return response()->json(['message' => 'Ministry not found'], 404);
        }

        $data = $request->all();
        $ministry->update($data);

        return response()->json([
            'message' => 'Ministry updated successfully',
            'ministryId' => $ministry->ministryId,
            'ministryName' => $ministry->ministryName], 201); // HTTP status code 201: Created

    }
    
    public function destroy($ministryId)
    {
        $ministry = Ministry::find($ministryId);
        if (!$ministry) {
            return response()->json(['message' => 'Ministry not found'], 404);
        }

        $ministry->delete();
        return response()->json(['message' => 'Ministry deleted successfully']);
    }
    
}
