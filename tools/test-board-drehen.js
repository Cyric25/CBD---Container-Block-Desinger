/*
 * Prüfungen "Lasso: Drehen, Duplizieren" und "Raster: Zeilen, Kästchen"
 * (Rückmeldung 2026-10-27, Plugin 3.1.143).
 *
 * Abgedeckt:
 *   - Auswahlleiste: Knöpfe "Drehen" und "Duplizieren"; im Drehmodus nur
 *     Hinweis + "Abbrechen"
 *   - Drehen wie im CAD: Drehpunkt setzen (folgt dem gehaltenen Zeiger),
 *     Bezugspunkt antippen, ziehen -> Auswahl dreht sich um den Drehpunkt;
 *     Zwischenstand vor dem Abheben; EIN Undo-Schritt; Modus endet, Auswahl
 *     bleibt; Anzeige nur auf der Auswahl-Ebene
 *   - Einrasten (±3° an 45°-Vielfachen), Umschalttaste 15°-Schritte
 *   - 0° ohne Undo-Eintrag; ESC und "Abbrechen" nehmen zurück; Pinch
 *     verwirft die Drehung, Drehpunkt bleibt; Werkzeugwechsel beendet
 *   - gedrehter Text: Pixel an der gedrehten Stelle, Feld winkel, lokal
 *     gespeichert und wieder geöffnet
 *   - Punkt-Radierer trifft ein gedrehtes Bild-Element an der gedrehten
 *     Stelle (nicht an der unverdrehten)
 *   - Duplizieren per Knopf und Strg+D (nicht aus Eingabefeldern): Kopien
 *     +20 px, neue IDs, Kopien gewählt, EIN Undo-Schritt
 *   - Raster: Knopf ein/aus, Auswahlfeld Kästchen/Zeilen/Sechsecke,
 *     Linien an den erwarteten Stellen, Leiste nach Wiederöffnen stimmig
 *
 * Wird von tools/test-board-zeiger.html NACH test-board-standard.js geladen
 * und hängt sich an die Haken window.__zusatzTests /
 * window.__zusatzTestsAsync an (vorhandene zuerst).
 */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var CID = 'test-zeiger';
    var ctx;

    var vorherSync = window.__zusatzTests;
    var vorherAsync = window.__zusatzTestsAsync;

    // ------------------------------------------------------------------
    // Hilfen
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

    function nah(a, b, tol) {
        return Math.abs(a - b) <= (tol === undefined ? 0.01 : tol);
    }

    /** Strich mit der Maus (ein 'pen' schaltete den Stift-Vorrang ein und sperrte die Finger-Prüfungen). */
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
        ctx.zeiger('pointerdown', 1, 'mouse', x, y);
        var feld = document.querySelector('.cbd-board-text-input');
        if (!feld) throw new Error('kein Textfeld');
        feld.value = inhalt;
        feld.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
        return el()[el().length - 1].id;
    }

    /** Rechteck-Lasso (Maus) um x1..x2, y1..y2. */
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

    function tippen(x, y, extra) {
        ctx.zeiger('pointerdown', 8, 'mouse', x, y, extra);
        ctx.zeiger('pointerup', 8, 'mouse', x, y, extra);
    }

    /** Drehpunkt (px,py) setzen, am Bezugspunkt aufsetzen, nach (bx,by) ziehen. */
    function drehen(px, py, ax, ay, bx, by, optionen) {
        optionen = optionen || {};
        if (!B.auswahlDrehenStarten()) throw new Error('Drehmodus startet nicht');
        tippen(px, py);
        ctx.zeiger('pointerdown', 9, 'mouse', ax, ay);
        var n = 6;
        for (var i = 1; i <= n; i++) {
            ctx.zeiger('pointermove', 9, 'mouse', ax + (bx - ax) * i / n, ay + (by - ay) * i / n, { shiftKey: !!optionen.shift });
        }
        if (!optionen.ohneAbheben) ctx.zeiger('pointerup', 9, 'mouse', bx, by);
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

    function taste(key, extra) {
        var init = { key: key, bubbles: true, cancelable: true };
        for (var k in (extra || {})) init[k] = extra[k];
        var ev = new KeyboardEvent('keydown', init);
        document.dispatchEvent(ev);
        return ev;
    }

    /** Alpha des Pixels (CSS-Koordinaten) einer Ebene. */
    function alpha(canvas, x, y) {
        var dpr = B.dpr || 1;
        var d = canvas.getContext('2d').getImageData(Math.round(x * dpr), Math.round(y * dpr), 1, 1).data;
        return d[3];
    }

    /** Größter Alpha-Wert in einem kleinen Quadrat (Kantenglättung). */
    function dunkelUm(canvas, x, y, r) {
        var max = 0;
        for (var dx = -(r || 2); dx <= (r || 2); dx++) {
            for (var dy = -(r || 2); dy <= (r || 2); dy++) {
                max = Math.max(max, alpha(canvas, x + dx, y + dy));
            }
        }
        return max > 100;
    }

    function ebeneLeer(canvas) {
        var d = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
        for (var i = 3; i < d.length; i += 4) if (d[i] > 0) return false;
        return true;
    }

    // ------------------------------------------------------------------
    // Prüfungen (synchron)
    // ------------------------------------------------------------------
    function leisteKnoepfe() {
        ctx.oeffnen();
        var f = [];
        strich(100, 100, 200, 100);
        lasso(80, 80, 220, 120);
        var dreh = knopf('drehen'), dup = knopf('duplizieren'), ab = knopf('drehen-abbrechen');
        if (!dreh || dreh.textContent.trim() !== 'Drehen') f.push('kein Knopf "Drehen"');
        else if (!dreh.querySelector('.dashicons-image-rotate')) f.push('Drehen ohne dashicons-image-rotate');
        if (!dup || dup.textContent.trim() !== 'Duplizieren') f.push('kein Knopf "Duplizieren"');
        else if (!dup.querySelector('.dashicons-admin-page')) f.push('Duplizieren ohne dashicons-admin-page');
        if (!sichtbar(dreh) || !sichtbar(dup)) f.push('Drehen/Duplizieren nicht sichtbar');
        if (sichtbar(ab)) f.push('"Abbrechen" außerhalb des Drehmodus sichtbar');
        dreh.click();
        if (!B._drehen) f.push('Klick auf "Drehen" startet keinen Drehmodus');
        var hinweis = leiste().querySelector('.cbd-board-auswahl-hinweis');
        if (!sichtbar(leiste())) f.push('Leiste im Drehmodus unsichtbar');
        if (!sichtbar(hinweis) || hinweis.textContent !== 'Drehpunkt antippen') f.push('Hinweis "' + (hinweis && hinweis.textContent) + '"');
        if (!sichtbar(ab)) f.push('"Abbrechen" im Drehmodus nicht sichtbar');
        if (sichtbar(dreh) || sichtbar(dup) || sichtbar(knopf('loeschen'))) f.push('normale Knöpfe im Drehmodus sichtbar');
        tippen(150, 200);
        if (hinweis.textContent !== 'Bezugspunkt antippen und ziehen') f.push('Hinweis nach Drehpunkt "' + hinweis.textContent + '"');
        ab.click();
        if (B._drehen) f.push('"Abbrechen" beendet den Drehmodus nicht');
        if (!B.auswahl.length) f.push('"Abbrechen" hebt die Auswahl auf');
        if (!sichtbar(dreh)) f.push('nach Abbrechen "Drehen" nicht wieder sichtbar');
        return ergebnis(f);
    }

    function drehen90() {
        ctx.oeffnen();
        var f = [];
        var a = strich(100, 100, 200, 100);
        var anderer = strich(300, 300, 400, 300);
        lasso(80, 80, 220, 120);
        var undoVorher = B.undoStapel.length;
        var vorher = element(anderer);
        drehen(100, 100, 200, 100, 100, 200);
        var s = element(a);
        if (!s) return { ok: false, detail: 'Strich weg' };
        var p0 = s.punkte[0], pn = s.punkte[s.punkte.length - 1];
        if (!nah(p0[0], 100) || !nah(p0[1], 100) || !nah(pn[0], 100) || !nah(pn[1], 200)) {
            f.push('Strich nicht um 90° gedreht: ' + JSON.stringify([p0, pn]));
        }
        if (element(anderer) !== vorher) f.push('nicht gewähltes Element verändert');
        if (B.undoStapel.length !== undoVorher + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undoVorher) + ' statt 1');
        if (B._drehen) f.push('Drehmodus nach dem Abheben nicht beendet');
        if (B.auswahl.join() !== a) f.push('Auswahl nach Drehen ' + B.auswahl.join());
        if (!dunkelUm(B.drawingCanvas, 100, 180)) f.push('gedrehter Strich nicht an (100,180) gezeichnet');
        if (dunkelUm(B.drawingCanvas, 180, 100, 1)) f.push('alter Strich bei (180,100) noch sichtbar');
        if (!sichtbar(leiste()) || !sichtbar(knopf('drehen'))) f.push('Leiste nach Drehen nicht wieder normal');
        B.undo();
        s = element(a);
        if (!s || !nah(s.punkte[s.punkte.length - 1][0], 200) || !nah(s.punkte[s.punkte.length - 1][1], 100)) f.push('Strg+Z stellt die Lage nicht her');
        return ergebnis(f);
    }

    function drehpunktFolgtUndAnzeige() {
        ctx.oeffnen();
        var f = [];
        strich(100, 100, 200, 100);
        lasso(80, 80, 220, 120);
        var png = B.drawingCanvas.toDataURL();
        B.auswahlDrehenStarten();
        ctx.zeiger('pointerdown', 8, 'mouse', 300, 300);
        ctx.zeiger('pointermove', 8, 'mouse', 320, 310);
        if (!B._drehen || B._drehen.px !== 320 || B._drehen.py !== 310) f.push('Drehpunkt folgt dem gehaltenen Zeiger nicht');
        if (sichtbar(leiste())) f.push('Leiste beim Ziehen des Drehpunkts sichtbar');
        ctx.zeiger('pointermove', 8, 'mouse', 150, 200);
        ctx.zeiger('pointerup', 8, 'mouse', 150, 200);
        if (B._drehen.phase !== 'bezug') f.push('Phase nach Drehpunkt ' + B._drehen.phase);
        if (!dunkelUm(B.auswahlCanvas, 150, 200)) f.push('Drehpunkt nicht auf der Auswahl-Ebene');
        if (dunkelUm(B.drawingCanvas, 150, 200, 3)) f.push('Drehpunkt auf der Zeichenebene');
        if (B.drawingCanvas.toDataURL() !== png) f.push('Zeichenebene durch den Drehmodus verändert');
        B._drehenAbbrechen();
        return ergebnis(f);
    }

    function einrastenUndFein() {
        ctx.oeffnen();
        var f = [];
        var a = strich(100, 100, 200, 100);
        lasso(80, 80, 220, 120);
        // 88° -> rastet auf 90°
        var w = 88 * Math.PI / 180;
        drehen(100, 100, 200, 100, 100 + 100 * Math.cos(w), 100 + 100 * Math.sin(w));
        var pn = element(a).punkte[element(a).punkte.length - 1];
        if (!nah(pn[0], 100) || !nah(pn[1], 200)) f.push('88° nicht auf 90° eingerastet: ' + JSON.stringify(pn));
        B.undo();
        lasso(80, 80, 220, 120);
        // 37° frei -> 37°
        w = 37 * Math.PI / 180;
        drehen(100, 100, 200, 100, 100 + 100 * Math.cos(w), 100 + 100 * Math.sin(w));
        pn = element(a).punkte[element(a).punkte.length - 1];
        if (!nah(pn[0], 100 + 100 * Math.cos(w), 0.01) || !nah(pn[1], 100 + 100 * Math.sin(w), 0.01)) f.push('37° nicht frei übernommen: ' + JSON.stringify(pn));
        B.undo();
        lasso(80, 80, 220, 120);
        // 37° mit Umschalttaste -> 30°
        drehen(100, 100, 200, 100, 100 + 100 * Math.cos(w), 100 + 100 * Math.sin(w), { shift: true });
        pn = element(a).punkte[element(a).punkte.length - 1];
        var w30 = 30 * Math.PI / 180;
        if (!nah(pn[0], 100 + 100 * Math.cos(w30)) || !nah(pn[1], 100 + 100 * Math.sin(w30))) f.push('Umschalt + 37° nicht auf 30°: ' + JSON.stringify(pn));
        // Gegen den Uhrzeigersinn: -60°
        B.undo();
        lasso(80, 80, 220, 120);
        w = -60 * Math.PI / 180;
        drehen(100, 100, 200, 100, 100 + 50 * Math.cos(w), 100 + 50 * Math.sin(w));
        pn = element(a).punkte[element(a).punkte.length - 1];
        if (!nah(pn[0], 100 + 100 * Math.cos(w)) || !nah(pn[1], 100 + 100 * Math.sin(w))) f.push('-60° falsch: ' + JSON.stringify(pn));
        return ergebnis(f);
    }

    function nullGradUndEsc() {
        ctx.oeffnen();
        var f = [];
        var a = strich(100, 100, 200, 100);
        lasso(80, 80, 220, 120);
        var vorher = element(a);
        var undoVorher = B.undoStapel.length;
        // Bezugspunkt nur antippen: 0° -> kein Undo-Eintrag
        B.auswahlDrehenStarten();
        tippen(100, 100);
        tippen(200, 100);
        if (B.undoStapel.length !== undoVorher) f.push('0° hinterlässt Undo-Eintrag');
        if (element(a) !== vorher) f.push('0° ersetzt das Element');
        if (B._drehen) f.push('Modus nach 0° nicht beendet');
        // ESC mitten in der Drehung: zurück, kein Undo-Rest, Auswahl bleibt
        drehen(100, 100, 200, 100, 100, 200, { ohneAbheben: true });
        var esc = taste('Escape');
        if (!esc.defaultPrevented) f.push('ESC im Drehmodus nicht abgefangen');
        if (!B.overlay) return { ok: false, detail: 'ESC hat die Tafel geschlossen' };
        if (element(a) !== vorher) f.push('ESC nimmt die Drehung nicht zurück');
        if (B.undoStapel.length !== undoVorher) f.push('ESC hinterlässt Undo-Eintrag');
        if (B._drehen) f.push('ESC beendet den Drehmodus nicht');
        if (B.auswahl.join() !== a) f.push('ESC im Drehmodus hebt die Auswahl auf');
        ctx.zeiger('pointerup', 9, 'mouse', 100, 200); // Zeiger der abgebrochenen Geste
        if (element(a) !== vorher) f.push('Abheben nach ESC dreht doch');
        taste('Escape');
        if (B.auswahl.length) f.push('zweites ESC hebt die Auswahl nicht auf');
        return ergebnis(f);
    }

    function pinchUndWerkzeugwechsel() {
        ctx.oeffnen();
        var f = [];
        var a = strich(100, 100, 200, 100);
        lasso(80, 80, 220, 120);
        var vorher = element(a);
        var undoVorher = B.undoStapel.length;
        B.auswahlDrehenStarten();
        ctx.zeiger('pointerdown', 21, 'touch', 100, 100);
        ctx.zeiger('pointerup', 21, 'touch', 100, 100);
        ctx.zeiger('pointerdown', 22, 'touch', 200, 100);
        ctx.zeiger('pointermove', 22, 'touch', 100, 200);
        ctx.zeiger('pointerdown', 23, 'touch', 250, 250); // zweiter Finger: Zoom
        if (element(a) !== vorher) f.push('Pinch verwirft die Drehung nicht');
        if (B.undoStapel.length !== undoVorher) f.push('Pinch hinterlässt Undo-Eintrag');
        if (!B._drehen || B._drehen.phase !== 'bezug' || B._drehen.px !== 100) f.push('Drehpunkt nach Pinch nicht erhalten: ' + JSON.stringify(B._drehen && { p: B._drehen.phase, x: B._drehen.px }));
        ctx.zeiger('pointerup', 23, 'touch', 250, 250);
        ctx.zeiger('pointerup', 22, 'touch', 100, 200);
        // erneuter Bezugspunkt dreht um denselben Drehpunkt
        ctx.zeiger('pointerdown', 24, 'touch', 200, 100);
        ctx.zeiger('pointermove', 24, 'touch', 100, 200);
        ctx.zeiger('pointerup', 24, 'touch', 100, 200);
        var pn = element(a).punkte[element(a).punkte.length - 1];
        if (!nah(pn[0], 100) || !nah(pn[1], 200)) f.push('Drehen nach Pinch falsch: ' + JSON.stringify(pn));
        // Werkzeugwechsel im Drehmodus beendet Modus und Auswahl
        lasso(80, 80, 220, 220);
        B.auswahlDrehenStarten();
        B.setTool('pen');
        if (B._drehen) f.push('Werkzeugwechsel beendet den Drehmodus nicht');
        if (B.auswahl.length) f.push('Werkzeugwechsel hebt die Auswahl nicht auf');
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Auswahl-Ebene nach Werkzeugwechsel nicht leer');
        return ergebnis(f);
    }

    function textGedreht() {
        ctx.oeffnen();
        var f = [];
        var t = text(200, 100, 'MMMMMM');
        var e0 = element(t);
        var px = e0.x, py = e0.y; // Anker = linke obere Ecke, Drehpunkt genau dort
        var r0 = B._rahmen(e0);
        lasso(r0.x - 20, r0.y - 20, r0.x + r0.b + 20, r0.y + r0.h + 20);
        if (B.auswahl.join() !== t) return { ok: false, detail: 'Text nicht gewählt: ' + B.auswahl.join() };
        drehen(px, py, px + 100, py, px, py + 100);
        var e = element(t);
        if (!e || e.winkel !== 90) f.push('Text winkel ' + (e && e.winkel));
        else if (!nah(e.x, px) || !nah(e.y, py)) f.push('Ecke um den Drehpunkt bewegt: ' + e.x + '/' + e.y);
        // Schrift läuft jetzt nach unten: links unterhalb der Ecke dunkel, rechts davon nicht
        var dunkelUnten = false, dunkelRechts = false;
        for (var k = 20; k < r0.b - 10; k += 4) {
            for (var q = 4; q < r0.h - 4; q += 3) {
                if (alpha(B.drawingCanvas, px - q, py + k) > 100) dunkelUnten = true;
                if (alpha(B.drawingCanvas, px + k, py + q) > 100) dunkelRechts = true;
            }
        }
        if (!dunkelUnten) f.push('gedrehter Text nicht unterhalb der Ecke gezeichnet');
        if (dunkelRechts) f.push('Text noch waagrecht rechts der Ecke');
        var rb = B.auswahlRahmen();
        if (!rb || rb.b > rb.h) f.push('Auswahlrahmen nicht hochkant: ' + JSON.stringify(rb));
        // lokal speichern und wieder öffnen: winkel bleibt
        B.saveDrawing();
        B.destroy();
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        var w = el().filter(function(x) { return x.typ === 'text'; });
        if (w.length !== 1 || w[0].winkel !== 90) f.push('nach Wiederöffnen: ' + JSON.stringify(w.map(function(x) { return x.winkel; })));
        return ergebnis(f);
    }

    function radiererAufGedrehtemBild() {
        ctx.oeffnen();
        var f = [];
        var c = document.createElement('canvas');
        c.width = 100;
        c.height = 40;
        var g = c.getContext('2d');
        g.fillStyle = '#000000';
        g.fillRect(0, 0, 100, 40);
        var daten = c.toDataURL('image/png');
        B._bildQuellen.set(daten, c);
        // 100 x 40 an (200,200), um 90° gedreht -> belegt x 160..200, y 200..300
        B.modell.elemente = [{ id: B.neueId(), typ: 'bild', daten: daten, x: 200, y: 200, b: 100, h: 40, winkel: 90 }];
        B.zeichneModell();
        if (!dunkelUm(B.drawingCanvas, 180, 280)) f.push('gedrehtes Bild nicht bei (180,280) gezeichnet');
        if (dunkelUm(B.drawingCanvas, 260, 220, 1)) f.push('gedrehtes Bild an der unverdrehten Stelle gezeichnet');
        B.setTool('eraser-point');
        var vorher = el()[0];
        // an der UNVERDREHTEN Stelle radieren: darf nichts treffen
        ctx.zeiger('pointerdown', 5, 'pen', 270, 220);
        ctx.zeiger('pointerup', 5, 'pen', 270, 220);
        if (el()[0] !== vorher) f.push('Radierer trifft die unverdrehte Lage');
        // an der gedrehten Stelle: trifft, Element behält winkel
        ctx.zeiger('pointerdown', 5, 'pen', 180, 280);
        ctx.zeiger('pointerup', 5, 'pen', 180, 280);
        var nach = el()[0];
        if (nach === vorher || nach.daten === daten) f.push('Radierer trifft die gedrehte Lage nicht');
        else if (nach.winkel !== 90) f.push('radiertes Bild verliert winkel: ' + nach.winkel);
        return ergebnis(f);
    }

    function duplizieren() {
        ctx.oeffnen();
        var f = [];
        var a = strich(100, 100, 200, 100);
        var t = text(100, 140, 'Hallo');
        lasso(80, 80, 240, 200);
        if (B.auswahl.length !== 2) return { ok: false, detail: 'Auswahl ' + B.auswahl.join() };
        var vorherA = element(a), vorherT = element(t);
        var undoVorher = B.undoStapel.length;
        knopf('duplizieren').click();
        if (el().length !== 4) f.push(el().length + ' Elemente statt 4');
        if (element(a) !== vorherA || element(t) !== vorherT) f.push('Originale verändert');
        var neu = B.auswahl.slice();
        if (neu.length !== 2 || neu.indexOf(a) !== -1 || neu.indexOf(t) !== -1) f.push('Auswahl nach Duplizieren ' + neu.join());
        var ka = element(neu[0]), kt = element(neu[1]);
        if (!ka || ka.typ !== 'strich' || !nah(ka.punkte[0][0], 120) || !nah(ka.punkte[0][1], 120)) f.push('Strich-Kopie nicht +20 px: ' + JSON.stringify(ka && ka.punkte[0]));
        if (!kt || kt.typ !== 'text' || kt.text !== 'Hallo' || !nah(kt.x, vorherT.x + 20) || !nah(kt.y, vorherT.y + 20)) f.push('Text-Kopie falsch');
        if (B.undoStapel.length !== undoVorher + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undoVorher));
        if (!dunkelUm(B.drawingCanvas, 150, 120)) f.push('Kopie nicht gezeichnet');
        // Kopien lassen sich direkt ziehen (Rahmen um die Kopien)
        var rb = B.auswahlRahmen();
        if (!rb || rb.x > 120 - 8 + 2 || rb.x < 120 - 8 - 4) f.push('Rahmen nicht um die Kopien: ' + JSON.stringify(rb));
        // Strg+D
        var ev = taste('d', { ctrlKey: true });
        if (!ev.defaultPrevented) f.push('Strg+D nicht abgefangen');
        if (el().length !== 6) f.push('Strg+D: ' + el().length + ' Elemente statt 6');
        // Strg+D aus einem Eingabefeld: nichts
        var feld = B.overlay.querySelector('.cbd-board-grid-size');
        var ev2 = new KeyboardEvent('keydown', { key: 'd', ctrlKey: true, bubbles: true, cancelable: true });
        feld.dispatchEvent(ev2);
        if (el().length !== 6) f.push('Strg+D im Eingabefeld dupliziert');
        // Rückgängig: zweimal -> wieder 2 Elemente
        B.undo();
        B.undo();
        if (el().length !== 2) f.push('nach 2x Strg+Z ' + el().length + ' Elemente');
        // ohne Auswahl: nichts
        if (B.auswahlDuplizieren() !== false) f.push('Duplizieren ohne Auswahl liefert nicht false');
        // gedrehtes Element duplizieren: winkel kommt mit
        lasso(80, 120, 240, 200);
        drehen(100, 140, 200, 140, 100, 240);
        knopf('duplizieren').click();
        var texte = el().filter(function(x) { return x.typ === 'text'; });
        if (texte.length !== 2 || texte[1].winkel !== 90) f.push('Kopie eines gedrehten Texts ohne winkel');
        return ergebnis(f);
    }

    function rasterZeilenKaestchen() {
        ctx.oeffnen();
        var f = [];
        var knopfR = B.overlay.querySelector('.cbd-board-grid-toggle');
        var art = B.overlay.querySelector('.cbd-board-grid-art');
        var regler = B.overlay.querySelector('.cbd-board-grid-controls');
        if (!art) return { ok: false, detail: 'kein Auswahlfeld .cbd-board-grid-art' };
        var werte = Array.prototype.map.call(art.options, function(o) { return o.value; }).join(',');
        if (werte !== 'kaestchen,zeilen,horizontal,vertical') f.push('Optionen ' + werte);
        if (B.gridMode !== 'off' || knopfR.classList.contains('active') || sichtbar(regler)) {
            B.gridMode = 'off';
            B._gitterUIAktualisieren();
        }
        B.gridOffsetX = 0;
        B.gridOffsetY = 0;
        B.gridSize = 30;
        art.value = 'kaestchen';
        art.dispatchEvent(new Event('change', { bubbles: true }));
        if (B.gridMode !== 'kaestchen') f.push('Auswahl Kästchen -> gridMode ' + B.gridMode);
        if (!knopfR.classList.contains('active')) f.push('Knopf nach Auswahl nicht aktiv');
        // Linien bei y = 30, 60 (waagrecht) und x = 30, 60 (senkrecht)
        if (!alpha(B.gridCanvas, 45, 30)) f.push('Kästchen: keine waagrechte Linie bei y 30');
        if (!alpha(B.gridCanvas, 30, 45)) f.push('Kästchen: keine senkrechte Linie bei x 30');
        if (alpha(B.gridCanvas, 45, 45)) f.push('Kästchen: Linie mitten im Kästchen');
        art.value = 'zeilen';
        art.dispatchEvent(new Event('change', { bubbles: true }));
        if (B.gridMode !== 'zeilen') f.push('Auswahl Zeilen -> gridMode ' + B.gridMode);
        if (!alpha(B.gridCanvas, 45, 60)) f.push('Zeilen: keine Linie bei y 60 (Abstand 2 x Größe)');
        if (alpha(B.gridCanvas, 45, 30)) f.push('Zeilen: Linie bei y 30');
        if (alpha(B.gridCanvas, 30, 45) || alpha(B.gridCanvas, 60, 45)) f.push('Zeilen: senkrechte Linie');
        // Versatz Y verschiebt die Zeilen
        var y = B.overlay.querySelector('.cbd-board-grid-offset-y');
        y.value = '10';
        y.dispatchEvent(new Event('input', { bubbles: true }));
        if (!alpha(B.gridCanvas, 45, 70) || alpha(B.gridCanvas, 45, 60)) f.push('Versatz Y wirkt nicht auf Zeilen');
        // aus und wieder an: zuletzt gewählte Art
        knopfR.click();
        if (B.gridMode !== 'off' || !ebeneLeer(B.gridCanvas) || sichtbar(regler)) f.push('Knopf schaltet nicht aus');
        knopfR.click();
        if (B.gridMode !== 'zeilen') f.push('Wiedereinschalten -> ' + B.gridMode + ' statt zeilen');
        // Sechsecke weiterhin wählbar
        art.value = 'vertical';
        art.dispatchEvent(new Event('change', { bubbles: true }));
        if (B.gridMode !== 'vertical' || ebeneLeer(B.gridCanvas)) f.push('Sechsecke nicht wählbar');
        // Wiederöffnen: Raster bleibt, Leiste zeigt es
        B.destroy();
        B.open(CID, '<p>Inhalt</p>', '#ffffff');
        knopfR = B.overlay.querySelector('.cbd-board-grid-toggle');
        art = B.overlay.querySelector('.cbd-board-grid-art');
        regler = B.overlay.querySelector('.cbd-board-grid-controls');
        if (!knopfR.classList.contains('active') || art.value !== 'vertical' || !sichtbar(regler)) f.push('Leiste nach Wiederöffnen passt nicht zum Raster');
        if (B.overlay.querySelector('.cbd-board-grid-offset-y').value !== '10') f.push('Regler Y nach Wiederöffnen nicht 10');
        knopfR.click(); // aufräumen
        B.gridOffsetY = 0;
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // Prüfungen (asynchron)
    // ------------------------------------------------------------------
    function zwischenstandVorAbheben() {
        ctx.oeffnen();
        var f = [];
        var a = strich(100, 100, 200, 100);
        lasso(80, 80, 220, 120);
        drehen(100, 100, 200, 100, 100, 200, { ohneAbheben: true });
        return ctx.warteAuf(function() {
            var s = element(a);
            return s && nah(s.punkte[s.punkte.length - 1][1], 200);
        }, 1000).then(function() {
            if (!dunkelUm(B.drawingCanvas, 100, 180)) f.push('Zwischenstand nicht gezeichnet');
            if (sichtbar(leiste())) f.push('Leiste während der Drehung sichtbar');
            if (!dunkelUm(B.auswahlCanvas, 100, 100)) f.push('Drehpunkt während der Drehung nicht sichtbar');
            ctx.zeiger('pointerup', 9, 'mouse', 100, 200);
            return ergebnis(f);
        }, function() {
            ctx.zeiger('pointerup', 9, 'mouse', 100, 200);
            return { ok: false, detail: 'kein Zwischenstand vor dem Abheben' };
        });
    }

    // ------------------------------------------------------------------
    // Einhängen (nach den vorhandenen Haken)
    // ------------------------------------------------------------------
    window.__zusatzTests = function(c) {
        if (typeof vorherSync === 'function') vorherSync(c);
        ctx = c;
        var p = c.pruefe;
        p('3.1.143: Leiste mit "Drehen"/"Duplizieren", Drehmodus zeigt Hinweis + "Abbrechen"', leisteKnoepfe);
        p('3.1.143: Drehen um 90° (Drehpunkt, Bezugspunkt, ziehen) - 1 Undo, Auswahl bleibt', drehen90);
        p('3.1.143: Drehpunkt folgt dem gehaltenen Zeiger, Anzeige nur auf der Auswahl-Ebene', drehpunktFolgtUndAnzeige);
        p('3.1.143: Einrasten an 45°, Umschalttaste 15°, gegen den Uhrzeigersinn', einrastenUndFein);
        p('3.1.143: 0° ohne Undo, ESC nimmt die Drehung zurück, zweites ESC hebt auf', nullGradUndEsc);
        p('3.1.143: Pinch verwirft die Drehung (Drehpunkt bleibt), Werkzeugwechsel beendet', pinchUndWerkzeugwechsel);
        p('3.1.143: gedrehter Text gezeichnet, Rahmen hochkant, winkel überlebt Speichern', textGedreht);
        p('3.1.143: Punkt-Radierer trifft gedrehtes Bild an der gedrehten Stelle', radiererAufGedrehtemBild);
        p('3.1.143: Duplizieren per Knopf und Strg+D (+20 px, Kopien gewählt, 1 Undo)', duplizieren);
        p('3.1.143: Raster Kästchen/Zeilen/Sechsecke, ein/aus, Versatz, Wiederöffnen', rasterZeilenKaestchen);
    };

    window.__zusatzTestsAsync = function(c) {
        var vorher = (typeof vorherAsync === 'function') ? vorherAsync(c) : Promise.resolve();
        return Promise.resolve(vorher).then(function() {
            ctx = c;
            return c.pruefeAsync('3.1.143: Zwischenstand der Drehung vor dem Abheben (Frame-Bündelung)', zwischenstandVorAbheben);
        });
    };
})();
