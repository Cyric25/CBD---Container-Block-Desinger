<?php
/**
 * Standalone-Harness für den Seitenimporter (AP-1 aus
 * docs/PLAN-Importer-Elternseite.md: „Elternseite serverseitig annehmen und
 * prüfen") — läuft OHNE WordPress.
 *
 * Geprüft wird die neue private Methode
 * `CBD_Page_Importer::bereinige_elternseite()` (per Reflection, Vorbild
 * tools/test-latex-parser.php) sowie ihre Verdrahtung in
 * `ajax_seiten_importieren()`: Der bereinigte Wert muss unverändert als
 * `post_parent` bei `wp_insert_post()` ankommen, und ein ungültiger Wert darf
 * den Lauf nicht mit einer Fehlermeldung an den Nutzer abbrechen — er fällt
 * still auf 0 zurück (bewusste Entscheidung, siehe Plan Abschnitt 5).
 *
 * Aufruf:  php tools/test-page-importer.php
 *
 * Liegt unter tools/ und ist damit NICHT im Verteilungs-ZIP enthalten
 * (create-plugin-zip.js listet nur admin, assets, blocks, includes, vendor,
 * languages).
 *
 * Aufbau und Stub-Stil nach dem Vorbild tools/test-block-content-api.php:
 * CLI-Wächter, Stubs, eigenes check()-Prüfgerüst, Exit-Code = Fehlerzahl > 0.
 *
 * `CBD_Content_Importer` und `CBD_Block_Serializer` sind laut Plan für dieses
 * Arbeitspaket „nur lesen" — sie werden deshalb hier NICHT eingebunden,
 * sondern durch schmale Doppel mit derselben Schnittstelle ersetzt. Das
 * isoliert die Prüfung des Seitenimports (Elternseite, wp_slash-Kette) von
 * der Komplexität des echten Markdown-Parsers, der nicht Gegenstand dieses
 * Arbeitspakets ist.
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');

$plugin_dir = str_replace('\\', '/', dirname(__DIR__)) . '/';
define('CBD_PLUGIN_DIR', $plugin_dir);
define('CBD_PLUGIN_URL', 'https://example.test/plugins/cdb/');
define('CBD_VERSION', 'test');
define('CBD_TABLE_BLOCKS', 'wp_cbd_blocks');

// --- WordPress-Stubs --------------------------------------------------------

function __($s, $d = null) { return $s; }
function add_action($t, $c, $p = 10, $a = 1) { return true; }
function add_submenu_page($parent, $title, $menu_title, $cap, $slug, $cb = '') { return $slug; }
function admin_url($p = '') { return 'https://example.test/wp-admin/' . $p; }
function wp_create_nonce($a) { return 'nonce'; }
function wp_enqueue_script($h, $s = '', $d = array(), $v = false, $f = false) {}
function wp_enqueue_style($h, $s = '', $d = array(), $v = false) {}
function wp_localize_script($h, $n, $d) {}
function sanitize_text_field($s) { return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s))); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }

/**
 * Wie das echte wp_unslash(): entfernt Backslashes bei Strings/Arrays,
 * lässt alles andere (null, int, bool …) unverändert.
 */
function wp_unslash($value) {
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }
    if (is_string($value)) {
        return stripslashes($value);
    }
    return $value;
}

/**
 * Markiert im Test sichtbar, dass die Maskierung tatsächlich durchlaufen
 * wurde — so lässt sich prüfen, dass wp_slash() vor wp_insert_post() NICHT
 * verloren geht (Risiko aus dem Plan, Abschnitt 6).
 */
function wp_slash($value) {
    if (is_array($value)) {
        return array_map('wp_slash', $value);
    }
    return is_string($value) ? '__SLASHED__' . $value : $value;
}

function check_ajax_referer($action, $name = false, $die = true) { return true; }
function current_user_can($cap) { return $GLOBALS['test_can'] ?? true; }

function wp_send_json_error($data = null) {
    $GLOBALS['test_last_error'] = $data;
    throw new RuntimeException('json_error');
}
function wp_send_json_success($data = null) {
    $GLOBALS['test_last_success'] = $data;
    throw new RuntimeException('json_success');
}

function get_edit_post_link($id, $context = 'display') {
    return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit';
}

class Test_WP_Error {
    public $message;
    public function __construct($message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($thing) { return $thing instanceof Test_WP_Error; }

function wp_insert_post($postarr, $wp_error = false) {
    $GLOBALS['test_insert_calls'][] = $postarr;
    if (!empty($GLOBALS['test_insert_wp_error'])) {
        return new Test_WP_Error('Einfügen fehlgeschlagen (Test)');
    }
    return $GLOBALS['test_next_page_id'];
}

/** Seiten/Beiträge kommen aus einer Tabelle im Test. Unbekannte ID -> null. */
function get_post($id = null) {
    $GLOBALS['test_get_post_aufrufe'][] = $id;
    if (is_object($id)) {
        return $id;
    }
    $id = (int) $id;
    return $GLOBALS['test_posts'][$id] ?? null;
}

/** Minimales $wpdb-Doppel — die Designliste ist für AP-1 ohne Belang. */
class Test_WPDB {
    public function get_results($sql) {
        return array();
    }
}
$GLOBALS['wpdb'] = new Test_WPDB();

// --- Fach-Doppel: parse_markdown_content() und to_post_content() -----------

class CBD_Content_Importer {
    private static $instance;
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function parse_markdown_content($inhalt, $designs) {
        $GLOBALS['test_parse_aufrufe'] = ($GLOBALS['test_parse_aufrufe'] ?? 0) + 1;
        return array(
            'sections' => array(array('id' => 's1', 'content' => $inhalt)),
            'groups'   => array(),
        );
    }
}

class CBD_Block_Serializer {
    public static function to_post_content($sections, $groups, $mappings, $optionen = array()) {
        $GLOBALS['test_serializer_aufrufe']  = ($GLOBALS['test_serializer_aufrufe'] ?? 0) + 1;
        $GLOBALS['test_serializer_optionen'] = $optionen;
        return '<!-- wp:paragraph --><p>Testinhalt \cdot mit Backslash</p><!-- /wp:paragraph -->';
    }
}

require_once $plugin_dir . 'includes/class-cbd-page-importer.php';

// --- Prüfgerüst --------------------------------------------------------------

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

function seite($id, $post_type, $post_status) {
    $p = new stdClass();
    $p->ID          = $id;
    $p->post_type   = $post_type;
    $p->post_status = $post_status;
    return $p;
}

$GLOBALS['test_posts'] = array(
    5 => seite(5, 'page', 'publish'),  // gültige, veröffentlichte Seite
    6 => seite(6, 'page', 'draft'),    // gültige Entwurfsseite
    7 => seite(7, 'page', 'private'),  // gültige private Seite
    8 => seite(8, 'post', 'publish'),  // Beitrag, keine Seite
    9 => seite(9, 'page', 'trash'),    // gelöschte Seite
);

$bereinige_ref = new ReflectionMethod('CBD_Page_Importer', 'bereinige_elternseite');
$bereinige_ref->setAccessible(true);
$importer = CBD_Page_Importer::get_instance();

function bereinige($roh) {
    global $bereinige_ref, $importer;
    return $bereinige_ref->invoke($importer, $roh);
}

/**
 * Ruft ajax_seiten_importieren() mit gegebenem $_POST auf und liefert
 * zurück, welche JSON-Antwortart ausgelöst wurde ('json_success' oder
 * 'json_error') — genau wie tools/test-block-content-api.php es über
 * geworfene RuntimeException-Nachrichten abbildet.
 */
function importiere($post_daten, $next_page_id = 501) {
    $_POST = $post_daten;
    $GLOBALS['test_insert_calls']    = array();
    $GLOBALS['test_last_success']    = null;
    $GLOBALS['test_last_error']      = null;
    $GLOBALS['test_next_page_id']    = $next_page_id;
    $GLOBALS['test_insert_wp_error'] = false;

    $importer = CBD_Page_Importer::get_instance();
    try {
        $importer->ajax_seiten_importieren();
        return 'keine json_*-Antwort';
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}

// =============================================================================
echo "== AK1: fehlend, leer, nicht numerisch, negativ, 0 -> 0 ==\n";
// =============================================================================

check('AK1.1 · fehlender Wert (null)', 0 === bereinige(null), bereinige(null));
check('AK1.2 · leerer String', 0 === bereinige(''), bereinige(''));
check('AK1.3 · nicht numerisch', 0 === bereinige('abc'), bereinige('abc'));
check('AK1.4 · negativ', 0 === bereinige('-5'), bereinige('-5'));
check('AK1.5 · genau 0', 0 === bereinige('0'), bereinige('0'));
check('AK1.6 · "-0"', 0 === bereinige('-0'), bereinige('-0'));
check('AK1.7 · nur Leerraum', 0 === bereinige('   '), bereinige('   '));

// =============================================================================
echo "\n== AK2: gültige Seiten-ID wird unverändert durchgereicht ==\n";
// =============================================================================

check('AK2.1 · veröffentlichte Seite', 5 === bereinige('5'), bereinige('5'));
check('AK2.2 · Entwurfsseite', 6 === bereinige('6'), bereinige('6'));
check('AK2.3 · private Seite', 7 === bereinige('7'), bereinige('7'));

// =============================================================================
echo "\n== AK3: Beitrag, gelöschte Seite, unbekannte ID -> 0 (ohne Fehlermeldung) ==\n";
// =============================================================================

check('AK3.1 · Beitrag (kein Seiten-Typ)', 0 === bereinige('8'), bereinige('8'));
check('AK3.2 · Seite im Papierkorb', 0 === bereinige('9'), bereinige('9'));
check('AK3.3 · nicht existierende ID', 0 === bereinige('123456'), bereinige('123456'));

// „ohne Fehlermeldung an den Nutzer" heißt: der ganze Import läuft trotzdem
// durch und endet in wp_send_json_success(), nicht in wp_send_json_error().
$r = importiere(array('title' => 'Beitrag als Eltern-ID', 'content' => '# Titel', 'parent_id' => '8'));
check('AK3.4 · Import mit Beitrags-ID als Elternseite bleibt Erfolg', 'json_success' === $r, $r);
$letzter = end($GLOBALS['test_insert_calls']);
check('AK3.5 · post_parent fällt dabei auf 0 zurück', 0 === ($letzter['post_parent'] ?? null), $letzter);

$r = importiere(array('title' => 'Geloeschte Seite als Eltern-ID', 'content' => '# Titel', 'parent_id' => '9'));
check('AK3.6 · Import mit gelöschter Seite als Elternseite bleibt Erfolg', 'json_success' === $r, $r);
$letzter = end($GLOBALS['test_insert_calls']);
check('AK3.7 · post_parent fällt dabei auf 0 zurück', 0 === ($letzter['post_parent'] ?? null), $letzter);

$r = importiere(array('title' => 'Unbekannte Eltern-ID', 'content' => '# Titel', 'parent_id' => '999999'));
check('AK3.8 · Import mit nicht existierender Elternseite bleibt Erfolg', 'json_success' === $r, $r);
$letzter = end($GLOBALS['test_insert_calls']);
check('AK3.9 · post_parent fällt dabei auf 0 zurück', 0 === ($letzter['post_parent'] ?? null), $letzter);

// =============================================================================
echo "\n== AK4: 20-stellige Ziffernfolge -> 0, keine PHP-Warnung ==\n";
// =============================================================================
//
// filter_var() statt (int)-Cast ist der Kern dieser Prüfung: Ein Cast bildet
// eine überlange Ziffernfolge ab PHP 8.1 mit einer Warnung auf PHP_INT_MAX ab
// statt sie abzulehnen (dieselbe Regel wie class-cbd-design-transfer.php,
// md_read_value(), Zeile ~911-915). Ohne einen eigenen set_error_handler
// bliebe die Warnung unsichtbar, weil sie den Ablauf nicht unterbricht.

$GLOBALS['test_warnungen'] = 0;
set_error_handler(function ($no, $str) {
    $GLOBALS['test_warnungen']++;
    return true;
}, E_ALL);
$ziffern_20 = str_repeat('9', 20);
$ergebnis_20 = bereinige($ziffern_20);
restore_error_handler();

check('AK4.1 · 20-stellige Ziffernfolge ergibt 0', 0 === $ergebnis_20, $ergebnis_20);
check('AK4.2 · dabei entsteht keine PHP-Warnung', 0 === $GLOBALS['test_warnungen'], $GLOBALS['test_warnungen']);

// Dieselbe Probe zusätzlich über den kompletten AJAX-Weg, damit ein
// künftiger Rückbau auf (int) auch dort auffiele.
$GLOBALS['test_warnungen'] = 0;
set_error_handler(function ($no, $str) {
    $GLOBALS['test_warnungen']++;
    return true;
}, E_ALL);
$r = importiere(array('title' => 'Ueberlange Eltern-ID', 'content' => '# Titel', 'parent_id' => str_repeat('9', 20)));
restore_error_handler();
check('AK4.3 · über den AJAX-Weg ebenfalls keine PHP-Warnung', 0 === $GLOBALS['test_warnungen'], $GLOBALS['test_warnungen']);
check('AK4.4 · über den AJAX-Weg dennoch Erfolgsantwort', 'json_success' === $r, $r);
$letzter = end($GLOBALS['test_insert_calls']);
check('AK4.5 · post_parent fällt dabei auf 0 zurück', 0 === ($letzter['post_parent'] ?? null), $letzter);

// =============================================================================
echo "\n== AK5: Wert mit vorangestellten Slashes (wie aus \$_POST) wird korrekt gelesen ==\n";
// =============================================================================
//
// stripslashes('\5') entfernt den Backslash -> "5". Das muss
// bereinige_elternseite() über sein eigenes wp_unslash() leisten, sonst
// bestünde "\5" nicht die Ziffernprüfung von filter_var() und fiele
// fälschlich auf 0 zurück.

check('AK5.1 · "\\5" wird als Seiten-ID 5 gelesen', 5 === bereinige('\5'), bereinige('\5'));
check('AK5.2 · "\\6" wird als Seiten-ID 6 gelesen', 6 === bereinige('\6'), bereinige('\6'));

// =============================================================================
echo "\n== AK6-Vorprüfung: gültiger Aufruf reicht den Wert unverändert an wp_insert_post() ==\n";
// =============================================================================
// (AK6 selbst ist `php tools/check-php74.php`, s. Übergabebericht — hier nur
// die Verdrahtung, die AK6 syntaktisch überhaupt erst ermöglicht.)

$r = importiere(array('title' => 'Gueltige Elternseite', 'content' => '# Titel', 'parent_id' => '5'));
check('V.1 · Import mit gültiger Elternseite: Erfolg', 'json_success' === $r, $r);
$letzter = end($GLOBALS['test_insert_calls']);
check('V.2 · post_parent = 5 kommt unverändert bei wp_insert_post() an', 5 === ($letzter['post_parent'] ?? null), $letzter);

$r = importiere(array('title' => 'Fehlende Elternseite', 'content' => '# Titel'));
check('V.3 · fehlender Parameter: Import läuft dennoch durch', 'json_success' === $r, $r);
$letzter = end($GLOBALS['test_insert_calls']);
check('V.4 · post_parent = 0 als Vorgabe (heutiges Verhalten bleibt erhalten)', 0 === ($letzter['post_parent'] ?? null), $letzter);

$r = importiere(array('title' => 'Leere Elternseite', 'content' => '# Titel', 'parent_id' => ''));
check('V.5 · leerer Parameter: Import läuft dennoch durch', 'json_success' === $r, $r);
$letzter = end($GLOBALS['test_insert_calls']);
check('V.6 · post_parent = 0 bei leerem Parameter', 0 === ($letzter['post_parent'] ?? null), $letzter);

// =============================================================================
echo "\n== Zusatz: wp_slash() bleibt vor wp_insert_post() erhalten (Risiko aus Plan Abschnitt 6) ==\n";
// =============================================================================

$r = importiere(array('title' => 'LaTeX-Regression', 'content' => '# Titel', 'parent_id' => '5'));
check('Z.1 · Import erfolgreich', 'json_success' === $r, $r);
$letzter = end($GLOBALS['test_insert_calls']);
check(
    'Z.2 · post_content ist maskiert (wp_slash() wurde vor wp_insert_post() aufgerufen)',
    0 === strpos((string) ($letzter['post_content'] ?? ''), '__SLASHED__'),
    $letzter['post_content'] ?? null
);

// =============================================================================

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
