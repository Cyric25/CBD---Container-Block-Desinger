/**
 * Container Block Designer - Block "Block-Referenz" (Editor)
 *
 * KEIN BUILD-SCHRITT: Diese Datei wird unveraendert an den Browser
 * ausgeliefert. Deshalb kein JSX und keine ES-Module-Syntax - der Zugriff
 * laeuft ausschliesslich ueber die wp.*-Globale, Elemente entstehen ueber
 * wp.element.createElement (hier als `el`). Vorbild: assets/js/block-editor.js
 *
 * Die Abhaengigkeiten (wp-blocks, wp-element, wp-block-editor, wp-components,
 * wp-i18n, wp-api-fetch) meldet CBD_Block_Reference::register_editor_script()
 * an; block.json verweist nur noch auf das Handle.
 *
 * Die hierarchische Zielauswahl in der Seitenleiste kommt seit AP-4.1
 * (docs/PLAN-Inline-Blockreferenz.md, Vertrag C) aus dem gemeinsamen
 * Auswahlbaustein assets/js/block-auswahl.js (window.cbdBlockAuswahl). Diese
 * Datei ruft selbst keine REST-Route mehr ab und haelt keinen eigenen
 * Zustand fuer die Zielliste - sie liest nur noch das Ergebnis der Auswahl
 * (den gewaehlten Eintrag bzw. `null`) und schreibt es in die Blockattribute.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;

	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;

	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var Notice = wp.components.Notice;

	var TEXTDOMAIN = 'container-block-designer';
	var __ = (wp.i18n && wp.i18n.__) ? wp.i18n.__ : function (text) { return text; };
	var sprintf = (wp.i18n && wp.i18n.sprintf) ? wp.i18n.sprintf : function (muster, wert) {
		return String(muster).replace('%s', wert);
	};

	/**
	 * Zulaessige Werte von `displayMode`.
	 *
	 * Dieselbe Liste steht in block.json (enum) und in render.php. Wird sie
	 * hier erweitert, muessen beide Stellen mitgezogen werden — render.php
	 * faellt auf 'modal' zurueck, sobald ein unbekannter Wert ankommt.
	 */
	var ANZEIGEMODI = ['modal', 'link'];

	function normalisiereModus(wert) {
		var text = (wert === null || wert === undefined) ? '' : String(wert);
		return (ANZEIGEMODI.indexOf(text) !== -1) ? text : 'modal';
	}

	/**
	 * Null-sichere Textumwandlung.
	 *
	 * Verschoben nach assets/js/block-auswahl.js (window.cbdBlockAuswahl.text,
	 * Vertrag C aus docs/PLAN-Inline-Blockreferenz.md) - Befund B1a aus
	 * AP-3.rev fuehrt diese Fassung als eine von fuenf Doppelungen auf. Kein
	 * blosser Alias (`var text = window.cbdBlockAuswahl.text`): Fehlt der
	 * Auswahlbaustein, wuerde ein Alias beim ersten Aufruf werfen und die
	 * ganze Seitenleiste mitreissen - genau der Absturz, den der Waechter in
	 * BlockReferenceEdit() um die Zielauswahl herum vermeiden soll. Ist der
	 * Baustein vorhanden, ruft diese Funktion ausschliesslich seine Fassung
	 * auf; es gibt also weiterhin nur eine Verhaltensdefinition.
	 */
	function text(wert) {
		if (window.cbdBlockAuswahl && 'function' === typeof window.cbdBlockAuswahl.text) {
			return window.cbdBlockAuswahl.text(wert);
		}
		return (wert === null || wert === undefined) ? '' : String(wert);
	}

	/**
	 * Automatisch vorbelegter Link-Text zu einem Blocktitel.
	 */
	function autoLinkText(titel) {
		return titel ? sprintf(__('Gehe zu: %s', TEXTDOMAIN), titel) : '';
	}

	function BlockReferenceEdit(props) {
		var attributes = props.attributes || {};
		var setAttributes = props.setAttributes;

		var targetStableId = text(attributes.targetStableId);
		var targetBlockId = text(attributes.targetBlockId);
		var targetPostId = parseInt(attributes.targetPostId, 10) || 0;
		var targetBlockTitle = text(attributes.targetBlockTitle);
		var targetPostTitle = text(attributes.targetPostTitle);
		var linkText = text(attributes.linkText);
		var showIcon = attributes.showIcon !== false;
		var displayMode = normalisiereModus(attributes.displayMode);

		var blockProps = useBlockProps({ className: 'cbd-block-reference-editor' });

		// Gemeinsamer Auswahlbaustein (Vertrag C aus
		// docs/PLAN-Inline-Blockreferenz.md). Kann fehlen, wenn
		// assets/js/block-auswahl.js aus irgendeinem Grund nicht mitkam
		// (unvollstaendiges Plugin-ZIP, veralteter Cache) - dafuer sorgt der
		// Waechter weiter unten fuer eine Notice statt einen Absturz.
		var cbdAuswahl = window.cbdBlockAuswahl || null;
		var HierarchieAuswahl = (cbdAuswahl && 'function' === typeof cbdAuswahl.HierarchieAuswahl)
			? cbdAuswahl.HierarchieAuswahl
			: null;

		// Ersetzt die vierte, handgeschriebene Fassung der Schluesselregel
		// "<postId>|<stableId>" (Befund B1a aus AP-3.rev, dort knapp
		// ausserhalb der urspruenglichen Liste gefunden). Die Regel liefert
		// jetzt ausschliesslich window.cbdBlockAuswahl.schluessel().
		var aktuellerWert = (cbdAuswahl && targetStableId)
			? cbdAuswahl.schluessel({ postId: targetPostId, stableId: targetStableId })
			: '';

		// Ein selbst geschriebener Link-Text bleibt erhalten; nur der
		// automatisch vorbelegte wird beim Zielwechsel nachgezogen.
		var linkTextIstAutomatisch = ('' === linkText) || (linkText === autoLinkText(targetBlockTitle));

		function waehleZiel(gewaehlt) {
			if (!gewaehlt) {
				var leer = {
					targetStableId: '',
					targetAnchor: '',
					targetBlockId: '',
					targetPostId: 0,
					targetBlockTitle: '',
					targetPostTitle: ''
				};
				if (linkTextIstAutomatisch) {
					leer.linkText = '';
				}
				setAttributes(leer);
				return;
			}

			var neu = {
				targetStableId: text(gewaehlt.stableId),
				targetAnchor: text(gewaehlt.anchor),
				targetBlockId: text(gewaehlt.blockId),
				targetPostId: parseInt(gewaehlt.postId, 10) || 0,
				targetBlockTitle: text(gewaehlt.blockTitle),
				targetPostTitle: text(gewaehlt.postTitle)
			};

			if (linkTextIstAutomatisch) {
				neu.linkText = autoLinkText(neu.targetBlockTitle);
			}

			setAttributes(neu);
		}

		var hatZiel = !!(targetStableId || targetBlockId);

		// Hierarchische Zielauswahl aus assets/js/block-auswahl.js: Suchfeld,
		// Kaskade ueber die Seitenhierarchie und Block-Auswahlfeld in einer
		// Komponente, die ihre Daten selbst bezieht (Zusicherung 1 aus
		// Vertrag C) und nie wirft (Zusicherung 2). `onWaehle` bekommt direkt
		// den gewaehlten Eintrag aus Vertrag A oder `null` beim Abwaehlen -
		// die Zuordnung Listeneintrag -> Blockattribute in waehleZiel() oben
		// bleibt dieselbe wie zuvor.
		var zielAuswahl = HierarchieAuswahl
			? el(HierarchieAuswahl, {
				wert: aktuellerWert,
				onWaehle: waehleZiel
			})
			: (Notice ? el(Notice, {
				status: 'error',
				isDismissible: false
			}, __('Der Auswahlbaustein fuer die Zielauswahl (assets/js/block-auswahl.js) ist nicht vorhanden. Seite neu laden; besteht das Problem weiter, das Plugin pruefen.', TEXTDOMAIN)) : null);

		var seitenleiste = el(InspectorControls, {},
			el(PanelBody, { title: __('Block-Referenz Einstellungen', TEXTDOMAIN) },
				zielAuswahl,

				hatZiel ? el(TextControl, {
					label: __('Link-Text', TEXTDOMAIN),
					value: linkText,
					onChange: function (wert) { setAttributes({ linkText: wert }); },
					help: __('Optionaler Text fuer den Link', TEXTDOMAIN),
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				}) : null,

				hatZiel ? el(ToggleControl, {
					label: __('Icon anzeigen', TEXTDOMAIN),
					checked: showIcon,
					onChange: function (wert) { setAttributes({ showIcon: !!wert }); },
					__nextHasNoMarginBottom: true
				}) : null,

				hatZiel ? el(SelectControl, {
					label: __('Verhalten beim Klick', TEXTDOMAIN),
					value: displayMode,
					options: [
						{ label: __('Als Modul oeffnen (Standard)', TEXTDOMAIN), value: 'modal' },
						{ label: __('Zum Block springen', TEXTDOMAIN), value: 'link' }
					],
					onChange: function (wert) {
						setAttributes({ displayMode: normalisiereModus(wert) });
					},
					help: __('Als Modul: Der Zielblock erscheint in einem Overlay auf dieser Seite. Zum Block springen: der Verweis fuehrt wie bisher zur Zielstelle.', TEXTDOMAIN),
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				}) : null
			)
		);

		var inhalt;

		if (!hatZiel) {
			// Kein eigener Ladezustand mehr abzufragen: Die Zielauswahl
			// bezieht ihre Daten selbst (siehe oben), die Leinwand muss
			// darauf nicht warten. Die fruehere Hinweiszeile mit der Anzahl
			// verfuegbarer Container-Block-Eintraege entfaellt ersatzlos
			// (Befund B1b aus AP-3.rev) - ihre Quelle, der inzwischen
			// entfernte lokale Zustand fuer die Liste, gibt es nicht mehr;
			// eine erneut erratene Zahl waere falsch.
			inhalt = el(Placeholder, {
				icon: 'admin-links',
				label: __('Block-Referenz', TEXTDOMAIN),
				instructions: __('Waehle einen Container-Block in den Einstellungen rechts aus.', TEXTDOMAIN)
			});
		} else {
			inhalt = el('div', { className: 'cbd-block-reference-preview' },
				el('div', { className: 'cbd-block-reference-preview-header' },
					el('span', { className: 'dashicons dashicons-admin-links' }),
					el('strong', {}, __('Block-Referenz:', TEXTDOMAIN))
				),
				el('div', { className: 'cbd-block-reference-preview-content' },
					el('p', { className: 'cbd-block-reference-preview-post' },
						el('strong', {}, __('Seite:', TEXTDOMAIN)),
						' ' + targetPostTitle
					),
					el('p', { className: 'cbd-block-reference-preview-block' },
						el('strong', {}, __('Block:', TEXTDOMAIN)),
						' ' + targetBlockTitle
					),
					linkText ? el('div', { className: 'cbd-block-reference-preview-link' },
						showIcon ? el('span', { className: 'dashicons dashicons-arrow-right-alt2' }) : null,
						el('span', {}, linkText)
					) : null,
					el('p', {
						className: 'cbd-block-reference-preview-modus'
							+ ('modal' === displayMode ? ' is-modal' : ' is-link')
					},
						el('span', {
							className: 'dashicons '
								+ ('modal' === displayMode ? 'dashicons-external' : 'dashicons-arrow-down-alt')
						}),
						el('span', {},
							'modal' === displayMode
								? __('Oeffnet den Block in einem Overlay auf dieser Seite.', TEXTDOMAIN)
								: __('Springt zum Block bzw. zur Zielseite.', TEXTDOMAIN)
						)
					)
				)
			);
		}

		return el(Fragment, {},
			seitenleiste,
			el('div', blockProps, inhalt)
		);
	}

	wp.blocks.registerBlockType('cbd/block-reference', {
		// Titel/Kategorie/Attribute liefert die serverseitige Registrierung
		// aus block.json mit; hier stehen sie zusaetzlich, damit der Block
		// auch dann vollstaendig ist, wenn die Server-Definition den Editor
		// nicht erreicht. Aenderungen also in beiden Dateien nachziehen.
		//
		// apiVersion gehoert ausdruecklich dazu: Ohne sie faellt der Block
		// auf Version 1 zurueck, und useBlockProps() im edit() bliebe
		// wirkungslos (kein Wrapper, keine Auswahl im Canvas).
		apiVersion: 3,
		title: __('Block-Referenz', TEXTDOMAIN),
		description: __('Erstelle einen Link zu einem anderen Container-Block auf dieser oder einer anderen Seite.', TEXTDOMAIN),
		category: 'container-blocks',
		icon: 'admin-links',
		keywords: ['referenz', 'link', 'container', 'cbd', 'verweis'],
		supports: {
			html: false,
			anchor: true,
			align: ['wide', 'full']
		},
		attributes: {
			targetStableId: { type: 'string', default: '' },
			targetAnchor: { type: 'string', default: '' },
			targetBlockId: { type: 'string', default: '' },
			targetPostId: { type: 'number', default: 0 },
			targetBlockTitle: { type: 'string', default: '' },
			targetPostTitle: { type: 'string', default: '' },
			linkText: { type: 'string', default: '' },
			showIcon: { type: 'boolean', default: true },
			displayMode: { type: 'string', default: 'modal' }
		},
		edit: BlockReferenceEdit,
		save: function () {
			// Serverseitiges Rendern uebernimmt blocks/block-reference/render.php
			return null;
		}
	});
})(window.wp);
