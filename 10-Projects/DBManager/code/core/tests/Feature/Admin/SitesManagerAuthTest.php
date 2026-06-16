<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitesManagerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_open_sites_screen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.sites'))->assertOk();
    }

    public function test_non_superadmin_cannot_manage_sites(): void
    {
        $this->actingAs(User::factory()->viewer()->create());
        $this->get(route('admin.sites'))->assertForbidden();

        $this->actingAs(User::factory()->manager()->create());
        $this->get(route('admin.sites'))->assertForbidden();
    }
}
