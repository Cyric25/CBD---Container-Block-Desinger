/**
 * Container Block Designer - Common Admin JavaScript
 * Version: 2.6.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // Global CBD Admin object
    window.CBDAdmin = window.CBDAdmin || {};

    /**
     * Common admin functionality
     */
    CBDAdmin.common = {
        
        /**
         * Initialize common features
         */
        init: function() {
            this.initConfirmDialogs();
            this.initBulkActions();
            this.initNotices();
            this.initTooltips();
            this.initAjaxForms();
            this.initToggleSwitches();
        },

        /**
         * Initialize confirmation dialogs
         */
        initConfirmDialogs: function() {
            $(document).on('click', '[data-confirm]', function(e) {
                const message = $(this).data('confirm');
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });

            // Specific confirmation for delete actions
            $(document).on('click', '.delete a, .cbd-delete-btn', function(e) {
                if (!confirm(cbdAdmin.strings.confirmDelete)) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Initialize bulk actions
         */
        initBulkActions: function() {
            const self = this;

            // Select all checkbox
            $(document).on('change', '#cb-select-all, #cb-select-all-1', function() {
                const checked = $(this).prop('checked');
                $('tbody input[type="checkbox"]').prop('checked', checked);
                self.updateBulkActionButton();
            });

            // Individual checkboxes
            $(document).on('change', 'tbody input[type="checkbox"]', function() {
                self.updateSelectAll();
                self.updateBulkActionButton();
            });

            // Bulk action form submit
            $(document).on('submit', 'form[data-bulk-actions]', function(e) {
                const selectedItems = $('tbody input[type="checkbox"]:checked').length;
                const action = $(this).find('select[name="bulk_action"]').val();

                if (!action || action === '') {
                    alert(cbdAdmin.strings.noBulkAction || 'Please select an action.');
                    e.preventDefault();
                    return false;
                }

                if (selectedItems === 0) {
                    alert(cbdAdmin.strings.noItemsSelected);
                    e.preventDefault();
                    return false;
                }

                if (action === 'delete' && !confirm(cbdAdmin.strings.confirmBulkDelete)) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Update select all checkbox state
         */
        updateSelectAll: function() {
            const totalCheckboxes = $('tbody input[type="checkbox"]').length;
            const checkedCheckboxes = $('tbody input[type="checkbox"]:checked').length;

            const selectAll = $('#cb-select-all, #cb-select-all-1');
            
            if (checkedCheckboxes === 0) {
                selectAll.prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                selectAll.prop('checked', true).prop('indeterminate', false);
            } else {
                selectAll.prop('checked', false).prop('indeterminate', true);
            }
        },

        /**
         * Update bulk action button state
         */
        updateBulkActionButton: function() {
            const selectedItems = $('tbody input[type="checkbox"]:checked').length;
            const bulkButton = $('.bulk-action-submit, input[type="submit"][name="bulk_action"]');
            
            if (selectedItems > 0) {
                bulkButton.removeClass('disabled').prop('disabled', false);
            } else {
                bulkButton.addClass('disabled').prop('disabled', true);
            }
        },

        /**
         * Initialize dismissible notices
         */
        initNotices: function() {
            $(document).on('click', '.notice-dismiss, .cbd-notice .notice-dismiss', function(e) {
                e.preventDefault();
                $(this).closest('.notice, .cbd-notice').fadeOut();
            });

            // Auto-hide success notices after 5 seconds
            setTimeout(function() {
                $('.notice-success, .cbd-notice.success').fadeOut();
            }, 5000);
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            if ($.fn.tooltip) {
                $('[data-tooltip], [title]').tooltip({
                    position: { my: "center bottom-20", at: "center top" },
                    tooltipClass: "cbd-tooltip"
                });
            }
        },

        /**
         * Initialize AJAX forms
         */
        initAjaxForms: function() {
            const self = this;

            $(document).on('submit', 'form[data-ajax="true"]', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const submitBtn = form.find('input[type="submit"], button[type="submit"]');
                const originalText = submitBtn.val() || submitBtn.text();

                // Show loading state
                submitBtn.prop('disabled', true)
                    .addClass('loading')
                    .val(cbdAdmin.strings.processing || 'Processing...')
                    .text(cbdAdmin.strings.processing || 'Processing...');

                // Add spinner
                if (form.find('.cbd-spinner').length === 0) {
                    submitBtn.after('<span class="cbd-spinner"></span>');
                }

                $.ajax({
                    url: form.attr('action') || ajaxurl,
                    type: form.attr('method') || 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        self.handleAjaxResponse(response, form);
                    },
                    error: function(xhr, status, error) {
                        self.showNotice('error', cbdAdmin.strings.error || 'An error occurred.');
                        console.error('AJAX Error:', error);
                    },
                    complete: function() {
                        // Restore button state
                        submitBtn.prop('disabled', false)
                            .removeClass('loading')
                            .val(originalText)
                            .text(originalText);
                        
                        form.find('.cbd-spinner').remove();
                    }
                });
            });
        },

        /**
         * Handle AJAX response
         */
        handleAjaxResponse: function(response, form) {
            if (response.success) {
                this.showNotice('success', response.data.message || cbdAdmin.strings.done);
                
                if (response.data.redirect) {
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1000);
                }
                
                if (response.data.reload) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                this.showNotice('error', response.data || cbdAdmin.strings.error);
            }
        },

        /**
         * Show notice message
         */
        showNotice: function(type, message) {
            const notice = $('<div class="notice notice-' + type + ' is-dismissible cbd-notice"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
            
            // Find the best place to insert notice
            let target = $('.wrap h1').first();
            if (target.length === 0) {
                target = $('.wrap').first();
            }
            
            if (target.length > 0) {
                target.after(notice);
            } else {
                $('body').prepend(notice);
            }

            // Auto-hide after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    notice.fadeOut();
                }, 5000);
            }
        },

        /**
         * Initialize toggle switches
         */
        initToggleSwitches: function() {
            $(document).on('click', '.cbd-toggle-switch', function() {
                const toggle = $(this);
                const input = toggle.find('input[type="checkbox"]') || 
                              toggle.siblings('input[type="checkbox"]') ||
                              toggle.parent().find('input[type="checkbox"]');
                
                if (input.length > 0) {
                    const isChecked = !input.prop('checked');
                    input.prop('checked', isChecked).trigger('change');
                    toggle.toggleClass('active', isChecked);
                }
            });
        },

        /**
         * Utility: Show loading overlay
         */
        showLoading: function(container) {
            container = container || $('body');
            
            if (container.find('.cbd-loading-overlay').length === 0) {
                const overlay = $('<div class="cbd-loading-overlay"><div class="cbd-spinner-large"></div></div>');
                container.css('position', 'relative').append(overlay);
            }
        },

        /**
         * Utility: Hide loading overlay
         */
        hideLoading: function(container) {
            container = container || $('body');
            container.find('.cbd-loading-overlay').remove();
            container.css('position', '');
        },

        /**
         * Utility: Format bytes
         */
        formatBytes: function(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';

            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];

            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        },

        /**
         * Utility: Debounce function
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                
                if (callNow) func.apply(context, args);
            };
        }
    };

    /**
     * Search functionality
     */
    CBDAdmin.search = {
        init: function() {
            this.initSearchBox();
            this.initFilters();
        },

        initSearchBox: function() {
            const searchInput = $('#block-search-input, .cbd-search-input');
            if (searchInput.length === 0) return;

            const searchForm = searchInput.closest('form');
            const debouncedSearch = CBDAdmin.common.debounce(function() {
                if (searchForm.length > 0) {
                    searchForm.submit();
                }
            }, 500);

            searchInput.on('input', debouncedSearch);
        },

        initFilters: function() {
            $('.cbd-filter-select').on('change', function() {
                $(this).closest('form').submit();
            });
        }
    };

    // Initialize everything
    CBDAdmin.common.init();
    CBDAdmin.search.init();

    // Make CBDAdmin globally accessible
    window.CBDAdmin = CBDAdmin;
});

// CSS for loading overlay and spinner
jQuery(document).ready(function($) {
    if ($('#cbd-admin-styles').length === 0) {
        $('<style id="cbd-admin-styles">').text(`
            .cbd-loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
            
            .cbd-spinner-large {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #0073aa;
                border-radius: 50%;
                animation: cbd-spin 1s linear infinite;
            }
            
            .cbd-tooltip {
                background: #333 !important;
                color: white !important;
                border: none !important;
                border-radius: 4px !important;
                padding: 8px 12px !important;
                font-size: 12px !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
            }
            
            .cbd-notice {
                animation: cbd-notice-in 0.3s ease-out;
            }
            
            @keyframes cbd-notice-in {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `).appendTo('head');
    }
});