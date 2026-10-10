<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_residencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->string('room', 20)->nullable();
            $table->date('assigned_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'ended_on']);
            $table->index(['hall_id', 'ended_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_residencies');
    }
};
