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
                    
                    var containerBlocks = $(".cbd-container:visible");
                    console.log("CBD: Found " + containerBlocks.length + " visible container blocks");
                    
                    // Debug: Log all found containers
                    containerBlocks.each(function(index) {
                        console.log("CBD: Container " + (index + 1) + " - Classes: " + this.className + ", ID: " + this.id);
                        console.log("CBD: Container " + (index + 1) + " - Title: " + $(this).find('.cbd-block-title').text());
                    });
                    
                    if (containerBlocks.length === 0) {
                        alert("Keine sichtbaren Container-Blöcke zum Exportieren gefunden.");
                        return;
                    }
                    
                    // Use the global PDF export function loaded by jspdf-loader.js
                    if (typeof window.cbdPDFExport === 'function') {
                        console.log("CBD: Using cbdPDFExport function");
                        var success = window.cbdPDFExport(containerBlocks);
                        if (success) {
                            console.log("CBD: PDF export completed successfully");
                        }
                    } else {
                        console.log("CBD: cbdPDFExport not available, checking jsPDF directly...");
                        
                        // Fallback to direct jsPDF if the loader hasn't finished
                        if (typeof window.jsPDF !== 'undefined' || typeof jsPDF !== 'undefined') {
                            try {
                                var pdf;
                                if (window.jsPDF && window.jsPDF.jsPDF) {
                                    pdf = new window.jsPDF.jsPDF();
                                } else if (window.jsPDF) {
                                    pdf = new window.jsPDF();
                                } else {
                                    pdf = new jsPDF();
                                }
                                
                                // Generate PDF manually
                                pdf.setFontSize(20);
                                pdf.text("Container Blöcke Export", 20, 30);
                                
                                pdf.setFontSize(12);
                                pdf.text("Exportiert am: " + new Date().toLocaleDateString("de-DE"), 20, 50);
                                
                                var y = 70;
                                containerBlocks.each(function(index) {
                                    if (y > 250) {
                                        pdf.addPage();
                                        y = 30;
                                    }
                                    
                                    var blockTitle = $(this).find(".cbd-block-title").text() || "Block " + (index + 1);
                                    var blockContent = $(this).find(".cbd-container-content").text().substring(0, 200);
                                    
                                    pdf.setFontSize(14);
                                    pdf.text("Block " + (index + 1) + ": " + blockTitle, 20, y);
                                    y += 10;
                                    
                                    pdf.setFontSize(10);
                                    var lines = pdf.splitTextToSize(blockContent, 170);
                                    for (var i = 0; i < lines.length && i < 5; i++) {
                                        pdf.text(lines[i], 20, y);
                                        y += 6;
                                    }
                                    
                                    y += 15;
                                });
                                
                                var filename = "container-blocks-" + new Date().toISOString().slice(0, 10) + ".pdf";
                                pdf.save(filename);
                                
                                console.log("CBD: PDF saved via fallback: " + filename);
                                
                            } catch (error) {
                                console.error("CBD: PDF fallback failed:", error);
                                alert("PDF-Export fehlgeschlagen. Bitte versuchen Sie es erneut oder laden Sie die Seite neu.");
                            }
                        } else {
                            console.log("CBD: No PDF export method available");
                            alert("PDF-Export nicht verfügbar. Bitte laden Sie die Seite neu und versuchen Sie es erneut.");
                        }
                    }
                });
                $("body").append(pdfButton);
                console.log("CBD: PDF button added");
            }
        }
    });
}