<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $table = 'membership_applications';
      protected $fillable = [
        'membershipType',
        'fullName',
        'dateOfBirth',
        'gender',
        'maritalStatus',
        'nationality',
        'homeAddress',
        'state',
        'lga',
        'wardDistrict',
        'community',
        'phoneNumber',
        'email',
        'occupation',
        'organization',
        'positionTitle',
        'areaOfExpertise',
        'reasonForJoining',
        'preferredCommunication',
        'meansOfIdentification',
        'meansOfIdentificationType',
        'cacDocument',
        'companyDetails',
        'companyMission',
        'operatorExperience',
        'status',
        'treatedBy',
    ];
}
