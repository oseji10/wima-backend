<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('leave_type_id')->index();
            $table->unsignedInteger('year');
            $table->decimal('days_allocated', 6, 2)->default(0); // overrides type default
            $table->timestamps();

            $table->unique(['staff_id', 'leave_type_id', 'year'], 'hr_alloc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_allocations');
    }
};