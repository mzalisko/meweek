<?php
// Гео-сценарій (Task 17 + фікси фінального рев'ю #1/#2).
// Запуск: wp eval-file /plugin/bin/scenario-geo.php
//
// Країна в проді детектується раз на init (CF-IPCountry із HTTP-запиту). WP-CLI не має
// HTTP-заголовків і запускає eval-file ПІСЛЯ init, тож країну подаємо явно/через глобал —
// це проганяє реальний WP-зареєстрований dbm_get → render-core → кеш → гео-фільтр у
// живому WordPress. Мапінг CF-IPCountry → країна покрито юніт-тестом GeoDetectorTest.

update_option('dbm_cache', ['site' => 'domen.ua', 'version' => 5, 'values' => [
    ['key' => 'phone_ua_1', 'state' => 'ok', 'value' => '+380441234567', 'geo' => ['UA']],
    ['key' => 'tg_brand', 'state' => 'ok', 'value' => 'https://t.me/brand', 'geo' => ['WORLD']],
]], true);
update_option('dbm_settings', ['shortcode' => 'dbm', 'css_class' => 'val']);

// Явна країна у dbm_get.
echo 'UA phone:' . dbm_get('phone_ua_1', ['country' => 'UA']) . "\n";
echo 'UA tg:' . dbm_get('tg_brand', ['country' => 'UA']) . "\n";
echo 'PL phone:' . dbm_get('phone_ua_1', ['country' => 'PL']) . "\n";
echo 'PL tg:' . dbm_get('tg_brand', ['country' => 'PL']) . "\n";

// #2: dbm_get БЕЗ явної країни бере детектовану (глобал) — пряме PHP-використання у шаблонах.
$GLOBALS['dbm_country'] = 'UA';
echo 'implicit UA phone:' . dbm_get('phone_ua_1') . "\n";
$GLOBALS['dbm_country'] = 'PL';
echo 'implicit PL phone:' . dbm_get('phone_ua_1') . "\n";

// #1: MaxMind Reader має бути завантажуваним (vendor задеплоєно й підвантажено в dbmanager.php).
echo 'maxmind reader loadable:' . (class_exists('MaxMind\\Db\\Reader') ? 'yes' : 'NO') . "\n";
