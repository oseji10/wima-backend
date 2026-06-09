<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipmentId')->index();

            $table->string('title');
            // preventive | inspection | calibration | service | safety_check
            $table->string('maintenance_type', 30)->default('preventive');

            // days | weeks | months | usage_hours
            $table->string('frequency_type', 20)->default('months');
            $table->unsignedInteger('frequency_value')->default(1);

            $table->date('last_serviced_at')->nullable();
            $table->date('next_due_at')->index();

            $table->unsignedBigInteger('assigned_to')->nullable()->index();

            // active | paused | completed
            $table->string('status', 20)->default('active');

            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};