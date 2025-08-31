<?php
/**
 * Container Block Designer - Erweiterte Admin-Einstellungen
 * Positionierungsoptionen für Icons und Zähler
 * Version: 2.4.0
 * 
 * Datei speichern als: includes/admin-position-settings.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Erweiterte Positionierungs-Einstellungen für Features
 */
class CBD_Position_Settings {
    
    /**
     * Verfügbare Positionierungsoptionen
     */
    public static function get_position_options() {
        return array(
            'inside' => array(
                'label' => __('Innerhalb', 'container-block-designer'),
                'positions' => array(
                    'top-left'     => __('Oben Links', 'container-block-designer'),
                    'top-center'   => __('Oben Mitte', 'container-block-designer'), 
                    'top-right'    => __('Oben Rechts', 'container-block-designer'),
                    'middle-left'  => __('Mitte Links', 'container-block-designer'),
                    'middle-center'=> __('Mitte Zentriert', 'container-block-designer'),
                    'middle-right' => __('Mitte Rechts', 'container-block-designer'),
                    'bottom-left'  => __('Unten Links', 'container-block-designer'),
                    'bottom-center'=> __('Unten Mitte', 'container-block-designer'),
                    'bottom-right' => __('Unten Rechts', 'container-block-designer')
                )
            ),
            'outside' => array(
                'label' => __('Außerhalb', 'container-block-designer'),
                'positions' => array(
                    'outside-top-left'     => __('Außerhalb Oben Links', 'container-block-designer'),
                    'outside-top-center'   => __('Außerhalb Oben Mitte', 'container-block-designer'),
                    'outside-top-right'    => __('Außerhalb Oben Rechts', 'container-block-designer'),
                    'outside-bottom-left'  => __('Außerhalb Unten Links', 'container-block-designer'),
                    'outside-bottom-center'=> __('Außerhalb Unten Mitte', 'container-block-designer'),
                    'outside-bottom-right' => __('Außerhalb Unten Rechts', 'container-block-designer'),
                    'outside-left-middle'  => __('Außerhalb Links Mitte', 'container-block-designer'),
                    'outside-right-middle' => __('Außerhalb Rechts Mitte', 'container-block-designer')
                )
            )
        );
    }
    
    /**
     * Rendert die Positionierungs-Einstellungen für ein Feature
     */
    public static function render_position_settings($feature_type, $current_settings = array()) {
        $default_settings = array(
            'placement' => 'inside',
            'position' => 'top-left',
            'offset_x' => '10',
            'offset_y' => '10',
            'z_index' => '100'
        );
        
        $settings = array_merge($default_settings, $current_settings);
        $position_options = self::get_position_options();
        
        echo '<div class="cbd-position-settings" id="position-settings-' . esc_attr($feature_type) . '">';
        echo '<h4>' . sprintf(__('%s Positionierung', 'container-block-designer'), ucfirst($feature_type)) . '</h4>';
        
        // Platzierung: Innerhalb/Außerhalb
        echo '<div class="cbd-setting-group">';
        echo '<label>' . __('Platzierung:', 'container-block-designer') . '</label>';
        echo '<div class="cbd-radio-group">';
        
        foreach ($position_options as $placement_key => $placement_data) {
            $checked = ($settings['placement'] === $placement_key) ? 'checked' : '';
            echo '<label class="cbd-radio-label">';
            echo '<input type="radio" name="' . esc_attr($feature_type) . '_placement" value="' . esc_attr($placement_key) . '" ' . $checked . '>';
            echo '<span>' . esc_html($placement_data['label']) . '</span>';
            echo '</label>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Position innerhalb der gewählten Platzierung
        echo '<div class="cbd-setting-group">';
        echo '<label>' . __('Position:', 'container-block-designer') . '</label>';
        echo '<select name="' . esc_attr($feature_type) . '_position" class="cbd-position-select">';
        
        foreach ($position_options as $placement_key => $placement_data) {
            $group_class = ($settings['placement'] === $placement_key) ? '' : 'style="display:none;"';
            echo '<optgroup label="' . esc_attr($placement_data['label']) . '" class="position-group-' . esc_attr($placement_key) . '" ' . $group_class . '>';
            
            foreach ($placement_data['positions'] as $position_key => $position_label) {
                $selected = ($settings['position'] === $position_key) ? 'selected' : '';
                echo '<option value="' . esc_attr($position_key) . '" ' . $selected . '>' . esc_html($position_label) . '</option>';
            }
            
            echo '</optgroup>';
        }
        
        echo '</select>';
        echo '</div>';
        
        // Offset-Einstellungen
        echo '<div class="cbd-setting-group cbd-input-row">';
        echo '<div class="cbd-input-col">';
        echo '<label>' . __('X-Offset (px):', 'container-block-designer') . '</label>';
        echo '<input type="number" name="' . esc_attr($feature_type) . '_offset_x" value="' . esc_attr($settings['offset_x']) . '" min="-100" max="100" class="small-text">';
        echo '</div>';
        
        echo '<div class="cbd-input-col">';
        echo '<label>' . __('Y-Offset (px):', 'container-block-designer') . '</label>';
        echo '<input type="number" name="' . esc_attr($feature_type) . '_offset_y" value="' . esc_attr($settings['offset_y']) . '" min="-100" max="100" class="small-text">';
        echo '</div>';
        echo '</div>';
        
        // Z-Index
        echo '<div class="cbd-setting-group">';
        echo '<label>' . __('Ebene (z-index):', 'container-block-designer') . '</label>';
        echo '<input type="number" name="' . esc_attr($feature_type) . '_z_index" value="' . esc_attr($settings['z_index']) . '" min="1" max="9999" class="small-text">';
        echo '<p class="description">' . __('Höhere Werte erscheinen über niedrigeren Werten.', 'container-block-designer') . '</p>';
        echo '</div>';
        
        // Visuelle Vorschau
        echo '<div class="cbd-position-preview" id="preview-' . esc_attr($feature_type) . '">';
        echo '<div class="cbd-preview-container">';
        echo '<div class="cbd-preview-element ' . esc_attr($feature_type) . '-preview" data-position="' . esc_attr($settings['position']) . '"></div>';
        echo '<span class="cbd-preview-label">' . __('Container', 'container-block-designer') . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Speichert die Positionierungs-Einstellungen
     */
    public static function save_position_settings($feature_type, $post_data) {
        $settings = array();
        
        $fields = array(
            'placement' => 'inside',
            'position' => 'top-left', 
            'offset_x' => '10',
            'offset_y' => '10',
            'z_index' => '100'
        );
        
        foreach ($fields as $field => $default) {
            $key = $feature_type . '_' . $field;
            if (isset($post_data[$key])) {
                $settings[$field] = sanitize_text_field($post_data[$key]);
            } else {
                $settings[$field] = $default;
            }
        }
        
        return $settings;
    }
    
    /**
     * Generiert CSS-Klassen basierend auf Positionierungs-Einstellungen
     */
    public static function generate_position_classes($settings) {
        $classes = array();
        $classes[] = 'cbd-positioned';
        $classes[] = 'cbd-' . $settings['placement'];
        $classes[] = 'cbd-' . str_replace('outside-', '', $settings['position']);
        
        return implode(' ', $classes);
    }
    
    /**
     * Generiert CSS-Style basierend auf Positionierungs-Einstellungen
     */
    public static function generate_position_styles($settings) {
        $styles = array();
        
        // Z-Index
        $styles[] = 'z-index: ' . intval($settings['z_index']);
        
        // Position-spezifische Styles
        $position = $settings['position'];
        $offset_x = intval($settings['offset_x']);
        $offset_y = intval($settings['offset_y']);
        
        // Bestimme CSS-Position basierend auf Einstellungen
        if ($settings['placement'] === 'outside') {
            $styles[] = 'position: absolute';
            
            switch ($position) {
                case 'outside-top-left':
                    $styles[] = 'top: ' . (-$offset_y) . 'px';
                    $styles[] = 'left: ' . (-$offset_x) . 'px';
                    break;
                case 'outside-top-center':
                    $styles[] = 'top: ' . (-$offset_y) . 'px';
                    $styles[] = 'left: 50%';
                    $styles[] = 'transform: translateX(-50%)';
                    break;
                case 'outside-top-right':
                    $styles[] = 'top: ' . (-$offset_y) . 'px';
                    $styles[] = 'right: ' . (-$offset_x) . 'px';
                    break;
                case 'outside-bottom-left':
                    $styles[] = 'bottom: ' . (-$offset_y) . 'px';
                    $styles[] = 'left: ' . (-$offset_x) . 'px';
                    break;
                case 'outside-bottom-center':
                    $styles[] = 'bottom: ' . (-$offset_y) . 'px';
                    $styles[] = 'left: 50%';
                    $styles[] = 'transform: translateX(-50%)';
                    break;
                case 'outside-bottom-right':
                    $styles[] = 'bottom: ' . (-$offset_y) . 'px';
                    $styles[] = 'right: ' . (-$offset_x) . 'px';
                    break;
                case 'outside-left-middle':
                    $styles[] = 'left: ' . (-$offset_x) . 'px';
                    $styles[] = 'top: 50%';
                    $styles[] = 'transform: translateY(-50%)';
                    break;
                case 'outside-right-middle':
                    $styles[] = 'right: ' . (-$offset_x) . 'px';
                    $styles[] = 'top: 50%';
                    $styles[] = 'transform: translateY(-50%)';
                    break;
            }
        } else {
            // Inside positioning
            $styles[] = 'position: absolute';
            
            switch ($position) {
                case 'top-left':
                    $styles[] = 'top: ' . $offset_y . 'px';
                    $styles[] = 'left: ' . $offset_x . 'px';
                    break;
                case 'top-center':
                    $styles[] = 'top: ' . $offset_y . 'px';
                    $styles[] = 'left: 50%';
                    $styles[] = 'transform: translateX(-50%)';
                    break;
                case 'top-right':
                    $styles[] = 'top: ' . $offset_y . 'px';
                    $styles[] = 'right: ' . $offset_x . 'px';
                    break;
                case 'middle-left':
                    $styles[] = 'left: ' . $offset_x . 'px';
                    $styles[] = 'top: 50%';
                    $styles[] = 'transform: translateY(-50%)';
                    break;
                case 'middle-center':
                    $styles[] = 'top: 50%';
                    $styles[] = 'left: 50%';
                    $styles[] = 'transform: translate(-50%, -50%)';
                    break;
                case 'middle-right':
                    $styles[] = 'right: ' . $offset_x . 'px';
                    $styles[] = 'top: 50%';
                    $styles[] = 'transform: translateY(-50%)';
                    break;
                case 'bottom-left':
                    $styles[] = 'bottom: ' . $offset_y . 'px';
                    $styles[] = 'left: ' . $offset_x . 'px';
                    break;
                case 'bottom-center':
                    $styles[] = 'bottom: ' . $offset_y . 'px';
                    $styles[] = 'left: 50%';
                    $styles[] = 'transform: translateX(-50%)';
                    break;
                case 'bottom-right':
                    $styles[] = 'bottom: ' . $offset_y . 'px';
                    $styles[] = 'right: ' . $offset_x . 'px';
                    break;
            }
        }
        
        return implode('; ', $styles);
    }
}

/**
 * AJAX Handler für Live-Vorschau der Positionierung
 */
add_action('wp_ajax_cbd_preview_position', 'cbd_handle_position_preview');
function cbd_handle_position_preview() {
    // Nonce-Überprüfung
    if (!wp_verify_nonce($_POST['nonce'], 'cbd-admin')) {
        wp_die(__('Sicherheitsüberprüfung fehlgeschlagen', 'container-block-designer'));
    }
    
    $feature_type = sanitize_text_field($_POST['feature_type']);
    $settings = array(
        'placement' => sanitize_text_field($_POST['placement']),
        'position' => sanitize_text_field($_POST['position']),
        'offset_x' => intval($_POST['offset_x']),
        'offset_y' => intval($_POST['offset_y']),
        'z_index' => intval($_POST['z_index'])
    );
    
    $styles = CBD_Position_Settings::generate_position_styles($settings);
    $classes = CBD_Position_Settings::generate_position_classes($settings);
    
    wp_send_json_success(array(
        'styles' => $styles,
        'classes' => $classes,
        'preview_html' => '<div class="cbd-preview-element ' . $classes . '" style="' . $styles . '"></div>'
    ));
}

/**
 * Feature-erweiterte Einstellungen in bestehende Admin-Seiten einbinden
 */
add_action('cbd_render_feature_settings', 'cbd_render_position_features', 10, 2);
function cbd_render_position_features($feature_type, $current_settings) {
    if (in_array($feature_type, array('icon', 'numbering'))) {
        $position_settings = isset($current_settings['position']) ? $current_settings['position'] : array();
        CBD_Position_Settings::render_position_settings($feature_type, $position_settings);
    }
}

/**
 * Speichern der erweiterten Position-Einstellungen
 */
add_filter('cbd_save_feature_settings', 'cbd_save_position_features', 10, 3);
function cbd_save_position_features($settings, $feature_type, $post_data) {
    if (in_array($feature_type, array('icon', 'numbering'))) {
        $settings['position'] = CBD_Position_Settings::save_position_settings($feature_type, $post_data);
    }
    return $settings;
}