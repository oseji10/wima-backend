<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();          // slug used by indicators
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_forms');
    }
};