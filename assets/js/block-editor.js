(function(wp, $) {
    'use strict';
    
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, InnerBlocks, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, TextControl, ToggleControl, ColorPicker, RangeControl } = wp.components;
    const { Fragment, useState, useEffect } = wp.element;
    const { __ } = wp.i18n;
    
    // Get block data from localized script
    const blockData = window.cbdBlockData || {};
    
    registerBlockType('container-block-designer/container', {
        title: blockData.i18n?.blockTitle || __('Container Block', 'container-block-designer'),
        description: blockData.i18n?.blockDescription || __('Ein anpassbarer Container-Block mit erweiterten Funktionen', 'container-block-designer'),
        icon: 'layout',
        category: 'layout',
        keywords: ['container', 'wrapper', 'section', 'box'],
        supports: {
            html: false,
            className: true,
            anchor: true,
            align: ['wide', 'full'],
            spacing: {
                margin: true,
                padding: true
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
                default: {}
            },
            blockFeatures: {
                type: 'object',
                default: {}
            },
            alignment: {
                type: 'string',
                default: 'none'
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes, className } = props;
            const { selectedBlock, customClasses, blockConfig, blockFeatures, alignment } = attributes;
            
            const [availableBlocks, setAvailableBlocks] = useState([]);
            const [isLoading, setIsLoading] = useState(true);
            const [error, setError] = useState(null);
            
            // Load available blocks on mount
            useEffect(() => {
                if (!blockData.ajaxUrl || !blockData.nonce) {
                    setError('AJAX configuration missing');
                    setIsLoading(false);
                    return;
                }
                
                $.ajax({
                    url: blockData.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'cbd_get_blocks',
                        nonce: blockData.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            setAvailableBlocks(response.data);
                            setError(null);
                        } else {
                            setError(response.data?.message || 'Failed to load blocks');
                        }
                        setIsLoading(false);
                    },
                    error: function(xhr, status, errorThrown) {
                        console.error('CBD: Failed to load blocks', errorThrown);
                        setError('Failed to load blocks: ' + errorThrown);
                        setIsLoading(false);
                    }
                });
            }, []);
            
            // Update block config when selection changes
            useEffect(() => {
                if (selectedBlock && availableBlocks.length > 0) {
                    const foundBlock = availableBlocks.find(b => b.slug === selectedBlock);
                    if (foundBlock) {
                        // Parse config if string
                        let config = foundBlock.config;
                        if (typeof config === 'string') {
                            try {
                                config = JSON.parse(config);
                            } catch(e) {
                                console.error('CBD: Failed to parse config', e);
                                config = {};
                            }
                        }
                        
                        // Parse features if string
                        let features = foundBlock.features;
                        if (typeof features === 'string') {
                            try {
                                features = JSON.parse(features);
                            } catch(e) {
                                console.error('CBD: Failed to parse features', e);
                                features = {};
                            }
                        }
                        
                        setAttributes({
                            blockConfig: config || {},
                            blockFeatures: features || {}
                        });
                    }
                }
            }, [selectedBlock, availableBlocks]);
            
            // Build styles from config
            const styles = blockConfig?.styles || {};
            const containerStyle = {};
            
            // Apply padding
            if (styles.padding) {
                containerStyle.paddingTop = `${styles.padding.top || 20}px`;
                containerStyle.paddingRight = `${styles.padding.right || 20}px`;
                containerStyle.paddingBottom = `${styles.padding.bottom || 20}px`;
                containerStyle.paddingLeft = `${styles.padding.left || 20}px`;
            }
            
            // Apply margin
            if (styles.margin) {
                containerStyle.marginTop = `${styles.margin.top || 0}px`;
                containerStyle.marginRight = `${styles.margin.right || 0}px`;
                containerStyle.marginBottom = `${styles.margin.bottom || 0}px`;
                containerStyle.marginLeft = `${styles.margin.left || 0}px`;
            }
            
            // Apply colors
            if (styles.background?.color) {
                containerStyle.backgroundColor = styles.background.color;
            }
            if (styles.text?.color) {
                containerStyle.color = styles.text.color;
            }
            if (styles.text?.alignment) {
                containerStyle.textAlign = styles.text.alignment;
            }
            
            // Apply border
            if (styles.border?.width && styles.border.width > 0) {
                containerStyle.border = `${styles.border.width}px ${styles.border.style || 'solid'} ${styles.border.color || '#ddd'}`;
                if (styles.border.radius) {
                    containerStyle.borderRadius = `${styles.border.radius}px`;
                }
            }
            
            // Apply shadow
            if (styles.shadow?.enabled) {
                const shadow = styles.shadow;
                containerStyle.boxShadow = `${shadow.x || 0}px ${shadow.y || 2}px ${shadow.blur || 4}px ${shadow.spread || 0}px ${shadow.color || 'rgba(0,0,0,0.1)'}`;
            }
            
            containerStyle.minHeight = '100px';
            containerStyle.position = 'relative';
            
            // Build class names
            const containerClasses = [
                'cbd-container',
                selectedBlock ? `cbd-container-${selectedBlock}` : '',
                alignment && alignment !== 'none' ? `align${alignment}` : '',
                customClasses
            ].filter(Boolean).join(' ');
            
            // Block props with styles
            const blockProps = useBlockProps({
                className: containerClasses,
                style: containerStyle
            });
            
            // Build select options
            const blockOptions = [
                { label: blockData.i18n?.selectBlock || 'Wählen Sie einen Block', value: '' },
                ...availableBlocks.map(b => ({
                    label: b.name,
                    value: b.slug
                }))
            ];
            
            // Get active features for display
            const activeFeatures = [];
            if (blockFeatures?.icon?.enabled) {
                activeFeatures.push('Icon: ' + (blockFeatures.icon.value || 'dashicons-admin-generic'));
            }
            if (blockFeatures?.collapse?.enabled) {
                activeFeatures.push('Collapse: ' + (blockFeatures.collapse.defaultState || 'expanded'));
            }
            if (blockFeatures?.numbering?.enabled) {
                activeFeatures.push('Nummerierung: ' + (blockFeatures.numbering.format || 'numeric'));
            }
            if (blockFeatures?.copyText?.enabled) {
                activeFeatures.push('Text kopieren');
            }
            if (blockFeatures?.screenshot?.enabled) {
                activeFeatures.push('Screenshot');
            }
            
            return (
                <Fragment>
                    <InspectorControls>
                        <PanelBody 
                            title={__('Container Einstellungen', 'container-block-designer')}
                            initialOpen={true}
                        >
                            {error ? (
                                <div style={{ color: 'red', marginBottom: '10px' }}>
                                    <strong>Error:</strong> {error}
                                </div>
                            ) : null}
                            
                            {isLoading ? (
                                <p>{blockData.i18n?.loading || 'Lade Blocks...'}</p>
                            ) : availableBlocks.length === 0 ? (
                                <p>{blockData.i18n?.noBlocks || 'Keine Blocks verfügbar. Bitte erstellen Sie zuerst einen Block im Admin-Bereich.'}</p>
                            ) : (
                                <Fragment>
                                    <SelectControl
                                        label={__('Block-Design', 'container-block-designer')}
                                        value={selectedBlock}
                                        options={blockOptions}
                                        onChange={(value) => setAttributes({ selectedBlock: value })}
                                        help={__('Wählen Sie ein vordefiniertes Container-Design', 'container-block-designer')}
                                    />
                                    
                                    <TextControl
                                        label={__('Zusätzliche CSS-Klassen', 'container-block-designer')}
                                        value={customClasses}
                                        onChange={(value) => setAttributes({ customClasses: value })}
                                        help={__('Fügen Sie eigene CSS-Klassen hinzu (durch Leerzeichen getrennt)', 'container-block-designer')}
                                    />
                                    
                                    {selectedBlock && activeFeatures.length > 0 && (
                                        <div style={{ 
                                            marginTop: '15px',
                                            padding: '10px',
                                            backgroundColor: '#f0f0f0',
                                            borderRadius: '4px'
                                        }}>
                                            <strong>{__('Aktive Features:', 'container-block-designer')}</strong>
                                            <ul style={{ 
                                                marginTop: '8px', 
                                                marginBottom: '0',
                                                fontSize: '12px',
                                                paddingLeft: '20px'
                                            }}>
                                                {activeFeatures.map((feature, index) => (
                                                    <li key={index}>{feature}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </Fragment>
                            )}
                        </PanelBody>
                        
                        {selectedBlock && blockConfig?.styles && (
                            <PanelBody 
                                title={__('Style-Informationen', 'container-block-designer')}
                                initialOpen={false}
                            >
                                <div style={{ fontSize: '12px' }}>
                                    <p><strong>Padding:</strong> {JSON.stringify(styles.padding || {})}</p>
                                    <p><strong>Margin:</strong> {JSON.stringify(styles.margin || {})}</p>
                                    {styles.background?.color && (
                                        <p><strong>Hintergrund:</strong> {styles.background.color}</p>
                                    )}
                                    {styles.border?.width && (
                                        <p><strong>Rahmen:</strong> {styles.border.width}px {styles.border.style || 'solid'}</p>
                                    )}
                                </div>
                            </PanelBody>
                        )}
                    </InspectorControls>
                    
                    <div {...blockProps}>
                        {!selectedBlock ? (
                            <div style={{ 
                                padding: '40px', 
                                textAlign: 'center',
                                backgroundColor: '#f5f5f5',
                                border: '2px dashed #ccc',
                                borderRadius: '4px'
                            }}>
                                <div className="dashicons dashicons-layout" style={{ 
                                    fontSize: '48px',
                                    width: '48px',
                                    height: '48px',
                                    color: '#999',
                                    margin: '0 auto 15px'
                                }}></div>
                                <p style={{ 
                                    margin: '0 0 10px 0', 
                                    fontSize: '16px', 
                                    color: '#333',
                                    fontWeight: 'bold'
                                }}>
                                    {__('Container Block Designer', 'container-block-designer')}
                                </p>
                                <p style={{ 
                                    margin: 0, 
                                    fontSize: '14px', 
                                    color: '#666' 
                                }}>
                                    {__('Bitte wählen Sie ein Container-Design in den Block-Einstellungen', 'container-block-designer')}
                                </p>
                            </div>
                        ) : (
                            <Fragment>
                                {blockFeatures?.icon?.enabled && (
                                    <div className="cbd-icon" style={{
                                        position: 'absolute',
                                        top: '10px',
                                        left: '10px',
                                        fontSize: '24px',
                                        color: blockFeatures.icon.color || '#333'
                                    }}>
                                        <span className={`dashicons ${blockFeatures.icon.value || 'dashicons-admin-generic'}`}></span>
                                    </div>
                                )}
                                <InnerBlocks 
                                    renderAppender={InnerBlocks.ButtonBlockAppender}
                                    template={[
                                        ['core/paragraph', { placeholder: 'Fügen Sie hier Ihren Inhalt ein...' }]
                                    ]}
                                    templateLock={false}
                                />
                            </Fragment>
                        )}
                    </div>
                </Fragment>
            );
        },
        
        save: function(props) {
            const { attributes } = props;
            const { selectedBlock, customClasses, blockConfig, blockFeatures } = attributes;
            
            // Server-side rendering - we just save the inner blocks
            // The PHP renderer will handle the container wrapper
            return <InnerBlocks.Content />;
        }
    });
    
    // Helper function to wait for block type registration
    function waitForBlock(callback, attempts = 0) {
        if (attempts > 20) {
            console.error('CBD: Timeout waiting for block registration');
            return;
        }
        
        const blockType = wp.blocks.getBlockType('container-block-designer/container');
        if (blockType) {
            console.log('CBD: Block registered successfully', blockType);
            callback(blockType);
        } else {
            setTimeout(() => waitForBlock(callback, attempts + 1), 250);
        }
    }
    
    // Enhanced block functionality after registration
    function enhanceBlock(blockType) {
        console.log('CBD: Enhancing block with additional features');
        
        // Add block variations if needed
        if (window.cbdBlockVariations && Array.isArray(window.cbdBlockVariations)) {
            window.cbdBlockVariations.forEach(variation => {
                wp.blocks.registerBlockVariation('container-block-designer/container', variation);
            });
        }
        
        // Add block styles if needed
        if (window.cbdBlockStyles && Array.isArray(window.cbdBlockStyles)) {
            window.cbdBlockStyles.forEach(style => {
                wp.blocks.registerBlockStyle('container-block-designer/container', style);
            });
        }
    }
    
    // Initialize when DOM is ready
    wp.domReady(() => {
        console.log('CBD: Initializing Container Block Designer');
        waitForBlock(enhanceBlock);
        
        // Unregister any existing block with same name to avoid conflicts
        const existingBlock = wp.blocks.getBlockType('container-block-designer/container');
        if (existingBlock && !existingBlock.cbdEnhanced) {
            wp.blocks.unregisterBlockType('container-block-designer/container');
            console.log('CBD: Unregistered existing block for re-registration');
        }
    });
    
})(window.wp, window.jQuery);