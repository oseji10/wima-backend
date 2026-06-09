<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundingSource extends Model
{
    protected $table = 'finance_funding_sources';

    protected $fillable = [
        'name', 'type', 'organization', 'contact_email', 'currency',
        'total_committed', 'start_date', 'end_date', 'status',
        'purpose', 'equity_pct', 'expected_return_pct', 'notes', 'created_by',
    ];

    protected $casts = [
        'total_committed' => 'float',
        'equity_pct' => 'float',
        'expected_return_pct' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $appends = ['total_received', 'balance', 'pct_received'];

    public function getTotalReceivedAttribute(): float
    {
        return round((float) $this->transactions()
            ->where('type', 'disbursement')->sum('amount'), 2);
    }

    public function getBalanceAttribute(): float
    {
        return round($this->total_committed - $this->total_received, 2);
    }

    public function getPctReceivedAttribute(): float
    {
        if ($this->total_committed <= 0) {
            return 0;
        }
        return round(($this->total_received / $this->total_committed) * 100, 1);
    }

    public function transactions()
    {
        return $this->hasMany(FundingTransaction::class, 'funding_source_id', 'id')
            ->orderByDesc('transaction_date');
    }
}