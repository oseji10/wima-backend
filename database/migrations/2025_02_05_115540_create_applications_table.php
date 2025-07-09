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
        // First create the table without foreign keys
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('applicationId')->unique();
            $table->string('jambId')->nullable();
            // $table->string('lastName')->nullable();
            $table->string('dateOfBirth')->nullable();
            $table->string('gender')->nullable();
            $table->string('alternatePhoneNumber')->nullable();
            // $table->string('email')->nullable();
            $table->string('licenceId')->nullable();
            $table->unsignedBigInteger('batch')->nullable();
            $table->unsignedBigInteger('applicationType')->nullable();
            $table->unsignedBigInteger('userId')->nullable();
            
            $table->string('isActive')->default('true');
            $table->string('slipPrintCount')->default('0');
            $table->string('admissionPrintCount')->default('0');
            $table->string('isPresent')->default('false');
            $table->string('status')->default('account_created');

            $table->timestamps();
            $table->softDeletes();  
        });
        
        // Foreign keys will be added manually after table creation
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
