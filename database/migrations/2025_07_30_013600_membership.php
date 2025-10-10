<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->string('membershipType');
            $table->string('fullName');
            $table->date('dateOfBirth')->nullable();
            $table->string('gender')->nullable();
            $table->string('maritalStatus')->nullable();
            $table->string('nationality')->nullable();
            $table->text('homeAddress')->nullable();
            $table->string('state')->nullable();
            $table->string('lga')->nullable();
            $table->string('wardDistrict')->nullable();
            $table->string('community')->nullable();
            $table->string('phoneNumber');
            $table->string('email')->unique();
            $table->string('occupation');
            $table->string('organization')->nullable();
            $table->string('positionTitle')->nullable();
            $table->string('areaOfExpertise')->nullable();
            $table->text('reasonForJoining')->nullable();
            $table->string('preferredCommunication')->nullable();
            $table->string('meansOfIdentification')->nullable();
            $table->string('meansOfIdentificationType')->nullable();
            $table->string('cacDocument')->nullable();
            $table->string('companyDetails')->nullable();
            $table->text('companyMission')->nullable();
            $table->text('operatorExperience')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->unsignedBigInteger('treatedBy')->nullable();
            $table->foreign('treatedBy')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};