<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductRequest extends Model
{
    use HasFactory;

    public $table = 'product_requests';
    protected $fillable = [
        'productRequestId',
        'productId',
        'lga',
        'quantityRequested',
        'quantityDispatched',
        'quantityReceived',
        'requestedBy',
        'approvedBy',
        'receivedBy',
        'requestDate',
        'approvedDate',
        'receivedDate',
    ];
    protected $primaryKey = 'id';

    
}
