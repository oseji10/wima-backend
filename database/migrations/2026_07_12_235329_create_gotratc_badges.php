<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A pool of pre-printed, anonymous QR badges. Each carries a human-readable
        // serial (printed under the QR) and a random token (encoded IN the QR).
        // A badge means nothing until it is assigned to a participant at the desk.
        Schema::create('gotract_badges', function (Blueprint $table) {
            $table->id();
            $table->string('serial')->unique();   // e.g. GT-0001  (printed, typed at desk)
            $table->string('token')->unique();    // encoded in the QR
            $table->string('batch')->nullable();  // print run, e.g. 2026-07-12
            $table->foreignId('application_id')->nullable()
                ->constrained('gotract_applications')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->string('assigned_by')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gotract_badges');
    }
};