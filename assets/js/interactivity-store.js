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
					console.warn('[CBD] Content element not found');
					return;
				}

				const textToCopy = contentElement.innerText.trim();

				if (!textToCopy) {
					console.warn('[CBD] No text to copy');
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
				console.error('[CBD] Copy failed:', error);
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
				console.error('[CBD] html2canvas not loaded');
				context.screenshotError = true;
				return;
			}

			try {
				// Setze Loading-State
				context.screenshotLoading = true;

				// Icon zu Loading ändern
				const icon = element.ref.querySelector('.cbd-screenshot .dashicons');
				if (icon) {
					icon.classList.remove('dashicons-camera');
					icon.classList.add('dashicons-update-alt');
				}

				// Finde Container-Block Element für Screenshot
				const containerBlock = element.ref.querySelector('.cbd-container-block');
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

				// Screenshot erstellen
				const canvas = yield html2canvas(containerBlock, {
					useCORS: true,
					allowTaint: false,
					scale: 2,
					logging: false,
					backgroundColor: null
				});

				// Convert to blob
				const blob = yield new Promise(resolve => canvas.toBlob(resolve, 'image/png'));

				if (!blob) {
					throw new Error('Failed to create blob from canvas');
				}

				// ==============================================
				// TIER 1: Clipboard API (iOS 13.4+, Chrome, Firefox)
				// ==============================================
				if (navigator.clipboard && navigator.clipboard.write) {
					try {
						const item = new ClipboardItem({ 'image/png': blob });
						yield navigator.clipboard.write([item]);
						console.log('[CBD] ✅ Clipboard: Screenshot copied to clipboard');
						// Success - continue to cleanup
					} catch (err) {
						console.warn('[CBD] ❌ Clipboard failed:', err);
						// Try Tier 2: Web Share API
						yield tryWebShare(blob, canvas, context);
					}
				} else {
					console.warn('[CBD] Clipboard API not available');
					// Try Tier 2: Web Share API
					yield tryWebShare(blob, canvas, context);
				}

				// Helper: Try Web Share API
				async function tryWebShare(blob, canvas, context) {
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
							console.log('[CBD] ✅ Web Share: Screenshot shared via iOS Share Sheet');
							// Success - return without download
							return;
						} catch (err) {
							if (err.name === 'AbortError') {
								console.log('[CBD] ℹ️ Web Share: User cancelled');
								// User cancelled - don't fallback to download
								throw err;
							} else {
								console.warn('[CBD] ❌ Web Share failed:', err);
								// Continue to download
							}
						}
					} else {
						console.warn('[CBD] Web Share API not available');
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
					console.log('[CBD] ⬇️ Download: Screenshot downloaded');
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
				console.error('[CBD] Screenshot failed:', error);
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
				console.error('[CBD] PDF dependencies not loaded');
				context.pdfError = true;
				return;
			}

			try {
				context.pdfLoading = true;

				// Analog zu Screenshot, aber mit jsPDF
				const containerBlock = element.ref.querySelector('.cbd-container-block');
				if (!containerBlock) {
					throw new Error('Container block not found');
				}

				// Temporär expandieren
				const wasCollapsed = context.isCollapsed;
				if (wasCollapsed) {
					context.isCollapsed = false;
					yield new Promise(resolve => setTimeout(resolve, 350));
				}

				// Canvas erstellen
				const canvas = yield html2canvas(containerBlock, {
					useCORS: true,
					allowTaint: false,
					scale: 2,
					logging: false
				});

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

				setTimeout(() => {
					context.pdfSuccess = false;
				}, 2000);

			} catch (error) {
				console.error('[CBD] PDF export failed:', error);
				context.pdfError = true;
				context.pdfLoading = false;

				setTimeout(() => {
					context.pdfError = false;
				}, 2000);
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