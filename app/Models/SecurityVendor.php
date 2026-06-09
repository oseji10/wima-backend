<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityVendor extends Model
{
    protected $table = 'security_vendors';

    protected $fillable = [
        'name', 'type', 'contact_name', 'contact_phone', 'contact_email',
        'service_scope', 'status', 'notes', 'created_by',
    ];

    public function coverage()
    {
        return $this->hasMany(VendorCoverage::class, 'vendor_id', 'id');
    }
}