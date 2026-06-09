<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueEntry extends Model
{
    protected $table = 'finance_revenue_entries';

    protected $fillable = [
        'hub', 'service_id', 'service_name',
        'unit_cost', 'target', 'quantity',
        'entry_date', 'scheme_id', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'unit_cost' => 'float',
        'target' => 'integer',
        'quantity' => 'integer',
        'entry_date' => 'date',
    ];

    // Computed figures exposed in every JSON response
    protected $appends = ['gross_total', 'shares'];

    /** gross = unit_cost x target x quantity (the spreadsheet's K column). */
    public function getGrossTotalAttribute(): float
    {
        return round($this->unit_cost * $this->target * $this->quantity, 2);
    }

    /** Full stakeholder breakdown via the entry's scheme (or the active one). */
    public function getSharesAttribute(): array
    {
        $scheme = $this->relationLoaded('scheme') && $this->scheme
            ? $this->scheme
            : ($this->scheme_id ? SharingScheme::find($this->scheme_id) : null);
        $scheme = $scheme ?: SharingScheme::current();

        return $scheme->split($this->gross_total);
    }

    public function scheme()
    {
        return $this->belongsTo(SharingScheme::class, 'scheme_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(FinanceService::class, 'service_id', 'id');
    }

    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }
}