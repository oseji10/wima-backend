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
        Schema::create('farmers', function (Blueprint $table) {
            $table->id('farmerId');
            $table->string('farmerFirstName')->nullable();
            $table->string('farmerLastName')->nullable();
            $table->string('farmerOtherNames')->nullable();
            $table->string('phoneNumber')->nullable();
            $table->string('alternatePhoneNumber')->nullable();
            $table->string('gender')->nullable();
            $table->string('maritalStatus')->nullable();
            $table->string('ageBracket')->nullable();
            $table->string('isDisabled')->nullable();
            $table->string('disabilityDescription')->nullable();
            $table->string('status')->default('inactive');
            
            
            $table->timestamps();
            $table->softDeletes();
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
