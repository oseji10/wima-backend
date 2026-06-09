<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->index();
            $table->unsignedBigInteger('hub')->nullable()->index();   // hubId
            $table->date('submission_date')->index();
            $table->json('data');                                     // { field_key: value }
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_submissions');
    }
};