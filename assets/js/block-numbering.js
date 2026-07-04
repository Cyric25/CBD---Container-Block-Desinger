/**
 * Container Block Designer - Block Numbering
 * Automatische Nummerierung von Top-Level Container-Blöcken
 *
 * Dieses Script läuft unabhängig von der Interactivity API und dem Fallback
 * und sorgt dafür, dass alle Container-Blöcke korrekt nummeriert werden.
 *
 * @package ContainerBlockDesigner
 * @since 3.0.5
 */

(function() {
    'use strict';

    /**
     * Nummeriert alle Top-Level Container-Blöcke
     */
    function renumberBlocks() {
        // Find all numbering elements
        const allNumberElements = document.querySelectorAll('.cbd-needs-numbering');

        // Filter to only TOP-LEVEL blocks (not nested inside other .cbd-container)
        const topLevelNumbers = Array.from(allNumberElements).filter(function(element) {
            // Get the container this number belongs to
            const container = element.closest('.cbd-container');
            if (!container) return false;

            // Check if this container is nested inside another .cbd-container
            const parentContainer = container.parentElement.closest('.cbd-container');
            const isTopLevel = !parentContainer;

            return isTopLevel;
        });

        // Renumber only top-level blocks
        topLevelNumbers.forEach(function(element, index) {
            const blockNumber = index + 1;
            element.textContent = blockNumber;
            element.setAttribute('data-number', blockNumber);
        });
    }

    /**
     * Initialisierung
     */
    function init() {
        // Initial numbering when DOM is ready.
        // Dynamisch nachgeladene Blöcke übernimmt der MutationObserver —
        // die früheren Nachlauf-Timeouts (500/1000ms) waren redundant.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', renumberBlocks);
        } else {
            renumberBlocks();
        }

        // Watch for new blocks being added to the DOM
        if (typeof MutationObserver !== 'undefined') {
            let timeout;
            const observer = new MutationObserver(function(mutations) {
                // Debounce: only renumber once after multiple mutations
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    // Check if any mutations added .cbd-needs-numbering elements
                    const hasNumberingElements = mutations.some(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            return Array.from(mutation.addedNodes).some(function(node) {
                                return node.nodeType === 1 && (
                                    node.classList && node.classList.contains('cbd-needs-numbering') ||
                                    node.querySelector && node.querySelector('.cbd-needs-numbering')
                                );
                            });
                        }
                        return false;
                    });

                    if (hasNumberingElements) {
                        renumberBlocks();
                    }
                }, 250);
            });

            // Start observing the document body for added nodes
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    // Initialize
    init();

    // Export for manual calls if needed
    window.CBDRenumberBlocks = renumberBlocks;

})();
