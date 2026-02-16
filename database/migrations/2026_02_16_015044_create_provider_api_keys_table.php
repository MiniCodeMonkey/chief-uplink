<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // hetzner, digitalocean
            $table->text('api_key'); // encrypted at rest via Laravel's encrypted cast
            $table->string('masked_key'); // e.g., sk-...abc123
            $table->string('account_name')->nullable(); // from provider validation
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_api_keys');
    }
};
