/**
 * Tafel-Elementmodell v1 — reines Daten- und Geometriemodul
 * (Plan „Rueckmeldungen Klassenmodus und Tafel-Lasso", Phase 4, AP-4.1,
 * Architektur E6).
 *
 * Setzt `window.CBDBoardElemente`. Wird von `board-mode.js` genutzt (ab
 * AP-4.3) und deshalb in `class-cbd-style-loader.php` VOR `cbd-board-mode`
 * eingereiht (Abhaengigkeit `cbd-board-elemente`).
 *
 * BITTE DOM-FREI LASSEN: Kein Zugriff auf Dokument, Browser-Speicher oder
 * Zeichenflaechen. Der Harnisch `node tools/test-board-elemente-js.js` fuehrt
 * diese Datei in einem leeren `vm`-Kontext aus und prueft den Quelltext auf
 * genau diese Namen — auch in Kommentaren. Textbreiten misst der Aufrufer
 * ueber eine uebergebene Messfunktion `ctxMass(text, schriftPx, stil) -> px`
 * mit `stil = {f: 0|1, k: 0|1}` (fett, kursiv; Schriftangabe dazu liefert
 * schrift(px, f, k)). Aeltere Messfunktionen mit zwei Parametern laufen
 * weiter, messen dann aber fett/kursiv in normaler Schrift.
 *
 * Format (verbindlich, Plan Abschnitt 4 „Elementmodell v1"):
 *   { v: 1, breite, hoehe, elemente: [
 *     { id:'e1', typ:'strich', werkzeug:'pen'|'highlighter', farbe, breite, punkte:[[x,y],...] },
 *     { id:'e2', typ:'text', farbe, breite, text, x, y [, teile] [, winkel] },
 *     { id:'e3', typ:'bild', daten:'data:image/png;base64,...', x, y, b, h [, winkel] } ] }
 *
 * `winkel` (seit 3.1.143, Lasso-Drehen) ist optional: Drehung in Grad um die
 * linke obere Ecke (x, y), positiv = im Uhrzeigersinn, in (-180, 180]. Fehlt
 * es, ist das Element unverdreht; 0 wird nie geschrieben. Das Format bleibt
 * v1 - eine aeltere Version laesst das Feld beim Pruefen still fallen.
 *
 * `teile` (Plan „Formatierter Text auf der Tafel", AP-1.1, Entscheidungen
 * F1-F5) ist optional: Abschnitte eines Texts in fett, kursiv und eigener
 * Farbe, `teile: [{t, f?, k?, farbe?}]`. `text` bleibt IMMER vollstaendig
 * erhalten - aeltere Versionen (Rueckweg auf 3.1.147) lesen nur `text` und
 * zeigen ihn unformatiert. Regeln (pruefeTeile): `t` nicht leere
 * Zeichenkette; `f` fehlt oder genau 1 (fett); `k` fehlt oder genau 1
 * (kursiv); `farbe` fehlt oder passt auf FARB_MUSTER; unbekannte Schluessel
 * entfallen; hoechstens MAX_TEILE Teile; die Verkettung aller `t` muss genau
 * den (auf MAX_TEXT gekuerzten) `text` ergeben - ein Stringvergleich statt
 * Zeichenpositionen, weil JavaScript UTF-16-Einheiten und PHP Codepunkte
 * zaehlt. Ein ungueltiger Teil oder eine falsche Verkettung verwirft nur das
 * Feld `teile`, nie das Element; hat kein Teil `f`, `k` oder `farbe`, entfaellt
 * `teile` ebenfalls (Modelle ohne Formatierung bleiben byte-gleich zu
 * 3.1.147). Gleiche Regel wie serverseitig in
 * CBD_Classroom::pruefe_elemente_json() - bei Aenderung beide Stellen
 * nachziehen. Die Pruefung legt NICHT zusammen; das macht
 * teileZusammenlegen() beim Erzeugen (F4).
 *
 * Alle Funktionen sind frei von Seiteneffekten: Eingaben werden nie
 * veraendert, Ergebnisse sind neue Objekte. Unveraenderte Elemente duerfen
 * in Ergebnissen als dieselbe Referenz auftauchen (AP-4.3 baut den
 * Undo-Stapel aus flachen Kopien der Elementliste und ersetzt Elemente nur,
 * statt sie zu veraendern).
 *
 * ES5, keine Abhaengigkeiten.
 *
 * @package ContainerBlockDesigner
 */
(function (global) {
    'use strict';

    var VERSION = 1;
    var MAX_TEXT = 5000;
    var MAX_PUNKTE = 20000;
    var BILD_PRAEFIX = 'data:image/png;base64,';
    var ID_MUSTER = /^e[0-9]+$/;
    // Gleiche Regel wie serverseitig in CBD_Classroom::pruefe_elemente_json()
    // (AP-4.2). Bei Aenderung beide Stellen nachziehen.
    var FARB_MUSTER = /^(#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6}|rgba?\(\s*[0-9.]+\s*,\s*[0-9.]+\s*,\s*[0-9.]+\s*(,\s*[0-9.]+\s*)?\))$/;
    var BASE64_MUSTER = /^[A-Za-z0-9+\/=]*$/;
    // Schrift wie im Text-Werkzeug von board-mode.js.
    var ZEILENHOEHE = 1.2;
    // Textgroesse nachtraeglich (3.1.144, textGroesse): Schrift in px und
    // Faktor je Schritt. 18 px entspricht breite 0.5 (breite muss > 0 sein).
    var TEXT_PX_MIN = 18;
    var TEXT_PX_MAX = 200;
    var TEXT_SCHRITT = 1.2;
    // Formatierte Abschnitte je Text (Feld teile, AP-1.1). Gleiche Grenze wie
    // serverseitig in CBD_Classroom::pruefe_elemente_json().
    var MAX_TEILE = 1000;

    // =====================================================================
    // Hilfsfunktionen
    // =====================================================================

    function istZahl(n) {
        return typeof n === 'number' && isFinite(n);
    }

    function istObjekt(o) {
        return o !== null && typeof o === 'object' && Object.prototype.toString.call(o) !== '[object Array]';
    }

    function istArray(a) {
        return Object.prototype.toString.call(a) === '[object Array]';
    }

    function schriftPx(breite) {
        return Math.round(16 + breite * 4);
    }

    /** Punkt als [x, y] oder {x, y} lesen. */
    function px(p) {
        return istArray(p) ? p[0] : p.x;
    }
    function py(p) {
        return istArray(p) ? p[1] : p.y;
    }

    /** Auf zwei Nachkommastellen runden, ohne -0. */
    function runde(n) {
        var r = Math.round(n * 100) / 100;
        return r === 0 ? 0 : r;
    }

    function mengeAus(ids) {
        var m = {};
        for (var i = 0; i < (ids ? ids.length : 0); i++) {
            m[ids[i]] = true;
        }
        return m;
    }

    function kopf(modell, elemente) {
        return { v: VERSION, breite: modell.breite, hoehe: modell.hoehe, elemente: elemente };
    }

    function punkteKopie(punkte) {
        var neu = [];
        for (var i = 0; i < punkte.length; i++) {
            neu.push([punkte[i][0], punkte[i][1]]);
        }
        return neu;
    }

    /** Winkel in Grad auf (-180, 180] bringen, ohne -0. */
    function normWinkel(grad) {
        var w = grad % 360;
        if (w > 180) w -= 360;
        if (w <= -180) w += 360;
        return w === 0 ? 0 : w;
    }

    /**
     * Optionales Feld `winkel` (Grad, Lasso-Drehen 3.1.143) an ein NEUES
     * Text-/Bild-Objekt haengen - nur, wenn es nicht 0 ist. Unverdrehte
     * Elemente sehen damit genau aus wie vor dem Drehen (gleiche Felder,
     * gleiche Serialisierung).
     */
    function mitWinkel(el, grad) {
        var w = istZahl(grad) ? normWinkel(grad) : 0;
        if (w !== 0) el.winkel = w;
        return el;
    }

    // =====================================================================
    // Formatierte Teile eines Texts (Feld teile, AP-1.1, F1-F5)
    // =====================================================================

    /**
     * Feld `teile` pruefen (Regeln im Kopfkommentar, F2/F3) gegen den schon
     * gekuerzten `text`. Liefert ein NEUES Array aus neuen Teil-Objekten
     * (Schluessel in der Reihenfolge t, f, k, farbe; unbekannte entfallen)
     * oder null - dann entfaellt nur das Feld, nie das Element. Legt nicht
     * zusammen (F4). Gleiche Regel wie serverseitig in
     * CBD_Classroom::pruefe_elemente_json() - bei Aenderung beide Stellen
     * nachziehen.
     *
     * "Fehlt" heisst hier `=== undefined`; ein vorhandenes `f: null` (oder
     * `k`/`farbe: null`) ist also ungueltig.
     */
    function pruefeTeile(teile, text) {
        if (!istArray(teile) || teile.length < 1 || teile.length > MAX_TEILE) return null;
        if (typeof text !== 'string') return null;
        var neu = [];
        var kette = '';
        var formatiert = false;
        for (var i = 0; i < teile.length; i++) {
            var teil = teile[i];
            if (!istObjekt(teil) || typeof teil.t !== 'string' || teil.t === '') return null;
            if (teil.f !== undefined && teil.f !== 1) return null;
            if (teil.k !== undefined && teil.k !== 1) return null;
            if (teil.farbe !== undefined && (typeof teil.farbe !== 'string' || !FARB_MUSTER.test(teil.farbe))) return null;
            kette += teil.t;
            // frueh abbrechen, statt einen ueberlangen Text weiter aufzubauen
            if (kette.length > text.length) return null;
            var n = { t: teil.t };
            if (teil.f === 1) n.f = 1;
            if (teil.k === 1) n.k = 1;
            if (teil.farbe !== undefined) n.farbe = teil.farbe;
            if (teil.f === 1 || teil.k === 1 || teil.farbe !== undefined) formatiert = true;
            neu.push(n);
        }
        // Stringvergleich statt Zeichenpositionen (F2); ohne Formatierung kein Feld (F3)
        if (kette !== text || !formatiert) return null;
        return neu;
    }

    /**
     * Optionales Feld `teile` an ein NEUES Text-Objekt haengen - nur, wenn
     * `teile` ein nicht leeres Array ist (Gegenstueck zu mitWinkel). Haengt
     * eine Kopie an: neue Teil-Objekte nur mit t, f, k, farbe. Aufrufreihenfolge
     * immer mitWinkel(mitTeile(...), winkel), damit die Schluessel in der
     * Reihenfolge id, typ, farbe, breite, text, x, y, teile, winkel stehen.
     * Texte ohne teile bleiben dadurch genau wie in 3.1.147 (gleiche Felder,
     * gleiche Serialisierung).
     */
    function mitTeile(el, teile) {
        if (!istArray(teile) || teile.length === 0) return el;
        var liste = [];
        for (var i = 0; i < teile.length; i++) {
            var teil = teile[i];
            if (!istObjekt(teil)) continue;
            var n = { t: teil.t };
            if (teil.f !== undefined) n.f = teil.f;
            if (teil.k !== undefined) n.k = teil.k;
            if (teil.farbe !== undefined) n.farbe = teil.farbe;
            liste.push(n);
        }
        if (liste.length) el.teile = liste;
        return el;
    }

    /**
     * Schriftangabe in der Kurzschreibweise von ctx.font fuer einen Teil:
     * '[italic ][bold ]<px>px sans-serif' (F5). Sans-serif wie im
     * Text-Werkzeug von board-mode.js.
     */
    function schrift(px, f, k) {
        return (k ? 'italic ' : '') + (f ? 'bold ' : '') + px + 'px sans-serif';
    }

    /**
     * Text eines Elements als Zeilen aus Teilen, zum Zeichnen und Messen:
     * [[{t, f:0|1, k:0|1, farbe}, ...], ...] - eine Liste je Zeile (`\n`),
     * Teile ueber einen Zeilenwechsel werden geteilt, `farbe` ist aufgeloest
     * (teil.farbe || el.farbe), leere Zeile -> []. Ohne (gueltige) teile ein
     * Teil je Zeile in el.farbe. Ungueltige teile (Pruefung wie
     * pruefeModell) werden wie fehlende behandelt - es wird also genau das
     * gezeichnet, was ein Speichern und Laden uebrig liesse. Die Zahl der
     * Zeilen ist immer text.split('\n').length.
     */
    function zeilenTeile(el) {
        var text = String(el.text);
        var teile = el.teile !== undefined ? pruefeTeile(el.teile, text) : null;
        if (!teile) teile = [{ t: text }];
        var zeilen = [[]];
        for (var i = 0; i < teile.length; i++) {
            var teil = teile[i];
            var stuecke = teil.t.split('\n');
            var f = teil.f === 1 ? 1 : 0;
            var k = teil.k === 1 ? 1 : 0;
            var farbe = teil.farbe || el.farbe;
            for (var j = 0; j < stuecke.length; j++) {
                if (j > 0) zeilen.push([]);
                if (stuecke[j] !== '') {
                    zeilen[zeilen.length - 1].push({ t: stuecke[j], f: f, k: k, farbe: farbe });
                }
            }
        }
        return zeilen;
    }

    /**
     * Teile fuer das ERZEUGEN aufbereiten (F4; die Pruefung legt bewusst
     * nicht zusammen): leere oder fehlende `t` entfallen, `f`/`k` werden zu 1
     * (wahr) bzw. entfallen (falsch), benachbarte Teile mit gleichem
     * (f, k, farbe) werden verbunden. Eine `farbe`, die nicht auf FARB_MUSTER
     * passt, entfaellt (sonst verwuerfe die Pruefung beim Speichern die
     * gesamte Formatierung des Texts). Bleibt keine Formatierung uebrig
     * (nur Klartext) -> []. Liefert immer ein neues Array; die Eingabe
     * bleibt unveraendert. MAX_TEILE wird hier nicht durchgesetzt.
     */
    function teileZusammenlegen(teile) {
        var neu = [];
        var formatiert = false;
        if (!istArray(teile)) return neu;
        for (var i = 0; i < teile.length; i++) {
            var teil = teile[i];
            if (!istObjekt(teil) || typeof teil.t !== 'string' || teil.t === '') continue;
            var f = teil.f ? 1 : 0;
            var k = teil.k ? 1 : 0;
            var farbe = typeof teil.farbe === 'string' && FARB_MUSTER.test(teil.farbe) ? teil.farbe : '';
            var letzter = neu.length ? neu[neu.length - 1] : null;
            if (letzter && (letzter.f || 0) === f && (letzter.k || 0) === k && (letzter.farbe || '') === farbe) {
                letzter.t += teil.t;
                continue;
            }
            var n = { t: teil.t };
            if (f) n.f = 1;
            if (k) n.k = 1;
            if (farbe) n.farbe = farbe;
            if (f || k || farbe) formatiert = true;
            neu.push(n);
        }
        return formatiert ? neu : [];
    }

    /**
     * Umschliessendes Rechteck eines um seine linke obere Ecke (x, y)
     * gedrehten Rechtecks b x h. Bildschirmsinn (y nach unten): positiver
     * Winkel dreht im Uhrzeigersinn, wie ctx.rotate().
     */
    function gedrehterRahmen(x, y, b, h, grad) {
        if (!grad) return { x: x, y: y, b: b, h: h };
        var rad = grad * Math.PI / 180;
        var cos = Math.cos(rad), sin = Math.sin(rad);
        var ecken = [[0, 0], [b, 0], [b, h], [0, h]];
        var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        for (var i = 0; i < 4; i++) {
            var ex = x + ecken[i][0] * cos - ecken[i][1] * sin;
            var ey = y + ecken[i][0] * sin + ecken[i][1] * cos;
            if (ex < minX) minX = ex;
            if (ex > maxX) maxX = ex;
            if (ey < minY) minY = ey;
            if (ey > maxY) maxY = ey;
        }
        return { x: minX, y: minY, b: maxX - minX, h: maxY - minY };
    }

    /**
     * Element um (dx, dy) versetzt als NEUES Objekt mit der ID `id`
     * (verschiebe() behaelt die ID, kopiere() vergibt eine neue). Unbekannter
     * Typ -> null.
     */
    function versetzt(e, dx, dy, id) {
        if (e.typ === 'strich') {
            var punkte = [];
            for (var j = 0; j < e.punkte.length; j++) {
                punkte.push([e.punkte[j][0] + dx, e.punkte[j][1] + dy]);
            }
            return { id: id, typ: 'strich', werkzeug: e.werkzeug, farbe: e.farbe,
                breite: e.breite, punkte: punkte };
        }
        if (e.typ === 'text') {
            return mitWinkel(mitTeile({ id: id, typ: 'text', farbe: e.farbe, breite: e.breite,
                text: e.text, x: e.x + dx, y: e.y + dy }, e.teile), e.winkel);
        }
        if (e.typ === 'bild') {
            return mitWinkel({ id: id, typ: 'bild', daten: e.daten, x: e.x + dx, y: e.y + dy,
                b: e.b, h: e.h }, e.winkel);
        }
        return null;
    }

    // =====================================================================
    // Pruefen
    // =====================================================================

    /**
     * Ein Element pruefen und als NEUES Objekt mit ausschliesslich den
     * erlaubten Feldern liefern; ungueltig -> null.
     */
    function pruefeElement(el) {
        if (!istObjekt(el)) return null;
        if (typeof el.id !== 'string' || !ID_MUSTER.test(el.id)) return null;

        if (el.typ === 'strich') {
            if (el.werkzeug !== 'pen' && el.werkzeug !== 'highlighter') return null;
            if (typeof el.farbe !== 'string' || !FARB_MUSTER.test(el.farbe)) return null;
            if (!istZahl(el.breite) || el.breite <= 0) return null;
            if (!istArray(el.punkte) || el.punkte.length < 1 || el.punkte.length > MAX_PUNKTE) return null;
            var punkte = [];
            for (var i = 0; i < el.punkte.length; i++) {
                var p = el.punkte[i];
                if (!istArray(p) || p.length !== 2 || !istZahl(p[0]) || !istZahl(p[1])) return null;
                punkte.push([p[0], p[1]]);
            }
            return { id: el.id, typ: 'strich', werkzeug: el.werkzeug, farbe: el.farbe,
                breite: el.breite, punkte: punkte };
        }

        if (el.typ === 'text') {
            if (typeof el.farbe !== 'string' || !FARB_MUSTER.test(el.farbe)) return null;
            if (!istZahl(el.breite) || el.breite <= 0) return null;
            if (typeof el.text !== 'string') return null;
            if (!istZahl(el.x) || !istZahl(el.y)) return null;
            // winkel optional (Lasso-Drehen 3.1.143); vorhanden, aber keine Zahl -> ungueltig
            if (el.winkel !== undefined && !istZahl(el.winkel)) return null;
            var inhalt = el.text.length > MAX_TEXT ? el.text.substring(0, MAX_TEXT) : el.text;
            // teile optional (AP-1.1): gegen den GEKUERZTEN Text pruefen - ein
            // gekuerzter Text passt nicht mehr und verliert nur die Formatierung.
            // Ungueltig -> nur das Feld entfaellt, das Element bleibt.
            return mitWinkel(mitTeile({ id: el.id, typ: 'text', farbe: el.farbe, breite: el.breite,
                text: inhalt, x: el.x, y: el.y },
                el.teile !== undefined ? pruefeTeile(el.teile, inhalt) : null), el.winkel);
        }

        if (el.typ === 'bild') {
            if (typeof el.daten !== 'string' || el.daten.indexOf(BILD_PRAEFIX) !== 0) return null;
            if (!BASE64_MUSTER.test(el.daten.substring(BILD_PRAEFIX.length))) return null;
            if (!istZahl(el.x) || !istZahl(el.y) || !istZahl(el.b) || !istZahl(el.h)) return null;
            if (el.b <= 0 || el.h <= 0) return null;
            if (el.winkel !== undefined && !istZahl(el.winkel)) return null;
            return mitWinkel({ id: el.id, typ: 'bild', daten: el.daten, x: el.x, y: el.y,
                b: el.b, h: el.h }, el.winkel);
        }

        return null; // unbekannter typ
    }

    /**
     * Modell pruefen. Liefert `{modell, verworfen}` (verworfen = Zahl der
     * verworfenen Elemente) oder `null`, wenn das Modell als Ganzes nicht
     * lesbar ist (kein Objekt, v != 1, Masse ungueltig, elemente kein Array).
     * Unbekannte Felder werden still entfernt; doppelte IDs: das spaetere
     * Element wird verworfen.
     */
    function pruefeModell(obj) {
        if (!istObjekt(obj)) return null;
        if (obj.v !== VERSION) return null;
        if (!istZahl(obj.breite) || obj.breite <= 0) return null;
        if (!istZahl(obj.hoehe) || obj.hoehe <= 0) return null;
        if (!istArray(obj.elemente)) return null;

        var elemente = [];
        var verworfen = 0;
        var gesehen = {};
        for (var i = 0; i < obj.elemente.length; i++) {
            var el = pruefeElement(obj.elemente[i]);
            if (!el || gesehen.hasOwnProperty(el.id)) {
                verworfen++;
                continue;
            }
            gesehen[el.id] = true;
            elemente.push(el);
        }
        return {
            modell: { v: VERSION, breite: obj.breite, hoehe: obj.hoehe, elemente: elemente },
            verworfen: verworfen
        };
    }

    // =====================================================================
    // Umwandlung zum heutigen strokes-Format von board-mode.js
    // =====================================================================

    /**
     * strokes (`{tool, color, width, points:[{x,y}]}`, Text zusaetzlich
     * `text`) -> Modell. Andere Werkzeuge werden nicht uebernommen.
     * `neueId` optional; ohne sie werden die IDs e1, e2, ... vergeben.
     */
    function ausStrokes(strokes, breite, hoehe, neueId) {
        var n = 1;
        var id = typeof neueId === 'function' ? neueId : function () { return 'e' + (n++); };
        var elemente = [];
        for (var i = 0; i < (strokes ? strokes.length : 0); i++) {
            var s = strokes[i];
            if (!s || !s.points || !s.points.length) continue;
            if (s.tool === 'pen' || s.tool === 'highlighter') {
                var punkte = [];
                for (var j = 0; j < s.points.length; j++) {
                    punkte.push([s.points[j].x, s.points[j].y]);
                }
                elemente.push({ id: id(), typ: 'strich', werkzeug: s.tool, farbe: s.color,
                    breite: s.width, punkte: punkte });
            } else if (s.tool === 'text') {
                elemente.push({ id: id(), typ: 'text', farbe: s.color, breite: s.width,
                    text: s.text, x: s.points[0].x, y: s.points[0].y });
            }
        }
        return { v: VERSION, breite: breite, hoehe: hoehe, elemente: elemente };
    }

    /** Modell -> strokes. Bild-Elemente haben kein strokes-Gegenstueck und entfallen. */
    function zuStrokes(modell) {
        var strokes = [];
        var el = modell && modell.elemente ? modell.elemente : [];
        for (var i = 0; i < el.length; i++) {
            var e = el[i];
            if (e.typ === 'strich') {
                var points = [];
                for (var j = 0; j < e.punkte.length; j++) {
                    points.push({ x: e.punkte[j][0], y: e.punkte[j][1] });
                }
                strokes.push({ tool: e.werkzeug, color: e.farbe, width: e.breite, points: points });
            } else if (e.typ === 'text') {
                strokes.push({ tool: 'text', text: e.text, color: e.farbe, width: e.breite,
                    points: [{ x: e.x, y: e.y }] });
            }
        }
        return strokes;
    }

    // =====================================================================
    // Geometrie
    // =====================================================================

    /** Umschliessendes Rechteck `{x, y, b, h}` eines Elements. */
    function rahmen(el, ctxMass) {
        if (el.typ === 'strich') {
            var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            for (var i = 0; i < el.punkte.length; i++) {
                var p = el.punkte[i];
                if (p[0] < minX) minX = p[0];
                if (p[0] > maxX) maxX = p[0];
                if (p[1] < minY) minY = p[1];
                if (p[1] > maxY) maxY = p[1];
            }
            var halb = el.breite / 2;
            return { x: minX - halb, y: minY - halb, b: (maxX - minX) + el.breite, h: (maxY - minY) + el.breite };
        }
        if (el.typ === 'text') {
            var groesse = schriftPx(el.breite);
            // Zeilen aus Teilen (AP-1.1, F5): Breite einer Zeile = Summe ihrer
            // Teile, jeder mit seinem Stil gemessen (fett/kursiv sind breiter).
            // Ohne teile ist jede Zeile genau ein Teil {f:0, k:0} wie bisher.
            var zeilen = zeilenTeile(el);
            var breitest = 0;
            for (var z = 0; z < zeilen.length; z++) {
                var w = 0;
                for (var t = 0; t < zeilen[z].length; t++) {
                    var teil = zeilen[z][t];
                    // Ohne Messfunktion grob schaetzen (0,6 em je Zeichen).
                    w += typeof ctxMass === 'function'
                        ? ctxMass(teil.t, groesse, { f: teil.f, k: teil.k })
                        : teil.t.length * groesse * 0.6;
                }
                if (w > breitest) breitest = w;
            }
            return gedrehterRahmen(el.x, el.y, breitest, zeilen.length * ZEILENHOEHE * groesse, el.winkel);
        }
        if (el.typ === 'bild') {
            return gedrehterRahmen(el.x, el.y, el.b, el.h, el.winkel);
        }
        return { x: 0, y: 0, b: 0, h: 0 };
    }

    /**
     * Punkt im Polygon (Strahlverfahren, gerade/ungerade Regel). Polygon als
     * [[x,y],...] oder [{x,y},...]; weniger als drei Ecken -> false.
     */
    function punktImPolygon(x, y, polygon) {
        if (!istArray(polygon) || polygon.length < 3) return false;
        var innen = false;
        for (var i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            var xi = px(polygon[i]), yi = py(polygon[i]);
            var xj = px(polygon[j]), yj = py(polygon[j]);
            if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                innen = !innen;
            }
        }
        return innen;
    }

    /**
     * Auswahl nach Regel E14: Strich, wenn mindestens 50 % seiner Punkte im
     * Lasso liegen; Text und Bild, wenn der Mittelpunkt ihres Rahmens im
     * Lasso liegt. Liefert die IDs in Listenreihenfolge.
     */
    function waehleImLasso(modell, polygon, ctxMass) {
        var ids = [];
        if (!istArray(polygon) || polygon.length < 3) return ids;
        var el = modell.elemente;
        for (var i = 0; i < el.length; i++) {
            var e = el[i];
            if (e.typ === 'strich') {
                var drin = 0;
                for (var j = 0; j < e.punkte.length; j++) {
                    if (punktImPolygon(e.punkte[j][0], e.punkte[j][1], polygon)) drin++;
                }
                if (e.punkte.length > 0 && drin * 2 >= e.punkte.length) ids.push(e.id);
            } else if (e.typ === 'text' || e.typ === 'bild') {
                var r = rahmen(e, ctxMass);
                if (punktImPolygon(r.x + r.b / 2, r.y + r.h / 2, polygon)) ids.push(e.id);
            }
        }
        return ids;
    }

    /** Neues Modell, in dem die Elemente mit diesen IDs um (dx, dy) verschoben sind. */
    function verschiebe(modell, ids, dx, dy) {
        var wahl = mengeAus(ids);
        var neu = [];
        for (var i = 0; i < modell.elemente.length; i++) {
            var e = modell.elemente[i];
            neu.push(wahl.hasOwnProperty(e.id) ? (versetzt(e, dx, dy, e.id) || e) : e);
        }
        return kopf(modell, neu);
    }

    /**
     * Neues Modell, in dem die Elemente mit diesen IDs um den Drehpunkt
     * (cx, cy) gedreht sind (Lasso-Drehen 3.1.143). `grad` im Bildschirmsinn:
     * positiv = im Uhrzeigersinn (y zeigt nach unten, wie ctx.rotate()).
     * Striche: jeder Punkt wird gedreht. Text und Bild: die linke obere Ecke
     * (x, y) wird gedreht und `winkel` waechst um `grad` - gezeichnet wird um
     * diese Ecke (board-mode.js, zeichneModell). Beides zusammen ist genau
     * die Drehung um (cx, cy); mehrfaches Drehen setzt sich exakt zusammen.
     * grad 0 (bzw. Vielfache von 360) -> unveraenderte Liste.
     */
    function drehe(modell, ids, cx, cy, grad) {
        if (!istZahl(grad) || normWinkel(grad) === 0) {
            return kopf(modell, modell.elemente.slice());
        }
        var wahl = mengeAus(ids);
        var rad = grad * Math.PI / 180;
        var cos = Math.cos(rad), sin = Math.sin(rad);
        var neu = [];
        for (var i = 0; i < modell.elemente.length; i++) {
            var e = modell.elemente[i];
            if (!wahl.hasOwnProperty(e.id)) {
                neu.push(e);
                continue;
            }
            var dx, dy;
            if (e.typ === 'strich') {
                var punkte = [];
                for (var j = 0; j < e.punkte.length; j++) {
                    dx = e.punkte[j][0] - cx;
                    dy = e.punkte[j][1] - cy;
                    punkte.push([cx + dx * cos - dy * sin, cy + dx * sin + dy * cos]);
                }
                neu.push({ id: e.id, typ: 'strich', werkzeug: e.werkzeug, farbe: e.farbe,
                    breite: e.breite, punkte: punkte });
            } else if (e.typ === 'text' || e.typ === 'bild') {
                dx = e.x - cx;
                dy = e.y - cy;
                var g = versetzt(e, cx + dx * cos - dy * sin - e.x, cy + dx * sin + dy * cos - e.y, e.id);
                delete g.winkel;
                neu.push(mitWinkel(g, (e.winkel || 0) + grad));
            } else {
                neu.push(e);
            }
        }
        return kopf(modell, neu);
    }

    /**
     * Kopien der Elemente mit diesen IDs, um (dx, dy) versetzt, mit neuen
     * IDs aus `neueId()` (Lasso-Duplizieren 3.1.143). Die Kopien kommen in
     * Listenreihenfolge ans ENDE der Liste (also obenauf in ihrer
     * Zeichenebene); Originale bleiben dieselben Objekte. Ohne `neueId`
     * werden die IDs ab hoechsteIdNummer(modell) + 1 vergeben.
     * Rueckgabe: { modell, ids } mit den IDs der Kopien.
     */
    function kopiere(modell, ids, dx, dy, neueId) {
        var n = hoechsteIdNummer(modell) + 1;
        var id = typeof neueId === 'function' ? neueId : function () { return 'e' + (n++); };
        var wahl = mengeAus(ids);
        var kopien = [];
        var neueIds = [];
        for (var i = 0; i < modell.elemente.length; i++) {
            var e = modell.elemente[i];
            if (!wahl.hasOwnProperty(e.id)) continue;
            if (e.typ !== 'strich' && e.typ !== 'text' && e.typ !== 'bild') continue;
            var k = versetzt(e, dx, dy, id());
            kopien.push(k);
            neueIds.push(k.id);
        }
        return { modell: kopf(modell, modell.elemente.concat(kopien)), ids: neueIds };
    }

    /**
     * Schrift (px) nach einem Schritt groesser (richtung > 0) bzw. kleiner
     * (< 0): mal bzw. durch TEXT_SCHRITT, ganzzahlig, mindestens 1 px
     * Aenderung. Groesser endet bei TEXT_PX_MAX, kleiner bei TEXT_PX_MIN;
     * eine Schrift jenseits der Grenze (z. B. nach skaliere()) bleibt in
     * dieser Richtung unveraendert.
     */
    function naechsteSchrift(schrift, richtung) {
        if (richtung > 0) {
            if (schrift >= TEXT_PX_MAX) return schrift;
            return Math.min(TEXT_PX_MAX, Math.max(schrift + 1, Math.round(schrift * TEXT_SCHRITT)));
        }
        if (richtung < 0) {
            if (schrift <= TEXT_PX_MIN) return schrift;
            return Math.max(TEXT_PX_MIN, Math.min(schrift - 1, Math.round(schrift / TEXT_SCHRITT)));
        }
        return schrift;
    }

    /**
     * Neues Modell, in dem die TEXTE unter diesen IDs einen Schritt groesser
     * (richtung > 0) bzw. kleiner (< 0) sind (3.1.144, Knoepfe "A+"/"A-" der
     * Auswahlleiste). Jeder Text waechst mit seiner eigenen Schrift, die
     * Verhaeltnisse bleiben also, bis ein Text eine Grenze erreicht. Linke
     * obere Ecke (x, y) und winkel bleiben; Striche, Bilder und nicht
     * gewaehlte Texte bleiben dieselben Objekte. Die Schrift folgt aus
     * `breite` (schriftPx), deshalb wird `breite` = (Schrift - 16) / 4
     * gesetzt - ein Vielfaches von 0,25, das serialisiere() exakt behaelt.
     * Liefert { modell, geaendert } (geaendert = Zahl der veraenderten Texte).
     */
    function textGroesse(modell, ids, richtung) {
        var wahl = mengeAus(ids);
        var neu = [];
        var geaendert = 0;
        for (var i = 0; i < modell.elemente.length; i++) {
            var e = modell.elemente[i];
            if (e.typ !== 'text' || !wahl.hasOwnProperty(e.id) || !istZahl(richtung)) {
                neu.push(e);
                continue;
            }
            var alt = schriftPx(e.breite);
            var schrift = naechsteSchrift(alt, richtung);
            if (schrift === alt) {
                neu.push(e);
                continue;
            }
            geaendert++;
            neu.push(mitWinkel(mitTeile({ id: e.id, typ: 'text', farbe: e.farbe, breite: (schrift - 16) / 4,
                text: e.text, x: e.x, y: e.y }, e.teile), e.winkel));
        }
        return { modell: kopf(modell, neu), geaendert: geaendert };
    }

    /**
     * Schriftgroessen (px) der Texte unter diesen IDs: { anzahl, min, max }
     * (ohne Texte anzahl 0, min/max null). Fuer die Anzeige und das
     * Sperren der Knoepfe in der Auswahlleiste.
     */
    function textGroessen(modell, ids) {
        var wahl = mengeAus(ids);
        var anzahl = 0, min = null, max = null;
        for (var i = 0; i < modell.elemente.length; i++) {
            var e = modell.elemente[i];
            if (e.typ !== 'text' || !wahl.hasOwnProperty(e.id)) continue;
            var s = schriftPx(e.breite);
            anzahl++;
            if (min === null || s < min) min = s;
            if (max === null || s > max) max = s;
        }
        return { anzahl: anzahl, min: min, max: max };
    }

    /**
     * Drehwinkel in Grad vom Strahl (cx,cy)->(ax,ay) zum Strahl
     * (cx,cy)->(bx,by), in (-180, 180], Bildschirmsinn wie drehe(). Ist
     * einer der Strahlen kuerzer als 1e-9 -> 0.
     */
    function drehwinkel(cx, cy, ax, ay, bx, by) {
        var ux = ax - cx, uy = ay - cy, vx = bx - cx, vy = by - cy;
        if (ux * ux + uy * uy < 1e-18 || vx * vx + vy * vy < 1e-18) return 0;
        var grad = (Math.atan2(vy, vx) - Math.atan2(uy, ux)) * 180 / Math.PI;
        return normWinkel(grad);
    }

    /** Neues Modell ohne die Elemente mit diesen IDs. */
    function entferne(modell, ids) {
        var weg = mengeAus(ids);
        var neu = [];
        for (var i = 0; i < modell.elemente.length; i++) {
            if (!weg.hasOwnProperty(modell.elemente[i].id)) neu.push(modell.elemente[i]);
        }
        return kopf(modell, neu);
    }

    /**
     * Punkt-Radierer (E11): schneidet aus einem Strich-Element den Teil im
     * Kreis (cx, cy, r) heraus. Echte Segment-Kreis-Schnitte — ein langes
     * Segment ohne Zwischenpunkte, das den Kreis quert, wird geteilt; die
     * Teilstuecke enden auf dem Kreisrand (minimal ausserhalb, damit kein
     * Punkt im Kreis liegt).
     *
     * Rueckgabe: Array von Strich-Elementen.
     *   - nichts beruehrt / kein Strich -> [element] (dieselbe Referenz)
     *   - sonst neue Elemente mit IDs aus `neueId()`; Stuecke mit < 2 Punkten entfallen.
     */
    function radiereKreis(el, cx, cy, r, neueId) {
        if (!el || el.typ !== 'strich' || !(r > 0)) return [el];
        // Geringe Toleranz: Punkte genau auf dem Rand (bzw. Rundungsrauschen
        // eines Tangentenpunkts) gelten als ausserhalb.
        var rInnen = r * (1 - 1e-9);
        var r2 = rInnen * rInnen;

        function istInnen(x, y) {
            var dx = x - cx, dy = y - cy;
            return dx * dx + dy * dy < r2;
        }

        function randPunkt(x, y) {
            // Punkt auf dem Kreisrand minimal nach aussen schieben, damit er
            // trotz Gleitkomma-Rundung nie im Kreis liegt.
            var dx = x - cx, dy = y - cy;
            var d = Math.sqrt(dx * dx + dy * dy);
            if (d === 0) return [x, y];
            var ziel = r * (1 + 1e-9) + 1e-7;
            if (d >= ziel) return [x, y];
            return [cx + dx / d * ziel, cy + dy / d * ziel];
        }

        var punkte = el.punkte;
        var stuecke = [];
        var lauf = null;
        var geaendert = false;

        function anhaengen(p) {
            if (!lauf) {
                lauf = [p];
                return;
            }
            var l = lauf[lauf.length - 1];
            if (Math.abs(l[0] - p[0]) < 1e-6 && Math.abs(l[1] - p[1]) < 1e-6) return;
            lauf.push(p);
        }

        function abschliessen() {
            if (lauf) stuecke.push(lauf);
            lauf = null;
        }

        if (punkte.length === 1) {
            return istInnen(punkte[0][0], punkte[0][1]) ? [] : [el];
        }

        if (!istInnen(punkte[0][0], punkte[0][1])) {
            anhaengen([punkte[0][0], punkte[0][1]]);
        } else {
            geaendert = true;
        }

        for (var i = 0; i < punkte.length - 1; i++) {
            var ax = punkte[i][0], ay = punkte[i][1];
            var bx = punkte[i + 1][0], by = punkte[i + 1][1];
            var ddx = bx - ax, ddy = by - ay;
            var fx = ax - cx, fy = ay - cy;

            // Schnittparameter t in (0, 1) von |A + t(B-A) - C| = r
            var ts = [0];
            var a = ddx * ddx + ddy * ddy;
            if (a > 0) {
                var b = 2 * (fx * ddx + fy * ddy);
                var c = fx * fx + fy * fy - r * r;
                var disk = b * b - 4 * a * c;
                if (disk > 0) {
                    var w = Math.sqrt(disk);
                    var t1 = (-b - w) / (2 * a);
                    var t2 = (-b + w) / (2 * a);
                    if (t1 > 0 && t1 < 1) ts.push(t1);
                    if (t2 > 0 && t2 < 1 && t2 !== t1) ts.push(t2);
                }
            }
            ts.push(1);

            for (var k = 0; k < ts.length - 1; k++) {
                var ta = ts[k], tb = ts[k + 1];
                if (tb <= ta) continue;
                var tm = (ta + tb) / 2;
                var mitteInnen = istInnen(ax + ddx * tm, ay + ddy * tm);
                if (mitteInnen) {
                    geaendert = true;
                    // Eintritt in den Kreis: laufendes Stueck am Rand beenden.
                    if (lauf) {
                        anhaengen(randPunkt(ax + ddx * ta, ay + ddy * ta));
                        abschliessen();
                    }
                } else {
                    if (!lauf) {
                        // Austritt aus dem Kreis: neues Stueck am Rand beginnen.
                        anhaengen(ta === 0
                            ? (istInnen(ax, ay) ? randPunkt(ax, ay) : [ax, ay])
                            : randPunkt(ax + ddx * ta, ay + ddy * ta));
                    }
                    if (tb === 1) {
                        anhaengen([bx, by]);
                    }
                }
            }
        }
        abschliessen();

        if (!geaendert) return [el];

        var ergebnis = [];
        for (var s = 0; s < stuecke.length; s++) {
            if (stuecke[s].length < 2) continue;
            ergebnis.push({ id: neueId(), typ: 'strich', werkzeug: el.werkzeug, farbe: el.farbe,
                breite: el.breite, punkte: stuecke[s] });
        }
        return ergebnis;
    }

    /**
     * In eine Zeichenflaeche anderer Groesse strecken (E13): x mit
     * zielB/breite, y mit zielH/hoehe; Strichbreite und Schriftgroesse mit
     * dem Mittel beider Faktoren. Da die Schriftgroesse eines Texts aus
     * `breite` folgt (Math.round(16 + breite * 4)), wird bei Texten die
     * `breite` so gesetzt, dass die SCHRIFT mit dem Mittel waechst
     * (untere Grenze 0.1, sonst wuerde die Breite bei starkem Verkleinern
     * negativ). Ungueltiges Ziel -> unveraenderte Kopie.
     */
    function skaliere(modell, zielB, zielH) {
        if (!istZahl(zielB) || !istZahl(zielH) || zielB <= 0 || zielH <= 0) {
            return kopf(modell, modell.elemente.slice());
        }
        var sx = zielB / modell.breite;
        var sy = zielH / modell.hoehe;
        var sm = (sx + sy) / 2;
        var neu = [];
        for (var i = 0; i < modell.elemente.length; i++) {
            var e = modell.elemente[i];
            if (e.typ === 'strich') {
                var punkte = [];
                for (var j = 0; j < e.punkte.length; j++) {
                    punkte.push([e.punkte[j][0] * sx, e.punkte[j][1] * sy]);
                }
                neu.push({ id: e.id, typ: 'strich', werkzeug: e.werkzeug, farbe: e.farbe,
                    breite: e.breite * sm, punkte: punkte });
            } else if (e.typ === 'text') {
                var tb = e.breite;
                if (sm !== 1) {
                    tb = Math.max(0.1, ((16 + e.breite * 4) * sm - 16) / 4);
                }
                // winkel bleibt; bei ungleichen Faktoren sx/sy ist das eine
                // Naeherung (eine gedrehte Flaeche wuerde eigentlich geschert)
                neu.push(mitWinkel(mitTeile({ id: e.id, typ: 'text', farbe: e.farbe, breite: tb,
                    text: e.text, x: e.x * sx, y: e.y * sy }, e.teile), e.winkel));
            } else if (e.typ === 'bild') {
                neu.push(mitWinkel({ id: e.id, typ: 'bild', daten: e.daten, x: e.x * sx, y: e.y * sy,
                    b: e.b * sx, h: e.h * sy }, e.winkel));
            } else {
                neu.push(e);
            }
        }
        return { v: VERSION, breite: zielB, hoehe: zielH, elemente: neu };
    }

    // =====================================================================
    // Serialisieren
    // =====================================================================

    /** JSON-Zeichenkette; alle Zahlen (Koordinaten, Masse, Breiten) auf 2 Nachkommastellen. */
    function serialisiere(modell) {
        var el = [];
        for (var i = 0; i < modell.elemente.length; i++) {
            var e = modell.elemente[i];
            if (e.typ === 'strich') {
                var punkte = [];
                for (var j = 0; j < e.punkte.length; j++) {
                    punkte.push([runde(e.punkte[j][0]), runde(e.punkte[j][1])]);
                }
                el.push({ id: e.id, typ: 'strich', werkzeug: e.werkzeug, farbe: e.farbe,
                    breite: runde(e.breite), punkte: punkte });
            } else if (e.typ === 'text') {
                // winkel in Grad, damit 2 Nachkommastellen genuegen (0,01°);
                // auf 0 gerundet entfaellt das Feld. teile (AP-1.1) nur, wenn
                // vorhanden - ohne teile byte-gleich zu 3.1.147.
                el.push(mitWinkel(mitTeile({ id: e.id, typ: 'text', farbe: e.farbe, breite: runde(e.breite),
                    text: e.text, x: runde(e.x), y: runde(e.y) }, e.teile), runde(e.winkel || 0)));
            } else if (e.typ === 'bild') {
                el.push(mitWinkel({ id: e.id, typ: 'bild', daten: e.daten, x: runde(e.x), y: runde(e.y),
                    b: runde(e.b), h: runde(e.h) }, runde(e.winkel || 0)));
            }
        }
        return JSON.stringify({ v: VERSION, breite: runde(modell.breite), hoehe: runde(modell.hoehe), elemente: el });
    }

    /** JSON-Zeichenkette -> `pruefeModell(JSON.parse(text))`; Fehler -> null. */
    function deserialisiere(text) {
        if (typeof text !== 'string' || text === '') return null;
        var daten;
        try {
            daten = JSON.parse(text);
        } catch (e) {
            return null;
        }
        return pruefeModell(daten);
    }

    /**
     * Hoechste Zahl n aller IDs `e<n>` im Modell (0 bei leerem Modell). Fuer
     * AP-4.3: ein ID-Zaehler, der ueber ein geladenes Modell hinaus eindeutig
     * bleibt, startet bei hoechsteIdNummer(modell) + 1.
     */
    function hoechsteIdNummer(modell) {
        var max = 0;
        var el = modell && modell.elemente ? modell.elemente : [];
        for (var i = 0; i < el.length; i++) {
            if (typeof el[i].id === 'string' && ID_MUSTER.test(el[i].id)) {
                var n = parseInt(el[i].id.substring(1), 10);
                if (n > max) max = n;
            }
        }
        return max;
    }

    /** Leeres Modell der angegebenen Groesse. */
    function leeresModell(breite, hoehe) {
        return { v: VERSION, breite: breite, hoehe: hoehe, elemente: [] };
    }

    // ---------------------------------------------------------------------
    // Fingerabdruck des PNG neben dem Modell (AP-4.fix2, Befund F2 aus
    // AP-4.rev). Eine aeltere Plugin-Version (Rueckweg) ueberschreibt nur das
    // PNG und laesst die gespeicherte Elementliste stehen. Damit diese alte
    // Liste das neuere PNG beim naechsten Oeffnen nicht ueberdeckt, wird sie
    // lokal in einer Huelle abgelegt:
    //     {"png":"<abdruck des PNG>","modell":<serialisiertes Modell v1>}
    // Das Modell selbst bleibt unveraendert (Elementmodell v1). Passt der
    // Abdruck nicht zum PNG, gilt das PNG (E7). Werte ohne Huelle (Stand vor
    // AP-4.fix2) gelten ebenfalls als ungueltig. Der Server legt dieselbe
    // Huelle mit md5 an (CBD_Classroom), prueft sie aber selbst und liefert
    // dem Browser nur das Modell - abdruck() wird also nur lokal gebraucht.
    // ---------------------------------------------------------------------

    /**
     * Abdruck eines Textes: "<Laenge>-<FNV-1a 32 Bit, 8 Hex>". Keine
     * Kryptographie (es geht um "gleich oder geaendert"), synchron und ohne
     * Abhaengigkeit (crypto.subtle ist asynchron und auf http nicht da).
     * Gemessen (Chromium, AP-4.fix2): 3 MB ~50 ms, uebliche Tafel-PNGs
     * (50-400 KB) wenige Millisekunden - einmal je Speichern/Oeffnen.
     */
    function abdruck(text) {
        if (typeof text !== 'string') return null;
        var h = 0x811c9dc5;
        for (var i = 0; i < text.length; i++) {
            h ^= text.charCodeAt(i);
            // h * 16777619 (FNV-Primzahl = 2^24 + 2^8 + 0x93) modulo 2^32,
            // ohne Math.imul (ES5): Summe der Verschiebungen bleibt < 2^35
            h = (h + (h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24)) >>> 0;
        }
        var hex = h.toString(16);
        while (hex.length < 8) hex = '0' + hex;
        return text.length + '-' + hex;
    }

    /** Huelle um einen serialisierten Modelltext; '' wenn eines fehlt. */
    function verpacke(png, modellText) {
        if (typeof png !== 'string' || png === '' || typeof modellText !== 'string' || modellText === '') return '';
        return '{"png":"' + abdruck(png) + '","modell":' + modellText + '}';
    }

    /**
     * Modelltext aus der Huelle, aber nur, wenn der Abdruck zum uebergebenen
     * PNG passt; sonst null. Liest nur den festen Kopf (kein JSON.parse des
     * ganzen Werts); der Modelltext wird danach wie bisher mit
     * deserialisiere() geprueft.
     */
    function entpacke(text, png) {
        if (typeof text !== 'string' || typeof png !== 'string' || png === '') return null;
        var kopf = '{"png":"' + abdruck(png) + '","modell":';
        // Das Modell ist ein JSON-Objekt: die Huelle endet also auf "}}" (ein
        // abgeschnittener Wert faellt so schon hier auf, nicht erst beim Parsen)
        if (text.length <= kopf.length + 2 || text.indexOf(kopf) !== 0 || text.slice(-2) !== '}}') return null;
        return text.slice(kopf.length, -1);
    }

    global.CBDBoardElemente = {
        VERSION: VERSION,
        TEXT_PX_MIN: TEXT_PX_MIN,
        TEXT_PX_MAX: TEXT_PX_MAX,
        MAX_TEILE: MAX_TEILE,
        pruefeModell: pruefeModell,
        ausStrokes: ausStrokes,
        zuStrokes: zuStrokes,
        rahmen: rahmen,
        punktImPolygon: punktImPolygon,
        waehleImLasso: waehleImLasso,
        verschiebe: verschiebe,
        drehe: drehe,
        kopiere: kopiere,
        drehwinkel: drehwinkel,
        naechsteSchrift: naechsteSchrift,
        textGroesse: textGroesse,
        textGroessen: textGroessen,
        entferne: entferne,
        radiereKreis: radiereKreis,
        skaliere: skaliere,
        serialisiere: serialisiere,
        deserialisiere: deserialisiere,
        hoechsteIdNummer: hoechsteIdNummer,
        leeresModell: leeresModell,
        schriftPx: schriftPx,
        schrift: schrift,
        zeilenTeile: zeilenTeile,
        teileZusammenlegen: teileZusammenlegen,
        abdruck: abdruck,
        verpacke: verpacke,
        entpacke: entpacke
    };
})(window);
