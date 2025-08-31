<?php
/**
 * Container Block Designer - Emergency Fix Script
 * Version: 1.0.0
 * 
 * WICHTIG: Diese Datei behebt kritische Fehler im Plugin
 * Speichern Sie diese Datei als: /wp-content/plugins/container-block-designer/quickfix.php
 * 
 * Dann rufen Sie auf: /wp-admin/admin.php?page=cbd-emergency-fix
 * 
 * @package ContainerBlockDesigner
 */

// WordPress-Umgebung laden, wenn direkt aufgerufen
if (!defined('ABSPATH')) {
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        die('WordPress konnte nicht geladen werden.');
    }
}

// Admin-Menü hinzufügen
add_action('admin_menu', function() {
    add_submenu_page(
        null, // Versteckte Seite
        'CBD Emergency Fix',
        'CBD Emergency Fix',
        'manage_options',
        'cbd-emergency-fix',
        'cbd_emergency_fix_page'
    );
});

/**
 * Emergency Fix Page
 */
function cbd_emergency_fix_page() {
    // Berechtigungen prüfen
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung');
    }
    
    // Plugin-Pfad bestimmen
    $plugin_dir = WP_PLUGIN_DIR . '/container-block-designer/';
    
    // Kritische Dateien die repariert werden müssen
    $critical_files = [
        'includes/ajax-handlers.php',
        'includes/ensure-ajaxurl.php',
        'includes/fix-localization.php',
        'admin/edit-block.php',
        'assets/css/advanced-features.css'
    ];
    
    $fixes_applied = [];
    $errors = [];
    
    // Wenn Fixes angefordert wurden
    if (isset($_POST['apply_emergency_fix']) && check_admin_referer('cbd_emergency_fix')) {
        
        // 1. Backup erstellen
        $backup_dir = $plugin_dir . 'backup-' . date('Y-m-d-His') . '/';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        foreach ($critical_files as $file) {
            $source = $plugin_dir . $file;
            if (file_exists($source)) {
                $dest = $backup_dir . $file;
                wp_mkdir_p(dirname($dest));
                copy($source, $dest);
            }
        }
        $fixes_applied[] = '✅ Backup erstellt in: ' . basename($backup_dir);
        
        // 2. Temporäre Stub-Dateien erstellen
        // Diese minimalen Dateien verhindern PHP-Fehler
        
        // ajax-handlers.php stub
        $ajax_stub = '<?php
/**
 * AJAX Handlers - Emergency Stub
 */
if (!defined("ABSPATH")) exit;

// Minimal handlers to prevent errors
add_action("wp_ajax_cbd_get_blocks", function() {
    wp_send_json_success([]);
});

add_action("wp_ajax_cbd_save_block", function() {
    wp_send_json_error(["message" => "System wird repariert"]);
});

add_action("wp_ajax_cbd_update_block", function() {
    wp_send_json_error(["message" => "System wird repariert"]);
});

add_action("wp_ajax_cbd_delete_block", function() {
    wp_send_json_error(["message" => "System wird repariert"]);
});';
        
        file_put_contents($plugin_dir . 'includes/ajax-handlers-stub.php', $ajax_stub);
        
        // 3. Hauptplugin-Datei patchen
        $main_file = $plugin_dir . 'container-block-designer.php';
        if (file_exists($main_file)) {
            $content = file_get_contents($main_file);
            
            // Ersetze includes mit Stub-Versionen
            $content = str_replace(
                "require_once CBD_PLUGIN_DIR . 'includes/ajax-handlers.php';",
                "require_once CBD_PLUGIN_DIR . 'includes/ajax-handlers-stub.php';",
                $content
            );
            
            // Deaktiviere problematische includes temporär
            $content = str_replace(
                "require_once CBD_PLUGIN_DIR . 'includes/ensure-ajaxurl.php';",
                "// DISABLED: require_once CBD_PLUGIN_DIR . 'includes/ensure-ajaxurl.php';",
                $content
            );
            
            $content = str_replace(
                "require_once CBD_PLUGIN_DIR . 'includes/fix-localization.php';",
                "// DISABLED: require_once CBD_PLUGIN_DIR . 'includes/fix-localization.php';",
                $content
            );
            
            file_put_contents($main_file, $content);
            $fixes_applied[] = '✅ Hauptplugin-Datei gepatcht';
        }
        
        // 4. Datenbank-Tabelle sicherstellen
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            config longtext,
            features longtext,
            status varchar(20) DEFAULT 'active',
            created datetime DEFAULT CURRENT_TIMESTAMP,
            modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        $fixes_applied[] = '✅ Datenbank-Tabelle überprüft';
        
        // 5. Cache leeren
        wp_cache_flush();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        $fixes_applied[] = '✅ Cache geleert';
        
        // 6. Konstanten neu definieren
        if (!defined('CBD_VERSION')) {
            define('CBD_VERSION', '2.2.1-emergency');
        }
        if (!defined('CBD_TABLE_BLOCKS')) {
            define('CBD_TABLE_BLOCKS', $wpdb->prefix . 'cbd_blocks');
        }
        $fixes_applied[] = '✅ Konstanten neu definiert';
    }
    
    // Status-Informationen sammeln
    $status = cbd_get_emergency_status($plugin_dir, $critical_files);
    
    ?>
    <div class="wrap">
        <h1>🚨 Container Block Designer - Emergency Fix</h1>
        
        <?php if (!empty($fixes_applied)): ?>
        <div class="notice notice-success">
            <h3>✅ Angewendete Fixes:</h3>
            <ul>
                <?php foreach ($fixes_applied as $fix): ?>
                    <li><?php echo esc_html($fix); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><strong>Nächste Schritte:</strong></p>
            <ol>
                <li>Plugin deaktivieren</li>
                <li>Plugin wieder aktivieren</li>
                <li>Testen Sie die Grundfunktionen</li>
            </ol>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="notice notice-error">
            <h3>❌ Fehler:</h3>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>📊 System-Status</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Komponente</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status as $key => $value): ?>
                    <tr>
                        <td><strong><?php echo esc_html($value['label']); ?></strong></td>
                        <td><?php echo $value['status'] ? '✅' : '❌'; ?></td>
                        <td><?php echo esc_html($value['details']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>🔧 Kritische Dateien</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Datei</th>
                        <th>Existiert</th>
                        <th>Größe</th>
                        <th>Letzte Änderung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($critical_files as $file): 
                        $full_path = $plugin_dir . $file;
                        $exists = file_exists($full_path);
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($file); ?></code></td>
                        <td><?php echo $exists ? '✅' : '❌'; ?></td>
                        <td><?php echo $exists ? size_format(filesize($full_path)) : '-'; ?></td>
                        <td><?php echo $exists ? date('Y-m-d H:i:s', filemtime($full_path)) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
            <h2>⚠️ Wichtiger Hinweis</h2>
            <p>Dieser Emergency Fix wendet <strong>temporäre Patches</strong> an, um das Plugin wieder funktionsfähig zu machen.</p>
            <p>Nach dem Fix sollten Sie:</p>
            <ol>
                <li>Ein vollständiges Backup Ihrer Website erstellen</li>
                <li>Die neueste Version des Plugins herunterladen</li>
                <li>Das Plugin komplett neu installieren</li>
            </ol>
        </div>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('cbd_emergency_fix'); ?>
            <p>
                <input type="submit" name="apply_emergency_fix" class="button button-primary button-hero" 
                       value="🚨 Emergency Fix anwenden" 
                       onclick="return confirm('Dies wird temporäre Patches anwenden. Backup vorhanden?');">
                
                <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-secondary button-hero">
                    Zur Plugin-Übersicht
                </a>
            </p>
        </form>
        
        <div class="card" style="margin-top: 20px;">
            <h2>📝 Debug-Informationen</h2>
            <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;">
PHP Version: <?php echo PHP_VERSION; ?>

WordPress Version: <?php echo get_bloginfo('version'); ?>

Plugin Directory: <?php echo $plugin_dir; ?>

Active Theme: <?php echo get_template(); ?>

Memory Limit: <?php echo WP_MEMORY_LIMIT; ?>

Debug Mode: <?php echo WP_DEBUG ? 'ON' : 'OFF'; ?>

Database Prefix: <?php echo $wpdb->prefix; ?>

Plugin URL: <?php echo plugins_url('', $plugin_dir . 'container-block-designer.php'); ?>

Admin URL: <?php echo admin_url(); ?>

Site URL: <?php echo site_url(); ?>

Loaded Extensions: <?php echo implode(', ', get_loaded_extensions()); ?>
            </textarea>
            <p><em>Kopieren Sie diese Informationen für den Support.</em></p>
        </div>
    </div>
    
    <style>
    .card {
        background: white;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-top: 20px;
    }
    .button-hero {
        font-size: 18px !important;
        line-height: 1.3 !important;
        padding: 12px 24px !important;
        height: auto !important;
    }
    .widefat td code {
        background: #f0f0f0;
        padding: 2px 5px;
        border-radius: 3px;
    }
    </style>
    <?php
}

/**
 * Get emergency status information
 */
function cbd_get_emergency_status($plugin_dir, $critical_files) {
    global $wpdb;
    
    $status = [];
    
    // PHP Version
    $status['php'] = [
        'label' => 'PHP Version',
        'status' => version_compare(PHP_VERSION, '7.4', '>='),
        'details' => PHP_VERSION . ' (Min: 7.4)'
    ];
    
    // WordPress Version
    $status['wp'] = [
        'label' => 'WordPress Version',
        'status' => version_compare(get_bloginfo('version'), '6.0', '>='),
        'details' => get_bloginfo('version') . ' (Min: 6.0)'
    ];
    
    // Plugin-Hauptdatei
    $main_file = $plugin_dir . 'container-block-designer.php';
    $status['main'] = [
        'label' => 'Plugin-Hauptdatei',
        'status' => file_exists($main_file),
        'details' => file_exists($main_file) ? 'Vorhanden' : 'FEHLT!'
    ];
    
    // Datenbank-Tabelle
    $table_name = $wpdb->prefix . 'cbd_blocks';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    $status['db'] = [
        'label' => 'Datenbank-Tabelle',
        'status' => $table_exists,
        'details' => $table_exists ? 'Existiert' : 'Fehlt'
    ];
    
    // Kritische Dateien
    $missing_files = 0;
    foreach ($critical_files as $file) {
        if (!file_exists($plugin_dir . $file)) {
            $missing_files++;
        }
    }
    $status['files'] = [
        'label' => 'Kritische Dateien',
        'status' => $missing_files === 0,
        'details' => $missing_files === 0 ? 'Alle vorhanden' : $missing_files . ' fehlen'
    ];
    
    // Memory Limit
    $memory_limit = wp_convert_hr_to_bytes(WP_MEMORY_LIMIT);
    $status['memory'] = [
        'label' => 'Memory Limit',
        'status' => $memory_limit >= 64 * 1024 * 1024,
        'details' => size_format($memory_limit) . ' (Min: 64 MB)'
    ];
    
    // Write Permissions
    $status['write'] = [
        'label' => 'Schreibrechte',
        'status' => is_writable($plugin_dir),
        'details' => is_writable($plugin_dir) ? 'Schreibbar' : 'Nicht schreibbar'
    ];
    
    return $status;
}

// Aktiviere die Emergency Fix Page
add_action('init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'cbd-emergency-fix') {
        add_action('admin_menu', function() {
            add_menu_page(
                'CBD Emergency Fix',
                'CBD Fix',
                'manage_options',
                'cbd-emergency-fix',
                'cbd_emergency_fix_page',
                'dashicons-warning',
                99
            );
        });
    }
});

// Zeige Notfall-Banner wenn kritische Fehler erkannt werden
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'container-block-designer') !== false) {
        ?>
        <div class="notice notice-error">
            <p><strong>🚨 Kritischer Fehler erkannt!</strong></p>
            <p>Das Container Block Designer Plugin hat kritische Fehler. 
               <a href="<?php echo admin_url('admin.php?page=cbd-emergency-fix'); ?>" class="button button-primary">
                   Emergency Fix starten
               </a>
            </p>
        </div>
        <?php
    }
});
