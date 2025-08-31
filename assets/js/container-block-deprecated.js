/**
 * Container Block Deprecated Versions
 * Fügen Sie dies am Ende der container-block.js hinzu oder als separate Datei
 */

(function(wp) {
    const { InnerBlocks } = wp.blockEditor;
    
    // Add deprecated versions to handle old saved blocks
    wp.hooks.addFilter(
        'blocks.registerBlockType',
        'cbd/add-deprecated',
        function(settings, name) {
            if (name !== 'container-block-designer/container') {
                return settings;
            }
            
            settings.deprecated = [
                {
                    // Version 1: Old format with inline styles
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
                    
                    save: function(props) {
                        const { attributes } = props;
                        const { selectedBlock, customClasses } = attributes;
                        
                        const containerClasses = [
                            'cbd-container',
                            selectedBlock ? `cbd-container-${selectedBlock}` : '',
                            customClasses
                        ].filter(Boolean).join(' ');
                        
                        return wp.element.createElement(
                            'div',
                            {
                                className: `wp-block-container-block-designer-container ${containerClasses}`,
                                'data-block-type': selectedBlock || undefined
                            },
                            wp.element.createElement(InnerBlocks.Content)
                        );
                    },
                    
                    migrate: function(attributes) {
                        // Migrate to new format with blockConfig and blockFeatures
                        return {
                            ...attributes,
                            blockConfig: {},
                            blockFeatures: {}
                        };
                    }
                },
                {
                    // Version 2: With CSS variables but without features
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
                        }
                    },
                    
                    save: function(props) {
                        const { attributes } = props;
                        const { selectedBlock, customClasses, blockConfig } = attributes;
                        
                        const containerClasses = [
                            'cbd-container',
                            selectedBlock ? `cbd-container-${selectedBlock}` : '',
                            customClasses
                        ].filter(Boolean).join(' ');
                        
                        const styles = blockConfig?.styles || {};
                        const styleAttr = {
                            '--cbd-padding-top': `${styles.padding?.top || 20}px`,
                            '--cbd-padding-right': `${styles.padding?.right || 20}px`,
                            '--cbd-padding-bottom': `${styles.padding?.bottom || 20}px`,
                            '--cbd-padding-left': `${styles.padding?.left || 20}px`,
                            '--cbd-background-color': styles.background?.color || '#ffffff',
                            '--cbd-text-color': styles.text?.color || '#000000',
                            '--cbd-border-radius': `${styles.border?.radius || 0}px`
                        };
                        
                        // Convert style object to string
                        const styleString = Object.entries(styleAttr)
                            .map(([key, value]) => `${key}:${value}`)
                            .join(';');
                        
                        return wp.element.createElement(
                            'div',
                            {
                                className: `wp-block-container-block-designer-container ${containerClasses}`,
                                style: styleString,
                                'data-block-type': selectedBlock || undefined
                            },
                            wp.element.createElement(InnerBlocks.Content)
                        );
                    },
                    
                    migrate: function(attributes) {
                        // Add features to existing attributes
                        return {
                            ...attributes,
                            blockFeatures: {}
                        };
                    }
                }
            ];
            
            return settings;
        }
    );
    
    console.log('✅ Deprecated versions registered for migration');
    
})(window.wp);