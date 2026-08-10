<?php
/**
 * Standalone-Harness für CBD_Design_Transfer — läuft OHNE WordPress und
 * ohne Datenbank.
 *
 * Geprüft wird die Eingangsvalidierung des Imports (JSON und Markdown):
 * Was darf hinein, was wird abgelehnt, und bleibt ein exportiertes Design
 * beim Wiedereinlesen unverändert.
 *
 * Aufruf:  php tools/test-design-transfer.php
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');
define('MINUTE_IN_SECONDS', 60);

$plugin_dir = str_replace('\\', '/', dirname(__DIR__)) . '/';
define('CBD_PLUGIN_DIR', $plugin_dir);
define('CBD_VERSION', 'test');

// --- Stubs ----------------------------------------------------------------
function __($s, $d = null) { return $s; }
function add_action() {}
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
/**
 * Vereinfachte Fassung der WordPress-Funktion. Wichtig: sie wandelt auch
 * GROSSbuchstaben mit Diakritika — sonst testet man an der Realität vorbei.
 */
function remove_accents($s) {
    return strtr($s, array(
        'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
        'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'á' => 'a',
        'É' => 'E', 'À' => 'A', 'ñ' => 'n', 'ç' => 'c',
    ));
}

require_once $plugin_dir . 'includes/class-cbd-design-transfer.php';

$GLOBALS['fails'] = 0;

function check($label, $condition, $actual = null) {
    if ($condition) {
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['fails']++;
    echo "  FAIL $label" . (null !== $actual ? ' -> ' . var_export($actual, true) : '') . "\n";
}

function rejects($label, $json) {
    $r = CBD_Design_Transfer::parse_payload($json);
    check($label, '' !== $r['error'] && empty($r['designs']), $r['error']);
}

/** Baut eine gueltige Exportdatei. */
function payload($designs) {
    return json_encode(array(
        'format'        => 'cbd-designs',
        'formatVersion' => 1,
        'plugin'        => '3.1.78',
        'designs'       => $designs,
    ));
}

echo "== Ablehnung ungueltiger Dateien ==\n";
rejects('leere Datei', '   ');
rejects('kein JSON', 'das ist keine json-datei');
rejects('JSON ohne format-Feld', json_encode(array('designs' => array(array('name' => 'x')))));
rejects('falsches format-Feld', json_encode(array('format' => 'irgendwas', 'designs' => array(array('name' => 'x')))));
rejects('neuere Formatversion', json_encode(array('format' => 'cbd-designs', 'formatVersion' => 99, 'designs' => array(array('name' => 'x')))));
rejects('keine Designs', payload(array()));
rejects('Designs ohne name', payload(array(array('title' => 'Ohne Slug'))));
rejects('nur unbrauchbare Slugs', payload(array(array('name' => '---'), array('name' => '!!!'))));

echo "\n== Gueltige Datei ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array(
        'name' => 'info-box', 'title' => 'Info-Box', 'description' => 'Ein Hinweis',
        'config' => array('allowInnerBlocks' => true),
        'styles' => array('padding' => array('top' => 10)),
        'features' => array('icon' => array('enabled' => true, 'value' => '{"type":"custom","value":"kategorien/hinweise"}')),
        'status' => 'active',
    ),
)));
check('akzeptiert', '' === $r['error'], $r['error']);
check('ein Design', 1 === count($r['designs']));
$d = $r['designs'][0];
check('Slug erhalten', 'info-box' === $d['name'], $d['name']);
check('Titel erhalten', 'Info-Box' === $d['title']);
check('config ist JSON-String', is_string($d['config']) && is_array(json_decode($d['config'], true)), $d['config']);
check('config-Inhalt erhalten', true === json_decode($d['config'], true)['allowInnerBlocks']);
check('verschachtelte styles erhalten', 10 === json_decode($d['styles'], true)['padding']['top']);
check('Icon-Wert im features-Feld ueberlebt',
    false !== strpos(json_decode($d['features'], true)['icon']['value'], 'kategorien/hinweise'),
    json_decode($d['features'], true)['icon']['value']);

echo "\n== Slug-Normalisierung ==\n";
$cases = array(
    'Info Box'          => 'info-box',
    'Übungen'           => 'ubungen',
    'MEIN DESIGN'       => 'mein-design',
    '../../etc/passwd'  => 'etc-passwd',
    'a<script>b'        => 'a-script-b',
    'mit_unterstrich'   => 'mit-unterstrich',
    '--doppel--strich--' => 'doppel-strich',
);
foreach ($cases as $in => $expected) {
    $actual = CBD_Design_Transfer::sanitize_slug($in);
    check(var_export($in, true) . ' -> ' . var_export($expected, true), $expected === $actual, $actual);
}

echo "\n== Doppelte Slugs in einer Datei ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array('name' => 'box', 'title' => 'Erste'),
    array('name' => 'box', 'title' => 'Zweite'),
    array('name' => 'Box', 'title' => 'Dritte'),   // normalisiert ebenfalls zu "box"
)));
check('nur ein Eintrag bleibt', 1 === count($r['designs']), count($r['designs']));
check('der erste gewinnt', 'Erste' === $r['designs'][0]['title'], $r['designs'][0]['title']);

echo "\n== Boesartige Inhalte ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array(
        'name' => 'evil',
        'title' => '<script>alert(1)</script>',
        'description' => '<img src=x onerror=alert(1)>',
        'styles' => array('background' => '<script>alert(1)</script>'),
    ),
)));
$d = $r['designs'][0];
check('Titel entschaerft', false === strpos($d['title'], '<'), $d['title']);
check('Beschreibung entschaerft', false === strpos($d['description'], '<'), $d['description']);
check('verschachtelter Style entschaerft', false === strpos($d['styles'], '<script'), $d['styles']);

echo "\n== Unbekannte Felder werden verworfen ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array('name' => 'sauber', 'title' => 'Sauber', 'id' => 999, 'created_at' => '2020-01-01', 'boese' => 'hallo'),
)));
$d = $r['designs'][0];
check('kein id-Feld', !isset($d['id']), array_keys($d));
check('kein created_at', !isset($d['created_at']));
check('kein Fremdfeld', !isset($d['boese']));
check('nur erwartete Schluessel',
    array('name','title','description','config','styles','features','status','is_default') === array_keys($d),
    array_keys($d));

echo "\n== Status wird auf zwei Werte begrenzt ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array('name' => 'a', 'status' => 'inactive'),
    array('name' => 'b', 'status' => 'geloescht'),
)));
check('inactive bleibt', 'inactive' === $r['designs'][0]['status']);
check('unbekannter Status -> active', 'active' === $r['designs'][1]['status'], $r['designs'][1]['status']);

echo "\n== config als JSON-String (handgeschriebene Datei) ==\n";
$r = CBD_Design_Transfer::parse_payload(payload(array(
    array('name' => 'string-config', 'config' => '{"maxWidth":"800px"}'),
)));
check('String-config wird gelesen', '800px' === json_decode($r['designs'][0]['config'], true)['maxWidth'],
    $r['designs'][0]['config']);

echo "\n== Rundlauf: Export -> Import ==\n";
$original = array(
    'name' => 'merksatz', 'title' => 'Merksatz', 'description' => 'Wichtig',
    'config' => array('allowInnerBlocks' => true, 'maxWidth' => '900px'),
    'styles' => array('border' => array('width' => 2, 'color' => '#e24614')),
    'features' => array('numbering' => array('enabled' => true, 'format' => 'numeric')),
    'status' => 'active', 'is_default' => 0,
);
$r = CBD_Design_Transfer::parse_payload(payload(array($original)));
$d = $r['designs'][0];
check('Slug identisch', $original['name'] === $d['name']);
check('config identisch', $original['config'] === json_decode($d['config'], true), json_decode($d['config'], true));
check('styles identisch', $original['styles'] === json_decode($d['styles'], true), json_decode($d['styles'], true));
check('features identisch', $original['features'] === json_decode($d['features'], true), json_decode($d['features'], true));

// =====================================================================
//  Markdown
// =====================================================================

function md_rejects($label, $md) {
    $r = CBD_Design_Transfer::parse_markdown($md);
    check($label, '' !== $r['error'] && empty($r['designs']), $r['error']);
}

/** Ein Design in der Form, die to_markdown() erwartet (dekodierte Felder). */
function design($overrides = array()) {
    return array_merge(array(
        'name' => 'info-box', 'title' => 'Info-Box', 'description' => '',
        'config' => array(), 'styles' => array(), 'features' => array(),
        'status' => 'active', 'is_default' => 0,
    ), $overrides);
}

echo "\n== Markdown: Ablehnung ==\n";
md_rejects('leere Datei', "   \n  ");
md_rejects('Text ohne H2', "# Nur eine Ueberschrift\n\nEtwas Fliesstext.");
md_rejects('H2 ohne brauchbaren Slug', "## ---\n\n- **Slug:** `!!!`");

echo "\n== Markdown: Grundgeruest ==\n";
$md = <<<'MD'
# Container-Designs

## Info-Box

- **Slug:** `info-box`
- **Status:** inaktiv
- **Standard:** ja

Hinweiskasten fuer Merksaetze.

### Konfiguration

- `allowInnerBlocks`: true
- `maxWidth`: 900px

### Stile

- `background.color`: #f5ede9
- `border.width`: 2
- `padding.top`: 20

### Funktionen

- `icon.enabled`: true
- `icon.value`: {"type":"custom","value":"kategorien/hinweise"}
- `collapse.enabled`: false
MD;
$r = CBD_Design_Transfer::parse_markdown($md);
check('akzeptiert', '' === $r['error'], $r['error']);
check('ein Design', 1 === count($r['designs']), count($r['designs']));
$d = $r['designs'][0];
check('Slug aus Stammdaten', 'info-box' === $d['name'], $d['name']);
check('Titel aus H2', 'Info-Box' === $d['title'], $d['title']);
check('Status inaktiv', 'inactive' === $d['status'], $d['status']);
check('Standard erkannt', 1 === $d['is_default'], $d['is_default']);
check('Beschreibung aus Fliesstext', 'Hinweiskasten fuer Merksaetze.' === $d['description'], $d['description']);

$config = json_decode($d['config'], true);
$styles = json_decode($d['styles'], true);
$features = json_decode($d['features'], true);
check('Wahrheitswert bleibt bool', true === $config['allowInnerBlocks'], $config['allowInnerBlocks']);
check('Text bleibt Text', '900px' === $config['maxWidth'], $config['maxWidth']);
check('Punkt-Pfad wird verschachtelt', '#f5ede9' === $styles['background']['color'], $styles);
check('Zahl wird Zahl', 2 === $styles['border']['width'], $styles['border']['width']);
check('zweite Zahl', 20 === $styles['padding']['top'], $styles['padding']['top']);
check('Icon-Wert mit Doppelpunkten ueberlebt',
    '{"type":"custom","value":"kategorien/hinweise"}' === $features['icon']['value'],
    $features['icon']['value']);
check('false bleibt false (nicht truthy!)', false === $features['collapse']['enabled'],
    var_export($features['collapse']['enabled'], true));

echo "\n== Markdown: Rundlauf Export -> Import ==\n";
$original = design(array(
    'name' => 'merksatz', 'title' => 'Merksatz', 'description' => "Zeile eins\nZeile zwei",
    'config' => array('allowInnerBlocks' => true, 'maxWidth' => '900px'),
    'styles' => array(
        'border' => array('width' => 2, 'color' => '#e24614', 'style' => 'solid'),
        'effects' => array('glassmorphism' => array('enabled' => false, 'opacity' => 0.1)),
        'text' => array('alignment' => 'left'),
    ),
    'features' => array(
        'icon' => array('enabled' => true, 'value' => '{"type":"custom","value":"kategorien/hinweise"}'),
        'numbering' => array('enabled' => true, 'format' => 'numeric'),
        'copyText' => array('enabled' => false, 'buttonText' => 'Text kopieren'),
    ),
    'status' => 'inactive',
));
$roundtrip = CBD_Design_Transfer::parse_markdown(CBD_Design_Transfer::to_markdown(array($original)));
check('Rundlauf akzeptiert', '' === $roundtrip['error'], $roundtrip['error']);
$d = $roundtrip['designs'][0];
check('Slug identisch', 'merksatz' === $d['name'], $d['name']);
check('Titel identisch', 'Merksatz' === $d['title'], $d['title']);
check('Status identisch', 'inactive' === $d['status'], $d['status']);
check('mehrzeilige Beschreibung erhalten', "Zeile eins\nZeile zwei" === $d['description'], $d['description']);
check('config identisch', $original['config'] === json_decode($d['config'], true), json_decode($d['config'], true));
check('styles identisch', $original['styles'] === json_decode($d['styles'], true), json_decode($d['styles'], true));
check('features identisch', $original['features'] === json_decode($d['features'], true), json_decode($d['features'], true));

echo "\n== Markdown: heikle Werte ==\n";
$tricky = design(array(
    'name' => 'tricky',
    'styles' => array(
        'leer'      => '',
        'wortJa'    => 'ja',
        'wortTrue'  => 'true',
        'zahlAlsText' => '007',
        'raute'     => '#e24614',
        'backtick'  => '`code`',
        'anfuehrung' => '"zitat"',
        'doppelpunkt' => 'a: b: c',
    ),
));
$r = CBD_Design_Transfer::parse_markdown(CBD_Design_Transfer::to_markdown(array($tricky)));
$s = json_decode($r['designs'][0]['styles'], true);
check('leerer Text bleibt leer', '' === $s['leer'], var_export($s['leer'], true));
check('"ja" bleibt Text', 'ja' === $s['wortJa'], var_export($s['wortJa'], true));
check('"true" bleibt Text', 'true' === $s['wortTrue'], var_export($s['wortTrue'], true));
check('"007" bleibt Text', '007' === $s['zahlAlsText'], var_export($s['zahlAlsText'], true));
check('Farbwert unveraendert', '#e24614' === $s['raute'], $s['raute']);
check('Backticks im Wert erhalten', '`code`' === $s['backtick'], $s['backtick']);
check('Anfuehrungszeichen im Wert erhalten', '"zitat"' === $s['anfuehrung'], $s['anfuehrung']);
check('Doppelpunkte im Wert erhalten', 'a: b: c' === $s['doppelpunkt'], $s['doppelpunkt']);

echo "\n== Markdown: Zahlen (bewusste Typaenderung) ==\n";
// Aus $_POST kommt alles als Zeichenkette. Die Datei schreibt solche Werte
// unquotiert, beim Import werden daraus echte Zahlen — gewollt, damit die
// Datei lesbar bleibt. Fuer die CSS-Erzeugung ist "20" dasselbe wie 20.
$zahlen = design(array('name' => 'zahlen', 'styles' => array(
    'padding' => array('top' => '20'),
    'border'  => array('width' => 2, 'radius' => '4'),
    'effects' => array('opacity' => '0.1', 'genau' => '0.10'),
    'gross'   => '999999999999999999999999',
)));
$md = CBD_Design_Transfer::to_markdown(array($zahlen));
check('Zahl steht unquotiert in der Datei', false !== strpos($md, '`padding.top`: 20'), $md);
$s = json_decode(CBD_Design_Transfer::parse_markdown($md)['designs'][0]['styles'], true);
check('"20" wird zur Zahl 20', 20 === $s['padding']['top'], var_export($s['padding']['top'], true));
check('echte Zahl bleibt Zahl', 2 === $s['border']['width'], var_export($s['border']['width'], true));
check('"0.1" wird Kommazahl', 0.1 === $s['effects']['opacity'], var_export($s['effects']['opacity'], true));
check('"0.10" bleibt Text (kein Praezisionsverlust)', '0.10' === $s['effects']['genau'], var_export($s['effects']['genau'], true));
check('ueberlange Ziffernfolge bleibt Text', '999999999999999999999999' === $s['gross'], var_export($s['gross'], true));

echo "\n== Markdown: Beschreibung mit Sonderzeichen ==\n";
$hashy = design(array('name' => 'hashy', 'description' => "# Keine Ueberschrift\n- kein Stammdatum"));
$r = CBD_Design_Transfer::parse_markdown(CBD_Design_Transfer::to_markdown(array($hashy)));
check('ein Design (Raute zerschneidet nicht)', 1 === count($r['designs']), count($r['designs']));
check('Beschreibung unveraendert',
    "# Keine Ueberschrift\n- kein Stammdatum" === $r['designs'][0]['description'],
    $r['designs'][0]['description']);

echo "\n== Markdown: handgeschrieben, minimal ==\n";
$r = CBD_Design_Transfer::parse_markdown("## Meine Box\n\n### Stile\n\n- background.color: #fff\n");
check('akzeptiert ohne Stammdaten', '' === $r['error'], $r['error']);
check('Slug aus der Ueberschrift', 'meine-box' === $r['designs'][0]['name'], $r['designs'][0]['name']);
check('Wert ohne Backticks gelesen', '#fff' === json_decode($r['designs'][0]['styles'], true)['background']['color'],
    $r['designs'][0]['styles']);
check('Status faellt auf active', 'active' === $r['designs'][0]['status'], $r['designs'][0]['status']);

echo "\n== Markdown: mehrere Designs, doppelte Slugs ==\n";
$r = CBD_Design_Transfer::parse_markdown(
    "## Erste\n\n- **Slug:** `box`\n\n## Zweite\n\n- **Slug:** `box`\n\n## Dritte\n\n- **Slug:** `andere`\n"
);
check('zwei Designs bleiben', 2 === count($r['designs']), count($r['designs']));
check('der erste gewinnt', 'Erste' === $r['designs'][0]['title'], $r['designs'][0]['title']);

echo "\n== Markdown: Traversal und Muell in Schluesseln ==\n";
// Schluessel duerfen nur [A-Za-z0-9_-] je Segment enthalten. Zeilen, die das
// verletzen, werden komplett verworfen — es wird KEIN bereinigter Schluessel
// erfunden, sonst landete unter einem Namen ein Wert, den niemand geschrieben
// hat. Die Segment-Bereinigung in unflatten() ist die zweite Schranke.
$r = CBD_Design_Transfer::parse_markdown(
    "## Boese\n\n- **Slug:** `boese`\n\n### Stile\n"
    . "- `../../etc.passwd`: x\n"
    . "- `a.<script>.b`: y\n"
    . "- `.....`: z\n"
    . "- `background.color`: #fff\n"
);
$s = json_decode($r['designs'][0]['styles'], true);
check('Traversal-Zeile verworfen', !isset($s['etc']) && !isset($s['..']), array_keys($s));
check('Markup-Zeile verworfen', !isset($s['a']), array_keys($s));
check('reine Punkte ergeben nichts', !isset($s['']), array_keys($s));
check('gueltige Zeile daneben bleibt erhalten', '#fff' === $s['background']['color'], $s);

echo "\n== Markdown: boesartige Inhalte ==\n";
$r = CBD_Design_Transfer::parse_markdown(
    "## <script>alert(1)</script>\n\n- **Slug:** `evil`\n\n"
    . "<img src=x onerror=alert(1)>\n\n"
    . "### Stile\n\n- `background.color`: <script>alert(1)</script>\n"
);
$d = $r['designs'][0];
check('Titel entschaerft', false === strpos($d['title'], '<'), $d['title']);
check('Beschreibung entschaerft', false === strpos($d['description'], '<'), $d['description']);
check('Style-Wert entschaerft', false === strpos($d['styles'], '<script'), $d['styles']);

echo "\n== Formatweiche parse_file() ==\n";
$r = CBD_Design_Transfer::parse_file(payload(array(array('name' => 'aus-json', 'title' => 'JSON'))));
check('JSON erkannt', '' === $r['error'] && 'aus-json' === $r['designs'][0]['name'], $r['error']);
$r = CBD_Design_Transfer::parse_file("## Aus MD\n\n- **Slug:** `aus-md`\n");
check('Markdown erkannt', '' === $r['error'] && 'aus-md' === $r['designs'][0]['name'], $r['error']);
$r = CBD_Design_Transfer::parse_file("\xEF\xBB\xBF## Mit BOM\n\n- **Slug:** `mit-bom`\n");
check('BOM stoert nicht', '' === $r['error'] && 'mit-bom' === $r['designs'][0]['name'], $r['error']);
$r = CBD_Design_Transfer::parse_file('   ');
check('leere Datei abgelehnt', '' !== $r['error'], $r['error']);

echo "\n== Markdown: JSON-Export -> Markdown-Export -> Import ==\n";
$json_designs = CBD_Design_Transfer::parse_payload(payload(array(
    array(
        'name' => 'quelle', 'title' => 'Quelle', 'description' => 'Aus JSON',
        'config' => array('allowInnerBlocks' => false),
        'styles' => array('padding' => array('top' => 20, 'left' => 20)),
        'features' => array('numbering' => array('enabled' => true, 'countingMode' => 'same-design')),
        'status' => 'active',
    ),
)));
// Wie handle_export(): fuer die Datei werden die Spalten wieder dekodiert.
$for_file = $json_designs['designs'][0];
foreach (array('config', 'styles', 'features') as $field) {
    $for_file[$field] = json_decode($for_file[$field], true);
}
$r = CBD_Design_Transfer::parse_markdown(CBD_Design_Transfer::to_markdown(array($for_file)));
check('Formatwechsel verlustfrei',
    $json_designs['designs'][0]['config'] === $r['designs'][0]['config']
    && $json_designs['designs'][0]['styles'] === $r['designs'][0]['styles']
    && $json_designs['designs'][0]['features'] === $r['designs'][0]['features'],
    array($r['designs'][0]['styles'], $r['designs'][0]['features']));

$fails = $GLOBALS['fails'];
echo "\n" . (0 === $fails ? "ALLE TESTS BESTANDEN\n" : "$fails FEHLER\n");
exit(0 === $fails ? 0 : 1);
