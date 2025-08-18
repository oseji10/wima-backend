<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    public $table = 'services';
    protected $primaryKey = 'serviceId';
    protected $fillable = [
        'serviceName',
        'measuringUnit',
        'costPerUnit',
        'cost',
        'addedBy',
        'status',
    ];
}
