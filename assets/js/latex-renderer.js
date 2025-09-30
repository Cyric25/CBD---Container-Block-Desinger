/**
 * LaTeX Formula Renderer using KaTeX
 *
 * Renders LaTeX formulas in the browser using KaTeX library
 * Handles both frontend display and PDF export preparation
 *
 * @package ContainerBlockDesigner
 * @since 2.7.0
 */

(function() {
    'use strict';

    /**
     * Initialize LaTeX rendering when DOM is ready
     */
    function initLaTeXRendering() {
        if (typeof renderMathInElement === 'undefined' || typeof katex === 'undefined') {
            console.warn('CBD LaTeX: KaTeX library not loaded');
            return;
        }

        // Render all LaTeX formulas
        renderAllFormulas();

        // Re-render when content is dynamically added
        observeDOMChanges();
    }

    /**
     * Render all LaTeX formulas on the page
     */
    function renderAllFormulas() {
        const formulas = document.querySelectorAll('.cbd-latex-formula');

        formulas.forEach(function(formulaElement) {
            renderFormula(formulaElement);
        });
    }

    /**
     * Render a single formula element
     */
    function renderFormula(formulaElement) {
        const latex = formulaElement.getAttribute('data-latex');
        const contentSpan = formulaElement.querySelector('.cbd-latex-content');

        if (!latex || !contentSpan) {
            return;
        }

        try {
            // Render with KaTeX (display mode = centered, block-level)
            katex.render(latex, contentSpan, {
                displayMode: true,
                throwOnError: false,
                errorColor: '#cc0000',
                strict: false,
                trust: false,
                output: 'html', // Use HTML output for better browser support
                fleqn: false // Center equations (not left-aligned)
            });

            // Mark as successfully rendered
            formulaElement.classList.add('cbd-latex-rendered');

            // Add data attribute for PDF export
            formulaElement.setAttribute('data-rendered', 'true');

        } catch (error) {
            console.error('CBD LaTeX: Error rendering formula:', error);
            contentSpan.innerHTML = '<span class="cbd-latex-error">LaTeX Error: ' +
                                   escapeHtml(error.message) + '</span>';
        }
    }

    /**
     * Observe DOM changes and re-render formulas when needed
     */
    function observeDOMChanges() {
        const observer = new MutationObserver(function(mutations) {
            let shouldRerender = false;

            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            if (node.classList && node.classList.contains('cbd-latex-formula')) {
                                shouldRerender = true;
                            } else if (node.querySelector && node.querySelector('.cbd-latex-formula')) {
                                shouldRerender = true;
                            }
                        }
                    });
                }
            });

            if (shouldRerender) {
                // Debounce re-rendering
                clearTimeout(window.cbdLatexRerenderTimeout);
                window.cbdLatexRerenderTimeout = setTimeout(renderAllFormulas, 100);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Export formula to SVG (for PDF export)
     */
    function formulaToSVG(latex) {
        try {
            const html = katex.renderToString(latex, {
                displayMode: true,
                throwOnError: false,
                output: 'html'
            });

            // For PDF export, we'll use the HTML output
            // jsPDF with html2canvas will capture this correctly
            return html;

        } catch (error) {
            console.error('CBD LaTeX: Error converting to SVG:', error);
            return '<div class="cbd-latex-error">LaTeX Error</div>';
        }
    }

    /**
     * Prepare formulas for PDF export
     * This function is called by the PDF export module
     */
    window.cbdPrepareFormulasForPDF = function(element) {
        const formulas = element.querySelectorAll('.cbd-latex-formula');

        formulas.forEach(function(formula) {
            const latex = formula.getAttribute('data-latex');

            if (latex && !formula.getAttribute('data-pdf-ready')) {
                // Ensure formula is rendered
                if (!formula.classList.contains('cbd-latex-rendered')) {
                    renderFormula(formula);
                }

                // Mark as PDF-ready
                formula.setAttribute('data-pdf-ready', 'true');

                // Add print-specific styling
                formula.style.pageBreakInside = 'avoid';
            }
        });

        return true;
    };

    /**
     * Get all formulas as array (for debugging/export)
     */
    window.cbdGetAllFormulas = function() {
        const formulas = document.querySelectorAll('.cbd-latex-formula');
        const result = [];

        formulas.forEach(function(formula) {
            result.push({
                id: formula.getAttribute('data-formula-id'),
                latex: formula.getAttribute('data-latex'),
                rendered: formula.classList.contains('cbd-latex-rendered')
            });
        });

        return result;
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLaTeXRendering);
    } else {
        initLaTeXRendering();
    }

    // Re-initialize after AJAX content loads (for WordPress block editor)
    if (typeof wp !== 'undefined' && wp.domReady) {
        wp.domReady(initLaTeXRendering);
    }

})();