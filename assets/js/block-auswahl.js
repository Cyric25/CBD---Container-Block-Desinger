/**
 * Container Block Designer - Gemeinsamer Auswahlbaustein fuer Zielbloecke
 *
 * KEIN BUILD-SCHRITT: Diese Datei wird unveraendert an den Browser
 * ausgeliefert. Deshalb kein JSX, keine ES-Module-Syntax, keine
 * Klassenfelder - Hausstil ist ES5 mit var/function und IIFE, der Zugriff
 * laeuft ueber die wp.*-Globale, Elemente entstehen ueber
 * wp.element.createElement. Vorbild: blocks/block-reference/index.js
 *
 * Die Abhaengigkeiten (wp-element, wp-components, wp-i18n, wp-api-fetch)
 * meldet CBD_Block_Reference::register_auswahl_script() an.
 *
 * ---------------------------------------------------------------------------
 * OEFFENTLICHE SCHNITTSTELLE (Vertrag C aus docs/PLAN-Inline-Blockreferenz.md)
 * ---------------------------------------------------------------------------
 *
 *   window.cbdBlockAuswahl = {
 *       ladeDaten,          // -> Promise<{bloecke, baum, fehler}>, memoisiert
 *       schluessel,         // Eintrag -> "<postId>|<stableId>"
 *       text,               // null-sichere Textumwandlung
 *       passtZurSuche,      // Mehrwortfilter, alle Teile muessen passen
 *       pfadVon,            // (baum, postId) -> Array<int>, Wurzel zuerst
 *       ebenen,             // (baum, bloecke, pfad) -> Kaskadenstufen
 *       HierarchieAuswahl   // wp.element-Komponente
 *   };
 *
 * Zwei Konsumenten teilen sich diesen Baustein: die Seitenleiste des Blocks
 * "Block-Referenz" (AP-4.1) und der Inline-Dialog des Textformats (AP-4.2).
 * Zwei Fassungen derselben Auswahl liefen unweigerlich auseinander - das
 * Projekt hat dafuer ein warnendes Beispiel in den beiden Admin-Formularen
 * admin/new-block.php und admin/edit-block.php.
 *
 * WICHTIG - `wp` DARF BEIM LADEN DIESER DATEI NICHT BERUEHRT WERDEN.
 * Jeder Zugriff auf window.wp steht deshalb INNERHALB einer Funktion, nie im
 * Rumpf der IIFE. Nur so laesst sich die reine Logik ohne WordPress pruefen
 * (tools/test-block-auswahl.js fuehrt die Datei mit einem Stub-window aus).
 *
 * @package ContainerBlockDesigner
 * @since 3.1.92
 */
(function (window) {
	'use strict';

	var TEXTDOMAIN = 'container-block-designer';

	var PFAD_BLOECKE = '/cbd/v1/blocks';
	var PFAD_BAUM = '/cbd/v1/seitenbaum';

	/**
	 * Wert der zusaetzlichen, flachen Wurzeloption.
	 *
	 * Beitraege sind nicht hierarchisch und stehen deshalb nicht im
	 * Seitenbaum (Vertrag B). Damit trotzdem kein Ziel unerreichbar ist,
	 * bekommen sie eine eigene Wurzeloption. Dieselbe Option faengt zwei
	 * weitere Faelle mit ab, in denen eine Seite nicht im Baum steht:
	 * verwaiste Seiten (Elternteil kein `publish` - dokumentierte Eigenschaft
	 * der Breitensuche) und der Ausfall der Baum-Route. Faellt der Baum aus,
	 * bleiben also ALLE Ziele ueber diese eine Option waehlbar.
	 *
	 * Der Wert kann mit keiner Seiten-ID kollidieren (IDs sind Ziffern) und
	 * darf als erstes Element eines Pfades stehen: So laesst sich "Beitraege
	 * gewaehlt, aber noch kein Beitrag" ausdruecken.
	 */
	var BEITRAEGE = 'beitraege';

	/** Memoisiertes Promise von ladeDaten() - siehe dort. */
	var gemerktesPromise = null;

	// -----------------------------------------------------------------------
	// Kleine Helfer
	// -----------------------------------------------------------------------

	/**
	 * Uebersetzung ueber wp.i18n, sofern vorhanden.
	 *
	 * Absichtlich eine Funktion und keine beim Laden aufgeloeste Referenz:
	 * Beim Laden dieser Datei darf `wp` nicht beruehrt werden.
	 *
	 * @param {string} roh Ausgangstext
	 * @returns {string}
	 */
	function uebersetze(roh) {
		if (window.wp && window.wp.i18n && 'function' === typeof window.wp.i18n.__) {
			return window.wp.i18n.__(roh, TEXTDOMAIN);
		}
		return roh;
	}

	/**
	 * Null-sichere Textumwandlung.
	 *
	 * Verschoben aus blocks/block-reference/index.js (dort :69-71); AP-4.1
	 * entfernt die dortige Fassung und ruft diese hier auf.
	 *
	 * @param {*} wert
	 * @returns {string}
	 */
	function text(wert) {
		return (wert === null || wert === undefined) ? '' : String(wert);
	}

	/**
	 * Positive Ganzzahl oder 0.
	 *
	 * @param {*} wert
	 * @returns {number}
	 */
	function ganzzahl(wert) {
		var zahl = parseInt(wert, 10);
		return (isNaN(zahl) || zahl <= 0) ? 0 : zahl;
	}

	/**
	 * Schluessel eines Listeneintrags fuer die Auswahlliste.
	 *
	 * Seiten-ID UND stableId, weil CBD_Block_Organizer::should_regenerate_id()
	 * die stableId beim Kopieren NICHT neu vergibt - dieselbe Kennung kann
	 * also auf zwei Seiten liegen.
	 *
	 * Verschoben aus blocks/block-reference/index.js (dort :62-67).
	 *
	 * @param {Object} eintrag Element aus Vertrag A
	 * @returns {string} "<postId>|<stableId>" oder ''
	 */
	function schluessel(eintrag) {
		if (!eintrag || !eintrag.stableId) {
			return '';
		}
		return String(parseInt(eintrag.postId, 10) || 0) + '|' + String(eintrag.stableId);
	}

	/**
	 * Mehrwortfilter ueber Seiten- und Blocktitel: ALLE Teile muessen passen.
	 *
	 * Verschoben aus blocks/block-reference/index.js (dort :80-92); der
	 * Wachposten gegen einen fehlenden Eintrag ist neu, weil die Komponente
	 * die Funktion ueber eine Liste aus dem Netz laufen laesst.
	 *
	 * @param {Object} eintrag Element aus Vertrag A
	 * @param {string} begriff Suchbegriff
	 * @returns {boolean}
	 */
	function passtZurSuche(eintrag, begriff) {
		if (!begriff) {
			return true;
		}

		var heuhaufen = (text(eintrag && eintrag.postTitle) + ' ' + text(eintrag && eintrag.blockTitle)).toLowerCase();
		var teile = String(begriff).toLowerCase().split(/\s+/);

		for (var i = 0; i < teile.length; i++) {
			if (teile[i] && heuhaufen.indexOf(teile[i]) === -1) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Leerer, wohlgeformter Baum nach Vertrag B.
	 *
	 * @returns {{knoten: Object, kinder: Object, wurzeln: Array}}
	 */
	function leererBaum() {
		return { knoten: {}, kinder: {}, wurzeln: [] };
	}

	/**
	 * Baumdaten in eine verlaessliche Form bringen.
	 *
	 * Die Daten kommen ueber das Netz - auf Vertrag B wird geprueft, nicht
	 * vertraut. Jeder Teil, der nicht passt, wird durch seinen leeren
	 * Gegenpart ersetzt; die Funktion wirft nie.
	 *
	 * @param {*} baum
	 * @returns {{knoten: Object, kinder: Object, wurzeln: Array}}
	 */
	function normalisiereBaum(baum) {
		var ergebnis = leererBaum();

		if (!baum || 'object' !== typeof baum || Array.isArray(baum)) {
			return ergebnis;
		}

		if (baum.knoten && 'object' === typeof baum.knoten && !Array.isArray(baum.knoten)) {
			ergebnis.knoten = baum.knoten;
		}
		if (baum.kinder && 'object' === typeof baum.kinder && !Array.isArray(baum.kinder)) {
			ergebnis.kinder = baum.kinder;
		}
		if (Array.isArray(baum.wurzeln)) {
			ergebnis.wurzeln = baum.wurzeln;
		}

		return ergebnis;
	}

	function enthaeltWert(optionen, wert) {
		for (var i = 0; i < optionen.length; i++) {
			if (optionen[i].value === wert) {
				return true;
			}
		}
		return false;
	}

	function enthaeltId(ids, id) {
		for (var i = 0; i < ids.length; i++) {
			if (ids[i] === id) {
				return true;
			}
		}
		return false;
	}

	// -----------------------------------------------------------------------
	// Vertrag C: Daten laden
	// -----------------------------------------------------------------------

	/**
	 * Laedt Vertrag A (Blockliste) und Vertrag B (Seitenbaum) parallel.
	 *
	 * MEMOISIERT: Das erzeugte Promise wird gemerkt und bei jedem weiteren
	 * Aufruf zurueckgegeben. Mehrere Aufrufer (Seitenleiste, Inline-Dialog,
	 * mehrere Bloecke im selben Editor) teilen sich damit EINEN Abruf je
	 * Route und Editor-Sitzung. Bisher rief die Seitenleiste /cbd/v1/blocks
	 * bei jedem Mount neu ab - und diese Route ist die teure (sie laedt alle
	 * veroeffentlichten Beitraege samt post_content).
	 *
	 * LEHNT NIE AB. Ein Fehler landet in `fehler`, der betroffene Datensatz
	 * bleibt leer. Faellt nur der Baum aus, bleiben die Bloecke nutzbar - sie
	 * sind dann ueber die flache Zusatzwurzel erreichbar.
	 *
	 * @returns {Promise<{bloecke: Array, baum: Object, fehler: string}>}
	 */
	function ladeDaten() {
		if (gemerktesPromise) {
			return gemerktesPromise;
		}

		var meldungBloecke = uebersetze('Die Blockliste konnte nicht geladen werden.');
		var meldungBaum = uebersetze('Der Seitenbaum konnte nicht geladen werden.');

		if (!window.wp || 'function' !== typeof window.wp.apiFetch) {
			gemerktesPromise = Promise.resolve({
				bloecke: [],
				baum: leererBaum(),
				fehler: meldungBloecke
			});
			return gemerktesPromise;
		}

		var apiFetch = window.wp.apiFetch;

		// Je Route ein eigener Wachposten, damit ein Ausfall die andere Route
		// nicht mitreisst.
		function hole(pfad) {
			return apiFetch({ path: pfad }).then(
				function (antwort) {
					return { ok: true, daten: antwort };
				},
				function (fehler) {
					if (window.console && window.console.error) {
						window.console.error('CBD Block-Auswahl: ' + pfad + ' konnte nicht geladen werden.', fehler);
					}
					return { ok: false, daten: null };
				}
			);
		}

		gemerktesPromise = Promise.all([hole(PFAD_BLOECKE), hole(PFAD_BAUM)]).then(function (antworten) {
			var meldungen = [];
			var bloecke = [];
			var baum = leererBaum();

			if (antworten[0].ok && Array.isArray(antworten[0].daten)) {
				bloecke = antworten[0].daten;
			} else {
				meldungen.push(meldungBloecke);
			}

			if (antworten[1].ok && antworten[1].daten && 'object' === typeof antworten[1].daten
				&& !Array.isArray(antworten[1].daten)) {
				baum = normalisiereBaum(antworten[1].daten);
			} else {
				meldungen.push(meldungBaum);
			}

			return { bloecke: bloecke, baum: baum, fehler: meldungen.join(' ') };
		}).catch(function () {
			// Kann nach den Wachposten oben nicht mehr eintreten - steht hier,
			// damit die Zusicherung "lehnt nie ab" nicht an einer kuenftigen
			// Aenderung haengt.
			return { bloecke: [], baum: leererBaum(), fehler: meldungBloecke };
		});

		return gemerktesPromise;
	}

	// -----------------------------------------------------------------------
	// Vertrag C: Hierarchie
	// -----------------------------------------------------------------------

	/**
	 * Vorfahrenkette einer Seite, Wurzel zuerst, einschliesslich postId.
	 *
	 * Unbekannte postId -> []. Der Schleifenschutz ueber eine Besuchsmenge
	 * bleibt, obwohl Vertrag B Zyklen ausschliesst: Die Daten kommen ueber
	 * das Netz.
	 *
	 * @param {Object} baum Vertrag B
	 * @param {*} postId
	 * @returns {Array<number>}
	 */
	function pfadVon(baum, postId) {
		var daten = normalisiereBaum(baum);
		var id = ganzzahl(postId);
		var kette = [];
		var gesehen = {};

		while (id && daten.knoten[id] && !gesehen[id]) {
			gesehen[id] = true;
			kette.unshift(id);
			id = ganzzahl(daten.knoten[id].parent);
		}

		return kette;
	}

	/**
	 * Zielbloecke nach Seiten-ID gruppieren.
	 *
	 * @param {Array} bloecke Vertrag A
	 * @returns {Object} { <postId>: Array<Eintrag> }
	 */
	function gruppiereNachSeite(bloecke) {
		var liste = Array.isArray(bloecke) ? bloecke : [];
		var karte = {};

		for (var i = 0; i < liste.length; i++) {
			var eintrag = liste[i];
			if (!eintrag || !eintrag.stableId) {
				continue;
			}
			var id = ganzzahl(eintrag.postId);
			if (!id) {
				continue;
			}
			if (!karte[id]) {
				karte[id] = [];
			}
			karte[id].push(eintrag);
		}

		return karte;
	}

	/**
	 * Alle Seiten, die selbst einen Zielblock tragen oder einen Nachfahren
	 * mit Zielblock haben.
	 *
	 * Berechnet von den Blaettern nach oben ueber pfadVon() - jede Seite mit
	 * Bloecken markiert ihre ganze Vorfahrenkette. Zweige ohne jeden
	 * Zielblock bleiben dadurch unmarkiert und werden beschnitten.
	 *
	 * @param {Object} baum
	 * @param {Object} nachSeite Ergebnis von gruppiereNachSeite()
	 * @returns {Object} { <postId>: true }
	 */
	function erreichbareSeiten(baum, nachSeite) {
		var menge = {};

		for (var roh in nachSeite) {
			if (!Object.prototype.hasOwnProperty.call(nachSeite, roh)) {
				continue;
			}
			var kette = pfadVon(baum, roh);
			for (var i = 0; i < kette.length; i++) {
				menge[kette[i]] = true;
			}
		}

		return menge;
	}

	/**
	 * Seiten mit Zielbloecken, die NICHT im Baum stehen.
	 *
	 * Das sind nach Vertrag B alle Beitraege; dazu kommen verwaiste Seiten
	 * und - faellt die Baum-Route aus - schlicht alle. Sortiert nach Titel,
	 * bei Gleichstand nach ID (stabile Reihenfolge).
	 *
	 * @param {Object} baum
	 * @param {Object} nachSeite
	 * @returns {Array<number>}
	 */
	function loseSeiten(baum, nachSeite) {
		var daten = normalisiereBaum(baum);
		var ids = [];

		for (var roh in nachSeite) {
			if (!Object.prototype.hasOwnProperty.call(nachSeite, roh)) {
				continue;
			}
			var id = ganzzahl(roh);
			if (!id || daten.knoten[id]) {
				continue;
			}
			ids.push(id);
		}

		ids.sort(function (a, b) {
			var titelA = seitentitel(nachSeite[a]);
			var titelB = seitentitel(nachSeite[b]);
			if (titelA === titelB) {
				return a - b;
			}
			return (titelA < titelB) ? -1 : 1;
		});

		return ids;
	}

	/**
	 * Seitentitel aus den Blockeintraegen einer Seite (Vertrag A liefert ihn
	 * je Eintrag mit).
	 *
	 * @param {Array} eintraege
	 * @returns {string}
	 */
	function seitentitel(eintraege) {
		if (!eintraege || !eintraege.length) {
			return '';
		}
		return text(eintraege[0].postTitle);
	}

	/**
	 * Option fuer eine Seite aus dem Baum.
	 *
	 * Gesperrte Seiten werden GEKENNZEICHNET, nicht ausgeblendet: Auf einer
	 * Lehrerseite ist der Verweis legitim, fuer Schuelerinnen und Schueler
	 * oeffnet er aber nichts. Die Kennzeichnung selbst (Text am Label) macht
	 * die Komponente - hier steht nur die Tatsache.
	 *
	 * @param {Object} daten normalisierter Baum
	 * @param {number} id
	 * @returns {{value: string, label: string, gesperrt: boolean}}
	 */
	function seitenOption(daten, id) {
		var knoten = daten.knoten[id] || {};

		return {
			value: String(id),
			label: text(knoten.titel) || uebersetze('(ohne Titel)'),
			gesperrt: true === knoten.gesperrt
		};
	}

	/**
	 * Optionen der Blockstufe.
	 *
	 * @param {Array} eintraege Zielbloecke einer Seite
	 * @param {boolean} gesperrt Ist die Seite fuer Lehrpersonen reserviert?
	 * @returns {Array<Object>}
	 */
	function blockOptionen(eintraege, gesperrt) {
		var optionen = [];

		for (var i = 0; i < eintraege.length; i++) {
			var eintrag = eintraege[i];
			optionen.push({
				value: schluessel(eintrag),
				label: text(eintrag.blockTitle) || text(eintrag.stableId),
				gesperrt: !!gesperrt,
				eintrag: eintrag
			});
		}

		return optionen;
	}

	/**
	 * Pfad in eine verlaessliche Form bringen.
	 *
	 * Erlaubt ist als ERSTES Element zusaetzlich die Marke BEITRAEGE; alle
	 * uebrigen Elemente sind positive Ganzzahlen. Unbrauchbares faellt weg.
	 *
	 * @param {*} pfad
	 * @returns {Array}
	 */
	function bereinigePfad(pfad) {
		var weg = [];

		if (!Array.isArray(pfad)) {
			return weg;
		}

		for (var i = 0; i < pfad.length; i++) {
			if (0 === i && BEITRAEGE === String(pfad[i])) {
				weg.push(BEITRAEGE);
				continue;
			}
			var id = ganzzahl(pfad[i]);
			if (id) {
				weg.push(id);
			}
		}

		return weg;
	}

	/**
	 * Kaskadenstufen fuer einen Pfad.
	 *
	 * Je Stufe: die waehlbaren Seiten und der aktuelle Wert. Zweige ohne
	 * erreichbaren Zielblock sind beschnitten.
	 *
	 * Form je Stufe: { tiefe, optionen, wert }
	 *
	 *   tiefe     Nummer der Stufe. Fuer Seitenstufen zugleich die Tiefe im
	 *             Baum (0 = oberste Ebene). Die BLOCKSTUFE traegt `null` -
	 *             sie gehoert zu keiner Baumebene.
	 *   optionen  [{ value, label, gesperrt, eintrag? }]; `eintrag` nur in
	 *             der Blockstufe (das Element aus Vertrag A).
	 *   wert      Der gewaehlte Wert dieser Stufe oder ''. In der Blockstufe
	 *             IMMER '': ebenen() kennt nur den Seitenpfad, nicht die
	 *             Zielwahl - die setzt die Komponente aus ihrem Prop `wert`.
	 *
	 * Die Stufe 0 wird immer geliefert, auch mit leerer Optionsliste. Damit
	 * ist ebenen(...).length >= 1 zugesichert.
	 *
	 * @param {Object} baum Vertrag B
	 * @param {Array} bloecke Vertrag A
	 * @param {Array} pfad Seiten-IDs, Wurzel zuerst; erstes Element darf die
	 *                     Marke BEITRAEGE sein
	 * @returns {Array<{tiefe: (number|null), optionen: Array, wert: string}>}
	 */
	function ebenen(baum, bloecke, pfad) {
		var daten = normalisiereBaum(baum);
		var nachSeite = gruppiereNachSeite(bloecke);
		var erreichbar = erreichbareSeiten(daten, nachSeite);
		var lose = loseSeiten(daten, nachSeite);
		var weg = bereinigePfad(pfad);
		var stufen = [];
		var i;

		var erste = weg.length ? weg[0] : 0;
		var istMarke = (BEITRAEGE === erste);
		var imBaum = (!istMarke && erste && !!daten.knoten[erste]);

		// --- Stufe 0: oberste Seitenebene, dazu die flache Zusatzwurzel ----
		var optionen = [];
		for (i = 0; i < daten.wurzeln.length; i++) {
			var wurzel = ganzzahl(daten.wurzeln[i]);
			if (wurzel && erreichbar[wurzel] && daten.knoten[wurzel]) {
				optionen.push(seitenOption(daten, wurzel));
			}
		}
		if (lose.length) {
			optionen.push({ value: BEITRAEGE, label: uebersetze('Beiträge'), gesperrt: false });
		}

		// Welcher Beitrag (bzw. welche Seite ausserhalb des Baums) ist
		// gewaehlt? Zwei Schreibweisen sind moeglich: [77] (so liefert es der
		// Rueckfall der Komponente, wenn pfadVon() nichts findet) und
		// [BEITRAEGE, 77] (so entsteht es beim Klicken durch die Kaskade).
		var loseAuswahl = 0;
		if (istMarke) {
			loseAuswahl = (weg.length > 1) ? ganzzahl(weg[1]) : 0;
		} else if (erste && !imBaum) {
			loseAuswahl = erste;
		}
		if (loseAuswahl && !enthaeltId(lose, loseAuswahl)) {
			loseAuswahl = 0;
		}

		var wert = '';
		if (imBaum && enthaeltWert(optionen, String(erste))) {
			wert = String(erste);
		} else if ((istMarke || loseAuswahl) && lose.length) {
			wert = BEITRAEGE;
		}

		stufen.push({ tiefe: 0, optionen: optionen, wert: wert });

		// --- Folgestufen ---------------------------------------------------
		var gewaehlt = 0;

		if (BEITRAEGE === wert) {
			var loseOptionen = [];
			for (i = 0; i < lose.length; i++) {
				loseOptionen.push({
					value: String(lose[i]),
					label: seitentitel(nachSeite[lose[i]]) || uebersetze('(ohne Titel)'),
					gesperrt: false
				});
			}
			stufen.push({
				tiefe: 1,
				optionen: loseOptionen,
				wert: loseAuswahl ? String(loseAuswahl) : ''
			});
			gewaehlt = loseAuswahl;
		} else if (wert) {
			gewaehlt = erste;

			var tiefe = 1;
			while (gewaehlt) {
				var rohKinder = daten.kinder[gewaehlt];
				var kinder = [];

				if (Array.isArray(rohKinder)) {
					for (i = 0; i < rohKinder.length; i++) {
						var kind = ganzzahl(rohKinder[i]);
						if (kind && erreichbar[kind] && daten.knoten[kind]) {
							kinder.push(kind);
						}
					}
				}

				if (!kinder.length) {
					break;
				}

				var naechste = (weg.length > tiefe) ? ganzzahl(weg[tiefe]) : 0;
				var kindWert = (naechste && enthaeltId(kinder, naechste)) ? String(naechste) : '';
				var kindOptionen = [];
				for (i = 0; i < kinder.length; i++) {
					kindOptionen.push(seitenOption(daten, kinder[i]));
				}

				stufen.push({ tiefe: tiefe, optionen: kindOptionen, wert: kindWert });

				if (!kindWert) {
					break;
				}

				gewaehlt = naechste;
				tiefe++;
			}
		}

		// --- Blockstufe ----------------------------------------------------
		// Sie erscheint, sobald die gewaehlte Seite SELBST Zielbloecke hat -
		// gleichzeitig mit einer noch offenen Unterseiten-Auswahl.
		if (gewaehlt && nachSeite[gewaehlt] && nachSeite[gewaehlt].length) {
			var knoten = daten.knoten[gewaehlt];
			stufen.push({
				tiefe: null,
				optionen: blockOptionen(nachSeite[gewaehlt], !!(knoten && true === knoten.gesperrt)),
				wert: ''
			});
		}

		return stufen;
	}

	/**
	 * Eintrag aus Vertrag A zu einem Schluessel finden.
	 *
	 * @param {Array} bloecke
	 * @param {string} wert
	 * @returns {Object|null}
	 */
	function eintragZuSchluessel(bloecke, wert) {
		if (!wert || !Array.isArray(bloecke)) {
			return null;
		}
		for (var i = 0; i < bloecke.length; i++) {
			if (schluessel(bloecke[i]) === wert) {
				return bloecke[i];
			}
		}
		return null;
	}

	/**
	 * Seiten-ID aus einem Schluessel "<postId>|<stableId>".
	 *
	 * @param {string} wert
	 * @returns {number}
	 */
	function postIdAusSchluessel(wert) {
		var teile = text(wert).split('|');
		return teile.length ? ganzzahl(teile[0]) : 0;
	}

	// -----------------------------------------------------------------------
	// Vertrag C: Komponente
	// -----------------------------------------------------------------------

	/**
	 * Hierarchische Zielauswahl.
	 *
	 * Props:
	 *   wert             string   aktueller Schluessel "<postId>|<stableId>" oder ''
	 *   onWaehle         function (eintrag|null) => void
	 *   suche            bool     Suchfeld anzeigen, Vorgabe true
	 *   hinweisGesperrt  bool     Hinweis bei gesperrter Zielseite, Vorgabe true
	 *   beschriftung     string   Ueberschrift des Auswahlbereichs, optional
	 *
	 * Zusicherungen:
	 *   1. Sie ruft ladeDaten() selbst auf und zeigt bis dahin einen Spinner.
	 *   2. Sie wirft nie - fehlende oder kaputte Daten ergeben eine Meldung.
	 *   3. Der aktuell gewaehlte Eintrag bleibt waehlbar, auch wenn der
	 *      Suchbegriff ihn herausfiltert.
	 *   4. Ein Suchtreffer stellt die Kaskade auf den Pfad des Treffers.
	 *   5. Die naechste Seitenstufe erscheint nur bei Kindern mit
	 *      erreichbaren Zielbloecken; die Blockstufe, sobald die gewaehlte
	 *      Seite selbst welche hat - beide duerfen gleichzeitig sichtbar sein.
	 *
	 * SUCHE UND KASKADE TEILEN EINEN ZUSTAND. Der Zustand ist der Seitenpfad.
	 * Ein Suchtreffer wird uebernommen wie jede andere Wahl; dadurch aendert
	 * sich `wert`, die manuelle Navigation verfaellt und die Kaskade steht auf
	 * dem Pfad des Treffers. Zwei nebeneinander laufende Zustaende wuerden
	 * sich frueher oder spaeter widersprechen.
	 *
	 * @param {Object} props
	 * @returns {Object|null}
	 */
	function HierarchieAuswahl(props) {
		var wp = window.wp;

		// Wachposten VOR den Hooks - und dauerhaft: Fehlt wp, liefert die
		// Komponente bei jedem Rendern null, die Hook-Reihenfolge bleibt also
		// gleich.
		if (!wp || !wp.element || !wp.components
			|| 'function' !== typeof wp.element.createElement
			|| 'function' !== typeof wp.element.useState
			|| 'function' !== typeof wp.element.useEffect) {
			return null;
		}

		var el = wp.element.createElement;
		var Fragment = wp.element.Fragment;
		var useState = wp.element.useState;
		var useEffect = wp.element.useEffect;

		var SelectControl = wp.components.SelectControl;
		var TextControl = wp.components.TextControl;
		var Spinner = wp.components.Spinner;
		var Notice = wp.components.Notice;

		var p = props || {};
		var wert = text(p.wert);
		var zeigeSuche = (false !== p.suche);
		var zeigeHinweis = (false !== p.hinweisGesperrt);
		var beschriftung = text(p.beschriftung);
		var melde = ('function' === typeof p.onWaehle) ? p.onWaehle : function () {};

		var datenZustand = useState(null);
		var daten = datenZustand[0];
		var setDaten = datenZustand[1];

		var sucheZustand = useState('');
		var suchbegriff = sucheZustand[0];
		var setSuchbegriff = sucheZustand[1];

		// Manuelle Navigation. Sie gilt nur, solange `wert` derselbe bleibt:
		// Waehlt der Nutzer ein Ziel, aendert sich `wert`, die
		// Ueberschreibung verfaellt und der Pfad wird wieder aus dem Ziel
		// abgeleitet. Genau das macht Suche und Kaskade zu zwei Sichten auf
		// einen Zustand.
		var pfadZustand = useState(null);
		var ueberschreibung = pfadZustand[0];
		var setUeberschreibung = pfadZustand[1];

		useEffect(function () {
			var abgebrochen = false;

			ladeDaten().then(function (ergebnis) {
				if (!abgebrochen) {
					setDaten(ergebnis);
				}
			});

			return function () {
				abgebrochen = true;
			};
		}, []);

		if (!daten) {
			return el('div', { className: 'cbd-block-auswahl is-laedt' },
				Spinner ? el(Spinner, {}) : null,
				el('p', {}, uebersetze('Ziel-Blöcke werden geladen …'))
			);
		}

		function zeichne() {
			var i;
			var bloecke = Array.isArray(daten.bloecke) ? daten.bloecke : [];
			var baum = daten.baum;

			var aktuellerEintrag = eintragZuSchluessel(bloecke, wert);
			var zielSeite = aktuellerEintrag
				? ganzzahl(aktuellerEintrag.postId)
				: postIdAusSchluessel(wert);

			// Rueckfall fuer Ziele ausserhalb des Baums (Beitraege, verwaiste
			// Seiten): ebenen() erkennt eine einelementige Kette selbst.
			var basisPfad = pfadVon(baum, zielSeite);
			if (!basisPfad.length && zielSeite) {
				basisPfad = [zielSeite];
			}

			var pfad = (ueberschreibung && ueberschreibung.fuer === wert)
				? ueberschreibung.pfad
				: basisPfad;

			var stufen = ebenen(baum, bloecke, pfad);

			/**
			 * Der neue Pfad wird aus den WERTEN der Stufen gebaut, nicht aus
			 * `pfad` beschnitten. Grund: Der abgeleitete Pfad kann eine
			 * verkuerzte Schreibweise tragen ([77] fuer einen Beitrag),
			 * waehrend die Kaskade die kanonische Form braucht
			 * ([BEITRAEGE, 77]). Aus den Stufenwerten kommt immer die
			 * kanonische Form heraus.
			 */
			function waehleSeite(tiefe, neuerWert) {
				var neu = [];

				for (var s = 0; s < stufen.length; s++) {
					if (null === stufen[s].tiefe || stufen[s].tiefe >= tiefe || !stufen[s].wert) {
						continue;
					}
					neu.push(BEITRAEGE === stufen[s].wert ? BEITRAEGE : ganzzahl(stufen[s].wert));
				}

				if (neuerWert) {
					neu.push(BEITRAEGE === neuerWert ? BEITRAEGE : ganzzahl(neuerWert));
				}

				setUeberschreibung({ fuer: wert, pfad: neu });
			}

			function waehleZiel(neuerWert) {
				if (!neuerWert) {
					melde(null);
					return;
				}
				melde(eintragZuSchluessel(bloecke, neuerWert) || null);
			}

			function beschrifte(option) {
				return option.gesperrt
					? option.label + ' ' + uebersetze('(nur für Lehrpersonen)')
					: option.label;
			}

			function alsOptionen(stufe, platzhalter) {
				var liste = [{ label: platzhalter, value: '' }];
				for (var k = 0; k < stufe.optionen.length; k++) {
					liste.push({
						label: beschrifte(stufe.optionen[k]),
						value: stufe.optionen[k].value
					});
				}
				return liste;
			}

			// --- Kaskade ---------------------------------------------------
			var felder = [];
			var gewaehlteSeite = 0;
			var blockstufeDa = false;

			for (i = 0; i < stufen.length; i++) {
				var stufe = stufen[i];

				if (null === stufe.tiefe) {
					blockstufeDa = true;

					var blockListe = alsOptionen(stufe, uebersetze('-- Block auswählen --'));
					var trefferDabei = false;
					for (var b = 0; b < stufe.optionen.length; b++) {
						if (stufe.optionen[b].value === wert) {
							trefferDabei = true;
						}
					}
					// Zusicherung 3, hier fuer den Fall, dass das gespeicherte
					// Ziel gar nicht mehr in der Liste steht (Block geloescht,
					// Seite nicht mehr veroeffentlicht).
					if (wert && !trefferDabei) {
						blockListe.push({
							label: (aktuellerEintrag
								? (text(aktuellerEintrag.blockTitle) || text(aktuellerEintrag.stableId))
								: uebersetze('(gespeichertes Ziel)')),
							value: wert
						});
					}

					felder.push(el(SelectControl, {
						key: 'block',
						label: uebersetze('Ziel-Block'),
						value: wert,
						options: blockListe,
						onChange: waehleZiel,
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true
					}));
					continue;
				}

				if (!stufe.optionen.length) {
					continue;
				}

				if (stufe.wert && BEITRAEGE !== stufe.wert) {
					gewaehlteSeite = ganzzahl(stufe.wert);
				}

				felder.push(el(SelectControl, {
					key: 'ebene-' + String(stufe.tiefe),
					label: uebersetze('Ebene') + ' ' + String(stufe.tiefe + 1),
					value: stufe.wert,
					options: alsOptionen(stufe, uebersetze('-- Seite auswählen --')),
					onChange: (function (tiefe) {
						return function (neuerWert) {
							waehleSeite(tiefe, neuerWert);
						};
					})(stufe.tiefe),
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				}));
			}

			// --- Suche -----------------------------------------------------
			var suchfeld = null;
			var trefferfeld = null;

			if (zeigeSuche) {
				suchfeld = el(TextControl, {
					key: 'suche',
					label: uebersetze('Blocksuche'),
					value: suchbegriff,
					onChange: function (neu) { setSuchbegriff(neu); },
					help: uebersetze('Sucht über alle Ebenen hinweg in Seiten- und Blocktitel.'),
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				});

				var begriff = suchbegriff.trim();
				if (begriff) {
					var treffer = [{ label: uebersetze('-- Treffer auswählen --'), value: '' }];
					var aktuellerTrefferDabei = false;

					for (i = 0; i < bloecke.length; i++) {
						var eintrag = bloecke[i];
						if (!eintrag || !eintrag.stableId || !passtZurSuche(eintrag, begriff)) {
							continue;
						}
						treffer.push({
							label: (text(eintrag.postTitle) || uebersetze('(ohne Titel)'))
								+ ' → '
								+ (text(eintrag.blockTitle) || text(eintrag.stableId)),
							value: schluessel(eintrag)
						});
						if (schluessel(eintrag) === wert) {
							aktuellerTrefferDabei = true;
						}
					}

					// Zusicherung 3: Die getroffene Auswahl bleibt in der
					// Liste, auch wenn der Suchtext sie herausfiltert - sonst
					// zeigte das Auswahlfeld einen Wert, den es nicht kennt,
					// und verwuerfe ihn beim naechsten Rendern.
					if (wert && !aktuellerTrefferDabei && aktuellerEintrag) {
						treffer.splice(1, 0, {
							label: (text(aktuellerEintrag.postTitle) || uebersetze('(ohne Titel)'))
								+ ' → '
								+ (text(aktuellerEintrag.blockTitle) || text(aktuellerEintrag.stableId)),
							value: wert
						});
					}

					trefferfeld = el(SelectControl, {
						key: 'treffer',
						label: uebersetze('Suchergebnis'),
						value: wert,
						options: treffer,
						onChange: waehleZiel,
						help: String(Math.max(0, treffer.length - 1)) + ' ' + uebersetze('Treffer'),
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true
					});
				}
			}

			// --- Meldungen -------------------------------------------------
			var meldungen = [];

			if (daten.fehler && Notice) {
				meldungen.push(el(Notice, {
					key: 'fehler',
					status: 'error',
					isDismissible: false
				}, daten.fehler));
			}

			if (!felder.length) {
				meldungen.push(el('p', { key: 'leer', className: 'cbd-block-auswahl__leer' },
					uebersetze('Es wurden keine Container-Blöcke gefunden.')
				));
			}

			if (zeigeHinweis && Notice && gewaehlteSeite) {
				var knoten = normalisiereBaum(baum).knoten[gewaehlteSeite];
				if (knoten && true === knoten.gesperrt) {
					meldungen.push(el(Notice, {
						key: 'gesperrt',
						status: 'warning',
						isDismissible: false
					}, uebersetze('Diese Seite ist für Lehrpersonen reserviert. Der Verweis öffnet für Schülerinnen und Schüler nur innerhalb einer Klassensitzung, in der der Block als behandelt markiert ist.')));
				}
			}

			// blockstufeDa wird nur ausgewertet, um den Hinweis auf eine noch
			// offene Blockwahl zu geben - die Kaskade selbst steht schon.
			if (felder.length && !blockstufeDa && !wert) {
				meldungen.push(el('p', { key: 'weiter', className: 'cbd-block-auswahl__hinweis' },
					uebersetze('Weiter auswählen, bis ein Block erscheint.')
				));
			}

			return el(Fragment, {},
				beschriftung ? el('p', { className: 'cbd-block-auswahl__titel' }, el('strong', {}, beschriftung)) : null,
				suchfeld,
				trefferfeld,
				felder,
				meldungen
			);
		}

		var inhalt;

		try {
			inhalt = zeichne();
		} catch (fehler) {
			if (window.console && window.console.error) {
				window.console.error('CBD Block-Auswahl: Die Zielauswahl konnte nicht aufgebaut werden.', fehler);
			}
			inhalt = el('p', { className: 'cbd-block-auswahl__fehler' },
				uebersetze('Die Zielauswahl konnte nicht aufgebaut werden.')
			);
		}

		return el('div', { className: 'cbd-block-auswahl' }, inhalt);
	}

	// -----------------------------------------------------------------------

	window.cbdBlockAuswahl = {
		ladeDaten: ladeDaten,
		schluessel: schluessel,
		text: text,
		passtZurSuche: passtZurSuche,
		pfadVon: pfadVon,
		ebenen: ebenen,
		HierarchieAuswahl: HierarchieAuswahl
	};
})(window);
