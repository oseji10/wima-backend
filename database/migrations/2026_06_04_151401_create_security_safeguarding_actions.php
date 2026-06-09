<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_safeguarding_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            // response | escalation | referral | note | status_change | resolution
            $table->string('action_type', 20)->default('note');
            $table->text('description');
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->dateTime('action_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_safeguarding_actions');
    }
};