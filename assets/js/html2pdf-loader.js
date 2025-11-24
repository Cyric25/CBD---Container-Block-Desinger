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

            // IMPORTANT: Do NOT filter nested containers - we want ALL blocks in PDF
            // Users explicitly want nested blocks to appear in the export
            console.log('CBD PDF: Including ALL blocks (nested and top-level):', containerBlocks.length);

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

            // CRITICAL: Expand ALL blocks IN-PLACE before cloning (like old working solution)
            console.log('CBD PDF: Expanding ALL blocks in-place before cloning...');
            var collapsedStates = [];

            containerBlocks.each(function(blockIndex) {
                var $block = $(this);
                console.log('CBD PDF: Pre-expanding block', blockIndex + 1);

                // Find ALL container blocks (including nested ones!) like old solution
                var allContainerBlocks = $block.find('[data-wp-interactive="container-block-designer"]');
                if ($block.is('[data-wp-interactive="container-block-designer"]')) {
                    allContainerBlocks = allContainerBlocks.add($block);
                }

                console.log('CBD PDF: Block', blockIndex + 1, 'has', allContainerBlocks.length, 'interactive container(s)');

                // Expand EACH container block (including nested ones) - OLD WORKING METHOD
                allContainerBlocks.each(function(containerIndex) {
                    var $container = $(this);
                    var content = $container.find('.cbd-container-content').first();

                    console.log('CBD PDF: - Expanding container', containerIndex + 1, 'in block', blockIndex + 1);

                    // Check if this specific container's content is collapsed
                    if (content.length > 0) {
                        var computedDisplay = window.getComputedStyle(content[0]).display;
                        var isHidden = computedDisplay === 'none' ||
                                     !content.is(':visible') ||
                                     content.css('display') === 'none';

                        if (isHidden) {
                            console.log('CBD PDF:   - Content was hidden, forcing visible');
                            collapsedStates.push({
                                element: content[0],
                                wasHidden: true,
                                originalDisplay: content[0].style.display,
                                originalVisibility: content[0].style.visibility,
                                originalMaxHeight: content[0].style.maxHeight,
                                originalOverflow: content[0].style.overflow
                            });

                            // Force expand with !important to override all styles - OLD WORKING METHOD
                            content[0].style.setProperty('display', 'block', 'important');
                            content[0].style.setProperty('visibility', 'visible', 'important');
                            content[0].style.setProperty('opacity', '1', 'important');
                            content[0].style.setProperty('max-height', 'none', 'important');
                            content[0].style.setProperty('overflow', 'visible', 'important');
                            content[0].style.setProperty('height', 'auto', 'important');
                        }
                    }
                });

                // Handle details elements
                var detailsElements = $block.find('details');
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
            });

            console.log('CBD PDF: Pre-expansion complete, waiting 350ms for animation...');

            // Wait 350ms for collapse animation to complete (like old working solution)
            setTimeout(function() {
                console.log('CBD PDF: Animation delay complete, now cloning blocks...');

                // NOW clone the blocks after they're expanded
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

                // Continue with PDF generation
                continueWithPDFGeneration();

            }, 350); // 350ms delay like old working solution

            // Store the continuation function
            function continueWithPDFGeneration() {

            console.log('CBD PDF: Wrapper created with', $wrapper.children().length, 'blocks');
            console.log('CBD PDF: Wrapper HTML length:', $wrapper.html().length);

            // SECOND PASS: Expand everything again after all blocks are assembled
            console.log('CBD PDF: Running second expansion pass on complete wrapper...');
            expandContent($wrapper);
            console.log('CBD PDF: Second expansion complete');

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

            // THIRD PASS: Expand after wrapper is in DOM (critical for proper rendering)
            console.log('CBD PDF: Running THIRD expansion pass (after DOM insertion)...');

            // Check which blocks are still collapsed
            var collapsedBlocks = $wrapper.find('.cbd-collapsed');
            console.log('CBD PDF: Found', collapsedBlocks.length, 'elements with .cbd-collapsed after DOM insertion');

            var collapsedContainers = $wrapper.find('[data-wp-interactive="container-block-designer"]').filter(function() {
                return $(this).hasClass('cbd-collapsed');
            });
            console.log('CBD PDF: Found', collapsedContainers.length, 'CBD containers still collapsed');

            // Log details of collapsed containers
            collapsedContainers.each(function(index) {
                var $container = $(this);
                console.log('CBD PDF: Collapsed container', index + 1, '- ID:', $container.attr('id'), 'Classes:', $container.attr('class'));

                // Check if content is hidden
                var $content = $container.find('.cbd-container-content, .cbd-content').first();
                if ($content.length > 0) {
                    var styles = $content.attr('style') || '';
                    var display = $content.css('display');
                    var visibility = $content.css('visibility');
                    console.log('CBD PDF: - Content display:', display, 'visibility:', visibility, 'has style attr:', styles.length > 0);
                }
            });

            // Run expansion again
            expandContent($wrapper);

            // Verify expansion worked
            var stillCollapsed = $wrapper.find('.cbd-collapsed');
            console.log('CBD PDF: After third pass,', stillCollapsed.length, 'elements still have .cbd-collapsed');

            // CRITICAL: Check actual computed styles of ALL content areas
            console.log('CBD PDF: Checking computed styles of content areas...');

            // IMPORTANT: Iterate through TOP-LEVEL blocks only (direct children of wrapper)
            $wrapper.children().each(function(blockIndex) {
                var $topBlock = $(this);
                console.log('CBD PDF: === Checking TOP-LEVEL Block', blockIndex + 1, '===');
                console.log('CBD PDF: Block', blockIndex + 1, 'classes:', $topBlock.attr('class'));

                // Find ALL interactive containers in this block (including nested)
                var $containers = $topBlock.find('[data-wp-interactive="container-block-designer"]');
                // Also check if the top block itself is a container
                if ($topBlock.is('[data-wp-interactive="container-block-designer"]')) {
                    $containers = $containers.add($topBlock);
                }

                console.log('CBD PDF: Block', blockIndex + 1, 'contains', $containers.length, 'container(s)');

                $containers.each(function(containerIndex) {
                    var $container = $(this);
                    console.log('CBD PDF: - Container', containerIndex + 1, 'in Block', blockIndex + 1);

                    // Find content areas in THIS specific container (not nested ones)
                    var $contentAreas = $container.children('.cbd-container-content, .cbd-content, .cbd-collapsible-content');

                    console.log('CBD PDF:   Found', $contentAreas.length, 'direct content area(s)');

                    $contentAreas.each(function(contentIndex) {
                        var $content = $(this);
                        var computed = window.getComputedStyle(this);

                        console.log('CBD PDF:   Content area', contentIndex + 1, ':');
                        console.log('CBD PDF:     - display:', computed.display);
                        console.log('CBD PDF:     - visibility:', computed.visibility);
                        console.log('CBD PDF:     - opacity:', computed.opacity);
                        console.log('CBD PDF:     - height:', computed.height);
                        console.log('CBD PDF:     - max-height:', computed.maxHeight);
                        console.log('CBD PDF:     - overflow:', computed.overflow);
                        console.log('CBD PDF:     - has inline style:', ($content.attr('style') || '').length > 0);

                        // If hidden, force it visible with maximum priority
                        if (computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0' || computed.maxHeight === '0px') {
                            console.warn('CBD PDF:     ⚠️ HIDDEN! Forcing visible with setProperty...');
                            this.style.setProperty('display', 'block', 'important');
                            this.style.setProperty('visibility', 'visible', 'important');
                            this.style.setProperty('opacity', '1', 'important');
                            this.style.setProperty('max-height', 'none', 'important');
                            this.style.setProperty('height', 'auto', 'important');
                            this.style.setProperty('overflow', 'visible', 'important');

                            // Verify the fix worked
                            var newComputed = window.getComputedStyle(this);
                            console.log('CBD PDF:     ✓ After fix - display:', newComputed.display, 'visibility:', newComputed.visibility);
                        } else {
                            console.log('CBD PDF:     ✓ Visible');
                        }
                    });
                });
            });

            console.log('CBD PDF: Third expansion complete');

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

                // Use html2canvas directly and create PDF with jsPDF
                // This bypasses html2pdf.js which seems to have issues

                // Check for jsPDF (try multiple locations)
                var jsPDF = window.jspdf && window.jspdf.jsPDF ? window.jspdf.jsPDF :
                            (window.jsPDF ? window.jsPDF : null);

                console.log('CBD PDF: window.jspdf:', typeof window.jspdf);
                console.log('CBD PDF: window.jspdf.jsPDF:', window.jspdf ? typeof window.jspdf.jsPDF : 'N/A');
                console.log('CBD PDF: window.jsPDF:', typeof window.jsPDF);
                console.log('CBD PDF: jsPDF resolved to:', !!jsPDF);
                console.log('CBD PDF: html2canvas available:', typeof html2canvas !== 'undefined');

                if (typeof html2canvas !== 'undefined' && jsPDF) {
                    console.log('CBD PDF: Using html2canvas + jsPDF directly...');

                    html2canvas($wrapper[0], {
                        scale: quality,
                        useCORS: true,
                        logging: false, // Disable for production
                        backgroundColor: '#ffffff',
                        scrollY: 0,
                        scrollX: 0
                    }).then(function(canvas) {
                        console.log('CBD PDF: html2canvas SUCCESS! Canvas size:', canvas.width, 'x', canvas.height);

                        // Create jsPDF instance
                        var pdf = new jsPDF({
                            orientation: 'portrait',
                            unit: 'mm',
                            format: 'a4'
                        });

                        // Calculate dimensions to fit A4
                        var imgWidth = 190; // A4 width minus margins (210 - 20)
                        var imgHeight = (canvas.height * imgWidth) / canvas.width;
                        var pageHeight = 277; // A4 height minus margins (297 - 20)

                        var heightLeft = imgHeight;
                        var position = 10; // Top margin

                        // Convert canvas to image data
                        var imgData = canvas.toDataURL('image/jpeg', 0.98);

                        // Add first page
                        pdf.addImage(imgData, 'JPEG', 10, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;

                        // Add additional pages if needed
                        while (heightLeft > 0) {
                            position = heightLeft - imgHeight + 10;
                            pdf.addPage();
                            pdf.addImage(imgData, 'JPEG', 10, position, imgWidth, imgHeight);
                            heightLeft -= pageHeight;
                        }

                        // Save PDF
                        var filename = 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf';
                        pdf.save(filename);

                        console.log('CBD PDF: PDF saved successfully via jsPDF!');

                        // Cleanup
                        $wrapper.remove();
                        $loadingMsg.remove();
                    }).catch(function(error) {
                        console.error('CBD PDF: html2canvas FAILED:', error);
                        $wrapper.remove();
                        $loadingMsg.remove();
                        alert('Fehler beim Canvas erstellen: ' + error.message);
                    });
                } else if (typeof html2canvas !== 'undefined') {
                    console.log('CBD PDF: jsPDF not available separately, using html2pdf');
                    // Fallback to html2pdf
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
                } else {
                    console.error('CBD PDF: Neither html2canvas nor html2pdf available!');
                    $wrapper.remove();
                    $loadingMsg.remove();
                    alert('PDF-Bibliotheken nicht geladen!');
                }
            }, 500);

            console.log('CBD PDF: html2pdf() will start after render delay...');
            return true;

        } catch (error) {
            alert('Fehler beim PDF erstellen: ' + error.message);
            return false;
        }
    }

    // Helper: Expand collapsed content - RECURSIVE expansion for multi-level nesting
    function expandContent($element, depth) {
        var $ = window.jQuery || window.$;
        depth = depth || 0;

        if (depth === 0) {
            console.log('CBD PDF: Starting RECURSIVE expansion...');
        }
        console.log('CBD PDF: Expanding at depth', depth);

        // STEP 1: Remove ALL collapsed classes at this level and below
        $element.find('.cbd-collapsed').addBack('.cbd-collapsed').removeClass('cbd-collapsed');

        // STEP 2: Expand ALL containers by data attribute (RECURSIVE!)
        var $allContainers = $element.find('[data-wp-interactive="container-block-designer"]');
        if ($element.is('[data-wp-interactive="container-block-designer"]')) {
            $allContainers = $allContainers.add($element);
        }

        console.log('CBD PDF: Found', $allContainers.length, 'interactive containers at depth', depth);

        $allContainers.each(function(index) {
            var $container = $(this);
            console.log('CBD PDF: - Processing container', index + 1, 'at depth', depth, '- ID:', $container.attr('id'));

            // Remove collapsed class
            $container.removeClass('cbd-collapsed');

            // Fix data-wp-context
            if ($container.attr('data-wp-context')) {
                try {
                    var context = JSON.parse($container.attr('data-wp-context'));
                    if (context.isCollapsed !== false) {
                        console.log('CBD PDF:   Setting isCollapsed = false in context');
                        context.isCollapsed = false;
                        $container.attr('data-wp-context', JSON.stringify(context));
                    }
                } catch(e) {
                    console.warn('CBD PDF:   Could not parse wp-context:', e);
                }
            }

            // Find direct content areas (not nested ones)
            var $directContent = $container.children('.cbd-container-content, .cbd-content, .cbd-collapsible-content');
            console.log('CBD PDF:   Found', $directContent.length, 'direct content area(s)');

            $directContent.each(function() {
                var $content = $(this);

                // Force visible with setProperty for maximum priority
                this.style.setProperty('display', 'block', 'important');
                this.style.setProperty('visibility', 'visible', 'important');
                this.style.setProperty('opacity', '1', 'important');
                this.style.setProperty('max-height', 'none', 'important');
                this.style.setProperty('height', 'auto', 'important');
                this.style.setProperty('overflow', 'visible', 'important');

                // Remove problematic attributes
                $content.removeAttr('aria-hidden');
                $content.removeClass('cbd-collapsed');

                // RECURSIVE: Check for nested containers inside this content
                var $nestedContainers = $content.find('[data-wp-interactive="container-block-designer"]');
                if ($nestedContainers.length > 0) {
                    console.log('CBD PDF:   Found', $nestedContainers.length, 'nested container(s) - recursing...');
                    // Recurse into nested containers
                    expandContent($content, depth + 1);
                }
            });
        });

        // STEP 3: Force expand ALL .cbd-container elements
        $element.find('.cbd-container').addBack('.cbd-container').each(function() {
            $(this).removeClass('cbd-collapsed');
        });

        // STEP 4: Force visible on ALL content areas using setProperty
        var contentSelectors = [
            '.cbd-container-content',
            '.cbd-content',
            '.cbd-collapsible-content'
        ];

        $.each(contentSelectors, function(i, selector) {
            $element.find(selector).each(function() {
                this.style.setProperty('display', 'block', 'important');
                this.style.setProperty('visibility', 'visible', 'important');
                this.style.setProperty('opacity', '1', 'important');
                this.style.setProperty('max-height', 'none', 'important');
                this.style.setProperty('overflow', 'visible', 'important');
                this.style.setProperty('height', 'auto', 'important');
            });
        });

        // STEP 5: Expand details elements
        $element.find('details').each(function() {
            this.open = true;
        });

        // STEP 6: Remove ALL aria-hidden
        $element.find('[aria-hidden="true"]').removeAttr('aria-hidden');

        if (depth === 0) {
            console.log('CBD PDF: RECURSIVE expansion complete');
        }
    }

    // Start loading
    loadFromCDN();
})();
