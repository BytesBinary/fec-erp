<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_pull_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('portal_exam_id');
            $table->string('exam_title', 255);
            $table->string('exam_kind', 24)->default('regular');
            $table->string('course_code', 30);
            $table->string('course_title', 255)->nullable();
            $table->decimal('credits', 4, 2)->nullable();
            $table->string('letter', 6)->nullable();
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('change_type', 16)->nullable();
            $table->string('previous_letter', 6)->nullable();
            $table->decimal('previous_grade_point', 3, 2)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'portal_exam_id', 'course_code']);
            $table->index(['student_id', 'course_code', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_results');
    }
};
