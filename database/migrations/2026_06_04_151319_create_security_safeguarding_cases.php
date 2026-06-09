<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stored SEPARATELY from general incidents. Access is gated to safeguarding
// officers in the controller; treat every column here as confidential.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_safeguarding_cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            // harassment | abuse | intimidation | discrimination | other
            $table->string('category', 20)->index();
            $table->string('severity', 12)->default('medium');
            $table->dateTime('occurred_at')->nullable();

            // Location is optional — captured only where it does not risk identifying the survivor
            $table->unsignedBigInteger('state')->nullable();
            $table->unsignedBigInteger('lga')->nullable();
            $table->unsignedBigInteger('hub')->nullable();

            $table->boolean('is_anonymous')->default(false);
            $table->string('survivor_ref')->nullable();   // non-identifying code
            $table->text('survivor_details')->nullable();  // confidential, optional
            $table->text('description')->nullable();
            $table->text('immediate_needs')->nullable();
            $table->boolean('consent_to_share')->default(false);

            // reported | under_review | support_provided | referred | resolved | closed
            $table->string('status', 20)->default('reported')->index();
            $table->unsignedBigInteger('assigned_officer_id')->nullable()->index();

            $table->unsignedBigInteger('reported_by')->nullable(); // null if anonymous
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_safeguarding_cases');
    }
};