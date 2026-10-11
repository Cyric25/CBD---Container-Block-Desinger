/*
 * Layout-Messung der Tafel (AP-2.4, #7149) - in der Konsole der Testseite
 * tools/test-board-zeiger.html ausführen (oder per Automatisierung):
 *     await window.__tafelLayout()  ->  Objekt mit Kennzahlen
 * Vergleich vorher/nachher bei 1366x768 und 820x1180 (unverändert) sowie
 * 1141x253 (Zeichenfläche >= 170 px, Werkzeuge per Wischen erreichbar).
 */
(function() {
    'use strict';
    function box(el) {
        if (!el) return null;
        var r = el.getBoundingClientRect();
        return [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)];
    }
    // Liefert ein Promise: wartet das Einblenden (cbdBoardFadeIn, 0,25 s,
    // scale 0.98) ab, sonst misst man die skalierte Zwischenstufe.
    window.__tafelLayout = function() {
        var B = window.CBDBoardMode;
        if (B.overlay) B.destroy();
        B.open('layout-test', '<p>Inhalt für die Layout-Messung</p>', '#ffffff');
        // In einem Hintergrund-Tab läuft die Animation nicht weiter - abschalten
        B.overlay.style.animation = 'none';
        return new Promise(function(fertig) {
            setTimeout(function() { fertig(messen(B)); }, 400);
        });
    };

    function messen(B) {
        var o = B.overlay;
        var inner = o.querySelector('.cbd-board-toolbar-inner');
        var knoepfe = o.querySelectorAll('.cbd-board-toolbar button, .cbd-board-toolbar input');
        var kleinsterKnopf = null;
        var ausserhalb = 0;
        var ib = inner.getBoundingClientRect();
        for (var i = 0; i < knoepfe.length; i++) {
            var r = knoepfe[i].getBoundingClientRect();
            if (knoepfe[i].offsetParent === null) continue; // ausgeblendet
            if (knoepfe[i].tagName === 'BUTTON') {
                var m = Math.min(r.width, r.height);
                if (kleinsterKnopf === null || m < kleinsterKnopf) kleinsterKnopf = Math.round(m);
            }
            if (r.right > ib.right + 1 || r.left < ib.left - 1) ausserhalb++;
        }
        var cs = getComputedStyle(inner);
        var ergebnis = {
            viewport: window.innerWidth + 'x' + window.innerHeight,
            header: box(o.querySelector('.cbd-board-header')),
            split: box(o.querySelector('.cbd-board-split')),
            inhalt: box(o.querySelector('.cbd-board-content')),
            canvasArea: box(o.querySelector('.cbd-board-canvas-area')),
            canvas: box(B.drawingCanvas),
            canvasPixel: [B.drawingCanvas.width, B.drawingCanvas.height],
            toolbar: box(o.querySelector('.cbd-board-toolbar')),
            toolbarInner: box(inner),
            innerScrollWidth: inner.scrollWidth,
            innerClientWidth: inner.clientWidth,
            innerOverflowX: cs.overflowX,
            innerTouchAction: cs.touchAction,
            innerWrap: cs.flexWrap,
            kleinsterKnopf: kleinsterKnopf,
            elementeAusserhalbSichtbar: ausserhalb,
            ersterKnopf: box(o.querySelector('.cbd-board-tool')),
            zoomReset: box(o.querySelector('.cbd-board-zoom-reset')),
            schliessen: box(o.querySelector('.cbd-board-close'))
        };
        B.destroy();
        return ergebnis;
    }
})();
