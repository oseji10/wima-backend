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
            $table->string('firstName');
            $table->string('lastName');
            $table->string('email')->unique();
            $table->string('phoneNumber');
            $table->string('profession');
            $table->text('message')->nullable();
            $table->string('equipmentProof')->nullable();
            $table->string('studentProof')->nullable();
            $table->string('companyDetails')->nullable();
            $table->text('companyMission')->nullable();
            $table->text('operatorExperience')->nullable();
            $table->string('skillsAssessment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};