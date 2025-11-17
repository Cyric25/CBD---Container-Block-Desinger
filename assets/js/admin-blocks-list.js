/**
 * Container Block Designer - Admin Blocks List JavaScript
 * Version: 2.6.0
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Blocks List functionality
     */
    const CBDBlocksList = {
        
        /**
         * Initialize blocks list features
         */
        init: function() {
            this.initQuickActions();
            this.initStatusToggle();
            this.initDuplication();
            this.initSearch();
            this.initSorting();
        },

        /**
         * Initialize quick actions in row
         */
        initQuickActions: function() {
            // Quick edit inline
            $(document).on('click', '.quick-edit', function(e) {
                e.preventDefault();
                const row = $(this).closest('tr');
                const blockId = row.find('input[type="checkbox"]').val();
                CBDBlocksList.showQuickEdit(row, blockId);
            });

            // Save quick edit
            $(document).on('click', '.save-quick-edit', function(e) {
                e.preventDefault();
                CBDBlocksList.saveQuickEdit($(this));
            });

            // Cancel quick edit
            $(document).on('click', '.cancel-quick-edit', function(e) {
                e.preventDefault();
                CBDBlocksList.cancelQuickEdit();
            });
        },

        /**
         * Initialize status toggle
         */
        initStatusToggle: function() {
            $(document).on('click', '.toggle-status', function(e) {
                e.preventDefault();
                
                const link = $(this);
                const blockId = link.data('block-id');
                const currentStatus = link.data('current-status');
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                
                // Show loading state
                link.addClass('loading').text('...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cbd_admin_action',
                        cbd_action: 'toggle_block_status',
                        block_id: blockId,
                        status: newStatus,
                        _wpnonce: cbdAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update UI
                            CBDBlocksList.updateStatusDisplay(link, newStatus);
                            CBDAdmin.common.showNotice('success', response.data.message);
                        } else {
                            CBDAdmin.common.showNotice('error', response.data || 'Status update failed');
                        }
                    },
                    error: function() {
                        CBDAdmin.common.showNotice('error', 'Network error occurred');
                    },
                    complete: function() {
                        link.removeClass('loading');
                    }
                });
            });
        },

        /**
         * Update status display
         */
        updateStatusDisplay: function(link, newStatus) {
            const row = link.closest('tr');
            const statusCell = row.find('.column-status');
            const statusBadge = statusCell.find('.status-badge');
            
            // Update badge
            statusBadge.removeClass('status-active status-inactive')
                      .addClass('status-' + newStatus)
                      .text(newStatus === 'active' ? 'Aktiv' : 'Inaktiv');
            
            // Update link
            link.data('current-status', newStatus)
                .text(newStatus === 'active' ? 'Deaktivieren' : 'Aktivieren');
        },

        /**
         * Initialize block duplication
         */
        initDuplication: function() {
            $(document).on('click', '.duplicate-block', function(e) {
                e.preventDefault();
                
                const link = $(this);
                const blockId = link.data('block-id');
                
                if (!confirm('Block duplizieren?')) {
                    return;
                }
                
                link.addClass('loading').text('Dupliziere...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cbd_admin_action',
                        cbd_action: 'duplicate_block',
                        block_id: blockId,
                        _wpnonce: cbdAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                window.location.reload();
                            }
                        } else {
                            CBDAdmin.common.showNotice('error', response.data || 'Duplication failed');
                        }
                    },
                    error: function() {
                        CBDAdmin.common.showNotice('error', 'Network error occurred');
                    },
                    complete: function() {
                        link.removeClass('loading').text('Duplizieren');
                    }
                });
            });
        },

        /**
         * Initialize search functionality
         */
        initSearch: function() {
            const searchInput = $('#block-search-input');
            if (searchInput.length === 0) return;

            // Real-time search with debounce
            const debouncedSearch = CBDAdmin.common.debounce(function() {
                CBDBlocksList.performSearch(searchInput.val());
            }, 300);

            searchInput.on('input', debouncedSearch);
            
            // Clear search
            $(document).on('click', '.clear-search', function(e) {
                e.preventDefault();
                searchInput.val('').trigger('input');
            });
        },

        /**
         * Perform search
         */
        performSearch: function(query) {
            const table = $('.wp-list-table tbody');
            const rows = table.find('tr');
            
            if (!query) {
                rows.show();
                return;
            }
            
            query = query.toLowerCase();
            
            rows.each(function() {
                const row = $(this);
                const name = row.find('.column-name').text().toLowerCase();
                const title = row.find('.column-title').text().toLowerCase();
                const description = row.find('.column-description').text().toLowerCase();
                
                const matches = name.includes(query) || 
                               title.includes(query) || 
                               description.includes(query);
                
                row.toggle(matches);
            });
            
            // Update "no results" message
            CBDBlocksList.updateNoResultsMessage(rows.filter(':visible').length);
        },

        /**
         * Update no results message
         */
        updateNoResultsMessage: function(visibleCount) {
            const table = $('.wp-list-table');
            let noResults = table.find('.no-results-row');
            
            if (visibleCount === 0) {
                if (noResults.length === 0) {
                    const colCount = table.find('thead th').length;
                    noResults = $('<tr class="no-results-row"><td colspan="' + colCount + '" style="text-align: center; padding: 40px; color: #666;">Keine Blöcke gefunden.</td></tr>');
                    table.find('tbody').append(noResults);
                }
                noResults.show();
            } else {
                noResults.hide();
            }
        },

        /**
         * Initialize sorting
         */
        initSorting: function() {
            $('.column-name, .column-date').on('click', function(e) {
                if ($(e.target).is('a')) return; // Don't interfere with links
                
                const column = $(this);
                const sortable = column.find('.sortable-link');
                
                if (sortable.length > 0) {
                    window.location.href = sortable.attr('href');
                }
            });
        },

        /**
         * Show quick edit form
         */
        showQuickEdit: function(row, blockId) {
            // Hide any existing quick edit
            CBDBlocksList.cancelQuickEdit();
            
            const name = row.find('.column-name strong a').text();
            const title = row.find('.column-title').text();
            const description = row.find('.column-description').text();
            const status = row.find('.status-badge').hasClass('status-active') ? 'active' : 'inactive';
            
            // Create quick edit form
            const quickEditHtml = `
                <tr class="quick-edit-row">
                    <td colspan="7">
                        <div class="quick-edit-form">
                            <h4>Quick Edit</h4>
                            <div class="quick-edit-fields">
                                <label>
                                    <span>Name:</span>
                                    <input type="text" name="name" value="${name}" readonly>
                                </label>
                                <label>
                                    <span>Titel:</span>
                                    <input type="text" name="title" value="${title}">
                                </label>
                                <label>
                                    <span>Beschreibung:</span>
                                    <textarea name="description">${description}</textarea>
                                </label>
                                <label>
                                    <span>Status:</span>
                                    <select name="status">
                                        <option value="active" ${status === 'active' ? 'selected' : ''}>Aktiv</option>
                                        <option value="inactive" ${status === 'inactive' ? 'selected' : ''}>Inaktiv</option>
                                    </select>
                                </label>
                            </div>
                            <div class="quick-edit-buttons">
                                <button type="button" class="button save-quick-edit" data-block-id="${blockId}">Speichern</button>
                                <button type="button" class="button cancel-quick-edit">Abbrechen</button>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            
            row.after(quickEditHtml);
            row.addClass('quick-edit-parent').hide();
        },

        /**
         * Save quick edit
         */
        saveQuickEdit: function(button) {
            const form = button.closest('.quick-edit-form');
            const blockId = button.data('block-id');
            
            const data = {
                action: 'cbd_save_block_quick',
                block_id: blockId,
                title: form.find('input[name="title"]').val(),
                description: form.find('textarea[name="description"]').val(),
                status: form.find('select[name="status"]').val(),
                _wpnonce: cbdAdmin.nonce
            };
            
            button.prop('disabled', true).text('Speichern...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        CBDAdmin.common.showNotice('success', 'Block wurde aktualisiert.');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        CBDAdmin.common.showNotice('error', response.data || 'Update failed');
                        button.prop('disabled', false).text('Speichern');
                    }
                },
                error: function() {
                    CBDAdmin.common.showNotice('error', 'Network error occurred');
                    button.prop('disabled', false).text('Speichern');
                }
            });
        },

        /**
         * Cancel quick edit
         */
        cancelQuickEdit: function() {
            $('.quick-edit-row').remove();
            $('.quick-edit-parent').removeClass('quick-edit-parent').show();
        }
    };

    // Initialize
    CBDBlocksList.init();
    
    // Export to global scope
    window.CBDBlocksList = CBDBlocksList;
});