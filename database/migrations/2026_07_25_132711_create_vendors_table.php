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
        Schema::create('vendors', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('contact_person')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->text('address')->nullable();
    $table->string('category')->nullable(); // e.g. "IT Equipment", "Logistics"
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::table('requests', function (Blueprint $table) {
    $table->dropColumn(['preferred_vendor', 'alternative_vendor']);
    $table->foreignId('vendor_id')->nullable()->after('justification')->constrained('vendors')->nullOnDelete();
    $table->foreignId('alternative_vendor_id')->nullable()->after('vendor_id')->constrained('vendors')->nullOnDelete();
    $table->string('payment_reference')->nullable();
    $table->decimal('paid_amount', 15, 2)->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->unsignedBigInteger('paid_by')->nullable();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
