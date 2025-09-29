<?php
/**
 * Container Block Designer - Master Renderer
 * This is the ONLY active frontend renderer - overrides all others
 * Version: 2.6.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Master Frontend Renderer Class
 * This class takes priority over all other renderers
 */
class CBD_Master_Renderer {
    
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
     * Constructor - Initialize with highest priority
     */
    private function __construct() {
        // Hook with highest priority to override all other renderers
        add_filter('render_block', array($this, 'render_container_blocks'), 5, 2);
        
        // Also register block types with our callback
        add_action('init', array($this, 'register_blocks'), 25);
        
        // Enqueue our assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 5);
        
        // Add debug hook
        add_action('wp_head', array($this, 'add_debug_marker'));
    }
    
    /**
     * Add debug marker to verify this renderer is active
     */
    public function add_debug_marker() {
        echo '<!-- CBD MASTER RENDERER LOADED AT ' . date('Y-m-d H:i:s') . ' -->' . "\n";
    }
    
    /**
     * Register blocks with our renderer
     */
    public function register_blocks() {
        // Unregister any existing registrations first
        if (WP_Block_Type_Registry::get_instance()->is_registered('container-block-designer/container')) {
            unregister_block_type('container-block-designer/container');
        }
        if (WP_Block_Type_Registry::get_instance()->is_registered('cbd/container-block')) {
            unregister_block_type('cbd/container-block');
        }
        
        // Register with our callback
        register_block_type('container-block-designer/container', array(
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'selectedBlock' => array('type' => 'string', 'default' => ''),
                'blockTitle' => array('type' => 'string', 'default' => ''),
                'customClasses' => array('type' => 'string', 'default' => ''),
                'align' => array('type' => 'string', 'default' => ''),
                'anchor' => array('type' => 'string', 'default' => '')
            )
        ));
        
        register_block_type('cbd/container-block', array(
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'selectedBlock' => array('type' => 'string', 'default' => ''),
                'blockTitle' => array('type' => 'string', 'default' => ''),
                'customClasses' => array('type' => 'string', 'default' => ''),
                'align' => array('type' => 'string', 'default' => ''),
                'anchor' => array('type' => 'string', 'default' => '')
            )
        ));
    }
    
    /**
     * Main render filter - intercepts ALL blocks
     */
    public function render_container_blocks($block_content, $block) {
        // Only process our container blocks
        $container_blocks = array(
            'container-block-designer/container',
            'cbd/container-block'
        );

        if (!in_array($block['blockName'], $container_blocks)) {
            return $block_content;
        }

        $attributes = $block['attrs'] ?? array();

        // CRITICAL FIX: Get inner blocks content properly
        $inner_content = '';

        // Process inner blocks if they exist
        if (!empty($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner_block) {
                $inner_content .= render_block($inner_block);
            }
        }

        // If no inner blocks, try to use the provided content
        if (empty($inner_content) && !empty($block_content)) {
            $inner_content = $block_content;
        }

        // If still no content, check innerHTML from block parsing
        if (empty($inner_content) && !empty($block['innerHTML'])) {
            $inner_content = $block['innerHTML'];
        }

        return $this->render_block($attributes, $inner_content);
    }
    
    /**
     * Main render callback
     */
    public function render_block($attributes, $content = '') {
        // MASTER RENDERER DEBUG
        $html = '<!-- ############################################### -->';
        $html .= '<!-- CBD MASTER RENDERER IS NOW ACTIVE!!! -->';
        $html .= '<!-- TIME: ' . date('Y-m-d H:i:s') . ' -->';
        $html .= '<!-- ATTRIBUTES: ' . json_encode($attributes) . ' -->';
        $html .= '<!-- CONTENT LENGTH: ' . strlen($content) . ' -->';
        $html .= '<!-- CONTENT PREVIEW: ' . esc_html(substr($content, 0, 300)) . ' -->';
        $html .= '<!-- CONTENT RAW (first 500 chars): ' . esc_html(substr($content, 0, 500)) . ' -->';
        $html .= '<!-- CONTENT HAS HTML TAGS: ' . (strpos($content, '<') !== false ? 'YES' : 'NO') . ' -->';
        $html .= '<!-- CONTENT HAS ENTITIES: ' . (strpos($content, '&lt;') !== false ? 'YES' : 'NO') . ' -->';
        $html .= '<!-- ############################################### -->';
        
        $selected_block = sanitize_text_field($attributes['selectedBlock'] ?? '');
        
        if (empty($selected_block)) {
            return $html . '<div class="cbd-container-placeholder" style="padding: 20px; border: 2px dashed #ccc; text-align: center;">Bitte wählen Sie einen Container-Block aus.</div>';
        }
        
        // Get block data from database
        global $wpdb;
        $block_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s AND status = 'active'",
            $selected_block
        ));
        
        if (!$block_data) {
            return $html . '<div class="cbd-container-error" style="padding: 20px; border: 2px solid red; text-align: center;">Container-Block "' . esc_html($selected_block) . '" nicht gefunden.</div>';
        }
        
        // Parse block data
        $styles = json_decode($block_data->styles, true) ?: array();
        $features = json_decode($block_data->features, true) ?: array();
        $config = json_decode($block_data->config, true) ?: array();
        
        // DEBUG: Show what we found
        $html .= '<!-- CBD DEBUG: Block found: ' . $block_data->title . ' -->';
        $html .= '<!-- CBD DEBUG: Features: ' . json_encode($features) . ' -->';
        
        // Generate unique container ID
        $container_id = 'cbd-container-' . uniqid();
        
        // Start container with proper structure
        $html .= '<div class="cbd-container" id="' . esc_attr($container_id) . '" style="position: relative; margin: 40px 0;">';
        
        // ALWAYS show numbering for testing - OUTSIDE content area so it stays visible
        $html .= '<div class="cbd-container-number cbd-outside-number" style="position: absolute !important; top: -40px !important; left: -40px !important; background: rgba(0,0,0,0.9) !important; color: white !important; width: 34px !important; height: 34px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-size: 15px !important; font-weight: bold !important; z-index: 99999 !important; border: 2px solid white !important;">';
        $html .= '1';
        $html .= '</div>';
        
        // ALWAYS show dropdown menu - OUTSIDE content area so it stays visible
        $html .= '<div class="cbd-selection-menu" style="position: absolute; top: 10px; right: 10px; z-index: 1000;">';
        $html .= '<button class="cbd-menu-toggle" type="button" style="background: rgba(0,0,0,0.8); color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer;">';
        $html .= '⚙️ Menu';
        $html .= '</button>';
        
        $html .= '<div class="cbd-dropdown-menu" style="position: absolute; top: 100%; right: 0; background: white; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); min-width: 150px; display: none;">';
        
        // Collapse button
        $html .= '<button class="cbd-dropdown-item cbd-collapse-toggle" type="button" style="display: block; width: 100%; padding: 10px; border: none; background: none; text-align: left; cursor: pointer; border-bottom: 1px solid #eee;">';
        $html .= '📁 Einklappen';
        $html .= '</button>';
        
        // Copy text button
        $html .= '<button class="cbd-dropdown-item cbd-copy-text" type="button" style="display: block; width: 100%; padding: 10px; border: none; background: none; text-align: left; cursor: pointer; border-bottom: 1px solid #eee;">';
        $html .= '📋 Text kopieren';
        $html .= '</button>';
        
        // Screenshot button
        $html .= '<button class="cbd-dropdown-item cbd-screenshot" type="button" style="display: block; width: 100%; padding: 10px; border: none; background: none; text-align: left; cursor: pointer;">';
        $html .= '📷 Screenshot';
        $html .= '</button>';
        
        $html .= '</div>'; // Close dropdown
        $html .= '</div>'; // Close selection menu
        
        // Content wrapper - THIS is what gets collapsed, not the whole container
        $html .= '<div class="cbd-content" id="' . esc_attr($container_id) . '-content">';
        
        // Inner styled container
        $inline_styles = $this->generate_inline_styles($styles);
        $html .= '<div class="cbd-container-block cbd-block-' . sanitize_html_class($selected_block) . '" style="' . esc_attr($inline_styles) . '">';
        
        // Show title if provided - INSIDE the collapsible area
        $block_title = sanitize_text_field($attributes['blockTitle'] ?? $attributes['title'] ?? '');
        if (!empty($block_title)) {
            $html .= '<div class="cbd-block-header" style="margin-bottom: 10px;">';
            $html .= '<h3 class="cbd-block-title" style="margin: 0; font-size: 18px; font-weight: bold;">' . esc_html($block_title) . '</h3>';
            $html .= '</div>';
        }
        
        // Actual content - ensure HTML is properly rendered
        $html .= '<div class="cbd-container-content">';

        // Process content properly - handle different content formats
        if (!empty($content)) {
            $html .= '<!-- CONTENT PROCESSING DEBUG START -->';

            // Multiple decoding strategies for different scenarios
            $strategy_used = '';

            // Strategy 1: Direct HTML content (ideal case)
            if (strpos($content, '<') !== false && strpos($content, '>') !== false) {
                // Already HTML, use as-is
                $processed_content = $content;
                $strategy_used = 'Strategy 1: Direct HTML';
            }
            // Strategy 2: HTML entity escaped content
            elseif (strpos($content, '&lt;') !== false || strpos($content, '&gt;') !== false) {
                $processed_content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $strategy_used = 'Strategy 2: HTML Entity Decoded';
            }
            // Strategy 3: WordPress escaped content (double escaping)
            elseif (strpos($content, '&amp;lt;') !== false) {
                $processed_content = html_entity_decode(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $strategy_used = 'Strategy 3: Double Decoded';
            }
            // Strategy 4: Plain text that might be CSS/JS (based on your example)
            elseif (preg_match('/\/\*.*?\*\/|\.[\w-]+\s*{|function\s+\w+\s*\(|document\./', $content)) {
                // Check what type of content we have
                $has_css = preg_match('/\/\*.*?\*\/|\.[\w-]+\s*{/', $content);
                $has_js = preg_match('/function\s+\w+\s*\(|document\.|const\s+|let\s+|var\s+/', $content);
                $has_html = preg_match('/<[^>]+>/', $content);

                if ($has_html) {
                    // Mixed content - assume it\'s complete HTML
                    $processed_content = $content;
                    $strategy_used = 'Strategy 4a: Mixed HTML content';
                } else {
                    // Pure CSS or JS - need to wrap appropriately
                    if ($has_css && $has_js) {
                        // Both CSS and JS - create proper HTML structure
                        $processed_content = '<style>' . preg_replace('/\/\*.*?\*\/(.*?)(?=function|const|let|var|$)/s', '$1</style><script>', $content) . '</script>';
                        $strategy_used = 'Strategy 4b: CSS + JS wrapped';
                    } elseif ($has_css) {
                        $processed_content = '<style>' . $content . '</style>';
                        $strategy_used = 'Strategy 4c: CSS wrapped';
                    } elseif ($has_js) {
                        $processed_content = '<script>' . $content . '</script>';
                        $strategy_used = 'Strategy 4d: JS wrapped';
                    } else {
                        $processed_content = $content;
                        $strategy_used = 'Strategy 4e: Unknown code type';
                    }
                }
            }
            // Strategy 5: Fallback - treat as HTML
            else {
                $processed_content = $content;
                $strategy_used = 'Strategy 5: Fallback HTML';
            }

            $html .= '<!-- STRATEGY USED: ' . $strategy_used . ' -->';
            $html .= '<!-- PROCESSED CONTENT LENGTH: ' . strlen($processed_content) . ' -->';
            $html .= '<!-- PROCESSED PREVIEW: ' . esc_html(substr($processed_content, 0, 200)) . ' -->';
            $html .= '<!-- CONTENT PROCESSING DEBUG END -->';

            $html .= $processed_content;
        } else {
            $html .= '<p>Kein Inhalt gefunden. Bitte fügen Sie Inhalt zum Block hinzu.</p>';
        }

        $html .= '</div>';
        
        $html .= '</div>'; // Close .cbd-container-block
        $html .= '</div>'; // Close .cbd-content (collapsible area)
        $html .= '</div>'; // Close .cbd-container
        
        return $html;
    }
    
    /**
     * Generate inline styles from block data
     */
    private function generate_inline_styles($styles) {
        $css_parts = array();
        
        // Background
        if (!empty($styles['background']['color'])) {
            $css_parts[] = 'background-color: ' . sanitize_hex_color($styles['background']['color']);
        }
        
        // Padding
        if (!empty($styles['padding']) && is_array($styles['padding'])) {
            $padding = $styles['padding'];
            $css_parts[] = sprintf(
                'padding: %dpx %dpx %dpx %dpx',
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
                $css_parts[] = 'border-width: ' . intval($border['width']) . 'px';
            }
            if (!empty($border['style'])) {
                $css_parts[] = 'border-style: ' . sanitize_text_field($border['style']);
            }
            if (!empty($border['color'])) {
                $css_parts[] = 'border-color: ' . sanitize_hex_color($border['color']);
            }
            if (!empty($border['radius'])) {
                $css_parts[] = 'border-radius: ' . intval($border['radius']) . 'px';
            }
        }
        
        // Box shadow
        if (!empty($styles['shadow']) && is_array($styles['shadow'])) {
            $shadow = $styles['shadow'];
            if (!empty($shadow['enabled'])) {
                $css_parts[] = sprintf(
                    'box-shadow: %dpx %dpx %dpx rgba(0,0,0,%s)',
                    intval($shadow['x'] ?? 2),
                    intval($shadow['y'] ?? 2),
                    intval($shadow['blur'] ?? 4),
                    floatval($shadow['opacity'] ?? 0.3)
                );
            }
        }
        
        return implode('; ', $css_parts);
    }
    
    /**
     * Enqueue assets with highest priority
     */
    public function enqueue_assets() {
        // CSS
        wp_enqueue_style(
            'cbd-master-frontend',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend-clean.css',
            array(),
            CBD_VERSION . '-master-' . time()
        );
        
        // Dashicons
        wp_enqueue_style('dashicons');
        
        // JavaScript
        wp_enqueue_script(
            'cbd-master-frontend',
            CBD_PLUGIN_URL . 'assets/js/frontend-working.js',
            array('jquery'),
            CBD_VERSION . '-master-' . time(),
            true
        );
        
        // Enhanced frontend script with proper HTML element support
        wp_add_inline_script('cbd-master-frontend', '
            jQuery(document).ready(function($) {
                console.log("CBD Master Renderer with HTML Element Support loaded at " + new Date());

                // Initialize container functionality
                function initializeContainer($container) {
                    console.log("Initializing container:", $container.attr("id"));

                    // Re-enable all interactive elements within the container
                    var $content = $container.find(".cbd-container-content");

                    // Re-bind click events for buttons, links, etc.
                    $content.find("button, a, input[type=\"button\"], input[type=\"submit\"]").each(function() {
                        var $element = $(this);

                        // Remove any existing disabled states
                        $element.prop("disabled", false);
                        $element.removeClass("disabled");

                        // Ensure element is visible and interactive
                        $element.css({
                            "pointer-events": "auto",
                            "opacity": "1"
                        });

                        console.log("Re-enabled interactive element:", $element.get(0).tagName, $element.attr("class"));
                    });

                    // Re-initialize form elements
                    $content.find("form").each(function() {
                        var $form = $(this);

                        // Re-bind form submission if needed
                        $form.off("submit.cbd").on("submit.cbd", function(e) {
                            console.log("Form submission detected in CBD container");
                            // Allow form to submit normally
                        });
                    });

                    // Re-initialize any custom scripts within the content
                    $content.find("script").each(function() {
                        var script = this;
                        if (script.src) {
                            // External script - reload if needed
                            console.log("External script found:", script.src);
                        } else {
                            // Inline script - re-execute
                            try {
                                var scriptContent = $(script).html();
                                if (scriptContent.trim()) {
                                    console.log("Re-executing inline script");
                                    eval(scriptContent);
                                }
                            } catch (e) {
                                console.warn("Error re-executing script:", e);
                            }
                        }
                    });

                    // Trigger custom reinitialization event
                    $container.trigger("cbd:reinitialized");
                }

                // Initialize all containers on page load
                $(".cbd-container").each(function() {
                    initializeContainer($(this));
                });

                // Toggle dropdown menu - now properly hidden by default
                $(document).on("click", ".cbd-menu-toggle", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log("Menu toggle clicked");

                    var dropdown = $(this).siblings(".cbd-dropdown-menu");
                    var isVisible = dropdown.is(":visible");

                    // Hide all other dropdowns first
                    $(".cbd-dropdown-menu").hide();

                    // Toggle this dropdown
                    if (!isVisible) {
                        dropdown.show();
                    }

                    console.log("Dropdown toggled, now visible:", dropdown.is(":visible"));
                });

                // Hide dropdown when clicking outside
                $(document).on("click", function(e) {
                    if (!$(e.target).closest(".cbd-selection-menu").length) {
                        $(".cbd-dropdown-menu").hide();
                    }
                });

                // Enhanced collapse functionality with HTML element preservation
                $(document).on("click", ".cbd-collapse-toggle", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log("Collapse toggle clicked");

                    var $toggle = $(this);
                    var $container = $toggle.closest(".cbd-container");
                    var $content = $container.find(".cbd-content");
                    var isCollapsed = $content.is(":hidden");

                    console.log("Container:", $container.attr("id"));
                    console.log("Content found:", $content.length);
                    console.log("Currently collapsed:", isCollapsed);

                    if ($content.length > 0) {
                        if (isCollapsed) {
                            // Expanding
                            $content.slideDown(300, function() {
                                console.log("Slide down complete");

                                // CRITICAL: Re-initialize all interactive elements after expand
                                setTimeout(function() {
                                    initializeContainer($container);
                                    console.log("Container re-initialized after expand");
                                }, 50);
                            });
                            $toggle.text("📁 Einklappen");
                        } else {
                            // Collapsing
                            $content.slideUp(300, function() {
                                console.log("Slide up complete");
                            });
                            $toggle.text("📂 Ausklappen");
                        }
                    }

                    $toggle.closest(".cbd-dropdown-menu").hide();
                });

                // Copy text functionality
                $(document).on("click", ".cbd-copy-text", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log("Copy text clicked");

                    var container = $(this).closest(".cbd-container");
                    var text = container.find(".cbd-container-content").text().trim();

                    if (navigator.clipboard && text) {
                        navigator.clipboard.writeText(text).then(function() {
                            alert("Text kopiert! (" + text.length + " Zeichen)");
                        }).catch(function(err) {
                            console.error("Copy failed:", err);
                            alert("Text kopieren fehlgeschlagen");
                        });
                    } else {
                        alert("Kein Text zum Kopieren gefunden oder Clipboard nicht unterstützt");
                    }

                    $(this).closest(".cbd-dropdown-menu").hide();
                });

                // Screenshot functionality
                $(document).on("click", ".cbd-screenshot", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log("Screenshot clicked");

                    var $container = $(this).closest(".cbd-container");
                    var containerId = $container.attr("id");

                    // Check if html2canvas is available
                    if (typeof html2canvas !== "undefined") {
                        var element = $container.find(".cbd-container-block")[0];
                        if (element) {
                            html2canvas(element, {
                                backgroundColor: null,
                                scale: 2,
                                useCORS: true
                            }).then(function(canvas) {
                                // Create download
                                canvas.toBlob(function(blob) {
                                    var url = URL.createObjectURL(blob);
                                    var a = document.createElement("a");
                                    a.href = url;
                                    a.download = "container-" + containerId + ".png";
                                    document.body.appendChild(a);
                                    a.click();
                                    document.body.removeChild(a);
                                    URL.revokeObjectURL(url);
                                    alert("Screenshot heruntergeladen!");
                                });
                            }).catch(function(error) {
                                console.error("Screenshot error:", error);
                                alert("Screenshot fehlgeschlagen");
                            });
                        }
                    } else {
                        alert("Screenshot-Bibliothek nicht verfügbar. html2canvas wird geladen...");
                        // Load html2canvas dynamically
                        var script = document.createElement("script");
                        script.src = "https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js";
                        script.onload = function() {
                            alert("html2canvas geladen. Bitte erneut versuchen.");
                        };
                        document.head.appendChild(script);
                    }

                    $(this).closest(".cbd-dropdown-menu").hide();
                });

                // Global event to handle dynamic content
                $(document).on("cbd:content-changed", ".cbd-container", function() {
                    console.log("Content changed event detected, re-initializing container");
                    initializeContainer($(this));
                });

                console.log("CBD Frontend with HTML element support fully initialized");
            });
        ');
    }
}

// Initialize immediately
CBD_Master_Renderer::get_instance();