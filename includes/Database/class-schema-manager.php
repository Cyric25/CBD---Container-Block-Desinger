<?php
/**
 * Container Block Designer - Unified Schema Manager
 * Consolidates all database operations and migrations
 * 
 * @package ContainerBlockDesigner
 * @since 2.6.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified Schema Manager Class
 * Replaces multiple migration files with centralized database management
 */
class CBD_Schema_Manager {
    
    /**
     * Database version for tracking migrations
     */
    const DB_VERSION = '2.6.0';
    
    /**
     * Option key for storing database version
     */
    const DB_VERSION_KEY = 'cbd_db_version';
    
    /**
     * Create or update database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = CBD_TABLE_BLOCKS;
        
        // Unified table schema with all required columns
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            title varchar(200) NOT NULL DEFAULT '',
            description text DEFAULT NULL,
            config longtext DEFAULT NULL,
            styles longtext DEFAULT NULL,
            features longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY status (status),
            KEY created_at (created_at),
            KEY updated_at (updated_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Run migrations if needed
        self::run_migrations();
        
        // Update database version
        update_option(self::DB_VERSION_KEY, self::DB_VERSION);
    }
    
    /**
     * Run database migrations
     */
    public static function run_migrations() {
        $current_version = get_option(self::DB_VERSION_KEY, '0');
        
        if (version_compare($current_version, '2.6.0', '<')) {
            self::migrate_to_2_6_0();
        }
    }
    
    /**
     * Migration to version 2.6.0 - Standardize column names
     */
    private static function migrate_to_2_6_0() {
        global $wpdb;
        $table_name = CBD_TABLE_BLOCKS;
        
        // Get current columns
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        
        if (empty($columns)) {
            return; // Table doesn't exist yet
        }
        
        // Standardize timestamp columns
        if (in_array('updated', $columns) && !in_array('updated_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `updated` `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        
        if (in_array('created', $columns) && !in_array('created_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `created` `created_at` datetime DEFAULT CURRENT_TIMESTAMP");
        }
        
        if (in_array('modified', $columns) && !in_array('updated_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `modified` `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        
        // Add missing columns in correct order
        if (!in_array('title', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `title` varchar(200) NOT NULL DEFAULT '' AFTER `name`");
        }

        // Add 'styles' column first if missing
        if (!in_array('styles', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `styles` longtext DEFAULT NULL AFTER `config`");
        }

        // Then add 'features' column after 'styles'
        if (!in_array('features', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `features` longtext DEFAULT NULL AFTER `styles`");
        }

        if (!in_array('status', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `status` varchar(20) DEFAULT 'active' AFTER `features`");
        }
        
        // Standardize name column (was sometimes 'slug')
        if (in_array('slug', $columns) && !in_array('name', $columns)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `slug` `name` varchar(100) NOT NULL");
        }
        
        // Clean up orphaned data
        self::cleanup_data();
    }
    
    /**
     * Clean up orphaned or invalid data
     */
    private static function cleanup_data() {
        global $wpdb;
        $table_name = CBD_TABLE_BLOCKS;
        
        // Remove blocks with empty names
        $wpdb->query("DELETE FROM $table_name WHERE name = '' OR name IS NULL");
        
        // Fix JSON fields that might be corrupted - with safe column checking
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

        // Build safe SELECT query based on available columns
        $select_fields = array('id');
        if (in_array('config', $columns)) $select_fields[] = 'config';
        if (in_array('styles', $columns)) $select_fields[] = 'styles';
        if (in_array('features', $columns)) $select_fields[] = 'features';

        $select_query = "SELECT " . implode(', ', $select_fields) . " FROM $table_name";
        $blocks = $wpdb->get_results($select_query);
        
        foreach ($blocks as $block) {
            $updated = false;
            $update_data = array();
            
            // Fix config field (only if column exists)
            if (in_array('config', $columns) && property_exists($block, 'config') && !empty($block->config) && !self::is_valid_json($block->config)) {
                $update_data['config'] = '{}';
                $updated = true;
            }

            // Fix styles field (only if column exists)
            if (in_array('styles', $columns) && property_exists($block, 'styles') && !empty($block->styles) && !self::is_valid_json($block->styles)) {
                $update_data['styles'] = '{}';
                $updated = true;
            }

            // Fix features field (only if column exists)
            if (in_array('features', $columns) && property_exists($block, 'features') && !empty($block->features) && !self::is_valid_json($block->features)) {
                $update_data['features'] = '{}';
                $updated = true;
            }
            
            if ($updated) {
                $wpdb->update($table_name, $update_data, array('id' => $block->id));
            }
        }
        
        // Set default values for NULL JSON fields (only for existing columns)
        if (in_array('config', $columns)) {
            $wpdb->query("UPDATE $table_name SET config = '{}' WHERE config IS NULL");
        }
        if (in_array('styles', $columns)) {
            $wpdb->query("UPDATE $table_name SET styles = '{}' WHERE styles IS NULL");
        }
        if (in_array('features', $columns)) {
            $wpdb->query("UPDATE $table_name SET features = '{}' WHERE features IS NULL");
        }
    }
    
    /**
     * Check if string is valid JSON
     */
    private static function is_valid_json($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Remove version option
        delete_option(self::DB_VERSION_KEY);
    }
    
    /**
     * Get database info for debugging
     */
    public static function get_database_info() {
        global $wpdb;
        
        $table_name = CBD_TABLE_BLOCKS;
        $info = array();
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $info['table_exists'] = !empty($table_exists);
        
        if ($info['table_exists']) {
            // Get columns
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
            $info['columns'] = array();
            foreach ($columns as $column) {
                $info['columns'][$column->Field] = array(
                    'type' => $column->Type,
                    'null' => $column->Null,
                    'default' => $column->Default,
                    'key' => $column->Key
                );
            }
            
            // Get row count
            $info['row_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }
        
        $info['db_version'] = get_option(self::DB_VERSION_KEY, 'not set');
        
        return $info;
    }
    
    /**
     * Repair database if needed
     */
    public static function repair_database() {
        // First try to create tables (handles missing table)
        self::create_tables();
        
        // Then run cleanup
        self::cleanup_data();
        
        return true;
    }
}