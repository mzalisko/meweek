<?php

namespace DBM\Admin;

use DBM\Config\Settings;

class AdminPages
{
    public function __construct(
        private Settings $settings,
        private \DBM\Geo\GeoSimulation $simulation,
    ) {}

    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_menu_page('DBManager', 'DBManager', 'edit_posts', 'dbm-data', [$this, 'renderData']);
            add_submenu_page('dbm-data', 'Данные', 'Данные', 'edit_posts', 'dbm-data', [$this, 'renderData']);
            add_submenu_page('dbm-data', 'Вставка', 'Вставка', 'edit_posts', 'dbm-insert', [$this, 'renderInsert']);
            add_submenu_page('dbm-data', 'Кастомизация', 'Кастомизация', 'manage_options', 'dbm-custom', [$this, 'renderCustom']);
            add_submenu_page('dbm-data', 'Геосимуляция', 'Геосимуляция', 'edit_posts', 'dbm-geosim', [$this, 'renderGeoSim']);
            add_submenu_page('dbm-data', 'Настройки', 'Настройки', 'manage_options', 'dbm-settings', [$this, 'renderSettings']);
        });

        add_action('admin_init', function (): void {
            register_setting('dbm', 'dbm_settings', ['sanitize_callback' => [$this, 'sanitizeSettings']]);
            register_setting('dbm_custom', 'dbm_custom_settings', ['sanitize_callback' => [$this, 'sanitizeCustomSettings']]);

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

    public function sanitizeSettings($input): array
    {
        $input = is_array($input) ? $input : [];
        $old = get_option('dbm_settings');
        $old = is_array($old) ? $old : [];

        $settings = [
            'signing_secret' => (string) ($old['signing_secret'] ?? ''),
            'shortcode' => sanitize_key((string) ($input['shortcode'] ?? ($old['shortcode'] ?? 'dbm'))),
            'css_class' => sanitize_html_class((string) ($input['css_class'] ?? ($old['css_class'] ?? ''))),
            'connection_site_id' => (int) ($old['connection_site_id'] ?? 0),
            'connection_ping_url' => esc_url_raw((string) ($old['connection_ping_url'] ?? '')),
            'connection_saved_at' => sanitize_text_field((string) ($old['connection_saved_at'] ?? '')),
        ];

        if ($settings['shortcode'] === '') {
            $settings['shortcode'] = 'dbm';
        }

        $connectionKey = trim((string) ($input['connection_key'] ?? ''));
        if ($connectionKey !== '') {
            $decoded = Settings::decodeConnectionKey($connectionKey);

            if ($decoded === null) {
                add_settings_error(
                    'dbm_settings',
                    'dbm_connection_invalid',
                    'Неверный ключ подключения. Создайте новый ключ в DBManager Core и вставьте его полностью.',
                    'error'
                );

                return $settings;
            }

            $settings['signing_secret'] = (string) $decoded['signing_secret'];
            $settings['shortcode'] = sanitize_key((string) ($decoded['shortcode'] ?: $settings['shortcode']));
            $settings['connection_site_id'] = (int) ($decoded['site_id'] ?? 0);
            $settings['connection_ping_url'] = esc_url_raw((string) $decoded['ping_url']);
            $settings['connection_saved_at'] = function_exists('current_time')
                ? (string) current_time('mysql')
                : gmdate('Y-m-d H:i:s');

            delete_option('dbm_cache');

            add_settings_error(
                'dbm_settings',
                'dbm_connection_saved',
                'Подключение сохранено. Плагин слушает подписанные обновления от DataBridge.',
                'updated'
            );
        }

        return $settings;
    }

    public function sanitizeCustomSettings($input): array
    {
        $input = is_array($input) ? $input : [];
        $output = [];

        $output['class_phone'] = sanitize_text_field((string) ($input['class_phone'] ?? ''));
        $output['class_messenger'] = sanitize_text_field((string) ($input['class_messenger'] ?? ''));
        $output['class_price'] = sanitize_text_field((string) ($input['class_price'] ?? ''));

        foreach ($input as $key => $value) {
            if (str_starts_with((string) $key, 'image_')) {
                $output[$key] = esc_url_raw((string) $value);
            }
        }

        return $output;
    }

    public function renderData(): void
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $values = $this->orderedValues($cache);
        $counts = $this->counts($values);

        $this->adminStyles();

        echo '<div class="wrap dbm-admin">';
        echo '<div class="dbm-hero"><div><span class="dbm-eyebrow">DBManager</span><h1>Данные сайта</h1><p>Плагин показывает кеш, доставленный из центральной CRM. Редактирование происходит только в Core.</p></div>';
        echo '<div class="dbm-version"><span>Версия</span><strong>' . (int) ($cache['version'] ?? 0) . '</strong></div></div>';
        echo '<div class="dbm-stats">';
        echo '<div class="dbm-stat"><span>ID сайта</span><strong>' . (isset($cache['site_id']) && $cache['site_id'] > 0 ? (int) $cache['site_id'] : 'локальный кеш') . '</strong></div>';
        echo '<div class="dbm-stat"><span>Номера</span><strong>' . (int) ($counts['phone'] ?? 0) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Мессенджеры</span><strong>' . (int) ($counts['messenger'] ?? 0) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Цены</span><strong>' . (int) ($counts['price'] ?? 0) . '</strong></div>';
        echo '</div>';
        echo '<div class="dbm-card">';
        echo '<div class="dbm-card-head"><div><h2>Текущие значения</h2><p>Порядок соответствует CRM: телефоны, мессенджеры, цены.</p></div>';
        echo '<div class="dbm-tabs" style="display: flex; gap: 6px; align-self: center;">';
        echo '<button type="button" class="button button-primary dbm-tab-btn" data-filter="all">Все</button>';
        echo '<button type="button" class="button button-secondary dbm-tab-btn" data-filter="phone">Номера</button>';
        echo '<button type="button" class="button button-secondary dbm-tab-btn" data-filter="messenger">Мессенджеры</button>';
        echo '<button type="button" class="button button-secondary dbm-tab-btn" data-filter="price">Цены</button>';
        echo '</div>';
        echo '</div>';
        echo '<table class="dbm-table"><thead><tr><th>Данные</th><th>Гео</th><th>Статус</th><th>Значение</th></tr></thead><tbody>';
        foreach ($values as $value) {
            $type = (string) ($value['type'] ?? '');
            echo '<tr class="dbm-row" data-type="' . esc_attr($type) . '">';
            echo '<td><div class="dbm-key">' . esc_html((string) ($value['key'] ?? '')) . '</div>' . $this->typeBadge($type) . '</td>';
            if ($type === 'price' && ! empty($value['prices'])) {
                echo '<td>' . $this->geoPricesList($value['prices']) . '</td>';
            } else {
                echo '<td>' . $this->geoChips($value['geo'] ?? []) . '</td>';
            }
            echo '<td>' . $this->stateBadge((string) ($value['state'] ?? 'ok')) . '</td>';
            echo '<td>' . $this->valuePreview($value, $values) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '<script>
        (function() {
            var tabs = document.querySelectorAll(".dbm-tab-btn");
            var rows = document.querySelectorAll(".dbm-row");
            tabs.forEach(function(tab) {
                tab.addEventListener("click", function() {
                    var filter = tab.getAttribute("data-filter");
                    tabs.forEach(function(t) {
                        t.classList.remove("button-primary");
                        t.classList.add("button-secondary");
                    });
                    tab.classList.remove("button-secondary");
                    tab.classList.add("button-primary");
                    rows.forEach(function(row) {
                        if (filter === "all" || row.getAttribute("data-type") === filter) {
                            row.style.display = "";
                        } else {
                            row.style.display = "none";
                        }
                    });
                });
            });
        })();
        </script>';
        echo '</div>';
    }

    public function renderInsert(): void
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];

        $this->adminStyles();

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Вставка</span><h1>Коды для сайта</h1><p>Готовые shortcode для значений и презентационного блока.</p></div></div>';
        echo '<div class="dbm-card dbm-highlight" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;"><div><h2>Презентационный блок</h2><p>Интерактивный блок с телефонами, мессенджерами и ценами из локального кеша.</p></div><div style="display:flex; gap:12px;">'
            . $this->copyableSnippet('', '[dbm_presentation]')
            . $this->copyableSnippet('', '[dbm_block]')
            . '</div></div>';
        echo '<div class="dbm-card"><div class="dbm-card-head"><div><h2>Отдельные значения</h2><p>Для каждого типа данных доступны специализированные шорткоды и PHP-функции.</p></div></div>';
        echo '<table class="dbm-table"><thead><tr><th style="width:20%">Данные</th><th style="width:40%">Шорткоды для вставки</th><th style="width:40%">PHP вызовы</th></tr></thead><tbody>';
        foreach ($this->orderedValues($cache) as $value) {
            $key = (string) ($value['key'] ?? '');
            $type = (string) ($value['type'] ?? '');
            
            echo '<tr>';
            echo '<td><div class="dbm-key">' . esc_html($key) . '</div>' . $this->typeBadge($type) . '</td>';
            
            echo '<td>';
            if ($type === 'phone') {
                echo $this->copyableSnippet('Стандартный:', '[' . $this->settings->shortcode . ' key="' . $key . '"]');
                echo $this->copyableSnippet('Ссылка tel:', '[' . $this->settings->shortcode . ' key="' . $key . '" format="tel"]');
                echo $this->copyableSnippet('Блок с мессенджерами:', '[dbm_phone_block key="' . $key . '"]');
            } elseif ($type === 'price') {
                echo $this->copyableSnippet('Универсальная цена:', '[dbm_price key="' . $key . '"]');
                
                $prices = $value['prices'] ?? [];
                foreach ($prices as $p) {
                    $lbl = trim((string) ($p['label'] ?? ''));
                    $geos = $p['geo'] ?? ['WORLD'];
                    if (! is_array($geos)) {
                        $geos = [$geos];
                    }
                    
                    $suffix = '';
                    $suffixLabel = '';
                    if ($lbl !== '') {
                        $suffix = strtolower($lbl);
                        $suffixLabel = 'Цена для ' . $lbl . ':';
                    } else {
                        $firstGeo = strtoupper(trim((string) ($geos[0] ?? 'WORLD')));
                        $suffix = strtolower($firstGeo);
                        $suffixLabel = 'Цена для ' . $firstGeo . ':';
                    }
                    
                    $suffixKey = $key . '_' . $suffix;
                    echo $this->copyableSnippet($suffixLabel, '[dbm_price key="' . $suffixKey . '"]');
                }
                
                echo $this->copyableSnippet('Числовое значение:', '[' . $this->settings->shortcode . ' key="' . $key . '"]');
            } else {
                echo $this->copyableSnippet('', '[' . $this->settings->shortcode . ' key="' . $key . '"]');
            }
            echo '</td>';
            
            echo '<td>';
            if ($type === 'phone') {
                echo $this->copyableSnippet('Значение:', 'dbm_get(\'' . $key . '\')');
                echo $this->copyableSnippet('Блок:', 'dbm_phone_block(\'' . $key . '\')');
            } elseif ($type === 'price') {
                echo $this->copyableSnippet('Универсальная:', 'dbm_price(\'' . $key . '\')');
                
                $prices = $value['prices'] ?? [];
                foreach ($prices as $p) {
                    $lbl = trim((string) ($p['label'] ?? ''));
                    $geos = $p['geo'] ?? ['WORLD'];
                    if (! is_array($geos)) {
                        $geos = [$geos];
                    }
                    
                    $suffix = '';
                    $suffixLabel = '';
                    if ($lbl !== '') {
                        $suffix = strtolower($lbl);
                        $suffixLabel = 'Цена для ' . $lbl . ':';
                    } else {
                        $firstGeo = strtoupper(trim((string) ($geos[0] ?? 'WORLD')));
                        $suffix = strtolower($firstGeo);
                        $suffixLabel = 'Цена для ' . $firstGeo . ':';
                    }
                    
                    $suffixKey = $key . '_' . $suffix;
                    echo $this->copyableSnippet($suffixLabel, 'dbm_price(\'' . $suffixKey . '\')');
                }
                
                echo $this->copyableSnippet('Значение:', 'dbm_get(\'' . $key . '\')');
            } else {
                echo $this->copyableSnippet('', 'dbm_get(\'' . $key . '\')');
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        
        echo '<script>
        (function() {
            var btns = document.querySelectorAll(".dbm-copy-btn");
            btns.forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var text = btn.getAttribute("data-clipboard");
                    if (!text) return;
                    
                    function copyToClipboard(val) {
                        if (navigator.clipboard && window.isSecureContext) {
                            return navigator.clipboard.writeText(val);
                        } else {
                            var textArea = document.createElement("textarea");
                            textArea.value = val;
                            textArea.style.position = "fixed";
                            textArea.style.left = "-999999px";
                            textArea.style.top = "-999999px";
                            document.body.appendChild(textArea);
                            textArea.focus();
                            textArea.select();
                            return new Promise(function(resolve, reject) {
                                if (document.execCommand("copy")) {
                                    resolve();
                                } else {
                                    reject();
                                }
                                textArea.remove();
                            });
                        }
                    }
                    
                    copyToClipboard(text).then(function() {
                        btn.classList.add("copied");
                        var copyIcon = btn.querySelector(".dbm-copy-icon");
                        var checkIcon = btn.querySelector(".dbm-check-icon");
                        if (copyIcon && checkIcon) {
                            copyIcon.style.display = "none";
                            checkIcon.style.display = "inline";
                        }
                        
                        setTimeout(function() {
                            btn.classList.remove("copied");
                            if (copyIcon && checkIcon) {
                                copyIcon.style.display = "inline";
                                checkIcon.style.display = "none";
                            }
                        }, 2000);
                    }).catch(function(err) {
                        console.error("Failed to copy: ", err);
                    });
                });
            });
        })();
        </script>';
        echo '</div>';
    }

    public function renderSettings(): void
    {
        $options = get_option('dbm_settings');
        $options = is_array($options) ? $options : [];
        $hasConnection = (string) ($options['signing_secret'] ?? '') !== '';
        $listenerUrl = function_exists('rest_url') ? rest_url('dbm/v1/ping') : '';

        $this->adminStyles();

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Настройки</span><h1>DBManager</h1><p>Подключение плагина как пассивного слушателя DataBridge.</p></div>';
        echo '<div class="dbm-status-card">' . ($hasConnection ? $this->stateBadge('ok') : $this->stateBadge('pending')) . '</div></div>';
        settings_errors('dbm_settings');

        echo '<div class="dbm-card">';
        echo '<h2>Статус подключения</h2><p class="dbm-muted">';
        echo $hasConnection ? 'Плагин подключен. Он не делает исходящих запросов к центральной CRM.' : 'Вставьте ключ подключения из DBManager Core. После этого плагин будет слушать подписанные обновления.';
        echo '</p>';

        if ($listenerUrl !== '') {
            echo '<div class="dbm-field-row"><span>Локальный endpoint</span><code>' . esc_html($listenerUrl) . '</code></div>';
        }
        if (! empty($options['connection_site_id'])) {
            echo '<div class="dbm-field-row"><span>ID сайта</span><strong>' . (int) $options['connection_site_id'] . '</strong></div>';
            if (! empty($options['connection_ping_url'])) {
                echo '<div class="dbm-field-row"><span>Endpoint доставки</span><code>' . esc_html((string) $options['connection_ping_url']) . '</code></div>';
            }
            if (! empty($options['connection_saved_at'])) {
                echo '<div class="dbm-field-row"><span>Ключ сохранен</span><strong>' . esc_html((string) $options['connection_saved_at']) . '</strong></div>';
            }
        }

        $cache = get_option('dbm_cache');
        if (is_array($cache) && isset($cache['version'])) {
            echo '<div class="dbm-field-row"><span>Последняя версия данных</span><strong>' . (int) $cache['version'] . '</strong></div>';
        } elseif ($hasConnection) {
            echo '<div class="dbm-field-row"><span>Данные</span><strong>еще не получены</strong></div>';
        }
        echo '</div>';

        echo '<form method="post" action="options.php" class="dbm-card dbm-form">';
        echo '<h2>Параметры</h2>';
        settings_fields('dbm');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="dbm_connection_key">Ключ подключения</label></th><td>';
        echo '<textarea id="dbm_connection_key" name="dbm_settings[connection_key]" rows="4" class="large-text code" autocomplete="off" spellcheck="false" placeholder="DBM1..."></textarea>';
        echo '<p class="description">Ключ показывается в Core один раз. Он содержит только секрет подписи для этого сайта, без адреса CRM.</p>';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="dbm_shortcode">Название shortcode</label></th><td>';
        echo '<input id="dbm_shortcode" type="text" name="dbm_settings[shortcode]" value="' . esc_attr((string) ($options['shortcode'] ?? 'dbm')) . '" class="regular-text">';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="dbm_css_class">CSS-класс вывода</label></th><td>';
        echo '<input id="dbm_css_class" type="text" name="dbm_settings[css_class]" value="' . esc_attr((string) ($options['css_class'] ?? '')) . '" class="regular-text">';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button('Сохранить настройки');
        echo '</form></div>';
    }

    public function renderCustom(): void
    {
        wp_enqueue_media();
        
        $options = get_option('dbm_custom_settings');
        $options = is_array($options) ? $options : [];

        $this->adminStyles();

        echo '<div class="wrap dbm-admin">';
        echo '<div class="dbm-hero"><div><span class="dbm-eyebrow">Кастомизация</span><h1>Внешний вид элементов</h1><p>Настройка изображений мессенджеров и дополнительных CSS-классов.</p></div></div>';
        
        settings_errors('dbm_custom_settings');

        echo '<form method="post" action="options.php" class="dbm-card dbm-form">';
        echo '<h2>Изображения мессенджеров</h2>';
        echo '<p class="dbm-muted">Загрузите изображения, которые заменят стандартные SVG-иконки в блоках телефонов.</p>';
        
        settings_fields('dbm_custom');
        
        echo '<table class="form-table" role="presentation"><tbody>';
        
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $networks = [];
        foreach ($cache['values'] ?? [] as $val) {
            if (($val['type'] ?? '') === 'messenger' && ! empty($val['network'])) {
                $net = trim((string) $val['network']);
                $netLower = strtolower($net);
                if ($netLower !== '' && ! isset($networks[$netLower])) {
                    $networks[$netLower] = $net;
                }
            }
        }
        if (empty($networks)) {
            $networks = [
                'telegram' => 'Telegram',
                'whatsapp' => 'WhatsApp',
                'viber' => 'Viber'
            ];
        }
        
        foreach ($networks as $net => $label) {
            $val = esc_url((string) ($options['image_' . $net] ?? ''));
            $displayStyle = $val === '' ? 'display:none;' : '';
            
            echo '<tr><th scope="row"><label for="dbm_image_' . $net . '">' . $label . '</label></th><td>';
            echo '<div style="display:flex; align-items:center; gap:12px;">';
            echo '<img id="dbm_preview_' . $net . '" src="' . $val . '" style="width:36px; height:36px; object-fit:contain; border:1px solid var(--dbm-line); padding:2px; border-radius:2px; background:#fff;' . $displayStyle . '" alt="Preview" />';
            echo '<input id="dbm_image_' . $net . '" type="text" name="dbm_custom_settings[image_' . $net . ']" value="' . $val . '" class="regular-text" style="flex-grow:1; max-width:400px;" readonly>';
            echo '<button type="button" class="button dbm-upload-btn" data-input="dbm_image_' . $net . '" data-preview="dbm_preview_' . $net . '">Загрузить</button>';
            echo '<button type="button" class="button dbm-clear-btn" data-input="dbm_image_' . $net . '" data-preview="dbm_preview_' . $net . '">Очистить</button>';
            echo '</div>';
            echo '</td></tr>';
        }
        
        echo '</tbody></table>';
        
        echo '<h2 style="margin-top:24px;">Дополнительные CSS-классы</h2>';
        echo '<p class="dbm-muted">Добавьте произвольные CSS-классы к элементам для стилизации (классы разделяются пробелами).</p>';
        
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="dbm_class_phone">Для блоков телефонов</label></th><td>';
        echo '<input id="dbm_class_phone" type="text" name="dbm_custom_settings[class_phone]" value="' . esc_attr((string) ($options['class_phone'] ?? '')) . '" class="regular-text">';
        echo '<p class="description">Добавляется к контейнеру телефона .dbm-phone-block</p>';
        echo '</td></tr>';
        
        echo '<tr><th scope="row"><label for="dbm_class_messenger">Для ссылок мессенджеров</label></th><td>';
        echo '<input id="dbm_class_messenger" type="text" name="dbm_custom_settings[class_messenger]" value="' . esc_attr((string) ($options['class_messenger'] ?? '')) . '" class="regular-text">';
        echo '<p class="description">Добавляется к ссылкам мессенджеров .dbm-phone-block__msg-link</p>';
        echo '</td></tr>';
        
        echo '<tr><th scope="row"><label for="dbm_class_price">Для цен</label></th><td>';
        echo '<input id="dbm_class_price" type="text" name="dbm_custom_settings[class_price]" value="' . esc_attr((string) ($options['class_price'] ?? '')) . '" class="regular-text">';
        echo '<p class="description">Добавляется к тегу цены, выводимому через [dbm_price] или dbm_price()</p>';
        echo '</td></tr>';
        
        echo '</tbody></table>';
        
        submit_button('Сохранить кастомизацию');
        echo '</form></div>';
        
        echo '<script>
        jQuery(document).ready(function($){
            $(".dbm-upload-btn").click(function(e) {
                e.preventDefault();
                var button = $(this);
                var inputId = button.data("input");
                var previewId = button.data("preview");
                
                var custom_uploader = wp.media({
                    title: "Выберите изображение",
                    button: {
                        text: "Использовать это изображение"
                    },
                    multiple: false
                });
                
                custom_uploader.on("select", function() {
                    var attachment = custom_uploader.state().get("selection").first().toJSON();
                    $("#" + inputId).val(attachment.url);
                    $("#" + previewId).attr("src", attachment.url).show();
                });
                
                custom_uploader.open();
            });
            
            $(".dbm-clear-btn").click(function(e) {
                e.preventDefault();
                var button = $(this);
                var inputId = button.data("input");
                var previewId = button.data("preview");
                $("#" + inputId).val("");
                $("#" + previewId).attr("src", "").hide();
            });
        });
        </script>';
    }

    public function renderGeoSim(): void
    {
        $simulated = $this->simulation->getSimulatedCountry();
        $detector = new \DBM\Geo\GeoDetector(
            new \DBM\Geo\MaxMindCountryLookup((string) (get_option('dbm_geodb_path') ?: ''))
        );
        $realCountry = $detector->detect(
            ['CF-IPCountry' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''],
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))
        );

        $currentEffective = $simulated !== null ? $simulated : $realCountry;
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $countries = $this->simulation->getAvailableCountries($cache);

        $this->adminStyles();

        echo '<div class="wrap dbm-admin"><div class="dbm-hero"><div><span class="dbm-eyebrow">Геосимуляция</span><h1>Проверка гео</h1><p>Симуляция страны для проверки видимости данных в плагине.</p></div>';
        echo '<div class="dbm-status-card">' . ($simulated !== null ? $this->stateBadge('pinned') : $this->stateBadge('ok')) . '</div></div>';

        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="updated notice is-dismissible"><p>Настройки геосимуляции сохранены.</p></div>';
        }

        echo '<div class="dbm-stats">';
        echo '<div class="dbm-stat"><span>Режим</span><strong>' . ($simulated !== null ? 'включен' : 'выключен') . '</strong></div>';
        echo '<div class="dbm-stat"><span>IP-страна</span><strong>' . esc_html($realCountry) . '</strong></div>';
        echo '<div class="dbm-stat"><span>Активная страна</span><strong>' . esc_html($currentEffective) . '</strong></div>';
        echo '</div>';

        echo '<form method="post" action="" class="dbm-card dbm-form">';
        echo '<h2>Страна для симуляции</h2>';
        wp_nonce_field('dbm_geosim_save', 'dbm_geosim_nonce');
        echo '<p><label for="simulated_country"><strong>Страна</strong></label><br>';
        echo '<select name="simulated_country" id="simulated_country" class="dbm-select">';
        echo '<option value="disabled" ' . selected($simulated, null, false) . '>Отключить симуляцию</option>';
        foreach ($countries as $country) {
            echo '<option value="' . esc_attr($country) . '" ' . selected($simulated, $country, false) . '>' . esc_html($country) . '</option>';
        }
        echo '</select></p>';
        echo '<p><input type="submit" class="button button-primary" value="Сохранить"></p>';
        echo '</form></div>';
    }

    private function adminStyles(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        echo <<<'HTML'
<style>
.dbm-admin{--dbm-ink:#0f172a;--dbm-muted:#475569;--dbm-line:#e2e8f0;--dbm-soft:#f8fafc;--dbm-accent:#2563eb;--dbm-accent-soft:#eff6ff;--dbm-ok:#10b981;--dbm-warn:#f59e0b;--dbm-bad:#ef4444;max-width:1180px;color:var(--dbm-ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif}
.dbm-admin *{box-sizing:border-box}
.dbm-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin:24px 0 16px;padding:24px 30px;border:1px solid var(--dbm-line);border-left:4px solid var(--dbm-accent);border-radius:4px;background:#fff;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03)}
.dbm-hero h1{margin:2px 0 6px;color:var(--dbm-ink);font-size:26px;font-weight:700;line-height:1.2}
.dbm-hero p,.dbm-muted{margin:0;color:var(--dbm-muted);font-size:13px;line-height:1.5}
.dbm-eyebrow{color:var(--dbm-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.dbm-version,.dbm-status-card{min-width:120px;text-align:right}
.dbm-version span{display:block;color:var(--dbm-muted);font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.05em}
.dbm-version strong{display:block;margin-top:4px;color:var(--dbm-accent);font-size:32px;line-height:1;font-weight:800}
.dbm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
.dbm-stat{min-width:0;padding:16px 20px;border:1px solid var(--dbm-line);border-radius:4px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.02)}
.dbm-stat span{display:block;color:var(--dbm-muted);font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.05em}
.dbm-stat strong{display:block;margin-top:6px;color:var(--dbm-ink);font-size:20px;line-height:1.25;font-weight:700;overflow-wrap:anywhere}
.dbm-card{margin:0 0 16px;padding:24px;border:1px solid var(--dbm-line);border-radius:4px;background:#fff;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03)}
.dbm-card h2{margin:0 0 6px;color:var(--dbm-ink);font-size:18px;font-weight:700}
.dbm-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}
.dbm-highlight{display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-color:#cbd5e1;background:linear-gradient(180deg,#fff,#f8fafc)}
.dbm-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid var(--dbm-line);border-radius:4px;background:#fff}
.dbm-table th{padding:12px 16px;background:#f8fafc;color:var(--dbm-muted);font-size:11px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--dbm-line)}
.dbm-table td{padding:14px 16px;border-top:1px solid var(--dbm-line);vertical-align:middle;color:var(--dbm-ink);font-size:13px}
.dbm-table tr:hover td{background:#f8fafc}
.dbm-key{margin-bottom:4px;color:var(--dbm-ink);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-weight:700;font-size:13px}
.dbm-value{display:flex;flex-direction:column;gap:4px;min-width:0}
.dbm-value strong{font-size:14px;color:var(--dbm-ink);overflow-wrap:anywhere;font-weight:600}
.dbm-value small{color:var(--dbm-muted);font-size:11px}
.dbm-badge,.dbm-chip,.dbm-state{display:inline-flex;align-items:center;gap:6px;min-height:22px;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:600;line-height:1}
.dbm-badge{background:var(--dbm-accent-soft);color:var(--dbm-accent);border:1px solid #bfdbfe}
.dbm-chip{margin:2px;background:#f1f5f9;color:var(--dbm-muted);border:1px solid var(--dbm-line)}
.dbm-chip--deny{background:#fef2f2;color:var(--dbm-bad);border-color:#fca5a5}
.dbm-net-badge{display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;text-transform:uppercase;padding:2px 5px;border-radius:2px;margin-left:6px;cursor:help;line-height:1;border:1px solid var(--dbm-line);background:#f8fafc;color:var(--dbm-muted);vertical-align:middle}
.dbm-net-badge--telegram{background:#eff6ff;color:#2563eb;border-color:#bfdbfe}
.dbm-net-badge--whatsapp{background:#ecfdf5;color:#10b981;border-color:#a7f3d0}
.dbm-net-badge--viber{background:#fbf7ff;color:#7c3aed;border-color:#ddd6fe}
.dbm-state{background:#ecfdf5;color:var(--dbm-ok);border:1px solid #a7f3d0}
.dbm-state--warn,.dbm-state--on_reserve,.dbm-state--pinned{background:#fffbeb;color:var(--dbm-warn);border:1px solid #fde68a}
.dbm-state--bad,.dbm-state--hidden,.dbm-state--exhausted{background:#fef2f2;color:var(--dbm-bad);border:1px solid #fca5a5}
.dbm-state--pending{background:#f1f5f9;color:var(--dbm-muted);border:1px solid var(--dbm-line)}
.dbm-field-row{display:flex;justify-content:space-between;gap:14px;padding:12px 0;border-top:1px solid var(--dbm-line)}
.dbm-field-row span{color:var(--dbm-muted);font-weight:600;font-size:13px}
.dbm-field-row code,.dbm-card code{display:inline-flex;max-width:100%;padding:3px 6px;border-radius:3px;background:#f1f5f9;border:1px solid var(--dbm-line);color:#334155;font-size:12px;overflow-wrap:anywhere;white-space:normal}
.dbm-form textarea,.dbm-form input[type=text],.dbm-select{max-width:620px;border:1px solid var(--dbm-line);border-radius:4px;padding:8px 12px;font-size:13px;transition:border-color 0.15s ease-in-out,box-shadow 0.15s ease-in-out;background:#fff;color:var(--dbm-ink)}
.dbm-form textarea:focus,.dbm-form input[type=text]:focus,.dbm-select:focus{border-color:var(--dbm-accent);box-shadow:0 0 0 3px rgba(37,99,254,0.15);outline:none}
@media (max-width:782px){.dbm-hero{display:block;padding:16px 20px}.dbm-version,.dbm-status-card{text-align:left;margin-top:12px}.dbm-stats{grid-template-columns:1fr 1fr}.dbm-table{display:block;overflow-x:auto}.dbm-field-row{display:block}.dbm-field-row code,.dbm-field-row strong{display:inline-block;margin-top:5px}}
.dbm-snippet-item{margin-bottom:8px;display:flex;align-items:center;gap:8px}
.dbm-snippet-item:last-child{margin-bottom:0}
.dbm-snippet-item span,.dbm-snippet-item .dbm-label{color:var(--dbm-muted);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;width:180px;flex-shrink:0;display:inline-block}
.dbm-snippet-item code{vertical-align:middle}
.dbm-copy-wrapper{display:inline-flex;align-items:center;background:#f8fafc;border:1px solid var(--dbm-line);border-radius:3px;padding:2px 4px;gap:4px;vertical-align:middle}
.dbm-copy-wrapper code{background:none !important;border:none !important;padding:0 6px !important;margin:0 !important;font-size:11px !important;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--dbm-ink);vertical-align:middle}
.dbm-copy-btn{background:#fff;border:1px solid var(--dbm-line);border-radius:3px;padding:4px 6px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:var(--dbm-muted);transition:all .15s ease}
.dbm-copy-btn:hover{color:var(--dbm-accent);border-color:var(--dbm-accent);background:var(--dbm-accent-soft)}
.dbm-copy-btn.copied{color:var(--dbm-ok);border-color:var(--dbm-ok);background:#ecfdf5}
.dbm-price-list{display:flex;flex-direction:column;gap:6px}
.dbm-price-row{display:flex;align-items:center;min-height:24px}
</style>
HTML;
    }

    /** @param array<int,array<string,mixed>> $values @return array<string,int> */
    private function counts(array $values): array
    {
        $counts = ['phone' => 0, 'messenger' => 0, 'price' => 0];
        foreach ($values as $value) {
            $type = (string) ($value['type'] ?? '');
            if (array_key_exists($type, $counts)) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    private function typeBadge(string $type): string
    {
        $labels = ['phone' => 'Телефон', 'messenger' => 'Мессенджер', 'price' => 'Цена'];
        return '<span class="dbm-badge">' . esc_html($labels[$type] ?? ($type !== '' ? $type : 'значение')) . '</span>';
    }

    private function stateBadge(string $state): string
    {
        $labels = [
            'ok' => '● активно',
            'pinned' => '● закреплено',
            'on_reserve' => '● резерв',
            'exhausted' => '● исчерпано',
            'hidden' => '● скрыто',
            'pending' => '● ожидает',
        ];
        $safe = preg_replace('/[^a-z0-9_-]/i', '', strtolower($state)) ?: 'pending';

        return '<span class="dbm-state dbm-state--' . esc_attr($safe) . '">' . esc_html($labels[$state] ?? ('● ' . $state)) . '</span>';
    }

    private function geoChips(mixed $geo): string
    {
        $geo = is_array($geo) ? $geo : [$geo];
        $geo = array_values(array_filter(array_map(fn ($item): string => strtoupper(trim((string) $item)), $geo)));
        if ($geo === []) {
            $geo = ['WORLD'];
        }

        return implode('', array_map(function (string $item): string {
            $class = str_starts_with($item, '!') ? 'dbm-chip dbm-chip--deny' : 'dbm-chip';
            return '<span class="' . $class . '">' . esc_html($item) . '</span>';
        }, $geo));
    }

    private function geoPricesList(array $prices): string
    {
        $html = '<div class="dbm-price-list">';
        foreach ($prices as $p) {
            $geos = $p['geo'] ?? ['WORLD'];
            $lbl = trim((string) ($p['label'] ?? ''));
            $html .= '<div class="dbm-price-row">';
            $html .= $this->geoChips($geos);
            if ($lbl !== '') {
                $html .= ' <span class="dbm-price-lbl" style="font-size:11px; color:var(--dbm-muted); margin-left:6px; font-weight:normal;">[метка: ' . esc_html($lbl) . ']</span>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function copyableSnippet(string $label, string $code): string
    {
        $escapedCode = esc_attr($code);
        $escapedHtml = esc_html($code);
        $escapedLabel = esc_html($label);

        return '<div class="dbm-snippet-item">'
            . '<span class="dbm-label">' . $escapedLabel . '</span>'
            . '<div class="dbm-copy-wrapper">'
            . '<code>' . $escapedHtml . '</code>'
            . '<button type="button" class="dbm-copy-btn" data-clipboard="' . $escapedCode . '" title="Копировать">'
            . '<svg class="dbm-copy-icon" viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>'
            . '<svg class="dbm-check-icon" viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><polyline points="20 6 9 17 4 12"></polyline></svg>'
            . '</button>'
            . '</div>'
            . '</div>';
    }

    private function groupCacheValues(array $values): array
    {
        $grouped = [];
        $priceSlots = [];

        foreach ($values as $val) {
            $type = (string) ($val['type'] ?? '');
            $key = (string) ($val['key'] ?? '');

            if ($type === 'price') {
                if (! isset($priceSlots[$key])) {
                    $priceSlots[$key] = [
                        'key' => $key,
                        'type' => 'price',
                        'state' => $val['state'] ?? 'ok',
                        'geo' => [],
                        'prices' => [],
                    ];
                }

                $candidateGeo = $val['geo'] ?? ['WORLD'];
                if (! is_array($candidateGeo)) {
                    $candidateGeo = [$candidateGeo];
                }
                foreach ($candidateGeo as $g) {
                    $gUpper = strtoupper(trim((string) $g));
                    if (! in_array($gUpper, $priceSlots[$key]['geo'], true)) {
                        $priceSlots[$key]['geo'][] = $gUpper;
                    }
                }

                $priceSlots[$key]['prices'][] = $val;
            } else {
                $grouped[] = $val;
            }
        }

        foreach ($priceSlots as $slot) {
            $grouped[] = $slot;
        }

        return $grouped;
    }

    /** @param array<string,mixed> $value */
    private function valuePreview(array $value, array $allValues = []): string
    {
        $type = (string) ($value['type'] ?? '');

        if ($type === 'price' && ! empty($value['prices'])) {
            $html = '<div class="dbm-price-list">';
            foreach ($value['prices'] as $p) {
                $val = trim((string) ($p['value'] ?? ''));
                $html .= '<div class="dbm-price-row"><strong>' . esc_html($val) . '</strong></div>';
            }
            $html .= '</div>';
            return $html;
        }

        $display = trim((string) ($value['display_value'] ?? $value['value'] ?? $value['name'] ?? $value['url'] ?? ''));
        $raw = trim((string) ($value['value'] ?? ''));
        $label = trim((string) ($value['label'] ?? $value['network'] ?? ''));

        if ($display === '') {
            $display = '—';
        }

        $html = '<span class="dbm-value"><strong>' . esc_html($display);

        if ($type === 'phone') {
            $phoneKey = (string) ($value['key'] ?? '');
            foreach ($allValues as $val) {
                if (($val['type'] ?? '') === 'messenger') {
                    $slots = $val['linked_slot'] ?? null;
                    $match = false;
                    if (is_array($slots)) {
                        $match = in_array($phoneKey, $slots, true);
                    } elseif (is_string($slots) && $slots === $phoneKey) {
                        $match = true;
                    }
                    if ($match) {
                        $netCode = strtolower((string) ($val['network'] ?? 'unknown'));
                        $netName = esc_attr((string) ($val['name'] ?? $val['network'] ?? 'messenger'));
                        $html .= '<span class="dbm-net-badge dbm-net-badge--' . esc_attr($netCode) . '" title="Прикреплен мессенджер: ' . $netName . '">' . esc_html(substr($netCode, 0, 2)) . '</span>';
                    }
                }
            }
        }

        $html .= '</strong>';
        if ($label !== '') {
            $html .= '<small>' . esc_html($label) . '</small>';
        }
        if ($raw !== '' && $raw !== $display) {
            $html .= '<small>raw: ' . esc_html($raw) . '</small>';
        }
        $html .= '</span>';

        return $html;
    }

    private function orderedValues(array $cache): array
    {
        $values = $cache['values'] ?? [];
        if (! is_array($values)) {
            return [];
        }

        $grouped = $this->groupCacheValues($values);

        $typeOrder = [
            'phone' => 0,
            'messenger' => 1,
            'price' => 2,
        ];

        $decorated = [];
        foreach ($grouped as $index => $value) {
            $decorated[] = ['index' => $index, 'value' => $value];
        }

        usort($decorated, function (array $a, array $b) use ($typeOrder): int {
            $aValue = $a['value'];
            $bValue = $b['value'];
            $aType = (string) ($aValue['type'] ?? '');
            $bType = (string) ($bValue['type'] ?? '');

            return [
                $typeOrder[$aType] ?? 99,
                strtolower((string) ($aValue['key'] ?? '')),
                $a['index'],
            ] <=> [
                $typeOrder[$bType] ?? 99,
                strtolower((string) ($bValue['key'] ?? '')),
                $b['index'],
            ];
        });

        return array_map(fn (array $item): array => $item['value'], $decorated);
    }
}
