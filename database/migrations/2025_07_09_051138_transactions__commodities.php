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
        Schema::create('transaction_commodities', function (Blueprint $table) {
            $table->id('transactionCommodityId');
            $table->string('transactionReference')->nullable();
            $table->unsignedBigInteger('commodityId')->nullable();
            
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('transactionReference')->references('transactionReference')->on('transactions')->onDelete('cascade');
            $table->foreign('commodityId')->references('commodityId')->on('commodities')->onDelete('cascade');

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
