<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Employment data attached 1:1 to a User. The User record remains the source of
 * truth for identity, name, role, state/lga and account status.
 */
class StaffProfile extends Model
{
    protected $table = 'hr_staff_profiles';

    protected $fillable = [
        'user_id', 'staff_number', 'job_title', 'department', 'employment_type',
        'hub', 'project_id', 'manager_id', 'hire_date', 'end_date',
        'employment_status', 'base_salary', 'notes', 'created_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'end_date' => 'date',
        'base_salary' => 'float',
    ];

    protected $appends = ['years_of_service'];

    public function getYearsOfServiceAttribute(): ?float
    {
        if (!$this->hire_date) {
            return null;
        }
        $end = $this->end_date ?: Carbon::today();
        return round($this->hire_date->floatDiffInYears($end), 1);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id', 'id');
    }
}