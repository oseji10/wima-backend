<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    protected $fillable = ['name', 'code', 'workflow_id', 'is_active'];
    public function workflow() { return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id'); }
}