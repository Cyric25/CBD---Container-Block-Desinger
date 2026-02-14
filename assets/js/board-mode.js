/**
 * Container Block Designer - Board Mode (Tafel-Modus)
 * Fullscreen-Overlay mit Canvas-Zeichenflaeche und localStorage-Persistenz
 *
 * @package ContainerBlockDesigner
 * @since 2.9.1
 */

(function() {
    'use strict';

    window.CBDBoardMode = {

        // State
        overlay: null,
        canvas: null,
        ctx: null,
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

        /**
         * Tafel-Modus oeffnen
         * @param {string} containerId - Eindeutige Block-ID
         * @param {string} contentHtml - HTML-Inhalt des Blocks
         * @param {string} boardColor - Hintergrundfarbe der Zeichenflaeche
         */
        open: function(containerId, contentHtml, boardColor) {
            // Verhindere doppeltes Oeffnen
            if (this.overlay) {
                return;
            }

            this.containerId = containerId;
            this.boardColor = boardColor || '#1a472a';

            // Overlay-DOM erstellen
            this.createOverlayDOM(contentHtml);

            // An Body anhaengen
            document.body.appendChild(this.overlay);

            // Scroll sperren
            document.body.style.overflow = 'hidden';

            // Canvas initialisieren
            this.canvas = this.overlay.querySelector('.cbd-board-canvas');
            this.ctx = this.canvas.getContext('2d');
            this.resizeCanvas();

            // Gespeicherte Zeichnung laden
            this.loadFromCache();

            // Events binden
            this.bindEvents();
        },

        /**
         * Tafel-Modus schliessen
         */
        close: function() {
            if (!this.overlay) return;

            // Zeichnung speichern
            this.saveToCache();

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
            this.canvas = null;
            this.ctx = null;
            this.isDrawing = false;
            this.containerId = null;
        },

        /**
         * Overlay-DOM erstellen
         * @param {string} contentHtml - HTML-Inhalt fuer die linke Seite
         */
        createOverlayDOM: function(contentHtml) {
            var overlay = document.createElement('div');
            overlay.className = 'cbd-board-overlay';
            overlay.id = 'cbd-board-overlay';

            overlay.innerHTML =
                '<div class="cbd-board-header">' +
                    '<span class="cbd-board-title">' +
                        '<span class="dashicons dashicons-welcome-write-blog"></span>' +
                        'Tafel-Modus' +
                    '</span>' +
                    '<button class="cbd-board-close" title="Tafel-Modus beenden">&times;</button>' +
                '</div>' +
                '<div class="cbd-board-split">' +
                    '<div class="cbd-board-content"></div>' +
                    '<div class="cbd-board-canvas-area">' +
                        '<div class="cbd-board-toolbar">' +
                            '<button class="cbd-board-tool active" data-tool="pen" title="Stift">' +
                                '<span class="dashicons dashicons-edit"></span>' +
                            '</button>' +
                            '<button class="cbd-board-tool" data-tool="eraser" title="Radierer">' +
                                '<span class="dashicons dashicons-editor-removeformatting"></span>' +
                            '</button>' +
                            '<span class="cbd-board-separator"></span>' +
                            '<input type="color" class="cbd-board-color" value="#ffffff" title="Stiftfarbe">' +
                            '<input type="range" class="cbd-board-width" min="1" max="20" value="3" title="Stiftdicke">' +
                            '<span class="cbd-board-width-display">3px</span>' +
                            '<span class="cbd-board-separator"></span>' +
                            '<button class="cbd-board-clear" title="Alles l\u00F6schen">' +
                                '<span class="dashicons dashicons-trash"></span>' +
                            '</button>' +
                        '</div>' +
                        '<canvas class="cbd-board-canvas"></canvas>' +
                    '</div>' +
                '</div>';

            // Block-Inhalt einfuegen (sicher, da es kopierter HTML-Inhalt aus dem eigenen Block ist)
            var contentArea = overlay.querySelector('.cbd-board-content');
            contentArea.innerHTML = contentHtml;

            this.overlay = overlay;
        },

        /**
         * Canvas-Groesse an Container anpassen
         */
        resizeCanvas: function() {
            if (!this.canvas) return;

            var parent = this.canvas.parentElement;
            var toolbar = parent.querySelector('.cbd-board-toolbar');
            var toolbarHeight = toolbar ? toolbar.offsetHeight : 0;

            // Canvas-Groesse = Parent minus Toolbar
            var width = parent.clientWidth;
            var height = parent.clientHeight - toolbarHeight;

            // Bestehende Zeichnung sichern
            var imageData = null;
            if (this.canvas.width > 0 && this.canvas.height > 0) {
                try {
                    imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
                } catch (e) {
                    // Canvas war leer
                }
            }

            // Groesse setzen
            this.canvas.width = width;
            this.canvas.height = height;

            // Hintergrundfarbe
            this.ctx.fillStyle = this.boardColor;
            this.ctx.fillRect(0, 0, width, height);

            // Zeichnung wiederherstellen
            if (imageData) {
                this.ctx.putImageData(imageData, 0, 0);
            }
        },

        /**
         * Alle Event-Handler binden
         */
        bindEvents: function() {
            var self = this;

            // Gebundene Handler speichern fuer spaeteres Entfernen
            this.boundHandlers = {
                pointerDown: function(e) { self.onPointerDown(e); },
                pointerMove: function(e) { self.onPointerMove(e); },
                pointerUp: function(e) { self.onPointerUp(e); },
                keyDown: function(e) { self.onKeyDown(e); }
            };

            // Canvas Events
            this.canvas.addEventListener('pointerdown', this.boundHandlers.pointerDown);
            this.canvas.addEventListener('pointermove', this.boundHandlers.pointerMove);
            this.canvas.addEventListener('pointerup', this.boundHandlers.pointerUp);
            this.canvas.addEventListener('pointerleave', this.boundHandlers.pointerUp);

            // ESC-Taste zum Schliessen
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

                    // Active-Klasse umschalten
                    toolButtons.forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                });
            });

            // Farbwaehler
            var colorInput = this.overlay.querySelector('.cbd-board-color');
            if (colorInput) {
                colorInput.addEventListener('input', function() {
                    self.setColor(this.value);
                });
            }

            // Stiftdicke
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

            // Alles loeschen
            var clearBtn = this.overlay.querySelector('.cbd-board-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    self.showClearConfirm();
                });
            }

            // ResizeObserver fuer Canvas-Groesse
            if (typeof ResizeObserver !== 'undefined') {
                this.resizeObserver = new ResizeObserver(function() {
                    self.resizeCanvas();
                });
                this.resizeObserver.observe(this.canvas.parentElement);
            }
        },

        /**
         * Event-Handler entfernen
         */
        unbindEvents: function() {
            if (this.canvas && this.boundHandlers.pointerDown) {
                this.canvas.removeEventListener('pointerdown', this.boundHandlers.pointerDown);
                this.canvas.removeEventListener('pointermove', this.boundHandlers.pointerMove);
                this.canvas.removeEventListener('pointerup', this.boundHandlers.pointerUp);
                this.canvas.removeEventListener('pointerleave', this.boundHandlers.pointerUp);
            }

            if (this.boundHandlers.keyDown) {
                document.removeEventListener('keydown', this.boundHandlers.keyDown);
            }

            this.boundHandlers = {};
        },

        // =============================================
        // Zeichenwerkzeuge
        // =============================================

        /**
         * Werkzeug wechseln
         * @param {string} tool - 'pen' oder 'eraser'
         */
        setTool: function(tool) {
            this.currentTool = tool;

            if (this.canvas) {
                if (tool === 'eraser') {
                    this.canvas.classList.add('eraser-active');
                } else {
                    this.canvas.classList.remove('eraser-active');
                }
            }
        },

        /**
         * Stiftfarbe setzen
         * @param {string} color - Hex-Farbwert
         */
        setColor: function(color) {
            this.currentColor = color;
        },

        /**
         * Stiftdicke setzen
         * @param {number} width - Breite in Pixeln
         */
        setLineWidth: function(width) {
            this.lineWidth = width;
        },

        /**
         * Canvas loeschen (mit Hintergrundfarbe fuellen)
         */
        clearCanvas: function() {
            if (!this.ctx || !this.canvas) return;

            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            this.ctx.fillStyle = this.boardColor;
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

            // Auch aus localStorage entfernen
            this.removeFromCache();
        },

        /**
         * Bestaetigungsdialog fuer "Alles loeschen"
         */
        showClearConfirm: function() {
            var self = this;

            var confirmOverlay = document.createElement('div');
            confirmOverlay.className = 'cbd-board-confirm-overlay';
            confirmOverlay.innerHTML =
                '<div class="cbd-board-confirm-dialog">' +
                    '<h4>Zeichnung l\u00F6schen?</h4>' +
                    '<p>Die gesamte Zeichnung wird unwiderruflich gel\u00F6scht.</p>' +
                    '<div class="cbd-board-confirm-actions">' +
                        '<button class="cbd-board-confirm-cancel">Abbrechen</button>' +
                        '<button class="cbd-board-confirm-delete">L\u00F6schen</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(confirmOverlay);

            // Cancel
            confirmOverlay.querySelector('.cbd-board-confirm-cancel').addEventListener('click', function() {
                document.body.removeChild(confirmOverlay);
            });

            // Delete
            confirmOverlay.querySelector('.cbd-board-confirm-delete').addEventListener('click', function() {
                self.clearCanvas();
                document.body.removeChild(confirmOverlay);
            });

            // Click auf Backdrop = Cancel
            confirmOverlay.addEventListener('click', function(e) {
                if (e.target === confirmOverlay) {
                    document.body.removeChild(confirmOverlay);
                }
            });
        },

        // =============================================
        // Canvas Event-Handler
        // =============================================

        /**
         * Zeichnen starten
         */
        onPointerDown: function(e) {
            this.isDrawing = true;

            var rect = this.canvas.getBoundingClientRect();
            this.lastX = e.clientX - rect.left;
            this.lastY = e.clientY - rect.top;

            // Einzelnen Punkt zeichnen
            this.ctx.beginPath();
            this.ctx.arc(this.lastX, this.lastY, this.lineWidth / 2, 0, Math.PI * 2);

            if (this.currentTool === 'eraser') {
                this.ctx.globalCompositeOperation = 'destination-out';
                this.ctx.fillStyle = 'rgba(0,0,0,1)';
            } else {
                this.ctx.globalCompositeOperation = 'source-over';
                this.ctx.fillStyle = this.currentColor;
            }

            this.ctx.fill();

            // Pointer capture fuer smooth drawing
            this.canvas.setPointerCapture(e.pointerId);
        },

        /**
         * Zeichnen (waehrend Maus/Finger gedrueckt)
         */
        onPointerMove: function(e) {
            if (!this.isDrawing) return;

            var rect = this.canvas.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;

            this.ctx.beginPath();
            this.ctx.moveTo(this.lastX, this.lastY);
            this.ctx.lineTo(x, y);

            this.ctx.lineWidth = this.lineWidth;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';

            if (this.currentTool === 'eraser') {
                this.ctx.globalCompositeOperation = 'destination-out';
                this.ctx.strokeStyle = 'rgba(0,0,0,1)';
            } else {
                this.ctx.globalCompositeOperation = 'source-over';
                this.ctx.strokeStyle = this.currentColor;
            }

            this.ctx.stroke();

            this.lastX = x;
            this.lastY = y;
        },

        /**
         * Zeichnen beenden
         */
        onPointerUp: function(e) {
            if (this.isDrawing) {
                this.isDrawing = false;
                this.ctx.globalCompositeOperation = 'source-over';
            }
        },

        /**
         * Tastendruck-Handler
         */
        onKeyDown: function(e) {
            if (e.key === 'Escape') {
                this.close();
            }
        },

        // =============================================
        // localStorage Persistenz
        // =============================================

        /**
         * Zeichnung in localStorage speichern
         */
        saveToCache: function() {
            if (!this.canvas || !this.containerId) return;

            try {
                var key = 'cbd-board-' + this.containerId;
                var dataUrl = this.canvas.toDataURL('image/png');
                localStorage.setItem(key, dataUrl);
            } catch (e) {
                // localStorage voll oder nicht verfuegbar
                console.warn('[CBD Board Mode] Zeichnung konnte nicht gespeichert werden:', e.message);
            }
        },

        /**
         * Zeichnung aus localStorage laden
         */
        loadFromCache: function() {
            if (!this.canvas || !this.ctx || !this.containerId) return;

            try {
                var key = 'cbd-board-' + this.containerId;
                var dataUrl = localStorage.getItem(key);

                if (!dataUrl) {
                    // Kein gespeicherter Stand - Hintergrundfarbe setzen
                    this.ctx.fillStyle = this.boardColor;
                    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                    return;
                }

                var self = this;
                var img = new Image();
                img.onload = function() {
                    // Hintergrund fuellen
                    self.ctx.fillStyle = self.boardColor;
                    self.ctx.fillRect(0, 0, self.canvas.width, self.canvas.height);
                    // Gespeicherte Zeichnung drueber legen
                    self.ctx.drawImage(img, 0, 0);
                };
                img.src = dataUrl;
            } catch (e) {
                // Fehler beim Laden
                console.warn('[CBD Board Mode] Zeichnung konnte nicht geladen werden:', e.message);
                this.ctx.fillStyle = this.boardColor;
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
            }
        },

        /**
         * Zeichnung aus localStorage entfernen
         */
        removeFromCache: function() {
            if (!this.containerId) return;

            try {
                var key = 'cbd-board-' + this.containerId;
                localStorage.removeItem(key);
            } catch (e) {
                // Ignorieren
            }
        }
    };

})();
