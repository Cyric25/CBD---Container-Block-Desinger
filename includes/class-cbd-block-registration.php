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
        // Frontend CSS - Use clean version
        wp_enqueue_style(
            'cbd-frontend-clean',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend-clean.css',
            array(),
            CBD_VERSION . '-' . time() // Force cache bust
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
        
        // Add inline JavaScript for direct button functionality
        wp_add_inline_script('cbd-frontend-working', '
            jQuery(document).ready(function($) {
                console.log("CBD: Direct button handlers loaded - REMOVING OLD HANDLERS");
                
                // REMOVE ALL EXISTING HANDLERS to prevent conflicts
                $(document).off("click", ".cbd-collapse-toggle");
                $(document).off("click.cbd-working", ".cbd-collapse-toggle");
                
                // Direct collapse button handler - ONLY hide content, keep buttons visible
                $(document).on("click.cbd-direct", ".cbd-collapse-toggle", function(e) {
                    e.preventDefault();
                    console.log("CBD: Direct collapse clicked");
                    
                    var button = $(this);
                    var container = button.closest(".cbd-container");
                    var contentToHide = container.find(".cbd-container-content"); // ONLY the inner content
                    
                    console.log("CBD: Container found:", container.length);
                    console.log("CBD: Content to hide found:", contentToHide.length);
                    console.log("CBD: Content currently visible:", contentToHide.is(":visible"));
                    
                    if (contentToHide.length > 0) {
                        // Check current state BEFORE animation
                        var isCurrentlyVisible = contentToHide.is(":visible");
                        
                        // Change button icon BEFORE animation starts
                        if (isCurrentlyVisible) {
                            button.html("🔽"); // Will be collapsed after animation
                            console.log("CBD: Setting icon to collapsed (🔽)");
                        } else {
                            button.html("🔼"); // Will be expanded after animation
                            console.log("CBD: Setting icon to expanded (🔼)");
                        }
                        
                        // Start the animation
                        contentToHide.slideToggle(300, function() {
                            console.log("CBD: Animation complete, content now visible:", $(this).is(":visible"));
                        });
                        
                    } else {
                        console.log("CBD: No content found to collapse");
                    }
                });
                
                // Enhanced copy text handler
                $(document).on("click.cbd-direct", ".cbd-copy-text", function(e) {
                    e.preventDefault();
                    console.log("CBD: Direct copy clicked");
                    
                    var button = $(this);
                    var container = button.closest(".cbd-container");
                    var contentElement = container.find(".cbd-container-content");
                    
                    // Get both plain text and formatted text
                    var plainText = contentElement.text().trim();
                    var htmlContent = contentElement.html();
                    
                    console.log("CBD: Text to copy:", plainText.substring(0, 100) + "...");
                    
                    if (!plainText) {
                        // Visual feedback for empty content
                        button.html("❌");
                        setTimeout(function() { button.html("📋"); }, 1000);
                        console.log("CBD: No text found to copy");
                        return;
                    }
                    
                    // Try modern clipboard API first
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(plainText).then(function() {
                            console.log("CBD: Text copied successfully via Clipboard API");
                            // Visual feedback - change icon temporarily
                            button.html("✅");
                            setTimeout(function() { button.html("📋"); }, 1500);
                        }).catch(function(err) {
                            console.log("CBD: Clipboard API failed:", err);
                            fallbackCopy(plainText, button);
                        });
                    } else {
                        console.log("CBD: Clipboard API not available, using fallback");
                        fallbackCopy(plainText, button);
                    }
                });
                
                // Fallback copy method for older browsers
                function fallbackCopy(text, button) {
                    try {
                        // Create temporary textarea
                        var textarea = $("<textarea>");
                        textarea.val(text);
                        textarea.css({
                            position: "fixed",
                            top: "-1000px",
                            left: "-1000px",
                            opacity: "0"
                        });
                        $("body").append(textarea);
                        textarea[0].select();
                        textarea[0].setSelectionRange(0, 99999);
                        
                        // Try to copy
                        var successful = document.execCommand("copy");
                        textarea.remove();
                        
                        if (successful) {
                            console.log("CBD: Text copied successfully via execCommand");
                            button.html("✅");
                            setTimeout(function() { button.html("📋"); }, 1500);
                        } else {
                            console.log("CBD: execCommand copy failed");
                            button.html("❌");
                            setTimeout(function() { button.html("📋"); }, 1000);
                        }
                    } catch (err) {
                        console.log("CBD: Fallback copy failed:", err);
                        button.html("❌");
                        setTimeout(function() { button.html("📋"); }, 1000);
                    }
                }
                
                // Enhanced screenshot handler
                $(document).on("click.cbd-direct", ".cbd-screenshot", function(e) {
                    e.preventDefault();
                    console.log("CBD: Direct screenshot clicked");
                    
                    var button = $(this);
                    var container = button.closest(".cbd-container");
                    var contentElement = container.find(".cbd-container-block");
                    
                    // Check if html2canvas is available
                    if (typeof html2canvas === "undefined") {
                        console.log("CBD: html2canvas not loaded");
                        button.html("❌");
                        setTimeout(function() { button.html("📷"); }, 1500);
                        return;
                    }
                    
                    if (contentElement.length === 0) {
                        console.log("CBD: No content found for screenshot");
                        button.html("❌");
                        setTimeout(function() { button.html("📷"); }, 1500);
                        return;
                    }
                    
                    // Show loading state
                    button.html("⏳");
                    console.log("CBD: Starting screenshot capture...");
                    
                    // Check if content is collapsed and expand if needed
                    var contentToCheck = container.find(".cbd-container-content");
                    var wasCollapsed = false;
                    
                    if (contentToCheck.length > 0 && !contentToCheck.is(":visible")) {
                        console.log("CBD: Content is collapsed, expanding for screenshot...");
                        wasCollapsed = true;
                        contentToCheck.show(); // Show immediately without animation
                    }
                    
                    // Temporarily hide the buttons and numbering for clean screenshot
                    var buttonContainer = container.find(".cbd-action-buttons");
                    var numbering = container.find(".cbd-container-number");
                    var originalButtonDisplay = buttonContainer.css("display");
                    var originalNumberDisplay = numbering.css("display");
                    
                    buttonContainer.hide();
                    numbering.hide();
                    
                    // html2canvas options
                    var options = {
                        backgroundColor: null, // Transparent background
                        scale: 2, // Higher quality
                        useCORS: true,
                        allowTaint: false,
                        logging: false,
                        width: contentElement.outerWidth(),
                        height: contentElement.outerHeight(),
                        scrollX: 0,
                        scrollY: 0
                    };
                    
                    html2canvas(contentElement[0], options).then(function(canvas) {
                        console.log("CBD: Screenshot captured successfully");
                        
                        // Restore hidden elements
                        buttonContainer.css("display", originalButtonDisplay);
                        numbering.css("display", originalNumberDisplay);
                        
                        // Restore collapsed state if it was collapsed before
                        if (wasCollapsed) {
                            console.log("CBD: Restoring collapsed state...");
                            contentToCheck.hide();
                        }
                        
                        // Convert to blob and download
                        canvas.toBlob(function(blob) {
                            var url = URL.createObjectURL(blob);
                            var timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, "-");
                            var filename = "container-block-" + timestamp + ".png";
                            
                            // Create download link
                            var link = document.createElement("a");
                            link.download = filename;
                            link.href = url;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            
                            // Clean up
                            URL.revokeObjectURL(url);
                            
                            // Success feedback
                            button.html("✅");
                            setTimeout(function() { button.html("📷"); }, 2000);
                            
                            console.log("CBD: Screenshot downloaded as " + filename);
                        }, "image/png");
                        
                    }).catch(function(error) {
                        console.log("CBD: Screenshot failed:", error);
                        
                        // Restore hidden elements
                        buttonContainer.css("display", originalButtonDisplay);
                        numbering.css("display", originalNumberDisplay);
                        
                        // Restore collapsed state if it was collapsed before
                        if (wasCollapsed) {
                            console.log("CBD: Error occurred, restoring collapsed state...");
                            contentToCheck.hide();
                        }
                        
                        // Error feedback
                        button.html("❌");
                        setTimeout(function() { button.html("📷"); }, 1500);
                    });
                });
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
        
        wp_enqueue_script(
            'cbd-frontend-working',
            CBD_PLUGIN_URL . 'assets/js/frontend-working.js',
            array('jquery'),
            CBD_VERSION . '-' . time(), // Force cache bust
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
        global $wpdb;
        
        // Cache prüfen
        $cache_key = 'cbd_has_interactive_features';
        $has_features = wp_cache_get($cache_key);
        
        if (false === $has_features) {
            // Prüfe ob irgendein Block interaktive Features hat
            $result = $wpdb->get_var(
                "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " 
                 WHERE status = 'active' 
                 AND (features LIKE '%copyText%' 
                      OR features LIKE '%screenshot%')"
            );
            
            $has_features = $result > 0;
            wp_cache_set($cache_key, $has_features, '', HOUR_IN_SECONDS);
        }
        
        return $has_features;
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
        
        $html .= '<div class="cbd-container-number cbd-outside-number" data-number="' . esc_attr($numbering_counter) . '" style="position: absolute !important; top: -40px !important; left: -40px !important; background: rgba(0,0,0,0.9) !important; color: white !important; width: 34px !important; height: 34px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-size: 15px !important; font-weight: bold !important; z-index: 99999 !important; border: 2px solid white !important;">';
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
        
        // Professional button styling - all buttons visible and functional
        $html .= '<!-- CBD: THREE BUTTONS - PROFESSIONAL STYLING -->';
        $html .= '<div class="cbd-action-buttons" style="position: absolute !important; top: 10px !important; right: 10px !important; z-index: 9999 !important; display: flex !important; gap: 4px !important; background: rgba(0,0,0,0.1) !important; padding: 4px !important; border-radius: 8px !important; backdrop-filter: blur(5px) !important;">';
        
        // Button 1: Collapse
        $html .= '<button type="button" class="cbd-collapse-toggle" data-container-id="' . esc_attr($container_id) . '" style="background: rgba(0,0,0,0.75) !important; color: white !important; border: none !important; padding: 8px !important; border-radius: 6px !important; cursor: pointer !important; width: 32px !important; height: 32px !important; display: flex !important; align-items: center !important; justify-content: center !important; visibility: visible !important; opacity: 1 !important; position: relative !important; z-index: 10000 !important; transition: all 0.2s ease !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;" title="Einklappen" onmouseover="this.style.background=\'rgba(0,0,0,0.9)\'" onmouseout="this.style.background=\'rgba(0,0,0,0.75)\'">';
        $html .= '🔼';
        $html .= '</button>';
        
        // Button 2: Copy Text
        $html .= '<button type="button" class="cbd-copy-text" data-container-id="' . esc_attr($container_id) . '" style="background: rgba(0,0,0,0.75) !important; color: white !important; border: none !important; padding: 8px !important; border-radius: 6px !important; cursor: pointer !important; width: 32px !important; height: 32px !important; display: flex !important; align-items: center !important; justify-content: center !important; visibility: visible !important; opacity: 1 !important; position: relative !important; z-index: 10000 !important; transition: all 0.2s ease !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;" title="Text kopieren" onmouseover="this.style.background=\'rgba(0,0,0,0.9)\'" onmouseout="this.style.background=\'rgba(0,0,0,0.75)\'">';
        $html .= '📋';
        $html .= '</button>';
        
        // Button 3: Screenshot
        $html .= '<button type="button" class="cbd-screenshot" data-container-id="' . esc_attr($container_id) . '" style="background: rgba(0,0,0,0.75) !important; color: white !important; border: none !important; padding: 8px !important; border-radius: 6px !important; cursor: pointer !important; width: 32px !important; height: 32px !important; display: flex !important; align-items: center !important; justify-content: center !important; visibility: visible !important; opacity: 1 !important; position: relative !important; z-index: 10000 !important; transition: all 0.2s ease !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;" title="Screenshot" onmouseover="this.style.background=\'rgba(0,0,0,0.9)\'" onmouseout="this.style.background=\'rgba(0,0,0,0.75)\'">';
        $html .= '📷';
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
                $icon_style = 'position: absolute; top: 12px; left: 12px; font-size: 20px; z-index: 10;' . ($icon_color ? ' ' . substr($icon_color, 7, -1) : '');
                
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
     * Inline-Styles generieren
     */
    private function generate_inline_styles($styles) {
        $css = '';
        
        // Background Color
        if (!empty($styles['background']['color'])) {
            $css .= 'background-color: ' . esc_attr($styles['background']['color']) . ';';
        }
        
        // Text Color
        if (!empty($styles['text']['color'])) {
            $css .= 'color: ' . esc_attr($styles['text']['color']) . ';';
        }
        
        // Text Alignment
        if (!empty($styles['text']['alignment'])) {
            $css .= 'text-align: ' . esc_attr($styles['text']['alignment']) . ';';
        }
        
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
        
        // Border
        if (!empty($styles['border'])) {
            $border = $styles['border'];
            
            // Border width, style, color
            if (!empty($border['width']) && !empty($border['style']) && !empty($border['color'])) {
                $width = $border['width'] . 'px';
                $style = esc_attr($border['style']);
                $color = esc_attr($border['color']);
                $css .= "border: {$width} {$style} {$color};";
            }
            
            // Border radius
            if (!empty($border['radius'])) {
                $css .= 'border-radius: ' . esc_attr($border['radius']) . 'px;';
            }
        }
        
        return $css;
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