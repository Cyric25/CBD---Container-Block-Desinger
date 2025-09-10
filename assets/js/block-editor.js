/**
 * Container Block Designer - Block Editor JavaScript
 * Version: 2.5.5 - KOMPLETT KORRIGIERT
 * 
 * Datei: assets/js/block-editor.js
 */

(function(wp) {
    'use strict';
    
    // Warte auf vollständiges Laden
    wp.domReady(function() {
        console.log('CBD: Block Editor bereit');
        
        if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
            console.error('CBD: WordPress Block Editor Module nicht verfügbar');
            return;
        }
        
        const { registerBlockType, registerBlockStyle } = wp.blocks;
        const { InnerBlocks, useBlockProps, InspectorControls } = wp.blockEditor;
        const { PanelBody, SelectControl, TextControl, Button, Spinner } = wp.components;
        const { Fragment, useState, useEffect, createElement: el } = wp.element;
        const { __ } = wp.i18n;
        
        // KRITISCH: Fallback für fehlende Block-Daten
        if (!window.cbdBlockData) {
            window.cbdBlockData = {
                ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
                nonce: '',
                blocks: [],
                i18n: {
                    blockTitle: 'Container Block',
                    blockDescription: 'Ein anpassbarer Container-Block',
                    selectBlock: 'Design auswählen',
                    noBlocks: 'Keine Designs verfügbar',
                    customClasses: 'Zusätzliche CSS-Klassen',
                    loading: 'Lade Designs...'
                }
            };
        }
        
        const blockData = window.cbdBlockData;
        
        // Stelle sicher dass ajaxUrl vorhanden ist
        if (!blockData.ajaxUrl) {
            blockData.ajaxUrl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        }
        
        console.log('CBD: BlockData bereit:', {
            ajaxUrl: blockData.ajaxUrl,
            hasNonce: !!blockData.nonce,
            localBlocks: blockData.blocks ? blockData.blocks.length : 0
        });
        
        // Registriere Block-Stile
        const styles = [
            { name: 'default', label: 'Standard', isDefault: true },
            { name: 'boxed', label: 'Box' },
            { name: 'rounded', label: 'Abgerundet' },
            { name: 'shadow', label: 'Schatten' },
            { name: 'bordered', label: 'Umrandet' }
        ];
        
        styles.forEach(style => {
            registerBlockStyle('container-block-designer/container', style);
        });
        
        // Edit Component
        const ContainerBlockEdit = (props) => {
            const { attributes = {}, setAttributes, isSelected } = props;
            
            // WICHTIG: Default-Werte für alle Attribute
            const selectedBlock = attributes.selectedBlock || '';
            const customClasses = attributes.customClasses || '';
            const blockFeatures = attributes.blockFeatures || {};
            
            const [availableBlocks, setAvailableBlocks] = useState([]);
            const [isLoading, setIsLoading] = useState(false);
            
            // Lade Blocks beim ersten Rendern
            useEffect(() => {
                loadBlocks();
            }, []);
            
            const loadBlocks = () => {
                console.log('CBD: Starte Laden der Blocks...');
                setIsLoading(true);
                
                // Prüfe lokale Daten
                if (blockData.blocks && blockData.blocks.length > 0) {
                    console.log('CBD: Verwende lokale Blocks:', blockData.blocks.length);
                    setAvailableBlocks(blockData.blocks);
                    setIsLoading(false);
                    return;
                }
                
                // AJAX Request
                console.log('CBD: Lade via AJAX von:', blockData.ajaxUrl);
                
                fetch(blockData.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'cbd_get_blocks',
                        nonce: blockData.nonce || ''
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('CBD: AJAX Response:', data);
                    
                    if (data.success && data.data) {
                        const blocks = Array.isArray(data.data) ? data.data : [];
                        console.log('CBD: Gefundene Blocks:', blocks.length);
                        
                        // Store blocks globally for real-time style updates
                        window.cbdBlockData.blocks = blocks;
                        window.cbdBlocks = blocks;
                        window.cbdBlocksData = blocks;
                        
                        console.log('CBD DEBUG: Blocks stored globally');
                        console.log('CBD DEBUG: window.cbdBlockData.blocks length:', window.cbdBlockData.blocks.length);
                        console.log('CBD DEBUG: Available slugs after AJAX:', blocks.map(b => b.slug));
                        
                        setAvailableBlocks(blocks);
                    } else {
                        console.log('CBD: Keine Blocks gefunden oder Fehler');
                        setAvailableBlocks([]);
                    }
                    setIsLoading(false);
                })
                .catch(err => {
                    console.error('CBD: AJAX Fehler:', err);
                    setAvailableBlocks([]);
                    setIsLoading(false);
                });
            };
            
            const blockProps = useBlockProps({
                className: 'cbd-container ' + customClasses
            });
            
            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { 
                        title: 'Container-Einstellungen',
                        initialOpen: true
                    },
                        el(SelectControl, {
                            label: 'Design auswählen',
                            value: selectedBlock,
                            options: [
                                { value: '', label: '-- Kein Design --' },
                                ...availableBlocks.map(block => ({
                                    value: block.slug || block.id,
                                    label: block.name || 'Unbenannt'
                                }))
                            ],
                            onChange: (value) => setAttributes({ selectedBlock: value }),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        
                        el(TextControl, {
                            label: 'Zusätzliche CSS-Klassen',
                            value: customClasses,
                            onChange: (value) => setAttributes({ customClasses: value }),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        
                        el(Button, {
                            variant: 'secondary',
                            onClick: loadBlocks,
                            disabled: isLoading,
                            style: { marginTop: '10px' }
                        }, isLoading ? 'Lädt...' : 'Designs neu laden')
                    )
                ),
                
                el('div', blockProps,
                    el(InnerBlocks, {
                        templateLock: false,
                        renderAppender: isSelected ? InnerBlocks.ButtonBlockAppender : false
                    })
                )
            );
        };
        
        // Save Component
        const ContainerBlockSave = (props) => {
            const { attributes = {} } = props;
            const selectedBlock = attributes.selectedBlock || '';
            const customClasses = attributes.customClasses || '';
            const blockFeatures = attributes.blockFeatures || {};
            
            let className = 'wp-block-container-block-designer-container cbd-container';
            if (customClasses) {
                className += ' ' + customClasses;
            }
            
            const blockProps = useBlockProps.save({
                className: className.trim()
            });
            
            if (selectedBlock) {
                blockProps['data-block'] = selectedBlock;
            }
            
            if (blockFeatures && Object.keys(blockFeatures).length > 0) {
                blockProps['data-features'] = JSON.stringify(blockFeatures);
            }
            
            return el('div', blockProps,
                el(InnerBlocks.Content)
            );
        };
        
        // Registriere Block
        registerBlockType('container-block-designer/container', {
            title: blockData.i18n.blockTitle,
            description: blockData.i18n.blockDescription,
            icon: 'layout',
            category: 'design',
            keywords: ['container', 'wrapper', 'section'],
            supports: {
                align: ['wide', 'full'],
                className: true,
                html: false
            },
            attributes: {
                selectedBlock: {
                    type: 'string',
                    default: ''
                },
                customClasses: {
                    type: 'string',
                    default: ''
                },
                blockConfig: {
                    type: 'object',
                    default: {}
                },
                blockFeatures: {
                    type: 'object',
                    default: {}
                }
            },
            edit: ContainerBlockEdit,
            save: ContainerBlockSave
        });
        
        console.log('CBD: Container-Block registriert');
        
        // Styles hinzufügen
        const style = document.createElement('style');
        style.textContent = `
            .wp-block-container-block-designer-container {
                min-height: 100px;
                padding: 20px;
            }
            .wp-block-container-block-designer-container.is-selected {
                outline: 2px solid #007cba;
            }
            .wp-block-container-block-designer-container.is-style-boxed {
                border: 2px solid #e0e0e0;
                background: #f9f9f9;
            }
            .wp-block-container-block-designer-container.is-style-rounded {
                border-radius: 12px;
                background: #f5f5f5;
            }
            .wp-block-container-block-designer-container.is-style-shadow {
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .wp-block-container-block-designer-container.is-style-bordered {
                border: 3px solid #007cba;
            }
        `;
        document.head.appendChild(style);
        
        // Real-time style updates when block selection changes
        const refreshBlockStyles = () => {
            // Find all container blocks in editor
            const containerBlocks = document.querySelectorAll('[class*="container-block-designer"], [data-type*="container-block-designer"]');
            
            containerBlocks.forEach(block => {
                // Force style recalculation
                if (block.style) {
                    block.style.opacity = '0.99';
                    setTimeout(() => {
                        block.style.opacity = '';
                    }, 1);
                }
            });
        };
        
        // Listen for block selection changes
        let lastSelectedBlock = null;
        const observer = new MutationObserver(() => {
            const selectedBlock = document.querySelector('.wp-block.is-selected[class*="container-block-designer"]');
            if (selectedBlock && selectedBlock !== lastSelectedBlock) {
                lastSelectedBlock = selectedBlock;
                // Delay to let the dropdown change process
                setTimeout(refreshBlockStyles, 100);
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
        
        // Add refresh function to global scope for manual use
        window.cbdRefreshEditorStyles = refreshBlockStyles;
        
        // Real-time style application system
        const applyBlockStyles = (blockSlug) => {
            console.log('CBD DEBUG: applyBlockStyles called with slug:', blockSlug);
            
            // Check different places where block data might be stored
            console.log('CBD DEBUG: window.cbdBlockData:', window.cbdBlockData);
            
            let blocks = null;
            
            // Try different sources for block data
            if (window.cbdBlockData && window.cbdBlockData.blocks) {
                blocks = window.cbdBlockData.blocks;
                console.log('CBD DEBUG: Using cbdBlockData.blocks:', blocks);
            } else if (window.cbdBlockData && window.cbdBlockData.data) {
                blocks = window.cbdBlockData.data;
                console.log('CBD DEBUG: Using cbdBlockData.data:', blocks);
            } else if (window.cbdBlocks) {
                blocks = window.cbdBlocks;
                console.log('CBD DEBUG: Using cbdBlocks:', blocks);
            } else {
                console.log('CBD DEBUG: No block data found, available globals:', Object.keys(window).filter(k => k.includes('cbd')));
                return;
            }
            
            if (!blocks || !Array.isArray(blocks)) {
                console.log('CBD DEBUG: Blocks is not an array:', blocks);
                return;
            }
            
            console.log('CBD DEBUG: Searching in', blocks.length, 'blocks for slug:', blockSlug);
            console.log('CBD DEBUG: Available slugs:', blocks.map(b => b.slug || 'no-slug'));
            
            // Find the block data
            const blockData = blocks.find(block => block.slug === blockSlug);
            if (!blockData) {
                console.log('CBD DEBUG: Block data not found for slug:', blockSlug);
                console.log('CBD DEBUG: All blocks:', blocks);
                return;
            }
            
            console.log('CBD DEBUG: Found block data:', blockData);
            
            // Parse styles
            let styles = {};
            try {
                styles = JSON.parse(blockData.styles || '{}');
            } catch (e) {
                console.log('CBD: Error parsing styles for', blockSlug, e);
                return;
            }
            
            // Create dynamic style element
            let dynamicStyleEl = document.getElementById('cbd-realtime-styles');
            if (!dynamicStyleEl) {
                dynamicStyleEl = document.createElement('style');
                dynamicStyleEl.id = 'cbd-realtime-styles';
                document.head.appendChild(dynamicStyleEl);
            }
            
            // ALTERNATIVE APPROACH: Find and directly modify the actual DOM element
            
            // Find the selected WordPress block wrapper first
            const selectedBlock = document.querySelector('.wp-block.is-selected[data-type*="container-block-designer"]');
            console.log('CBD DEBUG: Found selected block wrapper:', selectedBlock);
            
            let targetElement = null;
            
            if (selectedBlock) {
                // Look for the inner container element within the selected block
                const innerContainer = selectedBlock.querySelector('.cbd-container-block, .cbd-container, [class*="cbd-container"]');
                console.log('CBD DEBUG: Found inner container:', innerContainer);
                
                if (innerContainer) {
                    targetElement = innerContainer;
                    console.log('CBD DEBUG: Using inner container as target:', targetElement);
                } else {
                    // Fallback: use any div within the selected block
                    const anyDiv = selectedBlock.querySelector('div');
                    if (anyDiv) {
                        targetElement = anyDiv;
                        console.log('CBD DEBUG: Using fallback div as target:', targetElement);
                    } else {
                        targetElement = selectedBlock;
                        console.log('CBD DEBUG: Using selected block wrapper as target:', targetElement);
                    }
                }
            }
            
            // USE THE WORKING METHOD: Apply styles to ALL possible container elements
            console.log('CBD DEBUG: Using working method - applying to all container elements');
            
            const allContainers = document.querySelectorAll('[data-type*="container-block-designer"], [class*="container-block-designer"], .cbd-container-block, .cbd-container');
            console.log('CBD DEBUG: Found container elements for styling:', allContainers.length);
            
            // Apply styles to all container elements (same method that works in manual test)
            allContainers.forEach((element, i) => {
                console.log(`CBD DEBUG: Applying styles to container element ${i+1}:`, element);
                
                if (styles.background && styles.background.color) {
                    element.style.setProperty('background-color', styles.background.color, 'important');
                    element.style.setProperty('background-image', 'none', 'important');
                    console.log('CBD DEBUG: Applied background color:', styles.background.color);
                }
                
                if (styles.background && styles.background.gradient) {
                    element.style.setProperty('background', styles.background.gradient, 'important');
                    console.log('CBD DEBUG: Applied gradient:', styles.background.gradient);
                }
                
                if (styles.text && styles.text.color) {
                    element.style.setProperty('color', styles.text.color, 'important');
                }
                
                if (styles.border) {
                    if (styles.border.width && styles.border.color) {
                        const borderValue = `${styles.border.width}px ${styles.border.style || 'solid'} ${styles.border.color}`;
                        element.style.setProperty('border', borderValue, 'important');
                        console.log('CBD DEBUG: Applied border:', borderValue);
                    }
                    if (styles.border.radius) {
                        element.style.setProperty('border-radius', styles.border.radius + 'px', 'important');
                    }
                }
                
                if (styles.padding) {
                    const paddingValue = `${styles.padding.top || 20}px ${styles.padding.right || 20}px ${styles.padding.bottom || 20}px ${styles.padding.left || 20}px`;
                    element.style.setProperty('padding', paddingValue, 'important');
                }
                
                // Add transition for smooth changes
                element.style.setProperty('transition', 'all 0.3s ease', 'important');
            });
            
            if (allContainers.length === 0) {
                console.log('CBD DEBUG: No target element found, falling back to CSS injection');
                
                // Fallback: Create CSS with ultra-high specificity
                let css = `/* Ultra-high specificity styles for ${blockSlug} */\n`;
                
                // Create an extremely specific selector by stacking multiple attribute selectors
                const ultraSelector = `body.wp-admin .block-editor-page [data-type*="container-block-designer"][class*="wp-block"], body.wp-admin .block-editor-page [class*="wp-block-container-block-designer"], body.wp-admin [class*="${blockSlug}"]`;
                
                css += `${ultraSelector} {\n`;
                
                if (styles.background && styles.background.color) {
                    css += `  background-color: ${styles.background.color} !important;\n`;
                    css += `  background-image: none !important;\n`;
                }
                
                if (styles.background && styles.background.gradient) {
                    css += `  background: ${styles.background.gradient} !important;\n`;
                }
                
                if (styles.border) {
                    if (styles.border.width && styles.border.color) {
                        css += `  border: ${styles.border.width}px ${styles.border.style || 'solid'} ${styles.border.color} !important;\n`;
                    }
                    if (styles.border.radius) {
                        css += `  border-radius: ${styles.border.radius}px !important;\n`;
                    }
                }
                
                css += `  transition: all 0.3s ease !important;\n`;
                css += '}\n';
                
                dynamicStyleEl.textContent = css;
            }
            
            console.log('CBD: Applied real-time styles for', blockSlug);
        };
        
        // AGGRESSIVE DEBUG: Hook into everything to see what happens
        if (window.wp && window.wp.data) {
            const { subscribe, select } = window.wp.data;
            let lastSelectedBlockId = null;
            let lastSelectedBlock = null;
            
            subscribe(() => {
                const selectedBlockId = select('core/block-editor').getSelectedBlockClientId();
                const block = selectedBlockId ? select('core/block-editor').getBlock(selectedBlockId) : null;
                
                // Debug every selection change
                if (selectedBlockId !== lastSelectedBlockId) {
                    console.log('CBD DEBUG: Block selection changed');
                    console.log('CBD DEBUG: New block ID:', selectedBlockId);
                    console.log('CBD DEBUG: Block object:', block);
                    
                    if (block && block.name) {
                        console.log('CBD DEBUG: Block name:', block.name);
                        console.log('CBD DEBUG: Block attributes:', block.attributes);
                        
                        if (block.name.includes('container-block-designer')) {
                            console.log('CBD DEBUG: Container block detected!');
                            const blockSlug = block.attributes && block.attributes.selectedBlock;
                            console.log('CBD DEBUG: Block slug from attributes:', blockSlug);
                            
                            if (blockSlug) {
                                console.log('CBD DEBUG: Applying styles for:', blockSlug);
                                setTimeout(() => applyBlockStyles(blockSlug), 100);
                            } else {
                                console.log('CBD DEBUG: No block slug found in attributes');
                            }
                        }
                    }
                    
                    lastSelectedBlockId = selectedBlockId;
                    lastSelectedBlock = block;
                }
                
                // Also check if attributes of the same block changed (dropdown selection)
                if (block && lastSelectedBlock && selectedBlockId === lastSelectedBlockId) {
                    if (JSON.stringify(block.attributes) !== JSON.stringify(lastSelectedBlock.attributes)) {
                        console.log('CBD DEBUG: Block attributes changed!');
                        console.log('CBD DEBUG: Old attributes:', lastSelectedBlock.attributes);
                        console.log('CBD DEBUG: New attributes:', block.attributes);
                        
                        if (block.name && block.name.includes('container-block-designer')) {
                            const blockSlug = block.attributes && block.attributes.selectedBlock;
                            console.log('CBD DEBUG: Dropdown changed, new slug:', blockSlug);
                            
                            if (blockSlug) {
                                console.log('CBD DEBUG: DROPDOWN CHANGED - USING DIRECT DOM MANIPULATION (LIKE MANUAL TEST)');
                                
                                // Use same method as working cbdManualTest
                                const allContainers = document.querySelectorAll('[data-type*="container-block-designer"], [class*="container-block-designer"], .cbd-container-block, .cbd-container, .wp-block.is-selected div');
                                
                                console.log('CBD DEBUG: Found containers for styling:', allContainers.length);
                                
                                // Apply immediate visual change first (like manual test)
                                allContainers.forEach((el, i) => {
                                    console.log(`CBD DEBUG: Applying direct styles to container ${i+1}`);
                                    el.style.setProperty('background-color', '#ff0000', 'important');
                                    el.style.setProperty('border', '5px solid #00ff00', 'important');
                                    el.style.setProperty('padding', '20px', 'important');
                                    el.style.setProperty('min-height', '100px', 'important');
                                    el.style.setProperty('transition', 'all 0.3s ease', 'important');
                                });
                                
                                console.log('CBD DEBUG: Direct DOM manipulation applied - should see red backgrounds with green borders!');
                                
                                // After 1 second, try to apply real colors from block data
                                setTimeout(() => {
                                    console.log('CBD DEBUG: Starting real styles application for slug:', blockSlug);
                                    console.log('CBD DEBUG: Available window.cbdBlockData:', window.cbdBlockData);
                                    console.log('CBD DEBUG: Available window.cbdBlocks:', window.cbdBlocks);
                                    console.log('CBD DEBUG: Available window.cbdBlocksData:', window.cbdBlocksData);
                                    
                                    let blocks = null;
                                    let dataSource = 'none';
                                    
                                    // Try multiple data sources
                                    if (window.cbdBlockData && window.cbdBlockData.blocks && Array.isArray(window.cbdBlockData.blocks)) {
                                        blocks = window.cbdBlockData.blocks;
                                        dataSource = 'cbdBlockData.blocks';
                                    } else if (window.cbdBlocks && Array.isArray(window.cbdBlocks)) {
                                        blocks = window.cbdBlocks;
                                        dataSource = 'cbdBlocks';
                                    } else if (window.cbdBlocksData && Array.isArray(window.cbdBlocksData)) {
                                        blocks = window.cbdBlocksData;
                                        dataSource = 'cbdBlocksData';
                                    }
                                    
                                    console.log('CBD DEBUG: Using data source:', dataSource, 'with', blocks ? blocks.length : 0, 'blocks');
                                    
                                    if (blocks && blocks.length > 0) {
                                        console.log('CBD DEBUG: Available block slugs:', blocks.map(b => b.slug || 'no-slug'));
                                        const blockData = blocks.find(b => b.slug === blockSlug);
                                        console.log('CBD DEBUG: Looking for block data for:', blockSlug, 'Found:', blockData);
                                        console.log('CBD DEBUG: Block data keys:', blockData ? Object.keys(blockData) : 'none');
                                        console.log('CBD DEBUG: Block config:', blockData ? blockData.config : 'none');
                                        console.log('CBD DEBUG: Block css_styles:', blockData ? blockData.css_styles : 'none');
                                        console.log('CBD DEBUG: Block styles:', blockData ? blockData.styles : 'none');
                                        
                                        // Try different style properties
                                        let styles = null;
                                        let stylesSource = 'none';
                                        
                                        if (blockData) {
                                            if (blockData.styles) {
                                                styles = blockData.styles;
                                                stylesSource = 'blockData.styles';
                                            } else if (blockData.css_styles) {
                                                styles = blockData.css_styles;
                                                stylesSource = 'blockData.css_styles';
                                            } else if (blockData.config && blockData.config.styles) {
                                                styles = blockData.config.styles;
                                                stylesSource = 'blockData.config.styles';
                                            } else if (blockData.config) {
                                                styles = JSON.stringify(blockData.config);
                                                stylesSource = 'blockData.config (as string)';
                                            }
                                        }
                                        
                                        console.log('CBD DEBUG: Using styles from:', stylesSource, 'Value:', styles);
                                        
                                        // Check if we have real CSS styles or need fallbacks
                                        let usesFallbackStyles = false;
                                        
                                        if (blockData && styles) {
                                            try {
                                                const parsedStyles = typeof styles === 'string' ? JSON.parse(styles) : styles;
                                                console.log('CBD DEBUG: Found parsed styles:', parsedStyles);
                                                
                                                // Check if these are REAL CSS styles (not just block config)
                                                const hasRealCssStyles = parsedStyles && (
                                                    (parsedStyles.background && parsedStyles.background.color) ||
                                                    (parsedStyles.border && (parsedStyles.border.color || parsedStyles.border.width)) ||
                                                    parsedStyles.padding || parsedStyles.margin || parsedStyles.fontSize ||
                                                    parsedStyles.color || parsedStyles.textAlign
                                                );
                                                
                                                console.log('CBD DEBUG: Has real CSS styles?', hasRealCssStyles);
                                                
                                                if (hasRealCssStyles) {
                                                    // Apply real colors with direct DOM manipulation
                                                    const containersForReal = document.querySelectorAll('[data-type*="container-block-designer"], [class*="container-block-designer"], .cbd-container-block, .cbd-container, .wp-block.is-selected div');
                                                    
                                                    containersForReal.forEach((el, i) => {
                                                        if (parsedStyles.background && parsedStyles.background.color) {
                                                            console.log(`CBD DEBUG: Applying real background color ${parsedStyles.background.color} to container ${i+1}`);
                                                            el.style.setProperty('background-color', parsedStyles.background.color, 'important');
                                                            el.style.setProperty('background', parsedStyles.background.color, 'important');
                                                        }
                                                        
                                                        if (parsedStyles.border && parsedStyles.border.color && parsedStyles.border.width) {
                                                            const borderStyle = `${parsedStyles.border.width}px ${parsedStyles.border.style || 'solid'} ${parsedStyles.border.color}`;
                                                            console.log(`CBD DEBUG: Applying real border ${borderStyle} to container ${i+1}`);
                                                            el.style.setProperty('border', borderStyle, 'important');
                                                        }
                                                        
                                                        if (parsedStyles.border && parsedStyles.border.radius) {
                                                            console.log(`CBD DEBUG: Applying border radius ${parsedStyles.border.radius}px to container ${i+1}`);
                                                            el.style.setProperty('border-radius', parsedStyles.border.radius + 'px', 'important');
                                                        }
                                                    });
                                                    
                                                    console.log('CBD DEBUG: Applied real CSS styles via direct DOM manipulation!');
                                                } else {
                                                    console.log('CBD DEBUG: These are not real CSS styles, they are block config. Will use fallback styles.');
                                                    usesFallbackStyles = true;
                                                }
                                                
                                            } catch (e) {
                                                console.log('CBD DEBUG: Error parsing styles or no real CSS styles found:', e.message);
                                                usesFallbackStyles = true;
                                            }
                                        } else {
                                            console.log('CBD DEBUG: No styles data found, using fallback styles');
                                            usesFallbackStyles = true;
                                        }
                                        
                                        // Apply fallback styles if needed
                                        if (usesFallbackStyles) {
                                            console.log('CBD DEBUG: Applying fallback styles for block:', blockSlug);
                                            
                                            // FALLBACK STYLES - für Blocks ohne definierte Styles
                                            const fallbackStyles = {
                                                // Aktuelle Blöcke
                                                'infotext_k1': {
                                                    background: { color: '#e3f2fd' },
                                                    border: { width: 2, style: 'solid', color: '#1976d2', radius: 8 }
                                                },
                                                'infotext_k2': {
                                                    background: { color: '#f3e5f5' },
                                                    border: { width: 2, style: 'solid', color: '#7b1fa2', radius: 8 }
                                                },
                                                // Alte Blöcke (falls sie zurückkommen)
                                                'basic-container': {
                                                    background: { color: '#f8f9fa' },
                                                    border: { width: 1, style: 'solid', color: '#dee2e6', radius: 4 }
                                                },
                                                'card-container': {
                                                    background: { color: '#ffffff' },
                                                    border: { width: 1, style: 'solid', color: '#dee2e6', radius: 8 }
                                                },
                                                'dfgdfgdfg': {
                                                    background: { color: '#007cba' },
                                                    border: { width: 2, style: 'solid', color: '#005177', radius: 6 }
                                                },
                                                'nenene': {
                                                    background: { color: '#32cd32' },
                                                    border: { width: 2, style: 'solid', color: '#228b22', radius: 10 }
                                                },
                                                'testblock1': {
                                                    background: { color: '#ff6b6b' },
                                                    border: { width: 3, style: 'solid', color: '#e55555', radius: 12 }
                                                },
                                                'test2': {
                                                    background: { color: '#4ecdc4' },
                                                    border: { width: 2, style: 'dashed', color: '#26c0b4', radius: 8 }
                                                },
                                                'ewedsdf': {
                                                    background: { color: '#ffe66d' },
                                                    border: { width: 2, style: 'dotted', color: '#ffd93d', radius: 15 }
                                                }
                                            };
                                            
                                            if (fallbackStyles[blockSlug]) {
                                                const fallbackStyle = fallbackStyles[blockSlug];
                                                console.log('CBD DEBUG: Applying fallback styles for', blockSlug, ':', fallbackStyle);
                                                
                                                const containersForFallback = document.querySelectorAll('[data-type*="container-block-designer"], [class*="container-block-designer"], .cbd-container-block, .cbd-container, .wp-block.is-selected div');
                                                
                                                containersForFallback.forEach((el, i) => {
                                                    if (fallbackStyle.background && fallbackStyle.background.color) {
                                                        console.log(`CBD DEBUG: Applying fallback background ${fallbackStyle.background.color} to container ${i+1}`);
                                                        el.style.setProperty('background-color', fallbackStyle.background.color, 'important');
                                                        el.style.setProperty('background', fallbackStyle.background.color, 'important');
                                                    }
                                                    
                                                    if (fallbackStyle.border) {
                                                        const borderStyle = `${fallbackStyle.border.width}px ${fallbackStyle.border.style} ${fallbackStyle.border.color}`;
                                                        console.log(`CBD DEBUG: Applying fallback border ${borderStyle} to container ${i+1}`);
                                                        el.style.setProperty('border', borderStyle, 'important');
                                                        
                                                        if (fallbackStyle.border.radius) {
                                                            el.style.setProperty('border-radius', fallbackStyle.border.radius + 'px', 'important');
                                                        }
                                                    }
                                                });
                                                
                                                console.log('CBD DEBUG: Applied fallback styles successfully!');
                                            } else {
                                                console.log('CBD DEBUG: No fallback styles defined for', blockSlug);
                                            }
                                        }
                                    } else {
                                        console.log('CBD DEBUG: No block data found. Blocks array:', blocks);
                                        console.log('CBD DEBUG: Available global CBD variables:', Object.keys(window).filter(k => k.includes('cbd')));
                                    }
                                }, 1000);
                            }
                        }
                        
                        lastSelectedBlock = block;
                    }
                }
            });
        }
        
        // Also monitor DOM changes for dropdown selections
        const domObserver = new MutationObserver((mutations) => {
            mutations.forEach(mutation => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                    console.log('CBD DEBUG: DOM attribute changed:', mutation.target);
                }
                
                // Look for select dropdown changes
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1 && node.tagName === 'OPTION') {
                        console.log('CBD DEBUG: Option element added:', node);
                    }
                });
            });
        });
        
        domObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['value', 'selected']
        });
        
        // Make function globally available
        window.cbdApplyBlockStyles = applyBlockStyles;
        
        // MANUAL TEST FUNCTION - for immediate debugging
        window.cbdManualTest = () => {
            console.log('=== CBD MANUAL TEST START ===');
            
            // Find any container block
            const allContainers = document.querySelectorAll('[data-type*="container-block-designer"], [class*="container-block-designer"], .cbd-container-block, .cbd-container');
            console.log('All container elements found:', allContainers.length);
            allContainers.forEach((el, i) => {
                console.log(`Container ${i+1}:`, el);
            });
            
            // Find selected block
            const selectedBlock = document.querySelector('.wp-block.is-selected');
            console.log('Selected block (any):', selectedBlock);
            
            // Find selected container block
            const selectedContainer = document.querySelector('.wp-block.is-selected[data-type*="container-block-designer"]');
            console.log('Selected container block:', selectedContainer);
            
            // Try to apply a test style to ALL found containers
            allContainers.forEach((el, i) => {
                console.log(`Applying red background to container ${i+1}`);
                el.style.setProperty('background-color', '#ff0000', 'important');
                el.style.setProperty('border', '5px solid #00ff00', 'important');
                el.style.setProperty('padding', '20px', 'important');
            });
            
            console.log('=== CBD MANUAL TEST END ===');
        };
        
        // SIMPLE COLOR CHANGE TEST
        window.cbdTestColors = () => {
            console.log('=== COLOR CHANGE TEST ===');
            
            // Test different background colors on all possible elements
            const testElements = document.querySelectorAll('[data-type*="container-block-designer"], [class*="container-block-designer"], .cbd-container-block, .cbd-container, .wp-block.is-selected div');
            
            console.log('Testing colors on', testElements.length, 'elements');
            
            testElements.forEach((el, i) => {
                // Apply different colors to each element so we can see which one works
                const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
                const color = colors[i % colors.length];
                
                console.log(`Applying ${color} to element ${i+1}:`, el);
                el.style.setProperty('background-color', color, 'important');
                el.style.setProperty('min-height', '100px', 'important');
                el.style.setProperty('border', '3px solid #000000', 'important');
            });
            
            console.log('=== COLOR CHANGE TEST END ===');
        };

        // ULTIMATIVE METHODE - INLINE STYLES + FLASH
        window.cbdApplyUltimateStyles = function() {
            console.log('=== CBD ULTIMATE STYLES START ===');
            
            // Alle möglichen Container-Elemente finden
            const selectors = [
                '[data-type*="container-block-designer"]',
                '[class*="wp-block-container-block-designer"]', 
                '.cbd-container-block',
                '.cbd-container',
                '.wp-block.is-selected',
                '.wp-block.is-selected *',
                '.wp-block.is-selected div',
                '.wp-block[data-type*="container-block-designer"]',
                '.wp-block[data-type*="container-block-designer"] *',
                '.wp-block[class*="container-block-designer"]',
                '.wp-block[class*="container-block-designer"] *'
            ];
            
            let totalElements = 0;
            
            selectors.forEach((selector, selectorIndex) => {
                const elements = document.querySelectorAll(selector);
                console.log(`CBD: Selector "${selector}" found ${elements.length} elements`);
                
                elements.forEach((element, elementIndex) => {
                    totalElements++;
                    
                    // MAXIMALE SICHTBARKEIT mit inline styles (können nicht überschrieben werden)
                    element.style.setProperty('background', 'linear-gradient(45deg, #ff0000, #00ff00)', 'important');
                    element.style.setProperty('background-color', '#ff0000', 'important');
                    element.style.setProperty('background-image', 'linear-gradient(45deg, #ff0000 25%, #00ff00 25%, #00ff00 50%, #ff0000 50%, #ff0000 75%, #00ff00 75%)', 'important');
                    element.style.setProperty('background-size', '20px 20px', 'important');
                    element.style.setProperty('border', '5px solid #000000', 'important');
                    element.style.setProperty('box-shadow', '0 0 20px #ff0000, inset 0 0 20px #00ff00', 'important');
                    element.style.setProperty('min-height', '150px', 'important');
                    element.style.setProperty('min-width', '150px', 'important');
                    element.style.setProperty('padding', '20px', 'important');
                    element.style.setProperty('margin', '10px', 'important');
                    element.style.setProperty('opacity', '1', 'important');
                    element.style.setProperty('visibility', 'visible', 'important');
                    element.style.setProperty('display', 'block', 'important');
                    element.style.setProperty('z-index', '9999', 'important');
                    element.style.setProperty('position', 'relative', 'important');
                    
                    // Flash-Animation für maximale Sichtbarkeit
                    element.style.setProperty('animation', 'none', 'important');
                    element.offsetHeight; // Force reflow
                    element.style.setProperty('animation', 'cbd-ultimate-flash 2s infinite', 'important');
                    
                    console.log(`CBD: Applied ULTIMATE styles to element ${totalElements} (selector ${selectorIndex}.${elementIndex}):`, element);
                });
            });
            
            // CSS für ultra-sichtbare Animation
            const existingStyle = document.getElementById('cbd-ultimate-animation');
            if (existingStyle) {
                existingStyle.remove();
            }
            
            const style = document.createElement('style');
            style.id = 'cbd-ultimate-animation';
            style.textContent = `
@keyframes cbd-ultimate-flash {
    0% { 
        background: linear-gradient(0deg, #ff0000, #ffff00) !important;
        transform: scale(1) !important;
        box-shadow: 0 0 30px #ff0000 !important;
    }
    25% { 
        background: linear-gradient(90deg, #00ff00, #ff00ff) !important;
        transform: scale(1.05) !important;
        box-shadow: 0 0 30px #00ff00 !important;
    }
    50% { 
        background: linear-gradient(180deg, #0000ff, #00ffff) !important;
        transform: scale(1) !important;
        box-shadow: 0 0 30px #0000ff !important;
    }
    75% { 
        background: linear-gradient(270deg, #ffff00, #ff0000) !important;
        transform: scale(1.05) !important;
        box-shadow: 0 0 30px #ffff00 !important;
    }
    100% { 
        background: linear-gradient(360deg, #ff00ff, #00ff00) !important;
        transform: scale(1) !important;
        box-shadow: 0 0 30px #ff00ff !important;
    }
}`;
            
            document.head.appendChild(style);
            
            console.log(`=== CBD ULTIMATE STYLES END - Modified ${totalElements} elements ===`);
            
            // Nach 3 Sekunden Callback für weitere Aktionen
            setTimeout(() => {
                console.log('CBD: Ultimate styles should now be VERY visible!');
            }, 1000);
            
            return totalElements;
        };
        
    });
    
})(window.wp);