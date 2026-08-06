<?php
/**
 * Standalone-Harness für CBD_SVG_Sanitizer — läuft OHNE WordPress.
 *
 * Prüft, dass die bekannten SVG-Angriffsvektoren entfernt bzw. abgelehnt
 * werden und dass legitime Icons (insbesondere die eigenen Kacheln mit
 * Verlauf und Filtern) unbeschädigt durchkommen.
 *
 * Aufruf:  php tools/test-svg-sanitizer.php
 * Exit-Code 0 = alles grün, 1 = mindestens ein Fehler.
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');
$plugin_dir = str_replace('\\', '/', dirname(__DIR__)) . '/';

function __($s, $d = null) { return $s; }
function number_format_i18n($n) { return (string) $n; }

require_once $plugin_dir . 'includes/class-cbd-svg-sanitizer.php';

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

/** Erwartet: Datei wird komplett abgelehnt. */
function rejects($label, $svg) {
    $r = CBD_SVG_Sanitizer::sanitize($svg);
    check($label, null === $r['svg'] && '' !== $r['error'], $r['error']);
}

/** Erwartet: Datei kommt durch, enthält $needle aber nicht mehr. */
function strips($label, $svg, $needle) {
    $r = CBD_SVG_Sanitizer::sanitize($svg);
    if (null === $r['svg']) {
        check($label, false, 'komplett abgelehnt: ' . $r['error']);
        return;
    }
    check($label, false === stripos($r['svg'], $needle), $r['svg']);
}

echo "== Ablehnung: XXE / Servercode ==\n";
rejects('DOCTYPE mit externer Entity',
    '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>');
rejects('DOCTYPE allein',
    '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>');
rejects('eingebetteter PHP-Code',
    '<svg xmlns="http://www.w3.org/2000/svg"><?php system($_GET["c"]); ?><rect width="1" height="1"/></svg>');
rejects('kein gueltiges XML', '<svg><rect</svg>');
rejects('Wurzelelement ist kein svg', '<html xmlns="http://www.w3.org/1999/xhtml"><body>hi</body></html>');
rejects('leere Datei', '   ');

echo "\n== Entfernen: Skript und Event-Handler ==\n";
strips('<script>-Element',
    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>', '<script');
strips('onload-Attribut',
    '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>', 'onload');
strips('ONLOAD in Grossschreibung',
    '<svg xmlns="http://www.w3.org/2000/svg" ONLOAD="alert(1)"><rect width="1" height="1"/></svg>', 'onload');
strips('onclick auf Kindelement',
    '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" onclick="alert(1)"/></svg>', 'onclick');
strips('onmouseover',
    '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="1" cy="1" r="1" onmouseover="alert(1)"/></svg>', 'onmouseover');

echo "\n== Entfernen: externe Referenzen ==\n";
strips('<foreignObject>',
    '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>', 'foreignObject');
strips('<image> mit externer Quelle',
    '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.test/x.png"/></svg>', '<image');
strips('<use> mit xlink:href',
    '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="https://evil.test/x.svg#a"/></svg>', '<use');
strips('<a>-Element mit javascript:',
    '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><rect width="1" height="1"/></a></svg>', 'javascript');
strips('<animate> mit values',
    '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"><animate attributeName="x" values="javascript:alert(1)"/></rect></svg>', '<animate');
strips('<feImage>',
    '<svg xmlns="http://www.w3.org/2000/svg"><filter id="f"><feImage href="https://evil.test/x.png"/></filter></svg>', 'feImage');

echo "\n== Entfernen: unsichere Werte ==\n";
strips('javascript: in style-Attribut',
    '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" style="background:url(javascript:alert(1))"/></svg>', 'javascript');
strips('externe url() in fill',
    '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" fill="url(https://evil.test/x.svg#a)"/></svg>', 'evil.test');
strips('@import in <style>',
    '<svg xmlns="http://www.w3.org/2000/svg"><style>@import url("https://evil.test/x.css");</style><rect width="1" height="1"/></svg>', '@import');
// Payload XML-kodiert — roh (mit spitzen Klammern im Attribut) waere die
// Datei bereits kein gueltiges XML und wuerde schon davor abgelehnt.
strips('data:text/html in style',
    '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" style="background:url(data:text/html,&lt;script&gt;alert(1)&lt;/script&gt;)"/></svg>', 'text/html');
rejects('rohe spitze Klammer im Attribut ist ungueltiges XML',
    '<svg xmlns="http://www.w3.org/2000/svg"><rect style="background:url(data:text/html,<script>alert(1)</script>)"/></svg>');
strips('XML-Verarbeitungsanweisung',
    '<svg xmlns="http://www.w3.org/2000/svg"><?xml-stylesheet href="https://evil.test/x.xsl"?><rect width="1" height="1"/></svg>', 'xml-stylesheet');

echo "\n== Legitime Icons bleiben heil ==\n";
$tile = file_get_contents($plugin_dir . 'assets/icons/kategorien/experimente.svg');
check('Testdatei vorhanden', false !== $tile && '' !== $tile);

$r = CBD_SVG_Sanitizer::sanitize($tile);
check('eigene Kachel wird akzeptiert', null !== $r['svg'], $r['error']);
check('nichts entfernt', array() === $r['removed'], $r['removed']);
check('Verlauf erhalten', false !== strpos($r['svg'], 'linearGradient'));
check('Filter erhalten', false !== strpos($r['svg'], 'feGaussianBlur'));
check('interne url(#…)-Referenz erhalten', false !== strpos($r['svg'], 'url(#bg)'));
check('Pfaddaten erhalten', false !== strpos($r['svg'], '<path'));

$number = file_get_contents($plugin_dir . 'assets/icons/zahlen/42.svg');
$rn = CBD_SVG_Sanitizer::sanitize($number);
check('Zahlenkachel akzeptiert', null !== $rn['svg'], $rn['error']);
check('Textelement erhalten', false !== strpos($rn['svg'], '<text'));
check('font-family erhalten', false !== strpos($rn['svg'], 'font-family'));

echo "\n== Fremdes, harmloses SVG ==\n";
$plain = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2L2 22h20z" fill="#ff0000" stroke="#000" stroke-width="2"/></svg>';
$rp = CBD_SVG_Sanitizer::sanitize($plain);
check('einfaches Icon akzeptiert', null !== $rp['svg'], $rp['error']);
check('fill erhalten', false !== strpos($rp['svg'], '#ff0000'));
check('stroke-width erhalten', false !== strpos($rp['svg'], 'stroke-width'));
check('viewBox erhalten', false !== stripos($rp['svg'], 'viewBox'));

echo "\n== Namespace wird ergaenzt ==\n";
$nons = '<svg viewBox="0 0 24 24"><rect width="24" height="24"/></svg>';
$rns = CBD_SVG_Sanitizer::sanitize($nons);
check('xmlns ergaenzt', null !== $rns['svg'] && false !== strpos($rns['svg'], 'http://www.w3.org/2000/svg'), $rns['svg']);

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
