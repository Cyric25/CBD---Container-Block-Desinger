/**
 * Container Block Designer - Floating PDF Export Button with Selection Mode
 * Shows a PDF export button when CBD blocks are present on the page.
 * Clicking the button enters a visual selection mode where the user can
 * click on blocks to select/deselect them for PDF export.
 *
 * @package ContainerBlockDesigner
 * @since 3.2.0
 */

(function ($) {
    'use strict';

    window.cbdDebug && console.log('[CBD PDF] Script loaded');

    $(document).ready(function () {
        var totalContainers = $('.cbd-container');
        window.cbdDebug && console.log('[CBD PDF] Found', totalContainers.length, 'containers');

        if (totalContainers.length === 0 || $('#cbd-pdf-export-fab').length > 0) {
            return;
        }

        // Read theme colors from CSS variables
        var rootStyles = getComputedStyle(document.documentElement);
        var themeColor = rootStyles.getPropertyValue('--color-ui-surface').trim() || '#e24614';
        var themeColorDark = rootStyles.getPropertyValue('--color-ui-surface-dark').trim() || '#c93d12';
        var themeColorLight = rootStyles.getPropertyValue('--color-ui-surface-light').trim() || '#f5ede9';
        // AP-2.9: Erfolgs-/Fehler- sowie Grundfarben ebenfalls ueber
        // getComputedStyle lesen (gleiches Muster wie oben), Fallback ist
        // der bisherige Literalwert.
        var colorSuccess = rootStyles.getPropertyValue('--color-success').trim() || '#2ecc40';
        var colorDanger = rootStyles.getPropertyValue('--color-danger').trim() || '#cc3333';
        var colorBackground = rootStyles.getPropertyValue('--color-background').trim() || '#ffffff';
        var colorTextPrimary = rootStyles.getPropertyValue('--color-text-primary').trim() || '#333333';
        // AP-2.4: Text/Icon-Farbe AUF der orangen Verlaufsflaeche (FAB, Werkzeugleiste,
        // Badges, der bewusst flache ".cbd-pdf-go"-Knopf) darf NICHT an --color-background
        // haengen - die wird im Darkmode dunkel (#121212, siehe Theme/style.css
        // :root[data-theme="dark"]), waehrend die orange Flaeche selbst unveraendert bleibt
        // (AP-1.1). Vorher fiel dieser Text dadurch im Darkmode fast schwarz auf orange
        // (Kontrast bricht in den dunkleren Verlaufsbereichen auf ~3:1, unter WCAG AA).
        // --color-text-on-accent ist genau fuer diesen Fall gedacht: bleibt in beiden Modi
        // #ffffff, weil die Akzentflaeche selbst in beiden Modi derselbe Orangeton bleibt
        // (Theme/style.css, dieselbe Variable, siehe PLAN-Darkmode-Umschaltung.md AP-1.1).
        var colorOnAccent = rootStyles.getPropertyValue('--color-text-on-accent').trim() || '#ffffff';

        // State
        var $containerBlocks = null;
        var selectionActive = false;

        // =====================================================================
        // Floating Action Button
        // =====================================================================

        // Plastischer Look wie die Icon-Kacheln (Rezeptur aus
        // Website/Icons/generate_iconset_local.py bzw. assets/icons/**/*.svg):
        // Verlauf 135 Grad Basisfarbe -> 20 % dunkler, radialer Glanz oben
        // links, Innenkante oben dunkel / unten hell, Schlagschatten in
        // stark abgedunkelter Basisfarbe.
        //
        // color-mix() statt fester Hexwerte, damit die Customizer-Farbe
        // durchschlaegt. themeColor kommt aus --color-ui-surface (siehe oben),
        // ist also selbst schon der eingestellte Wert.
        var plasticDark = 'color-mix(in srgb, ' + themeColor + ' 80%, #000)';
        var plasticShadow = 'color-mix(in srgb, ' + themeColor + ' 45%, #000)';
        var glossLayer = 'radial-gradient(75% 75% at 30% 22%,' +
            'rgba(255,255,255,.35) 0%,rgba(255,255,255,.08) 45%,rgba(255,255,255,0) 100%)';

        function plasticBackground(from, to) {
            return glossLayer + ',linear-gradient(135deg,' + from + ' 0%,' + to + ' 100%)';
        }

        var plasticShadowStack =
            'inset 0 2px 2px -1px color-mix(in srgb, ' + plasticShadow + ' 75%, transparent),' +
            'inset 0 -2px 2px -1px rgba(255,255,255,.5),' +
            'inset -1px 0 1px color-mix(in srgb, ' + plasticShadow + ' 25%, transparent),' +
            '0 4px 10px color-mix(in srgb, ' + plasticShadow + ' 55%, transparent)';

        var $pdfButton = $('<div id="cbd-pdf-export-fab">PDF</div>');
        $pdfButton.css({
            position: 'fixed',
            bottom: '30px',
            right: '30px',
            zIndex: '999999',
            // backgroundImage statt background: die Kurzschreibweise wuerde
            // bei einem ungueltigen Verlauf (Browser ohne color-mix()) auch
            // backgroundColor zuruecksetzen — der Knopf waere dann durchsichtig
            // statt einfarbig. So ueberlebt die Farbe als Rueckfall.
            backgroundColor: themeColor,
            backgroundImage: plasticBackground(themeColor, plasticDark),
            color: colorOnAccent,
            textShadow: '0 1px 2px rgba(0,0,0,.35)',
            borderRadius: '12px',
            padding: '15px',
            cursor: 'pointer',
            boxShadow: plasticShadowStack,
            fontSize: '14px',
            fontWeight: 'bold',
            textAlign: 'center',
            minWidth: '60px',
            transition: 'transform 0.2s ease, box-shadow 0.2s ease'
        });
        $pdfButton.attr('title', 'Container-Bl\u00f6cke als PDF exportieren');
        // Beim Hover den ganzen Verlauf austauschen, nicht nur background:
        // eine einzelne Farbe wuerde die Verlaufsschichten ueberschreiben und
        // den plastischen Look beim Ueberfahren flach machen.
        $pdfButton.hover(
            function () {
                $(this).css({
                    transform: 'scale(1.05)',
                    backgroundColor: themeColorDark,
                    backgroundImage: plasticBackground(themeColorDark, plasticDark)
                });
            },
            function () {
                $(this).css({
                    transform: 'scale(1)',
                    backgroundColor: themeColor,
                    backgroundImage: plasticBackground(themeColor, plasticDark)
                });
            }
        );

        $pdfButton.on('click', function () {
            window.cbdDebug && console.log('[CBD PDF] FAB clicked');

            // Get top-level containers (not nested)
            var $topLevel = $('.cbd-container:visible').filter(function () {
                var $this = $(this);
                if ($this.parent().closest('.cbd-container-content, .cbd-content, .cbd-collapsible-content').length > 0) {
                    return false;
                }
                return true;
            });

            window.cbdDebug && console.log('[CBD PDF] Top-level containers:', $topLevel.length);

            if ($topLevel.length === 0) {
                alert('Keine sichtbaren Container-Bl\u00f6cke zum Exportieren gefunden.');
                return;
            }

            enterSelectionMode($topLevel);
        });

        $('body').append($pdfButton);

        // =====================================================================
        // CSS Injection
        // =====================================================================

        function injectSelectionCSS() {
            if ($('#cbd-pdf-selection-styles').length > 0) return;

            var css =
                /* Toolbar */
                // Dieselbe plastische Rezeptur wie der FAB und die Kopfleiste
                // des Themes — die Werkzeugleiste ist ein Band, kein Knopf,
                // deshalb ohne seitliche Innenkante.
                '.cbd-pdf-toolbar{' +
                  'position:fixed;top:0;left:0;right:0;z-index:999999;' +
                  'background-color:' + themeColor + ';' +
                  'background-image:' + plasticBackground(themeColor, plasticDark) + ';' +
                  'color:' + colorOnAccent + ';text-shadow:0 1px 2px rgba(0,0,0,.35);' +
                  'padding:10px 20px;display:flex;align-items:center;' +
                  'gap:10px;flex-wrap:wrap;font-size:14px;' +
                  'box-shadow:' +
                    'inset 0 3px 3px -1px color-mix(in srgb,' + plasticShadow + ' 75%,transparent),' +
                    'inset 0 -2px 2px -1px rgba(255,255,255,.5),' +
                    '0 3px 10px color-mix(in srgb,' + plasticShadow + ' 55%,transparent);' +
                  'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif' +
                '}' +
                'body.admin-bar .cbd-pdf-toolbar{top:32px}' +
                '@media(max-width:782px){body.admin-bar .cbd-pdf-toolbar{top:46px}}' +
                'body.cbd-pdf-mode{padding-top:56px!important}' +
                'body.cbd-pdf-mode.admin-bar{padding-top:88px!important}' +
                '@media(max-width:782px){body.cbd-pdf-mode.admin-bar{padding-top:102px!important}}' +

                '.cbd-pdf-toolbar .cbd-pdf-label{font-weight:700;font-size:15px;white-space:nowrap}' +
                '.cbd-pdf-toolbar .cbd-pdf-count{opacity:.9;white-space:nowrap}' +
                '.cbd-pdf-toolbar .cbd-pdf-spacer{flex:1}' +

                '.cbd-pdf-toolbar button{' +
                  'padding:6px 14px;border:2px solid rgba(255,255,255,.6);' +
                  'border-radius:6px;cursor:pointer;font-size:13px;' +
                  'background:transparent;color:' + colorOnAccent + ';transition:all .15s;white-space:nowrap' +
                '}' +
                '.cbd-pdf-toolbar button:hover{background:rgba(255,255,255,.2);border-color:' + colorOnAccent + '}' +
                '.cbd-pdf-toolbar button.cbd-pdf-go{' +
                  'background:' + colorOnAccent + ';color:' + themeColor + ';font-weight:700;border-color:' + colorOnAccent + '' +
                '}' +
                '.cbd-pdf-toolbar button.cbd-pdf-go:hover{background:' + themeColorLight + '}' +

                '.cbd-pdf-toolbar select{' +
                  'padding:6px 10px;border:2px solid rgba(255,255,255,.6);' +
                  'border-radius:6px;background:transparent;color:' + colorOnAccent + ';' +
                  'font-size:13px;cursor:pointer' +
                '}' +
                '.cbd-pdf-toolbar select option{background:' + colorBackground + ';color:' + colorTextPrimary + '}' +

                /* AP-2.4: Schalter "Tafelbilder/Notizen einschließen" — Ausrichtung
                   wie das bestehende <select>, Farben ueber die bereits vorhandenen
                   Variablen der Datei (colorOnAccent/colorTextPrimary), keine neuen
                   Hex-Werte. */
                '.cbd-pdf-toolbar .cbd-pdf-drawings-toggle{' +
                  'display:flex;align-items:center;gap:6px;' +
                  'color:' + colorOnAccent + ';font-size:13px;white-space:nowrap;' +
                  'cursor:pointer' +
                '}' +
                '.cbd-pdf-toolbar .cbd-pdf-drawings-toggle input.cbd-pdf-drawings-check{' +
                  'width:16px;height:16px;margin:0;cursor:pointer;accent-color:' + colorOnAccent +
                '}' +

                /* Selected block: green outline */
                '.cbd-container.cbd-pdf-on{' +
                  'outline:4px solid ' + colorSuccess + '!important;' +
                  'outline-offset:-2px;' +
                  'cursor:pointer!important;' +
                  'transition:outline .2s,opacity .2s' +
                '}' +

                /* Deselected block: red dashed outline + faded */
                '.cbd-container.cbd-pdf-off{' +
                  'outline:4px dashed ' + colorDanger + '!important;' +
                  'outline-offset:-2px;' +
                  'opacity:.4!important;' +
                  'cursor:pointer!important;' +
                  'transition:outline .2s,opacity .2s' +
                '}' +

                /* Badge */
                '.cbd-pdf-badge{' +
                  'position:absolute;top:-12px;right:-12px;z-index:100000;' +
                  'width:32px;height:32px;border-radius:50%;' +
                  'display:flex;align-items:center;justify-content:center;' +
                  'font-size:18px;font-weight:700;color:' + colorOnAccent + ';' +
                  'box-shadow:0 2px 8px rgba(0,0,0,.4);pointer-events:none' +
                '}' +
                '.cbd-pdf-badge-on{background:' + colorSuccess + '}' +
                '.cbd-pdf-badge-off{background:' + colorDanger + '}' +

                /* Kill pointer-events on everything INSIDE selectable blocks */
                'body.cbd-pdf-mode .cbd-container.cbd-pdf-on > *,' +
                'body.cbd-pdf-mode .cbd-container.cbd-pdf-off > *{' +
                  'pointer-events:none!important' +
                '}' +
                /* But keep badge visible (it already has pointer-events:none) */

                /* Mobile */
                '@media(max-width:600px){' +
                  '.cbd-pdf-toolbar{padding:8px 12px;gap:6px;font-size:12px}' +
                  '.cbd-pdf-toolbar button{padding:5px 10px;font-size:12px}' +
                  '.cbd-pdf-badge{width:26px;height:26px;font-size:14px;top:-8px;right:-8px}' +
                '}';

            $('head').append('<style id="cbd-pdf-selection-styles">' + css + '</style>');
            window.cbdDebug && console.log('[CBD PDF] CSS injected');
        }

        // =====================================================================
        // Selection Mode
        // =====================================================================

        function enterSelectionMode($blocks) {
            if (selectionActive) return;
            selectionActive = true;
            $containerBlocks = $blocks;
            window.cbdDebug && console.log('[CBD PDF] Entering selection mode with', $blocks.length, 'blocks');

            injectSelectionCSS();
            $pdfButton.hide();
            $('body').addClass('cbd-pdf-mode');

            // Create toolbar
            var toolbar =
                '<div class="cbd-pdf-toolbar" id="cbd-pdf-toolbar">' +
                '  <span class="cbd-pdf-label">PDF Export</span>' +
                '  <span class="cbd-pdf-count"></span>' +
                '  <button type="button" class="cbd-pdf-all">Alle</button>' +
                '  <button type="button" class="cbd-pdf-none">Keine</button>' +
                '  <span class="cbd-pdf-spacer"></span>' +
                '  <select class="cbd-pdf-mode-sel">' +
                '    <option value="visual">Visuell</option>' +
                '    <option value="print">Druck-optimiert</option>' +
                '    <option value="text">Nur Text</option>' +
                '  </select>' +
                '  <label class="cbd-pdf-drawings-toggle">' +
                '    <input type="checkbox" class="cbd-pdf-drawings-check" checked>' +
                '    Tafelbilder/Notizen einschließen' +
                '  </label>' +
                '  <button type="button" class="cbd-pdf-go">PDF erstellen</button>' +
                '  <button type="button" class="cbd-pdf-exit">Abbrechen</button>' +
                '</div>';

            $('body').prepend(toolbar);
            window.cbdDebug && console.log('[CBD PDF] Toolbar created');

            // Mark all blocks as selected
            $containerBlocks.each(function (i) {
                var block = this;
                var $block = $(block);

                // Add selected class
                $block.addClass('cbd-pdf-on');

                // Force position:relative for badge positioning
                block.style.setProperty('position', 'relative', 'important');
                block.style.setProperty('z-index', 'auto', 'important');

                // Create badge with inline styles as fallback
                var $badge = $('<span class="cbd-pdf-badge cbd-pdf-badge-on">\u2713</span>');
                $badge.css({
                    position: 'absolute',
                    top: '-12px',
                    right: '-12px',
                    zIndex: 100000,
                    width: '32px',
                    height: '32px',
                    borderRadius: '50%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '18px',
                    fontWeight: '700',
                    color: colorOnAccent,
                    background: colorSuccess,
                    boxShadow: '0 2px 8px rgba(0,0,0,.4)',
                    pointerEvents: 'none'
                });

                $block.append($badge);
                window.cbdDebug && console.log('[CBD PDF] Block', i, 'marked:', block.className.substring(0, 60));
            });

            updateCount();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            bindEvents();
            window.cbdDebug && console.log('[CBD PDF] Selection mode active');
        }

        function exitSelectionMode() {
            if (!selectionActive) return;
            selectionActive = false;
            window.cbdDebug && console.log('[CBD PDF] Exiting selection mode');

            $('#cbd-pdf-toolbar').remove();
            $('body').removeClass('cbd-pdf-mode');

            if ($containerBlocks) {
                $containerBlocks.each(function () {
                    var block = this;
                    $(block).removeClass('cbd-pdf-on cbd-pdf-off');
                    block.style.removeProperty('position');
                    block.style.removeProperty('z-index');
                });
                $containerBlocks.find('.cbd-pdf-badge').remove();
            }

            $(document).off('.cbdSel');
            $pdfButton.show();
            $containerBlocks = null;
        }

        function updateCount() {
            if (!$containerBlocks) return;
            var total = $containerBlocks.length;
            var on = $containerBlocks.filter('.cbd-pdf-on').length;
            $('.cbd-pdf-count').text(on + ' von ' + total + ' Bl\u00f6cken');
            $('.cbd-pdf-go').css('opacity', on > 0 ? '1' : '.4');
        }

        function toggleBlock($block) {
            var $badge = $block.find('> .cbd-pdf-badge');

            if ($block.hasClass('cbd-pdf-on')) {
                $block.removeClass('cbd-pdf-on').addClass('cbd-pdf-off');
                $badge.text('\u2717')
                    .removeClass('cbd-pdf-badge-on').addClass('cbd-pdf-badge-off')
                    .css('background', colorDanger);
            } else {
                $block.removeClass('cbd-pdf-off').addClass('cbd-pdf-on');
                $badge.text('\u2713')
                    .removeClass('cbd-pdf-badge-off').addClass('cbd-pdf-badge-on')
                    .css('background', colorSuccess);
            }

            updateCount();
            window.cbdDebug && console.log('[CBD PDF] Toggled block:', $block[0].id || '(no id)');
        }

        // =====================================================================
        // Event Binding
        // =====================================================================

        function bindEvents() {
            // Click on selectable block → toggle
            $(document).on('click.cbdSel', '.cbd-pdf-on, .cbd-pdf-off', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                toggleBlock($(this));
                return false;
            });

            // Select all
            $(document).on('click.cbdSel', '.cbd-pdf-all', function (e) {
                e.stopPropagation();
                $containerBlocks.each(function () {
                    var $b = $(this);
                    if (!$b.hasClass('cbd-pdf-on')) {
                        $b.removeClass('cbd-pdf-off').addClass('cbd-pdf-on');
                        $b.find('> .cbd-pdf-badge').text('\u2713')
                            .removeClass('cbd-pdf-badge-off').addClass('cbd-pdf-badge-on')
                            .css('background', colorSuccess);
                    }
                });
                updateCount();
            });

            // Select none
            $(document).on('click.cbdSel', '.cbd-pdf-none', function (e) {
                e.stopPropagation();
                $containerBlocks.each(function () {
                    var $b = $(this);
                    if (!$b.hasClass('cbd-pdf-off')) {
                        $b.removeClass('cbd-pdf-on').addClass('cbd-pdf-off');
                        $b.find('> .cbd-pdf-badge').text('\u2717')
                            .removeClass('cbd-pdf-badge-on').addClass('cbd-pdf-badge-off')
                            .css('background', colorDanger);
                    }
                });
                updateCount();
            });

            // Cancel
            $(document).on('click.cbdSel', '.cbd-pdf-exit', function (e) {
                e.stopPropagation();
                exitSelectionMode();
            });

            // Create PDF
            $(document).on('click.cbdSel', '.cbd-pdf-go', function (e) {
                e.stopPropagation();
                window.cbdDebug && console.log('[CBD PDF] Create PDF clicked');

                var selectedBlocks = [];
                var mode = $('.cbd-pdf-mode-sel').val();
                var includeDrawings = $('.cbd-pdf-drawings-check').is(':checked');

                $containerBlocks.filter('.cbd-pdf-on').each(function () {
                    selectedBlocks.push($(this));
                });

                if (selectedBlocks.length === 0) {
                    alert('Bitte mindestens einen Block ausw\u00e4hlen.');
                    return;
                }

                exitSelectionMode();
                startPDFExport(selectedBlocks, mode, includeDrawings);
            });

            // ESC key
            $(document).on('keydown.cbdSel', function (e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    exitSelectionMode();
                }
            });
        }

        // =====================================================================
        // PDF Export
        // =====================================================================

        function startPDFExport(selectedBlocks, mode, includeDrawings) {
            window.cbdDebug && console.log('[CBD PDF] Starting export:', selectedBlocks.length, 'blocks, mode:', mode, 'includeDrawings:', includeDrawings);
            if (typeof window.cbdPDFExportServerSide === 'function') {
                window.cbdPDFExportServerSide(selectedBlocks, mode, undefined, includeDrawings);
            } else {
                console.warn('[CBD PDF] No export function, using window.print()');
                window.print();
            }
        }
    });

})(jQuery);
