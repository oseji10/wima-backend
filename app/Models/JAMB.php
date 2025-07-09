<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JAMB extends Model
{
    use HasFactory;

    public $table = 'jamb';
    protected $fillable = [
        'jambId',
        'firstName',
        'lastName',
        'otherNames',
        'gender',
        'state',
        'aggregateScore',
    ];
    protected $primaryKey = 'jambId';

    public $incrementing = false; // Set to false since jambId is not an auto-incrementing integer
    public $timestamps = true; // Enable timestamps if you want created_at and updated_at fields
    protected $keyType = 'string'; // Set the key type to string since jambId is a string
    protected $casts = [
        'jambId' => 'string',
        'firstName' => 'string',
        'lastName' => 'string',
        'otherNames' => 'string',
        'gender' => 'string',
        'state' => 'string',
        'aggregateScore' => 'float',
    ];
}
