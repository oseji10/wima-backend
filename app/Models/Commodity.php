<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commodity extends Model
{
    public $table = 'commodities';
    protected $primaryKey = 'commodityId';
    protected $fillable = [
        'commodityName',
        'commodityId',
        'status',
    ];
}
