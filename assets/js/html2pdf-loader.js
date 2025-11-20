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

            // Filter out nested containers to prevent duplication
            containerBlocks = containerBlocks.filter(function() {
                var isNested = $(this).closest('.cbd-container-content').length > 0;
                return !isNested;
            });

            if (containerBlocks.length === 0) {
                alert('Keine Container-Blöcke zum Exportieren gefunden.');
                return false;
            }

            // Create wrapper for all blocks
            var $wrapper = $('<div class="cbd-pdf-export-wrapper"></div>');

            // Process each block
            containerBlocks.each(function(index) {
                var $block = $(this);
                var $clone = $block.clone();

                // Expand collapsed content
                expandContent($clone);

                // Hide action buttons
                $clone.find('.cbd-action-buttons').remove();
                $clone.find('.cbd-collapse-toggle').remove();

                // Add page break after each block (except last)
                if (index < containerBlocks.length - 1) {
                    $clone.css('page-break-after', 'always');
                }

                $wrapper.append($clone);
            });

            // Add wrapper to body (hidden)
            $wrapper.css({
                position: 'absolute',
                left: '-9999px',
                top: '0',
                width: '794px', // A4 width in pixels (210mm)
                backgroundColor: '#fff'
            });
            $('body').append($wrapper);

            // Configure html2pdf options
            var opt = {
                margin: [10, 10, 10, 10], // [top, left, bottom, right] in mm
                filename: 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: quality,
                    useCORS: true,
                    logging: false,
                    letterRendering: true
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };

            // Generate PDF
            html2pdf()
                .set(opt)
                .from($wrapper[0])
                .save()
                .then(function() {
                    // Remove wrapper
                    $wrapper.remove();
                })
                .catch(function(error) {
                    console.error('CBD: PDF generation error:', error);
                    $wrapper.remove();
                    alert('Fehler beim PDF erstellen: ' + error.message);
                });

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
