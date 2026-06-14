<?php
// Гео-сценарій (Task 17). Запуск: wp eval-file /plugin/bin/scenario-geo.php
//
// Країна в проді детектується раз на init (CF-IPCountry із HTTP-запиту). WP-CLI не має
// HTTP-заголовків і запускає eval-file ПІСЛЯ init, тож країну подаємо явно у dbm_get —
// це проганяє реальний WP-зареєстрований dbm_get → render-core → кеш → гео-фільтр у
// живому WordPress. Мапінг CF-IPCountry → країна покрито юніт-тестом GeoDetectorTest.

update_option('dbm_cache', ['site' => 'domen.ua', 'version' => 5, 'values' => [
    ['key' => 'phone_ua_1', 'state' => 'ok', 'value' => '+380441234567', 'geo' => ['UA']],
    ['key' => 'tg_brand', 'state' => 'ok', 'value' => 'https://t.me/brand', 'geo' => ['WORLD']],
]], true);
update_option('dbm_settings', ['shortcode' => 'dbm', 'css_class' => 'val']);

echo 'UA phone:' . dbm_get('phone_ua_1', ['country' => 'UA']) . "\n";
echo 'UA tg:' . dbm_get('tg_brand', ['country' => 'UA']) . "\n";
echo 'PL phone:' . dbm_get('phone_ua_1', ['country' => 'PL']) . "\n";
echo 'PL tg:' . dbm_get('tg_brand', ['country' => 'PL']) . "\n";
