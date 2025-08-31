/**
 * Container Block Designer - Position Settings Admin JavaScript
 * Interaktive Funktionalität für Positionierungs-Einstellungen
 * Version: 2.4.0
 * 
 * Datei speichern als: assets/js/admin-position-settings.js
 */

(function($) {
    'use strict';
    
    /**
     * Position Settings Handler
     */
    class PositionSettingsHandler {
        constructor() {
            this.init();
            this.bindEvents();
        }
        
        /**
         * Initialisierung
         */
        init() {
            this.updateSelectOptions();
            this.updatePreviews();
            
            // Tooltip-System initialisieren
            this.initTooltips();
            
            console.log('✅ Position Settings Handler initialized');
        }
        
        /**
         * Event-Listener binden
         */
        bindEvents() {
            const self = this;
            
            // Platzierungs-Radio-Buttons
            $(document).on('change', 'input[name$="_placement"]', function() {
                const featureType = $(this).attr('name').replace('_placement', '');
                self.updateSelectOptions(featureType);
                self.updatePreview(featureType);
            });
            
            // Position-Select
            $(document).on('change', 'select[name$="_position"]', function() {
                const featureType = $(this).attr('name').replace('_position', '');
                self.updatePreview(featureType);
            });
            
            // Offset-Inputs
            $(document).on('input', 'input[name$="_offset_x"], input[name$="_offset_y"]', function() {
                const featureType = $(this).attr('name').split('_').slice(0, -2).join('_');
                self.debounce(() => self.updatePreview(featureType), 300)();
            });
            
            // Z-Index
            $(document).on('input', 'input[name$="_z_index"]', function() {
                const featureType = $(this).attr('name').replace('_z_index', '');
                self.updatePreview(featureType);
            });
            
            // Reset-Button für Positionen
            $(document).on('click', '.cbd-reset-position', function(e) {
                e.preventDefault();
                const featureType = $(this).data('feature');
                self.resetToDefaults(featureType);
            });
            
            // Live-Vorschau Toggle
            $(document).on('change', '.cbd-live-preview-toggle', function() {
                const enabled = $(this).is(':checked');
                self.toggleLivePreview(enabled);
            });
        }
        
        /**
         * Select-Optionen basierend auf Platzierung aktualisieren
         */
        updateSelectOptions(featureType = null) {
            const features = featureType ? [featureType] : ['icon', 'numbering'];
            
            features.forEach(feature => {
                const $placementInputs = $(`input[name="${feature}_placement"]`);
                const $select = $(`select[name="${feature}_position"]`);
                
                if ($placementInputs.length && $select.length) {
                    const selectedPlacement = $placementInputs.filter(':checked').val();
                    
                    // Alle Optgroups verstecken
                    $select.find('optgroup').hide();
                    
                    // Entsprechende Optgroup anzeigen
                    const $targetGroup = $select.find(`.position-group-${selectedPlacement}`);
                    $targetGroup.show();
                    
                    // Ersten sichtbaren Wert auswählen, falls aktueller Wert nicht sichtbar
                    const currentValue = $select.val();
                    const visibleOptions = $targetGroup.find('option');
                    
                    if (!visibleOptions.filter(`[value="${currentValue}"]`).length && visibleOptions.length) {
                        $select.val(visibleOptions.first().val());
                    }
                }
            });
        }
        
        /**
         * Vorschau für spezifisches Feature aktualisieren
         */
        updatePreview(featureType) {
            const settings = this.getFeatureSettings(featureType);
            const $preview = $(`#preview-${featureType} .${featureType}-preview`);
            
            if ($preview.length) {
                // CSS-Klassen aktualisieren
                const classes = this.generatePositionClasses(settings);
                $preview.attr('class', `cbd-preview-element ${featureType}-preview ${classes}`);
                
                // Inline-Styles aktualisieren
                const styles = this.generatePositionStyles(settings);
                $preview.attr('style', styles);
                
                // Animation für Änderung
                $preview.addClass('cbd-position-updating');
                setTimeout(() => {
                    $preview.removeClass('cbd-position-updating');
                }, 300);
            }
        }
        
        /**
         * Alle Vorschauen aktualisieren
         */
        updatePreviews() {
            ['icon', 'numbering'].forEach(feature => {
                if ($(`#position-settings-${feature}`).length) {
                    this.updatePreview(feature);
                }
            });
        }
        
        /**
         * Feature-Einstellungen aus Formular extrahieren
         */
        getFeatureSettings(featureType) {
            const settings = {
                placement: $(`input[name="${featureType}_placement"]:checked`).val() || 'inside',
                position: $(`select[name="${featureType}_position"]`).val() || 'top-left',
                offset_x: parseInt($(`input[name="${featureType}_offset_x"]`).val()) || 10,
                offset_y: parseInt($(`input[name="${featureType}_offset_y"]`).val()) || 10,
                z_index: parseInt($(`input[name="${featureType}_z_index"]`).val()) || 100
            };
            
            return settings;
        }
        
        /**
         * CSS-Klassen basierend auf Einstellungen generieren
         */
        generatePositionClasses(settings) {
            const classes = ['cbd-positioned'];
            classes.push(`cbd-${settings.placement}`);
            
            // Position ohne "outside-" Präfix
            const cleanPosition = settings.position.replace('outside-', '');
            classes.push(`cbd-${cleanPosition}`);
            
            return classes.join(' ');
        }
        
        /**
         * CSS-Styles basierend auf Einstellungen generieren
         */
        generatePositionStyles(settings) {
            const styles = [];
            const { placement, position, offset_x, offset_y, z_index } = settings;
            
            styles.push(`z-index: ${z_index}`);
            styles.push('position: absolute');
            
            if (placement === 'outside') {
                switch (position) {
                    case 'outside-top-left':
                        styles.push(`top: -${offset_y}px`);
                        styles.push(`left: -${offset_x}px`);
                        break;
                    case 'outside-top-center':
                        styles.push(`top: -${offset_y}px`);
                        styles.push('left: 50%');
                        styles.push('transform: translateX(-50%)');
                        break;
                    case 'outside-top-right':
                        styles.push(`top: -${offset_y}px`);
                        styles.push(`right: -${offset_x}px`);
                        break;
                    case 'outside-bottom-left':
                        styles.push(`bottom: -${offset_y}px`);
                        styles.push(`left: -${offset_x}px`);
                        break;
                    case 'outside-bottom-center':
                        styles.push(`bottom: -${offset_y}px`);
                        styles.push('left: 50%');
                        styles.push('transform: translateX(-50%)');
                        break;
                    case 'outside-bottom-right':
                        styles.push(`bottom: -${offset_y}px`);
                        styles.push(`right: -${offset_x}px`);
                        break;
                    case 'outside-left-middle':
                        styles.push(`left: -${offset_x}px`);
                        styles.push('top: 50%');
                        styles.push('transform: translateY(-50%)');
                        break;
                    case 'outside-right-middle':
                        styles.push(`right: -${offset_x}px`);
                        styles.push('top: 50%');
                        styles.push('transform: translateY(-50%)');
                        break;
                }
            } else {
                // Inside positioning
                const cleanPosition = position.replace('outside-', '');
                switch (cleanPosition) {
                    case 'top-left':
                        styles.push(`top: ${offset_y}px`);
                        styles.push(`left: ${offset_x}px`);
                        break;
                    case 'top-center':
                        styles.push(`top: ${offset_y}px`);
                        styles.push('left: 50%');
                        styles.push('transform: translateX(-50%)');
                        break;
                    case 'top-right':
                        styles.push(`top: ${offset_y}px`);
                        styles.push(`right: ${offset_x}px`);
                        break;
                    case 'middle-left':
                        styles.push(`left: ${offset_x}px`);
                        styles.push('top: 50%');
                        styles.push('transform: translateY(-50%)');
                        break;
                    case 'middle-center':
                        styles.push('top: 50%');
                        styles.push('left: 50%');
                        styles.push('transform: translate(-50%, -50%)');
                        break;
                    case 'middle-right':
                        styles.push(`right: ${offset_x}px`);
                        styles.push('top: 50%');
                        styles.push('transform: translateY(-50%)');
                        break;
                    case 'bottom-left':
                        styles.push(`bottom: ${offset_y}px`);
                        styles.push(`left: ${offset_x}px`);
                        break;
                    case 'bottom-center':
                        styles.push(`bottom: ${offset_y}px`);
                        styles.push('left: 50%');
                        styles.push('transform: translateX(-50%)');
                        break;
                    case 'bottom-right':
                        styles.push(`bottom: ${offset_y}px`);
                        styles.push(`right: ${offset_x}px`);
                        break;
                }
            }
            
            return styles.join('; ');
        }
        
        /**
         * Auf Standardwerte zurücksetzen
         */
        resetToDefaults(featureType) {
            const defaults = {
                placement: 'inside',
                position: 'top-left',
                offset_x: 10,
                offset_y: 10,
                z_index: 100
            };
            
            // Formularfelder aktualisieren
            $(`input[name="${featureType}_placement"][value="${defaults.placement}"]`).prop('checked', true);
            $(`select[name="${featureType}_position"]`).val(defaults.position);
            $(`input[name="${featureType}_offset_x"]`).val(defaults.offset_x);
            $(`input[name="${featureType}_offset_y"]`).val(defaults.offset_y);
            $(`input[name="${featureType}_z_index"]`).val(defaults.z_index);
            
            // UI aktualisieren
            this.updateSelectOptions(featureType);
            this.updatePreview(featureType);
            
            // Erfolgs-Toast
            this.showToast('Einstellungen zurückgesetzt', 'success');
        }
        
        /**
         * Live-Vorschau umschalten
         */
        toggleLivePreview(enabled) {
            if (enabled) {
                $('.cbd-preview-container').addClass('cbd-live-preview-active');
                this.showToast('Live-Vorschau aktiviert', 'info');
            } else {
                $('.cbd-preview-container').removeClass('cbd-live-preview-active');
                this.showToast('Live-Vorschau deaktiviert', 'info');
            }
        }
        
        /**
         * Tooltips initialisieren
         */
        initTooltips() {
            // Einfache Tooltip-Implementierung
            $(document).on('mouseenter', '[data-tooltip]', function() {
                const $this = $(this);
                const tooltipText = $this.data('tooltip');
                
                if (!tooltipText) return;
                
                const $tooltip = $('<div class="cbd-tooltip"></div>');
                $tooltip.text(tooltipText);
                $('body').append($tooltip);
                
                const rect = this.getBoundingClientRect();
                $tooltip.css({
                    position: 'absolute',
                    top: rect.top - $tooltip.outerHeight() - 5,
                    left: rect.left + (rect.width / 2) - ($tooltip.outerWidth() / 2),
                    zIndex: 9999
                });
                
                $tooltip.fadeIn(200);
            });
            
            $(document).on('mouseleave', '[data-tooltip]', function() {
                $('.cbd-tooltip').remove();
            });
        }
        
        /**
         * Toast-Nachrichten anzeigen
         */
        showToast(message, type = 'info') {
            const $toast = $(`
                <div class="cbd-toast cbd-toast-${type}">
                    <span class="cbd-toast-icon"></span>
                    <span class="cbd-toast-message">${message}</span>
                </div>
            `);
            
            $('body').append($toast);
            
            // Animation
            setTimeout(() => $toast.addClass('cbd-toast-show'), 100);
            
            // Auto-remove
            setTimeout(() => {
                $toast.removeClass('cbd-toast-show');
                setTimeout(() => $toast.remove(), 300);
            }, 3000);
        }
        
        /**
         * Debounce-Funktion
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        /**
         * Validierung der Eingaben
         */
        validateSettings(settings) {
            const errors = [];
            
            if (!['inside', 'outside'].includes(settings.placement)) {
                errors.push('Ungültige Platzierung');
            }
            
            if (settings.offset_x < -100 || settings.offset_x > 100) {
                errors.push('X-Offset muss zwischen -100 und 100 liegen');
            }
            
            if (settings.offset_y < -100 || settings.offset_y > 100) {
                errors.push('Y-Offset muss zwischen -100 und 100 liegen');
            }
            
            if (settings.z_index < 1 || settings.z_index > 9999) {
                errors.push('Z-Index muss zwischen 1 und 9999 liegen');
            }
            
            return errors;
        }
        
        /**
         * Einstellungen exportieren
         */
        exportSettings() {
            const settings = {};
            
            ['icon', 'numbering'].forEach(feature => {
                if ($(`#position-settings-${feature}`).length) {
                    settings[feature] = this.getFeatureSettings(feature);
                }
            });
            
            return settings;
        }
        
        /**
         * Einstellungen importieren
         */
        importSettings(settings) {
            Object.keys(settings).forEach(feature => {
                const featureSettings = settings[feature];
                
                // Formular füllen
                $(`input[name="${feature}_placement"][value="${featureSettings.placement}"]`).prop('checked', true);
                $(`select[name="${feature}_position"]`).val(featureSettings.position);
                $(`input[name="${feature}_offset_x"]`).val(featureSettings.offset_x);
                $(`input[name="${feature}_offset_y"]`).val(featureSettings.offset_y);
                $(`input[name="${feature}_z_index"]`).val(featureSettings.z_index);
                
                // UI aktualisieren
                this.updateSelectOptions(feature);
                this.updatePreview(feature);
            });
            
            this.showToast('Einstellungen importiert', 'success');
        }
    }
    
    /**
     * Initialisierung bei DOM-Ready
     */
    $(document).ready(function() {
        // Position Settings Handler starten
        window.cbdPositionSettings = new PositionSettingsHandler();
        
        // Global verfügbare Funktionen
        window.cbdExportPositionSettings = () => {
            return window.cbdPositionSettings.exportSettings();
        };
        
        window.cbdImportPositionSettings = (settings) => {
            window.cbdPositionSettings.importSettings(settings);
        };
        
        console.log('✅ Position Settings JS loaded successfully');
    });
    
})(jQuery);