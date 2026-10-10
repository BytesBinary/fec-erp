<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_required_fields', function (Blueprint $table): void {
            $table->id();
            $table->string('field_key', 60)->unique();
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_required_fields');
    }
};
