<?php
/**
 * Container Block Designer - Klassen-Verwaltung (Admin)
 *
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Pruefen ob Klassen-System aktiviert ist
$classroom_enabled = get_option('cbd_classroom_enabled', false);
?>
<div class="wrap cbd-classroom-admin">
    <h1><?php _e('Klassen-Verwaltung', 'container-block-designer'); ?></h1>

    <?php if (!$classroom_enabled): ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('Das Klassen-System ist derzeit deaktiviert.', 'container-block-designer'); ?>
                <a href="<?php echo admin_url('admin.php?page=cbd-settings'); ?>">
                    <?php _e('In den Einstellungen aktivieren', 'container-block-designer'); ?>
                </a>
            </p>
        </div>
    <?php else: ?>

    <div class="cbd-classroom-wrapper">
        <!-- Neue Klasse erstellen -->
        <div class="cbd-classroom-form-section">
            <h2 id="cbd-form-title"><?php _e('Neue Klasse erstellen', 'container-block-designer'); ?></h2>
            <form id="cbd-class-form" class="cbd-class-form">
                <input type="hidden" id="cbd-class-id" value="0">
                <table class="form-table">
                    <tr>
                        <th><label for="cbd-class-name"><?php _e('Klassenname', 'container-block-designer'); ?></label></th>
                        <td>
                            <input type="text" id="cbd-class-name" class="regular-text" required
                                   placeholder="<?php esc_attr_e('z.B. 3a Chemie 2026', 'container-block-designer'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbd-class-password"><?php _e('Passwort', 'container-block-designer'); ?></label></th>
                        <td>
                            <input type="text" id="cbd-class-password" class="regular-text"
                                   placeholder="<?php esc_attr_e('Passwort fuer Schueler-Zugang', 'container-block-designer'); ?>">
                            <p class="description" id="cbd-password-hint">
                                <?php _e('Dieses Passwort benoetigen die Schueler zum Zugriff auf die Klasse.', 'container-block-designer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Zugeordnete Seiten', 'container-block-designer'); ?></label></th>
                        <td>
                            <div id="cbd-class-pages" class="cbd-class-pages">
                                <div class="cbd-page-selector">
                                    <select class="cbd-page-select">
                                        <option value=""><?php _e('-- Seite waehlen --', 'container-block-designer'); ?></option>
                                        <?php
                                        $pages = get_pages(array('sort_column' => 'post_title', 'post_status' => 'publish'));
                                        foreach ($pages as $page) {
                                            echo '<option value="' . esc_attr($page->ID) . '">' . esc_html($page->post_title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button type="button" class="button cbd-remove-page" title="<?php esc_attr_e('Entfernen', 'container-block-designer'); ?>">&times;</button>
                                </div>
                            </div>
                            <button type="button" id="cbd-add-page" class="button">
                                + <?php _e('Seite hinzufuegen', 'container-block-designer'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" id="cbd-save-class">
                        <?php _e('Klasse speichern', 'container-block-designer'); ?>
                    </button>
                    <button type="button" class="button" id="cbd-cancel-edit" style="display:none;">
                        <?php _e('Abbrechen', 'container-block-designer'); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- Klassen-Liste -->
        <div class="cbd-classroom-list-section">
            <h2><?php _e('Meine Klassen', 'container-block-designer'); ?></h2>
            <div id="cbd-classes-loading" class="cbd-loading">
                <span class="spinner is-active"></span>
                <?php _e('Lade Klassen...', 'container-block-designer'); ?>
            </div>
            <table class="wp-list-table widefat fixed striped" id="cbd-classes-table" style="display:none;">
                <thead>
                    <tr>
                        <th class="column-name"><?php _e('Name', 'container-block-designer'); ?></th>
                        <th class="column-pages"><?php _e('Seiten', 'container-block-designer'); ?></th>
                        <th class="column-status"><?php _e('Status', 'container-block-designer'); ?></th>
                        <th class="column-created"><?php _e('Erstellt', 'container-block-designer'); ?></th>
                        <th class="column-actions"><?php _e('Aktionen', 'container-block-designer'); ?></th>
                    </tr>
                </thead>
                <tbody id="cbd-classes-body">
                </tbody>
            </table>
            <p id="cbd-no-classes" style="display:none;">
                <?php _e('Noch keine Klassen erstellt.', 'container-block-designer'); ?>
            </p>
        </div>
    </div>

    <?php endif; ?>
</div>
