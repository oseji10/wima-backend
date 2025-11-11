<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
    use App\Models\User;
use App\Mail\MembershipNotificationMail;
use App\Mail\ApplicationStatusMail;
use Illuminate\Support\Facades\Mail;

class MembershipController extends Controller
{
          public function index(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    $membershipType = $request->query('membership_type');
    $status = $request->query('status');

     $query = Membership::orderBy('id', 'desc');
    
    

            if ($membershipType) {
                    $query->where('membershipType', $membershipType); 
            }

           if ($status) {
               $query->where('status', $status);
           }

               if ($search) {
                    $query->where('fullName', 'like', "%$search%")
                          ->orWhere('phoneNumber', 'like', "%$search%")
                          ->orWhere('email', 'like', "%$search%"); 
            }

    $membership_plans = $query->paginate($perPage);
    
    return response()->json($membership_plans);
}



// public function store(Request $request)
// {
//     $validator = Validator::make($request->all(), [
//         'membershipType' => 'required|in:Full Membership,Associate Membership,Youth & Student Membership,Operator Membership,Corporate/Institution Membership',
//         'firstName' => 'required|string|max:255',
//         'lastName' => 'required|string|max:255',
//         'email' => 'required|email|max:255',
//         //  'email' => 'required|email|max:255|unique:membership_applications,email',
//         'phoneNumber' => 'required|string|max:20',
//         'profession' => 'required|string|max:255',
//         'message' => 'nullable|string',
//         'equipmentProof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
//         'studentProof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',

//         'companyDetails' => 'nullable|string|max:255',
//         'companyMission' => 'nullable|string',
//         'operatorExperience' => 'nullable|string',
//         'skillsAssessment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
//         'meansOfIdentification' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
//         'meansOfIdentificationType' => 'nullable|string|max:255',
//     ]);

//     if ($validator->fails()) {
//         return response()->json([
//             'success' => false,
//             'errors' => $validator->errors()
//         ], 422);
//     }

//     $data = $request->only([
//         'membershipType',
//         'firstName',
//         'lastName',
//         'email',
//         'phoneNumber',
//         'profession',
//         'message',
//         'companyDetails',
//         'companyMission',
//         'operatorExperience'

//     ]);

//     // Handle file uploads
//     if ($request->hasFile('equipmentProof')) {
//         $data['equipmentProof'] = $request->file('equipmentProof')->store('equipment_proofs', 'public');
//     }

//     if ($request->hasFile('studentProof')) {
//         $data['studentProof'] = $request->file('studentProof')->store('student_proofs', 'public');
//     }

//     if ($request->hasFile('skillsAssessment')) {
//         $data['skillsAssessment'] = $request->file('skillsAssessment')->store('skills_assessments', 'public');
//     }
//     if ($request->hasFile('identification')) {
//         $data['meansOfIdentification'] = $request->file('identification')->store('means_of_identification', 'public');
//     }
//     $data['meansOfIdentificationType'] = $request->identificationType;

//     $membership = Membership::create($data);

//     // 🔔 Notify all users with roleId = 3
//  $users = User::whereIn('role', [1, 3])->get();
//     foreach ($users as $user) {
//         Mail::to($user->email)->send(new MembershipNotificationMail($data));
//     }

//     return response()->json([
//         'success' => true,
//         'message' => 'Membership application submitted successfully'
//     ], 201);
// }




   public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'membershipType' => 'required|in:Full Membership,Associate Membership,Youth & Student Membership,Operator Membership,Corporate/Institution Membership',
        'fullName' => 'required|string|max:255',
        'dateOfBirth' => 'nullable|date',
        'gender' => 'nullable|in:Male,Female',
        'maritalStatus' => 'nullable|in:Single,Married,Divorced,Widowed',
        'nationality' => 'nullable|string|max:255',
        'homeAddress' => 'nullable|string',
        'state' => 'nullable|string|max:255',
        'lga' => 'nullable|string|max:255',
        'wardDistrict' => 'nullable|string|max:255',
        'community' => 'nullable|string|max:255',
        'phoneNumber' => 'required|string|max:20',
        'email' => 'nullable|email|max:255',
        'occupation' => 'required|string|max:255',
        'organization' => 'nullable|string|max:255',
        'positionTitle' => 'nullable|string|max:255',
        'areaOfExpertise' => 'nullable|string|max:255',
        'reasonForJoining' => 'nullable|string',
        'preferredCommunication' => 'nullable|in:Email,Phone Call,WhatsApp,SMS',
        'identification' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        'identificationType' => 'required|string|in:NIN,Driver\'s License,Voter\'s Card',
        'cacDocument' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120|required_if:membershipType,Corporate/Institution Membership',
        'companyDetails' => 'nullable|string|max:255|required_if:membershipType,Corporate/Institution Membership',
        'companyMission' => 'nullable|string|required_if:membershipType,Corporate/Institution Membership',
        'operatorExperience' => 'nullable|string|required_if:membershipType,Operator Membership',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // ✅ Check if phone number already exists
    $existingMember = Membership::where('phoneNumber', $request->phoneNumber)->first();
    if ($existingMember) {
        return response()->json([
            'success' => false,
            'message' => 'A membership application with this phone number already exists.'
        ], 409);
    }

 

    $data = $request->only([
        'membershipType',
        'fullName',
        'dateOfBirth',
        'gender',
        'maritalStatus',
        'nationality',
        'homeAddress',
        'state',
        'lga',
        'wardDistrict',
        'community',
        'phoneNumber',
        'email',
        'occupation',
        'organization',
        'positionTitle',
        'areaOfExpertise',
        'reasonForJoining',
        'preferredCommunication',
        'companyDetails',
        'companyMission',
        'operatorExperience',
        'identificationType',
    ]);

    // Handle file uploads
    if ($request->hasFile('identification')) {
        $data['meansOfIdentification'] = $request->file('identification')->store('identifications', 'public');
    }

    if ($request->hasFile('cacDocument')) {
        $data['cacDocument'] = $request->file('cacDocument')->store('cac_documents', 'public');
    }

    $data['meansOfIdentificationType'] = $request->identificationType;

    $membership = Membership::create($data);

    // Notify all users with role 1, 3, or 8
    $users = User::whereIn('role', [1, 3, 8])->get();
    foreach ($users as $user) {
        Mail::to($user->email)->send(new MembershipNotificationMail($data));
    }

    return response()->json([
        'success' => true,
        'message' => 'Membership application submitted successfully'
    ], 201);
}


// public function updateStatus(Request $request, $id)
// {
//     $validator = Validator::make($request->all(), [
//         'status' => 'required|in:pending,approved,rejected'
//     ]);

//     if ($validator->fails()) {
//         return response()->json([
//             'success' => false,
//             'errors' => $validator->errors()
//         ], 422);
//     }

//     $membership = Membership::find($id);
//     if (!$membership) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Membership application not found'
//         ], 404);
//     }

//     $membership->status = $request->status;
//     $membership->save();

//     return response()->json([
//         'success' => true,
//         'message' => 'Membership application status updated successfully'
//     ]);

// }


public function updateStatus(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'status' => 'required|in:pending,approved,rejected'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $membership = Membership::find($id);
    if (!$membership) {
        return response()->json([
            'success' => false,
            'message' => 'Membership application not found'
        ], 404);
    }

    $oldStatus = $membership->status;
    $membership->status = $request->status;
    $membership->save();

    // Send notification email to the applicant if email is provided
    if ($membership->email && $oldStatus !== $request->status) {
        try {
            Mail::to($membership->email)->send(new ApplicationStatusMail($membership));
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to send status update email: ' . $e->getMessage());
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Membership application status updated successfully'
    ]);
}


public function destroy($id)
    {
        $application = Membership::findOrFail($id);

        // Only allow deletion if status is rejected
        if ($application->status !== 'rejected') {
            return response()->json(['message' => 'Only rejected applications can be deleted.'], 403);
        }

        // Optionally, delete associated files if needed
        // For example:
        // if ($application->meansOfIdentification) {
        //     Storage::delete($application->meansOfIdentification);
        // }
        // if ($application->cacDocument) {
        //     Storage::delete($application->cacDocument);
        // }

        $application->delete();

        return response()->json(['message' => 'Application deleted successfully.']);
    }


}