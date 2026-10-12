<?php
/**
 * Standalone-Harness für die Elementliste der Tafelbilder — läuft OHNE
 * WordPress (Plan „Rückmeldungen Klassenmodus und Tafel-Lasso", Phase 4,
 * AP-4.2, Architektur E8, Risiken R5–R7).
 *
 * Geprüft wird:
 *   A  CBD_Classroom::pruefe_elemente_json()   (privat, per Reflection)
 *   B  CBD_Classroom::ajax_save_drawing()      (Spalte schreiben, Selbstheilung)
 *   C  CBD_Classroom::ajax_load_drawing()      (Spalte liefern, alte DB ohne Spalte)
 *
 * Aufruf:  php tools/test-board-elemente.php
 *
 * Gegenstück im Browser-JavaScript: tools/test-board-elemente-js.js (AP-4.1,
 * `CBDBoardElemente.pruefeModell`). Beide Seiten prüfen dasselbe Format
 * „Elementmodell v1" (Plan, Abschnitt 4) mit demselben Farbmuster.
 *
 * Liegt unter tools/ und ist damit NICHT im Verteilungs-ZIP enthalten.
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
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_url($u) { return $u; }
function esc_sql($s) { return addslashes((string) $s); }
function add_action($t, $c, $p = 10, $a = 1) { return true; }
function add_filter($t, $c, $p = 10, $a = 1) { return true; }
function add_shortcode($t, $c) { return true; }
function do_action($t, ...$a) { $GLOBALS['test_actions'][] = $t; }
function register_setting($g, $n, $a = array()) { return true; }
function get_option($k, $d = false) { return $d; }
function current_time($t) { return '2026-10-12 12:00:00'; }
function get_current_user_id() { return 7; }
function is_user_logged_in() { return true; }
function current_user_can($c) { return true; }
function get_user_meta($u, $k, $s = false) { return $s ? '' : array(); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
// Wie WordPress: entfernt die Backslashes, die WordPress allen
// $_POST-Werten voranstellt (wp_magic_quotes).
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : stripslashes((string) $v); }
function wp_json_encode($d, $o = 0, $t = 512) { return json_encode($d, $o, $t); }
if (!function_exists('mb_substr')) {
    // Ersatz nur, falls die CLI ohne mbstring läuft (Zeichen statt Bytes).
    function mb_substr($s, $start, $len = null) {
        preg_match_all('/./us', $s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
}
function check_ajax_referer($a, $n) { return true; }
function wp_send_json_error($d = null) { $GLOBALS['test_json'] = array('ok' => false, 'data' => $d); throw new RuntimeException('json'); }
function wp_send_json_success($d = null) { $GLOBALS['test_json'] = array('ok' => true, 'data' => $d); throw new RuntimeException('json'); }

/** Stub des Schema-Managers: zählt Aufrufe und „legt die Spalte an". */
class CBD_Schema_Manager {
    public static $aufrufe = 0;
    public static function ensure_columns() {
        self::$aufrufe++;
        if ($GLOBALS['wpdb']->reparierbar) {
            $GLOBALS['wpdb']->spalte = true;
        }
        return array();
    }
}

/**
 * $wpdb-Doppel für cbd_drawings. Bildet nach: eine Zeile je Container,
 * update()/insert() scheitern (false + last_error wie MySQL), wenn die Spalte
 * elemente_data fehlt und trotzdem geschrieben wird.
 */
class Test_WPDB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $spalte = true;          // existiert elemente_data?
    public $reparierbar = true;     // legt ensure_columns() sie an?
    public $zeile = null;           // gespeicherte Zeile (array) oder null
    public $schreibvorgaenge = array();
    public $abfragen = array();
    private $args = array();

    public function prepare($sql, ...$args) {
        if (1 === count($args) && is_array($args[0])) {
            $args = $args[0];
        }
        $this->args = $args;
        foreach ($args as $a) {
            $sql = preg_replace('/%[dsf]/', is_int($a) ? (string) $a : "'" . addslashes((string) $a) . "'", $sql, 1);
        }
        return $sql;
    }

    public function get_var($sql) {
        $this->abfragen[] = $sql;
        if (stripos($sql, 'SHOW COLUMNS') !== false) {
            return ($this->spalte && stripos($sql, 'elemente_data') !== false) ? 'elemente_data' : null;
        }
        if (stripos($sql, CBD_TABLE_CLASSES) !== false) {
            return 1; // Lehrperson besitzt die Klasse
        }
        return null;
    }

    public function get_row($sql) {
        $this->abfragen[] = $sql;
        if (!$this->zeile) {
            return null;
        }
        if (!$this->spalte && stripos($sql, 'elemente_data') !== false) {
            $this->last_error = "Unknown column 'elemente_data' in 'field list'";
            return null;
        }
        $o = new stdClass();
        foreach ($this->zeile as $k => $v) {
            $o->$k = $v;
        }
        if (stripos($sql, 'elemente_data') === false) {
            unset($o->elemente_data);
        }
        return $o;
    }

    private function schreibe($art, $daten) {
        $this->schreibvorgaenge[] = array($art, $daten);
        if (!$this->spalte && array_key_exists('elemente_data', $daten)) {
            $this->last_error = "Unknown column 'elemente_data' in 'field list'";
            return false;
        }
        $this->last_error = '';
        $this->zeile = array_merge($this->zeile ?: array('id' => 1, 'is_behandelt' => 0, 'elemente_data' => null), $daten);
        return 1;
    }

    public function insert($t, $d) { return $this->schreibe('insert', $d); }
    public function update($t, $d, $w) { return $this->schreibe('update', $d); }
    public function get_results($sql, $o = null) { return array(); }
    public function get_col($sql) { return array(); }
    public function delete($t, $w, $f = null) { return 1; }
}

if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }

$GLOBALS['wpdb'] = new Test_WPDB();
$GLOBALS['test_actions'] = array();

require_once $plugin_dir . 'includes/class-cbd-classroom.php';

// --- Prüfgerüst -----------------------------------------------------------

$GLOBALS['fails'] = 0;
$GLOBALS['oks'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        $GLOBALS['oks']++;
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

$klasse = CBD_Classroom::get_instance();

if (!method_exists($klasse, 'pruefe_elemente_json')) {
    check('CBD_Classroom::pruefe_elemente_json() existiert', false);
    echo "\nErgebnis: {$GLOBALS['oks']} OK, {$GLOBALS['fails']} FAIL\n";
    exit(1);
}
$pruefe = new ReflectionMethod('CBD_Classroom', 'pruefe_elemente_json');
$pruefe->setAccessible(true);

/** Ruft pruefe_elemente_json() wie ajax_save_drawing() mit einem $_POST-Wert. */
function pj($roh) {
    global $pruefe, $klasse;
    return $pruefe->invoke($klasse, $roh);
}

/** Wie WordPress $_POST-Werte ankommen lässt (wp_magic_quotes → addslashes). */
function geslasht($text) {
    return addslashes($text);
}

$bild = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
$modell = array(
    'v' => 1, 'breite' => 1200, 'hoehe' => 800,
    'elemente' => array(
        array('id' => 'e1', 'typ' => 'strich', 'werkzeug' => 'pen', 'farbe' => '#000000', 'breite' => 3,
            'punkte' => array(array(10.5, 20), array(12, 22.25))),
        array('id' => 'e2', 'typ' => 'strich', 'werkzeug' => 'highlighter', 'farbe' => 'rgba(255, 255, 200, 1.0)', 'breite' => 18,
            'punkte' => array(array(100, 40), array(180, 40))),
        array('id' => 'e3', 'typ' => 'text', 'farbe' => '#1565c0', 'breite' => 3,
            'text' => "Na⁺ + Cl⁻ \"zitiert\" \\ Backslash", 'x' => 40, 'y' => 300),
        array('id' => 'e4', 'typ' => 'bild', 'daten' => $bild, 'x' => 0, 'y' => 0, 'b' => 1200, 'h' => 800),
    ),
);
$json = json_encode($modell, JSON_UNESCAPED_UNICODE);

function mit_element($el) {
    return json_encode(array('v' => 1, 'breite' => 100, 'hoehe' => 100, 'elemente' => array($el)));
}
function anzahl($ergebnis) {
    if (!is_string($ergebnis)) {
        return $ergebnis;
    }
    $d = json_decode($ergebnis, true);
    return count($d['elemente']);
}

// =========================================================================
echo "\nA: pruefe_elemente_json()\n";
// =========================================================================

$slashed = geslasht($json);
check('A1 Testdaten enthalten \\" (Anführungszeichen werden geslasht)', strpos($slashed, '\\"') !== false);
check('A2 R7-Falle: geslashtes JSON lässt sich OHNE wp_unslash nicht parsen',
    null === json_decode($slashed, true));
$r = pj($slashed);
check('A3 geslashtes gültiges JSON wird akzeptiert (String)', is_string($r), $r);
$d = is_string($r) ? json_decode($r, true) : null;
check('A4 Ergebnis ist inhaltsgleich zum Modell', $d === json_decode($json, true), $d);
check('A5 Text mit Anführungszeichen und Backslash unverändert',
    $d && $d['elemente'][2]['text'] === "Na⁺ + Cl⁻ \"zitiert\" \\ Backslash");

check('A6 leer ("") -> null', null === pj(''));
check('A7 null -> null', null === pj(null));
check('A8 Array statt Text -> false', false === pj(array('a')));

$gross = geslasht('{"v":1,"breite":1,"hoehe":1,"elemente":[{"id":"e1","typ":"text","farbe":"#000","breite":1,"x":0,"y":0,"text":"'
    . str_repeat('a', 8000001) . '"}]}');
check('A9 > 8 000 000 Bytes -> false', false === pj($gross));

check('A10 v = 2 -> false', false === pj('{"v":2,"breite":1,"hoehe":1,"elemente":[]}'));
check('A11 v = "1" -> false', false === pj(geslasht('{"v":"1","breite":1,"hoehe":1,"elemente":[]}')));
check('A12 kaputtes JSON -> false', false === pj('{"v":1,'));
check('A13 kein Objekt (Zahl) -> false', false === pj('5'));
check('A14 elemente kein Array -> false', false === pj('{"v":1,"breite":1,"hoehe":1,"elemente":"x"}'));
check('A15 breite fehlt -> false', false === pj('{"v":1,"hoehe":1,"elemente":[]}'));

$strich = array('id' => 'e1', 'typ' => 'strich', 'werkzeug' => 'pen', 'farbe' => '#000', 'breite' => 2,
    'punkte' => array(array(1, 2), array(3, 4)));
check('A16 gültiger Einzelstrich bleibt', 1 === anzahl(pj(geslasht(mit_element($strich)))));

foreach (array('red; x', 'red', 'url(javascript:alert(1))', '#12', '#1234567', 'rgb(1,2)', 'rgba(1,2,3,4);x', '#000"><script>') as $i => $f) {
    $s = $strich;
    $s['farbe'] = $f;
    check("A17.$i ungültige Farbe " . json_encode($f) . ' -> Element verworfen', null === pj(geslasht(mit_element($s))) || 0 === anzahl(pj(geslasht(mit_element($s)))), pj(geslasht(mit_element($s))));
}
foreach (array('#abc', '#A1b2C3', 'rgb(1, 2, 3)', 'rgba(255, 255, 200, 1.0)') as $i => $f) {
    $s = $strich;
    $s['farbe'] = $f;
    check("A18.$i gültige Farbe " . json_encode($f) . ' bleibt', 1 === anzahl(pj(geslasht(mit_element($s)))));
}

foreach (array('javascript:alert(1)', 'data:image/svg+xml;base64,PHN2Zz4=', 'data:image/png;base64,abc"><x', 'data:image/png;base64,ab cd', 42) as $i => $daten) {
    $b = array('id' => 'e1', 'typ' => 'bild', 'daten' => $daten, 'x' => 0, 'y' => 0, 'b' => 10, 'h' => 10);
    $e = pj(geslasht(mit_element($b)));
    check("A19.$i Bild mit " . json_encode(substr((string) $daten, 0, 24)) . ' -> verworfen', null === $e || 0 === anzahl($e), $e);
}
$b = array('id' => 'e1', 'typ' => 'bild', 'daten' => $bild, 'x' => 0, 'y' => 0, 'b' => 10, 'h' => 10);
check('A20 gültiges Bild bleibt', 1 === anzahl(pj(geslasht(mit_element($b)))));

// Unbekannte Felder und unbekannte Typen
$s = $strich;
$s['onclick'] = 'boese()';
$e = pj(geslasht(mit_element($s)));
$d = is_string($e) ? json_decode($e, true) : null;
check('A21 unbekanntes Feld im Element entfernt', $d && !array_key_exists('onclick', $d['elemente'][0]), $e);
$e = pj(geslasht('{"v":1,"breite":1,"hoehe":1,"extra":"x","elemente":[' . json_encode($strich) . ']}'));
$d = is_string($e) ? json_decode($e, true) : null;
check('A22 unbekanntes Feld oben entfernt', $d && !array_key_exists('extra', $d), $e);
$e = pj(geslasht('{"v":1,"breite":1,"hoehe":1,"elemente":[' . json_encode($strich) . ',{"id":"e2","typ":"kreis","x":1}]}'));
check('A23 typ "kreis" verworfen, Rest bleibt', 1 === anzahl($e), $e);

// Zahlen
foreach (array('"12"' => true, '"abc"' => false, '1e999' => false, 'null' => false, 'true' => false) as $wert => $gueltig) {
    $roh = '{"v":1,"breite":1,"hoehe":1,"elemente":[{"id":"e1","typ":"text","farbe":"#000","breite":1,"text":"t","x":' . $wert . ',"y":0}]}';
    $e = pj(geslasht($roh));
    check('A24 Koordinate ' . $wert . ' -> ' . ($gueltig ? 'akzeptiert (is_numeric)' : 'verworfen'),
        $gueltig ? (1 === anzahl($e)) : (null === $e || 0 === anzahl($e)), $e);
}
$e = pj(geslasht('{"v":1,"breite":1,"hoehe":1,"elemente":[{"id":"e1","typ":"text","farbe":"#000","breite":1,"text":"t","x":"12","y":0}]}'));
$d = is_string($e) ? json_decode($e, true) : null;
check('A25 numerischer String wird als Zahl ausgegeben', $d && $d['elemente'][0]['x'] === 12, $e);

// Text
$t = array('id' => 'e1', 'typ' => 'text', 'farbe' => '#000', 'breite' => 1, 'text' => str_repeat('ä', 5010), 'x' => 0, 'y' => 0);
$e = pj(geslasht(mit_element($t)));
$d = is_string($e) ? json_decode($e, true) : null;
check('A26 Text > 5000 Zeichen per mb_substr auf 5000 Zeichen gekürzt', $d && mb_strlen($d['elemente'][0]['text'], 'UTF-8') === 5000, $d ? mb_strlen($d['elemente'][0]['text'], 'UTF-8') : $e);
$t['text'] = 12;
check('A27 Text keine Zeichenkette -> verworfen', null === pj(geslasht(mit_element($t))) || 0 === anzahl(pj(geslasht(mit_element($t)))));

// Striche
$s = $strich;
$s['werkzeug'] = 'eraser-point';
check('A28 werkzeug außerhalb {pen, highlighter} verworfen', 0 === (int) anzahl(pj(geslasht(mit_element($s)))));
$s = $strich;
$s['punkte'] = array();
for ($i = 0; $i < 20001; $i++) { $s['punkte'][] = array($i % 100, 1); }
check('A29 Strich mit 20 001 Punkten verworfen', 0 === (int) anzahl(pj(geslasht(mit_element($s)))));
$s = $strich;
$s['punkte'] = array(array(1, 2, 3));
check('A30 Punkt mit drei Koordinaten verworfen', 0 === (int) anzahl(pj(geslasht(mit_element($s)))));
$s = $strich;
$s['id'] = 'x1';
check('A31 id nicht im Format e<Zahl> verworfen', 0 === (int) anzahl(pj(geslasht(mit_element($s)))));
$e = pj(geslasht('{"v":1,"breite":1,"hoehe":1,"elemente":[' . json_encode($strich) . ',' . json_encode($strich) . ']}'));
check('A32 doppelte id: zweites Element verworfen', 1 === anzahl($e), $e);

check('A33 leere Elementliste -> null (Spalte NULL)', null === pj('{"v":1,"breite":1,"hoehe":1,"elemente":[]}'));
check('A34 alle Elemente ungültig -> null', null === pj(geslasht('{"v":1,"breite":1,"hoehe":1,"elemente":[{"id":"e1","typ":"kreis"}]}')));

// =========================================================================
echo "\nB: ajax_save_drawing()\n";
// =========================================================================

function speichern(array $post) {
    $_POST = array_merge(array('nonce' => 'n', 'class_id' => '3', 'page_id' => '5663', 'container_id' => 'cbd-abc',
        'drawing_data' => 'data:image/png;base64,AAAA'), $post);
    $GLOBALS['test_json'] = null;
    $GLOBALS['test_actions'] = array();
    try {
        CBD_Classroom::get_instance()->ajax_save_drawing();
    } catch (RuntimeException $e) {
        // wp_send_json_* beendet hier den Aufruf
    }
    return $GLOBALS['test_json'];
}

$wpdb = $GLOBALS['wpdb'];

// B1: neue Zeile mit gültigem elemente_data
$wpdb->zeile = null;
$antwort = speichern(array('elemente_data' => geslasht($json)));
check('B1 Antwort Erfolg', $antwort && $antwort['ok']);
check('B2 insert enthält bereinigte JSON', $wpdb->zeile && json_decode((string) $wpdb->zeile['elemente_data'], true) === json_decode($json, true));
check('B3 Antwort ohne elemente_verworfen', $antwort && empty($antwort['data']['elemente_verworfen']));
check('B4 message bleibt', $antwort && $antwort['data']['message'] === 'Zeichnung gespeichert.');
check('B5 do_action cbd_klassenmodus_geaendert weiterhin ausgelöst', in_array('cbd_klassenmodus_geaendert', $GLOBALS['test_actions'], true));

// B6: alter Client ohne elemente_data -> Spalte NULL (update)
$antwort = speichern(array());
$letzter = end($wpdb->schreibvorgaenge);
check('B6 alter Client: update setzt elemente_data auf NULL',
    $letzter[0] === 'update' && array_key_exists('elemente_data', $letzter[1]) && null === $letzter[1]['elemente_data'], $letzter);
check('B7 alter Client: drawing_data wie bisher', $letzter[1]['drawing_data'] === 'data:image/png;base64,AAAA');

// B8: leerer Canvas -> drawing_data NULL und elemente_data NULL, auch wenn Elemente mitkommen
$antwort = speichern(array('drawing_data' => '', 'elemente_data' => geslasht($json)));
$letzter = end($wpdb->schreibvorgaenge);
check('B8 leerer Canvas: elemente_data NULL', null === $letzter[1]['elemente_data'] && null === $letzter[1]['drawing_data'], $letzter);

// B9: ungültiges elemente_data -> NULL + elemente_verworfen
$antwort = speichern(array('elemente_data' => '{"v":2}'));
$letzter = end($wpdb->schreibvorgaenge);
check('B9 ungültig: elemente_data NULL', null === $letzter[1]['elemente_data']);
check('B10 ungültig: Antwort elemente_verworfen = true', $antwort && true === ($antwort['data']['elemente_verworfen'] ?? null), $antwort);
check('B11 ungültig: PNG trotzdem gespeichert', $wpdb->zeile['drawing_data'] === 'data:image/png;base64,AAAA');

// B12: Spalte fehlt, Selbstheilung klappt
$wpdb->spalte = false;
$wpdb->reparierbar = true;
CBD_Schema_Manager::$aufrufe = 0;
$vorher = count($wpdb->schreibvorgaenge);
$antwort = speichern(array('elemente_data' => geslasht($json)));
check('B12 Spalte fehlt: ensure_columns() genau einmal', 1 === CBD_Schema_Manager::$aufrufe, CBD_Schema_Manager::$aufrufe);
check('B13 Spalte fehlt: zweiter Versuch schreibt elemente_data', $wpdb->spalte && json_decode((string) $wpdb->zeile['elemente_data'], true) === json_decode($json, true));
check('B14 Spalte fehlt: genau zwei Schreibversuche', 2 === count($wpdb->schreibvorgaenge) - $vorher);
check('B15 Selbstheilung erfolgreich: kein elemente_verworfen', $antwort && empty($antwort['data']['elemente_verworfen']), $antwort);

// B16: Spalte fehlt und lässt sich nicht anlegen -> ohne Spalte speichern
$wpdb->spalte = false;
$wpdb->reparierbar = false;
$wpdb->zeile = null;
CBD_Schema_Manager::$aufrufe = 0;
$vorher = count($wpdb->schreibvorgaenge);
$antwort = speichern(array('elemente_data' => geslasht($json)));
check('B16 nicht reparierbar: PNG trotzdem gespeichert (insert ohne Spalte)',
    $wpdb->zeile && $wpdb->zeile['drawing_data'] === 'data:image/png;base64,AAAA', $wpdb->zeile);
$letzter = end($wpdb->schreibvorgaenge);
check('B17 nicht reparierbar: letzter Versuch ohne elemente_data', !array_key_exists('elemente_data', $letzter[1]));
check('B18 nicht reparierbar: Antwort elemente_verworfen = true', $antwort && true === ($antwort['data']['elemente_verworfen'] ?? null), $antwort);
check('B19 nicht reparierbar: drei Schreibversuche, ein ensure_columns()', 3 === count($wpdb->schreibvorgaenge) - $vorher && 1 === CBD_Schema_Manager::$aufrufe);

// B20: alter Client auf alter DB (ohne Spalte) -> PNG gespeichert, nichts gemeldet? (Spalte nicht nötig)
$wpdb->spalte = false;
$wpdb->reparierbar = false;
$antwort = speichern(array());
check('B20 alter Client auf DB ohne Spalte: Erfolg, PNG gespeichert', $antwort && $antwort['ok'] && $wpdb->zeile['drawing_data'] === 'data:image/png;base64,AAAA');

// =========================================================================
echo "\nC: ajax_load_drawing()\n";
// =========================================================================

function laden() {
    $_POST = array('nonce' => 'n', 'class_id' => '3', 'page_id' => '5663', 'container_id' => 'cbd-abc');
    $GLOBALS['test_json'] = null;
    try {
        CBD_Classroom::get_instance()->ajax_load_drawing();
    } catch (RuntimeException $e) {
    }
    return $GLOBALS['test_json'];
}

function cache_leeren() {
    $p = new ReflectionProperty('CBD_Classroom', 'elemente_spalte_vorhanden');
    $p->setAccessible(true);
    $p->setValue(null, null);
}

$wpdb->spalte = true;
$wpdb->zeile = array('id' => 1, 'drawing_data' => 'data:image/png;base64,BBBB', 'is_behandelt' => 1, 'elemente_data' => '{"v":1}');
cache_leeren();
$antwort = laden();
check('C1 Laden liefert elemente_data', $antwort && $antwort['ok'] && $antwort['data']['elemente_data'] === '{"v":1}', $antwort);
check('C2 Laden liefert drawing_data und is_behandelt wie bisher',
    $antwort && $antwort['data']['drawing_data'] === 'data:image/png;base64,BBBB' && true === $antwort['data']['is_behandelt']);

$wpdb->abfragen = array();
laden();
$show = 0;
foreach ($wpdb->abfragen as $q) { if (stripos($q, 'SHOW COLUMNS') !== false) { $show++; } }
check('C3 SHOW COLUMNS statisch zwischengespeichert (zweiter Aufruf ohne)', 0 === $show, $wpdb->abfragen);

// Alte DB ohne Spalte
$wpdb->spalte = false;
$wpdb->last_error = '';
cache_leeren();
$wpdb->abfragen = array();
$antwort = laden();
check('C4 alte DB: Laden gelingt', $antwort && $antwort['ok'] && $antwort['data']['drawing_data'] === 'data:image/png;base64,BBBB', $antwort);
check('C5 alte DB: elemente_data = null', $antwort && array_key_exists('elemente_data', $antwort['data']) && null === $antwort['data']['elemente_data']);
$select_mit = false;
foreach ($wpdb->abfragen as $q) {
    if (stripos($q, 'SELECT') === 0 && stripos($q, 'elemente_data') !== false) { $select_mit = true; }
}
check('C6 alte DB: SELECT ohne elemente_data', !$select_mit, $wpdb->abfragen);

// Keine Zeile
$wpdb->spalte = true;
$wpdb->zeile = null;
cache_leeren();
$antwort = laden();
check('C7 keine Zeichnung: elemente_data = null', $antwort && $antwort['ok'] && null === $antwort['data']['elemente_data']);

echo "\nErgebnis: {$GLOBALS['oks']} OK, {$GLOBALS['fails']} FAIL\n";
exit($GLOBALS['fails'] > 0 ? 1 : 0);
