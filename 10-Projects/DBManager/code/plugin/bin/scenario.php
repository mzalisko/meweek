<?php
// Сценарії-приймання Task 11. Запуск: wp eval-file /plugin/bin/scenario.php
// Сценарій A — у цьому ж процесі (плагін активний, рендер із кешу).
// Сценарій B — окрема команда wp eval ПІСЛЯ цього файлу (свіжий PHP-процес),
// бо видалити плагін і рендерити в одному процесі не можна (класи/функції вже завантажені).

// Засіваємо кеш як «отримане від bridge» (без реальної мережі — bridge «мертвий»).
update_option('dbm_cache', [
    'site' => 'domen.ua', 'version' => 4,
    'values' => [['key' => 'phone_ua_1', 'state' => 'ok', 'value' => '+380441234567']],
], true);
update_option('dbm_settings', ['shortcode' => 'dbm', 'css_class' => 'val']);

// Сценарій A: bridge мертвий — рендер тільки з кешу, жодного HTTP.
$a = do_shortcode('[dbm key="phone_ua_1"]');
echo "A:" . $a . "\n";

// Готуємо сценарій B: знімаємо й видаляємо основний плагін.
// Кеш (dbm_cache) НЕ чіпаємо — він має пережити видалення (§8), щоб mu-фолбек рендерив.
WP_CLI::runcommand('plugin deactivate dbmanager');
WP_CLI::runcommand('plugin delete dbmanager');
echo "B-pending (запусти окрему команду wp eval для сценарію B)\n";
