<?php
/**
 * Standalone-Harness für den LaTeX-Parser — ohne WordPress.
 *
 * Geprüft wird:
 *  - Filter-Registrierung (`the_content` auf 11, `render_block` auf 5)
 *  - alle vier Delimiter: $$…$$, [latex]…[/latex], \[…\], $…$, \(…\)
 *  - Display-Formeln sind ein <span>, kein <div> (AP-1.1)
 *  - der Doppelparse-Schutz
 *  - die Folgen der Prioritätsänderung 5 -> 11: klassische Inhalte laufen
 *    jetzt NACH wpautop() und wptexturize() durch den Parser
 *  - das Lade-Gate should_load_katex()
 *  - AP-1.fix2: Code-Blöcke bleiben unangetastet (Blocknamen-Filter und
 *    Maskierung von script/pre/code) und HTML-Entities erreichen KaTeX
 *    aufgelöst
 *  - AP-1.fix5: die vier Befunde aus dem Review AP-1.rev2 — $-Zählung auf dem
 *    maskierten Text (N1), Rücktausch in umgekehrter Reihenfolge (N2),
 *    kollisionsfreie Marken (N3), unmittelbare PCRE-Fehlerauswertung (N4)
 *
 * Aufruf:  php tools/test-latex-parser.php
 *
 * @package ContainerBlockDesigner
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}

define('ABSPATH', '/');

// WP_DEBUG an, damit die Protokollzweige des Parsers überhaupt laufen; die
// Ausgabe von error_log() geht in eine Datei, damit der Harness sie lesen
// kann (gebraucht für N4). Ohne die Umleitung schriebe PHP-CLI nach stderr.
define('WP_DEBUG', true);
$GLOBALS['log_file'] = sys_get_temp_dir() . '/cbd-latex-parser-test.log';
@unlink($GLOBALS['log_file']);
ini_set('error_log', $GLOBALS['log_file']);
// Fatale Fehler dürfen nicht in dieser Datei verschwinden – sonst bricht der
// Harness stillschweigend mitten im Lauf ab.
ini_set('display_errors', 'stderr');

function log_reset() { @unlink($GLOBALS['log_file']); }
function log_read()  {
    return is_file($GLOBALS['log_file']) ? (string) file_get_contents($GLOBALS['log_file']) : '';
}

// --- WordPress-Stubs ------------------------------------------------------

class WP_Post {
    public $ID = 0;
    public $post_content = '';
    public function __construct($id = 0, $content = '') {
        $this->ID = $id;
        $this->post_content = $content;
    }
}

$GLOBALS['hooks'] = array();          // hook => array(array(priority, callback))
$GLOBALS['is_admin'] = false;
$GLOBALS['is_singular'] = false;
$GLOBALS['is_home'] = false;
$GLOBALS['is_archive'] = false;
$GLOBALS['queried_object'] = null;
$GLOBALS['queried_object_id'] = 0;
$GLOBALS['posts_by_id'] = array();
$GLOBALS['global_post'] = null;

function add_action($hook, $cb, $priority = 10, $args = 1) {
    add_filter($hook, $cb, $priority, $args);
}
function add_filter($hook, $cb, $priority = 10, $args = 1) {
    if (!isset($GLOBALS['hooks'][$hook])) {
        $GLOBALS['hooks'][$hook] = array();
    }
    $GLOBALS['hooks'][$hook][] = array('priority' => $priority, 'cb' => $cb);
    return true;
}
function __($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s)  { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s)   { return (string) $s; }
function is_admin()    { return (bool) $GLOBALS['is_admin']; }
function is_singular($t = '') { return (bool) $GLOBALS['is_singular']; }
function is_home()     { return (bool) $GLOBALS['is_home']; }
function is_archive()  { return (bool) $GLOBALS['is_archive']; }
function get_queried_object()    { return $GLOBALS['queried_object']; }
function get_queried_object_id() { return (int) $GLOBALS['queried_object_id']; }
function get_post($id = null) {
    if (null === $id) {
        return $GLOBALS['global_post'];
    }
    $id = (int) $id;
    return isset($GLOBALS['posts_by_id'][$id]) ? $GLOBALS['posts_by_id'][$id] : null;
}

// --- Kern-Filter auf `the_content` (Reihenfolge wie in WordPress) ---------
// In WordPress registriert default-filters.php diese VOR jedem Plugin.
add_filter('the_content', 'stub_do_blocks', 9);
add_filter('the_content', 'stub_wptexturize', 10);
add_filter('the_content', 'stub_wpautop', 10);
add_filter('the_content', 'stub_do_shortcode', 11);

/**
 * do_blocks() – hier bewusst als Durchreiche. Der ungünstigste Fall für
 * diesen Test: Blockinhalt wird separat über parse_latex_in_blocks() geführt,
 * klassischer Inhalt kommt unverändert bei Priorität 11 an.
 */
function stub_do_blocks($c) { return $c; }
function stub_do_shortcode($c) { return $c; }

/**
 * Naive, aber tag-bewusste Nachbildung von wptexturize(): Text zwischen den
 * Tags wird typografisch ersetzt, Attributwerte bleiben unberührt — genau
 * wie im Original.
 */
function stub_wptexturize($content) {
    $parts = preg_split('/(<[^>]*>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $part) {
        if ($part !== '' && $part[0] === '<') {
            $out .= $part;
            continue;
        }
        $part = str_replace('---', '&#8212;', $part);
        $part = str_replace('--', '&#8211;', $part);
        $part = str_replace('...', '&#8230;', $part);
        $part = str_replace("'", '&#8217;', $part);
        $part = str_replace('"', '&#8221;', $part);
        $part = preg_replace('/(?<=\d)x(?=\d)/', '&#215;', $part);
        $out .= $part;
    }
    return $out;
}

/** Nachbildung von wpautop() für unformatierten Text. */
function stub_wpautop($content) {
    if (strpos($content, '<p>') !== false) {
        return $content; // bereits abgesetzt
    }
    $parts = preg_split('/\n\s*\n/', trim($content));
    $out = '';
    foreach ($parts as $p) {
        $out .= '<p>' . str_replace("\n", "<br />\n", $p) . "</p>\n";
    }
    return $out;
}

/** Minimaler Ersatz für apply_filters('the_content', …). */
function run_the_content($content) {
    $entries = $GLOBALS['hooks']['the_content'];
    // stabil nach Priorität sortieren (Registrierungsreihenfolge erhalten)
    $indexed = array();
    foreach ($entries as $i => $e) {
        $indexed[] = array($e['priority'], $i, $e['cb']);
    }
    usort($indexed, function($a, $b) {
        if ($a[0] === $b[0]) {
            return $a[1] - $b[1];
        }
        return $a[0] < $b[0] ? -1 : 1;
    });
    foreach ($indexed as $e) {
        $content = call_user_func($e[2], $content);
    }
    return $content;
}

require_once str_replace('\\', '/', dirname(__DIR__)) . '/includes/class-latex-parser.php';

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

/** Liest das data-latex-Attribut der n-ten Formel (entschlüsselt). */
function latex_of($html, $n = 0) {
    if (!preg_match_all('/data-latex="([^"]*)"/', $html, $m)) {
        return null;
    }
    return isset($m[1][$n]) ? html_entity_decode($m[1][$n], ENT_QUOTES, 'UTF-8') : null;
}

function formula_count($html) {
    return substr_count($html, 'data-formula-id="');
}

$parser = CBD_LaTeX_Parser::get_instance();

// =========================================================================
echo "== Filter-Registrierung ==\n";

$prio = array();
foreach ($GLOBALS['hooks'] as $hook => $entries) {
    foreach ($entries as $e) {
        if (is_array($e['cb']) && $e['cb'][0] === $parser) {
            $prio[$hook . '::' . $e['cb'][1]] = $e['priority'];
        }
    }
}

check('the_content -> parse_latex auf Prioritaet 11',
    isset($prio['the_content::parse_latex']) && 11 === $prio['the_content::parse_latex'],
    isset($prio['the_content::parse_latex']) ? $prio['the_content::parse_latex'] : null);
check('render_block -> parse_latex_in_blocks auf Prioritaet 5',
    isset($prio['render_block::parse_latex_in_blocks']) && 5 === $prio['render_block::parse_latex_in_blocks'],
    isset($prio['render_block::parse_latex_in_blocks']) ? $prio['render_block::parse_latex_in_blocks'] : null);
check('Prioritaet 11 liegt NACH do_blocks (9), wpautop und wptexturize (10)',
    isset($prio['the_content::parse_latex']) && $prio['the_content::parse_latex'] > 10);

// =========================================================================
echo "\n== Display-Formeln: <span>, kein <div> ==\n";

$cases_display = array(
    'Dollar   $$a+b$$'       => '$$a+b$$',
    'Shortcode [latex]'      => '[latex]a+b[/latex]',
    'Backslash \\[ … \\]'    => '\[a+b\]',
);
foreach ($cases_display as $label => $input) {
    $out = $parser->parse_latex($input);
    check($label . ': enthaelt cbd-latex-display', strpos($out, 'cbd-latex-display') !== false, $out);
    check($label . ': enthaelt KEIN <div', strpos($out, '<div') === false, $out);
    check($label . ': data-latex ist "a+b"', 'a+b' === latex_of($out), latex_of($out));
    check($label . ': genau eine Formel', 1 === formula_count($out), formula_count($out));
}

$out = $parser->parse_latex('$$a+b$$');
check('Markup-Form der Display-Formel',
    (bool) preg_match('/^<span class="cbd-latex-formula cbd-latex-display" id="[^"]+" data-latex="a\+b" data-formula-id="[^"]+"><span class="cbd-latex-content"><\/span><\/span>$/', $out),
    $out);

// =========================================================================
echo "\n== Inline-Formeln ==\n";

$out = $parser->parse_latex('Es gilt $x$ hier.');
check('$x$ -> cbd-latex-inline', strpos($out, 'cbd-latex-inline') !== false, $out);
check('$x$ -> kein <div', strpos($out, '<div') === false, $out);
check('$x$ -> data-latex "x"', 'x' === latex_of($out), latex_of($out));

$out = $parser->parse_latex('Es gilt \(x\) hier.');
check('\\(x\\) -> cbd-latex-inline', strpos($out, 'cbd-latex-inline') !== false, $out);
check('\\(x\\) -> data-latex "x"', 'x' === latex_of($out), latex_of($out));

$out = $parser->parse_latex('Index \(y_1\) im Text.');
check('\\(y_1\\) -> data-latex "y_1"', 'y_1' === latex_of($out), latex_of($out));

$out = $parser->parse_latex('Display \[x^2\] im Text.');
check('\\[x^2\\] -> data-latex "x^2"', 'x^2' === latex_of($out), latex_of($out));
check('\\[x^2\\] -> Display-Klasse', strpos($out, 'cbd-latex-display') !== false, $out);

$out = $parser->parse_latex('Klammer \( x \) mit Leerzeichen.');
check('\\( x \\) wird trotz Leerzeichen erkannt (eindeutiger Delimiter)',
    strpos($out, 'cbd-latex-inline') !== false, $out);
check('\\( x \\) -> data-latex getrimmt "x"', 'x' === latex_of($out), latex_of($out));

$in = 'Kosten: $5 bis $10 pro Stueck.';
check('Whitespace-Regel bei $…$ bleibt: "$5 bis $10" unveraendert',
    $in === $parser->parse_latex($in), $parser->parse_latex($in));

$in = 'Leere Klammer \(\) und \( \) bleiben stehen.';
check('leere \\(\\)/\\( \\) erzeugen keine Formel',
    formula_count($parser->parse_latex($in)) === 0, $parser->parse_latex($in));

$in = 'Leer \(\) danach echt \(x\) Ende.';
$out = $parser->parse_latex($in);
check('leere Klammer verschluckt die folgende Formel nicht',
    1 === formula_count($out) && 'x' === latex_of($out), $out);

$in = 'Leer \[\] danach echt \[y\] Ende.';
$out = $parser->parse_latex($in);
check('leere \\[\\] verschluckt die folgende Display-Formel nicht',
    1 === formula_count($out) && 'y' === latex_of($out), $out);

// =========================================================================
echo "\n== Gemischt, Reihenfolge Display vor Inline ==\n";

$out = $parser->parse_latex('Zuerst \[a^2\] dann \(b\) und $c$ sowie $$d$$.');
check('vier Formeln erkannt', 4 === formula_count($out), formula_count($out));
check('kein <div im Gesamtergebnis', strpos($out, '<div') === false, $out);
check('Display a^2 vorhanden', strpos($out, 'data-latex="a^2"') !== false, $out);
check('Display d vorhanden', strpos($out, 'data-latex="d"') !== false, $out);
check('kein Platzhalter uebrig', strpos($out, '___CBD_') === false, $out);

// =========================================================================
echo "\n== Inhalte ohne Formeln und Doppelparse-Schutz ==\n";

$plain = "<p>Ein Absatz ganz ohne Formeln.</p>\n<p>Noch einer.</p>";
check('Text ohne Formeln bleibt unveraendert', $plain === $parser->parse_latex($plain), $parser->parse_latex($plain));

$rendered = $parser->parse_latex('Formel $x^2$ im Text.');
check('Doppelparse-Schutz: zweiter Durchlauf aendert nichts',
    $rendered === $parser->parse_latex($rendered));
check('Doppelparse-Schutz: weiterhin genau eine Formel',
    1 === formula_count($parser->parse_latex($rendered)));

check('parse_latex(null) bleibt null', null === $parser->parse_latex(null));
check('parse_latex("") bleibt ""', '' === $parser->parse_latex(''));

// =========================================================================
echo "\n== Blockweg (render_block, Prioritaet 5) ==\n";

$block = array('blockName' => 'core/paragraph', 'attrs' => array());

$out = $parser->parse_latex_in_blocks('<p>Formel $E=mc^2$ hier.</p>', $block);
check('Block mit $…$ wird geparst', 1 === formula_count($out), $out);

$out = $parser->parse_latex_in_blocks('<p>Formel \[E=mc^2\] hier.</p>', $block);
check('Block mit \\[…\\] wird NICHT mehr vorzeitig uebersprungen',
    1 === formula_count($out), $out);

$out = $parser->parse_latex_in_blocks('<p>Formel \(E=mc^2\) hier.</p>', $block);
check('Block mit \\(…\\) wird NICHT mehr vorzeitig uebersprungen',
    1 === formula_count($out), $out);

$nomarker = '<p>Ganz normaler Absatz.</p>';
check('Block ohne Marker bleibt zeichengleich', $nomarker === $parser->parse_latex_in_blocks($nomarker, $block));

// =========================================================================
echo "\n== Prioritaetswechsel 5 -> 11: klassischer Inhalt (kein Blockmarkup) ==\n";

$klassisch = "Klassischer Absatz mit \$E=mc^2\$ im Text.\n\nZweiter Absatz mit \\[a^2+b^2=c^2\\] als Blockformel.";
$seite = run_the_content($klassisch);

check('klassisch: zwei Formeln erzeugt', 2 === formula_count($seite), $seite);
check('klassisch: Inline-Formel korrekt', 'E=mc^2' === latex_of($seite, 0), latex_of($seite, 0));
check('klassisch: Display-Formel korrekt', 'a^2+b^2=c^2' === latex_of($seite, 1), latex_of($seite, 1));
check('klassisch: kein <div im Ergebnis', strpos($seite, '<div') === false, $seite);
check('klassisch: Display-Formel liegt IM Absatz (kein zerrissener <p>)',
    (bool) preg_match('/<p>[^<]*<span class="cbd-latex-formula cbd-latex-display"/', $seite), $seite);
check('klassisch: kein Platzhalter uebrig', strpos($seite, '___CBD_') === false, $seite);

// Formel ueber mehrere Zeilen: wpautop setzt <br /> hinein
$mehrzeilig = "Vorher.\n\n\$\$\na + b\n\$\$\n\nNachher.";
$seite = run_the_content($mehrzeilig);
check('mehrzeilige $$…$$: <br /> aus wpautop wird entfernt',
    'a + b' === latex_of($seite), latex_of($seite));

// wptexturize haette die Formel zerstoert
$texturiert = "Ableitung \$f'(x) = 2x\$ und Produkt \$3x3\$ und Bereich \$a--b\$.";
$seite = run_the_content($texturiert);
check('wptexturize: Apostroph wiederhergestellt', "f'(x) = 2x" === latex_of($seite, 0), latex_of($seite, 0));
check('wptexturize: 3x3 wiederhergestellt', '3x3' === latex_of($seite, 1), latex_of($seite, 1));
check('wptexturize: Doppel-Minus wiederhergestellt', 'a--b' === latex_of($seite, 2), latex_of($seite, 2));

// =========================================================================
echo "\n== Prioritaetswechsel 5 -> 11: Blockinhalt laeuft nicht doppelt ==\n";

$block_html = '<p>Formel $E=mc^2$ und \[a^2\] im Block.</p>';
$nach_render_block = $parser->parse_latex_in_blocks($block_html, $block);
check('render_block hat beide Formeln erzeugt', 2 === formula_count($nach_render_block), $nach_render_block);

$vorher = $nach_render_block;
$nachher = $parser->parse_latex($nach_render_block);
check('the_content(11) laesst bereits gerenderten Blockinhalt zeichengleich',
    $vorher === $nachher);

$seite = run_the_content($nach_render_block);
check('Gesamtkette: weiterhin genau zwei Formeln (keine Doppelrendrung)',
    2 === formula_count($seite), formula_count($seite));
check('Gesamtkette: data-latex unveraendert', 'E=mc^2' === latex_of($seite, 0), latex_of($seite, 0));

// =========================================================================
echo "\n== Code-Bloecke und Entities (AP-1.fix2) ==\n";

// --- M1, Ebene 1: Blocktypen, in denen \( und \[ keine Formeln sind -------
// Regression aus AP-1.1: Seit die Delimiter \(…\) und \[…\] erkannt werden,
// zerschoss der Parser jede JavaScript-Regex in Custom-HTML-, Code- und
// Preformatted-Bloecken.

$js_inline  = 'var muster = /\(([^)]+)\)/g;';
$js_display = 'var m = /\[[a-z]+\]/i;';

foreach (array('core/html', 'core/code', 'core/preformatted', 'core/freeform') as $blockname) {
    $b = array('blockName' => $blockname, 'attrs' => array());

    $out = $parser->parse_latex_in_blocks($js_inline, $b);
    check($blockname . ': Regex mit \\(…\\) bleibt zeichengleich', $js_inline === $out, $out);
    check($blockname . ': keine Formelklasse eingefuegt',
        strpos($out, 'cbd-latex-formula') === false, $out);

    $out = $parser->parse_latex_in_blocks($js_display, $b);
    check($blockname . ': Regex mit \\[…\\] bleibt zeichengleich', $js_display === $out, $out);
}

$html_block  = array('blockName' => 'core/html', 'attrs' => array());
$script_html = '<script>var m = /\(([^)]+)\)/g; console.log(m);</script>';
$out = $parser->parse_latex_in_blocks($script_html, $html_block);
check('core/html mit vollstaendigem Skript bleibt zeichengleich', $script_html === $out, $out);

// Blockname null = Inhalt ohne Blockmarkup (Freiform im klassischen Editor).
// Der darf NICHT uebersprungen werden, sonst verlieren klassische Inhalte
// ihre Formeln.
$out = $parser->parse_latex_in_blocks('<p>Formel $E=mc^2$ hier.</p>',
    array('blockName' => null, 'attrs' => array()));
check('blockName null wird NICHT uebersprungen', 1 === formula_count($out), $out);

$out = $parser->parse_latex_in_blocks('<p>Formel \(x^2\) hier.</p>',
    array('blockName' => 'core/paragraph', 'attrs' => array()));
check('core/paragraph parst \\(…\\) weiterhin', 1 === formula_count($out), $out);

// Klassischer Inhalt kommt nicht ueber render_block, sondern ueber
// the_content(11) — dort greift der Blocknamen-Filter bewusst nicht.
$seite = run_the_content("Klassisch mit \$a^2\$ im Text.");
check('klassischer Inhalt ueber the_content(11) parst weiterhin',
    1 === formula_count($seite), $seite);

// --- M1, Ebene 2: script/pre/code auch im gewoehnlichen Absatz ------------
// Ein Skript kann auch in einem Absatz oder in einem Container-Block stehen;
// dort greift der Blocknamen-Filter nicht.

$mit_formel = '<p>Text mit ' . $script_html . ' und Formel \(x^2\) dazu.</p>';
$out = $parser->parse_latex($mit_formel);
check('Skript im Absatz bleibt zeichengleich', strpos($out, $script_html) !== false, $out);
check('Formel neben dem Skript wird gesetzt', 1 === formula_count($out), $out);
check('Formel neben dem Skript ist "x^2"', 'x^2' === latex_of($out), latex_of($out));
check('kein Platzhalter uebrig (Skript)', strpos($out, '___CBD_') === false, $out);

$code_tag = '<code>\(x\)</code>';
$out = $parser->parse_latex($code_tag);
check('<code> bleibt zeichengleich', $code_tag === $out, $out);

$pre_tag = '<pre>\[y\]</pre>';
$out = $parser->parse_latex($pre_tag);
check('<pre> bleibt zeichengleich', $pre_tag === $out, $out);

$pre_code = '<pre><code>\(x\)</code></pre>';
$out = $parser->parse_latex($pre_code);
check('<pre><code> verschachtelt bleibt zeichengleich', $pre_code === $out, $out);

$gemischt = '<p>Siehe ' . $code_tag . ' und $y$ hier.</p>';
$out = $parser->parse_latex($gemischt);
check('<code> daneben bleibt zeichengleich', strpos($out, $code_tag) !== false, $out);
check('Formel neben <code> wird gesetzt',
    1 === formula_count($out) && 'y' === latex_of($out), $out);

$script_attr = '<SCRIPT type="text/javascript">var r = /\[a\]/;</SCRIPT>';
$out = $parser->parse_latex($script_attr);
check('Skript mit Attributen und Grossschreibung bleibt zeichengleich',
    $script_attr === $out, $out);

$code_attr = '<code class="language-js">if (a &lt; b) { m = /\(x\)/; }</code>';
$out = $parser->parse_latex($code_attr);
check('<code> mit Attributen bleibt zeichengleich', $code_attr === $out, $out);

// --- M2: HTML-Entities erreichen KaTeX aufgeloest -------------------------
// Der Editor speichert `<`, `>` und `&` in Absaetzen immer als Entity.
// Unaufgeloest sind \begin{aligned}…&=…, array, matrix und jeder Vergleich
// a < b in Formeln unbenutzbar.

$out = $parser->parse_latex('Bedingung $a &lt; b$ gilt.');
check('$a &lt; b$ -> data-latex "a < b"', 'a < b' === latex_of($out), latex_of($out));
check('$a &lt; b$ -> kein & im Formeltext',
    strpos((string) latex_of($out), '&') === false, latex_of($out));

$out = $parser->parse_latex('Produkt $x &amp; y$ hier.');
check('$x &amp; y$ -> data-latex "x & y"', 'x & y' === latex_of($out), latex_of($out));

$out = $parser->parse_latex('Wort $\text{caf&eacute;}$ hier.');
check('&eacute; wird aufgeloest', '\text{café}' === latex_of($out), latex_of($out));

$out = $parser->parse_latex('$$\begin{aligned} a &amp;= b \end{aligned}$$');
check('&amp; in \\begin{aligned} wird zum Zeichen',
    '\begin{aligned} a &= b \end{aligned}' === latex_of($out), latex_of($out));

// Reihenfolge festgenagelt: die wptexturize-Tabelle arbeitet auf der
// Entity-Schreibweise und muss VOR dem Dekodieren greifen — sonst stuende
// dort das typografische Zeichen und die Ableitung f'(x) bekaeme ein U+2019.
$seite = run_the_content("Ableitung \$f'(x) &lt; 2\$ hier.");
check('Texturierung und Entity zusammen -> "f\'(x) < 2"',
    "f'(x) < 2" === latex_of($seite), latex_of($seite));

// Gegenprobe: eine echte Formel ohne Entities bleibt unveraendert.
$out = $parser->parse_latex('Bruch $\frac{1}{2}$ hier.');
check('Formel ohne Entities unveraendert', '\frac{1}{2}' === latex_of($out), latex_of($out));

$out = $parser->parse_latex('Menge $a \cap b$ hier.');
check('Backslash-Makro unveraendert', 'a \cap b' === latex_of($out), latex_of($out));

// =========================================================================
echo "\n== Haertung AP-1.fix5 ==\n";

// --- N1: die $-Zaehlung laeuft auf dem maskierten Text --------------------
// jQuery mit $ ist im Projekt gelebtes Muster (dafuer existiert eigens
// CBD_Block_Registration::isolate_inline_scripts()), und Container-Bloecke
// stehen nicht in KEIN_LATEX_BLOCK. Vorher zaehlte parse_latex_in_blocks()
// die $ im ROHTEXT: drei $ im Skript = ungerade -> rote Warnbox plus rot
// hinterlegte <span> mitten ins Skript, das Skript war zerstoert.

$container_block = array('blockName' => 'container-block-designer/container', 'attrs' => array());
$jquery = '<script>jQuery(function($){ var a = $(".x"); var b = $(".y"); });</script>';

$in  = '<p>Hinweis dazu.</p>' . $jquery;
$out = $parser->parse_latex_in_blocks($in, $container_block);
check('N1: jQuery-Skript mit ungerader $-Zahl bleibt zeichengleich', $in === $out, $out);
check('N1: keine Warnbox wegen der $ im Skript',
    strpos($out, 'cbd-latex-warning') === false, $out);
check('N1: keine rot hinterlegten <span> im Skript',
    strpos($out, 'background: #dc3545') === false, $out);

$in  = '<p>Formel $x^2$ dazu.</p>' . $jquery;
$out = $parser->parse_latex_in_blocks($in, $container_block);
check('N1: Formel neben dem Skript wird gesetzt', 1 === formula_count($out), $out);
check('N1: data-latex ist "x^2"', 'x^2' === latex_of($out), latex_of($out));
check('N1: Skript daneben bleibt unversehrt', strpos($out, $jquery) !== false, $out);
check('N1: keine Warnbox trotz fuenf $ im Rohtext',
    strpos($out, 'cbd-latex-warning') === false, $out);

// GEAENDERT am 2026-08-21: Hier stand die Forderung, eine ungerade Zahl von $
// muesse eine Warnbox erzeugen. Diese Regel ist auf Wunsch des Nutzers
// entfallen - ein einzelnes $ im Fliesstext ("Das kostet 65$") ist normal und
// darf keine Fehlermeldung ausloesen. Geprueft wird jetzt das Gegenteil.
$in  = '<p>Preis $5 und $ noch $ ein Zeichen.</p>';
$out = $parser->parse_latex_in_blocks($in, $container_block);
check('ungerade $ ausserhalb von Skripten -> KEINE Warnbox mehr',
    strpos($out, 'cbd-latex-warning') === false, $out);
check('ungerade $ ausserhalb von Skripten -> keine roten <span>',
    strpos($out, 'background: #dc3545') === false, $out);
check('ungerade $ ausserhalb von Skripten -> Text bleibt zeichengleich',
    $in === $out, $out);

// Ein Skript mit $ daneben aendert daran nichts.
$dollar_skript = '<script>var s = "$";</script>';
$in  = '<p>Preis $5 und $ noch $ ein.</p>' . $dollar_skript;
$out = $parser->parse_latex_in_blocks($in, $container_block);
check('Skript mit $ daneben: keine Warnbox',
    strpos($out, 'cbd-latex-warning') === false, $out);
check('Skript bleibt zeichengleich',
    strpos($out, $dollar_skript) !== false, $out);

// --- N2: zurueckgetauscht wird in umgekehrter Reihenfolge -----------------
// Die geschuetzten Bereiche liegen im Speicher VOR den Formeln. Wurden sie
// zuerst zurueckgetauscht, lief ihr wiederhergestellter Inhalt anschliessend
// noch durch die Formel-Ersetzungen.

$restore = new ReflectionMethod('CBD_LaTeX_Parser', 'restore_placeholders');
$restore->setAccessible(true);
$store = array(
    // Reihenfolge wie im echten Ablauf: erst geschuetzter Bereich, dann Formel
    '___CBD_PROTECTED_probe_0___'       => '<code>___CBD_DISPLAY_FORMULA_probe_0___</code>',
    '___CBD_DISPLAY_FORMULA_probe_0___' => '<span class="probe-formel"></span>',
);
$out = $restore->invoke(
    $parser,
    'A ___CBD_PROTECTED_probe_0___ B ___CBD_DISPLAY_FORMULA_probe_0___ C',
    $store
);
check('N2: Formeln werden vor den geschuetzten Bereichen zurueckgetauscht',
    'A <code>___CBD_DISPLAY_FORMULA_probe_0___</code> B <span class="probe-formel"></span> C' === $out,
    $out);

// Der im Review gemessene Fall, Ende zu Ende.
$in  = '<p>Beispiel <code>___CBD_DISPLAY_FORMULA_0___</code> und $$q$$ dazu.</p>';
$out = $parser->parse_latex($in);
check('N2: Code-Inhalt wird nicht durch die Formel ersetzt',
    strpos($out, '<code>___CBD_DISPLAY_FORMULA_0___</code>') !== false, $out);
check('N2: dabei entsteht genau eine Formel', 1 === formula_count($out), $out);

// --- N3: die Marken sind je Aufruf zufaellig ------------------------------
// Ein Nutzertext, der eine Marke enthaelt, wurde vorher durch den
// Skriptinhalt bzw. die Formel ersetzt — das Skript stand danach doppelt.

$skript = '<script>var a = 1;</script>';
$in  = '<p>Text mit ___CBD_PROTECTED_0___ als Beispiel.</p>' . $skript;
$out = $parser->parse_latex($in);
check('N3: Nutzertext mit ___CBD_PROTECTED_0___ bleibt zeichengleich', $in === $out, $out);
check('N3: das Skript steht genau einmal', 1 === substr_count($out, $skript), $out);

$in  = '<p>Marke ___CBD_INLINE_FORMULA_0___ und \(z\) dazu.</p>';
$out = $parser->parse_latex($in);
check('N3: Inline-Marke im Nutzertext ueberlebt',
    strpos($out, '___CBD_INLINE_FORMULA_0___') !== false, $out);
check('N3: dabei entsteht genau eine Inline-Formel', 1 === formula_count($out), $out);

// Der Doppelparse-Schutz prueft die AUSGABE (cbd-latex-formula), nicht die
// Marken — er darf von der Zufallsmarke nicht betroffen sein.
$rendered_n3 = $parser->parse_latex('Formel $p^2$ im Text.');
check('N3: Doppelparse-Schutz greift weiterhin',
    $rendered_n3 === $parser->parse_latex($rendered_n3), $parser->parse_latex($rendered_n3));

// --- N4: PCRE-Fehler wird unmittelbar ausgewertet -------------------------
// preg_last_error() wird von jedem nachfolgenden erfolgreichen preg_*-Aufruf
// auf PREG_NO_ERROR zurueckgesetzt. Eine Auswertung "unten" erreicht den
// Fehler aus mask_protected_regions() deshalb nie.

$maskiere = Closure::bind(function ($text) {
    $speicher = array();
    $zaehler  = 0;
    return $this->mask_protected_regions($text, $speicher, $zaehler, 'probe');
}, $parser, 'CBD_LaTeX_Parser');

$alt_limit = ini_get('pcre.backtrack_limit');
ini_set('pcre.backtrack_limit', '1000');
$boese = '<script>' . str_repeat('a', 100000); // ohne schliessendes Tag
log_reset();
$ergebnis = $maskiere($boese);
$protokoll = log_read();
ini_set('pcre.backtrack_limit', $alt_limit);

check('N4: bei PCRE-Fehler bleibt der Inhalt unveraendert', $boese === $ergebnis);
check('N4: der PCRE-Fehler wird protokolliert',
    strpos($protokoll, 'PREG') !== false, $protokoll);
check('N4: die Meldung nennt die Fundstelle mask_protected_regions',
    strpos($protokoll, 'mask_protected_regions') !== false, $protokoll);

// Fehlercode wieder sauber machen, damit die Robustheitspruefung am Ende
// nicht auf diesem provozierten Fehler sitzenbleibt.
preg_match('/a/', 'a');

// =========================================================================
echo "\n== content_has_latex_markers() ==\n";

$marker_cases = array(
    'Text mit $x$'                => true,
    'Text mit [latex]x[/latex]'   => true,
    'Text mit \(x\)'              => true,
    'Text mit \[x\]'              => true,
    // "blockquote" darf die Heuristik fuer wiederverwendbare Bloecke nicht
    // ausloesen: gesucht wird "<!-- wp:block " MIT Leerzeichen.
    '<!-- wp:blockquote -->Zitat'  => false,
    '<!-- wp:block {"ref":7} -->' => true,
    'Ganz normaler Text.'         => false,
    ''                            => false,
);
foreach ($marker_cases as $in => $expected) {
    $actual = CBD_LaTeX_Parser::content_has_latex_markers($in);
    check(var_export($in, true) . ' -> ' . var_export($expected, true), $expected === $actual, $actual);
}
check('nicht-String -> false', false === CBD_LaTeX_Parser::content_has_latex_markers(null));

// =========================================================================
echo "\n== should_load_katex() ==\n";

$gate = new ReflectionMethod('CBD_LaTeX_Parser', 'should_load_katex');
$gate->setAccessible(true);
function gate_says($parser, $gate) { return (bool) $gate->invoke($parser); }

function reset_query_state() {
    $GLOBALS['is_admin'] = false;
    $GLOBALS['is_singular'] = false;
    $GLOBALS['is_home'] = false;
    $GLOBALS['is_archive'] = false;
    $GLOBALS['queried_object'] = null;
    $GLOBALS['queried_object_id'] = 0;
    $GLOBALS['posts_by_id'] = array();
    $GLOBALS['global_post'] = null;
}

// 1) Einzelseite mit \[ im Inhalt -> laden (frueher: nur $ / [latex])
reset_query_state();
$post = new WP_Post(42, 'Inhalt mit \[a^2\] Formel.');
$GLOBALS['is_singular'] = true;
$GLOBALS['queried_object'] = $post;
$GLOBALS['queried_object_id'] = 42;
$GLOBALS['posts_by_id'][42] = $post;
check('Einzelseite mit \\[ -> laden', gate_says($parser, $gate));

// 2) Einzelseite ohne Marker -> nicht laden
reset_query_state();
$post = new WP_Post(43, 'Ein Text ganz ohne Formeln.');
$GLOBALS['is_singular'] = true;
$GLOBALS['queried_object'] = $post;
$GLOBALS['queried_object_id'] = 43;
$GLOBALS['posts_by_id'][43] = $post;
check('Einzelseite ohne Marker -> nicht laden', !gate_says($parser, $gate));

// 3) get_queried_object_id() wird genutzt, auch wenn das globale $post
//    (noch) auf einen anderen Beitrag zeigt — das ist der Kern der Haertung.
reset_query_state();
$richtig = new WP_Post(44, 'Inhalt mit $x$ Formel.');
$falsch  = new WP_Post(99, 'Ganz ohne Formeln.');
$GLOBALS['is_singular'] = true;
$GLOBALS['queried_object'] = $richtig;
$GLOBALS['queried_object_id'] = 44;
$GLOBALS['posts_by_id'][44] = $richtig;
$GLOBALS['global_post'] = $falsch;
check('abgefragter Beitrag schlaegt globales $post', gate_says($parser, $gate));

// 4) Rueckfall auf get_post(), wenn get_queried_object_id() nichts liefert
reset_query_state();
$GLOBALS['is_singular'] = true;
$GLOBALS['global_post'] = new WP_Post(45, 'Inhalt mit $y$ Formel.');
check('Rueckfall auf globales $post', gate_says($parser, $gate));

// 5) Startseite / Archiv -> laden (frueher: nie, wegen is_singular()-Gate)
reset_query_state();
$GLOBALS['is_home'] = true;
check('Startseite -> laden', gate_says($parser, $gate));

reset_query_state();
$GLOBALS['is_archive'] = true;
check('Archiv -> laden', gate_says($parser, $gate));

// 6) Term-Archiv: get_queried_object() ist kein WP_Post -> Term-ID darf nicht
//    als Beitrags-ID missverstanden werden.
reset_query_state();
$GLOBALS['is_archive'] = false;
$GLOBALS['queried_object'] = (object) array('term_id' => 7);
$GLOBALS['queried_object_id'] = 7;
$GLOBALS['posts_by_id'][7] = new WP_Post(7, 'Fremder Beitrag mit $z$.');
check('Term-ID wird nicht als Beitrags-ID gelesen', !gate_says($parser, $gate));

// 7) Nichts ermittelbar, keine Einzelseite -> nicht laden
reset_query_state();
check('kein Kontext -> nicht laden', !gate_says($parser, $gate));

// =========================================================================
echo "\n== Robustheit ==\n";

$lang = 'Text ' . str_repeat('a', 200) . ' $x$ Ende.';
check('langer Text mit Formel wird verarbeitet', 1 === formula_count($parser->parse_latex($lang)));

$unbalanced = '<p>Preis $5 und $ noch $ ein Zeichen</p>'; // drei $ = ungerade
$out = $parser->parse_latex_in_blocks($unbalanced, $block);
check('ungerade Anzahl $ -> keine Warnung, keine Formel',
    strpos($out, 'cbd-latex-warning') === false && 0 === formula_count($out), $out);

// =========================================================================
echo "\n== Leerzeichenregel fuer \$...\$ (2026-08-21) ==\n";
//
// Der Parser unterscheidet eine Formel von Text mit Dollarzeichen allein
// daran, ob direkt hinter dem oeffnenden und direkt vor dem schliessenden $
// Leerraum steht. Kein Zaehlen, keine Heuristik.

$faelle = array(
    // Text                          Formeln  Beschreibung
    array('$E=mc^2$',                     1, 'ohne Leerzeichen -> Formel'),
    array('$x$',                          1, 'einzelnes Zeichen -> Formel'),
    array('$H_2O$ und $CO_2$',            2, 'zwei Formeln nebeneinander'),
    array('$Testformel $',                0, 'Leerzeichen VOR dem Schluss-$ -> keine Formel'),
    array('$ Testformel$',                0, 'Leerzeichen HINTER dem Start-$ -> keine Formel'),
    array('$ Testformel $',               0, 'Leerzeichen auf beiden Seiten -> keine Formel'),
    array('Das kostet 65$',               0, 'einzelnes $ am Wortende -> keine Formel'),
    array('65$ und dann 30$',             0, 'zwei Preise -> keine Formel dazwischen'),
    array('Zwischen 5$ und 10$ liegen',   0, 'Preisspanne bleibt Text'),
    array("Zeilen\n\$a+b\$ Ende",         1, 'Formel nach Zeilenumbruch'),
    array('$a$b$c$',                      2, 'Kette ohne Leerzeichen'),
);
foreach ($faelle as $fall) {
    list($text, $erwartet, $was) = $fall;
    $erg = $parser->parse_latex($text);
    check('Leerzeichenregel: ' . $was, $erwartet === formula_count($erg), $erg);
}

// Der Text um eine nicht erkannte Formel darf sich nicht veraendern.
$roh = '<p>Das kostet 65$ und mehr.</p>';
check('Text mit einzelnem $ bleibt zeichengleich', $roh === $parser->parse_latex($roh), $parser->parse_latex($roh));

$roh = '<p>Ein $Testformel $ Rest.</p>';
check('Text mit Leerzeichen-Formel bleibt zeichengleich', $roh === $parser->parse_latex($roh), $parser->parse_latex($roh));

// =========================================================================
echo "\n== Bereits gerenderte Formeln werden maskiert, nicht abgebrochen ==\n";
//
// Frueher gab parse_latex() beim ersten Fund von cbd-latex-formula den GANZEN
// Inhalt unveraendert zurueck. Bei verschachtelten Bloecken feuert
// render_block fuer den inneren Block zuerst; der aeussere Container sah
// dann schon fertige Spans und gab auf, bevor er seinen eigenen Blocktitel
// ansehen konnte. Formeln im Titel blieben Text - aber nur bei Bloecken,
// deren Inhalt ebenfalls eine Formel enthielt.

$fertig = $parser->parse_latex('<p>$H_2O$</p>');
check('Vorbereitung: eine Formel gerendert', 1 === formula_count($fertig), $fertig);

$gemischt = '<h3>Titel $E^3$ dazu</h3>' . $fertig;
$erg = $parser->parse_latex($gemischt);
check('gerenderte Formel plus unbearbeitete: beide vorhanden',
    2 === formula_count($erg), $erg);
check('die fertige Formel bleibt zeichengleich enthalten',
    strpos($erg, $fertig) !== false, $erg);
check('kein Platzhalter bleibt uebrig',
    strpos($erg, '___CBD_PROTECTED_') === false, $erg);

$nur_fertig = $parser->parse_latex($fertig);
check('rein gerenderter Inhalt kommt zeichengleich heraus',
    $nur_fertig === $fertig, $nur_fertig);

// Eine Formel innerhalb eines <script> bleibt trotzdem unberuehrt - die
// Maskierung der Tags laeuft vor der der Formeln.
$mit_skript = '<script>var s = "$x$";</script><p>$y$</p>';
$erg = $parser->parse_latex($mit_skript);
check('Formel im Skript bleibt Text, die daneben wird gesetzt',
    1 === formula_count($erg) && strpos($erg, 'var s = "$x$";') !== false, $erg);

check('preg_last_error() ist sauber', PREG_NO_ERROR === preg_last_error(), preg_last_error());

// =========================================================================
echo "\n";
if ($GLOBALS['fails'] > 0) {
    echo "FEHLGESCHLAGEN: {$GLOBALS['fails']} Pruefung(en).\n";
    exit(1);
}
echo "Alle Pruefungen bestanden.\n";
exit(0);
