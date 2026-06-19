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
            add_submenu_page('dbm-data', 'Данные', 'Данные', 'edit_posts', 'dbm-data', [$this, 'renderData']);
            add_submenu_page('dbm-data', 'Вставка', 'Вставка', 'edit_posts', 'dbm-insert', [$this, 'renderInsert']);
            add_submenu_page('dbm-data', 'Геосимуляция', 'Геосимуляция', 'edit_posts', 'dbm-geosim', [$this, 'renderGeoSim']);
            add_submenu_page('dbm-data', 'Настройки', 'Настройки', 'manage_options', 'dbm-settings', [$this, 'renderSettings']);
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
                    'Неверный ключ подключения. Создайте новый ключ в DBManager Core и вставьте его полностью.',
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
                'Подключение сохранено. Плагин слушает подписанные обновления от DataBridge.',
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
        echo '<div class="dbm-hero"><div><span class="dbm-eyebrow">DBManager</span><h1>Данные сайта</h1><p>Плагин показывает кеш, доставленный из центральной CRM. Редактирование происходит только в Core.</p></div>';
        echo '<div class="dbm-version"><span>Версия</span><strong>' . (int) ($cache['version'] ?? 0) . '</strong></div></div>';
        echo '<div class="dbm-stats">';
        echo '<div class="dbm-stat"><span>Сайт</span><strong>' . esc_html((string) ($cache['site'] ?? 'локальный кеш')) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Номера</span><strong>' . (int) ($counts['phone'] ?? 0) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Мессенджеры</span><strong>' . (int) ($counts['messenger'] ?? 0) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Цены</span><strong>' . (int) ($counts['price'] ?? 0) . '</strong></div>';
        echo '</div>';
        echo '<div class="dbm-card">';
        echo '<div class="dbm-card-head"><div><h2>Текущие значения</h2><p>Порядок соответствует CRM: телефоны, мессенджеры, цены.</p></div>';
        echo '<div class="dbm-tabs" style="display: flex; gap: 6px; align-self: center;">';
        echo '<button type="button" class="button button-primary dbm-tab-btn" data-filter="all">Все</button>';
        echo '<button type="button" class="button button-secondary dbm-tab-btn" data-filter="phone">Номера</button>';
        echo '<button type="button" class="button button-secondary dbm-tab-btn" data-filter="messenger">Мессенджеры</button>';
        echo '<button type="button" class="button button-secondary dbm-tab-btn" data-filter="price">Цены</button>';
        echo '</div>';
        echo '</div>';
        echo '<table class="dbm-table"><thead><tr><th>Данные</th><th>Гео</th><th>Статус</th><th>Значение</th></tr></thead><tbody>';
        foreach ($values as $value) {
            $type = (string) ($value['type'] ?? '');
            echo '<tr class="dbm-row" data-type="' . esc_attr($type) . '">';
            echo '<td><div class="dbm-key">' . esc_html((string) ($value['key'] ?? '')) . '</div>' . $this->typeBadge($type) . '</td>';
            echo '<td>' . $this->geoChips($value['geo'] ?? []) . '</td>';
            echo '<td>' . $this->stateBadge((string) ($value['state'] ?? 'ok')) . '</td>';
            echo '<td>' . $this->valuePreview($value, $values) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '<script>
        (function() {
            var tabs = document.querySelectorAll(".dbm-tab-btn");
            var rows = document.querySelectorAll(".dbm-row");
            tabs.forEach(function(tab) {
                tab.addEventListener("click", function() {
                    var filter = tab.getAttribute("data-filter");
                    tabs.forEach(function(t) {
                        t.classList.remove("button-primary");
                        t.classList.add("button-secondary");
                    });
                    tab.classList.remove("button-secondary");
                    tab.classList.add("button-primary");
                    rows.forEach(function(row) {
                        if (filter === "all" || row.getAttribute("data-type") === filter) {
                            row.style.display = "";
                        } else {
                            row.style.display = "none";
                        }
                    });
                });
            });
        })();
        </script>';
        echo '</div>';
    }

    public function renderInsert(): void
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $builder = new SnippetBuilder($this->settings->shortcode);

        $this->adminStyles();

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Вставка</span><h1>Коды для сайта</h1><p>Готовые шорткоды для значений и презентационного блока.</p></div></div>';
        echo '<div class="dbm-card dbm-highlight"><div><h2>Презентационный блок</h2><p>Интерактивный блок с телефонами, мессенджерами и ценами из локального кеша.</p></div><code>[dbm_presentation]</code><code>[dbm_block]</code></div>';
        echo '<div class="dbm-card"><div class="dbm-card-head"><div><h2>Отдельные значения</h2><p>Для телефонов формат <code>tel</code> оставляет чистый номер в href.</p></div></div>';
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

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Настройки</span><h1>DBManager</h1><p>Подключение плагина как пассивного слушателя DataBridge.</p></div>';
        echo '<div class="dbm-status-card">' . ($hasConnection ? $this->stateBadge('ok') : $this->stateBadge('pending')) . '</div></div>';
        settings_errors('dbm_settings');

        echo '<div class="dbm-card">';
        echo '<h2>Статус подключения</h2><p class="dbm-muted">';
        echo $hasConnection ? 'Плагин подключен. Он не делает исходящих запросов к центральной CRM.' : 'Вставьте ключ подключения из DBManager Core. После этого плагин будет слушать подписанные обновления.';
        echo '</p>';

        if ($listenerUrl !== '') {
            echo '<div class="dbm-field-row"><span>Локальный endpoint</span><code>' . esc_html($listenerUrl) . '</code></div>';
        }
        if (! empty($options['connection_site'])) {
            echo '<div class="dbm-field-row"><span>Сайт</span><strong>' . esc_html((string) $options['connection_site']) . '</strong></div>';
            if (! empty($options['connection_ping_url'])) {
                echo '<div class="dbm-field-row"><span>Endpoint доставки</span><code>' . esc_html((string) $options['connection_ping_url']) . '</code></div>';
            }
            if (! empty($options['connection_saved_at'])) {
                echo '<div class="dbm-field-row"><span>Ключ сохранен</span><strong>' . esc_html((string) $options['connection_saved_at']) . '</strong></div>';
            }
        }

        $cache = get_option('dbm_cache');
        if (is_array($cache) && isset($cache['version'])) {
            echo '<div class="dbm-field-row"><span>Последняя версия данных</span><strong>' . (int) $cache['version'] . '</strong></div>';
        } elseif ($hasConnection) {
            echo '<div class="dbm-field-row"><span>Данные</span><strong>еще не получены</strong></div>';
        }
        echo '</div>';

        echo '<form method="post" action="options.php" class="dbm-card dbm-form">';
        echo '<h2>Параметры</h2>';
        settings_fields('dbm');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="dbm_connection_key">Ключ подключения</label></th><td>';
        echo '<textarea id="dbm_connection_key" name="dbm_settings[connection_key]" rows="4" class="large-text code" autocomplete="off" spellcheck="false" placeholder="DBM1..."></textarea>';
        echo '<p class="description">Ключ показывается в Core один раз. Он содержит только секрет подписи для этого сайта, без адреса CRM.</p>';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="dbm_shortcode">Название shortcode</label></th><td>';
        echo '<input id="dbm_shortcode" type="text" name="dbm_settings[shortcode]" value="' . esc_attr((string) ($options['shortcode'] ?? 'dbm')) . '" class="regular-text">';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="dbm_css_class">CSS-класс вывода</label></th><td>';
        echo '<input id="dbm_css_class" type="text" name="dbm_settings[css_class]" value="' . esc_attr((string) ($options['css_class'] ?? '')) . '" class="regular-text">';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button('Сохранить настройки');
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

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Геосимуляция</span><h1>Проверка гео</h1><p>Симуляция страны для проверки видимости данных в плагине.</p></div>';
        echo '<div class="dbm-status-card">' . ($simulated !== null ? $this->stateBadge('pinned') : $this->stateBadge('ok')) . '</div></div>';

        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="updated notice is-dismissible"><p>Настройки геосимуляции сохранены.</p></div>';
        }

        echo '<div class="dbm-stats">';
        echo '<div class="dbm-stat"><span>Режим</span><strong>' . ($simulated !== null ? 'включен' : 'выключен') . '</strong></div>';
        echo '<div class="dbm-stat"><span>IP-страна</span><strong>' . esc_html($realCountry) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Активная страна</span><strong>' . esc_html($currentEffective) . '</strong></div>';
        echo '</div>';

        echo '<form method="post" action="" class="dbm-card dbm-form">';
        echo '<h2>Страна для симуляции</h2>';
        wp_nonce_field('dbm_geosim_save', 'dbm_geosim_nonce');
        echo '<p><label for="simulated_country"><strong>Страна</strong></label><br>';
        echo '<select name="simulated_country" id="simulated_country" class="dbm-select">';
        echo '<option value="disabled" ' . selected($simulated, null, false) . '>Отключить симуляцию</option>';
        foreach ($countries as $country) {
            echo '<option value="' . esc_attr($country) . '" ' . selected($simulated, $country, false) . '>' . esc_html($country) . '</option>';
        }
        echo '</select></p>';
        echo '<p><input type="submit" class="button button-primary" value="Сохранить"></p>';
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
.dbm-net-badge{display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;text-transform:uppercase;padding:2px 5px;border-radius:4px;margin-left:6px;cursor:help;line-height:1;border:1px solid var(--dbm-line);background:#f1f4f6;color:var(--dbm-muted);vertical-align:middle}
.dbm-net-badge--telegram{background:#eaf5fc;color:#315f8a;border-color:#bce0f2}
.dbm-net-badge--whatsapp{background:#eef7f2;color:#1f7a4d;border-color:#c5f2d6}
.dbm-net-badge--viber{background:#f6f1fc;color:#7360f2;border-color:#dfcff7}
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
        $labels = ['phone' => 'Телефон', 'messenger' => 'Мессенджер', 'price' => 'Цена'];
        return '<span class="dbm-badge">' . esc_html($labels[$type] ?? ($type !== '' ? $type : 'значение')) . '</span>';
    }

    private function stateBadge(string $state): string
    {
        $labels = [
            'ok' => '● активно',
            'pinned' => '● закреплено',
            'on_reserve' => '● резерв',
            'exhausted' => '● исчерпано',
            'hidden' => '● скрыто',
            'pending' => '● ожидает',
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
    private function valuePreview(array $value, array $allValues = []): string
    {
        $display = trim((string) ($value['display_value'] ?? $value['value'] ?? $value['name'] ?? $value['url'] ?? ''));
        $raw = trim((string) ($value['value'] ?? ''));
        $label = trim((string) ($value['label'] ?? $value['network'] ?? ''));
        $type = (string) ($value['type'] ?? '');

        if ($display === '') {
            $display = '—';
        }

        $html = '<span class="dbm-value"><strong>' . esc_html($display);

        if ($type === 'phone') {
            $phoneKey = (string) ($value['key'] ?? '');
            foreach ($allValues as $val) {
                if (($val['type'] ?? '') === 'messenger') {
                    $slots = $val['linked_slot'] ?? null;
                    $match = false;
                    if (is_array($slots)) {
                        $match = in_array($phoneKey, $slots, true);
                    } elseif (is_string($slots) && $slots === $phoneKey) {
                        $match = true;
                    }
                    if ($match) {
                        $netCode = strtolower((string) ($val['network'] ?? 'unknown'));
                        $netName = esc_attr((string) ($val['name'] ?? $val['network'] ?? 'messenger'));
                        $html .= '<span class="dbm-net-badge dbm-net-badge--' . esc_attr($netCode) . '" title="Прикреплен мессенджер: ' . $netName . '">' . esc_html(substr($netCode, 0, 2)) . '</span>';
                    }
                }
            }
        }

        $html .= '</strong>';
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
