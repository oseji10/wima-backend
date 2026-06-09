<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Full audit trail for every access to the safeguarding workflow.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_safeguarding_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            // list | view | create | update | action_added | export | access_denied
            $table->string('action', 20)->index();
            $table->text('detail')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_safeguarding_audit_logs');
    }
};