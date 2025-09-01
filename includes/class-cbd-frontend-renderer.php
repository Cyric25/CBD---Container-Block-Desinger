<?php
/**
 * Container Block Designer - Frontend Renderer
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend-Renderer Klasse
 */
class CBD_Frontend_Renderer {
    
    /**
     * Initialisierung
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_render_callback'));
    }
    
    /**
     * Render-Callback registrieren
     */
    public static function register_render_callback() {
        if (function_exists('register_block_type')) {
            register_block_type('container-block-designer/container', array(
                'render_callback' => array(__CLASS__, 'render_block')
            ));
        }
    }
    
    /**
     * Block rendern
     */
    public static function render_block($attributes, $content) {
        // Attribute mit Standardwerten
        $selected_block = isset($attributes['selectedBlock']) ? sanitize_text_field($attributes['selectedBlock']) : '';
        $custom_classes = isset($attributes['customClasses']) ? sanitize_text_field($attributes['customClasses']) : '';
        $block_config = isset($attributes['blockConfig']) ? $attributes['blockConfig'] : array();
        $block_features = isset($attributes['blockFeatures']) ? $attributes['blockFeatures'] : array();
        
        // Container-Klassen erstellen
        $container_classes = array(
            'wp-block-container-block-designer-container',
            'cbd-container'
        );
        
        if ($selected_block) {
            $container_classes[] = 'cbd-block-' . esc_attr($selected_block);
        }
        
        if ($custom_classes) {
            $container_classes[] = esc_attr($custom_classes);
        }
        
        // Features prüfen
        $has_icon = isset($block_features['icon']) && $block_features['icon']['enabled'];
        $has_collapse = isset($block_features['collapse']) && $block_features['collapse']['enabled'];
        $has_numbering = isset($block_features['numbering']) && $block_features['numbering']['enabled'];
        $has_copy = isset($block_features['copyText']) && $block_features['copyText']['enabled'];
        $has_screenshot = isset($block_features['screenshot']) && $block_features['screenshot']['enabled'];
        
        // Unique ID für JavaScript-Features
        $block_id = 'cbd-block-' . uniqid();
        
        // Output Buffer starten
        ob_start();
        ?>
        <div id="<?php echo esc_attr($block_id); ?>" 
             class="<?php echo esc_attr(implode(' ', $container_classes)); ?>"
             <?php if ($has_collapse): ?>
                data-collapse="true"
                data-collapse-default="<?php echo esc_attr($block_features['collapse']['defaultState'] ?? 'expanded'); ?>"
             <?php endif; ?>>
            
            <?php if ($has_icon && !empty($block_features['icon']['value'])): ?>
                <div class="cbd-block-icon">
                    <span class="<?php echo esc_attr($block_features['icon']['value']); ?>"></span>
                </div>
            <?php endif; ?>
            
            <?php if ($has_numbering): ?>
                <div class="cbd-block-number" 
                     data-format="<?php echo esc_attr($block_features['numbering']['format'] ?? 'numeric'); ?>">
                </div>
            <?php endif; ?>
            
            <?php if ($has_collapse): ?>
                <button class="cbd-collapse-toggle" aria-expanded="true">
                    <span class="dashicons dashicons-arrow-down"></span>
                </button>
            <?php endif; ?>
            
            <div class="cbd-block-content">
                <?php echo $content; ?>
            </div>
            
            <?php if ($has_copy || $has_screenshot): ?>
                <div class="cbd-block-actions">
                    <?php if ($has_copy): ?>
                        <button class="cbd-copy-text" data-block-id="<?php echo esc_attr($block_id); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php echo esc_html($block_features['copyText']['buttonText'] ?? __('Text kopieren', 'container-block-designer')); ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($has_screenshot): ?>
                        <button class="cbd-screenshot" data-block-id="<?php echo esc_attr($block_id); ?>">
                            <span class="dashicons dashicons-camera"></span>
                            <?php echo esc_html($block_features['screenshot']['buttonText'] ?? __('Screenshot', 'container-block-designer')); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}