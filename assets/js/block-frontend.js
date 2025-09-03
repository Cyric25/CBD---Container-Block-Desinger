/**
 * Container Block Designer - Frontend JavaScript
 * Version: 2.5.2
 * 
 * Datei: assets/js/block-frontend.js
 */

(function($) {
    'use strict';
    
    // Warte bis DOM geladen ist
    $(document).ready(function() {
        initContainerBlocks();
    });
    
    /**
     * Container Blocks initialisieren
     */
    function initContainerBlocks() {
        // Collapsible Feature
        initCollapsibleContainers();
        
        // Copy Feature
        initCopyButtons();
        
        // Screenshot Feature
        initScreenshotButtons();
        
        // Numbering Feature
        initNumbering();
    }
    
    /**
     * Collapsible Container initialisieren
     */
    function initCollapsibleContainers() {
        $('.cbd-container.cbd-collapsible').each(function() {
            const $container = $(this);
            const features = $container.data('features');
            
            // Toggle Button hinzufügen wenn nicht vorhanden
            if (!$container.find('.cbd-collapse-toggle').length) {
                const $toggle = $('<button class="cbd-collapse-toggle" aria-label="Ein-/Ausklappen"></button>');
                $container.prepend($toggle);
            }
            
            // Standard-Zustand setzen
            if (features && features.collapsible) {
                if (features.collapsible.defaultState === 'collapsed') {
                    $container.addClass('cbd-collapsed');
                }
            }
        });
        
        // Click Handler für Toggle
        $(document).on('click', '.cbd-collapse-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $toggle = $(this);
            const $container = $toggle.closest('.cbd-container');
            
            // Toggle State
            $container.toggleClass('cbd-collapsed');
            
            // Aria Label aktualisieren
            const isCollapsed = $container.hasClass('cbd-collapsed');
            const label = isCollapsed ? cbdFrontend.i18n.expanded : cbdFrontend.i18n.collapsed;
            $toggle.attr('aria-label', label);
            
            // Event auslösen
            $container.trigger('cbd:toggle', [isCollapsed]);
        });
        
        // Click auf Header (wenn aktiviert)
        $(document).on('click', '.cbd-collapse-header', function(e) {
            const $header = $(this);
            const $container = $header.closest('.cbd-container');
            const $toggle = $container.find('.cbd-collapse-toggle');
            
            if ($toggle.length) {
                $toggle.trigger('click');
            }
        });
    }
    
    /**
     * Copy Buttons initialisieren
     */
    function initCopyButtons() {
        // Copy Button Click Handler
        $(document).on('click', '.cbd-copy-button', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $container = $button.closest('.cbd-container');
            const $content = $container.find('.cbd-container-content');
            
            // Text extrahieren
            const textContent = extractTextContent($content[0]);
            
            if (!textContent) {
                showTooltip($button, cbdFrontend.i18n.noTextFound, 'error');
                return;
            }
            
            // In Zwischenablage kopieren
            copyToClipboard(textContent).then(function() {
                // Erfolg
                $button.addClass('cbd-copy-success');
                showTooltip($button, cbdFrontend.i18n.copySuccess, 'success');
                
                setTimeout(function() {
                    $button.removeClass('cbd-copy-success');
                }, 2000);
                
            }).catch(function(err) {
                // Fehler
                $button.addClass('cbd-copy-error');
                showTooltip($button, cbdFrontend.i18n.copyError, 'error');
                
                setTimeout(function() {
                    $button.removeClass('cbd-copy-error');
                }, 2000);
            });
        });
    }
    
    /**
     * Screenshot Buttons initialisieren
     */
    function initScreenshotButtons() {
        // Prüfe ob html2canvas verfügbar ist
        if (typeof html2canvas === 'undefined') {
            // Lade html2canvas dynamisch
            loadHtml2Canvas();
        }
        
        // Screenshot Button Click Handler
        $(document).on('click', '.cbd-screenshot-button', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $container = $button.closest('.cbd-container');
            
            // Prüfe ob html2canvas geladen ist
            if (typeof html2canvas === 'undefined') {
                showTooltip($button, cbdFrontend.i18n.screenshotUnavailable, 'error');
                return;
            }
            
            // Button deaktivieren
            $button.prop('disabled', true).text(cbdFrontend.i18n.creating);
            
            // Screenshot erstellen
            html2canvas($container[0], {
                backgroundColor: '#ffffff',
                logging: false,
                useCORS: true,
                scale: 2
            }).then(function(canvas) {
                // Canvas zu Blob konvertieren
                canvas.toBlob(function(blob) {
                    // Download-Link erstellen
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'container-screenshot-' + Date.now() + '.png';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    // Button zurücksetzen
                    $button.prop('disabled', false).text(cbdFrontend.i18n.screenshot);
                    showTooltip($button, cbdFrontend.i18n.screenshotSuccess, 'success');
                });
            }).catch(function(error) {
                console.error('Screenshot error:', error);
                $button.prop('disabled', false).text(cbdFrontend.i18n.screenshot);
                showTooltip($button, cbdFrontend.i18n.screenshotError, 'error');
            });
        });
    }
    
    /**
     * Numbering initialisieren
     */
    function initNumbering() {
        // Zähler für verschiedene Block-Typen
        const counters = {};
        
        $('.cbd-container').each(function() {
            const $container = $(this);
            const features = $container.data('features');
            
            if (!features || !features.numbering || !features.numbering.enabled) {
                return;
            }
            
            // Block-Typ ermitteln
            const classes = $container.attr('class').split(' ');
            let blockType = 'default';
            
            classes.forEach(function(className) {
                if (className.startsWith('cbd-container-')) {
                    blockType = className.replace('cbd-container-', '');
                }
            });
            
            // Counter initialisieren
            if (!counters[blockType]) {
                counters[blockType] = 0;
            }
            
            // Counter erhöhen
            counters[blockType]++;
            
            // Nummer-Element finden oder erstellen
            let $number = $container.find('.cbd-number');
            if (!$number.length) {
                const position = features.numbering.position || 'top-left';
                $number = $('<span class="cbd-number cbd-number-' + position + '"></span>');
                $container.prepend($number);
            }
            
            // Nummer formatieren und setzen
            const format = features.numbering.format || 'numeric';
            const formattedNumber = formatNumber(counters[blockType], format);
            $number.text(formattedNumber);
        });
    }
    
    /**
     * Text-Inhalt extrahieren
     */
    function extractTextContent(element) {
        // Clone Element um Original nicht zu verändern
        const clone = element.cloneNode(true);
        
        // Buttons und andere Nicht-Inhalt-Elemente entfernen
        const excludeSelectors = '.cbd-copy-button, .cbd-screenshot-button, .cbd-collapse-toggle, .cbd-icon, .cbd-number';
        $(clone).find(excludeSelectors).remove();
        
        // Text extrahieren
        let text = clone.textContent || clone.innerText || '';
        
        // Text bereinigen
        text = text.replace(/\s+/g, ' ').trim();
        
        return text;
    }
    
    /**
     * Text in Zwischenablage kopieren
     */
    function copyToClipboard(text) {
        // Moderne Clipboard API verwenden wenn verfügbar
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        
        // Fallback für ältere Browser
        return new Promise(function(resolve, reject) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            
            try {
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                resolve();
            } catch (err) {
                document.body.removeChild(textarea);
                reject(err);
            }
        });
    }
    
    /**
     * Nummer formatieren
     */
    function formatNumber(number, format) {
        switch (format) {
            case 'roman':
                return toRoman(number);
            case 'letters':
                return toLetters(number);
            case 'leading-zero':
                return number.toString().padStart(2, '0');
            default:
                return number.toString();
        }
    }
    
    /**
     * Zahl zu römischen Ziffern
     */
    function toRoman(num) {
        const romanNumerals = [
            ['M', 1000], ['CM', 900], ['D', 500], ['CD', 400],
            ['C', 100], ['XC', 90], ['L', 50], ['XL', 40],
            ['X', 10], ['IX', 9], ['V', 5], ['IV', 4], ['I', 1]
        ];
        
        let result = '';
        for (let [roman, value] of romanNumerals) {
            while (num >= value) {
                result += roman;
                num -= value;
            }
        }
        return result;
    }
    
    /**
     * Zahl zu Buchstaben
     */
    function toLetters(num) {
        let result = '';
        while (num > 0) {
            num--;
            result = String.fromCharCode(65 + (num % 26)) + result;
            num = Math.floor(num / 26);
        }
        return result;
    }
    
    /**
     * Tooltip anzeigen
     */
    function showTooltip($element, message, type) {
        // Existierenden Tooltip entfernen
        $('.cbd-tooltip').remove();
        
        // Neuen Tooltip erstellen
        const $tooltip = $('<div class="cbd-tooltip cbd-tooltip-' + type + '">' + message + '</div>');
        
        // Position berechnen
        const offset = $element.offset();
        const buttonWidth = $element.outerWidth();
        
        $tooltip.css({
            position: 'absolute',
            top: offset.top - 35,
            left: offset.left + (buttonWidth / 2),
            transform: 'translateX(-50%)',
            zIndex: 10000
        });
        
        // Zum Body hinzufügen
        $('body').append($tooltip);
        
        // Animation
        $tooltip.fadeIn(200);
        
        // Nach 2 Sekunden ausblenden
        setTimeout(function() {
            $tooltip.fadeOut(200, function() {
                $tooltip.remove();
            });
        }, 2000);
    }
    
    /**
     * html2canvas dynamisch laden
     */
    function loadHtml2Canvas() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script.onload = function() {
            console.log('html2canvas loaded successfully');
        };
        document.head.appendChild(script);
    }
    
    /**
     * Öffentliche API
     */
    window.CBDFrontend = {
        init: initContainerBlocks,
        copyToClipboard: copyToClipboard,
        formatNumber: formatNumber,
        extractTextContent: extractTextContent
    };
    
})(jQuery);