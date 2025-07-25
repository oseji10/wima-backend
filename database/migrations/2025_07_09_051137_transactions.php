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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id('transactionId');
            $table->string('msp')->nullable();
            $table->string('transactionType')->nullable();
            $table->string('totalCost')->nullable();
            $table->string('transactionStatus')->nullable();
            $table->string('transactionReference')->nullable();
            $table->unsignedBigInteger('lga')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('msp')->references('mspId')->on('msps')->onDelete('cascade');
            $table->foreign('lga')->references('lgaId')->on('lgas')->onDelete('cascade');
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
