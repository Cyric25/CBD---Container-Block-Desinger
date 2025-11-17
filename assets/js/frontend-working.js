/**
 * Container Block Designer - Working Simple Frontend
 * Version: 2.8.0-WORKING
 * Toggle functionality + Header Menu System
 */

(function($) {
    'use strict';
    
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        
        // Remove ALL existing handlers to prevent conflicts
        $(document).off('click', '.cbd-collapse-toggle');
        $('.cbd-collapse-toggle').off();
        
        // Initialize selection-based menu system
        initializeSelectionMenu();
        
        // Set initial collapse states
        initializeCollapseStates();
        
        // ONE SIMPLE GLOBAL HANDLER
        $(document).on('click.cbd-working', '.cbd-collapse-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            
            var $container = $(this).closest('.cbd-container');
            var $contentToToggle = $container.find('.cbd-container-content');
            
            
            if ($contentToToggle.length === 0) {
                return;
            }
            
            // PROTECTION: Force all container parts visible
            $container.css('display', 'block');
            $container.find('.cbd-content').css('display', 'block');
            $container.find('.cbd-container-block').css('display', 'block');
            $container.find('.cbd-header').css('display', 'block');
            $(this).css('display', 'flex');
            
            // SIMPLE TOGGLE
            if ($contentToToggle.is(':visible')) {
                $contentToToggle.hide();
                $container.addClass('cbd-collapsed');
                $(this).find('.dashicons')
                    .removeClass('dashicons-arrow-up-alt2')
                    .addClass('dashicons-arrow-down-alt2');
            } else {
                $contentToToggle.show();
                $container.removeClass('cbd-collapsed');
                $(this).find('.dashicons')
                    .removeClass('dashicons-arrow-down-alt2')
                    .addClass('dashicons-arrow-up-alt2');
            }
            
        });
        
        // DROPDOWN MENU HANDLER
        $(document).on('click.cbd-menu', '.cbd-menu-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            
            var $menu = $(this).siblings('.cbd-dropdown-menu');
            var $allMenus = $('.cbd-dropdown-menu');
            
            // Close all other dropdowns first
            $allMenus.not($menu).removeClass('show');
            
            // Toggle current menu
            $menu.toggleClass('show');
            
        });
        
        // CLOSE DROPDOWN WHEN CLICKING OUTSIDE
        $(document).on('click.cbd-outside', function(e) {
            if (!$(e.target).closest('.cbd-header-menu').length) {
                $('.cbd-dropdown-menu').removeClass('show');
            }
        });
        
        // DROPDOWN ACTION HANDLERS
        $(document).on('click.cbd-actions', '.cbd-dropdown-item', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $button = $(this);
            var $container = $button.closest('.cbd-container');
            
            
            // Handle different button types
            if ($button.hasClass('cbd-copy-text')) {
                copyContainerText($container);
            } else if ($button.hasClass('cbd-screenshot')) {
                takeScreenshot($container);
            } else if ($button.hasClass('cbd-collapse-toggle')) {
                // For collapse, we handle it separately - this is just for consistency
                return; // Let the existing collapse handler take care of it
            }
            
            // Close dropdown and deselect container after action
            $(this).closest('.cbd-dropdown-menu').removeClass('show');
            $(this).closest('.cbd-container').removeClass('cbd-selected');
        });
        
    });
    
    // SELECTION-BASED MENU SYSTEM
    function initializeSelectionMenu() {
        
        // Detect touch device
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        if (isTouchDevice) {
            // Touch device: tap to select/deselect
            $(document).on('touchstart.cbd-selection', '.cbd-container', function(e) {
                e.stopPropagation();
                const $container = $(this);
                
                // Toggle selection
                if ($container.hasClass('cbd-selected')) {
                    $container.removeClass('cbd-selected');
                } else {
                    $('.cbd-container').removeClass('cbd-selected'); // Clear other selections
                    $container.addClass('cbd-selected');
                }
            });
            
            // Close menu when tapping outside
            $(document).on('touchstart.cbd-outside', function(e) {
                if (!$(e.target).closest('.cbd-container, .cbd-selection-menu').length) {
                    $('.cbd-container').removeClass('cbd-selected');
                    $('.cbd-dropdown-menu').removeClass('show');
                }
            });
            
        } else {
            // Desktop: hover to show menu
            $(document).on('mouseenter.cbd-hover', '.cbd-container', function() {
                const $container = $(this);
                $container.addClass('cbd-selected');
            });
            
            $(document).on('mouseleave.cbd-hover', '.cbd-container', function(e) {
                const $container = $(this);
                const $menu = $container.find('.cbd-selection-menu');
                
                // Don't hide if moving to the menu or dropdown
                if ($(e.relatedTarget).closest('.cbd-selection-menu, .cbd-dropdown-menu').length > 0) {
                    return;
                }
                
                // Only remove if dropdown is not open
                if (!$container.find('.cbd-dropdown-menu').hasClass('show')) {
                    $container.removeClass('cbd-selected');
                }
            });
            
            // Keep container selected when hovering menu
            $(document).on('mouseenter.cbd-menu-hover', '.cbd-selection-menu', function() {
                $(this).closest('.cbd-container').addClass('cbd-selected');
            });
            
            $(document).on('mouseleave.cbd-menu-hover', '.cbd-selection-menu', function() {
                const $container = $(this).closest('.cbd-container');
                if (!$container.find('.cbd-dropdown-menu').hasClass('show')) {
                    $container.removeClass('cbd-selected');
                }
            });
            
            // Close menu when clicking outside
            $(document).on('click.cbd-outside', function(e) {
                if (!$(e.target).closest('.cbd-container, .cbd-selection-menu').length) {
                    $('.cbd-container').removeClass('cbd-selected');
                    $('.cbd-dropdown-menu').removeClass('show');
                }
            });
        }
    }
    
    // INITIALIZE COLLAPSE STATES
    function initializeCollapseStates() {
        
        $('.cbd-container').each(function() {
            const $container = $(this);
            const collapseData = $container.data('collapse');
            
            if (collapseData && collapseData.enabled) {
                const defaultState = collapseData.defaultState || 'expanded';
                const $content = $container.find('.cbd-container-content');
                
                if (defaultState === 'collapsed') {
                    $content.hide();
                    $container.addClass('cbd-collapsed');
                    $container.find('.cbd-collapse-toggle .dashicons')
                        .removeClass('dashicons-arrow-up-alt2')
                        .addClass('dashicons-arrow-down-alt2');
                } else {
                    $content.show();
                    $container.removeClass('cbd-collapsed');
                    $container.find('.cbd-collapse-toggle .dashicons')
                        .removeClass('dashicons-arrow-down-alt2')
                        .addClass('dashicons-arrow-up-alt2');
                }
            }
        });
    }
    
    // SCREENSHOT FUNCTIONALITY
    function takeScreenshot($container) {
        
        if (typeof html2canvas === 'undefined') {
            alert('Screenshot-Funktion nicht verfügbar. html2canvas library fehlt.');
            return;
        }
        
        var element = $container.find('.cbd-container-content')[0];
        if (!element) {
            alert('Kein Inhalt zum Screenshot gefunden.');
            return;
        }
        
        html2canvas(element, {
            backgroundColor: null,
            scale: 2,
            logging: false
        }).then(function(canvas) {
            var link = document.createElement('a');
            link.download = 'container-screenshot-' + Date.now() + '.png';
            link.href = canvas.toDataURL();
            link.click();
        }).catch(function(error) {
            alert('Screenshot-Fehler: ' + error.message);
        });
    }
    
    // COPY TEXT FUNCTIONALITY
    function copyContainerText($container) {
        
        var $content = $container.find('.cbd-container-content');
        var text = $content.text().trim();
        
        if (!text) {
            alert('Kein Text zum Kopieren gefunden.');
            return;
        }
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyFeedback($container);
            }).catch(function(error) {
                fallbackCopyText(text);
            });
        } else {
            fallbackCopyText(text);
        }
    }
    
    // FALLBACK COPY METHOD
    function fallbackCopyText(text) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
        } catch (error) {
            alert('Text konnte nicht kopiert werden.');
        }
        
        document.body.removeChild(textArea);
    }
    
    // COPY FEEDBACK
    function showCopyFeedback($container) {
        var $feedback = $('<div class="cbd-copy-feedback">Text kopiert!</div>');
        $container.append($feedback);
        
        $feedback.fadeIn(200).delay(2000).fadeOut(400, function() {
            $(this).remove();
        });
    }
    
})(jQuery);