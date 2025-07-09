<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cancer extends Model
{
    use HasFactory;

    public $table = 'cancers';
    protected $fillable = [
        'cancerId',
        'cancerName',
    ];
    protected $primaryKey = 'cancerId';

    public function patientsCancer()
    {
        return $this->hasMany(Patient::class, 'cancer', 'cancerId');
    } 
}
