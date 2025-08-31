<?php
/**
 * Admin Features Class
 * 
 * @package ContainerBlockDesigner
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Features handler
 */
class CBD_Admin_Features {
    
    private static $instance = null;
    
    /**
     * Feature types
     */
    private $feature_types = array(
        'toggle' => 'Toggle/Accordion',
        'numbering' => 'Nummerierung',
        'copyText' => 'Text kopieren',
        'screenshot' => 'Screenshot',
        'print' => 'Drucken'
    );
    
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
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_cbd_save_features', array($this, 'ajax_save_features'));
        add_action('wp_ajax_cbd_get_features', array($this, 'ajax_get_features'));
        add_action('wp_ajax_cbd_toggle_feature', array($this, 'ajax_toggle_feature'));
        
        // Frontend AJAX
        add_action('wp_ajax_cbd_copy_text', array($this, 'ajax_copy_text'));
        add_action('wp_ajax_nopriv_cbd_copy_text', array($this, 'ajax_copy_text'));
    }
    
    /**
     * Render features settings
     */
    public function render_features_settings($block_id = null) {
        $features = array();
        
        if ($block_id) {
            $features = $this->get_block_features($block_id);
        }
        ?>
        <div class="cbd-features-settings">
            <h3><?php _e('Erweiterte Features', 'container-block-designer'); ?></h3>
            
            <!-- Toggle/Accordion Feature -->
            <div class="cbd-feature-item" data-feature="toggle">
                <label>
                    <input type="checkbox" 
                           name="features[toggle][enabled]" 
                           class="cbd-feature-toggle"
                           <?php checked(!empty($features['toggle']['enabled'])); ?>>
                    <span><?php _e('Toggle/Accordion aktivieren', 'container-block-designer'); ?></span>
                </label>
                
                <div class="cbd-feature-options" <?php echo empty($features['toggle']['enabled']) ? 'style="display:none;"' : ''; ?>>
                    <label>
                        <span><?php _e('Standard-Status:', 'container-block-designer'); ?></span>
                        <select name="features[toggle][defaultState]">
                            <option value="collapsed" <?php selected($features['toggle']['defaultState'] ?? '', 'collapsed'); ?>>
                                <?php _e('Eingeklappt', 'container-block-designer'); ?>
                            </option>
                            <option value="expanded" <?php selected($features['toggle']['defaultState'] ?? '', 'expanded'); ?>>
                                <?php _e('Ausgeklappt', 'container-block-designer'); ?>
                            </option>
                        </select>
                    </label>
                    
                    <label>
                        <span><?php _e('Animation:', 'container-block-designer'); ?></span>
                        <select name="features[toggle][animation]">
                            <option value="slide" <?php selected($features['toggle']['animation'] ?? '', 'slide'); ?>>Slide</option>
                            <option value="fade" <?php selected($features['toggle']['animation'] ?? '', 'fade'); ?>>Fade</option>
                            <option value="none" <?php selected($features['toggle']['animation'] ?? '', 'none'); ?>>None</option>
                        </select>
                    </label>
                </div>
            </div>
            
            <!-- Numbering Feature -->
            <div class="cbd-feature-item" data-feature="numbering">
                <label>
                    <input type="checkbox" 
                           name="features[numbering][enabled]" 
                           class="cbd-feature-toggle"
                           <?php checked(!empty($features['numbering']['enabled'])); ?>>
                    <span><?php _e('Automatische Nummerierung', 'container-block-designer'); ?></span>
                </label>
                
                <div class="cbd-feature-options" <?php echo empty($features['numbering']['enabled']) ? 'style="display:none;"' : ''; ?>>
                    <label>
                        <span><?php _e('Format:', 'container-block-designer'); ?></span>
                        <select name="features[numbering][format]">
                            <option value="numeric" <?php selected($features['numbering']['format'] ?? '', 'numeric'); ?>>
                                1, 2, 3...
                            </option>
                            <option value="alpha" <?php selected($features['numbering']['format'] ?? '', 'alpha'); ?>>
                                A, B, C...
                            </option>
                            <option value="roman" <?php selected($features['numbering']['format'] ?? '', 'roman'); ?>>
                                I, II, III...
                            </option>
                        </select>
                    </label>
                    
                    <label>
                        <span><?php _e('Prefix:', 'container-block-designer'); ?></span>
                        <input type="text" 
                               name="features[numbering][prefix]" 
                               value="<?php echo esc_attr($features['numbering']['prefix'] ?? ''); ?>"
                               placeholder="z.B. Step ">
                    </label>
                </div>
            </div>
            
            <!-- Copy Text Feature -->
            <div class="cbd-feature-item" data-feature="copyText">
                <label>
                    <input type="checkbox" 
                           name="features[copyText][enabled]" 
                           class="cbd-feature-toggle"
                           <?php checked(!empty($features['copyText']['enabled'])); ?>>
                    <span><?php _e('Text kopieren Button', 'container-block-designer'); ?></span>
                </label>
                
                <div class="cbd-feature-options" <?php echo empty($features['copyText']['enabled']) ? 'style="display:none;"' : ''; ?>>
                    <label>
                        <span><?php _e('Button-Text:', 'container-block-designer'); ?></span>
                        <input type="text" 
                               name="features[copyText][buttonText]" 
                               value="<?php echo esc_attr($features['copyText']['buttonText'] ?? __('Text kopieren', 'container-block-designer')); ?>">
                    </label>
                    
                    <label>
                        <span><?php _e('Position:', 'container-block-designer'); ?></span>
                        <select name="features[copyText][position]">
                            <option value="top-right" <?php selected($features['copyText']['position'] ?? '', 'top-right'); ?>>
                                <?php _e('Oben rechts', 'container-block-designer'); ?>
                            </option>
                            <option value="top-left" <?php selected($features['copyText']['position'] ?? '', 'top-left'); ?>>
                                <?php _e('Oben links', 'container-block-designer'); ?>
                            </option>
                            <option value="bottom-right" <?php selected($features['copyText']['position'] ?? '', 'bottom-right'); ?>>
                                <?php _e('Unten rechts', 'container-block-designer'); ?>
                            </option>
                            <option value="bottom-left" <?php selected($features['copyText']['position'] ?? '', 'bottom-left'); ?>>
                                <?php _e('Unten links', 'container-block-designer'); ?>
                            </option>
                        </select>
                    </label>
                </div>
            </div>
            
            <!-- Screenshot Feature -->
            <div class="cbd-feature-item" data-feature="screenshot">
                <label>
                    <input type="checkbox" 
                           name="features[screenshot][enabled]" 
                           class="cbd-feature-toggle"
                           <?php checked(!empty($features['screenshot']['enabled'])); ?>>
                    <span><?php _e('Screenshot Button', 'container-block-designer'); ?></span>
                </label>
                
                <div class="cbd-feature-options" <?php echo empty($features['screenshot']['enabled']) ? 'style="display:none;"' : ''; ?>>
                    <label>
                        <span><?php _e('Button-Text:', 'container-block-designer'); ?></span>
                        <input type="text" 
                               name="features[screenshot][buttonText]" 
                               value="<?php echo esc_attr($features['screenshot']['buttonText'] ?? __('Screenshot', 'container-block-designer')); ?>">
                    </label>
                    
                    <label>
                        <span><?php _e('Dateiname:', 'container-block-designer'); ?></span>
                        <input type="text" 
                               name="features[screenshot][filename]" 
                               value="<?php echo esc_attr($features['screenshot']['filename'] ?? 'container-screenshot'); ?>">
                    </label>
                </div>
            </div>
            
            <!-- Print Feature -->
            <div class="cbd-feature-item" data-feature="print">
                <label>
                    <input type="checkbox" 
                           name="features[print][enabled]" 
                           class="cbd-feature-toggle"
                           <?php checked(!empty($features['print']['enabled'])); ?>>
                    <span><?php _e('Drucken Button', 'container-block-designer'); ?></span>
                </label>
                
                <div class="cbd-feature-options" <?php echo empty($features['print']['enabled']) ? 'style="display:none;"' : ''; ?>>
                    <label>
                        <span><?php _e('Button-Text:', 'container-block-designer'); ?></span>
                        <input type="text" 
                               name="features[print][buttonText]" 
                               value="<?php echo esc_attr($features['print']['buttonText'] ?? __('Drucken', 'container-block-designer')); ?>">
                    </label>
                    
                    <label>
                        <input type="checkbox" 
                               name="features[print][hideOtherElements]" 
                               <?php checked(!empty($features['print']['hideOtherElements'])); ?>>
                        <span><?php _e('Andere Elemente beim Drucken ausblenden', 'container-block-designer'); ?></span>
                    </label>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.cbd-feature-toggle').on('change', function() {
                var $options = $(this).closest('.cbd-feature-item').find('.cbd-feature-options');
                if ($(this).is(':checked')) {
                    $options.slideDown(200);
                } else {
                    $options.slideUp(200);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get block features
     */
    public function get_block_features($block_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT features FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
            $block_id
        ));
        
        if ($result) {
            return json_decode($result, true);
        }
        
        return array();
    }
    
    /**
     * Save block features
     */
    public function save_block_features($block_id, $features) {
        global $wpdb;
        
        // Sanitize features
        $sanitized_features = $this->sanitize_features($features);
        
        $result = $wpdb->update(
            CBD_TABLE_BLOCKS,
            array('features' => wp_json_encode($sanitized_features)),
            array('id' => $block_id),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Sanitize features array
     */
    private function sanitize_features($features) {
        $sanitized = array();
        
        foreach ($this->feature_types as $key => $label) {
            if (isset($features[$key])) {
                $sanitized[$key] = array();
                
                // Enabled flag
                $sanitized[$key]['enabled'] = !empty($features[$key]['enabled']);
                
                // Feature-specific sanitization
                switch ($key) {
                    case 'toggle':
                        $sanitized[$key]['defaultState'] = in_array($features[$key]['defaultState'] ?? '', array('collapsed', 'expanded')) 
                            ? $features[$key]['defaultState'] 
                            : 'collapsed';
                        $sanitized[$key]['animation'] = in_array($features[$key]['animation'] ?? '', array('slide', 'fade', 'none'))
                            ? $features[$key]['animation']
                            : 'slide';
                        break;
                        
                    case 'numbering':
                        $sanitized[$key]['format'] = in_array($features[$key]['format'] ?? '', array('numeric', 'alpha', 'roman'))
                            ? $features[$key]['format']
                            : 'numeric';
                        $sanitized[$key]['prefix'] = sanitize_text_field($features[$key]['prefix'] ?? '');
                        break;
                        
                    case 'copyText':
                        $sanitized[$key]['buttonText'] = sanitize_text_field($features[$key]['buttonText'] ?? __('Text kopieren', 'container-block-designer'));
                        $sanitized[$key]['position'] = in_array($features[$key]['position'] ?? '', array('top-right', 'top-left', 'bottom-right', 'bottom-left'))
                            ? $features[$key]['position']
                            : 'top-right';
                        break;
                        
                    case 'screenshot':
                        $sanitized[$key]['buttonText'] = sanitize_text_field($features[$key]['buttonText'] ?? __('Screenshot', 'container-block-designer'));
                        $sanitized[$key]['filename'] = sanitize_file_name($features[$key]['filename'] ?? 'container-screenshot');
                        break;
                        
                    case 'print':
                        $sanitized[$key]['buttonText'] = sanitize_text_field($features[$key]['buttonText'] ?? __('Drucken', 'container-block-designer'));
                        $sanitized[$key]['hideOtherElements'] = !empty($features[$key]['hideOtherElements']);
                        break;
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX: Save features
     */
    public function ajax_save_features() {
        check_ajax_referer('cbd_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'container-block-designer'));
        }
        
        $block_id = intval($_POST['block_id'] ?? 0);
        $features = $_POST['features'] ?? array();
        
        if (!$block_id) {
            wp_send_json_error(__('Invalid block ID', 'container-block-designer'));
        }
        
        if ($this->save_block_features($block_id, $features)) {
            wp_send_json_success(__('Features saved successfully', 'container-block-designer'));
        } else {
            wp_send_json_error(__('Failed to save features', 'container-block-designer'));
        }
    }
    
    /**
     * AJAX: Get features
     */
    public function ajax_get_features() {
        check_ajax_referer('cbd_admin', 'nonce');
        
        $block_id = intval($_POST['block_id'] ?? 0);
        
        if (!$block_id) {
            wp_send_json_error(__('Invalid block ID', 'container-block-designer'));
        }
        
        $features = $this->get_block_features($block_id);
        wp_send_json_success($features);
    }
    
    /**
     * AJAX: Toggle feature
     */
    public function ajax_toggle_feature() {
        check_ajax_referer('cbd_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'container-block-designer'));
        }
        
        $block_id = intval($_POST['block_id'] ?? 0);
        $feature_key = sanitize_key($_POST['feature'] ?? '');
        $enabled = $_POST['enabled'] === 'true';
        
        if (!$block_id || !$feature_key) {
            wp_send_json_error(__('Invalid parameters', 'container-block-designer'));
        }
        
        $features = $this->get_block_features($block_id);
        
        if (!isset($features[$feature_key])) {
            $features[$feature_key] = array();
        }
        
        $features[$feature_key]['enabled'] = $enabled;
        
        if ($this->save_block_features($block_id, $features)) {
            wp_send_json_success(__('Feature toggled successfully', 'container-block-designer'));
        } else {
            wp_send_json_error(__('Failed to toggle feature', 'container-block-designer'));
        }
    }
    
    /**
     * AJAX: Copy text (Frontend)
     */
    public function ajax_copy_text() {
        // No nonce check for frontend functionality
        wp_send_json_success(__('Text copied to clipboard', 'container-block-designer'));
    }
}