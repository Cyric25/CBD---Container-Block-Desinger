/**
 * Container Block Designer – Seitenimporter (Oberfläche)
 *
 * Gehört zur Adminseite „Seitenmanager → Seiten importieren"
 * (admin/page-import.php, Steuerung in includes/class-cbd-page-importer.php).
 *
 * Ablauf in drei Schritten:
 *   1. Dateien wählen oder ablegen → im Browser lesen → je Datei über den
 *      bestehenden Endpunkt `cbd_parse_import_file` parsen → Dubletten prüfen
 *   2. Die H2-Gruppen ALLER Dateien zu einer Liste zusammenführen und in
 *      EINEM Dialog zuweisen
 *   3. Je ausgewählter Datei ein Aufruf an `cbd_import_pages`
 *
 * Bewusst schlichtes JavaScript ohne Build-Schritt und ohne React: Die Seite
 * ist keine Editor-Oberfläche. Debug-Ausgaben laufen über window.cbdDebug
 * (Projektkonvention); console.error bleibt immer aktiv.
 *
 * SICHERHEIT: Alles, was aus Dateiinhalten oder Serverantworten stammt, wird
 * ausschließlich über textContent gesetzt – niemals über innerHTML. Ein
 * Markdown-Titel wie „# <img src=x onerror=…>" darf im Backend nichts tun.
 *
 * @package ContainerBlockDesigner
 */

(function () {
    'use strict';

    if (typeof window.cbdPageImport === 'undefined') {
        return;
    }

    var KONF = window.cbdPageImport;

    /** Sonderwert „ohne Container einfügen" – wie NO_CONTAINER im Editor-Importer. */
    var OHNE_CONTAINER = '__none__';

    /** Höchstens so viele Dateien gleichzeitig parsen, damit der Server Luft behält. */
    var PARALLEL = 4;

    /** Feste Kürzel für die bekannten Kompetenzstufen. */
    var BADGES = { k1: 'K1', k2: 'K2', k3: 'K3', sources: '📚', other: '?' };

    // ---------------------------------------------------------------
    // Zustand
    // ---------------------------------------------------------------

    var dateien = [];        // { dateiname, titel, rohtext, sections, groups, stats, dublette, ausgewaehlt, fehler }
    var stile = [];          // [{ value, label }]
    var hatStile = true;
    var gruppen = [];        // zusammengeführt über alle Dateien
    var zuweisungen = {};    // gruppenschlüssel => Slug oder OHNE_CONTAINER
    var accordionAuswahl = {}; // gruppenschlüssel => bool
    var laeuft = false;

    // ---------------------------------------------------------------
    // Kleine Helfer
    // ---------------------------------------------------------------

    function $(id) { return document.getElementById(id); }

    function debug() {
        if (window.cbdDebug) {
            console.log.apply(console, arguments);
        }
    }

    /**
     * Element bauen. `text` wird IMMER als Text gesetzt, nie als HTML.
     */
    function el(tag, klasse, text) {
        var n = document.createElement(tag);
        if (klasse) { n.className = klasse; }
        if (text !== undefined && text !== null) { n.textContent = String(text); }
        return n;
    }

    function leeren(knoten) {
        while (knoten.firstChild) { knoten.removeChild(knoten.firstChild); }
    }

    function post(daten) {
        var fd = new FormData();
        Object.keys(daten).forEach(function (k) {
            if (Array.isArray(daten[k])) {
                daten[k].forEach(function (v) { fd.append(k + '[]', v); });
            } else {
                fd.append(k, daten[k]);
            }
        });
        return fetch(KONF.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    /** Kürzel für eine Gruppe. */
    function badge(gruppe) {
        if (BADGES[gruppe.key]) { return BADGES[gruppe.key]; }
        var nur = (gruppe.label || '?').replace(/[^\wÀ-ÿ]/g, '');
        return (nur.substring(0, 2) || '?').toUpperCase();
    }

    /** Titel aus der ersten „# "-Zeile; sonst Dateiname ohne Endung. */
    function titelAusMarkdown(text, dateiname) {
        var treffer = text.match(/^#[ \t]+(.+?)[ \t]*$/m);
        var titel = treffer ? treffer[1].trim() : '';
        if (titel === '') {
            titel = dateiname.replace(/\.[^.]+$/, '').trim();
        }
        return titel !== '' ? titel : 'Unbenannt';
    }

    // ---------------------------------------------------------------
    // Elternseite: gestaffelte, kaskadierende Auswahl
    //
    // Ersetzt das frühere einzelne wp_dropdown_pages()-Feld. Das Feld
    // #cbd-import-parent bleibt als verstecktes <input type="hidden">
    // bestehen (siehe admin/page-import.php) und trägt weiterhin den
    // finalen Wert – importStarten() liest es unverändert wie vorher.
    // Die sichtbaren <select class="cbd-pi-kaskade-ebene">-Elemente sind
    // reine UI und halten dieses Feld synchron.
    //
    // KEIN wp.element/React (Projektkonvention dieser Datei, siehe
    // Datei-Kopf) – nur wp.apiFetch (kein UI-Framework, nur ein
    // Fetch-Wrapper mit automatischer REST-Wurzel/Nonce-Konfiguration,
    // sobald das Skript-Handle "wp-api-fetch" als Abhängigkeit eingebunden
    // ist, siehe includes/class-cbd-page-importer.php).
    // ---------------------------------------------------------------

    /** {knoten, kinder, wurzeln} aus GET cbd/v1/seitenbaum?entwuerfe=1 – null bis geladen/bei Fehlschlag. */
    var seitenbaum = null;

    function kaskadeLaden() {
        var ziel = $('cbd-pi-kaskade');
        if (!ziel) { return Promise.resolve(); }

        if (!window.wp || 'function' !== typeof window.wp.apiFetch) {
            kaskadeFehler(ziel, 'Der Auswahlbaustein für die Seitenhierarchie ist nicht verfügbar. Die Elternseite bleibt auf oberster Ebene.');
            return Promise.resolve();
        }

        return window.wp.apiFetch({ path: '/cbd/v1/seitenbaum?entwuerfe=1' }).then(function (baum) {
            seitenbaum = baum;
            kaskadeZeichnen(ziel);
        }).catch(function (fehler) {
            console.error('[CBD Seitenimport] Seitenbaum konnte nicht geladen werden', fehler);
            kaskadeFehler(ziel, 'Seitenbaum konnte nicht geladen werden — Elternseite bleibt auf oberster Ebene.');
        });
    }

    /** Ladeprobleme dürfen den Import nie blockieren – Elternseite bleibt „0" (oberste Ebene). */
    function kaskadeFehler(ziel, text) {
        leeren(ziel);
        ziel.appendChild(el('p', 'cbd-pi-kaskade-status cbd-pi-kaskade-status--fehler', text));
    }

    /** Baut die erste Kaskaden-Ebene (Wurzeln) auf. */
    function kaskadeZeichnen(ziel) {
        leeren(ziel);
        ziel.appendChild(kaskadeEbeneBauen(0, seitenbaum.wurzeln || []));
    }

    /**
     * Baut ein <select class="cbd-pi-kaskade-ebene"> für eine Ebene.
     *
     * @param {number} elternId ID der übergeordneten Seite (0 = oberste Ebene). Wird als Wert der ersten Option verwendet, damit diese Ebene selbst als Elternseite gültig bleibt, wenn keine tiefere Unterseite gewählt wird.
     * @param {Array<number>} kinderIds IDs der in dieser Ebene wählbaren Seiten (aus seitenbaum.wurzeln bzw. seitenbaum.kinder[x]).
     * @returns {HTMLSelectElement}
     */
    function kaskadeEbeneBauen(elternId, kinderIds) {
        var auswahl = document.createElement('select');
        auswahl.className = 'cbd-pi-kaskade-ebene';

        var leereOption = document.createElement('option');
        leereOption.value = String(elternId);
        // .text setzt den sichtbaren Options-Text ohne HTML-Parsing – wie
        // textContent an anderer Stelle dieser Datei, kein innerHTML.
        leereOption.text = (elternId === 0) ? '— oberste Ebene —' : '— diese Seite als Elternseite —';
        auswahl.appendChild(leereOption);

        (kinderIds || []).forEach(function (id) {
            var knoten = seitenbaum.knoten[id];
            if (!knoten) { return; }
            var option = document.createElement('option');
            option.value = String(id);
            option.text = knoten.titel;
            auswahl.appendChild(option);
        });

        auswahl.addEventListener('change', function () {
            kaskadeAuswahlGeaendert(auswahl);
        });

        return auswahl;
    }

    /** Bei Auswahl: nachfolgende Ebenen entfernen, verstecktes Feld aktualisieren, ggf. neue Ebene anhängen. */
    function kaskadeAuswahlGeaendert(ausgewaehltesFeld) {
        var naechstes = ausgewaehltesFeld.nextSibling;
        while (naechstes) {
            var zuLoeschen = naechstes;
            naechstes = naechstes.nextSibling;
            zuLoeschen.parentNode.removeChild(zuLoeschen);
        }

        var gewaehlteId = parseInt(ausgewaehltesFeld.value, 10) || 0;

        var elternfeld = $('cbd-import-parent');
        if (elternfeld) { elternfeld.value = String(gewaehlteId); }

        if (gewaehlteId === 0 || !seitenbaum) { return; }

        var kinder = (seitenbaum.kinder && seitenbaum.kinder[gewaehlteId]) || [];
        if (kinder.length === 0) { return; }

        ausgewaehltesFeld.parentNode.appendChild(kaskadeEbeneBauen(gewaehlteId, kinder));
    }

    /** Während eines Laufs alle sichtbaren Kaskaden-Ebenen sperren/entsperren – analog zum bisherigen elternfeld.disabled. */
    function kaskadeSperren(gesperrt) {
        var ziel = $('cbd-pi-kaskade');
        if (!ziel) { return; }
        var felder = ziel.querySelectorAll('.cbd-pi-kaskade-ebene');
        for (var i = 0; i < felder.length; i++) {
            felder[i].disabled = gesperrt;
        }
    }

    // ---------------------------------------------------------------
    // Schritt 1: Dateien aufnehmen
    // ---------------------------------------------------------------

    function dateiLesen(file) {
        return new Promise(function (fertig) {
            var leser = new FileReader();
            leser.onload = function (e) { fertig({ name: file.name, text: String(e.target.result) }); };
            leser.onerror = function () { fertig({ name: file.name, text: null }); };
            leser.readAsText(file, 'UTF-8');
        });
    }

    function dateienAufnehmen(fileList) {
        var liste = Array.prototype.slice.call(fileList || []);
        if (liste.length === 0) { return; }

        var erlaubt = [];
        liste.forEach(function (f) {
            if (/\.(md|txt)$/i.test(f.name)) {
                erlaubt.push(f);
            } else {
                dateien.push({
                    dateiname: f.name, titel: '', rohtext: '', sections: [], groups: [],
                    stats: null, dublette: null, ausgewaehlt: false,
                    fehler: 'Nur .md und .txt werden gelesen.'
                });
            }
        });

        fortschrittZeigen('Dateien werden gelesen …', 0);

        var fertig = 0;
        var gesamt = erlaubt.length;

        // Sequenziell in kleinen Bündeln – 40 Dateien auf einmal würden den
        // Server unnötig belasten.
        function naechstesBuendel(index) {
            if (index >= gesamt) {
                return titelPruefen().then(function () {
                    fortschrittVerbergen();
                    gruppenZusammenfuehren();
                    zeichneAlles();
                });
            }
            var buendel = erlaubt.slice(index, index + PARALLEL);
            return Promise.all(buendel.map(function (f) {
                return dateiLesen(f).then(function (gelesen) {
                    fertig++;
                    fortschrittZeigen('Datei ' + fertig + ' von ' + gesamt + ' gelesen', fertig / gesamt * 100);
                    if (gelesen.text === null) {
                        dateien.push({
                            dateiname: gelesen.name, titel: '', rohtext: '', sections: [], groups: [],
                            stats: null, dublette: null, ausgewaehlt: false,
                            fehler: 'Datei konnte nicht gelesen werden.'
                        });
                        return;
                    }
                    return dateiParsen(gelesen.name, gelesen.text);
                });
            })).then(function () { return naechstesBuendel(index + PARALLEL); });
        }

        naechstesBuendel(0);
    }

    function dateiParsen(name, text) {
        var eintrag = {
            dateiname: name,
            titel: titelAusMarkdown(text, name),
            rohtext: text,
            sections: [], groups: [], stats: null,
            dublette: null, ausgewaehlt: true, fehler: null
        };
        dateien.push(eintrag);

        return post({
            action: 'cbd_parse_import_file',
            nonce: KONF.nonceParse,
            content: text
        }).then(function (antwort) {
            if (antwort && antwort.success) {
                eintrag.sections = antwort.data.sections || [];
                eintrag.groups = antwort.data.groups || [];
                eintrag.stats = antwort.data.stats || null;
            } else {
                eintrag.ausgewaehlt = false;
                eintrag.fehler = (antwort && antwort.data && antwort.data.message)
                    ? antwort.data.message : 'Die Datei konnte nicht gelesen werden.';
            }
        }).catch(function (f) {
            eintrag.ausgewaehlt = false;
            eintrag.fehler = 'Netzwerkfehler beim Parsen.';
            console.error('[CBD Seitenimport]', f);
        });
    }

    /** Alle Titel in EINEM Aufruf gegen vorhandene Seiten prüfen. */
    function titelPruefen() {
        var titel = dateien.filter(function (d) { return !d.fehler; })
            .map(function (d) { return d.titel; });
        if (titel.length === 0) { return Promise.resolve(); }

        return post({
            action: 'cbd_check_page_titles',
            nonce: KONF.nonceImport,
            titles: titel
        }).then(function (antwort) {
            if (!antwort || !antwort.success) { return; }
            var dub = antwort.data.dubletten || {};
            dateien.forEach(function (d) {
                if (dub[d.titel]) { d.dublette = dub[d.titel]; }
            });
        }).catch(function (f) {
            console.error('[CBD Seitenimport] Dublettenprüfung fehlgeschlagen', f);
        });
    }

    // ---------------------------------------------------------------
    // Schritt 2: Gruppen zusammenführen
    // ---------------------------------------------------------------

    function gruppenZusammenfuehren() {
        var nachKey = {};
        var reihenfolge = [];

        dateien.forEach(function (d) {
            (d.groups || []).forEach(function (g) {
                if (!nachKey[g.key]) {
                    nachKey[g.key] = {
                        key: g.key,
                        label: g.label,
                        count: 0,
                        dateien: 0,
                        suggestedStyle: null,
                        similarStyle: null,
                        hasSubheadings: false,
                        accordion: null
                    };
                    reihenfolge.push(g.key);
                }
                var z = nachKey[g.key];
                z.count += (g.count || 0);
                z.dateien += 1;
                // Ein exakter Treffer in EINER Datei genügt.
                if (g.suggestedStyle && !z.suggestedStyle) { z.suggestedStyle = g.suggestedStyle; }
                if (g.similarStyle && !z.similarStyle) { z.similarStyle = g.similarStyle; }
                if (g.hasSubheadings) { z.hasSubheadings = true; }
                if (g.accordion && !z.accordion) { z.accordion = g.accordion; }
            });
        });

        // „other" ans Ende
        gruppen = reihenfolge.map(function (k) { return nachKey[k]; });
        gruppen = gruppen.filter(function (g) { return g.key !== 'other'; })
            .concat(gruppen.filter(function (g) { return g.key === 'other'; }));

        gruppen.forEach(function (g) {
            if (typeof zuweisungen[g.key] === 'undefined') {
                // Vorbelegt wird NUR bei exakter Namensgleichheit. Unscharfe
                // Treffer erscheinen als Hinweis – zuweisen soll der Nutzer
                // bewusst selbst (Verhalten des Editor-Importers).
                zuweisungen[g.key] = g.suggestedStyle || OHNE_CONTAINER;
            }
            if (g.accordion && g.accordion.enabled && typeof accordionAuswahl[g.key] === 'undefined') {
                accordionAuswahl[g.key] = true;
            }
        });
    }

    // ---------------------------------------------------------------
    // Zeichnen
    // ---------------------------------------------------------------

    function zeichneAlles() {
        zeichneDateiliste();
        zeichneGruppen();
        zeichneAktionen();
    }

    function zeichneDateiliste() {
        var ziel = $('cbd-pi-dateiliste');
        leeren(ziel);
        if (dateien.length === 0) { return; }

        var mitDublette = dateien.filter(function (d) { return d.dublette; }).length;
        var kopf = el('p', 'cbd-pi-zusammenfassung',
            dateien.length + ' Datei(en) geladen'
            + (mitDublette > 0 ? ', davon ' + mitDublette + ' mit bereits vorhandenem Titel' : ''));
        ziel.appendChild(kopf);

        if (mitDublette > 0) {
            ziel.appendChild(el('p', 'cbd-pi-warnung',
                'Dubletten werden trotzdem als neuer Entwurf angelegt und überschreiben nichts. '
                + 'Zum Überspringen die Datei abwählen.'));
        }

        dateien.forEach(function (d, i) {
            var zeile = el('div', 'cbd-pi-datei'
                + (d.dublette ? ' cbd-pi-datei--dublette' : '')
                + (!d.ausgewaehlt ? ' cbd-pi-datei--abgewaehlt' : ''));

            var box = document.createElement('input');
            box.type = 'checkbox';
            box.checked = !!d.ausgewaehlt;
            box.disabled = !!d.fehler;
            box.setAttribute('aria-label', 'Datei ' + d.dateiname + ' einbeziehen');
            box.addEventListener('change', function () {
                dateien[i].ausgewaehlt = box.checked;
                zeichneDateiliste();
                zeichneAktionen();
            });
            zeile.appendChild(box);

            var mitte = el('div', 'cbd-pi-datei-text');
            mitte.appendChild(el('span', 'cbd-pi-datei-titel', d.titel || d.dateiname));
            var meta = d.dateiname
                + (d.stats ? ' · ' + d.stats.total + ' Abschnitt(e)' : '');
            mitte.appendChild(el('span', 'cbd-pi-datei-meta', meta));

            if (d.fehler) {
                mitte.appendChild(el('span', 'cbd-pi-warnung', d.fehler));
            } else if (d.dublette) {
                var w = el('span', 'cbd-pi-warnung', 'Eine Seite mit diesem Titel existiert bereits. ');
                if (d.dublette.editLink) {
                    var a = el('a', null, 'Vorhandene Seite ansehen');
                    a.href = d.dublette.editLink;
                    a.target = '_blank';
                    a.rel = 'noopener';
                    w.appendChild(a);
                }
                mitte.appendChild(w);
            }

            zeile.appendChild(mitte);
            ziel.appendChild(zeile);
        });
    }

    function zeichneGruppen() {
        var ziel = $('cbd-pi-gruppen');
        leeren(ziel);
        if (gruppen.length === 0) { return; }

        ziel.appendChild(el('h2', null, 'Container-Designs zuweisen'));
        ziel.appendChild(el('p', 'description',
            'Die Zuweisung gilt für alle geladenen Dateien gemeinsam.'));

        if (!hatStile) {
            ziel.appendChild(el('div', 'notice notice-warning cbd-pi-notice',
                'Es sind noch keine Block-Designs angelegt. Der Inhalt wird ohne Container '
                + 'eingefügt und kann später Containern zugewiesen werden.'));
        }

        var accGruppen = gruppen.filter(function (g) { return g.accordion && g.accordion.enabled; });
        if (accGruppen.length > 0 && !KONF.accordionVerfuegbar) {
            ziel.appendChild(el('div', 'notice notice-warning cbd-pi-notice',
                'Der Block „Accordion – Aufklappbare Zeilen" ist nicht verfügbar. Folgende '
                + 'Gruppen enthalten eine Accordion-Direktive und werden stattdessen wie '
                + 'gewohnt importiert: '
                + accGruppen.map(function (g) { return '„' + g.label + '“'; }).join(', ') + '.'));
        }

        // Sammelzuweisung für alle noch offenen Gruppen
        var offen = gruppen.filter(function (g) {
            var v = zuweisungen[g.key];
            return !v || v === OHNE_CONTAINER;
        });
        var echte = stile.filter(function (s) { return s.value !== OHNE_CONTAINER; });

        if (offen.length > 0 && echte.length > 0) {
            var kasten = el('div', 'cbd-pi-sammelzuweisung');
            kasten.appendChild(el('p', 'cbd-pi-sammel-label',
                offen.length + ' Gruppe(n) noch ohne Design: '
                + offen.map(function (g) { return '„' + g.label + '“'; }).join(', ')));

            var auswahl = document.createElement('select');
            auswahl.appendChild(new Option('— Design wählen —', ''));
            echte.forEach(function (s) { auswahl.appendChild(new Option(s.label, s.value)); });
            kasten.appendChild(auswahl);

            var knopf = el('button', 'button', 'Allen offenen Gruppen zuweisen');
            knopf.type = 'button';
            knopf.addEventListener('click', function () {
                if (!auswahl.value) { return; }
                offen.forEach(function (g) { zuweisungen[g.key] = auswahl.value; });
                zeichneGruppen();
                zeichneAktionen();
            });
            kasten.appendChild(knopf);
            ziel.appendChild(kasten);
        }

        gruppen.forEach(function (g) {
            var aktuell = zuweisungen[g.key] || OHNE_CONTAINER;
            var istOffen = (aktuell === OHNE_CONTAINER);

            var zeile = el('div', 'cbd-pi-gruppe' + (istOffen ? ' cbd-pi-gruppe--offen' : ''));
            zeile.appendChild(el('span', 'cbd-pi-badge', badge(g)));

            var rechts = el('div', 'cbd-pi-gruppe-inhalt');
            rechts.appendChild(el('label', 'cbd-pi-gruppe-label',
                g.label + ' (' + g.count + ' Blöcke in ' + g.dateien + ' Datei(en))'));

            var sel = document.createElement('select');
            stile.forEach(function (s) {
                var o = new Option(s.label, s.value);
                if (s.value === aktuell) { o.selected = true; }
                sel.appendChild(o);
            });
            sel.addEventListener('change', function () {
                zuweisungen[g.key] = sel.value;
                zeichneGruppen();
                zeichneAktionen();
            });
            rechts.appendChild(sel);

            var hinweis;
            if (g.suggestedStyle) {
                hinweis = 'automatisch zugeordnet (Name stimmt überein)';
            } else if (g.similarStyle) {
                var aehnlich = stile.filter(function (s) { return s.value === g.similarStyle; })[0];
                hinweis = 'kein exakt gleichnamiges Design — ähnlich: „'
                    + (aehnlich ? aehnlich.label : g.similarStyle) + '" (bitte selbst zuweisen)';
            } else {
                hinweis = 'kein gleichnamiges Design gefunden — bitte selbst zuweisen';
            }
            if (g.hasSubheadings) { hinweis += ' · ###-Unterabschnitte erkannt'; }
            rechts.appendChild(el('p', 'cbd-pi-hinweis', hinweis));

            if (g.accordion && g.accordion.enabled) {
                var accZeile = el('div', 'cbd-pi-accordion');
                var accBox = document.createElement('input');
                accBox.type = 'checkbox';
                accBox.id = 'cbd-pi-acc-' + g.key;
                accBox.checked = accordionAuswahl[g.key] !== false;
                accBox.disabled = !KONF.accordionVerfuegbar;
                accBox.addEventListener('change', function () {
                    accordionAuswahl[g.key] = accBox.checked;
                });
                var accLabel = el('label', null, 'Als Accordion importieren – '
                    + g.count + ' Klappzeilen');
                accLabel.htmlFor = accBox.id;
                accZeile.appendChild(accBox);
                accZeile.appendChild(accLabel);
                rechts.appendChild(accZeile);
            }

            zeile.appendChild(rechts);
            ziel.appendChild(zeile);
        });
    }

    function zeichneAktionen() {
        var ziel = $('cbd-pi-aktionen');
        leeren(ziel);

        var auswahl = dateien.filter(function (d) { return d.ausgewaehlt && !d.fehler; });
        if (dateien.length === 0) { return; }

        var zugewiesen = gruppen.filter(function (g) {
            var v = zuweisungen[g.key];
            return v && v !== OHNE_CONTAINER;
        }).length;

        var text = gruppen.length > 0
            ? zugewiesen + '/' + gruppen.length + ' Gruppen zugewiesen'
                + (zugewiesen < gruppen.length ? ' — Rest wird ohne Container eingefügt' : '')
            : '';
        var status = el('span', 'cbd-pi-status' + (zugewiesen < gruppen.length ? ' is-incomplete' : ''), text);
        ziel.appendChild(status);

        var knopf = el('button', 'button button-primary', laeuft
            ? 'Import läuft …'
            : auswahl.length + ' Seite(n) anlegen');
        knopf.type = 'button';
        knopf.id = 'cbd-pi-import-start';
        knopf.disabled = laeuft || auswahl.length === 0;
        knopf.addEventListener('click', importStarten);
        ziel.appendChild(knopf);
    }

    function fortschrittZeigen(text, prozent) {
        var f = $('cbd-pi-fortschritt');
        f.hidden = false;
        leeren(f);
        f.appendChild(el('p', null, text));
        var leiste = el('div', 'cbd-pi-fortschritt-leiste');
        var balken = el('div', 'cbd-pi-fortschritt-balken');
        balken.style.width = Math.max(0, Math.min(100, prozent)) + '%';
        leiste.appendChild(balken);
        f.appendChild(leiste);
    }

    function fortschrittVerbergen() {
        var f = $('cbd-pi-fortschritt');
        f.hidden = true;
        leeren(f);
    }

    // ---------------------------------------------------------------
    // Schritt 3: Import ausführen
    // ---------------------------------------------------------------

    function importStarten() {
        var auswahl = dateien.filter(function (d) { return d.ausgewaehlt && !d.fehler; });
        if (auswahl.length === 0 || laeuft) { return; }

        if (!window.confirm(auswahl.length + ' Seite(n) werden als Entwurf angelegt. Fortfahren?')) {
            return;
        }

        // Einmal vor Beginn lesen und für den GANZEN Lauf festhalten – nicht
        // je Datei neu, sonst könnte eine Bedienung mitten im Lauf die
        // Elternseite für die zweite Hälfte der Dateien ändern.
        var elternfeld = $('cbd-import-parent');
        var elternId = elternfeld ? elternfeld.value : '0';
        if (elternfeld) { elternfeld.disabled = true; }
        kaskadeSperren(true);

        laeuft = true;
        zeichneAktionen();

        var ergebnisZiel = $('cbd-pi-ergebnis');
        ergebnisZiel.hidden = false;
        leeren(ergebnisZiel);
        ergebnisZiel.appendChild(el('h2', null, 'Ergebnis'));

        var angelegt = 0;
        var fehlgeschlagen = 0;

        // Nacheinander – das hält die Reihenfolge stabil und den Server ruhig.
        function naechste(i) {
            if (i >= auswahl.length) {
                fortschrittVerbergen();
                laeuft = false;
                if (elternfeld) { elternfeld.disabled = false; }
                kaskadeSperren(false);
                zeichneAktionen();

                var summe = el('p', 'cbd-pi-summe',
                    angelegt + ' Seite(n) angelegt' + (fehlgeschlagen > 0 ? ', ' + fehlgeschlagen + ' Fehler.' : '.'));
                ergebnisZiel.appendChild(summe);

                var zurueck = el('a', 'button', 'Zum Seitenmanager');
                zurueck.href = KONF.seitenmanagerUrl;
                ergebnisZiel.appendChild(zurueck);
                return;
            }

            var d = auswahl[i];
            fortschrittZeigen('Seite ' + (i + 1) + ' von ' + auswahl.length + ': ' + d.titel,
                i / auswahl.length * 100);

            post({
                action: 'cbd_import_pages',
                nonce: KONF.nonceImport,
                title: d.titel,
                content: d.rohtext,
                mappings: JSON.stringify(zuweisungen),
                accordion_opt_out: JSON.stringify(accordionOptOut()),
                parent_id: elternId
            }).then(function (antwort) {
                if (antwort && antwort.success) {
                    angelegt++;
                    var zeile = el('div', 'cbd-pi-ergebnis-zeile');
                    zeile.appendChild(el('span', null,
                        antwort.data.title + ' — ' + antwort.data.blocks + ' Blöcke'));
                    if (antwort.data.editLink) {
                        var a = el('a', null, 'Bearbeiten');
                        a.href = antwort.data.editLink;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        zeile.appendChild(a);
                    }
                    ergebnisZiel.appendChild(zeile);
                } else {
                    fehlgeschlagen++;
                    var f = el('div', 'cbd-pi-ergebnis-zeile cbd-pi-ergebnis-zeile--fehler');
                    f.appendChild(el('span', null, d.titel + ' — '
                        + ((antwort && antwort.data && antwort.data.message) || 'unbekannter Fehler')));
                    ergebnisZiel.appendChild(f);
                }
            }).catch(function (fehler) {
                fehlgeschlagen++;
                console.error('[CBD Seitenimport]', fehler);
                var f = el('div', 'cbd-pi-ergebnis-zeile cbd-pi-ergebnis-zeile--fehler');
                f.appendChild(el('span', null, d.titel + ' — Netzwerkfehler'));
                ergebnisZiel.appendChild(f);
            }).then(function () {
                // Ein Fehler bricht den Durchlauf NICHT ab.
                naechste(i + 1);
            });
        }

        naechste(0);
    }

    /** Nur die tatsächlich abgewählten Gruppen übergeben. */
    function accordionOptOut() {
        var raus = {};
        Object.keys(accordionAuswahl).forEach(function (k) {
            if (accordionAuswahl[k] === false) { raus[k] = true; }
        });
        return raus;
    }

    // ---------------------------------------------------------------
    // Start
    // ---------------------------------------------------------------

    function stileLaden() {
        return post({
            action: 'cbd_get_style_mappings',
            nonce: KONF.nonceParse
        }).then(function (antwort) {
            if (antwort && antwort.success) {
                stile = (antwort.data.styles || []).slice();
                hatStile = antwort.data.hasStyles !== false;
            } else {
                stile = [];
                hatStile = false;
            }
            // „ohne Container" steht immer zur Verfügung – damit lässt sich
            // auch ohne passendes Design importieren.
            stile.push({ value: OHNE_CONTAINER, label: '— ohne Container (nur Inhalt) —' });
            debug('[CBD Seitenimport] Stile geladen:', stile.length);
        }).catch(function (f) {
            console.error('[CBD Seitenimport] Stile konnten nicht geladen werden', f);
            stile = [{ value: OHNE_CONTAINER, label: '— ohne Container (nur Inhalt) —' }];
            hatStile = false;
        });
    }

    function ereignisseBinden() {
        var auswahlfeld = $('cbd-pi-dateiauswahl');
        if (auswahlfeld) {
            auswahlfeld.addEventListener('change', function () {
                dateienAufnehmen(auswahlfeld.files);
                auswahlfeld.value = '';
            });
        }

        var zone = $('cbd-pi-dropzone');
        if (zone) {
            ['dragenter', 'dragover'].forEach(function (typ) {
                zone.addEventListener(typ, function (e) {
                    // Ohne preventDefault öffnet der Browser die Datei.
                    e.preventDefault();
                    e.stopPropagation();
                    zone.classList.add('cbd-pi-dropzone--aktiv');
                });
            });
            ['dragleave', 'drop'].forEach(function (typ) {
                zone.addEventListener(typ, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    zone.classList.remove('cbd-pi-dropzone--aktiv');
                });
            });
            zone.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files) {
                    dateienAufnehmen(e.dataTransfer.files);
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        ereignisseBinden();
        stileLaden();
        kaskadeLaden();
    });
})();
