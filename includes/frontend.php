<?php
/**
 * Container Block Designer - Frontend PHP Functions
 * Version: 2.4.0
 * 
 * Datei speichern als: includes/frontend.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend functionality class
 */
class CBD_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_custom_styles'));
        add_filter('body_class', array($this, 'add_body_classes'));
        add_action('wp_footer', array($this, 'add_frontend_scripts'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Frontend CSS
        wp_enqueue_style(
            'cbd-frontend',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend.css',
            array(),
            CBD_VERSION
        );
        
        wp_enqueue_style(
            'cbd-position-frontend',
            CBD_PLUGIN_URL . 'assets/css/frontend-positioning.css',
            array('cbd-frontend'),
            CBD_VERSION
        );
        
        // Frontend JavaScript
        wp_enqueue_script(
            'cbd-frontend',
            CBD_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            CBD_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('cbd-frontend', 'cbdFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd-frontend'),
            'strings' => array(
                'copySuccess' => __('Text kopiert!', 'container-block-designer'),
                'copyError' => __('Kopieren fehlgeschlagen', 'container-block-designer'),
                'screenshotSuccess' => __('Screenshot erstellt!', 'container-block-designer'),
                'screenshotError' => __('Screenshot fehlgeschlagen', 'container-block-designer'),
                'containerNotFound' => __('Container nicht gefunden', 'container-block-designer'),
                'noTextFound' => __('Kein Text zum Kopieren gefunden', 'container-block-designer'),
                'screenshotUnavailable' => __('Screenshot-Funktion nicht verfügbar', 'container-block-designer'),
                'creating' => __('Erstelle Screenshot...', 'container-block-designer'),
                'close' => __('Schließen', 'container-block-designer'),
                'containerIcon' => __('Container Icon', 'container-block-designer'),
                'containerNumber' => __('Container Nummer', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Add custom styles to head
     */
    public function add_custom_styles() {
        $custom_css = get_option('cbd_custom_css', '');
        
        if (!empty($custom_css)) {
            echo '<style id="cbd-custom-styles">' . wp_strip_all_tags($custom_css) . '</style>';
        }
    }
    
    /**
     * Add body classes
     */
    public function add_body_classes($classes) {
        $classes[] = 'cbd-enabled';
        
        // Add classes based on active features
        if ($this->has_positioned_elements()) {
            $classes[] = 'cbd-has-positioned-elements';
        }
        
        if ($this->has_collapsible_containers()) {
            $classes[] = 'cbd-has-collapsible';
        }
        
        return $classes;
    }
    
    /**
     * Check if page has positioned elements
     */
    private function has_positioned_elements() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_block('cbd/container-block', $post);
    }
    
    /**
     * Check if page has collapsible containers
     */
    private function has_collapsible_containers() {
        // This would require parsing the actual block content
        // For now, we'll assume it's possible if CBD blocks are present
        return $this->has_positioned_elements();
    }
    
    /**
     * Add frontend scripts to footer
     */
    public function add_frontend_scripts() {
        ?>
        <script>
        // Initialize CBD Frontend when DOM is ready
        jQuery(document).ready(function($) {
            // Add global functions for backwards compatibility
            window.cbdShowToast = function(message, type, duration) {
                if (window.CBD_Frontend_API && window.CBD_Frontend_API.showToast) {
                    window.CBD_Frontend_API.showToast(message, type, duration);
                }
            };
            
            window.cbdCopyToClipboard = function(text) {
                if (window.CBD_Frontend_API && window.CBD_Frontend_API.copyToClipboard) {
                    return window.CBD_Frontend_API.copyToClipboard(text);
                }
                return Promise.reject('CBD Frontend API not available');
            };
        });
        </script>
        <?php
    }
}

// Initialize frontend
new CBD_Frontend();

/**
 * Helper functions for frontend use
 */

/**
 * Check if a container block is active on current page
 */
function cbd_has_container_blocks() {
    global $post;
    
    if (!$post) {
        return false;
    }
    
    return has_block('cbd/container-block', $post);
}

/**
 * Get container blocks from post content
 */
function cbd_get_container_blocks($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post ? $post->ID : 0;
    }
    
    if (!$post_id) {
        return array();
    }
    
    $content = get_post_field('post_content', $post_id);
    $blocks = parse_blocks($content);
    
    return cbd_filter_container_blocks($blocks);
}

/**
 * Recursively filter container blocks from blocks array
 */
function cbd_filter_container_blocks($blocks) {
    $container_blocks = array();
    
    foreach ($blocks as $block) {
        if ($block['blockName'] === 'cbd/container-block') {
            $container_blocks[] = $block;
        }
        
        // Check inner blocks
        if (!empty($block['innerBlocks'])) {
            $inner_containers = cbd_filter_container_blocks($block['innerBlocks']);
            $container_blocks = array_merge($container_blocks, $inner_containers);
        }
    }
    
    return $container_blocks;
}

/**
 * Generate container HTML with positioning
 */
function cbd_render_positioned_container($block_slug, $content = '', $attributes = array()) {
    return CBD_Block_Renderer::render_container_block($attributes, $content);
}

/**
 * Get formatted container number
 */
function cbd_get_formatted_number($format, $index) {
    return cbd_generate_container_number($format, $index);
}

/**
 * Add custom CSS for specific container
 */
function cbd_add_container_css($css) {
    static $custom_styles = array();
    
    $custom_styles[] = $css;
    
    // Output styles in footer
    add_action('wp_footer', function() use ($custom_styles) {
        if (!empty($custom_styles)) {
            echo '<style>' . implode("\n", $custom_styles) . '</style>';
        }
    });
}

/**
 * Register container block patterns
 */
add_action('init', 'cbd_register_block_patterns');
function cbd_register_block_patterns() {
    // Get active blocks for patterns
    $active_blocks = cbd_get_active_blocks();
    
    foreach ($active_blocks as $block) {
        register_block_pattern(
            'cbd/' . $block['slug'] . '-pattern',
            array(
                'title' => sprintf(__('%s Pattern', 'container-block-designer'), $block['name']),
                'description' => $block['description'] ?: sprintf(__('Pattern für %s', 'container-block-designer'), $block['name']),
                'content' => sprintf(
                    '<!-- wp:cbd/container-block {"selectedBlock":"%s"} -->
                    <div class="cbd-container cbd-block-%s">
                        <!-- wp:paragraph -->
                        <p>%s</p>
                        <!-- /wp:paragraph -->
                    </div>
                    <!-- /wp:cbd/container-block -->',
                    $block['slug'],
                    $block['slug'],
                    __('Beispiel-Inhalt für diesen Container.', 'container-block-designer')
                ),
                'categories' => array('cbd-patterns'),
                'keywords' => array($block['slug'], 'container', $block['name']),
            )
        );
    }
}

/**
 * Register pattern category
 */
add_action('init', 'cbd_register_pattern_category');
function cbd_register_pattern_category() {
    register_block_pattern_category(
        'cbd-patterns',
        array(
            'label' => __('Container Patterns', 'container-block-designer'),
            'description' => __('Vordefinierte Container-Layouts mit positionierten Elementen', 'container-block-designer'),
        )
    );
}

/**
 * Handle frontend AJAX requests
 */
add_action('wp_ajax_cbd_frontend_action', 'cbd_handle_frontend_ajax');
add_action('wp_ajax_nopriv_cbd_frontend_action', 'cbd_handle_frontend_ajax');

function cbd_handle_frontend_ajax() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cbd-frontend')) {
        wp_die(__('Sicherheitsüberprüfung fehlgeschlagen', 'container-block-designer'));
    }
    
    $action_type = sanitize_text_field($_POST['action_type'] ?? '');
    
    switch ($action_type) {
        case 'get_container_data':
            $container_id = sanitize_text_field($_POST['container_id'] ?? '');
            // Handle container data request
            wp_send_json_success(array('message' => 'Container data retrieved'));
            break;
            
        default:
            wp_send_json_error(__('Unbekannte Aktion', 'container-block-designer'));
            break;
    }
}

/**
 * Add structured data for containers
 */
add_action('wp_head', 'cbd_add_structured_data');
function cbd_add_structured_data() {
    if (!cbd_has_container_blocks()) {
        return;
    }
    
    $containers = cbd_get_container_blocks();
    
    if (empty($containers)) {
        return;
    }
    
    $structured_data = array(
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'mainEntity' => array()
    );
    
    foreach ($containers as $container) {
        $attributes = $container['attrs'] ?? array();
        $block_slug = $attributes['selectedBlock'] ?? '';
        
        if ($block_slug) {
            $block_data = cbd_get_block_by_slug($block_slug);
            
            if ($block_data) {
                $structured_data['mainEntity'][] = array(
                    '@type' => 'Article',
                    'name' => $block_data['name'],
                    'description' => $block_data['description']
                );
            }
        }
    }
    
    if (!empty($structured_data['mainEntity'])) {
        echo '<script type="application/ld+json">' . json_encode($structured_data, JSON_UNESCAPED_SLASHES) . '</script>';
    }
}

/**
 * ============================================
 * BLOCKS MANAGER PHP
 * Datei speichern als: includes/blocks-manager.php
 * ============================================
 */

/**
 * Blocks Manager Class
 */
class CBD_Blocks_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_blocks'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_assets'));
        add_filter('block_categories_all', array($this, 'add_block_category'));
    }
    
    /**
     * Register blocks
     */
    public function register_blocks() {
        // Register the main container block
        register_block_type('cbd/container-block', array(
            'editor_script' => 'cbd-container-block',
            'editor_style' => 'cbd-container-block-editor',
            'style' => 'cbd-container-block-frontend',
            'render_callback' => array('CBD_Block_Renderer', 'render_container_block'),
            'attributes' => array(
                'selectedBlock' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'blockFeatures' => array(
                    'type' => 'object',
                    'default' => array()
                ),
                'blockConfig' => array(
                    'type' => 'object',
                    'default' => array()
                ),
                'align' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'supports' => array(
                'align' => array('wide', 'full'),
                'html' => false,
                'spacing' => array(
                    'margin' => true,
                    'padding' => true
                ),
                'color' => array(
                    'background' => true,
                    'text' => true,
                    'gradients' => true
                )
            )
        ));
        
        // Register dynamic block variations
        $this->register_block_variations();
    }
    
    /**
     * Register block variations based on available designs
     */
    public function register_block_variations() {
        $active_blocks = cbd_get_active_blocks();
        
        foreach ($active_blocks as $block) {
            // This will be handled via JavaScript
            // PHP registration is mainly for server-side rendering
        }
    }
    
    /**
     * Enqueue block assets
     */
    public function enqueue_block_assets() {
        // Block editor script
        wp_enqueue_script(
            'cbd-container-block',
            CBD_PLUGIN_URL . 'assets/js/container-block.js',
            array(
                'wp-blocks',
                'wp-element',
                'wp-editor',
                'wp-block-editor',
                'wp-components',
                'wp-i18n',
                'wp-api-fetch'
            ),
            CBD_VERSION,
            true
        );
        
        // Block editor styles
        wp_enqueue_style(
            'cbd-container-block-editor',
            CBD_PLUGIN_URL . 'assets/css/container-block-editor.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
        
        // Localize script for block editor
        wp_localize_script('cbd-container-block', 'cbdBlockEditor', array(
            'apiUrl' => rest_url('cbd/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => CBD_PLUGIN_URL,
            'strings' => array(
                'selectDesign' => __('-- Design auswählen --', 'container-block-designer'),
                'loadingBlocks' => __('Lade Blöcke...', 'container-block-designer'),
                'noBlocks' => __('Keine aktiven Container-Designs gefunden.', 'container-block-designer'),
                'createFirst' => __('Erstes Design erstellen', 'container-block-designer'),
                'blockIcon' => __('Block-Icon', 'container-block-designer'),
                'numbering' => __('Nummerierung', 'container-block-designer'),
                'collapsed' => __('Eingeklappt', 'container-block-designer'),
                'expanded' => __('Ausgeklappt', 'container-block-designer'),
                'copyText' => __('Text kopieren', 'container-block-designer'),
                'screenshot' => __('Screenshot', 'container-block-designer'),
                'editDesign' => __('Design bearbeiten', 'container-block-designer'),
                'position' => __('Position:', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Add custom block category
     */
    public function add_block_category($categories) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug' => 'cbd-blocks',
                    'title' => __('Container Blocks', 'container-block-designer'),
                    'icon' => 'layout'
                )
            )
        );
    }
    
    /**
     * Get block registration data
     */
    public function get_block_data($block_slug) {
        return cbd_get_block_by_slug($block_slug);
    }
    
    /**
     * Validate block attributes
     */
    public function validate_block_attributes($attributes) {
        $defaults = array(
            'selectedBlock' => '',
            'blockFeatures' => array(),
            'blockConfig' => array()
        );
        
        return wp_parse_args($attributes, $defaults);
    }
}

// Initialize blocks manager
new CBD_Blocks_Manager();