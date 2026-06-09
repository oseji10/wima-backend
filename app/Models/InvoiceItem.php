<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'finance_invoice_items';

    protected $fillable = ['invoice_id', 'description', 'quantity', 'unit_price', 'amount'];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }
}