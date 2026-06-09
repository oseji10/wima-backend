<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->unsignedBigInteger('hub')->nullable()->index();

            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();

            $table->date('issue_date');
            $table->date('due_date')->nullable()->index();

            // draft | sent | partial | paid | overdue | void
            $table->string('status', 20)->default('draft')->index();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_pct', 6, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_invoices');
    }
};