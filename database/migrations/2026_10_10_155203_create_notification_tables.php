<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80)->index();
            $table->string('name', 120);
            $table->string('subject', 255);
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80)->unique();
            $table->string('category', 40);
            $table->boolean('enabled')->default(false);
            $table->string('mode', 10)->default('immediate');
            $table->json('recipients');
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->timestamps();

            $table->index('category');
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80);
            $table->string('dedupe_key', 191)->unique();
            $table->json('context');
            $table->unsignedBigInteger('affected_user_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('hall_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('processed_at');
            $table->index('event_key');
        });

        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outbox_event_id')->nullable()->constrained('outbox_events')->nullOnDelete();
            $table->string('event_key', 80);
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email', 255);
            $table->string('subject', 255);
            $table->text('body');
            $table->string('url', 500)->nullable();
            $table->string('status', 12)->default('queued');
            $table->string('mode', 10)->default('immediate');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('dedupe_key', 191)->unique();
            $table->boolean('is_test')->default(false);
            $table->unsignedBigInteger('digest_delivery_id')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['event_key', 'status']);
            $table->index(['recipient_email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('email_templates');
    }
};
