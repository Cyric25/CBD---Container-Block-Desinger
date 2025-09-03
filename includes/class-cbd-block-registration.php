<?php
/**
 * Container Block Designer - Block Registration
 * Verbesserte Version mit Style Loader Integration
 * Version: 2.5.2
 * 
 * Datei: includes/class-cbd-block-registration.php
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
     * Style Loader Instanz
     */
    private $style_loader = null;
    
    /**
     * Debug-Modus
     */
    private $debug_mode = false;
    
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
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        // Style Loader initialisieren
        if (file_exists(CBD_PLUGIN_DIR . 'includes/class-cbd-style-loader.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/class-cbd-style-loader.php';
            $this->style_loader = CBD_Style_Loader::get_instance();
        }
    }
    
    /**
     * Blocks registrieren
     */
    public function register_blocks() {
        // Block-Skripte registrieren
        $this->register_block_scripts();
        
        // Block-Typ registrieren
        $this->register_block_type();
        
        // REST API Endpunkte registrieren
        $this->register_rest_routes();
        
        $this->log_debug('Blocks registered');
    }
    
    /**
     * Block-Skripte registrieren
     */
    private function register_block_scripts() {
        // Block Editor JavaScript
        wp_register_script(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/js/block-editor.js',
            array(
                'wp-blocks',
                'wp-element',
                'wp-editor',
                'wp-components',
                'wp-i18n',
                'wp-block-editor',
                'wp-data'
            ),
            CBD_VERSION,
            true
        );
        
        // Frontend JavaScript (wenn Features es benötigen)
        if ($this->has_interactive_features()) {
            wp_register_script(
                'cbd-block-frontend',
                CBD_PLUGIN_URL . 'assets/js/block-frontend.js',
                array('jquery'),
                CBD_VERSION,
                true
            );
        }
        
        // Lokalisierung
        $this->localize_scripts();
    }
    
    /**
     * Block-Typ registrieren
     */
    private function register_block_type() {
        // Style Loader kümmert sich um CSS
        // Wir registrieren nur den Block-Typ
        
        register_block_type('container-block-designer/container', array(
            'editor_script' => 'cbd-block-editor',
            'script' => $this->has_interactive_features() ? 'cbd-block-frontend' : null,
            'render_callback' => array($this, 'render_block'),
            'attributes' => $this->get_block_attributes(),
            'supports' => $this->get_block_supports()
        ));
        
        $this->log_debug('Block type registered');
    }
    
    /**
     * Block-Attribute definieren
     */
    private function get_block_attributes() {
        return array(
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
            'blockFeatures' => array(
                'type' => 'object',
                'default' => array()
            ),
            'align' => array(
                'type' => 'string',
                'enum' => array('', 'wide', 'full'),
                'default' => ''
            ),
            'anchor' => array(
                'type' => 'string',
                'default' => ''
            )
        );
    }
    
    /**
     * Block-Supports definieren
     */
    private function get_block_supports() {
        return array(
            'html' => false,
            'className' => true,
            'anchor' => true,
            'align' => array('wide', 'full'),
            'spacing' => array(
                'margin' => true,
                'padding' => true,
                'blockGap' => true
            ),
            'color' => array(
                'background' => true,
                'text' => true,
                'link' => true
            ),
            '__experimentalBorder' => array(
                'color' => true,
                'radius' => true,
                'style' => true,
                'width' => true
            )
        );
    }
    
    /**
     * Skripte lokalisieren
     */
    private function localize_scripts() {
        $localization_data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('cbd/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'blocks' => $this->get_available_blocks(),
            'pluginUrl' => CBD_PLUGIN_URL,
            'debug' => $this->debug_mode,
            'stylesVersion' => get_option('cbd_styles_version', CBD_VERSION),
            'i18n' => array(
                'blockTitle' => __('Container Block', 'container-block-designer'),
                'blockDescription' => __('Ein anpassbarer Container-Block', 'container-block-designer'),
                'selectBlock' => __('Block-Design auswählen', 'container-block-designer'),
                'customClasses' => __('Zusätzliche CSS-Klassen', 'container-block-designer'),
                'loading' => __('Lade Blocks...', 'container-block-designer'),
                'noBlocks' => __('Keine Blocks verfügbar', 'container-block-designer'),
                'settings' => __('Einstellungen', 'container-block-designer'),
                'design' => __('Design', 'container-block-designer'),
                'features' => __('Features', 'container-block-designer')
            )
        );
        
        wp_localize_script('cbd-block-editor', 'cbdBlockData', $localization_data);
        
        if ($this->has_interactive_features()) {
            wp_localize_script('cbd-block-frontend', 'cbdFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cbd-frontend'),
                'i18n' => array(
                    'copySuccess' => __('Text kopiert!', 'container-block-designer'),
                    'copyError' => __('Kopieren fehlgeschlagen', 'container-block-designer'),
                    'collapsed' => __('Eingeklappt', 'container-block-designer'),
                    'expanded' => __('Ausgeklappt', 'container-block-designer'),
                    'screenshot' => __('Screenshot', 'container-block-designer'),
                    'creating' => __('Erstelle...', 'container-block-designer'),
                    'screenshotSuccess' => __('Screenshot erstellt!', 'container-block-designer'),
                    'screenshotError' => __('Screenshot fehlgeschlagen', 'container-block-designer'),
                    'screenshotUnavailable' => __('Screenshot nicht verfügbar', 'container-block-designer'),
                    'noTextFound' => __('Kein Text gefunden', 'container-block-designer')
                )
            ));
        }
    }
    
    /**
     * Verfügbare Blocks abrufen
     */
    public function get_available_blocks() {
        global $wpdb;
        
        $blocks = $wpdb->get_results(
            "SELECT id, name, slug, description, config, styles, features 
             FROM " . CBD_TABLE_BLOCKS . " 
             WHERE status = 'active' 
             ORDER BY name ASC",
            ARRAY_A
        );
        
        if (!$blocks) {
            return array();
        }
        
        // Daten für JavaScript aufbereiten
        $formatted_blocks = array();
        foreach ($blocks as $block) {
    // Sichere JSON-Dekodierung
    $config = array();
    $styles = array();
    $features = array();
    
    if (!empty($block['config'])) {
        $decoded = @json_decode($block['config'], true);
        if ($decoded !== null) {
            $config = $decoded;
        }
    }
    
    if (!empty($block['styles'])) {
        $decoded = @json_decode($block['styles'], true);
        if ($decoded !== null) {
            $styles = $decoded;
        }
    }
    
    if (!empty($block['features'])) {
        $decoded = @json_decode($block['features'], true);
        if ($decoded !== null) {
            $features = $decoded;
        }
    }
    
    $formatted_blocks[] = array(
        'id' => $block['id'],
        'name' => $block['name'],
        'slug' => $block['slug'],
        'description' => $block['description'],
        'config' => $config,
        'styles' => $styles,
        'features' => $features
    );
}
        
        return $formatted_blocks;
    }
    
    /**
     * Block rendern
     */
    public function render_block($attributes, $content) {
        $selected_block = $attributes['selectedBlock'] ?? '';
        
        if (empty($selected_block)) {
            // Kein Block ausgewählt - Platzhalter im Editor anzeigen
            if (is_admin()) {
                return '<div class="cbd-block-placeholder">' . 
                       __('Bitte wählen Sie ein Container-Design aus den Block-Einstellungen.', 'container-block-designer') . 
                       '</div>';
            }
            return ''; // Nichts im Frontend anzeigen
        }
        
        // Block-Daten aus DB laden
        $block_data = $this->get_block_data($selected_block);
        
        if (!$block_data) {
            return '<div class="cbd-block-error">' . 
                   __('Container-Design nicht gefunden.', 'container-block-designer') . 
                   '</div>';
        }
        
        // HTML generieren
        return $this->generate_block_html($attributes, $content, $block_data);
    }
    
    /**
     * Block-Daten aus Datenbank abrufen
     */
    private function get_block_data($slug) {
        global $wpdb;
        
        $cache_key = 'cbd_block_' . $slug;
        $block_data = wp_cache_get($cache_key);
        
        if (false === $block_data) {
            $block_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " 
                 WHERE slug = %s AND status = 'active'",
                $slug
            ), ARRAY_A);
            
            if ($block_data) {
                // Sicheres Dekodieren der JSON-Felder
                $config_data = array();
                $styles_data = array();
                $features_data = array();
                
                if (!empty($block_data['config']) && is_string($block_data['config'])) {
                    $decoded = json_decode($block_data['config'], true);
                    if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                        $config_data = $decoded;
                    }
                }
                
                if (!empty($block_data['styles']) && is_string($block_data['styles'])) {
                    $decoded = json_decode($block_data['styles'], true);
                    if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                        $styles_data = $decoded;
                    }
                }
                
                if (!empty($block_data['features']) && is_string($block_data['features'])) {
                    $decoded = json_decode($block_data['features'], true);
                    if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                        $features_data = $decoded;
                    }
                }
                
                $block_data['config'] = $config_data;
                $block_data['styles'] = $styles_data;
                $block_data['features'] = $features_data;
                
                wp_cache_set($cache_key, $block_data, '', HOUR_IN_SECONDS);
            }
        }
        
        return $block_data;
    }
    
    /**
     * Block-HTML generieren
     */
    private function generate_block_html($attributes, $content, $block_data) {
        // Attribute extrahieren
        $custom_classes = $attributes['customClasses'] ?? '';
        $align = $attributes['align'] ?? '';
        $anchor = $attributes['anchor'] ?? '';
        $block_config = $attributes['blockConfig'] ?? array();
        $block_features = $attributes['blockFeatures'] ?? array();
        
        // Config und Features mergen
        $config = array_merge($block_data['config'], $block_config);
        $features = array_merge($block_data['features'], $block_features);
        
        // CSS-Klassen aufbauen
        $classes = array(
            'wp-block-container-block-designer-container',
            'cbd-container',
            'cbd-container-' . sanitize_html_class($block_data['slug'])
        );
        
        if ($custom_classes) {
            $classes[] = esc_attr($custom_classes);
        }
        
        if ($align) {
            $classes[] = 'align' . $align;
        }
        
        // Feature-Klassen hinzufügen
        if (!empty($features['collapsible']['enabled'])) {
            $classes[] = 'cbd-collapsible';
        }
        
        if (!empty($features['icon']['enabled'])) {
            $classes[] = 'cbd-has-icon';
        }
        
        if (!empty($features['numbering']['enabled'])) {
            $classes[] = 'cbd-has-numbering';
        }
        
        // Container-Attribute
        $container_attrs = array(
            'class' => implode(' ', $classes)
        );
        
        if ($anchor) {
            $container_attrs['id'] = esc_attr($anchor);
        }
        
        // Data-Attribute für JavaScript
        if (!empty($features)) {
            $container_attrs['data-features'] = esc_attr(json_encode($features));
        }
        
        // HTML aufbauen
        $html = sprintf(
            '<div %s>',
            $this->build_attributes($container_attrs)
        );
        
        // Features rendern
        $html .= $this->render_features($features, $block_data['slug']);
        
        // Inhalt
        $html .= '<div class="cbd-container-content">';
        $html .= $content;
        $html .= '</div>';
        
        // Action Buttons
        $html .= $this->render_action_buttons($features);
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Features rendern
     */
    private function render_features($features, $block_slug) {
        $html = '';
        
        // Icon
        if (!empty($features['icon']['enabled'])) {
            $icon = $features['icon'];
            $icon_class = $icon['value'] ?? 'dashicons-admin-generic';
            $position = $icon['position'] ?? 'top-left';
            
            $html .= sprintf(
                '<span class="cbd-icon cbd-icon-%s dashicons %s" aria-hidden="true"></span>',
                esc_attr($position),
                esc_attr($icon_class)
            );
        }
        
        // Numbering
        if (!empty($features['numbering']['enabled'])) {
            $numbering = $features['numbering'];
            $format = $numbering['format'] ?? 'numeric';
            $position = $numbering['position'] ?? 'top-left';
            
            // Counter erhöhen
            static $counter = array();
            if (!isset($counter[$block_slug])) {
                $counter[$block_slug] = 0;
            }
            $counter[$block_slug]++;
            
            $number = $this->format_number($counter[$block_slug], $format);
            
            $html .= sprintf(
                '<span class="cbd-number cbd-number-%s">%s</span>',
                esc_attr($position),
                esc_html($number)
            );
        }
        
        // Collapsible Toggle
        if (!empty($features['collapsible']['enabled'])) {
            $html .= '<button class="cbd-collapse-toggle" aria-label="' . 
                     esc_attr__('Ein-/Ausklappen', 'container-block-designer') . 
                     '"></button>';
        }
        
        return $html;
    }
    
    /**
     * Action Buttons rendern
     */
    private function render_action_buttons($features) {
        $html = '';
        $has_buttons = false;
        
        // Copy Button
        if (!empty($features['copy']['enabled'])) {
            $has_buttons = true;
            $html .= '<button class="cbd-copy-button" data-action="copy">' .
                     esc_html__('Kopieren', 'container-block-designer') .
                     '</button>';
        }
        
        // Screenshot Button
        if (!empty($features['screenshot']['enabled'])) {
            $has_buttons = true;
            $html .= '<button class="cbd-screenshot-button" data-action="screenshot">' .
                     esc_html__('Screenshot', 'container-block-designer') .
                     '</button>';
        }
        
        if ($has_buttons) {
            return '<div class="cbd-action-buttons">' . $html . '</div>';
        }
        
        return '';
    }
    
    /**
     * Nummer formatieren
     */
    private function format_number($number, $format) {
        switch ($format) {
            case 'roman':
                return $this->to_roman($number);
            case 'letters':
                return $this->to_letters($number);
            case 'leading-zero':
                return str_pad($number, 2, '0', STR_PAD_LEFT);
            default:
                return $number;
        }
    }
    
    /**
     * Zahl zu römischen Ziffern
     */
    private function to_roman($number) {
        $map = array(
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        );
        
        $result = '';
        foreach ($map as $roman => $value) {
            $matches = intval($number / $value);
            $result .= str_repeat($roman, $matches);
            $number = $number % $value;
        }
        
        return $result;
    }
    
    /**
     * Zahl zu Buchstaben
     */
    private function to_letters($number) {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)) . $letters;
            $number = intval($number / 26);
        }
        return $letters;
    }
    
    /**
     * HTML-Attribute aufbauen
     */
    private function build_attributes($attrs) {
        $output = '';
        foreach ($attrs as $key => $value) {
            if ($value !== null && $value !== '') {
                $output .= sprintf(' %s="%s"', $key, esc_attr($value));
            }
        }
        return $output;
    }
    
    /**
     * Prüfen ob interaktive Features vorhanden sind
     */
    private function has_interactive_features() {
        global $wpdb;
        
        // Cache prüfen
        $cache_key = 'cbd_has_interactive_features';
        $has_features = wp_cache_get($cache_key);
        
        if (false === $has_features) {
            // Prüfe ob irgendein Block interaktive Features hat
            $result = $wpdb->get_var(
                "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " 
                 WHERE status = 'active' 
                 AND (features LIKE '%collapsible%' 
                      OR features LIKE '%copy%' 
                      OR features LIKE '%screenshot%')"
            );
            
            $has_features = $result > 0;
            wp_cache_set($cache_key, $has_features, '', HOUR_IN_SECONDS);
        }
        
        return $has_features;
    }
    
    /**
     * REST API Routes registrieren
     */
    private function register_rest_routes() {
        add_action('rest_api_init', function() {
            // Blocks abrufen
            register_rest_route('cbd/v1', '/blocks', array(
                'methods' => 'GET',
                'callback' => array($this, 'rest_get_blocks'),
                'permission_callback' => '__return_true'
            ));
            
            // Block-Details abrufen
            register_rest_route('cbd/v1', '/blocks/(?P<slug>[a-z0-9-]+)', array(
                'methods' => 'GET',
                'callback' => array($this, 'rest_get_block'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'slug' => array(
                        'validate_callback' => function($param) {
                            return preg_match('/^[a-z0-9-]+$/', $param);
                        }
                    )
                )
            ));
            
            // Styles aktualisieren (Admin)
            register_rest_route('cbd/v1', '/refresh-styles', array(
                'methods' => 'POST',
                'callback' => array($this, 'rest_refresh_styles'),
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                }
            ));
        });
    }
    
    /**
     * REST: Alle Blocks abrufen
     */
    public function rest_get_blocks($request) {
        $blocks = $this->get_available_blocks();
        
        return new WP_REST_Response($blocks, 200);
    }
    
    /**
     * REST: Einzelnen Block abrufen
     */
    public function rest_get_block($request) {
        $slug = $request->get_param('slug');
        $block = $this->get_block_data($slug);
        
        if (!$block) {
            return new WP_Error(
                'block_not_found',
                __('Block nicht gefunden', 'container-block-designer'),
                array('status' => 404)
            );
        }
        
        return new WP_REST_Response($block, 200);
    }
    
    /**
     * REST: Styles aktualisieren
     */
    public function rest_refresh_styles($request) {
        // Style Cache leeren
        if ($this->style_loader) {
            $this->style_loader->clear_styles_cache();
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Styles wurden aktualisiert', 'container-block-designer')
        ), 200);
    }
    
    /**
     * Debug-Log
     */
    private function log_debug($message) {
        if ($this->debug_mode) {
            error_log('[CBD Block Registration] ' . print_r($message, true));
        }
    }
}

// Block Registration initialisieren
add_action('init', function() {
    CBD_Block_Registration::get_instance();
}, 5);