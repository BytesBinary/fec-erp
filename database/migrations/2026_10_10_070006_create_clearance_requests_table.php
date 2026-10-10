<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_no', 24)->unique();
            $table->string('verify_code', 40)->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->foreignId('current_stage_id')->nullable()->constrained('clearance_stages')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('id_verified')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('chain_hash', 64)->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('student_id');
            $table->index(['status', 'current_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_requests');
    }
};
