// Create cooperative_requests table
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gotract_cooperative_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained('gotract_cooperatives')->onDelete('cascade');
            $table->foreignId('equipment_id')->constrained('gotract_equipment')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->string('status')->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('gotract_applications')->onDelete('set null');
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
        });

        // Update cooperatives table to remove equipment_id
        Schema::table('gotract_cooperatives', function (Blueprint $table) {
            if (Schema::hasColumn('gotract_cooperatives', 'equipment_id')) {
                $table->dropForeign(['equipment_id']);
                $table->dropColumn('equipment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gotract_cooperative_requests');
    }
};