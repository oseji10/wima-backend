<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipmentId')->index();

            // acquired | deployed | transferred | maintenance | repaired
            // | idle | reactivated | retired | disposed
            $table->string('event_type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('event_date');

            // Flexible payload (e.g. {"from_hub":3,"to_hub":7,"cost":1500})
            $table->json('meta')->nullable();

            $table->unsignedBigInteger('performed_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_lifecycle_events');
    }
};