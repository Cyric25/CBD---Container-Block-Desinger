<?php
/**
 * Ansicht: Seiten aus Markdown importieren
 *
 * Wird von CBD_Page_Importer::seite_ausgeben() eingebunden. Die Hüllen sind
 * leer – gefüllt werden sie von assets/js/page-importer.js. Die IDs und
 * Klassennamen sind fest vereinbart; werden sie geändert, greifen JavaScript
 * und Gestaltung ins Leere.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.86
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$anzahl_designs = 0;
if (defined('CBD_TABLE_BLOCKS')) {
    $anzahl_designs = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'"
    );
}
?>
<div class="wrap cbd-pi-wrap">
    <h1><?php esc_html_e('Seiten aus Markdown importieren', 'container-block-designer'); ?></h1>

    <p class="description">
        <?php esc_html_e('Aus jeder Markdown-Datei entsteht eine Seite. Der Seitentitel ist die erste „# “-Zeile der Datei; fehlt sie, wird der Dateiname verwendet. Alle Seiten werden als Entwurf angelegt und überschreiben nichts – auf oberster Ebene, sofern unten keine Elternseite gewählt wird.', 'container-block-designer'); ?>
    </p>

    <?php if ($anzahl_designs === 0) : ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('Es sind keine aktiven Block-Designs vorhanden. Der Inhalt wird dann ohne Container eingefügt und lässt sich später Containern zuweisen.', 'container-block-designer'); ?>
            </p>
        </div>
    <?php endif; ?>

    <div id="cbd-page-import-app">

        <div class="cbd-pi-elternseite" id="cbd-pi-elternseite">
            <label id="cbd-pi-elternseite-label"><?php esc_html_e('Elternseite', 'container-block-designer'); ?></label>
            <input type="hidden" id="cbd-import-parent" name="cbd-import-parent" value="0">
            <div id="cbd-pi-kaskade" class="cbd-pi-kaskade" aria-live="polite" aria-labelledby="cbd-pi-elternseite-label">
                <p class="cbd-pi-kaskade-status"><?php esc_html_e('Lade Seitenbaum …', 'container-block-designer'); ?></p>
            </div>
            <p class="description">
                <?php esc_html_e('Gilt für den gesamten Lauf: Alle Seiten, die aus den unten gewählten Dateien entstehen, bekommen dieselbe Elternseite. Jede Ebene erscheint erst, nachdem in der vorherigen eine Seite gewählt wurde.', 'container-block-designer'); ?>
            </p>
        </div>

        <div class="cbd-pi-dropzone" id="cbd-pi-dropzone">
            <p class="cbd-pi-dropzone-icon" aria-hidden="true">📄</p>
            <p class="cbd-pi-dropzone-text">
                <?php esc_html_e('Markdown-Dateien hierher ziehen', 'container-block-designer'); ?>
            </p>
            <p class="cbd-pi-dropzone-or"><?php esc_html_e('oder', 'container-block-designer'); ?></p>
            <label class="button button-secondary cbd-pi-dateibutton">
                <?php esc_html_e('Dateien auswählen', 'container-block-designer'); ?>
                <input type="file" id="cbd-pi-dateiauswahl" multiple accept=".md,.txt" style="display:none;" />
            </label>
        </div>

        <div class="cbd-pi-dateiliste" id="cbd-pi-dateiliste"></div>

        <div class="cbd-pi-gruppen" id="cbd-pi-gruppen"></div>

        <div class="cbd-pi-fortschritt" id="cbd-pi-fortschritt" hidden></div>

        <div class="cbd-pi-ergebnis" id="cbd-pi-ergebnis" hidden></div>

        <div class="cbd-pi-aktionen" id="cbd-pi-aktionen"></div>

    </div>
</div>
