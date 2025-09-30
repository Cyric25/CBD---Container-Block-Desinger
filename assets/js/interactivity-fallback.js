/**
 * Container Block Designer - Interactivity API Fallback
 * jQuery-basierter Fallback für Interactivity API Directives
 *
 * Dieses Script simuliert das Verhalten der Interactivity API für den Fall,
 * dass die WordPress Interactivity API nicht geladen wird.
 *
 * @package ContainerBlockDesigner
 * @since 2.8.0
 */

(function($) {
    'use strict';

    // Warte bis DOM bereit ist
    $(document).ready(function() {
        console.log('[CBD Fallback] Initializing Interactivity API fallback...');

        // Prüfe ob WordPress Interactivity API bereits aktiv ist
        if (typeof window.wp !== 'undefined' && typeof window.wp.interactivity !== 'undefined') {
            console.log('[CBD Fallback] WordPress Interactivity API is active, skipping fallback');
            return;
        }

        console.log('[CBD Fallback] Using jQuery fallback for interactivity');

        /**
         * Initialisiere alle Container-Blöcke
         */
        function initializeContainers() {
            $('[data-wp-interactive="container-block-designer"]').each(function() {
                const $container = $(this);
                const containerId = $container.attr('id');

                // Parse context aus data-wp-context
                let context = {};
                try {
                    const contextStr = $container.attr('data-wp-context');
                    if (contextStr) {
                        context = JSON.parse(contextStr);
                    }
                } catch (e) {
                    console.error('[CBD Fallback] Failed to parse context:', e);
                }

                console.log('[CBD Fallback] Initializing container:', containerId, context);

                // Set initial state
                $container.data('cbd-context', context);

                // Set initial aria attributes
                const $content = $container.find('.cbd-container-content');
                if ($content.length) {
                    $content.attr('aria-hidden', context.isCollapsed ? 'true' : 'false');
                    $content.attr('role', 'region');
                }

                // Set initial collapsed state
                if (context.isCollapsed) {
                    $content.addClass('cbd-collapsed');
                }

                // Initialize icon states
                const $collapseIcon = $container.find('.cbd-collapse-toggle .dashicons');
                if (context.isCollapsed) {
                    $collapseIcon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                } else {
                    $collapseIcon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                }
            });
        }

        /**
         * Toggle Collapse Action
         */
        $(document).on('click', '[data-wp-on--click="actions.toggleCollapse"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            const context = $container.data('cbd-context') || {};
            const $content = $container.find('.cbd-container-content');
            const $icon = $button.find('.dashicons');

            console.log('[CBD Fallback] Toggle collapse:', $container.attr('id'));

            // Toggle state
            context.isCollapsed = !context.isCollapsed;
            $container.data('cbd-context', context);

            // Update UI
            if (context.isCollapsed) {
                $content.slideUp(300, function() {
                    $content.addClass('cbd-collapsed');
                });
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                $content.attr('aria-hidden', 'true');
                $button.attr('aria-expanded', 'false');
            } else {
                $content.slideDown(300, function() {
                    $content.removeClass('cbd-collapsed');
                });
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $content.attr('aria-hidden', 'false');
                $button.attr('aria-expanded', 'true');
            }
        });

        /**
         * Copy Text Action
         */
        $(document).on('click', '[data-wp-on--click="actions.copyText"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            const context = $container.data('cbd-context') || {};
            const $content = $container.find('.cbd-container-content');
            const $icon = $button.find('.dashicons');

            console.log('[CBD Fallback] Copy text:', $container.attr('id'));

            if (!$content.length) {
                console.warn('[CBD Fallback] Content element not found');
                return;
            }

            const textToCopy = $content.text().trim();

            if (!textToCopy) {
                console.warn('[CBD Fallback] No text to copy');
                return;
            }

            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy)
                    .then(function() {
                        console.log('[CBD Fallback] Text copied successfully');

                        // Update context
                        context.copySuccess = true;
                        $container.data('cbd-context', context);

                        // Visual feedback
                        $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes-alt');

                        // Reset after 2 seconds
                        setTimeout(function() {
                            context.copySuccess = false;
                            $container.data('cbd-context', context);
                            $icon.removeClass('dashicons-yes-alt').addClass('dashicons-clipboard');
                        }, 2000);
                    })
                    .catch(function(err) {
                        console.error('[CBD Fallback] Copy failed:', err);
                        context.copyError = true;
                        $container.data('cbd-context', context);
                    });
            } else {
                console.error('[CBD Fallback] Clipboard API not available');
            }
        });

        /**
         * Screenshot Action
         */
        $(document).on('click', '[data-wp-on--click="actions.createScreenshot"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            const context = $container.data('cbd-context') || {};
            const $containerBlock = $container.find('.cbd-container-block');
            const $icon = $button.find('.dashicons');
            const $content = $container.find('.cbd-container-content');

            console.log('[CBD Fallback] Create screenshot:', $container.attr('id'));

            // Check if html2canvas is available
            if (typeof html2canvas === 'undefined') {
                console.error('[CBD Fallback] html2canvas not loaded');
                return;
            }

            // Set loading state
            context.screenshotLoading = true;
            $container.data('cbd-context', context);
            $button.prop('disabled', true);
            $icon.removeClass('dashicons-camera').addClass('dashicons-update-alt');

            // Remember if was collapsed
            const wasCollapsed = context.isCollapsed;

            // Expand if collapsed
            if (wasCollapsed) {
                $content.show();
            }

            // Small delay for animation
            setTimeout(function() {
                html2canvas($containerBlock[0], {
                    useCORS: true,
                    allowTaint: false,
                    scale: 2,
                    logging: false,
                    backgroundColor: null
                }).then(function(canvas) {
                    // Create download link
                    const link = document.createElement('a');
                    link.download = 'cbd-container-' + context.blockId + '-' + Date.now() + '.png';
                    link.href = canvas.toDataURL('image/png');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Collapse again if was collapsed
                    if (wasCollapsed) {
                        $content.hide();
                    }

                    // Success feedback
                    context.screenshotLoading = false;
                    context.screenshotSuccess = true;
                    $container.data('cbd-context', context);
                    $button.prop('disabled', false);
                    $icon.removeClass('dashicons-update-alt').addClass('dashicons-yes-alt');

                    console.log('[CBD Fallback] Screenshot created successfully');

                    // Reset after 2 seconds
                    setTimeout(function() {
                        context.screenshotSuccess = false;
                        $container.data('cbd-context', context);
                        $icon.removeClass('dashicons-yes-alt').addClass('dashicons-camera');
                    }, 2000);

                }).catch(function(error) {
                    console.error('[CBD Fallback] Screenshot failed:', error);

                    // Error state
                    context.screenshotLoading = false;
                    context.screenshotError = true;
                    $container.data('cbd-context', context);
                    $button.prop('disabled', false);
                    $icon.removeClass('dashicons-update-alt').addClass('dashicons-camera');

                    // Reset error after 2 seconds
                    setTimeout(function() {
                        context.screenshotError = false;
                        $container.data('cbd-context', context);
                    }, 2000);
                });
            }, wasCollapsed ? 350 : 50);
        });

        // Initialize all containers on page load
        initializeContainers();

        console.log('[CBD Fallback] Initialization complete');
    });

})(jQuery);