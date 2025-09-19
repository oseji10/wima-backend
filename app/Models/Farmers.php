<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Farmers extends Model
{
    use HasFactory;

    public $table = 'farmers';
    protected $fillable = [
        'farmerId',
        'farmerFirstName',
        'farmerLastName',
        'farmerOtherNames',
        'phoneNumber',
        'alternatePhoneNumber',
        'gender',
        'maritalStatus',
        'msp',
        'ageBracket',
        'isDisabled',
        'disabilityDescription',
        'status',
        'hub',
        'project',
    ];
    protected $primaryKey = 'id';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    // public function states()
    // {
    //     return $this->belongsTo(State::class, 'state', 'stateId');
    // } 

     public function msp()
    {
        return $this->belongsTo(MSPs::class, 'msp', 'mspId');
    } 

     public function hubs()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    } 

    

      public function projects()
    {
        return $this->belongsTo(Project::class, 'project', 'projectId');
    } 
}
