/**
 * Prueft die getrennten Mindestabstaende in `classroom-page-filter.js`
 * (Vorhaben „Schneller Klassenpuls", AP-3.2).
 *
 * WARUM ES DIESEN HARNISCH GIBT. Auf serverseitig reduzierten („gesperrten")
 * Seiten loest eine Aenderung kein Live-Einblenden aus, sondern ein
 * Neuladen. Damit eine zeichnende Lehrperson die Schuelerseite nicht im
 * Sekundentakt wegzieht, gibt es einen Mindestabstand. Bis AP-3.2 war das
 * EIN Wert von 60 Sekunden fuer jeden Anlass - und damit geriet die Freigabe
 * in Sippenhaft fuer das Zeichnen: Eine Freigabe, die kurz nach einem
 * Tafelbild eintraf, erschien beim Schueler erst nach 55 750 ms. Genau das
 * Ereignis, um dessen Beschleunigung es im ganzen Vorhaben geht, war auf
 * gesperrten Seiten am langsamsten.
 *
 * Seither gilt `ABSTAND_FREIGABE_MS` fuer `'freigabe'` und
 * `MINDESTABSTAND_MS` fuer alles andere. Dieser Harnisch haelt beide Haelften
 * fest: dass eine Freigabe schnell durchkommt UND dass ein Tafelbild
 * weiterhin lange wartet (Fall G, der Gegenbeweis). Ohne den Gegenbeweis
 * waere ein Harnisch gruen, der schlicht jeden Abstand abgeschafft hat.
 *
 * WIE ER MISST. `ladeNeu()` wird aus der AUSGELIEFERTEN Datei
 * herausgeschnitten und gegen eine VIRTUELLE UHR gefahren. Die Funktion
 * haengt nur an `Date.now`, `window.setTimeout`, den beiden Konstanten,
 * `this.letzteNeuladung`, `this.vorgemerkterGrund`, `this.abstandZeitgeber`,
 * `this.abstandFaellig` und `this.pruefeFreigabeUndLade()` - alles ersetzbar,
 * keine DOM-Abhaengigkeit. Kopflos, weil die Frage reine Zeitarithmetik ist;
 * eine Minute Warten je Durchgang waere teuer und ungenauer.
 *
 * Aufruf:  node tools/test-classroom-abstand.js
 * Eine andere Datei pruefen (fuer Mutationsproben):
 *          CBD_FILTER_DATEI=<pfad> node tools/test-classroom-abstand.js
 *
 * TRAGENDE MUTATIONEN - dagegen ist der Harnisch geprueft, und zwar
 * gemessen, nicht behauptet (Probe im Scratchpad, veraendert stets nur eine
 * Kopie der Datei):
 *   1. `ABSTAND_FREIGABE_MS` auf 60000 gesetzt          -> 0, C, F sterben
 *   2. Abstand haengt nicht mehr am Grund               -> C, D2, F sterben
 *   3. Vorziehen des spaeteren Zeitgebers entfernt      -> **nur D2 stirbt**
 *   4. `MINDESTABSTAND_MS` auf 5000 gesetzt             -> 0, B, H2 sterben
 *   5. Grund-Verdraengung entfernt                      -> D, D2 sterben
 *
 * Punkt 3 ist der Grund, warum es Fall D2 gibt. Die erste Fassung dieses
 * Harnischs hatte ihn nicht - und die Mutationsprobe zeigte, dass das
 * Entfernen des Vorziehens damals KEINE einzige Pruefung zum Fallen brachte,
 * obwohl ohne diese Zutat eine Freigabe weiterhin bis zu eine Minute
 * gewartet haette. Fall D prueft nur das Etikett der nachgezogenen
 * Neuladung, nicht ihren Zeitpunkt. Wer D2 entfernt, macht das Vorziehen
 * wieder ungeprueft.
 *
 * EINE MUTATION UEBERLEBT ABSICHTLICH, und das ist kein Loch: Faellt die
 * Bedingung `!this.abstandZeitgeber` weg, legt jede Aenderung in der
 * Sperrzeit einen eigenen Zeitgeber an. Mehr Neuladungen entstehen dadurch
 * trotzdem nicht, weil der erste Rueckruf `vorgemerkterGrund` auf `null`
 * setzt und alle Doppelgaenger danach ins Leere laufen. Die Bedingung ist
 * also Guertel zum Hosentraeger, nicht die tragende Sicherung - eine
 * Pruefung dafuer wuerde eine Eigenschaft festschreiben, die der Code gar
 * nicht braucht.
 */
'use strict';

var fs = require('fs');
var path = require('path');
var vm = require('vm');

var CR = String.fromCharCode(13);
var LF = String.fromCharCode(10);

var QUELLDATEI = process.env.CBD_FILTER_DATEI
	|| path.join(__dirname, '..', 'assets', 'js', 'classroom-page-filter.js');

// Git checkt die Datei auf Windows mit CRLF aus, die Suchmuster unten sind
// aber LF-basiert. Ohne diese Normalisierung faende `schneideAus()` nichts
// und der Harnisch stuerbe an einer Kleinigkeit statt an einem Befund -
// dieselbe Falle wie in `tools/test-klassenpuls-js.js`.
var quelle = fs.readFileSync(QUELLDATEI, 'utf8').split(CR + LF).join(LF);

/** `ladeNeu:` bis zur zugehoerigen schliessenden Klammer herausschneiden. */
function schneideLadeNeu() {
	var start = quelle.indexOf('        ladeNeu: function(grund) {');

	if (start < 0) { throw new Error('ladeNeu() nicht gefunden'); }

	var ende = quelle.indexOf('\n        },\n', start);

	if (ende < 0) { throw new Error('Ende von ladeNeu() nicht gefunden'); }

	// `ende` zeigt auf das \n VOR der schliessenden Klammer der
	// Objekt-Eigenschaft. Der Rumpf endet also dort; die Klammer setzt
	// dieser Harnisch selbst, damit kein `},` aus dem Objektliteral
	// stehenbleibt.
	return quelle.slice(start, ende)
		.replace('ladeNeu: function(grund) {', 'function ladeNeu(grund) {')
		+ '\n}\n';
}

/** Eine Konstante aus der Quelle lesen, statt sie hier zu wiederholen. */
function konstante(name) {
	var m = quelle.match(new RegExp('var\\s+' + name + '\\s*=\\s*(\\d+)\\s*;'));

	if (!m) { throw new Error('Konstante nicht gefunden: ' + name); }

	return parseInt(m[1], 10);
}

var MINDESTABSTAND_MS = konstante('MINDESTABSTAND_MS');
var ABSTAND_FREIGABE_MS = konstante('ABSTAND_FREIGABE_MS');

/**
 * Was der Dateitakt schlimmstenfalls VOR `ladeNeu()` verbraucht.
 *
 * Das Akzeptanzkriterium von AP-3.2 lautet „in unter 10 Sekunden" — und
 * zwar beim SCHUELER. Bis `ladeNeu()` ueberhaupt gerufen wird, hat der
 * Dateitakt die Aenderung schon bemerken muessen: Vorgabe 2 s, nach oben
 * gestreut mit `1 + Zufall * 0,5`, also hoechstens 3000 ms.
 *
 * WARUM DAS HIER STEHT (Befund B3 aus AP-3.rev): Die Faelle C und F
 * prueften urspruenglich gegen die vollen 10 000 ms und uebersahen damit
 * genau diese Reserve. Nachgemessen: `ABSTAND_FREIGABE_MS = 9700` liess
 * alle Pruefungen gruen, obwohl das Kriterium dann in Wirklichkeit
 * gerissen waere. Seither rechnen C und F gegen das echte Budget.
 *
 * Wer den Dateitakt im Backend hochsetzt, verbraucht mehr davon — bei
 * einem eingestellten Takt von 5 s waeren es 7500 ms statt 3000, und die
 * Zusicherung traegt nicht mehr. Sie gilt fuer die Vorgabe.
 */
var DATEI_TAKT_MAX_MS = 3000;
var BUDGET_MS = 10000 - DATEI_TAKT_MAX_MS;

// =====================================================================
// Virtuelle Uhr + Attrappe des Filterobjekts
// =====================================================================

function baue() {
	var jetzt = 0;
	var naechsteId = 1;
	var zeitgeber = [];
	var neuladungen = [];

	var sandbox = {
		MINDESTABSTAND_MS: MINDESTABSTAND_MS,
		ABSTAND_FREIGABE_MS: ABSTAND_FREIGABE_MS,
		console: { log: function () {} },
		Date: { now: function () { return jetzt; } }
	};

	sandbox.window = {
		cbdDebug: false,
		setTimeout: function (fn, ms) {
			var id = naechsteId++;
			zeitgeber.push({ id: id, fn: fn, faellig: jetzt + ms });
			return id;
		},
		clearTimeout: function (id) {
			zeitgeber = zeitgeber.filter(function (z) { return z.id !== id; });
		}
	};

	vm.createContext(sandbox);
	vm.runInContext(schneideLadeNeu() + '\nthis.ladeNeu = ladeNeu;',
		sandbox, { filename: 'ladeNeu.js' });

	var filter = {
		letzteNeuladung: 0,
		vorgemerkterGrund: null,
		abstandZeitgeber: null,
		abstandFaellig: 0,
		/** Ersetzt den echten Freigabe-Check: laedt sofort. */
		pruefeFreigabeUndLade: function (grund) {
			this.letzteNeuladung = jetzt;
			neuladungen.push({ t: jetzt, grund: grund });
		},
		ladeNeu: sandbox.ladeNeu
	};

	return {
		filter: filter,
		neuladungen: neuladungen,
		/** Zeit bis `bis` vorspulen und faellige Zeitgeber ausfuehren. */
		spuleBis: function (bis) {
			while (true) {
				zeitgeber.sort(function (a, b) { return a.faellig - b.faellig; });

				if (!zeitgeber.length || zeitgeber[0].faellig > bis) {
					jetzt = bis;
					return;
				}

				var z = zeitgeber.shift();
				jetzt = z.faellig;
				z.fn.call(filter);
			}
		},
		setze: function (t) { jetzt = t; }
	};
}

// =====================================================================

var gruen = 0;
var rot = 0;

function pruefe(name, bedingung, zusatz) {
	if (bedingung) { gruen++; console.log('  OK   ' + name); }
	else { rot++; console.log('  FEHL ' + name + (zusatz ? '  -> ' + zusatz : '')); }
}

function laden(u) {
	return u.neuladungen.map(function (n) {
		return Math.round(n.t / 1000) + 's/' + n.grund;
	});
}

console.log('Gelesen aus ' + path.basename(QUELLDATEI)
	+ (process.env.CBD_FILTER_DATEI ? '  (Pfad aus CBD_FILTER_DATEI)' : ''));
console.log('  MINDESTABSTAND_MS   = ' + MINDESTABSTAND_MS);
console.log('  ABSTAND_FREIGABE_MS = ' + ABSTAND_FREIGABE_MS);

pruefe('0 - der Freigabe-Abstand ist kuerzer als der allgemeine',
	ABSTAND_FREIGABE_MS < MINDESTABSTAND_MS,
	ABSTAND_FREIGABE_MS + ' vs ' + MINDESTABSTAND_MS);

pruefe('0b - Abstand + Zeitgeberzuschlag + Dateitakt bleiben unter 10 s',
	ABSTAND_FREIGABE_MS + 250 + DATEI_TAKT_MAX_MS < 10000,
	(ABSTAND_FREIGABE_MS + 250 + DATEI_TAKT_MAX_MS) + ' ms');

console.log('\n--- A: Freigabe ohne laufende Sperrzeit ---');
var u = baue();
u.setze(1000);
u.filter.ladeNeu.call(u.filter, 'freigabe');
u.spuleBis(120000);
pruefe('A - Freigabe laedt sofort neu',
	1 === u.neuladungen.length && 1000 === u.neuladungen[0].t,
	JSON.stringify(laden(u)));

console.log('\n--- B: durchgehendes Zeichnen, 20 Speicherungen in 60 s ---');
u = baue();
u.setze(0);
for (var i = 0; i < 20; i++) {
	u.spuleBis(i * 3000 + 2500);
	u.filter.ladeNeu.call(u.filter, 'tafel');
}
u.spuleBis(70000);
pruefe('B - hoechstens 2 Neuladungen in 70 s (Messwert AP-2.3: 1 je Minute)',
	u.neuladungen.length <= 2, 'n=' + u.neuladungen.length + ' ' + JSON.stringify(laden(u)));

console.log('\n--- C: Freigabe faellt in eine laufende Sperrzeit ---');
u = baue();
u.setze(0);
u.spuleBis(2500);
u.filter.ladeNeu.call(u.filter, 'tafel');
u.spuleBis(7000);
u.filter.ladeNeu.call(u.filter, 'freigabe');
u.spuleBis(200000);
var freigabe = u.neuladungen.filter(function (n) { return 'freigabe' === n.grund; })[0];
var wartezeit = freigabe ? freigabe.t - 7000 : null;
console.log('  Wartezeit: ' + wartezeit + ' ms  (vor AP-3.2: 55 750 ms)');
pruefe('C - Freigabe in unter 10 s beim Schueler (Budget ohne Dateitakt)',
	null !== wartezeit && wartezeit < BUDGET_MS,
	'wartezeit=' + wartezeit + ' ms, Budget ' + BUDGET_MS);

console.log('\n--- D/E: Freigabe verdraengt ein vorgemerktes Tafelbild ---');
u = baue();
u.setze(0);
u.spuleBis(2500);
u.filter.ladeNeu.call(u.filter, 'tafel');
u.spuleBis(5000);
u.filter.ladeNeu.call(u.filter, 'tafel');
u.spuleBis(6000);
u.filter.ladeNeu.call(u.filter, 'freigabe');
u.spuleBis(200000);
var zweite = u.neuladungen[1];
pruefe('D - die nachgezogene Neuladung traegt den Grund "freigabe"',
	zweite && 'freigabe' === zweite.grund,
	zweite ? zweite.grund : 'keine zweite Neuladung');
pruefe('E - jede Aenderung in der Sperrzeit wird genau einmal nachgezogen',
	2 === u.neuladungen.length, 'n=' + u.neuladungen.length);

// Der Grund allein genuegt NICHT. Faellt eine Freigabe in eine Sperrzeit,
// in der bereits ein Tafelbild vorgemerkt ist, laeuft dessen Zeitgeber
// schon - und zwar auf bis zu eine Minute. Wird er nicht VORGEZOGEN, feuert
// er weiterhin spaet, nur eben mit dem Grund 'freigabe': Der Schueler
// wartet dieselbe Minute, und Fall D sieht trotzdem gruen aus, weil er nur
// das Etikett prueft. Ohne diesen Fall ueberlebt genau diese Mutation.
var freigabeD = u.neuladungen.filter(function (n) { return 'freigabe' === n.grund; })[0];
var wartenD = freigabeD ? freigabeD.t - 6000 : null;
console.log('  Wartezeit der verdraengenden Freigabe: ' + wartenD + ' ms');
pruefe('D2 - sie wartet auch dann unter 10 s, wenn schon ein '
	+ 'Tafelbild-Zeitgeber laeuft',
	null !== wartenD && wartenD < BUDGET_MS, 'wartezeit=' + wartenD);

console.log('\n--- F: schlechtester Fall (Freigabe direkt nach einem Neuladen) ---');
u = baue();
u.setze(0);
u.spuleBis(2500);
u.filter.ladeNeu.call(u.filter, 'tafel');
u.filter.ladeNeu.call(u.filter, 'freigabe');
u.spuleBis(200000);
var fr = u.neuladungen.filter(function (n) { return 'freigabe' === n.grund; })[0];
var w = fr ? fr.t - 2500 : null;
console.log('  Wartezeit: ' + w + ' ms');
pruefe('F - auch im schlechtesten Fall unter 10 s (Budget ohne Dateitakt)',
	null !== w && w < BUDGET_MS, 'wartezeit=' + w + ' ms, Budget ' + BUDGET_MS);

console.log('\n--- G: DER GEGENBEWEIS - eine Freigabe verkuerzt den ---');
console.log('    Tafelbild-Abstand NICHT. Ohne diesen Fall waere auch ein');
console.log('    Harnisch gruen, der jeden Abstand abgeschafft hat.');
u = baue();
u.setze(0);
u.spuleBis(1000);
u.filter.ladeNeu.call(u.filter, 'freigabe');
u.spuleBis(2000);
u.filter.ladeNeu.call(u.filter, 'tafel');
u.spuleBis(200000);
var ta = u.neuladungen.filter(function (n) { return 'tafel' === n.grund; })[0];
var wt = ta ? ta.t - 1000 : null;
console.log('  Tafelbild erscheint ' + wt + ' ms nach dem letzten Neuladen');
pruefe('G - das Tafelbild wartet weiterhin rund 60 s',
	null !== wt && wt >= MINDESTABSTAND_MS && wt <= MINDESTABSTAND_MS + 1000,
	'abstand=' + wt);

console.log('\n--- H: Dauerzeichnen MIT einer Freigabe dazwischen ---');
u = baue();
u.setze(0);
for (var j = 0; j < 20; j++) {
	u.spuleBis(j * 3000 + 2500);
	u.filter.ladeNeu.call(u.filter, 8 === j ? 'freigabe' : 'tafel');
}
u.spuleBis(90000);
var frH = u.neuladungen.filter(function (n) { return 'freigabe' === n.grund; });
pruefe('H - die Freigabe zwischen 20 Pinselstrichen kommt durch',
	1 === frH.length, 'n=' + frH.length + ' ' + JSON.stringify(laden(u)));
pruefe('H2 - und die Gesamtzahl bleibt klein (hoechstens 4 in 90 s)',
	u.neuladungen.length <= 4, 'n=' + u.neuladungen.length);

console.log('\n--- I: der vorgemerkte Grund wird nach dem Nachziehen ---');
console.log('    wirklich zurueckgesetzt.');
// Ohne `self.vorgemerkterGrund = null` im Zeitgeber-Rueckruf bleibt der
// alte Grund stehen. Die Bedingung `grund === 'freigabe' ||
// !this.vorgemerkterGrund` laesst ein spaeteres 'tafel' dann NICHT mehr
// durch - die naechste nachgezogene Neuladung traegt faelschlich
// weiterhin 'freigabe'. Befund B7/R8 aus AP-3.rev: Diese Luecke hat jede
// andere Pruefung ueberlebt, obwohl auf genau dieser Zeile die
// Begruendung ruht, warum die Zeitgeber-Sperre entbehrlich sein darf.
u = baue();
u.setze(0);
u.spuleBis(2500);
u.filter.ladeNeu.call(u.filter, 'tafel');        // laedt sofort
u.spuleBis(4000);
u.filter.ladeNeu.call(u.filter, 'freigabe');     // wird zurueckgestellt
u.spuleBis(8000);                                // Nachzug ist gelaufen
u.filter.ladeNeu.call(u.filter, 'tafel');        // neue Sperrzeit
u.spuleBis(200000);
var dritte = u.neuladungen[2];
console.log('  Neuladungen: ' + JSON.stringify(laden(u)));
pruefe('I - die dritte Neuladung traegt "tafel", nicht den alten Grund',
	dritte && 'tafel' === dritte.grund,
	dritte ? dritte.grund : 'keine dritte Neuladung');

console.log('\n----------------------------------------');
console.log('gruen: ' + gruen + '   rot: ' + rot);
process.exit(rot > 0 ? 1 : 0);
