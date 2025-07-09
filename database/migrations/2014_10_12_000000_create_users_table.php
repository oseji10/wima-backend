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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('otherNames')->nullable();
            $table->string('email')->unique();
            $table->string('phoneNumber');
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->unsignedBigInteger('role')->nullable();
            $table->unsignedBigInteger('applicationType')->nullable();
            $table->string('jambId')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('role')->references('roleId')->on('roles')->onDelete('cascade');
            $table->foreign('applicationType')->references('typeId')->on('application_types')->onDelete('cascade');
            $table->foreign('jambId')->references('jambId')->on('jamb')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
