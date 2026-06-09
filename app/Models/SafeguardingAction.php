<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafeguardingAction extends Model
{
    protected $table = 'security_safeguarding_actions';

    protected $fillable = [
        'case_id', 'action_type', 'description', 'decision_note', 'performed_by', 'action_at',
    ];

    protected $casts = ['action_at' => 'datetime'];

    public function case()
    {
        return $this->belongsTo(SafeguardingCase::class, 'case_id', 'id');
    }
}