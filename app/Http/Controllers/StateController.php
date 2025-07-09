<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\State;
class StateController extends Controller
{
    public function index()
    {
        $states = State::all();
        return response()->json($states);
       
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
