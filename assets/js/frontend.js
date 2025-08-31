/**
 * Container Block Designer - Frontend JavaScript
 * Handles all frontend functionality including collapse/expand, copy text, screenshots, and interactive features
 */

(function($) {
    'use strict';
    
    // Wait for document ready
    $(document).ready(function() {
        
        // Global CBD Frontend object
        window.CBDFrontend = {
            
            // Configuration
            config: {
                animationSpeed: 300,
                localStorage: true,
                debug: false
            },
            
            // Initialize frontend functionality
            init: function() {
                if (this.config.debug) {
                    console.log('CBD Frontend: Initializing');
                }
                
                this.initContainers();
                this.bindEvents();
                this.restoreStates();
                this.initLazyLoad();
                this.initAccessibility();
            },
            
            // Initialize all containers
            initContainers: function() {
                $('.cbd-container').each(function() {
                    const container = $(this);
                    const blockId = container.data('block-id');
                    
                    if (!blockId) return;
                    
                    // Add unique ID if not present
                    if (!container.attr('id')) {
                        container.attr('id', 'cbd-container-' + blockId);
                    }
                    
                    // Initialize features
                    CBDFrontend.initIcon(container);
                    CBDFrontend.initCollapse(container);
                    CBDFrontend.initNumbering(container);
                    CBDFrontend.initCopyText(container);
                    CBDFrontend.initScreenshot(container);
                    CBDFrontend.initCustomFeatures(container);
                });
            },
            
            // Bind global events
            bindEvents: function() {
                // Collapse/Expand
                $(document).on('click', '.cbd-collapse-toggle', this.toggleCollapse);
                
                // Copy text
                $(document).on('click', '.cbd-copy-text', this.copyText);
                
                // Screenshot
                $(document).on('click', '.cbd-screenshot', this.takeScreenshot);
                
                // Expand/Collapse all
                $(document).on('click', '.cbd-expand-all', this.expandAll);
                $(document).on('click', '.cbd-collapse-all', this.collapseAll);
                
                // Print container
                $(document).on('click', '.cbd-print', this.printContainer);
                
                // Fullscreen
                $(document).on('click', '.cbd-fullscreen', this.toggleFullscreen);
                
                // Handle window resize
                $(window).on('resize', this.debounce(this.handleResize, 250));
                
                // Handle print media
                window.matchMedia('print').addListener(this.handlePrintMedia);
            },
            
            // Initialize icon feature
            initIcon: function(container) {
                const iconData = container.data('icon');
                if (!iconData || !iconData.enabled) return;
                
                const iconHtml = `<span class="cbd-icon ${iconData.position || 'top-left'}" 
                    style="color: ${iconData.color || '#333'};">
                    <i class="dashicons ${iconData.value || 'dashicons-admin-generic'}"></i>
                </span>`;
                
                container.prepend(iconHtml);
                
                // Add animation if specified
                if (iconData.animation) {
                    container.find('.cbd-icon').addClass('cbd-icon-' + iconData.animation);
                }
            },
            
            // Initialize collapse feature
            initCollapse: function(container) {
                const collapseData = container.data('collapse');
                if (!collapseData || !collapseData.enabled) return;
                
                // Create collapse header
                const header = container.find('.cbd-header');
                if (!header.length) {
                    container.prepend('<div class="cbd-header"></div>');
                }
                
                const toggleHtml = `
                    <button class="cbd-collapse-toggle" 
                        aria-expanded="${collapseData.defaultState !== 'collapsed'}"
                        aria-controls="${container.attr('id')}-content">
                        <span class="cbd-toggle-icon">
                            <i class="dashicons dashicons-arrow-${collapseData.defaultState === 'collapsed' ? 'down' : 'up'}-alt2"></i>
                        </span>
                        <span class="cbd-toggle-text">${collapseData.label || 'Toggle'}</span>
                    </button>
                `;
                
                container.find('.cbd-header').append(toggleHtml);
                
                // Wrap content
                const content = container.find('.cbd-content');
                if (!content.length) {
                    container.children().not('.cbd-header, .cbd-icon').wrapAll('<div class="cbd-content"></div>');
                }
                
                // Set initial state
                if (collapseData.defaultState === 'collapsed') {
                    container.addClass('cbd-collapsed');
                    container.find('.cbd-content').hide();
                }
                
                // Add transition
                container.find('.cbd-content').css('transition', `all ${this.config.animationSpeed}ms ease`);
            },
            
            // Initialize numbering feature
            initNumbering: function(container) {
                const numberingData = container.data('numbering');
                if (!numberingData || !numberingData.enabled) return;
                
                const format = numberingData.format || 'numeric';
                const startFrom = numberingData.startFrom || 1;
                const prefix = numberingData.prefix || '';
                const suffix = numberingData.suffix || '';
                
                // Find all numberable items
                const selector = numberingData.selector || 'h2, h3, h4';
                const items = container.find(selector);
                
                items.each(function(index) {
                    const item = $(this);
                    let number;
                    
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
                    
                    const numberHtml = `<span class="cbd-number">${prefix}${number}${suffix}</span> `;
                    item.prepend(numberHtml);
                });
            },
            
            // Initialize copy text feature
            initCopyText: function(container) {
                const copyData = container.data('copy-text');
                if (!copyData || !copyData.enabled) return;
                
                const buttonHtml = `
                    <button class="cbd-copy-text" 
                        title="${copyData.tooltip || 'Text kopieren'}"
                        data-container-id="${container.attr('id')}">
                        <i class="dashicons dashicons-clipboard"></i>
                        <span class="cbd-copy-label">${copyData.label || 'Kopieren'}</span>
                    </button>
                `;
                
                // Add to header or create action bar
                let target = container.find('.cbd-header');
                if (!target.length) {
                    container.prepend('<div class="cbd-actions"></div>');
                    target = container.find('.cbd-actions');
                }
                
                target.append(buttonHtml);
            },
            
            // Initialize screenshot feature
            initScreenshot: function(container) {
                const screenshotData = container.data('screenshot');
                if (!screenshotData || !screenshotData.enabled) return;
                
                // Check if html2canvas is available
                if (typeof html2canvas === 'undefined') {
                    // Load html2canvas from CDN
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                    script.onload = function() {
                        CBDFrontend.addScreenshotButton(container, screenshotData);
                    };
                    document.head.appendChild(script);
                } else {
                    this.addScreenshotButton(container, screenshotData);
                }
            },
            
            // Add screenshot button
            addScreenshotButton: function(container, screenshotData) {
                const buttonHtml = `
                    <button class="cbd-screenshot" 
                        title="${screenshotData.tooltip || 'Screenshot erstellen'}"
                        data-container-id="${container.attr('id')}">
                        <i class="dashicons dashicons-camera"></i>
                        <span class="cbd-screenshot-label">${screenshotData.label || 'Screenshot'}</span>
                    </button>
                `;
                
                let target = container.find('.cbd-header, .cbd-actions').first();
                if (!target.length) {
                    container.prepend('<div class="cbd-actions"></div>');
                    target = container.find('.cbd-actions');
                }
                
                target.append(buttonHtml);
            },
            
            // Initialize custom features
            initCustomFeatures: function(container) {
                // Hover effects
                if (container.hasClass('cbd-hover-effect')) {
                    container.on('mouseenter', function() {
                        $(this).addClass('cbd-hovered');
                    }).on('mouseleave', function() {
                        $(this).removeClass('cbd-hovered');
                    });
                }
                
                // Click to expand
                if (container.hasClass('cbd-click-expand')) {
                    container.on('click', function(e) {
                        if (!$(e.target).is('button, a, input, select, textarea')) {
                            $(this).toggleClass('cbd-expanded');
                        }
                    });
                }
                
                // Sticky header
                if (container.hasClass('cbd-sticky-header')) {
                    const header = container.find('.cbd-header');
                    if (header.length) {
                        $(window).on('scroll', function() {
                            const scrollTop = $(window).scrollTop();
                            const containerTop = container.offset().top;
                            
                            if (scrollTop > containerTop) {
                                header.addClass('cbd-sticky');
                            } else {
                                header.removeClass('cbd-sticky');
                            }
                        });
                    }
                }
            },
            
            // Toggle collapse state
            toggleCollapse: function(e) {
                e.preventDefault();
                const button = $(this);
                const container = button.closest('.cbd-container');
                const content = container.find('.cbd-content');
                const isCollapsed = container.hasClass('cbd-collapsed');
                
                if (isCollapsed) {
                    // Expand
                    content.slideDown(CBDFrontend.config.animationSpeed);
                    container.removeClass('cbd-collapsed');
                    button.attr('aria-expanded', 'true');
                    button.find('.dashicons')
                        .removeClass('dashicons-arrow-down-alt2')
                        .addClass('dashicons-arrow-up-alt2');
                } else {
                    // Collapse
                    content.slideUp(CBDFrontend.config.animationSpeed);
                    container.addClass('cbd-collapsed');
                    button.attr('aria-expanded', 'false');
                    button.find('.dashicons')
                        .removeClass('dashicons-arrow-up-alt2')
                        .addClass('dashicons-arrow-down-alt2');
                }
                
                // Save state
                CBDFrontend.saveState(container.attr('id'), !isCollapsed);
                
                // Trigger custom event
                container.trigger('cbd:toggle', [!isCollapsed]);
            },
            
            // Copy text to clipboard
            copyText: function(e) {
                e.preventDefault();
                const button = $(this);
                const containerId = button.data('container-id');
                const container = $('#' + containerId);
                
                if (!container.length) return;
                
                // Get text content
                const textContent = container.find('.cbd-content').text().trim();
                
                // Copy to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textContent).then(function() {
                        CBDFrontend.showToast('Text wurde in die Zwischenablage kopiert', 'success');
                        button.addClass('cbd-copied');
                        setTimeout(() => button.removeClass('cbd-copied'), 2000);
                    }).catch(function(err) {
                        CBDFrontend.showToast('Fehler beim Kopieren', 'error');
                        console.error('CBD: Copy failed', err);
                    });
                } else {
                    // Fallback for older browsers
                    const textarea = $('<textarea>')
                        .val(textContent)
                        .css({ position: 'fixed', top: '-9999px' })
                        .appendTo('body');
                    
                    textarea[0].select();
                    
                    try {
                        document.execCommand('copy');
                        CBDFrontend.showToast('Text wurde kopiert', 'success');
                        button.addClass('cbd-copied');
                        setTimeout(() => button.removeClass('cbd-copied'), 2000);
                    } catch (err) {
                        CBDFrontend.showToast('Fehler beim Kopieren', 'error');
                        console.error('CBD: Copy failed', err);
                    }
                    
                    textarea.remove();
                }
            },
            
            // Take screenshot of container
            takeScreenshot: function(e) {
                e.preventDefault();
                const button = $(this);
                const containerId = button.data('container-id');
                const container = document.getElementById(containerId);
                
                if (!container || typeof html2canvas === 'undefined') return;
                
                button.prop('disabled', true);
                button.addClass('cbd-loading');
                
                html2canvas(container, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: null,
                    logging: false
                }).then(function(canvas) {
                    // Convert to blob
                    canvas.toBlob(function(blob) {
                        // Create download link
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `container-${containerId}-${Date.now()}.png`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        
                        CBDFrontend.showToast('Screenshot wurde erstellt', 'success');
                    });
                    
                    button.prop('disabled', false);
                    button.removeClass('cbd-loading');
                }).catch(function(error) {
                    console.error('CBD: Screenshot failed', error);
                    CBDFrontend.showToast('Fehler beim Erstellen des Screenshots', 'error');
                    button.prop('disabled', false);
                    button.removeClass('cbd-loading');
                });
            },
            
            // Expand all containers
            expandAll: function(e) {
                e.preventDefault();
                $('.cbd-container.cbd-collapsed').each(function() {
                    $(this).find('.cbd-collapse-toggle').trigger('click');
                });
            },
            
            // Collapse all containers
            collapseAll: function(e) {
                e.preventDefault();
                $('.cbd-container:not(.cbd-collapsed)').each(function() {
                    $(this).find('.cbd-collapse-toggle').trigger('click');
                });
            },
            
            // Print specific container
            printContainer: function(e) {
                e.preventDefault();
                const button = $(this);
                const container = button.closest('.cbd-container');
                
                if (!container.length) return;
                
                // Create print window
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                const styles = Array.from(document.styleSheets)
                    .map(styleSheet => {
                        try {
                            return Array.from(styleSheet.cssRules)
                                .map(rule => rule.cssText)
                                .join('\n');
                        } catch (e) {
                            return '';
                        }
                    })
                    .join('\n');
                
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Container Print</title>
                        <style>${styles}</style>
                        <style>
                            body { margin: 20px; }
                            .cbd-actions { display: none !important; }
                            .cbd-collapse-toggle { display: none !important; }
                            @media print {
                                .cbd-no-print { display: none !important; }
                            }
                        </style>
                    </head>
                    <body>
                        ${container[0].outerHTML}
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
                
                setTimeout(function() {
                    printWindow.print();
                    printWindow.close();
                }, 250);
            },
            
            // Toggle fullscreen
            toggleFullscreen: function(e) {
                e.preventDefault();
                const button = $(this);
                const container = button.closest('.cbd-container')[0];
                
                if (!container) return;
                
                if (!document.fullscreenElement) {
                    container.requestFullscreen().then(function() {
                        $(container).addClass('cbd-fullscreen');
                        button.find('.dashicons')
                            .removeClass('dashicons-fullscreen-alt')
                            .addClass('dashicons-fullscreen-exit-alt');
                    }).catch(function(err) {
                        console.error('CBD: Fullscreen failed', err);
                    });
                } else {
                    document.exitFullscreen().then(function() {
                        $(container).removeClass('cbd-fullscreen');
                        button.find('.dashicons')
                            .removeClass('dashicons-fullscreen-exit-alt')
                            .addClass('dashicons-fullscreen-alt');
                    });
                }
            },
            
            // Initialize lazy loading for images
            initLazyLoad: function() {
                if ('IntersectionObserver' in window) {
                    const imageObserver = new IntersectionObserver(function(entries, observer) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                const img = entry.target;
                                if (img.dataset.src) {
                                    img.src = img.dataset.src;
                                    img.removeAttribute('data-src');
                                    observer.unobserve(img);
                                }
                            }
                        });
                    });
                    
                    $('.cbd-container img[data-src]').each(function() {
                        imageObserver.observe(this);
                    });
                }
            },
            
            // Initialize accessibility features
            initAccessibility: function() {
                // Add keyboard navigation
                $('.cbd-container').attr('tabindex', '0');
                
                // Handle keyboard events
                $('.cbd-container').on('keydown', function(e) {
                    const container = $(this);
                    
                    // Enter or Space on container toggles collapse
                    if ((e.key === 'Enter' || e.key === ' ') && $(e.target).is('.cbd-container')) {
                        e.preventDefault();
                        container.find('.cbd-collapse-toggle').trigger('click');
                    }
                    
                    // Escape closes fullscreen
                    if (e.key === 'Escape' && container.hasClass('cbd-fullscreen')) {
                        document.exitFullscreen();
                    }
                });
                
                // Add ARIA labels
                $('.cbd-container').each(function() {
                    const container = $(this);
                    const title = container.find('h1, h2, h3, h4, h5, h6').first().text();
                    
                    if (title) {
                        container.attr('aria-label', title);
                    }
                });
            },
            
            // Save collapse state to localStorage
            saveState: function(containerId, isCollapsed) {
                if (!this.config.localStorage || !window.localStorage) return;
                
                const states = JSON.parse(localStorage.getItem('cbd-states') || '{}');
                states[containerId] = { collapsed: isCollapsed, timestamp: Date.now() };
                localStorage.setItem('cbd-states', JSON.stringify(states));
            },
            
            // Restore saved states
            restoreStates: function() {
                if (!this.config.localStorage || !window.localStorage) return;
                
                const states = JSON.parse(localStorage.getItem('cbd-states') || '{}');
                const oneWeekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
                
                // Clean old states
                Object.keys(states).forEach(key => {
                    if (states[key].timestamp < oneWeekAgo) {
                        delete states[key];
                    }
                });
                
                localStorage.setItem('cbd-states', JSON.stringify(states));
                
                // Apply states
                Object.keys(states).forEach(containerId => {
                    const container = $('#' + containerId);
                    if (container.length && states[containerId].collapsed) {
                        container.addClass('cbd-collapsed');
                        container.find('.cbd-content').hide();
                        container.find('.cbd-collapse-toggle')
                            .attr('aria-expanded', 'false')
                            .find('.dashicons')
                            .removeClass('dashicons-arrow-up-alt2')
                            .addClass('dashicons-arrow-down-alt2');
                    }
                });
            },
            
            // Handle window resize
            handleResize: function() {
                $('.cbd-container').each(function() {
                    const container = $(this);
                    const width = container.width();
                    
                    // Add responsive classes
                    container.removeClass('cbd-small cbd-medium cbd-large');
                    
                    if (width < 480) {
                        container.addClass('cbd-small');
                    } else if (width < 768) {
                        container.addClass('cbd-medium');
                    } else {
                        container.addClass('cbd-large');
                    }
                });
            },
            
            // Handle print media
            handlePrintMedia: function(mql) {
                if (mql.matches) {
                    // Expand all collapsed containers for printing
                    $('.cbd-container.cbd-collapsed').addClass('cbd-print-expanded');
                    $('.cbd-container.cbd-print-expanded .cbd-content').show();
                } else {
                    // Restore after printing
                    $('.cbd-container.cbd-print-expanded .cbd-content').hide();
                    $('.cbd-container.cbd-print-expanded').removeClass('cbd-print-expanded');
                }
            },
            
            // Show toast notification
            showToast: function(message, type = 'info') {
                const toast = $('<div>')
                    .addClass(`cbd-toast cbd-toast-${type}`)
                    .text(message);
                
                $('body').append(toast);
                
                setTimeout(() => toast.addClass('cbd-toast-show'), 100);
                
                setTimeout(() => {
                    toast.removeClass('cbd-toast-show');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            },
            
            // Convert number to roman numerals
            toRoman: function(num) {
                const romanNumerals = [
                    ['M', 1000], ['CM', 900], ['D', 500], ['CD', 400],
                    ['C', 100], ['XC', 90], ['L', 50], ['XL', 40],
                    ['X', 10], ['IX', 9], ['V', 5], ['IV', 4], ['I', 1]
                ];
                
                let result = '';
                for (const [roman, value] of romanNumerals) {
                    while (num >= value) {
                        result += roman;
                        num -= value;
                    }
                }
                return result;
            },
            
            // Debounce helper
            debounce: function(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        };
        
        // Initialize CBD Frontend
        CBDFrontend.init();
        
        // Make available globally
        window.CBDFrontend = CBDFrontend;
    });
    
})(jQuery);