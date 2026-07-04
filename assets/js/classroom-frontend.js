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
                        var hasCreds = self.getStoredCredentials(cls.id);
                        var label = self.escapeHtml(cls.name);
                        if (hasCreds) {
                            label += ' \u2713'; // checkmark for saved classes
                        }
                        $select.append('<option value="' + cls.id + '">' + label + '</option>');
                    });

                    // Update password field visibility for initial state
                    self.updatePasswordFieldVisibility();
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
            // When class selection changes, check for stored credentials
            $('#cbd-class-select').on('change', function() {
                self.updatePasswordFieldVisibility();
            });
        },

        /**
         * Show/hide password field based on stored credentials
         */
        updatePasswordFieldVisibility: function() {
            var classId = $('#cbd-class-select').val();
            var $passwordField = $('#cbd-class-password').closest('.cbd-classroom-field');
            var $btn = $('#cbd-class-login');

            if (cbdClassroomFrontend.isUserLoggedIn) {
                // WordPress user logged in — no password needed
                $passwordField.hide();
                $btn.text('Klasse betreten');
            } else if (classId && this.getStoredCredentials(classId)) {
                // Credentials stored — hide password, change button text
                $passwordField.hide();
                $btn.text('Einloggen');
            } else {
                // No credentials — show password field
                $passwordField.show();
                $btn.text('Einloggen');
            }
        },

        /**
         * Pruefe ob bereits eine Authentifizierung existiert (Session Token).
         * Wenn Token abgelaufen ist aber Credentials gespeichert sind,
         * automatisch neu einloggen.
         */
        checkExistingAuth: function() {
            var token = this.getStoredToken();
            var classId = this.getStoredClassId();

            if (token && classId) {
                this.token = token;
                this.classId = classId;
                this.loadClassroomData();
            } else if (classId) {
                // Token fehlt/abgelaufen aber classId bekannt —
                // WP-User: direkt ohne Passwort neu einloggen
                // Andere: mit gespeicherten Credentials versuchen
                if (cbdClassroomFrontend.isUserLoggedIn) {
                    this.autoLogin(classId, '');
                } else {
                    var creds = this.getStoredCredentials(classId);
                    if (creds) {
                        this.autoLogin(classId, creds.password);
                    }
                }
            }
        },

        /**
         * Automatisch einloggen mit gespeicherten Credentials
         */
        autoLogin: function(classId, password) {
            var self = this;
            var isWpUser = cbdClassroomFrontend.isUserLoggedIn;

            var postData = {
                action: 'cbd_student_auth',
                class_id: classId
            };

            if (isWpUser) {
                postData._wpnonce = cbdClassroomFrontend.nonce;
            } else {
                postData.password = password;
            }

            $.post(cbdClassroomFrontend.ajaxUrl, postData, function(response) {
                if (response.success) {
                    self.token = response.data.token;
                    self.classId = classId;
                    self.storeToken(response.data.token);
                    self.storeClassId(classId);
                    self.loadClassroomData();
                } else {
                    // Credentials ungültig (Passwort geändert) — entfernen
                    self.removeStoredCredentials(classId);
                    self.updatePasswordFieldVisibility();
                }
            });
        },

        /**
         * Handle Auth (Login)
         * Uses stored credentials if available, otherwise requires password input
         */
        handleAuth: function() {
            var self = this;
            var classId = $('#cbd-class-select').val();
            var $error = $('#cbd-classroom-error');
            var $btn = $('#cbd-class-login');
            var isWpUser = cbdClassroomFrontend.isUserLoggedIn;

            if (!classId) {
                $error.text('Bitte wählen Sie eine Klasse.').show();
                return;
            }

            // WordPress user: no password needed
            // Other users: try stored credentials, then password field
            var storedCreds = !isWpUser ? this.getStoredCredentials(classId) : null;
            var password = isWpUser ? '' : (storedCreds ? storedCreds.password : $('#cbd-class-password').val());

            if (!isWpUser && !password) {
                $error.text('Bitte geben Sie das Passwort ein.').show();
                return;
            }

            $btn.prop('disabled', true).text('Wird geprüft...');
            $error.hide();

            var postData = {
                action: 'cbd_student_auth',
                class_id: classId
            };

            if (isWpUser) {
                // Send nonce for WordPress authentication
                postData._wpnonce = cbdClassroomFrontend.nonce;
            } else {
                postData.password = password;
            }

            $.post(cbdClassroomFrontend.ajaxUrl, postData, function(response) {
                $btn.prop('disabled', false).text(isWpUser ? 'Klasse betreten' : 'Einloggen');

                if (response.success) {
                    self.token = response.data.token;
                    self.classId = classId;
                    self.storeToken(response.data.token);
                    self.storeClassId(classId);
                    // Save credentials locally for future logins (not for WP users)
                    if (!isWpUser && password) {
                        self.storeCredentials(classId, password, response.data.class_name);
                    }
                    self.loadClassroomData();
                } else {
                    // If stored credentials failed (e.g. password changed),
                    // remove them and show password field
                    if (storedCreds) {
                        self.removeStoredCredentials(classId);
                        self.updatePasswordFieldVisibility();
                        $error.text('Gespeichertes Passwort ist ungültig. Bitte erneut eingeben.').show();
                    } else {
                        $error.text(response.data.message || 'Falsches Passwort.').show();
                    }
                }
            }).fail(function() {
                $btn.prop('disabled', false).text(isWpUser ? 'Klasse betreten' : 'Einloggen');
                $error.text('Netzwerk-Fehler. Bitte versuchen Sie es erneut.').show();
            });
        },

        /**
         * Load classroom data (pages, drawings)
         */
        loadClassroomData: function() {
            var self = this;

            window.cbdDebug && console.log('[CBD Classroom] Loading classroom data...');

            $.post(cbdClassroomFrontend.ajaxUrl, {
                action: 'cbd_student_get_data',
                token: this.token
            }, function(response) {
                window.cbdDebug && console.log('[CBD Classroom] Response received:', response);

                if (response.success) {
                    window.cbdDebug && console.log('[CBD Classroom] Pages data:', response.data.pages);
                    window.cbdDebug && console.log('[CBD Classroom] Number of items:', response.data.pages ? response.data.pages.length : 0);
                    self.renderClassroomContent(response.data);
                } else {
                    console.warn('[CBD Classroom] Token expired or invalid, trying re-login...');
                    // Token abgelaufen — versuche mit gespeicherten Credentials
                    var creds = self.classId ? self.getStoredCredentials(self.classId) : null;
                    if (creds) {
                        self.autoLogin(self.classId, creds.password);
                    } else {
                        console.error('[CBD Classroom] No stored credentials, showing login form');
                        $('#cbd-classroom-error').text(response.data.message || 'Sitzung abgelaufen. Bitte erneut einloggen.').show();
                        self.clearAuth();
                    }
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

            window.cbdDebug && console.log('[CBD Classroom] Rendering content, pages:', data.pages);
            window.cbdDebug && console.log('[CBD Classroom] Pages length:', data.pages ? data.pages.length : 'undefined');

            if (data.pages && data.pages.length > 0) {
                window.cbdDebug && console.log('[CBD Classroom] Rendering', data.pages.length, 'items');
                data.pages.forEach(function(item, index) {
                    window.cbdDebug && console.log('[CBD Classroom] Item', index, ':', item);
                    if (item.type === 'page' && item.page) {
                        var page = item.page;
                        var level = page.level || 0;

                        window.cbdDebug && console.log('[CBD Classroom] Rendering page:', page.title, 'Level:', level);

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
            this.updatePasswordFieldVisibility();
        },

        // =============================================
        // Token storage
        // =============================================

        storeToken: function(token) {
            try {
                localStorage.setItem('cbd_classroom_token', token);
            } catch (e) {}
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
            } catch (e) {}
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
            } catch (e) {}
        },

        // =============================================
        // Credential storage (per class, persistent)
        // Stored as: cbd_classroom_credentials → { "classId": { password, className } }
        // =============================================

        storeCredentials: function(classId, password, className) {
            try {
                var creds = this.getAllStoredCredentials();
                creds[classId] = {
                    password: password,
                    className: className || ''
                };
                localStorage.setItem('cbd_classroom_credentials', JSON.stringify(creds));
            } catch (e) {}
        },

        getStoredCredentials: function(classId) {
            try {
                var creds = this.getAllStoredCredentials();
                return creds[classId] || null;
            } catch (e) {
                return null;
            }
        },

        removeStoredCredentials: function(classId) {
            try {
                var creds = this.getAllStoredCredentials();
                delete creds[classId];
                localStorage.setItem('cbd_classroom_credentials', JSON.stringify(creds));
            } catch (e) {}
        },

        getAllStoredCredentials: function() {
            try {
                var raw = localStorage.getItem('cbd_classroom_credentials');
                return raw ? JSON.parse(raw) : {};
            } catch (e) {
                return {};
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
