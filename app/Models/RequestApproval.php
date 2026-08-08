<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestApproval extends Model
{
    protected $fillable = ['request_id', 'approval_step_id', 'approver_id', 'status', 'comments', 'acted_at'];
    public function step() { return $this->belongsTo(ApprovalStep::class, 'approval_step_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approver_id'); }
}