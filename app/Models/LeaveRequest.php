<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $table = 'hr_leave_requests';

    protected $fillable = [
        'staff_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'reason',
        'status', 'approver_id', 'decided_at', 'decision_note', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'float',
        'decided_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id', 'id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id', 'id');
    }
}