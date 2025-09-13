<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StateCoordinators extends Model
{
    use HasFactory;
    public $table = 'state_coordinators';
    protected $primaryKey = 'coordinatorId';
    protected $fillable = ['stateId', 'status', 'userId'];

    public function state()
    {
        return $this->belongsTo(State::class, 'stateId', 'stateId');
    } 

      public function users()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }
}
