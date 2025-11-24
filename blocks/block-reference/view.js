/**
 * CBD Block-Referenz Frontend JavaScript
 * Handles smooth scrolling to referenced blocks
 */

document.addEventListener('DOMContentLoaded', function() {
	const referenceLinks = document.querySelectorAll('.cbd-block-reference-link');

	referenceLinks.forEach(link => {
		link.addEventListener('click', function(e) {
			const targetBlockId = this.getAttribute('data-target-block');
			const isSamePage = this.getAttribute('data-same-page') === 'true';

			// Only handle smooth scroll if on same page
			if (isSamePage && targetBlockId) {
				e.preventDefault();

				const targetElement = document.getElementById(targetBlockId);

				if (targetElement) {
					// Smooth scroll to target
					targetElement.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});

					// Update URL hash without jumping
					history.pushState(null, null, '#' + targetBlockId);

					// Optional: Highlight target block briefly
					highlightBlock(targetElement);
				} else {
					console.warn(`CBD Block-Referenz: Ziel-Block mit ID "${targetBlockId}" nicht gefunden`);
				}
			}
			// For links to other pages, let the browser handle it normally
		});
	});

	/**
	 * Highlight a block temporarily
	 * @param {HTMLElement} element
	 */
	function highlightBlock(element) {
		// Add highlight class
		element.classList.add('cbd-block-reference-highlight');

		// Remove after animation
		setTimeout(() => {
			element.classList.remove('cbd-block-reference-highlight');
		}, 2000);
	}

	// Handle direct navigation via URL hash on page load
	handleHashNavigation();

	// Handle hash changes (browser back/forward)
	window.addEventListener('hashchange', handleHashNavigation);

	function handleHashNavigation() {
		const hash = window.location.hash;

		if (hash) {
			const targetId = hash.substring(1);
			const targetElement = document.getElementById(targetId);

			if (targetElement) {
				// Small delay to ensure page is fully loaded
				setTimeout(() => {
					targetElement.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});
					highlightBlock(targetElement);
				}, 100);
			}
		}
	}
});
