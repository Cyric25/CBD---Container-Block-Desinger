/*
 * Prüfungen AP-5.1 (PLAN-Rueckmeldungen-Klassenmodus-und-Tafel-Lasso.md,
 * Phase 5): Lasso-Werkzeug - Auswahl und Auswahlanzeige.
 *
 * Abgedeckt (Akzeptanzkriterien AP-5.1 + Regeln E14):
 *   - Lasso-Knopf nach dem Text-Werkzeug, Breiten-/Transparenzregler weg,
 *     Breite der anderen Werkzeuge bleibt unberührt
 *   - vierte Canvas-Ebene canvas.cbd-board-canvas-auswahl (z-index 4,
 *     pointer-events none, gleiche Pixeldichte wie die Zeichenebene)
 *   - Auswahlregel: zwei Striche + ein Text gewählt, ein nur zu rund einem
 *     Drittel umrandeter Strich NICHT; Rahmen (+ 8 px) umschließt alle drei
 *   - Bild-Element (alte Tafel): Umranden seiner Mitte wählt es
 *   - leere Auswahl zeigt nichts; Werkzeugwechsel, "Auswahl aufheben" und
 *     ESC heben die Auswahl auf; ESC ohne Auswahl schließt wie bisher
 *   - gespeichertes PNG ohne Lassolinie und Rahmen; zeichneModell() lässt
 *     die Auswahl-Ebene unberührt
 *   - Zoom 150 %: Auswahl und Anzeige stimmen mit den Elementen überein
 *   - Kontextmenü-Sperre beim Lasso-Halten, Finger/Handballen/Pinch
 *
 * AP-5.2 (Verschieben, Löschen, Rückgängig, Speichern), Abschnitt unten:
 *   Ziehen im Auswahlrahmen verschiebt nur Gewähltes (aus der Momentaufnahme,
 *   nicht kumulativ gerundet, Zoom herausgerechnet), 0 px ohne Undo-Eintrag,
 *   Löschen per Knopf und Entf/Rücktaste (nicht aus Eingabefeldern), Undo je
 *   einzeln, Bild-Element, Finger/Maus/Pinch, lokal und Server speichern +
 *   Wiederöffnen an neuer Stelle, Zwischenstand vor dem Abheben.
 *
 * Verträge, auf die sich diese Prüfungen stützen (Plan AP-5.1):
 *   B.auswahl           Array der gewählten Element-IDs
 *   B.auswahlCanvas     die vierte Ebene (canvas.cbd-board-canvas-auswahl)
 *   B.auswahlAufheben() hebt die Auswahl auf (wie der Knopf)
 *   .cbd-board-auswahl-leiste mit den Knöpfen "Löschen" und
 *                       "Auswahl aufheben" (Text des Knopfs)
 *   Werkzeug 'lasso' über setTool('lasso') bzw. den Knopf data-tool="lasso"
 * Rahmen und Lassolinie werden nur über die Pixel der Auswahl-Ebene
 * geprüft, nicht über interne Hilfsfunktionen.
 *
 * Wird von tools/test-board-zeiger.html NACH test-board-speichern.js
 * geladen und hängt sich an die Haken window.__zusatzTests /
 * window.__zusatzTestsAsync an (die vorhandenen Haken laufen zuerst).
 * Ohne Browser nicht lauffähig (Canvas, PointerEvents, localStorage).
 */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var E = window.CBDBoardElemente;
    var CID = 'test-zeiger';
    var SCHLUESSEL = 'cbd-board-' + CID;
    var ctx;

    var vorherSync = window.__zusatzTests;
    var vorherAsync = window.__zusatzTestsAsync;

    // ------------------------------------------------------------------
    // Hilfen
    // ------------------------------------------------------------------
    function el() {
        return (B.modell && B.modell.elemente) ? B.modell.elemente : [];
    }

    function idsVon(typ) {
        return el().filter(function(e) { return !typ || e.typ === typ; }).map(function(e) { return e.id; });
    }

    function sortiert(ids) {
        return (ids || []).slice().sort().join(',');
    }

    function mass(text, px) {
        B.drawingCtx.font = px + 'px sans-serif';
        return B.drawingCtx.measureText(text).width;
    }

    /** Zeiger in ELEMENT-Koordinaten (Zoom wird hier eingerechnet). */
    function z(typ, id, art, x, y, extra) {
        var f = B.zoom || 1;
        return ctx.zeiger(typ, id, art, x * f, y * f, extra);
    }

    /** Strich mit gleichmäßig verteilten Punkten (Abstand schritt). */
    function strich(x1, y1, x2, y2, schritt) {
        B.setTool('pen');
        B.setColor('#000000');
        var n = Math.max(1, Math.round(Math.max(Math.abs(x2 - x1), Math.abs(y2 - y1)) / (schritt || 10)));
        z('pointerdown', 3, 'pen', x1, y1);
        for (var i = 1; i <= n; i++) {
            z('pointermove', 3, 'pen', x1 + (x2 - x1) * i / n, y1 + (y2 - y1) * i / n);
        }
        z('pointerup', 3, 'pen', x2, y2);
    }

    function text(x, y, inhalt) {
        B.setTool('text');
        z('pointerdown', 1, 'mouse', x, y);
        var feld = document.querySelector('.cbd-board-text-input');
        if (!feld) throw new Error('kein Textfeld');
        feld.value = inhalt;
        feld.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    }

    /** Rechteck als Lasso-Polygon mit mehreren Punkten je Kante. */
    function rechteck(x1, y1, x2, y2) {
        var p = [], i, n = 8;
        for (i = 0; i < n; i++) p.push([x1 + (x2 - x1) * i / n, y1]);
        for (i = 0; i < n; i++) p.push([x2, y1 + (y2 - y1) * i / n]);
        for (i = 0; i < n; i++) p.push([x2 - (x2 - x1) * i / n, y2]);
        for (i = 0; i < n; i++) p.push([x1, y2 - (y2 - y1) * i / n]);
        p.push([x1, y1 + 2]);
        return p;
    }

    /**
     * Lasso ziehen. optionen.art/id: Zeigerart (Standard Stift, ID 7);
     * optionen.ohneAbheben: pointerup weglassen; optionen.mitte(i): Rückruf
     * nach dem i-ten Punkt (für Zwischenprüfungen).
     */
    function lasso(punkte, optionen) {
        optionen = optionen || {};
        var art = optionen.art || 'pen', id = optionen.id || 7, extra = optionen.extra;
        B.setTool('lasso');
        z('pointerdown', id, art, punkte[0][0], punkte[0][1], extra);
        for (var i = 1; i < punkte.length; i++) {
            z('pointermove', id, art, punkte[i][0], punkte[i][1], extra);
            if (optionen.mitte) optionen.mitte(i);
        }
        if (!optionen.ohneAbheben) {
            var l = punkte[punkte.length - 1];
            z('pointerup', id, art, l[0], l[1], extra);
        }
    }

    /** Bounding-Box (CSS-Pixel der Zeichenfläche) aller sichtbaren Pixel einer Ebene, oder null. */
    function pixelBox(canvas) {
        var c = canvas.getContext('2d');
        var w = canvas.width, h = canvas.height;
        var d = c.getImageData(0, 0, w, h).data;
        var minX = Infinity, minY = Infinity, maxX = -1, maxY = -1, n = 0;
        for (var y = 0; y < h; y++) {
            for (var x = 0; x < w; x++) {
                if (d[(y * w + x) * 4 + 3] > 0) {
                    n++;
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                }
            }
        }
        if (!n) return null;
        var dpr = B.dpr || 1;
        return { x: minX / dpr, y: minY / dpr, r: (maxX + 1) / dpr, u: (maxY + 1) / dpr, n: n };
    }

    function ebeneLeer(canvas) {
        return pixelBox(canvas) === null;
    }

    function ebenenDaten(canvas) {
        var c = canvas.getContext('2d');
        return c.getImageData(0, 0, canvas.width, canvas.height).data;
    }

    function gleicheDaten(a, b) {
        if (a.length !== b.length) return false;
        for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
        return true;
    }

    /** Vereinigung der Element-Rahmen (ohne Zuschlag) der angegebenen IDs. */
    function vereinigung(ids) {
        var r = null;
        el().forEach(function(e) {
            if (ids.indexOf(e.id) === -1) return;
            var q = E.rahmen(e, mass);
            if (!r) { r = { x: q.x, y: q.y, r: q.x + q.b, u: q.y + q.h }; return; }
            r.x = Math.min(r.x, q.x); r.y = Math.min(r.y, q.y);
            r.r = Math.max(r.r, q.x + q.b); r.u = Math.max(r.u, q.y + q.h);
        });
        return r;
    }

    /** Rahmen der Auswahl-Ebene umschließt die Vereinigung mit rund 8 px Zuschlag. */
    function rahmenPasst(box, u, f) {
        if (!box) { f.push('kein Rahmen auf der Auswahl-Ebene'); return; }
        if (!u) { f.push('keine Vereinigung'); return; }
        // Zuschlag 8 px (+/- Linienbreite und Kantenglättung): 5..12 px außen
        var links = u.x - box.x, oben = u.y - box.y, rechts = box.r - u.r, unten = box.u - u.u;
        [['links', links], ['oben', oben], ['rechts', rechts], ['unten', unten]].forEach(function(s) {
            if (s[1] < 5 || s[1] > 12) f.push('Rahmenabstand ' + s[0] + ' ' + s[1].toFixed(1) + ' px (erwartet ~8)');
        });
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

    function knopf(beschriftung) {
        var l = leiste();
        if (!l) return null;
        var k = l.querySelectorAll('button');
        for (var i = 0; i < k.length; i++) {
            if (k[i].textContent.replace(/\s+/g, ' ').trim() === beschriftung) return k[i];
        }
        return null;
    }

    function ereignis(ziel, typ) {
        var ev = new MouseEvent(typ, { bubbles: true, cancelable: true, composed: true });
        ziel.dispatchEvent(ev);
        return ev.defaultPrevented;
    }

    function taste(key) {
        var ev = new KeyboardEvent('keydown', { key: key, bubbles: true, cancelable: true });
        document.dispatchEvent(ev);
        return ev;
    }

    function ergebnis(f) {
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    /** Testzeichnung der Auswahlregel: A, B (ganz innen), Text, D (rund ein Drittel innen). */
    var LASSO_REGEL = null;
    function regelZeichnung() {
        strich(40, 100, 200, 100);      // A
        strich(40, 160, 200, 160);      // B
        text(60, 210, 'Hallo Welt');     // Text
        strich(30, 280, 620, 280);      // D: 60 Punkte, 22 davon in x <= 240 (37 %)
        B.setTool('pen');
        var ids = idsVon();
        if (ids.length !== 4) throw new Error('Testzeichnung: ' + ids.length + ' statt 4 Elemente');
        LASSO_REGEL = rechteck(20, 60, 240, 300);
        return { a: ids[0], b: ids[1], t: ids[2], d: ids[3] };
    }

    // ------------------------------------------------------------------
    // Prüfungen (synchron)
    // ------------------------------------------------------------------
    function knopfUndRegler() {
        ctx.oeffnen();
        var f = [];
        var k = B.overlay.querySelector('.cbd-board-tool[data-tool="lasso"]');
        if (!k) return { ok: false, detail: 'kein Knopf data-tool="lasso"' };
        var textKnopf = B.overlay.querySelector('.cbd-board-tool[data-tool="text"]');
        if (!textKnopf || textKnopf.nextElementSibling !== k) f.push('Lasso-Knopf steht nicht direkt nach dem Text-Werkzeug');
        if (k.getAttribute('title') !== 'Auswählen (Lasso)') f.push('title "' + k.getAttribute('title') + '"');
        if (!k.querySelector('.dashicons.dashicons-image-crop')) f.push('Dashicon dashicons-image-crop fehlt');
        if (k.tagName !== 'BUTTON') f.push('kein button');

        B.setLineWidth(7); // Stift 7 px
        var breite = B.overlay.querySelector('.cbd-board-width');
        var anzeige = B.overlay.querySelector('.cbd-board-width-display');
        var deck = B.overlay.querySelector('.cbd-board-opacity-control');
        k.click();
        if (B.currentTool !== 'lasso') f.push('Werkzeug nach Klick ' + B.currentTool);
        if (!k.classList.contains('active')) f.push('Knopf nicht aktiv');
        if (sichtbar(breite)) f.push('Breitenregler sichtbar');
        if (sichtbar(anzeige)) f.push('Breitenanzeige sichtbar');
        if (sichtbar(deck)) f.push('Transparenzregler sichtbar');
        if (B.lineWidth !== 7) f.push('lineWidth bei Lasso ' + B.lineWidth + ' (loadToolWidth überschreibt)');
        if (B.penWidth !== 7) f.push('penWidth ' + B.penWidth);
        var cur = getComputedStyle(B.drawingCanvas).cursor;
        if (cur !== 'crosshair') f.push('Cursor ' + cur);

        B.overlay.querySelector('.cbd-board-tool[data-tool="highlighter"]').click();
        if (!sichtbar(breite)) f.push('Breitenregler beim Textmarker nicht wieder sichtbar');
        if (!sichtbar(deck)) f.push('Transparenzregler beim Textmarker nicht sichtbar');
        B.overlay.querySelector('.cbd-board-tool[data-tool="pen"]').click();
        if (!sichtbar(breite)) f.push('Breitenregler beim Stift nicht wieder sichtbar');
        if (B.lineWidth !== 7) f.push('Stiftbreite nach Lasso ' + B.lineWidth);
        B.setLineWidth(3);
        return ergebnis(f);
    }

    function auswahlEbene() {
        ctx.oeffnen();
        var f = [];
        var c = B.overlay.querySelector('.cbd-board-canvas-container canvas.cbd-board-canvas.cbd-board-canvas-auswahl');
        if (!c) return { ok: false, detail: 'keine canvas.cbd-board-canvas.cbd-board-canvas-auswahl im Container' };
        if (B.auswahlCanvas !== c) f.push('B.auswahlCanvas ist nicht diese Ebene');
        if (c.previousElementSibling !== B.drawingCanvas) f.push('Auswahl-Ebene liegt nicht direkt nach der Zeichenebene');
        var cs = getComputedStyle(c);
        if (cs.pointerEvents !== 'none') f.push('pointer-events ' + cs.pointerEvents);
        if (cs.zIndex !== '4') f.push('z-index ' + cs.zIndex);
        if (c.width !== B.drawingCanvas.width || c.height !== B.drawingCanvas.height) {
            f.push('Pixelmaße ' + c.width + 'x' + c.height + ' statt ' + B.drawingCanvas.width + 'x' + B.drawingCanvas.height);
        }
        var a = c.getBoundingClientRect(), d = B.drawingCanvas.getBoundingClientRect();
        if (Math.abs(a.left - d.left) > 0.5 || Math.abs(a.top - d.top) > 0.5 || Math.abs(a.width - d.width) > 0.5 || Math.abs(a.height - d.height) > 0.5) {
            f.push('Lage weicht von der Zeichenebene ab');
        }
        // Größenänderung behandelt die Ebene mit
        B.resizeAllCanvases();
        if (c.width !== B.drawingCanvas.width || c.height !== B.drawingCanvas.height) f.push('nach resizeAllCanvases andere Maße');
        // Der Zeichen-Canvas bleibt Ziel der Zeigerereignisse
        var r = B.drawingCanvas.getBoundingClientRect();
        var oben = document.elementFromPoint(r.left + 50, r.top + 50);
        if (oben !== B.drawingCanvas) f.push('Ereignisziel über der Fläche: ' + (oben ? oben.className : 'null'));
        return ergebnis(f);
    }

    function lassolinieWaehrendZiehen() {
        ctx.oeffnen();
        strich(40, 100, 200, 100);
        var f = [];
        var vorher = ebenenDaten(B.drawingCanvas);
        var modellVorher = JSON.stringify(el());
        var undoVorher = B.undoStapel.length;
        var gesehen = false, zeichenebeneGleich = true;
        lasso(rechteck(20, 60, 240, 140), {
            ohneAbheben: true,
            mitte: function(i) {
                if (i === 12) {
                    gesehen = !ebeneLeer(B.auswahlCanvas);
                    zeichenebeneGleich = gleicheDaten(vorher, ebenenDaten(B.drawingCanvas));
                }
            }
        });
        if (!gesehen) f.push('Lassolinie während des Ziehens nicht auf der Auswahl-Ebene');
        if (!zeichenebeneGleich) f.push('Lasso malt auf die Zeichenebene');
        // gestrichelt: entlang der oberen Kante gibt es Lücken
        var dpr = B.dpr || 1;
        var zeile = B.auswahlCanvas.getContext('2d').getImageData(Math.round(40 * dpr), Math.round(60 * dpr), Math.round(150 * dpr), 1).data;
        var an = 0, aus = 0;
        for (var i = 3; i < zeile.length; i += 4) { if (zeile[i] > 0) an++; else aus++; }
        if (!(an > 0 && aus > 0)) f.push('Lassolinie nicht gestrichelt (an ' + an + ', aus ' + aus + ')');
        z('pointerup', 7, 'pen', 20, 62);
        if (JSON.stringify(el()) !== modellVorher) f.push('Lasso verändert das Modell');
        if (B.undoStapel.length !== undoVorher) f.push('Lasso legt Undo-Schritt an');
        if (!gleicheDaten(vorher, ebenenDaten(B.drawingCanvas))) f.push('Zeichenebene nach dem Lasso verändert');
        return ergebnis(f);
    }

    function auswahlregel() {
        ctx.oeffnen();
        var f = [];
        var ids = regelZeichnung();
        var undoVorher = B.undoStapel.length;
        lasso(LASSO_REGEL);
        var erwartet = sortiert([ids.a, ids.b, ids.t]);
        if (sortiert(B.auswahl) !== erwartet) f.push('Auswahl ' + sortiert(B.auswahl) + ' statt ' + erwartet + ' (D=' + ids.d + ')');
        if ((B.auswahl || []).indexOf(ids.d) !== -1) f.push('zu einem Drittel umrandeter Strich gewählt');
        rahmenPasst(pixelBox(B.auswahlCanvas), vereinigung([ids.a, ids.b, ids.t]), f);
        var box = pixelBox(B.auswahlCanvas);
        if (box && box.r > 320) f.push('Rahmen reicht bis x ' + box.r.toFixed(0) + ' (Strich D einbezogen?)');
        if (B.undoStapel.length !== undoVorher) f.push('Auswahl legt Undo-Schritt an');
        if (!sichtbar(leiste())) f.push('Auswahlleiste nicht sichtbar');
        if (!knopf('Löschen')) f.push('Knopf "Löschen" fehlt');
        if (!knopf('Auswahl aufheben')) f.push('Knopf "Auswahl aufheben" fehlt');
        return ergebnis(f);
    }

    function auswahlAufhebenKnopf() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        lasso(LASSO_REGEL);
        if (!(B.auswahl && B.auswahl.length === 3)) return { ok: false, detail: 'Vorbedingung: Auswahl ' + sortiert(B.auswahl) };
        var k = knopf('Auswahl aufheben');
        if (!k) return { ok: false, detail: 'Knopf fehlt' };
        k.click();
        if (B.auswahl.length !== 0) f.push('Auswahl nach Klick ' + sortiert(B.auswahl));
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Rahmen bleibt stehen');
        if (sichtbar(leiste())) f.push('Leiste bleibt sichtbar');
        if (B.currentTool !== 'lasso') f.push('Werkzeug ' + B.currentTool);
        if (!B.overlay) f.push('Tafel geschlossen');
        return ergebnis(f);
    }

    function leereAuswahl() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        lasso(LASSO_REGEL);
        if (!(B.auswahl && B.auswahl.length === 3)) f.push('Vorbedingung: Auswahl ' + sortiert(B.auswahl));
        // neues Lasso im leeren Bereich: alte Auswahl weg, nichts angezeigt
        lasso(rechteck(400, 60, 600, 200));
        if (!B.auswahl || B.auswahl.length !== 0) f.push('Auswahl ' + sortiert(B.auswahl));
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Auswahl-Ebene nicht leer (Linie/Rahmen)');
        if (sichtbar(leiste())) f.push('Leiste sichtbar');
        // Antippen mit dem Lasso (weniger als drei Punkte) wählt nichts
        B.setTool('lasso');
        z('pointerdown', 7, 'pen', 100, 100);
        z('pointerup', 7, 'pen', 100, 100);
        if (!B.auswahl || B.auswahl.length !== 0) f.push('Antippen wählt ' + sortiert(B.auswahl));
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Antippen hinterlässt Pixel');
        return ergebnis(f);
    }

    function bildElement() {
        ctx.oeffnen();
        var f = [];
        var bild = ctx.basis && ctx.basis.schwarz;
        if (!bild) return { ok: false, detail: 'kein Basisbild' };
        var be = B.bildElementHinzufuegen(bild.src, bild);
        if (!be) return { ok: false, detail: 'Bild-Element nicht angelegt' };
        var mx = B.cssWidth / 2, my = B.cssHeight / 2;
        // Ecke umranden (Mitte NICHT drin): nicht gewählt
        lasso(rechteck(10, 10, 120, 120));
        if (B.auswahl.indexOf(be.id) !== -1) f.push('Ecke umrandet, Bild trotzdem gewählt');
        // Mitte umranden: gewählt
        lasso(rechteck(mx - 40, my - 40, mx + 40, my + 40));
        if (sortiert(B.auswahl) !== be.id) f.push('Auswahl ' + sortiert(B.auswahl) + ' statt ' + be.id);
        var box = pixelBox(B.auswahlCanvas);
        if (!box) f.push('kein Rahmen');
        else if (box.x > 0.5 || box.y > 0.5 || box.r < B.cssWidth - 0.5 || box.u < B.cssHeight - 0.5) {
            // Rahmen liegt 8 px außerhalb der Fläche - sichtbar ist nur, was auf der Ebene liegt
            f.push('Rahmen ' + JSON.stringify(box) + ' umschließt das Bild nicht');
        }
        if (!sichtbar(leiste())) f.push('Leiste nicht sichtbar');
        return ergebnis(f);
    }

    function werkzeugwechsel() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        var namen = ['pen', 'highlighter', 'eraser-stroke', 'eraser-point', 'text'];
        namen.forEach(function(n) {
            lasso(LASSO_REGEL);
            if (!(B.auswahl && B.auswahl.length === 3)) { f.push(n + ': Vorbedingung Auswahl ' + sortiert(B.auswahl)); return; }
            B.overlay.querySelector('.cbd-board-tool[data-tool="' + n + '"]').click();
            if (B.auswahl.length !== 0) f.push(n + ': Auswahl bleibt');
            if (!ebeneLeer(B.auswahlCanvas)) f.push(n + ': Rahmen bleibt');
            if (sichtbar(leiste())) f.push(n + ': Leiste bleibt');
        });
        // setTool('lasso') bei schon aktivem Lasso hebt nichts auf
        lasso(LASSO_REGEL);
        B.setTool('lasso');
        if (!(B.auswahl && B.auswahl.length === 3)) f.push('erneutes setTool(lasso) hebt Auswahl auf');
        if (el().length !== 4) f.push('Elemente verändert: ' + el().length);
        return ergebnis(f);
    }

    function escMitAuswahl() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        lasso(LASSO_REGEL);
        if (!(B.auswahl && B.auswahl.length === 3)) return { ok: false, detail: 'Vorbedingung: Auswahl ' + sortiert(B.auswahl) };
        var ov = B.overlay;
        taste('Escape');
        if (B.overlay !== ov || ov.classList.contains('cbd-board-closing')) f.push('ESC mit Auswahl schließt die Tafel');
        if (B.auswahl.length !== 0) f.push('Auswahl nach ESC ' + sortiert(B.auswahl));
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Rahmen nach ESC');
        if (sichtbar(leiste())) f.push('Leiste nach ESC');
        if (el().length !== 4) f.push('Elemente verändert');
        // ESC während des Lasso-Ziehens: Lasso verworfen, Tafel bleibt
        lasso(LASSO_REGEL, { ohneAbheben: true });
        taste('Escape');
        if (!B.overlay || B.overlay.classList.contains('cbd-board-closing')) f.push('ESC beim Ziehen schließt die Tafel');
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Lassolinie nach ESC');
        z('pointerup', 7, 'pen', 20, 62);
        if (B.auswahl.length !== 0) f.push('nach ESC und Abheben trotzdem gewählt: ' + sortiert(B.auswahl));
        return ergebnis(f);
    }

    function pngOhneAuswahl() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        var png0 = B.drawingCanvas.toDataURL('image/png');
        lasso(LASSO_REGEL);
        if (!(B.auswahl && B.auswahl.length === 3)) f.push('Vorbedingung: Auswahl ' + sortiert(B.auswahl));
        if (ebeneLeer(B.auswahlCanvas)) f.push('Vorbedingung: Rahmen sichtbar');
        B.saveDrawing();
        var gespeichert = null;
        try { gespeichert = localStorage.getItem(SCHLUESSEL); } catch (e) {}
        if (!gespeichert) f.push('nichts gespeichert');
        else if (gespeichert !== png0) f.push('gespeichertes PNG weicht ab (Lasso/Rahmen im Bild?)');
        // mitten im Lasso speichern (Seitenwechsel/Schließen während des Ziehens)
        lasso(LASSO_REGEL, { ohneAbheben: true });
        if (B.drawingCanvas.toDataURL('image/png') !== png0) f.push('PNG während des Lasso-Ziehens weicht ab');
        z('pointerup', 7, 'pen', 20, 62);
        return ergebnis(f);
    }

    function zeichneModellLaesstAuswahl() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        lasso(LASSO_REGEL);
        var vorher = ebenenDaten(B.auswahlCanvas);
        B.zeichneModell();
        B.redrawAllStrokes();
        if (!gleicheDaten(vorher, ebenenDaten(B.auswahlCanvas))) f.push('zeichneModell() verändert die Auswahl-Ebene');
        if (!(B.auswahl && B.auswahl.length === 3)) f.push('Auswahl nach zeichneModell ' + sortiert(B.auswahl));
        return ergebnis(f);
    }

    function zoom150() {
        ctx.oeffnen();
        var f = [];
        var ids = regelZeichnung();
        B.zoom = 1.5;
        B.panX = 0;
        B.panY = 0;
        B.updateCanvasTransform();
        lasso(LASSO_REGEL); // z() rechnet Element- in Bildschirmkoordinaten um
        var erwartet = sortiert([ids.a, ids.b, ids.t]);
        if (sortiert(B.auswahl) !== erwartet) f.push('Auswahl bei 150 % ' + sortiert(B.auswahl) + ' statt ' + erwartet);
        var box = pixelBox(B.auswahlCanvas);
        rahmenPasst(box, vereinigung([ids.a, ids.b, ids.t]), f);
        var a = B.auswahlCanvas.getBoundingClientRect(), d = B.drawingCanvas.getBoundingClientRect();
        if (Math.abs(a.left - d.left) > 0.5 || Math.abs(a.top - d.top) > 0.5 || Math.abs(a.width - d.width) > 0.5) {
            f.push('Auswahl-Ebene folgt dem Zoom nicht');
        }
        var l = leiste();
        if (!sichtbar(l)) f.push('Leiste nicht sichtbar');
        else if (box) {
            var lr = l.getBoundingClientRect();
            var rLinks = d.left + box.x * 1.5, rRechts = d.left + box.r * 1.5, rOben = d.top + box.y * 1.5;
            if (lr.bottom > rOben + 1 || lr.bottom < rOben - 30) f.push('Leiste nicht über dem Rahmen (unten ' + lr.bottom.toFixed(0) + ', Rahmen oben ' + rOben.toFixed(0) + ')');
            if (lr.left < rLinks - 2 || lr.left > rRechts) f.push('Leiste waagrecht neben dem Rahmen (links ' + lr.left.toFixed(0) + ', Rahmen ' + rLinks.toFixed(0) + '-' + rRechts.toFixed(0) + ')');
        }
        // Zoom-Wechsel bei bestehender Auswahl: Leiste wandert mit
        B.zoom = 1.0;
        B.updateCanvasTransform();
        if (l && box && sichtbar(l)) {
            var lr2 = l.getBoundingClientRect();
            var d2 = B.drawingCanvas.getBoundingClientRect();
            var oben2 = d2.top + box.y;
            if (lr2.bottom > oben2 + 1 || lr2.bottom < oben2 - 30) f.push('Leiste folgt dem Zoom nicht (unten ' + lr2.bottom.toFixed(0) + ', Rahmen oben ' + oben2.toFixed(0) + ')');
        }
        return ergebnis(f);
    }

    function kontextmenueBeimHalten() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        var p = LASSO_REGEL;
        B.setTool('lasso');
        z('pointerdown', 7, 'pen', p[0][0], p[0][1]);
        z('pointermove', 7, 'pen', p[1][0], p[1][1]);
        // langes Halten: Browser feuert contextmenu am Canvas
        if (!ereignis(B.drawingCanvas, 'contextmenu')) f.push('Kontextmenü am Canvas beim Lasso-Halten nicht gesperrt');
        for (var i = 2; i < p.length; i++) z('pointermove', 7, 'pen', p[i][0], p[i][1]);
        z('pointerup', 7, 'pen', p[p.length - 1][0], p[p.length - 1][1]);
        if (!(B.auswahl && B.auswahl.length === 3)) f.push('Lasso nach contextmenu abgebrochen: ' + sortiert(B.auswahl));
        var l = leiste();
        if (l) {
            if (!ereignis(l, 'contextmenu')) f.push('Kontextmenü auf der Auswahlleiste nicht gesperrt');
            var k = knopf('Löschen');
            if (k && !ereignis(k, 'contextmenu')) f.push('Kontextmenü auf "Löschen" nicht gesperrt');
        } else {
            f.push('keine Leiste');
        }
        return ergebnis(f);
    }

    function fingerUndHandballen() {
        // Finger zeichnen nur, solange in dieser Sitzung kein Stift auftrat
        // (Stift-Vorrang #7149) - deshalb hier mit der Maus vorzeichnen.
        ctx.oeffnen();
        var f = [];
        B.setColor('#000000');
        z('pointerdown', 1, 'mouse', 40, 100);
        z('pointermove', 1, 'mouse', 200, 100);
        z('pointerup', 1, 'mouse', 200, 100);
        var id = idsVon()[0];
        lasso(rechteck(20, 60, 240, 140), { art: 'touch', id: 21 });
        if (sortiert(B.auswahl) !== id) f.push('Finger-Lasso wählt ' + sortiert(B.auswahl) + ' statt ' + id);
        // Handballen (große Kontaktfläche) startet kein Lasso
        B.auswahlAufheben();
        lasso(rechteck(20, 60, 240, 140), { art: 'touch', id: 22, extra: { width: 40, height: 40 } });
        if (B.auswahl.length !== 0) f.push('Handballen wählt ' + sortiert(B.auswahl));
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Handballen malt Lassolinie');
        // zweiter Finger (Pinch) bricht das Finger-Lasso ab
        lasso(rechteck(20, 60, 240, 140), { art: 'touch', id: 23, ohneAbheben: true });
        z('pointerdown', 24, 'touch', 400, 400);
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Lassolinie nach Pinch-Beginn');
        z('pointerup', 24, 'touch', 400, 400);
        z('pointerup', 23, 'touch', 20, 62);
        if (B.auswahl.length !== 0) f.push('Pinch-abgebrochenes Lasso wählt ' + sortiert(B.auswahl));
        // Rechtsklick startet kein Lasso
        lasso(rechteck(20, 60, 240, 140), { art: 'mouse', id: 1, extra: { button: 2 } });
        if (B.auswahl.length !== 0) f.push('Rechtsklick-Lasso wählt ' + sortiert(B.auswahl));
        return ergebnis(f);
    }

    function seiteUndUndoHebenAuf() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        lasso(LASSO_REGEL);
        if (!(B.auswahl && B.auswahl.length === 3)) f.push('Vorbedingung: Auswahl ' + sortiert(B.auswahl));
        B.addPage();
        if (B.auswahl.length !== 0) f.push('neue Seite: Auswahl bleibt');
        if (!ebeneLeer(B.auswahlCanvas)) f.push('neue Seite: Rahmen bleibt');
        if (sichtbar(leiste())) f.push('neue Seite: Leiste bleibt');
        B.goToPage(0);
        lasso(LASSO_REGEL);
        if (!(B.auswahl && B.auswahl.length === 3)) f.push('Seite 1: Auswahl ' + sortiert(B.auswahl));
        B.undo();
        if (B.auswahl.length !== 0) f.push('Rückgängig: Auswahl bleibt');
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Rückgängig: Rahmen bleibt');
        return ergebnis(f);
    }

    function schliessenSetztZurueck() {
        ctx.oeffnen();
        var f = [];
        regelZeichnung();
        lasso(LASSO_REGEL);
        B.destroy();
        if (B.auswahl && B.auswahl.length) f.push('Auswahl nach destroy ' + sortiert(B.auswahl));
        if (B.auswahlCanvas) f.push('auswahlCanvas nach destroy nicht freigegeben');
        B.open(CID, '<p>Inhalt</p>', '#ffffff');
        if (B.currentTool === 'lasso') f.push('Tafel öffnet im Lasso-Modus (Knopf zeigt Stift)');
        var aktiv = B.overlay.querySelector('.cbd-board-tool.active');
        if (aktiv && aktiv.getAttribute('data-tool') !== B.currentTool) f.push('aktiver Knopf ' + aktiv.getAttribute('data-tool') + ' ≠ Werkzeug ' + B.currentTool);
        if (B.auswahl && B.auswahl.length) f.push('Auswahl nach Öffnen');
        return ergebnis(f);
    }

    function leisteDarkmode() {
        var f = [];
        var html = document.documentElement;
        var vorher = html.getAttribute('data-theme');
        try {
            ['light', 'dark'].forEach(function(modus) {
                html.setAttribute('data-theme', modus);
                ctx.oeffnen();
                regelZeichnung();
                lasso(LASSO_REGEL);
                var l = leiste();
                if (!sichtbar(l)) { f.push(modus + ': Leiste nicht sichtbar'); return; }
                var bg = getComputedStyle(l).backgroundColor;
                l.querySelectorAll('button').forEach(function(k) {
                    var cs = getComputedStyle(k);
                    if (cs.color === bg) f.push(modus + ': Knopf "' + k.textContent.trim() + '" Schrift = Leistenfarbe');
                    var kb = cs.backgroundColor;
                    if (kb !== 'rgba(0, 0, 0, 0)' && kb === cs.color) f.push(modus + ': Knopf "' + k.textContent.trim() + '" unlesbar');
                });
                if (bg === 'rgba(0, 0, 0, 0)') f.push(modus + ': Leiste ohne Hintergrund');
                ctx.schliessen();
            });
        } finally {
            if (vorher === null) html.removeAttribute('data-theme'); else html.setAttribute('data-theme', vorher);
        }
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // Prüfungen (asynchron)
    // ------------------------------------------------------------------
    function escOhneAuswahlSchliesst() {
        ctx.oeffnen();
        regelZeichnung();
        lasso(LASSO_REGEL);
        taste('Escape'); // hebt nur die Auswahl auf
        if (!B.overlay) return { ok: false, detail: 'erstes ESC schloss sofort' };
        var ov = B.overlay;
        taste('Escape'); // ohne Auswahl: schließen wie bisher
        if (!ov.classList.contains('cbd-board-closing')) return { ok: false, detail: 'ESC ohne Auswahl schließt nicht' };
        return ctx.warteAuf(function() { return !B.overlay; }, 2000).then(function() {
            var png = null;
            try { png = localStorage.getItem(SCHLUESSEL); } catch (e) {}
            return png ? true : { ok: false, detail: 'beim Schließen nicht gespeichert' };
        });
    }

    // ==================================================================
    // AP-5.2: Verschieben, Löschen, Rückgängig, Speichern
    // ==================================================================
    //
    // Verträge (Plan AP-5.2, Übergabenotiz AP-5.1):
    //   - Zeiger setzt mit Werkzeug 'lasso' INNERHALB des Auswahlrahmens
    //     (+ 8 px) auf -> Verschieben statt neuem Lasso; die Auswahl bleibt.
    //   - Verschiebung = Momentaufnahme beim Aufsetzen + verschiebe() mit der
    //     GESAMTEN Verschiebung seit dem Start (nicht kumulativ gerundet).
    //   - genau EIN Undo-Schritt je Ziehen; 0 px -> kein Undo-Schritt.
    //   - Leiste während des Ziehens ausgeblendet, Cursor 'move'.
    //   - B.auswahlLoeschen() / Knopf "Löschen" / Entf / Rücktaste entfernen
    //     die Auswahl (ein Undo-Schritt), Auswahl danach aufgehoben.
    //   - Entf/Rücktaste mit Fokus in input/textarea löschen NICHT.
    //   - Der Zwischenstand darf per requestAnimationFrame gebündelt werden;
    //     spätestens beim Abheben steht das Modell an der Endlage.

    /** Kopie der Lage eines Elements (Punkte bzw. x/y/b/h). */
    function lage(e) {
        if (!e) return null;
        if (e.typ === 'strich') return { typ: e.typ, punkte: e.punkte.map(function(p) { return [p[0], p[1]]; }) };
        return { typ: e.typ, x: e.x, y: e.y, b: e.b, h: e.h };
    }

    function lagenNachId() {
        var o = {};
        el().forEach(function(e) { o[e.id] = lage(e); });
        return o;
    }

    function finde(id) {
        var l = el();
        for (var i = 0; i < l.length; i++) if (l[i].id === id) return l[i];
        return null;
    }

    /** Ist Element id um (dx, dy) gegenüber alt verschoben (Toleranz tol)? */
    function verschobenUm(id, alt, dx, dy, tol, f, name) {
        var e = finde(id);
        if (!e) { f.push((name || id) + ' fehlt'); return; }
        var a = alt[id];
        var ab = 0;
        if (e.typ === 'strich') {
            if (e.punkte.length !== a.punkte.length) { f.push((name || id) + ': Punktzahl geändert'); return; }
            for (var i = 0; i < e.punkte.length; i++) {
                ab = Math.max(ab, Math.abs(e.punkte[i][0] - (a.punkte[i][0] + dx)), Math.abs(e.punkte[i][1] - (a.punkte[i][1] + dy)));
            }
        } else {
            ab = Math.max(Math.abs(e.x - (a.x + dx)), Math.abs(e.y - (a.y + dy)));
            if (e.typ === 'bild' && (e.b !== a.b || e.h !== a.h)) f.push((name || id) + ': Größe geändert');
        }
        if (ab > tol) f.push((name || id) + ': Abweichung ' + ab.toFixed(6) + ' px von (' + dx.toFixed(4) + ', ' + dy.toFixed(4) + ')');
    }

    function unveraendert(id, alt, f, name) {
        verschobenUm(id, alt, 0, 0, 0, f, name);
    }

    /**
     * Ziehen mit dem Lasso-Werkzeug von (x0,y0) nach (x1,y1) in n Schritten
     * (Element-Koordinaten). optionen wie lasso(); optionen.mitte(i) nach dem
     * i-ten pointermove. Liefert { dx, dy } = tatsächliche Verschiebung aus
     * den ausgelösten Ereignissen (Zoom herausgerechnet).
     */
    function ziehe(x0, y0, x1, y1, n, optionen) {
        optionen = optionen || {};
        var art = optionen.art || 'pen', id = optionen.id || 8, extra = optionen.extra;
        n = n || 5;
        if (B.currentTool !== 'lasso') B.setTool('lasso');
        var start = z('pointerdown', id, art, x0, y0, extra);
        var letzt = start;
        for (var i = 1; i <= n; i++) {
            letzt = z('pointermove', id, art, x0 + (x1 - x0) * i / n, y0 + (y1 - y0) * i / n, extra);
            if (optionen.mitte) optionen.mitte(i);
        }
        if (!optionen.ohneAbheben) z('pointerup', id, art, x1, y1, extra);
        var f = B.zoom || 1;
        return { dx: (letzt.clientX - start.clientX) / f, dy: (letzt.clientY - start.clientY) / f };
    }

    function waehleRegel() {
        var ids = regelZeichnung();
        lasso(LASSO_REGEL);
        if (sortiert(B.auswahl) !== sortiert([ids.a, ids.b, ids.t])) throw new Error('Vorbedingung: Auswahl ' + sortiert(B.auswahl));
        return ids;
    }

    function cursorVon(c) {
        return getComputedStyle(c).cursor;
    }

    function istDunkelBei(x, y) {
        var dpr = B.dpr || 1;
        var d = B.drawingCtx.getImageData(Math.round(x * dpr), Math.round(y * dpr), 1, 1).data;
        return d[3] > 128 && d[0] < 100 && d[1] < 100 && d[2] < 100;
    }

    function leerBei(x, y) {
        var dpr = B.dpr || 1;
        return B.drawingCtx.getImageData(Math.round(x * dpr), Math.round(y * dpr), 1, 1).data[3] === 0;
    }

    // ------------------------------------------------------------------
    // Prüfungen AP-5.2 (synchron)
    // ------------------------------------------------------------------
    function ziehenVerschiebtNurGewaehlte() {
        ctx.oeffnen();
        var f = [];
        var ids = waehleRegel();
        var alt = lagenNachId();
        var boxVorher = pixelBox(B.auswahlCanvas);
        var undoVorher = B.undoStapel.length;
        var leisteWaehrend = true, cursorWaehrend = '';
        // Aufsetzen mitten im Rahmen (zwischen A und B), 50/30 px ziehen
        var d = ziehe(120, 130, 170, 160, 6, {
            mitte: function(i) {
                if (i === 3) {
                    leisteWaehrend = sichtbar(leiste());
                    cursorWaehrend = cursorVon(B.drawingCanvas);
                }
            }
        });
        if (Math.abs(d.dx - 50) > 1 || Math.abs(d.dy - 30) > 1) f.push('Testaufbau: Verschiebung ' + d.dx + '/' + d.dy);
        verschobenUm(ids.a, alt, d.dx, d.dy, 1e-6, f, 'A');
        verschobenUm(ids.b, alt, d.dx, d.dy, 1e-6, f, 'B');
        verschobenUm(ids.t, alt, d.dx, d.dy, 1e-6, f, 'Text');
        unveraendert(ids.d, alt, f, 'D (nicht gewählt)');
        if (el().length !== 4) f.push('Elementzahl ' + el().length);
        if (sortiert(B.auswahl) !== sortiert([ids.a, ids.b, ids.t])) f.push('Auswahl nach dem Ziehen ' + sortiert(B.auswahl));
        if (B.undoStapel.length !== undoVorher + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undoVorher) + ' statt 1');
        if (leisteWaehrend) f.push('Leiste während des Ziehens sichtbar');
        if (cursorWaehrend !== 'move') f.push('Cursor während des Ziehens ' + cursorWaehrend);
        if (!sichtbar(leiste())) f.push('Leiste nach dem Ziehen nicht sichtbar');
        if (cursorVon(B.drawingCanvas) !== 'crosshair') f.push('Cursor nach dem Ziehen ' + cursorVon(B.drawingCanvas));
        // Rahmen folgt
        var box = pixelBox(B.auswahlCanvas);
        if (!box || !boxVorher) f.push('kein Rahmen');
        else if (Math.abs((box.x - boxVorher.x) - d.dx) > 1.5 || Math.abs((box.y - boxVorher.y) - d.dy) > 1.5) {
            f.push('Rahmen verschoben um ' + (box.x - boxVorher.x).toFixed(1) + '/' + (box.y - boxVorher.y).toFixed(1));
        }
        rahmenPasst(box, vereinigung([ids.a, ids.b, ids.t]), f);
        // Zeichenebene: A an neuer Stelle, alte Stelle leer, D unverändert
        if (!istDunkelBei(120 + d.dx, 100 + d.dy)) f.push('A an neuer Stelle nicht gezeichnet');
        if (!leerBei(120, 100)) f.push('alte Stelle von A nicht leer');
        if (!istDunkelBei(500, 280)) f.push('D nicht mehr gezeichnet');
        return ergebnis(f);
    }

    function ziehenNichtKumulativGerundet() {
        ctx.oeffnen();
        var f = [];
        var ids = waehleRegel();
        var alt = lagenNachId();
        // 9 Schritte zu je 0,3371 px: eine je Schritt auf 2 Stellen gerundete
        // oder aufsummierte Lage wiche messbar von alt + Gesamtverschiebung ab
        var d = ziehe(120, 130, 120 + 9 * 0.3371, 130 + 9 * 0.2219, 9);
        if (Math.abs(d.dx) < 1e-9 && Math.abs(d.dy) < 1e-9) return { ok: false, detail: 'Testaufbau: keine Verschiebung erzeugt' };
        verschobenUm(ids.a, alt, d.dx, d.dy, 1e-6, f, 'A');
        verschobenUm(ids.t, alt, d.dx, d.dy, 1e-6, f, 'Text');
        unveraendert(ids.d, alt, f, 'D');
        // zweites Ziehen baut auf der Endlage auf
        var alt2 = lagenNachId();
        var d2 = ziehe(121, 131, 121 - 7 * 0.1234, 131 + 7 * 0.4321, 7);
        verschobenUm(ids.b, alt2, d2.dx, d2.dy, 1e-6, f, 'B (zweites Ziehen)');
        return ergebnis(f);
    }

    function nullVerschiebungOhneUndo() {
        ctx.oeffnen();
        var f = [];
        var ids = waehleRegel();
        var vorher = JSON.stringify(el());
        var undoVorher = B.undoStapel.length;
        // hin und zurück
        B.setTool('lasso');
        z('pointerdown', 8, 'pen', 120, 130);
        z('pointermove', 8, 'pen', 160, 150);
        z('pointermove', 8, 'pen', 140, 140);
        z('pointermove', 8, 'pen', 120, 130);
        z('pointerup', 8, 'pen', 120, 130);
        if (B.undoStapel.length !== undoVorher) f.push('hin und zurück: ' + (B.undoStapel.length - undoVorher) + ' Undo-Schritte');
        if (JSON.stringify(el()) !== vorher) f.push('hin und zurück: Modell verändert');
        if (sortiert(B.auswahl) !== sortiert([ids.a, ids.b, ids.t])) f.push('hin und zurück: Auswahl ' + sortiert(B.auswahl));
        // reines Antippen im Rahmen: Auswahl bleibt, kein neues Lasso, kein Undo
        z('pointerdown', 8, 'pen', 120, 130);
        z('pointerup', 8, 'pen', 120, 130);
        if (B.undoStapel.length !== undoVorher) f.push('Antippen: Undo-Schritt');
        if (sortiert(B.auswahl) !== sortiert([ids.a, ids.b, ids.t])) f.push('Antippen im Rahmen hebt Auswahl auf: ' + sortiert(B.auswahl));
        if (!sichtbar(leiste())) f.push('Leiste nach Antippen nicht sichtbar');
        // Aufsetzen AUSSERHALB des Rahmens beginnt weiterhin ein neues Lasso
        lasso(rechteck(400, 60, 600, 200));
        if (B.auswahl.length !== 0) f.push('Lasso außerhalb wählt ' + sortiert(B.auswahl));
        if (JSON.stringify(el()) !== vorher) f.push('Lasso außerhalb verändert das Modell');
        return ergebnis(f);
    }

    function loeschenPerKnopf() {
        ctx.oeffnen();
        var f = [];
        var ids = waehleRegel();
        var alt = lagenNachId();
        var undoVorher = B.undoStapel.length;
        if (typeof B.auswahlLoeschen !== 'function') f.push('B.auswahlLoeschen fehlt');
        var k = knopf('Löschen');
        if (!k) return { ok: false, detail: 'Knopf "Löschen" fehlt' };
        k.click();
        if (sortiert(idsVon()) !== ids.d) f.push('nach Löschen ' + sortiert(idsVon()) + ' statt ' + ids.d);
        unveraendert(ids.d, alt, f, 'D');
        if (B.auswahl.length !== 0) f.push('Auswahl nach Löschen ' + sortiert(B.auswahl));
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Rahmen bleibt');
        if (sichtbar(leiste())) f.push('Leiste bleibt');
        if (B.undoStapel.length !== undoVorher + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undoVorher) + ' statt 1');
        if (!leerBei(120, 100)) f.push('A noch gezeichnet');
        if (!istDunkelBei(500, 280)) f.push('D nicht mehr gezeichnet');
        if (!B.overlay) f.push('Tafel geschlossen');
        // ohne Auswahl tut der Aufruf nichts
        B.auswahlLoeschen && B.auswahlLoeschen();
        if (B.undoStapel.length !== undoVorher + 1) f.push('Löschen ohne Auswahl legt Undo-Schritt an');
        if (el().length !== 1) f.push('Löschen ohne Auswahl entfernt ' + (1 - el().length));
        return ergebnis(f);
    }

    function loeschenPerTaste() {
        var f = [];
        ['Delete', 'Backspace'].forEach(function(key) {
            ctx.oeffnen();
            var ids = waehleRegel();
            var undoVorher = B.undoStapel.length;
            var ev = taste(key);
            if (sortiert(idsVon()) !== ids.d) f.push(key + ': nach Taste ' + sortiert(idsVon()));
            if (B.auswahl.length !== 0) f.push(key + ': Auswahl bleibt');
            if (B.undoStapel.length !== undoVorher + 1) f.push(key + ': Undo-Schritte ' + (B.undoStapel.length - undoVorher));
            if (!ev.defaultPrevented) f.push(key + ': Standardaktion nicht verhindert');
            if (!B.overlay || B.overlay.classList.contains('cbd-board-closing')) f.push(key + ': Tafel geschlossen');
            // ohne Auswahl: nichts löschen, kein Undo-Schritt
            var ev2 = taste(key);
            if (el().length !== 1) f.push(key + ' ohne Auswahl löscht');
            if (B.undoStapel.length !== undoVorher + 1) f.push(key + ' ohne Auswahl: Undo-Schritt');
            if (ev2.defaultPrevented) f.push(key + ' ohne Auswahl: Standardaktion verhindert');
            ctx.schliessen();
        });
        return ergebnis(f);
    }

    function entfImTextfeldLoeschtNichtAuswahl() {
        ctx.oeffnen();
        var f = [];
        var ids = regelZeichnung();
        // Textfeld des Text-Werkzeugs offen (hängt an document.body)
        B.setTool('text');
        z('pointerdown', 1, 'mouse', 400, 60);
        var feld = document.querySelector('.cbd-board-text-input');
        if (!feld) return { ok: false, detail: 'kein Textfeld' };
        feld.value = 'abc';
        // Auswahl bei offenem Feld (programmatischer Werkzeugwechsel ohne blur)
        B.setTool('lasso');
        lasso(LASSO_REGEL);
        var gewaehlt = sortiert(B.auswahl);
        if (gewaehlt !== sortiert([ids.a, ids.b, ids.t])) f.push('Vorbedingung: Auswahl ' + gewaehlt);
        feld.focus();
        var undoVorher = B.undoStapel.length;
        ['Delete', 'Backspace'].forEach(function(key) {
            // Taste im Textfeld selbst
            var ev = new KeyboardEvent('keydown', { key: key, bubbles: true, cancelable: true });
            feld.dispatchEvent(ev);
            if (ev.defaultPrevented) f.push(key + ' im Textfeld: Standardaktion (Zeichen löschen) verhindert');
            // Taste am Dokument, Fokus im Textfeld
            if (document.activeElement === feld) {
                var ev2 = taste(key);
                if (ev2.defaultPrevented) f.push(key + ' bei Fokus im Textfeld: Standardaktion verhindert');
            } else {
                f.push('Testaufbau: Textfeld hat keinen Fokus');
            }
        });
        // Taste in einem anderen Eingabefeld (Breitenregler der Werkzeugleiste)
        var regler = B.overlay.querySelector('input');
        if (regler) {
            var ev3 = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true });
            regler.dispatchEvent(ev3);
        }
        if (el().length !== 4) f.push('Elemente gelöscht: ' + el().length + ' statt 4');
        if (sortiert(B.auswahl) !== gewaehlt) f.push('Auswahl verändert: ' + sortiert(B.auswahl));
        if (B.undoStapel.length !== undoVorher) f.push('Undo-Schritt angelegt');
        var t = document.querySelector('.cbd-board-text-input');
        if (t && t.parentNode) t.parentNode.removeChild(t);
        return ergebnis(f);
    }

    function undoJeEinzeln() {
        ctx.oeffnen();
        var f = [];
        var ids = waehleRegel();
        var original = JSON.stringify(el());
        var undoVorher = B.undoStapel.length;
        var d = ziehe(120, 130, 160, 150, 4);
        var verschoben = JSON.stringify(el());
        if (verschoben === original) f.push('Ziehen ohne Wirkung');
        // Auswahl besteht weiter -> Entf
        taste('Delete');
        if (el().length !== 1) f.push('Löschen nach Ziehen: ' + el().length + ' Elemente');
        if (B.undoStapel.length !== undoVorher + 2) f.push('Undo-Schritte ' + (B.undoStapel.length - undoVorher) + ' statt 2');
        // Strg+Z #1: Löschen rückgängig, Lage bleibt die verschobene
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true, cancelable: true }));
        if (JSON.stringify(el()) !== verschoben) f.push('erstes Strg+Z stellt nicht den verschobenen Stand her');
        if (B.auswahl.length !== 0) f.push('Auswahl nach erstem Strg+Z');
        if (!ebeneLeer(B.auswahlCanvas)) f.push('Rahmen nach erstem Strg+Z');
        if (sichtbar(leiste())) f.push('Leiste nach erstem Strg+Z');
        // Strg+Z #2: Verschieben rückgängig
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true, cancelable: true }));
        if (JSON.stringify(el()) !== original) f.push('zweites Strg+Z stellt nicht den Ausgangsstand her');
        if (B.undoStapel.length !== undoVorher) f.push('Undo-Stapel ' + (B.undoStapel.length - undoVorher));
        if (!istDunkelBei(120, 100)) f.push('A nach Rückgängig nicht an alter Stelle');
        if (!leerBei(120 + d.dx, 100 + d.dy)) f.push('verschobenes A bleibt sichtbar');
        return ergebnis(f);
    }

    function bildElementVerschiebenLoeschen() {
        ctx.oeffnen();
        var f = [];
        var bild = ctx.basis && ctx.basis.schwarz;
        if (!bild) return { ok: false, detail: 'kein Basisbild' };
        var be = B.bildElementHinzufuegen(bild.src, bild);
        if (!be) return { ok: false, detail: 'Bild-Element nicht angelegt' };
        var mx = B.cssWidth / 2, my = B.cssHeight / 2;
        lasso(rechteck(mx - 40, my - 40, mx + 40, my + 40));
        if (sortiert(B.auswahl) !== be.id) return { ok: false, detail: 'Vorbedingung: Auswahl ' + sortiert(B.auswahl) };
        var alt = lagenNachId();
        var pngVorher = B.drawingCanvas.toDataURL('image/png');
        var d = ziehe(mx, my, mx + 30, my + 20, 5);
        verschobenUm(be.id, alt, d.dx, d.dy, 1e-6, f, 'Bild');
        if (B.drawingCanvas.toDataURL('image/png') === pngVorher) f.push('Zeichenebene nach Verschieben unverändert');
        var nachZiehen = JSON.stringify(el());
        if (sortiert(B.auswahl) !== be.id) f.push('Auswahl nach Ziehen ' + sortiert(B.auswahl));
        taste('Delete');
        if (el().length !== 0) f.push('Bild nach Entf nicht gelöscht (' + el().length + ')');
        if (!ebeneLeer(B.drawingCanvas)) f.push('Zeichenebene nach Löschen nicht leer');
        B.undo();
        if (JSON.stringify(el()) !== nachZiehen) f.push('Rückgängig stellt das verschobene Bild nicht her');
        B.undo();
        verschobenUm(be.id, alt, 0, 0, 0, f, 'Bild nach zweitem Rückgängig');
        return ergebnis(f);
    }

    function zoomBeimZiehen() {
        ctx.oeffnen();
        var f = [];
        var ids = regelZeichnung();
        B.zoom = 1.5;
        B.panX = 0;
        B.panY = 0;
        B.updateCanvasTransform();
        lasso(LASSO_REGEL);
        if (sortiert(B.auswahl) !== sortiert([ids.a, ids.b, ids.t])) return { ok: false, detail: 'Vorbedingung: Auswahl ' + sortiert(B.auswahl) };
        var alt = lagenNachId();
        // z() rechnet Element- in Bildschirmkoordinaten um: 40/20 Element-px = 60/30 Bildschirm-px
        var d = ziehe(120, 130, 160, 150, 4);
        if (Math.abs(d.dx - 40) > 1 || Math.abs(d.dy - 20) > 1) f.push('Testaufbau: ' + d.dx + '/' + d.dy);
        verschobenUm(ids.a, alt, d.dx, d.dy, 1e-6, f, 'A');
        verschobenUm(ids.t, alt, d.dx, d.dy, 1e-6, f, 'Text');
        unveraendert(ids.d, alt, f, 'D');
        // Aufsetzen im Rahmen (Element-Koordinaten) wird bei Zoom erkannt
        var box = pixelBox(B.auswahlCanvas);
        rahmenPasst(box, vereinigung([ids.a, ids.b, ids.t]), f);
        if (!sichtbar(leiste())) f.push('Leiste nach Ziehen bei 150 % nicht sichtbar');
        return ergebnis(f);
    }

    function fingerZiehenUndPinch() {
        ctx.oeffnen();
        var f = [];
        // mit der Maus vorzeichnen (Stift-Vorrang, s. fingerUndHandballen)
        B.setColor('#000000');
        z('pointerdown', 1, 'mouse', 40, 100);
        z('pointermove', 1, 'mouse', 200, 100);
        z('pointerup', 1, 'mouse', 200, 100);
        z('pointerdown', 1, 'mouse', 40, 300);
        z('pointermove', 1, 'mouse', 200, 300);
        z('pointerup', 1, 'mouse', 200, 300);
        var ids = idsVon();
        lasso(rechteck(20, 60, 240, 140), { art: 'touch', id: 31 });
        if (sortiert(B.auswahl) !== ids[0]) return { ok: false, detail: 'Vorbedingung: Auswahl ' + sortiert(B.auswahl) };
        var alt = lagenNachId();
        var d = ziehe(120, 100, 150, 140, 5, { art: 'touch', id: 32 });
        verschobenUm(ids[0], alt, d.dx, d.dy, 1e-6, f, 'Finger');
        unveraendert(ids[1], alt, f, 'nicht gewählt');
        // Maus zieht ebenso
        var alt2 = lagenNachId();
        var d2 = ziehe(120 + d.dx, 100 + d.dy, 100, 100, 5, { art: 'mouse', id: 1 });
        verschobenUm(ids[0], alt2, d2.dx, d2.dy, 1e-6, f, 'Maus');
        // zweiter Finger während des Finger-Ziehens: Ziehen verworfen
        var alt3 = lagenNachId();
        var undo3 = B.undoStapel.length;
        ziehe(100, 100, 140, 130, 3, { art: 'touch', id: 33, ohneAbheben: true });
        z('pointerdown', 34, 'touch', 400, 400);
        unveraendert(ids[0], alt3, f, 'nach Pinch-Beginn');
        if (B.undoStapel.length !== undo3) f.push('Pinch-Abbruch hinterlässt Undo-Schritt');
        z('pointermove', 33, 'touch', 180, 180);
        unveraendert(ids[0], alt3, f, 'nach Pinch, weiterer Fingerzug');
        z('pointerup', 34, 'touch', 400, 400);
        z('pointerup', 33, 'touch', 180, 180);
        unveraendert(ids[0], alt3, f, 'nach Abheben');
        if (B.undoStapel.length !== undo3) f.push('nach Abheben Undo-Schritt');
        return ergebnis(f);
    }

    function lokalSpeichernUndWiederoeffnen() {
        ctx.oeffnen();
        var f = [];
        var ids = regelZeichnung();
        // nur A wählen und verschieben
        lasso(rechteck(20, 80, 240, 120));
        if (sortiert(B.auswahl) !== ids.a) return { ok: false, detail: 'Vorbedingung A: ' + sortiert(B.auswahl) };
        var alt = lagenNachId();
        var d = ziehe(120, 100, 150, 340, 6);
        // nur B wählen und löschen
        lasso(rechteck(20, 140, 240, 180));
        if (sortiert(B.auswahl) !== ids.b) return { ok: false, detail: 'Vorbedingung B: ' + sortiert(B.auswahl) };
        taste('Delete');
        // Text weit über den Rand hinaus: bleibt im Modell (kein stilles Löschen)
        lasso(rechteck(50, 195, 240, 250));
        if (sortiert(B.auswahl) !== ids.t) return { ok: false, detail: 'Vorbedingung Text: ' + sortiert(B.auswahl) };
        var dt = ziehe(100, 220, -400, 220, 5);
        if (!finde(ids.t)) f.push('über den Rand geschobener Text aus dem Modell entfernt');
        B.saveDrawing();
        var png = null;
        try { png = localStorage.getItem(SCHLUESSEL); } catch (e) {}
        if (!png) f.push('kein PNG gespeichert');
        // Wiederöffnen OHNE Speicher zu leeren
        B.destroy();
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        if (sortiert(idsVon()) !== sortiert([ids.a, ids.t, ids.d])) f.push('nach Wiederöffnen ' + sortiert(idsVon()) + ' (B muss fehlen)');
        if (el().some(function(e) { return e.typ === 'bild'; })) f.push('als Bild-Element geladen statt als Elemente');
        // gespeichert wird auf 2 Nachkommastellen (Elementmodell v1)
        verschobenUm(ids.a, alt, d.dx, d.dy, 0.006, f, 'A nach Wiederöffnen');
        verschobenUm(ids.t, alt, dt.dx, dt.dy, 0.006, f, 'Text nach Wiederöffnen');
        unveraendert(ids.d, alt, f, 'D nach Wiederöffnen');
        if (!istDunkelBei(120 + d.dx, 100 + d.dy)) f.push('A an neuer Stelle nicht gezeichnet');
        if (!leerBei(120, 100)) f.push('alte Stelle von A nicht leer');
        if (!leerBei(120, 160)) f.push('gelöschtes B sichtbar');
        if (B.auswahl.length !== 0) f.push('Auswahl nach Wiederöffnen');
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // Prüfungen AP-5.2 (asynchron)
    // ------------------------------------------------------------------
    function zwischenstandImNaechstenFrame() {
        ctx.oeffnen();
        var ids = waehleRegel();
        var alt = lagenNachId();
        B.setTool('lasso');
        z('pointerdown', 8, 'pen', 120, 130);
        z('pointermove', 8, 'pen', 140, 140);
        z('pointermove', 8, 'pen', 160, 150);
        var f = [];
        return ctx.warteAuf(function() {
            var e = finde(ids.a);
            return e && Math.abs(e.punkte[0][0] - (alt[ids.a].punkte[0][0] + 40)) < 1;
        }, 1000).then(function() {
            verschobenUm(ids.b, alt, 40, 20, 1, f, 'B im Zwischenstand');
            unveraendert(ids.d, alt, f, 'D im Zwischenstand');
            if (!istDunkelBei(120 + 40, 100 + 20)) f.push('Zwischenstand nicht gezeichnet');
            if (sichtbar(leiste())) f.push('Leiste im Zwischenstand sichtbar');
            z('pointerup', 8, 'pen', 160, 150);
            verschobenUm(ids.a, alt, 40, 20, 1, f, 'A nach Abheben');
            if (!sichtbar(leiste())) f.push('Leiste nach Abheben nicht sichtbar');
            return ergebnis(f);
        }, function() {
            z('pointerup', 8, 'pen', 160, 150);
            return { ok: false, detail: 'Modell folgt dem Ziehen nicht vor dem Abheben (kein Zwischenstand binnen 1 s)' };
        });
    }

    function serverWegSpeichertNeueLage() {
        var echt = window.fetch;
        var gespeichert = null;
        function stub(antwort) {
            window.fetch = function(url, opts) {
                var o = {};
                if (opts && opts.body && typeof opts.body.forEach === 'function') opts.body.forEach(function(w, n) { o[n] = w; });
                return Promise.resolve(antwort(o)).then(function(json) {
                    return { json: function() { return Promise.resolve(json); } };
                });
            };
        }
        function klassenFelder() {
            B.classId = '7';
            B.ajaxUrl = '/nachgestellt/admin-ajax.php';
            B.nonce = 'test-nonce';
            B.pageId = '5663';
            B.stableContainerId = CID;
        }
        function modellAus(text) {
            var o = JSON.parse(text);
            return o && o.modell ? o.modell : o;
        }
        var f = [];
        var ids, alt, d;
        return Promise.resolve().then(function() {
            ctx.oeffnen();
            stub(function(o) {
                if (o.action === 'cbd_save_drawing') gespeichert = o;
                return { success: true, data: { message: 'ok' } };
            });
            klassenFelder();
            ids = regelZeichnung();
            lasso(rechteck(20, 80, 240, 120)); // nur A
            if (sortiert(B.auswahl) !== ids.a) throw new Error('Vorbedingung A: ' + sortiert(B.auswahl));
            alt = lagenNachId();
            d = ziehe(120, 100, 170, 330, 5);
            lasso(rechteck(20, 140, 240, 180)); // nur B
            if (typeof B.auswahlLoeschen === 'function') B.auswahlLoeschen();
            B.saveDrawing();
            if (!gespeichert) throw new Error('keine Speicheranfrage cbd_save_drawing');
            if (!gespeichert.drawing_data || gespeichert.drawing_data.indexOf('data:image/png') !== 0) f.push('drawing_data kein PNG');
            var m = modellAus(gespeichert.elemente_data);
            var ga = null, gb = null;
            m.elemente.forEach(function(e) { if (e.id === ids.a) ga = e; if (e.id === ids.b) gb = e; });
            if (!ga) f.push('A fehlt in elemente_data');
            else if (Math.abs(ga.punkte[0][0] - (alt[ids.a].punkte[0][0] + d.dx)) > 0.006 || Math.abs(ga.punkte[0][1] - (alt[ids.a].punkte[0][1] + d.dy)) > 0.006) {
                f.push('A in elemente_data an alter Stelle ' + JSON.stringify(ga.punkte[0]));
            }
            if (gb) f.push('gelöschtes B in elemente_data');
            return ctx.warteAuf(function() { return !B.isSaving; }, 2000);
        }).then(function() {
            // Wiederöffnen: Server liefert genau das Gespeicherte zurück
            B.destroy();
            B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
            stub(function(o) {
                if (o.action === 'cbd_load_drawing') {
                    // Der Server liefert dem Browser nur das Modell (ohne Hülle)
                    return { success: true, data: { drawing_data: gespeichert.drawing_data, elemente_data: JSON.stringify(modellAus(gespeichert.elemente_data)) } };
                }
                return { success: true, data: { message: 'ok' } };
            });
            klassenFelder();
            B.loadFromServer(CID);
            return ctx.warteAuf(function() { return el().length === 3; }, 2000);
        }).then(function() {
            if (sortiert(idsVon()) !== sortiert([ids.a, ids.t, ids.d])) f.push('nach Laden ' + sortiert(idsVon()));
            verschobenUm(ids.a, alt, d.dx, d.dy, 0.006, f, 'A nach Laden');
            if (!istDunkelBei(120 + d.dx, 100 + d.dy)) f.push('A an neuer Stelle nicht gezeichnet');
            window.fetch = echt;
            return ergebnis(f);
        }, function(e) {
            window.fetch = echt;
            return { ok: false, detail: (e && e.message ? e.message : String(e)) + (f.length ? '; ' + f.join('; ') : '') };
        });
    }

    // ------------------------------------------------------------------
    // Einhängen (nach den vorhandenen Haken)
    // ------------------------------------------------------------------
    window.__zusatzTests = function(c) {
        if (typeof vorherSync === 'function') vorherSync(c);
        ctx = c;
        var p = c.pruefe;
        p('AP-5.1: Lasso-Knopf nach Text, Breitenregler weg, Breiten unberührt', knopfUndRegler);
        p('AP-5.1: vierte Ebene canvas.cbd-board-canvas-auswahl (z 4, pointer-events none, DPR)', auswahlEbene);
        p('AP-5.1: Lassolinie gestrichelt auf der Auswahl-Ebene, Modell/Undo/Zeichenebene unberührt', lassolinieWaehrendZiehen);
        p('AP-5.1: zwei Striche + Text gewählt, Drittel-Strich nicht, Rahmen +8 px, Leiste', auswahlregel);
        p('AP-5.1: Knopf "Auswahl aufheben"', auswahlAufhebenKnopf);
        p('AP-5.1: leere Auswahl zeigt nichts, neues Lasso ersetzt alte Auswahl', leereAuswahl);
        p('AP-5.1: Bild-Element - Umranden der Mitte wählt es, Ecke nicht', bildElement);
        p('AP-5.1: Werkzeugwechsel hebt Auswahl auf', werkzeugwechsel);
        p('AP-5.1: ESC mit Auswahl/beim Ziehen hebt nur auf, Tafel bleibt', escMitAuswahl);
        p('AP-5.1: gespeichertes PNG ohne Lassolinie und Rahmen', pngOhneAuswahl);
        p('AP-5.1: zeichneModell() verändert die Auswahl-Ebene nicht', zeichneModellLaesstAuswahl);
        p('AP-5.1: Zoom 150 % - Auswahl, Rahmen und Leiste stimmen', zoom150);
        p('AP-5.1: Kontextmenü-Sperre beim Lasso-Halten und auf der Leiste', kontextmenueBeimHalten);
        p('AP-5.1: Finger-Lasso, Handballen, Pinch-Abbruch, Rechtsklick', fingerUndHandballen);
        p('AP-5.1: Seitenwechsel und Rückgängig heben Auswahl auf', seiteUndUndoHebenAuf);
        p('AP-5.1: Schließen setzt Auswahl/Lasso zurück, Öffnen nicht im Lasso-Modus', schliessenSetztZurueck);
        p('AP-5.1: Auswahlleiste hell/dunkel lesbar', leisteDarkmode);
        p('AP-5.2: Ziehen im Rahmen verschiebt nur Gewähltes (1 Undo, Leiste weg, Cursor move, Rahmen folgt)', ziehenVerschiebtNurGewaehlte);
        p('AP-5.2: Verschiebung aus Momentaufnahme, nicht kumulativ gerundet', ziehenNichtKumulativGerundet);
        p('AP-5.2: 0-px-Verschiebung und Antippen ohne Undo-Eintrag, außerhalb neues Lasso', nullVerschiebungOhneUndo);
        p('AP-5.2: Knopf "Löschen" entfernt die Auswahl (1 Undo)', loeschenPerKnopf);
        p('AP-5.2: Entf und Rücktaste löschen die Auswahl', loeschenPerTaste);
        p('AP-5.2: Entf/Rücktaste im Textfeld bzw. Eingabefeld löschen nicht die Auswahl', entfImTextfeldLoeschtNichtAuswahl);
        p('AP-5.2: Rückgängig je Verschieben und Löschen einzeln, hebt Auswahl auf', undoJeEinzeln);
        p('AP-5.2: Bild-Element (alte Tafel) verschieb- und löschbar', bildElementVerschiebenLoeschen);
        p('AP-5.2: Zoom 150 % - Verschiebung in Element-Koordinaten', zoomBeimZiehen);
        p('AP-5.2: Finger und Maus ziehen, zweiter Finger verwirft das Ziehen', fingerZiehenUndPinch);
        p('AP-5.2: lokal speichern + Wiederöffnen an neuer Stelle, gelöschtes fehlt, Randüberschreitung bleibt', lokalSpeichernUndWiederoeffnen);
    };

    window.__zusatzTestsAsync = function(c) {
        var vorher = (typeof vorherAsync === 'function') ? vorherAsync(c) : Promise.resolve();
        return Promise.resolve(vorher).then(function() {
            ctx = c;
            return c.pruefeAsync('AP-5.1: ESC ohne Auswahl schließt die Tafel wie bisher', escOhneAuswahlSchliesst);
        }).then(function() {
            return c.pruefeAsync('AP-5.2: Zwischenstand folgt dem Ziehen vor dem Abheben (Frame-Bündelung)', zwischenstandImNaechstenFrame);
        }).then(function() {
            return c.pruefeAsync('AP-5.2: Server-Tafel speichert neue Lage, gelöschtes fehlt, Laden an neuer Stelle', serverWegSpeichertNeueLage);
        });
    };
})();
