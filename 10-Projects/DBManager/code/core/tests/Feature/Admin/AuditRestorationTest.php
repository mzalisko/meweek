<?php

namespace Tests\Feature\Admin;

use App\Admin\AccessControl;
use App\Livewire\AuditManager;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use App\Models\ValueType;
use App\Services\Audit\AuditRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class AuditRestorationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->superAdmin = User::factory()->create([
            'role' => AccessControl::ROLE_SUPERADMIN,
            'is_active' => true,
        ]);
        $this->viewer = User::factory()->create([
            'role' => AccessControl::ROLE_VIEWER,
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin);
    }

    public function test_user_login_logs_auth_event(): void
    {
        // Вручну викликаємо івент авторизації
        event(new \Illuminate\Auth\Events\Login('web', $this->superAdmin, false));

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'user',
            'actor_id' => $this->superAdmin->id,
            'action' => 'user.login',
        ]);
    }

    public function test_revert_value_update(): void
    {
        $site = Site::factory()->create();
        $type = ValueType::firstOrCreate(['code' => 'text'], ['name' => 'Text']);
        $dv = DataValue::create([
            'key' => 't_key',
            'value_type_id' => $type->id,
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'content' => ['value' => 'Old Value'],
            'status' => 'active',
        ]);

        // Створюємо аудит лог оновлення
        $log = AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $this->superAdmin->id,
            'action' => 'value.updated',
            'subject_type' => 'DataValue',
            'subject_id' => $dv->id,
            'old' => ['value' => 'Old Value'],
            'new' => ['value' => 'New Value'],
        ]);

        // Оновлюємо значення
        $dv->update(['content' => ['value' => 'New Value']]);

        // Робимо відкат
        $restored = AuditRestorer::restore($log, $this->superAdmin);
        $this->assertTrue($restored);

        $this->assertEquals('Old Value', $dv->fresh()->content['value']);
    }

    public function test_revert_value_deletion(): void
    {
        $site = Site::factory()->create();
        $type = ValueType::firstOrCreate(['code' => 'text'], ['name' => 'Text']);
        $dv = DataValue::create([
            'key' => 't_delete_key',
            'value_type_id' => $type->id,
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'content' => ['value' => 'Deleted Value'],
            'status' => 'active',
        ]);

        $serialized = AuditRestorer::serializeValue($dv);
        $dvId = $dv->id;

        // Створюємо аудит лог видалення
        $log = AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $this->superAdmin->id,
            'action' => 'value.deleted',
            'subject_type' => 'DataValue',
            'subject_id' => $dvId,
            'old' => $serialized,
            'new' => null,
        ]);

        $dv->delete();

        // Перевіряємо, що в БД його немає
        $this->assertDatabaseMissing('data_values', ['id' => $dvId]);

        // Робимо відкат
        $restored = AuditRestorer::restore($log, $this->superAdmin);
        $this->assertTrue($restored);

        // Перевіряємо, що запис знову існує
        $this->assertDatabaseHas('data_values', [
            'key' => 't_delete_key',
            'scope_type' => 'site',
            'scope_id' => $site->id,
        ]);
    }

    public function test_revert_phone_slot_deletion(): void
    {
        $site = Site::factory()->create();
        $type = ValueType::firstOrCreate(['code' => 'phone'], ['name' => 'Phone']);
        $dv = DataValue::create([
            'key' => 'p_delete_key',
            'value_type_id' => $type->id,
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'content' => [],
            'status' => 'active',
        ]);

        $ps = PhoneSlot::create([
            'data_value_id' => $dv->id,
            'return_mode' => 'auto',
            'exhaustion_policy' => 'emergency',
        ]);

        $pn1 = PhoneNumber::create(['e164' => '+380991112233', 'status' => 'active']);
        $pn2 = PhoneNumber::create(['e164' => '+380997778899', 'status' => 'active']);

        NumberEntry::create([
            'phone_slot_id' => $ps->id,
            'phone_number_id' => $pn1->id,
            'priority' => 0,
        ]);
        NumberEntry::create([
            'phone_slot_id' => $ps->id,
            'phone_number_id' => $pn2->id,
            'priority' => 1,
        ]);

        $serialized = AuditRestorer::serializeValue($dv);
        $dvId = $dv->id;

        // Створюємо аудит лог видалення слота
        $log = AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $this->superAdmin->id,
            'action' => 'slot.removed',
            'subject_type' => 'DataValue',
            'subject_id' => $dvId,
            'old' => $serialized,
            'new' => null,
        ]);

        // Видаляємо
        $ps->entries()->delete();
        $ps->delete();
        $dv->delete();

        // Відкат
        $restored = AuditRestorer::restore($log, $this->superAdmin);
        $this->assertTrue($restored);

        // Перевіряємо відновлення DataValue, PhoneSlot та NumberEntries
        $restoredDv = DataValue::where('key', 'p_delete_key')->first();
        $this->assertNotNull($restoredDv);
        $this->assertNotNull($restoredDv->phoneSlot);
        $this->assertEquals('emergency', $restoredDv->phoneSlot->exhaustion_policy);
        $this->assertCount(2, $restoredDv->phoneSlot->entries);
    }

    public function test_non_allowed_user_cannot_restore(): void
    {
        $site = Site::factory()->create();
        $type = ValueType::firstOrCreate(['code' => 'text'], ['name' => 'Text']);
        $dv = DataValue::create([
            'key' => 't_key',
            'value_type_id' => $type->id,
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'content' => ['value' => 'Val'],
            'status' => 'active',
        ]);

        $log = AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $this->superAdmin->id,
            'action' => 'value.updated',
            'subject_type' => 'DataValue',
            'subject_id' => $dv->id,
            'old' => ['value' => 'Old'],
            'new' => ['value' => 'Val'],
        ]);

        // acting as viewer
        $this->actingAs($this->viewer);

        $restored = AuditRestorer::restore($log, $this->viewer);
        $this->assertFalse($restored);
    }

    public function test_get_affected_domains(): void
    {
        $site = Site::factory()->create(['domain' => 'affected.com']);
        $type = ValueType::firstOrCreate(['code' => 'text'], ['name' => 'Text']);
        $dv = DataValue::create([
            'key' => 't_key',
            'value_type_id' => $type->id,
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'content' => ['value' => 'Val'],
            'status' => 'active',
        ]);

        $log = AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $this->superAdmin->id,
            'action' => 'value.updated',
            'subject_type' => 'DataValue',
            'subject_id' => $dv->id,
            'old' => ['value' => 'Old', 'scope_type' => 'site', 'scope_id' => $site->id],
            'new' => ['value' => 'Val', 'scope_type' => 'site', 'scope_id' => $site->id],
        ]);

        $this->assertEquals('affected.com', $log->getAffectedDomains());

        // Для відновлення
        $restoreLog = AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $this->superAdmin->id,
            'action' => 'audit.restored',
            'subject_type' => 'AuditLog',
            'subject_id' => $log->id,
            'old' => ['action' => 'value.updated'],
            'new' => [],
        ]);

        $this->assertEquals('affected.com', $restoreLog->getAffectedDomains());
    }
}
