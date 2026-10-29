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
gleich('genau die sechs Funktionen der Schnittstelle (Plan, Abschnitt 4)',
	Object.keys(B).sort(),
	['absaetzeAusText', 'flaecheUnter', 'latexZuText', 'lesbareFarbe', 'umbrechen', 'verteile']);
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

ende();
