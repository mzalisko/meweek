<?php
/**
 * Plugin Name: DBManager
 * Description: Read-only клиент центральных данных (DBManager Core / DataBridge).
 * Version: 0.1.0
 * Requires PHP: 8.2
 */

if (! defined('ABSPATH')) {
    return; // прямой вызов вне WordPress — ничего не делаем
}

require_once __DIR__ . '/../shared/render-core.php';

// Composer vendor (bundled в дистрибутиве плагина): MaxMind\Db\Reader для PHP-лукапа
// страны по IP. Без этого гео падает до CF-заголовок/WORLD. В проде упаковывать с --no-dev.
$dbmVendor = __DIR__ . '/vendor/autoload.php';
if (is_file($dbmVendor)) {
    require_once $dbmVendor;
}

// prepend=true: ручной DBM\-loader должен предшествовать composer-loader из vendor, иначе
// composer пробует собственный (ложный для деплоя) baseDir для DBM\ и спамит include-warnings.
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
