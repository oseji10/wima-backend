<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceIncident extends Model
{
    protected $table = 'maintenance_incidents';

    protected $fillable = [
        'equipmentId',
        'reference',
        'type',
        'severity',
        'title',
        'description',
        'reported_by',
        'reported_at',
        'status',
        'assigned_to',
        'downtime_hours',
        'cost',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
        'downtime_hours' => 'float',
        'cost' => 'float',
    ];

    protected $appends = ['is_open'];

    public function getIsOpenAttribute(): bool
    {
        return !in_array($this->status, ['resolved', 'closed']);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipmentId', 'equipmentId');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by', 'id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }
}