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
        Schema::create('msps', function (Blueprint $table) {
            $table->id();
            $table->string('mspId')->unique();
            $table->unsignedBigInteger('hub')->nullable();
            $table->string('address')->nullable();
            $table->string('alternatePhoneNumber')->nullable();
            $table->unsignedBigInteger('userId')->nullable();
            $table->unsignedBigInteger('addedBy')->nullable();
            $table->string('status')->default('active');
            

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('addedBy')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('hub')->references('hubId')->on('hubs')->onDelete('cascade');
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
