<?php

namespace App\Services\Audit;

use App\Admin\AccessControl;
use App\Admin\AffectedSites;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\User;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Illuminate\Support\Facades\DB;

class AuditRestorer
{
    /**
     * Серіалізує DataValue зі зв'язками для повного відновлення при видаленні.
     */
    public static function serializeValue(DataValue $dv): array
    {
        $dv->loadMissing(['geoTags', 'phoneSlot.entries.phoneNumber']);
        
        $data = [
            'key' => $dv->key,
            'value_type_id' => (int) $dv->value_type_id,
            'scope_type' => $dv->scope_type,
            'scope_id' => (int) $dv->scope_id,
            'content' => $dv->content,
            'status' => $dv->status,
            'geo_tag_ids' => $dv->geoTags->pluck('id')->all(),
        ];

        if ($dv->phoneSlot) {
            $data['phone_slot'] = [
                'return_mode' => $dv->phoneSlot->return_mode,
                'exhaustion_policy' => $dv->phoneSlot->exhaustion_policy,
                'entries' => $dv->phoneSlot->entries->map(fn ($e) => [
                    'priority' => (int) $e->priority,
                    'e164' => $e->phoneNumber->e164,
                    'status' => $e->phoneNumber->status ?? 'active',
                    'is_pinned' => (bool) ($e->phoneNumber->is_pinned ?? false),
                ])->all(),
            ];
        }

        return $data;
    }

    /**
     * Відтворює DataValue та його зв'язки з серіалізованих даних.
     */
    public static function restoreValue(array $data): DataValue
    {
        return DB::transaction(function () use ($data) {
            $dv = DataValue::updateOrCreate(
                [
                    'key' => $data['key'],
                    'scope_type' => $data['scope_type'],
                    'scope_id' => $data['scope_id'],
                ],
                [
                    'value_type_id' => $data['value_type_id'],
                    'content' => $data['content'],
                    'status' => $data['status'] ?? 'active',
                ]
            );

            // Очищаємо зв'язки, якщо вони вже існували
            $dv->geoTags()->detach();
            if ($dv->phoneSlot) {
                $dv->phoneSlot->entries()->delete();
                $dv->phoneSlot->delete();
            }

            if (! empty($data['geo_tag_ids'])) {
                $dv->geoTags()->sync($data['geo_tag_ids']);
            }

            if (! empty($data['phone_slot'])) {
                $ps = PhoneSlot::create([
                    'data_value_id' => $dv->id,
                    'return_mode' => $data['phone_slot']['return_mode'] ?? 'auto',
                    'exhaustion_policy' => $data['phone_slot']['exhaustion_policy'] ?? 'hide',
                ]);

                foreach ($data['phone_slot']['entries'] as $eData) {
                    $pn = PhoneNumber::firstOrCreate(
                        ['e164' => $eData['e164']],
                        [
                            'status' => $eData['status'] ?? 'active',
                            'is_pinned' => $eData['is_pinned'] ?? false,
                        ]
                    );

                    NumberEntry::create([
                        'phone_slot_id' => $ps->id,
                        'phone_number_id' => $pn->id,
                        'priority' => $eData['priority'],
                    ]);
                }
            }

            return $dv;
        });
    }

    /**
     * Перевіряє, чи має користувач права на відновлення за цим записом аудиту.
     */
    public static function canRestore(User $user, AuditLog $log): bool
    {
        $ac = app(AccessControl::class);
        if ($ac->canManageAccess($user)) {
            return true;
        }

        $scopeType = null;
        $scopeId = null;

        // Спробуємо дізнатися scope з поточного DataValue
        if ($log->subject_type === 'DataValue') {
            $dv = DataValue::find($log->subject_id);
            if ($dv) {
                $scopeType = $dv->scope_type;
                $scopeId = $dv->scope_id;
            }
        }

        // Або з старих/нових серіалізованих даних
        if (! $scopeType && is_array($log->old)) {
            $scopeType = $log->old['scope_type'] ?? null;
            $scopeId = $log->old['scope_id'] ?? null;
        }
        if (! $scopeType && is_array($log->new)) {
            $scopeType = $log->new['scope_type'] ?? null;
            $scopeId = $log->new['scope_id'] ?? null;
        }

        if ($scopeType === 'site' && $scopeId) {
            return $ac->canEditSite($user, (int) $scopeId);
        }
        if ($scopeType === 'group' && $scopeId) {
            return $ac->canEditGroup($user, (int) $scopeId);
        }

        return false;
    }

    /**
     * Виконує відкат (відновлення) зміни. Повертає true у разі успіху.
     */
    public static function restore(AuditLog $log, User $actor): bool
    {
        if (! self::canRestore($actor, $log)) {
            return false;
        }

        $success = DB::transaction(function () use ($log) {
            switch ($log->action) {
                // Створення запису ➔ Відкат полягає у видаленні
                case 'value.created':
                case 'messenger.added':
                case 'messenger.reserve_added':
                    $dv = DataValue::find($log->subject_id);
                    if ($dv) {
                        $dv->geoTags()->detach();
                        $dv->delete();
                        self::logRestoreAction($log, $dv);
                        return true;
                    }
                    break;

                // Видалення запису ➔ Відкат полягає у відтворенні
                case 'value.deleted':
                case 'slot.removed':
                case 'messenger.removed':
                    if (is_array($log->old) && isset($log->old['key'])) {
                        $dv = self::restoreValue($log->old);
                        self::logRestoreAction($log, $dv);
                        return true;
                    }
                    break;

                // Оновлення контенту ➔ Відкат полягає у записі старого контенту
                case 'value.updated':
                case 'messenger.toggled':
                case 'messenger.pinned':
                case 'messenger.unpinned':
                    $dv = DataValue::find($log->subject_id);
                    if ($dv && is_array($log->old)) {
                        $clean = collect($log->old)->except(['scope_type', 'scope_id', 'key'])->all();
                        $dv->update(['content' => $clean]);
                        self::logRestoreAction($log, $dv);
                        return true;
                    }
                    break;

                // Зміна гео-тегів ➔ Відкат полягає у відновленні старого списку тегів
                case 'value.geo_changed':
                case 'messenger.geo_changed':
                    $dv = DataValue::find($log->subject_id);
                    if ($dv && is_array($log->old) && isset($log->old['geo_tag_ids'])) {
                        $dv->geoTags()->sync($log->old['geo_tag_ids']);
                        self::logRestoreAction($log, $dv);
                        return true;
                    }
                    break;

                // Зміна ключа (перейменування) ➔ Відкат ключа
                case 'slot.renamed':
                case 'messenger.slot_renamed':
                    $dv = DataValue::find($log->subject_id);
                    if ($dv && is_array($log->old) && isset($log->old['key'])) {
                        $dv->update(['key' => $log->old['key']]);
                        self::logRestoreAction($log, $dv);
                        return true;
                    }
                    break;

                // Зміна політики вичерпання / режиму повернення / аварійного номеру месенджера
                case 'messenger.exhaustion_policy_changed':
                case 'messenger.return_mode_changed':
                case 'messenger.emergency_changed':
                case 'messenger.slot_hidden':
                case 'messenger.slot_shown':
                    $dv = DataValue::find($log->subject_id);
                    if ($dv && is_array($log->old)) {
                        $clean = collect($log->old)->except(['scope_type', 'scope_id', 'key'])->all();
                        $content = array_merge($dv->content ?? [], $clean);
                        $dv->update(['content' => $content]);
                        self::logRestoreAction($log, $dv);
                        return true;
                    }
                    break;

                case 'number.edited':
                    // subject_type = 'DataValue', subject_id = data_value_id
                    // old = ['e164' => старий, 'scope_type' => ..., 'scope_id' => ...]
                    // new = ['e164' => новий, ...]
                    $dv = DataValue::with(['phoneSlot.entries.phoneNumber'])->find($log->subject_id);
                    $oldE164 = $log->old['e164'] ?? null;
                    $newE164 = $log->new['e164'] ?? null;
                    if ($dv && $dv->phoneSlot && $oldE164 && $newE164) {
                        // Знаходимо запис із поточним (новим) номером
                        $entry = $dv->phoneSlot->entries->first(
                            fn($e) => $e->phoneNumber?->e164 === $newE164
                        );
                        if ($entry) {
                            $oldPn = PhoneNumber::firstOrCreate(
                                ['e164' => $oldE164],
                                ['status' => 'active', 'is_pinned' => false]
                            );
                            $entry->update(['phone_number_id' => $oldPn->id]);
                            self::logRestoreAction($log, $dv);
                            return true;
                        }
                    }
                    break;

                case 'phone.override_collapsed':
                    // При згортанні оверайду видаляється оверайд і підставляється батьківське значення.
                    // У полі new зберігаються site_id, source_id, key.
                    // Але відкатати це важко, якщо старий оверайд не збережено повністю.
                    break;

                case 'messenger.materialized':
                case 'phone.materialized':
                    $dv = DataValue::find($log->subject_id);
                    if ($dv) {
                        $dv->geoTags()->detach();
                        $dv->delete();
                        self::logRestoreAction($log, $dv);
                        return true;
                    }
                    break;
            }

            return false;
        });

        if ($success) {
            // Публікуємо зміни на сайти
            self::publishChanges($log);
        }

        return $success;
    }

    /**
     * Створює запис в аудиті про факт відновлення.
     */
    private static function logRestoreAction(AuditLog $originalLog, ?DataValue $dv = null, ?User $actor = null): void
    {
        $scopeType = null;
        $scopeId = null;

        if (is_array($originalLog->old)) {
            $scopeType = $originalLog->old['scope_type'] ?? null;
            $scopeId = $originalLog->old['scope_id'] ?? null;
        }
        if (! $scopeType && is_array($originalLog->new)) {
            $scopeType = $originalLog->new['scope_type'] ?? null;
            $scopeId = $originalLog->new['scope_id'] ?? null;
        }
        if (! $scopeType && $originalLog->subject_type === 'DataValue') {
            $origDv = DataValue::find($originalLog->subject_id);
            if ($origDv) {
                $scopeType = $origDv->scope_type;
                $scopeId = $origDv->scope_id;
            }
        }
        if (! $scopeType && $dv) {
            $scopeType = $dv->scope_type;
            $scopeId = $dv->scope_id;
        }

        $oldPayload = [
            'action' => $originalLog->action,
            'subject_id' => $originalLog->subject_id,
        ];
        $newPayload = [
            'restored_data_value_id' => $dv?->id,
        ];

        if ($scopeType && $scopeId) {
            $oldPayload['scope_type'] = $scopeType;
            $oldPayload['scope_id'] = (int) $scopeId;
            $newPayload['scope_type'] = $scopeType;
            $newPayload['scope_id'] = (int) $scopeId;
        }

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id'   => auth()->id(),
            'action' => 'audit.restored',
            'subject_type' => 'AuditLog',
            'subject_id' => $originalLog->id,
            'old' => $oldPayload,
            'new' => $newPayload,
        ]);
    }

    /**
     * Перераховує та публікує payload на всі зачеплені сайти.
     */
    private static function publishChanges(AuditLog $log): void
    {
        $scopeType = null;
        $scopeId = null;

        // Визначаємо область дії з логу
        if (is_array($log->old)) {
            $scopeType = $log->old['scope_type'] ?? null;
            $scopeId = $log->old['scope_id'] ?? null;
        }
        if (! $scopeType && is_array($log->new)) {
            $scopeType = $log->new['scope_type'] ?? null;
            $scopeId = $log->new['scope_id'] ?? null;
        }

        if (! $scopeType && $log->subject_type === 'DataValue') {
            $dv = DataValue::find($log->subject_id);
            if ($dv) {
                $scopeType = $dv->scope_type;
                $scopeId = $dv->scope_id;
            }
        }

        if (! $scopeType) {
            return;
        }

        // Знаходимо сайти
        $sites = collect();
        if ($scopeType === 'site') {
            $site = \App\Models\Site::find($scopeId);
            if ($site) {
                $sites->push($site);
            }
        } elseif ($scopeType === 'group') {
            $sites = \App\Models\Site::where('site_group_id', $scopeId)->get();
        }

        // Оновлюємо та пушимо на DataBridge
        $sites->each(function ($site) {
            $publication = app(SitePayloadCompiler::class)->publish($site);
            app(BridgePublisher::class)->push($publication);
        });
    }
}
