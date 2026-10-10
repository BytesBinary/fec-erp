<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_exam_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_pull_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('portal_exam_id');
            $table->string('exam_title', 255);
            $table->string('exam_kind', 24)->default('regular');
            $table->unsignedSmallInteger('exam_year')->nullable();
            $table->string('exam_roll', 30)->nullable();
            $table->string('class_roll', 30)->nullable();
            $table->date('published_on')->nullable();
            $table->string('outcome', 60)->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->decimal('cgpa', 3, 2)->nullable();
            $table->json('backlog_codes')->nullable();
            $table->string('raw_page_path', 255)->nullable();
            $table->string('raw_page_hash', 64)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'portal_exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_exam_results');
    }
};
