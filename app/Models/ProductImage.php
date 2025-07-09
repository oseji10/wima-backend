<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $table = 'product_images';
    protected $fillable = [
        'imageId',
        'productId',
        'imagePath',
        'isPrimary',
    ];
    protected $primaryKey = 'imageId';

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId', 'productId');
    }
}
