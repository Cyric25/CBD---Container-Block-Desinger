<?php
/**
 * Container Block Designer - Unified Frontend Renderer
 * Consolidates all frontend rendering functionality
 * 
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified Frontend Renderer Class
 * Replaces multiple renderer classes with single, consistent implementation
 */
class CBD_Unified_Frontend_Renderer {
    
    /**
     * Registered blocks cache
     */
    private static $block_cache = array();
    
    /**
     * Initialize the renderer
     */
    public static function init() {
        // Block rendering
        add_filter('render_block', array(__CLASS__, 'render_container_block'), 10, 2);
        
        // Assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_action('wp_footer', array(__CLASS__, 'render_inline_scripts'));
        
        // Register blocks
        add_action('init', array(__CLASS__, 'register_blocks'), 20);
    }
    
    /**
     * Register all container blocks
     */
    public static function register_blocks() {
        if (function_exists('register_block_type')) {
            // Register main container block
            register_block_type('container-block-designer/container', array(
                'render_callback' => array(__CLASS__, 'render_block_callback'),
                'attributes' => self::get_block_attributes()
            ));
            
            // Alternative block name for compatibility
            register_block_type('cbd/container-block', array(
                'render_callback' => array(__CLASS__, 'render_block_callback'),
                'attributes' => self::get_block_attributes()
            ));
        }
    }
    
    /**
     * Get block attributes schema
     */
    private static function get_block_attributes() {
        return array(
            'selectedBlock' => array(
                'type' => 'string',
                'default' => ''
            ),
            'customClasses' => array(
                'type' => 'string', 
                'default' => ''
            ),
            'features' => array(
                'type' => 'object',
                'default' => array()
            ),
            'enableIcon' => array(
                'type' => 'boolean',
                'default' => false
            ),
            'iconValue' => array(
                'type' => 'string',
                'default' => 'dashicons-admin-generic'
            ),
            'enableCollapse' => array(
                'type' => 'boolean', 
                'default' => false
            ),
            'collapseDefault' => array(
                'type' => 'string',
                'default' => 'expanded'
            )
        );
    }
    
    /**
     * Render block callback for both block types
     */
    public static function render_block_callback($attributes, $content) {
        return self::render_block($attributes, $content);
    }
    
    /**
     * Process container blocks via filter hook
     */
    public static function render_container_block($block_content, $block) {
        // Handle both block name variants
        $container_blocks = array(
            'container-block-designer/container',
            'cbd/container-block'
        );
        
        if (!in_array($block['blockName'], $container_blocks)) {
            return $block_content;
        }
        
        $attributes = $block['attrs'] ?? array();
        return self::render_block($attributes, $block_content);
    }
    
    /**
     * Main block rendering method
     */
    public static function render_block($attributes, $content = '') {
        // Sanitize and extract attributes
        $selected_block = sanitize_text_field($attributes['selectedBlock'] ?? '');
        $custom_classes = sanitize_text_field($attributes['customClasses'] ?? '');
        $features = $attributes['features'] ?? array();
        
        // Feature attributes for backward compatibility
        $enable_icon = filter_var($attributes['enableIcon'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $icon_value = sanitize_text_field($attributes['iconValue'] ?? 'dashicons-admin-generic');
        $enable_collapse = filter_var($attributes['enableCollapse'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $collapse_default = sanitize_text_field($attributes['collapseDefault'] ?? 'expanded');
        
        // Get block configuration
        $block_data = self::get_block_data($selected_block);
        if (!$block_data) {
            return self::render_fallback_block($content, $custom_classes);
        }
        
        // Parse block data
        $config = json_decode($block_data->config, true) ?: array();
        $styles = json_decode($block_data->styles, true) ?: array();
        $block_features = json_decode($block_data->features, true) ?: array();
        
        // Merge features (attributes override block defaults)
        if ($enable_icon) {
            $block_features['icon'] = array(
                'enabled' => true,
                'value' => $icon_value
            );
        }
        
        if ($enable_collapse) {
            $block_features['collapsible'] = array(
                'enabled' => true,
                'defaultState' => $collapse_default
            );
        }
        
        // Generate unique ID
        $block_id = 'cbd-block-' . uniqid();
        
        // Build CSS classes
        $classes = array('cbd-container-block', 'cbd-block-' . sanitize_html_class($selected_block));
        if (!empty($custom_classes)) {
            $classes[] = $custom_classes;
        }
        
        // Add feature classes
        if (!empty($block_features['collapsible']['enabled'])) {
            $classes[] = 'cbd-collapsible';
            if ($block_features['collapsible']['defaultState'] === 'collapsed') {
                $classes[] = 'cbd-collapsed';
            }
        }
        
        if (!empty($block_features['icon']['enabled'])) {
            $classes[] = 'cbd-has-icon';
        }
        
        // Generate inline styles
        $inline_styles = self::generate_inline_styles($block_id, $styles);
        
        // Build HTML
        $html = '<div class="' . esc_attr(implode(' ', $classes)) . '" id="' . esc_attr($block_id) . '">';
        
        // Add icon if enabled
        if (!empty($block_features['icon']['enabled'])) {
            $icon_class = sanitize_html_class($block_features['icon']['value']);
            $icon_color = !empty($block_features['icon']['color']) ? 
                'style="color: ' . esc_attr($block_features['icon']['color']) . '"' : '';
            $html .= '<span class="cbd-icon dashicons ' . $icon_class . '" ' . $icon_color . '></span>';
        }
        
        // Add collapse button if enabled
        if (!empty($block_features['collapsible']['enabled'])) {
            $html .= '<button class="cbd-collapse-toggle" type="button" aria-expanded="true">';
            $html .= '<span class="cbd-collapse-icon"></span>';
            $html .= '</button>';
        }
        
        // Add content wrapper
        $html .= '<div class="cbd-content">';
        $html .= $content;
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Add inline styles
        if ($inline_styles) {
            $html .= '<style>' . $inline_styles . '</style>';
        }
        
        return $html;
    }
    
    /**
     * Get block data from database with caching
     */
    private static function get_block_data($block_name) {
        if (empty($block_name)) {
            return null;
        }
        
        // Check cache first
        if (isset(self::$block_cache[$block_name])) {
            return self::$block_cache[$block_name];
        }
        
        global $wpdb;
        $block_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s AND status = 'active'",
            $block_name
        ));
        
        // Cache result (even if null)
        self::$block_cache[$block_name] = $block_data;
        
        return $block_data;
    }
    
    /**
     * Generate inline CSS styles
     */
    private static function generate_inline_styles($block_id, $styles) {
        if (empty($styles)) {
            return '';
        }
        
        $css = "#$block_id {";
        
        // Background
        if (!empty($styles['background']['color'])) {
            $css .= 'background-color: ' . sanitize_hex_color($styles['background']['color']) . ';';
        }
        
        // Padding
        if (!empty($styles['padding'])) {
            $padding = $styles['padding'];
            if (is_array($padding)) {
                $css .= sprintf(
                    'padding: %dpx %dpx %dpx %dpx;',
                    intval($padding['top'] ?? 0),
                    intval($padding['right'] ?? 0),
                    intval($padding['bottom'] ?? 0),
                    intval($padding['left'] ?? 0)
                );
            }
        }
        
        // Border
        if (!empty($styles['border'])) {
            $border = $styles['border'];
            if (!empty($border['width'])) {
                $css .= 'border-width: ' . intval($border['width']) . 'px;';
            }
            if (!empty($border['style'])) {
                $css .= 'border-style: ' . sanitize_text_field($border['style']) . ';';
            }
            if (!empty($border['color'])) {
                $css .= 'border-color: ' . sanitize_hex_color($border['color']) . ';';
            }
            if (!empty($border['radius'])) {
                $css .= 'border-radius: ' . intval($border['radius']) . 'px;';
            }
        }
        
        // Typography
        if (!empty($styles['typography']['color'])) {
            $css .= 'color: ' . sanitize_hex_color($styles['typography']['color']) . ';';
        }
        
        $css .= '}';
        
        return $css;
    }
    
    /**
     * Render fallback block when no specific block is selected
     */
    private static function render_fallback_block($content, $custom_classes = '') {
        $classes = array('cbd-container-block', 'cbd-fallback');
        if (!empty($custom_classes)) {
            $classes[] = $custom_classes;
        }
        
        return '<div class="' . esc_attr(implode(' ', $classes)) . '">' . $content . '</div>';
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend_assets() {
        if (!self::has_container_blocks()) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'cbd-frontend',
            CBD_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            CBD_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'cbd-frontend',
            CBD_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            CBD_VERSION,
            true
        );
        
        // Dashicons for icons
        wp_enqueue_style('dashicons');
    }
    
    /**
     * Render inline scripts for collapsible functionality
     */
    public static function render_inline_scripts() {
        if (!self::has_container_blocks()) {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Collapsible functionality
            $('.cbd-collapse-toggle').on('click', function(e) {
                e.preventDefault();
                var $container = $(this).closest('.cbd-collapsible');
                var $content = $container.find('.cbd-content');
                var $toggle = $(this);
                
                if ($container.hasClass('cbd-collapsed')) {
                    $content.slideDown();
                    $container.removeClass('cbd-collapsed');
                    $toggle.attr('aria-expanded', 'true');
                } else {
                    $content.slideUp();
                    $container.addClass('cbd-collapsed');
                    $toggle.attr('aria-expanded', 'false');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Check if current page has container blocks
     */
    private static function has_container_blocks() {
        global $post;
        
        if (!$post || !has_blocks($post->post_content)) {
            return false;
        }
        
        $blocks = parse_blocks($post->post_content);
        return self::search_for_container_blocks($blocks);
    }
    
    /**
     * Recursively search for container blocks
     */
    private static function search_for_container_blocks($blocks) {
        foreach ($blocks as $block) {
            if (in_array($block['blockName'], array('container-block-designer/container', 'cbd/container-block'))) {
                return true;
            }
            
            if (!empty($block['innerBlocks'])) {
                if (self::search_for_container_blocks($block['innerBlocks'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
}