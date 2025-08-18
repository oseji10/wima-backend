<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionCommodity extends Model
{
    use HasFactory;

    public $table = 'transaction_commodities';
    protected $fillable = [
        'transactionCommodityId',
        'transactionReference',
        'commodityId',
        
    ];
    protected $primaryKey = 'transactionCommodityId';

    public function commodities()
    {
        return $this->belongsTo(Commodity::class, 'commodityId', 'commodityId');
    } 

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'stockId', 'stockId');
    } 
}
