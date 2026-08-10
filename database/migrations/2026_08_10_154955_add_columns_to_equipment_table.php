<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gotract_equipment', function (Blueprint $table) {
            if (!Schema::hasColumn('gotract_equipment', 'category')) {
                $table->string('category')->nullable()->after('type')->default('Other');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gotract_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('gotract_equipment', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};