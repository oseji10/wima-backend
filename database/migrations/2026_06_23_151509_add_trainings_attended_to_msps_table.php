<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msps', function (Blueprint $table) {
        $table->json('trainings_attended')->nullable();
        $table->integer('ageBracket')->nullable();
        $table->string('type')->nullable();
        $table->string('year')->nullable();
        $table->string('code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};