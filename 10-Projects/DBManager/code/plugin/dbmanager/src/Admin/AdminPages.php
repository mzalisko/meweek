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
            add_submenu_page('dbm-data', 'Данные', 'Данные', 'edit_posts', 'dbm-data', [$this, 'renderData']);
            add_submenu_page('dbm-data', 'Вставка', 'Вставка', 'edit_posts', 'dbm-insert', [$this, 'renderInsert']);
            add_submenu_page('dbm-data', 'Геосимуляция', 'Геосимуляция', 'edit_posts', 'dbm-geosim', [$this, 'renderGeoSim']);
            add_submenu_page('dbm-data', 'Настройки', 'Настройки', 'manage_options', 'dbm-settings', [$this, 'renderSettings']);
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
        echo '<div class="wrap"><h1>Данные</h1>';
        echo '<p>Версия: ' . (int) ($cache['version'] ?? 0) . '. Редактирование — только в центре.</p>';
        echo '<table class="widefat"><thead><tr><th>Ключ</th><th>Тип</th><th>Состояние</th><th>Значение</th></tr></thead><tbody>';
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
        echo '<div class="wrap"><h1>Настройки</h1><form method="post" action="options.php">';
        settings_fields('dbm');
        $o = get_option('dbm_settings');
        $o = is_array($o) ? $o : [];
        $field = function (string $key, string $label) use ($o): void {
            $val = esc_attr((string) ($o[$key] ?? ''));
            echo '<p><label>' . esc_html($label) . '<br><input type="text" name="dbm_settings[' . $key . ']" value="' . $val . '" size="60"></label></p>';
        };
        $field('signing_secret', 'Токен / Секрет подписи данных');
        $field('shortcode', 'Название шорткода (нейтральное)');
        $field('css_class', 'CSS-класс вывода');
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

        echo '<div class="wrap"><h1>Геосимуляция</h1>';

        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="updated notice is-dismissible"><p>Настройки геосимуляции сохранены!</p></div>';
        }

        echo '<p>Эта панель позволяет симулировать просмотр сайта из определенной страны для проверки отображения телефонов, цен и мессенджеров.</p>';

        echo '<table class="widefat" style="margin-bottom: 20px; max-width: 600px;">';
        echo '<thead><tr><th colspan="2">Текущее состояние локализации</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td style="width: 200px;"><strong>Режим симуляции:</strong></td><td>';
        if ($simulated !== null) {
            echo '<span style="color: #d63638; font-weight: bold;">Включено (Симуляция: ' . esc_html($simulated) . ')</span>';
        } else {
            echo '<span style="color: #67b878; font-weight: bold;">Выключено (Работает автоопределение)</span>';
        }
        echo '</td></tr>';
        echo '<tr><td><strong>Реальная страна (по IP):</strong></td><td><code>' . esc_html($real_country) . '</code> (IP: ' . esc_html($_SERVER['REMOTE_ADDR'] ?? '') . ')</td></tr>';
        echo '<tr><td><strong>Активная страна для сайта:</strong></td><td><strong style="font-size: 1.2em; color: #007cba;">' . esc_html($current_effective) . '</strong></td></tr>';
        echo '</tbody></table>';

        echo '<form method="post" action="" style="max-width: 600px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">';
        wp_nonce_field('dbm_geosim_save', 'dbm_geosim_nonce');

        echo '<p><label for="simulated_country"><strong>Выберите страну для симуляции:</strong></label><br>';
        echo '<select name="simulated_country" id="simulated_country" style="width: 100%; margin-top: 5px; max-width: 400px;">';
        echo '<option value="disabled" ' . selected($simulated, null, false) . '>— Выключить симуляцию (определять по IP) —</option>';
        foreach ($countries as $c) {
            echo '<option value="' . esc_attr($c) . '" ' . selected($simulated, $c, false) . '>' . esc_html($c) . '</option>';
        }
        echo '</select></p>';

        echo '<p><input type="submit" class="button button-primary" value="Сохранить настройки"></p>';
        echo '</form></div>';
    }
}
