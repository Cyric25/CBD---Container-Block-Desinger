#!/usr/bin/env node
/**
 * Standalone-Harnisch fuer den Taktgeber `assets/js/klassenpuls.js`
 * (Vorhaben „Schneller Klassenpuls", Phase 2) — ohne Browser, ohne jsdom,
 * ohne npm-Abhaengigkeit.
 *
 * Aufruf:  node tools/test-klassenpuls-js.js
 *
 * ---------------------------------------------------------------------------
 * WARUM DIESE DATEI IM REPOSITORY LIEGT
 * ---------------------------------------------------------------------------
 *
 * `tools/test-klassenpuls.php` deckt die SERVERSEITE des Klassenpulses ab (41
 * Pruefungen). Fuer die Clientseite gab es bis AP-2.fix1 keinen Gegenpart im
 * Repository — die Pruefungen der Phase 2 lagen in einem Scratchpad und waeren
 * mit der Arbeitssitzung verschwunden. Fuer die komplexeste Zustandsmaschine
 * des Vorhabens (zwei verschraenkte Zeitgeber, Rueckfallkette, Sichtbarkeit,
 * Sitzungswechsel) waere damit kein wiederholbarer Schutz uebrig geblieben.
 * Befund `P1` aus `AP-2.rev`.
 *
 * ---------------------------------------------------------------------------
 * DAS VERFAHREN, UND WARUM ES SO AUSSIEHT
 * ---------------------------------------------------------------------------
 *
 * Gruppe A faehrt die AUSGELIEFERTE Datei in einem `vm`-Kontext ueber ihre
 * oeffentliche Schnittstelle. Kein Nachbau: Es laeuft der Quelltext, der auch
 * an den Browser geht.
 *
 * Gruppe B schneidet `uebernehmeSignaturen()` und `verarbeiteDatei()` aus
 * derselben Datei HERAUS und fuehrt sie gegen einen nachgebauten
 * Modulzustand. Noetig, weil `verarbeiteDatei()` von aussen nicht erreichbar
 * ist. Muster uebernommen aus `tools/test-svg-aufbereitung.js`.
 *
 * Gruppe C ersetzt `setTimeout` durch eine VIRTUELLE UHR: `tick()` springt zum
 * naechstfaelligen Zeitgeber und fuehrt ihn aus. Anders liessen sich zwei
 * Zeitgeber im Verhaeltnis 1:25 nicht in ihrer echten Verschraenkung pruefen,
 * ohne minutenlang zu warten. `fetch()` unterscheidet Route und Pulsdatei an
 * der Adresse und beantwortet beide aus eigenen Warteschlangen.
 *
 * Eine andere Datei pruefen (fuer Mutationsproben): `CBD_PULS_DATEI=<pfad>`.
 *
 * ---------------------------------------------------------------------------
 * DASS DER HARNISCH BEISST, IST GEPRUEFT — NICHT BEHAUPTET
 * ---------------------------------------------------------------------------
 *
 * Zwanzig Mutationen wurden gegen eine KOPIE der Datei gefahren; jede muss
 * mindestens eine Pruefung umbringen. Wer den Harnisch erweitert, sollte das
 * wiederholen, statt sich auf eine gruene Bilanz zu verlassen — beim Bauen
 * haben drei Pruefungen genau daran versagt (eine war ein Muenzwurf, eine
 * prueft te den falschen Zustand, eine prueft te gar nichts). Die tragenden
 * Mutationen, knapp:
 *
 *   - `hasOwnProperty`-Wache in `uebernehmeSignaturen()` entfernt
 *   - Feuerbedingung ohne `erstanfrageErledigt` / ohne beide Terme
 *   - inline melden statt erst vergleichen, dann melden
 *   - `verarbeiteDatei()` uebernimmt den Takt aus der Datei
 *   - `seiten_unvollstaendig`-Wache entfernt
 *   - Ersatzwert statt Auslassen bei unbekannter `seiteId`
 *   - Herzschlag-Basis ignoriert den Dateimodus / `Math.max` entfernt
 *   - Dateiintervall nutzt die REST-Streuformel
 *   - Rueckfallkette abgeschaltet / Herzschlag danach nicht neu geplant
 *   - verstecktes Fenster loescht den Dateizeitgeber nicht
 *   - `halte()` / `sitzungAbgelaufen()` lassen den Dateizeitgeber laufen
 *   - `starte()` gleicht den Dateizeitgeber nicht ab
 *   - `setzeSitzung()` plant den Herzschlag nicht neu
 *   - gleichzeitige Dateiabfragen erlaubt
 *   - `credentials: 'include'` statt `'omit'`
 *   - Dateiadresse nicht aus der Route
 *   - fehlender `herzschlag` faellt auf 0 statt auf 60 zurueck
 *
 * EINE Mutation ueberlebt mit Absicht und ist kein Mangel: Der Term
 * `erstanfrageErledigt &&` allein entfernt aendert nichts, weil `signaturen`
 * und `erstanfrageErledigt` nur GEMEINSAM zurueckgesetzt werden. A9/A9b
 * halten genau diese Voraussetzung fest; der Docblock in der geprueften Datei
 * sagt es ebenfalls.
 *
 * @package ContainerBlockDesigner
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const QUELLDATEI = process.env.CBD_PULS_DATEI
	|| path.join(__dirname, '..', 'assets', 'js', 'klassenpuls.js');

/**
 * Zeilenenden werden beim Einlesen auf LF normalisiert.
 *
 * NICHT KOSMETIK, SONDERN NOTWENDIG: Git normalisiert die Datei auf dieser
 * Plattform beim Auschecken auf CRLF. `schneideAus()` unten sucht das
 * Funktionsende als `\n\t}\n` — in einer CRLF-Datei steht dort `\r\n\t}\r\n`,
 * und die Suche geht ins Leere. Der Harnisch brach dann mit „Funktionsende
 * nicht gefunden" ab, obwohl am geprueften Code nichts falsch war. Gefunden
 * beim ersten Lauf auf `main` unmittelbar nach dem Merge der Phase 2 — auf
 * dem Arbeitsbranch lag die Datei noch mit LF vor.
 */
const quelle = fs.readFileSync(QUELLDATEI, 'utf8').replace(/\r\n/g, '\n');

/** Eine erfundene, aber formgerechte Pulsdatei-Adresse. */
const DATEI_URL = 'https://example.test/wp-content/uploads/'
	+ 'container-block-designer/klassenpuls/puls-15-abcdef0123456789.json';

/** Die Klemmung aus `DATEI_INTERVALL_MIN_MS` — sie darf nie greifen. */
const DATEI_INTERVALL_MIN_MS = 1000;

/** Die sieben Namen des Vertrags `window.cbdKlassenpuls`. */
const VERTRAG = [
	'abonniere', 'halte', 'laeuft', 'setzeSeite', 'setzeSitzung', 'sofort', 'starte'
];

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

function zwischen(name, wert, min, max) {
	pruefe(name, wert >= min && wert <= max,
		'wert=' + wert + ' erwartet ' + min + '..' + max);
}

function warte() {
	return new Promise(function (aufloesen) { setImmediate(aufloesen); });
}

// =========================================================================
// Umgebung: Stub-`window` mit virtueller Uhr und zweigeteiltem `fetch`
// =========================================================================

function baue(optionen) {
	optionen = optionen || {};

	let jetzt = 0;
	let naechsteId = 1;
	let zeitgeber = [];                       // {id, fn, faellig, ms}
	const abrufe = { route: [], datei: [] };
	const antwortenRoute = optionen.route || [];
	const antwortenDatei = optionen.datei || [];
	const warnungen = [];
	const sandbox = {};

	sandbox.window = sandbox;
	sandbox.document = {
		hidden: !!optionen.versteckt,
		addEventListener: function (art, fn) {
			if ('visibilitychange' === art) { sandbox.__sichtbarkeit = fn; }
		}
	};
	sandbox.cbdKlassenpulsDaten = {
		restUrl: 'https://example.test/?rest_route=/cbd/v1/klassenpuls',
		takt: 10
	};
	sandbox.cbdDebug = false;
	sandbox.console = {
		log: function () {},
		error: function () {},
		warn: function (t) { warnungen.push(String(t)); }
	};
	sandbox.Promise = Promise;
	sandbox.Math = Math;
	sandbox.isNaN = isNaN;
	sandbox.parseInt = parseInt;
	sandbox.Object = Object;
	sandbox.Error = Error;
	sandbox.String = String;
	sandbox.encodeURIComponent = encodeURIComponent;
	sandbox.Date = Date;

	sandbox.setTimeout = function (fn, ms) {
		const id = naechsteId++;
		zeitgeber.push({ id: id, fn: fn, faellig: jetzt + ms, ms: ms });
		return id;
	};
	sandbox.clearTimeout = function (id) {
		zeitgeber = zeitgeber.filter(function (z) { return z.id !== id; });
	};

	sandbox.fetch = function (url, init) {
		const istDatei = url.indexOf('puls-') > -1;
		const schlange = istDatei ? antwortenDatei : antwortenRoute;
		const naechste = schlange.shift();

		abrufe[istDatei ? 'datei' : 'route'].push({
			t: jetzt, url: url, init: init || null
		});

		if (!naechste || naechste.netzfehler) {
			return Promise.reject(new Error(naechste ? 'Netzfehler' : 'leer'));
		}

		return Promise.resolve({
			ok: naechste.status >= 200 && naechste.status < 300,
			status: naechste.status,
			json: function () {
				return naechste.kaputt
					? Promise.reject(new Error('kein JSON'))
					: Promise.resolve(naechste.daten);
			}
		});
	};

	vm.createContext(sandbox);
	vm.runInContext(quelle, sandbox, { filename: 'klassenpuls.js' });

	return {
		w: sandbox,
		abrufe: abrufe,
		warnungen: warnungen,
		offene: function () {
			return zeitgeber
				.map(function (z) { return { ms: z.ms, faellig: z.faellig }; })
				.sort(function (a, b) { return a.faellig - b.faellig; });
		},
		verstecke: function (ja) {
			sandbox.document.hidden = !!ja;
			if (sandbox.__sichtbarkeit) { sandbox.__sichtbarkeit(); }
		},
		/** Zum naechstfaelligen Zeitgeber springen und ihn ausfuehren. */
		tick: async function (n) {
			for (let i = 0; i < (n || 1); i++) {
				if (!zeitgeber.length) { return false; }
				zeitgeber.sort(function (a, b) { return a.faellig - b.faellig; });
				const z = zeitgeber.shift();
				jetzt = z.faellig;
				z.fn();
				await warte();
				await warte();
			}
			return true;
		},
		warte: warte
	};
}

function routeAntwort(zusatz) {
	const d = {
		klasse: 'k0', fragenwand: 'f0', seite: 's0', tafel: 't0',
		takt: 10, datei: DATEI_URL, takt_datei: 2, herzschlag: 60
	};
	Object.keys(zusatz || {}).forEach(function (k) { d[k] = zusatz[k]; });
	return { status: 200, daten: d };
}

function dateiAntwort(zusatz) {
	const d = {
		klasse: 'k0', fragenwand: 'f0',
		seiten: { '1618': ['s0', 't0'] },
		takt: 99, stand: 1
	};
	Object.keys(zusatz || {}).forEach(function (k) { d[k] = zusatz[k]; });
	return { status: 200, daten: d };
}

function vieleDateiAntworten(n) {
	const a = [];
	for (let i = 0; i < n; i++) { a.push(dateiAntwort()); }
	return a;
}

// =========================================================================
// GRUPPE A — Routenweg ueber die oeffentliche Schnittstelle
// =========================================================================

async function gruppeA() {
	console.log('\nGruppe A - Routenweg ueber die oeffentliche Schnittstelle\n');

	let u = baue({});
	gleich('A1 - genau die sieben Vertragsnamen',
		Object.keys(u.w.cbdKlassenpuls).sort(), VERTRAG.slice().sort());

	// --- Regel 1 ---------------------------------------------------------
	let meldungen = [];
	u = baue({
		route: [
			routeAntwort({ datei: null, takt_datei: 0 }),
			routeAntwort({ datei: null, takt_datei: 0 }),
			routeAntwort({ datei: null, takt_datei: 0, klasse: 'ZZZ' })
		]
	});

	['seite', 'tafel', 'klasse', 'fragenwand'].forEach(function (n) {
		u.w.cbdKlassenpuls.abonniere(n, function (neu, alt) {
			meldungen.push(n + ':' + alt + '->' + neu);
		});
	});

	u.w.cbdKlassenpuls.setzeSeite(1618);
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	gleich('A2 - erste Antwort meldet nichts (Regel 1)', meldungen, []);

	await u.tick();
	gleich('A3 - unveraenderte zweite Antwort meldet nichts', meldungen, []);

	await u.tick();
	gleich('A4 - geaenderte Signatur meldet genau einmal',
		meldungen, ['klasse:k0->ZZZ']);

	// --- takt 0 ----------------------------------------------------------
	u = baue({
		route: [
			routeAntwort({ datei: null, takt_datei: 0 }),
			routeAntwort({ datei: null, takt_datei: 0, takt: 0 })
		]
	});
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	await u.tick();
	pruefe('A5 - takt 0 haelt den Taktgeber an',
		false === u.w.cbdKlassenpuls.laeuft());

	// --- abgelaufene Sitzung ---------------------------------------------
	let abgelaufen = 0;
	u = baue({ route: [{ status: 404, daten: { code: 'cbd_puls_not_available' } }] });
	u.w.cbdKlassenpuls.abonniere('abgelaufen', function () { abgelaufen++; });
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	pruefe('A6 - HTTP 404 meldet "abgelaufen" genau einmal', 1 === abgelaufen,
		'gemeldet=' + abgelaufen);
	pruefe('A7 - nach 404 laeuft der Taktgeber nicht mehr',
		false === u.w.cbdKlassenpuls.laeuft());

	// --- fehlendes Feld setzt nicht zurueck ------------------------------
	meldungen = [];
	u = baue({
		route: [
			routeAntwort({ datei: null, takt_datei: 0, seite: 's1', tafel: 't1' }),
			{ status: 200, daten: { klasse: 'k0', fragenwand: 'f0', takt: 10 } },
			routeAntwort({ datei: null, takt_datei: 0, seite: 's2', tafel: 't1' })
		]
	});
	u.w.cbdKlassenpuls.setzeSeite(1618);
	u.w.cbdKlassenpuls.abonniere('seite', function (neu, alt) {
		meldungen.push(alt + '->' + neu);
	});
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	await u.tick();
	await u.tick();
	gleich('A8 - fehlendes Feld setzt nicht zurueck, Aenderung danach kommt an',
		meldungen, ['s1->s2']);

	// --- Sitzungswechsel verwirft BEIDE Marken ---------------------------
	//
	// Diese Pruefung haelt die Voraussetzung fest, unter der der Term
	// `erstanfrageErledigt &&` in der Feuerbedingung redundant ist: dass
	// `signaturen` und `erstanfrageErledigt` nur GEMEINSAM zurueckgesetzt
	// werden. Setzt jemand kuenftig nur eine der beiden Marken zurueck, wird
	// der Term wieder tragend — und diese Pruefung schlaegt an.
	meldungen = [];
	u = baue({
		route: [
			routeAntwort({ datei: null, takt_datei: 0, klasse: 'a1', fragenwand: 'b1' }),
			routeAntwort({ datei: null, takt_datei: 0, klasse: 'ANDERS', fragenwand: 'AUCH' })
		]
	});
	u.w.cbdKlassenpuls.abonniere('klasse', function (neu, alt) {
		meldungen.push('klasse:' + alt + '->' + neu);
	});
	u.w.cbdKlassenpuls.abonniere('fragenwand', function (neu, alt) {
		meldungen.push('fragenwand:' + alt + '->' + neu);
	});
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok-a');
	await u.warte(); await u.warte();
	gleich('A9 - erste Antwort der ersten Sitzung meldet nichts', meldungen, []);

	u.w.cbdKlassenpuls.setzeSitzung(16, 'tok-b');
	await u.warte(); await u.warte();
	gleich('A9b - erste Antwort NACH Sitzungswechsel meldet ebenfalls nichts',
		meldungen, []);
}

// =========================================================================
// GRUPPE B — herausgeschnittene Funktionen gegen nachgebauten Zustand
// =========================================================================

function schneideAus(name) {
	const start = quelle.indexOf('\n\tfunction ' + name + '(');

	if (start < 0) { throw new Error('Funktion nicht gefunden: ' + name); }

	const ende = quelle.indexOf('\n\t}\n', start);

	if (ende < 0) { throw new Error('Funktionsende nicht gefunden: ' + name); }

	return quelle.slice(start, ende + 4);
}

function baueTeilB(seiteIdWert, vorhandeneSignaturen, erstanfrage) {
	const gemeldet = [];
	const sandbox = {
		SIGNATURNAMEN: ['seite', 'tafel', 'klasse', 'fragenwand'],
		signaturen: Object.assign({}, vorhandeneSignaturen),
		seiteId: seiteIdWert,
		erstanfrageErledigt: erstanfrage,
		melde: function () {},
		rufeAbonnenten: function (name, neu, alt) {
			gemeldet.push(name + ':' + alt + '->' + neu);
		},
		Object: Object,
		String: String,
		console: console
	};

	vm.createContext(sandbox);
	vm.runInContext(
		schneideAus('uebernehmeSignaturen') + '\n' + schneideAus('verarbeiteDatei')
		+ '\nthis.uebernehmeSignaturen = uebernehmeSignaturen;'
		+ '\nthis.verarbeiteDatei = verarbeiteDatei;',
		sandbox, { filename: 'ausschnitt.js' });

	return { s: sandbox, gemeldet: gemeldet };
}

function gruppeB() {
	console.log('\nGruppe B - verarbeiteDatei() und uebernehmeSignaturen()\n');

	const voll = { seite: 'alt1', tafel: 'alt2', klasse: 'altK', fragenwand: 'altF' };
	let t;

	t = baueTeilB(1618, voll, true);
	t.s.verarbeiteDatei({
		klasse: 'neuK', fragenwand: 'altF',
		seiten: { '1618': ['neu1', 'alt2'] },
		takt: 99, stand: 1
	});
	gleich('B1 - nur die zwei echten Aenderungen melden, in Reihenfolge',
		t.gemeldet, ['seite:alt1->neu1', 'klasse:altK->neuK']);
	gleich('B1b - Signaturen uebernommen',
		[t.s.signaturen.seite, t.s.signaturen.tafel, t.s.signaturen.klasse],
		['neu1', 'alt2', 'neuK']);

	pruefe('B2 - verarbeiteDatei() beruehrt keinen Takt',
		schneideAus('verarbeiteDatei').indexOf('takt') === -1,
		'Fundstelle im Rumpf');

	// `seiten` IST hier vorhanden. Nur so ist die Wache ueberhaupt
	// beobachtbar — ohne den Schluessel schiene sie zu wirken, obwohl in
	// Wahrheit nur die Abbildung fehlt.
	t = baueTeilB(1618, voll, true);
	t.s.verarbeiteDatei({
		klasse: 'altK', fragenwand: 'altF',
		seiten_unvollstaendig: true,
		seiten: { '1618': ['veraltet1', 'veraltet2'] }
	});
	gleich('B3 - seiten_unvollstaendig meldet nichts', t.gemeldet, []);
	gleich('B3b - seite/tafel bleiben unveraendert stehen',
		[t.s.signaturen.seite, t.s.signaturen.tafel], ['alt1', 'alt2']);

	t = baueTeilB(9999, voll, true);
	t.s.verarbeiteDatei({
		klasse: 'altK', fragenwand: 'altF', seiten: { '1618': ['x', 'y'] }
	});
	gleich('B4 - unbekannte seiteId meldet nichts', t.gemeldet, []);
	gleich('B4b - seite/tafel bleiben stehen (kein Ersatzwert)',
		[t.s.signaturen.seite, t.s.signaturen.tafel], ['alt1', 'alt2']);

	t = baueTeilB(0, { klasse: 'altK', fragenwand: 'altF' }, true);
	t.s.verarbeiteDatei({
		klasse: 'altK', fragenwand: 'neuF', seiten: { '1618': ['x', 'y'] }
	});
	gleich('B5 - ohne Seitenbezug nur klasse/fragenwand', t.gemeldet,
		['fragenwand:altF->neuF']);
	pruefe('B5b - seite wird dabei nicht gesetzt',
		!Object.prototype.hasOwnProperty.call(t.s.signaturen, 'seite'));

	// Regel 1 gilt quellenuebergreifend.
	t = baueTeilB(1618, {}, false);
	t.s.verarbeiteDatei({
		klasse: 'k1', fragenwand: 'f1', seiten: { '1618': ['s1', 't1'] }
	});
	gleich('B6 - erste Uebernahme meldet nichts (Regel 1)', t.gemeldet, []);
	pruefe('B6b - erstanfrageErledigt danach true', true === t.s.erstanfrageErledigt);

	t.gemeldet.length = 0;
	t.s.uebernehmeSignaturen({ klasse: 'k1', fragenwand: 'f1', seite: 's1', tafel: 't1' });
	gleich('B6c - gleiche Werte aus der anderen Quelle melden nichts', t.gemeldet, []);

	t.s.uebernehmeSignaturen({ klasse: 'k2', fragenwand: 'f1', seite: 's1', tafel: 't1' });
	gleich('B6d - echte Aenderung aus der anderen Quelle meldet',
		t.gemeldet, ['klasse:k1->k2']);

	t = baueTeilB(1618, { klasse: 'altK', fragenwand: 'altF', seite: 's', tafel: 't' }, true);
	t.s.verarbeiteDatei(null);
	t.s.verarbeiteDatei({});
	t.s.verarbeiteDatei({ klasse: null, fragenwand: undefined });
	t.s.verarbeiteDatei({
		klasse: 'altK', fragenwand: 'altF', seiten: { '1618': ['nur-eins'] }
	});
	gleich('B7 - beschaedigte Dateien melden nichts', t.gemeldet, []);
	gleich('B7b - bekannte Signaturen bleiben unangetastet',
		[t.s.signaturen.klasse, t.s.signaturen.fragenwand,
			t.s.signaturen.seite, t.s.signaturen.tafel],
		['altK', 'altF', 's', 't']);

	t = baueTeilB(1618, { klasse: 'k1', fragenwand: 'f1' }, true);
	const gesehen = [];
	t.s.rufeAbonnenten = function (name) {
		gesehen.push(name + '|' + t.s.signaturen.klasse + ',' + t.s.signaturen.fragenwand);
	};
	t.s.uebernehmeSignaturen({ klasse: 'k2', fragenwand: 'f2' });
	gleich('B8 - beim ersten Rueckruf sind bereits ALLE Signaturen gesetzt',
		gesehen, ['klasse|k2,f2', 'fragenwand|k2,f2']);
}

// =========================================================================
// GRUPPE C — zwei Zeitgeber, Herzschlag, Rueckfallkette
// =========================================================================

async function gruppeC() {
	console.log('\nGruppe C - zwei Zeitgeber, Herzschlag, Rueckfall\n');

	let u;
	let offen;

	// --- zwei Zeitgeber nach der ersten Routenantwort ---------------------
	u = baue({ route: [routeAntwort()] });
	u.w.cbdKlassenpuls.setzeSeite(1618);
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();

	offen = u.offene();
	pruefe('C1 - zwei Zeitgeber geplant', 2 === offen.length,
		'offen=' + JSON.stringify(offen));
	zwischen('C1b - Herzschlag 60 s mit +-25 % (45000-75000 ms)',
		Math.round(offen[1].ms), 45000, 75000);

	// --- Streuung des Dateitakts, ueber viele Ziehungen -------------------
	//
	// EINE Ziehung genuegt hier NICHT. Die falsche Formel (`0,75 + Zufall
	// * 0,5`, also 1500-2500 ms) ueberlappt die richtige (2000-3000 ms) zur
	// Haelfte — eine Einzelmessung waere ein Muenzwurf und liesse die
	// Mutation in rund der Haelfte der Laeufe durch.
	const u2 = baue({ route: [routeAntwort()], datei: vieleDateiAntworten(200) });
	u2.w.cbdKlassenpuls.setzeSeite(1618);
	u2.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u2.warte(); await u2.warte();

	const abstaende = [];
	for (let dz = 0; dz < 150; dz++) {
		const dOffen = u2.offene().filter(function (o) { return o.ms < 5000; });
		if (dOffen.length) { abstaende.push(dOffen[0].ms); }
		await u2.tick();
	}
	const minA = Math.min.apply(Math, abstaende);
	const maxA = Math.max.apply(Math, abstaende);

	pruefe('C2 - alle ' + abstaende.length + ' Dateiintervalle liegen in 2000-3000 ms',
		abstaende.length >= 100 && minA >= 2000 && maxA <= 3000,
		'min=' + Math.round(minA) + ' max=' + Math.round(maxA)
		+ ' n=' + abstaende.length);
	pruefe('C2b - und sie streuen wirklich (Spanne ueber 800 ms)',
		maxA - minA > 800, 'spanne=' + Math.round(maxA - minA));
	pruefe('C2c - die Klemmung greift nie (Streuung nur nach oben)',
		minA > DATEI_INTERVALL_MIN_MS, 'min=' + Math.round(minA));

	// --- der schnelle Zeitgeber ruft die DATEI ----------------------------
	u = baue({ route: [routeAntwort()], datei: [dateiAntwort({ klasse: 'kNEU' })] });
	const gemeldet = [];
	u.w.cbdKlassenpuls.setzeSeite(1618);
	u.w.cbdKlassenpuls.abonniere('klasse', function (neu, alt) {
		gemeldet.push(alt + '->' + neu);
	});
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	await u.tick();

	gleich('C3 - Dateiabruf traegt die Dateiadresse',
		u.abrufe.datei.map(function (a) { return a.url === DATEI_URL; }), [true]);
	gleich('C3b - Aenderung aus der DATEI wird gemeldet', gemeldet, ['k0->kNEU']);
	gleich('C3c - kein zusaetzlicher Routenabruf', u.abrufe.route.length, 1);
	gleich('C4 - cache no-cache und credentials omit',
		u.abrufe.datei[0].init, { cache: 'no-cache', credentials: 'omit' });

	offen = u.offene();
	zwischen('C5 - Herzschlag folgt weiter 60 s, nicht dem takt der Datei',
		Math.round(offen.filter(function (o) { return o.ms > 10000; })[0].ms),
		45000, 75000);

	// --- ohne datei-Feld kein zweiter Zeitgeber ---------------------------
	u = baue({ route: [routeAntwort({ datei: null, takt_datei: 0 })] });
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();

	offen = u.offene();
	pruefe('C6 - ohne Dateiadresse nur EIN Zeitgeber', 1 === offen.length,
		'offen=' + JSON.stringify(offen));
	zwischen('C6b - und der taktet mit takt=10 s, nicht mit dem Herzschlag',
		Math.round(offen[0].ms), 7500, 12500);

	// --- Rueckfallkette: alle drei Fehlerarten ----------------------------
	// Die ZWEITE Routenantwort traegt die Aenderung — die drei Fehlschlaege
	// der Datei verbrauchen keine Routenantwort. Genau hier hatte die erste
	// Fassung dieser Pruefung (C8 im Scratchpad-Harnisch) ins Leere gegriffen:
	// Sie setzte eine Eigenschaft, die die Datei nirgends liest, und wertete
	// das Meldungsarray nie aus (Befund G6 aus `AP-2.rev`).
	u = baue({
		route: [routeAntwort(), routeAntwort({ klasse: 'NACHHER' })],
		datei: [
			{ status: 404 },                    // HTTP-Fehler
			{ status: 200, kaputt: true },      // unlesbares JSON
			{ netzfehler: true }                // Netzfehler
		]
	});
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();

	await u.tick();
	pruefe('C7 - nach dem 1. Fehlschlag (HTTP) laeuft der Dateizeitgeber weiter',
		2 === u.offene().length, 'offen=' + JSON.stringify(u.offene()));

	await u.tick();
	pruefe('C7b - nach dem 2. Fehlschlag (kaputtes JSON) ebenso',
		2 === u.offene().length, 'offen=' + JSON.stringify(u.offene()));

	await u.tick();
	offen = u.offene();
	pruefe('C7c - nach dem 3. Fehlschlag (Netzfehler) nur noch EIN Zeitgeber',
		1 === offen.length, 'offen=' + JSON.stringify(offen));
	zwischen('C7d - und der Herzschlag ist auf takt=10 s zurueck',
		Math.round(offen[0].ms), 7500, 12500);
	pruefe('C7e - eine sichtbare Warnung, nicht hinter cbdDebug',
		1 === u.warnungen.length && u.warnungen[0].indexOf('Pulsdatei') > -1,
		JSON.stringify(u.warnungen));
	gleich('C7f - genau drei Dateiabrufe, danach keiner mehr',
		u.abrufe.datei.length, 3);

	// --- nach dem Rueckfall bleibt der Puls ARBEITSFAEHIG -----------------
	//
	// Nicht nur „es gibt noch Routenabrufe": Eine echte Signaturaenderung
	// muss weiterhin bei den Abonnenten ankommen. Ohne diese Zusicherung
	// waere der Rueckfall wertlos.
	const nachher = [];
	u.w.cbdKlassenpuls.abonniere('klasse', function (neu, alt) {
		nachher.push(alt + '->' + neu);
	});
	const routeVorher = u.abrufe.route.length;
	await u.tick();

	pruefe('C8 - der Herzschlag laeuft weiter (ein weiterer Routenabruf)',
		u.abrufe.route.length === routeVorher + 1,
		'vorher=' + routeVorher + ' nachher=' + u.abrufe.route.length);
	gleich('C8b - und eine echte Aenderung wird weiterhin gemeldet',
		nachher, ['k0->NACHHER']);
	gleich('C8c - immer noch keine weiteren Dateiabrufe', u.abrufe.datei.length, 3);

	// --- takt 0 haelt BEIDE Zeitgeber an ----------------------------------
	u = baue({
		route: [routeAntwort(), routeAntwort({ takt: 0 })],
		datei: vieleDateiAntworten(60)
	});
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	pruefe('C9 - vorher zwei Zeitgeber', 2 === u.offene().length);

	while (u.abrufe.route.length < 2 && await u.tick()) { /* bis der Herzschlag feuert */ }

	gleich('C9b - nach takt 0 kein Zeitgeber mehr', u.offene().length, 0);
	pruefe('C9c - und laeuft() ist false', false === u.w.cbdKlassenpuls.laeuft());

	// --- verstecktes Fenster ----------------------------------------------
	u = baue({ route: [routeAntwort()] });
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	pruefe('C10 - sichtbar: zwei Zeitgeber', 2 === u.offene().length);

	u.verstecke(true);
	gleich('C10b - versteckt: kein Zeitgeber', u.offene().length, 0);

	u.verstecke(false);
	await u.warte(); await u.warte();
	pruefe('C10c - wieder sichtbar: beide Zeitgeber zurueck',
		2 === u.offene().length, 'offen=' + JSON.stringify(u.offene()));

	// --- Sitzungswechsel --------------------------------------------------
	u = baue({
		route: [routeAntwort(), routeAntwort()],
		datei: [{ status: 404 }, { status: 404 }, { status: 404 }]
	});
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok-a');
	await u.warte(); await u.warte();
	await u.tick(); await u.tick(); await u.tick();
	gleich('C11 - Dateimodus nach drei Fehlschlaegen aus', u.offene().length, 1);

	u.w.cbdKlassenpuls.setzeSitzung(16, 'tok-b');
	await u.warte(); await u.warte();

	// Der Sitzungswechsel allein bringt die Datei NICHT zurueck: `dateiUrl`
	// wird dabei verworfen und kommt ausschliesslich aus der Antwort der
	// Route. Genau das ist gewollt — die Adresse gehoert zur Klasse.
	gleich('C11b - direkt nach dem Wechsel noch kein Dateizeitgeber',
		u.offene().filter(function (o) { return o.ms < 5000; }).length, 0);

	await u.tick();
	pruefe('C11c - die naechste Antwort der Route startet den Dateimodus neu',
		2 === u.offene().length, 'offen=' + JSON.stringify(u.offene()));

	// --- Sitzungswechsel bei LAUFENDEM Dateimodus -------------------------
	//
	// Der Grund, warum `setzeSitzung()` den Herzschlag neu plant. Im
	// Dateimodus steht der Herzschlag auf 60 s, und `starte()` kehrt bei
	// bereits laufendem Taktgeber sofort zurueck — ohne die Neuplanung bliebe
	// die neue Sitzung bis zu eine Minute lang voellig unabgefragt.
	u = baue({ route: [routeAntwort()] });
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok-a');
	await u.warte(); await u.warte();
	zwischen('C12 - im Dateimodus taktet der Herzschlag mit 60 s',
		Math.round(u.offene().filter(function (o) { return o.ms > 5000; })[0].ms),
		45000, 75000);

	u.w.cbdKlassenpuls.setzeSitzung(17, 'tok-c');
	await u.warte(); await u.warte();
	offen = u.offene();
	gleich('C12b - der schnelle Zeitgeber ist weg', offen.length, 1);
	zwischen('C12c - und der Herzschlag steht SOFORT wieder auf takt=10 s',
		Math.round(offen[0].ms), 7500, 12500);

	// --- herzschlag fehlt -> Rueckfall 60 --------------------------------
	u = baue({ route: [routeAntwort({ herzschlag: null })] });
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	zwischen('C13 - fehlender herzschlag faellt auf 60 s zurueck',
		Math.round(u.offene()[1].ms), 45000, 75000);

	// --- takt groesser als herzschlag ------------------------------------
	u = baue({ route: [routeAntwort({ takt: 300, herzschlag: 60 })] });
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	zwischen('C14 - takt 300 schlaegt herzschlag 60 (Math.max)',
		Math.round(u.offene()[1].ms), 225000, 375000);

	// --- keine zwei gleichzeitigen Dateiabfragen --------------------------
	u = baue({ route: [routeAntwort()], datei: [] });
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	u.w.cbdKlassenpuls.sofort();
	u.w.cbdKlassenpuls.sofort();
	u.w.cbdKlassenpuls.sofort();
	await u.warte();
	gleich('C15 - drei sofort() ergeben hoechstens einen offenen Dateiabruf',
		u.abrufe.datei.length, 1);

	// --- abgelaufene Sitzung raeumt BEIDE Zeitgeber ab -------------------
	//
	// Befund G4 aus `AP-2.rev`: Eine Mutation, die in `sitzungAbgelaufen()`
	// nur den Routen-Zeitgeber loescht, ueberlebte beide Harnische. Sie ist
	// harmlos — `dateiModusAktiv()` prueft `endgueltigGestoppt` unabhaengig,
	// es wird also trotzdem nicht mehr abgefragt —, aber ein Zeitgeber, der
	// nach dem endgueltigen Stopp noch eingeplant ist, gehoert nicht dorthin.
	u = baue({
		route: [routeAntwort(), { status: 404, daten: { code: 'x' } }],
		datei: vieleDateiAntworten(60)
	});
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok');
	await u.warte(); await u.warte();
	pruefe('C16 - vor dem Ablauf zwei Zeitgeber', 2 === u.offene().length);

	while (u.abrufe.route.length < 2 && await u.tick()) { /* bis der 404 kommt */ }

	gleich('C16b - nach dem 404 ist KEIN Zeitgeber mehr eingeplant',
		u.offene().length, 0);
	const dateiNachAblauf = u.abrufe.datei.length;
	await u.tick(5);
	gleich('C16c - und es folgt kein weiterer Dateiabruf',
		u.abrufe.datei.length, dateiNachAblauf);

	// --- halte() dann setzeSitzung() mit DENSELBEN Werten ----------------
	//
	// Befund G5 aus `AP-2.rev`: Genau die Zeile, fuer die AP-2.2 vom
	// Plantext abgewichen ist (`dateiZeitgeberAbgleichen()` in `starte()`),
	// war ungeprueft. Der Fall ist nicht hypothetisch: `handleLogout()` in
	// `classroom-frontend.js` ruft `halte()`, und Regel 2 im Kopfkommentar
	// beschreibt ausdruecklich den Aufruf von `setzeSitzung()` mit
	// UNVERAENDERTEN Werten durch die Fragenwand. Dabei ueberspringt
	// `setzeSitzung()` seinen Ruecksetzblock, und `starte()` plant von sich
	// aus nur den Routen-Zeitgeber — der schnelle kaeme sonst erst mit dem
	// naechsten Herzschlag zurueck, also bis zu 60 s spaeter.
	u = baue({ route: [routeAntwort()], datei: vieleDateiAntworten(10) });
	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok-gleich');
	await u.warte(); await u.warte();
	pruefe('C17 - Dateimodus laeuft', 2 === u.offene().length);

	u.w.cbdKlassenpuls.halte();
	gleich('C17b - halte() raeumt beide Zeitgeber ab', u.offene().length, 0);

	u.w.cbdKlassenpuls.setzeSitzung(15, 'tok-gleich');
	await u.warte(); await u.warte();
	offen = u.offene();
	pruefe('C17c - setzeSitzung() mit denselben Werten bringt BEIDE zurueck',
		2 === offen.length, 'offen=' + JSON.stringify(offen));
	zwischen('C17d - der schnelle Zeitgeber ist wirklich der schnelle',
		Math.round(offen[0].ms), 2000, 3000);
}

// =========================================================================

async function main() {
	console.log('Harnisch fuer ' + path.basename(QUELLDATEI)
		+ (process.env.CBD_PULS_DATEI ? '  (Pfad aus CBD_PULS_DATEI)' : ''));

	await gruppeA();
	gruppeB();
	await gruppeC();

	console.log('\n----------------------------------------');
	console.log('gruen: ' + gruen + '   Fehler: ' + fehlschlaege);
	console.log('\n' + (0 === fehlschlaege ? 'ALLE TESTS BESTANDEN'
		: fehlschlaege + ' FEHLER'));
	process.exit(0 === fehlschlaege ? 0 : 1);
}

main().catch(function (fehler) {
	console.error('Harnisch abgebrochen:', fehler);
	process.exit(1);
});
