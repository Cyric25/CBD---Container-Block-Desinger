/**
 * Container Block Designer - Block Editor Script
 * Version: 3.0.1 - Fixed
 */

(function(wp, $) {
    'use strict';
    
    const { InnerBlocks, InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, TextControl, Notice, Placeholder, Button } = wp.components;
    const { Fragment, useState, useEffect, createElement: el } = wp.element;
    const { __ } = wp.i18n;
    
    // Get block data
    const blockData = window.cbdBlockData || {};
    
    // Debug logging
    function log(message, data) {
        if (blockData.debug || window.cbdDebug) {
            console.log('[CBD Block]', message, data || '');
        }
    }
    
    // Wait for block to be registered
    function waitForBlock(callback) {
        let attempts = 0;
        const maxAttempts = 50;
        
        const checkInterval = setInterval(() => {
            attempts++;
            const block = wp.blocks.getBlockType('container-block-designer/container');
            
            if (block) {
                clearInterval(checkInterval);
                log('Block found, enhancing...', block);
                callback(block);
            } else if (attempts >= maxAttempts) {
                clearInterval(checkInterval);
                log('Block not found after max attempts');
            }
        }, 100);
    }
    
    // Enhance the block with full functionality
    function enhanceBlock(block) {
        // Store original functions
        const originalEdit = block.edit;
        const originalSave = block.save;
        
        // Create enhanced edit function
        const enhancedEdit = function(props) {
            const { attributes, setAttributes } = props;
            const { selectedBlock, customClasses, blockConfig, blockFeatures } = attributes;
            const [availableBlocks, setAvailableBlocks] = useState(blockData.blocks || []);
            const [loading, setLoading] = useState(false);
            
            // Load blocks if not available
            useEffect(() => {
                if (!availableBlocks.length && blockData.restUrl) {
                    setLoading(true);
                    
                    fetch(blockData.restUrl + 'blocks', {
                        headers: {
                            'X-WP-Nonce': blockData.nonce
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        setAvailableBlocks(data || []);
                        setLoading(false);
                    })
                    .catch(err => {
                        log('Error loading blocks:', err);
                        setLoading(false);
                    });
                }
            }, []);
            
            // Update config when block changes
            useEffect(() => {
                if (selectedBlock && availableBlocks.length) {
                    const foundBlock = availableBlocks.find(b => b.slug === selectedBlock);
                    if (foundBlock) {
                        // Parse config if string
                        let config = foundBlock.config;
                        if (typeof config === 'string') {
                            try {
                                config = JSON.parse(config);
                            } catch(e) {
                                config = {};
                            }
                        }
                        
                        // Parse features if string
                        let features = foundBlock.features;
                        if (typeof features === 'string') {
                            try {
                                features = JSON.parse(features);
                            } catch(e) {
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
            if (styles.border?.width > 0) {
                containerStyle.border = `${styles.border.width}px solid ${styles.border.color || '#ddd'}`;
                if (styles.border.radius) {
                    containerStyle.borderRadius = `${styles.border.radius}px`;
                }
            }
            
            containerStyle.minHeight = '100px';
            containerStyle.position = 'relative';
            
            // Build class names
            const containerClasses = [
                'cbd-container',
                selectedBlock ? `cbd-container-${selectedBlock}` : '',
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
            
            return el(
                Fragment,
                {},
                // Inspector Controls
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { 
                            title: __('Container Einstellungen', 'container-block-designer'),
                            initialOpen: true 
                        },
                        // Block Selection
                        el(SelectControl, {
                            label: __('Block-Typ', 'container-block-designer'),
                            value: selectedBlock,
                            options: blockOptions,
                            onChange: (value) => setAttributes({ selectedBlock: value }),
                            help: __('Wählen Sie einen vorkonfigurierten Container-Block', 'container-block-designer')
                        }),
                        // Custom Classes
                        el(TextControl, {
                            label: __('CSS-Klassen', 'container-block-designer'),
                            value: customClasses,
                            onChange: (value) => setAttributes({ customClasses: value }),
                            help: __('Zusätzliche CSS-Klassen (durch Leerzeichen getrennt)', 'container-block-designer')
                        }),
                        // Features Display
                        activeFeatures.length > 0 && el(
                            'div',
                            { 
                                style: { 
                                    marginTop: '15px',
                                    padding: '10px',
                                    background: '#f0f0f1',
                                    borderRadius: '4px'
                                }
                            },
                            el('strong', {}, __('Aktive Features:', 'container-block-designer')),
                            el(
                                'ul',
                                { style: { margin: '5px 0 0 20px', fontSize: '12px' } },
                                activeFeatures.map((feature, i) => 
                                    el('li', { key: i }, feature)
                                )
                            )
                        )
                    )
                ),
                // Block Content
                el(
                    'div',
                    blockProps,
                    !selectedBlock ? 
                        // Placeholder
                        el(
                            Placeholder,
                            {
                                icon: 'layout',
                                label: __('Container Block', 'container-block-designer'),
                                instructions: __('Wählen Sie einen Container-Typ in der Seitenleiste', 'container-block-designer')
                            },
                            !loading && availableBlocks.length === 0 && el(
                                Notice,
                                { status: 'warning', isDismissible: false },
                                __('Keine Container-Blocks verfügbar. Bitte erstellen Sie zuerst einen Block.', 'container-block-designer')
                            )
                        ) :
                        // Content with InnerBlocks
                        el(
                            Fragment,
                            {},
                            // Feature indicators
                            (blockFeatures?.icon?.enabled || blockFeatures?.collapse?.enabled) && el(
                                'div',
                                {
                                    style: {
                                        display: 'flex',
                                        alignItems: 'center',
                                        marginBottom: '10px',
                                        padding: '8px',
                                        background: 'rgba(0,0,0,0.02)',
                                        borderRadius: '4px'
                                    }
                                },
                                blockFeatures?.icon?.enabled && el(
                                    'span',
                                    {
                                        className: `dashicons ${blockFeatures.icon.value || 'dashicons-admin-generic'}`,
                                        style: {
                                            fontSize: '20px',
                                            marginRight: '10px',
                                            color: blockFeatures.icon.color || '#007cba'
                                        }
                                    }
                                ),
                                blockFeatures?.collapse?.enabled && el(
                                    'span',
                                    {
                                        style: {
                                            marginLeft: 'auto',
                                            fontSize: '11px',
                                            background: '#fff',
                                            padding: '2px 6px',
                                            borderRadius: '3px',
                                            border: '1px solid #ddd'
                                        }
                                    },
                                    'Collapse aktiv'
                                )
                            ),
                            // InnerBlocks
                            el(InnerBlocks, {
                                template: [
                                    ['core/paragraph', { 
                                        placeholder: __('Inhalt hier eingeben...', 'container-block-designer')
                                    }]
                                ]
                            })
                        )
                )
            );
        };
        
        // Enhanced save function
        const enhancedSave = function(props) {
            const { attributes } = props;
            const { selectedBlock, customClasses, blockFeatures } = attributes;
            
            // Build class names
            const containerClasses = [
                'cbd-container',
                selectedBlock ? `cbd-container-${selectedBlock}` : '',
                customClasses
            ].filter(Boolean).join(' ');
            
            // Build data attributes
            const dataAttributes = {};
            if (selectedBlock) {
                dataAttributes['data-block-type'] = selectedBlock;
            }
            
            // Add feature data attributes
            if (blockFeatures?.icon?.enabled) {
                dataAttributes['data-icon'] = 'true';
                dataAttributes['data-icon-value'] = blockFeatures.icon.value || 'dashicons-admin-generic';
            }
            if (blockFeatures?.collapse?.enabled) {
                dataAttributes['data-collapse'] = 'true';
                dataAttributes['data-collapse-default'] = blockFeatures.collapse.defaultState || 'expanded';
            }
            if (blockFeatures?.numbering?.enabled) {
                dataAttributes['data-numbering'] = 'true';
                dataAttributes['data-numbering-format'] = blockFeatures.numbering.format || 'numeric';
            }
            if (blockFeatures?.copyText?.enabled) {
                dataAttributes['data-copy-text'] = 'true';
            }
            if (blockFeatures?.screenshot?.enabled) {
                dataAttributes['data-screenshot'] = 'true';
            }
            
            const blockProps = useBlockProps.save({
                className: containerClasses,
                ...dataAttributes
            });
            
            return el('div', blockProps, el(InnerBlocks.Content));
        };
        
        // Update block type
        wp.blocks.unregisterBlockType('container-block-designer/container');
        wp.blocks.registerBlockType('container-block-designer/container', {
            ...block,
            edit: enhancedEdit,
            save: enhancedSave
        });
        
        log('✅ Block successfully enhanced!');
    }
    
    // Initialize when ready
    wp.domReady(() => {
        waitForBlock(enhanceBlock);
    });
    
})(window.wp, window.jQuery);