<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agents extends Model
{
    use HasFactory;

    public $table = 'agents';
    protected $fillable = [
        'agentId',
        'agentName',
        'phoneNumber',
        'email',
        'status',
    ];
    // protected $primaryKey = 'agentId';

     public function hubs()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    } 
}
