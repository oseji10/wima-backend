<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicesCategory extends Model
{
    public $table = 'service_categories';
    protected $primaryKey = 'categoryId';
    protected $fillable = [
        'categoryName',
        'status',
    ];
}
