<?php
/**
 * Plugin Name: DBManager
 * Description: Read-only клієнт центральних даних (DBManager Core / DataBridge).
 * Version: 0.1.0
 * Requires PHP: 8.2
 */

if (! defined('ABSPATH')) {
    return; // прямий виклик поза WordPress — нічого не робимо
}

require_once __DIR__ . '/../shared/render-core.php';

// Composer vendor (bundled у дистрибутиві плагіна): MaxMind\Db\Reader для PHP-лукапу
// країни за IP. Без цього гео падає до CF-заголовок/WORLD. У проді пакувати з --no-dev.
$dbmVendor = __DIR__ . '/vendor/autoload.php';
if (is_file($dbmVendor)) {
    require_once $dbmVendor;
}

// prepend=true: ручний DBM\-лоадер має передувати composer-лоадеру з vendor, інакше
// composer пробує власний (хибний для деплою) baseDir для DBM\ і спамить include-warnings.
spl_autoload_register(function (string $class): void {
    if (! str_starts_with($class, 'DBM\\')) {
        return;
    }
    $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);

(new DBM\Wp\Plugin())->register();
