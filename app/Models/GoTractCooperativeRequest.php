<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoTractCooperativeRequest extends Model
{
    protected $table = 'gotract_cooperative_requests';

    protected $fillable = [
        'cooperative_id', 'equipment_id', 'quantity', 'status',
        'requested_at', 'approved_at', 'approved_by', 'collected_at', 'returned_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at'  => 'datetime',
        'collected_at' => 'datetime',
        'returned_at'  => 'datetime',
    ];

    public const ACTIVE_STATUSES = ['pending', 'approved', 'collected'];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(GoTractCooperative::class, 'cooperative_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(GoTractEquipment::class, 'equipment_id');
    }
}