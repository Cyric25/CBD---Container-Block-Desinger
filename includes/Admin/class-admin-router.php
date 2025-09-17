<?php
/**
 * Container Block Designer - Admin Router
 * Routes admin requests to appropriate controllers
 * 
 * @package ContainerBlockDesigner\Admin
 * @since 2.6.0
 */

namespace ContainerBlockDesigner\Admin;

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Router Class
 * Handles routing of admin pages to appropriate controllers
 */
class AdminRouter {
    
    /**
     * Controller namespace
     */
    const CONTROLLER_NAMESPACE = 'ContainerBlockDesigner\\Admin\\Controllers\\';
    
    /**
     * Initialize the router
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_pages'));
    }
    
    /**
     * Register admin menu pages
     */
    public function register_admin_pages() {
        // Berechtigungen für verschiedene Benutzertypen
        $user = wp_get_current_user();
        $is_block_redakteur = $user && in_array('block_redakteur', $user->roles);

        if ($is_block_redakteur) {
            // Block-Redakteure: Read-Only Zugriff
            $block_capability = 'cbd_edit_blocks';  // Können Blocks sehen
            $admin_capability = 'manage_options';   // Können KEINE Admin-Funktionen
        } else {
            // Alle anderen: Vollzugriff
            $block_capability = 'cbd_edit_blocks';  // Container-Blocks verwenden
            $admin_capability = 'cbd_admin_blocks'; // Container-Block Admin-Funktionen

            // Fallback für Kompatibilität
            if (!current_user_can($admin_capability)) {
                $admin_capability = 'manage_options';
            }
            if (!current_user_can($block_capability)) {
                $block_capability = 'edit_posts';
            }
        }
        
        // Main menu page - für Mitarbeiter zugänglich
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Blocks', 'container-block-designer'),
            $block_capability,
            'cbd-blocks',
            array($this, 'route_request'),
            'dashicons-layout',
            30
        );

        // Block-Verwaltung - für Mitarbeiter zugänglich
        add_submenu_page(
            'cbd-blocks',
            __('Alle Blöcke', 'container-block-designer'),
            __('Alle Blöcke', 'container-block-designer'),
            $block_capability,
            'cbd-blocks',
            array($this, 'route_request')
        );

        // Block hinzufügen - NUR für Nicht-Block-Redakteure
        if (!$is_block_redakteur) {
            add_submenu_page(
                'cbd-blocks',
                __('Block hinzufügen', 'container-block-designer'),
                __('Block hinzufügen', 'container-block-designer'),
                $admin_capability,
                'cbd-new-block',
                array($this, 'route_request')
            );

            // Hidden page für Block-Bearbeitung - NUR für Nicht-Block-Redakteure
            add_submenu_page(
                '', // Empty string instead of null for hidden pages
                __('Block bearbeiten', 'container-block-designer'),
                __('Block bearbeiten', 'container-block-designer'),
                $admin_capability,
                'cbd-edit-block',
                array($this, 'route_request')
            );
        }

        // Admin-only Bereiche - NUR für Nicht-Block-Redakteure
        if (!$is_block_redakteur) {
            add_submenu_page(
                'cbd-blocks',
                __('Import/Export', 'container-block-designer'),
                __('Import/Export', 'container-block-designer'),
                $admin_capability,
                'cbd-import-export',
                array($this, 'route_request')
            );

            add_submenu_page(
                'cbd-blocks',
                __('Einstellungen', 'container-block-designer'),
                __('Einstellungen', 'container-block-designer'),
                $admin_capability,
                'cbd-settings',
                array($this, 'route_request')
            );
        }
    }
    
    /**
     * Route admin request to appropriate controller
     */
    public function route_request() {
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        
        if (empty($page)) {
            wp_die(__('Keine Seite angegeben.', 'container-block-designer'));
            return;
        }
        
        // Map pages to controllers
        $routes = array(
            'cbd-blocks' => 'BlocksListController',
            'cbd-new-block' => 'BlockEditorController',
            'cbd-edit-block' => 'BlockEditorController',
            'cbd-import-export' => 'ImportExportController',
            'cbd-settings' => 'SettingsController'
        );
        
        if (!isset($routes[$page])) {
            wp_die(__('Unbekannte Seite.', 'container-block-designer'));
            return;
        }
        
        $controller_class = self::CONTROLLER_NAMESPACE . $routes[$page];
        
        // Load controller if not autoloaded
        $controller_file = $this->get_controller_file($routes[$page]);
        if (file_exists($controller_file)) {
            require_once $controller_file;
        }
        
        if (!class_exists($controller_class)) {
            // Try to load with fallback path
            $fallback_file = CBD_PLUGIN_DIR . 'admin/' . str_replace('_', '-', strtolower($page)) . '.php';
            if (file_exists($fallback_file)) {
                // Use legacy template file
                require_once $fallback_file;
                return;
            }
            
            wp_die(sprintf(__('Controller %s nicht gefunden.', 'container-block-designer'), $controller_class));
            return;
        }
        
        // Instantiate and render
        $controller = new $controller_class();
        
        if (!method_exists($controller, 'render')) {
            wp_die(sprintf(__('Controller %s hat keine render() Methode.', 'container-block-designer'), $controller_class));
            return;
        }
        
        $controller->render();
    }
    
    /**
     * Get controller file path
     */
    private function get_controller_file($controller_name) {
        return CBD_PLUGIN_DIR . 'includes/Admin/Controllers/class-' . 
               strtolower(str_replace('Controller', '-controller', $controller_name)) . '.php';
    }
    
    /**
     * Handle AJAX requests for admin
     */
    public function handle_ajax_request() {
        // Check nonce first
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cbd_admin')) {
            wp_send_json_error(__('Sicherheitsüberprüfung fehlgeschlagen.', 'container-block-designer'));
            return;
        }
        
        $action = isset($_POST['cbd_action']) ? sanitize_text_field($_POST['cbd_action']) : '';
        
        if (empty($action)) {
            wp_send_json_error(__('Keine Aktion angegeben.', 'container-block-designer'));
            return;
        }
        
        // Route to appropriate handler
        switch ($action) {
            case 'preview_block':
                $this->handle_preview_block();
                break;
                
            case 'duplicate_block':
                $this->handle_duplicate_block();
                break;
                
            case 'toggle_block_status':
                $this->handle_toggle_block_status();
                break;
                
            default:
                wp_send_json_error(__('Unbekannte Aktion.', 'container-block-designer'));
        }
    }
    
    /**
     * Handle block preview AJAX request
     */
    private function handle_preview_block() {
        $styles = isset($_POST['styles']) ? $_POST['styles'] : '{}';
        $features = isset($_POST['features']) ? $_POST['features'] : '{}';
        $content = isset($_POST['content']) ? sanitize_text_field($_POST['content']) : '';
        
        // Generate preview HTML
        $preview_html = '<div class="cbd-block-preview">';
        $preview_html .= '<div class="cbd-block-content">' . $content . '</div>';
        $preview_html .= '</div>';
        
        wp_send_json_success(array(
            'html' => $preview_html,
            'styles' => $styles,
            'features' => $features
        ));
    }
    
    /**
     * Handle block duplication
     */
    private function handle_duplicate_block() {
        if (!isset($_POST['block_id'])) {
            wp_send_json_error(__('Block ID fehlt.', 'container-block-designer'));
            return;
        }
        
        $block_id = intval($_POST['block_id']);
        
        global $wpdb;
        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ));
        
        if (!$block) {
            wp_send_json_error(__('Block nicht gefunden.', 'container-block-designer'));
            return;
        }
        
        // Create duplicate with new name
        $new_name = $block->name . '-copy-' . time();
        $new_title = $block->title . ' (Kopie)';
        
        $result = $wpdb->insert(
            CBD_TABLE_BLOCKS,
            array(
                'name' => $new_name,
                'title' => $new_title,
                'description' => $block->description,
                'content' => $block->content,
                'styles' => $block->styles,
                'features' => $block->features,
                'config' => $block->config,
                'status' => 'inactive', // Start as inactive
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Block wurde dupliziert.', 'container-block-designer'),
                'new_id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error(__('Fehler beim Duplizieren des Blocks.', 'container-block-designer'));
        }
    }
    
    /**
     * Handle block status toggle
     */
    private function handle_toggle_block_status() {
        if (!isset($_POST['block_id'])) {
            wp_send_json_error(__('Block ID fehlt.', 'container-block-designer'));
            return;
        }
        
        $block_id = intval($_POST['block_id']);
        
        global $wpdb;
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ));
        
        if ($current_status === null) {
            wp_send_json_error(__('Block nicht gefunden.', 'container-block-designer'));
            return;
        }
        
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        
        $result = $wpdb->update(
            CBD_TABLE_BLOCKS,
            array('status' => $new_status, 'updated_at' => current_time('mysql')),
            array('id' => $block_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Block-Status wurde aktualisiert.', 'container-block-designer'),
                'new_status' => $new_status
            ));
        } else {
            wp_send_json_error(__('Fehler beim Aktualisieren des Block-Status.', 'container-block-designer'));
        }
    }
}