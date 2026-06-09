<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMovement extends Model
{
    protected $table = 'equipment_movements';

    protected $fillable = [
        'equipmentId',
        'from_hub',
        'to_hub',
        'from_location',
        'to_location',
        'movement_type',
        'reason',
        'movement_date',
        'expected_return_date',
        'status',
        'dispatched_by',
        'received_by',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'expected_return_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipmentId', 'equipmentId');
    }

    public function fromHub()
    {
        return $this->belongsTo(Hubs::class, 'from_hub', 'hubId');
    }

    public function toHub()
    {
        return $this->belongsTo(Hubs::class, 'to_hub', 'hubId');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatched_by', 'id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by', 'id');
    }
}