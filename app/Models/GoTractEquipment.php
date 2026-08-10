<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoTractEquipment extends Model
{
    protected $table = 'gotract_equipment';

    protected $fillable = [
        'name', 'description', 'type', 'group_size', 'image_url',
        'total_quantity', 'available_quantity', 'unit', 'is_active', 'category',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }
}