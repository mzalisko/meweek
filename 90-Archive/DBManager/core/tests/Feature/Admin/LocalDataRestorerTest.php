<?php

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\SiteGroup;
use App\Support\LocalDataRestorer;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LocalDataRestorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeded_on_fresh_database(): void
    {
        $restorer = app(LocalDataRestorer::class);

        $this->assertTrue($restorer->needsDemoData());

        $restorer->restore();

        $this->assertTrue(SiteGroup::query()->where('name', 'Brand A')->exists());
        $this->assertDatabaseHas('sites', ['domain' => 'domen.ua']);
        $this->assertDatabaseHas('sites', ['domain' => 'domen.ro']);
    }

    public function test_demo_data_not_needed_once_seeded(): void
    {
        Artisan::call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        $this->assertFalse(app(LocalDataRestorer::class)->needsDemoData());
    }

    public function test_restore_keeps_existing_site_ids(): void
    {
        Artisan::call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        $idsBefore = Site::query()->orderBy('id')->pluck('id')->all();

        app(LocalDataRestorer::class)->restore();

        $idsAfter = Site::query()->orderBy('id')->pluck('id')->all();

        $this->assertSame($idsBefore, $idsAfter);
    }
}
