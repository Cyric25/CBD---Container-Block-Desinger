/**
 * Server-Side PDF Generation for Container Block Designer
 *
 * Hybrid approach:
 * 1. Expand all collapsed blocks (including nested)
 * 2. Extract clean HTML per block
 * 3. Convert KaTeX formulas to rendered HTML
 * 4. Screenshot only interactive elements (modular-blocks)
 * 5. Send structured data to server (mPDF renders the PDF)
 * 6. Restore original collapsed states
 *
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

(function () {
    'use strict';

    var $ = window.jQuery || window.$;
    if (!$) {
        console.error('[CBD PDF] jQuery not available');
        return;
    }

    // iOS detection
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    /**
     * Main export function - called by floating-pdf-button.js
     *
     * @param {jQuery|Array} containerBlocks jQuery collection or array of jQuery elements
     * @param {string} mode 'visual'|'print'|'text'
     * @param {number} quality Scale factor (only for screenshots of interactive elements)
     */
    window.cbdPDFExportServerSide = function (containerBlocks, mode, quality) {
        mode = mode || 'visual';
        quality = quality || (isIOS ? 1 : 1.5);

        // Normalize to jQuery collection
        if (Array.isArray(containerBlocks)) {
            var $merged = $();
            for (var i = 0; i < containerBlocks.length; i++) {
                $merged = $merged.add(containerBlocks[i]);
            }
            containerBlocks = $merged;
        } else if (!containerBlocks.jquery) {
            containerBlocks = $(containerBlocks);
        }

        if (containerBlocks.length === 0) {
            alert('Keine Container-Blöcke zum Exportieren gefunden.');
            return false;
        }

        console.log('[CBD PDF] Starting export:', containerBlocks.length, 'blocks, mode:', mode);

        // Show progress overlay
        var $overlay = createProgressOverlay(containerBlocks.length);
        $('body').append($overlay);

        // Step 1: Expand all collapsed blocks
        var collapsedStates = expandAllBlocks(containerBlocks);

        // Step 2: Wait for expansion animation, then process
        setTimeout(function () {
            processBlocksSequentially(containerBlocks, mode, quality, $overlay, collapsedStates);
        }, 400);

        return true;
    };

    // =========================================================================
    // Block Expansion (reuse proven logic from old implementation)
    // =========================================================================

    /**
     * Expand all collapsed blocks (including nested ones)
     * Returns array of states to restore later
     */
    function expandAllBlocks(containerBlocks) {
        var states = [];

        containerBlocks.each(function () {
            var $block = $(this);

            // Find ALL interactive containers (including nested)
            var $allContainers = $block.find('[data-wp-interactive="container-block-designer"]');
            if ($block.is('[data-wp-interactive="container-block-designer"]')) {
                $allContainers = $allContainers.add($block);
            }

            $allContainers.each(function () {
                var $container = $(this);
                var $content = $container.find('.cbd-container-content').first();

                if ($content.length > 0) {
                    var computed = window.getComputedStyle($content[0]);
                    var isHidden = computed.display === 'none' ||
                        computed.visibility === 'hidden' ||
                        computed.maxHeight === '0px';

                    if (isHidden) {
                        states.push({
                            element: $content[0],
                            type: 'content',
                            display: $content[0].style.display,
                            visibility: $content[0].style.visibility,
                            maxHeight: $content[0].style.maxHeight,
                            overflow: $content[0].style.overflow,
                            height: $content[0].style.height,
                            opacity: $content[0].style.opacity
                        });

                        $content[0].style.setProperty('display', 'block', 'important');
                        $content[0].style.setProperty('visibility', 'visible', 'important');
                        $content[0].style.setProperty('opacity', '1', 'important');
                        $content[0].style.setProperty('max-height', 'none', 'important');
                        $content[0].style.setProperty('overflow', 'visible', 'important');
                        $content[0].style.setProperty('height', 'auto', 'important');
                    }
                }
            });

            // Expand <details> elements
            $block.find('details').each(function () {
                if (!this.open) {
                    states.push({ element: this, type: 'details', open: false });
                    this.open = true;
                }
            });

            // Note: Drawings (Tafelbilder/Notizen) are injected directly from
            // localStorage in processOneBlock() via injectDrawingsFromStorage()
        });

        return states;
    }

    /**
     * Restore original collapsed states
     */
    function restoreStates(states) {
        for (var i = 0; i < states.length; i++) {
            var s = states[i];
            if (s.type === 'details') {
                s.element.open = s.open;
            } else if (s.type === 'content') {
                var el = s.element;
                el.style.removeProperty('display');
                el.style.removeProperty('visibility');
                el.style.removeProperty('opacity');
                el.style.removeProperty('max-height');
                el.style.removeProperty('overflow');
                el.style.removeProperty('height');
                el.style.display = s.display || '';
                el.style.visibility = s.visibility || '';
                el.style.maxHeight = s.maxHeight || '';
                el.style.overflow = s.overflow || '';
                el.style.height = s.height || '';
                el.style.opacity = s.opacity || '';
            }
        }
    }

    // =========================================================================
    // Block Processing Pipeline
    // =========================================================================

    /**
     * Process blocks one by one (sequential to avoid memory issues on iOS)
     */
    function processBlocksSequentially(containerBlocks, mode, quality, $overlay, collapsedStates) {
        var blocksData = [];
        var totalBlocks = containerBlocks.length;
        var currentIndex = 0;

        function processNext() {
            if (currentIndex >= totalBlocks) {
                // All blocks processed - restore states and send to server
                restoreStates(collapsedStates);
                updateProgress($overlay, totalBlocks, totalBlocks, 'PDF wird auf dem Server erstellt...');
                sendToServer(blocksData, mode, $overlay);
                return;
            }

            var $block = $(containerBlocks[currentIndex]);
            updateProgress($overlay, currentIndex + 1, totalBlocks, 'Block ' + (currentIndex + 1) + ' wird verarbeitet...');

            processOneBlock($block, mode, quality, function (blockData) {
                blocksData.push(blockData);
                currentIndex++;
                // Use setTimeout to prevent UI freeze
                setTimeout(processNext, 50);
            });
        }

        processNext();
    }

    /**
     * Process a single block: extract HTML, formulas, and screenshots
     */
    function processOneBlock($block, mode, quality, callback) {
        // Step 1: Find interactive elements FIRST and ensure they have IDs
        // (must happen before cloning so the IDs are included in the clone)
        var interactiveElements = findInteractiveElements($block);

        // Step 2: Clone block for HTML extraction (don't modify original)
        var $clone = $block.clone();

        // Remove interactive controls and unnecessary elements from clone
        $clone.find('.cbd-action-buttons').remove();
        $clone.find('.cbd-collapse-toggle').remove();
        $clone.find('.cbd-header-menu').remove();
        $clone.find('.cbd-container-number').remove();
        $clone.find('.cbd-selection-menu').remove();
        $clone.find('.cbd-board-overlay').remove();
        $clone.find('.cbd-drawing-canvas').remove();
        $clone.find('script').remove();        // Remove isolated scripts (not needed in PDF)
        $clone.find('svg').remove();           // Remove SVG icons (controls)

        // Remove existing drawing sections (we'll rebuild from localStorage data)
        $clone.find('.cbd-drawing-section').remove();
        $clone.find('.cbd-local-drawing-section').remove();
        $clone.find('.cbd-class-drawing-section').remove();

        // Inject drawings directly from localStorage
        injectDrawingsFromStorage($block, $clone);

        // Replace KaTeX formulas with readable text in clone.
        // mPDF cannot handle KaTeX's complex nested CSS spans, causing doubled text.
        // Extract the visible text from the rendered KaTeX output instead of raw LaTeX.
        $clone.find('.cbd-latex-formula').each(function () {
            var $formula = $(this);
            var isDisplay = $formula.hasClass('cbd-latex-display');

            // Strategy: get the visible text from KaTeX's rendered output
            // .katex-html contains the visual representation
            var readableText = '';
            var $katexHtml = $formula.find('.katex-html');
            if ($katexHtml.length > 0) {
                // Clone KaTeX HTML, remove hidden elements, extract text
                var $kClone = $katexHtml.clone();
                $kClone.find('.katex-mathml').remove();
                readableText = $kClone.text().replace(/\s+/g, ' ').trim();
            }

            // Fallback: convert data-latex to readable text
            if (!readableText) {
                var latex = $formula.attr('data-latex') || '';
                readableText = latexToReadable(latex);
            }

            if (readableText) {
                var style = isDisplay
                    ? 'display:block; text-align:center; margin:10px 0; font-size:12pt; font-family:dejavusans,sans-serif;'
                    : 'display:inline; font-family:dejavusans,sans-serif;';
                var tag = isDisplay ? 'div' : 'span';
                var replacement = '<' + tag + ' class="cbd-pdf-formula" style="' + style + '">' +
                    $('<span>').text(readableText).html() +
                    '</' + tag + '>';
                $formula.replaceWith(replacement);
            }
        });

        // Ensure all content is visible in clone
        $clone.find('.cbd-container-content, .cbd-content, .cbd-collapsible-content').each(function () {
            this.style.setProperty('display', 'block', 'important');
            this.style.setProperty('visibility', 'visible', 'important');
            this.style.setProperty('opacity', '1', 'important');
            this.style.setProperty('max-height', 'none', 'important');
        });
        $clone.find('.cbd-collapsed').removeClass('cbd-collapsed');

        // Force page-break-inside:avoid on the container block itself (inline style for mPDF)
        $clone.find('.cbd-container-block').each(function () {
            this.style.setProperty('page-break-inside', 'avoid', 'important');
        });
        // Also on the outermost wrapper
        $clone[0].style.setProperty('page-break-inside', 'avoid', 'important');

        // Step 3: Replace interactive elements in clone with simple placeholders
        // This avoids the server having to regex-match complex nested HTML
        for (var i = 0; i < interactiveElements.length; i++) {
            var item = interactiveElements[i];
            var $cloneEl = $clone.find('#' + CSS.escape(item.id));
            if ($cloneEl.length > 0) {
                $cloneEl.replaceWith(
                    '<div data-cbd-screenshot-id="' + item.id + '" ' +
                    'style="page-break-inside:avoid; margin:8px 0; text-align:center;">' +
                    '[Screenshot: ' + item.id + ']</div>'
                );
            }
        }

        // Extract title
        var title = $clone.find('.cbd-block-title').first().text().trim() || '';

        // Formulas are already replaced as plain text in the clone above,
        // no need to extract and re-inject on the server side
        var formulas = [];

        // Get clean HTML from clone
        var html = $clone[0].outerHTML;

        if (interactiveElements.length > 0 && mode !== 'text') {
            // Take screenshots of interactive elements (from original DOM)
            screenshotInteractiveElements(interactiveElements, quality, function (screenshots) {
                callback({
                    html: html,
                    title: title,
                    formulas: formulas,
                    screenshots: screenshots
                });
            });
        } else {
            callback({
                html: html,
                title: title,
                formulas: formulas,
                screenshots: []
            });
        }
    }

    // =========================================================================
    // LaTeX to Readable Text Conversion
    // =========================================================================

    /**
     * Convert raw LaTeX string to human-readable Unicode text.
     * Used as fallback when KaTeX rendered output is not available.
     */
    function latexToReadable(latex) {
        if (!latex) return '';
        var s = latex;

        // Greek letters
        var greekMap = {
            '\\alpha': '\u03B1', '\\beta': '\u03B2', '\\gamma': '\u03B3',
            '\\delta': '\u03B4', '\\epsilon': '\u03B5', '\\zeta': '\u03B6',
            '\\eta': '\u03B7', '\\theta': '\u03B8', '\\iota': '\u03B9',
            '\\kappa': '\u03BA', '\\lambda': '\u03BB', '\\mu': '\u03BC',
            '\\nu': '\u03BD', '\\xi': '\u03BE', '\\pi': '\u03C0',
            '\\rho': '\u03C1', '\\sigma': '\u03C3', '\\tau': '\u03C4',
            '\\upsilon': '\u03C5', '\\phi': '\u03C6', '\\chi': '\u03C7',
            '\\psi': '\u03C8', '\\omega': '\u03C9',
            '\\Delta': '\u0394', '\\Sigma': '\u03A3', '\\Omega': '\u03A9',
            '\\Pi': '\u03A0', '\\Lambda': '\u039B', '\\Gamma': '\u0393'
        };
        for (var cmd in greekMap) {
            s = s.split(cmd).join(greekMap[cmd]);
        }

        // Math symbols
        s = s.replace(/\\approx/g, '\u2248');    // ≈
        s = s.replace(/\\neq/g, '\u2260');       // ≠
        s = s.replace(/\\leq/g, '\u2264');       // ≤
        s = s.replace(/\\geq/g, '\u2265');       // ≥
        s = s.replace(/\\pm/g, '\u00B1');        // ±
        s = s.replace(/\\times/g, '\u00D7');     // ×
        s = s.replace(/\\cdot/g, '\u00B7');      // ·
        s = s.replace(/\\rightarrow/g, '\u2192'); // →
        s = s.replace(/\\leftarrow/g, '\u2190');  // ←
        s = s.replace(/\\infty/g, '\u221E');     // ∞

        // \text{...} → content
        s = s.replace(/\\text\{([^}]*)\}/g, '$1');
        s = s.replace(/\\textbf\{([^}]*)\}/g, '$1');
        s = s.replace(/\\mathrm\{([^}]*)\}/g, '$1');

        // \frac{a}{b} → (a)/(b)
        s = s.replace(/\\frac\{([^}]*)\}\{([^}]*)\}/g, '($1)/($2)');

        // \sqrt{x} → √(x)
        s = s.replace(/\\sqrt\{([^}]*)\}/g, '\u221A($1)');

        // Subscripts: _{...} → content (just inline)
        s = s.replace(/\_\{([^}]*)\}/g, '$1');
        s = s.replace(/\_([a-zA-Z0-9])/g, '$1');

        // Superscripts: ^{...} → content
        s = s.replace(/\^\{([^}]*)\}/g, '$1');
        s = s.replace(/\^([a-zA-Z0-9+\-])/g, '$1');

        // Remove remaining LaTeX commands
        s = s.replace(/\\[a-zA-Z]+/g, '');

        // Clean up braces and extra spaces
        s = s.replace(/[{}]/g, '');
        s = s.replace(/\s+/g, ' ').trim();

        return s;
    }

    // =========================================================================
    // Drawing Injection from localStorage
    // =========================================================================

    /**
     * Read drawing data from localStorage and inject as <img> tags into clone.
     * Works for both local drawings (Eigene Notizen) and multi-page drawings.
     *
     * localStorage keys:
     *   cbd-board-{stableId}        → Page 0 PNG data URL
     *   cbd-board-{stableId}:pN     → Page N PNG data URL
     *   cbd-board-pagecount-{stableId} → Total page count
     *   cbd-board-{stableId}-bgcolor   → Board background color
     *
     * @param {jQuery} $original - Original block (to read data-stable-id)
     * @param {jQuery} $clone    - Cloned block (to inject images into)
     */
    function injectDrawingsFromStorage($original, $clone) {
        // Find all containers with data-stable-id (including the block itself)
        var containers = [];

        // Check the block itself
        var blockStableId = $original.attr('data-stable-id');
        if (blockStableId) {
            containers.push({
                stableId: blockStableId,
                $cloneTarget: $clone
            });
        }

        // Check nested containers (skip duplicates with same stableId as block)
        $original.find('[data-stable-id]').each(function () {
            var stableId = $(this).attr('data-stable-id');
            if (stableId && stableId !== blockStableId) {
                // Find corresponding element in clone
                var $cloneEl = $clone.find('[data-stable-id="' + stableId + '"]');
                if ($cloneEl.length > 0) {
                    containers.push({
                        stableId: stableId,
                        $cloneTarget: $cloneEl
                    });
                }
            }
        });

        var totalInjected = 0;

        for (var c = 0; c < containers.length; c++) {
            var stableId = containers[c].stableId;
            var $target = containers[c].$cloneTarget;

            // Read page count
            var pageCountStr = null;
            try { pageCountStr = localStorage.getItem('cbd-board-pagecount-' + stableId); } catch (e) {}
            var totalPages = pageCountStr ? Math.max(1, parseInt(pageCountStr, 10)) : 1;

            // Collect all page images
            var pages = [];
            for (var p = 0; p < totalPages; p++) {
                var key = p === 0
                    ? 'cbd-board-' + stableId
                    : 'cbd-board-' + stableId + ':p' + p;
                var dataUrl = null;
                try { dataUrl = localStorage.getItem(key); } catch (e) {}

                if (dataUrl && dataUrl.indexOf('data:image/') === 0) {
                    // Read optional background color
                    var bgColor = null;
                    try { bgColor = localStorage.getItem(key + '-bgcolor'); } catch (e) {}

                    // Compress drawing for PDF (PNG → smaller JPEG)
                    var compressed = recompressBase64(dataUrl, 0.75, 1200);
                    pages.push({
                        dataUrl: compressed || dataUrl,
                        bgColor: bgColor,
                        pageIndex: p
                    });
                }
            }

            if (pages.length === 0) continue;

            // Build HTML for drawing section
            var drawingHtml = '<div class="cbd-pdf-drawing-section" style="' +
                'margin: 12px 0; padding: 8px; page-break-inside: avoid;">';

            drawingHtml += '<div style="font-size: 11px; color: #666; margin-bottom: 6px; ' +
                'font-style: italic;">Eigene Notiz' +
                (pages.length > 1 ? ' (' + pages.length + ' Seiten)' : '') +
                '</div>';

            for (var j = 0; j < pages.length; j++) {
                var page = pages[j];
                var bgStyle = page.bgColor
                    ? 'background-color:' + page.bgColor + ';'
                    : '';

                drawingHtml += '<div style="margin: 4px 0; text-align: center; ' +
                    bgStyle + ' page-break-inside: avoid;">';
                drawingHtml += '<img src="' + page.dataUrl + '" style="' +
                    'max-width: 100%; height: auto; display: block; margin: 0 auto;" ' +
                    'alt="Zeichnung Seite ' + (page.pageIndex + 1) + '" />';
                drawingHtml += '</div>';
            }

            drawingHtml += '</div>';

            // Inject after the content area of this container
            var $content = $target.find('.cbd-container-content').first();
            if ($content.length > 0) {
                $content.append(drawingHtml);
            } else {
                $target.append(drawingHtml);
            }

            totalInjected += pages.length;
        }

        if (totalInjected > 0) {
            console.log('[CBD PDF] Injected', totalInjected, 'drawing page(s) from localStorage');
        }
    }

    // =========================================================================
    // Formula Extraction
    // =========================================================================

    /**
     * Extract KaTeX formula data from block
     * Captures the rendered HTML so mPDF can display it
     */
    function extractFormulas($block) {
        var formulas = [];

        $block.find('.cbd-latex-formula').each(function (index) {
            var $formula = $(this);
            var latex = $formula.attr('data-latex') || '';
            var id = $formula.attr('id') || 'formula-' + index + '-' + Date.now();

            // Ensure element has an ID for server-side replacement
            if (!$formula.attr('id')) {
                $formula.attr('id', id);
            }

            // Get the rendered KaTeX HTML (already rendered in the browser)
            var $content = $formula.find('.cbd-latex-content').first();
            var renderedHtml = '';

            if ($content.length > 0 && $content.find('.katex').length > 0) {
                // KaTeX already rendered - grab the HTML
                // Clone to remove MathML annotations (cause doubled text in PDF)
                var $contentClone = $content.clone();
                $contentClone.find('.katex-mathml').remove();
                renderedHtml = $contentClone.html();
            } else if (latex && typeof katex !== 'undefined') {
                // Render now with KaTeX
                try {
                    var isDisplay = $formula.hasClass('cbd-latex-display');
                    var rawHtml = katex.renderToString(latex, {
                        displayMode: isDisplay,
                        throwOnError: false,
                        output: 'html'
                    });
                    // Remove MathML annotations to prevent doubled text
                    var $tmp = $('<div>').html(rawHtml);
                    $tmp.find('.katex-mathml').remove();
                    renderedHtml = $tmp.html();
                } catch (e) {
                    renderedHtml = '<span style="color:red;">Formula Error</span>';
                }
            }

            if (latex || renderedHtml) {
                formulas.push({
                    id: id,
                    latex: latex,
                    renderedHtml: renderedHtml
                });
            }
        });

        return formulas;
    }

    // =========================================================================
    // Interactive Element Screenshots
    // =========================================================================

    /**
     * Find interactive elements from "Eigene WP Blocks" that need screenshots.
     * Tags each element with a capture method: 'webgl', 'canvas', or 'html2canvas'.
     */
    function findInteractiveElements($block) {
        var elements = [];

        // Modular blocks (educational interactive blocks)
        var selectors = [
            '[class*="wp-block-modular-blocks-"]',
            '.modular-block-drag-and-drop',
            '.modular-block-multiple-choice',
            '.modular-block-drag-the-words',
            '.modular-block-statement-connector',
            '.modular-block-summary-block',
            '.modular-block-image-comparison',
            '.modular-block-point-of-interest',
            '.modular-block-molecule-viewer',
            '.modular-block-chart-block'
        ];

        $block.find(selectors.join(', ')).each(function (index) {
            var $el = $(this);

            // Skip if already inside another interactive element (avoid nested screenshots)
            if ($el.parents(selectors.join(', ')).length > 0) {
                return;
            }

            // Ensure it has an ID
            var id = this.id || 'interactive-' + index + '-' + Date.now();
            if (!this.id) {
                this.id = id;
            }

            // Determine best capture method
            var method = 'html2canvas';
            var webglCanvas = this.querySelector('canvas');
            if (webglCanvas) {
                try {
                    var gl = webglCanvas.getContext('webgl') || webglCanvas.getContext('webgl2');
                    if (gl) {
                        method = 'webgl';
                    } else {
                        method = 'canvas';
                    }
                } catch (e) {
                    // Canvas exists but context check failed - try direct export
                    method = 'canvas';
                }
            }

            elements.push({ element: this, id: id, method: method, canvas: webglCanvas });
        });

        return elements;
    }

    // Max screenshot dimensions to keep payload small
    var MAX_SCREENSHOT_WIDTH = 1200;
    var MAX_SCREENSHOT_HEIGHT = 900;

    /**
     * Downscale a canvas if it exceeds max dimensions, then export as JPEG.
     * Returns base64 string.
     */
    function canvasToCompressedBase64(sourceCanvas, jpegQuality) {
        var w = sourceCanvas.width;
        var h = sourceCanvas.height;
        var ratio = Math.min(1, MAX_SCREENSHOT_WIDTH / w, MAX_SCREENSHOT_HEIGHT / h);

        if (ratio < 1) {
            // Downscale via offscreen canvas
            var nw = Math.round(w * ratio);
            var nh = Math.round(h * ratio);
            var tmp = document.createElement('canvas');
            tmp.width = nw;
            tmp.height = nh;
            var ctx = tmp.getContext('2d');
            ctx.drawImage(sourceCanvas, 0, 0, nw, nh);
            return tmp.toDataURL('image/jpeg', jpegQuality);
        }

        return sourceCanvas.toDataURL('image/jpeg', jpegQuality);
    }

    /**
     * Take screenshots of interactive elements.
     * Uses direct canvas export for WebGL/canvas elements (fast & reliable),
     * falls back to html2canvas for DOM-only elements.
     * Sequential processing to avoid memory issues on iOS.
     */
    function screenshotInteractiveElements(elements, quality, callback) {
        var screenshots = [];
        var index = 0;
        var jpegQuality = 0.75; // Slightly lower for smaller payload

        // iOS canvas pixel limit
        var maxPixels = isIOS ? 16000000 : 64000000;

        function nextScreenshot() {
            if (index >= elements.length) {
                callback(screenshots);
                return;
            }

            var item = elements[index];
            var el = item.element;

            // --- WebGL / Canvas: direct export (fast, no html2canvas needed) ---
            if ((item.method === 'webgl' || item.method === 'canvas') && item.canvas) {
                try {
                    // For WebGL: force a render before capture if viewer has a render method
                    if (item.method === 'webgl') {
                        var viewer = item.canvas._symmetryViewer || item.canvas._3dmolViewer ||
                            (window.$3Dmol && item.canvas.__3dmolViewer);
                        if (viewer && typeof viewer.render === 'function') {
                            viewer.render();
                        }
                        // Ensure preserveDrawingBuffer by reading pixels immediately
                        var gl = item.canvas.getContext('webgl', { preserveDrawingBuffer: true }) ||
                                 item.canvas.getContext('webgl2', { preserveDrawingBuffer: true });
                    }

                    var base64 = canvasToCompressedBase64(item.canvas, jpegQuality);

                    // Check if canvas export produced valid data (not blank)
                    if (base64 && base64.length > 1000) {
                        console.log('[CBD PDF] Direct canvas export for', item.id, '(' + Math.round(base64.length / 1024) + ' KB)');
                        screenshots.push({ id: item.id, base64: base64 });
                        index++;
                        setTimeout(nextScreenshot, 20);
                        return;
                    }
                    // Blank canvas - fall through to html2canvas
                    console.warn('[CBD PDF] Canvas export blank for', item.id, '- trying html2canvas');
                } catch (err) {
                    console.warn('[CBD PDF] Direct canvas export failed for', item.id, err);
                }
            }

            // --- html2canvas fallback for DOM elements ---
            if (typeof html2canvas === 'undefined') {
                console.warn('[CBD PDF] html2canvas not available, skipping', item.id);
                index++;
                setTimeout(nextScreenshot, 20);
                return;
            }

            var scale = quality;
            var totalPixels = el.offsetWidth * el.offsetHeight * scale * scale;
            if (totalPixels > maxPixels) {
                scale = Math.max(1, Math.sqrt(maxPixels / (el.offsetWidth * el.offsetHeight)));
            }

            html2canvas(el, {
                useCORS: true,
                allowTaint: false,
                scale: scale,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(function (canvas) {
                var base64 = canvasToCompressedBase64(canvas, jpegQuality);
                console.log('[CBD PDF] html2canvas for', item.id, '(' + Math.round(base64.length / 1024) + ' KB)');

                screenshots.push({ id: item.id, base64: base64 });
                index++;
                setTimeout(nextScreenshot, 50);
            }).catch(function (err) {
                console.warn('[CBD PDF] Screenshot failed for', item.id, err);
                index++;
                setTimeout(nextScreenshot, 50);
            });
        }

        nextScreenshot();
    }

    // =========================================================================
    // Image Recompression (synchronous via existing canvas)
    // =========================================================================

    /**
     * Synchronously recompress a base64 image to lower quality/smaller size.
     * Returns new base64 string or null on failure.
     */
    function recompressBase64(base64, quality, maxWidth) {
        try {
            var img = document.createElement('img');
            img.src = base64;
            // img should load synchronously from data URI
            if (!img.complete || img.naturalWidth === 0) return null;

            var w = img.naturalWidth;
            var h = img.naturalHeight;
            var ratio = Math.min(1, maxWidth / w);
            var nw = Math.round(w * ratio);
            var nh = Math.round(h * ratio);

            var canvas = document.createElement('canvas');
            canvas.width = nw;
            canvas.height = nh;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, nw, nh);
            return canvas.toDataURL('image/jpeg', quality);
        } catch (e) {
            return null;
        }
    }

    // =========================================================================
    // Server Communication
    // =========================================================================

    /**
     * Send structured block data to server for PDF generation
     * First runs a diagnosis check, then sends the actual PDF request
     */
    function sendToServer(blocksData, mode, $overlay) {
        // Step 1: Run diagnosis via REST API (bypasses admin-ajax.php)
        console.log('[CBD PDF] Running server diagnosis via REST API...');

        var diagnoseUrl = cbdPDFData.resturl ? cbdPDFData.resturl + 'pdf-diagnose' : null;

        if (!diagnoseUrl) {
            console.warn('[CBD PDF] No REST URL, trying PDF directly');
            sendPDFRequest(blocksData, mode, $overlay);
            return;
        }

        $.ajax({
            url: diagnoseUrl,
            type: 'GET',
            timeout: 15000,
            success: function (info) {
                console.log('[CBD PDF] Server info:', info);

                // Check for missing extensions
                var problems = [];
                if (!info.ext_mbstring) problems.push('PHP-Erweiterung "mbstring" fehlt');
                if (!info.ext_gd) problems.push('PHP-Erweiterung "gd" fehlt');
                if (!info.mpdf_available && !info.tcpdf_available) {
                    problems.push('Keine PDF-Bibliothek verfügbar (mPDF: ' + (info.mpdf_error || 'nicht geladen') + ')');
                }
                if (!info.temp_dir_writable) problems.push('Temp-Verzeichnis nicht beschreibbar');

                if (problems.length > 0) {
                    $overlay.remove();
                    handleError('Server-Voraussetzungen fehlen:\n- ' + problems.join('\n- '));
                    return;
                }

                // All checks passed - proceed with PDF generation
                sendPDFRequest(blocksData, mode, $overlay);
            },
            error: function (xhr) {
                console.warn('[CBD PDF] REST diagnosis failed:', xhr.status, xhr.responseText);
                // Try PDF generation anyway
                sendPDFRequest(blocksData, mode, $overlay);
            }
        });
    }

    /**
     * Actually send the PDF generation request
     */
    function sendPDFRequest(blocksData, mode, $overlay) {
        // Collect CSS variable values from the current page
        var cssVariables = collectCSSVariables();

        // Build filename
        var pageTitle = document.title.replace(/[^a-zA-Z0-9äöüÄÖÜß\s-]/g, '').trim();
        var filename = (pageTitle || 'container-blocks') + '-' + new Date().toISOString().slice(0, 10) + '.pdf';

        // Calculate payload size and warn if too large
        var payload = JSON.stringify(blocksData);
        var payloadKB = Math.round(payload.length / 1024);
        console.log('[CBD PDF] Sending', blocksData.length, 'blocks to server, payload:', payloadKB, 'KB');

        // If payload > 6MB, try to reduce screenshot quality
        if (payload.length > 6 * 1024 * 1024) {
            console.warn('[CBD PDF] Payload too large (' + payloadKB + ' KB), recompressing screenshots...');
            for (var i = 0; i < blocksData.length; i++) {
                var block = blocksData[i];
                if (block.screenshots && block.screenshots.length > 0) {
                    for (var j = 0; j < block.screenshots.length; j++) {
                        var ss = block.screenshots[j];
                        if (ss.base64 && ss.base64.length > 200000) {
                            // Re-encode at lower quality via temp canvas
                            try {
                                var img = new Image();
                                var recompressed = recompressBase64(ss.base64, 0.5, 800);
                                if (recompressed) {
                                    ss.base64 = recompressed;
                                }
                            } catch (e) { /* keep original */ }
                        }
                    }
                }
            }
            payload = JSON.stringify(blocksData);
            console.log('[CBD PDF] Recompressed payload:', Math.round(payload.length / 1024), 'KB');
        }

        // Use REST API (bypasses admin-ajax.php which may have issues)
        var pdfUrl = cbdPDFData.resturl ? cbdPDFData.resturl + 'generate-pdf' : null;

        if (pdfUrl) {
            // REST API endpoint
            $.ajax({
                url: pdfUrl,
                type: 'POST',
                timeout: 120000,
                contentType: 'application/json',
                data: JSON.stringify({
                    blocks_json: blocksData,
                    filename: filename,
                    mode: mode,
                    css_variables: cssVariables
                }),
                success: function (response) {
                    $overlay.remove();

                    if (response.success) {
                        console.log('[CBD PDF] PDF generated with engine:', response.engine);
                        downloadPDF(response.url, response.filename || filename);
                    } else {
                        console.error('[CBD PDF] Server error:', response.message);
                        handleError(response.message || 'Unbekannter Fehler');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[CBD PDF] REST error:', status, error, 'Response:', xhr.responseText);

                    // Fallback: Try admin-ajax.php
                    console.log('[CBD PDF] Falling back to admin-ajax.php...');
                    sendPDFViaAjax(payload, filename, mode, cssVariables, $overlay);
                }
            });
        } else {
            // No REST URL, use admin-ajax.php directly
            sendPDFViaAjax(payload, filename, mode, cssVariables, $overlay);
        }
    }

    /**
     * Fallback: Send PDF request via admin-ajax.php
     */
    function sendPDFViaAjax(payload, filename, mode, cssVariables, $overlay) {
        $.ajax({
            url: cbdPDFData.ajaxurl,
            type: 'POST',
            timeout: 120000,
            data: {
                action: 'cbd_generate_pdf',
                nonce: cbdPDFData.nonce,
                blocks_json: payload,
                filename: filename,
                mode: mode,
                css_variables: JSON.stringify(cssVariables),
                is_rest_fallback: '1'
            },
            success: function (response) {
                $overlay.remove();

                if (response.success) {
                    console.log('[CBD PDF] PDF generated via AJAX, engine:', response.data.engine);
                    downloadPDF(response.data.url, response.data.filename || filename);
                } else {
                    var errorMsg = response.data ? response.data.message : 'Unbekannter Fehler';
                    console.error('[CBD PDF] AJAX error:', errorMsg);
                    handleError(errorMsg);
                }
            },
            error: function (xhr, status, error) {
                $overlay.remove();
                console.error('[CBD PDF] AJAX error:', status, error, 'Response:', xhr.responseText);

                if (status === 'timeout') {
                    handleError('Zeitüberschreitung - die Seite hat zu viele Inhalte.');
                } else {
                    var serverMsg = '';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        serverMsg = resp.data ? resp.data.message : (resp.message || '');
                    } catch (e) {
                        serverMsg = xhr.responseText ? xhr.responseText.substring(0, 300) : '';
                    }
                    handleError('Serverfehler: ' + (serverMsg || error || status));
                }
            }
        });
    }

    /**
     * Download PDF file
     */
    function downloadPDF(url, filename) {
        var link = document.createElement('a');
        link.href = url;
        link.download = filename;

        if (isIOS) {
            link.target = '_blank';
        }

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Collect current CSS variable values from the page
     */
    function collectCSSVariables() {
        var root = getComputedStyle(document.documentElement);
        return {
            specialText: root.getPropertyValue('--color-special-text').trim() || '#71230a',
            uiSurface: root.getPropertyValue('--color-ui-surface').trim() || '#e24614',
            uiSurfaceDark: root.getPropertyValue('--color-ui-surface-dark').trim() || '#c93d12',
            uiSurfaceLight: root.getPropertyValue('--color-ui-surface-light').trim() || '#f5ede9',
            sidebarBorder: root.getPropertyValue('--color-sidebar-border').trim() || '#e0e0e0',
            primaryText: root.getPropertyValue('--color-primary-text').trim() || '#333333',
            background: root.getPropertyValue('--color-background').trim() || '#ffffff',
            lightBackground: root.getPropertyValue('--color-light-background').trim() || '#f8f9fa'
        };
    }

    // =========================================================================
    // UI: Progress Overlay & Error Handling
    // =========================================================================

    /**
     * Create progress overlay
     */
    function createProgressOverlay(totalBlocks) {
        var $overlay = $('<div id="cbd-pdf-progress" style="' +
            'position:fixed; top:0; left:0; width:100%; height:100%; ' +
            'background:rgba(0,0,0,0.7); z-index:9999999; display:flex; ' +
            'align-items:center; justify-content:center;">' +
            '<div style="background:#fff; padding:30px 40px; border-radius:12px; ' +
            'text-align:center; min-width:300px; box-shadow:0 10px 40px rgba(0,0,0,0.3);">' +
            '<h3 style="margin:0 0 15px 0; font-size:18px;">PDF wird erstellt</h3>' +
            '<div class="cbd-pdf-progress-bar" style="background:#eee; border-radius:8px; ' +
            'height:8px; margin:0 0 12px 0; overflow:hidden;">' +
            '<div class="cbd-pdf-progress-fill" style="background:#e24614; height:100%; ' +
            'width:0%; border-radius:8px; transition:width 0.3s ease;"></div></div>' +
            '<p class="cbd-pdf-progress-text" style="margin:0; color:#666; font-size:14px;">' +
            'Block 1 von ' + totalBlocks + ' wird verarbeitet...</p>' +
            '</div></div>');
        return $overlay;
    }

    /**
     * Update progress display
     */
    function updateProgress($overlay, current, total, message) {
        var pct = Math.round((current / total) * 100);
        $overlay.find('.cbd-pdf-progress-fill').css('width', pct + '%');
        $overlay.find('.cbd-pdf-progress-text').text(message || 'Block ' + current + ' von ' + total + '...');
    }

    /**
     * Handle errors with fallback to window.print()
     */
    function handleError(message) {
        var useprint = confirm(
            'PDF-Erstellung fehlgeschlagen:\n' + message +
            '\n\nMöchten Sie stattdessen die Browser-Druckfunktion verwenden?'
        );
        if (useprint) {
            window.print();
        }
    }

    console.log('[CBD PDF] Server-side PDF export loaded (v3.0, iOS:', isIOS, ')');
})();
