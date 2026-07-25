<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MSPs extends Model
{
    use HasFactory;

    public $table = 'msps';
    protected $fillable = [
        'mspId',
        'firstName',
        'lastName',
        'otherNames',
        'hub',
        'gender',
        'address',
        'addedBy',
        'project',
        'userId',
        'trainings_attended',
        'ageBracket',
        'type',
        'year',
        'code',
        // --- added for CAC onboarding ---
        'state',
        'lga',
        'age',
        'dateOfBirth',
        'alternatePhoneNumber',
        'nin',
        'cac_cohort',
        'cac_valid_id_type',
        'cac_valid_id_path',
        'cac_passport_path',
        'cac_signature_path',
        'cac_business_address',
        'cac_business_name_1',
        'cac_business_name_2',
        'cac_business_name_3',
        'cac_submitted_at',
        'cac_status',
        'cac_approved_name',
        'cac_admin_note',
        'cac_reviewed_at',
        'cac_reviewed_by'
    ];
    // protected $primaryKey = 'mspId';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'trainings_attended' => 'array',
        'ageBracket' => 'integer',
        'age' => 'integer',
        'dateOfBirth' => 'date',
        'cac_submitted_at' => 'datetime',
    ];

    public function states()
    {
        return $this->belongsTo(State::class, 'state', 'stateId');
    }

    public function lgas()
    {
        return $this->belongsTo(Lgas::class, 'lga', 'lgaId');
    }

    public function hub()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function projects()
    {
        return $this->belongsTo(Project::class, 'project', 'projectId');
    }

    public function hubs()
    {
        return $this->belongsTo(Hubs::class, 'hub', 'hubId');
    }

    // Generate MSP ID
    public static function generateMspId()
    {
        $latest = self::orderBy('id', 'desc')->first();
        $number = $latest ? intval(substr($latest->mspId, 3)) + 1 : 1;
        return 'MSP' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}