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
    });
    
})(window.wp);