<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::statement("CREATE TABLE applications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        applicationId VARCHAR(255) NOT NULL UNIQUE,
        jambId VARCHAR(255) NULL,
        dateOfBirth VARCHAR(255) NULL,
        gender VARCHAR(255) NULL,
        alternatePhoneNumber VARCHAR(255) NULL,
        licenceId VARCHAR(255) NULL,
        batch BIGINT UNSIGNED NULL,
        applicationType BIGINT UNSIGNED NULL,
        userId BIGINT UNSIGNED NULL,
        isActive VARCHAR(255) NOT NULL DEFAULT 'true',
        slipPrintCount VARCHAR(255) NOT NULL DEFAULT '0',
        admissionPrintCount VARCHAR(255) NOT NULL DEFAULT '0',
        isPresent VARCHAR(255) NOT NULL DEFAULT 'false',
        status VARCHAR(255) NOT NULL DEFAULT 'account_created',
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        deleted_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "Applications table created successfully!\n";
    
    // Also record the migration as completed
    DB::table('migrations')->insert([
        'migration' => '2025_02_05_115540_create_applications_table',
        'batch' => 28
    ]);
    
    echo "Migration recorded as completed!\n";
    
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
