<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('msps', function (Blueprint $table) {
    $table->tinyInteger('cac_approved_name')->nullable()->after('cac_status'); // 1, 2, or 3
    $table->text('cac_admin_note')->nullable()->after('cac_approved_name');
    $table->timestamp('cac_reviewed_at')->nullable()->after('cac_admin_note');
    $table->unsignedBigInteger('cac_reviewed_by')->nullable()->after('cac_reviewed_at');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('msps', function (Blueprint $table) {
            //
        });
    }
};
