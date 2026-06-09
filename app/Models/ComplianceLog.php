<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ComplianceLog extends Model
{
    protected $table = 'compliance_logs';

    protected $fillable = [
        'equipmentId',
        'log_type',
        'title',
        'status',
        'issued_at',
        'expires_at',
        'authority',
        'document_ref',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    protected $appends = ['computed_status', 'days_until_expiry'];

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return Carbon::today()->diffInDays($this->expires_at, false);
    }

    // Derives a live status from the expiry date when one exists.
    public function getComputedStatusAttribute(): string
    {
        if (!$this->expires_at) {
            return $this->status ?: 'na';
        }
        $days = $this->days_until_expiry;
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'due';
        }
        return 'compliant';
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