<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->index();
            $table->string('key', 60);                     // machine key, unique per form
            $table->string('label');
            // text | textarea | number | select | boolean | date
            $table->string('type', 20)->default('text');
            $table->json('options')->nullable();           // for select
            $table->boolean('required')->default(false);
            $table->string('unit', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_form_fields');
    }
};