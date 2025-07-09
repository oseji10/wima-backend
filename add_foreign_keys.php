<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$foreignKeys = [
    [
        'name' => 'applications_applicationtype_foreign',
        'sql' => 'ALTER TABLE applications ADD CONSTRAINT applications_applicationtype_foreign FOREIGN KEY (applicationType) REFERENCES application_types(typeId) ON DELETE CASCADE',
        'description' => 'applicationType foreign key'
    ],
    [
        'name' => 'applications_batch_foreign',
        'sql' => 'ALTER TABLE applications ADD CONSTRAINT applications_batch_foreign FOREIGN KEY (batch) REFERENCES batches(batchId) ON DELETE CASCADE',
        'description' => 'batch foreign key'
    ],
    [
        'name' => 'applications_jambid_foreign',
        'sql' => 'ALTER TABLE applications ADD CONSTRAINT applications_jambid_foreign FOREIGN KEY (jambId) REFERENCES jamb(jambId) ON DELETE CASCADE',
        'description' => 'jambId foreign key'
    ],
    [
        'name' => 'applications_userid_foreign',
        'sql' => 'ALTER TABLE applications ADD CONSTRAINT applications_userid_foreign FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE',
        'description' => 'userId foreign key'
    ]
];

foreach ($foreignKeys as $fk) {
    try {
        DB::statement($fk['sql']);
        echo "✓ {$fk['description']} added successfully\n";
    } catch (Exception $e) {
        echo "✗ Error adding {$fk['description']}: " . $e->getMessage() . "\n";
    }
}
