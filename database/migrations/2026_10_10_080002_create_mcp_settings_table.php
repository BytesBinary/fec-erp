<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('global_enabled')->default(true);
            $table->unsignedSmallInteger('max_integrations_per_user')->default(5);
            $table->boolean('allow_never_expire')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_settings');
    }
};
