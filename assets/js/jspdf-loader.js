/**
 * jsPDF Loader with multiple CDN fallbacks
 * Ensures jsPDF is always available for the Container Block Designer
 */

(function() {
    'use strict';
    
    // Check if jsPDF is already loaded
    if (typeof window.jsPDF !== 'undefined' || typeof jsPDF !== 'undefined') {
        console.log('CBD: jsPDF already loaded');
        return;
    }
    
    console.log('CBD: Loading jsPDF with fallback mechanism...');
    
    var cdnSources = [
        'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
        'https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js',
        'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js'
    ];
    
    var currentSourceIndex = 0;
    var maxRetries = cdnSources.length;
    
    function loadFromCDN() {
        if (currentSourceIndex >= maxRetries) {
            console.error('CBD: All jsPDF CDN sources failed');
            // Last resort - create a simple alert-based PDF export
            window.cbdPDFExport = function(containerBlocks) {
                var content = 'PDF Export (Text-Only)\n\n';
                var $ = window.jQuery || window.$;
                
                containerBlocks.each(function(index) {
                    var $this = $(this);
                    var title = $this.find('.cbd-block-title').text() || 'Block ' + (index + 1);
                    var text = $this.find('.cbd-container-content').text().trim();
                    content += 'Block ' + (index + 1) + ': ' + title + '\n';
                    content += text + '\n\n';
                });
                
                var blob = new Blob([content], { type: 'text/plain' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.txt';
                link.click();
                console.log('CBD: Text export fallback used');
            };
            return;
        }
        
        var script = document.createElement('script');
        script.src = cdnSources[currentSourceIndex];
        script.async = true;
        
        script.onload = function() {
            console.log('CBD: jsPDF loaded successfully from:', cdnSources[currentSourceIndex]);
            
            // Verify jsPDF is accessible
            setTimeout(function() {
                var testPdf = null;
                try {
                    if (window.jsPDF && window.jsPDF.jsPDF) {
                        testPdf = new window.jsPDF.jsPDF();
                    } else if (window.jsPDF) {
                        testPdf = new window.jsPDF();
                    } else if (window.jspdf && window.jspdf.jsPDF) {
                        testPdf = new window.jspdf.jsPDF();
                    } else if (window.jspdf) {
                        testPdf = new window.jspdf();
                    } else if (typeof jsPDF !== 'undefined') {
                        testPdf = new jsPDF();
                    } else if (typeof jspdf !== 'undefined') {
                        testPdf = new jspdf();
                    }
                    
                    if (testPdf) {
                        console.log('CBD: jsPDF instance test successful');
                        // Create enhanced export function with options
                        window.cbdPDFExportWithOptions = function(containerBlocks, mode, quality) {
                            return cbdCreatePDF(containerBlocks, mode || 'visual', quality || 1);
                        };
                        
                        // Create global export function (backward compatibility)
                        window.cbdPDFExport = function(containerBlocks) {
                            return cbdCreatePDF(containerBlocks, 'visual', 1);
                        };
                        
                        function cbdCreatePDF(containerBlocks, mode, quality) {
                            try {
                                var pdf;
                                if (window.jsPDF && window.jsPDF.jsPDF) {
                                    pdf = new window.jsPDF.jsPDF();
                                } else if (window.jsPDF) {
                                    pdf = new window.jsPDF();
                                } else if (window.jspdf && window.jspdf.jsPDF) {
                                    pdf = new window.jspdf.jsPDF();
                                } else if (window.jspdf) {
                                    pdf = new window.jspdf();
                                } else if (typeof jsPDF !== 'undefined') {
                                    pdf = new jsPDF();
                                } else {
                                    pdf = new jspdf();
                                }
                                
                                pdf.setFontSize(20);
                                pdf.text('Container Blöcke Export (' + mode.toUpperCase() + ')', 20, 30);
                                
                                pdf.setFontSize(12);
                                pdf.text('Exportiert am: ' + new Date().toLocaleDateString('de-DE'), 20, 50);
                                pdf.text('Qualität: ' + quality + 'x, Modus: ' + getModeDescription(mode), 20, 60);
                                
                                function getModeDescription(mode) {
                                    switch(mode) {
                                        case 'visual': return 'Visuell mit Farben';
                                        case 'print': return 'Druck-optimiert';
                                        case 'text': return 'Nur Text';
                                        default: return 'Standard';
                                    }
                                }
                                
                                var y = 80;
                                
                                // Use jQuery safely - check if it's available
                                var $ = window.jQuery || window.$;
                                if (!$ && containerBlocks && containerBlocks.each) {
                                    // containerBlocks is already a jQuery object
                                    $ = function(el) { return containerBlocks.constructor(el); };
                                }
                                
                                console.log('CBD: Processing ' + containerBlocks.length + ' container blocks for PDF');
                                
                                // Process containers one by one using html2canvas for images
                                var processedBlocks = 0;
                                
                                function processNextBlock() {
                                    if (processedBlocks >= containerBlocks.length) {
                                        // All blocks processed, save PDF
                                        var filename = 'container-blocks-' + new Date().toISOString().slice(0, 10) + '.pdf';
                                        pdf.save(filename);
                                        console.log('CBD: PDF saved successfully:', filename);
                                        return true;
                                    }
                                    
                                    var $currentBlock = $(containerBlocks[processedBlocks]);
                                    var blockTitle = $currentBlock.find('.cbd-block-title').text() || 'Block ' + (processedBlocks + 1);
                                    
                                    console.log('CBD: Processing Block ' + (processedBlocks + 1) + ' - Title: "' + blockTitle + '"');
                                    
                                    if (y > 200) {
                                        pdf.addPage();
                                        y = 30;
                                    }
                                    
                                    // Add block title
                                    pdf.setFontSize(14);
                                    pdf.text('Block ' + (processedBlocks + 1) + ': ' + blockTitle, 20, y);
                                    y += 15;
                                    
                                    // Process based on mode
                                    if (mode === 'text') {
                                        // Text-only mode
                                        addTextOnly();
                                    } else {
                                        // Visual or print mode - ALWAYS use visual rendering for these modes
                                        var images = $currentBlock.find('img');
                                        console.log('CBD: Block ' + (processedBlocks + 1) + ' - Images found:', images.length, 'Mode:', mode);
                                        
                                        // FORCE visual rendering for visual and print modes (ignore images check)
                                        if (mode === 'visual' || mode === 'print') {
                                            console.log('CBD: Block has visual content, using html2canvas (mode: ' + mode + ')');
                                            
                                            // Load html2canvas if needed
                                            if (typeof html2canvas === 'undefined') {
                                                console.log('CBD: html2canvas not found, loading from CDN...');
                                                var script = document.createElement('script');
                                                script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
                                                script.onload = function() {
                                                    console.log('CBD: html2canvas loaded from CDN, calling renderBlockWithImages()');
                                                    renderBlockWithImages();
                                                };
                                                script.onerror = function() {
                                                    console.error('CBD: Failed to load html2canvas from CDN');
                                                    addTextOnly(); // Fallback to text
                                                };
                                                document.head.appendChild(script);
                                            } else {
                                                console.log('CBD: html2canvas already available, calling renderBlockWithImages()');
                                                renderBlockWithImages();
                                            }
                                            
                                            // Function to find intelligent break points for large blocks
                                            function findIntelligentBreakPoints(blockElement, canvas, maxPageHeight, imgWidth, imgHeight) {
                                                console.log('CBD: Analyzing block for intelligent break points...');

                                                var breakPoints = [];
                                                var scale = canvas.height / imgHeight; // Canvas to PDF scale factor
                                                var maxCanvasHeight = maxPageHeight * scale;

                                                // Get all potential break point elements (including nested ones)
                                                var allElements = [];

                                                // Collect all breakable elements within the block (including nested)
                                                function collectBreakableElements(parent, depth) {
                                                    if (depth > 3) return; // Limit depth to avoid infinite recursion

                                                    var children = parent.children;
                                                    for (var i = 0; i < children.length; i++) {
                                                        var child = children[i];

                                                        try {
                                                            var rect = child.getBoundingClientRect();
                                                            var blockRect = blockElement.getBoundingClientRect();

                                                            var relativeTop = rect.top - blockRect.top;
                                                            var relativeBottom = rect.bottom - blockRect.top;

                                                            // Only consider elements with meaningful height and position
                                                            if (rect.height > 10 && relativeTop >= 0) {
                                                                allElements.push({
                                                                    element: child,
                                                                    top: relativeTop,
                                                                    bottom: relativeBottom,
                                                                    height: rect.height,
                                                                    tagName: child.tagName,
                                                                    className: child.className,
                                                                    isBreakable: isBreakableElement(child),
                                                                    depth: depth,
                                                                    priority: getBreakPriority(child)
                                                                });
                                                            }

                                                            // Recursively collect from children
                                                            if (child.children.length > 0) {
                                                                collectBreakableElements(child, depth + 1);
                                                            }
                                                        } catch (rectError) {
                                                            console.log('CBD: Could not get rect for element:', child.tagName);
                                                        }
                                                    }
                                                }

                                                collectBreakableElements(blockElement, 0);

                                                // Filter and sort elements by position
                                                var childElements = allElements
                                                    .filter(function(el) { return el.isBreakable || el.priority > 0; })
                                                    .sort(function(a, b) {
                                                        if (a.top === b.top) {
                                                            return b.priority - a.priority; // Higher priority first
                                                        }
                                                        return a.top - b.top;
                                                    });

                                                console.log('CBD: Found ' + childElements.length + ' child elements to analyze');

                                                // Sort children by their top position
                                                childElements.sort(function(a, b) { return a.top - b.top; });

                                                // Find natural break points
                                                var currentSectionStart = 0;
                                                var currentSectionHeight = 0;
                                                var currentSectionElements = [];

                                                for (var j = 0; j < childElements.length; j++) {
                                                    var element = childElements[j];
                                                    var elementCanvasTop = element.top * scale;
                                                    var elementCanvasBottom = element.bottom * scale;
                                                    var potentialSectionHeight = elementCanvasBottom - (currentSectionStart * scale);

                                                    // Check if adding this element would exceed page height
                                                    if (potentialSectionHeight > maxCanvasHeight && currentSectionElements.length > 0) {
                                                        // Create break point before this element
                                                        var breakPoint = createBreakPoint(
                                                            currentSectionStart,
                                                            element.top,
                                                            currentSectionElements,
                                                            scale,
                                                            imgWidth,
                                                            imgHeight
                                                        );

                                                        if (breakPoint) {
                                                            breakPoints.push(breakPoint);
                                                            console.log('CBD: Created break point at ' + element.top + 'px (avoiding ' + element.tagName + ')');
                                                        }

                                                        // Start new section
                                                        currentSectionStart = element.top;
                                                        currentSectionElements = [element];
                                                    } else {
                                                        // Add element to current section
                                                        currentSectionElements.push(element);
                                                    }
                                                }

                                                // Add final section
                                                if (currentSectionElements.length > 0) {
                                                    var finalBreakPoint = createBreakPoint(
                                                        currentSectionStart,
                                                        imgHeight / scale, // End of block
                                                        currentSectionElements,
                                                        scale,
                                                        imgWidth,
                                                        imgHeight
                                                    );

                                                    if (finalBreakPoint) {
                                                        breakPoints.push(finalBreakPoint);
                                                    }
                                                }

                                                // Fallback: if no intelligent breaks found, use safe mechanical splitting
                                                if (breakPoints.length === 0) {
                                                    console.log('CBD: No intelligent break points found, using safe mechanical splitting');
                                                    return createSafeMechanicalBreaks(canvas, maxPageHeight, imgWidth, imgHeight);
                                                }

                                                console.log('CBD: Created ' + breakPoints.length + ' intelligent break points');
                                                return breakPoints;
                                            }

                                            function isBreakableElement(element) {
                                                var breakableTypes = [
                                                    'DIV', 'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
                                                    'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'NAV',
                                                    'UL', 'OL', 'LI', 'BLOCKQUOTE', 'HR'
                                                ];

                                                // Avoid breaking within these elements
                                                var nonBreakableTypes = [
                                                    'IMG', 'SVG', 'CANVAS', 'VIDEO', 'AUDIO', 'IFRAME',
                                                    'TABLE', 'FORM', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA'
                                                ];

                                                if (nonBreakableTypes.indexOf(element.tagName) !== -1) {
                                                    return false;
                                                }

                                                return breakableTypes.indexOf(element.tagName) !== -1;
                                            }

                                            function getBreakPriority(element) {
                                                var tagName = element.tagName;
                                                var className = element.className || '';

                                                // High priority: Major structural breaks
                                                if (tagName === 'SECTION' || tagName === 'ARTICLE') return 100;
                                                if (tagName === 'HEADER' || tagName === 'FOOTER') return 95;
                                                if (className.indexOf('cbd-section') !== -1) return 90;

                                                // Medium-high priority: Headings (bigger headings = higher priority)
                                                if (tagName === 'H1') return 85;
                                                if (tagName === 'H2') return 80;
                                                if (tagName === 'H3') return 75;
                                                if (tagName === 'H4') return 70;
                                                if (tagName === 'H5') return 65;
                                                if (tagName === 'H6') return 60;

                                                // Medium priority: Block elements
                                                if (tagName === 'DIV' && className.indexOf('block') !== -1) return 55;
                                                if (tagName === 'BLOCKQUOTE') return 50;
                                                if (tagName === 'UL' || tagName === 'OL') return 45;
                                                if (tagName === 'HR') return 40;

                                                // Lower priority: Paragraphs and list items
                                                if (tagName === 'P') return 35;
                                                if (tagName === 'LI') return 30;
                                                if (tagName === 'DIV') return 25;

                                                // Very low priority: Non-breakable or inline elements
                                                var nonBreakableTypes = [
                                                    'IMG', 'SVG', 'CANVAS', 'VIDEO', 'AUDIO', 'IFRAME',
                                                    'TABLE', 'FORM', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA',
                                                    'SPAN', 'A', 'STRONG', 'EM', 'B', 'I'
                                                ];

                                                if (nonBreakableTypes.indexOf(tagName) !== -1) return 0;

                                                // Default for other elements
                                                return 20;
                                            }

                                            function createBreakPoint(startPos, endPos, elements, scale, imgWidth, imgHeight) {
                                                var heightInPx = endPos - startPos;
                                                var heightInPdf = heightInPx * (imgWidth / (imgHeight / scale));
                                                var canvasStart = startPos * scale;
                                                var canvasHeight = heightInPx * scale;

                                                // Ensure break point is not too small
                                                if (heightInPdf < 30) {
                                                    return null;
                                                }

                                                return {
                                                    start: startPos,
                                                    end: endPos,
                                                    height: heightInPdf,
                                                    canvasStart: canvasStart,
                                                    canvasHeight: canvasHeight,
                                                    elementsPreserved: elements.length
                                                };
                                            }

                                            function createSafeMechanicalBreaks(canvas, maxPageHeight, imgWidth, imgHeight) {
                                                console.log('CBD: Creating safe mechanical breaks as fallback');

                                                var breakPoints = [];
                                                var sectionsNeeded = Math.ceil(imgHeight / maxPageHeight);
                                                var sectionHeight = imgHeight / sectionsNeeded;
                                                var canvasSectionHeight = canvas.height / sectionsNeeded;

                                                // Ensure sections are not too small (minimum 50px height)
                                                if (sectionHeight < 50) {
                                                    sectionsNeeded = Math.ceil(imgHeight / 50);
                                                    sectionHeight = imgHeight / sectionsNeeded;
                                                    canvasSectionHeight = canvas.height / sectionsNeeded;
                                                }

                                                for (var i = 0; i < sectionsNeeded; i++) {
                                                    breakPoints.push({
                                                        start: i * sectionHeight,
                                                        end: (i + 1) * sectionHeight,
                                                        height: sectionHeight,
                                                        canvasStart: i * canvasSectionHeight,
                                                        canvasHeight: canvasSectionHeight,
                                                        elementsPreserved: 'mechanical'
                                                    });
                                                }

                                                return breakPoints;
                                            }

                                            // Function to fix interactive HTML elements for better rendering
                                            function fixInteractiveElements(clonedDoc) {
                                                console.log('CBD: Fixing interactive HTML elements for better rendering');

                                                // Fix input elements
                                                var inputs = clonedDoc.querySelectorAll('input, textarea, select');
                                                console.log('CBD: Found ' + inputs.length + ' input elements to fix');

                                                for (var i = 0; i < inputs.length; i++) {
                                                    var input = inputs[i];
                                                    var inputType = input.type || 'text';
                                                    var inputValue = input.value || input.innerHTML || '';

                                                    // Create replacement div
                                                    var replacement = clonedDoc.createElement('div');

                                                    // Safely copy computed styles
                                                    try {
                                                        var computedStyle = clonedDoc.defaultView.getComputedStyle(input);
                                                        if (computedStyle && computedStyle.cssText) {
                                                            replacement.style.cssText = computedStyle.cssText;
                                                        }
                                                    } catch (styleError) {
                                                        console.log('CBD: Could not copy computed styles, using manual styling');
                                                    }
                                                    replacement.style.border = input.style.border || '1px solid #ccc';
                                                    replacement.style.padding = input.style.padding || '5px';
                                                    replacement.style.backgroundColor = input.style.backgroundColor || '#fff';
                                                    replacement.style.color = input.style.color || '#000';
                                                    replacement.style.fontSize = input.style.fontSize || '14px';
                                                    replacement.style.display = 'inline-block';
                                                    replacement.style.minHeight = '20px';
                                                    replacement.style.verticalAlign = 'top';

                                                    // Handle different input types
                                                    if (inputType === 'checkbox') {
                                                        replacement.innerHTML = input.checked ? '☑' : '☐';
                                                        replacement.style.width = '20px';
                                                        replacement.style.height = '20px';
                                                        replacement.style.textAlign = 'center';
                                                        replacement.style.lineHeight = '20px';
                                                    } else if (inputType === 'radio') {
                                                        replacement.innerHTML = input.checked ? '◉' : '○';
                                                        replacement.style.width = '20px';
                                                        replacement.style.height = '20px';
                                                        replacement.style.textAlign = 'center';
                                                        replacement.style.lineHeight = '20px';
                                                    } else if (inputType === 'submit' || inputType === 'button') {
                                                        replacement.innerHTML = inputValue || input.getAttribute('value') || 'Button';
                                                        replacement.style.backgroundColor = '#007cba';
                                                        replacement.style.color = '#fff';
                                                        replacement.style.textAlign = 'center';
                                                        replacement.style.cursor = 'default';
                                                    } else if (input.tagName === 'SELECT') {
                                                        var selectedText = '';
                                                        if (input.selectedIndex >= 0 && input.options[input.selectedIndex]) {
                                                            selectedText = input.options[input.selectedIndex].text;
                                                        }
                                                        replacement.innerHTML = selectedText || 'Select...';
                                                        replacement.style.border = '1px solid #ccc';
                                                        replacement.style.paddingRight = '20px';
                                                        replacement.style.background = '#fff url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 4 5\'%3E%3Cpath fill=\'%23666\' d=\'M2 0L0 2h4zm0 5L0 3h4z\'/%3E%3C/svg%3E") no-repeat right 8px center';
                                                        replacement.style.backgroundSize = '8px';
                                                    } else if (input.tagName === 'TEXTAREA') {
                                                        replacement.innerHTML = inputValue.replace(/\n/g, '<br>') || 'Textarea content...';
                                                        replacement.style.whiteSpace = 'pre-wrap';
                                                        replacement.style.minHeight = input.style.height || '60px';
                                                        replacement.style.maxHeight = input.style.height || '200px';
                                                        replacement.style.overflow = 'hidden';
                                                    } else {
                                                        // Text, email, password, etc.
                                                        replacement.innerHTML = inputValue || input.getAttribute('placeholder') || '';
                                                        if (inputType === 'password' && inputValue) {
                                                            replacement.innerHTML = '●'.repeat(inputValue.length);
                                                        }
                                                    }

                                                    // Copy classes and id
                                                    if (input.className) replacement.className = input.className;
                                                    if (input.id) replacement.id = input.id + '_rendered';

                                                    // Replace in DOM
                                                    if (input.parentNode) {
                                                        input.parentNode.replaceChild(replacement, input);
                                                        console.log('CBD: Replaced ' + inputType + ' input with rendered div');
                                                    }
                                                }

                                                // Fix form elements that might not render properly
                                                var forms = clonedDoc.querySelectorAll('form');
                                                for (var j = 0; j < forms.length; j++) {
                                                    var form = forms[j];
                                                    form.style.display = 'block';
                                                    form.style.visibility = 'visible';
                                                }

                                                // Fix buttons
                                                var buttons = clonedDoc.querySelectorAll('button:not(.cbd-action-btn)'); // Exclude our action buttons
                                                for (var k = 0; k < buttons.length; k++) {
                                                    var button = buttons[k];
                                                    var buttonText = button.innerHTML || button.value || 'Button';

                                                    var buttonDiv = clonedDoc.createElement('div');
                                                    buttonDiv.innerHTML = buttonText;

                                                    // Safely copy computed styles
                                                    try {
                                                        var computedStyle = clonedDoc.defaultView.getComputedStyle(button);
                                                        if (computedStyle && computedStyle.cssText) {
                                                            buttonDiv.style.cssText = computedStyle.cssText;
                                                        }
                                                    } catch (styleError) {
                                                        console.log('CBD: Could not copy button styles, using manual styling');
                                                    }
                                                    buttonDiv.style.display = 'inline-block';
                                                    buttonDiv.style.backgroundColor = button.style.backgroundColor || '#007cba';
                                                    buttonDiv.style.color = button.style.color || '#fff';
                                                    buttonDiv.style.padding = button.style.padding || '8px 16px';
                                                    buttonDiv.style.border = button.style.border || '1px solid #005a87';
                                                    buttonDiv.style.borderRadius = button.style.borderRadius || '4px';
                                                    buttonDiv.style.textAlign = 'center';
                                                    buttonDiv.style.cursor = 'default';

                                                    if (button.className) buttonDiv.className = button.className;
                                                    if (button.id) buttonDiv.id = button.id + '_rendered';

                                                    if (button.parentNode) {
                                                        button.parentNode.replaceChild(buttonDiv, button);
                                                        console.log('CBD: Replaced button with rendered div');
                                                    }
                                                }

                                                // Fix iframe elements
                                                var iframes = clonedDoc.querySelectorAll('iframe');
                                                for (var l = 0; l < iframes.length; l++) {
                                                    var iframe = iframes[l];
                                                    var iframeReplacement = clonedDoc.createElement('div');
                                                    iframeReplacement.innerHTML = '[iframe: ' + (iframe.src || 'embedded content') + ']';

                                                    // Safely copy computed styles
                                                    try {
                                                        var computedStyle = clonedDoc.defaultView.getComputedStyle(iframe);
                                                        if (computedStyle && computedStyle.cssText) {
                                                            iframeReplacement.style.cssText = computedStyle.cssText;
                                                        }
                                                    } catch (styleError) {
                                                        console.log('CBD: Could not copy iframe styles, using manual styling');
                                                    }
                                                    iframeReplacement.style.backgroundColor = '#f0f0f0';
                                                    iframeReplacement.style.border = '2px dashed #ccc';
                                                    iframeReplacement.style.display = 'block';
                                                    iframeReplacement.style.textAlign = 'center';
                                                    iframeReplacement.style.padding = '20px';
                                                    iframeReplacement.style.color = '#666';

                                                    if (iframe.parentNode) {
                                                        iframe.parentNode.replaceChild(iframeReplacement, iframe);
                                                        console.log('CBD: Replaced iframe with placeholder');
                                                    }
                                                }

                                                // Fix video and audio elements
                                                var mediaElements = clonedDoc.querySelectorAll('video, audio');
                                                for (var m = 0; m < mediaElements.length; m++) {
                                                    var media = mediaElements[m];
                                                    var mediaReplacement = clonedDoc.createElement('div');
                                                    var mediaType = media.tagName.toLowerCase();
                                                    mediaReplacement.innerHTML = '[' + mediaType + ': ' + (media.src || media.currentSrc || 'media content') + ']';

                                                    // Safely copy computed styles
                                                    try {
                                                        var computedStyle = clonedDoc.defaultView.getComputedStyle(media);
                                                        if (computedStyle && computedStyle.cssText) {
                                                            mediaReplacement.style.cssText = computedStyle.cssText;
                                                        }
                                                    } catch (styleError) {
                                                        console.log('CBD: Could not copy media styles, using manual styling');
                                                    }
                                                    mediaReplacement.style.backgroundColor = '#f5f5f5';
                                                    mediaReplacement.style.border = '2px solid #ddd';
                                                    mediaReplacement.style.display = 'block';
                                                    mediaReplacement.style.textAlign = 'center';
                                                    mediaReplacement.style.padding = '20px';
                                                    mediaReplacement.style.color = '#333';

                                                    if (media.parentNode) {
                                                        media.parentNode.replaceChild(mediaReplacement, media);
                                                        console.log('CBD: Replaced ' + mediaType + ' with placeholder');
                                                    }
                                                }

                                                console.log('CBD: Interactive elements fixing completed');
                                            }

                                            function renderBlockWithImages() {
                                                console.log('CBD: renderBlockWithImages called with mode:', mode, 'quality:', quality);
                                                
                                                var canvasOptions = {
                                                    useCORS: true,
                                                    allowTaint: false,
                                                    scale: quality,
                                                    logging: true,
                                                    backgroundColor: 'white',
                                                    // Enhanced options for better HTML element rendering
                                                    foreignObjectRendering: true,
                                                    removeContainer: false,
                                                    imageTimeout: 5000,
                                                    ignoreElements: function(element) {
                                                        // Ignore certain problematic elements
                                                        if (element.tagName === 'SCRIPT' || element.tagName === 'NOSCRIPT') {
                                                            return true;
                                                        }
                                                        return false;
                                                    }
                                                };
                                                
                                                // Print mode - modify styles for print
                                                if (mode === 'print') {
                                                    console.log('CBD: Applying PRINT mode styling');
                                                    canvasOptions.backgroundColor = 'white';
                                                    canvasOptions.onclone = function(clonedDoc) {
                                                        console.log('CBD: Applying print-friendly styles with direct DOM manipulation');

                                                        // Fix interactive elements FIRST
                                                        fixInteractiveElements(clonedDoc);

                                                        // Direct DOM manipulation - more reliable than CSS
                                                        var elements = clonedDoc.querySelectorAll('*');
                                                        
                                                        for (var i = 0; i < elements.length; i++) {
                                                            var el = elements[i];
                                                            
                                                            // Force remove all backgrounds
                                                            el.style.setProperty('background', 'transparent', 'important');
                                                            el.style.setProperty('background-color', 'transparent', 'important');
                                                            el.style.setProperty('background-image', 'none', 'important');
                                                            el.style.setProperty('background-attachment', 'initial', 'important');
                                                            el.style.setProperty('background-position', 'initial', 'important');
                                                            el.style.setProperty('background-repeat', 'initial', 'important');
                                                            el.style.setProperty('background-size', 'initial', 'important');
                                                            
                                                            // Force remove all shadows and effects
                                                            el.style.setProperty('box-shadow', 'none', 'important');
                                                            el.style.setProperty('text-shadow', 'none', 'important');
                                                            el.style.setProperty('filter', 'none', 'important');
                                                            el.style.setProperty('backdrop-filter', 'none', 'important');
                                                            el.style.setProperty('-webkit-filter', 'none', 'important');
                                                            el.style.setProperty('-webkit-backdrop-filter', 'none', 'important');
                                                            
                                                            // Force text color to black
                                                            if (!el.matches('img, svg, canvas')) {
                                                                el.style.setProperty('color', '#000000', 'important');
                                                            }
                                                            
                                                            // AGGRESSIVE border removal - all border properties
                                                            el.style.setProperty('border', 'none', 'important');
                                                            el.style.setProperty('border-top', 'none', 'important');
                                                            el.style.setProperty('border-right', 'none', 'important');
                                                            el.style.setProperty('border-bottom', 'none', 'important');
                                                            el.style.setProperty('border-left', 'none', 'important');
                                                            el.style.setProperty('border-width', '0', 'important');
                                                            el.style.setProperty('border-style', 'none', 'important');
                                                            el.style.setProperty('border-color', 'transparent', 'important');
                                                            el.style.setProperty('outline', 'none', 'important');
                                                            el.style.setProperty('outline-width', '0', 'important');
                                                            el.style.setProperty('outline-style', 'none', 'important');
                                                            el.style.setProperty('outline-color', 'transparent', 'important');
                                                        }
                                                        
                                                        // Special handling for container - no borders in print mode
                                                        var containers = clonedDoc.querySelectorAll('.cbd-container');
                                                        for (var j = 0; j < containers.length; j++) {
                                                            var container = containers[j];
                                                            container.style.setProperty('background-color', 'white', 'important');
                                                            container.style.setProperty('background', 'white', 'important');
                                                            container.style.setProperty('border', 'none', 'important');
                                                            container.style.setProperty('box-shadow', 'none', 'important');
                                                        }
                                                        
                                                        // PHYSICALLY REMOVE action buttons and button containers from DOM
                                                        var actionButtons = clonedDoc.querySelectorAll('.cbd-action-buttons, .cbd-action-btn, .cbd-collapse-toggle, .cbd-copy-text, .cbd-screenshot, button, .dashicons');
                                                        console.log('CBD: Removing', actionButtons.length, 'button elements from DOM');
                                                        for (var k = actionButtons.length - 1; k >= 0; k--) {
                                                            var btn = actionButtons[k];
                                                            if (btn && btn.parentNode) {
                                                                btn.parentNode.removeChild(btn);
                                                                console.log('CBD: Removed button element:', btn.className);
                                                            }
                                                        }
                                                        
                                                        // Special handling for numbering - no borders in print mode
                                                        var numbers = clonedDoc.querySelectorAll('.cbd-container-number');
                                                        for (var l = 0; l < numbers.length; l++) {
                                                            var num = numbers[l];
                                                            num.style.setProperty('background', 'transparent', 'important');
                                                            num.style.setProperty('background-color', 'transparent', 'important');
                                                            num.style.setProperty('border', 'none', 'important');
                                                            num.style.setProperty('color', '#000000', 'important');
                                                            num.style.setProperty('box-shadow', 'none', 'important');
                                                        }
                                                        
                                                        // Remove any remaining visual elements that might have borders
                                                        var visualElements = clonedDoc.querySelectorAll('[style*=\"border\"], [class*=\"border\"], [class*=\"outline\"]');
                                                        for (var m = 0; m < visualElements.length; m++) {
                                                            var elem = visualElements[m];
                                                            elem.style.setProperty('border', 'none', 'important');
                                                            elem.style.setProperty('outline', 'none', 'important');
                                                        }
                                                        
                                                        console.log('CBD: Direct DOM manipulation applied to', elements.length, 'elements');
                                                    };
                                                } else {
                                                    console.log('CBD: Applying VISUAL mode styling');
                                                    // Visual/Standard mode - optimized for good visual rendering
                                                    canvasOptions.backgroundColor = 'white';
                                                    canvasOptions.useCORS = true;
                                                    canvasOptions.allowTaint = false;
                                                    canvasOptions.logging = true;
                                                    
                                                    // Ensure content visibility in visual mode with error handling
                                                    canvasOptions.onclone = function(clonedDoc) {
                                                        try {
                                                            console.log('CBD: Optimizing visual mode rendering');

                                                            // Fix interactive elements FIRST
                                                            fixInteractiveElements(clonedDoc);

                                                            // Simple visual mode optimization - just ensure visibility
                                                            var containers = clonedDoc.querySelectorAll('.cbd-container');
                                                            console.log('CBD: Found', containers.length, 'containers in visual mode');
                                                            
                                                            for (var i = 0; i < containers.length; i++) {
                                                                var container = containers[i];
                                                                
                                                                // Ensure container content is visible
                                                                var content = container.querySelector('.cbd-container-content');
                                                                if (content) {
                                                                    content.style.setProperty('display', 'block', 'important');
                                                                    content.style.setProperty('visibility', 'visible', 'important');
                                                                    content.style.setProperty('opacity', '1', 'important');
                                                                    console.log('CBD: Made content visible for container', i + 1);
                                                                }
                                                                
                                                                // Ensure container is visible
                                                                container.style.setProperty('display', 'block', 'important');
                                                                container.style.setProperty('visibility', 'visible', 'important');
                                                                container.style.setProperty('opacity', '1', 'important');
                                                            }
                                                            
                                                            // REMOVE action buttons and icons in visual mode too (cleaner PDFs)
                                                            var actionButtons = clonedDoc.querySelectorAll('.cbd-action-buttons, .cbd-action-btn, .cbd-collapse-toggle, .cbd-copy-text, .cbd-screenshot, button, .dashicons');
                                                            console.log('CBD: Removing', actionButtons.length, 'button elements from visual mode');
                                                            for (var j = actionButtons.length - 1; j >= 0; j--) {
                                                                try {
                                                                    var btn = actionButtons[j];
                                                                    if (btn && btn.parentNode) {
                                                                        btn.parentNode.removeChild(btn);
                                                                        console.log('CBD: Removed visual mode button element:', btn.className || btn.tagName);
                                                                    }
                                                                } catch (removeError) {
                                                                    console.log('CBD: Could not remove button element:', removeError.message);
                                                                }
                                                            }
                                                            
                                                            console.log('CBD: Visual mode optimization complete - containers and buttons should be visible');
                                                        } catch (oncloneError) {
                                                            console.error('CBD: onclone callback error:', oncloneError);
                                                            console.error('CBD: onclone error stack:', oncloneError.stack);
                                                            // Don't throw - let html2canvas continue
                                                        }
                                                    };
                                                }
                                                
                                                console.log('CBD: Starting html2canvas with options:', canvasOptions);
                                                console.log('CBD: Target element:', $currentBlock[0]);
                                                console.log('CBD: Element visibility:', $currentBlock.is(':visible'));
                                                console.log('CBD: Element dimensions:', $currentBlock[0].offsetWidth + 'x' + $currentBlock[0].offsetHeight);
                                                
                                                try {
                                                    console.log('CBD: About to call html2canvas...');
                                                    html2canvas($currentBlock[0], canvasOptions).then(function(canvas) {
                                                    console.log('CBD: html2canvas successful - Canvas size:', canvas.width + 'x' + canvas.height);

                                                    var imageFormat = 'JPEG';
                                                    var imageQuality = mode === 'print' ? 0.9 : 0.8;
                                                    var imgData = canvas.toDataURL('image/jpeg', imageQuality);
                                                    var imgWidth = 170;
                                                    var imgHeight = (canvas.height * imgWidth) / canvas.width;

                                                    console.log('CBD: Image dimensions - Width:', imgWidth, 'Height:', imgHeight, 'Quality:', imageQuality);

                                                    // Handle large blocks - split them across multiple pages
                                                    var maxPageHeight = 220; // Maximum height that fits on one page (leaving margins)
                                                    var availableHeight = maxPageHeight - y + 30; // Available height on current page

                                                    if (imgHeight > maxPageHeight) {
                                                        console.log('CBD: Block is too tall (' + imgHeight + 'px), analyzing for intelligent splitting...');

                                                        // Intelligent splitting - find natural break points
                                                        var breakPoints = findIntelligentBreakPoints($currentBlock[0], canvas, maxPageHeight, imgWidth, imgHeight);

                                                        console.log('CBD: Found ' + breakPoints.length + ' intelligent break points');

                                                        // Show user notification about splitting
                                                        if (window.cbdShowToast) {
                                                            window.cbdShowToast('📄 Block ' + (processedBlocks + 1) + ' wird intelligent auf ' + breakPoints.length + ' PDF-Seiten aufgeteilt...', 'info');
                                                        } else {
                                                            console.log('CBD: User notification - Block ' + (processedBlocks + 1) + ' wird intelligent auf ' + breakPoints.length + ' PDF-Seiten aufgeteilt');
                                                        }

                                                        for (var section = 0; section < breakPoints.length; section++) {
                                                            var breakPoint = breakPoints[section];

                                                            // If not first section or no space, add new page
                                                            if (section > 0 || y + breakPoint.height > 250) {
                                                                pdf.addPage();
                                                                y = 30;
                                                            }

                                                            // Create a new canvas for this section
                                                            var sectionCanvas = document.createElement('canvas');
                                                            var sectionCtx = sectionCanvas.getContext('2d');
                                                            sectionCanvas.width = canvas.width;
                                                            sectionCanvas.height = breakPoint.canvasHeight;

                                                            // Copy the section from the original canvas
                                                            sectionCtx.drawImage(
                                                                canvas,
                                                                0, breakPoint.canvasStart, // Source x, y
                                                                canvas.width, breakPoint.canvasHeight, // Source width, height
                                                                0, 0, // Destination x, y
                                                                canvas.width, breakPoint.canvasHeight // Destination width, height
                                                            );

                                                            // Convert section to image and add to PDF
                                                            var sectionImgData = sectionCanvas.toDataURL('image/jpeg', imageQuality);
                                                            pdf.addImage(sectionImgData, imageFormat, 20, y, imgWidth, breakPoint.height);

                                                            console.log('CBD: Added intelligent section ' + (section + 1) + '/' + breakPoints.length +
                                                                ' at y=' + y + ', height=' + breakPoint.height + ', elements preserved: ' + breakPoint.elementsPreserved);

                                                            y += breakPoint.height + 5; // Small gap between sections
                                                        }

                                                        y += 5; // Extra space after block

                                                    } else {
                                                        // Block fits normally
                                                        // Check if image fits on current page
                                                        if (y + imgHeight > 250) {
                                                            pdf.addPage();
                                                            y = 30;
                                                            console.log('CBD: Added new page for block, y reset to 30');
                                                        }

                                                        pdf.addImage(imgData, imageFormat, 20, y, imgWidth, imgHeight);
                                                        console.log('CBD: Added full block at y=' + y + ', height=' + imgHeight);
                                                        y += imgHeight + 10;
                                                    }
                                                    
                                                    processedBlocks++;
                                                    processNextBlock();
                                                }).catch(function(error) {
                                                    console.error('CBD: html2canvas failed for block ' + (processedBlocks + 1) + ':', error);
                                                    console.error('CBD: Canvas options were:', canvasOptions);
                                                    console.error('CBD: Block element:', $currentBlock[0]);
                                                    console.log('CBD: Falling back to text-only for this block');
                                                    addTextOnly();
                                                });
                                                } catch (syncError) {
                                                    console.error('CBD: html2canvas synchronous error:', syncError);
                                                    console.error('CBD: Sync error stack:', syncError.stack);
                                                    console.log('CBD: Falling back to text-only due to sync error');
                                                    addTextOnly();
                                                }
                                            }
                                        } else {
                                            console.log('CBD: No visual content detected, using text-only rendering');
                                            // No visual content, use text
                                            addTextOnly();
                                        }
                                    }
                                    
                                    function addTextOnly() {
                                        console.log('CBD: addTextOnly called for block', processedBlocks + 1);
                                        var blockContent = $currentBlock.find('.cbd-container-content').text().substring(0, 300);
                                        console.log('CBD: Block content length:', blockContent.length);
                                        pdf.setFontSize(10);
                                        var lines = pdf.splitTextToSize(blockContent, 170);
                                        for (var i = 0; i < Math.min(lines.length, 8); i++) {
                                            pdf.text(lines[i], 20, y);
                                            y += 6;
                                        }
                                        y += 10;
                                        
                                        processedBlocks++;
                                        processNextBlock();
                                    }
                                }
                                
                                // Start processing
                                processNextBlock();
                                
                            } catch (error) {
                                console.error('CBD: PDF generation failed:', error);
                                alert('Fehler beim PDF erstellen: ' + error.message);
                                return false;
                            }
                        };
                    } else {
                        throw new Error('Cannot create jsPDF instance');
                    }
                } catch (error) {
                    console.log('CBD: jsPDF test failed:', error.message);
                    currentSourceIndex++;
                    loadFromCDN();
                }
            }, 100);
        };
        
        script.onerror = function() {
            console.log('CBD: Failed to load jsPDF from:', cdnSources[currentSourceIndex]);
            currentSourceIndex++;
            loadFromCDN();
        };
        
        console.log('CBD: Trying jsPDF source:', cdnSources[currentSourceIndex]);
        document.head.appendChild(script);
    }
    
    // Start loading
    loadFromCDN();
})();