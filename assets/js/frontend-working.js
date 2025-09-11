/**
 * Container Block Designer - Working Simple Frontend
 * Version: 2.7.0-WORKING
 * ONLY the working toggle functionality - no complex features
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
        
        console.log('CBD Working Frontend: Ready');
    });
    
})(jQuery);