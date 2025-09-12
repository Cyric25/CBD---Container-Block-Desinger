<?php
/**
 * Container Block Designer - Simplified Admin Wrapper
 * Version: 2.6.0 - Clean wrapper that enables modern AdminRouter
 * 
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simplified Admin Class for Container Block Designer
 * This replaces the complex CBD_Admin class with a clean wrapper
 * that properly initializes the modern AdminRouter system
 */
class CBD_Admin_Simple {
    
    private static $instance = null;
    private $admin_router = null;
    
    /**
     * Get Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize admin system
     */
    private function __construct() {
        // Initialize the modern admin router system
        $this->init_admin_router();
        
        // Admin-only hooks
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        // Essential AJAX handlers for backward compatibility
        add_action('wp_ajax_cbd_live_preview', array($this, 'ajax_live_preview'));
        add_action('wp_ajax_cbd_get_block_data', array($this, 'ajax_get_block_data'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . CBD_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }
    
    /**
     * Initialize the modern admin router system
     */
    private function init_admin_router() {
        // Get from service container if available
        try {
            $container = CBD_Service_Container::get_instance();
            if ($container->has('admin_router')) {
                $this->admin_router = $container->get('admin_router');
                return;
            }
        } catch (Exception $e) {
            // Service container not available
        }
        
        // Fallback: Create AdminRouter directly
        if (!class_exists('\\ContainerBlockDesigner\\Admin\\AdminRouter')) {
            require_once CBD_PLUGIN_DIR . 'includes/Admin/class-admin-router.php';
        }
        
        if (class_exists('\\ContainerBlockDesigner\\Admin\\AdminRouter')) {
            $this->admin_router = new \ContainerBlockDesigner\Admin\AdminRouter();
            
            // Register AJAX handler for the router
            add_action('wp_ajax_cbd_admin_action', array($this->admin_router, 'handle_ajax_request'));
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'cbd-') === false && strpos($hook, 'container') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'cbd-admin-css',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CBD_VERSION . '-admin-' . time()
        );
        
        // JavaScript
        wp_enqueue_script(
            'cbd-admin-js',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CBD_VERSION . '-admin-' . time(),
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('cbd-admin-js', 'cbdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_admin'),
            'strings' => array(
                'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
                'blockDeleted' => __('Block wurde gelöscht.', 'container-block-designer'),
                'blockUpdated' => __('Block wurde aktualisiert.', 'container-block-designer'),
                'error' => __('Ein Fehler ist aufgetreten.', 'container-block-designer')
            )
        ));
    }
    
    /**
     * AJAX: Live Preview (backward compatibility)
     */
    public function ajax_live_preview() {
        check_ajax_referer('cbd_admin', 'nonce');
        
        $styles = isset($_POST['styles']) ? $_POST['styles'] : '{}';
        $features = isset($_POST['features']) ? $_POST['features'] : '{}';
        $content = isset($_POST['content']) ? sanitize_text_field($_POST['content']) : 'Sample content';
        
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
     * AJAX: Get Block Data (backward compatibility)
     */
    public function ajax_get_block_data() {
        check_ajax_referer('cbd_admin', 'nonce');
        
        $block_id = intval($_POST['block_id'] ?? 0);
        
        if (!$block_id) {
            wp_send_json_error(__('Invalid block ID', 'container-block-designer'));
        }
        
        $block = CBD_Database::get_block($block_id);
        
        if (!$block) {
            wp_send_json_error(__('Block not found', 'container-block-designer'));
        }
        
        wp_send_json_success($block);
    }
    
    /**
     * Add plugin action links
     */
    public function add_action_links($links) {
        $admin_url = admin_url('admin.php?page=cbd-blocks');
        $action_links = array(
            'settings' => '<a href="' . $admin_url . '">' . __('Verwalten', 'container-block-designer') . '</a>',
        );
        
        return array_merge($action_links, $links);
    }
}