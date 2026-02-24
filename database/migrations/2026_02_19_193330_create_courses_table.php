<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('semester_number')->comment('1-8');
            $table->string('type', 20)->default('theory')->comment('theory or lab');
            $table->string('code', 30);
            $table->unsignedTinyInteger('version')->nullable()->comment('null = latest');
            $table->string('name');
            $table->decimal('credit_hours', 4, 2)->default(3.00);
            $table->unsignedTinyInteger('weekly_classes')->nullable()->comment('null = 1 class/week');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['code', 'version'], 'courses_code_version_unique');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
