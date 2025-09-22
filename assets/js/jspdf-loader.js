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

                                    console.log('CBD: Image dimensions - Width:', imgWidth, 'Height:', imgHeight, 'Current Y:', y);

                                    // Intelligent page splitting for large blocks
                                    var pageHeight = 280; // A4 page height minus margins
                                    var availableHeight = pageHeight - y;
                                    var maxSinglePageHeight = pageHeight - 50; // Leave space for headers

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
                    var blockContent = $currentBlock.find('.cbd-container-content').text();
                    console.log('CBD: Block content length:', blockContent.length);

                    pdf.setFontSize(10);
                    var lines = pdf.splitTextToSize(blockContent, 170);
                    console.log('CBD: Text will be split into', lines.length, 'lines');

                    var pageHeight = 280;
                    var lineHeight = 6;
                    var maxLinesPerPage = Math.floor((pageHeight - 50) / lineHeight); // Leave space for headers

                    var pageIndex = 0;
                    for (var i = 0; i < lines.length; i++) {
                        // Check if we need a new page
                        if (y + lineHeight > pageHeight || (i > 0 && i % maxLinesPerPage === 0)) {
                            pageIndex++;
                            console.log('CBD: Starting new page for text continuation at line', i + 1);
                            pdf.addPage();
                            y = 30;

                            // Add block title on new page for continuation
                            if (i > 0) {
                                pdf.setFontSize(14);
                                pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle + ' (Teil ' + (pageIndex + 1) + ')', 20, y);
                                y += 15;

                                // Add continuation indicator
                                pdf.setFontSize(8);
                                pdf.setTextColor(128, 128, 128);
                                pdf.text('(Fortsetzung von vorheriger Seite)', 20, y);
                                pdf.setTextColor(0, 0, 0);
                                pdf.setFontSize(10);
                                y += 10;
                            }
                        }

                        pdf.text(lines[i], 20, y);
                        y += lineHeight;
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