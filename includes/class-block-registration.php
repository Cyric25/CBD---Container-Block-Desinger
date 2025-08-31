<?php
/**
 * Container Block Designer - Block Registration Class (Updated)
 * 
 * Version 3.0.1 - Mit korrekten Asset-Pfaden
 * 
 * Diese Datei ersetzt: includes/class-block-registration.php
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
        // Enable debug mode if WP_DEBUG is on
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize all hooks
     */
    private function init_hooks() {
        // Server-side block registration (PRIMARY METHOD)
        add_action('init', array($this, 'register_block_server_side'), 5);
        
        // Enqueue block assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_assets'), 5);
        
        // Enqueue frontend styles
        add_action('enqueue_block_assets', array($this, 'enqueue_frontend_block_assets'), 5);
        
        // Inline registration as fallback
        add_action('admin_footer', array($this, 'inline_block_registration'), 999);
        
        // Add debug info if needed
        if ($this->debug_mode) {
            add_action('admin_notices', array($this, 'show_debug_info'));
        }
        
        // REST API endpoint for block data
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Dequeue old container-block.js if it exists
        add_action('enqueue_block_editor_assets', array($this, 'dequeue_old_scripts'), 20);
    }
    
    /**
     * Dequeue old scripts that cause conflicts
     */
    public function dequeue_old_scripts() {
        // Remove old container-block.js if it's enqueued
        if (wp_script_is('cbd-container-block', 'enqueued')) {
            wp_dequeue_script('cbd-container-block');
            wp_deregister_script('cbd-container-block');
        }
    }
    
    /**
     * Register block server-side (PRIMARY METHOD)
     */
    public function register_block_server_side() {
        // Check if Gutenberg is available
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Register scripts for the block
        wp_register_script(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/js/block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-block-editor'),
            CBD_VERSION,
            true
        );
        
        // Register editor styles
        wp_register_style(
            'cbd-block-editor-style',
            CBD_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
        
        // Register frontend styles
        wp_register_style(
            'cbd-block-style',
            CBD_PLUGIN_URL . 'assets/css/block-style.css',
            array(),
            CBD_VERSION
        );
        
        // Create frontend style file if it doesn't exist
        $this->ensure_css_files_exist();
        
        // Register the block type
        register_block_type('container-block-designer/container', array(
            'editor_script' => 'cbd-block-editor',
            'editor_style' => 'cbd-block-editor-style',
            'style' => 'cbd-block-style',
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
                )
            )
        ));
        
        $this->log_debug('Block registered server-side');
    }
    
    /**
     * Ensure CSS files exist
     */
    private function ensure_css_files_exist() {
        $css_dir = CBD_PLUGIN_DIR . 'assets/css/';
        
        // Create directory if it doesn't exist
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        // Create block-editor.css if it doesn't exist
        $editor_css = $css_dir . 'block-editor.css';
        if (!file_exists($editor_css)) {
            $editor_content = '/* Container Block Designer - Editor Styles */
.wp-block-container-block-designer-container {
    position: relative;
    min-height: 100px;
}
.wp-block-container-block-designer-container.is-selected {
    outline: 2px solid #007cba;
    outline-offset: -2px;
}
.cbd-container {
    position: relative;
    min-height: 80px;
}';
            file_put_contents($editor_css, $editor_content);
        }
        
        // Create block-style.css if it doesn't exist
        $style_css = $css_dir . 'block-style.css';
        if (!file_exists($style_css)) {
            $style_content = '/* Container Block Designer - Frontend Styles */
.wp-block-container-block-designer-container {
    position: relative;
}
.cbd-container {
    position: relative;
    min-height: 50px;
}';
            file_put_contents($style_css, $style_content);
        }
    }
    
    /**
     * Enqueue block assets
     */
    public function enqueue_block_assets() {
        // Localize script with data
        wp_localize_script('cbd-block-editor', 'cbdBlockData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('cbd/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'blocks' => $this->get_available_blocks(),
            'pluginUrl' => CBD_PLUGIN_URL,
            'debug' => $this->debug_mode,
            'i18n' => array(
                'blockTitle' => __('Container Block', 'container-block-designer'),
                'blockDescription' => __('Ein anpassbarer Container-Block mit erweiterten Features', 'container-block-designer'),
                'selectBlock' => __('Block-Typ auswählen', 'container-block-designer'),
                'customClasses' => __('Zusätzliche CSS-Klassen', 'container-block-designer'),
                'loading' => __('Lade Blocks...', 'container-block-designer'),
                'noBlocks' => __('Keine Blocks verfügbar', 'container-block-designer')
            )
        ));
        
        $this->log_debug('Block assets enqueued');
    }
    
    /**
     * Enqueue frontend block assets
     */
    public function enqueue_frontend_block_assets() {
        // This ensures the styles are loaded on both frontend and editor
        if (!is_admin()) {
            wp_enqueue_style(
                'cbd-block-style',
                CBD_PLUGIN_URL . 'assets/css/block-style.css',
                array(),
                CBD_VERSION
            );
        }
    }
    
    /**
     * Inline block registration as fallback
     */
    public function inline_block_registration() {
        // Only on block editor pages
        $screen = get_current_screen();
        if (!$screen || !$screen->is_block_editor()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            // Debug mode
            const debugMode = <?php echo $this->debug_mode ? 'true' : 'false'; ?>;
            
            // Log function
            function cbdLog(message, data = null) {
                if (debugMode || window.cbdDebug) {
                    console.log('[CBD Registration]', message, data || '');
                }
            }
            
            // Check if block is already registered
            function isBlockRegistered() {
                if (!window.wp || !window.wp.blocks) return false;
                const blocks = wp.blocks.getBlockTypes();
                return blocks.some(block => block.name === 'container-block-designer/container');
            }
            
            // Registration attempts counter
            let attempts = 0;
            const maxAttempts = 10;
            
            // Try to register block
            function tryRegisterBlock() {
                attempts++;
                cbdLog(`Registration attempt ${attempts}/${maxAttempts}`);
                
                // Check if already registered
                if (isBlockRegistered()) {
                    cbdLog('✅ Block already registered!');
                    return;
                }
                
                // Check if WordPress is ready
                if (!window.wp || !window.wp.blocks || !window.wp.element) {
                    if (attempts < maxAttempts) {
                        cbdLog('WordPress not ready, retrying...');
                        setTimeout(tryRegisterBlock, 500);
                    } else {
                        cbdLog('❌ Failed to register after max attempts');
                    }
                    return;
                }
                
                // Get data
                const blockData = window.cbdBlockData || {};
                
                // Register the block
                try {
                    const { registerBlockType } = wp.blocks;
                    const { InnerBlocks, useBlockProps } = wp.blockEditor;
                    const { Fragment, createElement: el } = wp.element;
                    const { __ } = wp.i18n;
                    
                    registerBlockType('container-block-designer/container', {
                        title: blockData.i18n?.blockTitle || 'Container Block',
                        description: blockData.i18n?.blockDescription || 'A customizable container block',
                        category: 'design',
                        icon: 'layout',
                        attributes: {
                            selectedBlock: { type: 'string', default: '' },
                            customClasses: { type: 'string', default: '' },
                            blockConfig: { type: 'object', default: {} },
                            blockFeatures: { type: 'object', default: {} }
                        },
                        
                        edit: function(props) {
                            const blockProps = useBlockProps();
                            return el(
                                'div',
                                blockProps,
                                el(InnerBlocks)
                            );
                        },
                        
                        save: function() {
                            const blockProps = useBlockProps.save();
                            return el(
                                'div',
                                blockProps,
                                el(InnerBlocks.Content)
                            );
                        }
                    });
                    
                    cbdLog('✅ Block registered via inline script!');
                    
                    // Trigger custom event
                    window.dispatchEvent(new CustomEvent('cbd-block-registered'));
                    
                } catch (error) {
                    cbdLog('❌ Registration error:', error);
                    
                    if (attempts < maxAttempts) {
                        setTimeout(tryRegisterBlock, 500);
                    }
                }
            }
            
            // Start registration process
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryRegisterBlock);
            } else {
                // Try immediately
                tryRegisterBlock();
                
                // Also try when Gutenberg is ready
                if (window.wp && window.wp.domReady) {
                    wp.domReady(tryRegisterBlock);
                }
            }
            
            // Expose debug function
            window.cbdDebugRegistration = function() {
                console.log('=== CBD Block Registration Debug ===');
                console.log('Block registered:', isBlockRegistered());
                console.log('WordPress loaded:', !!window.wp);
                console.log('Blocks API:', !!window.wp?.blocks);
                console.log('Block data:', window.cbdBlockData);
                console.log('Attempts made:', attempts);
                
                if (window.wp?.blocks) {
                    const blocks = wp.blocks.getBlockTypes();
                    const cbdBlock = blocks.find(b => b.name === 'container-block-designer/container');
                    console.log('CBD Block:', cbdBlock);
                }
            };
            
        })();
        </script>
        <?php
    }
    
    /**
     * Render block callback
     */
    public function render_block($attributes, $content) {
        $selected_block = $attributes['selectedBlock'] ?? '';
        $custom_classes = $attributes['customClasses'] ?? '';
        $block_config = $attributes['blockConfig'] ?? array();
        $block_features = $attributes['blockFeatures'] ?? array();
        
        // Build container classes
        $container_classes = array(
            'wp-block-container-block-designer-container',
            'cbd-container'
        );
        
        if ($selected_block) {
            $container_classes[] = 'cbd-container-' . $selected_block;
        }
        
        if ($custom_classes) {
            $container_classes[] = $custom_classes;
        }
        
        // Build data attributes for features
        $data_attrs = array();
        
        if ($selected_block) {
            $data_attrs['data-block-type'] = $selected_block;
        }
        
        // Add feature data attributes
        if (!empty($block_features['icon']['enabled'])) {
            $data_attrs['data-icon'] = 'true';
            $data_attrs['data-icon-value'] = $block_features['icon']['value'] ?? 'dashicons-admin-generic';
        }
        
        if (!empty($block_features['collapse']['enabled'])) {
            $data_attrs['data-collapse'] = 'true';
            $data_attrs['data-collapse-default'] = $block_features['collapse']['defaultState'] ?? 'expanded';
        }
        
        if (!empty($block_features['numbering']['enabled'])) {
            $data_attrs['data-numbering'] = 'true';
            $data_attrs['data-numbering-format'] = $block_features['numbering']['format'] ?? 'numeric';
        }
        
        if (!empty($block_features['copyText']['enabled'])) {
            $data_attrs['data-copy-text'] = 'true';
            $data_attrs['data-copy-button-text'] = $block_features['copyText']['buttonText'] ?? 'Text kopieren';
        }
        
        if (!empty($block_features['screenshot']['enabled'])) {
            $data_attrs['data-screenshot'] = 'true';
            $data_attrs['data-screenshot-button-text'] = $block_features['screenshot']['buttonText'] ?? 'Screenshot';
        }
        
        // Build attributes string
        $attrs_string = '';
        foreach ($data_attrs as $key => $value) {
            $attrs_string .= sprintf(' %s="%s"', $key, esc_attr($value));
        }
        
        // Return rendered block
        return sprintf(
            '<div class="%s"%s>%s</div>',
            esc_attr(implode(' ', $container_classes)),
            $attrs_string,
            $content
        );
    }
    
    /**
     * Get available blocks from database
     */
    private function get_available_blocks() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return array();
        }
        
        // Get active blocks
        $blocks = $wpdb->get_results(
            "SELECT id, name, slug, description, config, features 
             FROM $table_name 
             WHERE status = 'active' 
             ORDER BY name ASC",
            ARRAY_A
        );
        
        if (!$blocks) {
            return array();
        }
        
        // Parse JSON fields
        foreach ($blocks as &$block) {
            $block['config'] = json_decode($block['config'], true) ?: array();
            $block['features'] = json_decode($block['features'], true) ?: array();
        }
        
        return $blocks;
    }
    
    /**
     * Register REST routes
     */
    public function register_rest_routes() {
        register_rest_route('cbd/v1', '/blocks', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_blocks'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * REST endpoint: Get blocks
     */
    public function rest_get_blocks($request) {
        return rest_ensure_response($this->get_available_blocks());
    }
    
    /**
     * Show debug info
     */
    public function show_debug_info() {
        $screen = get_current_screen();
        if (!$screen || !$screen->is_block_editor()) {
            return;
        }
        
        ?>
        <div class="notice notice-info">
            <p><strong>CBD Debug Mode:</strong> Block-Registrierung ist aktiv. 
            Öffnen Sie die Browser-Konsole und führen Sie <code>cbdDebugRegistration()</code> aus für Details.</p>
        </div>
        <?php
    }
    
    /**
     * Log debug message
     */
    private function log_debug($message) {
        if ($this->debug_mode) {
            error_log('[CBD Registration] ' . $message);
        }
    }
}

// Initialize
CBD_Block_Registration::get_instance();