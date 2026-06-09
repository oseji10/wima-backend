<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    public $table = 'equipment';

    protected $fillable = [
        'equipmentId',
        'equipmentName',
        'serialNumber',
        'value',
        'equipmentCategory',
        'hub',
        'owner',
        'exact_location',
        'status',
    ];

    protected $primaryKey = 'equipmentId';

    public function category()
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipmentCategory', 'categoryId');
    }

    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    public function hubs()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner', 'id');
    }

    // ---- New asset-management relationships ----

    public function lifecycleEvents()
    {
        return $this->hasMany(EquipmentLifecycleEvent::class, 'equipmentId', 'equipmentId')
            ->orderByDesc('event_date');
    }

    public function movements()
    {
        return $this->hasMany(EquipmentMovement::class, 'equipmentId', 'equipmentId')
            ->orderByDesc('movement_date');
    }

    public function utilizationLogs()
    {
        return $this->hasMany(EquipmentUtilizationLog::class, 'equipmentId', 'equipmentId')
            ->orderByDesc('log_date');
    }

    public function maintenanceSchedules()
    {
        return $this->hasMany(MaintenanceSchedule::class, 'equipmentId', 'equipmentId')
            ->orderBy('next_due_at');
    }

    public function incidents()
    {
        return $this->hasMany(MaintenanceIncident::class, 'equipmentId', 'equipmentId')
            ->orderByDesc('reported_at');
    }

    public function complianceLogs()
    {
        return $this->hasMany(ComplianceLog::class, 'equipmentId', 'equipmentId')
            ->orderBy('expires_at');
    }
}