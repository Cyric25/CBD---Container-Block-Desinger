/**
 * Container Block Designer - Frontend JavaScript
 * Version: 2.5.0
 * 
 * Verwaltet alle Frontend-Interaktionen der Container Blocks
 */

(function($) {
    'use strict';
    
    // CBD Frontend Namespace
    window.CBD_Frontend = {
        
        /**
         * Initialisierung
         */
        init: function() {
            this.initCollapse();
            this.initCopyText();
            this.initNumbering();
            this.initTooltips();
            this.initAnimations();
            this.trackInteractions();
        },
        
        /**
         * Collapse/Expand Funktionalität
         */
        initCollapse: function() {
            $('.cbd-collapse-toggle').each(function() {
                var $toggle = $(this);
                var $container = $toggle.closest('.cbd-container-block');
                var $content = $container.find('.cbd-container-content');
                var state = $toggle.data('state') || 'expanded';
                
                // Initial-Status setzen
                if (state === 'collapsed') {
                    $content.hide();
                    $toggle.find('.dashicons')
                        .removeClass('dashicons-arrow-up')
                        .addClass('dashicons-arrow-down');
                }
                
                // Click-Handler
                $toggle.on('click', function(e) {
                    e.preventDefault();
                    
                    var currentState = $toggle.data('state');
                    
                    if (currentState === 'expanded') {
                        // Einklappen
                        $content.slideUp(300, function() {
                            $container.addClass('cbd-collapsed');
                        });
                        $toggle.data('state', 'collapsed');
                        $toggle.find('.dashicons')
                            .removeClass('dashicons-arrow-up')
                            .addClass('dashicons-arrow-down');
                        
                        // Tooltip aktualisieren
                        $toggle.attr('title', cbdFrontend.strings.expand || 'Ausklappen');
                        
                    } else {
                        // Ausklappen
                        $content.slideDown(300, function() {
                            $container.removeClass('cbd-collapsed');
                        });
                        $toggle.data('state', 'expanded');
                        $toggle.find('.dashicons')
                            .removeClass('dashicons-arrow-down')
                            .addClass('dashicons-arrow-up');
                        
                        // Tooltip aktualisieren
                        $toggle.attr('title', cbdFrontend.strings.collapse || 'Einklappen');
                    }
                    
                    // Interaktion tracken
                    CBD_Frontend.track($container.data('block-id'), 'collapse-toggle');
                });
                
                // Tooltip hinzufügen
                $toggle.attr('title', 
                    state === 'expanded' ? 
                    (cbdFrontend.strings.collapse || 'Einklappen') : 
                    (cbdFrontend.strings.expand || 'Ausklappen')
                );
            });
        },
        
        /**
         * Text kopieren Funktionalität
         */
        initCopyText: function() {
            $('.cbd-copy-text').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $container = $button.closest('.cbd-container-block');
                var $content = $container.find('.cbd-container-content');
                var originalText = $button.text();
                
                // Text extrahieren (ohne HTML)
                var textToCopy = $content.text().trim();
                
                // Moderne Clipboard API verwenden
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textToCopy)
                        .then(function() {
                            CBD_Frontend.showCopySuccess($button, originalText);
                        })
                        .catch(function(err) {
                            CBD_Frontend.fallbackCopy(textToCopy, $button, originalText);
                        });
                } else {
                    // Fallback für ältere Browser
                    CBD_Frontend.fallbackCopy(textToCopy, $button, originalText);
                }
                
                // Interaktion tracken
                CBD_Frontend.track($container.data('block-id'), 'copy-text');
            });
        },
        
        /**
         * Fallback Copy-Methode
         */
        fallbackCopy: function(text, $button, originalText) {
            var $temp = $('<textarea>');
            $temp.css({
                position: 'fixed',
                top: '-9999px',
                left: '-9999px'
            });
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    this.showCopySuccess($button, originalText);
                } else {
                    this.showCopyError($button, originalText);
                }
            } catch(err) {
                this.showCopyError($button, originalText);
            }
            
            $temp.remove();
        },
        
        /**
         * Copy-Erfolg anzeigen
         */
        showCopySuccess: function($button, originalText) {
            $button.addClass('cbd-success');
            $button.text(cbdFrontend.strings.copied || 'Kopiert!');
            
            // Icon ändern wenn vorhanden
            var $icon = $button.find('.dashicons');
            if ($icon.length) {
                $icon.removeClass('dashicons-clipboard')
                     .addClass('dashicons-yes');
            }
            
            setTimeout(function() {
                $button.removeClass('cbd-success');
                $button.text(originalText);
                if ($icon.length) {
                    $icon.removeClass('dashicons-yes')
                         .addClass('dashicons-clipboard');
                }
            }, 2000);
        },
        
        /**
         * Copy-Fehler anzeigen
         */
        showCopyError: function($button, originalText) {
            $button.addClass('cbd-error');
            $button.text(cbdFrontend.strings.copyError || 'Fehler!');
            
            setTimeout(function() {
                $button.removeClass('cbd-error');
                $button.text(originalText);
            }, 2000);
        },
        
        /**
         * Automatische Nummerierung
         */
        initNumbering: function() {
            // Nummerierung nach Container-Typ gruppieren
            var blockGroups = {};
            
            $('.cbd-numbering').each(function() {
                var $numbering = $(this);
                var $container = $numbering.closest('.cbd-container-block');
                var blockClass = $container.attr('class').match(/cbd-block-[\w-]+/);
                
                if (blockClass) {
                    blockClass = blockClass[0];
                    if (!blockGroups[blockClass]) {
                        blockGroups[blockClass] = [];
                    }
                    blockGroups[blockClass].push($numbering);
                }
            });
            
            // Nummerierung für jede Gruppe anwenden
            $.each(blockGroups, function(blockClass, elements) {
                $.each(elements, function(index, element) {
                    var $numbering = $(element);
                    var format = $numbering.data('format') || 'numeric';
                    var number = index + 1;
                    var displayNumber = '';
                    
                    switch(format) {
                        case 'alphabetic':
                            displayNumber = CBD_Frontend.toAlphabetic(number);
                            break;
                        case 'roman':
                            displayNumber = CBD_Frontend.toRoman(number);
                            break;
                        case 'numeric':
                        default:
                            displayNumber = number;
                            break;
                    }
                    
                    $numbering.html('<span class="cbd-number">' + displayNumber + '.</span>');
                });
            });
        },
        
        /**
         * Zahl zu Buchstaben konvertieren
         */
        toAlphabetic: function(num) {
            var result = '';
            while (num > 0) {
                num--;
                result = String.fromCharCode(65 + (num % 26)) + result;
                num = Math.floor(num / 26);
            }
            return result;
        },
        
        /**
         * Zahl zu römischen Ziffern konvertieren
         */
        toRoman: function(num) {
            var roman = {
                M: 1000, CM: 900, D: 500, CD: 400,
                C: 100, XC: 90, L: 50, XL: 40,
                X: 10, IX: 9, V: 5, IV: 4, I: 1
            };
            var result = '';
            
            for (var key in roman) {
                var count = Math.floor(num / roman[key]);
                if (count) {
                    result += key.repeat(count);
                    num -= roman[key] * count;
                }
            }
            
            return result;
        },
        
        /**
         * Tooltips initialisieren
         */
        initTooltips: function() {
            $('.cbd-container-block [title]').each(function() {
                var $element = $(this);
                var title = $element.attr('title');
                
                if (title) {
                    $element.on('mouseenter', function() {
                        var $tooltip = $('<div class="cbd-tooltip">' + title + '</div>');
                        $('body').append($tooltip);
                        
                        var offset = $element.offset();
                        var width = $element.outerWidth();
                        var height = $element.outerHeight();
                        
                        $tooltip.css({
                            top: offset.top - $tooltip.outerHeight() - 5,
                            left: offset.left + (width / 2) - ($tooltip.outerWidth() / 2)
                        }).fadeIn(200);
                        
                        $element.data('tooltip', $tooltip);
                    });
                    
                    $element.on('mouseleave', function() {
                        var $tooltip = $element.data('tooltip');
                        if ($tooltip) {
                            $tooltip.fadeOut(200, function() {
                                $(this).remove();
                            });
                        }
                    });
                }
            });
        },
        
        /**
         * Animationen initialisieren
         */
        initAnimations: function() {
            // Fade-in Animation beim Scrollen
            var $animatedBlocks = $('.cbd-container-block[data-animation="fade-in"]');
            
            if ($animatedBlocks.length) {
                var checkVisibility = function() {
                    $animatedBlocks.each(function() {
                        var $block = $(this);
                        if (!$block.hasClass('cbd-animated') && CBD_Frontend.isInViewport($block)) {
                            $block.addClass('cbd-animated');
                        }
                    });
                };
                
                $(window).on('scroll resize', checkVisibility);
                checkVisibility();
            }
            
            // Hover-Effekte
            $('.cbd-container-block').on('mouseenter', function() {
                $(this).addClass('cbd-hover');
            }).on('mouseleave', function() {
                $(this).removeClass('cbd-hover');
            });
        },
        
        /**
         * Prüfen ob Element im Viewport ist
         */
        isInViewport: function($element) {
            var elementTop = $element.offset().top;
            var elementBottom = elementTop + $element.outerHeight();
            var viewportTop = $(window).scrollTop();
            var viewportBottom = viewportTop + $(window).height();
            
            return elementBottom > viewportTop && elementTop < viewportBottom;
        },
        
        /**
         * Screenshot-Funktionalität
         */
        initScreenshot: function() {
            $('.cbd-screenshot').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $container = $button.closest('.cbd-container-block');
                
                // Hinweis: Für echte Screenshot-Funktionalität wird eine
                // zusätzliche Library wie html2canvas benötigt
                
                alert(cbdFrontend.strings.screenshotNotAvailable || 
                      'Screenshot-Funktion wird in einer zukünftigen Version hinzugefügt.');
                
                // Interaktion tracken
                CBD_Frontend.track($container.data('block-id'), 'screenshot');
            });
        },
        
        /**
         * Interaktionen tracken
         */
        track: function(blockId, interaction) {
            if (!blockId || !cbdFrontend.ajaxUrl) {
                return;
            }
            
            $.post(cbdFrontend.ajaxUrl, {
                action: 'cbd_track_interaction',
                nonce: cbdFrontend.nonce,
                block_id: blockId,
                interaction: interaction
            });
        },
        
        /**
         * Alle Interaktionen tracken
         */
        trackInteractions: function() {
            // Click-Tracking
            $('.cbd-container-block').on('click', function(e) {
                if (!$(e.target).closest('button, a').length) {
                    CBD_Frontend.track($(this).data('block-id'), 'click');
                }
            });
        }
    };
    
    // Initialisierung beim DOM Ready
    $(document).ready(function() {
        CBD_Frontend.init();
    });
    
    // Re-Initialisierung bei dynamischen Inhalten
    $(document).on('cbd:refresh', function() {
        CBD_Frontend.init();
    });
    
})(jQuery);