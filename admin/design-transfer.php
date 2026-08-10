<?php
/**
 * Admin-Seite: Designs exportieren und importieren
 *
 * Reine Darstellung — die Verarbeitung steckt in CBD_Design_Transfer.
 *
 * @package ContainerBlockDesigner
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can(CBD_Design_Transfer::CAPABILITY)) {
    wp_die(esc_html__('Du hast keine Berechtigung, diese Seite zu sehen.', 'container-block-designer'));
}

$cbd_designs = CBD_Design_Transfer::get_all_designs();
$cbd_staged  = get_transient(CBD_Design_Transfer::staging_key());
$cbd_preview = (isset($_GET['cbd_preview']) && is_array($cbd_staged) && !empty($cbd_staged));

$cbd_errors = array(
    'nofile'     => __('Es wurde keine Datei ausgewählt.', 'container-block-designer'),
    'upload'     => __('Der Upload ist fehlgeschlagen.', 'container-block-designer'),
    'toobig'     => __('Die Datei ist zu groß (maximal 5 MB).', 'container-block-designer'),
    'unreadable' => __('Die Datei konnte nicht gelesen werden.', 'container-block-designer'),
    'payload'    => __('Die Datei wurde abgelehnt.', 'container-block-designer'),
    'expired'    => __('Die Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.', 'container-block-designer'),
    'noexport'   => __('Es gibt keine Designs zum Exportieren.', 'container-block-designer'),
    'encode'     => __('Der Export konnte nicht erzeugt werden.', 'container-block-designer'),
);
?>
<div class="wrap cbd-design-transfer">
    <h1><?php esc_html_e('Designs exportieren / importieren', 'container-block-designer'); ?></h1>

    <?php if (isset($_GET['cbd_error']) && isset($cbd_errors[sanitize_key($_GET['cbd_error'])])) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($cbd_errors[sanitize_key($_GET['cbd_error'])]); ?></p>
            <?php
            $cbd_detail = get_transient(CBD_Design_Transfer::staging_key() . '_error');
            if ($cbd_detail) {
                delete_transient(CBD_Design_Transfer::staging_key() . '_error');
                echo '<p><em>' . esc_html($cbd_detail) . '</em></p>';
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['cbd_created'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php
                printf(
                    esc_html__('Import abgeschlossen: %1$d neu angelegt, %2$d überschrieben, %3$d übersprungen.', 'container-block-designer'),
                    (int) $_GET['cbd_created'],
                    isset($_GET['cbd_updated']) ? (int) $_GET['cbd_updated'] : 0,
                    isset($_GET['cbd_skipped']) ? (int) $_GET['cbd_skipped'] : 0
                );
            ?></p>
        </div>
    <?php endif; ?>

    <div class="cbd-transfer-intro">
        <p><?php esc_html_e('Übertragen werden die Design-Vorlagen — also Aussehen und Funktionen der Container. Seiteninhalte, platzierte Blöcke, Klassen und Zeichnungen gehören nicht dazu.', 'container-block-designer'); ?></p>
        <p class="description">
            <strong><?php esc_html_e('Der Slug entscheidet:', 'container-block-designer'); ?></strong>
            <?php esc_html_e('Bestehende Seiten verweisen über den Slug auf ihr Design. Wird ein Design unter einem anderen Slug importiert, bleiben diese Container ungestylt.', 'container-block-designer'); ?>
        </p>
        <p class="description">
            <?php esc_html_e('Standardformat ist Markdown (.md): lesbar, versionierbar und von Hand änderbar. JSON funktioniert unverändert weiter — beim Import erkennt die Seite am Inhalt, welches Format vorliegt.', 'container-block-designer'); ?>
        </p>
    </div>

    <?php if ($cbd_preview) : ?>
        <?php $cbd_rows = CBD_Design_Transfer::build_preview($cbd_staged); ?>
        <?php $cbd_conflicts = count(array_filter(wp_list_pluck($cbd_rows, 'conflict'))); ?>

        <h2><?php esc_html_e('Vorschau des Imports', 'container-block-designer'); ?></h2>

        <div class="notice notice-info inline">
            <p><?php
                printf(
                    esc_html__('%1$d Designs in der Datei, davon %2$d mit gleichem Slug wie ein vorhandenes Design.', 'container-block-designer'),
                    count($cbd_rows),
                    (int) $cbd_conflicts
                );
            ?></p>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="cbd_designs_import">
            <?php wp_nonce_field('cbd_designs_import'); ?>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <td class="check-column"><input type="checkbox" checked disabled title="<?php esc_attr_e('Auswahl unten', 'container-block-designer'); ?>"></td>
                        <th><?php esc_html_e('Slug', 'container-block-designer'); ?></th>
                        <th><?php esc_html_e('Titel', 'container-block-designer'); ?></th>
                        <th><?php esc_html_e('Status', 'container-block-designer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cbd_rows as $cbd_row) : ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="cbd_designs[]" value="<?php echo esc_attr($cbd_row['name']); ?>" checked>
                            </th>
                            <td><code><?php echo esc_html($cbd_row['name']); ?></code></td>
                            <td><?php echo esc_html($cbd_row['title']); ?></td>
                            <td>
                                <?php if ($cbd_row['conflict']) : ?>
                                    <span class="cbd-conflict">
                                        <?php
                                        printf(
                                            esc_html__('Slug existiert bereits („%s")', 'container-block-designer'),
                                            esc_html($cbd_row['existing'])
                                        );
                                        ?>
                                    </span>
                                <?php else : ?>
                                    <span class="cbd-new"><?php esc_html_e('neu', 'container-block-designer'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($cbd_conflicts > 0) : ?>
                <h3><?php esc_html_e('Was soll bei gleichem Slug passieren?', 'container-block-designer'); ?></h3>
                <p>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="radio" name="cbd_conflict_mode" value="skip" checked>
                        <strong><?php esc_html_e('Überspringen', 'container-block-designer'); ?></strong> —
                        <?php esc_html_e('vorhandenes Design bleibt unverändert', 'container-block-designer'); ?>
                    </label>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="radio" name="cbd_conflict_mode" value="overwrite">
                        <strong><?php esc_html_e('Überschreiben', 'container-block-designer'); ?></strong> —
                        <?php esc_html_e('vorhandenes Design wird ersetzt; Seiten übernehmen sofort das neue Aussehen', 'container-block-designer'); ?>
                    </label>
                    <label style="display:block;">
                        <input type="radio" name="cbd_conflict_mode" value="copy">
                        <strong><?php esc_html_e('Als Kopie anlegen', 'container-block-designer'); ?></strong> —
                        <?php esc_html_e('neuer Slug mit angehängter Nummer; bestehende Seiten bleiben beim alten Design', 'container-block-designer'); ?>
                    </label>
                </p>
            <?php else : ?>
                <input type="hidden" name="cbd_conflict_mode" value="skip">
            <?php endif; ?>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('Import ausführen', 'container-block-designer'); ?></button>
                <a href="<?php echo esc_url(CBD_Design_Transfer::page_url()); ?>" class="button"><?php esc_html_e('Abbrechen', 'container-block-designer'); ?></a>
            </p>
        </form>

        <hr>
    <?php endif; ?>

    <h2><?php esc_html_e('Exportieren', 'container-block-designer'); ?></h2>

    <?php if (empty($cbd_designs)) : ?>
        <p class="description"><?php esc_html_e('Es sind noch keine Designs vorhanden.', 'container-block-designer'); ?></p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="cbd_designs_export">
            <?php wp_nonce_field('cbd_designs_export'); ?>

            <p class="description">
                <?php esc_html_e('Ohne Auswahl werden alle Designs exportiert.', 'container-block-designer'); ?>
            </p>

            <table class="wp-list-table widefat striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <td class="check-column"></td>
                        <th><?php esc_html_e('Slug', 'container-block-designer'); ?></th>
                        <th><?php esc_html_e('Titel', 'container-block-designer'); ?></th>
                        <th><?php esc_html_e('Status', 'container-block-designer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cbd_designs as $cbd_design) : ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="cbd_designs[]" value="<?php echo esc_attr($cbd_design['name']); ?>">
                            </th>
                            <td><code><?php echo esc_html($cbd_design['name']); ?></code></td>
                            <td><?php echo esc_html($cbd_design['title']); ?></td>
                            <td><?php echo esc_html($cbd_design['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" name="cbd_format" value="md" class="button button-primary">
                    <?php esc_html_e('Als Markdown herunterladen', 'container-block-designer'); ?>
                </button>
                <button type="submit" name="cbd_format" value="json" class="button">
                    <?php esc_html_e('Als JSON herunterladen', 'container-block-designer'); ?>
                </button>
            </p>
        </form>
    <?php endif; ?>

    <hr>

    <h2><?php esc_html_e('Importieren', 'container-block-designer'); ?></h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="cbd_designs_upload">
        <?php wp_nonce_field('cbd_designs_upload'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="cbd_import_file"><?php esc_html_e('Datei (.md oder .json)', 'container-block-designer'); ?></label></th>
                <td>
                    <input type="file" name="cbd_import_file" id="cbd_import_file" accept=".md,.markdown,.txt,.json,text/markdown,application/json" required>
                    <p class="description">
                        <?php esc_html_e('Eine zuvor hier exportierte oder eine selbst geschriebene Markdown-Datei. Nach dem Hochladen erscheint eine Vorschau — erst danach wird etwas geschrieben.', 'container-block-designer'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Datei prüfen', 'container-block-designer'), 'secondary'); ?>
    </form>

    <details class="cbd-transfer-format">
        <summary><?php esc_html_e('Aufbau der Markdown-Datei', 'container-block-designer'); ?></summary>
        <ul class="cbd-transfer-rules">
            <li><?php esc_html_e('Jede Überschrift der Ebene 2 ist ein Design. Fehlt die Zeile „Slug", wird er aus der Überschrift gebildet.', 'container-block-designer'); ?></li>
            <li><?php esc_html_e('Werte stehen als Punkt-Pfade. Schlüssel dürfen nur Buchstaben, Ziffern, Bindestrich, Unterstrich und Punkt enthalten — Zeilen mit anderen Zeichen werden übergangen.', 'container-block-designer'); ?></li>
            <li><?php esc_html_e('true/false (auch ja/nein) sind Wahrheitswerte, reine Zahlen sind Zahlen, alles andere ist Text. Anführungszeichen erzwingen Text: "true" bleibt das Wort.', 'container-block-designer'); ?></li>
            <li><?php esc_html_e('Überschreiben ersetzt das Design vollständig: Was nicht in der Datei steht, ist danach nicht mehr gesetzt.', 'container-block-designer'); ?></li>
            <li><?php esc_html_e('„Standard" wird gelesen, aber nicht übernommen — welches Design das Standarddesign ist, bleibt Sache dieser Installation.', 'container-block-designer'); ?></li>
        </ul>
        <pre><code>## Info-Box

- **Slug:** `info-box`
- **Status:** aktiv
- **Standard:** nein

Hinweiskasten für Merksätze.

### Konfiguration

- `allowInnerBlocks`: true
- `maxWidth`: 900px

### Stile

- `background.color`: #f5ede9
- `border.width`: 2
- `border.color`: #e24614
- `padding.top`: 20

### Funktionen

- `icon.enabled`: true
- `icon.value`: {"type":"custom","value":"kategorien/hinweise"}
- `collapse.enabled`: false</code></pre>
    </details>
</div>

<style>
.cbd-transfer-intro {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-left: 4px solid #e24614;
    padding: 12px 16px;
    margin: 16px 0 24px;
    max-width: 900px;
}
.cbd-design-transfer .cbd-conflict { color: #b53810; font-weight: 600; }
.cbd-design-transfer .cbd-new { color: #1e7a1e; }
.cbd-design-transfer h2 { margin-top: 28px; }
.cbd-transfer-format {
    max-width: 900px;
    margin: 16px 0 24px;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid #c3c4c7;
}
.cbd-transfer-format summary { cursor: pointer; font-weight: 600; }
.cbd-transfer-rules { margin: 12px 0; list-style: disc; padding-left: 20px; color: #50575e; }
.cbd-transfer-format pre {
    margin: 0;
    padding: 12px;
    overflow-x: auto;
    background: #f6f7f7;
    border: 1px solid #dcdcde;
}
</style>
