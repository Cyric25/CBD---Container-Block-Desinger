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
        $capability = 'manage_options';
        
        // Main menu page
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Blocks', 'container-block-designer'),
            $capability,
            'cbd-blocks',
            array($this, 'route_request'),
            'dashicons-layout',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'cbd-blocks',
            __('Alle Blöcke', 'container-block-designer'),
            __('Alle Blöcke', 'container-block-designer'),
            $capability,
            'cbd-blocks',
            array($this, 'route_request')
        );
        
        add_submenu_page(
            'cbd-blocks',
            __('Block hinzufügen', 'container-block-designer'),
            __('Block hinzufügen', 'container-block-designer'),
            $capability,
            'cbd-new-block',
            array($this, 'route_request')
        );
        
        add_submenu_page(
            null, // Hidden from menu
            __('Block bearbeiten', 'container-block-designer'),
            __('Block bearbeiten', 'container-block-designer'),
            $capability,
            'cbd-edit-block',
            array($this, 'route_request')
        );
        
        add_submenu_page(
            'cbd-blocks',
            __('Import/Export', 'container-block-designer'),
            __('Import/Export', 'container-block-designer'),
            $capability,
            'cbd-import-export',
            array($this, 'route_request')
        );
        
        add_submenu_page(
            'cbd-blocks',
            __('Einstellungen', 'container-block-designer'),
            __('Einstellungen', 'container-block-designer'),
            $capability,
            'cbd-settings',
            array($this, 'route_request')
        );
    }
    
    /**
     * Route admin request to appropriate controller
     */
    public function route_request() {
        $page = sanitize_text_field($_GET['page'] ?? '');
        
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
        }
        
        $controller_class = self::CONTROLLER_NAMESPACE . $routes[$page];
        
        // Load controller if not autoloaded
        $controller_file = $this->get_controller_file($routes[$page]);
        if (file_exists($controller_file)) {
            require_once $controller_file;
        }
        
        if (!class_exists($controller_class)) {
            wp_die(sprintf(__('Controller %s nicht gefunden.', 'container-block-designer'), $controller_class));
        }
        
        // Instantiate and render
        $controller = new $controller_class();
        
        if (!method_exists($controller, 'render')) {
            wp_die(sprintf(__('Controller %s hat keine render() Methode.', 'container-block-designer'), $controller_class));
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
        $action = sanitize_text_field($_POST['cbd_action'] ?? '');
        
        if (empty($action)) {
            wp_send_json_error(__('Keine Aktion angegeben.', 'container-block-designer'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cbd_admin')) {
            wp_send_json_error(__('Sicherheitsüberprüfung fehlgeschlagen.', 'container-block-designer'));
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
        $styles = $_POST['styles'] ?? '{}';
        $features = $_POST['features'] ?? '{}';
        $content = sanitize_text_field($_POST['content'] ?? 'Beispielinhalt');
        
        // Generate preview HTML
        $preview_html = $this->generate_preview_html($styles, $features, $content);
        
        wp_send_json_success(array(
            'html' => $preview_html,
            'message' => __('Vorschau aktualisiert', 'container-block-designer')
        ));
    }
    
    /**
     * Generate preview HTML
     */
    private function generate_preview_html($styles_json, $features_json, $content) {
        $styles = json_decode($styles_json, true) ?: array();
        $features = json_decode($features_json, true) ?: array();
        
        // Use unified frontend renderer for consistent preview
        require_once CBD_PLUGIN_DIR . 'includes/class-unified-frontend-renderer.php';
        
        $attributes = array(
            'selectedBlock' => 'preview',
            'features' => $features
        );
        
        // Mock block data for preview
        $mock_block_data = array(
            'name' => 'preview',
            'styles' => $styles_json,
            'features' => $features_json,
            'config' => '{}'
        );
        
        // Generate preview using renderer logic
        // This would need to be adapted to work with the renderer
        return '<div class="cbd-preview-container">' . $content . '</div>';
    }
    
    /**
     * Handle block duplication
     */
    private function handle_duplicate_block() {
        $block_id = intval($_POST['block_id'] ?? 0);
        
        if ($block_id <= 0) {
            wp_send_json_error(__('Ungültige Block-ID.', 'container-block-designer'));
        }
        
        $new_block_id = \CBD_Database::duplicate_block($block_id);
        
        if ($new_block_id) {
            wp_send_json_success(array(
                'message' => __('Block wurde dupliziert.', 'container-block-designer'),
                'redirect' => admin_url('admin.php?page=cbd-edit-block&id=' . $new_block_id)
            ));
        } else {
            wp_send_json_error(__('Fehler beim Duplizieren des Blocks.', 'container-block-designer'));
        }
    }
    
    /**
     * Handle block status toggle
     */
    private function handle_toggle_block_status() {
        $block_id = intval($_POST['block_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        
        if ($block_id <= 0) {
            wp_send_json_error(__('Ungültige Block-ID.', 'container-block-designer'));
        }
        
        if (!in_array($new_status, array('active', 'inactive'))) {
            wp_send_json_error(__('Ungültiger Status.', 'container-block-designer'));
        }
        
        $result = \CBD_Database::update_block_status($block_id, $new_status);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Block wurde %s.', 'container-block-designer'),
                    $new_status === 'active' ? __('aktiviert', 'container-block-designer') : __('deaktiviert', 'container-block-designer')
                )
            ));
        } else {
            wp_send_json_error(__('Fehler beim Aktualisieren des Block-Status.', 'container-block-designer'));
        }
    }
}