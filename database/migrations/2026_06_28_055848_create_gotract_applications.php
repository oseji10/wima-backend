<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gotract_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique();

            // Section 1 — Personal information
            $table->string('full_name');
            $table->string('gender');
            $table->date('date_of_birth');
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->string('state')->default('Gombe');
            $table->string('lga');
            $table->string('village');

            // Section 2 — Identification & banking
            $table->string('national_id')->unique();
            $table->string('bvn')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->boolean('has_disability')->default(false);
            $table->string('disability_type')->nullable();
            $table->string('disability_other')->nullable();

            // Section 3 — Demographic & economic
            $table->string('marital_status');
            $table->string('primary_occupation');
            $table->string('crops_farmed')->nullable();
            $table->unsignedSmallInteger('household_size')->nullable();
            $table->unsignedSmallInteger('dependents')->nullable();
            $table->decimal('land_area', 8, 2)->nullable();
            $table->string('land_ownership')->nullable();

            // Section 4 — Mechanization & financial
            $table->boolean('in_cooperative')->default(false);
            $table->string('cooperative_name')->nullable();
            $table->boolean('prior_mech_experience')->default(false);
            $table->json('preferred_services')->nullable();
            $table->boolean('currently_employed')->default(false);
            $table->boolean('willing_repayment')->default(false);
            $table->boolean('access_to_credit')->default(false);

            // Section 5 — Training & consent
            $table->json('training_areas')->nullable();
            $table->string('training_other')->nullable();
            $table->boolean('consent')->default(false);
            $table->string('signature');

            // Workflow / audit
            $table->string('status')->default('pending');
            $table->string('ip_address')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('lga');
            $table->index('status');
            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gotract_applications');
    }
};