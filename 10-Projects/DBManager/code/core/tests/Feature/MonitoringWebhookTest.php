<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Publication;
use App\Models\Site;
use App\Services\Failover\SlotResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class MonitoringWebhookTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.monitoring.secret' => self::SECRET]);
    }

    private function postSigned(array $payload, ?string $secret = null)
    {
        $body = json_encode($payload);

        return $this->call(
            'POST', '/api/monitoring/numbers', [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, $secret ?? self::SECRET),
                'HTTP_ACCEPT' => 'application/json',
            ],
            $body
        );
    }

    public function test_invalid_signature_rejected(): void
    {
        $this->postSigned(['e164' => '+380440000000', 'status' => 'down'], 'wrong-secret')
            ->assertStatus(401);
    }

    public function test_unknown_number_logged_and_422(): void
    {
        $this->postSigned(['e164' => '+380440000000', 'status' => 'down'])
            ->assertStatus(422);

        $this->assertTrue(AuditLog::where('action', 'webhook.unknown_number')->exists());
    }

    public function test_down_signal_switches_slot_and_publishes_site(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update([
            'key' => 'phone_ua_1', 'scope_type' => 'site', 'scope_id' => $site->id,
        ]);

        $response = $this->postSigned([
            'e164' => $entries[0]->phoneNumber->e164,
            'status' => 'down',
        ]);

        $response->assertOk()->assertJson(['affected_sites' => 1]);
        $this->assertSame(
            $entries[1]->phoneNumber->e164,
            app(SlotResolver::class)->resolve($slot->fresh())->number
        );
        $this->assertSame(1, Publication::where('site_id', $site->id)->count());
    }

    public function test_active_signal_recovers_slot(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update([
            'key' => 'phone_ua_1', 'scope_type' => 'site', 'scope_id' => $site->id,
        ]);
        $this->postSigned(['e164' => $entries[0]->phoneNumber->e164, 'status' => 'down']);

        $this->postSigned(['e164' => $entries[0]->phoneNumber->e164, 'status' => 'active'])
            ->assertOk();

        $this->assertSame(
            $entries[0]->phoneNumber->e164,
            app(SlotResolver::class)->resolve($slot->fresh())->number
        );
    }
}
