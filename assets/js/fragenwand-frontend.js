/**
 * Container Block Designer - Fragenwand im Frontend (Modal, Trigger, Datenabruf)
 *
 * KEIN BUILD-SCHRITT: Diese Datei wird unveraendert an den Browser
 * ausgeliefert. Hausstil ist ES5 mit var/function und IIFE - kein JSX, keine
 * Arrow Functions, keine Template-Literale, kein let/const.
 *
 * ---------------------------------------------------------------------------
 * WAS DIESE DATEI TUT (AP-3.2 aus PLAN-Fragenwand.md)
 * ---------------------------------------------------------------------------
 *
 * Sie faengt JEDEN Klick auf ein Element mit der Klasse
 * `cbd-fragenwand-verweis` ab und oeffnet die Fragenwand als Modal. Woher der
 * Verweis stammt, ist ihr gleichgueltig: aus dem Textformat im Fliesstext
 * (AP-3.1, assets/js/fragenwand-format.js) oder - ab Phase 4 - aus dem
 * Eintrag ganz oben im Theme-Inhaltsverzeichnis. Deshalb haengt der Listener
 * an `document` und nicht an einzelnen Elementen: Beide Quellen sollen ohne
 * eine zweite Verdrahtung funktionieren.
 *
 * ROLLEN-ERKENNUNG - ein Hinweis, keine Autorisierung. Ob jemand Lehrperson
 * oder Schueler ist, entscheidet IMMER der Server (AP-2.2: fuenf AJAX-Actions
 * hinter Nonce + `cbd_edit_blocks`; AP-2.3: REST-Route hinter der
 * Klassensitzung). Hier wird nur entschieden, WELCHER dieser beiden Wege
 * ueberhaupt versucht wird:
 *
 *   - `window.cbdClassroomData` vorhanden -> Lehrer-Modus. Das Objekt schreibt
 *     class-cbd-block-registration.php per Inline-Script im `wp_footer` nur
 *     fuer angemeldete Personen mit `cbd_edit_blocks` aus. Dann IMMER zuerst
 *     die Klassenauswahl - auch wenn zusaetzlich `?classroom=` in der Adresse
 *     steht: Eine Lehrperson, die eine Klassensitzung mitlaufen laesst, will
 *     nicht stillschweigend auf deren Wand festgelegt werden.
 *     Die Klassenauswahl selbst liefert AP-3.3 als `lehrerFlow` nach; bis
 *     dahin bleibt es bei einem Konsolenhinweis (siehe unten).
 *   - sonst -> Schueler-/Besuchermodus. Die Klassen-ID wird hier NICHT
 *     ermittelt. Der Browser ruft `GET cbd/v1/fragenwand` mit der vorhandenen
 *     Abfragezeichenfolge auf; welche Klasse gemeint ist, liest
 *     `CBD_Classroom_Gate::sitzung()` serverseitig aus `$_GET` und prueft es
 *     gegen den Transient. Ein clientseitig gelesenes `?classroom=` waere nur
 *     ein Anspruch und keine Auskunft.
 *
 * AUS AP-3.2 STAMMT DIE LESEANSICHT. Fuer Schueler ist sie der Endzustand
 * (Nicht-Ziel in Abschnitt 2 des Plans: Schueler schreiben nie). Das Aussehen
 * (Post-it-Optik, Ausgrauen, Darkmode) kommt in AP-3.4 - diese Datei setzt
 * deshalb bewusst KEINE Inline-Styles und bringt keine eigene CSS-Datei mit.
 *
 * ---------------------------------------------------------------------------
 * WAS AP-3.3 ERGAENZT HAT
 * ---------------------------------------------------------------------------
 *
 * Den Lehrer-Weg, der in AP-3.2 nur als Erweiterungspunkt vorgezeichnet war:
 *
 *   1. `lehrerFlow(trigger)` ist jetzt gesetzt. Ein Klick einer Lehrperson
 *      oeffnet zuerst eine Klassenauswahl - IMMER, auch bei mitlaufender
 *      Klassensitzung (Begruendung siehe Rollen-Erkennung oben).
 *   2. `zeigeKlassenauswahl(callback)` ist eine EIGENE, neue Funktion nach dem
 *      optischen und strukturellen Vorbild von `showClassSelector()` in
 *      assets/js/board-mode.js (Overlay + Dialog + ein Knopf je Klasse +
 *      Abbrechen + Backdrop-Klick). Jene Datei wird dabei WEDER geaendert NOCH
 *      aufgerufen - der Plan verbietet beides ausdruecklich (AP-3.3, Schritt 5;
 *      Risikoregister Abschnitt 5: eine versehentliche Aenderung dort waere
 *      eine Tafelmodus-Regression). Uebernommen ist nur das Muster, nicht der
 *      Code, und OHNE die dortige Option „Persoenlich (lokal)": Die Fragenwand
 *      kennt keinen lokalen Modus, es muss immer eine echte Klasse sein.
 *   3. `open()` nimmt ein drittes Argument `{verwaltbar: true}`. Damit werden
 *      die Haken bedienbar, jede Notiz bekommt „Bearbeiten"/„Loeschen", und
 *      unter der Liste steht ein Feld „Frage hinzufuegen".
 *
 * JEDE SCHREIBENDE AKTION LAEDT DIE LISTE DANACH NEU, statt das DOM selbst
 * fortzuschreiben: Die Reihenfolge (offene zuerst, aelteste zuerst) entsteht
 * serverseitig im `ORDER BY` (AP-2.2). Ein Haken verschiebt eine Notiz also
 * ans Ende - das kann der Browser nicht raten, ohne die Sortierregel ein
 * zweites Mal zu schreiben. Neu geladen wird nur der Koerper des Dialogs, das
 * Overlay bleibt stehen (Vorgabe aus AP-3.3, Schritt 2).
 *
 * @package ContainerBlockDesigner
 * @since Vorhaben „Fragenwand", Phase 3 (AP-3.2), erweitert in AP-3.3
 */
(function (window, document) {
	'use strict';

	// =====================================================================
	// KONSTANTEN
	// =====================================================================

	/**
	 * Die CSS-Klasse des Fragenwand-Verweises.
	 *
	 * DRITTE VON DREI STELLEN, an denen diese Zeichenkette wirksam steht:
	 * assets/js/fragenwand-format.js (Registrierung des Textformats), hier
	 * (Klick-Selektor) und ab AP-3.4 assets/css/fragenwand.css (Selektoren).
	 * Wird sie geaendert, muessen alle drei mitgezogen werden. Einen
	 * Duplikatswaechter wie fuer `cbd-block-reference-inline`
	 * (tools/test-inline-reference.php, Gruppe 11) gibt es dafuer noch nicht -
	 * in der AP-3.1-Uebergabenotiz als offener Punkt vermerkt.
	 */
	var TRIGGER_KLASSE = 'cbd-fragenwand-verweis';

	/** Prefix aller Modal-Klassen. */
	var M = 'cbd-fragenwand';

	/** Id der Ueberschrift - fuer `aria-labelledby` am Dialog. */
	var TITEL_ID = 'cbd-fragenwand-titel';

	/**
	 * Id der Ueberschrift der Klassenauswahl - fuer deren `aria-labelledby`.
	 *
	 * Eigene Id, nicht `TITEL_ID`: Auswahl und Modal koennen zwar nie
	 * gleichzeitig offen sein, aber zwei Elemente mit derselben Id waeren
	 * trotzdem eine Falle, sobald sich das je aendert.
	 *
	 * @since AP-3.3
	 */
	var WAHL_TITEL_ID = 'cbd-fragenwand-klassenwahl-titel';

	/**
	 * Rueckfalltexte.
	 *
	 * Der Server schickt dieselben Texte uebersetzbar mit (siehe
	 * `CBD_Fragenwand::enqueue_frontend_assets()`, Schluessel `texte`). Diese
	 * Tabelle greift nur, wenn `wp_localize_script()` nicht gelaufen ist -
	 * etwa weil die Seite das Objekt aus einem Cache ohne das Inline-Script
	 * ausliefert. Ein Modal ohne Beschriftung waere schlechter als eines mit
	 * unuebersetzter.
	 *
	 * DIE AB `klassenwahlTitel` FOLGENDEN SCHLUESSEL SCHICKT DER SERVER
	 * (NOCH) NICHT MIT. Sie sind in AP-3.3 dazugekommen; dessen Abschnitt
	 * „Betroffene Dateien" nennt ausschliesslich diese JS-Datei, die
	 * PHP-Seite (`enqueue_frontend_assets()`) gehoerte also nicht zum Scope.
	 * `t()` faellt fuer sie deshalb immer auf diese Tabelle zurueck - der
	 * Lehrer-Teil der Oberflaeche ist damit vorerst nicht uebersetzbar. Wer
	 * die Schluessel spaeter in `texte` ergaenzt, muss hier nichts aendern:
	 * `t()` bevorzugt den Serverwert automatisch, sobald es ihn gibt.
	 */
	var TEXTE_VORGABE = {
		titel: 'Fragenwand',
		schliessen: 'Schließen',
		laden: 'Fragenwand wird geladen …',
		keineSitzung: 'Keine aktive Klassensitzung.',
		fehler: 'Die Fragenwand konnte nicht geladen werden.',
		leer: 'Auf dieser Fragenwand steht noch nichts.',

		// Ab hier: AP-3.3 (Lehrer-Klassenauswahl und Verwaltungscontrols).
		klassenwahlTitel: 'Klasse wählen',
		klassenwahlHinweis: 'Für welche Klasse soll die Fragenwand geöffnet werden?',
		abbrechen: 'Abbrechen',
		keineKlassen: 'Keine Klassen vorhanden.',
		neueFrage: 'Neue Frage …',
		hinzufuegen: 'Frage hinzufügen',
		bearbeiten: 'Bearbeiten',
		loeschen: 'Löschen',
		speichern: 'Speichern',
		schreibFehler: 'Die Änderung konnte nicht gespeichert werden.'
	};

	/** Was als fokussierbar gilt (Vorbild: blocks/block-reference/view.js). */
	var FOKUSSIERBAR = 'a[href], area[href], button, input, select, textarea, summary, iframe, object, embed, [tabindex], [contenteditable]';

	// =====================================================================
	// ZUSTAND
	// =====================================================================

	/** Das Overlay, solange das Modal offen ist - sonst `null`. */
	var overlay = null;

	/** Der Dialog innerhalb des Overlays. */
	var dialog = null;

	/** Der Koerper des Dialogs (Liste oder Meldung). */
	var koerper = null;

	/** Der Schliessen-Knopf. */
	var schliessKnopf = null;

	/**
	 * Die Statuszeile unter dem Koerper - fuer Fehler einzelner Schreibaktionen.
	 *
	 * Sie liegt bewusst NEBEN dem Koerper im Dialog, nicht darin: `leere(koerper)`
	 * beim Neuladen der Liste wuerde sie sonst jedes Mal mitloeschen, und eine
	 * Meldung „Speichern fehlgeschlagen" verschwaende genau in dem Moment, in
	 * dem sie gebraucht wird.
	 *
	 * @since AP-3.3
	 */
	var statusZeile = null;

	/**
	 * Die Klasse, deren Wand gerade offen ist - nur im Verwaltungsmodus gesetzt.
	 *
	 * Im Schueler-Modus bleibt sie `null`: Dort kennt der Browser die
	 * Klassen-ID nicht und braucht sie auch nicht (der REST-Endpunkt leitet
	 * sie aus der Sitzung ab, AP-2.3).
	 *
	 * @since AP-3.3
	 */
	var aktuelleKlasse = null;

	/** Sind die Verwaltungscontrols aktiv? @since AP-3.3 */
	var verwaltbar = false;

	/** Das Overlay der Klassenauswahl, solange sie offen ist. @since AP-3.3 */
	var wahlOverlay = null;

	/**
	 * Laeuft gerade eine schreibende Anfrage?
	 *
	 * Solange sie laeuft, wird keine zweite gestartet. Ohne diese Sperre
	 * koennte ein doppelter Klick auf „Loeschen" zwei Anfragen ausloesen,
	 * deren Antworten sich mit dem Neuaufbau der Liste ueberholen.
	 *
	 * @since AP-3.3
	 */
	var schreibtGerade = false;

	/**
	 * Wohin der Fokus nach dem naechsten Neuaufbau der Liste soll.
	 *
	 * `null` oder z. B. `{art: 'haken', noteId: 12}`. Ohne das landet der Fokus
	 * nach jeder Schreibaktion auf `document.body`, weil das fokussierte
	 * Element beim Neuaufbau verschwindet - die Fokusfalle faengt das zwar ab,
	 * aber erst beim naechsten Tab-Druck und dann am Dialoganfang.
	 *
	 * @since AP-3.3
	 */
	var fokusWunsch = null;

	/** Das Element, das das Modal geoeffnet hat - dorthin kehrt der Fokus zurueck. */
	var ausloeser = null;

	/** `document.body.style.overflow` vor dem Oeffnen. */
	var overflowVorher = '';

	/**
	 * Zaehler laufender Abrufe.
	 *
	 * Wird bei jedem Abruf und bei jedem Schliessen erhoeht. Eine Antwort, die
	 * nach dem Schliessen (oder nach einem zweiten Klick) eintrifft, traegt
	 * eine veraltete Nummer und wird verworfen - sonst ueberschriebe sie den
	 * Inhalt eines inzwischen anders befuellten Modals.
	 */
	var abrufZaehler = 0;

	// =====================================================================
	// KLEINE HELFER
	// =====================================================================

	/**
	 * Das vom Server lokalisierte Datenobjekt.
	 *
	 * @returns {Object}
	 */
	function konfig() {
		var k = window.cbdFragenwandFrontend;
		return (k && 'object' === typeof k) ? k : {};
	}

	/**
	 * Einen Text lesen - lokalisiert, sonst Rueckfall.
	 *
	 * @param {string} schluessel
	 * @returns {string}
	 */
	function t(schluessel) {
		var texte = konfig().texte;

		if (texte && 'string' === typeof texte[schluessel] && '' !== texte[schluessel]) {
			return texte[schluessel];
		}

		return TEXTE_VORGABE[schluessel] || '';
	}

	/**
	 * Warnung auf der Konsole.
	 *
	 * `console.warn` bleibt laut Hausstil ungegatet; nur `console.log` gehoert
	 * hinter `window.cbdDebug`. In dieser Datei gibt es kein `console.log`.
	 *
	 * @param {string} text
	 * @returns {void}
	 */
	function warne(text) {
		if (window.console && 'function' === typeof window.console.warn) {
			window.console.warn('CBD Fragenwand: ' + text);
		}
	}

	/**
	 * Alle Kinder eines Elements entfernen.
	 *
	 * @param {Element} element
	 * @returns {void}
	 */
	function leere(element) {
		if (!element) {
			return;
		}
		while (element.firstChild) {
			element.removeChild(element.firstChild);
		}
	}

	/**
	 * Vom Klickziel aus nach oben den Verweis suchen.
	 *
	 * `Element.closest()` gibt es nicht auf Text- oder Dokumentknoten und in
	 * aelteren Browsern gar nicht. Deshalb die Schleife statt eines nackten
	 * `e.target.closest(...)`.
	 *
	 * @param {EventTarget} ziel
	 * @returns {Element|null}
	 */
	function naechsterVerweis(ziel) {
		var knoten = ziel;

		while (knoten && knoten !== document) {
			if (1 === knoten.nodeType
				&& knoten.classList
				&& knoten.classList.contains(TRIGGER_KLASSE)) {
				return knoten;
			}
			knoten = knoten.parentNode;
		}

		return null;
	}

	/**
	 * Ist eine Lehrperson am Werk?
	 *
	 * Reiner Hinweis (siehe Kopfkommentar) - die Autorisierung liegt beim
	 * Server. `window.cbdClassroomData` setzt
	 * class-cbd-block-registration.php nur fuer Angemeldete mit
	 * `cbd_edit_blocks` UND eingeschaltetem Klassensystem.
	 *
	 * @returns {boolean}
	 */
	function istLehrkraft() {
		return 'undefined' !== typeof window.cbdClassroomData
			&& !!window.cbdClassroomData;
	}

	/**
	 * Das Datenobjekt der Lehrperson.
	 *
	 * @since AP-3.3
	 * @returns {Object}
	 */
	function klassenDaten() {
		var d = window.cbdClassroomData;
		return (d && 'object' === typeof d) ? d : {};
	}

	/**
	 * Eine Zahl aus einem Wert holen, der auch eine Zeichenkette sein kann.
	 *
	 * `wp_json_encode()` gibt die Spalten aus `$wpdb->get_results()` als
	 * Zeichenketten aus - `window.cbdClassroomData.classes[i].id` ist also
	 * `"20"`, nicht `20`. Ohne diese Umwandlung stuende in `data-class-id`
	 * zwar dasselbe, aber jeder Vergleich mit `===` liefe ins Leere.
	 *
	 * @since AP-3.3
	 * @param {*} wert
	 * @returns {number} 0, wenn nichts Sinnvolles herauskommt
	 */
	function zahl(wert) {
		var n = parseInt(wert, 10);
		return isNaN(n) ? 0 : n;
	}

	/**
	 * Die Fehlermeldung aus einer AJAX-Antwort holen.
	 *
	 * `wp_send_json_error(array('message' => …))` ergibt
	 * `{success: false, data: {message: …}}`.
	 *
	 * @since AP-3.3
	 * @param {Object} antwort
	 * @returns {string} leer, wenn keine Meldung dabei ist
	 */
	function fehlerText(antwort) {
		if (antwort && antwort.data && 'string' === typeof antwort.data.message) {
			return antwort.data.message;
		}
		return '';
	}

	/**
	 * Einen der fuenf Lehrer-AJAX-Endpunkte aus AP-2.2 aufrufen.
	 *
	 * Adresse und Nonce kommen aus `window.cbdClassroomData` - demselben
	 * Objekt, an dem oben die Rollen-Erkennung haengt. Der Parametername
	 * `nonce` ist Vorgabe: `check_ajax_referer('cbd_classroom_nonce', 'nonce')`
	 * sucht genau darunter.
	 *
	 * FEHLGESCHLAGENE NONCE-PRUEFUNGEN LANDEN IM FEHLERZWEIG, nicht im
	 * Erfolgszweig: `check_ajax_referer()` beendet die Anfrage mit dem
	 * Rumpf `-1` und HTTP 403. Das ist kein gueltiges JSON-Objekt, `json()`
	 * wirft, und der `catch`-Zweig greift.
	 *
	 * @since AP-3.3
	 * @param {string}   action    z. B. 'cbd_fragenwand_get_notes'
	 * @param {Object}   felder    zusaetzliche Formularfelder
	 * @param {Function} fertig    bekommt die dekodierte Antwort
	 * @param {Function} gescheitert  ohne Argumente
	 * @returns {void}
	 */
	function rufeAjax(action, felder, fertig, gescheitert) {
		var daten = klassenDaten();
		var url = ('string' === typeof daten.ajaxUrl) ? daten.ajaxUrl : '';

		if ('' === url
			|| 'function' !== typeof window.fetch
			|| 'undefined' === typeof window.FormData) {
			warne('Der AJAX-Aufruf ' + action + ' ist nicht moeglich (ajaxUrl, fetch oder FormData fehlt).');
			gescheitert();
			return;
		}

		var formular = new window.FormData();
		formular.append('action', action);
		formular.append('nonce', daten.nonce || '');

		for (var name in felder) {
			if (Object.prototype.hasOwnProperty.call(felder, name)) {
				formular.append(name, felder[name]);
			}
		}

		window.fetch(url, {
			method: 'POST',
			body: formular,
			credentials: 'same-origin'
		}).then(function (antwort) {
			return antwort.json();
		}).then(function (daten) {
			fertig(daten);
		})['catch'](function (fehler) {
			warne('Der AJAX-Aufruf ' + action + ' ist fehlgeschlagen: ' + fehler);
			gescheitert();
		});
	}

	/**
	 * Die Abruf-Adresse fuer den Schueler-Lesezugriff bauen.
	 *
	 * DIE BASIS KOMMT VOM SERVER, NIE AUS DIESEM SCRIPT. Auf Installationen
	 * ohne huebsche Permalinks liefert `/wp-json/…` einen Apache-404; dort
	 * traegt nur `?rest_route=/cbd/v1/fragenwand`. Welche Form gilt, weiss
	 * allein `rest_url()` auf dem Server - deshalb kommt `restUrl` fertig aus
	 * `wp_localize_script()`.
	 *
	 * Genau daraus folgt die Feinheit beim Anhaengen: Enthaelt die Basis
	 * bereits ein `?` (eben der `rest_route`-Fall), muss die Abfragezeichen-
	 * folge der Seite mit `&` angehaengt werden. Ein zweites `?` zerschnitte
	 * die Adresse, und `classroom`/`token` kaemen nie am Endpunkt an. Vorbild:
	 * `mitParameter()` in blocks/block-reference/view.js.
	 *
	 * Weitergereicht wird `window.location.search` UNVERAENDERT, nicht nur
	 * `classroom`/`token`: Welche Parameter die Sitzung ausmachen, entscheidet
	 * `CBD_Classroom_Gate::sitzung()`. Eine Auswahl hier waere eine zweite,
	 * stillschweigend veraltende Fassung dieser Regel.
	 *
	 * @returns {string} leer, wenn keine Basis bekannt ist
	 */
	function baueAbrufUrl() {
		var k = konfig();
		var basis = ('string' === typeof k.restUrl) ? k.restUrl : '';

		if ('' === basis) {
			return '';
		}

		var such = window.location.search || '';

		if ('?' === such.charAt(0)) {
			such = such.substring(1);
		}

		if ('' === such) {
			return basis;
		}

		return basis + ((-1 === basis.indexOf('?')) ? '?' : '&') + such;
	}

	// =====================================================================
	// MODAL - AUFBAU
	// =====================================================================

	/**
	 * Das Modal-Geruest anlegen und an `document.body` haengen.
	 *
	 * Struktur (die Klassennamen sind der Vertrag mit AP-3.4):
	 *
	 *   div.cbd-fragenwand-overlay
	 *     div.cbd-fragenwand-dialog          [role=dialog, aria-modal]
	 *       div.cbd-fragenwand-kopf
	 *         h2.cbd-fragenwand-titel        [id=cbd-fragenwand-titel]
	 *         button.cbd-fragenwand-schliessen
	 *       div.cbd-fragenwand-koerper       [tabindex=-1]
	 *       p.cbd-fragenwand-status          [role=status, hidden]   (AP-3.3)
	 *
	 * Der Inhalt des Koerpers entsteht spaeter - je nach Fall eine
	 * `ul.cbd-fragenwand-liste` oder ein `p.cbd-fragenwand-meldung`,
	 * im Verwaltungsmodus zusaetzlich `div.cbd-fragenwand-neu` darunter.
	 *
	 * Gebaut wird ueber `document.createElement`, nicht ueber `innerHTML` mit
	 * zusammengesetzten Zeichenketten: Notiztexte kommen aus der Datenbank
	 * und werden ausschliesslich per `textContent` gesetzt. Der Server
	 * bereinigt sie zwar bereits (`sanitize_textarea_field()`, AP-2.2), aber
	 * eine zweite Schicht kostet hier nichts.
	 *
	 * @returns {void}
	 */
	function baueModal() {
		overlay = document.createElement('div');
		overlay.className = M + '-overlay';

		// Backdrop-Klick schliesst. Die Pruefung auf `e.target === overlay`
		// ist noetig, weil das Ereignis auch aus dem Dialog hochblubbert -
		// sonst schloesse jeder Klick im Dialog das Modal.
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) {
				schliesse(true);
			}
		});

		dialog = document.createElement('div');
		dialog.className = M + '-dialog';
		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-labelledby', TITEL_ID);

		var kopf = document.createElement('div');
		kopf.className = M + '-kopf';

		var titel = document.createElement('h2');
		titel.className = M + '-titel';
		titel.id = TITEL_ID;
		titel.textContent = t('titel');

		schliessKnopf = document.createElement('button');
		schliessKnopf.type = 'button';
		schliessKnopf.className = M + '-schliessen';
		schliessKnopf.setAttribute('aria-label', t('schliessen'));
		// `×` = ×. Als HTML-Entity waere es innerHTML; textContent ist
		// hier die schmalere Tuer.
		schliessKnopf.textContent = '×';
		schliessKnopf.addEventListener('click', function () {
			schliesse(true);
		});

		koerper = document.createElement('div');
		koerper.className = M + '-koerper';
		// tabindex="-1": nur programmatisch fokussierbar. Der Fokus landet
		// hier, wenn der Dialog nichts anderes Fokussierbares enthaelt.
		koerper.setAttribute('tabindex', '-1');

		// AP-3.3: Statuszeile fuer Fehler einzelner Schreibaktionen.
		// `role="status"` + `aria-live="polite"` sagt Screenreadern die
		// Meldung an, ohne den Fokus zu stehlen.
		statusZeile = document.createElement('p');
		statusZeile.className = M + '-status';
		statusZeile.setAttribute('role', 'status');
		statusZeile.setAttribute('aria-live', 'polite');
		statusZeile.hidden = true;

		kopf.appendChild(titel);
		kopf.appendChild(schliessKnopf);
		dialog.appendChild(kopf);
		dialog.appendChild(koerper);
		dialog.appendChild(statusZeile);
		overlay.appendChild(dialog);

		document.body.appendChild(overlay);

		overflowVorher = document.body.style.overflow;
		document.body.style.overflow = 'hidden';

		document.addEventListener('keydown', beiTaste);
	}

	/**
	 * Sicherstellen, dass ein Modal offen ist - und zwar ein leeres.
	 *
	 * @returns {void}
	 */
	function oeffneGeruest() {
		if (!overlay) {
			baueModal();
		}
		leere(koerper);
		setzeStatus('');
	}

	/**
	 * Die Statuszeile setzen oder (bei leerem Text) verstecken.
	 *
	 * @since AP-3.3
	 * @param {string} text
	 * @returns {void}
	 */
	function setzeStatus(text) {
		if (!statusZeile) {
			return;
		}

		statusZeile.textContent = text || '';
		statusZeile.hidden = ('' === (text || ''));
	}

	/**
	 * Einen Meldungsabsatz bauen (ohne ihn einzuhaengen).
	 *
	 * Getrennt von `setzeMeldung()`, weil der Verwaltungsmodus die Meldung
	 * „Auf dieser Fragenwand steht noch nichts." zusammen mit dem Feld
	 * „Frage hinzufuegen" zeigen muss - `setzeMeldung()` leert den Koerper und
	 * loeschte das Feld dabei wieder mit.
	 *
	 * @since AP-3.3
	 * @param {string} text
	 * @param {string} art  Zusatzklasse ohne Prefix, z. B. 'fehler'
	 * @returns {Element}
	 */
	function baueMeldung(text, art) {
		var absatz = document.createElement('p');
		absatz.className = M + '-meldung'
			+ (art ? ' ' + M + '-meldung--' + art : '');
		absatz.textContent = text;
		return absatz;
	}

	/**
	 * Eine einzelne Meldung statt einer Liste zeigen.
	 *
	 * @param {string} text
	 * @param {string} art  Zusatzklasse ohne Prefix, z. B. 'fehler'
	 * @returns {void}
	 */
	function setzeMeldung(text, art) {
		if (!koerper) {
			return;
		}

		leere(koerper);
		koerper.appendChild(baueMeldung(text, art));
	}

	/**
	 * Eine einzelne Notiz als Listeneintrag bauen.
	 *
	 * @param {Object}  notiz     {id, text, ist_erledigt}
	 * @param {boolean} nurLesend Checkbox deaktivieren?
	 * @returns {Element}
	 */
	function baueNotiz(notiz, nurLesend) {
		var erledigt = !!notiz.ist_erledigt;

		var eintrag = document.createElement('li');
		eintrag.className = M + '-notiz'
			+ (erledigt ? ' ' + M + '-notiz--erledigt' : '');
		// Der Bezeichner steht am Listeneintrag, nicht an der Checkbox: Die
		// Verwaltungscontrols aus AP-3.3 (bearbeiten, loeschen) haengen
		// ebenfalls hier und finden ihre Notiz dann ueber denselben Knoten.
		eintrag.setAttribute('data-note-id', String(notiz.id));

		var beschriftung = document.createElement('label');
		beschriftung.className = M + '-notiz__label';

		var haken = document.createElement('input');
		haken.type = 'checkbox';
		haken.className = M + '-notiz__haken';
		haken.checked = erledigt;

		if (nurLesend) {
			// `disabled`, nicht `readonly`: Ein `readonly` wirkt auf
			// Checkboxen im HTML-Standard gar nicht - sie blieben klickbar.
			haken.disabled = true;
		}

		var text = document.createElement('span');
		text.className = M + '-notiz__text';
		text.textContent = ('string' === typeof notiz.text) ? notiz.text : '';

		beschriftung.appendChild(haken);
		beschriftung.appendChild(text);
		eintrag.appendChild(beschriftung);

		if (nurLesend) {
			return eintrag;
		}

		// ---- AP-3.3: Verwaltungscontrols ------------------------------------
		var noteId = zahl(notiz.id);

		// `change` statt `click`: Auf einem Haken innerhalb eines <label>
		// erzeugt ein Klick auf den Text ein zweites, weitergeleitetes
		// Klick-Ereignis - mit `click` liefe die Aktion doppelt.
		haken.addEventListener('change', function () {
			schalteNotizUm(noteId, haken);
		});

		var aktionen = document.createElement('div');
		aktionen.className = M + '-notiz__aktionen';

		var bearbeitenKnopf = document.createElement('button');
		bearbeitenKnopf.type = 'button';
		bearbeitenKnopf.className = M + '-notiz__bearbeiten';
		bearbeitenKnopf.textContent = t('bearbeiten');
		bearbeitenKnopf.addEventListener('click', function () {
			starteBearbeiten(eintrag, noteId, text.textContent);
		});

		var loeschenKnopf = document.createElement('button');
		loeschenKnopf.type = 'button';
		loeschenKnopf.className = M + '-notiz__loeschen';
		loeschenKnopf.textContent = t('loeschen');
		loeschenKnopf.addEventListener('click', function () {
			loescheNotiz(noteId);
		});

		aktionen.appendChild(bearbeitenKnopf);
		aktionen.appendChild(loeschenKnopf);
		eintrag.appendChild(aktionen);

		return eintrag;
	}

	/**
	 * Das Feld „Frage hinzufuegen" unter der Liste bauen.
	 *
	 * @since AP-3.3
	 * @returns {Element}
	 */
	function baueNeuBereich() {
		var bereich = document.createElement('div');
		bereich.className = M + '-neu';

		var feld = document.createElement('input');
		feld.type = 'text';
		feld.className = M + '-neu__feld';
		feld.placeholder = t('neueFrage');
		feld.setAttribute('aria-label', t('neueFrage'));

		var knopf = document.createElement('button');
		knopf.type = 'button';
		knopf.className = M + '-neu__knopf';
		knopf.textContent = t('hinzufuegen');

		function absenden() {
			legeNotizAn(feld.value);
		}

		knopf.addEventListener('click', absenden);

		// Enter im Feld soll dasselbe tun wie der Knopf. Das Feld steht in
		// keinem <form>, es gibt also kein Absenden von selbst.
		feld.addEventListener('keydown', function (event) {
			if ('Enter' === event.key || 13 === event.keyCode) {
				event.preventDefault();
				absenden();
			}
		});

		bereich.appendChild(feld);
		bereich.appendChild(knopf);

		return bereich;
	}

	/**
	 * Die Notizenliste in den Koerper schreiben.
	 *
	 * @param {Array}   notizen
	 * @param {boolean} nurLesend
	 * @returns {void}
	 */
	function setzeListe(notizen, nurLesend) {
		if (!koerper) {
			return;
		}

		leere(koerper);

		if (!notizen || !notizen.length) {
			// Im Verwaltungsmodus darf hier NICHT `setzeMeldung()` stehen und
			// danach abgebrochen werden: Das Feld „Frage hinzufuegen" unten
			// ist gerade dann am wichtigsten, wenn die Wand noch leer ist.
			koerper.appendChild(baueMeldung(t('leer'), 'leer'));
		} else {
			var liste = document.createElement('ul');
			liste.className = M + '-liste';

			for (var i = 0; i < notizen.length; i++) {
				if (notizen[i] && 'undefined' !== typeof notizen[i].id) {
					liste.appendChild(baueNotiz(notizen[i], nurLesend));
				}
			}

			koerper.appendChild(liste);
		}

		if (!nurLesend) {
			koerper.appendChild(baueNeuBereich());
		}

		erfuelleFokusWunsch();
	}

	// =====================================================================
	// MODAL - FOKUS UND TASTATUR
	// =====================================================================

	/**
	 * Die fokussierbaren Elemente im Dialog.
	 *
	 * @returns {Array}
	 */
	function fokussierbare() {
		if (!dialog) {
			return [];
		}

		var alle = dialog.querySelectorAll(FOKUSSIERBAR);
		var liste = [];

		for (var i = 0; i < alle.length; i++) {
			var element = alle[i];
			if (element.hasAttribute('disabled')) {
				continue;
			}
			if ('-1' === element.getAttribute('tabindex')) {
				continue;
			}
			if ('hidden' === element.getAttribute('type')) {
				continue;
			}
			liste.push(element);
		}

		return liste;
	}

	/**
	 * Fokusfalle: Tab verlaesst den Dialog nicht.
	 *
	 * @param {KeyboardEvent} event
	 * @returns {void}
	 */
	function fokusFalle(event) {
		var liste = fokussierbare();

		if (!liste.length) {
			event.preventDefault();
			if (koerper && 'function' === typeof koerper.focus) {
				koerper.focus();
			}
			return;
		}

		var erstes = liste[0];
		var letztes = liste[liste.length - 1];
		var aktiv = document.activeElement;

		if (!aktiv || !dialog.contains(aktiv)) {
			event.preventDefault();
			erstes.focus();
			return;
		}

		if (event.shiftKey && aktiv === erstes) {
			event.preventDefault();
			letztes.focus();
		} else if (!event.shiftKey && aktiv === letztes) {
			event.preventDefault();
			erstes.focus();
		}
	}

	/**
	 * Tastatur am offenen Modal: Escape schliesst, Tab bleibt drin.
	 *
	 * @param {KeyboardEvent} event
	 * @returns {void}
	 */
	function beiTaste(event) {
		if (!overlay) {
			return;
		}

		if ('Escape' === event.key || 'Esc' === event.key || 27 === event.keyCode) {
			event.preventDefault();
			schliesse(true);
			return;
		}

		if ('Tab' === event.key || 9 === event.keyCode) {
			fokusFalle(event);
		}
	}

	// =====================================================================
	// MODAL - OEFFNEN UND SCHLIESSEN
	// =====================================================================

	/**
	 * Das Modal mit einer Notizenliste zeigen.
	 *
	 * @param {number|null} classId Klassen-ID im Lehrer-Fall, sonst `null`.
	 *                              Schueler kennen ihre Klassen-ID nicht und
	 *                              brauchen sie auch nicht - der Endpunkt
	 *                              leitet sie aus der Sitzung ab.
	 * @param {Array}       notizen  Eintraege {id, text, ist_erledigt}
	 * @param {Object}      [optionen] `{verwaltbar: true}` schaltet die
	 *                      Verwaltungscontrols frei (AP-3.3). Vorgabe:
	 *                      `{verwaltbar: false}` - reine Leseansicht.
	 * @returns {void}
	 */
	function open(classId, notizen, optionen) {
		oeffneGeruest();

		optionen = (optionen && 'object' === typeof optionen) ? optionen : {};

		// ZWEI BEDINGUNGEN, NICHT EINE. `verwaltbar: true` allein genuegt
		// nicht: Ohne brauchbare Klassen-ID koennte `legeNotizAn()` gar nicht
		// sagen, wohin die neue Notiz gehoert. `classId === null` ist zudem
		// die Schueler-Kennung aus dem Plan (AP-3.2, Schritt 6) - dort sind
		// die Haken immer nur zum Ansehen da.
		var klasse = zahl(classId);
		var kannVerwalten = (true === optionen.verwaltbar) && klasse > 0;

		aktuelleKlasse = kannVerwalten ? klasse : null;
		verwaltbar = kannVerwalten;

		if (dialog) {
			// Ein Haken, den die Lehrperson auch bedienen darf, ist ein
			// anderer Zustand als eine reine Leseansicht - AP-3.4 soll beides
			// unterscheiden koennen, ohne die Checkboxen abzufragen.
			dialog.setAttribute('data-modus', kannVerwalten ? 'verwalten' : 'lesen');
		}

		setzeListe(notizen, !kannVerwalten);
		setzeFokus();
	}

	/**
	 * Das Modal mit einer Meldung statt einer Liste zeigen.
	 *
	 * Oeffentlich, weil AP-3.3 dieselbe Darstellung fuer fehlgeschlagene
	 * AJAX-Aufrufe braucht.
	 *
	 * @param {string} text
	 * @param {string} art  Zusatzklasse ohne Prefix, z. B. 'fehler'
	 * @returns {void}
	 */
	function openMitMeldung(text, art) {
		oeffneGeruest();

		// Eine Meldung ist keine verwaltbare Wand - sonst versuchte ein
		// spaeter eintreffendes `ladeListeNeu()` in sie hineinzuschreiben.
		aktuelleKlasse = null;
		verwaltbar = false;

		if (dialog) {
			dialog.setAttribute('data-modus', 'meldung');
		}

		setzeMeldung(text, art);
		setzeFokus();
	}

	/**
	 * Den Fokus in den frisch befuellten Dialog legen.
	 *
	 * Auf den Schliessen-Knopf, nicht auf das erste Element im Koerper: Er ist
	 * in jedem Zustand vorhanden, und „Schliessen" ist die Aktion, die eine
	 * Person mit Tastatur zuerst braucht.
	 *
	 * @returns {void}
	 */
	function setzeFokus() {
		if (schliessKnopf && 'function' === typeof schliessKnopf.focus) {
			schliessKnopf.focus();
		}
	}

	/**
	 * Den vorgemerkten Fokus nach einem Neuaufbau der Liste setzen.
	 *
	 * Wird von `setzeListe()` aufgerufen und verbraucht den Wunsch dabei -
	 * ein Neuaufbau ohne vorherige Schreibaktion (z. B. beim ersten Oeffnen)
	 * soll den Fokus nicht verschieben, dort entscheidet `setzeFokus()`.
	 *
	 * @since AP-3.3
	 * @returns {void}
	 */
	function erfuelleFokusWunsch() {
		var wunsch = fokusWunsch;
		fokusWunsch = null;

		if (!wunsch || !koerper) {
			return;
		}

		var ziel = null;

		if ('eingabe' === wunsch.art) {
			ziel = koerper.querySelector('.' + M + '-neu__feld');
		} else if (wunsch.noteId) {
			var eintrag = koerper.querySelector(
				'.' + M + '-notiz[data-note-id="' + wunsch.noteId + '"]'
			);
			if (eintrag) {
				ziel = eintrag.querySelector(
					'haken' === wunsch.art
						? '.' + M + '-notiz__haken'
						: '.' + M + '-notiz__bearbeiten'
				);
			}
		}

		// Kein Rueckfall auf irgendein anderes Element: Ist die Notiz weg
		// (geloescht, von jemand anderem abgeraeumt), faengt die Fokusfalle
		// beim naechsten Tab-Druck.
		if (ziel && 'function' === typeof ziel.focus) {
			ziel.focus();
		}
	}

	/**
	 * Das Modal schliessen.
	 *
	 * @param {boolean} fokusZurueck Fokus zurueck auf den Ausloeser?
	 * @returns {void}
	 */
	function close(fokusZurueck) {
		if (!overlay) {
			return;
		}

		// Laufende Abrufe entwerten - eine spaeter eintreffende Antwort darf
		// kein bereits geschlossenes Modal wiederbeleben.
		abrufZaehler += 1;

		document.removeEventListener('keydown', beiTaste);

		if (overlay.parentNode) {
			overlay.parentNode.removeChild(overlay);
		}

		overlay = null;
		dialog = null;
		koerper = null;
		schliessKnopf = null;
		statusZeile = null;

		// AP-3.3: Der Verwaltungszustand gehoert zum offenen Modal, nicht zur
		// Seite. Bliebe `verwaltbar` stehen, schriebe eine verspaetete Antwort
		// in ein Modal, das es nicht mehr gibt.
		aktuelleKlasse = null;
		verwaltbar = false;
		fokusWunsch = null;
		schreibtGerade = false;

		document.body.style.overflow = overflowVorher;

		var ziel = ausloeser;
		ausloeser = null;

		if (fokusZurueck && ziel
			&& 'function' === typeof ziel.focus
			&& document.body.contains(ziel)) {
			ziel.focus();
		}
	}

	/** Interner Kurzname - `close()` ist der oeffentliche. */
	var schliesse = close;

	// =====================================================================
	// DATENABRUF (SCHUELER-/BESUCHERMODUS)
	// =====================================================================

	/**
	 * Die Fragenwand der laufenden Klassensitzung holen und zeigen.
	 *
	 * Der Endpunkt antwortet auf JEDEN Fehlschlag gleich (HTTP 404,
	 * `cbd_fragenwand_not_available`) - fehlende Sitzung, abgelaufenes Token,
	 * `?classroom=` passt nicht zum Token. Diese Absicht (AP-2.3: kein
	 * Prueffeld fuer geratene Klassen-IDs) wird hier nicht unterlaufen: Auch
	 * das Frontend zeigt fuer alle diese Faelle denselben Satz.
	 *
	 * @returns {void}
	 */
	function ladeSchuelerwand() {
		var url = baueAbrufUrl();

		if ('' === url) {
			warne('Es ist keine REST-Adresse bekannt (cbdFragenwandFrontend.restUrl fehlt).');
			openMitMeldung(t('fehler'), 'fehler');
			return;
		}

		if ('function' !== typeof window.fetch) {
			warne('Dieser Browser kennt fetch() nicht; die Fragenwand kann nicht geladen werden.');
			openMitMeldung(t('fehler'), 'fehler');
			return;
		}

		abrufZaehler += 1;
		var meineNummer = abrufZaehler;

		oeffneGeruest();
		setzeMeldung(t('laden'), 'laden');
		setzeFokus();

		window.fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { Accept: 'application/json' }
		}).then(function (antwort) {
			if (meineNummer !== abrufZaehler) {
				return null;
			}

			if (404 === antwort.status) {
				openMitMeldung(t('keineSitzung'), 'keine-sitzung');
				return null;
			}

			if (!antwort.ok) {
				openMitMeldung(t('fehler'), 'fehler');
				return null;
			}

			return antwort.json();
		}).then(function (daten) {
			if (null === daten || meineNummer !== abrufZaehler) {
				return;
			}

			open(null, (daten && daten.notes) ? daten.notes : []);
		})['catch'](function (fehler) {
			if (meineNummer !== abrufZaehler) {
				return;
			}
			warne('Der Abruf der Fragenwand ist fehlgeschlagen: ' + fehler);
			openMitMeldung(t('fehler'), 'fehler');
		});
	}

	// =====================================================================
	// KLASSENAUSWAHL (LEHRPERSON)
	// =====================================================================

	/**
	 * Die Klassenauswahl schliessen.
	 *
	 * @since AP-3.3
	 * @returns {void}
	 */
	function schliesseKlassenauswahl() {
		if (!wahlOverlay) {
			return;
		}

		document.removeEventListener('keydown', beiWahlTaste);

		if (wahlOverlay.parentNode) {
			wahlOverlay.parentNode.removeChild(wahlOverlay);
		}

		wahlOverlay = null;
	}

	/**
	 * Escape schliesst die Klassenauswahl.
	 *
	 * Eine eigene Tastaturbehandlung, nicht `beiTaste`: Das Modal ist
	 * waehrend der Auswahl noch gar nicht offen, `beiTaste` haengt zu diesem
	 * Zeitpunkt nicht an `document`.
	 *
	 * @since AP-3.3
	 * @param {KeyboardEvent} event
	 * @returns {void}
	 */
	function beiWahlTaste(event) {
		if (!wahlOverlay) {
			return;
		}

		if ('Escape' === event.key || 'Esc' === event.key || 27 === event.keyCode) {
			event.preventDefault();
			schliesseKlassenauswahl();
		}
	}

	/**
	 * Die Klasse erfragen, deren Fragenwand geoeffnet werden soll.
	 *
	 * OPTISCHES UND STRUKTURELLES VORBILD: `showClassSelector()` in
	 * assets/js/board-mode.js (Overlay, Dialog mit Ueberschrift und
	 * Erklaersatz, ein Knopf je Klasse mit Dashicon, Abbrechen-Knopf,
	 * Backdrop-Klick bricht ab). Uebernommen ist das Muster, NICHT der Code:
	 * Jene Datei bleibt unveraendert und wird auch nicht aufgerufen (AP-3.3,
	 * Schritt 5).
	 *
	 * DREI UNTERSCHIEDE ZUM VORBILD, alle beabsichtigt:
	 *   1. KEINE Option „Persoenlich (lokal)" (`data-class-id="0"`). Die
	 *      Fragenwand kennt keinen lokalen Modus - jede Notiz gehoert zu einer
	 *      echten Klasse, sonst koennte sie niemand sehen.
	 *   2. Eigene Klassennamen (`cbd-fragenwand-klassenwahl-*`) statt der
	 *      Tafelmodus-Klassen: Die Gestaltung kommt in AP-3.4 und soll die
	 *      Tafelmodus-Dialoge nicht mitverstellen.
	 *   3. Aufgebaut mit `document.createElement` statt `innerHTML`. Der
	 *      Klassenname kommt aus der Datenbank; mit `textContent` braucht es
	 *      gar keine Maskierhilfe wie `_escHtml()` im Vorbild.
	 *
	 * @since AP-3.3
	 * @param {Function} callback bekommt die gewaehlte Klassen-ID (Zahl)
	 * @returns {void}
	 */
	function zeigeKlassenauswahl(callback) {
		var klassen = klassenDaten().classes;

		if (!klassen || !klassen.length) {
			// Sollte `lehrerFlow()` bereits abgefangen haben - hier nur als
			// Sicherheitsnetz, damit nie ein leerer Dialog erscheint.
			openMitMeldung(t('keineKlassen'), 'keine-klassen');
			return;
		}

		schliesseKlassenauswahl();

		wahlOverlay = document.createElement('div');
		wahlOverlay.className = M + '-klassenwahl-overlay';

		var wahlDialog = document.createElement('div');
		wahlDialog.className = M + '-klassenwahl-dialog';
		wahlDialog.setAttribute('role', 'dialog');
		wahlDialog.setAttribute('aria-modal', 'true');
		wahlDialog.setAttribute('aria-labelledby', WAHL_TITEL_ID);

		var wahlTitel = document.createElement('h2');
		wahlTitel.className = M + '-klassenwahl-titel';
		wahlTitel.id = WAHL_TITEL_ID;
		wahlTitel.textContent = t('klassenwahlTitel');

		var hinweis = document.createElement('p');
		hinweis.className = M + '-klassenwahl-hinweis';
		hinweis.textContent = t('klassenwahlHinweis');

		var optionen = document.createElement('div');
		optionen.className = M + '-klassenwahl-optionen';

		var ersterKnopf = null;

		for (var i = 0; i < klassen.length; i++) {
			var klasse = klassen[i];
			var klassenId = zahl(klasse && klasse.id);

			if (klassenId <= 0) {
				continue;
			}

			var knopf = document.createElement('button');
			knopf.type = 'button';
			knopf.className = M + '-klassenwahl-option';
			knopf.setAttribute('data-class-id', String(klassenId));

			// Wie im Vorbild ein Dashicon. Ist die Dashicons-Schrift auf
			// dieser Seite nicht geladen, bleibt das Span leer - der
			// Klassenname daneben steht davon unberuehrt.
			var symbol = document.createElement('span');
			symbol.className = 'dashicons dashicons-groups';
			symbol.setAttribute('aria-hidden', 'true');

			var name = document.createElement('span');
			name.className = M + '-klassenwahl-name';
			name.textContent = ('string' === typeof klasse.name && '' !== klasse.name)
				? klasse.name
				: String(klassenId);

			knopf.appendChild(symbol);
			knopf.appendChild(name);

			// Die Zuweisung in einer eigenen Funktion, damit die Schleifen-
			// variable nicht ueber alle Knoepfe hinweg geteilt wird (ES5
			// kennt kein `let`).
			(function (gewaehlt) {
				knopf.addEventListener('click', function () {
					schliesseKlassenauswahl();
					callback(gewaehlt);
				});
			}(klassenId));

			if (!ersterKnopf) {
				ersterKnopf = knopf;
			}

			optionen.appendChild(knopf);
		}

		var aktionen = document.createElement('div');
		aktionen.className = M + '-klassenwahl-aktionen';

		var abbrechenKnopf = document.createElement('button');
		abbrechenKnopf.type = 'button';
		abbrechenKnopf.className = M + '-klassenwahl-abbrechen';
		abbrechenKnopf.textContent = t('abbrechen');
		abbrechenKnopf.addEventListener('click', schliesseKlassenauswahl);

		aktionen.appendChild(abbrechenKnopf);

		wahlDialog.appendChild(wahlTitel);
		wahlDialog.appendChild(hinweis);
		wahlDialog.appendChild(optionen);
		wahlDialog.appendChild(aktionen);
		wahlOverlay.appendChild(wahlDialog);

		// Backdrop-Klick bricht ab. Die Pruefung auf `e.target === overlay`
		// ist noetig, weil das Ereignis aus dem Dialog hochblubbert.
		wahlOverlay.addEventListener('click', function (e) {
			if (e.target === wahlOverlay) {
				schliesseKlassenauswahl();
			}
		});

		document.body.appendChild(wahlOverlay);
		document.addEventListener('keydown', beiWahlTaste);

		if (ersterKnopf && 'function' === typeof ersterKnopf.focus) {
			ersterKnopf.focus();
		}
	}

	// =====================================================================
	// LEHRER-WEG: WAND OEFFNEN UND VERWALTEN
	// =====================================================================

	/**
	 * Der Einstieg fuer angemeldete Lehrpersonen.
	 *
	 * DAS IST DER ERWEITERUNGSPUNKT AUS AP-3.2: `oeffneAusTrigger()` ruft
	 * `window.CBDFragenwand.lehrerFlow` auf, sofern gesetzt. Diese Funktion
	 * setzt ihn - an `oeffneAusTrigger()` selbst musste dafuer nichts
	 * geaendert werden.
	 *
	 * @since AP-3.3
	 * @param {Element} trigger
	 * @returns {void}
	 */
	function lehrerFlow(trigger) {
		// `oeffneAusTrigger()` hat den Ausloeser bereits gesetzt; wird
		// `lehrerFlow()` von aussen aufgerufen, holen wir das hier nach.
		if (trigger) {
			ausloeser = trigger;
		}

		var klassen = klassenDaten().classes;

		if (!klassen || !klassen.length) {
			openMitMeldung(t('keineKlassen'), 'keine-klassen');
			return;
		}

		zeigeKlassenauswahl(function (classId) {
			ladeLehrerwand(classId);
		});
	}

	/**
	 * Die Wand einer Klasse laden und im Verwaltungsmodus zeigen.
	 *
	 * @since AP-3.3
	 * @param {number} classId
	 * @returns {void}
	 */
	function ladeLehrerwand(classId) {
		abrufZaehler += 1;
		var meineNummer = abrufZaehler;

		oeffneGeruest();
		setzeMeldung(t('laden'), 'laden');
		setzeFokus();

		rufeAjax('cbd_fragenwand_get_notes', { class_id: classId }, function (antwort) {
			if (meineNummer !== abrufZaehler) {
				return;
			}

			if (!antwort || true !== antwort.success) {
				openMitMeldung(fehlerText(antwort) || t('fehler'), 'fehler');
				return;
			}

			open(classId, notizenAus(antwort), { verwaltbar: true });
		}, function () {
			if (meineNummer !== abrufZaehler) {
				return;
			}
			openMitMeldung(t('fehler'), 'fehler');
		});
	}

	/**
	 * Die Notizen aus einer AJAX-Antwort holen.
	 *
	 * @since AP-3.3
	 * @param {Object} antwort
	 * @returns {Array}
	 */
	function notizenAus(antwort) {
		if (antwort && antwort.data && antwort.data.notes && antwort.data.notes.length) {
			return antwort.data.notes;
		}
		return [];
	}

	/**
	 * Die Liste im offenen Modal neu vom Server holen.
	 *
	 * NUR DER KOERPER WIRD ERSETZT, das Overlay bleibt stehen (AP-3.3,
	 * Schritt 2). Und geholt wird sie wirklich neu, statt das DOM
	 * fortzuschreiben: Die Reihenfolge steht im `ORDER BY` des Servers, ein
	 * abgehakter Eintrag rutscht dadurch ans Ende der Liste.
	 *
	 * @since AP-3.3
	 * @returns {void}
	 */
	function ladeListeNeu() {
		if (!verwaltbar || !aktuelleKlasse || !koerper) {
			return;
		}

		var klasse = aktuelleKlasse;

		abrufZaehler += 1;
		var meineNummer = abrufZaehler;

		rufeAjax('cbd_fragenwand_get_notes', { class_id: klasse }, function (antwort) {
			// Zwischenzeitlich geschlossen, andere Klasse gewaehlt oder
			// bereits eine neuere Antwort verarbeitet: verwerfen.
			if (meineNummer !== abrufZaehler || !koerper || klasse !== aktuelleKlasse) {
				return;
			}

			if (!antwort || true !== antwort.success) {
				setzeStatus(fehlerText(antwort) || t('fehler'));
				return;
			}

			setzeListe(notizenAus(antwort), false);
		}, function () {
			if (meineNummer !== abrufZaehler || !koerper) {
				return;
			}
			setzeStatus(t('fehler'));
		});
	}

	/**
	 * Eine Notiz abhaken bzw. wieder oeffnen.
	 *
	 * @since AP-3.3
	 * @param {number}  noteId
	 * @param {Element} haken Die Checkbox - fuer den Rueckbau bei Fehlschlag.
	 * @returns {void}
	 */
	function schalteNotizUm(noteId, haken) {
		if (schreibtGerade) {
			// Der Haken hat sich durch den Klick bereits umgestellt; ohne
			// diesen Rueckbau zeigte er einen Zustand, den niemand gespeichert
			// hat.
			haken.checked = !haken.checked;
			return;
		}

		schreibtGerade = true;
		setzeStatus('');
		fokusWunsch = { art: 'haken', noteId: noteId };

		rufeAjax('cbd_fragenwand_toggle_note', { note_id: noteId }, function (antwort) {
			schreibtGerade = false;

			if (!antwort || true !== antwort.success) {
				haken.checked = !haken.checked;
				fokusWunsch = null;
				setzeStatus(fehlerText(antwort) || t('schreibFehler'));
				return;
			}

			ladeListeNeu();
		}, function () {
			schreibtGerade = false;
			haken.checked = !haken.checked;
			fokusWunsch = null;
			setzeStatus(t('schreibFehler'));
		});
	}

	/**
	 * Eine neue Notiz anlegen.
	 *
	 * Ein leerer Text wird bewusst NICHT hier abgefangen, sondern an den
	 * Server geschickt: Die Regel („Text darf nicht leer sein.") steht in
	 * AP-2.2 und soll nicht in einer zweiten, stillschweigend veraltenden
	 * Fassung im Browser stehen. Der Server antwortet mit genau diesem Satz,
	 * und die Statuszeile zeigt ihn.
	 *
	 * @since AP-3.3
	 * @param {string} text
	 * @returns {void}
	 */
	function legeNotizAn(text) {
		if (schreibtGerade || !aktuelleKlasse) {
			return;
		}

		schreibtGerade = true;
		setzeStatus('');
		fokusWunsch = { art: 'eingabe' };

		rufeAjax('cbd_fragenwand_add_note', {
			class_id: aktuelleKlasse,
			text: text
		}, function (antwort) {
			schreibtGerade = false;

			if (!antwort || true !== antwort.success) {
				fokusWunsch = null;
				setzeStatus(fehlerText(antwort) || t('schreibFehler'));
				return;
			}

			// Das Feld wird beim Neuaufbau der Liste ohnehin neu gebaut und
			// ist dadurch leer - ein ausdrueckliches Leeren waere doppelt.
			ladeListeNeu();
		}, function () {
			schreibtGerade = false;
			fokusWunsch = null;
			setzeStatus(t('schreibFehler'));
		});
	}

	/**
	 * Den Text einer Notiz aendern.
	 *
	 * @since AP-3.3
	 * @param {number} noteId
	 * @param {string} text
	 * @returns {void}
	 */
	function speichereNotiz(noteId, text) {
		if (schreibtGerade) {
			return;
		}

		schreibtGerade = true;
		setzeStatus('');
		fokusWunsch = { art: 'bearbeiten', noteId: noteId };

		rufeAjax('cbd_fragenwand_edit_note', {
			note_id: noteId,
			text: text
		}, function (antwort) {
			schreibtGerade = false;

			if (!antwort || true !== antwort.success) {
				fokusWunsch = null;
				setzeStatus(fehlerText(antwort) || t('schreibFehler'));
				return;
			}

			ladeListeNeu();
		}, function () {
			schreibtGerade = false;
			fokusWunsch = null;
			setzeStatus(t('schreibFehler'));
		});
	}

	/**
	 * Eine Notiz loeschen.
	 *
	 * @since AP-3.3
	 * @param {number} noteId
	 * @returns {void}
	 */
	function loescheNotiz(noteId) {
		if (schreibtGerade) {
			return;
		}

		schreibtGerade = true;
		setzeStatus('');
		fokusWunsch = { art: 'eingabe' };

		rufeAjax('cbd_fragenwand_delete_note', { note_id: noteId }, function (antwort) {
			schreibtGerade = false;

			if (!antwort || true !== antwort.success) {
				fokusWunsch = null;
				setzeStatus(fehlerText(antwort) || t('schreibFehler'));
				return;
			}

			ladeListeNeu();
		}, function () {
			schreibtGerade = false;
			fokusWunsch = null;
			setzeStatus(t('schreibFehler'));
		});
	}

	/**
	 * Eine Notiz an Ort und Stelle bearbeitbar machen.
	 *
	 * Kein zweites Modal und kein gemeinsames Eingabefeld unter der Liste:
	 * Der Text soll dort stehen, wo er hingehoert, damit beim Aendern
	 * sichtbar bleibt, welche der Notizen gemeint ist.
	 *
	 * @since AP-3.3
	 * @param {Element} eintrag Das <li> der Notiz
	 * @param {number}  noteId
	 * @param {string}  altText
	 * @returns {void}
	 */
	function starteBearbeiten(eintrag, noteId, altText) {
		if (!eintrag || '1' === eintrag.getAttribute('data-bearbeitung')) {
			return;
		}

		eintrag.setAttribute('data-bearbeitung', '1');

		var beschriftung = eintrag.querySelector('.' + M + '-notiz__label');
		var aktionen = eintrag.querySelector('.' + M + '-notiz__aktionen');

		if (beschriftung) {
			beschriftung.hidden = true;
		}
		if (aktionen) {
			aktionen.hidden = true;
		}

		var bereich = document.createElement('div');
		bereich.className = M + '-notiz__bearbeitung';

		var feld = document.createElement('input');
		feld.type = 'text';
		feld.className = M + '-notiz__feld';
		feld.value = altText || '';
		feld.setAttribute('aria-label', t('bearbeiten'));

		var speichernKnopf = document.createElement('button');
		speichernKnopf.type = 'button';
		speichernKnopf.className = M + '-notiz__speichern';
		speichernKnopf.textContent = t('speichern');

		var abbrechenKnopf = document.createElement('button');
		abbrechenKnopf.type = 'button';
		abbrechenKnopf.className = M + '-notiz__abbrechen';
		abbrechenKnopf.textContent = t('abbrechen');

		function beende() {
			if (bereich.parentNode) {
				bereich.parentNode.removeChild(bereich);
			}
			if (beschriftung) {
				beschriftung.hidden = false;
			}
			if (aktionen) {
				aktionen.hidden = false;
			}
			eintrag.removeAttribute('data-bearbeitung');
		}

		speichernKnopf.addEventListener('click', function () {
			speichereNotiz(noteId, feld.value);
		});

		abbrechenKnopf.addEventListener('click', function () {
			beende();

			var zurueck = eintrag.querySelector('.' + M + '-notiz__bearbeiten');
			if (zurueck && 'function' === typeof zurueck.focus) {
				zurueck.focus();
			}
		});

		feld.addEventListener('keydown', function (event) {
			if ('Enter' === event.key || 13 === event.keyCode) {
				event.preventDefault();
				speichereNotiz(noteId, feld.value);
				return;
			}

			if ('Escape' === event.key || 'Esc' === event.key || 27 === event.keyCode) {
				// NICHT weiterreichen: `beiTaste` haengt an `document` und
				// schloesse sonst gleich das ganze Modal, obwohl nur die
				// Bearbeitung gemeint war.
				event.preventDefault();
				event.stopPropagation();
				beende();

				var zurueck = eintrag.querySelector('.' + M + '-notiz__bearbeiten');
				if (zurueck && 'function' === typeof zurueck.focus) {
					zurueck.focus();
				}
			}
		});

		bereich.appendChild(feld);
		bereich.appendChild(speichernKnopf);
		bereich.appendChild(abbrechenKnopf);
		eintrag.appendChild(bereich);

		feld.focus();
		feld.select();
	}

	// =====================================================================
	// TRIGGER
	// =====================================================================

	/**
	 * Ein Fragenwand-Verweis wurde angeklickt.
	 *
	 * DER ERWEITERUNGSPUNKT AUS AP-3.2 liegt hier: Ist
	 * `window.CBDFragenwand.lehrerFlow` eine Funktion, bekommt sie den
	 * Ausloeser und uebernimmt vollstaendig (Klassenauswahl, danach
	 * `open(classId, notizen, {verwaltbar: true})`). AP-3.3 hat genau diese
	 * eine Eigenschaft gesetzt - an dieser Funktion war nichts zu aendern.
	 * Der Konsolenhinweis unten ist seither nur noch ein Rueckfall fuer den
	 * Fall, dass jemand `lehrerFlow` von aussen wieder entfernt.
	 *
	 * @param {Element} trigger
	 * @returns {void}
	 */
	function oeffneAusTrigger(trigger) {
		ausloeser = trigger || null;

		if (istLehrkraft()) {
			if ('function' === typeof window.CBDFragenwand.lehrerFlow) {
				window.CBDFragenwand.lehrerFlow(trigger);
				return;
			}

			warne('window.CBDFragenwand.lehrerFlow ist keine Funktion - die Klassenauswahl fuer Lehrpersonen steht nicht zur Verfuegung.');
			return;
		}

		ladeSchuelerwand();
	}

	/**
	 * Delegierter Klick-Listener.
	 *
	 * An `document`, nicht an den Verweisen: Der Trigger kann aus dem
	 * Fliesstext kommen, ab Phase 4 aber auch aus dem Inhaltsverzeichnis des
	 * Themes - und dieses Markup entsteht ausserhalb dieses Plugins.
	 *
	 * @param {MouseEvent} event
	 * @returns {void}
	 */
	function beiKlick(event) {
		// Modifizierte Klicks und Klicks mit einer anderen Maustaste dem
		// Browser ueberlassen: „In neuem Tab oeffnen" auf einem `href="#"`
		// ist zwar sinnlos, aber es ist die Entscheidung der Nutzerin.
		if (event.defaultPrevented || event.button > 0
			|| event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
			return;
		}

		var trigger = naechsterVerweis(event.target);

		if (!trigger) {
			return;
		}

		// Der gespeicherte `href="#"` ist rein strukturell (AP-3.1) - ohne
		// preventDefault() sprang die Seite nach oben und haengte ein `#` an
		// die Adresse.
		event.preventDefault();

		oeffneAusTrigger(trigger);
	}

	// =====================================================================
	// OEFFENTLICHE SCHNITTSTELLE
	// =====================================================================

	/**
	 * `window.CBDFragenwand` - die sechste oeffentliche `window.cbd*`-/
	 * `window.CBD*`-Schnittstelle des Plugins (siehe CLAUDE.md, Abschnitt
	 * „Oeffentliche window.cbd*-Schnittstellen").
	 *
	 * | Name             | Zweck                                            |
	 * |------------------|--------------------------------------------------|
	 * | `open`           | Modal mit Notizenliste zeigen (3. Argument: `{verwaltbar}`) |
	 * | `openMitMeldung` | Modal mit einem Satz statt einer Liste zeigen    |
	 * | `close`          | Modal schliessen                                 |
	 * | `lehrerFlow`     | Klassenauswahl, danach die Wand im Verwaltungsmodus (AP-3.3) |
	 *
	 * Ein bereits vorhandenes Objekt wird ergaenzt statt ersetzt - die
	 * Reihenfolge, in der Skripte laufen, soll keine Rolle spielen.
	 */
	var api = window.CBDFragenwand || {};

	api.open = open;
	api.openMitMeldung = openMitMeldung;
	api.close = close;
	api.lehrerFlow = lehrerFlow;

	window.CBDFragenwand = api;

	// =====================================================================
	// START
	// =====================================================================

	/**
	 * Den Klick-Listener anmelden.
	 *
	 * Das Script laeuft im Footer, `DOMContentLoaded` ist zu diesem Zeitpunkt
	 * also noch nicht gefeuert - der Ereignisname allein waere trotzdem
	 * unzuverlaessig, falls die Datei je anders eingebunden wird. Deshalb die
	 * Weiche ueber `document.readyState`.
	 *
	 * @returns {void}
	 */
	function starte() {
		document.addEventListener('click', beiKlick);
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', starte);
	} else {
		starte();
	}
})(window, document);
