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
        $this->init_hooks();
    }
    
    /**
     * Hooks initialisieren
     */
    private function init_hooks() {
        // Plugin-Aktivierung und Deaktivierung
        register_activation_hook(CBD_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(CBD_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Plugin-Initialisierung
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Block-Registrierung mit hoher Priorität
        add_action('init', array($this, 'register_block'), 10);
        
        // Admin-Initialisierung
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Scripts und Styles
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // AJAX-Handler
        add_action('wp_ajax_cbd_get_blocks', array($this, 'ajax_get_blocks'));
        add_action('wp_ajax_cbd_save_block', array($this, 'ajax_save_block'));
        add_action('wp_ajax_cbd_delete_block', array($this, 'ajax_delete_block'));
        
        // Block-Kategorie
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
    }
    
    /**
     * Plugin-Initialisierung
     */
    public function init() {
        // Lade Abhängigkeiten erst nach WordPress-Initialisierung
        $this->load_dependencies();
        
        // Stelle sicher, dass die Datenbank-Tabellen existieren
        $this->check_database();
    }
    
    /**
     * Abhängigkeiten laden
     */
    private function load_dependencies() {
        // Kern-Funktionen
        if (file_exists(CBD_PLUGIN_DIR . 'includes/functions.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/functions.php';
        }
        
        // Datenbank-Klasse
        if (file_exists(CBD_PLUGIN_DIR . 'includes/class-cbd-database.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/class-cbd-database.php';
        }
        
        // Admin-Klasse nur im Backend
        if (is_admin() && file_exists(CBD_PLUGIN_DIR . 'includes/class-cbd-admin.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/class-cbd-admin.php';
        }
        
        // Frontend-Renderer
        if (!is_admin() && file_exists(CBD_PLUGIN_DIR . 'includes/class-cbd-frontend.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/class-cbd-frontend.php';
        }
    }
    
    /**
     * Block registrieren
     */
    public function register_block() {
        // Registriere den Block mit render_callback für Server-Side-Rendering
        register_block_type('container-block-designer/container', array(
            'editor_script' => 'cbd-block-editor',
            'editor_style' => 'cbd-block-editor-style',
            'style' => 'cbd-block-style',
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
                'blockFeatures' => array(
                    'type' => 'object',
                    'default' => array()
                ),
                'align' => array(
                    'type' => 'string',
                    'default' => ''
                )
            )
        ));
    }
    
    /**
     * Block-Editor Assets einbinden
     */
    public function enqueue_block_editor_assets() {
        // Stelle sicher, dass alle WordPress-Dependencies geladen sind
        $dependencies = array(
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-i18n',
            'wp-data',
            'wp-compose',
            'wp-hooks',
            'wp-dom-ready',
            'jquery' // jQuery für AJAX-Calls
        );
        
        // Block-Editor JavaScript
        wp_enqueue_script(
            'cbd-block-editor',
            CBD_PLUGIN_URL . 'assets/js/block-editor.js',
            $dependencies,
            CBD_VERSION,
            true
        );
        
        // Block-Editor Styles - WICHTIG: Nicht als separate Datei registrieren
        // sondern direkt enqueuen für korrekte iframe-Integration
        wp_enqueue_style(
            'cbd-block-editor-style',
            CBD_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            CBD_VERSION
        );
        
        // Lokalisierung mit Block-Daten
        wp_localize_script('cbd-block-editor', 'cbdBlockData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('cbd/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxNonce' => wp_create_nonce('cbd-admin'),
            'pluginUrl' => CBD_PLUGIN_URL,
            'blocks' => $this->get_active_blocks(),
            'i18n' => array(
                'blockTitle' => __('Container Block', 'container-block-designer'),
                'blockDescription' => __('Ein anpassbarer Container-Block', 'container-block-designer'),
                'selectBlock' => __('Design auswählen', 'container-block-designer'),
                'noBlocks' => __('Keine Blöcke verfügbar', 'container-block-designer'),
                'addContent' => __('Inhalt hinzufügen', 'container-block-designer'),
                'settings' => __('Einstellungen', 'container-block-designer'),
                'design' => __('Design', 'container-block-designer'),
                'features' => __('Funktionen', 'container-block-designer'),
                'customClasses' => __('Eigene CSS-Klassen', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Frontend und Editor Styles einbinden
     */
    public function enqueue_block_assets() {
        // Diese Funktion läuft sowohl im Frontend als auch im Editor
        // Styles für beide Kontexte
        wp_enqueue_style(
            'cbd-block-style',
            CBD_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            CBD_VERSION
        );
        
        // Nur im Frontend JavaScript laden
        if (!is_admin()) {
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
        if (strpos($hook, 'container-block-designer') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-components'),
            CBD_VERSION
        );
        
        // Admin JavaScript
        wp_enqueue_script(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-i18n'),
            CBD_VERSION,
            true
        );
        
        // Lokalisierung
        wp_localize_script('cbd-admin', 'cbdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd-admin'),
            'pluginUrl' => CBD_PLUGIN_URL,
            'strings' => array(
                'confirmDelete' => __('Sind Sie sicher?', 'container-block-designer'),
                'saving' => __('Speichern...', 'container-block-designer'),
                'saved' => __('Gespeichert!', 'container-block-designer'),
                'error' => __('Fehler aufgetreten', 'container-block-designer')
            )
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
                    'icon' => 'layout'
                )
            ),
            $categories
        );
    }
    
    /**
     * Admin-Initialisierung
     */
    public function admin_init() {
        // Admin-spezifische Initialisierung
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Blocks', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_admin_page'),
            'dashicons-layout',
            30
        );
        
        add_submenu_page(
            'container-block-designer',
            __('Alle Blöcke', 'container-block-designer'),
            __('Alle Blöcke', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'container-block-designer',
            __('Neuer Block', 'container-block-designer'),
            __('Neuer Block', 'container-block-designer'),
            'manage_options',
            'cbd-new-block',
            array($this, 'render_new_block_page')
        );
    }
    
    /**
     * Admin-Seite rendern
     */
    public function render_admin_page() {
        if (file_exists(CBD_PLUGIN_DIR . 'templates/admin-page.php')) {
            include CBD_PLUGIN_DIR . 'templates/admin-page.php';
        } else {
            echo '<div class="wrap"><h1>' . __('Container Block Designer', 'container-block-designer') . '</h1>';
            echo '<p>' . __('Admin-Template nicht gefunden.', 'container-block-designer') . '</p></div>';
        }
    }
    
    /**
     * Neue Block-Seite rendern
     */
    public function render_new_block_page() {
        if (file_exists(CBD_PLUGIN_DIR . 'templates/new-block-page.php')) {
            include CBD_PLUGIN_DIR . 'templates/new-block-page.php';
        } else {
            echo '<div class="wrap"><h1>' . __('Neuer Block', 'container-block-designer') . '</h1>';
            echo '<p>' . __('Template nicht gefunden.', 'container-block-designer') . '</p></div>';
        }
    }
    
    /**
     * REST API Routen registrieren
     */
    public function register_rest_routes() {
        register_rest_route('cbd/v1', '/blocks', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_blocks'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('cbd/v1', '/blocks/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_block'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                )
            )
        ));
    }
    
    /**
     * REST: Alle Blöcke abrufen
     */
    public function rest_get_blocks($request) {
        return new WP_REST_Response($this->get_active_blocks(), 200);
    }
    
    /**
     * REST: Einzelnen Block abrufen
     */
    public function rest_get_block($request) {
        $id = $request->get_param('id');
        $block = $this->get_block_by_id($id);
        
        if (!$block) {
            return new WP_Error('block_not_found', __('Block nicht gefunden', 'container-block-designer'), array('status' => 404));
        }
        
        return new WP_REST_Response($block, 200);
    }
    
    /**
     * AJAX: Blöcke abrufen
     */
    public function ajax_get_blocks() {
        // Nonce-Überprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false)) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        $blocks = $this->get_active_blocks();
        wp_send_json_success($blocks);
    }
    
    /**
     * AJAX: Block speichern
     */
    public function ajax_save_block() {
        // Nonce-Überprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false)) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        // Block-Daten sammeln
        $block_data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'slug' => sanitize_title($_POST['slug'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'config' => wp_json_encode($_POST['config'] ?? array()),
            'features' => wp_json_encode($_POST['features'] ?? array())
        );
        
        // Validierung
        if (empty($block_data['name']) || empty($block_data['slug'])) {
            wp_send_json_error(__('Name und Slug sind erforderlich', 'container-block-designer'));
        }
        
        // In Datenbank speichern
        global $wpdb;
        
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update
            $result = $wpdb->update(
                CBD_TABLE_BLOCKS,
                $block_data,
                array('id' => intval($_POST['id']))
            );
        } else {
            // Insert
            $result = $wpdb->insert(CBD_TABLE_BLOCKS, $block_data);
        }
        
        if ($result === false) {
            wp_send_json_error(__('Speichern fehlgeschlagen', 'container-block-designer'));
        }
        
        wp_send_json_success(array(
            'message' => __('Block gespeichert', 'container-block-designer'),
            'id' => isset($_POST['id']) ? intval($_POST['id']) : $wpdb->insert_id
        ));
    }
    
    /**
     * AJAX: Block löschen
     */
    public function ajax_delete_block() {
        // Nonce-Überprüfung
        if (!check_ajax_referer('cbd-admin', 'nonce', false)) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen', 'container-block-designer'));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'container-block-designer'));
        }
        
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            wp_send_json_error(__('Ungültige Block-ID', 'container-block-designer'));
        }
        
        global $wpdb;
        $result = $wpdb->delete(CBD_TABLE_BLOCKS, array('id' => $id));
        
        if ($result === false) {
            wp_send_json_error(__('Löschen fehlgeschlagen', 'container-block-designer'));
        }
        
        wp_send_json_success(array(
            'message' => __('Block gelöscht', 'container-block-designer')
        ));
    }
    
    /**
     * Aktive Blöcke abrufen
     */
    private function get_active_blocks() {
        global $wpdb;
        
        // Prüfe ob Tabelle existiert
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . CBD_TABLE_BLOCKS . "'") === CBD_TABLE_BLOCKS;
        
        if (!$table_exists) {
            return array();
        }
        
        $blocks = $wpdb->get_results(
            "SELECT id, name, slug, description, config, features 
             FROM " . CBD_TABLE_BLOCKS . " 
             WHERE status = 'active' 
             ORDER BY name ASC",
            ARRAY_A
        );
        
        if (!$blocks) {
            return array();
        }
        
        // JSON-Daten dekodieren
        foreach ($blocks as &$block) {
            $block['config'] = json_decode($block['config'], true) ?: array();
            $block['features'] = json_decode($block['features'], true) ?: array();
        }
        
        return $blocks;
    }
    
    /**
     * Block nach ID abrufen
     */
    private function get_block_by_id($id) {
        global $wpdb;
        
        $block = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        
        if ($block) {
            $block['config'] = json_decode($block['config'], true) ?: array();
            $block['features'] = json_decode($block['features'], true) ?: array();
        }
        
        return $block;
    }
    
    /**
     * Block rendern (Server-Side-Rendering)
     */
    public function render_block($attributes, $content) {
        $selected_block = $attributes['selectedBlock'] ?? '';
        $custom_classes = $attributes['customClasses'] ?? '';
        $block_config = $attributes['blockConfig'] ?? array();
        $block_features = $attributes['blockFeatures'] ?? array();
        $align = $attributes['align'] ?? '';
        
        // Container-Klassen
        $container_classes = array('cbd-container');
        
        if ($selected_block) {
            $container_classes[] = 'cbd-block-' . sanitize_html_class($selected_block);
        }
        
        if ($custom_classes) {
            $container_classes[] = esc_attr($custom_classes);
        }
        
        if ($align) {
            $container_classes[] = 'align' . $align;
        }
        
        // Container-Styles
        $container_styles = array();
        
        if (isset($block_config['styles'])) {
            $styles = $block_config['styles'];
            
            if (isset($styles['padding'])) {
                $padding = $styles['padding'];
                $container_styles[] = sprintf(
                    'padding: %dpx %dpx %dpx %dpx',
                    $padding['top'] ?? 20,
                    $padding['right'] ?? 20,
                    $padding['bottom'] ?? 20,
                    $padding['left'] ?? 20
                );
            }
            
            if (isset($styles['background']['color'])) {
                $container_styles[] = 'background-color: ' . esc_attr($styles['background']['color']);
            }
            
            if (isset($styles['text']['color'])) {
                $container_styles[] = 'color: ' . esc_attr($styles['text']['color']);
            }
            
            if (isset($styles['border']['width']) && $styles['border']['width'] > 0) {
                $container_styles[] = sprintf(
                    'border: %dpx solid %s',
                    $styles['border']['width'],
                    esc_attr($styles['border']['color'] ?? '#ddd')
                );
                
                if (isset($styles['border']['radius'])) {
                    $container_styles[] = 'border-radius: ' . intval($styles['border']['radius']) . 'px';
                }
            }
        }
        
        // HTML generieren
        $html = sprintf(
            '<div class="%s"%s>',
            esc_attr(implode(' ', $container_classes)),
            $container_styles ? ' style="' . esc_attr(implode('; ', $container_styles)) . '"' : ''
        );
        
        // Icon Feature
        if (isset($block_features['icon']['enabled']) && $block_features['icon']['enabled']) {
            $icon_position = $block_features['icon']['position'] ?? 'top-left';
            $icon_value = $block_features['icon']['value'] ?? 'dashicons-admin-generic';
            $icon_color = $block_features['icon']['color'] ?? '#333';
            
            $html .= sprintf(
                '<span class="cbd-icon %s dashicons %s" style="color: %s;"></span>',
                esc_attr($icon_position),
                esc_attr($icon_value),
                esc_attr($icon_color)
            );
        }
        
        // Content
        $html .= '<div class="cbd-container-content">';
        $html .= $content;
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Datenbank prüfen
     */
    private function check_database() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . CBD_TABLE_BLOCKS . "'") === CBD_TABLE_BLOCKS;
        
        if (!$table_exists) {
            $this->create_database_tables();
        }
    }
    
    /**
     * Datenbank-Tabellen erstellen
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS " . CBD_TABLE_BLOCKS . " (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            status varchar(20) DEFAULT 'active',
            config longtext,
            features longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Plugin aktivieren
     */
    public function activate() {
        $this->create_database_tables();
        
        // Beispiel-Block erstellen
        global $wpdb;
        
        $example_block = array(
            'name' => 'Standard Container',
            'slug' => 'standard-container',
            'description' => 'Ein einfacher Container-Block mit Basis-Styling',
            'status' => 'active',
            'config' => json_encode(array(
                'styles' => array(
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
                        'color' => '#e0e0e0',
                        'radius' => 4
                    )
                )
            )),
            'features' => json_encode(array(
                'icon' => array(
                    'enabled' => false,
                    'value' => 'dashicons-admin-generic',
                    'position' => 'top-left',
                    'color' => '#333333'
                )
            ))
        );
        
        // Prüfe ob Beispiel-Block bereits existiert
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s",
                'standard-container'
            )
        );
        
        if (!$exists) {
            $wpdb->insert(CBD_TABLE_BLOCKS, $example_block);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deaktivieren
     */
    public function deactivate() {
        flush_rewrite_rules();
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
}

// Plugin initialisieren
function cbd_init() {
    return ContainerBlockDesigner::get_instance();
}

// Starte das Plugin
add_action('plugins_loaded', 'cbd_init');

// Globale Hilfsfunktionen
if (!function_exists('cbd_get_active_blocks')) {
    function cbd_get_active_blocks() {
        global $wpdb;
        
        $table = CBD_TABLE_BLOCKS;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return array();
        }
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'active' ORDER BY name",
            ARRAY_A
        );
    }
}

if (!function_exists('cbd_get_block_by_slug')) {
    function cbd_get_block_by_slug($slug) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . CBD_TABLE_BLOCKS . " WHERE slug = %s AND status = 'active'",
                $slug
            ),
            ARRAY_A
        );
    }
}