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
            this.initStatusToggle();
            this.initDuplication();
            this.initSearch();
            this.initSorting();
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

    };

    // Initialize
    CBDBlocksList.init();
    
    // Export to global scope
    window.CBDBlocksList = CBDBlocksList;
});