<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipmentId')->index();

            $table->string('reference', 40)->unique();

            // breakdown | incident | fault | accident
            $table->string('type', 20)->default('breakdown');
            // low | medium | high | critical
            $table->string('severity', 12)->default('medium');

            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedBigInteger('reported_by')->nullable()->index();
            $table->timestamp('reported_at')->nullable();

            // open | acknowledged | in_progress | resolved | closed
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable()->index();

            $table->decimal('downtime_hours', 8, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();

            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_incidents');
    }
};