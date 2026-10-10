<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table): void {
            $table->id();
            $table->decimal('min_mark', 5, 2);
            $table->decimal('max_mark', 5, 2);
            $table->string('letter', 4);
            $table->decimal('grade_point', 3, 2);
            $table->date('active_from')->nullable();
            $table->timestamps();

            $table->index(['min_mark', 'max_mark']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scales');
    }
};
