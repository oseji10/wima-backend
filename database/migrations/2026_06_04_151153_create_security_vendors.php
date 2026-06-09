<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('private')->index(); // public | private
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email')->nullable();
            $table->text('service_scope')->nullable();
            $table->string('status', 20)->default('active')->index(); // active | inactive
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_vendors');
    }
};