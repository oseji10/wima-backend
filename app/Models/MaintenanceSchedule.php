<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MaintenanceSchedule extends Model
{
    protected $table = 'maintenance_schedules';

    protected $fillable = [
        'equipmentId',
        'title',
        'maintenance_type',
        'frequency_type',
        'frequency_value',
        'last_serviced_at',
        'next_due_at',
        'assigned_to',
        'status',
        'instructions',
    ];

    protected $casts = [
        'last_serviced_at' => 'date',
        'next_due_at' => 'date',
        'frequency_value' => 'integer',
    ];

    protected $appends = ['due_status', 'days_until_due'];

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->next_due_at) {
            return null;
        }
        return Carbon::today()->diffInDays($this->next_due_at, false);
    }

    // ok | due_soon | overdue  (due_soon = within 7 days)
    public function getDueStatusAttribute(): string
    {
        if ($this->status !== 'active' || !$this->next_due_at) {
            return 'ok';
        }
        $days = $this->days_until_due;
        if ($days < 0) {
            return 'overdue';
        }
        if ($days <= 7) {
            return 'due_soon';
        }
        return 'ok';
    }

    /**
     * Roll the schedule forward from a service date based on its frequency.
     */
    public function computeNextDue(Carbon $from): Carbon
    {
        return match ($this->frequency_type) {
            'days'  => $from->copy()->addDays($this->frequency_value),
            'weeks' => $from->copy()->addWeeks($this->frequency_value),
            'usage_hours' => $from->copy()->addDays($this->frequency_value), // fallback: treat as days
            default => $from->copy()->addMonths($this->frequency_value),
        };
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipmentId', 'equipmentId');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }
}