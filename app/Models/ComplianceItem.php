<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ComplianceItem extends Model
{
    protected $table = 'hr_compliance_items';

    protected $fillable = [
        'staff_id', 'type', 'title', 'issued_at', 'expires_at',
        'authority', 'document_ref', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    protected $appends = ['computed_status', 'days_until_expiry'];

    public function getComputedStatusAttribute(): string
    {
        if (!$this->expires_at) {
            return 'valid';
        }
        $days = Carbon::today()->diffInDays($this->expires_at, false);
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'expiring';
        }
        return 'valid';
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->expires_at ? (int) Carbon::today()->diffInDays($this->expires_at, false) : null;
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id', 'id');
    }
}