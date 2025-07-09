<?php
// LoanTransactionProduct.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanTransactionProduct extends Model
{
    protected $fillable = ['transactionId', 'productId', 'quantitySold', 'cost'];

    public function products()
    {
        return $this->belongsTo(Product::class, 'productId', 'productId');
    }
}