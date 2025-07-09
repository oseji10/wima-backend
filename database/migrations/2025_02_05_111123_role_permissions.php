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
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id('rolePermissionId');
            $table->unsignedBigInteger('roleId');
            $table->unsignedBigInteger('permissionId');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('roleId')->references('roleId')->on('roles');
            $table->foreign('permissionId')->references('permissionId')->on('permissions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
