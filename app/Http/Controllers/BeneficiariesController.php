<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Beneficiary;
use App\Models\User; 
use App\Models\BeneficiaryType;
use App\Models\BeneficiaryImage;
use App\Models\Transactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BeneficiariesController extends Controller
{
    public function index()
    {
        $beneficiaries = Beneficiary::with('enrolled_by', 'beneficiary_type', 'lga_info', 'cadre_info', 'ministry_info', 'beneficiary_image')
        ->orderBy('created_at', 'desc')
        ->get();
        return response()->json($beneficiaries);

    }

    public function getOnboarderBeneficiaries()
    {
        $user = auth()->user();
        // return $user->staff->lga;
        $beneficiaries = Beneficiary::with('enrolled_by', 'beneficiary_type', 'lga_info', 'cadre_info', 'ministry_info', 'beneficiary_image')
        ->where('lga', $user->staff->lga)
        ->orderBy('created_at', 'desc')
        ->get();
        return response()->json($beneficiaries);

    }

    public function getOneBeneficiary(Request $request): JsonResponse
    {
        // Check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Validate search parameter
        $searchParam = $request->query('param');
        if (!$searchParam) {
            return response()->json(['message' => 'Search parameter is required'], 400);
        }

        // Check if user has staff relationship and LGA
        if (!$user->staff || !$user->staff->lga) {
            return response()->json(['message' => 'User LGA not configured'], 403);
        }

        // Query beneficiary with LGA restriction and search parameter
        $beneficiary = Beneficiary::with(['enrolled_by', 'beneficiary_type', 'lga_info', 'cadre_info', 'ministry_info', 'beneficiary_image', 'cadre_info', 'beneficiary_type'])
            ->where('lga', $user->staff->lga)
            ->where(function ($query) use ($searchParam) {
                $query->where('beneficiaryId', $searchParam)
                      ->orWhere('phoneNumber', $searchParam)
                      ->orWhere('employeeId', $searchParam)
                      ->orWhere('email', $searchParam);
            })
            ->first();

        // Return 404 if no beneficiary is found
        if (!$beneficiary) {
            return response()->json(['message' => 'No beneficiary found'], 404);
        }

        // Calculate total spent this week
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        
        $weeklyTotal = Transactions::where('beneficiary', $beneficiary->beneficiaryId)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->with('transaction_products')
            ->get()
            ->sum(function ($transaction) {
                return $transaction->transaction_products->sum(function ($product) {
                    $cost = $product->cost ? floatval($product->cost) : floatval($product->products->cost);
                    return $cost * intval($product->quantitySold);
                });
            });

        // Format response to match frontend expectations
        $response = [
            'userId' => $beneficiary->beneficiaryId,
            'email' => $beneficiary->email,
            'phoneNumber' => $beneficiary->phoneNumber,
            'employeeId' => $beneficiary->employeeId ?? $beneficiary->beneficiaryId,
            'firstName' => $beneficiary->firstName,
            'lastName' => $beneficiary->lastName,
            'cardNumber' => $beneficiary->cardNumber,
            'department' => $beneficiary->ministry_info?->name ?? null,
            'salary' => $beneficiary->cadre_info?->salary ?? null,
            'billingSetting' => $beneficiary->beneficiary_type?->billingSetting ?? null,
            'beneficiaryType' => $beneficiary->beneficiary_type?->typeName ?? null,
            'weeklyTotalSpent' => number_format($weeklyTotal, 2, '.', ''),
        ];

        return response()->json($response);
    }


    public function beneficiaryTypes()
    {
        $beneficiaryTypes = BeneficiaryType::all();
        return response()->json($beneficiaryTypes);
    }

   public function store(Request $request)
{
    // Validate the request data
    $validatedData = $request->validate([
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'otherNames' => 'nullable|string|max:255',
        'phoneNumber' => 'nullable|string|max:11|unique:beneficiaries,phoneNumber',
        'email' => 'nullable|email|max:255|unique:beneficiaries,email',
        'beneficiaryType' => 'required|integer|exists:beneficiary_type,typeId',
        'ministry' => 'nullable|integer|exists:ministries,ministryId',
        'cardNumber' => 'nullable|string|max:255',
        'cadre' => 'nullable|integer|exists:cadres,cadreId',
        'employeeId' => 'nullable|string|max:255|unique:beneficiaries,employeeId',
        'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Adjust validation rules as needed

    ]);

    // Get the authenticated staff
    $user = auth()->user();

    // Ensure the user is authenticated and has an LGA
    if (!$user || !isset($user->staff->lga)) {
        return response()->json(['message' => 'Authenticated staff or LGA not found'], 403);
    }

    // Prepare the data for creation
    $data = array_merge($validatedData, [
        'enrolledBy' => $user->id,
        'lga' => $user->staff->lga, // Assign the staff's LGA to the beneficiary
    ]);

    
    // Create the beneficiary
    $beneficiary = Beneficiary::create($data);
    // $beneficiary->load(['beneficiary_type', 'enrolled_by', 'lga_info']);

    $imagePath = $request->file('image')->store('beneficiary_images', 'public');
        
   // Full path in storage
    $source = storage_path('app/public/' . $imagePath);

    // Destination path in public/storage
    $destination = public_path('storage/' . $imagePath);

    // Ensure destination folder exists
    File::ensureDirectoryExists(dirname($destination));

    // Copy the file from storage to public
    File::copy($source, $destination);


        // Assuming you have a ProductImage model to handle product images
        $beneficiary->beneficiary_image()->create([
            'imagePath' => $imagePath,
            'beneficiaryId' => $beneficiary->beneficiaryId, 
        ]);
        
    $beneficiary->load(['beneficiary_type', 'enrolled_by', 'lga_info', 'cadre_info', 'ministry_info', 'beneficiary_image']);
    return response()->json([
        'beneficiaryId' => $beneficiary->beneficiaryId,
        'employeeId' => $beneficiary->employeeId,
        'firstName' => $beneficiary->firstName,
        'lastName' => $beneficiary->lastName,
        'otherNames' => $beneficiary->otherNames,
        'phoneNumber' => $beneficiary->phoneNumber,
        'email' => $beneficiary->email,
        'lga' => $beneficiary->lga_info ? $beneficiary->lga_info->lgaName : null,
        'ministry' => $beneficiary->ministry_info ? $beneficiary->ministry_info->ministryName : null,
        'cardNumber' => $beneficiary->cardNumber,
        'cadre' => $beneficiary->cadre_info ? $beneficiary->cadre_info->cadreName : null,
        'beneficiaryType' => $beneficiary->beneficiary_type->typeName,
        'enrolledBy' => $beneficiary->enrolled_by->firstName . ' ' . $beneficiary->enrolled_by->lastName,
    ], 201);
}

// public function show($beneficiaryId)
// {
//     $beneficiary = Beneficiary::find($beneficiaryId);
//     if (!$beneficiary) {
//         return response()->json(['message' => 'Beneficiary not found'], 404);
//     }
//     $beneficiary->load(['beneficiary_type', 'enrolled_by', 'lga_info']);
//     return response()->json($beneficiary);

//     $beneficiary->load(['beneficiary_type', 'enrolled_by', 'lga_info']);
// }



public function update(Request $request, $beneficiaryId)
{
    $beneficiary = Beneficiary::find($beneficiaryId);
    if (!$beneficiary) {
        return response()->json(['message' => 'Beneficiary not found'], 404);
    }

    // Validate the request data
    $validatedData = $request->validate([
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'otherNames' => 'nullable|string|max:255',
        'phoneNumber' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255|unique:beneficiaries,email,' . $beneficiary->beneficiaryId,
        'beneficiaryType' => 'required|integer|exists:beneficiary_type,typeId',
        'ministry' => 'nullable|integer|exists:ministries,ministryId',
        'cadre' => 'nullable|integer|exists:cadres,cadreId',
        'employeeId' => 'nullable|string|max:255',
    ]);

    // Update the beneficiary
    $beneficiary->update($validatedData);
    $beneficiary->load(['beneficiary_type', 'enrolled_by', 'lga_info', 'cadre_info', 'ministry_info']);

    return response()->json([
        'message' => "Beneficiary successfully updated",
        'beneficiaryId' => $beneficiary->beneficiaryId,
        'employeeId' => $beneficiary->employeeId,
        'firstName' => $beneficiary->firstName,
        'lastName' => $beneficiary->lastName,
        'otherNames' => $beneficiary->otherNames,
        'phoneNumber' => $beneficiary->phoneNumber,
        'email' => $beneficiary->email,
        'lga' => $beneficiary->lga_info ? $beneficiary->lga_info->lgaName : null,
        'ministry' => $beneficiary->ministry_info ? $beneficiary->ministry_info->ministryName : null,
        'cadre' => $beneficiary->cadre_info ? $beneficiary->cadre_info->cadreName : null,
        'beneficiaryType' => $beneficiary->beneficiary_type->typeName,
        'enrolledBy' => $beneficiary->enrolled_by->firstName . ' ' . $beneficiary->enrolled_by->lastName,
    ], 200);
}

public function destroy($beneficiaryId)
{
    $beneficiary = Beneficiary::find($beneficiaryId);
    if (!$beneficiary) {
        return response()->json(['message' => 'Beneficiary not found'], 404);
    }

    // Soft delete the beneficiary
    $beneficiary->delete();

    return response()->json(['message' => 'Beneficiary deleted successfully'], 200);
}
}
