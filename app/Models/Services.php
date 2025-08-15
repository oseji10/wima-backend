<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    public $table = 'services';
    protected $primaryKey = 'serviceId';
    protected $fillable = [
        'serviceName',
        'measurementUnit',
        'costPerUnit',
        'cost',
        'addedBy',
        'status',
    ];
}
