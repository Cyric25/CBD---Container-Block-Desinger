/**
 * jsPDF Loader with multiple CDN fallbacks
 * Ensures jsPDF is always available for the Container Block Designer
 */

(function() {
    'use strict';

    console.log('CBD: jspdf-loader.js starting execution');

    // Check if jsPDF is already loaded
    if (typeof window.jsPDF !== 'undefined' || typeof jsPDF !== 'undefined') {
        console.log('CBD: jsPDF already loaded');
        // Still set up status for existing installation
        window.cbdPDFStatus = {
            loading: false,
            loaded: true,
            error: null,
            attempts: ['SUCCESS: Already loaded']
        };

        // Create export functions immediately for already loaded jsPDF
        setupPDFExportFunctions();
        return;
    }
    
    console.log('CBD: Loading jsPDF with fallback mechanism...');

    // Create global status tracking
    window.cbdPDFStatus = {
        loading: true,
        loaded: false,
        error: null,
        attempts: []
    };
    
    var cdnSources = [
        'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
        'https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js',
        'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js'
    ];
    
    var currentSourceIndex = 0;
    var maxRetries = cdnSources.length;
    
    function loadFromCDN() {
        if (currentSourceIndex >= maxRetries) {
            console.error('CBD: All jsPDF CDN sources failed');
            window.cbdPDFStatus.loading = false;
            window.cbdPDFStatus.loaded = false;
            window.cbdPDFStatus.error = 'All CDN sources failed: ' + window.cbdPDFStatus.attempts.join(', ');

            // Last resort - create a simple alert-based PDF export
            window.cbdPDFExport = function(containerBlocks) {
                var content = 'PDF Export (Text-Only)\n\n';
                var $ = window.jQuery || window.$;
                
                containerBlocks.each(function(index) {
                    var $this = $(this);
                    var title = $this.find('.cbd-block-title').text() || 'Block ' + (index + 1);
                    var text = $this.find('.cbd-container-content').text().trim();
                    content += 'Block ' + (index + 1) + ': ' + title + '\n';
                    content += text + '\n\n';
                });
                
                var blob = new Blob([content], { type: 'text/plain' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.txt';
                link.click();
                console.log('CBD: Text export fallback used');
            };
            return;
        }
        
        var script = document.createElement('script');
        script.src = cdnSources[currentSourceIndex];
        script.async = true;
        
        script.onload = function() {
            console.log('CBD: jsPDF loaded successfully from:', cdnSources[currentSourceIndex]);
            window.cbdPDFStatus.attempts.push('SUCCESS: ' + cdnSources[currentSourceIndex]);

            // Give the script a moment to initialize fully
            setTimeout(function() {
                console.log('CBD: Testing jsPDF initialization after loading...');
                // Verify jsPDF is accessible
                (function() {
                var testPdf = null;
                try {
                    if (window.jsPDF && window.jsPDF.jsPDF) {
                        testPdf = new window.jsPDF.jsPDF();
                    } else if (window.jsPDF) {
                        testPdf = new window.jsPDF();
                    } else if (window.jspdf && window.jspdf.jsPDF) {
                        testPdf = new window.jspdf.jsPDF();
                    } else if (window.jspdf) {
                        testPdf = new window.jspdf();
                    } else if (typeof jsPDF !== 'undefined') {
                        testPdf = new jsPDF();
                    } else if (typeof jspdf !== 'undefined') {
                        testPdf = new jspdf();
                    }

                    if (testPdf) {
                        console.log('CBD: jsPDF instance test successful');
                        window.cbdPDFStatus.loading = false;
                        window.cbdPDFStatus.loaded = true;

                        // Setup export functions
                        setupPDFExportFunctions();
                    } else {
                        throw new Error('Cannot create jsPDF instance');
                    }
                } catch (error) {
                    console.log('CBD: jsPDF test failed:', error.message);
                    window.cbdPDFStatus.attempts.push('FAILED: ' + cdnSources[currentSourceIndex] + ' (test failed: ' + error.message + ')');
                    currentSourceIndex++;
                    loadFromCDN();
                }
            })();
            }, 200); // Give 200ms for script initialization
        };
        
        script.onerror = function() {
            console.log('CBD: Failed to load jsPDF from:', cdnSources[currentSourceIndex]);
            window.cbdPDFStatus.attempts.push('FAILED: ' + cdnSources[currentSourceIndex]);
            currentSourceIndex++;
            loadFromCDN();
        };
        
        console.log('CBD: Trying jsPDF source:', cdnSources[currentSourceIndex]);
        document.head.appendChild(script);
    }
    
    // Function to setup PDF export functions
    function setupPDFExportFunctions() {
        console.log('CBD: Setting up PDF export functions');

        // Create enhanced export function with options
        window.cbdPDFExportWithOptions = function(containerBlocks, mode, quality) {
            return cbdCreatePDF(containerBlocks, mode || 'visual', quality || 1);
        };

        // Create global export function (backward compatibility)
        window.cbdPDFExport = function(containerBlocks) {
            return cbdCreatePDF(containerBlocks, 'visual', 1);
        };

        console.log('CBD: PDF export functions are now available globally');
    }

    // Main PDF creation function
    function cbdCreatePDF(containerBlocks, mode, quality) {
        try {
            var pdf;
            if (window.jsPDF && window.jsPDF.jsPDF) {
                pdf = new window.jsPDF.jsPDF();
            } else if (window.jsPDF) {
                pdf = new window.jsPDF();
            } else if (window.jspdf && window.jspdf.jsPDF) {
                pdf = new window.jspdf.jsPDF();
            } else if (window.jspdf) {
                pdf = new window.jspdf();
            } else if (typeof jsPDF !== 'undefined') {
                pdf = new jsPDF();
            } else {
                pdf = new jspdf();
            }

            pdf.setFontSize(20);
            pdf.text('Container Blöcke Export (' + mode.toUpperCase() + ')', 20, 30);

            pdf.setFontSize(12);
            pdf.text('Exportiert am: ' + new Date().toLocaleDateString('de-DE'), 20, 50);
            pdf.text('Qualität: ' + quality + 'x, Modus: ' + getModeDescription(mode), 20, 60);

            function getModeDescription(mode) {
                switch(mode) {
                    case 'visual': return 'Visuell mit Farben';
                    case 'print': return 'Druck-optimiert';
                    case 'text': return 'Nur Text';
                    default: return 'Standard';
                }
            }

            var y = 80;

            // Use jQuery safely - check if it's available
            var $ = window.jQuery || window.$;
            if (!$ && containerBlocks && containerBlocks.each) {
                // containerBlocks is already a jQuery object
                $ = function(el) { return containerBlocks.constructor(el); };
            }

            console.log('CBD: Processing ' + containerBlocks.length + ' container blocks for PDF');

            // Process containers one by one using html2canvas for images
            var processedBlocks = 0;

            function processNextBlock() {
                if (processedBlocks >= containerBlocks.length) {
                    // All blocks processed, save PDF
                    var filename = 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf';
                    pdf.save(filename);
                    console.log('CBD: PDF saved successfully:', filename);
                    return true;
                }

                var $currentBlock = $(containerBlocks[processedBlocks]);
                var blockTitle = $currentBlock.find('.cbd-block-title').text() || 'Block ' + (processedBlocks + 1);

                console.log('CBD: Processing Block ' + (processedBlocks + 1) + ' - Title: "' + blockTitle + '"');

                // Check if we need a new page for the block title and some content
                var titleHeight = 15;
                var minContentSpace = 50; // Minimum space needed for content after title

                if (y + titleHeight + minContentSpace > 280) {
                    console.log('CBD: Moving to new page to keep title and content together');
                    pdf.addPage();
                    y = 30;
                }

                // Add block title
                pdf.setFontSize(14);
                pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle, 20, y);
                y += titleHeight;

                // Prepare LaTeX formulas for PDF rendering if present
                if (typeof window.cbdPrepareFormulasForPDF === 'function') {
                    console.log('CBD: Preparing LaTeX formulas for PDF');
                    window.cbdPrepareFormulasForPDF($currentBlock[0]);
                }

                // Process based on mode
                if (mode === 'text') {
                    // Text-only mode
                    addTextOnly();
                } else {
                    // Visual or print mode - ALWAYS use visual rendering for these modes
                    if (mode === 'visual' || mode === 'print') {
                        console.log('CBD: Block has visual content, using html2canvas (mode: ' + mode + ')');

                        // Load html2canvas if needed
                        if (typeof html2canvas === 'undefined') {
                            console.log('CBD: html2canvas not found, loading from CDN...');
                            var script = document.createElement('script');
                            script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
                            script.onload = function() {
                                console.log('CBD: html2canvas loaded from CDN, calling renderBlockWithImages()');
                                renderBlockWithImages();
                            };
                            script.onerror = function() {
                                console.error('CBD: Failed to load html2canvas from CDN');
                                addTextOnly(); // Fallback to text
                            };
                            document.head.appendChild(script);
                        } else {
                            console.log('CBD: html2canvas already available, calling renderBlockWithImages()');
                            renderBlockWithImages();
                        }

                        function renderBlockWithImages() {
                            console.log('CBD: renderBlockWithImages called with mode:', mode, 'quality:', quality);

                            // Store original collapsed state and expand content before rendering
                            var collapsedStates = [];
                            expandContentBeforeRendering();

                            function expandContentBeforeRendering() {
                                console.log('CBD: Expanding collapsed content in original DOM before rendering');

                                // Find collapsed content in the current block
                                var blockContent = $currentBlock.find('.cbd-container-content');
                                if (blockContent.length > 0 && !blockContent.is(':visible')) {
                                    console.log('CBD: Found collapsed main content, expanding temporarily');
                                    collapsedStates.push({
                                        element: blockContent[0],
                                        wasHidden: true,
                                        originalDisplay: blockContent[0].style.display,
                                        originalVisibility: blockContent[0].style.visibility
                                    });
                                    blockContent.show();
                                }

                                // Find any other collapsed elements within the block
                                var hiddenElements = $currentBlock.find('[style*="display: none"], [style*="visibility: hidden"]');
                                hiddenElements.each(function() {
                                    // Skip action buttons and controls
                                    if (!$(this).hasClass('cbd-action-buttons') &&
                                        !$(this).hasClass('cbd-action-btn') &&
                                        !$(this).hasClass('dashicons')) {

                                        collapsedStates.push({
                                            element: this,
                                            wasHidden: true,
                                            originalDisplay: this.style.display,
                                            originalVisibility: this.style.visibility
                                        });

                                        $(this).show().css('visibility', 'visible');
                                        console.log('CBD: Temporarily expanded hidden element:', this.className);
                                    }
                                });

                                // Handle details elements
                                var detailsElements = $currentBlock.find('details');
                                detailsElements.each(function() {
                                    if (!this.open) {
                                        collapsedStates.push({
                                            element: this,
                                            wasDetails: true,
                                            originalOpen: false
                                        });
                                        this.open = true;
                                        console.log('CBD: Temporarily opened details element');
                                    }
                                });

                                console.log('CBD: Temporarily expanded', collapsedStates.length, 'elements');
                            }

                            function restoreOriginalStates() {
                                console.log('CBD: Restoring original collapsed states');
                                for (var i = 0; i < collapsedStates.length; i++) {
                                    var state = collapsedStates[i];
                                    if (state.wasDetails) {
                                        state.element.open = state.originalOpen;
                                    } else if (state.wasHidden) {
                                        state.element.style.display = state.originalDisplay;
                                        state.element.style.visibility = state.originalVisibility;
                                    }
                                }
                                console.log('CBD: Restored', collapsedStates.length, 'elements to original state');
                            }

                            var canvasOptions = {
                                useCORS: true,
                                allowTaint: false,
                                scale: quality,
                                logging: false,
                                backgroundColor: 'white',
                                height: null, // Let html2canvas determine optimal height
                                width: null,  // Let html2canvas determine optimal width
                                scrollX: 0,
                                scrollY: 0,
                                windowWidth: $currentBlock[0].scrollWidth,
                                windowHeight: $currentBlock[0].scrollHeight
                            };

                            // Print mode - modify styles for print
                            if (mode === 'print') {
                                console.log('CBD: Applying PRINT mode styling');
                                canvasOptions.onclone = function(clonedDoc) {
                                    console.log('CBD: Applying print-friendly styles and expanding content');

                                    // FIRST: Expand all collapsed content before any other modifications
                                    expandAllCollapsedContent(clonedDoc);

                                    // Fix LaTeX formula positioning for PDF
                                    fixLatexFormulasForPDF(clonedDoc);

                                    // SECOND: Apply grayscale and print optimizations
                                    applyPrintModeStyles(clonedDoc);

                                    // Remove action buttons
                                    var actionButtons = clonedDoc.querySelectorAll('.cbd-action-buttons, .cbd-action-btn, .cbd-collapse-toggle, .cbd-copy-text, .cbd-screenshot, button, .dashicons');
                                    for (var k = actionButtons.length - 1; k >= 0; k--) {
                                        var btn = actionButtons[k];
                                        if (btn && btn.parentNode) {
                                            btn.parentNode.removeChild(btn);
                                        }
                                    }
                                };
                            } else {
                                console.log('CBD: Applying VISUAL mode styling');
                                canvasOptions.onclone = function(clonedDoc) {
                                    console.log('CBD: Expanding content and removing action buttons');

                                    // FIRST: Expand all collapsed content before any other modifications
                                    expandAllCollapsedContent(clonedDoc);

                                    // Fix LaTeX formula positioning for PDF
                                    fixLatexFormulasForPDF(clonedDoc);

                                    // Remove action buttons in visual mode too
                                    var actionButtons = clonedDoc.querySelectorAll('.cbd-action-buttons, .cbd-action-btn, .cbd-collapse-toggle, .cbd-copy-text, .cbd-screenshot, button, .dashicons');
                                    for (var j = actionButtons.length - 1; j >= 0; j--) {
                                        var btn = actionButtons[j];
                                        if (btn && btn.parentNode) {
                                            btn.parentNode.removeChild(btn);
                                        }
                                    }
                                };
                            }

                            // Function to fix LaTeX formula positioning for PDF export
                            function fixLatexFormulasForPDF(doc) {
                                console.log('CBD: Preparing LaTeX formulas for PDF');

                                var formulas = doc.querySelectorAll('.cbd-latex-formula');
                                console.log('CBD: Found', formulas.length, 'LaTeX formulas');

                                // Add minimal CSS for PDF rendering
                                var style = doc.createElement('style');
                                style.textContent = `
                                    /* Only ensure container is centered - don't touch KaTeX internals */
                                    .cbd-latex-formula {
                                        display: block !important;
                                        text-align: center !important;
                                        width: 100% !important;
                                        margin: 20px auto !important;
                                    }
                                `;
                                doc.head.appendChild(style);

                                console.log('CBD: LaTeX formulas ready for PDF (CSS fixes applied)');
                            }

                            // Function to expand all collapsed content
                            function expandAllCollapsedContent(doc) {
                                console.log('CBD: Starting to expand all collapsed content');

                                // Find all container content that might be collapsed
                                var containerContents = doc.querySelectorAll('.cbd-container-content');
                                var expandedCount = 0;

                                for (var i = 0; i < containerContents.length; i++) {
                                    var content = containerContents[i];

                                    // Check if content is hidden/collapsed
                                    var isHidden = content.style.display === 'none' ||
                                                  content.style.visibility === 'hidden' ||
                                                  content.offsetHeight === 0 ||
                                                  doc.defaultView.getComputedStyle(content).display === 'none';

                                    if (isHidden) {
                                        console.log('CBD: Expanding collapsed content block', i + 1);

                                        // Force show the content
                                        content.style.setProperty('display', 'block', 'important');
                                        content.style.setProperty('visibility', 'visible', 'important');
                                        content.style.setProperty('opacity', '1', 'important');
                                        content.style.setProperty('height', 'auto', 'important');
                                        content.style.setProperty('max-height', 'none', 'important');
                                        content.style.setProperty('overflow', 'visible', 'important');

                                        expandedCount++;
                                    }

                                    // Also check for any nested collapsed elements
                                    var nestedHidden = content.querySelectorAll('[style*="display: none"], [style*="visibility: hidden"]');
                                    for (var j = 0; j < nestedHidden.length; j++) {
                                        var hiddenElement = nestedHidden[j];
                                        // Don't expand action buttons or controls
                                        if (!hiddenElement.classList.contains('cbd-action-buttons') &&
                                            !hiddenElement.classList.contains('cbd-action-btn') &&
                                            !hiddenElement.classList.contains('dashicons')) {

                                            hiddenElement.style.setProperty('display', 'block', 'important');
                                            hiddenElement.style.setProperty('visibility', 'visible', 'important');
                                            hiddenElement.style.setProperty('opacity', '1', 'important');
                                            expandedCount++;
                                        }
                                    }
                                }

                                // Find and expand any details/summary elements
                                var detailsElements = doc.querySelectorAll('details');
                                for (var k = 0; k < detailsElements.length; k++) {
                                    if (!detailsElements[k].open) {
                                        console.log('CBD: Opening details element', k + 1);
                                        detailsElements[k].open = true;
                                        expandedCount++;
                                    }
                                }

                                // Find any elements with collapse/expand functionality
                                var collapsibleElements = doc.querySelectorAll('.collapsed, .cbd-collapsed, [data-collapsed="true"]');
                                for (var l = 0; l < collapsibleElements.length; l++) {
                                    var element = collapsibleElements[l];
                                    element.classList.remove('collapsed', 'cbd-collapsed');
                                    element.removeAttribute('data-collapsed');
                                    element.style.setProperty('display', 'block', 'important');
                                    element.style.setProperty('visibility', 'visible', 'important');
                                    expandedCount++;
                                }

                                // ENHANCED: Optimize text rendering for lists and headers
                                optimizeTextRendering(doc);

                                console.log('CBD: Expanded', expandedCount, 'collapsed elements');

                                // Small delay to allow DOM to update
                                return new Promise(function(resolve) {
                                    setTimeout(resolve, 100);
                                });
                            }

                            // Function to optimize text rendering for better PDF output
                            function optimizeTextRendering(doc) {
                                console.log('CBD: Optimizing text rendering for lists and headers');

                                // Ensure all text elements are fully visible
                                var textElements = doc.querySelectorAll('h1, h2, h3, h4, h5, h6, p, li, span, div');

                                for (var i = 0; i < textElements.length; i++) {
                                    var element = textElements[i];

                                    // Force text to be visible and not clipped
                                    element.style.setProperty('overflow', 'visible', 'important');
                                    element.style.setProperty('text-overflow', 'clip', 'important');
                                    element.style.setProperty('white-space', 'normal', 'important');
                                    element.style.setProperty('word-wrap', 'break-word', 'important');
                                    element.style.setProperty('height', 'auto', 'important');
                                    element.style.setProperty('max-height', 'none', 'important');
                                    element.style.setProperty('line-height', 'normal', 'important');
                                }

                                // Special handling for lists
                                var lists = doc.querySelectorAll('ul, ol');
                                for (var j = 0; j < lists.length; j++) {
                                    var list = lists[j];
                                    list.style.setProperty('display', 'block', 'important');
                                    list.style.setProperty('visibility', 'visible', 'important');
                                    list.style.setProperty('height', 'auto', 'important');
                                    list.style.setProperty('overflow', 'visible', 'important');

                                    // Ensure list items are visible
                                    var listItems = list.querySelectorAll('li');
                                    for (var k = 0; k < listItems.length; k++) {
                                        var item = listItems[k];
                                        item.style.setProperty('display', 'list-item', 'important');
                                        item.style.setProperty('visibility', 'visible', 'important');
                                        item.style.setProperty('height', 'auto', 'important');
                                        item.style.setProperty('overflow', 'visible', 'important');
                                        item.style.setProperty('word-wrap', 'break-word', 'important');
                                    }
                                }

                                // Special handling for headers
                                var headers = doc.querySelectorAll('h1, h2, h3, h4, h5, h6');
                                for (var l = 0; l < headers.length; l++) {
                                    var header = headers[l];
                                    header.style.setProperty('display', 'block', 'important');
                                    header.style.setProperty('visibility', 'visible', 'important');
                                    header.style.setProperty('height', 'auto', 'important');
                                    header.style.setProperty('overflow', 'visible', 'important');
                                    header.style.setProperty('word-wrap', 'break-word', 'important');
                                    header.style.setProperty('text-overflow', 'clip', 'important');
                                }

                                // Special handling for tables
                                var tables = doc.querySelectorAll('table');
                                for (var m = 0; m < tables.length; m++) {
                                    var table = tables[m];
                                    table.style.setProperty('display', 'table', 'important');
                                    table.style.setProperty('visibility', 'visible', 'important');
                                    table.style.setProperty('width', 'auto', 'important');
                                    table.style.setProperty('table-layout', 'fixed', 'important');
                                    table.style.setProperty('border-collapse', 'collapse', 'important');
                                    table.style.setProperty('overflow', 'visible', 'important');

                                    // Ensure table cells are visible
                                    var cells = table.querySelectorAll('td, th');
                                    for (var n = 0; n < cells.length; n++) {
                                        var cell = cells[n];
                                        cell.style.setProperty('display', 'table-cell', 'important');
                                        cell.style.setProperty('visibility', 'visible', 'important');
                                        cell.style.setProperty('padding', '4px', 'important');
                                        cell.style.setProperty('border', '1px solid #ddd', 'important');
                                        cell.style.setProperty('word-wrap', 'break-word', 'important');
                                        cell.style.setProperty('overflow', 'visible', 'important');
                                        cell.style.setProperty('height', 'auto', 'important');
                                        cell.style.setProperty('max-height', 'none', 'important');
                                    }

                                    // Ensure table rows are visible
                                    var rows = table.querySelectorAll('tr');
                                    for (var o = 0; o < rows.length; o++) {
                                        var row = rows[o];
                                        row.style.setProperty('display', 'table-row', 'important');
                                        row.style.setProperty('visibility', 'visible', 'important');
                                        row.style.setProperty('height', 'auto', 'important');
                                    }
                                }

                                console.log('CBD: Text rendering optimization completed');
                            }

                            // Function to apply print mode optimizations (grayscale, white backgrounds)
                            function applyPrintModeStyles(doc) {
                                console.log('CBD: Applying print mode grayscale and white background optimizations');

                                // Get all elements in the document
                                var allElements = doc.querySelectorAll('*');
                                var convertedElements = 0;

                                for (var i = 0; i < allElements.length; i++) {
                                    var element = allElements[i];

                                    // Force white backgrounds on all elements
                                    element.style.setProperty('background-color', 'white', 'important');
                                    element.style.setProperty('background', 'white', 'important');
                                    element.style.setProperty('background-image', 'none', 'important');
                                    element.style.setProperty('background-attachment', 'initial', 'important');
                                    element.style.setProperty('background-position', 'initial', 'important');
                                    element.style.setProperty('background-repeat', 'initial', 'important');
                                    element.style.setProperty('background-size', 'initial', 'important');

                                    // Remove all shadows and effects
                                    element.style.setProperty('box-shadow', 'none', 'important');
                                    element.style.setProperty('text-shadow', 'none', 'important');
                                    element.style.setProperty('filter', 'none', 'important');
                                    element.style.setProperty('backdrop-filter', 'none', 'important');
                                    element.style.setProperty('-webkit-filter', 'none', 'important');
                                    element.style.setProperty('-webkit-backdrop-filter', 'none', 'important');

                                    // Convert text colors to grayscale
                                    var computedStyle = doc.defaultView.getComputedStyle(element);
                                    var textColor = computedStyle.color;

                                    if (textColor && textColor !== 'rgba(0, 0, 0, 0)' && textColor !== 'transparent') {
                                        var grayscaleColor = convertToGrayscale(textColor);
                                        if (grayscaleColor) {
                                            element.style.setProperty('color', grayscaleColor, 'important');
                                            convertedElements++;
                                        }
                                    }

                                    // Convert border colors to grayscale
                                    var borderColor = computedStyle.borderColor;
                                    if (borderColor && borderColor !== 'rgba(0, 0, 0, 0)' && borderColor !== 'transparent') {
                                        var grayscaleBorder = convertToGrayscale(borderColor);
                                        if (grayscaleBorder) {
                                            element.style.setProperty('border-color', grayscaleBorder, 'important');
                                        }
                                    }

                                    // Special handling for specific border properties
                                    ['border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color'].forEach(function(prop) {
                                        var color = computedStyle.getPropertyValue(prop);
                                        if (color && color !== 'rgba(0, 0, 0, 0)' && color !== 'transparent') {
                                            var grayscaleColor = convertToGrayscale(color);
                                            if (grayscaleColor) {
                                                element.style.setProperty(prop, grayscaleColor, 'important');
                                            }
                                        }
                                    });

                                    // Remove all outline colors and force them to grayscale if present
                                    element.style.setProperty('outline', 'none', 'important');
                                    element.style.setProperty('outline-width', '0', 'important');
                                    element.style.setProperty('outline-style', 'none', 'important');
                                    element.style.setProperty('outline-color', 'transparent', 'important');
                                }

                                // Special handling for images - apply grayscale filter
                                var images = doc.querySelectorAll('img');
                                for (var j = 0; j < images.length; j++) {
                                    var img = images[j];
                                    img.style.setProperty('filter', 'grayscale(100%)', 'important');
                                    img.style.setProperty('-webkit-filter', 'grayscale(100%)', 'important');
                                }

                                // Special handling for tables in print mode
                                var tables = doc.querySelectorAll('table');
                                for (var k = 0; k < tables.length; k++) {
                                    var table = tables[k];
                                    table.style.setProperty('background-color', 'white', 'important');
                                    table.style.setProperty('border-collapse', 'collapse', 'important');

                                    // Make table cells print-friendly
                                    var cells = table.querySelectorAll('td, th');
                                    for (var l = 0; l < cells.length; l++) {
                                        var cell = cells[l];
                                        cell.style.setProperty('background-color', 'white', 'important');
                                        cell.style.setProperty('border', '1px solid #666', 'important');
                                        cell.style.setProperty('color', '#000000', 'important');
                                    }
                                }

                                // Apply global grayscale filter to the entire document
                                var body = doc.body || doc.documentElement;
                                if (body) {
                                    body.style.setProperty('filter', 'grayscale(100%) contrast(120%)', 'important');
                                    body.style.setProperty('-webkit-filter', 'grayscale(100%) contrast(120%)', 'important');
                                    body.style.setProperty('background-color', 'white', 'important');
                                }

                                console.log('CBD: Print mode applied - converted', convertedElements, 'text colors to grayscale');
                            }

                            // Function to convert color values to grayscale
                            function convertToGrayscale(colorString) {
                                if (!colorString || colorString === 'transparent' || colorString === 'inherit') {
                                    return null;
                                }

                                // Handle rgba/rgb colors
                                var rgbaMatch = colorString.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
                                if (rgbaMatch) {
                                    var r = parseInt(rgbaMatch[1]);
                                    var g = parseInt(rgbaMatch[2]);
                                    var b = parseInt(rgbaMatch[3]);
                                    var a = rgbaMatch[4] ? parseFloat(rgbaMatch[4]) : 1;

                                    // Convert to grayscale using luminance formula
                                    var gray = Math.round(0.299 * r + 0.587 * g + 0.114 * b);

                                    // Ensure good contrast for text readability
                                    if (gray > 128) {
                                        gray = Math.min(gray, 200); // Lighter grays
                                    } else {
                                        gray = Math.max(gray, 60);  // Darker grays
                                    }

                                    return a < 1 ? 'rgba(' + gray + ',' + gray + ',' + gray + ',' + a + ')' : 'rgb(' + gray + ',' + gray + ',' + gray + ')';
                                }

                                // Handle hex colors
                                var hexMatch = colorString.match(/^#([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i);
                                if (hexMatch) {
                                    var r = parseInt(hexMatch[1], 16);
                                    var g = parseInt(hexMatch[2], 16);
                                    var b = parseInt(hexMatch[3], 16);

                                    var gray = Math.round(0.299 * r + 0.587 * g + 0.114 * b);

                                    // Ensure good contrast
                                    if (gray > 128) {
                                        gray = Math.min(gray, 200);
                                    } else {
                                        gray = Math.max(gray, 60);
                                    }

                                    var grayHex = gray.toString(16).padStart(2, '0');
                                    return '#' + grayHex + grayHex + grayHex;
                                }

                                // Handle named colors by converting them to black/gray for print
                                var namedColors = {
                                    'red': '#666666',
                                    'blue': '#555555',
                                    'green': '#777777',
                                    'yellow': '#cccccc',
                                    'orange': '#999999',
                                    'purple': '#444444',
                                    'pink': '#aaaaaa',
                                    'cyan': '#888888',
                                    'magenta': '#666666',
                                    'brown': '#333333',
                                    'black': '#000000',
                                    'white': '#ffffff',
                                    'gray': '#808080',
                                    'grey': '#808080'
                                };

                                var lowerColor = colorString.toLowerCase();
                                if (namedColors[lowerColor]) {
                                    return namedColors[lowerColor];
                                }

                                // Default fallback
                                return '#000000';
                            }

                            console.log('CBD: Starting html2canvas...');

                            try {
                                html2canvas($currentBlock[0], canvasOptions).then(function(canvas) {
                                    console.log('CBD: html2canvas successful - Canvas size:', canvas.width + 'x' + canvas.height);

                                    // Restore original collapsed states after rendering
                                    restoreOriginalStates();

                                    var imageFormat = 'JPEG';
                                    var imageQuality = mode === 'print' ? 0.9 : 0.8;
                                    var imgData = canvas.toDataURL('image/jpeg', imageQuality);

                                    // PDF page dimensions (A4 with margins)
                                    var pageHeight = 280; // A4 page height minus margins
                                    var maxPageWidth = 170; // Maximum width for images
                                    var maxSinglePageHeight = pageHeight - 50; // Leave space for headers

                                    // Calculate optimal image dimensions with proper scaling
                                    var originalWidth = canvas.width;
                                    var originalHeight = canvas.height;
                                    var aspectRatio = originalWidth / originalHeight;

                                    // Calculate scale factors for both width and height constraints
                                    var widthScale = maxPageWidth / originalWidth;
                                    var heightScale = maxSinglePageHeight / originalHeight;

                                    // Use the smaller scale factor to ensure image fits within both constraints
                                    var scale = Math.min(widthScale, heightScale, 1.0); // Never scale up

                                    var imgWidth = originalWidth * scale;
                                    var imgHeight = originalHeight * scale;

                                    // Ensure minimum readable size while maintaining aspect ratio
                                    var minWidth = 100;
                                    var minHeight = 50;

                                    if (imgWidth < minWidth || imgHeight < minHeight) {
                                        var minScale = Math.max(minWidth / originalWidth, minHeight / originalHeight);
                                        if (minScale < scale || scale < 0.1) {
                                            imgWidth = originalWidth * minScale;
                                            imgHeight = originalHeight * minScale;
                                            console.log('CBD: Applied minimum size scaling - factor:', minScale);
                                        }
                                    }

                                    console.log('CBD: Original canvas:', originalWidth + 'x' + originalHeight);
                                    console.log('CBD: Scaled image dimensions - Width:', imgWidth, 'Height:', imgHeight, 'Current Y:', y);

                                    // Intelligent page splitting for large blocks
                                    var availableHeight = pageHeight - y;

                                    if (imgHeight > maxSinglePageHeight) {
                                        // Block is too large for a single page - split it intelligently
                                        console.log('CBD: Block too large (' + imgHeight + 'px), splitting into multiple pages');
                                        splitLargeBlockAcrossPages(canvas, imgData, imgWidth, imgHeight, imageFormat, imageQuality);
                                    } else if (imgHeight > availableHeight) {
                                        // Block doesn't fit on current page but fits on a new page
                                        console.log('CBD: Block doesn\'t fit on current page, moving to new page');
                                        pdf.addPage();
                                        y = 30;
                                        pdf.addImage(imgData, imageFormat, 20, y, imgWidth, imgHeight);
                                        y += imgHeight + 10;
                                    } else {
                                        // Block fits on current page
                                        console.log('CBD: Block fits on current page');
                                        pdf.addImage(imgData, imageFormat, 20, y, imgWidth, imgHeight);
                                        y += imgHeight + 10;
                                    }

                                    processedBlocks++;
                                    processNextBlock();

                                    function splitLargeBlockAcrossPages(canvas, imgData, imgWidth, imgHeight, imageFormat, imageQuality) {
                                        console.log('CBD: Starting intelligent block splitting');

                                        var pageHeight = 280;
                                        var headerSpace = 30;
                                        var usablePageHeight = pageHeight - headerSpace;
                                        var minSegmentHeight = 50; // Minimum height for a segment to avoid tiny pieces

                                        // Calculate optimal page distribution to avoid tiny segments
                                        var totalPages = Math.ceil(imgHeight / usablePageHeight);
                                        var adjustedSegmentHeight = imgHeight / totalPages;

                                        // If the last segment would be too small, redistribute
                                        if (totalPages > 1 && (imgHeight % usablePageHeight) < minSegmentHeight) {
                                            adjustedSegmentHeight = imgHeight / (totalPages - 1);
                                            if (adjustedSegmentHeight <= usablePageHeight * 1.1) {
                                                totalPages = totalPages - 1;
                                                usablePageHeight = adjustedSegmentHeight;
                                                console.log('CBD: Redistributed to avoid tiny segments - new segment height:', usablePageHeight);
                                            }
                                        }

                                        console.log('CBD: Will split block across', totalPages, 'pages with segment height:', usablePageHeight);

                                        // Start on a new page for large blocks
                                        if (y > headerSpace) {
                                            pdf.addPage();
                                            y = headerSpace;
                                        }

                                        // Split the image into segments
                                        for (var pageIndex = 0; pageIndex < totalPages; pageIndex++) {
                                            var segmentStartY = pageIndex * usablePageHeight;
                                            var segmentHeight = Math.min(usablePageHeight, imgHeight - segmentStartY);

                                            console.log('CBD: Processing page', pageIndex + 1, '- segment height:', segmentHeight);

                                            // Create a new canvas for this segment
                                            var segmentCanvas = document.createElement('canvas');
                                            segmentCanvas.width = canvas.width;
                                            segmentCanvas.height = (segmentHeight * canvas.width) / imgWidth;

                                            var segmentCtx = segmentCanvas.getContext('2d');

                                            // Draw the segment from the original canvas
                                            var sourceY = (segmentStartY * canvas.width) / imgWidth;
                                            var sourceHeight = segmentCanvas.height;

                                            segmentCtx.drawImage(
                                                canvas,
                                                0, sourceY, canvas.width, sourceHeight,
                                                0, 0, segmentCanvas.width, segmentCanvas.height
                                            );

                                            // Convert segment to image data
                                            var segmentImgData = segmentCanvas.toDataURL('image/jpeg', imageQuality);

                                            // Add segment to PDF
                                            if (pageIndex > 0) {
                                                pdf.addPage();
                                                y = headerSpace;

                                                // Add block title on new page for continuation
                                                pdf.setFontSize(14);
                                                pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle + ' (Teil ' + (pageIndex + 1) + ')', 20, y);
                                                y += 15;
                                            }

                                            pdf.addImage(segmentImgData, imageFormat, 20, y, imgWidth, segmentHeight);

                                            // Add continuation indicator for split blocks
                                            if (pageIndex > 0) {
                                                pdf.setFontSize(8);
                                                pdf.setTextColor(128, 128, 128); // Gray text
                                                pdf.text('(Fortsetzung von vorheriger Seite)', 20, y - 5);
                                                pdf.setTextColor(0, 0, 0); // Reset to black
                                            }

                                            if (pageIndex < totalPages - 1) {
                                                pdf.setFontSize(8);
                                                pdf.setTextColor(128, 128, 128);
                                                pdf.text('(Fortsetzung auf nächster Seite)', 20, y + segmentHeight + 5);
                                                pdf.setTextColor(0, 0, 0);
                                            }

                                            y += segmentHeight + 10;

                                            console.log('CBD: Added segment', pageIndex + 1, 'at Y position:', y - segmentHeight - 10);
                                        }

                                        console.log('CBD: Block splitting completed across', totalPages, 'pages');
                                    }

                                }).catch(function(error) {
                                    console.error('CBD: html2canvas failed for block ' + (processedBlocks + 1) + ':', error);

                                    // Restore original states even on error
                                    restoreOriginalStates();

                                    console.log('CBD: Falling back to text-only for this block');
                                    addTextOnly();
                                });
                            } catch (syncError) {
                                console.error('CBD: html2canvas synchronous error:', syncError);

                                // Restore original states even on error
                                restoreOriginalStates();

                                console.log('CBD: Falling back to text-only due to sync error');
                                addTextOnly();
                            }
                        }
                    } else {
                        addTextOnly();
                    }
                }

                function addTextOnly() {
                    console.log('CBD: addTextOnly called for block', processedBlocks + 1);

                    // Enhanced text extraction that preserves structure
                    var structuredContent = extractStructuredContent($currentBlock.find('.cbd-container-content'));
                    console.log('CBD: Extracted', structuredContent.length, 'content elements');

                    var pageHeight = 280;
                    var pageIndex = 0;

                    function checkNewPage(requiredHeight) {
                        if (y + requiredHeight > pageHeight) {
                            pageIndex++;
                            console.log('CBD: Starting new page for structured content at page', pageIndex + 1);
                            pdf.addPage();
                            y = 30;

                            // Add block title on new page for continuation
                            if (pageIndex > 0) {
                                pdf.setFontSize(14);
                                pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle + ' (Teil ' + (pageIndex + 1) + ')', 20, y);
                                y += 15;

                                // Add continuation indicator
                                pdf.setFontSize(8);
                                pdf.setTextColor(128, 128, 128);
                                pdf.text('(Fortsetzung von vorheriger Seite)', 20, y);
                                pdf.setTextColor(0, 0, 0);
                                y += 10;
                            }
                        }
                    }

                    // Process each structured element
                    for (var i = 0; i < structuredContent.length; i++) {
                        var element = structuredContent[i];

                        switch (element.type) {
                            case 'header':
                                checkNewPage(element.fontSize + 5); // Extra space for headers
                                pdf.setFontSize(element.fontSize);
                                pdf.setFont(undefined, 'bold');
                                pdf.text(element.text, 20, y);
                                y += element.fontSize + 3;
                                pdf.setFont(undefined, 'normal');
                                break;

                            case 'list':
                                checkNewPage(15); // Minimum space for list start
                                addListToPDF(element);
                                break;

                            case 'table':
                                addTableToPDF(element);
                                break;

                            case 'paragraph':
                                addParagraphToPDF(element);
                                break;

                            default:
                                addParagraphToPDF(element);
                                break;
                        }
                    }

                    function extractStructuredContent($content) {
                        var elements = [];

                        // Process child elements to maintain structure
                        $content.children().each(function() {
                            var $this = $(this);
                            var tagName = this.tagName.toLowerCase();

                            if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(tagName)) {
                                // Header elements
                                var level = parseInt(tagName.charAt(1));
                                var fontSize = Math.max(16 - level * 2, 10);
                                elements.push({
                                    type: 'header',
                                    text: $this.text().trim(),
                                    fontSize: fontSize,
                                    level: level
                                });
                            } else if (['ul', 'ol'].includes(tagName)) {
                                // List elements
                                var listItems = [];
                                $this.find('li').each(function() {
                                    listItems.push($(this).text().trim());
                                });
                                elements.push({
                                    type: 'list',
                                    ordered: tagName === 'ol',
                                    items: listItems
                                });
                            } else if (tagName === 'table') {
                                // Table elements
                                var tableData = extractTableData($this);
                                if (tableData.rows.length > 0) {
                                    elements.push({
                                        type: 'table',
                                        headers: tableData.headers,
                                        rows: tableData.rows,
                                        hasHeaders: tableData.hasHeaders
                                    });
                                }
                            } else {
                                // Regular content
                                var text = $this.text().trim();
                                if (text.length > 0) {
                                    elements.push({
                                        type: 'paragraph',
                                        text: text
                                    });
                                }
                            }
                        });

                        // If no structured elements found, use the entire text as one paragraph
                        if (elements.length === 0) {
                            var fullText = $content.text().trim();
                            if (fullText.length > 0) {
                                elements.push({
                                    type: 'paragraph',
                                    text: fullText
                                });
                            }
                        }

                        return elements;
                    }

                    function extractTableData($table) {
                        var tableData = {
                            headers: [],
                            rows: [],
                            hasHeaders: false
                        };

                        // Check for table headers
                        var $headers = $table.find('thead tr th, tr:first-child th');
                        if ($headers.length > 0) {
                            tableData.hasHeaders = true;
                            $headers.each(function() {
                                tableData.headers.push($(this).text().trim());
                            });
                        }

                        // Extract table rows
                        var $rows = tableData.hasHeaders ?
                            $table.find('tbody tr, tr:not(:first-child)') :
                            $table.find('tr');

                        $rows.each(function() {
                            var row = [];
                            $(this).find('td, th').each(function() {
                                row.push($(this).text().trim());
                            });
                            if (row.length > 0) {
                                tableData.rows.push(row);
                            }
                        });

                        return tableData;
                    }

                    function addTableToPDF(tableElement) {
                        console.log('CBD: Adding table with', tableElement.rows.length, 'rows');

                        var colCount = Math.max(
                            tableElement.headers.length,
                            tableElement.rows.length > 0 ? tableElement.rows[0].length : 0
                        );

                        if (colCount === 0) return;

                        var tableWidth = 170;
                        var colWidth = tableWidth / colCount;
                        var rowHeight = 12;
                        var headerHeight = 15;

                        // Calculate total table height
                        var totalTableHeight = (tableElement.hasHeaders ? headerHeight : 0) +
                                             (tableElement.rows.length * rowHeight) +
                                             10; // Extra spacing

                        // Check if entire table fits on current page
                        if (y + totalTableHeight > pageHeight) {
                            // Table doesn't fit, check if it fits on a new page
                            if (totalTableHeight < pageHeight - 50) {
                                // Start new page for entire table
                                pageIndex++;
                                console.log('CBD: Moving entire table to new page');
                                pdf.addPage();
                                y = 30;

                                if (pageIndex > 0) {
                                    pdf.setFontSize(14);
                                    pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle + ' (Teil ' + (pageIndex + 1) + ')', 20, y);
                                    y += 15;
                                    pdf.setFontSize(8);
                                    pdf.setTextColor(128, 128, 128);
                                    pdf.text('(Fortsetzung von vorheriger Seite)', 20, y);
                                    pdf.setTextColor(0, 0, 0);
                                    y += 10;
                                }
                            } else {
                                // Table is too large, needs to be split intelligently
                                addLargeTableToPDF(tableElement, colWidth, rowHeight, headerHeight);
                                return;
                            }
                        }

                        // Add table headers if present
                        if (tableElement.hasHeaders) {
                            pdf.setFontSize(9);
                            pdf.setFont(undefined, 'bold');

                            for (var i = 0; i < tableElement.headers.length; i++) {
                                var cellX = 20 + (i * colWidth);
                                var cellText = tableElement.headers[i];
                                var lines = pdf.splitTextToSize(cellText, colWidth - 2);

                                // Draw header cell border
                                pdf.rect(cellX, y, colWidth, headerHeight);

                                // Add header text
                                for (var lineIndex = 0; lineIndex < Math.min(lines.length, 2); lineIndex++) {
                                    pdf.text(lines[lineIndex], cellX + 1, y + 7 + (lineIndex * 5));
                                }
                            }
                            y += headerHeight;
                            pdf.setFont(undefined, 'normal');
                        }

                        // Add table rows
                        pdf.setFontSize(8);
                        for (var rowIndex = 0; rowIndex < tableElement.rows.length; rowIndex++) {
                            var row = tableElement.rows[rowIndex];

                            for (var colIndex = 0; colIndex < Math.min(row.length, colCount); colIndex++) {
                                var cellX = 20 + (colIndex * colWidth);
                                var cellText = row[colIndex] || '';
                                var lines = pdf.splitTextToSize(cellText, colWidth - 2);

                                // Draw cell border
                                pdf.rect(cellX, y, colWidth, rowHeight);

                                // Add cell text
                                for (var lineIndex = 0; lineIndex < Math.min(lines.length, 2); lineIndex++) {
                                    pdf.text(lines[lineIndex], cellX + 1, y + 6 + (lineIndex * 4));
                                }
                            }
                            y += rowHeight;
                        }

                        y += 10; // Extra spacing after table
                    }

                    function addLargeTableToPDF(tableElement, colWidth, rowHeight, headerHeight) {
                        console.log('CBD: Splitting large table across multiple pages');

                        var colCount = Math.max(tableElement.headers.length,
                                              tableElement.rows.length > 0 ? tableElement.rows[0].length : 0);
                        var pageRowCapacity = Math.floor((pageHeight - 80) / rowHeight); // Reserve space for headers

                        // Start on new page for large tables
                        if (y > 50) {
                            pageIndex++;
                            pdf.addPage();
                            y = 30;

                            if (pageIndex > 0) {
                                pdf.setFontSize(14);
                                pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle + ' (Teil ' + (pageIndex + 1) + ')', 20, y);
                                y += 15;
                            }
                        }

                        var rowsProcessed = 0;
                        while (rowsProcessed < tableElement.rows.length) {
                            // Add headers on each page
                            if (tableElement.hasHeaders) {
                                pdf.setFontSize(9);
                                pdf.setFont(undefined, 'bold');

                                for (var i = 0; i < tableElement.headers.length; i++) {
                                    var cellX = 20 + (i * colWidth);
                                    var cellText = tableElement.headers[i];
                                    var lines = pdf.splitTextToSize(cellText, colWidth - 2);

                                    pdf.rect(cellX, y, colWidth, headerHeight);
                                    for (var lineIndex = 0; lineIndex < Math.min(lines.length, 2); lineIndex++) {
                                        pdf.text(lines[lineIndex], cellX + 1, y + 7 + (lineIndex * 5));
                                    }
                                }
                                y += headerHeight;
                                pdf.setFont(undefined, 'normal');
                            }

                            // Calculate how many rows fit on this page
                            var remainingSpace = pageHeight - y - 20;
                            var rowsThisPage = Math.min(
                                Math.floor(remainingSpace / rowHeight),
                                tableElement.rows.length - rowsProcessed
                            );

                            // Add rows for this page
                            pdf.setFontSize(8);
                            for (var i = 0; i < rowsThisPage; i++) {
                                var row = tableElement.rows[rowsProcessed + i];

                                for (var colIndex = 0; colIndex < Math.min(row.length, colCount); colIndex++) {
                                    var cellX = 20 + (colIndex * colWidth);
                                    var cellText = row[colIndex] || '';
                                    var lines = pdf.splitTextToSize(cellText, colWidth - 2);

                                    pdf.rect(cellX, y, colWidth, rowHeight);
                                    for (var lineIndex = 0; lineIndex < Math.min(lines.length, 2); lineIndex++) {
                                        pdf.text(lines[lineIndex], cellX + 1, y + 6 + (lineIndex * 4));
                                    }
                                }
                                y += rowHeight;
                            }

                            rowsProcessed += rowsThisPage;

                            // Start new page if more rows remaining
                            if (rowsProcessed < tableElement.rows.length) {
                                pageIndex++;
                                pdf.addPage();
                                y = 30;

                                pdf.setFontSize(14);
                                pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle + ' (Tabelle Teil ' + (pageIndex + 1) + ')', 20, y);
                                y += 15;
                                pdf.setFontSize(8);
                                pdf.setTextColor(128, 128, 128);
                                pdf.text('(Tabellenfortsetzung)', 20, y);
                                pdf.setTextColor(0, 0, 0);
                                y += 10;
                            }
                        }

                        y += 10; // Extra spacing after table
                    }

                    function addListToPDF(listElement) {
                        console.log('CBD: Adding list with', listElement.items.length, 'items');
                        pdf.setFontSize(10);

                        for (var i = 0; i < listElement.items.length; i++) {
                            var item = listElement.items[i];
                            var prefix = listElement.ordered ? (i + 1) + '. ' : '• ';
                            var fullItem = prefix + item;

                            // Split long list items
                            var lines = pdf.splitTextToSize(fullItem, 160); // Slightly less width for indentation

                            for (var j = 0; j < lines.length; j++) {
                                checkNewPage(8);
                                var xPos = j === 0 ? 25 : 35; // Indent continuation lines more
                                pdf.text(lines[j], xPos, y);
                                y += 6;
                            }
                            y += 2; // Small gap between list items
                        }
                        y += 5; // Gap after list
                    }

                    function addParagraphToPDF(paragraphElement) {
                        if (!paragraphElement.text || paragraphElement.text.length === 0) return;

                        console.log('CBD: Adding paragraph, length:', paragraphElement.text.length);
                        pdf.setFontSize(10);
                        var lines = pdf.splitTextToSize(paragraphElement.text, 170);

                        for (var i = 0; i < lines.length; i++) {
                            checkNewPage(8);
                            pdf.text(lines[i], 20, y);
                            y += 6;
                        }
                        y += 8; // Gap after paragraph
                    }

                    y += 10; // Extra spacing after block

                    processedBlocks++;
                    processNextBlock();
                }
            }

            // Start processing
            processNextBlock();

        } catch (error) {
            console.error('CBD: PDF generation failed:', error);
            alert('Fehler beim PDF erstellen: ' + error.message);
            return false;
        }
    }

    // Start loading
    loadFromCDN();
})();