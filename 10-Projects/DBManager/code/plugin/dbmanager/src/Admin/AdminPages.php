<?php

namespace DBM\Admin;

use DBM\Config\Settings;

class AdminPages
{
    public function __construct(
        private Settings $settings,
        private \DBM\Geo\GeoSimulation $simulation
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
            register_setting('dbm', 'dbm_settings');

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

    public function renderData(): void
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        echo '<div class="wrap"><h1>Дані</h1>';
        echo '<p>Версія: ' . (int) ($cache['version'] ?? 0) . '. Редагування — лише в центрі.</p>';
        echo '<table class="widefat"><thead><tr><th>Ключ</th><th>Тип</th><th>Стан</th><th>Значення</th></tr></thead><tbody>';
        foreach ($cache['values'] ?? [] as $v) {
            echo '<tr><td>' . esc_html($v['key'] ?? '') . '</td><td>' . esc_html($v['type'] ?? '') . '</td>'
                . '<td>' . esc_html($v['state'] ?? '') . '</td><td>' . esc_html($v['value'] ?? '') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderInsert(): void
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $builder = new SnippetBuilder($this->settings->shortcode);
        echo '<div class="wrap"><h1>Вставка</h1><table class="widefat"><thead><tr><th>Ключ</th><th>Шорткод</th><th>tel</th><th>PHP</th></tr></thead><tbody>';
        foreach ($cache['values'] ?? [] as $v) {
            $s = $builder->forKey((string) ($v['key'] ?? ''));
            echo '<tr><td>' . esc_html($v['key'] ?? '') . '</td><td><code>' . esc_html($s['shortcode']) . '</code></td>'
                . '<td><code>' . esc_html($s['tel']) . '</code></td><td><code>' . esc_html($s['php']) . '</code></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderSettings(): void
    {
        echo '<div class="wrap"><h1>Налаштування</h1><form method="post" action="options.php">';
        settings_fields('dbm');
        $o = get_option('dbm_settings');
        $o = is_array($o) ? $o : [];
        $field = function (string $key, string $label) use ($o): void {
            $val = esc_attr((string) ($o[$key] ?? ''));
            echo '<p><label>' . esc_html($label) . '<br><input type="text" name="dbm_settings[' . $key . ']" value="' . $val . '" size="60"></label></p>';
        };
        $field('bridge_url', 'URL DataBridge');
        $field('site_token', 'Токен сайта');
        $field('signing_secret', 'Секрет підпису даних');
        $field('ping_secret', 'Секрет пінга');
        $field('shortcode', 'Назва шорткода (нейтральна)');
        $field('css_class', 'CSS-клас виводу');
        submit_button();
        echo '</form></div>';
    }

    public function renderGeoSim(): void
    {
        $simulated = $this->simulation->getSimulatedCountry();

        $detector = new \DBM\Geo\GeoDetector(
            new \DBM\Geo\MaxMindCountryLookup((string) (get_option('dbm_geodb_path') ?: ''))
        );
        $real_country = $detector->detect(
            ['CF-IPCountry' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''],
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))
        );

        $current_effective = $simulated !== null ? $simulated : $real_country;

        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $countries = $this->simulation->getAvailableCountries($cache);

        echo '<div class="wrap"><h1>Геосимуляція</h1>';

        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="updated notice is-dismissible"><p>Налаштування геосимуляції збережено!</p></div>';
        }

        echo '<p>Ця панель дозволяє симулювати перегляд сайту з певної країни для перевірки відображення телефонів, цін та месенджерів.</p>';

        echo '<table class="widefat" style="margin-bottom: 20px; max-width: 600px;">';
        echo '<thead><tr><th colspan="2">Поточний стан локалізації</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td style="width: 200px;"><strong>Режим симуляції:</strong></td><td>';
        if ($simulated !== null) {
            echo '<span style="color: #d63638; font-weight: bold;">Увімкнено (Симуляція: ' . esc_html($simulated) . ')</span>';
        } else {
            echo '<span style="color: #67b878; font-weight: bold;">Вимкнено (Працює авто-визначення)</span>';
        }
        echo '</td></tr>';
        echo '<tr><td><strong>Реальна країна (за IP):</strong></td><td><code>' . esc_html($real_country) . '</code> (IP: ' . esc_html($_SERVER['REMOTE_ADDR'] ?? '') . ')</td></tr>';
        echo '<tr><td><strong>Активна країна для сайту:</strong></td><td><strong style="font-size: 1.2em; color: #007cba;">' . esc_html($current_effective) . '</strong></td></tr>';
        echo '</tbody></table>';

        echo '<form method="post" action="" style="max-width: 600px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">';
        wp_nonce_field('dbm_geosim_save', 'dbm_geosim_nonce');

        echo '<p><label for="simulated_country"><strong>Оберіть країну для симуляції:</strong></label><br>';
        echo '<select name="simulated_country" id="simulated_country" style="width: 100%; margin-top: 5px; max-width: 400px;">';
        echo '<option value="disabled" ' . selected($simulated, null, false) . '>— Вимкнути симуляцію (визначати за IP) —</option>';
        foreach ($countries as $c) {
            echo '<option value="' . esc_attr($c) . '" ' . selected($simulated, $c, false) . '>' . esc_html($c) . '</option>';
        }
        echo '</select></p>';

        echo '<p><input type="submit" class="button button-primary" value="Зберегти налаштування"></p>';
        echo '</form></div>';
    }
}
