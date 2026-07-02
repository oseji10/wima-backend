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

    /**
     * Computed suitability score is exposed on every serialization so the
     * admin list and detail views both carry it. (The public submit response
     * uses a resource that omits these, so applicants never see their score.)
     */
    protected $appends = ['score', 'score_band', 'score_breakdown'];

    protected ?array $scoreCache = null;

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

    /* ----------------------------- Scoring ----------------------------- */

    public function getScoreAttribute(): int
    {
        return $this->scoreData()['score'];
    }

    public function getScoreBandAttribute(): string
    {
        return $this->scoreData()['band'];
    }

    public function getScoreBreakdownAttribute(): array
    {
        return $this->scoreData()['breakdown'];
    }

    /**
     * Transparent, point-based suitability score (0–100) to guide approvers.
     * Every factor is a signal of programme fit or financing viability; the
     * breakdown is returned so the approver can see exactly how it was earned.
     */
    protected function scoreData(): array
    {
        if ($this->scoreCache !== null) {
            return $this->scoreCache;
        }

        $breakdown = [];
        $add = function (string $label, int $points, int $max) use (&$breakdown) {
            $breakdown[] = ['label' => $label, 'points' => $points, 'max' => $max];
        };

        // Youth age window (core eligibility).
        $age = (int) $this->age;
        $agePoints = ($age >= 18 && $age <= 35) ? 25 : (($age >= 36 && $age <= 40) ? 10 : 0);
        $add('Youth age (18–35)', $agePoints, 25);

        // Commitment to the 20% equity / repayment plan (critical for financing).
        $add('Willing to repay (20% equity)', $this->willing_repayment ? 20 : 0, 20);

        // Prior mechanized farming experience.
        $add('Prior mechanized experience', $this->prior_mech_experience ? 12 : 0, 12);

        // Occupation relevance.
        $occupation = $this->primary_occupation;
        $occupationPoints = in_array($occupation, ['Farming', 'Both'], true) ? 10
            : ($occupation === 'Livestock Rearing' ? 5 : 0);
        $add('Farming occupation', $occupationPoints, 10);

        // Cooperative / group membership (eases delivery & repayment).
        $add('Cooperative / group member', $this->in_cooperative ? 10 : 0, 10);

        // Access to credit or savings (financial readiness).
        $add('Access to credit / savings', $this->access_to_credit ? 8 : 0, 8);

        // Land ownership (asset base / commitment).
        $landPoints = match ($this->land_ownership) {
            'Owned'    => 7,
            'Communal' => 5,
            'Rented'   => 3,
            default    => 0,
        };
        $add('Land ownership', $landPoints, 7);

        // Banking details provided (needed before financing).
        $add('Bank account provided', $this->bank_account_number ? 4 : 0, 4);

        // Interest in mechanization operation training.
        $trainingAreas = (array) $this->training_areas;
        $trainingPoints = in_array('mechanization-operation', $trainingAreas, true) ? 4
            : (count($trainingAreas) > 0 ? 2 : 0);
        $add('Mechanization training interest', $trainingPoints, 4);

        $score = array_sum(array_column($breakdown, 'points'));
        $band = $score >= 70 ? 'High' : ($score >= 45 ? 'Medium' : 'Low');

        return $this->scoreCache = [
            'score'     => $score,
            'band'      => $band,
            'breakdown' => $breakdown,
        ];
    }
}