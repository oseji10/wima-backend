<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $table = 'hr_leave_types';

    protected $fillable = ['name', 'default_days_per_year', 'paid', 'color', 'active'];

    protected $casts = [
        'default_days_per_year' => 'integer',
        'paid' => 'boolean',
        'active' => 'boolean',
    ];
}