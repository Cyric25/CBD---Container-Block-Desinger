<?php
/**
 * Container Block Designer - Database Update Script
 * 
 * Dieses Skript aktualisiert die Datenbank-Tabelle um Kompatibilitätsprobleme zu beheben
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.1
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Datenbank-Schema aktualisieren
 */
function cbd_update_database_schema() {
    global $wpdb;
    
    $table_name = CBD_TABLE_BLOCKS;
    
    // Prüfe ob Tabelle existiert
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return false;
    }
    
    // Hole aktuelle Spalten
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    
    // Prüfe ob 'updated' existiert aber 'updated_at' nicht
    if (in_array('updated', $columns) && !in_array('updated_at', $columns)) {
        // Benenne 'updated' in 'updated_at' um
        $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `updated` `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    
    // Prüfe ob 'created' existiert aber 'created_at' nicht
    if (in_array('created', $columns) && !in_array('created_at', $columns)) {
        // Benenne 'created' in 'created_at' um
        $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `created` `created_at` datetime DEFAULT CURRENT_TIMESTAMP");
    }
    
    // Falls weder 'updated' noch 'updated_at' existiert, füge 'updated_at' hinzu
    if (!in_array('updated', $columns) && !in_array('updated_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    
    // Falls weder 'created' noch 'created_at' existiert, füge 'created_at' hinzu
    if (!in_array('created', $columns) && !in_array('created_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `created_at` datetime DEFAULT CURRENT_TIMESTAMP");
    }
    
    return true;
}

// Führe Update aus
add_action('admin_init', 'cbd_check_database_update');
function cbd_check_database_update() {
    // Nur auf unseren Plugin-Seiten
    if (!isset($_GET['page']) || strpos($_GET['page'], 'container-block-designer') === false) {
        return;
    }
    
    // Prüfe ob Update bereits durchgeführt wurde
    $db_version = get_option('cbd_db_version', '1.0');
    
    if (version_compare($db_version, '2.5.1', '<')) {
        cbd_update_database_schema();
        update_option('cbd_db_version', '2.5.1');
    }
}

// Alternative: Manuelles Update über Hook
add_action('cbd_update_database', 'cbd_update_database_schema');