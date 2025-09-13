<?php
/**
 * Container Block Designer - Block Registration
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.2
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Block Registration Klasse
 */
class CBD_Block_Registration {
    
    /**
     * Singleton-Instanz
     */
    private static $instance = null;
    
    /**
     * Registrierte Blöcke
     */
    private $registered_blocks = array();
    
    /**
     * Singleton-Getter
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        // Nur Asset-Hooks hier, Block-Registrierung erfolgt manuell über register_blocks()
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
    }
    
    /**
     * Blöcke registrieren
     */
    public function register_blocks() {
        // Verwende WordPress Block Registry als einzige Wahrheitsquelle
        if (WP_Block_Type_Registry::get_instance()->is_registered('container-block-designer/container')) {
            error_log('[CBD Block Registration] Blocks already registered, skipping');
            return;
        }
        
        global $wpdb;
        
        // Hole alle aktiven Blöcke aus der Datenbank
        $blocks = $wpdb->get_results(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'"
        );
        
        // Registriere jeden Block
        foreach ($blocks as $block) {
            $this->register_block_type($block);
        }
        
        // Registriere den Haupt-Container-Block
        $this->register_main_container_block();
        
        error_log('[CBD Block Registration] Blocks registered');
    }
    
    /**
     * Einzelnen Block-Typ registrieren
     */
    private function register_block_type($block) {
        // Konvertiere Block-Name in gültiges Format (lowercase, keine Leerzeichen)
        $sanitized_name = $this->sanitize_block_name($block->name);
        $block_name = 'container-block-designer/' . $sanitized_name;
        
        // Überprüfe ob dieser spezifische Block bereits registriert ist
        if (WP_Block_Type_Registry::get_instance()->is_registered($block_name)) {
            error_log('[CBD Block Registration] Block ' . $block_name . ' already registered, skipping');
            return;
        }
        
        $config = !empty($block->config) ? json_decode($block->config, true) : array();
        $features = !empty($block->features) ? json_decode($block->features, true) : array();
        
        // Block-Attribute definieren
        $attributes = array(
            'selectedBlock' => array(
                'type' => 'string',
                'default' => $sanitized_name  // Verwende den sanitized name
            ),
            'customClasses' => array(
                'type' => 'string',
                'default' => ''
            ),
            'blockConfig' => array(
                'type' => 'object',
                'default' => $config
            ),
            'blockFeatures' => array(
                'type' => 'object',
                'default' => $features
            ),
            'align' => array(
                'type' => 'string',
                'default' => ''
            ),
            'anchor' => array(
                'type' => 'string',
                'default' => ''
            )
        );
        
        // Block-Supports definieren
        $supports = array(
            'html' => false,
            'className' => true,
            'anchor' => true,
            'align' => array('wide', 'full'),
            'spacing' => array(
                'margin' => true,
                'padding' => true
            ),
            'color' => array(
                'background' => true,
                'text' => true,
                'link' => true
            ),
            '__experimentalBorder' => array(
                'radius' => true,
                'width' => true,
                'color' => true,
                'style' => true
            )
        );
        
        // Block registrieren - nutze den sanitized block_name
        $result = register_block_type($block_name, array(
            'editor_script' => 'cbd-block-editor',
            'editor_style' => 'cbd-editor-base',
            'style' => 'cbd-editor-frontend-consolidated',
            'script' => null,
            'render_callback' => array($this, 'render_block'),
            'attributes' => $attributes,
            'supports' => $supports
        ));
        
        if ($result) {
            $this->registered_blocks[$block_name] = $block;
            error_log('[CBD Block Registration] Block type registered: ' . $block_name);
        } else {
            error_log('[CBD Block Registration] Failed to register block type: ' . $block_name);
        }
    }
    
    /**
     * Sanitize block name for WordPress requirements
     * Block names must be lowercase and without spaces
     */
    private function sanitize_block_name($name) {
        // Konvertiere zu lowercase und ersetze Leerzeichen/Sonderzeichen durch Bindestriche
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        $name = trim($name, '-');
        return $name;
    }
    
    /**
     * Haupt-Container-Block registrieren
     */
    private function register_main_container_block() {
        $block_name = 'container-block-designer/container';
        
        // Überprüfe nochmals ob Block bereits registriert ist
        if (WP_Block_Type_Registry::get_instance()->is_registered($block_name)) {
            error_log('[CBD Block Registration] Main container block already registered');
            return;
        }
        
        register_block_type($block_name, array(
            'editor_script' => 'cbd-block-editor',
            'editor_style' => 'cbd-editor-base',
            'style' => 'cbd-editor-frontend-consolidated',
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
                'align' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'anchor' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'supports' => array(
                'html' => false,
                'className' => true,
                'anchor' => true,
                'align' => array('wide', 'full')
            )
        ));
        
        error_log('[CBD Block Registration] Main container block registered');
    }
    
    /**
     * Block-Editor-Assets einbinden
     */
    public function enqueue_block_editor_assets() {
        // Block-Editor JavaScript
        wp_register_script(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/js/block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            CBD_VERSION,
            true
        );
        
        // Lokalisierung für JavaScript
        wp_localize_script('cbd-block-editor', 'cbdBlockEditor', array(
            'blocks' => $this->get_available_blocks(),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_block_editor'),
            'pluginUrl' => CBD_PLUGIN_URL,
            'strings' => array(
                'selectBlock' => __('Block auswählen', 'container-block-designer'),
                'noBlocks' => __('Keine Blöcke verfügbar', 'container-block-designer'),
                'customClasses' => __('Zusätzliche CSS-Klassen', 'container-block-designer'),
                'blockSettings' => __('Block-Einstellungen', 'container-block-designer')
            )
        ));
        
        // Block-Editor CSS
        wp_enqueue_style(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
    }
    
    /**
     * Block-Assets einbinden (Frontend & Editor)
     */
    public function enqueue_block_assets() {
        // Frontend CSS - Use clean version with button styles
        wp_enqueue_style(
            'cbd-frontend-clean',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend-clean.css',
            array(),
            CBD_VERSION . '-buttons-' . time() // Force cache bust with button styles
        );
        
        // Dashicons for frontend icons
        wp_enqueue_style('dashicons');
        
        // Frontend JavaScript für interaktive Features
        $this->enqueue_frontend_scripts();
        
        // Enqueue html2canvas for screenshot functionality
        wp_enqueue_script(
            'html2canvas',
            CBD_PLUGIN_URL . 'assets/lib/html2canvas.min.js',
            array(),
            '1.4.1',
            true
        );
        
        // PDF Export is now handled by jspdf-loader.js with multiple CDN fallbacks
        
        // Fixed inline JavaScript for basic functionality
        wp_add_inline_script('cbd-frontend-working', '
            console.log("CBD: JavaScript loading...");
            jQuery(document).ready(function($) {
                console.log("CBD: jQuery ready - Starting CBD functionality");
                
                // Remove old event handlers
                $(document).off("click", ".cbd-collapse-toggle, .cbd-copy-text, .cbd-screenshot");
                
                // Toggle functionality
                $(document).on("click", ".cbd-collapse-toggle", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log("CBD: Toggle clicked");
                    
                    var button = $(this);
                    var container = button.closest(".cbd-container");
                    var content = container.find(".cbd-container-content");
                    var icon = button.find(".dashicons");
                    
                    if (content.length > 0) {
                        if (content.is(":visible")) {
                            content.slideUp(300);
                            icon.removeClass("dashicons-arrow-up-alt2").addClass("dashicons-arrow-down-alt2");
                            console.log("CBD: Content collapsed");
                        } else {
                            content.slideDown(300);
                            icon.removeClass("dashicons-arrow-down-alt2").addClass("dashicons-arrow-up-alt2");
                            console.log("CBD: Content expanded");
                        }
                    } else {
                        console.log("CBD: Content element not found");
                    }
                });
                
                // Copy functionality
                $(document).on("click", ".cbd-copy-text", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log("CBD: Copy clicked");
                    
                    var button = $(this);
                    var container = button.closest(".cbd-container");
                    var content = container.find(".cbd-container-content");
                    
                    if (content.length > 0) {
                        var textToCopy = content.text().trim();
                        console.log("CBD: Text to copy:", textToCopy.substring(0, 50) + "...");
                        
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(textToCopy).then(function() {
                                button.find(".dashicons").removeClass("dashicons-clipboard").addClass("dashicons-yes-alt");
                                console.log("CBD: Copy successful");
                                setTimeout(function() { 
                                    button.find(".dashicons").removeClass("dashicons-yes-alt").addClass("dashicons-clipboard"); 
                                }, 2000);
                            }).catch(function() {
                                console.log("CBD: Copy failed");
                            });
                        }
                    }
                });
                
                // Screenshot functionality
                $(document).on("click", ".cbd-screenshot", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log("CBD: Screenshot clicked");
                    
                    var button = $(this);
                    var container = button.closest(".cbd-container");
                    var content = container.find(".cbd-container-content");
                    
                    // Expand if collapsed before screenshot
                    var wasCollapsed = !content.is(":visible");
                    if (wasCollapsed) {
                        content.show();
                    }
                    
                    if (typeof html2canvas !== "undefined") {
                        button.find(".dashicons").removeClass("dashicons-camera").addClass("dashicons-update-alt");
                        
                        // Use entire container for screenshot to include images
                        html2canvas(container[0], {
                            useCORS: true,
                            allowTaint: false,
                            scale: 1,
                            logging: true
                        }).then(function(canvas) {
                            var link = document.createElement("a");
                            link.download = "container-block-screenshot.png";
                            link.href = canvas.toDataURL("image/png");
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            
                            button.find(".dashicons").removeClass("dashicons-update-alt").addClass("dashicons-yes-alt");
                            console.log("CBD: Screenshot created");
                            
                            setTimeout(function() { 
                                button.find(".dashicons").removeClass("dashicons-yes-alt").addClass("dashicons-camera"); 
                            }, 2000);
                            
                            // Collapse again if it was collapsed
                            if (wasCollapsed) {
                                content.hide();
                            }
                        }).catch(function(error) {
                            console.error("CBD: Screenshot failed:", error);
                            button.find(".dashicons").removeClass("dashicons-update-alt").addClass("dashicons-camera");
                        });
                    } else {
                        console.log("CBD: html2canvas not available");
                    }
                });
                
                console.log("CBD: Enhanced functionality loaded");
            });
        ');
    }
    
    /**
     * Frontend JavaScript einbinden
     */
    private function enqueue_frontend_scripts() {
        // Prüfe ob interaktive Features verwendet werden
        if (!$this->has_interactive_features()) {
            return;
        }
        
        // jQuery, html2canvas und jsPDF einbinden
        wp_enqueue_script('jquery');
        
        wp_enqueue_script(
            'html2canvas',
            'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',
            array(),
            '1.4.1',
            true
        );
        
        // PDF functionality is handled by the new jspdf-loader.js system
        
        wp_enqueue_script(
            'cbd-frontend-working',
            '',
            array('jquery', 'html2canvas'),
            CBD_VERSION . '-' . time(),
            true
        );
        
        // Lokalisierung für Frontend
        wp_localize_script('cbd-frontend-working', 'cbdFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_frontend'),
            'i18n' => array(
                'copySuccess' => __('Text kopiert!', 'container-block-designer'),
                'copyError' => __('Kopieren fehlgeschlagen', 'container-block-designer'),
                'screenshotSuccess' => __('Screenshot erstellt!', 'container-block-designer'),
                'screenshotError' => __('Screenshot fehlgeschlagen', 'container-block-designer'),
                'screenshotUnavailable' => __('Screenshot-Funktion nicht verfügbar', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Prüfen ob interaktive Features vorhanden sind
     */
    private function has_interactive_features() {
        // Always return true since we now always render the action buttons
        // The buttons are displayed for all container blocks
        return true;
    }
    
    /**
     * Block rendern - Updated with new structure
     */
    public function render_block($attributes, $content) {
        // DEBUG: Add HTML comment to verify this renderer is active
        $html = '<!-- ========================================= -->';
        $html .= '<!-- CBD DEBUG: BLOCK REGISTRATION IS ACTIVE -->';
        $html .= '<!-- TIME: ' . date('Y-m-d H:i:s') . ' -->';
        $html .= '<!-- ========================================= -->';
        
        $selected_block = isset($attributes['selectedBlock']) ? $attributes['selectedBlock'] : '';
        $custom_classes = isset($attributes['customClasses']) ? $attributes['customClasses'] : '';
        $align = isset($attributes['align']) ? $attributes['align'] : '';
        $anchor = isset($attributes['anchor']) ? $attributes['anchor'] : '';
        
        if (empty($selected_block)) {
            return '<!-- Container Block: No block selected -->';
        }
        
        // Block-Daten aus der Datenbank holen
        global $wpdb;
        
        // Suche zuerst nach dem sanitized Namen
        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s AND status = 'active'",
            $selected_block
        ));
        
        // Falls nicht gefunden, suche nach Blocks deren sanitized Name dem selected_block entspricht
        if (!$block) {
            $all_blocks = $wpdb->get_results("SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'");
            
            foreach ($all_blocks as $test_block) {
                $sanitized_name = $this->sanitize_block_name($test_block->name);
                
                if ($sanitized_name === $selected_block) {
                    $block = $test_block;
                    break;
                }
            }
        }
        
        if (!$block) {
            return '<!-- Container Block: Block "' . esc_html($selected_block) . '" not found -->';
        }
        
        // Parse block data
        $styles = !empty($block->styles) ? json_decode($block->styles, true) : array();
        $features = !empty($block->features) ? json_decode($block->features, true) : array();
        $config = !empty($block->config) ? json_decode($block->config, true) : array();
        
        // DEBUG: Add debug output to see what features are available
        $html .= '<!-- CBD DEBUG: Features available: ' . json_encode($features) . ' -->';
        $html .= '<!-- CBD DEBUG: Config available: ' . json_encode($config) . ' -->';
        
        // Generate unique container ID
        $container_id = 'cbd-container-' . uniqid();
        
        // Build wrapper classes (.cbd-container - transparent wrapper)
        $wrapper_classes = array('cbd-container');
        $wrapper_attributes = array('id' => $container_id);
        
        // Add legacy classes for backward compatibility
        $wrapper_classes[] = 'cbd-block-' . $selected_block;
        
        if (!empty($custom_classes)) {
            $wrapper_classes[] = $custom_classes;
        }
        
        if (!empty($align)) {
            $wrapper_classes[] = 'align' . $align;
        }
        
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
        
        // Legacy data attributes
        if (!empty($features)) {
            $wrapper_attributes['data-features'] = esc_attr(json_encode($features));
        }
        
        $wrapper_attributes['data-block-id'] = esc_attr($block->id);
        $wrapper_attributes['data-block-name'] = esc_attr($selected_block);
        
        if (!empty($anchor)) {
            $wrapper_attributes['id'] = $anchor;
        }
        
        $wrapper_attributes['class'] = implode(' ', $wrapper_classes);
        
        // Inner content block classes (.cbd-container-block)
        $content_classes = array('cbd-container-block');
        $content_attributes = array();
        
        // Add block-specific class to content block
        $content_classes[] = 'cbd-block-' . sanitize_html_class($selected_block);
        
        $content_attributes['class'] = implode(' ', $content_classes);
        
        // Generate inline styles for content block only
        $inline_styles = $this->generate_inline_styles($styles);
        if (!empty($inline_styles)) {
            $content_attributes['style'] = $inline_styles;
        }
        
        // Start outer wrapper (.cbd-container) - for controls and positioning
        $html .= '<div';
        foreach ($wrapper_attributes as $attr => $value) {
            $html .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        // Add numbering OUTSIDE everything (in the main container) - ALWAYS for now
        static $numbering_counter = 0;
        $numbering_counter++;
        
        $html .= "<div class=\"cbd-container-number cbd-outside-number\" data-number=\"" . esc_attr($numbering_counter) . "\" style=\"position: absolute !important; top: -40px !important; left: -40px !important; background: rgba(0,0,0,0.9) !important; color: white !important; width: 34px !important; height: 34px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-size: 15px !important; font-weight: bold !important; z-index: 99999 !important; border: 2px solid white !important;\">";
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
        
        // Button styling with CSS classes + inline fallback for visibility
        $html .= '<!-- CBD: THREE BUTTONS - CSS + INLINE FALLBACK -->';
        $html .= '<div class="cbd-action-buttons">';
        
        // Button 1: Collapse - with Dashicon
        $html .= "<button type=\"button\" class=\"cbd-collapse-toggle\" data-container-id=\"" . esc_attr($container_id) . "\" style=\"display: flex !important; visibility: visible !important; opacity: 1 !important;\" title=\"Einklappen\">";
        $html .= '<span class="dashicons dashicons-arrow-up-alt2"></span>';
        $html .= '</button>';
        
        // Button 2: Copy Text - with Dashicon
        $html .= "<button type=\"button\" class=\"cbd-copy-text\" data-container-id=\"" . esc_attr($container_id) . "\" style=\"display: flex !important; visibility: visible !important; opacity: 1 !important;\" title=\"Text kopieren\">";
        $html .= '<span class="dashicons dashicons-clipboard"></span>';
        $html .= '</button>';
        
        // Button 3: Screenshot - with Dashicon
        $html .= "<button type=\"button\" class=\"cbd-screenshot\" data-container-id=\"" . esc_attr($container_id) . "\" style=\"display: flex !important; visibility: visible !important; opacity: 1 !important;\" title=\"Screenshot\">";
        $html .= '<span class="dashicons dashicons-camera"></span>';
        $html .= '</button>';
        
        $html .= '</div>'; // Close buttons
        
        // Add block header with icon and title (always top-left, visible when collapsed)
        $block_title = '';
        
        // Only use editor-entered titles, not database block titles
        if (!empty($attributes['blockTitle'])) {
            $block_title = $attributes['blockTitle'];
        } elseif (!empty($attributes['title'])) {
            $block_title = $attributes['title'];  
        }
        // Note: We explicitly do NOT use config or database titles anymore
        
        $has_icon = !empty($features['icon']['enabled']);
        
        // DEBUG: Always show icon for testing until we fix feature detection
        $has_icon = true; // Force icon display for now
        
        // Always show header if there's a title OR icon
        if ($has_icon || !empty($block_title)) {
            $html .= '<div class="cbd-block-header">';
            
            // Icon (always top-left if enabled)
            if ($has_icon) {
                $icon_class = sanitize_html_class($features['icon']['value'] ?? 'dashicons-admin-generic');
                $icon_color = !empty($features['icon']['color']) ? 
                    'style="color: ' . esc_attr($features['icon']['color']) . '"' : '';
                    
                // Add inline styles to ensure visibility
                $icon_style = "position: absolute; top: 12px; left: 12px; font-size: 20px; z-index: 10;" . ($icon_color ? " " . substr($icon_color, 7, -1) : "");
                
                $html .= '<span class="cbd-header-icon" style="' . esc_attr($icon_style) . '">';
                $html .= '<i class="dashicons ' . $icon_class . '"></i>';
                $html .= '</span>';
            }
            
            // Block title (next to icon, responsive) - ALWAYS if not empty
            if (!empty($block_title)) {
                $html .= '<h3 class="cbd-block-title">' . esc_html($block_title) . '</h3>';
            }
            
            $html .= '</div>'; // Close block header
        }
        
        // Legacy feature rendering for backward compatibility - DISABLED to prevent duplicate icons
        // if (!empty($features)) {
        //     $html .= $this->render_features($features, $block->id);
        // }
        
        // Wrap the actual content in a collapsible container
        $html .= '<div class="cbd-container-content">';
        
        // Actual content
        $html .= $content;
        
        $html .= '</div>'; // Close .cbd-container-content
        $html .= '</div>'; // Close .cbd-container-block
        $html .= '</div>'; // Close .cbd-content
        $html .= '</div>'; // Close .cbd-container
        
        // Add separate JavaScript file and required libraries
        static $js_added = false;
        if (!$js_added) {
            // Load robust jsPDF loader with fallbacks
            $html .= '<script src="' . CBD_PLUGIN_URL . 'assets/js/jspdf-loader.js?v=' . time() . mt_rand() . '"></script>';
            // Load our custom JavaScript
            $html .= '<script src="' . CBD_PLUGIN_URL . 'assets/js/container-blocks-inline.js?v=' . time() . mt_rand() . '"></script>';
            $js_added = true;
        }
        
        return $html;
    }
    
    /**
     * Features rendern
     */
    private function render_features($features, $block_id) {
        $html = '';
        
        // Icon Feature
        if (!empty($features['icon']['enabled'])) {
            $icon_class = $features['icon']['value'] ?? 'dashicons-admin-generic';
            $html .= '<div class="cbd-container-icon">';
            $html .= '<span class="dashicons ' . esc_attr($icon_class) . '" aria-hidden="true"></span>';
            $html .= '</div>';
        }
        
        // Numbering Feature
        if (!empty($features['numbering']['enabled'])) {
            static $numbering_counter = 0;
            $numbering_counter++;
            
            $format = $features['numbering']['format'] ?? 'numeric';
            $number = $this->format_number($numbering_counter, $format);
            
            $html .= '<div class="cbd-container-number" data-number="' . esc_attr($numbering_counter) . '">';
            $html .= esc_html($number);
            $html .= '</div>';
        }
        
        // Action Buttons - DISABLED: All buttons now in dropdown menu only
        // No separate action buttons are generated - all functionality is in the dropdown menu
        
        return $html;
    }
    
    /**
     * Nummer formatieren basierend auf Format
     */
    private function format_number($number, $format) {
        switch ($format) {
            case 'alphabetic':
                return chr(64 + $number); // A, B, C...
                
            case 'roman':
                $map = array('', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X');
                return isset($map[$number]) ? $map[$number] : $number;
                
            case 'numeric':
            default:
                return $number;
        }
    }
    
    /**
     * Inline-Styles generieren - Enhanced with all styling options
     */
    private function generate_inline_styles($styles) {
        $css = '';
        
        // Background Properties - Support admin structure
        if (!empty($styles['background'])) {
            $bg = $styles['background'];
            
            // Admin structure handles color and gradient separately
            if (!empty($bg['type'])) {
                if ($bg['type'] === 'color' && !empty($bg['color'])) {
                    $css .= 'background-color: ' . esc_attr($bg['color']) . ';';
                } elseif ($bg['type'] === 'gradient') {
                    // Handle gradient from admin structure
                    if (!empty($bg['gradient']['type']) && !empty($bg['gradient']['color1']) && !empty($bg['gradient']['color2'])) {
                        $type = $bg['gradient']['type'];
                        $color1 = $bg['gradient']['color1'];
                        $color2 = $bg['gradient']['color2'];
                        $angle = $bg['gradient']['angle'] ?? 45;
                        
                        if ($type === 'linear') {
                            $css .= "background: linear-gradient({$angle}deg, {$color1}, {$color2});";
                        } elseif ($type === 'radial') {
                            $css .= "background: radial-gradient(circle, {$color1}, {$color2});";
                        }
                    }
                }
            } else {
                // Fallback for direct background properties
                if (!empty($bg['color'])) {
                    $css .= 'background-color: ' . esc_attr($bg['color']) . ';';
                }
            }
            
            // Standard background properties (for future extensions)
            if (!empty($bg['image'])) {
                $css .= 'background-image: url(' . esc_url($bg['image']) . ');';
            }
            
            if (!empty($bg['size'])) {
                $css .= 'background-size: ' . esc_attr($bg['size']) . ';';
            }
            
            if (!empty($bg['position'])) {
                $css .= 'background-position: ' . esc_attr($bg['position']) . ';';
            }
            
            if (!empty($bg['repeat'])) {
                $css .= 'background-repeat: ' . esc_attr($bg['repeat']) . ';';
            }
        }
        
        // Text Properties
        if (!empty($styles['text'])) {
            $text = $styles['text'];
            
            // Text Color
            if (!empty($text['color'])) {
                $css .= 'color: ' . esc_attr($text['color']) . ';';
            }
            
            // Text Alignment
            if (!empty($text['alignment'])) {
                $css .= 'text-align: ' . esc_attr($text['alignment']) . ';';
            }
            
            // Font Size
            if (!empty($text['fontSize'])) {
                $css .= 'font-size: ' . esc_attr($text['fontSize']) . ';';
            }
            
            // Font Weight
            if (!empty($text['fontWeight'])) {
                $css .= 'font-weight: ' . esc_attr($text['fontWeight']) . ';';
            }
            
            // Font Family
            if (!empty($text['fontFamily'])) {
                $css .= 'font-family: ' . esc_attr($text['fontFamily']) . ';';
            }
            
            // Line Height
            if (!empty($text['lineHeight'])) {
                $css .= 'line-height: ' . esc_attr($text['lineHeight']) . ';';
            }
            
            // Letter Spacing
            if (!empty($text['letterSpacing'])) {
                $css .= 'letter-spacing: ' . esc_attr($text['letterSpacing']) . ';';
            }
            
            // Text Transform
            if (!empty($text['textTransform'])) {
                $css .= 'text-transform: ' . esc_attr($text['textTransform']) . ';';
            }
            
            // Text Decoration
            if (!empty($text['textDecoration'])) {
                $css .= 'text-decoration: ' . esc_attr($text['textDecoration']) . ';';
            }
        }
        
        // Spacing Properties
        // Padding
        if (!empty($styles['padding'])) {
            if (is_array($styles['padding'])) {
                $top = $styles['padding']['top'] ?? 0;
                $right = $styles['padding']['right'] ?? 0;
                $bottom = $styles['padding']['bottom'] ?? 0;
                $left = $styles['padding']['left'] ?? 0;
                $css .= "padding: {$top}px {$right}px {$bottom}px {$left}px;";
            } else {
                $css .= 'padding: ' . esc_attr($styles['padding']) . ';';
            }
        }
        
        // Margin
        if (!empty($styles['margin'])) {
            if (is_array($styles['margin'])) {
                $top = $styles['margin']['top'] ?? 0;
                $right = $styles['margin']['right'] ?? 0;
                $bottom = $styles['margin']['bottom'] ?? 0;
                $left = $styles['margin']['left'] ?? 0;
                $css .= "margin: {$top}px {$right}px {$bottom}px {$left}px;";
            } else {
                $css .= 'margin: ' . esc_attr($styles['margin']) . ';';
            }
        }
        
        // Border Properties
        if (!empty($styles['border'])) {
            $border = $styles['border'];
            
            // Border width, style, color (combined)
            if (!empty($border['width']) && !empty($border['style']) && !empty($border['color'])) {
                $width = $border['width'] . 'px';
                $style = esc_attr($border['style']);
                $color = esc_attr($border['color']);
                $css .= "border: {$width} {$style} {$color};";
            } else {
                // Individual border properties
                if (!empty($border['width'])) {
                    $css .= 'border-width: ' . esc_attr($border['width']) . 'px;';
                }
                if (!empty($border['style'])) {
                    $css .= 'border-style: ' . esc_attr($border['style']) . ';';
                }
                if (!empty($border['color'])) {
                    $css .= 'border-color: ' . esc_attr($border['color']) . ';';
                }
            }
            
            // Border radius
            if (!empty($border['radius'])) {
                $css .= 'border-radius: ' . esc_attr($border['radius']) . 'px;';
            }
            
            // Individual border sides
            $sides = ['top', 'right', 'bottom', 'left'];
            foreach ($sides as $side) {
                if (!empty($border[$side])) {
                    $sideProps = $border[$side];
                    if (!empty($sideProps['width']) && !empty($sideProps['style']) && !empty($sideProps['color'])) {
                        $css .= "border-{$side}: {$sideProps['width']}px {$sideProps['style']} {$sideProps['color']};";
                    }
                }
            }
        }
        
        // Box Shadow - Support both new and admin data structures
        $shadowValues = [];
        
        // New structure: boxShadow array
        if (!empty($styles['boxShadow'])) {
            $shadows = $styles['boxShadow'];
            if (is_array($shadows)) {
                foreach ($shadows as $shadow) {
                    if (is_array($shadow)) {
                        $offsetX = $shadow['offsetX'] ?? 0;
                        $offsetY = $shadow['offsetY'] ?? 0;
                        $blurRadius = $shadow['blurRadius'] ?? 0;
                        $spreadRadius = $shadow['spreadRadius'] ?? 0;
                        $color = $shadow['color'] ?? 'rgba(0,0,0,0.1)';
                        $inset = !empty($shadow['inset']) ? 'inset ' : '';
                        $shadowValues[] = "{$inset}{$offsetX}px {$offsetY}px {$blurRadius}px {$spreadRadius}px {$color}";
                    }
                }
            } else {
                // Simple string shadow value
                $shadowValues[] = esc_attr($shadows);
            }
        }
        
        // Admin structure: shadow.outer and shadow.inner
        if (!empty($styles['shadow'])) {
            $shadow = $styles['shadow'];
            
            // Outer shadow
            if (!empty($shadow['outer']['enabled'])) {
                $outer = $shadow['outer'];
                $x = $outer['x'] ?? 0;
                $y = $outer['y'] ?? 4;
                $blur = $outer['blur'] ?? 6;
                $spread = $outer['spread'] ?? 0;
                $color = $outer['color'] ?? 'rgba(0,0,0,0.1)';
                $shadowValues[] = "{$x}px {$y}px {$blur}px {$spread}px {$color}";
            }
            
            // Inner shadow
            if (!empty($shadow['inner']['enabled'])) {
                $inner = $shadow['inner'];
                $x = $inner['x'] ?? 0;
                $y = $inner['y'] ?? 2;
                $blur = $inner['blur'] ?? 4;
                $spread = $inner['spread'] ?? 0;
                $color = $inner['color'] ?? 'rgba(0,0,0,0.1)';
                $shadowValues[] = "inset {$x}px {$y}px {$blur}px {$spread}px {$color}";
            }
        }
        
        if (!empty($shadowValues)) {
            $css .= 'box-shadow: ' . implode(', ', $shadowValues) . ';';
        }
        
        // Text Shadow
        if (!empty($styles['textShadow'])) {
            $css .= 'text-shadow: ' . esc_attr($styles['textShadow']) . ';';
        }
        
        // Opacity
        if (isset($styles['opacity']) && $styles['opacity'] !== '') {
            $css .= 'opacity: ' . esc_attr($styles['opacity']) . ';';
        }
        
        // Transform
        if (!empty($styles['transform'])) {
            $css .= 'transform: ' . esc_attr($styles['transform']) . ';';
        }
        
        // Transition
        if (!empty($styles['transition'])) {
            $css .= 'transition: ' . esc_attr($styles['transition']) . ';';
        }
        
        // Display
        if (!empty($styles['display'])) {
            $css .= 'display: ' . esc_attr($styles['display']) . ';';
        }
        
        // Position
        if (!empty($styles['position'])) {
            $css .= 'position: ' . esc_attr($styles['position']) . ';';
        }
        
        // Z-Index
        if (!empty($styles['zIndex'])) {
            $css .= 'z-index: ' . esc_attr($styles['zIndex']) . ';';
        }
        
        // Width and Height
        if (!empty($styles['width'])) {
            $css .= 'width: ' . esc_attr($styles['width']) . ';';
        }
        
        if (!empty($styles['height'])) {
            $css .= 'height: ' . esc_attr($styles['height']) . ';';
        }
        
        // Min/Max Width and Height
        if (!empty($styles['minWidth'])) {
            $css .= 'min-width: ' . esc_attr($styles['minWidth']) . ';';
        }
        
        if (!empty($styles['maxWidth'])) {
            $css .= 'max-width: ' . esc_attr($styles['maxWidth']) . ';';
        }
        
        if (!empty($styles['minHeight'])) {
            $css .= 'min-height: ' . esc_attr($styles['minHeight']) . ';';
        }
        
        if (!empty($styles['maxHeight'])) {
            $css .= 'max-height: ' . esc_attr($styles['maxHeight']) . ';';
        }
        
        // Overflow
        if (!empty($styles['overflow'])) {
            $css .= 'overflow: ' . esc_attr($styles['overflow']) . ';';
        }
        
        if (!empty($styles['overflowX'])) {
            $css .= 'overflow-x: ' . esc_attr($styles['overflowX']) . ';';
        }
        
        if (!empty($styles['overflowY'])) {
            $css .= 'overflow-y: ' . esc_attr($styles['overflowY']) . ';';
        }
        
        // Flexbox Properties
        if (!empty($styles['flex'])) {
            $flex = $styles['flex'];
            
            if (!empty($flex['direction'])) {
                $css .= 'flex-direction: ' . esc_attr($flex['direction']) . ';';
            }
            
            if (!empty($flex['wrap'])) {
                $css .= 'flex-wrap: ' . esc_attr($flex['wrap']) . ';';
            }
            
            if (!empty($flex['justifyContent'])) {
                $css .= 'justify-content: ' . esc_attr($flex['justifyContent']) . ';';
            }
            
            if (!empty($flex['alignItems'])) {
                $css .= 'align-items: ' . esc_attr($flex['alignItems']) . ';';
            }
            
            if (!empty($flex['alignContent'])) {
                $css .= 'align-content: ' . esc_attr($flex['alignContent']) . ';';
            }
            
            if (!empty($flex['gap'])) {
                $css .= 'gap: ' . esc_attr($flex['gap']) . ';';
            }
        }
        
        // Grid Properties
        if (!empty($styles['grid'])) {
            $grid = $styles['grid'];
            
            if (!empty($grid['templateColumns'])) {
                $css .= 'grid-template-columns: ' . esc_attr($grid['templateColumns']) . ';';
            }
            
            if (!empty($grid['templateRows'])) {
                $css .= 'grid-template-rows: ' . esc_attr($grid['templateRows']) . ';';
            }
            
            if (!empty($grid['gap'])) {
                $css .= 'grid-gap: ' . esc_attr($grid['gap']) . ';';
            }
        }
        
        // Special Effects from Admin Interface
        if (!empty($styles['effects'])) {
            $effects = $styles['effects'];
            
            // Glassmorphism effect
            if (!empty($effects['glassmorphism']['enabled'])) {
                $glass = $effects['glassmorphism'];
                $opacity = $glass['opacity'] ?? 0.1;
                $blur = $glass['blur'] ?? 10;
                $saturate = $glass['saturate'] ?? 100;
                $color = $glass['color'] ?? '#ffffff';
                
                // Convert color to rgba with opacity
                $rgba_color = $this->hex_to_rgba($color, $opacity);
                $css .= "background: {$rgba_color};";
                $css .= "backdrop-filter: blur({$blur}px) saturate({$saturate}%);";
                $css .= "-webkit-backdrop-filter: blur({$blur}px) saturate({$saturate}%);";
            }
            
            // CSS Filters
            if (!empty($effects['filters'])) {
                $filters = $effects['filters'];
                $filterValues = [];
                
                if (isset($filters['brightness']) && $filters['brightness'] != 100) {
                    $filterValues[] = "brightness({$filters['brightness']}%)";
                }
                if (isset($filters['contrast']) && $filters['contrast'] != 100) {
                    $filterValues[] = "contrast({$filters['contrast']}%)";
                }
                if (isset($filters['hue']) && $filters['hue'] != 0) {
                    $filterValues[] = "hue-rotate({$filters['hue']}deg)";
                }
                
                if (!empty($filterValues)) {
                    $css .= 'filter: ' . implode(' ', $filterValues) . ';';
                }
            }
            
            // Neumorphism effect
            if (!empty($effects['neumorphism']['enabled'])) {
                $neuro = $effects['neumorphism'];
                $style = $neuro['style'] ?? 'raised';
                $intensity = $neuro['intensity'] ?? 10;
                $background = $neuro['background'] ?? '#e0e0e0';
                $distance = $neuro['distance'] ?? 15;
                
                $css .= "background: {$background};";
                if ($style === 'raised') {
                    $css .= "box-shadow: {$distance}px {$distance}px {$intensity}px rgba(0,0,0,0.2), -{$distance}px -{$distance}px {$intensity}px rgba(255,255,255,0.7);";
                } else {
                    $css .= "box-shadow: inset {$distance}px {$distance}px {$intensity}px rgba(0,0,0,0.2), inset -{$distance}px -{$distance}px {$intensity}px rgba(255,255,255,0.7);";
                }
            }
            
            // Animation effects
            if (!empty($effects['animation'])) {
                $animation = $effects['animation'];
                $duration = $animation['duration'] ?? 300;
                $easing = $animation['easing'] ?? 'ease';
                
                $css .= "transition: all {$duration}ms {$easing};";
                
                if (!empty($animation['origin'])) {
                    $css .= "transform-origin: {$animation['origin']};";
                }
            }
        }
        
        // Custom CSS Properties (CSS Variables)
        if (!empty($styles['customProperties'])) {
            foreach ($styles['customProperties'] as $property => $value) {
                if (strpos($property, '--') === 0) {
                    $css .= esc_attr($property) . ': ' . esc_attr($value) . ';';
                }
            }
        }
        
        // Any additional custom CSS
        if (!empty($styles['custom'])) {
            $css .= esc_attr($styles['custom']) . ';';
        }
        
        return $css;
    }
    
    /**
     * Helper function to convert hex color to rgba
     */
    private function hex_to_rgba($hex, $alpha = 1) {
        // Remove # if present
        $hex = ltrim($hex, '#');
        
        // Convert hex to rgb
        if (strlen($hex) === 3) {
            $hex = str_repeat(substr($hex,0,1), 2) . str_repeat(substr($hex,1,1), 2) . str_repeat(substr($hex,2,1), 2);
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
    
    /**
     * Verfügbare Blöcke abrufen
     */
    public function get_available_blocks() {
        global $wpdb;
        
        $blocks = $wpdb->get_results(
            "SELECT name, title, description FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'",
            ARRAY_A
        );
        
        $formatted_blocks = array();
        
        foreach ($blocks as $block) {
            // Verwende den sanitized name als Wert
            $sanitized_name = $this->sanitize_block_name($block['name']);
            
            $formatted_blocks[] = array(
                'value' => $sanitized_name,
                'label' => $block['title'] ?: $block['name'],
                'description' => $block['description']
            );
        }
        
        return $formatted_blocks;
    }
}