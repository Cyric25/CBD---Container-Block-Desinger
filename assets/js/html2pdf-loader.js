/**
 * html2pdf.js Loader with multiple CDN fallbacks
 * Creates text-based searchable PDFs instead of image-only PDFs
 * Ensures html2pdf is always available for the Container Block Designer
 */

(function() {
    'use strict';

    // Check if html2pdf is already loaded
    if (typeof window.html2pdf !== 'undefined') {
        window.cbdPDFStatus = {
            loading: false,
            loaded: true,
            error: null,
            attempts: ['SUCCESS: html2pdf already loaded']
        };
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
        'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js',
        'https://unpkg.com/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js',
        'https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js'
    ];

    var currentSourceIndex = 0;
    var maxRetries = cdnSources.length;

    function loadFromCDN() {
        if (currentSourceIndex >= maxRetries) {
            window.cbdPDFStatus.loading = false;
            window.cbdPDFStatus.loaded = false;
            window.cbdPDFStatus.error = 'All CDN sources failed: ' + window.cbdPDFStatus.attempts.join(', ');

            // Last resort - create a simple text export
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
                try {
                    if (typeof window.html2pdf !== 'undefined') {
                        window.cbdPDFStatus.loading = false;
                        window.cbdPDFStatus.loaded = true;
                        setupPDFExportFunctions();
                    } else {
                        throw new Error('html2pdf not accessible after load');
                    }
                } catch (error) {
                    window.cbdPDFStatus.attempts.push('FAILED: ' + cdnSources[currentSourceIndex] + ' (test failed: ' + error.message + ')');
                    currentSourceIndex++;
                    loadFromCDN();
                }
            }, 200);
        };

        script.onerror = function() {
            console.log('CBD: Failed to load html2pdf from:', cdnSources[currentSourceIndex]);
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
            return cbdCreatePDF(containerBlocks, mode || 'text', quality || 2);
        };

        // Create global export function (backward compatibility)
        window.cbdPDFExport = function(containerBlocks) {
            return cbdCreatePDF(containerBlocks, 'text', 2);
        };
    }

    // Main PDF creation function using html2pdf.js
    function cbdCreatePDF(containerBlocks, mode, quality) {
        try {
            var $ = window.jQuery || window.$;

            // Ensure containerBlocks is a jQuery object
            if (!$ || typeof $.fn === 'undefined') {
                throw new Error('jQuery is not available');
            }

            // Convert to jQuery object if needed
            if (!containerBlocks.jquery) {
                containerBlocks = $(containerBlocks);
            }

            console.log('CBD PDF: Starting PDF generation');
            console.log('CBD PDF: html2pdf available:', typeof window.html2pdf !== 'undefined');
            console.log('CBD PDF: Container blocks count:', containerBlocks.length);

            // Filter out nested containers to prevent duplication
            containerBlocks = containerBlocks.filter(function() {
                var isNested = $(this).closest('.cbd-container-content').length > 0;
                return !isNested;
            });

            console.log('CBD PDF: After filtering nested, count:', containerBlocks.length);

            if (containerBlocks.length === 0) {
                alert('Keine Container-Blöcke zum Exportieren gefunden.');
                return false;
            }

            // Verify html2pdf is available
            if (typeof window.html2pdf === 'undefined') {
                throw new Error('html2pdf.js ist nicht geladen');
            }

            // Create wrapper for all blocks
            var $wrapper = $('<div class="cbd-pdf-export-wrapper"></div>');

            // Process each block
            containerBlocks.each(function(index) {
                var $block = $(this);
                console.log('CBD PDF: Processing block', index + 1, 'of', containerBlocks.length);
                console.log('CBD PDF: Block', index + 1, 'classes:', $block.attr('class'));

                // Clone with deep copy
                var $clone = $block.clone(true, true);
                console.log('CBD PDF: Block', index + 1, 'cloned, HTML length:', $clone.html().length);

                // Find the actual content block inside
                var $contentBlock = $clone.find('.cbd-container-block').first();
                if ($contentBlock.length === 0) {
                    console.warn('CBD PDF: Block', index + 1, 'has no .cbd-container-block, using whole clone');
                } else {
                    console.log('CBD PDF: Block', index + 1, 'found .cbd-container-block');
                }

                // Expand collapsed content
                expandContent($clone);
                console.log('CBD PDF: Block', index + 1, 'content expanded');

                // Hide action buttons and menus
                $clone.find('.cbd-action-buttons').remove();
                $clone.find('.cbd-collapse-toggle').remove();
                $clone.find('.cbd-header-menu').remove();
                $clone.find('.cbd-container-number').remove(); // Remove counter circles

                // Add page break after each block (except last)
                if (index < containerBlocks.length - 1) {
                    $clone.css('page-break-after', 'always');
                }

                $wrapper.append($clone);
                console.log('CBD PDF: Block', index + 1, 'appended to wrapper');
            });

            console.log('CBD PDF: Wrapper created with', $wrapper.children().length, 'blocks');
            console.log('CBD PDF: Wrapper HTML length:', $wrapper.html().length);

            // Add wrapper to body - VISIBLE during PDF generation
            // html2canvas requires elements to be in viewport
            $wrapper.css({
                position: 'fixed',
                top: '0',
                left: '0',
                width: '794px', // A4 width in pixels (210mm)
                maxHeight: '100vh',
                backgroundColor: '#fff',
                opacity: '1',
                overflow: 'auto', // Allow scrolling for html2canvas
                zIndex: '999999', // On top during generation
                boxShadow: '0 0 0 9999px rgba(0,0,0,0.8)' // Dark overlay behind
            });
            $('body').append($wrapper);

            console.log('CBD PDF: Wrapper appended to body');
            console.log('CBD PDF: Wrapper dimensions:', $wrapper[0].offsetWidth, 'x', $wrapper[0].offsetHeight);

            // Add loading message
            var $loadingMsg = $('<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px;border-radius:8px;z-index:9999999;box-shadow:0 4px 20px rgba(0,0,0,0.3);text-align:center;"><h3 style="margin:0 0 10px 0;">PDF wird erstellt...</h3><p style="margin:0;color:#666;">Bitte warten Sie einen Moment.</p></div>');
            $('body').append($loadingMsg);

            // Configure html2pdf options
            var opt = {
                margin: [10, 10, 10, 10],
                filename: 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: quality,
                    useCORS: true,
                    logging: true, // Enable logging to debug
                    letterRendering: true,
                    scrollY: 0,
                    scrollX: 0,
                    windowWidth: 794,
                    windowHeight: $wrapper[0].scrollHeight,
                    backgroundColor: '#ffffff',
                    removeContainer: false // Keep container for debugging
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };

            console.log('CBD PDF: Wrapper scroll height:', $wrapper[0].scrollHeight);
            console.log('CBD PDF: Wrapper has children:', $wrapper.children().length);
            console.log('CBD PDF: First child:', $wrapper.children().first()[0]);

            // Generate PDF
            console.log('CBD PDF: Starting html2pdf generation...');
            console.log('CBD PDF: Wrapper element:', $wrapper[0]);
            console.log('CBD PDF: Options:', opt);

            // Small delay to ensure rendering
            setTimeout(function() {
                console.log('CBD PDF: Delay complete, starting capture...');

                // Direct html2canvas test first
                if (typeof html2canvas !== 'undefined') {
                    console.log('CBD PDF: Testing direct html2canvas first...');
                    html2canvas($wrapper[0], {
                        scale: quality,
                        useCORS: true,
                        logging: true,
                        backgroundColor: '#ffffff',
                        scrollY: 0,
                        scrollX: 0
                    }).then(function(canvas) {
                        console.log('CBD PDF: html2canvas SUCCESS! Canvas size:', canvas.width, 'x', canvas.height);
                        console.log('CBD PDF: Canvas has data:', canvas.toDataURL('image/png').length, 'bytes');

                        // Now try html2pdf
                        html2pdf()
                            .set(opt)
                            .from($wrapper[0])
                            .save()
                            .then(function() {
                                console.log('CBD PDF: PDF generation successful');
                                $wrapper.remove();
                                $loadingMsg.remove();
                            })
                            .catch(function(error) {
                                console.error('CBD PDF: Generation error:', error);
                                $wrapper.remove();
                                $loadingMsg.remove();
                                alert('Fehler beim PDF erstellen: ' + error.message);
                            });
                    }).catch(function(error) {
                        console.error('CBD PDF: html2canvas FAILED:', error);
                        $wrapper.remove();
                        $loadingMsg.remove();
                        alert('html2canvas Fehler: ' + error.message);
                    });
                } else {
                    console.log('CBD PDF: html2canvas not available, using html2pdf directly');
                    html2pdf()
                        .set(opt)
                        .from($wrapper[0])
                        .save()
                        .then(function() {
                            console.log('CBD PDF: PDF generation successful');
                            $wrapper.remove();
                            $loadingMsg.remove();
                        })
                        .catch(function(error) {
                            console.error('CBD PDF: Generation error:', error);
                            $wrapper.remove();
                            $loadingMsg.remove();
                            alert('Fehler beim PDF erstellen: ' + error.message);
                        });
                }
            }, 500); // Increased delay to 500ms for better rendering

            console.log('CBD PDF: html2pdf() will start after render delay...');
            return true;

        } catch (error) {
            alert('Fehler beim PDF erstellen: ' + error.message);
            return false;
        }
    }

    // Helper: Expand collapsed content
    function expandContent($element) {
        var $ = window.jQuery || window.$;

        // Expand all collapsed CBD containers
        $element.find('[data-wp-interactive="container-block-designer"]').each(function() {
            var $container = $(this);
            var $content = $container.find('.cbd-container-content').first();

            if ($content.length > 0) {
                $content.css({
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1',
                    'max-height': 'none',
                    'overflow': 'visible',
                    'height': 'auto'
                });
            }
        });

        // Expand details elements
        $element.find('details').each(function() {
            this.open = true;
        });

        // Show hidden elements (except action buttons)
        $element.find('[style*="display: none"], [style*="visibility: hidden"]').each(function() {
            var $elem = $(this);
            if (!$elem.hasClass('cbd-action-buttons') &&
                !$elem.hasClass('cbd-action-btn') &&
                !$elem.hasClass('dashicons')) {
                $elem.css({
                    'display': 'block',
                    'visibility': 'visible'
                });
            }
        });
    }

    // Start loading
    loadFromCDN();
})();
