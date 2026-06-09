<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Staff extends Model
{
    use SoftDeletes;

    protected $table = 'hr_staff';

    protected $fillable = [
        'user_id', 'staff_number', 'first_name', 'last_name', 'email', 'phone',
        'role_id', 'job_title', 'department', 'employment_type',
        'hub', 'project_id', 'manager_id',
        'hire_date', 'end_date', 'status', 'base_salary', 'notes', 'created_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'end_date' => 'date',
        'base_salary' => 'float',
    ];

    protected $appends = ['full_name', 'years_of_service'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getYearsOfServiceAttribute(): ?float
    {
        if (!$this->hire_date) {
            return null;
        }
        $end = $this->end_date ?: Carbon::today();
        return round($this->hire_date->floatDiffInYears($end), 1);
    }

    public function role()
    {
        return $this->belongsTo(HrRole::class, 'role_id', 'id');
    }

    public function manager()
    {
        return $this->belongsTo(Staff::class, 'manager_id', 'id');
    }

    public function reports()
    {
        return $this->hasMany(Staff::class, 'manager_id', 'id');
    }

    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    public function reviews()
    {
        return $this->hasMany(PerformanceReview::class, 'staff_id', 'id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'staff_id', 'id');
    }

    public function complianceItems()
    {
        return $this->hasMany(ComplianceItem::class, 'staff_id', 'id');
    }
}