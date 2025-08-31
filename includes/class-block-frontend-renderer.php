<?php
/**
 * Container Block Designer - Frontend Block Renderer
 * Vereinfachte Version ohne globale Einstellungen
 * 
 * @package ContainerBlockDesigner
 * @version 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Block_Frontend_Renderer {
    
    /**
     * Initialize the renderer
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_block'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_action('wp_footer', array(__CLASS__, 'render_inline_scripts'));
    }
    
    /**
     * Register the block for server-side rendering
     */
    public static function register_block() {
        if (function_exists('register_block_type')) {
            register_block_type('cbd/container-block', array(
                'render_callback' => array(__CLASS__, 'render_block'),
                'editor_script' => 'cbd-container-block',
                'editor_style' => 'cbd-container-block-editor',
                'style' => 'cbd-container-block'
            ));
        }
    }
    
    /**
     * Render block on frontend
     */
    public static function render_block($attributes, $content) {
        // Sanitize and extract attributes with defaults
        $selectedBlock = sanitize_text_field($attributes['selectedBlock'] ?? '');
        $customClasses = sanitize_text_field($attributes['customClasses'] ?? '');
        
        // Feature attributes
        $enableIcon = filter_var($attributes['enableIcon'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $iconValue = sanitize_text_field($attributes['iconValue'] ?? 'dashicons-admin-generic');
        
        $enableCollapse = filter_var($attributes['enableCollapse'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $collapseDefault = sanitize_text_field($attributes['collapseDefault'] ?? 'expanded');
        
        $enableNumbering = filter_var($attributes['enableNumbering'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $numberingFormat = sanitize_text_field($attributes['numberingFormat'] ?? 'numeric');
        $numberingStart = absint($attributes['numberingStart'] ?? 1);
        
        $enableCopyText = filter_var($attributes['enableCopyText'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $copyButtonText = sanitize_text_field($attributes['copyButtonText'] ?? __('Text kopieren', 'container-block-designer'));
        
        $enableScreenshot = filter_var($attributes['enableScreenshot'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $screenshotButtonText = sanitize_text_field($attributes['screenshotButtonText'] ?? __('Screenshot', 'container-block-designer'));
        
        // Build CSS classes
        $cssClasses = array('cbd-container-block');
        if ($selectedBlock) {
            $cssClasses[] = 'cbd-block-' . $selectedBlock;
        }
        if ($customClasses) {
            $cssClasses[] = $customClasses;
        }
        
        // Add feature classes
        if ($enableCollapse) {
            $cssClasses[] = 'cbd-collapsible';
            $cssClasses[] = 'cbd-' . $collapseDefault;
        }
        if ($enableNumbering) {
            $cssClasses[] = 'cbd-numbered';
            $cssClasses[] = 'cbd-numbering-' . $numberingFormat;
        }
        
        // Build data attributes for JavaScript
        $dataAttributes = array();
        if ($enableIcon) {
            $dataAttributes['data-enable-icon'] = 'true';
            $dataAttributes['data-icon-value'] = $iconValue;
        }
        if ($enableCollapse) {
            $dataAttributes['data-enable-collapse'] = 'true';
            $dataAttributes['data-collapse-default'] = $collapseDefault;
        }
        if ($enableNumbering) {
            $dataAttributes['data-enable-numbering'] = 'true';
            $dataAttributes['data-numbering-format'] = $numberingFormat;
            $dataAttributes['data-numbering-start'] = $numberingStart;
        }
        if ($enableCopyText) {
            $dataAttributes['data-enable-copy-text'] = 'true';
            $dataAttributes['data-copy-button-text'] = $copyButtonText;
        }
        if ($enableScreenshot) {
            $dataAttributes['data-enable-screenshot'] = 'true';
            $dataAttributes['data-screenshot-button-text'] = $screenshotButtonText;
        }
        
        // Generate unique ID for this block instance
        $blockId = 'cbd-block-' . uniqid();
        
        // Start output buffering
        ob_start();
        ?>
        
        <div id="<?php echo esc_attr($blockId); ?>" 
             class="<?php echo esc_attr(implode(' ', $cssClasses)); ?>"
             <?php foreach ($dataAttributes as $key => $value): ?>
             <?php echo esc_attr($key); ?>="<?php echo esc_attr($value); ?>"
             <?php endforeach; ?>>
            
            <?php if ($enableIcon || $enableCollapse || $enableNumbering): ?>
            <div class="cbd-block-header">
                <?php if ($enableIcon): ?>
                <span class="cbd-block-icon dashicons <?php echo esc_attr($iconValue); ?>"></span>
                <?php endif; ?>
                
                <?php if ($enableNumbering): ?>
                <span class="cbd-block-number" data-format="<?php echo esc_attr($numberingFormat); ?>" data-start="<?php echo esc_attr($numberingStart); ?>">
                    <?php echo esc_html(self::format_number($numberingStart, $numberingFormat)); ?>
                </span>
                <?php endif; ?>
                
                <?php if ($enableCollapse): ?>
                <button class="cbd-collapse-toggle" aria-expanded="<?php echo $collapseDefault === 'expanded' ? 'true' : 'false'; ?>">
                    <span class="cbd-toggle-icon dashicons dashicons-<?php echo $collapseDefault === 'expanded' ? 'minus' : 'plus'; ?>"></span>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="cbd-block-content"<?php if ($enableCollapse && $collapseDefault === 'collapsed'): ?> style="display: none;"<?php endif; ?>>
                <?php echo $content; ?>
            </div>
            
            <?php if ($enableCopyText || $enableScreenshot): ?>
            <div class="cbd-block-actions">
                <?php if ($enableCopyText): ?>
                <button class="cbd-copy-text-btn button" data-target="#<?php echo esc_attr($blockId); ?> .cbd-block-content">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php echo esc_html($copyButtonText); ?>
                </button>
                <?php endif; ?>
                
                <?php if ($enableScreenshot): ?>
                <button class="cbd-screenshot-btn button" data-target="#<?php echo esc_attr($blockId); ?>">
                    <span class="dashicons dashicons-camera"></span>
                    <?php echo esc_html($screenshotButtonText); ?>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Format number based on numbering format
     */
    private static function format_number($number, $format) {
        switch ($format) {
            case 'alphabetic':
                // Convert to alphabetic (A, B, C, ...)
                return chr(64 + $number); // A=65, so 64+1=A
                
            case 'roman':
                // Convert to roman numerals
                return self::int_to_roman($number);
                
            case 'numeric':
            default:
                return $number;
        }
    }
    
    /**
     * Convert integer to roman numeral
     */
    private static function int_to_roman($integer) {
        $table = array(
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        );
        $return = '';
        while ($integer > 0) {
            foreach ($table as $rom => $arb) {
                if ($integer >= $arb) {
                    $integer -= $arb;
                    $return .= $rom;
                    break;
                }
            }
        }
        return $return;
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend_assets() {
        // Only enqueue on pages that have our blocks
        if (has_block('cbd/container-block')) {
            
            // Frontend CSS
            wp_enqueue_style(
                'cbd-container-block',
                CBD_PLUGIN_URL . 'assets/css/container-block.css',
                array(),
                CBD_VERSION
            );
            
            // Frontend JavaScript
            wp_enqueue_script(
                'cbd-frontend',
                CBD_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                CBD_VERSION,
                true
            );
            
            // Localize script for frontend functionality
            wp_localize_script('cbd-frontend', 'cbdFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cbd-frontend'),
                'strings' => array(
                    'copied' => __('Kopiert!', 'container-block-designer'),
                    'copyError' => __('Fehler beim Kopieren', 'container-block-designer'),
                    'screenshotSaved' => __('Screenshot gespeichert!', 'container-block-designer'),
                    'screenshotError' => __('Fehler beim Erstellen des Screenshots', 'container-block-designer'),
                )
            ));
        }
    }
    
    /**
     * Render inline scripts for block initialization
     */
    public static function render_inline_scripts() {
        if (has_block('cbd/container-block')) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Initialize container blocks
                $('.cbd-container-block').each(function() {
                    var $block = $(this);
                    var blockId = $block.attr('id');
                    
                    // Initialize numbering for multiple blocks
                    if ($block.data('enable-numbering')) {
                        CBD_Frontend.initializeNumbering($block);
                    }
                    
                    // Initialize collapse functionality
                    if ($block.data('enable-collapse')) {
                        CBD_Frontend.initializeCollapse($block);
                    }
                    
                    // Initialize copy text functionality
                    if ($block.data('enable-copy-text')) {
                        CBD_Frontend.initializeCopyText($block);
                    }
                    
                    // Initialize screenshot functionality
                    if ($block.data('enable-screenshot')) {
                        CBD_Frontend.initializeScreenshot($block);
                    }
                });
            });
            </script>
            <?php
        }
    }
}

// Initialize the renderer
CBD_Block_Frontend_Renderer::init();