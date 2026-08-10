/**
 * Container Block Designer – Seitenimporter (Oberfläche)
 *
 * Gehört zur Adminseite „Seitenmanager → Seiten importieren"
 * (admin/page-import.php, Steuerung in includes/class-cbd-page-importer.php).
 *
 * Bewusst schlichtes JavaScript ohne Build-Schritt und ohne React: Die Seite
 * ist keine Editor-Oberfläche, wp.element wäre unnötiger Ballast.
 * Debug-Ausgaben laufen über window.cbdDebug (Projektkonvention).
 *
 * @package ContainerBlockDesigner
 */

(function () {
    'use strict';

    if (typeof window.cbdPageImport === 'undefined') {
        return;
    }

    // Inhalt folgt in AP-2.2 (Dateiauswahl, Parsen, Dubletten),
    // AP-2.3 (Stil-Dialog) und AP-2.4 (Import ausführen).
})();
