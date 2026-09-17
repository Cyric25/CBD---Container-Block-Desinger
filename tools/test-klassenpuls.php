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
 *   D  Wächter gegen eine zweite Token-Deutung — textbasiert   (8 Prüfungen)
 *   E  Pulsdatei: Adresse und Pfad   — AP-1.2                  (7 Prüfungen)
 *   F  Pulsdatei: Inhalt und Schreiben — AP-1.3/AP-1.4        (10 Prüfungen)
 *
 * Gesamt: 41 Prüfungen.
 *
 * AP-1.1 (Vorhaben „Schneller Klassenpuls") schreibt die Gruppen D7/D8, E und
 * F nach TDD **vor** der Implementierung. Solange die geprüften Methoden in
 * class-cbd-klassenpuls.php fehlen, ist ein rotes Ergebnis das gewünschte,
 * korrekte Ergebnis dieses APs — die Gruppen A bis C müssen dabei grün
 * bleiben, sie prüfen Bestandscode.
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

/**
 * Fester Salt-Wert. E3 rechnet den HMAC-Anteil des Dateinamens nach — mit
 * einem zufaelligen Salt waere die Erwartung nicht bestimmbar, und die
 * Pruefung wuerde nur ihre eigene Rechnung gegen sich selbst halten.
 */
function wp_salt($schema = 'auth') { return 'test-salt-0123456789'; }

/** Wurzel des temporaeren Upload-Verzeichnisses dieser Pruefung. */
function cbd_test_uploads_basis() {
    return rtrim(str_replace(DIRECTORY_SEPARATOR, '/', sys_get_temp_dir()), '/') . '/cbd-test-uploads';
}

function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false) {
    return array(
        'basedir' => cbd_test_uploads_basis(),
        'baseurl' => 'http://example.test/wp-content/uploads',
        'error'   => false,
    );
}

function wp_mkdir_p($pfad) {
    return is_dir($pfad) || @mkdir($pfad, 0777, true);
}

function wp_json_encode($daten, $optionen = 0, $tiefe = 512) {
    return json_encode($daten, $optionen, $tiefe);
}

/** Verzeichnis samt Inhalt entfernen — auch nach fehlgeschlagenen Pruefungen. */
function cbd_test_raeume_uploads_auf() {
    $wurzel = cbd_test_uploads_basis();
    if (!is_dir($wurzel)) {
        return;
    }
    $eintraege = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($wurzel, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($eintraege as $eintrag) {
        if ($eintrag->isDir()) {
            @rmdir($eintrag->getPathname());
        } else {
            @unlink($eintrag->getPathname());
        }
    }
    @rmdir($wurzel);
}

/**
 * Minimal-Attrappe fuer $wpdb.
 *
 * Gruppen A-D kamen ohne aus; ab Gruppe F laufen mit signatur_klasse() und
 * signatur_fragenwand() erstmals Methoden, die `global $wpdb` benutzen —
 * ohne diese Attrappe waere das ein Fatal Error, kein roter Test.
 *
 * Die Vorgabe ist bewusst "kein Ergebnis": Der Produktivcode behandelt das
 * wie "nichts vorhanden" und liefert trotzdem eine gueltige Signatur. Genau
 * diese stille Richtung soll der Harnisch abbilden.
 */
class CBD_Test_WPDB {
    public $prefix = 'wp_';
    public function prepare($sql, ...$args) {
        return $sql . ' /* ' . implode(',', array_map('strval', $args)) . ' */';
    }
    public function get_row($sql = null, $output = null, $y = 0) {
        return isset($GLOBALS['test_db_row']) ? $GLOBALS['test_db_row'] : null;
    }
    public function get_results($sql = null, $output = null) {
        return isset($GLOBALS['test_db_results']) ? $GLOBALS['test_db_results'] : array();
    }
    public function get_var($sql = null, $x = 0, $y = 0) {
        return isset($GLOBALS['test_db_var']) ? $GLOBALS['test_db_var'] : null;
    }
}

$GLOBALS['wpdb']            = new CBD_Test_WPDB();
$GLOBALS['test_db_row']     = null;
$GLOBALS['test_db_results'] = array();

// Reste eines abgebrochenen Vorlaufs zuerst wegraeumen.
cbd_test_raeume_uploads_auf();

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

// D7/D8 kamen mit AP-1.1 dazu. Sie halten die Capability-URL der Pulsdatei
// (Vorhaben „Schneller Klassenpuls") davon ab, ein zweiter Auth-Pfad zu
// werden: Die Sitzung kommt weiterhin ausschliesslich aus dem Gate, und der
// Dateiname ist wirklich abgeleitet statt aus der Klassen-ID geraten.
check('D7 · kein $_GET (die Sitzung kommt nur aus dem Gate)', $hat_klassenpuls_datei && false === strpos($klassenpuls_quelltext, '$_GET'));
check('D8 · nutzt hash_hmac und wp_salt (unerratbarer Dateiname)', $hat_klassenpuls_datei && false !== strpos($klassenpuls_quelltext, 'hash_hmac') && false !== strpos($klassenpuls_quelltext, 'wp_salt'));


/**
 * Baut eine Seiten-Abbildung mit $anzahl Eintraegen, wie sie
 * signaturen_alle_seiten() liefern wird: page_id => array(seite, tafel).
 */
function cbd_test_seiten($anzahl) {
    $seiten = array();
    for ($i = 1; $i <= $anzahl; $i++) {
        $seiten[(string) $i] = array('aaaaaaaaaaaa', 'bbbbbbbbbbbb');
    }
    return $seiten;
}

echo "\n== Gruppe E: Pulsdatei — Adresse und Pfad (AP-1.2) ==\n";

$uploads = wp_upload_dir();
$nameE   = ruf_statisch('CBD_Klassenpuls', 'pulsdatei_name', array(15));
$formE   = is_string($nameE) && 0 === strpos($nameE, 'puls-15-') && '.json' === substr($nameE, -5);

check('E1 · pulsdatei_name(15) beginnt mit "puls-15-" und endet auf ".json"', $formE, $nameE);

$hmacE = $formE ? substr($nameE, strlen('puls-15-'), -5) : '';
check('E2 · der Anteil dazwischen ist genau 16 Zeichen aus [0-9a-f]',
    1 === preg_match('/^[0-9a-f]{16}$/', $hmacE), $hmacE);

$erwartetE = substr(hash_hmac('sha256', 'klassenpuls|15', wp_salt('auth')), 0, 16);
check('E3 · dieser Anteil entspricht dem nachgerechneten HMAC',
    '' !== $hmacE && $hmacE === $erwartetE, array('ist' => $hmacE, 'soll' => $erwartetE));

$nameE16 = ruf_statisch('CBD_Klassenpuls', 'pulsdatei_name', array(16));
check('E4 · Klasse 15 und Klasse 16 ergeben verschiedene Namen',
    $formE && is_string($nameE16) && $nameE !== $nameE16, array($nameE, $nameE16));

$nameE0  = ruf_statisch('CBD_Klassenpuls', 'pulsdatei_name', array(0));
$nameEm1 = ruf_statisch('CBD_Klassenpuls', 'pulsdatei_name', array(-1));
check('E5 · unplausible Klassen-ID (0, -1) ergibt einen leeren Namen',
    '' === $nameE0 && '' === $nameEm1, array($nameE0, $nameEm1));

$pfadE = ruf_statisch('CBD_Klassenpuls', 'pulsdatei_pfad', array(15));
check('E6 · pulsdatei_pfad(15) liegt unter basedir + /container-block-designer/klassenpuls/',
    is_string($pfadE) && $formE
        && false !== strpos($pfadE, $uploads['basedir'])
        && false !== strpos($pfadE, '/container-block-designer/klassenpuls/' . $nameE),
    $pfadE);

$urlE = ruf_statisch('CBD_Klassenpuls', 'pulsdatei_url', array(15));
check('E7 · pulsdatei_url(15) nutzt baseurl, denselben Namen und keinen Backslash',
    is_string($urlE) && $formE
        && 0 === strpos($urlE, $uploads['baseurl'])
        && false !== strpos($urlE, $nameE)
        && false === strpos($urlE, chr(92)),
    $urlE);

echo "\n== Gruppe F: Pulsdatei — Inhalt und Schreiben (AP-1.3/AP-1.4) ==\n";

$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => '10');

$schluesselSoll = array('klasse', 'fragenwand', 'seiten', 'takt', 'stand');
sort($schluesselSoll);

$dF1 = ruf_statisch('CBD_Klassenpuls', 'baue_pulsdaten', array(15, array()));
$keysF1 = is_array($dF1) ? array_keys($dF1) : array();
sort($keysF1);
check('F1 · baue_pulsdaten() liefert genau klasse/fragenwand/seiten/takt/stand',
    $keysF1 === $schluesselSoll, $keysF1);

check('F2 · "stand" ist ein Integer groesser 0',
    is_array($dF1) && isset($dF1['stand']) && is_int($dF1['stand']) && $dF1['stand'] > 0,
    is_array($dF1) && isset($dF1['stand']) ? $dF1['stand'] : null);

// F3 prueft die Regel „weglassen statt kuerzen": Die Zahl der Eintraege unter
// „seiten" ist entweder 0 oder die volle Zahl, niemals etwas dazwischen —
// dieselbe Fehlerklasse, gegen die sich das Vorgaenger-Vorhaben schon beim
// GROUP_CONCAT entschieden hat (stilles Abschneiden friert die Signatur ein).
$dF3 = ruf_statisch('CBD_Klassenpuls', 'baue_pulsdaten', array(15, cbd_test_seiten(401)));
$anzahlF3 = (is_array($dF3) && isset($dF3['seiten']) && is_array($dF3['seiten']))
    ? count($dF3['seiten']) : 0;
check('F3 · 401 Seiten: "seiten" faellt GANZ weg, "seiten_unvollstaendig" ist true',
    is_array($dF3)
        && !isset($dF3['seiten'])
        && isset($dF3['seiten_unvollstaendig'])
        && true === $dF3['seiten_unvollstaendig']
        && (0 === $anzahlF3 || 401 === $anzahlF3),
    is_array($dF3) ? array_keys($dF3) : $dF3);

$dF4 = ruf_statisch('CBD_Klassenpuls', 'baue_pulsdaten', array(15, cbd_test_seiten(400)));
check('F4 · 400 Seiten: "seiten" ist vollstaendig da, kein Unvollstaendig-Kennzeichen',
    is_array($dF4) && isset($dF4['seiten']) && is_array($dF4['seiten'])
        && 400 === count($dF4['seiten'])
        && !isset($dF4['seiten_unvollstaendig']),
    is_array($dF4) ? array_keys($dF4) : $dF4);

$jsonF5 = is_array($dF1) ? json_encode($dF1) : '';
check('F5 · JSON einer Klasse ohne Seiten ist kuerzer als 300 Byte',
    is_string($jsonF5) && '' !== $jsonF5 && strlen($jsonF5) < 300, strlen((string) $jsonF5));

$okF6     = ruf_statisch('CBD_Klassenpuls', 'schreibe_pulsdatei', array(15));
// Nicht nur auf is_string() pruefen: ruf_statisch() liefert bei fehlender
// Methode die Zeichenkette "### METHODE FEHLT: ...", und dirname() macht
// daraus '.' - das aktuelle Verzeichnis, das immer existiert. F7 waere
// dadurch gruen geworden, ohne je ein Pulsverzeichnis gesehen zu haben.
$pfadF6   = (is_string($pfadE) && false !== strpos($pfadE, $uploads['basedir'])) ? $pfadE : '';
$inhaltF6 = ('' !== $pfadF6 && is_file($pfadF6))
    ? json_decode(file_get_contents($pfadF6), true) : null;
$keysF6 = is_array($inhaltF6) ? array_keys($inhaltF6) : array();
sort($keysF6);
check('F6 · schreibe_pulsdatei(15) legt gueltiges JSON mit den F1-Schluesseln an',
    true === $okF6 && $keysF6 === $schluesselSoll,
    array('rueckgabe' => $okF6, 'schluessel' => $keysF6));

$verzF7   = ('' !== $pfadF6) ? dirname($pfadF6) : '';
$verzDaF7 = ('' !== $verzF7 && is_dir($verzF7));
$tmpF7    = $verzDaF7 ? glob($verzF7 . '/*.tmp') : array();
// Das Vorhandensein des Verzeichnisses ist Teil der Bedingung, nicht nur
// Vorbedingung: Ohne diese Forderung waere F7 schon VOR der Umsetzung gruen —
// kein Verzeichnis, also trivialerweise keine .tmp-Datei. Eine Pruefung, die
// gruen ist, weil nichts geschehen ist, prueft nichts.
check('F7 · Verzeichnis existiert und enthaelt keine .tmp-Datei (Zwischendatei umbenannt)',
    $verzDaF7 && is_array($tmpF7) && 0 === count($tmpF7),
    array('verzeichnis_da' => $verzDaF7, 'tmp' => $tmpF7));

// Vor der Notbremsen-Pruefung die vorhandene Datei entfernen, sonst prueft F8
// einen Altbestand aus F6 statt des Verhaltens bei Takt 0.
if ('' !== $pfadF6 && is_file($pfadF6)) {
    @unlink($pfadF6);
}
$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => '0');
$okF8 = ruf_statisch('CBD_Klassenpuls', 'schreibe_pulsdatei', array(15));
check('F8 · bei cbd_klassenpuls_takt = 0 entsteht KEINE Datei, Rueckgabe false',
    false === $okF8 && ('' === $pfadF6 || !is_file($pfadF6)),
    array('rueckgabe' => $okF8, 'datei_da' => ('' !== $pfadF6 && is_file($pfadF6))));
$GLOBALS['test_options'] = array('cbd_klassenpuls_takt' => '10');

// Fuer F9 eine Datei anlegen, damit der erste Loeschversuch etwas vorfindet.
ruf_statisch('CBD_Klassenpuls', 'schreibe_pulsdatei', array(15));
$GLOBALS['test_warnungen'] = 0;
set_error_handler(function ($nr, $text, $datei = '', $zeile = 0) {
    $GLOBALS['test_warnungen']++;
    return true;
});
$loesch1 = ruf_statisch('CBD_Klassenpuls', 'loesche_pulsdatei', array(15));
$loesch2 = ruf_statisch('CBD_Klassenpuls', 'loesche_pulsdatei', array(15));
restore_error_handler();
check('F9 · loeschen: erster Aufruf true, zweiter false, dabei keine PHP-Warnung',
    true === $loesch1 && false === $loesch2 && 0 === $GLOBALS['test_warnungen'],
    array('erster' => $loesch1, 'zweiter' => $loesch2, 'warnungen' => $GLOBALS['test_warnungen']));

$htaccessF10 = ('' !== $verzF7) ? $verzF7 . '/.htaccess' : '';
$indexF10    = ('' !== $verzF7) ? $verzF7 . '/index.php' : '';
$inhaltHt    = ('' !== $htaccessF10 && is_file($htaccessF10)) ? file_get_contents($htaccessF10) : '';
check('F10 · .htaccess mit "Options -Indexes" und leere index.php im Verzeichnis',
    is_string($inhaltHt) && false !== strpos($inhaltHt, 'Options -Indexes')
        && '' !== $indexF10 && is_file($indexF10) && 0 === filesize($indexF10),
    array('htaccess' => $inhaltHt, 'index_da' => ('' !== $indexF10 && is_file($indexF10))));

// Das temporaere Upload-Verzeichnis IMMER wegraeumen — auch nach
// fehlgeschlagenen Pruefungen, also vor dem abschliessenden exit.
cbd_test_raeume_uploads_auf();

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
