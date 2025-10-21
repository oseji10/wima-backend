<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    public $table = 'equipment';
    protected $fillable = [
        'equipmentId',
        'equipmentName',
        'serialNumber',
        'value',
        'equipmentCategory',
        'hub',
        'owner',
        'exact_location',
        'status'
    ];
    protected $primaryKey = 'equipmentId';

    public function category()
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipmentCategory', 'categoryId');
    }
    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    } 

    public function hubs()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    } 

     public function owner()
    {
        return $this->belongsTo(User::class, 'owner', 'id');
    } 
}
