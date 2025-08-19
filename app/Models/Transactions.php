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
        'msp',
        'farmer',
        'paymentMethod',
        'transactionType',
        'transactionReference',
        'transactionStatus',
        'totalCost',
        'hub',
    ];
    protected $primaryKey = 'transactionId';

    public function transaction_list()
    {
        return $this->belongsTo(TransactionList::class, 'transactionReference', 'transactionReference');
    } 

    public function transaction_commodity()
    {
        return $this->hasMany(TransactionCommodity::class, 'transactionReference', 'transactionReference');
    } 

     public function transaction_services()
    {
        return $this->belongsTo(TransactionList::class, 'transactionReference', 'transactionReference');
    } 

    public function msp_info()
    {
        return $this->belongsTo(MSPs::class, 'msp', 'mspId');
    }

    public function farmer_info()
    {
        return $this->belongsTo(Farmers::class, 'farmer', 'farmerId');
    }

    public function hub_info()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    public function active_states()
    {
        return $this->belongsTo(ActiveStates::class, 'state', 'stateId');
    }

}
