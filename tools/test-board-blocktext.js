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
        if (t.value !== 'Eins\n\nZwei') f.push('Inhalt ' + JSON.stringify(t.value));
        if (document.activeElement !== t) f.push('Fokus auf ' + (document.activeElement && document.activeElement.className));
        if (t.selectionStart !== 0 || t.selectionEnd !== 0) f.push('Schreibmarke ' + t.selectionStart + '-' + t.selectionEnd);
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
        var t = vorschauOeffnen();
        t.value = 'Eins\n\nDrei';
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
        var t = vorschauOeffnen();
        t.value = 'gekürzt';
        var fe = fenster();
        fe.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        var f = [];
        if (!fenster() || feld().value !== 'gekürzt') f.push('Klick neben das Fenster schließt es');
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
            });
        });
    };
})();
