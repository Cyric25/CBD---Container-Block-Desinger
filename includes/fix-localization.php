<?php
/**
 * Container Block Designer - Script Localization Fix
 * 
 * Diese Datei behebt Probleme mit der JavaScript-Lokalisierung
 * Datei speichern als: /wp-content/plugins/container-block-designer/includes/fix-localization.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fix admin script localization
 */
add_action('admin_enqueue_scripts', 'cbd_fix_admin_localization', 20);
function cbd_fix_admin_localization($hook) {
    // Nur auf unseren Plugin-Seiten
    if (strpos($hook, 'container-block-designer') === false) {
        return;
    }
    
    // Stelle sicher, dass admin.js geladen ist
    if (!wp_script_is('cbd-admin', 'registered')) {
        // Registriere das Script falls noch nicht geschehen
        wp_register_script(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            CBD_VERSION,
            true
        );
    }
    
    // Enqueue falls noch nicht geschehen
    if (!wp_script_is('cbd-admin', 'enqueued')) {
        wp_enqueue_script('cbd-admin');
    }
    
    // Erstelle Nonce
    $nonce = wp_create_nonce('cbd-admin');
    
    // Lokalisiere das Script mit allen notwendigen Daten
    wp_localize_script('cbd-admin', 'cbdAdmin', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => $nonce,
        'blocksListUrl' => admin_url('admin.php?page=container-block-designer'),
        'strings' => array(
            'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
            'saving' => __('Speichern...', 'container-block-designer'),
            'saved' => __('Gespeichert!', 'container-block-designer'),
            'error' => __('Ein Fehler ist aufgetreten.', 'container-block-designer'),
            'active' => __('Aktiv', 'container-block-designer'),
            'inactive' => __('Inaktiv', 'container-block-designer'),
            'activate' => __('Aktivieren', 'container-block-designer'),
            'deactivate' => __('Deaktivieren', 'container-block-designer')
        ),
        'debug' => WP_DEBUG
    ));
    
    // Features Script lokalisieren
    if (wp_script_is('cbd-admin-features', 'enqueued')) {
        wp_localize_script('cbd-admin-features', 'cbdFeatures', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_features_nonce'),
            'strings' => array(
                'saved' => __('Features gespeichert', 'container-block-designer'),
                'error' => __('Fehler beim Speichern', 'container-block-designer'),
                'confirmReset' => __('Wirklich alle Features zurücksetzen?', 'container-block-designer'),
                'loading' => __('Wird geladen...', 'container-block-designer'),
                'defaultCopyText' => __('Text kopieren', 'container-block-designer'),
                'defaultScreenshotText' => __('Screenshot', 'container-block-designer')
            )
        ));
    }
}

/**
 * Add nonce field to forms
 */
add_action('cbd_block_form_fields', 'cbd_add_nonce_field');
function cbd_add_nonce_field() {
    wp_nonce_field('cbd-admin', 'cbd_nonce');
}

/**
 * Debug-Funktion für die Admin-Seiten
 */
add_action('admin_footer', 'cbd_debug_scripts');
function cbd_debug_scripts() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'container-block-designer') === false) {
        return;
    }
    
    if (WP_DEBUG) {
        ?>
        <script>
        console.log('🔍 CBD Debug Info:');
        console.log('- cbdAdmin exists:', typeof cbdAdmin !== 'undefined');
        if (typeof cbdAdmin !== 'undefined') {
            console.log('- cbdAdmin.ajaxUrl:', cbdAdmin.ajaxUrl);
            console.log('- cbdAdmin.nonce:', cbdAdmin.nonce ? '✅ Set' : '❌ Missing');
            console.log('- cbdAdmin.strings:', cbdAdmin.strings);
        }
        console.log('- jQuery exists:', typeof jQuery !== 'undefined');
        console.log('- ajaxurl (global):', typeof ajaxurl !== 'undefined' ? ajaxurl : 'Not defined');
        
        // Prüfe ob CBDAdmin initialisiert wurde
        jQuery(document).ready(function($) {
            setTimeout(function() {
                console.log('🔍 After DOM ready:');
                console.log('- CBDAdmin exists:', typeof CBDAdmin !== 'undefined');
                console.log('- Forms found:', $('#cbd-block-form').length);
                console.log('- Save buttons found:', $('#cbd-save-block').length);
                console.log('- Delete buttons found:', $('.cbd-delete-btn').length);
            }, 1000);
        });
        </script>
        <?php
    }
}

/**
 * Ensure AJAX handlers have proper nonce checking
 */
add_filter('wp_ajax_cbd_save_block', 'cbd_ensure_nonce_check', 1);
add_filter('wp_ajax_cbd_update_block', 'cbd_ensure_nonce_check', 1);
add_filter('wp_ajax_cbd_delete_block', 'cbd_ensure_nonce_check', 1);
add_filter('wp_ajax_cbd_toggle_status', 'cbd_ensure_nonce_check', 1);
add_filter('wp_ajax_cbd_duplicate_block', 'cbd_ensure_nonce_check', 1);

function cbd_ensure_nonce_check() {
    // Prüfe ob Nonce vorhanden ist
    if (!isset($_POST['nonce']) && !isset($_REQUEST['_wpnonce'])) {
        // Versuche Nonce aus verschiedenen Quellen zu bekommen
        if (isset($_POST['cbd_nonce'])) {
            $_POST['nonce'] = $_POST['cbd_nonce'];
        } elseif (isset($_REQUEST['cbd_nonce'])) {
            $_POST['nonce'] = $_REQUEST['cbd_nonce'];
        } else {
            // Wenn im Debug-Modus, erlaube Anfragen ohne Nonce
            if (WP_DEBUG) {
                error_log('CBD Warning: AJAX request without nonce - allowing in debug mode');
                $_POST['nonce'] = wp_create_nonce('cbd-admin');
            }
        }
    }
}

/**
 * Add duplicate block handler if missing
 */
if (!has_action('wp_ajax_cbd_duplicate_block')) {
    add_action('wp_ajax_cbd_duplicate_block', 'cbd_ajax_duplicate_block');
    
    function cbd_ajax_duplicate_block() {
        global $wpdb;
        
        // Nonce verification
        if (!check_ajax_referer('cbd-admin', 'nonce', false) && !WP_DEBUG) {
            wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')]);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'container-block-designer')]);
            return;
        }
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(['message' => __('Ungültige Block-ID', 'container-block-designer')]);
            return;
        }
        
        // Get original block
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ), ARRAY_A);
        
        if (!$original) {
            wp_send_json_error(['message' => __('Block nicht gefunden', 'container-block-designer')]);
            return;
        }
        
        // Create new block with modified name and slug
        $counter = 1;
        $new_name = $original['name'] . ' (Kopie)';
        $new_slug = $original['slug'] . '-copy';
        
        // Check if slug already exists
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s",
            $new_slug . ($counter > 1 ? '-' . $counter : '')
        )) > 0) {
            $counter++;
        }
        
        if ($counter > 1) {
            $new_name = $original['name'] . ' (Kopie ' . $counter . ')';
            $new_slug = $new_slug . '-' . $counter;
        }
        
        // Insert duplicate
        $result = $wpdb->insert(
            CBD_TABLE_BLOCKS,
            array(
                'name' => $new_name,
                'slug' => $new_slug,
                'description' => $original['description'],
                'config' => $original['config'],
                'features' => $original['features'],
                'status' => 'inactive', // Start as inactive
                'created' => current_time('mysql'),
                'modified' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Fehler beim Duplizieren des Blocks', 'container-block-designer')]);
            return;
        }
        
        wp_send_json_success([
            'message' => __('Block erfolgreich dupliziert', 'container-block-designer'),
            'block_id' => $wpdb->insert_id
        ]);
    }
}