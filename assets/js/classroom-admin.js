/**
 * Container Block Designer - Classroom Admin JavaScript
 * Handles class CRUD operations in the admin panel.
 *
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

(function($) {
    'use strict';

    var CBD_Classroom = {

        // State
        classes: [],
        editingClassId: 0,
        pageTemplate: null,

        /**
         * Initialize
         */
        init: function() {
            // Store reference to page selector template
            this.pageTemplate = $('.cbd-page-selector').first().clone();

            // Bind events
            this.bindEvents();

            // Load classes
            this.loadClasses();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;

            // Form submit
            $('#cbd-class-form').on('submit', function(e) {
                e.preventDefault();
                self.saveClass();
            });

            // Add page button
            $('#cbd-add-page').on('click', function() {
                self.addPageSelector();
            });

            // Remove page (delegated)
            $(document).on('click', '.cbd-remove-page', function() {
                var $selector = $(this).closest('.cbd-page-selector');
                if ($('.cbd-page-selector').length > 1) {
                    $selector.remove();
                } else {
                    $selector.find('select').val('');
                }
            });

            // Cancel edit
            $('#cbd-cancel-edit').on('click', function() {
                self.resetForm();
            });

            // Edit class (delegated)
            $(document).on('click', '.cbd-edit-class', function() {
                var classId = $(this).data('class-id');
                self.editClass(classId);
            });

            // Delete class (delegated)
            $(document).on('click', '.cbd-delete-class', function() {
                var classId = $(this).data('class-id');
                var className = $(this).data('class-name');
                self.deleteClass(classId, className);
            });
        },

        /**
         * Load all classes via AJAX
         */
        loadClasses: function() {
            var self = this;

            $('#cbd-classes-loading').show();
            $('#cbd-classes-table').hide();
            $('#cbd-no-classes').hide();

            $.post(cbdClassroomAdmin.ajaxUrl, {
                action: 'cbd_get_classes',
                nonce: cbdClassroomAdmin.nonce
            }, function(response) {
                $('#cbd-classes-loading').hide();

                if (response.success && response.data.length > 0) {
                    self.classes = response.data;
                    self.renderClassesTable(response.data);
                    $('#cbd-classes-table').show();
                } else {
                    $('#cbd-no-classes').show();
                }
            }).fail(function() {
                $('#cbd-classes-loading').hide();
                $('#cbd-no-classes').text('Fehler beim Laden der Klassen.').show();
            });
        },

        /**
         * Render classes into the table
         */
        renderClassesTable: function(classes) {
            var $tbody = $('#cbd-classes-body');
            $tbody.empty();

            classes.forEach(function(cls) {
                var pagesHtml = '';
                if (cls.pages && cls.pages.length > 0) {
                    pagesHtml = '<div class="cbd-page-tags">';
                    cls.pages.forEach(function(page) {
                        pagesHtml += '<span class="cbd-page-tag">' + self.escHtml(page.post_title || 'Seite #' + page.page_id) + '</span>';
                    });
                    pagesHtml += '</div>';
                } else {
                    pagesHtml = '<em>Keine Seiten</em>';
                }

                var statusClass = cls.status === 'active' ? 'active' : 'inactive';
                var statusText = cls.status === 'active' ? 'Aktiv' : 'Inaktiv';

                var row = '<tr>' +
                    '<td class="column-name">' + self.escHtml(cls.name) + '</td>' +
                    '<td class="column-pages">' + pagesHtml + '</td>' +
                    '<td class="column-status"><span class="cbd-status-badge ' + statusClass + '">' + statusText + '</span></td>' +
                    '<td class="column-created">' + self.formatDate(cls.created_at) + '</td>' +
                    '<td class="column-actions">' +
                        '<div class="cbd-class-actions">' +
                            '<button type="button" class="button button-small cbd-edit-class" data-class-id="' + cls.id + '">Bearbeiten</button>' +
                            '<button type="button" class="button button-small button-link-delete cbd-delete-class" data-class-id="' + cls.id + '" data-class-name="' + self.escAttr(cls.name) + '">L\u00F6schen</button>' +
                        '</div>' +
                    '</td>' +
                '</tr>';

                $tbody.append(row);
            });

            // Fix: use self reference
            var self = this;
        },

        /**
         * Save class (create or update)
         */
        saveClass: function() {
            var self = this;
            var classId = parseInt($('#cbd-class-id').val()) || 0;
            var name = $('#cbd-class-name').val().trim();
            var password = $('#cbd-class-password').val();

            if (!name) {
                alert('Bitte geben Sie einen Klassennamen ein.');
                return;
            }

            if (classId === 0 && !password) {
                alert('Bitte geben Sie ein Passwort ein.');
                return;
            }

            // Collect page IDs
            var pageIds = [];
            $('.cbd-page-select').each(function() {
                var val = parseInt($(this).val());
                if (val > 0) {
                    pageIds.push(val);
                }
            });

            var $saveBtn = $('#cbd-save-class');
            $saveBtn.prop('disabled', true).text('Speichert...');

            $.post(cbdClassroomAdmin.ajaxUrl, {
                action: 'cbd_save_class',
                nonce: cbdClassroomAdmin.nonce,
                class_id: classId,
                name: name,
                password: password,
                page_ids: pageIds
            }, function(response) {
                $saveBtn.prop('disabled', false).text('Klasse speichern');

                if (response.success) {
                    self.resetForm();
                    self.loadClasses();
                    self.showNotice('Klasse gespeichert.', 'success');
                } else {
                    self.showNotice(response.data.message || 'Fehler beim Speichern.', 'error');
                }
            }).fail(function() {
                $saveBtn.prop('disabled', false).text('Klasse speichern');
                self.showNotice('Netzwerk-Fehler.', 'error');
            });
        },

        /**
         * Edit a class - populate form with data
         */
        editClass: function(classId) {
            var cls = this.classes.find(function(c) { return c.id == classId; });
            if (!cls) return;

            this.editingClassId = classId;
            $('#cbd-class-id').val(classId);
            $('#cbd-class-name').val(cls.name);
            $('#cbd-class-password').val(''); // Don't show password
            $('#cbd-password-hint').text('Leer lassen um das Passwort nicht zu aendern.');
            $('#cbd-form-title').text('Klasse bearbeiten');
            $('#cbd-cancel-edit').show();

            // Set page selectors
            var $pagesContainer = $('#cbd-class-pages');
            $pagesContainer.empty();

            if (cls.pages && cls.pages.length > 0) {
                var self = this;
                cls.pages.forEach(function(page) {
                    var $row = self.pageTemplate.clone();
                    $row.find('select').val(page.page_id);
                    $pagesContainer.append($row);
                });
            } else {
                $pagesContainer.append(this.pageTemplate.clone());
            }

            // Scroll to form
            $('html, body').animate({ scrollTop: $('.cbd-classroom-form-section').offset().top - 50 }, 300);
        },

        /**
         * Delete a class
         */
        deleteClass: function(classId, className) {
            if (!confirm('Klasse "' + className + '" wirklich l\u00F6schen?\n\nAlle zugehoerigen Zeichnungen werden ebenfalls geloescht!')) {
                return;
            }

            var self = this;

            $.post(cbdClassroomAdmin.ajaxUrl, {
                action: 'cbd_delete_class',
                nonce: cbdClassroomAdmin.nonce,
                class_id: classId
            }, function(response) {
                if (response.success) {
                    self.loadClasses();
                    self.showNotice('Klasse geloescht.', 'success');
                } else {
                    self.showNotice(response.data.message || 'Fehler beim Loeschen.', 'error');
                }
            });
        },

        /**
         * Add a page selector row
         */
        addPageSelector: function() {
            var $clone = this.pageTemplate.clone();
            $clone.find('select').val('');
            $('#cbd-class-pages').append($clone);
        },

        /**
         * Reset form to create mode
         */
        resetForm: function() {
            this.editingClassId = 0;
            $('#cbd-class-id').val(0);
            $('#cbd-class-name').val('');
            $('#cbd-class-password').val('');
            $('#cbd-password-hint').text('Dieses Passwort benoetigen die Schueler zum Zugriff auf die Klasse.');
            $('#cbd-form-title').text('Neue Klasse erstellen');
            $('#cbd-cancel-edit').hide();

            // Reset page selectors to one empty
            var $pagesContainer = $('#cbd-class-pages');
            $pagesContainer.empty();
            $pagesContainer.append(this.pageTemplate.clone());
        },

        /**
         * Show an admin notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + this.escHtml(message) + '</p></div>');
            $('.cbd-classroom-admin h1').after($notice);

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
        },

        /**
         * Format date string
         */
        formatDate: function(dateStr) {
            if (!dateStr) return '-';
            var d = new Date(dateStr);
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },

        /**
         * Escape HTML
         */
        escHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        /**
         * Escape attribute value
         */
        escAttr: function(str) {
            return this.escHtml(str).replace(/"/g, '&quot;');
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.cbd-classroom-admin').length > 0) {
            CBD_Classroom.init();
        }
    });

})(jQuery);
