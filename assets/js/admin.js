/**
 * Container Block Designer - Admin JavaScript
 * Handles all admin-side functionality including block management, settings, and AJAX operations
 */

(function($) {
    'use strict';
    
    // Wait for document ready
    $(document).ready(function() {
        
        // Global CBD Admin object
        window.CBDAdmin = {
            
            // Initialize admin functionality
            init: function() {
                console.log('CBD Admin: Initializing');
                
                this.bindEvents();
                this.initColorPickers();
                this.initSortable();
                this.initTooltips();
                this.initCodeEditor();
                this.validateForms();
                
                // Check for messages in URL
                this.handleUrlMessages();
            },
            
            // Bind all event handlers
            bindEvents: function() {
                // Delete block confirmation
                $('.cbd-delete-block').on('click', this.confirmDelete);
                
                // Duplicate block
                $('.cbd-duplicate-block').on('click', this.duplicateBlock);
                
                // Export block
                $('.cbd-export-block').on('click', this.exportBlock);
                
                // Import block
                $('#cbd-import-form').on('submit', this.importBlock);
                
                // Feature toggles
                $('.cbd-feature-toggle').on('change', this.toggleFeature);
                
                // Style preview update
                $('.cbd-style-input').on('input change', this.updatePreview);
                
                // Save settings via AJAX
                $('.cbd-ajax-save').on('click', this.saveViaAjax);
                
                // Reset to defaults
                $('.cbd-reset-defaults').on('click', this.resetToDefaults);
                
                // Tab navigation
                $('.cbd-tab-nav a').on('click', this.switchTab);
                
                // Search/Filter blocks
                $('#cbd-block-search').on('input', this.filterBlocks);
                
                // Bulk actions
                $('#cbd-bulk-action-submit').on('click', this.executeBulkAction);
            },
            
            // Initialize color pickers
            initColorPickers: function() {
                if ($.fn.wpColorPicker) {
                    $('.cbd-color-picker').each(function() {
                        $(this).wpColorPicker({
                            change: function(event, ui) {
                                $(event.target).trigger('change');
                                CBDAdmin.updatePreview();
                            },
                            clear: function() {
                                CBDAdmin.updatePreview();
                            }
                        });
                    });
                }
            },
            
            // Initialize sortable for blocks list
            initSortable: function() {
                if ($.fn.sortable) {
                    $('#cbd-blocks-list tbody').sortable({
                        handle: '.cbd-drag-handle',
                        placeholder: 'cbd-sortable-placeholder',
                        update: function(event, ui) {
                            CBDAdmin.updateBlockOrder();
                        }
                    });
                }
            },
            
            // Initialize tooltips
            initTooltips: function() {
                if ($.fn.tooltip) {
                    $('.cbd-tooltip').tooltip({
                        position: {
                            my: 'center bottom-10',
                            at: 'center top'
                        }
                    });
                }
            },
            
            // Initialize code editor for custom CSS
            initCodeEditor: function() {
                if (window.wp && window.wp.codeEditor) {
                    $('.cbd-code-editor').each(function() {
                        const settings = {
                            codemirror: {
                                mode: $(this).data('mode') || 'css',
                                lineNumbers: true,
                                lineWrapping: true,
                                indentUnit: 2,
                                tabSize: 2
                            }
                        };
                        
                        const editor = wp.codeEditor.initialize(this, settings);
                        $(this).data('code-editor', editor);
                        
                        // Update preview on change
                        editor.codemirror.on('change', function() {
                            CBDAdmin.updatePreview();
                        });
                    });
                }
            },
            
            // Validate forms before submission
            validateForms: function() {
                $('form.cbd-validate').on('submit', function(e) {
                    const form = $(this);
                    let isValid = true;
                    
                    // Check required fields
                    form.find('[required]').each(function() {
                        const field = $(this);
                        if (!field.val() || field.val().trim() === '') {
                            field.addClass('cbd-error');
                            isValid = false;
                        } else {
                            field.removeClass('cbd-error');
                        }
                    });
                    
                    // Check slug format
                    form.find('.cbd-slug-field').each(function() {
                        const field = $(this);
                        const value = field.val();
                        if (value && !/^[a-z0-9-]+$/.test(value)) {
                            field.addClass('cbd-error');
                            CBDAdmin.showNotice('Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten', 'error');
                            isValid = false;
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        CBDAdmin.showNotice('Bitte füllen Sie alle Pflichtfelder korrekt aus', 'error');
                    }
                });
            },
            
            // Handle URL messages
            handleUrlMessages: function() {
                const urlParams = new URLSearchParams(window.location.search);
                const message = urlParams.get('message');
                
                if (message) {
                    const messages = {
                        'block-saved': 'Block erfolgreich gespeichert',
                        'block-deleted': 'Block erfolgreich gelöscht',
                        'block-duplicated': 'Block erfolgreich dupliziert',
                        'settings-saved': 'Einstellungen erfolgreich gespeichert'
                    };
                    
                    if (messages[message]) {
                        this.showNotice(messages[message], 'success');
                    }
                }
            },
            
            // Confirm delete action
            confirmDelete: function(e) {
                e.preventDefault();
                const link = $(this);
                const blockName = link.data('block-name') || 'diesen Block';
                
                if (confirm(`Möchten Sie ${blockName} wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`)) {
                    window.location.href = link.attr('href');
                }
            },
            
            // Duplicate block via AJAX
            duplicateBlock: function(e) {
                e.preventDefault();
                const button = $(this);
                const blockId = button.data('block-id');
                
                if (!blockId) return;
                
                button.prop('disabled', true);
                
                $.ajax({
                    url: cbdAdminData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cbd_duplicate_block',
                        block_id: blockId,
                        nonce: cbdAdminData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            CBDAdmin.showNotice('Block erfolgreich dupliziert', 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            CBDAdmin.showNotice(response.data || 'Fehler beim Duplizieren', 'error');
                            button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        CBDAdmin.showNotice('Netzwerkfehler beim Duplizieren', 'error');
                        button.prop('disabled', false);
                    }
                });
            },
            
            // Export block as JSON
            exportBlock: function(e) {
                e.preventDefault();
                const button = $(this);
                const blockId = button.data('block-id');
                
                if (!blockId) return;
                
                $.ajax({
                    url: cbdAdminData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cbd_export_block',
                        block_id: blockId,
                        nonce: cbdAdminData.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // Create download link
                            const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `cbd-block-${response.data.slug || blockId}.json`;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                            
                            CBDAdmin.showNotice('Block erfolgreich exportiert', 'success');
                        } else {
                            CBDAdmin.showNotice('Fehler beim Exportieren', 'error');
                        }
                    },
                    error: function() {
                        CBDAdmin.showNotice('Netzwerkfehler beim Exportieren', 'error');
                    }
                });
            },
            
            // Import block from JSON
            importBlock: function(e) {
                e.preventDefault();
                const form = $(this);
                const fileInput = form.find('input[type="file"]')[0];
                
                if (!fileInput || !fileInput.files.length) {
                    CBDAdmin.showNotice('Bitte wählen Sie eine Datei aus', 'error');
                    return;
                }
                
                const file = fileInput.files[0];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    try {
                        const blockData = JSON.parse(event.target.result);
                        
                        $.ajax({
                            url: cbdAdminData.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'cbd_import_block',
                                block_data: blockData,
                                nonce: cbdAdminData.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    CBDAdmin.showNotice('Block erfolgreich importiert', 'success');
                                    setTimeout(() => window.location.reload(), 1500);
                                } else {
                                    CBDAdmin.showNotice(response.data || 'Fehler beim Importieren', 'error');
                                }
                            },
                            error: function() {
                                CBDAdmin.showNotice('Netzwerkfehler beim Importieren', 'error');
                            }
                        });
                    } catch (error) {
                        CBDAdmin.showNotice('Ungültige JSON-Datei', 'error');
                    }
                };
                
                reader.readAsText(file);
            },
            
            // Toggle feature on/off
            toggleFeature: function() {
                const checkbox = $(this);
                const featureContainer = checkbox.closest('.cbd-feature-container');
                const featureSettings = featureContainer.find('.cbd-feature-settings');
                
                if (checkbox.is(':checked')) {
                    featureSettings.slideDown(200);
                } else {
                    featureSettings.slideUp(200);
                }
                
                CBDAdmin.updatePreview();
            },
            
            // Update live preview
            updatePreview: function() {
                const preview = $('#cbd-block-preview');
                if (!preview.length) return;
                
                // Collect all style values
                const styles = {
                    padding: {
                        top: $('#padding-top').val() || 20,
                        right: $('#padding-right').val() || 20,
                        bottom: $('#padding-bottom').val() || 20,
                        left: $('#padding-left').val() || 20
                    },
                    margin: {
                        top: $('#margin-top').val() || 0,
                        right: $('#margin-right').val() || 0,
                        bottom: $('#margin-bottom').val() || 0,
                        left: $('#margin-left').val() || 0
                    },
                    background: {
                        color: $('#background-color').val() || '#ffffff'
                    },
                    text: {
                        color: $('#text-color').val() || '#333333',
                        alignment: $('#text-alignment').val() || 'left'
                    },
                    border: {
                        width: $('#border-width').val() || 0,
                        style: $('#border-style').val() || 'solid',
                        color: $('#border-color').val() || '#dddddd',
                        radius: $('#border-radius').val() || 0
                    }
                };
                
                // Apply styles to preview
                preview.css({
                    'padding': `${styles.padding.top}px ${styles.padding.right}px ${styles.padding.bottom}px ${styles.padding.left}px`,
                    'margin': `${styles.margin.top}px ${styles.margin.right}px ${styles.margin.bottom}px ${styles.margin.left}px`,
                    'background-color': styles.background.color,
                    'color': styles.text.color,
                    'text-align': styles.text.alignment,
                    'border': styles.border.width > 0 ? `${styles.border.width}px ${styles.border.style} ${styles.border.color}` : 'none',
                    'border-radius': `${styles.border.radius}px`
                });
                
                // Update custom CSS
                const customCSS = $('.cbd-code-editor').val();
                if (customCSS) {
                    $('#cbd-custom-preview-styles').remove();
                    $('<style>')
                        .attr('id', 'cbd-custom-preview-styles')
                        .text(customCSS.replace(/\.cbd-container/g, '#cbd-block-preview'))
                        .appendTo('head');
                }
            },
            
            // Save settings via AJAX
            saveViaAjax: function(e) {
                e.preventDefault();
                const button = $(this);
                const form = button.closest('form');
                
                button.prop('disabled', true);
                
                const formData = new FormData(form[0]);
                formData.append('action', 'cbd_save_settings');
                formData.append('nonce', cbdAdminData.nonce);
                
                $.ajax({
                    url: cbdAdminData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            CBDAdmin.showNotice('Einstellungen erfolgreich gespeichert', 'success');
                        } else {
                            CBDAdmin.showNotice(response.data || 'Fehler beim Speichern', 'error');
                        }
                        button.prop('disabled', false);
                    },
                    error: function() {
                        CBDAdmin.showNotice('Netzwerkfehler beim Speichern', 'error');
                        button.prop('disabled', false);
                    }
                });
            },
            
            // Reset to default values
            resetToDefaults: function(e) {
                e.preventDefault();
                
                if (!confirm('Möchten Sie wirklich alle Einstellungen auf die Standardwerte zurücksetzen?')) {
                    return;
                }
                
                // Reset form fields
                $('#padding-top, #padding-right, #padding-bottom, #padding-left').val(20);
                $('#margin-top, #margin-right, #margin-bottom, #margin-left').val(0);
                $('#background-color').val('#ffffff').trigger('change');
                $('#text-color').val('#333333').trigger('change');
                $('#text-alignment').val('left');
                $('#border-width').val(0);
                $('#border-style').val('solid');
                $('#border-color').val('#dddddd').trigger('change');
                $('#border-radius').val(0);
                
                // Clear custom CSS
                const editor = $('.cbd-code-editor').data('code-editor');
                if (editor) {
                    editor.codemirror.setValue('');
                }
                
                CBDAdmin.updatePreview();
                CBDAdmin.showNotice('Einstellungen wurden zurückgesetzt', 'info');
            },
            
            // Switch tabs
            switchTab: function(e) {
                e.preventDefault();
                const link = $(this);
                const target = link.attr('href');
                
                // Update active states
                link.closest('.cbd-tab-nav').find('a').removeClass('active');
                link.addClass('active');
                
                // Show target panel
                $('.cbd-tab-panel').removeClass('active');
                $(target).addClass('active');
                
                // Save active tab
                if (window.localStorage) {
                    localStorage.setItem('cbd-active-tab', target);
                }
            },
            
            // Filter blocks in list
            filterBlocks: function() {
                const searchTerm = $(this).val().toLowerCase();
                const rows = $('#cbd-blocks-list tbody tr');
                
                rows.each(function() {
                    const row = $(this);
                    const text = row.text().toLowerCase();
                    
                    if (text.includes(searchTerm)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
                
                // Update results count
                const visibleCount = rows.filter(':visible').length;
                $('#cbd-results-count').text(`${visibleCount} von ${rows.length} Blocks`);
            },
            
            // Execute bulk action
            executeBulkAction: function(e) {
                e.preventDefault();
                const action = $('#cbd-bulk-action').val();
                const checkedItems = $('.cbd-bulk-checkbox:checked');
                
                if (!action) {
                    CBDAdmin.showNotice('Bitte wählen Sie eine Aktion aus', 'error');
                    return;
                }
                
                if (checkedItems.length === 0) {
                    CBDAdmin.showNotice('Bitte wählen Sie mindestens einen Block aus', 'error');
                    return;
                }
                
                const blockIds = [];
                checkedItems.each(function() {
                    blockIds.push($(this).val());
                });
                
                if (action === 'delete' && !confirm(`Möchten Sie wirklich ${blockIds.length} Block(s) löschen?`)) {
                    return;
                }
                
                $.ajax({
                    url: cbdAdminData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cbd_bulk_action',
                        bulk_action: action,
                        block_ids: blockIds,
                        nonce: cbdAdminData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            CBDAdmin.showNotice(`Aktion erfolgreich für ${blockIds.length} Block(s) ausgeführt`, 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            CBDAdmin.showNotice(response.data || 'Fehler bei der Ausführung', 'error');
                        }
                    },
                    error: function() {
                        CBDAdmin.showNotice('Netzwerkfehler bei der Ausführung', 'error');
                    }
                });
            },
            
            // Update block order after sorting
            updateBlockOrder: function() {
                const order = [];
                $('#cbd-blocks-list tbody tr').each(function(index) {
                    const blockId = $(this).data('block-id');
                    if (blockId) {
                        order.push({ id: blockId, order: index });
                    }
                });
                
                $.ajax({
                    url: cbdAdminData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cbd_update_block_order',
                        order: order,
                        nonce: cbdAdminData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            CBDAdmin.showNotice('Reihenfolge aktualisiert', 'success');
                        }
                    }
                });
            },
            
            // Show notice message
            showNotice: function(message, type = 'info') {
                const notice = $('<div>')
                    .addClass(`notice notice-${type} is-dismissible cbd-notice`)
                    .html(`<p>${message}</p>`);
                
                // Add dismiss button
                const dismissButton = $('<button>')
                    .attr('type', 'button')
                    .addClass('notice-dismiss')
                    .on('click', function() {
                        notice.fadeOut(300, function() {
                            $(this).remove();
                        });
                    });
                
                notice.append(dismissButton);
                
                // Insert after page title or at top of content
                const target = $('.wrap h1').first();
                if (target.length) {
                    notice.insertAfter(target);
                } else {
                    notice.prependTo('.wrap');
                }
                
                // Auto-dismiss after 5 seconds for success messages
                if (type === 'success') {
                    setTimeout(() => {
                        notice.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 5000);
                }
                
                // Scroll to notice
                $('html, body').animate({
                    scrollTop: notice.offset().top - 50
                }, 300);
            }
        };
        
        // Initialize CBD Admin
        CBDAdmin.init();
        
        // Make available globally
        window.CBDAdmin = CBDAdmin;
    });
    
})(jQuery);