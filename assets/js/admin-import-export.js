/**
 * Container Block Designer - Import/Export JavaScript
 * Version: 2.6.0
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Import/Export functionality
     */
    const CBDImportExport = {
        
        /**
         * Initialize import/export features
         */
        init: function() {
            this.initExport();
            this.initImport();
            this.initFileUpload();
        },

        /**
         * Initialize export functionality
         */
        initExport: function() {
            // Select all blocks checkbox
            $(document).on('change', '#select-all-blocks', function() {
                const isChecked = $(this).prop('checked');
                $('.block-selection input[type="checkbox"]').prop('checked', isChecked);
                CBDImportExport.updateExportButton();
            });

            // Individual block checkboxes
            $(document).on('change', '.block-selection input[type="checkbox"]', function() {
                CBDImportExport.updateSelectAll();
                CBDImportExport.updateExportButton();
            });

            // Export form submission
            $(document).on('submit', '#cbd-export-form', function(e) {
                e.preventDefault();
                CBDImportExport.performExport();
            });

            // Export format change
            $(document).on('change', '#export-format', function() {
                CBDImportExport.updateExportOptions();
            });
        },

        /**
         * Initialize import functionality
         */
        initImport: function() {
            // Import form submission
            $(document).on('submit', '#cbd-import-form', function(e) {
                e.preventDefault();
                CBDImportExport.performImport();
            });

            // Import mode change
            $(document).on('change', 'input[name="import_mode"]', function() {
                CBDImportExport.updateImportOptions();
            });
        },

        /**
         * Initialize file upload handling
         */
        initFileUpload: function() {
            // File input change
            $(document).on('change', '#import-file', function() {
                CBDImportExport.validateImportFile(this);
            });

            // Drag and drop functionality
            const dropZone = $('.cbd-drop-zone');
            if (dropZone.length > 0) {
                this.initDragDrop(dropZone);
            }
        },

        /**
         * Initialize drag and drop
         */
        initDragDrop: function(dropZone) {
            dropZone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });

            dropZone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });

            dropZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');

                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    const fileInput = $('#import-file')[0];
                    fileInput.files = files;
                    CBDImportExport.validateImportFile(fileInput);
                }
            });
        },

        /**
         * Update select all checkbox state
         */
        updateSelectAll: function() {
            const totalBoxes = $('.block-selection input[type="checkbox"]').length;
            const checkedBoxes = $('.block-selection input[type="checkbox"]:checked').length;
            const selectAll = $('#select-all-blocks');

            if (checkedBoxes === 0) {
                selectAll.prop('checked', false).prop('indeterminate', false);
            } else if (checkedBoxes === totalBoxes) {
                selectAll.prop('checked', true).prop('indeterminate', false);
            } else {
                selectAll.prop('checked', false).prop('indeterminate', true);
            }
        },

        /**
         * Update export button state
         */
        updateExportButton: function() {
            const selectedBlocks = $('.block-selection input[type="checkbox"]:checked').length;
            const exportButton = $('#export-button');
            
            if (selectedBlocks > 0) {
                exportButton.prop('disabled', false).removeClass('disabled');
                $('#selected-count').text(selectedBlocks);
            } else {
                exportButton.prop('disabled', true).addClass('disabled');
                $('#selected-count').text(0);
            }
        },

        /**
         * Update export options based on format
         */
        updateExportOptions: function() {
            const format = $('#export-format').val();
            const options = $('.export-format-options');
            
            options.hide();
            $('#export-options-' + format).show();
        },

        /**
         * Update import options based on mode
         */
        updateImportOptions: function() {
            const mode = $('input[name="import_mode"]:checked').val();
            const options = $('.import-mode-options');
            
            options.hide();
            $('#import-options-' + mode).show();
        },

        /**
         * Validate import file
         */
        validateImportFile: function(input) {
            const file = input.files[0];
            const feedback = $('#file-feedback');
            const importButton = $('#import-button');
            
            if (!file) {
                feedback.text('').removeClass('error success');
                importButton.prop('disabled', true);
                return;
            }

            // Check file type
            const allowedTypes = ['application/json', 'text/json', 'application/zip'];
            const fileName = file.name.toLowerCase();
            
            if (!allowedTypes.includes(file.type) && 
                !fileName.endsWith('.json') && 
                !fileName.endsWith('.zip')) {
                feedback.text('Ungültiger Dateityp. Nur JSON und ZIP Dateien sind erlaubt.')
                       .removeClass('success').addClass('error');
                importButton.prop('disabled', true);
                return;
            }

            // Check file size (max 10MB)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                feedback.text('Datei ist zu groß. Maximale Größe: 10MB.')
                       .removeClass('success').addClass('error');
                importButton.prop('disabled', true);
                return;
            }

            feedback.text(`Datei bereit: ${file.name} (${CBDAdmin.common.formatBytes(file.size)})`)
                   .removeClass('error').addClass('success');
            importButton.prop('disabled', false);
        },

        /**
         * Perform export
         */
        performExport: function() {
            const form = $('#cbd-export-form');
            const selectedBlocks = $('.block-selection input[type="checkbox"]:checked');
            const exportButton = $('#export-button');
            const originalText = exportButton.text();

            if (selectedBlocks.length === 0) {
                CBDAdmin.common.showNotice('error', 'Bitte wählen Sie mindestens einen Block aus.');
                return;
            }

            // Show loading state
            exportButton.prop('disabled', true).text('Exportiere...');
            CBDAdmin.common.showLoading($('.cbd-export-section'));

            const blockIds = [];
            selectedBlocks.each(function() {
                blockIds.push($(this).val());
            });

            const exportData = {
                action: 'cbd_export_blocks',
                block_ids: blockIds,
                format: $('#export-format').val(),
                include_images: $('#include-images').prop('checked'),
                include_settings: $('#include-settings').prop('checked'),
                _wpnonce: cbdAdmin.nonce
            };

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: exportData,
                success: function(response) {
                    if (response.success) {
                        if (response.data.download_url) {
                            // Trigger download
                            const link = document.createElement('a');
                            link.href = response.data.download_url;
                            link.download = response.data.filename || 'cbd-export.json';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            
                            CBDAdmin.common.showNotice('success', 'Export erfolgreich erstellt.');
                        } else {
                            CBDAdmin.common.showNotice('error', 'Export-URL nicht gefunden.');
                        }
                    } else {
                        CBDAdmin.common.showNotice('error', response.data || 'Export fehlgeschlagen.');
                    }
                },
                error: function() {
                    CBDAdmin.common.showNotice('error', 'Netzwerkfehler beim Export.');
                },
                complete: function() {
                    exportButton.prop('disabled', false).text(originalText);
                    CBDAdmin.common.hideLoading($('.cbd-export-section'));
                }
            });
        },

        /**
         * Perform import
         */
        performImport: function() {
            const form = $('#cbd-import-form');
            const fileInput = $('#import-file')[0];
            const importButton = $('#import-button');
            const originalText = importButton.text();

            if (!fileInput.files[0]) {
                CBDAdmin.common.showNotice('error', 'Bitte wählen Sie eine Datei aus.');
                return;
            }

            // Show loading state
            importButton.prop('disabled', true).text('Importiere...');
            CBDAdmin.common.showLoading($('.cbd-import-section'));

            const formData = new FormData();
            formData.append('action', 'cbd_import_blocks');
            formData.append('import_file', fileInput.files[0]);
            formData.append('import_mode', $('input[name="import_mode"]:checked').val());
            formData.append('overwrite_existing', $('#overwrite-existing').prop('checked') ? '1' : '0');
            formData.append('update_references', $('#update-references').prop('checked') ? '1' : '0');
            formData.append('_wpnonce', cbdAdmin.nonce);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        CBDAdmin.common.showNotice('success', 
                            `Import erfolgreich: ${response.data.imported_count} Blöcke importiert.`
                        );
                        
                        // Show import summary
                        if (response.data.summary) {
                            CBDImportExport.showImportSummary(response.data.summary);
                        }

                        // Reset form
                        form[0].reset();
                        $('#file-feedback').text('').removeClass('error success');
                    } else {
                        CBDAdmin.common.showNotice('error', response.data || 'Import fehlgeschlagen.');
                    }
                },
                error: function() {
                    CBDAdmin.common.showNotice('error', 'Netzwerkfehler beim Import.');
                },
                complete: function() {
                    importButton.prop('disabled', true).text(originalText);
                    CBDAdmin.common.hideLoading($('.cbd-import-section'));
                }
            });
        },

        /**
         * Show import summary
         */
        showImportSummary: function(summary) {
            const modal = $('<div class="cbd-modal-overlay"><div class="cbd-modal"><div class="cbd-modal-header"><h3>Import Zusammenfassung</h3><button class="cbd-modal-close">&times;</button></div><div class="cbd-modal-content"></div><div class="cbd-modal-footer"><button class="button cbd-modal-close">Schließen</button></div></div></div>');
            
            let content = '<div class="import-summary">';
            
            if (summary.imported) {
                content += `<h4>Importierte Blöcke (${summary.imported.length})</h4><ul>`;
                summary.imported.forEach(block => {
                    content += `<li><strong>${block.name}</strong> - ${block.status}</li>`;
                });
                content += '</ul>';
            }
            
            if (summary.skipped && summary.skipped.length > 0) {
                content += `<h4>Übersprungene Blöcke (${summary.skipped.length})</h4><ul>`;
                summary.skipped.forEach(block => {
                    content += `<li><strong>${block.name}</strong> - ${block.reason}</li>`;
                });
                content += '</ul>';
            }
            
            if (summary.errors && summary.errors.length > 0) {
                content += `<h4>Fehler (${summary.errors.length})</h4><ul>`;
                summary.errors.forEach(error => {
                    content += `<li class="error">${error}</li>`;
                });
                content += '</ul>';
            }
            
            content += '</div>';
            
            modal.find('.cbd-modal-content').html(content);
            $('body').append(modal);
            
            // Close modal handlers
            modal.on('click', '.cbd-modal-close, .cbd-modal-overlay', function(e) {
                if (e.target === this) {
                    modal.remove();
                }
            });
        }
    };

    // Initialize
    CBDImportExport.init();
    
    // Export to global scope
    window.CBDImportExport = CBDImportExport;
});

// Add modal styles
jQuery(document).ready(function($) {
    if ($('#cbd-import-export-styles').length === 0) {
        $('<style id="cbd-import-export-styles">').text(`
            .cbd-drop-zone {
                border: 2px dashed #ccc;
                border-radius: 8px;
                padding: 40px;
                text-align: center;
                color: #666;
                cursor: pointer;
                transition: all 0.3s ease;
                margin: 15px 0;
            }
            
            .cbd-drop-zone:hover,
            .cbd-drop-zone.drag-over {
                border-color: #0073aa;
                background-color: #f0f8ff;
                color: #0073aa;
            }
            
            .cbd-drop-zone p {
                margin: 10px 0;
                font-size: 16px;
            }
            
            .cbd-drop-zone .upload-icon {
                font-size: 48px;
                margin-bottom: 15px;
                opacity: 0.5;
            }
            
            #file-feedback {
                margin-top: 10px;
                padding: 8px;
                border-radius: 4px;
                font-weight: 500;
            }
            
            #file-feedback.error {
                background-color: #ffeaea;
                color: #d63638;
                border: 1px solid #d63638;
            }
            
            #file-feedback.success {
                background-color: #eafaea;
                color: #00a32a;
                border: 1px solid #00a32a;
            }
            
            .import-summary h4 {
                color: #333;
                margin-top: 20px;
                margin-bottom: 10px;
            }
            
            .import-summary ul {
                margin: 0 0 15px 20px;
            }
            
            .import-summary li {
                margin-bottom: 5px;
                padding: 3px 0;
            }
            
            .import-summary li.error {
                color: #d63638;
            }
            
            .cbd-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .cbd-modal {
                background: white;
                border-radius: 8px;
                max-width: 600px;
                max-height: 80vh;
                overflow: hidden;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                animation: cbd-modal-in 0.3s ease-out;
            }
            
            .cbd-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #eee;
                background: #f8f9fa;
            }
            
            .cbd-modal-header h3 {
                margin: 0;
                color: #333;
            }
            
            .cbd-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .cbd-modal-content {
                padding: 20px;
                max-height: 400px;
                overflow-y: auto;
            }
            
            .cbd-modal-footer {
                padding: 15px 20px;
                border-top: 1px solid #eee;
                background: #f8f9fa;
                text-align: right;
            }
            
            @keyframes cbd-modal-in {
                from {
                    opacity: 0;
                    transform: scale(0.9) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
            
            .export-format-options,
            .import-mode-options {
                display: none;
                margin-top: 15px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            
            .export-format-options.active,
            .import-mode-options.active {
                display: block;
            }
        `).appendTo('head');
    }
});