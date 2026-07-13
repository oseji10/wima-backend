<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gotract_applications', function (Blueprint $table) {
            // Age is still stored (for reporting), but we also keep the date of
            // birth so the form can prefill the date field for returning MSPs.
            if (! Schema::hasColumn('gotract_applications', 'accredited_at')) {
                $table->date('accredited_at')->nullable();
            }
            $table->unsignedBigInteger('accredited_by')->nullable();
            $table->foreign('accredited_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('gotract_applications', function (Blueprint $table) {
            if (Schema::hasColumn('gotract_applications', 'accredited_at')) {
                $table->dropColumn('accredited_at');
            }
            if (Schema::hasColumn('gotract_applications', 'accredited_by')) {
                $table->dropColumn('accredited_by');
            }
        });
    }
};