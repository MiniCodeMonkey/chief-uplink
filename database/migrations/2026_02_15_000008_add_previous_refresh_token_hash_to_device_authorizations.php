<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_authorizations', function (Blueprint $table) {
            $table->string('previous_refresh_token_hash')->nullable()->after('refresh_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('device_authorizations', function (Blueprint $table) {
            $table->dropColumn('previous_refresh_token_hash');
        });
    }
};
