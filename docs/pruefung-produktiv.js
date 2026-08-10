/**
 * Container Block Designer – Bestandsaufnahme der Produktivinstallation
 *
 * Beantwortet zwei Fragen, die sich aus dem Code allein nicht beantworten
 * lassen und die das Vorhaben „Seitenimport aus Markdown" blockieren
 * (siehe docs/PLAN-Seitenimport.md, AP-1.0.fix1):
 *
 *   1. Welche WordPress-Version läuft? Davon hängt das Blockmarkup ab, das der
 *      Seitenimporter erzeugen muss.
 *   2. Welche Spalten hat die Tabelle `<präfix>cbd_blocks`? Der Schema-Manager
 *      legt `name` und `title` an, mehrere Abfragen im Plugin verlangen aber
 *      eine Spalte `slug`. Auf einer frisch aufgesetzten Installation
 *      scheitern sie deshalb.
 *
 * ANWENDUNG
 *   1. Als Administrator auf der Website anmelden.
 *   2. Irgendeine Seite im WP-Adminbereich öffnen (Dashboard genügt).
 *      Noch besser: eine Seite im Blockeditor öffnen – dann läuft
 *      zusätzlich Prüfung D.
 *   3. Entwicklerwerkzeuge öffnen (F12) → Reiter „Konsole".
 *   4. Diese Datei vollständig hineinkopieren und Enter drücken.
 *   5. Den ausgegebenen Textblock kopieren und zurückschicken.
 *
 * Das Skript ist ausschließlich LESEND. Es ruft nur Ansichten ab und wertet
 * sie aus – es speichert nichts, ändert nichts und löst insbesondere die
 * Reparatur auf der Seite „Datenbank reparieren" NICHT aus.
 *
 * @package ContainerBlockDesigner
 */

(async function () {
    'use strict';

    const ergebnis = {};
    const hinweise = [];

    /** Kurzschreibweise: Text säubern (Mehrfach-Leerraum zu einem Leerzeichen). */
    const sauber = (t) => (t || '').replace(/\s+/g, ' ').trim();

    /** Eine Admin-Seite abrufen und als DOM zurückgeben. */
    async function holeSeite(pfad) {
        const antwort = await fetch(pfad, { credentials: 'same-origin' });
        if (!antwort.ok) {
            throw new Error('HTTP ' + antwort.status + ' bei ' + pfad);
        }
        const text = await antwort.text();
        return new DOMParser().parseFromString(text, 'text/html');
    }

    /** Basispfad des Adminbereichs ermitteln (funktioniert auch in Unterordner-Installationen). */
    function adminBasis() {
        if (typeof ajaxurl === 'string' && ajaxurl) {
            return ajaxurl.replace(/admin-ajax\.php.*$/, '');
        }
        const treffer = window.location.pathname.match(/^(.*\/wp-admin\/)/);
        return treffer ? treffer[1] : '/wp-admin/';
    }

    // ----------------------------------------------------------------
    // Voraussetzungen
    // ----------------------------------------------------------------
    if (!/\/wp-admin\//.test(window.location.pathname)) {
        console.error(
            '%cAbbruch: Das Skript muss im WordPress-Adminbereich laufen.',
            'color:#b32d0f;font-weight:bold'
        );
        console.info('Bitte das Dashboard öffnen (…/wp-admin/) und erneut einfügen.');
        return;
    }

    const BASIS = adminBasis();
    console.log('%cBestandsaufnahme läuft …', 'color:#e24614;font-weight:bold');

    // ----------------------------------------------------------------
    // A) WordPress-Version
    //    Steht in der Fußzeile jeder Adminseite (#footer-upgrade), z. B.
    //    „Version 6.4.3". Fehlt sie (manche Themes/Plugins blenden sie aus),
    //    wird die Info-Seite „Über WordPress" gelesen.
    // ----------------------------------------------------------------
    try {
        let roh = sauber(document.querySelector('#footer-upgrade')?.textContent);

        if (!/\d+\.\d+/.test(roh)) {
            const dom = await holeSeite(BASIS + 'about.php');
            roh = sauber(dom.querySelector('.about__header-title, .wp-heading-inline, h1')?.textContent);
        }

        const treffer = roh.match(/(\d+\.\d+(?:\.\d+)?)/);
        ergebnis['WordPress-Version'] = treffer ? treffer[1] : 'nicht ermittelbar (Rohtext: ' + roh + ')';
    } catch (fehler) {
        ergebnis['WordPress-Version'] = 'nicht ermittelbar (' + fehler.message + ')';
    }

    // ----------------------------------------------------------------
    // B) Spalten der Tabelle cbd_blocks
    //    Die Seite „Datenbank reparieren" gibt sie bereits aus
    //    (includes/class-cbd-admin.php, render_database_repair_page()).
    //    Wir lesen die Zeile „Aktuelle Spalten:" aus – ohne die Reparatur
    //    auszulösen, dafür wäre ein Formular-Absenden nötig.
    // ----------------------------------------------------------------
    try {
        const dom = await holeSeite(BASIS + 'admin.php?page=cbd-database-repair');

        // Die Werte stehen jeweils in der <td>-Zelle NACH der Beschriftung.
        const werteNeben = (beschriftung) => {
            for (const zelle of dom.querySelectorAll('td')) {
                if (sauber(zelle.textContent).startsWith(beschriftung)) {
                    return sauber(zelle.nextElementSibling?.textContent);
                }
            }
            return null;
        };

        const spalten = werteNeben('Aktuelle Spalten');
        const anzahl = werteNeben('Anzahl Blocks');
        const existiert = werteNeben('Tabelle existiert');

        if (spalten === null && existiert === null) {
            ergebnis['Tabellenspalten'] =
                'Seite „Datenbank reparieren" nicht lesbar – ist das Plugin aktiv und bist du Administrator?';
        } else {
            ergebnis['Tabelle existiert'] = existiert || 'unbekannt';
            ergebnis['Tabellenspalten'] = spalten || 'keine ausgegeben';
            ergebnis['Anzahl Designs'] = anzahl || 'unbekannt';

            if (spalten) {
                const liste = spalten.split(',').map((s) => s.trim());
                const hatName = liste.includes('name');
                const hatSlug = liste.includes('slug');
                const hatTitle = liste.includes('title');

                ergebnis['Spalte name'] = hatName ? 'vorhanden' : 'FEHLT';
                ergebnis['Spalte slug'] = hatSlug ? 'vorhanden' : 'FEHLT';
                ergebnis['Spalte title'] = hatTitle ? 'vorhanden' : 'FEHLT';

                if (!hatSlug) {
                    hinweise.push(
                        'Die Spalte `slug` fehlt. Mehrere Abfragen im Plugin verlangen sie ' +
                        '(Content-Importer und die Nachschlage-Abfrage beim Rendern eines ' +
                        'Containers). Diese Abfragen laufen hier ins Leere.'
                    );
                }
                if (hatSlug && hatName) {
                    hinweise.push(
                        'Beide Spalten `name` und `slug` sind vorhanden – die Installation ' +
                        'stammt aus der Zeit vor der Schema-Vereinheitlichung. Wichtig ist ' +
                        'dann Prüfung C: welche der beiden trägt den Bezeichner.'
                    );
                }
            }
        }
    } catch (fehler) {
        ergebnis['Tabellenspalten'] = 'nicht ermittelbar (' + fehler.message + ')';
    }

    // ----------------------------------------------------------------
    // C) Beispielzeilen – welche Spalte trägt den Bezeichner?
    //    Die Blockübersicht zeigt Titel und Bezeichner nebeneinander. Wir
    //    lesen die ersten Zeilen der Tabelle aus und geben sie roh aus;
    //    daran ist erkennbar, ob der Bezeichner (z. B. „infotext_k1") in
    //    `name` oder in `slug` steht.
    // ----------------------------------------------------------------
    try {
        const dom = await holeSeite(BASIS + 'admin.php?page=container-block-designer');
        const zeilen = [...dom.querySelectorAll('table.wp-list-table tbody tr')].slice(0, 5);

        if (zeilen.length === 0) {
            ergebnis['Beispieldesigns'] = 'keine Tabelle gefunden (evtl. andere Ansicht)';
        } else {
            ergebnis['Beispieldesigns'] = zeilen
                .map((zeile) =>
                    [...zeile.querySelectorAll('td, th')]
                        .map((z) => sauber(z.textContent))
                        .filter((t) => t !== '')
                        .slice(0, 4)
                        .join('  |  ')
                )
                .join('\n                        ');
        }
    } catch (fehler) {
        ergebnis['Beispieldesigns'] = 'nicht ermittelbar (' + fehler.message + ')';
    }

    // ----------------------------------------------------------------
    // D) Nur im Blockeditor: Was liefert der Importer tatsächlich?
    //    Das ist die Entscheidungsprobe. Kommt hier eine leere Stilliste
    //    zurück, obwohl Designs angelegt sind, scheitert die Abfrage an der
    //    fehlenden Spalte – genau der Fehler, um den es geht.
    // ----------------------------------------------------------------
    if (window.cbdContentImporter && window.cbdContentImporter.nonce) {
        try {
            const daten = new FormData();
            daten.append('action', 'cbd_get_style_mappings');
            daten.append('nonce', window.cbdContentImporter.nonce);

            const antwort = await fetch(window.cbdContentImporter.ajaxUrl, {
                method: 'POST',
                body: daten,
                credentials: 'same-origin',
            });
            const json = await antwort.json();

            if (json && json.success) {
                const stile = json.data.styles || [];
                ergebnis['Importer: Stile'] = stile.length + ' Stück';
                ergebnis['Importer: hasStyles'] = String(json.data.hasStyles);
                ergebnis['Importer: erste Einträge'] = stile
                    .slice(0, 5)
                    .map((s) => 'value=' + s.value + '  label=' + s.label)
                    .join('\n                        ') || '(leer)';

                if (stile.length === 0) {
                    hinweise.push(
                        'Der Importer bietet KEINE Stile an, obwohl Designs angelegt sind. ' +
                        'Das ist der erwartete Effekt der fehlenden Spalte `slug`.'
                    );
                }
            } else {
                ergebnis['Importer: Stile'] = 'Anfrage abgelehnt (' + JSON.stringify(json).slice(0, 120) + ')';
            }
        } catch (fehler) {
            ergebnis['Importer: Stile'] = 'nicht ermittelbar (' + fehler.message + ')';
        }
    } else {
        ergebnis['Importer: Stile'] =
            'übersprungen – dafür das Skript in einer im Blockeditor geöffneten Seite ausführen';
    }

    // ----------------------------------------------------------------
    // E) Versionen von Plugin und Theme
    //    Aus der ?ver=-Angabe einer eingebundenen Plugin-Datei; das ist
    //    CBD_VERSION. Zuverlässiger als die Plugin-Liste zu zerlegen.
    // ----------------------------------------------------------------
    try {
        const treffer = [...document.querySelectorAll('link[href], script[src]')]
            .map((el) => el.href || el.src)
            .find((u) => u && u.indexOf('container-block-designer') !== -1 && u.indexOf('ver=') !== -1);
        ergebnis['CDB-Plugin-Version'] = treffer
            ? decodeURIComponent(treffer.split('ver=')[1].split('&')[0])
            : 'nicht ermittelbar (keine Plugin-Datei auf dieser Seite eingebunden)';
    } catch (fehler) {
        ergebnis['CDB-Plugin-Version'] = 'nicht ermittelbar (' + fehler.message + ')';
    }

    ergebnis['Adresse'] = window.location.origin;
    ergebnis['Tabellenpräfix (vermutet)'] = 'siehe Spaltenzeile oben';

    // ----------------------------------------------------------------
    // Ausgabe
    // ----------------------------------------------------------------
    let block = '--- CDB Bestandsaufnahme Produktiv ---\n';
    for (const [schluessel, wert] of Object.entries(ergebnis)) {
        block += schluessel.padEnd(24, ' ') + ': ' + wert + '\n';
    }
    if (hinweise.length) {
        block += '\nHinweise:\n';
        hinweise.forEach((h, i) => {
            block += '  ' + (i + 1) + '. ' + h + '\n';
        });
    }
    block += '--- Ende ---';

    console.log('%c\n' + block + '\n', 'font-family:monospace;font-size:12px');

    // Bequemlichkeit: direkt in die Zwischenablage, wo der Browser es erlaubt.
    try {
        await navigator.clipboard.writeText(block);
        console.log('%cIn die Zwischenablage kopiert – einfach einfügen.', 'color:#2a7d2a;font-weight:bold');
    } catch (fehler) {
        console.log(
            '%cBitte den Textblock oben markieren und kopieren ' +
            '(Zwischenablage nicht freigegeben: ' + fehler.message + ').',
            'color:#8a6d00'
        );
    }
})();
