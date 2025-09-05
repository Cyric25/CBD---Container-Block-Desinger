<?php
/**
 * Container Block Designer - Database Migration
 * 
 * Diese Datei führt automatisch Datenbank-Migrationen durch,
 * um Kompatibilität zwischen verschiedenen Versionen sicherzustellen.
 * 
 * @package ContainerBlockDesigner
 * @since 2.5.2
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hauptfunktion für Datenbank-Migration
 */
function cbd_run_database_migration() {
    global $wpdb;
    
    $table_name = CBD_TABLE_BLOCKS;
    
    // Prüfe ob Tabelle existiert
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Tabelle existiert nicht, erstelle sie mit korrekter Struktur
        CBD_Database::create_tables();
        return true;
    }
    
    // Hole aktuelle Spalten
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    
    // Migration 1: 'updated' zu 'updated_at'
    if (in_array('updated', $columns) && !in_array('updated_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `updated` `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        error_log('CBD Migration: Renamed column updated to updated_at');
    }
    
    // Migration 2: 'created' zu 'created_at'
    if (in_array('created', $columns) && !in_array('created_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `created` `created_at` datetime DEFAULT CURRENT_TIMESTAMP");
        error_log('CBD Migration: Renamed column created to created_at');
    }
    
    // Migration 3: 'modified' zu 'updated_at'
    if (in_array('modified', $columns) && !in_array('updated_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `modified` `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        error_log('CBD Migration: Renamed column modified to updated_at');
    }
    
    // Migration 4: Fehlende Spalten hinzufügen
    if (!in_array('updated_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        error_log('CBD Migration: Added column updated_at');
    }
    
    if (!in_array('created_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `created_at` datetime DEFAULT CURRENT_TIMESTAMP");
        error_log('CBD Migration: Added column created_at');
    }
    
    // Migration 5: Fehlende Felder für Features und Styles
    if (!in_array('features', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `features` longtext AFTER `config`");
        error_log('CBD Migration: Added column features');
    }
    
    if (!in_array('styles', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `styles` longtext AFTER `config`");
        error_log('CBD Migration: Added column styles');
    }
    
    // Migration 6: 'slug' Spalte entfernen falls vorhanden (wird durch 'name' ersetzt)
    if (in_array('slug', $columns) && !in_array('name', $columns)) {
        // Zuerst 'slug' zu 'name' umbenennen
        $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `slug` `name` varchar(100) NOT NULL");
        error_log('CBD Migration: Renamed column slug to name');
    } elseif (in_array('slug', $columns) && in_array('name', $columns)) {
        // Wenn beide existieren, lösche 'slug'
        $wpdb->query("ALTER TABLE $table_name DROP COLUMN `slug`");
        error_log('CBD Migration: Removed duplicate column slug');
    }
    
    // Migration 7: 'title' Spalte hinzufügen falls nicht vorhanden
    if (!in_array('title', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `title` varchar(200) NOT NULL AFTER `name`");
        // Fülle title mit name als Default
        $wpdb->query("UPDATE $table_name SET title = name WHERE title IS NULL OR title = ''");
        error_log('CBD Migration: Added column title');
    }
    
    // Version in Options speichern
    update_option('cbd_db_version', '2.5.2');
    
    // Cache leeren
    wp_cache_flush();
    
    error_log('CBD Migration: Database migration completed successfully');
    
    return true;
}

/**
 * Prüfe bei Plugin-Aktivierung
 */
function cbd_check_database_on_activation() {
    cbd_run_database_migration();
}

/**
 * Prüfe bei Admin-Init ob Migration nötig ist
 */
add_action('admin_init', 'cbd_check_database_version');
function cbd_check_database_version() {
    $current_db_version = get_option('cbd_db_version', '0');
    $target_version = '2.5.2';
    
    if (version_compare($current_db_version, $target_version, '<')) {
        cbd_run_database_migration();
    }
}

/**
 * Hook für Plugin-Aktivierung
 */
if (defined('CBD_PLUGIN_FILE')) {
    register_activation_hook(CBD_PLUGIN_FILE, 'cbd_check_database_on_activation');
}

/**
 * Manuelle Migration über Admin-Menü
 */
add_action('admin_notices', 'cbd_migration_admin_notice');
function cbd_migration_admin_notice() {
    // Nur auf Plugin-Seiten anzeigen
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'container-block-designer') === false) {
        return;
    }
    
    // Prüfe ob Migration durchgeführt werden muss
    $current_db_version = get_option('cbd_db_version', '0');
    if (version_compare($current_db_version, '2.5.2', '<')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>Container Block Designer:</strong> Eine Datenbank-Aktualisierung ist verfügbar. 
            <a href="<?php echo admin_url('admin.php?page=container-block-designer&cbd_run_migration=1'); ?>" class="button button-primary">Jetzt aktualisieren</a></p>
        </div>
        <?php
    }
}

/**
 * Handle manuelle Migration
 */
add_action('admin_init', 'cbd_handle_manual_migration');
function cbd_handle_manual_migration() {
    if (isset($_GET['cbd_run_migration']) && $_GET['cbd_run_migration'] == '1') {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }
        
        $result = cbd_run_database_migration();
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=container-block-designer&cbd_message=migration_success'));
        } else {
            wp_redirect(admin_url('admin.php?page=container-block-designer&cbd_message=migration_failed'));
        }
        exit;
    }
}

/**
 * Zeige Migration-Erfolgsmeldung
 */
add_action('admin_notices', 'cbd_show_migration_message');
function cbd_show_migration_message() {
    if (isset($_GET['cbd_message'])) {
        if ($_GET['cbd_message'] == 'migration_success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Container Block Designer:</strong> Datenbank wurde erfolgreich aktualisiert!</p>
            </div>
            <?php
        } elseif ($_GET['cbd_message'] == 'migration_failed') {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>Container Block Designer:</strong> Fehler bei der Datenbank-Aktualisierung. Bitte prüfen Sie die Fehler-Logs.</p>
            </div>
            <?php
        }
    }
}
