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

        echo '<div class="wrap">';
        echo '<h1>Дані</h1>';
        echo '<p>Версія: ' . (int) ($cache['version'] ?? 0) . '. Редагування відбувається тільки у центральній CRM.</p>';
        echo '<table class="widefat"><thead><tr><th>Ключ</th><th>Тип</th><th>Стан</th><th>Значення</th></tr></thead><tbody>';
        foreach ($cache['values'] ?? [] as $value) {
            echo '<tr><td>' . esc_html($value['key'] ?? '') . '</td><td>' . esc_html($value['type'] ?? '') . '</td>';
            echo '<td>' . esc_html($value['state'] ?? '') . '</td><td>' . esc_html($value['value'] ?? '') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderInsert(): void
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $builder = new SnippetBuilder($this->settings->shortcode);

        echo '<div class="wrap"><h1>Вставка</h1><table class="widefat"><thead><tr><th>Ключ</th><th>Шорткод</th><th>tel</th><th>PHP</th></tr></thead><tbody>';
        foreach ($cache['values'] ?? [] as $value) {
            $snippet = $builder->forKey((string) ($value['key'] ?? ''));
            echo '<tr><td>' . esc_html($value['key'] ?? '') . '</td><td><code>' . esc_html($snippet['shortcode']) . '</code></td>';
            echo '<td><code>' . esc_html($snippet['tel']) . '</code></td><td><code>' . esc_html($snippet['php']) . '</code></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderSettings(): void
    {
        $options = get_option('dbm_settings');
        $options = is_array($options) ? $options : [];
        $hasConnection = (string) ($options['signing_secret'] ?? '') !== '';
        $listenerUrl = function_exists('rest_url') ? rest_url('dbm/v1/ping') : '';

        echo '<div class="wrap"><h1>Налаштування DBManager</h1>';
        settings_errors('dbm_settings');

        echo '<div class="notice notice-info"><p>';
        echo $hasConnection
            ? 'Плагін підключено як пасивний слухач. Він не робить вихідних запитів до центральної CRM.'
            : 'Вставте ключ підключення з DBManager Core. Після цього плагін буде тільки слухати підписані оновлення.';
        echo '</p></div>';

        if ($listenerUrl !== '') {
            echo '<p><strong>Локальний endpoint слухача:</strong><br><code>' . esc_html($listenerUrl) . '</code></p>';
        }
        if (! empty($options['connection_site'])) {
            echo '<div class="notice notice-success inline"><p><strong>Сайт:</strong> ' . esc_html((string) $options['connection_site']) . '</p>';
            if (! empty($options['connection_ping_url'])) {
                echo '<p><strong>Endpoint доставки:</strong><br><code>' . esc_html((string) $options['connection_ping_url']) . '</code></p>';
            }
            if (! empty($options['connection_saved_at'])) {
                echo '<p><strong>Ключ збережено:</strong> ' . esc_html((string) $options['connection_saved_at']) . '</p>';
            }
            echo '</div>';
        }

        $cache = get_option('dbm_cache');
        if (is_array($cache) && isset($cache['version'])) {
            echo '<p><strong>Остання отримана версія даних:</strong> ' . (int) $cache['version'] . '</p>';
        } elseif ($hasConnection) {
            echo '<p><strong>Дані ще не отримано.</strong> Підключення збережено, плагін очікує першу підписану доставку.</p>';
        }

        echo '<form method="post" action="options.php">';
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

        echo '<div class="wrap"><h1>Геосимуляція</h1>';

        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="updated notice is-dismissible"><p>Налаштування геосимуляції збережено.</p></div>';
        }

        echo '<p>Панель дозволяє перевірити відображення даних для вибраної країни.</p>';
        echo '<table class="widefat" style="margin-bottom:20px;max-width:600px;"><tbody>';
        echo '<tr><td><strong>Режим симуляції:</strong></td><td>' . ($simulated !== null ? 'Увімкнено: ' . esc_html($simulated) : 'Вимкнено') . '</td></tr>';
        echo '<tr><td><strong>Реальна країна за IP:</strong></td><td><code>' . esc_html($realCountry) . '</code></td></tr>';
        echo '<tr><td><strong>Активна країна:</strong></td><td><strong>' . esc_html($currentEffective) . '</strong></td></tr>';
        echo '</tbody></table>';

        echo '<form method="post" action="" style="max-width:600px;background:#fff;padding:20px;border:1px solid #ccd0d4;">';
        wp_nonce_field('dbm_geosim_save', 'dbm_geosim_nonce');
        echo '<p><label for="simulated_country"><strong>Країна для симуляції:</strong></label><br>';
        echo '<select name="simulated_country" id="simulated_country" style="width:100%;margin-top:5px;max-width:400px;">';
        echo '<option value="disabled" ' . selected($simulated, null, false) . '>Вимкнути симуляцію</option>';
        foreach ($countries as $country) {
            echo '<option value="' . esc_attr($country) . '" ' . selected($simulated, $country, false) . '>' . esc_html($country) . '</option>';
        }
        echo '</select></p>';
        echo '<p><input type="submit" class="button button-primary" value="Зберегти"></p>';
        echo '</form></div>';
    }
}
