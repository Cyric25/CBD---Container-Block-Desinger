<?php
/**
 * Plugin Name: Container Block Designer
 * Plugin URI: https://example.com/container-block-designer
 * Description: Erstellen und verwalten Sie anpassbare Container-Blöcke für den WordPress Block-Editor
 * Version: 2.5.3
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
define('CBD_VERSION', '2.5.3');
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
        
        // Frontend-Renderer - Prüfe ob Datei existiert
        if (file_exists(CBD_PLUGIN_DIR . 'includes/class-cbd-frontend-renderer.php')) {
            if (!is_admin() || wp_doing_ajax()) {
                require_once CBD_PLUGIN_DIR . 'includes/class-cbd-frontend-renderer.php';
            }
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
        
        // Admin
        if (is_admin()) {
            add_action('admin_init', array($this, 'check_version'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }
        
        // Block Editor
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
        
        // Frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        
        // Style Loader initialisieren (Singleton)
        if (class_exists('CBD_Style_Loader')) {
            $this->style_loader = CBD_Style_Loader::get_instance();
        }
        
        // Block Registration initialisieren (Singleton)
        if (class_exists('CBD_Block_Registration')) {
            $this->block_registration = CBD_Block_Registration::get_instance();
        }
        
        // AJAX Handler initialisieren
        if (class_exists('CBD_Ajax_Handler')) {
            new CBD_Ajax_Handler();
        }
        
        // Admin initialisieren
        if (is_admin() && class_exists('CBD_Admin')) {
            CBD_Admin::get_instance();
        }
        
        // Frontend Renderer initialisieren
        if ((!is_admin() || wp_doing_ajax()) && class_exists('CBD_Frontend_Renderer')) {
            new CBD_Frontend_Renderer();
        }
    }
    
    /**
     * Plugin-Initialisierung
     */
    public function init() {
        // Block registrieren
        if ($this->block_registration) {
            $this->block_registration->register_blocks();
        }
        
        // REST API Endpunkte registrieren
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Plugin aktivieren
     */
    public function activate() {
        // Datenbank-Tabellen erstellen
        if (class_exists('CBD_Database')) {
            CBD_Database::create_tables();
        }
        
        // Capabilities hinzufügen
        $this->add_capabilities();
        
        // Standard-Blocks erstellen
        $this->create_default_blocks();
        
        // Upload-Verzeichnis erstellen
        $this->create_upload_directory();
        
        // Aktivierungs-Flag setzen
        set_transient('cbd_activated', true, 5);
        
        // Plugin-Version speichern
        update_option('cbd_plugin_version', CBD_VERSION);
        
        // Cache leeren
        wp_cache_flush();
    }
    
    /**
     * Plugin deaktivieren
     */
    public function deactivate() {
        // Transients löschen
        delete_transient('cbd_activated');
        
        // Scheduled Events entfernen
        wp_clear_scheduled_hook('cbd_cleanup_old_styles');
        
        // Cache leeren
        wp_cache_flush();
    }
    
    /**
     * Capabilities hinzufügen
     */
    private function add_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_container_blocks');
            $role->add_cap('edit_container_blocks');
            $role->add_cap('delete_container_blocks');
        }
        
        $role = get_role('editor');
        if ($role) {
            $role->add_cap('edit_container_blocks');
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
            return; // Blocks existieren bereits
        }
        
        // Standard-Blocks definieren
        $default_blocks = array(
            array(
                'name' => 'info-box',
                'title' => __('Info Box', 'container-block-designer'),
                'description' => __('Eine einfache Info-Box mit blauem Rahmen', 'container-block-designer'),
                'config' => json_encode(array(
                    'allowInnerBlocks' => true,
                    'templateLock' => false
                )),
                'styles' => json_encode(array(
                    'padding' => array(
                        'top' => 20,
                        'right' => 20,
                        'bottom' => 20,
                        'left' => 20
                    ),
                    'background' => array(
                        'color' => '#e3f2fd'
                    ),
                    'border' => array(
                        'width' => 2,
                        'style' => 'solid',
                        'color' => '#2196f3',
                        'radius' => 4
                    ),
                    'text' => array(
                        'color' => '#0d47a1',
                        'alignment' => 'left'
                    )
                )),
                'features' => json_encode(array(
                    'icon' => array(
                        'enabled' => true,
                        'value' => 'info'
                    ),
                    'collapse' => array(
                        'enabled' => false,
                        'value' => ''
                    )
                )),
                'status' => 'active'
            ),
            array(
                'name' => 'warning-box',
                'title' => __('Warnung Box', 'container-block-designer'),
                'description' => __('Eine Warnung-Box mit gelbem Hintergrund', 'container-block-designer'),
                'config' => json_encode(array(
                    'allowInnerBlocks' => true,
                    'templateLock' => false
                )),
                'styles' => json_encode(array(
                    'padding' => array(
                        'top' => 20,
                        'right' => 20,
                        'bottom' => 20,
                        'left' => 20
                    ),
                    'background' => array(
                        'color' => '#fff3cd'
                    ),
                    'border' => array(
                        'width' => 2,
                        'style' => 'solid',
                        'color' => '#ffc107',
                        'radius' => 4
                    ),
                    'text' => array(
                        'color' => '#856404',
                        'alignment' => 'left'
                    )
                )),
                'features' => json_encode(array(
                    'icon' => array(
                        'enabled' => true,
                        'value' => 'warning'
                    ),
                    'collapse' => array(
                        'enabled' => false,
                        'value' => ''
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
            
            // index.php für zusätzliche Sicherheit
            $index = $cbd_dir . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * Version prüfen und ggf. Updates ausführen
     */
    public function check_version() {
        $current_version = get_option('cbd_plugin_version', '0');
        
        if (version_compare($current_version, CBD_VERSION, '<')) {
            // Datenbank-Migration durchführen
            if (class_exists('CBD_Database')) {
                CBD_Database::migrate_columns();
            }
            
            // Version aktualisieren
            update_option('cbd_plugin_version', CBD_VERSION);
            
            // Cache leeren
            if ($this->style_loader) {
                $this->style_loader->clear_styles_cache();
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
     * Admin-Scripts einbinden
     */
    public function admin_enqueue_scripts($hook) {
        // Nur auf unseren Plugin-Seiten laden
        if (strpos($hook, 'container-block') === false && strpos($hook, 'cbd') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CBD_VERSION
        );
        
        // Admin JS
        wp_enqueue_script(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            CBD_VERSION,
            true
        );
        
        // Color Picker
        wp_enqueue_style('wp-color-picker');
        
        // Localization
        wp_localize_script('cbd-admin', 'cbd_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
                'saving' => __('Wird gespeichert...', 'container-block-designer'),
                'saved' => __('Gespeichert!', 'container-block-designer'),
                'error' => __('Ein Fehler ist aufgetreten.', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Block Editor Assets einbinden
     */
    public function enqueue_block_editor_assets() {
        // Prüfe ob Build-Dateien existieren
        $index_js = CBD_PLUGIN_DIR . 'build/index.js';
        $editor_css = CBD_PLUGIN_DIR . 'build/editor.css';
        
        // Fallback auf Assets-Verzeichnis
        if (!file_exists($index_js)) {
            $index_js = CBD_PLUGIN_DIR . 'assets/js/block-editor.js';
        }
        
        if (!file_exists($editor_css)) {
            $editor_css = CBD_PLUGIN_DIR . 'assets/css/block-editor.css';
        }
        
        // Block Editor JS
        if (file_exists($index_js)) {
            wp_enqueue_script(
                'cbd-block-editor',
                str_replace(CBD_PLUGIN_DIR, CBD_PLUGIN_URL, $index_js),
                array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
                CBD_VERSION,
                true
            );
        }
        
        // Block Editor CSS
        if (file_exists($editor_css)) {
            wp_enqueue_style(
                'cbd-block-editor',
                str_replace(CBD_PLUGIN_DIR, CBD_PLUGIN_URL, $editor_css),
                array('wp-edit-blocks'),
                CBD_VERSION
            );
        }
        
        // Localization
        wp_localize_script('cbd-block-editor', 'cbdBlockData', array(
            'blocks' => $this->get_available_blocks(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_block_nonce'),
            'pluginUrl' => CBD_PLUGIN_URL,
            'strings' => array(
                'selectBlock' => __('Container-Design auswählen', 'container-block-designer'),
                'noBlockSelected' => __('Kein Container-Design ausgewählt', 'container-block-designer'),
                'loading' => __('Wird geladen...', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Frontend Styles einbinden
     */
    public function enqueue_frontend_styles() {
        // Prüfe ob Style-Datei existiert
        $style_css = CBD_PLUGIN_DIR . 'build/style.css';
        
        // Fallback auf Assets-Verzeichnis
        if (!file_exists($style_css)) {
            $style_css = CBD_PLUGIN_DIR . 'assets/css/frontend.css';
        }
        
        // Basis Frontend CSS
        if (file_exists($style_css)) {
            wp_enqueue_style(
                'cbd-frontend',
                str_replace(CBD_PLUGIN_DIR, CBD_PLUGIN_URL, $style_css),
                array(),
                CBD_VERSION
            );
        }
        
        // Dynamische Styles
        if ($this->style_loader) {
            $this->style_loader->enqueue_dynamic_styles();
        }
    }
    
    /**
     * Verfügbare Blocks abrufen
     */
    private function get_available_blocks() {
        if (!class_exists('CBD_Database')) {
            return array();
        }
        
        return CBD_Database::get_all_blocks(array(
            'status' => 'active'
        ));
    }
    
    /**
     * REST API Routes registrieren
     */
    public function register_rest_routes() {
        register_rest_route('cbd/v1', '/blocks', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_blocks_rest'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('cbd/v1', '/block/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_block_rest'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
    }
    
    /**
     * REST API: Blocks abrufen
     */
    public function get_blocks_rest($request) {
        if (!class_exists('CBD_Database')) {
            return rest_ensure_response(array());
        }
        
        $blocks = CBD_Database::get_all_blocks();
        return rest_ensure_response($blocks);
    }
    
    /**
     * REST API: Einzelnen Block abrufen
     */
    public function get_block_rest($request) {
        if (!class_exists('CBD_Database')) {
            return new WP_Error('database_not_available', __('Datenbank nicht verfügbar', 'container-block-designer'), array('status' => 500));
        }
        
        $block_id = $request->get_param('id');
        $block = CBD_Database::get_block($block_id);
        
        if (!$block) {
            return new WP_Error('block_not_found', __('Block nicht gefunden', 'container-block-designer'), array('status' => 404));
        }
        
        return rest_ensure_response($block);
    }
    
    /**
     * Admin Notices
     */
    public function admin_notices() {
        // Aktivierungs-Notice
        if (get_transient('cbd_activated')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php printf(
                    __('Container Block Designer wurde erfolgreich aktiviert! <a href="%s">Erstellen Sie Ihren ersten Block</a>.', 'container-block-designer'),
                    admin_url('admin.php?page=cbd-new-block')
                ); ?></p>
            </div>
            <?php
            delete_transient('cbd_activated');
        }
        
        // Update-Notice
        if (isset($_GET['cbd_updated']) && $_GET['cbd_updated'] == '1') {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><?php _e('Container Block Designer wurde erfolgreich aktualisiert!', 'container-block-designer'); ?></p>
            </div>
            <?php
        }
    }
}

// Plugin initialisieren
function cbd_init() {
    return ContainerBlockDesigner::get_instance();
}

// Start
add_action('plugins_loaded', 'cbd_init', 5);