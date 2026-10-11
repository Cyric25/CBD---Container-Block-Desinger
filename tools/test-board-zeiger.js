/* Testlogik für tools/test-board-zeiger.html (#7149) */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var CID = 'test-zeiger';
    var ergebnisse = [];
    window.__ergebnisse = ergebnisse;

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

    function oeffnen() {
        if (B.overlay) B.destroy();
        speicherLeeren();
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        B.setTool('pen');
    }

    function schliessen() {
        if (B.overlay) B.destroy();
        var t = document.querySelector('.cbd-board-text-input');
        if (t && t.parentNode) t.parentNode.removeChild(t);
        speicherLeeren();
    }

    // Ereignis am Zeichen-Canvas auslösen; x/y relativ zur Canvas-Ecke (CSS-Pixel)
    function zeiger(typ, id, art, x, y, extra) {
        extra = extra || {};
        var rect = B.drawingCanvas.getBoundingClientRect();
        var init = {
            bubbles: true,
            cancelable: true,
            composed: true,
            pointerId: id,
            pointerType: art,
            isPrimary: extra.isPrimary !== undefined ? extra.isPrimary : true,
            clientX: rect.left + x,
            clientY: rect.top + y,
            width: extra.width !== undefined ? extra.width : (art === 'touch' ? 10 : 1),
            height: extra.height !== undefined ? extra.height : (art === 'touch' ? 10 : 1),
            pressure: typ === 'pointerup' ? 0 : 0.5,
            button: extra.button !== undefined ? extra.button : (typ === 'pointermove' ? -1 : 0),
            buttons: extra.buttons !== undefined ? extra.buttons : (typ === 'pointerup' ? 0 : 1)
        };
        var ev = new PointerEvent(typ, init);
        (extra.ziel || B.drawingCanvas).dispatchEvent(ev);
        return ev;
    }

    function pruefe(name, fn) {
        var ok = false, detail = '';
        try {
            var r = fn();
            ok = r === true || (r && r.ok === true);
            detail = (r && r.detail) ? r.detail : (ok ? '' : 'Bedingung nicht erfüllt');
        } catch (e) {
            ok = false;
            detail = 'Ausnahme: ' + (e && e.message ? e.message : e);
        }
        ergebnisse.push({ name: name, ok: ok, detail: detail });
        try { schliessen(); } catch (e2) {}
    }

    function alleImBereich(punkte, minX, maxX, minY, maxY) {
        for (var i = 0; i < punkte.length; i++) {
            var p = punkte[i];
            if (p.x < minX || p.x > maxX || p.y < minY || p.y > maxY) return false;
        }
        return true;
    }

    function punkteText(s) {
        return s.points.map(function(p) { return Math.round(p.x) + '/' + Math.round(p.y); }).join(' ');
    }

    // ------------------------------------------------------------------
    // Szenario A: Ballen liegt auf, dann zeichnet der Stift
    // ------------------------------------------------------------------
    function szenarioA(ballenBreite) {
        return function() {
            oeffnen();
            zeiger('pointerdown', 2, 'touch', 60, 300, { width: ballenBreite, height: ballenBreite });
            zeiger('pointermove', 2, 'touch', 62, 305, { width: ballenBreite, height: ballenBreite });
            zeiger('pointerdown', 3, 'pen', 200, 100);
            zeiger('pointermove', 2, 'touch', 64, 310, { width: ballenBreite, height: ballenBreite });
            zeiger('pointermove', 3, 'pen', 210, 105);
            zeiger('pointermove', 2, 'touch', 66, 312, { width: ballenBreite, height: ballenBreite });
            zeiger('pointermove', 3, 'pen', 220, 110);
            zeiger('pointermove', 3, 'pen', 230, 115);
            zeiger('pointerup', 3, 'pen', 230, 115);
            zeiger('pointerup', 2, 'touch', 66, 312, { width: ballenBreite, height: ballenBreite });
            var s = B.strokes;
            if (s.length !== 1) return { ok: false, detail: 'erwartet 1 Strich, gefunden ' + s.length + (s.length ? ' (' + s.map(punkteText).join(' | ') + ')' : '') };
            if (s[0].points.length !== 4) return { ok: false, detail: 'erwartet 4 Stiftpunkte, gefunden ' + s[0].points.length + ': ' + punkteText(s[0]) };
            if (!alleImBereich(s[0].points, 190, 240, 90, 125)) return { ok: false, detail: 'Fremdpunkte im Strich: ' + punkteText(s[0]) };
            return true;
        };
    }

    // ------------------------------------------------------------------
    // Szenario B: Stift zeichnet, Ballen setzt auf und bewegt sich
    // ------------------------------------------------------------------
    function szenarioB(ballenBreite) {
        return function() {
            oeffnen();
            zeiger('pointerdown', 3, 'pen', 200, 100);
            zeiger('pointermove', 3, 'pen', 210, 100);
            zeiger('pointerdown', 2, 'touch', 60, 300, { width: ballenBreite, height: ballenBreite });
            zeiger('pointermove', 3, 'pen', 220, 100);
            zeiger('pointermove', 2, 'touch', 80, 320, { width: ballenBreite, height: ballenBreite });
            zeiger('pointermove', 3, 'pen', 230, 100);
            zeiger('pointermove', 2, 'touch', 100, 340, { width: ballenBreite, height: ballenBreite });
            zeiger('pointermove', 3, 'pen', 240, 100);
            var zoomWaehrend = B.zoom;
            zeiger('pointerup', 3, 'pen', 240, 100);
            zeiger('pointerup', 2, 'touch', 100, 340, { width: ballenBreite, height: ballenBreite });
            var s = B.strokes;
            if (zoomWaehrend !== 1) return { ok: false, detail: 'Zoom geändert: ' + zoomWaehrend };
            if (s.length !== 1) return { ok: false, detail: 'erwartet 1 Strich, gefunden ' + s.length };
            if (s[0].points.length !== 5) return { ok: false, detail: 'erwartet 5 Stiftpunkte, gefunden ' + s[0].points.length + ': ' + punkteText(s[0]) };
            if (!alleImBereich(s[0].points, 195, 245, 95, 105)) return { ok: false, detail: 'Fremdpunkte: ' + punkteText(s[0]) };
            return true;
        };
    }

    // ------------------------------------------------------------------
    // Szenario C: Stift zeichnet, Ballen hebt ab -> Strich läuft weiter
    // ------------------------------------------------------------------
    function szenarioC(ballenBreite, ballenZuerst) {
        return function() {
            oeffnen();
            if (ballenZuerst) {
                zeiger('pointerdown', 2, 'touch', 60, 300, { width: ballenBreite, height: ballenBreite });
                zeiger('pointerdown', 3, 'pen', 200, 100);
            } else {
                zeiger('pointerdown', 3, 'pen', 200, 100);
                zeiger('pointerdown', 2, 'touch', 60, 300, { width: ballenBreite, height: ballenBreite });
            }
            zeiger('pointermove', 3, 'pen', 210, 100);
            zeiger('pointerup', 2, 'touch', 60, 300, { width: ballenBreite, height: ballenBreite });
            zeiger('pointerleave', 2, 'touch', 60, 300, { width: ballenBreite, height: ballenBreite });
            zeiger('pointermove', 3, 'pen', 220, 100);
            zeiger('pointermove', 3, 'pen', 230, 100);
            var laeuft = B.isDrawing;
            zeiger('pointerup', 3, 'pen', 230, 100);
            var s = B.strokes;
            if (!laeuft) return { ok: false, detail: 'Strich nach Abheben des Ballens beendet' };
            if (s.length !== 1) return { ok: false, detail: 'erwartet 1 Strich, gefunden ' + s.length };
            if (s[0].points.length !== 4) return { ok: false, detail: 'erwartet 4 Punkte, gefunden ' + s[0].points.length + ': ' + punkteText(s[0]) };
            return true;
        };
    }

    // ------------------------------------------------------------------
    // Szenario D: nach Stiftstrich zeichnet ein Finger nicht, zwei Finger zoomen
    // ------------------------------------------------------------------
    function szenarioD() {
        oeffnen();
        zeiger('pointerdown', 3, 'pen', 200, 100);
        zeiger('pointermove', 3, 'pen', 220, 100);
        zeiger('pointerup', 3, 'pen', 220, 100);
        if (B.strokes.length !== 1) return { ok: false, detail: 'Stiftstrich fehlt' };
        // Ein Finger
        zeiger('pointerdown', 10, 'touch', 100, 200);
        zeiger('pointermove', 10, 'touch', 150, 200);
        zeiger('pointermove', 10, 'touch', 200, 200);
        zeiger('pointerup', 10, 'touch', 200, 200);
        if (B.strokes.length !== 1) return { ok: false, detail: 'Finger hat nach Stiftstrich gezeichnet (' + B.strokes.length + ' Striche)' };
        if (B.currentStroke) return { ok: false, detail: 'currentStroke durch Finger gesetzt' };
        // Zwei Finger
        zeiger('pointerdown', 11, 'touch', 100, 200);
        zeiger('pointerdown', 12, 'touch', 200, 200, { isPrimary: false });
        zeiger('pointermove', 12, 'touch', 300, 200, { isPrimary: false });
        var z = B.zoom;
        zeiger('pointerup', 12, 'touch', 300, 200, { isPrimary: false });
        zeiger('pointerup', 11, 'touch', 100, 200);
        if (Math.abs(z - 2) > 0.01) return { ok: false, detail: 'Zoom erwartet 2, ist ' + z };
        if (B.strokes.length !== 1) return { ok: false, detail: 'Pinch hat Strich erzeugt' };
        return true;
    }

    // ------------------------------------------------------------------
    // Szenario E: ohne Stift zeichnet ein Finger, zwei Finger zoomen
    // ------------------------------------------------------------------
    function szenarioE() {
        oeffnen();
        zeiger('pointerdown', 10, 'touch', 100, 200);
        zeiger('pointermove', 10, 'touch', 150, 200);
        zeiger('pointermove', 10, 'touch', 200, 200);
        zeiger('pointerup', 10, 'touch', 200, 200);
        if (B.strokes.length !== 1) return { ok: false, detail: 'Finger-Strich fehlt (' + B.strokes.length + ')' };
        if (B.strokes[0].points.length !== 3) return { ok: false, detail: 'Finger-Strich hat ' + B.strokes[0].points.length + ' Punkte' };
        zeiger('pointerdown', 11, 'touch', 100, 200);
        zeiger('pointerdown', 12, 'touch', 200, 200, { isPrimary: false });
        zeiger('pointermove', 12, 'touch', 300, 200, { isPrimary: false });
        var z = B.zoom;
        zeiger('pointerup', 12, 'touch', 300, 200, { isPrimary: false });
        zeiger('pointerup', 11, 'touch', 100, 200);
        if (Math.abs(z - 2) > 0.01) return { ok: false, detail: 'Zoom erwartet 2, ist ' + z };
        if (B.strokes.length !== 1) return { ok: false, detail: 'Pinch hat Strich erzeugt (' + B.strokes.length + ')' };
        if (B.currentStroke) return { ok: false, detail: 'abgebrochener Finger-Strich hängt als currentStroke' };
        return true;
    }

    // Stift-Vorrang gilt nur bis zum Schließen der Tafel
    function stiftVorrangJeOeffnen() {
        oeffnen();
        zeiger('pointerdown', 3, 'pen', 200, 100);
        zeiger('pointerup', 3, 'pen', 200, 100);
        B.destroy();
        oeffnen();
        zeiger('pointerdown', 10, 'touch', 100, 200);
        zeiger('pointermove', 10, 'touch', 150, 200);
        zeiger('pointerup', 10, 'touch', 150, 200);
        return B.strokes.length === 1 ? true : { ok: false, detail: 'Finger zeichnet nach erneutem Öffnen nicht (' + B.strokes.length + ')' };
    }

    // Finger-Strich wird verworfen, wenn der Stift dazukommt
    function stiftUebernimmtFingerstrich() {
        oeffnen();
        zeiger('pointerdown', 10, 'touch', 100, 200);
        zeiger('pointermove', 10, 'touch', 120, 200);
        zeiger('pointerdown', 3, 'pen', 300, 100);
        zeiger('pointermove', 3, 'pen', 310, 100);
        zeiger('pointermove', 10, 'touch', 140, 200);
        zeiger('pointerup', 10, 'touch', 140, 200);
        zeiger('pointermove', 3, 'pen', 320, 100);
        zeiger('pointerup', 3, 'pen', 320, 100);
        var s = B.strokes;
        if (s.length !== 1) return { ok: false, detail: 'erwartet 1 Strich, gefunden ' + s.length };
        if (!alleImBereich(s[0].points, 295, 325, 95, 105) || s[0].points.length !== 3) return { ok: false, detail: punkteText(s[0]) };
        return true;
    }

    function rechtsklick() {
        oeffnen();
        zeiger('pointerdown', 1, 'mouse', 100, 100, { button: 2, buttons: 2 });
        zeiger('pointerup', 1, 'mouse', 100, 100, { button: 2, buttons: 0 });
        if (B.strokes.length !== 0) return { ok: false, detail: 'Rechtsklick hat ' + B.strokes.length + ' Strich(e) erzeugt' };
        if (B.isDrawing) return { ok: false, detail: 'isDrawing nach Rechtsklick' };
        // Mittelklick ebenso
        zeiger('pointerdown', 1, 'mouse', 100, 100, { button: 1, buttons: 4 });
        zeiger('pointerup', 1, 'mouse', 100, 100, { button: 1, buttons: 0 });
        return B.strokes.length === 0 ? true : { ok: false, detail: 'Mittelklick zeichnet' };
    }

    // ------------------------------------------------------------------
    // Regression
    // ------------------------------------------------------------------
    function mausZeichnen() {
        oeffnen();
        zeiger('pointerdown', 1, 'mouse', 100, 100);
        zeiger('pointermove', 1, 'mouse', 150, 120);
        zeiger('pointermove', 1, 'mouse', 200, 140);
        zeiger('pointerup', 1, 'mouse', 200, 140);
        var s = B.strokes;
        if (s.length !== 1 || s[0].points.length !== 3 || s[0].tool !== 'pen') return { ok: false, detail: JSON.stringify(s) };
        // Pixel gesetzt?
        var dpr = B.dpr || 1;
        var px = B.drawingCtx.getImageData(Math.round(150 * dpr), Math.round(120 * dpr), 1, 1).data;
        return px[3] > 0 ? true : { ok: false, detail: 'kein Pixel bei 150/120' };
    }

    function mausVerlaesstCanvasOhneCapture() {
        // Ohne Pointer-Capture beendet pointerleave den Strich weiterhin
        // (bisheriges Verhalten für Maus ohne Capture).
        oeffnen();
        zeiger('pointerdown', 1, 'mouse', 100, 100);
        zeiger('pointermove', 1, 'mouse', 150, 120);
        zeiger('pointerleave', 1, 'mouse', 150, 120, { buttons: 1 });
        return (!B.isDrawing && B.strokes.length === 1) ? true : { ok: false, detail: 'isDrawing=' + B.isDrawing + ', Striche=' + B.strokes.length };
    }

    function textmarker() {
        oeffnen();
        B.setTool('highlighter');
        zeiger('pointerdown', 3, 'pen', 100, 100);
        zeiger('pointermove', 3, 'pen', 200, 100);
        zeiger('pointerup', 3, 'pen', 200, 100);
        var s = B.strokes;
        return (s.length === 1 && s[0].tool === 'highlighter' && s[0].points.length === 2) ? true : { ok: false, detail: JSON.stringify(s) };
    }

    function strichRadierer() {
        oeffnen();
        zeiger('pointerdown', 3, 'pen', 100, 100);
        zeiger('pointermove', 3, 'pen', 200, 100);
        zeiger('pointerup', 3, 'pen', 200, 100);
        zeiger('pointerdown', 3, 'pen', 100, 300);
        zeiger('pointermove', 3, 'pen', 200, 300);
        zeiger('pointerup', 3, 'pen', 200, 300);
        B.setTool('eraser-stroke');
        zeiger('pointerdown', 3, 'pen', 50, 200);
        zeiger('pointermove', 3, 'pen', 100, 300);
        zeiger('pointerup', 3, 'pen', 100, 300);
        var s = B.strokes;
        return (s.length === 1 && s[0].points[0].y === 100) ? true : { ok: false, detail: s.length + ' Striche' };
    }

    function punktRadierer() {
        oeffnen();
        zeiger('pointerdown', 3, 'pen', 100, 100);
        zeiger('pointermove', 3, 'pen', 200, 100);
        zeiger('pointerup', 3, 'pen', 200, 100);
        B.setTool('eraser-point');
        zeiger('pointerdown', 3, 'pen', 150, 100);
        zeiger('pointermove', 3, 'pen', 155, 100);
        var waehrend = B.isDrawing;
        zeiger('pointerup', 3, 'pen', 155, 100);
        var dpr = B.dpr || 1;
        var px = B.drawingCtx.getImageData(Math.round(152 * dpr), Math.round(100 * dpr), 1, 1).data;
        if (!waehrend) return { ok: false, detail: 'Punkt-Radierer radiert nicht kontinuierlich' };
        if (B.strokes.length !== 1) return { ok: false, detail: 'Punkt-Radierer hat Strichliste verändert' };
        return px[3] === 0 ? true : { ok: false, detail: 'Pixel bei 152/100 nicht radiert (alpha ' + px[3] + ')' };
    }

    function fingerRadiertNichtNachStift() {
        oeffnen();
        zeiger('pointerdown', 3, 'pen', 100, 100);
        zeiger('pointermove', 3, 'pen', 200, 100);
        zeiger('pointerup', 3, 'pen', 200, 100);
        B.setTool('eraser-stroke');
        // genau auf einen Strichpunkt (100/100), damit ein Radiervorgang sicher träfe
        zeiger('pointerdown', 10, 'touch', 100, 100);
        zeiger('pointermove', 10, 'touch', 200, 100);
        zeiger('pointerup', 10, 'touch', 200, 100);
        return B.strokes.length === 1 ? true : { ok: false, detail: 'Finger hat nach Stift radiert' };
    }

    function textWerkzeug() {
        oeffnen();
        B.setTool('text');
        zeiger('pointerdown', 1, 'mouse', 120, 80);
        var feld = document.querySelector('.cbd-board-text-input');
        if (!feld) return { ok: false, detail: 'kein Textfeld geöffnet' };
        feld.value = 'Hallo';
        feld.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
        var s = B.strokes;
        return (s.length === 1 && s[0].tool === 'text' && s[0].text === 'Hallo') ? true : { ok: false, detail: JSON.stringify(s) };
    }

    function rueckgaengig() {
        oeffnen();
        zeiger('pointerdown', 1, 'mouse', 100, 100);
        zeiger('pointermove', 1, 'mouse', 150, 100);
        zeiger('pointerup', 1, 'mouse', 150, 100);
        zeiger('pointerdown', 1, 'mouse', 100, 200);
        zeiger('pointermove', 1, 'mouse', 150, 200);
        zeiger('pointerup', 1, 'mouse', 150, 200);
        B.undo();
        var dpr = B.dpr || 1;
        var weg = B.drawingCtx.getImageData(Math.round(125 * dpr), Math.round(200 * dpr), 1, 1).data[3];
        var da = B.drawingCtx.getImageData(Math.round(125 * dpr), Math.round(100 * dpr), 1, 1).data[3];
        return (B.strokes.length === 1 && weg === 0 && da > 0) ? true : { ok: false, detail: 'Striche ' + B.strokes.length + ', alpha weg=' + weg + ', da=' + da };
    }

    function mehrseitig() {
        oeffnen();
        zeiger('pointerdown', 1, 'mouse', 100, 100);
        zeiger('pointermove', 1, 'mouse', 150, 100);
        zeiger('pointerup', 1, 'mouse', 150, 100);
        B.addPage();
        if (B.currentPageIndex !== 1 || B.strokes.length !== 0) return { ok: false, detail: 'addPage: Seite ' + B.currentPageIndex + ', Striche ' + B.strokes.length };
        zeiger('pointerdown', 3, 'pen', 100, 200);
        zeiger('pointermove', 3, 'pen', 150, 200);
        zeiger('pointerup', 3, 'pen', 150, 200);
        if (B.strokes.length !== 1) return { ok: false, detail: 'Zeichnen auf Seite 2' };
        B.goToPage(0);
        var gespeichert = null;
        try { gespeichert = localStorage.getItem('cbd-board-' + CID + ':p1'); } catch (e) {}
        return (B.currentPageIndex === 0 && gespeichert && gespeichert.indexOf('data:image/png') === 0) ? true : { ok: false, detail: 'Seite 2 nicht gespeichert' };
    }

    function speichernLaden() {
        oeffnen();
        zeiger('pointerdown', 1, 'mouse', 100, 100);
        zeiger('pointermove', 1, 'mouse', 200, 100);
        zeiger('pointerup', 1, 'mouse', 200, 100);
        B.saveDrawing();
        var gespeichert = null;
        try { gespeichert = localStorage.getItem('cbd-board-' + CID); } catch (e) {}
        return (gespeichert && gespeichert.indexOf('data:image/png') === 0) ? true : { ok: false, detail: 'nicht gespeichert' };
    }

    function zustandNachDestroy() {
        oeffnen();
        zeiger('pointerdown', 3, 'pen', 100, 100);
        B.destroy();
        var rest = [];
        if (B.isDrawing) rest.push('isDrawing');
        if (B.drawingPointerId !== null && B.drawingPointerId !== undefined) rest.push('drawingPointerId=' + B.drawingPointerId);
        if (B.stiftGesehen) rest.push('stiftGesehen');
        return rest.length ? { ok: false, detail: rest.join(', ') } : true;
    }

    // ------------------------------------------------------------------
    // AP-2.2: iOS-Gesten sperren, Seitenknöpfe ausblenden
    // ------------------------------------------------------------------
    function ereignis(ziel, typ, Konstruktor) {
        var ev;
        if (Konstruktor === 'touch' && typeof TouchEvent === 'function') {
            ev = new TouchEvent(typ, { bubbles: true, cancelable: true, composed: true });
        } else if (Konstruktor === 'mouse') {
            ev = new MouseEvent(typ, { bubbles: true, cancelable: true, composed: true });
        } else {
            ev = new Event(typ, { bubbles: true, cancelable: true, composed: true });
        }
        ziel.dispatchEvent(ev);
        return ev.defaultPrevented;
    }

    function klasseAnHtml() {
        oeffnen();
        var offen = document.documentElement.classList.contains('cbd-board-offen');
        B.destroy();
        var danach = document.documentElement.classList.contains('cbd-board-offen');
        return (offen && !danach) ? true : { ok: false, detail: 'offen=' + offen + ', nach destroy=' + danach };
    }

    function markierungBeimOeffnenWeg() {
        var r = document.createRange();
        r.selectNodeContents(document.getElementById('seitentext'));
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
        var vorher = sel.toString();
        oeffnen();
        var nachher = window.getSelection().toString();
        return (vorher.length > 0 && nachher === '') ? true : { ok: false, detail: 'vorher "' + vorher + '", nachher "' + nachher + '"' };
    }

    function selectstartGesperrt() {
        oeffnen();
        var inhalt = B.overlay.querySelector('.cbd-board-content p');
        var textknoten = inhalt.firstChild;
        var werkzeug = B.overlay.querySelector('.cbd-board-tool');
        var seite = document.getElementById('seitentext').firstChild;
        var f = [];
        if (!ereignis(textknoten, 'selectstart')) f.push('Inhalt (Textknoten) nicht gesperrt');
        if (!ereignis(werkzeug, 'selectstart')) f.push('Toolbar nicht gesperrt');
        if (!ereignis(seite, 'selectstart')) f.push('Seitentext hinter der Tafel nicht gesperrt');
        var breite = B.overlay.querySelector('.cbd-board-width');
        if (ereignis(breite, 'selectstart')) f.push('input range gesperrt');
        // Textfeld des Text-Werkzeugs (hängt an body)
        B.setTool('text');
        zeiger('pointerdown', 1, 'mouse', 120, 80);
        var feld = document.querySelector('.cbd-board-text-input');
        if (!feld) f.push('kein Textfeld');
        else if (ereignis(feld, 'selectstart')) f.push('Textfeld gesperrt');
        if (feld) {
            var us = getComputedStyle(feld).webkitUserSelect || getComputedStyle(feld).userSelect;
            if (us !== 'text' && us !== 'auto') f.push('Textfeld user-select=' + us);
            feld.value = '';
            feld.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        }
        B.destroy();
        if (ereignis(seite, 'selectstart')) f.push('nach Schließen weiter gesperrt');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function touchAmCanvasGesperrt() {
        if (typeof TouchEvent !== 'function') return { ok: false, detail: 'TouchEvent nicht konstruierbar (Browser)' };
        oeffnen();
        var f = [];
        if (!ereignis(B.drawingCanvas, 'touchstart', 'touch')) f.push('touchstart am Canvas nicht verhindert');
        if (!ereignis(B.drawingCanvas, 'touchmove', 'touch')) f.push('touchmove am Canvas nicht verhindert');
        var inhalt = B.overlay.querySelector('.cbd-board-content p');
        if (ereignis(inhalt, 'touchstart', 'touch')) f.push('touchstart im Inhalt verhindert (Scrollen kaputt)');
        if (ereignis(inhalt, 'touchmove', 'touch')) f.push('touchmove im Inhalt verhindert (Scrollen kaputt)');
        var breite = B.overlay.querySelector('.cbd-board-width');
        if (ereignis(breite, 'touchstart', 'touch')) f.push('touchstart am Schieberegler verhindert');
        var farbe = B.overlay.querySelector('.cbd-board-color');
        if (ereignis(farbe, 'touchstart', 'touch')) f.push('touchstart an Farbwahl verhindert');
        var canvas = B.drawingCanvas;
        B.destroy();
        if (ereignis(canvas, 'touchstart', 'touch')) f.push('nach Schließen weiter verhindert');
        if (ereignis(document.body, 'touchstart', 'touch')) f.push('body nach Schließen verhindert');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function doppelklickGesperrt() {
        oeffnen();
        var f = [];
        if (!ereignis(B.overlay.querySelector('.cbd-board-content p'), 'dblclick', 'mouse')) f.push('dblclick im Inhalt nicht verhindert');
        if (!ereignis(B.overlay.querySelector('.cbd-board-tool'), 'dblclick', 'mouse')) f.push('dblclick in Toolbar nicht verhindert');
        if (ereignis(B.overlay.querySelector('.cbd-board-width'), 'dblclick', 'mouse')) f.push('dblclick an input verhindert');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function kontextmenueWeiterGesperrt() {
        // Regression #7063
        oeffnen();
        var f = [];
        if (!ereignis(B.drawingCanvas, 'contextmenu', 'mouse')) f.push('Canvas');
        if (!ereignis(B.overlay.querySelector('.cbd-board-tool'), 'contextmenu', 'mouse')) f.push('Toolbar');
        if (ereignis(B.overlay.querySelector('.cbd-board-width'), 'contextmenu', 'mouse')) f.push('input gesperrt');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function cssGestensperre() {
        oeffnen();
        var f = [];
        var cs = function(el) { return getComputedStyle(el); };
        var html = cs(document.documentElement), body = cs(document.body);
        var usH = html.webkitUserSelect || html.userSelect, usB = body.webkitUserSelect || body.userSelect;
        if (usH !== 'none') f.push('html user-select=' + usH);
        if (usB !== 'none') f.push('body user-select=' + usB);
        if (html.overscrollBehaviorY !== 'none') f.push('html overscroll=' + html.overscrollBehaviorY);
        var ov = cs(B.overlay);
        if (ov.touchAction !== 'none') f.push('Overlay touch-action=' + ov.touchAction);
        if (ov.zIndex !== '1000000') f.push('Overlay z-index=' + ov.zIndex);
        if (ov.overscrollBehaviorY !== 'contain') f.push('Overlay overscroll=' + ov.overscrollBehaviorY);
        var inhalt = cs(B.overlay.querySelector('.cbd-board-content'));
        if (inhalt.touchAction !== 'pan-y') f.push('Inhalt touch-action=' + inhalt.touchAction);
        var knopf = cs(B.overlay.querySelector('.cbd-board-tool'));
        if (knopf.touchAction !== 'manipulation') f.push('Werkzeugknopf touch-action=' + knopf.touchAction);
        var regler = cs(B.overlay.querySelector('.cbd-board-width'));
        if (regler.touchAction !== 'manipulation') f.push('Regler touch-action=' + regler.touchAction);
        B.destroy();
        var htmlDanach = cs(document.documentElement);
        var usDanach = htmlDanach.webkitUserSelect || htmlDanach.userSelect;
        if (usDanach === 'none') f.push('html nach Schließen weiter user-select:none');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function dialogeUeberOverlay() {
        oeffnen();
        B.showClearConfirm();
        var dlg = document.querySelector('.cbd-board-confirm-overlay');
        var z = dlg ? parseInt(getComputedStyle(dlg).zIndex, 10) : 0;
        var ov = parseInt(getComputedStyle(B.overlay).zIndex, 10);
        if (dlg && dlg.parentNode) dlg.parentNode.removeChild(dlg);
        return (z > ov) ? true : { ok: false, detail: 'Dialog z-index ' + z + ' <= Overlay ' + ov };
    }

    function seitenknoepfeAusgeblendet() {
        var ids = ['#cbd-pdf-export-fab', '.cbd-notes-manager-button', '.sidebar-toggle-btn'];
        var f = [];
        ids.forEach(function(s) {
            if (getComputedStyle(document.querySelector(s)).display === 'none') f.push(s + ' schon vor dem Öffnen unsichtbar');
        });
        oeffnen();
        ids.forEach(function(s) {
            if (getComputedStyle(document.querySelector(s)).display !== 'none') f.push(s + ' bei offener Tafel sichtbar');
        });
        B.destroy();
        ids.forEach(function(s) {
            if (getComputedStyle(document.querySelector(s)).display === 'none') f.push(s + ' nach dem Schließen unsichtbar');
        });
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function keineDoppeltenListener() {
        var z = window.__listenerZaehler;
        if (B.overlay) B.destroy();
        var basis = JSON.stringify(z.offen);
        window.__zaehlerAktiv = true;
        var nachOeffnen = [];
        try {
            for (var i = 0; i < 3; i++) {
                oeffnen();
                var summe = 0;
                for (var k in z.offen) summe += z.offen[k];
                nachOeffnen.push(summe);
                B.destroy();
            }
        } finally {
            window.__zaehlerAktiv = false;
        }
        var offen = [];
        for (var k2 in z.offen) if (z.offen[k2] !== 0) offen.push(k2 + '=' + z.offen[k2]);
        if (offen.length) return { ok: false, detail: 'nach 3x Öffnen/Schließen offen: ' + offen.join(', ') + ' (je Öffnen ' + nachOeffnen.join('/') + ')' };
        if (nachOeffnen[0] !== nachOeffnen[2]) return { ok: false, detail: 'Listener wachsen je Öffnen: ' + nachOeffnen.join('/') };
        return { ok: true, detail: 'je Öffnen ' + nachOeffnen[0] + ' document/html/body/window-Listener, nach Schließen 0 (Basis ' + basis + ')' };
    }

    // ------------------------------------------------------------------
    // AP-2.3: Flüssiges Zeichnen (inkrementell, coalesced events)
    // ------------------------------------------------------------------
    function bildDaten() {
        return B.drawingCtx.getImageData(0, 0, B.drawingCanvas.width, B.drawingCanvas.height).data;
    }

    function pixelAbweichung(a, b) {
        var n = 0, gesamt = a.length / 4;
        for (var i = 0; i < a.length; i += 4) {
            if (Math.abs(a[i] - b[i]) > 8 || Math.abs(a[i + 1] - b[i + 1]) > 8 ||
                Math.abs(a[i + 2] - b[i + 2]) > 8 || Math.abs(a[i + 3] - b[i + 3]) > 8) n++;
        }
        return { anzahl: n, anteil: n / gesamt };
    }

    var ZICKZACK = [[60, 60], [90, 140], [130, 70], [170, 160], [200, 90], [260, 180], [300, 100], [330, 220]];

    // Ein vollständiger Stiftstrich: Endzustand gleich dem Neuzeichnen aus strokes
    function pixelGleichNachPointerup(werkzeug) {
        return function() {
            oeffnen();
            B.setTool('pen');
            // Vorhandene Striche inkl. Textmarker und Text, damit die Ebenen-Reihenfolge zählt
            zeiger('pointerdown', 3, 'pen', 40, 120);
            zeiger('pointermove', 3, 'pen', 360, 120);
            zeiger('pointerup', 3, 'pen', 360, 120);
            B.setTool('highlighter');
            zeiger('pointerdown', 3, 'pen', 40, 150);
            zeiger('pointermove', 3, 'pen', 360, 150);
            zeiger('pointerup', 3, 'pen', 360, 150);
            B.setTool(werkzeug);
            zeiger('pointerdown', 3, 'pen', ZICKZACK[0][0], ZICKZACK[0][1]);
            for (var i = 1; i < ZICKZACK.length; i++) zeiger('pointermove', 3, 'pen', ZICKZACK[i][0], ZICKZACK[i][1]);
            zeiger('pointerup', 3, 'pen', ZICKZACK[ZICKZACK.length - 1][0], ZICKZACK[ZICKZACK.length - 1][1]);
            var neu = bildDaten();
            if (B.strokes.length !== 3) return { ok: false, detail: B.strokes.length + ' Striche' };
            B.redrawAllStrokes(); // bisheriges Verfahren: alles aus strokes
            var alt = bildDaten();
            var d = pixelAbweichung(neu, alt);
            return d.anzahl === 0 ? true : { ok: false, detail: d.anzahl + ' Pixel abweichend (' + (d.anteil * 100).toFixed(3) + ' %)' };
        };
    }

    // Während des Strichs: inkrementelles Bild nahe am bisherigen Neuzeichnen
    function zwischenstandStift() {
        oeffnen();
        B.setTool('pen');
        zeiger('pointerdown', 3, 'pen', ZICKZACK[0][0], ZICKZACK[0][1]);
        for (var i = 1; i < ZICKZACK.length; i++) zeiger('pointermove', 3, 'pen', ZICKZACK[i][0], ZICKZACK[i][1]);
        var neu = bildDaten();
        B.redrawAllStrokes(); // bisheriges Verfahren mit currentStroke
        var alt = bildDaten();
        zeiger('pointerup', 3, 'pen', ZICKZACK[ZICKZACK.length - 1][0], ZICKZACK[ZICKZACK.length - 1][1]);
        var d = pixelAbweichung(neu, alt);
        return d.anteil <= 0.01 ? { ok: true, detail: d.anzahl + ' Pixel (' + (d.anteil * 100).toFixed(3) + ' %) Kantenglättung an Gelenken' } :
            { ok: false, detail: d.anzahl + ' Pixel (' + (d.anteil * 100).toFixed(3) + ' %)' };
    }

    function textmarkerUeberStiftSichtbar() {
        // Während eines Textmarker-Strichs bleibt ein darunter liegender
        // Stiftstrich sichtbar (Textmarker liegt beim Neuzeichnen UNTER den Stiftstrichen)
        oeffnen();
        B.setTool('pen');
        B.setColor('#000000');
        zeiger('pointerdown', 3, 'pen', 200, 40);
        zeiger('pointermove', 3, 'pen', 200, 300);
        zeiger('pointerup', 3, 'pen', 200, 300);
        B.setTool('highlighter');
        B.setColor('#ffff00');
        zeiger('pointerdown', 3, 'pen', 100, 150);
        zeiger('pointermove', 3, 'pen', 300, 150);
        var dpr = B.dpr || 1;
        var px = B.drawingCtx.getImageData(Math.round(200 * dpr), Math.round(150 * dpr), 1, 1).data;
        var mitte = B.drawingCtx.getImageData(Math.round(150 * dpr), Math.round(150 * dpr), 1, 1).data;
        zeiger('pointerup', 3, 'pen', 300, 150);
        if (mitte[3] === 0) return { ok: false, detail: 'Textmarker während des Strichs unsichtbar' };
        return (px[0] < 80 && px[1] < 80 && px[2] < 80) ? true : { ok: false, detail: 'Stiftstrich unter Textmarker überdeckt: rgba ' + Array.prototype.join.call(px, ',') };
    }

    // Erzwingt das Rastern der bisher gepufferten Zeichenbefehle (sonst misst
    // performance.now() nur das Aufzeichnen, nicht das Zeichnen).
    function abgleich() {
        B.drawingCtx.getImageData(0, 0, 1, 1);
    }

    function dauerProMove() {
        oeffnen();
        B.setTool('pen');
        // 500 vorhandene Striche à 20 Punkte
        var viele = [];
        for (var s = 0; s < 500; s++) {
            var pts = [];
            for (var p = 0; p < 20; p++) pts.push({ x: (s * 7 + p * 3) % 380, y: (s * 13 + p * 5) % 300 });
            viele.push({ tool: s % 10 === 0 ? 'highlighter' : 'pen', color: s % 10 === 0 ? 'rgba(255,255,180,1.0)' : '#000000', width: 3, points: pts });
        }
        B.strokes = viele;
        B.redrawAllStrokes();
        zeiger('pointerdown', 3, 'pen', 20, 20);
        var n = 200, t0 = performance.now();
        for (var i = 1; i <= n; i++) { zeiger('pointermove', 3, 'pen', 20 + (i % 300), 20 + (i * 7 % 280)); abgleich(); }
        var dauer = (performance.now() - t0) / n;
        zeiger('pointerup', 3, 'pen', 20, 20);
        // Vergleich: bisheriges Verfahren (Neuzeichnen je Ereignis)
        var t1 = performance.now();
        for (var j = 0; j < 20; j++) { B.redrawAllStrokes(); abgleich(); }
        var alt = (performance.now() - t1) / 20;
        var text = 'pointermove ' + dauer.toFixed(3) + ' ms (bisher je Ereignis ca. ' + alt.toFixed(2) + ' ms Neuzeichnen)';
        return dauer < 4 ? { ok: true, detail: text } : { ok: false, detail: text };
    }

    function textmarkerDauerProMove() {
        oeffnen();
        var viele = [];
        for (var s = 0; s < 500; s++) {
            var pts = [];
            for (var p = 0; p < 20; p++) pts.push({ x: (s * 7 + p * 3) % 380, y: (s * 13 + p * 5) % 300 });
            viele.push({ tool: 'pen', color: '#000000', width: 3, points: pts });
        }
        B.strokes = viele;
        B.redrawAllStrokes();
        B.setTool('highlighter');
        zeiger('pointerdown', 3, 'pen', 20, 20);
        var n = 200, t0 = performance.now();
        for (var i = 1; i <= n; i++) { zeiger('pointermove', 3, 'pen', 20 + (i % 300), 20 + (i * 7 % 280)); abgleich(); }
        var dauer = (performance.now() - t0) / n;
        zeiger('pointerup', 3, 'pen', 20, 20);
        var text = 'Textmarker-pointermove ' + dauer.toFixed(3) + ' ms';
        return dauer < 4 ? { ok: true, detail: text } : { ok: false, detail: text };
    }

    function zoomPanSegmente() {
        oeffnen();
        B.setTool('pen');
        B.zoom = 2;
        B.panX = -100;
        B.panY = -50;
        B.updateCanvasTransform();
        // Bildschirmabstand 160 px vom Canvas-Ursprung = 80 Canvas-Pixel bei Zoom 2
        zeiger('pointerdown', 3, 'pen', 200, 160);
        zeiger('pointermove', 3, 'pen', 300, 160);
        zeiger('pointermove', 3, 'pen', 400, 160);
        var dpr = B.dpr || 1;
        var waehrend = B.drawingCtx.getImageData(Math.round(175 * dpr), Math.round(80 * dpr), 1, 1).data[3];
        var daneben = B.drawingCtx.getImageData(Math.round(175 * dpr), Math.round(160 * dpr), 1, 1).data[3];
        zeiger('pointerup', 3, 'pen', 400, 160);
        var p = B.strokes[0] ? B.strokes[0].points : [];
        B.resetZoom();
        if (!(p.length === 3 && Math.abs(p[0].x - 100) < 0.01 && Math.abs(p[2].x - 200) < 0.01 && Math.abs(p[0].y - 80) < 0.01)) return { ok: false, detail: 'Punkte ' + JSON.stringify(p) };
        return (waehrend > 0 && daneben === 0) ? true : { ok: false, detail: 'Segment-Pixel alpha=' + waehrend + ', daneben=' + daneben };
    }

    function coalescedPunkte() {
        oeffnen();
        B.setTool('pen');
        var rect = B.drawingCanvas.getBoundingClientRect();
        zeiger('pointerdown', 3, 'pen', 100, 100);
        var teile = [[110, 100], [120, 100], [130, 100]].map(function(q) {
            return new PointerEvent('pointermove', { pointerId: 3, pointerType: 'pen', clientX: rect.left + q[0], clientY: rect.top + q[1], buttons: 1 });
        });
        var ev;
        try {
            ev = new PointerEvent('pointermove', { bubbles: true, cancelable: true, pointerId: 3, pointerType: 'pen', isPrimary: true,
                clientX: rect.left + 130, clientY: rect.top + 100, buttons: 1, coalescedEvents: teile });
        } catch (e) {
            return { ok: false, detail: 'coalescedEvents im Konstruktor nicht unterstützt' };
        }
        if (!ev.getCoalescedEvents || ev.getCoalescedEvents().length !== 3) {
            zeiger('pointerup', 3, 'pen', 130, 100);
            return { ok: true, detail: 'Browser übernimmt coalescedEvents synthetisch nicht - übersprungen' };
        }
        B.drawingCanvas.dispatchEvent(ev);
        zeiger('pointerup', 3, 'pen', 130, 100);
        var p = B.strokes[0] ? B.strokes[0].points : [];
        var xs = p.map(function(q) { return Math.round(q.x); }).join(',');
        return xs === '100,110,120,130' ? true : { ok: false, detail: 'Punkte x=' + xs };
    }

    function laufen() {
        pruefe('A: Ballen (klein) liegt auf, dann Stift', szenarioA(10));
        pruefe('A: Ballen (groß) liegt auf, dann Stift', szenarioA(40));
        pruefe('B: Stift zeichnet, Ballen (klein) setzt auf + bewegt', szenarioB(10));
        pruefe('B: Stift zeichnet, Ballen (groß) setzt auf + bewegt', szenarioB(40));
        pruefe('C: Ballen (groß) zuerst, hebt während Strich ab', szenarioC(40, true));
        pruefe('C: Ballen (klein) während Strich, hebt ab', szenarioC(10, false));
        pruefe('D: nach Stift zeichnet Finger nicht, zwei Finger zoomen', szenarioD);
        pruefe('E: ohne Stift Finger-Zeichnen und Zwei-Finger-Zoom', szenarioE);
        pruefe('Stift-Vorrang setzt sich je Öffnen zurück', stiftVorrangJeOeffnen);
        pruefe('Stift übernimmt laufenden Finger-Strich', stiftUebernimmtFingerstrich);
        pruefe('Rechts-/Mittelklick zeichnet nicht', rechtsklick);
        pruefe('Regression: Maus-Zeichnen', mausZeichnen);
        pruefe('Regression: pointerleave ohne Capture beendet Strich', mausVerlaesstCanvasOhneCapture);
        pruefe('Regression: Textmarker', textmarker);
        pruefe('Regression: Strich-Radierer', strichRadierer);
        pruefe('Regression: Punkt-Radierer', punktRadierer);
        pruefe('Stift-Vorrang: Finger radiert nicht', fingerRadiertNichtNachStift);
        pruefe('Regression: Text-Werkzeug', textWerkzeug);
        pruefe('Regression: Rückgängig', rueckgaengig);
        pruefe('Regression: Mehrseitigkeit', mehrseitig);
        pruefe('Regression: Speichern (localStorage)', speichernLaden);
        pruefe('Zustand nach destroy() zurückgesetzt', zustandNachDestroy);

        // AP-2.2
        pruefe('AP-2.2: Klasse cbd-board-offen an html, nach Schließen weg', klasseAnHtml);
        pruefe('AP-2.2: vorhandene Markierung beim Öffnen aufgehoben', markierungBeimOeffnenWeg);
        pruefe('AP-2.2: selectstart gesperrt (außer Eingabefelder), nach Schließen frei', selectstartGesperrt);
        pruefe('AP-2.2: touchstart/touchmove am Canvas gesperrt, Inhalt/Regler frei', touchAmCanvasGesperrt);
        pruefe('AP-2.2: Doppelklick im Overlay gesperrt (außer Eingabefelder)', doppelklickGesperrt);
        pruefe('AP-2.2: Regression Kontextmenü-Sperre #7063', kontextmenueWeiterGesperrt);
        pruefe('AP-2.2: CSS-Gestensperre (html/body/Overlay/Inhalt/Knöpfe)', cssGestensperre);
        pruefe('AP-2.2: Dialoge liegen über dem Overlay', dialogeUeberOverlay);
        pruefe('AP-2.2: Seitenknöpfe bei offener Tafel ausgeblendet', seitenknoepfeAusgeblendet);
        pruefe('AP-2.2: keine doppelten Listener nach 3x Öffnen/Schließen', keineDoppeltenListener);

        // AP-2.3
        pruefe('AP-2.3: Stift - Endzustand pixelgleich zum Neuzeichnen', pixelGleichNachPointerup('pen'));
        pruefe('AP-2.3: Textmarker - Endzustand pixelgleich zum Neuzeichnen', pixelGleichNachPointerup('highlighter'));
        pruefe('AP-2.3: Stift - Zwischenstand ≤ 1 % Abweichung', zwischenstandStift);
        pruefe('AP-2.3: Textmarker überdeckt Stiftstrich während des Zeichnens nicht', textmarkerUeberStiftSichtbar);
        pruefe('AP-2.3: 500 Striche - Stift-pointermove < 4 ms', dauerProMove);
        pruefe('AP-2.3: 500 Striche - Textmarker-pointermove < 4 ms', textmarkerDauerProMove);
        pruefe('AP-2.3: Zoom/Pan - Segmente an richtiger Stelle', zoomPanSegmente);
        pruefe('AP-2.3: getCoalescedEvents-Punkte übernommen', coalescedPunkte);

        // Zusatztests späterer APs hängen sich hier ein
        if (typeof window.__zusatzTests === 'function') {
            window.__zusatzTests({ B: B, pruefe: pruefe, zeiger: zeiger, oeffnen: oeffnen, schliessen: schliessen, ergebnisse: ergebnisse });
        }

        var tbody = document.querySelector('#ergebnis tbody');
        var ok = 0;
        ergebnisse.forEach(function(r) {
            if (r.ok) ok++;
            var tr = document.createElement('tr');
            tr.className = r.ok ? 'ok' : 'fehl';
            tr.innerHTML = '<td></td><td>' + (r.ok ? 'OK' : 'FEHLER') + '</td><td></td>';
            tr.children[0].textContent = r.name;
            tr.children[2].textContent = r.detail;
            tbody.appendChild(tr);
        });
        window.__zusammenfassung = ok + ' / ' + ergebnisse.length + ' bestanden';
        document.getElementById('zusammenfassung').textContent = window.__zusammenfassung;
    }

    window.__laufen = laufen;
    if (!window.__keinAutostart) {
        window.addEventListener('load', function() { setTimeout(laufen, 50); });
    }
})();
