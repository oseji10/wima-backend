<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('reviewer_id')->nullable()->index(); // hr_staff or null
            $table->string('period_label')->nullable();   // e.g. "Q2 2025"
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            // draft | in_progress | completed | acknowledged
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('overall_score', 6, 2)->nullable(); // 0-100 weighted
            $table->text('summary')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_performance_reviews');
    }
};