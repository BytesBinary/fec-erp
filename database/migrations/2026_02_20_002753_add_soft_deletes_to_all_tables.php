<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users',
            'departments',
            'designations',
            'batches',
            'courses',
            'teachers',
            'students',
            'staff',
            'time_slots',
            'exam_types',
            'exam_halls',
            'exam_duties',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'departments',
            'designations',
            'batches',
            'courses',
            'teachers',
            'students',
            'staff',
            'time_slots',
            'exam_types',
            'exam_halls',
            'exam_duties',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
