<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_funding_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // donor | grant | investment
            $table->string('type', 20)->default('donor')->index();
            $table->string('organization')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('currency', 8)->default('NGN');

            $table->decimal('total_committed', 16, 2)->default(0);

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable()->index();

            // pledged | active | closed
            $table->string('status', 20)->default('active')->index();

            // For grants/investments: restricted purpose, equity %, expected return
            $table->string('purpose')->nullable();
            $table->decimal('equity_pct', 6, 3)->nullable();
            $table->decimal('expected_return_pct', 6, 3)->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_funding_sources');
    }
};