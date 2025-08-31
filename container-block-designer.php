<?php
/**
 * Plugin Name: Container Block Designer
 * Plugin URI: https://example.com/container-block-designer
 * Description: Erstellen Sie anpassbare Container-Blocks mit erweiterten Features für den Gutenberg Editor
 * Version: 3.0.0
 * Author: Ihr Name
 * Author URI: https://example.com
 * Text Domain: container-block-designer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * 
 * @package ContainerBlockDesigner
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('CBD_VERSION', '3.0.0');
define('CBD_PLUGIN_FILE', __FILE__);
define('CBD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CBD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CBD_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Database Table
global $wpdb;
define('CBD_TABLE_BLOCKS', $wpdb->prefix . 'cbd_blocks');

// Main Plugin Class
if (!class_exists('ContainerBlockDesigner')) {

/**
 * Main Plugin Class
 */
class ContainerBlockDesigner {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->check_requirements();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('Container Block Designer benötigt PHP 7.4 oder höher.', 'container-block-designer'); ?></p>
                </div>
                <?php
            });
            return false;
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('Container Block Designer benötigt WordPress 6.0 oder höher.', 'container-block-designer'); ?></p>
                </div>
                <?php
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core includes
        require_once CBD_PLUGIN_DIR . 'install.php';
        require_once CBD_PLUGIN_DIR . 'includes/ajax-handlers.php';
        require_once CBD_PLUGIN_DIR . 'includes/rest-api.php';
        
        // Block Registration System - NEUE STABILE LÖSUNG
        require_once CBD_PLUGIN_DIR . 'includes/class-block-registration.php';
        
        // Database includes
        if (file_exists(CBD_PLUGIN_DIR . 'includes/database-fix.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/database-fix.php';
        }
        
        if (file_exists(CBD_PLUGIN_DIR . 'includes/Database/cbd-migration-features.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/Database/cbd-migration-features.php';
        }
        
        // Admin includes (nur im Admin-Bereich)
        if (is_admin()) {
            if (file_exists(CBD_PLUGIN_DIR . 'includes/Admin/class-admin-features.php')) {
                require_once CBD_PLUGIN_DIR . 'includes/Admin/class-admin-features.php';
            }
        }
        
        // Frontend includes (nur im Frontend)
        if (!is_admin()) {
            if (file_exists(CBD_PLUGIN_DIR . 'includes/Blocks/block-frontend-renderer.php')) {
                require_once CBD_PLUGIN_DIR . 'includes/Blocks/block-frontend-renderer.php';
            }
        }
        
        // Development/Debug tools (nur wenn WP_DEBUG aktiv)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (file_exists(CBD_PLUGIN_DIR . 'cbd-debug.php')) {
                require_once CBD_PLUGIN_DIR . 'cbd-debug.php';
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation/Uninstall
        register_activation_hook(CBD_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(CBD_PLUGIN_FILE, array($this, 'deactivate'));
        register_uninstall_hook(CBD_PLUGIN_FILE, array(__CLASS__, 'uninstall'));
        
        // Core actions
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Database maintenance
        add_action('admin_init', array($this, 'check_database'));
        add_action('admin_init', array($this, 'maybe_update_database'));
        
        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Block assets (moved to block registration class)
        // add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        // add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
        
        // Dynamic CSS
        add_action('wp_head', array($this, 'output_dynamic_css'), 100);
        add_action('admin_head', array($this, 'output_admin_dynamic_css'), 100);
        
        // Plugin action links
        add_filter('plugin_action_links_' . CBD_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create/Update database tables
        $this->create_database_tables();
        
        // Run installation routine
        if (function_exists('cbd_install')) {
            cbd_install();
        }
        
        // Set activation flag for welcome message
        set_transient('cbd_activation_redirect', true, 30);
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        $this->log_event('plugin_activated', array('version' => CBD_VERSION));
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events if any
        wp_clear_scheduled_hook('cbd_daily_maintenance');
        
        // Clear transients
        delete_transient('cbd_activation_redirect');
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        $this->log_event('plugin_deactivated', array('version' => CBD_VERSION));
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Only run if explicitly uninstalling
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Get uninstall option
        $remove_data = get_option('cbd_remove_data_on_uninstall', false);
        
        if ($remove_data) {
            // Remove database tables
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS " . CBD_TABLE_BLOCKS);
            
            // Remove options
            $options = array(
                'cbd_version',
                'cbd_db_version',
                'cbd_features_version',
                'cbd_features_migration_version',
                'cbd_db_fix_version',
                'cbd_remove_data_on_uninstall'
            );
            
            foreach ($options as $option) {
                delete_option($option);
            }
            
            // Remove transients
            delete_transient('cbd_activation_redirect');
            
            // Clear any cached data
            wp_cache_flush();
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize block registration system (NEW)
        if (class_exists('CBD_Block_Registration')) {
            CBD_Block_Registration::get_instance();
        }
        
        // Initialize admin features
        if (is_admin() && class_exists('ContainerBlockDesigner\Admin\Admin_Features')) {
            ContainerBlockDesigner\Admin\Admin_Features::init();
        }
        
        // Initialize frontend renderer
        if (!is_admin() && class_exists('CBD_Frontend_Renderer')) {
            CBD_Frontend_Renderer::init();
        }
        
        // Register custom post statuses if needed
        $this->register_post_statuses();
        
        // Schedule maintenance tasks
        if (!wp_next_scheduled('cbd_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'cbd_daily_maintenance');
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'container-block-designer',
            false,
            dirname(CBD_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            config longtext,
            features longtext,
            css_variables text,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20) DEFAULT 0,
            created datetime DEFAULT CURRENT_TIMESTAMP,
            modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('cbd_db_version', CBD_VERSION);
    }
    
    /**
     * Check database structure
     */
    public function check_database() {
        $installed_version = get_option('cbd_db_version');
        
        if ($installed_version !== CBD_VERSION) {
            $this->create_database_tables();
        }
    }
    
    /**
     * Maybe update database
     */
    public function maybe_update_database() {
        // Check if migration is needed
        if (class_exists('\ContainerBlockDesigner\Database\Migration_Features')) {
            if (\ContainerBlockDesigner\Database\Migration_Features::is_needed()) {
                \ContainerBlockDesigner\Database\Migration_Features::up();
            }
        }
        
        // Fix any database issues
        if (function_exists('cbd_run_database_fix')) {
            $fix_version = get_option('cbd_db_fix_version', '0');
            if (version_compare($fix_version, '2.2.1', '<')) {
                cbd_run_database_fix();
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Container Block Designer', 'container-block-designer'),
            __('Container Blocks', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_admin_page'),
            'dashicons-layout',
            30
        );
        
        // Submenu: All Blocks
        add_submenu_page(
            'container-block-designer',
            __('Alle Blocks', 'container-block-designer'),
            __('Alle Blocks', 'container-block-designer'),
            'manage_options',
            'container-block-designer',
            array($this, 'render_admin_page')
        );
        
        // Submenu: Add New
        add_submenu_page(
            'container-block-designer',
            __('Neuer Block', 'container-block-designer'),
            __('Neuer Block', 'container-block-designer'),
            'manage_options',
            'cbd-new-block',
            array($this, 'render_new_block_page')
        );
        
        // Submenu: Edit Block (hidden)
        add_submenu_page(
            null,
            __('Block bearbeiten', 'container-block-designer'),
            __('Block bearbeiten', 'container-block-designer'),
            'manage_options',
            'cbd-edit-block',
            array($this, 'render_edit_block_page')
        );
        
        // Submenu: Settings
        add_submenu_page(
            'container-block-designer',
            __('Einstellungen', 'container-block-designer'),
            __('Einstellungen', 'container-block-designer'),
            'manage_options',
            'cbd-settings',
            array($this, 'render_settings_page')
        );
        
        // Debug page (only in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                null,
                'CBD Debug',
                'CBD Debug',
                'manage_options',
                'cbd-debug',
                array($this, 'render_debug_page')
            );
        }
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        require_once CBD_PLUGIN_DIR . 'admin/blocks-list.php';
    }
    
    /**
     * Render new block page
     */
    public function render_new_block_page() {
        require_once CBD_PLUGIN_DIR . 'admin/new-block.php';
    }
    
    /**
     * Render edit block page
     */
    public function render_edit_block_page() {
        require_once CBD_PLUGIN_DIR . 'admin/edit-block.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (file_exists(CBD_PLUGIN_DIR . 'admin/settings.php')) {
            require_once CBD_PLUGIN_DIR . 'admin/settings.php';
        } else {
            ?>
            <div class="wrap">
                <h1><?php _e('Container Block Designer - Einstellungen', 'container-block-designer'); ?></h1>
                <form method="post" action="options.php">
                    <?php settings_fields('cbd_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cbd_remove_data_on_uninstall">
                                    <?php _e('Daten bei Deinstallation löschen', 'container-block-designer'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="checkbox" name="cbd_remove_data_on_uninstall" id="cbd_remove_data_on_uninstall" value="1" <?php checked(get_option('cbd_remove_data_on_uninstall'), 1); ?> />
                                <p class="description"><?php _e('Alle Plugin-Daten werden bei der Deinstallation gelöscht.', 'container-block-designer'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }
    }
    
    /**
     * Render debug page
     */
    public function render_debug_page() {
        if (file_exists(CBD_PLUGIN_DIR . 'cbd-debug.php') && function_exists('cbd_debug_page')) {
            cbd_debug_page();
        } else {
            ?>
            <div class="wrap">
                <h1>Container Block Designer - Debug</h1>
                <pre><?php
                    echo "CBD_VERSION: " . CBD_VERSION . "\n";
                    echo "CBD_PLUGIN_DIR: " . CBD_PLUGIN_DIR . "\n";
                    echo "CBD_PLUGIN_URL: " . CBD_PLUGIN_URL . "\n";
                    echo "CBD_TABLE_BLOCKS: " . CBD_TABLE_BLOCKS . "\n";
                    echo "PHP Version: " . PHP_VERSION . "\n";
                    echo "WordPress Version: " . get_bloginfo('version') . "\n";
                ?></pre>
            </div>
            <?php
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only on our admin pages
        if (strpos($hook, 'container-block-designer') === false && strpos($hook, 'cbd-') === false) {
            return;
        }
        
        // Admin styles
        wp_enqueue_style(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-color-picker'),
            CBD_VERSION
        );
        
        // Admin script
        wp_enqueue_script(
            'cbd-admin',
            CBD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            CBD_VERSION,
            true
        );
        
        // Admin features script
        if (file_exists(CBD_PLUGIN_DIR . 'assets/js/admin-features.js')) {
            wp_enqueue_script(
                'cbd-admin-features',
                CBD_PLUGIN_URL . 'assets/js/admin-features.js',
                array('jquery', 'jquery-ui-sortable'),
                CBD_VERSION,
                true
            );
        }
        
        // Localize scripts
        wp_localize_script('cbd-admin', 'cbdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd-admin'),
            'i18n' => array(
                'confirmDelete' => __('Sind Sie sicher, dass Sie diesen Block löschen möchten?', 'container-block-designer'),
                'saving' => __('Speichern...', 'container-block-designer'),
                'saved' => __('Gespeichert!', 'container-block-designer'),
                'error' => __('Ein Fehler ist aufgetreten.', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Check if our block is used on this page
        if (!has_block('container-block-designer/container')) {
            return;
        }
        
        // Frontend styles
        wp_enqueue_style(
            'cbd-frontend',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend.css',
            array(),
            CBD_VERSION
        );
        
        // Advanced features styles
        wp_enqueue_style(
            'cbd-advanced-features',
            CBD_PLUGIN_URL . 'assets/css/advanced-features.css',
            array('dashicons'),
            CBD_VERSION
        );
        
        // Advanced features script
        wp_enqueue_script(
            'cbd-advanced-features',
            CBD_PLUGIN_URL . 'assets/js/advanced-features.js',
            array('jquery'),
            CBD_VERSION,
            true
        );
        
        // Html2Canvas for screenshot feature
        wp_enqueue_script(
            'html2canvas',
            'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
            array(),
            '1.4.1',
            true
        );
        
        // Localize script
        wp_localize_script('cbd-advanced-features', 'cbdFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cbd_frontend'),
            'strings' => array(
                'copied' => __('Text kopiert!', 'container-block-designer'),
                'copyError' => __('Fehler beim Kopieren', 'container-block-designer'),
                'screenshotSaved' => __('Screenshot gespeichert!', 'container-block-designer'),
                'screenshotError' => __('Fehler beim Erstellen des Screenshots', 'container-block-designer'),
                'expand' => __('Ausklappen', 'container-block-designer'),
                'collapse' => __('Einklappen', 'container-block-designer')
            )
        ));
    }
    
    /**
     * Output dynamic CSS
     */
    public function output_dynamic_css() {
        global $wpdb;
        
        // Get all active blocks
        $blocks = $wpdb->get_results(
            "SELECT slug, config FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'"
        );
        
        if (!$blocks) {
            return;
        }
        
        echo '<style type="text/css" id="cbd-dynamic-styles">' . "\n";
        
        foreach ($blocks as $block) {
            $config = json_decode($block->config, true);
            if (!$config || !isset($config['styles'])) {
                continue;
            }
            
            $styles = $config['styles'];
            $selector = '.cbd-container-' . esc_attr($block->slug);
            
            echo $selector . ' {' . "\n";
            
            // Padding
            if (isset($styles['padding'])) {
                echo '  padding: ';
                echo intval($styles['padding']['top']) . 'px ';
                echo intval($styles['padding']['right']) . 'px ';
                echo intval($styles['padding']['bottom']) . 'px ';
                echo intval($styles['padding']['left']) . 'px;' . "\n";
            }
            
            // Margin
            if (isset($styles['margin'])) {
                echo '  margin: ';
                echo intval($styles['margin']['top']) . 'px ';
                echo intval($styles['margin']['right']) . 'px ';
                echo intval($styles['margin']['bottom']) . 'px ';
                echo intval($styles['margin']['left']) . 'px;' . "\n";
            }
            
            // Background
            if (isset($styles['background']['color'])) {
                echo '  background-color: ' . esc_attr($styles['background']['color']) . ';' . "\n";
            }
            
            // Text
            if (isset($styles['text']['color'])) {
                echo '  color: ' . esc_attr($styles['text']['color']) . ';' . "\n";
            }
            if (isset($styles['text']['alignment'])) {
                echo '  text-align: ' . esc_attr($styles['text']['alignment']) . ';' . "\n";
            }
            
            // Border
            if (isset($styles['border'])) {
                if (isset($styles['border']['width']) && $styles['border']['width'] > 0) {
                    echo '  border: ' . intval($styles['border']['width']) . 'px solid ';
                    echo esc_attr($styles['border']['color']) . ';' . "\n";
                }
                if (isset($styles['border']['radius']) && $styles['border']['radius'] > 0) {
                    echo '  border-radius: ' . intval($styles['border']['radius']) . 'px;' . "\n";
                }
            }
            
            echo '}' . "\n\n";
        }
        
        echo '</style>' . "\n";
    }
    
    /**
     * Output admin dynamic CSS
     */
    public function output_admin_dynamic_css() {
        $this->output_dynamic_css();
    }
    
    /**
     * Register custom post statuses
     */
    private function register_post_statuses() {
        // Can be used for future functionality
    }
    
    /**
     * Add plugin action links
     */
    public function add_action_links($links) {
        $action_links = array(
            '<a href="' . admin_url('admin.php?page=container-block-designer') . '">' . __('Blocks verwalten', 'container-block-designer') . '</a>',
            '<a href="' . admin_url('admin.php?page=cbd-settings') . '">' . __('Einstellungen', 'container-block-designer') . '</a>'
        );
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Log events for debugging
     */
    private function log_event($event, $data = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[CBD] Event: %s | Data: %s',
                $event,
                json_encode($data)
            ));
        }
    }
    
    /**
     * Handle activation redirect
     */
    public function handle_activation_redirect() {
        if (get_transient('cbd_activation_redirect')) {
            delete_transient('cbd_activation_redirect');
            
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=container-block-designer'));
                exit;
            }
        }
    }
}

} // End class_exists check

// Initialize plugin
add_action('plugins_loaded', function() {
    ContainerBlockDesigner::get_instance();
});

// Handle activation redirect
add_action('admin_init', function() {
    if (get_transient('cbd_activation_redirect')) {
        delete_transient('cbd_activation_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=container-block-designer'));
            exit;
        }
    }
});