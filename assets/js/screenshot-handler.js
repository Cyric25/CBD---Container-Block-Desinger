/**
 * Container Block Designer - Screenshot Handler
 * Basis-Implementierung für Screenshot-Funktionalität
 * 
 * @package ContainerBlockDesigner
 * @version 1.0.0
 */

class CBDScreenshotHandler {
    constructor() {
        // Feature detection
        this.capabilities = {
            hasClipboardAPI: this.checkClipboardAPI(),
            hasClipboardItem: typeof ClipboardItem !== 'undefined',
            isSecureContext: window.isSecureContext,
            isAppleDevice: this.detectAppleDevice(),
            isSafari: this.detectSafari()
        };
        
        // Configuration
        this.config = {
            maxCanvasSize: this.capabilities.isAppleDevice ? 4096 : 8192,
            defaultScale: this.capabilities.isAppleDevice ? 1.5 : 2,
            jpegQuality: 0.92,
            timeout: 30000, // 30 seconds
            retryAttempts: 3
        };
        
        // Libraries state
        this.libraries = {
            html2canvas: typeof html2canvas !== 'undefined',
            modernScreenshot: typeof modernScreenshot !== 'undefined'
        };
        
        // Progress tracking
        this.progress = {
            current: 0,
            total: 100
        };
    }
    
    /**
     * Feature detection methods
     */
    checkClipboardAPI() {
        return !!(navigator.clipboard && typeof navigator.clipboard.write === 'function');
    }
    
    detectAppleDevice() {
        return /iPad|iPhone|iPod|Mac/.test(navigator.userAgent) ||
               (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }
    
    detectSafari() {
        return /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    }
    
    /**
     * Main screenshot method
     */
    async takeScreenshot(element, options = {}) {
        try {
            // Show loading indicator
            this.showLoading(element);
            
            // Prepare element
            this.prepareElement(element);
            
            // Determine best method
            const method = this.determineMethod();
            let canvas;
            
            // Execute screenshot with chosen method
            switch (method) {
                case 'modern-screenshot':
                    canvas = await this.modernScreenshotMethod(element, options);
                    break;
                case 'html2canvas':
                    canvas = await this.html2canvasMethod(element, options);
                    break;
                default:
                    canvas = await this.svgFallbackMethod(element, options);
            }
            
            // Convert to blob
            const blob = await this.canvasToBlob(canvas);
            
            // Try clipboard first, fallback to download
            const success = await this.copyToClipboard(blob);
            if (!success) {
                this.downloadScreenshot(blob, options.filename);
            }
            
            // Cleanup
            this.cleanup(canvas);
            this.hideLoading(element);
            
            return true;
            
        } catch (error) {
            console.error('Screenshot failed:', error);
            this.hideLoading(element);
            this.showError(error.message);
            return false;
        }
    }
    
    /**
     * Prepare element for screenshot
     */
    prepareElement(element) {
        // Add marker for screenshot mode
        element.setAttribute('data-screenshot', 'active');
        
        // Force reflow
        void element.offsetHeight;
        
        // Wait for animations to settle
        return new Promise(resolve => setTimeout(resolve, 100));
    }
    
    /**
     * Determine best screenshot method based on device/browser
     */
    determineMethod() {
        if (this.capabilities.isAppleDevice && this.libraries.modernScreenshot) {
            return 'modern-screenshot';
        }
        
        if (this.libraries.html2canvas) {
            return 'html2canvas';
        }
        
        return 'svg-fallback';
    }
    
    /**
     * Modern Screenshot method (best for Apple devices)
     */
    async modernScreenshotMethod(element, options) {
        const config = {
            backgroundColor: '#ffffff',
            scale: options.scale || this.config.defaultScale,
            style: {
                // Reset transforms for Safari
                transform: 'none',
                transformOrigin: 'top left'
            }
        };
        
        // Check canvas size limits
        const bounds = element.getBoundingClientRect();
        const estimatedWidth = bounds.width * config.scale;
        const estimatedHeight = bounds.height * config.scale;
        
        if (estimatedWidth > this.config.maxCanvasSize || estimatedHeight > this.config.maxCanvasSize) {
            // Auto-scale down
            config.scale = Math.min(
                this.config.maxCanvasSize / bounds.width,
                this.config.maxCanvasSize / bounds.height
            );
        }
        
        const dataUrl = await modernScreenshot.domToPng(element, config);
        return this.dataUrlToCanvas(dataUrl);
    }
    
    /**
     * html2canvas method with Safari optimizations
     */
    async html2canvasMethod(element, options) {
        const config = {
            backgroundColor: '#ffffff',
            scale: options.scale || this.config.defaultScale,
            useCORS: true,
            allowTaint: false,
            logging: false,
            onclone: (clonedDoc) => {
                if (this.capabilities.isSafari) {
                    // Safari-specific fixes
                    this.applySafariFixes(clonedDoc);
                }
            }
        };
        
        // iOS Canvas size limitation
        if (this.capabilities.isAppleDevice) {
            const bounds = element.getBoundingClientRect();
            if (bounds.width > this.config.maxCanvasSize || bounds.height > this.config.maxCanvasSize) {
                config.scale = Math.min(
                    this.config.maxCanvasSize / bounds.width,
                    this.config.maxCanvasSize / bounds.height
                );
            }
        }
        
        return await html2canvas(element, config);
    }
    
    /**
     * SVG foreignObject fallback method
     */
    async svgFallbackMethod(element, options) {
        const bounds = element.getBoundingClientRect();
        const width = bounds.width;
        const height = bounds.height;
        
        // Create SVG
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', width);
        svg.setAttribute('height', height);
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        
        // Create foreignObject
        const foreignObject = document.createElementNS('http://www.w3.org/2000/svg', 'foreignObject');
        foreignObject.setAttribute('width', '100%');
        foreignObject.setAttribute('height', '100%');
        
        // Clone element
        const clone = element.cloneNode(true);
        foreignObject.appendChild(clone);
        svg.appendChild(foreignObject);
        
        // Convert to data URL
        const svgData = new XMLSerializer().serializeToString(svg);
        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(svgBlob);
        
        // Convert to canvas
        const img = new Image();
        img.src = url;
        
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
        });
        
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        
        URL.revokeObjectURL(url);
        return canvas;
    }
    
    /**
     * Apply Safari-specific fixes to cloned document
     */
    applySafariFixes(doc) {
        // Fix CSS transforms
        const elements = doc.querySelectorAll('*');
        elements.forEach(el => {
            const style = window.getComputedStyle(el);
            if (style.transform && style.transform !== 'none') {
                el.style.transform = 'none';
            }
        });
        
        // Fix position fixed elements
        doc.querySelectorAll('[style*="position: fixed"]').forEach(el => {
            el.style.position = 'absolute';
        });
    }
    
    /**
     * Convert data URL to canvas
     */
    async dataUrlToCanvas(dataUrl) {
        const img = new Image();
        img.src = dataUrl;
        
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
        });
        
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        
        return canvas;
    }
    
    /**
     * Convert canvas to blob
     */
    async canvasToBlob(canvas) {
        return new Promise((resolve, reject) => {
            canvas.toBlob(
                blob => {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error('Failed to convert canvas to blob'));
                    }
                },
                'image/png',
                this.config.jpegQuality
            );
        });
    }
    
    /**
     * Copy screenshot to clipboard
     */
    async copyToClipboard(blob) {
        if (!this.capabilities.hasClipboardAPI || !this.capabilities.hasClipboardItem) {
            return false;
        }
        
        try {
            // Safari needs special handling
            if (this.capabilities.isSafari) {
                return await this.safariClipboardCopy(blob);
            }
            
            // Standard approach
            const clipboardItem = new ClipboardItem({
                'image/png': blob
            });
            
            await navigator.clipboard.write([clipboardItem]);
            this.showSuccess('Screenshot copied to clipboard!');
            return true;
            
        } catch (error) {
            console.warn('Clipboard copy failed:', error);
            return false;
        }
    }
    
    /**
     * Safari-specific clipboard copy
     */
    async safariClipboardCopy(blob) {
        return new Promise((resolve) => {
            // Safari requires the ClipboardItem to be created with a Promise
            const clipboardItem = new ClipboardItem({
                'image/png': new Promise((resolve) => {
                    // Small delay for Safari
                    setTimeout(() => resolve(blob), 100);
                })
            });
            
            // Use setTimeout to ensure we're in a user gesture context
            setTimeout(async () => {
                try {
                    await navigator.clipboard.write([clipboardItem]);
                    this.showSuccess('Screenshot copied to clipboard!');
                    resolve(true);
                } catch (error) {
                    console.warn('Safari clipboard copy failed:', error);
                    resolve(false);
                }
            }, 0);
        });
    }
    
    /**
     * Download screenshot as file
     */
    downloadScreenshot(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename || `screenshot-${Date.now()}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        this.showSuccess('Screenshot downloaded!');
    }
    
    /**
     * UI Methods
     */
    showLoading(element) {
        const $element = jQuery(element);
        const $loading = jQuery('<div class="cbd-screenshot-loading">')
            .html('<div class="cbd-spinner"></div><span>Creating screenshot...</span>');
        
        $element.css('position', 'relative').append($loading);
    }
    
    hideLoading(element) {
        jQuery(element).find('.cbd-screenshot-loading').remove();
        element.removeAttribute('data-screenshot');
    }
    
    showSuccess(message) {
        // Use existing notification system
        if (window.cbdFeatures && window.cbdFeatures.containers[0]) {
            window.cbdFeatures.containers[0].showNotification(message, 'success');
        }
    }
    
    showError(message) {
        if (window.cbdFeatures && window.cbdFeatures.containers[0]) {
            window.cbdFeatures.containers[0].showNotification(message, 'error');
        }
    }
    
    /**
     * Cleanup
     */
    cleanup(canvas) {
        // Clear canvas to free memory
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        canvas.width = 0;
        canvas.height = 0;
    }
}

// Export for use
window.CBDScreenshotHandler = CBDScreenshotHandler;