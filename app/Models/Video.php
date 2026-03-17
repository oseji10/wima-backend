<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    public $table = 'videos';
    protected $fillable = [
        'title',
        'src',
        'thumbnail',
        'category',
        'duration',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}