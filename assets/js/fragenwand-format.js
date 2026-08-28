/**
 * Container Block Designer - Fragenwand-Verweis als Textformat (Editor)
 *
 * KEIN BUILD-SCHRITT: Diese Datei wird unveraendert an den Browser
 * ausgeliefert. Deshalb kein JSX, keine ES-Module-Syntax, keine Arrow
 * Functions, keine Template-Literale, kein let/const - Hausstil ist ES5 mit
 * var/function und IIFE, der Zugriff laeuft ueber die wp.*-Globale, Elemente
 * entstehen ueber wp.element.createElement (hier als `el`). Vorbild:
 * blocks/block-reference/format.js.
 *
 * Die Abhaengigkeiten (wp-rich-text, wp-block-editor, wp-element, wp-i18n,
 * wp-data) meldet CBD_Fragenwand::register_editor_format() an. `wp-rich-text`
 * und `wp-data` stehen dort ausdruecklich, obwohl wp-block-editor sie meist
 * mitbringt - das Plugin hat an genau dieser Auslassung schon einmal gelitten
 * (class-cbd-block-reference.php:155-158, nachgezogen in
 * class-cbd-inline-reference.php::format_script_daten()).
 *
 * ---------------------------------------------------------------------------
 * WAS DIESE DATEI TUT (AP-3.1 aus PLAN-Fragenwand.md)
 * ---------------------------------------------------------------------------
 *
 * Text markieren -> Schaltflaeche in der RichText-Werkzeugleiste -> der
 * markierte Text wird zu einem <a class="cbd-fragenwand-verweis" href="#">,
 * das im Frontend die Fragenwand der aktuellen Klasse als Modal oeffnet
 * (AP-3.2, assets/js/fragenwand-frontend.js).
 *
 * BEWUSST OHNE AUSWAHL-DIALOG - der entscheidende Unterschied zum Vorbild
 * blocks/block-reference/format.js: Dort gibt es viele moegliche Zielbloecke,
 * also einen Dialog mit hierarchischer Zielauswahl und fuenf gespeicherte
 * `data-target-*`-Attribute. Hier gibt es genau EINE Fragenwand je Klasse
 * (Nicht-Ziel in Abschnitt 2 des Plans) - es ist also nichts auszuwaehlen und
 * nichts pro Instanz zu speichern. Der Werkzeugleisten-Knopf wendet das Format
 * unmittelbar an bzw. entfernt es. Entsprechend braucht diese Datei weder
 * `wp.components` (Modal, Button) noch `wp.element.useState` noch den
 * Auswahlbaustein `window.cbdBlockAuswahl`.
 *
 * GESPEICHERT WIRD GENAU EIN ATTRIBUT: `href="#"`. Es ist rein strukturell -
 * anders als beim Block-Verweis gibt es keine sinnvolle Sprungziel-URL, weil
 * die Fragenwand keine eigene Seite hat. Ohne JavaScript passiert deshalb
 * nichts; `preventDefault()` uebernimmt view-seitig AP-3.2. Das ist eine
 * bewusst akzeptierte Einschraenkung (Abschnitt 2 des Plans).
 *
 * KEIN serverseitiger Auffrisch-Filter (Gegenstueck zu
 * CBD_Inline_Reference::inhalt_auffrischen()): Es gibt kein zielabhaengiges
 * Attribut, das veralten koennte.
 *
 * @package ContainerBlockDesigner
 * @since Vorhaben „Fragenwand", Phase 3 (AP-3.1)
 */
(function (window) {
	'use strict';

	var wp = window.wp;

	var TEXTDOMAIN = 'container-block-designer';

	/** Name des Textformats. */
	var FORMAT = 'cbd/fragenwand-verweis';

	/**
	 * Die CSS-Klasse des Fragenwand-Verweises.
	 *
	 * DIESE ZEICHENKETTE WIRD AB AP-3.2 AN MEHREREN STELLEN GEBRAUCHT: hier
	 * (wirksamer Wert der Registrierung), im Klick-Selektor von
	 * assets/js/fragenwand-frontend.js und in den Selektoren von
	 * assets/css/fragenwand.css. Wird sie geaendert, muessen alle Stellen
	 * mitgezogen werden - dieselbe Lage wie bei `cbd-block-reference-inline`
	 * (dort haelt `tools/test-inline-reference.php`, Gruppe 11, sie zusammen).
	 */
	var KLASSE = 'cbd-fragenwand-verweis';

	/**
	 * Das Kern-Link-Format.
	 *
	 * Liegt es IRGENDWO im markierten Bereich, wird das Format NICHT angewendet:
	 * Ein <a> in einem <a> ist ungueltiges HTML, und der Schaden landet im
	 * gespeicherten `post_content` - er liesse sich durch kein Plugin-Update
	 * mehr reparieren. Siehe `linkImBereich()` weiter unten.
	 */
	var LINK_FORMAT = 'core/link';

	/**
	 * Warnung auf der Konsole.
	 *
	 * `console.warn` darf ungegatet bleiben - anders als `console.log`, das
	 * laut Hausstil hinter `window.cbdDebug` gehoert. In dieser Datei gibt es
	 * kein `console.log`.
	 *
	 * @param {string} text
	 * @returns {void}
	 */
	function warne(text) {
		if (window.console && 'function' === typeof window.console.warn) {
			window.console.warn('CBD Fragenwand-Verweis: ' + text);
		}
	}

	// -----------------------------------------------------------------------
	// Wachposten
	// -----------------------------------------------------------------------

	// Geprueft wird NUR, was diese Datei wirklich benutzt. Eine Anforderung an
	// eine ungenutzte Funktion waere kein Netz, sondern ein falsches Negativ:
	// Sie liesse die Registrierung auf einer WordPress-Fassung ausfallen, auf
	// der das Format einwandfrei liefe. Deshalb steht hier - anders als im
	// Vorbild - weder `wp.components` noch `wp.element.useState`.
	if (!wp || !wp.richText || !wp.blockEditor || !wp.element
		|| 'function' !== typeof wp.richText.registerFormatType
		|| 'function' !== typeof wp.richText.applyFormat
		|| 'function' !== typeof wp.richText.removeFormat
		|| 'function' !== typeof wp.element.createElement
		|| 'function' !== typeof wp.blockEditor.RichTextToolbarButton) {
		warne('Die benoetigten wp.*-Module fehlen; das Textformat wird nicht registriert.');
		return;
	}

	// -----------------------------------------------------------------------
	// Kuerzel auf die wp.*-Bausteine
	// -----------------------------------------------------------------------

	var el = wp.element.createElement;

	var registerFormatType = wp.richText.registerFormatType;
	var applyFormat = wp.richText.applyFormat;
	var removeFormat = wp.richText.removeFormat;

	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;

	var __ = (wp.i18n && 'function' === typeof wp.i18n.__)
		? wp.i18n.__
		: function (text) { return text; };

	// -----------------------------------------------------------------------
	// Kleine Helfer
	// -----------------------------------------------------------------------

	/**
	 * Der markierte Bereich als HALBOFFENES Intervall [von, bis).
	 *
	 * Fehlt die Auswahl ganz, sind `start` und `end` beide `undefined` - dann
	 * ergibt sich [0, 0), also ein leerer Bereich.
	 *
	 * @param {Object} wert RichText-Wert (props.value)
	 * @returns {Array} [von, bis]
	 */
	function bereich(wert) {
		if (!wert) {
			return [0, 0];
		}

		var von = ('number' === typeof wert.start) ? wert.start : 0;
		var bis = ('number' === typeof wert.end) ? wert.end : 0;

		return [von, bis];
	}

	/**
	 * Ist die Markierung leer (oder gibt es gar keine)?
	 *
	 * @param {Object} wert RichText-Wert
	 * @returns {boolean}
	 */
	function markierungLeer(wert) {
		return !wert || wert.start === wert.end;
	}

	/**
	 * Liegt IRGENDWO im markierten Bereich ein `core/link`?
	 *
	 * WARUM NICHT `getActiveFormat(wert, 'core/link')`: Die Funktion liefert
	 * nur Formate, die die GANZE Markierung ueberspannen. Liegt der Link
	 * INNERHALB der Markierung (der praxisnahe Fall: ein ganzer Satz mit einer
	 * verlinkten Quellenangabe) oder ueberlappt er ihren Rand nur teilweise,
	 * ist der Rueckgabewert `undefined` - und `applyFormat()` legte das
	 * Fragenwand-Format AUSSEN um den Link: ein <a> in einem <a>.
	 *
	 * Das ist im Vorbild (blocks/block-reference/format.js, AP-4.fix2 des
	 * Vorhabens „Inline-Blockreferenz") mit WordPress' eigenem Baum-Parser
	 * belegt worden (`WP_HTML_Processor::normalize()` liefert dafuer NULL) und
	 * gilt hier unveraendert: Das Ergebnis von `toHTMLString()` ist der String,
	 * der in `post_content` landet - ein Plugin-Update holt ihn nicht zurueck,
	 * und der Absatz gilt beim naechsten Oeffnen als „Block enthaelt
	 * unerwarteten oder ungueltigen Inhalt".
	 *
	 * Der Plan (AP-3.1, Schritt 2, letzter Punkt) stellte diese Pruefung frei,
	 * weil das Kollisionsrisiko ohne Zwischendialog geringer ist. Sie ist
	 * trotzdem uebernommen: Der Schaden waere derselbe, und die Kosten sind
	 * eine Schleife.
	 *
	 * Drei Feinheiten, alle aus dem Vorbild uebernommen:
	 *   1. NUR `core/link`. Ein zweiter Fragenwand-Verweis im Bereich ist kein
	 *      Konflikt - `applyFormat()` filtert den eigenen Typ vorher heraus.
	 *   2. Der Bereich ist HALBOFFEN, [von, bis). Ein Link, der genau dort
	 *      endet, wo die Markierung beginnt, wird ein Geschwister, kein
	 *      verschachteltes Element.
	 *   3. Bei zusammengefallener Auswahl laeuft die Schleife nicht - richtig,
	 *      denn `beiKlick()` prueft vorher `istAktiv` und entfernt dann.
	 *
	 * `wert.formats` ist ein LUECKENHAFTES Array - Zeichen ohne Format haben
	 * keinen Eintrag. Daher die Existenzpruefung in der Schleife.
	 *
	 * @param {Object} wert RichText-Wert (props.value)
	 * @returns {boolean}
	 */
	function linkImBereich(wert) {
		if (!wert || !wert.formats) {
			return false;
		}

		var grenzen = bereich(wert);

		for (var i = grenzen[0]; i < grenzen[1]; i++) {
			var formate = wert.formats[i];
			if (!formate || !formate.length) {
				continue;
			}
			for (var j = 0; j < formate.length; j++) {
				if (formate[j] && LINK_FORMAT === formate[j].type) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Warnung im Editor: auf der Markierung liegt bereits ein Link.
	 *
	 * `wp-data` ist deshalb eine ausdruecklich deklarierte Abhaengigkeit dieses
	 * Scripts (siehe Kopfkommentar). Die Existenzpruefung bleibt trotzdem als
	 * zweite Absicherung stehen und faellt notfalls auf die Konsole zurueck -
	 * eine stille Nichtreaktion des Knopfes waere das schlechteste Ergebnis.
	 *
	 * @returns {void}
	 */
	function warneVerschachtelung() {
		var meldung = __('Auf dem markierten Text liegt bereits ein Link. Entferne ihn zuerst - ein Fragenwand-Verweis innerhalb eines Links ergaebe ungueltiges HTML.', TEXTDOMAIN);

		if (wp.data && 'function' === typeof wp.data.dispatch) {
			var meldungen = wp.data.dispatch('core/notices');
			if (meldungen && 'function' === typeof meldungen.createNotice) {
				meldungen.createNotice('warning', meldung, {
					id: 'cbd-fragenwand-verweis-link-konflikt',
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
	 * Werkzeugleisten-Knopf - ohne Dialog, ohne Zustand.
	 *
	 * Erhaelt von RichText unter anderem `isActive`, `value` und `onChange`
	 * (siehe FormatEdit in wp-includes/js/dist/block-editor.js). Weil es nichts
	 * auszuwaehlen gibt, ist das eine reine Funktion ohne `useState` - sie gibt
	 * genau ein Element zurueck, kein Fragment mit Dialog.
	 *
	 * @param {Object} props
	 * @returns {Object}
	 */
	function FragenwandVerweisFormat(props) {
		var p = props || {};
		var wert = p.value || {};
		var istAktiv = !!p.isActive;
		var melde = ('function' === typeof p.onChange) ? p.onChange : function () {};

		var leer = markierungLeer(wert);

		/**
		 * Klick auf den Werkzeugleisten-Knopf: Umschalten ohne Zwischenschritt.
		 *
		 * @returns {void}
		 */
		function beiKlick() {
			if (istAktiv) {
				melde(removeFormat(wert, FORMAT));
				return;
			}

			// Ohne Markierung gibt es nichts, worauf das Format gelegt werden
			// koennte. Der Knopf ist in diesem Fall bereits `disabled`; die
			// Pruefung ist das Netz fuer einen Aufruf ueber die Tastatur oder
			// eine kuenftige Aenderung an `disabled`.
			if (leer) {
				return;
			}

			if (linkImBereich(wert)) {
				warneVerschachtelung();
				return;
			}

			melde(applyFormat(wert, {
				type: FORMAT,
				attributes: {
					// Rein strukturell - siehe Kopfkommentar. `#` und nicht
					// `''`: Ein leeres href zeigte auf die aktuelle Seite und
					// laedt sie beim Klick ohne JavaScript neu.
					href: '#'
				}
			}));
		}

		return el(RichTextToolbarButton, {
			// Dashicon `sticky` - ein Notizzettel, passend zur Post-it-Optik
			// der Fragenwand (AP-3.4).
			icon: 'sticky',
			title: istAktiv
				? __('Fragenwand-Verweis entfernen', TEXTDOMAIN)
				: __('Fragenwand-Verweis einfuegen', TEXTDOMAIN),
			onClick: beiKlick,
			isActive: istAktiv,
			// Ohne Markierung gibt es nichts anzuwenden - dann bleibt der Knopf
			// deaktiviert. Steht der Cursor aber OHNE Markierung INNERHALB
			// eines bereits aktiven Verweises (istAktiv), kann removeFormat()
			// bei zusammengefallener Auswahl trotzdem den ganzen Lauf entfernen
			// (WordPress-Quelle wp-includes/js/dist/rich-text.js, Funktion
			// removeFormat(), Zweig startIndex === endIndex). Der Knopf bleibt
			// in genau diesem Fall bedienbar - deaktiviert wird nur, wenn es
			// wirklich nichts zu tun gibt.
			disabled: leer && !istAktiv,
			// ZWEITER NAME FUER DIESELBE AUSSAGE, und das ist kein Versehen:
			// Ein Format ohne `name`-Prop landet in der Ueberlaufliste „Mehr"
			// der Formatwerkzeugleiste. `FormatToolbar` rendert die Fills dort
			// NICHT, sondern liest nur ihre Props und reicht sie als `controls`
			// an `DropdownMenu` weiter - und diese Komponente liest
			// `control.isDisabled`, nicht `control.disabled`. Ohne die zweite
			// Angabe bliebe der Eintrag im Ueberlaufmenue immer anklickbar
			// (live gemessen am 2026-08-28 gegen WordPress 7.0.4; dasselbe gilt
			// unverandert fuer das aeltere Format `cbd/block-reference-inline`,
			// das nur `disabled` setzt). `disabled` bleibt trotzdem stehen: Es
			// ist der Name, den `ToolbarButton` liest, falls das Format je
			// direkt in der Leiste erscheint.
			isDisabled: leer && !istAktiv
		});
	}

	// -----------------------------------------------------------------------
	// Registrierung
	// -----------------------------------------------------------------------

	registerFormatType(FORMAT, {
		title: __('Fragenwand-Verweis', TEXTDOMAIN),

		// `a`, NICHT `span`: Die Glossar-Autoverlinkung des Themes
		// (the_content, Prioritaet 10000) ueberspringt <a>-Elemente korrekt.
		// Bei einem <span> duerfte sie ein <a class="glossar-term"> HINEIN
		// setzen; Klick und Tooltip wuerden konkurrieren.
		tagName: 'a',
		className: KLASSE,
		attributes: {
			href: 'href'
		},
		edit: FragenwandVerweisFormat
	});
})(window);
