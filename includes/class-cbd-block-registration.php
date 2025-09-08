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
        add_action('init', array($this, 'register_blocks'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
    }
    
    /**
     * Blöcke registrieren
     */
    public function register_blocks() {
        // Überprüfe ob Block bereits registriert ist
        if (WP_Block_Type_Registry::get_instance()->is_registered('container-block-designer/container')) {
            error_log('[CBD Block Registration] Block already registered, skipping');
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
        // Gemeinsame Styles für Frontend und Editor
        wp_enqueue_style(
            'cbd-block-common',
            CBD_PLUGIN_URL . 'assets/css/block-common.css',
            array(),
            CBD_VERSION
        );
    }
    
    /**
     * Block rendern
     */
    public function render_block($attributes, $content) {
        $selected_block = isset($attributes['selectedBlock']) ? $attributes['selectedBlock'] : '';
        $custom_classes = isset($attributes['customClasses']) ? $attributes['customClasses'] : '';
        $align = isset($attributes['align']) ? $attributes['align'] : '';
        $anchor = isset($attributes['anchor']) ? $attributes['anchor'] : '';
        
        if (empty($selected_block)) {
            return '<!-- Container Block: No block selected -->';
        }
        
        // Block-Daten aus der Datenbank holen
        // Der selected_block ist bereits sanitized, also müssen wir nach dem Original-Namen suchen
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
                if ($this->sanitize_block_name($test_block->name) === $selected_block) {
                    $block = $test_block;
                    break;
                }
            }
        }
        
        if (!$block) {
            return '<!-- Container Block: Block not found -->';
        }
        
        // Block-HTML generieren
        $block_classes = array('cbd-container-block', 'cbd-block-' . $selected_block);
        
        if (!empty($custom_classes)) {
            $block_classes[] = $custom_classes;
        }
        
        if (!empty($align)) {
            $block_classes[] = 'align' . $align;
        }
        
        $block_attributes = array(
            'class' => implode(' ', $block_classes)
        );
        
        if (!empty($anchor)) {
            $block_attributes['id'] = $anchor;
        }
        
        // Styles anwenden
        $styles = !empty($block->styles) ? json_decode($block->styles, true) : array();
        $inline_styles = $this->generate_inline_styles($styles);
        
        if (!empty($inline_styles)) {
            $block_attributes['style'] = $inline_styles;
        }
        
        // HTML ausgeben
        $html = '<div';
        foreach ($block_attributes as $key => $value) {
            $html .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        $html .= $content;
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Inline-Styles generieren
     */
    private function generate_inline_styles($styles) {
        $css = '';
        
        if (!empty($styles['backgroundColor'])) {
            $css .= 'background-color: ' . $styles['backgroundColor'] . ';';
        }
        
        if (!empty($styles['textColor'])) {
            $css .= 'color: ' . $styles['textColor'] . ';';
        }
        
        if (!empty($styles['padding'])) {
            $css .= 'padding: ' . $styles['padding'] . ';';
        }
        
        if (!empty($styles['margin'])) {
            $css .= 'margin: ' . $styles['margin'] . ';';
        }
        
        if (!empty($styles['borderRadius'])) {
            $css .= 'border-radius: ' . $styles['borderRadius'] . ';';
        }
        
        if (!empty($styles['border'])) {
            $css .= 'border: ' . $styles['border'] . ';';
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