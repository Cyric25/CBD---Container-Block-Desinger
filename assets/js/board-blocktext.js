/**
 * Blocktext auf die Tafel — reines Text- und Geometriemodul
 * (Plan „Blocktext auf die Tafel", Phase 1, Architektur E5).
 *
 * Setzt `window.CBDBoardBlocktext`. Noch nicht eingereiht (ab Phase 2).
 *
 * ES5, keine Abhaengigkeiten.
 *
 * @package ContainerBlockDesigner
 */
(function (global) {
    'use strict';

    function latexZuText(latex, protokoll) {
        return '';
    }

    global.CBDBoardBlocktext = {
        latexZuText: latexZuText
    };
})(window);
