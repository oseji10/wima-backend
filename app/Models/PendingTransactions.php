<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingTransactions extends Model
{
    protected $fillable = [
        'transactionId',
        'beneficiaryId',
        'paymentMethod',
        'products',
        'totalCost',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'products' => 'array',
        'totalCost' => 'decimal:2',
    ];
}