<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_utilization_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipmentId')->index();

            $table->date('log_date');

            // Hours the asset was actively used in the period
            $table->decimal('hours_used', 8, 2)->default(0);
            // Hours the asset was scheduled/available in the period
            $table->decimal('hours_available', 8, 2)->default(0);
            // Hours lost to faults/maintenance in the period
            $table->decimal('downtime_hours', 8, 2)->default(0);

            // Optional production output (e.g. 12 hectares, 40 jobs)
            $table->decimal('output_units', 12, 2)->nullable();
            $table->string('output_unit_label', 40)->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['equipmentId', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_utilization_logs');
    }
};