/**
 * LaTeX Formula Renderer using KaTeX
 *
 * Renders LaTeX formulas in the browser using KaTeX library
 * Handles both frontend display and PDF export preparation
 *
 * Öffentliche Schnittstelle (AP-1.1):
 *
 *   window.cbdRenderLatex(root) -> Promise<number>
 *
 *   root     Element oder Document. Optional, Vorgabe `document`.
 *   Wirkung  Rendert alle `.cbd-latex-formula` innerhalb von `root`, die noch
 *            kein Attribut `data-cbd-latex-rendered="1"` tragen; nach
 *            erfolgreichem Rendern wird dieses Attribut gesetzt.
 *   Rückgabe Promise, das die Anzahl NEU gerenderter Formeln liefert und erst
 *            auflöst, nachdem `document.fonts.ready` erfüllt ist – der
 *            Aufrufer kann danach zuverlässig Höhen messen.
 *   KaTeX    Fehlt die Bibliothek, wird `Promise.resolve(0)` geliefert; die
 *            Funktion wirft nie.
 *
 * Aufrufer aus anderen Plugins müssen `typeof window.cbdRenderLatex === 'function'`
 * prüfen – das CDB-Plugin kann abgeschaltet sein.
 *
 * @package ContainerBlockDesigner
 * @since 2.7.0
 */

(function() {
    'use strict';

    var FORMULA_SELECTOR = '.cbd-latex-formula';
    var RENDERED_ATTR = 'data-cbd-latex-rendered';
    var FAILED_ATTR = 'data-cbd-latex-failed';

    /**
     * Debug-Ausgabe (nur mit window.cbdDebug = true)
     */
    function debug() {
        if (!window.cbdDebug || !window.console || !console.log) {
            return;
        }
        var args = Array.prototype.slice.call(arguments);
        args.unshift('[CBD LaTeX]');
        console.log.apply(console, args);
    }

    /**
     * Ist KaTeX benutzbar?
     */
    function katexAvailable() {
        return typeof katex !== 'undefined' && katex && typeof katex.render === 'function';
    }

    /**
     * Wurde dieses Element bereits behandelt (gerendert oder endgültig
     * gescheitert)? Gescheiterte Formeln werden nicht erneut versucht, sonst
     * liefe jeder resize-/Mutation-Durchlauf erneut in denselben Fehler.
     */
    function isHandled(element) {
        return element.getAttribute(RENDERED_ATTR) === '1'
            || element.getAttribute(FAILED_ATTR) === '1';
    }

    /**
     * Alle noch nicht behandelten Formeln innerhalb von `root` einsammeln.
     * `root` selbst kann die Formel sein.
     */
    function collectTargets(root) {
        var scope = root;

        if (!scope || typeof scope.querySelectorAll !== 'function') {
            scope = document;
        }

        var targets = [];

        if (scope.nodeType === 1 && typeof scope.matches === 'function'
            && scope.matches(FORMULA_SELECTOR) && !isHandled(scope)) {
            targets.push(scope);
        }

        var found = scope.querySelectorAll(FORMULA_SELECTOR);
        for (var i = 0; i < found.length; i++) {
            if (!isHandled(found[i])) {
                targets.push(found[i]);
            }
        }

        return targets;
    }

    /**
     * Promise, das auflöst, sobald die Webfonts geladen sind.
     *
     * Erst danach hat eine gerenderte Formel ihre endgültige Größe – wer
     * vorher misst (z. B. das Accordion für seine Aufklapphöhe), misst die
     * Ersatzschrift.
     */
    function whenFontsReady() {
        try {
            if (document.fonts && document.fonts.ready
                && typeof document.fonts.ready.then === 'function') {
                return document.fonts.ready.then(function() {
                    return null;
                }, function() {
                    return null;
                });
            }
        } catch (e) {
            // document.fonts kann in exotischen Umgebungen werfen
        }
        return Promise.resolve(null);
    }

    /**
     * Render a single formula element
     *
     * @return {boolean} true, wenn in diesem Aufruf neu gerendert wurde
     */
    function renderFormula(formulaElement) {
        if (!formulaElement || formulaElement.nodeType !== 1 || isHandled(formulaElement)) {
            return false;
        }

        var latex = formulaElement.getAttribute('data-latex');
        var contentSpan = formulaElement.querySelector('.cbd-latex-content');

        if (!latex || !contentSpan) {
            return false;
        }

        if (!katexAvailable()) {
            return false;
        }

        // Determine if this is a display formula (centered, block-level)
        var isDisplay = formulaElement.classList
            && formulaElement.classList.contains('cbd-latex-display');

        try {
            // Render with KaTeX
            // displayMode: true = centered, block-level (for $$formula$$)
            // displayMode: false = inline, within text flow (for $formula$)
            katex.render(latex, contentSpan, {
                displayMode: !!isDisplay, // Use displayMode only for display formulas
                throwOnError: false,
                errorColor: '#cc0000',
                strict: false,
                trust: false,
                output: 'html', // Use HTML output for better browser support
                fleqn: false // Center equations (not left-aligned) for display mode
            });

            // Mark as successfully rendered
            formulaElement.classList.add('cbd-latex-rendered');

            // Add data attribute for PDF export
            formulaElement.setAttribute('data-rendered', 'true');

            // Schnittstellen-Attribut: verhindert Doppelrendrung
            formulaElement.setAttribute(RENDERED_ATTR, '1');

            return true;

        } catch (error) {
            contentSpan.innerHTML = '<span class="cbd-latex-error">LaTeX Error: ' +
                                   escapeHtml(error && error.message ? error.message : String(error)) +
                                   '</span>';
            // Nicht erneut versuchen – der Fehler wiederholt sich sonst bei
            // jedem resize- und Mutation-Durchlauf.
            formulaElement.setAttribute(FAILED_ATTR, '1');
            return false;
        }
    }

    /**
     * Öffentliche Schnittstelle – siehe Dateikopf.
     *
     * @param {Element|Document} [root=document]
     * @returns {Promise<number>}
     */
    window.cbdRenderLatex = function(root) {
        if (!katexAvailable()) {
            return Promise.resolve(0);
        }

        var targets;
        try {
            targets = collectTargets(root);
        } catch (error) {
            if (window.console && console.warn) {
                console.warn('[CBD LaTeX] Formeln konnten nicht eingesammelt werden:', error);
            }
            return Promise.resolve(0);
        }

        var rendered = 0;
        for (var i = 0; i < targets.length; i++) {
            if (renderFormula(targets[i])) {
                rendered++;
            }
        }

        debug('gerendert:', rendered, 'von', targets.length, 'Kandidaten');

        return whenFontsReady().then(function() {
            return rendered;
        });
    };

    /**
     * Render all LaTeX formulas on the page
     */
    function renderAllFormulas() {
        return window.cbdRenderLatex(document);
    }

    /**
     * Initialize LaTeX rendering when DOM is ready
     *
     * Nur KaTeX ist Voraussetzung. `renderMathInElement` (auto-render) wird
     * bewusst NICHT verlangt: Dieser Renderer arbeitet über das vom
     * PHP-Parser erzeugte Markup, nicht über die Auto-Erkennung. Ein
     * fehlgeschlagener Nachlade-Versuch der Erweiterung darf das Rendern
     * nicht verhindern.
     */
    function initLaTeXRendering() {
        if (!katexAvailable()) {
            return;
        }

        renderAllFormulas();
    }

    /**
     * Observe DOM changes and re-render formulas when needed
     *
     * Wird SOFORT beim Laden der Datei gestartet, nicht erst bei
     * DOMContentLoaded: Blöcke wie das Accordion bauen ihre Struktur bereits
     * vorher um. Reagiert weiterhin nur auf hinzugefügte Knoten.
     */
    var observerStarted = false;

    function observeDOMChanges() {
        if (observerStarted || typeof MutationObserver === 'undefined') {
            return;
        }

        var target = document.body || document.documentElement;
        if (!target) {
            return;
        }

        var observer = new MutationObserver(function(mutations) {
            var shouldRerender = false;

            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length === 0) {
                    return;
                }
                Array.prototype.forEach.call(mutation.addedNodes, function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.classList && node.classList.contains('cbd-latex-formula')) {
                            shouldRerender = true;
                        } else if (node.querySelector && node.querySelector(FORMULA_SELECTOR)) {
                            shouldRerender = true;
                        }
                    }
                });
            });

            if (shouldRerender) {
                // Debounce re-rendering
                clearTimeout(window.cbdLatexRerenderTimeout);
                window.cbdLatexRerenderTimeout = setTimeout(renderAllFormulas, 100);
            }
        });

        observer.observe(target, {
            childList: true,
            subtree: true
        });

        observerStarted = true;
        debug('MutationObserver gestartet');
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Prepare formulas for PDF export
     * This function is called by the PDF export module
     */
    window.cbdPrepareFormulasForPDF = function(element) {
        if (!element || typeof element.querySelectorAll !== 'function') {
            return false;
        }

        var formulas = element.querySelectorAll(FORMULA_SELECTOR);

        Array.prototype.forEach.call(formulas, function(formula) {
            var latex = formula.getAttribute('data-latex');

            if (latex && !formula.getAttribute('data-pdf-ready')) {
                // Ensure formula is rendered
                renderFormula(formula);

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
        var formulas = document.querySelectorAll(FORMULA_SELECTOR);
        var result = [];

        Array.prototype.forEach.call(formulas, function(formula) {
            result.push({
                id: formula.getAttribute('data-formula-id'),
                latex: formula.getAttribute('data-latex'),
                rendered: formula.getAttribute(RENDERED_ATTR) === '1'
            });
        });

        return result;
    };

    // MutationObserver sofort starten (Skript liegt im Footer, <body> steht).
    observeDOMChanges();
    if (!observerStarted) {
        document.addEventListener('DOMContentLoaded', observeDOMChanges);
    }

    // Neu vermessen/rendern, wenn sich das Layout ändert. Das Accordion feuert
    // beim Aufklappen ein resize-Ereignis; damit greift der Fall auch dann,
    // wenn ein Aufrufer window.cbdRenderLatex nicht direkt nutzt.
    var resizeTimeout = null;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            renderAllFormulas();
        }, 150);
    });

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
