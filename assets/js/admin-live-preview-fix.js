/**
 * Live Preview Fix für Container Block Designer
 * Verhindert, dass Template-Styles die Live-Preview überschreiben
 */

(function($) {
    'use strict';

    // Warte bis WordPress und alle Scripts geladen sind
    $(document).ready(function() {

        // Funktion zur Live-Preview-Aktualisierung (Backend-Admin)
        function updateAdminLivePreview() {
            // Suche nach verschiedenen Preview-Containern
            let $preview = $('#cbd-block-preview-content');
            if (!$preview.length) {
                $preview = $('#cbd-preview-block .cbd-preview-content');
            }
            if (!$preview.length) {
                $preview = $('.cbd-preview-content');
            }

            if (!$preview.length) {
                return;
            }


            // Sammle Styles aus Formular-Inputs
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
                    color: $('input[name="styles[text][color]"]').val() || '#333333'
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

            // Styles mit !important anwenden um Template-Styles zu überschreiben
            $preview.attr('style', css + ' position: relative; z-index: 1000;');

        }

        // Event-Listener für Admin-Formular-Änderungen
        $('input[name^="styles["], select[name^="styles["]').on('change keyup input', function() {
            updateAdminLivePreview();
        });

        // Update-Button Event
        $('#cbd-update-preview').on('click', function() {
            updateAdminLivePreview();
        });

        // Initiale Preview-Aktualisierung
        setTimeout(function() {
            updateAdminLivePreview();
        }, 500);

    });

    // Funktion für Gutenberg-Editor Live-Preview
    function initGutenbergLivePreview() {
        if (!window.wp || !window.wp.data) {
            return;
        }


        // Override der Template-Style-Anwendung
        if (window.applyRealStyles) {
            const originalApplyRealStyles = window.applyRealStyles;
            window.applyRealStyles = function(blockSlug, blockId) {
                // Keine Template-Styles anwenden - Live-Preview hat Priorität
                return;
            };
        }

        // Monitor für Block-Attribut-Änderungen
        const { subscribe, select } = window.wp.data;

        subscribe(() => {
            const selectedBlock = select('core/block-editor').getSelectedBlock();
            if (selectedBlock && selectedBlock.name && selectedBlock.name.includes('container-block-designer')) {

                // Prüfe ob es benutzerdefinierte Styles in den Attributen gibt
                if (selectedBlock.attributes && selectedBlock.attributes.customStyles) {
                    const customStyles = selectedBlock.attributes.customStyles;

                    // Wende Custom Styles an statt Template-Styles
                    applyCustomStylesToBlock(selectedBlock, customStyles);
                }
            }
        });
    }

    // Custom Styles auf Block anwenden
    function applyCustomStylesToBlock(block, customStyles) {
        const blockElement = document.querySelector(`[data-block="${block.clientId}"]`);
        if (!blockElement) {
            return;
        }

        // CSS aus Custom Styles generieren
        let css = '';
        if (customStyles.backgroundColor) {
            css += `background-color: ${customStyles.backgroundColor} !important;`;
        }
        if (customStyles.padding) {
            css += `padding: ${customStyles.padding} !important;`;
        }
        if (customStyles.border) {
            css += `border: ${customStyles.border} !important;`;
        }
        if (customStyles.borderRadius) {
            css += `border-radius: ${customStyles.borderRadius} !important;`;
        }

        blockElement.style.cssText += css;
    }

    // Versuche Gutenberg-Integration nach DOM-Load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGutenbergLivePreview);
    } else {
        initGutenbergLivePreview();
    }

    // Fallback: Versuche nach 2 Sekunden nochmal
    setTimeout(initGutenbergLivePreview, 2000);

})(jQuery);