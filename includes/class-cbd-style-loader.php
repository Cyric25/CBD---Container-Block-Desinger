<?php
/**
 * Container Block Designer - Style Loader
 * Verbesserte Verwaltung der Block-Styles
 * Version: 2.5.2
 * 
 * Datei: includes/class-cbd-style-loader.php
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Style Loader Klasse
 * Lädt und verwaltet alle Block-Styles dynamisch
 */
class CBD_Style_Loader {
    
    /**
     * Singleton-Instanz
     */
    private static $instance = null;
    
    /**
     * Zwischenspeicher für Block-Styles
     */
    private $block_styles_cache = array();
    
    /**
     * Debug-Modus
     */
    private $debug_mode = false;
    
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
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->init_hooks();
    }
    
    /**
     * Hooks initialisieren
     */
    private function init_hooks() {
        // Styles im Frontend laden
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'), 10);
        
        // Styles im Block Editor laden
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_styles'), 10);
        
        // Dynamische Styles generieren
        add_action('wp_head', array($this, 'output_dynamic_styles'), 100);
        add_action('admin_head', array($this, 'output_editor_dynamic_styles'), 100);
        add_action('admin_footer', array($this, 'output_editor_dynamic_styles'), 100);
        add_action('admin_footer', array($this, 'output_emergency_editor_styles'), 999);
        add_action('admin_print_styles', array($this, 'output_emergency_editor_styles'), 999);
        add_action('admin_print_footer_scripts', array($this, 'output_emergency_editor_styles'), 999);
        
        // AJAX für Style-Updates
        add_action('wp_ajax_cbd_refresh_styles', array($this, 'ajax_refresh_styles'));
        
        // Cache leeren bei Block-Updates
        add_action('cbd_block_saved', array($this, 'clear_styles_cache'));
        add_action('cbd_block_deleted', array($this, 'clear_styles_cache'));
    }
    
    /**
     * Frontend-Styles laden
     */
    public function enqueue_frontend_styles() {
        // Basis-Styles für Container
        wp_enqueue_style(
            'cbd-block-base',
            CBD_PLUGIN_URL . 'assets/css/block-base.css',
            array(),
            CBD_VERSION
        );
        
        // Responsive Styles
        wp_enqueue_style(
            'cbd-block-responsive',
            CBD_PLUGIN_URL . 'assets/css/block-responsive.css',
            array('cbd-block-base'),
            CBD_VERSION
        );
        
        // Feature-spezifische Styles
        $this->enqueue_feature_styles();
    }
    
    /**
     * Editor-Styles laden
     */
    public function enqueue_editor_styles() {
        // Load frontend styles in editor for accurate preview
        wp_enqueue_style(
            'cbd-editor-frontend-base',
            CBD_PLUGIN_URL . 'assets/css/block-base.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
        
        wp_enqueue_style(
            'cbd-editor-frontend-responsive',
            CBD_PLUGIN_URL . 'assets/css/block-responsive.css',
            array('cbd-editor-frontend-base'),
            CBD_VERSION
        );
        
        wp_enqueue_style(
            'cbd-editor-frontend-consolidated',
            CBD_PLUGIN_URL . 'assets/css/frontend-consolidated.css',
            array('cbd-editor-frontend-responsive'),
            CBD_VERSION
        );
        
        // Editor-spezifische Anpassungen
        wp_enqueue_style(
            'cbd-editor-base',
            CBD_PLUGIN_URL . 'assets/css/editor-base.css',
            array('cbd-editor-frontend-consolidated'),
            CBD_VERSION
        );
        
        // Dashicons für Icons im Editor
        wp_enqueue_style('dashicons');
        
        // Alle aktiven Block-Styles für Vorschau
        $this->enqueue_block_preview_styles();
        
        // Force add inline styles to ensure they load
        $this->add_inline_editor_styles();
    }
    
    /**
     * Feature-spezifische Styles laden
     */
    private function enqueue_feature_styles() {
        $active_features = $this->get_active_features();
        
        // Icons
        if (in_array('icon', $active_features)) {
            wp_enqueue_style('dashicons');
        }
        
        // Collapsible
        if (in_array('collapsible', $active_features)) {
            wp_enqueue_style(
                'cbd-feature-collapsible',
                CBD_PLUGIN_URL . 'assets/css/features/collapsible.css',
                array(),
                CBD_VERSION
            );
        }
        
        // Copy Button
        if (in_array('copy', $active_features)) {
            wp_enqueue_style(
                'cbd-feature-copy',
                CBD_PLUGIN_URL . 'assets/css/features/copy.css',
                array(),
                CBD_VERSION
            );
        }

        // Board Mode (Tafel-Modus)
        if (in_array('boardMode', $active_features)) {
            wp_enqueue_style(
                'cbd-board-mode',
                CBD_PLUGIN_URL . 'assets/css/board-mode.css',
                array('dashicons'),
                CBD_VERSION
            );
            wp_enqueue_script(
                'cbd-board-mode',
                CBD_PLUGIN_URL . 'assets/js/board-mode.js',
                array(),
                CBD_VERSION,
                true
            );
        }

        // Personal Notes Manager (Export/Import persönlicher Notizen)
        $notes_manager_enabled = get_option('cbd_personal_notes_manager', 'disabled');
        if ($notes_manager_enabled !== 'disabled') {
            // Nur auf konfigurierten Seiten anzeigen
            $show_on_pages = get_option('cbd_notes_manager_pages', array());
            $current_page_id = get_the_ID();
            $should_show = false;

            if ($notes_manager_enabled === 'all') {
                // Auf allen Seiten mit Container Blocks
                $should_show = true;
            } elseif ($notes_manager_enabled === 'specific' && !empty($show_on_pages)) {
                // Nur auf spezifischen Seiten
                $should_show = in_array($current_page_id, $show_on_pages);
            }

            if ($should_show) {
                wp_enqueue_style(
                    'cbd-personal-notes-manager',
                    CBD_PLUGIN_URL . 'assets/css/personal-notes-manager.css',
                    array('dashicons'),
                    CBD_VERSION
                );
                wp_enqueue_script(
                    'cbd-personal-notes-manager',
                    CBD_PLUGIN_URL . 'assets/js/personal-notes-manager.js',
                    array(),
                    CBD_VERSION,
                    true
                );
            }
        }
    }
    
    /**
     * Block-Vorschau-Styles im Editor laden - NATIVE WORDPRESS METHOD
     */
    private function enqueue_block_preview_styles() {
        $blocks = $this->get_all_blocks();
        
        if (empty($blocks)) {
            return;
        }
        
        // Generate dynamic CSS for all blocks using WordPress native wp_add_inline_style
        $dynamic_css = $this->generate_editor_dynamic_css($blocks);
        
        if (!empty($dynamic_css)) {
            // Use WordPress native method instead of JavaScript DOM manipulation
            wp_add_inline_style('cbd-editor-base', $dynamic_css);
            
            if ($this->debug_mode) {
                error_log('CBD: Generated dynamic editor CSS: ' . strlen($dynamic_css) . ' characters');
            }
        }
        
        // Inline-Styles für alle Blocks generieren
        $preview_css = $this->generate_all_block_styles($blocks, true);
        
        if (!empty($preview_css)) {
            wp_add_inline_style('cbd-editor-base', $preview_css);
        }
    }
    
    /**
     * Generate dynamic CSS for editor with current block styles
     */
    private function generate_editor_dynamic_css($blocks) {
        $css = "/* Container Block Designer - Dynamic Editor Styles */\n";
        $css .= "/* Generated: " . date('Y-m-d H:i:s') . " */\n\n";
        
        // FIRST: Always add the empty selection warning CSS at the top with high priority
        $css .= $this->generate_empty_selection_warning();
        
        foreach ($blocks as $block) {
            if (empty($block['slug'])) {
                continue;
            }
            
            $slug = sanitize_html_class($block['slug']);
            
            // Parse styles from database (check both 'styles' and other possible locations)
            $styles = array();
            $has_real_styles = false;
            
            if (!empty($block['styles'])) {
                $styles = is_array($block['styles']) ? $block['styles'] : json_decode($block['styles'], true);
                $has_real_styles = $this->has_valid_css_styles($styles);
            } elseif (!empty($block['css_styles'])) {
                $styles = is_array($block['css_styles']) ? $block['css_styles'] : json_decode($block['css_styles'], true);
                $has_real_styles = $this->has_valid_css_styles($styles);
            } elseif (!empty($block['config']['styles'])) {
                $styles = $block['config']['styles'];
                $has_real_styles = $this->has_valid_css_styles($styles);
            }
            
            if ($has_real_styles && !empty($styles) && is_array($styles)) {
                $css .= $this->generate_dynamic_block_editor_css($slug, $styles);
            } else {
                // Generate warning styles for blocks without real CSS styles
                $css .= $this->generate_warning_css($slug);
                if ($this->debug_mode) {
                    error_log("CBD: No valid styles found for block: {$slug}");
                }
            }
        }
        
        return $css;
    }
    
    /**
     * Generate CSS for specific block in editor - NEW METHOD
     */
    private function generate_dynamic_block_editor_css($slug, $styles) {
        $css = "/* SPECIFIC BLOCK STYLES: {$slug} - HIGHER SPECIFICITY THAN WARNING */\n";
        
        // Editor-specific selectors with ULTRA HIGH specificity to override warning styles
        $selectors = array(
            // Ultra-high specificity to ensure these styles override the warning
            "html body.wp-admin .block-editor-page .wp-block[data-type*=\"container-block-designer\"].wp-block-container-block-designer-{$slug}",
            "body.wp-admin .wp-block.is-selected[data-type*=\"container-block-designer\"].wp-block-container-block-designer-{$slug}",
            "body.wp-admin .wp-block[data-type*=\"container-block-designer\"][class*=\"{$slug}\"]",
            "html body .wp-block-container-block-designer-{$slug}",
        );
        
        $css .= implode(",\n", $selectors) . " {\n";
        
        // Background
        if (!empty($styles['background']['color'])) {
            $css .= "    background-color: {$styles['background']['color']} !important;\n";
            $css .= "    background: {$styles['background']['color']} !important;\n";
            $css .= "    background-image: none !important;\n";
        }
        
        // Border
        if (!empty($styles['border'])) {
            if (!empty($styles['border']['width']) && !empty($styles['border']['color'])) {
                $border_style = $styles['border']['style'] ?? 'solid';
                $css .= "    border: {$styles['border']['width']}px {$border_style} {$styles['border']['color']} !important;\n";
                $css .= "    border-color: {$styles['border']['color']} !important;\n";
                $css .= "    border-style: {$border_style} !important;\n";
                $css .= "    border-width: {$styles['border']['width']}px !important;\n";
            }
            if (!empty($styles['border']['radius'])) {
                $css .= "    border-radius: {$styles['border']['radius']}px !important;\n";
            }
        }
        
        // Padding
        if (!empty($styles['padding'])) {
            if (is_array($styles['padding'])) {
                $padding = sprintf('%spx %spx %spx %spx',
                    $styles['padding']['top'] ?? 20,
                    $styles['padding']['right'] ?? 20,
                    $styles['padding']['bottom'] ?? 20,
                    $styles['padding']['left'] ?? 20
                );
                $css .= "    padding: {$padding} !important;\n";
            } else {
                $css .= "    padding: {$styles['padding']} !important;\n";
            }
        }
        
        // Text color
        if (!empty($styles['color'])) {
            $css .= "    color: {$styles['color']} !important;\n";
        }
        
        // Override any warning pseudo-elements for this specific block
        $css .= "}\n\n";
        
        // Remove warning pseudo-elements for specific blocks
        $css .= implode(",\n", $selectors) . "::before,\n";
        $css .= implode(",\n", $selectors) . "::after {\n";
        $css .= "    content: none !important;\n";
        $css .= "    display: none !important;\n";
        $css .= "}\n\n";
        
        // Reset inner elements
        $css .= implode(",\n", $selectors) . " .block-editor-inner-blocks,\n";
        $css .= implode(",\n", $selectors) . " .block-editor-block-list__layout {\n";
        $css .= "    background: transparent !important;\n";
        $css .= "    border: none !important;\n";
        $css .= "    padding: 0 !important;\n";
        $css .= "}\n\n";
        
        if ($this->debug_mode) {
            error_log("CBD: Generated specific block CSS for: {$slug}");
        }
        
        return $css;
    }
    
    /**
     * Generate warning CSS for blocks without styles
     */
    private function generate_warning_css($slug) {
        $css = "/* Warning for undefined block: {$slug} */\n";
        
        $selectors = array(
            ".wp-block.is-selected[data-type*=\"container-block-designer\"][class*=\"{$slug}\"]",
            ".wp-block[data-type*=\"container-block-designer\"].wp-block-container-block-designer-{$slug}"
        );
        
        $css .= implode(",\n", $selectors) . " {\n";
        $css .= "    background-color: #fff3cd !important;\n";
        $css .= "    border: 2px dashed #ffc107 !important;\n";
        $css .= "    color: #856404 !important;\n";
        $css .= "    padding: 20px !important;\n";
        $css .= "    text-align: center !important;\n";
        $css .= "    position: relative !important;\n";
        $css .= "}\n\n";
        
        $css .= implode(",\n", $selectors) . "::before {\n";
        $css .= "    content: '⚠️ Kein Style gewählt für: {$slug}' !important;\n";
        $css .= "    display: block !important;\n";
        $css .= "    font-weight: bold !important;\n";
        $css .= "    margin-bottom: 5px !important;\n";
        $css .= "}\n\n";
        
        $css .= implode(",\n", $selectors) . "::after {\n";
        $css .= "    content: 'Bitte definieren Sie Styles für diesen Block.' !important;\n";
        $css .= "    display: block !important;\n";
        $css .= "    font-size: 0.9em !important;\n";
        $css .= "}\n\n";
        
        return $css;
    }
    
    /**
     * Check if styles contain real CSS properties (not just block config)
     */
    private function has_valid_css_styles($styles) {
        if (!is_array($styles)) {
            return false;
        }

        // Check for actual CSS properties (erweiterte Liste)
        $css_properties = array('background', 'border', 'padding', 'margin', 'color', 'font', 'text', 'typography', 'width', 'height', 'display', 'position');

        foreach ($css_properties as $property) {
            if (!empty($styles[$property])) {
                return true;
            }
        }

        // Check for nested style properties (erweiterte Prüfung)
        if (!empty($styles['background']['color']) ||
            !empty($styles['border']['color']) ||
            !empty($styles['border']['width']) ||
            !empty($styles['padding']['top']) ||
            !empty($styles['padding']['left']) ||
            !empty($styles['typography']['color']) ||
            !empty($styles['text']['color'])) {
            return true;
        }

        // Fallback: Wenn es ein Array mit mindestens einer Eigenschaft ist,
        // betrachte es als gültig (bessere Kompatibilität mit duplizierten Blocks)
        if (is_array($styles) && count($styles) > 0) {
            if ($this->debug_mode) {
                error_log('[CBD Style Loader] Found non-empty styles array, treating as valid: ' . print_r($styles, true));
            }
            return true;
        }

        // Debug: Logge was in den Styles ist, wenn sie als ungültig eingestuft werden
        if ($this->debug_mode) {
            error_log('[CBD Style Loader] No valid styles found: ' . print_r($styles, true));
        }

        return false;
    }
    
    /**
     * Generate warning CSS for empty selectedBlock values
     */
    private function generate_empty_selection_warning() {
        $css = "/* WARNING SYSTEM FOR EMPTY SELECTIONS - ULTRA HIGH SPECIFICITY */\n";
        
        // STRATEGY: Use ultra-high specificity selectors that will definitely match
        // Target the generic container block class that WordPress applies when no specific block is selected
        $genericSelectors = array(
            // When selectedBlock is empty, WordPress uses the generic class name
            "body.wp-admin .wp-block[data-type*=\"container-block-designer\"].wp-block-container-block-designer-container", 
            "html body.wp-admin .block-editor-page .wp-block[data-type*=\"container-block-designer\"].wp-block-container-block-designer-container",
            "body.wp-admin .wp-block.is-selected[data-type*=\"container-block-designer\"].wp-block-container-block-designer-container",
        );
        
        // Apply warning styles to generic container blocks (no specific style selected)
        $css .= implode(",\n", $genericSelectors) . " {\n";
        $css .= "    background-color: #fff3cd !important;\n";
        $css .= "    background-image: none !important;\n";
        $css .= "    background: #fff3cd !important;\n";
        $css .= "    border: 3px dashed #ffc107 !important;\n";
        $css .= "    color: #856404 !important;\n";
        $css .= "    padding: 30px !important;\n";
        $css .= "    text-align: center !important;\n";
        $css .= "    position: relative !important;\n";
        $css .= "    min-height: 120px !important;\n";
        $css .= "    box-shadow: none !important;\n";
        $css .= "    transition: none !important;\n";
        $css .= "}\n\n";
        
        // Add warning text with pseudo-elements
        $css .= implode(",\n", $genericSelectors) . "::before {\n";
        $css .= "    content: '⚠️ Kein Container-Style ausgewählt!' !important;\n";
        $css .= "    display: block !important;\n";
        $css .= "    font-weight: bold !important;\n";
        $css .= "    font-size: 16px !important;\n";
        $css .= "    margin-bottom: 10px !important;\n";
        $css .= "    color: #856404 !important;\n";
        $css .= "    position: absolute !important;\n";
        $css .= "    top: 50% !important;\n";
        $css .= "    left: 50% !important;\n";
        $css .= "    transform: translate(-50%, -50%) !important;\n";
        $css .= "    z-index: 1000 !important;\n";
        $css .= "}\n\n";
        
        $css .= implode(",\n", $genericSelectors) . "::after {\n";
        $css .= "    content: 'Wählen Sie bitte einen Container-Style im Dropdown-Menü rechts aus.' !important;\n";
        $css .= "    display: block !important;\n";
        $css .= "    font-size: 14px !important;\n";
        $css .= "    line-height: 1.4 !important;\n";
        $css .= "    color: #856404 !important;\n";
        $css .= "    position: absolute !important;\n";
        $css .= "    top: 60% !important;\n";
        $css .= "    left: 50% !important;\n";
        $css .= "    transform: translate(-50%, -50%) !important;\n";
        $css .= "    z-index: 1000 !important;\n";
        $css .= "    max-width: 80% !important;\n";
        $css .= "}\n\n";
        
        // OVERRIDE any existing styles that might conflict
        $css .= "/* OVERRIDE EXISTING STYLES THAT MIGHT SHOW THE FIRST BLOCK */\n";
        $css .= implode(",\n", $genericSelectors) . " {\n";
        $css .= "    /* Force override any inherited styles from first block */\n";
        $css .= "    background-color: #fff3cd !important;\n";
        $css .= "    border-color: #ffc107 !important;\n";
        $css .= "    border-style: dashed !important;\n";
        $css .= "    border-width: 3px !important;\n";
        $css .= "}\n\n";
        
        if ($this->debug_mode) {
            error_log("CBD: Generated empty selection warning CSS with " . count($genericSelectors) . " selectors");
        }
        
        return $css;
    }
    
    /**
     * Dynamische Styles im Frontend ausgeben
     */
    public function output_dynamic_styles() {
        $blocks = $this->get_used_blocks_on_page();
        
        if (empty($blocks)) {
            return;
        }
        
        $css = $this->generate_block_styles_for_page($blocks);
        
        if (!empty($css)) {
            echo "\n<!-- Container Block Designer Dynamic Styles -->\n";
            echo '<style id="cbd-dynamic-styles">' . "\n";
            echo $this->minify_css($css);
            echo "\n</style>\n";
        }
    }
    
    /**
     * Dynamische Styles im Editor ausgeben
     */
    public function output_editor_dynamic_styles() {
        $screen = get_current_screen();
        
        // Nur im Block Editor
        if (!$screen || !$screen->is_block_editor()) {
            return;
        }
        
        // Force load editor base styles inline
        echo "\n<!-- Container Block Designer Editor Styles -->\n";
        echo '<style id="cbd-editor-styles">' . "\n";
        
        // Load base editor CSS inline
        $editor_css_path = CBD_PLUGIN_DIR . 'assets/css/editor-base.css';
        if (file_exists($editor_css_path)) {
            $base_css = file_get_contents($editor_css_path);
            // Replace @import with actual CSS content
            $base_css = str_replace('@import url(\'block-base.css\');', $this->get_css_file_content('block-base.css'), $base_css);
            $base_css = str_replace('@import url(\'block-responsive.css\');', $this->get_css_file_content('block-responsive.css'), $base_css);
            $base_css = str_replace('@import url(\'frontend-consolidated.css\');', $this->get_css_file_content('frontend-consolidated.css'), $base_css);
            echo $base_css . "\n";
        }
        
        // Add dynamic block styles
        $blocks = $this->get_all_blocks();
        if (!empty($blocks)) {
            $css = $this->generate_all_block_styles($blocks, true);
            if (!empty($css)) {
                echo $css . "\n";
            }
        }
        
        echo "\n</style>\n";
    }
    
    /**
     * Get CSS file content
     */
    private function get_css_file_content($filename) {
        $file_path = CBD_PLUGIN_DIR . 'assets/css/' . $filename;
        if (file_exists($file_path)) {
            return file_get_contents($file_path) . "\n";
        }
        return '';
    }
    
    /**
     * Add inline editor styles directly
     */
    private function add_inline_editor_styles() {
        // Get all CSS content and add as inline styles
        $css_content = '';
        
        // Essential editor CSS
        $css_content .= "
        /* Essential Container Block Designer Editor Styles */
        .wp-block[data-type*=\"container-block-designer\"] .cbd-container-block,
        [class*=\"wp-block-container-block-designer\"] .cbd-container-block {
            position: relative;
            display: block;
            width: 100%;
            min-height: 50px;
            padding: 20px;
            margin: 10px 0;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            background: #fff;
            transition: all 0.2s ease;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-container-block:hover,
        [class*=\"wp-block-container-block-designer\"] .cbd-container-block:hover {
            outline: 2px solid #007cba;
            outline-offset: 2px;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-block-icon,
        [class*=\"wp-block-container-block-designer\"] .cbd-block-icon {
            position: absolute;
            z-index: 10;
            font-family: dashicons;
            font-size: 20px;
            width: 20px;
            height: 20px;
            line-height: 1;
            color: #666;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-block-icon.top-left,
        [class*=\"wp-block-container-block-designer\"] .cbd-block-icon.top-left {
            top: 10px;
            left: 10px;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-block-icon.top-center,
        [class*=\"wp-block-container-block-designer\"] .cbd-block-icon.top-center {
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-block-icon.top-right,
        [class*=\"wp-block-container-block-designer\"] .cbd-block-icon.top-right {
            top: 10px;
            right: 10px;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-block-number,
        [class*=\"wp-block-container-block-designer\"] .cbd-block-number {
            position: absolute;
            z-index: 10;
            font-weight: bold;
            font-size: 16px;
            color: #333;
            background: rgba(255,255,255,0.9);
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-block-number.top-left,
        [class*=\"wp-block-container-block-designer\"] .cbd-block-number.top-left {
            top: 5px;
            left: 5px;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-feature-buttons,
        [class*=\"wp-block-container-block-designer\"] .cbd-feature-buttons {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 8px;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-feature-button,
        [class*=\"wp-block-container-block-designer\"] .cbd-feature-button {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: #f9f9f9;
            color: #666;
            pointer-events: none;
            opacity: 0.8;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-container-error,
        [class*=\"wp-block-container-block-designer\"] .cbd-container-error {
            border: 2px dashed #e74c3c;
            background: #fdf2f2;
            padding: 20px;
            border-radius: 8px;
            margin: 10px 0;
            color: #e74c3c;
            font-weight: 600;
        }
        
        .wp-block[data-type*=\"container-block-designer\"] .cbd-container-error a,
        [class*=\"wp-block-container-block-designer\"] .cbd-container-error a {
            color: #007cba;
            text-decoration: none;
        }
        ";
        
        // Add dynamic block styles
        $blocks = $this->get_all_blocks();
        if (!empty($blocks)) {
            foreach ($blocks as $block) {
                // Fix: Use array notation since get_all_blocks() returns ARRAY_A
                $styles = json_decode($block['styles'], true);
                if (!empty($styles)) {
                    $block_class = '.wp-block-container-block-designer-' . sanitize_html_class($block['slug']);
                    $css_content .= $this->generate_block_editor_css($styles, $block_class, $block['slug']);
                }
            }
        }
        
        // Add inline styles
        wp_add_inline_style('wp-edit-blocks', $css_content);
    }
    
    /**
     * Generate CSS for a specific block in editor
     */
    private function generate_block_editor_css($styles, $block_class, $block_slug) {
        $css = '';
        
        if (empty($styles)) {
            return $css;
        }
        
        $css .= "\n/* Block: " . $block_slug . " */\n";
        $css .= $block_class . ' .cbd-container-block {';
        
        // Background
        if (!empty($styles['background']['color'])) {
            $css .= 'background-color: ' . esc_attr($styles['background']['color']) . ' !important;';
        }
        
        // Text color
        if (!empty($styles['text']['color'])) {
            $css .= 'color: ' . esc_attr($styles['text']['color']) . ' !important;';
        }
        
        // Border
        if (!empty($styles['border'])) {
            $border = $styles['border'];
            if (!empty($border['width']) && !empty($border['color']) && !empty($border['style'])) {
                $css .= sprintf('border: %dpx %s %s !important;', 
                    intval($border['width']), 
                    esc_attr($border['style']), 
                    esc_attr($border['color'])
                );
            }
            
            if (!empty($border['radius'])) {
                $css .= 'border-radius: ' . intval($border['radius']) . 'px !important;';
            }
        }
        
        $css .= '}';
        
        return $css;
    }
    
    /**
     * Production editor styles with professional appearance
     */
    public function output_emergency_editor_styles() {
        $screen = get_current_screen();
        
        // Only output on block editor screens or when screen is not detected
        if ($screen && !$screen->is_block_editor()) {
            return;
        }
        
        echo "\n<!-- Container Block Designer Production Editor Styles -->\n";
        
        // Debug: Load and show blocks
        $blocks = $this->get_all_blocks();
        echo "<!-- CBD DEBUG: Found " . count($blocks) . " blocks -->\n";
        
        // Debug each block with FULL details
        if (!empty($blocks)) {
            foreach ($blocks as $i => $block) {
                echo "<!-- CBD DEBUG Block " . ($i+1) . ": " . $block['name'] . " (Slug: " . $block['slug'] . ") -->\n";
                echo "<!-- CBD DEBUG Full Styles for " . $block['slug'] . ": " . $block['styles'] . " -->\n";
                echo "<!-- CBD DEBUG Features: " . substr($block['features'], 0, 100) . "... -->\n";
                
                // Parse and debug styles
                $parsed_styles = json_decode($block['styles'], true);
                if ($parsed_styles) {
                    echo "<!-- CBD DEBUG Parsed Styles for " . $block['slug'] . ": -->\n";
                    if (isset($parsed_styles['background']['color'])) {
                        echo "<!-- CBD DEBUG - Background Color: " . $parsed_styles['background']['color'] . " -->\n";
                    }
                    if (isset($parsed_styles['border']['color'])) {
                        echo "<!-- CBD DEBUG - Border Color: " . $parsed_styles['border']['color'] . " -->\n";
                    }
                    if (isset($parsed_styles['border']['width'])) {
                        echo "<!-- CBD DEBUG - Border Width: " . $parsed_styles['border']['width'] . " -->\n";
                    }
                } else {
                    echo "<!-- CBD DEBUG: Could not parse styles JSON for " . $block['slug'] . " -->\n";
                }
            }
        }
        
        ?>
        <style id="cbd-production-editor-styles">
        /* Container Block Designer - Production Editor Styles */
        
        /* TARGETED WARNING SYSTEM - ONLY FOR GENERIC CONTAINER BLOCKS */
        /* Target ONLY the base container class without specific style extensions */
        .wp-block-container-block-designer-container:not([class*="-infotext"]):not([class*="-basic"]):not([class*="-card"]) {
            background-color: #fff3cd !important;
            background-image: none !important;
            background: #fff3cd !important;
            border: 3px dashed #ffc107 !important;
            color: #856404 !important;
            padding: 30px !important;
            text-align: center !important;
            position: relative !important;
            min-height: 120px !important;
            box-shadow: none !important;
            transition: none !important;
        }
        
        /* Warning text - ONLY for base container without specific styles */
        .wp-block-container-block-designer-container:not([class*="-infotext"]):not([class*="-basic"]):not([class*="-card"])::before {
            content: '⚠️ Kein Container-Style ausgewählt!' !important;
            display: block !important;
            font-weight: bold !important;
            font-size: 16px !important;
            color: #856404 !important;
            position: absolute !important;
            top: 40% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 1000 !important;
            white-space: nowrap !important;
        }
        
        .wp-block-container-block-designer-container:not([class*="-infotext"]):not([class*="-basic"]):not([class*="-card"])::after {
            content: 'Wählen Sie bitte einen Container-Style im Dropdown-Menü rechts aus.' !important;
            display: block !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            color: #856404 !important;
            position: absolute !important;
            top: 60% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 1000 !important;
            max-width: 80% !important;
            text-align: center !important;
        }
        
        /* Base Container Block Styling - MINIMAL DEFAULT STYLES */
        /* Only apply minimal positioning and layout styles, NO colors or borders */
        .wp-block[data-type*="container-block-designer"] .cbd-container-block,
        [class*="wp-block-container-block-designer"] .cbd-container-block,
        .wp-block[data-type*="container-block-designer"],
        [class*="wp-block-container-block-designer"],
        div[class*="container-block-designer"],
        *[class*="container-block-designer"],
        [data-type*="container-block-designer"] {
            position: relative !important;
            min-height: 60px !important;
            box-sizing: border-box !important;
            /* DO NOT apply background, border, or other visual styles here */
            /* Those should come from the selected block style or warning system */
        }
        
        /* Hover effects */
        .wp-block[data-type*="container-block-designer"]:hover .cbd-container-block,
        [class*="wp-block-container-block-designer"]:hover .cbd-container-block,
        .wp-block[data-type*="container-block-designer"]:hover,
        [class*="wp-block-container-block-designer"]:hover,
        div[class*="container-block-designer"]:hover,
        *[class*="container-block-designer"]:hover,
        [data-type*="container-block-designer"]:hover,
        [class*="dfgdfgdfg"]:hover {
            border-color: #007cba !important;
            box-shadow: 0 4px 12px rgba(0,123,186,0.15) !important;
            transform: translateY(-2px) !important;
        }
        
        <?php
        // Load dynamic block styles directly
        if (!empty($blocks)) {
            echo "\n/* DYNAMIC BLOCK STYLES START */\n";
            foreach ($blocks as $block) {
                $styles = json_decode($block['styles'], true);
                $features = json_decode($block['features'], true);
                
                echo "/* Processing Block: " . $block['name'] . " */\n";
                
                if (!empty($styles) || !empty($features)) {
                    echo "\n/* Block: " . esc_attr($block['name']) . " (Slug: " . esc_attr($block['slug']) . ") */\n";
                    
                    // FIXED: Only use SPECIFIC selectors for each block, not broad selectors
                    // This was the root cause - broad selectors applied first block to all blocks!
                    $selectors = [];
                    
                    // Add specific block slug selectors with ULTRA HIGH specificity to override warning
                    if (!empty($block['slug'])) {
                        $selectors[] = 'html body.wp-admin .wp-block-container-block-designer-' . esc_attr($block['slug']);
                        $selectors[] = 'html body.wp-admin .wp-block[data-type*="container-block-designer"].wp-block-container-block-designer-' . esc_attr($block['slug']);
                        $selectors[] = 'html body.wp-admin .block-editor-page .wp-block[data-type*="container-block-designer"].wp-block-container-block-designer-' . esc_attr($block['slug']);
                        $selectors[] = 'body.wp-admin [data-type*="container-block-designer"][class*="' . esc_attr($block['slug']) . '"]';
                    }
                    
                    // Only proceed if we have specific selectors
                    if (empty($selectors)) {
                        continue; // Skip blocks without proper slug
                    }
                    
                    $selector_string = implode(', ', $selectors);
                    
                    echo $selector_string . ' {' . "\n";
                    
                    // Background styles
                    if (!empty($styles['background']['color'])) {
                        echo '    background-color: ' . esc_attr($styles['background']['color']) . ' !important;' . "\n";
                    }
                    if (!empty($styles['background']['gradient'])) {
                        // Handle gradient as string or array
                        if (is_array($styles['background']['gradient'])) {
                            $gradient_css = $this->convert_gradient_array_to_css($styles['background']['gradient']);
                            if (!empty($gradient_css)) {
                                echo '    background: ' . esc_attr($gradient_css) . ' !important;' . "\n";
                            }
                        } else {
                            echo '    background: ' . esc_attr($styles['background']['gradient']) . ' !important;' . "\n";
                        }
                    }
                    
                    // Text styles - OVERRIDE WARNING COLOR
                    if (!empty($styles['text']['color'])) {
                        echo '    color: ' . esc_attr($styles['text']['color']) . ' !important;' . "\n";
                    } else {
                        // Default text color to override warning color
                        echo '    color: #333333 !important;' . "\n";
                    }
                    
                    // Border styles
                    if (!empty($styles['border']['width']) && !empty($styles['border']['color'])) {
                        echo '    border: ' . intval($styles['border']['width']) . 'px ' . 
                             esc_attr($styles['border']['style'] ?? 'solid') . ' ' . 
                             esc_attr($styles['border']['color']) . ' !important;' . "\n";
                    }
                    if (!empty($styles['border']['radius'])) {
                        echo '    border-radius: ' . intval($styles['border']['radius']) . 'px !important;' . "\n";
                    }
                    
                    // Box shadow
                    if (!empty($styles['boxShadow']['enabled'])) {
                        $shadow = $styles['boxShadow'];
                        echo '    box-shadow: ' . 
                             intval($shadow['x'] ?? 0) . 'px ' . 
                             intval($shadow['y'] ?? 2) . 'px ' . 
                             intval($shadow['blur'] ?? 4) . 'px ' . 
                             intval($shadow['spread'] ?? 0) . 'px ' . 
                             esc_attr($shadow['color'] ?? 'rgba(0,0,0,0.1)') . ' !important;' . "\n";
                    }
                    
                    // Padding
                    if (!empty($styles['padding'])) {
                        $padding = $styles['padding'];
                        echo '    padding: ' . 
                             intval($padding['top'] ?? 20) . 'px ' . 
                             intval($padding['right'] ?? 20) . 'px ' . 
                             intval($padding['bottom'] ?? 20) . 'px ' . 
                             intval($padding['left'] ?? 20) . 'px !important;' . "\n";
                    }
                    
                    echo '}' . "\n\n";
                    
                    // No need to remove pseudo-elements since warning only targets generic blocks
                    
                    // Icon styles
                    if (!empty($features['icon']['enabled'])) {
                        $icon = $features['icon'];
                        $icon_selectors = [
                            '.wp-block[data-type*="container-block-designer"] .cbd-block-icon',
                            '[class*="wp-block-container-block-designer"] .cbd-block-icon',
                            '.wp-block-container-block-designer-' . esc_attr($block['slug']) . ' .cbd-block-icon',
                            '[class*="' . esc_attr($block['slug']) . '"] .cbd-block-icon'
                        ];
                        $icon_selector = implode(', ', $icon_selectors);
                        echo $icon_selector . ' {' . "\n";
                        if (!empty($icon['color'])) {
                            echo '    color: ' . esc_attr($icon['color']) . ' !important;' . "\n";
                        }
                        if (!empty($icon['size'])) {
                            echo '    font-size: ' . intval($icon['size']) . 'px !important;' . "\n";
                        }
                        echo '}' . "\n\n";
                    }
                    
                    // Numbering styles
                    if (!empty($features['numbering']['enabled'])) {
                        $numbering = $features['numbering'];
                        $number_selectors = [
                            '.wp-block[data-type*="container-block-designer"] .cbd-block-number',
                            '[class*="wp-block-container-block-designer"] .cbd-block-number',
                            '.wp-block-container-block-designer-' . esc_attr($block['slug']) . ' .cbd-block-number',
                            '[class*="' . esc_attr($block['slug']) . '"] .cbd-block-number'
                        ];
                        $number_selector = implode(', ', $number_selectors);
                        echo $number_selector . ' {' . "\n";
                        if (!empty($numbering['color'])) {
                            echo '    color: ' . esc_attr($numbering['color']) . ' !important;' . "\n";
                        }
                        if (!empty($numbering['backgroundColor'])) {
                            echo '    background-color: ' . esc_attr($numbering['backgroundColor']) . ' !important;' . "\n";
                        }
                        echo '}' . "\n\n";
                    }
                }
            }
        }
        ?>
        
        /* Base Universal Styles */
        
        /* Universal selectors for all container block variants */
        *[class*="container-block-designer"],
        *[data-type*="container-block-designer"],
        div[class*="dfgdfgdfg"],
        .wp-block[data-type*="dfgdfgdfg"],
        .wp-block-container-block-designer-dfgdfgdfg,
        [class*="dfgdfgdfg"] {
            position: relative !important;
            min-height: 80px !important;
            padding: 20px !important;
            margin: 15px 0 !important;
            border-radius: 8px !important;
            background: #ffffff !important;
            border: 1px solid #e0e0e0 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
            transition: all 0.2s ease !important;
        }
        
        /* Professional hover effect for better UX */
        .block-editor-page [class*="dfgdfgdfg"]:hover,
        .editor-styles-wrapper [class*="dfgdfgdfg"]:hover,
        .wp-block-container-block-designer-dfgdfgdfg:hover {
            border-color: #007cba !important;
            box-shadow: 0 4px 12px rgba(0,123,186,0.15) !important;
            transform: translateY(-2px) !important;
        }
        
        /* Inner content styling */
        [class*="dfgdfgdfg"] .cbd-block-content,
        [class*="container-block-designer"] .cbd-block-content,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-content {
            position: relative !important;
            z-index: 1 !important;
            min-height: 50px !important;
            line-height: 1.6 !important;
        }
        
        /* Icon positioning system - all 9 positions */
        [class*="dfgdfgdfg"] .cbd-block-icon,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon {
            position: absolute !important;
            z-index: 10 !important;
            font-family: dashicons !important;
            font-size: 20px !important;
            width: 20px !important;
            height: 20px !important;
            line-height: 1 !important;
            color: #666 !important;
            transition: color 0.2s ease !important;
        }
        
        /* Icon positions */
        [class*="dfgdfgdfg"] .cbd-block-icon.top-left,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.top-left { 
            top: 10px !important; left: 10px !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-icon.top-center,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.top-center { 
            top: 10px !important; left: 50% !important; transform: translateX(-50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-icon.top-right,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.top-right { 
            top: 10px !important; right: 10px !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-icon.middle-left,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.middle-left { 
            top: 50% !important; left: 10px !important; transform: translateY(-50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-icon.middle-center,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.middle-center { 
            top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-icon.middle-right,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.middle-right { 
            top: 50% !important; right: 10px !important; transform: translateY(-50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-icon.bottom-left,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.bottom-left { 
            bottom: 10px !important; left: 10px !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-icon.bottom-center,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.bottom-center { 
            bottom: 10px !important; left: 50% !important; transform: translateX(-50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-icon.bottom-right,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-icon.bottom-right { 
            bottom: 10px !important; right: 10px !important; 
        }
        
        /* Numbering positioning system - all 9 positions */
        [class*="dfgdfgdfg"] .cbd-block-number,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number {
            position: absolute !important;
            z-index: 10 !important;
            font-weight: bold !important;
            font-size: 14px !important;
            color: #333 !important;
            background: rgba(255,255,255,0.95) !important;
            padding: 4px 8px !important;
            border-radius: 12px !important;
            border: 1px solid #ddd !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15) !important;
            min-width: 24px !important;
            text-align: center !important;
        }
        
        /* Numbering positions */
        [class*="dfgdfgdfg"] .cbd-block-number.top-left,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.top-left { 
            top: 5px !important; left: 5px !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-number.top-center,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.top-center { 
            top: 5px !important; left: 50% !important; transform: translateX(-50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-number.top-right,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.top-right { 
            top: 5px !important; right: 5px !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-number.middle-left,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.middle-left { 
            top: 50% !important; left: 5px !important; transform: translateY(-50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-number.middle-center,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.middle-center { 
            top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-number.middle-right,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.middle-right { 
            top: 50% !important; right: 5px !important; transform: translateY(-50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-number.bottom-left,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.bottom-left { 
            bottom: 5px !important; left: 5px !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-number.bottom-center,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.bottom-center { 
            bottom: 5px !important; left: 50% !important; transform: translateX(-50%) !important; 
        }
        [class*="dfgdfgdfg"] .cbd-block-number.bottom-right,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-block-number.bottom-right { 
            bottom: 5px !important; right: 5px !important; 
        }
        
        /* Feature buttons preview */
        [class*="dfgdfgdfg"] .cbd-feature-buttons,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-feature-buttons {
            margin-top: 20px !important;
            padding: 12px 0 !important;
            border-top: 1px solid #e0e0e0 !important;
            display: flex !important;
            gap: 8px !important;
            align-items: center !important;
            flex-wrap: wrap !important;
        }
        
        [class*="dfgdfgdfg"] .cbd-feature-button,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-feature-button {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 8px 12px !important;
            font-size: 12px !important;
            border: 1px solid #ddd !important;
            border-radius: 4px !important;
            background: linear-gradient(to bottom, #fafafa, #f0f0f0) !important;
            color: #666 !important;
            pointer-events: none !important;
            opacity: 0.8 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
        }
        
        [class*="dfgdfgdfg"] .cbd-feature-button .dashicons,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-feature-button .dashicons {
            font-size: 14px !important;
            width: 14px !important;
            height: 14px !important;
        }
        
        /* Collapse toggle styling */
        [class*="dfgdfgdfg"] .cbd-collapse-toggle,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-collapse-toggle {
            cursor: not-allowed !important;
            background: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            padding: 4px 8px !important;
            border-radius: 3px !important;
            font-size: 11px !important;
            color: #6c757d !important;
        }
        
        /* Error state styling */
        [class*="dfgdfgdfg"] .cbd-container-error,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-container-error {
            background: #fff5f5 !important;
            border: 2px dashed #e53e3e !important;
            padding: 20px !important;
            border-radius: 8px !important;
            margin: 10px 0 !important;
            color: #c53030 !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
        }
        
        [class*="dfgdfgdfg"] .cbd-container-error strong,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-container-error strong {
            color: #c53030 !important;
            font-weight: 600 !important;
            display: block !important;
            margin-bottom: 8px !important;
        }
        
        [class*="dfgdfgdfg"] .cbd-container-error a,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-container-error a {
            color: #2b6cb0 !important;
            text-decoration: underline !important;
            font-weight: 500 !important;
        }
        
        [class*="dfgdfgdfg"] .cbd-container-error a:hover,
        .wp-block-container-block-designer-dfgdfgdfg .cbd-container-error a:hover {
            color: #2c5aa0 !important;
        }
        
        /* Selected block styling in editor */
        .wp-block.is-selected[class*="dfgdfgdfg"],
        .wp-block.is-selected.wp-block-container-block-designer-dfgdfgdfg {
            outline: 2px solid #007cba !important;
            outline-offset: -2px !important;
        }
        
        /* Modern effects preview in editor */
        [class*="dfgdfgdfg"].cbd-glassmorphism .cbd-container-block,
        .wp-block-container-block-designer-dfgdfgdfg.cbd-glassmorphism .cbd-container-block {
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
            background: rgba(255,255,255,0.8) !important;
        }
        
        [class*="dfgdfgdfg"].cbd-neumorphism .cbd-container-block,
        .wp-block-container-block-designer-dfgdfgdfg.cbd-neumorphism .cbd-container-block {
            box-shadow: 6px 6px 12px rgba(0,0,0,0.15), -6px -6px 12px rgba(255,255,255,0.8) !important;
        }
        
        [class*="dfgdfgdfg"].cbd-animated:hover .cbd-container-block,
        .wp-block-container-block-designer-dfgdfgdfg.cbd-animated:hover .cbd-container-block {
            transform: translateY(-3px) !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
        }
        
        </style>
        
        <script>
        // Add real-time style refresh capability
        (function() {
            // Function to refresh dynamic styles
            window.cbdRefreshDynamicStyles = function() {
                // Remove old dynamic styles
                const oldStyles = document.getElementById('cbd-production-editor-styles');
                if (oldStyles) {
                    // Create new style element with updated styles
                    fetch(window.location.href, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(response => response.text()).then(html => {
                        // Extract new styles from response
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newStyles = doc.getElementById('cbd-production-editor-styles');
                        
                        if (newStyles) {
                            // Replace old styles with new ones
                            oldStyles.innerHTML = newStyles.innerHTML;
                        }
                    }).catch(console.error);
                }
            };
            
            // Auto-refresh styles when container blocks change
            if (window.wp && window.wp.data) {
                const { subscribe, select } = window.wp.data;
                let lastBlockCount = 0;
                
                subscribe(() => {
                    const blocks = select('core/block-editor').getBlocks();
                    const containerBlocks = blocks.filter(block => 
                        block.name && block.name.includes('container-block-designer')
                    );
                    
                    if (containerBlocks.length !== lastBlockCount) {
                        lastBlockCount = containerBlocks.length;
                        setTimeout(window.cbdRefreshDynamicStyles, 500);
                    }
                });
            }
            
            console.log('CBD: Dynamic style refresh system loaded');
        })();
        </script>
        
        <?php
    }
    
    /**
     * Alle Blocks aus der Datenbank holen
     */
    private function get_all_blocks() {
        global $wpdb;
        
        $cache_key = 'cbd_all_blocks_styles';
        $blocks = wp_cache_get($cache_key);
        
        if (false === $blocks) {
            $blocks = $wpdb->get_results(
                "SELECT id, name, slug, styles, config, features 
                 FROM " . CBD_TABLE_BLOCKS . " 
                 WHERE status = 'active'",
                ARRAY_A
            );
            
            // Cache für 1 Stunde
            wp_cache_set($cache_key, $blocks, '', HOUR_IN_SECONDS);
        }
        
        return $blocks;
    }
    
    /**
     * Verwendete Blocks auf der aktuellen Seite ermitteln
     */
    private function get_used_blocks_on_page() {
        global $post;
        
        if (!$post || !$post->post_content) {
            return array();
        }
        
        // Parse Blocks aus Post-Content
        $parsed_blocks = parse_blocks($post->post_content);
        $used_block_slugs = $this->extract_container_block_slugs($parsed_blocks);
        
        if (empty($used_block_slugs)) {
            return array();
        }
        
        // Nur die verwendeten Blocks aus DB holen
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($used_block_slugs), '%s'));
        
        $blocks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, slug, styles, config, features 
                 FROM " . CBD_TABLE_BLOCKS . " 
                 WHERE status = 'active' 
                 AND slug IN ($placeholders)",
                $used_block_slugs
            ),
            ARRAY_A
        );
        
        return $blocks;
    }
    
    /**
     * Container Block Slugs aus geparsten Blocks extrahieren
     */
    private function extract_container_block_slugs($blocks, &$slugs = array()) {
        foreach ($blocks as $block) {
            // Container Block gefunden
            if ($block['blockName'] === 'container-block-designer/container') {
                if (!empty($block['attrs']['selectedBlock'])) {
                    $slugs[] = $block['attrs']['selectedBlock'];
                }
            }
            
            // Rekursiv in innerBlocks suchen
            if (!empty($block['innerBlocks'])) {
                $this->extract_container_block_slugs($block['innerBlocks'], $slugs);
            }
        }
        
        return array_unique($slugs);
    }
    
    /**
     * CSS für alle Blocks generieren
     */
    private function generate_all_block_styles($blocks, $is_editor = false) {
        $css = '';
        
        foreach ($blocks as $block) {
            $css .= $this->generate_single_block_css($block, $is_editor);
        }
        
        // Allgemeine Container-Styles hinzufügen
        $css .= $this->get_base_container_css($is_editor);
        
        return $css;
    }
    
    /**
     * CSS für verwendete Blocks auf der Seite generieren
     */
    private function generate_block_styles_for_page($blocks) {
        $css = '';
        
        // Basis-Container-Styles
        $css .= $this->get_base_container_css(false);
        
        // Spezifische Block-Styles
        foreach ($blocks as $block) {
            $css .= $this->generate_single_block_css($block, false);
        }
        
        // Feature-Styles
        $css .= $this->generate_feature_styles($blocks);
        
        return $css;
    }
    
    /**
     * CSS für einen einzelnen Block generieren
     */
    private function generate_single_block_css($block, $is_editor = false) {
        $styles = !empty($block['styles']) ? json_decode($block['styles'], true) : array();
        $config = !empty($block['config']) ? json_decode($block['config'], true) : array();
        $features = !empty($block['features']) ? json_decode($block['features'], true) : array();
        
        $selector = $is_editor 
            ? '.editor-styles-wrapper .cbd-container-' . $block['slug']
            : '.cbd-container-' . $block['slug'];
        
        $css = "\n/* Block: {$block['name']} */\n";
        
        // Haupt-Container-Styles
        $container_styles = $this->build_container_styles($styles, $config);
        if (!empty($container_styles)) {
            $css .= "{$selector} {\n{$container_styles}}\n";
        }
        
        // Responsive Styles
        $css .= $this->generate_responsive_styles($selector, $styles, $config);
        
        // Feature-spezifische Styles
        $css .= $this->generate_block_feature_styles($selector, $features);
        
        // Hover-Styles
        if (!empty($styles['hover'])) {
            $hover_styles = $this->build_hover_styles($styles['hover']);
            if (!empty($hover_styles)) {
                $css .= "{$selector}:hover {\n{$hover_styles}}\n";
            }
        }
        
        return $css;
    }
    
    /**
     * Container-Styles aufbauen
     */
    private function build_container_styles($styles, $config) {
        $css_properties = array();
        
        // Padding
        if (!empty($styles['padding'])) {
            $padding = $styles['padding'];
            $css_properties[] = sprintf(
                '  padding: %spx %spx %spx %spx;',
                $padding['top'] ?? 20,
                $padding['right'] ?? 20,
                $padding['bottom'] ?? 20,
                $padding['left'] ?? 20
            );
        }
        
        // Margin
        if (!empty($styles['margin'])) {
            $margin = $styles['margin'];
            $css_properties[] = sprintf(
                '  margin: %spx %spx %spx %spx;',
                $margin['top'] ?? 0,
                $margin['right'] ?? 'auto',
                $margin['bottom'] ?? 20,
                $margin['left'] ?? 'auto'
            );
        }
        
        // Background
        if (!empty($styles['background'])) {
            $bg = $styles['background'];
            
            if (!empty($bg['color'])) {
                $css_properties[] = '  background-color: ' . $bg['color'] . ';';
            }
            
            if (!empty($bg['image'])) {
                $css_properties[] = '  background-image: url(' . $bg['image'] . ');';
                $css_properties[] = '  background-size: ' . ($bg['size'] ?? 'cover') . ';';
                $css_properties[] = '  background-position: ' . ($bg['position'] ?? 'center') . ';';
                $css_properties[] = '  background-repeat: ' . ($bg['repeat'] ?? 'no-repeat') . ';';
            }
            
            if (!empty($bg['gradient'])) {
                // Handle gradient as string or array
                if (is_array($bg['gradient'])) {
                    // If gradient is stored as array, convert to CSS string
                    $gradient_css = $this->convert_gradient_array_to_css($bg['gradient']);
                    if (!empty($gradient_css)) {
                        $css_properties[] = '  background: ' . $gradient_css . ';';
                    }
                } else {
                    // If gradient is already a string
                    $css_properties[] = '  background: ' . $bg['gradient'] . ';';
                }
            }
        }
        
        // Border
        if (!empty($styles['border'])) {
            $border = $styles['border'];
            
            if (!empty($border['width']) && !empty($border['color'])) {
                $css_properties[] = sprintf(
                    '  border: %spx %s %s;',
                    $border['width'],
                    $border['style'] ?? 'solid',
                    $border['color']
                );
            }
            
            if (!empty($border['radius'])) {
                $css_properties[] = '  border-radius: ' . $border['radius'] . 'px;';
            }
        }
        
        // Typography
        if (!empty($styles['typography'])) {
            $typo = $styles['typography'];
            
            if (!empty($typo['color'])) {
                $css_properties[] = '  color: ' . $typo['color'] . ';';
            }
            
            if (!empty($typo['fontSize'])) {
                $css_properties[] = '  font-size: ' . $typo['fontSize'] . ';';
            }
            
            if (!empty($typo['fontFamily'])) {
                $css_properties[] = '  font-family: ' . $typo['fontFamily'] . ';';
            }
            
            if (!empty($typo['lineHeight'])) {
                $css_properties[] = '  line-height: ' . $typo['lineHeight'] . ';';
            }
            
            if (!empty($typo['textAlign'])) {
                $css_properties[] = '  text-align: ' . $typo['textAlign'] . ';';
            }
        }
        
        // Box Shadow
        if (!empty($styles['boxShadow'])) {
            $shadow = $styles['boxShadow'];
            if (!empty($shadow['enabled'])) {
                $css_properties[] = sprintf(
                    '  box-shadow: %spx %spx %spx %spx %s;',
                    $shadow['x'] ?? 0,
                    $shadow['y'] ?? 2,
                    $shadow['blur'] ?? 4,
                    $shadow['spread'] ?? 0,
                    $shadow['color'] ?? 'rgba(0, 0, 0, 0.1)'
                );
            }
        }
        
        // Max Width
        if (!empty($config['maxWidth'])) {
            $css_properties[] = '  max-width: ' . $config['maxWidth'] . ';';
            $css_properties[] = '  margin-left: auto;';
            $css_properties[] = '  margin-right: auto;';
        }
        
        // Min Height
        if (!empty($config['minHeight'])) {
            $css_properties[] = '  min-height: ' . $config['minHeight'] . ';';
        }
        
        // Display
        if (!empty($config['display'])) {
            $css_properties[] = '  display: ' . $config['display'] . ';';
            
            // Flexbox-Properties
            if ($config['display'] === 'flex') {
                if (!empty($config['flexDirection'])) {
                    $css_properties[] = '  flex-direction: ' . $config['flexDirection'] . ';';
                }
                if (!empty($config['justifyContent'])) {
                    $css_properties[] = '  justify-content: ' . $config['justifyContent'] . ';';
                }
                if (!empty($config['alignItems'])) {
                    $css_properties[] = '  align-items: ' . $config['alignItems'] . ';';
                }
                if (!empty($config['gap'])) {
                    $css_properties[] = '  gap: ' . $config['gap'] . 'px;';
                }
            }
        }
        
        // Position
        if (!empty($config['position']) && $config['position'] !== 'static') {
            $css_properties[] = '  position: ' . $config['position'] . ';';
        }
        
        // Z-Index
        if (!empty($config['zIndex'])) {
            $css_properties[] = '  z-index: ' . $config['zIndex'] . ';';
        }
        
        // Overflow
        if (!empty($config['overflow'])) {
            $css_properties[] = '  overflow: ' . $config['overflow'] . ';';
        }
        
        // Transition für sanfte Hover-Effekte
        $css_properties[] = '  transition: all 0.3s ease;';
        
        return implode("\n", $css_properties);
    }
    
    /**
     * Hover-Styles aufbauen
     */
    private function build_hover_styles($hover_styles) {
        $css_properties = array();
        
        if (!empty($hover_styles['backgroundColor'])) {
            $css_properties[] = '  background-color: ' . $hover_styles['backgroundColor'] . ';';
        }
        
        if (!empty($hover_styles['borderColor'])) {
            $css_properties[] = '  border-color: ' . $hover_styles['borderColor'] . ';';
        }
        
        if (!empty($hover_styles['textColor'])) {
            $css_properties[] = '  color: ' . $hover_styles['textColor'] . ';';
        }
        
        if (!empty($hover_styles['transform'])) {
            $css_properties[] = '  transform: ' . $hover_styles['transform'] . ';';
        }
        
        if (!empty($hover_styles['boxShadow'])) {
            $shadow = $hover_styles['boxShadow'];
            $css_properties[] = sprintf(
                '  box-shadow: %spx %spx %spx %spx %s;',
                $shadow['x'] ?? 0,
                $shadow['y'] ?? 4,
                $shadow['blur'] ?? 8,
                $shadow['spread'] ?? 0,
                $shadow['color'] ?? 'rgba(0, 0, 0, 0.2)'
            );
        }
        
        return implode("\n", $css_properties);
    }
    
    /**
     * Responsive Styles generieren
     */
    private function generate_responsive_styles($selector, $styles, $config) {
        $css = '';
        
        // Tablet Styles (768px - 1024px)
        if (!empty($styles['tablet']) || !empty($config['tablet'])) {
            $css .= "\n@media (max-width: 1024px) and (min-width: 768px) {\n";
            $css .= "  {$selector} {\n";
            
            if (!empty($styles['tablet']['padding'])) {
                $padding = $styles['tablet']['padding'];
                $css .= sprintf(
                    '    padding: %spx %spx %spx %spx;',
                    $padding['top'] ?? 15,
                    $padding['right'] ?? 15,
                    $padding['bottom'] ?? 15,
                    $padding['left'] ?? 15
                ) . "\n";
            }
            
            if (!empty($styles['tablet']['fontSize'])) {
                $css .= '    font-size: ' . $styles['tablet']['fontSize'] . ";\n";
            }
            
            if (!empty($config['tablet']['display'])) {
                $css .= '    display: ' . $config['tablet']['display'] . ";\n";
            }
            
            $css .= "  }\n}\n";
        }
        
        // Mobile Styles (< 768px)
        if (!empty($styles['mobile']) || !empty($config['mobile'])) {
            $css .= "\n@media (max-width: 767px) {\n";
            $css .= "  {$selector} {\n";
            
            if (!empty($styles['mobile']['padding'])) {
                $padding = $styles['mobile']['padding'];
                $css .= sprintf(
                    '    padding: %spx %spx %spx %spx;',
                    $padding['top'] ?? 10,
                    $padding['right'] ?? 10,
                    $padding['bottom'] ?? 10,
                    $padding['left'] ?? 10
                ) . "\n";
            }
            
            if (!empty($styles['mobile']['fontSize'])) {
                $css .= '    font-size: ' . $styles['mobile']['fontSize'] . ";\n";
            }
            
            if (!empty($config['mobile']['display'])) {
                $css .= '    display: ' . $config['mobile']['display'] . ";\n";
            }
            
            if (!empty($config['mobile']['flexDirection'])) {
                $css .= '    flex-direction: ' . $config['mobile']['flexDirection'] . ";\n";
            }
            
            $css .= "  }\n}\n";
        }
        
        return $css;
    }
    
    /**
     * Feature-spezifische Styles für einen Block generieren
     */
    private function generate_block_feature_styles($selector, $features) {
        $css = '';
        
        // Icon Styles
        if (!empty($features['icon']['enabled'])) {
            $icon = $features['icon'];
            
            $css .= "{$selector} .cbd-icon {\n";
            
            if (!empty($icon['color'])) {
                $css .= '  color: ' . $icon['color'] . ";\n";
            }
            
            if (!empty($icon['size'])) {
                $css .= '  font-size: ' . $icon['size'] . "px;\n";
            }
            
            if (!empty($icon['position'])) {
                $pos = $icon['position'];
                
                if ($pos === 'top-left') {
                    $css .= "  position: absolute;\n  top: 10px;\n  left: 10px;\n";
                } elseif ($pos === 'top-right') {
                    $css .= "  position: absolute;\n  top: 10px;\n  right: 10px;\n";
                } elseif ($pos === 'bottom-left') {
                    $css .= "  position: absolute;\n  bottom: 10px;\n  left: 10px;\n";
                } elseif ($pos === 'bottom-right') {
                    $css .= "  position: absolute;\n  bottom: 10px;\n  right: 10px;\n";
                }
            }
            
            $css .= "}\n";
        }
        
        // Numbering Styles
        if (!empty($features['numbering']['enabled'])) {
            $numbering = $features['numbering'];
            
            $css .= "{$selector} .cbd-number {\n";
            
            if (!empty($numbering['color'])) {
                $css .= '  color: ' . $numbering['color'] . ";\n";
            }
            
            if (!empty($numbering['backgroundColor'])) {
                $css .= '  background-color: ' . $numbering['backgroundColor'] . ";\n";
            }
            
            if (!empty($numbering['size'])) {
                $css .= '  width: ' . $numbering['size'] . "px;\n";
                $css .= '  height: ' . $numbering['size'] . "px;\n";
                $css .= '  line-height: ' . $numbering['size'] . "px;\n";
            }
            
            if (!empty($numbering['position'])) {
                $pos = $numbering['position'];
                
                if ($pos === 'top-left') {
                    $css .= "  position: absolute;\n  top: -15px;\n  left: -15px;\n";
                } elseif ($pos === 'top-right') {
                    $css .= "  position: absolute;\n  top: -15px;\n  right: -15px;\n";
                }
            }
            
            $css .= "  border-radius: 50%;\n";
            $css .= "  text-align: center;\n";
            $css .= "  font-weight: bold;\n";
            $css .= "}\n";
        }
        
        // Collapsible Styles
        if (!empty($features['collapsible']['enabled'])) {
            $css .= "{$selector}.cbd-collapsed .cbd-container-content {\n";
            $css .= "  display: none;\n";
            $css .= "}\n";
            
            $css .= "{$selector} .cbd-collapse-toggle {\n";
            $css .= "  cursor: pointer;\n";
            $css .= "  user-select: none;\n";
            $css .= "  transition: transform 0.3s ease;\n";
            $css .= "}\n";
            
            $css .= "{$selector}.cbd-collapsed .cbd-collapse-toggle {\n";
            $css .= "  transform: rotate(-90deg);\n";
            $css .= "}\n";
        }
        
        // Copy Button Styles
        if (!empty($features['copy']['enabled'])) {
            $css .= "{$selector} .cbd-copy-button {\n";
            $css .= "  position: absolute;\n";
            $css .= "  top: 10px;\n";
            $css .= "  right: 10px;\n";
            $css .= "  padding: 5px 10px;\n";
            $css .= "  background: rgba(255, 255, 255, 0.9);\n";
            $css .= "  border: 1px solid #ddd;\n";
            $css .= "  border-radius: 3px;\n";
            $css .= "  cursor: pointer;\n";
            $css .= "  font-size: 12px;\n";
            $css .= "  opacity: 0;\n";
            $css .= "  transition: opacity 0.3s ease;\n";
            $css .= "}\n";
            
            $css .= "{$selector}:hover .cbd-copy-button {\n";
            $css .= "  opacity: 1;\n";
            $css .= "}\n";
        }
        
        return $css;
    }
    
    /**
     * Allgemeine Feature-Styles generieren
     */
    private function generate_feature_styles($blocks) {
        $css = '';
        $has_features = array();
        
        // Sammle alle verwendeten Features
        foreach ($blocks as $block) {
            $features = json_decode($block['features'], true) ?: array();
            foreach ($features as $feature_key => $feature_data) {
                if (!empty($feature_data['enabled'])) {
                    $has_features[$feature_key] = true;
                }
            }
        }
        
        // Generiere Feature-übergreifende Styles
        if (!empty($has_features['icon'])) {
            $css .= "\n/* Icon Feature Styles */\n";
            $css .= ".cbd-icon { display: inline-flex; align-items: center; justify-content: center; }\n";
        }
        
        if (!empty($has_features['numbering'])) {
            $css .= "\n/* Numbering Feature Styles */\n";
            $css .= ".cbd-number { display: flex; align-items: center; justify-content: center; }\n";
        }
        
        if (!empty($has_features['collapsible'])) {
            $css .= "\n/* Collapsible Feature Styles */\n";
            $css .= ".cbd-container.cbd-collapsible { position: relative; }\n";
            $css .= ".cbd-collapse-toggle { position: absolute; }\n";
        }
        
        return $css;
    }
    
    /**
     * Basis-Container-CSS
     */
    private function get_base_container_css($is_editor = false) {
        $prefix = $is_editor ? '.editor-styles-wrapper ' : '';
        
        $css = "\n/* Base Container Styles */\n";
        $css .= "{$prefix}.wp-block-container-block-designer-container {\n";
        $css .= "  position: relative;\n";
        $css .= "  box-sizing: border-box;\n";
        $css .= "}\n";
        
        $css .= "{$prefix}.cbd-container {\n";
        $css .= "  position: relative;\n";
        $css .= "  box-sizing: border-box;\n";
        $css .= "  min-height: 50px;\n";
        $css .= "}\n";
        
        $css .= "{$prefix}.cbd-container * {\n";
        $css .= "  box-sizing: border-box;\n";
        $css .= "}\n";
        
        $css .= "{$prefix}.cbd-container-content {\n";
        $css .= "  position: relative;\n";
        $css .= "  z-index: 1;\n";
        $css .= "}\n";
        
        // Editor-spezifische Styles
        if ($is_editor) {
            $css .= "\n/* Editor Specific */\n";
            $css .= "{$prefix}.wp-block-container-block-designer-container.is-selected {\n";
            $css .= "  outline: 2px solid #007cba;\n";
            $css .= "  outline-offset: -2px;\n";
            $css .= "}\n";
        }
        
        return $css;
    }
    
    /**
     * Aktive Features ermitteln
     */
    private function get_active_features() {
        global $wpdb;
        
        $cache_key = 'cbd_active_features';
        $features = wp_cache_get($cache_key);
        
        if (false === $features) {
            $blocks = $wpdb->get_results(
                "SELECT features FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'",
                ARRAY_A
            );
            
            $features = array();
            foreach ($blocks as $block) {
                $block_features = json_decode($block['features'], true) ?: array();
                foreach ($block_features as $feature_key => $feature_data) {
                    if (!empty($feature_data['enabled'])) {
                        $features[] = $feature_key;
                    }
                }
            }
            
            $features = array_unique($features);
            wp_cache_set($cache_key, $features, '', HOUR_IN_SECONDS);
        }
        
        return $features;
    }
    
    /**
     * CSS minifizieren
     */
    private function minify_css($css) {
        if ($this->debug_mode) {
            return $css; // Im Debug-Modus nicht minifizieren
        }
        
        // Kommentare entfernen
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Unnötige Leerzeichen entfernen
        $css = str_replace(array("\r\n", "\r", "\n", "\t"), '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Leerzeichen um bestimmte Zeichen entfernen
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        
        // Führende Nullen bei Dezimalzahlen entfernen
        $css = preg_replace('/(:| )0\./', '$1.', $css);
        
        // Abschließendes Semikolon vor } entfernen
        $css = str_replace(';}', '}', $css);
        
        return trim($css);
    }
    
    /**
     * AJAX: Styles aktualisieren
     */
    public function ajax_refresh_styles() {
        check_ajax_referer('cbd-admin-nonce', 'nonce');

        // Nur Style-Verwaltung ist auf Administratoren beschränkt
        if (!$this->can_manage_styles()) {
            wp_die(__('Keine Berechtigung für Style-Verwaltung', 'container-block-designer'));
        }
        
        // Cache leeren
        $this->clear_styles_cache();
        
        // Neue Styles generieren
        $blocks = $this->get_all_blocks();
        $css = $this->generate_all_block_styles($blocks);
        
        // CSS-Datei aktualisieren (optional für bessere Performance)
        $this->update_compiled_css_file($css);
        
        wp_send_json_success(array(
            'message' => __('Styles erfolgreich aktualisiert', 'container-block-designer'),
            'css' => $css
        ));
    }
    
    /**
     * Kompilierte CSS-Datei aktualisieren
     */
    private function update_compiled_css_file($css) {
        $upload_dir = wp_upload_dir();
        $cbd_dir = $upload_dir['basedir'] . '/container-block-designer';
        
        // Verzeichnis erstellen falls nicht vorhanden
        if (!file_exists($cbd_dir)) {
            wp_mkdir_p($cbd_dir);
        }
        
        // CSS-Datei schreiben
        $file_path = $cbd_dir . '/compiled-styles.css';
        file_put_contents($file_path, $this->minify_css($css));
        
        // Zeitstempel für Cache-Busting aktualisieren
        update_option('cbd_styles_version', time());
    }
    
    /**
     * Style-Cache leeren
     */
    public function clear_styles_cache() {
        wp_cache_delete('cbd_all_blocks_styles');
        wp_cache_delete('cbd_active_features');
        delete_transient('cbd_compiled_styles');
        
        // Zeitstempel aktualisieren für Cache-Busting
        update_option('cbd_styles_version', time());
    }
    
    /**
     * Convert gradient array to CSS string
     */
    private function convert_gradient_array_to_css($gradient) {
        if (!is_array($gradient)) {
            return '';
        }

        // Handle common gradient array structures
        if (isset($gradient['type']) && isset($gradient['value'])) {
            // WordPress block editor gradient format
            return $gradient['value'];
        } elseif (isset($gradient['gradient'])) {
            // Nested gradient format
            return $gradient['gradient'];
        } elseif (isset($gradient['css'])) {
            // CSS property format
            return $gradient['css'];
        } elseif (isset($gradient['colors']) && is_array($gradient['colors'])) {
            // Build linear gradient from colors array
            $type = $gradient['type'] ?? 'linear';
            $direction = $gradient['direction'] ?? 'to bottom';
            $colors = [];

            foreach ($gradient['colors'] as $color) {
                if (is_array($color) && isset($color['color'])) {
                    $position = isset($color['position']) ? ' ' . $color['position'] : '';
                    $colors[] = $color['color'] . $position;
                } elseif (is_string($color)) {
                    $colors[] = $color;
                }
            }

            if (!empty($colors)) {
                return $type . '-gradient(' . $direction . ', ' . implode(', ', $colors) . ')';
            }
        }

        // If it's just a simple array, try to implode it
        if (count($gradient) === 1 && is_string(reset($gradient))) {
            return reset($gradient);
        }

        // Fallback: log the structure for debugging
        $this->debug_log('Unknown gradient array structure: ' . print_r($gradient, true));
        return '';
    }

    /**
     * Prüfe Berechtigungen für Container-Block Nutzung
     */
    private function can_use_container_blocks() {
        // Mitarbeiter und höher können Container-Blocks verwenden
        return current_user_can('edit_posts');
    }

    /**
     * Prüfe Berechtigungen für Style-Verwaltung
     */
    private function can_manage_styles() {
        // Nur Administratoren können Styles verwalten
        return current_user_can('manage_options');
    }

    /**
     * Debug-Ausgabe
     */
    private function debug_log($message) {
        if ($this->debug_mode) {
            error_log('[CBD Style Loader] ' . print_r($message, true));
        }
    }
}

// Style Loader initialisieren
CBD_Style_Loader::get_instance();