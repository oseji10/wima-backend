<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_indicator_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('indicator_id')->index();
            $table->unsignedBigInteger('hub')->nullable()->index();
            $table->date('period_date')->index();      // first day of the period
            $table->decimal('value', 18, 4)->default(0);
            // manual | auto (cached from computation)
            $table->string('source', 10)->default('manual');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['indicator_id', 'hub', 'period_date'], 'me_val_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_indicator_values');
    }
};