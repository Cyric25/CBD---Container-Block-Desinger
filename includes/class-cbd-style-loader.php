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
        // Editor-spezifische Basis-Styles
        wp_enqueue_style(
            'cbd-editor-base',
            CBD_PLUGIN_URL . 'assets/css/editor-base.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
        
        // Alle aktiven Block-Styles für Vorschau
        $this->enqueue_block_preview_styles();
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
    }
    
    /**
     * Block-Vorschau-Styles im Editor laden
     */
    private function enqueue_block_preview_styles() {
        $blocks = $this->get_all_blocks();
        
        if (empty($blocks)) {
            return;
        }
        
        // Inline-Styles für alle Blocks generieren
        $preview_css = $this->generate_all_block_styles($blocks, true);
        
        if (!empty($preview_css)) {
            wp_add_inline_style('cbd-editor-base', $preview_css);
        }
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
        
        $blocks = $this->get_all_blocks();
        
        if (empty($blocks)) {
            return;
        }
        
        $css = $this->generate_all_block_styles($blocks, true);
        
        if (!empty($css)) {
            echo "\n<!-- Container Block Designer Editor Dynamic Styles -->\n";
            echo '<style id="cbd-editor-dynamic-styles">' . "\n";
            echo $this->minify_css($css);
            echo "\n</style>\n";
        }
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
                $css_properties[] = '  background: ' . $bg['gradient'] . ';';
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
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'container-block-designer'));
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