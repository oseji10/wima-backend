<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'category',
        'bank_name',
        'account_name',
        'account_number',
        'bank_sort_code',
        'tin',
        'is_active',
    ];

    public function requests()
    {
        return $this->hasMany(ServiceRequest::class, 'vendor_id');
    }
}