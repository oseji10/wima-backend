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
        Schema::create('active_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stateId')->nullable();
            $table->string('status')->default('active');
           
            $table->timestamps();
            $table->foreign('stateId')->references('stateId')->on('states')->onDelete('cascade');
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
