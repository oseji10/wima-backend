<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();          // referenced in computed formulas
            $table->text('description')->nullable();

            $table->string('unit', 30)->nullable();        // count | % | people | ha | NGN ...
            // output | outcome | impact (or any program-defined level)
            $table->string('level', 30)->default('output');
            $table->unsignedBigInteger('project_id')->nullable()->index(); // FK -> projects.projectId
            $table->string('program')->nullable()->index(); // project name snapshot (display / grouping)

            // How the value is produced
            // form | manual | computed
            $table->string('source_type', 20)->default('form');

            // form source
            $table->unsignedBigInteger('form_id')->nullable()->index();
            $table->string('field_key', 60)->nullable();
            // sum | average | count | latest | ratio
            $table->string('aggregation', 20)->default('sum');
            $table->string('numerator_field', 60)->nullable();   // for ratio
            $table->string('denominator_field', 60)->nullable(); // for ratio

            // computed source: arithmetic over other indicator codes, e.g. "(reach / target) * 100"
            $table->text('formula')->nullable();

            // Targets
            $table->decimal('baseline', 18, 4)->nullable();
            $table->decimal('target', 18, 4)->nullable();
            // increase = higher is better; decrease = lower is better
            $table->string('direction', 10)->default('increase');
            // monthly | quarterly | annual
            $table->string('frequency', 20)->default('monthly');

            $table->boolean('is_donor_visible')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_indicators');
    }
};