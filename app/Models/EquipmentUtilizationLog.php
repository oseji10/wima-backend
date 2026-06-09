<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentUtilizationLog extends Model
{
    protected $table = 'equipment_utilization_logs';

    protected $fillable = [
        'equipmentId',
        'log_date',
        'hours_used',
        'hours_available',
        'downtime_hours',
        'output_units',
        'output_unit_label',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'log_date' => 'date',
        'hours_used' => 'float',
        'hours_available' => 'float',
        'downtime_hours' => 'float',
        'output_units' => 'float',
    ];

    // Expose computed metrics in JSON responses
    protected $appends = ['utilization_pct', 'uptime_pct'];

    public function getUtilizationPctAttribute(): float
    {
        if (!$this->hours_available) {
            return 0;
        }
        return round(($this->hours_used / $this->hours_available) * 100, 1);
    }

    public function getUptimePctAttribute(): float
    {
        if (!$this->hours_available) {
            return 0;
        }
        $up = max(0, $this->hours_available - $this->downtime_hours);
        return round(($up / $this->hours_available) * 100, 1);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipmentId', 'equipmentId');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }
}