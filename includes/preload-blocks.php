<?php
/**
 * Container Block Designer - Preload Blocks for Faster Loading
 * Version: 1.0.0
 * 
 * Diese Datei lädt die Blöcke vorab und stellt sie dem Editor zur Verfügung
 * Speichern als: /wp-content/plugins/container-block-designer/includes/preload-blocks.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Preload blocks data for Gutenberg editor
 */
add_action('enqueue_block_editor_assets', 'cbd_preload_blocks_data', 5);
function cbd_preload_blocks_data() {
    global $wpdb;
    
    // Cache-Key für Transient
    $cache_key = 'cbd_active_blocks_' . get_current_blog_id();
    $blocks = get_transient($cache_key);
    
    if (false === $blocks) {
        // Lade aktive Blöcke aus der Datenbank
        $blocks = $wpdb->get_results(
            "SELECT id, name, slug, config, features 
             FROM " . CBD_TABLE_BLOCKS . " 
             WHERE status = 'active' 
             ORDER BY name ASC",
            ARRAY_A
        );
        
        // Parse JSON für config und features
        if ($blocks) {
            foreach ($blocks as &$block) {
                $block['config'] = json_decode($block['config'], true);
                $block['features'] = json_decode($block['features'], true);
            }
        } else {
            $blocks = array();
        }
        
        // Cache für 1 Stunde
        set_transient($cache_key, $blocks, HOUR_IN_SECONDS);
    }
    
    // Stelle sicher, dass das Script registriert ist
    if (!wp_script_is('cbd-container-block', 'registered')) {
        wp_register_script(
            'cbd-container-block',
            CBD_PLUGIN_URL . 'assets/js/container-block.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            CBD_VERSION,
            true
        );
    }
    
    // Lokalisiere Daten VOR dem Enqueue
    wp_localize_script('cbd-container-block', 'cbdData', array(
        'blocks' => $blocks,
        'apiUrl' => home_url('/wp-json/cbd/v1/'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_rest'),
        'pluginUrl' => CBD_PLUGIN_URL,
        'isDebug' => WP_DEBUG,
        'strings' => array(
            'loading' => __('Lade Container-Blöcke...', 'container-block-designer'),
            'noBlocks' => __('Keine Container-Blöcke verfügbar', 'container-block-designer'),
            'error' => __('Fehler beim Laden der Blöcke', 'container-block-designer'),
            'selectBlock' => __('-- Container-Block wählen --', 'container-block-designer'),
            'createFirst' => __('Bitte erstellen Sie zuerst einen Container-Block', 'container-block-designer')
        )
    ));
    
    // Enqueue script falls noch nicht geschehen
    if (!wp_script_is('cbd-container-block', 'enqueued')) {
        wp_enqueue_script('cbd-container-block');
    }
}

/**
 * Clear blocks cache when blocks are updated
 */
add_action('cbd_block_saved', 'cbd_clear_blocks_cache');
add_action('cbd_block_deleted', 'cbd_clear_blocks_cache');
add_action('cbd_block_status_changed', 'cbd_clear_blocks_cache');
function cbd_clear_blocks_cache() {
    $cache_key = 'cbd_active_blocks_' . get_current_blog_id();
    delete_transient($cache_key);
    
    // Lösche auch andere mögliche Caches
    wp_cache_delete('cbd_blocks', 'cbd');
    
    // Trigger cache clear für externe Cache-Plugins
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Preload REST API routes for faster access
 */
add_filter('block_editor_rest_api_preload_paths', 'cbd_preload_rest_routes');
function cbd_preload_rest_routes($preload_paths) {
    // Füge unsere REST-Route zum Preload hinzu
    $preload_paths[] = '/cbd/v1/blocks';
    
    return $preload_paths;
}

/**
 * Add inline script for immediate availability
 */
add_action('admin_footer-post.php', 'cbd_add_inline_blocks_cache');
add_action('admin_footer-post-new.php', 'cbd_add_inline_blocks_cache');
function cbd_add_inline_blocks_cache() {
    global $wpdb;
    
    // Nur im Block Editor
    if (!function_exists('get_current_screen')) {
        return;
    }
    
    $screen = get_current_screen();
    if (!$screen || !$screen->is_block_editor()) {
        return;
    }
    
    // Lade Blöcke direkt
    $blocks = $wpdb->get_results(
        "SELECT id, name, slug 
         FROM " . CBD_TABLE_BLOCKS . " 
         WHERE status = 'active' 
         ORDER BY name ASC",
        ARRAY_A
    );
    
    if (empty($blocks)) {
        $blocks = array();
    }
    
    ?>
    <script type="text/javascript">
    // Sofortiger Cache der Container-Blöcke
    window.cbdBlocksCache = <?php echo json_encode($blocks); ?>;
    console.log('CBD: Blocks Cache vorgeladen:', window.cbdBlocksCache);
    
    // Stelle sicher, dass cbdData existiert
    window.cbdData = window.cbdData || {};
    window.cbdData.blocks = window.cbdBlocksCache;
    
    // Performance-Optimierung: Registriere Block sofort wenn wp.blocks verfügbar
    if (window.wp && window.wp.domReady) {
        window.wp.domReady(function() {
            console.log('CBD: Block-Editor bereit, Blöcke verfügbar:', window.cbdBlocksCache.length);
        });
    }
    </script>
    <?php
}

/**
 * Optimize database queries
 */
add_action('init', 'cbd_optimize_blocks_table', 99);
function cbd_optimize_blocks_table() {
    // Nur einmal pro Tag ausführen
    if (get_transient('cbd_table_optimized')) {
        return;
    }
    
    global $wpdb;
    $table = CBD_TABLE_BLOCKS;
    
    // Prüfe ob Index existiert
    $index_exists = $wpdb->get_var(
        "SHOW INDEX FROM $table WHERE Key_name = 'idx_status_slug'"
    );
    
    if (!$index_exists) {
        // Füge Index für bessere Performance hinzu
        $wpdb->query("ALTER TABLE $table ADD INDEX idx_status_slug (status, slug)");
    }
    
    // Setze Transient für 24 Stunden
    set_transient('cbd_table_optimized', true, DAY_IN_SECONDS);
}

/**
 * Add HTTP/2 Server Push for critical assets
 */
add_action('send_headers', 'cbd_add_resource_hints');
function cbd_add_resource_hints() {
    if (!is_admin()) {
        return;
    }
    
    // Push kritische Ressourcen
    header('Link: <' . CBD_PLUGIN_URL . 'assets/js/container-block.js>; rel=preload; as=script', false);
    header('Link: <' . CBD_PLUGIN_URL . 'assets/css/container-block.css>; rel=preload; as=style', false);
}

/**
 * Enable AJAX endpoint caching
 */
add_filter('cbd_ajax_cache_headers', 'cbd_set_ajax_cache_headers');
function cbd_set_ajax_cache_headers($headers) {
    $headers['Cache-Control'] = 'public, max-age=300'; // 5 Minuten Cache
    $headers['Expires'] = gmdate('D, d M Y H:i:s', time() + 300) . ' GMT';
    return $headers;
}