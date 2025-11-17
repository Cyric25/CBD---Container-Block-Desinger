/**
 * jsPDF Loader with multiple CDN fallbacks
 * Ensures jsPDF is always available for the Container Block Designer
 */

(function() {
    'use strict';


    // Check if jsPDF is already loaded
    if (typeof window.jsPDF !== 'undefined' || typeof jsPDF !== 'undefined') {
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
            };
            return;
        }
        
        var script = document.createElement('script');
        script.src = cdnSources[currentSourceIndex];
        script.async = true;
        
        script.onload = function() {
            window.cbdPDFStatus.attempts.push('SUCCESS: ' + cdnSources[currentSourceIndex]);

            // Give the script a moment to initialize fully
            setTimeout(function() {
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
                        window.cbdPDFStatus.loading = false;
                        window.cbdPDFStatus.loaded = true;

                        // Setup export functions
                        setupPDFExportFunctions();
                    } else {
                        throw new Error('Cannot create jsPDF instance');
                    }
                } catch (error) {
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
        
        document.head.appendChild(script);
    }
    
    // Function to setup PDF export functions
    function setupPDFExportFunctions() {

        // Create enhanced export function with options
        window.cbdPDFExportWithOptions = function(containerBlocks, mode, quality) {
            return cbdCreatePDF(containerBlocks, mode || 'visual', quality || 1);
        };

        // Create global export function (backward compatibility)
        window.cbdPDFExport = function(containerBlocks) {
            return cbdCreatePDF(containerBlocks, 'visual', 1);
        };

    }

    // Main PDF creation function
    function cbdCreatePDF(containerBlocks, mode, quality) {
        try {
            // Initialize first page - will be replaced with first block
            var pdf = null;
            var firstBlock = true;

            // Use jQuery safely - check if it's available
            var $ = window.jQuery || window.$;
            if (!$ && containerBlocks && containerBlocks.each) {
                // containerBlocks is already a jQuery object
                $ = function(el) { return containerBlocks.constructor(el); };
            }

            // Process containers one by one using the SIMPLE method (like single block export)
            var processedBlocks = 0;

            function processNextBlock() {
                if (processedBlocks >= containerBlocks.length) {
                    // All blocks processed, save PDF
                    if (pdf) {
                        var filename = 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf';
                        pdf.save(filename);
                    }
                    return true;
                }

                var $currentBlock = $(containerBlocks[processedBlocks]);
                var blockTitle = $currentBlock.find('.cbd-block-title').text() || 'Block ' + (processedBlocks + 1);

                // Use SIMPLE method like single block export (no complex scaling)
                // Load html2canvas if needed
                if (typeof html2canvas === 'undefined') {
                    var script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
                    script.onload = function() {
                        renderBlockSimple();
                    };
                    script.onerror = function() {
                        console.error('CBD: Failed to load html2canvas from CDN');
                        processedBlocks++;
                        processNextBlock();
                    };
                    document.head.appendChild(script);
                } else {
                    renderBlockSimple();
                }

                function renderBlockSimple() {
                    // SIMPLE method like single block export - no complex A4 scaling!

                    // Find the actual container block element
                    var containerBlock = $currentBlock.find('.cbd-container-block')[0] || $currentBlock[0];

                    // Store original collapsed state and expand ALL content before rendering
                    var collapsedStates = [];
                    expandAllContentBeforeRendering();

                    function expandAllContentBeforeRendering() {
                        // CRITICAL: Find ALL container blocks (including nested ones!)
                        var allContainerBlocks = $currentBlock.find('[data-wp-interactive="container-block-designer"]');

                        // If current block itself is an interactive container, include it
                        if ($currentBlock.is('[data-wp-interactive="container-block-designer"]')) {
                            allContainerBlocks = allContainerBlocks.add($currentBlock);
                        }

                        // Expand EACH container block (including nested ones)
                        allContainerBlocks.each(function() {
                            var $container = $(this);
                            var content = $container.find('.cbd-container-content').first();

                            // Check if this specific container's content is collapsed
                            if (content.length > 0) {
                                var computedDisplay = window.getComputedStyle(content[0]).display;
                                var isHidden = computedDisplay === 'none' ||
                                             !content.is(':visible') ||
                                             content.css('display') === 'none';

                                if (isHidden) {
                                    collapsedStates.push({
                                        element: content[0],
                                        wasHidden: true,
                                        originalDisplay: content[0].style.display,
                                        originalVisibility: content[0].style.visibility,
                                        originalMaxHeight: content[0].style.maxHeight,
                                        originalOverflow: content[0].style.overflow
                                    });

                                    // Force expand with !important to override all styles
                                    content[0].style.setProperty('display', 'block', 'important');
                                    content[0].style.setProperty('visibility', 'visible', 'important');
                                    content[0].style.setProperty('opacity', '1', 'important');
                                    content[0].style.setProperty('max-height', 'none', 'important');
                                    content[0].style.setProperty('overflow', 'visible', 'important');
                                    content[0].style.setProperty('height', 'auto', 'important');
                                }
                            }

                            // Also expand collapse toggle if exists
                            var collapseToggle = $container.find('.cbd-collapse-toggle').first();
                            if (collapseToggle.length > 0) {
                                var icon = collapseToggle.find('.dashicons');
                                if (icon.hasClass('dashicons-arrow-down-alt2')) {
                                    // Was collapsed, store state
                                    collapsedStates.push({
                                        element: icon[0],
                                        wasCollapsedIcon: true
                                    });
                                }
                            }
                        });

                        // Find any other hidden elements (not action buttons)
                        var hiddenElements = $currentBlock.find('[style*="display: none"], [style*="visibility: hidden"]');
                        hiddenElements.each(function() {
                            var $elem = $(this);
                            // Skip action buttons, controls, and already processed content
                            if (!$elem.hasClass('cbd-action-buttons') &&
                                !$elem.hasClass('cbd-action-btn') &&
                                !$elem.hasClass('dashicons') &&
                                !$elem.hasClass('cbd-container-content')) {

                                collapsedStates.push({
                                    element: this,
                                    wasHidden: true,
                                    originalDisplay: this.style.display,
                                    originalVisibility: this.style.visibility
                                });

                                this.style.setProperty('display', 'block', 'important');
                                this.style.setProperty('visibility', 'visible', 'important');
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
                            }
                        });
                    }

                    function restoreOriginalStates() {
                        for (var i = 0; i < collapsedStates.length; i++) {
                            var state = collapsedStates[i];
                            if (state.wasDetails) {
                                state.element.open = state.originalOpen;
                            } else if (state.wasCollapsedIcon) {
                                // Icon state restoration not needed, will be handled by next toggle
                            } else if (state.wasHidden) {
                                // Remove important flags and restore original values
                                state.element.style.removeProperty('display');
                                state.element.style.removeProperty('visibility');
                                state.element.style.removeProperty('opacity');
                                state.element.style.removeProperty('max-height');
                                state.element.style.removeProperty('overflow');
                                state.element.style.removeProperty('height');

                                // Restore original inline styles
                                state.element.style.display = state.originalDisplay || '';
                                state.element.style.visibility = state.originalVisibility || '';
                                if (state.originalMaxHeight !== undefined) {
                                    state.element.style.maxHeight = state.originalMaxHeight || '';
                                }
                                if (state.originalOverflow !== undefined) {
                                    state.element.style.overflow = state.originalOverflow || '';
                                }
                            }
                        }
                    }

                    // Hide action buttons temporarily
                    var actionButtons = $currentBlock.find('.cbd-action-buttons');
                    var originalVisibility = '';
                    if (actionButtons.length > 0) {
                        originalVisibility = actionButtons.css('visibility') || '';
                        actionButtons.css({
                            'visibility': 'hidden',
                            'opacity': '0'
                        });
                    }

                    // Wait for DOM to update after expansion (350ms for collapse animation)
                    setTimeout(function() {
                        // Create canvas with SIMPLE options (like single block export)
                        html2canvas(containerBlock, {
                            useCORS: true,
                            allowTaint: false,
                            scale: quality || 2,
                            logging: false,
                            backgroundColor: null
                        }).then(function(canvas) {

                            // Restore button visibility
                            if (actionButtons.length > 0) {
                                actionButtons.css({
                                    'visibility': originalVisibility,
                                    'opacity': '1'
                                });
                            }

                            // Restore ALL original collapsed states
                            restoreOriginalStates();

                            // Create or add to PDF with EXACT canvas dimensions (NO A4 scaling!)
                            var imgData = canvas.toDataURL('image/png');

                            if (firstBlock) {
                                // First block - create PDF with canvas dimensions
                                if (window.jsPDF && window.jsPDF.jsPDF) {
                                    pdf = new window.jsPDF.jsPDF({
                                        orientation: canvas.width > canvas.height ? 'landscape' : 'portrait',
                                        unit: 'px',
                                        format: [canvas.width, canvas.height]
                                    });
                                } else if (window.jspdf && window.jspdf.jsPDF) {
                                    pdf = new window.jspdf.jsPDF({
                                        orientation: canvas.width > canvas.height ? 'landscape' : 'portrait',
                                        unit: 'px',
                                        format: [canvas.width, canvas.height]
                                    });
                                }

                                pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
                                firstBlock = false;
                            } else {
                                // Subsequent blocks - add new page with this block's dimensions
                                pdf.addPage([canvas.width, canvas.height], canvas.width > canvas.height ? 'l' : 'p');
                                pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
                            }

                            // Process next block
                            processedBlocks++;
                            processNextBlock();

                        }).catch(function(error) {
                            console.error('CBD: html2canvas error for block', processedBlocks + 1, error);

                            // Restore visibility even on error
                            if (actionButtons.length > 0) {
                                actionButtons.css({
                                    'visibility': originalVisibility,
                                    'opacity': '1'
                                });
                            }

                            // Restore ALL original collapsed states even on error
                            restoreOriginalStates();

                            // Skip this block and continue
                            processedBlocks++;
                            processNextBlock();
                        });
                    }, 350); // 350ms delay for collapse animation to complete
                }

                // OLD COMPLEX CODE REMOVED - Now using simple method like single block export
                // The simple method creates PDF pages with exact canvas dimensions (no A4 scaling)
                // This matches the working single-block export functionality
            }

            // Start processing
            processNextBlock();

        } catch (error) {
            alert('Fehler beim PDF erstellen: ' + error.message);
            return false;
        }
    }

    // Start loading
    loadFromCDN();
})();