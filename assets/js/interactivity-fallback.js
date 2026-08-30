/**
 * Container Block Designer - Interactivity API Fallback
 * jQuery-basierter Fallback für Interactivity API Directives
 *
 * Dieses Script simuliert das Verhalten der Interactivity API für den Fall,
 * dass die WordPress Interactivity API nicht geladen wird.
 *
 * @package ContainerBlockDesigner
 * @since 2.8.0
 */

(function($) {
    'use strict';

    // Global flag to track if Interactivity API is active
    let interactivityAPIActive = false;

    /**
     * Erkennt Apple-Geraete (iOS, iPadOS, macOS-Safari) - AP-2.6.
     *
     * Deckungsgleich mit istAppleGeraet() in interactivity-store.js (dort mit
     * Begruendung und Umlauten in den Kommentaren) - diese Datei wird auf
     * aktuellen WordPress-Versionen zwar nicht geladen (siehe AP-1.4), muss
     * aber inhaltlich gleich bleiben.
     *
     * @return {boolean} true auf iOS/iPadOS/macOS-Safari, sonst false.
     */
    function istAppleGeraet() {
        var ua = navigator.userAgent || '';

        var isIOSDevice = /iPad|iPhone|iPod/.test(ua) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (isIOSDevice) {
            return true;
        }

        var vendor = navigator.vendor || '';
        return vendor.indexOf('Apple') !== -1 &&
            ua.indexOf('Safari') !== -1 &&
            ua.indexOf('Chrome') === -1 &&
            ua.indexOf('Chromium') === -1 &&
            ua.indexOf('Edg') === -1 &&
            ua.indexOf('OPR') === -1;
    }

    // Einmal berechnen statt bei jedem Aufruf neu - siehe interactivity-store.js.
    var cbdIstAppleGeraet = istAppleGeraet();

    /**
     * Verzögerung bis die Aktionsleiste sich von selbst ausblendet (AP-2,
     * PLAN-Aktionsleiste-Autoausblenden.md). Deckungsgleich mit der
     * gleichnamigen Konstante in interactivity-store.js, damit der Wert
     * nicht auseinanderläuft.
     */
    var CBD_AKTIONSLEISTE_VERZOEGERUNG = 2000;

    // Zeitgeber je Container, nicht in einer einzelnen Modulvariable - sonst
    // löschte ein zweiter Container auf derselben Seite den Zeitgeber des
    // ersten.
    var cbdAktionsleisteTimer = new WeakMap();

    /**
     * Blendet die Aktionsleiste eines Containers CBD_AKTIONSLEISTE_VERZOEGERUNG
     * nach dem Erscheinen von selbst aus, indem die Klasse
     * "cbd-actions-verborgen" am Container (.cbd-container) gesetzt wird -
     * das eigentliche Ausblenden übernimmt CSS (cbd-frontend-clean.css,
     * AP-1). Läuft nicht, solange der Zeiger über der Leiste selbst steht
     * oder der Container den Fokus enthält (:focus-within): Sonst wäre die
     * Leiste beim Zielen auf einen Knopf oder beim Tab-Springen unbedienbar.
     * Deckungsgleiche Funktion in interactivity-store.js.
     *
     * @param {jQuery} $container .cbd-container-Element als jQuery-Objekt.
     */
    function initAktionsleisteAutoAusblenden($container) {
        var container = $container[0];
        if (!container) {
            return;
        }

        // .find() sucht in allen Nachfahren; bei verschachtelten Containern
        // liefert .first() trotzdem die eigene Leiste, weil sie im Markup vor
        // jedem inneren Container steht (erstes Fundstück gewinnt).
        var $actionButtons = $container.find('.cbd-action-buttons').first();
        if ($actionButtons.length === 0) {
            return;
        }

        function zeitgeberAbbrechen() {
            var timerId = cbdAktionsleisteTimer.get(container);
            if (timerId) {
                clearTimeout(timerId);
                cbdAktionsleisteTimer.delete(container);
            }
        }

        function zeitgeberStarten() {
            zeitgeberAbbrechen();
            var timerId = setTimeout(function() {
                cbdAktionsleisteTimer.delete(container);

                // Doppelte Absicherung: Auch falls der Zeitgeber durch ein
                // verpasstes Event nicht abgebrochen wurde, bleibt die Leiste
                // sichtbar, solange der Zeiger über ihr steht oder der
                // Container den Fokus enthält.
                if ($container.is(':focus-within') || $actionButtons.is(':hover')) {
                    // Nicht abbrechen, sondern erneut anlaufen lassen: Ein
                    // blosses return loeschte den Zeitgeber, und die Leiste
                    // bliebe dauerhaft stehen, falls das zugehoerige mouseleave
                    // bzw. focusout einmal ausbleibt. So blendet sie spaetestens
                    // zwei Sekunden nach dem Ende von Fokus oder Zeigerkontakt aus.
                    zeitgeberStarten();
                    return;
                }

                $container.addClass('cbd-actions-verborgen');
            }, CBD_AKTIONSLEISTE_VERZOEGERUNG);
            cbdAktionsleisteTimer.set(container, timerId);
        }

        $container.on('mouseenter', function() {
            $container.removeClass('cbd-actions-verborgen');
            zeitgeberStarten();
        });

        $container.on('mouseleave', function() {
            // Container verlassen: Zustand zurücksetzen, sonst bliebe die
            // Leiste beim nächsten Überfahren dauerhaft unsichtbar.
            zeitgeberAbbrechen();
            $container.removeClass('cbd-actions-verborgen');
        });

        $actionButtons.on('mouseenter', function() {
            zeitgeberAbbrechen();
            $container.removeClass('cbd-actions-verborgen');
        });

        $actionButtons.on('mouseleave', function() {
            zeitgeberStarten();
        });

        $container.on('focusin', function() {
            zeitgeberAbbrechen();
            $container.removeClass('cbd-actions-verborgen');
        });

        $container.on('focusout', function() {
            zeitgeberStarten();
        });
    }

    // Check immediately and after a delay
    function checkInteractivityAPI() {
        if (typeof window.wp !== 'undefined' && typeof window.wp.interactivity !== 'undefined') {
            interactivityAPIActive = true;
            return true;
        }
        return false;
    }

    // Warte bis DOM bereit ist
    $(document).ready(function() {

        // NOTE: Block numbering is now handled by block-numbering.js
        // which runs independently with MutationObserver support

        // Initial check
        if (checkInteractivityAPI()) {
            return;
        }

        // Check again after short delay (in case Interactivity API loads later)
        setTimeout(function() {
            if (checkInteractivityAPI()) {
                interactivityAPIActive = true;
            }
        }, 100);


        /**
         * Initialisiere alle Container-Blöcke
         */
        function initializeContainers() {
            $('[data-wp-interactive="container-block-designer"]').each(function() {
                const $container = $(this);
                const containerId = $container.attr('id');

                // Parse context aus data-wp-context
                let context = {};
                try {
                    const contextStr = $container.attr('data-wp-context');
                    if (contextStr) {
                        context = JSON.parse(contextStr);
                    }
                } catch (e) {
                    console.error('[CBD Fallback] Failed to parse context:', e);
                }


                // Set initial state
                $container.data('cbd-context', context);

                // Set initial aria attributes
                // WICHTIG: Nur das DIREKTE Content-Element, nicht verschachtelte!
                const $content = $container.children('.cbd-container-block').children('.cbd-container-content');
                if ($content.length) {
                    $content.attr('aria-hidden', context.isCollapsed ? 'true' : 'false');
                    $content.attr('role', 'region');
                }

                // Set initial collapsed state
                if (context.isCollapsed) {
                    $content.addClass('cbd-collapsed');
                }

                // Initialize icon states
                // WICHTIG: Nur das DIREKTE Icon, nicht verschachtelte!
                const $collapseIcon = $container.children('.cbd-action-buttons').find('.cbd-collapse-toggle .dashicons');
                if (context.isCollapsed) {
                    $collapseIcon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                } else {
                    $collapseIcon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                }

                // Apple-Geraete (iOS/iPadOS/macOS-Safari): Screenshot-Knopf(-e)
                // optisch zum Einzelblock-PDF-Knopf umschalten (AP-2.6). Die
                // eigentliche Umleitung passiert im Klick-Handler weiter unten.
                // Ist das Screenshot-Feature abgeschaltet, existiert der Knopf
                // gar nicht - die Auswahl ist dann einfach leer.
                if (cbdIstAppleGeraet) {
                    $container.children('.cbd-action-buttons').find('.cbd-screenshot').each(function() {
                        const $btn = $(this);
                        const $btnIcon = $btn.find('.dashicons');
                        $btnIcon.removeClass('dashicons-camera').addClass('dashicons-pdf');
                        $btn.attr('title', 'Diesen Block als PDF speichern');
                        $btn.attr('aria-label', 'Diesen Block als PDF speichern');
                        $btn.attr('data-cbd-apple-pdf', '1');
                    });
                }

                // Aktionsleiste blendet sich nach CBD_AKTIONSLEISTE_VERZOEGERUNG
                // von selbst aus (AP-2, PLAN-Aktionsleiste-Autoausblenden.md).
                initAktionsleisteAutoAusblenden($container);
            });
        }

        /**
         * Toggle Collapse Action
         */
        $(document).on('click', '[data-wp-on--click="actions.toggleCollapse"]', function(e) {
            // WICHTIG: Sofort stoppen damit Event nicht zu Parent-Containern bubblet
            e.preventDefault();
            e.stopPropagation();

            // Runtime check: Skip if Interactivity API is now active
            if (interactivityAPIActive || checkInteractivityAPI()) {
                return;
            }

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            const context = $container.data('cbd-context') || {};

            // WICHTIG: Nur das DIREKTE Content-Element dieses Containers, nicht verschachtelte
            // Fallback-Strategie: Versuche mehrere Selektoren
            let $content = $container.children('.cbd-container-content');
            if ($content.length === 0) {
                $content = $container.find('> .cbd-container-block > .cbd-container-content');
            }
            if ($content.length === 0) {
                $content = $container.find('.cbd-container-content').first();
            }
            const $icon = $button.find('.dashicons');

            // Toggle state
            context.isCollapsed = !context.isCollapsed;
            $container.data('cbd-context', context);

            // Toggle wrapper class so CSS rules based on .cbd-container.cbd-collapsed work correctly
            $container.toggleClass('cbd-collapsed', context.isCollapsed);

            // Update UI
            if (context.isCollapsed) {
                $content.slideUp(300, function() {
                    $content.addClass('cbd-collapsed');
                });
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                $content.attr('aria-hidden', 'true');
                $button.attr('aria-expanded', 'false');
            } else {
                $content.slideDown(300, function() {
                    $content.removeClass('cbd-collapsed');
                });
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $content.attr('aria-hidden', 'false');
                $button.attr('aria-expanded', 'true');
            }
        });

        /**
         * Copy Text Action
         */
        $(document).on('click', '[data-wp-on--click="actions.copyText"]', function(e) {
            // WICHTIG: Sofort stoppen damit Event nicht zu Parent-Containern bubblet
            e.preventDefault();
            e.stopPropagation();

            // Runtime check: Skip if Interactivity API is now active
            if (interactivityAPIActive || checkInteractivityAPI()) {
                return;
            }

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            const context = $container.data('cbd-context') || {};

            // WICHTIG: Nur das DIREKTE Content-Element - Fallback-Strategie
            let $content = $container.children('.cbd-container-content');
            if ($content.length === 0) {
                $content = $container.find('> .cbd-container-block > .cbd-container-content');
            }
            if ($content.length === 0) {
                $content = $container.find('.cbd-container-content').first();
            }
            const $icon = $button.find('.dashicons');


            if (!$content.length) {
                return;
            }

            const textToCopy = $content.text().trim();

            if (!textToCopy) {
                return;
            }

            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy)
                    .then(function() {

                        // Update context
                        context.copySuccess = true;
                        $container.data('cbd-context', context);

                        // Visual feedback
                        $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes-alt');

                        // Reset after 2 seconds
                        setTimeout(function() {
                            context.copySuccess = false;
                            $container.data('cbd-context', context);
                            $icon.removeClass('dashicons-yes-alt').addClass('dashicons-clipboard');
                        }, 2000);
                    })
                    .catch(function(err) {
                        context.copyError = true;
                        $container.data('cbd-context', context);
                    });
            } else {
            }
        });

        /**
         * Screenshot Action
         */
        $(document).on('click', '[data-wp-on--click="actions.createScreenshot"]', function(e) {
            // WICHTIG: Sofort stoppen damit Event nicht zu Parent-Containern bubblet
            e.preventDefault();
            e.stopPropagation();

            // Runtime check: Skip if Interactivity API is now active
            if (interactivityAPIActive || checkInteractivityAPI()) {
                return;
            }

            const $button = $(this);
            const $container = $button.closest('[data-wp-interactive="container-block-designer"]');

            // Apple-Geraete (iOS/iPadOS/macOS-Safari): auf den bereits
            // vorhandenen serverseitigen Einzelblock-PDF-Export umleiten,
            // statt html2canvas zu bemuehen (AP-2.6, identische Begruendung
            // wie in interactivity-store.js).
            if (cbdIstAppleGeraet) {
                if (typeof window.cbdPDFExportServerSide === 'function' && $container.length) {
                    window.cbdPDFExportServerSide([$container], 'visual');
                } else {
                    // Kein funktionsfaehiger PDF-Weg vorhanden - ein Knopf ohne
                    // Funktion ist schlechter als keiner. console.warn bleibt
                    // bewusst ungegated (siehe CLAUDE.md, Debugging-Konventionen).
                    console.warn('[CBD Fallback Screenshot] window.cbdPDFExportServerSide nicht verfuegbar - Apple-PDF-Knopf wird ausgeblendet.');
                    $button[0].style.setProperty('display', 'none', 'important');
                }
                return;
            }

            const context = $container.data('cbd-context') || {};
            const $containerBlock = $container.children('.cbd-container-block');
            const $icon = $button.find('.dashicons');

            // Ursache eines gescheiterten Zwischenablage-Versuchs (Stufe 1) merken,
            // damit der abschließende Fehlerpfad sie ausgeben kann (sinngemäß wie
            // in interactivity-store.js).
            let clipboardErrorCause = null;

            // WICHTIG: Nur das DIREKTE Content-Element - Fallback-Strategie
            let $content = $container.children('.cbd-container-content');
            if ($content.length === 0) {
                $content = $container.find('> .cbd-container-block > .cbd-container-content');
            }
            if ($content.length === 0) {
                $content = $containerBlock.children('.cbd-container-content');
            }


            // Check if html2canvas is available
            if (typeof html2canvas === 'undefined') {
                return;
            }

            // Check if containerBlock exists
            if (!$containerBlock.length) {
                return;
            }


            // Set loading state
            context.screenshotLoading = true;
            $container.data('cbd-context', context);
            $button.prop('disabled', true);
            $icon.removeClass('dashicons-camera').addClass('dashicons-update-alt');

            // Remember if was collapsed
            const wasCollapsed = context.isCollapsed;

            // Expand if collapsed
            if (wasCollapsed) {
                $content.show();
            }

            // Small delay for animation
            setTimeout(function() {

                // Buttons ausblenden für Screenshot
                const $actionButtons = $container.find('.cbd-action-buttons');
                $actionButtons.css({
                    'visibility': 'hidden !important',
                    'opacity': '0 !important'
                });

                // Kurze Verzögerung damit DOM aktualisiert wird
                setTimeout(function() {
                    // Canvas-Fläche deckeln: fest scale:2 kann bei sehr hohen Blöcken
                    // die Flächengrenze mancher Browser sprengen (toBlob liefert dann
                    // null). Gleiches Muster wie im PDF-Pfad (pdf-server-side.js:26-27,
                    // :787, :838-842) und in interactivity-store.js: Gerät erkennen,
                    // Pixel-Obergrenze setzen, scale so weit reduzieren, dass
                    // breite * hoehe * scale² darunter bleibt.
                    var isIOSDevice = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                    var maxCanvasPixels = isIOSDevice ? 16000000 : 64000000;
                    var screenshotScale = 2;
                    var containerEl = $containerBlock[0];
                    var containerPixels = containerEl.offsetWidth * containerEl.offsetHeight;
                    if (containerPixels * screenshotScale * screenshotScale > maxCanvasPixels) {
                        screenshotScale = Math.max(1, Math.sqrt(maxCanvasPixels / containerPixels));
                    }
                    screenshotScale = Math.min(screenshotScale, 2);

                    if (window.cbdDebug) {
                        console.log('[CBD Fallback Screenshot] scale =', screenshotScale, 'für', containerEl.offsetWidth, 'x', containerEl.offsetHeight, 'px');
                    }

                    // AP-2.5: backgroundColor bleibt bewusst literal '#ffffff' -
                    // feste Canvas-Exporthintergrundfarbe fuer den Bildexport,
                    // keine UI-Chrome-Farbe, muss unabhaengig vom Theme/Darkmode
                    // weiss bleiben (siehe Plan AP-2.5, Vorgehen Punkt 2).
                    html2canvas($containerBlock[0], {
                        useCORS: true,
                        allowTaint: false,
                        scale: screenshotScale,
                        logging: false,
                        backgroundColor: '#ffffff'
                    }).then(function(canvas) {
                        // Buttons wieder einblenden
                        $actionButtons.css({
                            'visibility': '',
                            'opacity': ''
                        });

                        // ==============================================
                        // TIER 1: Clipboard API (iOS 13.4+, Chrome, Firefox)
                        // ==============================================
                        if (navigator.clipboard && navigator.clipboard.write) {
                            // Safari/iOS FIX: Promise direkt an ClipboardItem übergeben
                            // NICHT vorher awaiten, sonst verliert Safari die User-Gesture
                            const blobPromise = new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                            const item = new ClipboardItem({ 'image/png': blobPromise });

                            navigator.clipboard.write([item])
                                .then(function() {
                                    showSuccess(context, wasCollapsed, $content, $button, $icon, $container);
                                })
                                .catch(function(err) {
                                    // Clipboard failed, erstelle Blob für Fallback.
                                    // Ursache merken statt verschlucken - der Ablauf
                                    // faellt weiterhin auf Stufe 2/3 zurueck.
                                    clipboardErrorCause = err;
                                    canvas.toBlob(function(blob) {
                                        if (!blob) {
                                            console.error('[CBD Fallback] Failed to create blob');
                                            downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container);
                                            return;
                                        }
                                        // Try Tier 2: Web Share API
                                        tryWebShare(blob, canvas, context, wasCollapsed, $content, $button, $icon, $container);
                                    }, 'image/png');
                                });
                            return;
                        }

                        // Clipboard not available, erstelle Blob für Fallback
                        canvas.toBlob(function(blob) {
                            if (!blob) {
                                console.error('[CBD Fallback] Failed to create blob');
                                downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container);
                                return;
                            }
                            tryWebShare(blob, canvas, context, wasCollapsed, $content, $button, $icon, $container);
                        }, 'image/png');

                    // ==============================================
                    // TIER 2: Web Share API (iOS 15+, Safari)
                    // ==============================================
                    function tryWebShare(blob, canvas, context, wasCollapsed, $content, $button, $icon, $container) {
                        // Check if Web Share API with files is supported (iOS/Safari)
                        const file = new File([blob], 'cbd-screenshot-' + Date.now() + '.png', { type: 'image/png' });

                        if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                            navigator.share({
                                files: [file],
                                title: 'Container Block Screenshot'
                            })
                            .then(function() {
                                showSuccess(context, wasCollapsed, $content, $button, $icon, $container);
                            })
                            .catch(function(err) {
                                // User cancelled or error
                                if (err.name === 'AbortError') {
                                    resetButton(context, $button, $icon, $container);
                                } else {
                                    // Fallback to download
                                    downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container);
                                }
                            });
                            return;
                        }

                        // Web Share not available, use download
                        downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container);
                    }

                    // ==============================================
                    // TIER 3: Download Fallback (All browsers)
                    // ==============================================
                    function downloadScreenshot(canvas, context, wasCollapsed, $content, $button, $icon, $container) {
                        const link = document.createElement('a');
                        link.download = 'cbd-container-' + context.blockId + '-' + Date.now() + '.png';
                        link.href = canvas.toDataURL('image/png');
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        showSuccess(context, wasCollapsed, $content, $button, $icon, $container);
                    }

                    // ==============================================
                    // Helper Functions
                    // ==============================================
                    function showSuccess(context, wasCollapsed, $content, $button, $icon, $container) {
                        // Collapse again if was collapsed
                        if (wasCollapsed) {
                            $content.hide();
                        }

                        // Success feedback
                        context.screenshotLoading = false;
                        context.screenshotSuccess = true;
                        $container.data('cbd-context', context);
                        $button.prop('disabled', false);
                        $icon.removeClass('dashicons-update-alt').addClass('dashicons-yes-alt');

                        // Reset after 2 seconds
                        setTimeout(function() {
                            context.screenshotSuccess = false;
                            $container.data('cbd-context', context);
                            $icon.removeClass('dashicons-yes-alt').addClass('dashicons-camera');
                        }, 2000);
                    }

                    function resetButton(context, $button, $icon, $container) {
                        context.screenshotLoading = false;
                        $container.data('cbd-context', context);
                        $button.prop('disabled', false);
                        $icon.removeClass('dashicons-update-alt').addClass('dashicons-camera');
                    }

                    }).catch(function(error) {

                        // Buttons wieder einblenden auch bei Fehler
                        $actionButtons.css({
                            'visibility': '',
                            'opacity': ''
                        });

                        // Ursache sichtbar machen - console.error bleibt bewusst
                        // ungegated (siehe CLAUDE.md, Abschnitt Debugging-Konventionen)
                        console.error('[CBD Fallback Screenshot] Fehlgeschlagen:', error, clipboardErrorCause ? { clipboardError: clipboardErrorCause } : '');

                        // Error state - Icon auf Warnsymbol setzen, damit der
                        // Fehlschlag sichtbar ist, sonst bliebe der Spinner
                        // (dashicons-update-alt) haengen
                        context.screenshotLoading = false;
                        context.screenshotError = true;
                        $container.data('cbd-context', context);
                        $button.prop('disabled', false);
                        $icon.removeClass('dashicons-update-alt dashicons-yes-alt').addClass('dashicons-warning');

                        // Reset error after 3 seconds
                        setTimeout(function() {
                            context.screenshotError = false;
                            $container.data('cbd-context', context);
                            $icon.removeClass('dashicons-warning').addClass('dashicons-camera');
                        }, 3000);
                    });
                }, 50); // Verzögerung für Button-Ausblendung
            }, wasCollapsed ? 350 : 50);
        });

        // Initialize all containers on page load
        initializeContainers();

        /**
         * Touch Device Support - Show buttons on tap/touch
         * Fügt .cbd-selected Klasse hinzu bei Touch-Interaktion
         */
        function initTouchSupport() {
            // Prüfe ob Touch-Gerät
            const isTouchDevice = ('ontouchstart' in window) ||
                                 (navigator.maxTouchPoints > 0) ||
                                 (navigator.msMaxTouchPoints > 0);

            if (!isTouchDevice) {
                return; // Nur auf Touch-Geräten aktivieren
            }

            // Touch-Handler für Container
            $(document).on('touchstart click', '.cbd-container', function(e) {
                // Verhindere dass Event zu Parent-Containern bubblet
                e.stopPropagation();

                const $container = $(this);

                // Entferne .cbd-selected von allen anderen Containern
                $('.cbd-container').not($container).removeClass('cbd-selected');

                // Füge .cbd-selected zu diesem Container hinzu
                $container.addClass('cbd-selected');
            });

            // Klick außerhalb entfernt Selektion
            $(document).on('touchstart click', function(e) {
                // Wenn nicht auf Container geklickt wurde
                if (!$(e.target).closest('.cbd-container').length) {
                    $('.cbd-container').removeClass('cbd-selected');
                }
            });

            // Wenn auf Button geklickt wird, behalte Selektion
            $(document).on('touchstart click', '.cbd-action-buttons button, .cbd-selection-menu', function(e) {
                e.stopPropagation();
                // Container bleibt selektiert
            });
        }

        // Initialisiere Touch-Support
        initTouchSupport();

        /**
         * Board Mode (Tafel-Modus) Action
         */
        $(document).on('click', '[data-wp-on--click="actions.toggleBoardMode"]', function(e) {
            // WICHTIG: Sofort stoppen damit Event nicht zu Parent-Containern bubblet
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation(); // Prevent other handlers

            // Runtime check: Skip if Interactivity API is now active
            // Check for store methods that indicate API is loaded
            if (interactivityAPIActive || checkInteractivityAPI() ||
                (window.wp && window.wp.interactivity && window.wp.interactivity.store)) {
                window.cbdDebug && console.log('[CBD Fallback] Skipping board mode - Interactivity API active');
                return;
            }

            // Prevent double-triggering by setting a flag
            if (this.dataset.cbdBoardOpening) {
                window.cbdDebug && console.log('[CBD Fallback] Board already opening, skipping');
                return;
            }
            this.dataset.cbdBoardOpening = 'true';
            setTimeout(() => { delete this.dataset.cbdBoardOpening; }, 1000);

            var $button = $(this);
            var $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            var context = $container.data('cbd-context') || {};
            var $containerBlock = $container.children('.cbd-container-block');

            // AP-2.5: '#ffffff'-Fallback bewusst literal belassen - landet als
            // this.boardColor in board-mode.js und wird dort direkt als Canvas-
            // 2D-fillStyle verwendet (board-mode.js:519), ist also Tafel-Inhalt
            // (Zeichenflaechenfarbe, analog zur Zeichenfarbpalette aus AP-2.6),
            // keine UI-Chrome-Farbe. Datei/Thema gehoert zu AP-2.6, nicht AP-2.5.
            var boardColor = $button.attr('data-board-color') || '#ffffff';

            if (window.CBDBoardMode) {
                var classroomData = window.cbdClassroomData || {};
                var options = {
                    stableContainerId: context.stableContainerId || $container.attr('data-stable-id') || null,
                    pageId: context.pageId || classroomData.pageId || null,
                    classes: classroomData.classes || [],
                    ajaxUrl: classroomData.ajaxUrl || null,
                    nonce: classroomData.nonce || null
                };
                window.CBDBoardMode.open(
                    context.containerId,
                    $containerBlock.html(),
                    boardColor,
                    options
                );
            }
        });

        /**
         * Behandelt Toggle Action (Classroom System)
         */
        $(document).on('click', '[data-wp-on--click="actions.toggleBehandelt"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Runtime check: Skip if Interactivity API is now active
            if (interactivityAPIActive || checkInteractivityAPI()) {
                return;
            }

            var $button = $(this);
            var $container = $button.closest('[data-wp-interactive="container-block-designer"]');
            var context = $container.data('cbd-context') || {};
            var classroomData = window.cbdClassroomData || {};
            var containerId = context.stableContainerId || $container.attr('data-stable-id');
            var pageId = context.pageId || classroomData.pageId;

            if (!classroomData.classes || classroomData.classes.length === 0) {
                alert('Keine Klassen vorhanden. Bitte erstellen Sie zuerst eine Klasse.');
                return;
            }

            // Aktuellen Status aller Klassen laden, dann Dialog öffnen
            $.ajax({
                url: classroomData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cbd_get_block_status',
                    nonce: classroomData.nonce,
                    page_id: pageId,
                    container_id: containerId
                },
                success: function(statusResponse) {
                    var classes = (statusResponse.success && statusResponse.data.classes)
                        ? statusResponse.data.classes
                        : classroomData.classes.map(function(c) { return { id: c.id, name: c.name, is_behandelt: false }; });

                    // Dialog aufbauen
                    var dialog = $('<div class="cbd-behandelt-selector-dialog"></div>');
                    var overlay = $('<div class="cbd-behandelt-selector-overlay"></div>');
                    var content = $('<div class="cbd-behandelt-selector-content"></div>');

                    content.append('<h3>Klasse wählen</h3>');
                    content.append('<p>Wählen Sie alle Klassen aus, für die Sie den Status ändern möchten.</p>');

                    var optionsContainer = $('<div class="cbd-behandelt-class-options"></div>');
                    classes.forEach(function(cls) {
                        var statusHtml = cls.is_behandelt
                            ? '<span class="dashicons dashicons-yes-alt"></span> Behandelt'
                            : '<span class="dashicons dashicons-marker"></span> Nicht behandelt';
                        var btn = $('<button class="cbd-behandelt-class-option' + (cls.is_behandelt ? ' is-behandelt' : '') + '"></button>')
                            .attr('data-class-id', cls.id)
                            .append('<span class="cbd-class-name">' + $('<div>').text(cls.name).html() + '</span>')
                            .append('<span class="cbd-class-status">' + statusHtml + '</span>');
                        optionsContainer.append(btn);
                    });
                    content.append(optionsContainer);

                    var doneBtn = $('<button class="cbd-behandelt-cancel">Fertig</button>');
                    content.append(doneBtn);

                    dialog.append(overlay);
                    dialog.append(content);
                    $('body').append(dialog);

                    // Event: Klasse angeklickt – Dialog bleibt offen
                    dialog.on('click', '.cbd-behandelt-class-option', function() {
                        var $btn = $(this);
                        if ($btn.prop('disabled')) return;

                        var classId = $btn.attr('data-class-id');
                        var cls = classes.find(function(c) { return String(c.id) === String(classId); });
                        var newStatus = cls ? !cls.is_behandelt : true;

                        $btn.prop('disabled', true).addClass('is-loading');

                        $.ajax({
                            url: classroomData.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'cbd_toggle_behandelt',
                                nonce: classroomData.nonce,
                                class_id: classId,
                                page_id: pageId,
                                container_id: containerId,
                                behandelt: newStatus ? '1' : '0'
                            },
                            success: function(response) {
                                $btn.prop('disabled', false).removeClass('is-loading');
                                if (response.success) {
                                    if (cls) cls.is_behandelt = newStatus;
                                    var $status = $btn.find('.cbd-class-status');
                                    if (newStatus) {
                                        $btn.addClass('is-behandelt');
                                        $status.html('<span class="dashicons dashicons-yes-alt"></span> Behandelt');
                                    } else {
                                        $btn.removeClass('is-behandelt');
                                        $status.html('<span class="dashicons dashicons-marker"></span> Nicht behandelt');
                                    }
                                } else {
                                    alert('Fehler beim Speichern: ' + (response.data || 'Unbekannter Fehler'));
                                }
                            },
                            error: function() {
                                $btn.prop('disabled', false).removeClass('is-loading');
                                alert('Fehler beim Speichern des Behandelt-Status.');
                            }
                        });
                    });

                    // Dialog schließen und Haupt-Icon aktualisieren
                    var closeDialog = function() {
                        dialog.remove();
                        var anyBehandelt = classes.some(function(c) { return c.is_behandelt; });
                        context.isBehandelt = anyBehandelt;
                        $container.data('cbd-context', context);
                        var $icon = $button.find('.dashicons');
                        $icon.removeClass('dashicons-yes-alt dashicons-marker');
                        if (anyBehandelt) {
                            // #4caf50 (Material Design Gruen) entspricht wertlich
                            // keiner AP-1.1-Variable (--color-success ist #2ecc40) -
                            // literal belassen statt falschem Fallback (AP-2.5,
                            // gleiches Prinzip wie die Bootstrap-/Material-Design-
                            // Ausnahmen in AP-2.1/2.2)
                            $icon.addClass('dashicons-yes-alt').css('color', '#4caf50');
                            setTimeout(function() { $icon.css('color', ''); }, 1000);
                        } else {
                            $icon.addClass('dashicons-marker');
                        }
                    };

                    doneBtn.on('click', closeDialog);
                    overlay.on('click', closeDialog);
                },
                error: function() {
                    alert('Fehler beim Laden des Status.');
                }
            });
        });

    });

})(jQuery);