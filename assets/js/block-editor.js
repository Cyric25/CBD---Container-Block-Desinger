/**
 * Container Block Designer - Block Editor Script
 * Version: 2.5.0
 * 
 * Registriert und verwaltet Container Blocks im Gutenberg Editor
 */

(function(wp, cbdBlockEditor) {
    'use strict';
    
    const { registerBlockType } = wp.blocks;
    const { InnerBlocks, InspectorControls, BlockControls, useBlockProps, PanelColorSettings } = wp.blockEditor;
    const { PanelBody, SelectControl, TextControl, ToggleControl, RangeControl, Button, ToolbarGroup, ToolbarButton } = wp.components;
    const { useState, useEffect, Fragment } = wp.element;
    const { __ } = wp.i18n;
    const { apiFetch } = wp;
    
    // Verfügbare Blocks laden
    let availableBlocks = cbdBlockEditor.blocks || [];
    
    /**
     * Haupt Container Block
     */
    registerBlockType('cbd/container', {
        title: __('Container Block', 'container-block-designer'),
        description: __('Ein anpassbarer Container für Ihre Inhalte', 'container-block-designer'),
        icon: 'layout',
        category: 'container-blocks',
        keywords: ['container', 'wrapper', 'box'],
        supports: {
            align: ['wide', 'full'],
            anchor: true,
            customClassName: true,
            html: false
        },
        attributes: {
            blockId: {
                type: 'number',
                default: 0
            },
            blockName: {
                type: 'string',
                default: ''
            },
            customClasses: {
                type: 'string',
                default: ''
            },
            alignment: {
                type: 'string',
                default: ''
            },
            styles: {
                type: 'object',
                default: {}
            },
            features: {
                type: 'object',
                default: {}
            }
        },
        
        /**
         * Editor-Ansicht
         */
        edit: function(props) {
            const { attributes, setAttributes, className } = props;
            const { blockId, customClasses, alignment, styles, features } = attributes;
            
            const [selectedBlock, setSelectedBlock] = useState(null);
            const [isLoading, setIsLoading] = useState(false);
            
            const blockProps = useBlockProps({
                className: `cbd-container-editor ${className || ''} ${customClasses || ''} ${alignment ? 'align' + alignment : ''}`
            });
            
            // Block-Daten laden wenn blockId sich ändert
            useEffect(() => {
                if (blockId > 0) {
                    setIsLoading(true);
                    
                    // AJAX-Request für Block-Daten
                    jQuery.post(cbdBlockEditor.ajaxUrl, {
                        action: 'cbd_get_block',
                        block_id: blockId,
                        nonce: cbdBlockEditor.nonce
                    }, function(response) {
                        if (response.success && response.data) {
                            setSelectedBlock(response.data);
                            setAttributes({
                                blockName: response.data.name,
                                styles: response.data.styles || {},
                                features: response.data.features || {}
                            });
                        }
                        setIsLoading(false);
                    });
                }
            }, [blockId]);
            
            // Block-Optionen für Select
            const blockOptions = [
                { label: __('-- Block auswählen --', 'container-block-designer'), value: 0 }
            ];
            
            availableBlocks.forEach(block => {
                blockOptions.push({
                    label: block.title,
                    value: parseInt(block.id)
                });
            });
            
            // Inline-Styles generieren
            const containerStyles = {};
            if (styles && styles.padding) {
                containerStyles.paddingTop = (styles.padding.top || 20) + 'px';
                containerStyles.paddingRight = (styles.padding.right || 20) + 'px';
                containerStyles.paddingBottom = (styles.padding.bottom || 20) + 'px';
                containerStyles.paddingLeft = (styles.padding.left || 20) + 'px';
            }
            if (styles && styles.background) {
                containerStyles.backgroundColor = styles.background.color || '#ffffff';
            }
            if (styles && styles.border) {
                if (styles.border.width > 0) {
                    containerStyles.borderWidth = styles.border.width + 'px';
                    containerStyles.borderStyle = styles.border.style || 'solid';
                    containerStyles.borderColor = styles.border.color || '#e0e0e0';
                }
                if (styles.border.radius > 0) {
                    containerStyles.borderRadius = styles.border.radius + 'px';
                }
            }
            
            return (
                <Fragment>
                    {/* Block Controls (Toolbar) */}
                    <BlockControls>
                        <ToolbarGroup>
                            <ToolbarButton
                                icon="admin-appearance"
                                label={__('Block-Einstellungen bearbeiten', 'container-block-designer')}
                                onClick={() => {
                                    if (blockId > 0) {
                                        window.open(cbdBlockEditor.adminUrl + 'admin.php?page=cbd-edit-block&id=' + blockId, '_blank');
                                    }
                                }}
                                disabled={blockId === 0}
                            />
                        </ToolbarGroup>
                    </BlockControls>
                    
                    {/* Inspector Controls (Sidebar) */}
                    <InspectorControls>
                        <PanelBody title={__('Container-Einstellungen', 'container-block-designer')} initialOpen={true}>
                            <SelectControl
                                label={__('Container-Block auswählen', 'container-block-designer')}
                                value={blockId}
                                options={blockOptions}
                                onChange={(value) => setAttributes({ blockId: parseInt(value) })}
                                help={__('Wählen Sie einen vorkonfigurierten Container-Block', 'container-block-designer')}
                            />
                            
                            {selectedBlock && (
                                <div className="cbd-block-info">
                                    <p><strong>{selectedBlock.title}</strong></p>
                                    {selectedBlock.description && (
                                        <p className="description">{selectedBlock.description}</p>
                                    )}
                                </div>
                            )}
                            
                            <TextControl
                                label={__('Zusätzliche CSS-Klassen', 'container-block-designer')}
                                value={customClasses}
                                onChange={(value) => setAttributes({ customClasses: value })}
                                help={__('Eigene CSS-Klassen für erweiterte Anpassungen', 'container-block-designer')}
                            />
                        </PanelBody>
                        
                        {/* Schnell-Anpassungen wenn Block ausgewählt */}
                        {blockId > 0 && styles && (
                            <PanelBody title={__('Schnell-Anpassungen', 'container-block-designer')} initialOpen={false}>
                                <PanelColorSettings
                                    title={__('Farben', 'container-block-designer')}
                                    colorSettings={[
                                        {
                                            value: styles.background?.color,
                                            onChange: (color) => {
                                                const newStyles = { ...styles };
                                                newStyles.background = { ...newStyles.background, color };
                                                setAttributes({ styles: newStyles });
                                            },
                                            label: __('Hintergrundfarbe', 'container-block-designer')
                                        },
                                        {
                                            value: styles.text?.color,
                                            onChange: (color) => {
                                                const newStyles = { ...styles };
                                                newStyles.text = { ...newStyles.text, color };
                                                setAttributes({ styles: newStyles });
                                            },
                                            label: __('Textfarbe', 'container-block-designer')
                                        }
                                    ]}
                                />
                                
                                <RangeControl
                                    label={__('Padding (alle Seiten)', 'container-block-designer')}
                                    value={styles.padding?.top || 20}
                                    onChange={(value) => {
                                        const newStyles = { ...styles };
                                        newStyles.padding = {
                                            top: value,
                                            right: value,
                                            bottom: value,
                                            left: value
                                        };
                                        setAttributes({ styles: newStyles });
                                    }}
                                    min={0}
                                    max={100}
                                />
                            </PanelBody>
                        )}
                        
                        {/* Features wenn Block ausgewählt */}
                        {blockId > 0 && features && (
                            <PanelBody title={__('Features', 'container-block-designer')} initialOpen={false}>
                                {features.icon && (
                                    <ToggleControl
                                        label={__('Icon anzeigen', 'container-block-designer')}
                                        checked={features.icon.enabled}
                                        onChange={(value) => {
                                            const newFeatures = { ...features };
                                            newFeatures.icon.enabled = value;
                                            setAttributes({ features: newFeatures });
                                        }}
                                    />
                                )}
                                
                                {features.collapse && (
                                    <ToggleControl
                                        label={__('Einklappbar', 'container-block-designer')}
                                        checked={features.collapse.enabled}
                                        onChange={(value) => {
                                            const newFeatures = { ...features };
                                            newFeatures.collapse.enabled = value;
                                            setAttributes({ features: newFeatures });
                                        }}
                                    />
                                )}
                                
                                {features.numbering && (
                                    <ToggleControl
                                        label={__('Nummerierung', 'container-block-designer')}
                                        checked={features.numbering.enabled}
                                        onChange={(value) => {
                                            const newFeatures = { ...features };
                                            newFeatures.numbering.enabled = value;
                                            setAttributes({ features: newFeatures });
                                        }}
                                    />
                                )}
                            </PanelBody>
                        )}
                    </InspectorControls>
                    
                    {/* Block-Inhalt */}
                    <div {...blockProps} style={containerStyles}>
                        {isLoading && (
                            <div className="cbd-loading">
                                <span className="spinner is-active"></span>
                                {__('Block wird geladen...', 'container-block-designer')}
                            </div>
                        )}
                        
                        {!isLoading && blockId === 0 && (
                            <div className="cbd-placeholder">
                                <div className="cbd-placeholder-icon">
                                    <span className="dashicons dashicons-layout"></span>
                                </div>
                                <h3>{__('Container Block', 'container-block-designer')}</h3>
                                <p>{__('Wählen Sie einen Container-Block aus den Einstellungen in der Seitenleiste.', 'container-block-designer')}</p>
                            </div>
                        )}
                        
                        {!isLoading && blockId > 0 && (
                            <div className="cbd-container-inner">
                                {/* Features Preview */}
                                {features && features.icon && features.icon.enabled && (
                                    <div className="cbd-feature-preview cbd-icon-preview">
                                        <span className={`dashicons ${features.icon.value || 'dashicons-admin-generic'}`}></span>
                                    </div>
                                )}
                                
                                {features && features.collapse && features.collapse.enabled && (
                                    <div className="cbd-feature-preview cbd-collapse-preview">
                                        <span className="dashicons dashicons-arrow-down"></span>
                                    </div>
                                )}
                                
                                {features && features.numbering && features.numbering.enabled && (
                                    <div className="cbd-feature-preview cbd-numbering-preview">
                                        <span>1.</span>
                                    </div>
                                )}
                                
                                {/* Inner Blocks */}
                                <InnerBlocks
                                    renderAppender={InnerBlocks.ButtonBlockAppender}
                                    placeholder={__('Fügen Sie Inhalte zu diesem Container hinzu...', 'container-block-designer')}
                                />
                            </div>
                        )}
                    </div>
                </Fragment>
            );
        },
        
        /**
         * Gespeicherte Ansicht
         */
        save: function(props) {
            const { attributes } = props;
            const { blockId, customClasses, alignment, styles, features } = attributes;
            
            const blockProps = useBlockProps.save({
                className: `cbd-container-block ${customClasses || ''} ${alignment ? 'align' + alignment : ''}`,
                'data-block-id': blockId
            });
            
            // Inline-Styles
            const containerStyles = {};
            if (styles && styles.padding) {
                containerStyles.paddingTop = (styles.padding.top || 20) + 'px';
                containerStyles.paddingRight = (styles.padding.right || 20) + 'px';
                containerStyles.paddingBottom = (styles.padding.bottom || 20) + 'px';
                containerStyles.paddingLeft = (styles.padding.left || 20) + 'px';
            }
            if (styles && styles.background) {
                containerStyles.backgroundColor = styles.background.color || '#ffffff';
            }
            if (styles && styles.border) {
                if (styles.border.width > 0) {
                    containerStyles.borderWidth = styles.border.width + 'px';
                    containerStyles.borderStyle = styles.border.style || 'solid';
                    containerStyles.borderColor = styles.border.color || '#e0e0e0';
                }
                if (styles.border.radius > 0) {
                    containerStyles.borderRadius = styles.border.radius + 'px';
                }
            }
            
            return (
                <div {...blockProps} style={containerStyles}>
                    <div className="cbd-container-content">
                        <InnerBlocks.Content />
                    </div>
                </div>
            );
        }
    });
    
    // Dynamische Blocks registrieren
    if (availableBlocks && availableBlocks.length > 0) {
        availableBlocks.forEach(function(block) {
            if (block.status === 'active') {
                const blockName = 'cbd/' + block.name.replace(/[^a-z0-9-]/g, '-');
                
                registerBlockType(blockName, {
                    title: block.title,
                    description: block.description,
                    icon: 'layout',
                    category: 'container-blocks',
                    parent: ['cbd/container'],
                    supports: {
                        customClassName: true
                    },
                    edit: function() {
                        return wp.element.createElement('div', { className: 'cbd-dynamic-block' },
                            wp.element.createElement('p', null, block.title)
                        );
                    },
                    save: function() {
                        return null; // Server-side rendering
                    }
                });
            }
        });
    }
    
})(window.wp, window.cbdBlockEditor || {});