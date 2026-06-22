<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_site_group_access', function (Blueprint $table) {
            $table->boolean('can_view_history')->default(false)->after('can_delete');
        });

        Schema::table('user_site_access', function (Blueprint $table) {
            $table->boolean('can_view_history')->default(false)->after('can_delete');
        });
    }

    public function down(): void
    {
        Schema::table('user_site_access', function (Blueprint $table) {
            $table->dropColumn('can_view_history');
        });

        Schema::table('user_site_group_access', function (Blueprint $table) {
            $table->dropColumn('can_view_history');
        });
    }
};
