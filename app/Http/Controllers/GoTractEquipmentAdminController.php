<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoTractCooperative;
use App\Models\GoTractEquipment;
use App\Models\GoTractIndividualLoan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GoTractEquipmentAdminController extends Controller
{
    /* ----------------------------- Catalog ------------------------------ */

    public function indexEquipment(): JsonResponse
    {
        return response()->json(['data' => GoTractEquipment::orderBy('type')->orderBy('name')->get()]);
    }

    public function storeEquipment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'type'            => ['required', Rule::in(['individual', 'group'])],
            'group_size'      => ['nullable', 'integer', 'min:2', 'max:100'],
            'total_quantity'  => ['required', 'integer', 'min:0'],
            'unit'            => ['nullable', 'string', 'max:50'],
        ]);

        $equipment = GoTractEquipment::create([
            ...$data,
            'group_size'         => $data['type'] === 'group' ? ($data['group_size'] ?? 10) : null,
            'available_quantity' => $data['total_quantity'],
            'unit'               => $data['unit'] ?? 'unit',
            'is_active'          => true,
        ]);

        return response()->json(['message' => 'Equipment added.', 'data' => $equipment], 201);
    }

    public function updateEquipment(Request $request, GoTractEquipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'group_size'      => ['nullable', 'integer', 'min:2', 'max:100'],
            'total_quantity'  => ['sometimes', 'integer', 'min:0'],
            'unit'            => ['nullable', 'string', 'max:50'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        // If total_quantity changes, shift available_quantity by the same delta
        // so already-approved loans aren't double-counted.
        if (array_key_exists('total_quantity', $data)) {
            $delta = $data['total_quantity'] - $equipment->total_quantity;
            $data['available_quantity'] = max(0, $equipment->available_quantity + $delta);
        }

        $equipment->update($data);

        return response()->json(['message' => 'Equipment updated.', 'data' => $equipment->fresh()]);
    }

    /* ------------------------------ Loans -------------------------------- */

    public function indexIndividualLoans(Request $request): JsonResponse
    {
        $loans = GoTractIndividualLoan::with(['application', 'equipment'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('equipment_id'), fn ($q) => $q->where('equipment_id', $request->input('equipment_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($loans);
    }

    public function indexCooperatives(Request $request): JsonResponse
    {
        $coops = GoTractCooperative::with(['requests.equipment', 'lead', 'members.application'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('equipment_id'), fn ($q) => $q->where('equipment_id', $request->input('equipment_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($coops);
    }

    /**
     * Approve/reject/collect/return an individual loan. Stock is decremented
     * on approval and restored on rejection/return.
     */
    public function updateIndividualLoan(Request $request, GoTractIndividualLoan $loan): JsonResponse
    {
        $data = $request->validate(['action' => [Rule::in(['approve', 'reject', 'collect', 'return'])]]);

        DB::transaction(function () use ($loan, $data) {
            $equipment = $loan->equipment()->lockForUpdate()->first();

            match ($data['action']) {
                'approve' => $this->approveIndividual($loan, $equipment),
                'reject'  => $loan->update(['status' => 'rejected']),
                'collect' => $loan->update(['status' => 'collected', 'collected_at' => now()]),
                'return'  => $this->returnIndividual($loan, $equipment),
            };
        });

        return response()->json(['message' => 'Loan updated.', 'data' => $loan->fresh(['application', 'equipment'])]);
    }

    protected function approveIndividual(GoTractIndividualLoan $loan, GoTractEquipment $equipment): void
    {
        abort_if($equipment->available_quantity < 1, 422, 'No stock available for this item.');
        $equipment->decrement('available_quantity');
        $loan->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => optional(Auth::user())->id]);
    }

    protected function returnIndividual(GoTractIndividualLoan $loan, GoTractEquipment $equipment): void
    {
        $equipment->increment('available_quantity');
        $loan->update(['status' => 'returned', 'returned_at' => now()]);
    }

    /**
     * Approve/reject/collect/return a cooperative's group loan.
     */
    public function updateCooperative(Request $request, GoTractCooperative $cooperative): JsonResponse
    {
        $data = $request->validate(['action' => [Rule::in(['approve', 'reject', 'collect', 'return'])]]);

        if ($data['action'] === 'approve' && $cooperative->status !== 'requested') {
            return response()->json(['message' => 'This cooperative has not submitted a request yet.'], 422);
        }

        DB::transaction(function () use ($cooperative, $data) {
            $equipment = $cooperative->equipment()->lockForUpdate()->first();

            match ($data['action']) {
                'approve' => $this->approveCooperative($cooperative, $equipment),
                'reject'  => $cooperative->update(['status' => 'rejected']),
                'collect' => $cooperative->update(['status' => 'collected', 'collected_at' => now()]),
                'return'  => $this->returnCooperative($cooperative, $equipment),
            };
        });

        return response()->json(['message' => 'Cooperative updated.', 'data' => $cooperative->fresh(['equipment', 'lead'])]);
    }

    protected function approveCooperative(GoTractCooperative $cooperative, GoTractEquipment $equipment): void
    {
        abort_if($equipment->available_quantity < 1, 422, 'No stock available for this item.');
        $equipment->decrement('available_quantity');
        $cooperative->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => optional(Auth::user())->id]);
    }

    protected function returnCooperative(GoTractCooperative $cooperative, GoTractEquipment $equipment): void
    {
        $equipment->increment('available_quantity');
        $cooperative->update(['status' => 'returned', 'returned_at' => now()]);
    }

    /**
     * Counters for the admin equipment dashboard.
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => [
            'equipmentTypes'   => GoTractEquipment::count(),
            'individualPending'=> GoTractIndividualLoan::where('status', 'pending')->count(),
            'cooperativesReady'=> GoTractCooperative::where('status', 'ready')->count(),
            'cooperativesForming' => GoTractCooperative::where('status', 'forming')->count(),
        ]]);
    }
}