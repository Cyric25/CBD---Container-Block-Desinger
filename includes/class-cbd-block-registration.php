<?php
/**
 * Container Block Designer - Block-Registrierung
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasse für die Block-Registrierung
 */
class CBD_Block_Registration {
    
    /**
     * Singleton-Instanz
     */
    private static $instance = null;
    
    /**
     * Debug-Modus
     */
    private $debug_mode = false;
    
    /**
     * Singleton-Instanz abrufen
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
    }
    
    /**
     * Alle Blöcke registrieren
     */
    public function register_blocks() {
        // Prüfen ob Block-Editor verfügbar ist
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Haupt-Container-Block registrieren
        register_block_type('container-block-designer/container', array(
            'render_callback' => array($this, 'render_container_block'),
            'attributes' => $this->get_block_attributes(),
            'editor_script' => 'cbd-block-editor',
            'editor_style' => 'cbd-block-editor',
            'style' => 'cbd-block-style',
            'supports' => array(
                'html' => false,
                'align' => array('wide', 'full'),
                'anchor' => true,
                'customClassName' => true,
            ),
        ));
        
        // Debug-Log
        if ($this->debug_mode) {
            error_log('CBD: Block registered - container-block-designer/container');
        }
        
        // Dynamische Blöcke aus der Datenbank registrieren
        $this->register_dynamic_blocks();
    }
    
    /**
     * Block-Attribute definieren
     */
    private function get_block_attributes() {
        return array(
            'selectedBlock' => array(
                'type' => 'string',
                'default' => '',
            ),
            'customClasses' => array(
                'type' => 'string',
                'default' => '',
            ),
            'blockConfig' => array(
                'type' => 'object',
                'default' => array(),
            ),
            'blockFeatures' => array(
                'type' => 'object',
                'default' => array(),
            ),
            'alignment' => array(
                'type' => 'string',
                'default' => '',
            ),
        );
    }
    
    /**
     * Dynamische Blöcke aus der Datenbank registrieren
     */
    private function register_dynamic_blocks() {
        // Prüfen ob CBD_Database verfügbar ist
        if (!class_exists('CBD_Database')) {
            return;
        }
        
        $blocks = CBD_Database::get_blocks(array('status' => 'active'));
        
        if (!is_array($blocks)) {
            return;
        }
        
        foreach ($blocks as $block) {
            $block_name = 'cbd/' . sanitize_title($block['name']);
            
            // Prüfen ob Block bereits registriert ist
            if (!WP_Block_Type_Registry::get_instance()->is_registered($block_name)) {
                register_block_type($block_name, array(
                    'render_callback' => array($this, 'render_dynamic_block'),
                    'attributes' => array_merge(
                        $this->get_block_attributes(),
                        array(
                            'dbBlockId' => array(
                                'type' => 'number',
                                'default' => $block['id'],
                            ),
                        )
                    ),
                ));
                
                if ($this->debug_mode) {
                    error_log('CBD: Dynamic block registered - ' . $block_name);
                }
            }
        }
    }
    
    /**
     * Container-Block rendern
     */
    public function render_container_block($attributes, $content) {
        $selected_block = !empty($attributes['selectedBlock']) ? $attributes['selectedBlock'] : '';
        $custom_classes = !empty($attributes['customClasses']) ? esc_attr($attributes['customClasses']) : '';
        $block_config = !empty($attributes['blockConfig']) ? $attributes['blockConfig'] : array();
        $block_features = !empty($attributes['blockFeatures']) ? $attributes['blockFeatures'] : array();
        
        // Block-Daten laden wenn ein Block ausgewählt wurde
        $block_data = null;
        if ($selected_block) {
            global $wpdb;
            $block_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE name = %s AND status = 'active'",
                $selected_block
            ), ARRAY_A);
            
            if ($block_data) {
                $block_data['config'] = json_decode($block_data['config'], true) ?: array();
                $block_data['styles'] = json_decode($block_data['styles'], true) ?: array();
                $block_data['features'] = json_decode($block_data['features'], true) ?: array();
            }
        }
        
        // CSS-Klassen zusammenstellen
        $classes = array('wp-block-container-block-designer-container', 'cbd-container');
        if ($custom_classes) {
            $classes[] = $custom_classes;
        }
        if ($selected_block) {
            $classes[] = 'cbd-container-' . sanitize_html_class($selected_block);
        }
        
        // Inline-Styles generieren
        $inline_styles = $this->generate_inline_styles($block_data);
        
        // HTML generieren
        $output = sprintf(
            '<div class="%s"%s>',
            esc_attr(implode(' ', $classes)),
            !empty($inline_styles) ? ' style="' . esc_attr($inline_styles) . '"' : ''
        );
        
        // Features rendern
        if ($block_data && !empty($block_data['features'])) {
            $output .= $this->render_features($block_data['features'], $block_config);
        }
        
        // Inhalt
        $output .= '<div class="cbd-container-content">';
        $output .= $content;
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Dynamischen Block rendern
     */
    public function render_dynamic_block($attributes, $content) {
        // Für dynamische Blöcke den Standard-Renderer verwenden
        return $this->render_container_block($attributes, $content);
    }
    
    /**
     * Inline-Styles generieren
     */
    private function generate_inline_styles($block_data) {
        if (!$block_data || empty($block_data['styles'])) {
            return '';
        }
        
        $styles = $block_data['styles'];
        $css_properties = array();
        
        // Padding
        if (!empty($styles['padding'])) {
            $padding = $styles['padding'];
            $css_properties[] = sprintf(
                'padding: %dpx %dpx %dpx %dpx',
                intval($padding['top'] ?? 20),
                intval($padding['right'] ?? 20),
                intval($padding['bottom'] ?? 20),
                intval($padding['left'] ?? 20)
            );
        }
        
        // Hintergrundfarbe
        if (!empty($styles['background']['color'])) {
            $css_properties[] = 'background-color: ' . $styles['background']['color'];
        }
        
        // Textfarbe
        if (!empty($styles['text']['color'])) {
            $css_properties[] = 'color: ' . $styles['text']['color'];
        }
        
        // Border
        if (!empty($styles['border'])) {
            $border = $styles['border'];
            if (!empty($border['width']) && $border['width'] > 0) {
                $css_properties[] = sprintf(
                    'border: %dpx solid %s',
                    intval($border['width']),
                    $border['color'] ?? '#e0e0e0'
                );
            }
            if (!empty($border['radius'])) {
                $css_properties[] = 'border-radius: ' . intval($border['radius']) . 'px';
            }
        }
        
        return implode('; ', $css_properties);
    }
    
    /**
     * Features rendern
     */
    private function render_features($features, $config = array()) {
        $output = '';
        
        // Icon-Feature
        if (!empty($features['icon']['enabled']) && !empty($features['icon']['value'])) {
            $output .= sprintf(
                '<div class="cbd-feature-icon">%s</div>',
                esc_html($features['icon']['value'])
            );
        }
        
        // Nummerierungs-Feature
        if (!empty($features['numbering']['enabled'])) {
            $output .= '<div class="cbd-feature-numbering"></div>';
        }
        
        // Collapse-Feature
        if (!empty($features['collapse']['enabled'])) {
            $output .= '<button class="cbd-feature-collapse" aria-label="' . 
                      esc_attr__('Container ein-/ausklappen', 'container-block-designer') . 
                      '"><span class="dashicons dashicons-arrow-down"></span></button>';
        }
        
        return $output;
    }
}