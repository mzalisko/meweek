<?php
// Сценарій B (Task 11): рендер ПІСЛЯ видалення основного плагіна — свіжий PHP-процес.
// Має спрацювати mu-фолбек із кешу (dbm_cache переживає видалення плагіна, §8).
// Запуск: wp eval-file /plugin/bin/scenario-b.php
echo "B:" . do_shortcode('[dbm key="phone_ua_1"]') . "\n";
