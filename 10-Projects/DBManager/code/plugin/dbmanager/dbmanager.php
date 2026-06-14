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

spl_autoload_register(function (string $class): void {
    if (! str_starts_with($class, 'DBM\\')) {
        return;
    }
    $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

(new DBM\Wp\Plugin())->register();
