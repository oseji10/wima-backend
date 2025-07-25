<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hubs extends Model
{
    use HasFactory;

    public $table = 'hubs';
    protected $fillable = [
        'hubId',
        'state',
        'lgaOrHub',
        'community',
        'addedBy',
    ];
    protected $primaryKey = 'hubId';
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
