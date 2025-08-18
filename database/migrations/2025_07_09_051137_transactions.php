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
            $table->string('farmer')->nullable();
            $table->string('transactionType')->nullable();
            $table->string('totalCost')->nullable();
            $table->string('transactionStatus')->nullable();
            $table->string('transactionReference')->unique();
            $table->string('paymentMethod')->nullable();
            $table->unsignedBigInteger('hub')->nullable();


            $table->timestamps();
            $table->softDeletes();

            $table->foreign('msp')->references('mspId')->on('msps')->onDelete('cascade');
            $table->foreign('hub')->references('hubId')->on('hubs')->onDelete('cascade');
            $table->foreign('farmer')->references('farmerId')->on('farmers')->onDelete('cascade');
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
