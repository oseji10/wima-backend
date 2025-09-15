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
        Schema::table('farmers', function (Blueprint $table) {
            // Add project column if it doesn't exist
            if (!Schema::hasColumn('farmers', 'project')) {
                $table->unsignedBigInteger('project')->nullable();
            }
        });
        
        // Add foreign key constraints with error handling for existing constraints
        try {
            Schema::table('farmers', function (Blueprint $table) {
                $table->foreign('hub')->references('hubId')->on('hubs')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Hub foreign key already exists, ignore
        }
        
        try {
            Schema::table('farmers', function (Blueprint $table) {
                $table->foreign('msp')->references('mspId')->on('msps')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // MSP foreign key already exists, ignore
        }
        
        try {
            Schema::table('farmers', function (Blueprint $table) {
                $table->foreign('project')->references('projectId')->on('projects')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Project foreign key might not be possible due to missing column or other issues
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            // Drop foreign key constraints with error handling
            try {
                $table->dropForeign(['hub']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            
            try {
                $table->dropForeign(['msp']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            
            try {
                $table->dropForeign(['project']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            
            // Drop project column if it exists
            if (Schema::hasColumn('farmers', 'project')) {
                $table->dropColumn('project');
            }
        });
    }
};
