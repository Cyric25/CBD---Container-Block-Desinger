/**
 * Container Block Designer - Board Mode (Tafel-Modus) v3.0
 * Multi-Layer Canvas System with Background Picker, Hexagon Grid, and Advanced Tools
 *
 * @package ContainerBlockDesigner
 * @since 2.9.1
 * @updated 3.0.0 - Complete rewrite with multi-layer canvas system
 */

(function() {
    'use strict';

    // Elementmodell v1 (PLAN-Rueckmeldungen-Klassenmodus-und-Tafel-Lasso.md,
    // Phase 4, AP-4.3): Geometrie und Datenformat liegen im DOM-freien Modul
    // assets/js/board-elemente.js (window.CBDBoardElemente, per
    // Abhaengigkeit 'cbd-board-elemente' VOR dieser Datei geladen).
    var UNDO_MAX = 50; // Architektur E12: hoechstens 50 Rueckgaengig-Schritte

    function elementeModul() {
        return window.CBDBoardElemente;
    }

    // "Blocktext auf die Tafel" (PLAN-Blocktext-auf-Tafel.md, Phase 2):
    // Formelumwandlung, Absatzzerlegung, Umbruch, Verteilung und Farbwahl
    // liegen im DOM-freien Modul assets/js/board-blocktext.js
    // (window.CBDBoardBlocktext, per Abhaengigkeit 'cbd-board-blocktext' VOR
    // dieser Datei geladen). Hier bleibt nur, was DOM und berechnete Stile
    // braucht (_blocktextLesen, Architektur E6).
    function blocktextModul() {
        return window.CBDBoardBlocktext;
    }

    // Was _blocktextLesen() samt Inhalt ueberspringt. Bedienelemente und
    // Medien tragen keinen Text, den ein Schueler abschreiben will; die
    // Klassen sind Knoepfe/Tafelbilder des Plugins im Blockbereich und
    // .screen-reader-text (per clip versteckt, nicht per display - traegt in
    // .cbd-container-number die Blocknummer; Review-Befund G7).
    var BLOCKTEXT_WEG_TAGS = {
        SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEMPLATE: 1, BUTTON: 1, INPUT: 1,
        SELECT: 1, TEXTAREA: 1, OPTION: 1, SVG: 1, CANVAS: 1, IMG: 1,
        PICTURE: 1, VIDEO: 1, AUDIO: 1, IFRAME: 1, OBJECT: 1, EMBED: 1
    };
    var BLOCKTEXT_WEG_KLASSEN = [
        'cbd-action-buttons', 'cbd-class-drawing-section', 'cbd-drawing-overlay',
        'cbd-drawing-toggle', 'cbd-board-blocktext-leiste', 'screen-reader-text'
    ];

    // Formatierung beim Lesen (PLAN-Blocktext-Formatierung.md, AP-2.1):
    // Stil eines Zeichens, das nicht aus einem Textknoten stammt - Listen-
    // praefix, " | " zwischen Tabellenzellen, Leerzeichen an Blockgrenzen
    // in einer Tabellenzeile und "\n" aus BR.
    var BLOCKTEXT_KLARTEXT = { f: 0, k: 0, farbe: null };

    // Namen der Stiftfarben (presetColors) für die Farbknöpfe der
    // Formatleiste im Bearbeitungsfeld (PLAN-Blocktext-Formatierung.md,
    // AP-2.4): aria-label und Tooltip. Unbekannte Farbe -> ihr Hex-Wert.
    var FORMAT_FARBNAMEN = {
        '#000000': 'Schwarz', '#ffffff': 'Weiß', '#ff0000': 'Rot',
        '#0000ff': 'Blau', '#ffff00': 'Gelb', '#00ff00': 'Grün'
    };

    /**
     * Farbangabe -> '#rrggbb' oder null (AP-2.1, Review-Befund B10 aus
     * AP-1.rev). getComputedStyle liefert meist rgb()/rgba(), fuer
     * color-mix() aber color(srgb …) oder oklab(…). Solche Formen passen
     * nicht auf das Farbmuster des Elementmodells, und teileZusammenlegen()
     * liesse sie still weg - deshalb wird hier vereinheitlicht:
     * #rgb/#rrggbb, rgb()/rgba() (Komma- oder Leerzeichenform) und
     * color(srgb r g b [/ a]) mit Anteilen 0..1 werden zu '#rrggbb';
     * ein Alpha ueber 0 wird uebergangen (halb durchsichtiges Rot -> Rot).
     * null (= keine eigene Farbe): Alpha 0 (durchsichtig, Regel F6) und
     * alles nicht Darstellbare (oklab, lab, display-p3, Farbnamen, Anteile
     * ausserhalb des Bereichs).
     */
    function blocktextHexFarbe(wert) {
        if (typeof wert !== 'string') return null;
        var s = wert.replace(/^\s+|\s+$/g, '').toLowerCase();
        var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/.exec(s);
        if (m) {
            var h = m[1];
            if (h.length === 3) {
                h = h.charAt(0) + h.charAt(0) + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2);
            }
            return '#' + h;
        }
        var faktor = 1;
        m = /^rgba?\(([^()]*)\)$/.exec(s);
        if (!m) {
            m = /^color\(\s*srgb\s+([^()]*)\)$/.exec(s);
            if (!m) return null;
            faktor = 255;
        }
        var werte = m[1].split(/[\s,\/]+/);
        if (werte.length && werte[0] === '') werte.shift();
        if (werte.length && werte[werte.length - 1] === '') werte.pop();
        if (werte.length !== 3 && werte.length !== 4) return null;
        var zahlen = [];
        for (var i = 0; i < werte.length; i++) {
            if (!/^[+-]?(\d+\.?\d*|\.\d+)(e[+-]?\d+)?$/.test(werte[i])) return null;
            zahlen.push(parseFloat(werte[i]));
        }
        if (zahlen.length === 4 && !(zahlen[3] > 0)) return null;
        var hex = '#';
        for (var j = 0; j < 3; j++) {
            var v = zahlen[j] * faktor;
            if (!(v >= -0.5 && v <= 255.5)) return null;
            var n = Math.max(0, Math.min(255, Math.round(v)));
            hex += (n < 16 ? '0' : '') + n.toString(16);
        }
        return hex;
    }

    /**
     * Einzelne UTF-16-Surrogate in einer Zeichenkette durch U+FFFD ersetzen
     * (AP-2.fix2, Review-Befund B5 aus AP-2.rev): ein High-Surrogat ohne
     * folgendes Low und ein Low ohne vorheriges High. Eine präparierte
     * Zwischenablage (text/plain) oder ein zerlegtes Zeichen im Feld
     * brächten sonst ein halbes Zeichen in den Text; json_decode im Server
     * lehnt das ab, und die Klassen-Tafel würde nur als Bild gespeichert.
     * Gültige Paare (Emoji) bleiben ganz.
     */
    function blocktextEinzelneSurrogate(text) {
        var s = String(text);
        var aus = '';
        var von = 0;
        for (var i = 0; i < s.length; i++) {
            var c = s.charCodeAt(i);
            if (c >= 0xD800 && c <= 0xDBFF) {
                var n = i + 1 < s.length ? s.charCodeAt(i + 1) : 0;
                if (n >= 0xDC00 && n <= 0xDFFF) {
                    i++;
                    continue;
                }
            } else if (!(c >= 0xDC00 && c <= 0xDFFF)) {
                continue;
            }
            aus += s.slice(von, i) + '�';
            von = i + 1;
        }
        return von === 0 && aus === '' ? s : aus + s.slice(von);
    }

    /**
     * Wie blocktextEinzelneSurrogate() für einen gelesenen Absatz
     * {text, teile?}. Ersetzt wird anhand von text (ein Paar über einer
     * Teilgrenze bleibt gültig und unverändert); jedes Zeichen wird durch
     * genau ein Zeichen ersetzt, die Länge ändert sich also nie. Die
     * Verkettung der Teile ergibt danach weiter text. Ein Absatz ohne
     * einzelnes Surrogat kommt unverändert zurück (dasselbe Objekt).
     */
    function blocktextAbsatzOhneEinzelneSurrogate(absatz) {
        var neu = blocktextEinzelneSurrogate(absatz.text);
        if (neu === absatz.text) return absatz;
        var aus = { text: neu };
        if (absatz.teile) {
            var pos = 0;
            aus.teile = absatz.teile.map(function(teil) {
                var kopie = {};
                for (var schluessel in teil) {
                    if (Object.prototype.hasOwnProperty.call(teil, schluessel)) kopie[schluessel] = teil[schluessel];
                }
                kopie.t = neu.slice(pos, pos + teil.t.length);
                pos += teil.t.length;
                return kopie;
            });
        }
        return aus;
    }

    /**
     * teile eines Absatzes {text, teile?} für textErsetzen()
     * (PLAN-Blocktext-Formatierung.md, AP-3.1): brauchbar sind sie nur als
     * Array von Objekten mit Zeichenketten-t, deren Verkettung genau text
     * ist (dieselbe Regel wie absatzNormieren() in board-blocktext.js und
     * teileVon in _textFeldBauen). Liefert dann dasselbe Array (es wird nur
     * gelesen), sonst null - der Absatz gilt als Klartext. f/k/farbe prüft
     * später _blocktextMitTeilen() (lesbare Farbe, teileZusammenlegen).
     */
    function textTeileBrauchbar(teile, text) {
        if (!Array.isArray(teile)) return null;
        var kette = '';
        for (var i = 0; i < teile.length; i++) {
            var teil = teile[i];
            if (teil === null || typeof teil !== 'object' || typeof teil.t !== 'string') return null;
            kette += teil.t;
            if (kette.length > text.length) return null;
        }
        return kette === text ? teile : null;
    }

    /**
     * Längster Text eines Text-Elements in UTF-16-Einheiten (AP-3.fix1):
     * dieselbe Grenze wie MAX_TEXT in board-elemente.js (pruefeModell kürzt
     * beim Laden/Speichern mit substring, dabei ginge die Formatierung
     * verloren) und mb_substr(…, 0, 5000) in class-cbd-classroom.php.
     * board-elemente.js gibt MAX_TEXT nicht heraus - bitte beide gleich halten.
     */
    var TEXT_MAX_EINHEITEN = 5000;

    /**
     * Teile im Bereich [a, e) eines Texts, dessen Teile teile sind
     * (Positionen in UTF-16-Einheiten), als neue Objekte {t[, f: 1][, k: 1]
     * [, farbe]} - für den Zeilenumbruch in textErsetzen() (AP-3.1). Die
     * Bereiche enden dort an Zeilenwechseln, zerschneiden also kein
     * Surrogatpaar.
     */
    function textTeileAusschnitt(teile, a, e) {
        var aus = [];
        var pos = 0;
        for (var i = 0; i < teile.length && pos < e; i++) {
            var t = teile[i].t;
            var von = Math.max(a, pos);
            var bis = Math.min(e, pos + t.length);
            if (bis > von) {
                var n = { t: t.slice(von - pos, bis - pos) };
                if (teile[i].f) n.f = 1;
                if (teile[i].k) n.k = 1;
                if (teile[i].farbe) n.farbe = teile[i].farbe;
                aus.push(n);
            }
            pos += t.length;
        }
        return aus;
    }

    /**
     * Teilgrenzen mitten in einem Surrogatpaar (AP-3.fix2, Review AP-3.rev,
     * Befund B2) für textErsetzen(): Endet ein Teil mit einem hohen Surrogat
     * (0xD800-0xDBFF) und beginnt der nächste nicht leere mit einem tiefen
     * (0xDC00-0xDFFF), rückt die Grenze eine Einheit nach hinten - das tiefe
     * Surrogat kommt zum Teil des hohen, ein dadurch leerer Teil entfällt.
     * Sonst stünde in zwei t je ein einzelnes Surrogat: JSON.stringify
     * schreibt es als \ud83d, PHP json_decode lehnt das ab, die
     * Klassen-Tafel würde nur als Bild gespeichert (Familie AP-1.rev B1).
     * Die Verkettung bleibt der Text. Ohne solche Grenze: dasselbe Array;
     * sonst neue Objekte (Kopien aller Schlüssel). Benachbarte gleiche Teile
     * legt danach _blocktextMitTeilen zusammen (teileZusammenlegen) - hier
     * nicht, das lieferte bei reinem Klartext [] statt der Teile.
     */
    function textTeileOhneGeteiltePaare(teile) {
        var aus = [];
        var verschoben = false;
        var vorher = -1; // Index in aus des letzten nicht leeren Teils
        for (var i = 0; i < teile.length; i++) {
            var kopie = {};
            for (var schluessel in teile[i]) {
                if (Object.prototype.hasOwnProperty.call(teile[i], schluessel)) kopie[schluessel] = teile[i][schluessel];
            }
            var t = kopie.t;
            var c = t.length ? t.charCodeAt(0) : 0;
            if (vorher >= 0 && c >= 0xDC00 && c <= 0xDFFF) {
                var v = aus[vorher].t;
                var h = v.charCodeAt(v.length - 1);
                if (h >= 0xD800 && h <= 0xDBFF) {
                    aus[vorher].t = v + t.charAt(0);
                    kopie.t = t = t.slice(1);
                    verschoben = true;
                    if (!t.length) continue;
                }
            }
            aus.push(kopie);
            if (t.length) vorher = aus.length - 1;
        }
        return verschoben ? aus : teile;
    }

    /**
     * Breite einer Zeile (teile ohne "\n") in Schrift px, je Teil fett/kursiv
     * gemessen - wie CBDBoardElemente.rahmen() eine Zeile misst (ohne
     * Messfunktion 0,6 em je Zeichen). Für die Umbruchregel F12a, R2
     * (AP-3.fix2).
     */
    function textZeilenBreite(teile, px, mass) {
        var w = 0;
        for (var i = 0; i < teile.length; i++) {
            var teil = teile[i];
            w += typeof mass === 'function'
                ? mass(teil.t, px, { f: teil.f ? 1 : 0, k: teil.k ? 1 : 0 })
                : teil.t.length * px * 0.6;
        }
        return w;
    }

    // Leerraum eines gelesenen Absatzes bereinigen - zeichenweise auf Text
    // UND Stil-Liste zugleich (AP-2.1). Ergebnis-Text exakt wie die frueheren
    // regulaeren Ausdruecke in _blocktextLesen() (bis 3.1.147):
    //   text.replace(/ {2,}/g, ' ').replace(/ *\n */g, '\n')
    //       .replace(/^[ \n]+|[ \n]+$/g, '')
    // 1. Mehrere Leerzeichen -> eines; das ERSTE behaelt seinen Stil
    //    (<p>a <b> b </b> c</p> -> "a " normal, "b " fett, "c" normal).
    // 2. Leerzeichen direkt vor oder nach "\n" entfallen (nach Schritt 1
    //    sind Leerzeichenfolgen hoechstens 1 lang - genau das, was der
    //    zweite Ausdruck entfernt).
    // 3. Leerzeichen und "\n" an beiden Raendern entfallen.
    // Nur U+0020 und "\n" werden angefasst, Surrogatpaare bleiben ganz.
    // Bitte nicht wieder auf die regulaeren Ausdruecke umstellen: die
    // Stil-Liste muss Zeichen fuer Zeichen mitlaufen. (Als Zeilenkommentar,
    // weil der zweite Ausdruck einen Block-Kommentar beenden wuerde.)
    function blocktextBereinigen(text, stile) {
        var z = [], s = [];
        var i;
        for (i = 0; i < text.length; i++) {
            var c = text.charAt(i);
            if (c === ' ' && i > 0 && text.charAt(i - 1) === ' ') continue;
            z.push(c);
            s.push(stile[i]);
        }
        var z2 = [], s2 = [];
        for (i = 0; i < z.length; i++) {
            if (z[i] === ' ' && ((i > 0 && z[i - 1] === '\n') || (i + 1 < z.length && z[i + 1] === '\n'))) continue;
            z2.push(z[i]);
            s2.push(s[i]);
        }
        var von = 0, bis = z2.length;
        while (von < bis && (z2[von] === ' ' || z2[von] === '\n')) von++;
        while (bis > von && (z2[bis - 1] === ' ' || z2[bis - 1] === '\n')) bis--;
        return { text: z2.slice(von, bis).join(''), stile: s2.slice(von, bis) };
    }

    // Eigene Notizen im localStorage (AP-4.4, Schritt 7): Alle Schlüssel mit
    // dem Präfix 'cbd-board-' gehören zu den Tafeln und werden exportiert,
    // importiert und mit "Alle Notizen löschen" entfernt - außer den
    // Werkzeug-Einstellungen. GEZÄHLT ("N Notizen") werden aber nur die
    // PNG-Schlüssel selbst, nicht die Begleitschlüssel (-elemente seit
    // Phase 4, -bgcolor, -classid) und nicht die Seitenzahl
    // ('cbd-board-pagecount-'). Gleiche Regel in personal-notes-manager.js.
    var NOTIZ_BEGLEITSUFFIXE = ['-elemente', '-bgcolor', '-classid'];

    function istTafelSchluessel(k) {
        return typeof k === 'string' && k.indexOf('cbd-board-') === 0 &&
            k !== 'cbd-board-tool-settings' && k !== 'cbd-board-font-size';
    }

    function istNotizSchluessel(k) {
        if (!istTafelSchluessel(k) || k.indexOf('cbd-board-pagecount-') === 0) return false;
        for (var i = 0; i < NOTIZ_BEGLEITSUFFIXE.length; i++) {
            var s = NOTIZ_BEGLEITSUFFIXE[i];
            if (k.length > s.length && k.slice(-s.length) === s) return false;
        }
        return true;
    }

    /** Flache Kopie eines Elements (Punkte-Arrays werden geteilt, nie verändert). */
    function elementKopie(el) {
        var k = {};
        for (var p in el) {
            if (Object.prototype.hasOwnProperty.call(el, p)) k[p] = el[p];
        }
        return k;
    }

    var NUR_BILD_TEXT = 'Nur als Bild gespeichert'; // E9 / AP-4.2: Elemente nicht gespeichert
    // AP-4.fix3 (F3): lokal ist trotz Aufräumen kein Platz für das PNG
    var SPEICHER_VOLL_TEXT = 'Nicht gespeichert – Speicher voll';

    // Obergrenze der Punkte einer an den Server gesendeten Elementliste
    // (AP-4.fix1, Befund F1). Gezählt wird wie auf dem Server die Zahl der
    // '[' im serialisierten Text (obere Schranke der Punktzahl). Darüber
    // sendet saveToServer() gar keine Liste - der Server verwürfe sie ohnehin
    // (CBD_Classroom::ELEMENTE_MAX_PUNKTE, gleicher Wert, nur gemeinsam
    // ändern; der Harnisch tools/test-board-elemente.php vergleicht beide).
    var ELEMENTE_MAX_PUNKTE = 100000;

    /** Zahl der '[' in einem Text (ohne Zwischen-Array, auch bei MB-Strings billig). */
    function klammerZahl(text) {
        var n = 0, i = -1;
        while ((i = text.indexOf('[', i + 1)) !== -1) n++;
        return n;
    }

    window.CBDBoardMode = {

        // State
        overlay: null,

        // Multi-layer canvases
        backgroundCanvas: null,
        backgroundCtx: null,
        gridCanvas: null,
        gridCtx: null,
        drawingCanvas: null,
        drawingCtx: null,
        canvasArea: null, // .cbd-board-canvas-area, Träger der Touch-Gestensperre (#7149)

        // Lasso (Phase 5, AP-5.1, Architektur E14): vierte Canvas-Ebene ÜBER
        // der Zeichenebene (pointer-events:none) - Lassolinie und
        // Auswahlrahmen landen so nie im gespeicherten PNG (das entsteht aus
        // drawingCanvas). Siehe "Lasso-Werkzeug" bei auswahlAufheben().
        auswahlCanvas: null,
        auswahlCtx: null,
        auswahl: [],          // IDs der gewählten Elemente (Listenreihenfolge)
        _lasso: null,         // Punkte [[x,y],…] der laufenden Lassolinie, sonst null
        _auswahlLeiste: null, // div.cbd-board-auswahl-leiste (Teil des Overlays)
        _auswahlFarbeWert: null,
        // AP-5.2: laufendes Verschieben der Auswahl (siehe _ziehenBeginnen),
        // sonst null
        _ziehen: null,
        // 3.1.143: Drehmodus der Auswahl (siehe auswahlDrehenStarten), sonst null
        _drehen: null,

        isDrawing: false,
        currentTool: 'pen',
        currentColor: '#000000',

        // Tool-spezifische Größen (unabhängig für jedes Tool)
        penWidth: 3,
        eraserStrokeWidth: 8,
        eraserPointWidth: 15,
        highlighterWidth: 20,
        highlighterOpacity: 0.3, // 0.0 - 1.0 (0% - 100%)

        lineWidth: 3, // Aktuelle Breite (wird beim Tool-Wechsel aktualisiert)
        containerId: null,
        boardColor: '#ffffff',
        fontSize: 150, // Textgröße in Prozent (150% = 1.5x normal, besser lesbar auf Tafel)
        lastX: 0,
        lastY: 0,
        resizeObserver: null,
        _viewportTimer: null, // Drossel für visualViewport-resize (#7149, AP-2.4)
        boundHandlers: {},

        // Alle aktuell aufliegenden Zeiger (nur noch Buchführung - die
        // Zeichen-Entscheidungen treffen drawingPointerId/stiftGesehen,
        // siehe "Zeigerverwaltung (#7149)" an onPointerDown()).
        activePointers: new Set(),

        // Zeigerverwaltung (#7149): genau EIN zeichnender Zeiger
        drawingPointerId: null,    // pointerId des Zeigers, der den laufenden Strich/Radiervorgang führt
        drawingPointerType: null,  // dessen pointerType ('pen' | 'touch' | 'mouse')
        stiftGesehen: false,       // Stift-Vorrang: true ab dem ersten pointerType 'pen' dieser Tafelsitzung

        // Elementmodell v1 (Phase 4, AP-4.3) - ersetzt das fruehere
        // this.strokes + this.baseImageObj. Alles, was auf der Zeichenebene
        // steht, ist ein Element in this.modell.elemente (Strich, Text, Bild);
        // zeichneModell() baut die Ebene daraus neu auf.
        //   - Elemente werden NIE an Ort und Stelle veraendert, nur ersetzt
        //     (der Undo-Stapel haelt flache Kopien der Elementliste, Bild-
        //     Elemente tragen grosse daten-Strings - siehe merkeZustand()).
        //   - currentStroke ist der "Strich in Arbeit" im bisherigen Format
        //     {tool, color, width, points:[{x,y}]} und wird erst in
        //     onPointerUp per CBDBoardElemente.ausStrokes() zum Element.
        modell: null,
        undoStapel: [],
        currentStroke: null,
        _idZaehler: 1,
        _idModell: null,      // Modell, gegen das _idZaehler zuletzt abgeglichen wurde
        _bildQuellen: null,   // Map daten-String -> Image|Canvas (Bild-Elemente)
        _radierBilder: null,  // { elementId: Canvas } waehrend einer Punkt-Radiergeste
        _radierLetzter: null, // letzte Radierposition (Zwischenpunkte bei schnellen Bewegungen)
        _radierGeaendert: false,
        _gesteGemerkt: false, // Radiergeste hat ihren Undo-Schritt schon angelegt
        _rahmenCache: null,   // WeakMap Element -> Rahmen (Elemente sind unveraenderlich)
        _ladeNr: 0,           // AP-4.4: zaehlt Oeffnen/Seitenwechsel hoch; eine Server-Antwort
                              // mit veralteter Nummer gehoert zu einer anderen Seite/Sitzung

        // Grid state: 'off', 'horizontal', 'vertical' (Sechsecke) und seit
        // 3.1.143 'kaestchen', 'zeilen'. gridArt = zuletzt gewählte Art, die
        // der Knopf beim Einschalten nimmt (Auswahlfeld .cbd-board-grid-art).
        gridMode: 'off',
        gridArt: 'horizontal',
        gridSize: 30, // Hexagon-Radius bzw. Kästchenseite in Pixeln (Zeilen: doppelt)
        gridOffsetX: 0, // Horizontaler Offset in Pixeln
        gridOffsetY: 0, // Vertikaler Offset in Pixeln

        // Eraser mode: 'stroke' or 'point'
        eraserMode: 'stroke',

        // Classroom System state
        classId: null,
        pageId: null,
        stableContainerId: null,
        ajaxUrl: null,
        nonce: null,
        isSaving: false,
        classes: [],
        isBehandeltSet: false, // Einmalig pro Session als behandelt markieren

        // Multi-Seiten-State
        currentPageIndex: 0,
        totalPages: 1,
        pageCache: {},   // { pageIndex: {png, modell} } ('' = Seite war leer; AP-4.4)

        // Zoom & Pan
        zoom: 1.0,
        panX: 0,
        panY: 0,
        _pinchPointers: {},      // { pointerId: {x, y} }
        _pinchStartDist: null,
        _pinchStartZoom: 1.0,
        _pinchStartPanX: 0,
        _pinchStartPanY: 0,
        _pinchMidX: 0,
        _pinchMidY: 0,

        // Preset board colors (for background)
        boardPresetColors: [
            '#ffffff', // Weiß (default)
            '#1a472a', // Grün
            '#1c1c1c'  // Schwarz
        ],

        // Preset pen colors
        presetColors: [
            '#000000', // Schwarz
            '#ffffff', // Weiß
            '#ff0000', // Rot
            '#0000ff', // Blau
            '#ffff00', // Gelb
            '#00ff00'  // Grün
        ],

        /**
         * Tafel-Modus oeffnen
         */
        open: function(containerId, contentHtml, boardColor, options) {
            // Verhindere doppeltes Oeffnen
            if (this.overlay) {
                return;
            }

            this.containerId = containerId;
            this.boardColor = boardColor || '#ffffff';

            // Classroom-Optionen setzen
            options = options || {};
            this.stableContainerId = options.stableContainerId || null;
            this.pageId = options.pageId || null;
            this.ajaxUrl = options.ajaxUrl || null;
            this.nonce = options.nonce || null;
            this.classes = options.classes || [];
            this.classId = null;
            this.isSaving = false;
            this.isBehandeltSet = false;

            // Lehrer: Selektor immer zeigen (persönlich oder Klasse wählen)
            if (this.ajaxUrl) {
                var self = this;
                this.showClassSelector(function(selectedClassId) {
                    self.classId = selectedClassId;
                    self._openOverlay(contentHtml);
                });
            } else {
                this._openOverlay(contentHtml);
            }
        },

        /**
         * Internes Oeffnen des Overlays
         */
        _openOverlay: function(contentHtml) {
            // Standardeinstellung bei jedem Öffnen (Rückmeldung 2026-10-12):
            // CBDBoardMode ist EIN Objekt je Seite - ohne diesen Rücksetzer
            // überlebten Werkzeug und Stiftfarbe das Schließen, während
            // createOverlayDOM() die Leiste fest mit aktivem Stift und
            // Schwarz baut. Wer rot aufhörte, schrieb in der Tafel eines
            // anderen Blocks rot weiter. Bitte nicht entfernen.
            // Seit 3.1.142 ebenso Breiten und Textmarker-Transparenz
            // (_standardBreiten); updateToolUI() unten stellt die Regler.
            this.currentTool = 'pen';
            this.eraserMode = 'stroke';
            this.currentColor = '#000000';
            this._standardBreiten();

            // Ein liegengebliebenes Fenster "Text auf die Tafel" (AP-2.3)
            // samt Klickschutz (AP-2.fix1) gehörte zur vorigen Tafel
            this._blocktextFensterSchliessen();
            this._blocktextKlickschutzEntfernen();

            // Multi-Seiten-State zurücksetzen
            this.currentPageIndex = 0;
            this.totalPages = 1;
            this.pageCache = {};

            // Zoom/Pan zurücksetzen
            this.zoom = 1.0;
            this.panX = 0;
            this.panY = 0;
            this._pinchPointers = {};
            this._pinchStartDist = null;

            // Elementmodell (AP-4.3): wird nach dem ersten Groessen-Abgleich
            // mit den CSS-Massen der Zeichenflaeche angelegt (siehe unten)
            this.modell = null;
            this.undoStapel = [];
            this.currentStroke = null;
            this._idZaehler = 1;
            this._idModell = null;
            this._bildQuellen = new Map();
            this._radierBilder = null;
            this._radierLetzter = null;
            this._rahmenCache = new WeakMap();
            this._ladeNr++;

            // Lasso (AP-5.1): keine Auswahl aus einer früheren Sitzung
            this.auswahl = [];
            this._lasso = null;
            this._auswahlFarbeWert = null;
            this._ziehen = null;
            this._drehen = null;

            // Zeigerverwaltung (#7149): Stift-Vorrang gilt je Tafelsitzung
            this.activePointers.clear();
            this.drawingPointerId = null;
            this.drawingPointerType = null;
            this.stiftGesehen = false;

            // Overlay-DOM erstellen
            this.createOverlayDOM(contentHtml);

            // Regler auf die geltende Breite setzen (Absicherung, falls die
            // fest gebaute Leiste und die Standardwerte auseinanderlaufen)
            this.updateToolUI();

            // 3.1.143: Das Raster überlebt das Schließen (gridMode), die
            // Leiste wird aber fest "aus" gebaut - Knopf, Auswahlfeld und
            // Regler an den Zustand angleichen
            this._gitterUIAktualisieren();

            // An Body anhaengen
            document.body.appendChild(this.overlay);

            // Scroll sperren
            document.body.style.overflow = 'hidden';

            // iOS-Gestensperre (#7149, E6): Klasse an <html> sperrt per CSS
            // (board-mode.css) Markieren/Callout/Overscroll der GANZEN Seite
            // und blendet schwebende Seitenknöpfe aus (E7). Rückstandsfrei
            // entfernbar in destroy(). Eine schon bestehende Markierung auf
            // der Seite (z. B. „PDF" des PDF-Knopfs) wird aufgehoben.
            document.documentElement.classList.add('cbd-board-offen');
            try {
                if (window.getSelection) {
                    window.getSelection().removeAllRanges();
                }
            } catch (selErr) {
                // ohne Markierungs-API weiter
            }

            // Canvas-Elemente initialisieren
            this.initCanvases();
            this.resizeAllCanvases();

            // Leeres Modell in der Groesse der Zeichenflaeche (CSS-Pixel)
            this.modell = this._leeresModell();

            // Seitenanzahl aus localStorage laden und UI aufbauen
            this.initPages();

            // Tafelfarbe-Button Anfangszustand setzen
            this.setBoardColor(this.boardColor);

            // Zeichnung laden (Seite 0)
            this.loadDrawing();

            // Events binden
            this.bindEvents();
        },

        /**
         * Tafel-Modus schliessen
         */
        close: function() {
            if (!this.overlay) return;

            // Fenster "Text auf die Tafel" sofort schließen, nicht erst in
            // destroy() 200 ms später - ein Einfügen in dieser Zeit wäre
            // nicht mehr gespeichert worden (AP-2.fix1, Review-Befund G2)
            this._blocktextFensterSchliessen();

            // Zeichnung speichern
            this.saveDrawing();

            // State für Tafelansicht sichern (vor destroy, nur lokaler Modus)
            var isLocal = !this.classId;
            var snapContainerId = this.containerId;
            var snapStableId = this.stableContainerId;
            var snapTotalPages = this.totalPages;
            var snapCurrentPage = this.currentPageIndex;

            // Klassen-Modus: prüfen ob aktuelle Seite leer ist
            var snapCurrentPageBlank = false;
            if (!isLocal && this.drawingCtx && this.totalPages === 1) {
                snapCurrentPageBlank = this._istLeer();
            }

            // Closing-Animation
            this.overlay.classList.add('cbd-board-closing');

            var self = this;
            setTimeout(function() {
                self.destroy();
                if (isLocal) {
                    // Lokaler Modus: Tafelansicht im Block aktualisieren
                    CBDBoardMode.updateBlockDisplay(snapContainerId, snapStableId, snapTotalPages, snapCurrentPage);
                } else if (snapCurrentPageBlank && snapStableId) {
                    // Klassen-Modus, einzelne Seite, leer: Drawing-Section entfernen
                    var container = document.querySelector('[data-stable-id="' + snapStableId + '"]');
                    if (container) {
                        var section = container.querySelector('.cbd-class-drawing-section');
                        if (section) section.parentNode.removeChild(section);
                    }
                }
            }, 200);
        },

        /**
         * Aufraeumen und Overlay entfernen
         */
        destroy: function() {
            // Vorschau/Rückfrage "Text auf die Tafel" (AP-2.3) und der
            // Klickschutz (AP-2.fix1) hängen am body
            this._blocktextFensterSchliessen();
            this._blocktextKlickschutzEntfernen();

            // Events entfernen
            this.unbindEvents();

            // ResizeObserver stoppen
            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }

            // Overlay entfernen
            if (this.overlay && this.overlay.parentNode) {
                this.overlay.parentNode.removeChild(this.overlay);
            }

            // Scroll freigeben
            document.body.style.overflow = '';

            // iOS-Gestensperre aufheben (#7149, siehe _openOverlay)
            document.documentElement.classList.remove('cbd-board-offen');

            // State zuruecksetzen
            this.overlay = null;
            this.backgroundCanvas = null;
            this.backgroundCtx = null;
            this.gridCanvas = null;
            this.gridCtx = null;
            this.drawingCanvas = null;
            this.drawingCtx = null;
            this.auswahlCanvas = null;
            this.auswahlCtx = null;
            this.isDrawing = false;
            this.containerId = null;
            this.classId = null;
            this.pageId = null;
            this.stableContainerId = null;
            this.isSaving = false;

            // Zeigerverwaltung (#7149): aktive Zeiger und Stift-Vorrang zurücksetzen
            this.activePointers.clear();
            this.drawingPointerId = null;
            this.drawingPointerType = null;
            this.stiftGesehen = false;

            // Elementmodell (AP-4.3) verwerfen
            this.modell = null;
            this.undoStapel = [];
            this.currentStroke = null;
            this._bildQuellen = null;
            this._radierBilder = null;
            this._radierLetzter = null;
            this._rahmenCache = null;
            this._idModell = null;

            // Lasso (AP-5.1): Auswahl verwerfen. Die Werkzeugleiste öffnet
            // immer mit aktivem Stift-Knopf (createOverlayDOM) - ein beim
            // Schließen aktives Lasso liefe sonst unsichtbar weiter.
            this.auswahl = [];
            this._lasso = null;
            this._auswahlLeiste = null;
            this._ziehen = null; // AP-5.2: ein noch offener Frame-Rückruf prüft das
            this._drehen = null; // 3.1.143: ebenso
            if (this.currentTool === 'lasso') {
                this.currentTool = 'pen';
            }

            // Multi-Seiten-State zurücksetzen
            this.currentPageIndex = 0;
            this.totalPages = 1;
            this.pageCache = {};

            // Zoom/Pan zurücksetzen
            this.zoom = 1.0;
            this.panX = 0;
            this.panY = 0;
            this._pinchPointers = {};
            this._pinchStartDist = null;
        },

        /**
         * Overlay-DOM erstellen mit Multi-Layer-Canvas
         */
        createOverlayDOM: function(contentHtml) {
            var overlay = document.createElement('div');
            overlay.className = 'cbd-board-overlay';
            overlay.id = 'cbd-board-overlay';

            // Header-Titel
            var titleExtra = '';
            if (this.classId) {
                var cls = this.classes.find(function(c) { return c.id == this.classId; }.bind(this));
                if (cls) {
                    titleExtra = ' <span class="cbd-board-class-badge">' + this._escHtml(cls.name) + '</span>';
                }
            }

            // Preset Pen Color Buttons HTML
            var presetColorsHtml = '';
            this.presetColors.forEach(function(color) {
                var borderColor = color === '#ffffff' ? '#ccc' : 'rgba(255, 255, 255, 0.5)';
                presetColorsHtml += '<button class="cbd-board-preset-color" data-color="' + color + '" style="background-color: ' + color + '; border-color: ' + borderColor + ';" title="' + color + '"></button>';
            });

            // Board Background Preset Buttons (for canvas overlay)
            var boardPresetHtml = '';
            this.boardPresetColors.forEach(function(color) {
                var label = color === '#ffffff' ? 'Weiß' : (color === '#1a472a' ? 'Grün' : 'Schwarz');
                var borderColor = color === '#ffffff' ? '#999' : 'rgba(255, 255, 255, 0.8)';
                boardPresetHtml += '<button class="cbd-board-bg-preset-btn" data-color="' + color + '" style="background-color: ' + color + '; border-color: ' + borderColor + ';" title="' + label + '">' + label + '</button>';
            });

            overlay.innerHTML =
                '<div class="cbd-board-header">' +
                    '<span class="cbd-board-title">' +
                        '<span class="dashicons dashicons-welcome-write-blog"></span>' +
                        'Tafel-Modus' + titleExtra +
                    '</span>' +
                    '<div class="cbd-board-header-actions">' +
                        '<span class="cbd-board-save-status" id="cbd-board-save-status"></span>' +
                        '<button class="cbd-board-close" title="Tafel-Modus beenden">&times;</button>' +
                    '</div>' +
                '</div>' +
                '<div class="cbd-board-split">' +
                    '<div class="cbd-board-content"></div>' +
                    '<div class="cbd-board-canvas-area">' +
                        '<div class="cbd-board-canvas-container">' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-background"></canvas>' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-grid"></canvas>' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-drawing"></canvas>' +
                            // Auswahl-Ebene (AP-5.1, E14): Lassolinie und Auswahlrahmen
                            '<canvas class="cbd-board-canvas cbd-board-canvas-auswahl"></canvas>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                // Toolbar: volle Breite, unterhalb beider Hälften
                '<div class="cbd-board-toolbar">' +
                    '<div class="cbd-board-toolbar-inner">' +
                        // Textgröße (linke Seite)
                        '<label class="cbd-board-font-label">📝</label>' +
                        '<input type="range" class="cbd-board-font-size" min="100" max="300" value="' + this.fontSize + '" step="10" title="Textgröße">' +
                        '<span class="cbd-board-font-size-display">' + this.fontSize + '%</span>' +
                        '<span class="cbd-board-separator"></span>' +
                        // Zeichen-Werkzeuge
                        '<button class="cbd-board-tool active" data-tool="pen" title="Stift">' +
                            '<span class="dashicons dashicons-edit"></span>' +
                        '</button>' +
                        '<button class="cbd-board-tool" data-tool="highlighter" title="Textmarkierer">' +
                            '<span class="dashicons dashicons-marker"></span>' +
                        '</button>' +
                        '<button class="cbd-board-tool" data-tool="eraser-stroke" title="Strich-Radierer">' +
                            '<span class="dashicons dashicons-editor-removeformatting"></span>' +
                        '</button>' +
                        '<button class="cbd-board-tool" data-tool="eraser-point" title="Punkt-Radierer">' +
                            '<span class="dashicons dashicons-dismiss"></span>' +
                        '</button>' +
                        '<button class="cbd-board-tool" data-tool="text" title="Text einfügen">' +
                            '<span class="dashicons dashicons-text-page"></span>' +
                        '</button>' +
                        // Lasso (AP-5.1): Elemente per Freihand-Umrandung auswählen
                        '<button class="cbd-board-tool" data-tool="lasso" title="Auswählen (Lasso)">' +
                            '<span class="dashicons dashicons-image-crop"></span>' +
                        '</button>' +
                        '<span class="cbd-board-separator"></span>' +
                        // Farben
                        '<input type="color" class="cbd-board-color" value="#000000" title="Stiftfarbe">' +
                        '<div class="cbd-board-preset-colors">' + presetColorsHtml + '</div>' +
                        '<span class="cbd-board-separator"></span>' +
                        // Stiftdicke
                        '<input type="range" class="cbd-board-width" min="1" max="20" value="3" title="Stiftdicke">' +
                        '<span class="cbd-board-width-display">3px</span>' +
                        // Textmarker-Transparenz (nur sichtbar wenn Textmarker aktiv)
                        '<div class="cbd-board-opacity-control" style="display: none; align-items: center; gap: 5px;">' +
                            '<span class="cbd-board-separator"></span>' +
                            '<label class="cbd-board-opacity-label">🎨</label>' +
                            '<input type="range" class="cbd-board-opacity" min="10" max="100" value="30" step="5" title="Textmarker-Transparenz">' +
                            '<span class="cbd-board-opacity-display">30%</span>' +
                        '</div>' +
                        '<span class="cbd-board-separator"></span>' +
                        // Gitter-Toggle (3.1.143: nur ein/aus, die Art wählt
                        // das Auswahlfeld in den Grid-Einstellungen)
                        '<button class="cbd-board-grid-toggle" title="Raster einblenden">' +
                            '<span class="dashicons dashicons-grid-view"></span>' +
                        '</button>' +
                        // Grid-Einstellungen (nur sichtbar wenn Gitter aktiv)
                        '<div class="cbd-board-grid-controls" style="display: none;">' +
                            '<select class="cbd-board-grid-art" title="Rasterart">' +
                                '<option value="kaestchen">Kästchen</option>' +
                                '<option value="zeilen">Zeilen</option>' +
                                '<option value="horizontal">Sechsecke – Kante seitlich</option>' +
                                '<option value="vertical">Sechsecke – Kante unten</option>' +
                            '</select>' +
                            '<label class="cbd-board-grid-label">Größe:</label>' +
                            '<input type="range" class="cbd-board-grid-size" min="15" max="60" value="30" title="Gitter-Größe">' +
                            '<span class="cbd-board-grid-size-display">30</span>' +
                            '<label class="cbd-board-grid-label">X:</label>' +
                            '<input type="range" class="cbd-board-grid-offset-x" min="-100" max="100" value="0" title="Horizontale Position">' +
                            '<span class="cbd-board-grid-offset-x-display">0</span>' +
                            '<label class="cbd-board-grid-label">Y:</label>' +
                            '<input type="range" class="cbd-board-grid-offset-y" min="-100" max="100" value="0" title="Vertikale Position">' +
                            '<span class="cbd-board-grid-offset-y-display">0</span>' +
                        '</div>' +
                        '<span class="cbd-board-separator"></span>' +
                        // Zeichnung löschen
                        '<button class="cbd-board-clear" title="Zeichnung löschen">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                        // Rückgängig
                        '<button class="cbd-board-undo" title="Rückgängig (Strg+Z)">' +
                            '<span class="dashicons dashicons-undo"></span>' +
                        '</button>' +
                        '<span class="cbd-board-separator"></span>' +
                        // Seiten-Navigation
                        '<button class="cbd-board-page-prev" title="Vorherige Seite" disabled>◀</button>' +
                        '<span class="cbd-board-page-indicator">1 / 1</span>' +
                        '<button class="cbd-board-page-next" title="Nächste Seite" disabled>▶</button>' +
                        '<button class="cbd-board-page-add" title="Neue Seite hinzufügen">+</button>' +
                        '<span class="cbd-board-separator"></span>' +
                        // Zoom-Steuerung
                        '<button class="cbd-board-zoom-out" title="Verkleinern">−</button>' +
                        '<span class="cbd-board-zoom-display">100%</span>' +
                        '<button class="cbd-board-zoom-in" title="Vergrößern">+</button>' +
                        '<button class="cbd-board-zoom-reset" title="Zoom zurücksetzen">⊙</button>' +
                        // Tafelfarbe (Zyklus-Button) – rechts außen
                        '<button class="cbd-board-bg-cycle" title="Tafelfarbe wechseln (Weiß / Grün / Schwarz)" style="background-color: ' + (this.boardColor || '#1a472a') + '; margin-left: auto;">' +
                            '<span class="dashicons dashicons-art"></span>' +
                        '</button>' +
                    '</div>' +
                    // Toolbar ein-/ausblenden
                    '<button class="cbd-board-toolbar-toggle" title="Toolbar ausblenden">▲</button>' +
                '</div>' +
                // Auswahlleiste (AP-5.1): schwebt über dem Auswahlrahmen. Liegt
                // bewusst NICHT im .cbd-board-canvas-area (dessen touchstart-
                // Sperre #7149 unterdrückte sonst den Klick per Finger) und
                // nicht im Canvas-Container (würde mitgezoomt).
                // "Löschen": auswahlLoeschen() (AP-5.2). "Drehen" und
                // "Duplizieren" (3.1.143): auswahlDrehenStarten() bzw.
                // auswahlDuplizieren(). Im Drehmodus zeigt die Leiste nur den
                // Hinweis und "Abbrechen" (Klasse --drehen, board-mode.css).
                // "A−"/"A+" mit Größenanzeige (3.1.144): auswahlTextGroesse(),
                // nur sichtbar, wenn Texte gewählt sind (Klasse --text).
                // "Text bearbeiten" (PLAN-Blocktext-Formatierung.md, AP-3.1,
                // F11): auswahlTextBearbeiten(), nur sichtbar bei genau EINEM
                // gewählten Text (Klasse --ein-text), nie im Drehmodus.
                '<div class="cbd-board-auswahl-leiste" role="toolbar" aria-label="Auswahl" style="display: none;">' +
                    '<button type="button" class="cbd-board-auswahl-knopf" data-aktion="drehen" title="Um einen Drehpunkt drehen">' +
                        '<span class="dashicons dashicons-image-rotate"></span> Drehen' +
                    '</button>' +
                    '<button type="button" class="cbd-board-auswahl-knopf" data-aktion="duplizieren" title="Auswahl duplizieren (Strg+D)">' +
                        '<span class="dashicons dashicons-admin-page"></span> Duplizieren' +
                    '</button>' +
                    '<button type="button" class="cbd-board-auswahl-knopf cbd-board-auswahl-nur-ein-text" data-aktion="text-bearbeiten" title="Text bearbeiten">' +
                        '<span class="dashicons dashicons-edit"></span> Text bearbeiten' +
                    '</button>' +
                    '<span class="cbd-board-auswahl-textgruppe cbd-board-auswahl-nur-text">' +
                        '<button type="button" class="cbd-board-auswahl-knopf cbd-board-auswahl-text-kleiner" data-aktion="text-kleiner" title="Text verkleinern" aria-label="Text verkleinern">A−</button>' +
                        '<span class="cbd-board-auswahl-textgroesse" title="Textgröße" aria-live="polite"></span>' +
                        '<button type="button" class="cbd-board-auswahl-knopf cbd-board-auswahl-text-groesser" data-aktion="text-groesser" title="Text vergrößern" aria-label="Text vergrößern">A+</button>' +
                    '</span>' +
                    '<button type="button" class="cbd-board-auswahl-knopf cbd-board-auswahl-loeschen" data-aktion="loeschen" title="Auswahl löschen">' +
                        '<span class="dashicons dashicons-trash"></span> Löschen' +
                    '</button>' +
                    '<button type="button" class="cbd-board-auswahl-knopf" data-aktion="aufheben" title="Auswahl aufheben (Esc)">Auswahl aufheben</button>' +
                    '<span class="cbd-board-auswahl-hinweis cbd-board-auswahl-nur-drehen" aria-live="polite"></span>' +
                    '<button type="button" class="cbd-board-auswahl-knopf cbd-board-auswahl-nur-drehen" data-aktion="drehen-abbrechen" title="Drehen abbrechen (Esc)">Abbrechen</button>' +
                '</div>';

            // Block-Inhalt einfuegen
            var contentArea = overlay.querySelector('.cbd-board-content');
            contentArea.innerHTML = contentHtml;

            // "Text auf die Tafel" (PLAN-Blocktext-auf-Tafel.md, AP-2.3, E4):
            // klebende Leiste als ERSTES Kind des Blockbereichs - ein drittes
            // Kind von .cbd-board-split bräche die Handy-/Querformat-Regeln.
            // Per insertBefore, damit der Inhalt nicht neu geparst wird.
            // _blocktextLesen() überspringt die Leiste.
            var blocktextLeiste = document.createElement('div');
            blocktextLeiste.className = 'cbd-board-blocktext-leiste';
            blocktextLeiste.innerHTML =
                '<button type="button" class="cbd-board-blocktext-knopf" title="Text dieses Blocks auf die Tafel übernehmen">' +
                    '<span class="dashicons dashicons-clipboard"></span> Text auf die Tafel' +
                '</button>';
            contentArea.insertBefore(blocktextLeiste, contentArea.firstChild);

            // Interaktive Blöcke initialisieren (Scripts ausführen)
            this.initializeInteractiveBlocks(contentArea);

            // Standard-Textgröße anwenden (150% für bessere Lesbarkeit auf Tafel)
            contentArea.style.fontSize = (this.fontSize / 100) + 'em';

            this.overlay = overlay;
            this._auswahlLeiste = overlay.querySelector('.cbd-board-auswahl-leiste');
        },

        /**
         * Canvas-Elemente initialisieren
         */
        initCanvases: function() {
            var canvases = this.overlay.querySelectorAll('.cbd-board-canvas');
            this.backgroundCanvas = canvases[0];
            this.gridCanvas = canvases[1];
            this.drawingCanvas = canvases[2];
            this.auswahlCanvas = canvases[3]; // AP-5.1, über der Zeichenebene

            this.backgroundCtx = this.backgroundCanvas.getContext('2d');
            this.gridCtx = this.gridCanvas.getContext('2d');
            this.drawingCtx = this.drawingCanvas.getContext('2d');
            this.auswahlCtx = this.auswahlCanvas.getContext('2d');
        },

        /**
         * Alle Canvas-Groessen anpassen (mit HiDPI/Retina-Unterstuetzung)
         */
        resizeAllCanvases: function() {
            if (!this.backgroundCanvas) return;

            var container = this.backgroundCanvas.parentElement;
            var cssWidth = container.clientWidth;
            var cssHeight = container.clientHeight;

            // Device Pixel Ratio für scharfe Darstellung auf HiDPI-Displays
            var dpr = window.devicePixelRatio || 1;

            // CSS-Dimensionen und DPR speichern (für Zeichenoperationen)
            this.dpr = dpr;
            this.cssWidth = cssWidth;
            this.cssHeight = cssHeight;

            // Alle Canvas-Dimensionen auf physische Pixel setzen (CSS × DPR)
            [this.backgroundCanvas, this.gridCanvas, this.drawingCanvas, this.auswahlCanvas].forEach(function(canvas) {
                canvas.width = cssWidth * dpr;
                canvas.height = cssHeight * dpr;
                canvas.style.width = cssWidth + 'px';
                canvas.style.height = cssHeight + 'px';
            });

            // Kontexte auf DPR skalieren – Zeichenkoordinaten bleiben in CSS-Pixeln
            [this.backgroundCtx, this.gridCtx, this.drawingCtx, this.auswahlCtx].forEach(function(ctx) {
                ctx.scale(dpr, dpr);
            });

            // Background neu zeichnen
            this.redrawBackground();

            // Raster neu zeichnen - die Größenänderung hat die Ebene geleert.
            // redrawGrid() selbst prüft gridMode. Bis 3.1.144 hing das an
            // einem Merker showGrid, der nie true wurde: Einschalten blendet
            // die Raster-Regler ein, die Leiste wird höher, die Fläche kleiner
            // - und das eben gezeichnete Raster verschwand wieder, bis ein
            // Regler bewegt wurde.
            this.redrawGrid();

            // Zeichnung aus dem Elementmodell neu aufbauen (AP-4.3, statt
            // getImageData/putImageData). modell.breite/hoehe bleiben bewusst
            // unverändert: die Koordinaten stehen 1:1 wie früher die Pixel,
            // auch Bild-Elemente behalten ihre Größe (kein Strecken).
            this.zeichneModell();

            // Auswahl-Ebene (AP-5.1): durch die Größenänderung geleert -
            // Lassolinie/Rahmen neu zeichnen, Leiste neu ausrichten
            this._auswahlZeichnen();
        },

        /**
         * Hintergrund neu zeichnen
         */
        redrawBackground: function() {
            if (!this.backgroundCtx || !this.backgroundCanvas) return;

            this.backgroundCtx.fillStyle = this.boardColor;
            this.backgroundCtx.fillRect(0, 0, this.cssWidth || this.backgroundCanvas.width, this.cssHeight || this.backgroundCanvas.height);
        },

        /**
         * Hexagon-Gitter zeichnen (horizontal oder vertical)
         */
        redrawGrid: function() {
            if (!this.gridCtx || !this.gridCanvas) return;

            // Grid canvas leeren (CSS-Dimensionen, da ctx.scale aktiv)
            this.gridCtx.clearRect(0, 0, this.cssWidth || this.gridCanvas.width, this.cssHeight || this.gridCanvas.height);

            if (this.gridMode === 'off') return;

            var width = this.cssWidth || this.gridCanvas.width;
            var height = this.cssHeight || this.gridCanvas.height;

            // Grid-Style (hellgrau, auf allen Hintergründen sichtbar)
            this.gridCtx.strokeStyle = 'rgba(150, 150, 150, 0.3)';
            this.gridCtx.lineWidth = 1;

            if (this.gridMode === 'horizontal') {
                // Horizontal: flache Kante seitlich (pointy-top)
                this.drawHorizontalGrid(width, height, this.gridSize);
            } else if (this.gridMode === 'vertical') {
                // Vertical: flache Kante unten (flat-top)
                this.drawVerticalGrid(width, height, this.gridSize);
            } else if (this.gridMode === 'kaestchen') {
                // 3.1.143: Rechenpapier, Kästchenseite = Größe
                this._gitterLinien(width, height, this.gridSize, true);
            } else if (this.gridMode === 'zeilen') {
                // 3.1.143: Linienpapier, Zeilenabstand = doppelte Größe
                // (Standard 60 px - Platz für Handschrift und Text-Werkzeug)
                this._gitterLinien(width, height, this.gridSize * 2, false);
            }
        },

        /**
         * Waagrechte Linien im Abstand `abstand` (Zeilen), mit senkrechten
         * zusätzlich Kästchen. Versatz X/Y wie bei den Sechsecken; Linien auf
         * halbe Pixel gelegt, damit sie scharf bleiben.
         */
        _gitterLinien: function(width, height, abstand, mitSenkrechten) {
            var ctx = this.gridCtx;
            var start = function(versatz) {
                return ((versatz % abstand) + abstand) % abstand;
            };
            var p;
            ctx.beginPath();
            for (p = start(this.gridOffsetY); p <= height; p += abstand) {
                ctx.moveTo(0, Math.round(p) + 0.5);
                ctx.lineTo(width, Math.round(p) + 0.5);
            }
            if (mitSenkrechten) {
                for (p = start(this.gridOffsetX); p <= width; p += abstand) {
                    ctx.moveTo(Math.round(p) + 0.5, 0);
                    ctx.lineTo(Math.round(p) + 0.5, height);
                }
            }
            ctx.stroke();
        },

        /**
         * Horizontal Grid: flache Kante seitlich (pointy-top)
         */
        drawHorizontalGrid: function(width, height, hexSize) {
            var hexWidth = hexSize * 2;
            var hexHeight = Math.sqrt(3) * hexSize;
            var hexHorizDist = hexWidth * 3 / 4;
            var hexVertDist = hexHeight;

            for (var row = -1; row < Math.ceil(height / hexVertDist) + 1; row++) {
                for (var col = -1; col < Math.ceil(width / hexHorizDist) + 1; col++) {
                    var x = col * hexHorizDist + this.gridOffsetX;
                    var y = row * hexVertDist + this.gridOffsetY;

                    // Jede zweite Spalte versetzt
                    if (col % 2 === 1) {
                        y += hexVertDist / 2;
                    }

                    this.drawHexagonPointyTop(x, y, hexSize);
                }
            }
        },

        /**
         * Vertical Grid: flache Kante unten (flat-top)
         */
        drawVerticalGrid: function(width, height, hexSize) {
            var hexHeight = hexSize * 2;
            var hexWidth = Math.sqrt(3) * hexSize;
            var hexVertDist = hexHeight * 3 / 4;
            var hexHorizDist = hexWidth;

            for (var row = -1; row < Math.ceil(height / hexVertDist) + 1; row++) {
                for (var col = -1; col < Math.ceil(width / hexHorizDist) + 1; col++) {
                    var x = col * hexHorizDist + this.gridOffsetX;
                    var y = row * hexVertDist + this.gridOffsetY;

                    // Jede zweite Reihe versetzt
                    if (row % 2 === 1) {
                        x += hexHorizDist / 2;
                    }

                    this.drawHexagonFlatTop(x, y, hexSize);
                }
            }
        },

        /**
         * Hexagon mit Spitze oben (pointy-top) - für Horizontal-Grid
         */
        drawHexagonPointyTop: function(centerX, centerY, radius) {
            this.gridCtx.beginPath();
            for (var i = 0; i < 6; i++) {
                var angle = (Math.PI / 3) * i;
                var x = centerX + radius * Math.cos(angle);
                var y = centerY + radius * Math.sin(angle);
                if (i === 0) {
                    this.gridCtx.moveTo(x, y);
                } else {
                    this.gridCtx.lineTo(x, y);
                }
            }
            this.gridCtx.closePath();
            this.gridCtx.stroke();
        },

        /**
         * Hexagon mit flacher Kante oben (flat-top) - für Vertical-Grid
         */
        drawHexagonFlatTop: function(centerX, centerY, radius) {
            this.gridCtx.beginPath();
            for (var i = 0; i < 6; i++) {
                // +Math.PI/6 dreht das Hexagon um 30° für flat-top Orientierung
                var angle = (Math.PI / 3) * i + Math.PI / 6;
                var x = centerX + radius * Math.cos(angle);
                var y = centerY + radius * Math.sin(angle);
                if (i === 0) {
                    this.gridCtx.moveTo(x, y);
                } else {
                    this.gridCtx.lineTo(x, y);
                }
            }
            this.gridCtx.closePath();
            this.gridCtx.stroke();
        },

        /**
         * Raster ein/aus (Knopf). Eingeschaltet wird die zuletzt gewählte
         * Art (gridArt). Bis 3.1.142 schaltete der Knopf reihum
         * off -> horizontal -> vertical; seit 3.1.143 wählt die Art das
         * Auswahlfeld (setGridArt).
         */
        toggleGrid: function() {
            this.gridMode = this.gridMode === 'off' ? (this.gridArt || 'horizontal') : 'off';
            this.redrawGrid();
            this._gitterUIAktualisieren();
        },

        /** Rasterart wählen und einschalten: 'kaestchen', 'zeilen', 'horizontal', 'vertical'. */
        setGridArt: function(art) {
            if (art !== 'kaestchen' && art !== 'zeilen' && art !== 'horizontal' && art !== 'vertical') return;
            this.gridArt = art;
            this.gridMode = art;
            this.redrawGrid();
            this._gitterUIAktualisieren();
        },

        /** Knopf, Auswahlfeld und Regler an gridMode/gridArt angleichen. */
        _gitterUIAktualisieren: function() {
            if (!this.overlay) return;
            var an = this.gridMode !== 'off';
            var btn = this.overlay.querySelector('.cbd-board-grid-toggle');
            if (btn) {
                btn.classList.toggle('active', an);
                btn.title = an ? 'Raster ausblenden' : 'Raster einblenden';
            }
            var art = this.overlay.querySelector('.cbd-board-grid-art');
            if (art) {
                art.value = an ? this.gridMode : (this.gridArt || 'horizontal');
            }
            // Grid-Einstellungen nur anzeigen wenn Gitter aktiv
            var gridControls = this.overlay.querySelector('.cbd-board-grid-controls');
            if (gridControls) {
                gridControls.style.display = an ? 'flex' : 'none';
            }
            // Regler zeigen die geltenden Werte (überleben das Schließen)
            var felder = [['.cbd-board-grid-size', this.gridSize], ['.cbd-board-grid-offset-x', this.gridOffsetX],
                ['.cbd-board-grid-offset-y', this.gridOffsetY]];
            for (var i = 0; i < felder.length; i++) {
                var regler = this.overlay.querySelector(felder[i][0]);
                var anzeige = this.overlay.querySelector(felder[i][0] + '-display');
                if (regler) regler.value = felder[i][1];
                if (anzeige) anzeige.textContent = felder[i][1];
            }
        },

        /**
         * Hintergrundfarbe aendern
         */
        setBoardColor: function(color) {
            this.boardColor = color;
            this.redrawBackground();
            // Toolbar-Button synchronisieren
            if (this.overlay) {
                var btn = this.overlay.querySelector('.cbd-board-bg-cycle');
                if (btn) {
                    btn.style.backgroundColor = color;
                    var icon = btn.querySelector('.dashicons');
                    if (icon) icon.style.color = color === '#ffffff' ? '#555' : '#fff';
                }
            }
            this.updateDarkModeInversion();
        },

        /**
         * AP-1.5 (PLAN-PDF-Export-und-Tafelmodus-Fixes.md): Invertiert die
         * Zeichenflaeche (Hintergrund- + Gitter- + Zeichen-Canvas gemeinsam,
         * ueber den gemeinsamen Wrapper .cbd-board-canvas-container) per
         * CSS-Filter, wenn die Website im Darkmode ist UND die Tafel auf der
         * weissen Standardfarbe steht. Wirkt rein visuell auf die Darstellung
         * - die in localStorage/serverseitig gespeicherten Pixel bleiben
         * unveraendert, betrifft also gleichermassen neu gezeichnete wie
         * bereits vorher gespeicherte Notizen.
         * AP-1.fix1 (Review-Befund B7): Der Filter ist seit F13
         * (PLAN-Blocktext-Formatierung.md, AP-1.4) `invert(1)
         * hue-rotate(180deg)` statt reinem `invert(1)`: Die Helligkeit kippt
         * wie bisher, der Farbton bleibt erhalten (Rot bleibt roetlich, Blau
         * blaeulich statt Tuerkis bzw. Gelb). Die Regel steht in
         * assets/css/board-mode.css (.cbd-board-inverted); die Auswahlebene
         * darin wendet die Umkehrung an. Bewusst
         * ausgenommen: Tafeln, die der Nutzer aktiv auf Gruen/Schwarz
         * gestellt hat (boardColor !== '#ffffff') - eine bewusste
         * Farbwahl soll nicht zusaetzlich invertiert werden.
         *
         * Aufgerufen von setBoardColor() - das deckt sowohl den initialen
         * Zustand beim Oeffnen (init() ruft setBoardColor(this.boardColor)
         * auf) als auch jeden Farbwechsel ueber den Tafelfarbe-Zyklus-Button
         * ab.
         */
        updateDarkModeInversion: function() {
            if (!this.overlay) return;
            var container = this.overlay.querySelector('.cbd-board-canvas-container');
            if (!container) return;
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var shouldInvert = isDark && this.boardColor === '#ffffff';
            container.classList.toggle('cbd-board-inverted', shouldInvert);
        },

        /**
         * Alle Event-Handler binden
         */
        bindEvents: function() {
            var self = this;

            // Gebundene Handler speichern
            this.boundHandlers = {
                pointerDown: function(e) { self.onPointerDown(e); },
                pointerMove: function(e) { self.onPointerMove(e); },
                pointerUp: function(e) { self.onPointerUp(e); },
                pointerLeave: function(e) { self.onPointerLeave(e); },
                keyDown: function(e) { self.onKeyDown(e); },
                contextMenu: function(e) { self.onContextMenu(e); },
                touchGeste: function(e) { self.onTouchGeste(e); },
                selectStart: function(e) { self.onSelectStart(e); },
                doppelklick: function(e) { self.onDoppelklick(e); }
            };

            // iOS-Gestensperre (#7149, E6). WebKit ignoriert user-select am
            // Overlay teilweise; Lupe, Markieren und Doppeltipp-Zoom lassen
            // sich auf der Zeichenfläche nur über preventDefault() auf
            // Touch-Ebene zuverlässig unterdrücken. Deshalb:
            // - touchstart/touchmove NUR am Canvas-Bereich, nicht-passiv
            //   (sonst ignoriert der Browser preventDefault). Die
            //   Inhaltshälfte bleibt frei, damit sie per Finger scrollt.
            //   Pointer-Ereignisse kommen trotzdem an (Zeichnen unberührt).
            // - selectstart am document: keine Markierung auf der GANZEN
            //   Seite, solange die Tafel offen ist (auch hinter dem Overlay).
            // - dblclick am Overlay: kein Wort-Markieren/Doppeltipp-Zoom.
            // Ausnahme jeweils Eingabefelder (siehe _istEingabefeld), u. a.
            // textarea.cbd-board-text-input, das an document.body hängt.
            // Alle drei in boundHandlers, entfernt in unbindEvents().
            this.canvasArea = this.overlay.querySelector('.cbd-board-canvas-area');
            if (this.canvasArea) {
                this.canvasArea.addEventListener('touchstart', this.boundHandlers.touchGeste, { passive: false });
                this.canvasArea.addEventListener('touchmove', this.boundHandlers.touchGeste, { passive: false });
            }
            document.addEventListener('selectstart', this.boundHandlers.selectStart);
            this.overlay.addEventListener('dblclick', this.boundHandlers.doppelklick);

            // Kontextmenü im ganzen Overlay unterdrücken (Fehlermeldung #7063):
            // Edge/Chrome unter Windows öffnen bei langem Halten mit Finger
            // oder Stift das Browsermenü (Kopieren …) – mitten beim Schreiben.
            // touch-action:none am Canvas verhindert das NICHT. Der Listener
            // hängt am Overlay, nicht am Canvas, weil das Menü sonst weiter über
            // Werkzeugleiste und Blockinhalt-Hälfte käme. Ausnahmen siehe
            // onContextMenu().
            this.overlay.addEventListener('contextmenu', this.boundHandlers.contextMenu);

            // Drawing Canvas Events (nur auf dem obersten Layer)
            this.drawingCanvas.addEventListener('pointerdown', this.boundHandlers.pointerDown);
            this.drawingCanvas.addEventListener('pointermove', this.boundHandlers.pointerMove);
            this.drawingCanvas.addEventListener('pointerup', this.boundHandlers.pointerUp);
            this.drawingCanvas.addEventListener('pointerleave', this.boundHandlers.pointerLeave);
            this.drawingCanvas.addEventListener('pointercancel', this.boundHandlers.pointerUp);

            // ESC-Taste
            document.addEventListener('keydown', this.boundHandlers.keyDown);

            // Close-Button
            var closeBtn = this.overlay.querySelector('.cbd-board-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    self.close();
                });
            }

            // "Text auf die Tafel" (AP-2.3): Knopf der Leiste im Blockbereich
            var blocktextKnopf = this.overlay.querySelector('.cbd-board-blocktext-knopf');
            if (blocktextKnopf) {
                blocktextKnopf.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.blocktextVorschau();
                });
            }

            // Tool-Buttons
            var toolButtons = this.overlay.querySelectorAll('.cbd-board-tool');
            toolButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tool = this.getAttribute('data-tool');
                    self.setTool(tool);

                    toolButtons.forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                });
            });

            // Pen Color
            var colorInput = this.overlay.querySelector('.cbd-board-color');
            if (colorInput) {
                colorInput.addEventListener('input', function() {
                    self.setColor(this.value);
                });
            }

            // Pen Color Presets
            var presetButtons = this.overlay.querySelectorAll('.cbd-board-preset-colors .cbd-board-preset-color');
            presetButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var color = this.getAttribute('data-color');
                    self.setColor(color);
                    if (colorInput) colorInput.value = color;
                });
            });

            // Tafelfarbe Zyklus-Button
            var bgCycleBtn = this.overlay.querySelector('.cbd-board-bg-cycle');
            if (bgCycleBtn) {
                bgCycleBtn.addEventListener('click', function() {
                    var colors = self.boardPresetColors;
                    var idx = colors.indexOf(self.boardColor);
                    var nextColor = colors[(idx + 1) % colors.length];
                    self.setBoardColor(nextColor);
                    bgCycleBtn.style.backgroundColor = nextColor;
                    // Icon-Farbe für Kontrast anpassen
                    var icon = bgCycleBtn.querySelector('.dashicons');
                    if (icon) {
                        icon.style.color = nextColor === '#ffffff' ? '#555' : '#fff';
                    }
                });
            }

            // Line Width
            var widthInput = this.overlay.querySelector('.cbd-board-width');
            var widthDisplay = this.overlay.querySelector('.cbd-board-width-display');
            if (widthInput) {
                widthInput.addEventListener('input', function() {
                    var w = parseInt(this.value, 10);
                    self.setLineWidth(w);
                    if (widthDisplay) {
                        widthDisplay.textContent = w + 'px';
                    }
                });
            }

            // Highlighter Opacity
            var opacityInput = this.overlay.querySelector('.cbd-board-opacity');
            var opacityDisplay = this.overlay.querySelector('.cbd-board-opacity-display');
            if (opacityInput) {
                opacityInput.addEventListener('input', function() {
                    var percent = parseInt(this.value, 10);
                    var opacity = percent / 100;
                    self.setHighlighterOpacity(opacity);
                    if (opacityDisplay) {
                        opacityDisplay.textContent = percent + '%';
                    }
                });
            }

            // Font Size – alle Schieberegler (oben + unten) synchron halten
            var fontSizeInputs = this.overlay.querySelectorAll('.cbd-board-font-size');
            var fontSizeDisplays = this.overlay.querySelectorAll('.cbd-board-font-size-display');
            fontSizeInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    var size = parseInt(this.value, 10);
                    self.setFontSize(size);
                    fontSizeInputs.forEach(function(inp) { inp.value = size; });
                    fontSizeDisplays.forEach(function(disp) { disp.textContent = size + '%'; });
                });
            });

            // Grid Toggle
            var gridToggle = this.overlay.querySelector('.cbd-board-grid-toggle');
            if (gridToggle) {
                gridToggle.addEventListener('click', function() {
                    self.toggleGrid();
                });
            }

            // Rasterart (3.1.143)
            var gridArt = this.overlay.querySelector('.cbd-board-grid-art');
            if (gridArt) {
                gridArt.addEventListener('change', function() {
                    self.setGridArt(this.value);
                });
            }

            // Grid Size
            var gridSizeInput = this.overlay.querySelector('.cbd-board-grid-size');
            var gridSizeDisplay = this.overlay.querySelector('.cbd-board-grid-size-display');
            if (gridSizeInput) {
                gridSizeInput.addEventListener('input', function() {
                    var size = parseInt(this.value, 10);
                    self.gridSize = size;
                    self.redrawGrid();
                    if (gridSizeDisplay) {
                        gridSizeDisplay.textContent = size;
                    }
                });
            }

            // Grid Offset X
            var gridOffsetXInput = this.overlay.querySelector('.cbd-board-grid-offset-x');
            var gridOffsetXDisplay = this.overlay.querySelector('.cbd-board-grid-offset-x-display');
            if (gridOffsetXInput) {
                gridOffsetXInput.addEventListener('input', function() {
                    var offset = parseInt(this.value, 10);
                    self.gridOffsetX = offset;
                    self.redrawGrid();
                    if (gridOffsetXDisplay) {
                        gridOffsetXDisplay.textContent = offset;
                    }
                });
            }

            // Grid Offset Y
            var gridOffsetYInput = this.overlay.querySelector('.cbd-board-grid-offset-y');
            var gridOffsetYDisplay = this.overlay.querySelector('.cbd-board-grid-offset-y-display');
            if (gridOffsetYInput) {
                gridOffsetYInput.addEventListener('input', function() {
                    var offset = parseInt(this.value, 10);
                    self.gridOffsetY = offset;
                    self.redrawGrid();
                    if (gridOffsetYDisplay) {
                        gridOffsetYDisplay.textContent = offset;
                    }
                });
            }

            // Clear Button
            var clearBtn = this.overlay.querySelector('.cbd-board-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    self.showClearConfirm();
                });
            }

            // Toolbar ein-/ausblenden
            var toolbarToggle = this.overlay.querySelector('.cbd-board-toolbar-toggle');
            if (toolbarToggle) {
                toolbarToggle.addEventListener('click', function() {
                    var toolbar = self.overlay.querySelector('.cbd-board-toolbar');
                    var inner = self.overlay.querySelector('.cbd-board-toolbar-inner');
                    if (inner && toolbar) {
                        var isHidden = inner.style.display === 'none';
                        inner.style.display = isHidden ? 'flex' : 'none';
                        toolbar.classList.toggle('cbd-board-toolbar--collapsed', !isHidden);
                        toolbarToggle.textContent = isHidden ? '▲' : '▼';
                        toolbarToggle.title = isHidden ? 'Toolbar ausblenden' : 'Toolbar einblenden';
                    }
                });
            }

            // Auswahlleiste (AP-5.1): ein Listener für beide Knöpfe. Hängt am
            // Element im Overlay und verschwindet mit ihm (kein unbind nötig).
            if (this._auswahlLeiste) {
                this._auswahlLeiste.addEventListener('click', function(e) {
                    var k = (e.target && e.target.closest) ? e.target.closest('[data-aktion]') : null;
                    if (!k) return;
                    var aktion = k.getAttribute('data-aktion');
                    if (aktion === 'aufheben') {
                        self.auswahlAufheben();
                    } else if (aktion === 'loeschen' && typeof self.auswahlLoeschen === 'function') {
                        self.auswahlLoeschen(); // AP-5.2
                    } else if (aktion === 'drehen') {
                        self.auswahlDrehenStarten(); // 3.1.143
                    } else if (aktion === 'duplizieren') {
                        self.auswahlDuplizieren(); // 3.1.143
                    } else if (aktion === 'text-groesser') {
                        self.auswahlTextGroesse(1); // 3.1.144
                    } else if (aktion === 'text-kleiner') {
                        self.auswahlTextGroesse(-1);
                    } else if (aktion === 'text-bearbeiten') {
                        self.auswahlTextBearbeiten(); // PLAN-Blocktext-Formatierung.md, AP-3.1/AP-3.2
                    } else if (aktion === 'drehen-abbrechen') {
                        self._drehenAbbrechen();
                    }
                });
            }

            // Seiten-Navigation
            var pagePrevBtn = this.overlay.querySelector('.cbd-board-page-prev');
            if (pagePrevBtn) {
                pagePrevBtn.addEventListener('click', function() {
                    self.goToPage(self.currentPageIndex - 1);
                });
            }

            var pageNextBtn = this.overlay.querySelector('.cbd-board-page-next');
            if (pageNextBtn) {
                pageNextBtn.addEventListener('click', function() {
                    self.goToPage(self.currentPageIndex + 1);
                });
            }

            var pageAddBtn = this.overlay.querySelector('.cbd-board-page-add');
            if (pageAddBtn) {
                pageAddBtn.addEventListener('click', function() {
                    self.addPage();
                });
            }

            // Klassentafelbild-Toggle im linken Inhaltsbereich aktivieren
            // (jQuery-Handler der classroom-page-filter.js gehen beim innerHTML-Kopieren verloren)
            var contentPanel = this.overlay.querySelector('.cbd-board-content');
            if (contentPanel) {
                contentPanel.addEventListener('click', function(e) {
                    var btn = e.target.closest('.cbd-drawing-toggle');
                    if (!btn) return;
                    var drawingOverlay = btn.parentNode.querySelector('.cbd-drawing-overlay');
                    if (!drawingOverlay) return;
                    var willShow = drawingOverlay.style.display === 'none';
                    drawingOverlay.style.display = willShow ? 'block' : 'none';
                    // Behalte den Typ des Buttons (Tafelbild vs. Eigene Notiz)
                    var isNote = btn.textContent.indexOf('Notiz') !== -1;
                    if (isNote) {
                        btn.textContent = willShow ? '📝 Eigene Notiz verbergen' : '📝 Eigene Notiz anzeigen';
                    } else {
                        btn.textContent = willShow ? '📋 Tafelbild verbergen' : '📋 Tafelbild anzeigen';
                    }
                    btn.classList.toggle('cbd-drawing-toggle-active', willShow);
                });
            }

            // Undo-Button
            var undoBtn = this.overlay.querySelector('.cbd-board-undo');
            if (undoBtn) {
                undoBtn.addEventListener('click', function() {
                    self.undo();
                });
            }

            // Zoom-Buttons
            var zoomInBtn = this.overlay.querySelector('.cbd-board-zoom-in');
            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', function() {
                    self.zoomIn();
                });
            }

            var zoomOutBtn = this.overlay.querySelector('.cbd-board-zoom-out');
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', function() {
                    self.zoomOut();
                });
            }

            var zoomResetBtn = this.overlay.querySelector('.cbd-board-zoom-reset');
            if (zoomResetBtn) {
                zoomResetBtn.addEventListener('click', function() {
                    self.resetZoom();
                });
            }

            // ResizeObserver
            if (typeof ResizeObserver !== 'undefined') {
                this.resizeObserver = new ResizeObserver(function() {
                    self.resizeAllCanvases();
                });
                this.resizeObserver.observe(this.backgroundCanvas.parentElement);
            }

            // Sichtbarer Bereich (iPad: Tastatur, Split View, #7149 AP-2.4).
            // Gedrosselt; zeichnet die Leinwände nur neu, wenn sich die Größe
            // des Canvas-Containers wirklich geändert hat (visualViewport
            // meldet auch reine Verschiebungen/Zoom).
            if (window.visualViewport && typeof window.visualViewport.addEventListener === 'function') {
                this.boundHandlers.viewportResize = function() {
                    if (self._viewportTimer) return;
                    self._viewportTimer = setTimeout(function() {
                        self._viewportTimer = null;
                        if (!self.overlay || !self.backgroundCanvas) return;
                        var behaelter = self.backgroundCanvas.parentElement;
                        if (behaelter.clientWidth !== self.cssWidth || behaelter.clientHeight !== self.cssHeight) {
                            self.resizeAllCanvases();
                        }
                    }, 100);
                };
                window.visualViewport.addEventListener('resize', this.boundHandlers.viewportResize);
            }
        },

        /**
         * Event-Handler entfernen
         */
        unbindEvents: function() {
            if (this.drawingCanvas && this.boundHandlers.pointerDown) {
                this.drawingCanvas.removeEventListener('pointerdown', this.boundHandlers.pointerDown);
                this.drawingCanvas.removeEventListener('pointermove', this.boundHandlers.pointerMove);
                this.drawingCanvas.removeEventListener('pointerup', this.boundHandlers.pointerUp);
                this.drawingCanvas.removeEventListener('pointerleave', this.boundHandlers.pointerLeave);
                this.drawingCanvas.removeEventListener('pointercancel', this.boundHandlers.pointerUp);
            }

            if (this.boundHandlers.keyDown) {
                document.removeEventListener('keydown', this.boundHandlers.keyDown);
            }

            if (this.overlay && this.boundHandlers.contextMenu) {
                this.overlay.removeEventListener('contextmenu', this.boundHandlers.contextMenu);
            }

            // iOS-Gestensperre (#7149) - Gegenstück zu bindEvents()
            if (this.canvasArea && this.boundHandlers.touchGeste) {
                this.canvasArea.removeEventListener('touchstart', this.boundHandlers.touchGeste, { passive: false });
                this.canvasArea.removeEventListener('touchmove', this.boundHandlers.touchGeste, { passive: false });
            }
            this.canvasArea = null;
            if (this.boundHandlers.selectStart) {
                document.removeEventListener('selectstart', this.boundHandlers.selectStart);
            }
            if (this.overlay && this.boundHandlers.doppelklick) {
                this.overlay.removeEventListener('dblclick', this.boundHandlers.doppelklick);
            }

            // visualViewport (#7149, AP-2.4)
            if (this.boundHandlers.viewportResize && window.visualViewport) {
                window.visualViewport.removeEventListener('resize', this.boundHandlers.viewportResize);
            }
            if (this._viewportTimer) {
                clearTimeout(this._viewportTimer);
                this._viewportTimer = null;
            }

            this.boundHandlers = {};
        },

        /**
         * Liegt das Ziel in einem Eingabefeld? Dort bleiben Markieren,
         * Lupe und Einfügen erlaubt (#7149). selectstart liefert in manchen
         * Browsern einen Textknoten als Ziel - dann dessen Elternelement.
         */
        _istEingabefeld: function(ziel) {
            if (ziel && ziel.nodeType === 3) {
                ziel = ziel.parentNode;
            }
            return !!(ziel && ziel.closest &&
                ziel.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""]'));
        },

        /** touchstart/touchmove am Canvas-Bereich (#7149, E6) */
        onTouchGeste: function(e) {
            if (this._istEingabefeld(e.target)) return;
            if (e.cancelable) {
                e.preventDefault();
            }
        },

        /** selectstart am document, solange die Tafel offen ist (#7149, E6) */
        onSelectStart: function(e) {
            if (this._istEingabefeld(e.target)) return;
            e.preventDefault();
        },

        /** dblclick im Overlay: kein Wort-Markieren/Doppeltipp-Zoom (#7149, E6) */
        onDoppelklick: function(e) {
            if (this._istEingabefeld(e.target)) return;
            e.preventDefault();
        },

        /**
         * Kontextmenü im Tafel-Overlay unterdrücken (Fehlermeldung #7063).
         *
         * AUSNAHME Eingabefelder: In input/textarea/select/[contenteditable]
         * bleibt das Menü erhalten (Einfügen in die Schieberegler-Nachbarn,
         * Dialoge). Das Textfeld des Text-Werkzeugs
         * (textarea.cbd-board-text-input) hängt openTextInput() an
         * document.body, NICHT ans Overlay – es erreicht diesen Listener gar
         * nicht erst und behält sein Menü ohnehin.
         */
        onContextMenu: function(e) {
            var ziel = e.target;
            if (ziel && ziel.closest && ziel.closest('input, textarea, select, [contenteditable]')) {
                return;
            }
            e.preventDefault();
        },

        // =============================================
        // Klassen-Selektor
        // =============================================

        showClassSelector: function(callback) {
            var self = this;

            var selectorOverlay = document.createElement('div');
            selectorOverlay.className = 'cbd-board-confirm-overlay';

            var optionsHtml = '<button class="cbd-class-option" data-class-id="0">' +
                '<span class="dashicons dashicons-admin-users"></span> Persönlich (lokal)' +
                '</button>';

            this.classes.forEach(function(cls) {
                optionsHtml += '<button class="cbd-class-option cbd-class-option-server" data-class-id="' + cls.id + '">' +
                    '<span class="dashicons dashicons-groups"></span> ' + self._escHtml(cls.name) +
                    '</button>';
            });

            selectorOverlay.innerHTML =
                '<div class="cbd-board-confirm-dialog cbd-class-selector-dialog">' +
                    '<h4>Klasse wählen</h4>' +
                    '<p>Wählen Sie, wo die Zeichnung gespeichert werden soll:</p>' +
                    '<div class="cbd-class-options">' + optionsHtml + '</div>' +
                    '<div class="cbd-board-confirm-actions">' +
                        '<button class="cbd-board-confirm-cancel">Abbrechen</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(selectorOverlay);

            // Class option buttons
            selectorOverlay.querySelectorAll('.cbd-class-option').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var classId = parseInt(this.getAttribute('data-class-id')) || null;
                    document.body.removeChild(selectorOverlay);
                    callback(classId === 0 ? null : classId);
                });
            });

            // Cancel
            selectorOverlay.querySelector('.cbd-board-confirm-cancel').addEventListener('click', function() {
                document.body.removeChild(selectorOverlay);
            });

            // Backdrop click = cancel
            selectorOverlay.addEventListener('click', function(e) {
                if (e.target === selectorOverlay) {
                    document.body.removeChild(selectorOverlay);
                }
            });
        },

        // =============================================
        // Zeichenwerkzeuge
        // =============================================

        setTool: function(tool) {
            // Lasso (AP-5.1): Wechsel zu einem anderen Werkzeug hebt die
            // Auswahl auf (auch eine halb gezogene Lassolinie)
            if (tool !== 'lasso') {
                this.auswahlAufheben();
            }

            this.currentTool = tool;

            // Set eraser mode
            if (tool === 'eraser-stroke') {
                this.eraserMode = 'stroke';
            } else if (tool === 'eraser-point') {
                this.eraserMode = 'point';
            }

            if (this.drawingCanvas) {
                if (tool === 'eraser-stroke' || tool === 'eraser-point') {
                    this.drawingCanvas.classList.add('eraser-active');
                } else {
                    this.drawingCanvas.classList.remove('eraser-active');
                }
                this.drawingCanvas.classList.toggle('cbd-board-lasso-aktiv', tool === 'lasso');
            }

            // Lade die gespeicherte Breite für dieses Tool
            this.loadToolWidth();

            // Aktualisiere UI-Elemente (Größen-Slider und Transparenz-Slider)
            this.updateToolUI();
        },

        setColor: function(color) {
            this.currentColor = color;
        },

        setLineWidth: function(width) {
            this.lineWidth = width;

            // Speichere die Breite für das aktuelle Tool
            this.saveToolWidth();
        },

        setHighlighterOpacity: function(opacity) {
            this.highlighterOpacity = opacity;
        },

        /**
         * Textgröße des Blockinhalts setzen
         */
        setFontSize: function(size) {
            this.fontSize = size;
            var contentArea = this.overlay.querySelector('.cbd-board-content');
            if (contentArea) {
                contentArea.style.fontSize = (size / 100) + 'em';
            }
        },

        /**
         * Breiten aller Werkzeuge und Textmarker-Transparenz auf Standard
         * (seit 3.1.142, Rückmeldung 2026-10-12). Bis 3.1.141 wurden sie in
         * localStorage['cbd-board-tool-settings'] gemerkt und beim Öffnen
         * geladen; gewünscht ist aber bei jedem Öffnen die
         * Standardeinstellung. Innerhalb einer Sitzung gilt die je Werkzeug
         * gewählte Breite weiter (Felder, saveToolWidth). Der alte Schlüssel
         * wird entfernt; die Ausschlusslisten der Notizzählung (oben und in
         * personal-notes-manager.js) behalten ihn für Altbestände.
         */
        _standardBreiten: function() {
            this.penWidth = 3;
            this.eraserStrokeWidth = 8;
            this.eraserPointWidth = 15;
            this.highlighterWidth = 20;
            this.highlighterOpacity = 0.3;
            try {
                localStorage.removeItem('cbd-board-tool-settings');
            } catch (e) {
                // ohne Speicher weiter
            }

            // Setze lineWidth auf die Breite des aktuellen Tools
            this.loadToolWidth();
        },

        /**
         * Lädt die Breite für das aktuelle Tool
         */
        loadToolWidth: function() {
            switch (this.currentTool) {
                case 'pen':
                    this.lineWidth = this.penWidth;
                    break;
                case 'eraser-stroke':
                    this.lineWidth = this.eraserStrokeWidth;
                    break;
                case 'eraser-point':
                    this.lineWidth = this.eraserPointWidth;
                    break;
                case 'highlighter':
                    this.lineWidth = this.highlighterWidth;
                    break;
                case 'lasso':
                    // AP-5.1: Das Lasso hat keine Breite - lineWidth bleibt,
                    // wie sie ist (Text-Werkzeug und Rückkehr unverändert)
                    break;
                default:
                    this.lineWidth = this.penWidth;
            }
        },

        /**
         * Speichert die aktuelle lineWidth für das aktuelle Tool
         */
        saveToolWidth: function() {
            switch (this.currentTool) {
                case 'pen':
                    this.penWidth = this.lineWidth;
                    break;
                case 'eraser-stroke':
                    this.eraserStrokeWidth = this.lineWidth;
                    break;
                case 'eraser-point':
                    this.eraserPointWidth = this.lineWidth;
                    break;
                case 'highlighter':
                    this.highlighterWidth = this.lineWidth;
                    break;
            }
        },

        /**
         * Aktualisiert die UI-Elemente (Slider) basierend auf dem aktuellen Tool
         */
        updateToolUI: function() {
            if (!this.overlay) return;

            // Größen-Slider aktualisieren (Lasso, AP-5.1: ausgeblendet)
            var ohneBreite = this.currentTool === 'lasso';
            var widthInput = this.overlay.querySelector('.cbd-board-width');
            var widthDisplay = this.overlay.querySelector('.cbd-board-width-display');
            if (widthInput) {
                widthInput.value = this.lineWidth;
                widthInput.style.display = ohneBreite ? 'none' : '';
                if (widthDisplay) {
                    widthDisplay.textContent = this.lineWidth + 'px';
                    widthDisplay.style.display = ohneBreite ? 'none' : '';
                }
            }

            // Transparenz-Slider nur für Textmarker anzeigen
            var opacityControl = this.overlay.querySelector('.cbd-board-opacity-control');
            if (opacityControl) {
                if (this.currentTool === 'highlighter') {
                    opacityControl.style.display = 'flex';
                    var opacityInput = this.overlay.querySelector('.cbd-board-opacity');
                    var opacityDisplay = this.overlay.querySelector('.cbd-board-opacity-display');
                    if (opacityInput) {
                        opacityInput.value = Math.round(this.highlighterOpacity * 100);
                        if (opacityDisplay) {
                            opacityDisplay.textContent = Math.round(this.highlighterOpacity * 100) + '%';
                        }
                    }
                } else {
                    opacityControl.style.display = 'none';
                }
            }
        },

        /**
         * Drawing Canvas loeschen
         */
        clearCanvas: function() {
            if (!this.drawingCtx || !this.drawingCanvas) return;

            // "Zeichnung löschen" ist rückgängig machbar (E12): Stand merken,
            // dann alle Elemente entfernen. Eine schon leere Tafel legt keinen
            // Undo-Schritt an.
            this.auswahlAufheben(); // AP-5.1: gewählte Elemente gibt es nicht mehr
            if (this.modell) {
                if (this.modell.elemente.length) {
                    this.merkeZustand();
                }
                this.modell.elemente = [];
            }
            this.currentStroke = null;
            this.zeichneModell();
            this.drawingCtx.clearRect(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);

            // Aus localStorage entfernen
            this.removeFromCache();
        },

        /**
         * Text-Werkzeug: Eingabefeld an der Klickposition öffnen.
         * Übernommener Text wird als Text-Element abgelegt (siehe commit()
         * unten) und dadurch automatisch gespeichert/rückgängig machbar.
         */
        openTextInput: function(clientX, clientY) {
            var self = this;
            var canvasX = this.lastX;
            var canvasY = this.lastY;
            var committed = false;

            // Falls bereits ein Eingabefeld offen ist: zuerst entfernen. Das
            // loest synchron ein 'blur' auf dem alten Feld aus, dessen
            // eigener Handler seinen Text uebernimmt (commit()) und sich
            // dabei SELBST aus dem DOM entfernt (cleanup()) - der
            // nachfolgende removeChild-Aufruf hier traefe dann auf einen
            // bereits entfernten Knoten. AP-1.fix1 (Befund B2 aus
            // AP-1.rev): try/catch faengt genau diesen Fall ab, das
            // Ergebnis (kein offenes altes Feld mehr) ist in beiden Faellen
            // dasselbe.
            var existing = document.querySelector('.cbd-board-text-input');
            if (existing && existing.parentNode) {
                try {
                    existing.parentNode.removeChild(existing);
                } catch (removeErr) {
                    // bereits per eigenem blur-Handler entfernt, siehe oben
                }
            }

            var textarea = document.createElement('textarea');
            textarea.className = 'cbd-board-text-input';
            textarea.style.left = clientX + 'px';
            textarea.style.top = clientY + 'px';

            function cleanup() {
                if (textarea.parentNode) {
                    textarea.parentNode.removeChild(textarea);
                }
            }

            function commit() {
                if (committed) return;
                committed = true;

                var text = textarea.value.replace(/^\s+|\s+$/g, '');
                cleanup();
                if (!text) return;

                // Tafel inzwischen geschlossen: nichts mehr ablegen
                if (!self.modell) return;

                // Als Text-Element ablegen (AP-4.3) - macht Undo, Speichern
                // und Seitenwechsel automatisch korrekt, weil zeichneModell()
                // ausschliesslich aus dem Modell neu aufbaut (dortiger Pass 4).
                var element = self._elementAusStroke({
                    tool: 'text',
                    text: text,
                    color: self.currentColor,
                    width: self.lineWidth,
                    points: [{x: canvasX, y: canvasY}]
                });
                if (!element) return;
                self.merkeZustand();
                self.modell.elemente.push(element);
                self.zeichneModell();
            }

            function cancel() {
                if (committed) return;
                committed = true;
                cleanup();
            }

            textarea.addEventListener('keydown', function(e) {
                // Nie an den dokumentweiten Tastatur-Handler des Tafelmodus
                // durchreichen (u. a. schliesst dessen Escape-Zweig sonst die
                // GESAMTE Tafel statt nur dieses Eingabefeld, siehe onKeyDown()).
                e.stopPropagation();

                if (e.key === 'Escape') {
                    e.preventDefault();
                    cancel();
                } else if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    commit();
                }
            });

            textarea.addEventListener('blur', function() {
                commit();
            });

            document.body.appendChild(textarea);
            textarea.focus();
        },

        showClearConfirm: function() {
            var self = this;

            var confirmOverlay = document.createElement('div');
            confirmOverlay.className = 'cbd-board-confirm-overlay';
            confirmOverlay.innerHTML =
                '<div class="cbd-board-confirm-dialog">' +
                    '<h4>Zeichnung löschen?</h4>' +
                    '<p>Die gesamte Zeichnung wird unwiderruflich gelöscht.</p>' +
                    '<div class="cbd-board-confirm-actions">' +
                        '<button class="cbd-board-confirm-cancel">Abbrechen</button>' +
                        '<button class="cbd-board-confirm-delete">Löschen</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(confirmOverlay);

            confirmOverlay.querySelector('.cbd-board-confirm-cancel').addEventListener('click', function() {
                document.body.removeChild(confirmOverlay);
            });

            confirmOverlay.querySelector('.cbd-board-confirm-delete').addEventListener('click', function() {
                self.clearCanvas();
                document.body.removeChild(confirmOverlay);
            });

            confirmOverlay.addEventListener('click', function(e) {
                if (e.target === confirmOverlay) {
                    document.body.removeChild(confirmOverlay);
                }
            });
        },

        // =============================================
        // Canvas Event-Handler
        // =============================================

        /*
         * ============================================================
         * Zeigerverwaltung (#7149) - bitte nicht vereinfachen
         * ============================================================
         * Anlass: Auf dem iPad (Apple Pencil, Hand liegt auf) riss der Strich
         * ab, der Handballen mischte Punkte in den Stiftstrich (Zickzack) oder
         * beendete ihn beim Abheben. Ursache war ein gemeinsames
         * activePointers-Set für ALLE Zeiger: Ballen + Stift = 2 -> Pinch +
         * isDrawing=false, und pointermove/pointerup prüften keine pointerId.
         *
         * Regeln (PLAN-Rueckmeldungen-Blockverweis-iPad-Tafel-Darkmode-Formeln.md, E4/E5):
         * 1. Genau EIN zeichnender Zeiger: drawingPointerId wird beim Start
         *    eines Strichs/Radiervorgangs gesetzt. Nur DESSEN pointermove/
         *    pointerup/pointercancel wirken auf den Strich; alle anderen
         *    Zeiger werden für das Zeichnen ignoriert.
         * 2. Stift-Vorrang: Sobald in dieser Tafelsitzung einmal
         *    pointerType 'pen' auftrat (stiftGesehen), startet 'touch' weder
         *    Strich noch Radierer noch Textfeld - nur noch Zwei-Finger-Zoom.
         *    Gilt bis zum Schließen (destroy/_openOverlay setzen zurück).
         *    Ohne Stift zeichnet ein Finger wie bisher (Smartboards).
         * 3. Ein Stift übernimmt einen laufenden FINGER-Strich (Ballen setzte
         *    vor dem ersten Stiftkontakt auf): der Finger-Strich wird verworfen.
         * 4. Pinch nur aus Touch-Zeigern (_pinchPointers), Stift-IDs kommen nie
         *    hinein. Pinch startet nicht, solange Stift oder Maus zeichnen;
         *    zeichnet ein Finger, bricht der zweite Finger dessen Strich ab
         *    (bisheriges Verhalten) und startet den Zoom.
         * 5. Nur e.button === 0 (linke Maustaste, Stiftspitze, Finger) startet
         *    etwas - Rechts-/Mittelklick zeichnen keinen Punkt mehr.
         * 6. pointerleave beendet einen Strich NICHT, solange der Canvas die
         *    Zeiger-Erfassung (setPointerCapture) für diesen Zeiger hält.
         */
        onPointerDown: function(e) {
            var istTouch = e.pointerType === 'touch';
            var istStift = e.pointerType === 'pen';

            // Buchführung aller aufliegenden Zeiger
            this.activePointers.add(e.pointerId);

            if (istStift) {
                this.stiftGesehen = true;
            }

            if (istTouch) {
                // Pinch-Tracking: nur Touch-Zeiger (Regel 4)
                this._pinchPointers[e.pointerId] = {x: e.clientX, y: e.clientY};
                var anzahlTouch = Object.keys(this._pinchPointers).length;

                if (anzahlTouch >= 2) {
                    // Zwei Finger: Zoom - aber nicht, solange Stift/Maus zeichnen
                    if (this.drawingPointerId === null || this.drawingPointerType === 'touch') {
                        if (this.drawingPointerType === 'touch') {
                            this._strichVerwerfen(); // Finger-Strich abbrechen (wie bisher)
                        }
                        if (anzahlTouch === 2) {
                            this._startPinch();
                        }
                    }
                    return; // Nicht zeichnen
                }

                // Stift-Vorrang (Regel 2)
                if (this.stiftGesehen) {
                    return;
                }

                // Es zeichnet schon jemand (z. B. die Maus)
                if (this.drawingPointerId !== null) {
                    return;
                }

                // Große Kontaktfläche deutet auf Handballen hin (ohne Stift)
                if (e.width > 25 || e.height > 25) {
                    return; // Wahrscheinlich Handballen
                }
            } else {
                // Maus/Stift: nur linke Taste bzw. Stiftspitze (Regel 5)
                if (e.button !== 0) {
                    return;
                }

                if (this.drawingPointerId !== null) {
                    if (istStift && this.drawingPointerType === 'touch') {
                        // Regel 3: Stift übernimmt den laufenden Finger-Strich
                        this._strichVerwerfen();
                    } else {
                        return; // Regel 1: es gibt schon einen zeichnenden Zeiger
                    }
                }

                // Ein aufsetzender Stift beendet einen laufenden Zwei-Finger-Zoom
                if (istStift) {
                    this._pinchStartDist = null;
                }
            }

            var rect = this.drawingCanvas.getBoundingClientRect();
            this.lastX = (e.clientX - rect.left) / this.zoom;
            this.lastY = (e.clientY - rect.top) / this.zoom;

            // Lasso (AP-5.1): Handballen-, Pinch-, Stift-Vorrang- und
            // Tastenregeln oben gelten wie beim Stift. Neue Lassolinie
            // beginnen; eine bestehende Auswahl wird dabei aufgehoben.
            // AP-5.2: Liegt der Punkt IN der Auswahl (auswahlRahmen(), also
            // Vereinigungsrahmen + 8 px), beginnt stattdessen das Verschieben.
            // 3.1.143: Im Drehmodus setzt das erste Aufsetzen den Drehpunkt,
            // das zweite den Bezugspunkt und dreht beim Ziehen - überall auf
            // der Fläche, nicht nur im Auswahlrahmen.
            if (this.currentTool === 'lasso') {
                if (this._drehen) {
                    this._drehenAufsetzen(this.lastX, this.lastY);
                } else if (this._imAuswahlRahmen(this.lastX, this.lastY)) {
                    this._ziehenBeginnen(this.lastX, this.lastY);
                } else {
                    this._lassoBeginnen(this.lastX, this.lastY);
                }
                this.isDrawing = true;
                this.drawingPointerId = e.pointerId;
                this.drawingPointerType = e.pointerType;
                try {
                    this.drawingCanvas.setPointerCapture(e.pointerId);
                } catch (captureErr) {
                    // ohne Pointer-Capture weiter (siehe unten)
                }
                return;
            }

            // Text-Werkzeug: Eingabefeld an der Klickposition öffnen statt zu zeichnen
            if (this.currentTool === 'text') {
                // AP-1.fix1 (Befund B1 aus AP-1.rev): Ohne preventDefault()
                // verarbeitet der Browser nach diesem pointerdown weiterhin
                // seine eigene Standardaktion (u. a. Fokuswechsel Richtung
                // Canvas) - das zog den Fokus dem gerade erst geöffneten
                // Eingabefeld sofort wieder weg, dessen blur-Handler committete
                // daraufhin einen leeren Text und entfernte das Feld, bevor der
                // Nutzer auch nur ein Zeichen tippen konnte. Nur bei ECHTER
                // Eingabe reproduzierbar, nicht bei synthetisch dispatchten
                // PointerEvents (die haben keine Standardaktion) - deshalb in
                // AP-1.1/AP-1.2 unentdeckt geblieben.
                e.preventDefault();
                this.openTextInput(e.clientX, e.clientY);
                return; // kein Zeichen-Strich beginnen
            }

            // Punkt-Radierer (vektoriell, E11): Radiergeste beginnen und den
            // ersten Punkt radieren
            if (this.currentTool === 'eraser-point') {
                this._radierGesteBeginnen(this.lastX, this.lastY);
                this._punktRadieren([{x: this.lastX, y: this.lastY}]);
                // KEIN return - isDrawing wird aktiviert für kontinuierliches Radieren
            }

            // Strich-Radierer: Prüfe ob ein Strich getroffen wurde
            if (this.currentTool === 'eraser-stroke') {
                this._gesteGemerkt = false; // neue Geste: höchstens EIN Undo-Schritt
                var deletedAny = this.eraseStrokeAtPoint(this.lastX, this.lastY);
                if (deletedAny) {
                    return; // Strich wurde gelöscht, kein isDrawing nötig
                }
            }

            this.isDrawing = true;

            // Zeigerverwaltung (#7149), Regel 1: dieser Zeiger führt den Strich
            this.drawingPointerId = e.pointerId;
            this.drawingPointerType = e.pointerType;

            // Neuen Strich beginnen (für Strich-basiertes Radieren)
            // Radierer werden nicht als Strich gespeichert
            if (this.currentTool !== 'eraser-stroke' && this.currentTool !== 'eraser-point') {
                this.currentStroke = {
                    tool: this.currentTool,
                    color: this.currentTool === 'highlighter'
                        ? this.getLighterHighlightColor(this.currentColor)
                        : this.currentColor,
                    width: this.lineWidth,
                    points: [{x: this.lastX, y: this.lastY}]
                };
            }

            // Canvas mit korrekter Ebenen-Reihenfolge neu zeichnen
            // (Textmarker unten, normale Striche oben)
            if (this.currentTool !== 'eraser-point' && this.currentTool !== 'eraser-stroke') {
                this.zeichneModell();
            }

            // Pointer capture - wirft u. a. bei synthetischen Zeigern ohne
            // aktiven Kontakt (Prüfseite tools/test-board-zeiger.html) oder
            // wenn der Zeiger schon wieder abgehoben hat; der Strich läuft
            // dann eben ohne Erfassung weiter.
            try {
                this.drawingCanvas.setPointerCapture(e.pointerId);
            } catch (captureErr) {
                // ohne Pointer-Capture weiter
            }
        },

        /**
         * Laufenden Strich/Radiervorgang verwerfen, ohne ihn ins Modell zu
         * übernehmen (Zeigerverwaltung #7149, Regeln 3 und 4). Ein Strich legt
         * erst beim Übernehmen (onPointerUp) seinen Undo-Schritt an - ein
         * verworfener Strich hinterlässt also keinen leeren Undo-Schritt.
         * Eine schon erfolgte Punkt-Radierung bleibt (wie früher die
         * Pixel-Radierung) und wird abgeschlossen.
         */
        _strichVerwerfen: function() {
            var hatteStrich = !!this.currentStroke;
            // Lasso (AP-5.1): laufende Lassolinie verwerfen, nichts wählen
            if (this._lasso) {
                this._lasso = null;
                this._auswahlZeichnen();
            }
            // AP-5.2: laufendes Verschieben verwerfen (zweiter Finger = Zoom,
            // Stift übernimmt den Finger) - Elemente zurück an den Start,
            // Undo-Schritt wieder weg, Auswahl bleibt
            if (this._ziehen) {
                this._ziehenBeenden(false);
            }
            // 3.1.143: laufende Drehung verwerfen - Elemente zurück, kein
            // Undo-Rest; Drehpunkt und Drehmodus bleiben
            if (this._drehen) {
                this._drehenVerwerfen();
            }
            this.isDrawing = false;
            this.currentStroke = null;
            this.drawingPointerId = null;
            this.drawingPointerType = null;
            if (this.drawingCtx) {
                this.drawingCtx.globalCompositeOperation = 'source-over';
                if (this._radierBilder) {
                    this._radierGesteAbschliessen();
                } else if (hatteStrich) {
                    this.zeichneModell(); // Teilstrich wieder von der Fläche nehmen
                }
            }
        },

        /**
         * pointerleave: beendet einen Strich nur, wenn der Canvas den Zeiger
         * NICHT erfasst hat (Zeigerverwaltung #7149, Regel 6). Mit Erfassung
         * kommt pointerup ohnehin am Canvas an.
         */
        onPointerLeave: function(e) {
            if (e.pointerId === this.drawingPointerId && this.drawingCanvas &&
                typeof this.drawingCanvas.hasPointerCapture === 'function') {
                var erfasst = false;
                try {
                    erfasst = this.drawingCanvas.hasPointerCapture(e.pointerId);
                } catch (err) {
                    erfasst = false;
                }
                if (erfasst) {
                    return;
                }
            }
            this.onPointerUp(e);
        },

        onPointerMove: function(e) {
            // Pinch-Zoom: Touch-Pointer-Position aktualisieren
            if (e.pointerType === 'touch' && this._pinchPointers[e.pointerId] !== undefined) {
                this._pinchPointers[e.pointerId] = {x: e.clientX, y: e.clientY};
                // Bei aktivem Pinch (2 Finger): Zoom verarbeiten statt zeichnen
                if (Object.keys(this._pinchPointers).length >= 2 && this._pinchStartDist !== null) {
                    this._handlePinch();
                    return;
                }
            }

            if (!this.isDrawing) return;

            // Zeigerverwaltung (#7149), Regel 1: nur der zeichnende Zeiger
            if (e.pointerId !== this.drawingPointerId) return;

            var rect = this.drawingCanvas.getBoundingClientRect();
            var x = (e.clientX - rect.left) / this.zoom;
            var y = (e.clientY - rect.top) / this.zoom;

            // Lasso (AP-5.1): Punkte sammeln (inkl. zusammengefasster
            // Zwischenpunkte) und die Linie auf der Auswahl-Ebene zeigen.
            // Ohne _lasso (per ESC/Werkzeugwechsel verworfen) nichts tun.
            if (this.currentTool === 'lasso') {
                // AP-5.2: Verschieben - Gesamtverschiebung seit dem Start,
                // angewandt im nächsten Frame (_ziehenVormerken)
                if (this._ziehen) {
                    this._ziehenVormerken(x, y);
                } else if (this._drehen) {
                    this._drehenBewegen(x, y, !!e.shiftKey); // 3.1.143
                } else if (this._lasso) {
                    var lp = this._punkteAusEreignis(e, rect);
                    for (var li = 0; li < lp.length; li++) {
                        this._lasso.push([lp[li].x, lp[li].y]);
                    }
                    this._auswahlZeichnen();
                }
                this.lastX = x;
                this.lastY = y;
                return;
            }

            // Strich-Radierer: Prüfe kontinuierlich ob ein Strich getroffen wird
            // (nur am letzten Punkt wie bisher - jeder Test ist O(Punkte aller Striche))
            if (this.currentTool === 'eraser-stroke') {
                this.eraseStrokeAtPoint(x, y);
                this.lastX = x;
                this.lastY = y;
                return;
            }

            // Zwischenpunkte, die der Browser seit dem letzten Ereignis
            // zusammengefasst hat (Apple Pencil: 120-240 Hz, Bildwiederholung
            // nur 60-120 Hz). Ohne sie wird der Strich bei schnellen Bögen eckig.
            var punkte = this._punkteAusEreignis(e, rect);

            // Punkt-Radierer (vektoriell, E11): an jeder Position radieren,
            // bei schnellen Bewegungen mit Zwischenpunkten (sonst Lücken)
            if (this.currentTool === 'eraser-point') {
                this._punktRadieren(this._radierZwischenpunkte(punkte));
                this.lastX = x;
                this.lastY = y;
                return;
            }

            // Punkte zum aktuellen Strich hinzufügen und NUR das neue Stück
            // zeichnen (#7149, E8) - siehe _segmentZeichnen().
            if (this.currentStroke) {
                var pts = this.currentStroke.points;
                var von = pts.length ? pts[pts.length - 1] : {x: x, y: y};
                for (var p = 0; p < punkte.length; p++) {
                    pts.push(punkte[p]);
                }
                this._segmentZeichnen(this.currentStroke, von, punkte);
            }

            this.lastX = x;
            this.lastY = y;
        },

        /**
         * Canvas-Koordinaten (CSS-Pixel, Zoom herausgerechnet) aller Punkte
         * eines pointermove - inkl. getCoalescedEvents(), falls vorhanden.
         * Gleiche Umrechnung wie onPointerDown/onPointerMove: rect ist die
         * bereits transformierte (Zoom/Pan) Canvas-Box.
         */
        _punkteAusEreignis: function(e, rect) {
            var liste = null;
            if (typeof e.getCoalescedEvents === 'function') {
                try {
                    liste = e.getCoalescedEvents();
                } catch (err) {
                    liste = null;
                }
            }
            if (!liste || !liste.length) {
                liste = [e];
            }
            var punkte = [];
            for (var i = 0; i < liste.length; i++) {
                punkte.push({
                    x: (liste[i].clientX - rect.left) / this.zoom,
                    y: (liste[i].clientY - rect.top) / this.zoom
                });
            }
            return punkte;
        },

        /**
         * Inkrementelles Zeichnen (#7149, E8) - bitte nicht durch
         * redrawAllStrokes() je pointermove ersetzen: das zeichnete bei jedem
         * Ereignis ALLE Striche neu (O(n)) und machte das Schreiben auf dem
         * iPad mit vielen Strichen zäh.
         *
         * Zeichnet nur das neue Stück (von -> neue Punkte) des laufenden
         * Strichs mit denselben Linienparametern wie redrawAllStrokes().
         * Kontext-Transformation (DPR-scale) ist dieselbe; Zoom/Pan wirken
         * per CSS-Transform am Container und sind in den Punkten schon
         * herausgerechnet.
         *
         * Stift: 'source-over' obenauf - während des Strichs liegt er damit
         * auch über Text-Einträgen; das pointerup-Neuzeichnen stellt die
         * Ebenen-Reihenfolge (Textmarker < Stift < Text) wieder her.
         *
         * Textmarker (Entscheidung AP-2.3): 'destination-over', also UNTER
         * alles bereits Gezeichnete. Mit 'lighten' obenauf (wie im
         * Neuzeichnen-Pass 1) würde der helle Marker darunterliegende
         * Stiftstriche während des Zeichnens überdecken, weil er dort zuletzt
         * käme. destination-over hält Stift- und Textstriche sichtbar; da der
         * Marker deckend ist (getLighterHighlightColor: Alpha 1.0), sieht
         * die Überlappung mit sich selbst gleich aus. Einziger Unterschied
         * bis zum pointerup: Überlappt er einen ANDEREN Textmarker, liegt der
         * neue darunter statt per lighten gemischt. Das pointerup-Neuzeichnen
         * stellt das bisherige Endbild exakt her.
         *
         * Seit #7152 liegt der Marker auch im Neuzeichnen UNTER dem Basis-Bild
         * (redrawAllStrokes: Textmarker -> Basis-Bild -> Stift -> Text), so dass
         * Zwischenstand und Endbild über alter Schrift übereinstimmen. Seit
         * AP-4.3 (Elementmodell) heißt das Neuzeichnen zeichneModell() und die
         * Basis-Bilder sind Bild-Elemente - Reihenfolge unverändert.
         */
        _segmentZeichnen: function(stroke, von, punkte) {
            if (!this.drawingCtx || !punkte.length) return;
            var ctx = this.drawingCtx;
            ctx.lineWidth = stroke.width;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = stroke.color;
            ctx.globalCompositeOperation = stroke.tool === 'highlighter' ? 'destination-over' : 'source-over';
            ctx.beginPath();
            ctx.moveTo(von.x, von.y);
            for (var i = 0; i < punkte.length; i++) {
                ctx.lineTo(punkte[i].x, punkte[i].y);
            }
            ctx.stroke();
            ctx.globalCompositeOperation = 'source-over';
        },

        onPointerUp: function(e) {
            // Pointer aus der Liste entfernen (für Palm Rejection)
            this.activePointers.delete(e.pointerId);

            // Pinch-Tracking: Pointer entfernen, Pinch beenden wenn < 2 Finger
            delete this._pinchPointers[e.pointerId];
            if (Object.keys(this._pinchPointers).length < 2) {
                this._pinchStartDist = null;
            }

            // Zeigerverwaltung (#7149), Regel 1: nur der zeichnende Zeiger
            // beendet den Strich - ein abhebender Handballen nicht.
            if (this.isDrawing && e.pointerId === this.drawingPointerId) {
                this.isDrawing = false;
                this.drawingPointerId = null;
                this.drawingPointerType = null;
                this.drawingCtx.globalCompositeOperation = 'source-over';

                // Lasso (AP-5.1): Polygon schließen und auswählen
                if (this._lasso) {
                    this._lassoAbschliessen();
                }

                // AP-5.2: Verschieben abschließen (Endlage übernehmen; 0 px
                // nimmt den Undo-Schritt wieder weg)
                if (this._ziehen) {
                    this._ziehenBeenden(true);
                }

                // 3.1.143: Drehpunkt festlegen bzw. Drehung übernehmen
                if (this._drehen) {
                    this._drehenAbheben();
                }

                // Aktuellen Strich als Element übernehmen (außer bei Radierer).
                // Undo-Schritt (E12) erst hier: der Stand VOR dem Strich ist
                // genau der jetzige Modellstand (der Strich lebt bis hierher
                // nur in currentStroke), und ein verworfener Strich legt so
                // keinen leeren Schritt an.
                if (this.currentStroke && this.currentTool !== 'eraser-stroke') {
                    var element = this.modell ? this._elementAusStroke(this.currentStroke) : null;
                    this.currentStroke = null;
                    if (element) {
                        this.merkeZustand();
                        this.modell.elemente.push(element);
                    }
                    // #7149 (E8): während des Strichs wurde nur inkrementell
                    // gezeichnet - jetzt EINMAL alles neu, damit Ebenen-
                    // Reihenfolge und Endbild exakt dem bisherigen Verfahren
                    // (redrawAllStrokes je pointermove) entsprechen.
                    this.zeichneModell();
                }

                // Punkt-Radiergeste abschließen (Bild-Elemente übernehmen,
                // exakt neu zeichnen)
                if (this._radierBilder) {
                    this._radierGesteAbschliessen();
                }
            }
        },

        /**
         * Strich-Radierer: Prüft ob ein Element an Position (x,y) ist und löscht es.
         * Striche per Punktabstand, Texte über ihren Rahmen (wie bisher),
         * Bild-Elemente NIE (E10: sonst löschte eine Berührung die ganze alte
         * Tafel). Höchstens ein Element je Aufruf; der erste Treffer einer
         * Geste legt den Undo-Schritt an (_merkeEinmal).
         */
        eraseStrokeAtPoint: function(x, y) {
            if (!this.modell) return false;
            var eraserRadius = this.lineWidth * 2; // Größerer Radius für einfacheres Treffen
            var elemente = this.modell.elemente;
            var treffer = -1;

            // Durch alle Elemente iterieren (rückwärts: oberstes zuerst)
            for (var i = elemente.length - 1; i >= 0 && treffer < 0; i--) {
                var el = elemente[i];

                if (el.typ === 'bild') continue; // E10: Strich-Radierer ignoriert Bilder

                // Text-Elemente haben nur einen Anker-Punkt (Einfügeposition),
                // nicht den tatsächlich gerenderten Textbereich - ein reiner
                // Punkt-Abstands-Test würde den Radierer nur nahe der linken
                // oberen Ecke treffen lassen (AP-1.rev, Befund B3). Deshalb
                // hier stattdessen ein Rechtecktest über die tatsächliche
                // Ausdehnung des gerenderten Texts (rahmen() mit Messfunktion).
                if (el.typ === 'text') {
                    var r = this._rahmen(el);
                    if (x >= r.x - eraserRadius && x <= r.x + r.b + eraserRadius &&
                        y >= r.y - eraserRadius && y <= r.y + r.h + eraserRadius) {
                        treffer = i;
                    }
                    continue;
                }

                // Prüfe ob Radierer den Linienzug des Strichs berührt. Abstand
                // zum SEGMENT, nicht nur zu den Stützpunkten (bis 3.1.138):
                // schnell gezogene Linien und die Teilstücke des Punkt-
                // Radierers haben lange Segmente ohne Zwischenpunkte, die
                // sonst in der Mitte nicht zu treffen wären. Schwelle wie
                // bisher: Radierradius + Strichbreite.
                if (this._abstandZumLinienzug(x, y, el.punkte) < eraserRadius + el.breite) {
                    treffer = i; // Strich getroffen
                }
            }

            if (treffer < 0) return false;

            this._merkeEinmal();
            this.modell.elemente.splice(treffer, 1);
            this.zeichneModell();
            return true;
        },

        // =============================================
        // Punkt-Radierer, vektoriell (Architektur E11)
        // =============================================

        /**
         * Beginn einer Punkt-Radiergeste (pointerdown). Der Undo-Schritt wird
         * erst beim ersten tatsächlichen Treffer angelegt (_merkeEinmal) -
         * eine Geste ins Leere hinterlässt keinen leeren Schritt.
         */
        _radierGesteBeginnen: function(x, y) {
            this._gesteGemerkt = false;
            this._radierBilder = {};
            this._radierLetzter = {x: x, y: y};
            this._radierGeaendert = false;
        },

        /**
         * Zwischenpunkte von der letzten Radierposition zu den neuen Punkten
         * im Abstand von höchstens r/2 (r = Radierradius). Ohne sie bleiben bei
         * schnellen Bewegungen Striche zwischen zwei Ereignissen stehen.
         * r/2 statt r: Kreise im Abstand r überdecken nur einen Streifen der
         * halben Breite r*0,87, bei r/2 sind es r*0,97.
         */
        _radierZwischenpunkte: function(punkte) {
            var r = this.lineWidth * 3;
            var schritt = Math.max(1, r / 2);
            var liste = [];
            var von = this._radierLetzter || punkte[0];
            for (var i = 0; i < punkte.length; i++) {
                var p = punkte[i];
                var dx = p.x - von.x;
                var dy = p.y - von.y;
                var n = Math.ceil(Math.sqrt(dx * dx + dy * dy) / schritt);
                for (var k = 1; k <= n; k++) {
                    liste.push({x: von.x + dx * k / n, y: von.y + dy * k / n});
                }
                von = p;
            }
            this._radierLetzter = {x: von.x, y: von.y};
            return liste;
        },

        /**
         * Radiert an allen übergebenen Punkten (Kreis mit r = lineWidth * 3):
         * - Strich-Elemente: CBDBoardElemente.radiereKreis() schneidet den
         *   Teil heraus, Striche zerfallen in Teilstriche mit neuen IDs. Der
         *   Kreis wird um die halbe Strichbreite erweitert, weil radiereKreis
         *   nur die Mittellinie kennt - so endet die runde Kappe des Reststücks
         *   genau am Radierkreis (wie früher die Pixel-Radierung).
         * - Text-Elemente, deren Rahmen den Punkt enthält, werden gelöscht.
         * - Bild-Elemente, deren Rahmen den Kreis schneidet: Kreis im
         *   Offscreen-Canvas des Bildes ausradieren (destination-out); das
         *   Element bekommt seine neuen daten erst beim Abschluss der Geste.
         * Danach wird nur das Nötige gezeichnet: ohne Text in der Nähe genügt
         * ein destination-out-Kreis auf der Zeichenebene (billig, wie früher),
         * sonst wird alles neu gezeichnet. Exakt neu gezeichnet wird ohnehin
         * beim Abschluss der Geste.
         */
        _punktRadieren: function(punkte) {
            if (!this.modell || !punkte.length) return;
            var E = elementeModul();
            var self = this;
            var r = this.lineWidth * 3;
            var neueId = function() { return self.neueId(); };
            var alt = this.modell.elemente;
            var neu = [];
            var geaendert = false;
            var bildGeaendert = false;
            var textWeg = false;
            var i, k, p;

            for (i = 0; i < alt.length; i++) {
                var el = alt[i];

                if (el.typ === 'strich') {
                    var rand = r + el.breite / 2;
                    var rb = this._rahmen(el);
                    var teile = [el];
                    for (k = 0; k < punkte.length && teile.length; k++) {
                        p = punkte[k];
                        // schneller Ausschluss über den Rahmen des Originals
                        if (!this._kreisSchneidetRahmen(p.x, p.y, rand, rb)) continue;
                        var naechste = [];
                        for (var t = 0; t < teile.length; t++) {
                            var stuecke = E.radiereKreis(teile[t], p.x, p.y, rand, neueId);
                            for (var s = 0; s < stuecke.length; s++) naechste.push(stuecke[s]);
                        }
                        teile = naechste;
                    }
                    if (teile.length !== 1 || teile[0] !== el) geaendert = true;
                    for (k = 0; k < teile.length; k++) neu.push(teile[k]);
                    continue;
                }

                if (el.typ === 'text') {
                    var rt = this._rahmen(el);
                    var weg = false;
                    for (k = 0; k < punkte.length; k++) {
                        p = punkte[k];
                        if (p.x >= rt.x && p.x <= rt.x + rt.b && p.y >= rt.y && p.y <= rt.y + rt.h) {
                            weg = true;
                            break;
                        }
                    }
                    if (weg) {
                        geaendert = true;
                        textWeg = true;
                    } else {
                        neu.push(el);
                    }
                    continue;
                }

                if (el.typ === 'bild') {
                    // In Bildkoordinaten prüfen und radieren (3.1.143:
                    // gedrehte Bilder, _bildLokal)
                    var bildFlaeche = {x: 0, y: 0, b: el.b, h: el.h};
                    for (k = 0; k < punkte.length; k++) {
                        var lp = this._bildLokal(el, punkte[k].x, punkte[k].y);
                        if (this._kreisSchneidetRahmen(lp.x, lp.y, r, bildFlaeche) && this._bildRadieren(el, lp.x, lp.y, r)) {
                            bildGeaendert = true;
                        }
                    }
                }
                neu.push(el);
            }

            if (!geaendert && !bildGeaendert) return;

            this._merkeEinmal(); // Stand VOR der Änderung (alt ist unverändert)
            if (geaendert) {
                this.modell.elemente = neu;
            }
            this._radierGeaendert = true;

            if (textWeg || this._textInKreisNaehe(punkte, r)) {
                this.zeichneModell();
                return;
            }
            var ctx = this.drawingCtx;
            ctx.globalCompositeOperation = 'destination-out';
            ctx.fillStyle = 'rgba(0,0,0,1)';
            for (k = 0; k < punkte.length; k++) {
                ctx.beginPath();
                ctx.arc(punkte[k].x, punkte[k].y, r, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.globalCompositeOperation = 'source-over';
        },

        /**
         * Kreis im Offscreen-Canvas eines Bild-Elements ausradieren. Der
         * Canvas hat die Auflösung der Bildquelle (bei HiDPI das Vielfache
         * der CSS-Maße b×h), damit das Bild durch das Radieren nicht
         * unscharf wird; die Position wird umgerechnet. (x, y) sind
         * BILDkoordinaten (CSS-Pixel ab der linken oberen Ecke, Drehung schon
         * herausgerechnet - _bildLokal). Liefert false, wenn die Bildquelle
         * noch nicht geladen ist.
         */
        _bildRadieren: function(el, x, y, r) {
            var c = this._radierBilder ? this._radierBilder[el.id] : null;
            if (!c) {
                var quelle = this._bildQuelle(el);
                if (!quelle || !this._radierBilder) return false;
                var bw = quelle.naturalWidth || quelle.width;
                var bh = quelle.naturalHeight || quelle.height;
                if (!bw || !bh) return false;
                c = document.createElement('canvas');
                c.width = bw;
                c.height = bh;
                c.getContext('2d').drawImage(quelle, 0, 0);
                this._radierBilder[el.id] = c;
            }
            var g = c.getContext('2d');
            g.save();
            g.scale(c.width / el.b, c.height / el.h);
            g.globalCompositeOperation = 'destination-out';
            g.fillStyle = 'rgba(0,0,0,1)';
            g.beginPath();
            g.arc(x, y, r, 0, Math.PI * 2);
            g.fill();
            g.restore();
            return true;
        },

        /**
         * Punkt der Zeichenfläche (CSS-Pixel) in Koordinaten eines
         * Bild-Elements: ab seiner linken oberen Ecke, ein Feld winkel
         * (3.1.143, Grad um diese Ecke) zurückgedreht.
         */
        _bildLokal: function(el, x, y) {
            var dx = x - el.x, dy = y - el.y;
            if (!el.winkel) return {x: dx, y: dy};
            var rad = -el.winkel * Math.PI / 180;
            var cos = Math.cos(rad), sin = Math.sin(rad);
            return {x: dx * cos - dy * sin, y: dx * sin + dy * cos};
        },

        /**
         * Ende einer Punkt-Radiergeste: radierte Bild-Elemente durch NEUE
         * Element-Objekte mit neuen daten ersetzen (das alte Objekt bleibt für
         * Rückgängig unverändert im Undo-Stapel), dann exakt neu zeichnen.
         */
        _radierGesteAbschliessen: function() {
            var bilder = this._radierBilder;
            this._radierBilder = null;
            this._radierLetzter = null;
            if (!this.modell || !bilder) return;
            var ids = Object.keys(bilder);
            if (ids.length) {
                var elemente = this.modell.elemente;
                for (var i = 0; i < elemente.length; i++) {
                    var el = elemente[i];
                    if (el.typ !== 'bild' || !bilder.hasOwnProperty(el.id)) continue;
                    var canvas = bilder[el.id];
                    var daten = canvas.toDataURL('image/png');
                    this._bildQuellen.set(daten, canvas);
                    elemente[i] = {id: el.id, typ: 'bild', daten: daten, x: el.x, y: el.y, b: el.b, h: el.h};
                    if (el.winkel) elemente[i].winkel = el.winkel; // 3.1.143
                }
            }
            if (this._radierGeaendert) {
                this.zeichneModell();
            }
            this._radierGeaendert = false;
        },

        /** Kleinster Abstand des Punkts (x,y) zum Linienzug punkte [[x,y],…]. */
        _abstandZumLinienzug: function(x, y, punkte) {
            var min = Infinity;
            for (var i = 0; i < punkte.length; i++) {
                var ax = punkte[i][0], ay = punkte[i][1];
                var bx = i + 1 < punkte.length ? punkte[i + 1][0] : ax;
                var by = i + 1 < punkte.length ? punkte[i + 1][1] : ay;
                var dx = bx - ax, dy = by - ay;
                var l2 = dx * dx + dy * dy;
                var t = l2 > 0 ? Math.max(0, Math.min(1, ((x - ax) * dx + (y - ay) * dy) / l2)) : 0;
                var ex = ax + t * dx - x, ey = ay + t * dy - y;
                var d = Math.sqrt(ex * ex + ey * ey);
                if (d < min) min = d;
            }
            return min;
        },

        _kreisSchneidetRahmen: function(cx, cy, r, rb) {
            var nx = Math.max(rb.x, Math.min(cx, rb.x + rb.b));
            var ny = Math.max(rb.y, Math.min(cy, rb.y + rb.h));
            var dx = cx - nx;
            var dy = cy - ny;
            return dx * dx + dy * dy < r * r;
        },

        _textInKreisNaehe: function(punkte, r) {
            var elemente = this.modell.elemente;
            for (var i = 0; i < elemente.length; i++) {
                if (elemente[i].typ !== 'text') continue;
                var rb = this._rahmen(elemente[i]);
                for (var k = 0; k < punkte.length; k++) {
                    if (this._kreisSchneidetRahmen(punkte[k].x, punkte[k].y, r, rb)) return true;
                }
            }
            return false;
        },

        // =============================================
        // Elementmodell (AP-4.3): Zustand, IDs, Undo, Bilder
        // =============================================

        /** Leeres Modell in der aktuellen Größe der Zeichenfläche (CSS-Pixel). */
        _leeresModell: function() {
            var b = this.cssWidth || (this.drawingCanvas ? this.drawingCanvas.width : 0) || 1;
            var h = this.cssHeight || (this.drawingCanvas ? this.drawingCanvas.height : 0) || 1;
            return elementeModul().leeresModell(b, h);
        },

        /**
         * Neue, im Modell eindeutige Element-ID 'e<n>'. Der Zähler läuft nur
         * vorwärts (auch über Rückgängig hinweg). Wird this.modell durch ein
         * anderes Objekt ersetzt (z. B. geladenes Modell, AP-4.4), zieht er
         * einmal per hoechsteIdNummer() nach - nicht bei jedem Aufruf, das
         * wäre beim Radieren O(Elemente) je neuem Teilstrich.
         */
        neueId: function() {
            if (this.modell && this._idModell !== this.modell) {
                var hoechste = elementeModul().hoechsteIdNummer(this.modell);
                if (this._idZaehler <= hoechste) this._idZaehler = hoechste + 1;
                this._idModell = this.modell;
            }
            return 'e' + (this._idZaehler++);
        },

        /** Strich/Text im bisherigen strokes-Format -> Element (oder null). */
        _elementAusStroke: function(stroke) {
            var self = this;
            var m = elementeModul().ausStrokes([stroke], this.modell.breite, this.modell.hoehe,
                function() { return self.neueId(); });
            return m.elemente.length ? m.elemente[0] : null;
        },

        /**
         * Messfunktion fuer Text-Elemente: function(text, px, stil) -> Breite
         * in px, mit stil = {f: 0|1, k: 0|1} (fett, kursiv) wie sie
         * CBDBoardElemente.rahmen()/waehleImLasso() uebergeben (AP-1.3,
         * Plan "Formatierter Text auf der Tafel", F5). Gemessen wird mit der
         * Zeichenebene in der Schrift, in der auch gezeichnet wird
         * (CBDBoardElemente.schrift) - fett und kursiv sind breiter, sonst
         * stimmen Rahmen, Lasso-Treffer und Radierer nicht. Ohne Zeichenebene
         * null: das Modul schaetzt dann 0,6 em je Zeichen.
         *
         * _rahmen() und _lassoAbschliessen() messen NUR darueber und bauen
         * keine eigene Messfunktion - sonst messen sie fett/kursiv wieder in
         * normaler Schrift. Seit AP-2.3 gilt das auch fuer _blocktextVerteilen()
         * ("Text auf die Tafel"); nur das laufende x beim Zeichnen
         * (zeichneModell, Pass 4) misst noch selbst, mit derselben Schrift.
         */
        _textMass: function() {
            var ctx = this.drawingCtx;
            if (!ctx) return null;
            var E = elementeModul();
            return function(text, px, stil) {
                ctx.font = E.schrift(px, stil && stil.f, stil && stil.k);
                return ctx.measureText(text).width;
            };
        },

        /** Rahmen eines Elements; Textbreite mit der Zeichenebene gemessen. */
        _rahmen: function(el) {
            var cache = this._rahmenCache;
            if (cache) {
                var r = cache.get(el);
                if (r) return r;
            }
            var rb = elementeModul().rahmen(el, this._textMass());
            if (cache) cache.set(el, rb);
            return rb;
        },

        /**
         * Undo (E12): flache Kopie der Elementliste auf den Stapel (max. 50).
         * Flach reicht, weil Elemente nie verändert, nur ersetzt werden -
         * eine tiefe Kopie würde die großen daten-Strings der Bild-Elemente
         * bei jedem Schritt verdoppeln.
         */
        merkeZustand: function() {
            if (!this.modell) return;
            this.undoStapel.push(this.modell.elemente.slice());
            while (this.undoStapel.length > UNDO_MAX) {
                this.undoStapel.shift();
            }
            this._bildQuellenAufraeumen();
        },

        /** Höchstens ein Undo-Schritt je Radiergeste. */
        _merkeEinmal: function() {
            if (this._gesteGemerkt) return;
            this._gesteGemerkt = true;
            this.merkeZustand();
        },

        /**
         * Leer = keine Elemente ODER keine sichtbaren Pixel (bisherige
         * Prüfung über den Alphakanal bleibt, AP-4.3 Schritt 8).
         */
        _istLeer: function() {
            if (!this.modell || this.modell.elemente.length === 0) return true;
            var pixels = this.drawingCtx.getImageData(0, 0, this.drawingCanvas.width, this.drawingCanvas.height).data;
            for (var i = 3; i < pixels.length; i += 4) {
                if (pixels[i] > 0) return false;
            }
            return true;
        },

        /**
         * PNG (data-URL) als EIN Bild-Element einfügen (E10): x 0, y 0,
         * b × h = aktuelle CSS-Maße der Zeichenfläche. Es kommt an den Anfang
         * der Liste (ältester Inhalt) und - weil Laden keine Nutzeraktion ist -
         * auch in alle schon vorhandenen Undo-Stände; sonst nähme ein vor dem
         * Laden begonnener Strich beim Rückgängigmachen das Bild mit.
         * quelle (optional): bereits erzeugtes Image/Canvas für diese daten;
         * ohne quelle wird ein Image angelegt und nach dem Laden neu gezeichnet.
         */
        bildElementHinzufuegen: function(daten, quelle) {
            if (!this.modell || typeof daten !== 'string' || !daten) return null;
            if (quelle) {
                this._bildQuellen.set(daten, quelle);
            } else if (!this._bildQuellen.has(daten)) {
                this._bildLaden(daten);
            }
            var el = {
                id: this.neueId(),
                typ: 'bild',
                daten: daten,
                x: 0,
                y: 0,
                b: this.cssWidth || this.drawingCanvas.width,
                h: this.cssHeight || this.drawingCanvas.height
            };
            this._geladeneVornEinfuegen([el]);
            this.zeichneModell();
            return el;
        },

        /**
         * Geladene Elemente (Server-/Cache-Antwort) VORN einfügen: in die
         * aktuelle Liste, in alle Undo-Stände (Laden ist keine Nutzeraktion)
         * und - AP-5.fix1 (AP-5.rev F1) - bei laufendem Verschieben auch in
         * die Momentaufnahme this._ziehen.basis; sonst baute der nächste
         * Frame bzw. das Abheben die Liste ohne sie neu, und das nächste
         * Speichern überschriebe die Tafel ohne den geladenen Inhalt.
         * Immer NEUE Arrays (concat), nie unshift: basis und
         * this.modell.elemente sind vor dem ersten Frame dasselbe Array, ein
         * unshift landete dort doppelt. Wird ein Undo-Stand ersetzt, der der
         * Undo-Eintrag des Ziehens ist, zieht z.undoEintrag mit (der
         * Identitätsvergleich in _ziehenBeenden() nimmt ihn bei 0 px bzw.
         * Verwerfen wieder vom Stapel). Dasselbe gilt seit 3.1.143 für die
         * Momentaufnahme einer laufenden Drehung (this._drehen.basis).
         */
        _geladeneVornEinfuegen: function(vorn) {
            var z = this._ziehen;
            var d = (this._drehen && this._drehen.basis) ? this._drehen : null;
            this.modell.elemente = vorn.concat(this.modell.elemente);
            for (var i = 0; i < this.undoStapel.length; i++) {
                var neu = vorn.concat(this.undoStapel[i]);
                if (z && this.undoStapel[i] === z.undoEintrag) z.undoEintrag = neu;
                if (d && this.undoStapel[i] === d.undoEintrag) d.undoEintrag = neu;
                this.undoStapel[i] = neu;
            }
            if (z) z.basis = vorn.concat(z.basis);
            if (d) d.basis = vorn.concat(d.basis);
        },

        /** true, wenn alle Bild-Elemente des Modells zeichenbereit sind. */
        bildElementeGeladen: function() {
            if (!this.modell) return false;
            var el = this.modell.elemente;
            for (var i = 0; i < el.length; i++) {
                if (el[i].typ === 'bild' && !this._bildQuelle(el[i])) return false;
            }
            return true;
        },

        _bildLaden: function(daten) {
            var self = this;
            var quellen = this._bildQuellen;
            var img = new Image();
            img.onload = function() {
                // nur, wenn die Tafel noch dieselbe Sitzung ist
                if (self._bildQuellen === quellen && self.modell) {
                    self.zeichneModell();
                }
            };
            quellen.set(daten, img);
            img.src = daten;
            return img;
        },

        /** Zeichenbereite Quelle (Image/Canvas) eines Bild-Elements oder null. */
        _bildQuelle: function(el) {
            if (this._radierBilder && this._radierBilder[el.id]) {
                return this._radierBilder[el.id];
            }
            if (!this._bildQuellen) return null;
            var q = this._bildQuellen.get(el.daten);
            if (!q) {
                q = this._bildLaden(el.daten);
            }
            if (typeof q.getContext === 'function') return q; // Canvas
            return (q.complete && q.naturalWidth > 0) ? q : null;
        },

        /**
         * Bildquellen, die weder das Modell noch ein Undo-Stand mehr
         * braucht, freigeben (erst ab 30 Einträgen - Radier-Gesten auf
         * Bildern erzeugen je eine neue Quelle).
         */
        _bildQuellenAufraeumen: function() {
            var quellen = this._bildQuellen;
            if (!quellen || quellen.size <= 30) return;
            var gebraucht = new Set();
            var listen = [this.modell.elemente].concat(this.undoStapel);
            for (var i = 0; i < listen.length; i++) {
                for (var j = 0; j < listen[i].length; j++) {
                    if (listen[i][j].typ === 'bild') gebraucht.add(listen[i][j].daten);
                }
            }
            quellen.forEach(function(wert, daten) {
                if (!gebraucht.has(daten)) quellen.delete(daten);
            });
        },

        // =============================================
        // Zeichnen
        // =============================================

        /** Linienzug eines Strich-Elements (punkte [[x,y],…]) zeichnen. */
        _elementLinie: function(ctx, punkte, breite, farbe) {
            if (punkte.length < 1) return;
            ctx.lineWidth = breite;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = farbe;
            ctx.beginPath();
            ctx.moveTo(punkte[0][0], punkte[0][1]);
            for (var j = 1; j < punkte.length; j++) {
                ctx.lineTo(punkte[j][0], punkte[j][1]);
            }
            ctx.stroke();
        },

        /** Linienzug des laufenden Strichs (points [{x,y},…]) zeichnen. */
        _laufendeLinie: function(ctx, stroke) {
            if (stroke.points.length < 1) return;
            ctx.lineWidth = stroke.width;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = stroke.color;
            ctx.beginPath();
            ctx.moveTo(stroke.points[0].x, stroke.points[0].y);
            for (var j = 1; j < stroke.points.length; j++) {
                ctx.lineTo(stroke.points[j].x, stroke.points[j].y);
            }
            ctx.stroke();
        },

        /**
         * Zeichenebene vollständig aus dem Elementmodell aufbauen (früher
         * redrawAllStrokes(), Alias unten). Der laufende Strich
         * (currentStroke) wird jeweils am Ende seines Durchgangs mitgezeichnet.
         */
        zeichneModell: function() {
            if (!this.drawingCtx || !this.drawingCanvas || !this.modell) return;
            var ctx = this.drawingCtx;
            var elemente = this.modell.elemente;
            var laufend = (this.currentStroke && this.currentStroke.points.length > 0) ? this.currentStroke : null;
            var i, el;

            // Canvas löschen
            ctx.clearRect(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);

            // Zeichenreihenfolge (#7152) - bitte nicht umstellen:
            //   Pass 1 Textmarker -> Pass 2 Bild-Elemente -> Pass 3 Stift -> Pass 4 Text
            // Bild-Elemente (PNG einer wieder geöffneten Tafel bzw. einer per
            // goToPage() zurückgeholten Seite, früher "Basis-Bild") NICHT
            // zuerst: Der deckende helle Marker (getLighterHighlightColor:
            // Alpha 1.0) ersetzte sonst per 'lighten' jede dunklere Stelle des
            // Bildes - also genau die alte Schrift, die man nachträglich
            // markieren wollte (Meldung #7152). Das PNG der Zeichenebene hat
            // transparenten Grund (die Tafelfarbe liegt auf eigener Ebene),
            // deshalb scheint ein Marker UNTER dem Bild überall dort durch, wo
            // das Bild leer ist, und liegt unter dessen Schrift - bei heller
            // wie dunkler Tafelfarbe. Passt zum inkrementellen Zeichnen
            // ('destination-over' in _segmentZeichnen). Ohne Bild-Elemente ist
            // das Ergebnis pixelgleich zur früheren Reihenfolge.

            // Pass 1: Textmarker (unterste Ebene, auch unter den Bild-Elementen)
            ctx.globalCompositeOperation = 'lighten';
            for (i = 0; i < elemente.length; i++) {
                el = elemente[i];
                if (el.typ === 'strich' && el.werkzeug === 'highlighter') {
                    this._elementLinie(ctx, el.punkte, el.breite, el.farbe);
                }
            }
            if (laufend && laufend.tool === 'highlighter') {
                this._laufendeLinie(ctx, laufend);
            }
            ctx.globalCompositeOperation = 'source-over';

            // Pass 2: Bild-Elemente in Listenreihenfolge, ÜBER den Textmarkern,
            // unter den Strichen (#7152, s. o.)
            for (i = 0; i < elemente.length; i++) {
                el = elemente[i];
                if (el.typ !== 'bild') continue;
                var quelle = this._bildQuelle(el);
                if (quelle && el.winkel) {
                    // 3.1.143: um die linke obere Ecke gedreht (Elementmodell)
                    ctx.save();
                    ctx.translate(el.x, el.y);
                    ctx.rotate(el.winkel * Math.PI / 180);
                    ctx.drawImage(quelle, 0, 0, el.b, el.h);
                    ctx.restore();
                } else if (quelle) {
                    ctx.drawImage(quelle, el.x, el.y, el.b, el.h);
                }
            }

            // Pass 3: Stift (obere Ebene)
            for (i = 0; i < elemente.length; i++) {
                el = elemente[i];
                if (el.typ === 'strich' && el.werkzeug !== 'highlighter') {
                    this._elementLinie(ctx, el.punkte, el.breite, el.farbe);
                }
            }
            if (laufend && laufend.tool !== 'highlighter' && laufend.tool !== 'text') {
                this._laufendeLinie(ctx, laufend);
            }

            // Composite Operation zurücksetzen
            ctx.globalCompositeOperation = 'source-over';

            // Pass 4: Text-Elemente (oberste Ebene). Schrift wie im
            // Elementmodell v1: Math.round(16 + breite * 4) px, Zeilenhöhe 1,2.
            // AP-1.3 (Plan "Formatierter Text auf der Tafel", F5): Jede Zeile
            // kommt aus CBDBoardElemente.zeilenTeile(el) als Liste von Teilen
            // {t, f, k, farbe} (Farbe schon aufgelöst); je Teil EIN fillText in
            // der Schrift schrift(px, f, k) und seiner Farbe, das laufende x
            // rückt um die mit derselben Schrift gemessene Breite des Teils
            // weiter. Ein Text ohne teile ist eine Zeile = ein Teil und wird
            // wie in 3.1.147 gezeichnet ('<px>px sans-serif', ein fillText je
            // Zeile; nur leere Zeilen rufen keinen fillText('') mehr).
            // AP-1.fix1 (Review-Befund B5): Gemessen wird nur, wenn in
            // derselben Zeile noch ein Teil FOLGT - nach dem letzten Teil
            // einer Zeile braucht niemand mehr das x. Ein Text ohne teile
            // (jede bestehende Tafel) misst beim Zeichnen deshalb gar nicht;
            // das zusätzliche measureText je Zeile kostete bei 240 Zeilen
            // rund 1 ms je Bild, und im Ziehen/Drehen läuft das je Frame.
            var E = elementeModul();
            for (i = 0; i < elemente.length; i++) {
                el = elemente[i];
                if (el.typ !== 'text') continue;
                var fontSizePx = E.schriftPx(el.breite);
                ctx.textBaseline = 'top';
                var zeilen = E.zeilenTeile(el);
                // 3.1.143: gedrehter Text um seine linke obere Ecke
                var tx = el.x, ty = el.y;
                if (el.winkel) {
                    ctx.save();
                    ctx.translate(el.x, el.y);
                    ctx.rotate(el.winkel * Math.PI / 180);
                    tx = 0;
                    ty = 0;
                }
                for (var li = 0; li < zeilen.length; li++) {
                    var zx = tx;
                    for (var ti = 0; ti < zeilen[li].length; ti++) {
                        var teil = zeilen[li][ti];
                        ctx.font = E.schrift(fontSizePx, teil.f, teil.k);
                        ctx.fillStyle = teil.farbe;
                        ctx.fillText(teil.t, zx, ty + li * fontSizePx * 1.2);
                        if (ti + 1 < zeilen[li].length) {
                            zx += ctx.measureText(teil.t).width;
                        }
                    }
                }
                if (el.winkel) {
                    ctx.restore();
                }
            }
        },

        /**
         * Früherer Name von zeichneModell() - bleibt als Alias (Prüfseite
         * tools/test-board-zeiger.html vergleicht damit das Neuzeichnen).
         */
        redrawAllStrokes: function() {
            this.zeichneModell();
        },

        // =============================================
        // Lasso-Werkzeug (Phase 5, AP-5.1)
        // =============================================
        //
        // Bedienung: Werkzeug 'lasso', Freihand-Umrandung ziehen, beim
        // Abheben sind die umrandeten Elemente gewählt (Regel E14 in
        // CBDBoardElemente.waehleImLasso(): Strich bei >= 50 % seiner Punkte
        // im Polygon, Text/Bild beim Mittelpunkt des Rahmens).
        //   this.auswahl  Array der gewählten Element-IDs (leer = keine)
        //   this._lasso   Punkte [[x,y],…] der laufenden Linie (CSS-Pixel der
        //                 Zeichenfläche, Zoom schon herausgerechnet) oder null
        // Anzeige NUR auf der Auswahl-Ebene (auswahlCanvas) und in der
        // DOM-Leiste - zeichneModell() fasst beide nicht an, das PNG entsteht
        // aus drawingCanvas und enthält sie deshalb nie.
        // Aufgehoben wird die Auswahl durch: neues Lasso, Werkzeugwechsel
        // (setTool), ESC, Knopf "Auswahl aufheben", Rückgängig, Seitenwechsel
        // (_seiteLeeren), "Zeichnung löschen", Schließen (destroy) und
        // (AP-5.2) Löschen der Auswahl. Verschieben/Löschen: Abschnitt unten.

        /**
         * Auswahl und laufende Lassolinie verwerfen (Elemente bleiben). Ein
         * laufendes Verschieben (AP-5.2) wird dabei mit der erreichten Lage
         * abgeschlossen - so speichert ein Seitenwechsel/Schließen mitten im
         * Ziehen, was zu sehen ist, und Strg+Z beim Ziehen nimmt genau dieses
         * Verschieben zurück.
         */
        auswahlAufheben: function() {
            // Fenster "Text bearbeiten" (AP-3.2) gehört zu dieser Auswahl:
            // fällt sie weg - Seitenwechsel (_seiteLeeren), Werkzeugwechsel,
            // Rückgängig -, schließt es ohne Änderung. Sonst träfe
            // "Übernehmen" bei einer anderen Seite (IDs gelten nur je Seite)
            // womöglich ein fremdes Element. Andere Blocktext-Fenster (Vorschau,
            // Rückfrage) bleiben unberührt: blocktextEinfuegen() ruft diese
            // Methode mitten im Klick auf "Auf die Tafel".
            if (this._textBearbeitenFenster) {
                this._blocktextFensterSchliessen(false);
            }
            if (this._ziehen) {
                this._ziehenBeenden(true);
            }
            // 3.1.143: ebenso eine laufende Drehung; der Drehmodus endet
            if (this._drehen) {
                this._drehenBeenden(true);
            }
            this.auswahl = [];
            this._lasso = null;
            this._auswahlZeichnen();
        },

        /** Neue Lassolinie an (x, y) beginnen; hebt eine bestehende Auswahl auf. */
        _lassoBeginnen: function(x, y) {
            this.auswahl = [];
            this._auswahlFarbeWert = null; // Theme-Farbe je Geste neu lesen (Darkmode-Wechsel)
            this._lasso = [[x, y]];
            this._auswahlZeichnen();
        },

        /**
         * Lassolinie schließen und auswählen. Weniger als drei Punkte
         * (Antippen) wählen nichts (waehleImLasso liefert dann []).
         */
        _lassoAbschliessen: function() {
            var polygon = this._lasso;
            this._lasso = null;
            // AP-1.3: Messung ueber _textMass() (fett/kursiv), wie in _rahmen()
            var ids = (polygon && this.modell) ? elementeModul().waehleImLasso(this.modell, polygon, this._textMass()) : [];
            this.auswahl = ids;
            this._auswahlZeichnen();
        },

        // ---------------------------------------------
        // Verschieben und Löschen der Auswahl (AP-5.2)
        // ---------------------------------------------
        //
        // Verschieben: Ein Zeiger, der mit dem Lasso-Werkzeug INNERHALB von
        // auswahlRahmen() (Vereinigungsrahmen + 8 px) aufsetzt, zieht die
        // Auswahl, statt ein neues Lasso zu beginnen. Beim Aufsetzen EIN
        // Undo-Schritt (merkeZustand) und eine Momentaufnahme der
        // Elementliste (this._ziehen.basis). Jeder Zwischenstand entsteht aus
        // dieser Momentaufnahme + verschiebe() mit der GESAMTEN Verschiebung
        // seit dem Start - bitte nicht auf schrittweises Verschieben des
        // jeweils letzten Stands umstellen: das summierte Rundungs- und
        // Gleitkommafehler auf (Plan AP-5.2 Schritt 2: "nicht kumulativ
        // runden"; gerundet wird nur beim Speichern, Elementmodell v1).
        // Zwischenstände werden per requestAnimationFrame gebündelt
        // (höchstens ein Neuzeichnen je Frame); das Abheben übernimmt die
        // Endlage sofort. 0 px Gesamtverschiebung -> Undo-Schritt wieder weg.
        // Grenzen: kein Einrasten; über den Rand geschobene Elemente bleiben
        // im Modell (auch ganz außerhalb liegende - kein stilles Löschen),
        // das PNG zeigt nur den sichtbaren Teil. Liegt die Auswahl ganz
        // außerhalb, lässt sie sich noch löschen (Entf, Knopf) oder per
        // Strg+Z zurückholen, aber nicht mehr greifen.

        /** Liegt (x, y) (CSS-Pixel der Zeichenfläche) im Auswahlrahmen? */
        _imAuswahlRahmen: function(x, y) {
            var r = this.auswahlRahmen();
            return !!r && x >= r.x && x <= r.x + r.b && y >= r.y && y <= r.y + r.h;
        },

        /** Verschieben an (x, y) beginnen: Undo-Schritt, Momentaufnahme, Leiste weg. */
        _ziehenBeginnen: function(x, y) {
            this.merkeZustand();
            this._ziehen = {
                x0: x,
                y0: y,
                dx: 0,
                dy: 0,
                // Momentaufnahme; wird nie verändert (verschiebe() baut neu),
                // nur ERSETZT: Jedes asynchrone Einfügen während des Ziehens
                // (Server-Ladeantwort, Bild-Rückfall) muss auch basis pflegen
                // - über _geladeneVornEinfuegen() (AP-5.fix1, AP-5.rev F1)
                basis: this.modell.elemente,
                ids: this.auswahl.slice(),
                undoEintrag: this.undoStapel[this.undoStapel.length - 1],
                frame: null
            };
            if (this.drawingCanvas) {
                this.drawingCanvas.classList.add('cbd-board-ziehen-aktiv');
            }
            this._auswahlLeisteAusrichten(); // blendet die Leiste aus
        },

        /** Neue Zeigerlage merken, im nächsten Frame anwenden. */
        _ziehenVormerken: function(x, y) {
            var z = this._ziehen;
            if (!z) return;
            z.dx = x - z.x0;
            z.dy = y - z.y0;
            var self = this;
            this._frameVormerken(z, function() {
                if (self._ziehen === z) self._ziehenAnwenden(z);
            });
        },

        /**
         * anwenden() im nächsten Frame aufrufen, höchstens einmal je Frame
         * (z.frame hält die Vormerkung; Verschieben AP-5.2, Drehen 3.1.143).
         * requestAnimationFrame bündelt auf einen Schritt je Frame. Ein
         * Zeitgeber (50 ms) springt ein, falls der Browser keine Frames
         * liefert (verborgener Tab/Bereich, gedrosselte Bildwiederholung) -
         * wer zuerst kommt, wendet an und hebt den anderen auf.
         */
        _frameVormerken: function(z, anwenden) {
            if (z.frame !== null) return;
            var self = this;
            var fertig = function() {
                if (z.frame === null) return;
                self._frameAbbrechen(z);
                anwenden();
            };
            z.frame = {
                raf: typeof window.requestAnimationFrame === 'function' ? window.requestAnimationFrame(fertig) : null,
                zeit: setTimeout(fertig, 50)
            };
        },

        /** Vormerkung aus _frameVormerken() zurücknehmen (ohne anzuwenden). */
        _frameAbbrechen: function(z) {
            if (z.frame === null) return;
            if (z.frame.raf !== null && typeof window.cancelAnimationFrame === 'function') {
                window.cancelAnimationFrame(z.frame.raf);
            }
            clearTimeout(z.frame.zeit);
            z.frame = null;
        },

        /** Modell = Momentaufnahme + Gesamtverschiebung; neu zeichnen, Rahmen folgt. */
        _ziehenAnwenden: function(z) {
            if (!this.modell) return;
            var m = elementeModul().verschiebe({
                v: this.modell.v, breite: this.modell.breite, hoehe: this.modell.hoehe,
                elemente: z.basis
            }, z.ids, z.dx, z.dy);
            this.modell.elemente = m.elemente;
            this.zeichneModell();
            this._auswahlZeichnen();
        },

        /**
         * Verschieben beenden. uebernehmen=true: Endlage übernehmen (Abheben,
         * Aufheben der Auswahl); false: verwerfen (Pinch, Stift übernimmt) -
         * Elemente zurück an den Start. Ohne Gesamtverschiebung oder beim
         * Verwerfen verschwindet der Undo-Schritt des Aufsetzens wieder.
         */
        _ziehenBeenden: function(uebernehmen) {
            var z = this._ziehen;
            if (!z) return;
            this._ziehen = null;
            this._frameAbbrechen(z);
            if (this.drawingCanvas) {
                this.drawingCanvas.classList.remove('cbd-board-ziehen-aktiv');
            }
            if (!this.modell) return;
            if (uebernehmen && (z.dx !== 0 || z.dy !== 0)) {
                this._ziehenAnwenden(z);
                return;
            }
            this.modell.elemente = z.basis;
            var st = this.undoStapel;
            if (st.length && st[st.length - 1] === z.undoEintrag) {
                st.pop();
            }
            this.zeichneModell();
            this._auswahlZeichnen();
        },

        /**
         * Gewählte Elemente löschen (Knopf "Löschen", Entf/Rücktaste): EIN
         * Undo-Schritt, danach ist die Auswahl aufgehoben. Ohne Auswahl bzw.
         * wenn keine gewählte ID mehr im Modell steht: nichts (kein
         * Undo-Schritt). Liefert true, wenn etwas gelöscht wurde.
         */
        auswahlLoeschen: function() {
            if (!this.modell || !this.auswahl || !this.auswahl.length) return false;
            if (this._ziehen) this._ziehenBeenden(true);
            if (this._drehen) this._drehenBeenden(true); // 3.1.143
            var neu = elementeModul().entferne(this.modell, this.auswahl).elemente;
            if (neu.length === this.modell.elemente.length) {
                this.auswahlAufheben();
                return false;
            }
            this.merkeZustand();
            this.modell.elemente = neu;
            this.auswahlAufheben();
            this.zeichneModell();
            return true;
        },

        // ---------------------------------------------
        // Drehen und Duplizieren der Auswahl (3.1.143)
        // ---------------------------------------------
        //
        // Drehen wie in CAD-Programmen (Knopf "Drehen" der Auswahlleiste,
        // das Werkzeug bleibt 'lasso'), this._drehen.phase:
        //   'drehpunkt'  Aufsetzen setzt den Drehpunkt; solange der Zeiger
        //                aufliegt, folgt der Punkt ihm, Abheben -> 'bezug'.
        //   'bezug'      Aufsetzen setzt den Bezugspunkt -> 'drehen'.
        //   'drehen'     Ziehen dreht die Auswahl um den Winkel zwischen den
        //                Strahlen Drehpunkt->Bezugspunkt und Drehpunkt->Zeiger;
        //                Abheben übernimmt die Drehung und beendet den Modus.
        // Überall auf der Fläche, nicht nur im Auswahlrahmen. Wie beim
        // Verschieben: EIN Undo-Schritt beim Aufsetzen des Bezugspunkts,
        // jeder Zwischenstand aus der Momentaufnahme basis + Gesamtwinkel
        // (CBDBoardElemente.drehe, nicht schrittweise), je Frame gebündelt;
        // 0° -> Undo-Schritt wieder weg. Winkel in ganzen Grad, nahe (±3°)
        // an Vielfachen von 45° rastet er ein, mit Umschalttaste 15°-Schritte.
        // Drehpunkt, Strahlen, Bogen und Gradzahl stehen nur auf der
        // Auswahl-Ebene, nie im PNG. ESC/"Abbrechen": laufende Drehung
        // zurück, Modus aus, Auswahl bleibt. Aufheben der Auswahl
        // (Werkzeugwechsel, Strg+Z, Seitenwechsel, Schließen) übernimmt eine
        // laufende Drehung; zweiter Finger/Stift übernimmt -> Drehung
        // verworfen, Drehpunkt bleibt ('bezug').
        //
        // Duplizieren: Kopien 20 px nach rechts unten versetzt am Ende der
        // Liste (CBDBoardElemente.kopiere), danach sind die KOPIEN gewählt
        // und lassen sich sofort ziehen. EIN Undo-Schritt. Knopf oder Strg+D.

        /** Drehmodus für die Auswahl beginnen (Knopf "Drehen"). */
        auswahlDrehenStarten: function() {
            if (!this.modell || !this.auswahlRahmen()) return false;
            if (this._ziehen) this._ziehenBeenden(true);
            if (!this._drehen) {
                this._drehen = {
                    phase: 'drehpunkt',
                    px: null,
                    py: null,
                    punktZieht: false,
                    ax: 0, ay: 0, bx: 0, by: 0,
                    grad: 0,
                    // Momentaufnahme wie _ziehen.basis - asynchrones Einfügen
                    // pflegt sie über _geladeneVornEinfuegen()
                    basis: null,
                    ids: null,
                    undoEintrag: null,
                    frame: null
                };
            }
            this._auswahlZeichnen();
            return true;
        },

        /** Zeiger setzt im Drehmodus auf (Drehpunkt bzw. Bezugspunkt). */
        _drehenAufsetzen: function(x, y) {
            var d = this._drehen;
            if (d.phase === 'drehpunkt') {
                d.px = x;
                d.py = y;
                d.punktZieht = true;
            } else if (d.phase === 'bezug') {
                this.merkeZustand();
                d.phase = 'drehen';
                d.ax = d.bx = x;
                d.ay = d.by = y;
                d.grad = 0;
                d.basis = this.modell.elemente;
                d.ids = this.auswahl.slice();
                d.undoEintrag = this.undoStapel[this.undoStapel.length - 1];
            }
            this._auswahlZeichnen();
        },

        /** Zeiger bewegt sich im Drehmodus (fein: Umschalttaste, 15°-Schritte). */
        _drehenBewegen: function(x, y, fein) {
            var d = this._drehen;
            if (!d) return;
            if (d.phase === 'drehpunkt' && d.punktZieht) {
                d.px = x;
                d.py = y;
                this._auswahlZeichnen();
                return;
            }
            if (d.phase !== 'drehen') return;
            d.bx = x;
            d.by = y;
            d.grad = this._drehwinkelRasten(elementeModul().drehwinkel(d.px, d.py, d.ax, d.ay, x, y), fein);
            var self = this;
            this._frameVormerken(d, function() {
                if (self._drehen === d) self._drehenAnwenden(d);
            });
        },

        /**
         * Winkel auf ganze Grad; nahe an Vielfachen von 45° (±3°) einrasten,
         * mit fein=true auf 15°-Schritte. Ergebnis in (-180, 180].
         */
        _drehwinkelRasten: function(grad, fein) {
            var g;
            if (fein) {
                g = Math.round(grad / 15) * 15;
            } else {
                var rast = Math.round(grad / 45) * 45;
                g = Math.abs(grad - rast) <= 3 ? rast : Math.round(grad);
            }
            if (g <= -180) g += 360;
            if (g > 180) g -= 360;
            return g === 0 ? 0 : g;
        },

        /** Modell = Momentaufnahme, um den Drehpunkt gedreht; neu zeichnen. */
        _drehenAnwenden: function(d) {
            if (!this.modell || !d.basis) return;
            var m = elementeModul().drehe({
                v: this.modell.v, breite: this.modell.breite, hoehe: this.modell.hoehe,
                elemente: d.basis
            }, d.ids, d.px, d.py, d.grad);
            this.modell.elemente = m.elemente;
            this.zeichneModell();
            this._auswahlZeichnen();
        },

        /** Zeiger hebt im Drehmodus ab: Drehpunkt steht bzw. Drehung übernehmen. */
        _drehenAbheben: function() {
            var d = this._drehen;
            if (!d) return;
            if (d.phase === 'drehpunkt' && d.punktZieht) {
                d.punktZieht = false;
                d.phase = 'bezug';
                this._auswahlZeichnen();
            } else if (d.phase === 'drehen') {
                this._drehenBeenden(true);
            }
        },

        /** Laufende Drehung zurücknehmen (Elemente, Undo-Schritt); d bleibt. */
        _drehenZuruecknehmen: function(d) {
            this._frameAbbrechen(d);
            if (this.modell && d.basis) {
                this.modell.elemente = d.basis;
                var st = this.undoStapel;
                if (st.length && st[st.length - 1] === d.undoEintrag) {
                    st.pop();
                }
                this.zeichneModell();
            }
            d.basis = null;
            d.ids = null;
            d.undoEintrag = null;
            d.grad = 0;
        },

        /**
         * Drehmodus beenden. uebernehmen=true: eine laufende Drehung gilt
         * (0° nimmt den Undo-Schritt wieder weg); false: zurücknehmen.
         * Die Auswahl bleibt in beiden Fällen.
         */
        _drehenBeenden: function(uebernehmen) {
            var d = this._drehen;
            if (!d) return;
            this._drehen = null;
            if (d.phase === 'drehen') {
                if (uebernehmen && d.grad !== 0) {
                    this._frameAbbrechen(d);
                    this._drehenAnwenden(d);
                    return;
                }
                this._drehenZuruecknehmen(d);
            }
            this._auswahlZeichnen();
        },

        /** ESC bzw. Knopf "Abbrechen": Drehung zurück, Modus aus. */
        _drehenAbbrechen: function() {
            this._drehenBeenden(false);
        },

        /**
         * Zweiter Finger/Stift übernimmt (_strichVerwerfen): laufende Drehung
         * zurück, Drehpunkt bleibt - der nächste Bezugspunkt dreht weiter.
         */
        _drehenVerwerfen: function() {
            var d = this._drehen;
            if (!d) return;
            if (d.phase === 'drehen') {
                this._drehenZuruecknehmen(d);
                d.phase = 'bezug';
            } else if (d.punktZieht) {
                d.punktZieht = false;
                d.phase = 'bezug';
            }
            this._auswahlZeichnen();
        },

        /**
         * Gewählte Elemente duplizieren (Knopf "Duplizieren", Strg+D). Die
         * Kopien sind danach gewählt. Liefert true, wenn etwas kopiert wurde.
         */
        auswahlDuplizieren: function() {
            if (!this.modell || !this.auswahl || !this.auswahl.length) return false;
            if (this._ziehen) this._ziehenBeenden(true);
            if (this._drehen) this._drehenBeenden(true);
            var self = this;
            var k = elementeModul().kopiere(this.modell, this.auswahl, 20, 20, function() {
                return self.neueId();
            });
            if (!k.ids.length) {
                this.auswahlAufheben();
                return false;
            }
            this.merkeZustand();
            this.modell.elemente = k.modell.elemente;
            this.auswahl = k.ids;
            this.zeichneModell();
            this._auswahlZeichnen();
            return true;
        },

        /**
         * Schrift der gewählten Texte einen Schritt größer (richtung > 0)
         * bzw. kleiner (< 0) - Knöpfe "A+"/"A−" der Auswahlleiste (3.1.144).
         * Faktor 1,2 je Schritt, 18-200 px (CBDBoardElemente.textGroesse);
         * linke obere Ecke und winkel bleiben, andere gewählte Elemente
         * bleiben unverändert, die Auswahl bleibt. EIN Undo-Schritt je Klick
         * (wie bei Office-Programmen); an der Grenze kein Undo-Eintrag.
         * Liefert true, wenn sich etwas geändert hat.
         */
        auswahlTextGroesse: function(richtung) {
            if (!this.modell || !this.auswahl || !this.auswahl.length) return false;
            if (this._ziehen) this._ziehenBeenden(true);
            if (this._drehen) this._drehenBeenden(true);
            var t = elementeModul().textGroesse(this.modell, this.auswahl, richtung);
            if (!t.geaendert) return false;
            this.merkeZustand();
            this.modell.elemente = t.modell.elemente;
            this.zeichneModell();
            this._auswahlZeichnen();
            return true;
        },

        // ---------------------------------------------
        // "Text bearbeiten" (PLAN-Blocktext-Formatierung.md, Phase 3)
        // ---------------------------------------------
        //
        // Ist genau EIN Text gewählt, zeigt die Auswahlleiste den Knopf
        // "Text bearbeiten" (F11; Klasse --ein-text in
        // _auswahlLeisteAusrichten). Sein Fenster (AP-3.2) öffnet den Text im
        // Bearbeitungsfeld aus _textFeldBauen (Modus 'bearbeiten'), und
        // "Übernehmen" ruft textErsetzen() mit dem, was _textFeldLesen()
        // liefert.

        /**
         * Das gewählte Text-Element, wenn genau EIN Element gewählt ist und
         * es ein Text ist - sonst null (AP-3.1). Grundlage für die
         * Sichtbarkeit von "Text bearbeiten".
         */
        _einGewaehlterText: function() {
            if (!this.modell || !this.auswahl || this.auswahl.length !== 1) return null;
            var el = this.modell.elemente;
            for (var i = 0; i < el.length; i++) {
                if (el[i].id === this.auswahl[0]) return el[i].typ === 'text' ? el[i] : null;
            }
            return null;
        },

        /**
         * Knopf "Text bearbeiten" der Auswahlleiste (F11, AP-3.2): öffnet den
         * gewählten Text in einem Fenster - dasselbe Bearbeitungsfeld wie die
         * Vorschau von "Text auf die Tafel" (_textFeldBauen), aber im Modus
         * 'bearbeiten' (ein Absatz, Enter = Zeilenwechsel, F12), mit der
         * Farbe des Elements als Grundschrift und der Tafelfarbe als
         * Hintergrund, samt Formatleiste. Nur bei genau EINEM gewählten Text
         * (_einGewaehlterText), sonst nichts. Die Schreibmarke steht am ENDE
         * des Texts (Weitertippen hängt an).
         *
         * "Übernehmen" liest das Feld (_textFeldLesen) und ruft
         * textErsetzen(id, absaetze) - außer wenn sich der Inhalt seit dem
         * Öffnen nicht geändert hat (dann bliebe sonst durch das
         * zusammenfassende Lesen etwa ein doppeltes Leerzeichen auf der
         * Strecke, und es gäbe einen überflüssigen Undo-Schritt) oder wenn
         * der Text inzwischen nicht mehr als einziger gewählt ist (dann
         * schließt es ohne Änderung; Element-IDs sind nur je Seite eindeutig).
         * Danach: Klickschutz (400 ms, wie bei "Auf die Tafel"), der Fokus
         * liegt nicht mehr im Fenster und auf keinem Knopf. "Abbrechen" und
         * Escape ändern nichts; der Fokus kehrt auf "Text bearbeiten" in der
         * Auswahlleiste zurück.
         *
         * Das Fenster schließt ohne Änderung, wenn die Tafel schließt
         * (close/destroy/_openOverlay über _blocktextFensterSchliessen) oder
         * die Auswahl aufgehoben wird - auch beim Seitenwechsel, der über
         * _seiteLeeren() auswahlAufheben() ruft. Liefert true, wenn das
         * Fenster geöffnet wurde.
         */
        auswahlTextBearbeiten: function() {
            if (!this.overlay) return false;
            // Laufende Gesten zuerst übernehmen (wie auswahlTextGroesse und
            // textErsetzen): sie bauen neue Element-Objekte
            if (this._ziehen) this._ziehenBeenden(true);
            if (this._drehen) this._drehenBeenden(true);
            var alt = this._einGewaehlterText();
            if (!alt) return false;
            var self = this;
            var id = alt.id;
            var teile = this._blocktextFensterBauen('cbd-board-text-bearbeiten', 'Text bearbeiten',
                'Ändere den Text. Markiere Wörter, um sie fett, kursiv oder farbig zu machen.',
                function() { self._textBearbeitenSchliessen(true); });
            var bau = this._textFeldBauen([{ text: alt.text, teile: alt.teile }], {
                modus: 'bearbeiten',
                grundfarbe: alt.farbe,
                hintergrund: this.boardColor
            });
            var feld = bau.feld;
            // Feld VOR der Leiste (erstes Ziel der Tab-Falle); board-mode.css
            // stellt die Leiste per order darüber (wie in der Vorschau)
            teile.dialog.appendChild(feld);
            teile.dialog.appendChild(bau.leiste);

            var anfang = null;
            var erledigt = false;
            teile.aktionen.appendChild(this._blocktextFensterKnopf('cbd-board-confirm-cancel', 'Abbrechen', function() {
                self._textBearbeitenSchliessen(true);
            }));
            teile.aktionen.appendChild(this._blocktextFensterKnopf('cbd-board-blocktext-einfuegen cbd-board-text-uebernehmen',
                'Übernehmen', function() {
                    if (erledigt) return; // schneller Doppelklick
                    erledigt = true;
                    // Lesen, solange das Feld noch im Dokument hängt
                    var absaetze = self._textFeldLesen(feld);
                    var geaendert = JSON.stringify(absaetze) !== anfang;
                    var gewaehlt = self._einGewaehlterText();
                    var ziel = gewaehlt && gewaehlt.id === id;
                    self._blocktextFensterSchliessen(false);
                    if (geaendert && ziel) self.textErsetzen(id, absaetze);
                    self._blocktextKlickschutz();
                }));
            teile.dialog.appendChild(teile.aktionen);
            document.body.appendChild(teile.fenster);
            this._textBearbeitenFenster = teile.fenster;

            // Fokus ins Feld, Schreibmarke ans Ende. Zurückgelesen wird der
            // Ausgangszustand, damit "Übernehmen" ohne eigene Änderung nichts
            // ändert (anfang).
            feld.focus();
            this._textFeldMarkeAmEnde(feld);
            feld.scrollTop = feld.scrollHeight;
            anfang = JSON.stringify(this._textFeldLesen(feld));
            return true;
        },

        /**
         * Fenster "Text bearbeiten" ohne Änderung schließen (Abbrechen,
         * Escape). rueckfokus: Fokus zurück auf den Knopf "Text bearbeiten"
         * der Auswahlleiste (die Auswahl bleibt, der Knopf ist sichtbar).
         */
        _textBearbeitenSchliessen: function(rueckfokus) {
            this._blocktextFensterSchliessen(false);
            var knopf = (rueckfokus && this._auswahlLeiste) ?
                this._auswahlLeiste.querySelector('[data-aktion="text-bearbeiten"]') : null;
            if (knopf) {
                try {
                    knopf.focus({ preventScroll: true });
                } catch (fehler) {
                    knopf.focus();
                }
            }
        },

        /**
         * Schreibmarke ans Ende des Texts im Bearbeitungsfeld setzen
         * (eingeklappt). Sie steht im letzten Textknoten, hinter dessen
         * letztem Zeichen - Weitertippen bleibt im selben Block und in der
         * Formatierung des letzten Teils, ein Eintrag direkt am Feld (hinter
         * dem div) hätte einen neuen Absatz gemacht. Endet der Text auf einen
         * Zeilenwechsel (<br>), steht sie davor.
         */
        _textFeldMarkeAmEnde: function(feld) {
            var sel = window.getSelection();
            if (!sel || !feld) return;
            var blatt = feld;
            while (blatt.lastChild) blatt = blatt.lastChild;
            var bereich = document.createRange();
            if (blatt.nodeType === 3) bereich.setStart(blatt, blatt.nodeValue.length);
            else if (blatt.tagName === 'BR') bereich.setStartBefore(blatt);
            else bereich.setStart(blatt, 0);
            bereich.collapse(true);
            sel.removeAllRanges();
            sel.addRange(bereich);
        },

        /**
         * Inhalt eines Text-Elements ersetzen (AP-3.1, Entscheidungen F7,
         * F11, F12) - für "Übernehmen" im Fenster "Text bearbeiten".
         *
         * absaetze: Array wie von _textFeldLesen() - Objekte {text, teile?}
         * (teile wie im Elementmodell) und/oder Zeichenketten. Sie werden mit
         * "\n" zu EINEM Text verbunden (das "\n" ist ein Klartext-Teil);
         * Einträge ohne sichtbaren Text zählen nicht (wie bei
         * blocktextEinfuegen). Unbrauchbare teile (kein Array, ein Eintrag
         * ohne Zeichenketten-t, Verkettung ≠ text) -> der Absatz als Klartext;
         * eine Teilgrenze mitten in einem Surrogatpaar rückt eine Einheit
         * nach hinten (AP-3.fix2, Befund B2).
         *
         * Das Element bleibt unter derselben ID an derselben Stelle der
         * Liste; x, y, breite, winkel und farbe bleiben. Umbruch (F12a,
         * AP-3.fix2, _textErsetzenUmbrechen): nur bei unverdrehten Texten,
         * nur neue oder geänderte Zeilen, die über den rechten Rand des
         * SICHTBAREN Ausschnitts selbst ragen - auf diesen Rand minus 20 px,
         * mit umbrechenTeile in der Schrift des Elements, und nicht, wenn
         * dafür weniger als 8 × Schrift-px bleiben; vorhandene Zeilenwechsel
         * und alle übrigen Zeilen bleiben unverändert. Teilfarben gehen durch
         * lesbareLauffarbe(…, Tafelfarbe) (F7), danach teileZusammenlegen -
         * beides über _blocktextMitTeilen() (mit dessen Absicherung: mehr als
         * MAX_TEILE Teile -> ohne teile, der Text bleibt).
         *
         * Obergrenze (AP-3.fix1): höchstens 5000 UTF-16-Einheiten, nie mitten
         * in einem Surrogatpaar, teile passend zugeschnitten
         * (_textErsetzenKuerzen) - vor dem Umbruch und danach, weil dieser
         * Zeilenwechsel in überlange Wörter einfügen kann.
         *
         * EIN Undo-Schritt, die Auswahl bleibt. Leerer Inhalt ([] oder nur
         * Leerraum) entfernt das Element (ein Undo-Schritt), die ID fällt aus
         * der Auswahl. Laufendes Ziehen/Drehen wird vorher übernommen (wie
         * bei auswahlTextGroesse).
         *
         * Rückgabe true bei einer Änderung; false ohne Änderung (gleicher
         * Inhalt, unbekannte ID, kein Text, absaetze kein Array) - dann kein
         * Undo-Eintrag.
         */
        textErsetzen: function(id, absaetze) {
            if (!this.modell || !Array.isArray(absaetze)) return false;
            if (this._ziehen) this._ziehenBeenden(true);
            if (this._drehen) this._drehenBeenden(true);
            // erst nach dem Beenden suchen: Ziehen/Drehen bauen neue Objekte
            var liste = this.modell.elemente;
            var stelle = -1;
            for (var i = 0; i < liste.length; i++) {
                if (liste[i].id === id) {
                    stelle = i;
                    break;
                }
            }
            if (stelle < 0 || liste[stelle].typ !== 'text') return false;
            var alt = liste[stelle];
            // AP-3.fix1: Obergrenze schon vor dem Umbruch (begrenzt dessen
            // Arbeit) und noch einmal danach (er kann Zeilenwechsel mitten in
            // einem überlangen Wort einfügen)
            var inhalt = this._textErsetzenKuerzen(this._textErsetzenInhalt(absaetze));

            if (!/\S/.test(inhalt.text)) {
                this.merkeZustand();
                this.modell.elemente = elementeModul().entferne(this.modell, [id]).elemente;
                this.auswahl = (this.auswahl || []).filter(function(a) { return a !== id; });
                this.zeichneModell();
                this._auswahlZeichnen();
                return true;
            }

            if (!alt.winkel) inhalt = this._textErsetzenKuerzen(this._textErsetzenUmbrechen(inhalt, alt));
            var neu = this._blocktextMitTeilen({ id: alt.id, typ: 'text', farbe: alt.farbe, breite: alt.breite,
                text: inhalt.text, x: alt.x, y: alt.y }, inhalt.teile);
            if (alt.winkel) neu.winkel = alt.winkel;
            if (neu.text === alt.text && JSON.stringify(neu.teile) === JSON.stringify(alt.teile)) return false;

            this.merkeZustand();
            var elemente = liste.slice();
            elemente[stelle] = neu;
            this.modell.elemente = elemente;
            this.zeichneModell();
            this._auswahlZeichnen();
            return true;
        },

        /**
         * Absätze -> {text, teile} für textErsetzen(): mit "\n" verbunden,
         * teile vollständig (Klartext als {t}), Verkettung === text. Eine
         * Teilgrenze mitten in einem Surrogatpaar rückt eine Einheit nach
         * hinten (textTeileOhneGeteiltePaare, AP-3.fix2, Befund B2) - danach
         * schneiden Kürzen und Umbruch nur noch an Paargrenzen.
         */
        _textErsetzenInhalt: function(absaetze) {
            var text = '';
            var teile = [];
            var erster = true;
            for (var i = 0; i < absaetze.length; i++) {
                var a = absaetze[i];
                var t = null;
                var tt = null;
                if (typeof a === 'string') {
                    t = a;
                } else if (a !== null && typeof a === 'object' && typeof a.text === 'string') {
                    t = a.text;
                    tt = textTeileBrauchbar(a.teile, t);
                    if (tt) tt = textTeileOhneGeteiltePaare(tt);
                }
                if (t === null || !/\S/.test(t)) continue;
                if (!erster) {
                    text += '\n';
                    teile.push({ t: '\n' });
                }
                erster = false;
                text += t;
                teile = teile.concat(tt || [{ t: t }]);
            }
            return { text: text, teile: teile };
        },

        /**
         * Obergrenze für textErsetzen() (AP-3.fix1, Befund aus AP-3.1):
         * {text, teile} auf höchstens TEXT_MAX_EINHEITEN (5000) UTF-16-
         * Einheiten kürzen, nie mitten in einem Surrogatpaar (endet der
         * Schnitt hinter einem hohen Surrogat, entfällt es), die teile über
         * textTeileAusschnitt passend zuschneiden. Kürzer oder gleich lang:
         * dasselbe Objekt. Sonst kürzte pruefeModell den Text beim nächsten
         * Laden und verwürfe dabei die Formatierung (Verkettung ≠ text).
         */
        _textErsetzenKuerzen: function(inhalt) {
            var text = inhalt.text;
            if (text.length <= TEXT_MAX_EINHEITEN) return inhalt;
            var ende = TEXT_MAX_EINHEITEN;
            var c = text.charCodeAt(ende - 1);
            if (c >= 0xD800 && c <= 0xDBFF) ende--;
            return { text: text.slice(0, ende), teile: textTeileAusschnitt(inhalt.teile, 0, ende) };
        },

        /**
         * Umbruchregel F12a für textErsetzen() (AP-3.fix2, Review AP-3.rev,
         * Befund B1; Entscheidung des Orchestrators - vorher F12: jede Zeile
         * über Rand − 20 px). Ziel: der Text bleibt sichtbar, nichts wird
         * unnötig verändert. Grenze = rechter Rand des sichtbaren Ausschnitts
         * (_sichtbarerBereich, Modell-px) minus 20 px, ab el.x. Jede Zeile
         * (an "\n") für sich; umbrochen wird sie nur, wenn
         *   R1 ihr Text NICHT unverändert als Zeile im bisherigen el.text
         *      vorkommt - bloßes Formatieren bricht nichts um, auch keine
         *      Zeile, die schon vorher über den Rand ragte (z. B. nach A+),
         *   R2 sie in der Schrift des Elements, je Teil fett/kursiv gemessen
         *      (_textMass), über den Rand SELBST ragt (el.x + Breite > Rand) -
         *      eine Zeile, die im 20-px-Randstreifen endet, ist ganz sichtbar
         *      und bleibt; dann auf die Grenze (umbrechenTeile),
         *   R3 die Grenze mindestens 8 × Schrift-px ist - darunter (Element
         *      knapp am oder rechts vom Rand) entstünde eine schmale Spalte
         *      mit zerteilten Wörtern; dann bleibt alles, wie es ist (das
         *      deckt auch Grenze <= 0 ab).
         * Umbrochene Zeilen ersetzen die Zeile (das Leerzeichen an einer
         * Umbruchstelle wird ein "\n", Klartext-Teil); liefert umbrechenTeile
         * nur eine Zeile, bleibt sie unverändert, auch mit Leerzeichen am
         * Ende. Der Betreiber kann F12a bei der Abnahme anders entscheiden -
         * die Regel steht nur hier.
         */
        _textErsetzenUmbrechen: function(inhalt, el) {
            var sichtbar = this._sichtbarerBereich();
            var rand = sichtbar.x + sichtbar.b;
            var grenze = rand - 20 - el.x;
            var px = elementeModul().schriftPx(el.breite);
            if (!(grenze >= 8 * px)) return inhalt; // R3
            var modul = blocktextModul();
            var mass = this._textMass();
            // R1: Zeilen des bisherigen Texts (Präfix: kein Schlüssel wie "__proto__")
            var bisher = Object.create(null);
            var alteZeilen = typeof el.text === 'string' ? el.text.split('\n') : [];
            for (var a = 0; a < alteZeilen.length; a++) bisher['z' + alteZeilen[a]] = true;
            var zeilen = inhalt.text.split('\n');
            var text = '';
            var teile = [];
            var pos = 0;
            var umbrochen = false;
            for (var i = 0; i < zeilen.length; i++) {
                var zeile = zeilen[i];
                var zt = textTeileAusschnitt(inhalt.teile, pos, pos + zeile.length);
                pos += zeile.length + 1;
                if (i > 0) {
                    text += '\n';
                    teile.push({ t: '\n' });
                }
                var stuecke = (zeile === '' || bisher['z' + zeile] ||        // R1
                    el.x + textZeilenBreite(zt, px, mass) <= rand)           // R2
                    ? [] : modul.umbrechenTeile({ text: zeile, teile: zt }, grenze, px, mass);
                if (stuecke.length < 2) {
                    text += zeile;
                    teile = teile.concat(zt);
                    continue;
                }
                umbrochen = true;
                for (var k = 0; k < stuecke.length; k++) {
                    if (k > 0) {
                        text += '\n';
                        teile.push({ t: '\n' });
                    }
                    text += stuecke[k].text;
                    teile = teile.concat(stuecke[k].teile);
                }
            }
            return umbrochen ? { text: text, teile: teile } : inhalt;
        },

        /**
         * Anzeige des Drehmodus auf der Auswahl-Ebene: Drehpunkt (Kreis mit
         * Kreuz), während der Drehung Bezugsstrahl (gestrichelt), Strahl zum
         * Zeiger, Bogen und Gradzahl (↻ im, ↺ gegen den Uhrzeigersinn).
         */
        _drehenZeichnen: function(c) {
            var d = this._drehen;
            if (!d || d.px === null) return;
            var farbe = this._auswahlFarbe();
            var px = d.px, py = d.py;
            c.save();
            c.globalCompositeOperation = 'source-over';
            c.strokeStyle = farbe;
            c.fillStyle = farbe;
            c.lineCap = 'round';
            c.setLineDash([]);
            c.lineWidth = 2;

            if (d.phase === 'drehen') {
                var la = Math.sqrt((d.ax - px) * (d.ax - px) + (d.ay - py) * (d.ay - py));
                var lb = Math.sqrt((d.bx - px) * (d.bx - px) + (d.by - py) * (d.by - py));
                var a0 = Math.atan2(d.ay - py, d.ax - px);
                var rad = d.grad * Math.PI / 180;
                // Bezugsstrahl gestrichelt, Zielstrahl im eingerasteten Winkel
                c.setLineDash([5, 4]);
                c.beginPath();
                c.moveTo(px, py);
                c.lineTo(d.ax, d.ay);
                c.stroke();
                c.setLineDash([]);
                var lz = Math.max(lb, 24);
                c.beginPath();
                c.moveTo(px, py);
                c.lineTo(px + Math.cos(a0 + rad) * lz, py + Math.sin(a0 + rad) * lz);
                c.stroke();
                if (d.grad !== 0 && la > 0) {
                    var rb = Math.max(18, Math.min(48, la, lb));
                    c.beginPath();
                    c.arc(px, py, rb, a0, a0 + rad, d.grad < 0);
                    c.stroke();
                }
                // Gradzahl neben dem Bogen, mit hellem Rand (auf jeder Tafelfarbe lesbar)
                var mitte = a0 + rad / 2;
                var rt = Math.max(18, Math.min(48, la || 48, lb || 48)) + 22;
                var text = Math.abs(d.grad) + '°' + (d.grad > 0 ? ' ↻' : (d.grad < 0 ? ' ↺' : ''));
                c.font = 'bold 15px sans-serif';
                c.textAlign = 'center';
                c.textBaseline = 'middle';
                c.lineWidth = 4;
                c.strokeStyle = '#ffffff';
                c.lineJoin = 'round';
                c.strokeText(text, px + Math.cos(mitte) * rt, py + Math.sin(mitte) * rt);
                c.fillText(text, px + Math.cos(mitte) * rt, py + Math.sin(mitte) * rt);
                c.strokeStyle = farbe;
                c.lineWidth = 2;
            }

            // Drehpunkt: Kreis mit Kreuz und Mittelpunkt
            c.beginPath();
            c.arc(px, py, 7, 0, Math.PI * 2);
            c.stroke();
            c.beginPath();
            c.moveTo(px - 13, py);
            c.lineTo(px - 9, py);
            c.moveTo(px + 9, py);
            c.lineTo(px + 13, py);
            c.moveTo(px, py - 13);
            c.lineTo(px, py - 9);
            c.moveTo(px, py + 9);
            c.lineTo(px, py + 13);
            c.stroke();
            c.beginPath();
            c.arc(px, py, 2.5, 0, Math.PI * 2);
            c.fill();
            c.restore();
        },

        /**
         * Vereinigungsrahmen der Auswahl plus 8 px Rand, {x, y, b, h} in
         * CSS-Pixeln der Zeichenfläche, oder null ohne Auswahl. Das ist der
         * gezeichnete Rahmen und (AP-5.2) die Fläche, in der ein Zeiger das
         * Verschieben beginnt.
         */
        auswahlRahmen: function() {
            if (!this.modell || !this.auswahl || !this.auswahl.length) return null;
            var wahl = {};
            for (var w = 0; w < this.auswahl.length; w++) wahl[this.auswahl[w]] = true;
            var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            var el = this.modell.elemente;
            for (var i = 0; i < el.length; i++) {
                if (!wahl.hasOwnProperty(el[i].id)) continue;
                var r = this._rahmen(el[i]);
                if (r.x < minX) minX = r.x;
                if (r.y < minY) minY = r.y;
                if (r.x + r.b > maxX) maxX = r.x + r.b;
                if (r.y + r.h > maxY) maxY = r.y + r.h;
            }
            if (minX === Infinity) return null; // IDs nicht (mehr) im Modell
            var rand = 8;
            return { x: minX - rand, y: minY - rand, b: (maxX - minX) + 2 * rand, h: (maxY - minY) + 2 * rand };
        },

        /** Akzentfarbe (--color-ui-surface des Themes, Rückfall #e24614). */
        _auswahlFarbe: function() {
            if (this._auswahlFarbeWert) return this._auswahlFarbeWert;
            var farbe = '';
            try {
                farbe = getComputedStyle(this.overlay || document.documentElement)
                    .getPropertyValue('--color-ui-surface').replace(/^\s+|\s+$/g, '');
            } catch (err) {
                farbe = '';
            }
            this._auswahlFarbeWert = farbe || '#e24614';
            return this._auswahlFarbeWert;
        },

        /**
         * Auswahl-Ebene neu zeichnen: laufende Lassolinie ODER Auswahlrahmen,
         * beide gestrichelt; danach die Leiste ausrichten. Die Linie wird je
         * pointermove ganz neu gezeichnet (gestrichelt lässt sich nicht
         * stückweise fortsetzen; ein Lasso hat wenige hundert Punkte).
         */
        _auswahlZeichnen: function() {
            var c = this.auswahlCtx;
            if (c && this.auswahlCanvas) {
                c.clearRect(0, 0, this.auswahlCanvas.width, this.auswahlCanvas.height);
                var lasso = this._lasso;
                var rahmen = lasso ? null : this.auswahlRahmen();
                if ((lasso && lasso.length > 1) || rahmen) {
                    c.save();
                    c.globalCompositeOperation = 'source-over';
                    c.strokeStyle = this._auswahlFarbe();
                    c.lineWidth = 2;
                    c.lineCap = 'butt';
                    c.lineJoin = 'round';
                    c.setLineDash([6, 4]);
                    c.beginPath();
                    if (lasso) {
                        c.moveTo(lasso[0][0], lasso[0][1]);
                        for (var i = 1; i < lasso.length; i++) {
                            c.lineTo(lasso[i][0], lasso[i][1]);
                        }
                    } else {
                        // In die Fläche geklemmt, damit der Rahmen auch bei
                        // einem flächenfüllenden Bild-Element (alte Tafel)
                        // sichtbar bleibt
                        var bw = this.cssWidth || 0, bh = this.cssHeight || 0;
                        var x0 = Math.max(rahmen.x, 1), y0 = Math.max(rahmen.y, 1);
                        var x1 = bw ? Math.min(rahmen.x + rahmen.b, bw - 1) : rahmen.x + rahmen.b;
                        var y1 = bh ? Math.min(rahmen.y + rahmen.h, bh - 1) : rahmen.y + rahmen.h;
                        if (x1 > x0 && y1 > y0) {
                            c.rect(x0, y0, x1 - x0, y1 - y0);
                        }
                    }
                    c.stroke();
                    c.restore();
                }
                this._drehenZeichnen(c); // 3.1.143
            }
            this._auswahlLeisteAusrichten();
        },

        /**
         * Leiste über dem Auswahlrahmen einblenden (ohne Auswahl bzw. beim
         * Lasso-Ziehen ausblenden). Die Leiste liegt im Overlay, nicht im
         * gezoomten Container: Lage aus den Bildschirmrechtecken, umgerechnet
         * in Overlay-Koordinaten (skala fängt die Einblend-Animation
         * scale(0.98) ab). Oberhalb des Rahmens, sonst darunter, sonst oben
         * in der Zeichenfläche; waagrecht in der Zeichenfläche gehalten.
         */
        _auswahlLeisteAusrichten: function() {
            var leiste = this._auswahlLeiste;
            if (!leiste) return;
            // 3.1.143: Im Drehmodus nur Hinweis + "Abbrechen" (board-mode.css);
            // ausgeblendet, solange der Drehpunkt gezogen oder gedreht wird
            var d = this._drehen;
            leiste.classList.toggle('cbd-board-auswahl-leiste--drehen', !!d);
            var hinweis = leiste.querySelector('.cbd-board-auswahl-hinweis');
            if (hinweis) {
                hinweis.textContent = !d ? '' : (d.phase === 'drehpunkt'
                    ? 'Drehpunkt antippen'
                    : 'Bezugspunkt antippen und ziehen');
            }
            var dreht = !!d && (d.phase === 'drehen' || d.punktZieht);
            // AP-5.2: auch während des Verschiebens ausgeblendet
            var r = (!this._lasso && !this._ziehen && !dreht && this.overlay && this.drawingCanvas && this.cssWidth && this.cssHeight)
                ? this.auswahlRahmen() : null;
            if (!r) {
                leiste.style.display = 'none';
                return;
            }
            leiste.style.display = 'flex';
            // 3.1.144: Textgröße nur bei gewählten Texten (vor dem Messen der
            // Leistenbreite unten); Knopf gesperrt, wenn kein Text mehr wachsen
            // bzw. schrumpfen kann
            var E = elementeModul();
            var tg = E.textGroessen(this.modell, this.auswahl);
            leiste.classList.toggle('cbd-board-auswahl-leiste--text', tg.anzahl > 0);
            if (tg.anzahl > 0) {
                var anzeige = leiste.querySelector('.cbd-board-auswahl-textgroesse');
                var kleiner = leiste.querySelector('[data-aktion="text-kleiner"]');
                var groesser = leiste.querySelector('[data-aktion="text-groesser"]');
                if (anzeige) {
                    anzeige.textContent = (tg.min === tg.max ? tg.min : tg.min + '–' + tg.max) + ' px';
                }
                if (kleiner) kleiner.disabled = tg.max <= E.TEXT_PX_MIN;
                if (groesser) groesser.disabled = tg.min >= E.TEXT_PX_MAX;
            }
            // PLAN-Blocktext-Formatierung.md, AP-3.1 (F11): "Text bearbeiten"
            // nur bei genau EINEM gewählten Text, nie im Drehmodus (ebenfalls
            // vor dem Messen der Leistenbreite unten)
            leiste.classList.toggle('cbd-board-auswahl-leiste--ein-text', !d && this._einGewaehlterText() !== null);
            var ov = this.overlay.getBoundingClientRect();
            var skala = this.overlay.offsetWidth ? ov.width / this.overlay.offsetWidth : 1;
            if (!skala) skala = 1;
            var cv = this.drawingCanvas.getBoundingClientRect();
            var fx = cv.width / this.cssWidth, fy = cv.height / this.cssHeight;
            var bereich = this.overlay.querySelector('.cbd-board-canvas-area');
            var ba = bereich ? bereich.getBoundingClientRect() : cv;
            var lx = function(cx) { return (cx - ov.left) / skala; };
            var ly = function(cy) { return (cy - ov.top) / skala; };
            var links = lx(cv.left + r.x * fx);
            var oben = ly(cv.top + r.y * fy);
            var unten = ly(cv.top + (r.y + r.h) * fy);
            var bL = lx(ba.left), bR = lx(ba.right), bO = ly(ba.top), bU = ly(ba.bottom);
            // 3.1.144: nie breiter als die Zeichenfläche - sonst bricht die
            // Leiste in eine zweite Zeile um (flex-wrap, board-mode.css),
            // statt rechts über den Rand zu ragen (schmale Anzeigen, iPad hoch)
            leiste.style.maxWidth = this._auswahlLeisteMaxBreite(bR - bL) + 'px';
            var lb = leiste.offsetWidth, lh = leiste.offsetHeight, abstand = 6;
            var top = oben - abstand - lh;
            if (top < bO + 4) {
                top = unten + abstand;
                if (top + lh > bU - 4) top = bO + 4;
            }
            var left = Math.min(Math.max(links, bL + 4), bR - 4 - lb);
            if (left < bL + 4) left = bL + 4;
            leiste.style.left = Math.round(left) + 'px';
            leiste.style.top = Math.round(top) + 'px';
        },

        /** Höchstbreite der Auswahlleiste bei gegebener Flächenbreite (Overlay-px). */
        _auswahlLeisteMaxBreite: function(flaechenBreite) {
            return Math.max(160, Math.floor(flaechenBreite - 8));
        },

        /**
         * Höhe der Auswahlleiste, wie sie bei gewählten Texten erscheint (mit
         * A−/A+, auf die Breite der Zeichenfläche begrenzt), in Overlay-px;
         * 0 ohne Leiste. Misst unsichtbar und stellt danach alles wieder her
         * (Folge-AP G9, für _blocktextVerteilen).
         * Seit PLAN-Blocktext-Formatierung.md, AP-3.1 misst sie konservativ
         * auch mit dem Knopf "Text bearbeiten" (Klasse --ein-text, erscheint
         * bei genau einem gewählten Text): Die Leiste kann so höchstens
         * niedriger sein als gemessen, nie höher - auf schmalen Anzeigen
         * bricht sie mit dem Knopf früher in eine weitere Zeile um.
         * AP-3.fix1: mitEinText wählt den Zustand - true (und ohne Argument,
         * wie seit AP-3.1) mit dem Knopf, false ohne ihn, so wie die Leiste
         * bei zwei oder mehr gewählten Texten steht. _blocktextVerteilen()
         * nimmt, was nach dem Einfügen wirklich gilt: ein Block gewählt =
         * mit Knopf, mehrere = ohne.
         */
        _auswahlLeisteHoeheText: function(mitEinText) {
            var leiste = this._auswahlLeiste;
            var bereich = this.overlay ? this.overlay.querySelector('.cbd-board-canvas-area') : null;
            if (!leiste || !bereich) return 0;
            var ov = this.overlay.getBoundingClientRect();
            var skala = this.overlay.offsetWidth ? ov.width / this.overlay.offsetWidth : 1;
            if (!skala) skala = 1;
            var anzeige = leiste.querySelector('.cbd-board-auswahl-textgroesse');
            var vorher = {
                display: leiste.style.display,
                visibility: leiste.style.visibility,
                maxWidth: leiste.style.maxWidth,
                klassen: leiste.className,
                anzeige: anzeige ? anzeige.textContent : ''
            };
            leiste.style.visibility = 'hidden';
            leiste.style.display = 'flex';
            leiste.classList.add('cbd-board-auswahl-leiste--text');
            // AP-3.1; seit AP-3.fix1 nur noch, wenn die Leiste den Knopf haben soll
            leiste.classList.toggle('cbd-board-auswahl-leiste--ein-text', mitEinText !== false);
            leiste.classList.remove('cbd-board-auswahl-leiste--drehen');
            leiste.style.maxWidth = this._auswahlLeisteMaxBreite(bereich.getBoundingClientRect().width / skala) + 'px';
            if (anzeige) anzeige.textContent = '200 px'; // breiteste Anzeige
            var hoehe = leiste.offsetHeight;
            leiste.style.display = vorher.display;
            leiste.style.visibility = vorher.visibility;
            leiste.style.maxWidth = vorher.maxWidth;
            leiste.className = vorher.klassen;
            if (anzeige) anzeige.textContent = vorher.anzeige;
            return hoehe;
        },

        onKeyDown: function(e) {
            // Fenster "Text auf die Tafel" offen (AP-2.3): Tasten gehören
            // dem Fenster, auch wenn der Fokus es per Tab verlassen hat.
            // Das Fenster selbst reicht nichts durch (stopPropagation).
            if (this._blocktextFenster) {
                return;
            }
            if (e.key === 'Escape') {
                // 3.1.143: im Drehmodus beendet ESC zuerst nur den Drehmodus
                // (laufende Drehung zurück), die Auswahl bleibt
                if (this._drehen) {
                    e.preventDefault();
                    this._drehenAbbrechen();
                    return;
                }
                // Lasso (AP-5.1): Bei aktiver Auswahl oder laufender
                // Lassolinie hebt ESC nur die Auswahl auf - die Tafel bleibt
                // offen. Ohne Auswahl schließt ESC wie bisher.
                if (this._lasso || (this.auswahl && this.auswahl.length)) {
                    e.preventDefault();
                    this.auswahlAufheben();
                    return;
                }
                this.close();
            } else if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
                e.preventDefault();
                this.undo();
            } else if ((e.ctrlKey || e.metaKey) && (e.key === 'd' || e.key === 'D') &&
                       this.auswahl && this.auswahl.length) {
                // 3.1.143: Strg+D dupliziert die Auswahl (statt Lesezeichen),
                // nicht aus Eingabefeldern heraus
                if (this._istEingabefeld(e.target) || this._istEingabefeld(document.activeElement)) {
                    return;
                }
                e.preventDefault();
                this.auswahlDuplizieren();
            } else if ((e.key === 'Delete' || e.key === 'Backspace') &&
                       this.auswahl && this.auswahl.length) {
                // AP-5.2: Entf/Rücktaste löschen die Auswahl - aber nie aus
                // einem Eingabefeld heraus (Textfeld des Text-Werkzeugs,
                // Regler): dort löschen sie Zeichen. Das Textfeld reicht seine
                // Tasten ohnehin nicht durch (stopPropagation in
                // openTextInput); geprüft werden Ziel UND Fokus.
                if (this._istEingabefeld(e.target) || this._istEingabefeld(document.activeElement)) {
                    return;
                }
                e.preventDefault(); // Rücktaste: keine Browser-Navigation
                this.auswahlLoeschen();
            }
        },

        // =============================================
        // Zeichnungs-Persistenz (Dispatcher)
        // =============================================

        loadDrawing: function() {
            var pageContainerId = this.getPageContainerId(this.currentPageIndex);
            if (this.classId && this.ajaxUrl) {
                this.loadFromServer(pageContainerId);
            } else {
                this.loadFromCache('cbd-board-' + pageContainerId);
            }
        },

        saveDrawing: function() {
            var pageContainerId = this.getPageContainerId(this.currentPageIndex);
            if (this.classId && this.ajaxUrl) {
                this.saveToServer(pageContainerId);
            } else {
                this.saveToCache('cbd-board-' + pageContainerId);
            }
        },

        // =============================================
        // Server-Persistenz
        // =============================================

        loadFromServer: function(pageContainerId) {
            // Klassen-Modus: NIEMALS lokalen Cache verwenden
            if (!this.classId || !this.ajaxUrl || !this.stableContainerId) {
                console.warn('[CBD Board Mode] loadFromServer: Fehlende Parameter, Abbruch.');
                return;
            }

            var self = this;
            this._setSaveStatus('Lade...');

            // Begleitschlüssel für den PDF-Export: Schlüsselname UND Wert JETZT festhalten,
            // nicht erst im .then()-Zweig. close() setzt this.classId/this.stableContainerId
            // synchron auf null zurück, während die Anfrage noch läuft – ein späteres
            // Auslesen ergäbe die Zeichenkette "null". Dieselbe Vorsichtsmaßnahme wie beim
            // bestehenden bgcolor-Begleitschlüssel, der ebenfalls vor dem fetch gebildet wird.
            var classIdKey = 'cbd-board-' + (pageContainerId || this.stableContainerId) + '-classid';
            var classIdWert = String(this.classId);

            // AP-4.4: Antwort nur übernehmen, wenn inzwischen weder die Seite
            // gewechselt noch die Tafel geschlossen/neu geöffnet wurde - sonst
            // landete die Zeichnung der alten Seite auf der neuen.
            var ladeNr = this._ladeNr;

            var formData = new FormData();
            formData.append('action', 'cbd_load_drawing');
            formData.append('nonce', this.nonce);
            formData.append('class_id', this.classId);
            formData.append('page_id', this.pageId);
            formData.append('container_id', pageContainerId || this.stableContainerId);

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!self.overlay || self._ladeNr !== ladeNr) {
                    return; // veraltete Antwort (Seitenwechsel, Schließen)
                }
                if (data.success && data.data.drawing_data) {
                    // Gespeicherte Hintergrundfarbe wiederherstellen (browser-spezifisch)
                    try {
                        var bgColorKey = 'cbd-board-' + (pageContainerId || self.stableContainerId) + '-bgcolor';
                        var savedBgColor = localStorage.getItem(bgColorKey);
                        if (savedBgColor) {
                            self.setBoardColor(savedBgColor);
                        }
                    } catch (e) { /* Ignorieren */ }

                    // Klassen-Zuordnung als Begleitschlüssel merken (für den PDF-Export)
                    try {
                        localStorage.setItem(classIdKey, classIdWert);
                    } catch (e) { /* Ignorieren, wie beim bestehenden bgcolor-Begleitschlüssel */ }

                    // AP-4.4: Elementliste bevorzugt (String oder null, AP-4.2);
                    // gestreckt auf die aktuelle Fläche (E13)
                    var geladen = self._elementeLesen(data.data.elemente_data);
                    if (geladen) {
                        self._elementeUebernehmen(geladen);
                        self._setSaveStatus('Geladen');
                        setTimeout(function() { self._setSaveStatus(''); }, 2000);
                        return;
                    }

                    // Rückfall (alte Tafel ohne Elemente, ungültige Liste):
                    // das PNG wird EIN Bild-Element (E10)
                    var img = new Image();
                    img.onload = function() {
                        if (self.modell) self.zeichneModell();
                        self._setSaveStatus('Geladen');
                        setTimeout(function() { self._setSaveStatus(''); }, 2000);
                    };
                    img.src = data.data.drawing_data;
                    self.bildElementHinzufuegen(data.data.drawing_data, img);
                } else {
                    // Keine Zeichnung auf Server → leerer Canvas (kein Cache-Fallback!)
                    self._setSaveStatus('');
                }
            })
            .catch(function(err) {
                console.warn('[CBD Board Mode] Server-Laden fehlgeschlagen:', err);
                self._setSaveStatus('Server-Fehler');
                // Kein Fallback auf lokalen Cache im Klassen-Modus
            });
        },

        saveToServer: function(pageContainerId) {
            // Klassen-Modus: NIEMALS lokalen Cache verwenden
            if (!this.classId || !this.ajaxUrl || !this.stableContainerId) {
                console.warn('[CBD Board Mode] saveToServer: Fehlende Parameter, Abbruch.');
                return;
            }
            if (this.isSaving) {
                // Bereits am Speichern – kein Cache-Fallback
                return;
            }

            this.isSaving = true;
            this._setSaveStatus('Speichert...');

            var self = this;

            // Leeren Canvas erkennen: keine Elemente oder alle Pixel transparent
            // -> keine Zeichnung vorhanden (AP-4.3, _istLeer)
            var isBlank = this._istLeer();
            var dataUrl = isBlank ? '' : this.drawingCanvas.toDataURL('image/png');

            // Hintergrundfarbe lokal speichern (browser-spezifische Präferenz)
            try {
                var bgColorKey = 'cbd-board-' + (pageContainerId || this.stableContainerId) + '-bgcolor';
                if (isBlank) {
                    localStorage.removeItem(bgColorKey);
                } else if (this.boardColor) {
                    localStorage.setItem(bgColorKey, this.boardColor);
                }
            } catch (e) { /* Ignorieren */ }

            // Begleitschlüssel für den PDF-Export: Schlüsselname UND Wert JETZT festhalten,
            // nicht erst im .then()-Zweig. close() ruft saveDrawing() auf und setzt
            // this.classId/this.stableContainerId unmittelbar danach synchron auf null –
            // die Antwort trifft erst später ein, ein Auslesen dort ergäbe die Zeichenkette
            // "null" statt der Klassen-ID. Gemessen beim Live-Test zu AP-2.2.
            var classIdKey = 'cbd-board-' + (pageContainerId || this.stableContainerId) + '-classid';
            var classIdWert = String(this.classId);

            var formData = new FormData();
            formData.append('action', 'cbd_save_drawing');
            formData.append('nonce', this.nonce);
            formData.append('class_id', this.classId);
            formData.append('page_id', this.pageId);
            formData.append('container_id', pageContainerId || this.stableContainerId);
            formData.append('drawing_data', dataUrl);
            // AP-4.4: Elementliste zusätzlich zum PNG (leere Tafel: '' ->
            // Server setzt elemente_data auf NULL). Das PNG bleibt die
            // maßgebliche Darstellung für Schüler, Puls und PDF (E7).
            // AP-4.fix1 (F1): Über ELEMENTE_MAX_PUNKTE gar keine Liste senden
            // (spart Upload und Serverlast); '' setzt die Spalte auf NULL,
            // gemeldet wird "Nur als Bild gespeichert".
            var elementeText = isBlank ? '' : this._elementeSerialisiert();
            var elementeZuGross = elementeText !== '' && klammerZahl(elementeText) > ELEMENTE_MAX_PUNKTE;
            if (elementeZuGross) {
                elementeText = '';
            }
            formData.append('elemente_data', elementeText);

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                self.isSaving = false;
                if (data.success) {
                    // elemente_verworfen (AP-4.2): Liste zu groß/unlesbar oder
                    // Spalte fehlt - gespeichert wurde nur das PNG; ebenso,
                    // wenn der Browser die Liste gar nicht erst gesendet hat
                    // (AP-4.fix1)
                    self._setSaveStatus((elementeZuGross || (data.data && data.data.elemente_verworfen === true)) ? NUR_BILD_TEXT : 'Gespeichert');

                    // Klassen-Zuordnung als Begleitschlüssel merken (für den PDF-Export).
                    // Bewusst AUCH bei leerem Canvas (isBlank) gesetzt: Der Export soll
                    // wissen, welcher Klasse dieser Container zuletzt zugeordnet war;
                    // die Bulk-Abfrage liefert für einen gelöschten Eintrag ohnehin nichts.
                    try {
                        localStorage.setItem(classIdKey, classIdWert);
                    } catch (e) { /* Ignorieren, wie beim bestehenden bgcolor-Begleitschlüssel */ }

                    // Einmalig als "behandelt" markieren wenn noch nicht geschehen
                    if (!self.isBehandeltSet) {
                        self.isBehandeltSet = true;
                        self.setBehandeltOnServer();
                    }
                } else {
                    console.warn('[CBD Board Mode] Server-Speichern fehlgeschlagen:', data);
                    self._setSaveStatus('Server-Fehler');
                    // Kein Fallback auf lokalen Cache im Klassen-Modus
                }
            })
            .catch(function(err) {
                self.isSaving = false;
                console.warn('[CBD Board Mode] Server-Speichern fehlgeschlagen:', err);
                self._setSaveStatus('Server-Fehler');
                // Kein Fallback auf lokalen Cache im Klassen-Modus
            });
        },

        _setSaveStatus: function(text) {
            var el = document.getElementById('cbd-board-save-status');
            if (el) {
                el.textContent = text;
            }
        },

        /**
         * Block als "behandelt" markieren (einmalig pro Session, fire-and-forget)
         */
        setBehandeltOnServer: function() {
            if (!this.classId || !this.ajaxUrl || !this.stableContainerId) return;

            var formData = new FormData();
            formData.append('action', 'cbd_set_behandelt');
            formData.append('nonce', this.nonce);
            formData.append('class_id', this.classId);
            formData.append('page_id', this.pageId);
            formData.append('container_id', this.stableContainerId);

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.success) {
                    console.warn('[CBD Board Mode] Behandelt-Status konnte nicht gesetzt werden:', data);
                }
            })
            .catch(function(err) {
                console.warn('[CBD Board Mode] Behandelt-Status konnte nicht gesetzt werden:', err);
            });
        },

        // =============================================
        // localStorage Persistenz
        // =============================================

        saveToCache: function(cacheKey) {
            if (!this.drawingCanvas || !this.containerId) return;

            try {
                var key = cacheKey || ('cbd-board-' + this.containerId);

                // Leeren Canvas erkennen: keine Elemente oder alle Pixel
                // transparent (AP-4.3, _istLeer)
                var isBlank = this._istLeer();

                if (isBlank) {
                    localStorage.removeItem(key);
                    localStorage.removeItem(key + '-bgcolor');
                    localStorage.removeItem(key + '-elemente');
                } else {
                    // Reihenfolge: erst das PNG (maßgeblich, E7), dann die Elemente
                    var png = this.drawingCanvas.toDataURL('image/png');
                    if (!this._pngLokalSchreiben(key, png)) {
                        // Endgültig kein Platz (AP-4.fix3): sichtbar melden,
                        // der bisherige Stand unter `key` bleibt unverändert.
                        this._setSaveStatus(SPEICHER_VOLL_TEXT);
                        return;
                    }
                    if (this.boardColor) {
                        try {
                            localStorage.setItem(key + '-bgcolor', this.boardColor);
                        } catch (eFarbe) { /* Tafelfarbe ist entbehrlich */ }
                    }
                    this._elementeLokalSpeichern(key, png);
                }
            } catch (e) {
                console.warn('[CBD Board Mode] Zeichnung konnte nicht gespeichert werden:', e.message);
            }
        },

        /**
         * PNG lokal schreiben; ist der Speicher voll, zuerst Platz schaffen
         * (AP-4.fix3, Befund F3 aus AP-4.rev). Die -elemente-Schlüssel sind
         * entbehrlich - das PNG ist maßgeblich (E7), eine Notiz ohne Liste
         * öffnet als EIN Bild-Element (E10) -, belegen aber bis zum Doppelten
         * des Platzes (ein Bild-Element trägt das alte PNG ein zweites Mal).
         * Reihenfolge: 1. Listen ANDERER Notizen/Seiten entfernen, erneut
         * versuchen; 2. die eigene (alte) Liste entfernen, erneut versuchen.
         * Nie werden PNGs anderer Notizen angetastet.
         *
         * @return {boolean} true = PNG geschrieben
         */
        _pngLokalSchreiben: function(key, png) {
            try {
                localStorage.setItem(key, png);
                return true;
            } catch (e) { /* Platz schaffen, s. u. */ }

            var eigen = key + '-elemente';
            var fremde = [];
            try {
                for (var i = 0; i < localStorage.length; i++) {
                    var k = localStorage.key(i);
                    if (istTafelSchluessel(k) && k !== eigen && k.length > 9 && k.slice(-9) === '-elemente') {
                        fremde.push(k);
                    }
                }
            } catch (eListe) { /* Speicher nicht lesbar */ }

            var stufen = [fremde, [eigen]];
            for (var s = 0; s < stufen.length; s++) {
                if (!stufen[s].length) continue;
                for (var j = 0; j < stufen[s].length; j++) {
                    try { localStorage.removeItem(stufen[s][j]); } catch (eWeg) { /* weiter */ }
                }
                console.warn('[CBD Board Mode] Speicher voll - Elementlisten entfernt (Notizen bleiben als Bild erhalten):', stufen[s].length);
                try {
                    localStorage.setItem(key, png);
                    return true;
                } catch (eNochmal) { /* nächste Stufe */ }
            }
            console.warn('[CBD Board Mode] Zeichnung konnte nicht gespeichert werden: Speicher voll');
            return false;
        },

        /**
         * Begleitschlüssel <PNG-Schlüssel>-elemente schreiben (E9, AP-4.4).
         * Scheitert setItem (Quota), bleibt das schon geschriebene PNG, der
         * Elementschlüssel wird entfernt (ein alter Stand darf das neue PNG
         * beim nächsten Öffnen nicht überdecken) und die Statusanzeige meldet
         * 4 s lang "Nur als Bild gespeichert".
         *
         * AP-4.fix2 (F2): Der Wert ist eine Hülle {"png":"<Abdruck des eben
         * geschriebenen PNG>","modell":<Modell v1>} (CBDBoardElemente.verpacke).
         * Überschreibt eine ältere Plugin-Version (Rückweg) nur das PNG, passt
         * der Abdruck nicht mehr und loadFromCache() nimmt das PNG.
         */
        _elementeLokalSpeichern: function(key, png) {
            var E = elementeModul();
            var text = E ? E.verpacke(png, this._elementeSerialisiert()) : '';
            try {
                if (!text) {
                    localStorage.removeItem(key + '-elemente');
                    return;
                }
                localStorage.setItem(key + '-elemente', text);
            } catch (e) {
                try { localStorage.removeItem(key + '-elemente'); } catch (e2) { /* Ignorieren */ }
                console.warn('[CBD Board Mode] Elemente nicht gespeichert, nur das Bild:', e && e.message);
                var self = this;
                this._setSaveStatus(NUR_BILD_TEXT);
                setTimeout(function() {
                    var s = document.getElementById('cbd-board-save-status');
                    if (s && s.textContent === NUR_BILD_TEXT) self._setSaveStatus('');
                }, 4000);
            }
        },

        /**
         * Elementmodell mit den AKTUELLEN CSS-Maßen der Zeichenfläche als
         * JSON (AP-4.4). Das Modell behält die Maße vom Öffnen (Koordinaten
         * bleiben bei einer Größenänderung 1:1), gespeichert wird aber, wie
         * groß die Fläche jetzt ist - daran streckt das nächste Laden (E13).
         * Leeres Modell oder Fehler -> '' (dann gilt nur das PNG).
         */
        _elementeSerialisiert: function() {
            if (!this.modell || !this.modell.elemente.length || !elementeModul()) return '';
            try {
                return elementeModul().serialisiere({
                    v: this.modell.v,
                    breite: this.cssWidth || this.modell.breite,
                    hoehe: this.cssHeight || this.modell.hoehe,
                    elemente: this.modell.elemente
                });
            } catch (e) {
                console.warn('[CBD Board Mode] Elemente nicht serialisierbar:', e && e.message);
                return '';
            }
        },

        /**
         * Gespeicherte Elementliste (Server-Feld elemente_data bzw. lokaler
         * -elemente-Schlüssel) prüfen und auf die aktuelle Fläche strecken
         * (E13). null, wenn nichts Brauchbares da ist - dann lädt der
         * Aufrufer das PNG als EIN Bild-Element (E10). Auch wenn die Prüfung
         * einzelne Elemente verworfen hat, gilt das PNG: Es ist maßgeblich
         * (E7) und vollständig, die Liste wäre es nicht.
         */
        _elementeLesen: function(text) {
            var E = elementeModul();
            if (!E || typeof text !== 'string' || text === '') return null;
            var geprueft = E.deserialisiere(text);
            if (!geprueft || geprueft.verworfen > 0 || !geprueft.modell.elemente.length) return null;
            return E.skaliere(geprueft.modell, this.cssWidth || geprueft.modell.breite,
                this.cssHeight || geprueft.modell.hoehe);
        },

        /**
         * Geladenes Modell übernehmen. Normalfall: Es ersetzt das leere Modell
         * (neueId() gleicht den Zähler selbst ab), Undo-Stapel bleibt leer.
         * Wurde schon gezeichnet, bevor die Antwort kam (Server-Laden ist
         * asynchron), kommen die geladenen Elemente VOR die Sitzungs-Elemente
         * und - weil Laden keine Nutzeraktion ist - auch in alle Undo-Stände
         * (wie bildElementHinzufuegen()); sie bekommen neue IDs, damit sie
         * nicht mit den Sitzungs-IDs kollidieren.
         */
        _elementeUebernehmen: function(geladen) {
            if (!this.modell) return;
            if (!this.modell.elemente.length && !this.undoStapel.length && !this.currentStroke && !this._ziehen &&
                !(this._drehen && this._drehen.basis)) {
                this.modell = geladen;
                this.zeichneModell();
                return;
            }
            var self = this;
            var vorn = geladen.elemente.map(function(el) {
                var k = elementKopie(el);
                k.id = self.neueId();
                return k;
            });
            this._geladeneVornEinfuegen(vorn); // pflegt auch _ziehen.basis (AP-5.fix1)
            this.zeichneModell();
        },

        loadFromCache: function(cacheKey) {
            if (!this.drawingCanvas || !this.drawingCtx || !this.containerId) return;

            try {
                var key = cacheKey || ('cbd-board-' + this.containerId);
                var dataUrl = localStorage.getItem(key);
                var savedBgColor = localStorage.getItem(key + '-bgcolor');

                if (savedBgColor) {
                    this.setBoardColor(savedBgColor);
                }

                if (!dataUrl) {
                    // Kein gespeicherter Stand. Ein -elemente-Schlüssel ohne
                    // PNG ist verwaist (z. B. von einer älteren Plugin-Version
                    // als leer gespeichert) und wird bewusst nicht geladen.
                    return;
                }

                // AP-4.4: zuerst die Elementliste (E9), gestreckt auf die
                // aktuelle Fläche (E13). AP-4.fix2 (F2): nur, wenn der Abdruck
                // in der Hülle zu diesem PNG passt - sonst ist die Liste
                // veraltet (Rückweg auf eine ältere Version) oder stammt aus
                // einem Stand ohne Abdruck, und das PNG gilt (E7).
                var E = elementeModul();
                var geladen = E ? this._elementeLesen(E.entpacke(localStorage.getItem(key + '-elemente'), dataUrl)) : null;
                if (geladen) {
                    this._elementeUebernehmen(geladen);
                    return;
                }

                // Rückfall (ältere Notiz ohne Elemente, ungültige Liste): das
                // PNG wird EIN Bild-Element (E10); es wird sofort eingefügt und
                // nach dem Laden gezeichnet.
                this.bildElementHinzufuegen(dataUrl);
            } catch (e) {
                console.warn('[CBD Board Mode] Zeichnung konnte nicht geladen werden:', e.message);
            }
        },

        removeFromCache: function() {
            if (!this.containerId) return;

            try {
                var key = 'cbd-board-' + this.containerId;
                localStorage.removeItem(key);
                localStorage.removeItem(key + '-bgcolor');
                localStorage.removeItem(key + '-elemente'); // AP-4.4
            } catch (e) {
                // Ignorieren
            }
        },

        // =============================================
        // Multi-Seiten-System
        // =============================================

        /**
         * Container-ID für eine bestimmte Seite
         * Seite 0: normale ID (rückwärtskompatibel)
         * Seite N: ID + ':pN'
         */
        getPageContainerId: function(index) {
            var baseId = this.stableContainerId || this.containerId;
            return index === 0 ? baseId : baseId + ':p' + index;
        },

        /**
         * Seitenanzahl aus localStorage laden und UI initialisieren
         */
        initPages: function() {
            var baseId = this.stableContainerId || this.containerId;
            try {
                var stored = localStorage.getItem('cbd-board-pagecount-' + baseId);
                this.totalPages = stored ? Math.max(1, parseInt(stored, 10)) : 1;
            } catch (e) {
                this.totalPages = 1;
            }
            this.updatePageNavUI();
        },

        /**
         * Zu einer anderen Seite navigieren
         */
        goToPage: function(index) {
            if (index < 0 || index >= this.totalPages) return;
            if (index === this.currentPageIndex) return;

            // Aktuelle Seite in den In-Memory-Cache: {png, modell} (leere Seite: '')
            this.pageCache[this.currentPageIndex] = this._seiteSichern();

            // Aktuelle Seite asynchron auf Server/Cache speichern
            var fromPageContainerId = this.getPageContainerId(this.currentPageIndex);
            if (this.classId && this.ajaxUrl) {
                this.saveToServer(fromPageContainerId);
            } else {
                this.saveToCache('cbd-board-' + fromPageContainerId);
            }

            // Zur neuen Seite wechseln
            this.currentPageIndex = index;
            this.updatePageNavUI();

            // Modell der neuen Seite: leer, Undo-Stapel geleert (Rückgängig
            // wirkt nur innerhalb einer Seite)
            this._seiteLeeren();

            // Neue Seite aus dem Seitencache (mit ihren Elementen, AP-4.4) oder
            // - noch nicht besucht - von Server/localStorage laden (Modell oder
            // PNG als Bild-Element)
            if (this.pageCache[index] !== undefined && this.pageCache[index] !== null) {
                var cached = this.pageCache[index];
                if (cached && cached.modell) {
                    this.modell = {
                        v: cached.modell.v,
                        breite: cached.modell.breite,
                        hoehe: cached.modell.hoehe,
                        elemente: cached.modell.elemente.slice()
                    };
                    this.zeichneModell();
                }
            } else {
                this.loadDrawing();
            }
        },

        /**
         * Neue leere Seite hinzufügen
         */
        addPage: function() {
            // Aktuelle Seite sichern: {png, modell} (leere Seite: '')
            this.pageCache[this.currentPageIndex] = this._seiteSichern();
            var fromPageContainerId = this.getPageContainerId(this.currentPageIndex);
            if (this.classId && this.ajaxUrl) {
                this.saveToServer(fromPageContainerId);
            } else {
                this.saveToCache('cbd-board-' + fromPageContainerId);
            }

            // Neue Seite anlegen
            this.totalPages++;
            var baseId = this.stableContainerId || this.containerId;
            try { localStorage.setItem('cbd-board-pagecount-' + baseId, this.totalPages); } catch (e) {}

            // Zur neuen Seite wechseln (leer)
            this.currentPageIndex = this.totalPages - 1;
            this.pageCache[this.currentPageIndex] = null; // Explizit als leer markieren
            this._seiteLeeren();
            this.updatePageNavUI();
        },

        /**
         * PNG der aktuellen Seite für den Seiten-Cache; eine Seite ohne
         * Elemente liefert '' (nichts zurückzuholen - früher ein leeres PNG,
         * das beim Zurückkehren als Basis-Bild bzw. jetzt als unsichtbares
         * Bild-Element geladen worden wäre).
         */
        _seitenPng: function() {
            if (!this.modell || this.modell.elemente.length === 0) return '';
            return this.drawingCanvas.toDataURL('image/png');
        },

        /**
         * Eintrag des Seitencaches (AP-4.4): {png, modell} - das Modell mit
         * einer eigenen Elementliste (Elemente selbst sind unveränderlich),
         * damit die Seite beim Zurückkehren Element für Element bearbeitbar
         * bleibt. Leere Seite -> ''.
         */
        _seiteSichern: function() {
            var png = this._seitenPng();
            if (!png) return '';
            return {
                png: png,
                modell: {
                    v: this.modell.v,
                    breite: this.modell.breite,
                    hoehe: this.modell.hoehe,
                    elemente: this.modell.elemente.slice()
                }
            };
        },

        /** Modell und Undo-Stapel beim Seitenwechsel neu anlegen, Ebene leeren. */
        _seiteLeeren: function() {
            this._ladeNr++; // laufende Server-Antworten gehören zur alten Seite
            this.auswahlAufheben(); // AP-5.1: Auswahl gehört zur verlassenen Seite
            this.currentStroke = null;
            this._radierBilder = null;
            this._radierLetzter = null;
            this.undoStapel = [];
            this.modell = this._leeresModell();
            this.drawingCtx.clearRect(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);
        },

        /**
         * Seiten-Navigations-UI aktualisieren
         */
        updatePageNavUI: function() {
            if (!this.overlay) return;
            var prevBtn = this.overlay.querySelector('.cbd-board-page-prev');
            var nextBtn = this.overlay.querySelector('.cbd-board-page-next');
            var indicator = this.overlay.querySelector('.cbd-board-page-indicator');
            if (prevBtn) prevBtn.disabled = this.currentPageIndex <= 0;
            if (nextBtn) nextBtn.disabled = this.currentPageIndex >= this.totalPages - 1;
            if (indicator) indicator.textContent = (this.currentPageIndex + 1) + ' / ' + this.totalPages;
        },

        // =============================================
        // Undo-System
        // =============================================

        /**
         * Letzten Schritt rückgängig machen (Strg+Z, E12): stellt den Stand
         * vor dem letzten Strich, Text, Radiervorgang oder "Zeichnung löschen"
         * wieder her (Momentaufnahme aus merkeZustand()). Leerer Stapel ->
         * nichts.
         */
        undo: function() {
            // AP-5.1: Strg+Z hebt eine Auswahl immer auf (auch bei leerem
            // Stapel) - nach dem Zurücksetzen passten die IDs nicht mehr
            this.auswahlAufheben();
            if (!this.modell || !this.undoStapel.length) return;
            this.modell.elemente = this.undoStapel.pop();
            this.zeichneModell();
        },

        // =============================================
        // Zoom & Pan
        // =============================================

        /**
         * CSS-Transform auf Canvas-Container anwenden
         */
        updateCanvasTransform: function() {
            if (!this.overlay) return;
            var container = this.overlay.querySelector('.cbd-board-canvas-container');
            if (container) {
                container.style.transform = 'translate(' + this.panX + 'px, ' + this.panY + 'px) scale(' + this.zoom + ')';
            }
            // AP-5.1: Der Rahmen zoomt mit (Ebene im Container), die Leiste nicht
            this._auswahlLeisteAusrichten();
        },

        /**
         * Zoom-Anzeige in Toolbar aktualisieren
         */
        updateZoomUI: function() {
            if (!this.overlay) return;
            var display = this.overlay.querySelector('.cbd-board-zoom-display');
            if (display) {
                display.textContent = Math.round(this.zoom * 100) + '%';
            }
        },

        /**
         * Hineinzoomen (25%-Schritte)
         */
        zoomIn: function() {
            var newZoom = Math.min(4.0, this.zoom * 1.25);
            this._setZoomCentered(newZoom);
        },

        /**
         * Herauszoomen (25%-Schritte)
         */
        zoomOut: function() {
            var newZoom = Math.max(0.5, this.zoom / 1.25);
            this._setZoomCentered(newZoom);
        },

        /**
         * Zoom zurücksetzen (100%)
         */
        resetZoom: function() {
            this.zoom = 1.0;
            this.panX = 0;
            this.panY = 0;
            this.updateZoomUI();
            this.updateCanvasTransform();
        },

        /**
         * Zoom auf Mittelpunkt des sichtbaren Bereichs zentrieren
         */
        _setZoomCentered: function(newZoom) {
            if (!this.overlay) {
                this.zoom = newZoom;
                this.updateZoomUI();
                return;
            }
            var canvasArea = this.overlay.querySelector('.cbd-board-canvas-area');
            if (!canvasArea) {
                this.zoom = newZoom;
                this.updateZoomUI();
                return;
            }
            var areaW = canvasArea.clientWidth;
            var areaH = canvasArea.clientHeight;
            // Mittelpunkt des sichtbaren Bereichs in Canvas-Koordinaten
            var cx = (areaW / 2 - this.panX) / this.zoom;
            var cy = (areaH / 2 - this.panY) / this.zoom;
            // Nach Zoomänderung: Mittelpunkt auf gleicher Viewport-Position halten
            this.panX = areaW / 2 - cx * newZoom;
            this.panY = areaH / 2 - cy * newZoom;
            this.zoom = newZoom;
            this.updateZoomUI();
            this.updateCanvasTransform();
        },

        /**
         * Pinch-Zoom starten (2 Finger erkannt)
         */
        _startPinch: function() {
            var ids = Object.keys(this._pinchPointers);
            if (ids.length < 2) return;
            var p1 = this._pinchPointers[ids[0]];
            var p2 = this._pinchPointers[ids[1]];
            this._pinchStartDist = Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
            this._pinchStartZoom = this.zoom;
            this._pinchStartPanX = this.panX;
            this._pinchStartPanY = this.panY;
            this._pinchMidX = (p1.x + p2.x) / 2;
            this._pinchMidY = (p1.y + p2.y) / 2;
        },

        /**
         * Pinch-Zoom verarbeiten (bei Fingerbewegung)
         */
        _handlePinch: function() {
            var ids = Object.keys(this._pinchPointers);
            if (ids.length < 2 || this._pinchStartDist === null) return;
            var p1 = this._pinchPointers[ids[0]];
            var p2 = this._pinchPointers[ids[1]];
            var newDist = Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
            var scale = newDist / this._pinchStartDist;
            var newZoom = Math.max(0.5, Math.min(4.0, this._pinchStartZoom * scale));

            // Neuer Mittelpunkt der Finger
            var midX = (p1.x + p2.x) / 2;
            var midY = (p1.y + p2.y) / 2;

            // Canvas-Bereich-Position ermitteln
            var canvasArea = this.overlay.querySelector('.cbd-board-canvas-area');
            if (!canvasArea) return;
            var areaRect = canvasArea.getBoundingClientRect();

            // Canvas-Koordinate am initialen Pinch-Mittelpunkt
            var midCanvasX = (this._pinchMidX - areaRect.left - this._pinchStartPanX) / this._pinchStartZoom;
            var midCanvasY = (this._pinchMidY - areaRect.top - this._pinchStartPanY) / this._pinchStartZoom;

            // Pan so anpassen dass Canvas-Koordinate am aktuellen Finger-Mittelpunkt bleibt
            this.zoom = newZoom;
            this.panX = midX - areaRect.left - midCanvasX * newZoom;
            this.panY = midY - areaRect.top - midCanvasY * newZoom;

            this.updateZoomUI();
            this.updateCanvasTransform();
        },

        // =============================================
        // Hilfsfunktionen
        // =============================================

        _escHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        /**
         * Hex zu RGBA konvertieren
         */
        hexToRgba: function(hex, alpha) {
            var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (result) {
                var r = parseInt(result[1], 16);
                var g = parseInt(result[2], 16);
                var b = parseInt(result[3], 16);
                return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
            }
            return hex;
        },

        // Hellt eine Farbe für Textmarkierer auf (mischt mit Weiß)
        // WICHTIG: Volle Deckkraft (1.0) + 'lighten' = Konstante Farbe beim Übermalen
        getLighterHighlightColor: function(hex) {
            var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (result) {
                var r = parseInt(result[1], 16);
                var g = parseInt(result[2], 16);
                var b = parseInt(result[3], 16);

                // Berechne Weiß-Mischung basierend auf highlighterOpacity
                // opacity 0.1 (10%) = sehr hell (viel Weiß gemischt)
                // opacity 1.0 (100%) = dunkel (wenig Weiß gemischt)
                var whiteMix = Math.max(0.3, Math.min(0.95, 1.0 - (this.highlighterOpacity * 0.7)));

                // Mische mit Weiß für hellen Textmarkierer-Effekt
                r = Math.round(r + (255 - r) * whiteMix);
                g = Math.round(g + (255 - g) * whiteMix);
                b = Math.round(b + (255 - b) * whiteMix);

                // Volle Deckkraft (1.0) damit 'lighten' operation korrekt funktioniert
                // Bei 1.0: Einmal markiert = Farbe bleibt konstant (lighten ersetzt nur dunklere Pixel)
                return 'rgba(' + r + ', ' + g + ', ' + b + ', 1.0)';
            }
            return 'rgba(255, 255, 200, 1.0)'; // Fallback: Helles Gelb
        },

        // =============================================
        // Import/Export persönliche Notizen
        // =============================================

        /**
         * Persönliche Notizen als JSON-Datei herunterladen
         */
        downloadPersonalNotes: function() {
            try {
                // Alle CBD-Board Daten aus localStorage sammeln
                // Exportiert werden alle Tafel-Schlüssel (auch -elemente, AP-4.4),
                // gezählt nur die Notizen selbst (istNotizSchluessel)
                var exportData = {};
                var count = 0;

                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (istTafelSchluessel(key)) {
                        exportData[key] = localStorage.getItem(key);
                        if (istNotizSchluessel(key)) count++;
                    }
                }

                if (count === 0) {
                    alert('Keine persönlichen Notizen zum Herunterladen vorhanden.');
                    return;
                }

                // JSON erstellen
                var jsonData = JSON.stringify(exportData, null, 2);
                var blob = new Blob([jsonData], { type: 'application/json' });
                var url = URL.createObjectURL(blob);

                // Download-Link erstellen und klicken
                var link = document.createElement('a');
                link.href = url;
                link.download = 'cbd-notizen-' + new Date().toISOString().split('T')[0] + '.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                // Bestätigung
                var msg = count === 1
                    ? '1 Notiz wurde heruntergeladen.'
                    : count + ' Notizen wurden heruntergeladen.';
                alert(msg + '\n\nSie können diese Datei auf einem neuen Gerät importieren.');

            } catch (e) {
                console.error('[CBD Board Mode] Fehler beim Download:', e);
                alert('Fehler beim Herunterladen der Notizen: ' + e.message);
            }
        },

        /**
         * Persönliche Notizen aus JSON-Datei importieren
         */
        uploadPersonalNotes: function() {
            var self = this;

            // File Input erstellen
            var input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';

            input.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;

                var reader = new FileReader();
                reader.onload = function(event) {
                    try {
                        var importData = JSON.parse(event.target.result);

                        // Validierung: Ist es ein Objekt mit cbd-board Keys?
                        var keys = Object.keys(importData);
                        var validKeys = keys.filter(istTafelSchluessel);
                        // Gezählt werden nur Notizen, keine Begleitschlüssel (AP-4.4)
                        var noteCount = validKeys.filter(istNotizSchluessel).length;

                        if (validKeys.length === 0) {
                            alert('Die Datei enthält keine gültigen CBD-Notizen.');
                            return;
                        }

                        // Prüfen, ob bereits Daten vorhanden sind
                        var existingCount = 0;
                        for (var i = 0; i < localStorage.length; i++) {
                            if (istNotizSchluessel(localStorage.key(i))) {
                                existingCount++;
                            }
                        }

                        // Bestätigung wenn Daten vorhanden
                        if (existingCount > 0) {
                            var confirmMsg = 'Es sind bereits ' + existingCount + ' persönliche Notiz(en) vorhanden.\n\n' +
                                'Beim Import werden ' + noteCount + ' Notiz(en) hochgeladen.\n' +
                                'Bestehende Notizen mit denselben IDs werden überschrieben.\n\n' +
                                'Möchten Sie fortfahren?';

                            if (!confirm(confirmMsg)) {
                                return;
                            }
                        }

                        // Import durchführen
                        var imported = 0;
                        validKeys.forEach(function(key) {
                            localStorage.setItem(key, importData[key]);
                            if (istNotizSchluessel(key)) {
                                imported++;
                                // Ältere Exportdatei ohne Elemente (AP-4.4): ein
                                // lokaler -elemente-Stand derselben Notiz ist
                                // veraltet und würde das importierte PNG beim
                                // Öffnen überdecken -> entfernen.
                                if (!Object.prototype.hasOwnProperty.call(importData, key + '-elemente')) {
                                    localStorage.removeItem(key + '-elemente');
                                }
                            }
                        });

                        // Bestätigung
                        var successMsg = imported === 1
                            ? '1 Notiz wurde erfolgreich importiert.'
                            : imported + ' Notizen wurden erfolgreich importiert.';

                        alert(successMsg + '\n\nDie Notizen sind jetzt verfügbar.');

                        // Wenn wir gerade die aktuelle Zeichnung importiert haben, neu laden
                        // (die importierte Fassung ersetzt die angezeigte Seite - sonst
                        // kämen ihre Elemente zu den angezeigten dazu)
                        if (importData['cbd-board-' + self.containerId] && self.overlay && self.modell) {
                            self._seiteLeeren();
                            self.loadDrawing();
                        }

                    } catch (e) {
                        console.error('[CBD Board Mode] Fehler beim Import:', e);
                        alert('Fehler beim Importieren der Notizen: ' + e.message + '\n\nStellen Sie sicher, dass die Datei eine gültige CBD-Notizen-Datei ist.');
                    }
                };
                reader.readAsText(file);
            });

            // File-Dialog öffnen
            input.click();
        },

        /**
         * Alle persönlichen Notizen löschen (mit Bestätigung)
         */
        deleteAllPersonalNotes: function() {
            try {
                // Zähle vorhandene Notizen (tool-settings ausschließen!). Gelöscht
                // werden alle Tafel-Schlüssel inkl. -elemente, gezählt nur die
                // Notizen selbst (AP-4.4)
                var count = 0;
                var keysToDelete = [];
                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (istTafelSchluessel(key)) {
                        if (istNotizSchluessel(key)) count++;
                        keysToDelete.push(key);
                    }
                }

                if (count === 0) {
                    alert('Keine persönlichen Notizen zum Löschen vorhanden.');
                    return;
                }

                // Bestätigungsabfrage
                var confirmMsg = '⚠️ WARNUNG: Alle lokalen Tafel-Notizen löschen\n\n' +
                    'Es werden ' + count + ' persönliche Notiz(en) UNWIDERRUFLICH gelöscht.\n' +
                    'Dies kann NICHT rückgängig gemacht werden!\n\n' +
                    'Tipp: Exportieren Sie Ihre Notizen zuerst mit dem Download-Button,\n' +
                    'wenn Sie sie später wiederherstellen möchten.\n\n' +
                    'Möchten Sie wirklich ALLE lokalen Notizen löschen?';

                if (!confirm(confirmMsg)) {
                    return;
                }

                // Zweite Bestätigung für zusätzliche Sicherheit
                var confirmMsg2 = 'Letzte Bestätigung:\n\n' +
                    'Sind Sie ABSOLUT SICHER, dass Sie alle ' + count + ' Notizen löschen möchten?\n\n' +
                    'Dies kann NICHT rückgängig gemacht werden!';

                if (!confirm(confirmMsg2)) {
                    return;
                }

                // Alle cbd-board-* Keys löschen
                keysToDelete.forEach(function(key) {
                    localStorage.removeItem(key);
                });

                // Canvas leeren (falls gerade eine Zeichnung angezeigt wird)
                if (this.drawingCtx) {
                    this.drawingCtx.clearRect(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);
                    if (this.modell) {
                        this.modell.elemente = [];
                        this.undoStapel = [];
                    }
                }

                // Erfolgsbestätigung
                alert('✓ Erfolgreich gelöscht!\n\n' +
                    'Alle ' + count + ' persönlichen Tafel-Notizen wurden gelöscht.\n\n' +
                    'Ihre Zeichnungen sind jetzt wieder leer.');

            } catch (e) {
                console.error('[CBD Board Mode] Fehler beim Löschen:', e);
                alert('Fehler beim Löschen der Notizen: ' + e.message);
            }
        },

        /**
         * Interaktive Blöcke initialisieren
         * Führt Inline-Scripts aus und triggert Block-Initialisierung
         */
        initializeInteractiveBlocks: function(container) {
            if (!container) return;

            var self = this;

            try {
                // 1. Externe Libraries für spezielle Blöcke laden
                this.loadBlockDependencies(container, function() {
                    // 2. Alle <script>-Tags finden und ausführen
                    var scripts = container.querySelectorAll('script');
                scripts.forEach(function(oldScript) {
                    // Neues Script-Element erstellen (damit es ausgeführt wird)
                    var newScript = document.createElement('script');

                    // Kopiere alle Attribute
                    Array.from(oldScript.attributes).forEach(function(attr) {
                        newScript.setAttribute(attr.name, attr.value);
                    });

                    // Kopiere Script-Inhalt
                    if (oldScript.src) {
                        // Externes Script - src kopieren
                        newScript.src = oldScript.src;
                    } else {
                        // Inline-Script - Inhalt kopieren
                        newScript.textContent = oldScript.textContent;
                    }

                    // Ersetze altes Script durch neues (triggert Ausführung)
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                // 3. Custom Event für Block-Initialisierung auslösen
                var event = new CustomEvent('cbd-board-content-loaded', {
                    detail: { container: container },
                    bubbles: true,
                    cancelable: false
                });
                container.dispatchEvent(event);

                // 4. jQuery-basierte Initialisierung (falls jQuery vorhanden)
                if (typeof jQuery !== 'undefined') {
                    jQuery(container).trigger('cbd-board-content-loaded');
                }

                // 5. WordPress Interactivity API neu initialisieren (falls vorhanden)
                if (window.wp && window.wp.interactivity) {
                    // Timeout um sicherzustellen, dass DOM-Updates abgeschlossen sind
                    setTimeout(function() {
                        // Triggere Interactivity API für alle interaktiven Elemente im Container
                        var interactiveElements = container.querySelectorAll('[data-wp-interactive]');
                        interactiveElements.forEach(function(element) {
                            // Force re-initialization by triggering a DOM mutation
                            var parent = element.parentNode;
                            var next = element.nextSibling;
                            parent.removeChild(element);
                            parent.insertBefore(element, next);
                        });
                    }, 100);
                }

                // 6. Spezielle Block-Initialisierung (Molekülviewer, etc.)
                self.initializeSpecialBlocks(container);

                window.cbdDebug && console.log('[CBD Board Mode] Interaktive Blöcke initialisiert:', scripts.length, 'Scripts ausgeführt');
            });

            } catch (e) {
                console.error('[CBD Board Mode] Fehler bei Block-Initialisierung:', e);
            }
        },

        /**
         * Lädt externe Abhängigkeiten für spezielle Blöcke (z.B. 3Dmol.js für Molekülviewer)
         */
        loadBlockDependencies: function(container, callback) {
            var dependencies = [];

            // Prüfe auf Molekülviewer-Block
            if (container.querySelector('.wp-block-modular-blocks-molecule-viewer')) {
                // Prüfe ob 3Dmol.js bereits geladen ist
                if (typeof window.$3Dmol === 'undefined') {
                    dependencies.push({
                        name: '3Dmol',
                        url: 'https://3dmol.csb.pitt.edu/build/3Dmol-min.js',
                        check: function() { return typeof window.$3Dmol !== 'undefined'; }
                    });
                }
            }

            // Prüfe auf Chart-Block (Plotly)
            if (container.querySelector('.wp-block-modular-blocks-chart-block')) {
                if (typeof window.Plotly === 'undefined') {
                    dependencies.push({
                        name: 'Plotly',
                        url: 'https://cdn.plot.ly/plotly-latest.min.js',
                        check: function() { return typeof window.Plotly !== 'undefined'; }
                    });
                }
            }

            // Wenn keine Dependencies fehlen, direkt callback ausführen
            if (dependencies.length === 0) {
                callback();
                return;
            }

            // Dependencies laden
            window.cbdDebug && console.log('[CBD Board Mode] Lade externe Libraries:', dependencies.map(function(d) { return d.name; }).join(', '));

            var loaded = 0;
            var total = dependencies.length;

            dependencies.forEach(function(dep) {
                var script = document.createElement('script');
                script.src = dep.url;
                script.onload = function() {
                    window.cbdDebug && console.log('[CBD Board Mode]', dep.name, 'erfolgreich geladen');
                    loaded++;
                    if (loaded === total) {
                        // Alle Dependencies geladen, callback ausführen
                        setTimeout(callback, 100); // Kurze Verzögerung für Initialisierung
                    }
                };
                script.onerror = function() {
                    console.error('[CBD Board Mode] Fehler beim Laden von', dep.name);
                    loaded++;
                    if (loaded === total) {
                        callback(); // Auch bei Fehler weitermachen
                    }
                };
                document.head.appendChild(script);
            });
        },

        /**
         * Spezielle Initialisierung für bekannte Blöcke im Overlay.
         * Alle Blöcke aus "Eigene WP Blocks" exponieren window.initBlockName(element).
         */
        initializeSpecialBlocks: function(container) {

            // === Molekülviewer (3Dmol.js) ===
            // Verwendet IntersectionObserver mit data-loaded Flag – muss direkt neu init werden
            var viewerElements = container.querySelectorAll('[data-chemviz-viewer]');
            if (viewerElements.length > 0 && typeof window.$3Dmol !== 'undefined' && window.ChemVizMoleculeViewer) {
                viewerElements.forEach(function(element) {
                    // data-loaded Flag zurücksetzen (wurde beim innerHTML-Kopieren mitgenommen)
                    delete element.dataset.loaded;

                    // Canvas-Div leeren und ID zurücksetzen für frischen WebGL-Kontext
                    var canvas = element.querySelector('.chemviz-viewer__canvas');
                    if (canvas) {
                        canvas.id = '';
                        canvas.innerHTML = '';
                    }

                    // Direkt laden (IntersectionObserver umgehen)
                    var config = window.ChemVizMoleculeViewer.parseConfig(element);
                    window.ChemVizMoleculeViewer.loadViewer(element);
                    window.ChemVizMoleculeViewer.attachControlListeners(element, config);
                });
            }

            // === Generische Blöcke via window.init* ===
            // Alle Blöcke exponieren eine globale Initialisierungsfunktion
            var blockInitMap = [
                { selector: '.wp-block-modular-blocks-drag-and-drop',       fn: 'initDragAndDrop' },
                { selector: '.wp-block-modular-blocks-drag-the-words',      fn: 'initDragTheWords' },
                { selector: '.wp-block-modular-blocks-multiple-choice',     fn: 'initMultipleChoice' },
                { selector: '.wp-block-modular-blocks-image-comparison',    fn: 'initImageComparison' },
                { selector: '.wp-block-modular-blocks-point-of-interest',   fn: 'initPointOfInterest' },
                { selector: '.wp-block-modular-blocks-statement-connector', fn: 'initStatementConnector' },
                { selector: '.wp-block-modular-blocks-summary-block',       fn: 'initSummaryBlock' },
                { selector: '.wp-block-modular-blocks-image-overlay',       fn: 'initImageOverlay' },
                { selector: '.wp-block-modular-blocks-chart-block',         fn: 'initInteractiveDataChart' },
            ];

            blockInitMap.forEach(function(entry) {
                if (typeof window[entry.fn] !== 'function') return;
                var blocks = container.querySelectorAll(entry.selector);
                blocks.forEach(function(block) {
                    // data-initialized Flag zurücksetzen, damit init erneut ausgeführt wird
                    delete block.dataset.initialized;
                    try {
                        window[entry.fn](block);
                    } catch (e) {
                        console.warn('[CBD Board Mode] Fehler bei', entry.fn + ':', e.message);
                    }
                });
            });
        },

        // =============================================
        // Blocktext auf die Tafel (PLAN-Blocktext-auf-Tafel.md, Phase 2)
        // =============================================

        /**
         * Sichtbaren Text eines Bereichs in Absätze zerlegen (AP-2.1).
         * Liefert [{art, text}], art = 'titel' | 'ueberschrift' | 'absatz' |
         * 'liste' | 'zeile' | 'formel' (nur zur Information).
         *
         * Gelesen wird nur, was der Schüler sieht (Architektur E7): hidden,
         * aria-hidden="true", berechnet display:none oder visibility:hidden
         * fallen samt Inhalt weg - sonst kämen versteckte Lösungen von
         * Quiz-Blöcken auf die Tafel. opacity:0 zählt NICHT als versteckt:
         * Formeln blenden damit ein. Ein BUTTON in einer Überschrift bleibt
         * (Zeilentitel des Accordion-Blocks: h3 > button).
         *
         * Formeln kommen aus data-latex über CBDBoardBlocktext.latexZuText();
         * ins KaTeX-Innenleben wird nicht hineingestiegen (.katex-mathml ist
         * nur weggeschnitten und doppelte sonst den Text).
         *
         * Absatzgrenzen setzt jedes Element, dessen berechnetes display nicht
         * mit "inline" beginnt und nicht "contents" ist. Innerhalb einer
         * Tabellenzeile gelten sie nicht: TR = ein Absatz, Zellen mit " | ".
         * Braucht deshalb einen im Dokument hängenden Bereich.
         *
         * Formatierung (PLAN-Blocktext-Formatierung.md, AP-2.1, Regel F6):
         * Jeder Absatz trägt zusätzlich teile [{t, f?, k?, farbe?}] im Format
         * des Elementmodells - aber nur, wenn etwas formatiert ist (sonst
         * fehlt der Schlüssel). fett = berechnete font-weight >= 600, kursiv =
         * font-style italic/oblique, farbe = berechnete Farbe als '#rrggbb',
         * wenn sie von der Grundfarbe abweicht. Grundfarbe:
         * optionen.grundfarbe, sonst die berechnete Farbe des ersten
         * .cbd-container-content in wurzel, sonst die von wurzel. Stil eines
         * Textknotens = Stil seines Elternelements, Formeltext = Stil des
         * Formel-Elements; Listenpräfix, " | ", Leerzeichen an Blockgrenzen
         * und "\n" aus BR sind Klartext. Der reine Text bleibt genau wie vor
         * AP-2.1 (die Leerraum-Bereinigung läuft zeichenweise über Text und
         * Stile zugleich, siehe blocktextBereinigen()).
         *
         * optionen (F10, das Bearbeitungsfeld aus AP-2.4 wird mit demselben
         * Leser zurückgelesen): grundfarbe ('#rrggbb' u. a.) ersetzt die
         * berechnete Grundfarbe; nbsp: true behandelt geschütztes Leerzeichen
         * (U+00A0) wie ein Leerzeichen (zusammengefasst, am Rand entfernt).
         * Ohne optionen bleibt es wie bisher erhalten; auch das
         * Bearbeitungsfeld setzt nbsp seit AP-2.fix2 nicht mehr
         * (_textFeldLesen).
         * pre: true (AP-2.4) liest schon die Wurzel wie ein PRE - "\n" in
         * Textknoten bleibt ein Zeilenwechsel. Das Bearbeitungsfeld hat
         * white-space: pre-wrap, und Chrome/Safari fügen dort bei einem
         * Zeilenwechsel (Umschalt+Enter, insertLineBreak) ein "\n"-Zeichen
         * statt eines <br> ein - ohne die Option würde er beim Lesen zum
         * Leerzeichen. Nur _textFeldLesen() setzt sie.
         */
        _blocktextLesen: function(wurzel, optionen) {
            var modul = blocktextModul();
            var elemente = elementeModul();
            var opt = optionen || {};
            var absaetze = [];
            var lauf = null; // {art, praefix, text, stile} - stile: ein Stil je Zeichen von text
            var zelleNr = 0;
            var LEER = opt.nbsp ? /[ \t\n\r\f ]+/g : /[ \t\n\r\f]+/g;
            var LEER_PRE = opt.nbsp ? /[ \t\f ]+/g : /[ \t\f]+/g;
            var istElement = !!(wurzel && wurzel.nodeType === 1);

            var grundfarbe = blocktextHexFarbe(opt.grundfarbe);
            if (!grundfarbe && istElement) {
                var inhalt = wurzel.querySelector('.cbd-container-content');
                grundfarbe = blocktextHexFarbe(window.getComputedStyle(inhalt || wurzel).color);
            }

            // Berechneter Stil -> {f, k, farbe} (Regel F6). 'bold'/'normal'
            // nur zur Sicherheit, Browser liefern Zahlen ("700").
            function formatVon(stil) {
                var g = stil.fontWeight;
                g = g === 'bold' ? 700 : (g === 'normal' ? 400 : parseFloat(g));
                var lage = String(stil.fontStyle || '');
                var farbe = blocktextHexFarbe(stil.color);
                return {
                    f: g >= 600 ? 1 : 0,
                    k: (lage.indexOf('italic') === 0 || lage.indexOf('oblique') === 0) ? 1 : 0,
                    farbe: (farbe && farbe !== grundfarbe) ? farbe : null
                };
            }

            // Zeichen gleichen Stils zu Teilen; Präfix als Klartext davor.
            // teileZusammenlegen liefert für reinen Klartext [] -> kein teile.
            function teileBilden(praefix, text, stile) {
                if (!elemente || typeof elemente.teileZusammenlegen !== 'function') return null;
                var roh = praefix ? [{ t: praefix }] : [];
                var i = 0;
                while (i < text.length) {
                    var st = stile[i];
                    var j = i + 1;
                    while (j < text.length && (stile[j] === st ||
                        (stile[j].f === st.f && stile[j].k === st.k && stile[j].farbe === st.farbe))) {
                        j++;
                    }
                    var teil = { t: text.slice(i, j) };
                    if (st.f) teil.f = 1;
                    if (st.k) teil.k = 1;
                    if (st.farbe) teil.farbe = st.farbe;
                    roh.push(teil);
                    i = j;
                }
                var teile = elemente.teileZusammenlegen(roh);
                return teile.length ? teile : null;
            }

            function abschliessen(hart) {
                if (!lauf) return;
                var b = blocktextBereinigen(lauf.text, lauf.stile);
                if (b.text) {
                    var absatz = { art: lauf.art, text: lauf.praefix + b.text };
                    var teile = teileBilden(lauf.praefix, b.text, b.stile);
                    if (teile) absatz.teile = teile;
                    absaetze.push(absatz);
                    lauf = null;
                } else if (hart || !lauf.praefix) {
                    lauf = null;
                }
                // Sonst bleibt ein leerer Listenpunkt offen: <li><p>a</p></li>
                // soll "• a" ergeben, nicht "a" ohne Aufzählungszeichen.
            }

            function beginnen(art, praefix) {
                abschliessen(true);
                lauf = { art: art, praefix: praefix || '', text: '', stile: [] };
            }

            function anhaengen(s, format) {
                if (!lauf) lauf = { art: 'absatz', praefix: '', text: '', stile: [] };
                var st = format || BLOCKTEXT_KLARTEXT;
                lauf.text += s;
                for (var i = 0; i < s.length; i++) lauf.stile.push(st);
            }

            function hatWegKlasse(el) {
                if (!el.classList) return false;
                for (var i = 0; i < BLOCKTEXT_WEG_KLASSEN.length; i++) {
                    if (el.classList.contains(BLOCKTEXT_WEG_KLASSEN[i])) return true;
                }
                return false;
            }

            function listenPraefix(li) {
                var liste = li.parentNode;
                if (!liste || !liste.tagName || liste.tagName.toUpperCase() !== 'OL') return '• ';
                var start = parseInt(liste.getAttribute('start'), 10);
                if (isNaN(start)) start = 1;
                var n = 0;
                for (var k = liste.firstElementChild; k; k = k.nextElementSibling) {
                    if (k.tagName.toUpperCase() === 'LI') n++;
                    if (k === li) break;
                }
                return (n + start - 1) + '. ';
            }

            // format: Stil des Formel-Elements selbst (z. B. fett in <strong>)
            function formel(el, inZeile, format) {
                var quelle = el.getAttribute('data-latex');
                var t = String((modul && typeof modul.latexZuText === 'function') ? modul.latexZuText(quelle) : quelle);
                if (!inZeile && el.classList.contains('cbd-latex-display')) {
                    beginnen('formel');
                    anhaengen(t, format);
                    abschliessen(true);
                } else {
                    anhaengen(t, format);
                }
            }

            function element(el, ctx) {
                var tag = el.tagName.toUpperCase();
                if (BLOCKTEXT_WEG_TAGS[tag] && !(tag === 'BUTTON' && ctx.ueberschrift)) return;
                if (hatWegKlasse(el) || el.hasAttribute('hidden') ||
                    el.getAttribute('aria-hidden') === 'true') {
                    return;
                }
                var stil = window.getComputedStyle(el);
                if (stil.display === 'none' || stil.visibility === 'hidden') return;
                var format = formatVon(stil);

                if (el.classList.contains('cbd-latex-formula') && el.hasAttribute('data-latex')) {
                    formel(el, ctx.inZeile, format);
                    return;
                }
                if (tag === 'BR') {
                    anhaengen('\n', BLOCKTEXT_KLARTEXT);
                    return;
                }

                var istUeberschrift = /^H[1-6]$/.test(tag);
                var art = null;
                var praefix = '';
                if (el.classList.contains('cbd-block-title')) art = 'titel';
                else if (istUeberschrift) art = 'ueberschrift';
                else if (tag === 'LI') { art = 'liste'; praefix = listenPraefix(el); }
                else if (tag === 'TR') art = 'zeile';

                var display = stil.display || '';
                var istBlock = display.indexOf('inline') !== 0 && display !== 'contents' &&
                    tag !== 'TD' && tag !== 'TH';
                var kind = {
                    pre: ctx.pre || tag === 'PRE',
                    ueberschrift: ctx.ueberschrift || istUeberschrift,
                    inZeile: ctx.inZeile,
                    format: format
                };

                if (ctx.inZeile) {
                    // In einer Tabellenzeile: Zellen verbinden, alles
                    // andere Blockige nur als Leerzeichen trennen
                    if (tag === 'TD' || tag === 'TH') {
                        if (zelleNr > 0) anhaengen(' | ', BLOCKTEXT_KLARTEXT);
                        zelleNr++;
                    } else if (istBlock || art) {
                        anhaengen(' ', BLOCKTEXT_KLARTEXT);
                    }
                    besuchen(el, kind);
                    if (istBlock || art) anhaengen(' ', BLOCKTEXT_KLARTEXT);
                    return;
                }

                if (art === 'zeile') {
                    beginnen('zeile');
                    var vorher = zelleNr;
                    zelleNr = 0;
                    kind.inZeile = true;
                    besuchen(el, kind);
                    zelleNr = vorher;
                    abschliessen(true);
                    return;
                }
                if (art) {
                    beginnen(art, praefix);
                    besuchen(el, kind);
                    abschliessen(true);
                    return;
                }
                if (istBlock) abschliessen(false);
                besuchen(el, kind);
                if (istBlock) abschliessen(false);
            }

            function besuchen(knoten, ctx) {
                // Geschlossenes <details>: nur die Überschrift (SUMMARY) ist zu
                // sehen; der übrige Inhalt behält sein berechnetes display und
                // würde sonst mitgelesen (AP-2.fix1, Review-Befund G6)
                var zu = knoten.nodeName === 'DETAILS' && !knoten.open;
                for (var k = knoten.firstChild; k; k = k.nextSibling) {
                    if (zu && k.nodeName !== 'SUMMARY') continue;
                    if (k.nodeType === 3) {
                        // Stil eines Textknotens = Stil seines Elternelements
                        var s = k.nodeValue;
                        s = ctx.pre ? s.replace(/\r\n?/g, '\n').replace(LEER_PRE, ' ')
                            : s.replace(LEER, ' ');
                        if (s) anhaengen(s, ctx.format);
                    } else if (k.nodeType === 1) {
                        element(k, ctx);
                    }
                }
            }

            if (wurzel) {
                besuchen(wurzel, {
                    pre: !!opt.pre, ueberschrift: false, inZeile: false,
                    format: istElement ? formatVon(window.getComputedStyle(wurzel)) : BLOCKTEXT_KLARTEXT
                });
            }
            abschliessen(true);
            return absaetze;
        },

        /**
         * Text des linken Blockbereichs für die Vorschau (AP-2.1): Absätze
         * aus _blocktextLesen(), durch eine Leerzeile getrennt. Ohne offene
         * Tafel ''.
         */
        _blocktextAlsText: function() {
            if (!this.overlay) return '';
            var bereich = this.overlay.querySelector('.cbd-board-content');
            if (!bereich) return '';
            return this._blocktextLesen(bereich).map(function(a) { return a.text; }).join('\n\n');
        },

        /**
         * Absätze des linken Blockbereichs mit Formatierung
         * (PLAN-Blocktext-Formatierung.md, AP-2.1): [{text, teile?}] aus
         * _blocktextLesen() ohne art; teile nur bei formatierten Absätzen.
         * Gleiche Texte wie _blocktextAlsText(). Ohne offene Tafel [].
         *
         * Im Darkmode mit den Stilen des Hellmodus gelesen (AP-2.fix1, siehe
         * _imHellmodus): Die gespeicherten Farben hängen sonst davon ab, in
         * welchem Modus die Lehrperson die Seite gerade ansieht (Glossar
         * '#f0b090' statt '#71230a', Grundfarbe hell statt '#333333').
         */
        _blocktextAbsaetze: function() {
            if (!this.overlay) return [];
            var bereich = this.overlay.querySelector('.cbd-board-content');
            if (!bereich) return [];
            var self = this;
            var gelesen = this._imHellmodus(function() {
                return self._blocktextLesen(bereich);
            }, bereich);
            return gelesen.map(function(a) {
                var absatz = { text: a.text };
                if (a.teile) absatz.teile = a.teile;
                return absatz;
            });
        },

        /**
         * Führt lesen() mit den berechneten Stilen des Hellmodus aus und
         * gibt sein Ergebnis zurück (PLAN-Blocktext-Formatierung.md,
         * AP-2.fix1). Der Darkmode des Themes ist das Attribut data-theme an
         * <html> (Theme/header.php; Hellmodus = Attribut fehlt). Es wird für
         * die Dauer des SYNCHRONEN Lesens entfernt und danach mit demselben
         * Wert wieder gesetzt - dazwischen zeichnet der Browser kein Bild,
         * es flackert also nichts. Ohne data-theme läuft lesen() direkt,
         * ohne jeden Eingriff.
         *
         * Zwei Fallen - bitte nicht vereinfachen:
         * 1. CSS-Übergänge: Das Theme hat z. B. .glossar-term
         *    {transition: all 0.2s}. Ohne Sperre startet das Umschalten mit
         *    der nächsten Stilberechnung einen Übergang, und
         *    getComputedStyle() liefert dessen STARTWERT - also wieder die
         *    Darkmode-Farbe (Hinweg); auf dem Rückweg liefe ein sichtbarer
         *    Übergang hell -> dunkel. Deshalb sperrt ein eigenes <style> alle
         *    Übergänge, bis nach dem Zurücksetzen eine Stilberechnung
         *    erzwungen ist (getComputedStyle an <html> und am gelesenen
         *    Bereich); erst danach wird es entfernt. Die Reihenfolge
         *    "zurücksetzen, berechnen, Sperre weg" ist entscheidend: Wer die
         *    Sperre vor der Berechnung entfernt, startet den Übergang
         *    hell -> dunkel auf der ganzen Seite. Laufende
         *    Übergänge (z. B. Hover auf dem Knopf) springen durch die
         *    Sperre an ihr Ende.
         *    Die Sperre ist bevorzugt ein Constructable Stylesheet
         *    (new CSSStyleSheet() + replaceSync(), angehängt über
         *    document.adoptedStyleSheets; danach wird exakt die alte Liste
         *    wiederhergestellt): Das wirkt auch unter einer CSP OHNE
         *    'unsafe-inline' in style-src, unter der ein eingefügtes
         *    <style>-Element wirkungslos bliebe (Review AP-2.rev, B3: dann
         *    würde wieder der Startwert des Übergangs gelesen, dazu ein
         *    CSP-Konsolenfehler je Lesen). Fehlen Constructable
         *    Stylesheets (ältere Browser) oder scheitert der Aufbau,
         *    sperrt ein <style data-cbd-hellmodus-lesen>-Element wie
         *    bisher; das setzt 'unsafe-inline' in style-src voraus - das
         *    braucht das Theme ohnehin (Customizer-CSS als Inline-<style>).
         * 2. Beobachter auf <html>: Ein MutationObserver auf data-theme
         *    bekäme zwei Einträge (entfernt, wieder gesetzt). Sein Rückruf
         *    läuft als Mikrotask NACH diesem synchronen Block und sieht das
         *    Attribut wieder im alten Zustand. Theme und beide Plugins
         *    beobachten <html> nicht (Stand AP-2.fix1, Liste in der
         *    Übergabenotiz des Plans); der Theme-Knopf liest das Attribut
         *    nur beim Klick, die Tafel-Inversion setzt nur
         *    updateDarkModeInversion(). Wer einen solchen Beobachter
         *    einbaut, muss ihn gegen dieses kurze Umschalten prüfen.
         * Mit try/finally: data-theme kommt auch bei einer Ausnahme zurück.
         * Gelesen werden nur berechnete Stile (kein Layout) - Resize- und
         * IntersectionObserver bemerken nichts.
         */
        _imHellmodus: function(lesen, bereich) {
            var html = document.documentElement;
            if (!html || !html.hasAttribute('data-theme')) return lesen();
            var wert = html.getAttribute('data-theme');
            var SPERRE_CSS = '*, *::before, *::after { transition: none !important; }';
            var altBlaetter = null; // adoptedStyleSheets vor der Sperre (exakt wiederherzustellen)
            var sperre = null;      // <style>-Element, nur im Rückfall
            try {
                if (typeof window.CSSStyleSheet === 'function' && document.adoptedStyleSheets) {
                    var blatt = new window.CSSStyleSheet();
                    blatt.replaceSync(SPERRE_CSS);
                    var alt = Array.prototype.slice.call(document.adoptedStyleSheets);
                    document.adoptedStyleSheets = alt.concat([blatt]);
                    altBlaetter = alt; // erst nach gelungener Zuweisung gesetzt
                }
            } catch (fehler) {
                altBlaetter = null;
            }
            if (!altBlaetter) {
                sperre = document.createElement('style');
                sperre.setAttribute('data-cbd-hellmodus-lesen', '');
                sperre.appendChild(document.createTextNode(SPERRE_CSS));
                (document.head || html).appendChild(sperre);
            }
            try {
                html.removeAttribute('data-theme');
                return lesen();
            } finally {
                html.setAttribute('data-theme', wert);
                // Stilberechnung erzwingen, solange die Sperre noch gilt
                // (Falle 1 oben) - der Rückgabewert wird nicht gebraucht
                window.getComputedStyle(html).getPropertyValue('color');
                if (bereich && bereich.nodeType === 1) window.getComputedStyle(bereich).getPropertyValue('color');
                if (altBlaetter) document.adoptedStyleSheets = altBlaetter;
                if (sperre && sperre.parentNode) sperre.parentNode.removeChild(sperre);
            }
        },

        /**
         * Sichtbarer Ausschnitt der Zeichenfläche in Modell-Koordinaten
         * {x, y, b, h} (AP-2.2): Größe von .cbd-board-canvas-area, um
         * Verschiebung und Zoom zurückgerechnet, auf die Zeichenfläche
         * begrenzt.
         */
        _sichtbarerBereich: function() {
            var flaeche = this.overlay ? this.overlay.querySelector('.cbd-board-canvas-area') : null;
            var breite = flaeche ? flaeche.clientWidth : this.cssWidth;
            var hoehe = flaeche ? flaeche.clientHeight : this.cssHeight;
            var zoom = this.zoom || 1;
            function begrenzt(v, max) { return Math.max(0, Math.min(max, v)); }
            var x0 = begrenzt(-this.panX / zoom, this.cssWidth);
            var x1 = begrenzt((breite - this.panX) / zoom, this.cssWidth);
            var y0 = begrenzt(-this.panY / zoom, this.cssHeight);
            var y1 = begrenzt((hoehe - this.panY) / zoom, this.cssHeight);
            return { x: x0, y: y0, b: Math.max(0, x1 - x0), h: Math.max(0, y1 - y0) };
        },

        /**
         * Text als Text-Elemente auf die aktuelle Seite setzen (AP-2.2) - ein
         * Element je Absatz (Leerzeile trennt), auf die sichtbare Breite
         * umbrochen (Zeilenwechsel stehen danach im Text, Architektur E8).
         * Aufgerufen von der Vorschau (AP-2.3).
         *
         * Eingabe (PLAN-Blocktext-Formatierung.md, AP-2.3): eine Zeichenkette
         * (Leerzeile trennt die Absätze, wie bisher) ODER ein Array von
         * Absätzen - Objekte {text, teile?} (teile wie im Elementmodell, F3)
         * und/oder Zeichenketten. Einträge ohne Text zählen nicht. Ein Absatz
         * mit teile wird zu einem Text-Element mit teile: gemessen wird je
         * Teil in seiner Schrift (fett ist breiter), Teilfarben macht
         * _blocktextMitTeilen() lesbar (F7). Ohne teile sind Element und
         * Ergebnis byte-gleich wie vor dieser Änderung.
         *
         * Platzierung (E9): unter allem, was im sichtbaren Ausschnitt schon
         * auf der Seite steht, 20 px Rand - eigene Notizen werden nicht
         * überdeckt; oben mindestens so tief, dass die Auswahlleiste über
         * den Text passt (G9, _blocktextVerteilen). Schrift 24 px, bis 18 px verkleinert, bis alles passt;
         * was dann nicht passt, kommt als "rest" zurück (in der Form der
         * Eingabe: Zeichenketten bleiben Zeichenketten, Objekte kommen als
         * {text[, teile]} zurück - beides für blocktextAufNeueSeite).
         *
         * Farbe (E10): aktuelle Stiftfarbe, aber mit Kontrastschutz - nach
         * dem Öffnen ist der Stift immer Schwarz, auf grüner oder schwarzer
         * Tafel wäre der Text sonst unsichtbar.
         *
         * Undo (E11): EIN Schritt für alle Absätze, wie beim Duplizieren.
         * Danach ist das Lasso aktiv und alles Eingefügte ausgewählt
         * (verschieben, A−/A+, drehen sofort möglich).
         *
         * Rückgabe {eingefuegt, rest, keinPlatz[, px, ids]}; ohne Tafel null.
         * keinPlatz: nicht einmal eine Zeile in 18 px passt - nichts geändert,
         * kein Undo-Eintrag.
         */
        blocktextEinfuegen: function(eingabe) {
            if (!this.modell) return null;
            var modul = blocktextModul();
            if (this._ziehen) this._ziehenBeenden(true);
            if (this._drehen) this._drehenBeenden(true);
            var absaetze = this._blocktextAbsaetzeAus(eingabe);
            if (!absaetze.length) return { eingefuegt: 0, rest: [], keinPlatz: false };

            var self = this;
            var rahmen = this.modell.elemente.map(function(el) { return self._rahmen(el); });
            var erg = this._blocktextVerteilen(absaetze, rahmen);
            if (!erg.bloecke.length) return { eingefuegt: 0, rest: absaetze, keinPlatz: true };

            var farbe = modul.lesbareFarbe(this.currentColor, this.boardColor);
            var neu = [];
            var ids = [];
            for (var i = 0; i < erg.bloecke.length; i++) {
                var blk = erg.bloecke[i];
                var el = this._elementAusStroke({ tool: 'text', text: blk.text, color: farbe,
                    width: (erg.px - 16) / 4, points: [{ x: blk.x, y: blk.y }] });
                if (el) {
                    if (blk.teile) el = this._blocktextMitTeilen(el, blk.teile);
                    neu.push(el);
                    ids.push(el.id);
                }
            }
            this.merkeZustand();
            this.modell.elemente = this.modell.elemente.concat(neu);
            this.zeichneModell();

            // Auch bei schon aktivem Lasso: alte Auswahl und eine halb
            // gezogene Lassolinie verwerfen (setTool hebt sie dann nicht auf)
            this.auswahlAufheben();
            this.setTool('lasso');
            // setTool() schaltet nur den Zustand um; den Knopf der
            // Werkzeugleiste stellt sonst nur dessen eigener Klick
            var knoepfe = this.overlay.querySelectorAll('.cbd-board-tool');
            for (var k = 0; k < knoepfe.length; k++) {
                knoepfe[k].classList.toggle('active', knoepfe[k].getAttribute('data-tool') === 'lasso');
            }
            this.auswahl = ids;
            this._auswahlZeichnen();
            return { eingefuegt: ids.length, rest: erg.rest, keinPlatz: false, px: erg.px, ids: ids };
        },

        /**
         * Eingabe von blocktextEinfuegen()/blocktextAufNeueSeite() -> Liste
         * der Absätze für CBDBoardBlocktext.verteile() (AP-2.3): eine
         * Zeichenkette wird an Leerzeilen in Absatz-Zeichenketten zerlegt
         * (wie bisher); aus einem Array werden Objekte mit nicht leerem
         * Text als neue Objekte {text[, teile]} übernommen (teile nur, wenn
         * es ein Array ist - die Prüfung der Teile macht das Modul, ein
         * unbrauchbares teile gilt dort als Klartext), Zeichenketten gehen
         * wie die Zeichenkette-Eingabe durch absaetzeAusText(). Alles andere
         * (null, Zahlen, Objekte ohne Text, nur Leerraum) zählt nicht.
         */
        _blocktextAbsaetzeAus: function(eingabe) {
            var modul = blocktextModul();
            if (typeof eingabe === 'string') return modul.absaetzeAusText(eingabe);
            var aus = [];
            if (!Array.isArray(eingabe)) return aus;
            for (var i = 0; i < eingabe.length; i++) {
                var a = eingabe[i];
                if (typeof a === 'string') {
                    aus = aus.concat(modul.absaetzeAusText(a));
                } else if (a !== null && typeof a === 'object' && typeof a.text === 'string' && /\S/.test(a.text)) {
                    var absatz = { text: a.text };
                    if (Array.isArray(a.teile)) absatz.teile = a.teile;
                    aus.push(absatz);
                }
            }
            return aus;
        },

        /**
         * Text-Element el (aus _elementAusStroke) um die Teile eines Blocks
         * von verteile() ergänzen (AP-2.3, F7/F4): liefert ein NEUES Objekt
         * mit denselben Feldern plus teile in der Schlüsselreihenfolge des
         * Elementmodells (id, typ, farbe, breite, text, x, y, teile). Jede
         * Teilfarbe geht durch lesbareLauffarbe(farbe, Tafelfarbe) - eine
         * Farbe, die das nicht kann (z. B. rgb()), entfällt -, danach legt
         * CBDBoardElemente.teileZusammenlegen benachbarte gleiche Teile
         * zusammen. Bleibt keine Formatierung übrig, kommt el unverändert
         * zurück (ohne teile). Dasselbe gilt, wenn die Teile die Prüfung des
         * Elementmodells nicht bestünden - mehr als MAX_TEILE Teile oder eine
         * Verkettung, die nicht der Text ist: Sonst verwürfe sie erst das
         * Speichern/Laden (und zeichnen würde schon vorher unformatiert,
         * zeilenTeile), der Text bleibt vollständig.
         */
        _blocktextMitTeilen: function(el, teile) {
            var modul = blocktextModul();
            var E = elementeModul();
            var lesbar = [];
            for (var i = 0; i < teile.length; i++) {
                var t = teile[i];
                var teil = { t: t.t };
                if (t.f) teil.f = 1;
                if (t.k) teil.k = 1;
                var farbe = t.farbe ? modul.lesbareLauffarbe(t.farbe, this.boardColor) : null;
                if (farbe) teil.farbe = farbe;
                lesbar.push(teil);
            }
            var fertig = E.teileZusammenlegen(lesbar);
            if (!fertig.length || fertig.length > E.MAX_TEILE) return el;
            var verkettet = '';
            for (var k = 0; k < fertig.length; k++) verkettet += fertig[k].t;
            if (verkettet !== el.text) return el;
            return { id: el.id, typ: el.typ, farbe: el.farbe, breite: el.breite, text: el.text,
                x: el.x, y: el.y, teile: fertig };
        },

        /**
         * Absätze im sichtbaren Ausschnitt unter den gegebenen Rahmen
         * verteilen (24 -> 18 px, höchstens 5000 Zeichen je Block); Breiten
         * mit der Zeichenebene gemessen wie in _rahmen() - über _textMass()
         * mit Stil, ein fetter oder kursiver Teil misst also in seiner
         * Schrift (AP-2.3; vorher setzte diese Methode ctx.font selbst und
         * kannte nur normale Schrift). Ohne Zeichenebene schätzt das Modul.
         * Absätze sind Zeichenketten oder {text, teile?} (siehe
         * CBDBoardBlocktext.verteile).
         *
         * Folge-AP G9: Nach dem Einfügen ist alles gewählt, und die
         * Auswahlleiste gehört über den Rahmen (8 px Rand, 6 px Abstand,
         * 4 px zum Flächenrand, _auswahlLeisteAusrichten). Stand der Text
         * oben am Rand und reichte fast bis unten, fand sie weder darüber
         * noch darunter Platz und lag über den ersten Zeilen. Deshalb bleibt
         * oben ihre Höhe frei; bei A+ wächst der Text nach unten, die Leiste
         * bleibt darüber. Passt so gar nichts hin (sehr niedriger
         * Ausschnitt), wird wie bisher ohne diesen Platz verteilt.
         *
         * AP-3.fix1 (Befund aus AP-3.1): Wie hoch die Leiste ist, hängt davon
         * ab, wie viele Blöcke danach gewählt sind. Bei mehreren fehlt der
         * Knopf "Text bearbeiten" (einzeilig, 1366x768: 40 px), bei genau
         * einem ist er da (dort zweizeilig, 76 px). Deshalb wird zuerst mit
         * der Höhe OHNE Knopf verteilt (1366x768: erster Text bei y 60); nur
         * wenn dabei genau ein Block herauskommt, noch einmal mit der Höhe
         * MIT Knopf (y 96) - maßgeblich ist die Zahl der Blöcke, nicht der
         * Absätze (passt nur der erste, lange Absatz zum Teil hin, ist auch
         * genau ein Block gewählt). Ist der zweite Versuch leer (der Text
         * passt mit dem Knopf-Platz nicht mehr hin), bleibt es beim ersten;
         * ist auch der leer, gilt der Rückfall von oben (y 20).
         */
        _blocktextVerteilen: function(absaetze, rahmen) {
            var modul = blocktextModul();
            var mass = this._textMass();
            var optionen = { startPx: 24, minPx: 18, maxZeichen: 5000 };
            var sichtbar = this._sichtbarerBereich();
            var flaeche = modul.flaecheUnter(sichtbar, rahmen, 20);
            var self = this;
            var ohneLeiste = null;
            function schlicht() {
                if (!ohneLeiste) ohneLeiste = modul.verteile(absaetze, flaeche, mass, optionen);
                return ohneLeiste;
            }
            // Verteilen mit Platz für die Leiste in ihrer Höhe mit oder ohne
            // Knopf. Leistenhöhe + 10 px (+ 2 px Spiel) in Overlay-px, durch
            // den Zoom in Modell-px; dazu der 8-px-Rand des Auswahlrahmens.
            // Steht der freie Bereich ohnehin tiefer, bleibt er, wie er ist.
            function mitPlatzFuerLeiste(mitEinText) {
                var oben = sichtbar.y + Math.ceil((self._auswahlLeisteHoeheText(mitEinText) + 12) / (self.zoom || 1)) + 8;
                if (flaeche.y >= oben) return schlicht();
                return modul.verteile(absaetze, { x: flaeche.x, y: oben, b: flaeche.b,
                    h: Math.max(0, flaeche.y + flaeche.h - oben) }, mass, optionen);
            }
            var ohneKnopf = mitPlatzFuerLeiste(false);
            if (ohneKnopf.bloecke.length === 1) {
                var mitKnopf = mitPlatzFuerLeiste(true);
                if (mitKnopf.bloecke.length) return mitKnopf;
            }
            if (ohneKnopf.bloecke.length) return ohneKnopf;
            return schlicht();
        },

        /**
         * Rest auf einer neuen Seite fortsetzen (AP-2.2): addPage() sichert
         * die aktuelle Seite und wechselt auf eine leere, dann wie
         * blocktextEinfuegen() - mit eigenem Undo-Schritt auf der neuen Seite
         * (jede Seite hat ihren eigenen Stapel). Ohne Rest keine neue Seite.
         * Passt im sichtbaren Ausschnitt nicht einmal einer LEEREN Seite
         * etwas hin (stark vergrößert), entsteht ebenfalls keine Seite -
         * Rückgabe keinPlatz (AP-2.fix1, Review-Befund G5: vorher blieb eine
         * leere Seite zurück, Seiten lassen sich nicht löschen).
         *
         * rest (AP-2.3): ein Array aus Zeichenketten wie bisher; enthält es
         * Objekte {text, teile?} (der Rest eines formatierten Texts), geht
         * die Liste als Array an blocktextEinfuegen() - Zeichenketten darin
         * gelten wie dort als Absätze -, und die Probe gegen die leere Seite
         * läuft mit denselben Absätzen. Der Rest behält so seine Formatierung.
         */
        blocktextAufNeueSeite: function(rest) {
            if (!this.modell) return null;
            var mitObjekten = Array.isArray(rest) && rest.some(function(r) {
                return r !== null && typeof r === 'object';
            });
            var text = null;
            var absaetze;
            if (mitObjekten) {
                absaetze = this._blocktextAbsaetzeAus(rest);
            } else {
                text = Array.isArray(rest) ? rest.join('\n\n') : '';
                absaetze = blocktextModul().absaetzeAusText(text);
            }
            if (!absaetze.length) {
                return { eingefuegt: 0, rest: [], keinPlatz: false };
            }
            if (!this._blocktextVerteilen(absaetze, []).bloecke.length) {
                return { eingefuegt: 0, rest: absaetze, keinPlatz: true };
            }
            this.addPage();
            return this.blocktextEinfuegen(mitObjekten ? absaetze : text);
        },

        /**
         * Fenster nach dem Muster von showClearConfirm() bauen (AP-2.3):
         * div.cbd-board-confirm-overlay > div.cbd-board-confirm-dialog am
         * body, also über der Tafel und außerhalb von deren Kontextmenü- und
         * Markiersperre. Liefert {fenster, dialog, aktionen}; der Verweis
         * steht in this._blocktextFenster (es gibt höchstens eines).
         *
         * Tastatur: keydown am Fenster IMMER stopPropagation() - der
         * Tafel-Handler onKeyDown() hängt am document und würde sonst bei
         * Escape die ganze Tafel schließen, bei Strg+Z auf der Tafel
         * rückgängig machen und bei Entf/Rücktaste eine Lasso-Auswahl
         * löschen (dieselbe Regel wie im Textfeld von openTextInput).
         * Escape ruft beiEscape(). Kein Schließen per Klick neben das
         * Fenster: gekürzter Text ginge sonst verloren.
         *
         * AP-2.fix1: Tab/Umschalt+Tab kreisen innerhalb des Fensters
         * (aria-modal, Review-Befund G1); eine gehaltene Taste auf einem
         * Knopf (keydown mit repeat) wirkt nicht - gehaltenes Enter auf
         * "Auf neuer Seite einfügen" legte sonst Seite um Seite an (M1).
         * Seit PLAN-Blocktext-Formatierung.md, AP-2.4 zählt auch das
         * Bearbeitungsfeld (div[contenteditable]) als Ziel; es steht im
         * DOM vor seiner Formatleiste und bleibt so das erste Ziel.
         */
        _blocktextFensterBauen: function(zusatzKlasse, titel, meldung, beiEscape) {
            this._blocktextFensterSchliessen(false);
            var fenster = document.createElement('div');
            fenster.className = 'cbd-board-confirm-overlay cbd-board-blocktext-overlay ' + zusatzKlasse;
            // Fokussierbar: Ein Klick auf den abgedunkelten Rand oder den
            // Fenstertext setzt den Fokus hierher statt auf body - Tasten
            // landen dann weiter bei diesem Fenster
            fenster.tabIndex = -1;
            var dialog = document.createElement('div');
            dialog.className = 'cbd-board-confirm-dialog cbd-board-blocktext-dialog';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'cbd-board-blocktext-titel');
            var h = document.createElement('h4');
            h.id = 'cbd-board-blocktext-titel';
            h.textContent = titel;
            var p = document.createElement('p');
            p.textContent = meldung;
            var aktionen = document.createElement('div');
            aktionen.className = 'cbd-board-confirm-actions';
            dialog.appendChild(h);
            dialog.appendChild(p);
            fenster.appendChild(dialog);
            fenster.addEventListener('keydown', function(e) {
                e.stopPropagation();
                if (e.key === 'Escape') {
                    e.preventDefault();
                    beiEscape();
                } else if (e.key === 'Tab') {
                    var ziele = dialog.querySelectorAll('textarea, [contenteditable="true"], button:not([disabled])');
                    if (!ziele.length) return;
                    var erstes = ziele[0];
                    var letztes = ziele[ziele.length - 1];
                    var aktiv = document.activeElement;
                    var drinnen = dialog.contains(aktiv);
                    if (e.shiftKey && (aktiv === erstes || !drinnen)) {
                        e.preventDefault();
                        letztes.focus();
                    } else if (!e.shiftKey && (aktiv === letztes || !drinnen)) {
                        e.preventDefault();
                        erstes.focus();
                    }
                } else if (e.repeat && e.target && e.target.tagName === 'BUTTON') {
                    e.preventDefault();
                }
            });
            this._blocktextFenster = fenster;
            return { fenster: fenster, dialog: dialog, aktionen: aktionen };
        },

        /** Knopf für ein Blocktext-Fenster. */
        _blocktextFensterKnopf: function(klasse, beschriftung, beiKlick) {
            var knopf = document.createElement('button');
            knopf.type = 'button';
            knopf.className = klasse;
            knopf.textContent = beschriftung;
            knopf.addEventListener('click', beiKlick);
            return knopf;
        },

        /**
         * Offenes Blocktext-Fenster entfernen (auch aus close/destroy/
         * _openOverlay). rueckfokus: Fokus zurück auf den Knopf "Text auf die
         * Tafel" (Abbrechen, Weglassen, Schließen, Escape - AP-2.fix1, G1);
         * sonst bliebe er auf body. Entfernt auch den selectionchange-
         * Lauscher eines Bearbeitungsfelds (_textFeldBauen, AP-2.4).
         */
        _blocktextFensterSchliessen: function(rueckfokus) {
            this._textFeldLauscherEntfernen();
            var f = this._blocktextFenster;
            this._blocktextFenster = null;
            this._textBearbeitenFenster = null; // Fenster "Text bearbeiten" (AP-3.2)
            if (f && f.parentNode) f.parentNode.removeChild(f);
            if (rueckfokus && f && this.overlay) {
                var knopf = this.overlay.querySelector('.cbd-board-blocktext-knopf');
                if (knopf) {
                    try {
                        knopf.focus({ preventScroll: true });
                    } catch (fehler) {
                        knopf.focus();
                    }
                }
            }
        },

        /**
         * Klickschutz nach "Auf die Tafel" bzw. "Auf neuer Seite einfügen"
         * (AP-2.fix1, Review-Befunde M1/G3): 400 ms lang fängt eine
         * unsichtbare Fläche über allem (board-mode.css,
         * .cbd-board-blocktext-schild) Zeigerklicks ab. Der zweite Klick
         * eines Doppelklicks oder doppelt erkannten Tippens träfe sonst den
         * Knopf der sofort erscheinenden Folge-Rückfrage (zweite neue Seite)
         * oder die Zeichenfläche mit aktivem Lasso (Auswahl weg).
         * Tastatur bleibt frei.
         */
        _blocktextKlickschutz: function() {
            this._blocktextKlickschutzEntfernen();
            var schild = document.createElement('div');
            schild.className = 'cbd-board-blocktext-schild';
            schild.setAttribute('aria-hidden', 'true');
            document.body.appendChild(schild);
            this._blocktextSchild = schild;
            var self = this;
            this._blocktextSchildZeit = setTimeout(function() {
                self._blocktextKlickschutzEntfernen();
            }, 400);
        },

        /** Klickschutz entfernen (nach Ablauf, in destroy und _openOverlay). */
        _blocktextKlickschutzEntfernen: function() {
            if (this._blocktextSchildZeit) {
                clearTimeout(this._blocktextSchildZeit);
                this._blocktextSchildZeit = null;
            }
            var s = this._blocktextSchild;
            this._blocktextSchild = null;
            if (s && s.parentNode) s.parentNode.removeChild(s);
        },

        // =============================================
        // Bearbeitungsfeld mit Formatleiste (PLAN-Blocktext-Formatierung.md,
        // AP-2.4, F8-F10): Vorschau "Text auf die Tafel", in Phase 3 auch
        // "Text bearbeiten"
        // =============================================

        /**
         * Formatiert bearbeitbares Feld samt Formatleiste bauen (F8).
         * Liefert {feld, leiste}. Der Aufrufer hängt feld VOR leiste ins
         * Fenster: Die Tab-Falle von _blocktextFensterBauen nimmt das erste
         * Ziel im DOM als Anfang (Prüfung g1Fokus: Tab vom letzten Knopf
         * führt ins Feld); board-mode.css stellt die Leiste per order über
         * das Feld.
         *
         * absaetze: [{text, teile?}] (auch Zeichenketten) wie aus
         * _blocktextAbsaetze() bzw. aus einem Text-Element. teile werden nur
         * übernommen, wenn ihre Verkettung genau text ergibt. optionen:
         *   modus       'vorschau' (Standard): je Absatz ein div, Enter =
         *               neuer Absatz; 'bearbeiten' (Phase 3): alles in EINEM
         *               div, Absätze und Zeilen durch <br>, Enter =
         *               Zeilenwechsel (F12)
         *   grundfarbe  Farbe des Texts ohne eigene Farbe (Einfügefarbe bzw.
         *               Farbe des Elements)
         *   hintergrund Tafelfarbe
         * Das Feld zeigt den Text so, wie er auf die Tafel kommt: Hintergrund
         * = Tafel, Schrift = Grundfarbe, Teilfarben schon über
         * lesbareLauffarbe (F7). Zurückgelesen wird mit _textFeldLesen().
         *
         * Inhalt NUR über DOM-Methoden (createElement, Textknoten, <br>) -
         * nie innerHTML mit Text aus dem Block oder der Zwischenablage (XSS).
         *
         * Formatleiste: Fett, Kursiv, die Stiftfarben (presetColors),
         * Standard (= Grundfarbe, entfernt die Farbe). Die Leiste verhindert
         * pointerdown und mousedown, damit Fokus und Markierung im Feld
         * bleiben. Ein Klick nimmt die Markierung im Feld - liegt keine im
         * Feld (auf dem iPad kann sie beim Antippen verloren gehen, Risiko in
         * Abschnitt 5 des Plans), die zuletzt dort gemerkte (selectionchange)
         * - und formatiert per document.execCommand mit styleWithCSS (F9).
         * Nur eine Schreibmarke im Feld oder gar keine Markierung: nichts.
         * Die Leiste ist EIN Tab-Halt (role toolbar), Pfeiltasten/Pos1/Ende
         * wandern zwischen den Knöpfen.
         *
         * Feld: Einfügen aus der Zwischenablage nur als Klartext
         * (insertText), Ziehen und Ablegen gesperrt, kein Unterstreichen
         * (Strg/Cmd+U, dazu beforeinput formatUnderline für das iPad-Menü) -
         * unterstrichen käme nicht auf die Tafel.
         *
         * Der selectionchange-Lauscher hängt am document; entfernt wird er
         * in _blocktextFensterSchliessen() (also auch in close/destroy/
         * _openOverlay) und beim Bau des nächsten Felds - es gibt höchstens
         * eines.
         */
        _textFeldBauen: function(absaetze, optionen) {
            var opt = optionen || {};
            var modul = blocktextModul();
            var modus = opt.modus === 'bearbeiten' ? 'bearbeiten' : 'vorschau';
            var hintergrund = blocktextHexFarbe(opt.hintergrund) || '#ffffff';
            var grundfarbe = blocktextHexFarbe(opt.grundfarbe) || '#000000';

            // Teilfarbe so, wie sie auf die Tafel kommt (F7); null = keine
            function lauffarbe(farbe) {
                var hex = blocktextHexFarbe(farbe);
                if (!hex || !modul || typeof modul.lesbareLauffarbe !== 'function') return hex;
                return modul.lesbareLauffarbe(hex, hintergrund);
            }

            var feld = document.createElement('div');
            feld.className = 'cbd-board-blocktext-feld';
            feld.setAttribute('contenteditable', 'true');
            feld.setAttribute('role', 'textbox');
            feld.setAttribute('aria-multiline', 'true');
            feld.setAttribute('aria-label', modus === 'bearbeiten' ? 'Text bearbeiten' : 'Text für die Tafel');
            feld.setAttribute('spellcheck', 'false');
            feld.setAttribute('autocorrect', 'off');
            feld.setAttribute('autocapitalize', 'off');
            feld.setAttribute('data-modus', modus);
            feld.setAttribute('data-grundfarbe', grundfarbe);
            feld.style.backgroundColor = hintergrund;
            feld.style.color = grundfarbe;

            // Text in ziel hängen, "\n" -> <br>
            function zeilenAnhaengen(ziel, text) {
                var zeilen = text.split('\n');
                for (var i = 0; i < zeilen.length; i++) {
                    if (i > 0) ziel.appendChild(document.createElement('br'));
                    if (zeilen[i]) ziel.appendChild(document.createTextNode(zeilen[i]));
                }
            }

            // Absatz -> Teile; teile nur, wenn sie genau den Text ergeben,
            // sonst gilt der Absatz als Klartext. Die Schleifenbedingung
            // prüft teile VOR teile.length: Ein ungültiger Teil setzt teile
            // auf null (Review AP-2.rev, B1 - "i < teile.length && teile"
            // las teile.length nach teile = null und warf einen TypeError).
            function teileVon(absatz) {
                var text = typeof absatz === 'string' ? absatz : String((absatz && absatz.text) || '');
                var teile = (absatz && typeof absatz === 'object' && Array.isArray(absatz.teile)) ? absatz.teile : null;
                if (teile) {
                    var kette = '';
                    for (var i = 0; teile && i < teile.length; i++) {
                        if (!teile[i] || typeof teile[i].t !== 'string') teile = null;
                        else kette += teile[i].t;
                    }
                    if (teile && kette !== text) teile = null;
                }
                return teile || [{ t: text }];
            }

            // Je formatiertem Teil ein span (fett, kursiv, Farbe), Klartext
            // als Textknoten
            function absatzAnhaengen(ziel, absatz) {
                var teile = teileVon(absatz);
                for (var i = 0; i < teile.length; i++) {
                    var teil = teile[i];
                    var farbe = teil.farbe ? lauffarbe(teil.farbe) : null;
                    var behaelter = ziel;
                    if (teil.f || teil.k || farbe) {
                        behaelter = document.createElement('span');
                        if (teil.f) behaelter.style.fontWeight = 'bold';
                        if (teil.k) behaelter.style.fontStyle = 'italic';
                        if (farbe) behaelter.style.color = farbe;
                        ziel.appendChild(behaelter);
                    }
                    zeilenAnhaengen(behaelter, teil.t);
                }
            }

            var liste = Array.isArray(absaetze) ? absaetze : [];
            var i;
            if (modus === 'bearbeiten') {
                var block = document.createElement('div');
                for (i = 0; i < liste.length; i++) {
                    if (i > 0) block.appendChild(document.createElement('br'));
                    absatzAnhaengen(block, liste[i]);
                }
                // Ein leeres div hat keine Zeile für die Schreibmarke
                if (!block.firstChild) block.appendChild(document.createElement('br'));
                feld.appendChild(block);
            } else {
                for (i = 0; i < liste.length; i++) {
                    var div = document.createElement('div');
                    absatzAnhaengen(div, liste[i]);
                    if (div.firstChild) feld.appendChild(div);
                }
                if (!feld.firstChild) {
                    var leer = document.createElement('div');
                    leer.appendChild(document.createElement('br'));
                    feld.appendChild(leer);
                }
            }

            // ---- Markierung merken ----
            var gemerkt = null;
            function imFeld(r) {
                return !!r && feld.contains(r.startContainer) && feld.contains(r.endContainer);
            }
            // Markierung, auf die ein Knopf wirkt: die aktuelle, wenn sie im
            // Feld liegt (nur Schreibmarke -> keine); liegt keine im Feld,
            // die zuletzt dort gemerkte
            function markierung() {
                var sel = window.getSelection();
                var r = (sel && sel.rangeCount) ? sel.getRangeAt(0) : null;
                if (!imFeld(r)) r = gemerkt;
                return (r && imFeld(r) && !r.collapsed) ? r.cloneRange() : null;
            }
            this._textFeldLauscherEntfernen();
            var merken = function() {
                var sel = window.getSelection();
                if (!sel || !sel.rangeCount) return;
                var r = sel.getRangeAt(0);
                if (imFeld(r)) gemerkt = r.cloneRange();
            };
            document.addEventListener('selectionchange', merken);
            this._textFeldLauscher = merken;

            function ausfuehren(befehl, wert) {
                var bereich = markierung();
                if (!bereich) return;
                if (document.activeElement !== feld) {
                    try {
                        feld.focus({ preventScroll: true });
                    } catch (fehler) {
                        feld.focus();
                    }
                }
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(bereich);
                document.execCommand('styleWithCSS', false, true);
                document.execCommand(befehl, false, wert);
            }

            function standardVerhindern(e) {
                e.preventDefault();
            }

            // ---- Formatleiste ----
            var leiste = document.createElement('div');
            leiste.className = 'cbd-board-blocktext-format';
            leiste.setAttribute('role', 'toolbar');
            leiste.setAttribute('aria-label', 'Formatieren');
            var knoepfe = [];

            function tabHalt(aktiv) {
                for (var j = 0; j < knoepfe.length; j++) knoepfe[j].tabIndex = knoepfe[j] === aktiv ? 0 : -1;
            }

            function knopf(klasse, beschriftung, name, beiKlick) {
                var k = document.createElement('button');
                k.type = 'button';
                k.className = 'cbd-board-format-knopf ' + klasse;
                k.textContent = beschriftung;
                if (name) {
                    k.setAttribute('aria-label', name);
                    k.title = name;
                }
                k.tabIndex = knoepfe.length ? -1 : 0;
                k.addEventListener('click', function() {
                    tabHalt(k);
                    beiKlick();
                });
                knoepfe.push(k);
                leiste.appendChild(k);
                return k;
            }

            function farbKnopf(farbe) {
                var hex = blocktextHexFarbe(farbe) || String(farbe);
                var k = knopf('cbd-board-format-farbe', '', FORMAT_FARBNAMEN[hex] || hex, function() {
                    ausfuehren('foreColor', lauffarbe(hex) || hex);
                });
                k.style.backgroundColor = hex;
                k.setAttribute('data-farbe', hex);
            }

            knopf('cbd-board-format-fett', 'F', 'Fett', function() { ausfuehren('bold'); });
            knopf('cbd-board-format-kursiv', 'K', 'Kursiv', function() { ausfuehren('italic'); });
            var stifte = this.presetColors || [];
            for (i = 0; i < stifte.length; i++) farbKnopf(stifte[i]);
            var standard = knopf('cbd-board-format-standard', 'Standard', '', function() {
                ausfuehren('foreColor', grundfarbe);
            });
            standard.title = 'Farbe entfernen';

            leiste.addEventListener('pointerdown', standardVerhindern);
            leiste.addEventListener('mousedown', standardVerhindern);
            leiste.addEventListener('keydown', function(e) {
                var nr = knoepfe.indexOf(e.target);
                if (nr < 0) return;
                var n = knoepfe.length;
                var neu = -1;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') neu = (nr + 1) % n;
                else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') neu = (nr + n - 1) % n;
                else if (e.key === 'Home') neu = 0;
                else if (e.key === 'End') neu = n - 1;
                if (neu < 0) return;
                e.preventDefault();
                tabHalt(knoepfe[neu]);
                knoepfe[neu].focus();
            });

            // ---- Feld ----
            feld.addEventListener('paste', function(e) {
                e.preventDefault();
                var daten = e.clipboardData;
                // Einzelne Surrogate -> U+FFFD (B5): Eine präparierte
                // Zwischenablage soll kein halbes Zeichen in den Text bringen
                var text = daten ? blocktextEinzelneSurrogate(String(daten.getData('text/plain') || '').replace(/\r\n?/g, '\n')) : '';
                if (!text) return;
                if (modus === 'bearbeiten') {
                    var zeilen = text.split('\n');
                    for (var z = 0; z < zeilen.length; z++) {
                        if (z > 0) document.execCommand('insertLineBreak');
                        if (zeilen[z]) document.execCommand('insertText', false, zeilen[z]);
                    }
                } else {
                    // Zeilenwechsel werden hier zu neuen Absätzen (Chrome/Safari)
                    document.execCommand('insertText', false, text);
                }
            });
            feld.addEventListener('dragover', standardVerhindern);
            feld.addEventListener('drop', standardVerhindern);
            feld.addEventListener('beforeinput', function(e) {
                if (e.inputType === 'formatUnderline') e.preventDefault();
            });
            feld.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'u' || e.key === 'U')) {
                    e.preventDefault();
                    return;
                }
                if (e.key !== 'Enter' || e.isComposing) return;
                if (modus === 'bearbeiten') {
                    if (!e.shiftKey) {
                        e.preventDefault();
                        document.execCommand('insertLineBreak');
                    }
                } else {
                    document.execCommand('defaultParagraphSeparator', false, 'div');
                }
            });

            return { feld: feld, leiste: leiste };
        },

        /** selectionchange-Lauscher des Bearbeitungsfelds abmelden (AP-2.4). */
        _textFeldLauscherEntfernen: function() {
            if (this._textFeldLauscher) {
                document.removeEventListener('selectionchange', this._textFeldLauscher);
                this._textFeldLauscher = null;
            }
        },

        /**
         * Bearbeitungsfeld zurücklesen (AP-2.4, F10): derselbe Leser wie für
         * den Blockbereich (_blocktextLesen), mit der Grundfarbe des Felds -
         * Teile in dieser Farbe tragen keine eigene, auch nach "Standard" -
         * und "\n" als Zeilenwechsel (Option pre, siehe dort).
         *
         * Geschütztes Leerzeichen (U+00A0) bleibt erhalten (nbsp: false,
         * AP-2.fix2, Review-Befund B2): Es stammt aus der Quelle ("25 °C",
         * bis 3.1.147 blieb es auf der Tafel erhalten, dort bricht die Zeile
         * nicht zwischen Zahl und Einheit). Ein im Feld getipptes
         * Leerzeichen ist U+0020 - das Feld hat white-space: pre-wrap, dort
         * fügt der Browser kein &nbsp; ein (gemessen in Chrome, Prüfung
         * "getipptes Leerzeichen"); aufeinanderfolgende Leerzeichen fasst
         * der Leser trotzdem zusammen. Die Option nbsp des Lesers selbst
         * bleibt bestehen.
         *
         * Einzelne Surrogate werden zu U+FFFD (B5, siehe
         * blocktextEinzelneSurrogate): in text und in allen t.
         *
         * Liefert [{text, teile?}] wie _blocktextAbsaetze() - direkt für
         * blocktextEinfuegen() (AP-2.3) bzw. in Phase 3 für textErsetzen().
         * Das Feld muss im Dokument hängen (berechnete Stile).
         */
        _textFeldLesen: function(feld) {
            if (!feld) return [];
            var grundfarbe = feld.getAttribute('data-grundfarbe') || window.getComputedStyle(feld).color;
            return this._blocktextLesen(feld, { grundfarbe: grundfarbe, nbsp: false, pre: true }).map(function(a) {
                var absatz = { text: a.text };
                if (a.teile) absatz.teile = a.teile;
                return blocktextAbsatzOhneEinzelneSurrogate(absatz);
            });
        },

        /**
         * Vorschau "Text auf die Tafel" (AP-2.3, E1): Text des Blockbereichs
         * zum Kürzen und Ändern, "Auf die Tafel" fügt ein. Bleibt ein Rest
         * oder ist kein Platz, folgt die Rückfrage.
         *
         * Seit PLAN-Blocktext-Formatierung.md, AP-2.4 (F8) ein formatiert
         * bearbeitbares Feld mit Formatleiste (_textFeldBauen) statt eines
         * textarea: Es zeigt fett, kursiv und Farben so, wie sie auf die
         * Tafel kommen (Tafelfarbe als Hintergrund, Einfügefarbe als
         * Grundschrift - dieselbe lesbareFarbe() wie in blocktextEinfuegen);
         * jeder Absatz (div) wird ein eigener Textblock. "Auf die Tafel"
         * liest das Feld mit _textFeldLesen() und übergibt die Absätze als
         * Array (AP-2.3).
         */
        blocktextVorschau: function() {
            if (!this.overlay) return;
            var self = this;
            var absaetze = this._blocktextAbsaetze();
            var leer = !absaetze.length;
            var teile = this._blocktextFensterBauen('', 'Text auf die Tafel',
                leer ? 'In diesem Block gibt es keinen Text zum Übernehmen.'
                     : 'Lösche, was du nicht brauchst. Jeder Absatz wird ein eigener Textblock. ' +
                       'Markiere Wörter, um sie fett, kursiv oder farbig zu machen.',
                function() { self._blocktextFensterSchliessen(true); });

            var feld = null;
            if (!leer) {
                var modul = blocktextModul();
                var bau = this._textFeldBauen(absaetze, {
                    modus: 'vorschau',
                    grundfarbe: (modul && typeof modul.lesbareFarbe === 'function') ?
                        modul.lesbareFarbe(this.currentColor, this.boardColor) : this.currentColor,
                    hintergrund: this.boardColor
                });
                feld = bau.feld;
                // Feld VOR der Leiste (erstes Ziel der Tab-Falle);
                // board-mode.css stellt die Leiste per order darüber
                teile.dialog.appendChild(feld);
                teile.dialog.appendChild(bau.leiste);
            }

            teile.aktionen.appendChild(this._blocktextFensterKnopf('cbd-board-confirm-cancel',
                leer ? 'Schließen' : 'Abbrechen', function() {
                    self._blocktextFensterSchliessen(true);
                }));
            if (!leer) {
                var erledigt = false;
                teile.aktionen.appendChild(this._blocktextFensterKnopf('cbd-board-blocktext-einfuegen',
                    'Auf die Tafel', function() {
                        if (erledigt) return; // schneller Doppelklick
                        erledigt = true;
                        // Lesen, solange das Feld noch im Dokument hängt
                        var erg = self.blocktextEinfuegen(self._textFeldLesen(feld));
                        self._blocktextFensterSchliessen(false);
                        self._blocktextKlickschutz();
                        self._blocktextNachEinfuegen(erg, false);
                    }));
            }
            teile.dialog.appendChild(teile.aktionen);
            document.body.appendChild(teile.fenster);

            if (feld) {
                // Fokus ins Feld, Schreibmarke an den Anfang (eingeklappt)
                feld.focus();
                var sel = window.getSelection();
                if (sel) {
                    var anfang = document.createRange();
                    anfang.setStart(feld, 0);
                    anfang.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(anfang);
                }
                feld.scrollTop = 0;
            } else {
                teile.aktionen.firstChild.focus();
            }
        },

        /**
         * Nach dem Einfügen: Rest oder fehlender Platz -> Rückfrage.
         * aufNeuerSeite: das Einfügen lief schon auf einer frischen Seite.
         */
        _blocktextNachEinfuegen: function(erg, aufNeuerSeite) {
            if (!erg) return;
            if (erg.keinPlatz) {
                if (aufNeuerSeite) {
                    // Auch die leere Seite hat im sichtbaren Ausschnitt keinen
                    // Platz für eine Zeile (stark vergrößert) - nicht noch
                    // eine Seite anbieten, sonst entstünde je Klick eine
                    // weitere leere Seite
                    this._blocktextHinweis('Auch auf der neuen Seite ist im sichtbaren Ausschnitt kein Platz. ' +
                        'Verkleinere die Ansicht und versuche es noch einmal.');
                    return;
                }
                this._blocktextRestFragen(erg.rest, 'Auf dieser Seite ist kein Platz mehr.');
            } else if (erg.rest.length > 0) {
                var n = erg.rest.length;
                this._blocktextRestFragen(erg.rest, n === 1 ?
                    '1 Textblock passt nicht mehr auf diese Seite.' :
                    n + ' Textblöcke passen nicht mehr auf diese Seite.');
            }
        },

        /**
         * Rückfrage für den Rest (AP-2.3): "Weglassen" oder "Auf neuer Seite
         * einfügen". Bleibt danach wieder ein Rest, wird erneut gefragt -
         * eine Schleife über die Rückfrage, keine Programmschleife.
         *
         * Speichert die Klassen-Tafel gerade (isSaving, saveToServer läuft
         * noch für die vorige Seite), wartet "Auf neuer Seite einfügen", bis
         * das Speichern fertig ist (höchstens 15 s): addPage() speichert die
         * gerade befüllte Seite, und saveToServer() überspränge das während
         * der Sperre - die Seite wäre nach dem Wiederöffnen leer
         * (AP-2.fix1, Review-Befund M2). Weglassen/Escape während des Wartens
         * brechen ab.
         */
        _blocktextRestFragen: function(rest, meldung) {
            if (!this.overlay) return;
            var self = this;
            var teile = this._blocktextFensterBauen('cbd-board-blocktext-rest', 'Text auf die Tafel', meldung,
                function() { self._blocktextFensterSchliessen(true); });
            teile.aktionen.appendChild(this._blocktextFensterKnopf('cbd-board-confirm-cancel', 'Weglassen', function() {
                self._blocktextFensterSchliessen(true);
            }));
            var erledigt = false;
            var weiter = this._blocktextFensterKnopf('cbd-board-blocktext-neue-seite', 'Auf neuer Seite einfügen', function() {
                if (erledigt) return;
                erledigt = true;
                var beginn = Date.now();
                (function wennGespeichert() {
                    if (self._blocktextFenster !== teile.fenster) return; // inzwischen geschlossen
                    if (self.isSaving && Date.now() - beginn < 15000) {
                        weiter.disabled = true;
                        weiter.textContent = 'Wird gespeichert …';
                        setTimeout(wennGespeichert, 150);
                        return;
                    }
                    self._blocktextFensterSchliessen(false);
                    var erg = self.blocktextAufNeueSeite(rest);
                    self._blocktextKlickschutz();
                    self._blocktextNachEinfuegen(erg, true);
                })();
            });
            teile.aktionen.appendChild(weiter);
            teile.dialog.appendChild(teile.aktionen);
            document.body.appendChild(teile.fenster);
            weiter.focus();
        },

        /** Reiner Hinweis mit "Schließen" (AP-2.3). */
        _blocktextHinweis: function(meldung) {
            if (!this.overlay) return;
            var self = this;
            var teile = this._blocktextFensterBauen('cbd-board-blocktext-hinweis', 'Text auf die Tafel', meldung,
                function() { self._blocktextFensterSchliessen(true); });
            var zu = this._blocktextFensterKnopf('cbd-board-confirm-cancel', 'Schließen', function() {
                self._blocktextFensterSchliessen(true);
            });
            teile.aktionen.appendChild(zu);
            teile.dialog.appendChild(teile.aktionen);
            document.body.appendChild(teile.fenster);
            zu.focus();
        },

        // =============================================
        // Tafelansicht im Block (lokaler Modus)
        // =============================================

        /**
         * Tafelansicht im Block aktualisieren (lokaler Modus)
         * Wird nach dem Schließen des Overlays aufgerufen
         */
        updateBlockDisplay: function(containerId, stableId, totalPages, currentPageIndex) {
            var baseId = stableId || containerId;
            if (!baseId) return;

            // Block-Container im DOM finden
            var container = null;
            var candidates = document.querySelectorAll('[data-stable-id]');
            for (var i = 0; i < candidates.length; i++) {
                if (candidates[i].getAttribute('data-stable-id') === baseId) {
                    container = candidates[i];
                    break;
                }
            }
            if (!container) return;

            // Seiten-Daten aus localStorage lesen – nur Seiten mit Zeichnungsinhalt
            var pages = [];
            for (var p = 0; p < totalPages; p++) {
                var key = p === 0 ? 'cbd-board-' + baseId : 'cbd-board-' + baseId + ':p' + p;
                var dataUrl = '';
                try { dataUrl = localStorage.getItem(key) || ''; } catch (e) {}
                if (dataUrl) pages.push(dataUrl);
            }

            // Prüfen ob Zeichnungen vorhanden
            var hasDrawing = pages.length > 0;

            // Ziel: .cbd-container-content
            var contentArea = container.querySelector('.cbd-container-content');
            if (!contentArea) return;

            // Bestehende lokale Drawing-Section entfernen (nicht die Server-Section!)
            var oldSection = contentArea.querySelector('.cbd-local-drawing-section');
            if (oldSection) oldSection.parentNode.removeChild(oldSection);
            if (!hasDrawing) return;

            // Drawing-Section aufbauen
            var section = document.createElement('div');
            section.className = 'cbd-drawing-section cbd-local-drawing-section';

            var toggleBtn = document.createElement('button');
            toggleBtn.className = 'cbd-drawing-toggle';
            toggleBtn.textContent = '📝 Eigene Notiz anzeigen';

            var drawingOverlay = document.createElement('div');
            drawingOverlay.className = 'cbd-drawing-overlay';
            drawingOverlay.style.display = 'none';

            var img = document.createElement('img');
            img.alt = 'Tafel-Zeichnung';
            img.style.maxWidth = '100%';

            if (pages.length > 1) {
                // Mehrseitige Navigation mit ◀ / Indikator / ▶
                var pageNav = document.createElement('div');
                pageNav.className = 'cbd-drawing-page-nav';

                var prevBtn = document.createElement('button');
                prevBtn.className = 'cbd-drawing-page-prev';
                prevBtn.textContent = '◀';
                prevBtn.disabled = true;

                var pageInd = document.createElement('span');
                pageInd.className = 'cbd-drawing-page-indicator';

                var nextBtn = document.createElement('button');
                nextBtn.className = 'cbd-drawing-page-next';
                nextBtn.textContent = '▶';

                pageNav.appendChild(prevBtn);
                pageNav.appendChild(pageInd);
                pageNav.appendChild(nextBtn);
                drawingOverlay.appendChild(pageNav);

                // IIFE: saubere Closure-Isolation pro Container
                (function(imgEl, prev, next, ind, pgs, startIdx) {
                    var current = startIdx;
                    function showPage(idx) {
                        if (idx < 0 || idx >= pgs.length) return;
                        current = idx;
                        imgEl.src = pgs[idx] || '';
                        prev.disabled = idx <= 0;
                        next.disabled = idx >= pgs.length - 1;
                        ind.textContent = (idx + 1) + ' / ' + pgs.length;
                    }
                    prev.addEventListener('click', function(e) { e.stopPropagation(); showPage(current - 1); });
                    next.addEventListener('click', function(e) { e.stopPropagation(); showPage(current + 1); });
                    showPage(startIdx);
                })(img, prevBtn, nextBtn, pageInd, pages, currentPageIndex);
            } else {
                img.src = pages[0] || '';
            }

            drawingOverlay.appendChild(img);
            section.appendChild(toggleBtn);
            section.appendChild(drawingOverlay);
            contentArea.appendChild(section);

            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var willShow = drawingOverlay.style.display === 'none';
                drawingOverlay.style.display = willShow ? 'block' : 'none';
                toggleBtn.textContent = willShow ? '📝 Eigene Notiz verbergen' : '📝 Eigene Notiz anzeigen';
                toggleBtn.classList.toggle('cbd-drawing-toggle-active', willShow);
            });
        },

        /**
         * Beim Seitenladen: bestehende Zeichnungen in allen Board-Mode-Blöcken anzeigen
         */
        initBlockDisplays: function() {
            var self = this;
            var boardBlocks = document.querySelectorAll('.cbd-has-board-mode[data-stable-id]');
            for (var i = 0; i < boardBlocks.length; i++) {
                var block = boardBlocks[i];
                var stableId = block.getAttribute('data-stable-id');
                if (!stableId) continue;

                // Seitenanzahl aus localStorage
                var totalPages = 1;
                try {
                    var stored = localStorage.getItem('cbd-board-pagecount-' + stableId);
                    totalPages = stored ? Math.max(1, parseInt(stored, 10)) : 1;
                } catch (e) {}

                // Nur anzeigen wenn mindestens eine Zeichnung vorhanden
                var hasDrawing = false;
                for (var p = 0; p < totalPages; p++) {
                    var key = p === 0 ? 'cbd-board-' + stableId : 'cbd-board-' + stableId + ':p' + p;
                    try {
                        if (localStorage.getItem(key)) { hasDrawing = true; break; }
                    } catch (e) {}
                }

                if (hasDrawing) {
                    self.updateBlockDisplay(null, stableId, totalPages, 0);
                }
            }
        }
    };

    // Beim Seitenladen gespeicherte Zeichnungen anzeigen
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.CBDBoardMode.initBlockDisplays();
        });
    } else {
        window.CBDBoardMode.initBlockDisplays();
    }

})();
