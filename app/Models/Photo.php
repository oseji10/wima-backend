<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Photo extends Model
{
    use HasFactory;
    public $table = 'passport_photographs';
    protected $primaryKey = 'photoId';
    protected $fillable = [
        'userId',
        'applicationId',
        'photoPath',
    ];
}
