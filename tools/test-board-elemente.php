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
    // AP-4.fix1: Schreiben mit nicht-leerem elemente_data scheitert aus einem
    // ANDEREN Grund als einer fehlenden Spalte (z. B. max_allowed_packet).
    public $fehler_mit_elementen = false;
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
        if ($this->fehler_mit_elementen && isset($daten['elemente_data'])) {
            $this->last_error = "Got a packet bigger than 'max_allowed_packet' bytes";
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
/**
 * AP-4.fix2: Die Spalte elemente_data hält die Liste in einer Hülle
 * {"png":"<md5 des gespeicherten drawing_data>","modell":<bereinigte JSON>}.
 * Liefert das Modell (Array), wenn die Hülle stimmt und der Abdruck zum PNG
 * passt; sonst eine Fehlerbeschreibung (String).
 */
function modell_aus_zeile($wert, $png) {
    $h = json_decode((string) $wert, true);
    if (!is_array($h) || !isset($h['png'], $h['modell']) || 2 !== count($h)) {
        return 'keine Hülle: ' . substr((string) $wert, 0, 40);
    }
    if ($h['png'] !== md5((string) $png)) {
        return 'Abdruck passt nicht zum PNG';
    }
    if (0 !== strpos((string) $wert, '{"png":"' . $h['png'] . '","modell":')) {
        return 'Hülle nicht in der festen Form';
    }
    return $h['modell'];
}

/** Hülle so bauen, wie ajax_save_drawing() sie schreibt (für Ladeprüfungen). */
function huelle($png, $modell_text) {
    return '{"png":"' . md5($png) . '","modell":' . $modell_text . '}';
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
check('B2 insert enthält bereinigte JSON (AP-4.fix2: in der Hülle mit Abdruck des PNG)', $wpdb->zeile && modell_aus_zeile($wpdb->zeile['elemente_data'], $wpdb->zeile['drawing_data']) === json_decode($json, true), $wpdb->zeile ? modell_aus_zeile($wpdb->zeile['elemente_data'], $wpdb->zeile['drawing_data']) : null);
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
check('B13 Spalte fehlt: zweiter Versuch schreibt elemente_data (AP-4.fix2: in der Hülle)', $wpdb->spalte && modell_aus_zeile($wpdb->zeile['elemente_data'], $wpdb->zeile['drawing_data']) === json_decode($json, true));
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
$wpdb->zeile = array('id' => 1, 'drawing_data' => 'data:image/png;base64,BBBB', 'is_behandelt' => 1, 'elemente_data' => huelle('data:image/png;base64,BBBB', '{"v":1}'));
cache_leeren();
$antwort = laden();
check('C1 Laden liefert elemente_data (AP-4.fix2: Inhalt der Hülle, Abdruck passt)', $antwort && $antwort['ok'] && $antwort['data']['elemente_data'] === '{"v":1}', $antwort);
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

// =========================================================================
echo "\nD: Obergrenze Punkte und Speicherspitze (AP-4.fix1, Befund F1)\n";
// =========================================================================
// AP-4.rev F1: pruefe_elemente_json() brauchte ~33 MB je MB Strich-JSON; ab
// ~2-2,5 MB riss die Anfrage bei memory_limit 128M mit WordPress-Grundlast
// ab - VOR dem Schreiben, also ohne PNG. Jetzt: billige Schätzung der
// Punktzahl über die Zahl der '[' vor json_decode() (Bild-daten enthalten
// kein '['), darüber nur das PNG.

$max = defined('CBD_Classroom::ELEMENTE_MAX_PUNKTE') ? CBD_Classroom::ELEMENTE_MAX_PUNKTE : null;
check('D1 Konstante CBD_Classroom::ELEMENTE_MAX_PUNKTE = 100000', 100000 === $max, $max);

/**
 * Strich-JSON mit $striche Strichen à $je Punkte (zufällige Koordinaten mit
 * zwei Nachkommastellen wie serialisiere()). Anzahl '[' = 1 + striche*(1+je).
 */
function strich_json($striche, $je) {
    mt_srand(7149);
    $el = array();
    for ($s = 0; $s < $striche; $s++) {
        $p = array();
        for ($i = 0; $i < $je; $i++) {
            $p[] = array(mt_rand(0, 136600) / 100, mt_rand(0, 70000) / 100);
        }
        $el[] = array('id' => 'e' . ($s + 1), 'typ' => 'strich', 'werkzeug' => 'pen', 'farbe' => '#000000', 'breite' => 3, 'punkte' => $p);
    }
    return json_encode(array('v' => 1, 'breite' => 1366, 'hoehe' => 700, 'elemente' => $el));
}

/** Zusätzliche Speicherspitze (MB) eines Aufrufs; null, wenn nicht messbar. */
function spitze_mb($fn, &$ergebnis) {
    if (!function_exists('memory_reset_peak_usage')) {
        $ergebnis = $fn();
        return null;
    }
    gc_collect_cycles();
    $basis = memory_get_usage();
    memory_reset_peak_usage();
    $ergebnis = $fn();
    return (memory_get_peak_usage() - $basis) / 1048576;
}

// Genau an der Grenze: 500 Striche à 199 Punkte -> 1 + 500*200 = 100 001 '[' (zu viel),
// 499 Striche à 199 Punkte + 1 Strich à 198 -> knapp darunter
$unter = strich_json(500, 199);
$unter = preg_replace('/,\[[0-9.]+,[0-9.]+\]\]\}\]\}$/', ']}]}', $unter); // letzten Punkt entfernen -> 100 000 '['
check('D2 Testdaten: genau 100 000 \'[\'', 100000 === substr_count($unter, '['), substr_count($unter, '['));
$r = pj(geslasht($unter));
check('D3 genau an der Grenze (100 000 \'[\') -> akzeptiert', is_string($r), is_string($r) ? 'String' : $r);
check('D4 an der Grenze: Ergebnis inhaltsgleich zur Eingabe', is_string($r) && json_decode($r, true) === json_decode($unter, true));
unset($r);

$ueber = strich_json(500, 199);
check('D5 Testdaten: 100 001 \'[\'', 100001 === substr_count($ueber, '['), substr_count($ueber, '['));
check('D6 eine Klammer über der Grenze -> false (nur PNG)', false === pj(geslasht($ueber)));

// Speicherspitze knapp unter der Grenze. Ziel: mit WordPress-Grundlast
// (gemessen in AP-4.fix1, Übergabenotiz) sicher unter memory_limit 128M.
$roh = geslasht($unter);
unset($unter, $ueber);
$mb = spitze_mb(function () use ($roh) { return pj($roh); }, $r);
check('D7 Speicherspitze an der Grenze <= 32 MB' . (null === $mb ? ' (nicht messbar, PHP < 8.2)' : sprintf(' (gemessen %.1f MB)', $mb)),
    null === $mb || $mb <= 32, $mb);
unset($r, $roh);

// ~3 MB Strich-JSON (Befund F1: vorher ~101 MB Spitze, Fatal bei 128M)
$drei = strich_json(940, 199);
check('D8 Testdaten: >= 3 MB Strich-JSON', strlen($drei) >= 3000000, strlen($drei));
$roh = geslasht($drei);
unset($drei);
$mb = spitze_mb(function () use ($roh) { return pj($roh); }, $r);
check('D9 3 MB Strich-JSON -> false', false === $r, is_string($r) ? 'String' : $r);
check('D10 3 MB Strich-JSON: Speicherspitze <= 8 MB (kein json_decode)' . (null === $mb ? ' (nicht messbar, PHP < 8.2)' : sprintf(' (gemessen %.1f MB)', $mb)),
    null === $mb || $mb <= 8, $mb);

// Über ajax_save_drawing(): PNG gespeichert, elemente_data NULL, gemeldet
$wpdb->spalte = true;
$wpdb->reparierbar = true;
$wpdb->zeile = null;
$antwort = speichern(array('elemente_data' => $roh));
unset($roh);
check('D11 Speichern mit 3 MB Strich-JSON: Erfolg, PNG geschrieben',
    $antwort && $antwort['ok'] && $wpdb->zeile && $wpdb->zeile['drawing_data'] === 'data:image/png;base64,AAAA', $antwort);
check('D12 ... elemente_data NULL und elemente_verworfen = true',
    $wpdb->zeile && null === $wpdb->zeile['elemente_data'] && true === ($antwort['data']['elemente_verworfen'] ?? null));

// Gleiche Grenze im Browser (board-mode.js sendet darüber gar keine Liste)
$js = (string) @file_get_contents($plugin_dir . 'assets/js/board-mode.js');
$js_max = preg_match('/var\s+ELEMENTE_MAX_PUNKTE\s*=\s*(\d+)\s*;/', $js, $m) ? (int) $m[1] : null;
check('D13 board-mode.js: ELEMENTE_MAX_PUNKTE gleich der PHP-Konstante', null !== $max && $js_max === $max, $js_max);

// =========================================================================
echo "\nE: Schreibfehler mit Elementen -> PNG ohne Elemente (AP-4.fix1)\n";
// =========================================================================

// E1-E5: Zeile vorhanden (update), Schreiben mit Elementen scheitert (Paketgröße)
$wpdb->spalte = true;
$wpdb->reparierbar = true;
$wpdb->fehler_mit_elementen = true;
$wpdb->zeile = array('id' => 1, 'drawing_data' => 'data:image/png;base64,ALT', 'is_behandelt' => 0, 'elemente_data' => '{"alt":1}');
CBD_Schema_Manager::$aufrufe = 0;
$vorher = count($wpdb->schreibvorgaenge);
$antwort = speichern(array('elemente_data' => geslasht($json)));
$neu = array_slice($wpdb->schreibvorgaenge, $vorher);
check('E1 update scheitert mit Elementen: PNG trotzdem geschrieben', $wpdb->zeile['drawing_data'] === 'data:image/png;base64,AAAA', $wpdb->zeile['drawing_data']);
check('E2 ... zweiter Versuch mit elemente_data = NULL (alte Liste überschrieben)',
    2 === count($neu) && array_key_exists('elemente_data', $neu[1][1]) && null === $neu[1][1]['elemente_data'] && null === $wpdb->zeile['elemente_data'], $neu);
check('E3 ... Antwort Erfolg mit elemente_verworfen = true', $antwort && $antwort['ok'] && true === ($antwort['data']['elemente_verworfen'] ?? null), $antwort);
check('E4 ... kein ensure_columns() (Fehler betrifft nicht die Spalte)', 0 === CBD_Schema_Manager::$aufrufe, CBD_Schema_Manager::$aufrufe);
check('E5 ... do_action cbd_klassenmodus_geaendert weiterhin ausgelöst', in_array('cbd_klassenmodus_geaendert', $GLOBALS['test_actions'], true));

// E6: neue Zeile (insert) scheitert mit Elementen
$wpdb->zeile = null;
$antwort = speichern(array('elemente_data' => geslasht($json)));
check('E6 insert scheitert mit Elementen: Zeile mit PNG und elemente_data NULL',
    $wpdb->zeile && $wpdb->zeile['drawing_data'] === 'data:image/png;base64,AAAA' && null === $wpdb->zeile['elemente_data']
    && true === ($antwort['data']['elemente_verworfen'] ?? null), $wpdb->zeile);

// E7: ohne Elemente (alter Client) kein zusätzlicher Versuch
$vorher = count($wpdb->schreibvorgaenge);
speichern(array());
check('E7 alter Client: genau ein Schreibversuch', 1 === count($wpdb->schreibvorgaenge) - $vorher, count($wpdb->schreibvorgaenge) - $vorher);
$wpdb->fehler_mit_elementen = false;

// =========================================================================
echo "\nF: Fingerabdruck des PNG – veraltete Liste nach einem Rückweg (AP-4.fix2, Befund F2)\n";
// =========================================================================
// Rückweg: Eine ältere Plugin-Version (z. B. 3.1.138) schreibt nur
// drawing_data und updated_at; elemente_data bleibt stehen. Nach erneutem
// Einspielen darf die alte Liste das neuere PNG nicht überdecken.

$wpdb->spalte = true;
$wpdb->reparierbar = true;
$wpdb->zeile = null;
cache_leeren();
$png_neu = 'data:image/png;base64,TkVV';
speichern(array('drawing_data' => $png_neu, 'elemente_data' => geslasht($json)));
$roh_spalte = (string) $wpdb->zeile['elemente_data'];
check('F1 Speichern legt Hülle {"png":md5(drawing_data),"modell":…} ab',
    0 === strpos($roh_spalte, '{"png":"' . md5($png_neu) . '","modell":{') && '}' === substr($roh_spalte, -1), substr($roh_spalte, 0, 60));
check('F2 Modell in der Hülle = bereinigte JSON (Elementmodell v1 unverändert)',
    modell_aus_zeile($roh_spalte, $png_neu) === json_decode($json, true));

$antwort = laden();
check('F3 Laden: Abdruck passt -> elemente_data = bereinigte JSON OHNE Hülle',
    $antwort && $antwort['ok'] && is_string($antwort['data']['elemente_data'])
    && json_decode($antwort['data']['elemente_data'], true) === json_decode($json, true)
    && 0 === strpos($antwort['data']['elemente_data'], '{"v":1,'), $antwort ? substr((string) $antwort['data']['elemente_data'], 0, 60) : null);

// Ältere Plugin-Version überschreibt nur das PNG
$wpdb->zeile['drawing_data'] = 'data:image/png;base64,QUxURVZFUlNJT04=';
$antwort = laden();
check('F4 Rückweg: PNG von älterer Version geändert -> elemente_data = null',
    $antwort && $antwort['ok'] && null === $antwort['data']['elemente_data'], $antwort ? $antwort['data']['elemente_data'] : null);
check('F5 ... drawing_data = das neuere PNG (wird Bild-Element)',
    $antwort && $antwort['data']['drawing_data'] === 'data:image/png;base64,QUxURVZFUlNJT04=');

// Neues Speichern nach dem Rückweg: frische Hülle, wieder ladbar
speichern(array('drawing_data' => $png_neu, 'elemente_data' => geslasht($json)));
$antwort = laden();
check('F6 erneut gespeichert -> Liste wieder geladen',
    $antwort && is_string($antwort['data']['elemente_data']) && json_decode($antwort['data']['elemente_data'], true) === json_decode($json, true));

// Werte ohne Hülle (Phase-4-Teststände vor AP-4.fix2) gelten als ungültig
$wpdb->zeile['elemente_data'] = $json;
$antwort = laden();
check('F7 Wert ohne Hülle -> null', $antwort && null === $antwort['data']['elemente_data'], $antwort ? substr((string) $antwort['data']['elemente_data'], 0, 40) : null);

// Kaputte Hüllen
foreach (array(
    'abgeschnitten' => substr(huelle($png_neu, $json), 0, -1),
    'Abdruck kein md5' => '{"png":"xyz","modell":' . $json . '}',
    'Abdruck Großbuchstaben' => '{"png":"' . strtoupper(md5($png_neu)) . '","modell":' . $json . '}',
    'Leerzeichen in der Hülle' => '{"png": "' . md5($png_neu) . '","modell":' . $json . '}',
    'leer' => '',
) as $fall => $wert) {
    $wpdb->zeile['elemente_data'] = $wert;
    $antwort = laden();
    check("F8 Hülle $fall -> null", $antwort && null === $antwort['data']['elemente_data'], $antwort ? substr((string) $antwort['data']['elemente_data'], 0, 40) : null);
}

// Zeile ohne PNG (leere Tafel) mit Liste – darf nie Elemente liefern
$wpdb->zeile['drawing_data'] = null;
$wpdb->zeile['elemente_data'] = huelle('', $json);
$antwort = laden();
check('F9 drawing_data NULL -> elemente_data = null', $antwort && null === $antwort['data']['elemente_data']);

// Nach Rückfall ohne Liste (AP-4.fix1) bleibt keine Hülle stehen
$wpdb->zeile = null;
$wpdb->fehler_mit_elementen = true;
speichern(array('drawing_data' => $png_neu, 'elemente_data' => geslasht($json)));
$wpdb->fehler_mit_elementen = false;
check('F10 Schreibfehler-Rückfall: Spalte NULL, keine Hülle', $wpdb->zeile && null === $wpdb->zeile['elemente_data']);

echo "\nErgebnis: {$GLOBALS['oks']} OK, {$GLOBALS['fails']} FAIL\n";
exit($GLOBALS['fails'] > 0 ? 1 : 0);
