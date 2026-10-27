#!/usr/bin/env node
/**
 * Standalone-Harnisch fuer das Elementmodul `assets/js/board-elemente.js`
 * (Plan „Rueckmeldungen Klassenmodus und Tafel-Lasso", Phase 4, AP-4.1,
 * Architektur E6) — ohne Browser, ohne jsdom, ohne npm-Abhaengigkeit.
 *
 * Aufruf:  node tools/test-board-elemente-js.js
 *
 * Eine andere Datei pruefen (fuer Mutationsproben):
 *          CBD_ELEMENTE_DATEI=<pfad> node tools/test-board-elemente-js.js
 *
 * ---------------------------------------------------------------------------
 * VERFAHREN
 * ---------------------------------------------------------------------------
 *
 * Die AUSGELIEFERTE Datei wird per `fs` gelesen und in einem `vm`-Kontext
 * ausgefuehrt, dessen einziges globales Objekt ein leeres `window` ist. Gibt
 * es dort keinen Zugriff auf Dokument, Speicher oder Zeichenflaeche, kann das
 * Modul keinen machen — ein Zugriff wuerde als ReferenceError auffallen.
 * Zusaetzlich prueft Gruppe 0 den Quelltext auf diese Namen.
 *
 * Geprueft wird ausschliesslich ueber die oeffentliche Schnittstelle
 * `window.CBDBoardElemente`. Format: Plan, Abschnitt 4, „Elementmodell v1".
 *
 * Exit-Code 0 = alles gruen, 1 = mindestens ein Fehlschlag.
 *
 * @package ContainerBlockDesigner
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const QUELLDATEI = process.env.CBD_ELEMENTE_DATEI
	|| path.join(__dirname, '..', 'assets', 'js', 'board-elemente.js');

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

/** Tiefe Kopie ueber JSON (Testdaten enthalten nur JSON-Werte). */
function kopie(o) {
	return JSON.parse(JSON.stringify(o));
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

const E = kontext.window.CBDBoardElemente;
pruefe('window.CBDBoardElemente ist gesetzt', E && typeof E === 'object');
if (!E) { ende(); }

[
	'pruefeModell', 'ausStrokes', 'zuStrokes', 'rahmen', 'punktImPolygon',
	'waehleImLasso', 'verschiebe', 'entferne', 'radiereKreis', 'skaliere',
	'serialisiere', 'deserialisiere'
].forEach(function (n) {
	pruefe('Schnittstelle: ' + n + ' ist Funktion', typeof E[n] === 'function');
});

// =========================================================================
// Gruppe 0: Modul ist DOM-frei (Akzeptanzkriterium)
// =========================================================================
console.log('\nGruppe 0: DOM-frei');
pruefe('kein Zugriff auf document', !/\bdocument\b/.test(quelle));
pruefe('kein Zugriff auf localStorage/sessionStorage', !/localStorage|sessionStorage/.test(quelle));
pruefe('kein Canvas-Zugriff (getContext/Canvas/new Image)',
	!/getContext|canvas|new\s+Image\b/i.test(quelle));

// Messfunktion fuer Texte: 10 px je Zeichen und je Pixel Schriftgroesse/10
// (so ist sie von der Schriftgroesse abhaengig, ohne Canvas).
function mass(text, schriftPx) {
	return text.length * schriftPx / 2;
}

// Ein gueltiges Beispielmodell nach Abschnitt 4 des Plans.
const BILD_DATEN = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
const MODELL = {
	v: 1,
	breite: 1200,
	hoehe: 800,
	elemente: [
		{ id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#000000', breite: 3,
			punkte: [[10.5, 20], [12, 22.25]] },
		{ id: 'e2', typ: 'strich', werkzeug: 'highlighter', farbe: 'rgba(255,235,59,0.3)', breite: 18,
			punkte: [[100, 40], [180, 40]] },
		{ id: 'e3', typ: 'text', farbe: '#1565c0', breite: 3, text: 'Na⁺ + Cl⁻', x: 40, y: 300 },
		{ id: 'e4', typ: 'bild', daten: BILD_DATEN, x: 0, y: 0, b: 1200, h: 800 }
	]
};

function mitElement(el) {
	return { v: 1, breite: 1200, hoehe: 800, elemente: [el] };
}

// =========================================================================
// Gruppe 1: pruefeModell
// =========================================================================
console.log('\nGruppe 1: pruefeModell');
(function () {
	let r = E.pruefeModell(kopie(MODELL));
	pruefe('1.1 gueltiges Modell liefert {modell, verworfen}', r && r.modell && typeof r.verworfen === 'number');
	gleich('1.2 gueltiges Modell bleibt gleich', r && r.modell, MODELL);
	gleich('1.3 gueltiges Modell: verworfen = 0', r && r.verworfen, 0);

	const eingabe = kopie(MODELL);
	E.pruefeModell(eingabe);
	gleich('1.4 Eingabe wird nicht veraendert', eingabe, MODELL);

	let m = kopie(MODELL);
	m.elemente[0].geheim = 'x';
	m.extra = 5;
	r = E.pruefeModell(m);
	pruefe('1.5 unbekanntes Feld im Element entfernt', r && !('geheim' in r.modell.elemente[0]));
	pruefe('1.6 unbekanntes Feld oben entfernt', r && !('extra' in r.modell));
	gleich('1.7 unbekanntes Feld zaehlt nicht als verworfen', r && r.verworfen, 0);

	m = kopie(MODELL);
	m.elemente.push({ id: 'e5', typ: 'kreis', x: 1, y: 2, r: 3 });
	r = E.pruefeModell(m);
	gleich('1.8 typ "kreis" verworfen (verworfen = 1)', r && r.verworfen, 1);
	gleich('1.9 uebrige Elemente bleiben', r && r.modell.elemente.length, 4);

	[NaN, Infinity, -Infinity, '12'].forEach(function (wert, i) {
		const s = kopie(MODELL.elemente[0]);
		s.punkte[1][0] = wert;
		r = E.pruefeModell(mitElement(s));
		gleich('1.10.' + i + ' Strichkoordinate ' + String(wert) + ' -> Element verworfen',
			r && [r.modell.elemente.length, r.verworfen], [0, 1]);
	});
	[NaN, Infinity, '40'].forEach(function (wert, i) {
		const t = kopie(MODELL.elemente[2]);
		t.x = wert;
		r = E.pruefeModell(mitElement(t));
		gleich('1.11.' + i + ' Textkoordinate ' + String(wert) + ' -> Element verworfen',
			r && [r.modell.elemente.length, r.verworfen], [0, 1]);
	});
	(function () {
		const b = kopie(MODELL.elemente[3]);
		b.b = Infinity;
		r = E.pruefeModell(mitElement(b));
		gleich('1.12 Bildbreite Infinity -> verworfen', r && r.verworfen, 1);
	})();

	['red; x', 'red', 'url(javascript:1)', '#12', '#1234567', 'rgb(1,2)', 'rgba(1,2,3,4);x'].forEach(function (f, i) {
		const s = kopie(MODELL.elemente[0]);
		s.farbe = f;
		r = E.pruefeModell(mitElement(s));
		gleich('1.13.' + i + ' Farbe ' + JSON.stringify(f) + ' -> verworfen', r && r.verworfen, 1);
	});
	['#abc', '#A1b2C3', 'rgb(1, 2, 3)', 'rgba(255, 255, 200, 1.0)'].forEach(function (f, i) {
		const s = kopie(MODELL.elemente[0]);
		s.farbe = f;
		r = E.pruefeModell(mitElement(s));
		gleich('1.14.' + i + ' Farbe ' + JSON.stringify(f) + ' -> gueltig', r && r.verworfen, 0);
	});

	['javascript:alert(1)', 'data:image/svg+xml;base64,PHN2Zz4=', 'iVBORw0KGgo=',
		'data:image/png;base64,abc"><x', 42].forEach(function (d, i) {
		const b = kopie(MODELL.elemente[3]);
		b.daten = d;
		r = E.pruefeModell(mitElement(b));
		gleich('1.15.' + i + ' Bilddaten ' + JSON.stringify(String(d).slice(0, 24)) + ' -> verworfen',
			r && r.verworfen, 1);
	});

	gleich('1.16 v = 2 -> null', E.pruefeModell({ v: 2, breite: 10, hoehe: 10, elemente: [] }), null);
	gleich('1.17 v fehlt -> null', E.pruefeModell({ breite: 10, hoehe: 10, elemente: [] }), null);
	gleich('1.18 v = "1" -> null', E.pruefeModell({ v: '1', breite: 10, hoehe: 10, elemente: [] }), null);
	gleich('1.19 null -> null', E.pruefeModell(null), null);
	gleich('1.20 Zahl -> null', E.pruefeModell(5), null);
	gleich('1.21 Zeichenkette -> null', E.pruefeModell('{"v":1}'), null);
	gleich('1.22 Array -> null', E.pruefeModell([1]), null);
	gleich('1.23 undefined -> null', E.pruefeModell(undefined), null);
	gleich('1.24 elemente kein Array -> null', E.pruefeModell({ v: 1, breite: 10, hoehe: 10, elemente: {} }), null);
	gleich('1.25 breite keine Zahl -> null', E.pruefeModell({ v: 1, breite: 'x', hoehe: 10, elemente: [] }), null);
	gleich('1.26 hoehe 0 -> null', E.pruefeModell({ v: 1, breite: 10, hoehe: 0, elemente: [] }), null);

	r = E.pruefeModell({ v: 1, breite: 10, hoehe: 10, elemente: [] });
	gleich('1.27 leere Tafel ist gueltig', r && r.modell, { v: 1, breite: 10, hoehe: 10, elemente: [] });

	const lang = new Array(5011).join('a'); // 5010 Zeichen
	const t = kopie(MODELL.elemente[2]);
	t.text = lang;
	r = E.pruefeModell(mitElement(t));
	gleich('1.28 Text > 5000 Zeichen auf 5000 gekuerzt', r && r.modell.elemente[0].text.length, 5000);
	gleich('1.29 gekuerzter Text zaehlt nicht als verworfen', r && r.verworfen, 0);
	const genau = kopie(MODELL.elemente[2]);
	genau.text = new Array(5001).join('b');
	r = E.pruefeModell(mitElement(genau));
	gleich('1.30 Text mit genau 5000 Zeichen unveraendert', r && r.modell.elemente[0].text.length, 5000);
	const keinText = kopie(MODELL.elemente[2]);
	keinText.text = 12;
	r = E.pruefeModell(mitElement(keinText));
	gleich('1.31 text keine Zeichenkette -> verworfen', r && r.verworfen, 1);

	function strichMit(n) {
		const p = [];
		for (let i = 0; i < n; i++) p.push([i % 1000, Math.floor(i / 1000)]);
		return { id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#000', breite: 2, punkte: p };
	}
	r = E.pruefeModell(mitElement(strichMit(20001)));
	gleich('1.32 Strich mit 20 001 Punkten verworfen', r && [r.modell.elemente.length, r.verworfen], [0, 1]);
	r = E.pruefeModell(mitElement(strichMit(20000)));
	gleich('1.33 Strich mit genau 20 000 Punkten gueltig', r && r.verworfen, 0);

	const ohnePunkte = strichMit(0);
	r = E.pruefeModell(mitElement(ohnePunkte));
	gleich('1.34 Strich ohne Punkte verworfen', r && r.verworfen, 1);
	const punkt = strichMit(1);
	r = E.pruefeModell(mitElement(punkt));
	gleich('1.35 Strich mit 1 Punkt (Tupfer) gueltig', r && r.verworfen, 0);

	const falschesWerkzeug = kopie(MODELL.elemente[0]);
	falschesWerkzeug.werkzeug = 'eraser-point';
	r = E.pruefeModell(mitElement(falschesWerkzeug));
	gleich('1.36 werkzeug ausserhalb {pen, highlighter} verworfen', r && r.verworfen, 1);

	const punktForm = kopie(MODELL.elemente[0]);
	punktForm.punkte = [[1, 2, 3], [4, 5]];
	r = E.pruefeModell(mitElement(punktForm));
	gleich('1.37 Punkt mit drei Koordinaten verworfen', r && r.verworfen, 1);

	const ohneId = kopie(MODELL.elemente[0]);
	ohneId.id = 'x1';
	r = E.pruefeModell(mitElement(ohneId));
	gleich('1.38 id nicht im Format e<Zahl> verworfen', r && r.verworfen, 1);
	m = kopie(MODELL);
	m.elemente[1].id = 'e1';
	r = E.pruefeModell(m);
	gleich('1.39 doppelte id: spaeteres Element verworfen',
		r && [r.modell.elemente.length, r.verworfen, r.modell.elemente[0].werkzeug], [3, 1, 'pen']);

	m = kopie(MODELL);
	m.elemente.push('kein Objekt', null, 7);
	r = E.pruefeModell(m);
	gleich('1.40 Nicht-Objekte in elemente verworfen', r && [r.modell.elemente.length, r.verworfen], [4, 3]);

	const negBreite = kopie(MODELL.elemente[0]);
	negBreite.breite = -1;
	r = E.pruefeModell(mitElement(negBreite));
	gleich('1.41 negative Strichbreite verworfen', r && r.verworfen, 1);
})();

// =========================================================================
// Gruppe 2: ausStrokes / zuStrokes
// =========================================================================
console.log('\nGruppe 2: ausStrokes / zuStrokes');
(function () {
	const strokes = [
		{ tool: 'pen', color: '#ff0000', width: 4, points: [{ x: 1, y: 2 }, { x: 3.5, y: 4.25 }] },
		{ tool: 'highlighter', color: 'rgba(255, 255, 200, 1.0)', width: 20, points: [{ x: 10, y: 10 }, { x: 50, y: 10 }] },
		{ tool: 'text', text: 'Hallo\nWelt', color: '#1565c0', width: 3, points: [{ x: 40, y: 60 }] },
		{ tool: 'pen', color: '#000', width: 2, points: [{ x: 7, y: 7 }] }
	];
	const original = kopie(strokes);
	const m = E.ausStrokes(strokes, 800, 600);
	gleich('2.1 Kopf des Modells', m && [m.v, m.breite, m.hoehe, m.elemente.length], [1, 800, 600, 4]);
	gleich('2.2 Stift-Element', m && m.elemente[0],
		{ id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#ff0000', breite: 4, punkte: [[1, 2], [3.5, 4.25]] });
	gleich('2.3 Textmarker-Element', m && m.elemente[1],
		{ id: 'e2', typ: 'strich', werkzeug: 'highlighter', farbe: 'rgba(255, 255, 200, 1.0)', breite: 20, punkte: [[10, 10], [50, 10]] });
	gleich('2.4 Text-Element', m && m.elemente[2],
		{ id: 'e3', typ: 'text', farbe: '#1565c0', breite: 3, text: 'Hallo\nWelt', x: 40, y: 60 });
	const ids = m ? m.elemente.map(function (e) { return e.id; }) : [];
	gleich('2.5 IDs fortlaufend eindeutig', ids, ['e1', 'e2', 'e3', 'e4']);
	gleich('2.6 Rueckweg zuStrokes verlustfrei', m && E.zuStrokes(m), original);
	gleich('2.7 Eingabe-strokes unveraendert', strokes, original);
	const pm = m && E.pruefeModell(m);
	gleich('2.8 ausStrokes-Ergebnis besteht pruefeModell', pm && pm.verworfen, 0);

	const mitFremd = E.ausStrokes([{ tool: 'eraser-point', color: '#000', width: 2, points: [{ x: 1, y: 1 }] }], 10, 10);
	gleich('2.9 unbekanntes Werkzeug wird nicht uebernommen', mitFremd && mitFremd.elemente.length, 0);
	gleich('2.10 leere strokes -> leeres Modell', E.ausStrokes([], 10, 20), { v: 1, breite: 10, hoehe: 20, elemente: [] });

	// IDs ueber eine uebergebene Funktion (AP-4.3: eindeutig ueber das geladene Modell hinaus)
	let n = 41;
	const mitId = E.ausStrokes(strokes.slice(0, 2), 10, 10, function () { return 'e' + (n++); });
	gleich('2.11 ausStrokes nutzt uebergebenes neueId()', mitId && mitId.elemente.map(function (e) { return e.id; }), ['e41', 'e42']);

	const zu = E.zuStrokes(kopie(MODELL));
	gleich('2.12 zuStrokes laesst Bild-Elemente aus', zu.length, 3);
	gleich('2.13 zuStrokes Text', zu[2], { tool: 'text', text: 'Na⁺ + Cl⁻', color: '#1565c0', width: 3, points: [{ x: 40, y: 300 }] });
})();

// =========================================================================
// Gruppe 3: rahmen
// =========================================================================
console.log('\nGruppe 3: rahmen');
(function () {
	const s = { id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#000', breite: 4, punkte: [[10, 20], [30, 5], [20, 40]] };
	gleich('3.1 Strich: Punkte-Huelle +- halbe Breite', E.rahmen(s, mass), { x: 8, y: 3, b: 24, h: 39 });
	const p = { id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#000', breite: 6, punkte: [[50, 50]] };
	gleich('3.2 Strich mit einem Punkt', E.rahmen(p, mass), { x: 47, y: 47, b: 6, h: 6 });
	gleich('3.3 Bild = x,y,b,h', E.rahmen(MODELL.elemente[3], mass), { x: 0, y: 0, b: 1200, h: 800 });

	// breite 1 -> Schrift 20 px; Zeilen "ab" (2) und "abcd" (4) -> breiteste 4*20/2 = 40;
	// Hoehe 2 Zeilen * 1.2 * 20 = 48.
	const t = { id: 'e3', typ: 'text', farbe: '#000', breite: 1, text: 'ab\nabcd', x: 5, y: 7 };
	const r = E.rahmen(t, mass);
	gleich('3.4 Text ueber Messfunktion (Zeilen x 1,2 x Schrift)', r, { x: 5, y: 7, b: 40, h: 48 });
	const aufrufe = [];
	E.rahmen(t, function (text, px) { aufrufe.push([text, px]); return 0; });
	gleich('3.5 Messfunktion bekommt Zeile und Schriftgroesse Math.round(16 + breite*4)',
		aufrufe, [['ab', 20], ['abcd', 20]]);
	const t2 = { id: 'e3', typ: 'text', farbe: '#000', breite: 2.6, text: 'x', x: 0, y: 0 };
	const r2 = E.rahmen(t2, function () { return 10; });
	// Schrift Math.round(16 + 10.4) = 26 -> Hoehe 31.2
	pruefe('3.6 Schriftgroesse gerundet (26 px -> Hoehe 31,2)', r2 && Math.abs(r2.h - 31.2) < 1e-9, JSON.stringify(r2));
})();

// =========================================================================
// Gruppe 4: punktImPolygon
// =========================================================================
console.log('\nGruppe 4: punktImPolygon');
(function () {
	const quadrat = [[0, 0], [10, 0], [10, 10], [0, 10]];
	pruefe('4.1 innen', E.punktImPolygon(5, 5, quadrat) === true);
	pruefe('4.2 aussen', E.punktImPolygon(15, 5, quadrat) === false);
	pruefe('4.3 aussen (negativ)', E.punktImPolygon(-1, -1, quadrat) === false);
	// U-Form (konkav): Einbuchtung zwischen x=4..6, y=4..10
	const u = [[0, 0], [10, 0], [10, 10], [6, 10], [6, 4], [4, 4], [4, 10], [0, 10]];
	pruefe('4.4 konkav: in der Einbuchtung aussen', E.punktImPolygon(5, 8, u) === false);
	pruefe('4.5 konkav: linker Schenkel innen', E.punktImPolygon(2, 8, u) === true);
	pruefe('4.6 konkav: rechter Schenkel innen', E.punktImPolygon(8, 8, u) === true);
	pruefe('4.7 konkav: Boden innen', E.punktImPolygon(5, 2, u) === true);
	pruefe('4.8 < 3 Punkte -> false', E.punktImPolygon(1, 1, [[0, 0], [10, 10]]) === false);
	pruefe('4.9 leeres Polygon -> false', E.punktImPolygon(1, 1, []) === false);
	pruefe('4.10 kein Array -> false', E.punktImPolygon(1, 1, null) === false);
	pruefe('4.11 Polygon als {x,y}-Punkte', E.punktImPolygon(5, 5,
		[{ x: 0, y: 0 }, { x: 10, y: 0 }, { x: 10, y: 10 }, { x: 0, y: 10 }]) === true);
})();

// =========================================================================
// Gruppe 5: waehleImLasso (Regel E14)
// =========================================================================
console.log('\nGruppe 5: waehleImLasso');
(function () {
	const lasso = [[0, 0], [100, 0], [100, 100], [0, 100]];
	function strich(id, punkte) {
		return { id: id, typ: 'strich', werkzeug: 'pen', farbe: '#000', breite: 2, punkte: punkte };
	}
	const m = {
		v: 1, breite: 500, hoehe: 500, elemente: [
			strich('e1', [[10, 10], [20, 20], [30, 30], [40, 40]]),            // 4/4 innen
			strich('e2', [[10, 10], [20, 20], [200, 200], [300, 300]]),        // 2/4 = 50 % -> gewaehlt
			strich('e3', [[10, 10], [200, 200], [300, 300], [400, 400]]),      // 1/4 -> nicht
			strich('e4', [[200, 200], [300, 300]]),                            // 0 -> nicht
			// Text: x=80, y=10, Schrift 20 px, "abcd" -> b 40, h 24 -> Mitte (100, 22) liegt AUF
			// dem Rand x=100 -> besser deutlich: Text bei x=90 -> Mitte (110, 22) aussen
			{ id: 'e5', typ: 'text', farbe: '#000', breite: 1, text: 'abcd', x: 90, y: 10 },
			// Text bei x=10 -> Mitte (30, 22) innen, obwohl Rahmen bis 50 reicht
			{ id: 'e6', typ: 'text', farbe: '#000', breite: 1, text: 'abcd', x: 10, y: 10 },
			// Bild 0..150 -> Mitte (75,75) innen, obwohl Rahmen ueber das Lasso hinausgeht
			{ id: 'e7', typ: 'bild', daten: BILD_DATEN, x: 0, y: 0, b: 150, h: 150 },
			// Bild 60..260 -> Mitte (160,160) aussen, obwohl es das Lasso ueberlappt
			{ id: 'e8', typ: 'bild', daten: BILD_DATEN, x: 60, y: 60, b: 200, h: 200 }
		]
	};
	gleich('5.1 Auswahl nach E14', E.waehleImLasso(m, lasso, mass), ['e1', 'e2', 'e6', 'e7']);
	gleich('5.2 Lasso mit < 3 Punkten waehlt nichts', E.waehleImLasso(m, [[0, 0], [100, 100]], mass), []);
	// Strich mit 3 Punkten, 1 innen -> 33 % -> nicht; 2 innen -> 67 % -> ja
	const m2 = { v: 1, breite: 500, hoehe: 500, elemente: [
		strich('e1', [[10, 10], [200, 200], [300, 300]]),
		strich('e2', [[10, 10], [20, 20], [300, 300]])
	] };
	gleich('5.3 Strich 1/3 nicht, 2/3 gewaehlt', E.waehleImLasso(m2, lasso, mass), ['e2']);
})();

// =========================================================================
// Gruppe 6: verschiebe / entferne
// =========================================================================
console.log('\nGruppe 6: verschiebe / entferne');
(function () {
	const m = kopie(MODELL);
	const vorher = kopie(m);
	const v = E.verschiebe(m, ['e1', 'e3', 'e4'], 5, -10);
	gleich('6.1 Original unveraendert', m, vorher);
	gleich('6.2 Strich verschoben', v.elemente[0].punkte, [[15.5, 10], [17, 12.25]]);
	gleich('6.3 nicht gewaehlter Strich bleibt', v.elemente[1], MODELL.elemente[1]);
	gleich('6.4 Text verschoben', [v.elemente[2].x, v.elemente[2].y], [45, 290]);
	gleich('6.5 Bild verschoben', [v.elemente[3].x, v.elemente[3].y, v.elemente[3].b, v.elemente[3].h], [5, -10, 1200, 800]);
	gleich('6.6 Kopf bleibt', [v.v, v.breite, v.hoehe, v.elemente.length], [1, 1200, 800, 4]);
	pruefe('6.7 verschiebe liefert neues Objekt', v !== m && v.elemente !== m.elemente);
	gleich('6.8 leere Auswahl -> inhaltsgleich', E.verschiebe(m, [], 3, 3), vorher);

	const e = E.entferne(m, ['e2', 'e4']);
	gleich('6.9 entferne: uebrige IDs', e.elemente.map(function (x) { return x.id; }), ['e1', 'e3']);
	gleich('6.10 entferne: Original unveraendert', m, vorher);
	pruefe('6.11 entferne liefert neues Objekt', e !== m && e.elemente !== m.elemente);
	gleich('6.12 entferne unbekannte ID -> inhaltsgleich', E.entferne(m, ['e99']), vorher);
})();

// =========================================================================
// Gruppe 7: radiereKreis (Segment-Kreis-Schnitt)
// =========================================================================
console.log('\nGruppe 7: radiereKreis');
(function () {
	let n = 100;
	function neueId() { return 'e' + (n++); }
	function abstand(p, cx, cy) { return Math.sqrt((p[0] - cx) * (p[0] - cx) + (p[1] - cy) * (p[1] - cy)); }
	function strich(punkte) {
		return { id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#123456', breite: 3, punkte: punkte };
	}

	const aussen = strich([[0, 0], [10, 0], [20, 0]]);
	const ra = E.radiereKreis(aussen, 50, 50, 5, neueId);
	pruefe('7.1 ganz ausserhalb -> [element] unveraendert', ra.length === 1 && ra[0] === aussen);

	const innen = strich([[49, 50], [50, 50], [51, 51]]);
	gleich('7.2 ganz innen -> []', E.radiereKreis(innen, 50, 50, 5, neueId), []);

	// Langes Segment OHNE Zwischenpunkte quer durch den Kreis
	const quer = strich([[0, 50], [100, 50]]);
	const vorher = kopie(quer);
	const rq = E.radiereKreis(quer, 50, 50, 10, neueId);
	gleich('7.3 mittig durchschnittenes Einzelsegment -> 2 Striche', rq.length, 2);
	pruefe('7.4 alle Punkte >= r vom Mittelpunkt', rq.every(function (s) {
		return s.punkte.every(function (p) { return abstand(p, 50, 50) >= 10; });
	}), JSON.stringify(rq.map(function (s) { return s.punkte; })));
	pruefe('7.5 Teilstuecke reichen an den Kreisrand (<= r + 0,01)', rq.length === 2
		&& Math.abs(abstand(rq[0].punkte[rq[0].punkte.length - 1], 50, 50) - 10) <= 0.01
		&& Math.abs(abstand(rq[1].punkte[0], 50, 50) - 10) <= 0.01,
		JSON.stringify(rq.map(function (s) { return s.punkte; })));
	gleich('7.6 Anfang und Ende bleiben erhalten', rq.length === 2 && [rq[0].punkte[0], rq[1].punkte[rq[1].punkte.length - 1]], [[0, 50], [100, 50]]);
	gleich('7.7 Stil bleibt (werkzeug, farbe, breite, typ)', rq.map(function (s) { return [s.typ, s.werkzeug, s.farbe, s.breite]; }),
		[['strich', 'pen', '#123456', 3], ['strich', 'pen', '#123456', 3]]);
	pruefe('7.8 neue IDs eindeutig und ueber neueId()', rq.length === 2 && rq[0].id !== rq[1].id
		&& /^e1\d\d$/.test(rq[0].id) && /^e1\d\d$/.test(rq[1].id), rq.map(function (s) { return s.id; }).join(','));
	gleich('7.9 Eingabe-Element unveraendert', quer, vorher);

	// Mehrpunkt-Strich, mittig durchschnitten
	const viele = strich([[0, 0], [10, 0], [20, 0], [30, 0], [40, 0], [50, 0], [60, 0], [70, 0], [80, 0], [90, 0], [100, 0]]);
	const rv = E.radiereKreis(viele, 50, 0, 15, neueId);
	gleich('7.10 Mehrpunkt-Strich -> 2 Striche', rv.length, 2);
	pruefe('7.11 Mehrpunkt: alle Punkte >= r', rv.every(function (s) {
		return s.punkte.every(function (p) { return abstand(p, 50, 0) >= 15; });
	}));
	pruefe('7.12 Mehrpunkt: Rand bei x=35 und x=65 erreicht', rv.length === 2
		&& Math.abs(rv[0].punkte[rv[0].punkte.length - 1][0] - 35) <= 0.01
		&& Math.abs(rv[1].punkte[0][0] - 65) <= 0.01);

	// Ende angeschnitten: Ergebnis ein Strich, der am Rand endet
	const ende = strich([[0, 0], [100, 0]]);
	const re = E.radiereKreis(ende, 100, 0, 10, neueId);
	gleich('7.13 Ende abgeschnitten -> 1 Strich bis x=90', re.length === 1 && [re[0].punkte.length, Math.round(re[0].punkte[1][0] * 100) / 100], [2, 90]);

	// Ergebnisstriche mit < 2 Punkten entfallen: Strich, dessen Rest nur ein Punkt waere
	const kurz = strich([[0, 0], [10, 0]]);
	// Kreis um (10,0) mit r=10 schneidet genau bei x=0 -> Rest waere nur der Punkt (0,0)
	gleich('7.14 Reststueck mit < 2 Punkten entfaellt', E.radiereKreis(kurz, 10, 0, 10, neueId), []);

	// Segment beruehrt den Kreis nur tangential -> unveraendert
	const tangente = strich([[0, 10], [100, 10]]);
	const rt = E.radiereKreis(tangente, 50, 0, 10, neueId);
	pruefe('7.15 tangential -> unveraendert', rt.length === 1 && rt[0] === tangente);

	// Ein-Punkt-Strich (Tupfer)
	const tupfer = strich([[50, 50]]);
	gleich('7.16 Tupfer im Kreis -> []', E.radiereKreis(tupfer, 50, 52, 5, neueId), []);
	const tupfer2 = strich([[50, 50]]);
	const rt2 = E.radiereKreis(tupfer2, 0, 0, 5, neueId);
	pruefe('7.17 Tupfer ausserhalb -> unveraendert', rt2.length === 1 && rt2[0] === tupfer2);

	// Zwei Durchquerungen -> drei Stuecke
	const zickzack = strich([[0, 0], [100, 0], [100, 40], [0, 40]]);
	const rz = E.radiereKreis(zickzack, 50, 20, 25, neueId);
	gleich('7.18 zwei Durchquerungen -> 3 Striche', rz.length, 3);
	const alleIds = rz.map(function (s) { return s.id; });
	pruefe('7.19 drei IDs paarweise verschieden', alleIds.length === 3 && alleIds[0] !== alleIds[1]
		&& alleIds[1] !== alleIds[2] && alleIds[0] !== alleIds[2], alleIds.join(','));

	// Schraege Segmente mit krummen Zahlen: der berechnete Randpunkt liegt
	// wegen Gleitkomma-Rundung sonst gern um 1e-15 IM Kreis (Mutationsprobe:
	// ohne das Hinausschieben ueberlebte die Mutation die Faelle 7.3-7.19).
	let schraegOk = true;
	let schraegZaehler = 0;
	let detail = '';
	for (let k = 0; k < 200; k++) {
		const ax = (k * 37.17) % 97.3, ay = (k * 11.93) % 61.7;
		const bx = 300 - (k * 23.41) % 89.9, by = 200 - (k * 7.77) % 53.1;
		const mx = (ax + bx) / 2 + 0.123, my = (ay + by) / 2 - 0.071;
		const r = 3.3 + (k % 17) * 1.37;
		const teile = E.radiereKreis(strich([[ax, ay], [bx, by]]), mx, my, r, neueId);
		if (teile.length !== 2) { schraegOk = false; detail = 'k=' + k + ' Teile=' + teile.length; break; }
		teile.forEach(function (t) {
			t.punkte.forEach(function (p) {
				schraegZaehler++;
				if (abstand(p, mx, my) < r) { schraegOk = false; detail = 'k=' + k + ' d-r=' + (abstand(p, mx, my) - r); }
			});
		});
	}
	pruefe('7.21 200 schraege Schnitte: kein Punkt im Kreis (' + schraegZaehler + ' Punkte)', schraegOk, detail);

	// AP-4.fix1 (aus AP-4.rev G1): Stuetzpunkt knapp innerhalb des Kreises
	// (0,95 r) muss entfernt werden - die Mutation `rInnen = r * 0.9`
	// ueberlebte vorher alle Faelle.
	const knapp = strich([[0, 0], [40.5, 0], [100, 0]]);
	const rk = E.radiereKreis(knapp, 50, 0, 10, neueId);
	pruefe('7.22 Stuetzpunkt bei 0,95 r wird entfernt, kein Punkt im Kreis', rk.length === 2 && rk.every(function (s) {
		return s.punkte.every(function (p) { return abstand(p, 50, 0) >= 10; });
	}), JSON.stringify(rk.map(function (s) { return s.punkte; })));

	// Nicht-Strich-Elemente werden nicht angefasst
	const text = kopie(MODELL.elemente[2]);
	const rtx = E.radiereKreis(text, 40, 300, 50, neueId);
	pruefe('7.20 Text-Element -> [element] unveraendert', rtx.length === 1 && rtx[0] === text);
})();

// =========================================================================
// Gruppe 8: skaliere
// =========================================================================
console.log('\nGruppe 8: skaliere');
(function () {
	const m = kopie(MODELL);
	const vorher = kopie(m);
	const s = E.skaliere(m, 600, 1600); // fx = 0.5, fy = 2, Mittel 1.25
	gleich('8.1 Kopf = Ziel', [s.breite, s.hoehe], [600, 1600]);
	gleich('8.2 Strichpunkte gestreckt', s.elemente[0].punkte, [[5.25, 40], [6, 44.5]]);
	gleich('8.3 Strichbreite mit Mittel beider Faktoren', [s.elemente[0].breite, s.elemente[1].breite], [3.75, 22.5]);
	gleich('8.4 Text-Anker gestreckt', [s.elemente[2].x, s.elemente[2].y], [20, 600]);
	// Schriftgroesse (16 + 4*3 = 28) mit Mittel 1.25 -> 35 px -> breite (35 - 16) / 4 = 4.75
	pruefe('8.5 Textgroesse mit Mittel beider Faktoren (28 px -> 35 px)',
		Math.abs((16 + s.elemente[2].breite * 4) - 35) < 1e-9, String(s.elemente[2].breite));
	gleich('8.6 Bild gestreckt', [s.elemente[3].x, s.elemente[3].y, s.elemente[3].b, s.elemente[3].h], [0, 0, 600, 1600]);
	gleich('8.7 Original unveraendert', m, vorher);
	gleich('8.8 Faktor 1 -> identisch', E.skaliere(m, 1200, 800), vorher);
	pruefe('8.9 Faktor 1 liefert trotzdem eigenes Objekt', E.skaliere(m, 1200, 800) !== m);
})();

// =========================================================================
// Gruppe 9: serialisiere / deserialisiere
// =========================================================================
console.log('\nGruppe 9: serialisiere / deserialisiere');
(function () {
	const m = {
		v: 1, breite: 1200.123, hoehe: 800, elemente: [
			{ id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#000', breite: 3.14159,
				// Bewusst keine Halbwerte wie 2.345: deren Binaerdarstellung liegt
				// knapp unter/ueber der Mitte, das Runden waere ein Muenzwurf.
				punkte: [[1.23456, 2.346], [10.006, 3.999]] },
			{ id: 'e2', typ: 'text', farbe: '#000', breite: 3, text: 'a"b\\c', x: 1.111, y: 2.226 },
			{ id: 'e3', typ: 'bild', daten: BILD_DATEN, x: 0.004, y: -0.004, b: 100.456, h: 50 }
		]
	};
	const vorher = kopie(m);
	const json = E.serialisiere(m);
	pruefe('9.1 serialisiere liefert Zeichenkette', typeof json === 'string');
	let o = null;
	try { o = JSON.parse(json); } catch (e) { /* bleibt null */ }
	pruefe('9.2 gueltiges JSON', o !== null);
	gleich('9.3 Strichpunkte auf 2 Nachkommastellen', o && o.elemente[0].punkte, [[1.23, 2.35], [10.01, 4]]);
	gleich('9.4 Textanker gerundet', o && [o.elemente[1].x, o.elemente[1].y], [1.11, 2.23]);
	gleich('9.5 Bildrahmen gerundet (ohne -0)', o && [o.elemente[2].x, o.elemente[2].y, o.elemente[2].b], [0, 0, 100.46]);
	pruefe('9.6 kein "-0" im JSON', json.indexOf('-0,') === -1 && json.indexOf('-0]') === -1);
	gleich('9.7 Text mit Sonderzeichen unveraendert', o && o.elemente[1].text, 'a"b\\c');
	gleich('9.8 Eingabe unveraendert', m, vorher);

	const d = E.deserialisiere(json);
	pruefe('9.9 deserialisiere liefert {modell, verworfen}', d && d.modell && d.verworfen === 0);
	gleich('9.10 Rundreise', d && d.modell, o);
	gleich('9.11 kaputtes JSON -> null', E.deserialisiere('{"v":1,'), null);
	gleich('9.12 leere Zeichenkette -> null', E.deserialisiere(''), null);
	gleich('9.13 null -> null', E.deserialisiere(null), null);
	gleich('9.14 JSON mit v=2 -> null', E.deserialisiere('{"v":2,"breite":1,"hoehe":1,"elemente":[]}'), null);
	const mitKreis = E.deserialisiere('{"v":1,"breite":10,"hoehe":10,"elemente":[{"id":"e1","typ":"kreis"}]}');
	gleich('9.15 deserialisiere prueft (unbekannter typ verworfen)', mitKreis && [mitKreis.modell.elemente.length, mitKreis.verworfen], [0, 1]);
	const original = kopie(MODELL);
	gleich('9.16 Rundreise des Plan-Beispiels', E.deserialisiere(E.serialisiere(original)).modell, MODELL);
})();

// =========================================================================
// Gruppe 10: Fingerabdruck des PNG neben dem Modell (AP-4.fix2, Befund F2)
// =========================================================================
// Eine aeltere Plugin-Version (Rueckweg) ueberschreibt das PNG, laesst den
// Begleitschluessel -elemente aber stehen. Damit die alte Liste das neuere
// PNG nicht ueberdeckt, liegt die Liste in einer Huelle
//   {"png":"<abdruck des PNG>","modell":<serialisiertes Modell v1>}
// Das Modell selbst bleibt unveraendert (Elementmodell v1).
console.log('\nGruppe 10: Fingerabdruck (abdruck, verpacke, entpacke)');
(function () {
	['abdruck', 'verpacke', 'entpacke'].forEach(function (n) {
		pruefe('10.0 Schnittstelle: ' + n + ' ist Funktion', typeof E[n] === 'function');
	});
	if (typeof E.abdruck !== 'function' || typeof E.verpacke !== 'function' || typeof E.entpacke !== 'function') {
		return;
	}

	// FNV-1a 32 Bit (bekannte Pruefwerte) mit vorangestellter Laenge
	gleich('10.1 abdruck("") = "0-811c9dc5"', E.abdruck(''), '0-811c9dc5');
	gleich('10.2 abdruck("a") = "1-e40c292c"', E.abdruck('a'), '1-e40c292c');
	gleich('10.3 abdruck("foobar") = "6-bf9cf968"', E.abdruck('foobar'), '6-bf9cf968');
	pruefe('10.4 abdruck(PNG) hat die Form <Laenge>-<8 Hex>', /^[0-9]+-[0-9a-f]{8}$/.test(E.abdruck(BILD_DATEN)), E.abdruck(BILD_DATEN));
	const anders = BILD_DATEN.slice(0, 40) + (BILD_DATEN.charAt(40) === 'A' ? 'B' : 'A') + BILD_DATEN.slice(41);
	pruefe('10.5 ein geaendertes Zeichen gleicher Laenge -> anderer Abdruck', E.abdruck(anders) !== E.abdruck(BILD_DATEN));
	gleich('10.6 abdruck ist deterministisch', E.abdruck(BILD_DATEN), E.abdruck(String(BILD_DATEN)));
	// Lange Zeichenkette (ein PNG von mehreren MB): Laenge stimmt, Hash 8 Hex
	let lang = '';
	for (let i = 0; i < 2000; i++) lang += BILD_DATEN;
	pruefe('10.7 langer Text: Laenge vorn korrekt', E.abdruck(lang).split('-')[0] === String(lang.length), E.abdruck(lang));
	gleich('10.8 Nicht-Zeichenkette -> null', E.abdruck(null), null);

	const modellText = E.serialisiere(MODELL);
	const huelle = E.verpacke(BILD_DATEN, modellText);
	gleich('10.9 verpacke: genaue Form {"png":"<abdruck>","modell":<text>}', huelle,
		'{"png":"' + E.abdruck(BILD_DATEN) + '","modell":' + modellText + '}');
	let h = null;
	try { h = JSON.parse(huelle); } catch (e) { /* bleibt null */ }
	pruefe('10.10 Huelle ist gueltiges JSON', h !== null && h.png === E.abdruck(BILD_DATEN));
	gleich('10.11 Modell in der Huelle unveraendert (Elementmodell v1)', h && h.modell, JSON.parse(modellText));

	gleich('10.12 entpacke mit passendem PNG -> Modelltext', E.entpacke(huelle, BILD_DATEN), modellText);
	gleich('10.13 entpacke mit anderem PNG (Rueckweg) -> null', E.entpacke(huelle, anders), null);
	gleich('10.14 entpacke ohne Huelle (Stand aus Phase-4-Tests) -> null', E.entpacke(modellText, BILD_DATEN), null);
	gleich('10.15 entpacke(null) -> null', E.entpacke(null, BILD_DATEN), null);
	gleich('10.16 entpacke ohne PNG -> null', E.entpacke(huelle, null), null);
	gleich('10.17 Huelle abgeschnitten -> null', E.entpacke(huelle.slice(0, -1), BILD_DATEN), null);
	gleich('10.18 fremder Text -> null', E.entpacke('alter Stand', BILD_DATEN), null);
	gleich('10.19 verpacke ohne Modelltext -> ""', E.verpacke(BILD_DATEN, ''), '');
	gleich('10.20 verpacke ohne PNG -> ""', E.verpacke('', modellText), '');
	// Rundreise ueber deserialisiere
	const rund = E.deserialisiere(E.entpacke(huelle, BILD_DATEN));
	gleich('10.21 Rundreise verpacke -> entpacke -> deserialisiere', rund && rund.modell, JSON.parse(modellText));
})();

// =========================================================================
// Gruppe 11: Drehen, Kopieren, Feld winkel (Lasso-Drehen 3.1.143)
// =========================================================================
console.log('\nGruppe 11: drehe / kopiere / drehwinkel / winkel');
(function () {
	const nah = (a, b) => Math.abs(a - b) < 1e-9;
	const punkteNah = (ist, soll) => ist.length === soll.length &&
		ist.every((p, i) => nah(p[0], soll[i][0]) && nah(p[1], soll[i][1]));

	['drehe', 'kopiere', 'drehwinkel'].forEach(function (n) {
		pruefe('11.0 Schnittstelle: ' + n + ' ist Funktion', typeof E[n] === 'function');
	});

	// drehwinkel: Bildschirmsinn (y nach unten), positiv = im Uhrzeigersinn
	pruefe('11.1 drehwinkel rechts -> unten = +90', nah(E.drehwinkel(0, 0, 10, 0, 0, 10), 90));
	pruefe('11.2 drehwinkel rechts -> oben = -90', nah(E.drehwinkel(0, 0, 10, 0, 0, -10), -90));
	pruefe('11.3 drehwinkel gegenueber = 180 (nie -180)', nah(E.drehwinkel(0, 0, 10, 0, -10, 0), 180));
	pruefe('11.4 drehwinkel ueber die -x-Achse: 170 -> -170 ergibt +20',
		nah(E.drehwinkel(0, 0, Math.cos(170 * Math.PI / 180), Math.sin(170 * Math.PI / 180),
			Math.cos(-170 * Math.PI / 180), Math.sin(-170 * Math.PI / 180)), 20));
	gleich('11.5 drehwinkel mit Punkt im Drehpunkt -> 0', E.drehwinkel(5, 5, 5, 5, 9, 9), 0);

	// drehe: Strich um (100, 100) um 90 Grad
	const m = {
		v: 1, breite: 400, hoehe: 300, elemente: [
			{ id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#000', breite: 3, punkte: [[110, 100], [120, 100]] },
			{ id: 'e2', typ: 'text', farbe: '#000', breite: 1, text: 'ab', x: 110, y: 100 },
			{ id: 'e3', typ: 'bild', daten: BILD_DATEN, x: 100, y: 120, b: 40, h: 20 },
			{ id: 'e4', typ: 'strich', werkzeug: 'pen', farbe: '#000', breite: 3, punkte: [[0, 0], [5, 5]] }
		]
	};
	const vorher = kopie(m);
	const d = E.drehe(m, ['e1', 'e2', 'e3'], 100, 100, 90);
	gleich('11.6 drehe veraendert die Eingabe nicht', m, vorher);
	pruefe('11.7 Strichpunkte um 90 Grad gedreht', punkteNah(d.elemente[0].punkte, [[100, 110], [100, 120]]),
		JSON.stringify(d.elemente[0].punkte));
	pruefe('11.8 Text: Ecke gedreht, winkel 90', nah(d.elemente[1].x, 100) && nah(d.elemente[1].y, 110) && d.elemente[1].winkel === 90,
		JSON.stringify(d.elemente[1]));
	pruefe('11.9 Bild: Ecke gedreht, winkel 90, b/h unveraendert',
		nah(d.elemente[2].x, 80) && nah(d.elemente[2].y, 100) && d.elemente[2].winkel === 90 && d.elemente[2].b === 40 && d.elemente[2].h === 20,
		JSON.stringify(d.elemente[2]));
	pruefe('11.10 nicht gewaehltes Element bleibt dieselbe Referenz', d.elemente[3] === m.elemente[3]);
	gleich('11.11 IDs und Reihenfolge bleiben', d.elemente.map(e => e.id), ['e1', 'e2', 'e3', 'e4']);

	// Rahmen des gedrehten Bilds: 40x20 um (80,100) um 90 Grad -> x 60..80, y 100..140
	const rb = E.rahmen(d.elemente[2]);
	pruefe('11.12 rahmen eines gedrehten Bilds ist das umschliessende Rechteck',
		nah(rb.x, 60) && nah(rb.y, 100) && nah(rb.b, 20) && nah(rb.h, 40), JSON.stringify(rb));
	const rt = E.rahmen(d.elemente[1], mass);
	pruefe('11.13 rahmen eines gedrehten Texts (90 Grad): Breite und Hoehe getauscht',
		nah(rt.b, 1.2 * E.schriftPx(1)) && nah(rt.h, mass('ab', E.schriftPx(1))), JSON.stringify(rt));

	// Zusammensetzen: 4 x 90 = Ausgangslage, winkel faellt weg
	let z = m;
	for (let i = 0; i < 4; i++) z = E.drehe(z, ['e1', 'e2', 'e3'], 100, 100, 90);
	pruefe('11.14 viermal 90 Grad -> Punkte wieder am Ausgang', punkteNah(z.elemente[0].punkte, m.elemente[0].punkte));
	pruefe('11.15 viermal 90 Grad -> Text/Bild ohne Feld winkel',
		!('winkel' in z.elemente[1]) && !('winkel' in z.elemente[2]), JSON.stringify(z.elemente.slice(1, 3)));
	pruefe('11.16 viermal 90 Grad -> Bildecke wieder am Ausgang', nah(z.elemente[2].x, 100) && nah(z.elemente[2].y, 120));
	const z2 = E.drehe(E.drehe(m, ['e3'], 100, 100, 170), ['e3'], 100, 100, 20);
	gleich('11.17 170 + 20 Grad -> winkel -170 (normiert)', z2.elemente[2].winkel, -170);
	gleich('11.18 drehe mit 0 Grad -> gleiche Elemente', E.drehe(m, ['e1'], 0, 0, 0).elemente[0] === m.elemente[0], true);
	gleich('11.19 drehe mit 360 Grad -> gleiche Elemente', E.drehe(m, ['e1'], 0, 0, 360).elemente[0] === m.elemente[0], true);

	// Drehen um den Mittelpunkt eines Bilds: Mittelpunkt bleibt
	const mb = E.drehe(m, ['e3'], 120, 130, 37);
	const r37 = E.rahmen(mb.elemente[2]);
	pruefe('11.20 Drehen um die Bildmitte haelt die Mitte des Rahmens fest',
		nah(r37.x + r37.b / 2, 120) && nah(r37.y + r37.h / 2, 130), JSON.stringify(r37));

	// verschiebe behaelt winkel
	const v = E.verschiebe(d, ['e2', 'e3'], 5, -5);
	pruefe('11.21 verschiebe behaelt winkel', v.elemente[1].winkel === 90 && v.elemente[2].winkel === 90);

	// pruefeModell: winkel optional, keine Zahl -> Element ungueltig, 0 faellt weg
	const p = E.pruefeModell({ v: 1, breite: 10, hoehe: 10, elemente: [
		{ id: 'e1', typ: 'text', farbe: '#000', breite: 1, text: 'a', x: 1, y: 1, winkel: 45 },
		{ id: 'e2', typ: 'bild', daten: BILD_DATEN, x: 0, y: 0, b: 1, h: 1, winkel: -30.5 },
		{ id: 'e3', typ: 'text', farbe: '#000', breite: 1, text: 'a', x: 1, y: 1, winkel: 'schief' },
		{ id: 'e4', typ: 'bild', daten: BILD_DATEN, x: 0, y: 0, b: 1, h: 1, winkel: null },
		{ id: 'e5', typ: 'text', farbe: '#000', breite: 1, text: 'a', x: 1, y: 1, winkel: 0 },
		{ id: 'e6', typ: 'text', farbe: '#000', breite: 1, text: 'a', x: 1, y: 1, winkel: 270 }
	] });
	gleich('11.22 pruefeModell: winkel uebernommen', p && p.modell.elemente.map(e => e.winkel), [45, -30.5, undefined, -90]);
	gleich('11.23 pruefeModell: winkel keine Zahl / null -> verworfen', p && p.verworfen, 2);
	pruefe('11.24 pruefeModell: winkel 0 -> kein Feld', p && !('winkel' in p.modell.elemente[2]));

	// serialisiere: winkel gerundet, 0 entfaellt; Rundreise
	const s = JSON.parse(E.serialisiere({ v: 1, breite: 10, hoehe: 10, elemente: [
		{ id: 'e1', typ: 'text', farbe: '#000', breite: 1, text: 'a', x: 1, y: 1, winkel: 12.3456 },
		{ id: 'e2', typ: 'bild', daten: BILD_DATEN, x: 0, y: 0, b: 1, h: 1, winkel: 0.001 },
		{ id: 'e3', typ: 'text', farbe: '#000', breite: 1, text: 'a', x: 1, y: 1 }
	] }));
	gleich('11.25 serialisiere: winkel auf 2 Stellen', s.elemente[0].winkel, 12.35);
	pruefe('11.26 serialisiere: auf 0 gerundeter winkel entfaellt', !('winkel' in s.elemente[1]));
	gleich('11.27 serialisiere: unverdrehter Text wie bisher (keine neuen Felder)', Object.keys(s.elemente[2]),
		['id', 'typ', 'farbe', 'breite', 'text', 'x', 'y']);
	const rr = E.deserialisiere(E.serialisiere(d));
	gleich('11.28 Rundreise serialisiere -> deserialisiere behaelt winkel', rr && rr.modell.elemente.map(e => e.winkel),
		[undefined, 90, 90, undefined]);

	// skaliere behaelt winkel
	gleich('11.29 skaliere behaelt winkel', E.skaliere(d, 800, 600).elemente[2].winkel, 90);

	// kopiere
	const k = E.kopiere(d, ['e3', 'e1'], 20, 20);
	gleich('11.30 kopiere: Originale unveraendert und zuerst', k.modell.elemente.slice(0, 4).every((e, i) => e === d.elemente[i]), true);
	gleich('11.31 kopiere: Kopien in Listenreihenfolge ans Ende, neue IDs', k.ids, ['e5', 'e6']);
	gleich('11.32 kopiere: IDs der Kopien in der Liste', k.modell.elemente.slice(4).map(e => e.id), ['e5', 'e6']);
	pruefe('11.33 kopiere: Strich versetzt', punkteNah(k.modell.elemente[4].punkte,
		d.elemente[0].punkte.map(q => [q[0] + 20, q[1] + 20])));
	pruefe('11.34 kopiere: Bild versetzt, winkel und daten mitgenommen',
		nah(k.modell.elemente[5].x, d.elemente[2].x + 20) && k.modell.elemente[5].winkel === 90 && k.modell.elemente[5].daten === BILD_DATEN);
	pruefe('11.35 kopiere: Kopie ist ein neues Objekt (keine geteilten Punkte)',
		k.modell.elemente[4].punkte !== d.elemente[0].punkte && k.modell.elemente[4].punkte[0] !== d.elemente[0].punkte[0]);
	let zaehler = 41;
	const k2 = E.kopiere(d, ['e2'], 0, 0, () => 'e' + (zaehler++));
	gleich('11.36 kopiere: neueId wird benutzt', k2.ids, ['e41']);
	const k3 = E.kopiere(d, ['e99'], 0, 0);
	gleich('11.37 kopiere ohne passende ID: nichts angehaengt', [k3.ids, k3.modell.elemente.length], [[], 4]);
	const geprueft = E.pruefeModell(k.modell);
	gleich('11.38 kopiertes Modell besteht pruefeModell ohne Verwerfen', geprueft && geprueft.verworfen, 0);
})();

// =========================================================================
console.log('\nGruppe 12: textGroesse / textGroessen / naechsteSchrift (3.1.144)');
(function () {
	['naechsteSchrift', 'textGroesse', 'textGroessen'].forEach(function (n) {
		pruefe('12.0 Schnittstelle: ' + n + ' ist Funktion', typeof E[n] === 'function');
	});
	gleich('12.1 Grenzen TEXT_PX_MIN/MAX', [E.TEXT_PX_MIN, E.TEXT_PX_MAX], [18, 200]);

	// naechsteSchrift: Faktor 1,2, ganzzahlig, Grenzen
	gleich('12.2 groesser: 28 -> 34 (28 * 1,2 = 33,6)', E.naechsteSchrift(28, 1), 34);
	gleich('12.3 kleiner: 28 -> 23 (28 / 1,2 = 23,3)', E.naechsteSchrift(28, -1), 23);
	gleich('12.4 groesser endet bei 200', [E.naechsteSchrift(180, 1), E.naechsteSchrift(200, 1)], [200, 200]);
	gleich('12.5 kleiner endet bei 18', [E.naechsteSchrift(20, -1), E.naechsteSchrift(18, -1)], [18, 18]);
	gleich('12.6 jenseits der Grenze bleibt die Schrift in dieser Richtung',
		[E.naechsteSchrift(16, -1), E.naechsteSchrift(250, 1)], [16, 250]);
	gleich('12.7 ... und kommt in der Gegenrichtung zurueck',
		[E.naechsteSchrift(16, 1), E.naechsteSchrift(250, -1)], [19, 208]);
	gleich('12.8 richtung 0 -> unveraendert', E.naechsteSchrift(28, 0), 28);
	// jeder Schritt aendert um mindestens 1 px: von 18 bis 200 in endlich vielen Schritten
	let s = 18, schritte = 0;
	while (s < 200 && schritte < 100) { const n = E.naechsteSchrift(s, 1); if (n <= s) break; s = n; schritte++; }
	gleich('12.9 von 18 bis 200 streng wachsend, < 20 Schritte', [s, schritte < 20], [200, true]);
	s = 200; schritte = 0;
	while (s > 18 && schritte < 100) { const n = E.naechsteSchrift(s, -1); if (n >= s) break; s = n; schritte++; }
	gleich('12.10 von 200 bis 18 streng fallend, < 20 Schritte', [s, schritte < 20], [18, true]);

	// textGroesse
	const m = {
		v: 1, breite: 400, hoehe: 300, elemente: [
			{ id: 'e1', typ: 'text', farbe: '#000', breite: 3, text: 'Hallo', x: 10, y: 20 },
			{ id: 'e2', typ: 'strich', werkzeug: 'pen', farbe: '#000', breite: 3, punkte: [[0, 0], [10, 0]] },
			{ id: 'e3', typ: 'text', farbe: '#c00', breite: 1, text: 'klein', x: 50, y: 60, winkel: 30 },
			{ id: 'e4', typ: 'text', farbe: '#000', breite: 3, text: 'nicht gewaehlt', x: 0, y: 0 },
			{ id: 'e5', typ: 'bild', daten: BILD_DATEN, x: 0, y: 0, b: 10, h: 10 }
		]
	};
	const vorher = kopie(m);
	const g = E.textGroesse(m, ['e1', 'e2', 'e3', 'e5'], 1);
	gleich('12.11 groesser: zwei Texte geaendert', g.geaendert, 2);
	gleich('12.12 Text 28 px -> 34 px (breite 4,5)', [g.modell.elemente[0].breite, E.schriftPx(g.modell.elemente[0].breite)], [4.5, 34]);
	gleich('12.13 Text 20 px -> 24 px (eigener Faktor je Text)', E.schriftPx(g.modell.elemente[2].breite), 24);
	gleich('12.14 Ecke, Farbe, Text, ID bleiben', [g.modell.elemente[0].x, g.modell.elemente[0].y,
		g.modell.elemente[0].farbe, g.modell.elemente[0].text, g.modell.elemente[0].id], [10, 20, '#000', 'Hallo', 'e1']);
	gleich('12.15 winkel bleibt', g.modell.elemente[2].winkel, 30);
	pruefe('12.16 Strich, Bild und nicht gewaehlter Text bleiben dieselben Objekte',
		g.modell.elemente[1] === m.elemente[1] && g.modell.elemente[4] === m.elemente[4] && g.modell.elemente[3] === m.elemente[3]);
	pruefe('12.17 geaenderter Text ist ein neues Objekt', g.modell.elemente[0] !== m.elemente[0]);
	gleich('12.18 Eingabe unveraendert', m, vorher);
	gleich('12.19 unverdrehter Text bekommt kein winkel-Feld', Object.keys(g.modell.elemente[0]),
		['id', 'typ', 'farbe', 'breite', 'text', 'x', 'y']);
	const k = E.textGroesse(m, ['e1'], -1);
	gleich('12.20 kleiner: 28 px -> 23 px (breite 1,75)', [k.geaendert, k.modell.elemente[0].breite], [1, 1.75]);

	// Grenzen: nichts zu tun -> geaendert 0, alle Elemente dieselben Objekte
	const grenze = { v: 1, breite: 10, hoehe: 10, elemente: [
		{ id: 'e1', typ: 'text', farbe: '#000', breite: 46, text: 'gross', x: 0, y: 0 },
		{ id: 'e2', typ: 'text', farbe: '#000', breite: 0.5, text: 'klein', x: 0, y: 0 }
	] };
	const oben = E.textGroesse(grenze, ['e1'], 1), unten = E.textGroesse(grenze, ['e2'], -1);
	gleich('12.21 an den Grenzen (200 / 18 px): geaendert 0', [oben.geaendert, unten.geaendert], [0, 0]);
	pruefe('12.22 ... Elemente unveraendert dieselben Objekte',
		oben.modell.elemente.every((e, i) => e === grenze.elemente[i]) && unten.modell.elemente.every((e, i) => e === grenze.elemente[i]));
	gleich('12.23 nur Striche gewaehlt: geaendert 0', E.textGroesse(m, ['e2', 'e5'], 1).geaendert, 0);
	gleich('12.24 richtung keine Zahl: geaendert 0', E.textGroesse(m, ['e1'], 'gross').geaendert, 0);
	// zusammen gewaehlt: der eine stoesst an, der andere waechst weiter
	const gemischt = E.textGroesse(grenze, ['e1', 'e2'], 1);
	gleich('12.25 gemischt an der Grenze: nur der kleinere waechst',
		[gemischt.geaendert, E.schriftPx(gemischt.modell.elemente[0].breite), E.schriftPx(gemischt.modell.elemente[1].breite)],
		[1, 200, 22]);

	// Speichern: breite bleibt exakt (Vielfache von 0,25), Modell gueltig
	const r = E.deserialisiere(E.serialisiere(g.modell));
	gleich('12.26 Rundreise serialisiere -> deserialisiere behaelt die Schrift',
		r && r.modell.elemente.filter(e => e.typ === 'text').map(e => E.schriftPx(e.breite)), [34, 24, 28]);
	gleich('12.27 Ergebnis besteht pruefeModell ohne Verwerfen', r && r.verworfen, 0);
	pruefe('12.28 Rahmen waechst mit der Schrift',
		E.rahmen(g.modell.elemente[0]).h > E.rahmen(m.elemente[0]).h && E.rahmen(g.modell.elemente[0]).b > E.rahmen(m.elemente[0]).b);

	// textGroessen
	gleich('12.29 textGroessen: gemischte Auswahl', E.textGroessen(m, ['e1', 'e2', 'e3']), { anzahl: 2, min: 20, max: 28 });
	gleich('12.30 textGroessen: ohne Texte', E.textGroessen(m, ['e2', 'e5']), { anzahl: 0, min: null, max: null });
	gleich('12.31 textGroessen: leere Auswahl', E.textGroessen(m, []), { anzahl: 0, min: null, max: null });
})();

ende();
