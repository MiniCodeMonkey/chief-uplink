<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_authorization_id')->constrained('device_authorizations')->cascadeOnDelete();
            $table->string('project_slug');
            $table->string('prd_name');
            $table->enum('status', ['completed', 'failed', 'paused', 'stopped'])->default('completed');
            $table->unsignedInteger('stories_completed')->default(0);
            $table->unsignedInteger('stories_total')->default(0);
            $table->json('story_results')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('tokens_used')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['device_authorization_id', 'project_slug']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_history');
    }
};
