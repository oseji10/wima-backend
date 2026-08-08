<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Equipment catalog.
        Schema::create('gotract_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');              // individual | group
            $table->string('image_url')->nullable();  
            $table->unsignedTinyInteger('group_size')->nullable(); // required members if type=group
            $table->unsignedInteger('total_quantity')->default(0);
            $table->unsignedInteger('available_quantity')->default(0);
            $table->string('unit')->default('unit'); // e.g. "set", "unit"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Individual loans — one accredited participant borrows directly.
        Schema::create('gotract_individual_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('gotract_applications')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('gotract_equipment')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1); // number of equipment items requested
            $table->string('status')->default('pending'); // pending|approved|rejected|collected|returned
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'equipment_id']);
        });

        // 3. Cooperatives — a group of 10 forming to collect one group-equipment item.
        Schema::create('gotract_cooperatives', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->nullable(); // share code, e.g. COOP-4F2A
            $table->string('name')->nullable();
            $table->foreignId('equipment_id')->constrained('gotract_equipment')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1); // number of equipment items requested
            $table->foreignId('lead_application_id')->constrained('gotract_applications')->cascadeOnDelete();
            $table->string('lga')->nullable();
            $table->unsignedTinyInteger('required_size')->default(10);
            $table->string('status')->default('forming'); // forming|ready|requested|approved|rejected|collected|returned
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });

        // 4. Cooperative members.
        Schema::create('gotract_cooperative_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained('gotract_cooperatives')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('gotract_applications')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            // A participant appears once per cooperative.
            $table->unique(['cooperative_id', 'application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gotract_cooperative_members');
        Schema::dropIfExists('gotract_cooperatives');
        Schema::dropIfExists('gotract_individual_loans');
        Schema::dropIfExists('gotract_equipment');
    }
};