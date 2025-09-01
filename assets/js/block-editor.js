/**
 * Container Block Designer - Block Editor JavaScript
 * Version: 2.5.2
 * Fixed: ToggleControl deprecation warning, SelectControl display
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
            customClasses: 'Eigene CSS-Klassen',
            iconEnabled: 'Icon anzeigen',
            collapseEnabled: 'Auf-/Zuklappen aktivieren',
            numberingEnabled: 'Nummerierung aktivieren',
            copyTextEnabled: 'Text kopieren aktivieren',
            screenshotEnabled: 'Screenshot aktivieren'
        }
    };
    
    // Verfügbare Blöcke vorbereiten
    const prepareBlockOptions = () => {
        const options = [
            { label: blockData.i18n.selectBlock, value: '' }
        ];
        
        if (blockData.blocks && Array.isArray(blockData.blocks)) {
            blockData.blocks.forEach(block => {
                if (block && block.slug && block.name) {
                    options.push({
                        label: block.name,
                        value: block.slug
                    });
                }
            });
        }
        
        return options;
    };
    
    // Block Edit Component
    const EditComponent = (props) => {
        const { attributes, setAttributes, isSelected } = props;
        const { 
            selectedBlock = '', 
            customClasses = '',
            blockConfig = {},
            blockFeatures = {}
        } = attributes;
        
        const [availableBlocks, setAvailableBlocks] = useState([]);
        const [isLoading, setIsLoading] = useState(false);
        
        // Lade verfügbare Blöcke
        useEffect(() => {
            if (blockData.blocks && blockData.blocks.length > 0) {
                setAvailableBlocks(blockData.blocks);
            } else {
                // Optional: Lade Blöcke via AJAX wenn nicht vorhanden
                loadBlocksViaAjax();
            }
        }, []);
        
        // AJAX-Funktion zum Laden der Blöcke
        const loadBlocksViaAjax = () => {
            if (!blockData.ajaxUrl) return;
            
            setIsLoading(true);
            
            jQuery.ajax({
                url: blockData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cbd_get_blocks',
                    nonce: blockData.nonce || ''
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
        };
        
        const blockProps = useBlockProps({
            className: `cbd-container ${selectedBlock ? 'cbd-has-block' : 'cbd-no-block'} ${customClasses}`.trim()
        });
        
        // Block Options für SelectControl
        const blockOptions = prepareBlockOptions();
        
        return createElement(
            Fragment,
            null,
            
            // Inspector Controls
            createElement(
                InspectorControls,
                null,
                
                // Haupteinstellungen Panel
                createElement(
                    PanelBody,
                    {
                        title: blockData.i18n.settings,
                        initialOpen: true
                    },
                    
                    // Block-Auswahl Dropdown mit Fix
                    createElement(
                        SelectControl,
                        {
                            label: blockData.i18n.design,
                            value: selectedBlock,
                            options: blockOptions,
                            onChange: (value) => {
                                setAttributes({ selectedBlock: value });
                                
                                // Lade Block-Config wenn Block ausgewählt
                                const selected = availableBlocks.find(b => b.slug === value);
                                if (selected) {
                                    setAttributes({
                                        blockConfig: selected.config || {},
                                        blockFeatures: selected.features || {}
                                    });
                                }
                            },
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }
                    ),
                    
                    // Custom Classes Input
                    createElement(
                        TextControl,
                        {
                            label: blockData.i18n.customClasses,
                            value: customClasses,
                            onChange: (value) => setAttributes({ customClasses: value }),
                            help: 'Zusätzliche CSS-Klassen (mit Leerzeichen getrennt)',
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }
                    )
                ),
                
                // Features Panel mit korrigierten ToggleControls
                createElement(
                    PanelBody,
                    {
                        title: blockData.i18n.features,
                        initialOpen: false
                    },
                    
                    // Icon Feature Toggle
                    createElement(
                        ToggleControl,
                        {
                            label: blockData.i18n.iconEnabled,
                            checked: blockFeatures.icon?.enabled || false,
                            onChange: (value) => {
                                setAttributes({
                                    blockFeatures: {
                                        ...blockFeatures,
                                        icon: {
                                            ...blockFeatures.icon,
                                            enabled: value
                                        }
                                    }
                                });
                            },
                            __nextHasNoMarginBottom: true
                        }
                    ),
                    
                    // Collapse Feature Toggle
                    createElement(
                        ToggleControl,
                        {
                            label: blockData.i18n.collapseEnabled,
                            checked: blockFeatures.collapse?.enabled || false,
                            onChange: (value) => {
                                setAttributes({
                                    blockFeatures: {
                                        ...blockFeatures,
                                        collapse: {
                                            ...blockFeatures.collapse,
                                            enabled: value
                                        }
                                    }
                                });
                            },
                            __nextHasNoMarginBottom: true
                        }
                    ),
                    
                    // Numbering Feature Toggle
                    createElement(
                        ToggleControl,
                        {
                            label: blockData.i18n.numberingEnabled,
                            checked: blockFeatures.numbering?.enabled || false,
                            onChange: (value) => {
                                setAttributes({
                                    blockFeatures: {
                                        ...blockFeatures,
                                        numbering: {
                                            ...blockFeatures.numbering,
                                            enabled: value
                                        }
                                    }
                                });
                            },
                            __nextHasNoMarginBottom: true
                        }
                    ),
                    
                    // Copy Text Feature Toggle
                    createElement(
                        ToggleControl,
                        {
                            label: blockData.i18n.copyTextEnabled,
                            checked: blockFeatures.copyText?.enabled || false,
                            onChange: (value) => {
                                setAttributes({
                                    blockFeatures: {
                                        ...blockFeatures,
                                        copyText: {
                                            ...blockFeatures.copyText,
                                            enabled: value
                                        }
                                    }
                                });
                            },
                            __nextHasNoMarginBottom: true
                        }
                    ),
                    
                    // Screenshot Feature Toggle
                    createElement(
                        ToggleControl,
                        {
                            label: blockData.i18n.screenshotEnabled,
                            checked: blockFeatures.screenshot?.enabled || false,
                            onChange: (value) => {
                                setAttributes({
                                    blockFeatures: {
                                        ...blockFeatures,
                                        screenshot: {
                                            ...blockFeatures.screenshot,
                                            enabled: value
                                        }
                                    }
                                });
                            },
                            __nextHasNoMarginBottom: true
                        }
                    )
                )
            ),
            
            // Block Toolbar
            createElement(
                BlockControls,
                null,
                createElement(
                    ToolbarGroup,
                    null,
                    createElement(
                        ToolbarButton,
                        {
                            icon: 'admin-appearance',
                            label: 'Block-Stil bearbeiten',
                            onClick: () => {
                                // Optional: Öffne Bearbeitungs-Modal
                                console.log('CBD: Block-Stil bearbeiten');
                            }
                        }
                    )
                )
            ),
            
            // Block Content
            createElement(
                'div',
                blockProps,
                
                // Wenn kein Block ausgewählt
                !selectedBlock ? 
                    createElement(
                        Placeholder,
                        {
                            icon: 'layout',
                            label: blockData.i18n.blockTitle,
                            instructions: blockData.i18n.selectBlock
                        },
                        
                        isLoading ? 
                            createElement(Spinner) :
                            createElement(
                                SelectControl,
                                {
                                    value: selectedBlock,
                                    options: blockOptions,
                                    onChange: (value) => {
                                        setAttributes({ selectedBlock: value });
                                    },
                                    __next40pxDefaultSize: true,
                                    __nextHasNoMarginBottom: true
                                }
                            )
                    ) :
                    
                    // InnerBlocks für Content
                    createElement(
                        'div',
                        { 
                            className: 'cbd-content',
                            'data-block-type': selectedBlock 
                        },
                        createElement(InnerBlocks, {
                            renderAppender: InnerBlocks.ButtonBlockAppender
                        })
                    )
            )
        );
    };
    
    // Block-Typ registrieren
    registerBlockType('container-block-designer/container', {
        title: blockData.i18n.blockTitle,
        description: blockData.i18n.blockDescription,
        icon: 'layout',
        category: 'design',
        keywords: ['container', 'wrapper', 'section', 'box', 'layout'],
        supports: {
            align: ['wide', 'full'],
            html: false,
            className: true,
            customClassName: true
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
        edit: EditComponent,
        save: (props) => {
            const { selectedBlock, customClasses } = props.attributes;
            
            const blockProps = useBlockProps.save({
                className: `cbd-container ${selectedBlock ? `cbd-block-${selectedBlock}` : ''} ${customClasses}`.trim()
            });
            
            return createElement(
                'div',
                blockProps,
                createElement(
                    'div',
                    { className: 'cbd-content' },
                    createElement(InnerBlocks.Content)
                )
            );
        }
    });
    
    // Debug-Ausgabe
    console.log('CBD: Block Editor Script geladen - Version 2.5.2');
    
})(window.wp, window.jQuery);