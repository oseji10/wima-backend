<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoTractCooperativeMember extends Model
{
    protected $table = 'gotract_cooperative_members';

    protected $fillable = ['cooperative_id', 'application_id', 'joined_at'];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(GoTractCooperative::class, 'cooperative_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GoTractApplication::class, 'application_id');
    }
}