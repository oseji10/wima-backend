<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
class ProjectsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $query = Project::query();
        $projects = $query->paginate($perPage);
        return response()->json($projects);
    }

   

    public function store(Request $request)
    {
        // Directly get the data from the request
        $data = $request->all();
    
        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $projects = Project::create($data);

        // Return a response, typically JSON
        return response()->json($projects, 201); // HTTP status code 201: Created
    }


  public function update(Request $request)
{


    $project = Project::where('projectId', $request->projectId)->first();
    if (!$project) {
        return response()->json(['message' => 'Project not found'], 404);
    }

    $project->update(['projectName' => $request->projectName]);

    return response()->json([
        'projectId' => $project->projectId,
        'projectName' => $project->projectName
    ], 200);
}

    public function destroy(Request $request, $projectId)
    {
        $project = Project::where('projectId', $request->projectId)->first();
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        $project->delete();
        return response()->json(['message' => 'Project deleted successfully'], 200);
    }

    
    
}
