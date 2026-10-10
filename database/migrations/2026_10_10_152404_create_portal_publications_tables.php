<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_publications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('portal_exam_id')->unique();
            $table->unsignedSmallInteger('program_id');
            $table->string('status', 16)->default('detected');
            $table->string('mode', 8)->default('live');
            $table->timestamp('detected_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->unsignedSmallInteger('check_count')->default(0);
            $table->unsignedInteger('students_total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('portal_probes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('portal_exam_id');
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 16);
            $table->text('message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['portal_exam_id', 'outcome']);
        });

        Schema::create('portal_check_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->string('status', 16)->default('running');
            $table->unsignedInteger('exams_total')->default(0);
            $table->unsignedInteger('new_exams')->default(0);
            $table->unsignedInteger('publications_confirmed')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_check_runs');
        Schema::dropIfExists('portal_probes');
        Schema::dropIfExists('portal_publications');
    }
};
