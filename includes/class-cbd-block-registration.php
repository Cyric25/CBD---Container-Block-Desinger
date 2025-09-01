<?php
/**
 * Container Block Designer - Block Registration Class
 * Version: 3.0.2 - Fixes 404 error and block validation
 * 
 * Datei: includes/class-block-registration.php
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Block_Registration {
    
    /**
     * Singleton Instance
     */
    private static $instance = null;
    
    /**
     * Debug Mode
     */
    private $debug_mode = false;
    
    /**
     * Get Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->init_hooks();
    }
    
    /**
     * Initialize all hooks
     */
    private function init_hooks() {
        // Entferne ALLE alten Script-Registrierungen
        add_action('init', array($this, 'cleanup_old_registrations'), 1);
        
        // Registriere den Block korrekt
        add_action('init', array($this, 'register_block_type'), 10);
        
        // Enqueue Block Editor Assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'), 10);
        
        // Enqueue Frontend Assets
        add_action('enqueue_block_assets', array($this, 'enqueue_frontend_assets'), 10);
        
        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Cleanup old registrations to prevent 404 errors
     */
    public function cleanup_old_registrations() {
        // Liste aller alten Script-Handles die entfernt werden müssen
        $old_scripts = array(
            'cbd-container-block',        // Das verursacht den 404 Fehler!
            'cbd-container-block-fixed',
            'cbd-block-registration',
            'container-block-designer-block'
        );
        
        // Entferne alle alten Script-Registrierungen
        foreach ($old_scripts as $handle) {
            if (wp_script_is($handle, 'registered')) {
                wp_deregister_script($handle);
            }
        }
        
        // Entferne alte Style-Registrierungen
        $old_styles = array(
            'cbd-container-block-css',
            'cbd-container-block-editor-css',
            'container-block-designer-editor'
        );
        
        foreach ($old_styles as $handle) {
            if (wp_style_is($handle, 'registered')) {
                wp_deregister_style($handle);
            }
        }
        
        if ($this->debug_mode) {
            error_log('CBD: Old scripts and styles cleaned up');
        }
    }
    
    /**
     * Register the block type properly
     */
    public function register_block_type() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Registriere Block Editor Script
        wp_register_script(
            'cbd-block-editor-script',
            CBD_PLUGIN_URL . 'assets/js/block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
            CBD_VERSION,
            true
        );
        
        // Registriere Editor Styles
        wp_register_style(
            'cbd-block-editor-style',
            CBD_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
        
        // Registriere Frontend Styles
        wp_register_style(
            'cbd-block-frontend-style',
            CBD_PLUGIN_URL . 'assets/css/block-style.css',
            array(),
            CBD_VERSION
        );
        
        // Registriere den Block-Typ mit korrekten Attributen
        register_block_type('container-block-designer/container', array(
            'editor_script' => 'cbd-block-editor-script',
            'editor_style' => 'cbd-block-editor-style',
            'style' => 'cbd-block-frontend-style',
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'selectedBlock' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'customClasses' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'blockConfig' => array(
                    'type' => 'object',
                    'default' => array()
                ),
                'blockFeatures' => array(
                    'type' => 'object',
                    'default' => array()
                ),
                'align' => array(
                    'type' => 'string',
                    'enum' => array('wide', 'full', ''),
                    'default' => ''
                )
            ),
            'supports' => array(
                'align' => array('wide', 'full'),
                'html' => false,
                'className' => true,
                'customClassName' => true
            )
        ));
        
        if ($this->debug_mode) {
            error_log('CBD: Block type registered successfully');
        }
    }
    
    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets() {
        // Stelle sicher, dass wir im Block Editor sind
        $screen = get_current_screen();
        if (!$screen || !$screen->is_block_editor()) {
            return;
        }
        
        // Lokalisiere Script mit Block-Daten
        wp_localize_script('cbd-block-editor-script', 'cbdBlockData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('cbd/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'blocks' => $this->get_available_blocks(),
            'pluginUrl' => CBD_PLUGIN_URL,
            'version' => CBD_VERSION,
            'debug' => $this->debug_mode,
            'i18n' => array(
                'blockTitle' => __('Container Block', 'container-block-designer'),
                'blockDescription' => __('Ein anpassbarer Container-Block mit erweiterten Features', 'container-block-designer'),
                'selectBlock' => __('Design auswählen', 'container-block-designer'),
                'noBlocks' => __('Keine Designs verfügbar', 'container-block-designer'),
                'addContent' => __('Inhalt hinzufügen', 'container-block-designer'),
                'settings' => __('Einstellungen', 'container-block-designer'),
                'design' => __('Design', 'container-block-designer'),
                'features' => __('Features', 'container-block-designer'),
                'customClasses' => __('Eigene CSS-Klassen', 'container-block-designer'),
                'iconEnabled' => __('Icon anzeigen', 'container-block-designer'),
                'collapseEnabled' => __('Auf-/Zuklappen aktivieren', 'container-block-designer'),
                'numberingEnabled' => __('Nummerierung aktivieren', 'container-block-designer'),
                'copyTextEnabled' => __('Text kopieren aktivieren', 'container-block-designer'),
                'screenshotEnabled' => __('Screenshot aktivieren', 'container-block-designer')
            )
        ));
        
        // Stelle sicher, dass das Script geladen wird
        wp_enqueue_script('cbd-block-editor-script');
        wp_enqueue_style('cbd-block-editor-style');
        
        if ($this->debug_mode) {
            error_log('CBD: Editor assets enqueued');
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Frontend styles werden automatisch durch register_block_type geladen
        // Zusätzliche Frontend-Scripts hier laden falls nötig
        
        if (has_block('container-block-designer/container')) {
            wp_enqueue_script(
                'cbd-frontend-script',
                CBD_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                CBD_VERSION,
                true
            );
            
            wp_localize_script('cbd-frontend-script', 'cbdFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cbd-frontend')
            ));
        }
    }
    
    /**
     * Get available blocks from database
     */
    private function get_available_blocks() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        // Prüfe ob Tabelle existiert
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $blocks = $wpdb->get_results(
            "SELECT id, name, slug, description, config, features, styles 
             FROM {$table_name} 
             WHERE status = 'active' 
             ORDER BY name ASC",
            ARRAY_A
        );
        
        if (!$blocks) {
            return array();
        }
        
        // Dekodiere JSON-Felder
        foreach ($blocks as &$block) {
            $block['config'] = json_decode($block['config'], true) ?: array();
            $block['features'] = json_decode($block['features'], true) ?: array();
            $block['styles'] = json_decode($block['styles'], true) ?: array();
        }
        
        return $blocks;
    }
    
    /**
     * Render block callback
     */
    public function render_block($attributes, $content) {
        $selected_block = $attributes['selectedBlock'] ?? '';
        $custom_classes = $attributes['customClasses'] ?? '';
        $align = $attributes['align'] ?? '';
        
        // Build class string
        $classes = array(
            'wp-block-container-block-designer-container',
            'cbd-container'
        );
        
        if ($selected_block) {
            $classes[] = 'cbd-block-' . sanitize_html_class($selected_block);
        }
        
        if ($custom_classes) {
            $classes[] = esc_attr($custom_classes);
        }
        
        if ($align) {
            $classes[] = 'align' . $align;
        }
        
        $class_string = implode(' ', $classes);
        
        // Render the block
        $output = sprintf(
            '<div class="%s"><div class="cbd-content">%s</div></div>',
            esc_attr($class_string),
            $content
        );
        
        return $output;
    }
    
    /**
     * Register REST routes
     */
    public function register_rest_routes() {
        register_rest_route('cbd/v1', '/blocks', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_blocks_rest'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * REST API callback for getting blocks
     */
    public function get_blocks_rest($request) {
        return rest_ensure_response($this->get_available_blocks());
    }
    
    /**
     * Debug method
     */
    public function debug_info() {
        if (!$this->debug_mode) {
            return;
        }
        
        echo '<script>';
        echo 'console.log("CBD Block Registration Debug:");';
        echo 'console.log("Version: ' . CBD_VERSION . '");';
        echo 'console.log("Plugin URL: ' . CBD_PLUGIN_URL . '");';
        echo 'console.log("Available Blocks:", ' . json_encode($this->get_available_blocks()) . ');';
        echo '</script>';
    }
}

// Initialize
CBD_Block_Registration::get_instance();