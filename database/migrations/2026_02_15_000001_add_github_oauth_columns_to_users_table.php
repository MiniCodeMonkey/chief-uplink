<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_id')->nullable()->unique()->after('id');
            $table->string('github_username')->nullable()->after('github_id');
            $table->string('avatar_url')->nullable()->after('github_username');
            $table->json('notification_preferences')->nullable()->after('remember_token');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'github_id',
                'github_username',
                'avatar_url',
                'notification_preferences',
            ]);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
