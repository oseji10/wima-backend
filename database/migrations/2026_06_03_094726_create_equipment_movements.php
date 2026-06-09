<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipmentId')->index();

            $table->unsignedBigInteger('from_hub')->nullable()->index();
            $table->unsignedBigInteger('to_hub')->nullable()->index();
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();

            // deployment | transfer | return | loan | maintenance_dispatch
            $table->string('movement_type', 30)->default('transfer');
            $table->string('reason')->nullable();

            $table->date('movement_date');
            $table->date('expected_return_date')->nullable();

            // in_transit | deployed | returned | completed | cancelled
            $table->string('status', 20)->default('in_transit');

            $table->unsignedBigInteger('dispatched_by')->nullable()->index();
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->timestamp('received_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_movements');
    }
};