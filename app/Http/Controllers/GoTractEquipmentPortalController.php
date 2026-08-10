<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoTractApplication;
use App\Models\GoTractCooperative;
use App\Models\GoTractCooperativeMember;
use App\Models\GoTractCooperativeRequest;
use App\Models\GoTractEquipment;
use App\Models\GoTractIndividualLoan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoTractEquipmentPortalController extends Controller
{
    /**
     * Verify a participant is accredited before letting them into the portal.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'digits:11']]);

        $application = $this->resolve($request->input('phone'));

        if (! $application) {
            return response()->json([
                'verified' => false,
                'message'  => 'We could not find an accredited participant with that phone number.',
            ], 404);
        }

        return response()->json([
            'verified' => true,
            'data'     => $this->participantPayload($application),
        ]);
    }

    /**
     * Equipment catalog with live availability and purchase tracking.
     * Grouped by category for frontend display.
     */
    public function catalog(): JsonResponse
    {
        $items = GoTractEquipment::where('is_active', true)
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $items->map(fn ($e) => [
            'id'                => $e->id,
            'name'              => $e->name,
            'description'       => $e->description,
            'image_url'         => $e->image_url,
            'type'              => $e->type,
            'category'          => $e->category ?? 'Other', // Added category
            'groupSize'         => $e->group_size,
            'unit'              => $e->unit,
            'availableQuantity' => $e->available_quantity,
            'totalQuantity'     => $e->total_quantity,
            'totalPurchased'    => $e->total_quantity - $e->available_quantity,
        ])]);
    }

    /**
     * Everything this participant is currently involved in.
     */
    public function mine(Request $request): JsonResponse
    {
        $application = $this->resolveOrFail($request);

        $individualLoans = GoTractIndividualLoan::with('equipment')
            ->where('application_id', $application->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($l) => $this->individualLoanPayload($l));

        $membership = GoTractCooperativeMember::where('application_id', $application->id)
            ->whereHas('cooperative', fn ($q) => $q->whereIn('status', GoTractCooperative::ACTIVE_STATUSES))
            ->with('cooperative')
            ->first();

        $cooperative = null;
        $cooperativeRequests = [];

        if ($membership) {
            $coop = $membership->cooperative;
            $cooperative = $this->cooperativePayload($coop, $application);
            
            $cooperativeRequests = GoTractCooperativeRequest::with('equipment')
                ->where('cooperative_id', $coop->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($req) => [
                    'id' => $req->id,
                    'equipment_id' => $req->equipment_id,
                    'equipment' => $req->equipment->name,
                    'unit' => $req->equipment->unit,
                    'quantity' => $req->quantity,
                    'status' => $req->status,
                    'requestedAt' => optional($req->requested_at)->toIso8601String(),
                ]);
        }

        return response()->json(['data' => [
            'individualLoans' => $individualLoans,
            'cooperative' => $cooperative,
            'cooperativeRequests' => $cooperativeRequests,
        ]]);
    }

    /**
     * Request an individual-type equipment item with quantity.
     */
    public function requestIndividual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'        => ['required', 'digits:11'],
            'equipment_id' => ['required', 'integer', 'exists:gotract_equipment,id'],
            'quantity'     => ['sometimes', 'integer', 'min:1'],
        ]);

        $application = $this->resolve($data['phone']);
        if (! $application) {
            return response()->json(['message' => 'Participant not found or not accredited.'], 404);
        }

        $equipment = GoTractEquipment::findOrFail($data['equipment_id']);
        $quantity = $data['quantity'] ?? 1;

        if ($equipment->type !== 'individual') {
            return response()->json(['message' => 'This item requires a cooperative. Use the group flow.'], 422);
        }
        if (! $equipment->is_active) {
            return response()->json(['message' => 'This item is not currently available.'], 422);
        }
        if ($equipment->available_quantity < $quantity) {
            return response()->json(['message' => "Only {$equipment->available_quantity} units available."], 422);
        }

        $existing = GoTractIndividualLoan::where('application_id', $application->id)
            ->where('equipment_id', $equipment->id)
            ->whereIn('status', GoTractIndividualLoan::ACTIVE_STATUSES)
            ->first();
        if ($existing) {
            return response()->json(['message' => 'You already have an active request for this item.'], 409);
        }

        $loan = GoTractIndividualLoan::create([
            'application_id' => $application->id,
            'equipment_id'   => $equipment->id,
            'quantity'       => $quantity,
            'status'         => 'pending',
            'requested_at'   => now(),
        ]);

        return response()->json([
            'message' => "Request submitted for {$quantity} unit(s). You will be notified once it is approved.",
            'data'    => $this->individualLoanPayload($loan->fresh('equipment')),
        ], 201);
    }

    /**
     * Update an individual loan quantity.
     */
    public function updateIndividual(Request $request, GoTractIndividualLoan $loan): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! in_array($loan->status, ['pending'])) {
            return response()->json(['message' => 'Cannot modify this request in its current state.'], 422);
        }

        $equipment = $loan->equipment;
        $newQuantity = $data['quantity'];
        $oldQuantity = $loan->quantity;
        $additionalNeeded = max(0, $newQuantity - $oldQuantity);

        if ($equipment->available_quantity < $additionalNeeded) {
            return response()->json([
                'message' => "Only {$equipment->available_quantity} units available. You currently have {$oldQuantity}.",
            ], 422);
        }

        $loan->update(['quantity' => $newQuantity]);

        return response()->json([
            'message' => "Quantity updated to {$newQuantity} unit(s).",
            'data'    => $this->individualLoanPayload($loan->fresh('equipment')),
        ]);
    }

    /**
     * Cancel an individual loan.
     */
    public function cancelIndividual(GoTractIndividualLoan $loan): JsonResponse
    {
        if (! in_array($loan->status, ['pending'])) {
            return response()->json(['message' => 'Cannot cancel this request in its current state.'], 422);
        }

        $loan->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Request cancelled successfully.',
        ]);
    }

    /**
     * Create a cooperative (not tied to specific equipment).
     */
    public function cooperativeCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'digits:11'],
            'name'  => ['required', 'string', 'max:255'],
        ]);

        $application = $this->resolve($data['phone']);
        if (! $application) {
            return response()->json(['message' => 'Participant not found or not accredited.'], 404);
        }

        if ($this->hasActiveCooperative($application->id)) {
            return response()->json(['message' => 'You are already part of an active cooperative.'], 409);
        }

        $cooperative = DB::transaction(function () use ($application, $data) {
            $coop = GoTractCooperative::create([
                'name'                => $data['name'],
                'lead_application_id' => $application->id,
                'lga'                 => $application->lga,
                'required_size'       => 10,
                'status'              => 'forming',
            ]);

            GoTractCooperativeMember::create([
                'cooperative_id' => $coop->id,
                'application_id' => $application->id,
                'joined_at'      => now(),
            ]);

            return $coop;
        });

        return response()->json([
            'message' => "Cooperative '{$cooperative->name}' created! Share code {$cooperative->code} with your group.",
            'data'    => $this->cooperativePayload($cooperative, $application),
        ], 201);
    }

    /**
     * Join a cooperative using its share code.
     */
    public function cooperativeJoin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'digits:11'],
            'code'  => ['required', 'string'],
        ]);

        $application = $this->resolve($data['phone']);
        if (! $application) {
            return response()->json(['message' => 'Participant not found or not accredited.'], 404);
        }

        $coop = GoTractCooperative::where('code', strtoupper(trim($data['code'])))->first();
        if (! $coop) {
            return response()->json(['message' => 'Invalid cooperative code.'], 404);
        }
        if (! in_array($coop->status, ['forming', 'active'], true)) {
            return response()->json(['message' => 'This cooperative is no longer accepting members.'], 422);
        }

        if ($this->hasActiveCooperative($application->id)) {
            return response()->json(['message' => 'You are already part of an active cooperative.'], 409);
        }

        $count = $coop->members()->count();
        if ($count >= $coop->required_size) {
            return response()->json(['message' => 'This cooperative is already full.'], 422);
        }

        DB::transaction(function () use ($coop, $application) {
            GoTractCooperativeMember::create([
                'cooperative_id' => $coop->id,
                'application_id' => $application->id,
                'joined_at'      => now(),
            ]);

            $count = $coop->members()->count();
            if ($count >= $coop->required_size) {
                $coop->update(['status' => 'active']);
            }
        });

        return response()->json([
            'message' => "You have joined '{$coop->name}' cooperative.",
            'data'    => $this->cooperativePayload($coop->fresh(), $application),
        ]);
    }

    /**
     * Cooperative requests equipment - Available to leader at ANY time (even while forming).
     */
    public function cooperativeRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'          => ['required', 'digits:11'],
            'cooperative_id' => ['required', 'integer', 'exists:gotract_cooperatives,id'],
            'equipment_id'   => ['required', 'integer', 'exists:gotract_equipment,id'],
            'quantity'       => ['sometimes', 'integer', 'min:1'],
        ]);

        $application = $this->resolve($data['phone']);
        if (! $application) {
            return response()->json(['message' => 'Participant not found or not accredited.'], 404);
        }

        $cooperative = GoTractCooperative::findOrFail($data['cooperative_id']);
        
        if ($cooperative->lead_application_id !== $application->id) {
            return response()->json(['message' => 'Only the cooperative lead can request equipment.'], 403);
        }

        if (! in_array($cooperative->status, ['forming', 'active'])) {
            return response()->json(['message' => 'This cooperative is no longer active.'], 422);
        }

        $equipment = GoTractEquipment::findOrFail($data['equipment_id']);
        $quantity = $data['quantity'] ?? 1;

        if (! $equipment->is_active) {
            return response()->json(['message' => 'This equipment is not currently available.'], 422);
        }
        if ($equipment->available_quantity < $quantity) {
            return response()->json(['message' => "Only {$equipment->available_quantity} units available."], 422);
        }

        $request = GoTractCooperativeRequest::create([
            'cooperative_id' => $cooperative->id,
            'equipment_id'   => $equipment->id,
            'quantity'       => $quantity,
            'status'         => 'pending',
            'requested_at'   => now(),
        ]);

        return response()->json([
            'message' => "Request submitted for {$quantity} unit(s) of {$equipment->name}.",
            'data'    => [
                'id' => $request->id,
                'equipment' => $equipment->name,
                'unit' => $equipment->unit,
                'quantity' => $request->quantity,
                'status' => $request->status,
            ],
        ], 201);
    }

    /**
     * Update a cooperative request quantity.
     */
    public function updateCooperativeRequest(Request $request, GoTractCooperativeRequest $cooperativeRequest): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'digits:11'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $application = $this->resolve($data['phone']);
        if (! $application) {
            return response()->json(['message' => 'Participant not found or not accredited.'], 404);
        }

        $cooperative = $cooperativeRequest->cooperative;
        if ($cooperative->lead_application_id !== $application->id) {
            return response()->json(['message' => 'Only the cooperative lead can update requests.'], 403);
        }

        if ($cooperativeRequest->status !== 'pending') {
            return response()->json(['message' => 'Cannot modify this request in its current state.'], 422);
        }

        $equipment = $cooperativeRequest->equipment;
        $newQuantity = $data['quantity'];
        $oldQuantity = $cooperativeRequest->quantity;
        $additionalNeeded = max(0, $newQuantity - $oldQuantity);

        if ($equipment->available_quantity < $additionalNeeded) {
            return response()->json([
                'message' => "Only {$equipment->available_quantity} units available. You currently have {$oldQuantity}.",
            ], 422);
        }

        $cooperativeRequest->update(['quantity' => $newQuantity]);

        return response()->json([
            'message' => "Quantity updated to {$newQuantity} unit(s).",
            'data' => [
                'id' => $cooperativeRequest->id,
                'equipment' => $equipment->name,
                'unit' => $equipment->unit,
                'quantity' => $cooperativeRequest->quantity,
                'status' => $cooperativeRequest->status,
            ],
        ]);
    }

    /**
     * Cancel a cooperative request.
     */
    public function cancelCooperativeRequest(Request $request, GoTractCooperativeRequest $cooperativeRequest): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'digits:11'],
        ]);

        $application = $this->resolve($data['phone']);
        if (! $application) {
            return response()->json(['message' => 'Participant not found or not accredited.'], 404);
        }

        $cooperative = $cooperativeRequest->cooperative;
        if ($cooperative->lead_application_id !== $application->id) {
            return response()->json(['message' => 'Only the cooperative lead can cancel requests.'], 403);
        }

        if ($cooperativeRequest->status !== 'pending') {
            return response()->json(['message' => 'Cannot cancel this request in its current state.'], 422);
        }

        $cooperativeRequest->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Request cancelled successfully.',
        ]);
    }

    /* ----------------------------- Helpers ------------------------------ */

    protected function resolve(string $phone): ?GoTractApplication
    {
        return GoTractApplication::where('phone_number', $phone)
            ->whereNotNull('accredited_at')
            ->first();
    }

    protected function resolveOrFail(Request $request): GoTractApplication
    {
        $request->validate(['phone' => ['required', 'digits:11']]);
        $application = $this->resolve($request->input('phone'));
        abort_if(! $application, 404, 'Participant not found or not accredited.');
        return $application;
    }

    protected function hasActiveCooperative(int $applicationId): bool
    {
        return GoTractCooperativeMember::where('application_id', $applicationId)
            ->whereHas('cooperative', fn ($q) => $q->whereIn('status', GoTractCooperative::ACTIVE_STATUSES))
            ->exists();
    }

    protected function participantPayload(GoTractApplication $a): array
    {
        return [
            'id'          => $a->id,
            'referenceId' => $a->reference_id,
            'fullName'    => $a->full_name,
            'phoneNumber' => $a->phone_number,
            'lga'         => $a->lga,
        ];
    }

    protected function individualLoanPayload(GoTractIndividualLoan $l): array
    {
        return [
            'id'          => $l->id,
            'equipment'   => $l->equipment->name,
            'unit'        => $l->equipment->unit,
            'quantity'    => $l->quantity ?? 1,
            'status'      => $l->status,
            'requestedAt' => optional($l->requested_at)->toIso8601String(),
            'approvedAt'  => optional($l->approved_at)->toIso8601String(),
            'collectedAt' => optional($l->collected_at)->toIso8601String(),
            'returnedAt'  => optional($l->returned_at)->toIso8601String(),
        ];
    }

    protected function cooperativePayload(GoTractCooperative $c, GoTractApplication $viewer): array
    {
        $members = $c->members()->with('application')->get();

        return [
            'id'            => $c->id,
            'code'          => $c->code,
            'name'          => $c->name,
            'requiredSize'  => $c->required_size,
            'memberCount'   => $members->count(),
            'status'        => $c->status,
            'isLead'        => $c->lead_application_id === $viewer->id,
            'leadName'      => optional($c->lead)->full_name,
            'members'       => $members->map(fn ($m) => [
                'fullName' => optional($m->application)->full_name,
                'lga'      => optional($m->application)->lga,
            ])->values(),
        ];
    }
}