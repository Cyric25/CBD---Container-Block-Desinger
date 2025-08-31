/**
 * Container Block Designer - Features Management
 * Version: 1.0.0
 */

(function($) {
    'use strict';
    
    // Debug flag
    const DEBUG = true;
    
    /**
     * CBD Features Manager
     */
    window.CBDFeatures = {
        
        currentBlockId: null,
        currentBlockName: null,
        
        /**
         * Initialize
         */
        init: function() {
            if (DEBUG) console.log('🚀 CBDFeatures.init() gestartet');
            
            // Sicherstellen dass jQuery geladen ist
            if (typeof $ === 'undefined') {
                console.error('❌ jQuery nicht gefunden!');
                return;
            }
            
            // Event-Handler sofort binden
            this.bindEvents();
            
            // Modal erstellen falls nicht vorhanden
            if ($('#cbd-features-modal').length === 0) {
                this.createModal();
            }
            
            if (DEBUG) console.log('✅ CBDFeatures erfolgreich initialisiert');
        },
        
        /**
         * Create the features modal
         */
        createModal: function() {
            if (DEBUG) console.log('📦 Erstelle Features Modal...');
            
            const modalHTML = `
                <div id="cbd-features-modal" class="cbd-modal" style="display: none;">
                    <div class="cbd-modal-backdrop"></div>
                    <div class="cbd-modal-content">
                        <div class="cbd-modal-header">
                            <h2 id="cbd-modal-title">Block Features konfigurieren</h2>
                            <button type="button" class="cbd-modal-close">&times;</button>
                        </div>
                        
                        <div class="cbd-modal-body">
                            <input type="hidden" id="features-block-id" value="">
                            
                            <form id="cbd-features-form">
                                <!-- Feature 1: Custom Icon -->
                                <div class="cbd-feature-item">
                                    <label class="cbd-feature-toggle">
                                        <input type="checkbox" id="feature-icon-enabled">
                                        <span>Custom Icon</span>
                                    </label>
                                    <div class="cbd-feature-settings" id="feature-icon-settings" style="display: none;">
                                        <label>Icon auswählen:</label>
                                        <div class="cbd-icon-selector">
                                            <input type="text" id="block-icon-value" placeholder="dashicons-admin-generic" class="regular-text">
                                            <button type="button" class="button cbd-icon-picker">Icon wählen</button>
                                            <div class="cbd-current-icon">
                                                <span class="dashicons dashicons-admin-generic"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feature 2: Collapsible -->
                                <div class="cbd-feature-item">
                                    <label class="cbd-feature-toggle">
                                        <input type="checkbox" id="feature-collapse-enabled">
                                        <span>Ein-/Ausklappbar</span>
                                    </label>
                                    <div class="cbd-feature-settings" id="feature-collapse-settings" style="display: none;">
                                        <label>Standardzustand:</label>
                                        <select id="collapse-default-state">
                                            <option value="expanded">Ausgeklappt</option>
                                            <option value="collapsed">Eingeklappt</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Feature 3: Numbering -->
                                <div class="cbd-feature-item">
                                    <label class="cbd-feature-toggle">
                                        <input type="checkbox" id="feature-numbering-enabled">
                                        <span>Nummerierung</span>
                                    </label>
                                    <div class="cbd-feature-settings" id="feature-numbering-settings" style="display: none;">
                                        <label>Format:</label>
                                        <select id="numbering-format">
                                            <option value="numeric">1, 2, 3...</option>
                                            <option value="alphabetic">a, b, c...</option>
                                            <option value="roman">I, II, III...</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Feature 4: Copy Text -->
                                <div class="cbd-feature-item">
                                    <label class="cbd-feature-toggle">
                                        <input type="checkbox" id="feature-copy-enabled">
                                        <span>Text kopieren Button</span>
                                    </label>
                                    <div class="cbd-feature-settings" id="feature-copy-settings" style="display: none;">
                                        <label>Button-Text:</label>
                                        <input type="text" id="copy-button-text" value="Text kopieren" class="regular-text">
                                    </div>
                                </div>
                                
                                <!-- Feature 5: Screenshot -->
                                <div class="cbd-feature-item">
                                    <label class="cbd-feature-toggle">
                                        <input type="checkbox" id="feature-screenshot-enabled">
                                        <span>Screenshot Button</span>
                                    </label>
                                    <div class="cbd-feature-settings" id="feature-screenshot-settings" style="display: none;">
                                        <label>Button-Text:</label>
                                        <input type="text" id="screenshot-button-text" value="Screenshot" class="regular-text">
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="cbd-modal-footer">
                            <button type="button" id="cbd-save-features" class="button button-primary">Features speichern</button>
                            <button type="button" id="cbd-modal-cancel" class="button">Abbrechen</button>
                            <button type="button" id="cbd-reset-features" class="button">Zurücksetzen</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Append modal to body
            $('body').append(modalHTML);
            if (DEBUG) console.log('✅ Features Modal wurde erstellt');
        },
        
        /**
         * Bind all events
         */
        bindEvents: function() {
            const self = this;
            if (DEBUG) console.log('📌 Binde Event-Handler...');
            
            // Entferne alte Event-Handler um Duplikate zu vermeiden
            $(document).off('click', '.cbd-features-btn, .cbd-configure-features, [data-action="configure-features"]');
            $(document).off('change', '#cbd-features-form input[type="checkbox"]');
            $(document).off('click', '#cbd-save-features');
            $(document).off('click', '#cbd-modal-cancel, .cbd-modal-close, .cbd-modal-backdrop');
            $(document).off('click', '#cbd-reset-features');
            
            // Features konfigurieren Button - HAUPTPROBLEM GELÖST
            $(document).on('click', '.cbd-features-btn, .cbd-configure-features, [data-action="configure-features"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (DEBUG) console.log('🔧 Features Button geklickt!', this);
                
                const $btn = $(this);
                
                // Verschiedene Wege um die Block-ID zu finden
                let blockId = $btn.data('block-id') || 
                             $btn.data('id') || 
                             $btn.closest('tr').data('block-id') || 
                             $btn.closest('.cbd-block-edit').find('#block-id').val() ||
                             $('#block-id').val();
                
                let blockName = $btn.data('block-name') || 
                               $btn.data('name') || 
                               $('#block-name').val() || 
                               'Block';
                
                if (DEBUG) {
                    console.log('Block-ID gefunden:', blockId);
                    console.log('Block-Name:', blockName);
                }
                
                if (blockId && blockId !== '0') {
                    self.openFeaturesModal(blockId, blockName);
                } else {
                    alert('Bitte speichern Sie zuerst den Block, bevor Sie Features konfigurieren.');
                }
            });
            
            // Feature toggle switches
            $(document).on('change', '#cbd-features-form input[type="checkbox"]', function() {
                const $settings = $(this).closest('.cbd-feature-item').find('.cbd-feature-settings');
                if ($(this).prop('checked')) {
                    $settings.slideDown(200);
                } else {
                    $settings.slideUp(200);
                }
            });
            
            // Save Features
            $(document).on('click', '#cbd-save-features', function() {
                if (DEBUG) console.log('💾 Speichere Features...');
                self.saveFeatures();
            });
            
            // Close Modal
            $(document).on('click', '#cbd-modal-cancel, .cbd-modal-close, .cbd-modal-backdrop', function() {
                if (DEBUG) console.log('❌ Schließe Modal');
                self.closeModal();
            });
            
            // Reset Features
            $(document).on('click', '#cbd-reset-features', function() {
                if (confirm('Wirklich alle Features zurücksetzen?')) {
                    self.resetFeatures();
                }
            });
            
            // Icon value change
            $(document).on('input', '#block-icon-value', function() {
                const iconClass = $(this).val();
                $('.cbd-current-icon span').attr('class', 'dashicons ' + iconClass);
            });
            
            // Escape key to close modal
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' && $('#cbd-features-modal').is(':visible')) {
                    self.closeModal();
                }
            });
            
            if (DEBUG) console.log('✅ Event-Handler wurden gebunden');
        },
        
        /**
         * Open Features Modal
         */
        openFeaturesModal: function(blockId, blockName) {
            if (DEBUG) console.log('📂 Öffne Features Modal für Block:', blockId, blockName);
            
            if (!blockId) {
                alert('Fehler: Keine Block-ID gefunden.');
                return;
            }
            
            this.currentBlockId = blockId;
            this.currentBlockName = blockName || 'Block';
            
            // Modal erstellen falls noch nicht vorhanden
            if ($('#cbd-features-modal').length === 0) {
                this.createModal();
            }
            
            // Set block info
            $('#features-block-id').val(blockId);
            $('#cbd-modal-title').text('Features für "' + this.currentBlockName + '" konfigurieren');
            
            // Load current features
            this.loadFeatures(blockId);
            
            // Show modal
            $('#cbd-features-modal').fadeIn(200);
            $('body').addClass('cbd-modal-open');
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('#cbd-features-modal').fadeOut(200);
            $('body').removeClass('cbd-modal-open');
        },
        
        /**
         * Load features for a block
         */
        loadFeatures: function(blockId) {
            if (DEBUG) console.log('📥 Lade Features für Block:', blockId);
            
            const self = this;
            
            // Reset form
            $('#cbd-features-form')[0].reset();
            $('.cbd-feature-settings').hide();
            
            // AJAX request
            $.ajax({
                url: cbdAdmin?.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_get_block_features',
                    block_id: blockId,
                    nonce: cbdAdmin?.nonce || $('#cbd-nonce').val()
                },
                success: function(response) {
                    if (DEBUG) console.log('📨 Features geladen:', response);
                    
                    if (response.success && response.data) {
                        self.populateFeatures(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Fehler beim Laden der Features:', error);
                }
            });
        },
        
        /**
         * Populate features in the form
         */
        populateFeatures: function(features) {
            if (DEBUG) console.log('🎨 Fülle Features-Formular:', features);
            
            if (!features) return;
            
            // Feature 1: Custom Icon
            if (features.customIcon) {
                const iconEnabled = features.customIcon.enabled === true || 
                                  features.customIcon.enabled === 'true' || 
                                  features.customIcon.enabled === 1;
                $('#feature-icon-enabled').prop('checked', iconEnabled);
                if (iconEnabled) {
                    $('#feature-icon-settings').show();
                    $('#block-icon-value').val(features.customIcon.icon || 'dashicons-admin-generic');
                    $('.cbd-current-icon span').attr('class', 'dashicons ' + (features.customIcon.icon || 'dashicons-admin-generic'));
                }
            }
            
            // Feature 2: Collapsible
            if (features.collapse) {
                const collapseEnabled = features.collapse.enabled === true || 
                                      features.collapse.enabled === 'true' || 
                                      features.collapse.enabled === 1;
                $('#feature-collapse-enabled').prop('checked', collapseEnabled);
                if (collapseEnabled) {
                    $('#feature-collapse-settings').show();
                    $('#collapse-default-state').val(features.collapse.defaultState || 'expanded');
                }
            }
            
            // Feature 3: Numbering
            if (features.numbering) {
                const numberingEnabled = features.numbering.enabled === true || 
                                       features.numbering.enabled === 'true' || 
                                       features.numbering.enabled === 1;
                $('#feature-numbering-enabled').prop('checked', numberingEnabled);
                if (numberingEnabled) {
                    $('#feature-numbering-settings').show();
                    $('#numbering-format').val(features.numbering.format || 'numeric');
                }
            }
            
            // Feature 4: Copy Text
            if (features.copyText) {
                const copyEnabled = features.copyText.enabled === true || 
                                  features.copyText.enabled === 'true' || 
                                  features.copyText.enabled === 1;
                $('#feature-copy-enabled').prop('checked', copyEnabled);
                if (copyEnabled) {
                    $('#feature-copy-settings').show();
                    $('#copy-button-text').val(features.copyText.buttonText || 'Text kopieren');
                }
            }
            
            // Feature 5: Screenshot
            if (features.screenshot) {
                const screenshotEnabled = features.screenshot.enabled === true || 
                                        features.screenshot.enabled === 'true' || 
                                        features.screenshot.enabled === 1;
                $('#feature-screenshot-enabled').prop('checked', screenshotEnabled);
                if (screenshotEnabled) {
                    $('#feature-screenshot-settings').show();
                    $('#screenshot-button-text').val(features.screenshot.buttonText || 'Screenshot');
                }
            }
            
            if (DEBUG) console.log('✅ Features wurden geladen');
        },
        
        /**
         * Save features
         */
        saveFeatures: function() {
            const blockId = $('#features-block-id').val();
            
            if (!blockId) {
                alert('Fehler: Keine Block-ID gefunden.');
                return;
            }
            
            // Collect feature data
            const featuresData = {
                customIcon: {
                    enabled: $('#feature-icon-enabled').is(':checked'),
                    icon: $('#block-icon-value').val() || 'dashicons-admin-generic'
                },
                collapse: {
                    enabled: $('#feature-collapse-enabled').is(':checked'),
                    defaultState: $('#collapse-default-state').val() || 'expanded'
                },
                numbering: {
                    enabled: $('#feature-numbering-enabled').is(':checked'),
                    format: $('#numbering-format').val() || 'numeric'
                },
                copyText: {
                    enabled: $('#feature-copy-enabled').is(':checked'),
                    buttonText: $('#copy-button-text').val() || 'Text kopieren'
                },
                screenshot: {
                    enabled: $('#feature-screenshot-enabled').is(':checked'),
                    buttonText: $('#screenshot-button-text').val() || 'Screenshot'
                }
            };
            
            if (DEBUG) console.log('💾 Speichere Features:', featuresData);
            
            // Change button state
            const $saveBtn = $('#cbd-save-features');
            const originalText = $saveBtn.text();
            $saveBtn.text('Speichern...').prop('disabled', true);
            
            // AJAX request
            $.ajax({
                url: cbdAdmin?.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbd_save_block_features',
                    block_id: blockId,
                    features: JSON.stringify(featuresData),
                    nonce: cbdAdmin?.nonce || $('#cbd-nonce').val()
                },
                success: function(response) {
                    if (DEBUG) console.log('✅ Features gespeichert:', response);
                    
                    if (response.success) {
                        $saveBtn.text('Gespeichert!');
                        
                        // Update features display if on edit page
                        if ($('.cbd-features-info').length) {
                            location.reload();
                        }
                        
                        setTimeout(function() {
                            CBDFeatures.closeModal();
                            $saveBtn.text(originalText).prop('disabled', false);
                        }, 1500);
                    } else {
                        alert('Fehler beim Speichern: ' + (response.data || 'Unbekannter Fehler'));
                        $saveBtn.text(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX Fehler:', error);
                    alert('Fehler beim Speichern der Features.');
                    $saveBtn.text(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Reset features
         */
        resetFeatures: function() {
            if (DEBUG) console.log('🔄 Setze Features zurück');
            
            $('#cbd-features-form')[0].reset();
            $('.cbd-feature-settings').hide();
            $('.cbd-current-icon span').attr('class', 'dashicons dashicons-admin-generic');
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Warte kurz um sicherzustellen dass alle Scripts geladen sind
        setTimeout(function() {
            if (DEBUG) console.log('📄 DOM bereit, initialisiere CBDFeatures...');
            CBDFeatures.init();
        }, 100);
    });
    
    // Fallback: Initialize on window load
    $(window).on('load', function() {
        if (!CBDFeatures.currentBlockId) {
            if (DEBUG) console.log('🪟 Window geladen, initialisiere CBDFeatures als Fallback...');
            CBDFeatures.init();
        }
    });
    
})(jQuery);