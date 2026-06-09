<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_sharing_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default Scheme');
            $table->boolean('is_active')->default(true)->index();

            // Top-level split (of each line's gross total)
            $table->decimal('wima_pct', 6, 3)->default(80);
            $table->decimal('state_pct', 6, 3)->default(20);

            // Breakdown of the STATE portion
            $table->decimal('sb_wima_pct', 6, 3)->default(10);
            $table->decimal('sb_community_dev_pct', 6, 3)->default(2);
            $table->decimal('sb_state_coord_pct', 6, 3)->default(1);
            $table->decimal('sb_cl_pct', 6, 3)->default(2);
            $table->decimal('sb_subcl_pct', 6, 3)->default(5);
            $table->decimal('sb_msp_pct', 6, 3)->default(80);

            // MSP distribution + period projection multipliers
            $table->unsignedInteger('msp_groups')->default(10);
            $table->unsignedInteger('msp_per_group')->default(7);
            $table->decimal('weekly_multiplier', 6, 2)->default(5);
            $table->decimal('monthly_multiplier', 6, 2)->default(4);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_sharing_schemes');
    }
};