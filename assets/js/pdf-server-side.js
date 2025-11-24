/**
 * Server-Side PDF Generation for Container Block Designer
 * Expands all blocks, sends HTML to server, receives PDF
 */

(function() {
    'use strict';

    var $ = window.jQuery || window.$;

    if (!$) {
        console.error('CBD PDF: jQuery not available');
        return;
    }

    // Create global export function
    window.cbdPDFExportServerSide = function(containerBlocks) {
        console.log('CBD PDF Server: Starting export');
        console.log('CBD PDF Server: containerBlocks type:', typeof containerBlocks);
        console.log('CBD PDF Server: containerBlocks.jquery:', containerBlocks.jquery);
        console.log('CBD PDF Server: Is array?', Array.isArray(containerBlocks));

        // Convert to jQuery object if it's an array of jQuery elements
        if (Array.isArray(containerBlocks)) {
            console.log('CBD PDF Server: Converting array to jQuery collection');
            // containerBlocks is an array of jQuery objects - merge them into one collection
            var $merged = $();
            for (var i = 0; i < containerBlocks.length; i++) {
                $merged = $merged.add(containerBlocks[i]);
            }
            containerBlocks = $merged;
        } else if (!containerBlocks.jquery) {
            // Not a jQuery object and not an array - try to wrap it
            containerBlocks = $(containerBlocks);
        }

        console.log('CBD PDF Server: Starting export with', containerBlocks.length, 'blocks');

        if (containerBlocks.length === 0) {
            alert('Keine Container-Blöcke zum Exportieren gefunden.');
            return false;
        }

        // Show loading message
        var $loadingMsg = $('<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px;border-radius:8px;z-index:9999999;box-shadow:0 4px 20px rgba(0,0,0,0.3);text-align:center;"><h3 style="margin:0 0 10px 0;">PDF wird erstellt...</h3><p style="margin:0;color:#666;">Bitte warten Sie einen Moment.</p></div>');
        $('body').append($loadingMsg);

        // STEP 1: Expand ALL blocks IN-PLACE (like old working solution)
        console.log('CBD PDF Server: Expanding ALL blocks in-place...');
        var collapsedStates = [];

        containerBlocks.each(function(blockIndex) {
            var $block = $(this);
            console.log('CBD PDF Server: Expanding block', blockIndex + 1);

            // Find ALL container blocks (including nested ones!)
            var allContainerBlocks = $block.find('[data-wp-interactive="container-block-designer"]');
            if ($block.is('[data-wp-interactive="container-block-designer"]')) {
                allContainerBlocks = allContainerBlocks.add($block);
            }

            console.log('CBD PDF Server: Block', blockIndex + 1, 'has', allContainerBlocks.length, 'containers');

            // Expand EACH container block (including nested ones)
            allContainerBlocks.each(function() {
                var $container = $(this);
                var content = $container.find('.cbd-container-content').first();

                if (content.length > 0) {
                    var computedDisplay = window.getComputedStyle(content[0]).display;
                    var isHidden = computedDisplay === 'none' ||
                                 !content.is(':visible') ||
                                 content.css('display') === 'none';

                    if (isHidden) {
                        console.log('CBD PDF Server: - Expanding hidden content');
                        collapsedStates.push({
                            element: content[0],
                            wasHidden: true,
                            originalDisplay: content[0].style.display,
                            originalVisibility: content[0].style.visibility,
                            originalMaxHeight: content[0].style.maxHeight,
                            originalOverflow: content[0].style.overflow
                        });

                        // Force expand
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

        console.log('CBD PDF Server: Expansion complete, waiting 350ms for animation...');

        // STEP 2: Wait for animation (350ms like old solution)
        setTimeout(function() {
            console.log('CBD PDF Server: Collecting HTML from expanded blocks...');

            // Collect HTML from each block
            var blocksHTML = [];

            containerBlocks.each(function(index) {
                var $block = $(this);

                // Clone the block
                var $clone = $block.clone();

                // Remove action buttons, menus, etc.
                $clone.find('.cbd-action-buttons').remove();
                $clone.find('.cbd-collapse-toggle').remove();
                $clone.find('.cbd-header-menu').remove();
                $clone.find('.cbd-container-number').remove();
                $clone.find('.cbd-selection-menu').remove();

                // Get the HTML
                var html = $clone.html();
                blocksHTML.push(html);

                console.log('CBD PDF Server: Block', index + 1, 'HTML length:', html.length);
            });

            console.log('CBD PDF Server: Total blocks collected:', blocksHTML.length);

            // STEP 3: Restore original states
            restoreOriginalStates(collapsedStates);

            // STEP 4: Send to server via AJAX
            console.log('CBD PDF Server: Sending to server...');

            $.ajax({
                url: cbdPDFData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_generate_pdf',
                    nonce: cbdPDFData.nonce,
                    blocks: blocksHTML,
                    filename: 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf'
                },
                success: function(response) {
                    $loadingMsg.remove();

                    if (response.success) {
                        console.log('CBD PDF Server: PDF generated successfully!');
                        console.log('CBD PDF Server: Download URL:', response.data.url);

                        // Trigger download
                        var link = document.createElement('a');
                        link.href = response.data.url;
                        link.download = response.data.filename;
                        link.click();

                        // Show success message
                        alert('PDF erfolgreich erstellt!');
                    } else {
                        console.error('CBD PDF Server: Error:', response.data.message);
                        alert('Fehler beim PDF erstellen: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $loadingMsg.remove();
                    console.error('CBD PDF Server: AJAX error:', error);
                    alert('Fehler beim PDF erstellen: ' + error);
                }
            });

        }, 350); // 350ms delay like old solution

        return true;
    };

    // Helper function to restore original states
    function restoreOriginalStates(collapsedStates) {
        console.log('CBD PDF Server: Restoring original states...');

        for (var i = 0; i < collapsedStates.length; i++) {
            var state = collapsedStates[i];

            if (state.wasDetails) {
                state.element.open = state.originalOpen;
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

        console.log('CBD PDF Server: States restored');
    }

    console.log('CBD PDF Server: Server-side PDF export function loaded');

})();
