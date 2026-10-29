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
 * Formatierung lesen (PLAN-Blocktext-Formatierung.md, AP-2.1, F6/F10):
 *   _blocktextLesen(wurzel, optionen) liefert je Absatz zusätzlich teile
 *   (fett = font-weight >= 600, kursiv = italic/oblique, Farbe = berechnete
 *   Farbe != Grundfarbe, als '#rrggbb'); Fälle (a)-(l) des Plans, Regel F6,
 *   Befund B10 (color(srgb …)/oklab(…)), _blocktextAbsaetze() und
 *   asynchron die Teile des Testmaterials (Verkettung = Text, gültig).
 *   Prüfnamen beginnen mit "Formatierung lesen".
 *
 * Formatiert einfügen (PLAN-Blocktext-Formatierung.md, AP-2.3, F7/F14):
 *   blocktextEinfuegen(Array von Absätzen [{text, teile?}]) legt Text-
 *   Elemente mit teile an (Teilfarben über lesbareLauffarbe, Messung über
 *   _textMass() mit fett/kursiv, ein Undo-Schritt, Modellprüfung ändert
 *   nichts), blocktextAufNeueSeite() setzt einen formatierten Rest fort.
 *   Prüfnamen beginnen mit "Formatiert einfügen".
 *
 * Bearbeitungsfeld (PLAN-Blocktext-Formatierung.md, AP-2.4, F8-F10): Die
 *   Vorschau ist ein formatiert bearbeitbares div (_textFeldBauen) mit
 *   Formatleiste; gelesen über _textFeldLesen. Aufbau und Tafelfarben,
 *   berechnete Stile, Rundlauf Feld = Blockbereich, Knöpfe F/K/Farben/
 *   Standard (Markierung per Range), Klartext-Einfügen, Strg+U, Ziehen,
 *   Zeilenwechsel, Modus "bearbeiten", Tastatur der Leiste, gemerkte
 *   Markierung (asynchron). Drei ältere Vorschau-Prüfungen (vorschauKlick,
 *   gekuerztEinfuegen, klickNebenFenster) lesen das Feld seit AP-2.4 über
 *   feldText()/feldSetzen() statt textarea.value (geplante Umstellung F8).
 *   Prüfnamen beginnen mit "Bearbeitungsfeld".
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
        // y: mindestens 20, genau so weit unten, dass die Auswahlleiste
        // darüber passt (Folge-AP G9, eigene Prüfungen unten) - bis 3.1.146
        // stand hier y === 20
        if (t[0].x !== 20 || t[0].y < 20) f.push('erstes bei ' + t[0].x + '/' + t[0].y);
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
        // y mindestens s.y + 20 (Folge-AP G9: Platz für die Auswahlleiste)
        if (texte().length && (texte()[0].x !== s.x + 20 || texte()[0].y < s.y + 20)) {
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

    // ------------------------------------------------------------------
    // AP-2.3: Knopf, Vorschau, Rückfrage
    // ------------------------------------------------------------------
    function langerInhalt(n) {
        var l = [];
        for (var i = 1; i <= n; i++) l.push('<p>Absatz ' + i + ' mit etwas Text, damit er eine Zeile füllt.</p>');
        return l.join('');
    }

    function bereich() {
        return B.overlay.querySelector('.cbd-board-content');
    }

    function fenster() {
        return document.querySelector('.cbd-board-blocktext-overlay');
    }

    function feld() {
        return document.querySelector('.cbd-board-blocktext-feld');
    }

    // Bearbeitungsfeld der Vorschau (PLAN-Blocktext-Formatierung.md, AP-2.4,
    // F8): ein formatiert bearbeitbares div statt des früheren Textfelds.
    // feldText(): Texte der Absätze aus B._textFeldLesen() mit Leerzeile
    // verbunden (wie früher feld().value); feldSetzen(absaetze): Inhalt über
    // DOM-Methoden ersetzen, je Absatz ein div (wie früher feld().value = …).
    function feldText() {
        return B._textFeldLesen(feld()).map(function(a) { return a.text; }).join('\n\n');
    }

    function feldSetzen(absaetze) {
        var fe = feld();
        while (fe.firstChild) fe.removeChild(fe.firstChild);
        absaetze.forEach(function(a) {
            var d = document.createElement('div');
            d.textContent = a;
            fe.appendChild(d);
        });
    }

    // Text vor der Schreibmarke im Feld t ('' = Marke am Anfang) oder null,
    // wenn die Auswahl nicht eingeklappt im Feld steht
    function textVorMarke(t) {
        var sel = window.getSelection();
        if (!sel || sel.rangeCount !== 1 || !sel.isCollapsed) return null;
        var r = sel.getRangeAt(0);
        if (!t.contains(r.startContainer)) return null;
        var vor = document.createRange();
        vor.setStart(t, 0);
        vor.setEnd(r.startContainer, r.startOffset);
        return vor.toString();
    }

    function taste(ziel, key, extra) {
        var init = { key: key, bubbles: true, cancelable: true };
        for (var k in (extra || {})) init[k] = extra[k];
        var ev = new KeyboardEvent('keydown', init);
        ziel.dispatchEvent(ev);
        return ev;
    }

    function vorschauOeffnen() {
        B.overlay.querySelector('.cbd-board-blocktext-knopf').click();
        return feld();
    }

    function leisteKlebt() {
        oeffnenMit(langerInhalt(80));
        var f = [];
        var b = bereich();
        var leiste = b.firstElementChild;
        if (!leiste || !leiste.classList.contains('cbd-board-blocktext-leiste')) {
            return { ok: false, detail: 'erstes Kind: ' + (leiste && leiste.className) };
        }
        var knopf = leiste.querySelector('.cbd-board-blocktext-knopf');
        var r = knopf.getBoundingClientRect();
        if (!(r.width > 0 && r.height > 0)) f.push('Knopf unsichtbar');
        if (knopf.textContent.indexOf('Text auf die Tafel') < 0) f.push('Beschriftung ' + JSON.stringify(knopf.textContent));
        b.scrollTop = 500;
        if (b.scrollTop < 100) f.push('Bereich scrollt nicht (' + b.scrollTop + ')');
        var oben = b.getBoundingClientRect().top;
        var r2 = knopf.getBoundingClientRect();
        if (r2.top < oben - 0.5 || r2.bottom > b.getBoundingClientRect().bottom) f.push('nach Scrollen bei ' + Math.round(r2.top) + ', Bereich ab ' + Math.round(oben));
        // Die Leiste deckt den Innenabstand ab: Ihre Oberkante liegt an der
        // Innenkante des Rahmens, nicht darunter (kein Text scheint durch)
        var lo = leiste.getBoundingClientRect().top;
        if (Math.abs(lo - (oben + b.clientTop)) > 1) f.push('Leiste klebt bei ' + Math.round(lo) + ' statt ' + Math.round(oben + b.clientTop));
        var vorher = getComputedStyle(knopf).fontSize;
        var regler = B.fontSize;
        B.setFontSize(300);
        var nachher = getComputedStyle(knopf).fontSize;
        B.setFontSize(regler); // gilt über das Schließen hinaus
        var lb = getComputedStyle(leiste).backgroundColor;
        if (lb !== getComputedStyle(b).backgroundColor) f.push('Leiste ' + lb + ' auf Bereich ' + getComputedStyle(b).backgroundColor);
        if (vorher !== '13px' || nachher !== '13px') f.push('Schrift ' + vorher + ' -> ' + nachher);
        if (B._blocktextAlsText().indexOf('Text auf die Tafel') >= 0) f.push('Beschriftung im gelesenen Text');
        if (B.overlay.querySelector('.cbd-board-split').children.length !== 2) f.push('Split hat nicht mehr 2 Kinder');
        return ergebnis(f);
    }

    function vorschauKlick() {
        oeffnenMit('<p>Eins</p><p>Zwei</p>');
        var f = [];
        var t = vorschauOeffnen();
        if (!fenster()) return { ok: false, detail: 'kein Fenster' };
        if (!t) return { ok: false, detail: 'kein Textfeld' };
        // AP-2.4 (F8): Inhalt über _textFeldLesen, Schreibmarke über die
        // Auswahl des Dokuments (am Anfang des Felds, eingeklappt)
        var inhalt = feldText();
        if (inhalt !== 'Eins\n\nZwei') f.push('Inhalt ' + JSON.stringify(inhalt));
        if (document.activeElement !== t) f.push('Fokus auf ' + (document.activeElement && document.activeElement.className));
        var vorMarke = textVorMarke(t);
        if (vorMarke !== '') f.push('Schreibmarke nicht eingeklappt am Anfang (Text davor ' + JSON.stringify(vorMarke) + ')');
        if (fenster().parentNode !== document.body) f.push('Fenster nicht am body');
        if (!fenster().querySelector('.cbd-board-blocktext-einfuegen')) f.push('kein Knopf "Auf die Tafel"');
        return ergebnis(f);
    }

    function escapeImFeld() {
        oeffnenMit('<p>Eins</p>');
        var t = vorschauOeffnen();
        var ev = taste(t, 'Escape');
        var f = [];
        if (fenster()) f.push('Fenster noch offen');
        if (!B.overlay) f.push('Tafel geschlossen');
        if (!ev.defaultPrevented) f.push('Escape nicht abgefangen');
        return ergebnis(f);
    }

    function strgZImFeld() {
        oeffnenMit('<p>Eins</p>');
        ctx.zeiger('pointerdown', 3, 'mouse', 300, 300);
        ctx.zeiger('pointermove', 3, 'mouse', 400, 300);
        ctx.zeiger('pointerup', 3, 'mouse', 400, 300);
        var anzahl = el().length;
        var undo = B.undoStapel.length;
        var t = vorschauOeffnen();
        taste(t, 'z', { ctrlKey: true });
        var f = [];
        if (anzahl !== 1) f.push('Strich fehlt vorher (' + anzahl + ')');
        if (B.undoStapel.length !== undo || el().length !== anzahl) f.push('Tafel verändert: Undo ' + undo + ' -> ' + B.undoStapel.length + ', Elemente ' + el().length);
        if (!fenster()) f.push('Fenster zu');
        return ergebnis(f);
    }

    function entfImFeld() {
        oeffnenMit('<p>Eins</p>');
        B.blocktextEinfuegen('Ausgewählt');
        var ids = B.auswahl.slice();
        var anzahl = el().length;
        var t = vorschauOeffnen();
        taste(t, 'Delete');
        taste(t, 'Backspace');
        taste(t, 'd', { ctrlKey: true });
        var f = [];
        if (ids.length !== 1) f.push('vorher keine Auswahl');
        if (B.auswahl.join() !== ids.join()) f.push('Auswahl ' + B.auswahl.join());
        if (el().length !== anzahl) f.push('Elemente ' + anzahl + ' -> ' + el().length);
        return ergebnis(f);
    }

    function gekuerztEinfuegen() {
        oeffnenMit('<p>Eins</p><p>Zwei</p><p>Drei</p>');
        vorschauOeffnen();
        feldSetzen(['Eins', 'Drei']); // AP-2.4 (F8): Feld statt t.value
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var f = [];
        if (fenster()) f.push('Fenster noch offen');
        var ist = texte().map(function(x) { return x.text; }).join('|');
        if (ist !== 'Eins|Drei') f.push('Elemente ' + JSON.stringify(ist));
        if (B.currentTool !== 'lasso' || B.auswahl.length !== 2) f.push('Werkzeug ' + B.currentTool + ', Auswahl ' + B.auswahl.length);
        return ergebnis(f);
    }

    function restNeueSeite() {
        oeffnenMit(langerInhalt(60));
        vorschauOeffnen();
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var f = [];
        var rf = fenster();
        if (!rf || !rf.classList.contains('cbd-board-blocktext-rest')) return { ok: false, detail: 'keine Rückfrage' };
        if (!/Textbl(ock passt|öcke passen) nicht mehr auf diese Seite\./.test(rf.textContent)) f.push('Meldung ' + JSON.stringify(rf.querySelector('p').textContent));
        var aufSeite1 = texte().length;
        rf.querySelector('.cbd-board-blocktext-neue-seite').click();
        if (B.totalPages !== 2 || B.currentPageIndex !== 1) f.push('Seite ' + B.currentPageIndex + ' von ' + B.totalPages);
        if (!texte().length) f.push('Seite 2 leer');
        if (!aufSeite1) f.push('Seite 1 leer');
        // 60 Absätze passen nicht auf zwei Seiten: erneute Rückfrage
        var rf2 = fenster();
        if (!rf2 || !rf2.classList.contains('cbd-board-blocktext-rest')) f.push('keine zweite Rückfrage');
        else {
            rf2.querySelector('.cbd-board-confirm-cancel').click();
            if (fenster() || B.totalPages !== 2) f.push('Weglassen auf Seite 2: ' + B.totalPages + ' Seiten');
        }
        return ergebnis(f);
    }

    function restWeglassen() {
        oeffnenMit(langerInhalt(60));
        vorschauOeffnen();
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var rf = fenster();
        if (!rf) return { ok: false, detail: 'keine Rückfrage' };
        var f = [];
        if (document.activeElement !== rf.querySelector('.cbd-board-blocktext-neue-seite')) f.push('Fokus nicht auf "Auf neuer Seite einfügen"');
        taste(document.activeElement, 'Escape');
        if (fenster()) f.push('Escape schließt die Rückfrage nicht');
        if (B.totalPages !== 1) f.push(B.totalPages + ' Seiten');
        if (!B.overlay) f.push('Tafel geschlossen');
        return ergebnis(f);
    }

    function keinPlatzRueckfrage() {
        oeffnenMit('<p>Eins</p>');
        ctx.zeiger('pointerdown', 3, 'mouse', 10, 5);
        for (var y = 15; y < B.cssHeight; y += 10) ctx.zeiger('pointermove', 3, 'mouse', 10, Math.min(y, B.cssHeight - 5));
        ctx.zeiger('pointerup', 3, 'mouse', 10, B.cssHeight - 5);
        vorschauOeffnen();
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var rf = fenster();
        var f = [];
        if (!rf || rf.querySelector('p').textContent !== 'Auf dieser Seite ist kein Platz mehr.') f.push('Meldung ' + (rf && rf.querySelector('p').textContent));
        else {
            rf.querySelector('.cbd-board-blocktext-neue-seite').click();
            if (B.totalPages !== 2 || texte().length !== 1) f.push('neue Seite: ' + B.totalPages + ' Seiten, ' + texte().length + ' Texte');
            if (fenster()) f.push('Fenster nach Einfügen offen');
        }
        return ergebnis(f);
    }

    function leererBlock() {
        oeffnenMit('<p hidden>versteckt</p>');
        vorschauOeffnen();
        var fe = fenster();
        var f = [];
        if (!fe) return { ok: false, detail: 'kein Fenster' };
        if (feld()) f.push('Textfeld vorhanden');
        if (fe.querySelector('p').textContent !== 'In diesem Block gibt es keinen Text zum Übernehmen.') f.push('Meldung ' + fe.querySelector('p').textContent);
        var knoepfe = fe.querySelectorAll('button');
        if (knoepfe.length !== 1 || knoepfe[0].textContent !== 'Schließen') f.push('Knöpfe ' + knoepfe.length);
        else knoepfe[0].click();
        if (fenster()) f.push('Schließen schließt nicht');
        return ergebnis(f);
    }

    function destroyMitFenster() {
        oeffnenMit('<p>Eins</p>');
        vorschauOeffnen();
        var f = [];
        if (!fenster()) f.push('kein Fenster');
        B.destroy();
        if (fenster()) f.push('Fenster nach destroy() noch da');
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        if (fenster()) f.push('Fenster nach neuem Öffnen da');
        return ergebnis(f);
    }

    function klickNebenFenster() {
        oeffnenMit('<p>Eins</p>');
        vorschauOeffnen();
        feldSetzen(['gekürzt']); // AP-2.4 (F8): Feld statt t.value
        var fe = fenster();
        fe.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        var f = [];
        if (!fenster() || feldText() !== 'gekürzt') f.push('Klick neben das Fenster schließt es');
        // Tasten bei Fokus außerhalb des Fensters wirken nicht auf die Tafel
        document.body.focus();
        taste(document.body, 'Escape');
        if (!B.overlay) f.push('Escape am body schließt die Tafel');
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // AP-2.fix1: Befunde aus AP-2.rev
    // ------------------------------------------------------------------
    function schild() {
        return document.querySelector('.cbd-board-blocktext-schild');
    }

    // Trifft ein Zeigerklick an der Mitte von el den Klickschutz?
    function schildFaengt(el) {
        var r = el.getBoundingClientRect();
        var oben = document.elementFromPoint((r.left + r.right) / 2, (r.top + r.bottom) / 2);
        return !!oben && oben === schild();
    }

    function warte(ms) {
        return new Promise(function(fertig) { setTimeout(fertig, ms); });
    }

    function m1KlickschutzRueckfrage() {
        oeffnenMit(langerInhalt(60));
        vorschauOeffnen();
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var f = [];
        var rf = fenster();
        if (!rf) return { ok: false, detail: 'keine Rückfrage' };
        if (!schild()) return { ok: false, detail: 'kein Klickschutz nach "Auf die Tafel"' };
        if (!schildFaengt(rf.querySelector('.cbd-board-blocktext-neue-seite'))) f.push('Knopf der Rückfrage nicht abgedeckt');
        rf.querySelector('.cbd-board-blocktext-neue-seite').click();
        var rf2 = fenster();
        if (!schild()) f.push('kein Klickschutz nach "Auf neuer Seite einfügen"');
        else if (rf2 && !schildFaengt(rf2.querySelector('.cbd-board-blocktext-neue-seite'))) f.push('Knopf der zweiten Rückfrage nicht abgedeckt');
        if (B.totalPages !== 2) f.push(B.totalPages + ' Seiten');
        return warte(450).then(function() {
            if (schild()) f.push('Klickschutz nach 450 ms noch da');
            return ergebnis(f);
        });
    }

    function g3KlickschutzOhneRest() {
        oeffnenMit('<p>Eins</p><p>Zwei</p>');
        vorschauOeffnen();
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var f = [];
        if (fenster()) f.push('Fenster offen');
        if (!schild()) return { ok: false, detail: 'kein Klickschutz' };
        if (!schildFaengt(B.drawingCanvas)) f.push('Zeichenfläche nicht abgedeckt');
        var ids = B.auswahl.join();
        return warte(450).then(function() {
            if (schild()) f.push('Klickschutz nach 450 ms noch da');
            if (B.auswahl.join() !== ids || !ids) f.push('Auswahl ' + B.auswahl.join());
            return ergebnis(f);
        });
    }

    function gehalteneTaste() {
        oeffnenMit(langerInhalt(60));
        vorschauOeffnen();
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var knopf = fenster() && fenster().querySelector('.cbd-board-blocktext-neue-seite');
        if (!knopf) return { ok: false, detail: 'keine Rückfrage' };
        var ev = taste(knopf, 'Enter', { repeat: true });
        var f = [];
        if (!ev.defaultPrevented) f.push('gehaltenes Enter nicht abgefangen');
        if (taste(knopf, 'Enter').defaultPrevented) f.push('einfaches Enter abgefangen');
        return ergebnis(f);
    }

    function m2SpeichernAbwarten() {
        oeffnenMit(langerInhalt(60));
        vorschauOeffnen();
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var knopf = fenster() && fenster().querySelector('.cbd-board-blocktext-neue-seite');
        if (!knopf) return { ok: false, detail: 'keine Rückfrage' };
        var f = [];
        B.isSaving = true; // wie ein noch laufendes saveToServer()
        knopf.click();
        if (B.totalPages !== 1) f.push('Seite angelegt, während gespeichert wird');
        if (!knopf.disabled || knopf.textContent.indexOf('gespeichert') < 0) f.push('Knopf ' + (knopf.disabled ? 'gesperrt' : 'frei') + ', ' + JSON.stringify(knopf.textContent));
        return warte(400).then(function() {
            if (B.totalPages !== 1) f.push('nach 400 ms trotz Sperre Seite angelegt');
            B.isSaving = false;
            return warte(400);
        }).then(function() {
            if (B.totalPages !== 2 || !texte().length) f.push('nach dem Speichern: ' + B.totalPages + ' Seiten, ' + texte().length + ' Texte');
            return ergebnis(f);
        });
    }

    function g5KeineLeereSeite() {
        oeffnenMit('<p>Eins</p>');
        var f = [];
        B.zoom = 20;
        B.updateCanvasTransform();
        var erg = B.blocktextAufNeueSeite(['Ein Absatz']);
        if (!erg || !erg.keinPlatz || erg.eingefuegt !== 0) f.push('Ergebnis ' + JSON.stringify(erg));
        if (B.totalPages !== 1) f.push(B.totalPages + ' Seiten (leere Seite angelegt)');
        if (erg && erg.rest.join() !== 'Ein Absatz') f.push('Rest ' + JSON.stringify(erg.rest));
        return ergebnis(f);
    }

    // Asynchron: close() zerstört die Tafel erst nach 200 ms per Zeitgeber -
    // der muss innerhalb dieser Prüfung ablaufen, sonst träfe er die nächste
    function g2CloseSchliesstFenster() {
        oeffnenMit('<p>Eins</p>');
        vorschauOeffnen();
        B.close();
        var offen = !!fenster();
        return warte(350).then(function() {
            return offen ? { ok: false, detail: 'Fenster bleibt während der Schließ-Animation' } : true;
        });
    }

    function g1Fokus() {
        oeffnenMit('<p>Eins</p>');
        var t = vorschauOeffnen();
        var f = [];
        var knoepfe = fenster().querySelectorAll('button');
        var letzter = knoepfe[knoepfe.length - 1];
        letzter.focus();
        var ev = taste(letzter, 'Tab');
        if (!ev.defaultPrevented || document.activeElement !== t) f.push('Tab am Ende kreist nicht (Fokus ' + (document.activeElement && document.activeElement.className) + ')');
        t.focus();
        var ev2 = taste(t, 'Tab', { shiftKey: true });
        if (!ev2.defaultPrevented || document.activeElement !== letzter) f.push('Umschalt+Tab am Anfang kreist nicht');
        t.focus(); // nach Umschalt+Tab steht der Fokus auf dem letzten Knopf
        var ev3 = taste(t, 'Tab');
        if (ev3.defaultPrevented) f.push('Tab mitten im Fenster abgefangen');
        taste(t, 'Escape');
        var knopf = B.overlay.querySelector('.cbd-board-blocktext-knopf');
        if (document.activeElement !== knopf) f.push('Fokus nach Escape auf ' + (document.activeElement && (document.activeElement.className || document.activeElement.tagName)));
        vorschauOeffnen();
        fenster().querySelector('.cbd-board-confirm-cancel').click();
        if (document.activeElement !== knopf) f.push('Fokus nach "Abbrechen" nicht auf dem Knopf');
        return ergebnis(f);
    }

    function g6Details() {
        oeffnenMit('<details><summary>Frage</summary><p>Geheime Lösung</p></details>' +
            '<details open><summary>Offen</summary><p>Sichtbar</p></details>');
        return vergleich(B._blocktextAlsText(), 'Frage\n\nOffen\n\nSichtbar');
    }

    function knopfleisteUnsichtbar() {
        // Leiste aus AP-2.3 (Klasse) und Aktionsleiste fallen weg, auch wenn
        // sie im Blockbereich stehen
        oeffnenMit('<div class="cbd-board-blocktext-leiste"><span>Text auf die Tafel</span></div><p>Inhalt</p>');
        return vergleich(B._blocktextAlsText(), 'Inhalt');
    }

    // ------------------------------------------------------------------
    // Folge-AP G9 (Review AP-2.rev): Die Auswahlleiste verdeckt nach dem
    // Einfügen nicht die ersten Zeilen - auch nicht nach A+
    // ------------------------------------------------------------------

    // Gezeichneter Auswahlrahmen (auswahlRahmen()) in Bildschirmkoordinaten
    function auswahlAmSchirm() {
        var r = B.auswahlRahmen();
        var cv = B.drawingCanvas.getBoundingClientRect();
        var fx = cv.width / B.cssWidth, fy = cv.height / B.cssHeight;
        return { oben: cv.top + r.y * fy, unten: cv.top + (r.y + r.h) * fy, links: cv.left + r.x * fx };
    }

    // Leiste sichtbar, innerhalb der Zeichenfläche und nicht über dem Rahmen
    function leisteFrei(f, wann) {
        var l = B.overlay.querySelector('.cbd-board-auswahl-leiste');
        if (!l || l.style.display === 'none') {
            f.push(wann + ': Leiste nicht sichtbar');
            return;
        }
        var lr = l.getBoundingClientRect(), a = auswahlAmSchirm();
        var ba = B.overlay.querySelector('.cbd-board-canvas-area').getBoundingClientRect();
        if (lr.bottom > a.oben + 1 && lr.top < a.unten - 1) {
            f.push(wann + ': Leiste ' + Math.round(lr.top) + '-' + Math.round(lr.bottom) +
                ' liegt auf dem Rahmen ' + Math.round(a.oben) + '-' + Math.round(a.unten));
        }
        if (lr.top < ba.top - 0.5 || lr.bottom > ba.bottom + 0.5) f.push(wann + ': Leiste außerhalb der Zeichenfläche');
    }

    function g9LangerText() {
        ctx.oeffnen();
        var f = [];
        var erg = B.blocktextEinfuegen(absaetze(60));
        if (!erg || !erg.rest.length) f.push('kein Rest - Seite nicht gefüllt');
        leisteFrei(f, 'nach dem Einfügen');
        return ergebnis(f);
    }

    function g9NachAPlus() {
        ctx.oeffnen();
        var f = [];
        B.blocktextEinfuegen(absaetze(60));
        if (!B.auswahlTextGroesse(1)) f.push('A+ ohne Wirkung');
        leisteFrei(f, 'nach A+');
        B.auswahlTextGroesse(1);
        leisteFrei(f, 'nach zweimal A+');
        return ergebnis(f);
    }

    function g9Zoom() {
        ctx.oeffnen();
        var f = [];
        B.zoom = 2;
        B.panX = -300;
        B.panY = -200;
        B.updateCanvasTransform();
        var erg = B.blocktextEinfuegen(absaetze(60));
        if (!erg || !erg.eingefuegt) f.push('nichts eingefügt');
        var s = B._sichtbarerBereich();
        texte().forEach(function(t) {
            if (!innerhalb(B._rahmen(t), s)) f.push(t.id + ' außerhalb ' + rahmenText(B._rahmen(t)));
        });
        leisteFrei(f, 'Zoom 2');
        return ergebnis(f);
    }

    function g9KeinPlatzFuerLeiste() {
        // Sehr niedriger Ausschnitt: Mit Platz für die Leiste passte nichts
        // mehr hin - dann wie bisher ohne ihn, statt "kein Platz"
        ctx.oeffnen();
        var echt = B._sichtbarerBereich;
        B._sichtbarerBereich = function() { return { x: 0, y: 0, b: 600, h: 70 }; };
        var erg;
        try {
            erg = B.blocktextEinfuegen('Eine Zeile');
        } finally {
            B._sichtbarerBereich = echt;
        }
        if (!erg || erg.keinPlatz || erg.eingefuegt !== 1) return { ok: false, detail: 'Ergebnis ' + JSON.stringify(erg) };
        return texte()[0].y === 20 ? true : { ok: false, detail: 'y ' + texte()[0].y };
    }

    // ------------------------------------------------------------------
    // Formatierung lesen (PLAN-Blocktext-Formatierung.md, AP-2.1, F6/F10):
    // _blocktextLesen(wurzel, optionen) liefert je Absatz neben text auch
    // teile [{t, f?, k?, farbe?}] - nur, wenn der Absatz formatiert ist.
    // Grundfarbe der Prüfseite ist Schwarz (Blockbereich ohne Website-CSS).
    // ------------------------------------------------------------------

    // Absätze als {text, teile?}. Ein vorhandener Schlüssel teile mit dem
    // Wert undefined wird sichtbar gemacht - "kein Schlüssel teile" gehört
    // zur Regel (F3: ohne Formatierung kein Feld).
    function textUndTeile(liste) {
        return (liste || []).map(function(a) {
            var o = { text: a.text };
            if (Object.prototype.hasOwnProperty.call(a, 'teile')) {
                o.teile = a.teile === undefined ? 'undefined' : a.teile;
            }
            return o;
        });
    }

    function teileVergleich(ist, soll) {
        var i = JSON.stringify(textUndTeile(ist));
        var s = JSON.stringify(soll);
        return i === s ? true : { ok: false, detail: 'ist ' + i + ' / soll ' + s };
    }

    // Prüffunktion: Tafel mit html öffnen, _blocktextLesen(Blockbereich,
    // optionen) mit soll = [{text, teile?}] vergleichen (Schlüsselreihenfolge
    // der Teile t, f, k, farbe wie bei teileZusammenlegen)
    function teileFall(html, soll, optionen) {
        return function() {
            oeffnenMit(html);
            return teileVergleich(B._blocktextLesen(bereich(), optionen), soll);
        };
    }

    // Fälle (a)-(i) des Plans - text UND teile. Fall (l) prüft für dieselben
    // HTML-Stücke, dass _blocktextAlsText() unverändert bleibt.
    var FORMAT_FAELLE = [
        {
            name: '(a) strong mitten im Wort',
            html: '<p>Es entsteht eine <strong>trans</strong>-Bindung</p>',
            soll: [{ text: 'Es entsteht eine trans-Bindung', teile: [{ t: 'Es entsteht eine ' }, { t: 'trans', f: 1 }, { t: '-Bindung' }] }]
        },
        {
            name: '(b) em, strong+em',
            html: '<p><em>kursiv</em> und <strong><em>beides</em></strong></p>',
            soll: [{ text: 'kursiv und beides', teile: [{ t: 'kursiv', k: 1 }, { t: ' und ' }, { t: 'beides', f: 1, k: 1 }] }]
        },
        {
            name: '(c) eigene Farbe als #rrggbb',
            html: '<p>Ein <span style="color:#71230a">Begriff</span>.</p>',
            soll: [{ text: 'Ein Begriff.', teile: [{ t: 'Ein ' }, { t: 'Begriff', farbe: '#71230a' }, { t: '.' }] }]
        },
        {
            // Grundfarbe = erstes .cbd-container-content (#123456), nicht der
            // (schwarze) Blockbereich: Titel in Grundfarbe ohne farbe,
            // Schwarz im Inhalt mit farbe; Absatz ohne Formatierung ohne teile
            name: '(d) Farbe = Grundfarbe -> kein farbe, ohne Formatierung kein teile',
            html: '<h3 class="cbd-block-title" style="color:#123456">Titel</h3>' +
                '<div class="cbd-container-content" style="color:#123456">' +
                '<p>Ganz <span style="color:#123456">ohne</span> Formatierung</p>' +
                '<p>Mit <span style="color:rgb(0, 0, 0)">Schwarz</span></p></div>',
            soll: [
                { text: 'Titel', teile: [{ t: 'Titel', f: 1 }] },
                { text: 'Ganz ohne Formatierung' },
                { text: 'Mit Schwarz', teile: [{ t: 'Mit ' }, { t: 'Schwarz', farbe: '#000000' }] }
            ]
        },
        {
            name: '(e) Leerraum über Teilgrenzen - erstes Leerzeichen behält seine Formatierung',
            html: '<p>a <strong> b </strong> c</p>',
            soll: [{ text: 'a b c', teile: [{ t: 'a ' }, { t: 'b ', f: 1 }, { t: 'c' }] }]
        },
        {
            // \n aus BR ist Klartext, Leerzeichen um \n entfallen
            name: '(e2) BR als Klartext, Leerzeichen um den Zeilenwechsel entfallen',
            html: '<p><strong>a </strong><br> <em>b</em></p>',
            soll: [{ text: 'a\nb', teile: [{ t: 'a', f: 1 }, { t: '\n' }, { t: 'b', k: 1 }] }]
        },
        {
            name: '(f) Listenpräfix immer Klartext',
            html: '<ul><li><strong>x</strong></li></ul><ol start="3"><li style="font-weight:700;color:#ff0000">y</li></ol>',
            soll: [
                { text: '• x', teile: [{ t: '• ' }, { t: 'x', f: 1 }] },
                { text: '3. y', teile: [{ t: '3. ' }, { t: 'y', f: 1, farbe: '#ff0000' }] }
            ]
        },
        {
            name: '(g) Überschrift fett über den Standard-Stil',
            html: '<h3>Titel</h3>',
            soll: [{ text: 'Titel', teile: [{ t: 'Titel', f: 1 }] }]
        },
        {
            name: '(h) Formel hat den Stil des Formel-Elements',
            html: '<p>Wasser: <strong><span class="cbd-latex-formula cbd-latex-inline" data-latex="H_2O">' +
                '<span class="katex"><span class="katex-html" aria-hidden="true">H2O</span></span></span></strong></p>' +
                '<div style="font-style:italic"><span class="cbd-latex-formula cbd-latex-display" data-latex="x^2" style="display:block">x2</span></div>',
            soll: [
                { text: 'Wasser: ' + M.latexZuText('H_2O'), teile: [{ t: 'Wasser: ' }, { t: M.latexZuText('H_2O'), f: 1 }] },
                { text: M.latexZuText('x^2'), teile: [{ t: M.latexZuText('x^2'), k: 1 }] }
            ]
        },
        {
            name: '(i) Tabellenzeile - " | " ist Klartext',
            html: '<table><tr><td><strong>A</strong></td><td>B</td></tr><tr><th>C</th><th>D</th></tr></table>',
            soll: [
                { text: 'A | B', teile: [{ t: 'A', f: 1 }, { t: ' | B' }] },
                { text: 'C | D', teile: [{ t: 'C', f: 1 }, { t: ' | ' }, { t: 'D', f: 1 }] }
            ]
        }
    ];

    // Nachtrag (Mutationsprobe): Klartext muss auch INNERHALB einer
    // Formatierung Klartext bleiben - in (e2) und (i) ist das Elternelement
    // des BR bzw. der Zelle unformatiert, dort fiele ein Fehler nicht auf.
    // BR in <strong>: "\n" ohne f; Absätze in einer fetten Tabellenzelle:
    // das trennende Leerzeichen ohne f.
    function formatKlartextInFormatierung() {
        var f = [];
        var br = teileFall('<p><strong>a<br>b</strong></p>',
            [{ text: 'a\nb', teile: [{ t: 'a', f: 1 }, { t: '\n' }, { t: 'b', f: 1 }] }])();
        if (br !== true) f.push('BR in strong: ' + br.detail);
        var zelle = teileFall('<table><tr><td style="font-weight:700"><p>x</p><p>y</p></td></tr></table>',
            [{ text: 'x y', teile: [{ t: 'x', f: 1 }, { t: ' ' }, { t: 'y', f: 1 }] }])();
        if (zelle !== true) f.push('Absätze in fetter Zelle: ' + zelle.detail);
        return ergebnis(f);
    }

    // (d, zweiter Teil): ohne .cbd-container-content gilt die Farbe der
    // übergebenen Wurzel als Grundfarbe
    function formatGrundfarbeWurzel() {
        oeffnenMit('<div id="fmt-wurzel" style="color:#336699"><p>a <span style="color:#336699">b</span> ' +
            '<span style="color:#000000">c</span></p></div>');
        var w = bereich().querySelector('#fmt-wurzel');
        if (!w) return { ok: false, detail: 'Wurzel fehlt' };
        return teileVergleich(B._blocktextLesen(w), [{ text: 'a b c', teile: [{ t: 'a b ' }, { t: 'c', farbe: '#000000' }] }]);
    }

    // (j) Option grundfarbe ersetzt die berechnete Grundfarbe
    function formatOptionGrundfarbe() {
        var html = '<p style="color:#ff0000">rot <span style="color:#000000">schwarz</span></p>';
        var f = [];
        var mit = teileFall(html, [{ text: 'rot schwarz', teile: [{ t: 'rot ' }, { t: 'schwarz', farbe: '#000000' }] }],
            { grundfarbe: '#ff0000' })();
        if (mit !== true) f.push('mit {grundfarbe:#ff0000}: ' + mit.detail);
        var ohne = teileFall(html, [{ text: 'rot schwarz', teile: [{ t: 'rot ', farbe: '#ff0000' }, { t: 'schwarz' }] }])();
        if (ohne !== true) f.push('ohne Option: ' + ohne.detail);
        return ergebnis(f);
    }

    // (k) Option nbsp: geschütztes Leerzeichen wie ein Leerzeichen
    // (zusammengefasst, am Rand entfernt); ohne Option bleibt es wie bisher
    function formatOptionNbsp() {
        var NB = String.fromCharCode(160);
        var html = '<p>a&nbsp;b&nbsp; c</p><p>&nbsp;x&nbsp;</p><p>a&nbsp;<strong>&nbsp;b</strong></p>';
        var f = [];
        var mit = teileFall(html, [
            { text: 'a b c' },
            { text: 'x' },
            { text: 'a b', teile: [{ t: 'a ' }, { t: 'b', f: 1 }] }
        ], { nbsp: true })();
        if (mit !== true) f.push('mit {nbsp:true}: ' + mit.detail);
        var ohne = teileFall(html, [
            { text: 'a' + NB + 'b' + NB + ' c' },
            { text: NB + 'x' + NB },
            { text: 'a' + NB + NB + 'b', teile: [{ t: 'a' + NB }, { t: NB + 'b', f: 1 }] }
        ])();
        if (ohne !== true) f.push('ohne Option: ' + ohne.detail);
        return ergebnis(f);
    }

    // (l) _blocktextAlsText() liefert für (a)-(i) denselben Text wie vor
    // AP-2.1 (Regressionswächter, schon vor der Umsetzung grün)
    function formatAlsTextUnveraendert() {
        var f = [];
        FORMAT_FAELLE.forEach(function(fall) {
            oeffnenMit(fall.html);
            var soll = fall.soll.map(function(a) { return a.text; }).join('\n\n');
            var v = vergleich(B._blocktextAlsText(), soll);
            if (v !== true) f.push(fall.name + ': ' + v.detail);
        });
        return ergebnis(f);
    }

    // Regel F6: fett ab font-weight 600 (Glossarbegriffe), 500 nicht;
    // oblique zählt als kursiv; Unterstreichen wird nicht übernommen
    function formatRegelF6() {
        return teileFall('<p><span style="font-weight:600">a</span><span style="font-weight:500">b</span>' +
            '<span style="font-style:oblique 10deg">c</span><b>d</b>' +
            '<span class="glossar-term" style="font-weight:600;color:#71230a;text-decoration:underline">Begriff</span></p>',
        [{ text: 'abcdBegriff', teile: [{ t: 'a', f: 1 }, { t: 'b' }, { t: 'c', k: 1 }, { t: 'd', f: 1 }, { t: 'Begriff', f: 1, farbe: '#71230a' }] }])();
    }

    // Befund B10 (AP-1.rev): getComputedStyle liefert für color-mix()
    // color(srgb …) bzw. oklab(…). srgb wird zu #rrggbb, nicht Darstellbares
    // gibt keine eigene Farbe; durchsichtig (Alpha 0) zählt nicht,
    // halbdurchsichtig zählt mit der deckenden Farbe.
    function formatB10Farbformen() {
        var html = '<p>a <span id="fmt-srgb" style="color:color-mix(in srgb, #ff0000 50%, #0000ff)">m</span> ' +
            '<span id="fmt-oklab" style="color:color-mix(in oklab, #ff0000 50%, #0000ff)">o</span> ' +
            '<span style="color:rgba(255, 0, 0, 0)">t</span> <span style="color:rgba(0, 0, 255, 0.5)">h</span></p>';
        oeffnenMit(html);
        var f = [];
        var srgb = getComputedStyle(bereich().querySelector('#fmt-srgb')).color;
        var oklab = getComputedStyle(bereich().querySelector('#fmt-oklab')).color;
        if (srgb.indexOf('color(srgb') !== 0) f.push('Vorbedingung: Browser liefert ' + srgb);
        if (oklab.indexOf('oklab(') !== 0) f.push('Vorbedingung: Browser liefert ' + oklab);
        var v = teileVergleich(B._blocktextLesen(bereich()), [{ text: 'a m o t h', teile: [
            { t: 'a ' }, { t: 'm', farbe: '#800080' }, { t: ' o t ' }, { t: 'h', farbe: '#0000ff' }
        ] }]);
        if (v !== true) f.push(v.detail);
        return ergebnis(f);
    }

    // _blocktextAbsaetze(): Absätze des Blockbereichs als [{text, teile?}]
    // (ohne art), Texte wie _blocktextAlsText(); ohne Tafel []
    function formatAbsaetze() {
        if (typeof B._blocktextAbsaetze !== 'function') return { ok: false, detail: '_blocktextAbsaetze fehlt' };
        var f = [];
        if (B.overlay) B.destroy();
        var leer = B._blocktextAbsaetze();
        if (JSON.stringify(leer) !== '[]') f.push('ohne Tafel ' + JSON.stringify(leer));
        oeffnenMit('<h3 class="cbd-block-title">Titel</h3><p>Es <strong>x</strong></p><ul><li>y</li></ul>');
        var l = B._blocktextAbsaetze();
        var soll = [
            { text: 'Titel', teile: [{ t: 'Titel', f: 1 }] },
            { text: 'Es x', teile: [{ t: 'Es ' }, { t: 'x', f: 1 }] },
            { text: '• y' }
        ];
        if (JSON.stringify(l) !== JSON.stringify(soll)) f.push('ist ' + JSON.stringify(l));
        var t = (l || []).map(function(a) { return a.text; }).join('\n\n');
        if (t !== B._blocktextAlsText()) f.push('Text weicht von _blocktextAlsText() ab: ' + sichtbar(t));
        return ergebnis(f);
    }

    // Asynchron: Teile aus dem Testmaterial - Verkettung aller t = text,
    // jedes teile besteht die Prüfung des Elementmodells unverändert
    // (pruefeModell), und mindestens ein Absatz ist formatiert (Überschriften,
    // strong, th im Testmaterial)
    function formatTestmaterial() {
        var E = window.CBDBoardElemente;
        var f = [];
        var formatiert = 0;
        var kette = Promise.resolve();
        FIXTURES.forEach(function(name) {
            kette = kette.then(function() {
                return holen('fixtures/blocktext/' + name + '.html');
            }).then(function(html) {
                oeffnenMit(html);
                B._blocktextLesen(bereich()).forEach(function(a, i) {
                    if (!Object.prototype.hasOwnProperty.call(a, 'teile')) return;
                    formatiert++;
                    var wo = name + ' Absatz ' + i;
                    if (!Array.isArray(a.teile) || !a.teile.length) {
                        f.push(wo + ': teile ' + JSON.stringify(a.teile));
                        return;
                    }
                    var kett = a.teile.map(function(t) { return t.t; }).join('');
                    if (kett !== a.text) f.push(wo + ': Verkettung ' + sichtbar(kett.slice(0, 60)) + ' statt ' + sichtbar(a.text.slice(0, 60)));
                    var p = E.pruefeModell({ v: 1, breite: 1000, hoehe: 1000, elemente: [
                        { id: 'e1', typ: 'text', farbe: '#000000', breite: 2, text: a.text, x: 0, y: 0, teile: a.teile }
                    ] });
                    var el2 = p && p.modell.elemente[0];
                    if (!el2 || JSON.stringify(el2.teile) !== JSON.stringify(a.teile)) f.push(wo + ': Prüfung des Modells verwirft/ändert teile');
                });
            });
        });
        return kette.then(function() {
            if (!formatiert) f.push('kein Absatz des Testmaterials mit teile');
            return ergebnis(f);
        });
    }

    // ------------------------------------------------------------------
    // Formatiert einfügen (PLAN-Blocktext-Formatierung.md, AP-2.3, F7/F14):
    // blocktextEinfuegen(Array von Absätzen [{text, teile?}]) legt Text-
    // Elemente mit teile an; Teilfarben werden über lesbareLauffarbe
    // lesbar gemacht, gemessen wird über _textMass() (fett ist breiter),
    // der Rest behält die Formatierung (blocktextAufNeueSeite).
    // ------------------------------------------------------------------

    // Relative Leuchtdichte und Kontrast nach WCAG 2 - eigene Rechnung,
    // unabhängig vom Modul
    function leuchtWert(hex) {
        function lin(i) {
            var v = parseInt(hex.substr(i, 2), 16) / 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * lin(1) + 0.7152 * lin(3) + 0.0722 * lin(5);
    }

    function kontrastWert(a, b) {
        var la = leuchtWert(a);
        var lb = leuchtWert(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    // Das Elementmodell übersteht die Prüfung des Elementmodells unverändert
    // (nichts verworfen, kein Feld, auch nicht teile, geändert) - Text der
    // Abweichung oder ''
    function modellUnveraendert() {
        var p = window.CBDBoardElemente.pruefeModell(JSON.parse(JSON.stringify(B.modell)));
        if (!p) return 'pruefeModell lieferte null';
        if (p.verworfen) return p.verworfen + ' Element(e) verworfen';
        var ist = JSON.stringify(p.modell.elemente);
        var soll = JSON.stringify(B.modell.elemente);
        return ist === soll ? '' : 'Modell verändert: ' + ist.slice(0, 200) + ' statt ' + soll.slice(0, 200);
    }

    function kette(teile) {
        return teile.map(function(t) { return t.t; }).join('');
    }

    function fmtEinElement() {
        ctx.oeffnen();
        var f = [];
        var teile = [{ t: 'Es ist ' }, { t: 'fett', f: 1 }, { t: '.' }];
        var erg = B.blocktextEinfuegen([{ text: 'Es ist fett.', teile: teile }]);
        var t = texte();
        if (!erg || erg.eingefuegt !== 1 || erg.keinPlatz || erg.rest.length) f.push('Ergebnis ' + JSON.stringify(erg));
        if (t.length !== 1) return { ok: false, detail: t.length + ' Texte, Ergebnis ' + JSON.stringify(erg) };
        if (t[0].text !== 'Es ist fett.') f.push('Text ' + JSON.stringify(t[0].text));
        if (JSON.stringify(t[0].teile) !== JSON.stringify(teile)) f.push('teile ' + JSON.stringify(t[0].teile));
        if (Object.keys(t[0]).join() !== 'id,typ,farbe,breite,text,x,y,teile') f.push('Schlüssel ' + Object.keys(t[0]).join());
        var m = modellUnveraendert();
        if (m) f.push(m);
        return ergebnis(f);
    }

    function fmtZeichenketteWieBisher() {
        ctx.oeffnen();
        var f = [];
        B.blocktextEinfuegen('Eins\n\nZwei');
        texte().forEach(function(t) {
            if (Object.keys(t).join() !== 'id,typ,farbe,breite,text,x,y') f.push('Schlüssel ' + Object.keys(t).join());
        });
        ctx.oeffnen();
        var erg = B.blocktextEinfuegen(absaetze(60));
        if (!erg || !erg.rest.length) f.push('kein Rest');
        else if (!erg.rest.every(function(r) { return typeof r === 'string'; })) f.push('Rest nicht nur Zeichenketten: ' + JSON.stringify(erg.rest[0]));
        return ergebnis(f);
    }

    function fmtTeilfarbeLesbar() {
        ctx.oeffnen();
        var f = [];
        var tafel = '#1a472a';
        B.setBoardColor(tafel);
        B.setColor('#000000');
        var teile = [{ t: 'Dunkelrot', farbe: '#71230a' }, { t: ' und ' }, { t: 'blau', farbe: '#0000ff' }];
        B.blocktextEinfuegen([{ text: 'Dunkelrot und blau', teile: teile }]);
        var t = texte();
        if (t.length !== 1 || !t[0].teile || t[0].teile.length !== 3) return { ok: false, detail: 'teile ' + JSON.stringify(t[0] && t[0].teile) };
        var a = t[0].teile[0].farbe;
        var c = t[0].teile[2].farbe;
        if (!a || kontrastWert(a, tafel) < 3) f.push('Dunkelrot ' + a + ' hat Kontrast ' + (a && kontrastWert(a, tafel).toFixed(2)));
        if (!c || kontrastWert(c, tafel) < 3) f.push('Blau ' + c + ' hat Kontrast ' + (c && kontrastWert(c, tafel).toFixed(2)));
        if (a && a !== M.lesbareLauffarbe('#71230a', tafel)) f.push('Dunkelrot ' + a + ' statt ' + M.lesbareLauffarbe('#71230a', tafel));
        if (a) {
            var r = parseInt(a.substr(1, 2), 16), g = parseInt(a.substr(3, 2), 16), b = parseInt(a.substr(5, 2), 16);
            if (!(r > g && r > b)) f.push('Farbton verloren: ' + a);
        }
        if (t[0].teile[1].farbe !== undefined) f.push('Teil ohne Farbe bekam ' + t[0].teile[1].farbe);
        if (t[0].farbe !== '#ffffff') f.push('Grundfarbe ' + t[0].farbe);
        // Idempotent: das Ergebnis noch einmal eingefügt ändert die Farben nicht
        B.blocktextEinfuegen([{ text: t[0].text, teile: t[0].teile }]);
        t = texte();
        if (t.length !== 2 || JSON.stringify(t[1].teile) !== JSON.stringify(t[0].teile)) f.push('zweites Einfügen: ' + JSON.stringify(t[1] && t[1].teile));
        // Auf weißer Tafel bleibt Dunkelrot, wie es ist
        B.setBoardColor('#ffffff');
        B.blocktextEinfuegen([{ text: 'Dunkelrot', teile: [{ t: 'Dunkelrot', farbe: '#71230a' }] }]);
        t = texte();
        if (t.length !== 3 || !t[2].teile || t[2].teile[0].farbe !== '#71230a') f.push('weiße Tafel: ' + JSON.stringify(t[2] && t[2].teile));
        var m = modellUnveraendert();
        if (m) f.push(m);
        return ergebnis(f);
    }

    // Unlesbare Farbangaben (rgb() passt auf FARB_MUSTER, ist aber keine
    // #rgb/#rrggbb-Angabe für lesbareLauffarbe): farbe entfällt, danach
    // werden gleiche Nachbarn zusammengelegt, ohne Formatierung kein teile
    function fmtFarbeUngueltig() {
        ctx.oeffnen();
        var f = [];
        B.blocktextEinfuegen([{ text: 'x y', teile: [{ t: 'x ', farbe: 'rgb(10, 20, 30)' }, { t: 'y', f: 1 }] }]);
        B.blocktextEinfuegen([{ text: 'nur Farbe', teile: [{ t: 'nur Farbe', farbe: 'rgb(1, 2, 3)' }] }]);
        B.blocktextEinfuegen([{ text: 'ab', teile: [{ t: 'a', f: 1 }, { t: 'b', f: 1, farbe: 'rgb(1, 2, 3)' }] }]);
        var t = texte();
        if (t.length !== 3) return { ok: false, detail: t.length + ' Texte' };
        if (JSON.stringify(t[0].teile) !== JSON.stringify([{ t: 'x ' }, { t: 'y', f: 1 }])) f.push('(1) ' + JSON.stringify(t[0].teile));
        if (Object.prototype.hasOwnProperty.call(t[1], 'teile')) f.push('(2) teile ' + JSON.stringify(t[1].teile));
        if (JSON.stringify(t[2].teile) !== JSON.stringify([{ t: 'ab', f: 1 }])) f.push('(3) ' + JSON.stringify(t[2].teile));
        var m = modellUnveraendert();
        if (m) f.push(m);
        return ergebnis(f);
    }

    // 60 Absätze mit je einem fetten Wort: Seite 1 und Rest tragen teile,
    // der Rest geht als Objekte auf Seite 2, dort ebenfalls mit teile
    function fmtLangUndNeueSeite() {
        ctx.oeffnen();
        var f = [];
        var liste = [];
        for (var i = 1; i <= 60; i++) {
            liste.push({
                text: 'Absatz ' + i + ' mit etwas Text, damit er eine Zeile füllt.',
                teile: [{ t: 'Absatz ' + i + ' mit etwas ' }, { t: 'Text', f: 1 }, { t: ', damit er eine Zeile füllt.' }]
            });
        }
        var erg = B.blocktextEinfuegen(liste);
        var t1 = texte();
        if (!erg || erg.px !== 18 || !erg.rest.length) return { ok: false, detail: 'Ergebnis ohne Rest: ' + JSON.stringify(erg && { px: erg.px, rest: erg.rest.length }) };
        if (t1.length + erg.rest.length !== 60) f.push('Absätze: ' + t1.length + ' + ' + erg.rest.length);
        t1.forEach(function(t) {
            if (!t.teile || kette(t.teile) !== t.text) f.push('Seite 1 ' + t.id + ': teile ' + JSON.stringify(t.teile));
        });
        erg.rest.forEach(function(r, i) {
            if (typeof r !== 'object' || !r.teile || kette(r.teile) !== r.text) f.push('Rest ' + i + ': ' + JSON.stringify(r));
        });
        var m1 = modellUnveraendert();
        if (m1) f.push('Seite 1: ' + m1);
        var erg2 = B.blocktextAufNeueSeite(erg.rest);
        if (B.currentPageIndex !== 1 || B.totalPages !== 2) f.push('Seite ' + B.currentPageIndex + ' von ' + B.totalPages);
        var t2 = texte();
        if (!erg2 || !erg2.eingefuegt || t2.length !== erg2.eingefuegt) f.push('Seite 2: ' + t2.length + ' Texte, Ergebnis ' + JSON.stringify(erg2 && erg2.eingefuegt));
        if (t2.length && t2[0].text !== erg.rest[0].text) f.push('Seite 2 beginnt mit ' + JSON.stringify(t2[0].text.slice(0, 40)));
        t2.forEach(function(t) {
            if (!t.teile || kette(t.teile) !== t.text) f.push('Seite 2 ' + t.id + ': teile ' + JSON.stringify(t.teile));
        });
        var m2 = modellUnveraendert();
        if (m2) f.push('Seite 2: ' + m2);
        return ergebnis(f);
    }

    // Fett misst breiter: Ein Text, der unformatiert in eine Zeile passt,
    // bricht als fetter Text um - das geht nur, wenn _blocktextVerteilen
    // mit der Schrift der Teile misst
    function fmtMisstFett() {
        ctx.oeffnen();
        var f = [];
        var mass = B._textMass();
        var s = B._sichtbarerBereich();
        var breite = s.b - 40;
        // Wörter aus einem Buchstaben, dessen fette Form breiter ist ('W'
        // ist es in Arial nicht): so wächst der Text in kleinen Schritten,
        // und es gibt eine Länge, die nur fett nicht mehr in die Zeile passt
        var text = '';
        for (var n = 1; n < 400 && !text; n++) {
            var cand = [];
            for (var i = 0; i < n; i++) cand.push('b');
            cand = cand.join(' ');
            if (mass(cand, 24, { f: 1, k: 0 }) > breite) {
                if (mass(cand, 24, { f: 0, k: 0 }) <= breite) text = cand;
                else break;
            }
        }
        if (!text) return { ok: false, detail: 'kein Text, der nur fett umbricht (Breite ' + breite + ')' };
        B.blocktextEinfuegen([{ text: text }]);
        B.blocktextEinfuegen([{ text: text, teile: [{ t: text, f: 1 }] }]);
        var t = texte();
        if (t.length !== 2) return { ok: false, detail: t.length + ' Texte' };
        if (t[0].text.indexOf('\n') >= 0) f.push('unformatiert umbrochen: ' + sichtbar(t[0].text.slice(0, 40)));
        if (t[1].text.indexOf('\n') < 0) f.push('fett nicht umbrochen (Breite ' + Math.round(breite) + ', fett ' + Math.round(mass(text, 24, { f: 1, k: 0 })) + ')');
        var flaeche = { x: s.x + 20, y: s.y + 20, b: s.b - 40, h: s.h - 40 };
        if (!innerhalb(B._rahmen(t[1]), flaeche)) f.push('fetter Text außerhalb: ' + rahmenText(B._rahmen(t[1])) + ' von ' + rahmenText(flaeche));
        return ergebnis(f);
    }

    // _blocktextVerteilen misst über _textMass() - die Stellvertreter-Probe
    // (wie in test-board-format.js) sieht jeden Aufruf mit seinem Stil.
    // Direkt aufgerufen, damit nur das Verteilen misst (das Einfügen
    // misst danach noch über _rahmen, ebenfalls über _textMass())
    function fmtMisstUeberTextMass() {
        ctx.oeffnen();
        var f = [];
        var original = B._textMass;
        var echt = original.call(B);
        var aufrufe = [];
        var erg;
        B._textMass = function() {
            return function(t, px, stil) {
                aufrufe.push({ t: t, px: px, f: stil && stil.f ? 1 : 0, k: stil && stil.k ? 1 : 0 });
                return echt(t, px, stil);
            };
        };
        try {
            erg = B._blocktextVerteilen([{ text: 'Eins zwei drei', teile: [{ t: 'Eins ' }, { t: 'zwei', f: 1 }, { t: ' drei', k: 1 }] }], []);
        } finally {
            B._textMass = original;
        }
        if (!erg || erg.bloecke.length !== 1) f.push('Verteilen lieferte ' + JSON.stringify(erg));
        if (!aufrufe.length) return { ok: false, detail: 'keine Messung über _textMass()' };
        if (!aufrufe.some(function(a) { return a.f === 1 && a.t === 'zwei'; })) f.push('fett nicht über _textMass() gemessen: ' + JSON.stringify(aufrufe.slice(0, 4)));
        if (!aufrufe.some(function(a) { return a.k === 1 && a.t === ' drei'; })) f.push('kursiv nicht über _textMass() gemessen');
        if (!aufrufe.some(function(a) { return a.px === 24; })) f.push('keine Messung bei 24 px');
        if (B._textMass !== original) f.push('_textMass nicht wiederhergestellt');
        return ergebnis(f);
    }

    function fmtUndoUndAuswahl() {
        ctx.oeffnen();
        var f = [];
        var undo = B.undoStapel.length;
        var erg = B.blocktextEinfuegen([
            { text: 'Erster Absatz', teile: [{ t: 'Erster ', f: 1 }, { t: 'Absatz' }] },
            { text: 'Zweiter Absatz' }
        ]);
        var t = texte();
        if (t.length !== 2) return { ok: false, detail: t.length + ' Texte' };
        if (B.undoStapel.length !== undo + 1) f.push('Undo ' + undo + ' -> ' + B.undoStapel.length);
        if (B.currentTool !== 'lasso') f.push('Werkzeug ' + B.currentTool);
        if (B.auswahl.join() !== [t[0].id, t[1].id].join()) f.push('Auswahl ' + B.auswahl.join());
        if (!erg || erg.ids.join() !== B.auswahl.join()) f.push('ids ' + JSON.stringify(erg && erg.ids));
        if (!t[0].teile || Object.prototype.hasOwnProperty.call(t[1], 'teile')) f.push('teile: ' + JSON.stringify([t[0].teile, t[1].teile]));
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true, cancelable: true }));
        if (texte().length) f.push(texte().length + ' Texte nach Strg+Z');
        return ergebnis(f);
    }

    // Einträge ohne Text zählen nicht; Zeichenketten im Array gelten als Absatz
    function fmtArrayRandfaelle() {
        ctx.oeffnen();
        var f = [];
        var undo = B.undoStapel.length;
        [[], [{ text: '' }], [{ text: '   ' }, { teile: [{ t: 'x', f: 1 }] }, null, 7, '', ' '], undefined, null].forEach(function(eingabe, i) {
            var erg = B.blocktextEinfuegen(eingabe);
            if (!erg || erg.eingefuegt !== 0 || erg.keinPlatz || erg.rest.length) f.push('leer ' + i + ': ' + JSON.stringify(erg));
        });
        if (el().length || B.undoStapel.length !== undo) f.push('geändert');
        if (B.currentTool !== 'pen') f.push('Werkzeug ' + B.currentTool);
        var erg2 = B.blocktextEinfuegen([{ text: '' }, null, 7, { text: 'Echt', teile: [{ t: 'Ec', f: 1 }, { t: 'ht' }] }, '', 'Zweiter']);
        var t = texte();
        if (!erg2 || erg2.eingefuegt !== 2 || t.length !== 2) return { ok: false, detail: t.length + ' Texte, Ergebnis ' + JSON.stringify(erg2) };
        if (t[0].text !== 'Echt' || t[1].text !== 'Zweiter') f.push('Texte ' + JSON.stringify([t[0].text, t[1].text]));
        if (!t[0].teile || Object.prototype.hasOwnProperty.call(t[1], 'teile')) f.push('teile ' + JSON.stringify([t[0].teile, t[1].teile]));
        return ergebnis(f);
    }

    // Kein Platz: nichts geändert, der Rest kommt als Objekte mit teile zurück
    function fmtKeinPlatz() {
        ctx.oeffnen();
        strich(10, 5, 10, B.cssHeight - 5);
        var vorher = el().slice();
        var undo = B.undoStapel.length;
        var a = { text: 'passt nicht', teile: [{ t: 'passt ', f: 1 }, { t: 'nicht' }] };
        var b = { text: 'auch nicht' };
        var erg = B.blocktextEinfuegen([a, b]);
        var f = [];
        if (!erg || !erg.keinPlatz || erg.eingefuegt !== 0) f.push('Ergebnis ' + JSON.stringify(erg));
        if (erg && JSON.stringify(erg.rest) !== JSON.stringify([a, b])) f.push('Rest ' + JSON.stringify(erg.rest));
        if (el().length !== vorher.length || el()[0] !== vorher[0] || B.undoStapel.length !== undo) f.push('Tafel verändert');
        return ergebnis(f);
    }

    // Review-Befund G5 mit Objekten: Passt auf einer leeren Seite nichts hin,
    // entsteht keine Seite; sonst Zeichenketten und Objekte gemischt
    function fmtNeueSeiteProbe() {
        oeffnenMit('<p>Eins</p>');
        var f = [];
        var a = { text: 'Ein Absatz', teile: [{ t: 'Ein ', f: 1 }, { t: 'Absatz' }] };
        B.zoom = 20;
        B.updateCanvasTransform();
        var erg = B.blocktextAufNeueSeite([a]);
        if (!erg || !erg.keinPlatz || erg.eingefuegt !== 0) f.push('Ergebnis ' + JSON.stringify(erg));
        if (B.totalPages !== 1) f.push(B.totalPages + ' Seiten (leere Seite angelegt)');
        if (erg && JSON.stringify(erg.rest) !== JSON.stringify([a])) f.push('Rest ' + JSON.stringify(erg.rest));
        B.zoom = 1;
        B.updateCanvasTransform();
        var erg2 = B.blocktextAufNeueSeite(['Alpha', { text: 'Beta fett', teile: [{ t: 'Beta ' }, { t: 'fett', f: 1 }] }]);
        var t = texte();
        if (!erg2 || erg2.eingefuegt !== 2 || t.length !== 2) return { ok: false, detail: t.length + ' Texte, Ergebnis ' + JSON.stringify(erg2) };
        if (B.totalPages !== 2) f.push(B.totalPages + ' Seiten');
        if (t[0].text !== 'Alpha' || Object.prototype.hasOwnProperty.call(t[0], 'teile')) f.push('erster ' + JSON.stringify(t[0]));
        if (t[1].text !== 'Beta fett' || JSON.stringify(t[1].teile) !== JSON.stringify([{ t: 'Beta ' }, { t: 'fett', f: 1 }])) f.push('zweiter ' + JSON.stringify(t[1].teile));
        return ergebnis(f);
    }

    // MAX_TEILE: Wechselt die Formatierung in jedem Zeichen, hat der Block
    // mehr als 1000 Teile - die Prüfung des Modells würde sie beim Speichern
    // verwerfen. Der Text bleibt vollständig, das Feld teile entfällt gleich
    // beim Einfügen (Modell danach unverändert durch die Prüfung)
    function fmtUeberMaxTeile() {
        ctx.oeffnen();
        var f = [];
        var text = '';
        var teile = [];
        for (var i = 0; i < 1200; i++) {
            var z = (i % 2) ? 'b' : 'a';
            text += z;
            teile.push(i % 2 ? { t: z } : { t: z, f: 1 });
        }
        var erg = B.blocktextEinfuegen([{ text: text, teile: teile }]);
        var t = texte();
        if (!erg || t.length !== 1) return { ok: false, detail: t.length + ' Texte, Ergebnis ' + JSON.stringify(erg && { e: erg.eingefuegt, rest: erg.rest.length }) };
        if (t[0].text.split('\n').join('') !== text) f.push('Text verändert (' + t[0].text.length + ' Zeichen)');
        if (t[0].teile && t[0].teile.length > window.CBDBoardElemente.MAX_TEILE) f.push(t[0].teile.length + ' Teile (MAX_TEILE ' + window.CBDBoardElemente.MAX_TEILE + ')');
        var m = modellUnveraendert();
        if (m) f.push(m);
        return ergebnis(f);
    }

    // Absicherung: Passen die Teile eines Blocks nicht zu seinem Text
    // (Verkettung !== text) - das Modul liefert das nie, die Prüfung des
    // Modells würde sie aber verwerfen -, bekommt das Element keine teile;
    // der Text bleibt. Das Modul wird dafür kurz durch eine Stellvertreter-
    // Fassung von verteile() ersetzt.
    function fmtVerkettungAbgesichert() {
        ctx.oeffnen();
        var f = [];
        var original = M.verteile;
        M.verteile = function() {
            return { px: 24, rest: [], bloecke: [{ text: 'abc', teile: [{ t: 'x', f: 1 }], x: 20, y: 60, b: 50, h: 29 }] };
        };
        var erg;
        try {
            erg = B.blocktextEinfuegen([{ text: 'abc' }]);
        } finally {
            M.verteile = original;
        }
        var t = texte();
        if (!erg || t.length !== 1) return { ok: false, detail: t.length + ' Texte, Ergebnis ' + JSON.stringify(erg) };
        if (t[0].text !== 'abc') f.push('Text ' + JSON.stringify(t[0].text));
        if (Object.prototype.hasOwnProperty.call(t[0], 'teile')) f.push('teile ' + JSON.stringify(t[0].teile));
        if (M.verteile !== original) f.push('verteile nicht wiederhergestellt');
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // Bearbeitungsfeld (PLAN-Blocktext-Formatierung.md, AP-2.4, F8-F10):
    // Die Vorschau ist ein formatiert bearbeitbares div mit Formatleiste
    // (Fett, Kursiv, sechs Stiftfarben, Standard). Gelesen wird es mit
    // B._textFeldLesen() (= _blocktextLesen mit Grundfarbe des Felds).
    // Prüfnamen beginnen mit "Bearbeitungsfeld".
    // ------------------------------------------------------------------
    var FARBNAMEN = ['Schwarz', 'Weiß', 'Rot', 'Blau', 'Gelb', 'Grün'];

    function formatLeiste() {
        return document.querySelector('.cbd-board-blocktext-format');
    }

    function formatKnopf(selektor) {
        var l = formatLeiste();
        return l ? l.querySelector(selektor) : null;
    }

    function farbKnopf(name) {
        return formatKnopf('.cbd-board-format-farbe[aria-label="' + name + '"]');
    }

    // '#rrggbb' -> 'rgb(r, g, b)' wie getComputedStyle
    function rgbVon(hex) {
        return 'rgb(' + parseInt(hex.substr(1, 2), 16) + ', ' + parseInt(hex.substr(3, 2), 16) + ', ' +
            parseInt(hex.substr(5, 2), 16) + ')';
    }

    // Erster Textknoten im Feld t, der wort enthält -> {knoten, start}
    function textStelle(t, wort) {
        var w = document.createTreeWalker(t, NodeFilter.SHOW_TEXT, null, false);
        for (var n = w.nextNode(); n; n = w.nextNode()) {
            var i = n.nodeValue.indexOf(wort);
            if (i >= 0) return { knoten: n, start: i };
        }
        return null;
    }

    // Markierung per Range auf wort setzen (wie ein Doppelklick); ende:
    // eingeklappt direkt hinter wort (Schreibmarke)
    function markiere(t, wort, ende) {
        var s = textStelle(t, wort);
        if (!s) throw new Error('Wort ' + JSON.stringify(wort) + ' nicht im Feld');
        t.focus();
        var r = document.createRange();
        r.setStart(s.knoten, ende ? s.start + wort.length : s.start);
        r.setEnd(s.knoten, s.start + wort.length);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
    }

    // Berechneter Stil des Elements, in dem wort steht
    function stilVon(t, wort) {
        var s = textStelle(t, wort);
        return s ? getComputedStyle(s.knoten.parentNode) : null;
    }

    function lesenAls(t) {
        return JSON.stringify(textUndTeile(B._textFeldLesen(t)));
    }

    function bfFeldAufbau() {
        oeffnenMit('<p>Eins</p>');
        var t = vorschauOeffnen();
        var f = [];
        if (!t) return { ok: false, detail: 'kein Feld' };
        if (t.tagName !== 'DIV' || t.getAttribute('contenteditable') !== 'true') f.push('Feld ist ' + t.tagName + ' contenteditable=' + t.getAttribute('contenteditable'));
        if (t.getAttribute('role') !== 'textbox' || t.getAttribute('aria-multiline') !== 'true' || !t.getAttribute('aria-label')) f.push('role/aria-multiline/aria-label fehlen');
        if (t.getAttribute('spellcheck') !== 'false' || t.getAttribute('autocorrect') !== 'off' || t.getAttribute('autocapitalize') !== 'off') f.push('spellcheck/autocorrect/autocapitalize');
        if (fenster().querySelector('textarea')) f.push('noch ein textarea im Fenster');
        var st = getComputedStyle(t);
        if (st.backgroundColor !== rgbVon(B.boardColor)) f.push('Hintergrund ' + st.backgroundColor + ' statt Tafel ' + B.boardColor);
        var grund = M.lesbareFarbe(B.currentColor, B.boardColor);
        if (st.color !== rgbVon(grund)) f.push('Schrift ' + st.color + ' statt Einfügefarbe ' + grund);
        var l = formatLeiste();
        if (!l || l.getAttribute('role') !== 'toolbar') return { ok: false, detail: f.concat(['keine Leiste mit role toolbar']).join('; ') };
        if (!fenster().contains(l)) f.push('Leiste nicht im Fenster');
        if (l.getBoundingClientRect().bottom > t.getBoundingClientRect().top + 0.5) f.push('Leiste nicht über dem Feld');
        var knoepfe = l.querySelectorAll('button');
        var namen = Array.prototype.map.call(knoepfe, function(k) { return k.getAttribute('aria-label') || k.textContent; });
        var soll = ['Fett', 'Kursiv'].concat(FARBNAMEN, ['Standard']);
        if (namen.join('|') !== soll.join('|')) f.push('Knöpfe ' + namen.join('|'));
        var farben = Array.prototype.map.call(l.querySelectorAll('.cbd-board-format-farbe'), function(k) { return getComputedStyle(k).backgroundColor; });
        if (farben.join('|') !== B.presetColors.map(rgbVon).join('|')) f.push('Farbknöpfe ' + farben.join('|'));
        Array.prototype.forEach.call(knoepfe, function(k) {
            var r = k.getBoundingClientRect();
            if (r.width < 36 || r.height < 36) f.push((k.getAttribute('aria-label') || k.textContent) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height));
            if (k.type !== 'button') f.push('type ' + k.type);
        });
        return ergebnis(f);
    }

    function bfSpansFormatiert() {
        oeffnenMit('<p>Ein <strong>fettes</strong> und <em>kursives</em> Wort, <span style="color:#0000ff">blau</span> und normal.</p>');
        var t = vorschauOeffnen();
        var f = [];
        if (!t || t.tagName !== 'DIV') return { ok: false, detail: 'kein div-Feld' };
        var fett = stilVon(t, 'fettes'), kursiv = stilVon(t, 'kursives'), blau = stilVon(t, 'blau'), normal = stilVon(t, 'und normal');
        if (!fett || !(parseFloat(fett.fontWeight) >= 600)) f.push('fettes: ' + (fett && fett.fontWeight));
        if (!kursiv || kursiv.fontStyle !== 'italic') f.push('kursives: ' + (kursiv && kursiv.fontStyle));
        if (!blau || blau.color !== 'rgb(0, 0, 255)') f.push('blau: ' + (blau && blau.color));
        if (!normal || parseFloat(normal.fontWeight) >= 600 || normal.fontStyle !== 'normal' || normal.color !== getComputedStyle(t).color) {
            f.push('normal: ' + (normal && [normal.fontWeight, normal.fontStyle, normal.color].join(' ')));
        }
        if (kursiv && parseFloat(kursiv.fontWeight) >= 600) f.push('kursives auch fett');
        return ergebnis(f);
    }

    // Grüne Tafel: Feld grün, Grundschrift weiß, Glossarfarbe aufgehellt
    // (lesbareLauffarbe, F7) - so, wie es auf die Tafel kommt
    function bfGrueneTafel() {
        oeffnenMit('<p>Ein <span style="color:#71230a">Begriff</span> im Text.</p>');
        B.setBoardColor('#1a472a');
        B.setColor('#000000');
        var t = vorschauOeffnen();
        var f = [];
        if (!t || t.tagName !== 'DIV') return { ok: false, detail: 'kein div-Feld' };
        var st = getComputedStyle(t);
        if (st.backgroundColor !== 'rgb(26, 71, 42)') f.push('Hintergrund ' + st.backgroundColor);
        if (st.color !== 'rgb(255, 255, 255)') f.push('Grundschrift ' + st.color);
        var soll = M.lesbareLauffarbe('#71230a', '#1a472a');
        var b = stilVon(t, 'Begriff');
        if (!b || b.color !== rgbVon(soll)) f.push('Begriff ' + (b && b.color) + ' statt ' + rgbVon(soll));
        if (kontrastWert(soll, '#1a472a') < 3) f.push('Kontrast ' + kontrastWert(soll, '#1a472a'));
        // Zurückgelesen: die angezeigte (lesbare) Farbe, Grundschrift ohne Farbe
        var soll2 = JSON.stringify([{ text: 'Ein Begriff im Text.', teile: [{ t: 'Ein ' }, { t: 'Begriff', farbe: soll }, { t: ' im Text.' }] }]);
        if (lesenAls(t) !== soll2) f.push('gelesen ' + lesenAls(t));
        return ergebnis(f);
    }

    // Unverändertes Feld = dieselben Absätze wie der Blockbereich (F10)
    function bfUnveraendertGleich() {
        oeffnenMit('<h3>Titel</h3>' +
            '<p>Es entsteht eine <strong>trans</strong>-Bindung mit <em>kursivem</em> und <span style="color:#0000ff">blauem</span> Text.</p>' +
            '<ul><li><strong>x</strong> y</li></ul>' +
            '<p>Zeile 1<br>Zeile <strong>2<br>fett</strong> weiter</p>' +
            '<p>Ganz normal.</p>' +
            '<table><tr><td><strong>A</strong></td><td>B</td></tr></table>');
        var soll = B._blocktextAbsaetze();
        var t = vorschauOeffnen();
        if (!t || t.tagName !== 'DIV') return { ok: false, detail: 'kein div-Feld' };
        var ist = B._textFeldLesen(t);
        var a = JSON.stringify(textUndTeile(ist));
        var b = JSON.stringify(textUndTeile(soll));
        if (soll.length !== 6 || !soll[1].teile) return { ok: false, detail: 'Vorbedingung: ' + b };
        return a === b ? true : { ok: false, detail: 'Feld ' + a + ' / Block ' + b };
    }

    function bfFettKursivKnopf() {
        oeffnenMit('<p>Eins zwei drei</p>');
        var t = vorschauOeffnen();
        if (!t || !formatKnopf('.cbd-board-format-fett')) return { ok: false, detail: 'kein Feld/keine Leiste' };
        markiere(t, 'zwei');
        formatKnopf('.cbd-board-format-fett').click();
        markiere(t, 'drei');
        formatKnopf('.cbd-board-format-kursiv').click();
        var soll = JSON.stringify([{ text: 'Eins zwei drei', teile: [{ t: 'Eins ' }, { t: 'zwei', f: 1 }, { t: ' ' }, { t: 'drei', k: 1 }] }]);
        return lesenAls(t) === soll ? true : { ok: false, detail: 'gelesen ' + lesenAls(t) };
    }

    function bfFarbknopfStandard() {
        oeffnenMit('<p>Eins zwei drei</p><p>Ein <span style="color:#0000ff">blaues</span> Wort</p>');
        var t = vorschauOeffnen();
        if (!t || !farbKnopf('Rot')) return { ok: false, detail: 'kein Feld/kein Knopf Rot' };
        var f = [];
        markiere(t, 'zwei');
        farbKnopf('Rot').click();
        var ist = B._textFeldLesen(t);
        var soll1 = JSON.stringify([{ t: 'Eins ' }, { t: 'zwei', farbe: '#ff0000' }, { t: ' drei' }]);
        if (JSON.stringify(ist[0] && ist[0].teile) !== soll1) f.push('Rot: ' + JSON.stringify(ist[0]));
        markiere(t, 'zwei');
        formatKnopf('.cbd-board-format-standard').click();
        markiere(t, 'blaues');
        formatKnopf('.cbd-board-format-standard').click();
        var soll2 = JSON.stringify([{ text: 'Eins zwei drei' }, { text: 'Ein blaues Wort' }]);
        if (lesenAls(t) !== soll2) f.push('Standard: ' + lesenAls(t));
        return ergebnis(f);
    }

    function bfPasteKlartext() {
        oeffnenMit('<p>Eins</p>');
        var t = vorschauOeffnen();
        if (!t || t.tagName !== 'DIV') return { ok: false, detail: 'kein div-Feld' };
        markiere(t, 'Eins', true);
        var dt = new DataTransfer();
        dt.setData('text/html', '<b class="cbd-fremd">Fremd</b><img class="cbd-fremd" src="data:,">');
        dt.setData('text/plain', ' Fremd');
        var ev = new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true });
        t.dispatchEvent(ev);
        var f = [];
        if (!ev.defaultPrevented) f.push('paste nicht abgefangen');
        if (t.querySelector('.cbd-fremd, b, img')) f.push('Markup eingefügt: ' + t.innerHTML.slice(0, 120));
        if (lesenAls(t) !== JSON.stringify([{ text: 'Eins Fremd' }])) f.push('gelesen ' + lesenAls(t));
        return ergebnis(f);
    }

    function bfStrgU() {
        oeffnenMit('<p>Eins</p>');
        var t = vorschauOeffnen();
        var f = [];
        if (!taste(t, 'u', { ctrlKey: true }).defaultPrevented) f.push('Strg+U nicht verhindert');
        if (!taste(t, 'u', { metaKey: true }).defaultPrevented) f.push('Cmd+U nicht verhindert');
        if (taste(t, 'b', { ctrlKey: true }).defaultPrevented) f.push('Strg+B verhindert (bleibt Browser-Standard)');
        if (!fenster() || !B.overlay) f.push('Fenster oder Tafel geschlossen');
        return ergebnis(f);
    }

    function bfZiehenGesperrt() {
        oeffnenMit('<p>Eins</p>');
        var t = vorschauOeffnen();
        var f = [];
        ['dragover', 'drop'].forEach(function(typ) {
            var dt = new DataTransfer();
            dt.setData('text/plain', 'x');
            var ev = new DragEvent(typ, { bubbles: true, cancelable: true, dataTransfer: dt });
            t.dispatchEvent(ev);
            if (!ev.defaultPrevented) f.push(typ + ' nicht verhindert');
        });
        return ergebnis(f);
    }

    // Formatknöpfe nehmen dem Feld weder Fokus noch Markierung
    function bfKnoepfeHaltenMarkierung() {
        oeffnenMit('<p>Eins</p>');
        vorschauOeffnen();
        var l = formatLeiste();
        if (!l) return { ok: false, detail: 'keine Leiste' };
        var f = [];
        Array.prototype.forEach.call(l.querySelectorAll('button'), function(k) {
            var p = new PointerEvent('pointerdown', { bubbles: true, cancelable: true, pointerType: 'mouse', isPrimary: true });
            k.dispatchEvent(p);
            var m = new MouseEvent('mousedown', { bubbles: true, cancelable: true });
            k.dispatchEvent(m);
            if (!p.defaultPrevented || !m.defaultPrevented) f.push((k.getAttribute('aria-label') || k.textContent) + ': pointerdown ' + p.defaultPrevented + ', mousedown ' + m.defaultPrevented);
        });
        return ergebnis(f);
    }

    // Ohne Markierung im Feld tun die Knöpfe nichts (Schreibmarke im Feld
    // oder Markierung außerhalb des Felds)
    function bfOhneMarkierung() {
        oeffnenMit('<p>Eins zwei</p>');
        var t = vorschauOeffnen();
        if (!t || !formatLeiste()) return { ok: false, detail: 'kein Feld/keine Leiste' };
        var f = [];
        var vorher = t.innerHTML;
        function alleKnoepfe() {
            Array.prototype.forEach.call(formatLeiste().querySelectorAll('button'), function(k) { k.click(); });
        }
        markiere(t, 'Eins', true);
        alleKnoepfe();
        if (t.innerHTML !== vorher) f.push('mit Schreibmarke geändert: ' + t.innerHTML.slice(0, 120));
        var p = fenster().querySelector('p');
        var r = document.createRange();
        r.selectNodeContents(p);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(r);
        alleKnoepfe();
        if (t.innerHTML !== vorher) f.push('mit Markierung außerhalb geändert: ' + t.innerHTML.slice(0, 120));
        return ergebnis(f);
    }

    function bfAufDieTafel() {
        oeffnenMit('<p>Es entsteht eine <strong>trans</strong>-Bindung</p><p>Ohne alles</p>');
        var t = vorschauOeffnen();
        if (!t || !farbKnopf('Rot')) return { ok: false, detail: 'kein Feld/kein Knopf Rot' };
        markiere(t, 'Ohne');
        farbKnopf('Rot').click();
        fenster().querySelector('.cbd-board-blocktext-einfuegen').click();
        var f = [];
        if (fenster()) f.push('Fenster noch offen');
        var tx = texte();
        if (tx.length !== 2) return { ok: false, detail: tx.length + ' Texte' };
        if (JSON.stringify(tx[0].teile) !== JSON.stringify([{ t: 'Es entsteht eine ' }, { t: 'trans', f: 1 }, { t: '-Bindung' }])) f.push('erster ' + JSON.stringify(tx[0].teile));
        if (JSON.stringify(tx[1].teile) !== JSON.stringify([{ t: 'Ohne', farbe: '#ff0000' }, { t: ' alles' }])) f.push('zweiter ' + JSON.stringify(tx[1].teile));
        if (B.currentTool !== 'lasso' || B.auswahl.length !== 2) f.push('Werkzeug ' + B.currentTool + ', Auswahl ' + B.auswahl.length);
        var m = modellUnveraendert();
        if (m) f.push(m);
        return ergebnis(f);
    }

    // Zeilenwechsel im Feld (Umschalt+Enter = insertLineBreak) bleibt beim
    // Lesen ein Zeilenwechsel - Chrome/Safari fügen bei white-space:
    // pre-wrap dafür ein "\n"-Zeichen statt eines <br> ein
    function bfZeilenwechsel() {
        oeffnenMit('<p>Eins zwei</p><p>x<br>y</p>');
        var t = vorschauOeffnen();
        if (!t || t.tagName !== 'DIV') return { ok: false, detail: 'kein div-Feld' };
        markiere(t, 'Eins', true);
        document.execCommand('insertLineBreak');
        var soll = JSON.stringify([{ text: 'Eins\nzwei' }, { text: 'x\ny' }]);
        return lesenAls(t) === soll ? true : { ok: false, detail: 'gelesen ' + lesenAls(t) + ', Feld ' + JSON.stringify(t.innerHTML) };
    }

    // Enter: Vorschau = neuer Absatz (Standard des Browsers), Modus
    // "bearbeiten" (Phase 3, F12) = Zeilenwechsel; dort alle Absätze in
    // einem div, Zeilen durch <br> getrennt
    function bfEnterUndModus() {
        oeffnenMit('<p>Eins</p>');
        var t = vorschauOeffnen();
        var f = [];
        if (taste(t, 'Enter').defaultPrevented) f.push('Vorschau: Enter verhindert');
        var teile = B._textFeldBauen([{ text: 'Zeile eins\nZeile zwei', teile: [{ t: 'Zeile ' }, { t: 'eins\nZeile', f: 1 }, { t: ' zwei' }] }],
            { modus: 'bearbeiten', grundfarbe: '#0000ff', hintergrund: '#ffffff' });
        var b = teile.feld;
        document.body.appendChild(b);
        try {
            if (b.children.length !== 1 || b.children[0].tagName !== 'DIV' || b.querySelectorAll('br').length !== 1) f.push('Bearbeiten-Aufbau ' + JSON.stringify(b.innerHTML));
            if (getComputedStyle(b).color !== 'rgb(0, 0, 255)') f.push('Grundschrift ' + getComputedStyle(b).color);
            var soll = JSON.stringify([{ text: 'Zeile eins\nZeile zwei', teile: [{ t: 'Zeile ' }, { t: 'eins', f: 1 }, { t: '\n' }, { t: 'Zeile', f: 1 }, { t: ' zwei' }] }]);
            if (lesenAls(b) !== soll) f.push('Bearbeiten gelesen ' + lesenAls(b));
            markiere(b, ' zw', true); // Schreibmarke mitten in "zwei"
            var ev = taste(b, 'Enter');
            if (!ev.defaultPrevented) f.push('Bearbeiten: Enter nicht verhindert');
            if (b.children.length !== 1) f.push('Bearbeiten: neues div nach Enter');
            var gelesen = B._textFeldLesen(b);
            if (gelesen.length !== 1 || gelesen[0].text !== 'Zeile eins\nZeile zw\nei') f.push('Bearbeiten nach Enter ' + JSON.stringify(gelesen));
            if (taste(b, 'Enter', { shiftKey: true }).defaultPrevented) f.push('Bearbeiten: Umschalt+Enter verhindert');
        } finally {
            if (b.parentNode) b.parentNode.removeChild(b);
        }
        return ergebnis(f);
    }

    // Leiste als ein Tab-Halt (role toolbar), Pfeiltasten wandern
    function bfLeisteTastatur() {
        oeffnenMit('<p>Eins</p>');
        vorschauOeffnen();
        var l = formatLeiste();
        if (!l) return { ok: false, detail: 'keine Leiste' };
        var knoepfe = l.querySelectorAll('button');
        var f = [];
        var halte = Array.prototype.filter.call(knoepfe, function(k) { return k.tabIndex === 0; });
        if (halte.length !== 1 || halte[0] !== knoepfe[0]) f.push(halte.length + ' Tab-Halte in der Leiste');
        knoepfe[0].focus();
        taste(knoepfe[0], 'ArrowRight');
        if (document.activeElement !== knoepfe[1] || knoepfe[1].tabIndex !== 0 || knoepfe[0].tabIndex !== -1) f.push('Pfeil rechts');
        taste(knoepfe[1], 'End');
        if (document.activeElement !== knoepfe[knoepfe.length - 1]) f.push('Ende');
        taste(document.activeElement, 'ArrowRight');
        if (document.activeElement !== knoepfe[0]) f.push('Pfeil rechts am Ende kreist nicht');
        taste(document.activeElement, 'ArrowLeft');
        if (document.activeElement !== knoepfe[knoepfe.length - 1]) f.push('Pfeil links am Anfang kreist nicht');
        return ergebnis(f);
    }

    // Inhalt nur als Text: Zeichen wie "<" aus dem Block bleiben Text
    function bfNurText() {
        oeffnenMit('<p>&lt;b class="cbd-fremd"&gt;x&lt;/b&gt; &amp; <strong>&lt;i class="cbd-fremd"&gt;</strong></p>');
        var t = vorschauOeffnen();
        if (!t || t.tagName !== 'DIV') return { ok: false, detail: 'kein div-Feld' };
        var f = [];
        if (t.querySelector('.cbd-fremd, b, i')) f.push('Markup erzeugt: ' + t.innerHTML.slice(0, 120));
        var soll = JSON.stringify([{ text: '<b class="cbd-fremd">x</b> & <i class="cbd-fremd">', teile: [{ t: '<b class="cbd-fremd">x</b> & ' }, { t: '<i class="cbd-fremd">', f: 1 }] }]);
        if (lesenAls(t) !== soll) f.push('gelesen ' + lesenAls(t));
        return ergebnis(f);
    }

    // Asynchron (selectionchange kommt als eigene Aufgabe): Geht die
    // Markierung beim Antippen eines Knopfs verloren (iPad), gilt die
    // zuletzt im Feld gemerkte
    function bfGemerkteMarkierung() {
        oeffnenMit('<p>Eins zwei drei</p>');
        var t = vorschauOeffnen();
        if (!t || !formatKnopf('.cbd-board-format-fett')) return Promise.resolve({ ok: false, detail: 'kein Feld/keine Leiste' });
        markiere(t, 'zwei');
        return warte(50).then(function() {
            window.getSelection().removeAllRanges();
            return warte(50);
        }).then(function() {
            formatKnopf('.cbd-board-format-fett').click();
            var soll = JSON.stringify([{ text: 'Eins zwei drei', teile: [{ t: 'Eins ' }, { t: 'zwei', f: 1 }, { t: ' drei' }] }]);
            return lesenAls(t) === soll ? true : { ok: false, detail: 'gelesen ' + lesenAls(t) };
        });
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
        p('Blocktext einfügen: zwei Absätze auf leerer Tafel (24 px, x 20, 1 Undo, Lasso, Auswahl)', zweiAbsaetzeLeer);
        p('Blocktext einfügen: unter vorhandenem Strich (+20 px)', unterStrich);
        p('Blocktext einfügen: langer Text - 18 px, Rest, alles innerhalb der Fläche, ≤ 5000 Zeichen', langerText);
        p('Blocktext einfügen: Rest auf neuer Seite (Seite 2, eigener Undo-Schritt)', restAufNeueSeite);
        p('Blocktext einfügen: grüne Tafel + schwarzer Stift -> weiß, sonst Stiftfarbe', grueneTafel);
        p('Blocktext einfügen: Zoom 2 und Verschiebung - im sichtbaren Ausschnitt', zoomUndVerschiebung);
        p('Blocktext einfügen: Tafel voll -> keinPlatz, nichts geändert, kein Undo', tafelVoll);
        p('Blocktext einfügen: leerer Text -> nichts, leerer Rest -> keine Seite', leererText);
        p('Blocktext einfügen: Strg+Z nimmt alles Eingefügte zurück', strgZ);
        p('Blocktext einfügen: Texte und Schrift überleben Speichern und Wiederöffnen', speichernWiederoeffnen);

        // AP-2.3
        p('Blocktext-Knopf: erstes Kind des Blockbereichs, klebt beim Scrollen, 13 px auch bei 300 %', leisteKlebt);
        p('Blocktext-Vorschau: Klick öffnet Fenster mit Blocktext, Fokus im Textfeld am Anfang', vorschauKlick);
        p('Blocktext-Vorschau: Escape im Textfeld schließt nur das Fenster', escapeImFeld);
        p('Blocktext-Vorschau: Strg+Z im Textfeld wirkt nicht auf die Tafel', strgZImFeld);
        p('Blocktext-Vorschau: Entf/Rücktaste/Strg+D im Textfeld lassen die Auswahl in Ruhe', entfImFeld);
        p('Blocktext-Vorschau: gekürzter Text -> genau diese Absätze', gekuerztEinfuegen);
        p('Blocktext-Rückfrage: langer Text -> Rückfrage, neue Seite, erneute Rückfrage, Weglassen', restNeueSeite);
        p('Blocktext-Rückfrage: Fokus auf "Auf neuer Seite einfügen", Escape = Weglassen', restWeglassen);
        p('Blocktext-Rückfrage: volle Seite -> "kein Platz", neue Seite fügt ein', keinPlatzRueckfrage);
        p('Blocktext-Vorschau: Block ohne Text -> Hinweis und nur "Schließen"', leererBlock);
        p('Blocktext-Vorschau: destroy() entfernt das Fenster, neues Öffnen ohne Fenster', destroyMitFenster);
        p('Blocktext-Vorschau: Klick neben das Fenster schließt nicht, Escape außerhalb schließt die Tafel nicht', klickNebenFenster);

        // AP-2.fix1
        p('AP-2.fix1 (G5): neue Seite nur, wenn auf einer leeren Seite Platz ist', g5KeineLeereSeite);
        p('AP-2.fix1 (G1): Tab kreist im Fenster, Fokus danach zurück auf den Knopf', g1Fokus);
        p('AP-2.fix1 (G6): geschlossenes <details> nur mit Überschrift', g6Details);
        p('AP-2.fix1 (M1): gehaltenes Enter in der Rückfrage wirkt nicht', gehalteneTaste);

        // Folge-AP G9
        p('G9: Auswahlleiste verdeckt den eingefügten langen Text nicht', g9LangerText);
        p('G9: auch nach A+ liegt die Leiste nicht auf dem Text', g9NachAPlus);
        p('G9: Zoom 2 und Verschiebung - Leiste frei, Text im Ausschnitt', g9Zoom);
        p('G9: kein Platz für die Leiste -> trotzdem eingefügt, wie bisher bei y 20', g9KeinPlatzFuerLeiste);

        // Formatierung lesen (PLAN-Blocktext-Formatierung.md, AP-2.1)
        FORMAT_FAELLE.forEach(function(fall) {
            p('Formatierung lesen ' + fall.name, teileFall(fall.html, fall.soll));
        });
        p('Formatierung lesen (d) ohne .cbd-container-content: Grundfarbe = Farbe der Wurzel', formatGrundfarbeWurzel);
        p('Formatierung lesen (j) Option grundfarbe', formatOptionGrundfarbe);
        p('Formatierung lesen (k) Option nbsp', formatOptionNbsp);
        p('Formatierung lesen (l) _blocktextAlsText() für (a)-(i) unverändert', formatAlsTextUnveraendert);
        p('Formatierung lesen: Regel F6 (Stärke 600 ja, 500 nein, oblique, Glossarbegriff)', formatRegelF6);
        p('Formatierung lesen (B10): color(srgb …) -> #rrggbb, oklab(…) und Alpha 0 ohne Farbe', formatB10Farbformen);
        p('Formatierung lesen: _blocktextAbsaetze() als [{text, teile?}], ohne Tafel []', formatAbsaetze);
        p('Formatierung lesen: BR und Blockgrenzen-Leerzeichen bleiben auch in Fett Klartext', formatKlartextInFormatierung);

        // Formatiert einfügen (PLAN-Blocktext-Formatierung.md, AP-2.3)
        ctx = c;
        p('Formatiert einfügen: Array mit teile -> ein Element mit diesen teile, besteht die Modellprüfung', fmtEinElement);
        p('Formatiert einfügen: Zeichenketten-Aufruf unverändert (kein teile, Rest = Zeichenketten)', fmtZeichenketteWieBisher);
        p('Formatiert einfügen: grüne Tafel - Teilfarbe Kontrast >= 3, Farbton bleibt, idempotent', fmtTeilfarbeLesbar);
        p('Formatiert einfügen: unlesbare Teilfarbe entfällt, Nachbarn zusammengelegt, ohne Formatierung kein teile', fmtFarbeUngueltig);
        p('Formatiert einfügen: 60 formatierte Absätze - Rest mit teile, Seite 2 mit teile', fmtLangUndNeueSeite);
        p('Formatiert einfügen: fetter Text bricht um, unformatierter nicht (Messung mit Stil)', fmtMisstFett);
        p('Formatiert einfügen: _blocktextVerteilen misst über _textMass() mit fett/kursiv', fmtMisstUeberTextMass);
        p('Formatiert einfügen: ein Undo-Schritt, Lasso, Auswahl, Strg+Z', fmtUndoUndAuswahl);
        p('Formatiert einfügen: Array-Randfälle (leere Einträge, Zeichenkette im Array, kein Array)', fmtArrayRandfaelle);
        p('Formatiert einfügen: kein Platz -> nichts geändert, Rest als Objekte mit teile', fmtKeinPlatz);
        p('Formatiert einfügen: blocktextAufNeueSeite mit Objekten (Probe G5, gemischter Rest)', fmtNeueSeiteProbe);
        p('Formatiert einfügen: über MAX_TEILE bleibt der Text, die Formatierung entfällt, Modell unverändert', fmtUeberMaxTeile);
        p('Formatiert einfügen: Teile, die nicht zum Text passen, werden nicht gesetzt (Absicherung)', fmtVerkettungAbgesichert);

        // Bearbeitungsfeld (PLAN-Blocktext-Formatierung.md, AP-2.4)
        p('Bearbeitungsfeld: div[contenteditable] in Tafelfarben, Formatleiste über dem Feld (Fett, Kursiv, sechs Farben, Standard, 36 px)', bfFeldAufbau);
        p('Bearbeitungsfeld: Vorschau zeigt fett, kursiv und farbig (berechnete Stile)', bfSpansFormatiert);
        p('Bearbeitungsfeld: grüne Tafel - Feld grün, Grundschrift weiß, Glossarfarbe aufgehellt', bfGrueneTafel);
        p('Bearbeitungsfeld: _textFeldLesen liefert für unverändertes Feld dieselben Absätze wie _blocktextAbsaetze()', bfUnveraendertGleich);
        p('Bearbeitungsfeld: Knopf "F" und "K" auf eine Markierung -> f:1 / k:1 beim Lesen', bfFettKursivKnopf);
        p('Bearbeitungsfeld: Farbknopf Rot -> farbe #ff0000, "Standard" entfernt die Farbe', bfFarbknopfStandard);
        p('Bearbeitungsfeld: Einfügen aus der Zwischenablage nur als Klartext', bfPasteKlartext);
        p('Bearbeitungsfeld: Strg+U/Cmd+U verhindert, Strg+B bleibt', bfStrgU);
        p('Bearbeitungsfeld: Ziehen und Ablegen gesperrt', bfZiehenGesperrt);
        p('Bearbeitungsfeld: Formatknöpfe verhindern pointerdown und mousedown', bfKnoepfeHaltenMarkierung);
        p('Bearbeitungsfeld: ohne Markierung im Feld tun die Knöpfe nichts', bfOhneMarkierung);
        p('Bearbeitungsfeld: "Auf die Tafel" legt Elemente mit teile an', bfAufDieTafel);
        p('Bearbeitungsfeld: Zeilenwechsel im Feld bleibt beim Lesen ein Zeilenwechsel', bfZeilenwechsel);
        p('Bearbeitungsfeld: Enter - Vorschau neuer Absatz, Modus "bearbeiten" Zeilenwechsel in einem div', bfEnterUndModus);
        p('Bearbeitungsfeld: Leiste ein Tab-Halt, Pfeiltasten wandern', bfLeisteTastatur);
        p('Bearbeitungsfeld: Text aus dem Block bleibt Text (kein Markup)', bfNurText);
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
            // AP-2.fix1: Klickschutz und Speichern abwarten brauchen Zeit
            return kette.then(function() {
                ctx = c;
                return c.pruefeAsync('AP-2.fix1 (M1): Klickschutz deckt die Rückfrage ab, verschwindet nach 400 ms', m1KlickschutzRueckfrage);
            }).then(function() {
                return c.pruefeAsync('AP-2.fix1 (G3): Klickschutz nach "Auf die Tafel" ohne Rest, Auswahl bleibt', g3KlickschutzOhneRest);
            }).then(function() {
                return c.pruefeAsync('AP-2.fix1 (M2): "Auf neuer Seite einfügen" wartet, bis gespeichert ist', m2SpeichernAbwarten);
            }).then(function() {
                return c.pruefeAsync('AP-2.fix1 (G2): close() schließt das Fenster sofort', g2CloseSchliesstFenster);
            }).then(function() {
                return c.pruefeAsync('Formatierung lesen: Testmaterial - Verkettung der Teile = Text, Modellprüfung behält teile', formatTestmaterial);
            }).then(function() {
                return c.pruefeAsync('Bearbeitungsfeld: verlorene Markierung (iPad) - Knopf nutzt die zuletzt im Feld gemerkte', bfGemerkteMarkierung);
            });
        });
    };
})();
