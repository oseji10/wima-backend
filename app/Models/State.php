<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;
    public $table = 'states';
    protected $primaryKey = 'stateId';
    protected $fillable = ['stateName', 'stateId'];

  
}
