<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorValue extends Model
{
    protected $table = 'me_indicator_values';

    protected $fillable = [
        'indicator_id', 'hub', 'period_date', 'value', 'source', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'value' => 'float',
        'period_date' => 'date',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class, 'indicator_id', 'id');
    }
}