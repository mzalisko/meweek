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
        'slot.hidden' => 'Приховано слот',
        'slot.shown' => 'Показано слот',
        'phone.materialized' => 'Матеріалізовано телефон',
        'phone.override_collapsed' => 'Згорнуто оверайд',
        'number.added' => 'Додано номер',
        'number.removed' => 'Видалено номер',
        'number.reordered' => 'Зміна пріоритетів номерів',
        'number.status_changed' => 'Зміна статусу номера',
        'number.edited' => 'Редагування номера',
        'audit.restored' => 'Відновлено дані з аудиту',
        'bulk.replace_text' => 'Масова заміна тексту',
        'bulk.set_value' => 'Масове встановлення значення',
        'bulk.set_geo' => 'Масова зміна гео-тегів',
        'bulk.set_status' => 'Масове перемикання слотів',
        'bulk.replace_phone' => 'Масова заміна телефону',
        'bulk.set_phone_status' => 'Масова зміна статусу телефонів',
        'bulk.set_phone_format' => 'Масова зміна формату телефонів',
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

        $excludeKeys = ['scope_type', 'scope_id', 'value_type_id', 'geo_tag_ids', 'phone_slot'];
        if ($action !== 'bulk.replace_text') {
            $excludeKeys[] = 'key';
        }
        if ($action !== 'bulk.set_status' && $action !== 'value.updated') {
            $excludeKeys[] = 'status';
        }

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
            'status' => 'Стан',
            'key' => 'Ключ',
            'linked_slot' => 'Прив\'язаний телефон',
            'prices' => 'Список цін',
            'current_messenger_id' => 'Активний месенджер',
            'last_active_value' => 'Останнє значення',
            'last_active_url' => 'Останнє URL',
            'phone_format' => 'Формат телефону',
        ];

        if ($action === 'audit.restored') {
            $origAction = $old['action'] ?? 'невідома дія';
            $origLabel = $actionLabels[$origAction] ?? $origAction;
            return '<div class="inline-flex items-center gap-2 bg-acc-bg border border-acc-bd px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-acc-tx font-bold uppercase tracking-wider text-[10px]">Відновлено зміну:</span> ' .
                '<span class="text-acc-tx font-semibold text-xs">«' . htmlspecialchars($origLabel) . '»</span>' .
                '</div>';
        }

        if (in_array($action, ['value.updated', 'messenger.toggled', 'messenger.pinned', 'messenger.unpinned', 'messenger.exhaustion_policy_changed', 'messenger.return_mode_changed', 'messenger.emergency_changed', 'messenger.slot_hidden', 'messenger.slot_shown', 'slot.hidden', 'slot.shown', 'bulk.replace_text', 'bulk.set_value', 'bulk.set_status', 'bulk.set_phone_format'])) {
            if (is_array($old) && is_array($new)) {
                $changes = [];
                $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
                foreach ($allKeys as $k) {
                    if (in_array($k, $excludeKeys, true)) continue;
                    $oldVal = $old[$k] ?? null;
                    $newVal = $new[$k] ?? null;
                    if ($oldVal !== $newVal) {
                        $label = $labels[$k] ?? $k;
                        if ($k === 'prices') {
                            $formatPrices = function($val) {
                                if (!is_array($val)) return (string)$val;
                                $parts = [];
                                foreach ($val as $p) {
                                    $lbl = !empty($p['label']) ? $p['label'] : 'Ціна';
                                    $geo = !empty($p['geo']) ? implode(',', $p['geo']) : 'WORLD';
                                    $parts[] = $lbl . ': ' . ($p['value'] ?? '') . ' [' . $geo . ']';
                                }
                                return implode(' | ', $parts);
                            };
                            $oldStr = $formatPrices($oldVal);
                            $newStr = $formatPrices($newVal);
                        } else {
                            if ($k === 'status') {
                                $statusMap = ['active' => 'активний', 'hidden' => 'прихований', 'down' => 'збій'];
                                $oldStr = $statusMap[$oldVal] ?? (string)$oldVal;
                                $newStr = $statusMap[$newVal] ?? (string)$newVal;
                            } else {
                                $oldStr = is_bool($oldVal) ? ($oldVal ? 'так' : 'ні') : (is_array($oldVal) ? implode(', ', $oldVal) : (string)$oldVal);
                                $newStr = is_bool($newVal) ? ($newVal ? 'так' : 'ні') : (is_array($newVal) ? implode(', ', $newVal) : (string)$newVal);
                            }
                        }
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

        if ($action === 'value.geo_changed' || $action === 'messenger.geo_changed' || $action === 'bulk.set_geo') {
            $getGeoString = function ($data) {
                if (!empty($data['geo_tag_ids'])) {
                    return implode(', ', \App\Models\GeoTag::whereIn('id', $data['geo_tag_ids'])->pluck('code')->toArray());
                }
                if (!empty($data['geo'])) {
                    return implode(', ', $data['geo']);
                }
                return '—';
            };
            $oldGeo = $getGeoString($old);
            $newGeo = $getGeoString($new);
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
                $key = $new['key'] ?? null;
                if (!$key && $log->subject_type === 'DataValue' && $log->subject_id) {
                    $key = \App\Models\DataValue::where('id', $log->subject_id)->value('key');
                }
                if ($key) {
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Ключ:</span> <span class="bg-[#eef8ef] border border-[#cbeed2] text-ok-tx px-1.5 py-0.5 rounded font-mono text-xs">' . htmlspecialchars($key) . '</span></div>';
                }

                $content = $new['content'] ?? $new;
                if (isset($content['value'])) {
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Значення:</span> <span class="text-ok-tx font-semibold text-xs">«' . htmlspecialchars($content['value']) . '»</span></div>';
                } elseif (isset($content['url'])) {
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">URL:</span> <span class="text-ok-tx font-semibold text-xs">«' . htmlspecialchars($content['url']) . '»</span></div>';
                }
                if (isset($content['prices'])) {
                    $formatPrices = function($val) {
                        if (!is_array($val)) return (string)$val;
                        $parts = [];
                        foreach ($val as $p) {
                            $lbl = !empty($p['label']) ? $p['label'] : 'Ціна';
                            $geo = !empty($p['geo']) ? implode(',', $p['geo']) : 'WORLD';
                            $parts[] = $lbl . ': ' . ($p['value'] ?? '') . ' [' . $geo . ']';
                        }
                        return implode(' | ', $parts);
                    };
                    $details[] = '<div class="flex items-center gap-1.5"><span class="text-mut font-bold uppercase tracking-wider text-[10px]">Ціни:</span> <span class="text-ok-tx font-semibold text-xs">' . htmlspecialchars($formatPrices($content['prices'])) . '</span></div>';
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
        if ($action === 'bulk.replace_phone') {
            return '<div class="inline-flex items-center gap-2 bg-[#f4f5f3] border border-[#e3e5e1] px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">Номер телефону:</span> ' .
                '<span class="text-bad-tx bg-bad-bg px-1.5 py-0.5 rounded text-[11px] font-mono line-through">' . htmlspecialchars($old['phone'] ?? '—') . '</span>' .
                '<span class="text-mut text-[10px]">➔</span>' .
                '<span class="text-ok-tx bg-ok-bg px-1.5 py-0.5 rounded font-semibold text-[11px] font-mono">' . htmlspecialchars($new['phone'] ?? '—') . '</span>' .
                '</div>';
        }
        if ($action === 'bulk.set_phone_status') {
            $statusMap = ['active' => 'Активний', 'down' => 'Збій'];
            $oldSt = $statusMap[$old['phone_status'] ?? ''] ?? ($old['phone_status'] ?? '—');
            $newSt = $statusMap[$new['phone_status'] ?? ''] ?? ($new['phone_status'] ?? '—');
            return '<div class="inline-flex items-center gap-2 bg-[#f4f5f3] border border-[#e3e5e1] px-2.5 py-1 rounded-md text-xs">' .
                '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">Номер:</span> ' .
                '<span class="font-mono font-semibold text-[11px] text-ink mr-2">' . htmlspecialchars($new['phone'] ?? '—') . '</span>' .
                '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">Статус:</span> ' .
                '<span class="text-bad-tx bg-bad-bg px-1.5 py-0.5 rounded text-[11px] line-through">' . htmlspecialchars($oldSt) . '</span>' .
                '<span class="text-mut text-[10px]">➔</span>' .
                '<span class="text-ok-tx bg-ok-bg px-1.5 py-0.5 rounded font-semibold text-[11px]">' . htmlspecialchars($newSt) . '</span>' .
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

    $failoverStateLabels = [
        'ok' => 'Основний',
        'on_reserve' => 'Резерв',
        'pinned' => 'Закріплено',
        'exhausted' => 'Вичерпано',
    ];

    $renderFailoverDetails = function($log) use ($failoverStateLabels) {
        $old = is_array($log->old) ? $log->old : [];
        $new = is_array($log->new) ? $log->new : [];
        $triggerStatus = $new['trigger_status'] ?? $old['trigger_status'] ?? null;
        $triggerNumber = $new['trigger_number'] ?? $old['trigger_number'] ?? ($old['number'] ?? null);
        $activeNumber = $new['number'] ?? null;

        if ($log->action === 'number.down') {
            return '<div class="inline-flex items-center gap-2 rounded-lg border border-[#f3e5e2] bg-bad-bg px-3 py-1.5 text-xs text-bad-tx">' .
                '<span class="font-bold uppercase tracking-wider text-[10px]">Впав номер</span>' .
                '<span class="font-mono font-bold">' . htmlspecialchars($new['e164'] ?? '—') . '</span>' .
                '</div>';
        }

        if ($log->action === 'number.recovered') {
            return '<div class="inline-flex items-center gap-2 rounded-lg border border-[#cbeed2] bg-ok-bg px-3 py-1.5 text-xs text-ok-tx">' .
                '<span class="font-bold uppercase tracking-wider text-[10px]">Номер відновлено</span>' .
                '<span class="font-mono font-bold">' . htmlspecialchars($new['e164'] ?? '—') . '</span>' .
                '</div>';
        }

        $eventLabel = match ($triggerStatus) {
            'down' => 'Номер впав',
            'active' => 'Номер відновився',
            default => 'Перерахунок лінії',
        };
        $eventClass = match ($triggerStatus) {
            'down' => 'border-[#f3e5e2] bg-bad-bg text-bad-tx',
            'active' => 'border-[#cbeed2] bg-ok-bg text-ok-tx',
            default => 'border-[#eed8a0] bg-warn-bg text-warn-tx',
        };
        $oldState = $failoverStateLabels[$old['state'] ?? ''] ?? ($old['state'] ?? '—');
        $newState = $failoverStateLabels[$new['state'] ?? ''] ?? ($new['state'] ?? '—');

        return '<div class="flex flex-wrap items-center gap-2">' .
            '<span class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-bold ' . $eventClass . '">' .
                '<span>' . htmlspecialchars($eventLabel) . '</span>' .
                '<span class="font-mono">' . htmlspecialchars($triggerNumber ?? '—') . '</span>' .
            '</span>' .
            '<span class="inline-flex items-center gap-2 rounded-lg border border-[#e3e5e1] bg-[#f4f5f3] px-3 py-1.5 text-xs">' .
                '<span class="text-mut font-bold uppercase tracking-wider text-[10px]">Було</span>' .
                '<span class="font-mono text-bad-tx">' . htmlspecialchars($old['number'] ?? '—') . '</span>' .
                '<span class="text-mut">(' . htmlspecialchars($oldState) . ')</span>' .
            '</span>' .
            '<span class="inline-flex items-center gap-2 rounded-lg border border-[#cbeed2] bg-ok-bg px-3 py-1.5 text-xs">' .
                '<span class="text-ok-tx font-bold uppercase tracking-wider text-[10px]">Активний зараз</span>' .
                '<span class="font-mono font-bold text-ok-tx">' . htmlspecialchars($activeNumber ?? '—') . '</span>' .
                '<span class="text-ok-tx">(' . htmlspecialchars($newState) . ')</span>' .
            '</span>' .
        '</div>';
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
        @if($auditAccess['changes'] ?? false)
            <button wire:click="$set('activeTab', 'changes')"
                class="pb-3 text-sm font-bold transition-colors {{ $activeTab === 'changes' ? 'border-b-2 border-acc text-acc-tx' : 'text-mut hover:text-ink' }}">
                Історія змін даних
            </button>
        @endif
        @if($auditAccess['failover'] ?? false)
            <button wire:click="$set('activeTab', 'failover')"
                class="pb-3 text-sm font-bold transition-colors {{ $activeTab === 'failover' ? 'border-b-2 border-acc text-acc-tx' : 'text-mut hover:text-ink' }}">
                Failover
            </button>
        @endif
        @if($auditAccess['users'] ?? false)
            <button wire:click="$set('activeTab', 'users')"
                class="pb-3 text-sm font-bold transition-colors {{ $activeTab === 'users' ? 'border-b-2 border-acc text-acc-tx' : 'text-mut hover:text-ink' }}">
                Користувачі
            </button>
        @endif
        @if($auditAccess['systems'] ?? false)
            <button wire:click="$set('activeTab', 'systems')"
                class="pb-3 text-sm font-bold transition-colors {{ $activeTab === 'systems' ? 'border-b-2 border-acc text-acc-tx' : 'text-mut hover:text-ink' }}">
                Системні логи
            </button>
        @endif
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
                                <th class="p-3.5 w-36">Користувач</th>
                                <th class="p-3.5 w-28">Операція</th>
                                <th class="p-3.5 w-28">Тип</th>
                                <th class="p-3.5 w-48">Дія</th>
                                <th class="p-3.5">Різниця значень (Старе ➔ Нове)</th>
                                <th class="p-3.5 w-24 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#edf0ed]">
                            @forelse($logs as $log)
                                @php
                                    // --- Бейдж операції ---
                                    $opBadge = match(true) {
                                        in_array($log->action, ['value.created','messenger.added','messenger.reserve_added','number.added','phone.materialized','messenger.materialized'])
                                            => ['Додано', 'bg-ok-bg text-ok-tx border border-[#cbeed2]'],
                                        in_array($log->action, ['value.deleted','slot.removed','messenger.removed','number.removed'])
                                            => ['Видалено', 'bg-bad-bg text-bad-tx border border-[#f3e5e2]'],
                                        $log->action === 'audit.restored'
                                            => ['Відновлено', 'bg-acc-bg text-acc-tx border border-acc-bd'],
                                        in_array($log->action, ['slot.suppressed','slot.hidden','messenger.slot_hidden','value.frozen','phone.override_collapsed'])
                                            => ['Приховано', 'bg-[#f4f5f3] text-mut border border-[#e3e5e1]'],
                                        in_array($log->action, ['slot.renamed','messenger.slot_renamed'])
                                            => ['Перейменовано', 'bg-warn-bg text-warn-tx border border-[#eed8a0]'],
                                        default
                                            => ['Оновлено', 'bg-warn-bg text-warn-tx border border-[#eed8a0]'],
                                    };

                                    // --- Тип даних ---
                                    $dataType = match(true) {
                                        str_starts_with($log->action, 'number.') || str_starts_with($log->action, 'phone.') || str_starts_with($log->action, 'slot.')
                                            => ['phone', 'Телефон', 'text-[#3a7c4f]'],
                                        str_starts_with($log->action, 'messenger.')
                                            => ['msg', 'Месенджер', 'text-[#6b52c8]'],
                                        $log->action === 'audit.restored'
                                            => ['refresh', 'Аудит', 'text-acc-tx'],
                                        str_starts_with($log->action, 'value.') => (function() use ($log) {
                                            $payload = $log->old ?? $log->new ?? [];
                                            if (!empty($payload['phone_slot'])) return ['phone', 'Телефон', 'text-[#3a7c4f]'];
                                            $dv = \App\Models\DataValue::with('type')->find($log->subject_id);
                                            if ($dv?->type?->code === 'phone')     return ['phone',   'Телефон',   'text-[#3a7c4f]'];
                                            if ($dv?->type?->code === 'messenger') return ['msg',     'Месенджер', 'text-[#6b52c8]'];
                                            if ($dv?->type?->code === 'price')     return ['dollar',  'Ціна',      'text-[#b45309]'];
                                            return ['text', 'Текст', 'text-[#555]'];
                                        })(),
                                        default => [null, '—', 'text-mut'],
                                    };
                                @endphp
                                <tr class="hover:bg-[#fafbfa] transition-colors">
                                    <td class="p-3.5 text-mut whitespace-nowrap text-xs border-b border-[#edf0ed]">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                                    <td class="p-3.5 text-ink truncate max-w-[140px] border-b border-[#edf0ed]" title="{{ $log->actor_id ? \App\Models\User::find($log->actor_id)?->email : 'Система' }}">
                                        {{ $log->actor_id ? \App\Models\User::find($log->actor_id)?->name : 'Система' }}
                                    </td>
                                    {{-- Операція (Додано/Оновлено/Видалено...) --}}
                                    <td class="p-3.5 border-b border-[#edf0ed] whitespace-nowrap">
                                        <span class="inline-block rounded-md px-2 py-0.5 text-[11px] font-bold {{ $opBadge[1] }}">
                                            {{ $opBadge[0] }}
                                        </span>
                                    </td>
                                    {{-- Тип даних --}}
                                    <td class="p-3.5 border-b border-[#edf0ed] whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $dataType[2] }}">
                                            @if($dataType[0])
                                                @svg($dataType[0], '14')
                                            @endif
                                            {{ $dataType[1] }}
                                        </span>
                                    </td>
                                    {{-- Назва дії --}}
                                    <td class="p-3.5 border-b border-[#edf0ed]">
                                        <span class="text-xs text-mut">
                                            {{ $actionLabels[$log->action] ?? $log->action }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 text-sm text-ink border-b border-[#edf0ed]">{!! $renderDiff($log) !!}</td>
                                    <td class="p-3.5 text-right whitespace-nowrap border-b border-[#edf0ed]">
                                        @if(in_array($log->action, ['value.updated', 'value.geo_changed', 'slot.renamed', 'slot.hidden', 'slot.shown', 'messenger.slot_renamed', 'value.deleted', 'slot.removed', 'messenger.removed', 'value.created', 'messenger.added', 'messenger.reserve_added', 'messenger.exhaustion_policy_changed', 'messenger.return_mode_changed', 'messenger.emergency_changed', 'messenger.slot_hidden', 'messenger.slot_shown', 'number.added', 'number.removed', 'number.status_changed', 'number.edited', 'bulk.replace_text', 'bulk.set_value', 'bulk.set_status', 'bulk.set_geo', 'bulk.replace_phone', 'bulk.set_phone_status', 'bulk.set_phone_format']))
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
                                    <td colspan="7" class="p-8 text-center text-mut text-sm">Записи змін відсутні</td>
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
    @elseif($activeTab === 'failover')
        {{-- FAILOVER VIEW --}}
        <div class="bg-white border border-[#dfe3e0] rounded-lg p-5 shadow-sm">
            <div class="border-b border-[#edf0ed] pb-4 mb-4">
                <h2 class="text-base font-bold text-ink">Failover аудит</h2>
                <span class="text-xs text-mut mt-1 block">Окрема стрічка перемикань телефонних ліній, падінь і відновлень номерів.</span>
            </div>

            <div class="flex flex-wrap gap-3 items-center mb-5 text-sm text-mut">
                <input wire:model.live.debounce.250ms="search" type="text" placeholder="Пошук номера або сайта..."
                    class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 w-64 text-sm text-ink focus:outline-none focus:border-acc bg-white">

                <select wire:model.live="actionFilter" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                    <option value="">Усі failover-події</option>
                    @foreach(self::FAILOVER_ACTIONS as $act)
                        <option value="{{ $act }}">{{ $actionLabels[$act] ?? $act }}</option>
                    @endforeach
                </select>

                <select wire:model.live="actorFilter" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                    <option value="">Усі джерела</option>
                    <option value="system">Система</option>
                    <option value="webhook">Вебхук</option>
                    @foreach(\App\Models\User::all() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>

                <input wire:model.live="dateFrom" type="date" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                <span>до</span>
                <input wire:model.live="dateTo" type="date" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">

                <button wire:click="resetFilters" class="ml-auto text-sm text-mut hover:text-ink font-semibold">Очистити фільтри ✕</button>
            </div>

            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-sm border-collapse border border-[#dfe3e0] rounded-lg overflow-hidden">
                    <thead>
                        <tr class="bg-[#f6f8f6] border-b border-[#dfe3e0] text-xs uppercase tracking-wider text-mut font-bold">
                            <th class="p-3.5 w-36">Коли</th>
                            <th class="p-3.5 w-56">Сайт</th>
                            <th class="p-3.5 w-40">Слот</th>
                            <th class="p-3.5 w-44">Подія</th>
                            <th class="p-3.5">Деталі</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#edf0ed]">
                        @forelse($logs as $log)
                            @php
                                $old = is_array($log->old) ? $log->old : [];
                                $new = is_array($log->new) ? $log->new : [];
                                $sites = collect($new['sites'] ?? $old['sites'] ?? []);
                                if (! app(\App\Admin\AccessControl::class)->canManageAccess(auth()->user())) {
                                    $sites = $sites->filter(fn($s) => app(\App\Admin\AccessControl::class)->canViewFailover(auth()->user(), $s['id'] ?? null));
                                }
                                $siteDomains = $sites->pluck('domain')->filter()->values();
                                $siteText = $siteDomains->isNotEmpty() ? $siteDomains->implode(', ') : '—';
                                $siteCount = $siteDomains->count();
                                $triggerStatus = $new['trigger_status'] ?? $old['trigger_status'] ?? null;
                                $rowClass = match (true) {
                                    $log->action === 'failover.switch' && $triggerStatus === 'down' => 'bg-[#fff8f6] hover:bg-[#fff3ef]',
                                    $log->action === 'failover.switch' && $triggerStatus === 'active' => 'bg-[#f5fbf5] hover:bg-[#eef8ef]',
                                    $log->action === 'number.down' => 'bg-[#fff8f6] hover:bg-[#fff3ef]',
                                    $log->action === 'number.recovered' => 'bg-[#f5fbf5] hover:bg-[#eef8ef]',
                                    default => 'hover:bg-[#fafbfa]',
                                };
                                $badgeClass = match (true) {
                                    $log->action === 'failover.switch' && $triggerStatus === 'down' => 'bg-bad-bg text-bad-tx border border-[#f3e5e2]',
                                    $log->action === 'failover.switch' && $triggerStatus === 'active' => 'bg-ok-bg text-ok-tx border border-[#cbeed2]',
                                    $log->action === 'number.down' => 'bg-bad-bg text-bad-tx border border-[#f3e5e2]',
                                    $log->action === 'number.recovered' => 'bg-ok-bg text-ok-tx border border-[#cbeed2]',
                                    default => 'bg-warn-bg text-warn-tx border border-[#eed8a0]',
                                };
                            @endphp
                            <tr class="{{ $rowClass }} transition-colors">
                                <td class="p-3.5 text-mut whitespace-nowrap text-xs border-b border-[#edf0ed]">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                                <td class="p-3.5 border-b border-[#edf0ed]">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-semibold text-ink text-xs">{{ $siteText }}</span>
                                        @if($siteCount > 1)
                                            <span class="rounded bg-acc-bg px-1.5 py-0.5 text-[10px] font-bold text-acc-tx">{{ $siteCount }} сайтів</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="p-3.5 border-b border-[#edf0ed]">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-mono text-xs font-bold text-ink">{{ $new['slot_key'] ?? $old['slot_key'] ?? ('#'.($log->subject_id ?? '—')) }}</span>
                                        <span class="text-[10px] text-mut">slot #{{ $new['slot_id'] ?? $old['slot_id'] ?? ($log->subject_id ?? '—') }}</span>
                                    </div>
                                </td>
                                <td class="p-3.5 border-b border-[#edf0ed]">
                                    <span class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold {{ $badgeClass }}">
                                        {{ $actionLabels[$log->action] ?? $log->action }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-sm text-ink border-b border-[#edf0ed]">{!! $renderFailoverDetails($log) !!}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-mut text-sm">Failover-подій поки немає</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $logs->links() }}
            </div>
        </div>
    @elseif($activeTab === 'users')
        {{-- USER LOGS VIEW --}}
        <div class="bg-white border border-[#dfe3e0] rounded-lg p-5 shadow-sm">
            <div class="border-b border-[#edf0ed] pb-4 mb-4">
                <h2 class="text-base font-bold text-ink">Користувачі і доступи</h2>
                <span class="text-xs text-mut mt-1 block">Входи, виходи, невдалі спроби входу, зміни користувачів, паролі та відкликання сесій.</span>
            </div>

            <div class="flex flex-wrap gap-3 items-center mb-5 text-sm text-mut">
                <input wire:model.live.debounce.250ms="search" type="text" placeholder="Пошук користувача або IP..."
                    class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 w-64 text-sm text-ink focus:outline-none focus:border-acc bg-white">

                <select wire:model.live="actionFilter" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                    <option value="">Усі події користувачів</option>
                    @foreach(self::USER_ACTIONS as $act)
                        <option value="{{ $act }}">{{ $actionLabels[$act] ?? $act }}</option>
                    @endforeach
                </select>

                <select wire:model.live="actorFilter" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                    <option value="">Усі актори</option>
                    <option value="system">Система</option>
                    @foreach(\App\Models\User::all() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>

                <input wire:model.live="dateFrom" type="date" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">
                <span>до</span>
                <input wire:model.live="dateTo" type="date" class="border border-[#dfe3e0] rounded-lg px-3.5 py-2 text-sm text-ink focus:outline-none focus:border-acc bg-white">

                <button wire:click="resetFilters" class="ml-auto text-sm text-mut hover:text-ink font-semibold">Очистити фільтри ✕</button>
            </div>

            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-sm border-collapse border border-[#dfe3e0] rounded-lg overflow-hidden">
                    <thead>
                        <tr class="bg-[#f6f8f6] border-b border-[#dfe3e0] text-xs uppercase tracking-wider text-mut font-bold">
                            <th class="p-3.5 w-36">Час</th>
                            <th class="p-3.5 w-44">Подія</th>
                            <th class="p-3.5 w-44">Актор</th>
                            <th class="p-3.5">Подробиці</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#edf0ed]">
                        @forelse($logs as $log)
                            <tr class="hover:bg-[#fafbfa] transition-colors">
                                <td class="p-3.5 text-mut whitespace-nowrap text-xs border-b border-[#edf0ed]">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                                <td class="p-3.5 border-b border-[#edf0ed]">
                                    <span class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold
                                        {{ str_contains($log->action, 'login_failed') || str_contains($log->action, 'deleted') || str_contains($log->action, 'deactivated') ? 'bg-bad-bg text-bad-tx' : '' }}
                                        {{ (str_contains($log->action, 'login') && !str_contains($log->action, 'failed')) || str_contains($log->action, 'created') || str_contains($log->action, 'activated') ? 'bg-ok-bg text-ok-tx' : '' }}
                                        {{ str_contains($log->action, 'password') || str_contains($log->action, 'sessions') || str_contains($log->action, 'updated') ? 'bg-warn-bg text-warn-tx' : '' }}
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
                                <td colspan="4" class="p-8 text-center text-mut text-sm">Подій користувачів немає</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $logs->links() }}
            </div>
        </div>
    @else
        {{-- SYSTEM LOGS VIEW --}}
        <div class="bg-white border border-[#dfe3e0] rounded-lg p-5 shadow-sm">
            <div class="border-b border-[#edf0ed] pb-4 mb-4">
                <h2 class="text-base font-bold text-ink">Системні логи</h2>
                <span class="text-xs text-mut mt-1 block">Сайти, групи, токени, вебхуки та інші службові події без failover і користувацької стрічки.</span>
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
