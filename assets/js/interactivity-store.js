/**
 * Container Block Designer - Interactivity API Store
 * Zukunftssichere Verwaltung mehrerer Block-Instanzen
 *
 * @package ContainerBlockDesigner
 * @since 2.8.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Erkennt Apple-Geräte (iOS, iPadOS, macOS-Safari) - AP-2.6.
 *
 * Auf diesen Geräten ist der Screenshot-Weg (html2canvas + Clipboard/Web-Share)
 * unzuverlässig: Safari verlangt `navigator.clipboard.write()` innerhalb
 * derselben User-Aktivierung, die durch das vorgelagerte `await html2canvas(...)`
 * verloren geht; iOS begrenzt zusätzlich die Canvas-Fläche und ignoriert
 * `<a download>` mit Data-URL. Reine, seiteneffektfreie Funktion, damit sie
 * sich isoliert mit gefälschten `navigator`-Werten testen lässt.
 *
 * @return {boolean} true auf iOS/iPadOS/macOS-Safari, sonst false.
 */
function istAppleGeraet() {
	const ua = navigator.userAgent || '';

	// iOS/iPadOS - dieselbe Erkennung wie beim Canvas-Flächendeckel in
	// createScreenshot() (isIOSDevice): iPadOS 13+ meldet sich als „MacIntel",
	// ist aber über maxTouchPoints > 1 vom echten Mac unterscheidbar.
	const isIOSDevice = /iPad|iPhone|iPod/.test(ua) ||
		(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	if (isIOSDevice) {
		return true;
	}

	// macOS-Safari: kein Touch, aber trotzdem Apple. Chromium-Abkömmlinge
	// (Chrome, Chromium, Edge, Opera) führen "Safari" ebenfalls im UA-String
	// und werden deshalb explizit ausgeschlossen.
	const vendor = navigator.vendor || '';
	return vendor.indexOf('Apple') !== -1 &&
		ua.indexOf('Safari') !== -1 &&
		ua.indexOf('Chrome') === -1 &&
		ua.indexOf('Chromium') === -1 &&
		ua.indexOf('Edg') === -1 &&
		ua.indexOf('OPR') === -1;
}

// Einmal berechnen statt bei jedem Aufruf neu - navigator.userAgent/vendor
// ändern sich während einer Sitzung nicht. Genutzt in callbacks.onInit
// (Knopf-Umschaltung) und actions.createScreenshot (Umleitung).
const cbdIstAppleGeraet = istAppleGeraet();

/**
 * Verzögerung bis die Aktionsleiste sich von selbst ausblendet (AP-2,
 * PLAN-Aktionsleiste-Autoausblenden.md). Eine benannte Konstante statt einer
 * nackten Zahl, damit der Wert nicht gegenüber der gleichlautenden Konstante
 * in interactivity-fallback.js auseinanderläuft.
 */
const CBD_AKTIONSLEISTE_VERZOEGERUNG = 1000;

// Zeitgeber je Container, nicht in einer einzelnen Modulvariable - sonst
// löschte ein zweiter Container auf derselben Seite den Zeitgeber des ersten.
const cbdAktionsleisteTimer = new WeakMap();

/**
 * Blendet die Aktionsleiste eines Containers CBD_AKTIONSLEISTE_VERZOEGERUNG
 * nach dem Erscheinen von selbst aus, indem die Klasse "cbd-actions-verborgen"
 * am Container (.cbd-container) gesetzt wird - das eigentliche Ausblenden
 * übernimmt CSS (cbd-frontend-clean.css, AP-1). Läuft nicht, solange der
 * Zeiger über der Leiste selbst steht oder der Container den Fokus enthält
 * (:focus-within): Sonst wäre die Leiste beim Zielen auf einen Knopf oder
 * beim Tab-Springen unbedienbar. Deckungsgleiche Funktion in
 * interactivity-fallback.js.
 *
 * @param {Element} container Wurzelelement mit der Klasse .cbd-container
 *   (identisch mit element.ref in callbacks.onInit).
 */
function initAktionsleisteAutoAusblenden(container) {
	if (!container) {
		return;
	}

	// container.querySelector() sucht in allen Nachfahren; bei verschachtelten
	// Containern liefert das trotzdem die eigene Leiste, weil sie im Markup
	// vor jedem inneren Container steht (erstes Fundstück gewinnt).
	const actionButtons = container.querySelector('.cbd-action-buttons');
	if (!actionButtons) {
		return;
	}

	function zeitgeberAbbrechen() {
		const timerId = cbdAktionsleisteTimer.get(container);
		if (timerId) {
			clearTimeout(timerId);
			cbdAktionsleisteTimer.delete(container);
		}
	}

	function zeitgeberStarten() {
		zeitgeberAbbrechen();
		const timerId = setTimeout(function() {
			cbdAktionsleisteTimer.delete(container);

			// Doppelte Absicherung: Auch falls der Zeitgeber durch ein
			// verpasstes Event nicht abgebrochen wurde, bleibt die Leiste
			// sichtbar, solange der Zeiger über ihr steht oder der Container
			// den Fokus enthält.
			if (container.matches(':focus-within') || actionButtons.matches(':hover')) {
				// Nicht abbrechen, sondern erneut anlaufen lassen: Ein blosses
				// return loeschte den Zeitgeber, und die Leiste bliebe dauerhaft
				// stehen, falls das zugehoerige mouseleave bzw. focusout einmal
				// ausbleibt. So blendet sie spaetestens eine Sekunde nach dem
				// Ende von Fokus oder Zeigerkontakt aus.
				zeitgeberStarten();
				return;
			}

			container.classList.add('cbd-actions-verborgen');
		}, CBD_AKTIONSLEISTE_VERZOEGERUNG);
		cbdAktionsleisteTimer.set(container, timerId);
	}

	container.addEventListener('mouseenter', function() {
		container.classList.remove('cbd-actions-verborgen');
		zeitgeberStarten();
	});

	container.addEventListener('mouseleave', function() {
		// Container verlassen: Zustand zurücksetzen, sonst bliebe die Leiste
		// beim nächsten Überfahren dauerhaft unsichtbar.
		zeitgeberAbbrechen();
		container.classList.remove('cbd-actions-verborgen');
	});

	actionButtons.addEventListener('mouseenter', function() {
		zeitgeberAbbrechen();
		container.classList.remove('cbd-actions-verborgen');
	});

	actionButtons.addEventListener('mouseleave', function() {
		zeitgeberStarten();
	});

	container.addEventListener('focusin', function() {
		zeitgeberAbbrechen();
		container.classList.remove('cbd-actions-verborgen');
	});

	container.addEventListener('focusout', function() {
		zeitgeberStarten();
	});
}

/**
 * Helper: Show class selector dialog for "Behandelt" feature
 * (Defined outside store to avoid 'this' context issues)
 */
function showClassSelectorDialog(classes, onClassToggle) {
	return new Promise((resolve) => {
		// Remove any existing dialogs first
		const existingDialogs = document.querySelectorAll('.cbd-behandelt-selector-dialog');
		existingDialogs.forEach(d => d.remove());

		const dialog = document.createElement('div');
		dialog.className = 'cbd-behandelt-selector-dialog';

		const escapeHtml = (text) => {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		};

		dialog.innerHTML = `
			<div class="cbd-behandelt-selector-overlay"></div>
			<div class="cbd-behandelt-selector-content">
				<h3>Klasse wählen</h3>
				<p>Wählen Sie alle Klassen aus, für die Sie den Status ändern möchten.</p>
				<div class="cbd-behandelt-class-options">
					${classes.map(cls => `
						<button class="cbd-behandelt-class-option ${cls.is_behandelt ? 'is-behandelt' : ''}" data-class-id="${cls.id}">
							<span class="cbd-class-name">${escapeHtml(cls.name)}</span>
							<span class="cbd-class-status">
								${cls.is_behandelt ? '<span class="dashicons dashicons-yes-alt"></span> Behandelt' : '<span class="dashicons dashicons-marker"></span> Nicht behandelt'}
							</span>
						</button>
					`).join('')}
				</div>
				<button class="cbd-behandelt-cancel">Fertig</button>
			</div>
		`;

		const cleanup = () => {
			dialog.removeEventListener('click', handleClick);
			if (dialog && dialog.parentNode) {
				dialog.parentNode.removeChild(dialog);
			}
		};

		const handleClick = (e) => {
			const classOption = e.target.closest('.cbd-behandelt-class-option');
			const cancelBtn = e.target.closest('.cbd-behandelt-cancel');
			const overlay = e.target.classList.contains('cbd-behandelt-selector-overlay');

			if (classOption && !classOption.disabled) {
				e.preventDefault();
				e.stopPropagation();
				const classId = classOption.getAttribute('data-class-id');

				// Button während AJAX sperren
				classOption.disabled = true;
				classOption.classList.add('is-loading');

				onClassToggle(classId).then((newStatus) => {
					classOption.disabled = false;
					classOption.classList.remove('is-loading');
					const statusSpan = classOption.querySelector('.cbd-class-status');
					if (newStatus) {
						classOption.classList.add('is-behandelt');
						if (statusSpan) statusSpan.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> Behandelt';
					} else {
						classOption.classList.remove('is-behandelt');
						if (statusSpan) statusSpan.innerHTML = '<span class="dashicons dashicons-marker"></span> Nicht behandelt';
					}
				}).catch((err) => {
					classOption.disabled = false;
					classOption.classList.remove('is-loading');
					alert('Fehler: ' + err.message);
				});

			} else if (cancelBtn || overlay) {
				e.preventDefault();
				e.stopPropagation();
				cleanup();
				resolve();
			}
		};

		dialog.addEventListener('click', handleClick, { once: false });
		document.body.appendChild(dialog);

		setTimeout(() => {
			const firstButton = dialog.querySelector('.cbd-behandelt-class-option');
			if (firstButton) firstButton.focus();
		}, 50);
	});
}

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

			// Apple-Geraete (iOS/iPadOS/macOS-Safari): auf den bereits
			// vorhandenen serverseitigen Einzelblock-PDF-Export umleiten,
			// statt html2canvas zu bemuehen (AP-2.6). Derselbe Aufruf wie in
			// actions.createPDF weiter unten.
			if (cbdIstAppleGeraet) {
				const mainContainer = element.ref.closest('[data-wp-interactive="container-block-designer"]');

				if (typeof window.cbdPDFExportServerSide === 'function' && window.jQuery && mainContainer) {
					const $ = window.jQuery;
					window.cbdPDFExportServerSide([$(mainContainer)], 'visual');
					return;
				}

				// Kein funktionsfaehiger PDF-Weg vorhanden - ein Knopf ohne
				// Funktion ist schlechter als keiner. console.warn bleibt
				// bewusst ungegated (siehe CLAUDE.md, Debugging-Konventionen).
				console.warn('[CBD Screenshot] window.cbdPDFExportServerSide nicht verfuegbar - Apple-PDF-Knopf wird ausgeblendet.');
				element.ref.style.setProperty('display', 'none', 'important');
				return;
			}

			// Pruefe ob html2canvas verfuegbar ist
			if (typeof html2canvas === 'undefined') {
				context.screenshotError = true;
				return;
			}

			// Ursache eines gescheiterten Zwischenablage-Versuchs (Stufe 1) merken,
			// damit der Haupt-catch sie bei vollständigem Fehlschlag ausgeben kann.
			let clipboardErrorCause = null;

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

				// Canvas-Fläche deckeln: fest scale:2 kann bei sehr hohen Blöcken
				// die Flächengrenze mancher Browser sprengen (toBlob liefert dann
				// null). Gleiches Muster wie im PDF-Pfad (pdf-server-side.js:26-27,
				// :787, :838-842): Gerät erkennen, Pixel-Obergrenze setzen, scale
				// so weit reduzieren, dass breite * hoehe * scale² darunter bleibt.
				const isIOSDevice = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
					(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
				const maxCanvasPixels = isIOSDevice ? 16000000 : 64000000;
				let screenshotScale = 2;
				const containerPixels = containerBlock.offsetWidth * containerBlock.offsetHeight;
				if (containerPixels * screenshotScale * screenshotScale > maxCanvasPixels) {
					screenshotScale = Math.max(1, Math.sqrt(maxCanvasPixels / containerPixels));
				}
				screenshotScale = Math.min(screenshotScale, 2);

				if (window.cbdDebug) {
					console.log('[CBD Screenshot] scale =', screenshotScale, 'für', containerBlock.offsetWidth, 'x', containerBlock.offsetHeight, 'px');
				}

				// Screenshot erstellen
				const canvas = yield html2canvas(containerBlock, {
					useCORS: true,
					allowTaint: false,
					scale: screenshotScale,
					logging: false,
					backgroundColor: '#ffffff'
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
						// Ursache merken statt verschlucken - der Ablauf faellt
						// weiterhin auf Stufe 2/3 (tryWebShare) zurueck
						clipboardErrorCause = err;
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
					yield* tryWebShare(blob, canvas, context);
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
				context.screenshotLoading = false;

				// Bewusster Abbruch des nativen Teilen-Dialogs (Tier 2, tryWebShare)
				// ist kein Fehler - der AbortError wird dort bewusst weitergereicht
				// (siehe throw err in tryWebShare) und landet hier. Stillschweigend
				// auf das Ausgangs-Icon zurückstellen: kein Warn-Icon, kein
				// console.error. Entspricht resetButton() in interactivity-fallback.js.
				if (error && error.name === 'AbortError') {
					const icon = element.ref.querySelector('.dashicons');
					if (icon) {
						icon.classList.remove('dashicons-update-alt', 'dashicons-yes-alt', 'dashicons-warning');
						icon.classList.add('dashicons-camera');
					}
					return;
				}

				context.screenshotError = true;

				// Ursache sichtbar machen - console.error bleibt bewusst
				// ungegated (siehe CLAUDE.md, Abschnitt Debugging-Konventionen)
				console.error('[CBD Screenshot] Fehlgeschlagen:', error, clipboardErrorCause ? { clipboardError: clipboardErrorCause } : '');

				// Icon auf Warnsymbol setzen, damit der Fehlschlag sichtbar ist -
				// sonst bliebe der Spinner (dashicons-update-alt) haengen
				const icon = element.ref.querySelector('.dashicons');
				if (icon) {
					icon.classList.remove('dashicons-update-alt', 'dashicons-yes-alt');
					icon.classList.add('dashicons-warning');
				}

				setTimeout(() => {
					context.screenshotError = false;
					if (icon) {
						icon.classList.remove('dashicons-warning');
						icon.classList.add('dashicons-camera');
					}
				}, 3000);
			}
		},

		/**
		 * PDF Export erstellen - nutzt serverseitigen Export (mPDF) wenn verfügbar
		 */
		*createPDF() {
			const context = getContext();
			const element = getElement();

			try {
				context.pdfLoading = true;

				// Icon zu Loading ändern
				const icon = element.ref.querySelector('.dashicons');
				if (icon) {
					icon.classList.remove('dashicons-pdf');
					icon.classList.add('dashicons-update-alt');
				}

				// Finde Container Element
				const mainContainer = element.ref.closest('[data-wp-interactive="container-block-designer"]');
				if (!mainContainer) {
					throw new Error('Main container not found');
				}

				// Priorität 1: Server-seitiger Export (mPDF - beste Qualität)
				if (typeof window.cbdPDFExportServerSide === 'function' && window.jQuery) {
					const $ = window.jQuery;
					window.cbdPDFExportServerSide([$(mainContainer)], 'visual');

					context.pdfSuccess = true;
					context.pdfLoading = false;

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
					}, 3000);
					return;
				}

				// Fallback: html2canvas + jsPDF (Client-seitig)
				if (typeof html2canvas === 'undefined') {
					throw new Error('Keine PDF-Bibliothek verfügbar');
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

				// Buttons ausblenden für Screenshot
				const actionButtons = mainContainer.querySelector('.cbd-action-buttons');
				if (actionButtons) {
					actionButtons.style.setProperty('visibility', 'hidden', 'important');
					actionButtons.style.setProperty('opacity', '0', 'important');
				}

				yield new Promise(resolve => setTimeout(resolve, 50));

				const canvas = yield html2canvas(containerBlock, {
					useCORS: true,
					allowTaint: false,
					scale: 2,
					logging: false,
					backgroundColor: null
				});

				if (actionButtons) {
					actionButtons.style.removeProperty('visibility');
					actionButtons.style.removeProperty('opacity');
				}

				// jsPDF erstellen
				const jsPDFClass = window.jspdf?.jsPDF || window.jsPDF;
				if (!jsPDFClass) {
					throw new Error('jsPDF nicht verfügbar');
				}

				const imgData = canvas.toDataURL('image/png');
				const pdf = new jsPDFClass({
					orientation: canvas.width > canvas.height ? 'landscape' : 'portrait',
					unit: 'px',
					format: [canvas.width, canvas.height]
				});

				pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
				pdf.save(`cbd-container-${context.blockId || 'export'}-${Date.now()}.pdf`);

				if (wasCollapsed) {
					context.isCollapsed = true;
				}

				context.pdfSuccess = true;
				context.pdfLoading = false;

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

				const icon = element.ref.querySelector('.dashicons');
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
			const boardColor = boardButton?.getAttribute('data-board-color') || '#ffffff';

			// Classroom-Optionen zusammenstellen
			const classroomData = window.cbdClassroomData || {};
			const options = {
				stableContainerId: context.stableContainerId || mainContainer.getAttribute('data-stable-id') || null,
				pageId: context.pageId || classroomData.pageId || null,
				classes: classroomData.classes || [],
				ajaxUrl: classroomData.ajaxUrl || null,
				nonce: classroomData.nonce || null
			};

			// Board Mode oeffnen via globales Modul
			if (window.CBDBoardMode) {
				window.CBDBoardMode.open(
					context.containerId,
					containerBlock.innerHTML,
					boardColor,
					options
				);
			}
		},

		/**
		 * Behandelt-Status togglen - Klassen-System
		 */
		*toggleBehandelt() {
			const context = getContext();
			const element = getElement();

			const classroomData = window.cbdClassroomData || {};

			if (!classroomData.classes || classroomData.classes.length === 0) {
				alert('Keine Klassen vorhanden. Bitte erstellen Sie zuerst eine Klasse.');
				return;
			}

			const mainContainer = element.ref.closest('[data-wp-interactive="container-block-designer"]');
			const containerId = context.stableContainerId || mainContainer?.getAttribute('data-stable-id');
			const pageId = context.pageId || classroomData.pageId;

			// Aktuellen Status aller Klassen laden
			const formData = new FormData();
			formData.append('action', 'cbd_get_block_status');
			formData.append('nonce', classroomData.nonce);
			formData.append('page_id', pageId);
			formData.append('container_id', containerId);

			const statusResponse = yield fetch(classroomData.ajaxUrl, {
				method: 'POST',
				body: formData
			});

			const statusData = yield statusResponse.json();

			if (!statusData.success) {
				alert('Fehler beim Laden des Status.');
				return;
			}

			// Lokale Kopie der Klassen mit Status (wird im Dialog live aktualisiert)
			const currentClasses = statusData.data.classes;

			// Callback: wird für jede angeklickte Klasse aufgerufen
			const onClassToggle = (classId) => {
				const cls = currentClasses.find(c => String(c.id) === String(classId));
				const newStatus = cls ? !cls.is_behandelt : true;

				const fd = new FormData();
				fd.append('action', 'cbd_toggle_behandelt');
				fd.append('nonce', classroomData.nonce);
				fd.append('class_id', classId);
				fd.append('page_id', pageId);
				fd.append('container_id', containerId);
				fd.append('behandelt', newStatus ? '1' : '0');

				return fetch(classroomData.ajaxUrl, { method: 'POST', body: fd })
					.then(r => r.json())
					.then(data => {
						if (data.success) {
							if (cls) cls.is_behandelt = newStatus;
							return newStatus;
						}
						throw new Error(data.data || 'Fehler beim Speichern');
					});
			};

			// Dialog öffnen – bleibt offen bis "Fertig" oder Overlay-Klick
			yield showClassSelectorDialog(currentClasses, onClassToggle);

			// Nach Schließen: Icon anhand Gesamtstatus aktualisieren
			const anyBehandelt = currentClasses.some(c => c.is_behandelt);
			context.isBehandelt = anyBehandelt;

			const icon = element.ref.querySelector('.dashicons');
			if (icon) {
				icon.classList.remove('dashicons-yes-alt', 'dashicons-marker');
				if (anyBehandelt) {
					icon.classList.add('dashicons-yes-alt');
					icon.style.color = '#4caf50';
					setTimeout(() => { icon.style.color = ''; }, 1000);
				} else {
					icon.classList.add('dashicons-marker');
				}
			}
		},

		/**
		 * Helper: Zeige Klassen-Auswahl Dialog fuer Behandelt-Markierung
		 */
		*showClassSelectorForBehandelt(classes) {
			return yield new Promise((resolve) => {
				// Erstelle Dialog
				const dialog = document.createElement('div');
				dialog.className = 'cbd-behandelt-selector-dialog';

				// Helper function for escaping HTML
				const escapeHtml = (text) => {
					const div = document.createElement('div');
					div.textContent = text;
					return div.innerHTML;
				};

				dialog.innerHTML = `
					<div class="cbd-behandelt-selector-overlay"></div>
					<div class="cbd-behandelt-selector-content">
						<h3>Klasse wählen</h3>
						<p>Für welche Klasse möchten Sie den Status ändern?</p>
						<div class="cbd-behandelt-class-options">
							${classes.map(cls => `
								<button class="cbd-behandelt-class-option" data-class-id="${cls.id}">
									${escapeHtml(cls.name)}
								</button>
							`).join('')}
						</div>
						<button class="cbd-behandelt-cancel">Abbrechen</button>
					</div>
				`;

				document.body.appendChild(dialog);

				// Event listeners
				dialog.querySelectorAll('.cbd-behandelt-class-option').forEach(btn => {
					btn.addEventListener('click', () => {
						const classId = btn.getAttribute('data-class-id');
						document.body.removeChild(dialog);
						resolve(classId);
					});
				});

				dialog.querySelector('.cbd-behandelt-cancel').addEventListener('click', () => {
					document.body.removeChild(dialog);
					resolve(null);
				});

				dialog.querySelector('.cbd-behandelt-selector-overlay').addEventListener('click', () => {
					document.body.removeChild(dialog);
					resolve(null);
				});
			});
		},

		/**
		 * Helper: HTML escaping
		 */
		escHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
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

			// Aktionsleiste blendet sich nach CBD_AKTIONSLEISTE_VERZOEGERUNG
			// von selbst aus (AP-2, PLAN-Aktionsleiste-Autoausblenden.md).
			// element.ref ist hier bereits der Container (.cbd-container),
			// weil data-wp-init am Wrapper selbst steht.
			initAktionsleisteAutoAusblenden(element.ref);

			// Apple-Geraete (iOS/iPadOS/macOS-Safari): Screenshot-Knopf(-e)
			// optisch zum Einzelblock-PDF-Knopf umschalten (AP-2.6). Die
			// eigentliche Umleitung passiert in actions.createScreenshot;
			// hier geht es nur um Icon/Beschriftung. Bleibt das Screenshot-
			// Feature abgeschaltet, existiert der Knopf gar nicht - die
			// NodeList ist dann einfach leer.
			if (cbdIstAppleGeraet) {
				const screenshotButtons = element.ref.querySelectorAll('.cbd-screenshot');
				screenshotButtons.forEach((button) => {
					const screenshotIcon = button.querySelector('.dashicons');
					if (screenshotIcon) {
						screenshotIcon.classList.remove('dashicons-camera');
						screenshotIcon.classList.add('dashicons-pdf');
					}
					button.setAttribute('title', 'Diesen Block als PDF speichern');
					button.setAttribute('aria-label', 'Diesen Block als PDF speichern');
					button.setAttribute('data-cbd-apple-pdf', '1');
				});
			}

			// Logging für Debug
			if (window.CBD_DEBUG) {
				window.cbdDebug && console.log('[CBD Interactivity] Block initialized:', {
					blockId: context.blockId,
					containerId: context.containerId,
					isCollapsed: context.isCollapsed
				});
			}
		}
	}
});