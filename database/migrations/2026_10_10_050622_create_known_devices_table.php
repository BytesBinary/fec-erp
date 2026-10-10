<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('known_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint_hash', 64);
            $table->string('label', 120)->nullable();
            $table->string('trusted_token_hash', 64)->nullable();
            $table->timestamp('trusted_until')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint_hash']);
            $table->index('trusted_token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('known_devices');
    }
};
