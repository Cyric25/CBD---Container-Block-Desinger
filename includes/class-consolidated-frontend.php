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
        
        // Frontend CSS
        wp_enqueue_style(
            'cbd-frontend-consolidated',
            CBD_PLUGIN_URL . 'assets/css/frontend-consolidated.css',
            array(),
            CBD_VERSION
        );
        
        // Position-specific CSS
        wp_enqueue_style(
            'cbd-position-frontend',
            CBD_PLUGIN_URL . 'assets/css/frontend-positioning.css',
            array('cbd-frontend-consolidated'),
            CBD_VERSION
        );
        
        // Frontend JavaScript
        wp_enqueue_script(
            'cbd-frontend-consolidated',
            CBD_PLUGIN_URL . 'assets/js/frontend-consolidated.js',
            array('jquery'),
            CBD_VERSION,
            true
        );
        
        // Dashicons for frontend icons
        wp_enqueue_style('dashicons');
        
        // Localize script with all frontend strings
        wp_localize_script('cbd-frontend-consolidated', 'cbdFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd-frontend'),
            'strings' => $this->get_frontend_strings()
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
        return '<div class="cbd-container-error">' . 
               sprintf(__('Container-Block "%s" nicht gefunden.', 'container-block-designer'), 
                       esc_html($selected_block)) . 
               '</div>';
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
     * Generate complete container HTML
     */
    private function generate_container_html($styles, $features, $config, $content, $block_slug) {
        $container_classes = array('cbd-container');
        $container_attributes = array();
        
        // Add block-specific class
        $container_classes[] = 'cbd-block-' . sanitize_html_class($block_slug);
        
        // Add custom class if set
        if (!empty($config['customClass'])) {
            $container_classes[] = sanitize_html_class($config['customClass']);
        }
        
        // Feature-based classes
        if (!empty($features['collapsible']['enabled'])) {
            $container_classes[] = 'cbd-collapsible';
            if ($features['collapsible']['defaultState'] === 'collapsed') {
                $container_classes[] = 'cbd-collapsed';
            }
        }
        
        if (!empty($features['icon']['enabled'])) {
            $container_classes[] = 'cbd-has-icon';
        }
        
        // Check for positioned elements
        if ($this->has_outside_positioned_elements($features)) {
            $container_classes[] = 'cbd-has-outside-elements';
        }
        
        // Generate unique ID
        $container_id = 'cbd-container-' . uniqid();
        $container_attributes['id'] = $container_id;
        $container_attributes['class'] = implode(' ', $container_classes);
        
        // Build HTML
        $html = '';
        
        // Wrapper for outside elements if needed
        if ($this->has_outside_positioned_elements($features)) {
            $html .= '<div class="cbd-container-wrapper">';
        }
        
        // Main container
        $html .= '<div';
        foreach ($container_attributes as $attr => $value) {
            $html .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        // Add icon if enabled
        if (!empty($features['icon']['enabled'])) {
            $icon_class = sanitize_html_class($features['icon']['value'] ?? 'dashicons-admin-generic');
            $icon_color = !empty($features['icon']['color']) ? 
                'style="color: ' . esc_attr($features['icon']['color']) . '"' : '';
            $html .= '<span class="cbd-icon dashicons ' . $icon_class . '" ' . $icon_color . '></span>';
        }
        
        // Add collapse button if enabled
        if (!empty($features['collapsible']['enabled'])) {
            $expanded = $features['collapsible']['defaultState'] !== 'collapsed';
            $html .= '<button class="cbd-collapse-toggle" type="button" aria-expanded="' . ($expanded ? 'true' : 'false') . '" title="' . esc_attr__('Ein-/Ausklappen', 'container-block-designer') . '">';
            $html .= '<span class="cbd-collapse-icon"></span>';
            $html .= '</button>';
        }
        
        // Content wrapper
        $html .= '<div class="cbd-content">';
        $html .= $content;
        $html .= '</div>';
        
        $html .= '</div>'; // Close main container
        
        // Close wrapper if needed
        if ($this->has_outside_positioned_elements($features)) {
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Get block data from database
     */
    private function get_block_data($block_name) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s AND status = 'active'",
            $block_name
        ), ARRAY_A);
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