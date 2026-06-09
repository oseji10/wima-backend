<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_revenue_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub')->nullable()->index();      // hubId
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('service_name');                              // snapshot label

            // The variables that drive every downstream figure
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->unsignedInteger('target')->default(0);
            $table->unsignedInteger('quantity')->default(0);

            $table->date('entry_date')->index();
            $table->unsignedBigInteger('scheme_id')->nullable()->index(); // scheme used (null = active)
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_revenue_entries');
    }
};