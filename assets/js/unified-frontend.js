/**
 * Container Block Designer - Unified Frontend JavaScript
 * Version: 2.6.2-FIXED
 * Vereinheitlicht alle Frontend-Funktionalität für bessere HTML-Element-Kompatibilität
 */

(function($) {
    'use strict';

    // Globales CBD Frontend Object
    window.CBDUnified = {
        // Konfiguration
        config: {
            animationSpeed: 300,
            debug: false
        },

        // Initialisierung
        init: function() {
            if (this.config.debug) {
            }

            this.initContainerBlocks();
            this.bindEvents();
            this.loadLibraries();
        },

        /**
         * Container-Blöcke initialisieren
         */
        initContainerBlocks: function() {
            $('.cbd-container').each(function() {
                var $container = $(this);
                var containerId = $container.attr('id') || 'cbd-container-' + Math.random().toString(36).substr(2, 9);

                if (!$container.attr('id')) {
                    $container.attr('id', containerId);
                }

                // Funktionen initialisieren
                CBDUnified.setupCollapse($container);
                CBDUnified.setupCopyText($container);
                CBDUnified.setupScreenshot($container);
                CBDUnified.setupNumbering($container);
            });
        },

        /**
         * Collapse-Funktionalität einrichten
         */
        setupCollapse: function($container) {
            // Prüfe ob Collapse-Button bereits existiert
            var $existingToggle = $container.find('.cbd-collapse-toggle');
            if ($existingToggle.length > 0) {
                return; // Bereits eingerichtet
            }

            // Prüfe ob Container collapsible sein soll (aus Block-Renderer)
            var $content = $container.find('.cbd-container-content');
            var $header = $container.find('.cbd-block-header');

            if ($content.length === 0 || $header.length === 0) {
                return; // Keine Struktur für Collapse vorhanden
            }

            // Toggle-Button ist bereits im Block-Renderer erstellt
            // Hier nur Event-Handler hinzufügen
            this.bindCollapseEvents($container);
        },

        /**
         * Copy-Text-Funktionalität einrichten
         */
        setupCopyText: function($container) {
            // Copy-Buttons sind bereits im Block-Renderer erstellt
            // Hier nur Event-Handler hinzufügen falls noch nicht geschehen
            var $copyButtons = $container.find('.cbd-copy-text');

            $copyButtons.each(function() {
                var $button = $(this);
                if (!$button.data('cbd-copy-initialized')) {
                    $button.data('cbd-copy-initialized', true);
                }
            });
        },

        /**
         * Screenshot-Funktionalität einrichten
         */
        setupScreenshot: function($container) {
            // Screenshot-Buttons sind bereits im Block-Renderer erstellt
            var $screenshotButtons = $container.find('.cbd-screenshot');

            $screenshotButtons.each(function() {
                var $button = $(this);
                if (!$button.data('cbd-screenshot-initialized')) {
                    $button.data('cbd-screenshot-initialized', true);
                }
            });
        },

        /**
         * Numbering einrichten
         */
        setupNumbering: function($container) {
            // Numbering wird bereits im Block-Renderer generiert
            var $numbers = $container.find('.cbd-container-number');
            if ($numbers.length > 0) {
                // Numbering bereits vorhanden
                return;
            }
        },

        /**
         * Events binden
         */
        bindEvents: function() {
            // Entferne alte Handler um Duplikate zu vermeiden
            $(document).off('click.cbd-unified');

            // Collapse Toggle
            $(document).on('click.cbd-unified', '.cbd-collapse-toggle', this.handleCollapseToggle);

            // Copy Text
            $(document).on('click.cbd-unified', '.cbd-copy-text', this.handleCopyText);

            // Screenshot
            $(document).on('click.cbd-unified', '.cbd-screenshot', this.handleScreenshot);

            // Menu Toggle (Dropdown)
            $(document).on('click.cbd-unified', '.cbd-menu-toggle', this.handleMenuToggle);

            // Schließe Dropdowns beim Klick außerhalb
            $(document).on('click.cbd-unified', function(e) {
                if (!$(e.target).closest('.cbd-selection-menu').length) {
                    $('.cbd-dropdown-menu').hide();
                }
            });
        },

        /**
         * Collapse Events binden (spezifisch für Container)
         */
        bindCollapseEvents: function($container) {
            // Direkte Bindung an den Container um Konflikte zu vermeiden
            var containerId = $container.attr('id');

            $container.off('click.cbd-collapse').on('click.cbd-collapse', '.cbd-collapse-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();

                CBDUnified.toggleCollapse(containerId);
            });
        },

        /**
         * Collapse Toggle Handler
         */
        handleCollapseToggle: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $toggle = $(this);
            var $container = $toggle.closest('.cbd-container');
            var containerId = $container.attr('id');

            CBDUnified.toggleCollapse(containerId);
        },

        /**
         * Collapse umschalten
         */
        toggleCollapse: function(containerId) {
            var $container = $('#' + containerId);
            if ($container.length === 0) {
                return;
            }

            var $contentToToggle = $container.find('.cbd-container-content');
            var $toggle = $container.find('.cbd-collapse-toggle');

            if ($contentToToggle.length === 0) {
                return;
            }

            // Container und wichtige Teile sichtbar halten
            $container.css({
                'display': 'block',
                'visibility': 'visible'
            });

            $container.find('.cbd-container-block').css('display', 'block');
            $container.find('.cbd-block-header').css('display', 'block');
            $container.find('.cbd-selection-menu').css('display', 'block');
            $toggle.css('display', 'flex');

            // Nur den Inhalt togglen
            if ($contentToToggle.is(':visible')) {
                // Einklappen
                $contentToToggle.slideUp(this.config.animationSpeed);
                $container.addClass('cbd-collapsed');
                $toggle.find('.dashicons')
                    .removeClass('dashicons-arrow-up-alt2')
                    .addClass('dashicons-arrow-down-alt2');
                $toggle.find('span').text('Ausklappen');
            } else {
                // Ausklappen
                $contentToToggle.slideDown(this.config.animationSpeed);
                $container.removeClass('cbd-collapsed');
                $toggle.find('.dashicons')
                    .removeClass('dashicons-arrow-down-alt2')
                    .addClass('dashicons-arrow-up-alt2');
                $toggle.find('span').text('Einklappen');
            }
        },

        /**
         * Copy Text Handler
         */
        handleCopyText: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);
            var containerId = $button.data('container-id');
            var $container = $('#' + containerId);

            if ($container.length === 0) {
                CBDUnified.showToast('Container nicht gefunden', 'error');
                return;
            }

            // Text aus dem Container extrahieren
            var textContent = CBDUnified.extractTextContent($container);

            // In Zwischenablage kopieren
            CBDUnified.copyToClipboard(textContent).then(function() {
                CBDUnified.showToast('Text kopiert!', 'success');
                $button.addClass('cbd-copied');
                setTimeout(function() {
                    $button.removeClass('cbd-copied');
                }, 2000);
            }).catch(function() {
                CBDUnified.showToast('Kopieren fehlgeschlagen', 'error');
            });
        },

        /**
         * Screenshot Handler
         */
        handleScreenshot: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);
            var containerId = $button.data('container-id');
            var $container = $('#' + containerId);

            if ($container.length === 0) {
                CBDUnified.showToast('Container nicht gefunden', 'error');
                return;
            }

            // Prüfe ob html2canvas verfügbar ist
            if (typeof html2canvas === 'undefined') {
                CBDUnified.showToast('Screenshot-Bibliothek wird geladen...', 'info');
                CBDUnified.loadLibraries().then(function() {
                    CBDUnified.takeScreenshot($container, $button);
                });
            } else {
                CBDUnified.takeScreenshot($container, $button);
            }
        },

        /**
         * Menu Toggle Handler
         */
        handleMenuToggle: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $toggle = $(this);
            var $menu = $toggle.next('.cbd-dropdown-menu');

            // Alle anderen Menüs schließen
            $('.cbd-dropdown-menu').not($menu).hide();

            // Dieses Menü togglen
            $menu.toggle();
        },

        /**
         * Screenshot erstellen
         */
        takeScreenshot: function($container, $button) {
            $button.prop('disabled', true);
            $button.addClass('cbd-loading');

            var originalText = $button.find('span').text();
            $button.find('span').text('Screenshot...');

            // Screenshot der Container-Block-Ebene
            var elementToCapture = $container.find('.cbd-container-block')[0] || $container[0];

            // Wait for LaTeX to fully render before screenshot
            var prepareAndCapture = function() {
                // Prepare LaTeX formulas
                if (typeof window.cbdPrepareFormulasForPDF === 'function') {
                    window.cbdPrepareFormulasForPDF(elementToCapture);
                }

                // Check if formulas are rendered
                var formulas = elementToCapture.querySelectorAll('.cbd-latex-formula');
                var allRendered = true;
                formulas.forEach(function(formula) {
                    if (!formula.classList.contains('cbd-latex-rendered')) {
                        allRendered = false;
                    }
                });

                if (!allRendered && formulas.length > 0) {
                    setTimeout(prepareAndCapture, 300);
                    return;
                }

                captureScreenshot();
            };

            var captureScreenshot = function() {
                html2canvas(elementToCapture, {
                    backgroundColor: null,
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    logging: false,
                    width: elementToCapture.offsetWidth,
                    height: elementToCapture.offsetHeight,
                    onclone: function(clonedDoc) {
                        // Ensure LaTeX formulas are visible in clone
                        var formulas = clonedDoc.querySelectorAll('.cbd-latex-formula');

                        formulas.forEach(function(formula, index) {
                            formula.style.setProperty('display', 'block', 'important');
                            formula.style.setProperty('visibility', 'visible', 'important');
                            formula.style.setProperty('opacity', '1', 'important');

                            // Use black as default for better visibility in screenshots
                            var textColor = '#000000';

                            // Try to get container color, but only use it if it's dark enough
                            var container = formula.closest('.cbd-container-content, .cbd-container-block, [class*="cbd-"]');
                            if (container) {
                                try {
                                    var computedStyle = clonedDoc.defaultView.getComputedStyle(container);
                                    var containerColor = computedStyle.color;

                                    // Parse RGB to check if color is dark enough
                                    if (containerColor) {
                                        var rgb = containerColor.match(/\d+/g);
                                        if (rgb && rgb.length >= 3) {
                                            var r = parseInt(rgb[0]);
                                            var g = parseInt(rgb[1]);
                                            var b = parseInt(rgb[2]);
                                            var brightness = (r * 299 + g * 587 + b * 114) / 1000;

                                            // Only use container color if it's dark enough (brightness < 180)
                                            if (brightness < 180) {
                                                textColor = containerColor;
                                            } else {
                                            }
                                        }
                                    }
                                } catch (e) {
                                }
                            }


                            // Apply color to formula and all its child elements
                            formula.style.setProperty('color', textColor, 'important');
                            formula.style.setProperty('background', 'none', 'important');
                            formula.style.setProperty('background-color', 'transparent', 'important');
                            formula.style.setProperty('opacity', '1', 'important');
                            formula.style.setProperty('filter', 'none', 'important');
                            formula.style.setProperty('-webkit-filter', 'none', 'important');

                            // Apply to all nested elements (KaTeX generates many spans)
                            var allElements = formula.querySelectorAll('*');

                            allElements.forEach(function(element) {
                                element.style.setProperty('color', textColor, 'important');
                                element.style.setProperty('background', 'none', 'important');
                                element.style.setProperty('background-color', 'transparent', 'important');
                                element.style.setProperty('opacity', '1', 'important');
                                element.style.setProperty('filter', 'none', 'important');
                                element.style.setProperty('-webkit-filter', 'none', 'important');

                                // Also check for inline styles that might override
                                if (element.style.color && element.style.color !== textColor) {
                                }
                            });
                        });

                        // Hide noscript fallback in screenshots
                        var noscripts = clonedDoc.querySelectorAll('.cbd-latex-formula noscript');
                        noscripts.forEach(function(noscript) {
                            noscript.style.display = 'none';
                        });
                    }
                }).then(function(canvas) {
                // Canvas zu Blob
                canvas.toBlob(function(blob) {
                    // Download erstellen mit jQuery/JavaScript
                    var url = URL.createObjectURL(blob);
                    var $link = $('<a></a>');
                    $link.attr({
                        'href': url,
                        'download': 'container-block-' + Date.now() + '.png'
                    });

                    // Link zum DOM hinzufügen, klicken, dann entfernen
                    $('body').append($link);
                    $link[0].click();
                    $link.remove();

                    // URL wieder freigeben
                    setTimeout(function() {
                        URL.revokeObjectURL(url);
                    }, 100);

                    CBDUnified.showToast('Screenshot heruntergeladen!', 'success');
                }, 'image/png');
                }).catch(function(error) {
                    console.error('Screenshot Fehler:', error);
                    CBDUnified.showToast('Screenshot fehlgeschlagen', 'error');
                }).finally(function() {
                    $button.prop('disabled', false);
                    $button.removeClass('cbd-loading');
                    $button.find('span').text(originalText);
                });
            };

            // Start the preparation and capture process
            prepareAndCapture();
        },

        /**
         * Text aus Container extrahieren
         */
        extractTextContent: function($container) {
            // Klone Container um Original nicht zu verändern (clone ohne Events)
            var $clone = $container.clone(false);

            // Entferne UI-Elemente
            $clone.find('.cbd-selection-menu, .cbd-block-header, .cbd-container-number').remove();

            // Debug: Log formulas found
            var formulaCount = $clone.find('.cbd-latex-formula').length;

            // WICHTIG: Formeln komplett durch LaTeX-Code ersetzen
            var formulas = $clone.find('.cbd-latex-formula').toArray();

            for (var i = 0; i < formulas.length; i++) {
                var formula = formulas[i];
                var latex = formula.getAttribute('data-latex');


                if (latex) {
                    // Erstelle ein neues Span-Element mit nur dem LaTeX-Code
                    var replacement = document.createElement('span');
                    replacement.className = 'cbd-latex-replacement';
                    replacement.textContent = '\n\n$$ ' + latex + ' $$\n\n';

                    // Ersetze die Formel
                    formula.parentNode.replaceChild(replacement, formula);
                }
            }

            // Hole Text-Inhalt
            var $content = $clone.find('.cbd-container-content');
            var text = $content.length > 0 ? $content.text() : $clone.text();

            return text.trim();
        },

        /**
         * In Zwischenablage kopieren
         */
        copyToClipboard: function(text) {
            return new Promise(function(resolve, reject) {
                // Moderne Clipboard API
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(resolve).catch(reject);
                    return;
                }

                // Fallback für ältere Browser
                try {
                    var $textarea = $('<textarea></textarea>');
                    $textarea.val(text);
                    $textarea.css({
                        position: 'fixed',
                        top: '-9999px',
                        left: '-9999px',
                        opacity: 0
                    });

                    $('body').append($textarea);
                    $textarea[0].select();
                    $textarea[0].setSelectionRange(0, 99999);

                    var success = document.execCommand('copy');
                    $textarea.remove();

                    if (success) {
                        resolve();
                    } else {
                        reject(new Error('execCommand fehlgeschlagen'));
                    }
                } catch (err) {
                    reject(err);
                }
            });
        },

        /**
         * Bibliotheken laden
         */
        loadLibraries: function() {
            return new Promise(function(resolve, reject) {
                if (typeof html2canvas !== 'undefined') {
                    resolve();
                    return;
                }

                var $script = $('<script></script>');
                $script.attr('src', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');

                $script.on('load', function() {
                    resolve();
                });

                $script.on('error', function() {
                    reject(new Error('Bibliothek konnte nicht geladen werden'));
                });

                $('head').append($script);
            });
        },

        /**
         * Toast-Benachrichtigung anzeigen
         */
        showToast: function(message, type) {
            type = type || 'info';

            // Entferne alte Toasts
            $('.cbd-toast').remove();

            var $toast = $('<div></div>');
            $toast.addClass('cbd-toast cbd-toast-' + type);
            $toast.text(message);

            $('body').append($toast);

            // Animation
            setTimeout(function() {
                $toast.addClass('cbd-toast-show');
            }, 100);

            // Auto-Hide
            setTimeout(function() {
                $toast.removeClass('cbd-toast-show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, 3000);
        }
    };

    // Initialisierung nach DOM Ready
    $(document).ready(function() {
        // Verhindere mehrfache Initialisierung
        if (window.CBDUnifiedInitialized) {
            return;
        }

        window.CBDUnifiedInitialized = true;
        CBDUnified.init();
    });

    // Global verfügbar machen
    window.CBDUnified = CBDUnified;

})(jQuery);