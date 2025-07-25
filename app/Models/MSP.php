<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    use HasFactory;

    public $table = 'msps';
    protected $fillable = [
        'mspId',
        'mspName',
        
        'userId',
    ];
    protected $primaryKey = 'mspId';

    // public function beneficiary_type()
    // {
    //     return $this->belongsTo(BeneficiaryType::class, 'beneficiaryType', 'typeId');
    // }



}
