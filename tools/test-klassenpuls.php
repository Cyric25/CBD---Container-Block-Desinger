<?php
/**
 * Standalone-Harness für den Klassenpuls — läuft OHNE WordPress.
 *
 * Geprüft wird die Klasse CBD_Klassenpuls aus
 * includes/class-cbd-klassenpuls.php (Route cbd/v1/klassenpuls, AP-1.3 von
 * PLAN-Klassenmodus-Live.md). Dieses AP (AP-1.2) schreibt den Harnisch nach
 * TDD **vor** der Implementierung — solange class-cbd-klassenpuls.php fehlt,
 * ist ein rotes Ergebnis das gewünschte, korrekte Ergebnis dieses APs.
 *
 * Gruppen:
 *   A  baue_signatur()               — rein, ohne Datenbank (5 Prüfungen)
 *   B  takt()                        — Optionsauswertung      (7 Prüfungen)
 *   C  Konstanten und Registrierung  —                         (4 Prüfungen)
 *   D  Wächter gegen eine zweite Token-Deutung — textbasiert   (6 Prüfungen)
 *
 * Aufruf:  php tools/test-klassenpuls.php
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

$plugin_dir = str_replace('\\', '/', dirname(__DIR__)) . '/';
define('CBD_PLUGIN_DIR', $plugin_dir);

define('CBD_TABLE_CLASSES', 'wp_cbd_classes');
define('CBD_TABLE_CLASS_PAGES', 'wp_cbd_class_pages');
define('CBD_TABLE_DRAWINGS', 'wp_cbd_drawings');
define('CBD_TABLE_NOTES', 'wp_cbd_notes');

// --- WordPress-Stubs ------------------------------------------------------
function __($s, $d = null) { return $s; }
function add_action($t, $c, $p = 10, $a = 1) { return true; }
function get_option($k, $d = false) {
    return array_key_exists($k, $GLOBALS['test_options']) ? $GLOBALS['test_options'][$k] : $d;
}
function nocache_headers() { $GLOBALS['nocache_gerufen'] = true; }
function absint($n) { return abs((int) $n); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function rest_ensure_response($daten) {
    if ($daten instanceof WP_REST_Response) { return $daten; }
    return new WP_REST_Response($daten, 200);
}

/**
 * Legt jede registrierte Endpunkt-Definition flach in $GLOBALS['test_routes']
 * ab. WordPress erlaubt für register_rest_route() sowohl eine einzelne
 * Definition (Schlüssel 'methods'/'callback' direkt im Argumente-Array) als
 * auch eine Liste mehrerer Definitionen (z. B. GET und POST auf derselben
 * Route) — beide Formen landen hier in derselben flachen Liste.
 */
function register_rest_route($namespace, $route, $args = array()) {
    if (isset($args['methods']) || isset($args['callback'])) {
        $args = array($args);
    }
    foreach ($args as $eintrag) {
        if (!is_array($eintrag)) {
            continue;
        }
        $GLOBALS['test_routes'][] = array(
            'namespace'           => $namespace,
            'route'               => $route,
            'methods'             => isset($eintrag['methods']) ? $eintrag['methods'] : null,
            'permission_callback' => isset($eintrag['permission_callback']) ? $eintrag['permission_callback'] : null,
            'callback'            => isset($eintrag['callback']) ? $eintrag['callback'] : null,
            'args'                => isset($eintrag['args']) ? $eintrag['args'] : array(),
        );
    }
}

$GLOBALS['test_options']    = array();
$GLOBALS['test_routes']     = array();
$GLOBALS['nocache_gerufen'] = false;

// --- Minimal-Attrappen für die REST-Klassen -------------------------------

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
    public function __construct($params = array()) {
        $this->params = $params;
    }
    public function get_param($name) {
        return array_key_exists($name, $this->params) ? $this->params[$name] : null;
    }
}

// --- Die zu prüfende Datei -------------------------------------------------
// Existiert sie noch nicht (Stand AP-1.2), sollen die Tests sauber rot
// melden statt mit einem Fatal Error abzubrechen.
$klassenpuls_datei     = CBD_PLUGIN_DIR . 'includes/class-cbd-klassenpuls.php';
$hat_klassenpuls_datei = file_exists($klassenpuls_datei);
if ($hat_klassenpuls_datei) {
    require_once $klassenpuls_datei;
}
$klassenpuls_quelltext = $hat_klassenpuls_datei ? file_get_contents($klassenpuls_datei) : '';

// --- Prüfgerüst -------------------------------------------------------------

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

/**
 * Ruft eine statische Methode auf; liefert bei fehlender Klasse/Methode eine
 * erkennbare Zeichenkette statt eines Fatal Error — derselbe Helfer wie in
 * tools/test-classroom-gate.php.
 */
function ruf_statisch($klasse, $methode, $args = array()) {
    if (!method_exists($klasse, $methode)) {
        return '### METHODE FEHLT: ' . $klasse . '::' . $methode;
    }
    return call_user_func_array(array($klasse, $methode), $args);
}

/**
 * Liefert den Wert einer Klassenkonstante über Reflection; liefert bei
 * fehlender Klasse/Konstante eine erkennbare Zeichenkette statt eines Fatal
 * Error (ein direktes Klasse::KONSTANTE auf eine nicht existierende Klasse
 * wäre ein Fatal Error, kein sauber prüfbarer Fehlschlag).
 */
function konstante($klasse, $name) {
    if (!class_exists($klasse)) {
        return '### KLASSE FEHLT: ' . $klasse;
    }
    $ref = new ReflectionClass($klasse);
    if (!$ref->hasConstant($name)) {
        return '### KONSTANTE FEHLT: ' . $klasse . '::' . $name;
    }
    return $ref->getConstant($name);
}

/** true, wenn $s eine gültige Puls-Signatur ist: genau 12 Zeichen [0-9a-f]. */
function ist_signatur($s) {
    return is_string($s) && 1 === preg_match('/^[0-9a-f]{12}$/', $s);
}

echo "== Gruppe A: baue_signatur() (rein, ohne DB) ==\n";

$sA1a = ruf_statisch('CBD_Klassenpuls', 'baue_signatur', array(array(1, 2, 3)));
$sA1b = ruf_statisch('CBD_Klassenpuls', 'baue_signatur', array(array(1, 2, 3)));
check('A1 · gleiche Eingabe -> gleiche Ausgabe', $sA1a === $sA1b && ist_signatur($sA1a), array($sA1a, $sA1b));

$sA2 = ruf_statisch('CBD_Klassenpuls', 'baue_signatur', array(array(1, 2, 4)));
check('A2 · unterschiedliche Eingabe -> unterschiedliche Ausgabe', $sA1a !== $sA2, array($sA1a, $sA2));

check('A3 · Ausgabe ist genau 12 Zeichen aus [0-9a-f]', ist_signatur($sA1a), $sA1a);

$sA4null = ruf_statisch('CBD_Klassenpuls', 'baue_signatur', array(array(null, 'x')));
$sA4leer = ruf_statisch('CBD_Klassenpuls', 'baue_signatur', array(array('', 'x')));
check('A4 · null und "" an derselben Position ergeben dieselbe Signatur', $sA4null === $sA4leer && ist_signatur($sA4null), array($sA4null, $sA4leer));

$sA5a = ruf_statisch('CBD_Klassenpuls', 'baue_signatur', array(array(1, 2)));
$sA5b = ruf_statisch('CBD_Klassenpuls', 'baue_signatur', array(array(2, 1)));
check('A5 · die Reihenfolge zählt', $sA5a !== $sA5b, array($sA5a, $sA5b));

echo "\n== Gruppe B: takt() ==\n";

$GLOBALS['test_options'] = array();
check('B1 · Option nicht gesetzt -> 10', 10 === ruf_statisch('CBD_Klassenpuls', 'takt'), ruf_statisch('CBD_Klassenpuls', 'takt'));

$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => '20');
check('B2 · Option "20" (String) -> 20', 20 === ruf_statisch('CBD_Klassenpuls', 'takt'), ruf_statisch('CBD_Klassenpuls', 'takt'));

$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => '0');
check('B3 · Option "0" -> 0 (abgeschaltet)', 0 === ruf_statisch('CBD_Klassenpuls', 'takt'), ruf_statisch('CBD_Klassenpuls', 'takt'));

$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => '2');
check('B4 · Option "2" (unter dem Minimum 5) -> 5', 5 === ruf_statisch('CBD_Klassenpuls', 'takt'), ruf_statisch('CBD_Klassenpuls', 'takt'));

$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => '9999');
check('B5 · Option "9999" (über TAKT_MAX) -> 300', 300 === ruf_statisch('CBD_Klassenpuls', 'takt'), ruf_statisch('CBD_Klassenpuls', 'takt'));

$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => 'quatsch');
check('B6 · Option "quatsch" -> 10 (Vorgabe)', 10 === ruf_statisch('CBD_Klassenpuls', 'takt'), ruf_statisch('CBD_Klassenpuls', 'takt'));

$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => '-5');
check('B7 · Option "-5" -> 0 (alles Negative gilt als abgeschaltet)', 0 === ruf_statisch('CBD_Klassenpuls', 'takt'), ruf_statisch('CBD_Klassenpuls', 'takt'));

echo "\n== Gruppe C: Konstanten und Registrierung ==\n";

check('C1 · REST_NAMESPACE ist "cbd/v1"', 'cbd/v1' === konstante('CBD_Klassenpuls', 'REST_NAMESPACE'), konstante('CBD_Klassenpuls', 'REST_NAMESPACE'));
check('C2 · REST_ROUTE ist "/klassenpuls"', '/klassenpuls' === konstante('CBD_Klassenpuls', 'REST_ROUTE'), konstante('CBD_Klassenpuls', 'REST_ROUTE'));
check('C3 · FEHLERSTATUS ist 404', 404 === konstante('CBD_Klassenpuls', 'FEHLERSTATUS'), konstante('CBD_Klassenpuls', 'FEHLERSTATUS'));

$GLOBALS['test_routes'] = array();
ruf_statisch('CBD_Klassenpuls', 'register_routes');
$routen = $GLOBALS['test_routes'];
check(
    'C4 · register_routes() legt genau eine GET-Route mit permission_callback __return_true an',
    1 === count($routen)
        && isset($routen[0]['methods']) && 'GET' === $routen[0]['methods']
        && isset($routen[0]['permission_callback']) && '__return_true' === $routen[0]['permission_callback'],
    $routen
);

echo "\n== Gruppe D: Wächter gegen eine zweite Token-Deutung ==\n";
echo "   (ohne class-cbd-klassenpuls.php ist diese Gruppe zwangsläufig rot —\n";
echo "    ohne Datei lässt sich keine Zusicherung über ihren Inhalt treffen)\n";

check('D1 · kein get_transient (keine zweite Token-Deutung)', $hat_klassenpuls_datei && false === strpos($klassenpuls_quelltext, 'get_transient'));
check('D2 · keine Zeichenfolge cbd_classroom_', $hat_klassenpuls_datei && false === strpos($klassenpuls_quelltext, 'cbd_classroom_'));
check('D3 · verwendet CBD_Classroom_Gate::sitzung', $hat_klassenpuls_datei && false !== strpos($klassenpuls_quelltext, 'CBD_Classroom_Gate::sitzung'));
check('D4 · ruft nocache_headers auf', $hat_klassenpuls_datei && false !== strpos($klassenpuls_quelltext, 'nocache_headers'));
check('D5 · kein GROUP_CONCAT', $hat_klassenpuls_datei && false === strpos($klassenpuls_quelltext, 'GROUP_CONCAT'));
check('D6 · nutzt $wpdb->prepare', $hat_klassenpuls_datei && false !== strpos($klassenpuls_quelltext, '$wpdb->prepare'));

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
