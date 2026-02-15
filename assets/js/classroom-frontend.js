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
            this.bindEvents();
            this.checkExistingAuth();
        },

        bindEvents: function() {
            $(document).on('submit', '#cbd-classroom-auth-form', this.handleAuth.bind(this));
            $(document).on('click', '.cbd-classroom-logout', this.handleLogout.bind(this));
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
         * Handle Auth Form Submit
         */
        handleAuth: function(e) {
            e.preventDefault();

            var $form = $(e.target);
            var $classSelect = $form.find('select[name="class_id"]');
            var $passwordInput = $form.find('input[name="password"]');
            var $submitBtn = $form.find('button[type="submit"]');
            var $error = $form.find('.cbd-classroom-error');

            var classId = $classSelect.val();
            var password = $passwordInput.val();

            if (!classId || !password) {
                $error.text('Bitte wählen Sie eine Klasse und geben Sie das Passwort ein.').show();
                return;
            }

            $submitBtn.prop('disabled', true).text('Wird geprüft...');
            $error.hide();

            $.ajax({
                url: cbdClassroomFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cbd_student_auth',
                    class_id: classId,
                    password: password
                },
                success: function(response) {
                    if (response.success && response.data.token) {
                        // Speichere Token und Klassen-ID
                        ClassroomFrontend.setStoredToken(response.data.token);
                        ClassroomFrontend.setStoredClassId(classId);
                        ClassroomFrontend.token = response.data.token;
                        ClassroomFrontend.classId = classId;

                        // Lade Klassendaten
                        ClassroomFrontend.loadClassroomData();
                    } else {
                        $error.text(response.data || 'Falsches Passwort.').show();
                        $submitBtn.prop('disabled', false).text('Anmelden');
                    }
                },
                error: function() {
                    $error.text('Verbindungsfehler. Bitte versuchen Sie es erneut.').show();
                    $submitBtn.prop('disabled', false).text('Anmelden');
                }
            });
        },

        /**
         * Lade Klassendaten (Zeichnungen, behandelte Bloecke)
         */
        loadClassroomData: function() {
            var self = this;

            $.ajax({
                url: cbdClassroomFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cbd_student_get_data',
                    token: this.token,
                    page_id: cbdClassroomFrontend.pageId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderClassroomContent(response.data);
                    } else {
                        // Token expired oder ungueltig
                        self.handleLogout();
                        $('.cbd-classroom-error').text('Sitzung abgelaufen. Bitte melden Sie sich erneut an.').show();
                    }
                },
                error: function() {
                    $('.cbd-classroom-error').text('Fehler beim Laden der Daten.').show();
                }
            });
        },

        /**
         * Rendere Classroom Content (Zeichnungen + Behandelt-Status)
         */
        renderClassroomContent: function(data) {
            // Verstecke Auth-Form
            $('#cbd-classroom-auth-form').hide();

            // Zeige Content Area
            var $content = $('#cbd-classroom-content');
            $content.show();

            // Klassen-Name anzeigen
            $content.find('.cbd-classroom-class-name').text(data.className || 'Klasse');

            // Zeichnungen anzeigen
            if (data.drawings && data.drawings.length > 0) {
                this.renderDrawings(data.drawings);
            } else {
                $('#cbd-classroom-drawings').html('<p class="cbd-no-drawings">Keine Notizen verfügbar.</p>');
            }

            // Behandelt-Status aktualisieren (grüne Badges)
            this.updateBehandeltStatus(data.behandelt || []);
        },

        /**
         * Rendere Zeichnungen als Canvas-Previews
         */
        renderDrawings: function(drawings) {
            var $container = $('#cbd-classroom-drawings');
            $container.empty();

            drawings.forEach(function(drawing) {
                var $item = $('<div class="cbd-drawing-item"></div>');

                // Titel (Container-ID als Fallback)
                var title = drawing.containerTitle || 'Notiz ' + drawing.containerId.substring(0, 8);
                $item.append('<h4>' + ClassroomFrontend.escHtml(title) + '</h4>');

                // Canvas Preview
                var $canvas = $('<canvas class="cbd-drawing-canvas" width="400" height="300"></canvas>');
                $item.append($canvas);

                // Zeichnung laden
                ClassroomFrontend.loadDrawingOnCanvas($canvas[0], drawing.drawingData);

                // Timestamp
                var date = new Date(drawing.updatedAt);
                var dateStr = date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});
                $item.append('<p class="cbd-drawing-date">Zuletzt aktualisiert: ' + dateStr + '</p>');

                $container.append($item);
            });
        },

        /**
         * Lade Zeichnung auf Canvas
         */
        loadDrawingOnCanvas: function(canvas, dataUrl) {
            if (!dataUrl) return;

            var ctx = canvas.getContext('2d');
            var img = new Image();

            img.onload = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            };

            img.src = dataUrl;
        },

        /**
         * Update behandelt status auf den Container-Bloecken
         */
        updateBehandeltStatus: function(behandeltIds) {
            if (!behandeltIds || behandeltIds.length === 0) return;

            // Finde alle Container-Bloecke auf der Seite
            behandeltIds.forEach(function(containerId) {
                var $container = $('[data-stable-id="' + containerId + '"]');
                if ($container.length > 0) {
                    // Fuege Badge hinzu
                    if (!$container.find('.cbd-behandelt-badge').length) {
                        var $badge = $('<div class="cbd-behandelt-badge">✓ Behandelt</div>');
                        $container.find('.cbd-action-buttons').before($badge);
                    }
                }
            });
        },

        /**
         * Logout Handler
         */
        handleLogout: function(e) {
            if (e) e.preventDefault();

            this.clearStoredToken();
            this.clearStoredClassId();
            this.token = null;
            this.classId = null;

            // Zeige Auth-Form wieder
            $('#cbd-classroom-auth-form').show();
            $('#cbd-classroom-content').hide();

            // Reset Form
            $('#cbd-classroom-auth-form')[0].reset();
            $('#cbd-classroom-auth-form button[type="submit"]').prop('disabled', false).text('Anmelden');
        },

        /**
         * LocalStorage Helpers
         */
        getStoredToken: function() {
            return localStorage.getItem('cbd_classroom_token');
        },

        setStoredToken: function(token) {
            localStorage.setItem('cbd_classroom_token', token);
        },

        clearStoredToken: function() {
            localStorage.removeItem('cbd_classroom_token');
        },

        getStoredClassId: function() {
            return localStorage.getItem('cbd_classroom_class_id');
        },

        setStoredClassId: function(classId) {
            localStorage.setItem('cbd_classroom_class_id', classId);
        },

        clearStoredClassId: function() {
            localStorage.removeItem('cbd_classroom_class_id');
        },

        /**
         * HTML Escaping
         */
        escHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Init on DOM ready
    $(document).ready(function() {
        if (typeof cbdClassroomFrontend !== 'undefined') {
            ClassroomFrontend.init();
        }
    });

})(jQuery);
