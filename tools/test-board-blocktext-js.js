#!/usr/bin/env node
/**
 * Standalone-Harnisch fuer das Blocktext-Modul `assets/js/board-blocktext.js`
 * (Plan „Blocktext auf die Tafel", Phase 1, AP-1.2/AP-1.3, Architektur E5)
 * — ohne Browser, ohne jsdom, ohne npm-Abhaengigkeit.
 *
 * Aufruf:  node tools/test-board-blocktext-js.js
 *
 * Eine andere Datei pruefen (fuer Mutationsproben):
 *          CBD_BLOCKTEXT_DATEI=<pfad> node tools/test-board-blocktext-js.js
 *
 * ---------------------------------------------------------------------------
 * VERFAHREN
 * ---------------------------------------------------------------------------
 *
 * Die AUSGELIEFERTE Datei wird per `fs` gelesen und in einem `vm`-Kontext
 * ausgefuehrt, dessen einziges globales Objekt ein leeres `window` ist. Gibt
 * es dort keinen Zugriff auf Dokument, Speicher oder Zeichenflaeche, kann das
 * Modul keinen machen — ein Zugriff wuerde als ReferenceError auffallen.
 * Zusaetzlich prueft Gruppe 0 den Quelltext auf diese Namen und auf ES5.
 *
 * Gruppe 1: latexZuText, Einzelfaelle (Plan AP-1.2, Schritt 2, plus Faelle
 *           aus dem echten Bestand, siehe tools/fixtures/blocktext/).
 * Gruppe 2: Bestandslauf ueber tools/fixtures/blocktext/formeln.json (alle
 *           Formeln der Website); wird uebersprungen, wenn die Datei fehlt.
 * Gruppen 3-10: Absaetze, Umbruch, Verteilung, Flaeche, Farbe (AP-1.3 ff.).
 * Gruppen 11-14 (PLAN-Blocktext-Formatierung.md, AP-2.2): umbrechenTeile,
 *           verteile mit Absatz-Objekten {text, teile?}, lesbareLauffarbe,
 *           Surrogatpaare nie trennen (Review AP-1.rev, Befund B1).
 *
 * LaTeX-Eingaben stehen als String.raw`...`, damit jeder Backslash genau
 * einmal im Quelltext steht.
 *
 * Exit-Code 0 = alles gruen, 1 = mindestens ein Fehlschlag.
 *
 * @package ContainerBlockDesigner
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const R = String.raw;
const QUELLDATEI = process.env.CBD_BLOCKTEXT_DATEI
	|| path.join(__dirname, '..', 'assets', 'js', 'board-blocktext.js');
const FORMELDATEI = path.join(__dirname, 'fixtures', 'blocktext', 'formeln.json');
const BS = String.fromCharCode(92);
const NBSP = String.fromCharCode(160);

let gruen = 0;
let fehlschlaege = 0;

function pruefe(name, bedingung, zusatz) {
	if (bedingung) {
		gruen++;
		console.log('  OK   ' + name);
	} else {
		fehlschlaege++;
		console.log('  FEHL ' + name + (zusatz ? '  -> ' + zusatz : ''));
	}
}

function gleich(name, ist, soll) {
	pruefe(name, JSON.stringify(ist) === JSON.stringify(soll),
		'ist=' + JSON.stringify(ist) + ' soll=' + JSON.stringify(soll));
}

function ende() {
	console.log('');
	console.log('Ergebnis: ' + gruen + ' gruen, ' + fehlschlaege + ' rot');
	process.exit(fehlschlaege > 0 ? 1 : 0);
}

// =========================================================================
// Laden
// =========================================================================

let quelle;
try {
	quelle = fs.readFileSync(QUELLDATEI, 'utf8').replace(/\r\n/g, '\n');
} catch (e) {
	pruefe('Moduldatei vorhanden (' + QUELLDATEI + ')', false, e.message);
	ende();
}

const kontext = { window: {} };
vm.createContext(kontext);
try {
	vm.runInContext(quelle, kontext, { filename: QUELLDATEI });
} catch (e) {
	pruefe('Moduldatei laeuft ohne Fehler im leeren Kontext', false, e.message);
	ende();
}

const B = kontext.window.CBDBoardBlocktext;
pruefe('window.CBDBoardBlocktext ist gesetzt', B && typeof B === 'object');
if (!B) { ende(); }
pruefe('Schnittstelle: latexZuText ist Funktion', typeof B.latexZuText === 'function');
if (typeof B.latexZuText !== 'function') { ende(); }

// =========================================================================
// Gruppe 0: DOM-frei und ES5
// =========================================================================
console.log('\nGruppe 0: DOM-frei, ES5');
['document', 'localStorage', 'sessionStorage', 'getContext', 'querySelector', 'getComputedStyle']
	.forEach(function (wort) {
		pruefe('Quelltext enthaelt nicht „' + wort + '"', quelle.indexOf(wort) < 0);
	});
pruefe('keine Pfeilfunktion', !/(\)|\b[A-Za-z_$][\w$]*)\s*=>/.test(quelle));
pruefe('kein let/const', !/(^|[^\w$.])(let|const)\s+[A-Za-z_$]/m.test(quelle));
pruefe('kein Template-String', quelle.indexOf('`') < 0);

// =========================================================================
// Gruppe 1: latexZuText, Einzelfaelle
// =========================================================================
console.log('\nGruppe 1: latexZuText');

function L(eingabe, protokoll) {
	return B.latexZuText(eingabe, protokoll);
}

const FAELLE = [
	// Hoch- und Tiefstellung
	[R`H_2O`, 'H₂O'],
	[R`CO_2`, 'CO₂'],
	[R`SO_4^{2-}`, 'SO₄²⁻'],
	[R`x^{10}`, 'x¹⁰'],
	[R`x^{n+1}`, 'xⁿ⁺¹'],
	[R`x^{q}`, 'x^(q)'],
	[R`x^q`, 'x^q'],
	// Text und Schriftbefehle
	[R`\text{Ag}^+ + e^- \rightleftharpoons \text{Ag}`, 'Ag⁺ + e⁻ ⇌ Ag'],
	[R`\mathrm{Na}^+`, 'Na⁺'],
	[R`\textbf{A}`, 'A'],
	[R`\mathbf{F}`, 'F'],
	// Brueche
	[R`\frac{a}{b}`, 'a/b'],
	[R`\frac{a+b}{c}`, '(a+b)/c'],
	[R`\frac{1}{2x}`, '1/(2x)'],
	[R`\frac{10}{3}`, '10/3'],
	// Wurzeln
	[R`\sqrt{x}`, '√x'],
	[R`\sqrt{x+1}`, '√(x+1)'],
	[R`\sqrt[3]{x}`, '³√x'],
	// Griechisch und Leerzeichen danach
	[R`\Delta G^0`, 'ΔG⁰'],
	[R`\Delta G`, 'ΔG'],
	[R`\alpha`, 'α'], [R`\beta`, 'β'], [R`\gamma`, 'γ'], [R`\delta`, 'δ'],
	[R`\lambda`, 'λ'], [R`\mu`, 'μ'], [R`\nu`, 'ν'], [R`\pi`, 'π'],
	[R`\rho`, 'ρ'], [R`\sigma`, 'σ'], [R`\tau`, 'τ'], [R`\phi`, 'φ'],
	[R`\omega`, 'ω'], [R`\Omega`, 'Ω'], [R`\Delta`, 'Δ'], [R`\Sigma`, 'Σ'],
	[R`\rightleftharpoons \text{Ag}`, '⇌ Ag'],
	// Funktionsnamen und grosse Operatoren
	[R`\ln`, 'ln'], [R`\log`, 'log'], [R`\lg`, 'lg'], [R`\sin`, 'sin'],
	[R`\cos`, 'cos'], [R`\tan`, 'tan'], [R`\exp`, 'exp'], [R`\lim`, 'lim'],
	[R`\min`, 'min'], [R`\max`, 'max'],
	[R`\sum`, 'Σ'], [R`\int`, '∫'], [R`\partial`, '∂'],
	// Grad
	[R`25\,^\circ\text{C}`, '25 °C'],
	[R`\degree`, '°'],
	// Operatoren und Zeichen
	[R`\cdot`, '·'], [R`\times`, '×'], [R`\pm`, '±'],
	[R`\leq`, '≤'], [R`\le`, '≤'], [R`\geq`, '≥'], [R`\ge`, '≥'],
	[R`\neq`, '≠'], [R`\approx`, '≈'], [R`\infty`, '∞'],
	[R`\%`, '%'], [R`\{`, '{'], [R`\}`, '}'],
	[R`\ldots`, '…'], [R`\dots`, '…'],
	// Pfeile
	[R`\rightarrow`, '→'], [R`\to`, '→'], [R`\longrightarrow`, '→'],
	[R`\leftarrow`, '←'], [R`\leftrightarrow`, '↔'],
	[R`\rightleftharpoons`, '⇌'], [R`\Rightarrow`, '⇒'],
	[R`\xrightarrow{h\nu}`, '→(hν)'],
	[R`\xrightarrow{}`, '→'],
	// Klammern
	[R`\left( x \right)`, '(x)'],
	[R`\left[ \right]`, '[]'],
	// Abstaende
	[R`a\,b`, 'a b'], [R`a\;b`, 'a b'], [R`a\:b`, 'a b'], [R`a\!b`, 'ab'],
	[R`a\quad b`, 'a b'], [R`a\qquad b`, 'a b'], [R`a~b`, 'a b'],
	[R`a    b`, 'a b'], [R`  a  `, 'a'],
	// mhchem
	[R`\ce{H2SO4}`, 'H₂SO₄'],
	[R`\ce{Fe^3+}`, 'Fe³⁺'],
	[R`\ce{A + B -> C}`, 'A + B → C'],
	[R`\ce{A <=> B}`, 'A ⇌ B'],
	// Gruppen und Zeilenwechsel
	[R`{ab}`, 'ab'],
	[R`a \\ b`, 'a b'],

	// --- Zusaetzlich: Faelle aus dem echten Bestand (AP-1.1) ---
	[R`_2`, '₂'],
	[R`E = +0{,}197`, 'E = +0,197'],
	[R`\text{trans-}\Delta^2\text{-Enoyl-CoA} + \text{H}_2\text{O} \xrightarrow{\text{Enoyl-CoA-Hydratase}} \text{L-3-Hydroxyacyl-CoA}`,
		'trans-Δ²-Enoyl-CoA + H₂O →(Enoyl-CoA-Hydratase) L-3-Hydroxyacyl-CoA'],
	['Glycerin+3' + NBSP + 'Fettsäuren→Triglycerid+3' + NBSP + 'H2O', 'Glycerin+3 Fettsäuren→Triglycerid+3 H2O'],
	[R`C_5H_8`, 'C₅H₈'],
	[R`C_{40}`, 'C₄₀'],
	[R`8 \times C_5 = C_{40}`, '8 × C₅ = C₄₀'],
	[R`-242\ \mathrm{kJ/mol}`, '-242 kJ/mol'],
	[R`\Delta G^‡ = \Delta H^‡ - T \cdot \Delta S^‡`, 'ΔG‡ = ΔH‡ - T · ΔS‡'],
	[R`\mathrm{g} \cdot \mathrm{mol}^{-1}`, 'g · mol⁻¹'],
	[R`2 \times 10^{-3}`, '2 × 10⁻³'],
	[R`\pm 0{,}5`, '±0,5'],
	[R`\ln K`, 'ln K'],
	[R`\lg c(\text{H}^+)`, 'lg c(H⁺)'],
	[R`\frac{\mathrm{mol}}{\mathrm{L}}`, 'mol/L'],
	[R`\dfrac{a}{b} + \tfrac{1}{2}`, 'a/b + 1/2'],
	[R`\frac{x^2}{2}`, 'x²/2'],
	[R`\lg \frac{c}{c^\circ}`, 'lg c/c°'],
	[R`\frac{c_0}{2x}`, 'c₀/(2x)'],
	[R`v_{\text{max}}`, 'vₘₐₓ'],
	[R`A\rightarrow B`, 'A → B'],
	[R`\overset{\Delta}{\rightarrow}`, '→(Δ)'],
	[R`\bigl( x \bigr)`, '(x)'],
	[R`\vec{F}`, 'F' + String.fromCharCode(0x20D7)],
	[R`\ce{Na+ + Cl-}`, 'Na⁺ + Cl⁻'],
	[R`\ce{Ca(OH)2}`, 'Ca(OH)₂'],
	[R`\ce{2H2O}`, '2H₂O'],
	[R`\ce{Fe3+}`, 'Fe³⁺'],
	[R`100\,\%`, '100 %'],
	[R`\backslash`, '∖']
];

FAELLE.forEach(function (f) {
	let ist;
	try {
		ist = L(f[0]);
	} catch (e) {
		ist = 'AUSNAHME: ' + e.message;
	}
	gleich('latexZuText(' + JSON.stringify(f[0]) + ')', ist, f[1]);
});

// Unbekannte Befehle und Protokoll
console.log('\nGruppe 1b: unbekannte Befehle, Robustheit');
(function () {
	const p = {};
	gleich('unbekannter Befehl: \\foo{x} -> foox', L(R`\foo{x}`, p), 'foox');
	gleich('protokoll.unbekannt = [foo]', p.unbekannt, ['foo']);
	const p2 = {};
	L(R`\alpha + \frac{a}{b} \cdot \mathrm{x}`, p2);
	pruefe('bekannte Befehle hinterlassen kein Protokoll',
		!p2.unbekannt || p2.unbekannt.length === 0, JSON.stringify(p2));
	let ok = true;
	try { L(R`\foo{x}`); } catch (e) { ok = false; }
	pruefe('ohne Protokoll-Objekt kein Fehler', ok);
})();

// Robustheit
gleich('leere Zeichenkette', L(''), '');
gleich('null', L(null), '');
gleich('undefined', L(undefined), '');
gleich('Zahl', L(42), '');
(function () {
	let ist;
	let ok = true;
	try { ist = L(R`\frac{a}`); } catch (e) { ok = false; }
	pruefe('kaputt \\frac{a}: keine Ausnahme', ok);
	pruefe('kaputt \\frac{a}: Ergebnis enthaelt a', ok && typeof ist === 'string' && ist.indexOf('a') >= 0,
		JSON.stringify(ist));
})();
gleich('x^ (offenes Hoch) -> x', L('x^'), 'x');
(function () {
	const stueck = R`\frac{a+b}{c} \cdot \text{Ag}^+ + x^{n+1} \rightarrow `;
	let lang = '';
	while (lang.length < 20000) { lang += stueck; }
	const t0 = Date.now();
	let ist;
	let ok = true;
	try { ist = L(lang); } catch (e) { ok = false; }
	const dauer = Date.now() - t0;
	pruefe('20 000 Zeichen: keine Ausnahme', ok);
	pruefe('20 000 Zeichen: unter 200 ms (' + dauer + ' ms)', dauer < 200);
	pruefe('20 000 Zeichen: Ergebnis ohne Backslash', ok && ist.indexOf(BS) < 0);
})();
(function () {
	const boese = [
		new Array(5001).join('{'),
		new Array(5001).join('}'),
		new Array(3001).join(R`\frac{`),
		new Array(2001).join('^'),
		new Array(2001).join('_{'),
		BS, BS + BS + BS, R`\sqrt[`, R`\left`, R`\ce{`, R`\ce{^}`, R`\xrightarrow[`,
		'\u0000\u0001\u0007\t\n\r', R`\text{`, R`\overset{a}`, R`^{`, '}{', R`\\\\\\`
	];
	boese.forEach(function (b, i) {
		let ist;
		let ok = true;
		try { ist = L(b); } catch (e) { ok = false; }
		pruefe('boesartige Eingabe ' + (i + 1) + ': keine Ausnahme, Zeichenkette ohne Backslash',
			ok && typeof ist === 'string' && ist.indexOf(BS) < 0, ok ? JSON.stringify(ist).slice(0, 80) : 'Ausnahme');
	});
})();

// =========================================================================
// Gruppe 2: Bestandslauf
// =========================================================================
console.log('\nGruppe 2: Bestandslauf (tools/fixtures/blocktext/formeln.json)');
(function () {
	let formeln;
	try {
		formeln = JSON.parse(fs.readFileSync(FORMELDATEI, 'utf8'));
	} catch (e) {
		console.log('  (uebersprungen: ' + FORMELDATEI + ' nicht lesbar)');
		return;
	}
	let ausnahmen = 0;
	let keinText = 0;
	let mitBackslash = 0;
	let mitUnbekannt = 0;
	const zaehler = {};
	const beispiele = [];
	const t0 = Date.now();
	formeln.forEach(function (f) {
		const p = {};
		let ist;
		try {
			ist = B.latexZuText(f, p);
		} catch (e) {
			ausnahmen++;
			return;
		}
		if (typeof ist !== 'string') { keinText++; return; }
		if (ist.indexOf(BS) >= 0) {
			mitBackslash++;
			if (beispiele.length < 5) { beispiele.push(f + '  =>  ' + ist); }
		}
		if (p.unbekannt && p.unbekannt.length) {
			mitUnbekannt++;
			p.unbekannt.forEach(function (n) { zaehler[n] = (zaehler[n] || 0) + 1; });
		}
	});
	const anteil = formeln.length ? mitUnbekannt / formeln.length : 0;
	console.log('  Formeln: ' + formeln.length + ', Dauer: ' + (Date.now() - t0) + ' ms');
	console.log('  mit unbekanntem Befehl: ' + mitUnbekannt + ' (' + (anteil * 100).toFixed(2) + ' %)');
	const top = Object.keys(zaehler).map(function (k) { return [k, zaehler[k]]; })
		.sort(function (a, b) { return b[1] - a[1]; }).slice(0, 20);
	console.log('  haeufigste unbekannte Befehle: ' + (top.length ? top.map(function (e) { return e[0] + ' (' + e[1] + ')'; }).join(', ') : 'keine'));
	pruefe('Bestand: keine Ausnahme (' + ausnahmen + ')', ausnahmen === 0);
	pruefe('Bestand: jedes Ergebnis ist Zeichenkette (' + keinText + ' nicht)', keinText === 0);
	pruefe('Bestand: kein Ergebnis mit Backslash (' + mitBackslash + ')', mitBackslash === 0, beispiele.join(' | '));
	pruefe('Bestand: hoechstens 2 % mit unbekanntem Befehl', anteil <= 0.02);
})();

// =========================================================================
// Ab hier AP-1.3: Absaetze, Umbruch, Verteilung, freie Flaeche, Farbe
// =========================================================================

/** Ruft fn auf und liefert Ergebnis oder 'AUSNAHME: ...' (Harnisch bricht nie ab). */
function versuche(fn) {
	try {
		return fn();
	} catch (e) {
		return 'AUSNAHME: ' + e.message;
	}
}

// Schaetz-Messfunktion (Plan AP-1.3, Schritt 1): deterministisch.
function mass(t, px) {
	return t.length * px * 0.5;
}

console.log('\nGruppe 3: Schnittstelle');
// Bis AP-2.2 (PLAN-Blocktext-Formatierung.md) hiess diese Pruefung „genau die
// sechs Funktionen der Schnittstelle (Plan, Abschnitt 4)" (alter Plan
// „Blocktext auf die Tafel"). F14 erweitert die Schnittstelle planmaessig um
// umbrechenTeile und lesbareLauffarbe; umgestellt auf Entscheidung des
// Orchestrators. Bleibt eine Exaktpruefung.
gleich('genau die acht Funktionen der Schnittstelle (PLAN-Blocktext-Formatierung.md, Abschnitt 4)',
	Object.keys(B).sort(),
	['absaetzeAusText', 'flaecheUnter', 'latexZuText', 'lesbareFarbe', 'lesbareLauffarbe', 'umbrechen', 'umbrechenTeile', 'verteile']);
Object.keys(B).forEach(function (k) {
	pruefe('Schnittstelle: ' + k + ' ist Funktion', typeof B[k] === 'function');
});

console.log('\nGruppe 4: absaetzeAusText');
gleich('a\\n\\nb', versuche(function () { return B.absaetzeAusText('a\n\nb'); }), ['a', 'b']);
gleich('a\\n \\n\\n b ', versuche(function () { return B.absaetzeAusText('a\n \n\n b '); }), ['a', 'b']);
gleich('a\\nb bleibt ein Absatz', versuche(function () { return B.absaetzeAusText('a\nb'); }), ['a\nb']);
gleich('leer', versuche(function () { return B.absaetzeAusText(''); }), []);
gleich('\\r\\n\\r\\nx', versuche(function () { return B.absaetzeAusText('\r\n\r\nx'); }), ['x']);
gleich('Zeilenenden getrimmt', versuche(function () { return B.absaetzeAusText('a  \nb\t\n\nc'); }), ['a\nb', 'c']);
gleich('keine Zeichenkette', versuche(function () { return B.absaetzeAusText(null); }), []);

console.log('\nGruppe 5: umbrechen');
gleich('umbrechen(aaa bbb ccc, 75)', versuche(function () { return B.umbrechen('aaa bbb ccc', 75, 20, mass); }), ['aaa bbb', 'ccc']);
gleich('umbrechen(abcdefghij, 45) teilt das Wort', versuche(function () { return B.umbrechen('abcdefghij', 45, 20, mass); }), ['abcd', 'efgh', 'ij']);
gleich('umbrechen(a\\nb) harte Zeilenwechsel', versuche(function () { return B.umbrechen('a\nb', 1000, 20, mass); }), ['a', 'b']);
gleich('umbrechen(leer)', versuche(function () { return B.umbrechen('', 100, 20, mass); }), []);
gleich('Leerzeichen an der Umbruchstelle entfallen', versuche(function () { return B.umbrechen('aaa    bbb', 45, 20, mass); }), ['aaa', 'bbb']);
gleich('Wort nach Teilung laeuft weiter', versuche(function () { return B.umbrechen('abcdefg hi', 55, 20, mass); }), ['abcde', 'fg hi']);
(function () {
	const zeilen = versuche(function () { return B.umbrechen('Die Werte fallen von oben nach unten und zeigen dasselbe Muster.', 200, 24, mass); });
	pruefe('jede Zeile passt in die Breite', Array.isArray(zeilen) && zeilen.every(function (z) { return mass(z, 24) <= 200; }), JSON.stringify(zeilen));
	pruefe('kein Wort geht verloren', Array.isArray(zeilen) && zeilen.join(' ') === 'Die Werte fallen von oben nach unten und zeigen dasselbe Muster.', JSON.stringify(zeilen));
})();

console.log('\nGruppe 6: verteile');
const FL = { x: 10, y: 20, b: 1000, h: 600 };
(function () {
	const r = versuche(function () { return B.verteile(['Erster Absatz', 'Zweiter'], FL, mass, {}); });
	const zh = 1.2 * 24;
	pruefe('zwei kurze Absaetze: px 24', r && r.px === 24, JSON.stringify(r));
	pruefe('zwei kurze Absaetze: zwei Bloecke, kein Rest', r && r.bloecke && r.bloecke.length === 2 && r.rest.length === 0, JSON.stringify(r));
	if (r && r.bloecke && r.bloecke.length === 2) {
		gleich('erster Block', r.bloecke[0], { text: 'Erster Absatz', x: 10, y: 20, b: mass('Erster Absatz', 24), h: zh });
		gleich('zweiter Block mit Absatzabstand darunter', r.bloecke[1],
			{ text: 'Zweiter', x: 10, y: 20 + zh + Math.round(0.6 * 24), b: mass('Zweiter', 24), h: zh });
	}
})();
(function () {
	// Drei einzeilige Absaetze; Hoehe 100 passt erst ab 20 px:
	// 21 px: 3*25.2 + 2*13 = 101.6 > 100, 20 px: 3*24 + 2*12 = 96 <= 100.
	const r = versuche(function () { return B.verteile(['a', 'b', 'c'], { x: 0, y: 0, b: 500, h: 100 }, mass, {}); });
	pruefe('passt erst mit 20 px: px 20', r && r.px === 20, JSON.stringify(r));
	pruefe('passt erst mit 20 px: kein Rest', r && r.rest && r.rest.length === 0, JSON.stringify(r && r.rest));
})();
(function () {
	const absatz = 'Ein langer Satz mit vielen Woertern, der sich ueber mehrere Zeilen erstreckt und umbrochen wird.';
	const eingabe = [absatz, absatz + ' Zwei', absatz + ' Drei', absatz + ' Vier', absatz + ' Fuenf'];
	const r = versuche(function () { return B.verteile(eingabe, { x: 5, y: 5, b: 300, h: 200 }, mass, {}); });
	pruefe('passt auch mit 18 px nicht: px 18', r && r.px === 18, JSON.stringify(r && r.px));
	pruefe('passt auch mit 18 px nicht: Rest nicht leer', r && r.rest && r.rest.length > 0);
	if (r && r.bloecke && r.rest) {
		const ohne = function (s) { return s.replace(/\s+/g, ''); };
		const zusammen = r.bloecke.map(function (b) { return b.text; }).concat(r.rest).join('');
		gleich('verlustfrei: Bloecke + Rest = Eingabe (ohne Leerraum)', ohne(zusammen), ohne(eingabe.join('')));
		const unten = r.bloecke.length ? r.bloecke[r.bloecke.length - 1].y + r.bloecke[r.bloecke.length - 1].h : 5;
		pruefe('alle Bloecke liegen in der Flaeche', unten <= 5 + 200 + 1e-9, 'unten=' + unten);
		pruefe('alle Zeilen passen in die Breite', r.bloecke.every(function (b) {
			return b.text.split('\n').every(function (z) { return mass(z, 18) <= 300; });
		}));
		pruefe('Rest beginnt mit dem Rest des geteilten Absatzes oder einem ganzen Absatz',
			r.rest.length > 0 && eingabe.some(function (a) { return ohne(a).endsWith(ohne(r.rest[0])); }), JSON.stringify(r.rest[0]));
	}
})();
(function () {
	const r = versuche(function () { return B.verteile(['a', 'b'], { x: 0, y: 0, b: 500, h: 20 }, mass, {}); });
	gleich('Flaeche niedriger als eine Zeile: keine Bloecke, alles Rest', r, { px: 18, bloecke: [], rest: ['a', 'b'] });
})();
(function () {
	let gross = '';
	while (gross.length < 12000) { gross += 'Wort' + (gross.length % 97) + ' '; }
	gross = gross.slice(0, 12000).replace(/\s+$/, '');
	const r = versuche(function () { return B.verteile([gross], { x: 0, y: 0, b: 1000, h: 100000 }, mass, {}); });
	pruefe('12 000 Zeichen: mehrere Bloecke', r && r.bloecke && r.bloecke.length >= 3, JSON.stringify(r && r.bloecke && r.bloecke.length));
	pruefe('12 000 Zeichen: jeder Block hoechstens 5000 Zeichen', r && r.bloecke && r.bloecke.every(function (b) { return b.text.length <= 5000; }));
	pruefe('12 000 Zeichen: kein Rest', r && r.rest && r.rest.length === 0);
	if (r && r.bloecke && r.bloecke.length > 1) {
		pruefe('12 000 Zeichen: Teilbloecke direkt untereinander (ohne Absatzabstand)',
			r.bloecke.every(function (b, i) { return i === 0 || Math.abs(b.y - (r.bloecke[i - 1].y + r.bloecke[i - 1].h)) < 1e-9; }));
		gleich('12 000 Zeichen: verlustfrei', r.bloecke.map(function (b) { return b.text; }).join('').replace(/\s+/g, ''), gross.replace(/\s+/g, ''));
	}
})();
gleich('leere Absatzliste', versuche(function () { return B.verteile([], FL, mass, {}); }), { px: 24, bloecke: [], rest: [] });
(function () {
	const r = versuche(function () { return B.verteile(['a'], FL, mass, { startPx: 30, minPx: 20 }); });
	pruefe('Optionen werden beachtet (startPx 30)', r && r.px === 30, JSON.stringify(r && r.px));
	const r2 = versuche(function () { return B.verteile(['a'], FL, mass); });
	pruefe('ohne Optionen: Vorgaben (24 px)', r2 && r2.px === 24, JSON.stringify(r2 && r2.px));
})();

console.log('\nGruppe 7: flaecheUnter');
const SICHT = { x: 100, y: 50, b: 800, h: 600 };
gleich('leere Liste', versuche(function () { return B.flaecheUnter(SICHT, [], 20); }), { x: 120, y: 70, b: 760, h: 560 });
gleich('ein Rahmen unten im Bild: y darunter', versuche(function () { return B.flaecheUnter(SICHT, [{ x: 200, y: 300, b: 100, h: 50 }], 20); }),
	{ x: 120, y: 370, b: 760, h: 260 });
gleich('Rahmen rechts ausserhalb: ignoriert', versuche(function () { return B.flaecheUnter(SICHT, [{ x: 950, y: 300, b: 100, h: 50 }], 20); }),
	{ x: 120, y: 70, b: 760, h: 560 });
// Bis AP-2.fix1 lautete diese Erwartung { y: 770, h: 0 } ("h 0"): Ein Rahmen
// ganz unterhalb des Ausschnitts schob die Flaeche hinaus. Review AP-2.rev,
// Befund G4: bei Zoom > 1 hiess es dann "kein Platz", obwohl der sichtbare
// Ausschnitt leer war. Vorgabe geaendert (Plan, AP-2.fix1 Schritt 6).
gleich('Rahmen unterhalb des sichtbaren Bereichs: ignoriert (AP-2.fix1, G4)', versuche(function () { return B.flaecheUnter(SICHT, [{ x: 200, y: 700, b: 100, h: 50 }], 20); }),
	{ x: 120, y: 70, b: 760, h: 560 });
gleich('Rahmen oberhalb: Oberkante bleibt', versuche(function () { return B.flaecheUnter(SICHT, [{ x: 200, y: 0, b: 100, h: 20 }], 20); }),
	{ x: 120, y: 70, b: 760, h: 560 });
gleich('Rahmen ragt von links hinein: zaehlt', versuche(function () { return B.flaecheUnter(SICHT, [{ x: 50, y: 100, b: 60, h: 100 }], 20); }),
	{ x: 120, y: 220, b: 760, h: 410 });

console.log('\nGruppe 8: lesbareFarbe');
gleich('schwarz auf weiss', versuche(function () { return B.lesbareFarbe('#000000', '#ffffff'); }), '#000000');
gleich('schwarz auf gruener Tafel -> weiss', versuche(function () { return B.lesbareFarbe('#000000', '#1a472a'); }), '#ffffff');
gleich('schwarz auf schwarzer Tafel -> weiss', versuche(function () { return B.lesbareFarbe('#000000', '#1c1c1c'); }), '#ffffff');
gleich('rot auf weiss bleibt rot', versuche(function () { return B.lesbareFarbe('#ff0000', '#ffffff'); }), '#ff0000');
gleich('#FFF auf weiss -> schwarz', versuche(function () { return B.lesbareFarbe('#FFF', '#ffffff'); }), '#000000');
gleich('ungueltiger Stift auf gruen -> weiss', versuche(function () { return B.lesbareFarbe('rot', '#1a472a'); }), '#ffffff');
gleich('Kurzform wird ausgeschrieben', versuche(function () { return B.lesbareFarbe('#F00', '#FFFFFF'); }), '#ff0000');
gleich('ungueltige Tafel gilt als weiss', versuche(function () { return B.lesbareFarbe('#ffffff', 'kaputt'); }), '#000000');
gleich('weiss auf schwarzer Tafel bleibt weiss', versuche(function () { return B.lesbareFarbe('#ffffff', '#1c1c1c'); }), '#ffffff');

// =========================================================================
// Gruppe 9: AP-1.fix1 (Review-Befunde M1, G2, G3)
// =========================================================================
console.log('\nGruppe 9: Rest als Quelltext, lineares Trimmen, Rueckfall');
(function () {
	const ohne = function (s) { return String(s).replace(/\s+/g, ''); };
	const absatz = 'Wenn Kohlenstoffdioxid in Wasser geloest wird, entsteht ein einfaches '
		+ 'Gleichgewichtssystem, das sich je nach Temperatur und Druck verschiebt und '
		+ 'deshalb im Unterricht gern als erstes Beispiel dient.';
	// 18 px: 9 px je Zeichen, Breite 300 -> 33 Zeichen je Zeile; Hoehe 50 -> 2 Zeilen.
	const r = versuche(function () { return B.verteile([absatz], { x: 0, y: 0, b: 300, h: 50 }, mass, {}); });
	pruefe('M1: teilweise passender Absatz wird geteilt (ein Block, ein Rest)',
		r && r.px === 18 && r.bloecke && r.bloecke.length === 1 && r.rest && r.rest.length === 1, JSON.stringify(r));
	if (r && r.bloecke && r.bloecke.length === 1 && r.rest && r.rest.length === 1) {
		const rest0 = r.rest[0];
		pruefe('M1: Rest enthaelt keine weichen Umbrueche (kein \\n)', rest0.indexOf('\n') < 0, JSON.stringify(rest0));
		pruefe('M1: Rest ist das unveraenderte Ende des Absatzes', absatz.endsWith(rest0) && rest0.length < absatz.length, JSON.stringify(rest0));
		pruefe('M1: Rest beginnt nicht mit Leerraum', !/^\s/.test(rest0), JSON.stringify(rest0));
		gleich('M1: Block + Rest = Absatz (ohne Leerraum)', ohne(r.bloecke[0].text + rest0), ohne(absatz));
	}
	const mitWechsel = 'Erste Zeile, die lang genug ist, um umbrochen zu werden\nZweite harte Zeile bleibt';
	const r2 = versuche(function () { return B.verteile([mitWechsel], { x: 0, y: 0, b: 300, h: 25 }, mass, {}); });
	const rest2 = r2 && r2.rest && r2.rest[0];
	pruefe('M1: harter Zeilenwechsel im Rest bleibt erhalten',
		typeof rest2 === 'string' && mitWechsel.endsWith(rest2) && rest2.split('\n').length === 2, JSON.stringify(r2));
})();
gleich('G3: catch-Rueckfall bei eingefrorenem Protokoll',
	versuche(function () { return B.latexZuText(R`\foo{x}  y`, Object.freeze({})); }), 'foo{x} y');
(function () {
	const leer = new Array(20001).join(' ');
	let t0 = Date.now();
	const a = versuche(function () { return B.absaetzeAusText('a' + leer + 'b'); });
	const d1 = Date.now() - t0;
	pruefe('G2: absaetzeAusText mit 20 000 Leerzeichen unter 200 ms (' + d1 + ' ms)', d1 < 200);
	pruefe('G2: Leerzeichen im Inneren bleiben', Array.isArray(a) && a.length === 1 && a[0].length === 20002);
	t0 = Date.now();
	const u = versuche(function () { return B.umbrechen('a' + leer + 'b', 1e9, 20, mass); });
	const d2 = Date.now() - t0;
	pruefe('G2: umbrechen mit 20 000 Leerzeichen unter 200 ms (' + d2 + ' ms)', d2 < 200);
	pruefe('G2: umbrechen liefert eine Zeile', Array.isArray(u) && u.length === 1 && u[0].length === 20002);
})();

// =========================================================================
// Gruppe 10: AP-2.fix1 (Review AP-2.rev, Befund G4)
// =========================================================================
console.log('\nGruppe 10: flaecheUnter zaehlt nur, was im Ausschnitt beginnt');
gleich('G4: Rahmen genau an der Unterkante beginnend: ignoriert', versuche(function () { return B.flaecheUnter(SICHT, [{ x: 200, y: 650, b: 100, h: 50 }], 20); }),
	{ x: 120, y: 70, b: 760, h: 560 });
gleich('G4: Rahmen ragt von oben in den Ausschnitt: zaehlt', versuche(function () { return B.flaecheUnter(SICHT, [{ x: 200, y: 640, b: 100, h: 50 }], 20); }),
	{ x: 120, y: 710, b: 760, h: 0 });
gleich('G4: Zoom 2 - Strich unterhalb der Ansicht, Ausschnitt leer', versuche(function () {
	return B.flaecheUnter({ x: 0, y: 0, b: 341, h: 330 }, [{ x: 10, y: 331, b: 200, h: 4 }], 20);
}), { x: 20, y: 20, b: 301, h: 290 });

// =========================================================================
// Ab hier PLAN-Blocktext-Formatierung.md, AP-2.2: Absaetze als Objekte
// {text, teile?} (umbrechenTeile, verteile), lesbareLauffarbe (F7) und
// Review-Befund B1 aus AP-1.rev (Surrogatpaare nie trennen).
// =========================================================================

// Messfunktion mit Stil (Plan AP-2.2, Schritt 1): fett misst breiter.
function massF(t, px, stil) {
	return t.length * px * (stil && stil.f ? 0.7 : 0.5);
}
const PLAIN = { f: 0, k: 0 };
const FETT = { f: 1, k: 0 };

function texte(r) {
	return Array.isArray(r) ? r.map(function (z) { return z && z.text; }) : r;
}
function verkettet(teile) {
	return teile.map(function (t) { return t.t; }).join('');
}
function istHochS(c) { return c >= 0xD800 && c <= 0xDBFF; }
function istTiefS(c) { return c >= 0xDC00 && c <= 0xDFFF; }
/** true, wenn s weder mit einem Low-Surrogat beginnt noch mit einem High-Surrogat endet. */
function surrogatSauber(s) {
	if (typeof s !== 'string' || s === '') { return true; }
	return !istTiefS(s.charCodeAt(0)) && !istHochS(s.charCodeAt(s.length - 1));
}
/** Teil-Liste formal sauber: t nicht leer und surrogat-sauber, f/k nur als 1. */
function teileFormalOk(teile) {
	return Array.isArray(teile) && teile.every(function (p) {
		return p && typeof p.t === 'string' && p.t !== '' && surrogatSauber(p.t)
			&& (p.f === undefined || p.f === 1) && (p.k === undefined || p.k === 1)
			&& Object.keys(p).every(function (k) { return ['t', 'f', 'k', 'farbe'].indexOf(k) >= 0; });
	});
}

// -------------------------------------------------------------------------
console.log('\nGruppe 11: umbrechenTeile (AP-2.2)');
pruefe('11.0 Schnittstelle: umbrechenTeile ist Funktion', typeof B.umbrechenTeile === 'function');

const A11 = { text: 'aa bb cc', teile: [{ t: 'aa ' }, { t: 'bb', f: 1 }, { t: ' cc' }] };
const A11_KLAR = { text: 'aa bb cc', teile: [{ t: 'aa ' }, { t: 'bb' }, { t: ' cc' }] };
// 20 px: normal 10 je Zeichen, fett 14. „aa bb" = 30 + 28 = 58, „aa bb cc" = 88.
gleich('11a: Umbruch nach „bb", Leerzeichen an der Umbruchstelle entfaellt',
	versuche(function () { return B.umbrechenTeile(A11, 70, 20, massF); }),
	[{ text: 'aa bb', teile: [{ t: 'aa ' }, { t: 'bb', f: 1 }] }, { text: 'cc', teile: [{ t: 'cc' }] }]);
gleich('11b: ohne Fett passt der Absatz in eine Zeile (Breite 85, 80 <= 85)',
	versuche(function () { return B.umbrechenTeile(A11_KLAR, 85, 20, massF); }),
	[{ text: 'aa bb cc', teile: [{ t: 'aa bb cc' }] }]);
gleich('11b: mit Fett nicht (fett misst breiter, 88 > 85)',
	texte(versuche(function () { return B.umbrechenTeile(A11, 85, 20, massF); })), ['aa bb', 'cc']);

[
	['aaa bbb ccc', 75, 20],
	['abcdefghij', 45, 20],
	['a\nb', 1000, 20],
	['aaa    bbb', 45, 20],
	['abcdefg hi', 55, 20],
	['  vorne und hinten  ', 1000, 20],
	['Die Werte fallen von oben nach unten und zeigen dasselbe Muster.', 200, 24]
].forEach(function (f) {
	const soll = B.umbrechen(f[0], f[1], f[2], mass);
	const ist = versuche(function () { return B.umbrechenTeile(f[0], f[1], f[2], massF); });
	gleich('11c: Zeichenkette ' + JSON.stringify(f[0]) + ' - Zeilen wie umbrechen', texte(ist), soll);
	pruefe('11c: Zeichenkette ' + JSON.stringify(f[0]) + ' - Teile je Zeile [{t: zeile}]',
		Array.isArray(ist) && ist.every(function (z) {
			return JSON.stringify(z.teile) === JSON.stringify(z.text === '' ? [] : [{ t: z.text }]);
		}), JSON.stringify(ist));
});
gleich('11c: leere Zeile hat teile []',
	versuche(function () { return B.umbrechenTeile('a\n\nb', 1000, 20, massF); }),
	[{ text: 'a', teile: [{ t: 'a' }] }, { text: '', teile: [] }, { text: 'b', teile: [{ t: 'b' }] }]);
gleich('11c: Objekt ohne teile wie Zeichenkette',
	versuche(function () { return B.umbrechenTeile({ text: 'aaa bbb ccc' }, 75, 20, massF); }),
	versuche(function () { return B.umbrechenTeile('aaa bbb ccc', 75, 20, massF); }));

gleich('11d: harter Zeilenwechsel bleibt, Teil ueber \\n wird geteilt',
	versuche(function () { return B.umbrechenTeile({ text: 'ab\ncd', teile: [{ t: 'a' }, { t: 'b\nc', f: 1 }, { t: 'd' }] }, 1000, 20, massF); }),
	[{ text: 'ab', teile: [{ t: 'a' }, { t: 'b', f: 1 }] }, { text: 'cd', teile: [{ t: 'c', f: 1 }, { t: 'd' }] }]);
gleich('11d: \\r\\n wie \\n, Teile folgen',
	versuche(function () { return B.umbrechenTeile({ text: 'ab\r\ncd', teile: [{ t: 'a' }, { t: 'b\r\nc', f: 1 }, { t: 'd' }] }, 1000, 20, massF); }),
	[{ text: 'ab', teile: [{ t: 'a' }, { t: 'b', f: 1 }] }, { text: 'cd', teile: [{ t: 'c', f: 1 }, { t: 'd' }] }]);
// Nachtrag nach der Mutationsprobe (Mutation „\r nicht normiert" blieb
// sonst unentdeckt, weil ein \r vor \n am Zeilenende ohnehin entfaellt):
gleich('11d: einzelnes \\r wie \\n (wie umbrechen)',
	versuche(function () { return B.umbrechenTeile({ text: 'ab\rcd', teile: [{ t: 'a' }, { t: 'b\rc', f: 1 }, { t: 'd' }] }, 1000, 20, massF); }),
	[{ text: 'ab', teile: [{ t: 'a' }, { t: 'b', f: 1 }] }, { text: 'cd', teile: [{ t: 'c', f: 1 }, { t: 'd' }] }]);

// 20 px, Breite 35: fett 14 je Zeichen, normal 10. „ab" = 28, „abc" = 42;
// „cde" = 14 + 20 = 34, „cdef" = 44.
gleich('11e: zu langes Wort zeichenweise geteilt, Teile folgen',
	versuche(function () { return B.umbrechenTeile({ text: 'abcdef', teile: [{ t: 'abc', f: 1 }, { t: 'def' }] }, 35, 20, massF); }),
	[{ text: 'ab', teile: [{ t: 'ab', f: 1 }] }, { text: 'cde', teile: [{ t: 'c', f: 1 }, { t: 'de' }] }, { text: 'f', teile: [{ t: 'f' }] }]);

gleich('11f: zusammengelegt wie teileZusammenlegen (f:true -> 1, leere t weg, ungueltige farbe weg)',
	versuche(function () {
		return B.umbrechenTeile({ text: 'abc', teile: [{ t: 'a', f: true }, { t: 'b', f: 1 }, { t: '', k: 1 }, { t: 'c', farbe: 'rot' }] }, 1000, 20, massF);
	}),
	[{ text: 'abc', teile: [{ t: 'ab', f: 1 }, { t: 'c' }] }]);
gleich('11f: farbe und kursiv bleiben, Schluesselreihenfolge t, f, k, farbe',
	versuche(function () {
		return B.umbrechenTeile({ text: 'abc', teile: [{ farbe: '#71230a', k: 1, f: 1, t: 'a' }, { t: 'b', farbe: 'rgb(1, 2, 3)' }, { t: 'c' }] }, 1000, 20, massF);
	}),
	[{ text: 'abc', teile: [{ t: 'a', f: 1, k: 1, farbe: '#71230a' }, { t: 'b', farbe: 'rgb(1, 2, 3)' }, { t: 'c' }] }]);
gleich('11g: Verkettung der teile != text -> wie Klartext',
	versuche(function () { return B.umbrechenTeile({ text: 'abc', teile: [{ t: 'ab', f: 1 }] }, 1000, 20, massF); }),
	[{ text: 'abc', teile: [{ t: 'abc' }] }]);
gleich('11g: teile kein Array -> wie Klartext',
	versuche(function () { return B.umbrechenTeile({ text: 'abc', teile: 'x' }, 1000, 20, massF); }),
	[{ text: 'abc', teile: [{ t: 'abc' }] }]);
gleich('11g: Teil ohne Zeichenketten-t -> wie Klartext',
	versuche(function () { return B.umbrechenTeile({ text: 'ab', teile: [{ t: 'a', f: 1 }, { t: ['b'] }] }, 1000, 20, massF); }),
	[{ text: 'ab', teile: [{ t: 'ab' }] }]);
[null, undefined, 42, {}, { text: 5 }, { text: '' }, ''].forEach(function (e, i) {
	gleich('11h: unbrauchbare Eingabe ' + (i + 1) + ' -> []', versuche(function () { return B.umbrechenTeile(e, 100, 20, massF); }), []);
});
(function () {
	const eingabe = { text: 'aa bb\ncc', teile: [{ t: 'aa ' }, { t: 'bb\ncc', f: 1 }] };
	const vorher = JSON.stringify(eingabe);
	versuche(function () { return B.umbrechenTeile(eingabe, 30, 20, massF); });
	gleich('11i: Eingabe bleibt unveraendert', JSON.stringify(eingabe), vorher);
	const aufrufe = [];
	versuche(function () {
		return B.umbrechenTeile(A11, 70, 20, function (t, px, stil) {
			aufrufe.push([t, px, stil]);
			return massF(t, px, stil);
		});
	});
	pruefe('11i: Messfunktion bekommt als dritten Wert {f: 0|1, k: 0|1}',
		aufrufe.length > 0 && aufrufe.every(function (a) {
			return a[2] && (a[2].f === 0 || a[2].f === 1) && (a[2].k === 0 || a[2].k === 1);
		}), JSON.stringify(aufrufe.slice(0, 5)));
	pruefe('11i: fetter Abschnitt wird fett gemessen, normaler normal',
		aufrufe.some(function (a) { return a[0] === 'bb' && a[2] && a[2].f === 1; })
		&& aufrufe.some(function (a) { return a[0] === 'aa ' && a[2] && a[2].f === 0; }), JSON.stringify(aufrufe));
	pruefe('11i: Messung je Abschnitt, nie ueber eine Formatgrenze hinweg',
		aufrufe.length > 0 && aufrufe.every(function (a) { return a[0].indexOf('a b') < 0 && a[0].indexOf('b c') < 0; }), JSON.stringify(aufrufe));
})();

// -------------------------------------------------------------------------
console.log('\nGruppe 12: verteile mit Absatz-Objekten (AP-2.2)');
const ALLE12 = [];
function v12(absaetze, flaeche, optionen) {
	const r = versuche(function () { return B.verteile(absaetze, flaeche, massF, optionen); });
	ALLE12.push(r);
	return r;
}
(function () {
	const zh = 1.2 * 24;
	// 24 px: normal 12 je Zeichen, fett 16,8. „aa bb" = 36 + 33,6 = 69,6,
	// „aa bb cc" = 105,6 > 100.
	const r = v12([A11, { text: 'xy' }], { x: 0, y: 0, b: 100, h: 600 }, {});
	gleich('12a: Block mit teile (\\n gehoert zum vorigen Teil), Absatz ohne Formatierung ohne teile', r, {
		px: 24,
		bloecke: [
			{ text: 'aa bb\ncc', teile: [{ t: 'aa ' }, { t: 'bb\n', f: 1 }, { t: 'cc' }], x: 0, y: 0,
				b: massF('aa ', 24, PLAIN) + massF('bb', 24, FETT), h: 2 * zh },
			{ text: 'xy', x: 0, y: 2 * zh + Math.round(0.6 * 24), b: massF('xy', 24, PLAIN), h: zh }
		],
		rest: []
	});
	const r2 = v12([{ text: 'ab cd', teile: [{ t: 'ab ' }, { t: 'cd' }] }], { x: 0, y: 0, b: 1000, h: 600 }, {});
	pruefe('12a: teile nur aus Klartext -> Block ohne teile',
		r2 && r2.bloecke && r2.bloecke.length === 1 && !('teile' in r2.bloecke[0]), JSON.stringify(r2));
	const r3 = v12([{ text: 'fett\nund normal', teile: [{ t: 'fett', f: 1 }, { t: '\nund normal' }] }], { x: 0, y: 0, b: 1000, h: 600 }, {});
	gleich('12a: harter Zeilenwechsel im Absatz', r3 && r3.bloecke && r3.bloecke[0] && r3.bloecke[0].teile,
		[{ t: 'fett\n', f: 1 }, { t: 'und normal' }]);
	pruefe('12a: Breite eines Blocks = breiteste Zeile, je Teil im eigenen Stil gemessen',
		r3 && r3.bloecke && r3.bloecke[0] && r3.bloecke[0].b === Math.max(massF('fett', 24, FETT), massF('und normal', 24, PLAIN)),
		JSON.stringify(r3 && r3.bloecke));
})();
(function () {
	// 20 px fest: normal 10, fett 14 je Zeichen; Breite 75 -> Zeilen
	// „aaa" / „bbb" / „ccc ddd" / „eee"; Hoehe 50 -> zwei Zeilen passen.
	const A12 = { text: 'aaa bbb ccc ddd eee', teile: [{ t: 'aaa ' }, { t: 'bbb', f: 1 }, { t: ' ccc ddd ' }, { t: 'eee', k: 1 }] };
	const danach = { text: 'danach', teile: [{ t: 'dan', f: 1 }, { t: 'ach' }] };
	const r = v12([A12, 'nachher', danach], { x: 0, y: 0, b: 75, h: 50 }, { startPx: 20, minPx: 20 });
	gleich('12b: Rest eines geteilten formatierten Absatzes ist Objekt {text, teile} ab der Teilungsstelle; Zeichenketten bleiben Zeichenketten', r, {
		px: 20,
		bloecke: [
			{ text: 'aaa\nbbb', teile: [{ t: 'aaa\n' }, { t: 'bbb', f: 1 }], x: 0, y: 0,
				b: Math.max(massF('aaa', 20, PLAIN), massF('bbb', 20, FETT)), h: 2 * (1.2 * 20) }
		],
		rest: [
			{ text: 'ccc ddd eee', teile: [{ t: 'ccc ddd ' }, { t: 'eee', k: 1 }] },
			'nachher',
			{ text: 'danach', teile: [{ t: 'dan', f: 1 }, { t: 'ach' }] }
		]
	});
	const rest0 = r && r.rest && r.rest[0];
	pruefe('12b: Rest-Objekt ohne weiche Umbrueche (kein \\n)', rest0 && typeof rest0.text === 'string' && rest0.text.indexOf('\n') < 0, JSON.stringify(rest0));
	pruefe('12b: Rest-Objekt ist das unveraenderte Ende des Absatzes', rest0 && A12.text.endsWith(rest0.text), JSON.stringify(rest0));

	const B12 = { text: 'aaa bbb ccc ddd eee', teile: [{ t: 'aaa ' }, { t: 'bbb', f: 1 }, { t: ' ccc ddd eee' }] };
	const rb = v12([B12], { x: 0, y: 0, b: 75, h: 50 }, { startPx: 20, minPx: 20 });
	gleich('12b: Rest ohne verbleibende Formatierung -> Objekt ohne teile', rb && rb.rest, [{ text: 'ccc ddd eee' }]);

	const C12 = { text: 'aa bb cc\ndd', teile: [{ t: 'aa ' }, { t: 'bb cc\ndd', k: 1 }] };
	const rc = v12([C12], { x: 0, y: 0, b: 75, h: 24 }, { startPx: 20, minPx: 20 });
	gleich('12b: harter Zeilenwechsel im Rest-Objekt bleibt', rc && rc.rest, [{ text: 'cc\ndd', teile: [{ t: 'cc\ndd', k: 1 }] }]);

	const rd = v12([A12, 'x'], { x: 0, y: 0, b: 75, h: 20 }, { startPx: 20, minPx: 20 });
	gleich('12b: passt keine Zeile -> ganzer Absatz als Objekt im Rest', rd, {
		px: 20, bloecke: [], rest: [{ text: A12.text, teile: A12.teile }, 'x']
	});
})();
(function () {
	const rS = v12(['Erster Absatz', 'Zweiter'], FL, {});
	const rO = v12(['Erster Absatz', { text: 'Zweiter' }], FL, {});
	gleich('12c: gemischte Liste - Objekt ohne teile ergibt dieselben Bloecke wie die Zeichenkette', rO, rS);
	const rM = v12([{ text: 'fett', teile: [{ t: 'fett', f: 1 }] }, 'Zeichenkette', { text: 'kursiv', teile: [{ t: 'kursiv', k: 1 }] }], FL, {});
	pruefe('12c: gemischte Liste - drei Bloecke, nur die formatierten mit teile',
		rM && rM.bloecke && rM.bloecke.length === 3 && 'teile' in rM.bloecke[0] && !('teile' in rM.bloecke[1]) && 'teile' in rM.bloecke[2]
		&& rM.bloecke[1].text === 'Zeichenkette', JSON.stringify(rM));
	pruefe('12c: Zeichenkette in gemischter Liste wie bisher gemessen',
		rM && rM.bloecke && rM.bloecke[1] && rM.bloecke[1].b === mass('Zeichenkette', 24), JSON.stringify(rM && rM.bloecke));
})();
gleich('12d: Objekt mit leerem text wird ignoriert wie eine leere Zeichenkette',
	v12([{ text: '' }, '', { text: 'a' }], FL, {}), v12(['a'], FL, {}));
gleich('12d: unbrauchbare Eintraege (null, {}, {text: 5}) werden ignoriert',
	v12([null, {}, { text: 5 }, { text: '' }], FL, {}), { px: 24, bloecke: [], rest: [] });
(function () {
	// maxZeichen 6: eine Zeile mit 14 Zeichen wird in drei Bloecke geteilt.
	const E12 = { text: 'aaaa bbbb cccc', teile: [{ t: 'aaaa ' }, { t: 'bbbb', f: 1 }, { t: ' cccc' }] };
	const r = v12([E12], FL, { maxZeichen: 6 });
	gleich('12e: Teilung nach maxZeichen - Texte', r && r.bloecke && r.bloecke.map(function (b) { return b.text; }), ['aaaa b', 'bbb cc', 'cc']);
	gleich('12e: Teilung nach maxZeichen - teile folgen, Klartextblock ohne teile',
		r && r.bloecke && r.bloecke.map(function (b) { return b.teile; }),
		[[{ t: 'aaaa ' }, { t: 'b', f: 1 }], [{ t: 'bbb', f: 1 }, { t: ' cc' }], undefined]);
	const F12 = { text: 'eins\nzwei\ndrei', teile: [{ t: 'eins\nz' }, { t: 'wei', f: 1 }, { t: '\ndrei' }] };
	const r2 = v12([F12], FL, { maxZeichen: 9 });
	gleich('12e: Zeilen nach maxZeichen gruppiert, teile folgen',
		r2 && r2.bloecke && r2.bloecke.map(function (b) { return [b.text, b.teile]; }),
		[['eins\nzwei', [{ t: 'eins\nz' }, { t: 'wei', f: 1 }]], ['drei', undefined]]);
	const vorher = JSON.stringify(E12);
	v12([E12, 'x'], { x: 0, y: 0, b: 30, h: 30 }, {});
	gleich('12f: Eingabe bleibt unveraendert', JSON.stringify(E12), vorher);
})();
(function () {
	// Akzeptanzkriterium: fuer jeden Block mit teile gilt Verkettung === text
	// (ueber alle Ergebnisse dieser Gruppe), ebenso fuer Rest-Objekte.
	let bloecke = 0;
	let fehler = [];
	let restObjekte = 0;
	ALLE12.forEach(function (r) {
		if (!r || !Array.isArray(r.bloecke)) { fehler.push('kein Ergebnis: ' + JSON.stringify(r)); return; }
		r.bloecke.forEach(function (b) {
			if (!('teile' in b)) { return; }
			bloecke++;
			if (!teileFormalOk(b.teile) || verkettet(b.teile) !== b.text) { fehler.push(JSON.stringify(b)); }
			if (!b.teile.some(function (p) { return p.f === 1 || p.k === 1 || p.farbe !== undefined; })) { fehler.push('ohne Formatierung: ' + JSON.stringify(b)); }
		});
		r.rest.forEach(function (e) {
			if (typeof e === 'string') { return; }
			restObjekte++;
			if (e.teile !== undefined && (!teileFormalOk(e.teile) || verkettet(e.teile) !== e.text)) { fehler.push('Rest: ' + JSON.stringify(e)); }
		});
	});
	pruefe('12g: Bloecke mit teile vorhanden (' + bloecke + ')', bloecke >= 8);
	pruefe('12g: Rest-Objekte vorhanden (' + restObjekte + ')', restObjekte >= 5);
	pruefe('12g: fuer jeden Block mit teile: Verkettung === text, Teile formal gueltig, mindestens ein formatierter Teil',
		fehler.length === 0, fehler.slice(0, 3).join(' | '));
})();

// -------------------------------------------------------------------------
console.log('\nGruppe 13: lesbareLauffarbe (AP-2.2, F7)');
pruefe('13.0 Schnittstelle: lesbareLauffarbe ist Funktion', typeof B.lesbareLauffarbe === 'function');
function hexKanal(h, i) { return parseInt(h.substr(1 + 2 * i, 2), 16); }
function leuchtdichteH(h) {
	function lin(c) { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }
	return 0.2126 * lin(hexKanal(h, 0)) + 0.7152 * lin(hexKanal(h, 1)) + 0.0722 * lin(hexKanal(h, 2));
}
function kontrastH(a, b) {
	const la = leuchtdichteH(a);
	const lb = leuchtdichteH(b);
	return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}
const HEX6 = /^#[0-9a-f]{6}$/;
function LL(farbe, tafel) { return versuche(function () { return B.lesbareLauffarbe(farbe, tafel); }); }

gleich('13a: #71230a auf Weiss bleibt (Kontrast schon >= 3)', LL('#71230a', '#ffffff'), '#71230a');
(function () {
	const r = LL('#71230a', '#1a472a');
	const ok = typeof r === 'string' && HEX6.test(r);
	pruefe('13b: #71230a auf gruener Tafel: Ergebnis #rrggbb (' + r + ')', ok);
	pruefe('13b: Kontrast >= 3 zu #1a472a', ok && kontrastH(r, '#1a472a') >= 3, ok ? String(kontrastH(r, '#1a472a')) : r);
	pruefe('13b: Farbton erhalten (Rot > Gruen und Rot > Blau)', ok && hexKanal(r, 0) > hexKanal(r, 1) && hexKanal(r, 0) > hexKanal(r, 2), r);
	pruefe('13b: heller als #71230a', ok && leuchtdichteH(r) > leuchtdichteH('#71230a'), r);
})();
(function () {
	const r = LL('#ffff00', '#ffffff');
	const ok = typeof r === 'string' && HEX6.test(r);
	pruefe('13c: Gelb auf Weiss wird dunkler (' + r + ')', ok && leuchtdichteH(r) < leuchtdichteH('#ffff00'), r);
	pruefe('13c: Kontrast >= 3', ok && kontrastH(r, '#ffffff') >= 3, r);
	pruefe('13c: Farbton erhalten (Rot = Gruen > Blau)', ok && hexKanal(r, 0) === hexKanal(r, 1) && hexKanal(r, 1) > hexKanal(r, 2), r);
})();
(function () {
	const r = LL('#000000', '#1c1c1c');
	const ok = typeof r === 'string' && HEX6.test(r);
	pruefe('13d: Schwarz auf schwarzer Tafel wird aufgehellt (' + r + ')', ok && leuchtdichteH(r) > 0, r);
	pruefe('13d: Kontrast >= 3', ok && kontrastH(r, '#1c1c1c') >= 3, r);
	pruefe('13d: bleibt grau (r = g = b)', ok && hexKanal(r, 0) === hexKanal(r, 1) && hexKanal(r, 1) === hexKanal(r, 2), r);
})();
gleich('13e: ungueltige Farbe „rot" -> null', LL('rot', '#ffffff'), null);
gleich('13e: Farbe null -> null', LL(null, '#ffffff'), null);
gleich('13e: Farbe undefined -> null', LL(undefined, '#1a472a'), null);
gleich('13e: Farbe als Zahl -> null', LL(255, '#1a472a'), null);
gleich('13e: ungueltige Tafel gilt als Weiss', LL('#ffff00', 'kaputt'), LL('#ffff00', '#ffffff'));
gleich('13e: fehlende Tafel gilt als Weiss', LL('#ffff00'), LL('#ffff00', '#ffffff'));
gleich('13f: Kurzform/Grossbuchstaben -> #rrggbb klein', LL('#F00', '#FFFFFF'), '#ff0000');
(function () {
	const FARBEN = ['#000000', '#ffffff', '#ff0000', '#0000ff', '#ffff00', '#00ff00', '#71230A', '#AC1313', '#333', '#808080', '#e24614'];
	const TAFELN = ['#ffffff', '#1a472a', '#1c1c1c', '#FFF'];
	// Referenz nach Plan AP-2.2, Schritt 2: dunkle Tafel = Weiss hat mehr
	// Kontrast als Schwarz; mischen farbe + (ziel - farbe) * s je Kanal,
	// s = 0,05, 0,10, ..., 1, gerundet; erste Stufe mit Kontrast >= 3.
	function norm(h) {
		h = h.toLowerCase();
		return h.length === 4 ? '#' + h[1] + h[1] + h[2] + h[2] + h[3] + h[3] : h;
	}
	function referenz(farbe, tafel) {
		const f = norm(farbe);
		const t = norm(tafel);
		if (kontrastH(f, t) >= 3) { return f; }
		const ziel = kontrastH('#ffffff', t) >= kontrastH('#000000', t) ? 255 : 0;
		for (let i = 1; i <= 20; i++) {
			const s = i / 20;
			const neu = '#' + [0, 1, 2].map(function (k) {
				const c = hexKanal(f, k);
				return ('0' + Math.round(c + (ziel - c) * s).toString(16)).slice(-2);
			}).join('');
			if (kontrastH(neu, t) >= 3) { return neu; }
		}
		return ziel ? '#ffffff' : '#000000';
	}
	let alleHex = true;
	let alleKontrast = true;
	let alleIdempotent = true;
	const abweichend = [];
	FARBEN.forEach(function (f) {
		TAFELN.forEach(function (t) {
			const r = LL(f, t);
			if (typeof r !== 'string' || !HEX6.test(r)) { alleHex = false; }
			else {
				if (kontrastH(r, norm(t)) < 3) { alleKontrast = false; }
				if (LL(r, t) !== r) { alleIdempotent = false; }
			}
			if (r !== referenz(f, t)) { abweichend.push(f + '/' + t + ': ' + r + ' statt ' + referenz(f, t)); }
		});
	});
	pruefe('13f: Ergebnis immer #rrggbb klein (11 Farben x 4 Tafeln)', alleHex);
	pruefe('13f: Kontrast immer >= 3', alleKontrast);
	pruefe('13g: idempotent (zweite Anwendung aendert nichts, F7)', alleIdempotent);
	pruefe('13h: Ergebnis = erste 5-%-Stufe mit Kontrast >= 3 (Plan, Schritt 2)', abweichend.length === 0, abweichend.slice(0, 3).join(' | '));
})();

// -------------------------------------------------------------------------
console.log('\nGruppe 14: Surrogatpaare nie trennen (Review AP-1.rev, Befund B1)');
const EMO = '😀';
(function () {
	// 20 px, normal 10 je UTF-16-Einheit; Breite 45 -> hoechstens 4 Einheiten.
	const wort = 'abc' + EMO + 'def';
	const z = versuche(function () { return B.umbrechen(wort, 45, 20, mass); });
	pruefe('14.1 umbrechen: keine Zeile beginnt mit Low- oder endet mit High-Surrogat',
		Array.isArray(z) && z.every(surrogatSauber), JSON.stringify(z));
	gleich('14.1 umbrechen: Emoji bleibt ganz', z, ['abc', EMO + 'de', 'f']);
	const zt = versuche(function () { return B.umbrechenTeile(wort, 45, 20, massF); });
	pruefe('14.2 umbrechenTeile (Zeichenkette): Zeilen und Teile surrogat-sauber',
		Array.isArray(zt) && zt.every(function (l) { return surrogatSauber(l.text) && teileFormalOk(l.teile); }), JSON.stringify(zt));
	[
		[{ t: 'abc' }, { t: EMO, f: 1 }, { t: 'def' }],
		[{ t: 'abc' + EMO, f: 1 }, { t: 'def' }],
		[{ t: 'abc', f: 1 }, { t: EMO + 'def' }],
		[{ t: 'ab' }, { t: 'c' + EMO + 'd', farbe: '#ff0000' }, { t: 'ef', k: 1 }]
	].forEach(function (teile, i) {
		const r = versuche(function () { return B.umbrechenTeile({ text: wort, teile: teile }, 45, 20, massF); });
		pruefe('14.3 umbrechenTeile, Formatgrenze am Emoji (' + (i + 1) + '): Zeilen und Teile surrogat-sauber, Verkettung === Zeile',
			Array.isArray(r) && r.length > 1 && r.every(function (l) {
				return surrogatSauber(l.text) && teileFormalOk(l.teile) && verkettet(l.teile) === l.text;
			}), JSON.stringify(r));
		pruefe('14.3 umbrechenTeile, Formatgrenze am Emoji (' + (i + 1) + '): nichts geht verloren',
			Array.isArray(r) && texte(r).join('') === wort, JSON.stringify(texte(r)));
	});
	const kaputt = versuche(function () {
		return B.umbrechenTeile({ text: 'a' + EMO + 'b', teile: [{ t: 'a\ud83d', f: 1 }, { t: '\ude00b' }] }, 1000, 20, massF);
	});
	pruefe('14.4 Eingabe-Teilgrenze mitten im Paar: Ausgabe-Teile trennen das Paar nicht',
		Array.isArray(kaputt) && kaputt.length === 1 && teileFormalOk(kaputt[0].teile) && verkettet(kaputt[0].teile) === 'a' + EMO + 'b',
		JSON.stringify(kaputt));
})();
(function () {
	// Alle Lagen des Emojis in einem ueberlangen Wort, mehrere Breiten.
	let zeilenGeprueft = 0;
	const fehler = [];
	for (let pos = 0; pos <= 12; pos++) {
		const wort = 'abcdefghijkl'.slice(0, pos) + EMO + 'abcdefghijkl'.slice(pos);
		[25, 35, 45, 55].forEach(function (breite) {
			const z = versuche(function () { return B.umbrechen(wort, breite, 20, mass); });
			const zt = versuche(function () {
				return B.umbrechenTeile({ text: wort, teile: [{ t: wort.slice(0, pos) }, { t: EMO, f: 1 }, { t: wort.slice(pos + 2) }].filter(function (p) { return p.t !== ''; }) }, breite, 20, massF);
			});
			if (!Array.isArray(z) || !Array.isArray(zt)) { fehler.push(pos + '/' + breite + ': kein Array'); return; }
			z.forEach(function (l) { zeilenGeprueft++; if (!surrogatSauber(l)) { fehler.push(pos + '/' + breite + ' umbrechen: ' + JSON.stringify(l)); } });
			zt.forEach(function (l) {
				zeilenGeprueft++;
				if (!surrogatSauber(l.text) || !teileFormalOk(l.teile)) { fehler.push(pos + '/' + breite + ' umbrechenTeile: ' + JSON.stringify(l)); }
			});
			if (z.join('') !== wort || texte(zt).join('') !== wort) { fehler.push(pos + '/' + breite + ': Verlust'); }
		});
	}
	pruefe('14.5 Emoji an jeder Stelle, vier Breiten (' + zeilenGeprueft + ' Zeilen): sauber und verlustfrei', fehler.length === 0 && zeilenGeprueft > 0, fehler.slice(0, 3).join(' | '));
})();
(function () {
	const wort = 'abc' + EMO + 'def';
	const r = versuche(function () { return B.verteile([wort], FL, mass, { maxZeichen: 4 }); });
	pruefe('14.6 verteile mit maxZeichen (Teilung nach Zeichen): Bloecke surrogat-sauber',
		r && Array.isArray(r.bloecke) && r.bloecke.length > 1 && r.bloecke.every(function (b) { return surrogatSauber(b.text); }), JSON.stringify(r));
	gleich('14.6 verteile mit maxZeichen: verlustfrei', r && r.bloecke && r.bloecke.map(function (b) { return b.text; }).join(''), wort);
	const ro = versuche(function () {
		return B.verteile([{ text: wort, teile: [{ t: 'abc', f: 1 }, { t: EMO }, { t: 'def', k: 1 }] }], FL, massF, { maxZeichen: 4 });
	});
	pruefe('14.6 verteile (Objekt) mit maxZeichen: Bloecke und Teile surrogat-sauber',
		ro && Array.isArray(ro.bloecke) && ro.bloecke.length > 1 && ro.bloecke.every(function (b) {
			return surrogatSauber(b.text) && (b.teile === undefined || (teileFormalOk(b.teile) && verkettet(b.teile) === b.text));
		}), JSON.stringify(ro));
	const r1 = versuche(function () { return B.verteile([EMO + EMO], FL, mass, { maxZeichen: 1 }); });
	pruefe('14.6 maxZeichen 1: ein Emoji je Block, nie halbiert',
		r1 && Array.isArray(r1.bloecke) && r1.bloecke.length === 2 && r1.bloecke.every(function (b) { return b.text === EMO; }), JSON.stringify(r1));
})();

ende();
