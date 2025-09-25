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
                console.log('CBD Unified Frontend: Initializing...');
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
                console.log('CBD: Container nicht gefunden:', containerId);
                return;
            }

            // KRITISCHER FIX: Nur direkter Content, NICHT verschachtelte Container
            // Suche nur innerhalb der direkten .cbd-content Ebene, nicht rekursiv
            var $directContent = $container.children('.cbd-content');
            var $contentToToggle = $directContent.find('.cbd-container-content').first();
            var $toggle = $container.find('.cbd-collapse-toggle').first();

            if ($contentToToggle.length === 0) {
                console.log('CBD: Kein Content zum Togglen gefunden');
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

            html2canvas(elementToCapture, {
                backgroundColor: null,
                scale: 2,
                useCORS: true,
                allowTaint: true,
                logging: false,
                width: elementToCapture.offsetWidth,
                height: elementToCapture.offsetHeight
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
        },

        /**
         * Text aus Container extrahieren
         */
        extractTextContent: function($container) {
            // Klone Container um Original nicht zu verändern
            var $clone = $container.clone();

            // Entferne UI-Elemente
            $clone.find('.cbd-selection-menu, .cbd-block-header, .cbd-container-number').remove();

            // Hole Text-Inhalt - so wie es war, funktioniert bereits korrekt
            var text = $clone.find('.cbd-container-content').text() || $clone.text();

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
                    console.log('CBD: html2canvas geladen');
                    resolve();
                });

                $script.on('error', function() {
                    console.error('CBD: html2canvas konnte nicht geladen werden');
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

        console.log('CBD Unified Frontend: Initializing...');
        window.CBDUnifiedInitialized = true;
        CBDUnified.init();
    });

    // Global verfügbar machen
    window.CBDUnified = CBDUnified;

})(jQuery);