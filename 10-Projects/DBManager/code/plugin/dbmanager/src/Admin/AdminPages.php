<?php

namespace DBM\Admin;

use DBM\Config\Settings;

class AdminPages
{
    public function __construct(
        private Settings $settings,
        private \DBM\Geo\GeoSimulation $simulation,
    ) {}

    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_menu_page('DBManager', 'DBManager', 'edit_posts', 'dbm-data', [$this, 'renderData']);
            add_submenu_page('dbm-data', 'Дані', 'Дані', 'edit_posts', 'dbm-data', [$this, 'renderData']);
            add_submenu_page('dbm-data', 'Вставка', 'Вставка', 'edit_posts', 'dbm-insert', [$this, 'renderInsert']);
            add_submenu_page('dbm-data', 'Геосимуляція', 'Геосимуляція', 'edit_posts', 'dbm-geosim', [$this, 'renderGeoSim']);
            add_submenu_page('dbm-data', 'Налаштування', 'Налаштування', 'manage_options', 'dbm-settings', [$this, 'renderSettings']);
        });

        add_action('admin_init', function (): void {
            register_setting('dbm', 'dbm_settings', ['sanitize_callback' => [$this, 'sanitizeSettings']]);

            if (isset($_POST['dbm_geosim_nonce']) && wp_verify_nonce($_POST['dbm_geosim_nonce'], 'dbm_geosim_save')) {
                if (current_user_can('edit_posts')) {
                    $country = sanitize_text_field($_POST['simulated_country'] ?? '');
                    $this->simulation->setSimulatedCountry($country);
                    wp_redirect(admin_url('admin.php?page=dbm-geosim&settings-updated=true'));
                    exit;
                }
            }
        });
    }

    public function sanitizeSettings($input): array
    {
        $input = is_array($input) ? $input : [];
        $old = get_option('dbm_settings');
        $old = is_array($old) ? $old : [];

        $settings = [
            'signing_secret' => (string) ($old['signing_secret'] ?? ''),
            'shortcode' => sanitize_key((string) ($input['shortcode'] ?? ($old['shortcode'] ?? 'dbm'))),
            'css_class' => sanitize_html_class((string) ($input['css_class'] ?? ($old['css_class'] ?? ''))),
            'connection_site' => sanitize_text_field((string) ($old['connection_site'] ?? '')),
            'connection_ping_url' => esc_url_raw((string) ($old['connection_ping_url'] ?? '')),
            'connection_saved_at' => sanitize_text_field((string) ($old['connection_saved_at'] ?? '')),
        ];

        if ($settings['shortcode'] === '') {
            $settings['shortcode'] = 'dbm';
        }

        $connectionKey = trim((string) ($input['connection_key'] ?? ''));
        if ($connectionKey !== '') {
            $decoded = Settings::decodeConnectionKey($connectionKey);

            if ($decoded === null) {
                add_settings_error(
                    'dbm_settings',
                    'dbm_connection_invalid',
                    'Невірний ключ підключення. Створіть новий ключ у DBManager Core і вставте його повністю.',
                    'error'
                );

                return $settings;
            }

            $settings['signing_secret'] = (string) $decoded['signing_secret'];
            $settings['shortcode'] = sanitize_key((string) ($decoded['shortcode'] ?: $settings['shortcode']));
            $settings['connection_site'] = sanitize_text_field((string) $decoded['site']);
            $settings['connection_ping_url'] = esc_url_raw((string) $decoded['ping_url']);
            $settings['connection_saved_at'] = function_exists('current_time')
                ? (string) current_time('mysql')
                : gmdate('Y-m-d H:i:s');

            add_settings_error(
                'dbm_settings',
                'dbm_connection_saved',
                'Підключення збережено. Плагін слухає підписані оновлення від DataBridge.',
                'updated'
            );
        }

        return $settings;
    }

    public function renderData(): void
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $values = $this->orderedValues($cache);
        $counts = $this->counts($values);

        $this->adminStyles();

        echo '<div class="wrap dbm-admin">';
        echo '<div class="dbm-hero"><div><span class="dbm-eyebrow">DBManager</span><h1>Дані сайту</h1><p>Плагін показує кеш, доставлений із центральної CRM. Редагування відбувається тільки у Core.</p></div>';
        echo '<div class="dbm-version"><span>Версія</span><strong>' . (int) ($cache['version'] ?? 0) . '</strong></div></div>';
        echo '<div class="dbm-stats">';
        echo '<div class="dbm-stat"><span>Сайт</span><strong>' . esc_html((string) ($cache['site'] ?? 'локальний кеш')) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Номери</span><strong>' . (int) ($counts['phone'] ?? 0) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Месенджери</span><strong>' . (int) ($counts['messenger'] ?? 0) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Ціни</span><strong>' . (int) ($counts['price'] ?? 0) . '</strong></div>';
        echo '</div>';
        echo '<div class="dbm-card">';
        echo '<div class="dbm-card-head"><div><h2>Поточні значення</h2><p>Порядок відповідає CRM: телефони, месенджери, ціни.</p></div></div>';
        echo '<table class="dbm-table"><thead><tr><th>Дані</th><th>Гео</th><th>Стан</th><th>Значення</th></tr></thead><tbody>';
        foreach ($values as $value) {
            echo '<tr>';
            echo '<td><div class="dbm-key">' . esc_html((string) ($value['key'] ?? '')) . '</div>' . $this->typeBadge((string) ($value['type'] ?? '')) . '</td>';
            echo '<td>' . $this->geoChips($value['geo'] ?? []) . '</td>';
            echo '<td>' . $this->stateBadge((string) ($value['state'] ?? 'ok')) . '</td>';
            echo '<td>' . $this->valuePreview($value) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div></div>';
    }

    public function renderInsert(): void
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $builder = new SnippetBuilder($this->settings->shortcode);

        $this->adminStyles();

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Вставка</span><h1>Коди для сайту</h1><p>Готові шорткоди для значень і презентаційного блоку.</p></div></div>';
        echo '<div class="dbm-card dbm-highlight"><div><h2>Презентаційний блок</h2><p>Інтерактивний блок із телефонами, месенджерами й цінами з локального кешу.</p></div><code>[dbm_presentation]</code><code>[dbm_block]</code></div>';
        echo '<div class="dbm-card"><div class="dbm-card-head"><div><h2>Окремі значення</h2><p>Для телефонів формат <code>tel</code> лишає чистий номер у href.</p></div></div>';
        echo '<table class="dbm-table"><thead><tr><th>Ключ</th><th>Шорткод</th><th>tel</th><th>PHP</th></tr></thead><tbody>';
        foreach ($this->orderedValues($cache) as $value) {
            $snippet = $builder->forKey((string) ($value['key'] ?? ''));
            echo '<tr><td><div class="dbm-key">' . esc_html($value['key'] ?? '') . '</div>' . $this->typeBadge((string) ($value['type'] ?? '')) . '</td><td><code>' . esc_html($snippet['shortcode']) . '</code></td>';
            echo '<td><code>' . esc_html($snippet['tel']) . '</code></td><td><code>' . esc_html($snippet['php']) . '</code></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    public function renderSettings(): void
    {
        $options = get_option('dbm_settings');
        $options = is_array($options) ? $options : [];
        $hasConnection = (string) ($options['signing_secret'] ?? '') !== '';
        $listenerUrl = function_exists('rest_url') ? rest_url('dbm/v1/ping') : '';

        $this->adminStyles();

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Налаштування</span><h1>DBManager</h1><p>Підключення плагіна як пасивного слухача DataBridge.</p></div>';
        echo '<div class="dbm-status-card">' . ($hasConnection ? $this->stateBadge('ok') : $this->stateBadge('pending')) . '</div></div>';
        settings_errors('dbm_settings');

        echo '<div class="dbm-card">';
        echo '<h2>Стан підключення</h2><p class="dbm-muted">';
        echo $hasConnection ? 'Плагін підключено. Він не робить вихідних запитів до центральної CRM.' : 'Вставте ключ підключення з DBManager Core. Після цього плагін буде слухати підписані оновлення.';
        echo '</p>';

        if ($listenerUrl !== '') {
            echo '<div class="dbm-field-row"><span>Локальний endpoint</span><code>' . esc_html($listenerUrl) . '</code></div>';
        }
        if (! empty($options['connection_site'])) {
            echo '<div class="dbm-field-row"><span>Сайт</span><strong>' . esc_html((string) $options['connection_site']) . '</strong></div>';
            if (! empty($options['connection_ping_url'])) {
                echo '<div class="dbm-field-row"><span>Endpoint доставки</span><code>' . esc_html((string) $options['connection_ping_url']) . '</code></div>';
            }
            if (! empty($options['connection_saved_at'])) {
                echo '<div class="dbm-field-row"><span>Ключ збережено</span><strong>' . esc_html((string) $options['connection_saved_at']) . '</strong></div>';
            }
        }

        $cache = get_option('dbm_cache');
        if (is_array($cache) && isset($cache['version'])) {
            echo '<div class="dbm-field-row"><span>Остання версія даних</span><strong>' . (int) $cache['version'] . '</strong></div>';
        } elseif ($hasConnection) {
            echo '<div class="dbm-field-row"><span>Дані</span><strong>ще не отримано</strong></div>';
        }
        echo '</div>';

        echo '<form method="post" action="options.php" class="dbm-card dbm-form">';
        echo '<h2>Параметри</h2>';
        settings_fields('dbm');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="dbm_connection_key">Ключ підключення</label></th><td>';
        echo '<textarea id="dbm_connection_key" name="dbm_settings[connection_key]" rows="4" class="large-text code" autocomplete="off" spellcheck="false" placeholder="DBM1..."></textarea>';
        echo '<p class="description">Ключ показується у Core один раз. Він містить тільки секрет підпису для цього сайту, без адреси CRM.</p>';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="dbm_shortcode">Назва шорткоду</label></th><td>';
        echo '<input id="dbm_shortcode" type="text" name="dbm_settings[shortcode]" value="' . esc_attr((string) ($options['shortcode'] ?? 'dbm')) . '" class="regular-text">';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="dbm_css_class">CSS-клас виводу</label></th><td>';
        echo '<input id="dbm_css_class" type="text" name="dbm_settings[css_class]" value="' . esc_attr((string) ($options['css_class'] ?? '')) . '" class="regular-text">';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button('Зберегти підключення');
        echo '</form></div>';
    }

    public function renderGeoSim(): void
    {
        $simulated = $this->simulation->getSimulatedCountry();
        $detector = new \DBM\Geo\GeoDetector(
            new \DBM\Geo\MaxMindCountryLookup((string) (get_option('dbm_geodb_path') ?: ''))
        );
        $realCountry = $detector->detect(
            ['CF-IPCountry' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''],
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))
        );

        $currentEffective = $simulated !== null ? $simulated : $realCountry;
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $countries = $this->simulation->getAvailableCountries($cache);

        $this->adminStyles();

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Геосимуляція</span><h1>Перевірка гео</h1><p>Симуляція країни для перевірки видимості даних у плагіні.</p></div>';
        echo '<div class="dbm-status-card">' . ($simulated !== null ? $this->stateBadge('pinned') : $this->stateBadge('ok')) . '</div></div>';

        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="updated notice is-dismissible"><p>Налаштування геосимуляції збережено.</p></div>';
        }

        echo '<div class="dbm-stats">';
        echo '<div class="dbm-stat"><span>Режим</span><strong>' . ($simulated !== null ? 'увімкнено' : 'вимкнено') . '</strong></div>';
        echo '<div class="dbm-stat"><span>IP-країна</span><strong>' . esc_html($realCountry) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Активна країна</span><strong>' . esc_html($currentEffective) . '</strong></div>';
        echo '</div>';

        echo '<form method="post" action="" class="dbm-card dbm-form">';
        echo '<h2>Країна для симуляції</h2>';
        wp_nonce_field('dbm_geosim_save', 'dbm_geosim_nonce');
        echo '<p><label for="simulated_country"><strong>Країна</strong></label><br>';
        echo '<select name="simulated_country" id="simulated_country" class="dbm-select">';
        echo '<option value="disabled" ' . selected($simulated, null, false) . '>Вимкнути симуляцію</option>';
        foreach ($countries as $country) {
            echo '<option value="' . esc_attr($country) . '" ' . selected($simulated, $country, false) . '>' . esc_html($country) . '</option>';
        }
        echo '</select></p>';
        echo '<p><input type="submit" class="button button-primary" value="Зберегти"></p>';
        echo '</form></div>';
    }

    private function adminStyles(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        echo <<<'HTML'
<style>
.dbm-admin{--dbm-ink:#243342;--dbm-muted:#6b7683;--dbm-line:#dfe5ea;--dbm-soft:#f5f7f8;--dbm-accent:#315f8a;--dbm-accent-soft:#eaf2f8;--dbm-ok:#1f7a4d;--dbm-warn:#9a6a1f;--dbm-bad:#9a3434;max-width:1180px;color:var(--dbm-ink)}
.dbm-admin *{box-sizing:border-box}
.dbm-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin:18px 0 14px;padding:18px 20px;border:1px solid var(--dbm-line);border-radius:8px;background:#fff;box-shadow:0 12px 34px rgba(31,45,56,.06)}
.dbm-hero h1{margin:2px 0 6px;color:var(--dbm-ink);font-size:24px;line-height:1.2}
.dbm-hero p,.dbm-muted{margin:0;color:var(--dbm-muted);font-size:13px;line-height:1.45}
.dbm-eyebrow{color:var(--dbm-muted);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.dbm-version,.dbm-status-card{min-width:112px;text-align:right}
.dbm-version span{display:block;color:var(--dbm-muted);font-size:11px;text-transform:uppercase;font-weight:800}
.dbm-version strong{display:block;margin-top:4px;color:var(--dbm-accent);font-size:28px;line-height:1}
.dbm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
.dbm-stat{min-width:0;padding:13px 14px;border:1px solid var(--dbm-line);border-radius:8px;background:#fff}
.dbm-stat span{display:block;color:var(--dbm-muted);font-size:11px;text-transform:uppercase;font-weight:800}
.dbm-stat strong{display:block;margin-top:5px;color:var(--dbm-ink);font-size:17px;line-height:1.25;overflow-wrap:anywhere}
.dbm-card{margin:0 0 14px;padding:16px;border:1px solid var(--dbm-line);border-radius:8px;background:#fff;box-shadow:0 10px 26px rgba(31,45,56,.05)}
.dbm-card h2{margin:0 0 5px;color:var(--dbm-ink);font-size:16px}
.dbm-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}
.dbm-highlight{display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-color:#cbdceb;background:linear-gradient(180deg,#fff,#f6fafc)}
.dbm-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid var(--dbm-line);border-radius:8px;background:#fff}
.dbm-table th{padding:10px 12px;background:#f4f7f8;color:var(--dbm-muted);font-size:11px;text-align:left;text-transform:uppercase;letter-spacing:.03em}
.dbm-table td{padding:10px 12px;border-top:1px solid #edf1f3;vertical-align:middle}
.dbm-table tr:hover td{background:#f8fbfd}
.dbm-key{margin-bottom:5px;color:var(--dbm-ink);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-weight:800}
.dbm-value{display:flex;flex-direction:column;gap:4px;min-width:0}
.dbm-value strong{font-size:15px;color:var(--dbm-ink);overflow-wrap:anywhere}
.dbm-value small{color:var(--dbm-muted)}
.dbm-badge,.dbm-chip,.dbm-state{display:inline-flex;align-items:center;gap:5px;min-height:24px;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:800;line-height:1}
.dbm-badge{background:var(--dbm-accent-soft);color:var(--dbm-accent)}
.dbm-chip{margin:2px;background:#eef2f5;color:var(--dbm-muted)}.dbm-chip--deny{background:#fdeeee;color:var(--dbm-bad)}
.dbm-state{background:#eef6f1;color:var(--dbm-ok)}
.dbm-state--warn,.dbm-state--on_reserve,.dbm-state--pinned{background:#fff6e5;color:var(--dbm-warn)}
.dbm-state--bad,.dbm-state--hidden,.dbm-state--exhausted{background:#fdeeee;color:var(--dbm-bad)}
.dbm-state--pending{background:#eef2f5;color:var(--dbm-muted)}
.dbm-field-row{display:flex;justify-content:space-between;gap:14px;padding:10px 0;border-top:1px solid #edf1f3}
.dbm-field-row span{color:var(--dbm-muted);font-weight:700}
.dbm-field-row code,.dbm-card code{display:inline-flex;max-width:100%;padding:4px 7px;border-radius:6px;background:#f1f4f6;color:#22313f;overflow-wrap:anywhere;white-space:normal}
.dbm-form textarea,.dbm-form input[type=text],.dbm-select{max-width:620px;border-color:var(--dbm-line);border-radius:8px}
@media (max-width:782px){.dbm-hero{display:block}.dbm-version,.dbm-status-card{text-align:left;margin-top:12px}.dbm-stats{grid-template-columns:1fr 1fr}.dbm-table{display:block;overflow-x:auto}.dbm-field-row{display:block}.dbm-field-row code,.dbm-field-row strong{display:inline-block;margin-top:5px}}
</style>
HTML;
    }

    /** @param array<int,array<string,mixed>> $values @return array<string,int> */
    private function counts(array $values): array
    {
        $counts = ['phone' => 0, 'messenger' => 0, 'price' => 0];
        foreach ($values as $value) {
            $type = (string) ($value['type'] ?? '');
            if (array_key_exists($type, $counts)) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    private function typeBadge(string $type): string
    {
        $labels = ['phone' => 'Телефон', 'messenger' => 'Месенджер', 'price' => 'Ціна'];
        return '<span class="dbm-badge">' . esc_html($labels[$type] ?? ($type !== '' ? $type : 'значення')) . '</span>';
    }

    private function stateBadge(string $state): string
    {
        $labels = [
            'ok' => '● активно',
            'pinned' => '● закріплено',
            'on_reserve' => '● резерв',
            'exhausted' => '● вичерпано',
            'hidden' => '● приховано',
            'pending' => '● очікує',
        ];
        $safe = preg_replace('/[^a-z0-9_-]/i', '', strtolower($state)) ?: 'pending';

        return '<span class="dbm-state dbm-state--' . esc_attr($safe) . '">' . esc_html($labels[$state] ?? ('● ' . $state)) . '</span>';
    }

    private function geoChips(mixed $geo): string
    {
        $geo = is_array($geo) ? $geo : [$geo];
        $geo = array_values(array_filter(array_map(fn ($item): string => strtoupper(trim((string) $item)), $geo)));
        if ($geo === []) {
            $geo = ['WORLD'];
        }

        return implode('', array_map(function (string $item): string {
            $class = str_starts_with($item, '!') ? 'dbm-chip dbm-chip--deny' : 'dbm-chip';
            return '<span class="' . $class . '">' . esc_html($item) . '</span>';
        }, $geo));
    }

    /** @param array<string,mixed> $value */
    private function valuePreview(array $value): string
    {
        $display = trim((string) ($value['display_value'] ?? $value['value'] ?? $value['name'] ?? $value['url'] ?? ''));
        $raw = trim((string) ($value['value'] ?? ''));
        $label = trim((string) ($value['label'] ?? $value['network'] ?? ''));

        if ($display === '') {
            $display = '—';
        }

        $html = '<span class="dbm-value"><strong>' . esc_html($display) . '</strong>';
        if ($label !== '') {
            $html .= '<small>' . esc_html($label) . '</small>';
        }
        if ($raw !== '' && $raw !== $display) {
            $html .= '<small>raw: ' . esc_html($raw) . '</small>';
        }
        $html .= '</span>';

        return $html;
    }

    private function orderedValues(array $cache): array
    {
        $values = $cache['values'] ?? [];
        if (! is_array($values)) {
            return [];
        }

        $typeOrder = [
            'phone' => 0,
            'messenger' => 1,
            'price' => 2,
        ];

        $decorated = [];
        foreach ($values as $index => $value) {
            if (! is_array($value)) {
                continue;
            }

            $decorated[] = ['index' => $index, 'value' => $value];
        }

        usort($decorated, function (array $a, array $b) use ($typeOrder): int {
            $aValue = $a['value'];
            $bValue = $b['value'];
            $aType = (string) ($aValue['type'] ?? '');
            $bType = (string) ($bValue['type'] ?? '');

            return [
                $typeOrder[$aType] ?? 99,
                strtolower((string) ($aValue['key'] ?? '')),
                $a['index'],
            ] <=> [
                $typeOrder[$bType] ?? 99,
                strtolower((string) ($bValue['key'] ?? '')),
                $b['index'],
            ];
        });

        return array_map(fn (array $item): array => $item['value'], $decorated);
    }
}
