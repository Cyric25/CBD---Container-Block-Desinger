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
 * ueber eine uebergebene Messfunktion `ctxMass(text, schriftPx) -> px`.
 *
 * Format (verbindlich, Plan Abschnitt 4 „Elementmodell v1"):
 *   { v: 1, breite, hoehe, elemente: [
 *     { id:'e1', typ:'strich', werkzeug:'pen'|'highlighter', farbe, breite, punkte:[[x,y],...] },
 *     { id:'e2', typ:'text', farbe, breite, text, x, y },
 *     { id:'e3', typ:'bild', daten:'data:image/png;base64,...', x, y, b, h } ] }
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
            return { id: el.id, typ: 'text', farbe: el.farbe, breite: el.breite,
                text: el.text.length > MAX_TEXT ? el.text.substring(0, MAX_TEXT) : el.text,
                x: el.x, y: el.y };
        }

        if (el.typ === 'bild') {
            if (typeof el.daten !== 'string' || el.daten.indexOf(BILD_PRAEFIX) !== 0) return null;
            if (!BASE64_MUSTER.test(el.daten.substring(BILD_PRAEFIX.length))) return null;
            if (!istZahl(el.x) || !istZahl(el.y) || !istZahl(el.b) || !istZahl(el.h)) return null;
            if (el.b <= 0 || el.h <= 0) return null;
            return { id: el.id, typ: 'bild', daten: el.daten, x: el.x, y: el.y, b: el.b, h: el.h };
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
            var zeilen = String(el.text).split('\n');
            var breitest = 0;
            for (var z = 0; z < zeilen.length; z++) {
                // Ohne Messfunktion grob schaetzen (0,6 em je Zeichen).
                var w = typeof ctxMass === 'function'
                    ? ctxMass(zeilen[z], groesse)
                    : zeilen[z].length * groesse * 0.6;
                if (w > breitest) breitest = w;
            }
            return { x: el.x, y: el.y, b: breitest, h: zeilen.length * ZEILENHOEHE * groesse };
        }
        if (el.typ === 'bild') {
            return { x: el.x, y: el.y, b: el.b, h: el.h };
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
            if (!wahl.hasOwnProperty(e.id)) {
                neu.push(e);
            } else if (e.typ === 'strich') {
                var punkte = [];
                for (var j = 0; j < e.punkte.length; j++) {
                    punkte.push([e.punkte[j][0] + dx, e.punkte[j][1] + dy]);
                }
                neu.push({ id: e.id, typ: 'strich', werkzeug: e.werkzeug, farbe: e.farbe,
                    breite: e.breite, punkte: punkte });
            } else if (e.typ === 'text') {
                neu.push({ id: e.id, typ: 'text', farbe: e.farbe, breite: e.breite,
                    text: e.text, x: e.x + dx, y: e.y + dy });
            } else if (e.typ === 'bild') {
                neu.push({ id: e.id, typ: 'bild', daten: e.daten, x: e.x + dx, y: e.y + dy,
                    b: e.b, h: e.h });
            } else {
                neu.push(e);
            }
        }
        return kopf(modell, neu);
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
                neu.push({ id: e.id, typ: 'text', farbe: e.farbe, breite: tb,
                    text: e.text, x: e.x * sx, y: e.y * sy });
            } else if (e.typ === 'bild') {
                neu.push({ id: e.id, typ: 'bild', daten: e.daten, x: e.x * sx, y: e.y * sy,
                    b: e.b * sx, h: e.h * sy });
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
                el.push({ id: e.id, typ: 'text', farbe: e.farbe, breite: runde(e.breite),
                    text: e.text, x: runde(e.x), y: runde(e.y) });
            } else if (e.typ === 'bild') {
                el.push({ id: e.id, typ: 'bild', daten: e.daten, x: runde(e.x), y: runde(e.y),
                    b: runde(e.b), h: runde(e.h) });
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

    global.CBDBoardElemente = {
        VERSION: VERSION,
        pruefeModell: pruefeModell,
        ausStrokes: ausStrokes,
        zuStrokes: zuStrokes,
        rahmen: rahmen,
        punktImPolygon: punktImPolygon,
        waehleImLasso: waehleImLasso,
        verschiebe: verschiebe,
        entferne: entferne,
        radiereKreis: radiereKreis,
        skaliere: skaliere,
        serialisiere: serialisiere,
        deserialisiere: deserialisiere,
        hoechsteIdNummer: hoechsteIdNummer,
        leeresModell: leeresModell,
        schriftPx: schriftPx
    };
})(window);
