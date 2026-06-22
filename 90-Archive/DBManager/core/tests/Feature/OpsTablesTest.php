<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Publication;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_row(): void
    {
        $log = AuditLog::create([
            'actor_type' => 'system',
            'action' => 'failover.switch',
            'subject_type' => 'phone_slot',
            'subject_id' => 1,
            'old' => ['number' => '+380111111111'],
            'new' => ['number' => '+380222222222'],
        ]);

        $this->assertSame('+380222222222', $log->fresh()->new['number']);
    }

    public function test_incident_defaults_to_new(): void
    {
        $incident = Incident::create([
            'severity' => 'critical',
            'kind' => 'slot_exhausted',
            'message' => 'Слот вичерпано',
        ]);

        $this->assertSame('new', $incident->fresh()->status);
    }

    public function test_publication_versions_unique_per_site(): void
    {
        $site = Site::factory()->create();
        Publication::create(['site_id' => $site->id, 'version' => 1, 'payload' => ['values' => []]]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Publication::create(['site_id' => $site->id, 'version' => 1, 'payload' => ['values' => []]]);
    }
}
