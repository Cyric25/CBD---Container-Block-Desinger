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
 * IN DIESEM AP ENTSTEHT NUR DIE LESEANSICHT. Fuer Schueler ist sie der
 * Endzustand (Nicht-Ziel in Abschnitt 2 des Plans: Schueler schreiben nie).
 * Die Verwaltungscontrols der Lehrperson kommen in AP-3.3, das Aussehen
 * (Post-it-Optik, Ausgrauen, Darkmode) in AP-3.4 - diese Datei setzt deshalb
 * bewusst KEINE Inline-Styles und bringt keine eigene CSS-Datei mit.
 *
 * @package ContainerBlockDesigner
 * @since Vorhaben „Fragenwand", Phase 3 (AP-3.2)
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
	 * Rueckfalltexte.
	 *
	 * Der Server schickt dieselben Texte uebersetzbar mit (siehe
	 * `CBD_Fragenwand::enqueue_frontend_assets()`, Schluessel `texte`). Diese
	 * Tabelle greift nur, wenn `wp_localize_script()` nicht gelaufen ist -
	 * etwa weil die Seite das Objekt aus einem Cache ohne das Inline-Script
	 * ausliefert. Ein Modal ohne Beschriftung waere schlechter als eines mit
	 * unuebersetzter.
	 */
	var TEXTE_VORGABE = {
		titel: 'Fragenwand',
		schliessen: 'Schließen',
		laden: 'Fragenwand wird geladen …',
		keineSitzung: 'Keine aktive Klassensitzung.',
		fehler: 'Die Fragenwand konnte nicht geladen werden.',
		leer: 'Auf dieser Fragenwand steht noch nichts.'
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
	 *
	 * Der Inhalt des Koerpers entsteht spaeter - je nach Fall eine
	 * `ul.cbd-fragenwand-liste` oder ein `p.cbd-fragenwand-meldung`.
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

		kopf.appendChild(titel);
		kopf.appendChild(schliessKnopf);
		dialog.appendChild(kopf);
		dialog.appendChild(koerper);
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

		var absatz = document.createElement('p');
		absatz.className = M + '-meldung'
			+ (art ? ' ' + M + '-meldung--' + art : '');
		absatz.textContent = text;

		koerper.appendChild(absatz);
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

		return eintrag;
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
			setzeMeldung(t('leer'), 'leer');
			return;
		}

		var liste = document.createElement('ul');
		liste.className = M + '-liste';

		for (var i = 0; i < notizen.length; i++) {
			if (notizen[i] && 'undefined' !== typeof notizen[i].id) {
				liste.appendChild(baueNotiz(notizen[i], nurLesend));
			}
		}

		koerper.appendChild(liste);
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
	 * @param {Array}       notizen Eintraege {id, text, ist_erledigt}
	 * @returns {void}
	 */
	function open(classId, notizen) {
		oeffneGeruest();

		// `classId === null` ist die Schueler-Kennung aus dem Plan (AP-3.2,
		// Schritt 6). In DIESEM Arbeitspaket erreicht nur der Schueler-Zweig
		// `open()` - der Lehrer-Zweig endet bis AP-3.3 in `lehrerFlow`. Die
		// Unterscheidung steht trotzdem schon hier, damit AP-3.3 die
		// Verwaltungscontrols anhaengen kann, ohne diese Funktion umzubauen.
		var nurLesend = (null === classId || 'undefined' === typeof classId);

		if (dialog) {
			// Ein Haken, den die Lehrperson auch bedienen darf, ist ein
			// anderer Zustand als eine reine Leseansicht - AP-3.4 soll beides
			// unterscheiden koennen, ohne die Checkboxen abzufragen.
			dialog.setAttribute('data-modus', nurLesend ? 'lesen' : 'verwalten');
		}

		setzeListe(notizen, nurLesend);
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
	// TRIGGER
	// =====================================================================

	/**
	 * Ein Fragenwand-Verweis wurde angeklickt.
	 *
	 * DER ERWEITERUNGSPUNKT FUER AP-3.3 liegt hier: Ist
	 * `window.CBDFragenwand.lehrerFlow` eine Funktion, bekommt sie den
	 * Ausloeser und uebernimmt vollstaendig (Klassenauswahl, danach
	 * `open(classId, notizen)`). AP-3.3 setzt also nur diese eine
	 * Eigenschaft und muss an dieser Funktion nichts aendern.
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

			warne('Die Klassenauswahl fuer Lehrpersonen fehlt noch (AP-3.3 liefert window.CBDFragenwand.lehrerFlow nach).');
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
	 * | `open`           | Modal mit Notizenliste zeigen                    |
	 * | `openMitMeldung` | Modal mit einem Satz statt einer Liste zeigen    |
	 * | `close`          | Modal schliessen                                 |
	 * | `lehrerFlow`     | NICHT hier gesetzt - Erweiterungspunkt fuer AP-3.3 |
	 *
	 * Ein bereits vorhandenes Objekt wird ergaenzt statt ersetzt: Laedt AP-3.3
	 * sein Script frueher, bliebe `lehrerFlow` sonst auf der Strecke.
	 */
	var api = window.CBDFragenwand || {};

	api.open = open;
	api.openMitMeldung = openMitMeldung;
	api.close = close;

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
