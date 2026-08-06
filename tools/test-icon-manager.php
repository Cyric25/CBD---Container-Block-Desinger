<?php
/**
 * Standalone-Harness für CBD_Icon_Manager::sanitize_icon_name().
 *
 * Der Dateiname eines Uploads wird zum Icon-Namen, landet im Dateisystem
 * und in einer URL. Diese Funktion ist damit die Stelle, an der ein
 * Verzeichnis-Ausbruch verhindert wird — und muss zusätzlich garantieren,
 * dass das Ergebnis von CBD_Icon_Library::parse_value() wieder akzeptiert
 * wird (sonst wäre das Icon nach dem Upload unbenutzbar).
 *
 * Aufruf:  php tools/test-icon-manager.php
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

// --- Stubs ----------------------------------------------------------------
function __($s, $d = null) { return $s; }
function is_admin() { return true; }
function add_action() {}
function trailingslashit($s) { return rtrim($s, '/\\') . '/'; }
function apply_filters($t, $v) { return $v; }
function get_transient($k) { return false; }
function set_transient($k, $v, $t) { return true; }
function delete_transient($k) { return true; }
function esc_url($u) { return $u; }
function esc_attr($s) { return $s; }
function esc_html($s) { return $s; }
function add_query_arg($k, $v, $u) { return $u . '?' . $k . '=' . $v; }
function wp_get_upload_dir() {
    return array('basedir' => sys_get_temp_dir() . '/cbd-none', 'baseurl' => 'https://example.test/u', 'error' => false);
}
// Vereinfachte Fassung der WordPress-Funktion (reicht für die Umlaute hier)
function remove_accents($s) {
    return strtr($s, array(
        'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U',
        'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a',
        'ñ' => 'n', 'ç' => 'c',
    ));
}

require_once $plugin_dir . 'includes/class-cbd-icon-library.php';
require_once $plugin_dir . 'includes/class-cbd-icon-manager.php';

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

function name_is($input, $expected) {
    $actual = CBD_Icon_Manager::sanitize_icon_name($input);
    check(var_export($input, true) . ' -> ' . var_export($expected, true), $expected === $actual, $actual);
}

echo "== Normale Namen ==\n";
name_is('experimente', 'experimente');
name_is('Experimente', 'experimente');
name_is('mein-icon_2', 'mein-icon_2');
name_is('42', '42');

echo "\n== Umlaute und Leerzeichen ==\n";
name_is('Übungen', 'ubungen');
name_is('Mein Neues Icon', 'mein-neues-icon');
name_is('Größe', 'grosse');

echo "\n== Verzeichnis-Ausbruch ==\n";
name_is('../../../wp-config', 'wp-config');
name_is('..\\..\\evil', 'evil');
name_is('/etc/passwd', 'etc-passwd');
name_is('....//....//x', 'x');
name_is('foo/../bar', 'foo-bar');

echo "\n== Gefaehrliche Endungen und Zeichen ==\n";
name_is('shell.php', 'shell-php');
name_is('icon.svg.php', 'icon-svg-php');
name_is('a<script>b', 'a-script-b');
name_is("null\x00byte", 'null-byte');
name_is('icon?query=1', 'icon-query-1');
name_is('icon%2e%2e', 'icon-2e-2e');

echo "\n== Randfaelle ==\n";
name_is('---', '');
name_is('', '');
name_is('.', '');
name_is('_-_', '');
name_is('--a', 'a');           // trim() entfernt fuehrende Trennzeichen
name_is('-_-icon-_-', 'icon');
check('Laenge auf 64 begrenzt',
    64 >= strlen(CBD_Icon_Manager::sanitize_icon_name(str_repeat('a', 200))));

echo "\n== Ergebnis ist immer fuer parse_value() gueltig ==\n";
$inputs = array(
    'experimente', 'Übungen', '../../../wp-config', 'shell.php', 'Mein Neues Icon',
    'a<script>b', '42', 'icon?query=1', str_repeat('x', 120), 'ÄÖÜ-test',
);
$all_valid = true;
$bad = null;
foreach ($inputs as $input) {
    $name = CBD_Icon_Manager::sanitize_icon_name($input);
    if ('' === $name) {
        continue; // leere Namen werden vom Upload abgelehnt
    }
    if (null === CBD_Icon_Library::parse_value('ui/' . $name)) {
        $all_valid = false;
        $bad = array($input, $name);
        break;
    }
}
check('jeder erzeugte Name passiert parse_value()', $all_valid, $bad);

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
