<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeForm extends Model
{
    protected $table = 'me_forms';

    protected $fillable = ['name', 'code', 'description', 'active', 'version', 'created_by'];

    protected $casts = ['active' => 'boolean', 'version' => 'integer'];

    public function fields()
    {
        return $this->hasMany(MeFormField::class, 'form_id', 'id')->orderBy('sort_order');
    }

    public function submissions()
    {
        return $this->hasMany(MeSubmission::class, 'form_id', 'id');
    }
}