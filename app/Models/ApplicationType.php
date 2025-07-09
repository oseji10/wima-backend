<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationType extends Model
{
    use HasFactory;

    public $table = 'application_types';
    protected $fillable = [
        'typeId',
        'typeName',
        
    ];
    protected $primaryKey = 'typeId';

    protected $hidden = ['created_at', 'updated_at'];
}
