/**
 * Container Block Designer - Classroom Frontend (Schueler-Ansicht)
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

(function($) {
    'use strict';

    // =============================================
    // localStorage-Persistenz fuer den Klappzustand der Login-Liste (AP-1.3a).
    // Vertrag "localStorage-Schema Klassenmodus" (PLAN-Inhaltsverzeichnisse.md,
    // Abschnitt 4): Schluessel cbd_classroom_toc_collapsed, Wert = JSON-Array
    // der zugeklappten Seiten-IDs als Strings. Dieselbe Datenform wie in
    // classroom-page-filter.js (dort unabhaengig implementiert, AP-1.3b) --
    // beide Dateien laufen nie gleichzeitig auf derselben Seite, deshalb ist
    // eine eigene, private Kopie hier unproblematisch.
    // =============================================

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

    var ClassroomFrontend = {
        classId: null,
        token: null,

        /**
         * Ob der Taktgeber `window.cbdKlassenpuls` fuer diese Sitzung bereits
         * verdrahtet ist (AP-4.1). Verhindert doppelte `abonniere()`-Aufrufe,
         * wenn `loadClassroomData()` innerhalb derselben Sitzung mehrfach
         * durchlaeuft (z. B. `checkExistingAuth()` mit abgelaufenem Token,
         * gefolgt von einem erfolgreichen `autoLogin()`). Wird von
         * `clearAuth()` zurueckgesetzt, damit eine neue Sitzung nach einem
         * Abmelden erneut verdrahtet wird.
         */
        klassenpulsVerdrahtet: false,

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

            // Taktgeber verdrahten (AP-4.1). An dieser Stelle sind
            // this.classId/this.token bereits gesetzt -- loadClassroomData()
            // wird ausschliesslich aus checkExistingAuth() (Wiederaufnahme
            // einer gespeicherten Sitzung), autoLogin() und handleAuth()
            // (beide nach erfolgreichem Login) gerufen, alle drei setzen
            // beide Werte unmittelbar vorher. verdrahteKlassenpuls() ist
            // idempotent, ein mehrfacher Aufruf hier ist deshalb unschaedlich.
            self.verdrahteKlassenpuls();

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

        /**
         * Den Taktgeber `window.cbdKlassenpuls` anzapfen (AP-4.1,
         * `PLAN-Klassenmodus-Live.md`, Phase 4).
         *
         * BESONDERHEIT DIESER SEITE: Der Login laeuft rein per AJAX, ohne
         * Seiten-Neuladen -- die Adresszeile traegt danach KEIN
         * `?classroom=&token=`. Anders als `classroom-page-filter.js`, das
         * beide Werte aus `window.location.search` liest, kennt der
         * Taktgeber sie hier nur, wenn wir sie ihm ausdruecklich per
         * `setzeSitzung()` mitteilen.
         *
         * `setzeSeite()` wird hier BEWUSST NICHT gerufen: Diese Seite zeigt
         * keine Container, die Signaturen 'seite' und 'tafel' sind hier
         * bedeutungslos -- ohne `page_id` spart sich der Server die
         * entsprechende Abfrage.
         *
         * Idempotent ueber `klassenpulsVerdrahtet`, siehe Kommentar dort.
         */
        verdrahteKlassenpuls: function() {
            if (this.klassenpulsVerdrahtet) {
                return;
            }

            if (!window.cbdKlassenpuls) {
                window.cbdDebug && console.log('[CBD Classroom] Kein Taktgeber vorhanden (cbd_klassenpuls_takt = 0?) – keine Live-Aktualisierung.');
                return;
            }

            if (!this.classId || !this.token) {
                return;
            }

            this.klassenpulsVerdrahtet = true;

            var self = this;

            window.cbdKlassenpuls.setzeSitzung(this.classId, this.token);

            window.cbdKlassenpuls.abonniere('klasse', function() {
                self.aktualisiereSeitenliste();
            });

            window.cbdKlassenpuls.abonniere('abgelaufen', function() {
                $('#cbd-classroom-error').text('Die Sitzung ist abgelaufen. Bitte erneut einloggen.').show();
                self.clearAuth();
            });
        },

        /**
         * Regelmaessige Aktualisierung der Kapitelliste, Rueckruf des
         * Abonnements 'klasse' (AP-4.1).
         *
         * Ruft `cbd_student_get_data` mit demselben `$.post`-Aufruf wie
         * `loadClassroomData()` auf, aber BEWUSST NICHT `loadClassroomData()`
         * selbst: Deren Fehlerpfad loest bei `response.success === false`
         * den Wiederanmelde-Pfad aus (`autoLogin()`/`clearAuth()`/
         * Fehlermeldung) -- das ist beim allerersten Aufruf richtig, wuerfe
         * den Schueler hier aber bei einem einzelnen fehlgeschlagenen
         * Nachschlag (z. B. ein kurzer Netzwerk-Aussetzer) grundlos aus der
         * Klasse. Eine tatsaechlich abgelaufene Sitzung meldet stattdessen
         * der `'abgelaufen'`-Rueckruf aus `verdrahteKlassenpuls()` oben --
         * der Puls-Endpunkt liefert bei ungueltigem Token HTTP 404, bevor
         * `cbd_student_get_data` ueberhaupt gefragt wird.
         */
        aktualisiereSeitenliste: function() {
            var self = this;

            $.post(cbdClassroomFrontend.ajaxUrl, {
                action: 'cbd_student_get_data',
                token: this.token
            }, function(response) {
                if (response.success) {
                    self.renderClassroomContent(response.data);
                } else {
                    window.cbdDebug && console.log('[CBD Classroom] Aktualisierung der Seitenliste fehlgeschlagen (kein Rauswurf), naechster Versuch folgt.', response);
                }
            }).fail(function() {
                window.cbdDebug && console.log('[CBD Classroom] Netzwerk-Fehler bei der Aktualisierung der Seitenliste (kein Rauswurf), naechster Versuch folgt.');
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

            // Fragenwand-Einstieg ganz oben (Hotfix „Fragenwand in
            // Klassenlisten"). Diese Seite verwendet den Theme-Block
            // fos/inhaltsverzeichnis NICHT — der PHP-Einhänger
            // CBD_Fragenwand::page_index_eintrag() (AP-4.2) greift hier also
            // nie, und ohne diesen Knopf gäbe es auf der Klassenzugangs-Seite
            // überhaupt keinen Weg zur Fragenwand.
            //
            // Markup bewusst zeichengleich zum PHP-Vorbild: dieselbe
            // Trigger-Klasse cbd-fragenwand-verweis (der delegierte
            // Klick-Listener in assets/js/fragenwand-frontend.js fängt jedes
            // Element damit ab, unabhängig vom Tag-Namen) und dieselben
            // Gestaltungsklassen aus assets/css/fragenwand.css, Abschnitt
            // „INHALTSVERZEICHNIS-EINTRAG".
            //
            // WARUM data-classroom/data-token, anders als im PHP-Vorbild:
            // Der Login läuft hier rein per AJAX, ohne Seiten-Neuladen — die
            // Adresszeile trägt danach KEIN ?classroom=&token=. Klassen-ID und
            // Token stehen nur in diesem Objekt (und im localStorage). Ohne
            // die beiden Attribute schickte fragenwand-frontend.js eine leere
            // Abfragezeichenfolge an cbd/v1/fragenwand, und der Schüler sähe
            // „Keine aktive Klassensitzung." trotz gültiger Sitzung. Die
            // Attribute sind nur ein Transportweg, keine Berechtigung: Der
            // Server prüft das Token unverändert gegen den Transient
            // cbd_classroom_<token>.
            if (this.classId && this.token) {
                $pagesContainer.append(
                    $('<div class="cbd-classroom-fragenwand page-index__zusatz page-index__zusatz--fragenwand">').append(
                        $('<button type="button" class="cbd-fragenwand-verweis page-index__fragenwand-link">')
                            .attr('data-classroom', String(this.classId))
                            .attr('data-token', String(this.token))
                            .text('Fragenwand öffnen')
                    )
                );
            }

            window.cbdDebug && console.log('[CBD Classroom] Rendering content, pages:', data.pages);
            window.cbdDebug && console.log('[CBD Classroom] Pages length:', data.pages ? data.pages.length : 'undefined');

            if (data.pages && data.pages.length > 0) {
                window.cbdDebug && console.log('[CBD Classroom] Rendering', data.pages.length, 'items');

                // Echte, verschachtelte Baumstruktur (AP-1.2).
                // Verfahren adaptiert von injectClassroomSidebar() in
                // classroom-page-filter.js: levelbasierter Stack aus <ul>-Containern.
                // Die Daten kommen flach an (jede Seite mit einem level-Feld) und
                // sind in Dokumentreihenfolge sortiert — ein Knoten gehoert also
                // immer unter den zuletzt gesehenen Knoten der Ebene darueber.
                var $rootUl     = $('<ul class="cbd-classroom-page-tree">');
                var levelUls    = [$rootUl];
                var levelLastLi = [null];

                data.pages.forEach(function(item, index) {
                    window.cbdDebug && console.log('[CBD Classroom] Item', index, ':', item);
                    if (item.type !== 'page' || !item.page) {
                        return;
                    }

                    var page  = item.page;
                    var level = page.level || 0;

                    window.cbdDebug && console.log('[CBD Classroom] Rendering page:', page.title, 'Level:', level);

                    // Stack anpassen, wenn die Ebene sinkt ...
                    if (level < levelUls.length - 1) {
                        levelUls.length    = level + 1;
                        levelLastLi.length = level + 1;
                    }
                    // ... oder steigt: fehlende Kind-Container anlegen.
                    while (levelUls.length <= level) {
                        var $parentLi = levelLastLi[levelUls.length - 1];
                        if (!$parentLi) {
                            break; // keine Elternzeile vorhanden — Knoten bleibt auf der erreichten Ebene
                        }
                        var $sub = $('<ul class="cbd-classroom-children">');
                        $parentLi.append($sub);
                        levelUls.push($sub);
                        levelLastLi.push(null);
                    }

                    var tiefe     = Math.min(level, levelUls.length - 1);
                    var $targetUl = levelUls[tiefe];

                    // Das <li> traegt Struktur und Zustand, die Zeile darin die Karten-Optik.
                    var $pageItem = $('<li>')
                        .addClass('cbd-classroom-page-item')
                        .addClass('cbd-level-' + level);

                    // ACHTUNG: Das ID-Feld der AJAX-Antwort heisst page_id, NICHT id
                    // (am echten Netzwerk-Response verifiziert, siehe AP-1.3b).
                    if (page.page_id !== undefined && page.page_id !== null) {
                        $pageItem.attr('data-page-id', String(page.page_id));
                    }

                    var $pageRow = $('<div class="cbd-classroom-page-row">');

                    if (page.url) {
                        // Page has URL - clickable (treated page)
                        var $pageLink = $('<a>')
                            .attr('href', page.url)
                            .addClass('cbd-classroom-page-link')
                            .text(page.title);

                        var $badge = $('<span>')
                            .addClass('cbd-treated-count-badge')
                            .text(page.treated_count + ' behandelt');

                        $pageRow.append($pageLink).append($badge);
                    } else {
                        // No URL - grayed out parent page
                        var $pageTitle = $('<div class="cbd-classroom-page-grayed">')
                            .text(page.title);

                        // For level 0 parents, make them look like headers
                        if (level === 0) {
                            $pageTitle.addClass('cbd-parent-header-grayed');
                        }

                        $pageRow.append($pageTitle);
                    }

                    $pageItem.append($pageRow);
                    $targetUl.append($pageItem);
                    levelLastLi[tiefe] = $pageItem;
                });

                // Toggle-Knoepfe fuer alle Knoten mit Kindern.
                // Standardzustand: aufgeklappt -- ausser der Knoten wurde vom
                // Nutzer zuvor zugeklappt und das steht im localStorage (AP-1.3a).
                var eingeklappteIds = cbdKlassenverzeichnisGeleseneCollapsedIds();
                $rootUl.find('.cbd-classroom-page-item').each(function() {
                    var $item = $(this);
                    if ($item.children('.cbd-classroom-children').length === 0) {
                        return;
                    }
                    $item.addClass('cbd-classroom-has-children');
                    var istZugeklappt = eingeklappteIds.indexOf(String($item.attr('data-page-id'))) !== -1;
                    if (!istZugeklappt) {
                        $item.addClass('cbd-classroom-expanded');
                    }
                    $item.children('.cbd-classroom-page-row').prepend(
                        $('<button type="button" class="cbd-classroom-toggle" aria-label="Unterseiten anzeigen/verbergen">')
                            .attr('aria-expanded', istZugeklappt ? 'false' : 'true')
                            .append('<span class="cbd-classroom-toggle-icon">▸</span>')
                    );
                });

                $pagesContainer.append($rootUl);

                // Event-Delegation am Container: der ueberlebt das .empty() oben,
                // deshalb vorher denselben Namensraum abmelden — sonst haengen nach
                // einem zweiten Rendern (Auto-Login, Token-Erneuerung) zwei Handler
                // am selben Knopf und heben sich gegenseitig auf.
                $pagesContainer.off('click.cbdClassroomToggle')
                    .on('click.cbdClassroomToggle', '.cbd-classroom-toggle', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var $item = $(this).closest('.cbd-classroom-page-item');
                        $item.toggleClass('cbd-classroom-expanded');
                        var istAufgeklappt = $item.hasClass('cbd-classroom-expanded');
                        $(this).attr('aria-expanded', istAufgeklappt ? 'true' : 'false');

                        // Klappzustand persistieren (AP-1.3a).
                        var pageId = String($item.attr('data-page-id'));
                        var collapsedIds = cbdKlassenverzeichnisGeleseneCollapsedIds();
                        var idx = collapsedIds.indexOf(pageId);
                        if (istAufgeklappt) {
                            if (idx !== -1) {
                                collapsedIds.splice(idx, 1);
                            }
                        } else if (idx === -1) {
                            collapsedIds.push(pageId);
                        }
                        cbdKlassenverzeichnisSchreibeCollapsedIds(collapsedIds);
                    });
            } else {
                $pagesContainer.append('<p class="cbd-no-pages">Keine behandelten Blöcke vorhanden.</p>');
            }
        },

        handleLogout: function() {
            // Taktgeber anhalten (AP-4.1) -- sonst fragt der Browser mit
            // einem verworfenen Token weiter nach. `halte()` existiert nur,
            // wenn `window.cbdKlassenpuls` ueberhaupt eingereiht wurde
            // (cbd_klassenpuls_takt > 0).
            window.cbdKlassenpuls && window.cbdKlassenpuls.halte();

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
            // Sperre gegen doppelte abonniere()-Aufrufe zuruecksetzen
            // (AP-4.1) -- eine folgende neue Sitzung (manueller Re-Login
            // nach "Klasse verlassen") muss den Taktgeber erneut mit
            // setzeSitzung() versorgen koennen.
            this.klassenpulsVerdrahtet = false;
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
