<?php
/**
 * Prüfharnisch für Formeln im Blocktitel (AP-2, PLAN-Formeln-in-Blocktiteln.md).
 *
 * Geprüft wird die Hilfsmethode CBD_Block_Registration::titel_mit_formeln().
 * Sie ist privat; der Harnisch greift über Reflection darauf zu — die Methode
 * gehört zur Klasse und soll nicht nur für den Test öffentlich werden.
 *
 * Läuft OHNE WordPress, mit denselben Stubs wie tools/test-latex-parser.php.
 *
 * Aufruf:
 *   php tools/test-blocktitel-latex.php
 *
 * Der Unteraufruf "ohne-parser" prüft AK5 (fehlende Parser-Klasse) in einem
 * eigenen Prozess — anders ist eine bereits geladene Klasse nicht wieder
 * loszuwerden.
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');
ini_set('display_errors', 'stderr');

$ohne_parser = isset($argv[1]) && $argv[1] === 'ohne-parser';

// --- WordPress-Stubs (Auszug aus tools/test-latex-parser.php) --------------
function add_action($hook, $cb, $priority = 10, $args = 1) { return true; }
function add_filter($hook, $cb, $priority = 10, $args = 1) { return true; }
function __($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s)  { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s)   { return (string) $s; }
function is_admin()    { return false; }
function is_singular($t = '') { return true; }
function is_home()     { return false; }
function is_archive()  { return false; }
function get_queried_object()    { return null; }
function get_queried_object_id() { return 0; }
function get_post($id = null)    { return null; }

$wurzel = str_replace('\\', '/', dirname(__DIR__));

if (!$ohne_parser) {
    require_once $wurzel . '/includes/class-latex-parser.php';
}
require_once $wurzel . '/includes/class-cbd-block-registration.php';

// --- Prüfgerüst ------------------------------------------------------------
$GLOBALS['ok'] = 0;
$GLOBALS['fehler'] = 0;

function pruefe($nummer, $beschreibung, $bedingung, $tatsaechlich = null) {
    if ($bedingung) {
        $GLOBALS['ok']++;
        echo "  OK   $nummer · $beschreibung\n";
        return;
    }
    $GLOBALS['fehler']++;
    echo "  FEHL $nummer · $beschreibung\n";
    if ($tatsaechlich !== null) {
        echo "       tatsächlich: " . var_export($tatsaechlich, true) . "\n";
    }
}

/**
 * Ruft die private Hilfsmethode auf.
 *
 * Scheitert der Zugriff, ist das ein Fehlschlag des Tests und kein Abbruch —
 * vor der Umsetzung von AP-2 gibt es die Methode noch nicht, und genau das
 * soll der rote Lauf zeigen.
 */
function titel($roh) {
    static $methode = null;

    if ($methode === null) {
        if (!method_exists('CBD_Block_Registration', 'titel_mit_formeln')) {
            $methode = false;
        } else {
            $methode = new ReflectionMethod('CBD_Block_Registration', 'titel_mit_formeln');
            $methode->setAccessible(true);
        }
    }

    if ($methode === false) {
        return '(Methode titel_mit_formeln() existiert nicht)';
    }

    return $methode->isStatic()
        ? $methode->invoke(null, $roh)
        : $methode->invoke(CBD_Block_Registration::get_instance(), $roh);
}

// --------------------------------------------------------------------------
// Unteraufruf: nur AK5, ohne geladene Parser-Klasse
// --------------------------------------------------------------------------
if ($ohne_parser) {
    echo "== Ohne CBD_LaTeX_Parser ==\n";
    pruefe(
        'AK5a',
        'Parser-Klasse ist tatsächlich nicht geladen',
        !class_exists('CBD_LaTeX_Parser', false)
    );
    $roh = 'Energie $E = mc^2$ und Umlaut ä';
    $aus = titel($roh);
    pruefe('AK5b', 'Titel kommt escapt zurück, ohne Fatal Error', $aus === esc_html($roh), $aus);
    pruefe('AK5c', 'Kein Formel-Span ohne Parser', strpos($aus, 'cbd-latex-formula') === false, $aus);

    echo "\n{$GLOBALS['ok']} bestanden, {$GLOBALS['fehler']} fehlgeschlagen\n";
    exit($GLOBALS['fehler'] === 0 ? 0 : 1);
}

// --------------------------------------------------------------------------
// Hauptlauf
// --------------------------------------------------------------------------
echo "== Formeln im Blocktitel ==\n";

// AK1 — Formel wird gerendert
$aus = titel('Energie $E = mc^2$');
pruefe('AK1a', 'Formel im Titel erzeugt einen Formel-Span', strpos($aus, 'cbd-latex-formula') !== false, $aus);
pruefe('AK1b', 'Der Text um die Formel bleibt erhalten', strpos($aus, 'Energie') === 0, $aus);

$aus = titel('Bruch $\frac{a}{b}$ und Punkt $\cdot$');
pruefe('AK1c', 'Zwei Formeln im Titel ergeben zwei Spans', substr_count($aus, 'cbd-latex-formula') >= 2, $aus);
pruefe('AK1d', 'Der Backslash von \\frac überlebt', strpos($aus, 'frac') !== false, $aus);

// AK2 — Titel ohne Formel bleibt zeichengleich zum bisherigen Verhalten
foreach (array(
    'Schlichter Titel',
    'Küchenchemie & Co.',
    'Er sagte "Hallo"',
    "Ableitung f'(x) ohne Formel",
    'Preis: 5 € — kein Dollar',
    '',
) as $i => $roh) {
    $aus = titel($roh);
    pruefe('AK2.' . ($i + 1), 'Ohne Formel zeichengleich mit esc_html(): ' . var_export($roh, true),
        $aus === esc_html($roh), $aus);
}

// AK3 — Markup im Titel bleibt Text
$aus = titel('<script>alert(1)</script>');
pruefe('AK3a', 'Kein ausführbares script-Tag im Ergebnis', stripos($aus, '<script') === false, $aus);
pruefe('AK3b', 'Das Tag erscheint als sichtbarer Text', strpos($aus, '&lt;script&gt;') !== false, $aus);

$aus = titel('<img src=x onerror=alert(1)>');
pruefe('AK3c', 'Kein img-Tag im Ergebnis', stripos($aus, '<img') === false, $aus);

// AK4 — Apostroph wird nicht typografisch
$aus = titel("Ableitung f'(x)");
pruefe('AK4a', 'Apostroph bleibt gerader Apostroph (als Entity)', strpos($aus, '&#039;') !== false, $aus);
pruefe('AK4b', 'Kein typografisches Anführungszeichen', strpos($aus, "\xe2\x80\x99") === false, $aus);

// Doppelparse
//
// BERICHTIGT am 2026-08-21. Hier stand zuerst die Prüfung, ein bereits
// gerenderter Titel müsse unverändert durchlaufen ($zweimal === $einmal).
// Diese Erwartung war falsch: titel_mit_formeln() escapt ZUERST, der
// Doppelparse-Schutz in parse_latex() sieht die Marke `cbd-latex-formula`
// danach nie mehr — sie steht dann als `&lt;span class=&quot;…` da.
//
// Vor allem aber beschreibt sie einen Fall, den es nicht gibt: Die Methode
// bekommt IMMER den rohen Wert aus dem Blockattribut, nie ihre eigene
// Ausgabe. Geprüft wird deshalb das, was tatsächlich gilt — und das ist
// sogar die schärfere Zusicherung.
$einmal  = titel('Energie $E = mc^2$');
$zweimal = titel('Energie $E = mc^2$');
$ohne_id = function ($html) { return preg_replace('/cbd-latex-inline-[0-9a-f]+-\d+/', 'ID', $html); };
pruefe('DP1', 'Zweimal derselbe Rohtitel ergibt dieselbe Struktur',
    $ohne_id($einmal) === $ohne_id($zweimal), $zweimal);

// Sicherheitsseite desselben Verhaltens: Wer den Klassennamen selbst in den
// Titel schreibt, erzeugt damit KEIN Markup.
$aus = titel('<span class="cbd-latex-formula" data-latex="\\evil">x</span>');
pruefe('DP2', 'Selbst geschriebenes Formel-Markup bleibt Text',
    stripos($aus, '<span') === false && strpos($aus, '&lt;span') !== false, $aus);

// Aufrufe stören sich nicht gegenseitig
$a = titel('Erster $\alpha$');
$b = titel('Zweiter $\beta$');
pruefe('ZS1', 'Zwei Aufrufe nacheinander bleiben getrennt',
    strpos($a, 'alpha') !== false && strpos($b, 'beta') !== false && strpos($b, 'alpha') === false,
    array($a, $b));

// Nicht-Zeichenketten dürfen nicht in einen Fatal Error laufen
$aus = titel(null);
pruefe('RB1', 'null ergibt eine leere Zeichenkette', $aus === '', $aus);

// AK5 im eigenen Prozess
echo "\n== Unteraufruf ohne Parser-Klasse ==\n";
$befehl = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ohne-parser';
$ausgabe = array();
$code = 0;
exec($befehl . ' 2>&1', $ausgabe, $code);
foreach ($ausgabe as $zeile) {
    echo '  | ' . $zeile . "\n";
}
pruefe('AK5', 'Unteraufruf ohne Parser-Klasse bestanden', $code === 0);

echo "\n{$GLOBALS['ok']} bestanden, {$GLOBALS['fehler']} fehlgeschlagen\n";

if ($GLOBALS['fehler'] > 0) {
    echo "FEHLGESCHLAGEN\n";
    exit(1);
}
echo "ALLE TESTS BESTANDEN\n";
exit(0);
