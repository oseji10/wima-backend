<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanTransaction extends Model
{
    protected $fillable = ['transactionId', 'beneficiary', 'soldBy', 'paymentMethod', 'status', 'totalCost'];

    public function transaction_products()
    {
        return $this->hasMany(LoanTransactionProduct::class, 'transactionId', 'transactionId');
    }

    public function beneficiary_info()
    {
        return $this->belongsTo(User::class, 'beneficiary');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'soldBy');
    }
}

