/**
 * jsPDF Loader with multiple CDN fallbacks
 * Ensures jsPDF is always available for the Container Block Designer
 */

(function() {
    'use strict';
    
    // Check if jsPDF is already loaded
    if (typeof window.jsPDF !== 'undefined' || typeof jsPDF !== 'undefined') {
        console.log('CBD: jsPDF already loaded');
        return;
    }
    
    console.log('CBD: Loading jsPDF with fallback mechanism...');
    
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
            
            // Verify jsPDF is accessible
            setTimeout(function() {
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
                        // Create enhanced export function with options
                        window.cbdPDFExportWithOptions = function(containerBlocks, mode, quality) {
                            return cbdCreatePDF(containerBlocks, mode || 'visual', quality || 1);
                        };
                        
                        // Create global export function (backward compatibility)
                        window.cbdPDFExport = function(containerBlocks) {
                            return cbdCreatePDF(containerBlocks, 'visual', 1);
                        };
                        
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
                                        var images = $currentBlock.find('img');
                                        console.log('CBD: Block ' + (processedBlocks + 1) + ' - Images found:', images.length, 'Mode:', mode);
                                        
                                        // FORCE visual rendering for visual and print modes (ignore images check)
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
                                                    logging: true,
                                                    backgroundColor: 'white'
                                                };
                                                
                                                // Print mode - modify styles for print
                                                if (mode === 'print') {
                                                    console.log('CBD: Applying PRINT mode styling');
                                                    canvasOptions.backgroundColor = 'white';
                                                    canvasOptions.onclone = function(clonedDoc) {
                                                        console.log('CBD: Applying print-friendly styles with direct DOM manipulation');
                                                        
                                                        // Direct DOM manipulation - more reliable than CSS
                                                        var elements = clonedDoc.querySelectorAll('*');
                                                        
                                                        for (var i = 0; i < elements.length; i++) {
                                                            var el = elements[i];
                                                            
                                                            // Force remove all backgrounds
                                                            el.style.setProperty('background', 'transparent', 'important');
                                                            el.style.setProperty('background-color', 'transparent', 'important');
                                                            el.style.setProperty('background-image', 'none', 'important');
                                                            el.style.setProperty('background-attachment', 'initial', 'important');
                                                            el.style.setProperty('background-position', 'initial', 'important');
                                                            el.style.setProperty('background-repeat', 'initial', 'important');
                                                            el.style.setProperty('background-size', 'initial', 'important');
                                                            
                                                            // Force remove all shadows and effects
                                                            el.style.setProperty('box-shadow', 'none', 'important');
                                                            el.style.setProperty('text-shadow', 'none', 'important');
                                                            el.style.setProperty('filter', 'none', 'important');
                                                            el.style.setProperty('backdrop-filter', 'none', 'important');
                                                            el.style.setProperty('-webkit-filter', 'none', 'important');
                                                            el.style.setProperty('-webkit-backdrop-filter', 'none', 'important');
                                                            
                                                            // Force text color to black
                                                            if (!el.matches('img, svg, canvas')) {
                                                                el.style.setProperty('color', '#000000', 'important');
                                                            }
                                                            
                                                            // AGGRESSIVE border removal - all border properties
                                                            el.style.setProperty('border', 'none', 'important');
                                                            el.style.setProperty('border-top', 'none', 'important');
                                                            el.style.setProperty('border-right', 'none', 'important');
                                                            el.style.setProperty('border-bottom', 'none', 'important');
                                                            el.style.setProperty('border-left', 'none', 'important');
                                                            el.style.setProperty('border-width', '0', 'important');
                                                            el.style.setProperty('border-style', 'none', 'important');
                                                            el.style.setProperty('border-color', 'transparent', 'important');
                                                            el.style.setProperty('outline', 'none', 'important');
                                                            el.style.setProperty('outline-width', '0', 'important');
                                                            el.style.setProperty('outline-style', 'none', 'important');
                                                            el.style.setProperty('outline-color', 'transparent', 'important');
                                                        }
                                                        
                                                        // Special handling for container - no borders in print mode
                                                        var containers = clonedDoc.querySelectorAll('.cbd-container');
                                                        for (var j = 0; j < containers.length; j++) {
                                                            var container = containers[j];
                                                            container.style.setProperty('background-color', 'white', 'important');
                                                            container.style.setProperty('background', 'white', 'important');
                                                            container.style.setProperty('border', 'none', 'important');
                                                            container.style.setProperty('box-shadow', 'none', 'important');
                                                        }
                                                        
                                                        // PHYSICALLY REMOVE action buttons and button containers from DOM
                                                        var actionButtons = clonedDoc.querySelectorAll('.cbd-action-buttons, .cbd-action-btn, .cbd-collapse-toggle, .cbd-copy-text, .cbd-screenshot, button, .dashicons');
                                                        console.log('CBD: Removing', actionButtons.length, 'button elements from DOM');
                                                        for (var k = actionButtons.length - 1; k >= 0; k--) {
                                                            var btn = actionButtons[k];
                                                            if (btn && btn.parentNode) {
                                                                btn.parentNode.removeChild(btn);
                                                                console.log('CBD: Removed button element:', btn.className);
                                                            }
                                                        }
                                                        
                                                        // Special handling for numbering - no borders in print mode
                                                        var numbers = clonedDoc.querySelectorAll('.cbd-container-number');
                                                        for (var l = 0; l < numbers.length; l++) {
                                                            var num = numbers[l];
                                                            num.style.setProperty('background', 'transparent', 'important');
                                                            num.style.setProperty('background-color', 'transparent', 'important');
                                                            num.style.setProperty('border', 'none', 'important');
                                                            num.style.setProperty('color', '#000000', 'important');
                                                            num.style.setProperty('box-shadow', 'none', 'important');
                                                        }
                                                        
                                                        // Remove any remaining visual elements that might have borders
                                                        var visualElements = clonedDoc.querySelectorAll('[style*=\"border\"], [class*=\"border\"], [class*=\"outline\"]');
                                                        for (var m = 0; m < visualElements.length; m++) {
                                                            var elem = visualElements[m];
                                                            elem.style.setProperty('border', 'none', 'important');
                                                            elem.style.setProperty('outline', 'none', 'important');
                                                        }
                                                        
                                                        console.log('CBD: Direct DOM manipulation applied to', elements.length, 'elements');
                                                    };
                                                } else {
                                                    console.log('CBD: Applying VISUAL mode styling');
                                                    // Visual/Standard mode - optimized for good visual rendering
                                                    canvasOptions.backgroundColor = 'white';
                                                    canvasOptions.useCORS = true;
                                                    canvasOptions.allowTaint = false;
                                                    canvasOptions.logging = true;
                                                    
                                                    // Ensure content visibility in visual mode with error handling
                                                    canvasOptions.onclone = function(clonedDoc) {
                                                        try {
                                                            console.log('CBD: Optimizing visual mode rendering');
                                                            
                                                            // Simple visual mode optimization - just ensure visibility
                                                            var containers = clonedDoc.querySelectorAll('.cbd-container');
                                                            console.log('CBD: Found', containers.length, 'containers in visual mode');
                                                            
                                                            for (var i = 0; i < containers.length; i++) {
                                                                var container = containers[i];
                                                                
                                                                // Ensure container content is visible
                                                                var content = container.querySelector('.cbd-container-content');
                                                                if (content) {
                                                                    content.style.setProperty('display', 'block', 'important');
                                                                    content.style.setProperty('visibility', 'visible', 'important');
                                                                    content.style.setProperty('opacity', '1', 'important');
                                                                    console.log('CBD: Made content visible for container', i + 1);
                                                                }
                                                                
                                                                // Ensure container is visible
                                                                container.style.setProperty('display', 'block', 'important');
                                                                container.style.setProperty('visibility', 'visible', 'important');
                                                                container.style.setProperty('opacity', '1', 'important');
                                                            }
                                                            
                                                            // REMOVE action buttons and icons in visual mode too (cleaner PDFs)
                                                            var actionButtons = clonedDoc.querySelectorAll('.cbd-action-buttons, .cbd-action-btn, .cbd-collapse-toggle, .cbd-copy-text, .cbd-screenshot, button, .dashicons');
                                                            console.log('CBD: Removing', actionButtons.length, 'button elements from visual mode');
                                                            for (var j = actionButtons.length - 1; j >= 0; j--) {
                                                                try {
                                                                    var btn = actionButtons[j];
                                                                    if (btn && btn.parentNode) {
                                                                        btn.parentNode.removeChild(btn);
                                                                        console.log('CBD: Removed visual mode button element:', btn.className || btn.tagName);
                                                                    }
                                                                } catch (removeError) {
                                                                    console.log('CBD: Could not remove button element:', removeError.message);
                                                                }
                                                            }
                                                            
                                                            console.log('CBD: Visual mode optimization complete - containers and buttons should be visible');
                                                        } catch (oncloneError) {
                                                            console.error('CBD: onclone callback error:', oncloneError);
                                                            console.error('CBD: onclone error stack:', oncloneError.stack);
                                                            // Don't throw - let html2canvas continue
                                                        }
                                                    };
                                                }
                                                
                                                console.log('CBD: Starting html2canvas with options:', canvasOptions);
                                                console.log('CBD: Target element:', $currentBlock[0]);
                                                console.log('CBD: Element visibility:', $currentBlock.is(':visible'));
                                                console.log('CBD: Element dimensions:', $currentBlock[0].offsetWidth + 'x' + $currentBlock[0].offsetHeight);
                                                
                                                try {
                                                    console.log('CBD: About to call html2canvas...');
                                                    html2canvas($currentBlock[0], canvasOptions).then(function(canvas) {
                                                    console.log('CBD: html2canvas successful - Canvas size:', canvas.width + 'x' + canvas.height);
                                                    
                                                    var imageFormat = 'JPEG';
                                                    var imageQuality = mode === 'print' ? 0.9 : 0.8;
                                                    var imgData = canvas.toDataURL('image/jpeg', imageQuality);
                                                    var imgWidth = 170;
                                                    var imgHeight = (canvas.height * imgWidth) / canvas.width;
                                                    
                                                    console.log('CBD: Image dimensions - Width:', imgWidth, 'Height:', imgHeight, 'Quality:', imageQuality);
                                                    
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
                                                    console.error('CBD: Canvas options were:', canvasOptions);
                                                    console.error('CBD: Block element:', $currentBlock[0]);
                                                    console.log('CBD: Falling back to text-only for this block');
                                                    addTextOnly();
                                                });
                                                } catch (syncError) {
                                                    console.error('CBD: html2canvas synchronous error:', syncError);
                                                    console.error('CBD: Sync error stack:', syncError.stack);
                                                    console.log('CBD: Falling back to text-only due to sync error');
                                                    addTextOnly();
                                                }
                                            }
                                        } else {
                                            console.log('CBD: No visual content detected, using text-only rendering');
                                            // No visual content, use text
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
                        };
                    } else {
                        throw new Error('Cannot create jsPDF instance');
                    }
                } catch (error) {
                    console.log('CBD: jsPDF test failed:', error.message);
                    currentSourceIndex++;
                    loadFromCDN();
                }
            }, 100);
        };
        
        script.onerror = function() {
            console.log('CBD: Failed to load jsPDF from:', cdnSources[currentSourceIndex]);
            currentSourceIndex++;
            loadFromCDN();
        };
        
        console.log('CBD: Trying jsPDF source:', cdnSources[currentSourceIndex]);
        document.head.appendChild(script);
    }
    
    // Start loading
    loadFromCDN();
})();