<?php
/**
 * Standalone-Harness für den REST-Endpunkt `cbd/v1/block-html` — läuft OHNE
 * WordPress.
 *
 * Geprüft wird das sicherheitskritischste Stück des Vorhabens: Der Endpunkt
 * geht an allen vier Durchsetzungsstellen der Theme-Sperre „nur für
 * Lehrpersonen" vorbei (`template_redirect` Prio 20, `redirect_canonical`,
 * `pre_get_posts`, `rest_pre_dispatch`) und muss sie deshalb selbst
 * durchsetzen.
 *
 * Aufruf:  php tools/test-block-content-api.php
 *
 * Liegt unter tools/ und ist damit NICHT im Verteilungs-ZIP enthalten
 * (create-plugin-zip.js listet nur admin, assets, blocks, includes, vendor,
 * languages).
 *
 * REIHENFOLGE IST TEIL DES TESTS: Die Theme-Funktionen werden erst NACH dem
 * Abschnitt „Theme fehlt" definiert (per eval, weil PHP Funktionen nicht
 * wieder vergessen kann). Nur so lässt sich beides prüfen — mit und ohne
 * Theme.
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

// Ein Testfall lässt das Rendern absichtlich scheitern; die Klasse schreibt
// dann eine Zeile ins Fehlerlog. Damit sie die Ausgabe nicht verunziert,
// bekommt error_log() ein eigenes Ziel.
ini_set('error_log', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cbd-test-block-content-api.log');

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
function esc_html__($s, $d = null) { return $s; }
function esc_url($u) { return $u; }
function add_action($t, $c, $p = 10, $a = 1) { return true; }
function add_filter($t, $c, $p = 10, $a = 1) { return true; }
function add_shortcode($t, $c) { return true; }
function register_setting($g, $n, $a = array()) { return true; }
function get_option($k, $d = false) { return $GLOBALS['test_options'][$k] ?? $d; }
function current_time($t) { return '2026-08-16 12:00:00'; }
function get_current_user_id() { return (int) ($GLOBALS['test_user_id'] ?? 0); }
function is_user_logged_in() { return ((int) ($GLOBALS['test_user_id'] ?? 0)) > 0; }
function current_user_can($c) { return false; }
function get_user_meta($u, $k, $s = false) { return $s ? '' : array(); }
function wp_hash_password($p) { return 'hash:' . $p; }
function wp_generate_password($l = 12, $s = true) { return str_repeat('x', $l); }
function get_transient($k) { return $GLOBALS['test_transients'][$k] ?? false; }
function set_transient($k, $v, $t) { $GLOBALS['test_transients'][$k] = $v; return true; }
function sanitize_text_field($s) { return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s))); }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : stripslashes((string) $v); }
function get_permalink($id) { return 'https://example.test/?p=' . (int) $id; }
function add_query_arg($args, $url) { return $url . '&stub=1'; }
function has_shortcode($c, $t) { return false; }
function is_singular($t = '') { return false; }
function is_admin() { return false; }
function in_the_loop() { return false; }
function is_main_query() { return false; }
function get_the_ID() { return isset($GLOBALS['post']) && is_object($GLOBALS['post']) ? (int) $GLOBALS['post']->ID : 0; }
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
function absint($v) { return abs((int) $v); }

/** Zählt die Aufrufe — der Endpunkt muss sie in JEDEM Antwortpfad absetzen. */
function nocache_headers() { $GLOBALS['test_nocache']++; }

/** Beiträge kommen aus einer Tabelle im Test. Unbekannte ID -> null. */
function get_post($id = null) {
    if (is_object($id)) {
        return $id;
    }
    $id = (int) $id;
    return $GLOBALS['test_posts'][$id] ?? null;
}

function post_password_required($post = null) {
    return is_object($post) && isset($post->post_password) && '' !== $post->post_password;
}

/** Die geparste Blockstruktur liegt je Inhalt vorbereitet bereit. */
function parse_blocks($content) {
    $GLOBALS['test_parse_aufrufe']++;
    return $GLOBALS['test_parsed'][(string) $content] ?? array();
}

/**
 * Rendert einen Block. Merkt sich den Beitragskontext, damit prüfbar ist,
 * dass der Endpunkt den globalen $post setzt (pageId im Block-Kontext).
 */
function render_block($block) {
    $GLOBALS['test_render_aufrufe']++;
    $GLOBALS['test_render_post_id'] = (isset($GLOBALS['post']) && is_object($GLOBALS['post']))
        ? (int) $GLOBALS['post']->ID
        : -1;

    if (!empty($GLOBALS['test_render_wirft'])) {
        throw new RuntimeException('Renderfehler im Test');
    }
    if (!empty($GLOBALS['test_render_leer'])) {
        return "  \n\t ";
    }

    $sid = $block['attrs']['stableId'] ?? 'ohne-attribut';
    return '<div class="cbd-container" data-cbd-test="' . $sid . '">Gerendert: ' . $sid . '</div>';
}

/** Registrierte Routen sammeln, damit die Route selbst prüfbar ist. */
function register_rest_route($namespace, $route, $args = array(), $override = false) {
    $GLOBALS['test_routes'][] = array(
        'namespace' => $namespace,
        'route'     => $route,
        'args'      => $args,
    );
    return true;
}

/**
 * Der Theme-Filter wird echt durchgereicht: `simple_clean_seite_sichtbar()`
 * fragt ihn, und `CBD_Classroom_Gate::seite_freigeben()` beantwortet ihn.
 * Damit prüft der Harnisch die tatsächliche Naht und nicht eine Attrappe.
 */
function apply_filters($tag, $value) {
    $args = array_slice(func_get_args(), 2);

    if ('simple_clean_lehrerseite_freigeben' === $tag && class_exists('CBD_Classroom_Gate')) {
        $gate = CBD_Classroom_Gate::get_instance();
        return $gate->seite_freigeben($value, isset($args[0]) ? $args[0] : 0);
    }

    return $value;
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

$GLOBALS['test_options']        = array('cbd_classroom_enabled' => 1);
$GLOBALS['test_transients']     = array();
$GLOBALS['test_user_id']        = 0;
$GLOBALS['test_posts']          = array();
$GLOBALS['test_parsed']         = array();
$GLOBALS['test_routes']         = array();
$GLOBALS['test_nocache']        = 0;
$GLOBALS['test_nocache_delta']  = 0;
$GLOBALS['test_render_aufrufe'] = 0;
$GLOBALS['test_parse_aufrufe']  = 0;
$GLOBALS['test_render_post_id'] = null;
$GLOBALS['test_render_wirft']   = false;
$GLOBALS['test_render_leer']    = false;
$GLOBALS['test_lehrperson']     = false;
$GLOBALS['test_gesperrt']       = array();

/** Minimales $wpdb-Doppel für die Tabelle cbd_drawings (wie test-classroom-gate.php). */
class Test_WPDB {
    public $prefix = 'wp_';
    public $posts  = 'wp_posts';
    public $zeilen = array();
    public $abfragen = 0;
    private $args = array();

    public function prepare($sql, ...$args) {
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

    public function get_results($sql, $output = 'OBJECT') { $this->abfragen++; return array(); }
    public function get_var($sql) { $this->abfragen++; return null; }
    public function get_row($sql) { $this->abfragen++; return null; }
    public function insert($t, $d) { return 1; }
    public function update($t, $d, $w) { return 1; }
    public function delete($t, $w, $f = null) { return 1; }
}

if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }

$GLOBALS['wpdb'] = new Test_WPDB();

require_once $plugin_dir . 'includes/class-cbd-classroom.php';
require_once $plugin_dir . 'includes/class-cbd-classroom-gate.php';
require_once $plugin_dir . 'includes/class-cbd-block-content-api.php';

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

/** Baut einen Blockeintrag, wie parse_blocks() ihn liefert. */
function block($name, $attrs = array(), $innerHTML = '', $innerBlocks = array()) {
    return array(
        'blockName'    => $name,
        'attrs'        => $attrs,
        'innerBlocks'  => $innerBlocks,
        'innerHTML'    => $innerHTML,
        'innerContent' => array($innerHTML),
    );
}

/** Baut ein Beitragsobjekt. */
function beitrag($id, $typ, $status, $inhalt, $titel, $passwort = '') {
    $p = new stdClass();
    $p->ID            = $id;
    $p->post_type     = $typ;
    $p->post_status   = $status;
    $p->post_content  = $inhalt;
    $p->post_title    = $titel;
    $p->post_password = $passwort;
    return $p;
}

/**
 * Eine Anfrage an den Endpunkt stellen.
 *
 * `classroom` und `token` landen zusätzlich in $_GET — genau so, wie sie am
 * echten Endpunkt ankommen; `CBD_Classroom_Gate::sitzung()` liest sie dort.
 */
function anfrage($post_id, $stable_id, $classroom = null, $token = null) {
    $_GET = array();
    if (null !== $classroom) { $_GET['classroom'] = $classroom; }
    if (null !== $token)     { $_GET['token']     = $token; }
    CBD_Classroom_Gate::sitzung_vergessen();

    $GLOBALS['test_render_post_id'] = null;
    $vor = $GLOBALS['test_nocache'];

    $antwort = CBD_Block_Content_API::liefere_block_html(new WP_REST_Request(array(
        'post_id'   => $post_id,
        'stable_id' => $stable_id,
        'classroom' => $classroom,
        'token'     => $token,
    )));

    $GLOBALS['test_nocache_delta'] = $GLOBALS['test_nocache'] - $vor;
    $GLOBALS['test_antworten'][]   = $antwort;

    return $antwort;
}

function ist_ablehnung($a) {
    return ($a instanceof WP_REST_Response)
        && 404 === $a->get_status()
        && array(
            'code'    => 'cbd_block_not_available',
            'message' => 'Der Block ist nicht verfügbar.',
        ) === $a->get_data();
}

function ist_erfolg($a) {
    if (!($a instanceof WP_REST_Response) || 200 !== $a->get_status()) {
        return false;
    }
    $d = $a->get_data();
    return is_array($d)
        && array('html', 'title') === array_keys($d)
        && is_string($d['html'])
        && '' !== trim($d['html']);
}

function fingerabdruck($a) {
    if (!($a instanceof WP_REST_Response)) {
        return 'KEINE ANTWORT';
    }
    return $a->get_status() . '|' . json_encode($a->get_data(), JSON_UNESCAPED_UNICODE);
}

$GLOBALS['test_antworten'] = array();

// =========================================================================
// Fixture
// =========================================================================

$GLOBALS['test_parsed']['INHALT-A'] = array(
    // Fremder Blocktyp mit passender Kennung — darf nie ausgeliefert werden.
    block('core/paragraph', array('stableId' => 'cbd-fremd'), '<p>frei stehend</p>'),
    // Container mit Attribut.
    block('container-block-designer/container', array(
        'stableId'   => 'cbd-aaa',
        'blockTitle' => 'Merksatz Ampholyte',
    ), '<div>A</div>'),
    // Altbestand: Kennung nur im gespeicherten Markup, kein Attribut.
    block('container-block-designer/infotext-k1', array(), '<div ' . 'data-stable-id="cbd-alt">Altbestand</div>'),
    // Verschachtelung: der gesuchte Container liegt in einem anderen.
    block('container-block-designer/container', array('stableId' => 'cbd-aussen'), '<div>aussen</div>', array(
        block('core/paragraph', array(), '<p>Text</p>'),
        block('container-block-designer/container', array('stableId' => 'cbd-innen'), '<div>innen</div>'),
        block('modular-blocks/accordion', array('stableId' => 'cbd-modul'), '<div>Accordion</div>'),
    )),
    // Container ganz ohne Kennung.
    block('container-block-designer/container', array(), '<div>ohne Kennung</div>'),
);

$GLOBALS['test_posts'] = array(
    31 => beitrag(31, 'page', 'publish', 'INHALT-A', 'Loesungen Kapitel 3 Saeuren'),
    40 => beitrag(40, 'page', 'draft',   'INHALT-A', 'Entwurfsseite'),
    41 => beitrag(41, 'attachment', 'inherit', 'INHALT-A', 'Anhang'),
    42 => beitrag(42, 'page', 'publish', 'INHALT-A', 'Passwortseite', 'geheim'),
    43 => beitrag(43, 'page', 'private', 'INHALT-A', 'Private Seite'),
    44 => beitrag(44, 'post', 'publish', 'INHALT-A', 'Ein Beitrag'),
);

// =========================================================================
// 1 · Die Route
// =========================================================================

echo "== Route und Argumente ==\n";

CBD_Block_Content_API::register_routes();
$routen = $GLOBALS['test_routes'];

check('1.1 · genau eine Route registriert', 1 === count($routen), count($routen));

$r = $routen[0] ?? array('namespace' => '', 'route' => '', 'args' => array());
check('1.2 · Namensraum cbd/v1', 'cbd/v1' === $r['namespace'], $r['namespace']);
check('1.3 · Route /block-html', '/block-html' === $r['route'], $r['route']);
check('1.4 · nur GET', 'GET' === ($r['args']['methods'] ?? null), $r['args']['methods'] ?? null);
check(
    '1.5 · permission_callback ist __return_true (Autorisierung im Callback)',
    '__return_true' === ($r['args']['permission_callback'] ?? null),
    $r['args']['permission_callback'] ?? null
);
check(
    '1.6 · Callback ist liefere_block_html',
    array('CBD_Block_Content_API', 'liefere_block_html') === ($r['args']['callback'] ?? null),
    $r['args']['callback'] ?? null
);

$args = $r['args']['args'] ?? array();
check('1.7 · post_id ist Pflicht', !empty($args['post_id']['required']), $args['post_id'] ?? null);
check('1.8 · post_id wird mit absint bereinigt', 'absint' === ($args['post_id']['sanitize_callback'] ?? null));
check('1.9 · stable_id ist Pflicht', !empty($args['stable_id']['required']), $args['stable_id'] ?? null);
check('1.10 · stable_id wird mit sanitize_text_field bereinigt', 'sanitize_text_field' === ($args['stable_id']['sanitize_callback'] ?? null));
check('1.11 · classroom ist optional und bekannt', isset($args['classroom']) && empty($args['classroom']['required']));
check('1.12 · token ist optional und bekannt', isset($args['token']) && empty($args['token']['required']));

// =========================================================================
// 2 · Zusicherungen am Quelltext
// =========================================================================

echo "\n== Quelltext-Zusicherungen ==\n";

$quelldatei = $plugin_dir . 'includes/class-cbd-block-content-api.php';
$quelle     = file_get_contents($quelldatei);

// Der Attributname wird am ROHEN Text geprueft — auch ein Kommentar wuerde
// hier als vierte Fundstelle zaehlen (das Akzeptanzkriterium des APs zaehlt
// `grep -r "data-stable-id" includes/`).
check(
    '2.1 · keine vierte Kopie der Kennungs-Extraktion (Attributname kommt nirgends vor)',
    false === strpos($quelle, 'data-' . 'stable-id')
);

// Alle im CODE vorkommenden Bezeichner einsammeln — Token statt Regex, damit
// Kommentare und Zeichenketten nicht mitzaehlen. Die Doku dieser Datei nennt
// `do_blocks()` und `serialize_blocks()` naemlich ausdruecklich als das, was
// sie NICHT tut.
$bezeichner = array();
foreach (token_get_all($quelle) as $token) {
    if (is_array($token) && T_STRING === $token[0]) {
        $bezeichner[$token[1]] = true;
    }
}

check('2.2 · kein eigener regulaerer Ausdruck', !isset($bezeichner['preg_match']) && !isset($bezeichner['preg_match_all']));
check('2.3 · gerendert wird mit render_block()', isset($bezeichner['render_block']));
check('2.4 · kein do_blocks()', !isset($bezeichner['do_blocks']));
check('2.5 · kein serialize_blocks()', !isset($bezeichner['serialize_blocks']));

$aufgerufen = array();
foreach (array_keys($bezeichner) as $name) {
    if (0 === strpos($name, 'simple_clean_')) {
        $aufgerufen[] = $name;
    }
}
sort($aufgerufen);

check('2.6 · simple_clean_seite_sichtbar() wird verwendet', in_array('simple_clean_seite_sichtbar', $aufgerufen, true), $aufgerufen);
foreach ($aufgerufen as $name) {
    check(
        '2.7 · ' . $name . '() ist mit function_exists() abgesichert',
        false !== strpos($quelle, "function_exists('" . $name . "')")
    );
}

// =========================================================================
// 3 · Block finden (finde_block)
// =========================================================================

echo "\n== Block finden ==\n";

$bloecke = $GLOBALS['test_parsed']['INHALT-A'];

$t = CBD_Block_Content_API::finde_block($bloecke, array('cbd-aaa'));
check('3.1 · ueber das Attribut stableId gefunden', is_array($t) && 'cbd-aaa' === ($t['attrs']['stableId'] ?? null), $t['attrs'] ?? null);

$t = CBD_Block_Content_API::finde_block($bloecke, array('cbd-alt'));
check('3.2 · Altbestand ueber das gespeicherte Markup gefunden', is_array($t) && 'container-block-designer/infotext-k1' === ($t['blockName'] ?? null), $t['blockName'] ?? null);

$t = CBD_Block_Content_API::finde_block($bloecke, array('cbd-innen'));
check('3.3 · verschachtelter Block gefunden (Rekursion)', is_array($t) && 'cbd-innen' === ($t['attrs']['stableId'] ?? null), $t['attrs'] ?? null);

$t = CBD_Block_Content_API::finde_block($bloecke, array('cbd-aussen'));
check('3.4 · aeusserer Container wird als Ganzes zurueckgegeben', is_array($t) && 'cbd-aussen' === ($t['attrs']['stableId'] ?? null));

check('3.5 · unbekannte Kennung -> null', null === CBD_Block_Content_API::finde_block($bloecke, array('gibtesnicht')));
check('3.6 · fremder Namensraum mit passender Kennung -> null', null === CBD_Block_Content_API::finde_block($bloecke, array('cbd-fremd')));
check('3.7 · Block aus „Eigene WP Blocks" -> null', null === CBD_Block_Content_API::finde_block($bloecke, array('cbd-modul')));
check('3.8 · leere Liste -> null', null === CBD_Block_Content_API::finde_block($bloecke, array()));
check('3.9 · kein Array -> null', null === CBD_Block_Content_API::finde_block('kein array', array('cbd-aaa')));

// =========================================================================
// 4 · Theme fehlt: kein Fatal, keine Sperre
// =========================================================================

echo "\n== Theme fehlt (simple_clean_* nicht definiert) ==\n";

check('4.0 · Vorbedingung: Theme-Funktion existiert wirklich nicht', !function_exists('simple_clean_seite_sichtbar'));

unset($GLOBALS['post']);
$a = anfrage(31, 'cbd-aaa');
check('4.1 · veroeffentlichte Seite -> Erfolg (kein Fatal)', ist_erfolg($a), fingerabdruck($a));
check('4.2 · nocache_headers() wurde aufgerufen', 1 === $GLOBALS['test_nocache_delta'], $GLOBALS['test_nocache_delta']);
check('4.3 · Titel kommt aus den Blockattributen', 'Merksatz Ampholyte' === ($a->get_data()['title'] ?? null), $a->get_data());
check('4.4 · Antwort hat genau die Schluessel html und title', array('html', 'title') === array_keys($a->get_data()));
check('4.5 · beim Rendern war der Beitragskontext gesetzt', 31 === $GLOBALS['test_render_post_id'], $GLOBALS['test_render_post_id']);
check('4.6 · globaler $post danach wieder unbesetzt', !array_key_exists('post', $GLOBALS));

$fremder_post = beitrag(999, 'page', 'publish', 'INHALT-A', 'Andere Seite');
$GLOBALS['post'] = $fremder_post;
$a = anfrage(31, 'cbd-aaa');
check('4.7 · vorhandener globaler $post wird wiederhergestellt', $GLOBALS['post'] === $fremder_post);
unset($GLOBALS['post']);

$a = anfrage(31, 'cbd-alt');
check('4.8 · Altbestand ohne Attribut wird ausgeliefert', ist_erfolg($a), fingerabdruck($a));
check('4.9 · Block ohne Titelattribut -> leerer Titel', '' === ($a->get_data()['title'] ?? null), $a->get_data());

$a = anfrage(31, 'cbd-innen');
check('4.10 · verschachtelter Block wird ausgeliefert', ist_erfolg($a), fingerabdruck($a));

$a = anfrage(31, 'cbd-aaa:p3');
check('4.11 · Suffix :pN wird auf die Basisform zurueckgefuehrt', ist_erfolg($a), fingerabdruck($a));

// --- Ablehnungen ---------------------------------------------------------
$ablehnungen = array();

$ablehnungen['unbekannte Kennung']    = anfrage(31, 'gibtesnicht');
$ablehnungen['fremder Blocktyp']      = anfrage(31, 'cbd-fremd');
$ablehnungen['fremdes Plugin']        = anfrage(31, 'cbd-modul');
$ablehnungen['Entwurf']               = anfrage(40, 'cbd-aaa');
$ablehnungen['Anhang']                = anfrage(41, 'cbd-aaa');
$ablehnungen['passwortgeschuetzt']    = anfrage(42, 'cbd-aaa');
$ablehnungen['privat']                = anfrage(43, 'cbd-aaa');
$ablehnungen['Beitrag existiert nicht'] = anfrage(4711, 'cbd-aaa');
$ablehnungen['post_id 0']             = anfrage(0, 'cbd-aaa');
$ablehnungen['post_id negativ']       = anfrage(-5, 'cbd-aaa');
$ablehnungen['leere stable_id']       = anfrage(31, '');
$ablehnungen['stable_id nur Leerraum'] = anfrage(31, '   ');

foreach ($ablehnungen as $label => $antwort) {
    check('4.12 · ' . $label . ' -> 404 mit Einheitsantwort', ist_ablehnung($antwort), fingerabdruck($antwort));
}

check('4.13 · auch bei Ablehnung wurde nocache_headers() aufgerufen', 1 === $GLOBALS['test_nocache_delta'], $GLOBALS['test_nocache_delta']);

// Feldartige Parameter (`?stable_id[]=x`) dürfen weder durchrutschen noch
// eine PHP-Warnung erzeugen.
$GLOBALS['test_warnungen'] = 0;
set_error_handler(function ($no, $str) {
    $GLOBALS['test_warnungen']++;
    return true;
}, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);
$feld_a = anfrage(31, array('cbd-aaa'));
$feld_b = anfrage(array(31), 'cbd-aaa');
restore_error_handler();
check('4.15 · stable_id als Feld -> 404', ist_ablehnung($feld_a), fingerabdruck($feld_a));
check('4.16 · post_id als Feld -> 404', ist_ablehnung($feld_b), fingerabdruck($feld_b));
check('4.17 · dabei keine PHP-Warnung', 0 === $GLOBALS['test_warnungen'], $GLOBALS['test_warnungen']);

$a = anfrage(44, 'cbd-aaa');
check('4.14 · veroeffentlichter Beitrag (post) wird ebenfalls bedient', ist_erfolg($a), fingerabdruck($a));

// =========================================================================
// 5 · Theme vorhanden
// =========================================================================

// Nachbildung von Theme/includes/sichtbarkeit.php — wortgleich in der Logik.
// eval(), weil PHP eine einmal definierte Funktion nicht wieder vergisst und
// der Abschnitt oben ohne sie laufen musste.
eval('
function simple_clean_ist_lehrperson() {
    return !empty($GLOBALS["test_lehrperson"]);
}
function simple_clean_seite_nur_lehrpersonen($post_id) {
    return !empty($GLOBALS["test_gesperrt"][(int) $post_id]);
}
function simple_clean_seite_sichtbar($post_id) {
    if (simple_clean_ist_lehrperson()) { return true; }
    $post_id = (int) $post_id;
    if (!simple_clean_seite_nur_lehrpersonen($post_id)) { return true; }
    return (bool) apply_filters("simple_clean_lehrerseite_freigeben", false, $post_id);
}
');

echo "\n== Theme vorhanden, Seite NICHT gesperrt ==\n";

check('5.0 · Vorbedingung: Theme-Funktion ist jetzt da', function_exists('simple_clean_seite_sichtbar'));

$GLOBALS['test_gesperrt'] = array();
$a = anfrage(31, 'cbd-aaa');
check('5.1 · offene Seite -> Erfolg', ist_erfolg($a), fingerabdruck($a));

echo "\n== Theme vorhanden, Seite GESPERRT ==\n";

$GLOBALS['test_gesperrt'] = array(31 => true);
$GLOBALS['test_transients'] = array();
$wpdb = $GLOBALS['wpdb'];
$wpdb->zeilen = array(
    array('class_id' => 4, 'page_id' => 31, 'container_id' => 'cbd-aaa',    'is_behandelt' => 1),
    array('class_id' => 4, 'page_id' => 31, 'container_id' => 'cbd-alt',    'is_behandelt' => 0),
    array('class_id' => 9, 'page_id' => 31, 'container_id' => 'cbd-innen',  'is_behandelt' => 1),
);

$gesperrt_ohne_sitzung = anfrage(31, 'cbd-aaa');
check('6.1 · gesperrte Seite ohne Sitzung -> 404', ist_ablehnung($gesperrt_ohne_sitzung), fingerabdruck($gesperrt_ohne_sitzung));
check('6.2 · dabei wurde nocache_headers() aufgerufen', 1 === $GLOBALS['test_nocache_delta']);
check('6.3 · dabei wurde NICHT gerendert', null === $GLOBALS['test_render_post_id'], $GLOBALS['test_render_post_id']);

$gueltig = array('class_id' => 4, 'class_name' => 'Testklasse');
$GLOBALS['test_transients']['cbd_classroom_tok4'] = $gueltig;

$a = anfrage(31, 'cbd-aaa', 4, 'tok4');
check('6.4 · gueltige Sitzung + behandelter Block -> Erfolg', ist_erfolg($a), fingerabdruck($a));

$a = anfrage(31, 'cbd-alt', 4, 'tok4');
check('6.5 · gueltige Sitzung, Block NICHT behandelt -> 404', ist_ablehnung($a), fingerabdruck($a));

$a = anfrage(31, 'cbd-innen', 4, 'tok4');
check('6.6 · Block einer FREMDEN Klasse behandelt -> 404', ist_ablehnung($a), fingerabdruck($a));

$a = anfrage(31, 'cbd-aaa', 9, 'tok4');
check('6.7 · classroom passt nicht zum Token -> 404', ist_ablehnung($a), fingerabdruck($a));

$a = anfrage(31, 'cbd-aaa', 4, 'falschertoken');
check('6.8 · unbekanntes Token -> 404', ist_ablehnung($a), fingerabdruck($a));

$a = anfrage(31, 'cbd-aaa', null, null);
check('6.9 · gesperrt, Sitzungsparameter fehlen -> 404', ist_ablehnung($a), fingerabdruck($a));

$GLOBALS['test_options']['cbd_classroom_enabled'] = 0;
$a = anfrage(31, 'cbd-aaa', 4, 'tok4');
check('6.10 · Klassensystem abgeschaltet -> 404', ist_ablehnung($a), fingerabdruck($a));
$GLOBALS['test_options']['cbd_classroom_enabled'] = 1;

// Gesperrte Seite, gueltige Sitzung, aber der Block existiert gar nicht.
$a = anfrage(31, 'gibtesnicht', 4, 'tok4');
check('6.11 · gesperrt + Sitzung + unbekannte Kennung -> 404', ist_ablehnung($a), fingerabdruck($a));

// Angemeldete Lehrperson braucht keinen Klassen-Durchlass.
$GLOBALS['test_lehrperson'] = true;
$GLOBALS['test_user_id']    = 7;
$a = anfrage(31, 'cbd-alt');
check('6.12 · angemeldete Lehrperson sieht auch nicht behandelte Bloecke', ist_erfolg($a), fingerabdruck($a));
$a = anfrage(40, 'cbd-aaa');
check('6.13 · aber auch fuer sie bleibt der Entwurf gesperrt', ist_ablehnung($a), fingerabdruck($a));
$GLOBALS['test_lehrperson'] = false;
$GLOBALS['test_user_id']    = 0;

// Vererbte Sperre: Unterseite einer gesperrten Seite.
$GLOBALS['test_gesperrt'] = array(31 => true, 44 => true);
$a = anfrage(44, 'cbd-aaa');
check('6.14 · geerbte Sperre wirkt ebenso (Theme entscheidet)', ist_ablehnung($a), fingerabdruck($a));
$GLOBALS['test_gesperrt'] = array(31 => true);

// =========================================================================
// 7 · Ablehnung und Nichtexistenz sind zeichengleich
// =========================================================================

echo "\n== Ablehnung und Nichtexistenz sind nicht unterscheidbar ==\n";

$vergleich = array(
    'gesperrte Seite'          => anfrage(31, 'cbd-aaa'),
    'unbekannte Kennung'       => anfrage(31, 'gibtesnicht'),
    'Seite gibt es nicht'      => anfrage(4711, 'cbd-aaa'),
    'Entwurf'                  => anfrage(40, 'cbd-aaa'),
    'passwortgeschuetzt'       => anfrage(42, 'cbd-aaa'),
    'fremder Blocktyp'         => anfrage(31, 'cbd-fremd'),
    'Block nicht freigegeben'  => anfrage(31, 'cbd-alt', 4, 'tok4'),
);

$abdruecke = array();
foreach ($vergleich as $label => $antwort) {
    $abdruecke[$label] = fingerabdruck($antwort);
}
$eindeutig = array_unique(array_values($abdruecke));
check('7.1 · alle Fehlschlaege liefern EINE einzige Antwort', 1 === count($eindeutig), $abdruecke);
check('7.2 · und zwar HTTP 404 mit dem Einheitscode', '404|{"code":"cbd_block_not_available","message":"Der Block ist nicht verfügbar."}' === reset($eindeutig), reset($eindeutig));

// =========================================================================
// 8 · Kein Seitentitel, kein Permalink in irgendeiner Antwort
// =========================================================================

echo "\n== Keine Metadaten der Seite in der Antwort ==\n";

$verboten = array('Loesungen Kapitel 3 Saeuren', 'Passwortseite', 'Entwurfsseite', 'example.test', '?p=31');
$leck     = array();
foreach ($GLOBALS['test_antworten'] as $i => $antwort) {
    $text = fingerabdruck($antwort);
    foreach ($verboten as $wort) {
        if (false !== strpos($text, $wort)) {
            $leck[] = $i . ': ' . $wort;
        }
    }
}
check('8.1 · keine Antwort nennt Seitentitel oder Permalink', empty($leck), $leck);
check('8.2 · ueberhaupt Antworten geprueft', count($GLOBALS['test_antworten']) > 25, count($GLOBALS['test_antworten']));

// =========================================================================
// 9 · Rendern
// =========================================================================

echo "\n== Rendern ==\n";

$GLOBALS['test_gesperrt'] = array();

$GLOBALS['test_render_leer'] = true;
$a = anfrage(31, 'cbd-aaa');
check('9.1 · leeres Renderergebnis -> 404 statt leerer Erfolg', ist_ablehnung($a), fingerabdruck($a));
$GLOBALS['test_render_leer'] = false;

unset($GLOBALS['post']);
$GLOBALS['test_render_wirft'] = true;
$a = anfrage(31, 'cbd-aaa');
check('9.2 · Ausnahme beim Rendern -> 404, kein Fatal', ist_ablehnung($a), fingerabdruck($a));
check('9.3 · globaler $post auch danach aufgeraeumt', !array_key_exists('post', $GLOBALS));
$GLOBALS['test_render_wirft'] = false;

$vor_parse = $GLOBALS['test_parse_aufrufe'];
anfrage(4711, 'cbd-aaa');
check('9.4 · fuer eine nicht existierende Seite wird gar nicht geparst', $vor_parse === $GLOBALS['test_parse_aufrufe'], $GLOBALS['test_parse_aufrufe'] - $vor_parse);

$vor_render = $GLOBALS['test_render_aufrufe'];
anfrage(31, 'gibtesnicht');
check('9.5 · ohne Treffer wird nicht gerendert', $vor_render === $GLOBALS['test_render_aufrufe'], $GLOBALS['test_render_aufrufe'] - $vor_render);

// =========================================================================

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
