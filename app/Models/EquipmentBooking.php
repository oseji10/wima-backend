<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentBooking extends Model
{
    use HasFactory;

    public $table = 'equipment_bookings';
    protected $fillable = [
        'bookingId',
        'transactionId',
        'equipmentId',
        'bookingDate',
        'status',
    ];
    // protected $primaryKey = 'bookingId';

     public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipmentId', 'equipmentId');
    }

        public function transaction()
        {
            return $this->belongsTo(Transactions::class, 'transactionId', 'transactionId');
        }
}
