<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subhubs extends Model
{
    use HasFactory;

    public $table = 'subhubs';
    protected $fillable = [
        'subHubId',
        'hubId',
        'subHubName',
        'addedBy',
        'status',
    ];
    protected $primaryKey = 'subHubId';
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

 public function subhubs()
    {
        return $this->hasMany(Subhubs::class, 'hubId', 'hubId');
    } 

     public function added_by()
    {
        return $this->belongsTo(User::class, 'addedBy', 'id');
    } 
}
