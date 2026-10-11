<?php
/**
 * Standalone-Harness für AP-1.2 (#7109): Block-IDs und cbd-ref-Kodierung.
 * Läuft OHNE WordPress.
 *
 * Aufruf:  php tools/test-block-reference-ids.php [Plugin-Ordner]
 * (Ohne Argument: der Ordner oberhalb von tools/.) Liegt unter tools/ und ist
 * damit nicht im Verteilungs-ZIP.
 *
 * Geprüft wird:
 *  (a) CBD_Block_Serializer::erzeuge_stable_id() liefert eine ID ohne
 *      E-Notation (/^cbd-\d{13}-[a-z0-9]+$/), auch mit ini `precision` = 12
 *      (so entstanden `cbd-1.78752232945E+12-…` auf dem Server).
 *  (b) Der href eines Verweises auf `cbd-1.78752232945E+12-2ixi6mg4` trägt
 *      `cbd-ref=cbd-1.78752232945E%2B12-2ixi6mg4` — Inline-Verweis
 *      (ziel_href(), per Reflection) UND Block-Verweis (render.php, per
 *      Quelltextprüfung, da das Template nicht ohne WordPress läuft).
 */

$plugin_dir = isset($argv[1]) ? rtrim($argv[1], '/\\') . '/' : dirname(__DIR__) . '/';

define('ABSPATH', '/');

$GLOBALS['ergebnis'] = array('ok' => 0, 'fehler' => 0);
function check($name, $bedingung) {
    if ($bedingung) {
        $GLOBALS['ergebnis']['ok']++;
        echo "  OK   $name\n";
    } else {
        $GLOBALS['ergebnis']['fehler']++;
        echo "  FAIL $name\n";
    }
}

// --- WordPress-Stubs (so nah an WordPress wie nötig) ----------------------
function __($s, $d = null) { return $s; }
function add_action($t, $c, $p = 10, $a = 1) { return true; }
function add_filter($t, $c, $p = 10, $a = 1) { return true; }
function wp_rand($min = 0, $max = 0) { return mt_rand($min, $max); }
function esc_url_raw($u, $p = null) {
    return str_replace(array(' ', '"', '<', '>', "\n", "\r", "\t"), '', (string) $u);
}
function get_permalink($id = 0) { return 'https://example.test/seite/'; }
/**
 * WordPress' add_query_arg() kodiert NEUE Werte NICHT (nur der bereits
 * vorhandene Query-String wird re-kodiert) — der Aufrufer muss selbst
 * rawurlencode() anwenden. Genau das prüft dieser Harnisch.
 */
function add_query_arg($key, $value = null, $url = '') {
    $trenner = (false === strpos((string) $url, '?')) ? '?' : '&';
    return $url . $trenner . $key . '=' . $value;
}

$serializer = $plugin_dir . 'includes/class-cbd-block-serializer.php';
$inline     = $plugin_dir . 'includes/class-cbd-inline-reference.php';
$render     = $plugin_dir . 'blocks/block-reference/render.php';

require_once $serializer;
require_once $inline;

echo "== (a) Serializer: ganzzahlige IDs ==\n";
$ids = array();
for ($i = 0; $i < 20; $i++) {
    $ids[] = CBD_Block_Serializer::erzeuge_stable_id();
}
$alle_ok = true;
foreach ($ids as $id) {
    if (!preg_match('/^cbd-\d{13}-[a-z0-9]+$/', $id)) {
        $alle_ok = false;
        echo "       Unerwartete ID: $id\n";
    }
}
check('a.1 · Standard-precision: alle IDs entsprechen /^cbd-\d{13}-[a-z0-9]+$/', $alle_ok);

$vorher = ini_get('precision');
ini_set('precision', '12');
$id = CBD_Block_Serializer::erzeuge_stable_id();
ini_set('precision', $vorher);
check('a.2 · precision=12 ergibt KEINE E-Notation (' . $id . ')', 1 === preg_match('/^cbd-\d{13}-[a-z0-9]+$/', $id));

echo "\n== (b) cbd-ref in der Adresse ==\n";
$alt = 'cbd-1.78752232945E+12-2ixi6mg4';
$methode = new ReflectionMethod('CBD_Inline_Reference', 'ziel_href');
$methode->setAccessible(true);
$href = $methode->invoke(null, 5, '', $alt);
check('b.1 · Inline-Verweis: href enthält cbd-ref=cbd-1.78752232945E%2B12-2ixi6mg4 (' . $href . ')',
    false !== strpos((string) $href, 'cbd-ref=cbd-1.78752232945E%2B12-2ixi6mg4'));
check('b.2 · Inline-Verweis: kein rohes "+" im Wert', false === strpos((string) $href, 'E+12'));

$normal = $methode->invoke(null, 5, '', 'cbd-1787522329450-abc123');
check('b.3 · normale ID bleibt unverändert lesbar', false !== strpos((string) $normal, 'cbd-ref=cbd-1787522329450-abc123'));

$quelle = file_get_contents($render);
check('b.4 · render.php kodiert cbd-ref mit rawurlencode()',
    1 === preg_match("/add_query_arg\(\s*'cbd-ref'\s*,\s*rawurlencode\(\s*\\\$target_stable_id\s*\)/", $quelle));

echo "\n" . $GLOBALS['ergebnis']['ok'] . ' OK, ' . $GLOBALS['ergebnis']['fehler'] . " Fehler\n";
exit($GLOBALS['ergebnis']['fehler'] > 0 ? 1 : 0);
