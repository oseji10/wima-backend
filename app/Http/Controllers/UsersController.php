<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Staff; 
use App\Models\StaffType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;
use DB;
use Illuminate\Http\JsonResponse;
use App\Models\Lgas;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;
class UsersController extends Controller
{
    // public function index()
    // {
    //     $users = User::with('staff.staff_type')->get();
    //     return response()->json($users);
       
    // }

public function index(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    $roleId = $request->query('role');

    // roles you want to exclude
    $excludedRoles = [0];

    $query = User::with('user_role', 'state_coordinator.state', 'community_lead.lga_info.state')
          ->orderBy('id', 'desc')
        ->whereNotIn('role', $excludedRoles);

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('firstName', 'like', "%$search%")
              ->orWhere('lastName', 'like', "%$search%")
              ->orWhere('email', 'like', "%$search%");
        });
    }

    if ($roleId) {
        $query->where('role', $roleId);
    }

    $users = $query->paginate($perPage);

    return response()->json($users);
}




public function createUser(Request $request)
{
    $request->validate([
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'otherNames' => 'nullable|string|max:255',
        'phoneNumber' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255|unique:users,email',
        'role' => 'required|integer|exists:roles,roleId',
        'stateId' => 'required_if:role,4|integer|exists:states,stateId', // Required if role is State Coordinator
        'communityId' => 'required_if:role,5|integer|exists:lgas,lgaId', // Required if role is Community Lead
        // Add any other necessary validation rules
    ]);
    $password = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);
    
    // Create user
    $user = User::create([
        'firstName' => $request->firstName,
        'lastName' => $request->lastName,
        'otherNames' => $request->otherNames,
        'phoneNumber' => $request->phoneNumber,
        'email' => $request->email,
        'password' => Hash::make($password),
        'role' => $request->role,
    ]);

    // Handle role-specific data
    if ($request->role == 4) {
        // Role 2: State Coordinator - save state information
        if ($request->has('stateId')) {
            StateCoordinators::create([
                'userId' => $user->id,
                'stateId' => $request->stateId,
                // Add any other relevant fields for StateCoordinator
            ]);
        }
    } elseif ($request->role == 5) {
        // Role 3: Community Lead - save hub information
        if ($request->has('communityId')) {
            CommunityLead::create([
                'userId' => $user->id,
                'lga' => $request->communityId,
                // Add any other relevant fields for CommunityLead
            ]);
        }
    }

    Log::info('User created:', ['email' => $user->email, 'password' => $password]);

    // Send email
    try {
        // Mail::to($user->email)->send(new WelcomeEmail($user->firstName, $user->lastName, $user->email, $user->phoneNumber));
        Mail::to($user->email)->send(new WelcomeEmail($user->email, $user->firstName, $user->lastName, $password, $user->phoneNumber));
        Log::info('Email sent successfully to ' . $user->email);
    } catch (\Exception $e) {
        Log::error('Email sending failed: ' . $e->getMessage());
    }

    // Return response
    return response()->json([
        'message' => "User successfully created",
    ]);
}


    public function staff_type()
    {
        $staffTypes = StaffType::all();
        return response()->json($staffTypes);
       
    }

    public function store(Request $request)
    {
    
        $validatedData = $request->validate([
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'otherNames' => 'nullable|string|max:255',
        'phoneNumber' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255|unique:users,email',
        'staff.staffType' => 'required|integer|exists:staff_type,typeId',
        'staff.lga' => 'required|integer|exists:lgas,lgaId',
    ]);

    $default_password = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);

    // Create user
    $user = User::create([
        'firstName' => $request->firstName,
        'lastName' => $request->lastName,
        'phoneNumber' => $request->phoneNumber,
        'email' => $request->email,
        'password' => Hash::make($default_password),
        'role' => 2,
    ]);


    
    $data = array_merge($validatedData, [
        'userId' => $user->id,
        'effectiveFrom' => now(),
        'isActive' => 'true',
        'effectiveUntil' => null,
        'supervisor' => $request->staff['supervisor'] ?? null, // Optional
        'lga' => $request->staff['lga'], // Ensure this is set correctly
        'staffType' => $request->staff['staffType'], // Ensure this is set correctly
    ]); 
    $staff = Staff::create($data);
    Log::info('User created:', ['email' => $user->email]);

    // Send email
    try {
        Mail::to($user->email)->send(new WelcomeEmail($user->firstName, $user->lastName, $user->email, $default_password));
        Log::info('Email sent successfully to ' . $user->email);
    } catch (\Exception $e) {
        Log::error('Email sending failed: ' . $e->getMessage());
    }

    // Return response
      
    $staff->load('staff_type', 'lga_info', 'supervisor_info');
    return response()->json([
        'message' => "User successfully created",
        'password' => $default_password,
        'staffId' => $staff->staffId,
        'firstName' => $user->firstName,
        'lastName' => $user->lastName,
        'otherNames' => $user->otherNames,
        'phoneNumber' => $user->phoneNumber,
        'email' => $user->email,
        'staffType' => $staff->staff_type->typeName,
        'lga' => $staff->lga_info->lgaName,
        'supervisor' => $staff->supervisor_info ? $staff->supervisor_info->firstName . ' ' . $staff->supervisor_info->lastName : null,
    ], 201);
}

    
        public function update(Request $request, $id)  
{
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->update($request->all());
        return response()->json($user);
    }

   public function destroy($id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            // Find the user
            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => 'Staff not found'], 404);
            }

            // Find the associated staff record
            // $staff = Staff::where('userId', $id)->first();
            // if (!$staff) {
            //     return response()->json(['message' => 'Associated staff record not found'], 404);
            // }

            // Delete both records
            // $staff->delete();
            $user->delete();

            return response()->json(['message' => 'User deleted successfully']);
        }, 5);
    }
}
