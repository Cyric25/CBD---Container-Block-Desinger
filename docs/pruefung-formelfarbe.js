/**
 * Prüfskript: Warum sind LaTeX-Formeln blass oder unsichtbar?
 *
 * Im Browser auf einer Seite mit Formeln in die Konsole einfügen und Enter.
 * Das Skript klappt Accordions und Container selbst auf, wartet, misst und
 * gibt am Ende einen fertigen Textblock aus, den man zurückmelden kann.
 *
 * Es unterscheidet die beiden möglichen Ursachen:
 *   (a) opacity 0.3 aus der Ladeanzeige
 *       `.cbd-latex-formula:not(.cbd-latex-rendered) .cbd-latex-content`
 *       → die Formel gilt als „nicht gerendert", obwohl KaTeX sie gesetzt hat
 *   (b) eine geerbte oder gesetzte Textfarbe, die zu hell ist
 *       → das Skript nennt die Regel, die gewonnen hat
 *
 * Verändert nichts dauerhaft: Aufgeklappte Bereiche bleiben offen, sonst
 * werden keine Attribute, Klassen oder Stile geschrieben.
 *
 * @package ContainerBlockDesigner
 */
(async function () {
	'use strict';

	const AUS = [];
	const merke = ( zeile ) => { AUS.push( zeile ); console.log( zeile ); };

	merke( '=== Formelfarbe: Diagnose ===' );
	merke( 'Seite: ' + location.href );

	// --- 1. Alles aufklappen, damit auch verborgene Formeln gemessen werden ---

	let geoeffnet = 0;

	document.querySelectorAll( '.cbd-collapse-toggle, .cbd-container-toggle' ).forEach( ( t ) => {
		const box = t.closest( '.cbd-container' );
		if ( box && box.querySelector( '.cbd-collapsed, .cbd-container-content.cbd-collapsed' ) ) {
			t.click();
			geoeffnet++;
		}
	} );

	document.querySelectorAll( '.mb-accordion-row__header[aria-expanded="false"]' ).forEach( ( h ) => {
		h.click();
		geoeffnet++;
	} );

	merke( 'Aufgeklappt: ' + geoeffnet + ' Bereiche' );

	// Auf Aufklapp-Animation (250 ms) und Nachrendern warten.
	await new Promise( ( r ) => setTimeout( r, 1200 ) );
	if ( document.fonts && document.fonts.ready ) {
		try { await document.fonts.ready; } catch ( e ) {}
	}

	// --- 2. Hilfsfunktionen ---

	/** Erste Vorfahrenfarbe, die tatsächlich gemalt wird (nicht transparent). */
	function echterHintergrund( el ) {
		let n = el;
		while ( n && n !== document.documentElement ) {
			const bg = getComputedStyle( n ).backgroundColor;
			if ( bg && bg !== 'transparent' && ! /rgba\(\s*0,\s*0,\s*0,\s*0\s*\)/.test( bg ) ) {
				return bg + '  (von ' + kurz( n ) + ')';
			}
			n = n.parentElement;
		}
		return 'keiner gefunden';
	}

	function kurz( el ) {
		if ( ! el ) { return '-'; }
		const k = ( el.className && typeof el.className === 'string' )
			? '.' + el.className.trim().split( /\s+/ ).slice( 0, 3 ).join( '.' )
			: '';
		return el.tagName.toLowerCase() + k;
	}

	/** Durchsucht alle Stylesheets nach Regeln, die auf el passen und prop setzen. */
	function regelnFuer( el, prop ) {
		const treffer = [];
		for ( const sheet of Array.from( document.styleSheets ) ) {
			let regeln;
			try {
				regeln = sheet.cssRules;
			} catch ( e ) {
				continue; // fremde Herkunft, nicht lesbar
			}
			if ( ! regeln ) { continue; }

			const durchlaufe = ( liste, medium ) => {
				for ( const r of Array.from( liste ) ) {
					if ( r.cssRules && ! r.selectorText ) {
						durchlaufe( r.cssRules, r.conditionText || medium );
						continue;
					}
					if ( ! r.selectorText || ! r.style ) { continue; }
					const wert = r.style.getPropertyValue( prop );
					if ( ! wert ) { continue; }
					let passt = false;
					try { passt = el.matches( r.selectorText ); } catch ( e ) {}
					if ( passt ) {
						treffer.push( {
							quelle: ( sheet.href || 'inline' ).split( '/' ).pop(),
							medium: medium || '',
							selektor: r.selectorText,
							wert: wert + ( r.style.getPropertyPriority( prop ) ? ' !important' : '' )
						} );
					}
				}
			};
			durchlaufe( regeln, '' );
		}
		return treffer;
	}

	/** Farbkette von el bis body. */
	function farbkette( el ) {
		const kette = [];
		let n = el;
		while ( n && n !== document.body ) {
			kette.push( kurz( n ) + ' → ' + getComputedStyle( n ).color );
			n = n.parentElement;
		}
		kette.push( 'body → ' + getComputedStyle( document.body ).color );
		return kette;
	}

	// --- 3. Messen ---

	const formeln = Array.from( document.querySelectorAll( '.cbd-latex-formula' ) );
	merke( 'Gefundene Formeln: ' + formeln.length );

	if ( ! formeln.length ) {
		merke( 'KEINE Formeln auf dieser Seite — falsche Seite?' );
		return;
	}

	const tabelle = [];
	let blasse = null;

	formeln.forEach( ( f, i ) => {
		const inhalt = f.querySelector( '.cbd-latex-content' ) || f;
		const katex  = f.querySelector( '.katex' );
		const ziel   = katex || inhalt;

		const opa   = getComputedStyle( inhalt ).opacity;
		const farbe = getComputedStyle( ziel ).color;
		const anim  = getComputedStyle( inhalt ).animationName;

		const zeile = {
			nr: i,
			art: f.classList.contains( 'cbd-latex-display' ) ? 'display' : 'inline',
			klasse_rendered: f.classList.contains( 'cbd-latex-rendered' ) ? 'JA' : 'NEIN',
			attr_rendered: f.getAttribute( 'data-cbd-latex-rendered' ) || '-',
			attr_failed: f.getAttribute( 'data-cbd-latex-failed' ) || '-',
			katex_da: katex ? 'ja' : 'NEIN',
			opacity: opa,
			animation: anim && anim !== 'none' ? anim : '-',
			color: farbe
		};
		tabelle.push( zeile );

		const blass = ( parseFloat( opa ) < 0.9 ) || /rgb\(\s*2[0-9]{2},\s*2[0-9]{2},\s*2[0-9]{2}\s*\)/.test( farbe );
		if ( blass && ! blasse ) { blasse = { f, inhalt, ziel }; }
	} );

	console.table( tabelle );
	tabelle.forEach( ( z ) => merke( JSON.stringify( z ) ) );

	// --- 4. Urteil ---

	merke( '' );
	merke( '--- Urteil ---' );

	const ohneKlasse = tabelle.filter( ( z ) => z.klasse_rendered === 'NEIN' ).length;
	const transparent = tabelle.filter( ( z ) => parseFloat( z.opacity ) < 0.9 ).length;
	const gescheitert = tabelle.filter( ( z ) => z.attr_failed === '1' ).length;
	const ohneKatex = tabelle.filter( ( z ) => z.katex_da === 'NEIN' ).length;

	merke( 'ohne Klasse cbd-latex-rendered : ' + ohneKlasse + ' von ' + tabelle.length );
	merke( 'opacity kleiner 0.9            : ' + transparent );
	merke( 'als gescheitert markiert       : ' + gescheitert );
	merke( 'ohne KaTeX-Element             : ' + ohneKatex );

	if ( transparent > 0 ) {
		merke( '>> URSACHE (a): Ladeanzeige greift. Die Formel gilt als nicht gerendert.' );
	} else {
		merke( '>> Ursache (a) ausgeschlossen — opacity ist überall 1.' );
	}

	// --- 5. Detail zur ersten blassen Formel ---

	if ( blasse ) {
		merke( '' );
		merke( '--- Erste blasse Formel im Detail ---' );
		merke( 'Element: ' + kurz( blasse.f ) );
		merke( 'Hintergrund dahinter: ' + echterHintergrund( blasse.f ) );
		merke( '' );
		merke( 'Farbkette von innen nach außen:' );
		farbkette( blasse.ziel ).forEach( ( z ) => merke( '   ' + z ) );

		// Für JEDES Element der Kette die Regeln nennen, nicht nur für das
		// gemessene. Sonst sieht man zwar, WO die Farbe umspringt, aber nicht,
		// welche Regel dort gewonnen hat.
		merke( '' );
		merke( 'Regeln je Element der Kette (nur wo welche greifen):' );
		let k = blasse.ziel, vorige = null;
		while ( k && k !== document.body ) {
			const jetzt = getComputedStyle( k ).color;
			const rr    = regelnFuer( k, 'color' );
			const wechsel = ( vorige !== null && vorige !== jetzt );

			if ( rr.length || wechsel ) {
				merke( '   ' + ( wechsel ? '>>> HIER SPRINGT DIE FARBE UM: ' : '' ) + kurz( k ) + '  = ' + jetzt );
				if ( k.getAttribute && k.getAttribute( 'style' ) ) {
					merke( '        inline: style="' + k.getAttribute( 'style' ) + '"' );
				}
				rr.forEach( ( r ) => merke( '        [' + r.quelle + ']' + ( r.medium ? ' @' + r.medium : '' ) + '  ' + r.selektor + '  ->  ' + r.wert ) );
				if ( ! rr.length ) {
					merke( '        keine passende Regel — geerbt oder inline' );
				}
			}
			vorige = jetzt;
			k = k.parentElement;
		}

		merke( '' );
		merke( 'Regeln, die opacity auf den Inhalt setzen:' );
		const ro = regelnFuer( blasse.inhalt, 'opacity' );
		if ( ro.length ) {
			ro.forEach( ( r ) => merke( '   [' + r.quelle + ']' + ( r.medium ? ' @' + r.medium : '' ) + '  ' + r.selektor + '  ->  ' + r.wert ) );
		} else {
			merke( '   keine' );
		}

		// Inline-Stile in der Kette — die schlagen jedes Stylesheet.
		merke( '' );
		merke( 'Inline-Stile in der Kette (schlagen jedes Stylesheet):' );
		let n = blasse.ziel, gefunden = false;
		while ( n && n !== document.body ) {
			if ( n.style && ( n.style.color || n.style.opacity ) ) {
				merke( '   ' + kurz( n ) + '  style="color:' + n.style.color + '; opacity:' + n.style.opacity + '"' );
				gefunden = true;
			}
			n = n.parentElement;
		}
		if ( ! gefunden ) { merke( '   keine' );	}
	} else {
		merke( '' );
		merke( 'Keine blasse Formel gefunden — alle sehen normal aus.' );
	}

	// --- 6. Zum Zurückmelden ---

	merke( '' );
	merke( '=== Ende ===' );

	const text = AUS.join( '\n' );
	if ( navigator.clipboard && navigator.clipboard.writeText ) {
		try {
			await navigator.clipboard.writeText( text );
			console.log( '%cErgebnis liegt in der Zwischenablage — einfach einfügen.', 'font-weight:bold' );
		} catch ( e ) {
			console.log( 'Zwischenablage nicht erreichbar. Text steht in window.formelBericht' );
		}
	}
	window.formelBericht = text;
} )();
