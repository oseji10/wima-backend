<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // job role / position
            $table->string('department')->nullable()->index();
            $table->text('description')->nullable();
            $table->text('responsibilities')->nullable();
            // entry | officer | manager | lead | executive
            $table->string('level', 20)->default('officer');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_roles');
    }
};