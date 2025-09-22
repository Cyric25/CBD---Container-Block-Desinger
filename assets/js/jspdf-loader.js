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

                if (y > 200) {
                    pdf.addPage();
                    y = 30;
                }

                // Add block title
                pdf.setFontSize(14);
                pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle, 20, y);
                y += 15;

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

                            var canvasOptions = {
                                useCORS: true,
                                allowTaint: false,
                                scale: quality,
                                logging: false,
                                backgroundColor: 'white'
                            };

                            // Print mode - modify styles for print
                            if (mode === 'print') {
                                console.log('CBD: Applying PRINT mode styling');
                                canvasOptions.onclone = function(clonedDoc) {
                                    console.log('CBD: Applying print-friendly styles');

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

                            console.log('CBD: Starting html2canvas...');

                            try {
                                html2canvas($currentBlock[0], canvasOptions).then(function(canvas) {
                                    console.log('CBD: html2canvas successful - Canvas size:', canvas.width + 'x' + canvas.height);

                                    var imageFormat = 'JPEG';
                                    var imageQuality = mode === 'print' ? 0.9 : 0.8;
                                    var imgData = canvas.toDataURL('image/jpeg', imageQuality);
                                    var imgWidth = 170;
                                    var imgHeight = (canvas.height * imgWidth) / canvas.width;

                                    // Check if image fits on current page
                                    if (y + imgHeight > 250) {
                                        pdf.addPage();
                                        y = 30;
                                    }

                                    pdf.addImage(imgData, imageFormat, 20, y, imgWidth, imgHeight);
                                    y += imgHeight + 10;

                                    processedBlocks++;
                                    processNextBlock();
                                }).catch(function(error) {
                                    console.error('CBD: html2canvas failed for block ' + (processedBlocks + 1) + ':', error);
                                    console.log('CBD: Falling back to text-only for this block');
                                    addTextOnly();
                                });
                            } catch (syncError) {
                                console.error('CBD: html2canvas synchronous error:', syncError);
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
                    var blockContent = $currentBlock.find('.cbd-container-content').text().substring(0, 300);
                    console.log('CBD: Block content length:', blockContent.length);
                    pdf.setFontSize(10);
                    var lines = pdf.splitTextToSize(blockContent, 170);
                    for (var i = 0; i < Math.min(lines.length, 8); i++) {
                        pdf.text(lines[i], 20, y);
                        y += 6;
                    }
                    y += 10;

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