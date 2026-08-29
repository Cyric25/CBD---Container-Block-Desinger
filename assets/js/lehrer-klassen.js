/**
 * Container Block Designer - Seitenauswahl der Frontend-Klassenverwaltung
 *
 * Einzige Aufgabe: die <option>-Elemente der Seitenzuordnung aus
 * window.cbdLehrerKlassen.seiten nachtragen. Die eigentliche Verwaltung leistet
 * unveraendert assets/js/classroom-admin.js — dieselbe Datei wie im Adminbereich.
 *
 * WARUM DIESE DATEI UEBERHAUPT EXISTIERT: Die Seitenliste darf nicht als Markup
 * im Seiteninhalt stehen. Sie liefe dann durch `the_content` und damit durch die
 * Glossar-Autoverlinkung des Themes, die den Inhalt in Textstuecke zerlegt und
 * auf jedes einen aus allen Glossarbegriffen gebauten regulaeren Ausdruck
 * anwendet. Gemessen auf der Testinstallation (281 Seiten, 1155 Begriffe): HTTP
 * 500 nach 30 Sekunden, reproduzierbar. Ausfuehrliche Begruendung im PHP:
 * CBD_Classroom::lehrer_klassen_seitendaten().
 *
 * ZEITPUNKT: Laeuft synchron beim Parsen im Footer, also nach dem Markup und vor
 * dem $(document).ready() von classroom-admin.js. Dessen init() klont
 * .cbd-page-selector als Vorlage fuer jede weitere Zeile — die Vorlage muss die
 * Optionen also bereits enthalten. Die Reihenfolge sichert zusaetzlich die
 * Skript-Abhaengigkeit (`cbd-classroom-admin` haengt von `cbd-lehrer-klassen`).
 *
 * @package ContainerBlockDesigner
 * @since 3.1.115
 */

(function () {
    'use strict';

    var daten = window.cbdLehrerKlassen;

    if (!daten || !daten.seiten || !daten.seiten.length) {
        return;
    }

    var auswahl = document.querySelector('.cbd-lehrer-klassen .cbd-page-select');

    if (!auswahl) {
        return;
    }

    // Zusammenhangloses Leerzeichen (U+00A0) statt &nbsp;, weil hier ueber
    // textContent geschrieben wird und keine HTML-Entities aufgeloest wuerden.
    var EINZUG = '   ';
    // U+25BC BLACK DOWN-POINTING TRIANGLE — Kennzeichen fuer "hat Unterseiten",
    // zeichengleich mit admin/classroom.php.
    var MARKER = ' ▼';

    var sammler = document.createDocumentFragment();

    daten.seiten.forEach(function (seite) {
        var option = document.createElement('option');
        var tiefe = parseInt(seite.tiefe, 10);
        var einzug = '';
        var i;

        if (!(tiefe > 0)) {
            tiefe = 0;
        }

        for (i = 0; i < tiefe; i++) {
            einzug += EINZUG;
        }

        option.value = String(seite.id);
        // getChildPages() in classroom-admin.js liest diesen Wert ueber
        // $option.data('parent') — der Name des Attributs ist Vertrag.
        option.setAttribute('data-parent', String(seite.parent));
        option.textContent = einzug + String(seite.titel) + (seite.kinder ? MARKER : '');

        sammler.appendChild(option);
    });

    auswahl.appendChild(sammler);
})();
