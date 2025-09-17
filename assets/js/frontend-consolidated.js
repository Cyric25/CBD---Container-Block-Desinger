/**
 * Container Block Designer - Unified Frontend JavaScript
 * Version: 2.6.1-FIXED
 * All frontend functionality consolidated for better performance and consistency
 * Fixed: Double event handler causing collapse issues
 */

(function($) {
    'use strict';

    // Global CBD Frontend object
    window.CBDFrontend = {
        config: {
            animationSpeed: 300,
            localStorage: true,
            debug: false
        },

        // Initialize when DOM is ready
        init: function() {
            if (this.config.debug) {
                console.log('CBD Frontend: Initializing unified version');
            }
            this.initContainerBlocks();
            this.bindGlobalEvents();
            this.loadLibraries();
        },

        /**
         * Initialize all container blocks
         */
        initContainerBlocks: function() {
            $('.cbd-container').each(function() {
                var $container = $(this);
                var blockId = $container.data('block-id');
                
                if (!blockId) {
                    blockId = 'cbd-' + Math.random().toString(36).substr(2, 9);
                    $container.data('block-id', blockId);
                }

                // Add unique ID if not present
                if (!$container.attr('id')) {
                    $container.attr('id', 'cbd-container-' + blockId);
                }
                
                // Initialize non-collapse features only
                CBDFrontend.initCopyText($container);
                CBDFrontend.initScreenshot($container);
                CBDFrontend.initNumbering($container);
                CBDFrontend.initIcon($container);
                CBDFrontend.initAccessibility($container);
                // NOTE: Collapse is handled by global event handler only
            });
        },

        /**
         * Initialize collapsible functionality
         */
        initCollapsible: function($container) {
            var collapseData = $container.data('collapse');
            if (!collapseData || !collapseData.enabled) return;

            // Find header (can be inside or outside content block)
            var $header = $container.find('.cbd-header');
            if (!$header.length) {
                // Create header inside content block at the top
                var $contentBlock = $container.find('.cbd-container-block');
                if ($contentBlock.length) {
                    $contentBlock.prepend('<div class="cbd-header"></div>');
                    $header = $contentBlock.find('.cbd-header');
                } else {
                    $container.prepend('<div class="cbd-header"></div>');
                    $header = $container.find('.cbd-header');
                }
            }

            // Create toggle button
            var toggleHtml = '<button class="cbd-collapse-toggle" ' +
                'aria-expanded="' + (collapseData.defaultState !== 'collapsed') + '" ' +
                'aria-controls="' + $container.attr('id') + '-content">' +
                '<span class="cbd-toggle-icon">' +
                '<i class="dashicons dashicons-arrow-' + (collapseData.defaultState === 'collapsed' ? 'down' : 'up') + '-alt2"></i>' +
                '</span>' +
                '<span class="cbd-toggle-text">' + (collapseData.label || 'Toggle') + '</span>' +
                '</button>';

            if (!$header.find('.cbd-collapse-toggle').length) {
                $header.append(toggleHtml);
                
                // DIRECT button binding instead of delegation
                var $toggleButton = $header.find('.cbd-collapse-toggle');
                var containerId = $container.attr('id');
                
                // Remove any existing handlers on this specific button
                $toggleButton.off('click.cbd-toggle');
                
                // Bind directly to this button
                $toggleButton.on('click.cbd-toggle', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('Direct toggle clicked for container:', containerId);
                    
                    var $thisContainer = $('#' + containerId);
                    var $contentToHide = $thisContainer.find('.cbd-container-content');
                    
                    console.log('Found content elements:', $contentToHide.length);
                    console.log('Container still exists:', $thisContainer.length);
                    
                    if ($contentToHide.length === 0) {
                        console.log('No content found - aborting');
                        return;
                    }
                    
                    // CRITICAL: Ensure container and its parts stay visible
                    $thisContainer.css('display', 'block');
                    $thisContainer.find('.cbd-content').css('display', 'block');
                    $thisContainer.find('.cbd-container-block').css('display', 'block');
                    $thisContainer.find('.cbd-header').css('display', 'block');
                    $(this).css('display', 'flex'); // Keep button visible
                    
                    if ($contentToHide.is(':visible')) {
                        // Hide content only
                        console.log('Hiding content');
                        $contentToHide.hide();
                        $thisContainer.addClass('cbd-collapsed');
                        $(this).find('.dashicons')
                            .removeClass('dashicons-arrow-up-alt2')
                            .addClass('dashicons-arrow-down-alt2');
                    } else {
                        // Show content
                        console.log('Showing content');
                        $contentToHide.show();
                        $thisContainer.removeClass('cbd-collapsed');
                        $(this).find('.dashicons')
                            .removeClass('dashicons-arrow-down-alt2')
                            .addClass('dashicons-arrow-up-alt2');
                    }
                    
                    console.log('Toggle complete, container visible:', $thisContainer.is(':visible'));
                });
                
                console.log('Direct button handler attached for:', containerId);
            }

            // Find content wrapper (should already exist)
            var $content = $container.find('.cbd-content');
            if (!$content.length) {
                // If no content wrapper exists, create one wrapping everything except external controls
                $container.children().not('.cbd-icon, .cbd-actions').wrapAll('<div class="cbd-content" id="' + $container.attr('id') + '-content"></div>');
                $content = $container.find('.cbd-content');
            }

            // Set initial state - only if not already set by backend
            if (collapseData.defaultState === 'collapsed' && !$container.hasClass('cbd-collapsed')) {
                $container.addClass('cbd-collapsed');
                // Simply hide the content wrapper
                var $contentWrapper = $container.find('.cbd-container-content');
                if ($contentWrapper.length) {
                    $contentWrapper.hide();
                }
            } else if ($container.hasClass('cbd-collapsed')) {
                // If already collapsed by backend, ensure content is hidden
                var $contentWrapper = $container.find('.cbd-container-content');
                if ($contentWrapper.length && $contentWrapper.is(':visible')) {
                    $contentWrapper.hide();
                }
            }
        },

        /**
         * Initialize copy text functionality
         */
        initCopyText: function($container) {
            var copyData = $container.data('copy-text');
            if (!copyData || !copyData.enabled) return;

            var buttonHtml = '<button class="cbd-copy-text" ' +
                'title="' + (copyData.tooltip || 'Text kopieren') + '" ' +
                'data-container-id="' + $container.attr('id') + '">' +
                '<i class="dashicons dashicons-clipboard"></i>' +
                '<span class="cbd-copy-label">' + (copyData.label || 'Kopieren') + '</span>' +
                '</button>';

            // Add to header or create action bar
            var $target = $container.find('.cbd-header, .cbd-actions').first();
            if (!$target.length) {
                $container.prepend('<div class="cbd-actions"></div>');
                $target = $container.find('.cbd-actions');
            }

            if (!$target.find('.cbd-copy-text').length) {
                $target.append(buttonHtml);
            }
        },

        /**
         * Initialize screenshot functionality
         */
        initScreenshot: function($container) {
            var screenshotData = $container.data('screenshot');
            if (!screenshotData || !screenshotData.enabled) return;

            var buttonHtml = '<button class="cbd-screenshot" ' +
                'title="' + (screenshotData.tooltip || 'Screenshot erstellen') + '" ' +
                'data-container-id="' + $container.attr('id') + '">' +
                '<i class="dashicons dashicons-camera"></i>' +
                '<span class="cbd-screenshot-label">' + (screenshotData.label || 'Screenshot') + '</span>' +
                '</button>';

            var $target = $container.find('.cbd-header, .cbd-actions').first();
            if (!$target.length) {
                $container.prepend('<div class="cbd-actions"></div>');
                $target = $container.find('.cbd-actions');
            }

            if (!$target.find('.cbd-screenshot').length) {
                $target.append(buttonHtml);
            }
        },

        /**
         * Initialize numbering functionality
         */
        initNumbering: function($container) {
            var numberingData = $container.data('numbering');
            if (!numberingData || !numberingData.enabled) return;

            var format = numberingData.format || 'numeric';
            var startFrom = numberingData.startFrom || 1;
            var prefix = numberingData.prefix || '';
            var suffix = numberingData.suffix || '';
            var selector = numberingData.selector || 'h2, h3, h4';

            $container.find(selector).each(function(index) {
                var $item = $(this);
                var number;
                
                switch(format) {
                    case 'numeric':
                        number = startFrom + index;
                        break;
                    case 'alphabetic':
                        number = String.fromCharCode(64 + startFrom + index);
                        break;
                    case 'roman':
                        number = CBDFrontend.toRoman(startFrom + index);
                        break;
                    default:
                        number = startFrom + index;
                }
                
                if (!$item.find('.cbd-number').length) {
                    $item.prepend('<span class="cbd-number">' + prefix + number + suffix + '</span> ');
                }
            });
        },

        /**
         * Initialize icon functionality
         */
        initIcon: function($container) {
            var iconData = $container.data('icon');
            if (!iconData || !iconData.enabled) return;

            var iconHtml = '<span class="cbd-icon ' + (iconData.position || 'top-left') + '" ' +
                'style="color: ' + (iconData.color || '#333') + ';">' +
                '<i class="dashicons ' + (iconData.value || 'dashicons-admin-generic') + '"></i>' +
                '</span>';

            if (!$container.find('.cbd-icon').length) {
                $container.prepend(iconHtml);
            }

            // Add animation if specified
            if (iconData.animation) {
                $container.find('.cbd-icon').addClass('cbd-icon-' + iconData.animation);
            }
        },

        /**
         * Initialize accessibility features
         */
        initAccessibility: function($container) {
            // Add keyboard navigation
            $container.attr('tabindex', '0');
            
            // Add ARIA labels
            var title = $container.find('h1, h2, h3, h4, h5, h6').first().text();
            if (title) {
                $container.attr('aria-label', title);
            }

            // Add click handler to show action buttons
            $container.on('click focus', function() {
                // Add selected class to show action buttons
                $('.cbd-container').removeClass('cbd-selected');
                $(this).addClass('cbd-selected');
            });

            // Remove selected class when clicking elsewhere
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.cbd-container').length) {
                    $('.cbd-container').removeClass('cbd-selected');
                }
            });
        },

        /**
         * Bind global events
         */
        bindGlobalEvents: function() {
            // Remove any old handlers
            $(document).off('click.cbd-frontend');
            
            // MINIMAL collapse handler - absolute minimum code
            $(document).on('click.cbd-frontend', '.cbd-collapse-toggle', function(e) {
                e.preventDefault();
                
                console.log('CBD Toggle: Button clicked');
                
                var $container = $(this).closest('.cbd-container');
                var $contentToToggle = $container.find('.cbd-container-content');
                
                console.log('CBD Toggle: Found content to toggle:', $contentToToggle.length);
                
                if ($contentToToggle.length === 0) {
                    console.log('CBD Toggle: No content found - aborting');
                    return;
                }
                
                // CRITICAL: Force container visible FIRST
                $container.css({
                    'display': 'block',
                    'visibility': 'visible'
                });
                $container.find('.cbd-container-block').css('display', 'block');
                $container.find('.cbd-header').css('display', 'block');
                $(this).css('display', 'flex');
                
                // Simple toggle of ONLY the content
                if ($contentToToggle.is(':visible')) {
                    console.log('CBD Toggle: Hiding content');
                    $contentToToggle.hide();
                    $(this).find('.dashicons')
                        .removeClass('dashicons-arrow-up-alt2')
                        .addClass('dashicons-arrow-down-alt2');
                } else {
                    console.log('CBD Toggle: Showing content');
                    $contentToToggle.show();
                    $(this).find('.dashicons')
                        .removeClass('dashicons-arrow-down-alt2')
                        .addClass('dashicons-arrow-up-alt2');
                }
                
                console.log('CBD Toggle: Complete');
            });
            
            $(document).on('click.cbd-frontend', '.cbd-copy-text', this.copyText);
            $(document).on('click.cbd-frontend', '.cbd-screenshot', this.takeScreenshot);
        },

        /**
         * Load required libraries
         */
        loadLibraries: function() {
            // Load html2canvas if not already loaded
            if (typeof html2canvas === 'undefined' && $('.cbd-screenshot').length) {
                var script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                script.async = true;
                document.head.appendChild(script);
            }
        },

        // Old toggleCollapse function removed - using simple inline handler instead

        /**
         * Copy text to clipboard
         */
        copyText: function(e) {
            e.preventDefault();
            var $button = $(this);
            var containerId = $button.data('container-id');
            var $container = $('#' + containerId);

            if (!$container.length) return;

            // Get text content from the inner container block
            var textContent = $container.find('.cbd-content .cbd-container-block').text().trim();

            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textContent).then(function() {
                    CBDFrontend.showToast('Text wurde kopiert', 'success');
                    $button.addClass('cbd-copied');
                    setTimeout(function() { $button.removeClass('cbd-copied'); }, 2000);
                }).catch(function(err) {
                    CBDFrontend.showToast('Fehler beim Kopieren', 'error');
                });
            } else {
                // Fallback for older browsers
                var $textarea = $('<textarea>')
                    .val(textContent)
                    .css({ position: 'fixed', top: '-9999px' })
                    .appendTo('body');
                
                $textarea[0].select();
                
                try {
                    document.execCommand('copy');
                    CBDFrontend.showToast('Text wurde kopiert', 'success');
                    $button.addClass('cbd-copied');
                    setTimeout(function() { $button.removeClass('cbd-copied'); }, 2000);
                } catch (err) {
                    CBDFrontend.showToast('Fehler beim Kopieren', 'error');
                }
                
                $textarea.remove();
            }
        },

        /**
         * Take screenshot of container
         */
        takeScreenshot: function(e) {
            e.preventDefault();
            var $button = $(this);
            var containerId = $button.data('container-id');
            var $container = $('#' + containerId);

            // Screenshot the inner content block, not the entire wrapper
            var contentBlock = $container.find('.cbd-content .cbd-container-block')[0];

            if (!contentBlock || typeof html2canvas === 'undefined') {
                CBDFrontend.showToast('Screenshot-Funktion nicht verfügbar', 'error');
                return;
            }

            $button.prop('disabled', true);
            $button.addClass('cbd-loading');

            html2canvas(contentBlock, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: null,
                logging: false
            }).then(function(canvas) {
                // Try to copy to clipboard first, fallback to download
                CBDFrontend.copyImageToClipboard(canvas, function(success) {
                    if (success) {
                        CBDFrontend.showToast('Screenshot in Zwischenablage kopiert', 'success');
                    } else {
                        // Fallback: Download the image
                        canvas.toBlob(function(blob) {
                            var url = URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = 'container-block-' + Date.now() + '.png';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);

                            CBDFrontend.showToast('Screenshot heruntergeladen (Zwischenablage nicht verfügbar)', 'info');
                        });
                    }

                    $button.prop('disabled', false);
                    $button.removeClass('cbd-loading');
                });
            }).catch(function(error) {
                console.error('CBD: Screenshot failed', error);
                CBDFrontend.showToast('Fehler beim Screenshot', 'error');
                $button.prop('disabled', false);
                $button.removeClass('cbd-loading');
            });
        },

        /**
         * Copy image to clipboard with fallbacks for different browsers/platforms
         */
        copyImageToClipboard: function(canvas, callback) {
            // Check basic clipboard support
            if (!navigator.clipboard) {
                console.log('CBD: Clipboard API not available');
                callback(false);
                return;
            }

            // Check ClipboardItem support
            if (typeof ClipboardItem === 'undefined' && typeof window.ClipboardItem === 'undefined') {
                console.log('CBD: ClipboardItem not supported');
                callback(false);
                return;
            }

            // Use window.ClipboardItem if ClipboardItem is undefined
            var ClipboardItemConstructor = ClipboardItem || window.ClipboardItem;

            try {
                // Convert canvas to blob
                canvas.toBlob(function(blob) {
                    if (!blob) {
                        console.log('CBD: Failed to create blob from canvas');
                        callback(false);
                        return;
                    }

                    console.log('CBD: Attempting to copy image to clipboard...');

                    // Check if we can support this MIME type
                    if (ClipboardItemConstructor.supports && !ClipboardItemConstructor.supports('image/png')) {
                        console.log('CBD: Browser does not support image/png in clipboard');
                        callback(false);
                        return;
                    }

                    try {
                        // Create clipboard item
                        var clipboardItem = new ClipboardItemConstructor({
                            'image/png': blob
                        });

                        // Write to clipboard
                        navigator.clipboard.write([clipboardItem]).then(function() {
                            console.log('CBD: Successfully copied image to clipboard');
                            callback(true);
                        }).catch(function(error) {
                            console.error('CBD: Clipboard write failed:', error);

                            // Try alternative approach for Safari
                            if (error.name === 'NotAllowedError') {
                                console.log('CBD: Trying Safari-compatible approach...');
                                CBDFrontend.copyImageToClipboardSafari(blob, ClipboardItemConstructor, callback);
                            } else {
                                callback(false);
                            }
                        });
                    } catch (clipboardError) {
                        console.error('CBD: ClipboardItem creation failed:', clipboardError);
                        callback(false);
                    }
                }, 'image/png');
            } catch (error) {
                console.error('CBD: Canvas blob conversion failed:', error);
                callback(false);
            }
        },

        /**
         * Safari-specific clipboard approach
         */
        copyImageToClipboardSafari: function(blob, ClipboardItemConstructor, callback) {
            try {
                // Create ClipboardItem with Promise for Safari compatibility
                var clipboardItem = new ClipboardItemConstructor({
                    'image/png': Promise.resolve(blob)
                });

                navigator.clipboard.write([clipboardItem]).then(function() {
                    console.log('CBD: Safari clipboard copy successful');
                    callback(true);
                }).catch(function(error) {
                    console.error('CBD: Safari clipboard copy failed:', error);
                    callback(false);
                });
            } catch (error) {
                console.error('CBD: Safari clipboard setup failed:', error);
                callback(false);
            }
        },

        /**
         * Test clipboard functionality (for debugging)
         */
        testClipboard: function() {
            console.log('=== CBD Clipboard Test ===');
            console.log('navigator.clipboard:', !!navigator.clipboard);
            console.log('navigator.clipboard.write:', !!(navigator.clipboard && navigator.clipboard.write));
            console.log('ClipboardItem:', typeof ClipboardItem);
            console.log('window.ClipboardItem:', typeof window.ClipboardItem);

            if (ClipboardItem && ClipboardItem.supports) {
                console.log('ClipboardItem.supports("image/png"):', ClipboardItem.supports('image/png'));
            } else if (window.ClipboardItem && window.ClipboardItem.supports) {
                console.log('window.ClipboardItem.supports("image/png"):', window.ClipboardItem.supports('image/png'));
            }

            console.log('User Agent:', navigator.userAgent);
            console.log('=== End Test ===');
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            type = type || 'info';
            var $toast = $('<div>')
                .addClass('cbd-toast cbd-toast-' + type)
                .text(message);

            $('body').append($toast);

            setTimeout(function() { $toast.addClass('cbd-toast-show'); }, 100);

            setTimeout(function() {
                $toast.removeClass('cbd-toast-show');
                setTimeout(function() { $toast.remove(); }, 300);
            }, 3000);
        },

        /**
         * Convert number to roman numerals
         */
        toRoman: function(num) {
            var romanNumerals = [
                ['M', 1000], ['CM', 900], ['D', 500], ['CD', 400],
                ['C', 100], ['XC', 90], ['L', 50], ['XL', 40],
                ['X', 10], ['IX', 9], ['V', 5], ['IV', 4], ['I', 1]
            ];
            
            var result = '';
            for (var i = 0; i < romanNumerals.length; i++) {
                while (num >= romanNumerals[i][1]) {
                    result += romanNumerals[i][0];
                    num -= romanNumerals[i][1];
                }
            }
            return result;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Prevent multiple initializations
        if (window.CBDFrontendInitialized) {
            console.log('CBD Frontend already initialized, skipping...');
            return;
        }
        
        console.log('CBD Frontend: Starting initialization...');
        window.CBDFrontendInitialized = true;
        CBDFrontend.init();
    });

    // Make available globally
    window.CBDFrontend = CBDFrontend;

})(jQuery);