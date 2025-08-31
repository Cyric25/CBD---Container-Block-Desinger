<?php
/**
 * Plugin Name: Container Block Designer
 * Plugin URI: https://example.com/container-block-designer
 * Description: Erstellen und verwalten Sie anpassbare Container-Blöcke für den WordPress Block-Editor
 * Version: 2.5.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: container-block-designer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package ContainerBlockDesigner
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten definieren
define('CBD_VERSION', '2.5.0');
define('CBD_PLUGIN_FILE', __FILE__);
define('CBD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CBD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CBD_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Datenbank-Tabellennamen
global $wpdb;
define('CBD_TABLE_BLOCKS', $wpdb->prefix . 'cbd_blocks');

/**
 * Hauptklasse des Plugins
 */
class ContainerBlockDesigner {
    
    /**
     * Singleton-Instanz
     */
    private static $instance = null;
    
    /**
     * Plugin-Initialisierung
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
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Abhängigkeiten laden
     */
    private function load_dependencies() {
        // Kern-Klassen
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-database.php';
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-block-registration.php';
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-ajax-handler.php';
        
        // Admin-Bereich nur im Backend laden
        if (is_admin()) {
            require_once CBD_PLUGIN_DIR . 'includes/class-cbd-admin.php';
        }
        
        // Frontend-Renderer
        if (!is_admin()) {
            require_once CBD_PLUGIN_DIR . 'includes/class-cbd-frontend-renderer.php';
        }
    }
    
    /**
     * Hooks initialisieren
     */
    private function init_hooks() {
        // Aktivierung/Deaktivierung
        register_activation_hook(CBD_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(CBD_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Initialisierung
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Skripte und Styles
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Block-Kategorien
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
    }
    
    /**
     * Plugin-Aktivierung
     */
    public function activate() {
        // Datenbank-Tabellen erstellen
        CBD_Database::create_tables();
        
        // Standarddaten einfügen
        $this->create_default_block();
        
        // Rewrite-Regeln aktualisieren
        flush_rewrite_rules();
        
        // Aktivierungs-Flag setzen
        update_option('cbd_plugin_activated', true);
    }
    
    /**
     * Plugin-Deaktivierung
     */
    public function deactivate() {
        // Rewrite-Regeln löschen
        flush_rewrite_rules();
        
        // Geplante Events entfernen
        wp_clear_scheduled_hook('cbd_daily_cleanup');
    }
    
    /**
     * Plugin initialisieren
     */
    public function init() {
        // Block-Registrierung
        $block_registration = new CBD_Block_Registration();
        $block_registration->register_blocks();
        
        // AJAX-Handler initialisieren
        new CBD_Ajax_Handler();
        
        // Admin-Bereich initialisieren
        if (is_admin()) {
            new CBD_Admin();
        }
        
        // Prüfen ob Plugin gerade aktiviert wurde
        if (get_option('cbd_plugin_activated')) {
            delete_option('cbd_plugin_activated');
            // Zur Plugin-Seite weiterleiten
            if (is_admin() && !wp_doing_ajax()) {
                wp_safe_redirect(admin_url('admin.php?page=container-block-designer&cbd_activated=1'));
                exit;
            }
        }
    }
    
    /**
     * Textdomain laden
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'container-block-designer',
            false,
            dirname(CBD_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Block-Editor Assets einbinden
     */
    public function enqueue_block_editor_assets() {
        // Block-Editor JavaScript
        wp_enqueue_script(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/js/block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-block-editor'),
            CBD_VERSION,
            true
        );
        
        // Block-Editor Styles
        wp_enqueue_style(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
        
        // Lokalisierung
        wp_localize_script('cbd-block-editor', 'cbdBlockEditor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd-block-editor'),
            'blocks' => $this->get_active_blocks(),
            'pluginUrl' => CBD_PLUGIN_URL,
            'strings' => array(
                'blockTitle' => __('Container Block', 'container-block-designer'),
                'selectBlock' => __('Block auswählen', 'container-block-designer'),
                'noBlocks' => __('Keine Blöcke verfügbar', 'container-block-designer'),
            )
        ));
    }
    
    /**
     * Frontend und Editor Assets einbinden
     */
    public function enqueue_block_assets() {
        // Nur auf Seiten mit Blöcken laden
        if (!is_admin()) {
            // Frontend Styles
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
        }
    }
    
    /**
     * Admin Assets einbinden
     */
    public function enqueue_admin_assets($hook) {
        // Nur auf Plugin-Seiten laden
        if (strpos($hook, 'container-block-designer') === false && 
            strpos($hook, 'cbd-') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CBD_VERSION
        );
        
        // Admin JavaScript
        wp_enqueue_script(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            CBD_VERSION,
            true
        );
        
        // Color Picker
        wp_enqueue_style('wp-color-picker');
        
        // Lokalisierung
        wp_localize_script('cbd-admin', 'cbdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd-admin'),
            'strings' => array(
                'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
                'saved' => __('Gespeichert!', 'container-block-designer'),
                'error' => __('Ein Fehler ist aufgetreten.', 'container-block-designer'),
            )
        ));
    }
    
    /**
     * Block-Kategorie hinzufügen
     */
    public function add_block_category($categories, $post) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug' => 'container-blocks',
                    'title' => __('Container Blocks', 'container-block-designer'),
                    'icon' => 'layout',
                )
            )
        );
    }
    
    /**
     * Aktive Blöcke abrufen
     */
    private function get_active_blocks() {
        global $wpdb;
        
        $blocks = $wpdb->get_results(
            "SELECT id, name, title, config, styles, features 
             FROM " . CBD_TABLE_BLOCKS . " 
             WHERE status = 'active' 
             ORDER BY name ASC",
            ARRAY_A
        );
        
        // JSON-Daten parsen
        foreach ($blocks as &$block) {
            $block['config'] = json_decode($block['config'], true) ?: array();
            $block['styles'] = json_decode($block['styles'], true) ?: array();
            $block['features'] = json_decode($block['features'], true) ?: array();
        }
        
        return $blocks ?: array();
    }
    
    /**
     * Standard-Block erstellen
     */
    private function create_default_block() {
        global $wpdb;
        
        // Prüfen ob bereits Blöcke existieren
        $count = $wpdb->get_var("SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS);
        
        if ($count == 0) {
            // Standard-Block einfügen
            $wpdb->insert(
                CBD_TABLE_BLOCKS,
                array(
                    'name' => 'standard-container',
                    'title' => __('Standard Container', 'container-block-designer'),
                    'description' => __('Ein einfacher Container-Block mit Grundfunktionen', 'container-block-designer'),
                    'config' => json_encode(array(
                        'allowInnerBlocks' => true,
                        'templateLock' => false,
                    )),
                    'styles' => json_encode(array(
                        'padding' => array('top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20),
                        'background' => array('color' => '#ffffff'),
                        'border' => array('width' => 1, 'color' => '#e0e0e0', 'radius' => 4),
                        'text' => array('color' => '#333333', 'alignment' => 'left'),
                    )),
                    'features' => json_encode(array(
                        'icon' => array('enabled' => false),
                        'collapse' => array('enabled' => false),
                        'numbering' => array('enabled' => false),
                    )),
                    'status' => 'active',
                    'created' => current_time('mysql'),
                    'updated' => current_time('mysql'),
                )
            );
        }
    }
}

// Plugin initialisieren
function cbd_init_plugin() {
    return ContainerBlockDesigner::get_instance();
}

// Plugin starten
add_action('plugins_loaded', 'cbd_init_plugin', 10);