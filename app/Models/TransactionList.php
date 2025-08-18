<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionList extends Model
{
    use HasFactory;

    public $table = 'transaction_list';
    protected $fillable = [
        'transactionListId',
        'transactionReference',
        'serviceId',
        'quantity',
        'unitCost',
        
    ];
    protected $primaryKey = 'transactionListId';

    public function products()
    {
        return $this->belongsTo(Products::class, 'productId', 'productId');
    } 

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'stockId', 'stockId');
    } 
}
