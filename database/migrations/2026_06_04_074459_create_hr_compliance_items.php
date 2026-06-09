<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_compliance_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            // certification | contract | training | document | background_check | medical
            $table->string('type', 30)->default('document');
            $table->string('title');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->string('authority')->nullable();
            $table->string('document_ref')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_compliance_items');
    }
};