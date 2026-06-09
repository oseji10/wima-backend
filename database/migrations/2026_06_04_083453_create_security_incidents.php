<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            // bandit_attack | terrorism | community_unrest | theft_vandalism | equipment_accident | health_safety | other
            $table->string('type', 30)->index();
            $table->string('severity', 12)->default('medium')->index(); // low|medium|high|critical
            $table->dateTime('occurred_at')->index();

            // Location
            $table->unsignedBigInteger('state')->nullable()->index(); // stateId
            $table->unsignedBigInteger('lga')->nullable()->index();   // community / lgaId
            $table->unsignedBigInteger('hub')->nullable()->index();   // hubId
            $table->string('location_note')->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            // Affected persons / assets
            $table->text('affected_persons')->nullable();
            $table->unsignedInteger('affected_persons_count')->default(0);
            $table->text('affected_assets')->nullable();
            $table->unsignedBigInteger('equipment_id')->nullable()->index(); // optional link to equipment

            // open | under_investigation | resolved | closed
            $table->string('status', 20)->default('open')->index();

            // Response / escalation / resolution
            $table->unsignedBigInteger('assigned_to')->nullable()->index(); // users.id
            $table->string('assigned_team')->nullable();
            $table->unsignedInteger('escalation_level')->default(0);
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->unsignedBigInteger('reported_by')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};