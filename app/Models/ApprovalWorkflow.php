<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    protected $fillable = ['name', 'is_active'];
    public function steps() { return $this->hasMany(ApprovalStep::class, 'workflow_id')->orderBy('step_order'); }
}