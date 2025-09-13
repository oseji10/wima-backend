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
         Schema::create('state_coordinators', function (Blueprint $table) {
            $table->id('coordinatorId');
            $table->unsignedBigInteger('userId')->nullable();
            $table->unsignedBigInteger('stateId')->nullable();
            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('stateId')->references('stateId')->on('states')->onDelete('cascade');
            $table->string('status')->default('active');
           
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
    }
};
