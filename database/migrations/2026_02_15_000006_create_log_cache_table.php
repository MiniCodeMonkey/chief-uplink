<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_authorization_id')->constrained('device_authorizations')->cascadeOnDelete();
            $table->string('project_slug');
            $table->string('log_type')->default('claude_output');
            $table->string('story_id')->nullable();
            $table->longText('content');
            $table->timestamps();

            $table->index(['device_authorization_id', 'project_slug']);
            $table->index('story_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_cache');
    }
};
