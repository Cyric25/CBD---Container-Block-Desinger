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
    const DB_VERSION = '3.1.61';
    
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
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY status (status),
            KEY is_default (is_default),
            KEY created_at (created_at),
            KEY updated_at (updated_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Classroom System tables
        $classes_table = $wpdb->prefix . 'cbd_classes';
        $class_pages_table = $wpdb->prefix . 'cbd_class_pages';
        $drawings_table = $wpdb->prefix . 'cbd_drawings';

        $sql_classes = "CREATE TABLE IF NOT EXISTS $classes_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            password varchar(255) NOT NULL,
            teacher_id bigint(20) unsigned NOT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY teacher_id (teacher_id),
            KEY status (status)
        ) $charset_collate;";

        $sql_class_pages = "CREATE TABLE IF NOT EXISTS $class_pages_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            class_id int(11) NOT NULL,
            page_id bigint(20) unsigned NOT NULL,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY class_page (class_id, page_id),
            KEY class_id (class_id),
            KEY page_id (page_id)
        ) $charset_collate;";

        $sql_drawings = "CREATE TABLE IF NOT EXISTS $drawings_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            class_id int(11) NOT NULL,
            teacher_id bigint(20) unsigned NOT NULL,
            page_id bigint(20) unsigned NOT NULL,
            container_id varchar(200) NOT NULL,
            drawing_data longtext DEFAULT NULL,
            is_behandelt tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY class_page_container (class_id, page_id, container_id),
            KEY class_id (class_id),
            KEY teacher_id (teacher_id),
            KEY page_id (page_id)
        ) $charset_collate;";

        dbDelta($sql_classes);
        dbDelta($sql_class_pages);
        dbDelta($sql_drawings);

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

        if (version_compare($current_version, '2.9.0', '<')) {
            self::migrate_to_2_9_0();
        }

        if (version_compare($current_version, '3.0.0', '<')) {
            self::migrate_to_3_0_0();
        }

        if (version_compare($current_version, '3.1.32', '<')) {
            self::migrate_legacy_container_ids();
        }

        if (version_compare($current_version, '3.1.61', '<')) {
            self::migrate_feature_keys_to_3_1_61();
        }

        // Update database version to current version
        update_option(self::DB_VERSION_KEY, self::DB_VERSION);
    }

    /**
     * Migration zu 3.1.61 - Feature-Keys vereinheitlichen
     *
     * Historisch existierten zwei Schreibweisen in der features-JSON-Spalte:
     * Admin/Renderer nutzen 'collapse'/'copyText', ältere Default-Blöcke und
     * der Style-Loader nutzten 'collapsible'/'copy'. Kanonisch sind ab jetzt
     * 'collapse' und 'copyText' (siehe VERBESSERUNGSPLAN.md AP1).
     */
    private static function migrate_feature_keys_to_3_1_61() {
        global $wpdb;
        $table_name = CBD_TABLE_BLOCKS;

        $rows = $wpdb->get_results("SELECT id, features FROM $table_name", ARRAY_A);
        if (empty($rows)) {
            return;
        }

        $key_map = array(
            'collapsible' => 'collapse',
            'copy'        => 'copyText',
        );

        foreach ($rows as $row) {
            $features = json_decode($row['features'], true);
            if (!is_array($features)) {
                continue;
            }

            $changed = false;
            foreach ($key_map as $old_key => $new_key) {
                if (!isset($features[$old_key])) {
                    continue;
                }
                // Alten Key umhängen, sofern der kanonische nicht schon existiert
                if (!isset($features[$new_key])) {
                    $features[$new_key] = $features[$old_key];
                }
                unset($features[$old_key]);
                $changed = true;
            }

            if ($changed) {
                $wpdb->update(
                    $table_name,
                    array('features' => wp_json_encode($features)),
                    array('id' => $row['id']),
                    array('%s'),
                    array('%d')
                );
            }
        }

        // Caches invalidieren, damit Frontend/Editor die neuen Keys sehen
        if (class_exists('CBD_Block_Registration')) {
            CBD_Block_Registration::clear_blocks_cache();
        }
        if (class_exists('CBD_Style_Loader')) {
            $style_loader = CBD_Style_Loader::get_instance();
            if (method_exists($style_loader, 'clear_styles_cache')) {
                $style_loader->clear_styles_cache();
            }
        }
    }

    /**
     * Migration to version 3.0.0 - Add Classroom System tables
     */
    private static function migrate_to_3_0_0() {
        // Tables are created in create_tables() via dbDelta
        // This migration handles any additional setup if needed
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
     * Migration to version 2.9.0 - Add is_default field for default style
     */
    private static function migrate_to_2_9_0() {
        global $wpdb;
        $table_name = CBD_TABLE_BLOCKS;

        // Get current columns
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

        if (empty($columns)) {
            return; // Table doesn't exist yet
        }

        // Add is_default column if missing
        if (!in_array('is_default', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `is_default` tinyint(1) DEFAULT 0 AFTER `status`");

            // Add index for faster queries
            $wpdb->query("ALTER TABLE $table_name ADD KEY `is_default` (`is_default`)");
        }
    }

    /**
     * Migration 3.1.32: Mark that legacy container ID migration is needed.
     * Actual migration happens lazily in render_block() where we have access
     * to the rendered $content needed to compute legacy hashes.
     */
    private static function migrate_legacy_container_ids() {
        global $wpdb;
        $drawings_table = $wpdb->prefix . 'cbd_drawings';

        // Check if drawings table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$drawings_table'") !== $drawings_table) {
            return;
        }

        // Count legacy entries
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $drawings_table WHERE container_id LIKE 'cbd-legacy-%'"
        );

        if ($count > 0) {
            update_option('cbd_legacy_migration_pending', true);
            error_log('[CBD Migration] Found ' . $count . ' legacy container IDs - will migrate lazily during render');
        } else {
            delete_option('cbd_legacy_migration_pending');
            error_log('[CBD Migration] No legacy container IDs found');
        }
    }

    /**
     * Lazy migration: Update a single legacy container ID to the real stableId.
     * Called from render_block() where we have access to the actual $content.
     *
     * @param string $stable_id   The real stableId (from block attributes/HTML)
     * @param string $legacy_hash The computed legacy hash for this block
     * @param int    $page_id     The page ID
     */
    public static function migrate_single_legacy_id($stable_id, $legacy_hash, $page_id) {
        // Only run if migration is pending and IDs differ
        if ($stable_id === $legacy_hash || strpos($stable_id, 'cbd-legacy-') === 0) {
            return;
        }

        if (!get_option('cbd_legacy_migration_pending')) {
            return;
        }

        global $wpdb;
        $drawings_table = $wpdb->prefix . 'cbd_drawings';

        // Check if legacy entries exist for this hash
        $legacy_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $drawings_table WHERE page_id = %d AND container_id = %s",
            $page_id, $legacy_hash
        ));

        if ($legacy_count === 0) {
            return;
        }

        // Check if target stableId already has entries (avoid duplicates)
        $existing_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $drawings_table WHERE page_id = %d AND container_id = %s",
            $page_id, $stable_id
        ));

        if ($existing_count > 0) {
            // Target already exists - remove legacy duplicates
            $wpdb->delete($drawings_table, array(
                'page_id' => $page_id,
                'container_id' => $legacy_hash
            ));
            error_log('[CBD Migration] Removed duplicate legacy entries: ' . $legacy_hash . ' (page ' . $page_id . ')');
        } else {
            // Update legacy to real stableId
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $drawings_table SET container_id = %s WHERE page_id = %d AND container_id = %s",
                $stable_id, $page_id, $legacy_hash
            ));
            error_log('[CBD Migration] Updated: ' . $legacy_hash . ' => ' . $stable_id . ' (page ' . $page_id . ', rows: ' . $result . ')');
        }

        // Check if any legacy entries remain globally
        $remaining = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $drawings_table WHERE container_id LIKE 'cbd-legacy-%'"
        );
        if ($remaining === 0) {
            delete_option('cbd_legacy_migration_pending');
            error_log('[CBD Migration] All legacy container IDs migrated!');
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