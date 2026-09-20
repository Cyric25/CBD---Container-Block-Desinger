/**
 * Prueft, dass die Datei-Map `reference_file_map.md` vollstaendig gerendert
 * wird - dass also keine Zeile Text an GitHub-Flavored Markdown verliert.
 *
 * WARUM ES DIESEN HARNISCH GIBT. GFM zerlegt eine Tabellenzeile an JEDER
 * nicht maskierten Pipe. Auch an einer, die innerhalb von Backticks steht:
 * Die Tabellenzerlegung laeuft VOR der Inline-Auswertung, ein Code-Span
 * schuetzt hier nichts. Zellen jenseits der Spaltenzahl der Kopfzeile
 * werden anschliessend ersatzlos VERWORFEN - ohne Warnung, ohne Luecke im
 * Layout, ohne dass beim Schreiben etwas auffaellt. Wer in einer
 * zweispaltigen Tabelle ein Code-Beispiel mit `implode('|', ...)` notiert,
 * hat den Rest der Zeile gerade unsichtbar gemacht.
 *
 * Das ist kein gedachtes Risiko, sondern zweimal gemessen worden, beide
 * Male an der echten GitHub-Darstellung, nicht am Parser hier:
 *
 *   - AP-3.2 des Vorhabens „Schneller Klassenpuls": Die Zeile zu
 *     `classroom-admin.js` rendete 7 779 von 28 448 Zeichen, der Rest fiel
 *     hinten herunter. Aufgefallen ist es nur, weil ein frisch angehaengter
 *     Absatz spurlos verschwand.
 *   - Der Durchgang danach: elf weitere Zeilen, zusammen 30 150 unsichtbare
 *     Zeichen. Die groesste (`pdf-server-side.js`) zeigte 1 334 statt
 *     17 748 Zeichen - 93 Prozent der Zeile waren weg.
 *
 * Zusammen waren das ueber 50 000 Zeichen, rund ein Viertel der Datei.
 * Niemand haette das an der Datei selbst gesehen; im Editor steht alles da.
 * Genau deshalb gehoert die Pruefung in den Harnisch und nicht ins
 * Scratchpad: Sie faengt einen Fehler ab, den das blosse Lesen der Quelle
 * nicht zeigt.
 *
 * WIE ER PRUEFT. Er bildet die Zerlegungsregel von GFM nach (Fall A bis E)
 * und beweist an einer synthetischen Tabelle, dass er dabei ueberhaupt rot
 * werden kann (Fall F, der Gegenbeweis). Ohne Fall F waere ein Harnisch
 * gruen, der gar nicht hinsieht.
 *
 * DIE REGEL, falls doch einmal etwas rot wird:
 *   1. Pipe im Zelleninhalt -> als `\|` maskieren. GFM ersetzt `\|` vor der
 *      Inline-Auswertung durch `|`, das wirkt also auch in Backticks.
 *   2. Zeile hat mehr Zellen als die Kopfzeile Spalten -> den ueberzaehligen
 *      Inhalt in den Flusstext der letzten gueltigen Zelle holen, NICHT
 *      loeschen. Der Text ist Dokumentation, keine Formatierung.
 *
 * Aufruf:  node tools/test-datei-map-pipes.js
 * Eine andere Datei pruefen (fuer Mutationsproben oder eine zweite Map):
 *          CBD_MAP_DATEI=<pfad> node tools/test-datei-map-pipes.js
 *
 * TRAGENDE MUTATIONEN - dagegen ist der Harnisch geprueft, und zwar
 * gemessen, nicht behauptet. Die Probe veraendert stets nur eine Kopie der
 * Datei im Scratchpad, nie das Original; alle fuenf sterben:
 *   1. eine Maskierung `\|` wieder zu `|` gemacht    -> B, D, E sterben
 *   2. eine zusaetzliche Pipe in einen Backtick-Span -> B, D, E sterben
 *   3. eine Pipe in Fliesstext ohne Backticks        -> B, E sterben
 *      D nicht, und das ist richtig so: D sieht nur in Code-Spans nach.
 *      Genau deshalb gibt es B und E daneben - sie fangen die Pipe
 *      unabhaengig davon, wo in der Zeile sie steht.
 *   4. ein echter Spaltentrenner entfernt            -> C stirbt
 *   5. ein Backtick entfernt                         -> D und G sterben
 *      D deshalb, weil ein fehlender Backtick die Paritaet kippt: Was
 *      vorher Prosa war, zaehlt danach als Code-Span - und ein echter
 *      Spaltentrenner steht dann scheinbar mitten im Code.
 */
'use strict';

var fs = require('fs');
var path = require('path');

var CR = String.fromCharCode(13);
var LF = String.fromCharCode(10);
var BS = String.fromCharCode(92);
var TICK = String.fromCharCode(96);
var PIPE = '|';

var QUELLDATEI = process.env.CBD_MAP_DATEI
	|| path.join(__dirname, '..', 'reference_file_map.md');

// Git checkt die Datei auf Windows mit CRLF aus. Ohne diese Normalisierung
// haenge das CR am letzten Zellinhalt und jede Laengenangabe waere um eins
// daneben - dieselbe Falle wie in `tools/test-classroom-abstand.js`.
var zeilen = fs.readFileSync(QUELLDATEI, 'utf8').split(CR + LF).join(LF)
	.split(LF);

// =====================================================================
// Die Zerlegungsregel von GFM, nachgebildet
// =====================================================================

/**
 * Zeile an nicht maskierten Pipes zerlegen, wie GFM es tut: Ein Backslash
 * deckt das FOLGENDE Zeichen ab, egal ob es in Backticks steht.
 */
function zellen(zeile) {
	var teile = [];
	var puffer = '';
	var i = 0;

	while (i < zeile.length) {
		var c = zeile.charAt(i);

		if (BS === c && i + 1 < zeile.length) {
			puffer += zeile.substr(i, 2);
			i += 2;
			continue;
		}

		if (PIPE === c) {
			teile.push(puffer);
			puffer = '';
			i += 1;
			continue;
		}

		puffer += c;
		i += 1;
	}

	teile.push(puffer);

	// Die leeren Zellen der Randpipes zaehlen nicht mit.
	if (teile.length && '' === teile[0].trim()) { teile = teile.slice(1); }

	if (teile.length && '' === teile[teile.length - 1].trim()) {
		teile = teile.slice(0, -1);
	}

	return teile;
}

/** Ist das die Trennzeile einer Tabelle (`|---|---|`)? */
function istTrennzeile(zeile) {
	var s = zeile.trim();

	if (0 !== s.indexOf(PIPE)) { return false; }

	var kern = s.split(PIPE).join('').split(' ').join('');

	if (kern.length !== kern.replace(/[^-:]/g, '').length) { return false; }

	return kern.indexOf('-') >= 0;
}

/** Positionen nicht maskierter Pipes innerhalb eines Backtick-Spans. */
function pipesImCode(zeile) {
	var fund = [];
	var imCode = false;
	var i = 0;

	while (i < zeile.length) {
		var c = zeile.charAt(i);

		if (BS === c && i + 1 < zeile.length) { i += 2; continue; }

		if (TICK === c) { imCode = !imCode; i += 1; continue; }

		if (PIPE === c && imCode) { fund.push(i); }

		i += 1;
	}

	return fund;
}

/**
 * Die Tabellenzeilen einer Datei, getrennt nach Kopf und Inhalt.
 *
 * Die Kopfzeile erkennt man daran, dass die NAECHSTE Zeile die Trennzeile
 * ist; sie selbst steht noch vor der Trennzeile und kennt ihre Spaltenzahl
 * deshalb noch nicht. Ohne diese Unterscheidung wuerde jede Kopfzeile als
 * „Tabellenzeile ohne Kopf" gemeldet - ein Fehlalarm je Tabelle.
 */
function tabellenzeilen(alleZeilen) {
	var inhalt = [];
	var koepfe = [];
	var spalten = null;
	var n;

	for (n = 0; n < alleZeilen.length; n++) {
		var z = alleZeilen[n];

		if (istTrennzeile(z)) { spalten = zellen(z).length; continue; }

		if (0 !== z.trim().indexOf(PIPE)) { spalten = null; continue; }

		if (n + 1 < alleZeilen.length && istTrennzeile(alleZeilen[n + 1])) {
			koepfe.push({ nummer: n + 1, zeile: z,
				spalten: zellen(alleZeilen[n + 1]).length });
			continue;
		}

		inhalt.push({ nummer: n + 1, zeile: z, spalten: spalten });
	}

	return { inhalt: inhalt, koepfe: koepfe };
}

/**
 * Der eigentliche Befund einer Datei: welche Zeilen Zellen verlieren,
 * welche zu wenige haben, wo eine Pipe im Code steht, wo Backticks
 * ungerade sind - und wie viele Zeichen unsichtbar bleiben.
 */
function befund(alleZeilen) {
	var geteilt = tabellenzeilen(alleZeilen);
	var e = {
		zeilen: 0, tabellen: geteilt.koepfe.length, ohneKopf: 0,
		kopfSchief: [], zuViele: [], zuWenige: [],
		imCode: [], ungeradeTicks: [], verloren: 0
	};

	// Die Kopfzeile muss so viele Zellen haben wie ihre Trennzeile Spalten.
	geteilt.koepfe.forEach(function (k) {
		var ist = zellen(k.zeile).length;

		if (ist !== k.spalten) {
			e.kopfSchief.push({ nummer: k.nummer, ist: ist, soll: k.spalten });
		}
	});

	// Pipes und Backticks gelten auch in der Kopfzeile.
	geteilt.koepfe.concat(geteilt.inhalt).forEach(function (t) {
		var pc = pipesImCode(t.zeile);

		if (pc.length) {
			e.imCode.push({ nummer: t.nummer, anzahl: pc.length,
				stelle: t.zeile.substr(Math.max(0, pc[0] - 40), 80) });
		}

		var ticks = t.zeile.split(TICK).length - 1;

		if (ticks % 2) {
			e.ungeradeTicks.push({ nummer: t.nummer, anzahl: ticks });
		}
	});

	geteilt.inhalt.forEach(function (t) {
		e.zeilen += 1;

		if (null === t.spalten) { e.ohneKopf += 1; return; }

		var teile = zellen(t.zeile);

		if (teile.length > t.spalten) {
			var weg = 0;

			teile.slice(t.spalten).forEach(function (x) { weg += x.length; });
			e.verloren += weg;
			e.zuViele.push({ nummer: t.nummer, ist: teile.length,
				soll: t.spalten, weg: weg, kopf: teile[0].trim().slice(0, 44) });
		} else if (teile.length < t.spalten) {
			e.zuWenige.push({ nummer: t.nummer, ist: teile.length,
				soll: t.spalten, kopf: teile.length ? teile[0].trim().slice(0, 44) : '' });
		}
	});

	return e;
}

// =====================================================================
// Pruefungen
// =====================================================================

var gruen = 0;
var rot = 0;

function pruefe(name, bedingung, zusatz) {
	if (bedingung) { gruen++; console.log('  OK   ' + name); }
	else { rot++; console.log('  FEHL ' + name + (zusatz ? '  -> ' + zusatz : '')); }
}

function liste(eintraege, mach) {
	return eintraege.slice(0, 6).map(mach).join('; ')
		+ (eintraege.length > 6 ? ' ...' : '');
}

console.log('Gelesen aus ' + path.basename(QUELLDATEI)
	+ (process.env.CBD_MAP_DATEI ? '  (Pfad aus CBD_MAP_DATEI)' : ''));

var b = befund(zeilen);

console.log('\n--- A: die Datei sieht ueberhaupt nach Tabellen aus ---');
console.log('  ' + b.tabellen + ' Tabellen, ' + b.zeilen
	+ ' Inhaltszeilen, ' + zeilen.length + ' Zeilen gesamt');
// Absichtlich nur „mehr als nichts": Diese Pruefung soll den stummen
// Totalausfall fangen (falscher Pfad, CRLF nicht normalisiert, Datei ohne
// Tabellen) - nicht die Groesse einer bestimmten Map festschreiben. Eine
// feste Untergrenze stand hier zuerst und schlug prompt bei der kleineren
// Datei-Map des Themes an, obwohl mit der nichts verkehrt war.
pruefe('A - es gibt Tabellen mit Inhaltszeilen zu pruefen',
	b.tabellen > 0 && b.zeilen > 0,
	b.tabellen + ' Tabellen, ' + b.zeilen + ' Inhaltszeilen');
pruefe('A2 - jede Tabellenzeile hat eine Kopfzeile ueber sich',
	0 === b.ohneKopf, b.ohneKopf + ' ohne');
pruefe('A3 - jede Kopfzeile hat so viele Zellen wie ihre Trennzeile',
	0 === b.kopfSchief.length,
	liste(b.kopfSchief, function (x) {
		return 'Zeile ' + x.nummer + ': ' + x.ist + '/' + x.soll;
	}));

console.log('\n--- B: keine Zeile verliert Zellen an GFM ---');
console.log('    Der Kernfall. Mehr Zellen als Spalten heisst: der Rest');
console.log('    der Zeile wird beim Rendern ersatzlos weggeworfen.');
pruefe('B - keine Zeile hat mehr Zellen als ihre Kopfzeile Spalten',
	0 === b.zuViele.length,
	liste(b.zuViele, function (x) {
		return 'Zeile ' + x.nummer + ': ' + x.ist + '/' + x.soll
			+ ', ' + x.weg + ' Zeichen weg (' + x.kopf + ')';
	}));

console.log('\n--- C: keine Zeile hat zu WENIGE Zellen ---');
console.log('    Verliert nichts, verschiebt aber alles um eine Spalte.');
pruefe('C - keine Zeile bleibt unter der Spaltenzahl ihrer Kopfzeile',
	0 === b.zuWenige.length,
	liste(b.zuWenige, function (x) {
		return 'Zeile ' + x.nummer + ': ' + x.ist + '/' + x.soll
			+ ' (' + x.kopf + ')';
	}));

console.log('\n--- D: keine nicht maskierte Pipe in einem Backtick-Span ---');
console.log('    Die Falle, die den Fehler ueberhaupt erst erzeugt: Ein');
console.log('    Code-Span schuetzt eine Pipe NICHT vor der Zerlegung.');
pruefe('D - jede Pipe in einem Code-Span ist als ' + BS + '| maskiert',
	0 === b.imCode.length,
	liste(b.imCode, function (x) {
		return 'Zeile ' + x.nummer + ' (' + x.anzahl + 'x): ...' + x.stelle + '...';
	}));

console.log('\n--- E: kein Zeichen der Datei bleibt unsichtbar ---');
console.log('  unsichtbar: ' + b.verloren + ' Zeichen');
pruefe('E - null verworfene Zeichen', 0 === b.verloren,
	b.verloren + ' Zeichen in ' + b.zuViele.length + ' Zeilen');

console.log('\n--- F: DER GEGENBEWEIS - der Pruefer kann rot werden ---');
console.log('    Ohne diesen Fall waere ein Harnisch gruen, der die Regel');
console.log('    gar nicht nachbildet. Geprueft an einer erfundenen');
console.log('    Tabelle, die echte Datei wird dabei nicht angefasst.');

// Alles ab der rohen Pipe faellt bei GFM hinten herunter - der erwartete
// Verlust ist also genau dieser Rest, nicht nur der Text hinter dem
// Code-Span. Die Zahl steht deshalb nicht als Literal da, sondern wird aus
// demselben Stueck Text gebildet, aus dem auch die Probezeile entsteht.
var ROH_VOR = '| ' + TICK + 'a.js' + TICK + ' | Text mit '
	+ TICK + 'implode(' + "'";
var ROH_NACH = "'" + ')' + TICK + ' und Nachsatz ';

var probe = [
	'| Datei | Zweck |',
	'|---|---|',
	ROH_VOR + PIPE + ROH_NACH + PIPE,
	'| ' + TICK + 'b.js' + TICK + ' | sauber, ohne Pipe |',
	'| ' + TICK + 'c.js' + TICK + ' | maskiert: '
		+ TICK + 'a' + BS + PIPE + 'b' + TICK + ' und Nachsatz |'
];
var p = befund(probe);

pruefe('F - die erfundene Zeile mit roher Pipe faellt auf',
	1 === p.zuViele.length && 3 === p.zuViele[0].nummer,
	JSON.stringify(p.zuViele));
pruefe('F2 - und zwar mit der richtigen Zahl unsichtbarer Zeichen',
	p.verloren === ROH_NACH.length,
	'verloren=' + p.verloren + ', erwartet ' + ROH_NACH.length);
pruefe('F3 - die Pipe im Code-Span wird als solche erkannt',
	1 === p.imCode.length && 3 === p.imCode[0].nummer,
	JSON.stringify(p.imCode));
pruefe('F4 - die maskierte Zeile bleibt unbeanstandet',
	0 === p.zuViele.filter(function (x) { return 5 === x.nummer; }).length
		&& 0 === p.imCode.filter(function (x) { return 5 === x.nummer; }).length,
	'Zeile 5 der Probe');
pruefe('F5 - die Zeile ganz ohne Pipe bleibt unbeanstandet',
	0 === p.zuViele.filter(function (x) { return 4 === x.nummer; }).length,
	'Zeile 4 der Probe');

console.log('\n--- G: Backticks paarweise ---');
console.log('    Verwandter stiller Fehler: Ein einzelner Backtick oeffnet');
console.log('    einen Code-Span, der den Rest der Zeile verschluckt.');
pruefe('G - jede Tabellenzeile hat eine gerade Zahl Backticks',
	0 === b.ungeradeTicks.length,
	liste(b.ungeradeTicks, function (x) {
		return 'Zeile ' + x.nummer + ': ' + x.anzahl;
	}));

console.log('\n----------------------------------------');
console.log('gruen: ' + gruen + '   rot: ' + rot);
process.exit(rot > 0 ? 1 : 0);
