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
        Schema::create('lgas', function (Blueprint $table) {
            $table->id('lgaId');
            $table->string('lgaName')->nullable();
            $table->unsignedBigInteger('state')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('state')->references('stateId')->on('states')->onDelete('cascade');

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
