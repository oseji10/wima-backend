<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionProducts extends Model
{
    use HasFactory;

    public $table = 'transaction_products';
    protected $fillable = [
        'transactionId',
        'productId',
        'soldBy',
        'cost',
        'quantitySold',
        
    ];
    protected $primaryKey = 'id';

    public function products()
    {
        return $this->belongsTo(Products::class, 'productId', 'productId');
    } 

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'stockId', 'stockId');
    } 
}
