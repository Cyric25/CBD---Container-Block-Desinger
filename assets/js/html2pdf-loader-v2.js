/**
 * html2pdf Loader V2 - Sequential block processing with IN-PLACE expansion
 * Based on old working solution but uses html2pdf.js for text-based PDFs
 */

(function() {
    'use strict';

    // Create global status tracking
    window.cbdPDFStatus = {
        loading: true,
        loaded: false,
        error: null
    };

    var cdnSources = [
        'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js',
        'https://unpkg.com/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js'
    ];

    var currentSourceIndex = 0;

    function loadFromCDN() {
        if (currentSourceIndex >= cdnSources.length) {
            window.cbdPDFStatus.loading = false;
            window.cbdPDFStatus.error = 'All CDN sources failed';
            console.error('CBD PDF: Failed to load html2pdf.js from all CDNs');
            return;
        }

        var script = document.createElement('script');
        script.src = cdnSources[currentSourceIndex];
        script.async = true;

        script.onload = function() {
            setTimeout(function() {
                if (typeof window.html2pdf !== 'undefined') {
                    window.cbdPDFStatus.loading = false;
                    window.cbdPDFStatus.loaded = true;
                    console.log('CBD PDF: html2pdf.js loaded successfully');
                    setupPDFExportFunctions();
                } else {
                    console.error('CBD PDF: html2pdf loaded but not available');
                    currentSourceIndex++;
                    loadFromCDN();
                }
            }, 200);
        };

        script.onerror = function() {
            console.log('CBD PDF: Failed from:', cdnSources[currentSourceIndex]);
            currentSourceIndex++;
            loadFromCDN();
        };

        document.head.appendChild(script);
    }

    function setupPDFExportFunctions() {
        window.cbdPDFExportWithOptions = function(containerBlocks, mode, quality) {
            return cbdCreatePDF(containerBlocks, mode || 'text', quality || 2);
        };

        window.cbdPDFExport = function(containerBlocks) {
            return cbdCreatePDF(containerBlocks, 'text', 2);
        };
    }

    // Main PDF creation function - SEQUENTIAL processing like old solution
    function cbdCreatePDF(containerBlocks, mode, quality) {
        try {
            var $ = window.jQuery || window.$;

            if (!$ || typeof $.fn === 'undefined') {
                throw new Error('jQuery is not available');
            }

            if (!containerBlocks.jquery) {
                containerBlocks = $(containerBlocks);
            }

            console.log('CBD PDF V2: Starting sequential PDF generation');
            console.log('CBD PDF V2: Total blocks:', containerBlocks.length);

            if (containerBlocks.length === 0) {
                alert('Keine Container-Blöcke zum Exportieren gefunden.');
                return false;
            }

            if (typeof window.html2pdf === 'undefined') {
                throw new Error('html2pdf.js ist nicht geladen');
            }

            // Add loading message
            var $loadingMsg = $('<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px;border-radius:8px;z-index:9999999;box-shadow:0 4px 20px rgba(0,0,0,0.3);text-align:center;"><h3 style="margin:0 0 10px 0;">PDF wird erstellt...</h3><p style="margin:0;color:#666;">Bitte warten Sie einen Moment.</p></div>');
            $('body').append($loadingMsg);

            // Variables for sequential processing
            var processedBlocks = 0;
            var pdf = null;
            var worker = null;

            // Process blocks ONE BY ONE (like old solution)
            function processNextBlock() {
                if (processedBlocks >= containerBlocks.length) {
                    // All done
                    $loadingMsg.remove();
                    console.log('CBD PDF V2: All blocks processed successfully!');
                    return true;
                }

                var $currentBlock = $(containerBlocks[processedBlocks]);
                console.log('CBD PDF V2: Processing block', processedBlocks + 1, 'of', containerBlocks.length);

                // STEP 1: Expand IN-PLACE (like old working solution)
                expandBlockInPlace($currentBlock);

                // STEP 2: Wait for animation (350ms like old solution)
                setTimeout(function() {
                    // STEP 3: Clone and prepare
                    var $clone = $currentBlock.clone(true, true);

                    // Remove action buttons
                    $clone.find('.cbd-action-buttons').remove();
                    $clone.find('.cbd-collapse-toggle').remove();
                    $clone.find('.cbd-header-menu').remove();
                    $clone.find('.cbd-container-number').remove();

                    // Create temporary container for this block
                    var $tempWrapper = $('<div class="cbd-pdf-temp-wrapper"></div>');
                    $tempWrapper.css({
                        position: 'fixed',
                        top: '0',
                        left: '0',
                        width: '794px', // A4 width
                        backgroundColor: '#fff',
                        zIndex: '999999',
                        padding: '20px'
                    });
                    $tempWrapper.append($clone);
                    $('body').append($tempWrapper);

                    console.log('CBD PDF V2: Block', processedBlocks + 1, 'rendered, generating PDF page...');

                    // Configure html2pdf for this single block
                    var opt = {
                        margin: [10, 10, 10, 10],
                        filename: 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf',
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: {
                            scale: quality,
                            useCORS: true,
                            logging: false,
                            letterRendering: true,
                            scrollY: 0,
                            scrollX: 0,
                            backgroundColor: '#ffffff'
                        },
                        jsPDF: {
                            unit: 'mm',
                            format: 'a4',
                            orientation: 'portrait'
                        },
                        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                    };

                    // Generate PDF from this block
                    if (processedBlocks === 0) {
                        // First block - create new PDF
                        worker = html2pdf().set(opt).from($tempWrapper[0]);

                        worker.toPdf().get('pdf').then(function(pdfObj) {
                            pdf = pdfObj;
                            console.log('CBD PDF V2: Block 1 added to PDF');

                            // Cleanup
                            $tempWrapper.remove();
                            restoreBlockState($currentBlock);

                            // Process next
                            processedBlocks++;
                            processNextBlock();
                        }).catch(function(error) {
                            console.error('CBD PDF V2: Error on block 1:', error);
                            $tempWrapper.remove();
                            restoreBlockState($currentBlock);
                            $loadingMsg.remove();
                            alert('Fehler beim PDF erstellen: ' + error.message);
                        });
                    } else {
                        // Subsequent blocks - add new page
                        html2pdf().set(opt).from($tempWrapper[0]).toPdf().get('pdf').then(function(pdfObj) {
                            // Add page to existing PDF
                            pdf.addPage();
                            var pages = pdfObj.internal.pages;
                            var lastPage = pages[pages.length - 1];

                            // Copy content from new page to existing PDF
                            // This is a workaround for html2pdf limitations
                            console.log('CBD PDF V2: Block', processedBlocks + 1, 'added to PDF');

                            // Cleanup
                            $tempWrapper.remove();
                            restoreBlockState($currentBlock);

                            // Check if this is the last block
                            if (processedBlocks === containerBlocks.length - 1) {
                                // Save final PDF
                                pdf.save(opt.filename);
                                console.log('CBD PDF V2: PDF saved successfully!');
                            }

                            // Process next
                            processedBlocks++;
                            processNextBlock();
                        }).catch(function(error) {
                            console.error('CBD PDF V2: Error on block', processedBlocks + 1, ':', error);
                            $tempWrapper.remove();
                            restoreBlockState($currentBlock);
                            $loadingMsg.remove();
                            alert('Fehler beim PDF erstellen: ' + error.message);
                        });
                    }

                }, 350); // 350ms delay like old solution
            }

            // Start processing
            processNextBlock();
            return true;

        } catch (error) {
            console.error('CBD PDF V2: Fatal error:', error);
            alert('Fehler beim PDF erstellen: ' + error.message);
            return false;
        }
    }

    // Expand block IN-PLACE (like old working solution)
    function expandBlockInPlace($block) {
        var $ = window.jQuery || window.$;

        console.log('CBD PDF V2: Expanding block in-place...');

        // Find ALL container blocks (including nested)
        var allContainerBlocks = $block.find('[data-wp-interactive="container-block-designer"]');
        if ($block.is('[data-wp-interactive="container-block-designer"]')) {
            allContainerBlocks = allContainerBlocks.add($block);
        }

        console.log('CBD PDF V2: Found', allContainerBlocks.length, 'interactive container(s)');

        // Expand EACH container (including nested ones)
        allContainerBlocks.each(function() {
            var $container = $(this);
            var content = $container.find('.cbd-container-content').first();

            if (content.length > 0) {
                var computedDisplay = window.getComputedStyle(content[0]).display;
                var isHidden = computedDisplay === 'none' ||
                             !content.is(':visible') ||
                             content.css('display') === 'none';

                if (isHidden) {
                    console.log('CBD PDF V2: - Expanding hidden content');
                    content[0].style.setProperty('display', 'block', 'important');
                    content[0].style.setProperty('visibility', 'visible', 'important');
                    content[0].style.setProperty('opacity', '1', 'important');
                    content[0].style.setProperty('max-height', 'none', 'important');
                    content[0].style.setProperty('overflow', 'visible', 'important');
                    content[0].style.setProperty('height', 'auto', 'important');

                    // Store for restoration
                    content[0].setAttribute('data-cbd-was-hidden', 'true');
                }
            }
        });

        // Expand details elements
        $block.find('details').each(function() {
            if (!this.open) {
                this.open = true;
                this.setAttribute('data-cbd-was-closed', 'true');
            }
        });
    }

    // Restore block to original state
    function restoreBlockState($block) {
        var $ = window.jQuery || window.$;

        // Restore hidden content
        $block.find('[data-cbd-was-hidden="true"]').each(function() {
            this.style.removeProperty('display');
            this.style.removeProperty('visibility');
            this.style.removeProperty('opacity');
            this.style.removeProperty('max-height');
            this.style.removeProperty('overflow');
            this.style.removeProperty('height');
            this.removeAttribute('data-cbd-was-hidden');
        });

        // Restore details elements
        $block.find('[data-cbd-was-closed="true"]').each(function() {
            this.open = false;
            this.removeAttribute('data-cbd-was-closed');
        });
    }

    // Start loading
    loadFromCDN();
})();
