<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msps', function (Blueprint $table) {
            // NIN may already exist on your msps table — only add if missing.
            if (! Schema::hasColumn('msps', 'nin')) {
                $table->string('nin')->nullable();
            }

            // CAC onboarding fields (prefixed to avoid clashing with existing columns).
            $table->string('cac_cohort')->nullable();            // Year 1 | Year 2
            $table->string('cac_valid_id_type')->nullable();     // passport | drivers_license | voters_card | national_id
            $table->string('cac_valid_id_path')->nullable();     // uploaded ID document
            $table->string('cac_passport_path')->nullable();     // passport photo
            $table->string('cac_signature_path')->nullable();    // scanned signature
            $table->text('cac_business_address')->nullable();
            $table->string('cac_business_name_1')->nullable();
            $table->string('cac_business_name_2')->nullable();
            $table->string('cac_business_name_3')->nullable();
            $table->timestamp('cac_submitted_at')->nullable();
            $table->string('cac_status')->nullable();            // pending | submitted | registered
        });
    }

    public function down(): void
    {
        Schema::table('msps', function (Blueprint $table) {
            $table->dropColumn([
                'cac_cohort', 'cac_valid_id_type', 'cac_valid_id_path', 'cac_passport_path', 'cac_signature_path',
                'cac_business_address', 'cac_business_name_1', 'cac_business_name_2', 'cac_business_name_3',
                'cac_submitted_at', 'cac_status',
            ]);
            // Note: we intentionally do NOT drop `nin` on rollback, since it may
            // have pre-existed. Drop it manually if this migration created it.
        });
    }
};