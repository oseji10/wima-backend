<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msps', function (Blueprint $table) {
            // Age is still stored (for reporting), but we also keep the date of
            // birth so the form can prefill the date field for returning MSPs.
            if (! Schema::hasColumn('msps', 'dateOfBirth')) {
                $table->date('dateOfBirth')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('msps', function (Blueprint $table) {
            if (Schema::hasColumn('msps', 'dateOfBirth')) {
                $table->dropColumn('dateOfBirth');
            }
        });
    }
};