<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_seeder_restores_local_admin_password(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@dbmanager.local'],
            ['name' => 'Старий адмін', 'password' => Hash::make('wrong-password')],
        );

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@dbmanager.local')->sole();

        $this->assertSame('Супер-адмін', $admin->name);
        $this->assertSame('superadmin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('admin', $admin->password));
    }

    public function test_admin_user_seeder_creates_local_manager_and_viewer(): void
    {
        $this->seed(AdminUserSeeder::class);

        $manager = User::where('email', 'manager@dbmanager.local')->sole();
        $viewer = User::where('email', 'viewer@dbmanager.local')->sole();

        $this->assertSame('manager', $manager->role);
        $this->assertSame('viewer', $viewer->role);
        $this->assertTrue(Hash::check('manager', $manager->password));
        $this->assertTrue(Hash::check('viewer', $viewer->password));
    }
}
