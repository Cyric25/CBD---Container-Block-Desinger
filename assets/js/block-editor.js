/**
 * Container Block Designer - Block Editor JavaScript
 * Version: 2.5.1
 * 
 * Datei: assets/js/block-editor.js
 */

(function(wp, jQuery) {
    'use strict';
    
    // Warte auf WordPress Block Editor
    if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
        console.error('CBD: WordPress Block Editor nicht verfügbar');
        return;
    }
    
    const { registerBlockType } = wp.blocks;
    const { InnerBlocks, useBlockProps, InspectorControls, BlockControls } = wp.blockEditor;
    const { 
        PanelBody, 
        SelectControl, 
        TextControl, 
        ToggleControl, 
        ColorPicker, 
        RangeControl,
        ToolbarGroup,
        ToolbarButton,
        Placeholder,
        Button,
        Spinner
    } = wp.components;
    const { Fragment, useState, useEffect, createElement } = wp.element;
    const { __ } = wp.i18n;
    
    // Block-Daten aus lokalisierten Daten
    const blockData = window.cbdBlockData || {
        blocks: [],
        i18n: {
            blockTitle: 'Container Block',
            blockDescription: 'Ein anpassbarer Container-Block',
            selectBlock: 'Design auswählen',
            noBlocks: 'Keine Blöcke verfügbar',
            addContent: 'Inhalt hinzufügen',
            settings: 'Einstellungen',
            design: 'Design',
            features: 'Funktionen',
            customClasses: 'Eigene CSS-Klassen'
        }
    };
    
    // Block-Typ registrieren
    registerBlockType('container-block-designer/container', {
        title: blockData.i18n.blockTitle,
        description: blockData.i18n.blockDescription,
        icon: 'layout',
        category: 'container-blocks',
        keywords: ['container', 'wrapper', 'section', 'box', 'layout'],
        supports: {
            html: false,
            className: true,
            anchor: true,
            align: ['wide', 'full'],
            spacing: {
                margin: true,
                padding: true
            },
            color: {
                background: true,
                text: true
            }
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
                default: {
                    styles: {
                        padding: {
                            top: 20,
                            right: 20,
                            bottom: 20,
                            left: 20
                        },
                        background: {
                            color: '#ffffff'
                        },
                        border: {
                            width: 0,
                            color: '#dddddd',
                            radius: 0
                        },
                        text: {
                            color: '#000000',
                            alignment: 'left'
                        }
                    }
                }
            },
            blockFeatures: {
                type: 'object',
                default: {
                    icon: {
                        enabled: false,
                        value: 'dashicons-admin-generic',
                        position: 'top-left',
                        color: '#333333'
                    }
                }
            },
            align: {
                type: 'string',
                default: ''
            }
        },
        
        // Deprecated versions für Migration alter Blöcke
        deprecated: [
            {
                // Version 1: Alte Blöcke mit data-attributes
                attributes: {
                    selectedBlock: {
                        type: 'string',
                        default: ''
                    },
                    customClasses: {
                        type: 'string',
                        default: ''
                    }
                },
                save: function() {
                    return null; // Server-side rendering
                },
                migrate: function(attributes) {
                    // Migriere alte Attribute zu neuen
                    return {
                        ...attributes,
                        blockConfig: {
                            styles: {
                                padding: { top: 20, right: 20, bottom: 20, left: 20 },
                                background: { color: '#ffffff' },
                                border: { width: 0, color: '#dddddd', radius: 0 },
                                text: { color: '#000000', alignment: 'left' }
                            }
                        },
                        blockFeatures: {
                            icon: {
                                enabled: false,
                                value: 'dashicons-admin-generic',
                                position: 'top-left',
                                color: '#333333'
                            }
                        }
                    };
                }
            }
        ],
        
        // Edit-Funktion
        edit: function EditComponent(props) {
            const { attributes, setAttributes, isSelected } = props;
            const { 
                selectedBlock, 
                customClasses, 
                blockConfig, 
                blockFeatures,
                align 
            } = attributes;
            
            // State für verfügbare Blöcke
            const [availableBlocks, setAvailableBlocks] = useState(blockData.blocks || []);
            const [isLoading, setIsLoading] = useState(false);
            
            // Lade Blöcke wenn noch nicht vorhanden
            useEffect(() => {
                if (availableBlocks.length === 0 && blockData.ajaxUrl) {
                    setIsLoading(true);
                    
                    jQuery.ajax({
                        url: blockData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'cbd_get_blocks',
                            nonce: blockData.ajaxNonce
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                setAvailableBlocks(response.data);
                            }
                            setIsLoading(false);
                        },
                        error: function() {
                            console.error('CBD: Fehler beim Laden der Blöcke');
                            setIsLoading(false);
                        }
                    });
                }
            }, []);
            
            // Block-Props mit Klassen
            const containerClasses = [
                'cbd-container-editor',
                selectedBlock ? `cbd-block-${selectedBlock}` : '',
                customClasses || '',
                align ? `align${align}` : ''
            ].filter(Boolean).join(' ');
            
            const blockProps = useBlockProps({
                className: containerClasses
            });
            
            // Container-Styles basierend auf Config
            const containerStyle = {};
            
            if (blockConfig && blockConfig.styles) {
                const styles = blockConfig.styles;
                
                // Padding
                if (styles.padding) {
                    containerStyle.padding = `${styles.padding.top || 20}px ${styles.padding.right || 20}px ${styles.padding.bottom || 20}px ${styles.padding.left || 20}px`;
                }
                
                // Background
                if (styles.background && styles.background.color) {
                    containerStyle.backgroundColor = styles.background.color;
                }
                
                // Text
                if (styles.text) {
                    if (styles.text.color) {
                        containerStyle.color = styles.text.color;
                    }
                    if (styles.text.alignment) {
                        containerStyle.textAlign = styles.text.alignment;
                    }
                }
                
                // Border
                if (styles.border && styles.border.width > 0) {
                    containerStyle.border = `${styles.border.width}px solid ${styles.border.color || '#ddd'}`;
                    if (styles.border.radius > 0) {
                        containerStyle.borderRadius = `${styles.border.radius}px`;
                    }
                }
            }
            
            // Update-Funktionen für Attribute
            const updateBlockConfig = (key, value) => {
                const newConfig = { ...blockConfig };
                const keys = key.split('.');
                let current = newConfig;
                
                for (let i = 0; i < keys.length - 1; i++) {
                    if (!current[keys[i]]) {
                        current[keys[i]] = {};
                    }
                    current = current[keys[i]];
                }
                
                current[keys[keys.length - 1]] = value;
                setAttributes({ blockConfig: newConfig });
            };
            
            const updateBlockFeatures = (key, value) => {
                const newFeatures = { ...blockFeatures };
                const keys = key.split('.');
                let current = newFeatures;
                
                for (let i = 0; i < keys.length - 1; i++) {
                    if (!current[keys[i]]) {
                        current[keys[i]] = {};
                    }
                    current = current[keys[i]];
                }
                
                current[keys[keys.length - 1]] = value;
                setAttributes({ blockFeatures: newFeatures });
            };
            
            // Render
            return createElement(
                Fragment,
                {},
                [
                    // Inspector Controls (Sidebar)
                    createElement(
                        InspectorControls,
                        { key: 'inspector' },
                        [
                            // Design-Auswahl
                            createElement(
                                PanelBody,
                                { 
                                    title: blockData.i18n.design,
                                    initialOpen: true,
                                    key: 'design-panel'
                                },
                                [
                                    createElement(
                                        SelectControl,
                                        {
                                            label: blockData.i18n.selectBlock,
                                            value: selectedBlock,
                                            options: [
                                                { label: '-- ' + blockData.i18n.selectBlock + ' --', value: '' },
                                                ...availableBlocks.map(block => ({
                                                    label: block.name,
                                                    value: block.slug
                                                }))
                                            ],
                                            onChange: (value) => {
                                                setAttributes({ selectedBlock: value });
                                                
                                                // Lade Block-Config wenn Block ausgewählt
                                                const selected = availableBlocks.find(b => b.slug === value);
                                                if (selected) {
                                                    setAttributes({
                                                        blockConfig: selected.config || blockConfig,
                                                        blockFeatures: selected.features || blockFeatures
                                                    });
                                                }
                                            },
                                            __next40pxDefaultSize: true,
                                            __nextHasNoMarginBottom: true,
                                            key: 'block-select'
                                        }
                                    ),
                                    createElement(
                                        TextControl,
                                        {
                                            label: blockData.i18n.customClasses,
                                            value: customClasses,
                                            onChange: (value) => setAttributes({ customClasses: value }),
                                            help: 'Zusätzliche CSS-Klassen (mit Leerzeichen getrennt)',
                                            __next40pxDefaultSize: true,
                                            __nextHasNoMarginBottom: true,
                                            key: 'custom-classes'
                                        }
                                    )
                                ]
                            ),
                            
                            // Styling-Einstellungen
                            createElement(
                                PanelBody,
                                { 
                                    title: 'Styling',
                                    initialOpen: false,
                                    key: 'styling-panel'
                                },
                                [
                                    // Padding
                                    createElement('h3', { key: 'padding-title' }, 'Innenabstand'),
                                    createElement(
                                        RangeControl,
                                        {
                                            label: 'Oben',
                                            value: blockConfig.styles?.padding?.top || 20,
                                            onChange: (value) => updateBlockConfig('styles.padding.top', value),
                                            min: 0,
                                            max: 100,
                                            key: 'padding-top'
                                        }
                                    ),
                                    createElement(
                                        RangeControl,
                                        {
                                            label: 'Rechts',
                                            value: blockConfig.styles?.padding?.right || 20,
                                            onChange: (value) => updateBlockConfig('styles.padding.right', value),
                                            min: 0,
                                            max: 100,
                                            key: 'padding-right'
                                        }
                                    ),
                                    createElement(
                                        RangeControl,
                                        {
                                            label: 'Unten',
                                            value: blockConfig.styles?.padding?.bottom || 20,
                                            onChange: (value) => updateBlockConfig('styles.padding.bottom', value),
                                            min: 0,
                                            max: 100,
                                            key: 'padding-bottom'
                                        }
                                    ),
                                    createElement(
                                        RangeControl,
                                        {
                                            label: 'Links',
                                            value: blockConfig.styles?.padding?.left || 20,
                                            onChange: (value) => updateBlockConfig('styles.padding.left', value),
                                            min: 0,
                                            max: 100,
                                            key: 'padding-left'
                                        }
                                    ),
                                    
                                    // Border
                                    createElement('h3', { key: 'border-title' }, 'Rahmen'),
                                    createElement(
                                        RangeControl,
                                        {
                                            label: 'Rahmenbreite',
                                            value: blockConfig.styles?.border?.width || 0,
                                            onChange: (value) => updateBlockConfig('styles.border.width', value),
                                            min: 0,
                                            max: 10,
                                            key: 'border-width'
                                        }
                                    ),
                                    createElement(
                                        RangeControl,
                                        {
                                            label: 'Eckenradius',
                                            value: blockConfig.styles?.border?.radius || 0,
                                            onChange: (value) => updateBlockConfig('styles.border.radius', value),
                                            min: 0,
                                            max: 50,
                                            key: 'border-radius'
                                        }
                                    )
                                ]
                            ),
                            
                            // Features
                            createElement(
                                PanelBody,
                                { 
                                    title: blockData.i18n.features,
                                    initialOpen: false,
                                    key: 'features-panel'
                                },
                                [
                                    createElement(
                                        ToggleControl,
                                        {
                                            label: 'Icon anzeigen',
                                            checked: blockFeatures.icon?.enabled || false,
                                            onChange: (value) => updateBlockFeatures('icon.enabled', value),
                                            key: 'icon-toggle'
                                        }
                                    ),
                                    blockFeatures.icon?.enabled && [
                                        createElement(
                                            TextControl,
                                            {
                                                label: 'Icon-Klasse',
                                                value: blockFeatures.icon?.value || 'dashicons-admin-generic',
                                                onChange: (value) => updateBlockFeatures('icon.value', value),
                                                help: 'Dashicons-Klasse (z.B. dashicons-admin-generic)',
                                                __next40pxDefaultSize: true,
                                                __nextHasNoMarginBottom: true,
                                                key: 'icon-class'
                                            }
                                        ),
                                        createElement(
                                            SelectControl,
                                            {
                                                label: 'Icon-Position',
                                                value: blockFeatures.icon?.position || 'top-left',
                                                options: [
                                                    { label: 'Oben Links', value: 'top-left' },
                                                    { label: 'Oben Rechts', value: 'top-right' },
                                                    { label: 'Unten Links', value: 'bottom-left' },
                                                    { label: 'Unten Rechts', value: 'bottom-right' }
                                                ],
                                                onChange: (value) => updateBlockFeatures('icon.position', value),
                                                __next40pxDefaultSize: true,
                                                __nextHasNoMarginBottom: true,
                                                key: 'icon-position'
                                            }
                                        )
                                    ]
                                ]
                            )
                        ]
                    ),
                    
                    // Block-Content
                    createElement(
                        'div',
                        { ...blockProps, style: containerStyle, key: 'block-content' },
                        [
                            // Icon anzeigen wenn aktiviert
                            blockFeatures.icon?.enabled && createElement(
                                'span',
                                {
                                    className: `cbd-icon ${blockFeatures.icon.position} dashicons ${blockFeatures.icon.value}`,
                                    style: { 
                                        color: blockFeatures.icon.color || '#333',
                                        position: 'absolute',
                                        fontSize: '24px',
                                        zIndex: 10
                                    },
                                    key: 'icon'
                                }
                            ),
                            
                            // Inner Blocks oder Placeholder
                            createElement(
                                'div',
                                { 
                                    className: 'cbd-container-content',
                                    style: { position: 'relative', minHeight: '60px' },
                                    key: 'content'
                                },
                                createElement(
                                    InnerBlocks,
                                    {
                                        renderAppender: InnerBlocks.ButtonBlockAppender,
                                        template: [
                                            ['core/paragraph', { 
                                                placeholder: blockData.i18n.addContent 
                                            }]
                                        ],
                                        templateLock: false
                                    }
                                )
                            )
                        ]
                    )
                ]
            );
        },
        
        // Save-Funktion
        save: function SaveComponent(props) {
            // Server-Side-Rendering: Nur InnerBlocks speichern
            // Der PHP-Renderer kümmert sich um den Container
            return createElement(InnerBlocks.Content);
        }
    });
    
    // Debug-Ausgabe
    console.log('CBD: Block erfolgreich registriert', wp.blocks.getBlockType('container-block-designer/container'));
    
})(window.wp, window.jQuery);