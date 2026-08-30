/**
 * Container Block Designer - Classroom Page Filter
 * Filters container blocks on normal WordPress pages in classroom mode
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

(function($) {
    'use strict';

    /**
     * localStorage-Vertrag „Klassenmodus" (siehe PLAN-Inhaltsverzeichnisse.md,
     * Abschnitt 4): Schlüssel 'cbd_classroom_toc_collapsed', JSON-Array von
     * Seiten-IDs (als Strings) der zugeklappten Knoten. Identischer Code wie
     * in classroom-frontend.js (AP-1.3a) — beide Dateien laufen nie
     * gleichzeitig auf derselben Seite, eine gemeinsame Modul-Datei gibt es
     * in diesem Plugin nicht (kein Build-Prozess).
     */
    function cbdKlassenverzeichnisGeleseneCollapsedIds() {
        try {
            var roh = localStorage.getItem('cbd_classroom_toc_collapsed');
            var arr = roh ? JSON.parse(roh) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
    }
    function cbdKlassenverzeichnisSchreibeCollapsedIds(idsArray) {
        try { localStorage.setItem('cbd_classroom_toc_collapsed', JSON.stringify(idsArray)); } catch (e) {}
    }

    var ClassroomPageFilter = {
        classroomId: null,
        token: null,
        pageId: null,
        className: null,

        init: function() {
            // Get URL parameters
            var urlParams = new URLSearchParams(window.location.search);
            this.classroomId = urlParams.get('classroom');
            this.token = urlParams.get('token');

            // Get page ID from localized data
            if (typeof cbdClassroomPageData !== 'undefined') {
                this.pageId = cbdClassroomPageData.pageId;
            }

            // Only run if we have all required parameters
            if (!this.classroomId || !this.token || !this.pageId) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Missing parameters, skipping');
                return;
            }

            window.cbdDebug && console.log('CBD Classroom Page Filter: Initializing for page', this.pageId, 'classroom', this.classroomId);
            this.loadClassroomData();
            this.verdrahteKlassenpuls();
        },

        /**
         * Den Taktgeber `window.cbdKlassenpuls` anzapfen (AP-2.1).
         *
         * Der Taktgeber ist OPTIONAL: Steht die Option `cbd_klassenpuls_takt`
         * auf 0, reiht `CBD_Classroom::enqueue_frontend_assets()` die Datei
         * `assets/js/klassenpuls.js` gar nicht erst ein — `window.cbdKlassenpuls`
         * existiert dann nicht und diese Methode steigt still aus. Der Filter
         * verhält sich in diesem Fall exakt wie vor diesem Arbeitspaket.
         *
         * Reihenfolge mit Absicht: erst `setzeSeite()`, dann `setzeSitzung()`.
         * `setzeSitzung()` startet den Taktgeber sofort (`starte()` ruft
         * synchron `frageAb()`); wäre die Seite da noch nicht gesetzt, ginge
         * die allererste Abfrage ohne `page_id` hinaus und der Server lieferte
         * die Signaturen `seite`/`tafel` erst beim zweiten Durchlauf. Der
         * Vertrag von `setzeSeite()` erlaubt den Aufruf vor `setzeSitzung()`
         * ausdrücklich (die Seitenbindung überlebt das Setzen der Sitzung).
         */
        verdrahteKlassenpuls: function() {
            if (!window.cbdKlassenpuls) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Kein Taktgeber vorhanden (cbd_klassenpuls_takt = 0?) – keine Live-Aktualisierung.');
                return;
            }

            var self = this;

            window.cbdKlassenpuls.setzeSeite(this.pageId);
            window.cbdKlassenpuls.setzeSitzung(this.classroomId, this.token);

            // Auf einer serverseitig reduzierten Seite liegt das HTML der noch
            // nicht freigegebenen Container GAR NICHT im DOM – ein Einblenden
            // wäre wirkungslos. Dort löst Phase 3 des Vorhabens ein gezieltes
            // Neuladen aus; hier wird deshalb bewusst KEIN 'seite'-Rückruf
            // registriert.
            if (this.istReduzierteSeite()) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Reduzierte Seite – kein seite-Rückruf (Phase 3 übernimmt).');
            } else {
                window.cbdKlassenpuls.abonniere('seite', function() {
                    self.aktualisiere();
                });
            }

            window.cbdKlassenpuls.abonniere('abgelaufen', function() {
                self.showError('Die Klassensitzung ist abgelaufen. Bitte erneut anmelden.');
            });
        },

        /**
         * Läuft diese Seite serverseitig reduziert?
         * Der Wert kommt aus CBD_Classroom::enqueue_frontend_assets().
         */
        istReduzierteSeite: function() {
            return (typeof cbdClassroomPageData !== 'undefined')
                && !!cbdClassroomPageData.reduziert;
        },

        /**
         * Load classroom data for this specific page
         */
        loadClassroomData: function() {
            var self = this;

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_get_page_classroom_data',
                token: this.token,
                page_id: this.pageId
            }, function(response) {
                if (response.success) {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Received data', response.data);
                    self.einmaligAufbauen(response.data);
                    self.filterContainers(response.data, true);
                } else {
                    console.error('CBD Classroom Page Filter: Error loading data', response.data.message);
                    // Show error to user
                    self.showError(response.data.message || 'Fehler beim Laden der Klassendaten.');
                }
            }).fail(function(xhr, status, error) {
                console.error('CBD Classroom Page Filter: Network error', error);
                self.showError('Netzwerk-Fehler beim Laden der Klassendaten.');
            });
        },

        /**
         * Klassendaten erneut holen und den Filter wiederholen (AP-2.1).
         *
         * Wird ausschließlich vom 'seite'-Rückruf des Taktgebers gerufen, also
         * nur dann, wenn sich die Freigabe-Signatur tatsächlich geändert hat.
         * Ruft WEDER `einmaligAufbauen()` NOCH `filterContainers(data, true)` –
         * die Navigationsleiste, die Link-Umleitung und die Warnung über
         * fehlende markierte Blöcke gehören zum Erstaufbau und dürfen sich
         * nicht wiederholen.
         *
         * Ein fehlgeschlagener Nachschlag bleibt bewusst STILL: Er ist kein
         * Grund, dem lesenden Schüler eine Fehlermeldung vor die Nase zu
         * setzen. Der nächste Takt versucht es ohnehin wieder.
         */
        aktualisiere: function() {
            var self = this;

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_get_page_classroom_data',
                token: this.token,
                page_id: this.pageId
            }, function(response) {
                if (response && response.success) {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Aktualisierung erhalten', response.data);
                    self.filterContainers(response.data);
                } else {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Aktualisierung ohne Erfolg – still ignoriert.');
                }
            }).fail(function(xhr, status, error) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Aktualisierung fehlgeschlagen – still ignoriert.', error);
            });
        },

        /**
         * Alles, was GENAU EINMAL geschehen darf (AP-2.1).
         *
         * Navigationsleiste (mit "Verlassen"-Button) IMMER einfügen –
         * unabhängig davon, ob die Seite Container-Blöcke hat. Sonst säße der
         * Schüler auf container-losen Seiten in der Klasse fest (kein Ausgang).
         *
         * Wird ausschließlich aus `loadClassroomData()` gerufen, NIE aus
         * `aktualisiere()`. Andernfalls entstünde bei jeder Live-Aktualisierung
         * eine weitere Navigationsleiste bzw. ein weiterer Klick-Abfänger.
         */
        einmaligAufbauen: function(data) {
            this.className = data.class_name;
            this.injectClassroomNavBar(data.class_name);
            this.interceptLinks();
        },

        /**
         * Filter containers based on classroom data.
         *
         * Seit AP-2.1 beliebig oft aufrufbar: Die Methode enthält nur noch den
         * wiederholbaren Teil (Schleife über die Container). Der einmalige Teil
         * steht in `einmaligAufbauen()`.
         *
         * @param {Object}  data          Antwort von cbd_get_page_classroom_data.
         * @param {boolean} istErstaufbau Nur beim ersten Durchlauf true. Steuert
         *                                die einmalige Warnung über fehlende
         *                                markierte Blöcke und verhindert, dass
         *                                der Erstaufbau als „neu freigegeben"
         *                                gilt.
         */
        filterContainers: function(data, istErstaufbau) {
            var self = this;
            var treatedContainers = data.treated_containers || [];
            var drawings = data.drawings || {};

            istErstaufbau = !!istErstaufbau;

            window.cbdDebug && console.log('CBD Classroom Page Filter: Treated containers:', treatedContainers);
            window.cbdDebug && console.log('CBD Classroom Page Filter: Drawings:', Object.keys(drawings));

            // Find all container blocks on the page
            // Try multiple selectors to catch all containers
            var $containers = $('[data-wp-interactive="container-block-designer"], [data-stable-id^="cbd-"]');
            window.cbdDebug && console.log('CBD Classroom Page Filter: Found', $containers.length, 'container blocks');

            // DEBUG: Log all found container stable IDs
            var foundStableIds = [];
            $containers.each(function() {
                var stableId = $(this).attr('data-stable-id');
                if (stableId) {
                    foundStableIds.push(stableId);
                }
            });
            window.cbdDebug && console.log('CBD Classroom Page Filter: All stable IDs found in DOM:', foundStableIds);
            window.cbdDebug && console.log('CBD Classroom Page Filter: Treated containers from server:', treatedContainers);

            // Check for inconsistencies: containers in DB but not in DOM
            var missingContainers = [];
            treatedContainers.forEach(function(containerId) {
                if (foundStableIds.indexOf(containerId) === -1) {
                    missingContainers.push(containerId);
                }
            });

            // Auf einer serverseitig reduzierten Seite ergibt diese Warnung
            // keinen Sinn: Dort steht ohnehin nur, was freigegeben ist, und
            // freigegebene Container anderer Seiten fehlen naturgemäß. Der
            // Wert kommt aus CBD_Classroom::enqueue_frontend_assets().
            var istReduziert = this.istReduzierteSeite();

            // Seit AP-2.1 NUR beim Erstaufbau: Bei jeder Live-Aktualisierung
            // würde sich dieselbe Warnung sonst endlos wiederholen. Der
            // istReduziert-Vorbehalt bleibt davon unberührt bestehen.
            if (istErstaufbau && missingContainers.length > 0 && !istReduziert) {
                console.warn('CBD Classroom Page Filter: WARNING - ' + missingContainers.length + ' treated containers from DB not found in DOM (page was likely edited):', missingContainers);

                // Show warning but DON'T auto-cleanup - teacher might want to re-mark the blocks
                this.showWarning('Hinweis: Diese Seite wurde bearbeitet. ' + missingContainers.length +
                    ' markierte(r) Block/Blöcke wurde(n) auf der Seite nicht gefunden. ' +
                    'Die Markierungen bleiben in der Datenbank gespeichert, werden aber auf dieser Seite nicht angezeigt.');

                // DON'T call cleanupInvalidContainers() - markings should persist
            } else if (istErstaufbau && missingContainers.length > 0) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: ' + missingContainers.length +
                    ' markierte Container fehlen im DOM - auf einer reduzierten Seite erwartet, keine Warnung.');
            }

            // Filter to only show containers that exist in BOTH DOM and DB
            var validTreatedContainers = treatedContainers.filter(function(containerId) {
                return foundStableIds.indexOf(containerId) !== -1;
            });

            window.cbdDebug && console.log('CBD Classroom Page Filter: Valid treated containers (intersection):', validTreatedContainers);

            if ($containers.length === 0) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: No containers found on page');
                return;
            }

            // Hide all containers by default, then show only treated ones that exist in DOM
            //
            // Seit AP-2.1 wird der ZUSTANDSWECHSEL erkannt, statt blind zu
            // setzen: Nur ein Container, der vorher versteckt war und jetzt
            // sichtbar wird, gilt als „neu freigegeben". Beim Erstaufbau sind
            // alle Container ohnehin sichtbar – dort entsteht deshalb kein
            // einziger Wechsel auf „sichtbar" und das Verhalten bleibt
            // unverändert.
            var neuSichtbarAnzahl = 0;

            $containers.each(function() {
                var $container = $(this);
                var stableId = $container.attr('data-stable-id');
                var sollSichtbar = !!stableId && validTreatedContainers.indexOf(stableId) !== -1;
                var istSichtbar = $container.is(':visible');

                window.cbdDebug && console.log('CBD Classroom Page Filter: Processing container', stableId);

                if (!sollSichtbar) {
                    // Container is NOT treated OR doesn't exist in DB -> hide it
                    if (istSichtbar) {
                        $container.hide();
                        window.cbdDebug && console.log('CBD Classroom Page Filter: Hiding non-treated container', stableId);
                    }
                } else {
                    // Container IS treated AND exists in DOM -> show it and add drawings/badges
                    if (!istSichtbar) {
                        $container.show();
                        neuSichtbarAnzahl++;
                        window.cbdDebug && console.log('CBD Classroom Page Filter: Showing treated container', stableId);

                        // Der Hinweis „neu freigegeben" gilt nur für echte
                        // Live-Freigaben, nicht für den Erstaufbau.
                        if (!istErstaufbau) {
                            self.markiereNeuFreigegeben($container);
                        }
                    }

                    // Add drawing and badge if available
                    if (drawings[stableId]) {
                        var drawing = drawings[stableId];

                        // Add "Behandelt" badge
                        if (drawing.is_behandelt) {
                            // Only add if not already present
                            if ($container.find('.cbd-behandelt-badge').length === 0) {
                                $container.prepend('<div class="cbd-behandelt-badge">✓ Behandelt</div>');
                                $container.addClass('cbd-is-behandelt');
                            }
                        }

                        // Add collapsible drawing section with optional page navigation
                        // Nur anzeigen wenn mindestens eine Seite echte Zeichnungsdaten hat
                        var hasPages = drawing.pages && Object.keys(drawing.pages).some(function(idx) {
                            return drawing.pages[idx] && drawing.pages[idx].drawing_data;
                        });
                        var hasLegacy = !hasPages && drawing.drawing_data;

                        if (hasPages || hasLegacy) {
                            var $content = $container.find('.cbd-container-content').first();
                            if ($content.length > 0 && $content.find('.cbd-class-drawing-section').length === 0) {
                                var $section = $('<div class="cbd-drawing-section cbd-class-drawing-section">');
                                var $toggle = $('<button class="cbd-drawing-toggle">📋 Tafelbild anzeigen</button>');
                                var $drawingOverlay = $('<div class="cbd-drawing-overlay" style="display: none;">');

                                if (hasPages) {
                                    // Multi-page: IIFE für saubere Closure-Isolation
                                    // Nur Seiten mit tatsächlichen Zeichnungsdaten berücksichtigen
                                    var pageIndices = Object.keys(drawing.pages).map(Number).sort(function(a, b) { return a - b; }).filter(function(idx) {
                                        return drawing.pages[idx] && drawing.pages[idx].drawing_data;
                                    });
                                    var totalDrawingPages = pageIndices.length;

                                    var $img = $('<img>').attr('alt', 'Tafel-Zeichnung').css('max-width', '100%');

                                    if (totalDrawingPages > 1) {
                                        var $pageNav = $('<div class="cbd-drawing-page-nav">');
                                        var $pagePrev = $('<button class="cbd-drawing-page-prev" disabled>◀</button>');
                                        var $pageIndicator = $('<span class="cbd-drawing-page-indicator">1 / ' + totalDrawingPages + '</span>');
                                        var $pageNext = $('<button class="cbd-drawing-page-next">▶</button>');
                                        $pageNav.append($pagePrev, $pageIndicator, $pageNext);
                                        $drawingOverlay.append($pageNav);

                                        // IIFE: alle Variablen als Parameter übergeben → kein var-Hoisting-Problem
                                        (function($imgEl, $prev, $next, $ind, pages, indices, total) {
                                            var current = 0;

                                            function showPage(idx) {
                                                if (idx < 0 || idx >= total) return;
                                                current = idx;
                                                var pd = pages[indices[idx]];
                                                $imgEl.attr('src', pd && pd.drawing_data ? pd.drawing_data : '');
                                                $prev.prop('disabled', idx <= 0);
                                                $next.prop('disabled', idx >= total - 1);
                                                $ind.text((idx + 1) + ' / ' + total);
                                            }

                                            $prev.on('click', function(e) { e.stopPropagation(); showPage(current - 1); });
                                            $next.on('click', function(e) { e.stopPropagation(); showPage(current + 1); });

                                            showPage(0);
                                        })($img, $pagePrev, $pageNext, $pageIndicator, drawing.pages, pageIndices, totalDrawingPages);
                                    } else {
                                        // Einzelne Seite: nur Bild anzeigen
                                        var pd0 = drawing.pages[pageIndices[0]];
                                        $img.attr('src', pd0 && pd0.drawing_data ? pd0.drawing_data : '');
                                    }

                                    $drawingOverlay.append($img);
                                } else {
                                    // Legacy: einzelne Zeichnung
                                    $drawingOverlay.append(
                                        $('<img>').attr({
                                            'src': drawing.drawing_data || '',
                                            'alt': 'Tafel-Zeichnung'
                                        }).css('max-width', '100%')
                                    );
                                }

                                $section.append($toggle, $drawingOverlay);
                                $content.append($section);

                                $toggle.on('click', function(e) {
                                    e.preventDefault();
                                    var willBeVisible = !$drawingOverlay.is(':visible');
                                    $drawingOverlay.slideToggle(300);
                                    $toggle.text(willBeVisible ? '📋 Tafelbild verbergen' : '📋 Tafelbild anzeigen');
                                    $toggle.toggleClass('cbd-drawing-toggle-active', willBeVisible);
                                });

                                window.cbdDebug && console.log('CBD Classroom Page Filter: Added drawing section to', stableId);
                            }
                        }
                    }
                }
            });

            // Nav-Leiste + Link-Interception stehen seit AP-2.1 in
            // einmaligAufbauen() – hier bewusst nicht aufrufen.

            // Nachrüst-Haken (AP-2.1): Ein Container, der versteckt im DOM lag,
            // wurde von der Nummerierung nicht mitgezählt (sie zählt nur
            // sichtbare Container) und seine Formeln konnten im versteckten
            // Zustand nicht korrekt vermessen werden. Beides wird nachgeholt,
            // sobald wirklich etwas neu sichtbar geworden ist.
            //
            // Beide typeof-Prüfungen sind PFLICHT: block-numbering.js und
            // latex-renderer.js werden nur eingereiht, wenn die jeweilige
            // Funktion auf der Seite überhaupt gebraucht wird.
            if (!istErstaufbau && neuSichtbarAnzahl > 0) {
                if (typeof window.CBDRenumberBlocks === 'function') {
                    window.CBDRenumberBlocks();
                }
                if (typeof window.cbdRenderLatex === 'function') {
                    window.cbdRenderLatex(document);
                }
            }
        },

        /**
         * Einen gerade live freigegebenen Container für 8 Sekunden markieren.
         *
         * Die Gestaltung der Klasse `cbd-neu-freigegeben` liefert AP-2.3.
         *
         * BEWUSST OHNE `scrollIntoView()` und ohne Fokuswechsel: Der Schüler
         * liest gerade – seine Scrollposition darf sich durch eine Freigabe
         * nicht verändern.
         */
        markiereNeuFreigegeben: function($container) {
            var laufenderZeitgeber = $container.data('cbdNeuFreigegebenZeitgeber');

            // Wird derselbe Container innerhalb der acht Sekunden erneut
            // freigegeben, beginnt die Frist von vorn statt sich zu stapeln.
            if (laufenderZeitgeber) {
                window.clearTimeout(laufenderZeitgeber);
            }

            $container.addClass('cbd-neu-freigegeben');

            $container.data('cbdNeuFreigegebenZeitgeber', window.setTimeout(function() {
                $container.removeClass('cbd-neu-freigegeben');
                $container.removeData('cbdNeuFreigegebenZeitgeber');
            }, 8000));
        },

        /**
         * Add visual indicator that page is in classroom mode
         */
        addClassroomIndicator: function(className) {
            // Only add if not already present
            if ($('#cbd-classroom-mode-indicator').length > 0) {
                return;
            }

            var $indicator = $('<div id="cbd-classroom-mode-indicator">')
                .addClass('cbd-classroom-indicator')
                .html('<strong>Klassen-Modus:</strong> ' + this.escapeHtml(className));

            // Insert at top of content area
            if ($('.entry-content').length > 0) {
                $('.entry-content').prepend($indicator);
            } else if ($('article').length > 0) {
                $('article').prepend($indicator);
            } else {
                $('body').prepend($indicator);
            }
        },

        /**
         * Show error message to user
         */
        showError: function(message) {
            var $error = $('<div class="cbd-classroom-error">')
                .text(message);

            if ($('.entry-content').length > 0) {
                $('.entry-content').prepend($error);
            } else if ($('article').length > 0) {
                $('article').prepend($error);
            } else {
                $('body').prepend($error);
            }
        },

        /**
         * Show warning message to user
         */
        showWarning: function(message) {
            // Only show if not already present
            if ($('#cbd-classroom-warning').length > 0) {
                return;
            }

            var $warning = $('<div id="cbd-classroom-warning">')
                .addClass('cbd-classroom-warning')
                .html('<strong>⚠️ Hinweis:</strong> ' + this.escapeHtml(message));

            if ($('.entry-content').length > 0) {
                $('.entry-content').prepend($warning);
            } else if ($('article').length > 0) {
                $('article').prepend($warning);
            } else {
                $('body').prepend($warning);
            }
        },

        /**
         * Cleanup invalid container references in database
         */
        cleanupInvalidContainers: function(invalidContainers) {
            var self = this;

            window.cbdDebug && console.log('CBD Classroom Page Filter: Cleaning up', invalidContainers.length, 'invalid containers');

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_cleanup_invalid_containers',
                token: this.token,
                page_id: this.pageId,
                invalid_containers: invalidContainers
            }, function(response) {
                if (response.success) {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Cleanup successful -', response.data.message);
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Remaining treated containers:', response.data.remaining_count);

                    if (response.data.remaining_count === 0) {
                        // No treated containers left - page should not be in TOC anymore
                        self.showError('Diese Seite wurde bearbeitet und hat keine behandelten Blöcke mehr. ' +
                            'Bitte kehren Sie zum Inhaltsverzeichnis zurück. ' +
                            '<a href="javascript:history.back();" style="color: #d32f2f; text-decoration: underline;">Zurück</a>');
                    } else {
                        // Some containers remain
                        self.showWarning('Diese Seite wurde bearbeitet. ' + response.data.deleted_count +
                            ' veraltete Container-Referenz(en) wurden automatisch entfernt. ' +
                            response.data.remaining_count + ' behandelte(r) Block/Blöcke verbleiben.');
                    }
                } else {
                    console.error('CBD Classroom Page Filter: Cleanup failed -', response.data.message);
                }
            }).fail(function(xhr, status, error) {
                console.error('CBD Classroom Page Filter: Cleanup network error', error);
            });
        },

        /**
         * Eigene Classroom-Navigationsleiste injizieren.
         * Zeigt nur Seiten mit behandelten Blöcken (Daten von cbd_student_get_data).
         * Die URLs kommen vom Server und enthalten bereits ?classroom=&token=.
         */
        injectClassroomNavBar: function(className) {
            if ($('#cbd-classroom-nav-header').length > 0) {
                return; // Bereits vorhanden
            }

            var self = this;

            // ---- Navigations-<nav> mit Ladeindikator ----
            var $nav = $('<nav class="cbd-classroom-main-nav" aria-label="Klassenmodus Navigation">');
            var $navUl = $('<ul class="cbd-classroom-nav-loading"><li>…</li></ul>');
            $nav.append($navUl);

            // ---- Verlassen-Button ----
            var $leaveBtn = $('<button class="cbd-classroom-nav-leave">✕ Verlassen</button>');
            $leaveBtn.on('click', function() {
                try {
                    localStorage.removeItem('cbd_classroom_token');
                    localStorage.removeItem('cbd_classroom_id');
                } catch (e) {}
                var url = new URL(window.location.href);
                url.searchParams.delete('classroom');
                url.searchParams.delete('token');
                window.location.href = url.toString();
            });

            // ---- Mobiler Hamburger-Button ----
            var $menuToggle = $('<button class="cbd-classroom-menu-toggle" aria-label="Menü öffnen">☰</button>');
            $menuToggle.on('click', function() {
                $nav.toggleClass('active');
                $menuToggle.attr('aria-expanded', $nav.hasClass('active'));
            });

            // ---- Aufbau ----
            var $left = $('<div class="cbd-classroom-nav-left">')
                .append('<span class="cbd-classroom-nav-badge">📚 Klassen-Modus</span>')
                .append('<span class="cbd-classroom-nav-name">' + self.escapeHtml(className) + '</span>');

            var $center = $('<div class="cbd-classroom-nav-center">').append($nav);
            var $right  = $('<div class="cbd-classroom-nav-right">').append($menuToggle).append($leaveBtn);

            var $content = $('<div class="cbd-classroom-nav-content container">')
                .append($left).append($center).append($right);

            var $header = $('<header id="cbd-classroom-nav-header" class="cbd-classroom-nav-header">')
                .append($content);

            // Klick außerhalb schließt mobiles Menü
            // (siehe interceptLinks(): eigener Namensraum, vor dem Binden
            // abgeworfen, damit kein zweiter Handler entstehen kann)
            $(document).off('click.cbdClassroomNav');
            $(document).on('click.cbdClassroomNav', function(e) {
                if (!$header.is(e.target) && $header.has(e.target).length === 0) {
                    $nav.removeClass('active');
                }
            });

            // Normale Site-Header ausblenden, Classroom-Nav einfügen
            var $siteHeader = $('.site-header').first();
            if ($siteHeader.length) {
                $siteHeader.before($header);
                $siteHeader.hide();
            } else {
                $('body').prepend($header);
            }

            // ---- Behandelte Seiten laden, Header-Nav + Sidebar befüllen ----
            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_student_get_data',
                token: this.token
            }, function(response) {
                if (response.success && response.data.pages) {
                    var pages = response.data.pages;
                    // Header: nur Level-0-Hauptseiten
                    var $builtUl = self.buildNavUl(pages);
                    $navUl.replaceWith($builtUl);
                    // Sidebar: vollständige Hierarchie
                    self.injectClassroomSidebar(pages, response.data.class_name);
                } else {
                    $navUl.empty();
                }
            }).fail(function() {
                $navUl.empty();
            });
        },

        /**
         * Baut eine flache <ul> für die Header-Navigationsleiste.
         * Zeigt nur Hauptseiten (level === 0) mit URL (behandelte Seiten).
         */
        buildNavUl: function(pages) {
            var currentPath = window.location.pathname;
            var $rootUl     = $('<ul>');

            pages.forEach(function(item) {
                if (item.type !== 'page' || !item.page) return;
                var page = item.page;

                // Nur Level-0-Seiten mit URL für den Header
                if (!page.url || (page.level || 0) !== 0) return;

                var isActive = false;
                try { isActive = new URL(page.url).pathname === currentPath; } catch (e) {}

                var $li = $('<li>');
                if (isActive) $li.addClass('current-menu-item');
                $li.append($('<a>').attr('href', page.url).text(page.title));
                $rootUl.append($li);
            });

            return $rootUl;
        },

        /**
         * Ersetzt den Inhalt der Theme-Sidebar mit der hierarchischen
         * Seitenliste des Klassenmodus. Verwendet die Theme-CSS-Klassen
         * (page-tree, page-item, page-link, etc.) für einheitliches Styling.
         * Die Öffnen/Schließen-Logik des Themes bleibt unverändert.
         */
        injectClassroomSidebar: function(pages, className) {
            var $sidebar = $('#sidebar');
            if ($sidebar.length === 0) return;

            var self        = this;
            var currentPath = window.location.pathname;

            // Sidebar-Titel aktualisieren
            $sidebar.find('.sidebar-title').text('Inhaltsverzeichnis');

            var $nav = $sidebar.find('.sidebar-navigation');
            $nav.empty();

            // Abschnitts-Überschrift mit Klassenname
            $nav.append(
                $('<div class="sidebar-section-title">').text('📚 ' + (className || 'Klassen-Modus'))
            );

            // Fragenwand-Einstieg ganz oben in der Liste (Hotfix „Fragenwand
            // in Klassenlisten"). Diese Methode ersetzt den Inhalt der
            // Theme-Seitenleiste vollständig; der PHP-Einhänger
            // CBD_Fragenwand::page_index_eintrag() (AP-4.2) bedient nur den
            // Block fos/inhaltsverzeichnis und greift hier nicht.
            //
            // Markup zeichengleich zum PHP-Vorbild: dieselbe Trigger-Klasse
            // cbd-fragenwand-verweis (der delegierte Klick-Listener in
            // assets/js/fragenwand-frontend.js hängt an document und fängt
            // jedes Element damit ab) und dieselben Gestaltungsklassen aus
            // assets/css/fragenwand.css, Abschnitt „INHALTSVERZEICHNIS-EINTRAG".
            //
            // BEWUSST OHNE data-classroom/data-token — anders als in
            // classroom-frontend.js: Diese Datei läuft ausschließlich auf
            // Seiten, deren Adresse ?classroom=&token= trägt (init() steigt
            // ohne beide Parameter aus, siehe oben). Damit greift der
            // Standardweg von fragenwand-frontend.js, das die
            // Abfragezeichenfolge der Seite unverändert weiterreicht — und
            // die Frage, welche Parameter eine Sitzung ausmachen, bleibt an
            // genau einer Stelle beantwortet (CBD_Classroom_Gate::sitzung()).
            $nav.append(
                $('<div class="cbd-classroom-fragenwand page-index__zusatz page-index__zusatz--fragenwand">').append(
                    $('<button type="button" class="cbd-fragenwand-verweis page-index__fragenwand-link">')
                        .text('Fragenwand öffnen')
                )
            );

            // Hierarchischen Baum aufbauen
            var $rootUl     = $('<ul class="page-tree">');
            var levelUls    = [$rootUl];
            var levelLastLi = [null];

            pages.forEach(function(item) {
                if (item.type !== 'page' || !item.page) return;
                var page  = item.page;
                var level = page.level || 0;

                // Stack anpassen wenn Ebene steigt oder sinkt
                if (level < levelUls.length - 1) {
                    levelUls.length    = level + 1;
                    levelLastLi.length = level + 1;
                }
                while (levelUls.length <= level) {
                    var $parentLi = levelLastLi[levelUls.length - 1];
                    if (!$parentLi) break;
                    var $sub = $('<ul class="page-tree-children">');
                    $parentLi.append($sub);
                    levelUls.push($sub);
                    levelLastLi.push(null);
                }

                var $targetUl = levelUls[Math.min(level, levelUls.length - 1)];
                var isActive  = false;
                try { isActive = page.url && new URL(page.url).pathname === currentPath; } catch (e) {}

                // Feldname ist page_id (nicht id) – siehe Antwort von
                // cbd_student_get_data, gemessen im Live-Test von AP-1.3b.
                var $li = $('<li class="page-item">').attr('data-page-id', String(page.page_id));
                if (isActive) $li.addClass('current-page expanded');

                if (page.url) {
                    $li.append(
                        $('<a class="page-link">').attr('href', page.url)
                            .append($('<span class="page-title">').text(page.title))
                    );
                } else {
                    // Elternseite ohne eigene behandelte Blöcke: nicht klickbar, gedimmt
                    $li.addClass('cbd-sidebar-parent-only')
                       .append(
                           $('<span class="page-link cbd-sidebar-no-link">')
                               .append($('<span class="page-title">').text(page.title))
                       );
                }

                $targetUl.append($li);
                levelLastLi[Math.min(level, levelLastLi.length - 1)] = $li;
            });

            // Toggle-Buttons zu Einträgen mit Kindern hinzufügen.
            // Standardzustand aufgeklappt – AUSSER die Seiten-ID steht in der
            // gespeicherten Collapsed-Liste (localStorage, siehe Hilfsfunktionen
            // oben).
            var collapsedIds = cbdKlassenverzeichnisGeleseneCollapsedIds();
            $rootUl.find('.page-item').each(function() {
                if ($(this).children('ul').length > 0) {
                    $(this).addClass('has-children');
                    var pageId = String($(this).attr('data-page-id'));
                    if (collapsedIds.indexOf(pageId) === -1) {
                        $(this).addClass('expanded');
                    }
                    $(this).prepend(
                        $('<button class="page-toggle" aria-label="Unterseiten anzeigen/verbergen">')
                            .append('<span class="toggle-icon">▸</span>')
                    );
                }
            });

            $nav.append($rootUl);

            // Event-Delegation für Toggle-Buttons (Theme-JS läuft vor dem AJAX-Ergebnis)
            $nav.on('click', '.page-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $item = $(this).closest('.page-item');
                $item.toggleClass('expanded');
                var nunExpanded = $item.hasClass('expanded');
                $(this).attr('aria-expanded', nunExpanded);

                // Collapsed-Liste (localStorage) synchron nachziehen.
                var pageId = String($item.attr('data-page-id'));
                var ids = cbdKlassenverzeichnisGeleseneCollapsedIds();
                var pos = ids.indexOf(pageId);
                if (nunExpanded) {
                    if (pos !== -1) { ids.splice(pos, 1); }
                } else {
                    if (pos === -1) { ids.push(pageId); }
                }
                cbdKlassenverzeichnisSchreibeCollapsedIds(ids);
            });
        },

        /**
         * Alle internen Link-Klicks auf der Seite abfangen und Classroom-Parameter
         * automatisch anhängen, damit der Klassenmodus beim Navigieren erhalten bleibt.
         */
        interceptLinks: function() {
            var classroomId = this.classroomId;
            var token = this.token;
            var siteHostname = window.location.hostname;

            // Gürtel und Hosenträger (AP-2.1): Diese Methode wird seit AP-2.1
            // ausschließlich aus einmaligAufbauen() gerufen, läuft also nur
            // einmal. Der Abwurf des eigenen Namensraums stellt aber sicher,
            // dass auch ein versehentlicher zweiter Aufruf keinen zweiten
            // Klick-Abfänger hinterlässt (jeder Klick würde sonst mehrfach
            // umgeleitet).
            $(document).off('click.cbdClassroomLinks');

            $(document).on('click.cbdClassroomLinks', 'a[href]', function(e) {
                var href = $(this).attr('href');
                if (!href || href.charAt(0) === '#') return;
                try {
                    var url = new URL(href, window.location.href);
                    if (url.hostname !== siteHostname) return;        // externer Link
                    if (url.searchParams.get('classroom')) return;     // schon gesetzt
                    e.preventDefault();
                    url.searchParams.set('classroom', classroomId);
                    url.searchParams.set('token', token);
                    window.location.href = url.toString();
                } catch (e) { /* ungültige URL – ignorieren */ }
            });
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        ClassroomPageFilter.init();
    });

})(jQuery);
