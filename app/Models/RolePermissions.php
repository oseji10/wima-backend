<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermissions extends Model
{
    use HasFactory;
    public $table = 'role_permissions';
    protected $primaryKey = 'rolePermissionId';
    protected $fillable = ['roleId', 'permissionId'];

    public function role()
    {
        return $this->hasOne(Roles::class, 'roleId', 'role'); // Assuming doctorId is the foreign key
    }   
}
