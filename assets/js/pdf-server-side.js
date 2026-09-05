/**
 * Server-Side PDF Generation for Container Block Designer
 *
 * Hybrid approach:
 * 1. Expand all collapsed blocks (including nested)
 * 2. Extract clean HTML per block
 * 3. Convert KaTeX formulas to rendered HTML
 * 4. Screenshot only interactive elements (modular-blocks)
 * 5. Send structured data to server (mPDF renders the PDF)
 * 6. Restore original collapsed states
 *
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

(function () {
    'use strict';

    var $ = window.jQuery || window.$;
    if (!$) {
        console.error('[CBD PDF] jQuery not available');
        return;
    }

    // AP-2.9: Theme-Farben fuer die UI-Chrome dieses Skripts (Fortschritts-
    // Overlay in createProgressOverlay() weiter unten). Gleiches Muster wie
    // in floating-pdf-button.js: getComputedStyle einmalig beim Laden lesen,
    // Fallback ist der bisherige Literalwert. Betrifft NICHT die separate
    // collectCSSVariables()-Funktion weiter unten (bestehender, von diesem
    // AP nicht behobener Code mit abweichenden Variablennamen, siehe
    // Uebergabenotiz AP-2.9).
    var rootStyles = getComputedStyle(document.documentElement);
    var colorUiSurface = rootStyles.getPropertyValue('--color-ui-surface').trim() || '#e24614';
    var colorBackground = rootStyles.getPropertyValue('--color-background').trim() || '#ffffff';
    var colorBorderLight = rootStyles.getPropertyValue('--color-border-light').trim() || '#eeeeee';
    var colorTextMuted = rootStyles.getPropertyValue('--color-text-muted').trim() || '#666666';

    // iOS detection
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // AP-2.3: Cache class_id -> Zeichnungen-Array, gueltig fuer EINEN
    // Export-Lauf (zurueckgesetzt am Anfang jedes cbdPDFExportServerSide()-
    // Aufrufs, siehe dort). Container werden sequentiell blockweise verarbeitet
    // (processBlocksSequentially/processOneBlock) - injectServerDrawings()
    // wird also PRO AUSGEWAEHLTEM TOP-LEVEL-CONTAINER erneut aufgerufen, nicht
    // nur einmal fuer den ganzen Export. Ohne diesen ueber alle Bloecke
    // geteilten Cache waeren zwei ausgewaehlte Container mit identischer
    // class_id zwei getrennte AJAX-Aufrufe (einer je processOneBlock-
    // Durchlauf) - das verletzt das Akzeptanzkriterium "GENAU EIN Aufruf je
    // class_id, nicht einer je Container". Die sequentielle Verarbeitung
    // macht ein einfaches synchrones Cache-Objekt ausreichend: Bis Block 2
    // dran ist, ist die Anfrage von Block 1 fuer dieselbe class_id laengst
    // abgeschlossen (Erfolg oder Fehler wird ebenfalls gecacht, damit eine
    // nicht zugreifbare/fehlerhafte class_id nicht bei jedem weiteren
    // Container erneut angefragt wird).
    var serverDrawingsCache = {};

    // N2 (PLAN-Nachtraege-Klassenmodus.md, Diagnose docs/diagnose-pdf-formeln.md):
    // Ergebnis des ersten foreignObjectRendering-Versuchs, gemerkt fuer den
    // ganzen Seitenaufruf. Liefert html2canvas mit foreignObjectRendering: true
    // eine leere Leinwand, ist das eine Eigenschaft des Browsers, nicht der
    // einzelnen Formel - der Versuch schlaegt dann fuer JEDE Formel fehl und
    // kostet trotzdem jedes Mal mehrere Sekunden. Ohne dieses Merken zahlt jede
    // Formel den aussichtslosen Versuch erneut; genau daran hing die gemessene
    // Exportdauer von mehreren Minuten fuer eine einzige Seite.
    var foRenderingLiefertLeerbild = false;

    /**
     * Main export function - called by floating-pdf-button.js
     *
     * @param {jQuery|Array} containerBlocks jQuery collection or array of jQuery elements
     * @param {string} mode 'visual'|'print'|'text'
     * @param {number} quality Scale factor (only for screenshots of interactive elements)
     */
    window.cbdPDFExportServerSide = function (containerBlocks, mode, quality, includeDrawings) {
        mode = mode || 'visual';
        quality = quality || (isIOS ? 1 : 1.5);
        // AP-2.3: Vierter Parameter steuert, ob lokale ("Eigene Notizen") UND
        // serverseitige Zeichnungen (Tafelbilder) ins PDF eingefuegt werden.
        // Default true bei fehlendem/undefined Parameter erhaelt das bisherige
        // Verhalten bestehender Aufrufer (Apple-PDF-Weiche in
        // interactivity-store.js/interactivity-fallback.js, die den Parameter
        // nicht kennen).
        includeDrawings = (includeDrawings === undefined) ? true : !!includeDrawings;
        // Frischer Cache je Export-Lauf - siehe Kommentar bei der Deklaration
        // von serverDrawingsCache oben. Verhindert sowohl fehlende Aufrufe
        // (neue Zeichnung seit dem letzten Export uebersehen) als auch
        // unbegrenztes Wachstum ueber mehrere Exportlaeufe einer Sitzung.
        serverDrawingsCache = {};

        // Normalize to jQuery collection
        if (Array.isArray(containerBlocks)) {
            var $merged = $();
            for (var i = 0; i < containerBlocks.length; i++) {
                $merged = $merged.add(containerBlocks[i]);
            }
            containerBlocks = $merged;
        } else if (!containerBlocks.jquery) {
            containerBlocks = $(containerBlocks);
        }

        if (containerBlocks.length === 0) {
            alert('Keine Container-Blöcke zum Exportieren gefunden.');
            return false;
        }

        window.cbdDebug && console.log('[CBD PDF] Starting export:', containerBlocks.length, 'blocks, mode:', mode);

        // Show progress overlay
        var $overlay = createProgressOverlay(containerBlocks.length);
        $('body').append($overlay);

        // Step 1: Expand all collapsed blocks
        var collapsedStates = expandAllBlocks(containerBlocks);

        // Step 2: Wait for expansion animation, then process
        setTimeout(function () {
            processBlocksSequentially(containerBlocks, mode, quality, includeDrawings, $overlay, collapsedStates);
        }, 400);

        return true;
    };

    // =========================================================================
    // Block Expansion (reuse proven logic from old implementation)
    // =========================================================================

    /**
     * Container-Erkennung, wortgleich mit assets/js/classroom-page-filter.js.
     * Der zweite Teil faengt Container ohne data-wp-interactive ab.
     */
    var CONTAINER_SELEKTOR = '[data-wp-interactive="container-block-designer"], [data-stable-id^="cbd-"]';

    /**
     * Expand all collapsed blocks (including nested ones)
     * Returns array of states to restore later
     */
    function expandAllBlocks(containerBlocks) {
        var states = [];

        containerBlocks.each(function () {
            var $block = $(this);

            // Find ALL containers (including nested).
            //
            // N2: Bis dahin wurde ausschliesslich nach
            // [data-wp-interactive="container-block-designer"] gesucht. Dieses
            // Attribut traegt nur das interaktive Wurzelelement eines Containers -
            // ein Container ohne dieses Attribut bleibt zugeklappt, seine Formeln
            // haben dann Mass 0 und werden von der Groessenbremse in
            // captureFormulaImages() still uebersprungen; im PDF bleibt der
            // Textrueckfall stehen.
            //
            // Bewusst KEINE neue Erkennungsregel: Dies ist derselbe
            // Doppel-Selektor, den assets/js/classroom-page-filter.js bereits
            // benutzt, um Container-Bloecke einer Seite einzusammeln.
            var $allContainers = $block.find(CONTAINER_SELEKTOR);
            if ($block.is(CONTAINER_SELEKTOR)) {
                $allContainers = $allContainers.add($block);
            }

            $allContainers.each(function () {
                var $container = $(this);
                var $content = $container.find('.cbd-container-content').first();

                if ($content.length > 0) {
                    var computed = window.getComputedStyle($content[0]);
                    var isHidden = computed.display === 'none' ||
                        computed.visibility === 'hidden' ||
                        computed.maxHeight === '0px';

                    if (isHidden) {
                        states.push({
                            element: $content[0],
                            type: 'content',
                            display: $content[0].style.display,
                            visibility: $content[0].style.visibility,
                            maxHeight: $content[0].style.maxHeight,
                            overflow: $content[0].style.overflow,
                            height: $content[0].style.height,
                            opacity: $content[0].style.opacity
                        });

                        $content[0].style.setProperty('display', 'block', 'important');
                        $content[0].style.setProperty('visibility', 'visible', 'important');
                        $content[0].style.setProperty('opacity', '1', 'important');
                        $content[0].style.setProperty('max-height', 'none', 'important');
                        $content[0].style.setProperty('overflow', 'visible', 'important');
                        $content[0].style.setProperty('height', 'auto', 'important');
                    }
                }
            });

            // Expand <details> elements
            $block.find('details').each(function () {
                if (!this.open) {
                    states.push({ element: this, type: 'details', open: false });
                    this.open = true;
                }
            });

            // Note: Drawings (Tafelbilder/Notizen) are injected directly from
            // localStorage in processOneBlock() via injectDrawingsFromStorage()
        });

        return states;
    }

    /**
     * Restore original collapsed states
     */
    function restoreStates(states) {
        for (var i = 0; i < states.length; i++) {
            var s = states[i];
            if (s.type === 'details') {
                s.element.open = s.open;
            } else if (s.type === 'content') {
                var el = s.element;
                el.style.removeProperty('display');
                el.style.removeProperty('visibility');
                el.style.removeProperty('opacity');
                el.style.removeProperty('max-height');
                el.style.removeProperty('overflow');
                el.style.removeProperty('height');
                el.style.display = s.display || '';
                el.style.visibility = s.visibility || '';
                el.style.maxHeight = s.maxHeight || '';
                el.style.overflow = s.overflow || '';
                el.style.height = s.height || '';
                el.style.opacity = s.opacity || '';
            }
        }
    }

    // =========================================================================
    // Block Processing Pipeline
    // =========================================================================

    /**
     * Process blocks one by one (sequential to avoid memory issues on iOS)
     */
    function processBlocksSequentially(containerBlocks, mode, quality, includeDrawings, $overlay, collapsedStates) {
        var blocksData = [];
        var totalBlocks = containerBlocks.length;
        var currentIndex = 0;

        function processNext() {
            if (currentIndex >= totalBlocks) {
                // All blocks processed - restore states and send to server
                restoreStates(collapsedStates);
                updateProgress($overlay, totalBlocks, totalBlocks, 'PDF wird auf dem Server erstellt...');
                sendToServer(blocksData, mode, $overlay);
                return;
            }

            var $block = $(containerBlocks[currentIndex]);
            updateProgress($overlay, currentIndex + 1, totalBlocks, 'Block ' + (currentIndex + 1) + ' wird verarbeitet...');

            processOneBlock($block, mode, quality, includeDrawings, function (blockData) {
                blocksData.push(blockData);
                currentIndex++;
                // Use setTimeout to prevent UI freeze
                setTimeout(processNext, 50);
            });
        }

        processNext();
    }

    /**
     * Process a single block: extract HTML, formulas, and screenshots
     */
    function processOneBlock($block, mode, quality, includeDrawings, callback) {
        // Step 1: Find interactive elements FIRST and ensure they have IDs
        // (must happen before cloning so the IDs are included in the clone)
        var interactiveElements = findInteractiveElements($block);

        // Step 1b: Formel-IDs vergeben - ebenfalls VOR dem Klonen.
        //
        // N2: Bis dahin geschah das erst nach $block.clone(). Der Klon truege
        // dann leere id-Attribute und der Server koennte den Platzhalter keiner
        // Formel zuordnen. Praktisch trat das nicht ein, weil CBD_LaTeX_Parser
        // jede Formel bereits mit id ausliefert - fuer clientseitig
        // nachgerenderte Formeln ohne id waere die Falle aber zugeschnappt.
        // Dieselbe Reihenfolge gilt aus demselben Grund bereits fuer die
        // interaktiven Elemente in Schritt 1.
        var formulaElements = [];
        var formulaCounter = 0;
        $block.find('.cbd-latex-formula').each(function () {
            var el = this;
            if (!el.id) {
                el.id = 'cbd-pdf-formula-' + Date.now() + '-' + (formulaCounter++);
            }
            formulaElements.push({
                id: el.id,
                element: el,
                isDisplay: $(el).hasClass('cbd-latex-display')
            });
        });

        // Step 2: Clone block for HTML extraction (don't modify original)
        var $clone = $block.clone();

        // Remove interactive controls and unnecessary elements from clone
        $clone.find('.cbd-action-buttons').remove();
        $clone.find('.cbd-collapse-toggle').remove();
        $clone.find('.cbd-header-menu').remove();
        $clone.find('.cbd-container-number').remove();
        $clone.find('.cbd-selection-menu').remove();
        $clone.find('.cbd-board-overlay').remove();
        $clone.find('.cbd-drawing-canvas').remove();
        $clone.find('script').remove();        // Remove isolated scripts (not needed in PDF)
        $clone.find('svg').remove();           // Remove SVG icons (controls)

        // Remove existing drawing sections (we'll rebuild from localStorage/server data)
        $clone.find('.cbd-drawing-section').remove();
        $clone.find('.cbd-local-drawing-section').remove();
        $clone.find('.cbd-class-drawing-section').remove();
        // AP-1.2 (PLAN-PDF-Export-und-Tafelmodus-Fixes.md, AP-1.1-Fund):
        // injectDrawingsFromStorage() erzeugt tatsaechlich .cbd-pdf-drawing-section
        // (nicht einen der drei Namen oben) - ohne diese Zeile griff die
        // Aufraeumung nie und wiederholte Exports haetten Notizen dupliziert.
        $clone.find('.cbd-pdf-drawing-section').remove();

        // AP-2.3: injectServerDrawings() braucht einen asynchronen AJAX-Aufruf
        // (Bulk-Endpoint cbd_get_page_drawings aus AP-2.1). Der Rest der bisher
        // synchronen Verarbeitung (inkl. der html-Extraktion aus $clone) darf
        // erst NACH dessen Abschluss laufen, sonst fehlten serverseitige
        // Tafelbilder im bereits ausgelesenen $clone-HTML. continueProcessing()
        // buendelt deshalb den kompletten bisherigen Rest dieser Funktion und
        // wird entweder sofort (kein includeDrawings) oder als Callback nach
        // den Drawing-Injections aufgerufen (siehe Funktionsende).
        function continueProcessing() {

        // KaTeX-Formeln: Im Klon durch Platzhalter mit Fallback-Text ersetzen.
        // Die Originale werden unten per html2canvas als PNG erfasst und
        // serverseitig als <img> eingesetzt (mPDF kann KaTeX-HTML nicht rendern).
        // Schlägt der Capture fehl, ersetzt der Server nichts und der lesbare
        // Fallback-Text bleibt stehen (bisheriges Verhalten).
        // Die IDs dafuer sind bereits VOR dem Klonen vergeben worden - siehe
        // Schritt 1b am Anfang dieser Funktion.

        $clone.find('.cbd-latex-formula').each(function () {
            var $formula = $(this);
            var isDisplay = $formula.hasClass('cbd-latex-display');
            var formulaId = this.id || '';

            // Lesbaren Fallback-Text aus dem gerenderten KaTeX extrahieren
            var readableText = '';
            var $katexHtml = $formula.find('.katex-html');
            if ($katexHtml.length > 0) {
                var $kClone = $katexHtml.clone();
                $kClone.find('.katex-mathml').remove();
                readableText = $kClone.text().replace(/\s+/g, ' ').trim();
            }
            if (!readableText) {
                var latex = $formula.attr('data-latex') || '';
                readableText = latexToReadable(latex);
            }

            var style = isDisplay
                ? 'display:block; text-align:center; margin:10px 0; font-size:12pt; font-family:dejavusans,sans-serif;'
                : 'display:inline; font-family:dejavusans,sans-serif;';
            var tag = isDisplay ? 'div' : 'span';
            var replacement = '<' + tag + ' class="cbd-pdf-formula" data-cbd-formula-id="' + formulaId + '" style="' + style + '">' +
                $('<span>').text(readableText || ' ').html() +
                '</' + tag + '>';
            $formula.replaceWith(replacement);
        });

        // Ensure all content is visible in clone
        $clone.find('.cbd-container-content, .cbd-content, .cbd-collapsible-content').each(function () {
            this.style.setProperty('display', 'block', 'important');
            this.style.setProperty('visibility', 'visible', 'important');
            this.style.setProperty('opacity', '1', 'important');
            this.style.setProperty('max-height', 'none', 'important');
        });
        $clone.find('.cbd-collapsed').removeClass('cbd-collapsed');

        // Force page-break-inside:avoid on the container block itself (inline style for mPDF)
        $clone.find('.cbd-container-block').each(function () {
            this.style.setProperty('page-break-inside', 'avoid', 'important');
        });
        // Also on the outermost wrapper
        $clone[0].style.setProperty('page-break-inside', 'avoid', 'important');

        // Step 3: Replace interactive elements in clone with simple placeholders
        // This avoids the server having to regex-match complex nested HTML
        for (var i = 0; i < interactiveElements.length; i++) {
            var item = interactiveElements[i];
            var $cloneEl = $clone.find('#' + CSS.escape(item.id));
            if ($cloneEl.length > 0) {
                $cloneEl.replaceWith(
                    '<div data-cbd-screenshot-id="' + item.id + '" ' +
                    'style="page-break-inside:avoid; margin:8px 0; text-align:center;">' +
                    '[Screenshot: ' + item.id + ']</div>'
                );
            }
        }

        // Extract title
        var title = $clone.find('.cbd-block-title').first().text().trim() || '';

        // Get clean HTML from clone
        var html = $clone[0].outerHTML;

        // Formeln als PNG erfassen (aus dem Original-DOM, das gerade aufgeklappt ist),
        // danach Screenshots der interaktiven Elemente.
        // Im Text-Modus bewusst kein Capture – dort gilt der lesbare Fallback-Text.
        captureFormulaImages(mode === 'text' ? [] : formulaElements, function (formulas) {
            if (interactiveElements.length > 0 && mode !== 'text') {
                // Take screenshots of interactive elements (from original DOM)
                screenshotInteractiveElements(interactiveElements, quality, function (screenshots) {
                    callback({
                        html: html,
                        title: title,
                        formulas: formulas,
                        screenshots: screenshots
                    });
                });
            } else {
                callback({
                    html: html,
                    title: title,
                    formulas: formulas,
                    screenshots: []
                });
            }
        });

        } // Ende continueProcessing()

        if (includeDrawings) {
            // Lokale "Eigene Notizen" (localStorage) bleiben synchron wie bisher.
            injectDrawingsFromStorage($block, $clone);
            // Serverseitige Tafelbilder (AP-2.1/AP-2.2) NACH den lokalen
            // Notizen einfuegen (asynchron), dann erst mit der Formel-/
            // Screenshot-Verarbeitung fortfahren.
            injectServerDrawings($block, $clone, continueProcessing);
        } else {
            continueProcessing();
        }
    }

    /**
     * Prueft, ob eine Leinwand ueberhaupt bemalt ist - also mindestens ein
     * Pixel mit nennenswerter Deckkraft traegt.
     *
     * Die Schwelle ist bewusst niedrig (EIN Pixel genuegt): Ein Bruchstrich,
     * ein Komma oder ein einzelner Buchstabe darf nicht als "leer" gelten.
     * Ist die Leinwand nicht auslesbar, gilt sie als bemalt - annehmen ist
     * dann besser als grundlos verwerfen.
     */
    function canvasIstBemalt(canvas) {
        if (!canvas || !canvas.width || !canvas.height) {
            return false;
        }
        var daten;
        try {
            var ctx = canvas.getContext('2d');
            if (!ctx) { return true; }
            daten = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        } catch (e) {
            return true;
        }
        // Bei sehr grossen Leinwaenden mit Schrittweite abtasten, damit die
        // Pruefung nicht selbst bremst. Bis rund 1 Mio. Pixel bleibt sie exakt.
        var schritt = Math.max(1, Math.round(Math.sqrt((canvas.width * canvas.height) / 1000000)));
        for (var y = 0; y < canvas.height; y += schritt) {
            var basis = y * canvas.width * 4;
            for (var x = 0; x < canvas.width; x += schritt) {
                if (daten[basis + x * 4 + 3] > 10) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Liefert Capture-Ziel und Masse einer Formel.
     *
     * N2: Eine abgesetzte Formel ist ein <span class="cbd-latex-formula
     * cbd-latex-display">. Als Inline-Element hat der Span keine eigene
     * Breite - getBoundingClientRect() meldet dort Breite 0, obwohl das
     * gerenderte KaTeX-Kind darin sichtbar ist. Die Groessenbremse unten hat
     * diese Formeln deshalb still uebersprungen; im PDF blieb der bei
     * Bruechen mathematisch falsche Textrueckfall stehen (Zaehler und Nenner
     * vertauscht). Gemessen: Bloecke mit ausschliesslich abgesetzten Formeln
     * meldeten "Captured 0/5".
     *
     * Ist das Element selbst zu klein, wird das erste gerenderte Kind mit
     * echtem Kasten zum Capture-Ziel. Findet sich keines, gilt die Formel wie
     * bisher als unsichtbar und wird uebersprungen (Fallback-Text greift).
     */
    function messeFormel(el) {
        var rect = el.getBoundingClientRect();
        if (rect.width >= 2 && rect.height >= 2) {
            return { ziel: el, rect: rect };
        }
        var kandidaten = el.querySelectorAll('.katex-display, .katex, .cbd-latex-content');
        for (var i = 0; i < kandidaten.length; i++) {
            var r = kandidaten[i].getBoundingClientRect();
            if (r.width >= 2 && r.height >= 2) {
                return { ziel: kandidaten[i], rect: r };
            }
        }
        return null;
    }

    /**
     * N2 (U3): Formelfarbe fuer den Capture auf den Hellmodus-Wertesatz
     * zwingen. Ein PDF bildet den Darkmode grundsaetzlich nicht ab (siehe
     * collectCSSVariables() weiter unten) - ohne diesen Eingriff kaemen im
     * Darkmode weisse Glyphen auf weissem Papier heraus, weil die
     * Darkmode-Neutralisierung erst NACH allen Captures laeuft.
     *
     * Der Eingriff passiert ausschliesslich im KLON, den html2canvas vor dem
     * Malen anlegt, NICHT an der laufenden Seite. Ein Umschalten von
     * data-theme am echten <html> waere sichtbares Flackern - und genau das
     * ist auf diesem Branch gerade behoben worden (N1).
     */
    function neutralisiereDarkmodeImKlon(klonDokument) {
        try {
            klonDokument.documentElement.removeAttribute('data-theme');
            var stil = klonDokument.createElement('style');
            stil.textContent =
                '.cbd-latex-formula, .cbd-latex-formula * {' +
                'color: var(--color-text-primary, #333333) !important;' +
                '-webkit-text-fill-color: var(--color-text-primary, #333333) !important;' +
                '}';
            (klonDokument.head || klonDokument.documentElement).appendChild(stil);
        } catch (e) {
            // Ohne Neutralisierung wird trotzdem erfasst - bisheriges Verhalten.
        }
    }

    /**
     * Rendert KaTeX-Formeln als PNG-Bilder (html2canvas, scale 2 für Schärfe).
     * Liefert [{id, image, width, height, isDisplay}] – width/height in CSS-px,
     * damit der Server das Bild in Originalgröße einsetzen kann.
     * Fehlgeschlagene Captures werden ausgelassen (Server lässt dann den
     * Fallback-Text im Platzhalter stehen).
     */
    function captureFormulaImages(formulaElements, callback) {
        if (!formulaElements.length || typeof html2canvas === 'undefined') {
            callback([]);
            return;
        }

        var formulas = [];
        var index = 0;

        function nextFormula() {
            if (index >= formulaElements.length) {
                window.cbdDebug && console.log('[CBD PDF] Captured ' + formulas.length + '/' + formulaElements.length +
                    ' formula images (' + (foRenderingLiefertLeerbild ? 'Standard-Painter' : 'foreignObject') + ')');
                callback(formulas);
                return;
            }

            var item = formulaElements[index];
            var mass = messeFormel(item.element);

            // Unsichtbare/leere Formeln überspringen (Fallback-Text greift)
            if (!mass) {
                index++;
                nextFormula();
                return;
            }
            var el = mass.ziel;
            var rect = mass.rect;

            // foreignObjectRendering rendert KaTeX' komplexe vertikale Stapelung
            // (Brüche, \xrightarrow-Beschriftungen, Wurzeln) über den nativen
            // SVG-foreignObject des Browsers KORREKT – der Standard-Canvas-Painter
            // von html2canvas kollabiert diese Stapelung (Label über Pfeil rutscht
            // auf den Pfeil). Bei Fehler/leer Fallback auf den Standard-Painter.
            function attemptCapture(useForeignObject, onDone) {
                html2canvas(el, {
                    scale: 2,
                    backgroundColor: null,
                    logging: false,
                    useCORS: true,
                    foreignObjectRendering: useForeignObject,
                    onclone: neutralisiereDarkmodeImKlon
                }).then(function (canvas) {
                    onDone(canvas);
                }).catch(function (err) {
                    console.warn('[CBD PDF] Formula capture (' + (useForeignObject ? 'FO' : 'std') + ') failed for', item.id, err);
                    onDone(null);
                });
            }

            function store(canvas) {
                try {
                    formulas.push({
                        id: item.id,
                        image: canvas.toDataURL('image/png'),
                        width: Math.round(rect.width),
                        height: Math.round(rect.height),
                        isDisplay: item.isDisplay ? 1 : 0
                    });
                } catch (e) {
                    console.warn('[CBD PDF] Formula toDataURL failed for', item.id, e);
                }
                index++;
                setTimeout(nextFormula, 10);
            }

            // 2. Versuch: Standard-Painter
            function standardPainter() {
                attemptCapture(false, function (canvas2) {
                    if (canvasIstBemalt(canvas2)) {
                        store(canvas2);
                    } else {
                        // Beide fehlgeschlagen – Fallback-Text im Platzhalter bleibt
                        index++;
                        setTimeout(nextFormula, 10);
                    }
                });
            }

            // Ist foreignObjectRendering in dieser Sitzung bereits als untauglich
            // erkannt, gar nicht erst versuchen (N2).
            if (foRenderingLiefertLeerbild) {
                standardPainter();
                return;
            }

            // 1. Versuch: foreignObjectRendering (beste KaTeX-Treue).
            //
            // N2 - der Kern der Reparatur: Geprueft wird jetzt der INHALT der
            // Leinwand, nicht nur ihre Masse. Eine korrekt dimensionierte, aber
            // vollstaendig transparente Leinwand bestand die alte Pruefung
            // (width > 0 && height > 0) und wurde angenommen - der
            // funktionierende Standard-Painter war damit unerreichbar. Der Server
            // bettete das leere PNG korrekt ein, im PDF blieb an der Formelstelle
            // eine Luecke. Belegt in docs/diagnose-pdf-formeln.md: 742 Byte fuer
            // ein 276x48-PNG mit 0 opaken Pixeln.
            attemptCapture(true, function (canvas) {
                if (canvasIstBemalt(canvas)) {
                    store(canvas);
                    return;
                }
                if (!foRenderingLiefertLeerbild) {
                    foRenderingLiefertLeerbild = true;
                    // Bewusst NICHT hinter window.cbdDebug: Diese Zeile ist der
                    // Beleg dafuer, dass der reparierte Codestand laeuft.
                    console.warn('[CBD PDF] foreignObjectRendering liefert eine leere Leinwand - ' +
                        'ab jetzt Standard-Painter fuer alle Formeln dieser Sitzung.');
                }
                standardPainter();
            });
        }

        nextFormula();
    }

    // =========================================================================
    // LaTeX to Readable Text Conversion
    // =========================================================================

    /**
     * Convert raw LaTeX string to human-readable Unicode text.
     * Used as fallback when KaTeX rendered output is not available.
     */
    function latexToReadable(latex) {
        if (!latex) return '';
        var s = latex;

        // Greek letters
        var greekMap = {
            '\\alpha': '\u03B1', '\\beta': '\u03B2', '\\gamma': '\u03B3',
            '\\delta': '\u03B4', '\\epsilon': '\u03B5', '\\zeta': '\u03B6',
            '\\eta': '\u03B7', '\\theta': '\u03B8', '\\iota': '\u03B9',
            '\\kappa': '\u03BA', '\\lambda': '\u03BB', '\\mu': '\u03BC',
            '\\nu': '\u03BD', '\\xi': '\u03BE', '\\pi': '\u03C0',
            '\\rho': '\u03C1', '\\sigma': '\u03C3', '\\tau': '\u03C4',
            '\\upsilon': '\u03C5', '\\phi': '\u03C6', '\\chi': '\u03C7',
            '\\psi': '\u03C8', '\\omega': '\u03C9',
            '\\Delta': '\u0394', '\\Sigma': '\u03A3', '\\Omega': '\u03A9',
            '\\Pi': '\u03A0', '\\Lambda': '\u039B', '\\Gamma': '\u0393'
        };
        for (var cmd in greekMap) {
            s = s.split(cmd).join(greekMap[cmd]);
        }

        // Math symbols
        s = s.replace(/\\approx/g, '\u2248');    // ≈
        s = s.replace(/\\neq/g, '\u2260');       // ≠
        s = s.replace(/\\leq/g, '\u2264');       // ≤
        s = s.replace(/\\geq/g, '\u2265');       // ≥
        s = s.replace(/\\pm/g, '\u00B1');        // ±
        s = s.replace(/\\times/g, '\u00D7');     // ×
        s = s.replace(/\\cdot/g, '\u00B7');      // ·
        s = s.replace(/\\rightarrow/g, '\u2192'); // →
        s = s.replace(/\\leftarrow/g, '\u2190');  // ←
        s = s.replace(/\\infty/g, '\u221E');     // ∞

        // \text{...} → content
        s = s.replace(/\\text\{([^}]*)\}/g, '$1');
        s = s.replace(/\\textbf\{([^}]*)\}/g, '$1');
        s = s.replace(/\\mathrm\{([^}]*)\}/g, '$1');

        // \frac{a}{b} → (a)/(b)
        s = s.replace(/\\frac\{([^}]*)\}\{([^}]*)\}/g, '($1)/($2)');

        // \sqrt{x} → √(x)
        s = s.replace(/\\sqrt\{([^}]*)\}/g, '\u221A($1)');

        // Subscripts: _{...} → content (just inline)
        s = s.replace(/\_\{([^}]*)\}/g, '$1');
        s = s.replace(/\_([a-zA-Z0-9])/g, '$1');

        // Superscripts: ^{...} → content
        s = s.replace(/\^\{([^}]*)\}/g, '$1');
        s = s.replace(/\^([a-zA-Z0-9+\-])/g, '$1');

        // Remove remaining LaTeX commands
        s = s.replace(/\\[a-zA-Z]+/g, '');

        // Clean up braces and extra spaces
        s = s.replace(/[{}]/g, '');
        s = s.replace(/\s+/g, ' ').trim();

        return s;
    }

    // =========================================================================
    // Drawing Injection from localStorage
    // =========================================================================

    /**
     * Read drawing data from localStorage and inject as <img> tags into clone.
     * Works for both local drawings (Eigene Notizen) and multi-page drawings.
     *
     * localStorage keys:
     *   cbd-board-{stableId}        → Page 0 PNG data URL
     *   cbd-board-{stableId}:pN     → Page N PNG data URL
     *   cbd-board-pagecount-{stableId} → Total page count
     *   cbd-board-{stableId}-bgcolor   → Board background color
     *
     * @param {jQuery} $original - Original block (to read data-stable-id)
     * @param {jQuery} $clone    - Cloned block (to inject images into)
     */
    function injectDrawingsFromStorage($original, $clone) {
        // Find all containers with data-stable-id (including the block itself)
        var containers = [];

        // Check the block itself
        var blockStableId = $original.attr('data-stable-id');
        if (blockStableId) {
            containers.push({
                stableId: blockStableId,
                $cloneTarget: $clone
            });
        }

        // Check nested containers (skip duplicates with same stableId as block)
        $original.find('[data-stable-id]').each(function () {
            var stableId = $(this).attr('data-stable-id');
            if (stableId && stableId !== blockStableId) {
                // Find corresponding element in clone
                var $cloneEl = $clone.find('[data-stable-id="' + stableId + '"]');
                if ($cloneEl.length > 0) {
                    containers.push({
                        stableId: stableId,
                        $cloneTarget: $cloneEl
                    });
                }
            }
        });

        var totalInjected = 0;

        for (var c = 0; c < containers.length; c++) {
            var stableId = containers[c].stableId;
            var $target = containers[c].$cloneTarget;

            // Read page count
            var pageCountStr = null;
            try { pageCountStr = localStorage.getItem('cbd-board-pagecount-' + stableId); } catch (e) {}
            var totalPages = pageCountStr ? Math.max(1, parseInt(pageCountStr, 10)) : 1;

            // Collect all page images
            var pages = [];
            for (var p = 0; p < totalPages; p++) {
                var key = p === 0
                    ? 'cbd-board-' + stableId
                    : 'cbd-board-' + stableId + ':p' + p;
                var dataUrl = null;
                try { dataUrl = localStorage.getItem(key); } catch (e) {}

                if (dataUrl && dataUrl.indexOf('data:image/') === 0) {
                    // Read optional background color
                    var bgColor = null;
                    try { bgColor = localStorage.getItem(key + '-bgcolor'); } catch (e) {}

                    // Compress drawing for PDF (PNG → smaller JPEG)
                    var compressed = recompressBase64(dataUrl, 0.75, 1200, 'image/png');
                    pages.push({
                        dataUrl: compressed || dataUrl,
                        bgColor: bgColor,
                        pageIndex: p
                    });
                }
            }

            if (pages.length === 0) continue;

            // Build HTML for drawing section
            var drawingHtml = '<div class="cbd-pdf-drawing-section" style="' +
                'margin: 12px 0; padding: 8px; page-break-inside: avoid;">';

            drawingHtml += '<div style="font-size: 11px; color: #666; margin-bottom: 6px; ' +
                'font-style: italic;">Eigene Notiz' +
                (pages.length > 1 ? ' (' + pages.length + ' Seiten)' : '') +
                '</div>';

            for (var j = 0; j < pages.length; j++) {
                var page = pages[j];
                var bgStyle = page.bgColor
                    ? 'background-color:' + page.bgColor + ';'
                    : '';

                drawingHtml += '<div style="margin: 4px 0; text-align: center; ' +
                    bgStyle + ' page-break-inside: avoid;">';
                drawingHtml += '<img src="' + page.dataUrl + '" style="' +
                    'max-width: 100%; height: auto; display: block; margin: 0 auto;" ' +
                    'alt="Zeichnung Seite ' + (page.pageIndex + 1) + '" />';
                drawingHtml += '</div>';
            }

            drawingHtml += '</div>';

            // Inject after the content area of this container
            var $content = $target.find('.cbd-container-content').first();
            if ($content.length > 0) {
                $content.append(drawingHtml);
            } else {
                $target.append(drawingHtml);
            }

            totalInjected += pages.length;
        }

        if (totalInjected > 0) {
            window.cbdDebug && console.log('[CBD PDF] Injected', totalInjected, 'drawing page(s) from localStorage');
        }
    }

    // =========================================================================
    // AP-2.3: Server-Tafelbilder (serverseitig gespeicherte Klassenzeichnungen)
    // =========================================================================

    /**
     * Liest fuer jeden Container mit `data-stable-id` den von board-mode.js
     * (AP-2.2) gepflegten Begleitschluessel `cbd-board-<stableId>-classid` aus
     * localStorage und laedt fuer JEDE dort gefundene class_id GENAU EINMAL
     * (nicht je Container) alle Tafelbilder der aktuellen Seite ueber den
     * Bulk-Endpoint `cbd_get_page_drawings` (AP-2.1) nach. Das Ergebnis wird
     * anschliessend auf alle betroffenen Container verteilt (der Server
     * liefert `container_id` je Zeichnung mit, siehe applyServerDrawings()).
     *
     * @param {jQuery}   $original Original-Block (zum Lesen von data-stable-id)
     * @param {jQuery}   $clone    Geklonter Block (zum Einfuegen der Bilder)
     * @param {Function} callback  Aufgerufen, sobald ALLE Anfragen fertig sind
     *                             (Erfolg oder Fehler) - immer ohne Argument.
     */
    function injectServerDrawings($original, $clone, callback) {
        // Fehlt cbdPDFData oder dessen ajaxurl (z. B. weil eine aeltere
        // Fassung von class-cbd-classroom.php ohne pageId/classroomNonce
        // ausliefert), gibt es nichts zu laden - unschaedlicher Rueckfall auf
        // "keine Server-Tafelbilder", der Rest des Exports laeuft normal weiter.
        if (typeof cbdPDFData === 'undefined' || !cbdPDFData.ajaxurl) {
            callback();
            return;
        }

        // Dieselbe Sammel-Logik wie injectDrawingsFromStorage(): Block selbst
        // plus alle verschachtelten Container mit data-stable-id.
        var containers = [];
        var blockStableId = $original.attr('data-stable-id');
        if (blockStableId) {
            containers.push({ stableId: blockStableId, $cloneTarget: $clone });
        }
        $original.find('[data-stable-id]').each(function () {
            var stableId = $(this).attr('data-stable-id');
            if (stableId && stableId !== blockStableId) {
                var $cloneEl = $clone.find('[data-stable-id="' + stableId + '"]');
                if ($cloneEl.length > 0) {
                    containers.push({ stableId: stableId, $cloneTarget: $cloneEl });
                }
            }
        });

        // Begleitschluessel aus AP-2.2 lesen, nach class_id gruppieren - so
        // entsteht GENAU EIN AJAX-Aufruf je class_id, nicht einer je Container
        // (Akzeptanzkriterium AP-2.3).
        var classGroups = {};
        for (var c = 0; c < containers.length; c++) {
            var stableId = containers[c].stableId;
            var classId = null;
            try {
                classId = localStorage.getItem('cbd-board-' + stableId + '-classid');
            } catch (e) { /* localStorage nicht verfuegbar - Container ueberspringen */ }

            if (!classId) {
                continue; // Kein Server-Tafelbild fuer diesen Container bekannt
            }
            if (!classGroups[classId]) {
                classGroups[classId] = [];
            }
            classGroups[classId].push(containers[c]);
        }

        var classIds = Object.keys(classGroups);
        if (classIds.length === 0) {
            callback();
            return;
        }

        // pageId/classroomNonce kommen bevorzugt aus cbdPDFData (ergaenzt in
        // class-cbd-classroom.php fuer Seiten mit dem [cbd_classroom]-
        // Shortcode). Auf GEWOEHNLICHEN Seiten (der weit ueberwiegende Fall
        // fuer den PDF-Export) lokalisiert stattdessen
        // class-cbd-block-registration.php dieselben Werte unter dem eigenen
        // Namen window.cbdClassroomData (dort bereits vorhanden, unveraendert
        // von diesem AP) - als Rueckfall gelesen, damit Server-Tafelbilder
        // nicht nur auf Klassenraum-Shortcode-Seiten funktionieren, ohne eine
        // dritte Datei aendern zu muessen.
        var classroomData = window.cbdClassroomData || {};
        var pageId = cbdPDFData.pageId || classroomData.pageId || 0;
        var nonce = cbdPDFData.classroomNonce || classroomData.nonce || '';
        var pending = classIds.length;

        function requestDone() {
            pending--;
            if (pending <= 0) {
                callback();
            }
        }

        classIds.forEach(function (classId) {
            // Bereits in einem FRUEHEREN processOneBlock()-Durchlauf desselben
            // Export-Laufs abgefragt (anderer Top-Level-Container, gleiche
            // class_id)? Dann Cache-Treffer verwenden statt eines weiteren
            // AJAX-Aufrufs - siehe Kommentar bei serverDrawingsCache oben.
            if (serverDrawingsCache.hasOwnProperty(classId)) {
                if (serverDrawingsCache[classId].length > 0) {
                    applyServerDrawings(classGroups[classId], serverDrawingsCache[classId]);
                }
                requestDone();
                return;
            }

            $.ajax({
                url: cbdPDFData.ajaxurl,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: 'cbd_get_page_drawings',
                    nonce: nonce,
                    class_id: classId,
                    page_id: pageId
                },
                success: function (response) {
                    // AP-2.1-Vertrag: {success:true, data:{drawings:[...]}} bei
                    // Erfolg, {success:false, data:{message:"..."}} bei
                    // Capability-/Zugriffsfehlern (z. B. fremde class_id) - in
                    // BEIDEN Faellen liefert der Server gueltiges JSON.
                    var drawings = (response && response.success && response.data && response.data.drawings)
                        ? response.data.drawings
                        : [];
                    // Auch ein leeres Ergebnis (Fehler, keine Zeichnungen) wird
                    // gecacht - sonst wuerde eine class_id ohne Zugriff/Daten
                    // bei JEDEM weiteren Container mit derselben class_id im
                    // selben Export-Lauf erneut angefragt.
                    serverDrawingsCache[classId] = drawings;
                    if (drawings.length > 0) {
                        applyServerDrawings(classGroups[classId], drawings);
                    }
                    requestDone();
                },
                error: function () {
                    // WICHTIG (Uebergabenotiz AP-2.1): Bei ungueltigem Nonce
                    // antwortet check_ajax_referer() mit HTTP 403 und LEEREM
                    // Rumpf (kein JSON) - das landet hier im error-Zweig, NICHT
                    // im success-Zweig mit response.success === false. Deshalb
                    // ein eigener, separater Zweig statt nur response.success
                    // zu pruefen. Beide Faelle (error und success:false) enden
                    // unschaedlich: keine Server-Tafelbilder fuer diese
                    // class_id, der PDF-Export laeuft mit dem Rest normal
                    // weiter (kein Abbruch des gesamten Exports).
                    serverDrawingsCache[classId] = [];
                    requestDone();
                }
            });
        });
    }

    /**
     * Fuegt die vom Bulk-Endpoint gelieferten Zeichnungen in die passenden
     * Klon-Container ein. `container_id` traegt bei mehrseitigen Tafelbildern
     * das Suffix `:pN` (siehe class-cbd-classroom.php, zerlege_container_id())
     * - der Teil vor dem Doppelpunkt ist die stableId, ueber die hier auf den
     * richtigen Container gematcht wird. Mehrere Seiten desselben Containers
     * werden nach Seitenzahl sortiert und alle eingefuegt (analog zum
     * bestehenden Mehrseiten-Verhalten der lokalen Notizen).
     *
     * @param {Array} containerGroup [{stableId, $cloneTarget}, ...] - alle
     *                Container dieser class_id (aus injectServerDrawings()).
     * @param {Array} drawings [{container_id, drawing_data}, ...] - Antwort
     *                des Bulk-Endpoints cbd_get_page_drawings.
     */
    function applyServerDrawings(containerGroup, drawings) {
        for (var i = 0; i < containerGroup.length; i++) {
            var stableId = containerGroup[i].stableId;
            var $target = containerGroup[i].$cloneTarget;

            var matched = [];
            for (var d = 0; d < drawings.length; d++) {
                var containerId = drawings[d].container_id || '';
                var baseId = containerId.split(':')[0];
                if (baseId === stableId && drawings[d].drawing_data &&
                    drawings[d].drawing_data.indexOf('data:image/') === 0) {
                    var pageMatch = /^:p(\d+)$/.exec(containerId.slice(baseId.length));
                    matched.push({
                        dataUrl: drawings[d].drawing_data,
                        pageIndex: pageMatch ? parseInt(pageMatch[1], 10) : 0
                    });
                }
            }
            if (matched.length === 0) continue;

            matched.sort(function (a, b) { return a.pageIndex - b.pageIndex; });

            // Label "Tafelbild" statt "Eigene Notiz" - Unterscheidung im PDF
            // zwischen lokalen und serverseitigen Zeichnungen (Plan-Vorgabe).
            var drawingHtml = '<div class="cbd-pdf-drawing-section" style="' +
                'margin: 12px 0; padding: 8px; page-break-inside: avoid;">';
            drawingHtml += '<div style="font-size: 11px; color: #666; margin-bottom: 6px; ' +
                'font-style: italic;">Tafelbild' +
                (matched.length > 1 ? ' (' + matched.length + ' Seiten)' : '') +
                '</div>';

            for (var m = 0; m < matched.length; m++) {
                var compressed = recompressBase64(matched[m].dataUrl, 0.75, 1200, 'image/png');
                drawingHtml += '<div style="margin: 4px 0; text-align: center; ' +
                    'page-break-inside: avoid;">';
                drawingHtml += '<img src="' + (compressed || matched[m].dataUrl) + '" style="' +
                    'max-width: 100%; height: auto; display: block; margin: 0 auto;" ' +
                    'alt="Tafelbild Seite ' + (matched[m].pageIndex + 1) + '" />';
                drawingHtml += '</div>';
            }

            drawingHtml += '</div>';

            var $content = $target.find('.cbd-container-content').first();
            if ($content.length > 0) {
                $content.append(drawingHtml);
            } else {
                $target.append(drawingHtml);
            }

            window.cbdDebug && console.log('[CBD PDF] Injected', matched.length, 'server drawing page(s) for', stableId);
        }
    }

    // =========================================================================
    // Formula Extraction
    // =========================================================================

    /**
     * TOTER CODE - wird an keiner Stelle aufgerufen (Stand N2, 2026-09-04).
     *
     * Diese Funktion sammelt gerendertes KaTeX-HTML (renderedHtml) fuer den
     * Serverzweig CBD_PDF_Generator::insert_formula(). Beide Seiten sind tot:
     * Der Weg rastert Formeln seit v3.1.58/59 im Browser zu PNG
     * (captureFormulaImages() weiter oben), und die an den Server gehende
     * Nutzlast enthaelt ausschliesslich id/image/width/height/isDisplay -
     * kein renderedHtml, kein latex.
     *
     * NICHT wiederbeleben: mPDFs CSS-Maschine kann KaTeX-Markup (.vlist,
     * .strut, absolute Positionierung, negative Raender, em-Ketten) nicht
     * setzen, und die KaTeX-Schriften sind dort nicht registriert.
     * Vollstaendige Begruendung samt Messwerten: docs/diagnose-pdf-formeln.md,
     * Abschnitte 3 und 8 (Variante B, ausdruecklich nicht empfohlen).
     *
     * Extract KaTeX formula data from block
     * Captures the rendered HTML so mPDF can display it
     */
    function extractFormulas($block) {
        var formulas = [];

        $block.find('.cbd-latex-formula').each(function (index) {
            var $formula = $(this);
            var latex = $formula.attr('data-latex') || '';
            var id = $formula.attr('id') || 'formula-' + index + '-' + Date.now();

            // Ensure element has an ID for server-side replacement
            if (!$formula.attr('id')) {
                $formula.attr('id', id);
            }

            // Get the rendered KaTeX HTML (already rendered in the browser)
            var $content = $formula.find('.cbd-latex-content').first();
            var renderedHtml = '';

            if ($content.length > 0 && $content.find('.katex').length > 0) {
                // KaTeX already rendered - grab the HTML
                // Clone to remove MathML annotations (cause doubled text in PDF)
                var $contentClone = $content.clone();
                $contentClone.find('.katex-mathml').remove();
                renderedHtml = $contentClone.html();
            } else if (latex && typeof katex !== 'undefined') {
                // Render now with KaTeX
                try {
                    var isDisplay = $formula.hasClass('cbd-latex-display');
                    var rawHtml = katex.renderToString(latex, {
                        displayMode: isDisplay,
                        throwOnError: false,
                        output: 'html'
                    });
                    // Remove MathML annotations to prevent doubled text
                    var $tmp = $('<div>').html(rawHtml);
                    $tmp.find('.katex-mathml').remove();
                    renderedHtml = $tmp.html();
                } catch (e) {
                    renderedHtml = '<span style="color:red;">Formula Error</span>';
                }
            }

            if (latex || renderedHtml) {
                formulas.push({
                    id: id,
                    latex: latex,
                    renderedHtml: renderedHtml
                });
            }
        });

        return formulas;
    }

    // =========================================================================
    // Interactive Element Screenshots
    // =========================================================================

    /**
     * Find interactive elements from "Eigene WP Blocks" that need screenshots.
     * Tags each element with a capture method: 'webgl', 'canvas', or 'html2canvas'.
     */
    function findInteractiveElements($block) {
        var elements = [];

        // Modular blocks (educational interactive blocks)
        var selectors = [
            '[class*="wp-block-modular-blocks-"]',
            '.modular-block-drag-and-drop',
            '.modular-block-multiple-choice',
            '.modular-block-drag-the-words',
            '.modular-block-statement-connector',
            '.modular-block-summary-block',
            '.modular-block-image-comparison',
            '.modular-block-point-of-interest',
            '.modular-block-molecule-viewer',
            '.modular-block-chart-block'
        ];

        $block.find(selectors.join(', ')).each(function (index) {
            var $el = $(this);

            // Skip if already inside another interactive element (avoid nested screenshots)
            if ($el.parents(selectors.join(', ')).length > 0) {
                return;
            }

            // Ensure it has an ID
            var id = this.id || 'interactive-' + index + '-' + Date.now();
            if (!this.id) {
                this.id = id;
            }

            // Determine best capture method
            var method = 'html2canvas';
            var webglCanvas = this.querySelector('canvas');
            if (webglCanvas) {
                try {
                    var gl = webglCanvas.getContext('webgl') || webglCanvas.getContext('webgl2');
                    if (gl) {
                        method = 'webgl';
                    } else {
                        method = 'canvas';
                    }
                } catch (e) {
                    // Canvas exists but context check failed - try direct export
                    method = 'canvas';
                }
            }

            elements.push({ element: this, id: id, method: method, canvas: webglCanvas });
        });

        return elements;
    }

    // Max screenshot dimensions to keep payload small
    var MAX_SCREENSHOT_WIDTH = 1200;
    var MAX_SCREENSHOT_HEIGHT = 900;

    /**
     * Downscale a canvas if it exceeds max dimensions, then export as JPEG.
     * Returns base64 string.
     */
    function canvasToCompressedBase64(sourceCanvas, jpegQuality) {
        var w = sourceCanvas.width;
        var h = sourceCanvas.height;
        var ratio = Math.min(1, MAX_SCREENSHOT_WIDTH / w, MAX_SCREENSHOT_HEIGHT / h);

        if (ratio < 1) {
            // Downscale via offscreen canvas
            var nw = Math.round(w * ratio);
            var nh = Math.round(h * ratio);
            var tmp = document.createElement('canvas');
            tmp.width = nw;
            tmp.height = nh;
            var ctx = tmp.getContext('2d');
            ctx.drawImage(sourceCanvas, 0, 0, nw, nh);
            return tmp.toDataURL('image/jpeg', jpegQuality);
        }

        return sourceCanvas.toDataURL('image/jpeg', jpegQuality);
    }

    /**
     * Take screenshots of interactive elements.
     * Uses direct canvas export for WebGL/canvas elements (fast & reliable),
     * falls back to html2canvas for DOM-only elements.
     * Sequential processing to avoid memory issues on iOS.
     */
    function screenshotInteractiveElements(elements, quality, callback) {
        var screenshots = [];
        var index = 0;
        var jpegQuality = 0.75; // Slightly lower for smaller payload

        // iOS canvas pixel limit
        var maxPixels = isIOS ? 16000000 : 64000000;

        function nextScreenshot() {
            if (index >= elements.length) {
                callback(screenshots);
                return;
            }

            var item = elements[index];
            var el = item.element;

            // --- WebGL / Canvas: direct export (fast, no html2canvas needed) ---
            if ((item.method === 'webgl' || item.method === 'canvas') && item.canvas) {
                try {
                    // For WebGL: force a render before capture if viewer has a render method
                    if (item.method === 'webgl') {
                        var viewer = item.canvas._symmetryViewer || item.canvas._3dmolViewer ||
                            (window.$3Dmol && item.canvas.__3dmolViewer);
                        if (viewer && typeof viewer.render === 'function') {
                            viewer.render();
                        }
                        // Ensure preserveDrawingBuffer by reading pixels immediately
                        var gl = item.canvas.getContext('webgl', { preserveDrawingBuffer: true }) ||
                                 item.canvas.getContext('webgl2', { preserveDrawingBuffer: true });
                    }

                    var base64 = canvasToCompressedBase64(item.canvas, jpegQuality);

                    // Check if canvas export produced valid data (not blank)
                    if (base64 && base64.length > 1000) {
                        window.cbdDebug && console.log('[CBD PDF] Direct canvas export for', item.id, '(' + Math.round(base64.length / 1024) + ' KB)');
                        screenshots.push({ id: item.id, base64: base64 });
                        index++;
                        setTimeout(nextScreenshot, 20);
                        return;
                    }
                    // Blank canvas - fall through to html2canvas
                    console.warn('[CBD PDF] Canvas export blank for', item.id, '- trying html2canvas');
                } catch (err) {
                    console.warn('[CBD PDF] Direct canvas export failed for', item.id, err);
                }
            }

            // --- html2canvas fallback for DOM elements ---
            if (typeof html2canvas === 'undefined') {
                console.warn('[CBD PDF] html2canvas not available, skipping', item.id);
                index++;
                setTimeout(nextScreenshot, 20);
                return;
            }

            var scale = quality;
            var totalPixels = el.offsetWidth * el.offsetHeight * scale * scale;
            if (totalPixels > maxPixels) {
                scale = Math.max(1, Math.sqrt(maxPixels / (el.offsetWidth * el.offsetHeight)));
            }

            html2canvas(el, {
                useCORS: true,
                allowTaint: false,
                scale: scale,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(function (canvas) {
                var base64 = canvasToCompressedBase64(canvas, jpegQuality);
                window.cbdDebug && console.log('[CBD PDF] html2canvas for', item.id, '(' + Math.round(base64.length / 1024) + ' KB)');

                screenshots.push({ id: item.id, base64: base64 });
                index++;
                setTimeout(nextScreenshot, 50);
            }).catch(function (err) {
                console.warn('[CBD PDF] Screenshot failed for', item.id, err);
                index++;
                setTimeout(nextScreenshot, 50);
            });
        }

        nextScreenshot();
    }

    // =========================================================================
    // Image Recompression (synchronous via existing canvas)
    // =========================================================================

    /**
     * Synchronously recompress a base64 image to lower quality/smaller size.
     * Returns new base64 string or null on failure.
     *
     * AP-1.2 (PLAN-PDF-Export-und-Tafelmodus-Fixes.md): outputFormat
     * defaults to 'image/jpeg' (bisheriges Verhalten, unveraendert fuer
     * Screenshots interaktiver Elemente - die haben keine Transparenz).
     * Fuer Tafelmodus-Zeichnungen (drawingCanvas.toDataURL('image/png') in
     * board-mode.js liefert NUR die Zeichenebene mit transparentem
     * Hintergrund, siehe injectDrawingsFromStorage()/applyServerDrawings())
     * MUSS 'image/png' uebergeben werden: JPEG kennt keine Transparenz,
     * ctx.drawImage() auf eine neue Canvas komponiert transparente Pixel
     * beim Export als JPEG automatisch auf Schwarz - aus duennen schwarzen
     * Strichen auf transparentem Grund wurde dadurch ein durchgehend
     * schwarzes Rechteck (im Live-Test nach dem data:-Praefix-Fix
     * gefunden).
     */
    function recompressBase64(base64, quality, maxWidth, outputFormat) {
        outputFormat = outputFormat || 'image/jpeg';
        try {
            var img = document.createElement('img');
            img.src = base64;
            // img should load synchronously from data URI
            if (!img.complete || img.naturalWidth === 0) return null;

            var w = img.naturalWidth;
            var h = img.naturalHeight;
            var ratio = Math.min(1, maxWidth / w);
            var nw = Math.round(w * ratio);
            var nh = Math.round(h * ratio);

            var canvas = document.createElement('canvas');
            canvas.width = nw;
            canvas.height = nh;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, nw, nh);
            return canvas.toDataURL(outputFormat, quality);
        } catch (e) {
            return null;
        }
    }

    // =========================================================================
    // Server Communication
    // =========================================================================

    /**
     * Send structured block data to server for PDF generation
     * First runs a diagnosis check, then sends the actual PDF request
     */
    function sendToServer(blocksData, mode, $overlay) {
        // Step 1: Run diagnosis via REST API (bypasses admin-ajax.php)
        window.cbdDebug && console.log('[CBD PDF] Running server diagnosis via REST API...');

        var diagnoseUrl = cbdPDFData.resturl ? cbdPDFData.resturl + 'pdf-diagnose' : null;

        if (!diagnoseUrl) {
            console.warn('[CBD PDF] No REST URL, trying PDF directly');
            sendPDFRequest(blocksData, mode, $overlay);
            return;
        }

        $.ajax({
            url: diagnoseUrl,
            type: 'GET',
            timeout: 15000,
            success: function (info) {
                window.cbdDebug && console.log('[CBD PDF] Server info:', info);

                // Check for missing extensions
                var problems = [];
                if (!info.ext_mbstring) problems.push('PHP-Erweiterung "mbstring" fehlt');
                if (!info.ext_gd) problems.push('PHP-Erweiterung "gd" fehlt');
                if (!info.mpdf_available && !info.tcpdf_available) {
                    problems.push('Keine PDF-Bibliothek verfügbar (mPDF: ' + (info.mpdf_error || 'nicht geladen') + ')');
                }
                if (!info.temp_dir_writable) problems.push('Temp-Verzeichnis nicht beschreibbar');

                if (problems.length > 0) {
                    $overlay.remove();
                    handleError('Server-Voraussetzungen fehlen:\n- ' + problems.join('\n- '));
                    return;
                }

                // All checks passed - proceed with PDF generation
                sendPDFRequest(blocksData, mode, $overlay);
            },
            error: function (xhr) {
                console.warn('[CBD PDF] REST diagnosis failed:', xhr.status, xhr.responseText);
                // Try PDF generation anyway
                sendPDFRequest(blocksData, mode, $overlay);
            }
        });
    }

    /**
     * Actually send the PDF generation request
     */
    function sendPDFRequest(blocksData, mode, $overlay) {
        // Collect CSS variable values from the current page
        var cssVariables = collectCSSVariables();

        // Build filename
        var pageTitle = document.title.replace(/[^a-zA-Z0-9äöüÄÖÜß\s-]/g, '').trim();
        var filename = (pageTitle || 'container-blocks') + '-' + new Date().toISOString().slice(0, 10) + '.pdf';

        // Calculate payload size and warn if too large
        var payload = JSON.stringify(blocksData);
        var payloadKB = Math.round(payload.length / 1024);
        window.cbdDebug && console.log('[CBD PDF] Sending', blocksData.length, 'blocks to server, payload:', payloadKB, 'KB');

        // If payload > 6MB, try to reduce screenshot quality
        if (payload.length > 6 * 1024 * 1024) {
            console.warn('[CBD PDF] Payload too large (' + payloadKB + ' KB), recompressing screenshots...');
            for (var i = 0; i < blocksData.length; i++) {
                var block = blocksData[i];
                if (block.screenshots && block.screenshots.length > 0) {
                    for (var j = 0; j < block.screenshots.length; j++) {
                        var ss = block.screenshots[j];
                        if (ss.base64 && ss.base64.length > 200000) {
                            // Re-encode at lower quality via temp canvas
                            try {
                                var img = new Image();
                                var recompressed = recompressBase64(ss.base64, 0.5, 800);
                                if (recompressed) {
                                    ss.base64 = recompressed;
                                }
                            } catch (e) { /* keep original */ }
                        }
                    }
                }
            }
            payload = JSON.stringify(blocksData);
            window.cbdDebug && console.log('[CBD PDF] Recompressed payload:', Math.round(payload.length / 1024), 'KB');
        }

        // Use REST API (bypasses admin-ajax.php which may have issues)
        var pdfUrl = cbdPDFData.resturl ? cbdPDFData.resturl + 'generate-pdf' : null;

        if (pdfUrl) {
            // REST API endpoint
            $.ajax({
                url: pdfUrl,
                type: 'POST',
                timeout: 120000,
                contentType: 'application/json',
                data: JSON.stringify({
                    blocks_json: blocksData,
                    filename: filename,
                    mode: mode,
                    css_variables: cssVariables
                }),
                success: function (response) {
                    $overlay.remove();

                    if (response.success) {
                        window.cbdDebug && console.log('[CBD PDF] PDF generated with engine:', response.engine);
                        downloadPDF(response.url, response.filename || filename);
                    } else {
                        console.error('[CBD PDF] Server error:', response.message);
                        handleError(response.message || 'Unbekannter Fehler');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[CBD PDF] REST error:', status, error, 'Response:', xhr.responseText);

                    // Fallback: Try admin-ajax.php
                    window.cbdDebug && console.log('[CBD PDF] Falling back to admin-ajax.php...');
                    sendPDFViaAjax(payload, filename, mode, cssVariables, $overlay);
                }
            });
        } else {
            // No REST URL, use admin-ajax.php directly
            sendPDFViaAjax(payload, filename, mode, cssVariables, $overlay);
        }
    }

    /**
     * Fallback: Send PDF request via admin-ajax.php
     */
    function sendPDFViaAjax(payload, filename, mode, cssVariables, $overlay) {
        $.ajax({
            url: cbdPDFData.ajaxurl,
            type: 'POST',
            timeout: 120000,
            data: {
                action: 'cbd_generate_pdf',
                nonce: cbdPDFData.nonce,
                blocks_json: payload,
                filename: filename,
                mode: mode,
                css_variables: JSON.stringify(cssVariables),
                is_rest_fallback: '1'
            },
            success: function (response) {
                $overlay.remove();

                if (response.success) {
                    window.cbdDebug && console.log('[CBD PDF] PDF generated via AJAX, engine:', response.data.engine);
                    downloadPDF(response.data.url, response.data.filename || filename);
                } else {
                    var errorMsg = response.data ? response.data.message : 'Unbekannter Fehler';
                    console.error('[CBD PDF] AJAX error:', errorMsg);
                    handleError(errorMsg);
                }
            },
            error: function (xhr, status, error) {
                $overlay.remove();
                console.error('[CBD PDF] AJAX error:', status, error, 'Response:', xhr.responseText);

                if (status === 'timeout') {
                    handleError('Zeitüberschreitung - die Seite hat zu viele Inhalte.');
                } else {
                    var serverMsg = '';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        serverMsg = resp.data ? resp.data.message : (resp.message || '');
                    } catch (e) {
                        serverMsg = xhr.responseText ? xhr.responseText.substring(0, 300) : '';
                    }
                    handleError('Serverfehler: ' + (serverMsg || error || status));
                }
            }
        });
    }

    /**
     * Download PDF file
     */
    function downloadPDF(url, filename) {
        var link = document.createElement('a');
        link.href = url;
        link.download = filename;

        if (isIOS) {
            link.target = '_blank';
        }

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Collect current CSS variable values from the page.
     *
     * AP-1.fix1 (PLAN-PDF-Export-und-Tafelmodus-Fixes.md): PDFs sollen den
     * Darkmode grundsaetzlich NICHT abbilden, unabhaengig davon, ob die Seite
     * gerade im Hell- oder Dunkelmodus angezeigt wird - ein PDF ist ein
     * eigenstaendiges Dokument, kein Theme-Snapshot. AP-1.2 hatte
     * urspruenglich nur dafuer gesorgt, dass der Darkmode-Zustand *korrekt*
     * uebernommen wird (dunkler Text auf dunklem Grund -> heller Text auf
     * dunklem Grund) - das war nicht die gewuenschte Loesung. Fix: das
     * data-theme-Attribut auf <html> wird waehrend des synchronen Auslesens
     * kurzzeitig entfernt (erzwingt den Hellmodus-Wertesatz aus dem
     * bestehenden :root-Block, inkl. etwaiger Customizer-Anpassungen) und
     * direkt danach wiederhergestellt - kein sichtbarer Flackereffekt, da
     * synchron und ohne Repaint zwischen den beiden Zeilen.
     */
    function collectCSSVariables() {
        var htmlEl = document.documentElement;
        var previousTheme = htmlEl.getAttribute('data-theme');
        if (previousTheme === 'dark') {
            htmlEl.removeAttribute('data-theme');
        }

        var root = getComputedStyle(htmlEl);
        var result = {
            specialText: root.getPropertyValue('--color-special-text').trim() || '#71230a',
            uiSurface: root.getPropertyValue('--color-ui-surface').trim() || '#e24614',
            uiSurfaceDark: root.getPropertyValue('--color-ui-surface-dark').trim() || '#c93d12',
            uiSurfaceLight: root.getPropertyValue('--color-ui-surface-light').trim() || '#f5ede9',
            sidebarBorder: root.getPropertyValue('--color-sidebar-border').trim() || '#e0e0e0',
            primaryText: root.getPropertyValue('--color-text-primary').trim() || '#333333',
            background: root.getPropertyValue('--color-background').trim() || '#ffffff',
            lightBackground: root.getPropertyValue('--color-background-light').trim() || '#f8f9fa'
        };

        if (previousTheme === 'dark') {
            htmlEl.setAttribute('data-theme', previousTheme);
        }

        return result;
    }

    // =========================================================================
    // UI: Progress Overlay & Error Handling
    // =========================================================================

    /**
     * Create progress overlay
     */
    function createProgressOverlay(totalBlocks) {
        var $overlay = $('<div id="cbd-pdf-progress" style="' +
            'position:fixed; top:0; left:0; width:100%; height:100%; ' +
            'background:rgba(0,0,0,0.7); z-index:9999999; display:flex; ' +
            'align-items:center; justify-content:center;">' +
            '<div style="background:' + colorBackground + '; padding:30px 40px; border-radius:12px; ' +
            'text-align:center; min-width:300px; box-shadow:0 10px 40px rgba(0,0,0,0.3);">' +
            '<h3 style="margin:0 0 15px 0; font-size:18px;">PDF wird erstellt</h3>' +
            '<div class="cbd-pdf-progress-bar" style="background:' + colorBorderLight + '; border-radius:8px; ' +
            'height:8px; margin:0 0 12px 0; overflow:hidden;">' +
            '<div class="cbd-pdf-progress-fill" style="background:' + colorUiSurface + '; height:100%; ' +
            'width:0%; border-radius:8px; transition:width 0.3s ease;"></div></div>' +
            '<p class="cbd-pdf-progress-text" style="margin:0; color:' + colorTextMuted + '; font-size:14px;">' +
            'Block 1 von ' + totalBlocks + ' wird verarbeitet...</p>' +
            '</div></div>');
        return $overlay;
    }

    /**
     * Update progress display
     */
    function updateProgress($overlay, current, total, message) {
        var pct = Math.round((current / total) * 100);
        $overlay.find('.cbd-pdf-progress-fill').css('width', pct + '%');
        $overlay.find('.cbd-pdf-progress-text').text(message || 'Block ' + current + ' von ' + total + '...');
    }

    /**
     * Handle errors with fallback to window.print()
     */
    function handleError(message) {
        var useprint = confirm(
            'PDF-Erstellung fehlgeschlagen:\n' + message +
            '\n\nMöchten Sie stattdessen die Browser-Druckfunktion verwenden?'
        );
        if (useprint) {
            window.print();
        }
    }

    window.cbdDebug && console.log('[CBD PDF] Server-side PDF export loaded (v3.0, iOS:', isIOS, ')');
})();
