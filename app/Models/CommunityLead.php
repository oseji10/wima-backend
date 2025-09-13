<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityLead extends Model
{
    use HasFactory;
    public $table = 'community_leads';
    protected $primaryKey = 'leadId';
    protected $fillable = ['lga', 'status', 'userId'];

    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

      public function users()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function lga_info()
    {
        return $this->belongsTo(Lgas::class, 'lga', 'lgaId');
    }
}
