/**
 * Simple Collapse Functionality - Clean Implementation
 */

(function($) {
    'use strict';

    // Simple collapse handler - no complex logic
    function handleToggleClick(event) {
        event.preventDefault();
        event.stopPropagation();
        
        console.log('Toggle clicked');
        
        var $button = $(this);
        var $container = $button.closest('.cbd-container');
        var $contentToHide = $container.find('.cbd-container-content');
        
        console.log('Found container:', $container.length);
        console.log('Found content to hide:', $contentToHide.length);
        
        if ($contentToHide.length === 0) {
            console.log('No content found to toggle');
            return;
        }
        
        // Simple show/hide toggle
        if ($contentToHide.is(':visible')) {
            // Hide content
            $contentToHide.hide();
            $container.addClass('cbd-collapsed');
            $button.find('.dashicons')
                .removeClass('dashicons-arrow-up-alt2')
                .addClass('dashicons-arrow-down-alt2');
            console.log('Collapsed');
        } else {
            // Show content  
            $contentToHide.show();
            $container.removeClass('cbd-collapsed');
            $button.find('.dashicons')
                .removeClass('dashicons-arrow-down-alt2')
                .addClass('dashicons-arrow-up-alt2');
            console.log('Expanded');
        }
    }
    
    // Initialize when ready
    $(document).ready(function() {
        console.log('Simple collapse script loaded');
        
        // Remove any existing handlers
        $(document).off('click', '.cbd-collapse-toggle');
        
        // Add simple click handler
        $(document).on('click', '.cbd-collapse-toggle', handleToggleClick);
        
        console.log('Toggle handlers attached');
    });

})(jQuery);