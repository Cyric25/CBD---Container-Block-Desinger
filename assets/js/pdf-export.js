/**
 * PDF Export functionality for Container Block Designer
 * Simple, clean implementation
 */

jQuery(document).ready(function($) {
    console.log("CBD: PDF Export script loading...");
    
    // Check if there are container blocks on the page
    var containerBlocks = $(".cbd-container");
    console.log("CBD: Found " + containerBlocks.length + " container blocks");
    
    if (containerBlocks.length > 0) {
        addPDFExportButton();
    }
    
    function addPDFExportButton() {
        console.log("CBD: Adding PDF export button");
        
        // Create simple floating button
        var button = $('<div id="cbd-pdf-export-fab">📄 PDF</div>');
        button.css({
            position: 'fixed',
            bottom: '30px',
            right: '30px',
            zIndex: '999999',
            background: '#0073aa',
            color: 'white',
            borderRadius: '12px',
            padding: '15px',
            cursor: 'pointer',
            boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
            fontSize: '14px',
            fontWeight: 'bold',
            textAlign: 'center',
            minWidth: '80px'
        });
        
        button.attr('title', 'Container-Blöcke als PDF exportieren');
        
        // Hover effect
        button.hover(
            function() { $(this).css('transform', 'scale(1.05)'); },
            function() { $(this).css('transform', 'scale(1)'); }
        );
        
        // Click handler
        button.on('click', function() {
            console.log("CBD: PDF Export clicked");
            showPDFModal();
        });
        
        $('body').append(button);
        console.log("CBD: PDF button added successfully");
    }
    
    function showPDFModal() {
        alert("PDF Export Modal\n\nDiese Funktionalität wird implementiert:\n\n• Block-Auswahl mit Checkboxen\n• Text-Export (schwarz-weiß)\n• Visueller Export (mit Farben)\n• Automatischer Download\n\nKlicken Sie OK um fortzufahren...");
        
        // Simple test PDF generation
        if (typeof window.jsPDF !== 'undefined') {
            generateSimplePDF();
        } else if (typeof jsPDF !== 'undefined') {
            generateSimplePDF();
        } else {
            console.log("CBD: jsPDF not loaded, checking global...");
            // Try to wait for library to load
            setTimeout(function() {
                if (typeof window.jsPDF !== 'undefined' || typeof jsPDF !== 'undefined') {
                    generateSimplePDF();
                } else {
                    console.log("CBD: jsPDF still not available");
                    alert("PDF-Library nicht verfügbar. Bitte Seite neu laden.");
                }
            }, 1000);
        }
    }
    
    function generateSimplePDF() {
        try {
            console.log("CBD: Generating simple test PDF");
            
            // Try different ways to access jsPDF
            var jsPDFConstructor;
            if (typeof window.jsPDF !== 'undefined') {
                jsPDFConstructor = window.jsPDF.jsPDF || window.jsPDF;
            } else if (typeof jsPDF !== 'undefined') {
                jsPDFConstructor = jsPDF;
            } else {
                throw new Error('jsPDF not found');
            }
            
            const pdf = new jsPDFConstructor();
            
            // Add title
            pdf.setFontSize(20);
            pdf.text("Container Blöcke Export", 20, 30);
            
            pdf.setFontSize(12);
            pdf.text("Exportiert am: " + new Date().toLocaleDateString('de-DE'), 20, 50);
            
            var y = 70;
            var containerBlocks = $(".cbd-container");
            
            containerBlocks.each(function(index) {
                if (y > 250) {
                    pdf.addPage();
                    y = 30;
                }
                
                var blockTitle = $(this).find('.cbd-block-title').text() || 'Block ' + (index + 1);
                var blockContent = $(this).find('.cbd-container-content').text().substring(0, 200);
                
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
            
            // Save PDF
            var filename = 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf';
            pdf.save(filename);
            
            console.log("CBD: PDF generated: " + filename);
            
        } catch (error) {
            console.error("CBD: PDF generation failed:", error);
            alert("Fehler beim PDF erstellen: " + error.message);
        }
    }
});