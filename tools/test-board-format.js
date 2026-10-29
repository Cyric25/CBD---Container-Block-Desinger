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
    // Einhängen (nach den vorhandenen Haken)
    // ------------------------------------------------------------------
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
    };
})();
