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
        // Drop any existing foreign key constraints that reference the msps table
        try {
            \DB::statement('ALTER TABLE farmers DROP FOREIGN KEY farmers_msp_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist, ignore the error
        }
        
        try {
            \DB::statement('ALTER TABLE transactions DROP FOREIGN KEY transactions_msp_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist, ignore the error
        }
        
        // Create the msps table only if it doesn't exist
        if (!Schema::hasTable('msps')) {
            Schema::create('msps', function (Blueprint $table) {
                $table->id();
                $table->string('mspId')->unique();
                $table->unsignedBigInteger('hub')->nullable();
                $table->string('address')->nullable();
                $table->string('alternatePhoneNumber')->nullable();
                $table->string('gender')->nullable();
                $table->unsignedBigInteger('userId')->nullable();
                $table->unsignedBigInteger('addedBy')->nullable();
                $table->unsignedBigInteger('project')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        
        // Populate the msps table with existing MSP IDs from farmers and transactions tables
        // Only populate if the table is empty
        if (\DB::table('msps')->count() == 0) {
            $existingMspIds = collect();
            
            // Get MSP IDs from farmers table
            $farmerMsps = \DB::table('farmers')
                ->whereNotNull('msp')
                ->distinct()
                ->pluck('msp');
            $existingMspIds = $existingMspIds->merge($farmerMsps);
            
            // Get MSP IDs from transactions table
            $transactionMsps = \DB::table('transactions')
                ->whereNotNull('msp')
                ->distinct()
                ->pluck('msp');
            $existingMspIds = $existingMspIds->merge($transactionMsps);
            
            // Insert unique MSP IDs into the msps table
            $uniqueMspIds = $existingMspIds->unique()->values();
            foreach ($uniqueMspIds as $mspId) {
                \DB::table('msps')->insert([
                    'mspId' => $mspId,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        // Add foreign key constraints for the msps table (with error handling)
        try {
            Schema::table('msps', function (Blueprint $table) {
                $table->foreign('userId')->references('id')->on('users')->onDelete('set null');
                $table->foreign('addedBy')->references('id')->on('users')->onDelete('set null');
                $table->foreign('hub')->references('hubId')->on('hubs')->onDelete('set null');
                $table->foreign('project')->references('projectId')->on('projects')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Constraints might already exist, ignore the error
        }
        
        // Re-add the foreign key constraints that reference msps (with error handling)
        try {
            Schema::table('farmers', function (Blueprint $table) {
                $table->foreign('msp')->references('mspId')->on('msps')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Constraint might already exist, ignore the error
        }
        
        try {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('msp')->references('mspId')->on('msps')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Constraint might already exist, ignore the error
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msps');
    }
};
