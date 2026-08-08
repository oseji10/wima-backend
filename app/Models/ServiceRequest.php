<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $table = 'requests';
    // protected $fillable = [
    //     'request_no', 'request_type_id', 'employee_id', 'department_id', 'title', 'priority',
    //     'needed_by', 'description', 'justification', 'preferred_vendor', 'alternative_vendor',
    //     'total_amount', 'status', 'current_step', 'submitted_at',
    // ];

    // ServiceRequest.php
protected $fillable = [
    'request_no', 'request_type_id', 'employee_id', 'state', 'lga', 'title', 'priority',
    'needed_by', 'description', 'justification', 'vendor_id', 'alternative_vendor_id',
    'total_amount', 'status', 'current_step', 'submitted_at',
    'payment_reference', 'paid_amount', 'paid_at', 'paid_by',
];

public function vendor() { return $this->belongsTo(Vendor::class, 'vendor_id'); }
public function alternativeVendor() { return $this->belongsTo(Vendor::class, 'alternative_vendor_id'); }
public function paidByUser() { return $this->belongsTo(User::class, 'paid_by'); }


protected $casts = ['needed_by' => 'date', 'submitted_at' => 'datetime', 'total_amount' => 'decimal:2'];

public function role_approvals_expected() { /* n/a, remove */ }


public function type() { return $this->belongsTo(RequestType::class, 'request_type_id'); }
    public function employee() { return $this->belongsTo(User::class, 'employee_id'); }
    public function items() { return $this->hasMany(RequestItem::class, 'request_id'); }
    public function attachments() { return $this->hasMany(RequestAttachment::class, 'request_id'); }
    public function approvals() { return $this->hasMany(RequestApproval::class, 'request_id')->orderBy('id'); }

    public function recalcTotal(): void
    {
        $this->total_amount = $this->items()->get()->sum(fn ($i) => $i->quantity * $i->unit_cost);
        $this->save();
    }
}