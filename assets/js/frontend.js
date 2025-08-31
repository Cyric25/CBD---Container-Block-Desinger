/**
 * Container Block Designer - Frontend JavaScript
 * Version: 2.4.0
 * 
 * Datei speichern als: assets/js/frontend.js
 */

(function($) {
    'use strict';
    
    /**
     * Container Block Frontend Handler
     */
    class CBD_Frontend {
        
        constructor() {
            this.init();
            this.bindEvents();
            console.log('✅ Container Block Designer Frontend initialized');
        }
        
        /**
         * Initialize functionality
         */
        init() {
            this.initCollapsibleContainers();
            this.initContainerNumbering();
            this.initTooltips();
        }
        
        /**
         * Bind event listeners
         */
        bindEvents() {
            // Copy text functionality
            $(document).on('click', '.cbd-copy-button', (e) => this.handleCopyText(e));
            
            // Screenshot functionality  
            $(document).on('click', '.cbd-screenshot-button', (e) => this.handleScreenshot(e));
            
            // Collapsible containers
            $(document).on('click', '.cbd-collapse-header', (e) => this.handleCollapse(e));
            
            // Container interactions
            $(document).on('mouseenter', '.cbd-positioned', (e) => this.handlePositionedHover(e, true));
            $(document).on('mouseleave', '.cbd-positioned', (e) => this.handlePositionedHover(e, false));
            
            // Window resize for responsive adjustments
            $(window).on('resize', this.debounce(() => this.handleResize(), 250));
        }
        
        /**
         * Initialize collapsible containers
         */
        initCollapsibleContainers() {
            $('.cbd-container[data-collapse="true"]').each(function() {
                const $container = $(this);
                const $header = $container.find('.cbd-collapse-header');
                const $content = $container.find('.cbd-container-content');
                const defaultState = $container.data('collapse-state') || 'expanded';
                
                if (defaultState === 'collapsed') {
                    $content.hide();
                    $header.addClass('collapsed').attr('aria-expanded', 'false');
                } else {
                    $header.attr('aria-expanded', 'true');
                }
            });
        }
        
        /**
         * Initialize container numbering
         */
        initContainerNumbering() {
            // Group containers by block type for numbering
            const containerGroups = {};
            
            $('.cbd-container[data-numbering="true"]').each(function() {
                const $container = $(this);
                const blockType = $container.data('block-type') || 'default';
                const format = $container.data('numbering-format') || 'decimal';
                
                if (!containerGroups[blockType]) {
                    containerGroups[blockType] = [];
                }
                
                containerGroups[blockType].push({
                    element: $container,
                    format: format
                });
            });
            
            // Apply numbering to each group
            Object.keys(containerGroups).forEach(blockType => {
                containerGroups[blockType].forEach((item, index) => {
                    const number = this.formatNumber(item.format, index + 1);
                    item.element.find('.cbd-container-number').text(number);
                });
            });
        }
        
        /**
         * Handle copy text functionality
         */
        handleCopyText(event) {
            event.preventDefault();
            
            const $button = $(event.currentTarget);
            const containerId = $button.data('container');
            const $container = $('#' + containerId);
            
            if (!$container.length) {
                this.showToast(__('Container nicht gefunden', 'container-block-designer'), 'error');
                return;
            }
            
            // Get text content
            const $content = $container.find('.cbd-container-content');
            let textToCopy = '';
            
            if ($content.length) {
                // Extract text while preserving some structure
                textToCopy = $content.clone()
                    .find('script, style').remove().end()
                    .text()
                    .replace(/\s+/g, ' ')
                    .trim();
            }
            
            if (!textToCopy) {
                this.showToast(__('Kein Text zum Kopieren gefunden', 'container-block-designer'), 'warning');
                return;
            }
            
            // Copy to clipboard
            this.copyToClipboard(textToCopy).then(() => {
                this.showToast(__('Text kopiert!', 'container-block-designer'), 'success');
                this.animateButton($button, 'success');
            }).catch(() => {
                this.showToast(__('Kopieren fehlgeschlagen', 'container-block-designer'), 'error');
                this.animateButton($button, 'error');
            });
        }
        
        /**
         * Handle screenshot functionality
         */
        handleScreenshot(event) {
            event.preventDefault();
            
            const $button = $(event.currentTarget);
            const containerId = $button.data('container');
            const $container = $('#' + containerId);
            
            if (!$container.length) {
                this.showToast(__('Container nicht gefunden', 'container-block-designer'), 'error');
                return;
            }
            
            // Check if html2canvas is available
            if (typeof html2canvas === 'undefined') {
                this.showToast(__('Screenshot-Funktion nicht verfügbar. html2canvas library fehlt.', 'container-block-designer'), 'error');
                return;
            }
            
            // Show loading state
            const originalText = $button.find('.cbd-button-text').text();
            $button.find('.cbd-button-text').text(__('Erstelle Screenshot...', 'container-block-designer'));
            $button.prop('disabled', true).addClass('cbd-loading');
            
            // Create screenshot
            const options = {
                backgroundColor: '#ffffff',
                scale: 2, // Higher quality
                useCORS: true,
                allowTaint: false,
                removeContainer: true,
                onclone: (clonedDoc) => {
                    // Clean up cloned document for better screenshot
                    $(clonedDoc).find('.cbd-copy-button, .cbd-screenshot-button').remove();
                }
            };
            
            html2canvas($container[0], options).then((canvas) => {
                // Create download link
                const link = document.createElement('a');
                link.download = `container-screenshot-${Date.now()}.png`;
                link.href = canvas.toDataURL('image/png');
                
                // Trigger download
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                this.showToast(__('Screenshot erstellt!', 'container-block-designer'), 'success');
                this.animateButton($button, 'success');
                
            }).catch((error) => {
                console.error('Screenshot error:', error);
                this.showToast(__('Screenshot fehlgeschlagen', 'container-block-designer'), 'error');
                this.animateButton($button, 'error');
                
            }).finally(() => {
                // Reset button state
                $button.find('.cbd-button-text').text(originalText);
                $button.prop('disabled', false).removeClass('cbd-loading');
            });
        }
        
        /**
         * Handle collapsible container toggle
         */
        handleCollapse(event) {
            event.preventDefault();
            
            const $header = $(event.currentTarget);
            const $container = $header.closest('.cbd-container');
            const $content = $container.find('.cbd-container-content');
            const $arrow = $header.find('.cbd-header-arrow');
            
            const isCollapsed = $header.hasClass('collapsed');
            
            if (isCollapsed) {
                // Expand
                $content.slideDown(300);
                $header.removeClass('collapsed').attr('aria-expanded', 'true');
                $arrow.removeClass('cbd-rotated');
                $container.removeClass('cbd-collapsed');
            } else {
                // Collapse  
                $content.slideUp(300);
                $header.addClass('collapsed').attr('aria-expanded', 'false');
                $arrow.addClass('cbd-rotated');
                $container.addClass('cbd-collapsed');
            }
            
            // Trigger custom event
            $container.trigger('cbd:collapse:toggled', [!isCollapsed]);
        }
        
        /**
         * Handle positioned element hover
         */
        handlePositionedHover(event, isEntering) {
            const $element = $(event.currentTarget);
            
            if (isEntering) {
                $element.addClass('cbd-hovered');
                
                // Show tooltip if available
                const tooltip = $element.data('tooltip');
                if (tooltip) {
                    this.showTooltip($element, tooltip);
                }
            } else {
                $element.removeClass('cbd-hovered');
                this.hideTooltip();
            }
        }
        
        /**
         * Handle window resize
         */
        handleResize() {
            // Update positioned elements for responsive behavior
            this.updateResponsiveElements();
            
            // Re-calculate container heights if needed
            this.updateContainerHeights();
        }
        
        /**
         * Update responsive elements
         */
        updateResponsiveElements() {
            const windowWidth = $(window).width();
            
            $('.cbd-positioned').each(function() {
                const $element = $(this);
                
                // Adjust sizes based on screen width
                if (windowWidth <= 480) {
                    $element.addClass('cbd-mobile');
                    $element.removeClass('cbd-tablet cbd-desktop');
                } else if (windowWidth <= 768) {
                    $element.addClass('cbd-tablet');
                    $element.removeClass('cbd-mobile cbd-desktop');
                } else {
                    $element.addClass('cbd-desktop');
                    $element.removeClass('cbd-mobile cbd-tablet');
                }
            });
        }
        
        /**
         * Update container heights
         */
        updateContainerHeights() {
            $('.cbd-container.cbd-auto-height').each(function() {
                const $container = $(this);
                const $content = $container.find('.cbd-container-content');
                
                if ($content.length) {
                    const contentHeight = $content.outerHeight();
                    $container.css('min-height', contentHeight + 'px');
                }
            });
        }
        
        /**
         * Initialize tooltips
         */
        initTooltips() {
            $('[data-tooltip]').each(function() {
                const $element = $(this);
                const tooltip = $element.data('tooltip');
                
                if (tooltip) {
                    $element.attr('title', tooltip);
                }
            });
        }
        
        /**
         * Show tooltip
         */
        showTooltip($element, text) {
            this.hideTooltip(); // Hide any existing tooltip
            
            const $tooltip = $('<div class="cbd-tooltip"></div>').text(text);
            $('body').append($tooltip);
            
            const elementRect = $element[0].getBoundingClientRect();
            const tooltipWidth = $tooltip.outerWidth();
            const tooltipHeight = $tooltip.outerHeight();
            
            // Position tooltip
            let top = elementRect.top - tooltipHeight - 8;
            let left = elementRect.left + (elementRect.width / 2) - (tooltipWidth / 2);
            
            // Adjust if tooltip goes off screen
            if (top < 0) {
                top = elementRect.bottom + 8;
                $tooltip.addClass('cbd-tooltip-below');
            }
            
            if (left < 0) {
                left = 8;
            } else if (left + tooltipWidth > $(window).width()) {
                left = $(window).width() - tooltipWidth - 8;
            }
            
            $tooltip.css({
                position: 'fixed',
                top: top + 'px',
                left: left + 'px',
                zIndex: 10000
            }).fadeIn(200);
        }
        
        /**
         * Hide tooltip
         */
        hideTooltip() {
            $('.cbd-tooltip').remove();
        }
        
        /**
         * Copy text to clipboard
         */
        async copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                // Modern clipboard API
                return navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                return new Promise((resolve, reject) => {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.opacity = '0';
                    
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    
                    try {
                        const successful = document.execCommand('copy');
                        if (successful) {
                            resolve();
                        } else {
                            reject(new Error('Copy command failed'));
                        }
                    } catch (err) {
                        reject(err);
                    } finally {
                        document.body.removeChild(textArea);
                    }
                });
            }
        }
        
        /**
         * Show toast notification
         */
        showToast(message, type = 'info', duration = 3000) {
            // Remove existing toasts of the same type
            $(`.cbd-toast.cbd-toast-${type}`).remove();
            
            const $toast = $(`
                <div class="cbd-toast cbd-toast-${type}">
                    <span class="cbd-toast-icon"></span>
                    <span class="cbd-toast-message">${message}</span>
                    <button class="cbd-toast-close" aria-label="${__('Schließen', 'container-block-designer')}">&times;</button>
                </div>
            `);
            
            // Add to body
            $('body').append($toast);
            
            // Show with animation
            setTimeout(() => {
                $toast.addClass('cbd-toast-show');
            }, 100);
            
            // Auto-hide
            setTimeout(() => {
                this.hideToast($toast);
            }, duration);
            
            // Manual close
            $toast.find('.cbd-toast-close').on('click', () => {
                this.hideToast($toast);
            });
        }
        
        /**
         * Hide toast notification
         */
        hideToast($toast) {
            $toast.removeClass('cbd-toast-show');
            setTimeout(() => {
                $toast.remove();
            }, 300);
        }
        
        /**
         * Animate button state
         */
        animateButton($button, state) {
            $button.addClass(`cbd-button-${state}`);
            
            setTimeout(() => {
                $button.removeClass(`cbd-button-${state}`);
            }, 1000);
        }
        
        /**
         * Format number based on format type
         */
        formatNumber(format, index) {
            switch (format) {
                case 'alpha':
                    return String.fromCharCode(64 + index); // A, B, C...
                case 'roman':
                    return this.intToRoman(index);
                case 'decimal':
                default:
                    return index.toString();
            }
        }
        
        /**
         * Convert integer to Roman numeral
         */
        intToRoman(num) {
            const lookup = {
                M: 1000, CM: 900, D: 500, CD: 400,
                C: 100, XC: 90, L: 50, XL: 40,
                X: 10, IX: 9, V: 5, IV: 4, I: 1
            };
            
            let roman = '';
            for (let i in lookup) {
                while (num >= lookup[i]) {
                    roman += i;
                    num -= lookup[i];
                }
            }
            return roman;
        }
        
        /**
         * Debounce function
         */
        debounce(func, wait) {
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
        
        /**
         * Check if element is in viewport
         */
        isInViewport($element, threshold = 0) {
            const elementTop = $element.offset().top;
            const elementBottom = elementTop + $element.outerHeight();
            const viewportTop = $(window).scrollTop();
            const viewportBottom = viewportTop + $(window).height();
            
            return elementBottom > viewportTop + threshold && elementTop < viewportBottom - threshold;
        }
        
        /**
         * Animate elements on scroll (optional feature)
         */
        initScrollAnimations() {
            const $animatedElements = $('.cbd-positioned[data-animate="true"]');
            
            if ($animatedElements.length === 0) return;
            
            const handleScroll = this.debounce(() => {
                $animatedElements.each((index, element) => {
                    const $element = $(element);
                    
                    if (this.isInViewport($element, 100)) {
                        $element.addClass('cbd-animate-in');
                    }
                });
            }, 50);
            
            $(window).on('scroll', handleScroll);
            
            // Trigger on load
            handleScroll();
        }
        
        /**
         * Handle keyboard navigation
         */
        initKeyboardNavigation() {
            $(document).on('keydown', '.cbd-positioned[tabindex]', (e) => {
                const $element = $(e.currentTarget);
                
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $element.click();
                }
            });
        }
        
        /**
         * Initialize intersection observer for performance
         */
        initIntersectionObserver() {
            if (!window.IntersectionObserver) return;
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('cbd-in-view');
                        
                        // Trigger custom event
                        $(entry.target).trigger('cbd:inview');
                    } else {
                        $(entry.target).removeClass('cbd-in-view');
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });
            
            $('.cbd-container').each(function() {
                observer.observe(this);
            });
        }
        
        /**
         * Handle print preparation
         */
        initPrintHandling() {
            window.addEventListener('beforeprint', () => {
                // Expand all collapsed containers for printing
                $('.cbd-container.cbd-collapsed .cbd-container-content').show();
                
                // Remove hover states
                $('.cbd-positioned').removeClass('cbd-hovered');
                
                // Hide action buttons
                $('.cbd-action-buttons').hide();
            });
            
            window.addEventListener('afterprint', () => {
                // Restore collapsed state
                $('.cbd-container.cbd-collapsed .cbd-container-content').hide();
                
                // Show action buttons
                $('.cbd-action-buttons').show();
            });
        }
        
        /**
         * Initialize accessibility enhancements
         */
        initAccessibility() {
            // Add ARIA labels to positioned elements
            $('.cbd-positioned').each(function() {
                const $element = $(this);
                
                if (!$element.attr('aria-label')) {
                    if ($element.hasClass('cbd-container-icon')) {
                        $element.attr('aria-label', __('Container Icon', 'container-block-designer'));
                    } else if ($element.hasClass('cbd-container-number')) {
                        const number = $element.text();
                        $element.attr('aria-label', `${__('Container Nummer', 'container-block-designer')} ${number}`);
                    }
                }
                
                // Make focusable if interactive
                if ($element.is('[data-tooltip]') && !$element.attr('tabindex')) {
                    $element.attr('tabindex', '0');
                }
            });
            
            // Initialize keyboard navigation
            this.initKeyboardNavigation();
        }
        
        /**
         * Public API methods
         */
        getAPI() {
            return {
                showToast: (message, type, duration) => this.showToast(message, type, duration),
                copyToClipboard: (text) => this.copyToClipboard(text),
                formatNumber: (format, index) => this.formatNumber(format, index),
                updateResponsiveElements: () => this.updateResponsiveElements(),
                isInViewport: ($element, threshold) => this.isInViewport($element, threshold)
            };
        }
    }
    
    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        // Initialize main frontend handler
        const cbdFrontend = new CBD_Frontend();
        
        // Initialize optional features
        cbdFrontend.initScrollAnimations();
        cbdFrontend.initIntersectionObserver();
        cbdFrontend.initPrintHandling();
        cbdFrontend.initAccessibility();
        
        // Make API globally available
        window.CBD_Frontend_API = cbdFrontend.getAPI();
        
        // Trigger ready event
        $(document).trigger('cbd:ready');
        
        console.log('✅ Container Block Designer Frontend fully loaded');
    });
    
    /**
     * Handle dynamic content loading (AJAX, etc.)
     */
    $(document).on('cbd:content:loaded', function() {
        // Re-initialize for dynamically loaded content
        const cbdFrontend = new CBD_Frontend();
        console.log('🔄 Container Block Designer re-initialized for dynamic content');
    });
    
})(jQuery);

/**
 * Polyfills for older browsers
 */
(function() {
    // String.fromCharCode polyfill (if needed)
    if (!String.fromCharCode) {
        String.fromCharCode = function(code) {
            return String.prototype.charAt.call(String.prototype, code);
        };
    }
    
    // Object.keys polyfill
    if (!Object.keys) {
        Object.keys = function(obj) {
            var keys = [];
            for (var key in obj) {
                if (obj.hasOwnProperty(key)) {
                    keys.push(key);
                }
            }
            return keys;
        };
    }
})();

/**
 * Custom Events Documentation
 * 
 * cbd:ready - Fired when frontend is fully initialized
 * cbd:collapse:toggled - Fired when a container is collapsed/expanded
 * cbd:inview - Fired when a container enters viewport
 * cbd:content:loaded - Fire this event after loading dynamic content
 * 
 * Usage examples:
 * $(document).on('cbd:ready', function() { console.log('CBD Ready!'); });
 * $(document).trigger('cbd:content:loaded'); // After AJAX content load
 */