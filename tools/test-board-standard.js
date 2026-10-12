/*
 * Prüfungen "Standardeinstellung beim Öffnen" (Rückmeldung 2026-10-12):
 * Der Tafelmodus merkte sich Stiftfarbe und Werkzeug über das Schließen
 * hinaus (CBDBoardMode ist EIN Objekt je Seite), die Werkzeugleiste wurde
 * beim Öffnen aber fest mit Schwarz und aktivem Stift gebaut. Wer mit Rot
 * aufhörte und die Tafel eines anderen Blocks öffnete, schrieb rot weiter,
 * obwohl die Leiste Schwarz zeigte.
 *
 * Abgedeckt:
 *   - nach Schließen mit Rot + Textmarker öffnet die Tafel mit Stift und
 *     Schwarz; Leiste (aktiver Knopf, Farbfeld) und Zustand stimmen überein
 *   - ein Strich direkt nach dem Öffnen ist schwarz
 *   - der Breitenregler zeigt die tatsächlich geltende Breite (die je
 *     Werkzeug gemerkten Breiten bleiben bewusst erhalten)
 *   - der Transparenzregler ist nach dem Öffnen verborgen (Stift aktiv)
 *
 * Öffnet bewusst über B.open() und NICHT über ctx.oeffnen(): Letzteres
 * ruft danach setTool('pen') auf und hätte den Fehler verdeckt.
 *
 * Wird von tools/test-board-zeiger.html NACH test-board-lasso.js geladen
 * und hängt sich an den Haken window.__zusatzTests an (vorhandene zuerst).
 */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var CID = 'test-zeiger';
    var ctx;

    var vorherSync = window.__zusatzTests;

    function ergebnis(f) {
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function neuOeffnen(cid) {
        if (B.overlay) B.destroy();
        B.open(cid || CID, '<p>Inhalt</p>', '#ffffff');
    }

    /** Zustand und Werkzeugleiste müssen dasselbe sagen. */
    function leisteStimmt(f, wo) {
        var aktiv = B.overlay.querySelectorAll('.cbd-board-tool.active');
        if (aktiv.length !== 1) f.push(wo + ': ' + aktiv.length + ' aktive Werkzeugknöpfe');
        else if (aktiv[0].getAttribute('data-tool') !== B.currentTool) {
            f.push(wo + ': Knopf ' + aktiv[0].getAttribute('data-tool') + ' ≠ Werkzeug ' + B.currentTool);
        }
        var farbe = B.overlay.querySelector('.cbd-board-color');
        if (farbe && farbe.value.toLowerCase() !== String(B.currentColor).toLowerCase()) {
            f.push(wo + ': Farbfeld ' + farbe.value + ' ≠ Stiftfarbe ' + B.currentColor);
        }
    }

    function standardNachRotUndTextmarker() {
        var f = [];
        neuOeffnen();
        B.setColor('#ff0000');
        B.setTool('highlighter');
        B.destroy();
        neuOeffnen('test-zeiger-anderer-block');
        if (B.currentTool !== 'pen') f.push('Werkzeug nach Öffnen ' + B.currentTool);
        if (String(B.currentColor).toLowerCase() !== '#000000') f.push('Stiftfarbe nach Öffnen ' + B.currentColor);
        leisteStimmt(f, 'nach Öffnen');
        return ergebnis(f);
    }

    function strichNachOeffnenSchwarz() {
        var f = [];
        neuOeffnen();
        B.setColor('#ff0000');
        B.destroy();
        neuOeffnen();
        ctx.zeiger('pointerdown', 4, 'pen', 100, 100);
        ctx.zeiger('pointermove', 4, 'pen', 160, 100);
        ctx.zeiger('pointermove', 4, 'pen', 220, 100);
        ctx.zeiger('pointerup', 4, 'pen', 220, 100);
        var el = (B.modell && B.modell.elemente) || [];
        var s = el.filter(function(e) { return e.typ === 'strich'; });
        if (s.length !== 1) f.push(s.length + ' Striche statt 1');
        else if (String(s[0].farbe).toLowerCase() !== '#000000') f.push('Strichfarbe ' + s[0].farbe);
        return ergebnis(f);
    }

    function breitenreglerZeigtGeltendeBreite() {
        var f = [];
        neuOeffnen();
        B.setTool('pen');
        B.setLineWidth(9);          // wird je Werkzeug gemerkt
        B.setTool('highlighter');
        B.destroy();
        neuOeffnen();
        var regler = B.overlay.querySelector('.cbd-board-width');
        var anzeige = B.overlay.querySelector('.cbd-board-width-display');
        if (B.lineWidth !== 9) f.push('geltende Stiftbreite ' + B.lineWidth + ' statt der gemerkten 9');
        if (!regler || Number(regler.value) !== B.lineWidth) f.push('Regler ' + (regler && regler.value) + ' ≠ Breite ' + B.lineWidth);
        if (!anzeige || anzeige.textContent !== B.lineWidth + 'px') f.push('Anzeige "' + (anzeige && anzeige.textContent) + '"');
        var transparenz = B.overlay.querySelector('.cbd-board-opacity-control');
        if (transparenz && transparenz.style.display !== 'none') f.push('Transparenzregler sichtbar');
        return ergebnis(f);
    }

    window.__zusatzTests = function(c) {
        if (typeof vorherSync === 'function') vorherSync(c);
        ctx = c;
        var p = c.pruefe;
        p('Standard: nach Rot + Textmarker öffnet die Tafel mit Stift und Schwarz, Leiste stimmt', standardNachRotUndTextmarker);
        p('Standard: erster Strich nach dem Öffnen ist schwarz', strichNachOeffnenSchwarz);
        p('Standard: Breitenregler zeigt die geltende Breite, Transparenzregler verborgen', breitenreglerZeigtGeltendeBreite);
    };
})();
