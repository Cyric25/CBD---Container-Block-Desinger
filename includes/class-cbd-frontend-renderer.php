<?php
/**
 * Container Block Designer - Frontend Renderer
 * Version: 2.5.2
 * 
 * Datei: includes/class-cbd-frontend-renderer.php
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Renderer Klasse
 */
class CBD_Frontend_Renderer {
    
    /**
     * Initialisierung
     */
    public static function init() {
        // Render Callback registrieren
        add_action('init', array(__CLASS__, 'register_render_callback'), 20);
        
        // Frontend Assets laden
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
    }
    
    /**
     * Render Callback registrieren
     */
    public static function register_render_callback() {
        // Prüfe ob Block bereits registriert ist
        if (!WP_Block_Type_Registry::get_instance()->is_registered('container-block-designer/container')) {
            register_block_type('container-block-designer/container', array(
                'render_callback' => array(__CLASS__, 'render_block')
            ));
        }
    }
    
    /**
     * Block rendern
     */
    public static function render_block($attributes, $content) {
        // Falls CBD_Block_Registration verfügbar ist, dessen Render-Methode verwenden
        if (class_exists('CBD_Block_Registration')) {
            $registration = CBD_Block_Registration::get_instance();
            if (method_exists($registration, 'render_block')) {
                return $registration->render_block($attributes, $content);
            }
        }
        
        // Fallback Rendering
        return self::fallback_render($attributes, $content);
    }
    
    /**
     * Fallback Rendering
     */
    private static function fallback_render($attributes, $content) {
        $selected_block = $attributes['selectedBlock'] ?? '';
        $custom_classes = $attributes['customClasses'] ?? '';
        $align = $attributes['align'] ?? '';
        
        // CSS-Klassen aufbauen
        $classes = array(
            'wp-block-container-block-designer-container',
            'cbd-container'
        );
        
        if ($selected_block) {
            $classes[] = 'cbd-container-' . sanitize_html_class($selected_block);
        }
        
        if ($custom_classes) {
            $classes[] = esc_attr($custom_classes);
        }
        
        if ($align) {
            $classes[] = 'align' . $align;
        }
        
        // HTML generieren
        $html = sprintf(
            '<div class="%s">',
            esc_attr(implode(' ', $classes))
        );
        
        $html .= '<div class="cbd-container-content">';
        $html .= $content;
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Frontend Assets laden
     */
    public static function enqueue_frontend_assets() {
        // Prüfe ob interaktive Features verwendet werden
        if (self::has_interactive_features()) {
            // Frontend JavaScript
            wp_enqueue_script(
                'cbd-frontend',
                CBD_PLUGIN_URL . 'assets/js/block-frontend.js',
                array('jquery'),
                CBD_VERSION,
                true
            );
            
            // Lokalisierung
            wp_localize_script('cbd-frontend', 'cbdFrontend', array(
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
     * Prüfen ob interaktive Features vorhanden sind
     */
    private static function has_interactive_features() {
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
}