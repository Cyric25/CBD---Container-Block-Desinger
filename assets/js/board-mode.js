/**
 * Container Block Designer - Board Mode (Tafel-Modus) v3.0
 * Multi-Layer Canvas System with Background Picker, Hexagon Grid, and Advanced Tools
 *
 * @package ContainerBlockDesigner
 * @since 2.9.1
 * @updated 3.0.0 - Complete rewrite with multi-layer canvas system
 */

(function() {
    'use strict';

    window.CBDBoardMode = {

        // State
        overlay: null,

        // Multi-layer canvases
        backgroundCanvas: null,
        backgroundCtx: null,
        gridCanvas: null,
        gridCtx: null,
        drawingCanvas: null,
        drawingCtx: null,

        isDrawing: false,
        currentTool: 'pen',
        currentColor: '#000000',

        // Tool-spezifische Größen (unabhängig für jedes Tool)
        penWidth: 3,
        eraserStrokeWidth: 8,
        eraserPointWidth: 15,
        highlighterWidth: 20,
        highlighterOpacity: 0.3, // 0.0 - 1.0 (0% - 100%)

        lineWidth: 3, // Aktuelle Breite (wird beim Tool-Wechsel aktualisiert)
        containerId: null,
        boardColor: '#ffffff',
        fontSize: 150, // Textgröße in Prozent (150% = 1.5x normal, besser lesbar auf Tafel)
        lastX: 0,
        lastY: 0,
        resizeObserver: null,
        boundHandlers: {},

        // Palm rejection: Track active pointers
        activePointers: new Set(),

        // Stroke-based drawing: Store all strokes for stroke-eraser
        strokes: [],
        currentStroke: null,

        // Grid state: 'off', 'horizontal', 'vertical'
        gridMode: 'off',
        gridSize: 30, // Hexagon-Radius in Pixeln
        gridOffsetX: 0, // Horizontaler Offset in Pixeln
        gridOffsetY: 0, // Vertikaler Offset in Pixeln

        // Eraser mode: 'stroke' or 'point'
        eraserMode: 'stroke',

        // Classroom System state
        classId: null,
        pageId: null,
        stableContainerId: null,
        ajaxUrl: null,
        nonce: null,
        isSaving: false,
        classes: [],
        isBehandeltSet: false, // Einmalig pro Session als behandelt markieren

        // Preset board colors (for background)
        boardPresetColors: [
            '#ffffff', // Weiß (default)
            '#1a472a', // Grün
            '#1c1c1c'  // Schwarz
        ],

        // Preset pen colors
        presetColors: [
            '#000000', // Schwarz
            '#ffffff', // Weiß
            '#ff0000', // Rot
            '#0000ff', // Blau
            '#ffff00', // Gelb
            '#00ff00'  // Grün
        ],

        /**
         * Tafel-Modus oeffnen
         */
        open: function(containerId, contentHtml, boardColor, options) {
            // Verhindere doppeltes Oeffnen
            if (this.overlay) {
                return;
            }

            this.containerId = containerId;
            this.boardColor = boardColor || '#1a472a';

            // Classroom-Optionen setzen
            options = options || {};
            this.stableContainerId = options.stableContainerId || null;
            this.pageId = options.pageId || null;
            this.ajaxUrl = options.ajaxUrl || null;
            this.nonce = options.nonce || null;
            this.classes = options.classes || [];
            this.classId = null;
            this.isSaving = false;
            this.isBehandeltSet = false;

            // Wenn Klassen vorhanden: Selektor zeigen, sonst direkt oeffnen
            if (this.classes.length > 0 && this.ajaxUrl) {
                var self = this;
                this.showClassSelector(function(selectedClassId) {
                    self.classId = selectedClassId;
                    self._openOverlay(contentHtml);
                });
            } else {
                this._openOverlay(contentHtml);
            }
        },

        /**
         * Internes Oeffnen des Overlays
         */
        _openOverlay: function(contentHtml) {
            // Tool-Einstellungen aus localStorage laden
            this.loadToolSettings();

            // Overlay-DOM erstellen
            this.createOverlayDOM(contentHtml);

            // An Body anhaengen
            document.body.appendChild(this.overlay);

            // Scroll sperren
            document.body.style.overflow = 'hidden';

            // Canvas-Elemente initialisieren
            this.initCanvases();
            this.resizeAllCanvases();

            // Zeichnung laden
            this.loadDrawing();

            // Events binden
            this.bindEvents();

        },

        /**
         * Tafel-Modus schliessen
         */
        close: function() {
            if (!this.overlay) return;

            // Zeichnung speichern
            this.saveDrawing();

            // Closing-Animation
            this.overlay.classList.add('cbd-board-closing');

            var self = this;
            setTimeout(function() {
                self.destroy();
            }, 200);
        },

        /**
         * Aufraeumen und Overlay entfernen
         */
        destroy: function() {
            // Events entfernen
            this.unbindEvents();

            // ResizeObserver stoppen
            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }

            // Overlay entfernen
            if (this.overlay && this.overlay.parentNode) {
                this.overlay.parentNode.removeChild(this.overlay);
            }

            // Scroll freigeben
            document.body.style.overflow = '';

            // State zuruecksetzen
            this.overlay = null;
            this.backgroundCanvas = null;
            this.backgroundCtx = null;
            this.gridCanvas = null;
            this.gridCtx = null;
            this.drawingCanvas = null;
            this.drawingCtx = null;
            this.isDrawing = false;
            this.containerId = null;
            this.classId = null;
            this.pageId = null;
            this.stableContainerId = null;
            this.isSaving = false;
            this.showGrid = false;

            // Palm Rejection: Aktive Pointer löschen
            this.activePointers.clear();

            // Stroke-basiertes Radieren: Striche löschen
            this.strokes = [];
            this.currentStroke = null;
        },

        /**
         * Overlay-DOM erstellen mit Multi-Layer-Canvas
         */
        createOverlayDOM: function(contentHtml) {
            var overlay = document.createElement('div');
            overlay.className = 'cbd-board-overlay';
            overlay.id = 'cbd-board-overlay';

            // Header-Titel
            var titleExtra = '';
            if (this.classId) {
                var cls = this.classes.find(function(c) { return c.id == this.classId; }.bind(this));
                if (cls) {
                    titleExtra = ' <span class="cbd-board-class-badge">' + this._escHtml(cls.name) + '</span>';
                }
            }

            // Preset Pen Color Buttons HTML
            var presetColorsHtml = '';
            this.presetColors.forEach(function(color) {
                var borderColor = color === '#ffffff' ? '#ccc' : 'rgba(255, 255, 255, 0.5)';
                presetColorsHtml += '<button class="cbd-board-preset-color" data-color="' + color + '" style="background-color: ' + color + '; border-color: ' + borderColor + ';" title="' + color + '"></button>';
            });

            // Board Background Preset Buttons (for canvas overlay)
            var boardPresetHtml = '';
            this.boardPresetColors.forEach(function(color) {
                var label = color === '#ffffff' ? 'Weiß' : (color === '#1a472a' ? 'Grün' : 'Schwarz');
                var borderColor = color === '#ffffff' ? '#999' : 'rgba(255, 255, 255, 0.8)';
                boardPresetHtml += '<button class="cbd-board-bg-preset-btn" data-color="' + color + '" style="background-color: ' + color + '; border-color: ' + borderColor + ';" title="' + label + '">' + label + '</button>';
            });

            overlay.innerHTML =
                '<div class="cbd-board-header">' +
                    '<span class="cbd-board-title">' +
                        '<span class="dashicons dashicons-welcome-write-blog"></span>' +
                        'Tafel-Modus' + titleExtra +
                    '</span>' +
                    '<div class="cbd-board-header-actions">' +
                        '<span class="cbd-board-save-status" id="cbd-board-save-status"></span>' +
                        '<button class="cbd-board-close" title="Tafel-Modus beenden">&times;</button>' +
                    '</div>' +
                '</div>' +
                '<div class="cbd-board-split">' +
                    '<div class="cbd-board-content"></div>' +
                    '<div class="cbd-board-canvas-area">' +
                        '<div class="cbd-board-canvas-container">' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-background"></canvas>' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-grid"></canvas>' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-drawing"></canvas>' +
                            '<div class="cbd-board-color-picker-overlay">' +
                                '<div class="cbd-board-color-picker-label">Tafelfarbe:</div>' +
                                '<div class="cbd-board-bg-preset-btns">' + boardPresetHtml + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="cbd-board-toolbar">' +
                            // Tools
                            '<button class="cbd-board-tool active" data-tool="pen" title="Stift">' +
                                '<span class="dashicons dashicons-edit"></span>' +
                            '</button>' +
                            '<button class="cbd-board-tool" data-tool="highlighter" title="Textmarkierer">' +
                                '<span class="dashicons dashicons-marker"></span>' +
                            '</button>' +
                            '<button class="cbd-board-tool" data-tool="eraser-stroke" title="Strich-Radierer">' +
                                '<span class="dashicons dashicons-editor-removeformatting"></span>' +
                            '</button>' +
                            '<button class="cbd-board-tool" data-tool="eraser-point" title="Punkt-Radierer">' +
                                '<span class="dashicons dashicons-dismiss"></span>' +
                            '</button>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Colors
                            '<input type="color" class="cbd-board-color" value="#000000" title="Stiftfarbe">' +
                            '<div class="cbd-board-preset-colors">' + presetColorsHtml + '</div>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Line width
                            '<input type="range" class="cbd-board-width" min="1" max="20" value="3" title="Stiftdicke">' +
                            '<span class="cbd-board-width-display">3px</span>' +
                            // Highlighter opacity (nur für Textmarker sichtbar)
                            '<div class="cbd-board-opacity-control" style="display: none; align-items: center; gap: 8px;">' +
                                '<span class="cbd-board-separator"></span>' +
                                '<label class="cbd-board-opacity-label">🎨</label>' +
                                '<input type="range" class="cbd-board-opacity" min="10" max="100" value="30" step="5" title="Textmarker-Transparenz">' +
                                '<span class="cbd-board-opacity-display">30%</span>' +
                            '</div>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Grid toggle
                            '<button class="cbd-board-grid-toggle" title="Hexagon-Gitter ein/aus">' +
                                '<span class="dashicons dashicons-grid-view"></span>' +
                            '</button>' +
                            '<label class="cbd-board-grid-label">Größe:</label>' +
                            '<input type="range" class="cbd-board-grid-size" min="15" max="60" value="30" title="Gitter-Größe">' +
                            '<span class="cbd-board-grid-size-display">30</span>' +
                            '<label class="cbd-board-grid-label">X:</label>' +
                            '<input type="range" class="cbd-board-grid-offset-x" min="-100" max="100" value="0" title="Horizontale Position">' +
                            '<span class="cbd-board-grid-offset-x-display">0</span>' +
                            '<label class="cbd-board-grid-label">Y:</label>' +
                            '<input type="range" class="cbd-board-grid-offset-y" min="-100" max="100" value="0" title="Vertikale Position">' +
                            '<span class="cbd-board-grid-offset-y-display">0</span>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Zeichnung löschen
                            '<button class="cbd-board-clear" title="Zeichnung löschen">' +
                                '<span class="dashicons dashicons-trash"></span>' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            // Block-Inhalt einfuegen
            var contentArea = overlay.querySelector('.cbd-board-content');
            contentArea.innerHTML = contentHtml;

            // Interaktive Blöcke initialisieren (Scripts ausführen)
            this.initializeInteractiveBlocks(contentArea);

            // Textgrößen-Steuerung über dem Content-Bereich hinzufügen
            contentArea.style.position = 'relative';  // Für absolute Positionierung der Steuerung
            var fontControl = document.createElement('div');
            fontControl.className = 'cbd-board-font-size-control';
            fontControl.innerHTML =
                '<label class="cbd-board-font-label">📝</label>' +
                '<input type="range" class="cbd-board-font-size" min="100" max="300" value="' + this.fontSize + '" step="10" title="Textgröße">' +
                '<span class="cbd-board-font-size-display">' + this.fontSize + '%</span>';
            contentArea.insertBefore(fontControl, contentArea.firstChild);

            // Standard-Textgröße anwenden (150% für bessere Lesbarkeit auf Tafel)
            contentArea.style.fontSize = (this.fontSize / 100) + 'em';

            this.overlay = overlay;
        },

        /**
         * Canvas-Elemente initialisieren
         */
        initCanvases: function() {
            var canvases = this.overlay.querySelectorAll('.cbd-board-canvas');
            this.backgroundCanvas = canvases[0];
            this.gridCanvas = canvases[1];
            this.drawingCanvas = canvases[2];

            this.backgroundCtx = this.backgroundCanvas.getContext('2d');
            this.gridCtx = this.gridCanvas.getContext('2d');
            this.drawingCtx = this.drawingCanvas.getContext('2d');
        },

        /**
         * Alle Canvas-Groessen anpassen
         */
        resizeAllCanvases: function() {
            if (!this.backgroundCanvas) return;

            var container = this.backgroundCanvas.parentElement;
            var width = container.clientWidth;
            var height = container.clientHeight;

            // Zeichnung vom Drawing Canvas sichern
            var imageData = null;
            if (this.drawingCanvas.width > 0 && this.drawingCanvas.height > 0) {
                try {
                    imageData = this.drawingCtx.getImageData(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);
                } catch (e) {
                    // Canvas war leer
                }
            }

            // Alle Canvas-Groessen setzen
            [this.backgroundCanvas, this.gridCanvas, this.drawingCanvas].forEach(function(canvas) {
                canvas.width = width;
                canvas.height = height;
            });

            // Background neu zeichnen
            this.redrawBackground();

            // Grid neu zeichnen (falls aktiv)
            if (this.showGrid) {
                this.redrawGrid();
            }

            // Zeichnung wiederherstellen
            if (imageData) {
                this.drawingCtx.putImageData(imageData, 0, 0);
            }
        },

        /**
         * Hintergrund neu zeichnen
         */
        redrawBackground: function() {
            if (!this.backgroundCtx || !this.backgroundCanvas) return;

            this.backgroundCtx.fillStyle = this.boardColor;
            this.backgroundCtx.fillRect(0, 0, this.backgroundCanvas.width, this.backgroundCanvas.height);
        },

        /**
         * Hexagon-Gitter zeichnen (horizontal oder vertical)
         */
        redrawGrid: function() {
            if (!this.gridCtx || !this.gridCanvas) return;

            // Grid canvas leeren
            this.gridCtx.clearRect(0, 0, this.gridCanvas.width, this.gridCanvas.height);

            if (this.gridMode === 'off') return;

            var width = this.gridCanvas.width;
            var height = this.gridCanvas.height;

            // Grid-Style (hellgrau, auf allen Hintergründen sichtbar)
            this.gridCtx.strokeStyle = 'rgba(150, 150, 150, 0.3)';
            this.gridCtx.lineWidth = 1;

            if (this.gridMode === 'horizontal') {
                // Horizontal: flache Kante seitlich (pointy-top)
                this.drawHorizontalGrid(width, height, this.gridSize);
            } else if (this.gridMode === 'vertical') {
                // Vertical: flache Kante unten (flat-top)
                this.drawVerticalGrid(width, height, this.gridSize);
            }
        },

        /**
         * Horizontal Grid: flache Kante seitlich (pointy-top)
         */
        drawHorizontalGrid: function(width, height, hexSize) {
            var hexWidth = hexSize * 2;
            var hexHeight = Math.sqrt(3) * hexSize;
            var hexHorizDist = hexWidth * 3 / 4;
            var hexVertDist = hexHeight;

            for (var row = -1; row < Math.ceil(height / hexVertDist) + 1; row++) {
                for (var col = -1; col < Math.ceil(width / hexHorizDist) + 1; col++) {
                    var x = col * hexHorizDist + this.gridOffsetX;
                    var y = row * hexVertDist + this.gridOffsetY;

                    // Jede zweite Spalte versetzt
                    if (col % 2 === 1) {
                        y += hexVertDist / 2;
                    }

                    this.drawHexagonPointyTop(x, y, hexSize);
                }
            }
        },

        /**
         * Vertical Grid: flache Kante unten (flat-top)
         */
        drawVerticalGrid: function(width, height, hexSize) {
            var hexHeight = hexSize * 2;
            var hexWidth = Math.sqrt(3) * hexSize;
            var hexVertDist = hexHeight * 3 / 4;
            var hexHorizDist = hexWidth;

            for (var row = -1; row < Math.ceil(height / hexVertDist) + 1; row++) {
                for (var col = -1; col < Math.ceil(width / hexHorizDist) + 1; col++) {
                    var x = col * hexHorizDist + this.gridOffsetX;
                    var y = row * hexVertDist + this.gridOffsetY;

                    // Jede zweite Reihe versetzt
                    if (row % 2 === 1) {
                        x += hexHorizDist / 2;
                    }

                    this.drawHexagonFlatTop(x, y, hexSize);
                }
            }
        },

        /**
         * Hexagon mit Spitze oben (pointy-top) - für Horizontal-Grid
         */
        drawHexagonPointyTop: function(centerX, centerY, radius) {
            this.gridCtx.beginPath();
            for (var i = 0; i < 6; i++) {
                var angle = (Math.PI / 3) * i;
                var x = centerX + radius * Math.cos(angle);
                var y = centerY + radius * Math.sin(angle);
                if (i === 0) {
                    this.gridCtx.moveTo(x, y);
                } else {
                    this.gridCtx.lineTo(x, y);
                }
            }
            this.gridCtx.closePath();
            this.gridCtx.stroke();
        },

        /**
         * Hexagon mit flacher Kante oben (flat-top) - für Vertical-Grid
         */
        drawHexagonFlatTop: function(centerX, centerY, radius) {
            this.gridCtx.beginPath();
            for (var i = 0; i < 6; i++) {
                // +Math.PI/6 dreht das Hexagon um 30° für flat-top Orientierung
                var angle = (Math.PI / 3) * i + Math.PI / 6;
                var x = centerX + radius * Math.cos(angle);
                var y = centerY + radius * Math.sin(angle);
                if (i === 0) {
                    this.gridCtx.moveTo(x, y);
                } else {
                    this.gridCtx.lineTo(x, y);
                }
            }
            this.gridCtx.closePath();
            this.gridCtx.stroke();
        },

        /**
         * Grid-Modus wechseln: off -> horizontal -> vertical -> off
         */
        toggleGrid: function() {
            if (this.gridMode === 'off') {
                this.gridMode = 'horizontal';
            } else if (this.gridMode === 'horizontal') {
                this.gridMode = 'vertical';
            } else {
                this.gridMode = 'off';
            }

            this.redrawGrid();

            // Button-Status und Text aktualisieren
            var btn = this.overlay.querySelector('.cbd-board-grid-toggle');
            if (btn) {
                if (this.gridMode === 'off') {
                    btn.classList.remove('active');
                    btn.title = 'Hexagon-Gitter ein/aus';
                } else if (this.gridMode === 'horizontal') {
                    btn.classList.add('active');
                    btn.title = 'Horizontal (flache Kante seitlich)';
                } else if (this.gridMode === 'vertical') {
                    btn.classList.add('active');
                    btn.title = 'Vertical (flache Kante unten)';
                }
            }
        },

        /**
         * Hintergrundfarbe aendern
         */
        setBoardColor: function(color) {
            this.boardColor = color;
            this.redrawBackground();
        },

        /**
         * Alle Event-Handler binden
         */
        bindEvents: function() {
            var self = this;

            // Gebundene Handler speichern
            this.boundHandlers = {
                pointerDown: function(e) { self.onPointerDown(e); },
                pointerMove: function(e) { self.onPointerMove(e); },
                pointerUp: function(e) { self.onPointerUp(e); },
                keyDown: function(e) { self.onKeyDown(e); }
            };

            // Drawing Canvas Events (nur auf dem obersten Layer)
            this.drawingCanvas.addEventListener('pointerdown', this.boundHandlers.pointerDown);
            this.drawingCanvas.addEventListener('pointermove', this.boundHandlers.pointerMove);
            this.drawingCanvas.addEventListener('pointerup', this.boundHandlers.pointerUp);
            this.drawingCanvas.addEventListener('pointerleave', this.boundHandlers.pointerUp);
            this.drawingCanvas.addEventListener('pointercancel', this.boundHandlers.pointerUp);

            // ESC-Taste
            document.addEventListener('keydown', this.boundHandlers.keyDown);

            // Close-Button
            var closeBtn = this.overlay.querySelector('.cbd-board-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    self.close();
                });
            }

            // Tool-Buttons
            var toolButtons = this.overlay.querySelectorAll('.cbd-board-tool');
            toolButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tool = this.getAttribute('data-tool');
                    self.setTool(tool);

                    toolButtons.forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                });
            });

            // Pen Color
            var colorInput = this.overlay.querySelector('.cbd-board-color');
            if (colorInput) {
                colorInput.addEventListener('input', function() {
                    self.setColor(this.value);
                });
            }

            // Pen Color Presets
            var presetButtons = this.overlay.querySelectorAll('.cbd-board-preset-colors .cbd-board-preset-color');
            presetButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var color = this.getAttribute('data-color');
                    self.setColor(color);
                    if (colorInput) colorInput.value = color;
                });
            });

            // Board Background Color Preset Buttons (on canvas overlay)
            var bgPresetBtns = this.overlay.querySelectorAll('.cbd-board-bg-preset-btn');
            bgPresetBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var color = this.getAttribute('data-color');
                    self.setBoardColor(color);
                });
            });

            // Line Width
            var widthInput = this.overlay.querySelector('.cbd-board-width');
            var widthDisplay = this.overlay.querySelector('.cbd-board-width-display');
            if (widthInput) {
                widthInput.addEventListener('input', function() {
                    var w = parseInt(this.value, 10);
                    self.setLineWidth(w);
                    if (widthDisplay) {
                        widthDisplay.textContent = w + 'px';
                    }
                });
            }

            // Highlighter Opacity
            var opacityInput = this.overlay.querySelector('.cbd-board-opacity');
            var opacityDisplay = this.overlay.querySelector('.cbd-board-opacity-display');
            if (opacityInput) {
                opacityInput.addEventListener('input', function() {
                    var percent = parseInt(this.value, 10);
                    var opacity = percent / 100;
                    self.setHighlighterOpacity(opacity);
                    if (opacityDisplay) {
                        opacityDisplay.textContent = percent + '%';
                    }
                });
            }

            // Font Size
            var fontSizeInput = this.overlay.querySelector('.cbd-board-font-size');
            var fontSizeDisplay = this.overlay.querySelector('.cbd-board-font-size-display');
            if (fontSizeInput) {
                fontSizeInput.addEventListener('input', function() {
                    var size = parseInt(this.value, 10);
                    self.setFontSize(size);
                    if (fontSizeDisplay) {
                        fontSizeDisplay.textContent = size + '%';
                    }
                });
            }

            // Grid Toggle
            var gridToggle = this.overlay.querySelector('.cbd-board-grid-toggle');
            if (gridToggle) {
                gridToggle.addEventListener('click', function() {
                    self.toggleGrid();
                });
            }

            // Grid Size
            var gridSizeInput = this.overlay.querySelector('.cbd-board-grid-size');
            var gridSizeDisplay = this.overlay.querySelector('.cbd-board-grid-size-display');
            if (gridSizeInput) {
                gridSizeInput.addEventListener('input', function() {
                    var size = parseInt(this.value, 10);
                    self.gridSize = size;
                    self.redrawGrid();
                    if (gridSizeDisplay) {
                        gridSizeDisplay.textContent = size;
                    }
                });
            }

            // Grid Offset X
            var gridOffsetXInput = this.overlay.querySelector('.cbd-board-grid-offset-x');
            var gridOffsetXDisplay = this.overlay.querySelector('.cbd-board-grid-offset-x-display');
            if (gridOffsetXInput) {
                gridOffsetXInput.addEventListener('input', function() {
                    var offset = parseInt(this.value, 10);
                    self.gridOffsetX = offset;
                    self.redrawGrid();
                    if (gridOffsetXDisplay) {
                        gridOffsetXDisplay.textContent = offset;
                    }
                });
            }

            // Grid Offset Y
            var gridOffsetYInput = this.overlay.querySelector('.cbd-board-grid-offset-y');
            var gridOffsetYDisplay = this.overlay.querySelector('.cbd-board-grid-offset-y-display');
            if (gridOffsetYInput) {
                gridOffsetYInput.addEventListener('input', function() {
                    var offset = parseInt(this.value, 10);
                    self.gridOffsetY = offset;
                    self.redrawGrid();
                    if (gridOffsetYDisplay) {
                        gridOffsetYDisplay.textContent = offset;
                    }
                });
            }

            // Clear Button
            var clearBtn = this.overlay.querySelector('.cbd-board-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    self.showClearConfirm();
                });
            }

            // ResizeObserver
            if (typeof ResizeObserver !== 'undefined') {
                this.resizeObserver = new ResizeObserver(function() {
                    self.resizeAllCanvases();
                });
                this.resizeObserver.observe(this.backgroundCanvas.parentElement);
            }
        },

        /**
         * Event-Handler entfernen
         */
        unbindEvents: function() {
            if (this.drawingCanvas && this.boundHandlers.pointerDown) {
                this.drawingCanvas.removeEventListener('pointerdown', this.boundHandlers.pointerDown);
                this.drawingCanvas.removeEventListener('pointermove', this.boundHandlers.pointerMove);
                this.drawingCanvas.removeEventListener('pointerup', this.boundHandlers.pointerUp);
                this.drawingCanvas.removeEventListener('pointerleave', this.boundHandlers.pointerUp);
                this.drawingCanvas.removeEventListener('pointercancel', this.boundHandlers.pointerUp);
            }

            if (this.boundHandlers.keyDown) {
                document.removeEventListener('keydown', this.boundHandlers.keyDown);
            }

            this.boundHandlers = {};
        },

        // =============================================
        // Klassen-Selektor
        // =============================================

        showClassSelector: function(callback) {
            var self = this;

            var selectorOverlay = document.createElement('div');
            selectorOverlay.className = 'cbd-board-confirm-overlay';

            var optionsHtml = '<button class="cbd-class-option" data-class-id="0">' +
                '<span class="dashicons dashicons-admin-users"></span> Persönlich (lokal)' +
                '</button>';

            this.classes.forEach(function(cls) {
                optionsHtml += '<button class="cbd-class-option cbd-class-option-server" data-class-id="' + cls.id + '">' +
                    '<span class="dashicons dashicons-groups"></span> ' + self._escHtml(cls.name) +
                    '</button>';
            });

            selectorOverlay.innerHTML =
                '<div class="cbd-board-confirm-dialog cbd-class-selector-dialog">' +
                    '<h4>Klasse wählen</h4>' +
                    '<p>Wählen Sie, wo die Zeichnung gespeichert werden soll:</p>' +
                    '<div class="cbd-class-options">' + optionsHtml + '</div>' +
                    '<div class="cbd-board-confirm-actions">' +
                        '<button class="cbd-board-confirm-cancel">Abbrechen</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(selectorOverlay);

            // Class option buttons
            selectorOverlay.querySelectorAll('.cbd-class-option').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var classId = parseInt(this.getAttribute('data-class-id')) || null;
                    document.body.removeChild(selectorOverlay);
                    callback(classId === 0 ? null : classId);
                });
            });

            // Cancel
            selectorOverlay.querySelector('.cbd-board-confirm-cancel').addEventListener('click', function() {
                document.body.removeChild(selectorOverlay);
            });

            // Backdrop click = cancel
            selectorOverlay.addEventListener('click', function(e) {
                if (e.target === selectorOverlay) {
                    document.body.removeChild(selectorOverlay);
                }
            });
        },

        // =============================================
        // Zeichenwerkzeuge
        // =============================================

        setTool: function(tool) {
            this.currentTool = tool;

            // Set eraser mode
            if (tool === 'eraser-stroke') {
                this.eraserMode = 'stroke';
            } else if (tool === 'eraser-point') {
                this.eraserMode = 'point';
            }

            if (this.drawingCanvas) {
                if (tool === 'eraser-stroke' || tool === 'eraser-point') {
                    this.drawingCanvas.classList.add('eraser-active');
                } else {
                    this.drawingCanvas.classList.remove('eraser-active');
                }
            }

            // Lade die gespeicherte Breite für dieses Tool
            this.loadToolWidth();

            // Aktualisiere UI-Elemente (Größen-Slider und Transparenz-Slider)
            this.updateToolUI();
        },

        setColor: function(color) {
            this.currentColor = color;
        },

        setLineWidth: function(width) {
            this.lineWidth = width;

            // Speichere die Breite für das aktuelle Tool
            this.saveToolWidth();
        },

        setHighlighterOpacity: function(opacity) {
            this.highlighterOpacity = opacity;
            this.saveToolSettings();
        },

        /**
         * Textgröße des Blockinhalts setzen
         */
        setFontSize: function(size) {
            this.fontSize = size;
            var contentArea = this.overlay.querySelector('.cbd-board-content');
            if (contentArea) {
                contentArea.style.fontSize = (size / 100) + 'em';
            }
        },

        /**
         * Lädt alle Tool-Einstellungen aus localStorage
         */
        loadToolSettings: function() {
            try {
                var settings = localStorage.getItem('cbd-board-tool-settings');
                if (settings) {
                    settings = JSON.parse(settings);
                    this.penWidth = settings.penWidth || 3;
                    this.eraserStrokeWidth = settings.eraserStrokeWidth || 8;
                    this.eraserPointWidth = settings.eraserPointWidth || 15;
                    this.highlighterWidth = settings.highlighterWidth || 20;
                    this.highlighterOpacity = settings.highlighterOpacity || 0.3;
                }
            } catch (e) {
                console.error('[CBD Board] Fehler beim Laden der Tool-Einstellungen:', e);
            }

            // Setze lineWidth auf die Breite des aktuellen Tools
            this.loadToolWidth();
        },

        /**
         * Speichert alle Tool-Einstellungen in localStorage
         */
        saveToolSettings: function() {
            try {
                var settings = {
                    penWidth: this.penWidth,
                    eraserStrokeWidth: this.eraserStrokeWidth,
                    eraserPointWidth: this.eraserPointWidth,
                    highlighterWidth: this.highlighterWidth,
                    highlighterOpacity: this.highlighterOpacity
                };
                localStorage.setItem('cbd-board-tool-settings', JSON.stringify(settings));
            } catch (e) {
                console.error('[CBD Board] Fehler beim Speichern der Tool-Einstellungen:', e);
            }
        },

        /**
         * Lädt die Breite für das aktuelle Tool
         */
        loadToolWidth: function() {
            switch (this.currentTool) {
                case 'pen':
                    this.lineWidth = this.penWidth;
                    break;
                case 'eraser-stroke':
                    this.lineWidth = this.eraserStrokeWidth;
                    break;
                case 'eraser-point':
                    this.lineWidth = this.eraserPointWidth;
                    break;
                case 'highlighter':
                    this.lineWidth = this.highlighterWidth;
                    break;
                default:
                    this.lineWidth = this.penWidth;
            }
        },

        /**
         * Speichert die aktuelle lineWidth für das aktuelle Tool
         */
        saveToolWidth: function() {
            switch (this.currentTool) {
                case 'pen':
                    this.penWidth = this.lineWidth;
                    break;
                case 'eraser-stroke':
                    this.eraserStrokeWidth = this.lineWidth;
                    break;
                case 'eraser-point':
                    this.eraserPointWidth = this.lineWidth;
                    break;
                case 'highlighter':
                    this.highlighterWidth = this.lineWidth;
                    break;
            }

            // Speichere alle Einstellungen
            this.saveToolSettings();
        },

        /**
         * Aktualisiert die UI-Elemente (Slider) basierend auf dem aktuellen Tool
         */
        updateToolUI: function() {
            if (!this.overlay) return;

            // Größen-Slider aktualisieren
            var widthInput = this.overlay.querySelector('.cbd-board-width');
            var widthDisplay = this.overlay.querySelector('.cbd-board-width-display');
            if (widthInput) {
                widthInput.value = this.lineWidth;
                if (widthDisplay) {
                    widthDisplay.textContent = this.lineWidth + 'px';
                }
            }

            // Transparenz-Slider nur für Textmarker anzeigen
            var opacityControl = this.overlay.querySelector('.cbd-board-opacity-control');
            if (opacityControl) {
                if (this.currentTool === 'highlighter') {
                    opacityControl.style.display = 'flex';
                    var opacityInput = this.overlay.querySelector('.cbd-board-opacity');
                    var opacityDisplay = this.overlay.querySelector('.cbd-board-opacity-display');
                    if (opacityInput) {
                        opacityInput.value = Math.round(this.highlighterOpacity * 100);
                        if (opacityDisplay) {
                            opacityDisplay.textContent = Math.round(this.highlighterOpacity * 100) + '%';
                        }
                    }
                } else {
                    opacityControl.style.display = 'none';
                }
            }
        },

        /**
         * Drawing Canvas loeschen
         */
        clearCanvas: function() {
            if (!this.drawingCtx || !this.drawingCanvas) return;

            this.drawingCtx.clearRect(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);

            // Striche löschen (für Strich-basiertes Radieren)
            this.strokes = [];
            this.currentStroke = null;

            // Aus localStorage entfernen
            this.removeFromCache();
        },

        showClearConfirm: function() {
            var self = this;

            var confirmOverlay = document.createElement('div');
            confirmOverlay.className = 'cbd-board-confirm-overlay';
            confirmOverlay.innerHTML =
                '<div class="cbd-board-confirm-dialog">' +
                    '<h4>Zeichnung löschen?</h4>' +
                    '<p>Die gesamte Zeichnung wird unwiderruflich gelöscht.</p>' +
                    '<div class="cbd-board-confirm-actions">' +
                        '<button class="cbd-board-confirm-cancel">Abbrechen</button>' +
                        '<button class="cbd-board-confirm-delete">Löschen</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(confirmOverlay);

            confirmOverlay.querySelector('.cbd-board-confirm-cancel').addEventListener('click', function() {
                document.body.removeChild(confirmOverlay);
            });

            confirmOverlay.querySelector('.cbd-board-confirm-delete').addEventListener('click', function() {
                self.clearCanvas();
                document.body.removeChild(confirmOverlay);
            });

            confirmOverlay.addEventListener('click', function(e) {
                if (e.target === confirmOverlay) {
                    document.body.removeChild(confirmOverlay);
                }
            });
        },

        // =============================================
        // Canvas Event-Handler
        // =============================================

        onPointerDown: function(e) {
            // Handballenunterdrückung (Palm Rejection)
            // Nur Stift (pen) oder Maus (mouse) erlauben - Touch mit mehreren Kontaktpunkten ignorieren

            // Pointer zur Liste hinzufügen
            this.activePointers.add(e.pointerId);

            if (e.pointerType === 'touch') {
                // Wenn mehrere Pointer aktiv sind: Wahrscheinlich Handballen + Finger
                // Erste Touch erlauben, weitere Touches (Palm) ignorieren
                if (this.activePointers.size > 1) {
                    return; // Palm-Berührung ignorieren
                }

                // Große Kontaktfläche deutet auf Handballen hin
                // width/height sind bei Palm größer als bei Finger/Stift
                if (e.width > 25 || e.height > 25) {
                    return; // Wahrscheinlich Handballen
                }
            }

            // Stift (pen) und Maus (mouse) immer erlauben

            var rect = this.drawingCanvas.getBoundingClientRect();
            this.lastX = e.clientX - rect.left;
            this.lastY = e.clientY - rect.top;

            // Punkt-Radierer: Kontinuierlich radieren (wie normaler Radierer)
            if (this.currentTool === 'eraser-point') {
                // Ersten Punkt radieren
                this.drawingCtx.beginPath();
                this.drawingCtx.arc(this.lastX, this.lastY, this.lineWidth * 3, 0, Math.PI * 2);
                this.drawingCtx.globalCompositeOperation = 'destination-out';
                this.drawingCtx.fillStyle = 'rgba(0,0,0,1)';
                this.drawingCtx.fill();
                this.drawingCtx.globalCompositeOperation = 'source-over';
                // KEIN return - isDrawing wird aktiviert für kontinuierliches Radieren
            }

            // Strich-Radierer: Prüfe ob ein Strich getroffen wurde
            if (this.currentTool === 'eraser-stroke') {
                var deletedAny = this.eraseStrokeAtPoint(this.lastX, this.lastY);
                if (deletedAny) {
                    return; // Strich wurde gelöscht, kein isDrawing nötig
                }
            }

            this.isDrawing = true;

            // Neuen Strich beginnen (für Strich-basiertes Radieren)
            // Radierer werden nicht als Strich gespeichert
            if (this.currentTool !== 'eraser-stroke' && this.currentTool !== 'eraser-point') {
                this.currentStroke = {
                    tool: this.currentTool,
                    color: this.currentTool === 'highlighter'
                        ? this.getLighterHighlightColor(this.currentColor)
                        : this.currentColor,
                    width: this.lineWidth,
                    points: [{x: this.lastX, y: this.lastY}]
                };
            }

            // Canvas mit korrekter Ebenen-Reihenfolge neu zeichnen
            // (Textmarker unten, normale Striche oben)
            if (this.currentTool !== 'eraser-point' && this.currentTool !== 'eraser-stroke') {
                this.redrawAllStrokes();
            }

            // Pointer capture
            this.drawingCanvas.setPointerCapture(e.pointerId);
        },

        onPointerMove: function(e) {
            if (!this.isDrawing) return;

            var rect = this.drawingCanvas.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;

            // Strich-Radierer: Prüfe kontinuierlich ob ein Strich getroffen wird
            if (this.currentTool === 'eraser-stroke') {
                this.eraseStrokeAtPoint(x, y);
                this.lastX = x;
                this.lastY = y;
                return;
            }

            // Punkt-Radierer: Zeichne Kreis an jeder Position (kontinuierlich)
            if (this.currentTool === 'eraser-point') {
                this.drawingCtx.beginPath();
                this.drawingCtx.arc(x, y, this.lineWidth * 3, 0, Math.PI * 2);
                this.drawingCtx.globalCompositeOperation = 'destination-out';
                this.drawingCtx.fillStyle = 'rgba(0,0,0,1)';
                this.drawingCtx.fill();
                this.drawingCtx.globalCompositeOperation = 'source-over';
                this.lastX = x;
                this.lastY = y;
                return;
            }

            // Punkt zum aktuellen Strich hinzufügen
            if (this.currentStroke) {
                this.currentStroke.points.push({x: x, y: y});
            }

            // Canvas mit korrekter Ebenen-Reihenfolge neu zeichnen
            // (Textmarker unten, normale Striche oben)
            this.redrawAllStrokes();

            this.lastX = x;
            this.lastY = y;
        },

        onPointerUp: function(e) {
            // Pointer aus der Liste entfernen (für Palm Rejection)
            this.activePointers.delete(e.pointerId);

            if (this.isDrawing) {
                this.isDrawing = false;
                this.drawingCtx.globalCompositeOperation = 'source-over';

                // Aktuellen Strich zur Liste hinzufügen (außer bei Radierer)
                if (this.currentStroke && this.currentTool !== 'eraser-stroke') {
                    this.strokes.push(this.currentStroke);
                    this.currentStroke = null;
                }
            }
        },

        /**
         * Strich-Radierer: Prüft ob ein Strich an Position (x,y) ist und löscht ihn
         */
        eraseStrokeAtPoint: function(x, y) {
            var eraserRadius = this.lineWidth * 2; // Größerer Radius für einfacheres Treffen
            var deletedAny = false;

            // Durch alle Striche iterieren (rückwärts für sicheres Löschen)
            for (var i = this.strokes.length - 1; i >= 0; i--) {
                var stroke = this.strokes[i];

                // Prüfe ob Radierer einen Punkt des Strichs berührt
                for (var j = 0; j < stroke.points.length; j++) {
                    var point = stroke.points[j];
                    var dx = point.x - x;
                    var dy = point.y - y;
                    var distance = Math.sqrt(dx * dx + dy * dy);

                    if (distance < eraserRadius + stroke.width) {
                        // Strich getroffen! Entfernen
                        this.strokes.splice(i, 1);
                        deletedAny = true;
                        break;
                    }
                }

                if (deletedAny) break; // Nur einen Strich pro Frame löschen
            }

            if (deletedAny) {
                this.redrawAllStrokes();
            }

            return deletedAny;
        },

        /**
         * Canvas neu zeichnen mit allen gespeicherten Strichen
         */
        redrawAllStrokes: function() {
            // Canvas löschen
            this.drawingCtx.clearRect(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);

            // Sammle alle Striche (abgeschlossen + aktuell in Bearbeitung)
            var allStrokes = this.strokes.slice(); // Kopie der abgeschlossenen Striche
            if (this.currentStroke && this.currentStroke.points.length > 0) {
                allStrokes.push(this.currentStroke); // Füge aktuellen Strich hinzu
            }

            // Zwei-Pass-Rendering: Erst Textmarkierer (unten), dann normale Striche (oben)

            // Pass 1: Textmarkierer zeichnen (untere Ebene)
            for (var i = 0; i < allStrokes.length; i++) {
                var stroke = allStrokes[i];
                if (stroke.tool !== 'highlighter') continue; // Nur Textmarkierer
                if (stroke.points.length < 1) continue;

                this.drawingCtx.lineWidth = stroke.width;
                this.drawingCtx.lineCap = 'round';
                this.drawingCtx.lineJoin = 'round';
                this.drawingCtx.strokeStyle = stroke.color;
                this.drawingCtx.globalCompositeOperation = 'lighten';

                // Strich zeichnen
                this.drawingCtx.beginPath();
                this.drawingCtx.moveTo(stroke.points[0].x, stroke.points[0].y);

                for (var j = 1; j < stroke.points.length; j++) {
                    this.drawingCtx.lineTo(stroke.points[j].x, stroke.points[j].y);
                }

                this.drawingCtx.stroke();
            }

            // Pass 2: Normale Striche zeichnen (obere Ebene)
            this.drawingCtx.globalCompositeOperation = 'source-over';

            for (var i = 0; i < allStrokes.length; i++) {
                var stroke = allStrokes[i];
                if (stroke.tool === 'highlighter') continue; // Überspringen, schon gezeichnet
                if (stroke.points.length < 1) continue;

                this.drawingCtx.lineWidth = stroke.width;
                this.drawingCtx.lineCap = 'round';
                this.drawingCtx.lineJoin = 'round';
                this.drawingCtx.strokeStyle = stroke.color;

                // Strich zeichnen
                this.drawingCtx.beginPath();
                this.drawingCtx.moveTo(stroke.points[0].x, stroke.points[0].y);

                for (var j = 1; j < stroke.points.length; j++) {
                    this.drawingCtx.lineTo(stroke.points[j].x, stroke.points[j].y);
                }

                this.drawingCtx.stroke();
            }

            // Composite Operation zurücksetzen
            this.drawingCtx.globalCompositeOperation = 'source-over';
        },

        onKeyDown: function(e) {
            if (e.key === 'Escape') {
                this.close();
            }
        },

        // =============================================
        // Zeichnungs-Persistenz (Dispatcher)
        // =============================================

        loadDrawing: function() {
            if (this.classId && this.ajaxUrl) {
                this.loadFromServer();
            } else {
                this.loadFromCache();
            }
        },

        saveDrawing: function() {
            if (this.classId && this.ajaxUrl) {
                this.saveToServer();
            } else {
                this.saveToCache();
            }
        },

        // =============================================
        // Server-Persistenz
        // =============================================

        loadFromServer: function() {
            if (!this.classId || !this.ajaxUrl || !this.stableContainerId) {
                this.loadFromCache();
                return;
            }

            var self = this;
            this._setSaveStatus('Lade...');

            var formData = new FormData();
            formData.append('action', 'cbd_load_drawing');
            formData.append('nonce', this.nonce);
            formData.append('class_id', this.classId);
            formData.append('page_id', this.pageId);
            formData.append('container_id', this.stableContainerId);

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.data.drawing_data) {
                    var img = new Image();
                    img.onload = function() {
                        // Zeichnung auf Drawing Canvas laden
                        self.drawingCtx.clearRect(0, 0, self.drawingCanvas.width, self.drawingCanvas.height);
                        self.drawingCtx.drawImage(img, 0, 0);
                        self._setSaveStatus('Geladen');
                        setTimeout(function() { self._setSaveStatus(''); }, 2000);
                    };
                    img.src = data.data.drawing_data;
                } else {
                    // Keine Zeichnung auf Server
                    self._setSaveStatus('');
                }
            })
            .catch(function(err) {
                console.warn('[CBD Board Mode] Server-Laden fehlgeschlagen:', err);
                self._setSaveStatus('Fehler');
                self.loadFromCache();
            });
        },

        saveToServer: function() {
            if (this.isSaving || !this.classId || !this.ajaxUrl || !this.stableContainerId) {
                this.saveToCache();
                return;
            }

            this.isSaving = true;
            this._setSaveStatus('Speichert...');

            var self = this;
            // Nur Drawing Canvas speichern
            var dataUrl = this.drawingCanvas.toDataURL('image/png');

            var formData = new FormData();
            formData.append('action', 'cbd_save_drawing');
            formData.append('nonce', this.nonce);
            formData.append('class_id', this.classId);
            formData.append('page_id', this.pageId);
            formData.append('container_id', this.stableContainerId);
            formData.append('drawing_data', dataUrl);

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                self.isSaving = false;
                if (data.success) {
                    self._setSaveStatus('Gespeichert');
                    // Einmalig als "behandelt" markieren wenn noch nicht geschehen
                    if (!self.isBehandeltSet) {
                        self.isBehandeltSet = true;
                        self.setBehandeltOnServer();
                    }
                } else {
                    console.warn('[CBD Board Mode] Server-Speichern fehlgeschlagen:', data);
                    self._setSaveStatus('Fehler');
                    self.saveToCache();
                }
            })
            .catch(function(err) {
                self.isSaving = false;
                console.warn('[CBD Board Mode] Server-Speichern fehlgeschlagen:', err);
                self._setSaveStatus('Fehler');
                self.saveToCache();
            });
        },

        _setSaveStatus: function(text) {
            var el = document.getElementById('cbd-board-save-status');
            if (el) {
                el.textContent = text;
            }
        },

        /**
         * Block als "behandelt" markieren (einmalig pro Session, fire-and-forget)
         */
        setBehandeltOnServer: function() {
            if (!this.classId || !this.ajaxUrl || !this.stableContainerId) return;

            var formData = new FormData();
            formData.append('action', 'cbd_set_behandelt');
            formData.append('nonce', this.nonce);
            formData.append('class_id', this.classId);
            formData.append('page_id', this.pageId);
            formData.append('container_id', this.stableContainerId);

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.success) {
                    console.warn('[CBD Board Mode] Behandelt-Status konnte nicht gesetzt werden:', data);
                }
            })
            .catch(function(err) {
                console.warn('[CBD Board Mode] Behandelt-Status konnte nicht gesetzt werden:', err);
            });
        },

        // =============================================
        // localStorage Persistenz
        // =============================================

        saveToCache: function() {
            if (!this.drawingCanvas || !this.containerId) return;

            try {
                var key = 'cbd-board-' + this.containerId;
                var dataUrl = this.drawingCanvas.toDataURL('image/png');
                localStorage.setItem(key, dataUrl);
            } catch (e) {
                console.warn('[CBD Board Mode] Zeichnung konnte nicht gespeichert werden:', e.message);
            }
        },

        loadFromCache: function() {
            if (!this.drawingCanvas || !this.drawingCtx || !this.containerId) return;

            try {
                var key = 'cbd-board-' + this.containerId;
                var dataUrl = localStorage.getItem(key);

                if (!dataUrl) {
                    // Kein gespeicherter Stand
                    return;
                }

                var self = this;
                var img = new Image();
                img.onload = function() {
                    self.drawingCtx.clearRect(0, 0, self.drawingCanvas.width, self.drawingCanvas.height);
                    self.drawingCtx.drawImage(img, 0, 0);
                };
                img.src = dataUrl;
            } catch (e) {
                console.warn('[CBD Board Mode] Zeichnung konnte nicht geladen werden:', e.message);
            }
        },

        removeFromCache: function() {
            if (!this.containerId) return;

            try {
                var key = 'cbd-board-' + this.containerId;
                localStorage.removeItem(key);
            } catch (e) {
                // Ignorieren
            }
        },

        // =============================================
        // Hilfsfunktionen
        // =============================================

        _escHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        /**
         * Hex zu RGBA konvertieren
         */
        hexToRgba: function(hex, alpha) {
            var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (result) {
                var r = parseInt(result[1], 16);
                var g = parseInt(result[2], 16);
                var b = parseInt(result[3], 16);
                return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
            }
            return hex;
        },

        // Hellt eine Farbe für Textmarkierer auf (mischt mit Weiß)
        // WICHTIG: Volle Deckkraft (1.0) + 'lighten' = Konstante Farbe beim Übermalen
        getLighterHighlightColor: function(hex) {
            var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (result) {
                var r = parseInt(result[1], 16);
                var g = parseInt(result[2], 16);
                var b = parseInt(result[3], 16);

                // Berechne Weiß-Mischung basierend auf highlighterOpacity
                // opacity 0.1 (10%) = sehr hell (viel Weiß gemischt)
                // opacity 1.0 (100%) = dunkel (wenig Weiß gemischt)
                var whiteMix = Math.max(0.3, Math.min(0.95, 1.0 - (this.highlighterOpacity * 0.7)));

                // Mische mit Weiß für hellen Textmarkierer-Effekt
                r = Math.round(r + (255 - r) * whiteMix);
                g = Math.round(g + (255 - g) * whiteMix);
                b = Math.round(b + (255 - b) * whiteMix);

                // Volle Deckkraft (1.0) damit 'lighten' operation korrekt funktioniert
                // Bei 1.0: Einmal markiert = Farbe bleibt konstant (lighten ersetzt nur dunklere Pixel)
                return 'rgba(' + r + ', ' + g + ', ' + b + ', 1.0)';
            }
            return 'rgba(255, 255, 200, 1.0)'; // Fallback: Helles Gelb
        },

        // =============================================
        // Import/Export persönliche Notizen
        // =============================================

        /**
         * Persönliche Notizen als JSON-Datei herunterladen
         */
        downloadPersonalNotes: function() {
            try {
                // Alle CBD-Board Daten aus localStorage sammeln
                var exportData = {};
                var count = 0;

                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (key && key.startsWith('cbd-board-')) {
                        exportData[key] = localStorage.getItem(key);
                        count++;
                    }
                }

                if (count === 0) {
                    alert('Keine persönlichen Notizen zum Herunterladen vorhanden.');
                    return;
                }

                // JSON erstellen
                var jsonData = JSON.stringify(exportData, null, 2);
                var blob = new Blob([jsonData], { type: 'application/json' });
                var url = URL.createObjectURL(blob);

                // Download-Link erstellen und klicken
                var link = document.createElement('a');
                link.href = url;
                link.download = 'cbd-notizen-' + new Date().toISOString().split('T')[0] + '.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                // Bestätigung
                var msg = count === 1
                    ? '1 Notiz wurde heruntergeladen.'
                    : count + ' Notizen wurden heruntergeladen.';
                alert(msg + '\n\nSie können diese Datei auf einem neuen Gerät importieren.');

            } catch (e) {
                console.error('[CBD Board Mode] Fehler beim Download:', e);
                alert('Fehler beim Herunterladen der Notizen: ' + e.message);
            }
        },

        /**
         * Persönliche Notizen aus JSON-Datei importieren
         */
        uploadPersonalNotes: function() {
            var self = this;

            // File Input erstellen
            var input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';

            input.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;

                var reader = new FileReader();
                reader.onload = function(event) {
                    try {
                        var importData = JSON.parse(event.target.result);

                        // Validierung: Ist es ein Objekt mit cbd-board Keys?
                        var keys = Object.keys(importData);
                        var validKeys = keys.filter(function(k) {
                            return k.startsWith('cbd-board-');
                        });

                        if (validKeys.length === 0) {
                            alert('Die Datei enthält keine gültigen CBD-Notizen.');
                            return;
                        }

                        // Prüfen, ob bereits Daten vorhanden sind
                        var existingCount = 0;
                        for (var i = 0; i < localStorage.length; i++) {
                            var key = localStorage.key(i);
                            if (key && key.startsWith('cbd-board-')) {
                                existingCount++;
                            }
                        }

                        // Bestätigung wenn Daten vorhanden
                        if (existingCount > 0) {
                            var confirmMsg = 'Es sind bereits ' + existingCount + ' persönliche Notiz(en) vorhanden.\n\n' +
                                'Beim Import werden ' + validKeys.length + ' Notiz(en) hochgeladen.\n' +
                                'Bestehende Notizen mit denselben IDs werden überschrieben.\n\n' +
                                'Möchten Sie fortfahren?';

                            if (!confirm(confirmMsg)) {
                                return;
                            }
                        }

                        // Import durchführen
                        var imported = 0;
                        validKeys.forEach(function(key) {
                            localStorage.setItem(key, importData[key]);
                            imported++;
                        });

                        // Bestätigung
                        var successMsg = imported === 1
                            ? '1 Notiz wurde erfolgreich importiert.'
                            : imported + ' Notizen wurden erfolgreich importiert.';

                        alert(successMsg + '\n\nDie Notizen sind jetzt verfügbar.');

                        // Wenn wir gerade die aktuelle Zeichnung importiert haben, neu laden
                        if (importData['cbd-board-' + self.containerId]) {
                            self.loadDrawing();
                        }

                    } catch (e) {
                        console.error('[CBD Board Mode] Fehler beim Import:', e);
                        alert('Fehler beim Importieren der Notizen: ' + e.message + '\n\nStellen Sie sicher, dass die Datei eine gültige CBD-Notizen-Datei ist.');
                    }
                };
                reader.readAsText(file);
            });

            // File-Dialog öffnen
            input.click();
        },

        /**
         * Alle persönlichen Notizen löschen (mit Bestätigung)
         */
        deleteAllPersonalNotes: function() {
            try {
                // Zähle vorhandene Notizen
                var count = 0;
                var keysToDelete = [];
                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (key && key.startsWith('cbd-board-')) {
                        count++;
                        keysToDelete.push(key);
                    }
                }

                if (count === 0) {
                    alert('Keine persönlichen Notizen zum Löschen vorhanden.');
                    return;
                }

                // Bestätigungsabfrage
                var confirmMsg = '⚠️ WARNUNG: Alle lokalen Tafel-Notizen löschen\n\n' +
                    'Es werden ' + count + ' persönliche Notiz(en) UNWIDERRUFLICH gelöscht.\n' +
                    'Dies kann NICHT rückgängig gemacht werden!\n\n' +
                    'Tipp: Exportieren Sie Ihre Notizen zuerst mit dem Download-Button,\n' +
                    'wenn Sie sie später wiederherstellen möchten.\n\n' +
                    'Möchten Sie wirklich ALLE lokalen Notizen löschen?';

                if (!confirm(confirmMsg)) {
                    return;
                }

                // Zweite Bestätigung für zusätzliche Sicherheit
                var confirmMsg2 = 'Letzte Bestätigung:\n\n' +
                    'Sind Sie ABSOLUT SICHER, dass Sie alle ' + count + ' Notizen löschen möchten?\n\n' +
                    'Dies kann NICHT rückgängig gemacht werden!';

                if (!confirm(confirmMsg2)) {
                    return;
                }

                // Alle cbd-board-* Keys löschen
                keysToDelete.forEach(function(key) {
                    localStorage.removeItem(key);
                });

                // Canvas leeren (falls gerade eine Zeichnung angezeigt wird)
                if (this.drawingCtx) {
                    this.drawingCtx.clearRect(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);
                    this.strokes = [];
                }

                // Erfolgsbestätigung
                alert('✓ Erfolgreich gelöscht!\n\n' +
                    'Alle ' + count + ' persönlichen Tafel-Notizen wurden gelöscht.\n\n' +
                    'Ihre Zeichnungen sind jetzt wieder leer.');

            } catch (e) {
                console.error('[CBD Board Mode] Fehler beim Löschen:', e);
                alert('Fehler beim Löschen der Notizen: ' + e.message);
            }
        },

        /**
         * Interaktive Blöcke initialisieren
         * Führt Inline-Scripts aus und triggert Block-Initialisierung
         */
        initializeInteractiveBlocks: function(container) {
            if (!container) return;

            var self = this;

            try {
                // 1. Externe Libraries für spezielle Blöcke laden
                this.loadBlockDependencies(container, function() {
                    // 2. Alle <script>-Tags finden und ausführen
                    var scripts = container.querySelectorAll('script');
                scripts.forEach(function(oldScript) {
                    // Neues Script-Element erstellen (damit es ausgeführt wird)
                    var newScript = document.createElement('script');

                    // Kopiere alle Attribute
                    Array.from(oldScript.attributes).forEach(function(attr) {
                        newScript.setAttribute(attr.name, attr.value);
                    });

                    // Kopiere Script-Inhalt
                    if (oldScript.src) {
                        // Externes Script - src kopieren
                        newScript.src = oldScript.src;
                    } else {
                        // Inline-Script - Inhalt kopieren
                        newScript.textContent = oldScript.textContent;
                    }

                    // Ersetze altes Script durch neues (triggert Ausführung)
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                // 3. Custom Event für Block-Initialisierung auslösen
                var event = new CustomEvent('cbd-board-content-loaded', {
                    detail: { container: container },
                    bubbles: true,
                    cancelable: false
                });
                container.dispatchEvent(event);

                // 4. jQuery-basierte Initialisierung (falls jQuery vorhanden)
                if (typeof jQuery !== 'undefined') {
                    jQuery(container).trigger('cbd-board-content-loaded');
                }

                // 5. WordPress Interactivity API neu initialisieren (falls vorhanden)
                if (window.wp && window.wp.interactivity) {
                    // Timeout um sicherzustellen, dass DOM-Updates abgeschlossen sind
                    setTimeout(function() {
                        // Triggere Interactivity API für alle interaktiven Elemente im Container
                        var interactiveElements = container.querySelectorAll('[data-wp-interactive]');
                        interactiveElements.forEach(function(element) {
                            // Force re-initialization by triggering a DOM mutation
                            var parent = element.parentNode;
                            var next = element.nextSibling;
                            parent.removeChild(element);
                            parent.insertBefore(element, next);
                        });
                    }, 100);
                }

                // 6. Spezielle Block-Initialisierung (Molekülviewer, etc.)
                self.initializeSpecialBlocks(container);

                console.log('[CBD Board Mode] Interaktive Blöcke initialisiert:', scripts.length, 'Scripts ausgeführt');
            });

            } catch (e) {
                console.error('[CBD Board Mode] Fehler bei Block-Initialisierung:', e);
            }
        },

        /**
         * Lädt externe Abhängigkeiten für spezielle Blöcke (z.B. 3Dmol.js für Molekülviewer)
         */
        loadBlockDependencies: function(container, callback) {
            var dependencies = [];

            // Prüfe auf Molekülviewer-Block
            if (container.querySelector('.wp-block-modular-blocks-molecule-viewer')) {
                // Prüfe ob 3Dmol.js bereits geladen ist
                if (typeof window.$3Dmol === 'undefined') {
                    dependencies.push({
                        name: '3Dmol',
                        url: 'https://3dmol.csb.pitt.edu/build/3Dmol-min.js',
                        check: function() { return typeof window.$3Dmol !== 'undefined'; }
                    });
                }
            }

            // Prüfe auf Chart-Block (Plotly)
            if (container.querySelector('.wp-block-modular-blocks-chart-block')) {
                if (typeof window.Plotly === 'undefined') {
                    dependencies.push({
                        name: 'Plotly',
                        url: 'https://cdn.plot.ly/plotly-latest.min.js',
                        check: function() { return typeof window.Plotly !== 'undefined'; }
                    });
                }
            }

            // Wenn keine Dependencies fehlen, direkt callback ausführen
            if (dependencies.length === 0) {
                callback();
                return;
            }

            // Dependencies laden
            console.log('[CBD Board Mode] Lade externe Libraries:', dependencies.map(function(d) { return d.name; }).join(', '));

            var loaded = 0;
            var total = dependencies.length;

            dependencies.forEach(function(dep) {
                var script = document.createElement('script');
                script.src = dep.url;
                script.onload = function() {
                    console.log('[CBD Board Mode]', dep.name, 'erfolgreich geladen');
                    loaded++;
                    if (loaded === total) {
                        // Alle Dependencies geladen, callback ausführen
                        setTimeout(callback, 100); // Kurze Verzögerung für Initialisierung
                    }
                };
                script.onerror = function() {
                    console.error('[CBD Board Mode] Fehler beim Laden von', dep.name);
                    loaded++;
                    if (loaded === total) {
                        callback(); // Auch bei Fehler weitermachen
                    }
                };
                document.head.appendChild(script);
            });
        },

        /**
         * Spezielle Initialisierung für bekannte Blöcke
         */
        initializeSpecialBlocks: function(container) {
            // Molekülviewer neu initialisieren
            var moleculeViewers = container.querySelectorAll('.wp-block-modular-blocks-molecule-viewer');
            if (moleculeViewers.length > 0 && typeof window.$3Dmol !== 'undefined') {
                moleculeViewers.forEach(function(viewer) {
                    // Triggere Re-Rendering durch DOM-Manipulation
                    var parent = viewer.parentNode;
                    var next = viewer.nextSibling;
                    parent.removeChild(viewer);

                    // Nach kurzer Verzögerung wieder einfügen (triggert Neuinitialisierung)
                    setTimeout(function() {
                        parent.insertBefore(viewer, next);

                        // Custom Event für Molekülviewer
                        var event = new CustomEvent('molecule-viewer-reinit', {
                            bubbles: true,
                            detail: { viewer: viewer }
                        });
                        viewer.dispatchEvent(event);
                    }, 50);
                });
            }

            // Chart-Blöcke neu initialisieren
            var chartBlocks = container.querySelectorAll('.wp-block-modular-blocks-chart-block');
            if (chartBlocks.length > 0 && typeof window.Plotly !== 'undefined') {
                chartBlocks.forEach(function(chart) {
                    var event = new CustomEvent('chart-reinit', {
                        bubbles: true,
                        detail: { chart: chart }
                    });
                    chart.dispatchEvent(event);
                });
            }
        }
    };

})();
