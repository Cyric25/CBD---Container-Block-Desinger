<?php
/**
 * Standalone-Harness für die Icon-Position — ohne WordPress.
 *
 * Heute gibt es KEINE funktionierende Icon-Positionierung: Der Wert
 * features.icon.position wird bei jedem Speichern ohne Whitelist auf
 * 'top-left' zurückgeschrieben und danach nirgends gelesen. In JEDEM
 * bestehenden Design steht deshalb "position":"top-left" — ein Wert, den
 * nie jemand gewählt hat. Der neue Standardwert heißt deshalb 'header';
 * alle vier Altwerte ('top-left', 'top-right', 'bottom-left',
 * 'bottom-right') fallen beim Lesen darauf zurück, damit kein Bestandsblock
 * sein Icon aus der Kopfzeile verliert.
 *
 * Geprüft wird die komplette Datenschicht: Grenzen/Vorgaben, Bereinigung
 * von Position und Feinversatz, CSS-Klassen- und Style-Erzeugung, die
 * Vorschau für das Admin-Formular sowie die Integration in
 * cbd_parse_features_from_post().
 *
 * Aufruf:  php tools/test-icon-position.php
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');

// --- Stubs ----------------------------------------------------------------
function __($s, $d = null) { return $s; }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : stripslashes((string) $v); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_html_class($c) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c); }
function sanitize_hex_color($c) { return preg_match('/^#[a-f0-9]{6}$/i', (string) $c) ? $c : null; }

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

/** Simuliert wp_magic_quotes(): WordPress slasht $_POST. */
function as_post($value) { return addslashes($value); }

// ----------------------------------------------------------------------
echo "== Vorgaben (cbd_icon_position_defaults) ==\n";

if (!function_exists('cbd_icon_position_defaults')) {
    check('cbd_icon_position_defaults() existiert', false);
} else {
    $defaults = cbd_icon_position_defaults();

    check('liefert ein Array', is_array($defaults), $defaults);
    check(
        'genau fuenf Positionen',
        isset($defaults['positions']) && is_array($defaults['positions']) && 5 === count($defaults['positions']),
        $defaults['positions'] ?? null
    );
    check(
        'Positionen enthalten header und die vier Container-Ecken',
        isset($defaults['positions']) && array(
            'header',
            'container-top-left',
            'container-top-right',
            'container-bottom-left',
            'container-bottom-right',
        ) == array_values((array) $defaults['positions']),
        $defaults['positions'] ?? null
    );
    check('Vorgabe ist header', isset($defaults['default']) && 'header' === $defaults['default'], $defaults['default'] ?? null);
    check('Grenzen -200/200', isset($defaults['offset_min'], $defaults['offset_max']) && -200 === $defaults['offset_min'] && 200 === $defaults['offset_max'], $defaults);
    check('Standard-Versatz 0', isset($defaults['offset_default']) && 0 === $defaults['offset_default'], $defaults['offset_default'] ?? null);
}

// ----------------------------------------------------------------------
echo "\n== cbd_sanitize_icon_position() ==\n";

if (!function_exists('cbd_sanitize_icon_position')) {
    check('cbd_sanitize_icon_position() existiert', false);
} else {
    $position_cases = array(
        'container-top-left'      => 'container-top-left',
        'container-top-right'     => 'container-top-right',
        'container-bottom-left'   => 'container-bottom-left',
        'container-bottom-right'  => 'container-bottom-right',
        'header'                  => 'header',
        // Altwerte -- MUESSEN auf 'header' zurueckfallen, sonst verliert
        // jeder Bestandsblock sein Icon aus der Kopfzeile.
        'top-left'                => 'header',
        'top-right'               => 'header',
        'bottom-left'             => 'header',
        'bottom-right'            => 'header',
        // Unsinn / leer / boesartig
        ''                        => 'header',
        '<script>x</script>'      => 'header',
        'irgendwas'               => 'header',
        // Trim + Kleinschreibung
        ' CONTAINER-TOP-RIGHT '   => 'container-top-right',
        ' header '                => 'header',
    );
    foreach ($position_cases as $in => $expected) {
        $actual = cbd_sanitize_icon_position($in);
        check(var_export($in, true) . ' -> ' . $expected, $expected === $actual, $actual);
    }

    // Rundlauf mit Slashes wie aus $_POST (Muster: as_post() in test-icon-value.php).
    $slashed = as_post("container-top-left'; DROP TABLE x; --");
    check(
        'geslashter Unsinn faellt auf header zurueck',
        'header' === cbd_sanitize_icon_position($slashed),
        cbd_sanitize_icon_position($slashed)
    );

    $slashed_valid = as_post('container-bottom-left');
    check(
        'geslashter gueltiger Wert kommt unveraendert an',
        'container-bottom-left' === cbd_sanitize_icon_position($slashed_valid),
        cbd_sanitize_icon_position($slashed_valid)
    );
}

// ----------------------------------------------------------------------
echo "\n== cbd_sanitize_icon_offset() ==\n";

if (!function_exists('cbd_sanitize_icon_offset')) {
    check('cbd_sanitize_icon_offset() existiert', false);
} else {
    $offset_cases = array(
        '12'      => 12,
        '-14'     => -14,
        '12,5'    => 13,   // deutsches Komma, gerundet
        '12.4'    => 12,
        'abc'     => 0,
        ''        => 0,
        '9999'    => 200,  // nach oben geklemmt
        '-9999'   => -200, // nach unten geklemmt
        0         => 0,
        12        => 12,
        '  5  '   => 5,
    );
    foreach ($offset_cases as $in => $expected) {
        $actual = cbd_sanitize_icon_offset($in);
        check(var_export($in, true) . ' -> ' . $expected, $expected === $actual, $actual);
    }

    // Rundlauf mit Slashes.
    $slashed = as_post('12,5');
    check('geslashtes Komma -> 13', 13 === cbd_sanitize_icon_offset($slashed), cbd_sanitize_icon_offset($slashed));
}

// ----------------------------------------------------------------------
echo "\n== cbd_get_icon_position_class() ==\n";

if (!function_exists('cbd_get_icon_position_class')) {
    check('cbd_get_icon_position_class() existiert', false);
} else {
    check("'header' -> ''", '' === cbd_get_icon_position_class('header'), cbd_get_icon_position_class('header'));

    $class = cbd_get_icon_position_class('container-bottom-right');
    check(
        "'container-bottom-right' enthaelt cbd-icon-positioned",
        false !== strpos($class, 'cbd-icon-positioned'),
        $class
    );
    check(
        "'container-bottom-right' enthaelt cbd-icon-at-bottom-right",
        false !== strpos($class, 'cbd-icon-at-bottom-right'),
        $class
    );

    $class_tl = cbd_get_icon_position_class('container-top-left');
    check('container-top-left enthaelt cbd-icon-positioned', false !== strpos($class_tl, 'cbd-icon-positioned'), $class_tl);
    check('container-top-left enthaelt cbd-icon-at-top-left', false !== strpos($class_tl, 'cbd-icon-at-top-left'), $class_tl);

    // Unbekannter/ungueltiger Wert darf keine Positionierungsklasse erzeugen
    // (die Funktion soll defensiv sein, auch wenn sie im Regelfall nur
    // bereits sanitisierte Werte bekommt).
    check("unbekannter Wert -> ''", '' === cbd_get_icon_position_class('irgendwas'), cbd_get_icon_position_class('irgendwas'));
}

// ----------------------------------------------------------------------
echo "\n== cbd_get_icon_position_style() ==\n";

if (!function_exists('cbd_get_icon_position_style')) {
    check('cbd_get_icon_position_style() existiert', false);
} else {
    check('(0, 0) -> leer', '' === cbd_get_icon_position_style(0, 0), cbd_get_icon_position_style(0, 0));

    $style = cbd_get_icon_position_style(12, -4);
    check('enthaelt --cbd-icon-dx:12px', false !== strpos($style, '--cbd-icon-dx:12px'), $style);
    check('enthaelt --cbd-icon-dy:-4px', false !== strpos($style, '--cbd-icon-dy:-4px'), $style);
    check('kein Komma im Style', false === strpos($style, ','), $style);

    $style_x_only = cbd_get_icon_position_style(5, 0);
    check('nur X gesetzt -> enthaelt dx', false !== strpos($style_x_only, '--cbd-icon-dx:5px'), $style_x_only);

    $style_y_only = cbd_get_icon_position_style(0, -7);
    check('nur Y gesetzt -> enthaelt dy', false !== strpos($style_y_only, '--cbd-icon-dy:-7px'), $style_y_only);

    // Muss ueber den gesamten erlaubten Bereich niemals ein Dezimalkomma
    // erzeugen (Rundfunktions-/Locale-Falle wie beim Icon-Groessen-Regler).
    $bad = false;
    for ($x = -200; $x <= 200; $x += 25) {
        $s = cbd_get_icon_position_style($x, -$x);
        if (false !== strpos($s, ',')) {
            $bad = true;
            break;
        }
    }
    check('gesamter Bereich frei von Kommas', !$bad);
}

// ----------------------------------------------------------------------
echo "\n== cbd_icon_position_preview() ==\n";

if (!function_exists('cbd_icon_position_preview')) {
    check('cbd_icon_position_preview() existiert', false);
} else {
    $preview = cbd_icon_position_preview('header', 0, 0);
    check('liefert ein Array', is_array($preview), $preview);
    check('mindestens ein Eintrag', count($preview) >= 1, $preview);

    $preview_offset = cbd_icon_position_preview('container-top-right', 12, -4);
    $joined = implode(' | ', array_map('strval', $preview_offset));
    check('Versatz taucht lesbar auf (12)', false !== strpos($joined, '12'), $preview_offset);
    check('Versatz taucht lesbar auf (4)', false !== strpos($joined, '4'), $preview_offset);
}

// ----------------------------------------------------------------------
echo "\n== Integration: cbd_parse_features_from_post() ==\n";

// Kein Positionsfeld im $_POST -> muss auf die neuen Vorgaben zurueckfallen.
$post_empty = array('features' => array('icon' => array('enabled' => '1', 'value' => 'dashicons-star-filled')));
$parsed_empty = cbd_parse_features_from_post($post_empty);
check(
    'fehlendes Positionsfeld -> header',
    'header' === ($parsed_empty['icon']['position'] ?? null),
    $parsed_empty['icon']['position'] ?? null
);
check(
    'fehlendes offsetX -> 0',
    0 === ($parsed_empty['icon']['offsetX'] ?? null),
    $parsed_empty['icon']['offsetX'] ?? null
);
check(
    'fehlendes offsetY -> 0',
    0 === ($parsed_empty['icon']['offsetY'] ?? null),
    $parsed_empty['icon']['offsetY'] ?? null
);

// Altwert 'top-left' im $_POST -- so sieht JEDES bestehende Design heute aus.
$post_legacy = array('features' => array('icon' => array(
    'enabled' => '1',
    'value' => 'dashicons-star-filled',
    'position' => 'top-left',
)));
$parsed_legacy = cbd_parse_features_from_post($post_legacy);
check(
    "Altwert 'top-left' aus \$_POST -> header",
    'header' === ($parsed_legacy['icon']['position'] ?? null),
    $parsed_legacy['icon']['position'] ?? null
);

// Gueltiger neuer Wert samt Versatz muss durchkommen.
$post_new = array('features' => array('icon' => array(
    'enabled' => '1',
    'value' => 'dashicons-star-filled',
    'position' => 'container-bottom-right',
    'offsetX' => '12,5',
    'offsetY' => '-8',
)));
$parsed_new = cbd_parse_features_from_post($post_new);
check(
    "neuer Wert 'container-bottom-right' kommt an",
    'container-bottom-right' === ($parsed_new['icon']['position'] ?? null),
    $parsed_new['icon']['position'] ?? null
);
check('offsetX wird bereinigt (12,5 -> 13)', 13 === ($parsed_new['icon']['offsetX'] ?? null), $parsed_new['icon']['offsetX'] ?? null);
check('offsetY wird uebernommen (-8)', -8 === ($parsed_new['icon']['offsetY'] ?? null), $parsed_new['icon']['offsetY'] ?? null);

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
