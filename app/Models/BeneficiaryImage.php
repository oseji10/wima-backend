<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiaryImage extends Model
{
    protected $table = 'beneficiary_image';
    protected $fillable = [
        'imageId',
        'beneficiaryId',
        'imagePath',
    ];
    protected $primaryKey = 'imageId';

    
}
