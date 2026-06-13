<?php

namespace App\Services\Failover;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\Site;
use Illuminate\Support\Collection;

class FailoverEngine
{
    public function __construct(private SlotResolver $resolver) {}

    /** @return Collection<int, PhoneSlot> слоти, чий видимий стан змінився */
    public function markNumberDown(PhoneNumber $number, string $source = 'system'): Collection
    {
        if ($number->status === 'down') {
            return collect();
        }

        // Знайти уражені слоти й зняти точні знімки ДО зміни статусу номера
        $slots = PhoneSlot::whereHas('entries', fn ($q) => $q->where('phone_number_id', $number->id))->get();
        $befores = $slots->mapWithKeys(fn ($s) => [$s->id => $this->resolver->resolve($s)]);

        $number->update(['status' => 'down', 'down_since' => now()]);
        $this->audit($source, 'number.down', 'phone_number', $number->id, null, ['e164' => $number->e164]);

        return $slots
            ->filter(function (PhoneSlot $slot) use ($source, $befores) {
                $slot->unsetRelations();

                return $this->recompute($slot, $source, $befores[$slot->id]);
            })
            ->values();
    }

    /** @return Collection<int, PhoneSlot> */
    public function markNumberActive(PhoneNumber $number, string $source = 'system'): Collection
    {
        if ($number->status === 'active') {
            return collect();
        }

        // Знайти уражені слоти й зняти точні знімки ДО зміни статусу номера
        $slots = PhoneSlot::whereHas('entries', fn ($q) => $q->where('phone_number_id', $number->id))->get();
        $befores = $slots->mapWithKeys(fn ($s) => [$s->id => $this->resolver->resolve($s)]);

        $number->update(['status' => 'active', 'down_since' => null]);
        $this->audit($source, 'number.recovered', 'phone_number', $number->id, null, ['e164' => $number->e164]);

        return $slots
            ->filter(function (PhoneSlot $slot) use ($source, $befores) {
                $slot->unsetRelations();

                return $this->recompute($slot, $source, $befores[$slot->id]);
            })
            ->values();
    }

    public function pin(PhoneSlot $slot, NumberEntry $entry, string $source = 'user'): void
    {
        $old = $this->resolver->resolve($slot);
        $slot->update(['pinned_number_entry_id' => $entry->id]);
        $new = $this->resolver->resolve($slot->fresh());
        $this->audit($source, 'slot.pinned', 'phone_slot', $slot->id,
            ['number' => $old->number], ['number' => $new->number, 'entry_id' => $entry->id]);
    }

    public function unpin(PhoneSlot $slot, string $source = 'user'): void
    {
        $before = $this->resolver->resolve($slot);
        $slot->update(['pinned_number_entry_id' => null]);
        $slot->unsetRelations();
        $after = $this->resolver->resolve($slot->fresh());
        $this->audit($source, 'slot.unpinned', 'phone_slot', $slot->id,
            ['number' => $before->number], ['number' => $after->number]);
        $this->recompute($slot->fresh(), $source);
    }

    /**
     * Перерахувати поточний запис слота. Повертає true, якщо стан змінився.
     *
     * @param  ResolvedSlot|null  $before  Знімок до зміни; якщо null — обчислюється через резолвер.
     */
    public function recompute(PhoneSlot $slot, string $source = 'system', ?ResolvedSlot $before = null): bool
    {
        if ($slot->pinned_number_entry_id) {
            return false; // закріплений слот не перемикається автоматично
        }

        // Точний знімок стану ДО перерахунку через резолвер (коли не передано ззовні)
        if ($before === null) {
            $before = $this->resolver->resolve($slot);
        }

        $slot->load('entries.phoneNumber');

        $candidates = $slot->entries
            ->filter(fn (NumberEntry $e) => $e->phoneNumber->status === 'active')
            ->sortBy('priority')
            ->values();

        $current = $slot->entries->firstWhere('id', $slot->current_number_entry_id);

        if ($slot->return_mode === 'sticky' && $current && $current->phoneNumber->status === 'active') {
            $next = $current; // sticky: тримаємось поточного, поки він живий
        } elseif ($slot->return_mode === 'sticky' && $current) {
            $next = $candidates->first(fn (NumberEntry $e) => $e->priority > $current->priority)
                ?? $candidates->first();
        } else {
            $next = $candidates->first(); // auto: завжди найвищий активний
        }

        $slot->current_number_entry_id = $next?->id;
        if ($next) {
            $slot->last_active_e164 = $next->phoneNumber->e164;
        }
        $slot->save();

        $after = $this->resolver->resolve($slot->fresh());

        // Виявлення змін враховує стан: number, visible і state
        $changed = $before->number !== $after->number
            || $before->visible !== $after->visible
            || $before->state !== $after->state;

        if (! $changed) {
            return false;
        }

        $this->audit($source, 'failover.switch', 'phone_slot', $slot->id,
            ['number' => $before->number, 'state' => $before->state],
            ['number' => $after->number, 'state' => $after->state]);

        // Вичерпання → критичний інцидент ЗАВЖДИ, незалежно від політики видимості
        if ($after->state === 'exhausted' && $before->state !== 'exhausted') {
            $this->incidentOnce('critical', 'slot_exhausted', $slot,
                "Слот #{$slot->id}: усі номери неактивні");
        } elseif ($before->visible && $after->visible && $before->number !== $after->number) {
            Incident::create([
                'severity' => 'warning',
                'kind' => 'failover',
                'subject_type' => 'phone_slot',
                'subject_id' => $slot->id,
                'message' => "Слот #{$slot->id}: перемкнуто {$before->number} → {$after->number}",
            ]);
        }

        return true;
    }

    /** Сайти, яких стосується слот (через область дії його значення). */
    public function sitesFor(PhoneSlot $slot): Collection
    {
        $value = $slot->dataValue;

        return $value->scope_type === 'site'
            ? Site::where('id', $value->scope_id)->get()
            : Site::where('site_group_id', $value->scope_id)->get();
    }

    private function audit(string $actorType, string $action, ?string $subjectType, ?int $subjectId, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'actor_type' => in_array($actorType, ['user', 'webhook'], true) ? $actorType : 'system',
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'old' => $old,
            'new' => $new,
        ]);
    }

    private function incidentOnce(string $severity, string $kind, PhoneSlot $slot, string $message): void
    {
        $exists = Incident::where('kind', $kind)
            ->where('subject_type', 'phone_slot')
            ->where('subject_id', $slot->id)
            ->where('status', 'new')
            ->exists();

        if (! $exists) {
            Incident::create([
                'severity' => $severity,
                'kind' => $kind,
                'subject_type' => 'phone_slot',
                'subject_id' => $slot->id,
                'message' => $message,
            ]);
        }
    }
}
