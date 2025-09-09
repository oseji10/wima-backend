<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    public $table = 'projects';
    protected $fillable = [
        'projectId',
        'projectName',
    ];
    protected $primaryKey = 'projectId';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
   
}
