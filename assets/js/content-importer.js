/**
 * Container Block Designer - Content Importer
 *
 * Gutenberg plugin for importing Markdown content with automatic K1/K2/K3 style assignment
 *
 * @package ContainerBlockDesigner
 * @since 2.9.3
 */

(function() {
    const { registerPlugin } = wp.plugins;
    const { PluginMoreMenuItem } = wp.editor || wp.editPost; // WordPress 6.6+ compatibility
    const { Modal, Button, Spinner, Notice, SelectControl, DropZone } = wp.components;
    const { useState, useEffect, createElement: el } = wp.element;
    const { __ } = wp.i18n;
    const { dispatch, select } = wp.data;

    /**
     * Content Importer Modal Component
     */
    const ContentImporterModal = function({ onClose }) {
        const [step, setStep] = useState(1);
        const [fileContent, setFileContent] = useState('');
        const [parsedData, setParsedData] = useState(null);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState(null);
        const [styleMappings, setStyleMappings] = useState({ k1: '', k2: '', k3: '', sources: '' });
        const [availableStyles, setAvailableStyles] = useState([]);
        const [isDragging, setIsDragging] = useState(false);

        // Lade verfügbare Styles beim Mount
        useEffect(function() {
            fetchAvailableStyles();
        }, []);

        /**
         * Lädt verfügbare CDB-Styles
         */
        const fetchAvailableStyles = function() {
            setLoading(true);

            const formData = new FormData();
            formData.append('action', 'cbd_get_style_mappings');
            formData.append('nonce', window.cbdContentImporter.nonce);

            fetch(window.cbdContentImporter.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success) {
                    setAvailableStyles(response.data.styles);
                    // Auto-Suggest
                    const suggestions = response.data.suggestions;
                    setStyleMappings({
                        k1: suggestions.k1 || (response.data.styles[0] ? response.data.styles[0].value : ''),
                        k2: suggestions.k2 || (response.data.styles[0] ? response.data.styles[0].value : ''),
                        k3: suggestions.k3 || (response.data.styles[0] ? response.data.styles[0].value : ''),
                        sources: suggestions.sources || (response.data.styles[0] ? response.data.styles[0].value : '')
                    });
                }
                setLoading(false);
            }).catch(function(err) {
                setError(__('Fehler beim Laden der Styles', 'container-block-designer'));
                setLoading(false);
            });
        };

        /**
         * Parse Markdown Content
         */
        const parseContent = function() {
            if (!fileContent.trim()) {
                setError(__('Bitte Inhalt eingeben oder Datei hochladen', 'container-block-designer'));
                return;
            }

            setLoading(true);
            setError(null);

            const formData = new FormData();
            formData.append('action', 'cbd_parse_import_file');
            formData.append('nonce', window.cbdContentImporter.nonce);
            formData.append('content', fileContent);

            fetch(window.cbdContentImporter.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success) {
                    setParsedData(response.data);
                    setStep(2);
                } else {
                    setError(response.data.message || __('Fehler beim Parsen', 'container-block-designer'));
                }
                setLoading(false);
            }).catch(function(err) {
                setError(__('AJAX-Fehler beim Parsen', 'container-block-designer'));
                setLoading(false);
            });
        };

        /**
         * Konvertiert HTML zu Gutenberg-Blöcken
         */
        const htmlToGutenbergBlocks = function(html) {
            const blocks = [];
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;

            Array.from(tempDiv.children).forEach(function(element) {
                const tagName = element.tagName.toLowerCase();

                // Überschriften (H3-H6)
                if (/^h[3-6]$/.test(tagName)) {
                    blocks.push(wp.blocks.createBlock('core/heading', {
                        content: element.innerHTML,
                        level: parseInt(tagName.substring(1))
                    }));
                }
                // Paragraphen
                else if (tagName === 'p') {
                    const content = element.innerHTML.trim();
                    if (content) {
                        blocks.push(wp.blocks.createBlock('core/paragraph', {
                            content: content
                        }));
                    }
                }
                // Ungeordnete Listen
                else if (tagName === 'ul') {
                    const listItems = Array.from(element.querySelectorAll('li')).map(function(li) {
                        return li.innerHTML;
                    }).join('</li><li>');
                    blocks.push(wp.blocks.createBlock('core/list', {
                        values: '<li>' + listItems + '</li>',
                        ordered: false
                    }));
                }
                // Geordnete Listen
                else if (tagName === 'ol') {
                    const listItems = Array.from(element.querySelectorAll('li')).map(function(li) {
                        return li.innerHTML;
                    }).join('</li><li>');
                    blocks.push(wp.blocks.createBlock('core/list', {
                        values: '<li>' + listItems + '</li>',
                        ordered: true
                    }));
                }
                // Fallback: Als Paragraph einfügen
                else {
                    const content = element.innerHTML.trim();
                    if (content) {
                        blocks.push(wp.blocks.createBlock('core/paragraph', {
                            content: content
                        }));
                    }
                }
            });

            return blocks;
        };

        /**
         * Fügt Blöcke in den Editor ein
         */
        const insertBlocks = function() {
            if (!parsedData || !parsedData.sections) {
                return;
            }

            const blocks = [];
            const editor = dispatch('core/block-editor');

            parsedData.sections.forEach(function(section) {
                // Bestimme Style basierend auf Kompetenzstufe
                const selectedStyle = styleMappings[section.competence];

                if (!selectedStyle) {
                    return; // Überspringe wenn kein Style zugewiesen
                }

                // Konvertiere HTML zu Gutenberg-Blöcken
                const innerBlocks = htmlToGutenbergBlocks(section.content);

                // Fallback: Wenn keine Blöcke erstellt wurden, verwende Classic Editor
                const finalInnerBlocks = innerBlocks.length > 0 ? innerBlocks : [
                    wp.blocks.createBlock('core/freeform', {
                        content: section.content
                    })
                ];

                // Erstelle CDB Container Block mit modernen Gutenberg-Blöcken
                const containerBlock = wp.blocks.createBlock(
                    'container-block-designer/container',
                    {
                        selectedBlock: selectedStyle,
                        blockTitle: section.blockTitle
                    },
                    finalInnerBlocks
                );

                blocks.push(containerBlock);
            });

            if (blocks.length === 0) {
                setError(__('Keine Blöcke zum Einfügen', 'container-block-designer'));
                return;
            }

            // Füge Blöcke an Cursor-Position ein
            const selectedBlockClientId = select('core/block-editor').getSelectedBlockClientId();

            if (selectedBlockClientId) {
                const selectedBlockIndex = select('core/block-editor').getBlockIndex(selectedBlockClientId);
                editor.insertBlocks(blocks, selectedBlockIndex + 1);
            } else {
                editor.insertBlocks(blocks);
            }

            // Schließe Modal
            onClose();
        };

        /**
         * Datei-Upload Handler
         */
        const handleFileUpload = function(files) {
            if (!files || files.length === 0) return;

            const file = files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                setFileContent(e.target.result);
                setIsDragging(false);
            };

            reader.onerror = function() {
                setError(__('Fehler beim Lesen der Datei', 'container-block-designer'));
                setIsDragging(false);
            };

            reader.readAsText(file);
        };

        /**
         * Render Step 1: File Upload
         */
        const renderStep1 = function() {
            return el('div', { className: 'cbd-importer-step' },
                el('h2', null, __('Schritt 1: Inhalt laden', 'container-block-designer')),

                error && el(Notice, { status: 'error', isDismissible: false }, error),

                el('div', {
                    className: 'cbd-importer-dropzone' + (isDragging ? ' is-dragging' : ''),
                    onDragEnter: function() { setIsDragging(true); },
                    onDragLeave: function() { setIsDragging(false); }
                },
                    el(DropZone, { onFilesDrop: handleFileUpload }),
                    el('div', { className: 'cbd-importer-dropzone-content' },
                        el('p', { className: 'cbd-importer-dropzone-icon' }, '📄'),
                        el('p', { className: 'cbd-importer-dropzone-text' },
                            __('Markdown-Datei hierher ziehen', 'container-block-designer')
                        ),
                        el('p', { className: 'cbd-importer-dropzone-or' }, __('oder', 'container-block-designer')),
                        el('label', { className: 'cbd-importer-file-button' },
                            __('Datei auswählen', 'container-block-designer'),
                            el('input', {
                                type: 'file',
                                accept: '.md,.txt',
                                onChange: function(e) { handleFileUpload(e.target.files); },
                                style: { display: 'none' }
                            })
                        )
                    )
                ),

                el('div', { className: 'cbd-importer-textarea-section' },
                    el('label', { htmlFor: 'cbd-content-textarea' },
                        __('Oder Text direkt einfügen:', 'container-block-designer')
                    ),
                    el('textarea', {
                        id: 'cbd-content-textarea',
                        className: 'cbd-importer-textarea',
                        rows: 10,
                        placeholder: __('# Thema\n\n## Basiswissen\n\n### Überschrift\nInhalt...', 'container-block-designer'),
                        value: fileContent,
                        onChange: function(e) { setFileContent(e.target.value); }
                    })
                ),

                el('div', { className: 'cbd-importer-actions' },
                    el(Button, {
                        variant: 'secondary',
                        onClick: onClose
                    }, __('Abbrechen', 'container-block-designer')),
                    el(Button, {
                        variant: 'primary',
                        onClick: parseContent,
                        disabled: loading || !fileContent.trim()
                    }, loading ? el(Spinner) : __('Weiter', 'container-block-designer'))
                )
            );
        };

        /**
         * Render Step 2: Style Mapping
         */
        const renderStep2 = function() {
            if (!parsedData) return null;

            const stats = parsedData.stats;

            return el('div', { className: 'cbd-importer-step' },
                el('h2', null, __('Schritt 2: Styles zuweisen', 'container-block-designer')),

                el('div', { className: 'cbd-importer-stats' },
                    el('p', null,
                        __('Gefundene Blöcke:', 'container-block-designer'),
                        ' ',
                        el('strong', null, stats.total)
                    ),
                    el('ul', null,
                        el('li', { className: 'cbd-importer-stat-k1' },
                            el('span', { className: 'cbd-importer-stat-badge' }, 'K1'),
                            stats.k1 + ' ' + __('Blöcke', 'container-block-designer')
                        ),
                        el('li', { className: 'cbd-importer-stat-k2' },
                            el('span', { className: 'cbd-importer-stat-badge' }, 'K2'),
                            stats.k2 + ' ' + __('Blöcke', 'container-block-designer')
                        ),
                        el('li', { className: 'cbd-importer-stat-k3' },
                            el('span', { className: 'cbd-importer-stat-badge' }, 'K3'),
                            stats.k3 + ' ' + __('Blöcke', 'container-block-designer')
                        ),
                        stats.sources > 0 && el('li', { className: 'cbd-importer-stat-sources' },
                            el('span', { className: 'cbd-importer-stat-badge' }, '📚'),
                            stats.sources + ' ' + __('Quellenangaben', 'container-block-designer')
                        )
                    )
                ),

                el('div', { className: 'cbd-importer-mappings' },
                    stats.k1 > 0 && el('div', { className: 'cbd-importer-mapping-row cbd-importer-mapping-k1' },
                        el('span', { className: 'cbd-importer-mapping-badge' }, 'K1'),
                        el(SelectControl, {
                            label: __('Style für Basiswissen', 'container-block-designer'),
                            value: styleMappings.k1,
                            options: availableStyles,
                            onChange: function(value) { setStyleMappings(Object.assign({}, styleMappings, { k1: value })); }
                        })
                    ),

                    stats.k2 > 0 && el('div', { className: 'cbd-importer-mapping-row cbd-importer-mapping-k2' },
                        el('span', { className: 'cbd-importer-mapping-badge' }, 'K2'),
                        el(SelectControl, {
                            label: __('Style für Erweitertes Wissen', 'container-block-designer'),
                            value: styleMappings.k2,
                            options: availableStyles,
                            onChange: function(value) { setStyleMappings(Object.assign({}, styleMappings, { k2: value })); }
                        })
                    ),

                    stats.k3 > 0 && el('div', { className: 'cbd-importer-mapping-row cbd-importer-mapping-k3' },
                        el('span', { className: 'cbd-importer-mapping-badge' }, 'K3'),
                        el(SelectControl, {
                            label: __('Style für Vertiefendes Wissen', 'container-block-designer'),
                            value: styleMappings.k3,
                            options: availableStyles,
                            onChange: function(value) { setStyleMappings(Object.assign({}, styleMappings, { k3: value })); }
                        })
                    ),

                    stats.sources > 0 && el('div', { className: 'cbd-importer-mapping-row cbd-importer-mapping-sources' },
                        el('span', { className: 'cbd-importer-mapping-badge' }, '📚'),
                        el(SelectControl, {
                            label: __('Style für Quellenangaben', 'container-block-designer'),
                            value: styleMappings.sources,
                            options: availableStyles,
                            onChange: function(value) { setStyleMappings(Object.assign({}, styleMappings, { sources: value })); }
                        })
                    )
                ),

                el('div', { className: 'cbd-importer-actions' },
                    el(Button, {
                        variant: 'secondary',
                        onClick: function() { setStep(1); }
                    }, __('Zurück', 'container-block-designer')),
                    el(Button, {
                        variant: 'primary',
                        onClick: insertBlocks
                    }, __('Blöcke einfügen', 'container-block-designer'))
                )
            );
        };

        return el(Modal, {
            title: __('Inhalt importieren (K1/K2/K3)', 'container-block-designer'),
            onRequestClose: onClose,
            className: 'cbd-importer-modal'
        },
            step === 1 ? renderStep1() : renderStep2()
        );
    };

    /**
     * Main Plugin Component
     */
    const ContentImporterPlugin = function() {
        const [isOpen, setIsOpen] = useState(false);

        return el('div', null,
            el(PluginMoreMenuItem, {
                icon: 'upload',
                onClick: function() { setIsOpen(true); }
            }, __('Inhalt importieren (K1/K2/K3)', 'container-block-designer')),

            isOpen && el(ContentImporterModal, {
                onClose: function() { setIsOpen(false); }
            })
        );
    };

    // Plugin registrieren
    registerPlugin('cbd-content-importer', {
        render: ContentImporterPlugin,
        icon: 'upload'
    });
})();
