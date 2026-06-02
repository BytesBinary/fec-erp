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
        Schema::table('routine_slots', function (Blueprint $table): void {
            $table->char('slot_group_id', 26)->nullable()->after('teacher_id');
            $table->boolean('is_lab_continuation')->default(false)->after('slot_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routine_slots', function (Blueprint $table): void {
            $table->dropColumn(['slot_group_id', 'is_lab_continuation']);
        });
    }
};
