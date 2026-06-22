<?php

namespace Tests\Feature\Admin;

use App\Livewire\IncidentsPage;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_incidents_page_is_accessible_to_logged_in_users(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('admin.incidents'))
            ->assertOk();
    }

    public function test_incidents_page_lists_reserves_and_primary_tabs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $site = Site::factory()->create(['domain' => 'my-site.test']);

        Livewire::test(IncidentsPage::class)
            ->call('selectTab', 'primary')
            ->assertSee('my-site.test')
            ->assertSet('activeTab', 'primary')
            ->call('selectTab', 'reserves')
            ->assertSet('activeTab', 'reserves');
    }
}
