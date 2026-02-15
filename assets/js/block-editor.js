/**
 * Container Block Designer - Block Editor JavaScript
 * Version: 2.5.5 - FUNKTIONIEREND (ohne rot-grün Aufblitzen)
 * 
 * Datei: assets/js/block-editor.js
 */

(function(wp) {
    'use strict';
    
    // Warte auf vollständiges Laden
    wp.domReady(function() {
        
        if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
            return;
        }
        
        const { registerBlockType, registerBlockStyle } = wp.blocks;
        const { InnerBlocks, useBlockProps, InspectorControls, BlockControls } = wp.blockEditor;
        const { PanelBody, SelectControl, TextControl, Button, Spinner, ToolbarGroup, ToolbarButton, Placeholder } = wp.components;
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

        // FIX: Verwende cbdBlockData (nicht cbdBlockEditor - war Tippfehler)
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
            const [defaultLoaded, setDefaultLoaded] = useState(false);

            // Generate stableId on first insert (persisted in post content)
            useEffect(() => {
                if (!attributes.stableId) {
                    const id = 'cbd-' + Date.now() + '-' + Math.random().toString(36).substr(2, 8);
                    setAttributes({ stableId: id });
                }
            }, []);

            // Lade Blocks beim ersten Rendern
            useEffect(() => {
                loadBlocks();
            }, []);

            // Automatisch Standard-Block laden, wenn kein Block ausgewählt ist
            useEffect(() => {
                // Nur beim ersten Laden und wenn noch kein Block ausgewählt ist
                if (!defaultLoaded && !selectedBlock && availableBlocks.length > 0) {
                    console.log('CBD: Prüfe auf Standard-Block...', availableBlocks);

                    // Finde Standard-Block
                    const defaultBlock = availableBlocks.find(block => {
                        console.log('CBD: Block:', block.name, 'is_default:', block.is_default);
                        return block.is_default == 1 || block.is_default === true;
                    });

                    if (defaultBlock) {
                        const blockValue = defaultBlock.slug || defaultBlock.id;
                        console.log('CBD: Standard-Block gefunden, wird automatisch geladen:', defaultBlock.name, 'Value:', blockValue);
                        setAttributes({ selectedBlock: blockValue });
                        setDefaultLoaded(true);
                    } else {
                        console.log('CBD: Kein Standard-Block gefunden');
                        setDefaultLoaded(true); // Trotzdem als "geladen" markieren
                    }
                }
            }, [availableBlocks, selectedBlock, defaultLoaded]);

            const loadBlocks = () => {
                setIsLoading(true);
                
                // Prüfe lokale Daten
                if (blockData.blocks && blockData.blocks.length > 0) {
                    setAvailableBlocks(blockData.blocks);
                    setIsLoading(false);
                    return;
                }
                
                // AJAX Request
                
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
                    
                    if (data.success && data.data) {
                        const blocks = Array.isArray(data.data) ? data.data : [];
                        
                        // Store blocks globally
                        window.cbdBlockData.blocks = blocks;
                        
                        setAvailableBlocks(blocks);
                    } else {
                        setAvailableBlocks([]);
                    }
                    setIsLoading(false);
                })
                .catch(err => {
                    setAvailableBlocks([]);
                    setIsLoading(false);
                });
            };
            
            const blockProps = useBlockProps({
                className: 'cbd-container cbd-editor-container ' + customClasses,
                style: {
                    maxWidth: '100%',
                    width: '100%'
                }
            });

            return el(Fragment, {},
                // Sidebar-Einstellungen (für erweiterte Optionen)
                el(InspectorControls, {},
                    el(PanelBody, {
                        title: 'Erweiterte Einstellungen',
                        initialOpen: false
                    },
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

                // Hauptbereich: Direkte Eingabefelder im Editor
                el('div', blockProps,
                    // Titel- und Style-Auswahl direkt im Editor
                    el('div', {
                        className: 'cbd-editor-controls',
                        style: {
                            background: '#f0f0f1',
                            padding: '12px 16px',
                            marginBottom: '16px',
                            borderRadius: '4px',
                            border: '1px solid #dcdcde',
                            maxWidth: '100%',
                            boxSizing: 'border-box'
                        }
                    },
                        el('div', {
                            style: {
                                marginBottom: '12px'
                            }
                        },
                            el('label', {
                                style: {
                                    display: 'block',
                                    marginBottom: '8px',
                                    fontWeight: '600',
                                    fontSize: '13px',
                                    color: '#1e1e1e'
                                }
                            }, 'Block-Titel'),
                            // Wrapper für Titel-Input mit Frontend-Styling
                            el('div', {
                                style: {
                                    // Frontend-ähnliches Styling für die Überschrift (wie in cbd-frontend-clean.css)
                                    fontSize: '20px',
                                    fontWeight: '700',
                                    lineHeight: '1.2',
                                    color: '#1e1e1e',
                                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
                                }
                            },
                                el(TextControl, {
                                    value: attributes.blockTitle || '',
                                    onChange: (value) => setAttributes({ blockTitle: value }),
                                    placeholder: 'Titel eingeben...',
                                    __next40pxDefaultSize: true,
                                    __nextHasNoMarginBottom: true
                                })
                            )
                        ),

                        el('div', {
                            style: {
                                // Hellroter Hintergrund wenn kein Style gewählt
                                background: !selectedBlock || selectedBlock === '' ? '#ffebee' : 'transparent',
                                padding: !selectedBlock || selectedBlock === '' ? '8px' : '0',
                                borderRadius: '4px',
                                border: !selectedBlock || selectedBlock === '' ? '1px solid #ffcdd2' : 'none',
                                transition: 'all 0.2s ease'
                            }
                        },
                            el('label', {
                                style: {
                                    display: 'block',
                                    marginBottom: '8px',
                                    fontWeight: '600',
                                    fontSize: '13px',
                                    color: !selectedBlock || selectedBlock === '' ? '#c62828' : '#1e1e1e'
                                }
                            }, !selectedBlock || selectedBlock === '' ? '⚠️ Design-Style (Bitte auswählen!)' : 'Design-Style'),
                            isLoading ?
                                el(Spinner) :
                                el(SelectControl, {
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
                                })
                        )
                    ),

                    // InnerBlocks für Inhalt
                    el(InnerBlocks, {
                        templateLock: false,
                        template: [
                            ['core/paragraph', { placeholder: 'Inhalt hier eingeben...' }]
                        ],
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
            const stableId = attributes.stableId || '';

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

            if (stableId) {
                blockProps['data-stable-id'] = stableId;
            }

            return el('div', blockProps,
                el(InnerBlocks.Content)
            );
        };
        
        // REALTIME STYLE UPDATES (ohne rot-grün Testfarben)
        let lastSelectedBlock = null;
        
        // Monitor for block selection changes
        if (wp.data && wp.data.subscribe) {
            wp.data.subscribe(() => {
                try {
                    const selectedBlockId = wp.data.select('core/block-editor').getSelectedBlockClientId();
                    if (!selectedBlockId) return;
                    
                    const block = wp.data.select('core/block-editor').getBlock(selectedBlockId);
                    if (!block) return;
                    
                    // Check if attributes changed
                    if (lastSelectedBlock && lastSelectedBlock.clientId === block.clientId) {
                        if (JSON.stringify(lastSelectedBlock.attributes) !== JSON.stringify(block.attributes)) {
                        }
                    } else if (block.name && block.name.includes('container-block-designer')) {
                        
                        if (block.name && block.name.includes('container-block-designer')) {
                            
                            if (block.attributes && block.attributes.selectedBlock !== undefined) {
                                const blockSlug = block.attributes.selectedBlock;
                                
                                if (!blockSlug) {
                                } else {
                                }
                            }
                        }
                        
                        // DEAKTIVIERT: Template-Styles überschreiben Live-Preview
                        // Check for dropdown changes (aber keine Styles anwenden)
                        if (lastSelectedBlock && lastSelectedBlock.clientId === block.clientId) {
                            const oldSlug = lastSelectedBlock.attributes && lastSelectedBlock.attributes.selectedBlock;
                            const newSlug = block.attributes && block.attributes.selectedBlock;

                            if (oldSlug !== newSlug) {
                                // applyRealStyles(newSlug, block.clientId); // DEAKTIVIERT
                            }
                        }

                        // DEAKTIVIERT: Template-Styles beim Block-Select
                        const currentSlug = block.attributes && block.attributes.selectedBlock;
                        if (currentSlug) {
                            // applyRealStyles(currentSlug, block.clientId); // DEAKTIVIERT
                        }
                        
                        lastSelectedBlock = block;
                    }
                } catch (error) {
                    // Ignore subscription errors
                }
            });
        }
        
        // Apply real styles function (ohne Testfarben)
        function applyRealStyles(blockSlug, blockId) {
            if (!blockSlug) {
                // Clear styles for empty selection
                const selectedBlock = document.querySelector('.wp-block.is-selected[data-type*="container-block-designer"]');
                if (selectedBlock) {
                    selectedBlock.style.removeProperty('background-color');
                    selectedBlock.style.removeProperty('border');
                    selectedBlock.style.removeProperty('border-radius');
                    selectedBlock.style.removeProperty('color');
                    selectedBlock.style.removeProperty('padding');
                }
                return;
            }
            
            
            // Find the selected container block with multiple selectors
            let selectedBlock = document.querySelector('.wp-block.is-selected[data-type*="container-block-designer"]');
            
            if (!selectedBlock) {
                // Try alternative selectors
                selectedBlock = document.querySelector('.wp-block.is-selected.wp-block-container-block-designer-container');
                if (!selectedBlock) {
                    selectedBlock = document.querySelector('[data-type*="container-block-designer"].is-selected');
                }
            }
            
            if (!selectedBlock) {
                return;
            }
            
            
            // Get block data
            let blocks = null;
            if (window.cbdBlockData && window.cbdBlockData.blocks && Array.isArray(window.cbdBlockData.blocks)) {
                blocks = window.cbdBlockData.blocks;
            }
            
            if (!blocks || blocks.length === 0) {
                applyFallbackStyles(selectedBlock, blockSlug);
                return;
            }
            
            const blockData = blocks.find(b => b.slug === blockSlug);
            if (blockData) {
                applyBlockStyles(selectedBlock, blockData);
            } else {
                applyFallbackStyles(selectedBlock, blockSlug);
            }
        }
        
        // Apply fallback styles - DEAKTIVIERT für Live-Preview
        function applyFallbackStyles(element, slug) {

            // ENTFERNT: Hardcoded orange/gray styles um Live-Preview zu ermöglichen
            // Keine fallback styles mehr - Live-Preview hat Priorität
            return;
        }
        
        // Apply database styles
        function applyBlockStyles(element, blockData) {
            
            // Try to parse database styles
            let styles = null;
            
            if (blockData.styles) {
                try {
                    styles = typeof blockData.styles === 'string' ? JSON.parse(blockData.styles) : blockData.styles;
                } catch (e) {
                }
            } else if (blockData.css_styles) {
                try {
                    styles = typeof blockData.css_styles === 'string' ? JSON.parse(blockData.css_styles) : blockData.css_styles;
                } catch (e) {
                }
            } else if (blockData.config && blockData.config.styles) {
                styles = blockData.config.styles;
            }
            
            if (styles && typeof styles === 'object') {
                
                // Apply database styles similar to fallback styles
                if (styles.background && styles.background.color) {
                    element.style.setProperty('background-color', styles.background.color, 'important');
                }
                if (styles.border) {
                    if (styles.border.width && styles.border.color) {
                        const border = `${styles.border.width}px ${styles.border.style || 'solid'} ${styles.border.color}`;
                        element.style.setProperty('border', border, 'important');
                    }
                    if (styles.border.radius) {
                        element.style.setProperty('border-radius', styles.border.radius + 'px', 'important');
                    }
                }
                if (styles.padding) {
                    element.style.setProperty('padding', typeof styles.padding === 'string' ? styles.padding : '20px', 'important');
                }
                if (styles.text && styles.text.color) {
                    element.style.setProperty('color', styles.text.color, 'important');
                } else if (styles.color) {
                    element.style.setProperty('color', styles.color, 'important');
                }
                
            } else {
                applyFallbackStyles(element, blockData.slug);
            }
        }
        
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
                blockTitle: {
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
                },
                stableId: {
                    type: 'string',
                    default: ''
                }
            },
            // Custom label for List View - shows block title instead of "Container Block"
            __experimentalLabel: (attributes, { context }) => {
                const { blockTitle } = attributes;

                // If we have a block title, show it in the list view
                if (blockTitle && blockTitle.trim()) {
                    return blockTitle.trim();
                }

                // Fallback to default title
                return blockData.i18n.blockTitle;
            },
            edit: ContainerBlockEdit,
            save: ContainerBlockSave
        });
        
        
        // Add direct event listener for dropdown changes
        setTimeout(() => {
            
            // Listen for any select changes in the inspector
            document.addEventListener('change', (e) => {
                if (e.target && e.target.tagName === 'SELECT') {
                    
                    // Check if this might be our dropdown
                    const selectValue = e.target.value;
                    if (selectValue === 'infotext_k1' || selectValue === 'infotext_k2' || selectValue === '') {
                        
                        // Apply styles immediately
                        setTimeout(() => {
                            // applyRealStyles(selectValue); // DEAKTIVIERT für Live-Preview
                        }, 100);
                    }
                }
            });
            
        }, 1000);
    });
    
})(window.wp);