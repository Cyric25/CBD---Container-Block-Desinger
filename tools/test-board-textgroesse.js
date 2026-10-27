/*
 * Prüfungen "Textgröße nachträglich ändern" (Rückmeldung 2026-10-27,
 * Plugin 3.1.144): Knöpfe "A−"/"A+" mit Größenanzeige in der Auswahlleiste.
 *
 * Abgedeckt:
 *   - Knöpfe und Anzeige nur, wenn Texte gewählt sind; nie im Drehmodus;
 *     gemischte Größen als Spanne "a–b px"
 *   - "A+"/"A−": Schrift x/÷ 1,2, linke obere Ecke bleibt, Auswahl bleibt,
 *     EIN Undo-Schritt je Klick, Text wird größer gezeichnet, Strg+Z
 *     schrittweise zurück
 *   - Grenzen 18/200 px: Knopf gesperrt, kein Undo-Eintrag
 *   - gemischte Auswahl: Strich bleibt dasselbe Objekt, winkel bleibt
 *   - lokal gespeichert und wieder geöffnet: neue Größe bleibt
 *
 * Wird von tools/test-board-zeiger.html NACH test-board-drehen.js geladen
 * und hängt sich an den Haken window.__zusatzTests an (vorhandene zuerst).
 */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var E = window.CBDBoardElemente;
    var CID = 'test-zeiger';
    var ctx;

    var vorherSync = window.__zusatzTests;

    // ------------------------------------------------------------------
    // Hilfen (wie in test-board-drehen.js)
    // ------------------------------------------------------------------
    function el() {
        return (B.modell && B.modell.elemente) ? B.modell.elemente : [];
    }

    function element(id) {
        var l = el();
        for (var i = 0; i < l.length; i++) if (l[i].id === id) return l[i];
        return null;
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
        return el()[el().length - 1].id;
    }

    function text(x, y, inhalt) {
        B.setTool('text');
        B.setColor('#000000');
        ctx.zeiger('pointerdown', 1, 'mouse', x, y);
        var feld = document.querySelector('.cbd-board-text-input');
        if (!feld) throw new Error('kein Textfeld');
        feld.value = inhalt;
        feld.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
        return el()[el().length - 1].id;
    }

    function lasso(x1, y1, x2, y2) {
        B.setTool('lasso');
        var p = [[x1, y1], [x2, y1], [x2, y2], [x1, y2], [x1, y1 + 2]];
        ctx.zeiger('pointerdown', 7, 'mouse', p[0][0], p[0][1]);
        for (var i = 1; i < p.length; i++) {
            var a = p[i - 1], b = p[i];
            for (var k = 1; k <= 8; k++) {
                ctx.zeiger('pointermove', 7, 'mouse', a[0] + (b[0] - a[0]) * k / 8, a[1] + (b[1] - a[1]) * k / 8);
            }
        }
        ctx.zeiger('pointerup', 7, 'mouse', p[p.length - 1][0], p[p.length - 1][1]);
    }

    /** Lasso großzügig um den Rahmen eines Elements. */
    function lassoUm(id, rand) {
        var r = B._rahmen(element(id));
        rand = rand || 20;
        lasso(r.x - rand, r.y - rand, r.x + r.b + rand, r.y + r.h + rand);
    }

    function leiste() {
        return B.overlay ? B.overlay.querySelector('.cbd-board-auswahl-leiste') : null;
    }

    function sichtbar(k) {
        if (!k) return false;
        var cs = getComputedStyle(k);
        if (cs.display === 'none' || cs.visibility === 'hidden') return false;
        var r = k.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
    }

    function knopf(aktion) {
        var l = leiste();
        return l ? l.querySelector('[data-aktion="' + aktion + '"]') : null;
    }

    function anzeige() {
        var l = leiste();
        var a = l ? l.querySelector('.cbd-board-auswahl-textgroesse') : null;
        return a ? a.textContent : null;
    }

    function px(id) {
        var e = element(id);
        return e ? E.schriftPx(e.breite) : null;
    }

    /** Zahl dunkler Pixel der Zeichenebene im Rechteck (CSS-Koordinaten). */
    function dunkleIm(x1, y1, x2, y2) {
        var dpr = B.dpr || 1;
        var c = B.drawingCanvas;
        var sx = Math.max(0, Math.round(x1 * dpr)), sy = Math.max(0, Math.round(y1 * dpr));
        var w = Math.max(1, Math.round((x2 - x1) * dpr)), h = Math.max(1, Math.round((y2 - y1) * dpr));
        var d = c.getContext('2d').getImageData(sx, sy, w, h).data;
        var n = 0;
        for (var i = 3; i < d.length; i += 4) if (d[i] > 100) n++;
        return n;
    }

    // ------------------------------------------------------------------
    // Prüfungen
    // ------------------------------------------------------------------
    function nurMitText() {
        ctx.oeffnen();
        var f = [];
        strich(100, 100, 200, 100);
        lasso(80, 80, 220, 120);
        if (!B.auswahl.length) return { ok: false, detail: 'Strich nicht gewählt' };
        var gr = knopf('text-groesser'), kl = knopf('text-kleiner');
        if (!gr || !kl) return { ok: false, detail: 'Knöpfe "A+"/"A−" fehlen im Markup' };
        if (sichtbar(gr) || sichtbar(kl) || sichtbar(leiste().querySelector('.cbd-board-auswahl-textgroesse'))) {
            f.push('Textgröße bei reiner Strich-Auswahl sichtbar');
        }
        var t = text(300, 200, 'Hallo');
        lassoUm(t);
        if (B.auswahl.join() !== t) return { ok: false, detail: 'Text nicht gewählt: ' + B.auswahl.join() };
        if (!sichtbar(gr) || !sichtbar(kl)) f.push('"A+"/"A−" bei Text-Auswahl nicht sichtbar');
        if (anzeige() !== px(t) + ' px') f.push('Anzeige "' + anzeige() + '" statt "' + px(t) + ' px"');
        if (gr.textContent !== 'A+' || kl.textContent !== 'A−') f.push('Beschriftung "' + kl.textContent + '"/"' + gr.textContent + '"');
        // gemischte Größen: Spanne
        var t2 = text(300, 300, 'Welt');
        B.modell.elemente = B.modell.elemente.map(function(e) {
            return e.id === t2 ? { id: e.id, typ: 'text', farbe: e.farbe, breite: 1, text: e.text, x: e.x, y: e.y } : e;
        });
        B.zeichneModell();
        lasso(250, 150, 500, 380);
        if (B.auswahl.length !== 2) f.push('zwei Texte nicht gewählt: ' + B.auswahl.join());
        else if (anzeige() !== '20–' + px(t) + ' px') f.push('Spanne "' + anzeige() + '"');
        // Drehmodus: Textgröße weg
        B.auswahlDrehenStarten();
        if (sichtbar(gr) || sichtbar(kl)) f.push('Textgröße im Drehmodus sichtbar');
        B._drehenAbbrechen();
        if (!sichtbar(gr)) f.push('nach Abbrechen "A+" nicht wieder sichtbar');
        return ergebnis(f);
    }

    function groesserKleinerUndUndo() {
        ctx.oeffnen();
        var f = [];
        var t = text(150, 150, 'MMMMMM');
        var e0 = element(t);
        var s0 = px(t);
        var r0 = B._rahmen(e0);
        lassoUm(t);
        if (B.auswahl.join() !== t) return { ok: false, detail: 'Text nicht gewählt' };
        var undoVorher = B.undoStapel.length;
        var rechts0 = dunkleIm(r0.x + r0.b + 4, r0.y, r0.x + r0.b + 60, r0.y + r0.h + 20);
        knopf('text-groesser').click();
        var e1 = element(t);
        var s1 = E.naechsteSchrift(s0, 1);
        if (px(t) !== s1) f.push('Schrift ' + px(t) + ' statt ' + s1 + ' nach "A+"');
        if (e1.x !== e0.x || e1.y !== e0.y) f.push('Ecke bewegt: ' + e1.x + '/' + e1.y);
        if (B.undoStapel.length !== undoVorher + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undoVorher) + ' statt 1');
        if (B.auswahl.join() !== t) f.push('Auswahl nach "A+": ' + B.auswahl.join());
        if (anzeige() !== s1 + ' px') f.push('Anzeige "' + anzeige() + '" nach "A+"');
        var r1 = B._rahmen(e1);
        if (!(r1.b > r0.b && r1.h > r0.h)) f.push('Rahmen nicht gewachsen');
        var ar = B.auswahlRahmen();
        if (!ar || ar.b < r1.b) f.push('Auswahlrahmen folgt nicht');
        // gezeichnet: rechts neben dem alten Rahmen ist jetzt Schrift
        var rechts1 = dunkleIm(r0.x + r0.b + 4, r0.y, r0.x + r0.b + 60, r0.y + r0.h + 20);
        if (rechts0 !== 0) f.push('Testfläche vorher nicht leer (' + rechts0 + ')');
        if (rechts1 < 20) f.push('größere Schrift nicht gezeichnet (' + rechts1 + ' Pixel)');
        knopf('text-groesser').click();
        var s2 = E.naechsteSchrift(s1, 1);
        knopf('text-kleiner').click();
        var s3 = E.naechsteSchrift(s2, -1);
        if (px(t) !== s3) f.push('Schrift ' + px(t) + ' statt ' + s3 + ' nach "A+ A+ A−"');
        if (B.undoStapel.length !== undoVorher + 3) f.push('drei Klicks = ' + (B.undoStapel.length - undoVorher) + ' Undo-Schritte');
        B.undo();
        if (px(t) !== s2) f.push('Strg+Z 1: ' + px(t) + ' statt ' + s2);
        B.undo();
        B.undo();
        if (px(t) !== s0) f.push('Strg+Z 3: ' + px(t) + ' statt ' + s0);
        return ergebnis(f);
    }

    function grenzen() {
        ctx.oeffnen();
        var f = [];
        var t = text(150, 150, 'Ab'); // kurz: passt auch mit 200 px in die Fläche
        B.modell.elemente = B.modell.elemente.map(function(e) {
            return e.id === t ? { id: e.id, typ: 'text', farbe: e.farbe, breite: 46, text: e.text, x: e.x, y: e.y } : e;
        });
        B.zeichneModell();
        lassoUm(t, 10);
        if (B.auswahl.join() !== t) return { ok: false, detail: 'großer Text nicht gewählt' };
        var gr = knopf('text-groesser'), kl = knopf('text-kleiner');
        if (anzeige() !== '200 px') f.push('Anzeige "' + anzeige() + '" bei 200 px');
        if (!gr.disabled) f.push('"A+" bei 200 px nicht gesperrt');
        if (kl.disabled) f.push('"A−" bei 200 px gesperrt');
        var undoVorher = B.undoStapel.length;
        var vorher = element(t);
        gr.click();
        if (B.auswahlTextGroesse(1) !== false) f.push('auswahlTextGroesse(1) an der Grenze nicht false');
        if (element(t) !== vorher || B.undoStapel.length !== undoVorher) f.push('an der oberen Grenze verändert/Undo-Eintrag');
        // untere Grenze
        B.modell.elemente = B.modell.elemente.map(function(e) {
            return e.id === t ? { id: e.id, typ: 'text', farbe: e.farbe, breite: 0.5, text: e.text, x: e.x, y: e.y } : e;
        });
        B.zeichneModell();
        B._auswahlZeichnen();
        if (anzeige() !== '18 px') f.push('Anzeige "' + anzeige() + '" bei 18 px');
        if (!kl.disabled) f.push('"A−" bei 18 px nicht gesperrt');
        if (gr.disabled) f.push('"A+" bei 18 px gesperrt');
        undoVorher = B.undoStapel.length;
        if (B.auswahlTextGroesse(-1) !== false || B.undoStapel.length !== undoVorher) f.push('an der unteren Grenze verändert/Undo-Eintrag');
        gr.click();
        if (px(t) !== 22) f.push('von 18 px mit "A+" auf ' + px(t) + ' statt 22');
        return ergebnis(f);
    }

    function gemischteAuswahl() {
        ctx.oeffnen();
        var f = [];
        var s = strich(100, 120, 200, 120);
        var t = text(130, 160, 'Text');
        B.modell.elemente = B.modell.elemente.map(function(e) {
            return e.id === t ? { id: e.id, typ: 'text', farbe: e.farbe, breite: e.breite, text: e.text, x: e.x, y: e.y, winkel: 30 } : e;
        });
        B.zeichneModell();
        lasso(60, 80, 320, 300);
        if (B.auswahl.length !== 2) return { ok: false, detail: 'Strich und Text nicht gewählt: ' + B.auswahl.join() };
        var strichVorher = element(s);
        var sVorher = px(t);
        B.auswahlTextGroesse(1);
        if (element(s) !== strichVorher) f.push('Strich verändert');
        if (px(t) !== E.naechsteSchrift(sVorher, 1)) f.push('Text nicht vergrößert');
        if (element(t).winkel !== 30) f.push('winkel ' + element(t).winkel);
        if (B.auswahl.length !== 2) f.push('Auswahl verändert: ' + B.auswahl.join());
        return ergebnis(f);
    }

    function speichernUndWiederoeffnen() {
        ctx.oeffnen();
        var f = [];
        var t = text(150, 150, 'bleibt');
        lassoUm(t);
        var soll = E.naechsteSchrift(E.naechsteSchrift(px(t), 1), 1);
        knopf('text-groesser').click();
        knopf('text-groesser').click();
        B.saveDrawing();
        B.destroy();
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        var w = el().filter(function(x) { return x.typ === 'text'; });
        if (w.length !== 1 || E.schriftPx(w[0].breite) !== soll) {
            f.push('nach Wiederöffnen: ' + JSON.stringify(w.map(function(x) { return E.schriftPx(x.breite); })) + ' statt ' + soll);
        }
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // Einhängen (nach den vorhandenen Haken)
    // ------------------------------------------------------------------
    window.__zusatzTests = function(c) {
        if (typeof vorherSync === 'function') vorherSync(c);
        ctx = c;
        var p = c.pruefe;
        p('3.1.144: "A−"/"A+" nur bei gewählten Texten, Spanne bei gemischten Größen, nicht im Drehmodus', nurMitText);
        p('3.1.144: "A+"/"A−" ändern die Schrift (Ecke bleibt), je Klick 1 Undo, Strg+Z zurück', groesserKleinerUndUndo);
        p('3.1.144: Grenzen 18/200 px sperren den Knopf, kein Undo-Eintrag', grenzen);
        p('3.1.144: Strich in der Auswahl bleibt, winkel bleibt', gemischteAuswahl);
        p('3.1.144: neue Textgröße überlebt Speichern und Wiederöffnen', speichernUndWiederoeffnen);
    };
})();
