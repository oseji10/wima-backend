<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transactions extends Model
{
    use HasFactory;

    public $table = 'transactions';
    protected $fillable = [
        'transactionId',
        'productId',
        'soldBy',
        'paymentMethod',
        'cost',
        'quantitySold',
        'lga',
        'beneficiary',
        'status'
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

    public function beneficiary_info()
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary', 'beneficiaryId');
    }

    public function transaction_products()
    {
        return $this->hasMany(TransactionProducts::class, 'transactionId', 'transactionId');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'soldBy', 'id');
    }
}
