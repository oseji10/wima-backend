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
        Schema::create('hubs', function (Blueprint $table) {
            $table->id('hubId');
            $table->unsignedBigInteger('state')->nullable();
            $table->unsignedBigInteger('lga')->nullable();
            $table->unsignedBigInteger('addedBy')->nullable();
            $table->string('status')->default('active');
            
            $table->timestamps();
            $table->softDeletes();

             $table->foreign('state')->references('stateId')->on('states')->onDelete('cascade');
             $table->foreign('lga')->references('lgaId')->on('lgas')->onDelete('cascade');
             $table->foreign('addedBy')->references('id')->on('users')->onDelete('cascade');
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
