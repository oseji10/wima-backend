<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;

use Illuminate\Support\Facades\Auth;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $state = $request->query('state');
        $lga = $request->query('lga');
        $project = $request->query('projectId');
        $category = $request->query('equipmentCategory');

        $query = Equipment::with('category', 'hub.states', 'hub.lgas', 'owner')->orderBy('equipmentId', 'desc');
        $state_coordinators = StateCoordinators::where('userId', $user->id)->first();
        $community_lead = CommunityLead::where('userId', $user->id)->first();

        // Role-based filtering
        if ($user->role === 4) {
            // State coordinators see only their state's records
            $query->whereHas('hub', function($q) use ($state_coordinators) {
                $q->where('state', $state_coordinators->stateId);
            });

            if ($lga) {
                $query->whereHas('hub', function($q) use ($lga) {
                    $q->where('lga', $lga);
                });
            }
        } elseif ($user->role === 5) {
            // Community leads see only their community's records
            $query->whereHas('hub', function($q) use ($community_lead) {
                $q->where('lga', $community_lead->lga);
            });
        } elseif ($user->role === 1 || $user->role === 3) {
            // Admin and National Coordinator can filter by state and LGA
            if ($state) {
                $query->whereHas('hub', function($q) use ($state) {
                    $q->where('state', $state);
                });
            }
            if ($lga) {
                $query->whereHas('hub', function($q) use ($lga) {
                    $q->where('lga', $lga);
                });
            }
        }

        // Project filtering for all roles
        if ($project) {
            $query->where('project', $project);
        }

         if ($category) {
            $query->where('equipmentCategory', $category);
        }

        // Search functionality with role-based restrictions
        if ($search) {
            $query->where(function($q) use ($search, $user, $state_coordinators, $community_lead) {
                // Apply state or LGA restriction for search
                if ($user->role === 4) {
                    $q->whereHas('hubs', function($hq) use ($state_coordinators) {
                        $hq->where('state', $state_coordinators->stateId);
                    });
                } elseif ($user->role === 5) {
                    $q->whereHas('hubs', function($hq) use ($community_lead) {
                        $hq->where('lga', $community_lead->lga);
                    });
                }
                // Search conditions
                $q->where(function($sq) use ($search) {
                    $sq->where('farmerFirstName', 'like', "%$search%")
                       ->orWhere('farmerLastName', 'like', "%$search%")
                       ->orWhere('farmerOtherNames', 'like', "%$search%")
                       ->orWhereRaw("CONCAT(farmerFirstName, ' ', farmerLastName, ' ', farmerOtherNames) LIKE ?", ["%{$search}%"])
                       ->orWhere('phoneNumber', 'like', "%$search%");
                });
            });
        }

        $farmers = $query->paginate($perPage);
        
        return response()->json($farmers);
    }

    public function equipmentCategories()
    {
        $categories = EquipmentCategory::all();
        return response()->json($categories);
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
