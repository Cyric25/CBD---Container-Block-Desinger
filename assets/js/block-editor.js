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
            if (!window.cbdBlockData || !window.cbdBlockData.blocks) {
                console.log('CBD: Block data not available for real-time updates');
                return;
            }
            
            // Find the block data
            const blockData = window.cbdBlockData.blocks.find(block => block.slug === blockSlug);
            if (!blockData) {
                console.log('CBD: Block data not found for slug:', blockSlug);
                return;
            }
            
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
            
            // Build CSS for this specific block
            let css = `/* Real-time styles for ${blockSlug} */\n`;
            const selectors = [
                `.wp-block-container-block-designer-${blockSlug}`,
                `[class*="${blockSlug}"]`,
                `[data-type*="container-block-designer"]`,
                `div[class*="container-block-designer"]`
            ];
            
            const selectorString = selectors.join(', ');
            css += `${selectorString} {\n`;
            
            // Apply background color
            if (styles.background && styles.background.color) {
                css += `  background-color: ${styles.background.color} !important;\n`;
            }
            
            // Apply background gradient
            if (styles.background && styles.background.gradient) {
                css += `  background: ${styles.background.gradient} !important;\n`;
            }
            
            // Apply text color
            if (styles.text && styles.text.color) {
                css += `  color: ${styles.text.color} !important;\n`;
            }
            
            // Apply border
            if (styles.border) {
                if (styles.border.width && styles.border.color) {
                    css += `  border: ${styles.border.width}px ${styles.border.style || 'solid'} ${styles.border.color} !important;\n`;
                }
                if (styles.border.radius) {
                    css += `  border-radius: ${styles.border.radius}px !important;\n`;
                }
            }
            
            // Apply box shadow
            if (styles.boxShadow && styles.boxShadow.enabled) {
                const shadow = styles.boxShadow;
                css += `  box-shadow: ${shadow.x || 0}px ${shadow.y || 2}px ${shadow.blur || 4}px ${shadow.spread || 0}px ${shadow.color || 'rgba(0,0,0,0.1)'} !important;\n`;
            }
            
            // Apply padding
            if (styles.padding) {
                css += `  padding: ${styles.padding.top || 20}px ${styles.padding.right || 20}px ${styles.padding.bottom || 20}px ${styles.padding.left || 20}px !important;\n`;
            }
            
            css += '}\n';
            
            // Update the style element
            dynamicStyleEl.textContent = css;
            
            console.log('CBD: Applied real-time styles for', blockSlug);
        };
        
        // Hook into WordPress block editor selection changes
        if (window.wp && window.wp.data) {
            const { subscribe, select } = window.wp.data;
            let lastSelectedBlockId = null;
            
            subscribe(() => {
                const selectedBlockId = select('core/block-editor').getSelectedBlockClientId();
                if (selectedBlockId && selectedBlockId !== lastSelectedBlockId) {
                    lastSelectedBlockId = selectedBlockId;
                    
                    const block = select('core/block-editor').getBlock(selectedBlockId);
                    if (block && block.name && block.name.includes('container-block-designer')) {
                        // Extract block slug from attributes
                        const blockSlug = block.attributes && block.attributes.selectedBlock;
                        if (blockSlug) {
                            console.log('CBD: Block selected, applying styles for:', blockSlug);
                            setTimeout(() => applyBlockStyles(blockSlug), 100);
                        }
                    }
                }
            });
        }
        
        // Make function globally available
        window.cbdApplyBlockStyles = applyBlockStyles;
    });
    
})(window.wp);