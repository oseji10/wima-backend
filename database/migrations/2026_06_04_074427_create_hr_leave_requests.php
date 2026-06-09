<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('leave_type_id')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 6, 2)->default(0);    // working days
            $table->text('reason')->nullable();
            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_requests');
    }
};