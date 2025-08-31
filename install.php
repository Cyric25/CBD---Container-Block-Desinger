<?php
/**
 * Container Block Designer - Installation
 * Version: 2.3.0
 * 
 * Diese Datei kümmert sich um die Installation und Datenbankstruktur
 * 
 * @package ContainerBlockDesigner
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Run installation routine
 */
function cbd_install() {
    cbd_create_tables();
    cbd_create_default_blocks();
    cbd_set_default_options();
    
    // Log installation
    if (function_exists('cbd_log')) {
        cbd_log('Plugin installed', array('version' => CBD_VERSION));
    }
}

/**
 * Create database tables
 */
function cbd_create_tables() {
    global $wpdb;
    
    $table_name = CBD_TABLE_BLOCKS;
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description text,
        config longtext,
        features longtext,
        status varchar(20) DEFAULT 'active',
        created datetime DEFAULT CURRENT_TIMESTAMP,
        modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Verify table was created
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        error_log('CBD Error: Failed to create database table ' . $table_name);
        return false;
    }
    
    return true;
}

/**
 * Create default blocks
 */
function cbd_create_default_blocks() {
    global $wpdb;
    
    // Check if we already have blocks
    $count = $wpdb->get_var("SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS);
    
    if ($count > 0) {
        return; // Already have blocks
    }
    
    // Default blocks data
    $default_blocks = array(
        array(
            'name' => 'Info Box',
            'slug' => 'info-box',
            'description' => 'Ein informativer Container mit blauem Rand',
            'config' => array(
                'styles' => array(
                    'padding' => array('top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20),
                    'margin' => array('top' => 10, 'right' => 0, 'bottom' => 10, 'left' => 0),
                    'background' => array('color' => '#f0f8ff'),
                    'text' => array('color' => '#333333', 'alignment' => 'left'),
                    'border' => array('width' => 2, 'color' => '#0073aa', 'radius' => 5)
                )
            ),
            'features' => array(
                'icon' => array('enabled' => true, 'value' => 'dashicons-info'),
                'numbering' => array('enabled' => false),
                'collapse' => array('enabled' => false),
                'copyText' => array('enabled' => true, 'buttonText' => 'Text kopieren'),
                'screenshot' => array('enabled' => false)
            ),
            'status' => 'active'
        ),
        array(
            'name' => 'Warning Box',
            'slug' => 'warning-box',
            'description' => 'Ein Warnhinweis-Container mit gelbem Hintergrund',
            'config' => array(
                'styles' => array(
                    'padding' => array('top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15),
                    'margin' => array('top' => 10, 'right' => 0, 'bottom' => 10, 'left' => 0),
                    'background' => array('color' => '#fff9e6'),
                    'text' => array('color' => '#664d00', 'alignment' => 'left'),
                    'border' => array('width' => 2, 'color' => '#ffcc00', 'radius' => 5)
                )
            ),
            'features' => array(
                'icon' => array('enabled' => true, 'value' => 'dashicons-warning'),
                'numbering' => array('enabled' => false),
                'collapse' => array('enabled' => false),
                'copyText' => array('enabled' => false),
                'screenshot' => array('enabled' => false)
            ),
            'status' => 'active'
        ),
        array(
            'name' => 'Success Box',
            'slug' => 'success-box',
            'description' => 'Ein Erfolgs-Container mit grünem Akzent',
            'config' => array(
                'styles' => array(
                    'padding' => array('top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20),
                    'margin' => array('top' => 10, 'right' => 0, 'bottom' => 10, 'left' => 0),
                    'background' => array('color' => '#f0fff0'),
                    'text' => array('color' => '#1e4e1e', 'alignment' => 'left'),
                    'border' => array('width' => 2, 'color' => '#46b450', 'radius' => 5)
                )
            ),
            'features' => array(
                'icon' => array('enabled' => true, 'value' => 'dashicons-yes-alt'),
                'numbering' => array('enabled' => false),
                'collapse' => array('enabled' => false),
                'copyText' => array('enabled' => false),
                'screenshot' => array('enabled' => false)
            ),
            'status' => 'active'
        )
    );
    
    // Insert default blocks
    foreach ($default_blocks as $block) {
        $wpdb->insert(
            CBD_TABLE_BLOCKS,
            array(
                'name' => $block['name'],
                'slug' => $block['slug'],
                'description' => $block['description'],
                'config' => json_encode($block['config']),
                'features' => json_encode($block['features']),
                'status' => $block['status'],
                'created' => current_time('mysql'),
                'modified' => current_time('mysql')
            )
        );
    }
    
    // Log creation
    if (function_exists('cbd_log')) {
        cbd_log('Default blocks created', array('count' => count($default_blocks)));
    }
}

/**
 * Set default plugin options
 */
function cbd_set_default_options() {
    // Plugin version
    update_option('cbd_version', CBD_VERSION);
    
    // Installation date
    if (!get_option('cbd_installed_date')) {
        update_option('cbd_installed_date', current_time('mysql'));
    }
    
    // Default settings
    $default_settings = array(
        'enable_debug' => WP_DEBUG,
        'enable_features' => true,
        'default_container_class' => 'cbd-container',
        'load_frontend_scripts' => true,
        'load_editor_scripts' => true
    );
    
    foreach ($default_settings as $key => $value) {
        if (get_option('cbd_' . $key) === false) {
            update_option('cbd_' . $key, $value);
        }
    }
}

/**
 * Run database upgrade if needed
 */
function cbd_upgrade_database() {
    $current_version = get_option('cbd_version', '0.0.0');
    
    if (version_compare($current_version, CBD_VERSION, '<')) {
        // Run upgrade routines
        cbd_create_tables(); // Ensure tables are up to date
        
        // Version specific upgrades
        if (version_compare($current_version, '2.0.0', '<')) {
            cbd_upgrade_to_2_0_0();
        }
        
        if (version_compare($current_version, '2.3.0', '<')) {
            cbd_upgrade_to_2_3_0();
        }
        
        // Update version
        update_option('cbd_version', CBD_VERSION);
        
        // Log upgrade
        if (function_exists('cbd_log')) {
            cbd_log('Database upgraded', array(
                'from' => $current_version,
                'to' => CBD_VERSION
            ));
        }
    }
}

/**
 * Upgrade to version 2.0.0
 */
function cbd_upgrade_to_2_0_0() {
    global $wpdb;
    
    // Add features column if it doesn't exist
    $table_name = CBD_TABLE_BLOCKS;
    $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'features'");
    
    if (!$column_exists) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN features longtext AFTER config");
        
        // Set default features for existing blocks
        $wpdb->query("UPDATE $table_name SET features = '{}' WHERE features IS NULL");
    }
}

/**
 * Upgrade to version 2.3.0
 */
function cbd_upgrade_to_2_3_0() {
    global $wpdb;
    
    // Ensure modified column has ON UPDATE CURRENT_TIMESTAMP
    $table_name = CBD_TABLE_BLOCKS;
    
    // This is a safe operation that won't fail if already set
    $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Clean up any orphaned data
    cbd_cleanup_orphaned_data();
}

/**
 * Clean up orphaned data
 */
function cbd_cleanup_orphaned_data() {
    global $wpdb;
    
    // Remove blocks with empty slugs
    $wpdb->query("DELETE FROM " . CBD_TABLE_BLOCKS . " WHERE slug = '' OR slug IS NULL");
    
    // Fix any JSON fields that might be corrupted
    $blocks = $wpdb->get_results("SELECT id, config, features FROM " . CBD_TABLE_BLOCKS);
    
    foreach ($blocks as $block) {
        $update_needed = false;
        $update_data = array();
        
        // Check config
        $config = json_decode($block->config, true);
        if ($config === null && $block->config !== 'null') {
            $update_data['config'] = '{}';
            $update_needed = true;
        }
        
        // Check features
        $features = json_decode($block->features, true);
        if ($features === null && $block->features !== 'null') {
            $update_data['features'] = '{}';
            $update_needed = true;
        }
        
        if ($update_needed) {
            $wpdb->update(
                CBD_TABLE_BLOCKS,
                $update_data,
                array('id' => $block->id)
            );
        }
    }
}

/**
 * Uninstall routine
 */
function cbd_uninstall() {
    global $wpdb;
    
    // Only run if explicitly uninstalling
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }
    
    // Remove database table
    $table_name = CBD_TABLE_BLOCKS;
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Remove options
    $options = array(
        'cbd_version',
        'cbd_installed_date',
        'cbd_enable_debug',
        'cbd_enable_features',
        'cbd_default_container_class',
        'cbd_load_frontend_scripts',
        'cbd_load_editor_scripts'
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Clean up any transients
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_cbd_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_cbd_%'");
}

// Hook into activation
register_activation_hook(CBD_PLUGIN_FILE, 'cbd_install');

// Check for upgrades on admin init
add_action('admin_init', 'cbd_upgrade_database');