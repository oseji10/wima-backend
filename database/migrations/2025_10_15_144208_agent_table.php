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
          Schema::create('agents', function (Blueprint $table) {
                    $table->id();
                    $table->string('agentId')->nullable();
                    $table->string('agentName')->nullable();
                    $table->string('phoneNumber')->nullable();
                    $table->string('email')->nullable();
                    $table->unsignedBigInteger('hub')->nullable();
                    $table->string('status')->default('active');
                    $table->timestamps();

                    $table->foreign('hub')->references('hubId')->on('hubs')->onDelete('cascade');
                });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
    }
};
