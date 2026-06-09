<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafeguardingCase extends Model
{
    protected $table = 'security_safeguarding_cases';

    protected $fillable = [
        'reference', 'category', 'severity', 'occurred_at',
        'state', 'lga', 'hub', 'is_anonymous', 'survivor_ref', 'survivor_details',
        'description', 'immediate_needs', 'consent_to_share',
        'status', 'assigned_officer_id', 'reported_by', 'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'is_anonymous' => 'boolean',
        'consent_to_share' => 'boolean',
    ];

    public function actions()
    {
        return $this->hasMany(SafeguardingAction::class, 'case_id', 'id')->orderByDesc('action_at');
    }

    /** Minimal fields safe to show in a list (no survivor identifiers). */
    public function toListArray(): array
    {
        return [
            'id'          => $this->id,
            'reference'   => $this->reference,
            'category'    => $this->category,
            'severity'    => $this->severity,
            'status'      => $this->status,
            'occurred_at' => $this->occurred_at,
            'is_anonymous' => $this->is_anonymous,
            'assigned_officer_id' => $this->assigned_officer_id,
            'created_at'  => $this->created_at,
        ];
    }
}