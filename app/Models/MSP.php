<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    use HasFactory;

    public $table = 'msps';
    protected $fillable = [
        'mspId',
        'mspName',
        'projectId',
        'userId',
    ];
    protected $primaryKey = 'mspId';

      public function projects()
    {
        return $this->belongsTo(Project::class, 'projectId', 'projectId');
    } 


}
