<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Invoice extends Model
{
    protected $table = 'finance_invoices';

    protected $fillable = [
        'invoice_number', 'hub', 'client_name', 'client_email', 'client_phone',
        'issue_date', 'due_date', 'status',
        'subtotal', 'tax_pct', 'tax_amount', 'total', 'amount_paid',
        'notes', 'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'float', 'tax_pct' => 'float', 'tax_amount' => 'float',
        'total' => 'float', 'amount_paid' => 'float',
    ];

    protected $appends = ['balance', 'computed_status', 'is_overdue'];

    public function getBalanceAttribute(): float
    {
        return round(max(0, $this->total - $this->amount_paid), 2);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, ['paid', 'void'])) {
            return false;
        }
        return $this->due_date && $this->due_date->isPast() && $this->balance > 0;
    }

    /** Derive a live status from payment + due date without overwriting 'void'. */
    public function getComputedStatusAttribute(): string
    {
        if ($this->status === 'void') {
            return 'void';
        }
        if ($this->balance <= 0 && $this->total > 0) {
            return 'paid';
        }
        if ($this->is_overdue) {
            return 'overdue';
        }
        if ($this->amount_paid > 0) {
            return 'partial';
        }
        return $this->status; // draft | sent
    }

    /** Recompute money columns from line items + tax, then persist. */
    public function recalcTotals(): void
    {
        $subtotal = $this->items()->sum('amount');
        $tax = round($subtotal * ($this->tax_pct / 100), 2);
        $this->subtotal = round($subtotal, 2);
        $this->tax_amount = $tax;
        $this->total = round($subtotal + $tax, 2);
        $this->save();
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id', 'id')->orderByDesc('paid_at');
    }

    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }
}