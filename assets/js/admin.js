/**
 * Container Block Designer - Admin JavaScript
 * Version: 2.5.2
 * 
 * Datei: assets/js/admin.js
 */

(function($) {
    'use strict';
    
    // Warte bis DOM geladen ist
    $(document).ready(function() {
        initAdmin();
    });
    
    /**
     * Admin-Funktionen initialisieren
     */
    function initAdmin() {
        // Color Picker initialisieren
        initColorPickers();
        
        // Tabs initialisieren
        initTabs();
        
        // Form Validierung
        initFormValidation();
        
        // Range Slider
        initRangeSliders();
        
        // Block Preview
        initBlockPreview();
        
        // AJAX Actions
        initAjaxActions();
        
        // Sortable für Features
        initSortable();
    }
    
    /**
     * Color Picker initialisieren
     */
    function initColorPickers() {
        $('.cbd-color-picker').each(function() {
            const $input = $(this);
            
            if ($.fn.wpColorPicker) {
                $input.wpColorPicker({
                    change: function(event, ui) {
                        updatePreview();
                    },
                    clear: function() {
                        updatePreview();
                    }
                });
            }
        });
    }
    
    /**
     * Tabs initialisieren
     */
    function initTabs() {
        $('.cbd-tab-nav-item').on('click', function() {
            const $tab = $(this);
            const target = $tab.data('tab');
            const $container = $tab.closest('.cbd-tabs');
            
            // Active State für Navigation
            $container.find('.cbd-tab-nav-item').removeClass('active');
            $tab.addClass('active');
            
            // Content anzeigen
            $container.find('.cbd-tab-content').removeClass('active');
            $container.find('#' + target).addClass('active');
            
            // In localStorage speichern
            if (typeof(Storage) !== 'undefined') {
                localStorage.setItem('cbd_active_tab', target);
            }
        });
        
        // Letzten aktiven Tab wiederherstellen
        if (typeof(Storage) !== 'undefined') {
            const activeTab = localStorage.getItem('cbd_active_tab');
            if (activeTab) {
                $(`.cbd-tab-nav-item[data-tab="${activeTab}"]`).trigger('click');
            }
        }
    }
    
    /**
     * Form Validierung
     */
    function initFormValidation() {
        // Nur auf new-block Seite ausführen (hat #block-slug Element)
        if (!$('#block-slug').length) {
            return;
        }
        
        $('#cbd-block-form').on('submit', function(e) {
            let isValid = true;
            const errors = [];
            
            // Name validieren
            const name = $('#block-name').val().trim();
            if (!name) {
                errors.push(cbdAdmin.strings.nameRequired || 'Name ist erforderlich');
                isValid = false;
            }
            
            // Slug validieren (nur wenn Element existiert)
            const $slugField = $('#block-slug');
            const slug = $slugField.length ? $slugField.val().trim() : '';
            if (!slug) {
                errors.push(cbdAdmin.strings.slugRequired || 'Slug ist erforderlich');
                isValid = false;
            } else if (!/^[a-z0-9-]+$/.test(slug)) {
                errors.push(cbdAdmin.strings.slugInvalid || 'Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten');
                isValid = false;
            }
            
            // Fehler anzeigen
            if (!isValid) {
                e.preventDefault();
                showNotice(errors.join('<br>'), 'error');
            }
        });
        
        // Auto-Slug generieren
        $('#block-name').on('keyup', function() {
            const $slug = $('#block-slug');
            
            // Nur wenn Slug leer ist oder automatisch generiert wurde
            if (!$slug.val() || $slug.data('auto-generated')) {
                const slug = generateSlug($(this).val());
                $slug.val(slug).data('auto-generated', true);
            }
        });
        
        // Manuell bearbeiteter Slug
        $('#block-slug').on('keyup', function() {
            $(this).data('auto-generated', false);
        });
    }
    
    /**
     * Range Sliders initialisieren
     */
    function initRangeSliders() {
        $('.cbd-range-wrapper input[type="range"]').each(function() {
            const $range = $(this);
            const $value = $range.siblings('.cbd-range-value');
            
            // Initial Value setzen
            $value.text($range.val() + ($range.data('unit') || 'px'));
            
            // Bei Änderung aktualisieren
            $range.on('input', function() {
                $value.text($(this).val() + ($range.data('unit') || 'px'));
                updatePreview();
            });
        });
    }
    
    /**
     * Block Preview initialisieren
     */
    function initBlockPreview() {
        const $preview = $('#cbd-block-preview');
        
        if (!$preview.length) {
            return;
        }
        
        // Initial Preview
        updatePreview();
        
        // Live Preview bei Änderungen - verwende korrekte Selektoren
        $('input[name^="styles["], select[name^="styles["]').on('change keyup input', function() {
            updatePreview();
        });

        // Preview Update Button (falls vorhanden)
        $('#cbd-update-preview').on('click', function() {
            updatePreview();
        });

        // Debugging: Logge welche Elements gefunden wurden
        console.log('CBD: Preview-Container gefunden:',
            $('#cbd-block-preview-content').length,
            $('#cbd-preview-block .cbd-preview-content').length,
            $('.cbd-preview-content').length
        );
        console.log('CBD: Style-Input-Felder gefunden:', $('input[name^="styles["]').length);
    }
    
    /**
     * Preview aktualisieren
     */
    function updatePreview() {
        // Suche nach beiden möglichen Preview-Containern
        let $preview = $('#cbd-block-preview-content');
        if (!$preview.length) {
            $preview = $('#cbd-preview-block .cbd-preview-content');
        }
        if (!$preview.length) {
            $preview = $('.cbd-preview-content');
        }

        if (!$preview.length) {
            console.log('CBD: Kein Preview-Container gefunden');
            return;
        }
        
        // Styles sammeln - verwende die korrekten Name-Attribute
        const styles = {
            padding: {
                top: $('input[name="styles[padding][top]"]').val() || 20,
                right: $('input[name="styles[padding][right]"]').val() || 20,
                bottom: $('input[name="styles[padding][bottom]"]').val() || 20,
                left: $('input[name="styles[padding][left]"]').val() || 20
            },
            background: {
                color: $('input[name="styles[background][color]"]').val() || '#ffffff'
            },
            border: {
                width: $('input[name="styles[border][width]"]').val() || 0,
                style: $('select[name="styles[border][style]"]').val() || 'solid',
                color: $('input[name="styles[border][color]"]').val() || '#dddddd',
                radius: $('input[name="styles[border][radius]"]').val() || 0
            },
            typography: {
                color: $('input[name="styles[text][color]"]').val() || '#333333',
                fontSize: $('input[name="styles[text][fontSize]"]').val() || '16px'
            }
        };
        
        // CSS generieren
        let css = '';
        css += `padding: ${styles.padding.top}px ${styles.padding.right}px ${styles.padding.bottom}px ${styles.padding.left}px;`;
        css += `background-color: ${styles.background.color};`;
        
        if (styles.border.width > 0) {
            css += `border: ${styles.border.width}px ${styles.border.style} ${styles.border.color};`;
        }
        
        if (styles.border.radius > 0) {
            css += `border-radius: ${styles.border.radius}px;`;
        }
        
        css += `color: ${styles.typography.color};`;
        css += `font-size: ${styles.typography.fontSize};`;
        
        // Styles anwenden
        $preview.attr('style', css);
        
        // Features aktualisieren
        updatePreviewFeatures();
    }
    
    /**
     * Preview Features aktualisieren
     */
    function updatePreviewFeatures() {
        const $preview = $('#cbd-block-preview-content');
        
        // Icon
        if ($('#feature-icon-enabled').is(':checked')) {
            const iconClass = $('#feature-icon-value').val() || 'dashicons-admin-generic';
            const iconPosition = $('#feature-icon-position').val() || 'top-left';
            
            let $icon = $preview.find('.cbd-preview-icon');
            if (!$icon.length) {
                $icon = $('<span class="cbd-preview-icon dashicons"></span>');
                $preview.prepend($icon);
            }
            
            $icon.removeClass().addClass('cbd-preview-icon dashicons ' + iconClass);
            $icon.attr('data-position', iconPosition);
        } else {
            $preview.find('.cbd-preview-icon').remove();
        }
        
        // Numbering
        if ($('#feature-numbering-enabled').is(':checked')) {
            const format = $('#feature-numbering-format').val() || 'numeric';
            const position = $('#feature-numbering-position').val() || 'top-left';
            
            let $number = $preview.find('.cbd-preview-number');
            if (!$number.length) {
                $number = $('<span class="cbd-preview-number">1</span>');
                $preview.prepend($number);
            }
            
            $number.attr('data-position', position);
            
            // Format anwenden
            switch(format) {
                case 'roman':
                    $number.text('I');
                    break;
                case 'letters':
                    $number.text('A');
                    break;
                case 'leading-zero':
                    $number.text('01');
                    break;
                default:
                    $number.text('1');
            }
        } else {
            $preview.find('.cbd-preview-number').remove();
        }
    }
    
    /**
     * AJAX Actions initialisieren
     */
    function initAjaxActions() {
        // Block speichern
        $('.cbd-save-block').on('click', function(e) {
            e.preventDefault();
            saveBlock();
        });
        
        // Block löschen
        $('.cbd-delete-block').on('click', function(e) {
            e.preventDefault();
            
            if (confirm(cbdAdmin.strings.confirmDelete)) {
                deleteBlock($(this).data('block-id'));
            }
        });
        
        // Block Status ändern
        $('.cbd-toggle-status').on('click', function(e) {
            e.preventDefault();
            toggleBlockStatus($(this).data('block-id'));
        });
        
        // Styles aktualisieren
        $('.cbd-refresh-styles').on('click', function(e) {
            e.preventDefault();
            refreshStyles();
        });
    }
    
    /**
     * Block speichern
     */
    function saveBlock() {
        const $form = $('#cbd-block-form');
        const formData = $form.serialize();
        
        // Loading State
        showSpinner();
        
        $.ajax({
            url: cbdAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cbd_save_block',
                nonce: cbdAdmin.nonce,
                data: formData
            },
            success: function(response) {
                hideSpinner();
                
                if (response.success) {
                    showNotice(cbdAdmin.strings.saved, 'success');
                    
                    // Nach 2 Sekunden zur Übersicht
                    setTimeout(function() {
                        window.location.href = cbdAdmin.adminUrl + 'admin.php?page=container-block-designer';
                    }, 2000);
                } else {
                    showNotice(response.data.message || cbdAdmin.strings.error, 'error');
                }
            },
            error: function() {
                hideSpinner();
                showNotice(cbdAdmin.strings.error, 'error');
            }
        });
    }
    
    /**
     * Block löschen
     */
    function deleteBlock(blockId) {
        showSpinner();
        
        $.ajax({
            url: cbdAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cbd_delete_block',
                nonce: cbdAdmin.nonce,
                block_id: blockId
            },
            success: function(response) {
                hideSpinner();
                
                if (response.success) {
                    showNotice(cbdAdmin.strings.deleted, 'success');
                    
                    // Zeile aus Tabelle entfernen
                    $(`tr[data-block-id="${blockId}"]`).fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    showNotice(response.data.message || cbdAdmin.strings.error, 'error');
                }
            },
            error: function() {
                hideSpinner();
                showNotice(cbdAdmin.strings.error, 'error');
            }
        });
    }
    
    /**
     * Block Status ändern
     */
    function toggleBlockStatus(blockId) {
        showSpinner();
        
        $.ajax({
            url: cbdAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cbd_toggle_block_status',
                nonce: cbdAdmin.nonce,
                block_id: blockId
            },
            success: function(response) {
                hideSpinner();
                
                if (response.success) {
                    // Status Badge aktualisieren
                    const $badge = $(`tr[data-block-id="${blockId}"] .cbd-status`);
                    
                    if ($badge.hasClass('cbd-status-active')) {
                        $badge.removeClass('cbd-status-active').addClass('cbd-status-inactive').text('Inaktiv');
                    } else {
                        $badge.removeClass('cbd-status-inactive').addClass('cbd-status-active').text('Aktiv');
                    }
                    
                    showNotice(cbdAdmin.strings.statusChanged, 'success');
                } else {
                    showNotice(response.data.message || cbdAdmin.strings.error, 'error');
                }
            },
            error: function() {
                hideSpinner();
                showNotice(cbdAdmin.strings.error, 'error');
            }
        });
    }
    
    /**
     * Styles aktualisieren
     */
    function refreshStyles() {
        showSpinner();
        
        $.ajax({
            url: cbdAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cbd_refresh_styles',
                nonce: cbdAdmin.nonce
            },
            success: function(response) {
                hideSpinner();
                
                if (response.success) {
                    showNotice(cbdAdmin.strings.stylesRefreshed || 'Styles aktualisiert', 'success');
                } else {
                    showNotice(response.data.message || cbdAdmin.strings.error, 'error');
                }
            },
            error: function() {
                hideSpinner();
                showNotice(cbdAdmin.strings.error, 'error');
            }
        });
    }
    
    /**
     * Sortable für Features
     */
    function initSortable() {
        if ($.fn.sortable) {
            $('.cbd-features-list').sortable({
                handle: '.cbd-feature-handle',
                placeholder: 'cbd-feature-placeholder',
                update: function(event, ui) {
                    updateFeatureOrder();
                }
            });
        }
    }
    
    /**
     * Feature-Reihenfolge aktualisieren
     */
    function updateFeatureOrder() {
        const order = [];
        
        $('.cbd-feature-item').each(function(index) {
            const featureId = $(this).data('feature');
            order.push(featureId);
            $(this).find('.cbd-feature-order').val(index);
        });
        
        // Order in Hidden Field speichern
        $('#feature-order').val(order.join(','));
    }
    
    /**
     * Slug generieren
     */
    function generateSlug(text) {
        return text
            .toLowerCase()
            .replace(/ä/g, 'ae')
            .replace(/ö/g, 'oe')
            .replace(/ü/g, 'ue')
            .replace(/ß/g, 'ss')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
    
    /**
     * Notice anzeigen
     */
    function showNotice(message, type) {
        // Alte Notices entfernen
        $('.cbd-ajax-notice').remove();
        
        const noticeHtml = `
            <div class="cbd-ajax-notice cbd-notice cbd-notice-${type}">
                <p>${message}</p>
            </div>
        `;
        
        $('.cbd-admin-wrap').prepend(noticeHtml);
        
        // Nach oben scrollen
        $('html, body').animate({ scrollTop: 0 }, 300);
        
        // Nach 5 Sekunden ausblenden
        setTimeout(function() {
            $('.cbd-ajax-notice').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Spinner anzeigen
     */
    function showSpinner() {
        if (!$('.cbd-global-spinner').length) {
            $('body').append('<div class="cbd-global-spinner"><span class="cbd-spinner"></span></div>');
        }
        $('.cbd-global-spinner').fadeIn();
    }
    
    /**
     * Spinner verstecken
     */
    function hideSpinner() {
        $('.cbd-global-spinner').fadeOut();
    }
    
})(jQuery);