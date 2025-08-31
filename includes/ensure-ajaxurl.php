<?php
/**
 * Container Block Designer - Ensure AJAX URL is available
 * 
 * Diese Datei stellt sicher, dass ajaxurl immer verfügbar ist
 * Speichern als: /wp-content/plugins/container-block-designer/includes/ensure-ajaxurl.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Make sure ajaxurl is available in admin
 */
add_action('admin_head', 'cbd_ensure_ajaxurl');
function cbd_ensure_ajaxurl() {
    ?>
    <script type="text/javascript">
    /* <![CDATA[ */
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    /* ]]> */
    </script>
    <?php
}

/**
 * Ensure proper script localization on our pages
 */
add_action('admin_print_scripts', 'cbd_ensure_localization', 999);
function cbd_ensure_localization() {
    $screen = get_current_screen();
    
    // Only on our plugin pages
    if (!$screen || strpos($screen->id, 'container-block-designer') === false) {
        return;
    }
    
    ?>
    <script type="text/javascript">
    /* CBD Localization Check */
    jQuery(document).ready(function($) {
        // Ensure cbdAdmin is available
        if (typeof cbdAdmin === 'undefined') {
            console.warn('CBD: Creating cbdAdmin fallback...');
            window.cbdAdmin = {
                ajaxUrl: ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('cbd-admin'); ?>',
                blocksListUrl: '<?php echo admin_url('admin.php?page=container-block-designer'); ?>',
                strings: {
                    confirmDelete: '<?php echo esc_js(__('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer')); ?>',
                    saving: '<?php echo esc_js(__('Speichern...', 'container-block-designer')); ?>',
                    saved: '<?php echo esc_js(__('Gespeichert!', 'container-block-designer')); ?>',
                    error: '<?php echo esc_js(__('Ein Fehler ist aufgetreten.', 'container-block-designer')); ?>',
                    active: '<?php echo esc_js(__('Aktiv', 'container-block-designer')); ?>',
                    inactive: '<?php echo esc_js(__('Inaktiv', 'container-block-designer')); ?>',
                    activate: '<?php echo esc_js(__('Aktivieren', 'container-block-designer')); ?>',
                    deactivate: '<?php echo esc_js(__('Deaktivieren', 'container-block-designer')); ?>'
                }
            };
            console.log('CBD: cbdAdmin created with ajaxUrl:', cbdAdmin.ajaxUrl);
        }
        
        // Also ensure CBDAdmin is initialized
        if (typeof CBDAdmin !== 'undefined' && typeof CBDAdmin.init === 'function') {
            // Re-initialize if not already done
            if (!window.CBDAdminInitialized) {
                console.log('CBD: Re-initializing CBDAdmin...');
                CBDAdmin.init();
                window.CBDAdminInitialized = true;
            }
        }
    });
    </script>
    <?php
}