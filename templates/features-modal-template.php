<?php
/**
 * Features Modal Template - VOLLSTÄNDIGE VERSION MIT ALLEN 5 FEATURES
 * 
 * @package ContainerBlockDesigner
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Features Configuration Modal mit ALLEN 5 Features -->
<div id="cbd-features-modal" class="cbd-modal" style="display: none;">
    <div class="cbd-modal-backdrop"></div>
    <div class="cbd-modal-content">
        <div class="cbd-modal-header">
            <h2 class="cbd-modal-title">Container-Features konfigurieren</h2>
            <button type="button" class="cbd-modal-close" aria-label="Schließen">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="cbd-modal-body">
            <form id="cbd-features-form">
                <input type="hidden" id="features-block-id" value="">
                
                <!-- FEATURE 1: Block-Icon -->
                <div class="cbd-feature-item">
                    <div class="cbd-feature-header">
                        <label class="cbd-feature-toggle">
                            <input type="checkbox" id="feature-icon-enabled">
                            <span class="cbd-toggle-slider"></span>
                        </label>
                        <div class="cbd-feature-info">
                            <strong>Block-Icon</strong>
                            <p>Zeigt ein Icon im Container-Header an</p>
                        </div>
                    </div>
                    <div class="cbd-feature-settings" id="feature-icon-settings" style="display: none;">
                        <label>Icon auswählen:</label>
                        <div class="cbd-icon-selector">
                            <div class="cbd-current-icon">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <input type="hidden" id="block-icon-value" value="dashicons-admin-generic">
                            </div>
                            <button type="button" class="button cbd-icon-picker-toggle">Icon wählen</button>
                        </div>
                        <div class="cbd-icon-picker" style="display: none;"></div>
                    </div>
                </div>
                
                <!-- FEATURE 2: Ein-/Ausklappbar -->
                <div class="cbd-feature-item">
                    <div class="cbd-feature-header">
                        <label class="cbd-feature-toggle">
                            <input type="checkbox" id="feature-collapse-enabled">
                            <span class="cbd-toggle-slider"></span>
                        </label>
                        <div class="cbd-feature-info">
                            <strong>Ein-/Ausklappbar</strong>
                            <p>Container kann auf- und zugeklappt werden</p>
                        </div>
                    </div>
                    <div class="cbd-feature-settings" id="feature-collapse-settings" style="display: none;">
                        <label>Standard-Zustand:</label>
                        <select id="collapse-default-state" class="regular-text">
                            <option value="expanded">Ausgeklappt</option>
                            <option value="collapsed">Eingeklappt</option>
                        </select>
                    </div>
                </div>
                
                <!-- FEATURE 3: Nummerierung -->
                <div class="cbd-feature-item">
                    <div class="cbd-feature-header">
                        <label class="cbd-feature-toggle">
                            <input type="checkbox" id="feature-numbering-enabled">
                            <span class="cbd-toggle-slider"></span>
                        </label>
                        <div class="cbd-feature-info">
                            <strong>Nummerierung</strong>
                            <p>Automatische Nummerierung der Container</p>
                        </div>
                    </div>
                    <div class="cbd-feature-settings" id="feature-numbering-settings" style="display: none;">
                        <label>Format:</label>
                        <select id="numbering-format" class="regular-text">
                            <option value="numeric">1, 2, 3...</option>
                            <option value="alpha">A, B, C...</option>
                            <option value="roman">I, II, III...</option>
                        </select>
                    </div>
                </div>
                
                <!-- FEATURE 4: Text kopieren -->
                <div class="cbd-feature-item">
                    <div class="cbd-feature-header">
                        <label class="cbd-feature-toggle">
                            <input type="checkbox" id="feature-copy-enabled">
                            <span class="cbd-toggle-slider"></span>
                        </label>
                        <div class="cbd-feature-info">
                            <strong>Text kopieren</strong>
                            <p>Button zum Kopieren des Container-Inhalts</p>
                        </div>
                    </div>
                    <div class="cbd-feature-settings" id="feature-copy-settings" style="display: none;">
                        <label>Button-Text:</label>
                        <input type="text" id="copy-button-text" value="Text kopieren" class="regular-text">
                    </div>
                </div>
                
                <!-- FEATURE 5: Screenshot -->
                <div class="cbd-feature-item">
                    <div class="cbd-feature-header">
                        <label class="cbd-feature-toggle">
                            <input type="checkbox" id="feature-screenshot-enabled">
                            <span class="cbd-toggle-slider"></span>
                        </label>
                        <div class="cbd-feature-info">
                            <strong>Screenshot</strong>
                            <p>Screenshot-Funktion für den Container</p>
                        </div>
                    </div>
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

<!-- Debug Script zur Überprüfung -->
<script>
jQuery(document).ready(function($) {
    console.log('✅ Features Modal Template geladen');
    console.log('Feature-Elemente gefunden:');
    console.log('- Icon:', $('#feature-icon-enabled').length > 0 ? '✅' : '❌');
    console.log('- Collapse:', $('#feature-collapse-enabled').length > 0 ? '✅' : '❌');
    console.log('- Numbering:', $('#feature-numbering-enabled').length > 0 ? '✅' : '❌');
    console.log('- Copy:', $('#feature-copy-enabled').length > 0 ? '✅' : '❌');
    console.log('- Screenshot:', $('#feature-screenshot-enabled').length > 0 ? '✅' : '❌');
    
    // Debug: Check if features are being saved
    $(document).on('click', '#cbd-save-features', function() {
        const blockId = $('#features-block-id').val();
        console.log('💾 Saving features for block ID:', blockId);
        
        if (!blockId || blockId === '' || blockId === '0') {
            console.error('❌ Keine gültige Block-ID!');
        }
    });
});
</script>