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
         Schema::create('services', function (Blueprint $table) {
            $table->id('serviceId');
            $table->string('serviceName')->nullable();
            $table->string('cost')->nullable();
            $table->string('measuringUnit')->nullable();
            $table->unsignedBigInteger('serviceCategoryId')->nullable();
            $table->unsignedBigInteger('addedBy')->nullable();
            $table->unsignedBigInteger('hub')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('serviceCategoryId')->references('categoryId')->on('service_categories')->onDelete('cascade');
            $table->foreign('addedBy')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('hub')->references('hubId')->on('hubs')->onDelete('cascade');
        

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
