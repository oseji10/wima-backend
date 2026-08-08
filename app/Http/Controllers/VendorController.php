<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::where('is_active', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('account_name', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('paginate')) {
            return response()->json($query->orderBy('name')->paginate($request->query('per_page', 10)));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        return response()->json(['data' => $vendor->loadCount('requests')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $vendor = Vendor::create($data);
        return response()->json(['message' => 'Vendor added.', 'data' => $vendor], 201);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $data = $this->validated($request, $vendor->id);
        $vendor->update($data);
        return response()->json(['message' => 'Vendor updated.', 'data' => $vendor]);
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        // Soft-disable rather than delete — historical requests still reference this vendor.
        $vendor->update(['is_active' => false]);
        return response()->json(['message' => 'Vendor deactivated.']);
    }

    protected function validated(Request $request, ?int $vendorId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:30'],
            'bank_sort_code' => ['nullable', 'string', 'max:20'],
            'tin' => ['nullable', 'string', 'max:30'],
        ]);
    }
}