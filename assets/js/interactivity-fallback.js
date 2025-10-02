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

    // Global flag to track if Interactivity API is active
    let interactivityAPIActive = false;

    // Check immediately and after a delay
    function checkInteractivityAPI() {
        if (typeof window.wp !== 'undefined' && typeof window.wp.interactivity !== 'undefined') {
            interactivityAPIActive = true;
            return true;
        }
        return false;
    }

    // Warte bis DOM bereit ist
    $(document).ready(function() {
        console.log('[CBD Fallback] Initializing Interactivity API fallback...');

        // Initial check
        if (checkInteractivityAPI()) {
            console.log('[CBD Fallback] WordPress Interactivity API is active, skipping fallback');
            return;
        }

        // Check again after short delay (in case Interactivity API loads later)
        setTimeout(function() {
            if (checkInteractivityAPI()) {
                console.log('[CBD Fallback] WordPress Interactivity API loaded after init, disabling fallback');
                interactivityAPIActive = true;
            }
        }, 100);

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
                // WICHTIG: Nur das DIREKTE Content-Element, nicht verschachtelte!
                const $content = $container.children('.cbd-container-block').children('.cbd-container-content');
                if ($content.length) {
                    $content.attr('aria-hidden', context.isCollapsed ? 'true' : 'false');
                    $content.attr('role', 'region');
                }

                // Set initial collapsed state
                if (context.isCollapsed) {
                    $content.addClass('cbd-collapsed');
                }

                // Initialize icon states
                // WICHTIG: Nur das DIREKTE Icon, nicht verschachtelte!
                const $collapseIcon = $container.children('.cbd-action-buttons').find('.cbd-collapse-toggle .dashicons');
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
            // WICHTIG: Sofort stoppen damit Event nicht zu Parent-Containern bubblet
            e.preventDefault();
            e.stopPropagation();

            // Runtime check: Skip if Interactivity API is now active
            if (interactivityAPIActive || checkInteractivityAPI()) {
                console.log('[CBD Fallback] Interactivity API is active, skipping jQuery handler');
                return;
            }

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            const context = $container.data('cbd-context') || {};

            // WICHTIG: Nur das DIREKTE Content-Element dieses Containers, nicht verschachtelte
            // Fallback-Strategie: Versuche mehrere Selektoren
            let $content = $container.children('.cbd-container-content');
            if ($content.length === 0) {
                $content = $container.find('> .cbd-container-block > .cbd-container-content');
            }
            if ($content.length === 0) {
                $content = $container.find('.cbd-container-content').first();
            }
            const $icon = $button.find('.dashicons');

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
            // Runtime check: Skip if Interactivity API is now active
            if (interactivityAPIActive || checkInteractivityAPI()) {
                console.log('[CBD Fallback] Interactivity API is active, skipping jQuery handler');
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            const context = $container.data('cbd-context') || {};
            // WICHTIG: Nur das DIREKTE Content-Element
            const $content = $container.children('.cbd-container-block').children('.cbd-container-content');
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
            // Runtime check: Skip if Interactivity API is now active
            if (interactivityAPIActive || checkInteractivityAPI()) {
                console.log('[CBD Fallback] Interactivity API is active, skipping jQuery handler');
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            const context = $container.data('cbd-context') || {};
            const $containerBlock = $container.children('.cbd-container-block');
            const $icon = $button.find('.dashicons');
            // WICHTIG: Nur das DIREKTE Content-Element
            const $content = $containerBlock.children('.cbd-container-content');

            console.log('[CBD Fallback] Create screenshot:', $container.attr('id'));

            // Check if html2canvas is available
            if (typeof html2canvas === 'undefined') {
                console.error('[CBD Fallback] html2canvas not loaded');
                return;
            }

            // Check if containerBlock exists
            if (!$containerBlock.length) {
                console.error('[CBD Fallback] .cbd-container-block not found');
                return;
            }

            console.log('[CBD Fallback] Starting html2canvas...');

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
                console.log('[CBD Fallback] Running html2canvas on element:', $containerBlock[0]);

                // Buttons ausblenden für Screenshot
                const $actionButtons = $container.find('.cbd-action-buttons');
                $actionButtons.css({
                    'visibility': 'hidden !important',
                    'opacity': '0 !important'
                });

                // Kurze Verzögerung damit DOM aktualisiert wird
                setTimeout(function() {
                    html2canvas($containerBlock[0], {
                        useCORS: true,
                        allowTaint: false,
                        scale: 2,
                        logging: false,
                        backgroundColor: null
                    }).then(function(canvas) {
                        // Buttons wieder einblenden
                        $actionButtons.css({
                            'visibility': '',
                            'opacity': ''
                        });

                        // ==============================================
                        // TIER 1: Clipboard API (iOS 13.4+, Chrome, Firefox)
                        // ==============================================
                        if (navigator.clipboard && navigator.clipboard.write) {
                            // Safari/iOS FIX: Promise direkt an ClipboardItem übergeben
                            // NICHT vorher awaiten, sonst verliert Safari die User-Gesture
                            const blobPromise = new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                            const item = new ClipboardItem({ 'image/png': blobPromise });

                            navigator.clipboard.write([item])
                                .then(function() {
                                    console.log('[CBD Fallback] ✅ Clipboard: Screenshot copied to clipboard');
                                    showSuccess(context, wasCollapsed, $content, $button, $icon, $container);
                                })
                                .catch(function(err) {
                                    console.warn('[CBD Fallback] ❌ Clipboard failed:', err);
                                    // Clipboard failed, erstelle Blob für Fallback
                                    canvas.toBlob(function(blob) {
                                        if (!blob) {
                                            console.error('[CBD Fallback] Failed to create blob');
                                            downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container);
                                            return;
                                        }
                                        // Try Tier 2: Web Share API
                                        tryWebShare(blob, canvas, context, wasCollapsed, $content, $button, $icon, $container);
                                    }, 'image/png');
                                });
                            return;
                        }

                        // Clipboard not available, erstelle Blob für Fallback
                        console.warn('[CBD Fallback] Clipboard API not available');
                        canvas.toBlob(function(blob) {
                            if (!blob) {
                                console.error('[CBD Fallback] Failed to create blob');
                                downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container);
                                return;
                            }
                            tryWebShare(blob, canvas, context, wasCollapsed, $content, $button, $icon, $container);
                        }, 'image/png');

                    // ==============================================
                    // TIER 2: Web Share API (iOS 15+, Safari)
                    // ==============================================
                    function tryWebShare(blob, canvas, context, wasCollapsed, $content, $button, $icon, $container) {
                        // Check if Web Share API with files is supported (iOS/Safari)
                        const file = new File([blob], 'cbd-screenshot-' + Date.now() + '.png', { type: 'image/png' });

                        if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                            navigator.share({
                                files: [file],
                                title: 'Container Block Screenshot'
                            })
                            .then(function() {
                                console.log('[CBD Fallback] ✅ Web Share: Screenshot shared via iOS Share Sheet');
                                showSuccess(context, wasCollapsed, $content, $button, $icon, $container);
                            })
                            .catch(function(err) {
                                // User cancelled or error
                                if (err.name === 'AbortError') {
                                    console.log('[CBD Fallback] ℹ️ Web Share: User cancelled');
                                    resetButton(context, $button, $icon, $container);
                                } else {
                                    console.warn('[CBD Fallback] ❌ Web Share failed:', err);
                                    // Fallback to download
                                    downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container);
                                }
                            });
                            return;
                        }

                        // Web Share not available, use download
                        console.warn('[CBD Fallback] Web Share API not available');
                        downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container);
                    }

                    // ==============================================
                    // TIER 3: Download Fallback (All browsers)
                    // ==============================================
                    function downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container) {
                        const link = document.createElement('a');
                        link.download = 'cbd-container-' + context.blockId + '-' + Date.now() + '.png';
                        link.href = canvas.toDataURL('image/png');
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        console.log('[CBD Fallback] ⬇️ Download: Screenshot downloaded');
                        showSuccess(context, wasCollapsed, $content, $button, $icon, $container);
                    }

                    // ==============================================
                    // Helper Functions
                    // ==============================================
                    function showSuccess(context, wasCollapsed, $content, $button, $icon, $container) {
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

                        // Reset after 2 seconds
                        setTimeout(function() {
                            context.screenshotSuccess = false;
                            $container.data('cbd-context', context);
                            $icon.removeClass('dashicons-yes-alt').addClass('dashicons-camera');
                        }, 2000);
                    }

                    function resetButton(context, $button, $icon, $container) {
                        context.screenshotLoading = false;
                        $container.data('cbd-context', context);
                        $button.prop('disabled', false);
                        $icon.removeClass('dashicons-update-alt').addClass('dashicons-camera');
                    }

                    }).catch(function(error) {
                        console.error('[CBD Fallback] Screenshot failed:', error);

                        // Buttons wieder einblenden auch bei Fehler
                        $actionButtons.css({
                            'visibility': '',
                            'opacity': ''
                        });

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
                }, 50); // Verzögerung für Button-Ausblendung
            }, wasCollapsed ? 350 : 50);
        });

        // Initialize all containers on page load
        initializeContainers();

        console.log('[CBD Fallback] Initialization complete');
    });

})(jQuery);