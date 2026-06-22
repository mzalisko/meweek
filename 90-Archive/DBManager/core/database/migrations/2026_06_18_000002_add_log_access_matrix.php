<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_view_user_logs')->default(false)->after('is_active');
            $table->boolean('can_view_system_logs')->default(false)->after('can_view_user_logs');
        });

        Schema::table('user_site_group_access', function (Blueprint $table) {
            $table->boolean('can_view_failover')->default(false)->after('can_view_history');
        });

        Schema::table('user_site_access', function (Blueprint $table) {
            $table->boolean('can_view_failover')->default(false)->after('can_view_history');
        });
    }

    public function down(): void
    {
        Schema::table('user_site_access', function (Blueprint $table) {
            $table->dropColumn('can_view_failover');
        });

        Schema::table('user_site_group_access', function (Blueprint $table) {
            $table->dropColumn('can_view_failover');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_view_user_logs', 'can_view_system_logs']);
        });
    }
};
