<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_authorization_id')->nullable()->constrained('device_authorizations')->nullOnDelete();
            $table->string('provider');
            $table->string('provider_server_id')->nullable();
            $table->text('provider_api_key');
            $table->string('region');
            $table->string('tier');
            $table->string('ip_address')->nullable();
            $table->enum('status', ['provisioning', 'active', 'suspended', 'destroyed'])->default('provisioning');
            $table->decimal('monthly_cost', 8, 2)->nullable();
            $table->string('setup_token')->nullable()->unique();
            $table->timestamp('setup_token_expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_deployments');
    }
};
