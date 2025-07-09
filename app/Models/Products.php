<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;

    public $table = 'products';
    protected $fillable = [
        'productId',
        'productName',
        'productType',
        'cost',
        'addedBy',
        'status',
    ];
    protected $primaryKey = 'productId';

    public function product_type()
    {
        return $this->belongsTo(ProductType::class, 'productType', 'typeId'); // Assuming productType is the foreign key
    }
    public function added_by()
    {
        return $this->belongsTo(User::class, 'addedBy', 'id'); // Assuming addedBy is the foreign key
    }

    public function product_images()
    {
        return $this->belongsTo(ProductImage::class, 'productId', 'productId'); // Assuming productId is the foreign key in ProductImage
    }

    protected $hidden = ['deleted_at'];
   
}
