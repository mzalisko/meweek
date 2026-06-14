<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_route_requires_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    /**
     * The /admin route is added in Task 2.
     * This test will be enabled once the route exists.
     */
    public function test_logged_in_user_reaches_admin(): void
    {
        $this->markTestSkipped('Route /admin is added in Task 2.');
    }
}
