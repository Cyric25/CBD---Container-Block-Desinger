/**
 * Container Block Designer - Advanced Features
 * Version: 2.3.0 - FIXED
 */
(function($) {
    'use strict';
    
    // Ensure ajaxurl is defined
    if (typeof ajaxurl === 'undefined') {
        window.ajaxurl = '/wp-admin/admin-ajax.php';
    }
    
    // Wait for DOM ready
    $(document).ready(function() {
        console.log('CBD Advanced Features: Initializing...');
        
        // Initialize all container blocks
        initializeContainerBlocks();
        
        // Re-initialize after dynamic content loads
        $(document).on('cbd-blocks-loaded', initializeContainerBlocks);
    });
    
    /**
     * Initialize all container blocks with features
     */
    function initializeContainerBlocks() {
        $('.wp-block-container-block-designer-container').each(function() {
            const $container = $(this);
            
            // Skip if already initialized
            if ($container.hasClass('cbd-initialized')) {
                return;
            }
            
            // Mark as initialized
            $container.addClass('cbd-initialized');
            
            // Initialize features
            initializeIcon($container);
            initializeCollapse($container);
            initializeNumbering($container);
            initializeCopyText($container);
            initializeScreenshot($container);
        });
    }
    
    /**
     * Initialize Icon Feature
     */
    function initializeIcon($container) {
        if ($container.data('icon') !== 'true') return;
        
        const iconClass = $container.data('icon-value') || 'dashicons-admin-generic';
        const iconColor = $container.data('icon-color') || '#007cba';
        
        // Remove existing icon if any
        $container.find('.cbd-container-icon').remove();
        
        // Add icon
        const $icon = $('<span class="cbd-container-icon"></span>')
            .addClass('dashicons')
            .addClass(iconClass)
            .css('color', iconColor);
        
        $container.prepend($icon);
        $container.addClass('has-icon');
    }
    
    /**
     * Initialize Collapse Feature
     */
    function initializeCollapse($container) {
        if ($container.data('collapse') !== 'true') return;
        
        const defaultState = $container.data('collapse-default') || 'expanded';
        const saveState = $container.data('collapse-save') === 'true';
        const containerId = $container.data('container-id') || Math.random().toString(36).substr(2, 9);
        
        // Remove existing collapse button if any
        $container.find('.cbd-collapse-toggle').remove();
        
        // Create collapse button
        const $button = $('<button class="cbd-collapse-toggle"></button>')
            .html('<span class="dashicons dashicons-arrow-down-alt2"></span>')
            .attr('aria-label', 'Toggle content');
        
        // Create content wrapper if not exists
        let $content = $container.find('.cbd-collapse-content');
        if ($content.length === 0) {
            $content = $('<div class="cbd-collapse-content"></div>');
            $container.children().not('.cbd-container-icon, .cbd-collapse-toggle').wrapAll($content);
        }
        
        // Apply saved state or default
        let currentState = defaultState;
        if (saveState && typeof localStorage !== 'undefined') {
            const savedState = localStorage.getItem('cbd-collapse-' + containerId);
            if (savedState) {
                currentState = savedState;
            }
        }
        
        // Set initial state
        if (currentState === 'collapsed') {
            $content.hide();
            $button.find('.dashicons')
                .removeClass('dashicons-arrow-down-alt2')
                .addClass('dashicons-arrow-right-alt2');
            $container.addClass('is-collapsed');
        }
        
        // Add button to container
        $container.prepend($button);
        $container.addClass('has-collapse');
        
        // Handle click
        $button.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isCollapsed = $container.hasClass('is-collapsed');
            
            if (isCollapsed) {
                // Expand
                $content.slideDown(300);
                $button.find('.dashicons')
                    .removeClass('dashicons-arrow-right-alt2')
                    .addClass('dashicons-arrow-down-alt2');
                $container.removeClass('is-collapsed');
                
                if (saveState && typeof localStorage !== 'undefined') {
                    localStorage.setItem('cbd-collapse-' + containerId, 'expanded');
                }
            } else {
                // Collapse
                $content.slideUp(300);
                $button.find('.dashicons')
                    .removeClass('dashicons-arrow-down-alt2')
                    .addClass('dashicons-arrow-right-alt2');
                $container.addClass('is-collapsed');
                
                if (saveState && typeof localStorage !== 'undefined') {
                    localStorage.setItem('cbd-collapse-' + containerId, 'collapsed');
                }
            }
        });
    }
    
    /**
     * Initialize Numbering Feature
     */
    function initializeNumbering($container) {
        if ($container.data('numbering') !== 'true') return;
        
        const format = $container.data('numbering-format') || 'numeric';
        const startFrom = parseInt($container.data('numbering-start') || 1);
        const prefix = $container.data('numbering-prefix') || '';
        const suffix = $container.data('numbering-suffix') || '.';
        
        // Find all similar containers in parent
        const $parent = $container.parent();
        const $siblings = $parent.find('.wp-block-container-block-designer-container[data-numbering="true"]');
        
        let index = $siblings.index($container);
        if (index === -1) return;
        
        index = startFrom + index;
        
        // Format number
        let number;
        switch (format) {
            case 'alpha-lower':
                number = String.fromCharCode(96 + index);
                break;
            case 'alpha-upper':
                number = String.fromCharCode(64 + index);
                break;
            case 'roman-lower':
                number = toRoman(index).toLowerCase();
                break;
            case 'roman-upper':
                number = toRoman(index);
                break;
            default:
                number = index;
        }
        
        // Remove existing numbering
        $container.find('.cbd-container-number').remove();
        
        // Add numbering
        const $number = $('<span class="cbd-container-number"></span>')
            .text(prefix + number + suffix);
        
        $container.prepend($number);
        $container.addClass('has-numbering');
    }
    
    /**
     * Initialize Copy Text Feature
     */
    function initializeCopyText($container) {
        if ($container.data('copy-text') !== 'true') return;
        
        const buttonText = $container.data('copy-button-text') || 'Text kopieren';
        const position = $container.data('copy-position') || 'top-right';
        
        // Remove existing button if any
        $container.find('.cbd-copy-button').remove();
        
        // Create copy button
        const $button = $('<button class="cbd-copy-button"></button>')
            .html('<span class="dashicons dashicons-clipboard"></span> ' + buttonText)
            .addClass('position-' + position);
        
        $container.append($button);
        $container.addClass('has-copy-text');
        
        // Handle click
        $button.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const text = $container.text().replace($button.text(), '').trim();
            
            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showNotification('Text erfolgreich kopiert!', 'success');
                }).catch(function() {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        });
    }
    
    /**
     * Initialize Screenshot Feature
     */
    function initializeScreenshot($container) {
        if ($container.data('screenshot') !== 'true') return;
        
        const buttonText = $container.data('screenshot-button-text') || 'Screenshot';
        
        // Remove existing button if any
        $container.find('.cbd-screenshot-button').remove();
        
        // Create screenshot button
        const $button = $('<button class="cbd-screenshot-button"></button>')
            .html('<span class="dashicons dashicons-camera"></span> ' + buttonText);
        
        $container.append($button);
        $container.addClass('has-screenshot');
        
        // Handle click
        $button.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (typeof html2canvas === 'undefined') {
                showNotification('Screenshot-Funktion nicht verfügbar', 'error');
                return;
            }
            
            // Hide button temporarily
            $button.hide();
            
            // Take screenshot
            html2canvas($container[0], {
                backgroundColor: null,
                scale: 2
            }).then(function(canvas) {
                // Show button again
                $button.show();
                
                // Convert to blob and download
                canvas.toBlob(function(blob) {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'container-screenshot-' + Date.now() + '.png';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    showNotification('Screenshot gespeichert!', 'success');
                });
            }).catch(function(error) {
                $button.show();
                console.error('Screenshot error:', error);
                showNotification('Fehler beim Erstellen des Screenshots', 'error');
            });
        });
    }
    
    /**
     * Helper Functions
     */
    function toRoman(num) {
        const roman = {
            M: 1000, CM: 900, D: 500, CD: 400,
            C: 100, XC: 90, L: 50, XL: 40,
            X: 10, IX: 9, V: 5, IV: 4, I: 1
        };
        let str = '';
        for (let i of Object.keys(roman)) {
            const q = Math.floor(num / roman[i]);
            num -= q * roman[i];
            str += i.repeat(q);
        }
        return str;
    }
    
    function fallbackCopy(text) {
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        
        try {
            document.execCommand('copy');
            showNotification('Text erfolgreich kopiert!', 'success');
        } catch (err) {
            showNotification('Fehler beim Kopieren', 'error');
        }
        
        $temp.remove();
    }
    
    function showNotification(message, type) {
        const $notification = $('<div class="cbd-notification"></div>')
            .addClass('cbd-notification-' + type)
            .text(message);
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.addClass('show');
        }, 100);
        
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 3000);
    }
    
})(jQuery);