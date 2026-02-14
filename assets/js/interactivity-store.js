/**
 * Container Block Designer - Interactivity API Store
 * Zukunftssichere Verwaltung mehrerer Block-Instanzen
 *
 * @package ContainerBlockDesigner
 * @since 2.8.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

store('container-block-designer', {
	state: {
		/**
		 * Globaler State für alle Blöcke
		 * Wird zwischen allen Block-Instanzen geteilt
		 */
		get hasHtml2Canvas() {
			return typeof html2canvas !== 'undefined';
		},
		get hasJsPDF() {
			return typeof jspdf !== 'undefined';
		}
	},

	actions: {
		/**
		 * Toggle-Funktion für Collapse/Expand
		 * Verwendet lokalen Context - jeder Block ist unabhängig
		 */
		*toggleCollapse() {
			const context = getContext();
			const element = getElement();

			// Toggle collapsed state im lokalen Context
			context.isCollapsed = !context.isCollapsed;

			// Update aria-expanded für Accessibility
			const contentElement = element.ref.querySelector('.cbd-container-content');
			if (contentElement) {
				contentElement.setAttribute('aria-hidden', context.isCollapsed ? 'true' : 'false');
			}

			// Update icon direction
			const icon = element.ref.querySelector('.cbd-collapse-toggle .dashicons');
			if (icon) {
				if (context.isCollapsed) {
					icon.classList.remove('dashicons-arrow-up-alt2');
					icon.classList.add('dashicons-arrow-down-alt2');
				} else {
					icon.classList.remove('dashicons-arrow-down-alt2');
					icon.classList.add('dashicons-arrow-up-alt2');
				}
			}
		},

		/**
		 * Text kopieren - verwendet lokalen Context für Container-ID
		 */
		*copyText() {
			const context = getContext();
			const element = getElement();

			try {
				// Finde Content-Element im aktuellen Block
				const contentElement = element.ref.querySelector('.cbd-container-content');
				if (!contentElement) {
					return;
				}

				const textToCopy = contentElement.innerText.trim();

				if (!textToCopy) {
					return;
				}

				// Clipboard API verwenden
				yield navigator.clipboard.writeText(textToCopy);

				// Visuelles Feedback im lokalen Context
				context.copySuccess = true;

				// Icon feedback
				const icon = element.ref.querySelector('.cbd-copy-text .dashicons');
				if (icon) {
					icon.classList.remove('dashicons-clipboard');
					icon.classList.add('dashicons-yes-alt');
				}

				// Feedback nach 2 Sekunden zurücksetzen
				setTimeout(() => {
					context.copySuccess = false;
					if (icon) {
						icon.classList.remove('dashicons-yes-alt');
						icon.classList.add('dashicons-clipboard');
					}
				}, 2000);

			} catch (error) {
				context.copyError = true;
				setTimeout(() => {
					context.copyError = false;
				}, 2000);
			}
		},

		/**
		 * Screenshot erstellen - verwendet html2canvas
		 */
		*createScreenshot() {
			const context = getContext();
			const element = getElement();

			// Prüfe ob html2canvas verfügbar ist
			if (typeof html2canvas === 'undefined') {
				context.screenshotError = true;
				return;
			}

			try {
				// Setze Loading-State
				context.screenshotLoading = true;

				// Icon zu Loading ändern (element.ref ist der Button selbst)
				const icon = element.ref.querySelector('.dashicons');
				if (icon) {
					icon.classList.remove('dashicons-camera');
					icon.classList.add('dashicons-update-alt');
				}

				// Finde Container-Block Element für Screenshot
				// element.ref ist der Button, wir müssen zum Container navigieren
				const mainContainer = element.ref.closest('[data-wp-interactive="container-block-designer"]');
				if (!mainContainer) {
					throw new Error('Main container not found');
				}

				const containerBlock = mainContainer.querySelector('.cbd-container-block');
				if (!containerBlock) {
					throw new Error('Container block not found');
				}

				// Temporär expandieren falls collapsed
				const wasCollapsed = context.isCollapsed;
				if (wasCollapsed) {
					context.isCollapsed = false;
					// Kurz warten bis Animation fertig ist
					yield new Promise(resolve => setTimeout(resolve, 350));
				}

				// Buttons ausblenden für Screenshot
				const actionButtons = mainContainer.querySelector('.cbd-action-buttons');
				let originalVisibility = '';
				if (actionButtons) {
					// Speichere originale Sichtbarkeit
					originalVisibility = actionButtons.style.visibility || '';
					// Verwende visibility statt display, damit Layout erhalten bleibt
					// und !important inline styles nicht überschrieben werden müssen
					actionButtons.style.setProperty('visibility', 'hidden', 'important');
					actionButtons.style.setProperty('opacity', '0', 'important');
				}

				// Kurze Verzögerung damit DOM aktualisiert wird
				yield new Promise(resolve => setTimeout(resolve, 50));

				// Screenshot erstellen
				const canvas = yield html2canvas(containerBlock, {
					useCORS: true,
					allowTaint: false,
					scale: 2,
					logging: false,
					backgroundColor: null
				});

				// Buttons wieder einblenden
				if (actionButtons) {
					actionButtons.style.removeProperty('visibility');
					actionButtons.style.removeProperty('opacity');
				}

				// ==============================================
				// TIER 1: Clipboard API (iOS 13.4+, Chrome, Firefox)
				// ==============================================
				let clipboardSuccess = false;

				if (navigator.clipboard && navigator.clipboard.write) {
					try {
						// Safari/iOS FIX: Promise direkt an ClipboardItem übergeben
						// NICHT vorher awaiten, sonst verliert Safari die User-Gesture
						const blobPromise = new Promise(resolve => canvas.toBlob(resolve, 'image/png'));

						const item = new ClipboardItem({ 'image/png': blobPromise });
						yield navigator.clipboard.write([item]);
						clipboardSuccess = true;
						// Success - skip other tiers
					} catch (err) {
						// Will fallback to Tier 2 below
					}
				} else {
				}

				// Blob für Tier 2/3 Fallbacks erstellen (nur wenn Clipboard fehlschlägt)
				let blob = null;
				if (!clipboardSuccess) {
					blob = yield new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
					if (!blob) {
						throw new Error('Failed to create blob from canvas');
					}
					yield tryWebShare(blob, canvas, context);
				}

				// Helper: Try Web Share API
				function* tryWebShare(blob, canvas, context) {
					const file = new File([blob], `cbd-screenshot-${Date.now()}.png`, { type: 'image/png' });

					// ==============================================
					// TIER 2: Web Share API (iOS 15+, Safari)
					// ==============================================
					if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
						try {
							yield navigator.share({
								files: [file],
								title: 'Container Block Screenshot'
							});
							// Success - return without download
							return;
						} catch (err) {
							if (err.name === 'AbortError') {
								// User cancelled - don't fallback to download
								throw err;
							} else {
								// Continue to download
							}
						}
					} else {
					}

					// ==============================================
					// TIER 3: Download Fallback (All browsers)
					// ==============================================
					const link = document.createElement('a');
					link.download = `cbd-container-${context.blockId || 'screenshot'}-${Date.now()}.png`;
					link.href = canvas.toDataURL('image/png');
					document.body.appendChild(link);
					link.click();
					document.body.removeChild(link);
				}

				// Wieder zusammenklappen falls vorher collapsed
				if (wasCollapsed) {
					context.isCollapsed = true;
				}

				// Success feedback
				context.screenshotSuccess = true;
				if (icon) {
					icon.classList.remove('dashicons-update-alt');
					icon.classList.add('dashicons-yes-alt');
				}

				setTimeout(() => {
					context.screenshotSuccess = false;
					context.screenshotLoading = false;
					if (icon) {
						icon.classList.remove('dashicons-yes-alt');
						icon.classList.add('dashicons-camera');
					}
				}, 2000);

			} catch (error) {
				context.screenshotError = true;
				context.screenshotLoading = false;

				// Icon zurücksetzen
				const icon = element.ref.querySelector('.cbd-screenshot .dashicons');
				if (icon) {
					icon.classList.remove('dashicons-update-alt', 'dashicons-yes-alt');
					icon.classList.add('dashicons-camera');
				}

				setTimeout(() => {
					context.screenshotError = false;
				}, 2000);
			}
		},

		/**
		 * PDF Export erstellen
		 */
		*createPDF() {
			const context = getContext();
			const element = getElement();

			// Prüfe Dependencies
			if (typeof html2canvas === 'undefined' || typeof jspdf === 'undefined') {
				context.pdfError = true;
				return;
			}

			try {
				context.pdfLoading = true;

				// Icon zu Loading ändern
				const icon = element.ref.querySelector('.dashicons');
				if (icon) {
					icon.classList.remove('dashicons-pdf');
					icon.classList.add('dashicons-update-alt');
				}

				// Finde Container-Block Element für PDF
				const mainContainer = element.ref.closest('[data-wp-interactive="container-block-designer"]');
				if (!mainContainer) {
					throw new Error('Main container not found');
				}

				const containerBlock = mainContainer.querySelector('.cbd-container-block');
				if (!containerBlock) {
					throw new Error('Container block not found');
				}

				// Temporär expandieren
				const wasCollapsed = context.isCollapsed;
				if (wasCollapsed) {
					context.isCollapsed = false;
					yield new Promise(resolve => setTimeout(resolve, 350));
				}

				// Buttons ausblenden für PDF
				const actionButtons = mainContainer.querySelector('.cbd-action-buttons');
				if (actionButtons) {
					actionButtons.style.setProperty('visibility', 'hidden', 'important');
					actionButtons.style.setProperty('opacity', '0', 'important');
				}

				// Kurze Verzögerung damit DOM aktualisiert wird
				yield new Promise(resolve => setTimeout(resolve, 50));

				// Canvas erstellen
				const canvas = yield html2canvas(containerBlock, {
					useCORS: true,
					allowTaint: false,
					scale: 2,
					logging: false,
					backgroundColor: null
				});

				// Buttons wieder einblenden
				if (actionButtons) {
					actionButtons.style.removeProperty('visibility');
					actionButtons.style.removeProperty('opacity');
				}

				// PDF erstellen
				const imgData = canvas.toDataURL('image/png');
				const pdf = new jspdf.jsPDF({
					orientation: canvas.width > canvas.height ? 'landscape' : 'portrait',
					unit: 'px',
					format: [canvas.width, canvas.height]
				});

				pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
				pdf.save(`cbd-container-${context.blockId || 'export'}-${Date.now()}.pdf`);

				// Wieder zusammenklappen
				if (wasCollapsed) {
					context.isCollapsed = true;
				}

				context.pdfSuccess = true;
				context.pdfLoading = false;

				// Icon Feedback
				if (icon) {
					icon.classList.remove('dashicons-update-alt');
					icon.classList.add('dashicons-yes-alt');
				}

				setTimeout(() => {
					context.pdfSuccess = false;
					if (icon) {
						icon.classList.remove('dashicons-yes-alt');
						icon.classList.add('dashicons-pdf');
					}
				}, 2000);

			} catch (error) {
				context.pdfError = true;
				context.pdfLoading = false;

				// Icon zurücksetzen
				if (icon) {
					icon.classList.remove('dashicons-update-alt', 'dashicons-yes-alt');
					icon.classList.add('dashicons-pdf');
				}

				setTimeout(() => {
					context.pdfError = false;
				}, 2000);
			}
		},

		/**
		 * Tafel-Modus oeffnen - Fullscreen-Overlay mit Canvas-Zeichenflaeche
		 */
		*toggleBoardMode() {
			const context = getContext();
			const element = getElement();

			const mainContainer = element.ref.closest('[data-wp-interactive="container-block-designer"]');
			if (!mainContainer) return;

			const containerBlock = mainContainer.querySelector('.cbd-container-block');
			if (!containerBlock) return;

			// Board-Farbe aus Button-Attribut lesen
			const boardButton = mainContainer.querySelector('.cbd-board-mode-toggle');
			const boardColor = boardButton?.getAttribute('data-board-color') || '#1a472a';

			// Board Mode oeffnen via globales Modul
			if (window.CBDBoardMode) {
				window.CBDBoardMode.open(
					context.containerId,
					containerBlock.innerHTML,
					boardColor
				);
			}
		}
	},

	callbacks: {
		/**
		 * Init callback - wird beim Mount des Blocks aufgerufen
		 */
		onInit() {
			const context = getContext();
			const element = getElement();

			// Initialisiere lokalen Context falls noch nicht vorhanden
			if (typeof context.isCollapsed === 'undefined') {
				context.isCollapsed = false;
			}

			// Set initial aria attributes für Accessibility
			const contentElement = element.ref.querySelector('.cbd-container-content');
			if (contentElement) {
				contentElement.setAttribute('aria-hidden', context.isCollapsed ? 'true' : 'false');
				contentElement.setAttribute('role', 'region');
			}

			// Logging für Debug
			if (window.CBD_DEBUG) {
				console.log('[CBD Interactivity] Block initialized:', {
					blockId: context.blockId,
					containerId: context.containerId,
					isCollapsed: context.isCollapsed
				});
			}
		}
	}
});