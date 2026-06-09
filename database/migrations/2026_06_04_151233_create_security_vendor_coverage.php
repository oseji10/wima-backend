<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row = one coverage area for a vendor. Nulls widen the scope
        // (e.g. state set, lga null = whole state).
        Schema::create('security_vendor_coverage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->unsignedBigInteger('state')->nullable()->index();
            $table->unsignedBigInteger('lga')->nullable()->index();
            $table->unsignedBigInteger('hub')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_vendor_coverage');
    }
};