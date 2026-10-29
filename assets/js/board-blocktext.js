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
 * Messfunktion mass(text, px) -> Breite in Pixeln (im Browser measureText).
 *
 * Schnittstelle (Plan, Abschnitt 4) - genau diese sechs Funktionen:
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
 *     breite Woerter werden zeichenweise geteilt.
 *   verteile(absaetze, flaeche, mass, optionen)                     (AP-1.3)
 *     -> {px, bloecke: [{text, x, y, b, h}], rest: [string]}
 *     Schrift 24 -> 18 px, bis alles in flaeche {x, y, b, h} passt; sonst
 *     auffuellen und Rest zurueckgeben (ein geteilter Absatz als restlicher
 *     Quelltext ohne weiche Umbrueche). Je Absatz ein Block (Zeilen mit \n),
 *     Abstand Math.round(0,6 * px), Bloecke hoechstens 5000 Zeichen.
 *   flaecheUnter(sichtbar, rahmenListe, rand) -> {x, y, b, h}       (AP-1.3)
 *     Freier Platz unter vorhandenem Inhalt im sichtbaren Bereich (Inhalt
 *     ganz unterhalb des Bereichs zaehlt nicht, AP-2.fix1).
 *   lesbareFarbe(stift, tafel) -> '#rrggbb'                         (AP-1.3)
 *     Stiftfarbe bei mindestens 3:1 Kontrast (WCAG), sonst Weiss/Schwarz.
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

    /** Anzahl der Nicht-Leerraum-Zeichen in einer Zeilenliste. */
    function zeichenOhneLeer(zeilen) {
        var n = 0;
        for (var i = 0; i < zeilen.length; i++) {
            for (var j = 0; j < zeilen[i].length; j++) {
                if (!istLeer(zeilen[i].charAt(j))) {
                    n++;
                }
            }
        }
        return n;
    }

    /**
     * Quelltext ab dem (k+1)-ten Nicht-Leerraum-Zeichen, ohne fuehrenden
     * Leerraum. umbrechen() entfernt nur Leerraum (an Bruchstellen und
     * Zeilenenden), die platzierten Zeilen enthalten also genau die ersten k
     * Nicht-Leerraum-Zeichen des Absatzes in Originalreihenfolge.
     */
    function restAb(text, k) {
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
        return text.slice(i);
    }

    /** Ersatz, falls der Aufrufer keine Messfunktion uebergibt. */
    function schaetzMass(t, px) {
        return t.length * px * 0.55;
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

    /** Eine harte Zeile umbrechen; haengt die Zeilen an „aus" an. */
    function umbrecheZeile(zeile, grenze, px, mass, aus) {
        if (mass(zeile, px) <= grenze) {
            aus.push(ohneEndLeer(zeile));
            return;
        }
        var woerter = zeile.split(' ');
        var akt = '';
        for (var w = 0; w < woerter.length; w++) {
            var wort = woerter[w];
            var kandidat = akt === '' ? wort : akt + ' ' + wort;
            if (mass(kandidat, px) <= grenze) {
                akt = kandidat;
                continue;
            }
            // Umbruchstelle: Leerzeichen dort entfallen.
            if (ohneEndLeer(akt) !== '') {
                aus.push(ohneEndLeer(akt));
            }
            akt = '';
            if (wort === '') {
                continue;
            }
            if (mass(wort, px) <= grenze) {
                akt = wort;
                continue;
            }
            // Ein Wort, breiter als die Zeile: zeichenweise teilen. Der letzte
            // Teil bleibt offen, das naechste Wort darf anschliessen.
            var stueck = '';
            for (var c = 0; c < wort.length; c++) {
                var zeichen = wort.charAt(c);
                if (stueck !== '' && mass(stueck + zeichen, px) > grenze) {
                    aus.push(stueck);
                    stueck = '';
                }
                stueck += zeichen;
            }
            akt = stueck;
        }
        if (ohneEndLeer(akt) !== '') {
            aus.push(ohneEndLeer(akt));
        }
    }

    /**
     * Text auf eine Breite umbrechen -> Zeilen. Vorhandene \n bleiben
     * Zeilenwechsel; gebrochen wird an Leerzeichen so, dass
     * mass(zeile, px) <= maxBreite; ein zu breites Wort wird zeichenweise
     * geteilt. mass(text, px) liefert die Breite in Pixeln.
     */
    function umbrechen(text, maxBreite, px, mass) {
        if (typeof text !== 'string' || text === '') {
            return [];
        }
        var m = typeof mass === 'function' ? mass : schaetzMass;
        var grenze = (typeof maxBreite === 'number' && maxBreite > 0) ? maxBreite : Infinity;
        var harte = text.replace(/\r\n?/g, '\n').split('\n');
        var aus = [];
        for (var i = 0; i < harte.length; i++) {
            umbrecheZeile(harte[i], grenze, px, m, aus);
        }
        return aus;
    }

    /** Zeilen eines Absatzes in Teile von hoechstens max Zeichen (mit \n). */
    function teileNachZeichen(zeilen, max) {
        var teile = [];
        var akt = [];
        var laenge = 0;
        for (var i = 0; i < zeilen.length; i++) {
            var z = zeilen[i];
            if (z.length > max) {
                if (akt.length) {
                    teile.push(akt);
                    akt = [];
                    laenge = 0;
                }
                for (var s = 0; s < z.length; s += max) {
                    teile.push([z.slice(s, s + max)]);
                }
                continue;
            }
            var neu = akt.length ? laenge + 1 + z.length : z.length;
            if (neu > max) {
                teile.push(akt);
                akt = [z];
                laenge = z.length;
            } else {
                akt.push(z);
                laenge = neu;
            }
        }
        if (akt.length) {
            teile.push(akt);
        }
        return teile;
    }

    /** Zeilenlisten -> Bloecke {text, x, y, b, h}, fortlaufend ab flaeche.y. */
    function zuBloecken(zeilenListe, f, px, mass, maxZeichen) {
        var zh = ZEILENHOEHE * px;
        var abstand = Math.round(ABSATZ_ABSTAND * px);
        var y = f.y;
        var aus = [];
        for (var i = 0; i < zeilenListe.length; i++) {
            if (i > 0) {
                y += abstand;
            }
            // Ueberlange Absaetze: Teile direkt untereinander, ohne Abstand.
            var teile = teileNachZeichen(zeilenListe[i], maxZeichen);
            for (var t = 0; t < teile.length; t++) {
                var zeilen = teile[t];
                var b = 0;
                for (var z = 0; z < zeilen.length; z++) {
                    b = Math.max(b, mass(zeilen[z], px));
                }
                var h = zeilen.length * zh;
                aus.push({ text: zeilen.join('\n'), x: f.x, y: y, b: b, h: h });
                y += h;
            }
        }
        return aus;
    }

    /**
     * Absaetze in einer Flaeche {x, y, b, h} anordnen.
     * optionen: {startPx: 24, minPx: 18, maxZeichen: 5000}.
     * Rueckgabe {px, bloecke: [{text, x, y, b, h}], rest: [string]}.
     * Die Schrift sinkt in Schritten von 1 px von startPx bis minPx, bis
     * alles passt. Passt es auch mit minPx nicht, wird von oben aufgefuellt;
     * ein teilweise passender Absatz wird an einer Zeilengrenze geteilt. Der
     * erste Eintrag von „rest" ist dann der unveraenderte restliche
     * Quelltext dieses Absatzes (ohne die weichen Umbrueche dieser Flaeche,
     * harte \n bleiben; AP-1.fix1), danach folgen die uebrigen Absaetze
     * unveraendert.
     */
    function verteile(absaetze, flaeche, mass, optionen) {
        var o = optionen || {};
        var startPx = Math.round(zahl(o.startPx, VORGABE_START_PX));
        var minPx = Math.min(startPx, Math.round(zahl(o.minPx, VORGABE_MIN_PX)));
        var maxZeichen = Math.max(1, Math.floor(zahl(o.maxZeichen, VORGABE_MAX_ZEICHEN)));
        var m = typeof mass === 'function' ? mass : schaetzMass;
        var liste = [];
        if (Array.isArray(absaetze)) {
            for (var a = 0; a < absaetze.length; a++) {
                if (typeof absaetze[a] === 'string' && absaetze[a] !== '') {
                    liste.push(absaetze[a]);
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
                gesetzt.push(umbrechen(liste[i], f.b, px, m));
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
            var zeilen = umbrechen(liste[i], f.b, px, m);
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
                rest.push(restAb(liste[i], zeichenOhneLeer(zeilen.slice(0, passen))));
            } else {
                rest.push(liste[i]);
            }
            for (var k = i + 1; k < liste.length; k++) {
                rest.push(liste[k]);
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
        verteile: verteile,
        flaecheUnter: flaecheUnter,
        lesbareFarbe: lesbareFarbe
    };
})(window);
