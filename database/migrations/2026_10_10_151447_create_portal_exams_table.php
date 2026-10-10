<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_exams', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('portal_exam_id')->unique();
            $table->unsignedSmallInteger('program_id');
            $table->string('title', 255);
            $table->string('kind', 24)->default('regular');
            $table->unsignedTinyInteger('semester')->nullable();
            $table->unsignedSmallInteger('exam_year')->nullable();
            $table->string('session_tag', 9)->nullable();
            $table->string('status', 16)->default('known');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->date('published_on')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('check_count')->default(0);
            $table->timestamps();

            $table->index(['program_id', 'exam_year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_exams');
    }
};
