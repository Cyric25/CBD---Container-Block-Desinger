<?php
/**
 * Container Block Designer - Activation Check
 *
 * Ensures database tables exist and creates them if missing
 *
 * @package ContainerBlockDesigner
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activation and Database Check
 */
class CBD_Activation_Check {

    /**
     * Initialize activation check
     */
    public static function init() {
        // Check on admin init to ensure database is ready
        add_action('admin_init', array(__CLASS__, 'verify_installation'));
    }

    /**
     * Verify plugin installation and database setup
     */
    public static function verify_installation() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cbd_blocks';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            error_log('CBD: Database table missing, creating...');
            self::create_database_table();
        } else {
            // Check if table has data
            $block_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

            if ($block_count == 0) {
                error_log('CBD: No blocks found, creating defaults...');
                self::create_default_blocks();
            }
        }
    }

    /**
     * Create database table if missing
     */
    private static function create_database_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cbd_blocks';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            title varchar(255) NOT NULL DEFAULT '',
            slug varchar(255) NOT NULL DEFAULT '',
            description text DEFAULT '',
            config longtext DEFAULT '{}',
            styles longtext DEFAULT '{}',
            features longtext DEFAULT '{}',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create default blocks after table creation
        self::create_default_blocks();
    }

    /**
     * Create default blocks
     */
    private static function create_default_blocks() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cbd_blocks';

        // Check if blocks already exist
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE name IN ('basic-container', 'card-container', 'hero-section')");

        if ($existing_count > 0) {
            return; // Blocks already exist
        }

        $default_blocks = array(
            array(
                'name' => 'basic-container',
                'title' => 'Einfacher Container',
                'slug' => 'basic-container',
                'description' => 'Ein einfacher Container mit Rahmen und Padding',
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
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array(
                'name' => 'card-container',
                'title' => 'Info-Box',
                'slug' => 'card-container',
                'description' => 'Eine Info-Box mit Icon und blauem Hintergrund',
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
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array(
                'name' => 'hero-section',
                'title' => 'Hero Section',
                'slug' => 'hero-section',
                'description' => 'Ein Hero-Bereich für prominente Inhalte',
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
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );

        // Insert blocks
        foreach ($default_blocks as $block) {
            $wpdb->insert($table_name, $block);
        }

        error_log('CBD: Default blocks created successfully');
    }
}

// Initialize activation check
CBD_Activation_Check::init();