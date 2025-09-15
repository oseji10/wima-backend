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
        // Drop any existing foreign key constraints that reference the transactions table
        try {
            \DB::statement('ALTER TABLE transaction_list DROP FOREIGN KEY transaction_list_transactionreference_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist, ignore the error
        }
        
        try {
            \DB::statement('ALTER TABLE transaction_commodities DROP FOREIGN KEY transaction_commodities_transactionreference_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist, ignore the error
        }
        
        // Create the transactions table without foreign key constraints first
        Schema::create('transactions', function (Blueprint $table) {
            $table->id('transactionId');
            $table->string('msp')->nullable();
            $table->string('farmer')->nullable();
            $table->string('transactionType')->nullable();
            $table->string('totalCost')->nullable();
            $table->string('transactionStatus')->nullable();
            $table->string('transactionReference')->unique();
            $table->string('paymentMethod')->nullable();
            $table->unsignedBigInteger('hub')->nullable();
            $table->unsignedBigInteger('project')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        
        // Add foreign key constraints only if the referenced tables exist
        
        // Add foreign key to hubs table (should exist by now)
        if (Schema::hasTable('hubs')) {
            try {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreign('hub')->references('hubId')->on('hubs')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Constraint might already exist, ignore the error
            }
        }
        
        // Add foreign key to projects table (should exist by now) 
        if (Schema::hasTable('projects')) {
            try {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreign('project')->references('projectId')->on('projects')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Constraint might already exist, ignore the error
            }
        }
        
        // Re-add the foreign key constraint from transaction_list to transactions
        if (Schema::hasTable('transaction_list')) {
            try {
                Schema::table('transaction_list', function (Blueprint $table) {
                    $table->foreign('transactionReference')->references('transactionReference')->on('transactions')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Constraint might already exist, ignore the error
            }
        }
        
        // Re-add the foreign key constraint from transaction_commodities to transactions
        if (Schema::hasTable('transaction_commodities')) {
            try {
                Schema::table('transaction_commodities', function (Blueprint $table) {
                    $table->foreign('transactionReference')->references('transactionReference')->on('transactions')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Constraint might already exist, ignore the error
            }
        }
        
        // Note: Foreign keys for 'msp' and 'farmer' will be added by their respective table migrations
        // when those tables are created, since they come later in the migration order
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
