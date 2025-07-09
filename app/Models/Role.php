<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    public $table = 'roles';
    protected $primaryKey = 'roleId';
    protected $fillable = ['roleName', 'roleId', 'roleType'];

    public function permissions()
    {
        return $this->belongsToMany(Permissions::class, 'role_permissions', 'roleId', 'permissionId');
    }
}
