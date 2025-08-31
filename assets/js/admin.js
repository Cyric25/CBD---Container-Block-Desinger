/**
 * Container Block Designer - Admin JavaScript
 * Version: 2.5.0
 * 
 * Verwaltet alle Admin-Funktionalitäten
 */

(function($) {
    'use strict';
    
    // Warten bis DOM bereit ist
    $(document).ready(function() {
        
        /**
         * Color Picker initialisieren
         */
        if ($.fn.wpColorPicker) {
            $('.cbd-color-picker').each(function() {
                $(this).wpColorPicker({
                    change: function(event, ui) {
                        // Live-Preview Update
                        updatePreview();
                    },
                    clear: function() {
                        updatePreview();
                    }
                });
            });
        }
        
        /**
         * Block Form Handler
         */
        $('#cbd-block-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            var isNewBlock = !$form.find('input[name="block_id"]').val();
            
            // Button Status ändern
            $button.prop('disabled', true).text(cbdAdmin.strings.saving || 'Speichern...');
            
            // Form-Daten sammeln
            var formData = new FormData(this);
            formData.append('action', 'cbd_save_block');
            
            // Styles sammeln
            var styles = {
                padding: {
                    top: parseInt($('input[name="styles[padding][top]"]').val()) || 20,
                    right: parseInt($('input[name="styles[padding][right]"]').val()) || 20,
                    bottom: parseInt($('input[name="styles[padding][bottom]"]').val()) || 20,
                    left: parseInt($('input[name="styles[padding][left]"]').val()) || 20
                },
                background: {
                    color: $('input[name="styles[background][color]"]').val() || '#ffffff'
                },
                text: {
                    color: $('input[name="styles[text][color]"]').val() || '#333333',
                    alignment: $('select[name="styles[text][alignment]"]').val() || 'left'
                },
                border: {
                    width: parseInt($('input[name="styles[border][width]"]').val()) || 0,
                    style: $('select[name="styles[border][style]"]').val() || 'solid',
                    color: $('input[name="styles[border][color]"]').val() || '#e0e0e0',
                    radius: parseInt($('input[name="styles[border][radius]"]').val()) || 0
                }
            };
            
            // Features sammeln
            var features = {
                icon: {
                    enabled: $('input[name="features[icon][enabled]"]').is(':checked'),
                    value: $('input[name="features[icon][value]"]').val() || 'dashicons-admin-generic'
                },
                collapse: {
                    enabled: $('input[name="features[collapse][enabled]"]').is(':checked'),
                    defaultState: $('select[name="features[collapse][defaultState]"]').val() || 'expanded'
                },
                numbering: {
                    enabled: $('input[name="features[numbering][enabled]"]').is(':checked'),
                    format: $('select[name="features[numbering][format]"]').val() || 'numeric'
                },
                copyText: {
                    enabled: $('input[name="features[copyText][enabled]"]').is(':checked'),
                    buttonText: $('input[name="features[copyText][buttonText]"]').val() || 'Text kopieren'
                }
            };
            
            // Config sammeln
            var config = {
                allowInnerBlocks: $('input[name="config[allowInnerBlocks]"]').is(':checked')
            };
            
            // AJAX-Daten vorbereiten
            var ajaxData = {
                action: 'cbd_save_block',
                nonce: $('#cbd_nonce').val() || formData.get('cbd_nonce'),
                block_id: formData.get('block_id') || 0,
                name: formData.get('name'),
                title: formData.get('title'),
                description: formData.get('description'),
                status: formData.get('status'),
                styles: JSON.stringify(styles),
                features: JSON.stringify(features),
                config: JSON.stringify(config)
            };
            
            // AJAX Request
            $.post(cbdAdmin.ajaxUrl, ajaxData)
                .done(function(response) {
                    if (response.success) {
                        // Erfolg
                        $button.text(cbdAdmin.strings.saved || 'Gespeichert!');
                        
                        // Bei neuem Block zur Bearbeitungsseite weiterleiten
                        if (isNewBlock && response.data.block) {
                            setTimeout(function() {
                                window.location.href = cbdAdmin.adminUrl + 'admin.php?page=cbd-edit-block&id=' + response.data.block.id;
                            }, 1000);
                        } else {
                            // Button nach 2 Sekunden zurücksetzen
                            setTimeout(function() {
                                $button.prop('disabled', false).text(originalText);
                            }, 2000);
                        }
                        
                        // Success Notice anzeigen
                        showNotice(response.data.message || 'Erfolgreich gespeichert', 'success');
                        
                    } else {
                        // Fehler
                        showNotice(response.data.message || cbdAdmin.strings.error, 'error');
                        $button.prop('disabled', false).text(originalText);
                    }
                })
                .fail(function(xhr, status, error) {
                    showNotice('Verbindungsfehler: ' + error, 'error');
                    $button.prop('disabled', false).text(originalText);
                });
        });
        
        /**
         * Block löschen
         */
        $('.cbd-delete-block').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(cbdAdmin.strings.confirmDelete)) {
                return;
            }
            
            var $button = $(this);
            var blockId = $button.data('id');
            var $row = $button.closest('tr');
            
            $button.prop('disabled', true).text('Löschen...');
            
            $.post(cbdAdmin.ajaxUrl, {
                action: 'cbd_delete_block',
                block_id: blockId,
                nonce: cbdAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Zeile ausblenden und entfernen
                    $row.fadeOut(400, function() {
                        $(this).remove();
                        
                        // Prüfen ob noch Blocks vorhanden
                        if ($('.wp-list-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                    
                    showNotice(response.data.message || 'Block gelöscht', 'success');
                } else {
                    showNotice(response.data.message || 'Fehler beim Löschen', 'error');
                    $button.prop('disabled', false).text('Löschen');
                }
            })
            .fail(function() {
                showNotice('Verbindungsfehler', 'error');
                $button.prop('disabled', false).text('Löschen');
            });
        });
        
        /**
         * Name-Feld automatisch formatieren
         */
        $('#block-name').on('input', function() {
            var value = $(this).val();
            var cleaned = value.toLowerCase()
                .replace(/[^a-z0-9-]/g, '-')
                .replace(/--+/g, '-')
                .replace(/^-+|-+$/g, '');
            
            if (value !== cleaned) {
                $(this).val(cleaned);
            }
        });
        
        /**
         * Feature-Toggles
         */
        $('input[type="checkbox"][name*="features"]').on('change', function() {
            var $checkbox = $(this);
            var $container = $checkbox.closest('tr').find('input[type="text"], select').not($checkbox);
            
            if ($checkbox.is(':checked')) {
                $container.prop('disabled', false).removeClass('disabled');
            } else {
                $container.prop('disabled', true).addClass('disabled');
            }
        }).trigger('change');
        
        /**
         * Live-Preview
         */
        function updatePreview() {
            var $preview = $('#cbd-block-preview');
            if ($preview.length === 0) {
                // Preview-Container erstellen
                var $sidebar = $('.cbd-sidebar');
                if ($sidebar.length) {
                    $sidebar.append(
                        '<div class="cbd-card">' +
                        '<h3>Live-Vorschau</h3>' +
                        '<div id="cbd-block-preview" class="cbd-preview-container">' +
                        '<div class="cbd-preview-content">Beispiel-Inhalt</div>' +
                        '</div>' +
                        '</div>'
                    );
                    $preview = $('#cbd-block-preview');
                }
            }
            
            if ($preview.length) {
                // Styles anwenden
                var styles = {
                    paddingTop: $('input[name="styles[padding][top]"]').val() + 'px',
                    paddingRight: $('input[name="styles[padding][right]"]').val() + 'px',
                    paddingBottom: $('input[name="styles[padding][bottom]"]').val() + 'px',
                    paddingLeft: $('input[name="styles[padding][left]"]').val() + 'px',
                    backgroundColor: $('input[name="styles[background][color]"]').val(),
                    color: $('input[name="styles[text][color]"]').val(),
                    textAlign: $('select[name="styles[text][alignment]"]').val(),
                    borderWidth: $('input[name="styles[border][width]"]').val() + 'px',
                    borderStyle: $('select[name="styles[border][style]"]').val(),
                    borderColor: $('input[name="styles[border][color]"]').val(),
                    borderRadius: $('input[name="styles[border][radius]"]').val() + 'px'
                };
                
                $preview.css(styles);
            }
        }
        
        // Preview bei Änderungen aktualisieren
        $('input[name*="styles"], select[name*="styles"]').on('change input', updatePreview);
        
        // Initial Preview
        updatePreview();
        
        /**
         * Tabs-Navigation (falls vorhanden)
         */
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.attr('href');
            
            // Tabs wechseln
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Inhalt wechseln
            $('.tab-content').hide();
            $(target).show();
        });
        
        /**
         * Dashicon-Picker
         */
        $('#dashicon-picker-button').on('click', function(e) {
            e.preventDefault();
            // Hier könnte ein Dashicon-Picker Modal geöffnet werden
            alert('Dashicon-Picker wird in einer zukünftigen Version hinzugefügt.\nBesuchen Sie: https://developer.wordpress.org/resource/dashicons/');
        });
        
        /**
         * Import/Export Handler
         */
        $('#cbd-export-blocks').on('click', function(e) {
            e.preventDefault();
            window.location.href = cbdAdmin.ajaxUrl + '?action=cbd_export_blocks&nonce=' + cbdAdmin.nonce;
        });
        
        $('#cbd-import-file').on('change', function() {
            var file = this.files[0];
            if (file) {
                $('#cbd-import-button').prop('disabled', false);
            } else {
                $('#cbd-import-button').prop('disabled', true);
            }
        });
        
        $('#cbd-import-button').on('click', function(e) {
            e.preventDefault();
            
            var fileInput = $('#cbd-import-file')[0];
            var file = fileInput.files[0];
            
            if (!file) {
                showNotice('Bitte wählen Sie eine Datei aus', 'error');
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var data = JSON.parse(e.target.result);
                    
                    $.post(cbdAdmin.ajaxUrl, {
                        action: 'cbd_import_blocks',
                        nonce: cbdAdmin.nonce,
                        data: JSON.stringify(data)
                    })
                    .done(function(response) {
                        if (response.success) {
                            showNotice('Blocks erfolgreich importiert!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            showNotice(response.data.message || 'Import fehlgeschlagen', 'error');
                        }
                    })
                    .fail(function() {
                        showNotice('Verbindungsfehler beim Import', 'error');
                    });
                    
                } catch(err) {
                    showNotice('Ungültige JSON-Datei: ' + err.message, 'error');
                }
            };
            
            reader.readAsText(file);
        });
        
        /**
         * Notice-Helper
         */
        function showNotice(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Diese Meldung ausblenden</span>' +
                '</button>' +
                '</div>');
            
            // Notice nach .wp-header-end einfügen
            $('.wp-header-end').after($notice);
            
            // Dismiss-Handler
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss nach 5 Sekunden für Erfolg
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
        
        /**
         * Responsive Sidebar Toggle
         */
        $('#cbd-toggle-sidebar').on('click', function() {
            $('.cbd-sidebar').toggleClass('cbd-sidebar-open');
        });
        
        /**
         * Keyboard Shortcuts
         */
        $(document).on('keydown', function(e) {
            // Strg/Cmd + S zum Speichern
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                $('#cbd-block-form').submit();
            }
        });
        
    });
    
})(jQuery);