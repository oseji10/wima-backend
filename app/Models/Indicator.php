<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    protected $table = 'me_indicators';

    protected $fillable = [
        'name', 'code', 'description', 'unit', 'level', 'project_id', 'program',
        'source_type', 'form_id', 'field_key', 'aggregation',
        'numerator_field', 'denominator_field', 'formula',
        'baseline', 'target', 'direction', 'frequency',
        'is_donor_visible', 'active', 'sort_order',
    ];

    protected $casts = [
        'baseline' => 'float',
        'target' => 'float',
        'is_donor_visible' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function form()
    {
        return $this->belongsTo(MeForm::class, 'form_id', 'id');
    }

    public function values()
    {
        return $this->hasMany(IndicatorValue::class, 'indicator_id', 'id');
    }

    /**
     * Attainment of target as a percentage, respecting direction.
     * For "increase" indicators: value/target. For "decrease": target/value.
     */
    public function attainment(?float $value): ?float
    {
        if ($value === null || $this->target === null || $this->target == 0) {
            return null;
        }
        $pct = $this->direction === 'decrease'
            ? ($value == 0 ? 100 : ($this->target / $value) * 100)
            : ($value / $this->target) * 100;

        return round($pct, 1);
    }
}