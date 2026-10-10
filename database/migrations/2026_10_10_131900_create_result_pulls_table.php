<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_pulls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger', 24);
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('exams_checked')->default(0);
            $table->unsignedSmallInteger('results_found')->default(0);
            $table->unsignedSmallInteger('results_changed')->default(0);
            $table->unsignedInteger('portal_exam_id')->nullable();
            $table->unsignedBigInteger('portal_publication_id')->nullable();
            $table->unsignedSmallInteger('pending_checks')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('portal_publication_id');
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_pulls');
    }
};
