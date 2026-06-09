<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentLifecycleEvent extends Model
{
    protected $table = 'equipment_lifecycle_events';

    protected $fillable = [
        'equipmentId',
        'event_type',
        'title',
        'description',
        'event_date',
        'meta',
        'performed_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'event_date' => 'date',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipmentId', 'equipmentId');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by', 'id');
    }
}