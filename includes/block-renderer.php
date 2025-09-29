<?php
/**
 * Container Block Designer - Block Renderer (Legacy Wrapper)
 * Now delegates to Consolidated Frontend Manager
 * Version: 2.6.0
 * 
 * Datei speichern als: includes/block-renderer.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load consolidated frontend
require_once CBD_PLUGIN_DIR . 'includes/class-consolidated-frontend.php';

/**
 * Block Renderer Class - Legacy wrapper
 */
class CBD_Block_Renderer {
    
    /**
     * Container counter for numbering - Legacy compatibility
     */
    private static $container_counter = array();
    
    /**
     * Render container block - Updated with new structure
     */
    public static function render_container_block($attributes, $content) {
        // DEBUG: Add HTML comment to verify this renderer is active
        $html = '<!-- ======================================== -->';
        $html .= '<!-- CBD DEBUG: BLOCK RENDERER IS ACTIVE!!! -->';
        $html .= '<!-- TIME: ' . date('Y-m-d H:i:s') . ' -->';
        $html .= '<!-- ======================================== -->';
        
        // Get block data
        $selected_block = $attributes['selectedBlock'] ?? '';
        
        if (empty($selected_block)) {
            return $html . '<div class="cbd-container-placeholder">' . __('Bitte wählen Sie einen Container-Block aus.', 'container-block-designer') . '</div>';
        }
        
        $block_data = cbd_get_block_by_slug($selected_block);
        
        if (!$block_data) {
            return $html . '<div class="cbd-container-error">' . __('Container-Block nicht gefunden.', 'container-block-designer') . '</div>';
        }
        
        // Parse block data
        $styles = json_decode($block_data['styles'], true) ?: array();
        $features = json_decode($block_data['features'], true) ?: array();
        $config = json_decode($block_data['config'], true) ?: array();
        
        // DEBUG: Add debug output to see what features are available
        $html .= '<!-- CBD DEBUG: Features available: ' . json_encode($features) . ' -->';
        $html .= '<!-- CBD DEBUG: Config available: ' . json_encode($config) . ' -->';
        
        // Override with block attributes if present
        if (!empty($attributes['blockFeatures'])) {
            $features = array_merge($features, $attributes['blockFeatures']);
        }
        
        if (!empty($attributes['blockConfig'])) {
            $config = array_merge($config, $attributes['blockConfig']);
        }
        
        // Generate unique container ID
        $container_id = 'cbd-container-' . uniqid();
        
        // Build wrapper classes (.cbd-container - transparent wrapper)
        $wrapper_classes = array('cbd-container');
        $wrapper_attributes = array('id' => $container_id);
        
        // Add legacy classes for backward compatibility
        $wrapper_classes[] = 'cbd-block-' . sanitize_html_class($selected_block);
        
        // Add custom class if set
        if (!empty($config['customClass'])) {
            $wrapper_classes[] = sanitize_html_class($config['customClass']);
        }
        
        $wrapper_attributes['class'] = implode(' ', $wrapper_classes);
        
        // Inner content block classes (.cbd-container-block)
        $content_classes = array('cbd-container-block');
        $content_attributes = array();
        
        // Add block-specific class to content block
        $content_classes[] = 'cbd-block-' . sanitize_html_class($selected_block);
        
        $content_attributes['class'] = implode(' ', $content_classes);
        
        // Generate inline styles for content block only
        $inline_styles = self::generate_container_styles($styles);
        if (!empty($inline_styles)) {
            $content_attributes['style'] = implode('; ', $inline_styles);
        }
        
        // Start outer wrapper (.cbd-container) - for controls and positioning
        $html .= '<div';
        foreach ($wrapper_attributes as $attr => $value) {
            $html .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        // Add numbering OUTSIDE everything (in the main container)
        // ALWAYS show numbering for now until we fix the feature detection
        $numbering_counter = 1; // Simple counter for now
        $html .= '<div class="cbd-container-number cbd-outside-number" data-number="' . esc_attr($numbering_counter) . '">';
        $html .= esc_html($numbering_counter);
        $html .= '</div>';
        
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
        
        // Selection-based feature menu - ALWAYS show for now
        $html .= '<!-- CBD DEBUG: Always showing feature menu -->';
        $html .= '<div class="cbd-selection-menu" style="display: none;">';
        $html .= '<button class="cbd-menu-toggle" type="button" aria-expanded="false">';
        $html .= '<i class="dashicons dashicons-admin-generic"></i>';
        $html .= '</button>';
        
        $html .= '<div class="cbd-dropdown-menu">';
        
        // Always show collapse
        $html .= '<button class="cbd-dropdown-item cbd-collapse-toggle" type="button" aria-expanded="true" aria-controls="' . esc_attr($container_id) . '-content">';
        $html .= '<i class="dashicons dashicons-arrow-up-alt2"></i>';
        $html .= '<span>Einklappen</span>';
        $html .= '</button>';
        
        // Always show copy text
        $html .= '<button class="cbd-dropdown-item cbd-copy-text" data-container-id="' . esc_attr($container_id) . '">';
        $html .= '<i class="dashicons dashicons-clipboard"></i>';
        $html .= '<span>Text kopieren</span>';
        $html .= '</button>';
        
        // Always show screenshot
        $html .= '<button class="cbd-dropdown-item cbd-screenshot" data-container-id="' . esc_attr($container_id) . '">';
        $html .= '<i class="dashicons dashicons-camera"></i>';
        $html .= '<span>Screenshot</span>';
        $html .= '</button>';
        
        $html .= '</div>'; // Close dropdown menu
        $html .= '</div>'; // Close selection menu
        
        // Add block header with icon and title (always top-left, visible when collapsed)
        $block_title = '';
        
        // Only use editor-entered titles, not database block titles
        if (!empty($attributes['blockTitle'])) {
            $block_title = $attributes['blockTitle'];
        } elseif (!empty($attributes['title'])) {
            $block_title = $attributes['title'];  
        }
        
        $has_icon = !empty($features['icon']['enabled']);
        
        // Always show header if there's a title OR icon
        if ($has_icon || !empty($block_title)) {
            $html .= '<div class="cbd-block-header">';
            
            // Icon (always top-left if enabled)
            if ($has_icon) {
                $icon_class = sanitize_html_class($features['icon']['value'] ?? 'dashicons-admin-generic');
                $icon_color = !empty($features['icon']['color']) ? 
                    'style="color: ' . esc_attr($features['icon']['color']) . '"' : '';
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
        
        // Actual content
        $html .= $content;
        
        $html .= '</div>'; // Close .cbd-container-content
        $html .= '</div>'; // Close .cbd-container-block
        $html .= '</div>'; // Close .cbd-content
        $html .= '</div>'; // Close .cbd-container
        
        return $html;
    }
    
    /**
     * Generate container HTML
     */
    private static function generate_container_html($styles, $features, $config, $content, $block_slug) {
        $container_classes = array('cbd-container');
        $container_styles = array();
        $container_attributes = array();
        
        // Add custom class if set
        if (!empty($config['customClass'])) {
            $container_classes[] = sanitize_html_class($config['customClass']);
        }
        
        // Add block-specific class
        $container_classes[] = 'cbd-block-' . sanitize_html_class($block_slug);
        
        // Generate styles
        $container_styles = self::generate_container_styles($styles);
        
        // Check if we have outside positioned elements
        $has_outside_elements = self::has_outside_positioned_elements($features);
        if ($has_outside_elements) {
            $container_classes[] = 'cbd-has-outside-elements';
        }
        
        // Container attributes
        $container_attributes['class'] = implode(' ', $container_classes);
        
        if (!empty($container_styles)) {
            $container_attributes['style'] = implode('; ', $container_styles);
        }
        
        // Generate container ID for potential JavaScript targeting
        $container_id = 'cbd-container-' . uniqid();
        $container_attributes['id'] = $container_id;
        
        // Start building HTML
        $html = '';
        
        // Wrapper div for outside elements if needed
        if ($has_outside_elements) {
            $html .= '<div class="cbd-container-wrapper cbd-has-outside-elements">';
        }
        
        // Main container
        $html .= sprintf('<div%s>', self::build_attributes($container_attributes));
        
        // Add positioned elements (icons, numbering, etc.)
        $html .= self::generate_positioned_elements($features, $block_slug);
        
        // Container content
        if (!empty($config['allowInnerBlocks']) || !empty($content)) {
            $html .= '<div class="cbd-container-content">';
            $html .= $content;
            $html .= '</div>';
        }
        
        // Additional features (collapse, copy, screenshot buttons)
        $html .= self::generate_action_buttons($features, $container_id);
        
        $html .= '</div>'; // End main container
        
        // Close wrapper if needed
        if ($has_outside_elements) {
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Generate container styles from styles array
     */
    private static function generate_container_styles($styles) {
        $css_styles = array();
        
        // Background
        if (!empty($styles['background']['color'])) {
            $css_styles[] = 'background-color: ' . esc_attr($styles['background']['color']);
        }
        
        // Border
        if (!empty($styles['border'])) {
            $border = $styles['border'];
            if (!empty($border['width']) && !empty($border['style']) && !empty($border['color'])) {
                $css_styles[] = sprintf('border: %s %s %s', 
                    esc_attr($border['width']), 
                    esc_attr($border['style']), 
                    esc_attr($border['color'])
                );
            }
            
            if (!empty($border['radius'])) {
                $css_styles[] = 'border-radius: ' . esc_attr($border['radius']);
            }
        }
        
        // Spacing
        if (!empty($styles['spacing'])) {
            $spacing = $styles['spacing'];
            if (!empty($spacing['padding'])) {
                $css_styles[] = 'padding: ' . esc_attr($spacing['padding']);
            }
            
            if (!empty($spacing['margin'])) {
                $css_styles[] = 'margin: ' . esc_attr($spacing['margin']);
            }
        }
        
        // Text
        if (!empty($styles['text'])) {
            $text = $styles['text'];
            if (!empty($text['color'])) {
                $css_styles[] = 'color: ' . esc_attr($text['color']);
            }
            
            if (!empty($text['size'])) {
                $css_styles[] = 'font-size: ' . esc_attr($text['size']);
            }
            
            if (!empty($text['align'])) {
                $css_styles[] = 'text-align: ' . esc_attr($text['align']);
            }
        }
        
        // Shadow
        if (!empty($styles['shadow']['enabled'])) {
            $shadow = $styles['shadow'];
            if (!empty($shadow['x']) && !empty($shadow['y']) && !empty($shadow['blur']) && !empty($shadow['color'])) {
                $css_styles[] = sprintf('box-shadow: %s %s %s %s', 
                    esc_attr($shadow['x']), 
                    esc_attr($shadow['y']), 
                    esc_attr($shadow['blur']), 
                    esc_attr($shadow['color'])
                );
            }
        }
        
        // Max width from config
        if (!empty($config['maxWidth'])) {
            $css_styles[] = 'max-width: ' . esc_attr($config['maxWidth']);
        }
        
        return $css_styles;
    }
    
    /**
     * Check if features have outside positioned elements
     */
    private static function has_outside_positioned_elements($features) {
        foreach ($features as $feature_data) {
            if (!empty($feature_data['enabled']) && !empty($feature_data['position'])) {
                if ($feature_data['position']['placement'] === 'outside') {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Generate positioned elements (icons, numbering)
     */
    private static function generate_positioned_elements($features, $block_slug) {
        $html = '';
        
        // Icon
        if (!empty($features['icon']['enabled'])) {
            $html .= self::generate_icon_element($features['icon']);
        }
        
        // Numbering
        if (!empty($features['numbering']['enabled'])) {
            $html .= self::generate_numbering_element($features['numbering'], $block_slug);
        }
        
        return $html;
    }
    
    /**
     * Generate icon element
     */
    private static function generate_icon_element($icon_data) {
        $icon_classes = array('cbd-container-icon', 'cbd-positioned');
        $icon_styles = array();
        
        // Get position settings
        $position_settings = $icon_data['position'] ?? array();
        
        // Generate position classes and styles
        if (!empty($position_settings)) {
            $position_classes = CBD_Position_Settings::generate_position_classes($position_settings);
            $position_styles = CBD_Position_Settings::generate_position_styles($position_settings);
            
            $icon_classes[] = $position_classes;
            $icon_styles[] = $position_styles;
        }
        
        // Icon value
        $icon_value = $icon_data['value'] ?? 'dashicons-admin-generic';
        
        // Accessibility
        $icon_attributes = array(
            'class' => implode(' ', $icon_classes),
            'aria-hidden' => 'true',
            'title' => __('Block Icon', 'container-block-designer')
        );
        
        if (!empty($icon_styles)) {
            $icon_attributes['style'] = implode('; ', $icon_styles);
        }
        
        $html = sprintf('<div%s>', self::build_attributes($icon_attributes));
        $html .= sprintf('<span class="dashicons %s"></span>', esc_attr($icon_value));
        $html .= '<span class="screen-reader-text">' . __('Block Icon', 'container-block-designer') . '</span>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate numbering element
     */
    private static function generate_numbering_element($numbering_data, $block_slug) {
        $number_classes = array('cbd-container-number', 'cbd-positioned');
        $number_styles = array();
        
        // Get position settings
        $position_settings = $numbering_data['position'] ?? array();
        
        // Generate position classes and styles
        if (!empty($position_settings)) {
            $position_classes = CBD_Position_Settings::generate_position_classes($position_settings);
            $position_styles = CBD_Position_Settings::generate_position_styles($position_settings);
            
            $number_classes[] = $position_classes;
            $number_styles[] = $position_styles;
        }
        
        // Get current number for this block type
        $format = $numbering_data['format'] ?? 'decimal';
        $current_number = self::get_container_number($block_slug, $format);
        
        // Determine if we need rectangular style for longer numbers
        if (strlen($current_number) > 2) {
            $number_classes[] = 'cbd-rectangular';
        }
        
        // Number attributes
        $number_attributes = array(
            'class' => implode(' ', $number_classes),
            'aria-label' => sprintf(__('Container Nummer %s', 'container-block-designer'), $current_number)
        );
        
        if (!empty($number_styles)) {
            $number_attributes['style'] = implode('; ', $number_styles);
        }
        
        $html = sprintf('<div%s>', self::build_attributes($number_attributes));
        $html .= esc_html($current_number);
        $html .= '<span class="screen-reader-text">' . sprintf(__('Container Nummer %s', 'container-block-designer'), $current_number) . '</span>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate action buttons - DISABLED: All buttons now in dropdown menu only
     */
    private static function generate_action_buttons($features, $container_id) {
        // All buttons are now in the dropdown menu only - no separate buttons
        return '';
    }
    
    /**
     * Get container number for numbering
     */
    private static function get_container_number($block_slug, $format) {
        // Initialize counter for this block type if not exists
        if (!isset(self::$container_counter[$block_slug])) {
            self::$container_counter[$block_slug] = 0;
        }
        
        // Increment counter
        self::$container_counter[$block_slug]++;
        
        // Get current number
        $number = self::$container_counter[$block_slug];
        
        // Format number based on format type
        return cbd_generate_container_number($format, $number);
    }
    
    /**
     * Build HTML attributes string
     */
    private static function build_attributes($attributes) {
        $output = '';
        
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== '') {
                $output .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }
        
        return $output;
    }
    
    /**
     * Reset container counters (useful for testing)
     */
    public static function reset_counters() {
        self::$container_counter = array();
    }
}

/**
 * Helper function to register additional block variations
 */
add_action('init', 'cbd_register_block_variations');
function cbd_register_block_variations() {
    // Get all active blocks for variations
    $active_blocks = cbd_get_active_blocks();
    
    if (empty($active_blocks)) {
        return;
    }
    
    foreach ($active_blocks as $block) {
        wp_add_inline_script('cbd-container-block', sprintf(
            'wp.blocks.registerBlockVariation("cbd/container-block", {
                name: "%s",
                title: "%s",
                description: "%s",
                attributes: {
                    selectedBlock: "%s"
                }
            });',
            esc_js($block['slug']),
            esc_js($block['name']),
            esc_js($block['description']),
            esc_js($block['slug'])
        ));
    }
}

/**
 * Add inline CSS for dynamic styles
 */
add_action('wp_head', 'cbd_add_dynamic_styles');
function cbd_add_dynamic_styles() {
    $active_blocks = cbd_get_active_blocks();
    
    if (empty($active_blocks)) {
        return;
    }
    
    echo '<style id="cbd-dynamic-styles">';
    
    foreach ($active_blocks as $block) {
        $styles = json_decode($block['styles'], true);
        $block_class = '.cbd-block-' . sanitize_html_class($block['slug']);
        
        if (!empty($styles)) {
            $css_rules = array();
            
            // Generate CSS from styles array
            foreach ($styles as $property_group => $properties) {
                switch ($property_group) {
                    case 'background':
                        if (!empty($properties['color'])) {
                            $css_rules[] = 'background-color: ' . esc_attr($properties['color']);
                        }
                        break;
                        
                    case 'border':
                        if (!empty($properties['width']) && !empty($properties['style']) && !empty($properties['color'])) {
                            $css_rules[] = sprintf('border: %s %s %s', 
                                esc_attr($properties['width']), 
                                esc_attr($properties['style']), 
                                esc_attr($properties['color'])
                            );
                        }
                        if (!empty($properties['radius'])) {
                            $css_rules[] = 'border-radius: ' . esc_attr($properties['radius']);
                        }
                        break;
                        
                    case 'spacing':
                        if (!empty($properties['padding'])) {
                            $css_rules[] = 'padding: ' . esc_attr($properties['padding']);
                        }
                        if (!empty($properties['margin'])) {
                            $css_rules[] = 'margin: ' . esc_attr($properties['margin']);
                        }
                        break;
                        
                    case 'text':
                        if (!empty($properties['color'])) {
                            $css_rules[] = 'color: ' . esc_attr($properties['color']);
                        }
                        if (!empty($properties['size'])) {
                            $css_rules[] = 'font-size: ' . esc_attr($properties['size']);
                        }
                        if (!empty($properties['align'])) {
                            $css_rules[] = 'text-align: ' . esc_attr($properties['align']);
                        }
                        break;
                        
                    case 'shadow':
                        if (!empty($properties['enabled']) && !empty($properties['x']) && !empty($properties['y']) && !empty($properties['blur']) && !empty($properties['color'])) {
                            $css_rules[] = sprintf('box-shadow: %s %s %s %s', 
                                esc_attr($properties['x']), 
                                esc_attr($properties['y']), 
                                esc_attr($properties['blur']), 
                                esc_attr($properties['color'])
                            );
                        }
                        break;
                }
            }
            
            if (!empty($css_rules)) {
                printf('%s { %s }', $block_class, implode('; ', $css_rules));
                echo "\n";
            }
        }
    }
    
    echo '</style>';
}

/**
 * Add frontend JavaScript for interactive features
 */
add_action('wp_footer', 'cbd_add_frontend_scripts');
function cbd_add_frontend_scripts() {
    ?>
    <script>
    (function($) {
        'use strict';
        
        // Copy text functionality
        $('.cbd-copy-button').on('click', function() {
            var containerId = $(this).data('container');
            var $container = $('#' + containerId);
            var $content = $container.find('.cbd-container-content');
            
            var textToCopy = $content.length ? $content.text().trim() : $container.text().trim();
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showToast('<?php _e("Text kopiert!", "container-block-designer"); ?>', 'success');
                }).catch(function() {
                    fallbackCopyText(textToCopy);
                });
            } else {
                fallbackCopyText(textToCopy);
            }
        });
        
        // Screenshot functionality
        $('.cbd-screenshot-button').on('click', function() {
            var containerId = $(this).data('container');
            var $container = $('#' + containerId);
            
            // Use html2canvas if available, otherwise show message
            if (typeof html2canvas !== 'undefined') {
                html2canvas($container[0]).then(function(canvas) {
                    var link = document.createElement('a');
                    link.download = 'container-screenshot.png';
                    link.href = canvas.toDataURL();
                    link.click();
                    
                    showToast('<?php _e("Screenshot erstellt!", "container-block-designer"); ?>', 'success');
                });
            } else {
                showToast('<?php _e("Screenshot-Funktion nicht verfügbar", "container-block-designer"); ?>', 'error');
            }
        });
        
        // Fallback copy function for older browsers
        function fallbackCopyText(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                showToast('<?php _e("Text kopiert!", "container-block-designer"); ?>', 'success');
            } catch (err) {
                showToast('<?php _e("Kopieren fehlgeschlagen", "container-block-designer"); ?>', 'error');
            }
            
            document.body.removeChild(textArea);
        }
        
        // Toast notification function
        function showToast(message, type) {
            var $toast = $('<div class="cbd-toast cbd-toast-' + type + '">' + message + '</div>');
            $('body').append($toast);
            
            setTimeout(function() {
                $toast.addClass('cbd-toast-show');
            }, 100);
            
            setTimeout(function() {
                $toast.removeClass('cbd-toast-show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, 3000);
        }
        
    })(jQuery);
    </script>
    <?php
}