<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipmentId')->index();

            // inspection | certification | safety_check | warranty
            // | insurance | calibration | service
            $table->string('log_type', 30);
            $table->string('title');

            // compliant | due | overdue | expired | na
            $table->string('status', 20)->default('compliant');

            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable()->index();

            $table->string('authority')->nullable();      // issuing body
            $table->string('document_ref')->nullable();    // cert no / file ref

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_logs');
    }
};