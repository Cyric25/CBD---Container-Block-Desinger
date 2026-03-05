/**
 * Container Block Designer - Classroom Page Filter
 * Filters container blocks on normal WordPress pages in classroom mode
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

(function($) {
    'use strict';

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
                console.log('CBD Classroom Page Filter: Missing parameters, skipping');
                return;
            }

            console.log('CBD Classroom Page Filter: Initializing for page', this.pageId, 'classroom', this.classroomId);
            this.loadClassroomData();
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
                    console.log('CBD Classroom Page Filter: Received data', response.data);
                    self.filterContainers(response.data);
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
         * Filter containers based on classroom data
         */
        filterContainers: function(data) {
            var treatedContainers = data.treated_containers || [];
            var drawings = data.drawings || {};

            console.log('CBD Classroom Page Filter: Treated containers:', treatedContainers);
            console.log('CBD Classroom Page Filter: Drawings:', Object.keys(drawings));

            // Find all container blocks on the page
            // Try multiple selectors to catch all containers
            var $containers = $('[data-wp-interactive="container-block-designer"], [data-stable-id^="cbd-"]');
            console.log('CBD Classroom Page Filter: Found', $containers.length, 'container blocks');

            // DEBUG: Log all found container stable IDs
            var foundStableIds = [];
            $containers.each(function() {
                var stableId = $(this).attr('data-stable-id');
                if (stableId) {
                    foundStableIds.push(stableId);
                }
            });
            console.log('CBD Classroom Page Filter: All stable IDs found in DOM:', foundStableIds);
            console.log('CBD Classroom Page Filter: Treated containers from server:', treatedContainers);

            // Check for inconsistencies: containers in DB but not in DOM
            var missingContainers = [];
            treatedContainers.forEach(function(containerId) {
                if (foundStableIds.indexOf(containerId) === -1) {
                    missingContainers.push(containerId);
                }
            });

            if (missingContainers.length > 0) {
                console.warn('CBD Classroom Page Filter: WARNING - ' + missingContainers.length + ' treated containers from DB not found in DOM (page was likely edited):', missingContainers);

                // Show warning but DON'T auto-cleanup - teacher might want to re-mark the blocks
                this.showWarning('Hinweis: Diese Seite wurde bearbeitet. ' + missingContainers.length +
                    ' markierte(r) Block/Blöcke wurde(n) auf der Seite nicht gefunden. ' +
                    'Die Markierungen bleiben in der Datenbank gespeichert, werden aber auf dieser Seite nicht angezeigt.');

                // DON'T call cleanupInvalidContainers() - markings should persist
            }

            // Filter to only show containers that exist in BOTH DOM and DB
            var validTreatedContainers = treatedContainers.filter(function(containerId) {
                return foundStableIds.indexOf(containerId) !== -1;
            });

            console.log('CBD Classroom Page Filter: Valid treated containers (intersection):', validTreatedContainers);

            if ($containers.length === 0) {
                console.log('CBD Classroom Page Filter: No containers found on page');
                return;
            }

            // Hide all containers by default, then show only treated ones that exist in DOM
            $containers.each(function() {
                var $container = $(this);
                var stableId = $container.attr('data-stable-id');

                console.log('CBD Classroom Page Filter: Processing container', stableId);

                if (!stableId || validTreatedContainers.indexOf(stableId) === -1) {
                    // Container is NOT treated OR doesn't exist in DB -> hide it
                    $container.hide();
                    console.log('CBD Classroom Page Filter: Hiding non-treated container', stableId);
                } else {
                    // Container IS treated AND exists in DOM -> show it and add drawings/badges
                    $container.show();
                    console.log('CBD Classroom Page Filter: Showing treated container', stableId);

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

                                console.log('CBD Classroom Page Filter: Added drawing section to', stableId);
                            }
                        }
                    }
                }
            });

            // Classroom-Navigationsleiste einfügen (ersetzt die normale Theme-Nav)
            this.className = data.class_name;
            this.injectClassroomNavBar(data.class_name);

            // Alle internen Links abfangen, damit Classroom-Params erhalten bleiben
            this.interceptLinks();
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

            console.log('CBD Classroom Page Filter: Cleaning up', invalidContainers.length, 'invalid containers');

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_cleanup_invalid_containers',
                token: this.token,
                page_id: this.pageId,
                invalid_containers: invalidContainers
            }, function(response) {
                if (response.success) {
                    console.log('CBD Classroom Page Filter: Cleanup successful -', response.data.message);
                    console.log('CBD Classroom Page Filter: Remaining treated containers:', response.data.remaining_count);

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

            // ---- Behandelte Seiten laden und Nav befüllen ----
            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_student_get_data',
                token: this.token
            }, function(response) {
                if (response.success && response.data.pages) {
                    var $builtUl = self.buildNavUl(response.data.pages);
                    $navUl.replaceWith($builtUl);
                } else {
                    $navUl.empty(); // Ladeindikator entfernen, Nav bleibt leer
                }
            }).fail(function() {
                $navUl.empty();
            });
        },

        /**
         * Baut eine hierarchische <ul> aus der behandelten Seitenliste.
         * Nur Seiten mit URL (is_treated) werden als Links angezeigt.
         * Parent-only Seiten (ohne URL) werden als nicht-klickbare Überschrift
         * mit Unterpunkt-Dropdown angezeigt, sofern sie Kinder haben.
         */
        buildNavUl: function(pages) {
            var self          = this;
            var currentPath   = window.location.pathname;
            var $rootUl       = $('<ul>');
            // Stack: levelUls[N] = das <ul> in das Einträge auf Ebene N kommen
            var levelUls      = [$rootUl];
            // Letztes <li> pro Ebene (für Dropdown-Anhang)
            var levelLastLi   = [null];

            pages.forEach(function(item) {
                if (item.type !== 'page' || !item.page) return;
                var page  = item.page;
                var level = page.level || 0;

                // Wenn wir auf eine höhere Ebene zurückgehen: Stack kürzen
                if (level < levelUls.length - 1) {
                    levelUls.length    = level + 1;
                    levelLastLi.length = level + 1;
                }

                // Wenn wir tiefer gehen als der Stack reicht: neues <ul> unter letztem <li>
                while (levelUls.length <= level) {
                    var $parentLi = levelLastLi[levelUls.length - 1];
                    if (!$parentLi) {
                        // Kein Parent vorhanden – Ebene nicht weiter verschachteln
                        break;
                    }
                    var $sub = $('<ul>');
                    $parentLi.append($sub);
                    levelUls.push($sub);
                    levelLastLi.push(null);
                }

                var $targetUl = levelUls[Math.min(level, levelUls.length - 1)];
                var $li = $('<li>');

                if (page.url) {
                    // Aktuelle Seite hervorheben (Pfad ohne Query vergleichen)
                    var isActive = false;
                    try {
                        isActive = new URL(page.url).pathname === currentPath;
                    } catch (e) {}

                    var $a = $('<a>')
                        .attr('href', page.url)
                        .text(page.title);
                    if (isActive) {
                        $li.addClass('current-menu-item');
                    }
                    $li.append($a);
                } else {
                    // Nicht klickbare Elternseite – nur als Label
                    $li.addClass('cbd-nav-parent-label')
                       .append($('<span>').text(page.title));
                }

                $targetUl.append($li);
                levelLastLi[Math.min(level, levelLastLi.length - 1)] = $li;
            });

            return $rootUl;
        },

        /**
         * Alle internen Link-Klicks auf der Seite abfangen und Classroom-Parameter
         * automatisch anhängen, damit der Klassenmodus beim Navigieren erhalten bleibt.
         */
        interceptLinks: function() {
            var classroomId = this.classroomId;
            var token = this.token;
            var siteHostname = window.location.hostname;

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
