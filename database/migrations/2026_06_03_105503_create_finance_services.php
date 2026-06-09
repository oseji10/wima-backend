<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('default_unit_cost', 14, 2)->default(0);
            $table->unsignedInteger('default_target')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_services');
    }
};