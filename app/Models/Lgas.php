<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lgas extends Model
{
    use HasFactory;

    public $table = 'lgas';
    protected $fillable = [
        'lgaId',
        'lgaName',
        'districtId',
    ];
    protected $primaryKey = 'lgaId';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    public function senatorial_district()
    {
        return $this->belongsTo(SenatorialDistrict::class, 'districtId', 'districtId');
    } 
}
