<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incident_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_id')->index();
            // response | escalation | note | assignment | status_change | resolution
            $table->string('action_type', 20)->default('note');
            $table->text('description');
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable()->index();
            $table->dateTime('action_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incident_actions');
    }
};