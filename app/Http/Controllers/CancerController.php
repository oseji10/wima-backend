<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cancer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CancerController extends Controller
{
    public function index()
    {
        // This method should return a list of cancers
        $cancers = Cancer::all();
        if ($cancers->isEmpty()) {
            return response()->json(['message' => 'No cancers found'], 404);
        }
        return response()->json($cancers);
    }


     public function store(Request $request)
    {
       $validated = $request->validate([
            'cancerName' => 'required|string|max:255',
        ]);

        $cancers = Cancer::create($validated);
        return response()->json($cancers, 201); // HTTP status code 201: Created

    }

   public function edit(Request $request, $cancerId)
{
    $validated = $request->validate([
        'cancerName' => 'required|string|max:255',
    ]);

    $cancer = Cancer::where('cancerId', $cancerId)->first();
    if (!$cancer) {
        return response()->json(['message' => 'Cancer type not found'], 404);
    }

    $cancer->update($validated);
    
    return response()->json([
        'cancerId' => $cancer->cancerId,
        'cancerName' => $cancer->cancerName
    ], 200);
}

    public function destroy($cancerId)
    {
        $cancer = Cancer::where('cancerId', $cancerId)->first();
        if (!$cancer) {
            return response()->json(['message' => 'Cancer type not found'], 404);
        }

        $cancer->delete();
        return response()->json(['message' => 'Cancer type deleted successfully'], 200);
    }
    public function show($cancerId)
    {
        $cancer = Cancer::where('cancerId', $cancerId)->first();
        if (!$cancer) {
            return response()->json(['message' => 'Cancer type not found'], 404);
        }
        return response()->json($cancer);
    }
   
}
