/**
 * Container Block Designer - Frontend Features
 * Interaktive Funktionen für Container-Blöcke
 */

(function($) {
    'use strict';
    
    class CBDContainerFeatures {
        constructor() {
            this.containers = [];
            this.init();
        }
        
        init() {
            // Wait for DOM ready
            $(document).ready(() => {
                this.initContainers();
                this.bindGlobalEvents();
                this.restoreStates();
            });
        }
        
        initContainers() {
            // Find all containers with features
            $('.cbd-container').each((index, element) => {
                const $element = $(element);
                const blockId = $element.data('block-id') || 'cbd-' + index;
                const features = this.extractFeatures($element);
                
                if (this.hasActiveFeatures(features)) {
                    const container = new CBDContainer(element, index, features);
                    this.containers.push(container);
                }
            });
            
            console.log(`CBD: Initialized ${this.containers.length} containers with features`);
        }
        
        extractFeatures($element) {
            // Try to get features from data attribute or parse from classes
            let features = $element.data('features');
            
            if (!features) {
                // Fallback: detect features from DOM structure
                features = {
                    icon: { enabled: $element.find('.cbd-header-icon').length > 0 },
                    collapse: { enabled: $element.find('.cbd-header-arrow').length > 0 },
                    numbering: { enabled: $element.find('.cbd-header-number').length > 0 },
                    copyText: { enabled: $element.find('.cbd-copy-button').length > 0 },
                    screenshot: { enabled: $element.find('.cbd-screenshot-button').length > 0 }
                };
            }
            
            return features;
        }
        
        hasActiveFeatures(features) {
            if (!features) return false;
            
            return Object.keys(features).some(key => 
                features[key] && features[key].enabled
            );
        }
        
        bindGlobalEvents() {
            // Close menus when clicking outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.cbd-menu-toggle, .cbd-menu-dropdown').length) {
                    $('.cbd-menu-dropdown').removeClass('show');
                }
            });
            
            // Keyboard navigation
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    $('.cbd-menu-dropdown').removeClass('show');
                }
            });
        }
        
        restoreStates() {
            // Restore collapsed states from localStorage
            this.containers.forEach(container => {
                container.restoreState();
            });
        }
    }
    
    class CBDContainer {
        constructor(element, index, features) {
            this.element = element;
            this.$element = $(element);
            this.index = index;
            this.features = features || {};
            this.blockId = this.$element.data('block-id') || 'cbd-' + index;
            this.isProcessing = false;
            
            this.init();
        }
        
        init() {
            this.wrapContainer();
            this.setupFeatures();
            this.bindEvents();
        }
        
        wrapContainer() {
            // Only wrap if not already wrapped
            if (this.$element.parent('.cbd-container-wrapper').length > 0) {
                this.$wrapper = this.$element.parent('.cbd-container-wrapper');
                return;
            }
            
            // Create wrapper
            this.$wrapper = $('<div class="cbd-container-wrapper has-features"></div>');
            this.$wrapper.attr('data-block-id', this.blockId);
            
            // Create header if features require it
            if (this.needsHeader()) {
                this.$header = this.createHeader();
                this.$wrapper.append(this.$header);
            }
            
            // Create body wrapper
            this.$body = $('<div class="cbd-container-body"></div>');
            
            // Wrap original container
            this.$element.wrap(this.$wrapper);
            this.$element.wrap(this.$body);
            
            // Update references
            this.$wrapper = this.$element.closest('.cbd-container-wrapper');
            this.$body = this.$element.closest('.cbd-container-body');
            
            // Add content wrapper
            this.$element.addClass('cbd-container-content');
        }
        
        needsHeader() {
            return this.features.icon?.enabled || 
                   this.features.collapse?.enabled || 
                   this.features.numbering?.enabled;
        }
        
        createHeader() {
            const $header = $('<div class="cbd-container-header"></div>');
            
            // Add icon
            if (this.features.icon?.enabled) {
                const iconClass = this.features.icon.value || 'dashicons-admin-generic';
                const iconColor = this.features.icon.color || '#007cba';
                $header.append(`
                    <div class="cbd-header-icon" style="color: ${iconColor}">
                        <span class="dashicons ${iconClass}"></span>
                    </div>
                `);
            }
            
            // Add numbering
            if (this.features.numbering?.enabled) {
                const number = this.calculateNumber();
                $header.append(`
                    <span class="cbd-header-number">${number}</span>
                `);
            }
            
            // Add title
            const title = this.$element.find('h1, h2, h3, h4, h5, h6').first().text() || 
                         'Container ' + (this.index + 1);
            $header.append(`
                <span class="cbd-header-title">${title}</span>
            `);
            
            // Add collapse arrow
            if (this.features.collapse?.enabled) {
                $header.addClass('clickable');
                $header.append(`
                    <span class="cbd-header-arrow">
                        <svg width="16" height="16" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M8 10.5L3 5.5l1.5-1.5L8 7.5 11.5 4l1.5 1.5z"/>
                        </svg>
                    </span>
                `);
            }
            
            // Add menu
            if (this.hasMenuItems()) {
                $header.append(this.createMenu());
            }
            
            return $header;
        }
        
        calculateNumber() {
            const format = this.features.numbering?.format || 'numeric';
            const startFrom = this.features.numbering?.startFrom || 1;
            const prefix = this.features.numbering?.prefix || '';
            const suffix = this.features.numbering?.suffix || '.';
            const currentNumber = startFrom + this.index;
            
            let formatted = '';
            
            switch (format) {
                case 'roman':
                    formatted = this.toRoman(currentNumber);
                    break;
                case 'letters':
                    formatted = this.toLetters(currentNumber);
                    break;
                case 'custom':
                    formatted = prefix + currentNumber + suffix;
                    return formatted;
                default:
                    formatted = currentNumber.toString();
            }
            
            return prefix + formatted + suffix;
        }
        
        toRoman(num) {
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
        }
        
        toLetters(num) {
            let result = '';
            while (num > 0) {
                num--;
                result = String.fromCharCode(65 + (num % 26)) + result;
                num = Math.floor(num / 26);
            }
            return result;
        }
        
        hasMenuItems() {
            return this.features.copyText?.enabled || 
                   this.features.screenshot?.enabled;
        }
        
        createMenu() {
            const $menu = $('<div class="cbd-header-menu"></div>');
            
            const $toggle = $(`
                <button class="cbd-menu-toggle" aria-label="Menu">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <circle cx="10" cy="5" r="1.5" fill="currentColor"/>
                        <circle cx="10" cy="10" r="1.5" fill="currentColor"/>
                        <circle cx="10" cy="15" r="1.5" fill="currentColor"/>
                    </svg>
                </button>
            `);
            
            const $dropdown = $('<div class="cbd-menu-dropdown"></div>');
            
            // Add copy text option
            if (this.features.copyText?.enabled) {
                const buttonText = this.features.copyText.buttonText || 'Text kopieren';
                $dropdown.append(`
                    <button class="cbd-menu-item cbd-copy-text" type="button">
                        <span class="dashicons dashicons-clipboard"></span>
                        ${buttonText}
                    </button>
                `);
            }
            
            // Add screenshot option
            if (this.features.screenshot?.enabled) {
                const buttonText = this.features.screenshot.buttonText || 'Screenshot';
                $dropdown.append(`
                    <button class="cbd-menu-item cbd-take-screenshot" type="button">
                        <span class="dashicons dashicons-camera"></span>
                        ${buttonText}
                    </button>
                `);
            }
            
            $menu.append($toggle, $dropdown);
            return $menu;
        }
        
        setupFeatures() {
            // Add copy button if enabled
            if (this.features.copyText?.enabled && !this.hasMenuItems()) {
                this.addCopyButton();
            }
            
            // Add screenshot button if enabled
            if (this.features.screenshot?.enabled && !this.hasMenuItems()) {
                this.addScreenshotButton();
            }
        }
        
        addCopyButton() {
            const buttonText = this.features.copyText?.buttonText || 'Text kopieren';
            const position = this.features.copyText?.position || 'top-right';
            
            const $button = $(`
                <button class="cbd-copy-button" type="button">
                    <span class="dashicons dashicons-clipboard"></span>
                    <span>${buttonText}</span>
                </button>
            `);
            
            if (position === 'top-right') {
                this.$element.prepend($button);
            } else {
                this.$element.append($button);
            }
        }
        
        addScreenshotButton() {
            const buttonText = this.features.screenshot?.buttonText || 'Screenshot';
            
            const $button = $(`
                <button class="cbd-screenshot-button" type="button">
                    <span class="dashicons dashicons-camera"></span>
                    <span>${buttonText}</span>
                </button>
            `);
            
            this.$element.prepend($button);
        }
        
        bindEvents() {
            // Collapse/Expand
            if (this.features.collapse?.enabled) {
                this.$header.on('click', (e) => {
                    if (!$(e.target).closest('.cbd-menu-toggle, .cbd-menu-dropdown').length) {
                        this.toggleCollapse();
                    }
                });
            }
            
            // Menu toggle
            this.$wrapper.on('click', '.cbd-menu-toggle', (e) => {
                e.stopPropagation();
                this.toggleMenu();
            });
            
            // Copy text
            this.$wrapper.on('click', '.cbd-copy-text, .cbd-copy-button', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.copyContent($(e.currentTarget));
            });
            
            // Screenshot
            this.$wrapper.on('click', '.cbd-take-screenshot, .cbd-screenshot-button', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.takeScreenshot($(e.currentTarget));
            });
        }
        
        toggleCollapse() {
            const isCollapsed = this.$wrapper.hasClass('collapsed');
            
            if (isCollapsed) {
                this.expand();
            } else {
                this.collapse();
            }
        }
        
        collapse() {
            const animationSpeed = this.features.collapse?.animationSpeed || 300;
            
            this.$wrapper.addClass('collapsed');
            this.$body.slideUp(animationSpeed);
            
            // Save state
            if (this.features.collapse?.saveState !== false) {
                this.saveState('collapsed');
            }
            
            // Trigger event
            this.$element.trigger('cbd:collapsed', [this]);
        }
        
        expand() {
            const animationSpeed = this.features.collapse?.animationSpeed || 300;
            
            this.$wrapper.removeClass('collapsed');
            this.$body.slideDown(animationSpeed);
            
            // Save state
            if (this.features.collapse?.saveState !== false) {
                this.saveState('expanded');
            }
            
            // Trigger event
            this.$element.trigger('cbd:expanded', [this]);
        }
        
        toggleMenu() {
            const $dropdown = this.$wrapper.find('.cbd-menu-dropdown');
            
            if ($dropdown.hasClass('show')) {
                $dropdown.removeClass('show');
            } else {
                // Close all other menus
                $('.cbd-menu-dropdown').removeClass('show');
                $dropdown.addClass('show');
            }
        }
        
        copyContent($button) {
            if (this.isProcessing) return;
            
            this.isProcessing = true;
            const originalText = $button.find('span:last').text();
            
            // Get content based on format
            const format = this.features.copyText?.copyFormat || 'plain';
            let content = '';
            
            if (format === 'html') {
                content = this.$element.html();
            } else {
                content = this.$element.text().trim();
            }
            
            // Copy to clipboard
            this.copyToClipboard(content).then(() => {
                // Success feedback
                $button.addClass('success');
                $button.find('span:last').text('Kopiert!');
                this.showToast('Text wurde in die Zwischenablage kopiert', 'success');
                
                setTimeout(() => {
                    $button.removeClass('success');
                    $button.find('span:last').text(originalText);
                }, 2000);
            }).catch((err) => {
                // Error feedback
                this.showToast('Kopieren fehlgeschlagen', 'error');
                console.error('Copy failed:', err);
            }).finally(() => {
                this.isProcessing = false;
            });
        }
        
        copyToClipboard(text) {
            // Modern clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(text);
            }
            
            // Fallback method
            return new Promise((resolve, reject) => {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                
                document.body.appendChild(textarea);
                textarea.select();
                
                try {
                    const success = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    
                    if (success) {
                        resolve();
                    } else {
                        reject(new Error('Copy command failed'));
                    }
                } catch (err) {
                    document.body.removeChild(textarea);
                    reject(err);
                }
            });
        }
        
        takeScreenshot($button) {
            if (this.isProcessing) return;
            
            // Check if html2canvas is loaded
            if (typeof html2canvas === 'undefined') {
                this.loadScreenshotLibrary().then(() => {
                    this.performScreenshot($button);
                }).catch(() => {
                    this.showToast('Screenshot-Funktion nicht verfügbar', 'error');
                });
            } else {
                this.performScreenshot($button);
            }
        }
        
        loadScreenshotLibrary() {
            return new Promise((resolve, reject) => {
                if (typeof html2canvas !== 'undefined') {
                    resolve();
                    return;
                }
                
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }
        
        performScreenshot($button) {
            this.isProcessing = true;
            const originalText = $button.find('span:last').text();
            
            // Show processing state
            $button.addClass('processing');
            $button.find('span:last').text('Erstelle Screenshot...');
            this.showLoadingOverlay();
            
            // Screenshot options
            const format = this.features.screenshot?.format || 'png';
            const quality = this.features.screenshot?.quality || 0.95;
            
            // Take screenshot
            html2canvas(this.$wrapper[0], {
                backgroundColor: '#ffffff',
                scale: 2,
                logging: false,
                useCORS: true,
                allowTaint: true
            }).then(canvas => {
                // Convert to blob
                canvas.toBlob(blob => {
                    // Create download link
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `container-${this.blockId}-${Date.now()}.${format}`;
                    
                    // Trigger download
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Clean up
                    setTimeout(() => URL.revokeObjectURL(url), 100);
                    
                    // Success feedback
                    this.showToast('Screenshot wurde heruntergeladen', 'success');
                    
                }, format === 'jpeg' ? 'image/jpeg' : 'image/png', quality);
                
            }).catch(err => {
                console.error('Screenshot failed:', err);
                this.showToast('Screenshot fehlgeschlagen', 'error');
                
            }).finally(() => {
                // Reset button
                $button.removeClass('processing');
                $button.find('span:last').text(originalText);
                this.hideLoadingOverlay();
                this.isProcessing = false;
            });
        }
        
        showLoadingOverlay() {
            const $overlay = $(`
                <div class="cbd-loading-overlay">
                    <div class="cbd-loading-spinner"></div>
                </div>
            `);
            
            this.$wrapper.append($overlay);
        }
        
        hideLoadingOverlay() {
            this.$wrapper.find('.cbd-loading-overlay').fadeOut(200, function() {
                $(this).remove();
            });
        }
        
        showToast(message, type = 'info') {
            // Remove existing toasts
            $('.cbd-toast').remove();
            
            const $toast = $(`
                <div class="cbd-toast ${type}">
                    ${message}
                </div>
            `);
            
            $('body').append($toast);
            
            // Animate in
            setTimeout(() => {
                $toast.addClass('show');
            }, 10);
            
            // Auto-hide
            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => {
                    $toast.remove();
                }, 300);
            }, 3000);
        }
        
        saveState(state) {
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem(`cbd-state-${this.blockId}`, state);
            }
        }
        
        restoreState() {
            if (typeof(Storage) !== "undefined") {
                const savedState = localStorage.getItem(`cbd-state-${this.blockId}`);
                
                if (savedState === 'collapsed') {
                    this.$wrapper.addClass('collapsed');
                    this.$body.hide();
                } else if (savedState === 'expanded') {
                    this.$wrapper.removeClass('collapsed');
                    this.$body.show();
                } else {
                    // Use default state
                    const defaultState = this.features.collapse?.defaultState || 'expanded';
                    if (defaultState === 'collapsed') {
                        this.$wrapper.addClass('collapsed');
                        this.$body.hide();
                    }
                }
            }
        }
    }
    
    // Initialize on document ready
    $(document).ready(() => {
        window.CBDContainerFeatures = new CBDContainerFeatures();
    });
    
    // Also initialize on Gutenberg content changes
    if (window.wp && window.wp.domReady) {
        wp.domReady(() => {
            // Re-initialize when blocks are added/changed
            const observer = new MutationObserver(() => {
                if (window.CBDContainerFeatures) {
                    window.CBDContainerFeatures.initContainers();
                }
            });
            
            const editorCanvas = document.querySelector('.block-editor-writing-flow');
            if (editorCanvas) {
                observer.observe(editorCanvas, {
                    childList: true,
                    subtree: true
                });
            }
        });
    }
    
})(jQuery);