<?php
/**
 * Testharness für CBD_Block_Serializer
 *
 * Läuft headless, ohne WordPress:
 *   php tools/test-block-serializer.php
 *
 * Muster wie tools/test-design-transfer.php und tools/test-icon-value.php:
 * die zu testende Klassendatei direkt einbinden, die benötigten
 * WordPress-Funktionen stubben, am Ende „N Prüfungen, M Fehler" ausgeben und
 * mit Exit-Code 1 abbrechen, wenn etwas fehlschlägt.
 *
 * Die Testfälle sind in vier Gruppen geordnet:
 *   A  Fragmentebene   – html_to_blocks()           (T1–T11, T23)
 *   B  Dokumentebene   – to_block_array()           (T12–T22)
 *   C  Markup-Treue    – Vergleich gegen die Fixture aus AP-1.2
 *   D  Delimiter-Bilanz – Kommentar-Trenner ausgeglichen und verschachtelt
 *
 * @package ContainerBlockDesigner
 */

// ---------------------------------------------------------------------------
// WordPress-Stubs
// ---------------------------------------------------------------------------

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return $url; }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) { return true; }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

// ---------------------------------------------------------------------------
// Serialisierung: wörtliche Kopien aus wp-includes/blocks.php (WordPress 7.0.3)
// — nicht anpassen. Sie sind reine Zeichenkettenverarbeitung ohne weitere
// WordPress-Abhängigkeiten. Die endgültige Absicherung der Markup-Treue
// leistet nicht dieser Stub, sondern Testgruppe C gegen die echte Fixture.
// ---------------------------------------------------------------------------

if (!function_exists('strip_core_block_namespace')) {
    // Original nutzt str_starts_with (PHP 8.0+); hier 7.4-verträglich.
    function strip_core_block_namespace($block_name = null) {
        if (is_string($block_name) && strpos($block_name, 'core/') === 0) {
            return substr($block_name, 5);
        }
        return $block_name;
    }
}

if (!function_exists('serialize_block_attributes')) {
    function serialize_block_attributes($block_attributes) {
        $encoded_attributes = wp_json_encode($block_attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return strtr(
            $encoded_attributes,
            array(
                '\\\\' => '\\u005c',
                '--'   => '\\u002d\\u002d',
                '<'    => '\\u003c',
                '>'    => '\\u003e',
                '&'    => '\\u0026',
                '\\"'  => '\\u0022',
            )
        );
    }
}

if (!function_exists('get_comment_delimited_block_content')) {
    function get_comment_delimited_block_content($block_name, $block_attributes, $block_content) {
        if (is_null($block_name)) {
            return $block_content;
        }

        $serialized_block_name = strip_core_block_namespace($block_name);
        $serialized_attributes = empty($block_attributes) ? '' : serialize_block_attributes($block_attributes) . ' ';

        if (empty($block_content)) {
            return sprintf('<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes);
        }

        return sprintf(
            '<!-- wp:%s %s-->%s<!-- /wp:%s -->',
            $serialized_block_name,
            $serialized_attributes,
            $block_content,
            $serialized_block_name
        );
    }
}

if (!function_exists('serialize_block')) {
    function serialize_block($block) {
        $block_content = '';

        $index = 0;
        foreach ($block['innerContent'] as $chunk) {
            $block_content .= is_string($chunk) ? $chunk : serialize_block($block['innerBlocks'][$index++]);
        }

        if (!is_array($block['attrs'])) {
            $block['attrs'] = array();
        }

        return get_comment_delimited_block_content(
            $block['blockName'],
            $block['attrs'],
            $block_content
        );
    }
}

if (!function_exists('serialize_blocks')) {
    function serialize_blocks($blocks) {
        return implode('', array_map('serialize_block', $blocks));
    }
}

// ---------------------------------------------------------------------------
// Prüfling
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../includes/class-cbd-block-serializer.php';

// ---------------------------------------------------------------------------
// Testgerüst
// ---------------------------------------------------------------------------

$GLOBALS['pruefungen'] = 0;
$GLOBALS['fehler'] = 0;
$GLOBALS['uebersprungen'] = 0;

function pruefe($bezeichnung, $erwartet, $tatsaechlich) {
    $GLOBALS['pruefungen']++;
    if ($erwartet === $tatsaechlich) {
        echo "  OK      $bezeichnung\n";
        return true;
    }
    $GLOBALS['fehler']++;
    echo "  FEHLER  $bezeichnung\n";
    echo "          erwartet:    " . kurz(var_export($erwartet, true)) . "\n";
    echo "          tatsächlich: " . kurz(var_export($tatsaechlich, true)) . "\n";
    return false;
}

function pruefe_wahr($bezeichnung, $bedingung, $zusatz = '') {
    return pruefe($bezeichnung . ($zusatz !== '' ? " ($zusatz)" : ''), true, (bool) $bedingung);
}

function ueberspringe($bezeichnung, $grund) {
    $GLOBALS['uebersprungen']++;
    echo "  ÜBERSPR $bezeichnung — $grund\n";
}

function kurz($text, $max = 400) {
    $text = preg_replace('/\s+/', ' ', (string) $text);
    return strlen($text) > $max ? substr($text, 0, $max) . ' …' : $text;
}

function abschnitt($titel) {
    echo "\n" . $titel . "\n" . str_repeat('-', strlen($titel)) . "\n";
}

/** Blocknamen eines Baums flach einsammeln (Reihenfolge = Dokumentreihenfolge). */
function namen($bloecke) {
    $liste = array();
    foreach ($bloecke as $b) {
        $liste[] = $b['blockName'];
    }
    return $liste;
}

/** Rekursiv alle Blöcke eines bestimmten Typs finden. */
function finde_alle($bloecke, $name) {
    $treffer = array();
    foreach ($bloecke as $b) {
        if ($b['blockName'] === $name) {
            $treffer[] = $b;
        }
        if (!empty($b['innerBlocks'])) {
            $treffer = array_merge($treffer, finde_alle($b['innerBlocks'], $name));
        }
    }
    return $treffer;
}

/** Baut einen Abschnitt, wie parse_markdown_content() ihn liefert. */
function abschnitt_daten($blockTitle, $content, $groupKey, $groupLabel = null, $titleSource = 'h3', $competence = 'other') {
    return array(
        'topic'                 => '',
        'competence'            => $competence,
        'blockTitle'            => $blockTitle,
        'groupKey'              => $groupKey,
        'groupLabel'            => $groupLabel !== null ? $groupLabel : $groupKey,
        'titleSource'           => $titleSource,
        'hasExplicitCompetence' => false,
        'content'               => $content,
    );
}

/** Baut eine Gruppe, wie parse_markdown_content() sie liefert. */
function gruppe_daten($key, $label, $count = 1, $accordion = null) {
    return array(
        'key'             => $key,
        'label'           => $label,
        'competence'      => 'other',
        'count'           => $count,
        'hasSubheadings'  => true,
        'suggestedStyle'  => null,
        'similarStyle'    => null,
        'matchedBy'       => null,
        'accordion'       => $accordion,
    );
}

/** Accordion-Direktive, wie parse_accordion_directive() sie liefert. */
function accordion_daten($level = 3, $numbering = true, $multiple = false, $openFirst = false, $expandAll = false) {
    return array(
        'enabled'   => true,
        'level'     => $level,
        'numbering' => $numbering,
        'multiple'  => $multiple,
        'openFirst' => $openFirst,
        'expandAll' => $expandAll,
    );
}

/**
 * Feste, vorhersagbare stableId für die Tests. Ohne das wäre die Ausgabe
 * bei jedem Lauf anders und ein Vergleich unmöglich.
 */
function fester_id_erzeuger() {
    $zaehler = 0;
    return function () use (&$zaehler) {
        $zaehler++;
        return 'cbd-testid-' . $zaehler;
    };
}

/** Standardoptionen für die Dokumentebene. */
function optionen($zusatz = array()) {
    return array_merge(array(
        'known_slugs'        => array('infotext_k1', 'uebungen', 'hinweise'),
        'accordion_available' => true,
        'stable_id_factory'  => fester_id_erzeuger(),
    ), $zusatz);
}

/**
 * Prüft, ob die Kommentar-Trenner ausgeglichen und korrekt verschachtelt
 * sind (Stapelprinzip). Gibt true zurück oder eine Fehlerbeschreibung.
 */
function pruefe_delimiter_bilanz($markup) {
    $stapel = array();
    $muster = '/<!--\s*(\/?)wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)(.*?)(\/?)-->/s';
    if (!preg_match_all($muster, $markup, $treffer, PREG_SET_ORDER)) {
        return 'keine Trenner gefunden';
    }
    foreach ($treffer as $t) {
        $ist_schliessend = ($t[1] === '/');
        $name = $t[2];
        $selbstschliessend = (substr(rtrim($t[3]), -1) === '/') || ($t[4] === '/');

        if ($ist_schliessend) {
            $oben = array_pop($stapel);
            if ($oben === null) {
                return "schließender Trenner ohne öffnenden: $name";
            }
            if ($oben !== $name) {
                return "falsche Verschachtelung: erwartet /$oben, gefunden /$name";
            }
        } elseif (!$selbstschliessend) {
            $stapel[] = $name;
        }
    }
    if (!empty($stapel)) {
        return 'nicht geschlossen: ' . implode(', ', $stapel);
    }
    return true;
}

echo "Testharness CBD_Block_Serializer\n";
echo "================================\n";

// ===========================================================================
// Gruppe A – Fragmentebene: html_to_blocks()
// ===========================================================================

abschnitt('Gruppe A – Fragmentebene (html_to_blocks)');

// T1: einfacher Absatz
$b = CBD_Block_Serializer::html_to_blocks('<p>Text</p>');
pruefe('T1 ein Absatz: Blockliste', array('core/paragraph'), namen($b));
pruefe_wahr('T1 Absatz: innerHTML enthält den Text', strpos($b[0]['innerHTML'], 'Text') !== false);

// T2: Überschriften mit Ebenen
$b = CBD_Block_Serializer::html_to_blocks('<h3>Drei</h3><h4>Vier</h4>');
pruefe('T2 zwei Überschriften: Blockliste', array('core/heading', 'core/heading'), namen($b));
pruefe('T2 Ebene der ersten', 3, $b[0]['attrs']['level']);
pruefe('T2 Ebene der zweiten', 4, $b[1]['attrs']['level']);

// T3: ungeordnete Liste – core/list-item, KEIN values-Attribut
$b = CBD_Block_Serializer::html_to_blocks('<ul><li>a</li><li>b</li></ul>');
pruefe('T3 ungeordnete Liste: ein Listenblock', array('core/list'), namen($b));
pruefe('T3 zwei Listeneinträge', array('core/list-item', 'core/list-item'), namen($b[0]['innerBlocks']));
pruefe_wahr('T3 kein veraltetes values-Attribut', !array_key_exists('values', $b[0]['attrs']));
pruefe_wahr('T3 nicht als geordnet markiert', empty($b[0]['attrs']['ordered']));

// T4: geordnete Liste
$b = CBD_Block_Serializer::html_to_blocks('<ol><li>a</li></ol>');
pruefe('T4 geordnete Liste: ein Listenblock', array('core/list'), namen($b));
pruefe('T4 ordered ist true', true, $b[0]['attrs']['ordered']);
pruefe('T4 ein Listeneintrag', array('core/list-item'), namen($b[0]['innerBlocks']));

// T5: Tabelle
$html = '<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>';
$b = CBD_Block_Serializer::html_to_blocks($html);
pruefe('T5 Tabelle: ein Tabellenblock', array('core/table'), namen($b));
pruefe('T5 Kopf: eine Zeile', 1, count($b[0]['attrs']['head']));
pruefe('T5 Kopf: zwei Zellen', 2, count($b[0]['attrs']['head'][0]['cells']));
pruefe('T5 Kopf: Zelle ist th', 'th', $b[0]['attrs']['head'][0]['cells'][0]['tag']);
pruefe('T5 Rumpf: eine Zeile', 1, count($b[0]['attrs']['body']));
pruefe('T5 Rumpf: zwei Zellen', 2, count($b[0]['attrs']['body'][0]['cells']));
pruefe('T5 Rumpf: Zelle ist td', 'td', $b[0]['attrs']['body'][0]['cells'][0]['tag']);

// T6: unbekanntes Element → Absatz (Rückfall wie im JavaScript)
$b = CBD_Block_Serializer::html_to_blocks('<blockquote>Zitat</blockquote>');
pruefe('T6 unbekanntes Element: als Absatz', array('core/paragraph'), namen($b));
pruefe_wahr('T6 Inhalt erhalten', strpos($b[0]['innerHTML'], 'Zitat') !== false);

// T7 / T8: leere Eingaben
pruefe('T7 leerer String: leeres Array', array(), CBD_Block_Serializer::html_to_blocks(''));
pruefe('T8 nur Leerraum: leeres Array', array(), CBD_Block_Serializer::html_to_blocks("\n   \n"));

// T9: UTF-8 (die klassische DOMDocument-Falle)
$b = CBD_Block_Serializer::html_to_blocks('<p>Größe, Übung, Straße</p>');
pruefe_wahr('T9 Umlaute unverändert', strpos($b[0]['innerHTML'], 'Größe, Übung, Straße') !== false,
    kurz($b[0]['innerHTML']));

// T10: LaTeX bleibt zeichengenau
$b = CBD_Block_Serializer::html_to_blocks('<p>Formel $a_1 \cdot b^*$ und $$\sum x_i$$</p>');
pruefe_wahr('T10 Inline-LaTeX erhalten', strpos($b[0]['innerHTML'], '$a_1 \cdot b^*$') !== false,
    kurz($b[0]['innerHTML']));
pruefe_wahr('T10 Display-LaTeX erhalten', strpos($b[0]['innerHTML'], '$$\sum x_i$$') !== false);

// T11: Inline-Auszeichnung und Links bleiben erhalten
$b = CBD_Block_Serializer::html_to_blocks('<p>Text mit <strong>fett</strong> und <a href="https://example.org/x">Link</a></p>');
pruefe_wahr('T11 strong erhalten', strpos($b[0]['innerHTML'], '<strong>fett</strong>') !== false);
pruefe_wahr('T11 Link erhalten', strpos($b[0]['innerHTML'], '<a href="https://example.org/x">Link</a>') !== false,
    kurz($b[0]['innerHTML']));

// T23: LaTeX mit < — libxml könnte das als Tag-Anfang deuten.
// Verlangt wird KEIN Inhaltsverlust; ob Absatz oder Freeform ist offen.
$b = CBD_Block_Serializer::html_to_blocks('<p>Wenn $a < b$ gilt</p>');
$gesamt = '';
foreach ($b as $x) { $gesamt .= $x['innerHTML']; }
pruefe_wahr('T23 Ergebnis nicht leer', count($b) > 0);
pruefe_wahr('T23 kein Inhaltsverlust (a und b noch da)',
    strpos($gesamt, '$a') !== false && strpos($gesamt, 'gilt') !== false,
    kurz($gesamt));

// ===========================================================================
// Gruppe B – Dokumentebene: to_block_array() / to_post_content()
// ===========================================================================

abschnitt('Gruppe B – Dokumentebene (to_block_array)');

$absatz = '<p>Inhalt</p>';

// T12: Abschnitt mit zugewiesenem Stil → Container
$sections = array(abschnitt_daten('Titel A', $absatz, 'h2-info', 'Info'));
$groups   = array(gruppe_daten('h2-info', 'Info'));
$b = CBD_Block_Serializer::to_block_array($sections, $groups, array('h2-info' => 'infotext_k1'), optionen());
pruefe('T12 genau ein Block', 1, count($b));
pruefe('T12 ist ein Container', 'container-block-designer/container', $b[0]['blockName']);
pruefe('T12 selectedBlock gesetzt', 'infotext_k1', $b[0]['attrs']['selectedBlock']);
pruefe('T12 blockTitle gesetzt', 'Titel A', $b[0]['attrs']['blockTitle']);
pruefe('T12 Absatz liegt im Container', array('core/paragraph'), namen($b[0]['innerBlocks']));
pruefe_wahr('T12 stableId vergeben', !empty($b[0]['attrs']['stableId']), $b[0]['attrs']['stableId']);
pruefe_wahr('T12 keine Standardattribute im JSON',
    !array_key_exists('customClasses', $b[0]['attrs'])
    && !array_key_exists('blockConfig', $b[0]['attrs'])
    && !array_key_exists('blockFeatures', $b[0]['attrs']),
    implode(',', array_keys($b[0]['attrs'])));

// T13: ohne Stil → Überschrift Ebene 3 plus Inhalt, kein Container
$b = CBD_Block_Serializer::to_block_array($sections, $groups, array('h2-info' => '__none__'), optionen());
pruefe('T13 ohne Container: zwei Blöcke', array('core/heading', 'core/paragraph'), namen($b));
pruefe('T13 Überschrift Ebene 3', 3, $b[0]['attrs']['level']);
pruefe_wahr('T13 Überschrift trägt den blockTitle', strpos($b[0]['innerHTML'], 'Titel A') !== false);

// T14: unbekannter Slug → Rückfall auf „ohne Container"
$b = CBD_Block_Serializer::to_block_array($sections, $groups, array('h2-info' => 'gibt-es-nicht'), optionen());
pruefe('T14 unbekannter Slug: kein Container', array('core/heading', 'core/paragraph'), namen($b));

// T15: Accordion-Gruppe mit drei Abschnitten
$acc_sections = array(
    abschnitt_daten('Zeile 1', '<p>Eins</p>', 'h2-uebungen', 'Übungen'),
    abschnitt_daten('Zeile 2', '<p>Zwei</p>', 'h2-uebungen', 'Übungen'),
    abschnitt_daten('Zeile 3', '<p>Drei</p>', 'h2-uebungen', 'Übungen'),
);
$acc_groups = array(gruppe_daten('h2-uebungen', 'Übungen', 3, accordion_daten(3, true, false, false, false)));
$b = CBD_Block_Serializer::to_block_array($acc_sections, $acc_groups, array('h2-uebungen' => '__none__'), optionen());
pruefe('T15 genau ein Block', 1, count($b));
pruefe('T15 ist ein Accordion', 'modular-blocks/accordion', $b[0]['blockName']);
pruefe('T15 headingLevel', 3, $b[0]['attrs']['headingLevel']);
pruefe('T15 allowMultiple', false, $b[0]['attrs']['allowMultiple']);
pruefe('T15 openFirst', false, $b[0]['attrs']['openFirst']);
pruefe('T15 showNumbering', true, $b[0]['attrs']['showNumbering']);
pruefe('T15 showExpandAll', false, $b[0]['attrs']['showExpandAll']);
pruefe('T15 drei Überschriften darin', 3, count(finde_alle($b[0]['innerBlocks'], 'core/heading')));
pruefe('T15 drei Absätze darin', 3, count(finde_alle($b[0]['innerBlocks'], 'core/paragraph')));

// T16: Accordion-Blocktyp nicht verfügbar → Rückfall auf Einzelabschnitte
$b = CBD_Block_Serializer::to_block_array($acc_sections, $acc_groups, array('h2-uebungen' => '__none__'),
    optionen(array('accordion_available' => false)));
pruefe('T16 kein Accordion erzeugt', 0, count(finde_alle($b, 'modular-blocks/accordion')));
pruefe('T16 stattdessen Einzelabschnitte', 6, count($b)); // je Abschnitt Überschrift + Absatz

// T17: Accordion mit zusätzlich zugewiesenem Stil → im Container
$b = CBD_Block_Serializer::to_block_array($acc_sections, $acc_groups, array('h2-uebungen' => 'uebungen'), optionen());
pruefe('T17 ein Container außen', 'container-block-designer/container', $b[0]['blockName']);
pruefe('T17 genau ein Innenblock', 1, count($b[0]['innerBlocks']));
pruefe('T17 und der ist das Accordion', 'modular-blocks/accordion', $b[0]['innerBlocks'][0]['blockName']);
pruefe('T17 blockTitle ist das Gruppenlabel', 'Übungen', $b[0]['attrs']['blockTitle']);

// T18: Nutzer hat die Direktive abgewählt
$b = CBD_Block_Serializer::to_block_array($acc_sections, $acc_groups, array('h2-uebungen' => '__none__'),
    optionen(array('accordion_opt_out' => array('h2-uebungen' => true))));
pruefe('T18 abgewählt: kein Accordion', 0, count(finde_alle($b, 'modular-blocks/accordion')));
pruefe('T18 abgewählt: Einzelabschnitte', 6, count($b));

// T19: H1-Unterdrückung beim ersten Abschnitt
$h1_sections = array(
    abschnitt_daten('Meine Seite', $absatz, 'h2-info', 'Info', 'h1'),
);
$b = CBD_Block_Serializer::to_block_array($h1_sections, $groups, array('h2-info' => '__none__'),
    optionen(array('page_title' => 'Meine Seite')));
pruefe('T19 Überschrift unterdrückt', array('core/paragraph'), namen($b));

// T20: zweiter H1-Abschnitt behält seine Überschrift
$h1_zwei = array(
    abschnitt_daten('Meine Seite', $absatz, 'h2-info', 'Info', 'h1'),
    abschnitt_daten('Zweites Thema', $absatz, 'h2-info', 'Info', 'h1'),
);
$b = CBD_Block_Serializer::to_block_array($h1_zwei, $groups, array('h2-info' => '__none__'),
    optionen(array('page_title' => 'Meine Seite')));
pruefe('T20 zweiter H1 behält Überschrift',
    array('core/paragraph', 'core/heading', 'core/paragraph'), namen($b));

// T21: Inhalt, aus dem sich kein Block bauen lässt → Freeform
$leer_sections = array(abschnitt_daten('Rest', '<!-- nur ein Kommentar -->', 'h2-info', 'Info'));
$b = CBD_Block_Serializer::to_block_array($leer_sections, $groups, array('h2-info' => '__none__'), optionen());
$freeform = finde_alle($b, 'core/freeform');
pruefe('T21 ein Freeform-Block', 1, count($freeform));
pruefe_wahr('T21 Roh-HTML erhalten', strpos($freeform[0]['innerHTML'], 'nur ein Kommentar') !== false,
    kurz($freeform[0]['innerHTML']));

// T22: keine Abschnitte
pruefe('T22 leere Abschnittsliste: leerer String', '',
    CBD_Block_Serializer::to_post_content(array(), array(), array(), optionen()));

// ===========================================================================
// Gruppe C – Markup-Treue gegen die Fixture aus AP-1.2
// ===========================================================================

abschnitt('Gruppe C – Markup-Treue gegen tools/fixtures/referenz-markup.html');

$fixture_pfad = __DIR__ . '/fixtures/referenz-markup.html';

if (!file_exists($fixture_pfad)) {
    ueberspringe('C1–C4', 'Fixture fehlt: ' . $fixture_pfad);
} else {
    $fixture = file_get_contents($fixture_pfad);

    // Normalisierung: Leerraum ZWISCHEN Tags vereinheitlichen, Text unangetastet.
    $normal = function ($html) {
        $html = preg_replace('/>\s+</', '> <', $html);
        return trim(preg_replace('/[ \t]+/', ' ', $html));
    };

    // C1: öffnender Trenner des Containers
    if (preg_match('/<!-- wp:container-block-designer\/container [^>]*-->/', $fixture, $t)) {
        $erwartet_trenner = $t[0];

        $c_sections = array(abschnitt_daten(
            'Was ist die sp³-Hybridisierung?',
            '<p>Platzhalter</p>',
            'h2-info', 'Info'
        ));
        $c_groups = array(gruppe_daten('h2-info', 'Info'));
        $erzeugt = CBD_Block_Serializer::to_post_content(
            $c_sections, $c_groups, array('h2-info' => 'infotext_k1'),
            array(
                'known_slugs'       => array('infotext_k1'),
                'stable_id_factory' => function () { return 'cbd-1784920502523-6k9yderp'; },
            )
        );

        preg_match('/<!-- wp:container-block-designer\/container [^>]*-->/', $erzeugt, $t2);
        pruefe('C1 öffnender Trenner des Containers', $erwartet_trenner, isset($t2[0]) ? $t2[0] : '(keiner)');

        // C2: das div mit Klassen und Datenattributen
        if (preg_match('/<div class="wp-block-container[^>]*>/', $fixture, $d)) {
            preg_match('/<div class="wp-block-container[^>]*>/', $erzeugt, $d2);
            pruefe('C2 div des Containers', $d[0], isset($d2[0]) ? $d2[0] : '(keines)');
        } else {
            ueberspringe('C2', 'kein Container-div in der Fixture');
        }

        // C3: Absatz-Markup (aus der Fixture, nicht abgeleitet)
        if (preg_match('/<!-- wp:paragraph -->\s*<p>/', $fixture)) {
            $p = CBD_Block_Serializer::to_post_content(
                array(abschnitt_daten('X', '<p>Text</p>', 'g', 'g')),
                array(gruppe_daten('g', 'g')),
                array('g' => '__none__'),
                optionen()
            );
            pruefe_wahr('C3 Absatz: <!-- wp:paragraph --> gefolgt von <p>',
                (bool) preg_match('/<!-- wp:paragraph -->\s*<p>/', $p), kurz($p));
        } else {
            ueberspringe('C3', 'kein Absatz in der Fixture');
        }

        // C4: Zeilenumbruch-Stil des JavaScript-Serializers
        pruefe_wahr('C4 Umbruch nach öffnendem Container-Trenner',
            (bool) preg_match('/container-block-designer\/container [^>]*-->\n<div/', $erzeugt),
            kurz($erzeugt, 200));
    } else {
        ueberspringe('C1–C4', 'kein Container-Trenner in der Fixture gefunden');
    }
}

// ===========================================================================
// Gruppe D – Delimiter-Bilanz
// ===========================================================================

abschnitt('Gruppe D – Delimiter-Bilanz');

$faelle = array(
    'D1 (wie T12, Container mit Absatz)' => CBD_Block_Serializer::to_post_content(
        $sections, $groups, array('h2-info' => 'infotext_k1'), optionen()),
    'D2 (wie T15, Accordion)' => CBD_Block_Serializer::to_post_content(
        $acc_sections, $acc_groups, array('h2-uebungen' => '__none__'), optionen()),
    'D3 (wie T17, Accordion im Container)' => CBD_Block_Serializer::to_post_content(
        $acc_sections, $acc_groups, array('h2-uebungen' => 'uebungen'), optionen()),
);
foreach ($faelle as $bezeichnung => $markup) {
    $ergebnis = pruefe_delimiter_bilanz($markup);
    pruefe($bezeichnung, true, $ergebnis === true ? true : $ergebnis);
}

// Zusatz: Liste mit Einträgen muss ebenfalls ausgeglichen sein
$listen_markup = CBD_Block_Serializer::to_post_content(
    array(abschnitt_daten('L', '<ul><li>a</li><li>b</li></ul>', 'g', 'g')),
    array(gruppe_daten('g', 'g')),
    array('g' => '__none__'),
    optionen()
);
$ergebnis = pruefe_delimiter_bilanz($listen_markup);
pruefe('D4 (Liste mit zwei Einträgen)', true, $ergebnis === true ? true : $ergebnis);

// ===========================================================================
// Zusammenfassung
// ===========================================================================

echo "\n";
echo str_repeat('=', 60) . "\n";
printf("%d Prüfungen, %d Fehler", $GLOBALS['pruefungen'], $GLOBALS['fehler']);
if ($GLOBALS['uebersprungen'] > 0) {
    printf(", %d übersprungen", $GLOBALS['uebersprungen']);
}
echo "\n";
echo str_repeat('=', 60) . "\n";

exit($GLOBALS['fehler'] > 0 ? 1 : 0);
