<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MSPs extends Model
{
    use HasFactory;

    public $table = 'msps';
    protected $fillable = [
        'mspId',
        'firstName',
        'lastName',
        'otherNames',
        'hub',
        'gender',
        'address',
        'addedBy',
        'project',
        'userId',
        'trainings_attended',
        'ageBracket',
        'type',
        'year',
        'code',
    ];
    // protected $primaryKey = 'mspId';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'trainings_attended' => 'array',
        'ageBracket' => 'integer',
    ];

    public function states()
    {
        return $this->belongsTo(State::class, 'state', 'stateId');
    } 

     public function lgas()
    {
        return $this->belongsTo(Lgas::class, 'lga', 'lgaId');
    } 

     public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    } 

      public function users()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    } 

      public function projects()
    {
        return $this->belongsTo(Project::class, 'project', 'projectId');
    } 

     public function hubs()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    // Generate MSP ID
    public static function generateMspId()
    {
        $latest = self::orderBy('id', 'desc')->first();
        $number = $latest ? intval(substr($latest->mspId, 3)) + 1 : 1;
        return 'MSP' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
