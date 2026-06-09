<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveAllocation extends Model
{
    protected $table = 'hr_leave_allocations';

    protected $fillable = ['staff_id', 'leave_type_id', 'year', 'days_allocated'];

    protected $casts = [
        'year' => 'integer',
        'days_allocated' => 'float',
    ];
}