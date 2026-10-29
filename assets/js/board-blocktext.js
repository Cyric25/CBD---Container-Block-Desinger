/**
 * Blocktext auf die Tafel — reines Text- und Geometriemodul
 * (Plan „Blocktext auf die Tafel", Phase 1, Architektur E5).
 *
 * Setzt „window.CBDBoardBlocktext". Genutzt von „board-mode.js" (seit
 * Phase 2: Lesen des Blockbereichs, Einfuegen auf die Tafel), eingereiht in
 * „class-cbd-style-loader.php" als „cbd-board-blocktext" VOR
 * „cbd-board-mode".
 *
 * BITTE DOM-FREI LASSEN: Kein Zugriff auf das Dokument, den
 * Browser-Speicher oder Zeichenflaechen. Der Harnisch
 * „node tools/test-board-blocktext-js.js" fuehrt diese Datei in einem leeren
 * vm-Kontext aus und prueft den Quelltext auf die Namen dieser Schnittstellen
 * — auch in Kommentaren. Textbreiten misst der Aufrufer: Er uebergibt eine
 * Messfunktion mass(text, px, stil) -> Breite in Pixeln (im Browser
 * measureText). stil = {f: 0|1, k: 0|1} (fett, kursiv; seit AP-2.2 von
 * PLAN-Blocktext-Formatierung.md); eine aeltere Messfunktion mit zwei
 * Parametern laeuft weiter und misst dann alles in normaler Schrift.
 *
 * Absatz (seit AP-2.2): eine Zeichenkette ODER ein Objekt {text, teile?}
 * mit teile = [{t, f?, k?, farbe?}] im Format des Text-Elements
 * (board-elemente.js, Feld teile; Verkettung aller t === text).
 * Zeichenketten verhalten sich genau wie vor AP-2.2. Bei Objekten wird
 * jeder Abschnitt in seiner Schrift gemessen (fett ist breiter), die Teile
 * laufen beim Umbruch mit, und der Rest eines geteilten Absatzes behaelt
 * seine Formatierung. Unbrauchbare teile (kein Array, Verkettung != text)
 * gelten als Klartext; repariert wird nichts.
 *
 * Surrogatpaare (Emoji) werden nirgends getrennt - weder beim
 * zeichenweisen Teilen zu breiter Woerter noch beim Teilen nach
 * maxZeichen noch an Teilgrenzen (Review AP-1.rev, Befund B1: ein halbes
 * Paar laesst PHP-json_decode scheitern, dann verwirft der Server die ganze
 * Elementliste einer Klassen-Tafel).
 *
 * Schnittstelle (PLAN-Blocktext-Formatierung.md, Abschnitt 4) - genau
 * diese acht Funktionen (die ersten sechs aus „Blocktext auf die Tafel"):
 *   latexZuText(latex, protokoll) -> string                         (AP-1.2)
 *     Verwandelt LaTeX-Quelltext (Attribut data-latex der Formeln) in
 *     lesbaren Unicode-Text, z. B. „\text{Ag}^+ + e^- \rightleftharpoons
 *     \text{Ag}" -> „Ag⁺ + e⁻ ⇌ Ag". Wirft NIE und gibt NIE einen Backslash
 *     aus. Ist „protokoll" ein Objekt, landet jeder unbekannte Befehl (ohne
 *     Backslash) in protokoll.unbekannt (nur fuer die Messung im Harnisch).
 *   absaetzeAusText(text) -> [string]                               (AP-1.3)
 *     Trennt an Leerzeilen; einzelne Zeilenwechsel bleiben im Absatz.
 *   umbrechen(text, maxBreite, px, mass) -> [zeile]                 (AP-1.3)
 *     Bricht an Leerzeichen auf maxBreite um, vorhandene \n bleiben, zu
 *     breite Woerter werden zeichenweise (je Codepunkt) geteilt.
 *   umbrechenTeile(absatz, maxBreite, px, mass)            (Formatierung AP-2.2)
 *     -> [{text: zeile, teile: [{t, f?, k?, farbe?}]}]
 *     Wie umbrechen (gleiche Wortgrenzen und Sonderfaelle), aber fuer einen
 *     Absatz (Zeichenkette oder Objekt); gemessen wird je Abschnitt mit
 *     mass(t, px, {f, k}). teile je Zeile: Verkettung === zeile, benachbarte
 *     gleiche Teile verbunden (wie teileZusammenlegen in board-elemente.js,
 *     aber ein Klartext-Teil bleibt stehen: [{t: zeile}]); leere Zeile -> [].
 *   verteile(absaetze, flaeche, mass, optionen)                     (AP-1.3)
 *     -> {px, bloecke: [{text, teile?, x, y, b, h}], rest: [Absatz]}
 *     Schrift 24 -> 18 px, bis alles in flaeche {x, y, b, h} passt; sonst
 *     auffuellen und Rest zurueckgeben (ein geteilter Absatz als restlicher
 *     Quelltext ohne weiche Umbrueche). Je Absatz ein Block (Zeilen mit \n),
 *     Abstand Math.round(0,6 * px), Bloecke hoechstens 5000 Zeichen. Seit
 *     AP-2.2 auch mit Absatz-Objekten: Ein Block traegt teile (Verkettung
 *     === text, ein eingefuegtes \n gehoert zum vorigen Teil) nur, wenn er
 *     Formatierung enthaelt; rest steht in der Form der Eingabe
 *     (Zeichenkette bleibt Zeichenkette, Objekt wird {text[, teile]}).
 *   flaecheUnter(sichtbar, rahmenListe, rand) -> {x, y, b, h}       (AP-1.3)
 *     Freier Platz unter vorhandenem Inhalt im sichtbaren Bereich (Inhalt
 *     ganz unterhalb des Bereichs zaehlt nicht, AP-2.fix1).
 *   lesbareFarbe(stift, tafel) -> '#rrggbb'                         (AP-1.3)
 *     Stiftfarbe bei mindestens 3:1 Kontrast (WCAG), sonst Weiss/Schwarz.
 *   lesbareLauffarbe(farbe, tafel) -> '#rrggbb' | null     (Formatierung AP-2.2)
 *     Teilfarbe (F7): bei Kontrast < 3 in 5-%-Schritten mit Weiss (dunkle
 *     Tafel) bzw. Schwarz (helle Tafel) mischen, bis Kontrast >= 3 - der
 *     Farbton bleibt erkennbar. Idempotent; ungueltige Farbe -> null.
 *
 * Alle Funktionen sind frei von Seiteneffekten; Eingaben werden nie
 * veraendert. Listen werden mit Array.isArray erkannt (nicht instanceof),
 * weil der Harnisch sie aus einem anderen Kontext uebergibt.
 *
 * Regeln der Umwandlung (Einzelheiten an den Tabellen unten):
 *   - Hoch-/Tiefstellung ueber Unicode-Tabellen. Ist ein Zeichen nicht
 *     abbildbar, wird eine Gruppe als ^(...) bzw. _(...) geschrieben, ein
 *     einzelnes Zeichen als ^q. Zeichen wie ° oder ‡ stehen ohne ^.
 *   - Brueche a/b; Zaehler/Nenner in Klammern, ausser sie bestehen aus einer
 *     Zahl, genau einem Zeichen oder einem einzigen Textbefehl ohne
 *     Rechenzeichen (\frac{\mathrm{mol}}{\mathrm{L}} -> mol/L).
 *   - Leerzeichen: Nach Zeichen wie griechischen Buchstaben entfaellt ein
 *     folgendes Leerzeichen (\Delta G -> ΔG, wie in TeX). Relationen und
 *     Pfeile stehen immer mit Leerzeichen; Rechenzeichen-Befehle (\cdot,
 *     \times, \pm) bekommen Leerzeichen, wenn links eines steht.
 *     Funktionsnamen (\ln) und unbekannte Befehle behalten ein folgendes
 *     Leerzeichen. Am Ende: Leerraum zusammengefasst, Innenraender von
 *     Klammern entfernt, getrimmt.
 *   - Unbekannter Befehl: Name ohne Backslash, Argumente laufen normal
 *     weiter (\foo{x} -> foox).
 *   - Tiefenschutz: Ab MAX_TIEFE Verschachtelung wird flach gelesen.
 *
 * ES5, keine Abhaengigkeiten.
 *
 * @package ContainerBlockDesigner
 */
(function (global) {
    'use strict';

    var BS = '\\';
    // Gravis-Akzent (Zeichen 96) als Code: der Harnisch verbietet das Zeichen
    // im Quelltext, weil es einen Template-String einleiten wuerde (kein ES5).
    var GRAVIS = String.fromCharCode(96);
    var MAX_TIEFE = 100;

    // =====================================================================
    // Tabellen
    // =====================================================================

    function tabelle(von, nach) {
        var t = {};
        for (var i = 0; i < von.length; i++) {
            t[von.charAt(i)] = nach.charAt(i);
        }
        return t;
    }

    function menge(liste) {
        var t = {};
        for (var i = 0; i < liste.length; i++) {
            t[liste[i]] = true;
        }
        return t;
    }

    // Hochstellung: Ziffern, Zeichen und alle Kleinbuchstaben, die Unicode
    // hochgestellt kennt (es gibt kein hochgestelltes q).
    var HOCH = tabelle(
        '0123456789+-−=()abcdefghijklmnoprstuvwxyz',
        '⁰¹²³⁴⁵⁶⁷⁸⁹⁺⁻⁻⁼⁽⁾ᵃᵇᶜᵈᵉᶠᵍʰⁱʲᵏˡᵐⁿᵒᵖʳˢᵗᵘᵛʷˣʸᶻ'
    );
    // Tiefstellung: Ziffern, Zeichen und die tiefgestellten Kleinbuchstaben
    // von Unicode (vmax -> vₘₐₓ).
    var TIEF = tabelle(
        '0123456789+-−=()aeoxhklmnpstijruv',
        '₀₁₂₃₄₅₆₇₈₉₊₋₋₌₍₎ₐₑₒₓₕₖₗₘₙₚₛₜᵢⱼᵣᵤᵥ'
    );
    // Zeichen, die ohnehin „oben" stehen: ^\circ -> °, \Delta G^‡ -> ΔG‡.
    var HOCH_DURCH = '°′″‴‡†*\'';

    // Buchstabenartige Zeichen: folgendes Leerzeichen entfaellt (wie in TeX).
    var GRIECHISCH = {
        alpha: 'α', beta: 'β', gamma: 'γ', delta: 'δ', epsilon: 'ε',
        varepsilon: 'ε', zeta: 'ζ', eta: 'η', theta: 'θ', vartheta: 'ϑ',
        iota: 'ι', kappa: 'κ', varkappa: 'ϰ', lambda: 'λ', mu: 'μ', nu: 'ν',
        xi: 'ξ', omicron: 'ο', pi: 'π', varpi: 'ϖ', rho: 'ρ', varrho: 'ϱ',
        sigma: 'σ', varsigma: 'ς', tau: 'τ', upsilon: 'υ', phi: 'φ',
        varphi: 'φ', chi: 'χ', psi: 'ψ', omega: 'ω',
        Gamma: 'Γ', Delta: 'Δ', Theta: 'Θ', Lambda: 'Λ', Xi: 'Ξ', Pi: 'Π',
        Sigma: 'Σ', Upsilon: 'Υ', Phi: 'Φ', Psi: 'Ψ', Omega: 'Ω'
    };
    var ZEICHEN = {
        infty: '∞', partial: '∂', nabla: '∇', hbar: 'ℏ', ell: 'ℓ',
        degree: '°', circ: '°', celsius: '°C', bullet: '•', ldots: '…',
        dots: '…', cdots: '…', vdots: '⋮', ast: '*', star: '⋆', prime: '′',
        angle: '∠', perp: '⊥', parallel: '∥', emptyset: '∅',
        varnothing: '∅', forall: '∀', exists: '∃', neg: '¬', lnot: '¬',
        sum: 'Σ', prod: 'Π', int: '∫', iint: '∬', oint: '∮',
        backslash: '∖', dagger: '†', ddagger: '‡', AA: 'Å', angstrom: 'Å',
        ohm: 'Ω', micro: 'µ', permil: '‰', checkmark: '✓', triangle: '△',
        square: '□', Box: '□', langle: '⟨', rangle: '⟩', lbrace: '{',
        rbrace: '}', lbrack: '[', rbrack: ']', vert: '|', lvert: '|',
        rvert: '|', Vert: '‖', lVert: '‖', rVert: '‖', aleph: 'ℵ',
        Re: 'ℜ', Im: 'ℑ', top: '⊤', bot: '⊥', sharp: '♯', flat: '♭',
        imath: 'ı', jmath: 'ȷ', diamond: '⋄', lfloor: '⌊', rfloor: '⌋',
        lceil: '⌈', rceil: '⌉', surd: '√'
    };
    // Rechenzeichen: Leerzeichen rundum, wenn links eines steht.
    var BINAER = {
        cdot: '·', cdotp: '·', centerdot: '·', times: '×', pm: '±',
        mp: '∓', div: '÷', oplus: '⊕', ominus: '⊖', otimes: '⊗',
        odot: '⊙', cup: '∪', cap: '∩', setminus: '∖', wedge: '∧',
        land: '∧', vee: '∨', lor: '∨'
    };
    // Relationen und Pfeile: immer Leerzeichen rundum.
    var RELATION = {
        leq: '≤', le: '≤', leqslant: '≤', geq: '≥', ge: '≥', geqslant: '≥',
        neq: '≠', ne: '≠', approx: '≈', equiv: '≡', sim: '∼', simeq: '≃',
        cong: '≅', propto: '∝', ll: '≪', gg: '≫', 'in': '∈', notin: '∉',
        ni: '∋', subset: '⊂', supset: '⊃', subseteq: '⊆', supseteq: '⊇',
        doteq: '≐', mid: '|', rightarrow: '→', to: '→',
        longrightarrow: '→', leftarrow: '←', gets: '←', longleftarrow: '←',
        leftrightarrow: '↔', longleftrightarrow: '↔',
        rightleftharpoons: '⇌', leftrightharpoons: '⇋',
        rightleftarrows: '⇄', leftrightarrows: '⇆', Rightarrow: '⇒',
        Longrightarrow: '⇒', implies: '⇒', Leftarrow: '⇐',
        Longleftarrow: '⇐', Leftrightarrow: '⇔', Longleftrightarrow: '⇔',
        iff: '⇔', uparrow: '↑', downarrow: '↓', updownarrow: '↕',
        Uparrow: '⇑', Downarrow: '⇓', nearrow: '↗', searrow: '↘',
        nwarrow: '↖', swarrow: '↙', mapsto: '↦', longmapsto: '↦',
        rightharpoonup: '⇀', rightharpoondown: '⇁', leftharpoonup: '↼',
        leftharpoondown: '↽', hookrightarrow: '↪', hookleftarrow: '↩'
    };
    // Pfeile mit Beschriftung: \xrightarrow[unten]{oben} -> →(oben, unten).
    var PFEIL_X = {
        xrightarrow: '→', xleftarrow: '←', xleftrightarrow: '↔',
        xrightleftharpoons: '⇌', xRightarrow: '⇒', xLeftarrow: '⇐',
        xLeftrightarrow: '⇔', xmapsto: '↦'
    };
    // Funktionsnamen: Name ohne Backslash, folgendes Leerzeichen bleibt.
    var FUNKTIONEN = menge([
        'ln', 'log', 'lg', 'lb', 'sin', 'cos', 'tan', 'cot', 'sec', 'csc',
        'arcsin', 'arccos', 'arctan', 'sinh', 'cosh', 'tanh', 'coth', 'exp',
        'lim', 'liminf', 'limsup', 'min', 'max', 'sup', 'inf', 'det', 'deg',
        'dim', 'ker', 'arg', 'gcd', 'lcm', 'Pr', 'mod', 'bmod'
    ]);
    // Ein Argument, als Text gelesen (^ und _ sind dort gewoehnliche Zeichen).
    var TEXT = menge([
        'text', 'textrm', 'textit', 'textbf', 'textsf', 'texttt',
        'textnormal', 'textup', 'textmd', 'textsc', 'mbox', 'hbox', 'emph',
        'fbox', 'pu'
    ]);
    // Ein Argument, Inhalt unveraendert (Schriftart, Hervorhebung).
    var MATHE = menge([
        'mathrm', 'mathit', 'mathbf', 'mathsf', 'mathtt', 'mathnormal',
        'mathcal', 'mathbb', 'mathfrak', 'mathscr', 'boldsymbol', 'bm',
        'operatorname', 'underline', 'underbrace', 'overbrace', 'cancel',
        'bcancel', 'xcancel', 'boxed', 'mathop', 'mathbin', 'mathrel',
        'mathord', 'ensuremath', 'smash'
    ]);
    // Akzente als kombinierende Zeichen hinter dem Inhalt.
    var AKZENT = {
        vec: 0x20D7, overrightarrow: 0x20D7, overleftarrow: 0x20D6,
        bar: 0x0304, overline: 0x0305, hat: 0x0302, widehat: 0x0302,
        tilde: 0x0303, widetilde: 0x0303, dot: 0x0307, ddot: 0x0308,
        acute: 0x0301, grave: 0x0300, breve: 0x0306, check: 0x030C,
        mathring: 0x030A
    };
    var BRUCH = menge(['frac', 'dfrac', 'tfrac', 'cfrac']);
    // Groessenbefehle fuer Klammern: verschwinden, die Klammer bleibt.
    var KLAMMERGROESSE = menge([
        'left', 'right', 'middle', 'big', 'Big', 'bigg', 'Bigg', 'bigl',
        'bigr', 'Bigl', 'Bigr', 'biggl', 'biggr', 'Biggl', 'Biggr', 'bigm',
        'Bigm', 'biggm', 'Biggm'
    ]);
    var ABSTAND = menge([
        'quad', 'qquad', 'enspace', 'enskip', 'thinspace', 'medspace',
        'thickspace', 'space', 'nobreakspace', 'hfill'
    ]);
    var NICHTS = menge([
        'displaystyle', 'textstyle', 'scriptstyle', 'scriptscriptstyle',
        'limits', 'nolimits', 'nonumber', 'notag', 'relax', 'strut',
        'mathstrut', 'protect', 'allowbreak', 'nobreak', 'hline',
        'negthinspace', 'negmedspace', 'negthickspace'
    ]);
    // Ein Argument wird verworfen; Ausgabe ist der Wert.
    var ARG_WEG = {
        phantom: '', vphantom: '', hphantom: '', color: '', label: '',
        hspace: ' ', vspace: ' ', mspace: ' ', begin: ' ', end: ' ',
        cline: ''
    };
    // Zwei Argumente, nur das zweite zaehlt.
    var ZWEITES = menge(['textcolor', 'colorbox']);
    var LABEL_BEFEHLE = menge(['overset', 'underset', 'stackrel']);
    // Ein einziger Textbefehl als Bruch-/Wurzelargument (keine Klammern).
    var EIN_TEXTBEFEHL = /^\s*\\(?:text|textrm|textit|textbf|mathrm|mathit|mathbf|operatorname)\s*\{[^{}]*\}\s*$/;

    // =====================================================================
    // Hilfsfunktionen
    // =====================================================================

    function istLeer(c) {
        return /\s/.test(c);
    }

    function istBuchstabe(c) {
        return /[A-Za-z]/.test(c);
    }

    /** Ueberspringt Leerraum; liefert true, wenn welcher da war. */
    function ueberspringeLeer(z) {
        var gab = false;
        while (z.i < z.n && istLeer(z.s.charAt(z.i))) {
            z.i++;
            gab = true;
        }
        return gab;
    }

    /** Index der zu s[start] = '{' passenden '}' (oder s.length). */
    function klammerEnde(s, start) {
        var tiefe = 0;
        for (var j = start; j < s.length; j++) {
            var c = s.charAt(j);
            if (c === BS) {
                j++;
                continue;
            }
            if (c === '{') {
                tiefe++;
            } else if (c === '}') {
                tiefe--;
                if (tiefe === 0) {
                    return j;
                }
            }
        }
        return s.length;
    }

    function abbilden(s, tab) {
        var aus = '';
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i);
            if (!tab.hasOwnProperty(c)) {
                return null;
            }
            aus += tab[c];
        }
        return aus;
    }

    function alleIn(s, erlaubt) {
        for (var i = 0; i < s.length; i++) {
            if (erlaubt.indexOf(s.charAt(i)) < 0) {
                return false;
            }
        }
        return true;
    }

    function zusammen(t) {
        return t.replace(/\s+/g, ' ').replace(/^ | $/g, '');
    }

    function merkeUnbekannt(z, name) {
        if (z.p) {
            // Array.isArray statt instanceof: Das Protokoll kann aus einem
            // anderen Kontext stammen (Harnisch: vm), dort schlaegt instanceof fehl.
            if (!Array.isArray(z.p.unbekannt)) {
                z.p.unbekannt = [];
            }
            z.p.unbekannt.push(name);
        }
    }

    /** Hoch-/Tiefstellung eines bereits umgewandelten Textes. */
    function hochTiefText(t, istGruppe, art) {
        var kompakt = t.replace(/\s+/g, '');
        if (kompakt === '') {
            return '';
        }
        if (art === '^' && alleIn(kompakt, HOCH_DURCH)) {
            return kompakt;
        }
        var abgebildet = abbilden(kompakt, art === '^' ? HOCH : TIEF);
        if (abgebildet !== null) {
            return abgebildet;
        }
        if (istGruppe || kompakt.length > 1) {
            return art + '(' + zusammen(t) + ')';
        }
        return art + kompakt;
    }

    // Alle Zeichen, die als Hoch-/Tiefstellung herauskommen koennen.
    var INDEXZEICHEN = (function () {
        var s = HOCH_DURCH;
        var k;
        for (k in HOCH) {
            if (HOCH.hasOwnProperty(k)) {
                s += HOCH[k];
            }
        }
        for (k in TIEF) {
            if (TIEF.hasOwnProperty(k)) {
                s += TIEF[k];
            }
        }
        return s;
    })();

    /**
     * Zaehler/Nenner/Wurzelinhalt: Klammern nur, wenn noetig. Ohne Klammern
     * bleiben eine Zahl, genau ein Zeichen (auch mit Hoch-/Tiefstellung wie
     * x², c°, c₀) und ein einziger Textbefehl ohne Rechenzeichen.
     */
    function bruchteil(a) {
        var t = zusammen(a.t);
        if (t === '' || t.length === 1 || /^[0-9]+([.,][0-9]+)?$/.test(t)) {
            return t;
        }
        if (alleIn(t.slice(1), INDEXZEICHEN) && INDEXZEICHEN.indexOf(t.charAt(0)) < 0) {
            return t;
        }
        if (a.gruppe && EIN_TEXTBEFEHL.test(a.roh) && /^[^\s+\-−·×\/=()]+$/.test(t)) {
            return t;
        }
        return '(' + t + ')';
    }

    // =====================================================================
    // Zerleger
    //   z = { s: Quelltext, i: Position, n: Laenge, p: Protokoll|null,
    //         tiefe: Verschachtelung }
    // =====================================================================

    /** Liest bis zum Ende (oder bis zur schliessenden Klammer). */
    function folge(z, bisKlammer, text) {
        var aus = '';
        while (z.i < z.n) {
            var c = z.s.charAt(z.i);
            if (c === '}') {
                z.i++;
                if (bisKlammer) {
                    return aus;
                }
                continue;
            }
            if (c === '{') {
                z.i++;
                aus += gruppe(z, text);
                continue;
            }
            if (c === BS) {
                aus += befehl(z, text, aus);
                continue;
            }
            if (!text && (c === '^' || c === '_')) {
                z.i++;
                var a = argument(z, false);
                aus += a ? hochTiefText(a.t, a.gruppe, c) : '';
                continue;
            }
            z.i++;
            if (c === '~' || (!text && c === '&') || istLeer(c)) {
                aus += ' ';
            } else if (c !== '$') {
                aus += c;
            }
        }
        return aus;
    }

    /** Gruppe {...}; z.i steht hinter der oeffnenden Klammer. */
    function gruppe(z, text) {
        if (z.tiefe >= MAX_TIEFE) {
            return flach(z);
        }
        z.tiefe++;
        var r = folge(z, true, text);
        z.tiefe--;
        return r;
    }

    /** Tiefenschutz: Rest der Gruppe ohne Auswertung, ohne Rekursion. */
    function flach(z) {
        var tiefe = 1;
        var aus = '';
        while (z.i < z.n) {
            var c = z.s.charAt(z.i);
            z.i++;
            if (c === BS) {
                z.i++;
            } else if (c === '{') {
                tiefe++;
            } else if (c === '}') {
                tiefe--;
                if (tiefe === 0) {
                    return aus;
                }
            } else {
                aus += c;
            }
        }
        return aus;
    }

    /** Teilzeichenkette mit demselben Protokoll umwandeln. */
    function unter(quelle, z, text) {
        if (z.tiefe >= MAX_TIEFE) {
            return quelle.split(BS).join('');
        }
        return folge({ s: quelle, i: 0, n: quelle.length, p: z.p, tiefe: z.tiefe + 1 }, false, text);
    }

    /**
     * Ein Argument: Gruppe, Befehl oder einzelnes Zeichen.
     * Liefert { t, gruppe, roh } oder null (kein Argument mehr da).
     */
    function argument(z, text) {
        ueberspringeLeer(z);
        if (z.i >= z.n) {
            return null;
        }
        var c = z.s.charAt(z.i);
        var start = z.i;
        if (c === '{') {
            z.i++;
            var t = gruppe(z, text);
            return { t: t, gruppe: true, roh: z.s.slice(start + 1, Math.max(start + 1, z.i - 1)) };
        }
        if (c === '}') {
            return null;
        }
        if (c === BS) {
            var t2 = befehl(z, text, '');
            return { t: t2, gruppe: false, roh: z.s.slice(start, z.i) };
        }
        z.i++;
        return { t: c, gruppe: false, roh: c };
    }

    /** Optionales Argument [...] oder null. */
    function optional(z, text) {
        var merk = z.i;
        ueberspringeLeer(z);
        if (z.s.charAt(z.i) !== '[') {
            z.i = merk;
            return null;
        }
        var tiefe = 0;
        var j = z.i + 1;
        while (j < z.n) {
            var c = z.s.charAt(j);
            if (c === BS) {
                j += 2;
                continue;
            }
            if (c === '{') {
                tiefe++;
            } else if (c === '}') {
                tiefe--;
            } else if (c === ']' && tiefe <= 0) {
                break;
            }
            j++;
        }
        var inhalt = z.s.slice(z.i + 1, Math.min(j, z.n));
        z.i = Math.min(j + 1, z.n);
        return unter(inhalt, z, text);
    }

    /** Rohes Gruppenargument (fuer \ce) oder null. */
    function rohesArgument(z) {
        ueberspringeLeer(z);
        if (z.s.charAt(z.i) !== '{') {
            return null;
        }
        var e = klammerEnde(z.s, z.i);
        var inhalt = z.s.slice(z.i + 1, e);
        z.i = Math.min(e + 1, z.n);
        return inhalt;
    }

    /** Befehl ab dem Backslash. links = bisherige Ausgabe der Folge. */
    function befehl(z, text, links) {
        z.i++;
        if (z.i >= z.n) {
            return '';
        }
        var c = z.s.charAt(z.i);
        if (!istBuchstabe(c)) {
            z.i++;
            return steuerzeichen(c, z);
        }
        var e = z.i;
        while (e < z.n && istBuchstabe(z.s.charAt(e))) {
            e++;
        }
        var name = z.s.slice(z.i, e);
        z.i = e;
        if (z.tiefe >= MAX_TIEFE) {
            return name;
        }
        z.tiefe++;
        var r = wort(name, z, text, links);
        z.tiefe--;
        return r;
    }

    /** Befehl aus einem einzelnen Nicht-Buchstaben (\, \% \{ ...). */
    function steuerzeichen(c, z) {
        if (c === ',' || c === ';' || c === ':' || c === '>' || istLeer(c)) {
            return ' ';
        }
        if (c === BS) {
            // Zeilenwechsel, optional mit Abstand: \\[2pt]
            if (z.s.charAt(z.i) === '[') {
                var e = z.s.indexOf(']', z.i);
                z.i = e < 0 ? z.n : e + 1;
            }
            return ' ';
        }
        if (c === '!' || c === '-' || c === '/') {
            return '';
        }
        if ('%#&$_{}'.indexOf(c) >= 0) {
            return c;
        }
        if (c === '|') {
            return '‖';
        }
        if ('\'"^~=.'.indexOf(c) >= 0 || c === GRAVIS) {
            // Akzent im Textmodus: der Buchstabe folgt, der Akzent entfaellt.
            return '';
        }
        merkeUnbekannt(z, c);
        return c;
    }

    /** Befehl aus Buchstaben. */
    function wort(name, z, text, links) {
        var a, b;
        if (GRIECHISCH.hasOwnProperty(name)) {
            ueberspringeLeer(z);
            return GRIECHISCH[name];
        }
        if (ZEICHEN.hasOwnProperty(name)) {
            ueberspringeLeer(z);
            return ZEICHEN[name];
        }
        if (RELATION.hasOwnProperty(name)) {
            ueberspringeLeer(z);
            return ' ' + RELATION[name] + ' ';
        }
        if (BINAER.hasOwnProperty(name)) {
            ueberspringeLeer(z);
            // Nur das letzte Zeichen ansehen: /\s$/ wuerde bei jedem Aufruf den
            // ganzen bisherigen Text absuchen (quadratisch bei vielen \cdot).
            return istLeer(links.charAt(links.length - 1)) ? ' ' + BINAER[name] + ' ' : BINAER[name];
        }
        if (FUNKTIONEN.hasOwnProperty(name)) {
            return name + (ueberspringeLeer(z) ? ' ' : '');
        }
        if (TEXT.hasOwnProperty(name)) {
            a = argument(z, true);
            return a ? a.t : '';
        }
        if (MATHE.hasOwnProperty(name)) {
            a = argument(z, text);
            return a ? a.t : '';
        }
        if (AKZENT.hasOwnProperty(name)) {
            a = argument(z, text);
            return a && zusammen(a.t) ? zusammen(a.t) + String.fromCharCode(AKZENT[name]) : '';
        }
        if (BRUCH.hasOwnProperty(name)) {
            a = argument(z, text);
            b = argument(z, text);
            if (!a) {
                return '';
            }
            if (!b) {
                return zusammen(a.t);
            }
            return bruchteil(a) + '/' + bruchteil(b);
        }
        if (name === 'sqrt') {
            var index = optional(z, text);
            a = argument(z, text);
            return (index !== null ? hochTiefText(index, true, '^') : '') + '√' + (a ? bruchteil(a) : '');
        }
        if (PFEIL_X.hasOwnProperty(name)) {
            var unten = optional(z, text);
            a = argument(z, text);
            var oben = a ? zusammen(a.t) : '';
            unten = unten !== null ? zusammen(unten) : '';
            var beschriftung = oben && unten ? oben + ', ' + unten : (oben || unten);
            return ' ' + PFEIL_X[name] + (beschriftung ? '(' + beschriftung + ')' : '') + ' ';
        }
        if (LABEL_BEFEHLE.hasOwnProperty(name)) {
            // \overset{\Delta}{\rightarrow} -> →(Δ)
            a = argument(z, text);
            b = argument(z, text);
            var marke = a ? zusammen(a.t) : '';
            if (!b) {
                return marke;
            }
            var basis = b.t.replace(/\s+$/, '');
            return basis + (marke ? '(' + marke + ')' : '') + (basis.length < b.t.length ? ' ' : '');
        }
        if (KLAMMERGROESSE.hasOwnProperty(name)) {
            ueberspringeLeer(z);
            if (z.s.charAt(z.i) === '.') {
                z.i++;
            }
            return '';
        }
        if (name === 'ce') {
            a = rohesArgument(z);
            return a === null ? '' : ' ' + ce(a, z) + ' ';
        }
        if (name === 'textsuperscript' || name === 'textsubscript') {
            a = argument(z, true);
            return a ? hochTiefText(a.t, true, name === 'textsuperscript' ? '^' : '_') : '';
        }
        if (ABSTAND.hasOwnProperty(name)) {
            ueberspringeLeer(z);
            return ' ';
        }
        if (NICHTS.hasOwnProperty(name)) {
            ueberspringeLeer(z);
            return '';
        }
        if (ARG_WEG.hasOwnProperty(name)) {
            a = argument(z, true);
            if (name === 'begin' && a && /^(array|tabular)\*?$/.test(zusammen(a.t))) {
                argument(z, true);
            }
            return ARG_WEG[name];
        }
        if (ZWEITES.hasOwnProperty(name)) {
            argument(z, true);
            a = argument(z, text);
            return a ? a.t : '';
        }
        if (name === 'not') {
            ueberspringeLeer(z);
            if (z.s.charAt(z.i) === '=') {
                z.i++;
                return ' ≠ ';
            }
            a = argument(z, text);
            var verneint = a ? zusammen(a.t) : '';
            return verneint ? ' ' + verneint + String.fromCharCode(0x0338) + ' ' : '';
        }
        merkeUnbekannt(z, name);
        return name + (ueberspringeLeer(z) ? ' ' : '');
    }

    // =====================================================================
    // mhchem: \ce{...}
    //   Ziffern direkt nach einem Buchstaben oder einer schliessenden
    //   Klammer werden tiefgestellt (H2SO4 -> H₂SO₄); ^... und eine Ladung
    //   n+/n- am Ende eines Teilchens hochgestellt (Fe^3+, Fe3+, Na+).
    // =====================================================================

    var CE_PFEILE = [
        ['<=>>', '⇌'], ['<<=>', '⇌'], ['<=>', '⇌'], ['<-->', '⇄'],
        ['<->', '↔'], ['->', '→'], ['<-', '←']
    ];

    function ceEndeDerLadung(s, i) {
        // Steht an s[i] ein + oder -, das ein Teilchen abschliesst?
        var c = s.charAt(i);
        if (c !== '+' && c !== '-') {
            return false;
        }
        var n = s.charAt(i + 1);
        return n === '' || istLeer(n) || n === ')' || n === ']' || n === '}';
    }

    function ce(roh, z) {
        var aus = '';
        var teilchen = false; // letztes Zeichen gehoert zu einer Formel
        var i = 0;
        var n = roh.length;
        while (i < n) {
            var c = roh.charAt(i);
            var pfeil = null;
            for (var k = 0; k < CE_PFEILE.length; k++) {
                if (roh.substr(i, CE_PFEILE[k][0].length) === CE_PFEILE[k][0]) {
                    pfeil = CE_PFEILE[k];
                    break;
                }
            }
            if (pfeil) {
                i += pfeil[0].length;
                var marken = [];
                while (roh.charAt(i) === '[') {
                    var e = roh.indexOf(']', i);
                    if (e < 0) {
                        e = n;
                    }
                    var m = zusammen(unter(roh.slice(i + 1, e), z, false));
                    if (m) {
                        marken.push(m);
                    }
                    i = e + 1;
                }
                aus += ' ' + pfeil[1] + (marken.length ? '(' + marken.join(', ') + ')' : '') + ' ';
                teilchen = false;
                continue;
            }
            if (c === '^' || c === '_') {
                i++;
                var inhalt;
                if (roh.charAt(i) === '{') {
                    var ende = klammerEnde(roh, i);
                    inhalt = roh.slice(i + 1, ende);
                    i = Math.min(ende + 1, n);
                } else {
                    var st = i;
                    while (i < n && /[0-9]/.test(roh.charAt(i))) {
                        i++;
                    }
                    if (i < n && (roh.charAt(i) === '+' || roh.charAt(i) === '-')) {
                        i++;
                    }
                    if (i === st && i < n && !istLeer(roh.charAt(i))) {
                        i++;
                    }
                    inhalt = roh.slice(st, i);
                }
                aus += hochTiefText(unter(inhalt, z, true), true, c);
                teilchen = true;
                continue;
            }
            if (/[0-9]/.test(c)) {
                var anfang = i;
                while (i < n && /[0-9]/.test(roh.charAt(i))) {
                    i++;
                }
                var ziffern = roh.slice(anfang, i);
                if (teilchen && ceEndeDerLadung(roh, i)) {
                    // Fe3+ -> Fe³⁺
                    aus += abbilden(ziffern + roh.charAt(i), HOCH);
                    i++;
                } else if (teilchen) {
                    aus += abbilden(ziffern, TIEF);
                } else {
                    aus += ziffern;
                }
                continue;
            }
            if (teilchen && ceEndeDerLadung(roh, i)) {
                aus += HOCH[c];
                i++;
                continue;
            }
            if (c === BS) {
                var bEnde = i + 1;
                while (bEnde < n && istBuchstabe(roh.charAt(bEnde))) {
                    bEnde++;
                }
                if (bEnde === i + 1) {
                    bEnde = Math.min(i + 2, n);
                }
                aus += unter(roh.slice(i, bEnde), z, false);
                i = bEnde;
                teilchen = true;
                continue;
            }
            i++;
            if (c === '{' || c === '}') {
                continue;
            }
            if (c === '*') {
                aus += '·';
                teilchen = false;
                continue;
            }
            if (istLeer(c)) {
                aus += ' ';
                teilchen = false;
                continue;
            }
            aus += c;
            teilchen = istBuchstabe(c) || c === ')' || c === ']';
        }
        return aus;
    }

    // =====================================================================
    // Absaetze, Umbruch, Verteilung, freie Flaeche, Farbe (AP-1.3)
    //   Schrift px und Zeilenhoehe 1,2 * px wie beim Text-Element der Tafel
    //   (board-elemente.js); Text-Elemente brechen selbst nicht um, eine
    //   Zeile ist ein mit \n getrennter Abschnitt des Elementtexts.
    // =====================================================================

    var ZEILENHOEHE = 1.2;
    var ABSATZ_ABSTAND = 0.6;
    // Ein Text-Element darf hoechstens so viele Zeichen haben (MAX_TEXT in
    // board-elemente.js, mb_substr in class-cbd-classroom.php) - sonst wird
    // es beim Pruefen still gekuerzt.
    var VORGABE_MAX_ZEICHEN = 5000;
    var VORGABE_START_PX = 24;
    var VORGABE_MIN_PX = 18;
    var EPS = 1e-9;

    function zahl(v, vorgabe) {
        return (typeof v === 'number' && isFinite(v)) ? v : vorgabe;
    }

    // Trimmen als Schleife, nicht als regulaerer Ausdruck: /\s+$/ braucht
    // bei langen Leerzeichenfolgen im Inneren quadratische Zeit (Review
    // AP-1.rev, G2: 20 000 Leerzeichen 1,3 s) - die Eingabe kommt aus dem
    // frei bearbeitbaren Vorschau-Textfeld.
    function ohneEndLeer(s) {
        var e = s.length;
        while (e > 0 && istLeer(s.charAt(e - 1))) {
            e--;
        }
        return s.slice(0, e);
    }

    function getrimmt(s) {
        var a = 0;
        while (a < s.length && istLeer(s.charAt(a))) {
            a++;
        }
        return ohneEndLeer(s.slice(a));
    }

    /**
     * Anzahl der Nicht-Leerraum-Zeichen in einer Zeilenliste (Zeichenketten
     * oder Zeilen {text, teile} aus umbrechenTeile).
     */
    function zeichenOhneLeer(zeilen) {
        var n = 0;
        for (var i = 0; i < zeilen.length; i++) {
            var z = typeof zeilen[i] === 'string' ? zeilen[i] : zeilen[i].text;
            for (var j = 0; j < z.length; j++) {
                if (!istLeer(z.charAt(j))) {
                    n++;
                }
            }
        }
        return n;
    }

    /**
     * Position im Quelltext hinter dem k-ten Nicht-Leerraum-Zeichen und dem
     * darauf folgenden Leerraum. umbrechen() und umbrechenTeile() entfernen
     * nur Leerraum (an Bruchstellen und Zeilenenden), die platzierten Zeilen
     * enthalten also genau die ersten k Nicht-Leerraum-Zeichen des Absatzes
     * in Originalreihenfolge.
     */
    function restIndex(text, k) {
        var i = 0;
        var gezaehlt = 0;
        while (i < text.length && gezaehlt < k) {
            if (!istLeer(text.charAt(i))) {
                gezaehlt++;
            }
            i++;
        }
        while (i < text.length && istLeer(text.charAt(i))) {
            i++;
        }
        return i;
    }

    /** Quelltext ab dem (k+1)-ten Nicht-Leerraum-Zeichen, ohne fuehrenden Leerraum. */
    function restAb(text, k) {
        return text.slice(restIndex(text, k));
    }

    /** Ersatz, falls der Aufrufer keine Messfunktion uebergibt. */
    function schaetzMass(t, px) {
        return t.length * px * 0.55;
    }

    // ---------------------------------------------------------------------
    // Absaetze mit Formatierung (PLAN-Blocktext-Formatierung.md, AP-2.2)
    //   Ein Absatz-Objekt {text, teile?} wird intern „normiert": Jede
    //   UTF-16-Einheit des Texts traegt eine Stil-Nummer (Index in liste,
    //   gleiche Stile teilen eine Nummer). Gemessen wird je Abschnitt
    //   gleichen Stils mit mass(t, px, {f, k}); die Teile einer Zeile oder
    //   eines Blocks entstehen aus denselben Abschnitten und sind dadurch
    //   schon zusammengelegt. Das entspricht der Messung von rahmen() in
    //   board-elemente.js (Summe je Teil), so stimmt die Blockbreite mit dem
    //   spaeteren Rahmen des Text-Elements ueberein.
    // ---------------------------------------------------------------------

    // Gleiche Regel wie FARB_MUSTER in board-elemente.js (und serverseitig
    // in CBD_Classroom::pruefe_elemente_json()). Hier nur, damit eine
    // unbrauchbare Teilfarbe wie in teileZusammenlegen() still entfaellt -
    // die Pruefung verwuerfe sonst beim Speichern die gesamte Formatierung.
    var FARB_MUSTER = /^(#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6}|rgba?\(\s*[0-9.]+\s*,\s*[0-9.]+\s*,\s*[0-9.]+\s*(,\s*[0-9.]+\s*)?\))$/;

    function istObjektWert(v) {
        return v !== null && typeof v === 'object' && !Array.isArray(v);
    }

    /**
     * true, wenn eine Grenze vor Index i ein Surrogatpaar trennen wuerde
     * (s[i - 1] High-, s[i] Low-Surrogat). Review AP-1.rev, Befund B1.
     */
    function trenntPaar(s, i) {
        if (i <= 0 || i >= s.length) {
            return false;
        }
        var hoch = s.charCodeAt(i - 1);
        var tief = s.charCodeAt(i);
        return hoch >= 0xD800 && hoch <= 0xDBFF && tief >= 0xDC00 && tief <= 0xDFFF;
    }

    /** Laenge des Zeichens ab Index i in UTF-16-Einheiten: 2 fuer ein Surrogatpaar, sonst 1. */
    function zeichenLaenge(s, i) {
        return trenntPaar(s, i + 1) ? 2 : 1;
    }

    /** Klartext-Absatz in normierter Form (stile null = alles ein Klartext-Abschnitt). */
    function klartext(text) {
        return { text: text, stile: null, liste: [] };
    }

    /**
     * Absatz -> {text, stile, liste} oder null (kein brauchbarer Absatz:
     * keine Zeichenkette/kein Objekt, text fehlt oder ist leer).
     * teile werden genutzt, wenn sie ein Array aus Objekten mit
     * Zeichenketten-t sind und ihre Verkettung genau text ist; sonst gilt
     * der Absatz als Klartext. f/k werden wie in teileZusammenlegen() zu 0/1,
     * eine Farbe ausserhalb FARB_MUSTER entfaellt, leere t tragen nichts bei.
     * Ein Low-Surrogat erbt den Stil seines High-Surrogats - so trennt auch
     * eine Teilgrenze mitten im Paar (Eingabe) das Paar in der Ausgabe nicht.
     */
    function absatzNormieren(absatz) {
        if (typeof absatz === 'string') {
            return absatz === '' ? null : klartext(absatz);
        }
        if (!istObjektWert(absatz) || typeof absatz.text !== 'string' || absatz.text === '') {
            return null;
        }
        var text = absatz.text;
        var teile = absatz.teile;
        var i;
        if (!Array.isArray(teile)) {
            return klartext(text);
        }
        var kette = '';
        for (i = 0; i < teile.length; i++) {
            if (!istObjektWert(teile[i]) || typeof teile[i].t !== 'string') {
                return klartext(text);
            }
            kette += teile[i].t;
            // frueh abbrechen, statt einen ueberlangen Text weiter aufzubauen
            if (kette.length > text.length) {
                return klartext(text);
            }
        }
        if (kette !== text) {
            return klartext(text);
        }
        var liste = [];
        var nummer = {};
        var stile = [];
        for (i = 0; i < teile.length; i++) {
            var teil = teile[i];
            var f = teil.f ? 1 : 0;
            var k = teil.k ? 1 : 0;
            var farbe = (typeof teil.farbe === 'string' && FARB_MUSTER.test(teil.farbe)) ? teil.farbe : '';
            var schluessel = f + '|' + k + '|' + farbe;
            if (!nummer.hasOwnProperty(schluessel)) {
                nummer[schluessel] = liste.length;
                liste.push({ f: f, k: k, farbe: farbe });
            }
            for (var j = 0; j < teil.t.length; j++) {
                stile.push(nummer[schluessel]);
            }
        }
        for (i = 1; i < text.length; i++) {
            if (trenntPaar(text, i)) {
                stile[i] = stile[i - 1];
            }
        }
        return { text: text, stile: stile, liste: liste };
    }

    /** \r\n und einzelnes \r -> \n wie in umbrechen(); die Stile laufen mit. */
    function zeilenwechselNormieren(n) {
        if (n.text.indexOf('\r') < 0) {
            return n;
        }
        var text = '';
        var stile = n.stile ? [] : null;
        for (var i = 0; i < n.text.length; i++) {
            var c = n.text.charAt(i);
            if (c === '\r') {
                if (n.text.charAt(i + 1) === '\n') {
                    continue;
                }
                c = '\n';
            }
            text += c;
            if (stile) {
                stile.push(n.stile[i]);
            }
        }
        return { text: text, stile: stile, liste: n.liste };
    }

    /**
     * Ruft tu(von, bis, stil) fuer jeden Abschnitt gleichen Stils in
     * n.text[a, e) auf; stil ist {f, k, farbe} oder null (Klartext).
     */
    function jeAbschnitt(n, a, e, tu) {
        var i = a;
        while (i < e) {
            var nr = n.stile ? n.stile[i] : -1;
            var j = i + 1;
            while (j < e && (n.stile ? n.stile[j] : -1) === nr) {
                j++;
            }
            tu(i, j, nr < 0 ? null : n.liste[nr]);
            i = j;
        }
    }

    /** Teil {t[, f][, k][, farbe]} - Schluessel wie in teileZusammenlegen(). */
    function teilObjekt(t, stil) {
        var teil = { t: t };
        if (stil) {
            if (stil.f) {
                teil.f = 1;
            }
            if (stil.k) {
                teil.k = 1;
            }
            if (stil.farbe) {
                teil.farbe = stil.farbe;
            }
        }
        return teil;
    }

    /** Messstil {f: 0|1, k: 0|1} eines Stils oder Teils (null = normal). */
    function messStil(stil) {
        return { f: stil && stil.f ? 1 : 0, k: stil && stil.k ? 1 : 0 };
    }

    /** Teile von n.text[a, e): je Abschnitt gleichen Stils ein Teil. */
    function teileVon(n, a, e) {
        var aus = [];
        jeAbschnitt(n, a, e, function (von, bis, stil) {
            aus.push(teilObjekt(n.text.slice(von, bis), stil));
        });
        return aus;
    }

    /**
     * Breite von n.text[a, e): Summe der Abschnitte, jeder in seiner Schrift.
     * cache (optional, {bis: [], w: []} je Umbruch und Schriftgroesse)
     * merkt sich je Abschnittsanfang das zuletzt gemessene Ende und die
     * Breite: Beim Umbruch waechst ein Kandidat Wort fuer Wort, die vorderen
     * Abschnitte bleiben gleich und wuerden sonst bei jedem Kandidaten neu
     * gemessen (im Browser je Abschnitt Schrift setzen plus measureText).
     * Gleiche Werte, rund ein Viertel der Aufrufe.
     */
    function breiteVon(n, a, e, px, mass, cache) {
        var b = 0;
        jeAbschnitt(n, a, e, function (von, bis, stil) {
            if (cache && cache.bis[von] === bis) {
                b += cache.w[von];
                return;
            }
            var w = mass(n.text.slice(von, bis), px, messStil(stil));
            if (cache) {
                cache.bis[von] = bis;
                cache.w[von] = w;
            }
            b += w;
        });
        return b;
    }

    /** true, wenn ein Teil fett, kursiv oder farbig ist. */
    function istFormatiert(teile) {
        for (var i = 0; i < teile.length; i++) {
            if (teile[i].f || teile[i].k || teile[i].farbe) {
                return true;
            }
        }
        return false;
    }

    function gleicherStil(a, b) {
        return (a.f || 0) === (b.f || 0) && (a.k || 0) === (b.k || 0) && (a.farbe || '') === (b.farbe || '');
    }

    /** Teil anhaengen; mit gleichem Stil wie der letzte wird er mit ihm verbunden. */
    function teilAnhaengen(liste, teil) {
        var letzter = liste.length ? liste[liste.length - 1] : null;
        if (letzter && gleicherStil(letzter, teil)) {
            letzter.t += teil.t;
        } else {
            liste.push(teilObjekt(teil.t, teil));
        }
    }

    /** Teile im Bereich [a, e) einer Teil-Liste (Positionen in UTF-16-Einheiten). */
    function teileAusschnitt(teile, a, e) {
        var aus = [];
        var pos = 0;
        for (var i = 0; i < teile.length && pos < e; i++) {
            var t = teile[i].t;
            var von = Math.max(a, pos);
            var bis = Math.min(e, pos + t.length);
            if (bis > von) {
                aus.push(teilObjekt(t.slice(von - pos, bis - pos), teile[i]));
            }
            pos += t.length;
        }
        return aus;
    }

    /**
     * Absatz-Objekt fuer „rest" ab Position a des (rohen) Texts:
     * {text[, teile]} - teile nur, wenn der Abschnitt Formatierung enthaelt.
     */
    function absatzAusgabe(n, a) {
        var aus = { text: n.text.slice(a) };
        var teile = teileVon(n, a, n.text.length);
        if (istFormatiert(teile)) {
            aus.teile = teile;
        }
        return aus;
    }

    /**
     * Text -> Absaetze. Getrennt wird an Zeilen, die nur Leerraum enthalten;
     * Zeilenenden und Absatz werden getrimmt, einzelne Zeilenwechsel bleiben.
     */
    function absaetzeAusText(text) {
        if (typeof text !== 'string') {
            return [];
        }
        var teile = text.replace(/\r\n?/g, '\n').split(/\n\s*\n/);
        var aus = [];
        for (var i = 0; i < teile.length; i++) {
            var zeilen = teile[i].split('\n');
            for (var j = 0; j < zeilen.length; j++) {
                zeilen[j] = ohneEndLeer(zeilen[j]);
            }
            var absatz = getrimmt(zeilen.join('\n'));
            if (absatz !== '') {
                aus.push(absatz);
            }
        }
        return aus;
    }

    /** Ende des Bereichs s[a, e) ohne Leerraum am Ende (Bereich statt Teilzeichenkette). */
    function endeOhneLeer(s, a, e) {
        while (e > a && istLeer(s.charAt(e - 1))) {
            e--;
        }
        return e;
    }

    /**
     * Eine harte Zeile s[za, ze) umbrechen; haengt jede Zeile als
     * Bereichspaar (Anfang, Ende) an „aus" an. messe(a, e) liefert die
     * Breite von s[a, e).
     *
     * Bis AP-2.2 arbeitete diese Regel auf Teilzeichenketten
     * (umbrecheZeile); sie ist unveraendert: Bruch an einzelnen
     * Leerzeichen (wie zeile.split(' ')), Leerraum an Bruchstellen und am
     * Zeilenende entfaellt, ein Wort breiter als die Zeile wird zeichenweise
     * geteilt und sein letzter Teil bleibt offen. Jeder Kandidat ist ein
     * zusammenhaengender Abschnitt der Zeile (akt + ' ' + wort folgt im
     * Text direkt aufeinander), deshalb genuegen Bereiche - und ein Absatz
     * mit Teilen kann je Abschnitt in seiner Schrift gemessen werden. Die
     * Messfunktion wird mit denselben Texten in derselben Reihenfolge
     * gerufen wie vorher.
     *
     * Einzige Aenderung (Review AP-1.rev, Befund B1): „zeichenweise" heisst
     * je Codepunkt, ein Surrogatpaar (Emoji) wird nie getrennt.
     */
    function umbrecheBereich(s, za, ze, grenze, messe, aus) {
        if (messe(za, ze) <= grenze) {
            aus.push(za, endeOhneLeer(s, za, ze));
            return;
        }
        // Woerter wie zeile.split(' '): Bereiche zwischen einzelnen Leerzeichen.
        var woerter = [];
        var wa = za;
        for (var p = za; p <= ze; p++) {
            if (p === ze || s.charAt(p) === ' ') {
                woerter.push(wa, p);
                wa = p + 1;
            }
        }
        // akt = s[aktA, aktE); leer, wenn aktA === aktE. Ist akt nicht leer,
        // steht direkt dahinter das Leerzeichen vor dem naechsten Wort.
        var aktA = za;
        var aktE = za;
        for (var w = 0; w < woerter.length; w += 2) {
            var a = woerter[w];
            var e = woerter[w + 1];
            var ka = aktA === aktE ? a : aktA;
            if (messe(ka, e) <= grenze) {
                aktA = ka;
                aktE = e;
                continue;
            }
            // Umbruchstelle: Leerzeichen dort entfallen.
            var ende = endeOhneLeer(s, aktA, aktE);
            if (ende > aktA) {
                aus.push(aktA, ende);
            }
            aktA = e;
            aktE = e;
            if (a === e) {
                continue;
            }
            if (messe(a, e) <= grenze) {
                aktA = a;
                continue;
            }
            // Ein Wort, breiter als die Zeile: je Codepunkt teilen. Der letzte
            // Teil bleibt offen, das naechste Wort darf anschliessen.
            var sa = a;
            for (var c = a; c < e; c += zeichenLaenge(s, c)) {
                if (c > sa && messe(sa, c + zeichenLaenge(s, c)) > grenze) {
                    aus.push(sa, c);
                    sa = c;
                }
            }
            aktA = sa;
        }
        var rest = endeOhneLeer(s, aktA, aktE);
        if (rest > aktA) {
            aus.push(aktA, rest);
        }
    }

    /** Text (Zeilenwechsel schon \n) an \n und auf grenze umbrechen -> Bereichspaare. */
    function bereicheUmbrechen(s, grenze, messe) {
        var aus = [];
        var a = 0;
        for (;;) {
            var e = s.indexOf('\n', a);
            if (e < 0) {
                e = s.length;
            }
            umbrecheBereich(s, a, e, grenze, messe, aus);
            if (e >= s.length) {
                return aus;
            }
            a = e + 1;
        }
    }

    function grenzeAus(maxBreite) {
        return (typeof maxBreite === 'number' && maxBreite > 0) ? maxBreite : Infinity;
    }

    /**
     * Text auf eine Breite umbrechen -> Zeilen. Vorhandene \n bleiben
     * Zeilenwechsel; gebrochen wird an Leerzeichen so, dass
     * mass(zeile, px) <= maxBreite; ein zu breites Wort wird je Codepunkt
     * geteilt. mass(text, px) liefert die Breite in Pixeln (hier immer mit
     * zwei Werten gerufen, wie vor AP-2.2).
     */
    function umbrechen(text, maxBreite, px, mass) {
        if (typeof text !== 'string' || text === '') {
            return [];
        }
        var m = typeof mass === 'function' ? mass : schaetzMass;
        var s = text.replace(/\r\n?/g, '\n');
        var bereiche = bereicheUmbrechen(s, grenzeAus(maxBreite), function (a, e) {
            return m(s.slice(a, e), px);
        });
        var aus = [];
        for (var i = 0; i < bereiche.length; i += 2) {
            aus.push(s.slice(bereiche[i], bereiche[i + 1]));
        }
        return aus;
    }

    /** Normierten Absatz (Zeilenwechsel schon \n) umbrechen -> [{text, teile}]. */
    function zeilenMitTeilen(n, maxBreite, px, mass) {
        var m = typeof mass === 'function' ? mass : schaetzMass;
        var cache = { bis: [], w: [] };
        var bereiche = bereicheUmbrechen(n.text, grenzeAus(maxBreite), function (a, e) {
            return breiteVon(n, a, e, px, m, cache);
        });
        var aus = [];
        for (var i = 0; i < bereiche.length; i += 2) {
            aus.push({
                text: n.text.slice(bereiche[i], bereiche[i + 1]),
                teile: teileVon(n, bereiche[i], bereiche[i + 1])
            });
        }
        return aus;
    }

    /**
     * Absatz (Zeichenkette oder {text, teile?}) auf eine Breite umbrechen ->
     * [{text: zeile, teile}]. Wortgrenzen und Sonderfaelle wie umbrechen();
     * gemessen wird je Abschnitt gleichen Stils mit mass(t, px, {f, k}).
     * teile je Zeile: Verkettung === zeile, benachbarte gleiche Teile
     * verbunden, Klartext als [{t: zeile}], leere Zeile -> []. Unbrauchbare
     * Eingabe -> [].
     */
    function umbrechenTeile(absatz, maxBreite, px, mass) {
        var n = absatzNormieren(absatz);
        if (!n) {
            return [];
        }
        return zeilenMitTeilen(zeilenwechselNormieren(n), maxBreite, px, mass);
    }

    /**
     * Zeilen eines Absatzes (Zeichenketten) in Gruppen von hoechstens max
     * Zeichen (mit \n) -> [[{z: Zeilennummer, a, e}]] (Bereich der Zeile).
     * Eine einzelne Zeile ueber max wird in Stuecke von max Zeichen geteilt -
     * eine Grenze mitten in einem Surrogatpaar rueckt eine Einheit vor
     * (Review AP-1.rev, Befund B1); nur bei max = 1 rueckt sie dahinter, das
     * Stueck hat dann 2 Einheiten.
     */
    function teileNachZeichen(zeilen, max) {
        var gruppen = [];
        var akt = [];
        var laenge = 0;
        for (var i = 0; i < zeilen.length; i++) {
            var z = zeilen[i];
            if (z.length > max) {
                if (akt.length) {
                    gruppen.push(akt);
                    akt = [];
                    laenge = 0;
                }
                var s = 0;
                while (s < z.length) {
                    var e = Math.min(s + max, z.length);
                    if (trenntPaar(z, e)) {
                        e = e - 1 > s ? e - 1 : e + 1;
                    }
                    gruppen.push([{ z: i, a: s, e: e }]);
                    s = e;
                }
                continue;
            }
            var neu = akt.length ? laenge + 1 + z.length : z.length;
            if (neu > max) {
                gruppen.push(akt);
                akt = [{ z: i, a: 0, e: z.length }];
                laenge = z.length;
            } else {
                akt.push({ z: i, a: 0, e: z.length });
                laenge = neu;
            }
        }
        if (akt.length) {
            gruppen.push(akt);
        }
        return gruppen;
    }

    /**
     * Zeilenlisten -> Bloecke {text, [teile,] x, y, b, h}, fortlaufend ab
     * flaeche.y. Eine Zeilenliste besteht aus Zeichenketten (Absatz als
     * Zeichenkette, gemessen mit mass(zeile, px) wie vor AP-2.2) oder aus
     * Zeilen {text, teile} (Absatz-Objekt): Dann ist die Breite einer Zeile
     * die Summe ihrer Teile, jeder in seiner Schrift, und der Block traegt
     * teile, wenn er Formatierung enthaelt - Verkettung === text, das \n
     * zwischen zwei Zeilen gehoert zum vorigen Teil.
     */
    function zuBloecken(zeilenListe, f, px, mass, maxZeichen) {
        var zh = ZEILENHOEHE * px;
        var abstand = Math.round(ABSATZ_ABSTAND * px);
        var y = f.y;
        var aus = [];
        for (var i = 0; i < zeilenListe.length; i++) {
            if (i > 0) {
                y += abstand;
            }
            var zeilen = zeilenListe[i];
            var mitTeilen = zeilen.length > 0 && typeof zeilen[0] !== 'string';
            var texte = zeilen;
            if (mitTeilen) {
                texte = [];
                for (var zt = 0; zt < zeilen.length; zt++) {
                    texte.push(zeilen[zt].text);
                }
            }
            // Ueberlange Absaetze: Teile direkt untereinander, ohne Abstand.
            var gruppen = teileNachZeichen(texte, maxZeichen);
            for (var g = 0; g < gruppen.length; g++) {
                var gruppe = gruppen[g];
                var stuecke = [];
                var blockTeile = [];
                var b = 0;
                for (var z = 0; z < gruppe.length; z++) {
                    var q = gruppe[z];
                    var stueck = texte[q.z].slice(q.a, q.e);
                    stuecke.push(stueck);
                    if (!mitTeilen) {
                        b = Math.max(b, mass(stueck, px));
                        continue;
                    }
                    var zeilenTeile = teileAusschnitt(zeilen[q.z].teile, q.a, q.e);
                    var breite = 0;
                    if (z > 0) {
                        if (blockTeile.length) {
                            blockTeile[blockTeile.length - 1].t += '\n';
                        } else {
                            blockTeile.push({ t: '\n' });
                        }
                    }
                    for (var p = 0; p < zeilenTeile.length; p++) {
                        breite += mass(zeilenTeile[p].t, px, messStil(zeilenTeile[p]));
                        teilAnhaengen(blockTeile, zeilenTeile[p]);
                    }
                    b = Math.max(b, breite);
                }
                var h = gruppe.length * zh;
                var block = { text: stuecke.join('\n') };
                if (mitTeilen && istFormatiert(blockTeile)) {
                    block.teile = blockTeile;
                }
                block.x = f.x;
                block.y = y;
                block.b = b;
                block.h = h;
                aus.push(block);
                y += h;
            }
        }
        return aus;
    }

    /** Zeilen eines Listeneintrags von verteile(): Zeichenkette oder normiertes Objekt. */
    function zeilenVon(eintrag, breite, px, mass) {
        if (typeof eintrag === 'string') {
            return umbrechen(eintrag, breite, px, mass);
        }
        return zeilenMitTeilen(eintrag.umbruch, breite, px, mass);
    }

    /** Eintrag von verteile() unveraendert fuer „rest": Zeichenkette oder {text[, teile]}. */
    function restEintrag(eintrag) {
        return typeof eintrag === 'string' ? eintrag : absatzAusgabe(eintrag.roh, 0);
    }

    /**
     * Absaetze in einer Flaeche {x, y, b, h} anordnen.
     * optionen: {startPx: 24, minPx: 18, maxZeichen: 5000}.
     * Rueckgabe {px, bloecke: [{text, teile?, x, y, b, h}], rest: [Absatz]}.
     * Die Schrift sinkt in Schritten von 1 px von startPx bis minPx, bis
     * alles passt. Passt es auch mit minPx nicht, wird von oben aufgefuellt;
     * ein teilweise passender Absatz wird an einer Zeilengrenze geteilt. Der
     * erste Eintrag von „rest" ist dann der unveraenderte restliche
     * Quelltext dieses Absatzes (ohne die weichen Umbrueche dieser Flaeche,
     * harte \n bleiben; AP-1.fix1), danach folgen die uebrigen Absaetze
     * unveraendert.
     *
     * Absaetze duerfen seit AP-2.2 auch Objekte {text, teile?} sein (siehe
     * Kopfkommentar). Ein Objekt mit leerem oder fehlendem text wird
     * ignoriert wie eine leere Zeichenkette. Bloecke tragen teile nur mit
     * Formatierung. „rest" steht in der Form der Eingabe: Zeichenketten
     * bleiben Zeichenketten, Objekte kommen als neues Objekt {text[, teile]}
     * zurueck (beim geteilten Absatz ab der Teilungsstelle, nach derselben
     * Regel wie bei Zeichenketten; teile nur, wenn der Rest formatiert ist).
     */
    function verteile(absaetze, flaeche, mass, optionen) {
        var o = optionen || {};
        var startPx = Math.round(zahl(o.startPx, VORGABE_START_PX));
        var minPx = Math.min(startPx, Math.round(zahl(o.minPx, VORGABE_MIN_PX)));
        var maxZeichen = Math.max(1, Math.floor(zahl(o.maxZeichen, VORGABE_MAX_ZEICHEN)));
        var m = typeof mass === 'function' ? mass : schaetzMass;
        // Eintraege: Zeichenkette (Weg wie vor AP-2.2) oder {roh, umbruch} -
        // roh = normierter Absatz mit Originaltext (fuer den Rest), umbruch =
        // derselbe mit Zeilenwechseln als \n (fuer den Umbruch).
        var liste = [];
        if (Array.isArray(absaetze)) {
            for (var a = 0; a < absaetze.length; a++) {
                var eintrag = absaetze[a];
                if (typeof eintrag === 'string') {
                    if (eintrag !== '') {
                        liste.push(eintrag);
                    }
                    continue;
                }
                var n = absatzNormieren(eintrag);
                if (n) {
                    liste.push({ roh: n, umbruch: zeilenwechselNormieren(n) });
                }
            }
        }
        if (!liste.length) {
            return { px: startPx, bloecke: [], rest: [] };
        }
        var f = flaeche || {};
        f = { x: zahl(f.x, 0), y: zahl(f.y, 0), b: zahl(f.b, 0), h: zahl(f.h, 0) };

        var px, zh, abstand, gesetzt, i;
        for (px = startPx; px >= minPx; px--) {
            zh = ZEILENHOEHE * px;
            abstand = Math.round(ABSATZ_ABSTAND * px);
            gesetzt = [];
            var hoehe = 0;
            for (i = 0; i < liste.length; i++) {
                gesetzt.push(zeilenVon(liste[i], f.b, px, m));
                hoehe += (i > 0 ? abstand : 0) + gesetzt[i].length * zh;
            }
            if (hoehe <= f.h + EPS) {
                return { px: px, bloecke: zuBloecken(gesetzt, f, px, m, maxZeichen), rest: [] };
            }
        }

        // Passt auch mit minPx nicht: von oben auffuellen.
        px = minPx;
        zh = ZEILENHOEHE * px;
        abstand = Math.round(ABSATZ_ABSTAND * px);
        var platziert = [];
        var rest = [];
        var belegt = 0;
        for (i = 0; i < liste.length; i++) {
            var zeilen = zeilenVon(liste[i], f.b, px, m);
            var oben = platziert.length ? belegt + abstand : 0;
            var passen = Math.floor((f.h - oben) / zh + EPS);
            if (passen >= zeilen.length) {
                platziert.push(zeilen);
                belegt = oben + zeilen.length * zh;
                continue;
            }
            if (passen > 0) {
                // Rest als unveraenderter Quelltext ab der Teilungsstelle:
                // Weiche Umbrueche dieser Flaeche gehoeren nicht hinein, sonst
                // entsteht auf der Folgeseite (24 px, evtl. andere Breite) ein
                // Treppenumbruch, und harte \n waeren nicht mehr erkennbar
                // (Review AP-1.rev, M1; AP-1.fix1).
                platziert.push(zeilen.slice(0, passen));
                var gesetzteZeichen = zeichenOhneLeer(zeilen.slice(0, passen));
                if (typeof liste[i] === 'string') {
                    rest.push(restAb(liste[i], gesetzteZeichen));
                } else {
                    rest.push(absatzAusgabe(liste[i].roh, restIndex(liste[i].roh.text, gesetzteZeichen)));
                }
            } else {
                rest.push(restEintrag(liste[i]));
            }
            for (var k = i + 1; k < liste.length; k++) {
                rest.push(restEintrag(liste[k]));
            }
            break;
        }
        return { px: px, bloecke: zuBloecken(platziert, f, px, m, maxZeichen), rest: rest };
    }

    /**
     * Freie Flaeche unter vorhandenem Inhalt im sichtbaren Bereich.
     * sichtbar und rahmenListe in Modell-Koordinaten {x, y, b, h}; zaehlt
     * nur Rahmen, die sich waagrecht mit dem sichtbaren Bereich
     * ueberschneiden und oberhalb seiner Unterkante beginnen - was ganz
     * darunter liegt, ist nicht zu sehen und wird nicht ueberdeckt (bis
     * AP-2.fix1 zaehlte es mit; bei Zoom > 1 hiess es dann "kein Platz",
     * obwohl der Ausschnitt leer war, Review-Befund G4).
     * Rueckgabe {x, y, b, h}, h nie negativ.
     */
    function flaecheUnter(sichtbar, rahmenListe, rand) {
        var s = sichtbar || {};
        s = { x: zahl(s.x, 0), y: zahl(s.y, 0), b: zahl(s.b, 0), h: zahl(s.h, 0) };
        var r = zahl(rand, 0);
        var y = s.y + r;
        if (Array.isArray(rahmenListe)) {
            for (var i = 0; i < rahmenListe.length; i++) {
                var q = rahmenListe[i];
                if (!q || !isFinite(q.x) || !isFinite(q.y) || !isFinite(q.b) || !isFinite(q.h)) {
                    continue;
                }
                if (q.x < s.x + s.b && q.x + q.b > s.x && q.y < s.y + s.h) {
                    y = Math.max(y, q.y + q.h + r);
                }
            }
        }
        var h = s.y + s.h - r - y;
        return { x: s.x + r, y: y, b: Math.max(0, s.b - 2 * r), h: h > 0 ? h : 0 };
    }

    /** '#rgb' oder '#rrggbb' -> '#rrggbb' (klein) oder null. */
    function hexFarbe(f) {
        if (typeof f !== 'string') {
            return null;
        }
        var t = f.replace(/^\s+|\s+$/g, '').toLowerCase();
        var kurz = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec(t);
        if (kurz) {
            return '#' + kurz[1] + kurz[1] + kurz[2] + kurz[2] + kurz[3] + kurz[3];
        }
        return /^#[0-9a-f]{6}$/.test(t) ? t : null;
    }

    /** Relative Leuchtdichte nach WCAG 2. */
    function leuchtdichte(hex) {
        function linear(stelle) {
            var c = parseInt(hex.substr(stelle, 2), 16) / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * linear(1) + 0.7152 * linear(3) + 0.0722 * linear(5);
    }

    function kontrast(a, b) {
        var la = leuchtdichte(a);
        var lb = leuchtdichte(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    /**
     * Stiftfarbe, wenn sie zur Tafel mindestens 3:1 Kontrast hat; sonst
     * Weiss oder Schwarz, je nachdem, was mehr Kontrast hat. Ungueltiger
     * Stift gilt als Schwarz, ungueltige Tafel als Weiss.
     */
    function lesbareFarbe(stift, tafel) {
        var s = hexFarbe(stift) || '#000000';
        var t = hexFarbe(tafel) || '#ffffff';
        if (kontrast(s, t) >= 3) {
            return s;
        }
        return kontrast('#ffffff', t) >= kontrast('#000000', t) ? '#ffffff' : '#000000';
    }

    function hexZweistellig(wert) {
        return (wert < 16 ? '0' : '') + wert.toString(16);
    }

    /**
     * Teilfarbe lesbar machen (PLAN-Blocktext-Formatierung.md, F7): Hat
     * farbe zur Tafel mindestens 3:1 Kontrast, bleibt sie (als '#rrggbb'
     * klein). Sonst wird sie je Kanal mit Weiss (dunkle Tafel) bzw. Schwarz
     * (helle Tafel) gemischt - farbe + (ziel - farbe) * s, gerundet, mit
     * s = 0,05, 0,10, ..., 1 -, bis der Kontrast >= 3 ist. „Dunkel" heisst:
     * Weiss hat zur Tafel mindestens so viel Kontrast wie Schwarz. So bleibt
     * Dunkelrot auf gruener Tafel rot (aufgehellt) statt wie bei
     * lesbareFarbe() weiss zu werden. Idempotent (ein Ergebnis hat schon
     * Kontrast >= 3). Ungueltige farbe (nicht '#rgb'/'#rrggbb') -> null,
     * ungueltige tafel gilt als Weiss. Bei s = 1 ist das Ziel erreicht:
     * Weiss oder Schwarz hat zu jeder Farbe mindestens Wurzel(21) > 4,5.
     */
    function lesbareLauffarbe(farbe, tafel) {
        var f = hexFarbe(farbe);
        if (!f) {
            return null;
        }
        var t = hexFarbe(tafel) || '#ffffff';
        if (kontrast(f, t) >= 3) {
            return f;
        }
        var ziel = kontrast('#ffffff', t) >= kontrast('#000000', t) ? 255 : 0;
        var kanaele = [parseInt(f.substr(1, 2), 16), parseInt(f.substr(3, 2), 16), parseInt(f.substr(5, 2), 16)];
        for (var stufe = 1; stufe <= 20; stufe++) {
            // stufe / 20 statt aufaddierter 0,05: keine Rundungsdrift
            var s = stufe / 20;
            var neu = '#';
            for (var k = 0; k < 3; k++) {
                neu += hexZweistellig(Math.round(kanaele[k] + (ziel - kanaele[k]) * s));
            }
            if (kontrast(neu, t) >= 3) {
                return neu;
            }
        }
        return ziel === 255 ? '#ffffff' : '#000000';
    }

    // =====================================================================
    // Oeffentlich
    // =====================================================================

    function aufraeumen(t) {
        return t.split(BS).join('')
            .replace(/[\x00-\x08\x0e-\x1f\x7f]/g, '')
            .replace(/\s+/g, ' ')
            .replace(/([(\[]) /g, '$1')
            .replace(/ ([)\]])/g, '$1')
            .replace(/^ | $/g, '');
    }

    function latexZuText(latex, protokoll) {
        if (typeof latex !== 'string' || latex === '') {
            return '';
        }
        var p = (protokoll && typeof protokoll === 'object') ? protokoll : null;
        try {
            return aufraeumen(folge({ s: latex, i: 0, n: latex.length, p: p, tiefe: 0 }, false, false));
        } catch (e) {
            return latex.split(BS).join('').replace(/\s+/g, ' ').replace(/^ | $/g, '');
        }
    }

    global.CBDBoardBlocktext = {
        latexZuText: latexZuText,
        absaetzeAusText: absaetzeAusText,
        umbrechen: umbrechen,
        umbrechenTeile: umbrechenTeile,
        verteile: verteile,
        flaecheUnter: flaecheUnter,
        lesbareFarbe: lesbareFarbe,
        lesbareLauffarbe: lesbareLauffarbe
    };
})(window);
