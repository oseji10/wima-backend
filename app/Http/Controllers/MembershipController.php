<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MembershipController extends Controller
{
          public function index(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    $state = $request->query('state');
    $lga = $request->query('lga');

     $query = Membership::orderBy('id', 'desc');
    
            // // if ($state) {
            // //     $query->whereHas('subhubs.hub', function($q) use ($state) {
            // //         $q->where('state', $state);
            // //     });
            // // }

            // // if ($lga) {
            // //     $query->whereHas('subhubs.hub', function($q) use ($lga) {
            // //         $q->where('lga', $lga);
            // //     });
            // // }

            // // if ($search) {
            // //     $query->where(function($q) use ($search) {
            // //         // $q->where('mspId', 'like', "%$search%")
            // //         //   $q->whereHas('users', function($q) use ($search) {
            // //               $q->where('farmerFirstName', 'like', "%$search%")
            // //                 ->orWhere('farmerLastName', 'like', "%$search%")
            // //                 ->orWhere('farmerOtherNames', 'like', "%$search%")
            // //                 ->orWhere('phoneNumber', 'like', "%$search%"); // Added phone number search
            // //         //   });
            // //     });
            // }

    $membership_plans = $query->paginate($perPage);
    
    return response()->json($membership_plans);
}

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membershipType' => 'required|in:Full Membership,Associate Membership,Youth & Student Membership,Operator Membership,Corporate/Institution Membership',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:membership_applications,email',
            'phoneNumber' => 'required|string|max:20',
            'profession' => 'required|string|max:255',
            'message' => 'nullable|string',
            'equipmentProof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'studentProof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'companyDetails' => 'nullable|string|max:255',
            'companyMission' => 'nullable|string',
            'operatorExperience' => 'nullable|string',
            'skillsAssessment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'membershipType',
            'firstName',
            'lastName',
            'email',
            'phoneNumber',
            'profession',
            'message',
            'companyDetails',
            'companyMission',
            'operatorExperience'
        ]);

        // Handle file uploads
        if ($request->hasFile('equipmentProof')) {
            $data['equipmentProof'] = $request->file('equipmentProof')->store('equipment_proofs', 'public');
        }

        if ($request->hasFile('studentProof')) {
            $data['studentProof'] = $request->file('studentProof')->store('student_proofs', 'public');
        }

        if ($request->hasFile('skillsAssessment')) {
            $data['skillsAssessment'] = $request->file('skillsAssessment')->store('skills_assessments', 'public');
        }

        Membership::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Membership application submitted successfully'
        ], 201);
    }
}