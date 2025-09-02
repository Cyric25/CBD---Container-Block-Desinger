<?php
/**
 * Plugin Name: Container Block Designer
 * Plugin URI: https://example.com/container-block-designer
 * Description: Erstellen und verwalten Sie anpassbare Container-Blöcke für den WordPress Block-Editor
 * Version: 2.5.1
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
define('CBD_VERSION', '2.5.1');
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
        if (!is_admin() || wp_doing_ajax()) {
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
        $block_registration = CBD_Block_Registration::get_instance();
        $block_registration->register_blocks();
        
        // AJAX-Handler initialisieren
        new CBD_Ajax_Handler();
        
        // Admin-Bereich initialisieren
        if (is_admin()) {
            CBD_Admin::get_instance();
        }
        
        // Frontend-Renderer initialisieren
        if (!is_admin() || wp_doing_ajax()) {
            if (class_exists('CBD_Frontend_Renderer')) {
                CBD_Frontend_Renderer::init();
            }
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
 * Block Editor Assets laden - KORRIGIERTE VERSION
 */
public function enqueue_block_editor_assets() {
    // Block Editor Script mit korrekter Versionsnummer
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
            'wp-dom-ready'
        ),
        CBD_VERSION, // Verwende die definierte Version
        true
    );
    
    // Block Editor Styles
    wp_enqueue_style(
        'cbd-block-editor',
        CBD_PLUGIN_URL . 'assets/css/block-editor.css',
        array('wp-edit-blocks'),
        CBD_VERSION
    );
    
    // Hole verfügbare Blocks
    $available_blocks = $this->get_available_blocks();
    
    // Lokalisierung mit allen notwendigen Daten
    wp_localize_script('cbd-block-editor', 'cbdBlockData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('cbd/v1/'),
        'nonce' => wp_create_nonce('cbd-nonce'),
        'blocks' => $available_blocks, // Die geladenen Blocks
        'pluginUrl' => CBD_PLUGIN_URL,
        'version' => CBD_VERSION,
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
        'i18n' => array(
            'blockTitle' => __('Container Block', 'container-block-designer'),
            'blockDescription' => __('Ein anpassbarer Container-Block mit erweiterten Features', 'container-block-designer'),
            'selectBlock' => __('Design auswählen', 'container-block-designer'),
            'customClasses' => __('Zusätzliche CSS-Klassen', 'container-block-designer'),
            'loading' => __('Lade Designs...', 'container-block-designer'),
            'noBlocks' => __('Keine Designs verfügbar', 'container-block-designer'),
            'error' => __('Fehler beim Laden', 'container-block-designer'),
            'reload' => __('Designs neu laden', 'container-block-designer')
        )
    ));
    
    error_log('CBD: Block Editor Assets geladen mit ' . count($available_blocks) . ' Blocks');
    } 
    
    /**
     * Frontend und Editor Assets laden
     */
    public function enqueue_block_assets() {
        // Frontend Styles
        wp_enqueue_style(
            'cbd-block-style',
            CBD_PLUGIN_URL . 'assets/css/block-style.css',
            array(),
            CBD_VERSION
        );
    }
    
    /**
     * Admin Assets laden
     */
    public function enqueue_admin_assets($hook) {
        // Nur auf Plugin-Seiten laden
        if (strpos($hook, 'container-block-designer') === false && 
            strpos($hook, 'cbd-') === false) {
            return;
        }
        
        // Admin Styles
        wp_enqueue_style(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CBD_VERSION
        );
        
        // Admin Scripts
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
            'nonce' => wp_create_nonce('cbd-admin-nonce'),
            'strings' => array(
                'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
                'saved' => __('Gespeichert!', 'container-block-designer'),
                'error' => __('Fehler beim Speichern', 'container-block-designer'),
            ),
        ));
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
                    'icon' => 'layout',
                ),
            ),
            $categories
        );
    }
    
/**
 * Verfügbare Blocks abrufen
 */
private function get_available_blocks() {
    global $wpdb;
    
    $blocks_data = array();
    $table_name = CBD_TABLE_BLOCKS;
    
    // Prüfe ob Tabelle existiert
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        // Hole alle aktiven Blocks aus der Datenbank
        $blocks = $wpdb->get_results(
            "SELECT 
                id,
                COALESCE(title, name) as name,
                COALESCE(slug, LOWER(REPLACE(COALESCE(title, name), ' ', '-'))) as slug,
                description,
                config,
                features,
                status
            FROM $table_name 
            WHERE status = 'active' OR status IS NULL
            ORDER BY id ASC",
            ARRAY_A
        );
        
        if ($blocks && is_array($blocks)) {
            foreach ($blocks as $block) {
                // Sichere JSON-Dekodierung mit NULL-Check
                $config = null;
                $features = null;
                
                if (!empty($block['config']) && is_string($block['config'])) {
                    $decoded = json_decode($block['config'], true);
                    $config = (json_last_error() === JSON_ERROR_NONE) ? $decoded : array();
                } else {
                    $config = array();
                }
                
                if (!empty($block['features']) && is_string($block['features'])) {
                    $decoded = json_decode($block['features'], true);
                    $features = (json_last_error() === JSON_ERROR_NONE) ? $decoded : array();
                } else {
                    $features = array();
                }
                
                // Stelle sicher dass slug existiert
                $slug = !empty($block['slug']) ? $block['slug'] : 'block-' . $block['id'];
                
                $blocks_data[] = array(
                    'id' => intval($block['id']),
                    'name' => esc_html($block['name'] ?: 'Block ' . $block['id']),
                    'slug' => sanitize_title($slug),
                    'description' => esc_html($block['description'] ?: ''),
                    'config' => $config,
                    'features' => $features
                );
                
                error_log('CBD: Block gefunden - ' . $block['name'] . ' (slug: ' . $slug . ')');
            }
        }
    }
    
    error_log('CBD: Insgesamt ' . count($blocks_data) . ' Blocks geladen');
    
    return $blocks_data;
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