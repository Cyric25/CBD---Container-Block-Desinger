/**
 * Container Block Designer - Block Editor Script
 * 
 * Simplified version that works with server-side registration
 * 
 * @version 3.0.0
 */

(function(wp, $) {
    'use strict';
    
    const { InnerBlocks, InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, TextControl, Notice, Placeholder, Button } = wp.components;
    const { Fragment, useState, useEffect, createElement: el } = wp.element;
    const { __ } = wp.i18n;
    
    // Get block data
    const blockData = window.cbdBlockData || {};
    
    // Log for debugging
    function log(message, data) {
        if (blockData.debug) {
            console.log('[CBD Block]', message, data || '');
        }
    }
    
    // Wait for block registration
    function waitForBlockRegistration(callback) {
        const checkInterval = setInterval(() => {
            const block = wp.blocks.getBlockType('container-block-designer/container');
            if (block) {
                clearInterval(checkInterval);
                callback(block);
            }
        }, 100);
        
        // Timeout after 5 seconds
        setTimeout(() => clearInterval(checkInterval), 5000);
    }
    
    // Enhance block after registration
    waitForBlockRegistration((block) => {
        log('Block found, enhancing...', block);
        
        // Update edit function
        wp.blocks.reregisterBlockType('container-block-designer/container', {
            ...block,
            
            edit: function(props) {
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
                            setAvailableBlocks(data);
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
                        const block = availableBlocks.find(b => b.slug === selectedBlock);
                        if (block) {
                            setAttributes({
                                blockConfig: block.config || {},
                                blockFeatures: block.features || {}
                            });
                        }
                    }
                }, [selectedBlock, availableBlocks]);
                
                // Build styles
                const styles = blockConfig?.styles || {};
                const containerStyle = {};
                
                // Apply styles
                if (styles.padding) {
                    containerStyle.padding = `${styles.padding.top || 20}px ${styles.padding.right || 20}px ${styles.padding.bottom || 20}px ${styles.padding.left || 20}px`;
                }
                
                if (styles.background?.color) {
                    containerStyle.backgroundColor = styles.background.color;
                }
                
                if (styles.text?.color) {
                    containerStyle.color = styles.text.color;
                }
                
                if (styles.text?.alignment) {
                    containerStyle.textAlign = styles.text.alignment;
                }
                
                if (styles.border?.width > 0) {
                    containerStyle.border = `${styles.border.width}px solid ${styles.border.color || '#ddd'}`;
                    if (styles.border.radius) {
                        containerStyle.borderRadius = `${styles.border.radius}px`;
                    }
                }
                
                containerStyle.minHeight = '100px';
                
                // Block props
                const blockProps = useBlockProps({
                    className: `cbd-container ${selectedBlock ? 'cbd-container-' + selectedBlock : ''} ${customClasses}`.trim(),
                    style: containerStyle
                });
                
                // Build select options
                const blockOptions = [
                    { label: blockData.i18n?.selectBlock || 'Select a block', value: '' },
                    ...availableBlocks.map(block => ({
                        label: block.name,
                        value: block.slug
                    }))
                ];
                
                return el(
                    Fragment,
                    {},
                    // Inspector Controls
                    el(
                        InspectorControls,
                        {},
                        el(
                            PanelBody,
                            { title: 'Container Settings', initialOpen: true },
                            el(SelectControl, {
                                label: blockData.i18n?.selectBlock || 'Select Block Type',
                                value: selectedBlock,
                                options: blockOptions,
                                onChange: (value) => setAttributes({ selectedBlock: value })
                            }),
                            el(TextControl, {
                                label: blockData.i18n?.customClasses || 'Custom CSS Classes',
                                value: customClasses,
                                onChange: (value) => setAttributes({ customClasses: value })
                            })
                        )
                    ),
                    // Block Content
                    el(
                        'div',
                        blockProps,
                        !selectedBlock ? 
                            el(
                                Placeholder,
                                {
                                    icon: 'layout',
                                    label: blockData.i18n?.blockTitle || 'Container Block',
                                    instructions: 'Select a container type from the sidebar'
                                }
                            ) :
                            el(InnerBlocks)
                    )
                );
            },
            
            save: function(props) {
                const { attributes } = props;
                const { selectedBlock, customClasses } = attributes;
                
                const blockProps = useBlockProps.save({
                    className: `cbd-container ${selectedBlock ? 'cbd-container-' + selectedBlock : ''} ${customClasses}`.trim()
                });
                
                return el('div', blockProps, el(InnerBlocks.Content));
            }
        });
        
        log('✅ Block enhanced successfully!');
    });
    
})(window.wp, window.jQuery);