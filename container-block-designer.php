<?php
/**
 * Plugin Name: Container Block Designer
 * Plugin URI: https://github.com/Cyric25/CBD---Container-Block-Desinger
 * Description: Erstellen und verwalten Sie anpassbare Container-Blöcke für den WordPress Block-Editor
 * Version: 3.0.19
 * Author: Cyric25
 * Author URI: https://github.com/Cyric25
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: container-block-designer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 6.4
 * Tested PHP: 8.4
 *
 * @package ContainerBlockDesigner
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten definieren
define('CBD_VERSION', '3.0.19');
define('CBD_PLUGIN_FILE', __FILE__);
define('CBD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CBD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CBD_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Datenbank-Tabellennamen
global $wpdb;
define('CBD_TABLE_BLOCKS', $wpdb->prefix . 'cbd_blocks');
define('CBD_TABLE_CLASSES', $wpdb->prefix . 'cbd_classes');
define('CBD_TABLE_CLASS_PAGES', $wpdb->prefix . 'cbd_class_pages');
define('CBD_TABLE_DRAWINGS', $wpdb->prefix . 'cbd_drawings');

// Load WordPress PHP 8.x compatibility layer early
require_once CBD_PLUGIN_DIR . 'includes/php8-wordpress-compatibility.php';

// Autoloading - Try Composer first, fallback to custom autoloader
if (file_exists(CBD_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once CBD_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback autoloader
    require_once CBD_PLUGIN_DIR . 'includes/class-autoloader.php';
    $autoloader = new CBD_Autoloader();
    $autoloader->init_default_mappings();
    $autoloader->register();
}

/**
 * Hauptklasse des Plugins - Refactored with Service Container
 */
class ContainerBlockDesigner {
    
    /**
     * Singleton-Instanz
     */
    private static $instance = null;
    
    /**
     * Service Container
     */
    private $container = null;
    
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
     * Konstruktor - Now uses Service Container
     */
    private function __construct() {
        $this->init_container();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Initialize service container
     */
    private function init_container() {
        require_once CBD_PLUGIN_DIR . 'includes/class-service-container.php';
        $this->container = CBD_Service_Container::get_instance();
    }
    
    /**
     * Abhängigkeiten laden
     */
    private function load_dependencies() {
        // Helper-Funktionen
        require_once CBD_PLUGIN_DIR . 'includes/user-capabilities.php';

        // Kern-Klassen
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-database.php';
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-style-loader.php';
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-block-registration.php';
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-ajax-handler.php';

        // LaTeX Parser für mathematische Formeln
        require_once CBD_PLUGIN_DIR . 'includes/class-latex-parser.php';

        // LaTeX Bulk Cleanup für beschädigte Formeln
        require_once CBD_PLUGIN_DIR . 'includes/class-latex-bulk-cleanup.php';

        // PDF Generator für serverseitige PDF-Erstellung
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-pdf-generator.php';

        // Block Reference - Link to other CBD blocks
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-block-reference.php';
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-blocks-rest-api.php';

        // Content Importer - Markdown to CDB blocks
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-content-importer.php';

        // Classroom System (Klassen-System) - optionales Feature
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-classroom.php';

        // Migration Tool - Stable ID Migration
        require_once CBD_PLUGIN_DIR . 'includes/class-cbd-migration.php';

        // Admin-Bereich nur im Backend laden
        if (is_admin()) {
            require_once CBD_PLUGIN_DIR . 'includes/class-cbd-admin.php';
        }

        // Frontend-Renderer - DISABLED to prevent conflicts with block registration
        // Master Renderer caused double rendering when block registration is active
        // if (!is_admin() || wp_doing_ajax()) {
        //     require_once CBD_PLUGIN_DIR . 'includes/class-master-renderer.php';
        // }
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

        // Rolle prüfen und erstellen falls nötig (auch bei bereits aktivierten Plugins)
        add_action('init', array($this, 'ensure_block_editor_role'));

        // Admin-Menüs für Block-Redakteure anpassen
        add_action('admin_menu', array($this, 'customize_admin_menu_for_block_editors'), 999);

        // Admin-Bar für Block-Redakteure anpassen
        add_action('wp_before_admin_bar_render', array($this, 'customize_admin_bar_for_block_editors'));
    }
    
    /**
     * Plugin-Aktivierung
     */
    public function activate() {
        // Datenbank-Tabellen erstellen mit neuem Schema Manager
        if (class_exists('CBD_Schema_Manager')) {
            CBD_Schema_Manager::create_tables();
        } else {
            // Fallback auf alte Methode
            CBD_Database::create_tables();
        }

        // Standarddaten einfügen
        $this->create_default_blocks();

        // Upload-Verzeichnis erstellen
        $this->create_upload_directory();

        // Custom User Role erstellen
        $this->create_block_editor_role();

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
        // Block-Redakteur Rolle entfernen
        $this->remove_block_editor_role();

        // Rewrite-Regeln löschen
        flush_rewrite_rules();
        
        // Geplante Events entfernen
        wp_clear_scheduled_hook('cbd_daily_cleanup');
        
        // Cache leeren - über Service Container
        try {
            $style_loader = $this->container->get('style_loader');
            if ($style_loader && method_exists($style_loader, 'clear_styles_cache')) {
                $style_loader->clear_styles_cache();
            }
        } catch (Exception $e) {
            // Fallback: Direkt über Singleton
            if (class_exists('CBD_Style_Loader')) {
                $style_loader = CBD_Style_Loader::get_instance();
                if (method_exists($style_loader, 'clear_styles_cache')) {
                    $style_loader->clear_styles_cache();
                }
            }
        }
    }
    
    /**
     * Plugin initialisieren - Now uses Service Container
     */
    public function init() {
        // Einfache statische Variable - funktioniert innerhalb eines Requests
        static $initialized = false;
        
        if ($initialized) {
            error_log('[CBD Main] Plugin already initialized, skipping');
            return;
        }
        
        try {
            // Initialize services through container
            $style_loader = $this->container->get('style_loader');
            
            // Block registration - RE-ENABLED for editor
            $block_registration = $this->container->get('block_registration');
            $block_registration->register_blocks();
            
            // AJAX handler
            $this->container->get('ajax_handler');

            // LaTeX Parser initialization
            if (class_exists('CBD_LaTeX_Parser')) {
                CBD_LaTeX_Parser::get_instance();
            }

            // LaTeX Bulk Cleanup initialization
            if (class_exists('CBD_LaTeX_Bulk_Cleanup')) {
                CBD_LaTeX_Bulk_Cleanup::get_instance();
            }

            // Block Reference initialization
            if (class_exists('CBD_Block_Reference')) {
                CBD_Block_Reference::init();
            }

            // REST API for Block Reference
            if (class_exists('CBD_Blocks_REST_API')) {
                CBD_Blocks_REST_API::init();
            }

            // Migration Tool initialization (Admin only)
            if (is_admin() && class_exists('CBD_Migration')) {
                CBD_Migration::get_instance();
            }

            // Admin area - aktiviert für normale Funktionalität
            if (is_admin()) {
                // Fallback: Direct admin initialization wenn Service Container admin nicht verfügbar
                if (class_exists('CBD_Admin')) {
                    CBD_Admin::get_instance();
                }
            }

            // Frontend renderer - DISABLED (Block Registration handles rendering)
            // if (!is_admin() || wp_doing_ajax()) {
            //     $frontend_renderer = $this->container->get('consolidated_frontend');
            // }
            
            // API Manager - falls benötigt
            // $this->container->get('api_manager');
            
            // Check for plugin activation
            if (get_option('cbd_plugin_activated')) {
                delete_option('cbd_plugin_activated');
                if (is_admin() && !wp_doing_ajax()) {
                    wp_safe_redirect(admin_url('admin.php?page=cbd-blocks&cbd_activated=1'));
                    exit;
                }
            }
            
            // Version update check
            $this->check_version_update();
            
            // Markiere als initialisiert
            $initialized = true;
            error_log('[CBD Main] Plugin initialization completed');
            
        } catch (Exception $e) {
            // Log error and fall back to legacy initialization
            error_log('CBD Service Container Error: ' . $e->getMessage());
            error_log('CBD Stack Trace: ' . $e->getTraceAsString());
            $this->init_legacy_fallback();
        } catch (Error $e) {
            // Handle fatal errors (like private constructor calls)
            error_log('CBD Service Container Fatal Error: ' . $e->getMessage());
            error_log('CBD Stack Trace: ' . $e->getTraceAsString());
            $this->init_legacy_fallback();
        }
    }
    
    /**
     * Legacy fallback initialization
     */
    private function init_legacy_fallback() {
        // Fallback to old initialization method
        if (class_exists('CBD_Style_Loader')) {
            CBD_Style_Loader::get_instance();
        }
        
        // Legacy block registration fallback
        if (class_exists('CBD_Block_Registration')) {
            $registration = CBD_Block_Registration::get_instance();
            $registration->register_blocks();
        }
        
        if (class_exists('CBD_Ajax_Handler')) {
            new CBD_Ajax_Handler();
        }
        
        if (is_admin() && class_exists('CBD_Admin')) {
            CBD_Admin::get_instance();
        }
        
        // Legacy frontend renderers disabled - Block Registration handles rendering
        // if ((!is_admin() || wp_doing_ajax()) && class_exists('CBD_Consolidated_Frontend')) {
        //     CBD_Consolidated_Frontend::get_instance();
        // }
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
            
            // Style-Cache leeren - über Service Container
            try {
                $style_loader = $this->container->get('style_loader');
                if ($style_loader && method_exists($style_loader, 'clear_styles_cache')) {
                    $style_loader->clear_styles_cache();
                }
            } catch (Exception $e) {
                // Fallback: Direkt über Singleton
                if (class_exists('CBD_Style_Loader')) {
                    $style_loader = CBD_Style_Loader::get_instance();
                    if (method_exists($style_loader, 'clear_styles_cache')) {
                        $style_loader->clear_styles_cache();
                    }
                }
            }
        }
    }
    
    /**
     * Update-Routinen ausführen
     */
    private function run_updates($from_version) {
        // Updates für Version 2.5.0
        if (version_compare($from_version, '2.5.0', '<')) {
            // Datenbank-Schema aktualisieren mit neuem Schema Manager
            if (class_exists('CBD_Schema_Manager')) {
                CBD_Schema_Manager::create_tables();
            } elseif (class_exists('CBD_Database') && method_exists('CBD_Database', 'update_schema')) {
                CBD_Database::update_schema();
            } else {
                // Fallback: Alte Methode
                if (class_exists('CBD_Database')) {
                    CBD_Database::create_tables();
                }
            }
        }

        // Updates für Version 2.6.0 - Neue Schema Manager Migration
        if (version_compare($from_version, '2.6.0', '<')) {
            if (class_exists('CBD_Schema_Manager')) {
                CBD_Schema_Manager::create_tables();
            }
        }
        
        // Updates für Version 2.5.2
        if (version_compare($from_version, '2.5.2', '<')) {
            // Alte CSS-Dateien löschen
            $this->cleanup_old_css_files();
            
            // Cache-Verzeichnis erstellen
            $this->create_upload_directory();
        }
        
        // Updates für Version 2.6.1
        if (version_compare($from_version, '2.6.1', '<')) {
            // Standard-Blocks mit korrekten Namen neu erstellen
            $this->create_default_blocks();
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
                    'title' => __('Container', 'container-block-designer'),
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
        
        // Prüfe ob bereits korrekte Standard-Blocks existieren
        $correct_blocks = $wpdb->get_var("SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE name IN ('basic-container', 'card-container', 'hero-section')");
        
        if ($correct_blocks >= 3) {
            return; // Bereits korrekte Blocks vorhanden
        }
        
        // Lösche alte Standard-Blocks mit deutschen Namen falls vorhanden
        $wpdb->query("DELETE FROM " . CBD_TABLE_BLOCKS . " WHERE name LIKE '%Container%' OR name LIKE '%Info-Box%' OR name LIKE '%Klappbar%'");
        
        // Standard-Blocks definieren
        $default_blocks = array(
            array(
                'name' => 'basic-container', // Konsistent mit Block-Registrierung
                'title' => __('Einfacher Container', 'container-block-designer'),
                'slug' => 'basic-container',
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
                'name' => 'card-container', // Konsistent mit Block-Registrierung
                'title' => __('Info-Box', 'container-block-designer'),
                'slug' => 'card-container',
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
                'name' => 'hero-section', // Konsistent mit Block-Registrierung
                'title' => __('Hero Section', 'container-block-designer'),
                'slug' => 'hero-section',
                'description' => __('Ein Hero-Bereich für prominente Inhalte', 'container-block-designer'),
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
     * Admin-Menüs für Block-Redakteure anpassen
     */
    public function customize_admin_menu_for_block_editors() {
        // Nur für Block-Redakteure
        $user = wp_get_current_user();
        if (!$user || !in_array('block_redakteur', $user->roles)) {
            return;
        }

        // ALLE WordPress Standard-Menüs entfernen außer den gewünschten
        global $menu, $submenu;

        // Posts-Menü komplett entfernen (Beiträge)
        remove_menu_page('edit.php');
        remove_menu_page('post-new.php');

        // Weitere WordPress-Menüs ausblenden die Block-Redakteure nicht brauchen
        remove_menu_page('tools.php');            // Werkzeuge
        remove_menu_page('options-general.php');  // Einstellungen
        remove_menu_page('edit-comments.php');    // Kommentare
        remove_menu_page('themes.php');           // Design/Themes
        remove_menu_page('plugins.php');          // Plugins
        remove_menu_page('users.php');            // Benutzer
        remove_menu_page('profile.php');          // Profil (wird automatisch wieder hinzugefügt)

        // Alle Post-Types außer Pages entfernen
        $post_types = get_post_types(array('public' => true, 'show_ui' => true), 'objects');
        foreach ($post_types as $post_type) {
            if ($post_type->name !== 'page') {
                remove_menu_page('edit.php?post_type=' . $post_type->name);
            }
        }

        // Container-Block Admin-Untermenüs für Block-Redakteure entfernen
        remove_submenu_page('cbd-blocks', 'cbd-new-block');      // Kein "Block hinzufügen"
        remove_submenu_page('cbd-blocks', 'cbd-edit-block');     // Kein "Block bearbeiten"
        remove_submenu_page('cbd-blocks', 'cbd-settings');       // Keine "Einstellungen"
        remove_submenu_page('cbd-blocks', 'cbd-import-export');  // Kein "Import/Export"

        // CSS und JS hinzufügen um sicherzustellen dass Posts-Menü ausgeblendet ist
        add_action('admin_head', function() {
            echo '<style>
                /* Posts-Menü für Block-Redakteure ausblenden */
                body.wp-admin #menu-posts,
                body.wp-admin #adminmenu #menu-posts,
                body.wp-admin #adminmenu li#menu-posts {
                    display: none !important;
                }
                /* Neue Post Links ausblenden */
                body.wp-admin .page-title-action[href*="post-new.php"],
                body.wp-admin .wp-admin #wp-admin-bar-new-post {
                    display: none !important;
                }
                /* Dashboard "Auf einen Blick" Posts ausblenden */
                body.wp-admin #dashboard_right_now .post-count {
                    display: none !important;
                }
            </style>';

            echo '<script>
                jQuery(document).ready(function($) {
                    // Posts-Menü mit JavaScript entfernen (Fallback)
                    $("#menu-posts").remove();
                    $("#adminmenu li#menu-posts").remove();

                    // "Neuer Beitrag" aus Admin-Bar entfernen
                    $("#wp-admin-bar-new-post").remove();

                    // Posts aus Dashboard "Auf einen Blick" entfernen
                    $("#dashboard_right_now .post-count").remove();
                });
            </script>';
        });
    }

    /**
     * Admin-Bar für Block-Redakteure anpassen
     */
    public function customize_admin_bar_for_block_editors() {
        // Nur für Block-Redakteure
        $user = wp_get_current_user();
        if (!$user || !in_array('block_redakteur', $user->roles)) {
            return;
        }

        global $wp_admin_bar;

        // "Neuer Beitrag" aus Admin-Bar entfernen
        $wp_admin_bar->remove_node('new-post');

        // Alle Post-Type "Neu" Links außer Seiten entfernen
        $post_types = get_post_types(array('public' => true, 'show_ui' => true), 'objects');
        foreach ($post_types as $post_type) {
            if ($post_type->name !== 'page') {
                $wp_admin_bar->remove_node('new-' . $post_type->name);
            }
        }
    }

    /**
     * Block-Redakteur Rolle sicherstellen (public für init hook)
     */
    public function ensure_block_editor_role() {
        // Nur einmal pro Request ausführen
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $this->create_block_editor_role();
    }

    /**
     * Block-Redakteur Rolle erstellen
     */
    private function create_block_editor_role() {
        // Prüfe ob Rolle bereits existiert
        if (get_role('block_redakteur')) {
            return; // Rolle existiert bereits
        }

        // Basis-Capabilities nur für Seiten
        $capabilities = array(
            'read' => true,                    // Grundrecht zum Lesen
            'edit_pages' => true,              // Seiten bearbeiten
            'edit_others_pages' => true,       // Fremde Seiten bearbeiten
            'edit_published_pages' => true,    // Veröffentlichte Seiten bearbeiten
            'publish_pages' => true,           // Seiten veröffentlichen
            'delete_pages' => false,           // NICHT löschen
            'delete_others_pages' => false,    // Keine fremden Seiten löschen
            'delete_published_pages' => false, // Keine veröffentlichten Seiten löschen

            // WordPress Editor verwenden (minimal für Block Editor)
            'edit_posts' => true,              // NÖTIG für Block-Editor
            'edit_others_posts' => false,      // Keine fremden Posts
            'edit_published_posts' => false,   // Keine veröffentlichten Posts
            'publish_posts' => false,          // Keine Posts veröffentlichen
            'delete_posts' => false,           // Keine Posts löschen

            // Custom Container Block Designer Capabilities
            'cbd_edit_blocks' => true,         // Container-Blocks im Editor verwenden
            'cbd_edit_styles' => false,        // KEINE Style-Bearbeitung (nur vordefinierte nutzen)
            'cbd_admin_blocks' => false,       // KEINE Admin-Funktionen (Settings/Import/Erstellen)

            // Standard Admin-Rechte
            'manage_options' => false,         // KEINE WordPress Admin-Rechte

            // Upload-Rechte für Medien in Blocks
            'upload_files' => true,

            // WordPress Editor verwenden
            'edit_theme_options' => false,     // Keine Theme-Bearbeitung
        );

        // Rolle hinzufügen
        add_role(
            'block_redakteur',
            __('Block-Redakteur', 'container-block-designer'),
            $capabilities
        );

        // Administrator Rolle um Container-Block Capabilities erweitern
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('cbd_edit_blocks');
            $admin_role->add_cap('cbd_edit_styles');
            $admin_role->add_cap('cbd_admin_blocks');
        }

        // Editor Rolle um Container-Block Capabilities erweitern (optional)
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap('cbd_edit_blocks');
            $editor_role->add_cap('cbd_edit_styles');
            // Editoren bekommen KEINE Admin-Rechte (cbd_admin_blocks = false)
        }
    }

    /**
     * Block-Redakteur Rolle entfernen
     */
    private function remove_block_editor_role() {
        // Prüfe ob Rolle existiert
        if (get_role('block_redakteur')) {
            // Alle Benutzer mit dieser Rolle zu Editor machen (als Fallback)
            $users = get_users(array('role' => 'block_redakteur'));
            foreach ($users as $user) {
                $user_obj = new WP_User($user->ID);
                $user_obj->remove_role('block_redakteur');
                $user_obj->add_role('editor'); // Fallback auf Editor-Rolle
            }

            // Rolle löschen
            remove_role('block_redakteur');
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
        try {
            $block_registration = $this->container->get('block_registration');
            return $block_registration->get_available_blocks();
        } catch (Exception $e) {
            return array();
        }
    }
    
    /**
     * Get service container
     */
    public function get_container() {
        return $this->container;
    }
    
    /**
     * Get service from container
     */
    public function get_service($service_name) {
        try {
            return $this->container->get($service_name);
        } catch (Exception $e) {
            error_log('CBD Service Error: ' . $e->getMessage());
            return null;
        }
    }
}

// Plugin initialisieren
add_action('plugins_loaded', function() {
    ContainerBlockDesigner::get_instance();
});

// Globale Hilfsfunktionen
if (!function_exists('cbd_get_blocks')) {
    function cbd_get_blocks() {
        $cbd = ContainerBlockDesigner::get_instance();
        return $cbd->get_available_blocks();
    }
}

if (!function_exists('cbd_get_service')) {
    function cbd_get_service($service_name) {
        $cbd = ContainerBlockDesigner::get_instance();
        return $cbd->get_service($service_name);
    }
}

if (!function_exists('cbd_get_container')) {
    function cbd_get_container() {
        $cbd = ContainerBlockDesigner::get_instance();
        return $cbd->get_container();
    }
}