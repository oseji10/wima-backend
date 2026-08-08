<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GoTractCooperative extends Model
{
    protected $table = 'gotract_cooperatives';

    protected $fillable = [
        'code', 'name', 'lead_application_id', 'lga', 'required_size',
        'status', 'formed_at',
    ];

    protected $casts = [
        'formed_at' => 'datetime',
    ];

    // Statuses that still count as "active" cooperative
    public const ACTIVE_STATUSES = ['forming', 'active', 'requested', 'approved', 'collected'];
    public const COMPLETED_STATUSES = ['completed', 'cancelled'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(GoTractApplication::class, 'lead_application_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GoTractCooperativeMember::class, 'cooperative_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(GoTractCooperativeRequest::class, 'cooperative_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(GoTractEquipment::class, 'equipment_id', 'id');
    }

    // public static function generateCode(): string
    // {
    //     do {
    //         $code = 'GTCOP-' . strtoupper(Str::random(5));
    //     } while (static::where('code', $code)->exists());

    //     return $code;
    // }
protected static function booted()
{
    static::created(function (GoTractCooperative $cooperative) {
        $cooperative->updateQuietly([
            'code' => 'GTCOP-' . str_pad($cooperative->id, 4, '0', STR_PAD_LEFT),
        ]);
    });
}
}