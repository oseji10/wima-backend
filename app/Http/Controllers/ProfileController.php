<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        
        // Load relationships if needed
        $user->load(['userRole', 'stateCoordinator.state', 'communityLead.lgaInfo']);
        
        // Format the response
        $profile = [
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'email' => $user->email,
            'phoneNumber' => $user->phoneNumber,
            'role' => $user->userRole?->roleName ?? $user->role,
            'stateName' => $this->getUserState($user),
            'communityName' => $this->getUserCommunity($user),
        ];
        
        return response()->json($profile);
    }

    /**
     * Update the authenticated user's profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'phoneNumber' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        
        $user->firstName = $request->firstName;
        $user->lastName = $request->lastName;
        $user->phoneNumber = $request->phoneNumber;
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'phoneNumber' => $user->phoneNumber,
        ]);
    }

    /**
     * Change the authenticated user's password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    /**
     * Get user's state name based on their role
     */
    private function getUserState($user)
    {
        if ($user->stateCoordinator && $user->stateCoordinator->state) {
            return $user->stateCoordinator->state->stateName;
        }
        
        if ($user->communityLead && $user->communityLead->lgaInfo && $user->communityLead->lgaInfo->state) {
            return $user->communityLead->lgaInfo->state->stateName;
        }
        
        return 'N/A';
    }

    /**
     * Get user's community name based on their role
     */
    private function getUserCommunity($user)
    {
        if ($user->communityLead && $user->communityLead->lgaInfo) {
            return $user->communityLead->lgaInfo->lgaName;
        }
        
        return 'N/A';
    }
}