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
        Schema::create('subhubs', function (Blueprint $table) {
            $table->id('subHubId');
            $table->unsignedBigInteger('hubId')->nullable();
             $table->string('subHubName')->nullable();
            $table->unsignedBigInteger('addedBy')->nullable();
            $table->string('status')->default('active');
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('hubId')->references('hubId')->on('hubs')->onDelete('cascade');
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
