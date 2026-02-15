<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cached_project_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_authorization_id')->constrained('device_authorizations')->cascadeOnDelete();
            $table->string('project_slug');
            $table->string('project_name');
            $table->string('git_branch')->nullable();
            $table->string('last_commit_hash')->nullable();
            $table->string('last_commit_message')->nullable();
            $table->enum('status', ['running', 'idle', 'error', 'paused', 'no_prd'])->default('idle');
            $table->string('current_prd_name')->nullable();
            $table->unsignedInteger('stories_completed')->default(0);
            $table->unsignedInteger('stories_total')->default(0);
            $table->json('story_details')->nullable();
            $table->unsignedInteger('active_sessions')->default(0);
            $table->json('recent_activity')->nullable();
            $table->timestamps();

            $table->unique(['device_authorization_id', 'project_slug']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cached_project_state');
    }
};
