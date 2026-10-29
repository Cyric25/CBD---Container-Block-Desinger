/*
 * Prüfungen "Formatierter Text auf der Tafel: zeichnen und messen"
 * (PLAN-Blocktext-Formatierung.md, AP-1.3; Entscheidungen F1-F5).
 *
 * Ein Text-Element kann Abschnitte in fett, kursiv und eigener Farbe tragen
 * (Feld teile, Modul board-elemente.js). Diese Prüfungen belegen, dass
 * board-mode.js sie zeichnet und misst:
 *   - Zeichnen: je Teil ein fillText mit Schrift (schrift(px, f, k)), Farbe
 *     und laufendem x; Teile über einen Zeilenwechsel beginnen die neue
 *     Zeile wieder links; gedrehter Text; Pixel (fett dicker, Farbe da)
 *   - Text ohne teile bleibt wie in 3.1.147: ein fillText je Zeile mit
 *     '<px>px sans-serif', Pixel gleich der bisherigen Zeichenroutine
 *   - Messen: _textMass() (Stil fett/kursiv), _rahmen() und
 *     _lassoAbschliessen() messen darüber (Stellvertreter-Probe), echte
 *     Messung: Lasso-Auswahl nach dem Mittelpunkt des fetten Rahmens
 *   - Lasso-Aktionen (A+, Ziehen, Drehen, Duplizieren, Strg+Z) behalten teile
 *   - lokal speichern und wieder öffnen: teile bleibt, wird formatiert gezeichnet
 *   - Strich- und Punkt-Radierer entfernen einen formatierten Text wie
 *     einen unformatierten (der Punkt-Radierer am rechten Ende des fetten
 *     Rahmens beweist die Breitenmessung)
 *   - AP-1.fix1 (Review-Befund B5): zeichneModell() misst in Pass 4 nur Teile,
 *     auf die in derselben Zeile noch ein Teil folgt (ohne teile: keine
 *     measureText-Aufrufe)
 *   - AP-3.1 (Phase 3, F7/F11/F12): Knopf "Text bearbeiten" in der
 *     Auswahlleiste nur bei genau einem gewählten Text (nicht bei zwei
 *     Texten, Strich, Text + Strich, im Drehmodus), Klick ruft
 *     auswahlTextBearbeiten(), Höhenmessung der Leiste mit dem Knopf;
 *     textErsetzen(id, absaetze): gleiche ID und Stelle, ein Undo-Schritt,
 *     Auswahl bleibt, Absätze mit \n, Umbruch am sichtbaren Rand (nur
 *     unverdreht), Teilfarben lesbar, leerer Inhalt entfernt, laufende
 *     Gesten werden beendet
 *   - AP-3.2 (Phase 3, F8/F11/F12): Fenster "Text bearbeiten" -
 *     auswahlTextBearbeiten() öffnet den Text im Bearbeitungsfeld
 *     (Modus 'bearbeiten') samt Formatleiste, "Übernehmen" ruft
 *     textErsetzen(), "Abbrechen"/Escape ändern nichts, Enter = Zeilenwechsel,
 *     Tastatur im Feld wirkt nicht auf die Tafel, Fenster schließt bei
 *     destroy()/close()/Seitenwechsel/aufgehobener Auswahl
 *
 * Wird von tools/test-board-zeiger.html NACH test-board-textgroesse.js und
 * VOR test-board-blocktext.js geladen und hängt sich an den Haken
 * window.__zusatzTests an (vorhandenen zuerst aufrufen).
 */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var E = window.CBDBoardElemente;
    var CID = 'test-zeiger';
    var ctx;

    var vorherSync = window.__zusatzTests;

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

    function texte() {
        return el().filter(function(x) { return x.typ === 'text'; });
    }

    function ergebnis(f) {
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function nah(a, b, tol) {
        return Math.abs(a - b) <= (tol === undefined ? 0.01 : tol);
    }

    // Eigene Zeichenfläche zum unabhängigen Messen (nicht über B._textMass)
    var messCtx = null;
    function messFlaeche() {
        if (!messCtx) messCtx = document.createElement('canvas').getContext('2d');
        return messCtx;
    }

    function messen(text, px, f, k) {
        var g = messFlaeche();
        g.font = (k ? 'italic ' : '') + (f ? 'bold ' : '') + px + 'px sans-serif';
        return g.measureText(text).width;
    }

    /** Schriftangabe so, wie ctx.font sie zurückgibt (Browser normalisiert). */
    function normSchrift(s) {
        var g = messFlaeche();
        g.font = s;
        return g.font;
    }

    /** Text-Element (noch nicht im Modell) mit neuer ID. */
    function neuesText(o) {
        var e = {
            id: B.neueId(), typ: 'text', farbe: o.farbe || '#000000',
            breite: o.breite === undefined ? 2 : o.breite, text: o.text, x: o.x, y: o.y
        };
        if (o.teile) e.teile = o.teile;
        if (o.winkel) e.winkel = o.winkel;
        return e;
    }

    /** Element wie die Konsolenanweisung des Plans einsetzen (mit Undo-Schritt). */
    function einsetzen(e) {
        B.merkeZustand();
        B.modell.elemente = B.modell.elemente.concat([e]);
        B.zeichneModell();
        return e.id;
    }

    /**
     * zeichneModell() ausführen und alle fillText-Aufrufe der Zeichenebene
     * mitschreiben (Schrift, Füllfarbe, Text, x, y, Matrix). Die Hülle liegt
     * als eigene Eigenschaft an der Zeichenebene und wird danach wieder
     * entfernt (die Prototyp-Methode bleibt unverändert).
     */
    function zeichnenMitschreiben() {
        var c = B.drawingCtx;
        var log = [];
        var orig = c.fillText;
        c.fillText = function(t, x, y) {
            var m = this.getTransform();
            log.push({ t: t, x: x, y: y, font: this.font, fill: this.fillStyle,
                m: { a: m.a, b: m.b, e: m.e, f: m.f } });
            return orig.apply(this, arguments);
        };
        try {
            B.zeichneModell();
        } finally {
            delete c.fillText;
        }
        return log;
    }

    function taste(key, extra) {
        var init = { key: key, bubbles: true, cancelable: true };
        for (var k in (extra || {})) init[k] = extra[k];
        var ev = new KeyboardEvent('keydown', init);
        document.dispatchEvent(ev);
        return ev;
    }

    function tippen(x, y) {
        ctx.zeiger('pointerdown', 3, 'pen', x, y);
        ctx.zeiger('pointerup', 3, 'pen', x, y);
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

    /** Lasso großzügig um den Rahmen eines Elements. */
    function lassoUm(id, rand) {
        var r = B._rahmen(element(id));
        rand = rand || 20;
        lasso(r.x - rand, r.y - rand, r.x + r.b + rand, r.y + r.h + rand);
    }

    /** Ziehen mit dem Lasso-Werkzeug (Maus) von (x0,y0) nach (x1,y1). */
    function ziehe(x0, y0, x1, y1) {
        if (B.currentTool !== 'lasso') B.setTool('lasso');
        ctx.zeiger('pointerdown', 8, 'mouse', x0, y0);
        for (var i = 1; i <= 5; i++) {
            ctx.zeiger('pointermove', 8, 'mouse', x0 + (x1 - x0) * i / 5, y0 + (y1 - y0) * i / 5);
        }
        ctx.zeiger('pointerup', 8, 'mouse', x1, y1);
    }

    /** Drehpunkt (px,py) setzen, am Bezugspunkt aufsetzen, nach (bx,by) ziehen. */
    function drehen(px, py, ax, ay, bx, by) {
        if (!B.auswahlDrehenStarten()) throw new Error('Drehmodus startet nicht');
        ctx.zeiger('pointerdown', 8, 'mouse', px, py);
        ctx.zeiger('pointerup', 8, 'mouse', px, py);
        ctx.zeiger('pointerdown', 9, 'mouse', ax, ay);
        for (var i = 1; i <= 6; i++) {
            ctx.zeiger('pointermove', 9, 'mouse', ax + (bx - ax) * i / 6, ay + (by - ay) * i / 6);
        }
        ctx.zeiger('pointerup', 9, 'mouse', bx, by);
    }

    function leiste() {
        return B.overlay ? B.overlay.querySelector('.cbd-board-auswahl-leiste') : null;
    }

    function knopf(aktion) {
        var l = leiste();
        return l ? l.querySelector('[data-aktion="' + aktion + '"]') : null;
    }

    function bild() {
        var c = B.drawingCanvas;
        return c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
    }

    /** Zahl der Pixel mit Alpha > 0. */
    function tinte(daten) {
        var n = 0;
        for (var i = 3; i < daten.length; i += 4) if (daten[i] > 0) n++;
        return n;
    }

    // ------------------------------------------------------------------
    // Zeichnen
    // ------------------------------------------------------------------
    function zeichnenMitTeilen() {
        ctx.oeffnen();
        var f = [];
        var teile = [{ t: 'Es ' }, { t: 'fett', f: 1 }, { t: ' und ' }, { t: 'rot', farbe: '#ff0000' }];
        var e = neuesText({ x: 100, y: 100, breite: 2, text: 'Es fett und rot', teile: teile });
        einsetzen(e);
        var px = E.schriftPx(e.breite);
        if (px !== 24) return { ok: false, detail: 'Testaufbau: Schrift ' + px + ' px statt 24 px' };
        var log = zeichnenMitschreiben();
        if (log.length !== 4) return { ok: false, detail: log.length + ' fillText-Aufrufe statt 4' };
        var soll = ['Es ', 'fett', ' und ', 'rot'];
        var fonts = ['24px sans-serif', 'bold 24px sans-serif', '24px sans-serif', '24px sans-serif'];
        var fills = ['#000000', '#000000', '#000000', '#ff0000'];
        for (var i = 0; i < 4; i++) {
            if (log[i].t !== soll[i]) f.push(i + ': Text "' + log[i].t + '"');
            if (log[i].font !== normSchrift(fonts[i])) f.push(i + ': Schrift "' + log[i].font + '" statt "' + normSchrift(fonts[i]) + '"');
            if (log[i].fill !== fills[i]) f.push(i + ': Farbe "' + log[i].fill + '" statt ' + fills[i]);
            if (!nah(log[i].y, 100)) f.push(i + ': y ' + log[i].y);
        }
        if (!nah(log[0].x, 100)) f.push('x des ersten Teils ' + log[0].x);
        // laufendes x: Breite des vorigen Teils mit DESSEN Schrift gemessen
        var w0 = messen('Es ', 24, 0, 0);
        var w1 = messen('fett', 24, 1, 0);
        var w2 = messen(' und ', 24, 0, 0);
        if (!nah(log[1].x, 100 + w0)) f.push('x von "fett" ' + log[1].x + ' statt ' + (100 + w0));
        if (!nah(log[2].x, 100 + w0 + w1)) f.push('x von " und " ' + log[2].x + ' statt ' + (100 + w0 + w1));
        if (!nah(log[3].x, 100 + w0 + w1 + w2)) f.push('x von "rot" ' + log[3].x + ' statt ' + (100 + w0 + w1 + w2));
        if (!(w1 > messen('fett', 24, 0, 0) + 0.5)) f.push('Testschrift: fett nicht breiter als normal');
        return ergebnis(f);
    }

    function teilUeberZeilenwechsel() {
        ctx.oeffnen();
        var f = [];
        var e = neuesText({ x: 80, y: 60, breite: 1, text: 'ab\ncd', farbe: '#222222',
            teile: [{ t: 'a' }, { t: 'b\nc', f: 1 }, { t: 'd' }] });
        einsetzen(e);
        var px = E.schriftPx(e.breite); // 20
        var log = zeichnenMitschreiben();
        if (log.length !== 4) return { ok: false, detail: log.length + ' fillText-Aufrufe statt 4: ' + JSON.stringify(log.map(function(l) { return l.t; })) };
        var texte = log.map(function(l) { return l.t; }).join('|');
        if (texte !== 'a|b|c|d') f.push('Teile "' + texte + '"');
        var wa = messen('a', px, 0, 0);
        var wc = messen('c', px, 1, 0);
        if (!nah(log[0].x, 80) || !nah(log[0].y, 60)) f.push('a bei ' + log[0].x + '/' + log[0].y);
        if (!nah(log[1].x, 80 + wa) || !nah(log[1].y, 60)) f.push('b bei ' + log[1].x + '/' + log[1].y);
        if (!nah(log[2].x, 80) || !nah(log[2].y, 60 + px * 1.2)) f.push('c bei ' + log[2].x + '/' + log[2].y + ' (neue Zeile links, 1,2 Zeilenhöhen)');
        if (!nah(log[3].x, 80 + wc) || !nah(log[3].y, 60 + px * 1.2)) f.push('d bei ' + log[3].x + '/' + log[3].y);
        if (log[1].font !== normSchrift('bold 20px sans-serif') || log[2].font !== normSchrift('bold 20px sans-serif')) f.push('b/c nicht fett');
        if (log[0].font !== normSchrift('20px sans-serif') || log[3].font !== normSchrift('20px sans-serif')) f.push('a/d nicht normal');
        return ergebnis(f);
    }

    function ohneTeileWieBisher() {
        ctx.oeffnen();
        var f = [];
        var e = neuesText({ x: 60, y: 70, breite: 1, text: 'Zeile eins\nZeile zwei\nDrei', farbe: '#1565c0' });
        einsetzen(e);
        var px = Math.round(16 + e.breite * 4);
        var log = zeichnenMitschreiben();
        var zeilen = e.text.split('\n');
        if (log.length !== zeilen.length) return { ok: false, detail: log.length + ' fillText-Aufrufe statt ' + zeilen.length };
        for (var i = 0; i < zeilen.length; i++) {
            if (log[i].t !== zeilen[i]) f.push(i + ': Text "' + log[i].t + '"');
            if (log[i].font !== normSchrift(px + 'px sans-serif')) f.push(i + ': Schrift "' + log[i].font + '"');
            if (log[i].fill !== '#1565c0') f.push(i + ': Farbe ' + log[i].fill);
            // dieselbe Rechnung wie 3.1.147: ty + li * px * 1.2
            if (log[i].x !== 60 || log[i].y !== 70 + i * px * 1.2) f.push(i + ': Lage ' + log[i].x + '/' + log[i].y);
        }
        // leere Zeile: die folgende Zeile steht zwei Zeilenhöhen tiefer. Ein
        // fillText('') (3.1.147 ruft ihn für leere Zeilen auf, ohne Wirkung)
        // wird nicht mitgezählt - der neue Pfad ruft ihn nicht mehr auf.
        var e2 = neuesText({ x: 60, y: 200, breite: 1, text: 'a\n\nb' });
        einsetzen(e2);
        var l2 = zeichnenMitschreiben().filter(function(l) { return l.y >= 200 && l.t !== ''; });
        if (l2.length !== 2 || l2[0].t !== 'a' || l2[1].t !== 'b' || l2[1].y !== 200 + 2 * px * 1.2) {
            f.push('leere Zeile: ' + JSON.stringify(l2.map(function(l) { return [l.t, l.y]; })));
        }
        return ergebnis(f);
    }

    /** Zeichenroutine von 3.1.147 für ein Text-Element, zum Pixelvergleich. */
    function alteTextRoutine(g, e) {
        var fontSizePx = Math.round(16 + e.breite * 4);
        g.font = fontSizePx + 'px sans-serif';
        g.fillStyle = e.farbe;
        g.textBaseline = 'top';
        var zeilen = e.text.split('\n');
        var tx = e.x, ty = e.y;
        if (e.winkel) {
            g.save();
            g.translate(e.x, e.y);
            g.rotate(e.winkel * Math.PI / 180);
            tx = 0;
            ty = 0;
        }
        for (var li = 0; li < zeilen.length; li++) {
            g.fillText(zeilen[li], tx, ty + li * fontSizePx * 1.2);
        }
        if (e.winkel) g.restore();
    }

    function ohneTeilePixelgleich() {
        ctx.oeffnen();
        var f = [];
        var a = neuesText({ x: 50, y: 60, breite: 2, text: 'Hallo Welt\nzweite Zeile', farbe: '#123456' });
        var b = neuesText({ x: 300, y: 200, breite: 3, text: 'Gedreht\nund mehrzeilig', farbe: '#a00000', winkel: 25 });
        einsetzen(a);
        einsetzen(b);
        var g = B.drawingCtx;
        var diff = -1;
        // Chrome wechselt eine Zeichenfläche nach wiederholtem Auslesen
        // (getImageData) von Grafikkarten- auf Software-Zeichnen; die
        // Kantenglättung der Schrift ändert sich dabei um bis zu 85 Stufen.
        // Erst danach sind zwei Zeichnungen bit-gleich. Deshalb Paare
        // (neuer Pfad, alte Routine) wiederholen, bis ein Paar übereinstimmt;
        // weicht auch das letzte ab, ist die Zahl der abweichenden Bytes der Befund.
        for (var versuch = 0; versuch < 5 && diff !== 0; versuch++) {
            B.zeichneModell();
            var neu = bild();
            if (tinte(neu) < 200) return { ok: false, detail: 'Testaufbau: nur ' + tinte(neu) + ' Pixel gezeichnet' };
            g.save();
            g.setTransform(1, 0, 0, 1, 0, 0);
            g.clearRect(0, 0, B.drawingCanvas.width, B.drawingCanvas.height);
            g.restore();
            alteTextRoutine(g, a);
            alteTextRoutine(g, b);
            var alt = bild();
            diff = 0;
            for (var i = 0; i < alt.length; i++) if (alt[i] !== neu[i]) diff++;
        }
        if (diff) f.push(diff + ' Bytes weichen von der Zeichenroutine aus 3.1.147 ab (5 Versuche)');
        B.zeichneModell();
        return ergebnis(f);
    }

    function gedrehterTextMitTeilen() {
        ctx.oeffnen();
        var f = [];
        var e = neuesText({ x: 200, y: 150, breite: 2, text: 'ab cd', winkel: 90,
            teile: [{ t: 'ab ', f: 1 }, { t: 'cd', farbe: '#0000ff' }] });
        einsetzen(e);
        var log = zeichnenMitschreiben();
        if (log.length !== 2) return { ok: false, detail: log.length + ' fillText-Aufrufe statt 2' };
        var dpr = B.dpr || 1;
        // Teile liegen im gedrehten Koordinatensystem: x/y relativ zur Ecke
        if (!nah(log[0].x, 0) || !nah(log[0].y, 0)) f.push('erster Teil bei ' + log[0].x + '/' + log[0].y);
        if (!nah(log[1].x, messen('ab ', 24, 1, 0)) || !nah(log[1].y, 0)) f.push('zweiter Teil bei ' + log[1].x + '/' + log[1].y);
        for (var i = 0; i < 2; i++) {
            var m = log[i].m;
            if (!nah(m.e / dpr, 200) || !nah(m.f / dpr, 150)) f.push(i + ': Ursprung ' + (m.e / dpr) + '/' + (m.f / dpr));
            if (!nah(m.a / dpr, 0, 1e-9) || !nah(m.b / dpr, 1, 1e-9)) f.push(i + ': nicht um 90 Grad gedreht (a=' + m.a + ', b=' + m.b + ')');
        }
        if (log[1].fill !== '#0000ff') f.push('Farbe des zweiten Teils ' + log[1].fill);
        var r = B._rahmen(element(e.id));
        if (!(r.h > r.b)) f.push('Rahmen des gedrehten Texts nicht hochkant: ' + JSON.stringify(r));
        return ergebnis(f);
    }

    function pixelFettKursivFarbe() {
        ctx.oeffnen();
        var f = [];
        // fett: mehr Tinte als dieselbe Schrift normal
        var norm = neuesText({ x: 40, y: 40, breite: 2, text: 'nnnnnnnn' });
        einsetzen(norm);
        var tNorm = tinte(bild());
        ctx.oeffnen();
        var fett = neuesText({ x: 40, y: 40, breite: 2, text: 'nnnnnnnn', teile: [{ t: 'nnnnnnnn', f: 1 }] });
        einsetzen(fett);
        var tFett = tinte(bild());
        if (!(tNorm > 50)) f.push('Testaufbau: normal nur ' + tNorm + ' Pixel');
        if (!(tFett > tNorm * 1.05)) f.push('fett (' + tFett + ' Pixel) nicht dicker als normal (' + tNorm + ')');
        // kursiv: anderes Bild als normal
        ctx.oeffnen();
        var norm2 = neuesText({ x: 40, y: 40, breite: 2, text: 'HIHIHIHI' });
        einsetzen(norm2);
        var bNorm = bild();
        ctx.oeffnen();
        var kurs = neuesText({ x: 40, y: 40, breite: 2, text: 'HIHIHIHI', teile: [{ t: 'HIHIHIHI', k: 1 }] });
        einsetzen(kurs);
        var bKurs = bild();
        var diff = 0;
        for (var i = 0; i < bNorm.length; i++) if (bNorm[i] !== bKurs[i]) diff++;
        if (diff < 100) f.push('kursiv unterscheidet sich kaum von normal (' + diff + ' Bytes)');
        // Teilfarbe: alle deckenden Pixel rot
        ctx.oeffnen();
        var rot = neuesText({ x: 40, y: 40, breite: 2, text: 'MMMM', farbe: '#000000', teile: [{ t: 'MMMM', farbe: '#ff0000' }] });
        einsetzen(rot);
        var d = bild();
        var deckend = 0, falsch = 0;
        for (var j = 0; j < d.length; j += 4) {
            if (d[j + 3] > 200) {
                deckend++;
                if (!(d[j] > 200 && d[j + 1] < 60 && d[j + 2] < 60)) falsch++;
            }
        }
        if (deckend < 20) f.push('rote Schrift: nur ' + deckend + ' deckende Pixel');
        if (falsch) f.push('rote Schrift: ' + falsch + ' deckende Pixel nicht rot');
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // Messen
    // ------------------------------------------------------------------
    function textMassFunktion() {
        ctx.oeffnen();
        var f = [];
        if (typeof B._textMass !== 'function') return { ok: false, detail: 'B._textMass fehlt' };
        var m = B._textMass();
        if (typeof m !== 'function') return { ok: false, detail: '_textMass() liefert keine Funktion' };
        var t = 'nnnnnnnn';
        if (!nah(m(t, 24), messen(t, 24, 0, 0))) f.push('ohne Stil: ' + m(t, 24));
        if (!nah(m(t, 24, { f: 0, k: 0 }), messen(t, 24, 0, 0))) f.push('normal: ' + m(t, 24, { f: 0, k: 0 }));
        if (!nah(m(t, 24, { f: 1, k: 0 }), messen(t, 24, 1, 0))) f.push('fett: ' + m(t, 24, { f: 1, k: 0 }));
        if (!nah(m(t, 24, { f: 0, k: 1 }), messen(t, 24, 0, 1))) f.push('kursiv: ' + m(t, 24, { f: 0, k: 1 }));
        if (!nah(m(t, 24, { f: 1, k: 1 }), messen(t, 24, 1, 1))) f.push('fett+kursiv: ' + m(t, 24, { f: 1, k: 1 }));
        if (!(m(t, 24, { f: 1, k: 0 }) > m(t, 24, { f: 0, k: 0 }) + 0.5)) f.push('fett nicht breiter als normal');
        if (!nah(m(t, 40), messen(t, 40, 0, 0))) f.push('andere Größe: ' + m(t, 40));
        // ohne Zeichenebene: null (das Modul schätzt dann 0,6 em je Zeichen)
        var g = B.drawingCtx;
        var ohne;
        B.drawingCtx = null;
        try {
            ohne = B._textMass();
        } finally {
            B.drawingCtx = g;
        }
        if (ohne !== null) f.push('ohne drawingCtx nicht null');
        return ergebnis(f);
    }

    function rahmenMitFettKursiv() {
        ctx.oeffnen();
        var f = [];
        var t1 = 'nnnnnnnnnnnnnnnn ', t2 = 'MMM ', t3 = 'iiii';
        var plain = neuesText({ x: 100, y: 100, breite: 2, text: t1 + t2 + t3 });
        var form = neuesText({ x: 100, y: 100, breite: 2, text: t1 + t2 + t3,
            teile: [{ t: t1, f: 1 }, { t: t2 }, { t: t3, k: 1 }] });
        var rp = B._rahmen(plain), rf = B._rahmen(form);
        var soll = messen(t1, 24, 1, 0) + messen(t2, 24, 0, 0) + messen(t3, 24, 0, 1);
        if (!nah(rp.b, messen(t1 + t2 + t3, 24, 0, 0))) f.push('unformatiert ' + rp.b);
        if (!(rf.b > rp.b + 5)) f.push('formatierter Rahmen (' + rf.b + ') nicht breiter als unformatierter (' + rp.b + ')');
        if (!nah(rf.b, soll)) f.push('Breite ' + rf.b + ' statt Summe der Teile ' + soll);
        if (!nah(rf.h, rp.h) || !nah(rf.h, 24 * 1.2)) f.push('Höhe ' + rf.h);
        if (!nah(rf.x, 100) || !nah(rf.y, 100)) f.push('Ecke ' + rf.x + '/' + rf.y);
        // zweizeilig: die breiteste Zeile zählt
        var zwei = neuesText({ x: 10, y: 10, breite: 2, text: 'ii\n' + t1,
            teile: [{ t: 'ii\n' }, { t: t1, f: 1 }] });
        var rz = B._rahmen(zwei);
        if (!nah(rz.b, messen(t1, 24, 1, 0))) f.push('zweizeilig: Breite ' + rz.b);
        if (!nah(rz.h, 2 * 24 * 1.2)) f.push('zweizeilig: Höhe ' + rz.h);
        return ergebnis(f);
    }

    function messenUeberTextMass() {
        ctx.oeffnen();
        var f = [];
        if (typeof B._textMass !== 'function') return { ok: false, detail: 'B._textMass fehlt' };
        var original = B._textMass;
        var stile = [];
        // Stellvertreter: Breite = 10 je Zeichen, +50 fett, +30 kursiv (px egal)
        B._textMass = function() {
            return function(t, px, stil) {
                stile.push((stil ? 'f' + stil.f + 'k' + stil.k : 'ohne') + ':' + t);
                return t.length * 10 + (stil && stil.f ? 50 : 0) + (stil && stil.k ? 30 : 0);
            };
        };
        try {
            var e = neuesText({ x: 100, y: 100, breite: 2, text: 'abcd efgh',
                teile: [{ t: 'abcd ', f: 1 }, { t: 'efgh', k: 1 }] });
            var rb = B._rahmen(e);
            if (!nah(rb.b, 170)) f.push('_rahmen: Breite ' + rb.b + ' statt 170 (50+50 und 40+30)');
            if (stile.join('|') !== 'f1k0:abcd |f0k1:efgh') f.push('_rahmen: Messaufrufe ' + stile.join('|'));
            // _lassoAbschliessen: Mittelpunkt der Breite 170 liegt bei x = 185;
            // mit normaler Messung (90 px) läge er bei 145 und fiele aus dem Lasso
            einsetzen(e);
            stile = [];
            lasso(170, 90, 200, 140);
            if (B.auswahl.join() !== e.id) f.push('Lasso um x 185 wählt "' + B.auswahl.join() + '" statt ' + e.id + ' (misst _lassoAbschliessen nicht über _textMass?)');
            if (!stile.length) f.push('_lassoAbschliessen ruft die Messfunktion von _textMass() nicht');
            B.auswahlAufheben();
            lasso(130, 90, 160, 140);
            if (B.auswahl.length) f.push('Lasso um x 145 wählt trotz Stil-Breite 170');
        } finally {
            B._textMass = original;
        }
        return ergebnis(f);
    }

    function lassoMitEchterMessung() {
        ctx.oeffnen();
        var f = [];
        var t = 'nnnnnnnnnnnnnnnnnnnnnnnnnnnnnn';
        var normal = messen(t, 24, 0, 0), fett = messen(t, 24, 1, 0);
        if (!(fett - normal > 20)) return { ok: false, detail: 'Testschrift: fett nur ' + (fett - normal).toFixed(1) + ' px breiter' };
        var e = neuesText({ x: 100, y: 100, breite: 2, text: t, teile: [{ t: t, f: 1 }] });
        einsetzen(e);
        var mitteFett = 100 + fett / 2, mitteNormal = 100 + normal / 2;
        // Lasso nur um den Mittelpunkt des FETTEN Rahmens
        lasso(mitteFett - 5, 90, mitteFett + 40, 140);
        if (B.auswahl.join() !== e.id) f.push('Lasso um die Mitte des fetten Rahmens (x ' + mitteFett.toFixed(1) + ') wählt nichts');
        B.auswahlAufheben();
        // Lasso nur um die Mitte, die ohne Fettmessung herauskäme
        lasso(mitteNormal - 8, 90, mitteNormal + 4, 140);
        if (B.auswahl.length) f.push('Lasso um die Mitte des unformatierten Rahmens wählt den fetten Text');
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // Lasso-Aktionen, Speichern, Radierer
    // ------------------------------------------------------------------
    function lassoAktionenBehaltenTeile() {
        ctx.oeffnen();
        var f = [];
        var teile = [{ t: 'Wasser ist ' }, { t: 'flüssig', f: 1 }, { t: ' und ', k: 1 }, { t: 'blau', farbe: '#0000ff' }];
        var e = neuesText({ x: 150, y: 150, breite: 2, text: 'Wasser ist flüssig und blau', teile: teile });
        var id = einsetzen(e);
        var soll = JSON.stringify(teile);
        function pruefeTeile(wo, x) {
            if (!x) { f.push(wo + ': Element fehlt'); return; }
            if (JSON.stringify(x.teile) !== soll) f.push(wo + ': teile ' + JSON.stringify(x.teile));
            if (x.text !== e.text) f.push(wo + ': text verändert');
        }
        lassoUm(id);
        if (B.auswahl.join() !== id) return { ok: false, detail: 'Text nicht gewählt: ' + B.auswahl.join() };
        var undoStart = B.undoStapel.length;
        // A+ (Textgröße)
        var px0 = E.schriftPx(element(id).breite);
        knopf('text-groesser').click();
        pruefeTeile('A+', element(id));
        if (E.schriftPx(element(id).breite) <= px0) f.push('A+: Schrift nicht größer');
        // Ziehen mit dem Lasso (aufsetzen mitten im Auswahlrahmen)
        var x0 = element(id).x, y0 = element(id).y;
        var ar = B.auswahlRahmen();
        ziehe(ar.x + ar.b / 2, ar.y + ar.h / 2, ar.x + ar.b / 2 + 40, ar.y + ar.h / 2 + 30);
        pruefeTeile('Ziehen', element(id));
        if (!nah(element(id).x, x0 + 40, 1) || !nah(element(id).y, y0 + 30, 1)) f.push('Ziehen: Ecke ' + element(id).x + '/' + element(id).y + ' statt ' + (x0 + 40) + '/' + (y0 + 30));
        if (B.auswahl.join() !== id) f.push('Ziehen: Auswahl ' + B.auswahl.join());
        // Drehen um die linke obere Ecke um 90 Grad
        var px = element(id).x, py = element(id).y;
        drehen(px, py, px + 100, py, px, py + 100);
        pruefeTeile('Drehen', element(id));
        if (element(id).winkel !== 90) f.push('Drehen: winkel ' + element(id).winkel);
        // Duplizieren
        knopf('duplizieren').click();
        var alle = texte();
        if (alle.length !== 2) {
            f.push('Duplizieren: ' + alle.length + ' Texte');
        } else {
            var kopie = alle[0].id === id ? alle[1] : alle[0];
            if (kopie.id === id) f.push('Kopie ohne neue ID');
            pruefeTeile('Kopie', kopie);
            if (kopie.winkel !== 90) f.push('Kopie: winkel ' + kopie.winkel);
        }
        // Strg+Z bis zum Anfang (Duplizieren, Drehen, Ziehen, A+): das Original
        // ist dieselbe Referenz wie vor den Aktionen
        if (B.undoStapel.length !== undoStart + 4) f.push('Undo-Schritte ' + (B.undoStapel.length - undoStart) + ' statt 4');
        for (var i = 0; i < 4; i++) B.undo();
        if (element(id) !== e) f.push('nach 4x Strg+Z nicht das ursprüngliche Element');
        pruefeTeile('Strg+Z', element(id));
        return ergebnis(f);
    }

    function speichernUndWiederoeffnen() {
        ctx.oeffnen();
        var f = [];
        var teile = [{ t: 'Es ' }, { t: 'fett', f: 1 }, { t: ' und ' }, { t: 'rot', farbe: '#ff0000' }];
        var e = neuesText({ x: 120, y: 90, breite: 2, text: 'Es fett und rot', teile: teile });
        einsetzen(e);
        var soll = JSON.stringify(teile);
        B.saveDrawing();
        B.destroy();
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        var w = texte();
        if (w.length !== 1) return { ok: false, detail: 'nach Wiederöffnen ' + w.length + ' Texte' };
        if (w[0].text !== e.text) f.push('text "' + w[0].text + '"');
        if (JSON.stringify(w[0].teile) !== soll) f.push('teile nach Wiederöffnen ' + JSON.stringify(w[0].teile));
        var log = zeichnenMitschreiben();
        if (log.length !== 4) f.push('nach Wiederöffnen ' + log.length + ' fillText-Aufrufe statt 4');
        else {
            if (log[1].font !== normSchrift('bold 24px sans-serif')) f.push('"fett" nach Wiederöffnen nicht fett: ' + log[1].font);
            if (log[3].fill !== '#ff0000') f.push('"rot" nach Wiederöffnen ' + log[3].fill);
        }
        return ergebnis(f);
    }

    function radiererEntferntFormatiertenText() {
        ctx.oeffnen();
        var f = [];
        var t = 'nnnnnnnnnnnnnnnnnnnnnnnnnnnnnn';
        var e = neuesText({ x: 100, y: 100, breite: 2, text: t, teile: [{ t: t, f: 1 }] });
        var andere = neuesText({ x: 100, y: 300, breite: 2, text: 'bleibt' });
        einsetzen(e);
        einsetzen(andere);
        var r = B._rahmen(e);
        var mitteY = r.y + r.h / 2;
        // Strich-Radierer in der Mitte
        B.setTool('eraser-stroke');
        tippen(r.x + r.b / 2, mitteY);
        if (element(e.id)) f.push('Strich-Radierer entfernt den formatierten Text nicht');
        if (!element(andere.id)) f.push('Strich-Radierer entfernt auch den anderen Text');
        B.undo();
        if (element(e.id) !== e) f.push('Strg+Z stellt den formatierten Text nicht her');
        // Punkt-Radierer am rechten Ende des fetten Rahmens: liegt nur im
        // Rahmen, wenn die Breite mit fetter Schrift gemessen wird
        var normal = messen(t, 24, 0, 0);
        if (!(r.x + r.b - 4 > 100 + normal)) f.push('Testaufbau: rechtes Ende nicht jenseits der normalen Breite');
        B.setTool('eraser-point');
        tippen(r.x + r.b - 4, mitteY);
        if (element(e.id)) f.push('Punkt-Radierer am rechten Ende entfernt den formatierten Text nicht');
        if (!element(andere.id)) f.push('Punkt-Radierer entfernt auch den anderen Text');
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // AP-1.fix1 (Review-Befund B5): beim Zeichnen nicht überflüssig messen
    // ------------------------------------------------------------------
    /**
     * Zahl der measureText-Aufrufe der Zeichenebene während EINES
     * zeichneModell(). Die Hülle liegt als eigene Eigenschaft an der
     * Zeichenebene und wird danach wieder entfernt (wie bei
     * zeichnenMitschreiben).
     */
    function messAufrufeBeimZeichnen() {
        var c = B.drawingCtx;
        var n = 0;
        var orig = c.measureText;
        c.measureText = function() {
            n++;
            return orig.apply(this, arguments);
        };
        try {
            B.zeichneModell();
        } finally {
            delete c.measureText;
        }
        return n;
    }

    /**
     * Pass 4 von zeichneModell() rückt in einer Zeile nur um die Breite eines
     * Teils weiter, wenn danach in derselben Zeile noch ein Teil folgt. Der
     * letzte Teil einer Zeile braucht keine Messung - bei einem Text ohne
     * teile (jede bestehende Tafel) heißt das: gar keine. Vor dem Zählen wird
     * einmal gezeichnet (füllt den Rahmen-Zwischenspeicher), es ist nichts
     * gewählt; gezählt wird die Differenz zu einem leeren Modell.
     */
    function zeichnenMisstNurFolgendeTeile() {
        var f = [];
        ctx.oeffnen();
        B.zeichneModell();
        var leer = messAufrufeBeimZeichnen();

        // A: Text ohne teile, drei Zeilen -> 0 Messungen
        ctx.oeffnen();
        einsetzen(neuesText({ x: 60, y: 60, breite: 1, text: 'eins\nzwei\ndrei' }));
        if (B.auswahl.length) return { ok: false, detail: 'Testaufbau: es ist etwas gewählt' };
        var nA = messAufrufeBeimZeichnen() - leer;
        if (nA !== 0) f.push('A (ohne teile, 3 Zeilen): ' + nA + ' measureText-Aufrufe statt 0');

        // B: eine Zeile aus drei Teilen -> 2 Messungen (nach Teil 1 und 2)
        ctx.oeffnen();
        einsetzen(neuesText({ x: 60, y: 60, breite: 1, text: 'ab cd ef',
            teile: [{ t: 'ab ' }, { t: 'cd ', f: 1 }, { t: 'ef', farbe: '#ff0000' }] }));
        var nB = messAufrufeBeimZeichnen() - leer;
        if (nB !== 2) f.push('B (eine Zeile, 3 Teile): ' + nB + ' measureText-Aufrufe statt 2');

        // C: Teil über den Zeilenwechsel, Zeilen [a, b] und [c, d] -> 2 Messungen
        // (nach "a" und nach "c"); das Zeilenende zählt je Zeile, nicht je Element
        ctx.oeffnen();
        einsetzen(neuesText({ x: 60, y: 60, breite: 1, text: 'ab\ncd',
            teile: [{ t: 'a' }, { t: 'b\nc', f: 1 }, { t: 'd' }] }));
        var nC = messAufrufeBeimZeichnen() - leer;
        if (nC !== 2) f.push('C (Teil über Zeilenwechsel, 2 Zeilen à 2 Teile): ' + nC + ' measureText-Aufrufe statt 2');

        if (B.drawingCtx.hasOwnProperty('measureText')) f.push('measureText-Hülle nicht entfernt');
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // AP-3.1 (Phase 3, Entscheidungen F7, F11, F12): Knopf "Text bearbeiten"
    // in der Auswahlleiste (nur bei genau einem gewählten Text) und
    // textErsetzen(id, absaetze) - ersetzt den Inhalt eines Texts unter
    // derselben ID (x, y, breite, winkel, farbe bleiben), ein Undo-Schritt,
    // Auswahl bleibt; Absätze mit \n verbunden; Umbruch am rechten Rand des
    // sichtbaren Ausschnitts (minus 20 px) nur bei unverdrehten Texten;
    // Teilfarben lesbar (lesbareLauffarbe); leerer Inhalt entfernt das Element.
    // ------------------------------------------------------------------
    var U = window.CBDBoardBlocktext;

    function sichtbar(k) {
        if (!k) return false;
        var cs = getComputedStyle(k);
        if (cs.display === 'none' || cs.visibility === 'hidden') return false;
        var r = k.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
    }

    /** Strich mit der Maus von (x1,y1) nach (x2,y2); liefert seine ID. */
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

    function strgZ() {
        return taste('z', { ctrlKey: true });
    }

    function teileText(x) {
        return x && x.teile !== undefined ? JSON.stringify(x.teile) : 'ohne teile';
    }

    /** Stil je UTF-16-Einheit ('f|k|farbe') eines Texts mit (optionalen) teile. */
    function stilJeZeichen(text, teile) {
        var aus = [];
        var liste = teile || [{ t: text }];
        for (var i = 0; i < liste.length; i++) {
            var s = (liste[i].f ? 1 : 0) + '|' + (liste[i].k ? 1 : 0) + '|' + (liste[i].farbe || '');
            for (var j = 0; j < liste[i].t.length; j++) aus.push(s);
        }
        return aus;
    }

    /** Breite jeder Zeile eines Text-Elements, je Teil in seiner Schrift gemessen. */
    function zeilenBreiten(e) {
        var m = B._textMass();
        var px = E.schriftPx(e.breite);
        return E.zeilenTeile(e).map(function(z) {
            var w = 0;
            for (var i = 0; i < z.length; i++) w += m(z[i].t, px, { f: z[i].f, k: z[i].k });
            return w;
        });
    }

    /**
     * Lange Zeile (60 Wörter, einzelne Leerzeichen) mit einem fetten, einem
     * kursiven und einem roten Abschnitt -> {text, teile}.
     */
    function langeZeile() {
        var woerter = [];
        for (var i = 0; i < 60; i++) woerter.push('Wort' + i);
        var a = woerter.slice(0, 10).join(' ') + ' ';
        var b = woerter.slice(10, 13).join(' ');
        var c = ' ' + woerter.slice(13, 30).join(' ') + ' ';
        var d = woerter.slice(30, 32).join(' ');
        var e = ' ' + woerter.slice(32, 45).join(' ') + ' ';
        var g = woerter[45];
        var h = ' ' + woerter.slice(46).join(' ');
        return {
            text: a + b + c + d + e + g + h,
            teile: [{ t: a }, { t: b, f: 1 }, { t: c }, { t: d, k: 1 }, { t: e }, { t: g, farbe: '#ff0000' }, { t: h }]
        };
    }

    function knopfNurBeiEinemText() {
        ctx.oeffnen();
        var f = [];
        var a = einsetzen(neuesText({ x: 100, y: 100, text: 'Eins' }));
        var b = einsetzen(neuesText({ x: 100, y: 220, text: 'Zwei' }));
        var s = strich(320, 400, 460, 400);
        var kb = knopf('text-bearbeiten');
        if (!kb) return { ok: false, detail: 'Knopf [data-aktion="text-bearbeiten"] fehlt in der Auswahlleiste' };
        // Markup wie im Plan (AP-3.1, Schritt 2), direkt nach "Duplizieren"
        if (kb.tagName !== 'BUTTON' || kb.getAttribute('type') !== 'button') f.push('kein <button type="button">');
        if (!kb.classList.contains('cbd-board-auswahl-knopf') || !kb.classList.contains('cbd-board-auswahl-nur-ein-text')) {
            f.push('Klassen "' + kb.className + '"');
        }
        if (kb.getAttribute('title') !== 'Text bearbeiten') f.push('title "' + kb.getAttribute('title') + '"');
        if (kb.textContent.replace(/\s+/g, ' ').trim() !== 'Text bearbeiten') f.push('Beschriftung "' + kb.textContent + '"');
        if (!kb.querySelector('.dashicons.dashicons-edit')) f.push('Symbol dashicons-edit fehlt');
        if (kb.previousElementSibling !== knopf('duplizieren')) f.push('steht nicht direkt nach "Duplizieren"');
        // genau ein Text
        lassoUm(a);
        if (B.auswahl.join() !== a) return { ok: false, detail: 'Testaufbau: Text nicht gewählt (' + B.auswahl.join() + ')' };
        if (!sichtbar(kb)) f.push('bei genau einem Text nicht sichtbar');
        if (!leiste().classList.contains('cbd-board-auswahl-leiste--ein-text')) f.push('Leiste ohne Klasse --ein-text bei einem Text');
        // zwei Texte
        lasso(60, 60, 260, 300);
        if (B.auswahl.length !== 2) f.push('Testaufbau: zwei Texte nicht gewählt (' + B.auswahl.join() + ')');
        else if (sichtbar(kb)) f.push('bei zwei Texten sichtbar');
        if (leiste().classList.contains('cbd-board-auswahl-leiste--ein-text')) f.push('Klasse --ein-text bei zwei Texten');
        // ein Strich
        lassoUm(s);
        if (B.auswahl.join() !== s) f.push('Testaufbau: Strich nicht gewählt (' + B.auswahl.join() + ')');
        else if (sichtbar(kb)) f.push('bei einem Strich sichtbar');
        // Text und Strich
        lasso(60, 190, 500, 430);
        if (B.auswahl.length !== 2 || B.auswahl.indexOf(b) < 0 || B.auswahl.indexOf(s) < 0) {
            f.push('Testaufbau: Text + Strich nicht gewählt (' + B.auswahl.join() + ')');
        } else if (sichtbar(kb)) {
            f.push('bei Text + Strich sichtbar');
        }
        // Drehmodus: weg, nach "Abbrechen" wieder da
        lassoUm(a);
        if (!sichtbar(kb)) f.push('vor dem Drehmodus nicht sichtbar');
        if (!B.auswahlDrehenStarten()) f.push('Testaufbau: Drehmodus startet nicht');
        if (!sichtbar(leiste())) f.push('Testaufbau: Leiste im Drehmodus unsichtbar');
        if (sichtbar(kb)) f.push('im Drehmodus sichtbar');
        if (leiste().classList.contains('cbd-board-auswahl-leiste--ein-text')) f.push('Klasse --ein-text im Drehmodus');
        B._drehenAbbrechen();
        if (!sichtbar(kb)) f.push('nach "Abbrechen" des Drehmodus nicht wieder sichtbar');
        // Strg+Z hebt die Auswahl auf: Leiste samt Knopf weg
        strgZ();
        if (sichtbar(kb)) f.push('nach Strg+Z sichtbar');
        return ergebnis(f);
    }

    function knopfRuftAuswahlTextBearbeiten() {
        ctx.oeffnen();
        var f = [];
        var a = einsetzen(neuesText({ x: 100, y: 100, text: 'Eins' }));
        lassoUm(a);
        var kb = knopf('text-bearbeiten');
        if (!kb) return { ok: false, detail: 'Knopf [data-aktion="text-bearbeiten"] fehlt' };
        if (typeof B.auswahlTextBearbeiten !== 'function') f.push('B.auswahlTextBearbeiten ist keine Funktion');
        // Stellvertreter: nur der Aufruf aus der Klick-Weiche wird geprüft
        // (den Inhalt der Methode baut AP-3.2)
        var hatte = Object.prototype.hasOwnProperty.call(B, 'auswahlTextBearbeiten');
        var original = B.auswahlTextBearbeiten;
        var aufrufe = 0;
        B.auswahlTextBearbeiten = function() { aufrufe++; };
        var vorher = el().slice(), undo0 = B.undoStapel.length;
        try {
            kb.click();
        } finally {
            if (hatte) B.auswahlTextBearbeiten = original;
            else delete B.auswahlTextBearbeiten;
        }
        if (aufrufe !== 1) f.push('Klick ruft auswahlTextBearbeiten() ' + aufrufe + '-mal statt einmal');
        if (el().length !== vorher.length || el()[0] !== vorher[0] || B.undoStapel.length !== undo0) f.push('Klick ändert die Tafel');
        if (B.auswahl.join() !== a) f.push('Auswahl nach dem Klick ' + B.auswahl.join());
        return ergebnis(f);
    }

    /**
     * _auswahlLeisteHoeheText() (Folge-AP G9, Platz über eingefügtem Text)
     * misst konservativ MIT dem Knopf "Text bearbeiten" (Klasse --ein-text).
     * Gesucht wird eine Höchstbreite, bei der der Knopf die Leiste um eine
     * Zeile höher macht; dort muss die Messung die höhere Leiste liefern -
     * auch wenn die Leiste gerade OHNE den Knopf steht (zwei Texte gewählt;
     * so beim Einfügen über "Text auf die Tafel", die Klasse hängt dann
     * nicht schon an der Leiste).
     */
    function leistenHoeheMitKnopf() {
        ctx.oeffnen();
        var f = [];
        var l = leiste();
        if (!knopf('text-bearbeiten')) return { ok: false, detail: 'Knopf [data-aktion="text-bearbeiten"] fehlt' };
        var anzeige = l.querySelector('.cbd-board-auswahl-textgroesse');
        // Leiste so wie in _auswahlLeisteHoeheText (unsichtbar, Anzeige "200 px")
        function hoehe(w, einText) {
            var vorher = { css: l.style.cssText, klassen: l.className, anzeige: anzeige ? anzeige.textContent : '' };
            l.style.visibility = 'hidden';
            l.style.display = 'flex';
            l.style.maxWidth = w + 'px';
            l.className = 'cbd-board-auswahl-leiste cbd-board-auswahl-leiste--text' + (einText ? ' cbd-board-auswahl-leiste--ein-text' : '');
            if (anzeige) anzeige.textContent = '200 px';
            var h = l.offsetHeight;
            l.style.cssText = vorher.css;
            l.className = vorher.klassen;
            if (anzeige) anzeige.textContent = vorher.anzeige;
            return h;
        }
        var a = einsetzen(neuesText({ x: 100, y: 200, text: 'Eins' }));
        einsetzen(neuesText({ x: 100, y: 300, text: 'Zwei' }));
        var w = 0;
        for (var b = 200; b <= 1400 && !w; b += 10) {
            if (hoehe(b, true) > hoehe(b, false)) w = b;
        }
        if (!w) return { ok: false, detail: 'Testaufbau: keine Breite, bei der der Knopf die Leiste höher macht' };
        var echt = B._auswahlLeisteMaxBreite;
        var soll, ohne, ist, klassenVorher, klassenNachher, gezeigt, istEin;
        B._auswahlLeisteMaxBreite = function() { return w; };
        try {
            // zwei Texte gewählt: Leiste steht ohne "Text bearbeiten"
            lasso(60, 160, 300, 360);
            if (B.auswahl.length !== 2) return { ok: false, detail: 'Testaufbau: zwei Texte nicht gewählt (' + B.auswahl.join() + ')' };
            soll = hoehe(w, true);
            ohne = hoehe(w, false);
            klassenVorher = l.className;
            ist = B._auswahlLeisteHoeheText();
            klassenNachher = l.className;
            // ein Text gewählt: die gezeigte Leiste ist nie höher als gemessen
            lassoUm(a);
            gezeigt = l.offsetHeight;
            istEin = B._auswahlLeisteHoeheText();
        } finally {
            B._auswahlLeisteMaxBreite = echt;
        }
        if (klassenVorher.indexOf('--ein-text') >= 0) f.push('Testaufbau: Klasse --ein-text bei zwei Texten');
        if (!(soll > ohne)) f.push('Testaufbau: an der Leistenlage macht der Knopf sie nicht höher (' + soll + '/' + ohne + ')');
        if (ist !== soll) f.push('_auswahlLeisteHoeheText() ' + ist + ' px statt ' + soll + ' px (mit "Text bearbeiten", Höchstbreite ' + w + ' px; ohne Knopf ' + ohne + ' px)');
        if (klassenNachher !== klassenVorher) f.push('Klassen danach "' + klassenNachher + '" statt "' + klassenVorher + '"');
        if (B.auswahl.join() !== a) f.push('Testaufbau: ein Text nicht gewählt');
        else if (!(istEin >= gezeigt)) f.push('gemessen ' + istEin + ' px, gezeigte Leiste bei einem Text ' + gezeigt + ' px');
        return ergebnis(f);
    }

    function textErsetzenGrundfall() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        var davor = einsetzen(neuesText({ x: 420, y: 420, text: 'Davor' }));
        var e = neuesText({ x: 120, y: 140, breite: 3, text: 'Alt', farbe: '#1565c0', winkel: 15 });
        var id = einsetzen(e);
        strich(300, 560, 420, 560);
        lassoUm(id);
        if (B.auswahl.join() !== id) return { ok: false, detail: 'Testaufbau: Text nicht gewählt (' + B.auswahl.join() + ')' };
        var liste = el().slice();
        var i0 = liste.indexOf(e);
        var undo0 = B.undoStapel.length;
        var r = B.textErsetzen(id, [{ text: 'Neu fett', teile: [{ t: 'Neu ' }, { t: 'fett', f: 1 }] }]);
        var n = element(id);
        if (r !== true) f.push('Rückgabe ' + r + ' statt true');
        if (!n) return { ok: false, detail: 'Element mit derselben ID fehlt danach' };
        if (n === e) return { ok: false, detail: 'Element unverändert' };
        if (el().length !== liste.length) f.push(el().length + ' Elemente statt ' + liste.length);
        if (el().indexOf(n) !== i0) f.push('Stelle in der Liste ' + el().indexOf(n) + ' statt ' + i0);
        for (var k = 0; k < liste.length; k++) {
            if (k !== i0 && el()[k] !== liste[k]) f.push('anderes Element ' + k + ' verändert');
        }
        if (n.typ !== 'text' || n.text !== 'Neu fett') f.push('text "' + n.text + '"');
        if (teileText(n) !== '[{"t":"Neu "},{"t":"fett","f":1}]') f.push('teile ' + teileText(n));
        if (n.x !== 120 || n.y !== 140 || n.breite !== 3 || n.winkel !== 15 || n.farbe !== '#1565c0') {
            f.push('Lage/Größe/Drehung/Farbe ' + JSON.stringify([n.x, n.y, n.breite, n.winkel, n.farbe]));
        }
        if (Object.keys(n).join() !== 'id,typ,farbe,breite,text,x,y,teile,winkel') f.push('Schlüssel ' + Object.keys(n).join());
        if (B.undoStapel.length !== undo0 + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undo0) + ' statt 1');
        if (B.auswahl.join() !== id) f.push('Auswahl ' + B.auswahl.join() + ' statt ' + id);
        if (!sichtbar(leiste()) || !sichtbar(knopf('text-bearbeiten'))) f.push('Leiste mit "Text bearbeiten" danach nicht sichtbar');
        // Die Modellprüfung (Speichern/Laden) lässt das Element, wie es ist
        var p = E.pruefeModell(B.modell);
        if (!p || !p.modell || JSON.stringify(p.modell.elemente[i0]) !== JSON.stringify(n)) f.push('pruefeModell ändert das Element');
        if (element(davor) !== liste[0]) f.push('"Davor" verändert');
        // gezeichnet: "fett" fett in der Schrift des Elements (breite 3 = 28 px)
        var log = zeichnenMitschreiben().filter(function(x) { return x.t === 'fett'; });
        if (log.length !== 1 || log[0].font !== normSchrift('bold 28px sans-serif')) f.push('"fett" gezeichnet: ' + JSON.stringify(log.map(function(x) { return x.font; })));
        // Strg+Z stellt den alten Text her
        strgZ();
        if (element(id) !== e) f.push('Strg+Z: nicht das alte Element (' + (element(id) && element(id).text) + ')');
        if (B.undoStapel.length !== undo0) f.push('Undo-Stapel nach Strg+Z ' + B.undoStapel.length + ' statt ' + undo0);
        return ergebnis(f);
    }

    function textErsetzenOhneAenderung() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        var e = neuesText({ x: 100, y: 100, text: 'Gleich fett', teile: [{ t: 'Gleich ' }, { t: 'fett', f: 1 }] });
        var id = einsetzen(e);
        var s = strich(300, 400, 420, 400);
        var vorher = el().slice();
        var undo0 = B.undoStapel.length;
        // gleicher Inhalt (anders zerlegt): keine Änderung, kein Undo-Eintrag
        var r = B.textErsetzen(id, [{ text: 'Gleich fett', teile: [{ t: 'Gleich ' }, { t: 'fe', f: 1 }, { t: 'tt', f: 1 }] }]);
        if (r !== false) f.push('gleicher Inhalt: Rückgabe ' + r + ' statt false');
        // unbekannte ID, Strich, Eingabe kein Array: nichts
        var faelle = [['unbekannte ID', 'gibt-es-nicht', ['x']], ['Strich', s, ['x']], ['null', id, null],
            ['undefined', id, undefined], ['Zahl', id, 5], ['Zeichenkette', id, 'x'], ['Objekt', id, { text: 'x' }]];
        for (var i = 0; i < faelle.length; i++) {
            var erg = B.textErsetzen(faelle[i][1], faelle[i][2]);
            if (erg !== false) f.push(faelle[i][0] + ': Rückgabe ' + erg + ' statt false');
        }
        if (el().length !== vorher.length) f.push('Zahl der Elemente ' + el().length);
        for (var k = 0; k < vorher.length; k++) {
            if (el()[k] !== vorher[k]) f.push('Element ' + k + ' verändert');
        }
        if (B.undoStapel.length !== undo0) f.push('Undo-Einträge ' + (B.undoStapel.length - undo0) + ' statt 0');
        return ergebnis(f);
    }

    function textErsetzenMehrereAbsaetze() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        var id = einsetzen(neuesText({ x: 100, y: 100, text: 'Alt' }));
        B.textErsetzen(id, [{ text: 'Eins', teile: [{ t: 'Eins', f: 1 }] }, 'Zwei', { text: 'Drei', teile: [{ t: 'Drei', farbe: '#ff0000' }] }]);
        var n = element(id);
        if (n.text !== 'Eins\nZwei\nDrei') f.push('drei Absätze: text ' + JSON.stringify(n.text));
        if (teileText(n) !== JSON.stringify([{ t: 'Eins', f: 1 }, { t: '\nZwei\n' }, { t: 'Drei', farbe: '#ff0000' }])) f.push('drei Absätze: teile ' + teileText(n));
        // ohne Formatierung: kein Feld teile
        B.textErsetzen(id, ['a', { text: 'b' }, { text: 'c', teile: [{ t: 'c' }] }]);
        n = element(id);
        if (n.text !== 'a\nb\nc' || Object.prototype.hasOwnProperty.call(n, 'teile')) f.push('Klartext: ' + JSON.stringify(n.text) + ', ' + teileText(n));
        // Einträge ohne sichtbaren Text zählen nicht (wie bei blocktextEinfuegen)
        B.textErsetzen(id, [{ text: 'x' }, null, { text: '   ' }, {}, { text: 5 }, ' \n ', 'y']);
        if (element(id).text !== 'x\ny') f.push('leere Einträge: ' + JSON.stringify(element(id).text));
        // vorhandene Zeilenwechsel in einem Absatz bleiben
        B.textErsetzen(id, [{ text: 'p\nq', teile: [{ t: 'p\n', k: 1 }, { t: 'q' }] }, 'r']);
        n = element(id);
        if (n.text !== 'p\nq\nr' || teileText(n) !== JSON.stringify([{ t: 'p\n', k: 1 }, { t: 'q\nr' }])) f.push('Zeilenwechsel im Absatz: ' + JSON.stringify(n.text) + ', ' + teileText(n));
        // unbrauchbare teile -> Absatz als Klartext
        B.textErsetzen(id, [{ text: 'ab', teile: [{ t: 'a', f: 1 }] }, { text: 'cd', teile: [null, { t: 'cd', f: 1 }] },
            { text: 'gh', teile: 'fett' }, { text: 'ef', teile: [{ t: 'ef', k: 1 }] }]);
        n = element(id);
        if (n.text !== 'ab\ncd\ngh\nef' || teileText(n) !== JSON.stringify([{ t: 'ab\ncd\ngh\n' }, { t: 'ef', k: 1 }])) {
            f.push('unbrauchbare teile: ' + JSON.stringify(n.text) + ', ' + teileText(n));
        }
        return ergebnis(f);
    }

    function textErsetzenUmbruch() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        var x = 100;
        var id = einsetzen(neuesText({ x: x, y: 60, breite: 3.5, text: 'kurz' }));
        var px = E.schriftPx(3.5); // 30 px - Schrift des Elements, nicht die 24 px von "Text auf die Tafel"
        var s = B._sichtbarerBereich();
        var grenze = s.x + s.b - 20 - x;
        var lang = langeZeile();
        var absatz = { text: 'Erste Zeile\n' + lang.text + '\nEnde', teile: [{ t: 'Erste Zeile\n' }].concat(lang.teile, [{ t: '\nEnde' }]) };
        var zeilen = U.umbrechenTeile(lang, grenze, px, B._textMass());
        if (zeilen.length < 4) return { ok: false, detail: 'Testaufbau: lange Zeile ergibt nur ' + zeilen.length + ' Zeilen bei ' + grenze + ' px' };
        var undo0 = B.undoStapel.length;
        B.textErsetzen(id, [absatz]);
        var n = element(id);
        var soll = 'Erste Zeile\n' + zeilen.map(function(z) { return z.text; }).join('\n') + '\nEnde';
        if (n.text !== soll) f.push('text ' + JSON.stringify(n.text.slice(0, 120)) + ' statt ' + JSON.stringify(soll.slice(0, 120)));
        if (n.text.split('\n')[0] !== 'Erste Zeile' || n.text.split('\n').pop() !== 'Ende') f.push('vorhandene Zeilen nicht erhalten');
        zeilenBreiten(n).forEach(function(w, i) {
            if (w > grenze + 0.01) f.push('Zeile ' + i + ': ' + w.toFixed(1) + ' px > ' + grenze.toFixed(1) + ' px');
        });
        // nur die Leerzeichen an den Umbruchstellen wurden Zeilenwechsel,
        // die Formatierung jedes Zeichens bleibt
        if (n.text.replace(/\n/g, ' ') !== absatz.text.replace(/\n/g, ' ')) f.push('Text über den Umbruch hinaus verändert');
        var kette = (n.teile || []).map(function(t) { return t.t; }).join('');
        if (kette !== n.text) f.push('Verkettung der teile ist nicht der text');
        var vorStil = stilJeZeichen(absatz.text, absatz.teile), nachStil = stilJeZeichen(n.text, n.teile);
        var abweichend = 0;
        for (var i = 0; i < n.text.length; i++) {
            if (n.text.charAt(i) !== '\n' && nachStil[i] !== vorStil[i]) abweichend++;
        }
        if (abweichend) f.push(abweichend + ' Zeichen mit anderer Formatierung');
        var r = B._rahmen(n);
        if (r.x + r.b > s.x + s.b - 20 + 0.01) f.push('Rahmen endet bei ' + (r.x + r.b).toFixed(1) + ', Rand ' + (s.x + s.b - 20).toFixed(1));
        if (B.undoStapel.length !== undo0 + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undo0));
        return ergebnis(f);
    }

    function textErsetzenUmbruchSichtbarerAusschnitt() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        B.zoom = 2;
        B.panX = -300;
        B.panY = -100;
        B.updateCanvasTransform();
        var s = B._sichtbarerBereich();
        if (Math.abs(s.x - 150) > 0.01 || Math.abs(s.y - 50) > 0.01) return { ok: false, detail: 'Testaufbau: Ausschnitt ' + JSON.stringify(s) };
        var x = s.x + 40;
        var id = einsetzen(neuesText({ x: x, y: s.y + 40, breite: 2, text: 'kurz' }));
        var grenze = s.x + s.b - 20 - x;
        if (!(B.cssWidth - 20 - x > grenze + 50)) return { ok: false, detail: 'Testaufbau: Ausschnitt nicht schmaler als die Zeichenfläche' };
        var lang = langeZeile();
        B.textErsetzen(id, [lang]);
        var n = element(id);
        var soll = U.umbrechenTeile(lang, grenze, 24, B._textMass()).map(function(z) { return z.text; }).join('\n');
        if (n.text !== soll) f.push('nicht am Rand des sichtbaren Ausschnitts umbrochen: ' + JSON.stringify(n.text.slice(0, 80)));
        zeilenBreiten(n).forEach(function(w, i) {
            if (w > grenze + 0.01) f.push('Zeile ' + i + ': ' + w.toFixed(1) + ' px > ' + grenze.toFixed(1) + ' px');
        });
        return ergebnis(f);
    }

    function textErsetzenGedrehtOhneUmbruch() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        var id = einsetzen(neuesText({ x: 100, y: 60, breite: 3.5, text: 'kurz', winkel: 30 }));
        var s = B._sichtbarerBereich();
        var lang = langeZeile();
        B.textErsetzen(id, [lang]);
        var n = element(id);
        if (n.text !== lang.text) f.push('gedrehter Text umbrochen: ' + JSON.stringify(n.text.slice(0, 80)));
        if (teileText(n) !== JSON.stringify(lang.teile)) f.push('teile ' + teileText(n));
        if (n.winkel !== 30) f.push('winkel ' + n.winkel);
        if (!(zeilenBreiten(n)[0] > s.x + s.b - 20 - 100)) f.push('Testaufbau: Zeile nicht breiter als der Rand');
        return ergebnis(f);
    }

    function textErsetzenLeer() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        var e = neuesText({ x: 100, y: 100, text: 'Weg damit', teile: [{ t: 'Weg ' }, { t: 'damit', f: 1 }] });
        var id = einsetzen(e);
        var bleibt = einsetzen(neuesText({ x: 100, y: 300, text: 'bleibt' }));
        [[], [{ text: '   ' }], [' \n ', { text: '\n', teile: [{ t: '\n', f: 1 }] }]].forEach(function(leer, nr) {
            lassoUm(id);
            if (B.auswahl.join() !== id) {
                f.push(nr + ': Testaufbau, Auswahl ' + B.auswahl.join());
                return;
            }
            var undo0 = B.undoStapel.length;
            var r = B.textErsetzen(id, leer);
            if (r !== true) f.push(nr + ': Rückgabe ' + r + ' statt true');
            if (element(id)) f.push(nr + ': Element nicht entfernt');
            if (!element(bleibt)) f.push(nr + ': anderes Element entfernt');
            if (B.undoStapel.length !== undo0 + 1) f.push(nr + ': Undo-Schritte ' + (B.undoStapel.length - undo0) + ' statt 1');
            if (B.auswahl.length) f.push(nr + ': Auswahl ' + B.auswahl.join());
            if (sichtbar(leiste())) f.push(nr + ': Auswahlleiste noch sichtbar');
            strgZ();
            if (element(id) !== e) f.push(nr + ': Strg+Z holt das Element nicht zurück');
        });
        return ergebnis(f);
    }

    function textErsetzenTeilfarbenLesbar() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        var gruen = '#1a472a';
        B.setBoardColor(gruen);
        var id = einsetzen(neuesText({ x: 100, y: 100, text: 'Alt', farbe: '#ffffff' }));
        if (U.lesbareLauffarbe('#71230a', gruen) === '#71230a') return { ok: false, detail: 'Testaufbau: Dunkelrot auf Grün schon lesbar' };
        B.textErsetzen(id, [{ text: 'dunkelrot rot schwarz blau', teile: [{ t: 'dunkelrot ', farbe: '#71230a' },
            { t: 'rot ', farbe: '#ff0000' }, { t: 'schwarz ', farbe: '#000000' }, { t: 'blau', farbe: 'blau' }] }]);
        var soll = E.teileZusammenlegen([
            { t: 'dunkelrot ', farbe: U.lesbareLauffarbe('#71230a', gruen) },
            { t: 'rot ', farbe: U.lesbareLauffarbe('#ff0000', gruen) },
            { t: 'schwarz ', farbe: U.lesbareLauffarbe('#000000', gruen) },
            { t: 'blau' } // keine gültige Farbe -> ohne
        ]);
        if (teileText(element(id)) !== JSON.stringify(soll)) f.push('grüne Tafel: ' + teileText(element(id)) + ' statt ' + JSON.stringify(soll));
        // weiße Tafel: Rot und Dunkelrot bleiben, Weiß wird lesbar
        B.setBoardColor('#ffffff');
        B.textErsetzen(id, [{ text: 'rot dunkel weiß', teile: [{ t: 'rot ', farbe: '#ff0000' }, { t: 'dunkel ', farbe: '#71230a' }, { t: 'weiß', farbe: '#ffffff' }] }]);
        var soll2 = JSON.stringify([{ t: 'rot ', farbe: '#ff0000' }, { t: 'dunkel ', farbe: '#71230a' }, { t: 'weiß', farbe: U.lesbareLauffarbe('#ffffff', '#ffffff') }]);
        if (teileText(element(id)) !== soll2) f.push('weiße Tafel: ' + teileText(element(id)) + ' statt ' + soll2);
        return ergebnis(f);
    }

    function textErsetzenBeendetGesten() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.textErsetzen !== 'function') return { ok: false, detail: 'B.textErsetzen fehlt' };
        var id = einsetzen(neuesText({ x: 100, y: 100, text: 'Alt' }));
        lassoUm(id);
        // Drehmodus (Drehpunkt noch nicht gesetzt) endet
        if (!B.auswahlDrehenStarten()) return { ok: false, detail: 'Testaufbau: Drehmodus startet nicht' };
        B.textErsetzen(id, [{ text: 'Neu' }]);
        if (B._drehen) f.push('Drehmodus läuft nach textErsetzen weiter');
        if (element(id).text !== 'Neu') f.push('im Drehmodus nicht ersetzt');
        // laufendes Ziehen (aufgesetzt und bewegt, nicht abgehoben): die
        // erreichte Lage gilt, das Ersetzen ist ein eigener Undo-Schritt
        var ar = B.auswahlRahmen();
        var mx = ar.x + ar.b / 2, my = ar.y + ar.h / 2;
        var undo0 = B.undoStapel.length;
        ctx.zeiger('pointerdown', 8, 'mouse', mx, my);
        for (var i = 1; i <= 3; i++) ctx.zeiger('pointermove', 8, 'mouse', mx + 10 * i, my + 10 * i);
        if (!B._ziehen) return { ok: false, detail: 'Testaufbau: Ziehen läuft nicht' };
        B.textErsetzen(id, [{ text: 'Neuer' }]);
        var n = element(id);
        if (B._ziehen) f.push('Ziehen läuft nach textErsetzen weiter');
        if (n.text !== 'Neuer' || n.x !== 130 || n.y !== 130) f.push('nach dem Ziehen: ' + JSON.stringify([n.text, n.x, n.y]) + ' statt ["Neuer",130,130]');
        if (B.undoStapel.length !== undo0 + 2) f.push('Undo-Schritte ' + (B.undoStapel.length - undo0) + ' statt 2 (Ziehen, Ersetzen)');
        if (B.auswahl.join() !== id) f.push('Auswahl ' + B.auswahl.join());
        strgZ();
        if (!element(id) || element(id).text !== 'Neu' || element(id).x !== 130) f.push('1. Strg+Z: ' + JSON.stringify(element(id) && [element(id).text, element(id).x]));
        strgZ();
        if (!element(id) || element(id).text !== 'Neu' || element(id).x !== 100) f.push('2. Strg+Z: ' + JSON.stringify(element(id) && [element(id).text, element(id).x]));
        return ergebnis(f);
    }

    // ------------------------------------------------------------------
    // AP-3.2: Fenster "Text bearbeiten" (F8, F11, F12). Der Knopf der
    // Auswahlleiste ruft auswahlTextBearbeiten(); das Fenster zeigt den Text
    // des gewählten Elements im Bearbeitungsfeld (_textFeldBauen, Modus
    // 'bearbeiten') mit Formatleiste, "Übernehmen" ruft textErsetzen().
    // ------------------------------------------------------------------
    var TB_MELDUNG = 'Ändere den Text. Markiere Wörter, um sie fett, kursiv oder farbig zu machen.';

    function tbFenster() {
        return document.querySelector('.cbd-board-text-bearbeiten');
    }

    function tbFeld() {
        var w = tbFenster();
        return w ? w.querySelector('.cbd-board-blocktext-feld') : null;
    }

    /** Knopf unten im Fenster ("Abbrechen", "Übernehmen") nach Beschriftung. */
    function tbKnopf(beschriftung) {
        var w = tbFenster();
        if (!w) return null;
        var k = w.querySelectorAll('.cbd-board-confirm-actions button');
        for (var i = 0; i < k.length; i++) if (k[i].textContent === beschriftung) return k[i];
        return null;
    }

    function tbFormatKnopf(selektor) {
        var w = tbFenster();
        return w ? w.querySelector('.cbd-board-blocktext-format ' + selektor) : null;
    }

    /** Text wählen (Lasso) und mit dem ECHTEN Klick auf den Knopf der Leiste bearbeiten. */
    function tbOeffnen(id) {
        lassoUm(id);
        if (B.auswahl.join() !== id) throw new Error('Testaufbau: Text nicht gewählt (' + B.auswahl.join() + ')');
        var k = knopf('text-bearbeiten');
        if (!k) throw new Error('Knopf "Text bearbeiten" fehlt');
        k.click();
        return tbFeld();
    }

    function tasteAn(ziel, key, extra) {
        var init = { key: key, bubbles: true, cancelable: true };
        for (var k in (extra || {})) init[k] = extra[k];
        var ev = new KeyboardEvent('keydown', init);
        ziel.dispatchEvent(ev);
        return ev;
    }

    /** Erster Textknoten im Feld, der wort enthält -> {knoten, start}. */
    function tbTextStelle(feld, wort) {
        var w = document.createTreeWalker(feld, NodeFilter.SHOW_TEXT, null, false);
        for (var n = w.nextNode(); n; n = w.nextNode()) {
            var i = n.nodeValue.indexOf(wort);
            if (i >= 0) return { knoten: n, start: i };
        }
        return null;
    }

    /** Markierung per Range auf wort (ende: eingeklappt hinter wort) - wie ein Doppelklick. */
    function tbMarkiere(feld, wort, ende) {
        var s = tbTextStelle(feld, wort);
        if (!s) throw new Error('Wort ' + JSON.stringify(wort) + ' nicht im Feld');
        feld.focus();
        var r = document.createRange();
        r.setStart(s.knoten, ende ? s.start + wort.length : s.start);
        r.setEnd(s.knoten, s.start + wort.length);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
    }

    /** Berechneter Stil des Elements, in dem wort steht. */
    function tbStil(feld, wort) {
        var s = tbTextStelle(feld, wort);
        return s ? getComputedStyle(s.knoten.parentNode) : null;
    }

    function tbRgb(hex) {
        return 'rgb(' + parseInt(hex.substr(1, 2), 16) + ', ' + parseInt(hex.substr(3, 2), 16) + ', ' + parseInt(hex.substr(5, 2), 16) + ')';
    }

    /** Text am Ende des Felds anhängen, über DOM (Plan AP-3.2, Prüfungen). */
    function tbAnhaengen(feld, text) {
        feld.firstChild.appendChild(document.createTextNode(text));
    }

    function tbSchildDa() {
        return !!document.querySelector('.cbd-board-blocktext-schild');
    }

    var TB_TEILE = [{ t: 'Alt ' }, { t: 'fett ', f: 1 }, { t: 'kursiv ', k: 1 }, { t: 'rot', farbe: '#ff0000' }];
    var TB_TEXT = 'Alt fett kursiv rot';

    // Das Fenster zeigt den Text samt Formatierung, Formatleiste, Knöpfe,
    // Schreibmarke am ENDE, Fokus im Feld; das Öffnen ändert nichts.
    function tbFensterZeigtText() {
        ctx.oeffnen();
        var f = [];
        var e = neuesText({ x: 120, y: 140, breite: 3, text: TB_TEXT, farbe: '#1565c0', teile: TB_TEILE });
        var id = einsetzen(e);
        if (tbFenster()) return { ok: false, detail: 'Testaufbau: Fenster schon offen' };
        var liste = el().slice();
        var undo0 = B.undoStapel.length;
        var feld = tbOeffnen(id);
        var w = tbFenster();
        if (!w || !feld) return { ok: false, detail: 'kein Fenster .cbd-board-text-bearbeiten mit Feld nach dem Klick' };
        if (!w.classList.contains('cbd-board-blocktext-overlay')) f.push('Fenster ohne Klasse cbd-board-blocktext-overlay');
        var h = w.querySelector('h4'), p = w.querySelector('p');
        if (!h || h.textContent !== 'Text bearbeiten') f.push('Titel ' + (h && JSON.stringify(h.textContent)));
        if (!p || p.textContent !== TB_MELDUNG) f.push('Meldung ' + (p && JSON.stringify(p.textContent)));
        var dialog = w.querySelector('.cbd-board-confirm-dialog');
        if (!dialog || dialog.getAttribute('role') !== 'dialog' || dialog.getAttribute('aria-modal') !== 'true') f.push('Dialog ohne role/aria-modal');
        if (feld.getAttribute('contenteditable') !== 'true' || feld.getAttribute('data-modus') !== 'bearbeiten') f.push('Feld nicht im Modus bearbeiten');
        if (feld.getAttribute('aria-label') !== 'Text bearbeiten') f.push('aria-label ' + feld.getAttribute('aria-label'));
        if (feld.children.length !== 1 || feld.children[0].tagName !== 'DIV') f.push('Feld hat ' + feld.children.length + ' Kinder statt einem div');
        // Der Text so, wie er auf die Tafel kommt: Tafelfarbe, Farbe des Elements, Stile
        var cs = getComputedStyle(feld);
        if (cs.backgroundColor !== tbRgb('#ffffff')) f.push('Hintergrund ' + cs.backgroundColor);
        if (cs.color !== tbRgb('#1565c0')) f.push('Grundschrift ' + cs.color + ' statt der Farbe des Elements');
        var fett = tbStil(feld, 'fett '), kursiv = tbStil(feld, 'kursiv '), rot = tbStil(feld, 'rot');
        if (!fett || fett.fontWeight !== '700') f.push('"fett" nicht fett (' + (fett && fett.fontWeight) + ')');
        if (!kursiv || kursiv.fontStyle !== 'italic') f.push('"kursiv" nicht kursiv (' + (kursiv && kursiv.fontStyle) + ')');
        if (!rot || rot.color !== tbRgb('#ff0000')) f.push('"rot" nicht rot (' + (rot && rot.color) + ')');
        var alt = tbStil(feld, 'Alt ');
        if (alt && (alt.fontWeight !== '400' || alt.fontStyle !== 'normal')) f.push('"Alt" formatiert');
        if (JSON.stringify(B._textFeldLesen(feld)) !== JSON.stringify([{ text: TB_TEXT, teile: TB_TEILE }])) {
            f.push('gelesen ' + JSON.stringify(B._textFeldLesen(feld)));
        }
        // Formatleiste: F, K, sechs Stiftfarben, Standard - über dem Feld
        var leisteEl = w.querySelector('.cbd-board-blocktext-format');
        if (!leisteEl || leisteEl.querySelectorAll('button').length !== 9) f.push('Formatleiste mit ' + (leisteEl ? leisteEl.querySelectorAll('button').length : 0) + ' Knöpfen statt 9');
        else if (leisteEl.getBoundingClientRect().bottom > feld.getBoundingClientRect().top + 1) f.push('Formatleiste nicht über dem Feld');
        // Knöpfe
        var ab = tbKnopf('Abbrechen'), ue = tbKnopf('Übernehmen');
        if (!ab || !ue) f.push('Knöpfe: Abbrechen ' + !!ab + ', Übernehmen ' + !!ue);
        else if (ab.compareDocumentPosition(ue) !== Node.DOCUMENT_POSITION_FOLLOWING) f.push('Reihenfolge der Knöpfe');
        // Fokus im Feld, Schreibmarke eingeklappt am ENDE des Texts
        if (document.activeElement !== feld) f.push('Fokus nicht im Feld');
        var sel = window.getSelection();
        if (!sel || sel.rangeCount !== 1 || !sel.isCollapsed || !feld.contains(sel.anchorNode)) {
            f.push('Schreibmarke nicht eingeklappt im Feld');
        } else {
            var r = document.createRange();
            r.setStart(feld, 0);
            r.setEnd(sel.anchorNode, sel.anchorOffset);
            if (r.toString() !== TB_TEXT) f.push('Schreibmarke nach ' + JSON.stringify(r.toString()) + ', nicht am Ende');
        }
        // Das Öffnen ändert nichts
        if (el().length !== liste.length || el().some(function(x, i) { return x !== liste[i]; })) f.push('Öffnen ändert die Elemente');
        if (B.undoStapel.length !== undo0) f.push('Öffnen legt einen Undo-Schritt an');
        if (B.auswahl.join() !== id) f.push('Auswahl ' + B.auswahl.join());
        return ergebnis(f);
    }

    // "Übernehmen": Änderung im Feld (DOM), Wort rot färben, ein Wort
    // ergänzen -> Element unter derselben ID, Lage/Größe/Drehung/Farbe
    // bleiben, ein Undo-Schritt, Auswahl bleibt, Klickschutz, Fokus nicht auf
    // einem Knopf, Doppelklick wirkt einmal, Strg+Z stellt den alten Text her.
    function tbUebernehmen() {
        ctx.oeffnen();
        var f = [];
        var davor = einsetzen(neuesText({ x: 420, y: 420, text: 'Davor' }));
        var e = neuesText({ x: 120, y: 140, breite: 3, text: TB_TEXT, farbe: '#1565c0', winkel: 15, teile: TB_TEILE });
        var id = einsetzen(e);
        strich(300, 560, 420, 560);
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        var liste = el().slice();
        var i0 = liste.indexOf(e);
        var undo0 = B.undoStapel.length;
        tbAnhaengen(feld, ' neu');
        tbMarkiere(feld, 'fett');
        var rotKnopf = tbFormatKnopf('.cbd-board-format-farbe[aria-label="Rot"]');
        if (!rotKnopf) return { ok: false, detail: 'Testaufbau: Farbknopf Rot fehlt' };
        rotKnopf.click();
        var ue = tbKnopf('Übernehmen');
        if (!ue) return { ok: false, detail: 'Knopf "Übernehmen" fehlt' };
        ue.click();
        ue.click(); // schneller Doppelklick: wirkt nur einmal
        var n = element(id);
        if (!n) return { ok: false, detail: 'Element mit derselben ID fehlt danach' };
        if (n === e) return { ok: false, detail: 'Element unverändert' };
        if (el().length !== liste.length) f.push(el().length + ' Elemente statt ' + liste.length);
        if (el().indexOf(n) !== i0) f.push('Stelle in der Liste ' + el().indexOf(n) + ' statt ' + i0);
        if (n.text !== TB_TEXT + ' neu') f.push('text ' + JSON.stringify(n.text));
        ['x', 'y', 'breite', 'winkel', 'farbe'].forEach(function(k) {
            if (n[k] !== e[k]) f.push(k + ' ' + n[k] + ' statt ' + e[k]);
        });
        var stil = stilJeZeichen(n.text, n.teile);
        function stilVon(wort) {
            var p = n.text.indexOf(wort);
            return p < 0 ? '?' : stil.slice(p, p + wort.length).filter(function(s, i, a) { return a.indexOf(s) === i; }).join(',');
        }
        if (stilVon('fett') !== '1|0|#ff0000') f.push('"fett" ' + stilVon('fett') + ' statt 1|0|#ff0000 (fett und rot)');
        if (stilVon('kursiv') !== '0|1|') f.push('"kursiv" ' + stilVon('kursiv'));
        if (stilVon('rot') !== '0|0|#ff0000') f.push('"rot" ' + stilVon('rot'));
        if (stilVon(' neu') !== '0|0|') f.push('"neu" ' + stilVon(' neu'));
        el().forEach(function(x, i) {
            if (i !== i0 && x !== liste[i]) f.push('anderes Element an Stelle ' + i + ' geändert');
        });
        if (B.undoStapel.length !== undo0 + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undo0) + ' statt 1');
        if (B.auswahl.join() !== id) f.push('Auswahl ' + B.auswahl.join());
        if (tbFenster()) f.push('Fenster nach "Übernehmen" noch offen');
        if (!tbSchildDa()) f.push('kein Klickschutz nach "Übernehmen"');
        var aktiv = document.activeElement;
        if (aktiv && (aktiv.tagName === 'BUTTON' || (aktiv.closest && aktiv.closest('.cbd-board-blocktext-overlay')))) {
            f.push('Fokus auf ' + aktiv.tagName + '.' + aktiv.className + ' statt auf der Zeichenfläche');
        }
        if (B._textFeldLauscher) f.push('selectionchange-Lauscher bleibt');
        if (!element(davor)) f.push('anderer Text fehlt');
        strgZ();
        if (element(id) !== e) f.push('Strg+Z stellt das alte Element nicht her');
        return ergebnis(f);
    }

    // "Abbrechen" und Escape (auch nach Änderungen im Feld) ändern nichts:
    // kein Undo-Eintrag, Auswahl bleibt, die Tafel bleibt offen (Escape am
    // Feld hebt NICHT die Auswahl auf und schließt NICHT die Tafel), der
    // Fokus kehrt auf den Knopf "Text bearbeiten" zurück.
    function tbAbbrechenUndEscape() {
        ctx.oeffnen();
        var f = [];
        var e = neuesText({ x: 120, y: 140, breite: 3, text: TB_TEXT, farbe: '#1565c0', teile: TB_TEILE });
        var id = einsetzen(e);
        var liste = el().slice();
        var undo0 = B.undoStapel.length;
        ['Abbrechen', 'Escape am Feld', 'Escape am Knopf'].forEach(function(weg) {
            var feld = tbOeffnen(id);
            if (!feld) {
                f.push(weg + ': Fenster öffnet nicht');
                return;
            }
            tbAnhaengen(feld, ' geändert');
            tbMarkiere(feld, 'Alt');
            tbFormatKnopf('.cbd-board-format-fett').click();
            if (weg === 'Abbrechen') {
                tbKnopf('Abbrechen').click();
            } else if (weg === 'Escape am Feld') {
                if (!tasteAn(feld, 'Escape').defaultPrevented) f.push(weg + ': Escape nicht behandelt');
            } else {
                var ue = tbKnopf('Übernehmen');
                ue.focus();
                tasteAn(ue, 'Escape');
            }
            if (tbFenster()) f.push(weg + ': Fenster noch offen');
            if (!B.overlay) {
                f.push(weg + ': Tafel geschlossen');
                return;
            }
            if (el().length !== liste.length || el().some(function(x, i) { return x !== liste[i]; })) f.push(weg + ': Elemente geändert');
            if (B.undoStapel.length !== undo0) f.push(weg + ': Undo-Schritte ' + (B.undoStapel.length - undo0));
            if (B.auswahl.join() !== id) f.push(weg + ': Auswahl ' + B.auswahl.join());
            var k = knopf('text-bearbeiten');
            if (!sichtbar(k)) f.push(weg + ': Knopf "Text bearbeiten" nicht mehr sichtbar');
            else if (document.activeElement !== k) f.push(weg + ': Fokus nicht auf "Text bearbeiten" (' + (document.activeElement && document.activeElement.tagName) + ')');
            if (B._textFeldLauscher) f.push(weg + ': selectionchange-Lauscher bleibt');
            if (tbSchildDa()) f.push(weg + ': Klickschutz nach Abbrechen');
        });
        return ergebnis(f);
    }

    // Enter im Feld = Zeilenwechsel, kein neues div. In Chrome/Safari fügt
    // insertLineBreak im pre-wrap-Feld ein "\n"-Zeichen ein (AP-2.4), kein
    // <br> - geprüft wird deshalb über _textFeldLesen (Zeilenwechsel im Text,
    // ein Absatz). Die Schreibmarke steht beim Öffnen am Ende: Weitertippen
    // hängt an die Zeile an, nicht in einen neuen Block.
    function tbEnter() {
        ctx.oeffnen();
        var f = [];
        var id = einsetzen(neuesText({ x: 120, y: 140, text: 'Abcd' }));
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        // 1. Tippen an der Marke, die beim Öffnen am Ende stand
        document.execCommand('insertText', false, '!');
        var l1 = B._textFeldLesen(feld);
        if (feld.children.length !== 1 || l1.length !== 1 || l1[0].text !== 'Abcd!') f.push('Tippen am Ende: ' + JSON.stringify(l1) + ', ' + feld.children.length + ' Kinder');
        // 2. Enter mitten im Wort
        tbMarkiere(feld, 'Ab', true);
        var ev = tasteAn(feld, 'Enter');
        if (!ev.defaultPrevented) f.push('Enter nicht verhindert');
        if (feld.children.length !== 1) f.push('Enter erzeugt ein neues div (' + feld.children.length + ' Kinder)');
        var l2 = B._textFeldLesen(feld);
        if (l2.length !== 1 || l2[0].text !== 'Ab\ncd!') f.push('nach Enter gelesen ' + JSON.stringify(l2));
        // 3. Enter ans Ende, dann weitertippen
        tbMarkiere(feld, 'cd!', true);
        tasteAn(feld, 'Enter');
        document.execCommand('insertText', false, 'e');
        var l3 = B._textFeldLesen(feld);
        if (l3.length !== 1 || l3[0].text !== 'Ab\ncd!\ne') f.push('Enter am Ende, dann tippen: ' + JSON.stringify(l3));
        tbKnopf('Übernehmen').click();
        var n = element(id);
        if (!n || n.text !== 'Ab\ncd!\ne') f.push('Element ' + JSON.stringify(n && n.text));
        else if (E.zeilenTeile(n).length !== 3) f.push(E.zeilenTeile(n).length + ' Zeilen statt 3');
        return ergebnis(f);
    }

    // Tastatur im Feld und im Fenster wirkt nicht auf die Tafel: Entf,
    // Rücktaste, Strg+Z, Strg+D (am Feld, an einem Knopf, am document bei
    // Fokus außerhalb des Fensters). Das Fenster bleibt offen.
    function tbTastaturWirktNichtAufDieTafel() {
        ctx.oeffnen();
        var f = [];
        var id = einsetzen(neuesText({ x: 120, y: 140, text: 'Alt' }));
        einsetzen(neuesText({ x: 120, y: 400, text: 'Anderes' }));
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        var liste = el().slice();
        var undo0 = B.undoStapel.length;
        var faelle = [['Delete', {}], ['Backspace', {}], ['z', { ctrlKey: true }], ['d', { ctrlKey: true }]];
        [['am Feld', feld], ['am Knopf', tbKnopf('Übernehmen')], ['am document', document]].forEach(function(ziel) {
            faelle.forEach(function(fall) {
                tasteAn(ziel[1], fall[0], fall[1]);
                var was = fall[0] + (fall[1].ctrlKey ? ' (Strg)' : '') + ' ' + ziel[0];
                if (!B.overlay) f.push(was + ': Tafel geschlossen');
                if (el().length !== liste.length || el().some(function(x, i) { return x !== liste[i]; })) f.push(was + ': Elemente geändert');
                if (B.undoStapel.length !== undo0) f.push(was + ': Undo-Stapel geändert');
                if (B.auswahl.join() !== id) f.push(was + ': Auswahl ' + B.auswahl.join());
                if (!tbFenster()) f.push(was + ': Fenster geschlossen');
            });
        });
        return ergebnis(f);
    }

    // Ein mit dem Text-Werkzeug geschriebener Text (ohne teile) lässt sich
    // formatieren (F8/Nicht-Ziel "Kein Formatieren im Text-Werkzeug"); ein
    // Undo-Schritt, Strg+Z stellt den Text OHNE teile wieder her.
    function tbTextWerkzeugText() {
        ctx.oeffnen();
        var f = [];
        B.setTool('text');
        B.setColor('#000000');
        ctx.zeiger('pointerdown', 1, 'mouse', 140, 160);
        var eingabe = document.querySelector('.cbd-board-text-input');
        if (!eingabe) return { ok: false, detail: 'Testaufbau: kein Textfeld des Text-Werkzeugs' };
        eingabe.value = 'Hallo Welt';
        eingabe.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
        var e = el()[el().length - 1];
        if (!e || e.typ !== 'text' || e.text !== 'Hallo Welt' || e.teile !== undefined) {
            return { ok: false, detail: 'Testaufbau: Element des Text-Werkzeugs ' + JSON.stringify(e) };
        }
        var id = e.id;
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        if (feld.querySelector('span')) f.push('Klartext zeigt formatierte Teile');
        tbMarkiere(feld, 'Welt');
        tbFormatKnopf('.cbd-board-format-fett').click();
        var undo0 = B.undoStapel.length;
        tbKnopf('Übernehmen').click();
        var n = element(id);
        if (!n || n === e) return { ok: false, detail: 'Element nicht ersetzt' };
        if (n.text !== 'Hallo Welt') f.push('text ' + JSON.stringify(n.text));
        if (teileText(n) !== JSON.stringify([{ t: 'Hallo ' }, { t: 'Welt', f: 1 }])) f.push('teile ' + teileText(n));
        if (n.farbe !== e.farbe || n.breite !== e.breite || n.x !== e.x || n.y !== e.y) f.push('Lage/Größe/Farbe verändert');
        if (B.undoStapel.length !== undo0 + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undo0) + ' statt 1');
        // gezeichnet: "Welt" in fett
        var welt = zeichnenMitschreiben().filter(function(x) { return x.t === 'Welt'; })[0];
        var soll = normSchrift(E.schrift(E.schriftPx(n.breite), 1, 0));
        if (!welt || welt.font !== soll) f.push('"Welt" gezeichnet mit ' + (welt && welt.font) + ' statt ' + soll);
        strgZ();
        if (element(id) !== e || e.teile !== undefined) f.push('Strg+Z stellt den Klartext nicht her');
        return ergebnis(f);
    }

    // Ohne eigene Änderung ändert "Übernehmen" nichts: kein Undo-Eintrag, das
    // Element bleibt dasselbe Objekt - auch wenn der Text Leerzeichen trägt,
    // die das Lesen des Felds zusammenfasst (zwei Leerzeichen).
    function tbUebernehmenOhneAenderung() {
        ctx.oeffnen();
        var f = [];
        var e = neuesText({ x: 120, y: 140, text: 'a  b  ', teile: [{ t: 'a  ' }, { t: 'b  ', f: 1 }] });
        var id = einsetzen(e);
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        var undo0 = B.undoStapel.length;
        tbKnopf('Übernehmen').click();
        if (tbFenster()) f.push('Fenster bleibt offen');
        if (element(id) !== e) f.push('Element ersetzt (text ' + JSON.stringify(element(id) && element(id).text) + ')');
        if (B.undoStapel.length !== undo0) f.push('Undo-Schritte ' + (B.undoStapel.length - undo0) + ' statt 0');
        if (B.auswahl.join() !== id) f.push('Auswahl ' + B.auswahl.join());
        return ergebnis(f);
    }

    // Leerer Text: Element entfernt (F11), ein Undo-Schritt, Auswahl leer,
    // Strg+Z holt es zurück
    function tbLeererTextEntfernt() {
        ctx.oeffnen();
        var f = [];
        var e = einsetzenElement({ x: 120, y: 140, text: 'Weg damit', teile: [{ t: 'Weg ' }, { t: 'damit', f: 1 }] });
        var bleibt = einsetzen(neuesText({ x: 120, y: 400, text: 'bleibt' }));
        var feld = tbOeffnen(e.id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        while (feld.firstChild) feld.removeChild(feld.firstChild);
        var d = document.createElement('div');
        d.appendChild(document.createElement('br'));
        feld.appendChild(d);
        var undo0 = B.undoStapel.length;
        tbKnopf('Übernehmen').click();
        if (tbFenster()) f.push('Fenster bleibt offen');
        if (element(e.id)) f.push('Element nicht entfernt');
        if (!element(bleibt)) f.push('anderes Element entfernt');
        if (B.undoStapel.length !== undo0 + 1) f.push('Undo-Schritte ' + (B.undoStapel.length - undo0) + ' statt 1');
        if (B.auswahl.length) f.push('Auswahl ' + B.auswahl.join());
        if (sichtbar(leiste())) f.push('Auswahlleiste noch sichtbar');
        strgZ();
        if (element(e.id) !== e) f.push('Strg+Z holt das Element nicht zurück');
        return ergebnis(f);
    }

    /** Text-Element mit den Angaben o einsetzen; liefert das Element selbst. */
    function einsetzenElement(o) {
        var e = neuesText(o);
        einsetzen(e);
        return e;
    }

    // Umbruch beim Übernehmen (F12): lange Zeile, die über den Rand des
    // sichtbaren Ausschnitts (minus 20 px) ragt, wird umbrochen; die per Enter
    // geschriebene Zeile bleibt
    function tbUmbruchBeimUebernehmen() {
        ctx.oeffnen();
        var f = [];
        var x = 100;
        var id = einsetzen(neuesText({ x: x, y: 60, breite: 2, text: 'Kurz' }));
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        tasteAn(feld, 'Enter');
        var woerter = [];
        for (var i = 0; i < 40; i++) woerter.push('Wort' + i);
        document.execCommand('insertText', false, woerter.join(' '));
        tbKnopf('Übernehmen').click();
        var n = element(id);
        if (!n) return { ok: false, detail: 'Element fehlt' };
        var s = B._sichtbarerBereich();
        var grenze = s.x + s.b - 20 - x;
        var zeilen = n.text.split('\n');
        if (zeilen[0] !== 'Kurz') f.push('erste Zeile ' + JSON.stringify(zeilen[0]));
        if (zeilen.length < 3) f.push('nicht umbrochen: ' + zeilen.length + ' Zeilen bei Grenze ' + grenze.toFixed(0) + ' px');
        zeilenBreiten(n).forEach(function(w, k) {
            if (w > grenze + 0.01) f.push('Zeile ' + k + ': ' + w.toFixed(1) + ' px > ' + grenze.toFixed(1) + ' px');
        });
        if (n.text.replace(/\n/g, ' ') !== 'Kurz ' + woerter.join(' ')) f.push('Wörter verändert');
        return ergebnis(f);
    }

    // Nur bei genau einem gewählten Text (F11): sonst öffnet nichts
    function tbNurBeiEinemText() {
        ctx.oeffnen();
        var f = [];
        if (typeof B.auswahlTextBearbeiten !== 'function') return { ok: false, detail: 'B.auswahlTextBearbeiten fehlt' };
        var a = einsetzen(neuesText({ x: 100, y: 100, text: 'Eins' }));
        einsetzen(neuesText({ x: 100, y: 220, text: 'Zwei' }));
        var s = strich(320, 400, 460, 400);
        B.auswahlAufheben();
        B.auswahlTextBearbeiten();
        if (tbFenster()) f.push('öffnet ohne Auswahl');
        lasso(60, 60, 260, 300);
        if (B.auswahl.length !== 2) return { ok: false, detail: 'Testaufbau: zwei Texte nicht gewählt' };
        B.auswahlTextBearbeiten();
        if (tbFenster()) f.push('öffnet bei zwei Texten');
        lassoUm(s);
        if (B.auswahl.join() !== s) return { ok: false, detail: 'Testaufbau: Strich nicht gewählt' };
        B.auswahlTextBearbeiten();
        if (tbFenster()) f.push('öffnet bei einem Strich');
        lasso(60, 60, 500, 430);
        if (B.auswahl.length !== 3) return { ok: false, detail: 'Testaufbau: drei Elemente nicht gewählt' };
        B.auswahlTextBearbeiten();
        if (tbFenster()) f.push('öffnet bei Text + Text + Strich');
        lassoUm(a);
        B.auswahlTextBearbeiten();
        if (!tbFenster()) f.push('öffnet bei genau einem Text nicht');
        return ergebnis(f);
    }

    // Tab-Falle des neuen Fensters: Feld ist das erste, "Übernehmen" das
    // letzte Ziel; Tab vom letzten Knopf führt ins Feld, Umschalt+Tab vom Feld
    // auf den letzten Knopf
    function tbTabFalle() {
        ctx.oeffnen();
        var f = [];
        var id = einsetzen(neuesText({ x: 120, y: 140, text: 'Alt' }));
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        var ue = tbKnopf('Übernehmen');
        ue.focus();
        var ev = tasteAn(ue, 'Tab');
        if (!ev.defaultPrevented || document.activeElement !== feld) f.push('Tab von "Übernehmen" führt nicht ins Feld');
        ev = tasteAn(feld, 'Tab', { shiftKey: true });
        if (!ev.defaultPrevented || document.activeElement !== ue) f.push('Umschalt+Tab vom Feld führt nicht auf "Übernehmen"');
        return ergebnis(f);
    }

    // Grüne Tafel: Feld grün, Grundschrift = Farbe des Elements, Teilfarben
    // schon aufgehellt (F7); ohne eigene Änderung bleibt das Element, mit
    // Änderung ist die aufgehellte Farbe stabil (idempotent)
    function tbGrueneTafel() {
        ctx.oeffnen();
        var f = [];
        var gruen = '#1a472a';
        B.setBoardColor(gruen);
        var dunkel = '#71230a';
        var hell = U.lesbareLauffarbe(dunkel, gruen);
        if (hell === dunkel) return { ok: false, detail: 'Testaufbau: Dunkelrot auf Grün schon lesbar' };
        var e = neuesText({ x: 120, y: 140, text: 'hell dunkel', farbe: '#ffffff', teile: [{ t: 'hell ' }, { t: 'dunkel', farbe: hell }] });
        var id = einsetzen(e);
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        var cs = getComputedStyle(feld);
        if (cs.backgroundColor !== tbRgb(gruen)) f.push('Hintergrund ' + cs.backgroundColor);
        if (cs.color !== tbRgb('#ffffff')) f.push('Grundschrift ' + cs.color);
        var d = tbStil(feld, 'dunkel');
        if (!d || d.color !== tbRgb(hell)) f.push('Teilfarbe ' + (d && d.color) + ' statt ' + tbRgb(hell));
        tbAnhaengen(feld, ' x');
        tbKnopf('Übernehmen').click();
        var n = element(id);
        if (!n || n.text !== 'hell dunkel x') return { ok: false, detail: 'Element ' + JSON.stringify(n && n.text) };
        var st = stilJeZeichen(n.text, n.teile);
        var p = n.text.indexOf('dunkel');
        if (st[p] !== '0|0|' + hell) f.push('Farbe von "dunkel" ' + st[p] + ' statt ' + hell);
        if (n.farbe !== '#ffffff') f.push('farbe ' + n.farbe);
        return ergebnis(f);
    }

    // Fenster schließt ohne Änderung, wenn die Tafel zerstört wird, die Seite
    // wechselt (addPage/goToPage) oder die Auswahl aufgehoben wird. Das
    // Schließen der Tafel über close() prüft die asynchrone Prüfung unten.
    function tbSchliesstBeiTafelSeiteAuswahl() {
        var f = [];
        function aufbau() {
            ctx.oeffnen();
            var e = neuesText({ x: 120, y: 140, text: 'Alt' });
            var id = einsetzen(e);
            var feld = tbOeffnen(id);
            if (!feld) throw new Error('Fenster öffnet nicht');
            tbAnhaengen(feld, ' neu');
            return { e: e, id: id };
        }
        // destroy()
        var a = aufbau();
        B.destroy();
        if (tbFenster()) f.push('destroy(): Fenster bleibt');
        if (B._textFeldLauscher) f.push('destroy(): Lauscher bleibt');
        // Seite wechseln
        a = aufbau();
        B.addPage();
        if (tbFenster()) f.push('addPage(): Fenster bleibt');
        if (B._textFeldLauscher) f.push('addPage(): Lauscher bleibt');
        B.goToPage(0);
        if (element(a.id) !== a.e) f.push('addPage()/goToPage(): Element von Seite 1 verändert');
        // goToPage bei offenem Fenster
        ctx.oeffnen();
        var e2 = neuesText({ x: 120, y: 140, text: 'Alt' });
        var id2 = einsetzen(e2);
        B.addPage();
        B.goToPage(0);
        lassoUm(id2);
        knopf('text-bearbeiten').click();
        if (!tbFenster()) return { ok: false, detail: 'Testaufbau: Fenster öffnet nach Seitenwechsel nicht' };
        B.goToPage(1);
        if (tbFenster()) f.push('goToPage(): Fenster bleibt');
        B.goToPage(0);
        if (element(id2) !== e2) f.push('goToPage(): Element verändert');
        // Auswahl aufgehoben
        a = aufbau();
        var undo0 = B.undoStapel.length;
        B.auswahlAufheben();
        if (tbFenster()) f.push('auswahlAufheben(): Fenster bleibt');
        if (element(a.id) !== a.e || B.undoStapel.length !== undo0) f.push('auswahlAufheben(): Tafel verändert');
        // Auswahl und Element verschwinden ohne auswahlAufheben(): "Übernehmen" ändert nichts
        a = aufbau();
        var liste = el().slice();
        undo0 = B.undoStapel.length;
        B.auswahl = [];
        tbKnopf('Übernehmen').click();
        if (tbFenster()) f.push('ohne Auswahl: Fenster bleibt nach "Übernehmen"');
        if (el().length !== liste.length || el().some(function(x, i) { return x !== liste[i]; }) || B.undoStapel.length !== undo0) {
            f.push('ohne Auswahl: "Übernehmen" ändert die Tafel');
        }
        a = aufbau();
        undo0 = B.undoStapel.length;
        B.modell.elemente = el().filter(function(x) { return x.id !== a.id; });
        tbKnopf('Übernehmen').click();
        if (tbFenster()) f.push('ohne Element: Fenster bleibt nach "Übernehmen"');
        if (el().length !== 0 || B.undoStapel.length !== undo0) f.push('ohne Element: "Übernehmen" ändert die Tafel');
        return ergebnis(f);
    }

    // close() schließt das Fenster sofort, nicht erst mit destroy() 200 ms
    // später (wie beim Fenster "Text auf die Tafel", AP-2.fix1 G2)
    function tbCloseSchliesstFenster() {
        ctx.oeffnen();
        var e = neuesText({ x: 120, y: 140, text: 'Alt' });
        var id = einsetzen(e);
        var feld = tbOeffnen(id);
        if (!feld) return { ok: false, detail: 'Fenster öffnet nicht' };
        tbAnhaengen(feld, ' neu');
        B.close();
        var offen = !!tbFenster();
        return new Promise(function(fertig) { setTimeout(fertig, 350); }).then(function() {
            if (offen) return { ok: false, detail: 'Fenster bleibt während der Schließ-Animation' };
            if (tbFenster()) return { ok: false, detail: 'Fenster nach dem Schließen' };
            if (B._textFeldLauscher) return { ok: false, detail: 'selectionchange-Lauscher bleibt' };
            return true;
        });
    }

    // ------------------------------------------------------------------
    // Einhängen (nach den vorhandenen Haken)
    // ------------------------------------------------------------------
    var vorherAsync = window.__zusatzTestsAsync;
    window.__zusatzTestsAsync = function(c) {
        var vorher = (typeof vorherAsync === 'function') ? vorherAsync(c) : Promise.resolve();
        return Promise.resolve(vorher).then(function() {
            ctx = c;
            return c.pruefeAsync('AP-3.2: close() schließt das Fenster "Text bearbeiten" sofort', tbCloseSchliesstFenster);
        });
    };

    window.__zusatzTests = function(c) {
        if (typeof vorherSync === 'function') vorherSync(c);
        ctx = c;
        var p = c.pruefe;
        p('AP-1.3: Text mit teile - je Teil ein fillText mit Schrift, Farbe und laufendem x', zeichnenMitTeilen);
        p('AP-1.3: Teil über einen Zeilenwechsel - neue Zeile beginnt links, Zeilenhöhe 1,2', teilUeberZeilenwechsel);
        p('AP-1.3: Text ohne teile - dieselben fillText-Aufrufe wie 3.1.147', ohneTeileWieBisher);
        p('AP-1.3: Text ohne teile - Pixel gleich der bisherigen Zeichenroutine (auch gedreht)', ohneTeilePixelgleich);
        p('AP-1.3: gedrehter Text mit teile - Teile im gedrehten Koordinatensystem', gedrehterTextMitTeilen);
        p('AP-1.3: fett dicker, kursiv anders, Teilfarbe wirklich gezeichnet (Pixel)', pixelFettKursivFarbe);
        p('AP-1.3: _textMass() misst mit Stil fett/kursiv, ohne Zeichenebene null', textMassFunktion);
        p('AP-1.3: _rahmen - Breite = Summe der Teile, fett/kursiv breiter', rahmenMitFettKursiv);
        p('AP-1.3: _rahmen und _lassoAbschliessen messen über _textMass()', messenUeberTextMass);
        p('AP-1.3: Lasso-Auswahl nach der Mitte des fetten Rahmens (echte Messung)', lassoMitEchterMessung);
        p('AP-1.3: A+, Ziehen, Drehen, Duplizieren und Strg+Z behalten teile', lassoAktionenBehaltenTeile);
        p('AP-1.3: teile überlebt Speichern und Wiederöffnen und wird formatiert gezeichnet', speichernUndWiederoeffnen);
        p('AP-1.3: Strich- und Punkt-Radierer entfernen formatierten Text', radiererEntferntFormatiertenText);
        p('AP-1.fix1: Zeichnen misst nur Teile, auf die in derselben Zeile ein Teil folgt (B5)', zeichnenMisstNurFolgendeTeile);
        // PLAN-Blocktext-Formatierung.md, AP-3.1
        p('AP-3.1: Knopf "Text bearbeiten" nur bei genau einem Text (nicht bei zwei Texten, Strich, Text + Strich, Drehmodus)', knopfNurBeiEinemText);
        p('AP-3.1: Klick auf "Text bearbeiten" ruft auswahlTextBearbeiten()', knopfRuftAuswahlTextBearbeiten);
        p('AP-3.1: _auswahlLeisteHoeheText misst mit "Text bearbeiten" (konservativ)', leistenHoeheMitKnopf);
        p('AP-3.1: textErsetzen - gleiche ID und Stelle, Lage/Größe/Drehung/Farbe bleiben, 1 Undo, Auswahl bleibt, Strg+Z', textErsetzenGrundfall);
        p('AP-3.1: textErsetzen - gleicher Inhalt oder ungültiger Aufruf ändert nichts (false, kein Undo)', textErsetzenOhneAenderung);
        p('AP-3.1: textErsetzen - mehrere Absätze mit \\n in einem Element, Teile entsprechend', textErsetzenMehrereAbsaetze);
        p('AP-3.1: textErsetzen - Umbruch am sichtbaren Rand minus 20 px in der Schrift des Elements, \\n bleiben', textErsetzenUmbruch);
        p('AP-3.1: textErsetzen - Umbruch am Rand des sichtbaren Ausschnitts (Zoom 2), nicht der Zeichenfläche', textErsetzenUmbruchSichtbarerAusschnitt);
        p('AP-3.1: textErsetzen - gedrehter Text wird nicht umbrochen', textErsetzenGedrehtOhneUmbruch);
        p('AP-3.1: textErsetzen - leerer Inhalt entfernt das Element (1 Undo, Auswahl leer)', textErsetzenLeer);
        p('AP-3.1: textErsetzen - Teilfarben durch lesbareLauffarbe(…, Tafelfarbe)', textErsetzenTeilfarbenLesbar);
        p('AP-3.1: textErsetzen - beendet Drehmodus und laufendes Ziehen (erreichte Lage gilt)', textErsetzenBeendetGesten);
        // PLAN-Blocktext-Formatierung.md, AP-3.2
        p('AP-3.2: "Text bearbeiten" zeigt Text samt Formatierung, Formatleiste, Knöpfe, Marke am Ende, ändert beim Öffnen nichts', tbFensterZeigtText);
        p('AP-3.2: "Übernehmen" ersetzt unter gleicher ID (Lage/Größe/Drehung/Farbe bleiben), 1 Undo, Klickschutz, Strg+Z', tbUebernehmen);
        p('AP-3.2: "Abbrechen" und Escape ändern nichts (kein Undo, Auswahl und Tafel bleiben, Fokus auf dem Knopf)', tbAbbrechenUndEscape);
        p('AP-3.2: Enter im Feld = Zeilenwechsel im selben Absatz, Weitertippen am Ende', tbEnter);
        p('AP-3.2: Entf, Rücktaste, Strg+Z, Strg+D im Fenster wirken nicht auf die Tafel', tbTastaturWirktNichtAufDieTafel);
        p('AP-3.2: Text-Werkzeug-Text ohne teile lässt sich formatieren, Strg+Z stellt den Klartext her', tbTextWerkzeugText);
        p('AP-3.2: "Übernehmen" ohne eigene Änderung ändert nichts (kein Undo, dasselbe Element)', tbUebernehmenOhneAenderung);
        p('AP-3.2: leerer Text entfernt das Element (1 Undo, Auswahl leer, Strg+Z)', tbLeererTextEntfernt);
        p('AP-3.2: Umbruch beim Übernehmen am sichtbaren Rand, per Enter geschriebene Zeile bleibt', tbUmbruchBeimUebernehmen);
        p('AP-3.2: öffnet nur bei genau einem gewählten Text', tbNurBeiEinemText);
        p('AP-3.2: Tab-Falle des Fensters (Feld erstes, "Übernehmen" letztes Ziel)', tbTabFalle);
        p('AP-3.2: grüne Tafel - Feld grün, Teilfarben lesbar, stabil beim Übernehmen', tbGrueneTafel);
        p('AP-3.2: Fenster schließt bei destroy(), Seitenwechsel, aufgehobener Auswahl; "Übernehmen" ohne Ziel ändert nichts', tbSchliesstBeiTafelSeiteAuswahl);
    };
})();
