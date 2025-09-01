<?php
/**
 * Container Block Designer - Cleanup Script
 * 
 * Dieses Script entfernt alte Block-Registrierungen und bereinigt die Datenbank
 * Datei speichern als: /wp-content/plugins/container-block-designer/includes/cleanup.php
 * 
 * Fügen Sie diese Zeile in container-block-designer.php ein (nach den includes):
 * require_once CBD_PLUGIN_DIR . 'includes/cleanup.php';
 */

// Sicherheit
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cleanup alte Scripts und Styles
 */
add_action('init', 'cbd_cleanup_old_assets', 1);
function cbd_cleanup_old_assets() {
    // Deregistriere alte Scripts falls vorhanden
    $old_scripts = array(
        'cbd-container-block',
        'cbd-container-block-fixed',
        'cbd-block-registration',
        'container-block-designer-block'
    );
    
    foreach ($old_scripts as $handle) {
        if (wp_script_is($handle, 'registered')) {
            wp_deregister_script($handle);
        }
    }
    
    // Deregistriere alte Styles
    $old_styles = array(
        'cbd-container-block-css',
        'cbd-container-block-editor-css',
        'container-block-designer-editor'
    );
    
    foreach ($old_styles as $handle) {
        if (wp_style_is($handle, 'registered')) {
            wp_deregister_style($handle);
        }
    }
}

/**
 * Verhindere das Laden alter Scripts
 */
add_action('wp_enqueue_scripts', 'cbd_prevent_old_scripts', 1);
add_action('admin_enqueue_scripts', 'cbd_prevent_old_scripts', 1);
add_action('enqueue_block_editor_assets', 'cbd_prevent_old_scripts', 1);

function cbd_prevent_old_scripts() {
    // Liste der zu blockierenden Script-URLs
    $blocked_scripts = array(
        'container-block.js',
        'container-block-deprecated.js',
        'fix-wp-blocks-loading.js'
    );
    
    global $wp_scripts;
    
    if (!$wp_scripts instanceof WP_Scripts) {
        return;
    }
    
    foreach ($wp_scripts->registered as $handle => $script) {
        foreach ($blocked_scripts as $blocked) {
            if (strpos($script->src, $blocked) !== false) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }
    }
}

/**
 * Cleanup Block Registry
 */
add_action('init', 'cbd_cleanup_block_registry', 5);
function cbd_cleanup_block_registry() {
    $registry = WP_Block_Type_Registry::get_instance();
    
    // Entferne alle alten Versionen des Container Blocks
    $old_block_names = array(
        'cbd/container-block',
        'container-block/container',
        'cbd/container'
    );
    
    foreach ($old_block_names as $block_name) {
        if ($registry->is_registered($block_name)) {
            unregister_block_type($block_name);
        }
    }
}

/**
 * Migriere alte Block-Inhalte in Posts
 */
add_action('admin_init', 'cbd_maybe_migrate_old_blocks');
function cbd_maybe_migrate_old_blocks() {
    // Nur einmal ausführen
    if (get_option('cbd_blocks_migrated_v251')) {
        return;
    }
    
    global $wpdb;
    
    // Suche nach Posts mit alten Block-Versionen
    $posts_with_old_blocks = $wpdb->get_results(
        "SELECT ID, post_content 
         FROM {$wpdb->posts} 
         WHERE post_content LIKE '%wp-block-container-block-designer-container%'
         AND post_status IN ('publish', 'draft', 'private')",
        ARRAY_A
    );
    
    if ($posts_with_old_blocks) {
        foreach ($posts_with_old_blocks as $post) {
            $content = $post['post_content'];
            $updated = false;
            
            // Entferne alte data-attributes
            $patterns = array(
                '/data-block-type="[^"]*"/',
                '/data-icon="[^"]*"/',
                '/data-icon-value="[^"]*"/',
                '/data-collapse="[^"]*"/',
                '/data-collapse-default="[^"]*"/',
                '/data-numbering="[^"]*"/',
                '/data-numbering-format="[^"]*"/',
                '/data-copy-text="[^"]*"/',
                '/data-screenshot="[^"]*"/'
            );
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, '', $content);
                    $updated = true;
                }
            }
            
            // Bereinige doppelte Leerzeichen
            if ($updated) {
                $content = preg_replace('/\s+/', ' ', $content);
                $content = str_replace('  >', '>', $content);
                
                // Update Post
                $wpdb->update(
                    $wpdb->posts,
                    array('post_content' => $content),
                    array('ID' => $post['ID'])
                );
            }
        }
    }
    
    // Markiere Migration als abgeschlossen
    update_option('cbd_blocks_migrated_v251', true);
}

/**
 * Clear Block Editor Cache
 */
add_action('admin_init', 'cbd_clear_block_cache');
function cbd_clear_block_cache() {
    if (isset($_GET['cbd_clear_cache']) && current_user_can('manage_options')) {
        // Clear WordPress cache
        wp_cache_flush();
        
        // Clear transients
        delete_transient('cbd_active_blocks_' . get_current_blog_id());
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Redirect ohne Parameter
        wp_redirect(remove_query_arg('cbd_clear_cache'));
        exit;
    }
}

/**
 * Admin Notice für Cache-Clear
 */
add_action('admin_notices', 'cbd_cache_clear_notice');
function cbd_cache_clear_notice() {
    $screen = get_current_screen();
    
    if (!$screen || strpos($screen->id, 'container-block-designer') === false) {
        return;
    }
    
    $clear_cache_url = add_query_arg('cbd_clear_cache', '1');
    ?>
    <div class="notice notice-info">
        <p>
            <strong>Container Block Designer:</strong> 
            Falls der Block nicht korrekt funktioniert, 
            <a href="<?php echo esc_url($clear_cache_url); ?>">Cache leeren</a>
        </p>
    </div>
    <?php
}

/**
 * Debug-Informationen im Footer (nur für Admins)
 */
add_action('admin_footer', 'cbd_debug_info');
function cbd_debug_info() {
    if (!current_user_can('manage_options') || !isset($_GET['cbd_debug'])) {
        return;
    }
    
    $screen = get_current_screen();
    if (!$screen || !$screen->is_block_editor()) {
        return;
    }
    
    ?>
    <script>
    console.log('=== CBD Debug Info ===');
    console.log('Registered Blocks:', wp.blocks.getBlockTypes().filter(b => b.name.includes('container')));
    console.log('CBD Block Data:', window.cbdBlockData);
    console.log('Loaded Scripts:', Array.from(document.querySelectorAll('script')).filter(s => s.src.includes('container-block')).map(s => s.src));
    console.log('Loaded Styles:', Array.from(document.querySelectorAll('link[rel="stylesheet"]')).filter(l => l.href.includes('container-block')).map(l => l.href));
    </script>
    <?php
}