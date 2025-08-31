<?php
/**
 * Container Block Designer - Database Fix
 * Version: 2.2.2
 * 
 * Dieses Skript repariert die Datenbank-Spalten und stellt Konsistenz her
 * 
 * @package ContainerBlockDesigner
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fix database column names
 * Diese Funktion kann in der Hauptdatei aufgerufen werden
 */
if (!function_exists('cbd_fix_database_columns')) {
    function cbd_fix_database_columns() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        // Prüfen ob Tabelle existiert
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            // Tabelle existiert nicht, erstelle sie neu
            if (function_exists('cbd_create_tables_fixed')) {
                cbd_create_tables_fixed();
            } else {
                cbd_create_tables();
            }
            return;
        }
        
        // Hole alle Spalten
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $existing_columns = array();
        
        foreach ($columns as $column) {
            $existing_columns[] = $column->Field;
        }
        
        // Prüfe ob alte Spalten (created_at, updated_at) existieren
        $has_created_at = in_array('created_at', $existing_columns);
        $has_updated_at = in_array('updated_at', $existing_columns);
        
        // Prüfe ob neue Spalten (created, modified) existieren
        $has_created = in_array('created', $existing_columns);
        $has_modified = in_array('modified', $existing_columns);
        
        // Wenn alte Spalten existieren, aber neue nicht -> Umbenennen
        if ($has_created_at && !$has_created) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN created_at created datetime DEFAULT CURRENT_TIMESTAMP");
            error_log("CBD: Renamed column created_at to created");
        }
        
        if ($has_updated_at && !$has_modified) {
            $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN updated_at modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            error_log("CBD: Renamed column updated_at to modified");
        }
        
        // Wenn keine Zeitstempel-Spalten existieren, füge sie hinzu
        if (!$has_created && !$has_created_at) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN created datetime DEFAULT CURRENT_TIMESTAMP");
            error_log("CBD: Added column created");
        }
        
        if (!$has_modified && !$has_updated_at) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            error_log("CBD: Added column modified");
        }
        
        // Prüfe ob features Spalte existiert
        if (!in_array('features', $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN features longtext AFTER config");
            error_log("CBD: Added column features");
        }
        
        // Prüfe ob status Spalte existiert
        if (!in_array('status', $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN status varchar(20) DEFAULT 'active' AFTER features");
            error_log("CBD: Added column status");
        }
    }
}

/**
 * Create tables with correct structure
 */
if (!function_exists('cbd_create_tables_fixed')) {
    function cbd_create_tables_fixed() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cbd_blocks';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Einheitliche Tabellenstruktur
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
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
        
        error_log("CBD: Created table with correct structure");
    }
}

/**
 * Run the fix - Diese Funktion sollte beim Plugin-Aktivieren aufgerufen werden
 */
if (!function_exists('cbd_run_database_fix')) {
    function cbd_run_database_fix() {
        // Fix column names
        if (function_exists('cbd_fix_database_columns')) {
            cbd_fix_database_columns();
        }
        
        // Update version to prevent re-running
        update_option('cbd_db_fix_version', '2.2.2');
        
        // Clear any cached queries
        wp_cache_flush();
        
        error_log("CBD: Database fix completed");
    }
}

// Hook für Plugin-Aktivierung nur registrieren wenn CBD_PLUGIN_FILE definiert ist
if (defined('CBD_PLUGIN_FILE')) {
    register_activation_hook(CBD_PLUGIN_FILE, 'cbd_run_database_fix');
}

// Prüfe bei Admin-Init ob Fix nötig ist
add_action('admin_init', function() {
    $fix_version = get_option('cbd_db_fix_version', '0');
    
    if (version_compare($fix_version, '2.2.2', '<')) {
        if (function_exists('cbd_run_database_fix')) {
            cbd_run_database_fix();
        }
    }
});