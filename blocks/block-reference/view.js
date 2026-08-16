/**
 * CBD Block-Referenz Frontend JavaScript
 * Handles smooth scrolling to referenced blocks
 *
 * Das Ziel wird ueber die stableId gefunden (Attribut data-stable-id am
 * .cbd-container-Wrapper). Ein HTML-Anker ist optional und hat Vorrang,
 * wenn er gesetzt ist.
 */

document.addEventListener('DOMContentLoaded', function () {
	'use strict';

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

	/**
	 * Sucht das Zielelement: erst der HTML-Anker, dann die stableId.
	 *
	 * @param {string} anchor
	 * @param {string} stableId
	 * @return {HTMLElement|null}
	 */
	function findeZiel(anchor, stableId) {
		if (anchor) {
			var perAnker = document.getElementById(anchor);
			if (perAnker) {
				return perAnker;
			}
		}

		if (stableId) {
			return document.querySelector('[data-stable-id="' + maskiere(stableId) + '"]');
		}

		return null;
	}

	/**
	 * Highlight a block temporarily
	 * @param {HTMLElement} element
	 */
	function highlightBlock(element) {
		// Add highlight class
		element.classList.add('cbd-block-reference-highlight');

		// Remove after animation
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
		element.scrollIntoView({
			behavior: 'smooth',
			block: 'start'
		});
	}

	var referenceLinks = document.querySelectorAll('.cbd-block-reference-link');

	Array.prototype.forEach.call(referenceLinks, function (link) {
		link.addEventListener('click', function (e) {
			var stableId = this.getAttribute('data-target-stable-id') || '';
			var anchor = this.getAttribute('data-target-anchor') || '';
			var isSamePage = this.getAttribute('data-same-page') === 'true';

			// Only handle smooth scroll if on same page
			if (!isSamePage) {
				// For links to other pages, let the browser handle it normally
				return;
			}

			var targetElement = findeZiel(anchor, stableId);

			if (!targetElement) {
				console.warn('CBD Block-Referenz: Ziel-Block nicht gefunden (Anker "' + anchor + '", stableId "' + stableId + '")');
				return;
			}

			e.preventDefault();

			springeZu(targetElement);

			// Die Adresszeile nur anpassen, wenn es einen echten Anker gibt.
			// Eine stableId ist keine Element-ID - ein Fragment daraus liefe
			// beim Neuladen ins Leere.
			if (anchor) {
				history.pushState(null, '', '#' + anchor);
			}
		});
	});

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
		var treffer = /[?&]cbd-ref=([^&#]+)/.exec(window.location.search);

		if (!treffer) {
			return;
		}

		var stableId = '';
		try {
			stableId = decodeURIComponent(treffer[1].replace(/\+/g, ' '));
		} catch (fehler) {
			stableId = treffer[1];
		}

		var targetElement = findeZiel('', stableId);

		if (targetElement) {
			setTimeout(function () {
				springeZu(targetElement);
			}, 100);
		}
	}

	// Handle direct navigation via URL hash on page load
	handleHashNavigation();

	// Handle direct navigation via cbd-ref parameter on page load
	handleQueryNavigation();

	// Handle hash changes (browser back/forward)
	window.addEventListener('hashchange', handleHashNavigation);
});
