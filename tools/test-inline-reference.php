<?php
/**
 * Standalone-Harness für die Serverseite des Inline-Verweises
 * (`CBD_Inline_Reference`) — läuft OHNE WordPress.
 *
 * Aufruf:  php tools/test-inline-reference.php
 *
 * Liegt unter tools/ und ist damit NICHT im Verteilungs-ZIP enthalten
 * (create-plugin-zip.js listet nur admin, assets, blocks, includes, vendor,
 * languages).
 *
 * WARUM DIESER HARNISCH DER WICHTIGSTE DES VORHABENS IST
 * `CBD_Inline_Reference::inhalt_auffrischen()` hängt an `the_content` und
 * schreibt damit in GESPEICHERTEN, produktiv vorhandenen Seiteninhalt. Ein
 * Fehler dort beschädigt fremdes Markup auf jeder Seite des Auftritts. Die
 * drei Wächter des Plans sind deshalb je eigene Prüfgruppe:
 *   1. Rückzug, sobald die Klassenzeichenkette gar nicht vorkommt (Gruppe 5)
 *   2. WP_HTML_Tag_Processor statt regulärer Ausdrücke (Gruppen 2 und 4)
 *   3. Inhalte, die der Filter NICHT betrifft, kommen ZEICHENGLEICH zurück
 *      (Gruppe 5 — jede Prüfung dort vergleicht mit `===`)
 *
 * REIHENFOLGE IST TEIL DES TESTS — an zwei Stellen:
 *   a) `WP_HTML_Tag_Processor` wird erst NACH Gruppe 4 bereitgestellt. Nur so
 *      lässt sich der Rückfall „Klasse fehlt" (WordPress vor 6.2) überhaupt
 *      prüfen; PHP kann eine geladene Klasse nicht wieder vergessen. Dasselbe
 *      Verfahren wie in tools/test-block-content-api.php mit den
 *      Theme-Funktionen.
 *   b) `CBD_Block_Reference` wird erst NACH Gruppe 3a als Doppel definiert.
 *      Davor ist prüfbar, dass ein fehlender Vertragspartner die
 *      Script-Registrierung überspringt statt einen Fatal Error zu erzeugen.
 *
 * WARUM EIN DOPPEL FÜR `CBD_Block_Reference` UND NICHT DIE ECHTE DATEI
 * Die Konstante `AUSWAHL_HANDLE` entsteht in AP-3.2, das parallel läuft. Ein
 * `require` der echten Datei machte diesen Harnisch vom Fortschritt eines
 * anderen Arbeitspakets abhängig. Das Doppel bildet genau den Vertrag ab, den
 * AP-3.2 zusagt.
 *
 * DER TAG-PROCESSOR IST NACH MÖGLICHKEIT DER ECHTE
 * Gesucht wird `wp-includes/html-api/class-wp-html-tag-processor.php` (siehe
 * `tag_processor_bereitstellen()`). Wird eine WordPress-Installation
 * gefunden, prüft der Harnisch gegen die ECHTE Klasse — das ist die einzige
 * Art, „fremdes Markup bleibt unangetastet" wirklich zu belegen. Ohne
 * Fundstelle springt ein bewusst schmales Doppel ein, damit der Harnisch auf
 * jeder Maschine läuft. Welcher Weg gilt, sagt die Ausgabe.
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

$plugin_dir = str_replace('\\', '/', dirname(__DIR__)) . '/';

define('ABSPATH', '/');
define('CBD_PLUGIN_DIR', $plugin_dir);
define('CBD_PLUGIN_URL', 'https://example.test/wp-content/plugins/container-block-designer/');
define('CBD_VERSION', '3.1.91');

// --- WordPress-Stubs ------------------------------------------------------

function __($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

/**
 * Bewusst nahezu identisch: Der Harnisch soll die Ausgabe des Filters
 * vergleichen können, ohne dass eine Escaping-Eigenheit die Erwartung
 * verschiebt. Entfernt werden nur Zeichen, die ein Attribut sprengen würden.
 */
function esc_url_raw($u, $p = null) {
    return str_replace(array(' ', '"', '<', '>', "\n", "\r", "\t"), '', (string) $u);
}
function esc_url($u, $p = null, $c = 'display') { return esc_url_raw($u); }

/** Von WP_HTML_Tag_Processor gebraucht, sobald die echte Klasse geladen ist. */
function wp_has_noncharacters($t) { return false; }
function wp_check_invalid_utf8($s, $strip = false) { return $s; }
function _doing_it_wrong($f, $m, $v) { $GLOBALS['test_doing_it_wrong']++; }
function wp_kses_uri_attributes() { return array('href', 'src', 'action', 'formaction', 'cite'); }

/** Registrierte Hooks sammeln — die Priorität ist ein Akzeptanzkriterium. */
function add_filter($tag, $callback, $prioritaet = 10, $args = 1) {
    $GLOBALS['test_filter'][] = array('tag' => $tag, 'cb' => $callback, 'prio' => $prioritaet);
    return true;
}
function add_action($tag, $callback, $prioritaet = 10, $args = 1) {
    $GLOBALS['test_action'][] = array('tag' => $tag, 'cb' => $callback, 'prio' => $prioritaet);
    return true;
}

/** Permalinks kommen aus einer Tabelle. Unbekannte ID -> false (wie WordPress). */
function get_permalink($id = 0, $leavename = false) {
    $id = (int) $id;
    return array_key_exists($id, $GLOBALS['test_permalinks']) ? $GLOBALS['test_permalinks'][$id] : false;
}

/** Ausserhalb des Loops liefert WordPress false — genau das bildet der Stub ab. */
function get_the_ID() {
    return isset($GLOBALS['test_aktuelle_post']) ? $GLOBALS['test_aktuelle_post'] : false;
}

function add_query_arg($key, $value = null, $url = '') {
    $trenner = (false === strpos((string) $url, '?')) ? '?' : '&';
    return $url . $trenner . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
}

/** Script-/Stil-Registrierung mitschreiben. */
function wp_register_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
    $GLOBALS['test_scripts'][$handle] = array(
        'src' => $src, 'deps' => $deps, 'ver' => $ver, 'in_footer' => $in_footer, 'enqueued' => false,
    );
    return true;
}
function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
    if (isset($GLOBALS['test_scripts'][$handle])) {
        $GLOBALS['test_scripts'][$handle]['enqueued'] = true;
    }
    $GLOBALS['test_enqueued_scripts'][] = $handle;
    return true;
}
function wp_script_is($handle, $liste = 'enqueued') {
    if ('registered' === $liste) {
        return isset($GLOBALS['test_scripts'][$handle]);
    }
    return in_array($handle, $GLOBALS['test_enqueued_scripts'], true);
}
function wp_register_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
    $GLOBALS['test_styles'][$handle] = true;
    return true;
}
function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
    $GLOBALS['test_enqueued_styles'][] = $handle;
    return true;
}
function wp_style_is($handle, $liste = 'enqueued') {
    if ('registered' === $liste) {
        return isset($GLOBALS['test_styles'][$handle]);
    }
    return in_array($handle, $GLOBALS['test_enqueued_styles'], true);
}

$GLOBALS['test_filter']            = array();
$GLOBALS['test_action']            = array();
$GLOBALS['test_permalinks']        = array();
$GLOBALS['test_aktuelle_post']     = false;
$GLOBALS['test_scripts']           = array();
$GLOBALS['test_styles']            = array();
$GLOBALS['test_enqueued_scripts']  = array();
$GLOBALS['test_enqueued_styles']   = array();
$GLOBALS['test_doing_it_wrong']    = 0;

/** Registry-Doppel für die Stil-Handles des Blocks. */
class WP_Block_Type_Registry {
    private static $instanz = null;
    public $typen = array();
    public static function get_instance() {
        if (null === self::$instanz) { self::$instanz = new self(); }
        return self::$instanz;
    }
    public function get_registered($name) {
        return isset($this->typen[$name]) ? $this->typen[$name] : null;
    }
}

// --- Prüfgerüst -----------------------------------------------------------

$GLOBALS['fails'] = 0;
$GLOBALS['pruefungen'] = 0;

function check($label, $condition, $actual = null) {
    $GLOBALS['pruefungen']++;
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

function abbruch($text) {
    echo "\n  FAIL $text\n\nABBRUCH\n";
    exit(1);
}

/** Vergleich von href-Werten unabhängig von der Entity-Schreibweise des &. */
function enthaelt_href($html, $erwartet) {
    return false !== strpos(html_entity_decode($html, ENT_QUOTES, 'UTF-8'), 'href="' . $erwartet . '"');
}

/**
 * Den Tag-Processor bereitstellen: echte WordPress-Klasse, sonst Doppel.
 *
 * @return string Bezeichnung des verwendeten Wegs
 */
function tag_processor_bereitstellen() {
    // Erzwingt das Doppel — damit auch auf dieser Maschine prüfbar bleibt,
    // dass der Rückfallweg trägt:  CBD_TEST_TAG_PROCESSOR=doppel php tools/…
    if ('doppel' === getenv('CBD_TEST_TAG_PROCESSOR')) {
        class_alias('CBD_Test_Tag_Processor', 'WP_HTML_Tag_Processor');
        return 'Doppel (per CBD_TEST_TAG_PROCESSOR erzwungen)';
    }

    $kandidaten = array();

    $aus_umgebung = getenv('CBD_WP_DIR');
    if (is_string($aus_umgebung) && '' !== $aus_umgebung) {
        $kandidaten[] = rtrim(str_replace('\\', '/', $aus_umgebung), '/');
    }

    // Der in docs/PLAN-Inline-Blockreferenz.md (Abschnitt 3) dokumentierte
    // lokale All-inkl-Simulator.
    $kandidaten[] = 'C:/allinkl-testserver/www/htdocs/w0000001/fos';

    // Liegt das Plugin in einer WordPress-Installation, steht wp-includes
    // weiter oben im Baum.
    $auf = rtrim(str_replace('\\', '/', CBD_PLUGIN_DIR), '/');
    for ($i = 0; $i < 6; $i++) {
        $auf = dirname($auf);
        if ('' === $auf || '.' === $auf) { break; }
        $kandidaten[] = $auf;
    }

    foreach ($kandidaten as $wurzel) {
        $inc = $wurzel . '/wp-includes/';
        if (!file_exists($inc . 'html-api/class-wp-html-tag-processor.php')) {
            continue;
        }
        $dateien = array(
            'class-wp-token-map.php',
            'html-api/class-wp-html-span.php',
            'html-api/class-wp-html-text-replacement.php',
            'html-api/class-wp-html-decoder.php',
            'html-api/class-wp-html-attribute-token.php',
            'html-api/html5-named-character-references.php',
            'html-api/class-wp-html-tag-processor.php',
        );
        foreach ($dateien as $datei) {
            if (file_exists($inc . $datei)) {
                require_once $inc . $datei;
            }
        }
        if (class_exists('WP_HTML_Tag_Processor')) {
            return 'echte WordPress-Klasse aus ' . $wurzel;
        }
    }

    class_alias('CBD_Test_Tag_Processor', 'WP_HTML_Tag_Processor');
    return 'Doppel (keine WordPress-Installation gefunden)';
}

/**
 * Schmales Doppel von WP_HTML_Tag_Processor — nur so viel, wie
 * CBD_Inline_Reference tatsächlich benutzt.
 *
 * BEWUSSTE GRENZEN, damit niemand mehr hineinliest, als drinsteht:
 *   - erkennt ausschliesslich `<a>`-Starttags (das Einzige, wonach der Filter
 *     fragt); `next_tag()` ohne Abfrage läuft deshalb ebenfalls nur über sie
 *   - kennt keine Rohtext-Elemente: ein `<a …>` INNERHALB von `<script>` gälte
 *     hier als Tag, in WordPress nicht
 *   - baut nur GEÄNDERTE Tags neu auf; alles Übrige bleibt Byte für Byte
 *     stehen. Genau darauf beruhen die Zeichengleichheits-Prüfungen
 *
 * Reguläre Ausdrücke sind hier zulässig — im Produktivcode nicht (Gruppe 1
 * prüft das).
 */
class CBD_Test_Tag_Processor {
    private $html;
    private $tags = array();
    private $pos = -1;

    public function __construct($html) {
        $this->html = (string) $html;
        $treffer = array();
        preg_match_all('/<a\b([^>]*)>/i', $this->html, $treffer, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($treffer as $satz) {
            $this->tags[] = array(
                'start'    => $satz[0][1],
                'laenge'   => strlen($satz[0][0]),
                'attrs'    => $this->attribute_lesen($satz[1][0]),
                'geaendert' => false,
            );
        }
    }

    private function attribute_lesen($roh) {
        $attrs = array();
        $treffer = array();
        preg_match_all(
            '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/',
            $roh,
            $treffer,
            PREG_SET_ORDER
        );
        foreach ($treffer as $satz) {
            $name = strtolower($satz[1]);
            if ('' === $name) { continue; }
            if (isset($satz[2]) && '' !== $satz[2]) { $attrs[$name] = html_entity_decode($satz[2], ENT_QUOTES, 'UTF-8'); continue; }
            if (isset($satz[3]) && '' !== $satz[3]) { $attrs[$name] = html_entity_decode($satz[3], ENT_QUOTES, 'UTF-8'); continue; }
            if (isset($satz[4]) && '' !== $satz[4]) { $attrs[$name] = html_entity_decode($satz[4], ENT_QUOTES, 'UTF-8'); continue; }
            // Ein Attribut mit leerem Wert bleibt leerer String, ein
            // wertloses Attribut wird true — wie in WordPress.
            $attrs[$name] = (false !== strpos($satz[0], '=')) ? '' : true;
        }
        return $attrs;
    }

    public function next_tag($abfrage = null) {
        $gesucht_klasse = '';
        if (is_array($abfrage) && isset($abfrage['class_name'])) {
            $gesucht_klasse = (string) $abfrage['class_name'];
        }
        while (++$this->pos < count($this->tags)) {
            if ('' === $gesucht_klasse) { return true; }
            $klassen = $this->tags[$this->pos]['attrs']['class'] ?? '';
            if (!is_string($klassen)) { continue; }
            $liste = preg_split('/\s+/', trim($klassen), -1, PREG_SPLIT_NO_EMPTY);
            if (in_array($gesucht_klasse, (array) $liste, true)) { return true; }
        }
        return false;
    }

    public function get_attribute($name) {
        if ($this->pos < 0 || $this->pos >= count($this->tags)) { return null; }
        $name = strtolower((string) $name);
        return array_key_exists($name, $this->tags[$this->pos]['attrs'])
            ? $this->tags[$this->pos]['attrs'][$name]
            : null;
    }

    public function set_attribute($name, $wert) {
        if ($this->pos < 0 || $this->pos >= count($this->tags)) { return false; }
        $this->tags[$this->pos]['attrs'][strtolower((string) $name)] = $wert;
        $this->tags[$this->pos]['geaendert'] = true;
        return true;
    }

    public function remove_attribute($name) {
        if ($this->pos < 0 || $this->pos >= count($this->tags)) { return false; }
        $name = strtolower((string) $name);
        if (array_key_exists($name, $this->tags[$this->pos]['attrs'])) {
            unset($this->tags[$this->pos]['attrs'][$name]);
            $this->tags[$this->pos]['geaendert'] = true;
        }
        return true;
    }

    public function get_updated_html() {
        $html = $this->html;
        for ($i = count($this->tags) - 1; $i >= 0; $i--) {
            if (!$this->tags[$i]['geaendert']) { continue; }
            $neu = '<a';
            foreach ($this->tags[$i]['attrs'] as $name => $wert) {
                if (true === $wert) { $neu .= ' ' . $name; continue; }
                $neu .= ' ' . $name . '="' . htmlspecialchars((string) $wert, ENT_QUOTES, 'UTF-8') . '"';
            }
            $neu .= '>';
            $html = substr($html, 0, $this->tags[$i]['start'])
                . $neu
                . substr($html, $this->tags[$i]['start'] + $this->tags[$i]['laenge']);
        }
        return $html;
    }
}

// =========================================================================
// Die Prüflinge laden
// =========================================================================

$quelldatei = $plugin_dir . 'includes/class-cbd-inline-reference.php';

echo "== Vorbedingungen ==\n";
check('0.1 · includes/class-cbd-inline-reference.php existiert', file_exists($quelldatei));

if (!file_exists($quelldatei)) {
    abbruch('Ohne die Klassendatei ist keine weitere Prüfung möglich.');
}

require_once $quelldatei;

check('0.2 · Klasse CBD_Inline_Reference ist geladen', class_exists('CBD_Inline_Reference'));

if (!class_exists('CBD_Inline_Reference')) {
    abbruch('Die Datei definiert CBD_Inline_Reference nicht.');
}

foreach (array('init', 'inhalt_auffrischen', 'register_format_script', 'format_script_daten', 'auswahl_handle') as $methode) {
    check('0.3 · Methode ' . $methode . '() vorhanden', method_exists('CBD_Inline_Reference', $methode));
}

if (!method_exists('CBD_Inline_Reference', 'inhalt_auffrischen')) {
    abbruch('Ohne inhalt_auffrischen() ist keine weitere Prüfung möglich.');
}

// =========================================================================
// 1 · Zusicherungen am Quelltext
// =========================================================================

echo "\n== 1 · Quelltext-Zusicherungen ==\n";

$quelle = file_get_contents($quelldatei);

$bezeichner = array();
foreach (token_get_all($quelle) as $token) {
    if (is_array($token) && T_STRING === $token[0]) {
        $bezeichner[$token[1]] = true;
    }
}

check(
    '1.1 · kein regulaerer Ausdruck auf dem Inhalt',
    !isset($bezeichner['preg_match']) && !isset($bezeichner['preg_match_all'])
        && !isset($bezeichner['preg_replace']) && !isset($bezeichner['preg_split'])
);
check('1.2 · WP_HTML_Tag_Processor wird verwendet', false !== strpos($quelle, 'WP_HTML_Tag_Processor'));
check(
    '1.3 · WP_HTML_Tag_Processor steht hinter class_exists()',
    false !== strpos($quelle, "class_exists('WP_HTML_Tag_Processor')")
);
check('1.4 · file_exists()-Waechter fuer format.js vorhanden', isset($bezeichner['file_exists']));
check(
    '1.5 · AUSWAHL_HANDLE wird ueber die Konstante gelesen, nicht als Zeichenkette wiederholt',
    false === strpos($quelle, 'cbd-block-auswahl') && false !== strpos($quelle, 'AUSWAHL_HANDLE')
);
check('1.6 · die Abhaengigkeit wp-rich-text ist deklariert', false !== strpos($quelle, 'wp-rich-text'));
check(
    '1.7 · die Annahme „view.js ist ein Footer-Script" steht als Kommentar im Code',
    false !== stripos($quelle, 'in_footer') || false !== stripos($quelle, 'Footer-Script')
);
check(
    '1.8 · der Endpunkt cbd/v1/block-html wird nicht angefasst',
    false === strpos($quelle, 'CBD_Block_Content_API') && false === strpos($quelle, 'block-html')
);
check('1.9 · direkter Aufruf ist per ABSPATH-Pruefung verhindert', false !== strpos($quelle, "!defined('ABSPATH')"));

// =========================================================================
// 2 · Hooks und Prioritaet (AK8)
// =========================================================================

echo "\n== 2 · Hooks und Prioritaet ==\n";

CBD_Inline_Reference::init();

$content_filter = null;
foreach ($GLOBALS['test_filter'] as $eintrag) {
    if ('the_content' === $eintrag['tag']) { $content_filter = $eintrag; }
}

check('2.1 · ein the_content-Filter wurde registriert', null !== $content_filter);
check(
    '2.2 · Callback ist CBD_Inline_Reference::inhalt_auffrischen',
    null !== $content_filter && array('CBD_Inline_Reference', 'inhalt_auffrischen') === $content_filter['cb'],
    $content_filter['cb'] ?? null
);
check('2.3 · Prioritaet ist genau 12', null !== $content_filter && 12 === $content_filter['prio'], $content_filter['prio'] ?? null);

// Gegenprobe an der echten Quelle: Das LaTeX-Netz liegt auf 11.
$latex_quelle = file_get_contents($plugin_dir . 'includes/class-latex-parser.php');
check(
    '2.4 · das LaTeX-Netz haengt nachweislich auf Prioritaet 11',
    false !== strpos($latex_quelle, "add_filter('the_content', array(\$this, 'parse_latex'), 11)")
);
check('2.5 · 12 liegt nach dem LaTeX-Netz (11)', 12 > 11);
check('2.6 · 12 liegt weit vor der Glossar-Autoverlinkung des Themes (10000)', 12 < 10000);

$editor_action = null;
foreach ($GLOBALS['test_action'] as $eintrag) {
    if ('enqueue_block_editor_assets' === $eintrag['tag']) { $editor_action = $eintrag; }
}
check('2.7 · register_format_script haengt an enqueue_block_editor_assets', null !== $editor_action);
check(
    '2.8 · und zwar mit dem richtigen Callback',
    null !== $editor_action && array('CBD_Inline_Reference', 'register_format_script') === $editor_action['cb'],
    $editor_action['cb'] ?? null
);

// =========================================================================
// 3a · Format-Script OHNE den Vertragspartner CBD_Block_Reference
// =========================================================================

echo "\n== 3a · Format-Script, Vertragspartner fehlt ==\n";

check('3a.0 · Vorbedingung: CBD_Block_Reference ist noch nicht definiert', !class_exists('CBD_Block_Reference'));

$vorhandene_datei = 'blocks/block-reference/view.js';
check('3a.1 · Vorbedingung: ' . $vorhandene_datei . ' existiert', file_exists($plugin_dir . $vorhandene_datei));

check(
    '3a.2 · ohne CBD_Block_Reference wird die Registrierung uebersprungen (kein Fatal)',
    null === CBD_Inline_Reference::format_script_daten($vorhandene_datei),
    CBD_Inline_Reference::format_script_daten($vorhandene_datei)
);

CBD_Inline_Reference::register_format_script();
check('3a.3 · dabei wurde kein Script registriert', empty($GLOBALS['test_scripts']), $GLOBALS['test_scripts']);

// =========================================================================
// 3b · Format-Script MIT Vertragspartner
// =========================================================================

echo "\n== 3b · Format-Script, Vertragspartner vorhanden ==\n";

/**
 * Doppel des Nachbarn — bildet genau den Vertrag von AP-3.2 ab.
 *
 * PER eval(), UND ZWAR ZWINGEND: PHP bindet eine unbedingte, nichts erbende
 * Klassendeklaration schon beim Kompilieren der Datei (Early Binding). Stünde
 * `class CBD_Block_Reference` hier einfach so, wäre sie bereits in Gruppe 3a
 * definiert — und der Nachweis „fehlender Vertragspartner erzeugt keinen Fatal
 * Error" wäre stillschweigend wertlos. Genau das hat der erste Durchlauf
 * dieses Harnischs gemeldet (Prüfung 3a.0).
 */
eval('
class CBD_Block_Reference {
    const AUSWAHL_HANDLE = "cbd-block-auswahl";
    const VIEW_HANDLE_FALLBACK = "cbd-block-reference-view-script";
    public static function view_script_handle($block_type = null) {
        if (is_object($block_type) && !empty($block_type->view_script_handles)) {
            return reset($block_type->view_script_handles);
        }
        return self::VIEW_HANDLE_FALLBACK;
    }
}

/** Klasse ohne die Konstante — der zweite Zweig des Waechters. */
class CBD_Test_Reference_Ohne_Konstante {
}
');

check(
    '3b.1 · auswahl_handle() liefert den Wert der Konstante',
    'cbd-block-auswahl' === CBD_Inline_Reference::auswahl_handle(),
    CBD_Inline_Reference::auswahl_handle()
);
check(
    '3b.2 · fehlende Klasse -> null statt Fatal',
    null === CBD_Inline_Reference::auswahl_handle('CBD_Test_Klasse_Gibt_Es_Nicht')
);
check(
    '3b.3 · Klasse ohne die Konstante -> null statt Fatal',
    null === CBD_Inline_Reference::auswahl_handle('CBD_Test_Reference_Ohne_Konstante')
);

$daten = CBD_Inline_Reference::format_script_daten($vorhandene_datei);
check('3b.4 · jetzt liefert format_script_daten() einen Datensatz', is_array($daten), $daten);

$erwartete_deps = array(
    'wp-rich-text', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-api-fetch', 'cbd-block-auswahl',
);
$deps = is_array($daten) ? (array) $daten['deps'] : array();
foreach ($erwartete_deps as $dep) {
    check('3b.5 · Abhaengigkeit ' . $dep . ' ist deklariert', in_array($dep, $deps, true), $deps);
}
check('3b.6 · keine weiteren Abhaengigkeiten', count($deps) === count($erwartete_deps), $deps);
check('3b.7 · Handle ist cbd-block-reference-format', is_array($daten) && 'cbd-block-reference-format' === $daten['handle'], $daten['handle'] ?? null);
check(
    '3b.8 · Quelle zeigt auf die Plugin-URL',
    is_array($daten) && CBD_PLUGIN_URL . $vorhandene_datei === $daten['src'],
    $daten['src'] ?? null
);
check(
    '3b.9 · Cache-Busting enthaelt CBD_VERSION und filemtime',
    is_array($daten) && 0 === strpos((string) $daten['ver'], CBD_VERSION . '.')
        && (string) filemtime($plugin_dir . $vorhandene_datei) === substr((string) $daten['ver'], strlen(CBD_VERSION) + 1),
    $daten['ver'] ?? null
);

// AK9 — der eigentliche Zustand nach diesem AP: format.js gibt es noch nicht.
echo "\n== 3c · AK9: format.js existiert noch nicht ==\n";

check(
    '3c.0 · Vorbedingung: blocks/block-reference/format.js existiert wirklich nicht',
    !file_exists($plugin_dir . 'blocks/block-reference/format.js')
);
check('3c.1 · format_script_daten() liefert null', null === CBD_Inline_Reference::format_script_daten());

$GLOBALS['test_warnungen'] = 0;
set_error_handler(function ($no, $str) { $GLOBALS['test_warnungen']++; return true; });
CBD_Inline_Reference::register_format_script();
restore_error_handler();

check('3c.2 · es wird nichts registriert', empty($GLOBALS['test_scripts']), $GLOBALS['test_scripts']);
check('3c.3 · es wird nichts eingereiht', empty($GLOBALS['test_enqueued_scripts']), $GLOBALS['test_enqueued_scripts']);
check('3c.4 · dabei entsteht keine Warnung', 0 === $GLOBALS['test_warnungen'], $GLOBALS['test_warnungen']);
check('3c.5 · und kein _doing_it_wrong()', 0 === $GLOBALS['test_doing_it_wrong'], $GLOBALS['test_doing_it_wrong']);

// =========================================================================
// 4 · AK7: WP_HTML_Tag_Processor fehlt
// =========================================================================

echo "\n== 4 · AK7: WP_HTML_Tag_Processor fehlt ==\n";

check('4.0 · Vorbedingung: die Klasse ist wirklich nicht geladen', !class_exists('WP_HTML_Tag_Processor'));

$mit_verweis = '<p>Siehe <a class="cbd-block-reference-inline" href="/alt/" '
    . 'data-target-post="45" data-target-stable-id="cbd-container-abc123" '
    . 'data-target-anchor="" data-target-title="IR-Spektroskopie">dort</a>.</p>';

$GLOBALS['test_permalinks'] = array(45 => 'https://example.test/ir-spektroskopie/');
$GLOBALS['test_aktuelle_post'] = 12;

$ergebnis = CBD_Inline_Reference::inhalt_auffrischen($mit_verweis);
check('4.1 · Inhalt kommt zeichengleich zurueck', $mit_verweis === $ergebnis, $ergebnis);
check('4.2 · kein Script eingereiht', empty($GLOBALS['test_enqueued_scripts']), $GLOBALS['test_enqueued_scripts']);
check('4.3 · kein Stil eingereiht', empty($GLOBALS['test_enqueued_styles']), $GLOBALS['test_enqueued_styles']);

// Ab hier steht der Tag-Processor bereit.
$weg = tag_processor_bereitstellen();
echo "\n  [Tag-Processor: $weg]\n";
check('4.4 · Tag-Processor steht jetzt bereit', class_exists('WP_HTML_Tag_Processor'));

// =========================================================================
// 5 · AK1: Inhalte, die der Filter NICHT betrifft, bleiben zeichengleich
// =========================================================================

echo "\n== 5 · AK1: zeichengleiche Rueckgabe ==\n";

$GLOBALS['test_aktuelle_post'] = 45;
$GLOBALS['test_permalinks'] = array(45 => 'https://example.test/ir-spektroskopie/');

$unberuehrt = array(
    'leerer String' => '',
    'reiner Text'   => 'Nichts Besonderes.',
    'Umlaute und LaTeX' => '<p>Größe der Ölsäure: \\(E = mc^2\\), Summe \\sum_{i=1}^{n} x_i · 100 %.</p>',
    'gewoehnlicher Link' => '<p>Mehr dazu <a href="https://example.test/andere-seite/" title="Ziel">hier</a>.</p>',
    'Skript im Inhalt' => '<div class="wp-block-html"><script>var re = /\\(([^)]+)\\)/g; if (a < b) { console.log("<a>"); }</script></div>',
    'Block-Referenz-Block (andere Klasse)' => '<div class="wp-block-cbd-block-reference cbd-block-reference-wrapper cbd-block-reference-mode-modal" data-same-page="false" data-display-mode="modal">'
        . '<a href="https://example.test/ziel/?cbd-ref=cbd-container-xyz" class="cbd-block-reference-link" '
        . 'data-target-stable-id="cbd-container-xyz" data-target-anchor="" data-target-post="99" '
        . 'data-same-page="false" data-display-mode="modal" data-target-title="Titel" aria-haspopup="dialog" '
        . 'title="Block anzeigen: Titel"><div class="cbd-block-reference-content">Inhalt</div></a></div>',
    'Container-Markup' => '<div class="cbd-container" data-stable-id="cbd-container-abc123"><div class="cbd-container-block"><p>Text</p></div></div>',
    'Kommentar und Entities' => '<!-- wp:paragraph --><p>A &amp; B &lt;nicht&gt; ein Tag &#8217;s</p><!-- /wp:paragraph -->',
);

foreach ($unberuehrt as $label => $inhalt) {
    $vorher = $inhalt;
    check('5.1 · ' . $label . ' bleibt zeichengleich', $vorher === CBD_Inline_Reference::inhalt_auffrischen($inhalt), $inhalt);
}

// Nicht-Zeichenketten duerfen nicht in eine Umwandlung geraten.
check('5.2 · null kommt unveraendert zurueck', null === CBD_Inline_Reference::inhalt_auffrischen(null));
check('5.3 · ein Array kommt unveraendert zurueck', array('a') === CBD_Inline_Reference::inhalt_auffrischen(array('a')));
check('5.4 · eine Zahl kommt unveraendert zurueck', 42 === CBD_Inline_Reference::inhalt_auffrischen(42));

// Die Klassenzeichenkette kommt vor, aber an keinem <a>.
$nur_text = '<p>Der Filter erkennt Verweise an der Klasse <code>cbd-block-reference-inline</code>.</p>';
check('5.5 · Klassenzeichenkette nur im Fliesstext -> zeichengleich', $nur_text === CBD_Inline_Reference::inhalt_auffrischen($nur_text));

$fremdes_tag = '<p><span class="cbd-block-reference-inline">kein Link</span></p>';
check('5.6 · Klasse an einem <span> statt <a> -> zeichengleich', $fremdes_tag === CBD_Inline_Reference::inhalt_auffrischen($fremdes_tag));

// AK6: kein oder unbrauchbares data-target-post.
$ungueltig = array(
    'ohne data-target-post' => '<p><a class="cbd-block-reference-inline" href="/x/">Text</a></p>',
    'leeres data-target-post' => '<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="">Text</a></p>',
    'nicht numerisch' => '<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="abc">Text</a></p>',
    'gemischt' => '<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="45abc">Text</a></p>',
    'null' => '<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="0">Text</a></p>',
    'negativ' => '<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="-5">Text</a></p>',
    'Kommazahl' => '<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="4.5">Text</a></p>',
    'nur Leerraum' => '<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="   ">Text</a></p>',
);
foreach ($ungueltig as $label => $inhalt) {
    check('5.7 · AK6: ' . $label . ' -> zeichengleich', $inhalt === CBD_Inline_Reference::inhalt_auffrischen($inhalt), $inhalt);
}

// Teilzeichenkette der Klasse darf nicht greifen.
$aehnlich = '<p><a class="cbd-block-reference-inline-extra" href="/x/" data-target-post="45">Text</a></p>';
check('5.8 · aehnlich benannte Klasse greift nicht -> zeichengleich', $aehnlich === CBD_Inline_Reference::inhalt_auffrischen($aehnlich));

check('5.9 · bis hierhin wurde kein Script eingereiht', empty($GLOBALS['test_enqueued_scripts']), $GLOBALS['test_enqueued_scripts']);

// =========================================================================
// 6 · AK2 bis AK5: der Verweis wird aufgefrischt
// =========================================================================

echo "\n== 6 · Auffrischen des Verweises ==\n";

$GLOBALS['test_permalinks'] = array(
    45 => 'https://example.test/ir-spektroskopie/',
    12 => 'https://example.test/grundlagen/',
    77 => 'https://example.test/?p=77',
    88 => '',
    99 => false,
);

function verweis($post, $anker = '', $stable = 'cbd-container-abc123', $extra = '') {
    return '<p>Siehe <a class="cbd-block-reference-inline" href="https://example.test/alt/?cbd-ref=alt" '
        . 'data-target-post="' . $post . '" '
        . 'data-target-stable-id="' . $stable . '" '
        . 'data-target-anchor="' . $anker . '" '
        . 'data-target-title="Grundlagen der IR-Spektroskopie"' . $extra . '>markierter Text</a> dazu.</p>';
}

// --- AK2: fremde Seite ---------------------------------------------------
$GLOBALS['test_aktuelle_post'] = 12;
$aus = CBD_Inline_Reference::inhalt_auffrischen(verweis(45));

check('6.1 · AK3: data-display-mode="modal" gesetzt', false !== strpos($aus, 'data-display-mode="modal"'), $aus);
check('6.2 · AK3: aria-haspopup="dialog" gesetzt', false !== strpos($aus, 'aria-haspopup="dialog"'), $aus);
check('6.3 · AK2: fremde Seite traegt KEIN data-same-page', false === strpos($aus, 'data-same-page'), $aus);
check('6.4 · AK4: href ohne Anker -> Permalink mit ?cbd-ref=', enthaelt_href($aus, 'https://example.test/ir-spektroskopie/?cbd-ref=cbd-container-abc123'), $aus);
check('6.5 · der markierte Text bleibt erhalten', false !== strpos($aus, '>markierter Text</a>'), $aus);
check('6.6 · der umgebende Absatz bleibt erhalten', 0 === strpos($aus, '<p>Siehe ') && false !== strpos($aus, ' dazu.</p>'), $aus);
check('6.7 · die gespeicherten data-Attribute bleiben stehen', false !== strpos($aus, 'data-target-stable-id="cbd-container-abc123"')
    && false !== strpos($aus, 'data-target-post="45"')
    && false !== strpos($aus, 'data-target-title="Grundlagen der IR-Spektroskopie"'), $aus);
check('6.8 · die Klasse bleibt erhalten', false !== strpos($aus, 'cbd-block-reference-inline'), $aus);

// --- AK2: eigene Seite ---------------------------------------------------
$GLOBALS['test_aktuelle_post'] = 45;
$aus = CBD_Inline_Reference::inhalt_auffrischen(verweis(45));
check('6.9 · AK2: eigene Seite traegt data-same-page="true"', false !== strpos($aus, 'data-same-page="true"'), $aus);

// --- ein falsch gespeichertes data-same-page muss weichen ----------------
$GLOBALS['test_aktuelle_post'] = 12;
$aus = CBD_Inline_Reference::inhalt_auffrischen(verweis(45, '', 'cbd-container-abc123', ' data-same-page="true"'));
check('6.10 · gespeichertes data-same-page="true" auf fremder Seite wird entfernt', false === strpos($aus, 'data-same-page'), $aus);

// --- AK4: mit Anker ------------------------------------------------------
$aus = CBD_Inline_Reference::inhalt_auffrischen(verweis(45, 'mein-anker'));
check('6.11 · AK4: href mit Anker -> Permalink#anker', enthaelt_href($aus, 'https://example.test/ir-spektroskopie/#mein-anker'), $aus);
check('6.12 · dabei kein cbd-ref-Parameter', false === strpos($aus, 'cbd-ref=cbd-container'), $aus);

// --- Permalink mit vorhandener Abfrage -----------------------------------
$aus = CBD_Inline_Reference::inhalt_auffrischen(verweis(77));
check('6.13 · Permalink mit Abfrage -> &cbd-ref=', enthaelt_href($aus, 'https://example.test/?p=77&cbd-ref=cbd-container-abc123'), $aus);

// --- AK5: get_permalink() liefert nichts ---------------------------------
$eingabe = verweis(88);
$aus = CBD_Inline_Reference::inhalt_auffrischen($eingabe);
check('6.14 · AK5: leerer Permalink -> gespeicherter href bleibt', enthaelt_href($aus, 'https://example.test/alt/?cbd-ref=alt'), $aus);
check('6.15 · AK5: dabei werden die uebrigen Attribute trotzdem gesetzt', false !== strpos($aus, 'data-display-mode="modal"'), $aus);

$eingabe = verweis(99);
$aus = CBD_Inline_Reference::inhalt_auffrischen($eingabe);
check('6.16 · AK5: get_permalink() === false -> gespeicherter href bleibt', enthaelt_href($aus, 'https://example.test/alt/?cbd-ref=alt'), $aus);

// --- mehrere Verweise ----------------------------------------------------
$GLOBALS['test_aktuelle_post'] = 45;
$mehrere = '<p>Erst <a class="cbd-block-reference-inline" href="/a/" data-target-post="45" '
    . 'data-target-stable-id="cbd-eins" data-target-anchor="" data-target-title="Eins">eins</a>, '
    . 'dann <a class="cbd-block-reference-inline" href="/b/" data-target-post="12" '
    . 'data-target-stable-id="cbd-zwei" data-target-anchor="anker-zwei" data-target-title="Zwei">zwei</a>, '
    . 'schliesslich <a href="https://example.test/normal/">ein gewoehnlicher Link</a>.</p>';
$aus = CBD_Inline_Reference::inhalt_auffrischen($mehrere);

check('6.17 · beide Verweise bekamen data-display-mode', 2 === substr_count($aus, 'data-display-mode="modal"'), $aus);
check('6.18 · beide Verweise bekamen aria-haspopup', 2 === substr_count($aus, 'aria-haspopup="dialog"'), $aus);
check('6.19 · nur der Verweis auf die eigene Seite traegt data-same-page', 1 === substr_count($aus, 'data-same-page="true"'), $aus);
check('6.20 · href des ersten Verweises', enthaelt_href($aus, 'https://example.test/ir-spektroskopie/?cbd-ref=cbd-eins'), $aus);
check('6.21 · href des zweiten Verweises', enthaelt_href($aus, 'https://example.test/grundlagen/#anker-zwei'), $aus);
check('6.22 · der gewoehnliche Link bleibt unangetastet', false !== strpos($aus, '<a href="https://example.test/normal/">ein gewoehnlicher Link</a>'), $aus);

// --- fremde Attribute bleiben erhalten -----------------------------------
$GLOBALS['test_aktuelle_post'] = 12;
$mit_extras = '<p><a class="cbd-block-reference-inline zusatzklasse" id="verweis-1" rel="noopener" '
    . 'title="Mein Titel" data-eigenes="wert" href="/alt/" data-target-post="45" '
    . 'data-target-stable-id="cbd-container-abc123" data-target-anchor="" '
    . 'data-target-title="Titel">Text</a></p>';
$aus = CBD_Inline_Reference::inhalt_auffrischen($mit_extras);
foreach (array('id="verweis-1"', 'rel="noopener"', 'title="Mein Titel"', 'data-eigenes="wert"', 'zusatzklasse') as $stueck) {
    check('6.23 · fremdes Attribut bleibt: ' . $stueck, false !== strpos($aus, $stueck), $aus);
}

// --- gueltiger und ungueltiger Verweis nebeneinander ---------------------
$gemischt = '<p><a class="cbd-block-reference-inline" href="/kaputt/" data-target-post="abc" '
    . 'data-target-stable-id="cbd-kaputt" data-target-anchor="" data-target-title="Kaputt">kaputt</a> '
    . 'und <a class="cbd-block-reference-inline" href="/heil/" data-target-post="45" '
    . 'data-target-stable-id="cbd-heil" data-target-anchor="" data-target-title="Heil">heil</a></p>';
$aus = CBD_Inline_Reference::inhalt_auffrischen($gemischt);
check('6.24 · der ungueltige Verweis bleibt Zeichen fuer Zeichen stehen',
    false !== strpos($aus, '<a class="cbd-block-reference-inline" href="/kaputt/" data-target-post="abc" data-target-stable-id="cbd-kaputt" data-target-anchor="" data-target-title="Kaputt">kaputt</a>'), $aus);
check('6.25 · der gueltige Verweis wurde aufgefrischt', 1 === substr_count($aus, 'data-display-mode="modal"'), $aus);

// --- Umlaute und LaTeX rundherum ----------------------------------------
$mit_formel = '<p>Für \\(E = mc^2\\) siehe <a class="cbd-block-reference-inline" href="/alt/" '
    . 'data-target-post="45" data-target-stable-id="cbd-container-abc123" data-target-anchor="" '
    . 'data-target-title="Größenordnung">Größenordnung</a> — Öl, Ähre, ß.</p>';
$aus = CBD_Inline_Reference::inhalt_auffrischen($mit_formel);
check('6.26 · LaTeX-Backslashes bleiben unversehrt', false !== strpos($aus, '\\(E = mc^2\\)'), $aus);
check('6.27 · Umlaute im Fliesstext bleiben unversehrt', false !== strpos($aus, '— Öl, Ähre, ß.</p>'), $aus);
check('6.28 · Umlaute im markierten Text bleiben unversehrt', false !== strpos($aus, '>Größenordnung</a>'), $aus);

// =========================================================================
// 7 · AK10: das View-Script wird nur bei Bedarf eingebunden
// =========================================================================

echo "\n== 7 · AK10: Einbindung des View-Scripts ==\n";

// Ausgangslage: view.js ist registriert, der Blocktyp kennt seine Stile.
wp_register_script('cbd-block-reference-view-script', 'https://example.test/view.js', array(), '1', true);
wp_register_style('cbd-block-reference-style', 'https://example.test/style.css');

$typ = new stdClass();
$typ->view_script_handles = array('cbd-block-reference-view-script');
$typ->style_handles = array('cbd-block-reference-style');
WP_Block_Type_Registry::get_instance()->typen['cbd/block-reference'] = $typ;

$GLOBALS['test_enqueued_scripts'] = array();
$GLOBALS['test_enqueued_styles']  = array();

CBD_Inline_Reference::inhalt_auffrischen('<p>Nichts Besonderes.</p>');
check('7.1 · ohne Verweis kein Script', empty($GLOBALS['test_enqueued_scripts']), $GLOBALS['test_enqueued_scripts']);

CBD_Inline_Reference::inhalt_auffrischen($nur_text);
check('7.2 · Klassenzeichenkette nur als Text -> kein Script', empty($GLOBALS['test_enqueued_scripts']), $GLOBALS['test_enqueued_scripts']);

CBD_Inline_Reference::inhalt_auffrischen('<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="abc">x</a></p>');
check('7.3 · ungueltiger Verweis -> kein Script', empty($GLOBALS['test_enqueued_scripts']), $GLOBALS['test_enqueued_scripts']);
check('7.4 · ungueltiger Verweis -> kein Stil', empty($GLOBALS['test_enqueued_styles']), $GLOBALS['test_enqueued_styles']);

CBD_Inline_Reference::inhalt_auffrischen(verweis(45));
check('7.5 · gueltiger Verweis -> view.js eingereiht',
    in_array('cbd-block-reference-view-script', $GLOBALS['test_enqueued_scripts'], true), $GLOBALS['test_enqueued_scripts']);
check('7.6 · gueltiger Verweis -> Blockstil eingereiht',
    in_array('cbd-block-reference-style', $GLOBALS['test_enqueued_styles'], true), $GLOBALS['test_enqueued_styles']);

// Ohne registrierten Blocktyp darf nichts brechen.
unset(WP_Block_Type_Registry::get_instance()->typen['cbd/block-reference']);
$GLOBALS['test_enqueued_scripts'] = array();
$GLOBALS['test_enqueued_styles']  = array();
$aus = CBD_Inline_Reference::inhalt_auffrischen(verweis(45));
check('7.7 · ohne registrierten Blocktyp kein Fatal', false !== strpos($aus, 'data-display-mode="modal"'), $aus);
check('7.8 · dabei greift das Rueckfall-Handle fuer view.js',
    in_array('cbd-block-reference-view-script', $GLOBALS['test_enqueued_scripts'], true), $GLOBALS['test_enqueued_scripts']);
check('7.9 · und kein Stil wird geraten', empty($GLOBALS['test_enqueued_styles']), $GLOBALS['test_enqueued_styles']);

check('7.10 · waehrend aller Pruefungen kein _doing_it_wrong()', 0 === $GLOBALS['test_doing_it_wrong'], $GLOBALS['test_doing_it_wrong']);

// =========================================================================
// 8 · AP-3.fix2, AK1-AK4: ziel_post_id() ohne (int)-Cast-Warnung
// =========================================================================
//
// Befund: ziel_post_id() prueft korrekt mit ctype_digit(), lief aber danach
// in (int) $roh. Eine ueberlange Ziffernfolge besteht ctype_digit(), aber
// (int) warnt ab PHP 8.1 ("not representable as an int, cast occurred") und
// liefert PHP_INT_MAX statt eines Fehlschlags -- der Wert gaelte faelschlich
// als gueltige Beitrags-ID. Siehe docs/PLAN-Inline-Blockreferenz.md,
// Abschnitt "AP-3.fix2", und die dort referenzierte Vorlage in
// class-cbd-design-transfer.php (md_read_value(), Zeile ~911-915).

echo "\n== 8 · AP-3.fix2: ueberlange Ziffernfolgen ohne (int)-Cast-Warnung ==\n";

/**
 * inhalt_auffrischen() aufrufen und jede PHP-Warnung/Notice waehrend des
 * Aufrufs zaehlen. Dasselbe Muster wie Pruefung 3c.4/3c.5: set_error_handler
 * macht eine Warnung zum sichtbaren Fehlschlag, statt sie im Ablauf
 * verschwinden zu lassen (die Warnung selbst unterbricht nichts).
 *
 * @return array [0 => string $ergebnis, 1 => int $warnungen]
 */
function mit_warnzaehler($inhalt) {
    $GLOBALS['test_warnungen'] = 0;
    set_error_handler(function ($no, $str) {
        $GLOBALS['test_warnungen']++;
        return true;
    });
    $ergebnis = CBD_Inline_Reference::inhalt_auffrischen($inhalt);
    restore_error_handler();
    return array($ergebnis, $GLOBALS['test_warnungen']);
}

$GLOBALS['test_aktuelle_post'] = 999;

// --- AK1: 20-stellige Ziffernfolge ---------------------------------------
$GLOBALS['test_permalinks'] = array(45 => 'https://example.test/ir-spektroskopie/');
$ziffern_20 = str_repeat('9', 20);
$eingabe = verweis($ziffern_20);
list($aus, $warnungen) = mit_warnzaehler($eingabe);
check('8.1 · AK1: 20-stellige Ziffernfolge bleibt zeichengleich', $eingabe === $aus, $aus);
check('8.2 · AK1: dabei entsteht keine PHP-Warnung', 0 === $warnungen, $warnungen);

// --- AK2: 30-stellige und 100-stellige Ziffernfolge ----------------------
foreach (array(30, 100) as $laenge) {
    $ziffern = str_repeat('7', $laenge);
    $eingabe = verweis($ziffern);
    list($aus, $warnungen) = mit_warnzaehler($eingabe);
    check('8.3 · AK2: ' . $laenge . '-stellige Ziffernfolge bleibt zeichengleich', $eingabe === $aus, $aus);
    check('8.4 · AK2: ' . $laenge . '-stellige Ziffernfolge erzeugt keine Warnung', 0 === $warnungen, $warnungen);
}

// --- AK3: genau PHP_INT_MAX bleibt eine gueltige Ziel-ID -----------------
// Auf 64-Bit-PHP (Projektumgebung) ist 9223372036854775807 = PHP_INT_MAX.
// filter_var() akzeptiert die Grenze noch, lehnt aber die naechstgroessere
// Ziffernfolge ab (in PHP direkt verifiziert) -- die Grenze wird also nicht
// zu weit gezogen.
$GLOBALS['test_permalinks'] = array(9223372036854775807 => 'https://example.test/riesige-id/');
$eingabe = verweis('9223372036854775807');
list($aus, $warnungen) = mit_warnzaehler($eingabe);
check('8.5 · AK3: PHP_INT_MAX wird weiterhin als gueltige ID gelesen (Verweis bearbeitet)', false !== strpos($aus, 'data-display-mode="modal"'), $aus);
check('8.6 · AK3: href zeigt auf die zugehoerige Seite', enthaelt_href($aus, 'https://example.test/riesige-id/?cbd-ref=cbd-container-abc123'), $aus);
check('8.7 · AK3: dabei entsteht keine PHP-Warnung', 0 === $warnungen, $warnungen);

// --- AK4: die bestehenden Ablehnungen bleiben unveraendert ---------------
// Ergaenzt die schon vorhandenen Faelle aus Abschnitt 5 (AK6) um genau die
// im AP genannten Werte, die dort mit anderen, aber gleichwertigen Werten
// geprueft wurden (z. B. "-5" statt "-7", "4.5" statt "4,5").
$ak4_ungueltig = array('+45', '4e2', '0x2d', '4,5', '  ', '-7', '0', 'abc');
foreach ($ak4_ungueltig as $wert) {
    $eingabe = verweis($wert);
    check('8.8 · AK4: data-target-post="' . $wert . '" bleibt zeichengleich', $eingabe === CBD_Inline_Reference::inhalt_auffrischen($eingabe));
}
$leer = '<p><a class="cbd-block-reference-inline" href="/x/" data-target-post="">Text</a></p>';
check('8.9 · AK4: leeres data-target-post bleibt zeichengleich', $leer === CBD_Inline_Reference::inhalt_auffrischen($leer));
$fehlend = '<p><a class="cbd-block-reference-inline" href="/x/">Text</a></p>';
check('8.10 · AK4: fehlendes data-target-post bleibt zeichengleich', $fehlend === CBD_Inline_Reference::inhalt_auffrischen($fehlend));

// =========================================================================
// 9 · AP-3.fix2: elf Struktur-Randfaelle des Tag-Processors
// =========================================================================
//
// Aus der Angriffssonde des Orchestrators (docs/PLAN-Inline-Blockreferenz.md,
// Abschnitt "AP-3.fix2") in den Bestand uebernommen. VIER Faelle (Skript,
// Stil, Textarea, HTML-Kommentar) verlangen, dass der Tag-Processor
// Rohtext-Elemente und Kommentare erkennt -- eine Eigenschaft, die
// CBD_Test_Tag_Processor laut seinem eigenen Kopfkommentar ausdruecklich
// NICHT hat ("kennt keine Rohtext-Elemente ... in WordPress nicht").
// Empirisch bestaetigt: Mit demselben Eingabe-HTML liefert die echte Klasse
// `found=0/unveraendert`, das Doppel dagegen `found=1/veraendert`. Diese vier
// laufen deshalb NUR mit der echten WordPress-Klasse; im erzwungenen
// Doppel-Modus werden sie SICHTBAR uebersprungen (SKIP-Zeile), nicht
// stillschweigend ausgelassen. Die uebrigen sieben Faelle verhalten sich in
// beiden Betriebsarten nachweislich gleich und laufen in beiden mit.

echo "\n== 9 · AP-3.fix2: elf Struktur-Randfaelle ==\n";

$GLOBALS['test_permalinks'] = array(
    45 => 'https://example.test/ir-spektroskopie/',
    12 => 'https://example.test/grundlagen/',
);
$GLOBALS['test_aktuelle_post'] = 999; // andere Seite als jedes Ziel in diesem Abschnitt

$GLOBALS['skips'] = 0;
function skip($label, $grund) {
    $GLOBALS['skips']++;
    echo "  SKIP $label -> $grund\n";
}

// $weg kommt aus Abschnitt 4 (tag_processor_bereitstellen()).
$mit_echtem_tag_processor = (false === strpos($weg, 'Doppel'));

// 1) Klasse an einem <span> statt <a>
$fall = '<p><span class="cbd-block-reference-inline" data-target-post="45">Text</span></p>';
check('9.1 · Klasse an <span> statt <a> -> zeichengleich', $fall === CBD_Inline_Reference::inhalt_auffrischen($fall));

// 2) <a> mit der Klasse in einem <script>-Block
$fall = '<div><script>// <a class="cbd-block-reference-inline" data-target-post="45">note</a></script></div>';
if ($mit_echtem_tag_processor) {
    check('9.2 · <a> mit Klasse in <script> -> zeichengleich', $fall === CBD_Inline_Reference::inhalt_auffrischen($fall));
} else {
    skip('9.2 · <a> mit Klasse in <script> -> zeichengleich', 'Doppel kennt keine Rohtext-Elemente (siehe Kopfkommentar CBD_Test_Tag_Processor)');
}

// 3) ... in einem <style>-Block
$fall = '<style>/* <a class="cbd-block-reference-inline" data-target-post="45">note</a> */</style>';
if ($mit_echtem_tag_processor) {
    check('9.3 · <a> mit Klasse in <style> -> zeichengleich', $fall === CBD_Inline_Reference::inhalt_auffrischen($fall));
} else {
    skip('9.3 · <a> mit Klasse in <style> -> zeichengleich', 'Doppel kennt keine Rohtext-Elemente (siehe Kopfkommentar CBD_Test_Tag_Processor)');
}

// 4) ... in einem <textarea>
$fall = '<textarea><a class="cbd-block-reference-inline" data-target-post="45">note</a></textarea>';
if ($mit_echtem_tag_processor) {
    check('9.4 · <a> mit Klasse in <textarea> -> zeichengleich', $fall === CBD_Inline_Reference::inhalt_auffrischen($fall));
} else {
    skip('9.4 · <a> mit Klasse in <textarea> -> zeichengleich', 'Doppel kennt keine Rohtext-Elemente (siehe Kopfkommentar CBD_Test_Tag_Processor)');
}

// 5) Klasse in einem HTML-Kommentar
$fall = '<!-- <a class="cbd-block-reference-inline" data-target-post="45">note</a> --><p>Text</p>';
if ($mit_echtem_tag_processor) {
    check('9.5 · Klasse in HTML-Kommentar -> zeichengleich', $fall === CBD_Inline_Reference::inhalt_auffrischen($fall));
} else {
    skip('9.5 · Klasse in HTML-Kommentar -> zeichengleich', 'Doppel erkennt keine Kommentare (siehe Kopfkommentar CBD_Test_Tag_Processor)');
}

// 6) Klasse als Wert eines fremden Attributs
$fall = '<p><a href="/x/" alt="Beschreibung mit cbd-block-reference-inline als Text">Text</a></p>';
check('9.6 · Klasse nur als Wert von alt -> zeichengleich', $fall === CBD_Inline_Reference::inhalt_auffrischen($fall));

// 7) Klasse in Grossschreibung
$fall = '<p><a class="CBD-BLOCK-REFERENCE-INLINE" data-target-post="45">Text</a></p>';
check('9.7 · Klasse in Grossschreibung -> zeichengleich', $fall === CBD_Inline_Reference::inhalt_auffrischen($fall));

// 8) unvollstaendiges Tag am Textende
$fall = '<p>Text <a class="cbd-block-reference-inline" data-target-post="45"';
check('9.8 · unvollstaendiges Tag am Textende -> zeichengleich', $fall === CBD_Inline_Reference::inhalt_auffrischen($fall));

// 9) zwei Klassen am Element, Reihenfolge erhalten
$fall = '<p><a class="cbd-block-reference-inline foo" data-target-post="45">Text</a></p>';
$aus = CBD_Inline_Reference::inhalt_auffrischen($fall);
check('9.9 · zwei Klassen: Verweis wurde bearbeitet', false !== strpos($aus, 'data-display-mode="modal"'), $aus);
check('9.9 · zwei Klassen: class-Attribut unveraendert (Reihenfolge erhalten)', false !== strpos($aus, 'class="cbd-block-reference-inline foo"'), $aus);

// 10) einfache Anfuehrungszeichen am Attribut
$fall = "<p><a class='cbd-block-reference-inline' data-target-post='45'>Text</a></p>";
$aus = CBD_Inline_Reference::inhalt_auffrischen($fall);
check('9.10 · einfache Anfuehrungszeichen: bearbeitet', $aus !== $fall && false !== strpos($aus, 'data-display-mode="modal"'), $aus);
check('9.10 · einfache Anfuehrungszeichen: Text erhalten', false !== strpos($aus, 'Text</a>'), $aus);

// 11) ganz ohne Anfuehrungszeichen am Attribut
$fall = '<p><a class=cbd-block-reference-inline data-target-post=45>Text</a></p>';
$aus = CBD_Inline_Reference::inhalt_auffrischen($fall);
check('9.11 · ohne Anfuehrungszeichen: bearbeitet', $aus !== $fall && false !== strpos($aus, 'data-display-mode="modal"'), $aus);
check('9.11 · ohne Anfuehrungszeichen: Text erhalten', false !== strpos($aus, 'Text</a>'), $aus);

// 12) verschachtelte <a> mit der Klasse
$fall = '<p><a class="cbd-block-reference-inline" data-target-post="45">Aussen <a class="cbd-block-reference-inline" data-target-post="12">Innen</a></a></p>';
$aus = CBD_Inline_Reference::inhalt_auffrischen($fall);
check('9.12 · verschachtelte <a>: beide bearbeitet', 2 === substr_count($aus, 'data-display-mode="modal"'), $aus);
check('9.12 · verschachtelte <a>: aussen-Text erhalten', false !== strpos($aus, 'Aussen'), $aus);
check('9.12 · verschachtelte <a>: innen-Text erhalten', false !== strpos($aus, 'Innen'), $aus);

if ($GLOBALS['skips'] > 0) {
    echo "\n  [" . $GLOBALS['skips'] . " Pruefung(en) im Doppel-Modus SICHTBAR uebersprungen -- siehe SKIP-Zeilen oben]\n";
}

// =========================================================================
// 10 · AP-3.fix5 (Befund S3): fuehrende Nullen bleiben abgelehnt
// =========================================================================
//
// ziel_post_id() lehnt seit AP-3.fix2 auch Ziffernfolgen mit fuehrender Null
// ab: ctype_digit('045') ist wahr, filter_var('045', FILTER_VALIDATE_INT)
// aber false (vor AP-3.fix2 ergab (int)'045' noch 45). Der Doc-Kommentar
// sagte bislang nur "eine reine Ziffernfolge zaehlt" -- "045" IST eine, und
// keine der bisherigen 155 Pruefungen deckte den Fall ab. Die Ablehnung ist
// inhaltlich richtig (eine Beitrags-ID hat keine fuehrenden Nullen) und
// bleibt unveraendert; diese zwei Pruefungen nageln sie fest, statt sie nur
// im Kommentar zu behaupten. Beide Faelle laufen unabhaengig vom
// Tag-Processor-Weg (kein Rohtext-Element, kein Kommentar beteiligt) --
// kein SKIP noetig.

echo "\n== 10 · AP-3.fix5 (S3): fuehrende Nullen ==\n";

$GLOBALS['test_aktuelle_post'] = 999;
$GLOBALS['test_permalinks'] = array(45 => 'https://example.test/ir-spektroskopie/');

foreach (array('045', '00000000000000000045') as $wert) {
    $eingabe = verweis($wert);
    check(
        '10.1 · AK1: data-target-post="' . $wert . '" bleibt zeichengleich (fuehrende Null)',
        $eingabe === CBD_Inline_Reference::inhalt_auffrischen($eingabe),
        $eingabe
    );
}

// =========================================================================

$fails = $GLOBALS['fails'];
$skips = isset($GLOBALS['skips']) ? $GLOBALS['skips'] : 0;
echo "\n" . $GLOBALS['pruefungen'] . " Pruefungen"
    . ($skips > 0 ? " (" . $skips . " im Doppel-Modus sichtbar uebersprungen)" : "") . ", "
    . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
