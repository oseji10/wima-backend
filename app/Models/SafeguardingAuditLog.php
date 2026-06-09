<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafeguardingAuditLog extends Model
{
    protected $table = 'security_safeguarding_audit_logs';

    public $timestamps = false;

    protected $fillable = ['case_id', 'user_id', 'action', 'detail', 'ip_address', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}