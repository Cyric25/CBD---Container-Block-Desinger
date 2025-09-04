<?php
/**
 * Container Block Designer - AJAX Handler
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.3
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler Klasse
 */
class CBD_Ajax_Handler {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * AJAX Hooks initialisieren
     */
    private function init_hooks() {
        // Block speichern
        add_action('wp_ajax_cbd_save_block', array($this, 'save_block'));
        
        // Block löschen
        add_action('wp_ajax_cbd_delete_block', array($this, 'delete_block'));
        
        // Block-Status ändern
        add_action('wp_ajax_cbd_toggle_block_status', array($this, 'toggle_block_status'));
        
        // Block-Daten abrufen
        add_action('wp_ajax_cbd_get_block', array($this, 'get_block'));
        add_action('wp_ajax_nopriv_cbd_get_block', array($this, 'get_block'));
        
        // Block-Liste abrufen
        add_action('wp_ajax_cbd_get_blocks', array($this, 'get_blocks'));
        add_action('wp_ajax_nopriv_cbd_get_blocks', array($this, 'get_blocks'));
        
        // Block duplizieren
        add_action('wp_ajax_cbd_duplicate_block', array($this, 'duplicate_block'));
        
        // Block exportieren
        add_action('wp_ajax_cbd_export_block', array($this, 'export_block'));
        
        // Block importieren
        add_action('wp_ajax_cbd_import_block', array($this, 'import_block'));
        
        // Styles neu generieren
        add_action('wp_ajax_cbd_regenerate_styles', array($this, 'regenerate_styles'));
    }
    
    /**
     * Nonce verifizieren
     */
    private function verify_nonce($nonce_name = 'cbd_ajax_nonce') {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $nonce_name)) {
            wp_send_json_error(array(
                'message' => __('Sicherheitsprüfung fehlgeschlagen.', 'container-block-designer')
            ));
        }
    }
    
    /**
     * Berechtigung prüfen
     */
    private function check_capability($capability = 'manage_container_blocks') {
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('Sie haben nicht die erforderliche Berechtigung.', 'container-block-designer')
            ));
        }
    }
    
    /**
     * Block speichern
     */
    public function save_block() {
        $this->verify_nonce();
        $this->check_capability('edit_container_blocks');
        
        // Daten validieren
        if (empty($_POST['name'])) {
            wp_send_json_error(array(
                'message' => __('Block-Name ist erforderlich.', 'container-block-designer')
            ));
        }
        
        $block_data = array(
            'id' => isset($_POST['id']) ? intval($_POST['id']) : null,
            'name' => sanitize_text_field($_POST['name']),
            'title' => sanitize_text_field($_POST['title'] ?? $_POST['name']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'config' => isset($_POST['config']) ? $_POST['config'] : array(),
            'styles' => isset($_POST['styles']) ? $_POST['styles'] : array(),
            'features' => isset($_POST['features']) ? $_POST['features'] : array(),
            'status' => sanitize_text_field($_POST['status'] ?? 'active')
        );
        
        // In Datenbank speichern
        $result = CBD_Database::save_block($block_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        // Style-Cache leeren
        if (class_exists('CBD_Style_Loader')) {
            $style_loader = CBD_Style_Loader::get_instance();
            $style_loader->clear_styles_cache();
        }
        
        wp_send_json_success(array(
            'message' => __('Block erfolgreich gespeichert.', 'container-block-designer'),
            'block_id' => $result
        ));
    }
    
    /**
     * Block löschen
     */
    public function delete_block() {
        $this->verify_nonce();
        $this->check_capability('delete_container_blocks');
        
        if (empty($_POST['block_id'])) {
            wp_send_json_error(array(
                'message' => __('Block-ID fehlt.', 'container-block-designer')
            ));
        }
        
        $block_id = intval($_POST['block_id']);
        $result = CBD_Database::delete_block($block_id);
        
        if (!$result) {
            wp_send_json_error(array(
                'message' => __('Block konnte nicht gelöscht werden.', 'container-block-designer')
            ));
        }
        
        // Style-Cache leeren
        if (class_exists('CBD_Style_Loader')) {
            $style_loader = CBD_Style_Loader::get_instance();
            $style_loader->clear_styles_cache();
        }
        
        wp_send_json_success(array(
            'message' => __('Block erfolgreich gelöscht.', 'container-block-designer')
        ));
    }
    
    /**
     * Block-Status umschalten
     */
    public function toggle_block_status() {
        $this->verify_nonce();
        $this->check_capability('edit_container_blocks');
        
        if (empty($_POST['block_id'])) {
            wp_send_json_error(array(
                'message' => __('Block-ID fehlt.', 'container-block-designer')
            ));
        }
        
        $block_id = intval($_POST['block_id']);
        $block = CBD_Database::get_block($block_id);
        
        if (!$block) {
            wp_send_json_error(array(
                'message' => __('Block nicht gefunden.', 'container-block-designer')
            ));
        }
        
        $new_status = $block['status'] === 'active' ? 'inactive' : 'active';
        $result = CBD_Database::update_block_status($block_id, $new_status);
        
        if (!$result) {
            wp_send_json_error(array(
                'message' => __('Status konnte nicht geändert werden.', 'container-block-designer')
            ));
        }
        
        // Style-Cache leeren
        if (class_exists('CBD_Style_Loader')) {
            $style_loader = CBD_Style_Loader::get_instance();
            $style_loader->clear_styles_cache();
        }
        
        wp_send_json_success(array(
            'message' => __('Status erfolgreich geändert.', 'container-block-designer'),
            'new_status' => $new_status
        ));
    }
    
    /**
     * Block-Daten abrufen
     */
    public function get_block() {
        // Kein Nonce-Check für öffentliche Anfragen
        
        if (empty($_REQUEST['block_id'])) {
            wp_send_json_error(array(
                'message' => __('Block-ID fehlt.', 'container-block-designer')
            ));
        }
        
        $block_id = intval($_REQUEST['block_id']);
        $block = CBD_Database::get_block($block_id);
        
        if (!$block) {
            wp_send_json_error(array(
                'message' => __('Block nicht gefunden.', 'container-block-designer')
            ));
        }
        
        wp_send_json_success($block);
    }
    
    /**
     * Block-Liste abrufen
     */
    public function get_blocks() {
        // Kein Nonce-Check für öffentliche Anfragen
        
        $args = array(
            'status' => isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : 'active',
            'orderby' => isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'name',
            'order' => isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC'
        );
        
        $blocks = CBD_Database::get_all_blocks($args);
        
        wp_send_json_success($blocks);
    }
    
    /**
     * Block duplizieren
     */
    public function duplicate_block() {
        $this->verify_nonce();
        $this->check_capability('edit_container_blocks');
        
        if (empty($_POST['block_id'])) {
            wp_send_json_error(array(
                'message' => __('Block-ID fehlt.', 'container-block-designer')
            ));
        }
        
        $block_id = intval($_POST['block_id']);
        $original_block = CBD_Database::get_block($block_id);
        
        if (!$original_block) {
            wp_send_json_error(array(
                'message' => __('Original-Block nicht gefunden.', 'container-block-designer')
            ));
        }
        
        // Neuen eindeutigen Namen generieren
        $base_name = $original_block['name'];
        $counter = 1;
        
        do {
            $new_name = $base_name . '-copy-' . $counter;
            $counter++;
        } while (CBD_Database::block_name_exists($new_name));
        
        // Block duplizieren
        $new_block = $original_block;
        unset($new_block['id']);
        $new_block['name'] = $new_name;
        $new_block['title'] = $original_block['title'] . ' ' . __('(Kopie)', 'container-block-designer');
        
        $result = CBD_Database::save_block($new_block);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Block erfolgreich dupliziert.', 'container-block-designer'),
            'block_id' => $result
        ));
    }
    
    /**
     * Block exportieren
     */
    public function export_block() {
        $this->verify_nonce();
        $this->check_capability('manage_container_blocks');
        
        if (empty($_POST['block_id'])) {
            wp_send_json_error(array(
                'message' => __('Block-ID fehlt.', 'container-block-designer')
            ));
        }
        
        $block_id = intval($_POST['block_id']);
        $block = CBD_Database::get_block($block_id);
        
        if (!$block) {
            wp_send_json_error(array(
                'message' => __('Block nicht gefunden.', 'container-block-designer')
            ));
        }
        
        // Sensible Daten entfernen
        unset($block['id']);
        unset($block['created_at']);
        unset($block['updated_at']);
        
        // Versionsinformationen hinzufügen
        $export_data = array(
            'plugin_version' => CBD_VERSION,
            'export_date' => current_time('mysql'),
            'block_data' => $block
        );
        
        wp_send_json_success($export_data);
    }
    
    /**
     * Block importieren
     */
    public function import_block() {
        $this->verify_nonce();
        $this->check_capability('manage_container_blocks');
        
        if (empty($_POST['import_data'])) {
            wp_send_json_error(array(
                'message' => __('Import-Daten fehlen.', 'container-block-designer')
            ));
        }
        
        // JSON dekodieren
        $import_data = json_decode(stripslashes($_POST['import_data']), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => __('Ungültige Import-Daten.', 'container-block-designer')
            ));
        }
        
        // Daten validieren
        if (!isset($import_data['block_data'])) {
            wp_send_json_error(array(
                'message' => __('Block-Daten fehlen in Import.', 'container-block-designer')
            ));
        }
        
        $block_data = $import_data['block_data'];
        
        // Prüfe ob Name bereits existiert
        if (CBD_Database::block_name_exists($block_data['name'])) {
            // Generiere neuen Namen
            $base_name = $block_data['name'];
            $counter = 1;
            
            do {
                $new_name = $base_name . '-import-' . $counter;
                $counter++;
            } while (CBD_Database::block_name_exists($new_name));
            
            $block_data['name'] = $new_name;
            $block_data['title'] .= ' ' . __('(Importiert)', 'container-block-designer');
        }
        
        // Block speichern
        $result = CBD_Database::save_block($block_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        // Style-Cache leeren
        if (class_exists('CBD_Style_Loader')) {
            $style_loader = CBD_Style_Loader::get_instance();
            $style_loader->clear_styles_cache();
        }
        
        wp_send_json_success(array(
            'message' => __('Block erfolgreich importiert.', 'container-block-designer'),
            'block_id' => $result
        ));
    }
    
    /**
     * Styles neu generieren
     */
    public function regenerate_styles() {
        $this->verify_nonce();
        $this->check_capability('manage_container_blocks');
        
        // Style-Cache leeren und neu generieren
        if (class_exists('CBD_Style_Loader')) {
            $style_loader = CBD_Style_Loader::get_instance();
            $style_loader->clear_styles_cache();
            
            // Neue Styles generieren (wird beim nächsten Seitenaufruf automatisch gemacht)
            wp_send_json_success(array(
                'message' => __('Styles wurden erfolgreich neu generiert.', 'container-block-designer')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Style Loader nicht verfügbar.', 'container-block-designer')
            ));
        }
    }
}