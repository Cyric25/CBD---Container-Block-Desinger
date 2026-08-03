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
     * Spezialwert: Abschnitt ohne Container-Block einfügen (nur Inhalt).
     * Ermöglicht den Import auch dann, wenn für einen Abschnitt kein
     * passendes Block-Design existiert oder noch keines angelegt wurde.
     */
    const NO_CONTAINER = '__none__';

    /**
     * Kategorien in fester Reihenfolge, inkl. 'other' für Abschnitte ohne
     * erkennbare Kompetenzstufe (H2 ohne K1/K2/K3-Schlüsselwort, Inhalt ohne
     * Überschriften, Präambeln …).
     */
    const CATEGORIES = [
        { key: 'k1', badge: 'K1', label: __('Style für Basiswissen', 'container-block-designer') },
        { key: 'k2', badge: 'K2', label: __('Style für Erweitertes Wissen', 'container-block-designer') },
        { key: 'k3', badge: 'K3', label: __('Style für Vertiefendes Wissen', 'container-block-designer') },
        { key: 'sources', badge: '📚', label: __('Style für Quellenangaben', 'container-block-designer') },
        { key: 'other', badge: '?', label: __('Style für Abschnitte ohne Kompetenzstufe', 'container-block-designer') }
    ];

    /**
     * Content Importer Modal Component
     */
    const ContentImporterModal = function({ onClose }) {
        const [step, setStep] = useState(1);
        const [fileContent, setFileContent] = useState('');
        const [parsedData, setParsedData] = useState(null);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState(null);
        const [styleMappings, setStyleMappings] = useState({
            k1: '', k2: '', k3: '', sources: '', other: ''
        });
        const [availableStyles, setAvailableStyles] = useState([]);
        const [hasStyles, setHasStyles] = useState(true);
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
                    // Option "ohne Container" immer anbieten — damit lässt sich
                    // Inhalt auch ohne (passendes) Block-Design importieren.
                    const styles = (response.data.styles || []).slice();
                    styles.push({
                        value: NO_CONTAINER,
                        label: __('— ohne Container (nur Inhalt) —', 'container-block-designer')
                    });
                    setAvailableStyles(styles);
                    setHasStyles(response.data.hasStyles !== false);

                    // Auto-Suggest; ohne Vorschlag/ohne Designs: kein Container
                    const suggestions = response.data.suggestions || {};
                    const fallback = (response.data.styles && response.data.styles[0])
                        ? response.data.styles[0].value
                        : NO_CONTAINER;
                    const mappings = {};
                    CATEGORIES.forEach(function(cat) {
                        mappings[cat.key] = suggestions[cat.key] || fallback;
                    });
                    setStyleMappings(mappings);
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
                // Tabellen
                else if (tagName === 'table') {
                    const thead = element.querySelector('thead');
                    const tbody = element.querySelector('tbody');

                    const head = [];
                    const body = [];

                    // Parse Header
                    if (thead) {
                        const headerRow = thead.querySelector('tr');
                        if (headerRow) {
                            const headerCells = Array.from(headerRow.querySelectorAll('th')).map(function(th) {
                                return { content: th.innerHTML, tag: 'th' };
                            });
                            if (headerCells.length > 0) {
                                head.push({ cells: headerCells });
                            }
                        }
                    }

                    // Parse Body
                    if (tbody) {
                        Array.from(tbody.querySelectorAll('tr')).forEach(function(tr) {
                            const rowCells = Array.from(tr.querySelectorAll('td')).map(function(td) {
                                return { content: td.innerHTML, tag: 'td' };
                            });
                            if (rowCells.length > 0) {
                                body.push({ cells: rowCells });
                            }
                        });
                    }

                    // Erstelle Gutenberg Table Block
                    blocks.push(wp.blocks.createBlock('core/table', {
                        head: head,
                        body: body,
                        hasFixedLayout: false
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

            // Slugs, die es tatsächlich als Block-Design gibt — ein Container
            // mit unbekanntem Slug würde im Frontend "Block nicht gefunden"
            // rendern, daher fallen solche Abschnitte auf "ohne Container".
            const knownSlugs = availableStyles
                .map(function(s) { return s.value; })
                .filter(function(v) { return v !== NO_CONTAINER; });

            parsedData.sections.forEach(function(section) {
                // Bestimme Style basierend auf Kategorie (k1/k2/k3/sources/other)
                let selectedStyle = styleMappings[section.competence];

                // Kein/unbekannter Style → ohne Container einfügen, statt den
                // Abschnitt stillschweigend zu verwerfen (früheres Verhalten).
                if (!selectedStyle || knownSlugs.indexOf(selectedStyle) === -1) {
                    selectedStyle = NO_CONTAINER;
                }

                // Konvertiere HTML zu Gutenberg-Blöcken
                const innerBlocks = htmlToGutenbergBlocks(section.content);

                // Fallback: Wenn keine Blöcke erstellt wurden, verwende Classic Editor
                const finalInnerBlocks = innerBlocks.length > 0 ? innerBlocks : [
                    wp.blocks.createBlock('core/freeform', {
                        content: section.content
                    })
                ];

                if (selectedStyle === NO_CONTAINER) {
                    // Ohne Container: Überschrift als Heading-Block voranstellen,
                    // damit die Gliederung erhalten bleibt und der Abschnitt
                    // später manuell in einen Container gepackt werden kann.
                    if (section.blockTitle) {
                        blocks.push(wp.blocks.createBlock('core/heading', {
                            content: section.blockTitle,
                            level: 3
                        }));
                    }
                    finalInnerBlocks.forEach(function(b) { blocks.push(b); });
                    return;
                }

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

            // Zähler je Kategorie (0 = Kategorie kommt in dieser Datei nicht vor)
            const countFor = function(key) { return stats[key] || 0; };

            return el('div', { className: 'cbd-importer-step' },
                el('h2', null, __('Schritt 2: Styles zuweisen', 'container-block-designer')),

                error && el(Notice, { status: 'error', isDismissible: false }, error),

                // Hinweis, wenn noch keine Block-Designs angelegt sind
                !hasStyles && el(Notice, { status: 'warning', isDismissible: false },
                    __('Es sind noch keine Block-Designs angelegt. Der Inhalt wird ohne Container eingefügt und kann später Containern zugewiesen werden.', 'container-block-designer')
                ),

                // Hinweis, wenn Abschnitte ohne erkennbare Kompetenzstufe dabei sind
                countFor('other') > 0 && el(Notice, { status: 'info', isDismissible: false },
                    countFor('other') + ' ' + __('Abschnitt(e) ohne erkennbare Kompetenzstufe gefunden (keine H2 mit „Basiswissen“/„Erweitertes Wissen“/„Vertiefendes Wissen“). Sie werden trotzdem importiert — Style unten wählbar.', 'container-block-designer')
                ),

                el('div', { className: 'cbd-importer-stats' },
                    el('p', null,
                        __('Gefundene Blöcke:', 'container-block-designer'),
                        ' ',
                        el('strong', null, stats.total)
                    ),
                    el('ul', null,
                        CATEGORIES.filter(function(cat) { return countFor(cat.key) > 0; }).map(function(cat) {
                            return el('li', {
                                key: 'stat-' + cat.key,
                                className: 'cbd-importer-stat-' + cat.key
                            },
                                el('span', { className: 'cbd-importer-stat-badge' }, cat.badge),
                                countFor(cat.key) + ' ' + __('Blöcke', 'container-block-designer')
                            );
                        })
                    )
                ),

                el('div', { className: 'cbd-importer-mappings' },
                    CATEGORIES.filter(function(cat) { return countFor(cat.key) > 0; }).map(function(cat) {
                        return el('div', {
                            key: 'map-' + cat.key,
                            className: 'cbd-importer-mapping-row cbd-importer-mapping-' + cat.key
                        },
                            el('span', { className: 'cbd-importer-mapping-badge' }, cat.badge),
                            el(SelectControl, {
                                label: cat.label,
                                value: styleMappings[cat.key],
                                options: availableStyles,
                                onChange: function(value) {
                                    const next = Object.assign({}, styleMappings);
                                    next[cat.key] = value;
                                    setStyleMappings(next);
                                }
                            })
                        );
                    })
                ),

                // Vorschau: welche Abschnitte wurden erkannt und wie werden sie eingefügt
                el('details', { className: 'cbd-importer-preview' },
                    el('summary', null, __('Erkannte Abschnitte anzeigen', 'container-block-designer')),
                    el('ul', { className: 'cbd-importer-preview-list' },
                        parsedData.sections.map(function(section, i) {
                            const style = styleMappings[section.competence];
                            const noContainer = !style || style === NO_CONTAINER;
                            return el('li', { key: 'sec-' + i },
                                el('code', null, section.competence.toUpperCase()),
                                ' ',
                                el('strong', null, section.blockTitle || __('(ohne Titel)', 'container-block-designer')),
                                noContainer
                                    ? ' — ' + __('ohne Container', 'container-block-designer')
                                    : ' — ' + style
                            );
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
