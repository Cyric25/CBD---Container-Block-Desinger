<?php
/**
 * SVG-Sanitizer
 *
 * SVG ist XML und darf Skripte, externe Referenzen und Event-Handler
 * enthalten. Ein hochgeladenes SVG, das direkt im Browser geöffnet wird,
 * führt enthaltenes JavaScript im Kontext der Seite aus — deshalb blockiert
 * WordPress SVG-Uploads standardmäßig. Diese Klasse macht Uploads
 * verantwortbar: sie arbeitet mit einer **Whitelist**, alles nicht
 * ausdrücklich Erlaubte fliegt raus.
 *
 * Bewusst kein "böse Muster suchen"-Ansatz (Blacklist) — der ist gegen
 * Verschleierung nicht zu gewinnen.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.77
 */

if (!defined('ABSPATH')) {
    exit;
}

class CBD_SVG_Sanitizer {

    /** Obergrenze; die generierten Kacheln liegen bei ~2 KB. */
    const MAX_BYTES = 512000;

    /**
     * Erlaubte Elemente.
     *
     * Nicht enthalten und damit immer entfernt: script, foreignObject, image,
     * feImage, use, a, animate/animateTransform/animateMotion, set, handler,
     * audio, video, iframe. Alle können Code ausführen oder externe
     * Ressourcen nachladen.
     */
    private static $allowed_elements = array(
        'svg', 'g', 'defs', 'title', 'desc', 'style', 'switch',
        // Formen
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        // Verläufe und Muster
        'linearGradient', 'radialGradient', 'stop', 'pattern',
        // Masken und Beschnitt
        'clipPath', 'mask', 'marker', 'symbol',
        // Filter
        'filter', 'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite',
        'feConvolveMatrix', 'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight',
        'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR',
        'feGaussianBlur', 'feMerge', 'feMergeNode', 'feMorphology', 'feOffset',
        'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence',
    );

    /**
     * Erlaubte Attribute. Alles andere wird entfernt — insbesondere jedes
     * on*-Attribut (onload, onclick, …) sowie href und xlink:href, über die
     * sich externe Dokumente einbinden lassen.
     */
    private static $allowed_attributes = array(
        // Struktur
        'id', 'class', 'style', 'transform', 'viewbox', 'xmlns', 'xmlns:xlink',
        'version', 'width', 'height', 'x', 'y', 'preserveaspectratio', 'overflow',
        // Formen
        'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x1', 'y1', 'x2', 'y2', 'points', 'pathlength',
        // Farbe und Kontur
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity',
        'stroke-miterlimit', 'opacity', 'color', 'visibility', 'display', 'vector-effect',
        'shape-rendering', 'color-interpolation-filters', 'paint-order',
        // Verläufe
        'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform',
        'spreadmethod', 'fx', 'fy',
        // Muster, Masken, Marker
        'patternunits', 'patterncontentunits', 'patterntransform', 'clip-path',
        'clippathunits', 'clip-rule', 'mask', 'maskunits', 'maskcontentunits',
        'markerwidth', 'markerheight', 'refx', 'refy', 'orient', 'markerunits',
        'marker-start', 'marker-mid', 'marker-end',
        // Filter
        'filter', 'filterunits', 'primitiveunits', 'in', 'in2', 'result', 'stddeviation',
        'dx', 'dy', 'operator', 'flood-color', 'flood-opacity', 'values', 'type',
        'tablevalues', 'mode', 'k1', 'k2', 'k3', 'k4', 'radius', 'scale', 'basefrequency',
        'numoctaves', 'seed', 'azimuth', 'elevation', 'specularconstant',
        'specularexponent', 'surfacescale', 'diffuseconstant', 'limitingconeangle',
        'pointsatx', 'pointsaty', 'pointsatz', 'xchannelselector', 'ychannelselector',
        'edgemode', 'kernelmatrix', 'order', 'divisor', 'bias', 'targetx', 'targety',
        'preservealpha', 'amplitude', 'exponent', 'intercept', 'slope',
        // Text
        'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant',
        'text-anchor', 'dominant-baseline', 'alignment-baseline', 'baseline-shift',
        'letter-spacing', 'word-spacing', 'text-decoration', 'writing-mode',
        // Barrierefreiheit
        'role', 'aria-hidden', 'aria-label', 'aria-labelledby', 'focusable',
    );

    /**
     * Säubert einen SVG-String.
     *
     * @param string $svg Roh-Inhalt der Datei
     * @return array {
     *     @type string|null $svg     Gesäuberter SVG-String, null bei Ablehnung
     *     @type string      $error   Grund der Ablehnung ('' wenn erfolgreich)
     *     @type array       $removed Liste der entfernten Elemente/Attribute
     * }
     */
    public static function sanitize($svg) {
        $result = array('svg' => null, 'error' => '', 'removed' => array());

        if (!is_string($svg) || '' === trim($svg)) {
            $result['error'] = __('Die Datei ist leer.', 'container-block-designer');
            return $result;
        }

        if (strlen($svg) > self::MAX_BYTES) {
            $result['error'] = sprintf(
                __('Die Datei ist zu groß (maximal %s KB).', 'container-block-designer'),
                number_format_i18n(self::MAX_BYTES / 1024)
            );
            return $result;
        }

        // Vor dem Parsen abfangen: DOCTYPE/ENTITY ermöglichen XXE-Angriffe
        // (externe Entities lesen Serverdateien aus). Keine legitime
        // Icon-Datei braucht das, deshalb wird hier hart abgelehnt statt
        // repariert.
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg)) {
            $result['error'] = __('Die Datei enthält eine DOCTYPE- oder ENTITY-Deklaration und wurde abgelehnt.', 'container-block-designer');
            return $result;
        }

        if (false !== stripos($svg, '<?php') || false !== stripos($svg, '<%')) {
            $result['error'] = __('Die Datei enthält Servercode und wurde abgelehnt.', 'container-block-designer');
            return $result;
        }

        // UTF-8-BOM und führenden Leerraum entfernen
        $svg = preg_replace('/^\xEF\xBB\xBF/', '', $svg);
        $svg = ltrim($svg);

        $previous = libxml_use_internal_errors(true);

        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = false;

        // LIBXML_NONET verbietet Netzwerkzugriffe beim Parsen.
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $result['error'] = __('Die Datei ist kein gültiges XML/SVG.', 'container-block-designer');
            return $result;
        }

        // Doctype kann trotz Vorprüfung über Umwege auftauchen
        if (null !== $dom->doctype) {
            $result['error'] = __('Die Datei enthält eine DOCTYPE-Deklaration und wurde abgelehnt.', 'container-block-designer');
            return $result;
        }

        $root = $dom->documentElement;
        if (!$root || 'svg' !== strtolower($root->nodeName)) {
            $result['error'] = __('Das Wurzelelement ist kein <svg>.', 'container-block-designer');
            return $result;
        }

        $removed = array();
        self::clean_node($root, $removed);

        // Sicherstellen, dass der Namespace gesetzt ist — ohne ihn rendert
        // der Browser das SVG nicht.
        if (!$root->hasAttribute('xmlns')) {
            $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }

        $output = $dom->saveXML($root);

        if (false === $output || '' === trim($output)) {
            $result['error'] = __('Nach der Bereinigung blieb kein darstellbarer Inhalt übrig.', 'container-block-designer');
            return $result;
        }

        $result['svg']     = $output;
        $result['removed'] = array_values(array_unique($removed));

        return $result;
    }

    /**
     * Räumt einen Knoten und rekursiv alle Kinder auf.
     *
     * @param DOMNode $node
     * @param array   $removed Sammelt Beschreibungen der Entfernungen
     */
    private static function clean_node($node, &$removed) {
        // Rückwärts iterieren: removeChild verändert die Live-NodeList
        if ($node->hasChildNodes()) {
            for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
                $child = $node->childNodes->item($i);

                if (XML_COMMENT_NODE === $child->nodeType) {
                    $node->removeChild($child);
                    continue;
                }

                if (XML_PI_NODE === $child->nodeType) {
                    $removed[] = 'Verarbeitungsanweisung';
                    $node->removeChild($child);
                    continue;
                }

                if (XML_ELEMENT_NODE !== $child->nodeType) {
                    continue; // Text, CDATA
                }

                $name = self::local_name($child);

                if (!in_array($name, self::allowed_elements_lc(), true)) {
                    $removed[] = '<' . $child->nodeName . '>';
                    $node->removeChild($child);
                    continue;
                }

                self::clean_node($child, $removed);
            }
        }

        if (XML_ELEMENT_NODE !== $node->nodeType) {
            return;
        }

        // <style>-Inhalt gesondert prüfen: dort kann CSS externe Ressourcen
        // nachladen (@import, url(...)).
        // WICHTIG: vor der Attribut-Prüfung, denn <style> hat typischerweise
        // gar keine Attribute — ein vorgezogenes return würde das CSS
        // ungeprüft durchlassen.
        if ('style' === self::local_name($node)) {
            $css   = $node->textContent;
            $clean = self::clean_css($css, $removed);
            if ($clean !== $css) {
                $node->textContent = $clean;
            }
        }

        if (!$node->hasAttributes()) {
            return;
        }

        for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
            $attr = $node->attributes->item($i);
            $attr_name = strtolower($attr->nodeName);

            // Event-Handler in jeder Schreibweise
            if (0 === strpos($attr_name, 'on')) {
                $removed[] = $attr->nodeName . '-Attribut';
                $node->removeAttributeNode($attr);
                continue;
            }

            // href/xlink:href erlauben das Einbinden fremder Dokumente.
            // ("xmlns:xlink" enthält kein "xlink:" und ist davon nicht betroffen.)
            if ('href' === $attr_name || 0 === strpos($attr_name, 'xlink:')) {
                $removed[] = $attr->nodeName . '-Attribut';
                $node->removeAttributeNode($attr);
                continue;
            }

            if (!in_array($attr_name, self::$allowed_attributes, true)) {
                $removed[] = $attr->nodeName . '-Attribut';
                $node->removeAttributeNode($attr);
                continue;
            }

            if (!self::is_safe_value($attr->nodeValue)) {
                $removed[] = $attr->nodeName . '-Attribut (unsicherer Wert)';
                $node->removeAttributeNode($attr);
            }
        }
    }

    /**
     * Whitelist der Elemente in Kleinschreibung (einmal aufgebaut).
     *
     * @return array
     */
    private static function allowed_elements_lc() {
        static $lc = null;

        if (null === $lc) {
            $lc = array_map('strtolower', self::$allowed_elements);
        }

        return $lc;
    }

    /**
     * Elementname ohne Namespace-Präfix, kleingeschrieben.
     *
     * @param DOMNode $node
     * @return string
     */
    private static function local_name($node) {
        $name = $node->nodeName;
        $pos  = strpos($name, ':');

        if (false !== $pos) {
            $name = substr($name, $pos + 1);
        }

        return strtolower($name);
    }

    /**
     * Attributwerte auf ausführbare oder externe Inhalte prüfen.
     *
     * Erlaubt bleibt url(#id) — die eigenen Kacheln referenzieren so ihre
     * Verläufe und Filter. Externe URLs in url(...) sind dagegen tabu.
     *
     * @param string $value
     * @return bool
     */
    private static function is_safe_value($value) {
        // Whitespace und Steuerzeichen normalisieren, damit "java\nscript:"
        // nicht durchrutscht
        $normalized = strtolower(preg_replace('/[\s\x00-\x1F\x7F]+/', '', (string) $value));

        if (false !== strpos($normalized, 'javascript:')) {
            return false;
        }

        if (false !== strpos($normalized, 'vbscript:')) {
            return false;
        }

        if (false !== strpos($normalized, 'expression(')) {
            return false;
        }

        // data:-URIs nur ablehnen, wenn sie Markup transportieren könnten
        if (preg_match('#data:(text/html|image/svg)#', $normalized)) {
            return false;
        }

        // url(...) nur als Fragment-Referenz
        if (preg_match_all('/url\(([^)]*)\)/', $normalized, $matches)) {
            foreach ($matches[1] as $target) {
                $target = trim($target, "'\"");
                if ('' === $target || '#' !== $target[0]) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * CSS aus einem <style>-Element entschärfen.
     *
     * @param string $css
     * @param array  $removed
     * @return string
     */
    private static function clean_css($css, &$removed) {
        $original = $css;

        // @import lädt externe Stylesheets
        $css = preg_replace('/@import[^;]*;?/i', '', $css);

        // url(...) nur als Fragment-Referenz zulassen
        $css = preg_replace_callback('/url\(([^)]*)\)/i', function ($m) {
            $target = trim($m[1], " \t\n\r'\"");
            return ('' !== $target && '#' === $target[0]) ? $m[0] : 'none';
        }, $css);

        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/(javascript|vbscript)\s*:/i', '', $css);

        if ($css !== $original) {
            $removed[] = 'unsicheres CSS in <style>';
        }

        return $css;
    }
}
