<?php
/**
 * Block Frontend Renderer - Verbesserte Version
 * 
 * Behebt das Problem mit doppelt angezeigten Buttons bei Collapse-Feature
 * 
 * @package ContainerBlockDesigner
 * @version 2.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Frontend_Renderer {
    
    /**
     * Initialize the renderer
     */
    public static function init() {
        add_filter('render_block', array(__CLASS__, 'render_container_block'), 10, 2);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
    }
    
    /**
     * Render container block
     */
    public static function render_container_block($block_content, $block) {
        // Only process our container blocks
        if ($block['blockName'] !== 'container-block-designer/container') {
            return $block_content;
        }
        
        // Extract block data
        $attributes = $block['attrs'] ?? array();
        $selectedBlock = $attributes['selectedBlock'] ?? '';
        $features = $attributes['features'] ?? array();
        
        // Get block configuration if selected
        $config = array();
        if ($selectedBlock) {
            global $wpdb;
            $block_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s AND status = 'active'",
                $selectedBlock
            ));
            
            if ($block_data) {
                $config = json_decode($block_data->config, true) ?: array();
                // Merge database features with block attributes (block attributes take precedence)
                $db_features = json_decode($block_data->features, true) ?: array();
                $features = wp_parse_args($features, $db_features);
            }
        }
        
        // Build data attributes for JavaScript
        $data_attributes = self::build_data_attributes($features);
        
        // Parse and enhance the HTML
        $enhanced_content = self::enhance_block_html($block_content, $data_attributes, $features, $config);
        
        return $enhanced_content;
    }
    
    /**
     * Build data attributes for features
     */
    private static function build_data_attributes($features) {
        $attributes = array();
        
        // Icon feature
        if (!empty($features['icon']['enabled'])) {
            $attributes['data-icon'] = 'true';
            $attributes['data-icon-value'] = esc_attr($features['icon']['value'] ?? 'dashicons-admin-generic');
        }
        
        // Collapse feature
        if (!empty($features['collapse']['enabled'])) {
            $attributes['data-collapse'] = 'true';
            $attributes['data-collapse-default'] = esc_attr($features['collapse']['defaultState'] ?? 'expanded');
        }
        
        // Numbering feature
        if (!empty($features['numbering']['enabled'])) {
            $attributes['data-numbering'] = 'true';
            $attributes['data-numbering-format'] = esc_attr($features['numbering']['format'] ?? 'numeric');
        }
        
        // Copy feature
        if (!empty($features['copyText']['enabled'])) {
            $attributes['data-copy'] = 'true';
            $attributes['data-copy-text'] = esc_attr($features['copyText']['buttonText'] ?? __('Text kopieren', 'container-block-designer'));
        }
        
        // Screenshot feature
        if (!empty($features['screenshot']['enabled'])) {
            $attributes['data-screenshot'] = 'true';
            $attributes['data-screenshot-text'] = esc_attr($features['screenshot']['buttonText'] ?? __('Screenshot', 'container-block-designer'));
        }
        
        return $attributes;
    }
    
    /**
     * Enhance block HTML with features
     */
    private static function enhance_block_html($content, $data_attributes, $features, $config) {
        if (empty($content)) {
            return $content;
        }
        
        // Use DOMDocument to parse and modify HTML
        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        
        // Suppress errors for malformed HTML and load content
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Find the main container
        $containers = $xpath->query("//div[contains(@class, 'cbd-container')]");
        
        if ($containers->length === 0) {
            return $content;
        }
        
        $container = $containers->item(0);
        
        // Add data attributes to container
        foreach ($data_attributes as $attr => $value) {
            $container->setAttribute($attr, $value);
        }
        
        // Add icon if enabled
        if (!empty($features['icon']['enabled'])) {
            self::add_icon_to_container($dom, $container, $features['icon']);
        }
        
        // Handle collapse feature
        if (!empty($features['collapse']['enabled'])) {
            self::setup_collapse_structure($dom, $container, $features);
        } else {
            // Only add action buttons if collapse is NOT enabled (to avoid duplicates)
            self::add_action_buttons($dom, $container, $features);
        }
        
        // Apply custom styles if available
        if (!empty($config['styles'])) {
            self::apply_inline_styles($container, $config['styles']);
        }
        
        // Return the modified HTML
        $html = $dom->saveHTML($container);
        
        // Clean up XML declaration and encoding issues
        $html = str_replace('<?xml encoding="UTF-8"?>', '', $html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $html;
    }
    
    /**
     * Add icon to container
     */
    private static function add_icon_to_container($dom, $container, $icon_config) {
        $icon_value = esc_attr($icon_config['value'] ?? 'dashicons-admin-generic');
        
        // Create icon element
        $icon_div = $dom->createElement('div');
        $icon_div->setAttribute('class', 'cbd-container-icon');
        
        $icon_span = $dom->createElement('span');
        $icon_span->setAttribute('class', 'dashicons ' . $icon_value);
        
        $icon_div->appendChild($icon_span);
        
        // Insert icon at the beginning of the container
        if ($container->firstChild) {
            $container->insertBefore($icon_div, $container->firstChild);
        } else {
            $container->appendChild($icon_div);
        }
    }
    
    /**
     * Setup collapse structure
     */
    private static function setup_collapse_structure($dom, $container, $features) {
        // Create collapse header
        $header = $dom->createElement('div');
        $header->setAttribute('class', 'cbd-collapse-header');
        
        // Toggle icon
        $toggle_icon = $dom->createElement('span');
        $toggle_icon->setAttribute('class', 'dashicons dashicons-arrow-down-alt2 cbd-collapse-toggle');
        $header->appendChild($toggle_icon);
        
        // Title
        $title = $dom->createElement('span');
        $title->setAttribute('class', 'cbd-collapse-title');
        $title->textContent = __('Container Inhalt', 'container-block-designer');
        $header->appendChild($title);
        
        // Action buttons in header (always present, will be shown/hidden by CSS/JS based on state)
        $header_buttons = self::create_action_buttons_element($dom, $features, 'header');
        if ($header_buttons) {
            $header->appendChild($header_buttons);
        }
        
        // Wrap existing content in collapse content div
        $content_wrapper = $dom->createElement('div');
        $content_wrapper->setAttribute('class', 'cbd-collapse-content');
        
        // Move all existing child nodes to the content wrapper
        $children = array();
        foreach ($container->childNodes as $child) {
            $children[] = $child;
        }
        
        foreach ($children as $child) {
            $content_wrapper->appendChild($child);
        }
        
        // Add action buttons to content (for expanded state)
        $content_buttons = self::create_action_buttons_element($dom, $features, 'content');
        if ($content_buttons) {
            $content_wrapper->appendChild($content_buttons);
        }
        
        // Add header and content wrapper to container
        $container->appendChild($header);
        $container->appendChild($content_wrapper);
        
        // Add CSS class for collapse functionality
        $existing_classes = $container->getAttribute('class');
        $container->setAttribute('class', $existing_classes . ' cbd-collapsible');
    }
    
    /**
     * Create action buttons element
     */
    private static function create_action_buttons_element($dom, $features, $context = 'content') {
        $has_buttons = !empty($features['copyText']['enabled']) || !empty($features['screenshot']['enabled']);
        
        if (!$has_buttons) {
            return null;
        }
        
        $buttons_container = $dom->createElement('div');
        $buttons_container->setAttribute('class', 'cbd-action-buttons cbd-action-buttons-' . $context);
        
        // Copy button
        if (!empty($features['copyText']['enabled'])) {
            $copy_button = $dom->createElement('button');
            $copy_button->setAttribute('type', 'button');
            $copy_button->setAttribute('class', 'cbd-copy-button');
            
            $copy_icon = $dom->createElement('span');
            $copy_icon->setAttribute('class', 'dashicons dashicons-clipboard');
            $copy_button->appendChild($copy_icon);
            
            $copy_text = $dom->createElement('span');
            $copy_text->textContent = $features['copyText']['buttonText'] ?? __('Text kopieren', 'container-block-designer');
            $copy_button->appendChild($copy_text);
            
            $buttons_container->appendChild($copy_button);
        }
        
        // Screenshot button
        if (!empty($features['screenshot']['enabled'])) {
            $screenshot_button = $dom->createElement('button');
            $screenshot_button->setAttribute('type', 'button');
            $screenshot_button->setAttribute('class', 'cbd-screenshot-button');
            
            $screenshot_icon = $dom->createElement('span');
            $screenshot_icon->setAttribute('class', 'dashicons dashicons-camera');
            $screenshot_button->appendChild($screenshot_icon);
            
            $screenshot_text = $dom->createElement('span');
            $screenshot_text->textContent = $features['screenshot']['buttonText'] ?? __('Screenshot', 'container-block-designer');
            $screenshot_button->appendChild($screenshot_text);
            
            $buttons_container->appendChild($screenshot_button);
        }
        
        return $buttons_container;
    }
    
    /**
     * Add action buttons (when collapse is NOT enabled)
     */
    private static function add_action_buttons($dom, $container, $features) {
        $buttons = self::create_action_buttons_element($dom, $features, 'default');
        
        if ($buttons) {
            $container->appendChild($buttons);
        }
    }
    
    /**
     * Apply inline styles from configuration
     */
    private static function apply_inline_styles($container, $styles) {
        $inline_styles = array();
        
        // Padding
        if (isset($styles['padding'])) {
            $padding = $styles['padding'];
            $inline_styles[] = sprintf(
                'padding: %dpx %dpx %dpx %dpx',
                $padding['top'] ?? 20,
                $padding['right'] ?? 20,
                $padding['bottom'] ?? 20,
                $padding['left'] ?? 20
            );
        }
        
        // Margin
        if (isset($styles['margin'])) {
            $margin = $styles['margin'];
            $inline_styles[] = sprintf(
                'margin: %dpx %dpx %dpx %dpx',
                $margin['top'] ?? 0,
                $margin['right'] ?? 0,
                $margin['bottom'] ?? 20,
                $margin['left'] ?? 0
            );
        }
        
        // Background Color
        if (!empty($styles['backgroundColor'])) {
            $inline_styles[] = 'background-color: ' . esc_attr($styles['backgroundColor']);
        }
        
        // Text Color
        if (!empty($styles['textColor'])) {
            $inline_styles[] = 'color: ' . esc_attr($styles['textColor']);
        }
        
        // Border
        if (isset($styles['border'])) {
            $border = $styles['border'];
            if (!empty($border['width']) && !empty($border['style']) && !empty($border['color'])) {
                $inline_styles[] = sprintf(
                    'border: %dpx %s %s',
                    intval($border['width']),
                    esc_attr($border['style']),
                    esc_attr($border['color'])
                );
            }
        }
        
        // Border Radius
        if (!empty($styles['borderRadius'])) {
            $inline_styles[] = 'border-radius: ' . intval($styles['borderRadius']) . 'px';
        }
        
        // Apply styles
        if (!empty($inline_styles)) {
            $existing_style = $container->getAttribute('style');
            $new_style = $existing_style . '; ' . implode('; ', $inline_styles);
            $container->setAttribute('style', $new_style);
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend_assets() {
        // Check if our block is used on this page
        if (!has_block('container-block-designer/container')) {
            return;
        }
        
        // Frontend styles
        wp_enqueue_style(
            'cbd-frontend',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend.css',
            array(),
            CBD_VERSION
        );
        
        // Advanced features styles
        wp_enqueue_style(
            'cbd-advanced-features',
            CBD_PLUGIN_URL . 'assets/css/advanced-features.css',
            array('dashicons'),
            CBD_VERSION
        );
        
        // Advanced features script
        wp_enqueue_script(
            'cbd-advanced-features',
            CBD_PLUGIN_URL . 'assets/js/advanced-features.js',
            array('jquery'),
            CBD_VERSION,
            true
        );
        
        // Html2Canvas for screenshot feature
        wp_enqueue_script(
            'html2canvas',
            'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
            array(),
            '1.4.1',
            true
        );
        
        // Localize script for translations
        wp_localize_script('cbd-advanced-features', 'cbdFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_frontend'),
            'strings' => array(
                'copied' => __('Text kopiert!', 'container-block-designer'),
                'copyError' => __('Fehler beim Kopieren', 'container-block-designer'),
                'screenshotSaved' => __('Screenshot gespeichert!', 'container-block-designer'),
                'screenshotError' => __('Fehler beim Erstellen des Screenshots', 'container-block-designer'),
                'expand' => __('Ausklappen', 'container-block-designer'),
                'collapse' => __('Einklappen', 'container-block-designer')
            )
        ));
    }
}

// Initialize the renderer
CBD_Frontend_Renderer::init();