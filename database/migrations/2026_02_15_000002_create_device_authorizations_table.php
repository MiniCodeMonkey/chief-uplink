<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_name');
            $table->string('os')->nullable();
            $table->string('arch')->nullable();
            $table->string('chief_version')->nullable();
            $table->string('refresh_token_hash');
            $table->string('last_ip')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_online');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_authorizations');
    }
};
