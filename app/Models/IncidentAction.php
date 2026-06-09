<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentAction extends Model
{
    protected $table = 'security_incident_actions';

    protected $fillable = [
        'incident_id', 'action_type', 'description', 'decision_note', 'performed_by', 'action_at',
    ];

    protected $casts = ['action_at' => 'datetime'];

    public function incident()
    {
        return $this->belongsTo(SecurityIncident::class, 'incident_id', 'id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by', 'id');
    }
}