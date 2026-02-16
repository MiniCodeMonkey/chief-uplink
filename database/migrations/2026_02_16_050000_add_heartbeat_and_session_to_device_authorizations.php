<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_authorizations', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_connected_at');
            $table->string('session_id')->nullable()->after('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::table('device_authorizations', function (Blueprint $table) {
            $table->dropColumn(['last_heartbeat_at', 'session_id']);
        });
    }
};
