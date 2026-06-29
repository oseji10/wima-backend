<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoTractApplication extends Model
{
    use HasFactory;

        protected $table = 'gotract_applications';

    protected $fillable = [
        'full_name', 'gender', 'date_of_birth', 'age', 'phone_number', 'email',
        'state', 'lga', 'village',
        'national_id', 'bvn', 'bank_account_number', 'bank_name',
        'has_disability', 'disability_type', 'disability_other',
        'marital_status', 'primary_occupation', 'crops_farmed',
        'household_size', 'dependents', 'land_area', 'land_ownership',
        'in_cooperative', 'cooperative_name', 'prior_mech_experience',
        'preferred_services', 'currently_employed', 'willing_repayment', 'access_to_credit',
        'training_areas', 'training_other', 'consent', 'signature',
        'status', 'ip_address', 'submitted_at',
    ];

    protected $casts = [
        'date_of_birth'         => 'date',
        'age'                   => 'integer',
        'household_size'        => 'integer',
        'dependents'            => 'integer',
        'land_area'             => 'decimal:2',
        'has_disability'        => 'boolean',
        'in_cooperative'        => 'boolean',
        'prior_mech_experience' => 'boolean',
        'currently_employed'    => 'boolean',
        'willing_repayment'     => 'boolean',
        'access_to_credit'      => 'boolean',
        'consent'               => 'boolean',
        'preferred_services'    => 'array',
        'training_areas'        => 'array',
        'submitted_at'          => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $application) {
            if (empty($application->reference_id)) {
                $application->reference_id = static::generateReference($application->lga);
            }
            if (empty($application->status)) {
                $application->status = 'pending';
            }
        });
    }

    /**
     * Build the next sequential reference for an LGA, e.g. GTR-26-AKK-0001.
     * The serial runs per LGA per year and is zero-padded to 4 digits.
     */
    public static function generateReference(?string $lga): string
    {
        $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $lga) ?: 'GEN', 0, 3));
        $prefix = sprintf('GTR-%s-%s-', date('y'), $code);

        // Highest serial issued so far for this LGA + year.
        $last = static::where('reference_id', 'like', $prefix . '%')
            ->orderByDesc('reference_id')
            ->value('reference_id');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        // Skip any number already taken (e.g. from a concurrent insert).
        do {
            $reference = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (static::where('reference_id', $reference)->exists());

        return $reference;
    }
}