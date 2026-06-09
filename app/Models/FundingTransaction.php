<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundingTransaction extends Model
{
    protected $table = 'finance_funding_transactions';

    protected $fillable = [
        'funding_source_id', 'type', 'amount', 'transaction_date',
        'reference', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'transaction_date' => 'date',
    ];

    public function source()
    {
        return $this->belongsTo(FundingSource::class, 'funding_source_id', 'id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }
}