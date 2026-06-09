<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff already live in `users`. This is a 1:1 extension that holds only the
 * employment data the users table does not carry. Identity, name, role, state,
 * lga and account status all stay on `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();   // users.id

            $table->string('staff_number', 40)->nullable()->unique();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable()->index();
            // full_time | part_time | contract | volunteer | intern
            $table->string('employment_type', 20)->default('full_time');

            $table->unsignedBigInteger('hub')->nullable()->index();        // optional work hub
            $table->unsignedBigInteger('project_id')->nullable()->index(); // projects.projectId
            $table->unsignedBigInteger('manager_id')->nullable()->index(); // users.id

            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            // employment lifecycle, kept separate from users.status (account status)
            $table->string('employment_status', 20)->default('active')->index();

            $table->decimal('base_salary', 16, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_staff_profiles');
    }
};