<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * msps.id was not defined as AUTO_INCREMENT, so inserts that rely on
     * Eloquent's auto-generated primary key fail with:
     *   "Field 'id' doesn't have a default value".
     * Restore auto-increment.
     *
     * NOTE: if your `id` column is INT rather than BIGINT UNSIGNED, change the
     * type below to match (e.g. `INT UNSIGNED`). `id` must be (part of) a key
     * for AUTO_INCREMENT to be accepted — normally it is the PRIMARY KEY.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `msps` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `msps` MODIFY `id` BIGINT UNSIGNED NOT NULL');
    }
};