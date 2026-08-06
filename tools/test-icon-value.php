<?php
/**
 * Standalone-Harness für den Icon-Wert-Rundlauf — läuft OHNE WordPress.
 *
 * Deckt den Fehler ab, der bis v3.1.77 dafür sorgte, dass Icons aller
 * Bibliotheken außer Dashicons unsichtbar blieben: cbd_parse_features_from_post()
 * speicherte den Wert ohne wp_unslash(), wodurch das JSON in der Datenbank
 * ungültig wurde.
 *
 * Getestet wird die komplette Kette:
 *   Picker -> $_POST (mit WordPress-Slashes) -> Speichern -> Lesen -> Rendern
 *
 * Aufruf:  php tools/test-icon-value.php
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');
define('DAY_IN_SECONDS', 86400);

$plugin_dir = str_replace('\\', '/', dirname(__DIR__)) . '/';
define('CBD_PLUGIN_DIR', $plugin_dir);
define('CBD_PLUGIN_URL', 'https://example.test/plugins/cdb/');
define('CBD_VERSION', 'test');

// --- WordPress-Stubs ------------------------------------------------------
function __($s, $d = null) { return $s; }
function is_admin() { return false; }
function trailingslashit($s) { return rtrim($s, '/\\') . '/'; }
function apply_filters($t, $v) { return $v; }
function get_transient($k) { return false; }
function set_transient($k, $v, $t) { return true; }
function delete_transient($k) { return true; }
function esc_url($u) { return $u; }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function add_query_arg($k, $v, $u) { return $u . (strpos($u, '?') === false ? '?' : '&') . $k . '=' . $v; }
function wp_get_upload_dir() {
    return array('basedir' => sys_get_temp_dir() . '/cbd-none', 'baseurl' => 'https://example.test/u', 'error' => false);
}
function wp_json_encode($d) { return json_encode($d); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_html_class($c) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c); }
function sanitize_hex_color($c) { return preg_match('/^#[a-f0-9]{6}$/i', (string) $c) ? $c : null; }
function wp_kses($s, $a) { return strip_tags((string) $s); }
/** Wie WordPress: rekursives stripslashes. */
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : stripslashes((string) $v); }

require_once $plugin_dir . 'includes/class-cbd-icon-library.php';
require_once $plugin_dir . 'includes/functions.php';

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

/** Kompletter Rundlauf: Auswahl -> $_POST -> DB -> gelesener Typ/Wert. */
function roundtrip($picked) {
    $post   = array('features' => array('icon' => array('enabled' => '1', 'value' => as_post($picked))));
    $parsed = cbd_parse_features_from_post($post);
    $stored = $parsed['icon']['value'];

    return array($stored, CBD_Icon_Library::parse_stored_value($stored));
}

echo "== Rundlauf: eigene SVG-Kachel (der gemeldete Fehler) ==\n";
$picked = json_encode(array('type' => 'custom', 'value' => 'kategorien/experimente'));
list($stored, $read) = roundtrip($picked);
check('DB-Wert ist gueltiges JSON', is_array(json_decode($stored, true)), $stored);
check('Typ bleibt custom', 'custom' === $read['type'], $read);
check('Wert bleibt erhalten', 'kategorien/experimente' === $read['value'], $read['value']);
check('faellt NICHT auf dashicons zurueck', 'dashicons' !== $read['type'], $read['type']);

echo "\n== Rundlauf: die anderen Bibliotheken (waren genauso betroffen) ==\n";
foreach (array(
    'fontawesome' => 'fa-solid fa-star',
    'material'    => 'home',
    'lucide'      => 'zap',
) as $type => $value) {
    list($s, $r) = roundtrip(json_encode(array('type' => $type, 'value' => $value)));
    check("$type ueberlebt", $type === $r['type'] && $value === $r['value'], $r);
}

echo "\n== Rundlauf: Legacy-Dashicons ==\n";
list($s, $r) = roundtrip('dashicons-admin-generic');
check('Klassenname unveraendert', 'dashicons-admin-generic' === $r['value'], $r['value']);
check('Typ dashicons', 'dashicons' === $r['type']);

list($s, $r) = roundtrip('star-filled');
check('Praefix wird ergaenzt', 'dashicons-star-filled' === $r['value'], $r['value']);

echo "\n== Reparatur von Altbestaenden in der DB ==\n";
// So sieht ein vor dem Fix gespeicherter Wert aus:
$kaputt = addslashes(json_encode(array('type' => 'custom', 'value' => 'kategorien/hinweise')));
$read   = CBD_Icon_Library::parse_stored_value($kaputt);
check('geslashtes JSON wird repariert', 'custom' === $read['type'], $read);
check('Wert korrekt gelesen', 'kategorien/hinweise' === $read['value'], $read['value']);

$kaputt_fa = addslashes(json_encode(array('type' => 'fontawesome', 'value' => 'fa-solid fa-heart')));
$read_fa   = CBD_Icon_Library::parse_stored_value($kaputt_fa);
check('altes Font-Awesome-Icon wird repariert', 'fontawesome' === $read_fa['type'], $read_fa);

echo "\n== Unsinnige und boesartige Eingaben ==\n";
list($s, $r) = roundtrip(json_encode(array('type' => 'javascript', 'value' => 'alert(1)')));
check('unbekannter Typ -> Default', 'dashicons-admin-generic' === $s, $s);

list($s, $r) = roundtrip(json_encode(array('type' => 'custom', 'value' => '')));
check('leerer Wert -> Default', 'dashicons-admin-generic' === $s, $s);

list($s, $r) = roundtrip('<script>alert(1)</script>');
check('Markup wird entschaerft', false === strpos($s, '<'), $s);

list($s, $r) = roundtrip(json_encode(array('type' => 'custom', 'value' => '<b>x</b>')));
check('Markup im JSON-Wert wird entfernt', false === strpos($s, '<'), $s);

echo "\n== Kein Doppel-Encoding bei mehrfachem Speichern ==\n";
$once  = roundtrip($picked);
$twice = roundtrip($once[0]);   // gespeicherten Wert erneut durchs Formular schicken
check('zweites Speichern bleibt stabil', $once[0] === $twice[0], array($once[0], $twice[0]));

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
