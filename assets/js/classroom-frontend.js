/**
 * Container Block Designer - Classroom Frontend (Schueler-Ansicht)
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

(function($) {
    'use strict';

    var ClassroomFrontend = {
        classId: null,
        token: null,

        init: function() {
            this.loadClasses();
            this.bindEvents();
            this.checkExistingAuth();
        },

        /**
         * Load available classes into dropdown
         */
        loadClasses: function() {
            var self = this;

            $.post(cbdClassroomFrontend.ajaxUrl, {
                action: 'cbd_get_public_classes'
            }, function(response) {
                if (response.success && response.data.length > 0) {
                    var $select = $('#cbd-class-select');
                    $select.empty();
                    $select.append('<option value="">-- Klasse wählen --</option>');

                    response.data.forEach(function(cls) {
                        $select.append('<option value="' + cls.id + '">' + self.escapeHtml(cls.name) + '</option>');
                    });
                }
            });
        },

        bindEvents: function() {
            var self = this;
            $('#cbd-class-login').on('click', function() {
                self.handleAuth();
            });
            $('#cbd-class-logout').on('click', function() {
                self.handleLogout();
            });
            // Enter key on password field
            $('#cbd-class-password').on('keypress', function(e) {
                if (e.which === 13) {
                    self.handleAuth();
                }
            });
        },

        /**
         * Pruefe ob bereits eine Authentifizierung existiert (Session Token)
         */
        checkExistingAuth: function() {
            var token = this.getStoredToken();
            var classId = this.getStoredClassId();

            if (token && classId) {
                this.token = token;
                this.classId = classId;
                this.loadClassroomData();
            }
        },

        /**
         * Handle Auth (Login)
         */
        handleAuth: function() {
            var self = this;
            var classId = $('#cbd-class-select').val();
            var password = $('#cbd-class-password').val();
            var $error = $('#cbd-classroom-error');
            var $btn = $('#cbd-class-login');

            if (!classId || !password) {
                $error.text('Bitte wählen Sie eine Klasse und geben Sie das Passwort ein.').show();
                return;
            }

            $btn.prop('disabled', true).text('Wird geprüft...');
            $error.hide();

            $.post(cbdClassroomFrontend.ajaxUrl, {
                action: 'cbd_student_auth',
                class_id: classId,
                password: password
            }, function(response) {
                $btn.prop('disabled', false).text('Einloggen');

                if (response.success) {
                    self.token = response.data.token;
                    self.classId = classId;
                    self.storeToken(response.data.token);
                    self.storeClassId(classId);
                    self.loadClassroomData();
                } else {
                    $error.text(response.data.message || 'Falsches Passwort.').show();
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Einloggen');
                $error.text('Netzwerk-Fehler. Bitte versuchen Sie es erneut.').show();
            });
        },

        /**
         * Load classroom data (pages, drawings)
         */
        loadClassroomData: function() {
            var self = this;

            console.log('[CBD Classroom] Loading classroom data...');

            $.post(cbdClassroomFrontend.ajaxUrl, {
                action: 'cbd_student_get_data',
                token: this.token
            }, function(response) {
                console.log('[CBD Classroom] Response received:', response);

                if (response.success) {
                    console.log('[CBD Classroom] Pages data:', response.data.pages);
                    console.log('[CBD Classroom] Number of items:', response.data.pages ? response.data.pages.length : 0);
                    self.renderClassroomContent(response.data);
                } else {
                    console.error('[CBD Classroom] Error response:', response.data);
                    $('#cbd-classroom-error').text(response.data.message || 'Fehler beim Laden.').show();
                    self.clearAuth();
                }
            }).fail(function() {
                console.error('[CBD Classroom] Network error');
                $('#cbd-classroom-error').text('Netzwerk-Fehler.').show();
            });
        },

        renderClassroomContent: function(data) {
            // Hide auth, show content
            $('#cbd-classroom-auth').hide();
            $('#cbd-classroom-content').show();

            // Set class name
            $('#cbd-classroom-class-name').text(data.class_name);

            // Render table of contents
            var $pagesContainer = $('#cbd-classroom-pages');
            $pagesContainer.empty();

            console.log('[CBD Classroom] Rendering content, pages:', data.pages);
            console.log('[CBD Classroom] Pages length:', data.pages ? data.pages.length : 'undefined');

            if (data.pages && data.pages.length > 0) {
                console.log('[CBD Classroom] Rendering', data.pages.length, 'items');
                data.pages.forEach(function(item, index) {
                    console.log('[CBD Classroom] Item', index, ':', item);
                    if (item.type === 'page' && item.page) {
                        var page = item.page;
                        var level = page.level || 0;

                        console.log('[CBD Classroom] Rendering page:', page.title, 'Level:', level);

                        // Create page item with indentation based on level
                        var $pageItem = $('<div class="cbd-classroom-page-item">');
                        $pageItem.css('margin-left', (level * 24) + 'px');

                        // Add level class for styling
                        $pageItem.addClass('cbd-level-' + level);

                        if (page.url) {
                            // Page has URL - clickable (treated page)
                            var $pageLink = $('<a>')
                                .attr('href', page.url)
                                .addClass('cbd-classroom-page-link')
                                .text(page.title);

                            var $badge = $('<span>')
                                .addClass('cbd-treated-count-badge')
                                .text(page.treated_count + ' behandelt');

                            $pageItem.append($pageLink).append($badge);
                        } else {
                            // No URL - grayed out parent page
                            var $pageTitle = $('<div class="cbd-classroom-page-grayed">')
                                .text(page.title);

                            // For level 0 parents, make them look like headers
                            if (level === 0) {
                                $pageTitle.addClass('cbd-parent-header-grayed');
                            }

                            $pageItem.append($pageTitle);
                        }

                        $pagesContainer.append($pageItem);
                    }
                }.bind(this));
            } else {
                $pagesContainer.append('<p class="cbd-no-pages">Keine behandelten Blöcke vorhanden.</p>');
            }
        },

        handleLogout: function() {
            this.clearAuth();
            $('#cbd-classroom-content').hide();
            $('#cbd-classroom-auth').show();
            $('#cbd-class-select').val('');
            $('#cbd-class-password').val('');
        },

        // Token storage
        storeToken: function(token) {
            try {
                localStorage.setItem('cbd_classroom_token', token);
            } catch (e) {
                // Fallback if localStorage not available
            }
        },

        getStoredToken: function() {
            try {
                return localStorage.getItem('cbd_classroom_token');
            } catch (e) {
                return null;
            }
        },

        storeClassId: function(classId) {
            try {
                localStorage.setItem('cbd_classroom_class_id', classId);
            } catch (e) {
                // Fallback
            }
        },

        getStoredClassId: function() {
            try {
                return localStorage.getItem('cbd_classroom_class_id');
            } catch (e) {
                return null;
            }
        },

        clearAuth: function() {
            this.token = null;
            this.classId = null;
            try {
                localStorage.removeItem('cbd_classroom_token');
                localStorage.removeItem('cbd_classroom_class_id');
            } catch (e) {
                // Ignore
            }
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('#cbd-classroom-app').length > 0) {
            ClassroomFrontend.init();
        }
    });

})(jQuery);
