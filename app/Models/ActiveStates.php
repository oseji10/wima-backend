<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ActiveStates extends Model
{
    use HasFactory;
    // use SoftDeletes;

    public $table = 'active_states';
    protected $fillable = [
        'stateId',
    ];
    protected $primaryKey = 'id';

    public function state_info()
    {
        return $this->belongsTo(State::class, 'stateId', 'stateId');
    } 

    

}
