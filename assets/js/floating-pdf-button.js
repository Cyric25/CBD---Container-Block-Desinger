/**
 * Container Block Designer - Floating PDF Export Button
 * Zeigt einen Button rechts unten an, wenn CBD-Blöcke auf der Seite sind
 *
 * @package ContainerBlockDesigner
 * @since 2.7.6
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Add PDF Export button if there are container blocks
        var totalContainers = $(".cbd-container");

        if (totalContainers.length > 0) {
            if ($("#cbd-pdf-export-fab").length === 0) {
                // Lese Theme-Farben aus CSS-Variablen
                var rootStyles = getComputedStyle(document.documentElement);
                var themeColor = rootStyles.getPropertyValue('--color-ui-surface').trim() || '#e24614';
                var themeColorDark = rootStyles.getPropertyValue('--color-ui-surface-dark').trim() || '#c93d12';

                // Fallback: Wenn CSS-Variablen nicht gesetzt sind, verwende Default-Werte
                if (!themeColor || themeColor === '') {
                    themeColor = '#e24614';
                }
                if (!themeColorDark || themeColorDark === '') {
                    themeColorDark = '#c93d12';
                }

                var pdfButton = $('<div id="cbd-pdf-export-fab">📄 PDF</div>');
                pdfButton.css({
                    position: "fixed",
                    bottom: "30px",
                    right: "30px",
                    zIndex: "999999",
                    background: themeColor,
                    color: "white",
                    borderRadius: "12px",
                    padding: "15px",
                    cursor: "pointer",
                    boxShadow: "0 4px 12px rgba(0,0,0,0.3)",
                    fontSize: "14px",
                    fontWeight: "bold",
                    textAlign: "center",
                    minWidth: "80px",
                    transition: "all 0.2s ease"
                });
                pdfButton.attr("title", "Container-Blöcke als PDF exportieren");
                pdfButton.hover(
                    function() {
                        $(this).css({
                            "transform": "scale(1.05)",
                            "background": themeColorDark
                        });
                    },
                    function() {
                        $(this).css({
                            "transform": "scale(1)",
                            "background": themeColor
                        });
                    }
                );
                pdfButton.on("click", function() {

                    // DEBUG: Log all containers first
                    console.log('[CBD PDF] Total visible containers:', $(".cbd-container:visible").length);

                    $(".cbd-container:visible").each(function(i) {
                        var $this = $(this);
                        var isInContent = $this.closest('.cbd-container-content').length > 0;
                        var title = $this.find('.cbd-block-title').first().text().trim();
                        console.log('[CBD PDF] Container', i + 1, ':', {
                            title: title || 'No title',
                            id: this.id,
                            isInsideContent: isInContent,
                            isTopLevel: !isInContent
                        });
                    });

                    // Filter out empty Gutenberg containers and only include containers with actual content
                    // PLUS: Filter out nested containers - only get top-level containers
                    var containerBlocks = $(".cbd-container:visible").filter(function() {
                        var $this = $(this);

                        // First check: Is this a top-level container?
                        // A container is nested if it's inside another container's CONTENT area
                        var isNested = $this.closest('.cbd-container-content').length > 0;
                        if (isNested) {
                            console.log('[CBD PDF] Filtering out nested container:', this.id);
                            return false; // Skip nested containers
                        }

                        // Second check: Does it have actual content?
                        var hasTitle = $this.find('.cbd-block-title').text().trim().length > 0;
                        var hasContent = $this.find('.cbd-container-content').text().trim().length > 0;
                        var hasId = this.id && this.id.length > 0;

                        // Include only top-level containers that have either title, content, or a proper ID
                        return hasTitle || hasContent || hasId;
                    });

                    console.log('[CBD PDF] After filtering, top-level containers:', containerBlocks.length);


                    if (containerBlocks.length === 0) {
                        alert("Keine sichtbaren Container-Blöcke zum Exportieren gefunden.");
                        return;
                    }

                    // Show PDF options modal instead of direct export
                    showPDFOptionsModal(containerBlocks);
                });

                // PDF Options Modal Function
                function showPDFOptionsModal(containerBlocks) {
                    // Remove existing modal if any
                    $('#cbd-pdf-modal').remove();

                    // Lese Theme-Farben aus CSS-Variablen (gleiche wie beim Button)
                    var rootStyles = getComputedStyle(document.documentElement);
                    var modalThemeColor = rootStyles.getPropertyValue('--color-ui-surface').trim() || themeColor || '#e24614';
                    var modalPrimaryText = rootStyles.getPropertyValue('--color-primary-text').trim() || '#333';
                    var modalBorderColor = rootStyles.getPropertyValue('--color-sidebar-border').trim() || '#e0e0e0';
                    var modalLightBg = rootStyles.getPropertyValue('--color-light-background').trim() || '#f8f9fa';

                    // containerBlocks already filtered to only include top-level containers

                    var modalHtml = '<div id="cbd-pdf-modal" style="' +
                        'position: fixed; top: 0; left: 0; width: 100%; height: 100%; ' +
                        'background: rgba(0,0,0,0.7); z-index: 999999; display: flex; ' +
                        'align-items: center; justify-content: center;">' +
                        '<div style="background: white; border-radius: 12px; padding: 30px; ' +
                        'max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; ' +
                        'box-shadow: 0 20px 40px rgba(0,0,0,0.3);">' +
                        '<h2 style="margin: 0 0 20px 0; color: ' + modalPrimaryText + '; font-size: 24px;">📄 PDF Export Optionen</h2>' +
                        '<div style="margin-bottom: 20px;">' +
                            '<h3 style="margin: 0 0 10px 0; color: ' + modalPrimaryText + '; font-size: 16px;">Container auswählen:</h3>' +
                            '<div id="cbd-block-selection" style="max-height: 200px; overflow-y: auto; border: 1px solid ' + modalBorderColor + '; padding: 10px; border-radius: 6px;">';

                    // Add checkboxes for each top-level container
                    containerBlocks.each(function(index) {
                        var $this = $(this);
                        var blockTitle = $this.find('.cbd-block-title').text() || 'Block ' + (index + 1);
                        var blockNumber = index + 1;
                        var blockId = this.id || 'block-' + blockNumber;

                        modalHtml += '<div style="margin-bottom: 8px;">' +
                            '<label style="display: flex; align-items: center; cursor: pointer;">' +
                            '<input type="checkbox" checked data-block-index="' + index + '" ' +
                            'style="margin-right: 8px; transform: scale(1.2);">' +
                            '<span style="font-weight: bold; margin-right: 8px;">' + blockNumber + '.</span>' +
                            '<span>' + blockTitle + '</span>' +
                            '</label>' +
                            '</div>';
                    });

                    modalHtml += '</div></div>' +
                        '<div style="margin-bottom: 20px;">' +
                            '<h3 style="margin: 0 0 10px 0; color: ' + modalPrimaryText + '; font-size: 16px;">Export Optionen:</h3>' +
                            '<div style="margin-bottom: 10px;">' +
                                '<label style="display: flex; align-items: center; cursor: pointer;">' +
                                '<input type="radio" name="pdf-mode" value="visual" checked ' +
                                'style="margin-right: 8px; transform: scale(1.2);">' +
                                '<span>🎨 Visuell (mit Farben und Styling)</span>' +
                                '</label>' +
                            '</div>' +
                            '<div style="margin-bottom: 10px;">' +
                                '<label style="display: flex; align-items: center; cursor: pointer;">' +
                                '<input type="radio" name="pdf-mode" value="print" ' +
                                'style="margin-right: 8px; transform: scale(1.2);">' +
                                '<span>🖨️ Druck-optimiert (transparenter Hintergrund)</span>' +
                                '</label>' +
                            '</div>' +
                            '<div style="margin-bottom: 10px;">' +
                                '<label style="display: flex; align-items: center; cursor: pointer;">' +
                                '<input type="radio" name="pdf-mode" value="text" ' +
                                'style="margin-right: 8px; transform: scale(1.2);">' +
                                '<span>📝 Nur Text (kleinste Dateigröße)</span>' +
                                '</label>' +
                            '</div>' +
                        '</div>' +
                        '<div style="margin-bottom: 20px;">' +
                            '<h3 style="margin: 0 0 10px 0; color: ' + modalPrimaryText + '; font-size: 16px;">Qualität:</h3>' +
                            '<select id="cbd-quality-select" style="width: 100%; padding: 8px; border: 1px solid ' + modalBorderColor + '; border-radius: 4px;">' +
                                '<option value="1">Niedrig (schnell, kleine Datei)</option>' +
                                '<option value="1.5" selected>Standard</option>' +
                                '<option value="2">Hoch (langsam, große Datei)</option>' +
                                '<option value="2.5">Sehr hoch (sehr langsam)</option>' +
                            '</select>' +
                        '</div>' +
                        '<div style="display: flex; gap: 10px; justify-content: flex-end;">' +
                            '<button id="cbd-pdf-cancel" style="padding: 10px 20px; border: 1px solid ' + modalBorderColor + '; ' +
                            'background: ' + modalLightBg + '; border-radius: 6px; cursor: pointer;">Abbrechen</button>' +
                            '<button id="cbd-pdf-create" style="padding: 10px 20px; border: none; ' +
                            'background: ' + modalThemeColor + '; color: white; border-radius: 6px; cursor: pointer; ' +
                            'font-weight: bold;">📄 PDF erstellen</button>' +
                        '</div>' +
                        '</div></div>';

                    $('body').append(modalHtml);

                    // Hover-Effekt für PDF erstellen Button
                    var modalThemeColorDark = rootStyles.getPropertyValue('--color-ui-surface-dark').trim() || themeColorDark || '#c93d12';
                    $('#cbd-pdf-create').hover(
                        function() {
                            $(this).css('background', modalThemeColorDark);
                        },
                        function() {
                            $(this).css('background', modalThemeColor);
                        }
                    );

                    // Modal event handlers
                    $('#cbd-pdf-cancel, #cbd-pdf-modal').on('click', function(e) {
                        if (e.target === this) {
                            $('#cbd-pdf-modal').remove();
                        }
                    });

                    $('#cbd-pdf-create').on('click', function() {
                        var selectedBlocks = [];
                        var mode = $('input[name="pdf-mode"]:checked').val();
                        var quality = parseFloat($('#cbd-quality-select').val());

                        // Get selected blocks (already filtered to top-level only)
                        $('#cbd-block-selection input[type="checkbox"]:checked').each(function() {
                            var index = parseInt($(this).data('block-index'));
                            selectedBlocks.push($(containerBlocks[index]));
                        });

                        if (selectedBlocks.length === 0) {
                            alert('Bitte wählen Sie mindestens einen Block aus.');
                            return;
                        }


                        $('#cbd-pdf-modal').remove();

                        // Create PDF with options - wait for PDF functions to be available
                        tryCreatePDF(selectedBlocks, mode, quality);
                    });
                }

                function tryCreatePDF(selectedBlocks, mode, quality, attempts) {
                    attempts = attempts || 0;

                    console.log('[CBD PDF Button] window.cbdPDFExportWithOptions exists:', typeof window.cbdPDFExportWithOptions);

                    if (typeof window.cbdPDFExportWithOptions === 'function') {
                        window.cbdPDFExportWithOptions(selectedBlocks, mode, quality);
                    } else if (typeof window.cbdPDFExport === 'function') {
                        window.cbdPDFExport(selectedBlocks);
                    } else if (attempts < 50) {
                        // PDF functions not available yet, wait and retry
                        setTimeout(function() {
                            tryCreatePDF(selectedBlocks, mode, quality, attempts + 1);
                        }, 300);
                    } else {
                        alert('PDF-Erstellung fehlgeschlagen: PDF-Bibliothek konnte nicht geladen werden.');
                    }
                }

                $("body").append(pdfButton);
            }
        }
    });

})(jQuery);
