<?php

namespace DBM\Admin;

use DBM\Config\Settings;

class AdminPages
{
    public function __construct(private Settings $settings) {}

    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_menu_page('DBManager', 'DBManager', 'edit_posts', 'dbm-data', [$this, 'renderData']);
            add_submenu_page('dbm-data', 'Дані', 'Дані', 'edit_posts', 'dbm-data', [$this, 'renderData']);
            add_submenu_page('dbm-data', 'Вставка', 'Вставка', 'edit_posts', 'dbm-insert', [$this, 'renderInsert']);
            add_submenu_page('dbm-data', 'Налаштування', 'Налаштування', 'manage_options', 'dbm-settings', [$this, 'renderSettings']);
        });
        add_action('admin_init', function (): void {
            register_setting('dbm', 'dbm_settings');
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
}
