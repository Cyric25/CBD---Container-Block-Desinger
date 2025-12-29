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
    global $wpdb;
    $table_name = $wpdb->prefix . 'cbd_blocks';
    $migration_result = array('success' => true, 'messages' => array());

    // Prüfe ob Feld bereits existiert
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

    if (!in_array('is_default', $columns)) {
        // Feld hinzufügen
        $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN `is_default` tinyint(1) DEFAULT 0 AFTER `status`");

        if ($result === false) {
            $migration_result['success'] = false;
            $migration_result['messages'][] = __('Fehler beim Hinzufügen des is_default Feldes:', 'container-block-designer') . ' ' . $wpdb->last_error;
        } else {
            $migration_result['messages'][] = __('Feld "is_default" erfolgreich hinzugefügt', 'container-block-designer');

            // Index hinzufügen
            $wpdb->query("ALTER TABLE $table_name ADD KEY `is_default` (`is_default`)");
            $migration_result['messages'][] = __('Index hinzugefügt', 'container-block-designer');

            // DB-Version aktualisieren
            update_option('cbd_db_version', '2.9.0');
            $migration_result['messages'][] = __('Datenbank-Version auf 2.9.0 aktualisiert', 'container-block-designer');
        }
    } else {
        $migration_result['messages'][] = __('Migration bereits durchgeführt - Feld existiert bereits', 'container-block-designer');
    }
}

// Einstellungen speichern
if (isset($_POST['cbd_save_settings']) && wp_verify_nonce($_POST['cbd_settings_nonce'], 'cbd_settings')) {
    update_option('cbd_remove_data_on_uninstall', isset($_POST['remove_data_on_uninstall']) ? 1 : 0);
    update_option('cbd_enable_debug_mode', isset($_POST['enable_debug_mode']) ? 1 : 0);
    update_option('cbd_default_block_status', sanitize_text_field($_POST['default_block_status']));
    update_option('cbd_enable_block_caching', isset($_POST['enable_block_caching']) ? 1 : 0);

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

// Datenbank-Status prüfen
global $wpdb;
$table_name = $wpdb->prefix . 'cbd_blocks';
$columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
$is_default_exists = in_array('is_default', $columns);
$db_version = get_option('cbd_db_version', '0');
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
                <th scope="row"><?php _e('Migration', 'container-block-designer'); ?></th>
                <td>
                    <?php if (!$is_default_exists): ?>
                        <form method="post" action="">
                            <?php wp_nonce_field('cbd_run_migration', 'cbd_migration_nonce'); ?>
                            <input type="submit" name="cbd_run_migration" class="button button-primary" value="<?php esc_attr_e('Datenbank auf Version 2.9.0 aktualisieren', 'container-block-designer'); ?>">
                        </form>
                        <p class="description">
                            <?php _e('Fügt das "is_default" Feld zur Datenbank hinzu, damit die Standard-Block-Auswahl funktioniert.', 'container-block-designer'); ?>
                        </p>
                    <?php else: ?>
                        <span style="color: green;">✅ <?php _e('Keine Migration erforderlich', 'container-block-designer'); ?></span>
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
