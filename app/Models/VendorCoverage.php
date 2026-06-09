<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorCoverage extends Model
{
    protected $table = 'security_vendor_coverage';

    protected $fillable = ['vendor_id', 'state', 'lga', 'hub'];

    public function vendor()
    {
        return $this->belongsTo(SecurityVendor::class, 'vendor_id', 'id');
    }
}