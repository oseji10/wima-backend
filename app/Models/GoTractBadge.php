<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GoTractBadge extends Model
{
    protected $table = 'gotract_badges';

    protected $fillable = [
        'serial', 'token', 'batch', 'application_id', 'assigned_at', 'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(GoTractApplication::class, 'application_id');
    }

    public function isAssigned(): bool
    {
        return ! is_null($this->application_id);
    }

    /**
     * Generate a batch of blank badges ready for printing.
     * Serials continue from the highest existing number, so batches never clash.
     */
    public static function generateBatch(int $count, ?string $batch = null): \Illuminate\Support\Collection
    {
        $batch = $batch ?: now()->format('Y-m-d');

        $last = static::orderByDesc('id')->value('serial');
        $next = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

        $created = collect();

        for ($i = 0; $i < $count; $i++) {
            $serial = 'GT-' . str_pad((string) ($next + $i), 4, '0', STR_PAD_LEFT);

            do {
                $token = strtoupper(Str::random(12));
            } while (static::where('token', $token)->exists());

            $created->push(static::create([
                'serial' => $serial,
                'token'  => $token,
                'batch'  => $batch,
            ]));
        }

        return $created;
    }
}