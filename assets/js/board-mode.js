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
        currentColor: '#ffffff',
        lineWidth: 3,
        containerId: null,
        boardColor: '#1a472a',
        lastX: 0,
        lastY: 0,
        resizeObserver: null,
        boundHandlers: {},

        // Grid state
        showGrid: false,

        // Classroom System state
        classId: null,
        pageId: null,
        stableContainerId: null,
        ajaxUrl: null,
        nonce: null,
        isSaving: false,
        classes: [],

        // Preset colors
        presetColors: [
            '#1a472a', // Dunkelgrün (default)
            '#2c3e50', // Dunkelblau
            '#1c1c1c', // Fast Schwarz
            '#4a1e1e', // Dunkelbraun
            '#2c4a3a', // Waldgrün
            '#3d3d5c'  // Dunkel-Lila
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

            // Preset Color Buttons HTML
            var presetColorsHtml = '';
            this.presetColors.forEach(function(color) {
                presetColorsHtml += '<button class="cbd-board-preset-color" data-color="' + color + '" style="background-color: ' + color + ';" title="' + color + '"></button>';
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
                        '<div class="cbd-board-toolbar">' +
                            // Tools
                            '<button class="cbd-board-tool active" data-tool="pen" title="Stift">' +
                                '<span class="dashicons dashicons-edit"></span>' +
                            '</button>' +
                            '<button class="cbd-board-tool" data-tool="highlighter" title="Textmarkierer">' +
                                '<span class="dashicons dashicons-marker"></span>' +
                            '</button>' +
                            '<button class="cbd-board-tool" data-tool="eraser" title="Radierer">' +
                                '<span class="dashicons dashicons-editor-removeformatting"></span>' +
                            '</button>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Colors
                            '<input type="color" class="cbd-board-color" value="#ffffff" title="Stiftfarbe">' +
                            '<div class="cbd-board-preset-colors">' + presetColorsHtml + '</div>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Line width
                            '<input type="range" class="cbd-board-width" min="1" max="20" value="3" title="Stiftdicke">' +
                            '<span class="cbd-board-width-display">3px</span>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Background color picker
                            '<label class="cbd-board-bg-label">Hintergrund:</label>' +
                            '<input type="color" class="cbd-board-bg-color" value="' + this.boardColor + '" title="Hintergrundfarbe">' +
                            '<div class="cbd-board-bg-preset-colors">' + presetColorsHtml + '</div>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Grid toggle
                            '<button class="cbd-board-grid-toggle" title="Hexagon-Gitter ein/aus">' +
                                '<span class="dashicons dashicons-grid-view"></span>' +
                            '</button>' +
                            '<span class="cbd-board-separator"></span>' +
                            // Clear button
                            '<button class="cbd-board-clear" title="Alles löschen">' +
                                '<span class="dashicons dashicons-trash"></span>' +
                            '</button>' +
                        '</div>' +
                        '<div class="cbd-board-canvas-container">' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-background"></canvas>' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-grid"></canvas>' +
                            '<canvas class="cbd-board-canvas cbd-board-canvas-drawing"></canvas>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            // Block-Inhalt einfuegen
            var contentArea = overlay.querySelector('.cbd-board-content');
            contentArea.innerHTML = contentHtml;

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
         * Hexagon-Gitter zeichnen
         */
        redrawGrid: function() {
            if (!this.gridCtx || !this.gridCanvas) return;

            // Grid canvas leeren
            this.gridCtx.clearRect(0, 0, this.gridCanvas.width, this.gridCanvas.height);

            if (!this.showGrid) return;

            var width = this.gridCanvas.width;
            var height = this.gridCanvas.height;

            // Hexagon-Parameter
            var hexSize = 30; // Radius des Hexagons
            var hexHeight = hexSize * 2;
            var hexWidth = Math.sqrt(3) * hexSize;
            var hexVertDist = hexHeight * 3 / 4;
            var hexHorizDist = hexWidth;

            // Grid-Style
            this.gridCtx.strokeStyle = 'rgba(255, 255, 255, 0.15)'; // Sehr leicht
            this.gridCtx.lineWidth = 1;

            // Hexagons zeichnen
            for (var row = -1; row < Math.ceil(height / hexVertDist) + 1; row++) {
                for (var col = -1; col < Math.ceil(width / hexHorizDist) + 1; col++) {
                    var x = col * hexHorizDist;
                    var y = row * hexVertDist;

                    // Jede zweite Reihe versetzt
                    if (row % 2 === 1) {
                        x += hexHorizDist / 2;
                    }

                    this.drawHexagon(x, y, hexSize);
                }
            }
        },

        /**
         * Ein einzelnes Hexagon zeichnen
         */
        drawHexagon: function(centerX, centerY, radius) {
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
         * Grid ein/ausschalten
         */
        toggleGrid: function() {
            this.showGrid = !this.showGrid;
            this.redrawGrid();

            // Button-Status aktualisieren
            var btn = this.overlay.querySelector('.cbd-board-grid-toggle');
            if (btn) {
                if (this.showGrid) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            }
        },

        /**
         * Hintergrundfarbe aendern
         */
        setBoardColor: function(color) {
            this.boardColor = color;
            this.redrawBackground();

            // Color input aktualisieren
            var input = this.overlay.querySelector('.cbd-board-bg-color');
            if (input) {
                input.value = color;
            }
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

            // Background Color
            var bgColorInput = this.overlay.querySelector('.cbd-board-bg-color');
            if (bgColorInput) {
                bgColorInput.addEventListener('input', function() {
                    self.setBoardColor(this.value);
                });
            }

            // Background Color Presets
            var bgPresetButtons = this.overlay.querySelectorAll('.cbd-board-bg-preset-colors .cbd-board-preset-color');
            bgPresetButtons.forEach(function(btn) {
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

            // Grid Toggle
            var gridToggle = this.overlay.querySelector('.cbd-board-grid-toggle');
            if (gridToggle) {
                gridToggle.addEventListener('click', function() {
                    self.toggleGrid();
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

            if (this.drawingCanvas) {
                if (tool === 'eraser') {
                    this.drawingCanvas.classList.add('eraser-active');
                } else {
                    this.drawingCanvas.classList.remove('eraser-active');
                }
            }
        },

        setColor: function(color) {
            this.currentColor = color;
        },

        setLineWidth: function(width) {
            this.lineWidth = width;
        },

        /**
         * Drawing Canvas loeschen
         */
        clearCanvas: function() {
            if (!this.drawingCtx || !this.drawingCanvas) return;

            this.drawingCtx.clearRect(0, 0, this.drawingCanvas.width, this.drawingCanvas.height);

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
            this.isDrawing = true;

            var rect = this.drawingCanvas.getBoundingClientRect();
            this.lastX = e.clientX - rect.left;
            this.lastY = e.clientY - rect.top;

            // Einzelnen Punkt zeichnen
            this.drawingCtx.beginPath();
            this.drawingCtx.arc(this.lastX, this.lastY, this.lineWidth / 2, 0, Math.PI * 2);

            if (this.currentTool === 'eraser') {
                // Radierer: Loescht nur auf Drawing Layer
                this.drawingCtx.globalCompositeOperation = 'destination-out';
                this.drawingCtx.fillStyle = 'rgba(0,0,0,1)';
            } else if (this.currentTool === 'highlighter') {
                // Textmarkierer: Semi-transparent
                this.drawingCtx.globalCompositeOperation = 'source-over';
                this.drawingCtx.fillStyle = this.hexToRgba(this.currentColor, 0.3);
            } else {
                // Normal pen
                this.drawingCtx.globalCompositeOperation = 'source-over';
                this.drawingCtx.fillStyle = this.currentColor;
            }

            this.drawingCtx.fill();

            // Pointer capture
            this.drawingCanvas.setPointerCapture(e.pointerId);
        },

        onPointerMove: function(e) {
            if (!this.isDrawing) return;

            var rect = this.drawingCanvas.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;

            this.drawingCtx.beginPath();
            this.drawingCtx.moveTo(this.lastX, this.lastY);
            this.drawingCtx.lineTo(x, y);

            this.drawingCtx.lineWidth = this.lineWidth;
            this.drawingCtx.lineCap = 'round';
            this.drawingCtx.lineJoin = 'round';

            if (this.currentTool === 'eraser') {
                this.drawingCtx.globalCompositeOperation = 'destination-out';
                this.drawingCtx.strokeStyle = 'rgba(0,0,0,1)';
            } else if (this.currentTool === 'highlighter') {
                this.drawingCtx.globalCompositeOperation = 'source-over';
                this.drawingCtx.strokeStyle = this.hexToRgba(this.currentColor, 0.3);
            } else {
                this.drawingCtx.globalCompositeOperation = 'source-over';
                this.drawingCtx.strokeStyle = this.currentColor;
            }

            this.drawingCtx.stroke();

            this.lastX = x;
            this.lastY = y;
        },

        onPointerUp: function(e) {
            if (this.isDrawing) {
                this.isDrawing = false;
                this.drawingCtx.globalCompositeOperation = 'source-over';
            }
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
        }
    };

})();
