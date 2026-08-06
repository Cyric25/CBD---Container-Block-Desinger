<?php
/**
 * Standalone-Harness für CBD_Icon_Library — läuft OHNE WordPress.
 *
 * Prüft die Klasse gegen die echten SVG-Dateien in assets/icons/:
 * Bestand, Sortierung, URL-Bildung samt Cache-Buster, Verzeichnis-Traversal
 * und die Admin-Vorschau.
 *
 * Aufruf:  php tools/test-icon-library.php
 * Exit-Code 0 = alles grün, 1 = mindestens ein Fehler.
 *
 * Sinnvoll nach jeder Änderung an der Icon-Registry oder am Icon-Bestand,
 * weil die Klasse fast ausschließlich mit dem Dateisystem arbeitet und
 * PHPUnit dafür eine komplette WordPress-Bootstrap bräuchte.
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
define('CBD_PLUGIN_URL', 'https://example.test/wp-content/plugins/container-block-designer/');
define('CBD_VERSION', 'test');

// --- Minimale WordPress-Stubs ---------------------------------------------
$GLOBALS['cbd_test_transients'] = array();

function is_admin() { return false; }
function trailingslashit($s) { return rtrim($s, '/\\') . '/'; }
function apply_filters($tag, $value) { return $value; }
function get_transient($k) {
    return isset($GLOBALS['cbd_test_transients'][$k]) ? $GLOBALS['cbd_test_transients'][$k] : false;
}
function set_transient($k, $v, $t) { $GLOBALS['cbd_test_transients'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['cbd_test_transients'][$k]); return true; }
function esc_url($u) { return $u; }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function wp_get_upload_dir() {
    // Override-Verzeichnis wird im Basistest bewusst ausgeklammert.
    return array(
        'basedir' => sys_get_temp_dir() . '/cbd-icons-no-override',
        'baseurl' => 'https://example.test/wp-content/uploads',
        'error'   => false,
    );
}
function add_query_arg($key, $value, $url) {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $sep . $key . '=' . rawurlencode($value);
}

require_once CBD_PLUGIN_DIR . 'includes/class-cbd-icon-library.php';

// --- Mini-Assertions ------------------------------------------------------
$GLOBALS['cbd_test_fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['cbd_test_fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

// --- Tests ----------------------------------------------------------------
echo "== Bestand ==\n";
$index = CBD_Icon_Library::get_index();
check('drei Gruppen vorhanden', array_keys($index) === array('kategorien', 'zahlen', 'ui'), array_keys($index));
check('UI-Icons gefunden', count($index['ui']) > 0, count($index['ui']));
check('Kategorien gefunden', count($index['kategorien']) > 0, count($index['kategorien']));
check('Zahlen gefunden', count($index['zahlen']) > 0, count($index['zahlen']));

echo "\n== Sortierung ==\n";
// PHP normalisiert numerische Array-Keys zu int — daher (int)-Vergleich.
$numbers = array_map('intval', array_keys($index['zahlen']));
$sorted  = $numbers;
sort($sorted);
check('Zahlen numerisch sortiert (2 vor 10)', $numbers === $sorted, array_slice($numbers, 0, 5));

$ui_names = array_keys($index['ui']);
$ui_sorted = $ui_names;
sort($ui_sorted);
check('UI-Icons alphabetisch sortiert', $ui_names === $ui_sorted);

echo "\n== URL-Bildung ==\n";
$first_cat = key($index['kategorien']);
$url = CBD_Icon_Library::get_icon_url('kategorien/' . $first_cat);
check('Kategorie-URL zeigt in assets/icons/', false !== strpos($url, 'assets/icons/kategorien/'), $url);
check('Cache-Buster angehängt', false !== strpos($url, '?ver='), $url);

$max = CBD_Icon_Library::get_max_number();
check('höchste Zahlenkachel ermittelt', $max > 0, $max);
check('Zahlen-URL gebildet', false !== strpos(CBD_Icon_Library::get_number_icon_url(1), 'zahlen/1.svg'));

echo "\n== Nicht vorhandene Icons liefern Leerstring ==\n";
check('unbekannter Name', '' === CBD_Icon_Library::get_icon_url('ui/gibt-es-nicht'));
check('Zahl über dem Maximum', '' === CBD_Icon_Library::get_number_icon_url($max + 1));
check('Zahl 0', '' === CBD_Icon_Library::get_number_icon_url(0));

echo "\n== Sicherheit: Verzeichnis-Traversal ==\n";
check('../ wird abgewiesen', null === CBD_Icon_Library::parse_value('zahlen/../../../wp-config'));
check('unbekannte Gruppe wird abgewiesen', null === CBD_Icon_Library::parse_value('etc/passwd'));
check('doppelter Slash wird abgewiesen', null === CBD_Icon_Library::parse_value('ui//etc/passwd'));
check('Wert ohne Gruppe wird abgewiesen', null === CBD_Icon_Library::parse_value('home'));
check('gültiger Wert wird akzeptiert',
    array('group' => 'ui', 'name' => 'home') === CBD_Icon_Library::parse_value('ui/home'));

echo "\n== JS-Daten für die Nummerierung ==\n";
$assets = CBD_Icon_Library::get_number_assets();
check('Basis-URL endet auf zahlen/', 'zahlen/' === substr($assets['base'], -7), $assets['base']);
check('Basis-URL ohne Query-String', false === strpos($assets['base'], '?'), $assets['base']);
check('Version gesetzt', '' !== $assets['ver'], $assets['ver']);
check('max entspricht get_max_number()', $assets['max'] === $max, $assets['max']);

echo "\n== Picker-Daten ==\n";
$picker = CBD_Icon_Library::get_picker_data();
check('Gruppenreihenfolge Kategorien/Zahlen/Symbole',
    array_keys($picker['categories']) === array('kategorien', 'zahlen', 'ui'),
    array_keys($picker['categories']));
check('Gruppenlabel übersetzt', 'Symbole' === $picker['categories']['ui']['label']);
$entry = $picker['categories']['kategorien']['icons'][0];
check('Eintrag hat value und url', isset($entry['value'], $entry['url']));
check('value ist gruppe/name', 0 === strpos($entry['value'], 'kategorien/'), $entry['value']);

echo "\n== Admin-Vorschau ==\n";
$custom = json_encode(array('type' => 'custom', 'value' => 'kategorien/' . $first_cat));
check('custom rendert <img>', 0 === strpos(CBD_Icon_Library::get_admin_preview_html($custom), '<img class="cbd-custom-icon-preview"'));
check('custom Label lesbar', 'custom: kategorien/' . $first_cat === CBD_Icon_Library::get_admin_label($custom));
check('Legacy-Dashicons unverändert',
    '<span class="dashicons dashicons-admin-generic"></span>' === CBD_Icon_Library::get_admin_preview_html('dashicons-admin-generic'));
check('Legacy ohne Präfix bekommt eines',
    '<span class="dashicons dashicons-star-filled"></span>' === CBD_Icon_Library::get_admin_preview_html('star-filled'));
check('fehlendes custom-Icon zeigt Warnung',
    false !== strpos(CBD_Icon_Library::get_admin_preview_html(json_encode(array('type' => 'custom', 'value' => 'ui/nope'))), 'dashicons-warning'));

$fails = $GLOBALS['cbd_test_fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
