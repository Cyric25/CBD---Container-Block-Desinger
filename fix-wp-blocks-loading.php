<?php
/**
 * Fix für wp.blocks nicht verfügbar
 * Fügen Sie diese Datei ins Plugin-Hauptverzeichnis ein
 * und inkludieren Sie sie in container-block-designer.php
 */

// Stelle sicher, dass Block Editor Assets geladen werden
add_action('enqueue_block_editor_assets', 'cbd_fix_block_editor_assets', 5);

function cbd_fix_block_editor_assets() {
    // Stelle sicher, dass alle WordPress Block Dependencies geladen sind
    $block_editor_scripts = array(
        'wp-blocks',
        'wp-element', 
        'wp-block-editor',
        'wp-components',
        'wp-i18n',
        'wp-data',
        'wp-dom-ready',
        'wp-edit-post' // Wichtig für Editor-Kontext
    );
    
    foreach ($block_editor_scripts as $handle) {
        wp_enqueue_script($handle);
    }
    
    // Debug Info
    wp_add_inline_script('wp-blocks', '
        console.log("=== CBD Block Loading Fix ===");
        console.log("wp object:", typeof wp !== "undefined" ? "✅" : "❌");
        console.log("wp.blocks:", typeof wp !== "undefined" && wp.blocks ? "✅" : "❌");
        console.log("wp.blockEditor:", typeof wp !== "undefined" && wp.blockEditor ? "✅" : "❌");
        
        // Warte auf vollständiges Laden
        if (typeof wp !== "undefined" && wp.domReady) {
            wp.domReady(function() {
                console.log("DOM Ready - wp.blocks verfügbar:", typeof wp.blocks !== "undefined" ? "✅" : "❌");
                
                // Lade Container Block Script erneut
                if (typeof wp.blocks !== "undefined") {
                    var script = document.createElement("script");
                    script.src = "' . CBD_PLUGIN_URL . 'assets/js/container-block.js?ver=' . time() . '";
                    script.onload = function() {
                        console.log("Container Block Script neu geladen");
                    };
                    document.head.appendChild(script);
                }
            });
        }
    ');
}

// Alternative: Verwende admin_enqueue_scripts für Gutenberg
add_action('admin_enqueue_scripts', 'cbd_ensure_gutenberg_scripts', 999);

function cbd_ensure_gutenberg_scripts($hook) {
    global $current_screen;
    
    // Nur im Block Editor
    if (!$current_screen || !$current_screen->is_block_editor()) {
        return;
    }
    
    // Lade Container Block mit allen Dependencies
    wp_enqueue_script(
        'cbd-container-block-fixed',
        CBD_PLUGIN_URL . 'assets/js/container-block.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-block-editor', 
            'wp-components',
            'wp-i18n',
            'wp-data',
            'wp-compose',
            'wp-dom-ready',
            'wp-edit-post',
            'jquery'
        ),
        CBD_VERSION . '.' . time(), // Cache-Busting
        true
    );
    
    // Stelle sicher, dass die Lokalisierung vorhanden ist
    wp_localize_script('cbd-container-block-fixed', 'cbdData', array(
        'apiUrl' => home_url('/wp-json/cbd/v1/'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_rest'),
        'ajaxNonce' => wp_create_nonce('cbd-admin'),
        'blocks' => cbd_get_active_blocks_for_fix(),
        'pluginUrl' => CBD_PLUGIN_URL,
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ));
}

// Helper Funktion
function cbd_get_active_blocks_for_fix() {
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
    
    return $blocks ?: array();
}

// Zusätzlicher Hook für Widget-Editor und Site-Editor
add_action('enqueue_block_assets', 'cbd_enqueue_everywhere');

function cbd_enqueue_everywhere() {
    if (!is_admin()) {
        return;
    }
    
    global $current_screen;
    if ($current_screen && (
        $current_screen->id === 'widgets' || 
        $current_screen->id === 'site-editor' ||
        $current_screen->id === 'appearance_page_gutenberg-edit-site'
    )) {
        cbd_ensure_gutenberg_scripts('');
    }
}

// Debug: Zeige, welche Scripts geladen sind
add_action('admin_footer', 'cbd_debug_loaded_scripts');

function cbd_debug_loaded_scripts() {
    global $current_screen;
    
    if (!$current_screen || !$current_screen->is_block_editor()) {
        return;
    }
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('=== CBD Debug: Geladene Scripts ===');
        
        // Prüfe wichtige Handles
        var handles = [
            'wp-blocks',
            'wp-element', 
            'wp-block-editor',
            'wp-components',
            'wp-data',
            'wp-edit-post',
            'cbd-container-block',
            'cbd-container-block-fixed'
        ];
        
        handles.forEach(function(handle) {
            var loaded = $('script[id="' + handle + '-js"]').length > 0;
            console.log(handle + ':', loaded ? '✅ Geladen' : '❌ Nicht geladen');
        });
        
        // Versuche manuelles Laden falls wp.blocks fehlt
        setTimeout(function() {
            if (typeof wp === 'undefined' || !wp.blocks) {
                console.error('❌ wp.blocks immer noch nicht verfügbar nach 2 Sekunden');
                console.log('Versuche manuelles Nachladen...');
                
                // Force load wp-blocks
                var wpBlocksScript = document.getElementById('wp-blocks-js');
                if (!wpBlocksScript) {
                    console.log('Lade wp-blocks manuell...');
                    jQuery.getScript('/wp-includes/js/dist/blocks.min.js')
                        .done(function() {
                            console.log('wp-blocks manuell geladen, lade Container Block...');
                            jQuery.getScript('<?php echo CBD_PLUGIN_URL; ?>assets/js/container-block.js?v=' + Date.now());
                        });
                }
            }
        }, 2000);
    });
    </script>
    <?php
}
