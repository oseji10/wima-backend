<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceGoal extends Model
{
    protected $table = 'hr_performance_goals';

    protected $fillable = [
        'review_id', 'title', 'description', 'indicator_id',
        'target_value', 'actual_value', 'weight', 'score', 'status',
    ];

    protected $casts = [
        'target_value' => 'float',
        'actual_value' => 'float',
        'weight' => 'float',
        'score' => 'float',
    ];

    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id', 'id');
    }

    // Links to the M&E module's KPI catalogue
    public function indicator()
    {
        return $this->belongsTo(Indicator::class, 'indicator_id', 'id');
    }
}