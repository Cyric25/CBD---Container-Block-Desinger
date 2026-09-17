/**
 * Container Block Designer – Voraussetzungen für den „Schnellen Klassenpuls"
 *
 * Beantwortet so viel wie möglich von der Klickliste
 * `docs/KLICKLISTE-Voraussetzungen-KAS.md` (AP-0.2 aus
 * `PLAN-Schneller-Klassenpuls.md`) automatisch — damit von Hand nur noch das
 * übrig bleibt, was wirklich eine KAS-Anmeldung braucht.
 *
 * WAS DAS SKRIPT BEANTWORTET UND WAS NICHT
 *
 *   Frage 1  Produkt/Tarif .................. NEIN, nur im KAS sichtbar
 *   Frage 2  Cronjobs ....................... NEIN, nur im KAS sichtbar
 *   Frage 3  PHP-Version .................... JA
 *   Frage 4  .user.ini wirksam .............. NEIN, braucht Dateizugriff
 *   Frage 5  uploads beschreibbar ........... JA (indirekt, siehe unten)
 *   Frage 6  .json wird ausgeliefert ........ TEILWEISE (Sonde mit Kontrolle)
 *   Frage 7  Verzeichnisauflistung .......... JA
 *
 *   Zusätzlich, ungefragt, aber für den Plan wertvoll:
 *   - Zeitvergleich statische Datei gegen REST-Route. Das ist die Annahme,
 *     auf der das ganze Vorhaben steht (lokal gemessen: Faktor 62,6).
 *   - Zustand von WP_DEBUG_LOG. Auf der Produktivinstallation muss der
 *     **aus** sein: Jede Anfrage erzeugt sonst rund 34 Boot-Protokollzeilen.
 *
 * WIE FRAGE 5 OHNE SCHREIBVORGANG BEANTWORTET WIRD
 *   Das Plugin schreibt längst nach `uploads` — `container-block-designer/`
 *   (generiertes CSS, `class-cbd-style-loader.php`), `cbd-icons/`,
 *   `cbd-styles/`, `cbd-temp-pdfs/`. Antwortet eine dieser Dateien mit
 *   HTTP 200, ist zweierlei bewiesen: PHP durfte dort schreiben, und Apache
 *   liefert aus diesem Unterordner aus. Das ist stärker als ein
 *   Medien-Upload und ändert nichts.
 *
 * WIE FRAGE 6 GEPRÜFT WIRD (und warum nur teilweise)
 *   Eine `.json` lässt sich aus dem Browser heraus nicht nach `uploads`
 *   legen — WordPress lehnt den MIME-Typ beim Medien-Upload ab. Stattdessen
 *   wird eine **nicht vorhandene** `.json` abgefragt und mit einer ebenso
 *   nicht vorhandenen `.txt` verglichen (Kontrollgruppe):
 *
 *     .json 404 · .txt 404  → keine Sperre, alles normal
 *     .json 403 · .txt 404  → eine Regel sperrt gezielt `.json` — das wäre
 *                             das Aus für den geplanten Entwurf
 *     .json 403 · .txt 403  → der ganze Ordner ist gesperrt
 *
 *   Das ist ein starker Hinweis, aber kein Beweis: Eine Regel, die nur
 *   *vorhandene* `.json` sperrt, bliebe unentdeckt. Der Handtest aus Frage 6
 *   der Klickliste bleibt deshalb die letzte Instanz.
 *
 * ANWENDUNG
 *   1. Als Administrator auf der Website anmelden.
 *   2. Irgendeine Seite im WP-Adminbereich öffnen (Dashboard genügt).
 *   3. Entwicklerwerkzeuge öffnen (F12) → Reiter „Konsole".
 *   4. Diese Datei vollständig hineinkopieren und Enter drücken.
 *   5. Den ausgegebenen Textblock kopieren und zurückschicken.
 *
 * Das Skript ist ausschließlich LESEND. Es lädt nichts hoch, legt nichts an,
 * löscht nichts und ändert keine Einstellung. Es ruft nur Adressen ab und
 * wertet Statuscodes und Kopfzeilen aus.
 *
 * @package ContainerBlockDesigner
 */

(async function () {
    'use strict';

    const ergebnis = {};
    const hinweise = [];
    const herkunft = window.location.origin;

    /** Mehrfach-Leerraum zu einem Leerzeichen. */
    const sauber = (t) => (t || '').replace(/\s+/g, ' ').trim();

    /** Median einer Zahlenliste. */
    function median(werte) {
        const s = werte.slice().sort((a, b) => a - b);
        return s[Math.floor(s.length / 2)];
    }

    /**
     * Eine Adresse abrufen und Status, Kopfzeilen und (optional) Körper
     * zurückgeben. Wirft nicht — ein Netzfehler wird als Ergebnis gemeldet.
     */
    async function sondiere(adresse, mitKoerper) {
        try {
            const antwort = await fetch(adresse, {
                credentials: 'same-origin',
                cache: 'no-store',
            });
            return {
                ok: true,
                status: antwort.status,
                typ: antwort.headers.get('content-type') || '(keiner)',
                php: antwort.headers.get('x-powered-by') || '',
                server: antwort.headers.get('server') || '',
                koerper: mitKoerper ? await antwort.text() : '',
            };
        } catch (fehler) {
            return { ok: false, status: 0, fehler: fehler.message };
        }
    }

    /** Eine Admin-Seite abrufen und als DOM zurückgeben. */
    async function holeSeite(pfad) {
        const antwort = await fetch(pfad, { credentials: 'same-origin' });
        if (!antwort.ok) {
            throw new Error('HTTP ' + antwort.status + ' bei ' + pfad);
        }
        const text = await antwort.text();
        return new DOMParser().parseFromString(text, 'text/html');
    }

    /**
     * In der Systembericht-Seite (Website-Zustand → Bericht) eine Zeile
     * suchen.
     *
     * ACHTUNG, hier lag beim ersten Entwurf ein Fehler: Die Bezeichnung steht
     * in einem `<th>`, der Wert in einem `<td>` — ein Scan über
     * `<td>`-Paare findet die PHP-Version deshalb NICHT und greift
     * stattdessen quer über Zeilengrenzen daneben (im Test lieferte er als
     * „WordPress-Version" die Versionszeile eines Plugins). Deshalb wird
     * zeilenweise über `<tr>` gegangen und strikt `th` gegen `td` gestellt.
     *
     * Die Bezeichnung muss GENAU passen, nicht nur beginnen: „Version" käme
     * sonst in jeder Plugin-Zeile vor.
     */
    function zeileAusBericht(dokument, bezeichnungen) {
        const gesucht = bezeichnungen.map((b) => b.toLowerCase());
        for (const zeile of Array.from(dokument.querySelectorAll('tr'))) {
            const kopf = zeile.querySelector('th');
            const wert = zeile.querySelector('td');
            if (!kopf || !wert) {
                continue;
            }
            if (gesucht.indexOf(sauber(kopf.textContent).toLowerCase()) !== -1) {
                return sauber(wert.textContent);
            }
        }
        return '';
    }

    console.log('%cVoraussetzungsprüfung läuft …', 'color:#2271b1;font-weight:bold');

    // ----------------------------------------------------------------
    // A – Grunddaten
    // ----------------------------------------------------------------
    ergebnis['Adresse'] = herkunft;
    ergebnis['Verbindung'] = herkunft.indexOf('https://') === 0
        ? 'HTTPS'
        : 'NUR HTTP — für den Klassenmodus ungünstig';

    const startseite = await sondiere(herkunft + '/', false);
    ergebnis['Server-Software'] = startseite.server || '(nicht gemeldet)';

    // WordPress-Version aus der Fußzeile des Adminbereichs. Bewusst NICHT aus
    // dem Systembericht: Dort heißt die Zeile schlicht „Version" und kommt in
    // jedem Plugin-Abschnitt erneut vor — der erste Treffer war im Test die
    // Version eines Plugins, nicht die von WordPress.
    const fusszeile = document.querySelector('#footer-upgrade');
    ergebnis['WordPress-Version'] = fusszeile
        ? sauber(fusszeile.textContent)
        : '(nicht ermittelbar — läuft das Skript im Adminbereich?)';

    // ----------------------------------------------------------------
    // B – Frage 3: PHP-Version
    // ----------------------------------------------------------------
    let phpVersion = '';
    if (startseite.php && startseite.php.indexOf('PHP') !== -1) {
        phpVersion = startseite.php + ' (aus der Kopfzeile X-Powered-By)';
    }

    let bericht = null;
    try {
        bericht = await holeSeite('/wp-admin/site-health.php?tab=debug');
        // Nicht angemeldet? Dann leitet WordPress auf das Anmeldeformular um
        // und antwortet dabei mit HTTP 200 — der Fehlerzweig oben griffe
        // also NICHT, und die Auswertung liefe stumm ins Leere.
        if (bericht.querySelector('#loginform') || bericht.querySelector('body.login')) {
            bericht = null;
            hinweise.push('NICHT ALS ADMINISTRATOR ANGEMELDET: Der Systembericht war nicht ' +
                'lesbar, deshalb fehlen PHP-Version (Frage 3), WordPress-Version und der ' +
                'Zustand von WP_DEBUG_LOG. Bitte anmelden und das Skript erneut ausführen.');
        }
    } catch (fehler) {
        hinweise.push('Systembericht nicht erreichbar (' + fehler.message +
            ') — PHP-Version und WP_DEBUG_LOG konnten nur aus Kopfzeilen kommen.');
    }

    if (bericht) {
        const ausBericht = zeileAusBericht(bericht, ['PHP-Version', 'PHP version']);
        if (ausBericht) {
            phpVersion = ausBericht + (phpVersion ? ' (Kopfzeile: ' + startseite.php + ')' : ' (aus dem Systembericht)');
        }
        const debugLog = zeileAusBericht(bericht, ['WP_DEBUG_LOG']);
        if (debugLog) {
            ergebnis['WP_DEBUG_LOG'] = debugLog;
            // ACHTUNG: Das Muster muss den GANZEN Wert prüfen, nicht auf ein
            // Teilstück passen. Der erste Entwurf testete /true|wahr|aktiv/i
            // — und „De-aktiv-iert" enthält „aktiv". Auf der
            // Produktivinstallation warnte das Skript deshalb vor einem
            // eingeschalteten Protokoll, obwohl es aus war.
            const aus = /^\s*(false|falsch|deaktiviert|aus|nein|0|nicht gesetzt|undefined)\s*$/i.test(debugLog);
            const an = !aus && /^\s*(true|wahr|aktiviert|ein|an|ja|1)\s*$/i.test(debugLog);
            if (an) {
                hinweise.push('WP_DEBUG_LOG ist EINGESCHALTET. Auf der Produktivinstallation ' +
                    'sollte er aus bleiben: Jede Anfrage erzeugt rund 34 Boot-Protokollzeilen, ' +
                    'bei 25 Schülern im Zehn-Sekunden-Takt also etwa 7.600 Zeilen je Minute.');
            }
        }
    }
    ergebnis['Frage 3 · PHP-Version'] = phpVersion || 'nicht ermittelbar';

    // ----------------------------------------------------------------
    // Adresse des uploads-Ordners bestimmen
    // ----------------------------------------------------------------
    let uploadsBasis = herkunft + '/wp-content/uploads';
    let medienDatei = null;
    try {
        const medien = await fetch('/wp-json/wp/v2/media?per_page=1', { credentials: 'same-origin' });
        if (medien.ok) {
            const liste = await medien.json();
            if (Array.isArray(liste) && liste.length && liste[0].source_url) {
                medienDatei = liste[0].source_url;
                const schnitt = medienDatei.indexOf('/uploads/');
                if (schnitt !== -1) {
                    uploadsBasis = medienDatei.slice(0, schnitt + '/uploads'.length);
                }
            }
        }
    } catch (fehler) {
        hinweise.push('Medien-Endpunkt nicht lesbar (' + fehler.message +
            ') — uploads-Adresse geraten: ' + uploadsBasis);
    }
    ergebnis['uploads-Adresse'] = uploadsBasis;

    // ----------------------------------------------------------------
    // C – Frage 5: Hat PHP dort jemals geschrieben?
    // ----------------------------------------------------------------
    // Geprüft werden VERZEICHNISSE, nicht bestimmte Dateinamen — die
    // unterscheiden sich je nach Installation und Plugin-Stand (im Test hieß
    // die Datei `cbd-styles/blocks.css`, nicht wie geraten
    // `compiled-styles.css`). Entscheidend ist der Statuscode:
    //
    //   200 → Verzeichnis da, Auflistung an   ┐ beides beweist: PHP durfte
    //   403 → Verzeichnis da, Auflistung aus  ┘ dort ein Verzeichnis anlegen
    //   404 → Verzeichnis nicht vorhanden
    //
    // Das trägt auch auf einer Installation mit abgeschalteter
    // Verzeichnisauflistung, wo ein Dateiname nicht zu erraten wäre.
    const spuren = [
        'container-block-designer/',
        'cbd-styles/',
        'cbd-icons/',
        'cbd-temp-pdfs/',
    ];
    const gefunden = [];
    let entdeckteDatei = null;
    for (const spur of spuren) {
        const r = await sondiere(uploadsBasis + '/' + spur, true);
        if (!r.ok || (r.status !== 200 && r.status !== 403)) {
            continue;
        }
        gefunden.push(spur.replace(/\/$/, '') + ' (HTTP ' + r.status + ')');
        // Wo eine Auflistung da ist: die erste echte Datei daraus merken,
        // sie dient später als Messziel.
        if (r.status === 200 && !entdeckteDatei) {
            const treffer = Array.from(r.koerper.matchAll(/<a href="([^"]+)">/g))
                .map((m) => m[1])
                .find((u) => u.slice(-1) !== '/' && u.indexOf('?') === -1);
            if (treffer) {
                entdeckteDatei = treffer.indexOf('http') === 0 ? treffer : herkunft + treffer;
            }
        }
    }
    if (gefunden.length) {
        ergebnis['Frage 5 · uploads beschreibbar'] =
            'JA — das Plugin hat dort Verzeichnisse angelegt: ' + gefunden.join(', ');
    } else {
        ergebnis['Frage 5 · uploads beschreibbar'] =
            'nicht belegt — keines der vier Plugin-Verzeichnisse antwortete mit 200 oder 403';
        hinweise.push('Frage 5 blieb offen: Das Plugin hat (noch) kein nachweisbares ' +
            'Verzeichnis in uploads angelegt. Bitte den Handtest aus der Klickliste machen — ' +
            'ein kleines Bild in die Mediathek hochladen und wieder löschen.');
    }

    // ----------------------------------------------------------------
    // D – Frage 6: .json-Sonde mit Kontrollgruppe
    // ----------------------------------------------------------------
    const zufall = 'cbd-sonde-' + Math.random().toString(36).slice(2, 10);
    const sondeJson = await sondiere(uploadsBasis + '/' + zufall + '.json', false);
    const sondeTxt = await sondiere(uploadsBasis + '/' + zufall + '.txt', false);

    let urteilJson;
    if (!sondeJson.ok || !sondeTxt.ok) {
        urteilJson = 'nicht ermittelbar (Netzfehler)';
    } else if (sondeJson.status === 404 && sondeTxt.status === 404) {
        urteilJson = 'KEINE SPERRE erkennbar (.json 404 · .txt 404) — der Entwurf trägt';
    } else if (sondeJson.status === 403 && sondeTxt.status === 404) {
        urteilJson = 'ACHTUNG: .json gezielt gesperrt (.json 403 · .txt 404)';
        hinweise.push('Eine Regel sperrt offenbar gezielt .json-Dateien in uploads. ' +
            'Das wäre das Aus für den geplanten Entwurf — bitte VOR Phase 1 melden.');
    } else if (sondeJson.status === 403 && sondeTxt.status === 403) {
        urteilJson = 'ACHTUNG: der ganze Ordner ist gesperrt (.json 403 · .txt 403)';
        hinweise.push('Der uploads-Ordner liefert gar nichts aus (beide Sonden 403). ' +
            'Bitte VOR Phase 1 melden.');
    } else {
        urteilJson = 'unklar (.json ' + sondeJson.status + ' · .txt ' + sondeTxt.status + ')';
        hinweise.push('Die .json-Sonde ergab ein unerwartetes Muster. Bitte den Handtest ' +
            'aus Frage 6 der Klickliste machen.');
    }
    ergebnis['Frage 6 · .json-Sonde'] = urteilJson;

    // ----------------------------------------------------------------
    // E – Frage 7: Verzeichnisauflistung
    // ----------------------------------------------------------------
    const ordner = await sondiere(uploadsBasis + '/', true);
    if (!ordner.ok) {
        ergebnis['Frage 7 · Verzeichnisauflistung'] = 'nicht ermittelbar (Netzfehler)';
    } else if (ordner.status === 403) {
        ergebnis['Frage 7 · Verzeichnisauflistung'] = 'NEIN — HTTP 403, bereits gesperrt';
    } else if (ordner.status === 200 && /index of|<title>index/i.test(ordner.koerper)) {
        ergebnis['Frage 7 · Verzeichnisauflistung'] = 'JA — HTTP 200 mit Dateiliste';
        hinweise.push('uploads zeigt eine echte Verzeichnisauflistung. Die .htaccess mit ' +
            '„Options -Indexes" und die leere index.php aus AP-1.2 sind damit NÖTIG, ' +
            'nicht bloß vorsorglich — sonst stünden die Pulsdateien aller Klassen ' +
            'auflistbar im Netz.');
    } else {
        ergebnis['Frage 7 · Verzeichnisauflistung'] =
            'nein — HTTP ' + ordner.status + ', keine Dateiliste im Körper';
    }

    // ----------------------------------------------------------------
    // F – Zusatz: die tragende Annahme messen
    // ----------------------------------------------------------------
    // Gemessen wird der ABLEHNUNGSpfad der Route. Laut
    // docs/messung-klassenpuls.md, Abschnitt 4, kostet er genauso viel wie
    // der Erfolgspfad — die Kosten stecken im WordPress-Bootstrap, nicht in
    // der Sitzungsprüfung. Ohne gültige Klassensitzung ist das der einzige
    // ehrlich messbare Weg, und er ist ein fairer Stellvertreter.
    // Messziel muss eine ECHTE DATEI sein. Im ersten Entwurf stand hier ein
    // Verzeichnis — gemessen wurde dann das Erzeugen einer
    // Verzeichnisauflistung, und der Faktor fiel dadurch auf 16,8 statt der
    // lokal belegten 62,6. Reihenfolge: eine Mediendatei (gibt es fast
    // immer), sonst eine in Abschnitt C entdeckte Datei.
    const statischeDatei = medienDatei || entdeckteDatei;

    async function messe(adresse, modus, durchgaenge) {
        const zeiten = [];
        for (let i = 0; i < durchgaenge; i++) {
            const start = performance.now();
            try {
                await fetch(adresse, { credentials: 'same-origin', cache: modus });
            } catch (fehler) {
                return null;
            }
            zeiten.push(performance.now() - start);
        }
        return median(zeiten);
    }

    const zeitRoute = await messe(herkunft + '/?rest_route=/cbd/v1/klassenpuls', 'no-store', 10);
    ergebnis['Messung · REST-Route (Ablehnung)'] = zeitRoute
        ? zeitRoute.toFixed(1) + ' ms (Median aus 10)'
        : 'nicht messbar';

    if (statischeDatei) {
        const zeitStatisch = await messe(statischeDatei, 'no-cache', 10);
        ergebnis['Messung · statische Datei (revalidiert)'] = zeitStatisch
            ? zeitStatisch.toFixed(1) + ' ms (Median aus 10)'
            : 'nicht messbar';
        ergebnis['Messung · Messziel'] = statischeDatei.replace(herkunft, '');
        if (zeitRoute && zeitStatisch) {
            // Der Unterschied in Millisekunden ist die aussagekräftigere Zahl:
            // Beide Messungen enthalten dieselbe Netzlaufzeit und denselben
            // fetch()-Eigenaufwand, die sich hier wegkürzen. Übrig bleibt die
            // Server-Rechenzeit, die der statische Weg spart — und genau die
            // entscheidet über die Last, nicht das Verhältnis.
            ergebnis['Messung · gesparte Serverzeit'] =
                (zeitRoute - zeitStatisch).toFixed(1) + ' ms je Anfrage (der belastbare Wert)';
            ergebnis['Messung · Faktor'] =
                (zeitRoute / zeitStatisch).toFixed(1) + '-mal schneller (UNTERGRENZE, siehe unten)';
            hinweise.push('Die Zeile „gesparte Serverzeit" ist die belastbare Zahl dieser ' +
                'Messung, NICHT der Faktor. Beide Messungen tragen dieselbe Netzlaufzeit; ' +
                'bei einem entfernten Server macht die oft den größten Teil beider Werte aus ' +
                'und drückt das Verhältnis gegen 1, obwohl die eingesparte Rechenzeit ' +
                'unverändert anfällt. Der statische Weg belegt zusätzlich gar keinen ' +
                'PHP-Arbeitsprozess — das zählt für die Serverlast mehr als jede Zeitangabe.');
            hinweise.push('Der gemessene Faktor ist eine UNTERGRENZE, kein Vergleichswert zu ' +
                'einer Servermessung. `fetch()` im Browser kostet je Aufruf einige ' +
                'Millisekunden Eigenaufwand, und dieser feste Betrag drückt den Quotienten ' +
                'umso stärker, je schneller die schnelle Seite ist. Beleg aus dem Testlauf ' +
                'dieses Skripts: dieselbe Installation ergab über `curl` (serverseitig, ' +
                'Keep-Alive) 76,9 ms gegen 1,2 ms = Faktor 62,6, im Browser dagegen ' +
                '147,9 ms gegen 7,8 ms = Faktor 19,0. Für die Belastbarkeit zählt die ' +
                'Messung aus AP-3.3 auf der Testdomain, nicht diese hier — diese sagt nur, ' +
                'ob die Größenordnung überhaupt stimmt.');
        }
    } else {
        ergebnis['Messung · statische Datei (revalidiert)'] =
            'übersprungen (keine Plugin-Datei in uploads gefunden)';
    }

    // ----------------------------------------------------------------
    // G – Was das Skript NICHT kann
    // ----------------------------------------------------------------
    ergebnis['Frage 1 · Produkt/Tarif'] = 'VON HAND — nur im KAS sichtbar';
    ergebnis['Frage 2 · Cronjobs'] = 'VON HAND — nur im KAS sichtbar';
    ergebnis['Frage 4 · .user.ini wirksam'] = 'VON HAND — braucht Dateizugriff';

    // ----------------------------------------------------------------
    // Ausgabe
    // ----------------------------------------------------------------
    let block = '--- CDB Voraussetzungen „Schneller Klassenpuls" ---\n';
    block += 'Erhoben am: ' + new Date().toISOString().slice(0, 16).replace('T', ' ') + '\n\n';
    for (const [schluessel, wert] of Object.entries(ergebnis)) {
        block += schluessel.padEnd(38, ' ') + ': ' + wert + '\n';
    }
    if (hinweise.length) {
        block += '\nHinweise:\n';
        hinweise.forEach((h, i) => {
            block += '  ' + (i + 1) + '. ' + h + '\n';
        });
    }
    block += '\nOffen bleiben die Fragen 1, 2 und 4 der Klickliste.\n';
    block += '--- Ende ---';

    console.log('%c\n' + block + '\n', 'font-family:monospace;font-size:12px');

    // Zweiter Weg an den Text, falls Konsole und Zwischenablage klemmen:
    // `copy(cbdVoraussetzungenBlock)` in der Konsole eingeben.
    window.cbdVoraussetzungenBlock = block;

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
