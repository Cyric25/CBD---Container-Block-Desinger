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
 *   - seit 3.1.142 auch Breiten aller Werkzeuge und die Textmarker-
 *     Transparenz: nach dem Öffnen Standard (3/20/8/15 px, 30 %), Regler
 *     und Anzeige stimmen; Transparenzregler verborgen (Stift aktiv)
 *   - innerhalb einer Sitzung gilt die je Werkzeug gewählte Breite weiter
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

    /** Seit 3.1.142: auch Breiten und Textmarker-Transparenz auf Standard. */
    function breitenAufStandard() {
        var f = [];
        neuOeffnen();
        B.setTool('pen');
        B.setLineWidth(9);
        B.setTool('highlighter');
        B.setLineWidth(14);
        B.setHighlighterOpacity(0.8);
        B.setTool('eraser-point');
        B.setLineWidth(30);
        B.destroy();
        neuOeffnen();
        var regler = B.overlay.querySelector('.cbd-board-width');
        var anzeige = B.overlay.querySelector('.cbd-board-width-display');
        if (B.lineWidth !== 3) f.push('Stiftbreite nach Öffnen ' + B.lineWidth + ' statt 3');
        if (!regler || Number(regler.value) !== 3) f.push('Regler ' + (regler && regler.value) + ' statt 3');
        if (!anzeige || anzeige.textContent !== '3px') f.push('Anzeige "' + (anzeige && anzeige.textContent) + '"');
        var transparenz = B.overlay.querySelector('.cbd-board-opacity-control');
        if (transparenz && transparenz.style.display !== 'none') f.push('Transparenzregler sichtbar');
        B.setTool('highlighter');
        if (B.lineWidth !== 20) f.push('Textmarkerbreite ' + B.lineWidth + ' statt 20');
        if (Math.abs(B.highlighterOpacity - 0.3) > 1e-9) f.push('Transparenz ' + B.highlighterOpacity + ' statt 0.3');
        B.setTool('eraser-point');
        if (B.lineWidth !== 15) f.push('Punkt-Radierer ' + B.lineWidth + ' statt 15');
        B.setTool('eraser-stroke');
        if (B.lineWidth !== 8) f.push('Strich-Radierer ' + B.lineWidth + ' statt 8');
        return ergebnis(f);
    }

    /** Innerhalb EINER Sitzung gilt die gewählte Breite je Werkzeug weiter. */
    function breiteGiltInnerhalbDerSitzung() {
        var f = [];
        neuOeffnen();
        B.setTool('pen');
        B.setLineWidth(9);
        B.setTool('highlighter');
        B.setTool('pen');
        if (B.lineWidth !== 9) f.push('Stiftbreite nach Werkzeugwechsel ' + B.lineWidth + ' statt 9');
        return ergebnis(f);
    }

    window.__zusatzTests = function(c) {
        if (typeof vorherSync === 'function') vorherSync(c);
        ctx = c;
        var p = c.pruefe;
        p('Standard: nach Rot + Textmarker öffnet die Tafel mit Stift und Schwarz, Leiste stimmt', standardNachRotUndTextmarker);
        p('Standard: erster Strich nach dem Öffnen ist schwarz', strichNachOeffnenSchwarz);
        p('Standard: Breiten und Textmarker-Transparenz nach dem Öffnen auf Standard, Regler stimmt', breitenAufStandard);
        p('Standard: innerhalb einer Sitzung gilt die gewählte Breite je Werkzeug weiter', breiteGiltInnerhalbDerSitzung);
    };
})();
