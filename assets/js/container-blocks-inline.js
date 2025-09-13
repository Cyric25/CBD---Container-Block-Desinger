/**
 * Container Blocks Inline Functionality
 * Loaded directly with each container block to ensure functionality
 */

if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function($) {
        console.log("CBD: Container blocks JavaScript loading...");
        
        // Remove old event handlers to prevent duplicates
        $(document).off("click", ".cbd-collapse-toggle, .cbd-copy-text, .cbd-screenshot");
        
        // Toggle functionality
        $(document).on("click", ".cbd-collapse-toggle", function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log("CBD: Toggle clicked");
            
            var button = $(this);
            var container = button.closest(".cbd-container");
            var content = container.find(".cbd-container-content");
            var icon = button.find(".dashicons");
            
            if (content.length > 0) {
                if (content.is(":visible")) {
                    content.slideUp(300);
                    icon.removeClass("dashicons-arrow-up-alt2").addClass("dashicons-arrow-down-alt2");
                    console.log("CBD: Content collapsed");
                } else {
                    content.slideDown(300);
                    icon.removeClass("dashicons-arrow-down-alt2").addClass("dashicons-arrow-up-alt2");
                    console.log("CBD: Content expanded");
                }
            }
        });
        
        // Copy functionality
        $(document).on("click", ".cbd-copy-text", function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log("CBD: Copy clicked");
            
            var button = $(this);
            var container = button.closest(".cbd-container");
            var content = container.find(".cbd-container-content");
            
            if (content.length > 0) {
                var textToCopy = content.text().trim();
                console.log("CBD: Text to copy:", textToCopy.substring(0, 50) + "...");
                
                // Try multiple copy methods for better compatibility
                if (navigator.clipboard && window.isSecureContext) {
                    // Modern Clipboard API
                    navigator.clipboard.writeText(textToCopy).then(function() {
                        showCopySuccess(button);
                    }).catch(function(error) {
                        console.log("CBD: Modern clipboard failed, trying fallback:", error);
                        fallbackCopy(textToCopy, button);
                    });
                } else {
                    // Fallback for older browsers or non-HTTPS
                    fallbackCopy(textToCopy, button);
                }
            }
            
            function showCopySuccess(button) {
                button.find(".dashicons").removeClass("dashicons-clipboard").addClass("dashicons-yes-alt");
                console.log("CBD: Copy successful");
                setTimeout(function() { 
                    button.find(".dashicons").removeClass("dashicons-yes-alt").addClass("dashicons-clipboard"); 
                }, 2000);
            }
            
            function fallbackCopy(text, button) {
                // Create temporary textarea for fallback copy
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                
                try {
                    textarea.focus();
                    textarea.select();
                    var successful = document.execCommand('copy');
                    if (successful) {
                        showCopySuccess(button);
                    } else {
                        console.log("CBD: Fallback copy failed");
                        alert('Text kopiert: ' + text.substring(0, 100) + '...');
                    }
                } catch (err) {
                    console.log("CBD: Copy error:", err);
                    alert('Text kopiert: ' + text.substring(0, 100) + '...');
                } finally {
                    document.body.removeChild(textarea);
                }
            }
        });
        
        // Screenshot functionality  
        $(document).on("click", ".cbd-screenshot", function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log("CBD: Screenshot clicked");
            
            var button = $(this);
            var container = button.closest(".cbd-container");
            var content = container.find(".cbd-container-content");
            
            // Expand if collapsed before screenshot
            var wasCollapsed = !content.is(":visible");
            if (wasCollapsed) {
                content.show();
            }
            
            // Load html2canvas if not available
            if (typeof html2canvas === "undefined") {
                var script = document.createElement("script");
                script.src = "https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js";
                script.onload = function() {
                    console.log("CBD: html2canvas loaded dynamically");
                    takeScreenshot();
                };
                document.head.appendChild(script);
            } else {
                takeScreenshot();
            }
            
            function takeScreenshot() {
                button.find(".dashicons").removeClass("dashicons-camera").addClass("dashicons-update-alt");
                
                html2canvas(container[0], {
                    useCORS: true,
                    allowTaint: false,
                    scale: 1.5, // Good resolution without issues
                    logging: true, // Enable logging for debugging
                    backgroundColor: 'white',
                    onclone: function(clonedDoc) {
                        console.log("CBD: html2canvas cloning document");
                        // Ensure content is visible in clone
                        var clonedContainer = clonedDoc.querySelector('.cbd-container');
                        if (clonedContainer) {
                            var content = clonedContainer.querySelector('.cbd-container-content');
                            if (content) {
                                content.style.display = 'block !important';
                                content.style.visibility = 'visible !important';
                                content.style.opacity = '1 !important';
                            }
                            // Ensure container has proper styling
                            clonedContainer.style.backgroundColor = clonedContainer.style.backgroundColor || 'white';
                        }
                    }
                }).then(function(canvas) {
                    console.log("CBD: html2canvas success, canvas size:", canvas.width + "x" + canvas.height);
                    var link = document.createElement("a");
                    link.download = "container-block-screenshot.png";
                    link.href = canvas.toDataURL("image/png");
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    button.find(".dashicons").removeClass("dashicons-update-alt").addClass("dashicons-yes-alt");
                    console.log("CBD: Screenshot created");
                    
                    setTimeout(function() { 
                        button.find(".dashicons").removeClass("dashicons-yes-alt").addClass("dashicons-camera"); 
                    }, 2000);
                    
                    // Collapse again if it was collapsed
                    if (wasCollapsed) {
                        content.hide();
                    }
                }).catch(function(error) {
                    console.error("CBD: Screenshot failed:", error);
                    button.find(".dashicons").removeClass("dashicons-update-alt").addClass("dashicons-camera");
                });
            }
        });
        
        console.log("CBD: Container functionality loaded successfully");
        
        // Add PDF Export button if there are container blocks
        var totalContainers = $(".cbd-container");
        console.log("CBD: Found " + totalContainers.length + " total .cbd-container elements on page");
        totalContainers.each(function(i) {
            console.log("CBD: Container " + (i+1) + " - Class: " + this.className + ", ID: " + this.id + ", Visible: " + $(this).is(':visible'));
        });
        
        if (totalContainers.length > 0) {
            if ($("#cbd-pdf-export-fab").length === 0) {
                var pdfButton = $('<div id="cbd-pdf-export-fab">📄 PDF</div>');
                pdfButton.css({
                    position: "fixed",
                    bottom: "30px",
                    right: "30px",
                    zIndex: "999999",
                    background: "#0073aa",
                    color: "white",
                    borderRadius: "12px",
                    padding: "15px",
                    cursor: "pointer",
                    boxShadow: "0 4px 12px rgba(0,0,0,0.3)",
                    fontSize: "14px",
                    fontWeight: "bold",
                    textAlign: "center",
                    minWidth: "80px"
                });
                pdfButton.attr("title", "Container-Blöcke als PDF exportieren");
                pdfButton.hover(
                    function() { $(this).css("transform", "scale(1.05)"); },
                    function() { $(this).css("transform", "scale(1)"); }
                );
                pdfButton.on("click", function() {
                    console.log("CBD: PDF Export clicked");
                    
                    // Filter out empty Gutenberg containers and only include containers with actual content
                    var containerBlocks = $(".cbd-container:visible").filter(function() {
                        var $this = $(this);
                        var hasTitle = $this.find('.cbd-block-title').text().trim().length > 0;
                        var hasContent = $this.find('.cbd-container-content').text().trim().length > 0;
                        var hasId = this.id && this.id.length > 0;
                        
                        // Include only containers that have either title, content, or a proper ID
                        return hasTitle || hasContent || hasId;
                    });
                    
                    console.log("CBD: Found " + containerBlocks.length + " container blocks with content");
                    
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
                    
                    var modalHtml = '<div id="cbd-pdf-modal" style="' +
                        'position: fixed; top: 0; left: 0; width: 100%; height: 100%; ' +
                        'background: rgba(0,0,0,0.7); z-index: 999999; display: flex; ' +
                        'align-items: center; justify-content: center;">' +
                        '<div style="background: white; border-radius: 12px; padding: 30px; ' +
                        'max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; ' +
                        'box-shadow: 0 20px 40px rgba(0,0,0,0.3);">' +
                        '<h2 style="margin: 0 0 20px 0; color: #333; font-size: 24px;">📄 PDF Export Optionen</h2>' +
                        '<div style="margin-bottom: 20px;">' +
                            '<h3 style="margin: 0 0 10px 0; color: #555; font-size: 16px;">Container auswählen:</h3>' +
                            '<div id="cbd-block-selection" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 6px;">';
                    
                    // Add checkboxes for each container
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
                            '<h3 style="margin: 0 0 10px 0; color: #555; font-size: 16px;">Export Optionen:</h3>' +
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
                                '<span>📝 Nur Text (kleiste Dateigröße)</span>' +
                                '</label>' +
                            '</div>' +
                        '</div>' +
                        '<div style="margin-bottom: 20px;">' +
                            '<h3 style="margin: 0 0 10px 0; color: #555; font-size: 16px;">Qualität:</h3>' +
                            '<select id="cbd-quality-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">' +
                                '<option value="1">Niedrig (schnell, kleine Datei)</option>' +
                                '<option value="1.5" selected>Standard</option>' +
                                '<option value="2">Hoch (langsam, große Datei)</option>' +
                                '<option value="2.5">Sehr hoch (sehr langsam)</option>' +
                            '</select>' +
                        '</div>' +
                        '<div style="display: flex; gap: 10px; justify-content: flex-end;">' +
                            '<button id="cbd-pdf-cancel" style="padding: 10px 20px; border: 1px solid #ddd; ' +
                            'background: #f5f5f5; border-radius: 6px; cursor: pointer;">Abbrechen</button>' +
                            '<button id="cbd-pdf-create" style="padding: 10px 20px; border: none; ' +
                            'background: #0073aa; color: white; border-radius: 6px; cursor: pointer; ' +
                            'font-weight: bold;">📄 PDF erstellen</button>' +
                        '</div>' +
                        '</div></div>';
                    
                    $('body').append(modalHtml);
                    
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
                        
                        // Get selected blocks
                        $('#cbd-block-selection input[type="checkbox"]:checked').each(function() {
                            var index = parseInt($(this).data('block-index'));
                            selectedBlocks.push($(containerBlocks[index]));
                        });
                        
                        if (selectedBlocks.length === 0) {
                            alert('Bitte wählen Sie mindestens einen Block aus.');
                            return;
                        }
                        
                        console.log('CBD: Creating PDF with', selectedBlocks.length, 'blocks, mode:', mode, 'quality:', quality);
                        
                        $('#cbd-pdf-modal').remove();
                        
                        // Create PDF with options
                        if (typeof window.cbdPDFExportWithOptions === 'function') {
                            window.cbdPDFExportWithOptions(selectedBlocks, mode, quality);
                        } else {
                            console.log('CBD: cbdPDFExportWithOptions not available, using fallback');
                            if (typeof window.cbdPDFExport === 'function') {
                                window.cbdPDFExport($(selectedBlocks));
                            }
                        }
                    });
                }
                $("body").append(pdfButton);
                console.log("CBD: PDF button added");
            }
        }
    });
}