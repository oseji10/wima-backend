<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    use HasFactory;

    public $table = 'product_type';
    protected $fillable = [
        'typeId',
        'typeName',
        
    ];
    protected $primaryKey = 'typeId';

    protected $hidden = ['created_at', 'updated_at'];
}
