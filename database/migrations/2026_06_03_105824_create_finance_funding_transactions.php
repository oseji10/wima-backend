<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_funding_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('funding_source_id')->index();
            // pledge | disbursement | expense | return_payout
            $table->string('type', 20)->default('disbursement');
            $table->decimal('amount', 16, 2);
            $table->date('transaction_date')->index();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_funding_transactions');
    }
};