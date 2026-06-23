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
        'zohoCustomerId',
        'mechanized_services',

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

    protected $casts = [
        'mechanized_services' => 'array',
        // 'ageBracke' => 'integer',
    ];

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


    public static function generateFarmerId()
    {
        $latest = self::orderBy('id', 'desc')->first();
        $number = $latest ? intval(substr($latest->farmerId, 2)) + 1 : 1;
        return 'FM' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
