<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafeguardingOfficer extends Model
{
    protected $table = 'security_safeguarding_officers';

    protected $fillable = ['user_id', 'active', 'assigned_by', 'assigned_at'];

    protected $casts = ['active' => 'boolean', 'assigned_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}