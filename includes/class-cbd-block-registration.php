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
     * Konstruktor
     */
    public function __construct() {
        // Nichts zu tun hier
    }
    
    /**
     * Alle Blöcke registrieren
     */
    public function register_blocks() {
        // Haupt-Container-Block registrieren
        register_block_type('cbd/container', array(
            'render_callback' => array($this, 'render_container_block'),
            'attributes' => $this->get_block_attributes(),
            'supports' => array(
                'html' => false,
                'align' => array('wide', 'full'),
                'anchor' => true,
                'customClassName' => true,
            ),
        ));
        
        // Dynamische Blöcke aus der Datenbank registrieren
        $this->register_dynamic_blocks();
    }
    
    /**
     * Block-Attribute definieren
     */
    private function get_block_attributes() {
        return array(
            'blockId' => array(
                'type' => 'number',
                'default' => 0,
            ),
            'blockName' => array(
                'type' => 'string',
                'default' => '',
            ),
            'customClasses' => array(
                'type' => 'string',
                'default' => '',
            ),
            'alignment' => array(
                'type' => 'string',
                'default' => '',
            ),
            'styles' => array(
                'type' => 'object',
                'default' => array(),
            ),
            'features' => array(
                'type' => 'object',
                'default' => array(),
            ),
            'content' => array(
                'type' => 'string',
                'default' => '',
            ),
        );
    }
    
    /**
     * Dynamische Blöcke aus der Datenbank registrieren
     */
    private function register_dynamic_blocks() {
        $blocks = CBD_Database::get_blocks(array('status' => 'active'));
        
        foreach ($blocks as $block) {
            $block_name = 'cbd/' . sanitize_title($block['name']);
            
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
            }
        }
    }
    
    /**
     * Container-Block rendern
     */
    public function render_container_block($attributes, $content) {
        $block_id = !empty($attributes['blockId']) ? intval($attributes['blockId']) : 0;
        $custom_classes = !empty($attributes['customClasses']) ? esc_attr($attributes['customClasses']) : '';
        $alignment = !empty($attributes['alignment']) ? esc_attr($attributes['alignment']) : '';
        
        // Block-Daten aus der Datenbank laden
        $block_data = null;
        if ($block_id > 0) {
            $block_data = CBD_Database::get_block($block_id);
        }
        
        // CSS-Klassen zusammenstellen
        $classes = array('cbd-container-block');
        if (!empty($custom_classes)) {
            $classes[] = $custom_classes;
        }
        if (!empty($alignment)) {
            $classes[] = 'align' . $alignment;
        }
        if ($block_data) {
            $classes[] = 'cbd-block-' . sanitize_html_class($block_data['name']);
        }
        
        // Inline-Styles generieren
        $inline_styles = $this->generate_inline_styles($attributes, $block_data);
        
        // HTML generieren
        $output = sprintf(
            '<div class="%s"%s>',
            esc_attr(implode(' ', $classes)),
            !empty($inline_styles) ? ' style="' . esc_attr($inline_styles) . '"' : ''
        );
        
        // Features rendern
        $output .= $this->render_features($attributes, $block_data);
        
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
        $db_block_id = !empty($attributes['dbBlockId']) ? intval($attributes['dbBlockId']) : 0;
        
        if ($db_block_id > 0) {
            $attributes['blockId'] = $db_block_id;
        }
        
        return $this->render_container_block($attributes, $content);
    }
    
    /**
     * Inline-Styles generieren
     */
    private function generate_inline_styles($attributes, $block_data) {
        $styles = array();
        
        // Styles aus Attributen
        if (!empty($attributes['styles'])) {
            $attr_styles = $attributes['styles'];
            
            // Padding
            if (!empty($attr_styles['padding'])) {
                $padding = $attr_styles['padding'];
                $styles[] = sprintf(
                    'padding: %dpx %dpx %dpx %dpx',
                    intval($padding['top'] ?? 20),
                    intval($padding['right'] ?? 20),
                    intval($padding['bottom'] ?? 20),
                    intval($padding['left'] ?? 20)
                );
            }
            
            // Background
            if (!empty($attr_styles['background']['color'])) {
                $styles[] = 'background-color: ' . esc_attr($attr_styles['background']['color']);
            }
            
            // Border
            if (!empty($attr_styles['border'])) {
                $border = $attr_styles['border'];
                if (!empty($border['width']) && $border['width'] > 0) {
                    $styles[] = sprintf(
                        'border: %dpx %s %s',
                        intval($border['width']),
                        esc_attr($border['style'] ?? 'solid'),
                        esc_attr($border['color'] ?? '#e0e0e0')
                    );
                }
                if (!empty($border['radius'])) {
                    $styles[] = 'border-radius: ' . intval($border['radius']) . 'px';
                }
            }
        }
        
        // Styles aus Datenbank
        if ($block_data && !empty($block_data['styles'])) {
            $db_styles = $block_data['styles'];
            
            // Nur anwenden wenn nicht bereits durch Attribute gesetzt
            if (empty($attributes['styles'])) {
                // Padding
                if (!empty($db_styles['padding'])) {
                    $padding = $db_styles['padding'];
                    $styles[] = sprintf(
                        'padding: %dpx %dpx %dpx %dpx',
                        intval($padding['top'] ?? 20),
                        intval($padding['right'] ?? 20),
                        intval($padding['bottom'] ?? 20),
                        intval($padding['left'] ?? 20)
                    );
                }
                
                // Background
                if (!empty($db_styles['background']['color'])) {
                    $styles[] = 'background-color: ' . esc_attr($db_styles['background']['color']);
                }
                
                // Border
                if (!empty($db_styles['border'])) {
                    $border = $db_styles['border'];
                    if (!empty($border['width']) && $border['width'] > 0) {
                        $styles[] = sprintf(
                            'border: %dpx %s %s',
                            intval($border['width']),
                            esc_attr($border['style'] ?? 'solid'),
                            esc_attr($border['color'] ?? '#e0e0e0')
                        );
                    }
                    if (!empty($border['radius'])) {
                        $styles[] = 'border-radius: ' . intval($border['radius']) . 'px';
                    }
                }
            }
        }
        
        return implode('; ', $styles);
    }
    
    /**
     * Features rendern
     */
    private function render_features($attributes, $block_data) {
        $output = '';
        
        // Features aus Attributen oder Datenbank
        $features = !empty($attributes['features']) ? $attributes['features'] : 
                   ($block_data && !empty($block_data['features']) ? $block_data['features'] : array());
        
        if (empty($features)) {
            return $output;
        }
        
        // Icon
        if (!empty($features['icon']['enabled']) && !empty($features['icon']['value'])) {
            $output .= sprintf(
                '<div class="cbd-feature-icon"><span class="dashicons %s"></span></div>',
                esc_attr($features['icon']['value'])
            );
        }
        
        // Collapse-Button
        if (!empty($features['collapse']['enabled'])) {
            $default_state = $features['collapse']['defaultState'] ?? 'expanded';
            $output .= sprintf(
                '<button class="cbd-collapse-toggle" data-state="%s">
                    <span class="dashicons dashicons-arrow-%s"></span>
                </button>',
                esc_attr($default_state),
                $default_state === 'expanded' ? 'up' : 'down'
            );
        }
        
        // Numbering
        if (!empty($features['numbering']['enabled'])) {
            $format = $features['numbering']['format'] ?? 'numeric';
            $output .= sprintf(
                '<div class="cbd-numbering" data-format="%s"></div>',
                esc_attr($format)
            );
        }
        
        // Copy-Text Button
        if (!empty($features['copyText']['enabled'])) {
            $button_text = $features['copyText']['buttonText'] ?? __('Text kopieren', 'container-block-designer');
            $output .= sprintf(
                '<button class="cbd-copy-text">%s</button>',
                esc_html($button_text)
            );
        }
        
        // Screenshot Button
        if (!empty($features['screenshot']['enabled'])) {
            $button_text = $features['screenshot']['buttonText'] ?? __('Screenshot', 'container-block-designer');
            $output .= sprintf(
                '<button class="cbd-screenshot">%s</button>',
                esc_html($button_text)
            );
        }
        
        return $output;
    }
}