<?php

namespace Tests\Feature;

use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Failover\FailoverEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class GroupScopeFanoutTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    public function test_group_scoped_slot_fans_out_to_all_sites_in_group(): void
    {
        $group = SiteGroup::factory()->create();
        $siteA = Site::factory()->for($group, 'group')->create();
        $siteB = Site::factory()->for($group, 'group')->create();
        $other = Site::factory()->create(); // інша група/без групи

        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update([
            'key' => 'phone_grp', 'scope_type' => 'group', 'scope_id' => $group->id,
        ]);

        $engine = app(FailoverEngine::class);
        $affected = $engine->markNumberDown($entries[0]->phoneNumber);

        $sites = $affected->flatMap(fn ($s) => $engine->sitesFor($s))->unique('id')->values();

        $this->assertTrue($sites->pluck('id')->contains($siteA->id));
        $this->assertTrue($sites->pluck('id')->contains($siteB->id));
        $this->assertFalse($sites->pluck('id')->contains($other->id));
    }
}
