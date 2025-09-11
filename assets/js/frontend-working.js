/**
 * Container Block Designer - Working Simple Frontend
 * Version: 2.8.0-WORKING
 * Toggle functionality + Header Menu System
 */

(function($) {
    'use strict';
    
    console.log('CBD Working Frontend: Loading...');
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('CBD Working Frontend: Initializing...');
        
        // Remove ALL existing handlers to prevent conflicts
        $(document).off('click', '.cbd-collapse-toggle');
        $('.cbd-collapse-toggle').off();
        
        // ONE SIMPLE GLOBAL HANDLER
        $(document).on('click.cbd-working', '.cbd-collapse-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('CBD Working: Toggle clicked');
            
            var $container = $(this).closest('.cbd-container');
            var $contentToToggle = $container.find('.cbd-container-content');
            
            console.log('CBD Working: Container found:', $container.length);
            console.log('CBD Working: Content found:', $contentToToggle.length);
            
            if ($contentToToggle.length === 0) {
                console.log('CBD Working: No content - aborting');
                return;
            }
            
            // PROTECTION: Force all container parts visible
            console.log('CBD Working: Protecting container visibility');
            $container.css('display', 'block');
            $container.find('.cbd-content').css('display', 'block');
            $container.find('.cbd-container-block').css('display', 'block');
            $container.find('.cbd-header').css('display', 'block');
            $(this).css('display', 'flex');
            
            // SIMPLE TOGGLE
            if ($contentToToggle.is(':visible')) {
                console.log('CBD Working: Hiding content');
                $contentToToggle.hide();
                $(this).find('.dashicons')
                    .removeClass('dashicons-arrow-up-alt2')
                    .addClass('dashicons-arrow-down-alt2');
            } else {
                console.log('CBD Working: Showing content');
                $contentToToggle.show();
                $(this).find('.dashicons')
                    .removeClass('dashicons-arrow-down-alt2')
                    .addClass('dashicons-arrow-up-alt2');
            }
            
            console.log('CBD Working: Toggle complete');
        });
        
        // DROPDOWN MENU HANDLER
        $(document).on('click.cbd-menu', '.cbd-menu-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('CBD Working: Menu toggle clicked');
            
            var $menu = $(this).siblings('.cbd-dropdown-menu');
            var $allMenus = $('.cbd-dropdown-menu');
            
            // Close all other dropdowns first
            $allMenus.not($menu).removeClass('show');
            
            // Toggle current menu
            $menu.toggleClass('show');
            
            console.log('CBD Working: Menu toggled');
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
            
            console.log('CBD Working: Dropdown action clicked:', $button.attr('class'));
            
            // Handle different button types
            if ($button.hasClass('cbd-copy-text')) {
                copyContainerText($container);
            } else if ($button.hasClass('cbd-screenshot')) {
                takeScreenshot($container);
            } else if ($button.hasClass('cbd-collapse-toggle')) {
                // For collapse, we handle it separately - this is just for consistency
                return; // Let the existing collapse handler take care of it
            }
            
            // Close dropdown after action (except for collapse which handles its own state)
            if (!$button.hasClass('cbd-collapse-toggle')) {
                $(this).closest('.cbd-dropdown-menu').removeClass('show');
            }
        });
        
        console.log('CBD Working Frontend: Ready');
    });
    
    // SCREENSHOT FUNCTIONALITY
    function takeScreenshot($container) {
        console.log('CBD Working: Taking screenshot');
        
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
            console.log('CBD Working: Screenshot saved');
        }).catch(function(error) {
            console.error('CBD Working: Screenshot error:', error);
            alert('Screenshot-Fehler: ' + error.message);
        });
    }
    
    // COPY TEXT FUNCTIONALITY
    function copyContainerText($container) {
        console.log('CBD Working: Copying text');
        
        var $content = $container.find('.cbd-container-content');
        var text = $content.text().trim();
        
        if (!text) {
            alert('Kein Text zum Kopieren gefunden.');
            return;
        }
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                console.log('CBD Working: Text copied to clipboard');
                showCopyFeedback($container);
            }).catch(function(error) {
                console.error('CBD Working: Clipboard error:', error);
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
            console.log('CBD Working: Text copied via fallback');
        } catch (error) {
            console.error('CBD Working: Fallback copy failed:', error);
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