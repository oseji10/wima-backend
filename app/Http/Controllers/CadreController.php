<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cadre;
class CadreController extends Controller
{
    public function index()
    {
        $cadres = Cadre::all();
        return response()->json($cadres);
    }

    public function show($cadreId)
    {
        $cadre = Cadre::find($cadreId);
        if (!$cadre) {
            return response()->json(['message' => 'Cadre not found'], 404);
        }
        return response()->json($cadre);
    }

    public function store(Request $request)
    {
        // Directly get the data from the request
        $data = $request->all();
    
        // Create a new cadre with the data (ensure that the fields are mass assignable in the model)
        $cadre = Cadre::create($data);
    
        // Return a response, typically JSON
        return response()->json($cadre, 201); // HTTP status code 201: Created
    }

    public function update(Request $request, $cadreId)
    {
        $cadre = Cadre::find($cadreId);
        if (!$cadre) {
            return response()->json(['message' => 'Cadre not found'], 404);
        }

        $data = $request->all();
        $cadre->update($data);

        return response()->json([
            'message' => 'Cadre updated successfully',
            'cadreId' => $cadre->cadreId,
            'cadreName' => $cadre->cadreName,
            'salary' => $cadre->salary
        ], 200); // HTTP status code 200: OK
    }

    public function destroy($cadreId)
    {
        $cadre = Cadre::find($cadreId);
        if (!$cadre) {
            return response()->json(['message' => 'Cadre not found'], 404);
        }

        $cadre->delete();
        return response()->json(['message' => 'Cadre deleted successfully'], 200); // HTTP status code 200: OK
    }
  

   
}
