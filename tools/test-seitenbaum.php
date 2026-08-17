<?php
/**
 * Standalone-Harness fuer AP-3.1 (Vertrag A und Vertrag B) — laeuft OHNE
 * WordPress.
 *
 * Geprueft werden zwei Dinge:
 *
 *  1. Vertrag A: `cbd/v1/blocks` bekommt drei zusaetzliche Felder
 *     (`postParent`, `menuOrder`, `postType`) — die acht bestehenden Felder
 *     bleiben unveraendert, die Antwort bleibt eine nackte Liste.
 *  2. Vertrag B: die neue Route `cbd/v1/seitenbaum` baut aus einer flachen
 *     Zeilenliste (wie sie `$wpdb->get_results()` liefert) einen Baum
 *     (`knoten`, `kinder`, `wurzeln`).
 *
 * Fachliches Vorbild fuer den Baumaufbau: Theme/includes/page-index.php,
 * Funktion `simple_clean_page_index_daten()` (Zeilen 135-249) — Breitensuche
 * ab Wurzel 0 loest drei Probleme auf einmal: Tiefen ohne erneute Aufloesung
 * der Elternkette, verwaiste Knoten fallen samt Unterbaum heraus, Zyklen
 * sind von der Wurzel aus unerreichbar.
 *
 * Aufruf:  php tools/test-seitenbaum.php
 *
 * Liegt unter tools/ und ist damit NICHT im Verteilungs-ZIP enthalten
 * (create-plugin-zip.js listet nur admin, assets, blocks, includes, vendor,
 * languages).
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur ueber die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');

$plugin_dir = str_replace('\\', '/', dirname(__DIR__)) . '/';
define('CBD_PLUGIN_DIR', $plugin_dir);
define('CBD_PLUGIN_URL', 'https://example.test/plugins/cdb/');
define('CBD_VERSION', 'test');

// --- WordPress-Stubs ------------------------------------------------------

/** Registrierte Routen sammeln, damit die Route selbst pruefbar ist. */
function register_rest_route($namespace, $route, $args = array(), $override = false) {
    $GLOBALS['test_routes'][] = array(
        'namespace' => $namespace,
        'route'     => $route,
        'args'      => $args,
    );
    return true;
}

function add_action($t, $c, $p = 10, $a = 1) { return true; }
function current_user_can($c) { return true; }
function get_permalink($id) { return 'https://example.test/?p=' . (int) $id; }

/**
 * Beitraege kommen aus einer Tabelle im Test — `get_posts()` wird fuer
 * Vertrag A gebraucht (Konsument von `get_cbd_blocks()`), stellt aber genau
 * die Argumente bereit, mit denen der Aufruf geschah, damit die Sortierung
 * (`orderby`) pruefbar ist.
 */
function get_posts($args = array()) {
    $GLOBALS['test_get_posts_args'][] = $args;
    return $GLOBALS['test_posts'];
}

/** Die geparste Blockstruktur liegt je Inhalt vorbereitet bereit. */
function parse_blocks($content) {
    return $GLOBALS['test_parsed'][(string) $content] ?? array();
}

/** Zaehlt Aufrufe — fuer AK6 (Abfragenzahl unabhaengig von der Seitenzahl). */
function update_meta_cache($type, $ids) {
    $GLOBALS['test_meta_cache_aufrufe']++;
    return true;
}

class WP_REST_Response {
    private $daten;
    private $status;
    public function __construct($daten = null, $status = 200) {
        $this->daten  = $daten;
        $this->status = $status;
    }
    public function get_data() { return $this->daten; }
    public function get_status() { return $this->status; }
}

class WP_REST_Request {
    private $params;
    public function __construct($params = array()) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}

/**
 * Minimales $wpdb-Doppel. `get_results()` liefert vorbereitete Zeilen und
 * zaehlt ihre Aufrufe mit — fuer AK6 muss das konstant bei EINEM Aufruf pro
 * `get_seitenbaum()`-Anfrage bleiben, unabhaengig von der Seitenzahl.
 */
class Test_WPDB {
    public $prefix = 'wp_';
    public $posts  = 'wp_posts';
    public $abfragen = 0;
    private $letzte_sql = '';

    public function get_results($sql) {
        $this->abfragen++;
        $this->letzte_sql = $sql;
        return $GLOBALS['test_wpdb_zeilen'];
    }

    public function letzte_sql() {
        return $this->letzte_sql;
    }
}

$GLOBALS['wpdb'] = new Test_WPDB();

$GLOBALS['test_routes']            = array();
$GLOBALS['test_posts']             = array();
$GLOBALS['test_parsed']            = array();
$GLOBALS['test_get_posts_args']    = array();
$GLOBALS['test_wpdb_zeilen']       = array();
$GLOBALS['test_meta_cache_aufrufe'] = 0;

require_once $plugin_dir . 'includes/class-cbd-blocks-rest-api.php';

// --- Pruefgeruest -----------------------------------------------------------

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

/** Baut ein Beitragsobjekt (fuer Vertrag A / get_cbd_blocks()). */
function beitrag($id, $typ, $inhalt, $titel, $parent = 0, $menu_order = 0) {
    $p = new stdClass();
    $p->ID          = $id;
    $p->post_type   = $typ;
    $p->post_status = 'publish';
    $p->post_content = $inhalt;
    $p->post_title  = $titel;
    $p->post_parent = $parent;
    $p->menu_order  = $menu_order;
    return $p;
}

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

/** Baut eine Zeile, wie $wpdb->get_results() sie fuer den Seitenbaum liefert. */
function zeile($id, $parent, $titel, $menu_order = 0, $typ = 'page') {
    $z = new stdClass();
    $z->ID          = $id;
    $z->post_parent = $parent;
    $z->post_title  = $titel;
    $z->menu_order  = $menu_order;
    $z->post_type   = $typ;
    return $z;
}

// =========================================================================
// 1 - Vertrag A: cbd/v1/blocks bekommt drei neue Felder
// =========================================================================

echo "== Vertrag A: cbd/v1/blocks ==\n";

$GLOBALS['test_parsed']['INHALT-A'] = array(
    block('container-block-designer/container', array(
        'stableId'   => 'cbd-aaa',
        'blockTitle' => 'Grundlagen der IR-Spektroskopie',
    ), '<div>A</div>'),
);

$GLOBALS['test_posts'] = array(
    beitrag(45, 'page', 'INHALT-A', 'IR-Spektroskopie', 34, 3),
);

$GLOBALS['test_get_posts_args'] = array();
$antwort = CBD_Blocks_REST_API::get_cbd_blocks(new WP_REST_Request());

check('1.1 - Antwort ist eine nackte Liste (kein Objekt)', is_array($antwort->get_data()) && array_keys($antwort->get_data()) === array(0));

$eintrag = $antwort->get_data()[0];

$erwartete_acht = array('stableId', 'anchor', 'blockId', 'blockTitle', 'postId', 'postTitle', 'postUrl', 'blockType');
$fehlende = array();
foreach ($erwartete_acht as $feld) {
    if (!array_key_exists($feld, $eintrag)) {
        $fehlende[] = $feld;
    }
}
check('1.2 - alle acht bestehenden Felder weiterhin vorhanden', empty($fehlende), $fehlende);

check('1.3 - postParent vorhanden und int', array_key_exists('postParent', $eintrag) && 34 === $eintrag['postParent'] && is_int($eintrag['postParent']), $eintrag['postParent'] ?? null);
check('1.4 - menuOrder vorhanden und int', array_key_exists('menuOrder', $eintrag) && 3 === $eintrag['menuOrder'] && is_int($eintrag['menuOrder']), $eintrag['menuOrder'] ?? null);
check('1.5 - postType vorhanden und string page', array_key_exists('postType', $eintrag) && 'page' === $eintrag['postType'] && is_string($eintrag['postType']), $eintrag['postType'] ?? null);

$argumente = $GLOBALS['test_get_posts_args'][0] ?? array();
check('1.6 - orderby ist menu_order title (Sortierung je Ebene nicht willkuerlich)', 'menu_order title' === ($argumente['orderby'] ?? null), $argumente['orderby'] ?? null);

// Ein Beitrag (post_type "post") bekommt dieselben drei Felder.
$GLOBALS['test_parsed']['INHALT-B'] = array(
    block('container-block-designer/container', array('stableId' => 'cbd-bbb'), '<div>B</div>'),
);
$GLOBALS['test_posts'] = array(
    beitrag(99, 'post', 'INHALT-B', 'Ein Beitrag', 0, 0),
);
$antwort = CBD_Blocks_REST_API::get_cbd_blocks(new WP_REST_Request());
$eintrag = $antwort->get_data()[0];
check('1.7 - postType eines Beitrags ist post', 'post' === ($eintrag['postType'] ?? null), $eintrag['postType'] ?? null);
check('1.8 - postParent 0 bleibt int 0, kein Fatal', 0 === ($eintrag['postParent'] ?? null));

// =========================================================================
// 2 - Route-Registrierung
// =========================================================================

echo "\n== Route-Registrierung ==\n";

$GLOBALS['test_routes'] = array();
CBD_Blocks_REST_API::register_routes();
$routen = $GLOBALS['test_routes'];

check('2.1 - zwei Routen registriert (blocks + seitenbaum)', 2 === count($routen), count($routen));

$seitenbaum_route = null;
foreach ($routen as $r) {
    if ('/seitenbaum' === $r['route']) {
        $seitenbaum_route = $r;
    }
}
check('2.2 - Route /seitenbaum registriert', null !== $seitenbaum_route);
check('2.3 - Namensraum cbd/v1', 'cbd/v1' === ($seitenbaum_route['namespace'] ?? null), $seitenbaum_route['namespace'] ?? null);
check('2.4 - nur GET', 'GET' === ($seitenbaum_route['args']['methods'] ?? null));
check(
    '2.5 - permission_callback ist check_permission (dasselbe Sicherheitsmodell wie /blocks)',
    array('CBD_Blocks_REST_API', 'check_permission') === ($seitenbaum_route['args']['permission_callback'] ?? null),
    $seitenbaum_route['args']['permission_callback'] ?? null
);
check(
    '2.6 - Callback ist get_seitenbaum',
    array('CBD_Blocks_REST_API', 'get_seitenbaum') === ($seitenbaum_route['args']['callback'] ?? null),
    $seitenbaum_route['args']['callback'] ?? null
);

// =========================================================================
// 3 - Quelltext-Zusicherung: kein SELECT *, kein post_content in der Abfrage
// =========================================================================

echo "\n== Quelltext-Zusicherungen (AK3) ==\n";

$quelldatei = $plugin_dir . 'includes/class-cbd-blocks-rest-api.php';
$quelle     = file_get_contents($quelldatei);

// Die Methode isoliert herausschneiden, damit die Pruefung nicht durch
// post_content in get_cbd_blocks() (dort legitim: parse_blocks($post->post_content))
// verfaelscht wird.
$start = strpos($quelle, 'function get_seitenbaum');
check('3.0 - Vorbedingung: get_seitenbaum() existiert im Quelltext', false !== $start);

$naechste_methode = false !== $start ? strpos($quelle, 'function ', $start + 20) : false;
$methodenkoerper  = (false !== $start)
    ? substr($quelle, $start, (false !== $naechste_methode ? $naechste_methode - $start : null))
    : '';

check('3.1 - fuenf Spalten einzeln benannt (ID, post_parent, post_title, menu_order, post_type)',
    false !== strpos($methodenkoerper, 'ID') &&
    false !== strpos($methodenkoerper, 'post_parent') &&
    false !== strpos($methodenkoerper, 'post_title') &&
    false !== strpos($methodenkoerper, 'menu_order') &&
    false !== strpos($methodenkoerper, 'post_type')
);
check('3.2 - kein SELECT * in get_seitenbaum()', false === strpos($methodenkoerper, 'SELECT *'));
check('3.3 - kein post_content in get_seitenbaum()', false === strpos($methodenkoerper, 'post_content'));

// =========================================================================
// 4 - Vertrag B: Baumaufbau (baue_seitenbaum) - Grundfall ueber vier Ebenen
// =========================================================================

echo "\n== Vertrag B: Baumaufbau ueber vier Ebenen ==\n";

// Klasse(12) -> Fach(34) -> Thema(45) -> Seite(50)
$zeilen = array(
    zeile(45, 34, 'IR-Spektroskopie', 0),
    zeile(12, 0,  '4. Klasse', 0),
    zeile(50, 45, 'Uebung 1', 0),
    zeile(34, 12, 'ACH', 1),
);

$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);

check('4.1 - genau vier Knoten', 4 === count($baum['knoten']), array_keys($baum['knoten']));
check('4.2 - Wurzel ist 12', array(12) === $baum['wurzeln'], $baum['wurzeln']);
check('4.3 - Tiefe von 12 ist 0', 0 === ($baum['knoten'][12]['tiefe'] ?? null));
check('4.4 - Tiefe von 34 ist 1', 1 === ($baum['knoten'][34]['tiefe'] ?? null));
check('4.5 - Tiefe von 45 ist 2', 2 === ($baum['knoten'][45]['tiefe'] ?? null));
check('4.6 - Tiefe von 50 ist 3', 3 === ($baum['knoten'][50]['tiefe'] ?? null));
check('4.7 - kinder[0] ist [12]', array(12) === ($baum['kinder'][0] ?? null), $baum['kinder'][0] ?? null);
check('4.8 - kinder[12] ist [34]', array(34) === ($baum['kinder'][12] ?? null), $baum['kinder'][12] ?? null);
check('4.9 - kinder[34] ist [45]', array(45) === ($baum['kinder'][34] ?? null), $baum['kinder'][34] ?? null);
check('4.10 - kinder[45] ist [50]', array(50) === ($baum['kinder'][45] ?? null), $baum['kinder'][45] ?? null);
check('4.11 - Knoten 34 traegt parent 12', 12 === ($baum['knoten'][34]['parent'] ?? null));
check('4.12 - Knoten 34 traegt titel ACH', 'ACH' === ($baum['knoten'][34]['titel'] ?? null));
check('4.13 - Knoten 34 traegt typ page', 'page' === ($baum['knoten'][34]['typ'] ?? null));
check('4.14 - Knoten 34 traegt menuOrder 1', 1 === ($baum['knoten'][34]['menuOrder'] ?? null));
check('4.15 - jeder Knoten traegt gesperrt (Theme fehlt hier noch) === false', false === ($baum['knoten'][45]['gesperrt'] ?? null));

// =========================================================================
// 5 - Sortierung: menuOrder vor titel
// =========================================================================

echo "\n== Sortierung: menuOrder vor titel ==\n";

// Drei Kinder derselben Wurzel, absichtlich in einer Reihenfolge angeliefert,
// die der SQL-Sortierung entspraeche (menu_order ASC, dann post_title ASC).
$zeilen = array(
    zeile(1, 0, 'Wurzel'),
    zeile(2, 1, 'Beta', 0),
    zeile(3, 1, 'Alpha', 1),
    zeile(4, 1, 'Gamma', 1),
);
$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
check('5.1 - Reihenfolge folgt der angelieferten (menuOrder, dann titel) Sortierung', array(2, 3, 4) === ($baum['kinder'][1] ?? null), $baum['kinder'][1] ?? null);

// =========================================================================
// 6 - Verwaister Knoten faellt samt Unterbaum heraus
// =========================================================================

echo "\n== Verwaister Knoten (Elternteil ist Entwurf, taucht in der Zeilenliste nicht auf) ==\n";

// 34 ist ein Entwurf und deshalb NICHT in der Zeilenliste (die Abfrage
// filtert auf post_status = publish). 45 ist Kind von 34 und damit verwaist,
// 50 ist Kind von 45 und faellt samt heraus.
$zeilen = array(
    zeile(12, 0, '4. Klasse'),
    zeile(45, 34, 'IR-Spektroskopie'), // Elternteil 34 fehlt (Entwurf)
    zeile(50, 45, 'Uebung 1'),
);
$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
check('6.1 - nur die Wurzel bleibt uebrig', array(12) === array_keys($baum['knoten']), array_keys($baum['knoten']));
check('6.2 - verwaister Knoten fehlt', !isset($baum['knoten'][45]));
check('6.3 - dessen Kind faellt ebenfalls heraus', !isset($baum['knoten'][50]));
check('6.4 - kein Eintrag kinder[34] (34 existiert nicht)', !isset($baum['kinder'][34]));

// =========================================================================
// 7 - Zyklus (A -> B -> A) ist von der Wurzel aus unerreichbar
// =========================================================================

echo "\n== Zyklus A -> B -> A ==\n";

// 1 -> 2 -> 1: keiner der beiden hat eine Wurzel als Vorfahren (post_parent
// von 1 ist 2, von 2 ist 1) - beide fehlen im Ergebnis, kein Endlosschleife.
$zeilen = array(
    zeile(1, 2, 'A'),
    zeile(2, 1, 'B'),
    zeile(3, 0, 'Wurzel'), // eigenstaendige, unbeteiligte Wurzel
);
$start_zeit = microtime(true);
$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
$dauer = microtime(true) - $start_zeit;

check('7.1 - kein Endlosschleife / Aufbau terminiert schnell', $dauer < 2.0, $dauer);
check('7.2 - Zyklus-Knoten 1 fehlt', !isset($baum['knoten'][1]));
check('7.3 - Zyklus-Knoten 2 fehlt', !isset($baum['knoten'][2]));
check('7.4 - unbeteiligte Wurzel 3 bleibt erhalten', isset($baum['knoten'][3]));

// =========================================================================
// 8 - Beitraege (post_type "post") erscheinen nicht im Baum
// =========================================================================

echo "\n== Beitraege erscheinen nicht im Baum ==\n";

$zeilen = array(
    zeile(12, 0, '4. Klasse', 0, 'page'),
    zeile(77, 0, 'Ein Blogbeitrag', 0, 'post'),
);
$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
check('8.1 - nur die Seite steht im Baum', array(12) === array_keys($baum['knoten']), array_keys($baum['knoten']));
check('8.2 - der Beitrag fehlt', !isset($baum['knoten'][77]));
check('8.3 - der Beitrag steht auch nicht in wurzeln', !in_array(77, $baum['wurzeln'], true));

// =========================================================================
// 9 - gesperrt: ohne und mit Theme-Funktion
// =========================================================================

echo "\n== gesperrt: Theme-Funktion fehlt ==\n";

check('9.0 - Vorbedingung: Theme-Funktion existiert wirklich nicht', !function_exists('simple_clean_seite_nur_lehrpersonen'));

$zeilen = array(
    zeile(12, 0, '4. Klasse'),
    zeile(34, 12, 'ACH'),
);
$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
check('9.1 - jedes gesperrt ist false, kein Fatal Error', false === $baum['knoten'][12]['gesperrt'] && false === $baum['knoten'][34]['gesperrt']);

echo "\n== gesperrt: Theme-Funktion vorhanden ==\n";

// eval(), weil PHP eine einmal definierte Funktion nicht wieder vergisst -
// dieser Abschnitt muss deshalb NACH dem "Theme fehlt"-Abschnitt stehen
// (gleiches Vorgehen wie in tools/test-block-content-api.php).
eval('
function simple_clean_seite_nur_lehrpersonen($post_id) {
    return !empty($GLOBALS["test_gesperrt"][(int) $post_id]);
}
');

check('9.2 - Vorbedingung: Theme-Funktion ist jetzt da', function_exists('simple_clean_seite_nur_lehrpersonen'));

$GLOBALS['test_gesperrt'] = array(45 => true);
$zeilen = array(
    zeile(12, 0, '4. Klasse'),
    zeile(34, 12, 'ACH'),
    zeile(45, 34, 'IR-Spektroskopie (gesperrt)'),
);
$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
check('9.3 - gesperrter Knoten traegt gesperrt = true', true === ($baum['knoten'][45]['gesperrt'] ?? null));
check('9.4 - nicht gesperrte Knoten bleiben false', false === ($baum['knoten'][12]['gesperrt'] ?? null) && false === ($baum['knoten'][34]['gesperrt'] ?? null));
// Wichtig: "gesperrt" fragt NUR simple_clean_seite_nur_lehrpersonen() ab,
// nicht simple_clean_seite_sichtbar() - "ist diese Seite fuer Lehrpersonen
// reserviert", nicht "darf der aktuelle Nutzer sie sehen" (Vertrag B).
check('9.5 - Sperrung ist unabhaengig von einer Vererbung ueber Vorfahren (nur die Theme-Funktion selbst entscheidet je Knoten)', true === ($baum['knoten'][45]['gesperrt'] ?? null) && false === ($baum['knoten'][34]['gesperrt'] ?? null));

// =========================================================================
// 10 - AK6: Abfragenzahl konstant, unabhaengig von der Seitenzahl
// =========================================================================

echo "\n== AK6: Datenbankabfragen unabhaengig von der Seitenzahl (2: Seiten + Meta-Cache) ==\n";

$wpdb = $GLOBALS['wpdb'];

// Fall A: fuenf Seiten.
$GLOBALS['test_wpdb_zeilen'] = array(
    zeile(1, 0, 'Wurzel'),
    zeile(2, 1, 'A'),
    zeile(3, 1, 'B'),
    zeile(4, 1, 'C'),
    zeile(5, 1, 'D'),
);
$wpdb->abfragen = 0;
$GLOBALS['test_meta_cache_aufrufe'] = 0;
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
$abfragen_klein = $wpdb->abfragen;
$meta_klein     = $GLOBALS['test_meta_cache_aufrufe'];

check('10.1 - genau eine $wpdb-Abfrage bei fuenf Seiten', 1 === $abfragen_klein, $abfragen_klein);
check('10.2 - genau ein update_meta_cache()-Aufruf bei fuenf Seiten', 1 === $meta_klein, $meta_klein);

// Fall B: fuenfzig Seiten - Abfragenzahl darf sich NICHT aendern.
$viele = array(zeile(100, 0, 'Wurzel-B'));
for ($i = 101; $i <= 150; $i++) {
    $viele[] = zeile($i, 100, 'Seite ' . $i);
}
$GLOBALS['test_wpdb_zeilen'] = $viele;
$wpdb->abfragen = 0;
$GLOBALS['test_meta_cache_aufrufe'] = 0;
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
$abfragen_gross = $wpdb->abfragen;
$meta_gross     = $GLOBALS['test_meta_cache_aufrufe'];

check('10.3 - weiterhin genau eine $wpdb-Abfrage bei fuenfzig Seiten', 1 === $abfragen_gross, $abfragen_gross);
check('10.4 - weiterhin genau ein update_meta_cache()-Aufruf bei fuenfzig Seiten', 1 === $meta_gross, $meta_gross);
check('10.5 - Abfragenzahl ist unabhaengig von der Seitenzahl (5 vs. 50 Seiten gleich)', $abfragen_klein === $abfragen_gross && $meta_klein === $meta_gross);

// =========================================================================
// 11 - get_seitenbaum() liefert die Antwortform aus Vertrag B
// =========================================================================

echo "\n== get_seitenbaum() Antwortform ==\n";

$GLOBALS['test_wpdb_zeilen'] = array(
    zeile(12, 0, '4. Klasse', 0),
    zeile(34, 12, 'ACH', 1),
    zeile(45, 34, 'IR-Spektroskopie', 0),
);
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
$antwort = CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());

check('11.1 - HTTP 200', 200 === $antwort->get_status());
$daten = $antwort->get_data();
check('11.2 - genau die Schluessel knoten, kinder, wurzeln', array('knoten', 'kinder', 'wurzeln') === array_keys($daten), array_keys($daten));
check('11.3 - drei Knoten im Ergebnis', 3 === count($daten['knoten']), array_keys($daten['knoten']));

// Innerhalb derselben Anfrage/desselben Prozesses: zweiter Aufruf liefert
// dasselbe Objekt, ohne die Datenquelle erneut zu befragen.
$wpdb->abfragen = 0;
$antwort2 = CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
check('11.4 - zweiter Aufruf im selben Prozess fragt $wpdb nicht erneut (Memoisierung)', 0 === $wpdb->abfragen, $wpdb->abfragen);
check('11.5 - zweiter Aufruf liefert dasselbe Ergebnis', $antwort2->get_data() === $daten);

// =========================================================================
// 12 - Randfaelle
// =========================================================================

echo "\n== Randfaelle ==\n";

check('12.1 - leere Zeilenliste ergibt leeren Baum, kein Fatal', array('knoten' => array(), 'kinder' => array(), 'wurzeln' => array()) === CBD_Blocks_REST_API::baue_seitenbaum(array()));

$einzeln = CBD_Blocks_REST_API::baue_seitenbaum(array(zeile(1, 0, 'Einsam')));
check('12.2 - einzelner Wurzelknoten ohne Kinder', array(1) === $einzeln['wurzeln'] && !isset($einzeln['kinder'][1]));

// =========================================================================

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
