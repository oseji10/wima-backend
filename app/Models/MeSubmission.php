<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeSubmission extends Model
{
    protected $table = 'me_submissions';

    protected $fillable = [
        'form_id', 'hub', 'submission_date', 'data', 'location', 'notes', 'submitted_by',
    ];

    protected $casts = [
        'data' => 'array',
        'submission_date' => 'date',
    ];

    public function form()
    {
        return $this->belongsTo(MeForm::class, 'form_id', 'id');
    }

    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }
}