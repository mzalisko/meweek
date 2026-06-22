<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'admin@dbmanager.local')
            ->update([
                'role' => 'superadmin',
                'is_active' => true,
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'admin@dbmanager.local')
            ->update([
                'role' => 'viewer',
            ]);
    }
};
