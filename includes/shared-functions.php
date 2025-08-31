<?php
/**
 * Container Block Designer - Shared Functions
 * 
 * Gemeinsame Funktionen die von mehreren Komponenten genutzt werden
 * 
 * @package ContainerBlockDesigner
 * @since 2.3.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate CSS for a block
 * 
 * Diese Funktion wird sowohl von AJAX als auch REST API genutzt
 * 
 * @param int $block_id Block ID
 * @param array $config Block configuration
 */
if (!function_exists('cbd_generate_block_css')) {
    function cbd_generate_block_css($block_id, $config) {
        // Get styles from config
        $styles = isset($config['styles']) ? $config['styles'] : array();
        
        // Extract padding values
        $padding_top = isset($styles['padding']['top']) ? intval($styles['padding']['top']) : 20;
        $padding_right = isset($styles['padding']['right']) ? intval($styles['padding']['right']) : 20;
        $padding_bottom = isset($styles['padding']['bottom']) ? intval($styles['padding']['bottom']) : 20;
        $padding_left = isset($styles['padding']['left']) ? intval($styles['padding']['left']) : 20;
        
        // Extract colors and text alignment
        $bg_color = isset($styles['background']['color']) ? $styles['background']['color'] : '#ffffff';
        $text_color = isset($styles['text']['color']) ? $styles['text']['color'] : '#333333';
        $text_align = isset($styles['text']['alignment']) ? $styles['text']['alignment'] : 'left';
        
        // Extract border values
        $border_width = isset($styles['border']['width']) ? intval($styles['border']['width']) : 0;
        $border_color = isset($styles['border']['color']) ? $styles['border']['color'] : '#dddddd';
        $border_radius = isset($styles['border']['radius']) ? intval($styles['border']['radius']) : 0;
        
        // Generate CSS
        $css = "
/* Container Block {$block_id} */
.cbd-container-{$block_id} {
    padding: {$padding_top}px {$padding_right}px {$padding_bottom}px {$padding_left}px;
    background-color: {$bg_color};
    color: {$text_color};
    text-align: {$text_align};
    border: {$border_width}px solid {$border_color};
    border-radius: {$border_radius}px;
}
";
        
        // Save CSS to file
        $upload_dir = wp_upload_dir();
        $cbd_dir = $upload_dir['basedir'] . '/cbd-blocks';
        
        if (!file_exists($cbd_dir)) {
            wp_mkdir_p($cbd_dir);
        }
        
        $css_file = $cbd_dir . '/block-' . $block_id . '.css';
        file_put_contents($css_file, $css);
        
        // Log success
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CBD: CSS generated for block ' . $block_id);
        }
    }
}

/**
 * Debug helper for AJAX requests
 */
if (!function_exists('cbd_debug_ajax_request')) {
    function cbd_debug_ajax_request($action) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CBD AJAX: ' . $action . ' called');
            error_log('CBD POST: ' . print_r($_POST, true));
        }
    }
}

/**
 * Get default features array
 * 
 * @return array Default features configuration
 */
if (!function_exists('cbd_get_default_features')) {
    function cbd_get_default_features() {
        return array(
            'icon' => array('enabled' => false, 'value' => 'dashicons-admin-generic'),
            'collapse' => array('enabled' => false, 'defaultState' => 'expanded'),
            'numbering' => array('enabled' => false, 'format' => 'numeric'),
            'copyText' => array('enabled' => false, 'buttonText' => 'Text kopieren'),
            'screenshot' => array('enabled' => false, 'buttonText' => 'Screenshot')
        );
    }
}

/**
 * Sanitize features array
 * 
 * @param array $features Raw features array
 * @return array Sanitized features array
 */
if (!function_exists('cbd_sanitize_features')) {
    function cbd_sanitize_features($features) {
        $default_features = cbd_get_default_features();
        
        if (!is_array($features)) {
            return $default_features;
        }
        
        // Sanitize each feature
        $sanitized = array();
        
        // Icon feature
        $sanitized['icon'] = array(
            'enabled' => !empty($features['icon']['enabled']),
            'value' => sanitize_text_field($features['icon']['value'] ?? 'dashicons-admin-generic')
        );
        
        // Collapse feature
        $sanitized['collapse'] = array(
            'enabled' => !empty($features['collapse']['enabled']),
            'defaultState' => in_array($features['collapse']['defaultState'] ?? '', array('expanded', 'collapsed')) 
                ? $features['collapse']['defaultState'] 
                : 'expanded'
        );
        
        // Numbering feature
        $sanitized['numbering'] = array(
            'enabled' => !empty($features['numbering']['enabled']),
            'format' => in_array($features['numbering']['format'] ?? '', array('numeric', 'alpha', 'roman'))
                ? $features['numbering']['format']
                : 'numeric'
        );
        
        // Copy text feature
        $sanitized['copyText'] = array(
            'enabled' => !empty($features['copyText']['enabled']),
            'buttonText' => sanitize_text_field($features['copyText']['buttonText'] ?? 'Text kopieren')
        );
        
        // Screenshot feature
        $sanitized['screenshot'] = array(
            'enabled' => !empty($features['screenshot']['enabled']),
            'buttonText' => sanitize_text_field($features['screenshot']['buttonText'] ?? 'Screenshot')
        );
        
        return $sanitized;
    }
}