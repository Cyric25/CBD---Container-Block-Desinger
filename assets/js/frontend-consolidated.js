/**
 * Container Block Designer - Unified Frontend JavaScript
 * Version: 2.6.0
 * All frontend functionality consolidated for better performance and consistency
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
                
                // Initialize all features
                CBDFrontend.initCollapsible($container);
                CBDFrontend.initCopyText($container);
                CBDFrontend.initScreenshot($container);
                CBDFrontend.initNumbering($container);
                CBDFrontend.initIcon($container);
                CBDFrontend.initAccessibility($container);
            });
        },

        /**
         * Initialize collapsible functionality
         */
        initCollapsible: function($container) {
            var collapseData = $container.data('collapse');
            if (!collapseData || !collapseData.enabled) return;

            // Find or create header
            var $header = $container.find('.cbd-header');
            if (!$header.length) {
                $container.prepend('<div class="cbd-header"></div>');
                $header = $container.find('.cbd-header');
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
            }

            // Wrap content
            var $content = $container.find('.cbd-content');
            if (!$content.length) {
                $container.children().not('.cbd-header, .cbd-icon, .cbd-actions').wrapAll('<div class="cbd-content" id="' + $container.attr('id') + '-content"></div>');
                $content = $container.find('.cbd-content');
            }

            // Set initial state
            if (collapseData.defaultState === 'collapsed') {
                $container.addClass('cbd-collapsed');
                $content.hide();
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
        },

        /**
         * Bind global events
         */
        bindGlobalEvents: function() {
            // Collapse/Expand events
            $(document).on('click', '.cbd-collapse-toggle', this.toggleCollapse);
            
            // Copy text events
            $(document).on('click', '.cbd-copy-text', this.copyText);
            
            // Screenshot events
            $(document).on('click', '.cbd-screenshot', this.takeScreenshot);
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

        /**
         * Toggle collapse state
         */
        toggleCollapse: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $container = $button.closest('.cbd-container');
            var $content = $container.find('.cbd-content');
            var isCollapsed = $container.hasClass('cbd-collapsed');

            if (isCollapsed) {
                // Expand
                $content.slideDown(CBDFrontend.config.animationSpeed);
                $container.removeClass('cbd-collapsed');
                $button.attr('aria-expanded', 'true');
                $button.find('.dashicons')
                    .removeClass('dashicons-arrow-down-alt2')
                    .addClass('dashicons-arrow-up-alt2');
            } else {
                // Collapse
                $content.slideUp(CBDFrontend.config.animationSpeed);
                $container.addClass('cbd-collapsed');
                $button.attr('aria-expanded', 'false');
                $button.find('.dashicons')
                    .removeClass('dashicons-arrow-up-alt2')
                    .addClass('dashicons-arrow-down-alt2');
            }

            // Trigger custom event
            $container.trigger('cbd:toggle', [!isCollapsed]);
        },

        /**
         * Copy text to clipboard
         */
        copyText: function(e) {
            e.preventDefault();
            var $button = $(this);
            var containerId = $button.data('container-id');
            var $container = $('#' + containerId);

            if (!$container.length) return;

            // Get text content
            var textContent = $container.find('.cbd-content').text().trim();

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
            var container = document.getElementById(containerId);

            if (!container || typeof html2canvas === 'undefined') {
                CBDFrontend.showToast('Screenshot-Funktion nicht verfügbar', 'error');
                return;
            }

            $button.prop('disabled', true);
            $button.addClass('cbd-loading');

            html2canvas(container, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: null,
                logging: false
            }).then(function(canvas) {
                canvas.toBlob(function(blob) {
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'container-' + containerId + '-' + Date.now() + '.png';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    CBDFrontend.showToast('Screenshot wurde erstellt', 'success');
                });
                
                $button.prop('disabled', false);
                $button.removeClass('cbd-loading');
            }).catch(function(error) {
                console.error('CBD: Screenshot failed', error);
                CBDFrontend.showToast('Fehler beim Screenshot', 'error');
                $button.prop('disabled', false);
                $button.removeClass('cbd-loading');
            });
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
        CBDFrontend.init();
    });

    // Make available globally
    window.CBDFrontend = CBDFrontend;

})(jQuery);