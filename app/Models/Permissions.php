<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permissions extends Model
{
    use HasFactory;
    public $table = 'permissions';
    protected $primaryKey = 'permissionId';
    protected $fillable = [
        'permissionName',
        'permissionSlug'
    ];
    
}
