<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('equipment_bookings', function (Blueprint $table) {
    $table->id('bookingId');
    $table->unsignedBigInteger('transactionId')->nullable();
    $table->unsignedBigInteger('equipmentId')->nullable();
    $table->date('bookingDate')->nullable();
    $table->enum('status', ['reserved', 'booked', 'completed', 'cancelled'])->default('reserved');
    $table->timestamps();
    
    $table->foreign('equipmentId')->references('equipmentId')->on('equipment')->onDelete('set null');
    $table->foreign('transactionId')->references('transactionId')->on('transactions')->onDelete('set null');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
