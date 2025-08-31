<?php
/**
 * CBD Blocks - Quick Fix für wp.blocks nicht verfügbar
 * 
 * Diese Datei in das Plugin-Hauptverzeichnis legen und 
 * in container-block-designer.php einbinden mit:
 * require_once CBD_PLUGIN_DIR . 'quickfix-enqueue.php';
 */

// Fix: Korrekte Script-Registrierung für Block Editor
add_action('enqueue_block_editor_assets', function() {
    // Entferne falsch registrierte Scripts
    wp_deregister_script('cbd-container-block');
    
    // Registriere mit korrekten Dependencies
    wp_register_script(
        'cbd-container-block',
        CBD_PLUGIN_URL . 'assets/js/container-block.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components', 
            'wp-i18n',
            'wp-api-fetch',
            'wp-data',
            'wp-compose',
            'wp-hooks',
            'wp-dom-ready',
            'jquery'  // Für AJAX Fallback
        ),
        CBD_VERSION . '.fix1',
        true
    );
    
    // Lokalisierung mit allen notwendigen Daten
    wp_localize_script('cbd-container-block', 'cbdData', array(
        'apiUrl' => home_url('/wp-json/cbd/v1/'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_rest'),
        'ajaxNonce' => wp_create_nonce('cbd-admin'),
        'blocks' => cbd_get_active_blocks_quickfix(),
        'pluginUrl' => CBD_PLUGIN_URL,
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
        'i18n' => array(
            'loading' => __('Lade Blocks...', 'container-block-designer'),
            'error' => __('Fehler beim Laden', 'container-block-designer'),
            'noBlocks' => __('Keine Blocks verfügbar', 'container-block-designer'),
            'selectBlock' => __('-- Wählen Sie einen Block --', 'container-block-designer')
        )
    ));
    
    // Script einreihen
    wp_enqueue_script('cbd-container-block');
    
    // Debug Info
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('CBD Quick Fix: Scripts neu registriert mit korrekten Dependencies');
    }
}, 999); // Hohe Priorität um andere Registrierungen zu überschreiben

// Helper function für aktive Blocks
function cbd_get_active_blocks_quickfix() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cbd_blocks';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        return array();
    }
    
    $blocks = $wpdb->get_results(
        "SELECT id, name, slug, description FROM {$table_name} WHERE status = 'active' ORDER BY name",
        ARRAY_A
    );
    
    return $blocks ? $blocks : array();
}

// Fix: Block-Kategorie sicherstellen
add_filter('block_categories_all', function($categories) {
    $has_design = false;
    
    foreach ($categories as $category) {
        if ($category['slug'] === 'design') {
            $has_design = true;
            break;
        }
    }
    
    if (!$has_design) {
        array_unshift($categories, array(
            'slug'  => 'design',
            'title' => __('Design Blocks', 'container-block-designer'),
            'icon'  => 'layout'
        ));
    }
    
    return $categories;
}, 5);

// Debug: Prüfe ob wp.blocks verfügbar ist
add_action('admin_footer', function() {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return;
    }
    
    $screen = get_current_screen();
    if ($screen && ($screen->is_block_editor || $screen->id === 'widgets')) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('=== CBD Debug Info ===');
            console.log('Screen:', '<?php echo $screen->id; ?>');
            console.log('wp object:', typeof wp !== 'undefined' ? '✅ Verfügbar' : '❌ Nicht verfügbar');
            console.log('wp.blocks:', typeof wp !== 'undefined' && wp.blocks ? '✅ Verfügbar' : '❌ Nicht verfügbar');
            console.log('wp.blockEditor:', typeof wp !== 'undefined' && wp.blockEditor ? '✅ Verfügbar' : '❌ Nicht verfügbar');
            console.log('wp.data:', typeof wp !== 'undefined' && wp.data ? '✅ Verfügbar' : '❌ Nicht verfügbar');
            console.log('cbdData:', typeof cbdData !== 'undefined' ? '✅ Verfügbar' : '❌ Nicht verfügbar');
            
            if (typeof wp !== 'undefined' && wp.blocks) {
                setTimeout(function() {
                    const cbdBlock = wp.blocks.getBlockType('container-block-designer/container');
                    console.log('CBD Container Block registriert:', cbdBlock ? '✅ Ja' : '❌ Nein');
                    
                    if (!cbdBlock) {
                        console.log('Verfügbare Blocks:', wp.blocks.getBlockTypes().map(b => b.name));
                    }
                }, 2000);
            }
        });
        </script>
        <?php
    }
});

// AJAX Fallback sicherstellen
add_action('wp_ajax_cbd_get_blocks', 'cbd_ajax_get_blocks_quickfix', 5);
add_action('wp_ajax_nopriv_cbd_get_blocks', 'cbd_ajax_get_blocks_quickfix', 5);

function cbd_ajax_get_blocks_quickfix() {
    // Prüfe ob bereits eine Handler existiert
    if (has_action('wp_ajax_cbd_get_blocks', 'cbd_ajax_get_blocks')) {
        return;
    }
    
    global $wpdb;
    
    $blocks = $wpdb->get_results(
        "SELECT id, name, slug, description FROM " . $wpdb->prefix . "cbd_blocks WHERE status = 'active' ORDER BY name",
        ARRAY_A
    );
    
    wp_send_json_success($blocks ?: []);
}

// Info-Nachricht im Admin
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'container-block-designer') !== false) {
        ?>
        <div class="notice notice-info">
            <p><strong>CBD Quick Fix aktiv:</strong> Script-Registrierung wurde korrigiert. 
            Prüfen Sie die Browser-Konsole für Debug-Informationen.</p>
        </div>
        <?php
    }
});
