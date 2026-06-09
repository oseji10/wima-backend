<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrRole extends Model
{
    protected $table = 'hr_roles';

    protected $fillable = ['name', 'department', 'description', 'responsibilities', 'level', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function staff()
    {
        return $this->hasMany(Staff::class, 'role_id', 'id');
    }
}