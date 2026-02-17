/**
 * Personal Notes Manager - Global Import/Export für persönliche Notizen
 *
 * Ermöglicht Schülern, alle ihre persönlichen Tafel-Notizen auf einmal
 * zu sichern und auf neuen Geräten wiederherzustellen.
 */

(function() {
    'use strict';

    var PersonalNotesManager = {
        /**
         * Initialisierung
         */
        init: function() {
            this.createFloatingButton();
            this.bindEvents();
        },

        /**
         * Schwebenden Button erstellen
         */
        createFloatingButton: function() {
            var button = document.createElement('div');
            button.className = 'cbd-notes-manager-button';
            button.innerHTML =
                '<button class="cbd-notes-toggle" title="Persönliche Notizen verwalten">' +
                    '<span class="dashicons dashicons-database-export"></span>' +
                '</button>' +
                '<div class="cbd-notes-menu" style="display:none;">' +
                    '<button class="cbd-notes-export">' +
                        '<span class="dashicons dashicons-download"></span>' +
                        'Alle Notizen exportieren' +
                    '</button>' +
                    '<button class="cbd-notes-import">' +
                        '<span class="dashicons dashicons-upload"></span>' +
                        'Notizen importieren' +
                    '</button>' +
                    '<div class="cbd-notes-info"></div>' +
                '</div>';

            document.body.appendChild(button);
        },

        /**
         * Event-Handler binden
         */
        bindEvents: function() {
            var self = this;

            // Toggle-Button
            var toggleBtn = document.querySelector('.cbd-notes-toggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    self.toggleMenu();
                });
            }

            // Export-Button
            var exportBtn = document.querySelector('.cbd-notes-export');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    self.exportAllNotes();
                });
            }

            // Import-Button
            var importBtn = document.querySelector('.cbd-notes-import');
            if (importBtn) {
                importBtn.addEventListener('click', function() {
                    self.importAllNotes();
                });
            }

            // Klick außerhalb schließt Menü
            document.addEventListener('click', function(e) {
                var manager = document.querySelector('.cbd-notes-manager-button');
                if (manager && !manager.contains(e.target)) {
                    var menu = document.querySelector('.cbd-notes-menu');
                    if (menu) {
                        menu.style.display = 'none';
                    }
                }
            });
        },

        /**
         * Menü öffnen/schließen
         */
        toggleMenu: function() {
            var menu = document.querySelector('.cbd-notes-menu');
            if (!menu) return;

            var isVisible = menu.style.display !== 'none';
            menu.style.display = isVisible ? 'none' : 'block';

            // Info aktualisieren
            if (!isVisible) {
                this.updateInfo();
            }
        },

        /**
         * Info über gespeicherte Notizen aktualisieren
         */
        updateInfo: function() {
            var infoDiv = document.querySelector('.cbd-notes-info');
            if (!infoDiv) return;

            var count = 0;
            for (var i = 0; i < localStorage.length; i++) {
                var key = localStorage.key(i);
                if (key && key.startsWith('cbd-board-')) {
                    count++;
                }
            }

            if (count === 0) {
                infoDiv.innerHTML = '<small>Keine Notizen gespeichert</small>';
            } else {
                var text = count === 1 ? '1 Notiz gespeichert' : count + ' Notizen gespeichert';
                infoDiv.innerHTML = '<small>' + text + '</small>';
            }
        },

        /**
         * Alle persönlichen Notizen exportieren
         */
        exportAllNotes: function() {
            try {
                // Alle CBD-Board Daten aus localStorage sammeln
                var exportData = {
                    version: '1.0',
                    exportDate: new Date().toISOString(),
                    notes: {}
                };
                var count = 0;

                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (key && key.startsWith('cbd-board-')) {
                        exportData.notes[key] = localStorage.getItem(key);
                        count++;
                    }
                }

                if (count === 0) {
                    alert('Keine persönlichen Notizen zum Exportieren vorhanden.\n\nÖffnen Sie zuerst den Tafel-Modus und erstellen Sie Zeichnungen.');
                    return;
                }

                // JSON erstellen
                var jsonData = JSON.stringify(exportData, null, 2);
                var blob = new Blob([jsonData], { type: 'application/json' });
                var url = URL.createObjectURL(blob);

                // Download-Link erstellen und klicken
                var link = document.createElement('a');
                link.href = url;
                link.download = 'meine-notizen-' + new Date().toISOString().split('T')[0] + '.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                // Menü schließen
                var menu = document.querySelector('.cbd-notes-menu');
                if (menu) {
                    menu.style.display = 'none';
                }

                // Bestätigung
                var msg = count === 1
                    ? '✓ 1 Notiz wurde exportiert.'
                    : '✓ ' + count + ' Notizen wurden exportiert.';
                alert(msg + '\n\nDie Datei wurde heruntergeladen. Sie können diese auf einem neuen Gerät importieren.');

            } catch (e) {
                console.error('[CBD Notes Manager] Fehler beim Export:', e);
                alert('Fehler beim Exportieren der Notizen: ' + e.message);
            }
        },

        /**
         * Notizen importieren
         */
        importAllNotes: function() {
            var self = this;

            // File Input erstellen
            var input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';

            input.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;

                var reader = new FileReader();
                reader.onload = function(event) {
                    try {
                        var importData = JSON.parse(event.target.result);

                        // Validierung: Format prüfen
                        var notes = importData.notes || importData; // Unterstützt beide Formate
                        var keys = Object.keys(notes);
                        var validKeys = keys.filter(function(k) {
                            return k.startsWith('cbd-board-');
                        });

                        if (validKeys.length === 0) {
                            alert('Die Datei enthält keine gültigen Notizen.\n\nStellen Sie sicher, dass Sie eine exportierte Notizen-Datei verwenden.');
                            return;
                        }

                        // Prüfen, ob bereits Daten vorhanden sind
                        var existingCount = 0;
                        for (var i = 0; i < localStorage.length; i++) {
                            var key = localStorage.key(i);
                            if (key && key.startsWith('cbd-board-')) {
                                existingCount++;
                            }
                        }

                        // Bestätigung
                        var confirmMsg = '';
                        if (existingCount > 0) {
                            confirmMsg = '⚠️ WARNUNG: Es sind bereits ' + existingCount + ' Notiz(en) auf diesem Gerät gespeichert.\n\n' +
                                'Beim Import werden ' + validKeys.length + ' Notiz(en) importiert.\n' +
                                'Bestehende Notizen mit denselben IDs werden ÜBERSCHRIEBEN.\n\n' +
                                'Möchten Sie fortfahren und die vorhandenen Notizen überschreiben?';
                        } else {
                            confirmMsg = validKeys.length + ' Notiz(en) werden importiert.\n\nMöchten Sie fortfahren?';
                        }

                        if (!confirm(confirmMsg)) {
                            return;
                        }

                        // Import durchführen
                        var imported = 0;
                        validKeys.forEach(function(key) {
                            localStorage.setItem(key, notes[key]);
                            imported++;
                        });

                        // Menü schließen
                        var menu = document.querySelector('.cbd-notes-menu');
                        if (menu) {
                            menu.style.display = 'none';
                        }

                        // Bestätigung
                        var successMsg = imported === 1
                            ? '✓ 1 Notiz wurde erfolgreich importiert.'
                            : '✓ ' + imported + ' Notizen wurden erfolgreich importiert.';

                        alert(successMsg + '\n\nIhre Notizen sind jetzt auf diesem Gerät verfügbar.\nÖffnen Sie den Tafel-Modus, um sie zu sehen.');

                        // Info aktualisieren
                        self.updateInfo();

                    } catch (e) {
                        console.error('[CBD Notes Manager] Fehler beim Import:', e);
                        alert('Fehler beim Importieren der Notizen: ' + e.message + '\n\nStellen Sie sicher, dass die Datei eine gültige exportierte Notizen-Datei ist.');
                    }
                };
                reader.readAsText(file);
            });

            // File-Dialog öffnen
            input.click();
        }
    };

    // Initialisierung nach DOM-Load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            PersonalNotesManager.init();
        });
    } else {
        PersonalNotesManager.init();
    }

})();
