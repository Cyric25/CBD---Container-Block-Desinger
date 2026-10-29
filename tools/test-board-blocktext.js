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

    // ------------------------------------------------------------------
    // AP-2.2: Einfügen (blocktextEinfuegen, blocktextAufNeueSeite)
    // ------------------------------------------------------------------
    var ctx;

    function el() {
        return (B.modell && B.modell.elemente) ? B.modell.elemente : [];
    }

    function texte() {
        return el().filter(function(e) { return e.typ === 'text'; });
    }

    function ergebnis(f) {
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function strich(x1, y1, x2, y2) {
        B.setTool('pen');
        B.setColor('#000000');
        var n = Math.max(1, Math.round(Math.max(Math.abs(x2 - x1), Math.abs(y2 - y1)) / 10));
        ctx.zeiger('pointerdown', 3, 'mouse', x1, y1);
        for (var i = 1; i <= n; i++) {
            ctx.zeiger('pointermove', 3, 'mouse', x1 + (x2 - x1) * i / n, y1 + (y2 - y1) * i / n);
        }
        ctx.zeiger('pointerup', 3, 'mouse', x2, y2);
        return el()[el().length - 1];
    }

    function absaetze(n) {
        var l = [];
        for (var i = 1; i <= n; i++) l.push('Absatz ' + i + ' mit etwas Text, damit er eine Zeile füllt.');
        return l.join('\n\n');
    }

    // Rahmen r liegt (mit 0,5 px Spiel) in Bereich s
    function innerhalb(r, s) {
        return r.x >= s.x - 0.5 && r.y >= s.y - 0.5 && r.x + r.b <= s.x + s.b + 0.5 && r.y + r.h <= s.y + s.h + 0.5;
    }

    function rahmenText(r) {
        return '{' + [r.x, r.y, r.b, r.h].map(function(v) { return Math.round(v); }).join(',') + '}';
    }

    function zweiAbsaetzeLeer() {
        ctx.oeffnen();
        var f = [];
        var undo = B.undoStapel.length;
        var erg = B.blocktextEinfuegen('Erster Absatz\n\nZweiter Absatz');
        var t = texte();
        if (!erg || erg.eingefuegt !== 2 || erg.keinPlatz || erg.rest.length) f.push('Ergebnis ' + JSON.stringify(erg));
        if (t.length !== 2) return { ok: false, detail: t.length + ' Texte' };
        if (t[0].text !== 'Erster Absatz' || t[1].text !== 'Zweiter Absatz') f.push('Texte ' + JSON.stringify([t[0].text, t[1].text]));
        if (t[0].breite !== 2 || t[1].breite !== 2) f.push('breite ' + t[0].breite + '/' + t[1].breite);
        if (t[0].x !== 20 || t[0].y !== 20) f.push('erstes bei ' + t[0].x + '/' + t[0].y);
        var r0 = B._rahmen(t[0]);
        if (!(t[1].y >= r0.y + r0.h) || t[1].x !== 20) f.push('zweites bei ' + t[1].x + '/' + t[1].y + ', erstes endet ' + (r0.y + r0.h));
        if (B.undoStapel.length !== undo + 1) f.push('Undo ' + undo + ' -> ' + B.undoStapel.length);
        if (B.currentTool !== 'lasso') f.push('Werkzeug ' + B.currentTool);
        if (B.auswahl.join() !== [t[0].id, t[1].id].join()) f.push('Auswahl ' + B.auswahl.join());
        var knopf = B.overlay.querySelector('.cbd-board-tool.active');
        if (!knopf || knopf.getAttribute('data-tool') !== 'lasso') f.push('aktiver Knopf ' + (knopf && knopf.getAttribute('data-tool')));
        var leiste = B.overlay.querySelector('.cbd-board-auswahl-leiste');
        if (!leiste || leiste.style.display === 'none') f.push('Auswahlleiste nicht sichtbar');
        return ergebnis(f);
    }

    function unterStrich() {
        ctx.oeffnen();
        var s = strich(50, 100, 400, 100);
        var unten = B._rahmen(s).y + B._rahmen(s).h;
        B.blocktextEinfuegen('Text unter dem Strich');
        var t = texte();
        if (t.length !== 1) return { ok: false, detail: t.length + ' Texte' };
        return t[0].y >= unten + 20 ? true : { ok: false, detail: 'Text bei y ' + t[0].y + ', Strich endet ' + unten };
    }

    function langerText() {
        ctx.oeffnen();
        var f = [];
        var erg = B.blocktextEinfuegen(absaetze(60));
        if (!erg || erg.px !== 18) f.push('px ' + (erg && erg.px));
        if (!erg || !erg.rest.length) f.push('kein Rest');
        var flaeche = { x: 0, y: 0, b: B.cssWidth, h: B.cssHeight };
        texte().forEach(function(t) {
            if (!innerhalb(B._rahmen(t), flaeche)) f.push(t.id + ' außerhalb: ' + rahmenText(B._rahmen(t)));
            if (t.text.length > 5000) f.push(t.id + ' hat ' + t.text.length + ' Zeichen');
        });
        if (erg && texte().length + erg.rest.length < 60) f.push('Absätze verloren: ' + texte().length + ' + ' + erg.rest.length);
        return ergebnis(f);
    }

    function restAufNeueSeite() {
        ctx.oeffnen();
        var f = [];
        var erg = B.blocktextEinfuegen(absaetze(60));
        var erg2 = B.blocktextAufNeueSeite(erg.rest);
        if (B.currentPageIndex !== 1 || B.totalPages !== 2) f.push('Seite ' + B.currentPageIndex + ' von ' + B.totalPages);
        if (!erg2 || !erg2.eingefuegt || texte().length !== erg2.eingefuegt) f.push('Seite 2: ' + texte().length + ' Texte, Ergebnis ' + JSON.stringify(erg2 && erg2.eingefuegt));
        if (B.undoStapel.length !== 1) f.push('Undo-Stapel Seite 2: ' + B.undoStapel.length);
        // Seite 2 setzt genau dort fort, wo Seite 1 aufgehört hat
        if (texte().length && erg.rest[0].indexOf(texte()[0].text.split('\n')[0]) !== 0) {
            f.push('Seite 2 beginnt mit ' + JSON.stringify(texte()[0].text.slice(0, 40)) + ' statt ' + JSON.stringify(erg.rest[0].slice(0, 40)));
        }
        if (B.auswahl.length !== texte().length) f.push('Auswahl ' + B.auswahl.length);
        return ergebnis(f);
    }

    function grueneTafel() {
        ctx.oeffnen();
        var f = [];
        B.setBoardColor('#1a472a');
        B.setColor('#000000');
        B.blocktextEinfuegen('Auf grün');
        var t = texte();
        if (t.length !== 1 || t[0].farbe !== '#ffffff') f.push('Farbe ' + (t[0] && t[0].farbe));
        B.setBoardColor('#ffffff');
        B.setColor('#c00000');
        B.blocktextEinfuegen('Rot auf weiß');
        t = texte();
        if (t.length !== 2 || t[1].farbe !== '#c00000') f.push('Stiftfarbe nicht übernommen: ' + (t[1] && t[1].farbe));
        return ergebnis(f);
    }

    function zoomUndVerschiebung() {
        ctx.oeffnen();
        var f = [];
        B.zoom = 2;
        B.panX = -300;
        B.panY = -200;
        B.updateCanvasTransform();
        var s = B._sichtbarerBereich();
        if (Math.abs(s.x - 150) > 0.01 || Math.abs(s.y - 100) > 0.01) f.push('Ausschnitt ' + rahmenText(s));
        var erg = B.blocktextEinfuegen(absaetze(3));
        if (!erg || erg.eingefuegt !== 3) f.push('eingefügt ' + (erg && erg.eingefuegt));
        texte().forEach(function(t) {
            if (!innerhalb(B._rahmen(t), s)) f.push(t.id + ' außerhalb ' + rahmenText(B._rahmen(t)) + ' von ' + rahmenText(s));
        });
        if (texte().length && (texte()[0].x !== s.x + 20 || texte()[0].y !== s.y + 20)) {
            f.push('erstes bei ' + texte()[0].x + '/' + texte()[0].y);
        }
        return ergebnis(f);
    }

    function tafelVoll() {
        ctx.oeffnen();
        strich(10, 5, 10, B.cssHeight - 5);
        var vorher = el().slice();
        var undo = B.undoStapel.length;
        var erg = B.blocktextEinfuegen('passt nicht\n\nauch nicht');
        var f = [];
        if (!erg || !erg.keinPlatz || erg.eingefuegt !== 0 || erg.rest.length !== 2) f.push('Ergebnis ' + JSON.stringify(erg));
        if (el().length !== vorher.length || el()[0] !== vorher[0]) f.push('Elemente geändert');
        if (B.undoStapel.length !== undo) f.push('Undo-Eintrag');
        return ergebnis(f);
    }

    function leererText() {
        ctx.oeffnen();
        var undo = B.undoStapel.length;
        var erg = B.blocktextEinfuegen(' \n\n  \n');
        var f = [];
        if (!erg || erg.eingefuegt !== 0 || erg.keinPlatz || erg.rest.length) f.push('Ergebnis ' + JSON.stringify(erg));
        if (el().length || B.undoStapel.length !== undo) f.push('geändert');
        if (B.currentTool !== 'pen') f.push('Werkzeug ' + B.currentTool);
        if (B.blocktextAufNeueSeite([]).eingefuegt !== 0 || B.totalPages !== 1) f.push('leerer Rest legt Seite an');
        return ergebnis(f);
    }

    function strgZ() {
        ctx.oeffnen();
        var s = strich(50, 60, 300, 60);
        B.blocktextEinfuegen(absaetze(4));
        if (texte().length !== 4) return { ok: false, detail: texte().length + ' Texte' };
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true, cancelable: true }));
        var f = [];
        if (texte().length) f.push(texte().length + ' Texte nach Strg+Z');
        if (el().length !== 1 || el()[0] !== s) f.push('Strich nicht unverändert');
        return ergebnis(f);
    }

    function speichernWiederoeffnen() {
        ctx.oeffnen();
        B.blocktextEinfuegen(absaetze(5));
        // Text, Schrift und Farbe exakt; die Lage mit 0,05 px Spiel - das
        // Speicherformat rundet (148.39999999999998 kommt als 148.4 zurück)
        function stand() {
            return texte().map(function(t) {
                return { s: [t.text, t.breite, t.farbe].join('|'), x: t.x, y: t.y };
            });
        }
        var vorher = stand();
        B.saveDrawing();
        B.destroy();
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        var nachher = stand();
        var f = [];
        if (vorher.length !== 5 || nachher.length !== 5) f.push('vorher ' + vorher.length + ', nachher ' + nachher.length);
        for (var i = 0; i < Math.min(vorher.length, nachher.length); i++) {
            if (vorher[i].s !== nachher[i].s) f.push(i + ': ' + JSON.stringify(nachher[i].s));
            if (Math.abs(vorher[i].x - nachher[i].x) > 0.05 || Math.abs(vorher[i].y - nachher[i].y) > 0.05) {
                f.push(i + ': Lage ' + nachher[i].x + '/' + nachher[i].y + ' statt ' + vorher[i].x + '/' + vorher[i].y);
            }
        }
        return ergebnis(f);
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

        // AP-2.2
        ctx = c;
        p('Blocktext einfügen: zwei Absätze auf leerer Tafel (24 px, 20/20, 1 Undo, Lasso, Auswahl)', zweiAbsaetzeLeer);
        p('Blocktext einfügen: unter vorhandenem Strich (+20 px)', unterStrich);
        p('Blocktext einfügen: langer Text - 18 px, Rest, alles innerhalb der Fläche, ≤ 5000 Zeichen', langerText);
        p('Blocktext einfügen: Rest auf neuer Seite (Seite 2, eigener Undo-Schritt)', restAufNeueSeite);
        p('Blocktext einfügen: grüne Tafel + schwarzer Stift -> weiß, sonst Stiftfarbe', grueneTafel);
        p('Blocktext einfügen: Zoom 2 und Verschiebung - im sichtbaren Ausschnitt', zoomUndVerschiebung);
        p('Blocktext einfügen: Tafel voll -> keinPlatz, nichts geändert, kein Undo', tafelVoll);
        p('Blocktext einfügen: leerer Text -> nichts, leerer Rest -> keine Seite', leererText);
        p('Blocktext einfügen: Strg+Z nimmt alles Eingefügte zurück', strgZ);
        p('Blocktext einfügen: Texte und Schrift überleben Speichern und Wiederöffnen', speichernWiederoeffnen);
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
