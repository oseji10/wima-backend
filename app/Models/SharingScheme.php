<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharingScheme extends Model
{
    protected $table = 'finance_sharing_schemes';

    protected $fillable = [
        'name', 'is_active',
        'wima_pct', 'state_pct',
        'sb_wima_pct', 'sb_community_dev_pct', 'sb_state_coord_pct',
        'sb_cl_pct', 'sb_subcl_pct', 'sb_msp_pct',
        'msp_groups', 'msp_per_group',
        'weekly_multiplier', 'monthly_multiplier',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'wima_pct' => 'float', 'state_pct' => 'float',
        'sb_wima_pct' => 'float', 'sb_community_dev_pct' => 'float',
        'sb_state_coord_pct' => 'float', 'sb_cl_pct' => 'float',
        'sb_subcl_pct' => 'float', 'sb_msp_pct' => 'float',
        'msp_groups' => 'integer', 'msp_per_group' => 'integer',
        'weekly_multiplier' => 'float', 'monthly_multiplier' => 'float',
    ];

    protected $appends = ['msp_headcount'];

    public function getMspHeadcountAttribute(): int
    {
        return (int) max(1, $this->msp_groups * $this->msp_per_group);
    }

    /** The currently active scheme, or a sensible default if none exists. */
    public static function current(): self
    {
        return static::where('is_active', true)->latest('id')->first()
            ?? static::firstOrCreate(['is_active' => true], ['name' => 'Default Scheme']);
    }

    /**
     * Split a gross amount into every stakeholder share using this scheme.
     * This is the single source of truth that mirrors the spreadsheet.
     */
    public function split(float $gross): array
    {
        $wima      = $gross * ($this->wima_pct / 100);
        $stateTotal = $gross * ($this->state_pct / 100);

        $sbWima      = $stateTotal * ($this->sb_wima_pct / 100);
        $communityDev = $stateTotal * ($this->sb_community_dev_pct / 100);
        $stateCoord  = $stateTotal * ($this->sb_state_coord_pct / 100);
        $cl          = $stateTotal * ($this->sb_cl_pct / 100);
        $subcl       = $stateTotal * ($this->sb_subcl_pct / 100);
        $msp         = $stateTotal * ($this->sb_msp_pct / 100);

        return [
            'gross'          => round($gross, 2),
            'wima_top'       => round($wima, 2),       // 80% headline share
            'state_total'    => round($stateTotal, 2), // 20% pool
            'wima_state'     => round($sbWima, 2),      // WIMA's slice of the pool
            'wima_combined'  => round($wima + $sbWima, 2),
            'community_dev'  => round($communityDev, 2),
            'state_coord'    => round($stateCoord, 2),
            'cl'             => round($cl, 2),
            'subcl'          => round($subcl, 2),
            'msp'            => round($msp, 2),
            'msp_per_person' => round($msp / $this->msp_headcount, 2),
        ];
    }
}