#!/usr/bin/env node
/**
 * Standalone-Harnisch fuer die kaskadierende Elternseiten-Auswahl im
 * Seitenimporter (AP-1.fix1, behebt Review-Befund B2: der in der
 * AP-1.2-Uebergabenotiz beschriebene Testharnisch mit 25 Pruefungen wurde
 * nie versioniert abgelegt und existierte nicht im Repository).
 *
 * Verfahren identisch zum Vorbild `tools/test-block-auswahl.js`: KEIN
 * jsdom, KEIN Handbau der zu pruefenden Logik selbst. `assets/js/page-importer.js`
 * wird als Text eingelesen und WOERTLICH ueber `new Function('window',
 * 'document', quelle)` mit einem selbstgeschriebenen, minimalen DOM-Stub
 * ausgefuehrt.
 *
 * Unterschied zum Vorbild: `page-importer.js` exportiert selbst NICHTS nach
 * `window` (anders als `block-auswahl.js`, das am Dateiende
 * `window.cbdBlockAuswahl` setzt) - die Kaskadenfunktionen (`kaskadeLaden`,
 * `kaskadeFehler`, `kaskadeZeichnen`, `kaskadeEbeneBauen`,
 * `kaskadeAuswahlGeaendert`, `kaskadeSperren`) sind reine, unexportierte
 * Funktionsdeklarationen im Rumpf der Datei-IIFE. Um sie trotzdem OHNE
 * Nachbau ihrer Logik pruefbar zu machen, wird der eingelesene Quelltext an
 * EINER eindeutigen, unveraenderten Ankerzeile (`var KONF =
 * window.cbdPageImport;`) minimal um eine einzige Zeile ergaenzt, die genau
 * diese - dank Function-Hoisting bereits existierenden - Funktionsreferenzen
 * auf `window.__cbdTestHooks__` legt. Kein Funktionsrumpf wird dabei
 * angefasst, verschoben oder neu geschrieben; es ist derselbe Kunstgriff wie
 * im Vorbild ("Laden der Datei und Ausfuehren im gleichen Node-Prozess",
 * siehe docs/PLAN-Seitenimporter-Kaskaden-Zielauswahl.md, AP-1.fix1,
 * Vorgehen Schritt 2). Findet sich der Anker nicht mehr oder mehrfach,
 * bricht der Harnisch mit einer klaren Fehlermeldung ab, statt
 * stillschweigend etwas Falsches zu pruefen.
 *
 * Aufruf:  node tools/test-page-importer-kaskade.js
 *
 * @package ContainerBlockDesigner
 */

'use strict';

const fs = require('fs');
const path = require('path');

const QUELLDATEI = path.join(__dirname, '..', 'assets', 'js', 'page-importer.js');
const ANKER = 'var KONF = window.cbdPageImport;';

let fehlschlaege = 0;
let pruefungen = 0;

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
	pruefungen++;
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

// --- Minimaler DOM-Stub ------------------------------------------------------
//
// Deckt genau das ab, was die gepruefte Kaskadenlogik tatsaechlich benutzt:
// am Dokument getElementById/createElement/addEventListener, an Elementen
// appendChild/removeChild/nextSibling/firstChild/options/value/text/
// textContent/className/disabled/querySelectorAll. Keine weitere
// DOM-Funktion wird von den gepruesten Funktionen angefasst - der Rest der
// Datei (Dateiauswahl, Gruppen-Zuweisung, Importlauf) wird von diesem
// Harnisch bewusst nicht beruehrt (das ist Scope von AP-1.2, nicht dieses
// Korrektur-APs).

function neueDomUmgebung() {
	const registry = {};

	function createNode(tagName) {
		const node = {
			tagName: tagName ? String(tagName).toLowerCase() : '',
			parentNode: null,
			childNodes: [],
			className: '',
			disabled: false,
			value: '',
			textContent: '',
			_id: '',
			_listeners: {}
		};

		Object.defineProperty(node, 'id', {
			get: function () { return node._id; },
			set: function (wert) { node._id = wert; registry[wert] = node; }
		});

		// Wie im echten DOM ist .text an einer <option> nur ein anderer
		// Zugriff auf den Textinhalt - KEIN HTML-Parsing (Sicherheitspunkt
		// der geprueften Datei, siehe deren Datei-Kopf).
		Object.defineProperty(node, 'text', {
			get: function () { return node.textContent; },
			set: function (wert) { node.textContent = wert; }
		});

		Object.defineProperty(node, 'firstChild', {
			get: function () { return node.childNodes.length > 0 ? node.childNodes[0] : null; }
		});

		Object.defineProperty(node, 'nextSibling', {
			get: function () {
				if (!node.parentNode) { return null; }
				const geschwister = node.parentNode.childNodes;
				const i = geschwister.indexOf(node);
				return (i > -1 && i + 1 < geschwister.length) ? geschwister[i + 1] : null;
			}
		});

		// <select>.options: im echten DOM automatisch aus den Kind-<option>
		// gebildet - hier als einfache Ableitung aus childNodes.
		Object.defineProperty(node, 'options', {
			get: function () {
				return node.childNodes.filter(function (kind) { return 'option' === kind.tagName; });
			}
		});

		node.appendChild = function (kind) {
			kind.parentNode = node;
			node.childNodes.push(kind);
			return kind;
		};

		node.removeChild = function (kind) {
			const i = node.childNodes.indexOf(kind);
			if (i > -1) { node.childNodes.splice(i, 1); }
			kind.parentNode = null;
			return kind;
		};

		node.addEventListener = function (typ, handler) {
			node._listeners[typ] = node._listeners[typ] || [];
			node._listeners[typ].push(handler);
		};

		node.dispatch = function (typ) {
			(node._listeners[typ] || []).forEach(function (handler) { handler(); });
		};

		node.querySelectorAll = function (selektor) {
			const klasse = selektor.replace(/^\./, '');
			const treffer = [];
			(function suche(n) {
				n.childNodes.forEach(function (kind) {
					const klassen = (kind.className || '').split(/\s+/).filter(Boolean);
					if (-1 !== klassen.indexOf(klasse)) { treffer.push(kind); }
					suche(kind);
				});
			})(node);
			return treffer;
		};

		return node;
	}

	return {
		createNode: createNode,
		document: {
			getElementById: function (id) { return registry[id] || null; },
			createElement: function (tag) { return createNode(tag); },
			// No-op: In den Tests wird DOMContentLoaded nie ausgeloest, die
			// Kaskadenfunktionen werden gezielt ueber die Testhaken
			// aufgerufen (identisches Vorgehen wie im Vorbild).
			addEventListener: function () {}
		}
	};
}

// --- Laden --------------------------------------------------------------------

function quelleLesen() {
	if (!fs.existsSync(QUELLDATEI)) {
		throw new Error('Datei fehlt: ' + QUELLDATEI);
	}
	return fs.readFileSync(QUELLDATEI, 'utf8');
}

/**
 * Fuehrt die unveraenderte Kaskadenlogik aus `page-importer.js` in einer
 * frischen, isolierten Modulinstanz aus (eigener DOM-Stub, eigene
 * `seitenbaum`-Modulvariable) und liefert Testhaken darauf zurueck.
 *
 * @param {Object} [windowExtra] zusaetzliche Eigenschaften fuer den
 *   Fenster-Stub, z. B. { wp: { apiFetch: ... } }.
 * @returns {{hooks: Object, dom: Object}}
 */
function ladeModul(windowExtra) {
	const quelle = quelleLesen();
	const ersterIndex = quelle.indexOf(ANKER);
	if (-1 === ersterIndex) {
		throw new Error('Anker fuer Testhaken nicht gefunden - Quelldatei hat sich vermutlich strukturell geaendert: ' + ANKER);
	}
	if (-1 !== quelle.indexOf(ANKER, ersterIndex + ANKER.length)) {
		throw new Error('Anker kommt mehrfach vor - Einfuegestelle nicht eindeutig: ' + ANKER);
	}

	const haken = '\n    window.__cbdTestHooks__ = {\n'
		+ '        kaskadeLaden: kaskadeLaden,\n'
		+ '        kaskadeFehler: kaskadeFehler,\n'
		+ '        kaskadeZeichnen: kaskadeZeichnen,\n'
		+ '        kaskadeEbeneBauen: kaskadeEbeneBauen,\n'
		+ '        kaskadeAuswahlGeaendert: kaskadeAuswahlGeaendert,\n'
		+ '        kaskadeSperren: kaskadeSperren,\n'
		+ '        getSeitenbaum: function () { return seitenbaum; },\n'
		+ '        setSeitenbaum: function (neu) { seitenbaum = neu; }\n'
		+ '    };\n';

	const modifiziert = quelle.slice(0, ersterIndex + ANKER.length) + haken + quelle.slice(ersterIndex + ANKER.length);

	const dom = neueDomUmgebung();
	const fenster = {
		cbdPageImport: {
			ajaxUrl: 'http://example.test/wp-admin/admin-ajax.php',
			nonceParse: 'nonce-parse',
			nonceImport: 'nonce-import',
			seitenmanagerUrl: 'http://example.test/wp-admin/admin.php?page=page-manager',
			accordionVerfuegbar: false
		}
	};
	if (windowExtra) {
		Object.keys(windowExtra).forEach(function (k) { fenster[k] = windowExtra[k]; });
	}

	new Function('window', 'document', modifiziert)(fenster, dom.document);

	if (!fenster.__cbdTestHooks__) {
		throw new Error('window.__cbdTestHooks__ wurde nicht gesetzt - Testhaken griff nicht');
	}

	return { hooks: fenster.__cbdTestHooks__, dom: dom };
}

/**
 * Baut ein frisches Testszenario: neues Modul plus die beiden Elemente, die
 * admin/page-import.php im echten Markup bereitstellt (#cbd-pi-kaskade,
 * #cbd-import-parent).
 */
function neuesSzenario(windowExtra) {
	const geladen = ladeModul(windowExtra);
	const kaskadeDiv = geladen.dom.createNode('div');
	kaskadeDiv.id = 'cbd-pi-kaskade';
	const parentFeld = geladen.dom.createNode('input');
	parentFeld.id = 'cbd-import-parent';
	parentFeld.value = '0';
	return { hooks: geladen.hooks, dom: geladen.dom, kaskadeDiv: kaskadeDiv, parentFeld: parentFeld };
}

function werte(optionen) {
	return optionen.map(function (o) { return o.value; });
}

// --- Testdaten ------------------------------------------------------------
//
//   1  Seite A                     (Wurzel)
//   +-- 2  Unterseite A1
//       +-- 3  Unter-Unterseite A1a     (Blatt)
//   4  Seite B <b>fett</b>          (Wurzel, Blatt - Titel bewusst mit
//                                     HTML-aehnlichem Text, um zu belegen,
//                                     dass NICHT als HTML geparst wird)

function baum() {
	return {
		knoten: {
			1: { id: 1, parent: 0, titel: 'Seite A', menuOrder: 0, tiefe: 0, typ: 'page', gesperrt: false },
			2: { id: 2, parent: 1, titel: 'Unterseite A1', menuOrder: 0, tiefe: 1, typ: 'page', gesperrt: false },
			3: { id: 3, parent: 2, titel: 'Unter-Unterseite A1a', menuOrder: 0, tiefe: 2, typ: 'page', gesperrt: false },
			4: { id: 4, parent: 0, titel: 'Seite B <b>fett</b>', menuOrder: 1, tiefe: 0, typ: 'page', gesperrt: false }
		},
		kinder: { 0: [1, 4], 1: [2], 2: [3] },
		wurzeln: [1, 4]
	};
}

// ---------------------------------------------------------------------------

async function main() {
	console.log('Prueft: ' + QUELLDATEI);

	await gruppe('Hausstil / Sicherheitskonvention der Datei', function () {
		const quelle = quelleLesen();
		check('Datei existiert und ist nicht leer', quelle.length > 0, quelle.length);
		check('keine .innerHTML =-Zuweisung im gesamten Quelltext', !/\.innerHTML\s*=/.test(quelle));
		check('Ankerzeile fuer Testhaken kommt genau einmal vor', 1 === quelle.split(ANKER).length - 1);
	});

	await gruppe('Modul laedt ohne Ausnahme, alle Testhaken sind Funktionen', function () {
		const geladen = ladeModul();
		check('kaskadeLaden ist eine Funktion', 'function' === typeof geladen.hooks.kaskadeLaden);
		check('kaskadeFehler ist eine Funktion', 'function' === typeof geladen.hooks.kaskadeFehler);
		check('kaskadeZeichnen ist eine Funktion', 'function' === typeof geladen.hooks.kaskadeZeichnen);
		check('kaskadeEbeneBauen ist eine Funktion', 'function' === typeof geladen.hooks.kaskadeEbeneBauen);
		check('kaskadeAuswahlGeaendert ist eine Funktion', 'function' === typeof geladen.hooks.kaskadeAuswahlGeaendert);
		check('kaskadeSperren ist eine Funktion', 'function' === typeof geladen.hooks.kaskadeSperren);
	});

	await gruppe('kaskadeEbeneBauen(): Ebene-1-Aufbau aus wurzeln', function () {
		const s = neuesSzenario();
		s.hooks.setSeitenbaum(baum());

		const ebene = s.hooks.kaskadeEbeneBauen(0, baum().wurzeln);

		check('Ergebnis ist ein <select>', 'select' === ebene.tagName, ebene.tagName);
		check('Klasse cbd-pi-kaskade-ebene gesetzt', 'cbd-pi-kaskade-ebene' === ebene.className, ebene.className);
		check('drei Optionen (Platzhalter + zwei Wurzeln)', 3 === ebene.options.length, ebene.options.length);
		check('Option 0: Wert 0', '0' === ebene.options[0].value, ebene.options[0].value);
		check('Option 0: Text "— oberste Ebene —"', '— oberste Ebene —' === ebene.options[0].text, ebene.options[0].text);
		check('Option 1: Wert 1', '1' === ebene.options[1].value, ebene.options[1].value);
		check('Option 1: Text = Seitentitel', 'Seite A' === ebene.options[1].text, ebene.options[1].text);
		check('Option 2: Wert 4', '4' === ebene.options[2].value, ebene.options[2].value);
		check(
			'Option 2: HTML-aehnlicher Titel bleibt wortgleich (kein innerHTML-Parsing)',
			'Seite B <b>fett</b>' === ebene.options[2].text,
			ebene.options[2].text
		);
		check(
			'Text kam ueber .text/.textContent an, nicht als geparstes Markup',
			ebene.options[2].textContent === ebene.options[2].text
		);
	});

	await gruppe('kaskadeAuswahlGeaendert(): Auswahl einer Seite MIT Kindern haengt neue Ebene an', function () {
		const s = neuesSzenario();
		s.hooks.setSeitenbaum(baum());
		s.hooks.kaskadeZeichnen(s.kaskadeDiv);

		const ebene0 = s.kaskadeDiv.childNodes[0];
		check('Ebene 0 wurde gezeichnet', 'select' === ebene0.tagName, ebene0.tagName);

		ebene0.value = '1';
		s.hooks.kaskadeAuswahlGeaendert(ebene0);

		check('genau zwei Ebenen nach der Auswahl', 2 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);
		const ebene1 = s.kaskadeDiv.childNodes[1];
		check('neue Ebene ist ein <select>', 'select' === ebene1.tagName, ebene1.tagName);
		check('neue Ebene: zwei Optionen', 2 === ebene1.options.length, ebene1.options.length);
		check('neue Ebene, Option 0: Wert = Eltern-ID (1), NICHT 0', '1' === ebene1.options[0].value, ebene1.options[0].value);
		check(
			'neue Ebene, Option 0: Text "— diese Seite als Elternseite —"',
			'— diese Seite als Elternseite —' === ebene1.options[0].text,
			ebene1.options[0].text
		);
		check('neue Ebene, Option 1: das Kind (2)', '2' === ebene1.options[1].value, ebene1.options[1].value);
		check('verstecktes Feld auf die gewaehlte ID (1) gesetzt', '1' === s.parentFeld.value, s.parentFeld.value);
	});

	await gruppe('kaskadeAuswahlGeaendert(): Auswahl eines Blatts haengt KEINE weitere Ebene an', function () {
		const s = neuesSzenario();
		s.hooks.setSeitenbaum(baum());
		s.hooks.kaskadeZeichnen(s.kaskadeDiv);

		const ebene0 = s.kaskadeDiv.childNodes[0];
		ebene0.value = '4'; // Seite B, hat keine Kinder
		s.hooks.kaskadeAuswahlGeaendert(ebene0);

		check('weiterhin genau eine Ebene (Blatt haengt nichts an)', 1 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);
		check('verstecktes Feld auf 4 gesetzt', '4' === s.parentFeld.value, s.parentFeld.value);
	});

	await gruppe('kaskadeAuswahlGeaendert(): erneute Wahl in einer hoeheren Ebene entfernt alle tieferen Ebenen', function () {
		const s = neuesSzenario();
		s.hooks.setSeitenbaum(baum());
		s.hooks.kaskadeZeichnen(s.kaskadeDiv);

		const ebene0 = s.kaskadeDiv.childNodes[0];
		ebene0.value = '1';
		s.hooks.kaskadeAuswahlGeaendert(ebene0); // Ebene 1 (Kind 2) haengt an

		const ebene1 = s.kaskadeDiv.childNodes[1];
		ebene1.value = '2';
		s.hooks.kaskadeAuswahlGeaendert(ebene1); // Ebene 2 (Kind 3) haengt an

		check('drei Ebenen nach zweifachem Drill-down', 3 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);

		// Jetzt in Ebene 0 einen ANDEREN Wurzelknoten waehlen.
		ebene0.value = '4';
		s.hooks.kaskadeAuswahlGeaendert(ebene0);

		check('nach erneuter Wahl in Ebene 0: alle tieferen Ebenen entfernt', 1 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);
		check('verstecktes Feld folgt der neuen Wahl (4)', '4' === s.parentFeld.value, s.parentFeld.value);
	});

	await gruppe('kaskadeAuswahlGeaendert(): Ruecksprung auf "oberste Ebene" (Wert 0) setzt das Feld auf 0', function () {
		const s = neuesSzenario();
		s.hooks.setSeitenbaum(baum());
		s.hooks.kaskadeZeichnen(s.kaskadeDiv);

		const ebene0 = s.kaskadeDiv.childNodes[0];
		ebene0.value = '1';
		s.hooks.kaskadeAuswahlGeaendert(ebene0); // Ebene 1 haengt an

		check('Ebene 1 haengt zunaechst an', 2 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);

		ebene0.value = '0'; // "oberste Ebene"
		s.hooks.kaskadeAuswahlGeaendert(ebene0);

		check('nach Ruecksprung: nur noch Ebene 0', 1 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);
		check('verstecktes Feld auf 0 gesetzt', '0' === s.parentFeld.value, s.parentFeld.value);
	});

	await gruppe('B1-Fix: "diese Seite als Elternseite" in einer Zwischenebene erzeugt KEINE Doppel-Ebene', function () {
		const s = neuesSzenario();
		s.hooks.setSeitenbaum(baum());
		s.hooks.kaskadeZeichnen(s.kaskadeDiv);

		const ebene0 = s.kaskadeDiv.childNodes[0];
		ebene0.value = '1';
		s.hooks.kaskadeAuswahlGeaendert(ebene0); // Ebene 1 (elternId=1) haengt an

		const ebene1 = s.kaskadeDiv.childNodes[1];
		check(
			'Ebene 1, Option 0 ist "diese Seite als Elternseite" mit Wert 1',
			'1' === ebene1.options[0].value && '— diese Seite als Elternseite —' === ebene1.options[0].text,
			ebene1.options[0]
		);

		ebene1.value = '2';
		s.hooks.kaskadeAuswahlGeaendert(ebene1); // Ebene 2 (elternId=2) haengt an

		check('vor dem Ruecksprung: drei Ebenen', 3 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);

		// Der eigentliche B1-Fall: in Ebene 1 (einer Zwischenebene) erneut
		// deren EIGENE erste Option waehlen (Wert 1 = die bereits gewaehlte
		// Eltern-ID, NICHT 0).
		ebene1.value = String(ebene1.options[0].value);
		s.hooks.kaskadeAuswahlGeaendert(ebene1);

		check(
			'B1: genau zwei Ebenen nach dem Ruecksprung (Ebene 2 entfernt, KEINE neue Doppel-Ebene)',
			2 === s.kaskadeDiv.childNodes.length,
			s.kaskadeDiv.childNodes.length
		);
		check('B1: Ebene 1 ist unveraendert dieselbe Ebene (nicht ersetzt)', s.kaskadeDiv.childNodes[1] === ebene1);
		check('B1: verstecktes Feld bleibt korrekt auf der Eltern-ID (1)', '1' === s.parentFeld.value, s.parentFeld.value);
	});

	await gruppe('kaskadeFehler(): zeigt die Meldung ohne Ausnahme', function () {
		const s = neuesSzenario();

		s.hooks.kaskadeFehler(s.kaskadeDiv, 'Testfehlermeldung');

		check('genau ein Kindknoten', 1 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);
		const meldung = s.kaskadeDiv.childNodes[0];
		check('Meldung ist ein <p>', 'p' === meldung.tagName, meldung.tagName);
		check(
			'Meldung traegt die Fehler-Klasse',
			'cbd-pi-kaskade-status cbd-pi-kaskade-status--fehler' === meldung.className,
			meldung.className
		);
		check('Meldungstext stimmt', 'Testfehlermeldung' === meldung.textContent, meldung.textContent);
	});

	await gruppe('kaskadeSperren(): sperrt/entsperrt alle sichtbaren Ebenen', function () {
		const s = neuesSzenario();
		s.hooks.setSeitenbaum(baum());
		s.hooks.kaskadeZeichnen(s.kaskadeDiv);
		const ebene0 = s.kaskadeDiv.childNodes[0];
		ebene0.value = '1';
		s.hooks.kaskadeAuswahlGeaendert(ebene0);
		const ebene1 = s.kaskadeDiv.childNodes[1];

		s.hooks.kaskadeSperren(true);
		check('Ebene 0 gesperrt', true === ebene0.disabled);
		check('Ebene 1 gesperrt', true === ebene1.disabled);

		s.hooks.kaskadeSperren(false);
		check('Ebene 0 entsperrt', false === ebene0.disabled);
		check('Ebene 1 entsperrt', false === ebene1.disabled);
	});

	await gruppe('kaskadeSperren(): fehlender Container wirft keine Ausnahme', function () {
		const geladen = ladeModul(); // KEIN #cbd-pi-kaskade registriert
		let keinFehler = true;
		try {
			geladen.hooks.kaskadeSperren(true);
		} catch (e) {
			keinFehler = false;
		}
		check('kein Fehler beim Sperren ohne Container', keinFehler);
	});

	await gruppe('kaskadeLaden(): kein wp.apiFetch verfuegbar', async function () {
		const s = neuesSzenario(); // fenster.wp bleibt undefined
		await s.hooks.kaskadeLaden();

		check('genau eine Meldung im Container', 1 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);
		const meldung = s.kaskadeDiv.childNodes[0];
		check(
			'Meldung traegt die Fehler-Klasse',
			-1 !== meldung.className.indexOf('cbd-pi-kaskade-status--fehler'),
			meldung.className
		);
		check(
			'Meldung nennt "nicht verfuegbar"',
			-1 !== meldung.textContent.indexOf('nicht verfügbar'),
			meldung.textContent
		);
		check('verstecktes Feld bleibt unangetastet auf 0', '0' === s.parentFeld.value, s.parentFeld.value);
	});

	await gruppe('kaskadeLaden(): apiFetch schlaegt fehl', async function () {
		const pfade = [];
		const original = console.error;
		console.error = function () {}; // Fehlerpfad loggt absichtlich - hier stummschalten

		try {
			const s = neuesSzenario({
				wp: {
					apiFetch: function (argumente) {
						pfade.push(argumente && argumente.path);
						return Promise.reject(new Error('Netzwerkfehler'));
					}
				}
			});

			await s.hooks.kaskadeLaden();

			check('genau ein Pfad angefragt', 1 === pfade.length, pfade);
			check('angefragter Pfad ist /cbd/v1/seitenbaum?entwuerfe=1', '/cbd/v1/seitenbaum?entwuerfe=1' === pfade[0], pfade[0]);
			check('genau eine Meldung im Container', 1 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);
			const meldung = s.kaskadeDiv.childNodes[0];
			check(
				'Meldung traegt die Fehler-Klasse',
				-1 !== meldung.className.indexOf('cbd-pi-kaskade-status--fehler'),
				meldung.className
			);
			check(
				'Meldung nennt den Ladefehler',
				-1 !== meldung.textContent.indexOf('konnte nicht geladen werden'),
				meldung.textContent
			);
			check('verstecktes Feld bleibt unangetastet auf 0', '0' === s.parentFeld.value, s.parentFeld.value);
		} finally {
			console.error = original;
		}
	});

	await gruppe('kaskadeLaden(): apiFetch erfolgreich', async function () {
		const pfade = [];
		const s = neuesSzenario({
			wp: {
				apiFetch: function (argumente) {
					pfade.push(argumente && argumente.path);
					return Promise.resolve(baum());
				}
			}
		});

		await s.hooks.kaskadeLaden();

		check('genau ein Pfad angefragt', 1 === pfade.length, pfade);
		check(
			'AP-1.1-Vertrag genutzt: /cbd/v1/seitenbaum?entwuerfe=1',
			'/cbd/v1/seitenbaum?entwuerfe=1' === pfade[0],
			pfade[0]
		);
		check('Ebene 1 wurde gezeichnet', 1 === s.kaskadeDiv.childNodes.length, s.kaskadeDiv.childNodes.length);
		const ebene0 = s.kaskadeDiv.childNodes[0];
		check('Ebene 1 zeigt die Wurzeln aus der Antwort', '0,1,4' === werte(ebene0.options).join(','), werte(ebene0.options));
		check(
			'geladener Seitenbaum steht als Modulzustand zur Verfuegung',
			JSON.stringify(baum()) === JSON.stringify(s.hooks.getSeitenbaum())
		);
	});

	console.log('\n' + pruefungen + ' Pruefungen, ' + fehlschlaege + ' Fehler');
	console.log(0 === fehlschlaege ? 'ALLE TESTS BESTANDEN' : fehlschlaege + ' FEHLER');
	process.exit(0 === fehlschlaege ? 0 : 1);
}

main().catch(function (fehler) {
	console.error('Harnisch abgebrochen:', fehler);
	process.exit(1);
});
