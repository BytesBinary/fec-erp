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
        Schema::create('routine_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('semester_number');
            $table->unsignedTinyInteger('day_of_week')->comment('0=Sun,1=Mon,2=Tue,3=Wed,4=Thu');
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // A batch cannot have two classes at the same time
            $table->unique(['batch_id', 'day_of_week', 'time_slot_id'], 'unique_batch_slot');
            // A teacher cannot teach two classes at the same time
            $table->unique(['teacher_id', 'day_of_week', 'time_slot_id'], 'unique_teacher_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routine_slots');
    }
};
