/**
 * CBD Block-Referenz — Frontend
 *
 * Zwei Verhaltensweisen, gesteuert ueber `data-display-mode` am Verweis:
 *
 *   link   Sprung zum Zielblock bzw. Navigation zur Zielseite (Stand AP-1.3).
 *   modal  Der Zielblock erscheint in einem Overlay auf DIESER Seite.
 *
 * Der Verweis ist in beiden Faellen ein <a> mit vollstaendiger Ziel-URL.
 * Ohne JavaScript bleibt er also ein gewoehnlicher Link — das Modal entsteht
 * erst durch preventDefault() hier.
 *
 * ZWEI WEGE ZUM INHALT
 *   1. Liegt der Block auf DIESER Seite (data-same-page="true"), wird er aus
 *      dem DOM geklont. Kein Netzverkehr, keine Autorisierung noetig.
 *   2. Sonst ueber den Endpunkt `cbd/v1/block-html` (AP-2.4). Dessen Basis
 *      kommt aus `window.cbdBlockReference.restUrl` und wird NIE hier
 *      zusammengebaut: auf manchen Servern liefert `/wp-json/…` einen
 *      Apache-404, dort funktioniert nur `?rest_route=…`. Welche Form gilt,
 *      weiss allein `rest_url()` auf dem Server.
 *
 * WAS DAS MODAL BEWUSST NICHT KANN
 * Die WordPress-Interactivity-API hydriert nur Markup, das beim Laden der
 * Seite schon dastand. Nachtraeglich eingefuegtes — geklont wie nachgeladen —
 * bekommt keine Hydrierung. Klappen, Kopieren, Screenshot, PDF und
 * Tafelmodus waeren im Modal also tote Knoepfe. `entschaerfeInhalt()`
 * entfernt die Aktionsleiste deshalb und klappt eingeklappte Container auf.
 * Das Modal ist eine LESEANSICHT.
 */
(function () {
	'use strict';

	// =====================================================================
	// KONFIGURATION (aus wp_localize_script, siehe class-cbd-block-reference.php)
	// =====================================================================

	function konfig() {
		return window.cbdBlockReference || {};
	}

	/**
	 * Uebersetzter Text mit Rueckfall.
	 *
	 * @param {string} schluessel
	 * @param {string} vorgabe
	 * @return {string}
	 */
	function t(schluessel, vorgabe) {
		var k = konfig();
		var texte = (k && k.texte) ? k.texte : {};
		if (texte && typeof texte[schluessel] === 'string' && texte[schluessel] !== '') {
			return texte[schluessel];
		}
		return vorgabe;
	}

	function restBasis() {
		var k = konfig();
		return (k && typeof k.restUrl === 'string') ? k.restUrl : '';
	}

	/**
	 * Debug-Ausgabe. Gegated ueber window.cbdDebug (Projektkonvention).
	 */
	function debug() {
		if (window.cbdDebug && window.console && typeof console.log === 'function') {
			console.log.apply(console, ['[CBD Block-Referenz]'].concat(Array.prototype.slice.call(arguments)));
		}
	}

	// =====================================================================
	// HILFEN
	// =====================================================================

	/**
	 * Maskiert einen Wert fuer die Verwendung in einem Attributselektor.
	 * CSS.escape fehlt in aelteren Browsern - dann genuegt das Maskieren
	 * von Anfuehrungszeichen und Backslashes.
	 *
	 * @param {string} wert
	 * @return {string}
	 */
	function maskiere(wert) {
		var text = String(wert);
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(text);
		}
		return text.replace(/["\\]/g, '\\$&');
	}

	function leere(element) {
		while (element && element.firstChild) {
			element.removeChild(element.firstChild);
		}
	}

	/**
	 * Haengt einen Parameter an eine URL — gleichgueltig, ob sie schon eine
	 * Abfragezeichenkette hat. Das ist der Grund, warum die Basis vom Server
	 * kommen darf: `?rest_route=/cbd/v1/block-html` bleibt heil.
	 *
	 * @param {string} url
	 * @param {string} name
	 * @param {string} wert
	 * @return {string}
	 */
	function mitParameter(url, name, wert) {
		var text = String(url);
		var trenner = (text.indexOf('?') === -1) ? '?' : '&';
		return text + trenner + encodeURIComponent(name) + '=' + encodeURIComponent(wert);
	}

	/**
	 * Einen Parameter aus der aktuellen Adresse lesen.
	 *
	 * @param {string} name
	 * @return {string} leer, wenn nicht vorhanden
	 */
	function ausAdresse(name) {
		var muster = new RegExp('[?&]' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^&#]*)');
		var treffer = muster.exec(window.location.search || '');

		if (!treffer) {
			return '';
		}

		try {
			return decodeURIComponent(treffer[1].replace(/\+/g, ' '));
		} catch (fehler) {
			return treffer[1];
		}
	}

	// =====================================================================
	// ZIELSUCHE IM DOM
	// =====================================================================

	/**
	 * Liegt das Element im gerade offenen Modal?
	 *
	 * Ohne diese Pruefung faende die Suche den KLON im Modal statt des
	 * Originals — der Klon traegt dieselbe `data-stable-id`.
	 *
	 * @param {Node} element
	 * @return {boolean}
	 */
	function imModal(element) {
		return !!(modal && element && modal.contains(element));
	}

	/**
	 * Sucht das Zielelement: erst der HTML-Anker, dann die stableId.
	 * Treffer innerhalb des Modals werden uebergangen.
	 *
	 * @param {string} anchor
	 * @param {string} stableId
	 * @return {HTMLElement|null}
	 */
	function findeZiel(anchor, stableId) {
		if (anchor) {
			var perAnker = document.getElementById(anchor);
			if (perAnker && !imModal(perAnker)) {
				return perAnker;
			}
		}

		if (stableId) {
			var alle = document.querySelectorAll('[data-stable-id="' + maskiere(stableId) + '"]');
			for (var i = 0; i < alle.length; i++) {
				if (!imModal(alle[i])) {
					return alle[i];
				}
			}
		}

		return null;
	}

	/**
	 * Wie findeZiel(), liefert aber den AEUSSEREN Container.
	 *
	 * Der HTML-Anker haengt am inneren `.cbd-container-block`; geklont werden
	 * soll der umgebende `.cbd-container`, damit Kopfzeile, Icon und Nummer
	 * mitkommen.
	 *
	 * @param {string} anchor
	 * @param {string} stableId
	 * @return {HTMLElement|null}
	 */
	function findeContainerImDom(anchor, stableId) {
		var kandidat = findeZiel(anchor, stableId);

		if (!kandidat) {
			return null;
		}

		if (kandidat.classList && kandidat.classList.contains('cbd-container')) {
			return kandidat;
		}

		var aussen = (typeof kandidat.closest === 'function') ? kandidat.closest('.cbd-container') : null;

		return aussen || kandidat;
	}

	/**
	 * Highlight a block temporarily
	 * @param {HTMLElement} element
	 */
	function highlightBlock(element) {
		element.classList.add('cbd-block-reference-highlight');

		setTimeout(function () {
			element.classList.remove('cbd-block-reference-highlight');
		}, 2000);
	}

	/**
	 * Weich zum Ziel scrollen und es kurz hervorheben.
	 * @param {HTMLElement} element
	 */
	function springeZu(element) {
		// Erst die Klasse setzen, dann scrollen: `scroll-margin-top` der
		// Klasse .cbd-block-reference-highlight wirkt nur, wenn sie zum
		// Zeitpunkt des scrollIntoView() bereits am Element haengt.
		highlightBlock(element);

		if (typeof element.scrollIntoView === 'function') {
			element.scrollIntoView({
				behavior: 'smooth',
				block: 'start'
			});
		}
	}

	// =====================================================================
	// MODAL — Zustand
	// =====================================================================

	var modal = null;            // aeusseres Dialogelement oder null
	var modalTitel = null;
	var modalKoerper = null;
	var modalSchliessen = null;
	var ausloeser = null;        // Verweis, der den Fokus zurueckbekommt
	var koerperOverflowVorher = '';
	var anfrageZaehler = 0;      // verwirft ueberholte fetch-Antworten
	var idZaehler = 0;           // Praefix-Zaehler fuer die ID-Umbenennung

	var TITEL_ID = 'cbd-block-reference-modal-titel';

	/**
	 * Attribute, die auf IDs verweisen. Werden beim Umbenennen mitgezogen,
	 * sonst zeigten `aria-controls` & Co. im Modal auf das Original.
	 */
	var ID_VERWEISE = [
		'aria-controls',
		'aria-labelledby',
		'aria-describedby',
		'aria-owns',
		'aria-flowto',
		'aria-details',
		'aria-errormessage',
		'for',
		'headers',
		'list',
		'form'
	];

	// =====================================================================
	// MODAL — Aufbau
	// =====================================================================

	function baueModal() {
		var wurzel = document.createElement('div');
		wurzel.className = 'cbd-block-reference-modal';
		wurzel.setAttribute('role', 'dialog');
		wurzel.setAttribute('aria-modal', 'true');
		wurzel.setAttribute('aria-labelledby', TITEL_ID);

		var overlay = document.createElement('div');
		overlay.className = 'cbd-block-reference-modal__overlay';
		overlay.addEventListener('click', function () {
			schliesseModal(true);
		});

		var karte = document.createElement('div');
		karte.className = 'cbd-block-reference-modal__karte';

		var kopf = document.createElement('div');
		kopf.className = 'cbd-block-reference-modal__kopf';

		var titel = document.createElement('h2');
		titel.className = 'cbd-block-reference-modal__titel';
		titel.id = TITEL_ID;

		var knopf = document.createElement('button');
		knopf.type = 'button';
		knopf.className = 'cbd-block-reference-modal__schliessen';
		knopf.setAttribute('aria-label', t('schliessen', 'Schliessen'));
		knopf.innerHTML = '&times;';
		knopf.addEventListener('click', function () {
			schliesseModal(true);
		});

		var koerper = document.createElement('div');
		koerper.className = 'cbd-block-reference-modal__koerper';
		// tabindex="-1": nur programmatisch fokussierbar, nicht per Tab. Der
		// Fokus landet hier, wenn der Inhalt gar nichts Fokussierbares hat.
		koerper.setAttribute('tabindex', '-1');

		kopf.appendChild(titel);
		kopf.appendChild(knopf);
		karte.appendChild(kopf);
		karte.appendChild(koerper);
		wurzel.appendChild(overlay);
		wurzel.appendChild(karte);

		document.body.appendChild(wurzel);

		modal = wurzel;
		modalTitel = titel;
		modalKoerper = koerper;
		modalSchliessen = knopf;

		return wurzel;
	}

	function setzeTitel(titel) {
		if (!modalTitel) {
			return;
		}
		modalTitel.textContent = titel || t('titelVorgabe', 'Block');
	}

	function zeigeLadehinweis() {
		if (!modalKoerper) {
			return;
		}
		leere(modalKoerper);
		var absatz = document.createElement('p');
		absatz.className = 'cbd-block-reference-modal__laden';
		absatz.textContent = t('laden', 'Inhalt wird geladen \u2026');
		modalKoerper.appendChild(absatz);
	}

	/**
	 * Die EINE Fehlermeldung. Jede nicht erfolgreiche Antwort landet hier —
	 * 404, 400, Netzfehler, leere Antwort. Der Endpunkt selbst antwortet auf
	 * jeden Fehlschlag zeichengleich (siehe class-cbd-block-content-api.php);
	 * eine feinere Unterscheidung im Browser waere also nur erfunden.
	 */
	function zeigeFehler() {
		if (!modalKoerper) {
			return;
		}
		leere(modalKoerper);
		var absatz = document.createElement('p');
		absatz.className = 'cbd-block-reference-modal__fehler';
		absatz.textContent = t('nichtVerfuegbar', 'Dieser Block ist nicht verf\u00fcgbar.');
		modalKoerper.appendChild(absatz);
	}

	// =====================================================================
	// MODAL — Inhalt aufbereiten
	// =====================================================================

	/**
	 * Alle IDs im Modalinhalt umbenennen und die Verweise darauf mitziehen.
	 *
	 * Ohne das existierte jede ID zweimal auf der Seite: Sprungmarken
	 * (`href="#…"`), `aria-controls`, `<label for>` und `getElementById()`
	 * traefen dann den ersten Treffer — je nach Reihenfolge das Original oder
	 * den Klon. Das Praefix ist je Oeffnung eindeutig, damit auch mehrfaches
	 * Oeffnen nicht kollidiert.
	 *
	 * @param {HTMLElement} wurzel
	 * @return {Object} Zuordnung alt → neu (fuer Tests)
	 */
	function entferneDoppelteIds(wurzel) {
		idZaehler += 1;

		var praefix = 'cbd-modal-' + idZaehler + '-';
		var karte = {};
		var i;
		var j;

		var mitId = wurzel.querySelectorAll('[id]');
		for (i = 0; i < mitId.length; i++) {
			var alt = mitId[i].getAttribute('id');
			if (!alt) {
				continue;
			}
			karte[alt] = praefix + alt;
			mitId[i].setAttribute('id', karte[alt]);
		}

		// Die Wurzel selbst faellt nicht unter querySelectorAll().
		if (wurzel.id) {
			karte[wurzel.id] = praefix + wurzel.id;
			wurzel.id = karte[wurzel.id];
		}

		var verweisSelektor = '[' + ID_VERWEISE.join('],[') + ']';
		var verweise = wurzel.querySelectorAll(verweisSelektor);
		for (i = 0; i < verweise.length; i++) {
			for (j = 0; j < ID_VERWEISE.length; j++) {
				var attribut = ID_VERWEISE[j];
				var wert = verweise[i].getAttribute(attribut);
				if (!wert) {
					continue;
				}
				// aria-labelledby, aria-describedby und headers duerfen
				// mehrere IDs fuehren, durch Leerraum getrennt.
				var neu = wert.split(/\s+/).map(function (einzel) {
					return Object.prototype.hasOwnProperty.call(karte, einzel) ? karte[einzel] : einzel;
				}).join(' ');
				verweise[i].setAttribute(attribut, neu);
			}
		}

		var anker = wurzel.querySelectorAll('a[href^="#"]');
		for (i = 0; i < anker.length; i++) {
			var ziel = (anker[i].getAttribute('href') || '').substring(1);
			if (ziel && Object.prototype.hasOwnProperty.call(karte, ziel)) {
				anker[i].setAttribute('href', '#' + karte[ziel]);
			}
		}

		return karte;
	}

	/**
	 * Den Inhalt fuer die Leseansicht herrichten.
	 *
	 * Drei Eingriffe, alle drei aus demselben Grund: Nachtraeglich eingefuegtes
	 * Markup wird von der Interactivity API nicht hydriert.
	 *
	 *   1. Die Aktionsleiste `.cbd-action-buttons` fliegt raus. Ihre Knoepfe
	 *      (Klappen, Kopieren, Screenshot, PDF, Tafel, Behandelt) haengen alle
	 *      an `data-wp-on--click` und waeren im Modal wirkungslos. Ein sichtbar
	 *      toter Knopf ist schlechter als kein Knopf; Screenreader kuendigten
	 *      ihn zudem als bedienbar an.
	 *   2. Eingeklappte Container werden aufgeklappt — der Umschalter, der sie
	 *      wieder oeffnen koennte, ist ja gerade entfernt worden.
	 *   3. Verweise IM Modal duerfen kein zweites Modal oeffnen. Sie werden auf
	 *      `link` zurueckgestuft; ein Modal im Modal ist damit ausgeschlossen.
	 *
	 * @param {HTMLElement} wurzel
	 */
	function entschaerfeInhalt(wurzel) {
		var i;

		var leisten = wurzel.querySelectorAll('.cbd-action-buttons');
		for (i = 0; i < leisten.length; i++) {
			if (leisten[i].parentNode) {
				leisten[i].parentNode.removeChild(leisten[i]);
			}
		}

		var eingeklappt = wurzel.querySelectorAll('.cbd-collapsed');
		for (i = 0; i < eingeklappt.length; i++) {
			eingeklappt[i].classList.remove('cbd-collapsed');
		}
		if (wurzel.classList && wurzel.classList.contains('cbd-collapsed')) {
			wurzel.classList.remove('cbd-collapsed');
		}

		var versteckt = wurzel.querySelectorAll('.cbd-container-content[aria-hidden="true"], .cbd-content[aria-hidden="true"]');
		for (i = 0; i < versteckt.length; i++) {
			versteckt[i].setAttribute('aria-hidden', 'false');
		}

		var verweise = wurzel.querySelectorAll('[data-display-mode="modal"]');
		for (i = 0; i < verweise.length; i++) {
			verweise[i].setAttribute('data-display-mode', 'link');
			verweise[i].removeAttribute('aria-haspopup');
		}
	}

	/**
	 * Formeln nachrendern.
	 *
	 * Geklonte Formeln tragen bereits `data-cbd-latex-rendered="1"`; dort tut
	 * der Aufruf nichts. Nachgeladene brauchen ihn. Die Funktion stammt aus dem
	 * LaTeX-Renderer desselben Plugins, kann aber fehlen (Renderer nicht
	 * eingebunden, weil die Seite selbst keine Formel hat) — deshalb die
	 * typeof-Pruefung.
	 *
	 * @param {HTMLElement} wurzel
	 */
	function rendereFormeln(wurzel) {
		if (typeof window.cbdRenderLatex !== 'function') {
			return;
		}

		try {
			var ergebnis = window.cbdRenderLatex(wurzel);
			if (ergebnis && typeof ergebnis.then === 'function') {
				ergebnis.then(function (anzahl) {
					debug('Formeln im Modal gerendert:', anzahl);
				})['catch'](function (fehler) {
					console.warn('CBD Block-Referenz: LaTeX-Rendern im Modal fehlgeschlagen.', fehler);
				});
			}
		} catch (fehler) {
			console.warn('CBD Block-Referenz: LaTeX-Rendern im Modal fehlgeschlagen.', fehler);
		}
	}

	/**
	 * Inhalt in den Modalkoerper setzen und aufbereiten.
	 *
	 * @param {Node|null} knoten   fertiger Knoten (DOM-Pfad) oder null
	 * @param {string}    html     HTML-Text (Server-Pfad); nur ohne Knoten
	 */
	function setzeInhalt(knoten, html) {
		if (!modalKoerper) {
			return;
		}

		leere(modalKoerper);

		var huelle = document.createElement('div');
		huelle.className = 'cbd-block-reference-modal__inhalt';

		if (knoten) {
			huelle.appendChild(knoten);
		} else {
			// <script> aus innerHTML fuehrt der Browser nicht aus. Das ist hier
			// erwuenscht: die in Container-Bloecken isolierten Inline-Skripte
			// wuerden sonst ein zweites Mal laufen.
			huelle.innerHTML = html || '';
		}

		modalKoerper.appendChild(huelle);

		entferneDoppelteIds(huelle);
		entschaerfeInhalt(huelle);
		rendereFormeln(huelle);
	}

	// =====================================================================
	// MODAL — Oeffnen, Schliessen, Tastatur
	// =====================================================================

	function textAusVerweis(link) {
		var label = (typeof link.querySelector === 'function')
			? link.querySelector('.cbd-block-reference-label')
			: null;
		var roh = label ? label.textContent : link.textContent;

		return String(roh || '').replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '');
	}

	/**
	 * Das Modal oeffnen bzw. — wenn schon eines offen ist — seinen Inhalt
	 * austauschen. Es gibt zu keinem Zeitpunkt zwei Overlays.
	 *
	 * @param {HTMLElement} link
	 */
	function oeffneModal(link) {
		var stableId = link.getAttribute('data-target-stable-id') || '';
		var anchor = link.getAttribute('data-target-anchor') || '';
		var postId = parseInt(link.getAttribute('data-target-post'), 10) || 0;
		var samePage = link.getAttribute('data-same-page') === 'true';
		var titel = link.getAttribute('data-target-title') || textAusVerweis(link);

		if (!modal) {
			koerperOverflowVorher = document.body.style.overflow;
			baueModal();
			document.body.style.overflow = 'hidden';
		}

		ausloeser = link;
		setzeTitel(titel);
		zeigeLadehinweis();

		if (modalSchliessen && typeof modalSchliessen.focus === 'function') {
			modalSchliessen.focus();
		}

		// ---- (1) DOM-Pfad ------------------------------------------------
		// Nur bei data-same-page="true". Der Bezeichner `stableId` wird beim
		// Kopieren eines Blocks NICHT neu vergeben (siehe
		// CBD_Block_Organizer::should_regenerate_id) — dieselbe Kennung kann
		// also auf zwei Seiten liegen. Ein ungepruefter DOM-Treffer zeigte
		// dann still den falschen Block.
		var quelle = samePage ? findeContainerImDom(anchor, stableId) : null;

		// Ohne konfigurierten Endpunkt bleibt der DOM der einzige Weg.
		if (!quelle && !samePage && !restBasis()) {
			quelle = findeContainerImDom(anchor, stableId);
		}

		if (quelle) {
			debug('Zielblock aus dem DOM geklont:', stableId);
			setzeInhalt(quelle.cloneNode(true), '');
			return;
		}

		// ---- (2) Server-Pfad ---------------------------------------------
		ladeVomServer(postId, stableId, titel);
	}

	/**
	 * Den Block ueber `cbd/v1/block-html` nachladen.
	 *
	 * @param {number} postId
	 * @param {string} stableId
	 * @param {string} titel   Rueckfalltitel, falls die Antwort keinen liefert
	 */
	function ladeVomServer(postId, stableId, titel) {
		var basis = restBasis();

		if (!basis || !postId || !stableId || typeof window.fetch !== 'function') {
			zeigeFehler();
			return;
		}

		var url = mitParameter(basis, 'post_id', String(postId));
		url = mitParameter(url, 'stable_id', stableId);

		// Klassensitzung durchreichen. Der Endpunkt liest `classroom` und
		// `token` aus $_GET; fehlen sie, lehnt er auf einer gesperrten Seite
		// zu Recht ab.
		var klasse = ausAdresse('classroom');
		var token = ausAdresse('token');
		if (klasse) {
			url = mitParameter(url, 'classroom', klasse);
		}
		if (token) {
			url = mitParameter(url, 'token', token);
		}

		var kopfzeilen = { 'Accept': 'application/json' };
		var k = konfig();
		if (k && k.nonce) {
			// KEINE Autorisierung — der Nonce sorgt nur dafuer, dass angemeldete
			// Nutzer bei REST-Aufrufen als angemeldet erkannt werden. Die
			// Rechtepruefung leistet ausschliesslich der Endpunkt.
			kopfzeilen['X-WP-Nonce'] = k.nonce;
		}

		anfrageZaehler += 1;
		var meineNummer = anfrageZaehler;

		window.fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: kopfzeilen,
			// Die Antwort ist NICHT zwischenspeicherbar: dieselbe URL liefert
			// fuer Lehrperson, Klassensitzung und anonymen Besucher
			// unterschiedlichen Inhalt.
			cache: 'no-store'
		}).then(function (antwort) {
			if (meineNummer !== anfrageZaehler) {
				return null; // ueberholt oder Modal inzwischen geschlossen
			}

			// JEDE Nicht-200-Antwort wird gleich behandelt. Die 404 dieses
			// Endpunkts traegt KEIN `data`-Feld — deshalb auf
			// `antwort.status` pruefen, nie auf `daten.data.status`.
			if (!antwort || antwort.status !== 200) {
				debug('Endpunkt antwortete mit Status', antwort ? antwort.status : '(keine Antwort)');
				zeigeFehler();
				return null;
			}

			return antwort.json();
		}).then(function (daten) {
			if (meineNummer !== anfrageZaehler || daten === null || daten === undefined) {
				return;
			}

			if (typeof daten.html !== 'string' || daten.html === '') {
				zeigeFehler();
				return;
			}

			setzeTitel(daten.title ? String(daten.title) : titel);
			setzeInhalt(null, daten.html);
		})['catch'](function (fehler) {
			if (meineNummer !== anfrageZaehler) {
				return;
			}
			console.warn('CBD Block-Referenz: Block konnte nicht nachgeladen werden.', fehler);
			zeigeFehler();
		});
	}

	/**
	 * Das Modal schliessen.
	 *
	 * @param {boolean} fokusZurueck Fokus auf den ausloesenden Verweis legen?
	 *                               Falsch, wenn direkt danach zu einer anderen
	 *                               Stelle gesprungen wird.
	 */
	function schliesseModal(fokusZurueck) {
		if (!modal) {
			return;
		}

		// Laufende Antworten verwerfen: der Zaehler stimmt danach nicht mehr.
		anfrageZaehler += 1;

		if (modal.parentNode) {
			modal.parentNode.removeChild(modal);
		}

		modal = null;
		modalTitel = null;
		modalKoerper = null;
		modalSchliessen = null;

		document.body.style.overflow = koerperOverflowVorher;

		var ziel = ausloeser;
		ausloeser = null;

		if (fokusZurueck && ziel && typeof ziel.focus === 'function' && document.body.contains(ziel)) {
			ziel.focus();
		}
	}

	var FOKUSSIERBAR = 'a[href], area[href], button, input, select, textarea, summary, iframe, object, embed, [tabindex], [contenteditable]';

	function fokussierbare() {
		if (!modal) {
			return [];
		}

		var alle = modal.querySelectorAll(FOKUSSIERBAR);
		var liste = [];

		for (var i = 0; i < alle.length; i++) {
			var element = alle[i];
			if (element.hasAttribute('disabled')) {
				continue;
			}
			if (element.getAttribute('tabindex') === '-1') {
				continue;
			}
			if (element.getAttribute('type') === 'hidden') {
				continue;
			}
			liste.push(element);
		}

		return liste;
	}

	/**
	 * Fokusfalle: Tab laesst den Dialog nicht verlassen.
	 *
	 * @param {KeyboardEvent} event
	 */
	function fokusFalle(event) {
		var liste = fokussierbare();

		if (!liste.length) {
			event.preventDefault();
			if (modalKoerper && typeof modalKoerper.focus === 'function') {
				modalKoerper.focus();
			}
			return;
		}

		var erstes = liste[0];
		var letztes = liste[liste.length - 1];
		var aktiv = document.activeElement;

		if (!aktiv || !modal.contains(aktiv)) {
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

	function beiTaste(event) {
		if (!modal) {
			return;
		}

		if (event.key === 'Escape' || event.key === 'Esc' || event.keyCode === 27) {
			event.preventDefault();
			schliesseModal(true);
			return;
		}

		if (event.key === 'Tab' || event.keyCode === 9) {
			fokusFalle(event);
		}
	}

	// =====================================================================
	// KLICKS
	// =====================================================================

	/**
	 * Sprungverhalten wie vor AP-2.5 (Modus `link`).
	 *
	 * @param {Event} event
	 * @param {HTMLElement} link
	 */
	function springeOderFolge(event, link) {
		var stableId = link.getAttribute('data-target-stable-id') || '';
		var anchor = link.getAttribute('data-target-anchor') || '';
		var isSamePage = link.getAttribute('data-same-page') === 'true';

		// Verweise auf andere Seiten uebernimmt der Browser.
		if (!isSamePage) {
			return;
		}

		var ziel = findeZiel(anchor, stableId);

		if (!ziel) {
			console.warn('CBD Block-Referenz: Ziel-Block nicht gefunden (Anker "' + anchor + '", stableId "' + stableId + '")');
			return;
		}

		event.preventDefault();

		springeZu(ziel);

		// Die Adresszeile nur anpassen, wenn es einen echten Anker gibt.
		// Eine stableId ist keine Element-ID - ein Fragment daraus liefe
		// beim Neuladen ins Leere.
		if (anchor && window.history && typeof history.pushState === 'function') {
			history.pushState(null, '', '#' + anchor);
		}
	}

	function beiKlick(event) {
		if (event.defaultPrevented) {
			return;
		}

		// Mittlere Maustaste und Modifikatoren gehoeren dem Browser
		// (neuer Tab, neues Fenster, Download).
		if (typeof event.button === 'number' && event.button !== 0) {
			return;
		}
		if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
			return;
		}

		var ziel = event.target;
		var link = (ziel && typeof ziel.closest === 'function')
			? ziel.closest('.cbd-block-reference-link')
			: null;

		if (!link) {
			return;
		}

		// Verschachtelungsgrenze, zweite Ebene: entschaerfeInhalt() stuft
		// Verweise im Modal bereits auf `link` zurueck. Diese Pruefung faengt
		// den Fall ab, dass ein Verweis auf anderem Weg dorthin gelangt.
		var innerhalb = imModal(link);

		if (!innerhalb && link.getAttribute('data-display-mode') === 'modal') {
			event.preventDefault();
			oeffneModal(link);
			return;
		}

		if (innerhalb) {
			// Erst schliessen, sonst spielte sich der Sprung hinter dem
			// Overlay ab. Kein Fokus zurueck — er gehoert zum Sprungziel.
			schliesseModal(false);
		}

		springeOderFolge(event, link);
	}

	// =====================================================================
	// DIREKTNAVIGATION (unveraendert aus AP-1.3)
	// =====================================================================

	/**
	 * Direktnavigation: Fragment in der Adresszeile.
	 */
	function handleHashNavigation() {
		var hash = window.location.hash;

		if (!hash) {
			return;
		}

		var targetId = hash.substring(1);
		var targetElement = findeZiel(targetId, targetId);

		if (targetElement) {
			// Small delay to ensure page is fully loaded
			setTimeout(function () {
				springeZu(targetElement);
			}, 100);
		}
	}

	/**
	 * Direktnavigation ohne Anker: Der Verweis auf eine ANDERE Seite haengt
	 * die stableId als Parameter cbd-ref an die Adresse.
	 */
	function handleQueryNavigation() {
		var stableId = ausAdresse('cbd-ref');

		if (!stableId) {
			return;
		}

		var targetElement = findeZiel('', stableId);

		if (targetElement) {
			setTimeout(function () {
				springeZu(targetElement);
			}, 100);
		}
	}

	// =====================================================================
	// START
	// =====================================================================

	function initialisiere() {
		// Delegiert statt je Verweis: so greifen auch Verweise, die erst
		// spaeter im DOM landen (etwa im Modal selbst).
		document.addEventListener('click', beiKlick);
		document.addEventListener('keydown', beiTaste);

		handleHashNavigation();
		handleQueryNavigation();

		window.addEventListener('hashchange', handleHashNavigation);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialisiere);
	} else {
		initialisiere();
	}
})();
