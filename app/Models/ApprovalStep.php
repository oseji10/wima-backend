<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    protected $fillable = ['workflow_id', 'step_order', 'role_id', 'approval_limit', 'label'];
    public function role() { return $this->belongsTo(Role::class, 'role_id', 'roleId'); }
}
