<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    public $table = 'services';
    protected $primaryKey = 'serviceId';
    protected $fillable = [
        'serviceName',
        'measuringUnit',
        'costPerUnit',
        'cost',
        'addedBy',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(ServicesCategory::class, 'serviceCategoryId', 'categoryId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy', 'id');
    }

    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }
}
