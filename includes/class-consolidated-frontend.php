<?php
/**
 * Container Block Designer - Consolidated Frontend Manager
 * Consolidates frontend.php and block-renderer.php functionality
 * 
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Consolidated Frontend Manager Class
 * Combines frontend asset management and block rendering
 */
class CBD_Consolidated_Frontend {
    
    /**
     * Container counter for numbering
     */
    private static $container_counter = array();
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize frontend hooks
     */
    private function __construct() {
        // Asset management
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_head', array($this, 'add_custom_styles'));
        add_action('wp_footer', array($this, 'add_frontend_scripts'));
        
        // Body classes
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // Block rendering
        add_filter('render_block', array($this, 'render_container_blocks'), 10, 2);
    }
    
    /**
     * Enqueue frontend assets (consolidated from both classes)
     */
    public function enqueue_frontend_assets() {
        // Only load if page has container blocks
        if (!$this->page_has_container_blocks()) {
            return;
        }
        
        // Frontend CSS - Use clean version without double styling
        wp_enqueue_style(
            'cbd-frontend-clean',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend-clean.css',
            array(),
            CBD_VERSION
        );
        
        // Position-specific CSS
        wp_enqueue_style(
            'cbd-position-frontend',
            CBD_PLUGIN_URL . 'assets/css/frontend-positioning.css',
            array('cbd-frontend-clean'),
            CBD_VERSION
        );
        
        // Frontend JavaScript - Working simple version
        wp_enqueue_script(
            'cbd-frontend-working',
            CBD_PLUGIN_URL . 'assets/js/frontend-working.js',
            array('jquery'),
            CBD_VERSION . '-working',
            true
        );
        
        // html2canvas for screenshot functionality (only load if screenshots are enabled)
        $script_dependencies = array('jquery');
        if ($this->page_has_screenshot_features()) {
            wp_enqueue_script(
                'html2canvas',
                'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
                array(),
                '1.4.1',
                true
            );
            
            // Update dependencies to include html2canvas
            $script_dependencies[] = 'html2canvas';
        }
        
        // Re-enqueue working frontend with updated dependencies
        wp_deregister_script('cbd-frontend-working');
        wp_enqueue_script(
            'cbd-frontend-working',
            CBD_PLUGIN_URL . 'assets/js/frontend-working.js',
            $script_dependencies,
            CBD_VERSION . '-working',
            true
        );
        
        // Dashicons for frontend icons
        wp_enqueue_style('dashicons');
        
        // Localize script with all frontend strings (for working version)
        wp_localize_script('cbd-frontend-working', 'cbdFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd-frontend'),
            'strings' => $this->get_frontend_strings(),
            'hasScreenshots' => $this->page_has_screenshot_features()
        ));
    }
    
    /**
     * Get all frontend localization strings
     */
    private function get_frontend_strings() {
        return array(
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
            'containerNumber' => __('Container Nummer', 'container-block-designer'),
            'expanded' => __('Ausgeklappt', 'container-block-designer'),
            'collapsed' => __('Eingeklappt', 'container-block-designer'),
            'toggleCollapse' => __('Ein-/Ausklappen', 'container-block-designer')
        );
    }
    
    /**
     * Add custom styles to head
     */
    public function add_custom_styles() {
        $custom_css = get_option('cbd_custom_css', '');
        
        if (!empty($custom_css)) {
            echo '<style id="cbd-custom-styles">' . wp_strip_all_tags($custom_css) . '</style>';
        }
        
        // Add dynamic container styles if needed
        $this->add_dynamic_container_styles();
    }
    
    /**
     * Add dynamic container styles
     */
    private function add_dynamic_container_styles() {
        global $post;
        
        if (!$post || !has_blocks($post->post_content)) {
            return;
        }
        
        $blocks = parse_blocks($post->post_content);
        $container_styles = $this->extract_container_styles($blocks);
        
        if (!empty($container_styles)) {
            echo '<style id="cbd-dynamic-styles">' . $container_styles . '</style>';
        }
    }
    
    /**
     * Extract container styles from blocks recursively
     */
    private function extract_container_styles($blocks) {
        $styles = '';
        
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'container-block-designer/container') {
                $attrs = $block['attrs'] ?? array();
                $selected_block = $attrs['selectedBlock'] ?? '';
                
                if ($selected_block) {
                    $block_data = $this->get_block_data($selected_block);
                    if ($block_data) {
                        $styles .= $this->generate_block_css($block_data, $selected_block);
                    }
                }
            }
            
            // Recursive check for inner blocks
            if (!empty($block['innerBlocks'])) {
                $styles .= $this->extract_container_styles($block['innerBlocks']);
            }
        }
        
        return $styles;
    }
    
    /**
     * Generate CSS for a specific block
     */
    private function generate_block_css($block_data, $block_slug) {
        $styles = json_decode($block_data['styles'], true) ?: array();
        if (empty($styles)) {
            return '';
        }
        
        $css = ".cbd-block-" . sanitize_html_class($block_slug) . " {\n";
        
        // Background
        if (!empty($styles['background']['color'])) {
            $css .= "    background-color: " . sanitize_hex_color($styles['background']['color']) . ";\n";
        }
        
        // Padding
        if (!empty($styles['padding']) && is_array($styles['padding'])) {
            $padding = $styles['padding'];
            $css .= sprintf(
                "    padding: %dpx %dpx %dpx %dpx;\n",
                intval($padding['top'] ?? 0),
                intval($padding['right'] ?? 0),
                intval($padding['bottom'] ?? 0),
                intval($padding['left'] ?? 0)
            );
        }
        
        // Border
        if (!empty($styles['border']) && is_array($styles['border'])) {
            $border = $styles['border'];
            if (!empty($border['width'])) {
                $css .= "    border-width: " . intval($border['width']) . "px;\n";
            }
            if (!empty($border['style'])) {
                $css .= "    border-style: " . sanitize_text_field($border['style']) . ";\n";
            }
            if (!empty($border['color'])) {
                $css .= "    border-color: " . sanitize_hex_color($border['color']) . ";\n";
            }
            if (!empty($border['radius'])) {
                $css .= "    border-radius: " . intval($border['radius']) . "px;\n";
            }
        }
        
        // Typography
        if (!empty($styles['typography']['color'])) {
            $css .= "    color: " . sanitize_hex_color($styles['typography']['color']) . ";\n";
        }
        
        $css .= "}\n\n";
        
        return $css;
    }
    
    /**
     * Add body classes based on container usage
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
        
        if ($this->has_icon_containers()) {
            $classes[] = 'cbd-has-icons';
        }
        
        return $classes;
    }
    
    /**
     * Add frontend scripts to footer
     */
    public function add_frontend_scripts() {
        if (!$this->page_has_container_blocks()) {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Consolidated frontend functionality
            
            // Collapsible containers
            $('.cbd-collapse-toggle').on('click', function(e) {
                e.preventDefault();
                var $container = $(this).closest('.cbd-collapsible');
                var $content = $container.find('.cbd-content');
                var $toggle = $(this);
                
                if ($container.hasClass('cbd-collapsed')) {
                    $content.slideDown(300);
                    $container.removeClass('cbd-collapsed');
                    $toggle.attr('aria-expanded', 'true').attr('title', cbdFrontend.strings.toggleCollapse);
                } else {
                    $content.slideUp(300);
                    $container.addClass('cbd-collapsed');
                    $toggle.attr('aria-expanded', 'false').attr('title', cbdFrontend.strings.toggleCollapse);
                }
            });
            
            // Copy functionality for containers
            $('.cbd-copy-button').on('click', function(e) {
                e.preventDefault();
                var $container = $(this).closest('.cbd-container');
                var text = $container.find('.cbd-content').text().trim();
                
                if (text) {
                    navigator.clipboard.writeText(text).then(function() {
                        // Success feedback
                        $(e.target).addClass('copied').text(cbdFrontend.strings.copySuccess);
                        setTimeout(function() {
                            $(e.target).removeClass('copied').text('Copy');
                        }, 2000);
                    }).catch(function() {
                        console.warn(cbdFrontend.strings.copyError);
                    });
                } else {
                    console.warn(cbdFrontend.strings.noTextFound);
                }
            });
            
            // Initialize positioned elements
            $('.cbd-positioned').each(function() {
                var $el = $(this);
                var position = $el.data('position');
                
                if (position) {
                    $el.css({
                        'position': 'absolute',
                        'top': position.top || 'auto',
                        'right': position.right || 'auto',
                        'bottom': position.bottom || 'auto',
                        'left': position.left || 'auto',
                        'z-index': position.zIndex || 1
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render container blocks (consolidated from block-renderer.php)
     */
    public function render_container_blocks($block_content, $block) {
        // Only process our container blocks
        if ($block['blockName'] !== 'container-block-designer/container') {
            return $block_content;
        }
        
        $attributes = $block['attrs'] ?? array();
        $selected_block = $attributes['selectedBlock'] ?? '';
        
        if (empty($selected_block)) {
            return $this->render_placeholder_block($block_content);
        }
        
        $block_data = $this->get_block_data($selected_block);
        if (!$block_data) {
            return $this->render_error_block($selected_block);
        }
        
        return $this->render_container_block($block_data, $attributes, $block_content);
    }
    
    /**
     * Render placeholder block
     */
    private function render_placeholder_block($content) {
        if (!empty($content)) {
            return '<div class="cbd-container cbd-placeholder">' . $content . '</div>';
        }
        return '<div class="cbd-container-placeholder">' . 
               __('Bitte wählen Sie einen Container-Block aus.', 'container-block-designer') . 
               '</div>';
    }
    
    /**
     * Render error block
     */
    private function render_error_block($selected_block) {
        // Check if user can manage options (admin)
        $is_admin = current_user_can('manage_options');
        
        $error_message = sprintf(__('Container-Block "%s" nicht gefunden.', 'container-block-designer'), 
                                esc_html($selected_block));
        
        // Get similar blocks suggestion
        $similar_blocks = $this->get_similar_blocks($selected_block);
        $suggestions = '';
        
        if (!empty($similar_blocks)) {
            $suggestions = '<p style="margin-top: 15px; font-size: 0.9em; color: #666;">' .
                          __('Verfügbare Container-Blöcke:', 'container-block-designer') . '</p>' .
                          '<ul style="margin: 5px 0; padding-left: 20px; font-size: 0.9em; color: #2271b1;">';
            foreach ($similar_blocks as $block) {
                $suggestions .= '<li><code>' . esc_html($block['name']) . '</code> - ' . esc_html($block['title'] ?: $block['name']) . '</li>';
            }
            $suggestions .= '</ul>';
        }
        
        $admin_help = '';
        if ($is_admin) {
            $admin_help = '<p style="margin-top: 10px; font-size: 0.9em; color: #666;">' .
                         __('Als Administrator können Sie:', 'container-block-designer') . '</p>' .
                         '<ul style="margin: 5px 0; padding-left: 20px; font-size: 0.9em; color: #666;">' .
                         '<li>' . sprintf(__('<a href="%s">Einen neuen Block erstellen</a>', 'container-block-designer'), admin_url('admin.php?page=cbd-new-block')) . '</li>' .
                         '<li>' . sprintf(__('<a href="%s">Vorhandene Blöcke verwalten</a>', 'container-block-designer'), admin_url('admin.php?page=container-block-designer')) . '</li>' .
                         '<li>' . __('Oder den Block-Namen in diesem Gutenberg-Block ändern', 'container-block-designer') . '</li>' .
                         '</ul>';
        } else {
            $admin_help = '<p style="margin-top: 10px; font-size: 0.9em; color: #666;">' .
                         __('Bitte wenden Sie sich an den Administrator, um dieses Problem zu beheben.', 'container-block-designer') . '</p>';
        }
        
        return '<div class="cbd-container-error" style="border: 2px dashed #e74c3c; background: #fdf2f2; padding: 20px; border-radius: 8px; margin: 10px 0;">' . 
               '<div style="color: #e74c3c; font-weight: 600; margin-bottom: 5px;">' . 
               '⚠️ ' . $error_message . 
               '</div>' .
               $suggestions .
               $admin_help .
               '</div>';
    }
    
    /**
     * Get similar or available blocks
     */
    private function get_similar_blocks($search_name) {
        global $wpdb;
        
        // Get all active blocks
        $blocks = $wpdb->get_results(
            "SELECT name, title FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active' ORDER BY name ASC LIMIT 10",
            ARRAY_A
        );
        
        return $blocks ?: array();
    }
    
    /**
     * Render container block with full functionality
     */
    private function render_container_block($block_data, $attributes, $content) {
        // Parse block data
        $styles = json_decode($block_data['styles'], true) ?: array();
        $features = json_decode($block_data['features'], true) ?: array();
        $config = json_decode($block_data['config'], true) ?: array();
        
        // Override with block attributes if present
        if (!empty($attributes['blockFeatures'])) {
            $features = array_merge($features, $attributes['blockFeatures']);
        }
        
        if (!empty($attributes['blockConfig'])) {
            $config = array_merge($config, $attributes['blockConfig']);
        }
        
        // Generate container HTML
        return $this->generate_container_html($styles, $features, $config, $content, $block_data['name']);
    }
    
    /**
     * Generate complete container HTML with proper two-layer structure
     */
    private function generate_container_html($styles, $features, $config, $content, $block_slug) {
        // Generate unique ID
        $container_id = 'cbd-container-' . uniqid();
        
        // Outer wrapper classes and attributes (.cbd-container)
        $wrapper_classes = array('cbd-container');
        $wrapper_attributes = array('id' => $container_id);
        
        // Add feature-based data attributes to wrapper for JavaScript
        if (!empty($features['collapse']['enabled'])) {
            $wrapper_attributes['data-collapse'] = json_encode($features['collapse']);
            $wrapper_classes[] = 'cbd-collapsible';
            if (($features['collapse']['defaultState'] ?? 'expanded') === 'collapsed') {
                $wrapper_classes[] = 'cbd-collapsed';
            }
        }
        
        if (!empty($features['copyText']['enabled'])) {
            $wrapper_attributes['data-copy-text'] = json_encode($features['copyText']);
        }
        
        if (!empty($features['screenshot']['enabled'])) {
            $wrapper_attributes['data-screenshot'] = json_encode($features['screenshot']);
        }
        
        if (!empty($features['icon']['enabled'])) {
            $wrapper_attributes['data-icon'] = json_encode($features['icon']);
        }
        
        if (!empty($features['numbering']['enabled'])) {
            $wrapper_attributes['data-numbering'] = json_encode($features['numbering']);
        }
        
        $wrapper_attributes['class'] = implode(' ', $wrapper_classes);
        
        // Inner content block classes (.cbd-container-block)
        $content_classes = array('cbd-container-block');
        $content_attributes = array();
        
        // Add block-specific class to content block
        $content_classes[] = 'cbd-block-' . sanitize_html_class($block_slug);
        
        // Add custom class if set
        if (!empty($config['customClass'])) {
            $content_classes[] = sanitize_html_class($config['customClass']);
        }
        
        // Add animation class if effects are enabled
        if (!empty($styles['effects']['animation']['hover']) && $styles['effects']['animation']['hover'] !== 'none') {
            $content_classes[] = 'cbd-animated';
        }
        
        $content_attributes['class'] = implode(' ', $content_classes);
        
        // Generate inline styles for content block only
        $inline_styles = $this->generate_inline_styles($styles, $features);
        if (!empty($inline_styles)) {
            $content_attributes['style'] = $inline_styles;
        }
        
        // Build HTML with proper two-layer structure
        $html = '';
        
        // Start outer wrapper (.cbd-container) - for controls and positioning
        $html .= '<div';
        foreach ($wrapper_attributes as $attr => $value) {
            $html .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        // Header will be added inside content block now
        
        // Add icons (outside the styled content)
        if (!empty($features['icon']['enabled'])) {
            $icon_class = sanitize_html_class($features['icon']['value'] ?? 'dashicons-admin-generic');
            $icon_position = $features['icon']['position'] ?? 'top-right';
            $icon_color = !empty($features['icon']['color']) ? 
                'style="color: ' . esc_attr($features['icon']['color']) . '"' : '';
            $html .= '<span class="cbd-icon ' . esc_attr($icon_position) . '" ' . $icon_color . '>';
            $html .= '<i class="dashicons ' . $icon_class . '"></i>';
            $html .= '</span>';
        }
        
        // Action buttons are now only in the dropdown menu - no separate buttons
        
        // Content wrapper div with proper ID for collapse functionality
        $content_wrapper_class = 'cbd-content';
        $content_wrapper_id = $container_id . '-content';
        
        $html .= '<div class="' . $content_wrapper_class . '" id="' . esc_attr($content_wrapper_id) . '">';
        
        // Inner content block (.cbd-container-block) - this gets the visual styling
        $html .= '<div';
        foreach ($content_attributes as $attr => $value) {
            $html .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        // NEW: Header with title and dropdown menu
        $block_title = !empty($config['blockTitle']) ? $config['blockTitle'] : '';
        $has_features = !empty($features['collapse']['enabled']) || !empty($features['copyText']['enabled']) || !empty($features['screenshot']['enabled']);
        
        if ($block_title || $has_features) {
            $html .= '<div class="cbd-header">';
            
            // Block title
            if ($block_title) {
                $html .= '<h3 class="cbd-header-title">' . esc_html($block_title) . '</h3>';
            }
            
            // Feature dropdown menu
            if ($has_features) {
                $html .= '<div class="cbd-header-menu">';
                $html .= '<button class="cbd-menu-toggle" type="button" aria-expanded="false">';
                $html .= '<i class="dashicons dashicons-admin-generic"></i>';
                $html .= '<span>Features</span>';
                $html .= '</button>';
                
                $html .= '<div class="cbd-dropdown-menu">';
                
                // Collapse toggle
                if (!empty($features['collapse']['enabled'])) {
                    $expanded = ($features['collapse']['defaultState'] ?? 'expanded') !== 'collapsed';
                    $html .= '<button class="cbd-dropdown-item cbd-collapse-toggle" type="button" aria-expanded="' . ($expanded ? 'true' : 'false') . '" aria-controls="' . esc_attr($container_id) . '-content">';
                    $html .= '<i class="dashicons dashicons-arrow-' . ($expanded ? 'up' : 'down') . '-alt2"></i>';
                    $html .= '<span>' . esc_html($features['collapse']['label'] ?? 'Einklappen') . '</span>';
                    $html .= '</button>';
                }
                
                // Copy text
                if (!empty($features['copyText']['enabled'])) {
                    $html .= '<button class="cbd-dropdown-item cbd-copy-text" data-container-id="' . esc_attr($container_id) . '">';
                    $html .= '<i class="dashicons dashicons-clipboard"></i>';
                    $html .= '<span>' . esc_html($features['copyText']['buttonText'] ?? 'Text kopieren') . '</span>';
                    $html .= '</button>';
                }
                
                // Screenshot
                if (!empty($features['screenshot']['enabled'])) {
                    $html .= '<button class="cbd-dropdown-item cbd-screenshot" data-container-id="' . esc_attr($container_id) . '">';
                    $html .= '<i class="dashicons dashicons-camera"></i>';
                    $html .= '<span>' . esc_html($features['screenshot']['buttonText'] ?? 'Screenshot') . '</span>';
                    $html .= '</button>';
                }
                
                $html .= '</div>'; // Close dropdown menu
                $html .= '</div>'; // Close header menu
            }
            
            $html .= '</div>'; // Close header
        }
        
        // Wrap the actual content (not the header) in a collapsible container
        $html .= '<div class="cbd-container-content">';
        
        // Add numbering inside the content block
        if (!empty($features['numbering']['enabled'])) {
            // Numbering will be handled by JavaScript after rendering
        }
        
        // Actual content
        $html .= $content;
        
        $html .= '</div>'; // Close .cbd-container-content
        
        $html .= '</div>'; // Close .cbd-container-block
        $html .= '</div>'; // Close .cbd-content wrapper
        $html .= '</div>'; // Close .cbd-container
        
        return $html;
    }
    
    /**
     * Generate action buttons - DISABLED: All buttons now in dropdown menu only
     */
    private function generate_action_buttons($features, $container_id) {
        // All buttons are now in the dropdown menu only - no separate buttons
        return '';
    }
    
    /**
     * Generate inline styles for the container
     */
    private function generate_inline_styles($styles, $features) {
        $css_rules = array();
        
        // Basic styles
        if (!empty($styles['background']['color'])) {
            $css_rules[] = 'background-color: ' . esc_attr($styles['background']['color']);
        }
        
        if (!empty($styles['text']['color'])) {
            $css_rules[] = 'color: ' . esc_attr($styles['text']['color']);
        }
        
        // Border styles
        if (!empty($styles['border'])) {
            $border = $styles['border'];
            if (!empty($border['width']) && !empty($border['color']) && !empty($border['style'])) {
                $css_rules[] = sprintf('border: %dpx %s %s', 
                    intval($border['width']), 
                    esc_attr($border['style']), 
                    esc_attr($border['color'])
                );
            }
            
            if (!empty($border['radius'])) {
                $css_rules[] = 'border-radius: ' . intval($border['radius']) . 'px';
            }
        }
        
        // Background gradient
        if (!empty($styles['background']['type']) && $styles['background']['type'] === 'gradient') {
            $gradient = $styles['background']['gradient'];
            if (!empty($gradient['color1']) && !empty($gradient['color2'])) {
                $angle = intval($gradient['angle'] ?? 45);
                $gradient_css = sprintf('linear-gradient(%ddeg, %s, %s', 
                    $angle, 
                    esc_attr($gradient['color1']), 
                    esc_attr($gradient['color2'])
                );
                
                if (!empty($gradient['color3'])) {
                    $gradient_css .= ', ' . esc_attr($gradient['color3']);
                }
                $gradient_css .= ')';
                
                $css_rules[] = 'background: ' . $gradient_css;
            }
        }
        
        // Modern effects
        if (!empty($styles['effects'])) {
            $effects = $styles['effects'];
            
            // Glassmorphism
            if (!empty($effects['glassmorphism']['enabled'])) {
                $glass = $effects['glassmorphism'];
                $opacity = floatval($glass['opacity'] ?? 0.1);
                $blur = intval($glass['blur'] ?? 10);
                $saturate = intval($glass['saturate'] ?? 100);
                
                $css_rules[] = sprintf('backdrop-filter: blur(%dpx) saturate(%d%%)', $blur, $saturate);
                $css_rules[] = sprintf('-webkit-backdrop-filter: blur(%dpx) saturate(%d%%)', $blur, $saturate);
                
                if (!empty($glass['color'])) {
                    $css_rules[] = sprintf('background-color: rgba(%s, %f)', 
                        $this->hex_to_rgb($glass['color']), 
                        $opacity
                    );
                }
            }
            
            // Neumorphism
            if (!empty($effects['neumorphism']['enabled'])) {
                $neuro = $effects['neumorphism'];
                $intensity = intval($neuro['intensity'] ?? 10);
                $distance = intval($neuro['distance'] ?? 15);
                $style = $neuro['style'] ?? 'raised';
                
                if ($style === 'raised') {
                    $css_rules[] = sprintf('box-shadow: %dpx %dpx %dpx rgba(0,0,0,0.1), -%dpx -%dpx %dpx rgba(255,255,255,0.7)', 
                        $distance/3, $distance/3, $intensity,
                        $distance/3, $distance/3, $intensity
                    );
                } else {
                    $css_rules[] = sprintf('box-shadow: inset %dpx %dpx %dpx rgba(0,0,0,0.1), inset -%dpx -%dpx %dpx rgba(255,255,255,0.7)', 
                        $distance/3, $distance/3, $intensity,
                        $distance/3, $distance/3, $intensity
                    );
                }
            }
        }
        
        return implode('; ', $css_rules);
    }
    
    /**
     * Convert hex color to RGB values
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        return sprintf('%d, %d, %d', 
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }
    
    /**
     * Get block data from database
     */
    private function get_block_data($block_name) {
        global $wpdb;
        
        // First try slug match (most common case for Gutenberg blocks)
        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s AND status = 'active'",
            $block_name
        ), ARRAY_A);
        
        // If not found by slug, try exact name match
        if (!$block) {
            $block = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s AND status = 'active'",
                $block_name
            ), ARRAY_A);
        }
        
        // If still not found, try case-insensitive name search
        if (!$block) {
            $block = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE LOWER(name) = LOWER(%s) AND status = 'active'",
                $block_name
            ), ARRAY_A);
        }
        
        return $block;
    }
    
    /**
     * Check if page has container blocks
     */
    private function page_has_container_blocks() {
        global $post;
        
        if (!$post || !has_blocks($post->post_content)) {
            return false;
        }
        
        $blocks = parse_blocks($post->post_content);
        return $this->search_for_container_blocks($blocks);
    }
    
    /**
     * Search for container blocks recursively
     */
    private function search_for_container_blocks($blocks) {
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'container-block-designer/container') {
                return true;
            }
            
            if (!empty($block['innerBlocks'])) {
                if ($this->search_for_container_blocks($block['innerBlocks'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if current page has positioned elements
     */
    private function has_positioned_elements() {
        // Implementation for checking positioned elements
        global $post;
        
        if (!$post || !has_blocks($post->post_content)) {
            return false;
        }
        
        // This would require parsing blocks and checking for position features
        // Simplified for now
        return false;
    }
    
    /**
     * Check if current page has collapsible containers
     */
    private function has_collapsible_containers() {
        global $post;
        
        if (!$post || !has_blocks($post->post_content)) {
            return false;
        }
        
        $blocks = parse_blocks($post->post_content);
        return $this->search_for_collapsible_blocks($blocks);
    }
    
    /**
     * Search for collapsible blocks recursively
     */
    private function search_for_collapsible_blocks($blocks) {
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'container-block-designer/container') {
                $attrs = $block['attrs'] ?? array();
                if (!empty($attrs['enableCollapse'])) {
                    return true;
                }
                
                // Check selected block features
                $selected_block = $attrs['selectedBlock'] ?? '';
                if ($selected_block) {
                    $block_data = $this->get_block_data($selected_block);
                    if ($block_data) {
                        $features = json_decode($block_data['features'], true) ?: array();
                        if (!empty($features['collapsible']['enabled'])) {
                            return true;
                        }
                    }
                }
            }
            
            if (!empty($block['innerBlocks'])) {
                if ($this->search_for_collapsible_blocks($block['innerBlocks'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if current page has icon containers
     */
    private function has_icon_containers() {
        // Similar implementation to collapsible check
        return false; // Simplified for now
    }
    
    /**
     * Check if page has screenshot features enabled
     */
    private function page_has_screenshot_features() {
        global $post;
        
        if (!$post || !has_blocks($post->post_content)) {
            return false;
        }
        
        $blocks = parse_blocks($post->post_content);
        return $this->search_for_screenshot_blocks($blocks);
    }
    
    /**
     * Search for blocks with screenshot features
     */
    private function search_for_screenshot_blocks($blocks) {
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'container-block-designer/container') {
                $attrs = $block['attrs'] ?? array();
                $selected_block = $attrs['selectedBlock'] ?? '';
                
                if ($selected_block) {
                    $block_data = $this->get_block_data($selected_block);
                    if ($block_data) {
                        $features = json_decode($block_data['features'], true) ?: array();
                        if (!empty($features['screenshot']['enabled'])) {
                            return true;
                        }
                    }
                }
            }
            
            if (!empty($block['innerBlocks'])) {
                if ($this->search_for_screenshot_blocks($block['innerBlocks'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if features contain outside positioned elements
     */
    private function has_outside_positioned_elements($features) {
        if (empty($features['positioning'])) {
            return false;
        }
        
        $positioning = $features['positioning'];
        return !empty($positioning['outsideElements']);
    }
}