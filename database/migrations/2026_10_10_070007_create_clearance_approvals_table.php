<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clearance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('clearance_stages');
            $table->string('decision', 12);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name')->nullable();
            $table->string('approver_designation')->nullable();
            $table->string('signature_snapshot_path')->nullable();
            $table->string('signature_sha256', 64)->nullable();
            $table->text('remarks')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('decided_at');
            $table->string('prev_hash', 64);
            $table->string('hash', 64);
            $table->boolean('superseded')->default(false);
            $table->timestamps();

            $table->index(['clearance_request_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_approvals');
    }
};
