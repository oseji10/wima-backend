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
        // database/migrations/xxxx_create_requisition_tables.php

Schema::create('approval_workflows', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('approval_steps', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
    $table->unsignedTinyInteger('step_order');
    $table->unsignedBigInteger('role_id'); // approver role at this step
    $table->decimal('approval_limit', 15, 2)->nullable(); // step only applies above this amount, null = always
    $table->string('label')->nullable(); // e.g. "Department Head"
    $table->timestamps();
});

Schema::create('request_types', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code')->unique(); // 'general_requisition', 'travel', ...
    $table->foreignId('workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('requests', function (Blueprint $table) {
    $table->id();
    $table->string('request_no')->unique();
    $table->foreignId('request_type_id')->constrained('request_types');
    $table->foreignId('employee_id')->constrained('users');
    $table->unsignedBigInteger('department_id')->nullable();
    $table->string('title');
    $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
    $table->date('needed_by')->nullable();
    $table->text('description')->nullable();
    $table->text('justification')->nullable();
    $table->string('preferred_vendor')->nullable();
    $table->string('alternative_vendor')->nullable();
    $table->decimal('total_amount', 15, 2)->default(0);
    $table->enum('status', [
        'draft', 'submitted', 'pending_approval', 'approved', 'rejected', 'cancelled', 'completed',
    ])->default('draft');
    $table->unsignedTinyInteger('current_step')->default(0); // 0 = not yet in workflow
    $table->timestamp('submitted_at')->nullable();
    $table->timestamps();
});

Schema::create('request_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
    $table->string('item_name');
    $table->unsignedInteger('quantity')->default(1);
    $table->string('unit')->nullable();
    $table->decimal('unit_cost', 15, 2)->default(0);
    $table->timestamps();
});

Schema::create('request_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
    $table->string('label'); // 'specification', 'quotation', 'picture', 'approval_memo'
    $table->string('path');
    $table->string('original_name');
    $table->timestamps();
});

Schema::create('request_approvals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
    $table->foreignId('approval_step_id')->constrained('approval_steps');
    $table->unsignedBigInteger('approver_id')->nullable(); // filled once someone acts
    $table->enum('status', ['pending', 'approved', 'rejected', 'clarification_requested', 'returned'])->default('pending');
    $table->text('comments')->nullable();
    $table->timestamp('acted_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_tables');
    }
};
