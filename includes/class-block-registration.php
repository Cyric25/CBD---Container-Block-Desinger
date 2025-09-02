<?php
/**
 * Container Block Designer - Block Registration
 * Version: 2.5.2
 * 
 * Datei: includes/class-block-registration.php
 * 
 * Diese Klasse kümmert sich um die korrekte Registrierung des Blocks
 * und das Laden der Stile im Editor
 */

// Sicherheit
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Block_Registration {
    
    /**
     * Debug-Modus
     */
    private $debug_mode = false;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Aktiviere Debug-Modus wenn WP_DEBUG aktiv ist
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        // Hooks für Block-Registrierung
        add_action('init', array($this, 'register_block_type'), 10);
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'), 10);
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'), 10);
        
        // Block-Kategorie hinzufügen
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
        
        // REST API Endpoint für Blocks
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // AJAX Handler
        add_action('wp_ajax_cbd_get_blocks', array($this, 'ajax_get_blocks'));
        
        $this->log_debug('Block Registration initialisiert');
    }
    
    /**
     * Debug-Logging
     */
    private function log_debug($message) {
        if ($this->debug_mode) {
            error_log('CBD Block Registration: ' . $message);
        }
    }
    
    /**
     * Block-Typ registrieren
     */
    public function register_block_type() {
        // Stelle sicher, dass die CSS-Dateien existieren
        $this->ensure_css_files_exist();
        
        // Registriere den Block-Typ
        register_block_type('container-block-designer/container', array(
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
                'blockConfig' => array(
                    'type' => 'object',
                    'default' => array()
                ),
                'align' => array(
                    'type' => 'string',
                    'enum' => array('left', 'center', 'right', 'wide', 'full'),
                    'default' => ''
                ),
                'className' => array(
                    'type' => 'string',
                    'default' => ''
                )
            )
        ));
        
        // Registriere Block-Stile serverseitig
        $this->register_block_styles();
        
        $this->log_debug('Block-Typ registriert');
    }
    
    /**
     * Block-Stile registrieren
     */
    private function register_block_styles() {
        // Standard-Stile für den Container-Block
        $styles = array(
            array(
                'name' => 'default',
                'label' => __('Standard', 'container-block-designer'),
                'is_default' => true
            ),
            array(
                'name' => 'boxed',
                'label' => __('Box', 'container-block-designer'),
                'inline_style' => '.wp-block-container-block-designer-container.is-style-boxed { border: 2px solid #e0e0e0; background: #f9f9f9; padding: 30px; }'
            ),
            array(
                'name' => 'rounded',
                'label' => __('Abgerundet', 'container-block-designer'),
                'inline_style' => '.wp-block-container-block-designer-container.is-style-rounded { border-radius: 12px; background: #f5f5f5; padding: 25px; }'
            ),
            array(
                'name' => 'shadow',
                'label' => __('Schatten', 'container-block-designer'),
                'inline_style' => '.wp-block-container-block-designer-container.is-style-shadow { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background: white; padding: 30px; }'
            ),
            array(
                'name' => 'bordered',
                'label' => __('Umrandet', 'container-block-designer'),
                'inline_style' => '.wp-block-container-block-designer-container.is-style-bordered { border: 3px solid #007cba; padding: 25px; }'
            )
        );
        
        // Registriere jeden Stil
        foreach ($styles as $style) {
            if (function_exists('register_block_style')) {
                register_block_style('container-block-designer/container', $style);
            }
        }
        
        $this->log_debug('Block-Stile registriert: ' . count($styles));
    }
    
    /**
     * Block Editor Assets laden
     */
    public function enqueue_block_editor_assets() {
        // Block Editor Script
        wp_enqueue_script(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/js/block-editor.js',
            array(
                'wp-blocks',
                'wp-element',
                'wp-block-editor',
                'wp-components',
                'wp-i18n',
                'wp-data',
                'wp-compose',
                'wp-dom-ready'
            ),
            CBD_VERSION,
            true
        );
        
        // Block Editor Styles
        wp_enqueue_style(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
        
        // Lokalisierung - WICHTIG: ajaxUrl muss gesetzt sein!
        wp_localize_script('cbd-block-editor', 'cbdBlockData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),  // KORRIGIERT: Immer setzen
            'restUrl' => rest_url('cbd/v1/'),
            'nonce' => wp_create_nonce('cbd-nonce'),
            'blocks' => $this->get_available_blocks(),
            'pluginUrl' => CBD_PLUGIN_URL,
            'debug' => $this->debug_mode,
            'i18n' => array(
                'blockTitle' => __('Container Block', 'container-block-designer'),
                'blockDescription' => __('Ein anpassbarer Container-Block mit erweiterten Features', 'container-block-designer'),
                'selectBlock' => __('Design auswählen', 'container-block-designer'),
                'customClasses' => __('Zusätzliche CSS-Klassen', 'container-block-designer'),
                'loading' => __('Lade Designs...', 'container-block-designer'),
                'noBlocks' => __('Keine Designs verfügbar', 'container-block-designer'),
                'error' => __('Fehler beim Laden', 'container-block-designer')
            )
        ));
        
        // Inline Styles für sofortige Verfügbarkeit
        wp_add_inline_style('cbd-block-editor', $this->get_inline_editor_styles());
        
        $this->log_debug('Block Editor Assets geladen');
    }
    
    /**
     * Block Assets (Frontend + Editor) laden
     */
    public function enqueue_block_assets() {
        // Frontend Styles (werden auch im Editor geladen)
        wp_enqueue_style(
            'cbd-block-style',
            CBD_PLUGIN_URL . 'assets/css/block-style.css',
            array(),
            CBD_VERSION
        );
        
        // Inline Styles für Frontend
        if (!is_admin()) {
            wp_add_inline_style('cbd-block-style', $this->get_inline_frontend_styles());
        }
    }
    
    /**
     * Inline Editor Styles
     */
    private function get_inline_editor_styles() {
        return '
            /* Container Block Designer - Inline Editor Styles */
            .wp-block-container-block-designer-container {
                position: relative;
                min-height: 100px;
                padding: 20px;
                margin: 20px 0;
                transition: all 0.3s ease;
            }
            
            .wp-block-container-block-designer-container.is-selected {
                outline: 2px solid #007cba;
                outline-offset: -2px;
            }
            
            /* Stelle sicher, dass Block-Stile sichtbar sind */
            .block-editor-block-styles__item-preview .wp-block-container-block-designer-container {
                min-height: 60px;
                padding: 10px;
            }
            
            /* Style Variationen */
            .wp-block-container-block-designer-container.is-style-boxed {
                border: 2px solid #e0e0e0;
                background: #f9f9f9;
            }
            
            .wp-block-container-block-designer-container.is-style-rounded {
                border-radius: 12px;
                background: #f5f5f5;
            }
            
            .wp-block-container-block-designer-container.is-style-shadow {
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                background: white;
            }
            
            .wp-block-container-block-designer-container.is-style-bordered {
                border: 3px solid #007cba;
            }
        ';
    }
    
    /**
     * Inline Frontend Styles
     */
    private function get_inline_frontend_styles() {
        return '
            /* Container Block Designer - Frontend Styles */
            .wp-block-container-block-designer-container {
                position: relative;
                padding: 20px;
                margin: 20px 0;
            }
            
            .wp-block-container-block-designer-container.is-style-boxed {
                border: 2px solid #e0e0e0;
                background: #f9f9f9;
                padding: 30px;
            }
            
            .wp-block-container-block-designer-container.is-style-rounded {
                border-radius: 12px;
                background: #f5f5f5;
                padding: 25px;
            }
            
            .wp-block-container-block-designer-container.is-style-shadow {
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                background: white;
                padding: 30px;
            }
            
            .wp-block-container-block-designer-container.is-style-bordered {
                border: 3px solid #007cba;
                padding: 25px;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .wp-block-container-block-designer-container {
                    padding: 15px;
                    margin: 15px 0;
                }
            }
        ';
    }
    
    /**
     * Block-Kategorie hinzufügen
     */
    public function add_block_category($categories, $post) {
        return array_merge(
            array(
                array(
                    'slug' => 'container-blocks',
                    'title' => __('Container Blocks', 'container-block-designer'),
                    'icon' => 'layout'
                )
            ),
            $categories
        );
    }
    
    /**
     * REST API Routes registrieren
     */
    public function register_rest_routes() {
        register_rest_route('cbd/v1', '/blocks', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_blocks'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * REST API: Blocks abrufen
     */
    public function rest_get_blocks($request) {
        return rest_ensure_response($this->get_available_blocks());
    }
    
    /**
     * AJAX: Blocks abrufen
     */
    public function ajax_get_blocks() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbd-nonce')) {
            wp_send_json_error('Ungültige Sicherheitsüberprüfung');
            return;
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        $blocks = $this->get_available_blocks();
        wp_send_json_success($blocks);
    }
    
    /**
     * Verfügbare Blocks abrufen
     */
    private function get_available_blocks() {
        global $wpdb;
        
        // Versuche Blocks aus der Datenbank zu laden
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        // Prüfe ob die Tabelle existiert
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            // Hole alle aktiven Blocks aus der Datenbank
            // Beachte: Die Spalte kann 'name' oder 'title' heißen, slug existiert immer
            $blocks = $wpdb->get_results(
                "SELECT 
                    id,
                    COALESCE(title, name) as name,
                    slug,
                    description,
                    config,
                    features,
                    status
                FROM $table_name 
                WHERE status = 'active' 
                ORDER BY COALESCE(title, name) ASC",
                ARRAY_A
            );
            
            // Verarbeite die Blocks für die Ausgabe
            if ($blocks && !empty($blocks)) {
                $processed_blocks = array();
                
                foreach ($blocks as $block) {
                    // Stelle sicher dass slug existiert
                    if (empty($block['slug'])) {
                        $block['slug'] = sanitize_title($block['name']);
                    }
                    
                    // Dekodiere JSON-Felder wenn nötig
                    if (!empty($block['config']) && is_string($block['config'])) {
                        $block['config'] = json_decode($block['config'], true) ?: array();
                    }
                    
                    if (!empty($block['features']) && is_string($block['features'])) {
                        $block['features'] = json_decode($block['features'], true) ?: array();
                    }
                    
                    $processed_blocks[] = array(
                        'id' => $block['id'],
                        'name' => $block['name'],
                        'slug' => $block['slug'],
                        'description' => $block['description'] ?: '',
                        'config' => $block['config'] ?: array(),
                        'features' => $block['features'] ?: array()
                    );
                }
                
                $this->log_debug('Blocks aus Datenbank geladen: ' . count($processed_blocks));
                return $processed_blocks;
            }
        }
        
        $this->log_debug('Keine Blocks in Datenbank gefunden, keine Demo-Blocks zurückgeben');
        
        // Kein Fallback zu Demo-Blocks - zeige leere Liste wenn keine echten Blocks existieren
        return array();
    }
    
    /**
     * Block rendern
     */
    public function render_block($attributes, $content) {
        $selected_block = isset($attributes['selectedBlock']) ? $attributes['selectedBlock'] : '';
        $custom_classes = isset($attributes['customClasses']) ? $attributes['customClasses'] : '';
        $className = isset($attributes['className']) ? $attributes['className'] : '';
        $align = isset($attributes['align']) ? 'align' . $attributes['align'] : '';
        
        // Kombiniere alle Klassen
        $classes = array(
            'wp-block-container-block-designer-container',
            'cbd-container'
        );
        
        if ($selected_block) {
            $classes[] = 'cbd-block-' . sanitize_html_class($selected_block);
        }
        
        if ($custom_classes) {
            $classes[] = esc_attr($custom_classes);
        }
        
        if ($className) {
            $classes[] = esc_attr($className);
        }
        
        if ($align) {
            $classes[] = esc_attr($align);
        }
        
        $class_string = implode(' ', $classes);
        
        // Render Output
        $output = sprintf(
            '<div class="%s" data-block="%s">%s</div>',
            esc_attr($class_string),
            esc_attr($selected_block),
            $content
        );
        
        return $output;
    }
    
    /**
     * Stelle sicher, dass CSS-Dateien existieren
     */
    private function ensure_css_files_exist() {
        $css_dir = CBD_PLUGIN_DIR . 'assets/css/';
        
        // Erstelle Verzeichnis wenn es nicht existiert
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        // Block Editor CSS
        $editor_css = $css_dir . 'block-editor.css';
        if (!file_exists($editor_css)) {
            $editor_content = '/* Container Block Designer - Editor Styles */
.wp-block-container-block-designer-container {
    position: relative;
    min-height: 100px;
    padding: 20px;
}';
            file_put_contents($editor_css, $editor_content);
        }
        
        // Frontend CSS
        $style_css = $css_dir . 'block-style.css';
        if (!file_exists($style_css)) {
            $style_content = '/* Container Block Designer - Frontend Styles */
.wp-block-container-block-designer-container {
    position: relative;
    padding: 20px;
}';
            file_put_contents($style_css, $style_content);
        }
    }
}

// Initialisierung
new CBD_Block_Registration();