<?php
/**
 * Container Block Designer - Einstellungen
 *
 * @package ContainerBlockDesigner
 * @since 2.5.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Datenbank-Migration durchführen
$migration_result = null;
if (isset($_POST['cbd_run_migration']) && wp_verify_nonce($_POST['cbd_migration_nonce'], 'cbd_run_migration')) {
    $migration_result = array('success' => true, 'messages' => array());

    try {
        // Verwende Schema Manager für alle Migrationen
        if (class_exists('CBD_Schema_Manager')) {
            $schema = CBD_Schema_Manager::get_instance();
            $schema->run_migrations();

            $new_version = get_option('cbd_db_version', '0');
            $migration_result['messages'][] = __('Alle Migrationen erfolgreich durchgeführt', 'container-block-designer');
            $migration_result['messages'][] = __('Datenbank-Version:', 'container-block-designer') . ' ' . $new_version;

            // Prüfe welche Tabellen erstellt wurden
            global $wpdb;
            $tables_check = array(
                'cbd_blocks' => $wpdb->prefix . 'cbd_blocks',
                'cbd_classes' => $wpdb->prefix . 'cbd_classes',
                'cbd_class_pages' => $wpdb->prefix . 'cbd_class_pages',
                'cbd_drawings' => $wpdb->prefix . 'cbd_drawings'
            );

            foreach ($tables_check as $name => $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
                if ($exists) {
                    $migration_result['messages'][] = '✓ Tabelle ' . $name . ' vorhanden';
                }
            }
        } else {
            $migration_result['success'] = false;
            $migration_result['messages'][] = __('CBD_Schema_Manager Klasse nicht gefunden', 'container-block-designer');
        }
    } catch (Exception $e) {
        $migration_result['success'] = false;
        $migration_result['messages'][] = __('Fehler:', 'container-block-designer') . ' ' . $e->getMessage();
    }
}

// Einstellungen speichern
if (isset($_POST['cbd_save_settings']) && wp_verify_nonce($_POST['cbd_settings_nonce'], 'cbd_settings')) {
    update_option('cbd_remove_data_on_uninstall', isset($_POST['remove_data_on_uninstall']) ? 1 : 0);
    update_option('cbd_enable_debug_mode', isset($_POST['enable_debug_mode']) ? 1 : 0);
    update_option('cbd_default_block_status', sanitize_text_field($_POST['default_block_status']));
    update_option('cbd_enable_block_caching', isset($_POST['enable_block_caching']) ? 1 : 0);
    update_option('cbd_classroom_enabled', isset($_POST['classroom_enabled']) ? 1 : 0);

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Einstellungen gespeichert.', 'container-block-designer') . '</p></div>';
}

// Migration-Ergebnis anzeigen
if ($migration_result !== null) {
    if ($migration_result['success']) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<h3>✅ ' . __('Migration erfolgreich!', 'container-block-designer') . '</h3>';
        foreach ($migration_result['messages'] as $msg) {
            echo '<p>• ' . esc_html($msg) . '</p>';
        }
        echo '</div>';
    } else {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<h3>❌ ' . __('Migration fehlgeschlagen!', 'container-block-designer') . '</h3>';
        foreach ($migration_result['messages'] as $msg) {
            echo '<p>' . esc_html($msg) . '</p>';
        }
        echo '</div>';
    }
}

// Aktuelle Einstellungen laden
$remove_data = get_option('cbd_remove_data_on_uninstall', 0);
$debug_mode = get_option('cbd_enable_debug_mode', 0);
$default_status = get_option('cbd_default_block_status', 'draft');
$enable_caching = get_option('cbd_enable_block_caching', 1);
$classroom_enabled = get_option('cbd_classroom_enabled', 0);

// Datenbank-Status prüfen
global $wpdb;
$table_name = $wpdb->prefix . 'cbd_blocks';
$columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
$is_default_exists = in_array('is_default', $columns);
$db_version = get_option('cbd_db_version', '0');

// Prüfe Klassen-Tabellen (Version 3.0.0)
$classroom_tables_exist = true;
$classroom_tables_status = array();
$classroom_tables = array(
    'cbd_classes' => $wpdb->prefix . 'cbd_classes',
    'cbd_class_pages' => $wpdb->prefix . 'cbd_class_pages',
    'cbd_drawings' => $wpdb->prefix . 'cbd_drawings'
);

foreach ($classroom_tables as $name => $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    $classroom_tables_status[$name] = (bool)$exists;
    if (!$exists) {
        $classroom_tables_exist = false;
    }
}

$needs_migration = !$is_default_exists || !$classroom_tables_exist || version_compare($db_version, '3.0.0', '<');
?>

<div class="wrap">
    <h1><?php _e('Container Block Designer - Einstellungen', 'container-block-designer'); ?></h1>

    <!-- Datenbank reparieren -->
    <div class="cbd-card" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2>🔧 <?php _e('Datenbank reparieren', 'container-block-designer'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Datenbank-Version', 'container-block-designer'); ?></th>
                <td>
                    <strong><?php echo esc_html($db_version); ?></strong>
                    <p class="description"><?php _e('Aktuelle Datenbank-Version des Plugins', 'container-block-designer'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('is_default Feld', 'container-block-designer'); ?></th>
                <td>
                    <?php if ($is_default_exists): ?>
                        <span style="color: green; font-weight: bold;">✅ <?php _e('Vorhanden', 'container-block-designer'); ?></span>
                        <p class="description"><?php _e('Das Feld für Standard-Block-Auswahl existiert.', 'container-block-designer'); ?></p>
                    <?php else: ?>
                        <span style="color: red; font-weight: bold;">❌ <?php _e('Fehlt', 'container-block-designer'); ?></span>
                        <p class="description"><?php _e('Das Feld muss hinzugefügt werden, damit die Standard-Block-Funktion funktioniert.', 'container-block-designer'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Klassen-Tabellen (v3.0.0)', 'container-block-designer'); ?></th>
                <td>
                    <?php foreach ($classroom_tables_status as $name => $exists): ?>
                        <?php if ($exists): ?>
                            <span style="color: green;">✅ <?php echo esc_html($name); ?></span><br>
                        <?php else: ?>
                            <span style="color: red;">❌ <?php echo esc_html($name); ?></span><br>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php if ($classroom_tables_exist): ?>
                            <?php _e('Alle Tabellen für das Klassen-System sind vorhanden.', 'container-block-designer'); ?>
                        <?php else: ?>
                            <?php _e('Einige Tabellen fehlen. Migration erforderlich.', 'container-block-designer'); ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Migration', 'container-block-designer'); ?></th>
                <td>
                    <?php if ($needs_migration): ?>
                        <form method="post" action="">
                            <?php wp_nonce_field('cbd_run_migration', 'cbd_migration_nonce'); ?>
                            <input type="submit" name="cbd_run_migration" class="button button-primary" value="<?php esc_attr_e('Alle Migrationen durchführen', 'container-block-designer'); ?>">
                        </form>
                        <p class="description">
                            <?php _e('Führt alle fehlenden Datenbank-Migrationen durch (v2.9.0 + v3.0.0 Klassen-System).', 'container-block-designer'); ?>
                        </p>
                    <?php else: ?>
                        <span style="color: green;">✅ <?php _e('Alle Migrationen abgeschlossen', 'container-block-designer'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Allgemeine Einstellungen -->
    <form method="post" action="">
        <?php wp_nonce_field('cbd_settings', 'cbd_settings_nonce'); ?>

        <div class="cbd-card" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h2><?php _e('Allgemeine Einstellungen', 'container-block-designer'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Standard-Blockstatus', 'container-block-designer'); ?></th>
                    <td>
                        <select name="default_block_status">
                            <option value="draft" <?php selected($default_status, 'draft'); ?>><?php _e('Entwurf', 'container-block-designer'); ?></option>
                            <option value="active" <?php selected($default_status, 'active'); ?>><?php _e('Aktiv', 'container-block-designer'); ?></option>
                            <option value="inactive" <?php selected($default_status, 'inactive'); ?>><?php _e('Inaktiv', 'container-block-designer'); ?></option>
                        </select>
                        <p class="description"><?php _e('Standard-Status für neue Blocks', 'container-block-designer'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Block-Caching', 'container-block-designer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_block_caching" value="1" <?php checked($enable_caching, 1); ?>>
                            <?php _e('Block-Caching aktivieren', 'container-block-designer'); ?>
                        </label>
                        <p class="description"><?php _e('Verbessert die Performance durch Zwischenspeicherung von Block-Daten', 'container-block-designer'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Debug-Modus', 'container-block-designer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_debug_mode" value="1" <?php checked($debug_mode, 1); ?>>
                            <?php _e('Debug-Modus aktivieren', 'container-block-designer'); ?>
                        </label>
                        <p class="description"><?php _e('Zeigt zusätzliche Debug-Informationen in der Browser-Konsole', 'container-block-designer'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Klassen-System', 'container-block-designer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="classroom_enabled" value="1" <?php checked($classroom_enabled, 1); ?>>
                            <?php _e('Klassen-System aktivieren', 'container-block-designer'); ?>
                        </label>
                        <p class="description"><?php _e('Ermöglicht Lehrern, Klassen zu erstellen, Notizen zu speichern und behandelte Themen zu markieren', 'container-block-designer'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Daten bei Deinstallation löschen', 'container-block-designer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="remove_data_on_uninstall" value="1" <?php checked($remove_data, 1); ?>>
                            <?php _e('Alle Plugin-Daten bei Deinstallation entfernen', 'container-block-designer'); ?>
                        </label>
                        <p class="description"><?php _e('Warnung: Alle Blocks und Einstellungen werden unwiderruflich gelöscht!', 'container-block-designer'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Einstellungen speichern', 'container-block-designer'), 'primary', 'cbd_save_settings'); ?>
        </div>
    </form>
</div>
