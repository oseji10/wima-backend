<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OlevelResult extends Model
{
    use HasFactory;
    public $table = 'olevel_results';
    protected $primaryKey = 'resultId';
    protected $fillable = [
        'examYear',
        'examType',
        'subject',
        'grade',
        'applicationId',
    ];
}
