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

                        // Add drawing overlay if exists
                        if (drawing.drawing_data) {
                            var $content = $container.find('.cbd-container-content').first();
                            if ($content.length > 0 && $content.find('.cbd-drawing-overlay').length === 0) {
                                var $overlay = $('<div class="cbd-drawing-overlay">');
                                $overlay.append('<img src="' + drawing.drawing_data + '" alt="Tafel-Zeichnung">');
                                $content.prepend($overlay);
                                console.log('CBD Classroom Page Filter: Added drawing overlay to', stableId);
                            }
                        }
                    }
                }
            });

            // Add classroom mode indicator
            this.addClassroomIndicator(data.class_name);
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
