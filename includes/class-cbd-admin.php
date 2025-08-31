<?php
/**
 * Container Block Designer - Admin-Klasse
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-Bereich Verwaltung
 */
class CBD_Admin {
    
    /**
     * Admin-Notices
     */
    private $notices = array();
    
    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        
        // Aktivierungs-Notice
        if (isset($_GET['cbd_activated']) && $_GET['cbd_activated'] == '1') {
            $this->add_notice(__('Container Block Designer wurde erfolgreich aktiviert!', 'container-block-designer'), 'success');
        }
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
            array($this, 'render_blocks_list_page'),
            'dashicons-layout',
            30
        );
        
        // Untermenüs
        add_submenu_page(
            'container-block-designer',
            __('Alle Blocks', 'container-block-designer'),
            __('Alle Blocks', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_blocks_list_page')
        );
        
        add_submenu_page(
            'container-block-designer',
            __('Neuer Block', 'container-block-designer'),
            __('Neuer Block', 'container-block-designer'),
            'manage_options',
            'cbd-new-block',
            array($this, 'render_new_block_page')
        );
        
        add_submenu_page(
            null, // Versteckt
            __('Block bearbeiten', 'container-block-designer'),
            __('Block bearbeiten', 'container-block-designer'),
            'manage_options',
            'cbd-edit-block',
            array($this, 'render_edit_block_page')
        );
        
        add_submenu_page(
            'container-block-designer',
            __('Einstellungen', 'container-block-designer'),
            __('Einstellungen', 'container-block-designer'),
            'manage_options',
            'cbd-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Admin-Aktionen verarbeiten
     */
    public function handle_admin_actions() {
        // Nur auf unseren Seiten
        if (!isset($_GET['page']) || strpos($_GET['page'], 'cbd-') === false && $_GET['page'] !== 'container-block-designer') {
            return;
        }
        
        // Block löschen
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['block_id'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'cbd_delete_block_' . $_GET['block_id'])) {
                $result = CBD_Database::delete_block(intval($_GET['block_id']));
                if ($result) {
                    $this->add_notice(__('Block erfolgreich gelöscht.', 'container-block-designer'), 'success');
                } else {
                    $this->add_notice(__('Fehler beim Löschen des Blocks.', 'container-block-designer'), 'error');
                }
                wp_redirect(admin_url('admin.php?page=container-block-designer'));
                exit;
            }
        }
        
        // Block duplizieren
        if (isset($_GET['action']) && $_GET['action'] === 'duplicate' && isset($_GET['block_id'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'cbd_duplicate_block_' . $_GET['block_id'])) {
                $new_id = CBD_Database::duplicate_block(intval($_GET['block_id']));
                if ($new_id) {
                    $this->add_notice(__('Block erfolgreich dupliziert.', 'container-block-designer'), 'success');
                    wp_redirect(admin_url('admin.php?page=cbd-edit-block&id=' . $new_id));
                    exit;
                } else {
                    $this->add_notice(__('Fehler beim Duplizieren des Blocks.', 'container-block-designer'), 'error');
                }
            }
        }
    }
    
    /**
     * Blocks-Liste rendern
     */
    public function render_blocks_list_page() {
        include CBD_PLUGIN_DIR . 'admin/blocks-list.php';
    }
    
    /**
     * Neuer Block Seite rendern
     */
    public function render_new_block_page() {
        include CBD_PLUGIN_DIR . 'admin/new-block.php';
    }
    
    /**
     * Block bearbeiten Seite rendern
     */
    public function render_edit_block_page() {
        include CBD_PLUGIN_DIR . 'admin/edit-block.php';
    }
    
    /**
     * Einstellungen Seite rendern
     */
    public function render_settings_page() {
        include CBD_PLUGIN_DIR . 'admin/settings.php';
    }
    
    /**
     * Notice hinzufügen
     */
    public function add_notice($message, $type = 'info') {
        $this->notices[] = array(
            'message' => $message,
            'type' => $type,
        );
    }
    
    /**
     * Admin-Notices anzeigen
     */
    public function display_admin_notices() {
        // Gespeicherte Notices aus Transient
        $saved_notices = get_transient('cbd_admin_notices');
        if ($saved_notices) {
            foreach ($saved_notices as $notice) {
                $this->notices[] = $notice;
            }
            delete_transient('cbd_admin_notices');
        }
        
        // Notices ausgeben
        foreach ($this->notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }
    
    /**
     * Notice für späteren Aufruf speichern
     */
    public static function save_notice($message, $type = 'info') {
        $notices = get_transient('cbd_admin_notices') ?: array();
        $notices[] = array(
            'message' => $message,
            'type' => $type,
        );
        set_transient('cbd_admin_notices', $notices, 30);
    }
}