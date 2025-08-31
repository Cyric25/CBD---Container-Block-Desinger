<?php
/**
 * Container Block Designer - Quick Debug
 * Version: 1.0.0
 * 
 * Einfaches Debug-System das sofort funktioniert
 * Speichern als: /wp-content/plugins/container-block-designer/cbd-quick-debug.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug-Log Funktion - Sofort verfügbar
if (!function_exists('cbd_debug_log')) {
    function cbd_debug_log($message, $data = null) {
        $log_file = plugin_dir_path(__FILE__) . 'cbd-debug.log';
        
        // Erstelle Log-Eintrag
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] {$message}";
        
        if ($data !== null) {
            $entry .= " | Data: " . print_r($data, true);
        }
        
        $entry .= "\n";
        
        // Schreibe in Datei
        error_log($entry, 3, $log_file);
        
        // Auch in WordPress Debug Log schreiben
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[CBD] ' . $message);
        }
    }
}

// Sofort loggen dass Debug aktiv ist
cbd_debug_log('=== CBD Quick Debug Loaded ===');
cbd_debug_log('PHP Version', PHP_VERSION);
cbd_debug_log('WordPress Version', get_bloginfo('version'));
cbd_debug_log('Plugin Directory', plugin_dir_path(__FILE__));

// Prüfe kritische Dateien
$critical_files = array(
    'container-block-designer.php' => 'Main Plugin File',
    'includes/ajax-handlers.php' => 'AJAX Handlers',
    'includes/Admin/class-admin-features.php' => 'Admin Features Class',
    'assets/js/container-block.js' => 'Block JS',
    'assets/js/admin.js' => 'Admin JS'
);

foreach ($critical_files as $file => $description) {
    $full_path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($full_path)) {
        cbd_debug_log("✅ Found: {$description}", $file);
    } else {
        cbd_debug_log("❌ MISSING: {$description}", $file);
    }
}

// Hook in WordPress actions zum Debuggen
add_action('init', function() {
    cbd_debug_log('WordPress Init Hook triggered');
});

add_action('admin_init', function() {
    cbd_debug_log('Admin Init Hook triggered');
    
    // Prüfe Datenbank-Tabelle
    global $wpdb;
    $table_name = $wpdb->prefix . 'cbd_blocks';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($table_exists) {
        cbd_debug_log('✅ Database table exists', $table_name);
        
        // Zähle Blocks
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        cbd_debug_log('Block count in database', $count);
    } else {
        cbd_debug_log('❌ Database table MISSING', $table_name);
    }
});

// Debug Admin Pages
add_action('admin_menu', function() {
    cbd_debug_log('Admin Menu Hook triggered');
});

// Debug Block Registration
add_action('init', function() {
    if (function_exists('register_block_type')) {
        cbd_debug_log('Block registration available');
    } else {
        cbd_debug_log('❌ Block registration NOT available');
    }
}, 20);

// Debug AJAX Calls
add_action('wp_ajax_cbd_save_block', function() {
    cbd_debug_log('AJAX: cbd_save_block called', $_POST);
}, 1);

add_action('wp_ajax_cbd_delete_block', function() {
    cbd_debug_log('AJAX: cbd_delete_block called', $_POST);
}, 1);

// Fehler abfangen
if (defined('WP_DEBUG') && WP_DEBUG) {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (strpos($errfile, 'container-block-designer') !== false) {
            cbd_debug_log('PHP Error', array(
                'type' => $errno,
                'message' => $errstr,
                'file' => basename($errfile),
                'line' => $errline
            ));
        }
        return false;
    });
}

// Admin Notice wenn Debug läuft
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'container-block-designer') !== false) {
        $log_file = plugin_dir_path(__FILE__) . 'cbd-debug.log';
        if (file_exists($log_file)) {
            $size = size_format(filesize($log_file));
            ?>
            <div class="notice notice-info">
                <p>
                    <strong>CBD Debug Active:</strong> 
                    Log file: <code><?php echo esc_html($log_file); ?></code> 
                    (Size: <?php echo $size; ?>)
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=container-block-designer&cbd_action=view_log'), 'cbd_view_log'); ?>" class="button button-small" style="margin-left: 10px;">View Log</a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=container-block-designer&cbd_action=clear_log'), 'cbd_clear_log'); ?>" class="button button-small">Clear Log</a>
                </p>
            </div>
            <?php
        }
    }
});

// Handle Log Actions
add_action('admin_init', function() {
    if (isset($_GET['cbd_action'])) {
        $action = $_GET['cbd_action'];
        
        if ($action === 'view_log' && wp_verify_nonce($_GET['_wpnonce'], 'cbd_view_log')) {
            $log_file = plugin_dir_path(__FILE__) . 'cbd-debug.log';
            if (file_exists($log_file)) {
                header('Content-Type: text/plain');
                readfile($log_file);
                exit;
            }
        }
        
        if ($action === 'clear_log' && wp_verify_nonce($_GET['_wpnonce'], 'cbd_clear_log')) {
            $log_file = plugin_dir_path(__FILE__) . 'cbd-debug.log';
            if (file_exists($log_file)) {
                file_put_contents($log_file, "=== Log Cleared ===\n" . date('Y-m-d H:i:s') . "\n\n");
            }
            wp_redirect(admin_url('admin.php?page=container-block-designer'));
            exit;
        }
    }
});

// Log Plugin Activation Status
cbd_debug_log('Plugin Status Check', array(
    'CBD_VERSION' => defined('CBD_VERSION') ? CBD_VERSION : 'NOT DEFINED',
    'CBD_PLUGIN_DIR' => defined('CBD_PLUGIN_DIR') ? CBD_PLUGIN_DIR : 'NOT DEFINED',
    'CBD_PLUGIN_URL' => defined('CBD_PLUGIN_URL') ? CBD_PLUGIN_URL : 'NOT DEFINED',
    'CBD_TABLE_BLOCKS' => defined('CBD_TABLE_BLOCKS') ? CBD_TABLE_BLOCKS : 'NOT DEFINED'
));

// Ende der Datei
cbd_debug_log('=== CBD Quick Debug Setup Complete ===');