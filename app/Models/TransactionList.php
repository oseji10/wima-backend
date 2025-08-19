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

    public function services()
    {
        return $this->belongsTo(Services::class, 'serviceId', 'serviceId');
    } 

  
}
