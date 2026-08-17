#!/usr/bin/env node
/**
 * Standalone-Harnisch fuer den gemeinsamen Auswahlbaustein (AP-3.2, Vertrag C)
 * — ohne WordPress, ohne jsdom, ohne npm-Abhaengigkeit.
 *
 * Verfahren: `assets/js/block-auswahl.js` wird als Text eingelesen und ueber
 * `new Function('window', quelle)` mit einem Stub-`window` ausgefuehrt. Damit
 * laesst sich die reine Logik pruefen, ohne dass irgendein `wp.*`-Global
 * existiert — genau das ist AK9: Die Datei darf `wp.element` beim LADEN nicht
 * beruehren, sondern erst innerhalb der Komponentenfunktion.
 *
 * NICHT geprueft wird die Komponente `HierarchieAuswahl` ueber ihren
 * Wachposten hinaus (fehlt `wp`, liefert sie `null`). Ohne React-Umgebung ist
 * mehr nicht sinnvoll moeglich; ihre Pruefung erfolgt in AP-4.3 an der
 * Oberflaeche.
 *
 * Aufruf:  node tools/test-block-auswahl.js
 *
 * @package ContainerBlockDesigner
 */

'use strict';

const fs = require('fs');
const path = require('path');

const QUELLDATEI = path.join(__dirname, '..', 'assets', 'js', 'block-auswahl.js');

/** Die sieben Namen aus Vertrag C — nicht mehr, nicht weniger (AK1). */
const VERTRAG_C = [
	'HierarchieAuswahl',
	'ebenen',
	'ladeDaten',
	'passtZurSuche',
	'pfadVon',
	'schluessel',
	'text'
];

let fehlschlaege = 0;

function zeige(wert) {
	if (wert instanceof Error) {
		return wert.message;
	}
	try {
		return JSON.stringify(wert);
	} catch (fehler) {
		return String(wert);
	}
}

function check(bezeichnung, bedingung, ist) {
	if (bedingung) {
		console.log('  OK   ' + bezeichnung);
		return;
	}
	fehlschlaege++;
	console.log('  FAIL ' + bezeichnung + (undefined !== ist ? ' -> ' + zeige(ist) : ''));
}

async function gruppe(name, ausfuehren) {
	console.log('\n== ' + name + ' ==');
	try {
		await ausfuehren();
	} catch (fehler) {
		check(name + ': keine Ausnahme', false, fehler);
	}
}

// --- Laden ----------------------------------------------------------------

function quelleLesen() {
	if (!fs.existsSync(QUELLDATEI)) {
		throw new Error('Datei fehlt: ' + QUELLDATEI);
	}
	return fs.readFileSync(QUELLDATEI, 'utf8');
}

/**
 * Fuehrt die Quelldatei mit einem Stub-`window` aus.
 *
 * @param {Object} zusatz Werte, die vor dem Ausfuehren am Stub liegen sollen.
 * @returns {Object} der Stub nach dem Ausfuehren
 */
function ladeModul(zusatz) {
	const quelle = quelleLesen();
	const fenster = {};

	if (zusatz) {
		Object.keys(zusatz).forEach(function (name) {
			fenster[name] = zusatz[name];
		});
	}

	new Function('window', quelle)(fenster);

	if (!fenster.cbdBlockAuswahl) {
		throw new Error('window.cbdBlockAuswahl wurde nicht gesetzt');
	}

	return fenster;
}

/** Frisches Modul (eigener Zustand, eigene Memoisierung) je Aufruf. */
function frischesModul(zusatz) {
	return ladeModul(zusatz).cbdBlockAuswahl;
}

function werte(optionen) {
	return (optionen || []).map(function (option) { return option.value; });
}

function stufeMit(stufen, tiefe) {
	for (let i = 0; i < stufen.length; i++) {
		if (stufen[i].tiefe === tiefe) {
			return stufen[i];
		}
	}
	return null;
}

/** console.error stummschalten — die Fehlerpfade loggen absichtlich. */
async function ohneFehlerausgabe(ausfuehren) {
	const original = console.error;
	console.error = function () {};
	try {
		return await ausfuehren();
	} finally {
		console.error = original;
	}
}

// --- Testdaten ------------------------------------------------------------
//
//   12  4. Klasse                (Wurzel)
//   +-- 34  ACH
//       +-- 45  IR-Spektroskopie      gesperrt, hat Block cbd-a
//           +-- 46  Auswertung        hat Block cbd-b
//   20  5. Klasse                (Wurzel, KEIN Zielblock im ganzen Zweig)
//   +-- 21  BCH
//
//   77  Ein Beitrag              (postType 'post', steht nicht im Baum)

function baum() {
	return {
		knoten: {
			12: { id: 12, parent: 0, titel: '4. Klasse', menuOrder: 0, tiefe: 0, typ: 'page', gesperrt: false },
			34: { id: 34, parent: 12, titel: 'ACH', menuOrder: 0, tiefe: 1, typ: 'page', gesperrt: false },
			45: { id: 45, parent: 34, titel: 'IR-Spektroskopie', menuOrder: 0, tiefe: 2, typ: 'page', gesperrt: true },
			46: { id: 46, parent: 45, titel: 'Auswertung', menuOrder: 0, tiefe: 3, typ: 'page', gesperrt: false },
			20: { id: 20, parent: 0, titel: '5. Klasse', menuOrder: 1, tiefe: 0, typ: 'page', gesperrt: false },
			21: { id: 21, parent: 20, titel: 'BCH', menuOrder: 0, tiefe: 1, typ: 'page', gesperrt: false }
		},
		kinder: { 0: [12, 20], 12: [34], 34: [45], 45: [46], 20: [21] },
		wurzeln: [12, 20]
	};
}

function bloecke() {
	return [
		{
			stableId: 'cbd-a', anchor: '', blockId: '',
			blockTitle: 'Grundlagen der IR-Spektroskopie',
			postId: 45, postTitle: 'IR-Spektroskopie', postUrl: 'http://x/ir/',
			blockType: 'container-block-designer/basic-container',
			postParent: 34, menuOrder: 0, postType: 'page'
		},
		{
			stableId: 'cbd-b', anchor: 'anker-1', blockId: '',
			blockTitle: 'Auswertung der Spektren',
			postId: 46, postTitle: 'Auswertung', postUrl: 'http://x/auswertung/',
			blockType: 'container-block-designer/basic-container',
			postParent: 45, menuOrder: 0, postType: 'page'
		},
		{
			stableId: 'cbd-c', anchor: '', blockId: '',
			blockTitle: 'Beitragsblock',
			postId: 77, postTitle: 'Ein Beitrag', postUrl: 'http://x/beitrag/',
			blockType: 'container-block-designer/basic-container',
			postParent: 0, menuOrder: 0, postType: 'post'
		}
	];
}

function apiStub(antworten) {
	const pfade = [];
	return {
		pfade: pfade,
		apiFetch: function (argumente) {
			const pfad = argumente && argumente.path;
			pfade.push(pfad);
			const antwort = antworten[pfad];
			if (antwort instanceof Error) {
				return Promise.reject(antwort);
			}
			if (undefined === antwort) {
				return Promise.reject(new Error('unbekannter Pfad: ' + pfad));
			}
			return Promise.resolve(antwort);
		}
	};
}

// --------------------------------------------------------------------------

async function main() {
	console.log('Prueft: ' + QUELLDATEI);

	await gruppe('Hausstil der Datei (AK8, Regel 17/19)', function () {
		const quelle = quelleLesen();

		check('Datei existiert und ist nicht leer', quelle.length > 0, quelle.length);
		check('kein import-Statement', !/^\s*import\s/m.test(quelle));
		check('kein export-Statement', !/^\s*export\s/m.test(quelle));
		check('keine class-Deklaration (also auch keine Klassenfelder)', !/^\s*class\s+\w/m.test(quelle));
		check('kein ungegatetes console.log', -1 === quelle.indexOf('console.log'), quelle.indexOf('console.log'));
		check('IIFE-Hausstil mit window-Parameter', /\(function\s*\(\s*window\s*\)/.test(quelle));
	});

	await gruppe('Laden ohne WordPress (AK9) und oeffentliche Namen (AK1)', function () {
		const quelle = quelleLesen();
		const fenster = {};
		let wpBeruehrt = false;

		// Ein nicht aufzaehlbarer Getter faengt JEDEN Lesezugriff auf window.wp
		// waehrend des Ladens ab — der schaerfste Nachweis fuer AK9.
		Object.defineProperty(fenster, 'wp', {
			configurable: true,
			enumerable: false,
			get: function () { wpBeruehrt = true; return undefined; }
		});

		new Function('window', quelle)(fenster);

		check('Laden wirft nicht ohne wp', true);
		check('window.wp wird beim Laden NICHT gelesen (AK9)', !wpBeruehrt);
		check('window.cbdBlockAuswahl ist gesetzt', !!fenster.cbdBlockAuswahl);
		check(
			'window bekommt genau einen neuen Namen',
			1 === Object.keys(fenster).length && 'cbdBlockAuswahl' === Object.keys(fenster)[0],
			Object.keys(fenster)
		);

		const namen = Object.keys(fenster.cbdBlockAuswahl || {}).sort();
		check('genau die sieben Namen aus Vertrag C', VERTRAG_C.join(',') === namen.join(','), namen);

		VERTRAG_C.forEach(function (name) {
			check(name + '() ist eine Funktion', 'function' === typeof (fenster.cbdBlockAuswahl || {})[name]);
		});
	});

	await gruppe('schluessel()', function () {
		const modul = frischesModul();

		check('normaler Eintrag', '45|cbd-a' === modul.schluessel(bloecke()[0]), modul.schluessel(bloecke()[0]));
		check('postId als Zeichenkette', '45|cbd-a' === modul.schluessel({ postId: '45', stableId: 'cbd-a' }));
		check('ohne stableId -> leer', '' === modul.schluessel({ postId: 45 }));
		check('null -> leer', '' === modul.schluessel(null));
		check('undefined -> leer', '' === modul.schluessel(undefined));
		check('ohne postId -> 0 als Praefix', '0|cbd-a' === modul.schluessel({ stableId: 'cbd-a' }), modul.schluessel({ stableId: 'cbd-a' }));
	});

	await gruppe('text()', function () {
		const modul = frischesModul();

		check('null -> leer', '' === modul.text(null));
		check('undefined -> leer', '' === modul.text(undefined));
		check('Zahl 42 -> "42"', '42' === modul.text(42));
		check('Zahl 0 -> "0"', '0' === modul.text(0));
		check('leere Zeichenkette bleibt leer', '' === modul.text(''));
		check('Zeichenkette bleibt', 'abc' === modul.text('abc'));
		check('false -> "false"', 'false' === modul.text(false));
	});

	await gruppe('passtZurSuche()', function () {
		const modul = frischesModul();
		const eintrag = bloecke()[0];

		check('leerer Begriff passt immer', true === modul.passtZurSuche(eintrag, ''));
		check('undefined-Begriff passt immer', true === modul.passtZurSuche(eintrag, undefined));
		check('Treffer im Seitentitel', true === modul.passtZurSuche(eintrag, 'spektro'));
		check('Treffer im Blocktitel', true === modul.passtZurSuche(eintrag, 'grundlagen'));
		check('Grossschreibung egal', true === modul.passtZurSuche(eintrag, 'IR-SPEKTROSKOPIE'));
		check('zwei Woerter, beide treffen', true === modul.passtZurSuche(eintrag, 'grundlagen spektro'));
		check('zwei Woerter, eines trifft nicht', false === modul.passtZurSuche(eintrag, 'grundlagen auswertung'));
		check('umgebender Whitespace stoert nicht', true === modul.passtZurSuche(eintrag, '  grundlagen   spektro '));
		check('Eintrag ohne Titel, mit Begriff -> false', false === modul.passtZurSuche({ stableId: 'x' }, 'ir'));
		check('Eintrag undefined, leerer Begriff -> true (wirft nicht)', true === modul.passtZurSuche(undefined, ''));
		check('Eintrag undefined, mit Begriff -> false (wirft nicht)', false === modul.passtZurSuche(undefined, 'ir'));
	});

	await gruppe('pfadVon()', function () {
		const modul = frischesModul();
		const b = baum();

		check('vier Ebenen, Wurzel zuerst', '12,34,45,46' === modul.pfadVon(b, 46).join(','), modul.pfadVon(b, 46));
		check('Wurzel selbst', '12' === modul.pfadVon(b, 12).join(','), modul.pfadVon(b, 12));
		check('mittlere Ebene', '12,34' === modul.pfadVon(b, 34).join(','), modul.pfadVon(b, 34));
		check('Zeichenketten-ID wird gelesen', '12,34,45' === modul.pfadVon(b, '45').join(','), modul.pfadVon(b, '45'));
		check('IDs sind Zahlen', 46 === modul.pfadVon(b, 46)[3], modul.pfadVon(b, 46));
		check('unbekannte postId -> [] (AK7)', 0 === modul.pfadVon(b, 999).length, modul.pfadVon(b, 999));
		check('postId 0 -> []', 0 === modul.pfadVon(b, 0).length);
		check('postId null -> []', 0 === modul.pfadVon(b, null).length);
		check('postId undefined -> []', 0 === modul.pfadVon(b, undefined).length);
		check('Baum null -> []', 0 === modul.pfadVon(null, 46).length);
		check('Baum ohne knoten -> []', 0 === modul.pfadVon({}, 46).length);
		check('Baum als Array -> []', 0 === modul.pfadVon([], 46).length);

		// Zyklus A -> B -> A: Die Daten kommen ueber das Netz, also nicht auf
		// Vertrag B vertrauen. Der Aufruf muss enden, nicht haengen.
		const zyklus = {
			knoten: { 5: { id: 5, parent: 6, titel: 'A' }, 6: { id: 6, parent: 5, titel: 'B' } },
			kinder: {},
			wurzeln: []
		};
		const kette = modul.pfadVon(zyklus, 5);
		check('Zyklus endet (Schleifenschutz)', Array.isArray(kette) && kette.length <= 2, kette);
	});

	await gruppe('ebenen(): Beschneidung und Kaskade', function () {
		const modul = frischesModul();
		const b = baum();
		const liste = bloecke();

		const leer = modul.ebenen(b, liste, []);
		check('ohne Pfad genau eine Stufe', 1 === leer.length, leer.length);
		check('Stufe 0 hat tiefe 0', 0 === leer[0].tiefe, leer[0].tiefe);
		check('Stufe 0 ohne Wahl -> wert leer', '' === leer[0].wert, leer[0].wert);
		check(
			'Stufe 0 zeigt Wurzel 12 und die Zusatzwurzel',
			'12,beitraege' === werte(leer[0].optionen).join(','),
			werte(leer[0].optionen)
		);
		check(
			'AK5: Zweig 20/21 ohne Zielblock erscheint nicht',
			-1 === werte(leer[0].optionen).indexOf('20'),
			werte(leer[0].optionen)
		);
		check('Titel der Wurzel', '4. Klasse' === leer[0].optionen[0].label, leer[0].optionen[0].label);
		check('Wurzel nicht gesperrt', false === leer[0].optionen[0].gesperrt, leer[0].optionen[0].gesperrt);
		check('Zusatzwurzel heisst Beiträge', 'Beiträge' === leer[0].optionen[1].label, leer[0].optionen[1].label);

		const eine = modul.ebenen(b, liste, [12]);
		check('Pfad [12]: zwei Stufen', 2 === eine.length, eine.length);
		check('Pfad [12]: Stufe 0 gewaehlt', '12' === eine[0].wert, eine[0].wert);
		check('Pfad [12]: Stufe 1 zeigt nur 34', '34' === werte(eine[1].optionen).join(','), werte(eine[1].optionen));
		check('Pfad [12]: Stufe 1 tiefe 1', 1 === eine[1].tiefe, eine[1].tiefe);
		check('Pfad [12]: Stufe 1 noch ohne Wahl', '' === eine[1].wert, eine[1].wert);
		check('Pfad [12]: KEINE Blockstufe (12 hat keine Bloecke)', null === stufeMit(eine, null), stufeMit(eine, null));

		const nichtWaehlbar = modul.ebenen(b, liste, [20]);
		check('Pfad [20] (beschnittener Zweig): nicht waehlbar', '' === nichtWaehlbar[0].wert, nichtWaehlbar[0].wert);
		check('Pfad [20]: keine Folgestufe', 1 === nichtWaehlbar.length, nichtWaehlbar.length);

		const unbekannt = modul.ebenen(b, liste, [999]);
		check('Pfad [999]: wert leer, keine Ausnahme', '' === unbekannt[0].wert, unbekannt[0].wert);
	});

	await gruppe('ebenen(): AK4 — Unterseiten UND Bloecke gleichzeitig', function () {
		const modul = frischesModul();
		const tief = modul.ebenen(baum(), bloecke(), [12, 34, 45]);

		check('vier Seitenstufen plus Blockstufe', 5 === tief.length, tief.map(function (s) { return s.tiefe; }));
		check('Stufe 2 gewaehlt: 45', '45' === stufeMit(tief, 2).wert, stufeMit(tief, 2).wert);
		check(
			'AK4: Unterseiten-Auswahl vorhanden (Stufe 3 zeigt 46)',
			null !== stufeMit(tief, 3) && '46' === werte(stufeMit(tief, 3).optionen).join(','),
			stufeMit(tief, 3) ? werte(stufeMit(tief, 3).optionen) : null
		);
		check('Stufe 3 noch ohne Wahl', '' === stufeMit(tief, 3).wert, stufeMit(tief, 3).wert);

		const blockstufe = stufeMit(tief, null);
		check('AK4: Block-Auswahl vorhanden (tiefe null)', null !== blockstufe);
		check('Blockstufe ist die letzte Stufe', tief[tief.length - 1] === blockstufe);
		check(
			'Blockstufe zeigt den Block der Seite 45',
			'45|cbd-a' === werte(blockstufe.optionen).join(','),
			werte(blockstufe.optionen)
		);
		check('Blockstufe: Beschriftung aus blockTitle', 'Grundlagen der IR-Spektroskopie' === blockstufe.optionen[0].label, blockstufe.optionen[0].label);
		check('Blockstufe: wert immer leer (kennt die Zielwahl nicht)', '' === blockstufe.wert, blockstufe.wert);
		check('Blockstufe: Eintrag mitgeliefert', blockstufe.optionen[0].eintrag && 'cbd-a' === blockstufe.optionen[0].eintrag.stableId);

		check('gesperrte Seite ist gekennzeichnet, nicht ausgeblendet', true === stufeMit(tief, 2).optionen[0].gesperrt, stufeMit(tief, 2).optionen[0]);
		check('Blockoption erbt die Sperre der Seite', true === blockstufe.optionen[0].gesperrt, blockstufe.optionen[0].gesperrt);

		const tiefer = modul.ebenen(baum(), bloecke(), [12, 34, 45, 46]);
		check('Pfad ueber vier Ebenen: Stufe 3 gewaehlt', '46' === stufeMit(tiefer, 3).wert, stufeMit(tiefer, 3).wert);
		check(
			'Blockstufe folgt der tiefsten Wahl (cbd-b)',
			'46|cbd-b' === werte(stufeMit(tiefer, null).optionen).join(','),
			werte(stufeMit(tiefer, null).optionen)
		);
		check('keine fuenfte Seitenstufe (46 hat keine Kinder)', null === stufeMit(tiefer, 4));
	});

	await gruppe('ebenen(): AK6 — Beitraege als flache Zusatzwurzel', function () {
		const modul = frischesModul();
		const b = baum();
		const liste = bloecke();

		const ueberBeitrag = modul.ebenen(b, liste, [77]);
		check('Stufe 0 steht auf der Zusatzwurzel', 'beitraege' === ueberBeitrag[0].wert, ueberBeitrag[0].wert);
		check('Stufe 1 listet den Beitrag', '77' === werte(ueberBeitrag[1].optionen).join(','), werte(ueberBeitrag[1].optionen));
		check('Stufe 1 zeigt den Seitentitel', 'Ein Beitrag' === ueberBeitrag[1].optionen[0].label, ueberBeitrag[1].optionen[0].label);
		check('Stufe 1 gewaehlt', '77' === ueberBeitrag[1].wert, ueberBeitrag[1].wert);
		check(
			'AK6: Blockstufe erreicht den Beitragsblock',
			null !== stufeMit(ueberBeitrag, null) && '77|cbd-c' === werte(stufeMit(ueberBeitrag, null).optionen).join(','),
			stufeMit(ueberBeitrag, null) ? werte(stufeMit(ueberBeitrag, null).optionen) : null
		);

		// Die Marke allein (Nutzer hat "Beiträge" gewaehlt, aber noch keinen
		// Beitrag) — der Pfad kann das als erstes Element tragen.
		const nurMarke = modul.ebenen(b, liste, ['beitraege']);
		check('nur Marke: Stufe 0 gewaehlt', 'beitraege' === nurMarke[0].wert, nurMarke[0].wert);
		check('nur Marke: Stufe 1 ohne Wahl', '' === nurMarke[1].wert, nurMarke[1].wert);
		check('nur Marke: keine Blockstufe', null === stufeMit(nurMarke, null));

		const markeUndBeitrag = modul.ebenen(b, liste, ['beitraege', 77]);
		check('Marke + Beitrag: Stufe 1 gewaehlt', '77' === markeUndBeitrag[1].wert, markeUndBeitrag[1].wert);
		check('Marke + Beitrag: Blockstufe da', null !== stufeMit(markeUndBeitrag, null));
	});

	await gruppe('ebenen(): Randfaelle', function () {
		const modul = frischesModul();

		const ohneBaum = modul.ebenen({}, bloecke(), []);
		check('leerer Baum: eine Stufe', 1 === ohneBaum.length, ohneBaum.length);
		check(
			'leerer Baum: alle Ziele bleiben ueber die Zusatzwurzel erreichbar',
			'beitraege' === werte(ohneBaum[0].optionen).join(','),
			werte(ohneBaum[0].optionen)
		);

		const ohneBloecke = modul.ebenen(baum(), [], []);
		check('ohne Bloecke: eine Stufe', 1 === ohneBloecke.length, ohneBloecke.length);
		check('ohne Bloecke: keine Option', 0 === ohneBloecke[0].optionen.length, werte(ohneBloecke[0].optionen));

		const garnichts = modul.ebenen(null, null, null);
		check('alles null: Array mit einer leeren Stufe, keine Ausnahme', Array.isArray(garnichts) && 1 === garnichts.length, garnichts);
		check('alles null: optionen ist ein Array', Array.isArray(garnichts[0].optionen));

		const kaputt = modul.ebenen({ knoten: 'nein', kinder: 5, wurzeln: 'x' }, bloecke(), [12]);
		check('kaputter Baum: keine Ausnahme', Array.isArray(kaputt), kaputt);

		const ohneStableId = modul.ebenen(baum(), [{ postId: 45, blockTitle: 'ohne Kennung' }], []);
		check('Eintrag ohne stableId zaehlt nicht als Zielblock', 0 === ohneStableId[0].optionen.length, werte(ohneStableId[0].optionen));
	});

	await gruppe('ladeDaten(): Memoisierung (AK2)', function () {
		const stub = apiStub({
			'/cbd/v1/blocks': bloecke(),
			'/cbd/v1/seitenbaum': baum()
		});
		const modul = frischesModul({ wp: { apiFetch: stub.apiFetch } });

		const a = modul.ladeDaten();
		const b = modul.ladeDaten();
		const c = modul.ladeDaten();

		check('drei gleichzeitige Aufrufe liefern dasselbe Promise', a === b && b === c);

		return Promise.all([a, b, c]).then(function (ergebnisse) {
			check('AK2: genau zwei apiFetch-Aufrufe', 2 === stub.pfade.length, stub.pfade);
			check('AK2: /cbd/v1/blocks genau einmal', 1 === stub.pfade.filter(function (p) { return '/cbd/v1/blocks' === p; }).length, stub.pfade);
			check('AK2: /cbd/v1/seitenbaum genau einmal', 1 === stub.pfade.filter(function (p) { return '/cbd/v1/seitenbaum' === p; }).length, stub.pfade);
			check('alle drei bekommen dasselbe Ergebnis', ergebnisse[0] === ergebnisse[1] && ergebnisse[1] === ergebnisse[2]);

			const daten = ergebnisse[0];
			check('bloecke ist die Liste aus Vertrag A', Array.isArray(daten.bloecke) && 3 === daten.bloecke.length, daten.bloecke && daten.bloecke.length);
			check('baum traegt knoten', !!(daten.baum && daten.baum.knoten && daten.baum.knoten[45]));
			check('baum traegt kinder', !!(daten.baum && daten.baum.kinder));
			check('baum traegt wurzeln', !!(daten.baum && Array.isArray(daten.baum.wurzeln)));
			check('fehler ist leer', '' === daten.fehler, daten.fehler);

			// Ein vierter Aufruf nach dem Aufloesen darf nicht neu abrufen.
			const d = modul.ladeDaten();
			check('vierter Aufruf: dasselbe Promise', d === a);
			check('vierter Aufruf: keine weiteren Abrufe', 2 === stub.pfade.length, stub.pfade);
		});
	});

	await gruppe('ladeDaten(): Fehlerpfade (AK3)', function () {
		return ohneFehlerausgabe(async function () {
			const beideKaputt = apiStub({
				'/cbd/v1/blocks': new Error('Netzfehler'),
				'/cbd/v1/seitenbaum': new Error('Netzfehler')
			});
			const modulA = frischesModul({ wp: { apiFetch: beideKaputt.apiFetch } });

			let abgelehnt = false;
			const ergebnisA = await modulA.ladeDaten().catch(function () { abgelehnt = true; return null; });

			check('AK3: lehnt nicht ab', !abgelehnt);
			check('AK3: bloecke leer', !!ergebnisA && Array.isArray(ergebnisA.bloecke) && 0 === ergebnisA.bloecke.length, ergebnisA && ergebnisA.bloecke);
			check('AK3: baum leer', !!ergebnisA && ergebnisA.baum && 0 === Object.keys(ergebnisA.baum.knoten).length, ergebnisA && ergebnisA.baum);
			check('AK3: fehler gesetzt', !!ergebnisA && 'string' === typeof ergebnisA.fehler && ergebnisA.fehler.length > 0, ergebnisA && ergebnisA.fehler);

			// Nur der Baum faellt aus: Die Bloecke bleiben nutzbar.
			const baumKaputt = apiStub({
				'/cbd/v1/blocks': bloecke(),
				'/cbd/v1/seitenbaum': new Error('Netzfehler')
			});
			const modulB = frischesModul({ wp: { apiFetch: baumKaputt.apiFetch } });
			const ergebnisB = await modulB.ladeDaten();

			check('Teilausfall: Bloecke bleiben erhalten', 3 === ergebnisB.bloecke.length, ergebnisB.bloecke.length);
			check('Teilausfall: fehler gesetzt', ergebnisB.fehler.length > 0, ergebnisB.fehler);
			check('Teilausfall: Baum leer aber wohlgeformt', 0 === ergebnisB.baum.wurzeln.length && !!ergebnisB.baum.knoten, ergebnisB.baum);

			// Ohne wp.apiFetch ueberhaupt.
			const modulC = frischesModul();
			const ergebnisC = await modulC.ladeDaten();
			check('ohne wp.apiFetch: loest auf', !!ergebnisC);
			check('ohne wp.apiFetch: fehler gesetzt', !!ergebnisC && ergebnisC.fehler.length > 0, ergebnisC && ergebnisC.fehler);
			check('ohne wp.apiFetch: leere Datensaetze', 0 === ergebnisC.bloecke.length && 0 === ergebnisC.baum.wurzeln.length);

			// Antwort in falscher Form (kein Array / kein Objekt).
			const falscheForm = apiStub({
				'/cbd/v1/blocks': { nanu: true },
				'/cbd/v1/seitenbaum': 'kein Objekt'
			});
			const modulD = frischesModul({ wp: { apiFetch: falscheForm.apiFetch } });
			const ergebnisD = await modulD.ladeDaten();
			check('falsche Antwortform: bloecke leer', 0 === ergebnisD.bloecke.length, ergebnisD.bloecke);
			check('falsche Antwortform: baum wohlgeformt', !!ergebnisD.baum.knoten && Array.isArray(ergebnisD.baum.wurzeln), ergebnisD.baum);
			check('falsche Antwortform: fehler gesetzt', ergebnisD.fehler.length > 0, ergebnisD.fehler);
		});
	});

	await gruppe('HierarchieAuswahl(): nur der Wachposten', function () {
		// Bewusst NICHT weiter geprueft: Ohne React-Umgebung ist das Rendern
		// nicht sinnvoll pruefbar. Die Komponente wird in AP-4.3 an der
		// Oberflaeche abgenommen.
		const modul = frischesModul();

		check('ist eine Funktion', 'function' === typeof modul.HierarchieAuswahl);
		check('ohne wp -> null statt Ausnahme', null === modul.HierarchieAuswahl({}));
		check('ohne Props und ohne wp -> null', null === modul.HierarchieAuswahl());
	});

	console.log('\n' + (0 === fehlschlaege ? 'ALLE TESTS BESTANDEN' : fehlschlaege + ' FEHLER'));
	process.exit(0 === fehlschlaege ? 0 : 1);
}

main().catch(function (fehler) {
	console.error('Harnisch abgebrochen:', fehler);
	process.exit(1);
});
