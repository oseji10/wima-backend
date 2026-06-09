<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'finance_payments';

    protected $fillable = ['invoice_id', 'amount', 'paid_at', 'method', 'reference', 'recorded_by'];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }
}