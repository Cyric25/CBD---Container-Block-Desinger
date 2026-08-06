<?php
/**
 * Standalone-Harness für CBD_Design_Transfer::parse_payload() und
 * ::sanitize_slug() — läuft OHNE WordPress und ohne Datenbank.
 *
 * Geprüft wird die Eingangsvalidierung des Imports: Was darf hinein, was
 * wird abgelehnt, und bleibt ein exportiertes Design beim Wiedereinlesen
 * unverändert.
 *
 * Aufruf:  php tools/test-design-transfer.php
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');
define('MINUTE_IN_SECONDS', 60);

$plugin_dir = str_replace('\\', '/', dirname(__DIR__)) . '/';
define('CBD_PLUGIN_DIR', $plugin_dir);
define('CBD_VERSION', 'test');

// --- Stubs ----------------------------------------------------------------
function __($s, $d = null) { return $s; }
function add_action() {}
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
/**
 * Vereinfachte Fassung der WordPress-Funktion. Wichtig: sie wandelt auch
 * GROSSbuchstaben mit Diakritika — sonst testet man an der Realität vorbei.
 */
function remove_accents($s) {
    return strtr($s, array(
        'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
        'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'á' => 'a',
        'É' => 'E', 'À' => 'A', 'ñ' => 'n', 'ç' => 'c',
    ));
}

require_once $plugin_dir . 'includes/class-cbd-design-transfer.php';

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

function rejects($label, $json) {
    $r = CBD_Design_Transfer::parse_payload($json);
    check($label, '' !== $r['error'] && empty($r['designs']), $r['error']);
}

/** Baut eine gueltige Exportdatei. */
function payload($designs) {
    return json_encode(array(
        'format'        => 'cbd-designs',
        'formatVersion' => 1,
        'plugin'        => '3.1.78',
        'designs'       => $designs,
    ));
}

echo "== Ablehnung ungueltiger Dateien ==\n";
rejects('leere Datei', '   ');
rejects('kein JSON', 'das ist keine json-datei');
rejects('JSON ohne format-Feld', json_encode(array('designs' => array(array('name' => 'x')))));
rejects('falsches format-Feld', json_encode(array('format' => 'irgendwas', 'designs' => array(array('name' => 'x')))));
rejects('neuere Formatversion', json_encode(array('format' => 'cbd-designs', 'formatVersion' => 99, 'designs' => array(array('name' => 'x')))));
rejects('keine Designs', payload(array()));
rejects('Designs ohne name', payload(array(array('title' => 'Ohne Slug'))));
rejects('nur unbrauchbare Slugs', payload(array(array('name' => '---'), array('name' => '!!!'))));

echo "\n== Gueltige Datei ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array(
        'name' => 'info-box', 'title' => 'Info-Box', 'description' => 'Ein Hinweis',
        'config' => array('allowInnerBlocks' => true),
        'styles' => array('padding' => array('top' => 10)),
        'features' => array('icon' => array('enabled' => true, 'value' => '{"type":"custom","value":"kategorien/hinweise"}')),
        'status' => 'active',
    ),
)));
check('akzeptiert', '' === $r['error'], $r['error']);
check('ein Design', 1 === count($r['designs']));
$d = $r['designs'][0];
check('Slug erhalten', 'info-box' === $d['name'], $d['name']);
check('Titel erhalten', 'Info-Box' === $d['title']);
check('config ist JSON-String', is_string($d['config']) && is_array(json_decode($d['config'], true)), $d['config']);
check('config-Inhalt erhalten', true === json_decode($d['config'], true)['allowInnerBlocks']);
check('verschachtelte styles erhalten', 10 === json_decode($d['styles'], true)['padding']['top']);
check('Icon-Wert im features-Feld ueberlebt',
    false !== strpos(json_decode($d['features'], true)['icon']['value'], 'kategorien/hinweise'),
    json_decode($d['features'], true)['icon']['value']);

echo "\n== Slug-Normalisierung ==\n";
$cases = array(
    'Info Box'          => 'info-box',
    'Übungen'           => 'ubungen',
    'MEIN DESIGN'       => 'mein-design',
    '../../etc/passwd'  => 'etc-passwd',
    'a<script>b'        => 'a-script-b',
    'mit_unterstrich'   => 'mit-unterstrich',
    '--doppel--strich--' => 'doppel-strich',
);
foreach ($cases as $in => $expected) {
    $actual = CBD_Design_Transfer::sanitize_slug($in);
    check(var_export($in, true) . ' -> ' . var_export($expected, true), $expected === $actual, $actual);
}

echo "\n== Doppelte Slugs in einer Datei ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array('name' => 'box', 'title' => 'Erste'),
    array('name' => 'box', 'title' => 'Zweite'),
    array('name' => 'Box', 'title' => 'Dritte'),   // normalisiert ebenfalls zu "box"
)));
check('nur ein Eintrag bleibt', 1 === count($r['designs']), count($r['designs']));
check('der erste gewinnt', 'Erste' === $r['designs'][0]['title'], $r['designs'][0]['title']);

echo "\n== Boesartige Inhalte ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array(
        'name' => 'evil',
        'title' => '<script>alert(1)</script>',
        'description' => '<img src=x onerror=alert(1)>',
        'styles' => array('background' => '<script>alert(1)</script>'),
    ),
)));
$d = $r['designs'][0];
check('Titel entschaerft', false === strpos($d['title'], '<'), $d['title']);
check('Beschreibung entschaerft', false === strpos($d['description'], '<'), $d['description']);
check('verschachtelter Style entschaerft', false === strpos($d['styles'], '<script'), $d['styles']);

echo "\n== Unbekannte Felder werden verworfen ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array('name' => 'sauber', 'title' => 'Sauber', 'id' => 999, 'created_at' => '2020-01-01', 'boese' => 'hallo'),
)));
$d = $r['designs'][0];
check('kein id-Feld', !isset($d['id']), array_keys($d));
check('kein created_at', !isset($d['created_at']));
check('kein Fremdfeld', !isset($d['boese']));
check('nur erwartete Schluessel',
    array('name','title','description','config','styles','features','status','is_default') === array_keys($d),
    array_keys($d));

echo "\n== Status wird auf zwei Werte begrenzt ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array('name' => 'a', 'status' => 'inactive'),
    array('name' => 'b', 'status' => 'geloescht'),
)));
check('inactive bleibt', 'inactive' === $r['designs'][0]['status']);
check('unbekannter Status -> active', 'active' === $r['designs'][1]['status'], $r['designs'][1]['status']);

echo "\n== config als JSON-String (handgeschriebene Datei) ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array('name' => 'string-config', 'config' => '{"maxWidth":"800px"}'),
)));
check('String-config wird gelesen', '800px' === json_decode($r['designs'][0]['config'], true)['maxWidth'],
    $r['designs'][0]['config']);

echo "\n== Rundlauf: Export -> Import ==\n";
$original = array(
    'name' => 'merksatz', 'title' => 'Merksatz', 'description' => 'Wichtig',
    'config' => array('allowInnerBlocks' => true, 'maxWidth' => '900px'),
    'styles' => array('border' => array('width' => 2, 'color' => '#e24614')),
    'features' => array('numbering' => array('enabled' => true, 'format' => 'numeric')),
    'status' => 'active', 'is_default' => 0,
);
$r = CBD_Design_Transfer::parse_payload(payload(array($original)));
$d = $r['designs'][0];
check('Slug identisch', $original['name'] === $d['name']);
check('config identisch', $original['config'] === json_decode($d['config'], true), json_decode($d['config'], true));
check('styles identisch', $original['styles'] === json_decode($d['styles'], true), json_decode($d['styles'], true));
check('features identisch', $original['features'] === json_decode($d['features'], true), json_decode($d['features'], true));

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
