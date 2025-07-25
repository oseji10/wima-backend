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
        Schema::create('transaction_list', function (Blueprint $table) {
            $table->id('transactionListId');
            $table->unsignedBigInteger('transactionId')->nullable();
            $table->unsignedBigInteger('serviceId')->nullable();
            $table->string('quantity')->nullable();
            $table->string('unitCost')->nullable();
            
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('transactionId')->references('transactionId')->on('transactions')->onDelete('cascade');
            $table->foreign('serviceId')->references('serviceId')->on('services')->onDelete('cascade');

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
