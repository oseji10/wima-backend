<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
        $table->json('mechanized_services')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};