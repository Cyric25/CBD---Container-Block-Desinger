/**
 * Container Block Designer - Admin JavaScript
 * Version: 2.2.0
 */

(function($) {
    'use strict';
    
    // Debug mode
    const DEBUG = true;
    
    /**
     * Container Block Designer Admin
     */
    window.CBDAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            console.log('🚀 Initializing CBD Admin...');
            
            // Check dependencies
            if (typeof $ === 'undefined') {
                console.error('❌ jQuery not found!');
                return;
            }
            
            // Setup AJAX defaults
            if (typeof ajaxurl === 'undefined' && typeof cbdAdmin !== 'undefined') {
                window.ajaxurl = cbdAdmin.ajaxUrl;
            }
            
            // Initialize cbdAdmin if not exists
            if (typeof cbdAdmin === 'undefined') {
                window.cbdAdmin = {
                    ajaxUrl: ajaxurl || '/wp-admin/admin-ajax.php',
                    nonce: $('#cbd-nonce').val() || '',
                    strings: {
                        confirmDelete: 'Wirklich löschen?',
                        confirmDuplicate: 'Block duplizieren?',
                        saving: 'Speichern...',
                        saved: 'Gespeichert!',
                        error: 'Ein Fehler ist aufgetreten.',
                        active: 'Aktiv',
                        inactive: 'Inaktiv',
                        activate: 'Aktivieren',
                        deactivate: 'Deaktivieren'
                    },
                    blocksListUrl: window.location.origin + '/wp-admin/admin.php?page=container-block-designer'
                };
            }
            
            this.bindEvents();
            this.initColorPickers();
            this.initPreview();
            this.initBulkActions();
            this.initSearch();
            
            // Initialize tooltips only if available
            this.initTooltips();
            
            console.log('✅ CBD Admin initialized successfully');
        },

        /**
         * Bind all events
         */
        bindEvents: function() {
            const self = this;
            if (DEBUG) console.log('📌 Binding event handlers...');
            
            // SAVE BUTTON
            $(document).on('click', '#cbd-save-block, button[type="submit"][name="cbd-save-block"]', function(e) {
                e.preventDefault();
                if (DEBUG) console.log('💾 Save button clicked');
                self.saveBlock();
            });
            
            // Form submit
            $(document).on('submit', '#cbd-block-form, form.cbd-block-form', function(e) {
                e.preventDefault();
                if (DEBUG) console.log('📝 Form submitted');
                self.saveBlock();
            });
            
            // DELETE
            $(document).on('click', '.cbd-delete-btn, a[data-action="delete"]', function(e) {
                e.preventDefault();
                const blockId = $(this).data('id') || $(this).data('block-id');
                if (DEBUG) console.log('🗑️ Delete button clicked for block:', blockId);
                if (blockId) {
                    self.deleteBlock(blockId);
                }
            });
            
            // TOGGLE STATUS
            $(document).on('click', '.cbd-toggle-status-btn, a[data-action="toggle-status"]', function(e) {
                e.preventDefault();
                const blockId = $(this).data('id') || $(this).data('block-id');
                if (DEBUG) console.log('🔄 Toggle status clicked for block:', blockId);
                if (blockId) {
                    self.toggleStatus(blockId);
                }
            });
            
            // DUPLICATE
            $(document).on('click', '.cbd-duplicate-btn, a[data-action="duplicate"]', function(e) {
                e.preventDefault();
                const blockId = $(this).data('id') || $(this).data('block-id');
                if (DEBUG) console.log('📋 Duplicate clicked for block:', blockId);
                if (blockId) {
                    self.duplicateBlock(blockId);
                }
            });
            
            // EDIT - direkte Navigation
            $(document).on('click', '.cbd-edit-btn, a[data-action="edit"]', function(e) {
                if (DEBUG) console.log('✏️ Edit button clicked');
                // Let the normal link work
            });
            
            // Features modal - wird von admin-features.js behandelt
            $(document).on('click', '.cbd-configure-features, .cbd-features-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                let blockId = $(this).data('id') || $(this).data('block-id') || 
                             $(this).closest('tr').data('block-id') || 
                             $('#block-id').val();
                
                const blockName = $(this).data('name') || $('#block-name').val() || 'Block';
                
                if (DEBUG) console.log('⚙️ Opening features modal for block:', {id: blockId, name: blockName});
                
                if (blockId && blockId !== '0') {
                    self.openFeaturesModal(blockId, blockName);
                } else {
                    self.showMessage('⚠️ Bitte speichern Sie zuerst den Block, bevor Sie Features konfigurieren.', 'warning');
                }
            });
            
            // Dismiss notices
            $(document).on('click', '.cbd-notice .notice-dismiss', function() {
                $(this).closest('.cbd-notice').fadeOut();
            });
            
            if (DEBUG) console.log('✅ Event handlers bound');
        },

        /**
         * Initialize tooltips - with fallback
         */
        initTooltips: function() {
            // Check if jQuery UI tooltip is available
            if ($.fn.tooltip) {
                try {
                    $('.cbd-tooltip').tooltip({
                        position: {
                            my: "center bottom-10",
                            at: "center top",
                            using: function(position, feedback) {
                                $(this).css(position);
                                $("<div>")
                                    .addClass("arrow")
                                    .addClass(feedback.vertical)
                                    .addClass(feedback.horizontal)
                                    .appendTo(this);
                            }
                        }
                    });
                    if (DEBUG) console.log('✅ Tooltips initialized');
                } catch (e) {
                    if (DEBUG) console.log('⚠️ Tooltip initialization failed:', e);
                }
            } else {
                // Fallback: Use title attribute
                if (DEBUG) console.log('ℹ️ jQuery UI Tooltip not available, using native tooltips');
                $('.cbd-tooltip').each(function() {
                    const $this = $(this);
                    if (!$this.attr('title') && $this.data('tooltip')) {
                        $this.attr('title', $this.data('tooltip'));
                    }
                });
            }
        },

        /**
         * Save block
         */
        saveBlock: function() {
            const self = this;
            if (DEBUG) console.log('💾 Saving block...');
            
            // Get form data
            const formData = {
                action: 'cbd_save_block',
                nonce: $('#cbd-nonce').val() || cbdAdmin.nonce,
                block_id: $('#block-id').val() || '0',
                block_name: $('#block-name').val(),
                block_slug: $('#block-slug').val(),
                block_description: $('#block-description').val(),
                block_category: $('#block-category').val(),
                block_icon: $('#block-icon').val(),
                block_keywords: $('#block-keywords').val(),
                block_status: $('#block-status').is(':checked') ? 1 : 0,
                
                // Style settings
                background_color: $('#background-color').val(),
                text_color: $('#text-color').val(),
                border_style: $('#border-style').val(),
                border_width: $('#border-width').val(),
                border_color: $('#border-color').val(),
                border_radius: $('#border-radius').val(),
                padding: $('#padding').val(),
                margin: $('#margin').val(),
                custom_css: $('#custom-css').val()
            };
            
            if (DEBUG) console.log('Form data:', formData);
            
            // Validate required fields
            if (!formData.block_name) {
                this.showMessage('❌ Bitte geben Sie einen Block-Namen ein.', 'error');
                $('#block-name').focus();
                return;
            }
            
            // Show saving state
            const $saveBtn = $('#cbd-save-block');
            const originalText = $saveBtn.text();
            $saveBtn.text(cbdAdmin.strings.saving).prop('disabled', true);
            
            // AJAX save
            $.ajax({
                url: cbdAdmin.ajaxUrl || ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (DEBUG) console.log('Save response:', response);
                    
                    if (response.success) {
                        // Update block ID if new block
                        if (response.data && response.data.block_id) {
                            $('#block-id').val(response.data.block_id);
                            
                            // Update URL if new block
                            if (formData.block_id === '0') {
                                const newUrl = cbdAdmin.blocksListUrl + '&action=edit&id=' + response.data.block_id;
                                window.history.replaceState({}, '', newUrl);
                            }
                        }
                        
                        $saveBtn.text(cbdAdmin.strings.saved);
                        self.showMessage('✅ ' + (response.data.message || 'Block erfolgreich gespeichert!'), 'success');
                        
                        // Reset button after delay
                        setTimeout(function() {
                            $saveBtn.text(originalText).prop('disabled', false);
                        }, 2000);
                    } else {
                        $saveBtn.text(originalText).prop('disabled', false);
                        self.showMessage('❌ ' + (response.data || cbdAdmin.strings.error), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Save error:', error);
                    $saveBtn.text(originalText).prop('disabled', false);
                    self.showMessage('❌ ' + cbdAdmin.strings.error, 'error');
                }
            });
        },

        /**
         * Delete block
         */
        deleteBlock: function(blockId) {
            if (!confirm(cbdAdmin.strings.confirmDelete)) {
                return;
            }
            
            const self = this;
            
            $.ajax({
                url: cbdAdmin.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_delete_block',
                    block_id: blockId,
                    nonce: cbdAdmin.nonce || $('#cbd-nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        // Remove row from table
                        $('tr[data-block-id="' + blockId + '"]').fadeOut(400, function() {
                            $(this).remove();
                        });
                        self.showMessage('✅ Block erfolgreich gelöscht!', 'success');
                    } else {
                        self.showMessage('❌ ' + (response.data || 'Fehler beim Löschen'), 'error');
                    }
                },
                error: function() {
                    self.showMessage('❌ Fehler beim Löschen', 'error');
                }
            });
        },

        /**
         * Toggle block status
         */
        toggleStatus: function(blockId) {
            const self = this;
            const $row = $('tr[data-block-id="' + blockId + '"]');
            const $statusBadge = $row.find('.cbd-status-badge');
            const currentStatus = $statusBadge.hasClass('cbd-status-active') ? 1 : 0;
            const newStatus = currentStatus ? 0 : 1;
            
            $.ajax({
                url: cbdAdmin.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_toggle_status',
                    block_id: blockId,
                    status: newStatus,
                    nonce: cbdAdmin.nonce || $('#cbd-nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        if (newStatus) {
                            $statusBadge
                                .removeClass('cbd-status-inactive')
                                .addClass('cbd-status-active')
                                .text(cbdAdmin.strings.active);
                        } else {
                            $statusBadge
                                .removeClass('cbd-status-active')
                                .addClass('cbd-status-inactive')
                                .text(cbdAdmin.strings.inactive);
                        }
                        self.showMessage('✅ Status erfolgreich geändert!', 'success');
                    } else {
                        self.showMessage('❌ Fehler beim Ändern des Status', 'error');
                    }
                },
                error: function() {
                    self.showMessage('❌ Fehler beim Ändern des Status', 'error');
                }
            });
        },

        /**
         * Duplicate block
         */
        duplicateBlock: function(blockId) {
            if (!confirm(cbdAdmin.strings.confirmDuplicate || 'Block duplizieren?')) {
                return;
            }
            
            const self = this;
            
            $.ajax({
                url: cbdAdmin.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_duplicate_block',
                    block_id: blockId,
                    nonce: cbdAdmin.nonce || $('#cbd-nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage('✅ Block erfolgreich dupliziert! Seite wird neu geladen...', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        self.showMessage('❌ ' + (response.data || 'Fehler beim Duplizieren'), 'error');
                    }
                },
                error: function() {
                    self.showMessage('❌ Fehler beim Duplizieren', 'error');
                }
            });
        },

        /**
         * Open features modal
         */
        openFeaturesModal: function(blockId, blockName) {
            if (DEBUG) console.log('Opening features modal:', {blockId, blockName});
            
            // Trigger the features modal from admin-features.js
            if (typeof CBDFeatures !== 'undefined' && CBDFeatures.openFeaturesModal) {
                CBDFeatures.openFeaturesModal(blockId, blockName);
            } else {
                console.warn('⚠️ CBDFeatures not available');
                this.showMessage('⚠️ Features-Modul nicht geladen', 'warning');
            }
        },

        /**
         * Show message
         */
        showMessage: function(message, type = 'info') {
            // Remove existing messages
            $('.cbd-notice').remove();
            
            const $notice = $('<div class="cbd-notice notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss</span>' +
                '</button>' +
                '</div>');
            
            // Try different container selectors
            const $container = $('.cbd-admin-content, .wrap').first();
            if ($container.length) {
                $container.prepend($notice);
            } else {
                $('body').prepend($notice);
            }
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(400, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.cbd-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        $(event.target).trigger('input');
                        CBDAdmin.updatePreview();
                    },
                    clear: function() {
                        CBDAdmin.updatePreview();
                    }
                });
                if (DEBUG) console.log('✅ Color pickers initialized');
            } else {
                if (DEBUG) console.log('⚠️ wpColorPicker not available');
            }
        },

        /**
         * Initialize preview
         */
        initPreview: function() {
            const self = this;
            
            // Listen for changes
            $('#cbd-block-form input, #cbd-block-form select, #cbd-block-form textarea').on('input change', function() {
                self.updatePreview();
            });
            
            // Initial preview
            this.updatePreview();
        },

        /**
         * Update preview
         */
        updatePreview: function() {
            const $preview = $('#cbd-preview-content');
            if (!$preview.length) return;
            
            // Get current values
            const styles = {
                backgroundColor: $('#background-color').val(),
                color: $('#text-color').val(),
                borderStyle: $('#border-style').val(),
                borderWidth: $('#border-width').val() + 'px',
                borderColor: $('#border-color').val(),
                borderRadius: $('#border-radius').val() + 'px',
                padding: $('#padding').val() + 'px',
                margin: $('#margin').val() + 'px'
            };
            
            // Apply styles
            $preview.css(styles);
            
            // Apply custom CSS
            const customCSS = $('#custom-css').val();
            if (customCSS) {
                $('#cbd-preview-custom-style').remove();
                $('<style id="cbd-preview-custom-style">' + 
                  '#cbd-preview-content { ' + customCSS + ' }' +
                  '</style>').appendTo('head');
            }
        },

        /**
         * Initialize bulk actions
         */
        initBulkActions: function() {
            const self = this;
            
            // Select all checkbox
            $('#cb-select-all').on('change', function() {
                $('.cbd-block-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Bulk action submit
            $('#doaction, #doaction2').on('click', function(e) {
                e.preventDefault();
                
                const action = $(this).prev('select').val();
                if (action === '-1') return;
                
                const selected = $('.cbd-block-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selected.length === 0) {
                    self.showMessage('⚠️ Bitte wählen Sie mindestens einen Block aus.', 'warning');
                    return;
                }
                
                self.performBulkAction(action, selected);
            });
        },

        /**
         * Perform bulk action
         */
        performBulkAction: function(action, blockIds) {
            const self = this;
            let confirmMsg = '';
            
            switch(action) {
                case 'delete':
                    confirmMsg = 'Wirklich ' + blockIds.length + ' Block(s) löschen?';
                    break;
                case 'activate':
                    confirmMsg = blockIds.length + ' Block(s) aktivieren?';
                    break;
                case 'deactivate':
                    confirmMsg = blockIds.length + ' Block(s) deaktivieren?';
                    break;
                default:
                    return;
            }
            
            if (!confirm(confirmMsg)) return;
            
            $.ajax({
                url: cbdAdmin.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_bulk_action',
                    bulk_action: action,
                    block_ids: blockIds,
                    nonce: cbdAdmin.nonce || $('#cbd-nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage('✅ ' + response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        self.showMessage('❌ ' + (response.data || 'Fehler'), 'error');
                    }
                },
                error: function() {
                    self.showMessage('❌ Fehler bei der Bulk-Aktion', 'error');
                }
            });
        },

        /**
         * Initialize search
         */
        initSearch: function() {
            const self = this;
            let searchTimeout;
            
            // Live search
            $('#cbd-search-input').on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                
                searchTimeout = setTimeout(function() {
                    self.performSearch(query);
                }, 300);
            });
            
            // Search form submit
            $('#cbd-search-form').on('submit', function(e) {
                e.preventDefault();
                self.performSearch($('#cbd-search-input').val());
            });
        },

        /**
         * Perform search
         */
        performSearch: function(query) {
            const $rows = $('.cbd-blocks-table tbody tr');
            
            if (!query) {
                $rows.show();
                return;
            }
            
            const searchTerm = query.toLowerCase();
            
            $rows.each(function() {
                const $row = $(this);
                const blockName = $row.find('.cbd-block-name').text().toLowerCase();
                const blockSlug = $row.find('.cbd-block-slug').text().toLowerCase();
                const blockDesc = $row.find('.cbd-block-description').text().toLowerCase();
                
                if (blockName.includes(searchTerm) || 
                    blockSlug.includes(searchTerm) || 
                    blockDesc.includes(searchTerm)) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if (DEBUG) console.log('📄 DOM ready, initializing CBD Admin...');
        CBDAdmin.init();
    });
    
    // Also bind to window load as fallback
    $(window).on('load', function() {
        // Check if already initialized
        if (!CBDAdmin.initialized) {
            if (DEBUG) console.log('🪟 Window loaded, initializing CBD Admin as fallback...');
            CBDAdmin.init();
            CBDAdmin.initialized = true;
        }
    });
    
})(jQuery);