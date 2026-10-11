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
