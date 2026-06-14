#!/usr/bin/env bash
set -euo pipefail
# Інтеграційний сетап WordPress «з нуля» для двох сценаріїв-приймання (Task 11).
# Запуск: docker compose run --rm wpcli bash /plugin/bin/install-wp.sh
# Виконується в контейнері wpcli (образ wordpress:cli, uid 33 www-data),
# том wp-data спільний із сервісом wordpress.

cd /var/www/html

# 1. БД. Образ wordpress:cli має MariaDB-клієнт, який не вміє MySQL 8.4
#    (caching_sha2_password + self-signed TLS), тож `wp db create`/`wp db reset` не працюють.
#    Створюємо й чистимо БД через PHP mysqli (той самий драйвер, що й сам WordPress) — він
#    із MySQL 8.4 працює коректно. Ідемпотентно й повторювано.
php -r '
$h = getenv("WORDPRESS_DB_HOST"); $u = getenv("WORDPRESS_DB_USER");
$p = getenv("WORDPRESS_DB_PASSWORD"); $d = getenv("WORDPRESS_DB_NAME");
$m = mysqli_init();
$m->real_connect($h, $u, $p) or exit("DB connect failed: " . mysqli_connect_error() . "\n");
$m->query("DROP DATABASE IF EXISTS `$d`");
$m->query("CREATE DATABASE `$d` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
    or exit("DB create failed: " . $m->error . "\n");
echo "БД $d створено\n";
'

# 2. WordPress core «з нуля» (PHP/mysqli — працює з MySQL 8.4).
wp core install --url=http://localhost --title=DBM --admin_user=admin \
  --admin_password=admin --admin_email=a@a.dev --skip-email

# 3. Копіюємо код плагіна у том WP (не bind-монтування — щоб сценарій B міг його видалити).
#    Основний плагін робить require '../shared/render-core.php' відносно dbmanager/,
#    тож shared/ має лежати поряд: wp-content/plugins/shared/render-core.php.
rm -rf wp-content/plugins/dbmanager wp-content/plugins/shared
cp -r /plugin/dbmanager wp-content/plugins/dbmanager
cp -r /plugin/shared    wp-content/plugins/shared

# 4. mu-plugin фолбек + його самодостатня копія ядра рендеру (для сценарію B).
mkdir -p wp-content/mu-plugins
cp /plugin/mu/dbmanager-fallback.php wp-content/mu-plugins/dbmanager-fallback.php
cp /plugin/shared/render-core.php    wp-content/mu-plugins/render-core.php

# 5. Активуємо основний плагін.
wp plugin activate dbmanager

echo "install-wp: готово"
