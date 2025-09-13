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
                                
                                containerBlocks.each(function(index) {
                                    if (y > 250) {
                                        pdf.addPage();
                                        y = 30;
                                    }
                                    
                                    var $this = $(this);
                                    var blockTitle = $this.find('.cbd-block-title').text() || 'Block ' + (index + 1);
                                    var blockContent = $this.find('.cbd-container-content').text().substring(0, 200);
                                    
                                    console.log('CBD: Block ' + (index + 1) + ' - Title: "' + blockTitle + '", Content length: ' + blockContent.length);
                                    console.log('CBD: Block element classes:', this.className);
                                    console.log('CBD: Block element id:', this.id);
                                    
                                    pdf.setFontSize(14);
                                    pdf.text('Block ' + (index + 1) + ': ' + blockTitle, 20, y);
                                    y += 10;
                                    
                                    pdf.setFontSize(10);
                                    var lines = pdf.splitTextToSize(blockContent, 170);
                                    for (var i = 0; i < lines.length && i < 5; i++) {
                                        pdf.text(lines[i], 20, y);
                                        y += 6;
                                    }
                                    
                                    y += 15;
                                });
                                
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