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

ende();
