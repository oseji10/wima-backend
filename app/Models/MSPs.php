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
        'address',
        'addedBy',
    ];
    // protected $primaryKey = 'mspId';
    protected $hidden = [
        'created_at',
        'updated_at',
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
}
