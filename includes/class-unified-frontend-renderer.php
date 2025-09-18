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
        // DISABLED - Master Renderer handles filtering
        // add_filter('render_block', array(__CLASS__, 'render_container_block'), 10, 2);
        
        // Assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_action('wp_footer', array(__CLASS__, 'render_inline_scripts'));
        
        // Register blocks
        // DISABLED - Master Renderer handles block registration
        // add_action('init', array(__CLASS__, 'register_blocks'), 20);
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
        // DEBUG: Add comment to verify this renderer is active
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CBD DEBUG: Unified Frontend Renderer is active at ' . date('Y-m-d H:i:s'));
        }
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
        // DEBUG: Add HTML comment to verify our changes are loading
        $html = '<!-- CBD DEBUG: Unified Frontend Renderer UPDATED active at ' . date('Y-m-d H:i:s') . ' -->';
        
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
            $block_features['collapse'] = array(
                'enabled' => true,
                'defaultState' => $collapse_default
            );
        }
        
        // Generate unique container ID
        $container_id = 'cbd-container-' . uniqid();
        
        // Build wrapper classes (.cbd-container - transparent wrapper)
        $wrapper_classes = array('cbd-container');
        $wrapper_attributes = array('id' => $container_id);
        
        // Add feature-based data attributes to wrapper for JavaScript
        if (!empty($block_features['collapse']['enabled'])) {
            $wrapper_attributes['data-collapse'] = json_encode($block_features['collapse']);
            $wrapper_classes[] = 'cbd-collapsible';
            if (($block_features['collapse']['defaultState'] ?? 'expanded') === 'collapsed') {
                $wrapper_classes[] = 'cbd-collapsed';
            }
        }
        
        if (!empty($block_features['copyText']['enabled'])) {
            $wrapper_attributes['data-copy-text'] = json_encode($block_features['copyText']);
        }
        
        if (!empty($block_features['screenshot']['enabled'])) {
            $wrapper_attributes['data-screenshot'] = json_encode($block_features['screenshot']);
        }
        
        if (!empty($block_features['icon']['enabled'])) {
            $wrapper_attributes['data-icon'] = json_encode($block_features['icon']);
        }
        
        if (!empty($block_features['numbering']['enabled'])) {
            $wrapper_attributes['data-numbering'] = json_encode($block_features['numbering']);
        }
        
        $wrapper_attributes['class'] = implode(' ', $wrapper_classes);
        
        // Inner content block classes (.cbd-container-block)
        $content_classes = array('cbd-container-block');
        $content_attributes = array();
        
        // Add block-specific class to content block
        $content_classes[] = 'cbd-block-' . sanitize_html_class($selected_block);
        
        // Add custom class if set
        if (!empty($custom_classes)) {
            $content_classes[] = sanitize_html_class($custom_classes);
        }
        
        $content_attributes['class'] = implode(' ', $content_classes);
        
        // Generate inline styles for content block only
        $inline_styles = self::generate_inline_styles('.' . implode('.', $content_classes), $styles);
        if (!empty($inline_styles)) {
            $content_attributes['style'] = $inline_styles;
        }
        
        // Start outer wrapper (.cbd-container) - for controls and positioning
        $html .= '<div';
        foreach ($wrapper_attributes as $attr => $value) {
            $html .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        // Add numbering OUTSIDE everything (in the main container)
        if (!empty($block_features['numbering']['enabled'])) {
            $numbering_counter = 1; // Simple counter for now
            $format = $block_features['numbering']['format'] ?? 'numeric';
            $number = $numbering_counter; // Simple implementation
            
            $html .= '<div class="cbd-container-number cbd-outside-number" data-number="' . esc_attr($numbering_counter) . '">';
            $html .= esc_html($number);
            $html .= '</div>';
        }
        
        // Content wrapper div for collapse functionality
        $content_wrapper_class = 'cbd-content';
        $content_wrapper_id = $container_id . '-content';
        
        $html .= '<div class="' . $content_wrapper_class . '" id="' . esc_attr($content_wrapper_id) . '">';
        
        // Inner content block (.cbd-container-block) - this gets the visual styling
        $html .= '<div';
        foreach ($content_attributes as $attr => $value) {
            $html .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        // Selection-based feature menu (appears on hover/tap)
        $has_collapse = !empty($block_features['collapse']['enabled']);
        
        // Check copyText with multiple fallbacks
        $has_copy = false;
        if (!empty($block_features['copyText']['enabled'])) {
            $has_copy = true;
        } elseif (!empty($block_features['copy-text']['enabled'])) {
            $has_copy = true;
        } elseif (!empty($block_features['copy_text']['enabled'])) {
            $has_copy = true;
        }
        
        // Check screenshot
        $has_screenshot = !empty($block_features['screenshot']['enabled']);
        
        // TEMPORARY FIX: Force at least collapse to be available for testing
        if (!$has_collapse && !$has_copy && !$has_screenshot) {
            $has_collapse = true; // Force collapse to show menu
        }
        
        $has_features = $has_collapse || $has_copy || $has_screenshot;
        
        if ($has_features) {
            $html .= '<div class="cbd-selection-menu" style="display: none;">';
            $html .= '<button class="cbd-menu-toggle" type="button" aria-expanded="false">';
            $html .= '<i class="dashicons dashicons-admin-generic"></i>';
            $html .= '</button>';
            
            $html .= '<div class="cbd-dropdown-menu">';
            
            // Collapse toggle
            if ($has_collapse) {
                $expanded = ($block_features['collapse']['defaultState'] ?? 'expanded') !== 'collapsed';
                $html .= '<button class="cbd-dropdown-item cbd-collapse-toggle" type="button" aria-expanded="' . ($expanded ? 'true' : 'false') . '" aria-controls="' . esc_attr($container_id) . '-content">';
                $html .= '<i class="dashicons dashicons-arrow-' . ($expanded ? 'up' : 'down') . '-alt2"></i>';
                $html .= '<span>' . esc_html($block_features['collapse']['label'] ?? $block_features['collapse']['buttonText'] ?? 'Einklappen') . '</span>';
                $html .= '</button>';
            }
            
            // Copy text
            if ($has_copy) {
                $copy_feature = array();
                if (!empty($block_features['copyText'])) {
                    $copy_feature = $block_features['copyText'];
                } elseif (!empty($block_features['copy-text'])) {
                    $copy_feature = $block_features['copy-text'];
                } elseif (!empty($block_features['copy_text'])) {
                    $copy_feature = $block_features['copy_text'];
                }
                
                $html .= '<button class="cbd-dropdown-item cbd-copy-text" data-container-id="' . esc_attr($container_id) . '">';
                $html .= '<i class="dashicons dashicons-clipboard"></i>';
                $html .= '<span>' . esc_html($copy_feature['buttonText'] ?? $copy_feature['label'] ?? 'Text kopieren') . '</span>';
                $html .= '</button>';
            }
            
            // Screenshot
            if ($has_screenshot) {
                $screenshot_feature = $block_features['screenshot'] ?? array();
                $html .= '<button class="cbd-dropdown-item cbd-screenshot" data-container-id="' . esc_attr($container_id) . '">';
                $html .= '<i class="dashicons dashicons-camera"></i>';
                $html .= '<span>' . esc_html($screenshot_feature['buttonText'] ?? $screenshot_feature['label'] ?? 'Screenshot') . '</span>';
                $html .= '</button>';
            }
            
            $html .= '</div>'; // Close dropdown menu
            $html .= '</div>'; // Close selection menu
        }
        
        // Add block header with icon and title (always top-left, visible when collapsed)
        $block_title = '';
        
        // Check multiple possible attribute names for title
        if (!empty($attributes['blockTitle'])) {
            $block_title = $attributes['blockTitle'];
        } elseif (!empty($attributes['title'])) {
            $block_title = $attributes['title'];  
        } elseif (!empty($config['blockTitle'])) {
            $block_title = $config['blockTitle'];
        } elseif (!empty($config['title'])) {
            $block_title = $config['title'];
        }
        
        // TEMPORARY: If no title found, use a test title
        if (empty($block_title)) {
            $block_title = 'Test Container Title'; // For testing
        }
        
        $has_icon = !empty($block_features['icon']['enabled']);
        
        // TEMPORARY: Force icon for testing if none configured
        if (!$has_icon) {
            $has_icon = true;
            $block_features['icon'] = array(
                'enabled' => true,
                'value' => 'dashicons-admin-generic',
                'color' => '#333'
            );
        }
        
        // Always show header if there's a title OR icon
        if ($has_icon || !empty($block_title)) {
            $html .= '<div class="cbd-block-header">';
            
            // Icon (always top-left if enabled)
            if ($has_icon) {
                $icon_class = sanitize_html_class($block_features['icon']['value'] ?? 'dashicons-admin-generic');
                $icon_color = !empty($block_features['icon']['color']) ? 
                    'style="color: ' . esc_attr($block_features['icon']['color']) . '"' : '';
                $html .= '<span class="cbd-header-icon" ' . $icon_color . '>';
                $html .= '<i class="dashicons ' . $icon_class . '"></i>';
                $html .= '</span>';
            }
            
            // Block title (next to icon, responsive) - ALWAYS if not empty
            if (!empty($block_title)) {
                $html .= '<h3 class="cbd-block-title">' . esc_html($block_title) . '</h3>';
            }
            
            $html .= '</div>'; // Close block header
        }
        
        // Wrap the actual content in a collapsible container
        $html .= '<div class="cbd-container-content">';
        
        // Actual content - process and secure HTML elements
        $html .= self::process_html_content($content);
        
        $html .= '</div>'; // Close .cbd-container-content
        $html .= '</div>'; // Close .cbd-container-block
        $html .= '</div>'; // Close .cbd-content
        $html .= '</div>'; // Close .cbd-container
        
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
     * Process and secure HTML content for container blocks
     * Handles interactive elements, applies CSS isolation, and ensures security
     */
    private static function process_html_content($content) {
        if (empty($content)) {
            return $content;
        }

        // Define allowed HTML tags and attributes for container blocks
        $allowed_html = array(
            // Text formatting
            'p' => array('class' => array(), 'style' => array(), 'id' => array()),
            'br' => array(),
            'strong' => array('class' => array(), 'style' => array()),
            'b' => array('class' => array(), 'style' => array()),
            'em' => array('class' => array(), 'style' => array()),
            'i' => array('class' => array(), 'style' => array()),
            'u' => array('class' => array(), 'style' => array()),
            'span' => array('class' => array(), 'style' => array(), 'id' => array()),
            'small' => array('class' => array(), 'style' => array()),

            // Headings
            'h1' => array('class' => array(), 'style' => array(), 'id' => array()),
            'h2' => array('class' => array(), 'style' => array(), 'id' => array()),
            'h3' => array('class' => array(), 'style' => array(), 'id' => array()),
            'h4' => array('class' => array(), 'style' => array(), 'id' => array()),
            'h5' => array('class' => array(), 'style' => array(), 'id' => array()),
            'h6' => array('class' => array(), 'style' => array(), 'id' => array()),

            // Lists
            'ul' => array('class' => array(), 'style' => array(), 'id' => array()),
            'ol' => array('class' => array(), 'style' => array(), 'id' => array()),
            'li' => array('class' => array(), 'style' => array(), 'id' => array()),

            // Links and images
            'a' => array('href' => array(), 'class' => array(), 'style' => array(), 'target' => array(), 'title' => array(), 'id' => array()),
            'img' => array('src' => array(), 'alt' => array(), 'class' => array(), 'style' => array(), 'width' => array(), 'height' => array(), 'id' => array()),

            // Block elements
            'div' => array('class' => array(), 'style' => array(), 'id' => array(), 'data-*' => array()),
            'section' => array('class' => array(), 'style' => array(), 'id' => array()),
            'article' => array('class' => array(), 'style' => array(), 'id' => array()),
            'aside' => array('class' => array(), 'style' => array(), 'id' => array()),
            'header' => array('class' => array(), 'style' => array(), 'id' => array()),
            'footer' => array('class' => array(), 'style' => array(), 'id' => array()),
            'blockquote' => array('class' => array(), 'style' => array(), 'id' => array()),
            'pre' => array('class' => array(), 'style' => array(), 'id' => array()),
            'code' => array('class' => array(), 'style' => array(), 'id' => array()),

            // Tables
            'table' => array('class' => array(), 'style' => array(), 'id' => array()),
            'thead' => array('class' => array(), 'style' => array()),
            'tbody' => array('class' => array(), 'style' => array()),
            'tfoot' => array('class' => array(), 'style' => array()),
            'tr' => array('class' => array(), 'style' => array(), 'id' => array()),
            'th' => array('class' => array(), 'style' => array(), 'colspan' => array(), 'rowspan' => array()),
            'td' => array('class' => array(), 'style' => array(), 'colspan' => array(), 'rowspan' => array()),

            // Interactive elements (forms)
            'form' => array('class' => array(), 'style' => array(), 'id' => array(), 'action' => array(), 'method' => array()),
            'input' => array('type' => array(), 'name' => array(), 'value' => array(), 'class' => array(), 'style' => array(), 'id' => array(), 'placeholder' => array(), 'required' => array(), 'disabled' => array(), 'readonly' => array()),
            'textarea' => array('name' => array(), 'class' => array(), 'style' => array(), 'id' => array(), 'placeholder' => array(), 'rows' => array(), 'cols' => array(), 'required' => array(), 'disabled' => array(), 'readonly' => array()),
            'select' => array('name' => array(), 'class' => array(), 'style' => array(), 'id' => array(), 'required' => array(), 'disabled' => array()),
            'option' => array('value' => array(), 'selected' => array(), 'disabled' => array()),
            'button' => array('type' => array(), 'class' => array(), 'style' => array(), 'id' => array(), 'disabled' => array()),
            'label' => array('for' => array(), 'class' => array(), 'style' => array()),

            // Media elements
            'video' => array('src' => array(), 'class' => array(), 'style' => array(), 'controls' => array(), 'width' => array(), 'height' => array(), 'autoplay' => array(), 'loop' => array(), 'muted' => array()),
            'audio' => array('src' => array(), 'class' => array(), 'style' => array(), 'controls' => array(), 'autoplay' => array(), 'loop' => array(), 'muted' => array()),
            'canvas' => array('class' => array(), 'style' => array(), 'width' => array(), 'height' => array(), 'id' => array()),
            'svg' => array('class' => array(), 'style' => array(), 'width' => array(), 'height' => array(), 'viewBox' => array()),

            // Other useful elements
            'iframe' => array('src' => array(), 'class' => array(), 'style' => array(), 'width' => array(), 'height' => array(), 'frameborder' => array(), 'allowfullscreen' => array()),
            'hr' => array('class' => array(), 'style' => array()),
        );

        // Filter HTML content for security
        $filtered_content = wp_kses($content, $allowed_html);

        // Wrap content in CSS isolation container to prevent style conflicts
        $processed_content = '<div class="cbd-html-content-wrapper">' . $filtered_content . '</div>';

        // Add JavaScript initialization for interactive elements if needed
        $processed_content .= self::get_html_initialization_script();

        return $processed_content;
    }

    /**
     * Get JavaScript initialization script for HTML elements
     */
    private static function get_html_initialization_script() {
        static $script_added = false;

        if ($script_added) {
            return '';
        }

        $script_added = true;

        return '
        <script>
        (function() {
            // Initialize interactive HTML elements in container blocks
            document.addEventListener("DOMContentLoaded", function() {
                // Find all HTML content wrappers
                var htmlWrappers = document.querySelectorAll(".cbd-html-content-wrapper");

                htmlWrappers.forEach(function(wrapper) {
                    // Initialize form elements
                    var forms = wrapper.querySelectorAll("form");
                    forms.forEach(function(form) {
                        // Prevent forms from breaking out of container block context
                        form.addEventListener("submit", function(e) {
                            // Allow normal form submission but ensure no conflicts
                            console.log("CBD: Form submitted in container block");
                        });
                    });

                    // Initialize interactive buttons
                    var buttons = wrapper.querySelectorAll("button");
                    buttons.forEach(function(button) {
                        if (!button.hasAttribute("onclick") && !button.hasAttribute("data-initialized")) {
                            button.setAttribute("data-initialized", "true");
                            // Add visual feedback for buttons without handlers
                            button.addEventListener("click", function(e) {
                                if (!button.hasAttribute("onclick") && button.type !== "submit") {
                                    button.style.transform = "scale(0.95)";
                                    setTimeout(function() {
                                        button.style.transform = "";
                                    }, 150);
                                }
                            });
                        }
                    });

                    // Initialize canvas elements
                    var canvases = wrapper.querySelectorAll("canvas");
                    canvases.forEach(function(canvas) {
                        if (!canvas.hasAttribute("data-initialized")) {
                            canvas.setAttribute("data-initialized", "true");
                            // Ensure canvas is visible and properly sized
                            if (!canvas.style.display) {
                                canvas.style.display = "block";
                            }
                        }
                    });
                });
            });
        })();
        </script>';
    }

    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend_assets() {
        if (!self::has_container_blocks()) {
            return;
        }
        
        // CSS - Use the updated clean frontend CSS
        wp_enqueue_style(
            'cbd-frontend-clean',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend-clean.css',
            array(),
            CBD_VERSION . '-' . time() // Force cache bust
        );
        
        // JavaScript - Use the working frontend JS
        wp_enqueue_script(
            'cbd-frontend-working',
            CBD_PLUGIN_URL . 'assets/js/frontend-working.js',
            array('jquery'),
            CBD_VERSION . '-' . time(), // Force cache bust
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