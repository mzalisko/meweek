<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@dbmanager.local'],
            [
                'name' => 'Супер-адмін',
                'password' => Hash::make('admin'),
                'role' => 'superadmin',
                'is_active' => true,
            ],
        );

        $manager = User::updateOrCreate(
            ['email' => 'manager@dbmanager.local'],
            [
                'name' => 'Тестовий менеджер',
                'password' => Hash::make('manager'),
                'role' => 'manager',
                'is_active' => true,
            ],
        );

        $viewer = User::updateOrCreate(
            ['email' => 'viewer@dbmanager.local'],
            [
                'name' => 'Тестовий в’ювер',
                'password' => Hash::make('viewer'),
                'role' => 'viewer',
                'is_active' => true,
            ],
        );

        $group = SiteGroup::query()->where('name', 'Brand A')->first()
            ?? SiteGroup::query()->orderBy('id')->first();

        if ($group) {
            $manager->siteGroupAccess()->updateOrCreate(
                ['site_group_id' => $group->id],
                ['can_view' => true, 'can_edit' => true, 'can_delete' => true, 'can_publish' => true],
            );

            $viewer->siteGroupAccess()->updateOrCreate(
                ['site_group_id' => $group->id],
                ['can_view' => true, 'can_edit' => false, 'can_delete' => false, 'can_publish' => false],
            );
        } elseif ($site = Site::query()->orderBy('id')->first()) {
            $manager->siteAccess()->updateOrCreate(
                ['site_id' => $site->id],
                ['can_view' => true, 'can_edit' => true, 'can_delete' => true, 'can_publish' => true],
            );

            $viewer->siteAccess()->updateOrCreate(
                ['site_id' => $site->id],
                ['can_view' => true, 'can_edit' => false, 'can_delete' => false, 'can_publish' => false],
            );
        }
    }
}
