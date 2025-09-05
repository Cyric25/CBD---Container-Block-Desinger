<?php
/**
 * Container Block Designer - Admin-Klasse
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.2
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-Klasse für das Container Block Designer Plugin
 */
class CBD_Admin {
    
    /**
     * Singleton-Instanz
     */
    private static $instance = null;
    
    /**
     * Singleton-Getter
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        // Datenbank-Update einbinden
        if (file_exists(CBD_PLUGIN_DIR . 'includes/update-database.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/update-database.php';
        }
        
        // Admin-Menü hinzufügen
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin-Styles und Scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX-Handler für Admin-Aktionen
        add_action('wp_ajax_cbd_save_block', array($this, 'ajax_save_block'));
        add_action('wp_ajax_cbd_delete_block', array($this, 'ajax_delete_block'));
        add_action('wp_ajax_cbd_toggle_status', array($this, 'ajax_toggle_status'));
        
        // Plugin-Action-Links
        add_filter('plugin_action_links_' . CBD_PLUGIN_BASENAME, array($this, 'add_action_links'));
        
        // Admin-Notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Verarbeite Admin-Aktionen früh (vor Ausgabe)
        add_action('admin_init', array($this, 'process_admin_actions'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        // Hauptmenüpunkt
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Designer', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_main_page'),
            'dashicons-layout',
            30
        );
        
        // Untermenü: Neuer Block
        add_submenu_page(
            'container-block-designer',
            __('Neuer Block', 'container-block-designer'),
            __('Neuer Block', 'container-block-designer'),
            'manage_options',
            'cbd-new-block',
            array($this, 'render_new_block_page')
        );
        
        // Untermenü: Import/Export
        add_submenu_page(
            'container-block-designer',
            __('Import/Export', 'container-block-designer'),
            __('Import/Export', 'container-block-designer'),
            'manage_options',
            'cbd-import-export',
            array($this, 'render_import_export_page')
        );
    }
    
    /**
     * Admin-Assets einbinden
     */
    public function enqueue_admin_assets($hook) {
        // Nur auf Plugin-Seiten laden
        if (strpos($hook, 'container-block-designer') === false && 
            strpos($hook, 'cbd-') === false) {
            return;
        }
        
        // Admin-CSS
        wp_enqueue_style(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CBD_VERSION
        );
        
        // Admin-JavaScript
        wp_enqueue_script(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            CBD_VERSION,
            true
        );
        
        // Color Picker
        wp_enqueue_style('wp-color-picker');
        
        // Lokalisierung
        wp_localize_script('cbd-admin', 'cbdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_admin_nonce'),
            'messages' => array(
                'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
                'saved' => __('Gespeichert!', 'container-block-designer'),
                'error' => __('Ein Fehler ist aufgetreten.', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Hauptseite rendern
     */
    public function render_main_page() {
        $file_path = CBD_PLUGIN_DIR . 'admin/container-block-designer.php';
        
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>' . __('Container Block Designer', 'container-block-designer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Admin-Datei nicht gefunden: admin/container-block-designer.php', 'container-block-designer') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Verarbeite Admin-Aktionen (POST/GET)
     * Muss VOR jeglicher Ausgabe erfolgen!
     */
    public function process_admin_actions() {
        // Nur auf unseren Admin-Seiten
        if (!isset($_GET['page']) || strpos($_GET['page'], 'container-block-designer') === false) {
            return;
        }
        
        global $wpdb;
        
        // Feature-Toggle verarbeiten
        if (isset($_POST['toggle_feature']) && isset($_POST['block_id']) && isset($_POST['feature_key'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cbd_toggle_feature')) {
                wp_die('Sicherheitsprüfung fehlgeschlagen');
            }
            
            $block_id = intval($_POST['block_id']);
            $feature_key = sanitize_text_field($_POST['feature_key']);
            
            $block = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
                $block_id
            ));
            
            if ($block) {
                $features = !empty($block->features) ? json_decode($block->features, true) : array();
                
                if (!isset($features[$feature_key])) {
                    $features[$feature_key] = array('enabled' => true);
                } else {
                    $features[$feature_key]['enabled'] = !$features[$feature_key]['enabled'];
                }
                
                // Verwende die korrekte Spalte 'updated_at'
                $update_data = array(
                    'features' => json_encode($features)
                );
                
                // Prüfe welche Spalte existiert
                $columns = $wpdb->get_col("SHOW COLUMNS FROM " . CBD_TABLE_BLOCKS);
                if (in_array('updated_at', $columns)) {
                    $update_data['updated_at'] = current_time('mysql');
                }
                
                $wpdb->update(
                    CBD_TABLE_BLOCKS,
                    $update_data,
                    array('id' => $block_id)
                );
                
                // Setze Transient für Erfolgsmeldung
                set_transient('cbd_admin_message', 'feature_toggled', 30);
                
                // Weiterleitung
                wp_safe_redirect(admin_url('admin.php?page=container-block-designer'));
                exit;
            }
        }
        
        // Block löschen
        if (isset($_POST['delete_block']) && isset($_POST['block_id'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cbd_delete_block')) {
                wp_die('Sicherheitsprüfung fehlgeschlagen');
            }
            
            $block_id = intval($_POST['block_id']);
            
            $wpdb->delete(
                CBD_TABLE_BLOCKS,
                array('id' => $block_id),
                array('%d')
            );
            
            set_transient('cbd_admin_message', 'block_deleted', 30);
            
            wp_safe_redirect(admin_url('admin.php?page=container-block-designer'));
            exit;
        }
        
        // Block-Status ändern
        if (isset($_POST['toggle_status']) && isset($_POST['block_id'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cbd_toggle_status')) {
                wp_die('Sicherheitsprüfung fehlgeschlagen');
            }
            
            $block_id = intval($_POST['block_id']);
            
            $block = $wpdb->get_row($wpdb->prepare(
                "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
                $block_id
            ));
            
            if ($block) {
                $new_status = $block->status === 'active' ? 'inactive' : 'active';
                
                $update_data = array(
                    'status' => $new_status
                );
                
                // Prüfe welche Spalte existiert
                $columns = $wpdb->get_col("SHOW COLUMNS FROM " . CBD_TABLE_BLOCKS);
                if (in_array('updated_at', $columns)) {
                    $update_data['updated_at'] = current_time('mysql');
                }
                
                $wpdb->update(
                    CBD_TABLE_BLOCKS,
                    $update_data,
                    array('id' => $block_id)
                );
                
                set_transient('cbd_admin_message', 'status_toggled', 30);
                
                wp_safe_redirect(admin_url('admin.php?page=container-block-designer'));
                exit;
            }
        }
    }
    
    /**
     * Admin-Notices anzeigen
     */
    public function admin_notices() {
        $message = get_transient('cbd_admin_message');
        
        if (!$message) {
            return;
        }
        
        delete_transient('cbd_admin_message');
        
        $messages = array(
            'feature_toggled' => __('Feature wurde erfolgreich geändert.', 'container-block-designer'),
            'block_deleted' => __('Block wurde erfolgreich gelöscht.', 'container-block-designer'),
            'status_toggled' => __('Block-Status wurde erfolgreich geändert.', 'container-block-designer'),
            'block_saved' => __('Block wurde erfolgreich gespeichert.', 'container-block-designer'),
            'settings_saved' => __('Einstellungen wurden erfolgreich gespeichert.', 'container-block-designer')
        );
        
        $text = isset($messages[$message]) ? $messages[$message] : __('Operation erfolgreich.', 'container-block-designer');
        
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }
    
    /**
     * Neue Block-Seite rendern
     */
    public function render_new_block_page() {
        $file_path = CBD_PLUGIN_DIR . 'admin/new-block.php';
        
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>' . __('Neuer Block', 'container-block-designer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Admin-Datei nicht gefunden: admin/new-block.php', 'container-block-designer') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Import/Export-Seite rendern
     */
    public function render_import_export_page() {
        $file_path = CBD_PLUGIN_DIR . 'admin/import-export.php';
        
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>' . __('Import/Export', 'container-block-designer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Admin-Datei nicht gefunden: admin/import-export.php', 'container-block-designer') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * AJAX: Block speichern
     */
    public function ajax_save_block() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $styles = wp_unslash($_POST['styles'] ?? '{}');
        $features = wp_unslash($_POST['features'] ?? '{}');
        
        if (empty($title)) {
            wp_send_json_error(__('Titel ist erforderlich', 'container-block-designer'));
        }
        
        $data = array(
            'title' => $title,
            'description' => $description,
            'styles' => $styles,
            'features' => $features
        );
        
        // Prüfe welche Spalte existiert
        $columns = $wpdb->get_col("SHOW COLUMNS FROM " . CBD_TABLE_BLOCKS);
        if (in_array('updated_at', $columns)) {
            $data['updated_at'] = current_time('mysql');
        }
        
        if ($block_id) {
            // Bestehenden Block aktualisieren
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                $data,
                array('id' => $block_id)
            );
        } else {
            // Neuen Block erstellen
            $data['status'] = 'active';
            if (in_array('created_at', $columns)) {
                $data['created_at'] = current_time('mysql');
            }
            
            $result = $wpdb->insert(
                CBD_TABLE_BLOCKS,
                $data
            );
            
            $block_id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Block erfolgreich gespeichert', 'container-block-designer'),
                'block_id' => $block_id
            ));
        } else {
            wp_send_json_error(__('Fehler beim Speichern des Blocks', 'container-block-designer'));
        }
    }
    
    /**
     * AJAX: Block löschen
     */
    public function ajax_delete_block() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(__('Ungültige Block-ID', 'container-block-designer'));
        }
        
        $result = $wpdb->delete(
            CBD_TABLE_BLOCKS,
            array('id' => $block_id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success(__('Block erfolgreich gelöscht', 'container-block-designer'));
        } else {
            wp_send_json_error(__('Fehler beim Löschen des Blocks', 'container-block-designer'));
        }
    }
    
    /**
     * AJAX: Block-Status umschalten
     */
    public function ajax_toggle_status() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if (!$block_id) {
            wp_send_json_error(__('Ungültige Block-ID', 'container-block-designer'));
        }
        
        // Aktuellen Status abrufen
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ));
        
        if (!$current_status) {
            wp_send_json_error(__('Block nicht gefunden', 'container-block-designer'));
        }
        
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        
        $update_data = array(
            'status' => $new_status
        );
        
        // Prüfe welche Spalte existiert
        $columns = $wpdb->get_col("SHOW COLUMNS FROM " . CBD_TABLE_BLOCKS);
        if (in_array('updated_at', $columns)) {
            $update_data['updated_at'] = current_time('mysql');
        }
        
        $result = $wpdb->update(
            CBD_TABLE_BLOCKS,
            $update_data,
            array('id' => $block_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Status erfolgreich geändert', 'container-block-designer'),
                'new_status' => $new_status
            ));
        } else {
            wp_send_json_error(__('Fehler beim Ändern des Status', 'container-block-designer'));
        }
    }
    
    /**
     * Plugin-Action-Links hinzufügen
     */
    public function add_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=container-block-designer') . '">' . __('Einstellungen', 'container-block-designer') . '</a>',
            '<a href="' . admin_url('admin.php?page=cbd-new-block') . '">' . __('Neuer Block', 'container-block-designer') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
}

// Initialisierung nur im Admin-Bereich
if (is_admin()) {
    CBD_Admin::get_instance();
}