<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('category');
            $table->string('account_name')->nullable()->after('bank_name');
            $table->string('account_number')->nullable()->after('account_name');
            $table->string('bank_sort_code')->nullable()->after('account_number'); // optional, if you use sort/routing codes
            $table->string('tin')->nullable()->after('bank_sort_code'); // tax identification number, often required for payment/finance sign-off
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_name', 'account_number', 'bank_sort_code', 'tin']);
        });
    }
};