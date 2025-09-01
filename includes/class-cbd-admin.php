<?php
/**
 * Container Block Designer - Admin-Klasse
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.1
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
        // Admin-Menü hinzufügen
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin-Styles und Scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX-Handler für Admin-Aktionen
        add_action('wp_ajax_cbd_save_block', array($this, 'ajax_save_block'));
        add_action('wp_ajax_cbd_delete_block', array($this, 'ajax_delete_block'));
        add_action('wp_ajax_cbd_toggle_status', array($this, 'ajax_toggle_status'));
        
        // Plugin-Action-Links
        add_filter('plugin_action_links_' . CBD_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        // Hauptmenü
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Blocks', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_main_page'),
            'dashicons-layout',
            30
        );
        
        // Übersicht (gleiche Seite wie Hauptmenü)
        add_submenu_page(
            'container-block-designer',
            __('Alle Blöcke', 'container-block-designer'),
            __('Alle Blöcke', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_main_page')
        );
        
        // Neuer Block
        add_submenu_page(
            'container-block-designer',
            __('Neuer Block', 'container-block-designer'),
            __('Neuer Block', 'container-block-designer'),
            'manage_options',
            'cbd-new-block',
            array($this, 'render_new_block_page')
        );
        
        // Block bearbeiten (versteckt, nur über direkten Link erreichbar)
        add_submenu_page(
            null, // Kein Parent-Menü (versteckt)
            __('Block bearbeiten', 'container-block-designer'),
            __('Block bearbeiten', 'container-block-designer'),
            'manage_options',
            'cbd-edit-block',
            array($this, 'render_edit_block_page')
        );
    }
    
    /**
     * Hauptseite rendern
     */
    public function render_main_page() {
        // Prüfe ob Datei existiert
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
     * Block bearbeiten Seite rendern
     */
    public function render_edit_block_page() {
        $file_path = CBD_PLUGIN_DIR . 'admin/edit-block.php';
        
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>' . __('Block bearbeiten', 'container-block-designer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Admin-Datei nicht gefunden: admin/edit-block.php', 'container-block-designer') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Admin-Assets laden
     */
    public function enqueue_admin_assets($hook) {
        // Nur auf unseren Plugin-Seiten laden
        if (strpos($hook, 'container-block-designer') === false && 
            strpos($hook, 'cbd-') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CBD_VERSION
        );
        
        // Admin JavaScript
        wp_enqueue_script(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            CBD_VERSION,
            true
        );
        
        // Color Picker CSS
        wp_enqueue_style('wp-color-picker');
        
        // Lokalisierung für JavaScript
        wp_localize_script('cbd-admin', 'cbdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd-admin-nonce'),
            'strings' => array(
                'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
                'saving' => __('Speichern...', 'container-block-designer'),
                'saved' => __('Gespeichert!', 'container-block-designer'),
                'error' => __('Ein Fehler ist aufgetreten.', 'container-block-designer'),
                'active' => __('Aktiv', 'container-block-designer'),
                'inactive' => __('Inaktiv', 'container-block-designer'),
                'featureEnabled' => __('Feature aktiviert', 'container-block-designer'),
                'featureDisabled' => __('Feature deaktiviert', 'container-block-designer')
            )
        ));
    }
    
    /**
     * AJAX: Block speichern
     */
    public function ajax_save_block() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin-nonce', 'nonce', false)) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'config' => wp_json_encode($_POST['config']),
            'styles' => wp_json_encode($_POST['styles']),
            'features' => wp_json_encode($_POST['features']),
            'status' => sanitize_text_field($_POST['status']),
            'updated' => current_time('mysql')
        );
        
        if ($block_id) {
            // Update
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                $data,
                array('id' => $block_id)
            );
        } else {
            // Insert
            $data['created'] = current_time('mysql');
            $result = $wpdb->insert(CBD_TABLE_BLOCKS, $data);
            $block_id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Block erfolgreich gespeichert', 'container-block-designer'),
                'block_id' => $block_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Fehler beim Speichern des Blocks', 'container-block-designer')
            ));
        }
    }
    
    /**
     * AJAX: Block löschen
     */
    public function ajax_delete_block() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin-nonce', 'nonce', false)) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if ($block_id) {
            $result = $wpdb->delete(
                CBD_TABLE_BLOCKS,
                array('id' => $block_id)
            );
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Block erfolgreich gelöscht', 'container-block-designer')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Fehler beim Löschen des Blocks', 'container-block-designer')
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => __('Ungültige Block-ID', 'container-block-designer')
            ));
        }
    }
    
    /**
     * AJAX: Status umschalten
     */
    public function ajax_toggle_status() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('cbd-admin-nonce', 'nonce', false)) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        global $wpdb;
        
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if ($block_id) {
            // Aktuellen Status abrufen
            $current_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
                $block_id
            ));
            
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';
            
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                array(
                    'status' => $new_status,
                    'updated' => current_time('mysql')
                ),
                array('id' => $block_id)
            );
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => __('Status erfolgreich geändert', 'container-block-designer'),
                    'new_status' => $new_status
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Fehler beim Ändern des Status', 'container-block-designer')
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => __('Ungültige Block-ID', 'container-block-designer')
            ));
        }
    }
    
    /**
     * Plugin-Action-Links hinzufügen
     */
    public function add_plugin_action_links($links) {
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