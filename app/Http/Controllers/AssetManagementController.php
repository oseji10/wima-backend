<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use App\Models\Equipment;
use App\Models\EquipmentLifecycleEvent;
use App\Models\EquipmentMovement;
use App\Models\EquipmentUtilizationLog;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceIncident;
use App\Models\ComplianceLog;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;

class AssetManagementController extends Controller
{
    /* =======================================================================
     |  Role scoping helpers
     |  Roles: 1 = Admin, 3 = National Coordinator, 4 = State Coordinator,
     |         5 = Community Lead
     * ===================================================================== */

    private function scopedEquipmentQuery()
    {
        $user = auth()->user();
        $query = Equipment::query();

        if ($user->user_role === 4) { // State Coordinator
            $sc = StateCoordinators::where('userId', $user->id)->first();
            $stateId = $sc->stateId ?? null;
            $query->whereHas('hub', fn ($q) => $q->where('state', $stateId));
        } elseif ($user->user_role === 5) { // Community Lead
            $cl = CommunityLead::where('userId', $user->id)->first();
            $lga = $cl->lga ?? null;
            $query->whereHas('hub', fn ($q) => $q->where('lga', $lga));
        }
        // Admin (1) & National Coordinator (3): no restriction

        return $query;
    }

    private function scopedEquipmentIds(): array
    {
        return $this->scopedEquipmentQuery()->pluck('equipmentId')->all();
    }

    private function assertCanAccess($equipmentId): void
    {
        $allowed = in_array((int) $equipmentId, array_map('intval', $this->scopedEquipmentIds()), true);
        abort_unless($allowed, 403, 'You do not have access to this asset.');
    }

    /* =======================================================================
     |  Fleet dashboard + service alerts
     * ===================================================================== */

    public function fleetDashboard(Request $request)
    {
        $ids = $this->scopedEquipmentIds();

        $statusBreakdown = Equipment::whereIn('equipmentId', $ids)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalValue = Equipment::whereIn('equipmentId', $ids)->sum('value');

        $openIncidents = MaintenanceIncident::whereIn('equipmentId', $ids)
            ->whereNotIn('status', ['resolved', 'closed'])->count();

        $overdueMaintenance = MaintenanceSchedule::whereIn('equipmentId', $ids)
            ->where('status', 'active')
            ->whereDate('next_due_at', '<', Carbon::today())->count();

        $dueSoonMaintenance = MaintenanceSchedule::whereIn('equipmentId', $ids)
            ->where('status', 'active')
            ->whereBetween('next_due_at', [Carbon::today(), Carbon::today()->addDays(7)])->count();

        $expiringCompliance = ComplianceLog::whereIn('equipmentId', $ids)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [Carbon::today()->subYear(), Carbon::today()->addDays(30)])
            ->count();

        // Fleet uptime over the trailing 30 days
        $util = EquipmentUtilizationLog::whereIn('equipmentId', $ids)
            ->whereDate('log_date', '>=', Carbon::today()->subDays(30))
            ->selectRaw('SUM(hours_used) as used, SUM(hours_available) as available, SUM(downtime_hours) as downtime')
            ->first();

        $available = (float) ($util->available ?? 0);
        $uptimePct = $available > 0
            ? round((($available - (float) $util->downtime) / $available) * 100, 1) : 0;
        $utilizationPct = $available > 0
            ? round(((float) $util->used / $available) * 100, 1) : 0;

        return response()->json([
            'total_assets'        => count($ids),
            'total_value'         => round((float) $totalValue, 2),
            'status_breakdown'    => $statusBreakdown,
            'open_incidents'      => $openIncidents,
            'overdue_maintenance' => $overdueMaintenance,
            'due_soon_maintenance'=> $dueSoonMaintenance,
            'expiring_compliance' => $expiringCompliance,
            'fleet_uptime_pct'    => $uptimePct,
            'fleet_utilization_pct' => $utilizationPct,
        ]);
    }

    public function serviceAlerts(Request $request)
    {
        $ids = $this->scopedEquipmentIds();
        $alerts = [];

        $schedules = MaintenanceSchedule::with('equipment:equipmentId,equipmentName')
            ->whereIn('equipmentId', $ids)
            ->where('status', 'active')
            ->whereDate('next_due_at', '<=', Carbon::today()->addDays(7))
            ->get();

        foreach ($schedules as $s) {
            $days = Carbon::today()->diffInDays($s->next_due_at, false);
            $alerts[] = [
                'category'    => 'maintenance',
                'severity'    => $days < 0 ? 'overdue' : 'due_soon',
                'equipmentId' => $s->equipmentId,
                'asset'       => $s->equipment->equipmentName ?? 'Asset',
                'title'       => $s->title,
                'detail'      => $days < 0
                    ? abs($days) . ' day(s) overdue'
                    : 'Due in ' . $days . ' day(s)',
                'date'        => $s->next_due_at?->toDateString(),
                'ref_id'      => $s->id,
            ];
        }

        $compliance = ComplianceLog::with('equipment:equipmentId,equipmentName')
            ->whereIn('equipmentId', $ids)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', Carbon::today()->addDays(30))
            ->get();

        foreach ($compliance as $c) {
            $days = Carbon::today()->diffInDays($c->expires_at, false);
            $alerts[] = [
                'category'    => 'compliance',
                'severity'    => $days < 0 ? 'expired' : 'due_soon',
                'equipmentId' => $c->equipmentId,
                'asset'       => $c->equipment->equipmentName ?? 'Asset',
                'title'       => $c->title,
                'detail'      => $days < 0
                    ? 'Expired ' . abs($days) . ' day(s) ago'
                    : 'Expires in ' . $days . ' day(s)',
                'date'        => $c->expires_at?->toDateString(),
                'ref_id'      => $c->id,
            ];
        }

        $incidents = MaintenanceIncident::with('equipment:equipmentId,equipmentName')
            ->whereIn('equipmentId', $ids)
            ->whereIn('status', ['open', 'acknowledged', 'in_progress'])
            ->get();

        foreach ($incidents as $i) {
            $alerts[] = [
                'category'    => 'incident',
                'severity'    => $i->severity === 'critical' ? 'critical' : 'open',
                'equipmentId' => $i->equipmentId,
                'asset'       => $i->equipment->equipmentName ?? 'Asset',
                'title'       => $i->title,
                'detail'      => ucfirst($i->type) . ' · ' . ucfirst(str_replace('_', ' ', $i->status)),
                'date'        => $i->reported_at?->toDateString(),
                'ref_id'      => $i->id,
            ];
        }

        // Surface the most urgent first
        $order = ['expired' => 0, 'overdue' => 0, 'critical' => 1, 'open' => 2, 'due_soon' => 3];
        usort($alerts, fn ($a, $b) => ($order[$a['severity']] ?? 9) <=> ($order[$b['severity']] ?? 9));

        return response()->json($alerts);
    }

    public function assetOverview($equipmentId)
    {
        $this->assertCanAccess($equipmentId);

        $equipment = Equipment::with('category', 'hub.states', 'hub.lgas', 'owner')
            ->findOrFail($equipmentId);

        $latestMovement = EquipmentMovement::where('equipmentId', $equipmentId)
            ->orderByDesc('movement_date')->first();

        $nextMaintenance = MaintenanceSchedule::where('equipmentId', $equipmentId)
            ->where('status', 'active')->orderBy('next_due_at')->first();

        $openIncidents = MaintenanceIncident::where('equipmentId', $equipmentId)
            ->whereNotIn('status', ['resolved', 'closed'])->count();

        $util = EquipmentUtilizationLog::where('equipmentId', $equipmentId)
            ->whereDate('log_date', '>=', Carbon::today()->subDays(30))
            ->selectRaw('SUM(hours_used) as used, SUM(hours_available) as available, SUM(downtime_hours) as downtime')
            ->first();
        $available = (float) ($util->available ?? 0);

        return response()->json([
            'equipment'        => $equipment,
            'current_location' => $latestMovement->to_location ?? $equipment->exact_location,
            'next_maintenance' => $nextMaintenance,
            'open_incidents'   => $openIncidents,
            'uptime_30d_pct'   => $available > 0
                ? round((($available - (float) $util->downtime) / $available) * 100, 1) : null,
            'utilization_30d_pct' => $available > 0
                ? round(((float) $util->used / $available) * 100, 1) : null,
        ]);
    }

    /* =======================================================================
     |  Lifecycle
     * ===================================================================== */

    public function lifecycleIndex($equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        return response()->json(
            EquipmentLifecycleEvent::with('performer:id,firstName')
                ->where('equipmentId', $equipmentId)
                ->orderByDesc('event_date')->orderByDesc('id')->get()
        );
    }

    public function lifecycleStore(Request $request, $equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        $data = $request->validate([
            'event_type'  => 'required|string|max:40',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date'  => 'required|date',
            'meta'        => 'nullable|array',
        ]);
        $data['equipmentId'] = $equipmentId;
        $data['performed_by'] = Auth::id();

        $event = EquipmentLifecycleEvent::create($data);
        return response()->json($event->load('performer:id,firstName'), 201);
    }

    /* =======================================================================
     |  Movements / deployment logs
     * ===================================================================== */

    public function movementIndex($equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        return response()->json(
            EquipmentMovement::with([
                'fromHub.lgas', 'toHub.lgas', 'dispatcher:id,firstName', 'receiver:id,firstName',
            ])->where('equipmentId', $equipmentId)
              ->orderByDesc('movement_date')->orderByDesc('id')->get()
        );
    }

    public function movementStore(Request $request, $equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        $data = $request->validate([
            'to_hub'               => 'nullable|integer',
            'from_hub'             => 'nullable|integer',
            'from_location'        => 'nullable|string|max:255',
            'to_location'          => 'nullable|string|max:255',
            'movement_type'        => 'required|string|max:30',
            'reason'               => 'nullable|string|max:255',
            'movement_date'        => 'required|date',
            'expected_return_date' => 'nullable|date',
            'status'               => 'nullable|string|max:20',
            'notes'                => 'nullable|string',
        ]);

        $equipment = Equipment::findOrFail($equipmentId);
        $data['equipmentId']   = $equipmentId;
        $data['from_hub']      = $data['from_hub'] ?? $equipment->hub;
        $data['from_location'] = $data['from_location'] ?? $equipment->exact_location;
        $data['dispatched_by'] = Auth::id();
        $data['status']        = $data['status'] ?? 'in_transit';

        $movement = EquipmentMovement::create($data);

        // For completed transfers, update the asset's current hub/location immediately
        if (in_array($movement->status, ['completed', 'deployed', 'returned'])) {
            if ($movement->to_hub) {
                $equipment->hub = $movement->to_hub;
            }
            if ($movement->to_location) {
                $equipment->exact_location = $movement->to_location;
            }
            $equipment->save();
        }

        EquipmentLifecycleEvent::create([
            'equipmentId'  => $equipmentId,
            'event_type'   => $movement->movement_type === 'deployment' ? 'deployed' : 'transferred',
            'title'        => ucfirst($movement->movement_type) . ($movement->to_location ? ' to ' . $movement->to_location : ''),
            'description'  => $movement->reason,
            'event_date'   => $movement->movement_date,
            'meta'         => ['movement_id' => $movement->id, 'to_hub' => $movement->to_hub],
            'performed_by' => Auth::id(),
        ]);

        return response()->json(
            $movement->load(['fromHub.lgas', 'toHub.lgas', 'dispatcher:id,firstName', 'receiver:id,firstName']),
            201
        );
    }

    public function movementReceive(Request $request, $id)
    {
        $movement = EquipmentMovement::findOrFail($id);
        $this->assertCanAccess($movement->equipmentId);

        $data = $request->validate([
            'status'        => 'required|string|max:20',
            'to_location'   => 'nullable|string|max:255',
        ]);

        $movement->status      = $data['status'];
        $movement->received_by = Auth::id();
        $movement->received_at = now();
        if (!empty($data['to_location'])) {
            $movement->to_location = $data['to_location'];
        }
        $movement->save();

        if (in_array($movement->status, ['completed', 'deployed', 'returned'])) {
            $equipment = Equipment::find($movement->equipmentId);
            if ($equipment) {
                if ($movement->to_hub) {
                    $equipment->hub = $movement->to_hub;
                }
                if ($movement->to_location) {
                    $equipment->exact_location = $movement->to_location;
                }
                $equipment->save();
            }
        }

        return response()->json($movement->fresh(['fromHub.lgas', 'toHub.lgas', 'receiver:id,firstName']));
    }

    /* =======================================================================
     |  Utilization & uptime
     * ===================================================================== */

    public function utilizationIndex(Request $request, $equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        $from = $request->query('from');
        $to   = $request->query('to');

        $logs = EquipmentUtilizationLog::where('equipmentId', $equipmentId)
            ->when($from, fn ($q) => $q->whereDate('log_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('log_date', '<=', $to))
            ->orderBy('log_date')
            ->get();

        $totUsed = $logs->sum('hours_used');
        $totAvail = $logs->sum('hours_available');
        $totDown = $logs->sum('downtime_hours');

        return response()->json([
            'logs'    => $logs,
            'summary' => [
                'total_hours_used'      => round($totUsed, 2),
                'total_hours_available' => round($totAvail, 2),
                'total_downtime_hours'  => round($totDown, 2),
                'utilization_pct'       => $totAvail > 0 ? round(($totUsed / $totAvail) * 100, 1) : 0,
                'uptime_pct'            => $totAvail > 0 ? round((($totAvail - $totDown) / $totAvail) * 100, 1) : 0,
            ],
        ]);
    }

    public function utilizationStore(Request $request, $equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        $data = $request->validate([
            'log_date'          => 'required|date',
            'hours_used'        => 'required|numeric|min:0',
            'hours_available'   => 'required|numeric|min:0',
            'downtime_hours'    => 'nullable|numeric|min:0',
            'output_units'      => 'nullable|numeric|min:0',
            'output_unit_label' => 'nullable|string|max:40',
            'notes'             => 'nullable|string',
        ]);
        $data['equipmentId'] = $equipmentId;
        $data['recorded_by'] = Auth::id();
        $data['downtime_hours'] = $data['downtime_hours'] ?? 0;

        // One log per asset per day — update if it already exists
        $log = EquipmentUtilizationLog::updateOrCreate(
            ['equipmentId' => $equipmentId, 'log_date' => $data['log_date']],
            $data
        );

        return response()->json($log, 201);
    }

    /* =======================================================================
     |  Maintenance schedules (preventive)
     * ===================================================================== */

    public function scheduleIndex($equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        return response()->json(
            MaintenanceSchedule::with('assignee:id,firstName')
                ->where('equipmentId', $equipmentId)
                ->orderBy('next_due_at')->get()
        );
    }

    public function scheduleStore(Request $request, $equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'maintenance_type' => 'required|string|max:30',
            'frequency_type'   => 'required|string|max:20',
            'frequency_value'  => 'required|integer|min:1',
            'last_serviced_at' => 'nullable|date',
            'next_due_at'      => 'required|date',
            'assigned_to'      => 'nullable|integer',
            'instructions'     => 'nullable|string',
            'status'           => 'nullable|string|max:20',
        ]);
        $data['equipmentId'] = $equipmentId;
        $data['status'] = $data['status'] ?? 'active';

        $schedule = MaintenanceSchedule::create($data);
        return response()->json($schedule->load('assignee:id,firstName'), 201);
    }

    public function scheduleUpdate(Request $request, $id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);
        $this->assertCanAccess($schedule->equipmentId);

        $data = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'maintenance_type' => 'sometimes|string|max:30',
            'frequency_type'   => 'sometimes|string|max:20',
            'frequency_value'  => 'sometimes|integer|min:1',
            'last_serviced_at' => 'nullable|date',
            'next_due_at'      => 'sometimes|date',
            'assigned_to'      => 'nullable|integer',
            'instructions'     => 'nullable|string',
            'status'           => 'sometimes|string|max:20',
        ]);

        $schedule->update($data);
        return response()->json($schedule->fresh('assignee:id,firstName'));
    }

    public function scheduleDestroy($id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);
        $this->assertCanAccess($schedule->equipmentId);
        $schedule->delete();
        return response()->json(['message' => 'Schedule removed']);
    }

    /**
     * Mark a scheduled maintenance as done: stamp the service date, roll the
     * next due date forward by the frequency, and log a lifecycle event.
     */
    public function scheduleMarkServiced(Request $request, $id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);
        $this->assertCanAccess($schedule->equipmentId);

        $data = $request->validate([
            'serviced_at' => 'nullable|date',
            'notes'       => 'nullable|string',
        ]);

        $servicedAt = Carbon::parse($data['serviced_at'] ?? now());
        $schedule->last_serviced_at = $servicedAt->toDateString();
        $schedule->next_due_at = $schedule->computeNextDue($servicedAt)->toDateString();
        $schedule->save();

        EquipmentLifecycleEvent::create([
            'equipmentId'  => $schedule->equipmentId,
            'event_type'   => 'maintenance',
            'title'        => 'Serviced: ' . $schedule->title,
            'description'  => $data['notes'] ?? null,
            'event_date'   => $servicedAt->toDateString(),
            'meta'         => ['schedule_id' => $schedule->id],
            'performed_by' => Auth::id(),
        ]);

        return response()->json($schedule->fresh('assignee:id,firstName'));
    }

    /* =======================================================================
     |  Incidents / breakdowns
     * ===================================================================== */

    public function incidentIndex(Request $request)
    {
        $ids = $this->scopedEquipmentIds();

        $query = MaintenanceIncident::with([
            'equipment:equipmentId,equipmentName,serialNumber',
            'reporter:id,firstName', 'assignee:id,firstName',
        ])->whereIn('equipmentId', $ids);

        if ($eq = $request->query('equipmentId')) {
            $query->where('equipmentId', $eq);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        return response()->json(
            $query->orderByDesc('reported_at')->paginate($request->query('per_page', 15))
        );
    }

    public function incidentStore(Request $request, $equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        $data = $request->validate([
            'type'        => 'required|string|max:20',
            'severity'    => 'required|string|max:12',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'reported_at' => 'nullable|date',
            'downtime_hours' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|integer',
        ]);

        $data['equipmentId'] = $equipmentId;
        $data['reported_by'] = Auth::id();
        $data['reported_at'] = $data['reported_at'] ?? now();
        $data['status']      = 'open';
        $data['reference']   = 'INC-' . now()->format('ymd') . '-' .
            str_pad((string) (MaintenanceIncident::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);

        $incident = MaintenanceIncident::create($data);

        // A breakdown usually flips the asset out of service
        if (in_array($data['severity'], ['high', 'critical'])) {
            Equipment::where('equipmentId', $equipmentId)->update(['status' => 'Under Maintenance']);
        }

        EquipmentLifecycleEvent::create([
            'equipmentId'  => $equipmentId,
            'event_type'   => 'maintenance',
            'title'        => ucfirst($data['type']) . ' reported: ' . $data['title'],
            'description'  => $data['description'] ?? null,
            'event_date'   => Carbon::parse($data['reported_at'])->toDateString(),
            'meta'         => ['incident_reference' => $incident->reference],
            'performed_by' => Auth::id(),
        ]);

        return response()->json($incident->load('reporter:id,firstName', 'assignee:id,firstName'), 201);
    }

    public function incidentUpdate(Request $request, $id)
    {
        $incident = MaintenanceIncident::findOrFail($id);
        $this->assertCanAccess($incident->equipmentId);

        $data = $request->validate([
            'status'         => 'sometimes|string|max:20',
            'severity'       => 'sometimes|string|max:12',
            'assigned_to'    => 'nullable|integer',
            'downtime_hours' => 'nullable|numeric|min:0',
            'cost'           => 'nullable|numeric|min:0',
            'resolution'     => 'nullable|string',
        ]);

        if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'])
            && !$incident->resolved_at) {
            $incident->resolved_at = now();

            // Bring the asset back into service if nothing else keeps it down
            $stillOpen = MaintenanceIncident::where('equipmentId', $incident->equipmentId)
                ->where('id', '!=', $incident->id)
                ->whereNotIn('status', ['resolved', 'closed'])->exists();
            if (!$stillOpen) {
                Equipment::where('equipmentId', $incident->equipmentId)
                    ->update(['status' => 'Active']);
            }

            EquipmentLifecycleEvent::create([
                'equipmentId'  => $incident->equipmentId,
                'event_type'   => 'repaired',
                'title'        => 'Resolved: ' . $incident->title,
                'description'  => $data['resolution'] ?? null,
                'event_date'   => now()->toDateString(),
                'meta'         => ['incident_reference' => $incident->reference],
                'performed_by' => Auth::id(),
            ]);
        }

        $incident->update($data);
        return response()->json($incident->fresh('reporter:id,firstName', 'assignee:id,firstName'));
    }

    /* =======================================================================
     |  Compliance & certification logs
     * ===================================================================== */

    public function complianceIndex($equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        return response()->json(
            ComplianceLog::with('recorder:id,firstName')
                ->where('equipmentId', $equipmentId)
                ->orderBy('expires_at')->get()
        );
    }

    public function complianceStore(Request $request, $equipmentId)
    {
        $this->assertCanAccess($equipmentId);
        $data = $request->validate([
            'log_type'     => 'required|string|max:30',
            'title'        => 'required|string|max:255',
            'status'       => 'nullable|string|max:20',
            'issued_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date',
            'authority'    => 'nullable|string|max:255',
            'document_ref' => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);
        $data['equipmentId'] = $equipmentId;
        $data['recorded_by'] = Auth::id();
        $data['status'] = $data['status'] ?? 'compliant';

        $log = ComplianceLog::create($data);
        return response()->json($log->load('recorder:id,firstName'), 201);
    }

    public function complianceUpdate(Request $request, $id)
    {
        $log = ComplianceLog::findOrFail($id);
        $this->assertCanAccess($log->equipmentId);

        $data = $request->validate([
            'log_type'     => 'sometimes|string|max:30',
            'title'        => 'sometimes|string|max:255',
            'status'       => 'sometimes|string|max:20',
            'issued_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date',
            'authority'    => 'nullable|string|max:255',
            'document_ref' => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);

        $log->update($data);
        return response()->json($log->fresh('recorder:id,firstName'));
    }
}