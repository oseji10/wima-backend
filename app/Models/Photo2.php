<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photo2 extends Model
{
    public $table = 'photos';

    protected $fillable = [
        'title',
        'src',
        'category',
        'instagram_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}