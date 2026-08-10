<?php
/**
 * Standalone-Harness für die Icon-Größen-Einstellung — ohne WordPress.
 *
 * Geprüft wird die Kette Eingabefeld -> cbd_sanitize_icon_scale() ->
 * Option -> cbd_get_icon_scale_css() -> CSS-Variable, plus die
 * Breakpoint-Vorschau, die in den Einstellungen die Handy-Größe anzeigt.
 *
 * Aufruf:  php tools/test-icon-scale.php
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');

// --- Stubs ----------------------------------------------------------------
function __($s, $d = null) { return $s; }
function wp_unslash($s) { return is_string($s) ? stripslashes($s) : $s; }

$GLOBALS['options'] = array();
function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default;
}
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; return true; }

require_once str_replace('\\', '/', dirname(__DIR__)) . '/includes/functions.php';

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

$bounds = cbd_icon_scale_bounds();

echo "== Grenzen ==\n";
check('Standard 110', 110 === $bounds['default'], $bounds['default']);
check('Minimum unter Standard', $bounds['min'] < $bounds['default']);
check('Maximum ueber Standard', $bounds['max'] > $bounds['default']);

echo "\n== Eingaben bereinigen ==\n";
$cases = array(
    // Eingabe            => erwartet
    '110'                 => 110,
    '150'                 => 150,
    150                   => 150,
    '  120  '             => 120,
    '99999'               => $bounds['max'],   // nach oben gekappt
    '-40'                 => $bounds['min'],   // nach unten gekappt
    '0'                   => $bounds['min'],
    ''                    => 110,              // leer -> Standard
    'abc'                 => 110,              // Unsinn -> Standard
    '<script>1</script>'  => 110,
    '112,5'               => 113,              // deutsches Komma, gerundet
    '112.4'               => 112,
);
foreach ($cases as $in => $expected) {
    $actual = cbd_sanitize_icon_scale($in);
    check(var_export($in, true) . ' -> ' . $expected, $expected === $actual, $actual);
}

echo "\n== CSS-Faktor ==\n";
$css_cases = array(110 => '1.1', 100 => '1', 150 => '1.5', 125 => '1.25', 50 => '0.5', 200 => '2');
foreach ($css_cases as $percent => $expected) {
    update_option('cbd_icon_scale', $percent);
    $actual = cbd_get_icon_scale_css();
    check($percent . ' % -> ' . $expected, $expected === $actual, $actual);
}

echo "\n== Faktor ist immer gueltiges CSS ==\n";
for ($p = $bounds['min']; $p <= $bounds['max']; $p++) {
    update_option('cbd_icon_scale', $p);
    $css = cbd_get_icon_scale_css();
    if (!preg_match('/^[0-9]+(\.[0-9]+)?$/', $css)) {
        check('Faktor fuer ' . $p . ' %', false, $css);
        break;
    }
}
check('alle ' . ($bounds['max'] - $bounds['min'] + 1) . ' Werte ergeben eine Dezimalzahl mit Punkt', true);

echo "\n== Kaputte Option in der DB ==\n";
update_option('cbd_icon_scale', 'irgendwas');
check('Text in der Option -> Standard', 110 === cbd_get_icon_scale_percent(), cbd_get_icon_scale_percent());
update_option('cbd_icon_scale', 99999);
check('absurder Wert in der Option -> gekappt', $bounds['max'] === cbd_get_icon_scale_percent(), cbd_get_icon_scale_percent());

echo "\n== Handy-Ansicht bleibt angepasst ==\n";
// Kern der Anforderung: auf jeder Stufe muss Handy < Tablet < Desktop bleiben.
foreach (array($bounds['min'], 100, 110, 150, $bounds['max']) as $percent) {
    $p = cbd_icon_scale_preview($percent);
    $desktop = $p['Desktop'];
    $tablet  = $p['Tablet'];
    $handy   = $p['Handy'];
    check(
        $percent . ' %: Handy(' . $handy . ') < Tablet(' . $tablet . ') < Desktop(' . $desktop . ')',
        $handy < $tablet && $tablet < $desktop,
        $p
    );
}

$p = cbd_icon_scale_preview(110);
check('bei 110 % ergibt Desktop 35 px', 35 === $p['Desktop'], $p['Desktop']);
check('bei 110 % ergibt Handy 26 px', 26 === $p['Handy'], $p['Handy']);

$p = cbd_icon_scale_preview($bounds['max']);
check('selbst am Maximum bleibt Handy unter 50 px', $p['Handy'] <= 48, $p['Handy']);

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
