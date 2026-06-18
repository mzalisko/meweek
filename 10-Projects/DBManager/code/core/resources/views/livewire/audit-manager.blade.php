@php
    $actionLabels = [
        'value.created' => 'Створення значення',
        'value.updated' => 'Оновлення значення',
        'value.deleted' => 'Видалення значення',
        'value.geo_changed' => 'Зміна гео-таргетингу',
        'value.frozen' => 'Замороження значення',
        'messenger.added' => 'Додано месенджер',
        'messenger.toggled' => 'Перемкнуто статус месенджера',
        'messenger.pinned' => 'Закріплено месенджер',
        'messenger.unpinned' => 'Відкріплено месенджер',
        'messenger.removed' => 'Видалено месенджер',
        'messenger.slot_renamed' => 'Перейменовано слот месенджера',
        'messenger.reserve_added' => 'Додано резервний месенджер',
        'messenger.exhaustion_policy_changed' => 'Зміна політики вичерпання',
        'messenger.return_mode_changed' => 'Зміна режиму повернення',
        'messenger.emergency_changed' => 'Зміна аварійного номера',
        'messenger.geo_changed' => 'Зміна гео месенджера',
        'messenger.slot_hidden' => 'Приховано слот',
        'messenger.slot_shown' => 'Показано слот',
        'messenger.materialized' => 'Матеріалізовано месенджер',
        'slot.removed' => 'Видалено слот',
        'slot.suppressed' => 'Пригнічено слот',
        'slot.renamed' => 'Перейменовано слот',
        'phone.materialized' => 'Матеріалізовано телефон',
        'phone.override_collapsed' => 'Згорнуто оверайд',
        'number.added' => 'Додано номер',
        'number.removed' => 'Видалено номер',
        'number.reordered' => 'Зміна пріоритетів номерів',
        'number.status_changed' => 'Зміна статусу номера',
        'number.edited' => 'Редагування номера',
        'audit.restored' => 'Відновлено дані з аудиту',
        'user.login' => 'Вхід у систему',
        'user.logout' => 'Вихід із системи',
        'user.login_failed' => 'Невдала спроба входу',
        'user.created' => 'Створено користувача',
        'user.updated' => 'Оновлено користувача',
        'user.deleted' => 'Видалено користувача',
        'user.activated' => 'Активовано користувача',
        'user.deactivated' => 'Деактивовано користувача',
        'user.password_reset' => 'Скинуто пароль',
        'user.sessions_revoked' => 'Сесії користувача відкликано',
        'group.created' => 'Створено групу',
        'group.updated' => 'Оновлено групу',
        'group.archived' => 'Групу архівовано',
        'group.restored' => 'Групу відновлено',
        'site.created' => 'Створено сайт',
        'site.updated' => 'Оновлено сайт',
        'site.archived' => 'Сайт архівовано',
        'site.restored' => 'Сайт відновлено',
        'site.purged' => 'Сайт остаточно видалено',
        'site.token_issued' => 'Випущено токен',
        'site.token_revoked' => 'Відкликано токен',
        'site.token_rotated' => 'Ротовано токен',
        'number.down' => 'Номер недоступний',
        'number.recovered' => 'Номер відновлено',
        'slot.pinned' => 'Слот закріплено',
        'slot.unpinned' => 'Знято закріплення слота',
        'failover.switch' => 'Failover перемикання',
        'webhook.unknown_number' => 'Аномалія вебхуку',
    ];

    $renderDiff = function($log) use ($actionLabels) {
        $action = $log->action;
        $old = $log->old;
        $new = $log->new;

        $excludeKeys = ['scope_type', 'scope_id', 'value_type_id', 'status', 'geo_tag_ids', 'phone_slot', 'key'];

        $labels = [
            'value' => 'Значення',
            'url' => 'URL-посилання',
            'network' => 'Месенджер/Мережа',
            'emergency_value' => 'Резервне значення',
            'emergency_url' => 'Резервний URL',
            'exhaustion_policy' => 'Політика вичерпання',
            'return_mode' => 'Режим повернення',
            'enabled' => 'Активний',
            'pinned' => 'Закріплений',
            'key' => 'Ключ',
            'linked_slot' => 'Прив\'язаний телефон',
            'current_messenger_id' => 'Активний месенджер',
            'last_active_value' => 'Останнє значення',
            'last_active_url' => 'Останнє URL',
        ];

        if ($action === 'audit.restored') {
            $origAction = $old['action'] ?? 'невідома дія';
            $origLabel = $actionLabels[$origAction] ?? $origAction;
            return '<div class="inline-flex items-center gap-2 bg-acc-bg border border-acc-bd px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-acc-tx font-bold uppercase tracking-wider text-[10px]">Відновлено зміну:</span> ' .
                '<span class="text-acc-tx font-semibold text-xs">«' . htmlspecialchars($origLabel) . '»</span>' .
                '</div>';
        }

        if (in_array($action, ['value.updated', 'messenger.toggled', 'messenger.pinned', 'messenger.unpinned', 'messenger.exhaustion_policy_changed', 'messenger.return_mode_changed', 'messenger.emergency_changed', 'messenger.slot_hidden', 'messenger.slot_shown'])) {
            if (is_array($old) && is_array($new)) {
                $changes = [];
                $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
                foreach ($allKeys as $k) {
                    if (in_array($k, $excludeKeys, true)) continue;
                    $oldVal = $old[$k] ?? null;
                    $newVal = $new[$k] ?? null;
                    if ($oldVal !== $newVal) {
                        $label = $labels[$k] ?? $k;
                        $oldStr = is_bool($oldVal) ? ($oldVal ? 'так' : 'ні') : (is_array($oldVal) ? implode(', ', $oldVal) : (string)$oldVal);
                        $newStr = is_bool($newVal) ? ($newVal ? 'так' : 'ні') : (is_array($newVal) ? implode(', ', $newVal) : (string)$newVal);
                        if ($oldStr === '') $oldStr = '—';
                        if ($newStr === '') $newStr = '—';
                        
                        $changes[] = '<div class="inline-flex items-center gap-2 bg-[#f4f5f3] border border-[#e3e5e1] px-2.5 py-1 rounded-md text-xs">' .
                            '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">' . htmlspecialchars($label) . ':</span>' .
                            '<span class="text-bad-tx bg-bad-bg px-1.5 py-0.5 rounded text-[11px] line-through">' . htmlspecialchars($oldStr) . '</span>' .
                            '<span class="text-mut text-[10px]">➔</span>' .
                            '<span class="text-ok-tx bg-ok-bg px-1.5 py-0.5 rounded font-semibold text-[11px]">' . htmlspecialchars($newStr) . '</span>' .
                            '</div>';
                    }
                }
                return !empty($changes) ? '<div class="flex flex-wrap gap-2">' . implode('', $changes) . '</div>' : '<span class="text-mut text-xs">Оновлено внутрішні параметри</span>';
            }
        }

        if ($action === 'slot.renamed' || $action === 'messenger.slot_renamed') {
            return '<div class="inline-flex items-center gap-2 bg-[#f4f5f3] border border-[#e3e5e1] px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">Ключ:</span> ' .
                '<span class="text-bad-tx bg-bad-bg px-1.5 py-0.5 rounded text-[11px] line-through">' . htmlspecialchars($old['key'] ?? '—') . '</span>' .
                '<span class="text-mut text-[10px]">➔</span>' .
                '<span class="text-ok-tx bg-ok-bg px-1.5 py-0.5 rounded font-semibold text-[11px]">' . htmlspecialchars($new['key'] ?? '—') . '</span>' .
                '</div>';
        }

        if ($action === 'value.geo_changed' || $action === 'messenger.geo_changed') {
            $oldGeo = !empty($old['geo_tag_ids']) ? implode(', ', \App\Models\GeoTag::whereIn('id', $old['geo_tag_ids'])->pluck('code')->toArray()) : '—';
            $newGeo = !empty($new['geo_tag_ids']) ? implode(', ', \App\Models\GeoTag::whereIn('id', $new['geo_tag_ids'])->pluck('code')->toArray()) : '—';
            return '<div class="inline-flex items-center gap-2 bg-[#f4f5f3] border border-[#e3e5e1] px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">Гео:</span> ' .
                '<span class="text-bad-tx bg-bad-bg px-1.5 py-0.5 rounded text-[11px] line-through">' . htmlspecialchars($oldGeo) . '</span>' .
                '<span class="text-mut text-[10px]">➔</span>' .
                '<span class="text-ok-tx bg-ok-bg px-1.5 py-0.5 rounded font-semibold text-[11px]">' . htmlspecialchars($newGeo) . '</span>' .
                '</div>';
        }

        if (in_array($action, ['value.deleted', 'slot.removed', 'messenger.removed'])) {
            if (is_array($old)) {
                $details = [];
                if (isset($old['key'])) {
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Ключ:</span> <span class="bg-[#faf5f4] border border-[#f3e5e2] text-bad-tx px-1.5 py-0.5 rounded font-mono text-xs">' . htmlspecialchars($old['key']) . '</span></div>';
                }
                $content = $old['content'] ?? [];
                if (isset($content['value'])) {
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Значення:</span> <span class="text-bad-tx font-semibold text-xs">«' . htmlspecialchars($content['value']) . '»</span></div>';
                } elseif (isset($content['name'])) {
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Назва:</span> <span class="text-bad-tx font-semibold text-xs">«' . htmlspecialchars($content['name']) . '»</span></div>';
                }
                if (isset($old['phone_slot'])) {
                    $nums = collect($old['phone_slot']['entries'])->map(fn($e) => $e['e164'] . ($e['priority'] > 0 ? ' (p' . $e['priority'] . ')' : ''))->implode(', ');
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Телефони в слоті:</span> <span class="text-bad-tx font-mono text-xs">' . htmlspecialchars($nums) . '</span></div>';
                }
                return '<div class="inline-flex flex-wrap items-center gap-x-4 gap-y-1.5 bg-[#fbf5f4] border border-[#f3e5e2] px-3 py-1.5 rounded-lg">' . implode('', $details) . '</div>';
            }
            return '<span class="text-bad-tx bg-bad-bg px-2 py-0.5 rounded-md text-xs font-semibold">Дані видалено</span>';
        }

        if (in_array($action, ['value.created', 'messenger.added', 'messenger.reserve_added'])) {
            if (is_array($new)) {
                $details = [];
                if (isset($new['key'])) {
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Ключ:</span> <span class="bg-[#eef8ef] border border-[#cbeed2] text-ok-tx px-1.5 py-0.5 rounded font-mono text-xs">' . htmlspecialchars($new['key']) . '</span></div>';
                }
                $content = $new['content'] ?? [];
                if (isset($content['value'])) {
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Значення:</span> <span class="text-ok-tx font-semibold text-xs">«' . htmlspecialchars($content['value']) . '»</span></div>';
                }
                if (isset($new['phone_slot'])) {
                    $nums = collect($new['phone_slot']['entries'])->pluck('e164')->implode(', ');
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Телефони:</span> <span class="text-ok-tx font-mono text-xs">' . htmlspecialchars($nums) . '</span></div>';
                }
                return '<div class="inline-flex flex-wrap items-center gap-x-4 gap-y-1.5 bg-[#eef8ef] border border-[#cbeed2] px-3 py-1.5 rounded-lg">' . implode('', $details) . '</div>';
            }
            return '<span class="text-ok-tx bg-ok-bg px-2 py-0.5 rounded-md text-xs font-semibold">Створено нове значення</span>';
        }

        if ($action === 'number.added') {
            return '<div class="inline-flex items-center gap-2 bg-ok-bg border border-[#cbeed2] px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-ok-tx font-bold uppercase tracking-wider text-[10px]">Додано номер:</span> ' .
                '<span class="text-ok-tx font-semibold font-mono text-xs">' . htmlspecialchars($new['e164'] ?? '—') . '</span>' .
                '<span class="text-mut text-[10px]"> (пріоритет ' . ($new['priority'] ?? 0) . ')</span>' .
                '</div>';
        }
        if ($action === 'number.removed') {
            return '<div class="inline-flex items-center gap-2 bg-bad-bg border border-[#f3e5e2] px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-bad-tx font-bold uppercase tracking-wider text-[10px]">Видалено номер:</span> ' .
                '<span class="text-bad-tx font-mono text-xs">' . htmlspecialchars($old['e164'] ?? '—') . '</span>' .
                '</div>';
        }
        if ($action === 'number.edited') {
            return '<div class="inline-flex items-center gap-2 bg-[#f4f5f3] border border-[#e3e5e1] px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">Номер:</span> ' .
                '<span class="text-bad-tx bg-bad-bg px-1.5 py-0.5 rounded text-[11px] font-mono line-through">' . htmlspecialchars($old['e164'] ?? '—') . '</span>' .
                '<span class="text-mut text-[10px]">➔</span>' .
                '<span class="text-ok-tx bg-ok-bg px-1.5 py-0.5 rounded font-semibold text-[11px] font-mono">' . htmlspecialchars($new['e164'] ?? '—') . '</span>' .
                '</div>';
        }
        if ($action === 'number.status_changed') {
            return '<div class="inline-flex items-center gap-2 bg-[#f4f5f3] border border-[#e3e5e1] px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">Статус номера:</span> ' .
                '<span class="text-bad-tx bg-bad-bg px-1.5 py-0.5 rounded text-[11px] font-mono">' . htmlspecialchars($old['status'] ?? '—') . '</span>' .
                '<span class="text-mut text-[10px]">➔</span>' .
                '<span class="text-ok-tx bg-ok-bg px-1.5 py-0.5 rounded font-semibold text-[11px] font-mono">' . htmlspecialchars($new['status'] ?? '—') . '</span>' .
                '</div>';
        }
        if (is_array($old) || is_array($new)) {
            return '<span class="text-mut text-xs">Зміни структури даних</span>';
        }
        return '—';
    };

    $renderSystemDetails = function($log) {
        $action = $log->action;
        $new = $log->new;
        $old = $log->old;

        $labels = [
            'name' => 'Назва',
            'domain' => 'Домен',
            'country_hint' => 'Країна',
            'site_group_id' => 'ID групи',
            'parent_site_id' => 'ID джерела',
            'email' => 'Email',
            'role' => 'Роль',
            'status' => 'Статус',
            'is_active' => 'Активний',
        ];

        if ($action === 'user.login') {
            return 'Вхід з IP: <span class="font-mono bg-acc-bg px-1.5 py-0.5 rounded text-xs">' . htmlspecialchars($new['ip'] ?? '—') . '</span>';
        }
        if ($action === 'user.logout') {
            return 'Вихід з IP: <span class="font-mono bg-acc-bg px-1.5 py-0.5 rounded text-xs">' . htmlspecialchars($new['ip'] ?? '—') . '</span>';
        }
        if ($action === 'user.login_failed') {
            return 'Невдалий вхід для: <span class="text-bad-tx font-bold font-mono text-xs">' . htmlspecialchars($new['email'] ?? '—') . '</span> (IP: ' . htmlspecialchars($new['ip'] ?? '—') . ')';
        }
        if ($action === 'failover.switch') {
            return 'Failover: перемикання активної лінії з ID ' . ($old['current_entry_id'] ?? 'none') . ' ➔ ID ' . ($new['current_entry_id'] ?? 'none');
        }
        if ($action === 'number.down') {
            return 'Номер ліній вимкнено: <span class="text-bad-tx font-bold font-mono bg-bad-bg px-2 py-0.5 rounded-md text-xs">' . htmlspecialchars($new['e164'] ?? '—') . '</span>';
        }
        if ($action === 'number.recovered') {
            return 'Номер відновлено в мережу: <span class="text-ok-tx font-bold font-mono bg-ok-bg px-2 py-0.5 rounded-md text-xs">' . htmlspecialchars($new['e164'] ?? '—') . '</span>';
        }
        if ($action === 'slot.pinned') {
            return 'Слот закріплено на номері ID ' . ($new['pinned_entry_id'] ?? 'none');
        }
        if ($action === 'slot.unpinned') {
            return 'Знято закріплення слота';
        }
        if ($action === 'webhook.unknown_number') {
            return 'Аномалія вебхуку (невідомий номер): <span class="text-bad-tx font-bold font-mono text-xs">' . htmlspecialchars($new['e164'] ?? '—') . '</span> (IP: ' . htmlspecialchars($new['ip'] ?? '—') . ')';
        }
        if ($action === 'site.token_issued' || $action === 'site.token_revoked' || $action === 'site.token_rotated') {
            return 'Операція токена: <span class="bg-[#f4f5f3] px-1.5 py-0.5 rounded font-semibold text-xs">' . str_replace('site.token_', '', $action) . '</span>';
        }

        // Рендеринг для створення/оновлення/видалення сайтів, груп, користувачів
        if (in_array($action, ['site.created', 'site.updated', 'site.archived', 'site.restored', 'site.purged', 'group.created', 'group.updated', 'group.archived', 'group.restored', 'user.created', 'user.updated', 'user.deleted', 'user.activated', 'user.deactivated', 'user.password_reset'])) {
            $changes = [];

            if ($action === 'site.created' || $action === 'group.created' || $action === 'user.created') {
                if (is_array($new)) {
                    foreach ($new as $k => $v) {
                        if (in_array($k, ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'])) continue;
                        $label = $labels[$k] ?? $k;
                        $vStr = is_bool($v) ? ($v ? 'так' : 'ні') : (string)$v;
                        $changes[] = '<strong>' . htmlspecialchars($label) . ':</strong> «' . htmlspecialchars($vStr) . '»';
                    }
                    return '<div class="text-[#3a7c4f] text-xs">Створено об\'єкт: ' . implode(', ', $changes) . '</div>';
                }
            } elseif ($action === 'site.updated' || $action === 'group.updated' || $action === 'user.updated') {
                if (is_array($old) && is_array($new)) {
                    $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
                    foreach ($allKeys as $k) {
                        if (in_array($k, ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'])) continue;
                        $oldVal = $old[$k] ?? null;
                        $newVal = $new[$k] ?? null;
                        if ($oldVal !== $newVal) {
                            $label = $labels[$k] ?? $k;
                            $oldStr = is_bool($oldVal) ? ($oldVal ? 'так' : 'ні') : (string)$oldVal;
                            $newStr = is_bool($newVal) ? ($newVal ? 'так' : 'ні') : (string)$newVal;
                            if ($oldStr === '') $oldStr = '—';
                            if ($newStr === '') $newStr = '—';
                            $changes[] = '<div class="my-1"><strong>' . htmlspecialchars($label) . ':</strong> <span class="text-[#a85c52] bg-[#f3e7e4] px-1.5 py-0.5 rounded line-through text-xs">' . htmlspecialchars($oldStr) . '</span> ➔ <span class="text-[#3a7c4f] bg-[#eef8ef] px-1.5 py-0.5 rounded font-semibold text-xs">' . htmlspecialchars($newStr) . '</span></div>';
                        }
                    }
                    return !empty($changes) ? implode('', $changes) : '<span class="text-mut text-xs">Оновлено системні метадані</span>';
                }
            } else {
                // archived, restored, purged, activated, deactivated тощо
                $parts = explode('.', $action);
                $subj = $parts[1] ?? '';
                $subjLabels = [
                    'archived' => 'Заархівовано',
                    'restored' => 'Відновлено з архіву',
                    'purged' => 'Остаточно видалено',
                    'deleted' => 'Видалено',
                    'activated' => 'Активовано',
                    'deactivated' => 'Деактивовано',
                    'password_reset' => 'Скинуто пароль',
                ];
                $label = $subjLabels[$subj] ?? $subj;
                $name = '';
                if (is_array($old)) {
                    $name = $old['domain'] ?? ($old['name'] ?? ($old['email'] ?? ''));
                }
                $color = in_array($subj, ['purged', 'deleted', 'deactivated']) ? 'text-[#a85c52]' : 'text-[#3a7c4f]';
                return '<div class="' . $color . ' text-xs">' . htmlspecialchars($label) . ': <strong>' . htmlspecialchars($name) . '</strong></div>';
            }
        }

        if (is_array($new)) {
            $formatted = [];
            foreach ($new as $k => $v) {
                if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                $formatted[] = '<strong>' . htmlspecialchars($k) . ':</strong> ' . htmlspecialchars((string)$v);
            }
            return '<div class="text-xs">' . implode(', ', $formatted) . '</div>';
        }
        return '—';
    };
@endphp

<x-slot name="breadcrumb">
    <div class="flex items-center gap-3 ml-2">
        <span class="text-mut text-sm select-none">/</span>
        <div class="inline-flex items-center bg-[#f4f5f3] px-3 py-1.5 rounded-lg border border-[#e3e5e1] text-xs font-bold text-ink select-none">
            Аудит
        </div>
    </div>
</x-slot>

<div class="flex-1 min-h-0 p-5 overflow-y-auto">
    {{-- Tabs --}}
    <div class="flex border-b border-[#dfe3e0] mb-5 gap-6">
        <button wire:click="$set('activeTab', 'changes')"
            class="pb-3 text-sm font-bold transition-colors {{ $activeTab === 'changes' ? 'border-b-2 border-acc text-acc-tx' : 'text-mut hover:text-ink' }}">
            Історія змін даних
        </button>
        <button wire:click="$set('activeTab', 'systems')"
            class="pb-3 text-sm font-bold transition-colors {{ $activeTab === 'systems' ? 'border-b-2 border-acc text-acc-tx' : 'text-mut hover:text-ink' }}">
            Системні логи
        </button>
    </div>

    {{-- Error messages --}}
    @error('restore')
        <div class="mb-4 rounded-lg border border-bad bg-bad-bg px-4 py-3 text-sm text-bad-tx">
            {{ $message }}
        </div>
    @enderror

    @if($activeTab === 'changes')
        @if($selectedSiteId || $selectedGroupId)
            {{-- DETAILED TIMELINE VIEW (DRILL DOWN) --}}
            <div class="bg-white border border-[#dfe3e0] rounded-lg p-5 mb-5 shadow-sm">
                <div class="flex justify-between items-center mb-5 border-b border-[#edf0ed] pb-4">
                    <div>
                        <h2 class="text-base font-bold text-ink flex items-center gap-2 flex-wrap">
                            <span>Стрічка змін для:</span> 
                            <span class="inline-flex items-center rounded-lg bg-acc-bg px-3 py-1 font-mono text-sm font-bold text-acc-tx border border-acc-bd whitespace-nowrap">
                                {{ $siteModel ? $siteModel->domain : ($groupModel ? $groupModel->name : '') }}
                            </span>
                        </h2>
                        <span class="text-xs text-mut mt-1.5 block">Хронологічний перелік змін та можливість відкату</span>
                    </div>
                    <button wire:click="selectSite(null)" 
                        class="rounded-lg border border-[#dfe3e0] px-4 py-2 text-sm font-bold text-mut hover:border-acc hover:text-acc-tx transition-colors bg-white">
                        ➔ Повернутись до зведеного списку
                    </button>
                </div>

                {{-- Filters --}}
                <div class="flex flex-wrap gap-3 items-center mb-5 text-sm text-mut">
                    <input wire:model.live.debounce.250ms="search" type="text" placeholder="Пошук вмісту..."
                        class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 w-56 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                    
                    <select wire:model.live="actionFilter" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                        <option value="">Усі дії</option>
                        @foreach(self::CHANGE_ACTIONS as $act)
                            <option value="{{ $act }}">{{ $actionLabels[$act] ?? $act }}</option>
                        @endforeach
                    </select>

                    <input wire:model.live="dateFrom" type="date" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                    <span>до</span>
                    <input wire:model.live="dateTo" type="date" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">

                    <button wire:click="resetFilters" class="ml-auto text-sm text-mut hover:text-ink font-semibold">Очистити фільтри ✕</button>
                </div>

                {{-- Timeline Table --}}
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-sm border-collapse border border-[#dfe3e0] rounded-lg overflow-hidden">
                        <thead>
                            <tr class="bg-[#f6f8f6] border-b border-[#dfe3e0] text-xs uppercase tracking-wider text-mut font-bold">
                                <th class="p-3.5 w-36">Час</th>
                                <th class="p-3.5 w-44">Користувач</th>
                                <th class="p-3.5 w-40">Дія</th>
                                <th class="p-3.5">Різниця значень (Старе ➔ Нове)</th>
                                <th class="p-3.5 w-24 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#edf0ed]">
                            @forelse($logs as $log)
                                <tr class="hover:bg-[#fafbfa] transition-colors">
                                    <td class="p-3.5 text-mut whitespace-nowrap text-xs border-b border-[#edf0ed]">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                                    <td class="p-3.5 text-ink truncate max-w-[170px] border-b border-[#edf0ed]" title="{{ $log->actor_id ? \App\Models\User::find($log->actor_id)?->email : 'Система' }}">
                                        {{ $log->actor_id ? \App\Models\User::find($log->actor_id)?->name : 'Система' }}
                                    </td>
                                    <td class="p-3.5 border-b border-[#edf0ed]">
                                        <span class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold
                                            {{ str_contains($log->action, 'create') || str_contains($log->action, 'add') ? 'bg-ok-bg text-ok-tx' : '' }}
                                            {{ str_contains($log->action, 'delete') || str_contains($log->action, 'remove') ? 'bg-bad-bg text-bad-tx' : '' }}
                                            {{ str_contains($log->action, 'update') || str_contains($log->action, 'change') || str_contains($log->action, 'rename') ? 'bg-warn-bg text-warn-tx' : '' }}
                                            {{ str_contains($log->action, 'restore') ? 'bg-acc-bg text-acc-tx' : '' }}
                                            {{ !str_contains($log->action, 'create') && !str_contains($log->action, 'delete') && !str_contains($log->action, 'remove') && !str_contains($log->action, 'update') && !str_contains($log->action, 'change') && !str_contains($log->action, 'rename') && !str_contains($log->action, 'restore') ? 'bg-[#f4f5f3] text-mut' : '' }}
                                        ">
                                            {{ $actionLabels[$log->action] ?? $log->action }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 text-sm text-ink border-b border-[#edf0ed]">{!! $renderDiff($log) !!}</td>
                                    <td class="p-3.5 text-right whitespace-nowrap border-b border-[#edf0ed]">
                                        @if(in_array($log->action, ['value.updated', 'value.geo_changed', 'slot.renamed', 'messenger.slot_renamed', 'value.deleted', 'slot.removed', 'messenger.removed', 'value.created', 'messenger.added', 'messenger.reserve_added', 'messenger.exhaustion_policy_changed', 'messenger.return_mode_changed', 'messenger.emergency_changed', 'messenger.slot_hidden', 'messenger.slot_shown', 'number.added', 'number.removed', 'number.status_changed', 'number.edited']))
                                            <button wire:click="restore({{ $log->id }})"
                                                wire:confirm="Ви впевнені, що хочете відкатати цю зміну назад?"
                                                class="rounded-md border border-acc bg-acc-bg px-3 py-1.5 text-xs font-bold text-acc-tx hover:bg-[#e6edf2] transition-colors">
                                                Відновити
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-mut text-sm">Записи змін відсутні</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-5">
                    {{ $logs->links() }}
                </div>
            </div>
        @else
            {{-- SUMMARY VIEW (GENERAL SITES/GROUPS LIST) --}}
            <div class="bg-white border border-[#dfe3e0] rounded-lg p-5 shadow-sm">
                <div class="border-b border-[#edf0ed] pb-4 mb-4">
                    <h2 class="text-base font-bold text-ink">Узагальнена історія змін даних</h2>
                    <span class="text-xs text-mut mt-1 block">Виберіть сайт або групу нижче, щоб детально переглянути історію правок та зробити відкат.</span>
                </div>

                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-sm border-collapse border border-[#dfe3e0] rounded-lg overflow-hidden">
                        <thead>
                            <tr class="bg-[#f6f8f6] border-b border-[#dfe3e0] text-xs uppercase tracking-wider text-mut font-bold">
                                <th class="p-3.5">Сайт</th>
                                <th class="p-3.5 text-center w-48">Кількість змін</th>
                                <th class="p-3.5 w-56">Остання зміна</th>
                                <th class="p-3.5 w-32 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#edf0ed]">
                            @forelse($summary as $item)
                                <tr class="hover:bg-[#fafbfa] transition-colors">
                                    <td class="p-3.5 font-bold text-ink text-sm border-b border-[#edf0ed]">{{ $item['name'] }}</td>
                                    <td class="p-3.5 text-center text-ink font-mono font-bold text-sm border-b border-[#edf0ed]">{{ $item['count'] }}</td>
                                    <td class="p-3.5 text-mut text-xs border-b border-[#edf0ed]">{{ $item['last_changed'] ? $item['last_changed']->format('d.m.Y H:i:s') : '—' }}</td>
                                    <td class="p-3.5 text-right border-b border-[#edf0ed]">
                                        <button wire:click="selectSite({{ $item['id'] }})"
                                            class="rounded-lg border border-[#dfe3e0] px-3 py-1.5 text-xs font-bold text-mut hover:border-acc hover:text-acc-tx transition-colors bg-white whitespace-nowrap">
                                            Детальніше ➔
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-8 text-center text-mut text-sm">Зміни не зафіксовані в системі</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @else
        {{-- SYSTEM LOGS VIEW --}}
        <div class="bg-white border border-[#dfe3e0] rounded-lg p-5 shadow-sm">
            <div class="border-b border-[#edf0ed] pb-4 mb-4">
                <h2 class="text-base font-bold text-ink">Системні логи та авторизація</h2>
                <span class="text-xs text-mut mt-1 block">Сесії користувачів, failover перемикання ліній, вебхуки, логи конфігурації сайтів/груп</span>
            </div>

            {{-- Filters --}}
            <div class="flex flex-wrap gap-3 items-center mb-5 text-sm text-mut">
                <input wire:model.live.debounce.250ms="search" type="text" placeholder="Пошук вмісту..."
                    class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 w-56 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                
                <select wire:model.live="actionFilter" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                    <option value="">Усі події</option>
                    @foreach(self::SYSTEM_ACTIONS as $act)
                        <option value="{{ $act }}">{{ $actionLabels[$act] ?? $act }}</option>
                    @endforeach
                </select>

                <select wire:model.live="actorFilter" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                    <option value="">Усі актори</option>
                    <option value="system">Система</option>
                    <option value="webhook">Вебхуки</option>
                    @foreach(\App\Models\User::all() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} (Користувач)</option>
                    @endforeach
                </select>

                <input wire:model.live="dateFrom" type="date" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                <span>до</span>
                <input wire:model.live="dateTo" type="date" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">

                <button wire:click="resetFilters" class="ml-auto text-sm text-mut hover:text-ink font-semibold">Очистити фільтри ✕</button>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-sm border-collapse border border-[#dfe3e0] rounded-lg overflow-hidden">
                    <thead>
                        <tr class="bg-[#f6f8f6] border-b border-[#dfe3e0] text-xs uppercase tracking-wider text-mut font-bold">
                            <th class="p-3.5 w-36">Час</th>
                            <th class="p-3.5 w-44">Подія</th>
                            <th class="p-3.5 w-44">Актор</th>
                            <th class="p-3.5">Подробиці / Лог</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#edf0ed]">
                        @forelse($logs as $log)
                            <tr class="hover:bg-[#fafbfa] transition-colors">
                                <td class="p-3.5 text-mut whitespace-nowrap text-xs border-b border-[#edf0ed]">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                                <td class="p-3.5 border-b border-[#edf0ed]">
                                    <span class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold
                                        {{ str_contains($log->action, 'login_failed') || str_contains($log->action, 'down') || str_contains($log->action, 'anomaly') ? 'bg-bad-bg text-bad-tx' : '' }}
                                        {{ str_contains($log->action, 'login') && !str_contains($log->action, 'failed') || str_contains($log->action, 'recovered') ? 'bg-ok-bg text-ok-tx' : '' }}
                                        {{ str_contains($log->action, 'switch') || str_contains($log->action, 'pinned') ? 'bg-warn-bg text-warn-tx' : '' }}
                                        {{ !str_contains($log->action, 'login') && !str_contains($log->action, 'switch') && !str_contains($log->action, 'pinned') && !str_contains($log->action, 'down') && !str_contains($log->action, 'recovered') && !str_contains($log->action, 'anomaly') ? 'bg-[#f4f5f3] text-mut' : '' }}
                                    ">
                                        {{ $actionLabels[$log->action] ?? $log->action }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-ink truncate max-w-[170px] border-b border-[#edf0ed]" title="{{ $log->actor_type === 'user' ? (\App\Models\User::find($log->actor_id)?->email ?? 'Користувач') : ucfirst($log->actor_type) }}">
                                    @if($log->actor_type === 'user')
                                        {{ \App\Models\User::find($log->actor_id)?->name ?? 'Користувач' }}
                                    @else
                                        <span class="text-mut font-semibold text-xs">{{ ucfirst($log->actor_type) }}</span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-sm text-ink border-b border-[#edf0ed]">{!! $renderSystemDetails($log) !!}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-mut text-sm">Записи логів відсутні</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-5">
                {{ $logs->links() }}
            </div>
        </div>
    @endif
</div>
