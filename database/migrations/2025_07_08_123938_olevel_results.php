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
         Schema::create('olevel_results', function (Blueprint $table) {
            $table->id('resultId');
            $table->string('applicationId')->nullable();
            $table->string('examYear')->nullable();
            $table->string('examType')->nullable();
            $table->string('subject')->nullable();
            $table->string('grade')->nullable();
            
            
            $table->timestamps();
            $table->softDeletes();

        $table->foreign('applicationId')->references('applicationId')->on('applications')->onDelete('cascade');

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
