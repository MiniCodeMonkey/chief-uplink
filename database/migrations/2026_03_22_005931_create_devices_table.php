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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('managed_server_id')->nullable();
            $table->string('name');
            $table->string('os');
            $table->string('arch');
            $table->string('chief_version');
            $table->string('access_token', 64)->unique();
            $table->string('refresh_token_hash', 64)->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('connected')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
