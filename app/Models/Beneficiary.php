<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    use HasFactory;

    public $table = 'beneficiaries';
    protected $fillable = [
        'beneficiaryId',
        'firstName',
        'lastName',
        'otherNames',
        'phoneNumber',
        'email',
        'enrolledBy',
        'lga',
        'isActive',
        'cadre',
        'beneficiaryType',
        'ministry',
        'cardNumber',
        'employeeId',
    ];
    protected $primaryKey = 'beneficiaryId';

    public function beneficiary_type()
    {
        return $this->belongsTo(BeneficiaryType::class, 'beneficiaryType', 'typeId');
    }

    public function enrolled_by()
    {
        return $this->belongsTo(User::class, 'enrolledBy', 'id');
    }   
    
    public function lga_info()
    {
        return $this->belongsTo(Lgas::class, 'lga', 'lgaId');
    }

    public function cadre_info()
    {
        return $this->belongsTo(Cadre::class, 'cadre', 'cadreId');
    }

    public function ministry_info()
    {
        return $this->belongsTo(Ministry::class, 'ministry', 'ministryId');
    }

    public function beneficiary_image()
    {
        return $this->belongsTo(BeneficiaryImage::class, 'beneficiaryId', 'beneficiaryId');
    }

}
