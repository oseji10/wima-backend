<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            CREATE TABLE farmers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                farmerId VARCHAR(255) NOT NULL UNIQUE,
                farmerFirstName VARCHAR(255) NULL,
                farmerLastName VARCHAR(255) NULL,
                farmerOtherNames VARCHAR(255) NULL,
                phoneNumber VARCHAR(255) NULL,
                alternatePhoneNumber VARCHAR(255) NULL,
                gender VARCHAR(255) NULL,
                maritalStatus VARCHAR(255) NULL,
                ageBracket VARCHAR(255) NULL,
                hub BIGINT UNSIGNED NULL,
                msp VARCHAR(255) NULL,
                isDisabled VARCHAR(255) NULL,
                disabilityDescription VARCHAR(255) NULL,
                status VARCHAR(255) NOT NULL DEFAULT "active",
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};
