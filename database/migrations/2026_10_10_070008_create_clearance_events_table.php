<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clearance_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 16)->default('web');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('clearance_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_events');
    }
};
