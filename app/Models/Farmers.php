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
        'alternatePhoneNumber',
        'gender',
        'maritalStatus',
        'msp',
        'ageBracket',
        'isDisabled',
        'disabilityDescription',
        'status',
        'community',
    ];
    protected $primaryKey = 'farmerId';
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

     public function subhubs()
    {
        return $this->belongsTo(Subhubs::class, 'community', 'subHubId');
    } 

    //   public function users()
    // {
    //     return $this->belongsTo(User::class, 'userId', 'id');
    // } 
}
