/*
 * Prüfungen AP-4.3 (PLAN-Rueckmeldungen-Klassenmodus-und-Tafel-Lasso.md,
 * Phase 4): board-mode.js arbeitet auf dem Elementmodell v1
 * (this.modell) statt auf strokes + Basisbild.
 *
 * Wird von tools/test-board-zeiger.html NACH test-board-zeiger.js geladen
 * und hängt sich über die Haken window.__zusatzTests (synchron) und
 * window.__zusatzTestsAsync (Promise) in deren Ablauf ein. Ergebnisse
 * landen in derselben Tabelle / window.__ergebnisse.
 *
 * Ohne Browser nicht lauffähig (Canvas, PointerEvents).
 */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var E = window.CBDBoardElemente;
    var CID = 'test-zeiger';
    var ctx; // Kontext aus test-board-zeiger.js (pruefe, zeiger, oeffnen, …)

    // ------------------------------------------------------------------
    // Hilfen
    // ------------------------------------------------------------------
    function el() {
        return (B.modell && B.modell.elemente) ? B.modell.elemente : null;
    }

    function nurTyp(typ) {
        var liste = el() || [];
        return liste.filter(function(e) { return e.typ === typ; });
    }

    function pixel(x, y) {
        var dpr = B.dpr || 1;
        return B.drawingCtx.getImageData(Math.round(x * dpr), Math.round(y * dpr), 1, 1).data;
    }

    function rgba(px) {
        return Array.prototype.join.call(px, ',');
    }

    function istDunkel(px) {
        return px[3] > 200 && px[0] < 80 && px[1] < 80 && px[2] < 80;
    }

    function strich(x1, y1, x2, y2, art, id) {
        var z = ctx.zeiger;
        art = art || 'pen';
        id = id || 3;
        z('pointerdown', id, art, x1, y1);
        z('pointermove', id, art, x2, y2);
        z('pointerup', id, art, x2, y2);
    }

    function tippen(x, y) {
        ctx.zeiger('pointerdown', 3, 'pen', x, y);
        ctx.zeiger('pointerup', 3, 'pen', x, y);
    }

    function strgZ() {
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true, cancelable: true }));
    }

    function text(x, y, inhalt) {
        B.setTool('text');
        ctx.zeiger('pointerdown', 1, 'mouse', x, y);
        var feld = document.querySelector('.cbd-board-text-input');
        if (!feld) throw new Error('kein Textfeld');
        feld.value = inhalt;
        feld.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    }

    function stapel() {
        return B.undoStapel ? B.undoStapel.length : -1;
    }

    // Basisbild (schwarzer senkrechter Strich bei x=200, y 40..300, wie
    // BASIS.schwarz in test-board-zeiger.js) als Bild-Element einsetzen -
    // synchron mit vorab geladenem Bild
    var BILD = null;

    function mitBild() {
        ctx.oeffnen();
        B.bildElementHinzufuegen(BILD.src, BILD);
        B.zeichneModell();
    }

    // ------------------------------------------------------------------
    // Synchrone Prüfungen
    // ------------------------------------------------------------------
    function modellNachOeffnen() {
        ctx.oeffnen();
        var m = B.modell;
        if (!m) return { ok: false, detail: 'kein this.modell' };
        var f = [];
        if (m.v !== 1) f.push('v=' + m.v);
        if (m.breite !== B.cssWidth || m.hoehe !== B.cssHeight) f.push('Maße ' + m.breite + 'x' + m.hoehe + ' statt ' + B.cssWidth + 'x' + B.cssHeight);
        if (!Array.isArray(m.elemente) || m.elemente.length !== 0) f.push('elemente nicht leer');
        if (stapel() !== 0) f.push('undoStapel=' + stapel());
        if (!E.pruefeModell(m)) f.push('pruefeModell lehnt ab');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function stiftWirdElement() {
        ctx.oeffnen();
        B.setTool('pen');
        B.setColor('#123456');
        ctx.zeiger('pointerdown', 1, 'mouse', 100, 100);
        ctx.zeiger('pointermove', 1, 'mouse', 150, 120);
        ctx.zeiger('pointermove', 1, 'mouse', 200, 140);
        ctx.zeiger('pointerup', 1, 'mouse', 200, 140);
        var l = el();
        if (!l) return { ok: false, detail: 'kein Modell' };
        if (l.length !== 1) return { ok: false, detail: l.length + ' Elemente' };
        var e = l[0];
        var f = [];
        if (e.typ !== 'strich' || e.werkzeug !== 'pen') f.push('typ/werkzeug ' + e.typ + '/' + e.werkzeug);
        if (e.farbe !== '#123456') f.push('farbe ' + e.farbe);
        if (e.breite !== B.penWidth) f.push('breite ' + e.breite);
        if (!/^e[0-9]+$/.test(e.id)) f.push('id ' + e.id);
        if (JSON.stringify(e.punkte) !== '[[100,100],[150,120],[200,140]]') f.push('punkte ' + JSON.stringify(e.punkte));
        if (B.currentStroke) f.push('currentStroke hängt');
        if (pixel(150, 120)[3] === 0) f.push('kein Pixel');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function markerWirdElement() {
        ctx.oeffnen();
        B.setTool('highlighter');
        B.setColor('#ffff00');
        strich(100, 100, 200, 100);
        var l = el() || [];
        if (l.length !== 1) return { ok: false, detail: l.length + ' Elemente' };
        var e = l[0];
        var f = [];
        if (e.werkzeug !== 'highlighter') f.push('werkzeug ' + e.werkzeug);
        if (e.farbe !== B.getLighterHighlightColor('#ffff00')) f.push('farbe ' + e.farbe);
        if (e.breite !== B.highlighterWidth) f.push('breite ' + e.breite);
        if (!E.pruefeModell(B.modell) || E.pruefeModell(B.modell).verworfen !== 0) f.push('Modell ungültig');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function textWirdElement() {
        ctx.oeffnen();
        B.setColor('#1565c0');
        text(120, 80, 'Hallo');
        var t = nurTyp('text');
        if (t.length !== 1) return { ok: false, detail: t.length + ' Text-Elemente' };
        var e = t[0];
        return (e.text === 'Hallo' && e.x === 120 && e.y === 80 && e.farbe === '#1565c0' && e.breite > 0) ? true :
            { ok: false, detail: JSON.stringify(e) };
    }

    function punktRadiererTeilt() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(40, 150, 360, 150);
        B.setTool('eraser-point');
        tippen(200, 150);
        var s = nurTyp('strich');
        var f = [];
        if (s.length !== 2) f.push(s.length + ' Striche statt 2');
        var r = B.lineWidth * 3;
        s.forEach(function(e) {
            e.punkte.forEach(function(p) {
                if (Math.sqrt((p[0] - 200) * (p[0] - 200) + (p[1] - 150) * (p[1] - 150)) < r - 0.001) f.push('Punkt im Radierkreis ' + p.join('/'));
            });
        });
        if (pixel(200, 150)[3] !== 0) f.push('Mitte nicht radiert (alpha ' + pixel(200, 150)[3] + ')');
        if (pixel(60, 150)[3] === 0) f.push('linker Teil fehlt');
        if (pixel(340, 150)[3] === 0) f.push('rechter Teil fehlt');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function undoNachPunktRadieren() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(40, 150, 360, 150);
        var vorher = JSON.stringify(el());
        B.setTool('eraser-point');
        tippen(200, 150);
        if ((el() || []).length !== 2) return { ok: false, detail: 'Radieren hat nicht geteilt' };
        strgZ();
        var nachher = JSON.stringify(el());
        if (nachher !== vorher) return { ok: false, detail: 'nach Strg+Z ' + nachher };
        return pixel(200, 150)[3] > 0 ? true : { ok: false, detail: 'Strich nach Strg+Z nicht wieder ganz gezeichnet' };
    }

    function punktRadierungBleibtNachStrichRadieren() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(40, 150, 360, 150);
        strich(40, 300, 360, 300);
        B.setTool('eraser-point');
        tippen(200, 150);
        B.setTool('eraser-stroke');
        tippen(100, 300);
        var s = nurTyp('strich');
        var f = [];
        if (s.length !== 2) f.push(s.length + ' Striche (erwartet: 2 Teile des oberen)');
        s.forEach(function(e) { if (e.punkte[0][1] !== 150) f.push('unterer Strich noch da'); });
        if (pixel(200, 150)[3] !== 0) f.push('Punkt-Radierung zurückgekehrt (alpha ' + pixel(200, 150)[3] + ')');
        if (pixel(100, 300)[3] !== 0) f.push('unterer Strich noch sichtbar');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function punktRadiererLoeschtText() {
        ctx.oeffnen();
        text(120, 80, 'Hallo');
        text(120, 300, 'Bleibt');
        B.setTool('eraser-point');
        tippen(130, 90);
        var t = nurTyp('text');
        return (t.length === 1 && t[0].text === 'Bleibt') ? true : { ok: false, detail: JSON.stringify(t.map(function(e) { return e.text; })) };
    }

    function punktRadiererSchnelleBewegung() {
        ctx.oeffnen();
        B.setTool('pen');
        for (var x = 100; x <= 300; x += 20) strich(x, 60, x, 240);
        B.setTool('eraser-point');
        ctx.zeiger('pointerdown', 3, 'pen', 60, 150);
        ctx.zeiger('pointermove', 3, 'pen', 340, 150); // ein Sprung über alle Striche
        ctx.zeiger('pointerup', 3, 'pen', 340, 150);
        var s = nurTyp('strich');
        var f = [];
        if (s.length !== 22) f.push(s.length + ' Teilstriche statt 22');
        s.forEach(function(e) {
            e.punkte.forEach(function(p) { if (Math.abs(p[1] - 150) < 30) f.push('Lücke bei x=' + p[0]); });
        });
        return f.length ? { ok: false, detail: f.slice(0, 5).join('; ') } : true;
    }

    function strichRadiererNieBild() {
        mitBild();
        B.setTool('pen');
        strich(300, 100, 400, 100);
        text(300, 250, 'Text');
        B.setTool('eraser-stroke');
        tippen(200, 150);         // auf die Bild-Schrift
        tippen(200, 60);
        var nachBild = (el() || []).map(function(e) { return e.typ; }).join(',');
        tippen(350, 100);         // Strich
        tippen(310, 260);         // Text
        var typen = (el() || []).map(function(e) { return e.typ; }).join(',');
        var f = [];
        if (nachBild.indexOf('bild') === -1) f.push('Bild-Element gelöscht');
        if (typen !== 'bild') f.push('übrig: ' + typen);
        if (!istDunkel(pixel(200, 150))) f.push('Bild nicht mehr sichtbar ' + rgba(pixel(200, 150)));
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function punktRadiererImBild() {
        mitBild();
        var alt = nurTyp('bild')[0];
        B.setTool('eraser-point');
        tippen(200, 150);
        var b = nurTyp('bild');
        var f = [];
        if (b.length !== 1) f.push(b.length + ' Bild-Elemente');
        else {
            if (b[0] === alt) f.push('Element nicht ersetzt');
            if (b[0].daten === alt.daten) f.push('daten unverändert');
            if (b[0].daten.indexOf('data:image/png;base64,') !== 0) f.push('daten kein PNG');
        }
        if (pixel(200, 150)[3] !== 0) f.push('200/150 nicht radiert ' + rgba(pixel(200, 150)));
        if (!istDunkel(pixel(200, 80))) f.push('200/80 mitradiert ' + rgba(pixel(200, 80)));
        strgZ();
        if (!istDunkel(pixel(200, 150))) f.push('nach Strg+Z nicht zurück ' + rgba(pixel(200, 150)));
        if (nurTyp('bild')[0] !== alt) f.push('Strg+Z stellt altes Element nicht her');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function undoJeSchritt() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(40, 100, 360, 100);       // 1
        text(40, 300, 'Wort');           // 2
        B.setTool('pen');
        strich(40, 200, 360, 200);       // 3
        B.setTool('eraser-point');
        tippen(200, 200);                // 4: Radiergeste
        B.showClearConfirm();            // 5: Zeichnung löschen
        var knopf = document.querySelector('.cbd-board-confirm-delete');
        if (!knopf) return { ok: false, detail: 'kein Löschen-Knopf' };
        knopf.click();
        var folge = [(el() || []).length];
        for (var i = 0; i < 6; i++) { B.undo(); folge.push((el() || []).length); }
        var soll = '0,4,3,2,1,0,0';
        return folge.join(',') === soll ? true : { ok: false, detail: 'Elementzahl je Undo ' + folge.join(',') + ' statt ' + soll };
    }

    function undoHoechstens50() {
        ctx.oeffnen();
        B.setTool('pen');
        for (var i = 0; i < 55; i++) strich(20 + i * 5, 50, 20 + i * 5, 90);
        var f = [];
        if (stapel() !== 50) f.push('Stapel ' + stapel());
        for (var j = 0; j < 50; j++) B.undo();
        if ((el() || []).length !== 5) f.push('nach 50x Undo ' + (el() || []).length + ' statt 5');
        B.undo();
        if ((el() || []).length !== 5) f.push('51. Undo änderte etwas');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function radierenOhneTrefferKeinSchritt() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(40, 100, 100, 100);
        var vorher = stapel();
        B.setTool('eraser-point');
        tippen(300, 300);
        B.setTool('eraser-stroke');
        tippen(300, 300);
        return (vorher === 1 && stapel() === 1) ? true : { ok: false, detail: 'Stapel vorher ' + vorher + ', nachher ' + stapel() };
    }

    function verworfenerFingerstrichKeinSchritt() {
        ctx.oeffnen();
        ctx.zeiger('pointerdown', 10, 'touch', 100, 200);
        ctx.zeiger('pointermove', 10, 'touch', 120, 200);
        ctx.zeiger('pointerdown', 3, 'pen', 300, 100);
        ctx.zeiger('pointermove', 3, 'pen', 310, 100);
        ctx.zeiger('pointerup', 10, 'touch', 120, 200);
        ctx.zeiger('pointerup', 3, 'pen', 310, 100);
        return ((el() || []).length === 1 && stapel() === 1) ? true : { ok: false, detail: 'Elemente ' + (el() || []).length + ', Stapel ' + stapel() };
    }

    function zeichenreihenfolge() {
        mitBild();
        B.setTool('highlighter');
        B.setColor('#ffff00');
        strich(100, 150, 300, 150);
        B.setTool('pen');
        B.setColor('#d00000');
        strich(250, 40, 250, 300);
        B.setTool('pen');
        B.setColor('#0000ff');
        B.setLineWidth(20); // Schrift 96 px
        text(170, 160, 'WWWW\nWWWW');
        var f = [];
        if (!istDunkel(pixel(200, 150))) f.push('Bild nicht über Textmarker ' + rgba(pixel(200, 150)));
        var rot = pixel(250, 150);
        if (!(rot[0] > 150 && rot[1] < 80 && rot[2] < 80)) f.push('Stift nicht über Textmarker ' + rgba(rot));
        var gelb = pixel(150, 150);
        if (!(gelb[3] === 255 && gelb[0] > 200 && gelb[1] > 200)) f.push('Textmarker fehlt ' + rgba(gelb));
        // Text liegt über Stift: irgendwo auf der Stiftlinie (x=250) im Textbereich blau
        var blauAufStift = false;
        for (var y = 165; y < 380; y++) {
            var p = pixel(250, y);
            if (p[2] > 150 && p[0] < 100) { blauAufStift = true; break; }
        }
        if (!blauAufStift) f.push('Text liegt nicht über dem Stiftstrich');
        B.setLineWidth(3);
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function resizeAusModell() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(40, 100, 200, 100);
        var b = B.modell ? B.modell.breite : null;
        B.drawingCtx.fillStyle = '#ff00ff';
        B.drawingCtx.fillRect(300, 250, 20, 20); // nicht im Modell
        B.resizeAllCanvases();
        var f = [];
        if (pixel(310, 260)[3] !== 0) f.push('Fremdpixel überlebt Resize (nicht aus dem Modell gezeichnet)');
        if (pixel(120, 100)[3] === 0) f.push('Strich nach Resize weg');
        if (!B.modell || B.modell.breite !== b) f.push('modell.breite verändert');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function leeresModellGiltAlsLeer() {
        ctx.oeffnen();
        B.drawingCtx.fillStyle = '#ff00ff';
        B.drawingCtx.fillRect(300, 250, 20, 20); // Pixel, aber keine Elemente
        B.saveDrawing();
        var gespeichert = null;
        try { gespeichert = localStorage.getItem('cbd-board-' + CID); } catch (e) {}
        return gespeichert === null ? true : { ok: false, detail: 'leere Elementliste als Zeichnung gespeichert' };
    }

    function keinStrokesMehr() {
        ctx.oeffnen();
        var f = [];
        if (B.hasOwnProperty('strokes')) f.push('this.strokes existiert noch');
        if (B.hasOwnProperty('baseImageObj')) f.push('this.baseImageObj existiert noch');
        if (typeof B.zeichneModell !== 'function') f.push('zeichneModell fehlt');
        if (typeof B.redrawAllStrokes !== 'function') f.push('Alias redrawAllStrokes fehlt');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    // ------------------------------------------------------------------
    // Asynchrone Prüfungen (echte Ladewege)
    // ------------------------------------------------------------------
    function bildGeladen() {
        return typeof B.bildElementeGeladen === 'function' && B.bildElementeGeladen() && nurTyp('bild').length > 0;
    }

    function oeffnenMitGespeichert(url) {
        if (B.overlay) B.destroy();
        ctx.speicherLeeren();
        try { localStorage.setItem('cbd-board-' + CID, url); } catch (e) {}
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        B.setTool('pen');
    }

    function wiederGeoeffnetEinBild() {
        oeffnenMitGespeichert(BILD.src);
        return ctx.warteAuf(bildGeladen, 3000).then(function() {
            var l = el();
            if (l.length !== 1) return { ok: false, detail: l.length + ' Elemente' };
            var e = l[0];
            var f = [];
            if (e.typ !== 'bild') f.push('typ ' + e.typ);
            if (e.daten !== BILD.src) f.push('daten weichen ab');
            if (e.x !== 0 || e.y !== 0 || e.b !== B.cssWidth || e.h !== B.cssHeight) f.push('Rahmen ' + [e.x, e.y, e.b, e.h].join('/'));
            if (!istDunkel(pixel(200, 150))) f.push('nicht gezeichnet ' + rgba(pixel(200, 150)));
            if (stapel() !== 0) f.push('Laden legt Undo-Schritt an');
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    function strichVorDemLadenBleibt() {
        oeffnenMitGespeichert(BILD.src);
        // sofort zeichnen, bevor das Bild geladen ist
        strich(300, 100, 400, 100);
        return ctx.warteAuf(bildGeladen, 3000).then(function() {
            var typen = el().map(function(e) { return e.typ; }).join(',');
            if (typen !== 'bild,strich') return { ok: false, detail: 'Elemente ' + typen };
            B.undo();
            var danach = el().map(function(e) { return e.typ; }).join(',');
            if (danach !== 'bild') return { ok: false, detail: 'nach Undo ' + danach };
            return istDunkel(pixel(200, 150)) ? true : { ok: false, detail: 'Bild nach Undo weg' };
        });
    }

    function radierungImBildBleibt() {
        oeffnenMitGespeichert(BILD.src);
        return ctx.warteAuf(bildGeladen, 3000).then(function() {
            B.setTool('eraser-point');
            tippen(200, 150);
            B.saveDrawing();
            B.destroy();
            B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
            return ctx.warteAuf(bildGeladen, 3000);
        }).then(function() {
            var f = [];
            if (pixel(200, 150)[3] !== 0) f.push('Radierung nach Wiederöffnen weg ' + rgba(pixel(200, 150)));
            if (!istDunkel(pixel(200, 80))) f.push('Rest des Bilds fehlt ' + rgba(pixel(200, 80)));
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    function seitenwechselBildElement() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(200, 40, 200, 300);
        B.addPage();
        var f = [];
        if ((el() || [1]).length !== 0) f.push('neue Seite nicht leer');
        if (stapel() !== 0) f.push('Undo-Stapel nach addPage ' + stapel());
        strich(100, 100, 150, 100);
        B.goToPage(0);
        if (stapel() !== 0) f.push('Undo-Stapel nach goToPage ' + stapel());
        return ctx.warteAuf(bildGeladen, 3000).then(function() {
            var l = el();
            if (l.length !== 1 || l[0].typ !== 'bild') f.push('Seite 1: ' + l.map(function(e) { return e.typ; }).join(','));
            if (!istDunkel(pixel(200, 150))) f.push('Seite 1 nicht gezeichnet');
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    // ------------------------------------------------------------------
    // Einhängen
    // ------------------------------------------------------------------
    window.__zusatzTests = function(c) {
        ctx = c;
        BILD = c.basis.schwarz;
        var p = c.pruefe;
        p('AP-4.3: Modell v1 nach dem Öffnen (Maße, leer, Undo-Stapel)', modellNachOeffnen);
        p('AP-4.3: this.strokes/baseImageObj ersetzt, zeichneModell + Alias', keinStrokesMehr);
        p('AP-4.3: Stiftstrich wird Strich-Element', stiftWirdElement);
        p('AP-4.3: Textmarker wird Strich-Element mit Markerfarbe', markerWirdElement);
        p('AP-4.3: Text wird Text-Element', textWirdElement);
        p('AP-4.3: Punkt-Radierer teilt Strich in zwei', punktRadiererTeilt);
        p('AP-4.3: Strg+Z macht Punkt-Radierung rückgängig', undoNachPunktRadieren);
        p('AP-4.3: Punkt-Radierung bleibt nach Strich-Radieren (Altfehler)', punktRadierungBleibtNachStrichRadieren);
        p('AP-4.3: Punkt-Radierer löscht Text unter dem Punkt', punktRadiererLoeschtText);
        p('AP-4.3: Punkt-Radierer lückenlos bei schneller Bewegung', punktRadiererSchnelleBewegung);
        p('AP-4.3: Strich-Radierer löscht Strich und Text, nie Bild-Element', strichRadiererNieBild);
        p('AP-4.3: Punkt-Radierer radiert im Bild-Element, Strg+Z stellt her', punktRadiererImBild);
        p('AP-4.3: Undo je Schritt (Strich, Text, Radieren, Löschen)', undoJeSchritt);
        p('AP-4.3: Undo-Stapel höchstens 50', undoHoechstens50);
        p('AP-4.3: Radiergeste ohne Treffer legt keinen Undo-Schritt an', radierenOhneTrefferKeinSchritt);
        p('AP-4.3: verworfener Finger-Strich legt keinen Undo-Schritt an', verworfenerFingerstrichKeinSchritt);
        p('AP-4.3: Zeichenreihenfolge Textmarker -> Bild -> Stift -> Text', zeichenreihenfolge);
        p('AP-4.3: Resize zeichnet aus dem Modell, Maße bleiben', resizeAusModell);
        p('AP-4.3: leere Elementliste gilt beim Speichern als leer', leeresModellGiltAlsLeer);
    };

    window.__zusatzTestsAsync = function(c) {
        ctx = c;
        var pa = c.pruefeAsync;
        BILD = c.basis.schwarz;
        return Promise.resolve()
            .then(function() { return pa('AP-4.3: wieder geöffnete Notiz ist EIN Bild-Element', wiederGeoeffnetEinBild); })
            .then(function() { return pa('AP-4.3: Strich vor dem Laden bleibt, Undo lässt Bild stehen', strichVorDemLadenBleibt); })
            .then(function() { return pa('AP-4.3: Punkt-Radierung im Bild bleibt nach Schließen/Öffnen', radierungImBildBleibt); })
            .then(function() { return pa('AP-4.3: Seitenwechsel - Bild-Element, Undo-Stapel geleert', seitenwechselBildElement); });
    };
})();
