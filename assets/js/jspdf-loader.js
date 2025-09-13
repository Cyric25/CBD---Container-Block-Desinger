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
                        // Create global export function
                        window.cbdPDFExport = function(containerBlocks) {
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
                                pdf.text('Container Blöcke Export', 20, 30);
                                
                                pdf.setFontSize(12);
                                pdf.text('Exportiert am: ' + new Date().toLocaleDateString('de-DE'), 20, 50);
                                
                                var y = 70;
                                
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
                                    
                                    // Check if block has images
                                    var images = $currentBlock.find('img');
                                    if (images.length > 0) {
                                        console.log('CBD: Block has ' + images.length + ' images, using html2canvas');
                                        
                                        // Load html2canvas if needed
                                        if (typeof html2canvas === 'undefined') {
                                            var script = document.createElement('script');
                                            script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
                                            script.onload = function() {
                                                renderBlockWithImages();
                                            };
                                            document.head.appendChild(script);
                                        } else {
                                            renderBlockWithImages();
                                        }
                                        
                                        function renderBlockWithImages() {
                                            html2canvas($currentBlock[0], {
                                                useCORS: true,
                                                allowTaint: false,
                                                scale: 0.5,
                                                logging: false
                                            }).then(function(canvas) {
                                                var imgData = canvas.toDataURL('image/jpeg', 0.7);
                                                var imgWidth = 170;
                                                var imgHeight = (canvas.height * imgWidth) / canvas.width;
                                                
                                                // Check if image fits on current page
                                                if (y + imgHeight > 280) {
                                                    pdf.addPage();
                                                    y = 30;
                                                }
                                                
                                                pdf.addImage(imgData, 'JPEG', 20, y, imgWidth, imgHeight);
                                                y += imgHeight + 10;
                                                
                                                processedBlocks++;
                                                processNextBlock();
                                            }).catch(function(error) {
                                                console.log('CBD: html2canvas failed for block, using text only:', error);
                                                addTextOnly();
                                            });
                                        }
                                    } else {
                                        // No images, just add text
                                        addTextOnly();
                                    }
                                    
                                    function addTextOnly() {
                                        var blockContent = $currentBlock.find('.cbd-container-content').text().substring(0, 300);
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
                                
                                var filename = 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf';
                                pdf.save(filename);
                                
                                console.log('CBD: PDF saved successfully:', filename);
                                return true;
                                
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