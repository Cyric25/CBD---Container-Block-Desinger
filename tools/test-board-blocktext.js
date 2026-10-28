/*
 * Prüfungen "Blocktext auf die Tafel" (PLAN-Blocktext-auf-Tafel.md,
 * Phase 2) für die Prüfseite tools/test-board-zeiger.html.
 *
 * AP-2.1 - Lesen des linken Blockbereichs (_blocktextLesen/_blocktextAlsText):
 *   - je Testmaterial-Paar aus tools/fixtures/blocktext/ (echte Blöcke der
 *     Testinstallation) zeichengenauer Vergleich mit der Erwartung; jeder
 *     Platzhalter {{F:<LaTeX>}} wird durch CBDBoardBlocktext.latexZuText()
 *     ersetzt (Format: tools/fixtures/blocktext/README.md)
 *   - künstliche Fälle (a)-(f) aus dem Plan und einige Randfälle
 *
 * Die Fixture-Prüfungen holen die Dateien per fetch - die Prüfseite muss
 * über den Webserver laufen. Wird von tools/test-board-zeiger.html als
 * letzte Zusatzdatei vor test-board-zeiger.js geladen und hängt sich an die
 * Haken window.__zusatzTests und window.__zusatzTestsAsync an (vorhandene
 * zuerst, Vorbild test-board-textgroesse.js).
 */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var M = window.CBDBoardBlocktext;
    var CID = 'test-zeiger';
    var BS = String.fromCharCode(92); // Backslash, unabhängig vom Editor

    var vorherSync = window.__zusatzTests;
    var vorherAsync = window.__zusatzTestsAsync;

    // Paare <name>.html / <name>.erwartet.txt in tools/fixtures/blocktext/
    var FIXTURES = [
        'formeln-listen-glossar',
        'abgesetzte-formel-reaktionspfeil',
        'tabelle',
        'accordion-zugeklappt',
        'zusammenfassung-quiz',
        'ueberschriften-unicode',
        'verschachtelt-formel-nbsp',
        'bildueberlagerung'
    ];

    // ------------------------------------------------------------------
    // Hilfen
    // ------------------------------------------------------------------
    function speicherLeeren() {
        try {
            var weg = [];
            for (var i = 0; i < localStorage.length; i++) {
                var k = localStorage.key(i);
                if (k && k.indexOf('cbd-board') === 0) weg.push(k);
            }
            weg.forEach(function(k) { localStorage.removeItem(k); });
        } catch (e) {}
    }

    function oeffnenMit(html) {
        if (B.overlay) B.destroy();
        speicherLeeren();
        B.open(CID, html, '#ffffff', {});
    }

    function sichtbar(s) {
        return JSON.stringify(s);
    }

    // Zeichengenauer Vergleich; bei Abweichung die erste unterschiedliche
    // Stelle mit etwas Umgebung
    function vergleich(ist, soll) {
        if (ist === soll) return true;
        var i = 0;
        while (i < ist.length && i < soll.length && ist.charAt(i) === soll.charAt(i)) i++;
        var von = Math.max(0, i - 25);
        return {
            ok: false,
            detail: 'erste Abweichung bei Zeichen ' + i + ' (Länge ist ' + ist.length + ', soll ' + soll.length + '): ist ' +
                sichtbar(ist.slice(von, i + 25)) + ' / soll ' + sichtbar(soll.slice(von, i + 25))
        };
    }

    // Erwartung aufbereiten: CRLF -> LF (Repo mit core.autocrlf), jeden
    // Platzhalter {{F:x}} durch latexZuText(x) ersetzen, am Ende trimmen.
    // Ein Platzhalter endet am ersten "}}", an dem die geschweiften Klammern
    // des Formeltexts ausgeglichen sind ("\{"/"\}" zählen nicht) -
    // {{F:C_{40}}} endet erst nach der dritten Klammer.
    function erwartungAufloesen(roh) {
        var t = roh.replace(/\r\n/g, '\n');
        var aus = '';
        var i = 0;
        while (i < t.length) {
            var p = t.indexOf('{{F:', i);
            if (p < 0) {
                aus += t.slice(i);
                break;
            }
            aus += t.slice(i, p);
            var j = p + 4;
            var tiefe = 0;
            var ende = -1;
            while (j < t.length) {
                var ch = t.charAt(j);
                if (ch === BS) {
                    j += 2;
                    continue;
                }
                if (ch === '{') {
                    tiefe++;
                } else if (ch === '}') {
                    if (tiefe === 0 && t.charAt(j + 1) === '}') {
                        ende = j;
                        break;
                    }
                    tiefe--;
                }
                j++;
            }
            if (ende < 0) throw new Error('Platzhalter ohne Ende ab Zeichen ' + p);
            aus += M.latexZuText(t.slice(p + 4, ende));
            i = ende + 2;
        }
        return aus.replace(/^[ \n\t]+|[ \n\t]+$/g, '');
    }

    function holen(pfad) {
        return fetch(pfad, { cache: 'no-store' }).then(function(r) {
            if (!r.ok) throw new Error(pfad + ': HTTP ' + r.status);
            return r.text();
        });
    }

    function fixturePruefung(name) {
        return function() {
            var basis = 'fixtures/blocktext/' + name;
            return Promise.all([holen(basis + '.html'), holen(basis + '.erwartet.txt')]).then(function(teile) {
                oeffnenMit(teile[0]);
                return vergleich(B._blocktextAlsText(), erwartungAufloesen(teile[1]));
            });
        };
    }

    // Prüffunktion: Tafel mit html öffnen, gelesenen Text mit soll vergleichen
    function fall(html, soll) {
        return function() {
            oeffnenMit(html);
            return vergleich(B._blocktextAlsText(), soll);
        };
    }

    // ------------------------------------------------------------------
    // Synchrone Fälle
    // ------------------------------------------------------------------
    function erwartungsParser() {
        // Selbstprüfung der Hilfe oben: verschachtelte Klammern, Backslash
        var f = [];
        var a = erwartungAufloesen('x {{F:C_{40}}} y');
        if (a !== 'x ' + M.latexZuText('C_{40}') + ' y') f.push('C_{40}: ' + sichtbar(a));
        var b = erwartungAufloesen('{{F:' + BS + '{a' + BS + '}}}' + String.fromCharCode(13, 10));
        if (b !== M.latexZuText(BS + '{a' + BS + '}')) f.push('Klammer-Escape: ' + sichtbar(b));
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function ohneTafel() {
        if (B.overlay) B.destroy();
        return B._blocktextAlsText() === '' ? true : { ok: false, detail: 'ohne Tafel nicht leer' };
    }

    function arten() {
        oeffnenMit('<h3 class="cbd-block-title">T</h3><h4>U</h4><p>A</p><ul><li>L</li></ul>' +
            '<table><tr><td>Z</td></tr></table>' +
            '<p><span class="cbd-latex-formula cbd-latex-display" data-latex="x">x</span></p>');
        var l = B._blocktextLesen(B.overlay.querySelector('.cbd-board-content'));
        var ist = l.map(function(a) { return a.art + ':' + a.text; }).join(' / ');
        var soll = 'titel:T / ueberschrift:U / absatz:A / liste:• L / zeile:Z / formel:' + M.latexZuText('x');
        return vergleich(ist, soll);
    }

    function knopfleisteUnsichtbar() {
        // Leiste aus AP-2.3 (Klasse) und Aktionsleiste fallen weg, auch wenn
        // sie im Blockbereich stehen
        oeffnenMit('<div class="cbd-board-blocktext-leiste"><span>Text auf die Tafel</span></div><p>Inhalt</p>');
        return vergleich(B._blocktextAlsText(), 'Inhalt');
    }

    // ------------------------------------------------------------------
    // Einhängen (nach den vorhandenen Haken)
    // ------------------------------------------------------------------
    window.__zusatzTests = function(c) {
        if (typeof vorherSync === 'function') vorherSync(c);
        var p = c.pruefe;
        p('Blocktext: Platzhalter der Erwartung (Klammern, Escape, CRLF)', erwartungsParser);
        p('Blocktext: ohne offene Tafel leerer Text', ohneTafel);
        p('Blocktext (a): Verstecktes (display, hidden, aria-hidden, visibility) fällt weg',
            fall('<p>sichtbar</p><p style="display:none">weg</p><p hidden>weg</p><p aria-hidden="true">weg</p><p style="visibility:hidden">weg</p>',
                'sichtbar'));
        p('Blocktext (b): Formel aus data-latex, KaTeX-Innenleben nicht doppelt',
            fall('<p>Es gilt <span class="cbd-latex-formula cbd-latex-inline" data-latex="H_2O"><span class="katex"><span class="katex-mathml">H2O</span><span class="katex-html" aria-hidden="true">H2O</span></span></span>.</p>',
                'Es gilt H₂O.'));
        p('Blocktext (c): Aufzählung und nummerierte Liste mit start',
            fall('<ul><li>a</li><li>b</li></ul><ol start="3"><li>c</li></ol>', '• a\n\n• b\n\n3. c'));
        p('Blocktext (d): Tabelle - Zeile je Absatz, Zellen mit " | "',
            fall('<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>', 'A | B\n\n1 | 2'));
        p('Blocktext (e): Zeilenumbruch bleibt, Knopf und Bild fallen weg',
            fall('<p>x<br>y</p><button>Klick</button><img alt="Bild">', 'x\ny'));
        p('Blocktext (f): Aktionsleiste fällt weg',
            fall('<div class="cbd-action-buttons"><span>Kopieren</span></div><p>Text</p>', 'Text'));
        p('Blocktext: Knopf in Überschrift bleibt (Accordion-Zeilentitel), opacity:0 bleibt',
            fall('<h3><button type="button">Zeile 1</button></h3><p style="opacity:0">blendet ein</p>',
                'Zeile 1\n\nblendet ein'));
        p('Blocktext: .screen-reader-text fällt weg (Blocknummer)',
            fall('<div class="cbd-container-number"><span class="screen-reader-text" style="display:block">Block 3</span></div><p>Inhalt</p>',
                'Inhalt'));
        p('Blocktext: Listenpunkt mit Absatz darin behält sein Zeichen, verschachtelte Liste ohne Einrückung',
            fall('<ul><li><p>a</p></li><li>b<ul><li>c</li></ul></li></ul><p>danach</p>',
                '• a\n\n• b\n\n• c\n\ndanach'));
        p('Blocktext: Absätze in einer Tabellenzelle trennen die Zeile nicht',
            fall('<table><tr><td><p>x</p><p>y</p></td><td>z</td></tr></table>', 'x y | z'));
        p('Blocktext: Leerraum zusammengefasst, Inline-Elemente ohne Grenze, leere Absätze entfallen',
            fall('<p>  ein   <b>fettes</b>\n  Wort </p><p> </p><div><span>a</span><span>b</span></div>',
                'ein fettes Wort\n\nab'));
        p('Blocktext: Zeilenwechsel in PRE bleiben', fall('<pre>a\n  b</pre>', 'a\nb'));
        p('Blocktext: Arten titel/ueberschrift/absatz/liste/zeile/formel', arten);
        p('Blocktext: Knopfleiste der Funktion selbst wird nicht gelesen', knopfleisteUnsichtbar);
    };

    window.__zusatzTestsAsync = function(c) {
        var vorher = (typeof vorherAsync === 'function') ? vorherAsync(c) : Promise.resolve();
        return Promise.resolve(vorher).then(function() {
            var kette = Promise.resolve();
            FIXTURES.forEach(function(name) {
                kette = kette.then(function() {
                    return c.pruefeAsync('Blocktext: ' + name, fixturePruefung(name));
                });
            });
            return kette;
        });
    };
})();
