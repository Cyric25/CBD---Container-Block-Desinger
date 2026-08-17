/**
 * Container Block Designer - Blockreferenz als Textformat (Editor)
 *
 * KEIN BUILD-SCHRITT: Diese Datei wird unveraendert an den Browser
 * ausgeliefert. Deshalb kein JSX, keine ES-Module-Syntax, keine Arrow
 * Functions, keine Template-Literale, kein let/const - Hausstil ist ES5 mit
 * var/function und IIFE, der Zugriff laeuft ueber die wp.*-Globale, Elemente
 * entstehen ueber wp.element.createElement (hier als `el`). Vorbilder:
 * blocks/block-reference/index.js und assets/js/block-auswahl.js.
 *
 * Die Abhaengigkeiten (wp-rich-text, wp-block-editor, wp-components,
 * wp-element, wp-i18n, wp-api-fetch, cbd-block-auswahl, wp-data) meldet
 * CBD_Inline_Reference::register_format_script() an. `wp-rich-text` und seit
 * AP-4.fix1 auch `wp-data` sind dort ausdruecklich deklariert, obwohl
 * wp-block-editor/wp-components sie meist mitbringen - das Plugin hat an
 * genau dieser Auslassung schon einmal gelitten
 * (class-cbd-block-reference.php:155-158).
 *
 * ---------------------------------------------------------------------------
 * WAS DIESE DATEI TUT (AP-4.2 aus docs/PLAN-Inline-Blockreferenz.md)
 * ---------------------------------------------------------------------------
 *
 * Text markieren -> Schaltflaeche in der RichText-Werkzeugleiste -> Dialog mit
 * der hierarchischen Zielauswahl (Vertrag C, assets/js/block-auswahl.js) ->
 * der markierte Text wird zu einem <a class="cbd-block-reference-inline">,
 * das im Frontend den Zielblock als Modal oeffnet (view.js).
 *
 * GESPEICHERT WERDEN GENAU FUENF ATTRIBUTE (Vertrag D): href,
 * data-target-post, data-target-stable-id, data-target-anchor,
 * data-target-title. NICHT gespeichert werden `data-display-mode`,
 * `data-same-page` und `aria-haspopup` - die setzt der serverseitige Filter
 * CBD_Inline_Reference::inhalt_auffrischen() bei JEDER Ausgabe frisch
 * (Vertrag E). Gruende, alle drei nachlesbar in Abschnitt 4 des Plans:
 * `data-same-page` mitzuspeichern zeigte nach dem Kopieren eines Absatzes
 * still den falschen Block; `aria-haspopup` steht nicht in der ARIA-Whitelist
 * von kses und wuerde einem Block-Redakteur beim Speichern entfernt; ein
 * Textformat friert seine Attribute beim Bearbeiten ein, waehrend der Filter
 * bei jedem Aufruf frisch rechnet und der Verweis dadurch eine
 * Slug-Aenderung ueberlebt.
 *
 * KEIN `displayMode`-ATTRIBUT. Der Inline-Verweis kennt nur den Modalmodus
 * (Entscheidung des Nutzers, Abschnitt 2 des Plans). Ein Attribut mit genau
 * einem erlaubten Wert waere Ballast.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.92
 */
(function (window) {
	'use strict';

	var wp = window.wp;

	var TEXTDOMAIN = 'container-block-designer';

	/** Name des Textformats (Vertrag D). */
	var FORMAT = 'cbd/block-reference-inline';

	/**
	 * Die CSS-Klasse des Inline-Verweises.
	 *
	 * DIESE ZEICHENKETTE STEHT AN DREI STELLEN: hier, in
	 * `CBD_Inline_Reference::KLASSE` (includes/class-cbd-inline-reference.php)
	 * und im Klick-Selektor von `view.js`. Wird sie geaendert, muessen alle
	 * drei mitgezogen werden. Dafuer gibt es seit AP-4.2 einen
	 * Duplikatswaechter: `tools/test-inline-reference.php`, Gruppe 11, schlaegt
	 * an, sobald eine der beiden JS-Fassungen von der PHP-Konstante abweicht
	 * (Befund S8 aus AP-3.rev; Praezedenz ist die `:pN`-Zusicherung in
	 * tools/test-classroom-gate.php).
	 *
	 * BEWUSST NICHT `cbd-block-reference-link` (die Klasse des Blocks): Jene
	 * traegt in style.css `display: block` samt Karten-Layout und `transform`
	 * beim Ueberfahren - mitten in einem Absatz zerreisst das den Textfluss.
	 */
	var KLASSE = 'cbd-block-reference-inline';

	/**
	 * Das Kern-Link-Format.
	 *
	 * Liegt es auf der Markierung, wird der Dialog NICHT geoeffnet: Ein <a> in
	 * einem <a> ist ungueltiges HTML und das Klickverhalten waere
	 * unvorhersehbar (Risiko aus Abschnitt 5 des Plans).
	 */
	var LINK_FORMAT = 'core/link';

	/** Laenge, ab der der markierte Text im Dialog gekuerzt angezeigt wird. */
	var ANZEIGE_MAX = 80;

	/**
	 * Warnung auf der Konsole.
	 *
	 * `console.warn` darf laut Pflichtregel 19 des Plans ungegatet bleiben -
	 * anders als `console.log`, das hinter `window.cbdDebug` gehoert. In dieser
	 * Datei gibt es kein `console.log`.
	 *
	 * @param {string} text
	 * @returns {void}
	 */
	function warne(text) {
		if (window.console && 'function' === typeof window.console.warn) {
			window.console.warn('CBD Inline-Verweis: ' + text);
		}
	}

	// -----------------------------------------------------------------------
	// Wachposten
	// -----------------------------------------------------------------------

	if (!wp || !wp.richText || !wp.blockEditor || !wp.components || !wp.element
		|| 'function' !== typeof wp.richText.registerFormatType
		|| 'function' !== typeof wp.richText.applyFormat
		|| 'function' !== typeof wp.richText.removeFormat
		|| 'function' !== typeof wp.richText.getActiveFormat
		|| 'function' !== typeof wp.element.createElement
		|| 'function' !== typeof wp.element.useState
		|| 'function' !== typeof wp.blockEditor.RichTextToolbarButton) {
		warne('Die benoetigten wp.*-Module fehlen; das Textformat wird nicht registriert.');
		return;
	}

	/**
	 * Der gemeinsame Auswahlbaustein (Vertrag C) MUSS vorhanden sein.
	 *
	 * Ohne ihn wird das Format gar nicht registriert. Ein Knopf, der einen
	 * leeren Dialog oeffnet, ist schlechter als kein Knopf - und ohne
	 * registriertes Format liest RichText ein vorhandenes
	 * `<a class="cbd-block-reference-inline">` als unregistriertes Format ein
	 * und schreibt es beim Speichern unveraendert zurueck (Risiko
	 * "Blockgueltigkeit bei nicht geladenem Format-Script", Abschnitt 5 des
	 * Plans; Netz ist zusaetzlich assets/js/block-recovery.js).
	 */
	if (!window.cbdBlockAuswahl
		|| 'function' !== typeof window.cbdBlockAuswahl.HierarchieAuswahl
		|| 'function' !== typeof window.cbdBlockAuswahl.schluessel) {
		warne('Der Auswahlbaustein window.cbdBlockAuswahl (assets/js/block-auswahl.js) fehlt; das Textformat wird nicht registriert.');
		return;
	}

	// -----------------------------------------------------------------------
	// Kuerzel auf die wp.*-Bausteine
	// -----------------------------------------------------------------------

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;

	var registerFormatType = wp.richText.registerFormatType;
	var applyFormat = wp.richText.applyFormat;
	var removeFormat = wp.richText.removeFormat;
	var getActiveFormat = wp.richText.getActiveFormat;

	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;

	var Modal = wp.components.Modal;
	var Button = wp.components.Button;

	var __ = (wp.i18n && 'function' === typeof wp.i18n.__)
		? wp.i18n.__
		: function (text) { return text; };

	// -----------------------------------------------------------------------
	// Kleine Helfer
	// -----------------------------------------------------------------------

	/**
	 * Null-sichere Textumwandlung.
	 *
	 * Nutzt die Fassung des Auswahlbausteins (Vertrag C), damit es nur EINE
	 * Verhaltensdefinition gibt; der Baustein ist an dieser Stelle bereits
	 * durch den Wachposten oben gesichert.
	 *
	 * @param {*} wert
	 * @returns {string}
	 */
	function text(wert) {
		if ('function' === typeof window.cbdBlockAuswahl.text) {
			return window.cbdBlockAuswahl.text(wert);
		}
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
	 * Markierten Text aus einem RichText-Wert lesen.
	 *
	 * @param {Object} wert RichText-Wert (props.value)
	 * @returns {string}
	 */
	function markierung(wert) {
		if (!wert || 'string' !== typeof wert.text) {
			return '';
		}
		var von = ('number' === typeof wert.start) ? wert.start : 0;
		var bis = ('number' === typeof wert.end) ? wert.end : 0;

		return wert.text.slice(von, bis);
	}

	/**
	 * Ist die Markierung leer (oder gibt es gar keine)?
	 *
	 * Fehlt die Auswahl ganz, sind `start` und `end` beide `undefined` - der
	 * Vergleich trifft diesen Fall mit.
	 *
	 * @param {Object} wert RichText-Wert
	 * @returns {boolean}
	 */
	function markierungLeer(wert) {
		return !wert || wert.start === wert.end;
	}

	/**
	 * Text fuer die Anzeige kuerzen.
	 *
	 * @param {string} roh
	 * @returns {string}
	 */
	function kurz(roh) {
		var eine_zeile = text(roh).replace(/\s+/g, ' ');

		return (eine_zeile.length > ANZEIGE_MAX)
			? eine_zeile.slice(0, ANZEIGE_MAX) + '…'
			: eine_zeile;
	}

	/**
	 * Ziel-URL aus einem Listeneintrag (Vertrag A) bilden.
	 *
	 * SELBE REGEL WIE `CBD_Inline_Reference::ziel_href()`
	 * (includes/class-cbd-inline-reference.php) UND `render.php`: Mit Anker das
	 * Fragment, ohne Anker der Parameter `cbd-ref`, bei leerem Bezeichner der
	 * nackte Permalink. Alle drei Fassungen zusammen aendern (AP-3.fix5,
	 * Befund S7) - ein Duplikatswaechter ist hier nicht moeglich, weil die
	 * dritte Fassung in einer anderen Sprache steht.
	 *
	 * Der gespeicherte `href` ist ohnehin nur die fortschreitende Verbesserung
	 * fuer den Fall ohne JavaScript: Vertrag E rechnet ihn bei jeder Ausgabe
	 * frisch aus `get_permalink()`. Deshalb ist ein veralteter Wert hier
	 * unkritisch, ein leerer waere es nicht - ein `href=""` zeigte auf die
	 * aktuelle Seite.
	 *
	 * @param {Object} eintrag Element aus Vertrag A
	 * @returns {string} vollstaendige URL oder ''
	 */
	function zielHref(eintrag) {
		if (!eintrag) {
			return '';
		}

		var basis = text(eintrag.postUrl);
		if (!basis) {
			return '';
		}

		var anker = text(eintrag.anchor).replace(/^\s+|\s+$/g, '');
		if (anker) {
			return basis + '#' + anker;
		}

		var stable = text(eintrag.stableId).replace(/^\s+|\s+$/g, '');
		if (!stable) {
			return basis;
		}

		// Gegenstueck zu add_query_arg(): ein vorhandenes Fragezeichen wird
		// respektiert (Installationen ohne huebsche Permalinks liefern
		// `?p=45`).
		return basis
			+ ((basis.indexOf('?') === -1) ? '?' : '&')
			+ 'cbd-ref=' + encodeURIComponent(stable);
	}

	/**
	 * Warnung im Editor: auf der Markierung liegt bereits ein Link.
	 *
	 * `wp-data` ist seit AP-4.fix1 (Befund F1) eine ausdruecklich deklarierte
	 * Abhaengigkeit dieses Scripts (siehe Kopfkommentar sowie
	 * CBD_Inline_Reference::format_script_daten()) - vorher war es nur
	 * zufaellig geladen, weil `wp-block-editor` und `wp-components` es
	 * mitbringen; genau darauf zu bauen verbietet
	 * class-cbd-block-reference.php:155-158. Die Existenzpruefung unten
	 * bleibt trotzdem als zweite Absicherung stehen und faellt notfalls auf
	 * die Konsole zurueck - eine stille Nichtreaktion des Knopfes waere das
	 * schlechteste Ergebnis.
	 *
	 * @returns {void}
	 */
	function warneVerschachtelung() {
		var meldung = __('Auf dem markierten Text liegt bereits ein Link. Entferne ihn zuerst - ein Verweis innerhalb eines Links ergaebe ungueltiges HTML.', TEXTDOMAIN);

		if (wp.data && 'function' === typeof wp.data.dispatch) {
			var meldungen = wp.data.dispatch('core/notices');
			if (meldungen && 'function' === typeof meldungen.createNotice) {
				meldungen.createNotice('warning', meldung, {
					id: 'cbd-inline-verweis-link-konflikt',
					type: 'snackbar',
					isDismissible: true
				});
				return;
			}
		}

		warne(meldung);
	}

	// -----------------------------------------------------------------------
	// Die Komponente hinter `edit`
	// -----------------------------------------------------------------------

	/**
	 * Werkzeugleisten-Knopf samt Dialog.
	 *
	 * Erhaelt von RichText unter anderem `isActive`, `value` und `onChange`
	 * (siehe FormatEdit in wp-includes/js/dist/block-editor.js).
	 *
	 * ZWEI FEHLER DES THEME-VORBILDS `Theme/src/js/glossar-editor.js` SIND HIER
	 * BEWUSST NICHT UEBERNOMMEN:
	 *   (a) Dort setzt `openModal()` bei leerer Markierung einen Fehlerzustand
	 *       und kehrt zurueck, OHNE den Dialog zu oeffnen (`:36-41`) - die
	 *       Meldung wird aber nur INNERHALB des Modals gerendert (`:168-172`)
	 *       und ist damit unsichtbar. Hier ist der Knopf stattdessen
	 *       `disabled`, solange nichts markiert ist (AK2).
	 *   (b) Dort eine Klassenkomponente; hier Hooks, wie im Hausstil von
	 *       blocks/block-reference/index.js.
	 *
	 * @param {Object} props
	 * @returns {Object}
	 */
	function InlineVerweisFormat(props) {
		var p = props || {};
		var wert = p.value || {};
		var istAktiv = !!p.isActive;
		var melde = ('function' === typeof p.onChange) ? p.onChange : function () {};

		var HierarchieAuswahl = window.cbdBlockAuswahl.HierarchieAuswahl;
		var schluessel = window.cbdBlockAuswahl.schluessel;

		var offenZustand = useState(false);
		var offen = offenZustand[0];
		var setOffen = offenZustand[1];

		// Das gewaehlte Ziel - ein Element aus Vertrag A oder null.
		var zielZustand = useState(null);
		var ziel = zielZustand[0];
		var setZiel = zielZustand[1];

		// Der RichText-Wert, wie er beim OEFFNEN des Dialogs war.
		//
		// Der Dialog ist eine Fokusfalle: Sobald er offen ist, kann im Absatz
		// nichts mehr geaendert werden, der gemerkte Wert kann also nicht
		// veralten. Umgekehrt kostet das Oeffnen dem Editor den Fokus - und
		// damit unter Umstaenden die Angabe von `start`/`end`. Der gemerkte
		// Wert ist deshalb die verlaesslichere Grundlage fuer applyFormat().
		var basisZustand = useState(null);
		var basis = basisZustand[0];
		var setBasis = basisZustand[1];

		var leer = markierungLeer(wert);
		var zielGueltig = !!(ziel && text(ziel.stableId));
		var angezeigterText = kurz(markierung(basis || wert));

		function schliesse() {
			setOffen(false);
			setZiel(null);
			setBasis(null);
		}

		/**
		 * Klick auf den Werkzeugleisten-Knopf.
		 *
		 * Umschaltlogik wie im Theme-Vorbild (`glossar-editor.js:139-149`):
		 * Liegt das Format schon auf der Markierung, entfernt der Klick es;
		 * sonst oeffnet er den Dialog.
		 *
		 * @returns {void}
		 */
		function beiKlick() {
			if (istAktiv) {
				melde(removeFormat(wert, FORMAT));
				return;
			}

			if (getActiveFormat(wert, LINK_FORMAT)) {
				warneVerschachtelung();
				return;
			}

			setBasis(wert);
			setZiel(null);
			setOffen(true);
		}

		/**
		 * "Uebernehmen": das Format mit den fuenf Attributen aus Vertrag D
		 * anwenden.
		 *
		 * @returns {void}
		 */
		function uebernehme() {
			if (!zielGueltig) {
				return;
			}

			var grundlage = basis || wert;

			// Reihenfolge wie in der Attributkarte der Registrierung unten -
			// sie bestimmt die Attributreihenfolge im gespeicherten Markup
			// (fromFormat() in wp-includes/js/dist/rich-text.js laeuft ueber
			// die Schluessel dieses Objekts). Eine feste Reihenfolge ist nicht
			// Vertragsbestandteil, aber eine gleichbleibende erspart
			// Unterschiede beim erneuten Speichern.
			melde(applyFormat(grundlage, {
				type: FORMAT,
				attributes: {
					stableId: text(ziel.stableId),
					postId: String(ganzzahl(ziel.postId)),
					anchor: text(ziel.anchor),
					titel: text(ziel.blockTitle),
					href: zielHref(ziel)
				}
			}));

			schliesse();
		}

		var knopf = el(RichTextToolbarButton, {
			icon: 'external',
			title: istAktiv
				? __('Block-Verweis entfernen', TEXTDOMAIN)
				: __('Block-Verweis einfuegen', TEXTDOMAIN),
			onClick: beiKlick,
			isActive: istAktiv,
			// AK2 (praezisiert durch AP-4.fix1, Befund F3): Ohne Markierung
			// gibt es nichts, worauf das Format gelegt werden koennte - dann
			// bleibt der Knopf deaktiviert, statt wie im Theme-Vorbild eine
			// unsichtbare Fehlermeldung zu erzeugen. Steht der Cursor aber
			// OHNE Markierung INNERHALB eines bereits aktiven Verweises
			// (istAktiv), kann removeFormat() bei zusammengefallener
			// Auswahl trotzdem den ganzen Lauf entfernen (WordPress-Quelle
			// wp-includes/js/dist/rich-text.js, Funktion removeFormat(),
			// Zweig startIndex === endIndex: das Format am Cursor wird
			// gesucht, start-/endIndex werden ueber den ganzen
			// zusammenhaengenden Lauf desselben Format-Objekts ausgeweitet).
			// Der Knopf bleibt in genau diesem Fall bedienbar - deaktiviert
			// wird nur, wenn es wirklich nichts zu tun gibt.
			disabled: leer && !istAktiv
		});

		var dialog = null;

		if (offen && Modal) {
			dialog = el(Modal, {
				title: __('Verweis auf einen Container-Block', TEXTDOMAIN),
				onRequestClose: schliesse,
				className: 'cbd-inline-verweis-dialog',
				// Die Abmessung steht inline, nicht in style.css: Jene Datei
				// ist das FRONTEND-Stylesheet des Blocks (block.json,
				// "style") und wird im Editor gar nicht geladen.
				style: { maxWidth: '640px' }
			},
				el('p', { style: { marginTop: 0, color: '#757575' } },
					el('strong', {}, __('Markierter Text:', TEXTDOMAIN)),
					' ',
					el('em', {}, '“' + angezeigterText + '”')
				),

				// Hierarchische Zielauswahl aus assets/js/block-auswahl.js
				// (Vertrag C). Sie bezieht ihre Daten selbst (Zusicherung 1)
				// und wirft nie (Zusicherung 2).
				//
				// `wert` WIRD ZURUECKGEGEBEN, nicht bloss entgegengenommen:
				// Die Komponente leitet den Kaskadenpfad aus `wert` ab und
				// verwirft ihre manuelle Navigation genau dann, wenn `wert`
				// sich aendert (block-auswahl.js:766-768, :803-810). Ein
				// Dialog, der `wert` nicht nachzieht, liesse die Kaskade nach
				// dem ersten Suchtreffer stehen - Zusicherung 4 aus Vertrag C
				// haengt am Aufrufer, nicht an der Komponente (AK13).
				el(HierarchieAuswahl, {
					wert: schluessel(ziel),
					onWaehle: function (eintrag) {
						setZiel(eintrag || null);
					},
					beschriftung: __('Ziel-Block', TEXTDOMAIN)
				}),

				el('div', {
					style: {
						marginTop: '1.5rem',
						display: 'flex',
						justifyContent: 'flex-end',
						gap: '0.5rem'
					}
				},
					el(Button, {
						variant: 'tertiary',
						onClick: schliesse
					}, __('Abbrechen', TEXTDOMAIN)),

					el(Button, {
						variant: 'primary',
						onClick: uebernehme,
						// Kein `onGeladen`-Prop noetig: Eine Wahl setzt
						// geladene Daten voraus, der Ladezustand der
						// Auswahl muss den Dialog also nicht erreichen
						// (so festgehalten bei Vertrag C).
						disabled: !zielGueltig
					}, __('Uebernehmen', TEXTDOMAIN))
				)
			);
		}

		return el(Fragment, {}, knopf, dialog);
	}

	// -----------------------------------------------------------------------
	// Registrierung (Vertrag D, wortgleich)
	// -----------------------------------------------------------------------

	registerFormatType(FORMAT, {
		title: __('Block-Verweis', TEXTDOMAIN),

		// `a`, NICHT `span`: Die Glossar-Autoverlinkung des Themes
		// (the_content, Prioritaet 10000) ueberspringt <a>-Elemente korrekt.
		// Bei einem <span> duerfte sie ein <a class="glossar-term"> HINEIN
		// setzen; Klick und Tooltip wuerden konkurrieren. Ausserdem ist der
		// Verweis so auch ohne JavaScript ein Link (fortschreitende
		// Verbesserung).
		tagName: 'a',
		className: KLASSE,
		attributes: {
			stableId: 'data-target-stable-id',
			postId: 'data-target-post',
			anchor: 'data-target-anchor',
			titel: 'data-target-title',
			href: 'href'
		},
		edit: InlineVerweisFormat
	});
})(window);
