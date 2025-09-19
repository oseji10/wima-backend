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
         Schema::create('equipment', function (Blueprint $table) {
            $table->id('equipmentId');
            $table->string('serialNumber')->nullable();
            $table->string('equipmentName')->nullable();
            $table->string('value')->nullable();
            $table->unsignedBigInteger('equipmentCategory')->nullable();
            $table->unsignedBigInteger('hub')->nullable();
            $table->unsignedBigInteger('owner')->nullable();
            $table->string('exact_location')->nullable();

            $table->foreign('equipmentCategory')->references('categoryId')->on('equipment_category')->onDelete('cascade');
            $table->foreign('hub')->references('hubId')->on('hubs')->onDelete('cascade');
            $table->foreign('owner')->references('id')->on('users')->onDelete('cascade');
            $table->string('status')->default('active');
           
            $table->timestamps();
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
