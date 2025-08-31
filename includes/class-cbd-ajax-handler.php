<?php
/**
 * Container Block Designer - AJAX-Handler
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
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
        // Admin AJAX-Handler
        add_action('wp_ajax_cbd_get_blocks', array($this, 'get_blocks'));
        add_action('wp_ajax_cbd_get_block', array($this, 'get_block'));
        add_action('wp_ajax_cbd_save_block', array($this, 'save_block'));
        add_action('wp_ajax_cbd_delete_block', array($this, 'delete_block'));
        add_action('wp_ajax_cbd_duplicate_block', array($this, 'duplicate_block'));
        add_action('wp_ajax_cbd_update_block_status', array($this, 'update_block_status'));
        
        // Editor AJAX-Handler
        add_action('wp_ajax_cbd_get_block_preview', array($this, 'get_block_preview'));
        add_action('wp_ajax_cbd_get_block_styles', array($this, 'get_block_styles'));
        
        // Frontend AJAX-Handler (für eingeloggte Benutzer)
        add_action('wp_ajax_cbd_track_interaction', array($this, 'track_interaction'));
        add_action('wp_ajax_nopriv_cbd_track_interaction', array($this, 'track_interaction'));
    }
    
    /**
     * Alle Blöcke abrufen
     */
    public function get_blocks() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false) && 
            !check_ajax_referer('cbd-block-editor', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
        }
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
        $blocks = CBD_Database::get_blocks(array('status' => $status));
        
        wp_send_json_success($blocks);
    }
    
    /**
     * Einzelnen Block abrufen
     */
    public function get_block() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false) && 
            !check_ajax_referer('cbd-block-editor', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
        }
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(array('message' => __('Ungültige Block-ID', 'container-block-designer')));
        }
        
        $block = CBD_Database::get_block($block_id);
        
        if (!$block) {
            wp_send_json_error(array('message' => __('Block nicht gefunden', 'container-block-designer')));
        }
        
        wp_send_json_success($block);
    }
    
    /**
     * Block speichern
     */
    public function save_block() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
        }
        
        // Daten validieren
        $data = array(
            'id' => isset($_POST['block_id']) ? intval($_POST['block_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'title' => isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
        );
        
        // Name validieren
        if (empty($data['name'])) {
            wp_send_json_error(array('message' => __('Block-Name ist erforderlich', 'container-block-designer')));
        }
        
        // Titel validieren
        if (empty($data['title'])) {
            wp_send_json_error(array('message' => __('Block-Titel ist erforderlich', 'container-block-designer')));
        }
        
        // Config parsen
        if (isset($_POST['config'])) {
            $data['config'] = is_array($_POST['config']) ? $_POST['config'] : json_decode(stripslashes($_POST['config']), true);
        }
        
        // Styles parsen
        if (isset($_POST['styles'])) {
            $data['styles'] = is_array($_POST['styles']) ? $_POST['styles'] : json_decode(stripslashes($_POST['styles']), true);
        }
        
        // Features parsen
        if (isset($_POST['features'])) {
            $data['features'] = is_array($_POST['features']) ? $_POST['features'] : json_decode(stripslashes($_POST['features']), true);
        }
        
        // Block speichern
        $result = CBD_Database::save_block($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Erfolgreiche Antwort
        $block = CBD_Database::get_block($result);
        wp_send_json_success(array(
            'message' => __('Block erfolgreich gespeichert', 'container-block-designer'),
            'block' => $block,
        ));
    }
    
    /**
     * Block löschen
     */
    public function delete_block() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false) && 
            !check_ajax_referer('cbd_delete_block', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
        }
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(array('message' => __('Ungültige Block-ID', 'container-block-designer')));
        }
        
        $result = CBD_Database::delete_block($block_id);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('Fehler beim Löschen des Blocks', 'container-block-designer')));
        }
        
        wp_send_json_success(array(
            'message' => __('Block erfolgreich gelöscht', 'container-block-designer'),
        ));
    }
    
    /**
     * Block duplizieren
     */
    public function duplicate_block() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
        }
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(array('message' => __('Ungültige Block-ID', 'container-block-designer')));
        }
        
        $new_block_id = CBD_Database::duplicate_block($block_id);
        
        if (!$new_block_id) {
            wp_send_json_error(array('message' => __('Fehler beim Duplizieren des Blocks', 'container-block-designer')));
        }
        
        $new_block = CBD_Database::get_block($new_block_id);
        
        wp_send_json_success(array(
            'message' => __('Block erfolgreich dupliziert', 'container-block-designer'),
            'block' => $new_block,
        ));
    }
    
    /**
     * Block-Status aktualisieren
     */
    public function update_block_status() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
        }
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (!$block_id || !in_array($status, array('active', 'inactive', 'draft'))) {
            wp_send_json_error(array('message' => __('Ungültige Parameter', 'container-block-designer')));
        }
        
        $result = CBD_Database::update_block_status($block_id, $status);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('Fehler beim Aktualisieren des Status', 'container-block-designer')));
        }
        
        wp_send_json_success(array(
            'message' => __('Status erfolgreich aktualisiert', 'container-block-designer'),
        ));
    }
    
    /**
     * Block-Vorschau abrufen
     */
    public function get_block_preview() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-block-editor', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
        }
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(array('message' => __('Ungültige Block-ID', 'container-block-designer')));
        }
        
        $block = CBD_Database::get_block($block_id);
        
        if (!$block) {
            wp_send_json_error(array('message' => __('Block nicht gefunden', 'container-block-designer')));
        }
        
        // HTML-Vorschau generieren
        $preview_html = '<div class="cbd-block-preview">';
        $preview_html .= '<h4>' . esc_html($block['title']) . '</h4>';
        
        if (!empty($block['description'])) {
            $preview_html .= '<p>' . esc_html($block['description']) . '</p>';
        }
        
        $preview_html .= '</div>';
        
        wp_send_json_success(array(
            'html' => $preview_html,
            'styles' => $block['styles'],
            'features' => $block['features'],
        ));
    }
    
    /**
     * Block-Styles abrufen
     */
    public function get_block_styles() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-block-editor', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
        }
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(array('message' => __('Ungültige Block-ID', 'container-block-designer')));
        }
        
        $block = CBD_Database::get_block($block_id);
        
        if (!$block) {
            wp_send_json_error(array('message' => __('Block nicht gefunden', 'container-block-designer')));
        }
        
        wp_send_json_success(array(
            'styles' => $block['styles'],
            'config' => $block['config'],
        ));
    }
    
    /**
     * Interaktion tracken (für Analytics)
     */
    public function track_interaction() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-frontend', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer')));
        }
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        $interaction = isset($_POST['interaction']) ? sanitize_text_field($_POST['interaction']) : '';
        
        if (!$block_id || !$interaction) {
            wp_send_json_error(array('message' => __('Ungültige Parameter', 'container-block-designer')));
        }
        
        // Hier könnte man die Interaktion in der Datenbank speichern
        // Für diese Version loggen wir nur
        do_action('cbd_track_interaction', $block_id, $interaction);
        
        wp_send_json_success(array(
            'message' => __('Interaktion getrackt', 'container-block-designer'),
        ));
    }
}