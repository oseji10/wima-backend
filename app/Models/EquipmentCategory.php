<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentCategory extends Model
{
    public $table = 'equipment_category';
    protected $fillable = [
        'categoryId',
        'categoryName',
        
        'status'
    ];
    protected $primaryKey = 'categoryId';

    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'equipmentCategory', 'categoryId');
    }
  
}
