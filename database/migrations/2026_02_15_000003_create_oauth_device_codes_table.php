<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_device_codes', function (Blueprint $table) {
            $table->id();
            $table->string('device_code')->unique();
            $table->string('user_code', 9)->unique();
            $table->string('device_name');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'denied', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();

            $table->index('user_code');
            $table->index('device_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_device_codes');
    }
};
