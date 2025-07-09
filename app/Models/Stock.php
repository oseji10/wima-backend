<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    public $table = 'stock';
    protected $fillable = [
        'stockId',
        'productId',
        'stockName',
        'batchNumber',
        'quantityReceived',
        'quantitySold',
        'quantityTransferred',
        'quantityExpired',
        'quantityDamaged',
        'expiryDate',
        'lgaId',
        'receivedBy',
        'status',
        'isActive',
    ];
    protected $primaryKey = 'stockId';

    public function product()
    {
        return $this->belongsTo(Products::class, 'productId', 'productId');
    }

    public function lga_info()
    {
        return $this->belongsTo(Lgas::class, 'lgaId', 'lgaId');
    }

    public function received_by()
    {
        return $this->belongsTo(User::class, 'receivedBy', 'id');
    }
}
