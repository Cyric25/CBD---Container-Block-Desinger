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

        // State
        var $containerBlocks = null;
        var selectionActive = false;

        // =====================================================================
        // Floating Action Button
        // =====================================================================

        var $pdfButton = $('<div id="cbd-pdf-export-fab">PDF</div>');
        $pdfButton.css({
            position: 'fixed',
            bottom: '30px',
            right: '30px',
            zIndex: '999999',
            background: themeColor,
            color: 'white',
            borderRadius: '12px',
            padding: '15px',
            cursor: 'pointer',
            boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
            fontSize: '14px',
            fontWeight: 'bold',
            textAlign: 'center',
            minWidth: '60px',
            transition: 'all 0.2s ease'
        });
        $pdfButton.attr('title', 'Container-Bl\u00f6cke als PDF exportieren');
        $pdfButton.hover(
            function () { $(this).css({ transform: 'scale(1.05)', background: themeColorDark }); },
            function () { $(this).css({ transform: 'scale(1)', background: themeColor }); }
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
                '.cbd-pdf-toolbar{' +
                  'position:fixed;top:0;left:0;right:0;z-index:999999;' +
                  'background:' + themeColor + ';color:#fff;' +
                  'padding:10px 20px;display:flex;align-items:center;' +
                  'gap:10px;flex-wrap:wrap;font-size:14px;' +
                  'box-shadow:0 2px 10px rgba(0,0,0,.3);' +
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
                  'background:transparent;color:#fff;transition:all .15s;white-space:nowrap' +
                '}' +
                '.cbd-pdf-toolbar button:hover{background:rgba(255,255,255,.2);border-color:#fff}' +
                '.cbd-pdf-toolbar button.cbd-pdf-go{' +
                  'background:#fff;color:' + themeColor + ';font-weight:700;border-color:#fff' +
                '}' +
                '.cbd-pdf-toolbar button.cbd-pdf-go:hover{background:' + themeColorLight + '}' +

                '.cbd-pdf-toolbar select{' +
                  'padding:6px 10px;border:2px solid rgba(255,255,255,.6);' +
                  'border-radius:6px;background:transparent;color:#fff;' +
                  'font-size:13px;cursor:pointer' +
                '}' +
                '.cbd-pdf-toolbar select option{background:#fff;color:#333}' +

                /* Selected block: green outline */
                '.cbd-container.cbd-pdf-on{' +
                  'outline:4px solid #2ecc40!important;' +
                  'outline-offset:-2px;' +
                  'cursor:pointer!important;' +
                  'transition:outline .2s,opacity .2s' +
                '}' +

                /* Deselected block: red dashed outline + faded */
                '.cbd-container.cbd-pdf-off{' +
                  'outline:4px dashed #cc3333!important;' +
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
                  'font-size:18px;font-weight:700;color:#fff;' +
                  'box-shadow:0 2px 8px rgba(0,0,0,.4);pointer-events:none' +
                '}' +
                '.cbd-pdf-badge-on{background:#2ecc40}' +
                '.cbd-pdf-badge-off{background:#cc3333}' +

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
                    color: '#fff',
                    background: '#2ecc40',
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
                    .css('background', '#cc3333');
            } else {
                $block.removeClass('cbd-pdf-off').addClass('cbd-pdf-on');
                $badge.text('\u2713')
                    .removeClass('cbd-pdf-badge-off').addClass('cbd-pdf-badge-on')
                    .css('background', '#2ecc40');
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
                            .css('background', '#2ecc40');
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
                            .css('background', '#cc3333');
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

                $containerBlocks.filter('.cbd-pdf-on').each(function () {
                    selectedBlocks.push($(this));
                });

                if (selectedBlocks.length === 0) {
                    alert('Bitte mindestens einen Block ausw\u00e4hlen.');
                    return;
                }

                exitSelectionMode();
                startPDFExport(selectedBlocks, mode);
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

        function startPDFExport(selectedBlocks, mode) {
            window.cbdDebug && console.log('[CBD PDF] Starting export:', selectedBlocks.length, 'blocks, mode:', mode);
            if (typeof window.cbdPDFExportServerSide === 'function') {
                window.cbdPDFExportServerSide(selectedBlocks, mode);
            } else {
                console.warn('[CBD PDF] No export function, using window.print()');
                window.print();
            }
        }
    });

})(jQuery);
