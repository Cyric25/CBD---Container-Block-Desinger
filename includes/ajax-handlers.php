<?php
/**
 * Container Block Designer - AJAX Handlers
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.2
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Block speichern
 */
add_action('wp_ajax_cbd_save_block', 'cbd_ajax_save_block');
function cbd_ajax_save_block() {
    // Nonce prüfen
    if (!wp_verify_nonce($_POST['nonce'], 'cbd_admin_nonce')) {
        wp_send_json_error('Sicherheitsprüfung fehlgeschlagen');
    }
    
    // Berechtigung prüfen - Mitarbeiter können Blocks verwalten
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Keine Berechtigung');
    }
    
    global $wpdb;
    
    // Daten sammeln
    $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
    $name = sanitize_text_field($_POST['name'] ?? '');
    $title = sanitize_text_field($_POST['title'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $status = isset($_POST['status']) ? 'active' : 'inactive';
    
    // Styles
    $styles = array(
        'backgroundColor' => sanitize_hex_color($_POST['backgroundColor'] ?? '#ffffff'),
        'color' => sanitize_hex_color($_POST['textColor'] ?? '#333333'),
        'borderStyle' => sanitize_text_field($_POST['borderStyle'] ?? 'solid'),
        'borderWidth' => intval($_POST['borderWidth'] ?? 1),
        'borderColor' => sanitize_hex_color($_POST['borderColor'] ?? '#e0e0e0'),
        'borderRadius' => intval($_POST['borderRadius'] ?? 4),
        'padding' => intval($_POST['padding'] ?? 20),
        'margin' => intval($_POST['margin'] ?? 0)
    );
    
    // Config
    $config = array(
        'styles' => $styles,
        'customCSS' => sanitize_textarea_field($_POST['customCSS'] ?? '')
    );
    
    // Daten für Datenbank vorbereiten
    $data = array(
        'name' => $name,
        'title' => $title,
        'description' => $description,
        'config' => json_encode($config),
        'styles' => json_encode($styles),
        'status' => $status,
        'updated_at' => current_time('mysql')
    );
    
    if ($block_id > 0) {
        // UPDATE existing block
        $result = $wpdb->update(
            CBD_TABLE_BLOCKS,
            $data,
            array('id' => $block_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Block erfolgreich aktualisiert',
                'block_id' => $block_id
            ));
        } else {
            wp_send_json_error('Fehler beim Aktualisieren: ' . $wpdb->last_error);
        }
    } else {
        // CREATE new block
        $data['created_at'] = current_time('mysql');
        $data['features'] = json_encode(cbd_get_default_features());
        
        $result = $wpdb->insert(
            CBD_TABLE_BLOCKS,
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Block erfolgreich erstellt',
                'block_id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error('Fehler beim Erstellen: ' . $wpdb->last_error);
        }
    }
}

/**
 * AJAX: Block duplizieren
 */
add_action('wp_ajax_cbd_duplicate_block', 'cbd_ajax_duplicate_block');
function cbd_ajax_duplicate_block() {
    // Nonce prüfen
    if (!wp_verify_nonce($_POST['nonce'], 'cbd_admin_nonce')) {
        wp_send_json_error('Sicherheitsprüfung fehlgeschlagen');
    }
    
    // Berechtigung prüfen - Mitarbeiter können Blocks verwalten
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Keine Berechtigung');
    }
    
    global $wpdb;
    
    $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
    
    if (!$block_id) {
        wp_send_json_error('Ungültige Block ID');
    }
    
    // Original Block holen
    $block = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $block_id
    ), ARRAY_A);
    
    if (!$block) {
        wp_send_json_error('Block nicht gefunden');
    }
    
    // Duplizierung vorbereiten
    unset($block['id']);
    $block['name'] = $block['name'] . '_copy_' . time();
    $block['title'] = $block['title'] . ' (Kopie)';
    $block['created_at'] = current_time('mysql');
    $block['updated_at'] = current_time('mysql');
    $block['status'] = 'inactive'; // Kopie standardmäßig inaktiv
    
    // Duplikat einfügen
    $result = $wpdb->insert(CBD_TABLE_BLOCKS, $block);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Block erfolgreich dupliziert',
            'new_id' => $wpdb->insert_id
        ));
    } else {
        wp_send_json_error('Fehler beim Duplizieren: ' . $wpdb->last_error);
    }
}

/**
 * AJAX: Block löschen
 */
add_action('wp_ajax_cbd_delete_block', 'cbd_ajax_delete_block');
function cbd_ajax_delete_block() {
    // Nonce prüfen
    if (!wp_verify_nonce($_POST['nonce'], 'cbd_admin_nonce')) {
        wp_send_json_error('Sicherheitsprüfung fehlgeschlagen');
    }
    
    // Berechtigung prüfen - Mitarbeiter können Blocks verwalten
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Keine Berechtigung');
    }
    
    global $wpdb;
    
    $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
    
    if (!$block_id) {
        wp_send_json_error('Ungültige Block ID');
    }
    
    // Block löschen
    $result = $wpdb->delete(
        CBD_TABLE_BLOCKS,
        array('id' => $block_id),
        array('%d')
    );
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Block erfolgreich gelöscht'
        ));
    } else {
        wp_send_json_error('Fehler beim Löschen: ' . $wpdb->last_error);
    }
}

/**
 * AJAX: Block Status ändern
 */
add_action('wp_ajax_cbd_toggle_status', 'cbd_ajax_toggle_status');
function cbd_ajax_toggle_status() {
    // Nonce prüfen
    if (!wp_verify_nonce($_POST['nonce'], 'cbd_admin_nonce')) {
        wp_send_json_error('Sicherheitsprüfung fehlgeschlagen');
    }
    
    // Berechtigung prüfen - Mitarbeiter können Blocks verwalten
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Keine Berechtigung');
    }
    
    global $wpdb;
    
    $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
    
    if (!$block_id) {
        wp_send_json_error('Ungültige Block ID');
    }
    
    // Aktuellen Status holen
    $current_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
        $block_id
    ));
    
    if (!$current_status) {
        wp_send_json_error('Block nicht gefunden');
    }
    
    // Status umschalten
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';
    
    // Status aktualisieren
    $result = $wpdb->update(
        CBD_TABLE_BLOCKS,
        array(
            'status' => $new_status,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $block_id),
        array('%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success(array(
            'message' => 'Status erfolgreich geändert',
            'new_status' => $new_status
        ));
    } else {
        wp_send_json_error('Fehler beim Ändern des Status: ' . $wpdb->last_error);
    }
}

/**
 * AJAX: Features speichern
 */
add_action('wp_ajax_cbd_save_features', 'cbd_ajax_save_features');
function cbd_ajax_save_features() {
    // Nonce prüfen
    if (!wp_verify_nonce($_POST['nonce'], 'cbd_admin_nonce')) {
        wp_send_json_error('Sicherheitsprüfung fehlgeschlagen');
    }
    
    // Berechtigung prüfen - Mitarbeiter können Blocks verwalten
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Keine Berechtigung');
    }
    
    global $wpdb;
    
    $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
    $features = isset($_POST['features']) ? $_POST['features'] : array();
    
    if (!$block_id) {
        wp_send_json_error('Ungültige Block ID');
    }
    
    // Features validieren und sanitisieren
    $sanitized_features = array();
    foreach ($features as $key => $feature) {
        $sanitized_features[$key] = array(
            'enabled' => isset($feature['enabled']) ? true : false,
            'value' => sanitize_text_field($feature['value'] ?? '')
        );
    }
    
    // Features in Datenbank speichern
    $result = $wpdb->update(
        CBD_TABLE_BLOCKS,
        array(
            'features' => json_encode($sanitized_features),
            'updated_at' => current_time('mysql')
        ),
        array('id' => $block_id),
        array('%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success(array(
            'message' => 'Features erfolgreich gespeichert'
        ));
    } else {
        wp_send_json_error('Fehler beim Speichern der Features: ' . $wpdb->last_error);
    }
}

/**
 * AJAX: Blocks suchen
 */
add_action('wp_ajax_cbd_search_blocks', 'cbd_ajax_search_blocks');
function cbd_ajax_search_blocks() {
    // Nonce prüfen
    if (!wp_verify_nonce($_POST['nonce'], 'cbd_admin_nonce')) {
        wp_send_json_error('Sicherheitsprüfung fehlgeschlagen');
    }
    
    global $wpdb;
    
    $search = sanitize_text_field($_POST['search'] ?? '');
    
    if (empty($search)) {
        // Alle Blocks zurückgeben
        $blocks = $wpdb->get_results("SELECT * FROM " . CBD_TABLE_BLOCKS . " ORDER BY name ASC", ARRAY_A);
    } else {
        // Nach Blocks suchen
        $search = '%' . $wpdb->esc_like($search) . '%';
        $blocks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " 
            WHERE name LIKE %s 
            OR title LIKE %s 
            OR description LIKE %s 
            ORDER BY name ASC",
            $search,
            $search,
            $search
        ), ARRAY_A);
    }
    
    wp_send_json_success(array(
        'blocks' => $blocks
    ));
}

/**
 * Get default features configuration
 */
function cbd_get_default_features() {
    return array(
        'icon' => array(
            'enabled' => false,
            'value' => 'dashicons-admin-generic'
        ),
        'collapse' => array(
            'enabled' => false,
            'value' => 'expanded'
        ),
        'numbering' => array(
            'enabled' => false,
            'value' => 'numeric'
        ),
        'copyText' => array(
            'enabled' => false,
            'value' => 'Text kopieren'
        ),
        'screenshot' => array(
            'enabled' => false,
            'value' => 'Screenshot'
        )
    );
}

/**
 * AJAX Debug Helper
 */
function cbd_debug_ajax($message, $data = array()) {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        error_log('CBD AJAX: ' . $message . ' - ' . print_r($data, true));
    }
}
