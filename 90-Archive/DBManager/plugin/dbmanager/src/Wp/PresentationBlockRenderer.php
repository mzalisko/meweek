<?php

namespace DBM\Wp;

class PresentationBlockRenderer
{
    private const TYPE_ORDER = [
        'phone' => 0,
        'messenger' => 1,
        'price' => 2,
    ];

    private const TYPE_LABELS = [
        'phone' => 'Номера',
        'messenger' => 'Мессенджеры',
        'price' => 'Цены',
    ];

    private static int $instance = 0;

    public function render(array $cache, array $options = []): string
    {
        $country = $options['country'] ?? 'WORLD';
        $items = $this->items($cache, $country);
        $id = 'dbm-present-' . (++self::$instance);

        if ($items === []) {
            return $this->styles() . '<div id="' . $this->e($id) . '" class="dbm-present dbm-present--empty">'
                . '<div class="dbm-present__empty">Данные еще не доставлены.</div>'
                . '</div>';
        }

        $siteId = (int) ($cache['site_id'] ?? 0);
        $version = (int) ($cache['version'] ?? 0);
        $generatedAt = trim((string) ($cache['generated_at'] ?? ''));
        $title = trim((string) ($options['title'] ?? ''));
        if ($title === '') {
            $title = $siteId > 0 ? 'ID: ' . $siteId : 'DBManager';
        }

        $counts = $this->counts($items);
        $html = $this->styles();
        $html .= '<div id="' . $this->e($id) . '" class="dbm-present">';
        $html .= '<div class="dbm-present__head">';
        $html .= '<div class="dbm-present__title">';
        $html .= '<span class="dbm-present__eyebrow">Текущие данные</span>';
        $html .= '<h2>' . $this->e($title) . '</h2>';
        $html .= '<span class="dbm-present__meta">';
        $html .= $siteId > 0 ? 'ID: ' . $siteId : 'локальный кэш';
        if ($version > 0) {
            $html .= ' · v' . $version;
        }
        if ($generatedAt !== '') {
            $html .= ' · ' . $this->e($generatedAt);
        }
        $html .= '</span>';
        $html .= '</div>';
        $html .= '<div class="dbm-present__stats">';
        foreach (self::TYPE_LABELS as $type => $label) {
            $html .= '<span><strong>' . (int) ($counts[$type] ?? 0) . '</strong>' . $this->e($label) . '</span>';
        }
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="dbm-present__controls" role="group" aria-label="Фильтр данных">';
        $html .= '<button type="button" class="dbm-present__tab is-active" data-dbm-filter="all" aria-pressed="true">Все</button>';
        foreach (self::TYPE_LABELS as $type => $label) {
            $html .= '<button type="button" class="dbm-present__tab" data-dbm-filter="' . $this->e($type) . '" aria-pressed="false">' . $this->e($label) . '</button>';
        }
        $html .= '<label class="dbm-present__search"><span class="dbm-present__sr">Поиск</span><input type="search" placeholder="Поиск" data-dbm-search></label>';
        $html .= '</div>';

        foreach (self::TYPE_LABELS as $type => $label) {
            $typeItems = array_values(array_filter($items, fn (array $item): bool => $item['type'] === $type));
            if ($typeItems === []) {
                continue;
            }

            $html .= '<section class="dbm-present__section" data-dbm-section data-type="' . $this->e($type) . '">';
            $html .= '<button type="button" class="dbm-present__section-head" data-dbm-section-toggle aria-expanded="true">';
            $html .= '<span>' . $this->e($label) . '</span><span>' . count($typeItems) . '</span>';
            $html .= '</button>';
            $html .= '<div class="dbm-present__grid">';
            foreach ($typeItems as $item) {
                $html .= $this->item($item);
            }
            $html .= '</div></section>';
        }

        $html .= '<div class="dbm-present__empty dbm-present__empty--filtered" hidden>Ничего не найдено.</div>';
        $html .= '</div>';
        $html .= $this->script($id);

        return $html;
    }

    /** @return array<int,array<string,mixed>> */
    private function items(array $cache, string $country = 'WORLD'): array
    {
        $values = $cache['values'] ?? [];
        if (! is_array($values)) {
            return [];
        }

        $grouped = [];
        foreach ($values as $index => $value) {
            if (! is_array($value)) {
                continue;
            }
            $key = (string) ($value['key'] ?? '');
            if ($key !== '') {
                $value['_index'] = $index;
                $grouped[$key][] = $value;
            }
        }

        $resolvedValues = [];
        $country = strtoupper($country);

        foreach ($grouped as $key => $candidates) {
            $chosen = null;
            $fallback = null;

            foreach ($candidates as $candidate) {
                $geo = array_map('strtoupper', $candidate['geo'] ?? []);
                if (in_array('!' . $country, $geo, true)) {
                    continue;
                }
                if (in_array($country, $geo, true)) {
                    $chosen = $candidate;
                    break;
                }
                if (empty($geo) || in_array('WORLD', $geo, true)) {
                    $fallback = $candidate;
                }
            }

            $active = $chosen ?? $fallback;
            if ($active !== null) {
                $resolvedValues[] = $active;
            }
        }

        $items = [];
        foreach ($resolvedValues as $value) {
            $type = (string) ($value['type'] ?? '');
            if (! array_key_exists($type, self::TYPE_ORDER)) {
                continue;
            }

            $state = (string) ($value['state'] ?? 'ok');
            if ($state === 'hidden') {
                continue;
            }

            $display = $this->displayValue($value);
            $raw = trim((string) ($value['value'] ?? $display));
            if ($display === '') {
                continue;
            }

            $networks = [];
            if ($type === 'phone') {
                $phoneKey = (string) ($value['key'] ?? '');
                foreach ($resolvedValues as $rv) {
                    if ((string) ($rv['type'] ?? '') === 'messenger') {
                        $slots = $rv['linked_slot'] ?? null;
                        $match = false;
                        if (is_array($slots)) {
                            $match = in_array($phoneKey, $slots, true);
                        } elseif (is_string($slots) && $slots === $phoneKey) {
                            $match = true;
                        }
                        if ($match) {
                            $networks[] = [
                                'network' => (string) ($rv['network'] ?? 'unknown'),
                                'name' => (string) ($rv['name'] ?? $rv['network'] ?? 'messenger'),
                            ];
                        }
                    }
                }
            }

            $items[] = [
                'index' => $value['_index'],
                'type' => $type,
                'state' => $state,
                'title' => $this->title($value, $type),
                'value' => $display,
                'raw_value' => $raw,
                'geo' => $this->geo($value),
                'href' => $this->href($value, $type, $raw),
                'networks' => $networks,
                'search' => strtolower(implode(' ', [
                    (string) ($value['key'] ?? ''),
                    (string) ($value['label'] ?? ''),
                    (string) ($value['name'] ?? ''),
                    (string) ($value['network'] ?? ''),
                    $display,
                    $raw,
                    implode(' ', $this->geo($value)),
                ])),
            ];
        }

        usort($items, function (array $a, array $b): int {
            return [
                self::TYPE_ORDER[(string) $a['type']] ?? 99,
                (int) $a['index'],
            ] <=> [
                self::TYPE_ORDER[(string) $b['type']] ?? 99,
                (int) $b['index'],
            ];
        });

        return $items;
    }

    /** @param array<string,mixed> $value */
    private function title(array $value, string $type): string
    {
        if ($type === 'price') {
            $label = trim((string) ($value['label'] ?? ''));
            return $label !== '' ? $label : 'Цена';
        }

        if ($type === 'messenger') {
            $network = trim((string) ($value['network'] ?? ''));
            if ($network !== '') {
                return $network;
            }
            $name = trim((string) ($value['name'] ?? ''));
            return $name !== '' ? $name : 'Мессенджер';
        }

        return 'Телефон';
    }

    /** @param array<string,mixed> $value */
    private function displayValue(array $value): string
    {
        foreach (['display_value', 'value', 'name', 'url'] as $field) {
            $candidate = trim((string) ($value[$field] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /** @param array<string,mixed> $value */
    private function href(array $value, string $type, string $display): string
    {
        if ($type === 'phone') {
            $phone = preg_replace('/[^\d+]/', '', $display) ?: '';
            return $phone !== '' ? 'tel:' . $phone : '';
        }

        if ($type === 'messenger') {
            $url = trim((string) ($value['url'] ?? $value['value'] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
                return in_array($scheme, ['http', 'https'], true) ? $url : '';
            }
        }

        return '';
    }

    /** @param array<string,mixed> $value @return array<int,string> */
    private function geo(array $value): array
    {
        $geo = $value['geo'] ?? ['WORLD'];
        if (! is_array($geo)) {
            $geo = [$geo];
        }

        $clean = [];
        foreach ($geo as $item) {
            $item = strtoupper(trim((string) $item));
            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean ?: ['WORLD']));
    }

    /** @param array<string,mixed> $item */
    private function item(array $item): string
    {
        $state = (string) $item['state'];
        $stateLabel = $this->stateLabel($state);
        $type = (string) $item['type'];
        $raw = (string) ($item['raw_value'] ?? '');
        $display = (string) $item['value'];
        $search = $this->e((string) $item['search']);
        $html = '<article class="dbm-present__item dbm-present__item--' . $this->e($type) . '" data-dbm-item data-type="' . $this->e($type) . '" data-search="' . $search . '">';
        $html .= '<div class="dbm-present__item-main">';
        $html .= '<span class="dbm-present__item-title"><span>' . $this->e((string) $item['title']) . '</span><span class="dbm-present__type">' . $this->e($this->typeLabel($type)) . '</span></span>';
        $html .= '<strong class="dbm-present__item-value">' . $this->e($display) . '</strong>';
        if ($raw !== '' && $raw !== $display) {
            $html .= '<small class="dbm-present__raw">' . $this->e($raw) . '</small>';
        }
        $html .= '</div>';
        $html .= '<div class="dbm-present__item-side">';
        $html .= '<span class="dbm-present__state dbm-present__state--' . $this->e($this->stateClass($state)) . '">' . $this->e($stateLabel) . '</span>';
        $html .= '<span class="dbm-present__geo">';
        foreach ((array) $item['geo'] as $geo) {
            $html .= '<span>' . $this->e((string) $geo) . '</span>';
        }
        $html .= '</span>';
        $href = (string) $item['href'];
        if ($href !== '') {
            $target = str_starts_with($href, 'http') ? ' target="_blank" rel="noopener noreferrer"' : '';
            $label = (string) $item['type'] === 'phone' ? 'Позвонить' : 'Открыть';
            $html .= '<a class="dbm-present__action" href="' . $this->e($href) . '"' . $target . '>' . $this->e($label) . '</a>';
        }
        $html .= '</div></article>';

        return $html;
    }

    private function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? $type;
    }

    private function stateLabel(string $state): string
    {
        return [
            'ok' => '● активно',
            'pinned' => '● закреплено',
            'on_reserve' => '● резерв',
            'exhausted' => '● исчерпано',
            'hidden' => '● скрыто',
        ][$state] ?? ('● ' . $state);
    }

    private function stateClass(string $state): string
    {
        return preg_replace('/[^a-z0-9_-]/i', '', strtolower($state)) ?: 'state';
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,int> */
    private function counts(array $items): array
    {
        $counts = ['phone' => 0, 'messenger' => 0, 'price' => 0];
        foreach ($items as $item) {
            $type = (string) $item['type'];
            if (array_key_exists($type, $counts)) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    private function styles(): string
    {
        return <<<'HTML'
<style>
.dbm-present{--dbm-ink:#0f172a;--dbm-muted:#475569;--dbm-line:#e2e8f0;--dbm-soft:#f8fafc;--dbm-panel:#fff;--dbm-accent:#2563eb;--dbm-accent-soft:#eff6ff;--dbm-good:#10b981;--dbm-warn:#f59e0b;--dbm-bad:#ef4444;box-sizing:border-box;width:100%;max-width:1120px;margin:24px auto;padding:32px;border:1px solid var(--dbm-line);border-radius:4px;background:#fff;color:var(--dbm-ink);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03)}
.dbm-present *{box-sizing:border-box}
.dbm-present__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:24px}
.dbm-present__eyebrow{display:block;margin-bottom:4px;color:var(--dbm-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.dbm-present h2{margin:0;color:var(--dbm-ink);font-size:28px;font-weight:700;line-height:1.2;letter-spacing:0}
.dbm-present__meta{display:block;margin-top:6px;color:var(--dbm-muted);font-size:13px;line-height:1.4}
.dbm-present__stats{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px}
.dbm-present__stats span{display:flex;align-items:center;gap:8px;min-height:36px;padding:8px 14px;border:1px solid var(--dbm-line);border-radius:4px;background:var(--dbm-soft);color:var(--dbm-muted);font-size:12px;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,0.02)}
.dbm-present__stats strong{color:var(--dbm-ink);font-size:16px;font-weight:700}
.dbm-present__controls{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:24px}
.dbm-present__tab,.dbm-present__section-head{appearance:none;border:1px solid var(--dbm-line);background:#fff;color:var(--dbm-muted);font:inherit;cursor:pointer}
.dbm-present__tab{min-height:34px;padding:8px 16px;border-radius:4px;font-size:13px;font-weight:600;transition:all .15s ease}
.dbm-present__tab:hover{border-color:#cbd5e1;background:var(--dbm-soft);color:var(--dbm-ink)}
.dbm-present__tab.is-active{border-color:var(--dbm-accent);background:var(--dbm-accent-soft);color:var(--dbm-accent)}
.dbm-present__search{margin-left:auto;min-width:220px;max-width:340px;flex:1 1 220px}
.dbm-present__search input{width:100%;min-height:36px;padding:8px 12px;border:1px solid var(--dbm-line);border-radius:4px;color:var(--dbm-ink);font:inherit;font-size:13px;transition:border-color 0.15s ease,box-shadow 0.15s ease;background:#fff}
.dbm-present__search input:focus{border-color:var(--dbm-accent);box-shadow:0 0 0 3px rgba(37,99,254,0.12);outline:none}
.dbm-present__section{margin-top:24px;border:none;background:transparent}
.dbm-present__section[hidden],.dbm-present__item[hidden]{display:none}
.dbm-present__section-head{display:flex;width:100%;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;background:transparent;color:var(--dbm-ink);font-size:16px;font-weight:700;text-transform:none;letter-spacing:normal;border:none;border-bottom:2px solid var(--dbm-line);margin-bottom:16px}
.dbm-present__section-head span:last-child{min-width:22px;padding:2px 8px;border-radius:3px;background:var(--dbm-soft);border:1px solid var(--dbm-line);color:var(--dbm-muted);font-size:12px;font-weight:600;text-align:center}
.dbm-present__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;background:transparent}
.dbm-present__section.is-collapsed .dbm-present__grid{display:none}
.dbm-present__item{display:flex;align-items:center;justify-content:space-between;gap:16px;min-width:0;padding:20px;background:#fff;border:1px solid var(--dbm-line);border-left:4px solid var(--dbm-line);border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.02);transition:all .2s ease}
.dbm-present__item:hover{border-color:#cbd5e1;box-shadow:0 10px 15px -3px rgba(0,0,0,0.05);transform:translateY(-2px)}
.dbm-present__item--phone{border-left-color:#3b82f6}
.dbm-present__item--messenger{border-left-color:#10b981}
.dbm-present__item--price{border-left-color:#f59e0b}
.dbm-present__item-main{min-width:0}
.dbm-present__item-title{display:flex;align-items:center;gap:8px;margin-bottom:8px;color:var(--dbm-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.dbm-present__type{display:inline-flex;min-height:18px;align-items:center;border-radius:3px;background:var(--dbm-soft);border:1px solid var(--dbm-line);color:var(--dbm-muted);padding:1px 6px;font-size:9px;font-weight:700;line-height:1;text-transform:uppercase;letter-spacing:.02em}
.dbm-net-badge{display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;text-transform:uppercase;padding:2px 5px;border-radius:2px;margin-left:6px;cursor:help;line-height:1;border:1px solid var(--dbm-line);background:var(--dbm-soft);color:var(--dbm-muted);vertical-align:middle}
.dbm-net-badge--telegram{background:#eff6ff;color:#2563eb;border-color:#bfdbfe}
.dbm-net-badge--whatsapp{background:#ecfdf5;color:#10b981;border-color:#a7f3d0}
.dbm-net-badge--viber{background:#fbf7ff;color:#7c3aed;border-color:#ddd6fe}
.dbm-present__item-value{display:block;color:var(--dbm-ink);font-size:20px;font-weight:700;line-height:1.2;letter-spacing:0;overflow-wrap:anywhere;background:none;padding:0;border-radius:0}
.dbm-present__raw{display:block;margin-top:6px;color:var(--dbm-muted);font-size:11px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.dbm-present__item-side{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:8px;min-width:160px}
.dbm-present__state,.dbm-present__geo span,.dbm-present__action{display:inline-flex;align-items:center;min-height:26px;padding:4px 10px;border-radius:3px;font-size:12px;font-weight:600;line-height:1;text-decoration:none}
.dbm-present__state{background:#ecfdf5;color:var(--dbm-good);border:1px solid #a7f3d0}
.dbm-present__state--pinned,.dbm-present__state--on_reserve{background:#fffbeb;color:var(--dbm-warn);border:1px solid #fde68a}
.dbm-present__state--exhausted,.dbm-present__state--hidden{background:#fef2f2;color:var(--dbm-bad);border:1px solid #fca5a5}
.dbm-present__geo{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:4px}
.dbm-present__geo span{background:var(--dbm-soft);color:var(--dbm-muted);border:1px solid var(--dbm-line)}
.dbm-present__action{border:1px solid var(--dbm-line);background:#fff;color:var(--dbm-accent);transition:all .15s ease;font-weight:600;cursor:pointer}
.dbm-present__action:hover{border-color:var(--dbm-accent);background:var(--dbm-accent);color:#fff;box-shadow:0 2px 4px rgba(37,99,254,0.1)}
.dbm-present__empty{padding:24px;border:1px dashed var(--dbm-line);border-radius:4px;background:var(--dbm-soft);color:var(--dbm-muted);text-align:center}
.dbm-present__empty--filtered{margin-top:16px}
.dbm-present__sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media (max-width:760px){.dbm-present{padding:16px;margin:16px auto}.dbm-present__head{display:block}.dbm-present__stats{justify-content:flex-start;margin-top:12px}.dbm-present h2{font-size:24px}.dbm-present__grid{grid-template-columns:1fr;gap:12px}.dbm-present__item{padding:16px}.dbm-present__item-side{justify-content:flex-start;min-width:0;margin-top:12px;width:100%}.dbm-present__search{min-width:100%;margin-left:0}}
</style>
HTML;
    }

    private function script(string $id): string
    {
        $id = json_encode($id, JSON_UNESCAPED_SLASHES);

        return <<<HTML
<script>
(function(){
    var root = document.getElementById($id);
    if (!root) return;
    var active = 'all';
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-dbm-filter]'));
    var sections = Array.prototype.slice.call(root.querySelectorAll('[data-dbm-section]'));
    var items = Array.prototype.slice.call(root.querySelectorAll('[data-dbm-item]'));
    var search = root.querySelector('[data-dbm-search]');
    var empty = root.querySelector('.dbm-present__empty--filtered');

    function apply() {
        var term = search ? search.value.trim().toLowerCase() : '';
        var visibleCount = 0;
        items.forEach(function(item) {
            var typeMatch = active === 'all' || item.getAttribute('data-type') === active;
            var searchMatch = term === '' || (item.getAttribute('data-search') || '').indexOf(term) !== -1;
            var visible = typeMatch && searchMatch;
            item.hidden = !visible;
            if (visible) visibleCount++;
        });
        sections.forEach(function(section) {
            var typeMatch = active === 'all' || section.getAttribute('data-type') === active;
            var hasVisible = !!section.querySelector('[data-dbm-item]:not([hidden])');
            section.hidden = !(typeMatch && hasVisible);
        });
        if (empty) empty.hidden = visibleCount > 0;
    }

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            active = tab.getAttribute('data-dbm-filter') || 'all';
            tabs.forEach(function(other) {
                var selected = other === tab;
                other.classList.toggle('is-active', selected);
                other.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            apply();
        });
    });
    if (search) search.addEventListener('input', apply);
    root.addEventListener('click', function(event) {
        var toggle = event.target.closest('[data-dbm-section-toggle]');
        if (!toggle || !root.contains(toggle)) return;
        var section = toggle.closest('[data-dbm-section]');
        if (!section) return;
        var collapsed = section.classList.toggle('is-collapsed');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
    apply();
})();
</script>
HTML;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
