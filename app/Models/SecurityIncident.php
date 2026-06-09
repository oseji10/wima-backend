<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityIncident extends Model
{
    protected $table = 'security_incidents';

    protected $fillable = [
        'reference', 'type', 'severity', 'occurred_at',
        'state', 'lga', 'hub', 'location_note',
        'title', 'description',
        'affected_persons', 'affected_persons_count', 'affected_assets', 'equipment_id',
        'status', 'assigned_to', 'assigned_team', 'escalation_level',
        'resolution', 'resolved_at', 'reported_by', 'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
        'affected_persons_count' => 'integer',
        'escalation_level' => 'integer',
    ];

    protected $appends = ['is_open', 'severity_weight'];

    public function getIsOpenAttribute(): bool
    {
        return !in_array($this->status, ['resolved', 'closed'], true);
    }

    public function getSeverityWeightAttribute(): int
    {
        return ['low' => 1, 'medium' => 3, 'high' => 7, 'critical' => 12][$this->severity] ?? 1;
    }

    public function actions()
    {
        return $this->hasMany(IncidentAction::class, 'incident_id', 'id')->orderByDesc('action_at');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    public function hubInfo()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }
}