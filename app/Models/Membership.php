<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $table = 'membership_applications';
     protected $fillable = [
        'membershipType',
        'firstName',
        'lastName',
        'email',
        'phoneNumber',
        'profession',
        'message',
        'equipmentProof',
        'studentProof',
        'companyDetails',
        'companyMission',
        'operatorExperience',
        'skillsAssessment',
    ];
}
