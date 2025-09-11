/**
 * Simple Frontend JavaScript - No complex logic
 * Version: 2.7.0-SIMPLE
 */

(function() {
    'use strict';
    
    console.log('Simple CBD Frontend loading...');
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        console.log('Simple CBD Frontend initializing...');
        
        // Find all containers
        const containers = document.querySelectorAll('.cbd-container');
        console.log('Found containers:', containers.length);
        
        containers.forEach((container, index) => {
            console.log(`Initializing container ${index + 1}`);
            initContainer(container);
        });
    }
    
    function initContainer(container) {
        // Find toggle button in this container
        const toggleBtn = container.querySelector('.cbd-collapse-toggle');
        if (!toggleBtn) {
            console.log('No toggle button found in container');
            return;
        }
        
        // Find content to toggle
        const contentToToggle = container.querySelector('.cbd-container-content');
        if (!contentToToggle) {
            console.log('No .cbd-container-content found in container');
            console.log('Available elements in container:', container.innerHTML);
            
            // Try alternative selectors
            const altContent = container.querySelector('.content') || 
                               container.querySelector('[class*="content"]') ||
                               container.querySelector('.cbd-content');
            
            if (altContent) {
                console.log('Found alternative content element:', altContent.className);
            } else {
                console.log('No content element found at all!');
                return;
            }
        }
        
        console.log('Setting up toggle for container:', container.id || 'unnamed');
        
        // Add click handler directly (no removal needed for first time)
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Toggle clicked for container:', container.id);
            
            // CRITICAL: First, ensure ALL container parts stay visible
            console.log('PROTECTION: Ensuring container visibility BEFORE toggle');
            
            // Force container visible
            container.style.display = 'block';
            container.style.visibility = 'visible';
            container.style.opacity = '1';
            
            // Force content wrapper visible  
            const contentWrapper = container.querySelector('.cbd-content');
            if (contentWrapper) {
                contentWrapper.style.display = 'block';
                contentWrapper.style.visibility = 'visible';
            }
            
            // Force container block visible
            const containerBlock = container.querySelector('.cbd-container-block');
            if (containerBlock) {
                containerBlock.style.display = 'block';
                containerBlock.style.visibility = 'visible';
            }
            
            // Force header visible
            const header = container.querySelector('.cbd-header');
            if (header) {
                header.style.display = 'block';
                header.style.visibility = 'visible';
            }
            
            // Force button visible
            toggleBtn.style.display = 'flex';
            toggleBtn.style.visibility = 'visible';
            
            console.log('PROTECTION: All parts forced visible');
            
            // Now do the simple toggle
            const currentDisplay = window.getComputedStyle(contentToToggle).display;
            if (currentDisplay === 'none') {
                // Show
                console.log('ACTION: Showing content');
                contentToToggle.style.display = 'block';
                updateButton(toggleBtn, false);
            } else {
                // Hide
                console.log('ACTION: Hiding content');
                contentToToggle.style.display = 'none';
                updateButton(toggleBtn, true);
            }
            
            // FINAL PROTECTION: Double-check container is still visible
            setTimeout(function() {
                container.style.display = 'block';
                if (containerBlock) containerBlock.style.display = 'block';
                if (header) header.style.display = 'block';
                toggleBtn.style.display = 'flex';
                console.log('FINAL CHECK: Container protected after toggle');
            }, 50);
            
            console.log('Toggle complete');
        });
    }
    
    function updateButton(button, collapsed) {
        const icon = button.querySelector('.dashicons');
        if (icon) {
            if (collapsed) {
                icon.className = 'dashicons dashicons-arrow-down-alt2';
            } else {
                icon.className = 'dashicons dashicons-arrow-up-alt2';
            }
        }
    }
    
    console.log('Simple CBD Frontend loaded');
    
})();