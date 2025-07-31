<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    public $table = 'services';
    protected $fillable = [
        'serviceName',
        'measurementUnit',
        'costPerUnit',
        'addedBy',
        'status',
    ];
}
