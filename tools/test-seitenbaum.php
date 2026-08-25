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
check('1.6 - orderby ist title (AP-3.fix3, Befund S5: menu_order title verschlechterte die flache Suchtrefferliste, ohne die Ebenenreihenfolge zu sichern - die liefert Vertrag B ueber kinder)', 'title' === ($argumente['orderby'] ?? null), $argumente['orderby'] ?? null);

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
// AP-3.fix3 - AK4 (S5): orderby bleibt 'title' - Quelltext-Zusicherung
// =========================================================================
//
// 1.6 oben prueft das an get_posts() WEITERGEGEBENE Argument (funktional).
// Diese Quelltext-Zusicherung schuetzt zusaetzlich davor, dass
// 'menu_order title' ein drittes Mal eingefuehrt wird - genau die Falle,
// vor der der Kommentar im Quellcode selbst warnt.

echo "\n== AP-3.fix3 AK4 (S5): orderby-Quelltext in get_cbd_blocks() ==\n";

$quelle_orderby = file_get_contents($plugin_dir . 'includes/class-cbd-blocks-rest-api.php');
$fn_start = strpos($quelle_orderby, 'function get_cbd_blocks(');
$fn_ende  = false !== $fn_start ? strpos($quelle_orderby, 'function find_cbd_blocks_recursive', $fn_start) : false;
$fn_text  = (false !== $fn_start && false !== $fn_ende) ? substr($quelle_orderby, $fn_start, $fn_ende - $fn_start) : '';

check('F3-AK4.1 - Vorbedingung: get_cbd_blocks()-Quelltext isoliert', '' !== $fn_text);
check("F3-AK4.2 - Quelltext bestaetigt orderby => title in get_cbd_blocks()", false !== strpos($fn_text, "'orderby' => 'title'"));
check("F3-AK4.3 - menu_order title steht NICHT mehr in get_cbd_blocks() (Regressionsschutz gegen ein drittes Mal)", false === strpos($fn_text, "'menu_order title'"));

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
// 3 - Quelltext-Zusicherung (AK3): die SQL-Zeichenkette selbst
// =========================================================================

echo "\n== Quelltext-Zusicherungen (AK3) ==\n";

$quelldatei = $plugin_dir . 'includes/class-cbd-blocks-rest-api.php';
$quelle     = file_get_contents($quelldatei);

// AK3 verlangt eine Aussage ueber die SQL-ZEICHENKETTE selbst ("nennt die
// fuenf Spalten einzeln, kein SELECT *"), nicht ueber die ganze Methode samt
// Doc-Kommentaren — sonst wuerde ein erklaerender Kommentar wie "laedt KEIN
// post_content" die Pruefung selbst ausloesen, obwohl die Abfrage korrekt
// ist. Isoliert wird deshalb nur der Aufruf `$wpdb->get_results(...)`.
$aufruf_start = strpos($quelle, '$wpdb->get_results(');
check('3.0 - Vorbedingung: $wpdb->get_results()-Aufruf in get_seitenbaum() gefunden', false !== $aufruf_start);

$aufruf_ende = false !== $aufruf_start ? strpos($quelle, ');', $aufruf_start) : false;
$sql_text    = (false !== $aufruf_start && false !== $aufruf_ende)
    ? substr($quelle, $aufruf_start, $aufruf_ende - $aufruf_start)
    : '';

check('3.1 - fuenf Spalten einzeln benannt (ID, post_parent, post_title, menu_order, post_type)',
    false !== strpos($sql_text, 'ID') &&
    false !== strpos($sql_text, 'post_parent') &&
    false !== strpos($sql_text, 'post_title') &&
    false !== strpos($sql_text, 'menu_order') &&
    false !== strpos($sql_text, 'post_type')
);
check('3.2 - kein SELECT * in der Abfrage', false === strpos($sql_text, 'SELECT *'));
check('3.3 - kein post_content in der Abfrage', false === strpos($sql_text, 'post_content'));

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
check('F1-AK3.0 - Vorbedingung (AP-3.fix1): auch Stufe-1-Funktion simple_clean_gesperrte_seiten_mit_unterbaum() existiert noch nicht - AK3 prueft den Fall, dass BEIDE Theme-Funktionen fehlen', !function_exists('simple_clean_gesperrte_seiten_mit_unterbaum'));

$zeilen = array(
    zeile(12, 0, '4. Klasse'),
    zeile(34, 12, 'ACH'),
);
$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
check('9.1 - jedes gesperrt ist false, kein Fatal Error', false === $baum['knoten'][12]['gesperrt'] && false === $baum['knoten'][34]['gesperrt']);
check('F1-AK3.1 - AK3 (AP-3.fix1) bestaetigt: beide Theme-Funktionen fehlen, gesperrt bleibt ueberall false, kein Fatal Error (identisch zu 9.1, hier fuer AP-3.fix1 explizit benannt)', false === $baum['knoten'][12]['gesperrt'] && false === $baum['knoten'][34]['gesperrt']);

echo "\n== gesperrt: Theme-Funktion vorhanden ==\n";

// eval(), weil PHP eine einmal definierte Funktion nicht wieder vergisst -
// dieser Abschnitt muss deshalb NACH dem "Theme fehlt"-Abschnitt stehen
// (gleiches Vorgehen wie in tools/test-block-content-api.php).
// Zaehler ergaenzt fuer AP-3.fix1 (AK1/AK2/AK5): macht nachweisbar, ob und
// wie oft Stufe 2 (dieser Rueckfall) tatsaechlich aufgerufen wird. Die
// Rueckgabe selbst bleibt unveraendert - keine bestehende Pruefung 9.2-9.5
// wird dadurch beeinflusst.
eval('
function simple_clean_seite_nur_lehrpersonen($post_id) {
    $GLOBALS["test_stufe2_aufrufe"] = ($GLOBALS["test_stufe2_aufrufe"] ?? 0) + 1;
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
// AP-3.fix3 - AK2 (Befund S2), erste Haelfte: Stufe 2 allein
// =========================================================================
//
// An dieser Stelle im Skript existiert ausschliesslich Stufe 2
// (simple_clean_seite_nur_lehrpersonen) - Stufe 1
// (simple_clean_gesperrte_seiten_mit_unterbaum) wird erst weiter unten per
// eval() definiert. Genau der richtige Moment, um die "genau einmal"-Haelfte
// von AK2 zu pruefen: Stufe 2 braucht Post-Meta (get_post_ancestors() intern)
// und muss update_meta_cache() VOR ihrer Schleife genau einmal aufrufen.

echo "\n== AP-3.fix3 AK2 (S2), Teil 1: update_meta_cache() bei Stufe 2 allein ==\n";

check('F3-AK2.0 - Vorbedingung: nur Stufe 2 ist bislang definiert, Stufe 1 noch nicht', function_exists('simple_clean_seite_nur_lehrpersonen') && !function_exists('simple_clean_gesperrte_seiten_mit_unterbaum'));

$GLOBALS['test_meta_cache_aufrufe'] = 0;
$zeilen = array(
    zeile(12, 0, '4. Klasse'),
    zeile(34, 12, 'ACH'),
    zeile(45, 34, 'IR-Spektroskopie (gesperrt)'),
);
CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
check('F3-AK2.1 - genau ein update_meta_cache()-Aufruf, wenn nur Stufe 2 verfuegbar ist', 1 === $GLOBALS['test_meta_cache_aufrufe'], $GLOBALS['test_meta_cache_aufrufe']);

// =========================================================================
// AP-3.fix1 - AK1 + AK4: Stufe 1 (simple_clean_gesperrte_seiten_mit_unterbaum)
// =========================================================================
//
// Befund des Orchestrators bei der Abnahme von AP-3.1: Die bisherige
// Ermittlung ruft simple_clean_seite_nur_lehrpersonen() JE SEITE auf; diese
// Funktion durchsucht bei mindestens einer gesperrten Seite ueber
// get_post_ancestors() die Elternkette - der rohe $wpdb-Aufbau oben fuellt
// den WordPress-Post-Cache nicht, auf 258 Seiten entstehen so potenziell
// hunderte Einzelabfragen. Das Theme haelt fuer genau diesen Fall bereits
// eine memoisierte Nachschlagekarte bereit
// (Theme/includes/sichtbarkeit.php:142-201), die den gesamten Unterbaum
// gesperrter Seiten in hoechstens zwei Abfragen liefert.

echo "\n== AP-3.fix1 AK1/AK4: Stufe 1 (simple_clean_gesperrte_seiten_mit_unterbaum) vorhanden ==\n";

check('F1-AK1.0 - Vorbedingung: Stufe-1-Funktion existiert noch nicht', !function_exists('simple_clean_gesperrte_seiten_mit_unterbaum'));

// Stub simuliert die vom Theme gelieferte, bereits vererbte Karte: Schluessel
// sind ALLE gesperrten Seiten EINSCHLIESSLICH ihres gesamten Unterbaums (so
// wie Theme/includes/sichtbarkeit.php:142-201 sie tatsaechlich liefert). Ein
// Zaehler macht AK1 pruefbar (Stufe 1 darf je Anfrage nur EINMAL aufgerufen
// werden).
eval('
function simple_clean_gesperrte_seiten_mit_unterbaum() {
    $GLOBALS["test_stufe1_aufrufe"] = ($GLOBALS["test_stufe1_aufrufe"] ?? 0) + 1;
    return $GLOBALS["test_gesperrt_mit_unterbaum"] ?? array();
}
');

check('F1-AK1.1 - Vorbedingung: Stufe-1-Funktion ist jetzt da', function_exists('simple_clean_gesperrte_seiten_mit_unterbaum'));

// Klasse(12) -> Fach(34) -> Thema(45, GESPERRT) -> Uebung(50, KEINE eigene
// Meta - erbt die Sperre ausschliesslich ueber die von Stufe 1 bereits
// vorberechnete Karte). Genau dieser Fall (AK4) fehlte im Harnisch von
// AP-3.1 vollstaendig - er ist der eigentliche Grund, warum der Irrtum in
// der Uebergabenotiz ("Theme-Funktion vererbt die Sperre nicht") nicht
// aufgefallen ist. Sie vererbt sie (sichtbarkeit.php:229-233).
$GLOBALS['test_gesperrt_mit_unterbaum'] = array(45 => true, 50 => true);
$zeilen = array(
    zeile(12, 0, '4. Klasse'),
    zeile(34, 12, 'ACH'),
    zeile(45, 34, 'IR-Spektroskopie (gesperrt)'),
    zeile(50, 45, 'Uebung 1 (erbt die Sperre, keine eigene Meta)'),
);

$GLOBALS['test_stufe1_aufrufe'] = 0;
$stufe2_vor_f1_ak1 = $GLOBALS['test_stufe2_aufrufe'] ?? 0;

$baum = CBD_Blocks_REST_API::baue_seitenbaum($zeilen);

check('F1-AK1.2 - Stufe 1 wird genau einmal je Anfrage aufgerufen', 1 === $GLOBALS['test_stufe1_aufrufe'], $GLOBALS['test_stufe1_aufrufe']);
check('F1-AK1.3 - Stufe 2 wird dabei GAR NICHT aufgerufen', $stufe2_vor_f1_ak1 === ($GLOBALS['test_stufe2_aufrufe'] ?? 0), $GLOBALS['test_stufe2_aufrufe'] ?? null);
check('F1-AK4.1 - direkt gesperrter Knoten (45) traegt gesperrt = true', true === ($baum['knoten'][45]['gesperrt'] ?? null));
check('F1-AK4.2 - Unterseite OHNE eigene Meta (50) erbt gesperrt = true ueber die Karte aus Stufe 1', true === ($baum['knoten'][50]['gesperrt'] ?? null));
check('F1-AK4.3 - unbeteiligte Knoten (12, 34) bleiben false', false === ($baum['knoten'][12]['gesperrt'] ?? null) && false === ($baum['knoten'][34]['gesperrt'] ?? null));

echo "\n== AP-3.fix1 AK2: Stufe 2 bleibt unveraendertes Rueckfallverhalten, wenn Stufe 1 fehlt ==\n";

// Die Pruefungen 9.2-9.5 weiter oben liefen, BEVOR Stufe 1 in diesem Skript
// definiert wurde - zu diesem Zeitpunkt war ausschliesslich Stufe 2
// verfuegbar und wurde tatsaechlich als Rueckfall benutzt (Zaehler > 0).
// Damit ist AK2 ("gleiche Ergebnisse wie AP-3.1, solange nur Stufe 2
// existiert") durch 9.2-9.5 bereits nachgewiesen; dieser Zaehler macht die
// Benutzung von Stufe 2 zusaetzlich explizit sichtbar.
check('F1-AK2.1 - Stufe 2 wurde tatsaechlich als Rueckfall benutzt, solange Stufe 1 nicht existierte', ($GLOBALS['test_stufe2_aufrufe'] ?? 0) > 0, $GLOBALS['test_stufe2_aufrufe'] ?? null);

// =========================================================================
// AP-3.fix3 - AK2 (Befund S2), zweite Haelfte: Stufe 1 vorhanden
// =========================================================================
//
// Ab hier ist Stufe 1 (simple_clean_gesperrte_seiten_mit_unterbaum) dauerhaft
// definiert - PHP vergisst eine einmal definierte Funktion nicht wieder. Die
// zweite Haelfte von AK2: Stufe 1 braucht keine Post-Meta (sie nutzt die vom
// Theme bereits vorberechnete Karte), update_meta_cache() darf hier GAR NICHT
// mehr aufgerufen werden. Vorher (Befund S2) lief der Aufruf unbedingt VOR
// der Verzweigung und lud damit bei jedem Editor-Aufruf unnoetig alle
// Postmeta aller Seiten in den Objektcache, obwohl Stufe 1 sie nie liest.

echo "\n== AP-3.fix3 AK2 (S2), Teil 2: KEIN update_meta_cache()-Aufruf bei Stufe 1 ==\n";

check('F3-AK2.2 - Vorbedingung: Stufe 1 ist jetzt definiert', function_exists('simple_clean_gesperrte_seiten_mit_unterbaum'));

$GLOBALS['test_meta_cache_aufrufe'] = 0;
$zeilen = array(
    zeile(12, 0, '4. Klasse'),
    zeile(34, 12, 'ACH'),
    zeile(45, 34, 'IR-Spektroskopie (gesperrt)'),
);
CBD_Blocks_REST_API::baue_seitenbaum($zeilen);
check('F3-AK2.3 - kein update_meta_cache()-Aufruf, wenn Stufe 1 verfuegbar ist', 0 === $GLOBALS['test_meta_cache_aufrufe'], $GLOBALS['test_meta_cache_aufrufe']);

// =========================================================================
// 10 - AK6: Abfragenzahl konstant, unabhaengig von der Seitenzahl
// =========================================================================

echo "\n== AK6: Datenbankabfragen unabhaengig von der Seitenzahl ==\n";

// Umformuliert fuer AP-3.fix3 (Befund S2): Ab hier im Skript ist Stufe 1
// (simple_clean_gesperrte_seiten_mit_unterbaum) bereits per eval() definiert
// (siehe Abschnitt "AP-3.fix1 AK1/AK4" weiter oben) und wird von
// baue_seitenbaum() bevorzugt verwendet. Vor der Behebung von S2 rief der
// Code update_meta_cache() unbedingt VOR der Verzweigung auf, weshalb hier
// vorher "genau ein Aufruf" stand, obwohl Stufe 1 gar keine Post-Meta liest.
// Seit der Behebung entsteht in diesem Kontext (Stufe 1 aktiv) GAR KEIN
// update_meta_cache()-Aufruf mehr - die eigentliche "genau einmal in Stufe
// 2"-Haelfte von AK2 pruefen die neuen Faelle F3-AK2.0/F3-AK2.1 weiter oben,
// wo im Skript noch ausschliesslich Stufe 2 existiert.
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
check('10.2 - kein update_meta_cache()-Aufruf bei fuenf Seiten (Stufe 1 ist hier bereits aktiv und braucht keine Post-Meta - AP-3.fix3, Befund S2)', 0 === $meta_klein, $meta_klein);

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
check('10.4 - weiterhin kein update_meta_cache()-Aufruf bei fuenfzig Seiten (Stufe 1 aktiv - AP-3.fix3, Befund S2)', 0 === $meta_gross, $meta_gross);
check('10.5 - Abfragenzahl ist unabhaengig von der Seitenzahl (5 vs. 50 Seiten gleich)', $abfragen_klein === $abfragen_gross && $meta_klein === $meta_gross);

// =========================================================================
// AP-3.fix1 - AK5: Abfragenzahl bleibt konstant, AUCH wenn die Sperrpruefung
// mitgezaehlt wird. AK6 aus AP-3.1 (Abschnitt 10 oben) war zu schwach
// formuliert: Der dortige Harnisch stubbt simple_clean_seite_nur_lehrpersonen()
// und zaehlt deshalb nur die Abfragen des Plugins, nie die Aufrufe der
// Theme-Funktion selbst - und in Abschnitt 10 ist ausserdem KEINE Seite
// gesperrt. Dieser Abschnitt zaehlt beide Theme-Funktionen mit UND es gibt
// in jedem Durchlauf mindestens eine gesperrte Seite (5 und 50 Seiten).
//
// Umformuliert fuer AP-3.fix3 (Befund S2): Dieser gesamte Abschnitt laeuft
// ausschliesslich im Stufe-1-Zweig (F1-AK5.4/.8 bestaetigen "Stufe 2 dabei
// gar nicht aufgerufen"). Seit der Behebung von S2 entsteht hier deshalb
// KEIN update_meta_cache()-Aufruf mehr (vorher: unbedingt "genau einer", weil
// der Aufruf vor der Verzweigung stand). Die "genau einmal in Stufe
// 2"-Haelfte von AK2 pruefen F3-AK2.0/F3-AK2.1 weiter oben.
// =========================================================================

echo "\n== AP-3.fix1 AK5: Abfragenzahl inkl. Sperrpruefung (Stufe 1) unabhaengig von der Seitenzahl ==\n";

// Fall A: fuenf Seiten, mindestens eine davon gesperrt.
$GLOBALS['test_gesperrt_mit_unterbaum'] = array(202 => true);
$GLOBALS['test_wpdb_zeilen'] = array(
    zeile(201, 0, 'Wurzel-F1-AK5'),
    zeile(202, 201, 'A (gesperrt)'),
    zeile(203, 201, 'B'),
    zeile(204, 201, 'C'),
    zeile(205, 201, 'D'),
);
$wpdb->abfragen = 0;
$GLOBALS['test_meta_cache_aufrufe'] = 0;
$GLOBALS['test_stufe1_aufrufe'] = 0;
$stufe2_vor_f1_ak5_klein = $GLOBALS['test_stufe2_aufrufe'] ?? 0;
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());

$abfragen_klein_f1  = $wpdb->abfragen;
$meta_klein_f1      = $GLOBALS['test_meta_cache_aufrufe'];
$stufe1_klein_f1    = $GLOBALS['test_stufe1_aufrufe'];
$stufe2_delta_klein = ($GLOBALS['test_stufe2_aufrufe'] ?? 0) - $stufe2_vor_f1_ak5_klein;

check('F1-AK5.1 - genau eine $wpdb-Abfrage bei fuenf Seiten (inkl. gesperrter Seite)', 1 === $abfragen_klein_f1, $abfragen_klein_f1);
check('F1-AK5.2 - kein update_meta_cache()-Aufruf bei fuenf Seiten (Stufe 1 aktiv - AP-3.fix3, Befund S2)', 0 === $meta_klein_f1, $meta_klein_f1);
check('F1-AK5.3 - Stufe 1 genau einmal aufgerufen bei fuenf Seiten', 1 === $stufe1_klein_f1, $stufe1_klein_f1);
check('F1-AK5.4 - Stufe 2 dabei gar nicht aufgerufen', 0 === $stufe2_delta_klein, $stufe2_delta_klein);

// Fall B: fuenfzig Seiten, ebenfalls mit mindestens einer gesperrten Seite -
// keine der Zahlen darf sich aendern.
$GLOBALS['test_gesperrt_mit_unterbaum'] = array(301 => true);
$viele_f1_ak5 = array(zeile(300, 0, 'Wurzel-F1-AK5-gross'));
for ($i = 301; $i <= 350; $i++) {
    $viele_f1_ak5[] = zeile($i, 300, 'Seite ' . $i);
}
$GLOBALS['test_wpdb_zeilen'] = $viele_f1_ak5;
$wpdb->abfragen = 0;
$GLOBALS['test_meta_cache_aufrufe'] = 0;
$GLOBALS['test_stufe1_aufrufe'] = 0;
$stufe2_vor_f1_ak5_gross = $GLOBALS['test_stufe2_aufrufe'] ?? 0;
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());

$abfragen_gross_f1  = $wpdb->abfragen;
$meta_gross_f1      = $GLOBALS['test_meta_cache_aufrufe'];
$stufe1_gross_f1    = $GLOBALS['test_stufe1_aufrufe'];
$stufe2_delta_gross = ($GLOBALS['test_stufe2_aufrufe'] ?? 0) - $stufe2_vor_f1_ak5_gross;

check('F1-AK5.5 - weiterhin genau eine $wpdb-Abfrage bei fuenfzig Seiten (inkl. gesperrter Seite)', 1 === $abfragen_gross_f1, $abfragen_gross_f1);
check('F1-AK5.6 - weiterhin kein update_meta_cache()-Aufruf bei fuenfzig Seiten (Stufe 1 aktiv - AP-3.fix3, Befund S2)', 0 === $meta_gross_f1, $meta_gross_f1);
check('F1-AK5.7 - Stufe 1 weiterhin genau einmal aufgerufen bei fuenfzig Seiten', 1 === $stufe1_gross_f1, $stufe1_gross_f1);
check('F1-AK5.8 - Stufe 2 weiterhin gar nicht aufgerufen', 0 === $stufe2_delta_gross, $stufe2_delta_gross);
check('F1-AK5.9 - Abfragen-/Aufrufzahlen sind unabhaengig von der Seitenzahl (5 vs. 50 Seiten gleich)', $abfragen_klein_f1 === $abfragen_gross_f1 && $meta_klein_f1 === $meta_gross_f1 && $stufe1_klein_f1 === $stufe1_gross_f1);

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
// (array)-Cast statt direktem array-Zugriff/count(): $daten['knoten'] ist seit
// AP-3.fix3 (Befund S1) im tatsaechlichen REST-Ergebnis ein stdClass-Objekt
// (siehe die AK1-Faelle F3-AK1.* unten), damit json_encode() daraus zuverlaessig
// ein JSON-Objekt macht. count()/array_keys() auf einem stdClass wuerde in
// PHP 8 einen TypeError werfen. (array) liefert in beiden Faellen (Array wie
// bisher, stdClass wie kuenftig) dieselben Elemente - die Pruefung bleibt also
// unabhaengig davon bestehen, ob S1 schon implementiert ist.
check('11.3 - drei Knoten im Ergebnis', 3 === count((array) $daten['knoten']), array_keys((array) $daten['knoten']));

// Innerhalb derselben Anfrage/desselben Prozesses: zweiter Aufruf liefert
// dasselbe Objekt, ohne die Datenquelle erneut zu befragen.
$wpdb->abfragen = 0;
$antwort2 = CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
check('11.4 - zweiter Aufruf im selben Prozess fragt $wpdb nicht erneut (Memoisierung)', 0 === $wpdb->abfragen, $wpdb->abfragen);
check('11.5 - zweiter Aufruf liefert dasselbe Ergebnis', $antwort2->get_data() === $daten);

// =========================================================================
// AP-3.fix3 - AK1 (Befund S1): knoten und kinder sind IMMER JSON-Objekte
// =========================================================================
//
// json_encode() eines PHP-Arrays mit den Schluesseln 0..n-1 ergibt eine
// JSON-Liste statt eines Objekts. Gemessen im Review: eine rein flache
// Seitenmenge (nur Wurzeln) hat in `kinder` ausschliesslich den Schluessel 0
// - {"kinder":[[...]]}, eine Liste. block-auswahl.js (normalisiereBaum)
// verwirft eine solche Liste stillschweigend und ersetzt sie durch {}.
//
// Geprueft wird bewusst die SERIALISIERUNG (json_encode() + json_decode()
// OHNE den assoziativ-Modus), nicht nur das PHP-Array: json_decode() ohne
// zweiten Parameter dekodiert ein JSON-Objekt als stdClass, eine JSON-Liste
// bleibt ein Array - genau der Unterschied, um den es hier geht. Mit
// json_decode(..., true) waeren beide Faelle nicht mehr unterscheidbar und
// die Pruefung wertlos.

echo "\n== AP-3.fix3 AK1 (S1): Serialisierung liefert JSON-Objekte fuer knoten und kinder ==\n";

// Fall 1: flache Seitenmenge (nur Wurzeln) - der im Review gemessene Fall.
$GLOBALS['test_wpdb_zeilen'] = array(
    zeile(43, 0, 'A'),
    zeile(44, 0, 'B'),
    zeile(45, 0, 'C'),
);
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
$antwort_json = CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
$json = json_encode($antwort_json->get_data());
$dekodiert = json_decode($json);
check('F3-AK1.1 - flache Seitenmenge: kinder ist ein JSON-Objekt', is_object($dekodiert->kinder), $json);
check('F3-AK1.2 - flache Seitenmenge: knoten ist ein JSON-Objekt', is_object($dekodiert->knoten), $json);

// Fall 2: hierarchische Seitenmenge (Regelfall).
$GLOBALS['test_wpdb_zeilen'] = array(
    zeile(12, 0, '4. Klasse', 0),
    zeile(34, 12, 'ACH', 1),
    zeile(45, 34, 'IR-Spektroskopie', 0),
);
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
$antwort_json = CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
$json = json_encode($antwort_json->get_data());
$dekodiert = json_decode($json);
check('F3-AK1.3 - hierarchische Seitenmenge: kinder ist ein JSON-Objekt', is_object($dekodiert->kinder), $json);
check('F3-AK1.4 - hierarchische Seitenmenge: knoten ist ein JSON-Objekt', is_object($dekodiert->knoten), $json);

// Fall 3: einzelne Wurzel ohne Kinder - kinder hat wie in Fall 1 nur den
// Schluessel 0.
$GLOBALS['test_wpdb_zeilen'] = array(
    zeile(5, 0, 'Einsame Wurzel'),
);
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
$antwort_json = CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
$json = json_encode($antwort_json->get_data());
$dekodiert = json_decode($json);
check('F3-AK1.5 - einzelne Wurzel: kinder ist ein JSON-Objekt', is_object($dekodiert->kinder), $json);
check('F3-AK1.6 - einzelne Wurzel: knoten ist ein JSON-Objekt', is_object($dekodiert->knoten), $json);

// Fall 4: leerer Baum - beide sind leere PHP-Arrays, json_encode() eines
// leeren Arrays ergibt IMMER "[]", nie "{}", unabhaengig von den Schluesseln.
$GLOBALS['test_wpdb_zeilen'] = array();
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
$antwort_json = CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
$json = json_encode($antwort_json->get_data());
$dekodiert = json_decode($json);
check('F3-AK1.7 - leerer Baum: kinder ist ein JSON-Objekt', is_object($dekodiert->kinder), $json);
check('F3-AK1.8 - leerer Baum: knoten ist ein JSON-Objekt', is_object($dekodiert->knoten), $json);

// =========================================================================
// 12 - Randfaelle
// =========================================================================

echo "\n== Randfaelle ==\n";

check('12.1 - leere Zeilenliste ergibt leeren Baum, kein Fatal', array('knoten' => array(), 'kinder' => array(), 'wurzeln' => array()) === CBD_Blocks_REST_API::baue_seitenbaum(array()));

$einzeln = CBD_Blocks_REST_API::baue_seitenbaum(array(zeile(1, 0, 'Einsam')));
check('12.2 - einzelner Wurzelknoten ohne Kinder', array(1) === $einzeln['wurzeln'] && !isset($einzeln['kinder'][1]));

// =========================================================================
// 13 - Entwuerfe-Parameter (opt-in, fuer den Seitenimporter)
// =========================================================================
//
// GET cbd/v1/seitenbaum bekommt einen neuen, additiven Query-Parameter
// "entwuerfe": Wert '1' schliesst zusaetzlich Seiten mit post_status =
// 'draft' ein. OHNE den Parameter (oder mit jedem anderen Wert als '1')
// bleibt das Verhalten exakt wie bisher - zwingend, weil
// assets/js/block-auswahl.js dieselbe Route ohne diesen Parameter aufruft
// und weiterhin nur veroeffentlichte Seiten erwarten darf.
//
// baue_seitenbaum() selbst bleibt unveraendert (sie ist status-agnostisch,
// verarbeitet nur post_type) - die Aenderung betrifft ausschliesslich die
// SQL-Abfrage und die Cache-Struktur in get_seitenbaum().

echo "\n== 13 - Entwuerfe-Parameter (opt-in) ==\n";

$GLOBALS['test_wpdb_zeilen'] = array(
    zeile(600, 0, 'Ohne Entwurf'),
);
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
$wpdb->abfragen = 0;
CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
$sql_ohne = $wpdb->letzte_sql();
check('13.1 - ohne Parameter: SQL filtert weiterhin nur auf publish, kein draft', false !== strpos($sql_ohne, "post_status = 'publish'") && false === strpos($sql_ohne, 'draft'), $sql_ohne);

CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request(array('entwuerfe' => '1')));
$sql_mit = $wpdb->letzte_sql();
check('13.2 - mit entwuerfe=1: SQL schliesst zusaetzlich draft ein', false !== strpos($sql_mit, "'draft'"), $sql_mit);

check('13.3 - beide Varianten loesen je EINE eigene $wpdb-Abfrage aus (Cache-Isolation, insgesamt 2)', 2 === $wpdb->abfragen, $wpdb->abfragen);

// Wiederholter Aufruf je Variante darf KEINE weitere Abfrage ausloesen -
// die Memoisierung bleibt je Parameterwert erhalten.
CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request(array('entwuerfe' => '1')));
check('13.4 - wiederholte Aufrufe beider Varianten loesen keine weitere Abfrage aus (weiterhin 2)', 2 === $wpdb->abfragen, $wpdb->abfragen);

// Rueckwaertskompatibilitaet: Antwortform ohne Parameter bleibt exakt
// Vertrag B (knoten, kinder, wurzeln) - kein neues Feld, keine geaenderte
// Struktur fuer bestehende Konsumenten (cbdBlockAuswahl).
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
$antwort_rueckwaerts = CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request());
$daten_rueckwaerts = $antwort_rueckwaerts->get_data();
check('13.5 - Antwortform ohne Parameter unveraendert (genau knoten, kinder, wurzeln)', array('knoten', 'kinder', 'wurzeln') === array_keys((array) $daten_rueckwaerts), array_keys((array) $daten_rueckwaerts));

// Jeder andere Wert als '1' wird wie "kein Parameter" behandelt (kein
// stillschweigendes "truthy" wie '0', 'false' als Zeichenkette o. Ae.).
CBD_Blocks_REST_API::seitenbaum_cache_vergessen();
$wpdb->abfragen = 0;
CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request(array('entwuerfe' => 'ja')));
$sql_anderer_wert = $wpdb->letzte_sql();
check('13.6 - anderer Wert als 1 (z. B. "ja") wird wie kein Parameter behandelt', false === strpos($sql_anderer_wert, 'draft'), $sql_anderer_wert);

// =========================================================================

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
