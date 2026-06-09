<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    protected $table = 'hr_performance_reviews';

    protected $fillable = [
        'staff_id', 'reviewer_id', 'period_label', 'period_start', 'period_end',
        'status', 'overall_score', 'summary', 'strengths', 'improvements', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'overall_score' => 'float',
    ];

    protected $appends = ['computed_overall'];

    /** Weighted average of goal scores (0-100), weighted by each goal's weight. */
    public function getComputedOverallAttribute(): ?float
    {
        if (!$this->relationLoaded('goals')) {
            return $this->overall_score;
        }
        $scored = $this->goals->filter(fn ($g) => $g->score !== null);
        $totalWeight = $scored->sum('weight');
        if ($totalWeight <= 0) {
            return $this->overall_score;
        }
        $weighted = $scored->reduce(fn ($c, $g) => $c + ($g->score * $g->weight), 0);
        return round($weighted / $totalWeight, 2);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'id');
    }

    public function goals()
    {
        return $this->hasMany(PerformanceGoal::class, 'review_id', 'id');
    }
}