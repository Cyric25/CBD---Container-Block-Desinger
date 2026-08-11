<?php
/**
 * Standalone-Harness für den Klassen-Durchlass — läuft OHNE WordPress.
 *
 * Geprüft werden die Bausteine, mit denen eine gesperrte Seite (Theme-Sperre
 * „nur für Lehrpersonen") in der Klassenansicht wieder sichtbar wird:
 *
 *   AP-2.1  CBD_Classroom::basis_container_id()
 *           CBD_Classroom::behandelte_container()
 *
 * Aufruf:  php tools/test-classroom-gate.php
 *
 * Liegt unter tools/ und ist damit NICHT im Verteilungs-ZIP enthalten
 * (create-plugin-zip.js listet nur admin, assets, blocks, includes, vendor,
 * languages).
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

define('CBD_TABLE_CLASSES', 'wp_cbd_classes');
define('CBD_TABLE_CLASS_PAGES', 'wp_cbd_class_pages');
define('CBD_TABLE_DRAWINGS', 'wp_cbd_drawings');

// --- WordPress-Stubs ------------------------------------------------------
function __($s, $d = null) { return $s; }
function _e($s, $d = null) { echo $s; }
function esc_attr_e($s, $d = null) { echo htmlspecialchars($s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_url($u) { return $u; }
function add_action($t, $c, $p = 10, $a = 1) { return true; }
function add_filter($t, $c, $p = 10, $a = 1) { return true; }
function add_shortcode($t, $c) { return true; }
function register_setting($g, $n, $a = array()) { return true; }
function get_option($k, $d = false) { return $GLOBALS['test_options'][$k] ?? $d; }
function current_time($t) { return '2026-08-11 12:00:00'; }
function get_current_user_id() { return (int) ($GLOBALS['test_user_id'] ?? 0); }
function is_user_logged_in() { return ((int) ($GLOBALS['test_user_id'] ?? 0)) > 0; }
function current_user_can($c) { return false; }
function get_user_meta($u, $k, $s = false) { return $s ? '' : array(); }
function wp_hash_password($p) { return 'hash:' . $p; }
function wp_generate_password($l = 12, $s = true) { return str_repeat('x', $l); }
function get_transient($k) { return $GLOBALS['test_transients'][$k] ?? false; }
function set_transient($k, $v, $t) { $GLOBALS['test_transients'][$k] = $v; return true; }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : stripslashes((string) $v); }
function get_permalink($id) { return 'https://example.test/?p=' . (int) $id; }
function add_query_arg($args, $url) { return $url . '&stub=1'; }
function get_post($id) { return null; }
function has_shortcode($c, $t) { return false; }
function is_singular($t = '') { return false; }
function get_the_ID() { return 0; }
function admin_url($p) { return 'https://example.test/wp-admin/' . $p; }
function wp_create_nonce($a) { return 'nonce'; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function wp_register_script_module() {}
function wp_enqueue_script_module() {}
function check_ajax_referer($a, $n) { return true; }
function wp_send_json_error($d) { throw new RuntimeException('json_error'); }
function wp_send_json_success($d) { throw new RuntimeException('json_success'); }

$GLOBALS['test_options']    = array();
$GLOBALS['test_transients'] = array();
$GLOBALS['test_user_id']    = 0;

/**
 * Minimales $wpdb-Doppel für die Tabelle cbd_drawings.
 *
 * prepare() merkt sich die Argumente; get_col() filtert die hinterlegten
 * Zeilen danach. Das genügt, weil in diesem Harnisch nur EINE Abfrageform
 * vorkommt (behandelte Container einer Klasse auf einer Seite).
 */
class Test_WPDB {
    public $prefix = 'wp_';
    public $posts  = 'wp_posts';
    public $zeilen = array();     // je Eintrag: class_id, page_id, container_id, is_behandelt
    public $abfragen = 0;
    private $args = array();

    public function prepare($sql, ...$args) {
        // WordPress erlaubt auch ein Array als zweites Argument.
        if (1 === count($args) && is_array($args[0])) {
            $args = $args[0];
        }
        $this->args = $args;
        return $sql;
    }

    public function get_col($sql) {
        $this->abfragen++;
        $class_id = (int) ($this->args[0] ?? 0);
        $page_id  = (int) ($this->args[1] ?? 0);
        $treffer  = array();
        foreach ($this->zeilen as $z) {
            if ((int) $z['class_id'] === $class_id
                && (int) $z['page_id'] === $page_id
                && !empty($z['is_behandelt'])) {
                $treffer[] = (string) $z['container_id'];
            }
        }
        return $treffer;
    }

    public function get_results($sql, $output = OBJECT) {
        $this->abfragen++;
        $class_id = (int) ($this->args[0] ?? 0);
        $page_id  = (int) ($this->args[1] ?? 0);
        $out = array();
        foreach ($this->zeilen as $z) {
            if ((int) $z['class_id'] === $class_id && (int) $z['page_id'] === $page_id) {
                $o = new stdClass();
                $o->container_id = $z['container_id'];
                $o->drawing_data = $z['drawing_data'] ?? null;
                $o->is_behandelt = $z['is_behandelt'];
                $out[] = $o;
            }
        }
        return $out;
    }

    public function get_var($sql) { $this->abfragen++; return null; }
    public function get_row($sql) { $this->abfragen++; return null; }
    public function insert($t, $d) { return 1; }
    public function update($t, $d, $w) { return 1; }
    public function delete($t, $w, $f = null) { return 1; }
}

if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }

$GLOBALS['wpdb'] = new Test_WPDB();

require_once $plugin_dir . 'includes/class-cbd-classroom.php';

// --- Prüfgerüst -----------------------------------------------------------

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

function ruf_statisch($klasse, $methode, $args = array()) {
    if (!method_exists($klasse, $methode)) {
        return '### METHODE FEHLT: ' . $klasse . '::' . $methode;
    }
    return call_user_func_array(array($klasse, $methode), $args);
}

echo "== Zerlegung der container_id ==\n";
$faelle = array(
    'cbd-123-abcd'       => 'cbd-123-abcd',
    'cbd-123-abcd:p0'    => 'cbd-123-abcd',
    'cbd-123-abcd:p12'   => 'cbd-123-abcd',
    'cbd-123-abcd:px'    => 'cbd-123-abcd:px',   // kein gueltiges Suffix
    'cbd-123:p1:p2'      => 'cbd-123:p1',        // nur das letzte Suffix zaehlt
    ''                   => '',
);
foreach ($faelle as $ein => $soll) {
    $ist = ruf_statisch('CBD_Classroom', 'basis_container_id', array($ein));
    check(var_export($ein, true) . ' -> ' . var_export($soll, true), $soll === $ist, $ist);
}

echo "\n== Behandelte Container einer Seite ==\n";
$wpdb = $GLOBALS['wpdb'];
$wpdb->zeilen = array(
    array('class_id' => 4, 'page_id' => 31, 'container_id' => 'cbd-aaa',      'is_behandelt' => 1),
    array('class_id' => 4, 'page_id' => 31, 'container_id' => 'cbd-bbb:p0',   'is_behandelt' => 1),
    array('class_id' => 4, 'page_id' => 31, 'container_id' => 'cbd-bbb:p1',   'is_behandelt' => 1),
    array('class_id' => 4, 'page_id' => 31, 'container_id' => 'cbd-ccc',      'is_behandelt' => 0),
    array('class_id' => 9, 'page_id' => 31, 'container_id' => 'cbd-fremd',    'is_behandelt' => 1),
    array('class_id' => 4, 'page_id' => 99, 'container_id' => 'cbd-andere',   'is_behandelt' => 1),
);

$ist = ruf_statisch('CBD_Classroom', 'behandelte_container', array(4, 31));
if (is_array($ist)) { sort($ist); }
check('mehrseitige Varianten zaehlen einmal', array('cbd-aaa', 'cbd-bbb') === $ist, $ist);

$ist2 = ruf_statisch('CBD_Classroom', 'behandelte_container', array(4, 31));
check('nicht behandelte Container fehlen', is_array($ist2) && !in_array('cbd-ccc', $ist2, true), $ist2);
check('Container fremder Klassen fehlen', is_array($ist2) && !in_array('cbd-fremd', $ist2, true), $ist2);
check('Container anderer Seiten fehlen', is_array($ist2) && !in_array('cbd-andere', $ist2, true), $ist2);

$leer = ruf_statisch('CBD_Classroom', 'behandelte_container', array(7, 31));
check('Klasse ohne Markierungen -> leeres Array', array() === $leer, $leer);

$wpdb->abfragen = 0;
$ungueltig1 = ruf_statisch('CBD_Classroom', 'behandelte_container', array(0, 31));
$ungueltig2 = ruf_statisch('CBD_Classroom', 'behandelte_container', array(4, 0));
$ungueltig3 = ruf_statisch('CBD_Classroom', 'behandelte_container', array(-1, -1));
check('class_id 0 -> leeres Array', array() === $ungueltig1, $ungueltig1);
check('page_id 0 -> leeres Array', array() === $ungueltig2, $ungueltig2);
check('negative Werte -> leeres Array', array() === $ungueltig3, $ungueltig3);
check('dabei GAR KEINE Abfrage', 0 === $wpdb->abfragen, $wpdb->abfragen);

echo "\n== Die Regel steht nur einmal im Code ==\n";
$quelle = file_get_contents($plugin_dir . 'includes/class-cbd-classroom.php');
$treffer = preg_match_all('/:p\(\\\\d\+\)|:p\(\[0-9\]\+\)/', $quelle, $m);
check(
    'Suffix-Regel :pN genau einmal als regulaerer Ausdruck',
    1 === $treffer,
    $treffer
);

// =========================================================================
// AP-2.2: Klassensitzung und Theme-Filter
// =========================================================================

require_once $plugin_dir . 'includes/class-cbd-classroom-gate.php';

/** Setzt URL-Parameter, Transients und verwirft die gemerkte Sitzung. */
function sitzung_setzen($classroom, $token, $transient = null) {
    $_GET = array();
    if (null !== $classroom) { $_GET['classroom'] = $classroom; }
    if (null !== $token)     { $_GET['token']     = $token; }

    $GLOBALS['test_transients'] = array();
    if (null !== $transient) {
        $GLOBALS['test_transients']['cbd_classroom_' . $token] = $transient;
    }
    if (method_exists('CBD_Classroom_Gate', 'sitzung_vergessen')) {
        CBD_Classroom_Gate::sitzung_vergessen();
    }
}

$gueltig = array('class_id' => 4, 'class_name' => 'Testklasse', 'created' => time());

echo "\n== Klassensitzung erkennen ==\n";
$GLOBALS['test_options']['cbd_classroom_enabled'] = 1;

sitzung_setzen(null, null);
check('1 · ohne Parameter -> keine Sitzung', null === ruf_statisch('CBD_Classroom_Gate', 'sitzung'), ruf_statisch('CBD_Classroom_Gate', 'sitzung'));

sitzung_setzen(4, 'abc');   // kein Transient
check('2 · Token abgelaufen -> keine Sitzung', null === ruf_statisch('CBD_Classroom_Gate', 'sitzung'));

sitzung_setzen(9, 'abc', $gueltig);   // Transient zeigt auf Klasse 4
check('3 · classroom passt nicht zum Token -> keine Sitzung', null === ruf_statisch('CBD_Classroom_Gate', 'sitzung'));

sitzung_setzen(4, 'abc', $gueltig);
$s = ruf_statisch('CBD_Classroom_Gate', 'sitzung');
check('4 · gueltige Sitzung', is_array($s) && 4 === ($s['class_id'] ?? null), $s);

$GLOBALS['test_options']['cbd_classroom_enabled'] = 0;
sitzung_setzen(4, 'abc', $gueltig);
check('5 · Klassensystem abgeschaltet -> keine Sitzung', null === ruf_statisch('CBD_Classroom_Gate', 'sitzung'));
$GLOBALS['test_options']['cbd_classroom_enabled'] = 1;

echo "\n== Der Theme-Filter simple_clean_lehrerseite_freigeben ==\n";
$gate = CBD_Classroom_Gate::get_instance();
$wpdb->zeilen = array(
    array('class_id' => 4, 'page_id' => 31, 'container_id' => 'cbd-aaa', 'is_behandelt' => 1),
    array('class_id' => 4, 'page_id' => 40, 'container_id' => 'cbd-zzz', 'is_behandelt' => 0),
);

sitzung_setzen(null, null);
check('6 · ohne Sitzung bleibt gesperrt', false === $gate->seite_freigeben(false, 31));

sitzung_setzen(4, 'abc', $gueltig);
check('7 · Seite MIT behandelten Containern wird freigegeben', true === $gate->seite_freigeben(false, 31));

sitzung_setzen(4, 'abc', $gueltig);
check('8 · Seite OHNE behandelte Container bleibt gesperrt', false === $gate->seite_freigeben(false, 40));

sitzung_setzen(4, 'abc', $gueltig);
check('9 · Seite ganz ohne Eintraege bleibt gesperrt', false === $gate->seite_freigeben(false, 99));

sitzung_setzen(null, null);
check('10 · ein bereits freigegebener Wert bleibt true', true === $gate->seite_freigeben(true, 31));

$GLOBALS['test_options']['cbd_classroom_enabled'] = 0;
sitzung_setzen(4, 'abc', $gueltig);
check('11 · Klassensystem abgeschaltet -> keine Freigabe', false === $gate->seite_freigeben(false, 31));
$GLOBALS['test_options']['cbd_classroom_enabled'] = 1;

// =========================================================================
// AP-2.3: Auswahl der Blöcke für die serverseitige Reduktion
// =========================================================================

echo "\n== Welche Bloecke bleiben stehen ==\n";

/** Baut einen Blockeintrag, wie parse_blocks() ihn liefert. */
function block($name, $attrs = array(), $innerHTML = '') {
    return array(
        'blockName'    => $name,
        'attrs'        => $attrs,
        'innerBlocks'  => array(),
        'innerHTML'    => $innerHTML,
        'innerContent' => array($innerHTML),
    );
}

$frei = array('cbd-aaa', 'cbd-html');

$container_frei = block('container-block-designer/container', array('stableId' => 'cbd-aaa'), '<div>frei</div>');
$container_zu   = block('container-block-designer/container', array('stableId' => 'cbd-bbb'), '<div>gesperrt</div>');
$container_ohne = block('container-block-designer/container', array(), '<div>ohne Kennung</div>');
$container_alt  = block('container-block-designer/container', array(), '<div data-stable-id="cbd-html">Altbestand</div>');
$absatz         = block('core/paragraph', array(), '<p>frei stehend</p>');
$ueberschrift   = block('core/heading', array(), '<h2>frei stehend</h2>');
$rohes_html     = block(null, array(), "\n\n");

check('1a · freigegebener Container bleibt', true === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($container_frei, $frei)));
check('1b · nicht freigegebener Container faellt', false === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($container_zu, $frei)));
check('2 · stableId NUR im HTML wird erkannt (Altbestand)', true === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($container_alt, $frei)));
check('3a · freistehender Absatz faellt', false === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($absatz, $frei)));
check('3b · freistehende Ueberschrift faellt', false === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($ueberschrift, $frei)));
check('4 · Container ohne stableId faellt', false === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($container_ohne, $frei)));
check('6 · bei leerer Freigabeliste faellt auch der Container', false === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($container_frei, array())));
check('7 · Eintrag ohne blockName faellt', false === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($rohes_html, $frei)));

// 5 · verschachtelte Bloecke: der Container bleibt als GANZES stehen
$container_verschachtelt = array(
    'blockName'   => 'container-block-designer/container',
    'attrs'       => array('stableId' => 'cbd-aaa'),
    'innerBlocks' => array(block('core/paragraph', array(), '<p>innen</p>')),
    'innerHTML'   => '<div></div>',
    'innerContent'=> array('<div>', null, '</div>'),
);
check('5 · Container mit verschachtelten Bloecken bleibt', true === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($container_verschachtelt, $frei)));

// Ein Container eines anderen Designs (eigener Blocktyp) zaehlt ebenfalls
$container_variante = block('container-block-designer/infotext-k1', array('stableId' => 'cbd-aaa'), '<div>x</div>');
check('8 · auch andere container-block-designer/* zaehlen', true === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($container_variante, $frei)));

// Ein fremder Blocktyp mit passender stableId zaehlt NICHT
$fremd = block('modular-blocks/accordion', array('stableId' => 'cbd-aaa'), '<div>x</div>');
check('9 · fremder Blocktyp mit passender Kennung faellt', false === ruf_statisch('CBD_Classroom_Gate', 'block_erlaubt', array($fremd, $frei)));

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
