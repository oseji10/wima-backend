<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoTractIndividualLoan extends Model
{
    protected $table = 'gotract_individual_loans';

    protected $fillable = [
        'application_id', 'equipment_id', 'quantity', 'status',
        'requested_at', 'approved_at', 'approved_by', 'collected_at', 'returned_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at'  => 'datetime',
        'collected_at' => 'datetime',
        'returned_at'  => 'datetime',
    ];

    public const ACTIVE_STATUSES = ['pending', 'approved', 'collected'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(GoTractApplication::class, 'application_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(GoTractEquipment::class, 'equipment_id');
    }
}