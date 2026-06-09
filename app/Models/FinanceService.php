<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceService extends Model
{
    protected $table = 'finance_services';

    protected $fillable = [
        'name', 'default_unit_cost', 'default_target', 'active', 'sort_order',
    ];

    protected $casts = [
        'default_unit_cost' => 'float',
        'default_target' => 'integer',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];
}