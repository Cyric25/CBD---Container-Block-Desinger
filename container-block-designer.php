<?php
/**
 * Plugin Name: Container Block Designer
 * Plugin URI: https://example.com/container-block-designer
 * Description: Erstellen und verwalten Sie anpassbare Container-Blöcke für den WordPress Block-Editor
 * Version: 2.5.2
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
define('CBD_VERSION', '2.5.2');
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
     * Style Loader Instanz
     */
    private $style_loader = null;
    
    /**
     * Block Registration Instanz
     */
    private $block_registration = null;
    
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
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-style-loader.php';
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
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Block-Kategorien
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
        
        // Admin Notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Plugin-Aktivierung
     */
    public function activate() {
        // Datenbank-Tabellen erstellen
        CBD_Database::create_tables();
        
        // Standarddaten einfügen
        $this->create_default_blocks();
        
        // Upload-Verzeichnis erstellen
        $this->create_upload_directory();
        
        // Rewrite-Regeln aktualisieren
        flush_rewrite_rules();
        
        // Aktivierungs-Flag setzen
        update_option('cbd_plugin_activated', true);
        update_option('cbd_plugin_version', CBD_VERSION);
    }
    
    /**
     * Plugin-Deaktivierung
     */
    public function deactivate() {
        // Rewrite-Regeln löschen
        flush_rewrite_rules();
        
        // Geplante Events entfernen
        wp_clear_scheduled_hook('cbd_daily_cleanup');
        
        // Cache leeren
        if ($this->style_loader) {
            $this->style_loader->clear_styles_cache();
        }
    }
    
    /**
     * Plugin initialisieren
     */
    public function init() {
        // Style Loader initialisieren (muss zuerst kommen)
        $this->style_loader = CBD_Style_Loader::get_instance();
        
        // Block-Registrierung
        $this->block_registration = CBD_Block_Registration::get_instance();
        $this->block_registration->register_blocks();
        
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
        
        // Version-Update prüfen
        $this->check_version_update();
    }
    
    /**
     * Version-Update prüfen und durchführen
     */
    private function check_version_update() {
        $current_version = get_option('cbd_plugin_version', '0');
        
        if (version_compare($current_version, CBD_VERSION, '<')) {
            // Update-Routinen
            $this->run_updates($current_version);
            
            // Version aktualisieren
            update_option('cbd_plugin_version', CBD_VERSION);
            
            // Style-Cache leeren
            if ($this->style_loader) {
                $this->style_loader->clear_styles_cache();
            }
        }
    }
    
    /**
     * Update-Routinen ausführen
     */
    private function run_updates($from_version) {
        // Updates für Version 2.5.0
        if (version_compare($from_version, '2.5.0', '<')) {
            // Datenbank-Schema aktualisieren
            // Prüfen ob die Methode existiert
            if (class_exists('CBD_Database') && method_exists('CBD_Database', 'update_schema')) {
                CBD_Database::update_schema();
            } else {
                // Alternative: Tabellen neu erstellen falls nötig
                CBD_Database::create_tables();
            }
        }
        
        // Updates für Version 2.5.2
        if (version_compare($from_version, '2.5.2', '<')) {
            // Alte CSS-Dateien löschen
            $this->cleanup_old_css_files();
            
            // Cache-Verzeichnis erstellen
            $this->create_upload_directory();
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
     * Admin Notices anzeigen
     */
    public function admin_notices() {
        // Aktivierungs-Notice
        if (isset($_GET['cbd_activated']) && $_GET['cbd_activated'] == '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Container Block Designer wurde erfolgreich aktiviert!', 'container-block-designer'); ?></p>
                <p><?php printf(
                    __('Erstellen Sie Ihren ersten Container-Block <a href="%s">hier</a>.', 'container-block-designer'),
                    admin_url('admin.php?page=cbd-new-block')
                ); ?></p>
            </div>
            <?php
        }
        
        // Update-Notice
        if (isset($_GET['cbd_updated']) && $_GET['cbd_updated'] == '1') {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><?php printf(
                    __('Container Block Designer wurde auf Version %s aktualisiert.', 'container-block-designer'),
                    CBD_VERSION
                ); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Standard-Blocks erstellen
     */
    private function create_default_blocks() {
        global $wpdb;
        
        // Prüfe ob bereits Blocks existieren
        $count = $wpdb->get_var("SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS);
        
        if ($count > 0) {
            return; // Bereits Blocks vorhanden
        }
        
        // Standard-Blocks definieren
        $default_blocks = array(
            array(
                'name' => __('Einfacher Container', 'container-block-designer'),
                'slug' => 'simple-container',
                'description' => __('Ein einfacher Container mit Rahmen und Padding', 'container-block-designer'),
                'config' => json_encode(array(
                    'allowInnerBlocks' => true,
                    'maxWidth' => '100%',
                    'minHeight' => '100px'
                )),
                'styles' => json_encode(array(
                    'padding' => array(
                        'top' => 20,
                        'right' => 20,
                        'bottom' => 20,
                        'left' => 20
                    ),
                    'background' => array(
                        'color' => '#ffffff'
                    ),
                    'border' => array(
                        'width' => 1,
                        'style' => 'solid',
                        'color' => '#e0e0e0',
                        'radius' => 4
                    )
                )),
                'features' => json_encode(array()),
                'status' => 'active'
            ),
            array(
                'name' => __('Info-Box', 'container-block-designer'),
                'slug' => 'info-box',
                'description' => __('Eine Info-Box mit Icon und blauem Hintergrund', 'container-block-designer'),
                'config' => json_encode(array(
                    'allowInnerBlocks' => true,
                    'maxWidth' => '100%',
                    'minHeight' => '80px'
                )),
                'styles' => json_encode(array(
                    'padding' => array(
                        'top' => 15,
                        'right' => 20,
                        'bottom' => 15,
                        'left' => 50
                    ),
                    'background' => array(
                        'color' => '#e3f2fd'
                    ),
                    'border' => array(
                        'width' => 0,
                        'radius' => 4
                    ),
                    'typography' => array(
                        'color' => '#1565c0'
                    )
                )),
                'features' => json_encode(array(
                    'icon' => array(
                        'enabled' => true,
                        'value' => 'dashicons-info',
                        'position' => 'left',
                        'color' => '#1565c0'
                    )
                )),
                'status' => 'active'
            ),
            array(
                'name' => __('Klappbarer Container', 'container-block-designer'),
                'slug' => 'collapsible-container',
                'description' => __('Ein Container der ein- und ausgeklappt werden kann', 'container-block-designer'),
                'config' => json_encode(array(
                    'allowInnerBlocks' => true,
                    'maxWidth' => '100%',
                    'minHeight' => '60px'
                )),
                'styles' => json_encode(array(
                    'padding' => array(
                        'top' => 15,
                        'right' => 15,
                        'bottom' => 15,
                        'left' => 15
                    ),
                    'background' => array(
                        'color' => '#f5f5f5'
                    ),
                    'border' => array(
                        'width' => 1,
                        'style' => 'solid',
                        'color' => '#d0d0d0',
                        'radius' => 4
                    )
                )),
                'features' => json_encode(array(
                    'collapsible' => array(
                        'enabled' => true,
                        'defaultState' => 'expanded'
                    )
                )),
                'status' => 'active'
            )
        );
        
        // Blocks in Datenbank einfügen
        foreach ($default_blocks as $block) {
            $block['created_at'] = current_time('mysql');
            $block['updated_at'] = current_time('mysql');
            $wpdb->insert(CBD_TABLE_BLOCKS, $block);
        }
    }
    
    /**
     * Upload-Verzeichnis erstellen
     */
    private function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $cbd_dir = $upload_dir['basedir'] . '/container-block-designer';
        
        if (!file_exists($cbd_dir)) {
            wp_mkdir_p($cbd_dir);
            
            // .htaccess für Sicherheit
            $htaccess = $cbd_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\n");
            }
        }
    }
    
    /**
     * Alte CSS-Dateien aufräumen
     */
    private function cleanup_old_css_files() {
        $css_files = array(
            CBD_PLUGIN_DIR . 'assets/css/block-style.css',
            CBD_PLUGIN_DIR . 'assets/css/block-editor.css'
        );
        
        foreach ($css_files as $file) {
            if (file_exists($file)) {
                // Backup erstellen
                $backup = $file . '.backup-' . date('Ymd-His');
                rename($file, $backup);
            }
        }
    }
    
    /**
     * Verfügbare Blocks für globale Verwendung
     */
    public function get_available_blocks() {
        if ($this->block_registration) {
            return $this->block_registration->get_available_blocks();
        }
        return array();
    }
}

// Plugin initialisieren
add_action('plugins_loaded', function() {
    ContainerBlockDesigner::get_instance();
});

// Globale Hilfsfunktion
if (!function_exists('cbd_get_blocks')) {
    function cbd_get_blocks() {
        $cbd = ContainerBlockDesigner::get_instance();
        return $cbd->get_available_blocks();
    }
}