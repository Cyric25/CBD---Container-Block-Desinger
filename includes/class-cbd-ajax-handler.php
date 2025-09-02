<?php
/**
 * Container Block Designer - AJAX Handler Korrektur
 * Version: 2.5.3
 * 
 * Datei: includes/class-cbd-ajax-handler.php
 * 
 * KORRIGIERT: Lädt echte Blocks aus der Datenbank
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasse für AJAX-Anfragen
 */
class CBD_Ajax_Handler {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->init_ajax_handlers();
    }
    
    /**
     * AJAX-Handler initialisieren
     */
    private function init_ajax_handlers() {
        // Block-Abruf für Editor
        add_action('wp_ajax_cbd_get_blocks', array($this, 'get_blocks'));
        add_action('wp_ajax_nopriv_cbd_get_blocks', array($this, 'get_blocks_nopriv'));
        
        // Weitere Admin AJAX-Handler
        add_action('wp_ajax_cbd_save_block', array($this, 'save_block'));
        add_action('wp_ajax_cbd_delete_block', array($this, 'delete_block'));
        add_action('wp_ajax_cbd_duplicate_block', array($this, 'duplicate_block'));
    }
    
    /**
     * Alle Blöcke abrufen - KORRIGIERTE VERSION
     */
    public function get_blocks() {
        // Debug-Log
        error_log('CBD: AJAX get_blocks aufgerufen');
        
        // Nonce-Überprüfung - flexibel für verschiedene Nonces
        $nonce_valid = false;
        
        // Prüfe verschiedene mögliche Nonce-Namen
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'cbd-nonce') || 
                wp_verify_nonce($_POST['nonce'], 'cbd-admin-nonce') ||
                wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
                $nonce_valid = true;
            }
        }
        
        // Falls kein gültiger Nonce, prüfe ob Benutzer berechtigt ist
        if (!$nonce_valid && !current_user_can('edit_posts')) {
            error_log('CBD: Nonce-Prüfung fehlgeschlagen');
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }
        
        global $wpdb;
        
        // Hole Blocks direkt aus der Datenbank
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        // Prüfe ob Tabelle existiert
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            error_log('CBD: Tabelle existiert nicht: ' . $table_name);
            wp_send_json_success(array());
            return;
        }
        
        // SQL-Query für aktive Blocks
        $sql = "SELECT 
                id,
                COALESCE(title, name) as name,
                slug,
                description,
                config,
                features,
                status
            FROM $table_name 
            WHERE status = 'active' OR status IS NULL
            ORDER BY COALESCE(title, name) ASC";
        
        $blocks = $wpdb->get_results($sql, ARRAY_A);
        
        error_log('CBD: Gefundene Blocks: ' . count($blocks));
        
        // Verarbeite die Blocks
        $processed_blocks = array();
        
        if ($blocks) {
            foreach ($blocks as $block) {
                // Stelle sicher dass slug existiert
                if (empty($block['slug'])) {
                    $block['slug'] = sanitize_title($block['name']);
                }
                
                // Parse JSON-Felder
                $config = !empty($block['config']) ? json_decode($block['config'], true) : array();
                $features = !empty($block['features']) ? json_decode($block['features'], true) : array();
                
                $processed_blocks[] = array(
                    'id' => intval($block['id']),
                    'name' => esc_html($block['name']),
                    'slug' => sanitize_title($block['slug']),
                    'description' => esc_html($block['description'] ?: ''),
                    'config' => $config ?: array(),
                    'features' => $features ?: array()
                );
            }
        }
        
        // Debug-Ausgabe
        error_log('CBD: Sende ' . count($processed_blocks) . ' Blocks zurück');
        
        // Sende erfolgreiche Antwort
        wp_send_json_success($processed_blocks);
    }
    
    /**
     * Blocks für nicht eingeloggte Benutzer (sollte nicht aufgerufen werden)
     */
    public function get_blocks_nopriv() {
        wp_send_json_error(array('message' => 'Nicht autorisiert'));
    }
    
    /**
     * Block speichern
     */
    public function save_block() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbd-admin-nonce')) {
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        // Daten vorbereiten
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'slug' => sanitize_title($_POST['slug'] ?: $_POST['name']),
            'description' => sanitize_textarea_field($_POST['description'] ?: ''),
            'config' => json_encode($_POST['config'] ?: array()),
            'features' => json_encode($_POST['features'] ?: array()),
            'status' => 'active'
        );
        
        // Speichern oder Update
        if (!empty($_POST['id'])) {
            $result = $wpdb->update($table_name, $data, array('id' => intval($_POST['id'])));
        } else {
            $result = $wpdb->insert($table_name, $data);
        }
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Fehler beim Speichern'));
            return;
        }
        
        wp_send_json_success(array('message' => 'Block gespeichert'));
    }
    
    /**
     * Block löschen
     */
    public function delete_block() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbd-admin-nonce')) {
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        $block_id = intval($_POST['block_id']);
        
        if (!$block_id) {
            wp_send_json_error(array('message' => 'Ungültige Block-ID'));
            return;
        }
        
        $result = $wpdb->delete($table_name, array('id' => $block_id));
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Fehler beim Löschen'));
            return;
        }
        
        wp_send_json_success(array('message' => 'Block gelöscht'));
    }
    
    /**
     * Block duplizieren
     */
    public function duplicate_block() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbd-admin-nonce')) {
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        $block_id = intval($_POST['block_id']);
        
        if (!$block_id) {
            wp_send_json_error(array('message' => 'Ungültige Block-ID'));
            return;
        }
        
        // Original-Block holen
        $original = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $block_id),
            ARRAY_A
        );
        
        if (!$original) {
            wp_send_json_error(array('message' => 'Block nicht gefunden'));
            return;
        }
        
        // Kopie erstellen
        unset($original['id']);
        $original['name'] = $original['name'] . ' (Kopie)';
        $original['slug'] = $original['slug'] . '-copy-' . time();
        
        $result = $wpdb->insert($table_name, $original);
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Fehler beim Duplizieren'));
            return;
        }
        
        wp_send_json_success(array('message' => 'Block dupliziert', 'id' => $wpdb->insert_id));
    }
}

// Initialisierung
new CBD_Ajax_Handler();