<?php

namespace Tests\Feature\Admin;

use App\Livewire\BulkOperations;
use App\Livewire\ValueEditor;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Підсвічування змін (вимога §4 ТЗ): дані, що підпадають під зміну, мають бути
 * візуально позначені — у single-edit (Було → стало), у масовому прев'ю та у звіті
 * «останні зміни» (структурований diff, не лише прев'ю), і для нових типів.
 */
class ChangeHighlightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    // --- BULK: звіт «останні зміни» дає структурований diff old/new ---

    public function test_recent_bulk_sessions_expose_structured_old_new_diff(): void
    {
        $site = Site::factory()->create();
        $dv = DataValue::factory()->ofType('text')->forSite($site)->create([
            'key' => 'note', 'content' => ['value' => 'нове значення'],
        ]);

        AuditLog::create([
            'action'       => 'bulk.set_value',
            'batch_id'     => 'batch-hl-1',
            'subject_type' => 'DataValue',
            'subject_id'   => $dv->id,
            'old'          => ['key' => 'note', 'content' => ['value' => 'старе значення']],
            'new'          => ['key' => 'note', 'content' => ['value' => 'нове значення']],
        ]);

        $sessions = Livewire::test(BulkOperations::class)->instance()->getRecentBulkSessions();

        $this->assertCount(1, $sessions);
        $det = $sessions->first()['details']->first();
        $this->assertSame('старе значення', $det['old']);
        $this->assertSame('нове значення', $det['new']);
    }

    // --- BULK: прев'ю показує осмислене значення нового типу (social), не «—» ---

    public function test_bulk_preview_shows_social_handle_with_network(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Soc HL']);
        $site = Site::factory()->for($group, 'group')->create(['domain' => 'soc-hl.test']);

        DataValue::factory()->ofType('social')->forSite($site)->create([
            'key' => 'tg_brand', 'content' => ['value' => '@brand', 'network' => 'telegram'],
        ]);

        Livewire::test(BulkOperations::class)
            ->set('scope', 'group')
            ->set('groupId', $group->id)
            ->set('targetType', 'social')
            ->assertSee('@brand (telegram)');
    }

    // --- SINGLE-EDIT: dirty-стан і Було→стало для значення та адреси ---

    public function test_single_edit_tracks_dirty_for_value_type(): void
    {
        $site = Site::factory()->create();
        $dv = DataValue::factory()->ofType('text')->forSite($site)->create([
            'key' => 'note', 'content' => ['value' => 'старе'],
        ]);

        $editor = Livewire::test(ValueEditor::class)->call('edit', $dv->id);
        $this->assertFalse($editor->instance()->isValueDirty());

        $editor->set('value', 'нове');
        $this->assertTrue($editor->instance()->isValueDirty());
    }

    public function test_single_edit_tracks_dirty_for_address_mirror(): void
    {
        $site = Site::factory()->create();
        $dv = DataValue::factory()->ofType('address')->forSite($site)->create([
            'key' => 'office',
            'content' => [
                'country' => 'Україна', 'region' => null, 'city' => 'Київ',
                'street' => 'вул. Хрещатик, 1', 'postcode' => null,
                'value' => 'вул. Хрещатик, 1, Київ, Україна',
            ],
        ]);

        $editor = Livewire::test(ValueEditor::class)->call('edit', $dv->id);
        // Дзеркало збігається зі складеним → ще не змінено.
        $this->assertFalse($editor->instance()->isValueDirty());

        $editor->set('addrCity', 'Львів');
        $this->assertTrue($editor->instance()->isValueDirty());
        $this->assertStringContainsString('Львів', $editor->instance()->currentDisplayValue());
    }
}
