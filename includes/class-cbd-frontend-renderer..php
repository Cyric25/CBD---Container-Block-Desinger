<?php
/**
 * Container Block Designer - Frontend-Renderer
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend-Rendering und Assets
 */
class CBD_Frontend_Renderer {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_footer', array($this, 'render_frontend_scripts'));
        add_filter('render_block', array($this, 'filter_block_output'), 10, 2);
    }
    
    /**
     * Frontend-Assets einbinden
     */
    public function enqueue_frontend_assets() {
        // Prüfen ob die Seite Container-Blocks enthält
        if ($this->page_has_container_blocks()) {
            // Frontend CSS
            wp_enqueue_style(
                'cbd-frontend',
                CBD_PLUGIN_URL . 'assets/css/frontend.css',
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
            
            // Lokalisierung
            wp_localize_script('cbd-frontend', 'cbdFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cbd-frontend'),
                'strings' => array(
                    'copied' => __('Text kopiert!', 'container-block-designer'),
                    'copyError' => __('Fehler beim Kopieren', 'container-block-designer'),
                    'screenshotSaved' => __('Screenshot gespeichert!', 'container-block-designer'),
                    'screenshotError' => __('Fehler beim Screenshot', 'container-block-designer'),
                ),
            ));
            
            // Dashicons für Features
            wp_enqueue_style('dashicons');
        }
    }
    
    /**
     * Prüfen ob die Seite Container-Blocks enthält
     */
    private function page_has_container_blocks() {
        global $post;
        
        if (!$post || !is_singular()) {
            return false;
    }
    
    /**
     * Block-Output filtern
     */
    public function filter_block_output($block_content, $block) {
        // Nur unsere Blocks verarbeiten
        if (strpos($block['blockName'], 'cbd/') !== 0) {
            return $block_content;
        }
        
        // Custom CSS für diesen Block generieren
        $custom_css = $this->generate_block_css($block);
        
        if (!empty($custom_css)) {
            $block_content = '<style>' . $custom_css . '</style>' . $block_content;
        }
        
        return $block_content;
    }
    
    /**
     * Block-spezifisches CSS generieren
     */
    private function generate_block_css($block) {
        $css = '';
        
        if (!empty($block['attrs']['blockId'])) {
            $block_id = $block['attrs']['blockId'];
            $block_data = CBD_Database::get_block($block_id);
            
            if ($block_data && !empty($block_data['styles'])) {
                $styles = $block_data['styles'];
                $selector = '.cbd-block-' . sanitize_html_class($block_data['name']);
                
                $css_rules = array();
                
                // Text-Styles
                if (!empty($styles['text'])) {
                    if (!empty($styles['text']['color'])) {
                        $css_rules[] = 'color: ' . $styles['text']['color'];
                    }
                    if (!empty($styles['text']['size'])) {
                        $css_rules[] = 'font-size: ' . $styles['text']['size'];
                    }
                    if (!empty($styles['text']['alignment'])) {
                        $css_rules[] = 'text-align: ' . $styles['text']['alignment'];
                    }
                }
                
                // Shadow
                if (!empty($styles['shadow']) && !empty($styles['shadow']['enabled'])) {
                    $shadow = $styles['shadow'];
                    $css_rules[] = sprintf(
                        'box-shadow: %s %s %s %s',
                        $shadow['x'] ?? '0',
                        $shadow['y'] ?? '2px',
                        $shadow['blur'] ?? '8px',
                        $shadow['color'] ?? 'rgba(0,0,0,0.1)'
                    );
                }
                
                if (!empty($css_rules)) {
                    $css .= $selector . ' { ' . implode('; ', $css_rules) . '; }';
                }
            }
        }
        
        return $css;
    }
    
    /**
     * Frontend-Scripts rendern
     */
    public function render_frontend_scripts() {
        if (!$this->page_has_container_blocks()) {
            return;
        }
        ?>
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                // Collapse-Funktionalität
                $('.cbd-collapse-toggle').on('click', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var $container = $btn.closest('.cbd-container-block');
                    var $content = $container.find('.cbd-container-content');
                    var state = $btn.data('state');
                    
                    if (state === 'expanded') {
                        $content.slideUp();
                        $btn.data('state', 'collapsed');
                        $btn.find('.dashicons').removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
                    } else {
                        $content.slideDown();
                        $btn.data('state', 'expanded');
                        $btn.find('.dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
                    }
                });
                
                // Copy-Text Funktionalität
                $('.cbd-copy-text').on('click', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var $container = $btn.closest('.cbd-container-block');
                    var text = $container.find('.cbd-container-content').text().trim();
                    
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            var originalText = $btn.text();
                            $btn.text(cbdFrontend.strings.copied);
                            setTimeout(function() {
                                $btn.text(originalText);
                            }, 2000);
                        }).catch(function() {
                            alert(cbdFrontend.strings.copyError);
                        });
                    } else {
                        // Fallback für ältere Browser
                        var $temp = $('<textarea>');
                        $('body').append($temp);
                        $temp.val(text).select();
                        try {
                            document.execCommand('copy');
                            var originalText = $btn.text();
                            $btn.text(cbdFrontend.strings.copied);
                            setTimeout(function() {
                                $btn.text(originalText);
                            }, 2000);
                        } catch(err) {
                            alert(cbdFrontend.strings.copyError);
                        }
                        $temp.remove();
                    }
                });
                
                // Numbering
                $('.cbd-numbering').each(function(index) {
                    var $numbering = $(this);
                    var format = $numbering.data('format');
                    var number = index + 1;
                    var displayNumber = '';
                    
                    switch(format) {
                        case 'alphabetic':
                            displayNumber = String.fromCharCode(64 + number);
                            break;
                        case 'roman':
                            displayNumber = toRoman(number);
                            break;
                        default:
                            displayNumber = number;
                    }
                    
                    $numbering.text(displayNumber + '.');
                });
                
                // Römische Zahlen Konvertierung
                function toRoman(num) {
                    var roman = {
                        M: 1000, CM: 900, D: 500, CD: 400,
                        C: 100, XC: 90, L: 50, XL: 40,
                        X: 10, IX: 9, V: 5, IV: 4, I: 1
                    };
                    var str = '';
                    for (var i of Object.keys(roman)) {
                        var q = Math.floor(num / roman[i]);
                        num -= q * roman[i];
                        str += i.repeat(q);
                    }
                    return str;
                }
            });
        })(jQuery);
        </script>
        <?php
    }
}
        }
        
        // Prüfen ob der Inhalt Container-Blocks enthält
        if (has_block('cbd/container', $post)) {
            return true;
        }
        
        // Prüfen auf dynamische Blocks
        $blocks = CBD_Database::get_blocks(array('status' => 'active'));
        foreach ($blocks as $block) {
            if (has_block('cbd/' . sanitize_title($block['name']), $post)) {
                return true;
            }
        }
        
        return false;