<?php
/**
 * Container Block Designer – Block-Serializer
 *
 * Wandelt die Ausgabe von CBD_Content_Importer::parse_markdown_content() in
 * fertiges Gutenberg-Block-Markup um, wie es in `post_content` steht.
 *
 * ABGRENZUNG ZUM CONTENT-IMPORTER
 * Der Content-Importer baut Blöcke im geöffneten Editor – das erledigt
 * JavaScript (assets/js/content-importer.js, insertBlocks()). Beim
 * Seitenimport gibt es keinen Editor, deshalb bildet diese Klasse dieselbe
 * Abbildung serverseitig nach.
 *
 * DAS ZIELMARKUP IST GEMESSEN, NICHT GERATEN
 * Die Vorlage steht in tools/fixtures/referenz-markup.html und stammt aus
 * einer echten Editor-Speicherung; tools/fixtures/referenz-umgebung.md hält
 * fest, was daraus abgelesen wurde. Ändert sich die WordPress- oder
 * Plugin-Version, muss die Fixture neu erhoben und
 * `php tools/test-block-serializer.php` erneut grün gemacht werden – sonst
 * erzeugt diese Klasse stillschweigend ungültige Blöcke.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.86
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Block-Serializer
 *
 * Nur statische Methoden – die Klasse hält keinen Zustand und wird nicht
 * als Singleton initialisiert.
 */
class CBD_Block_Serializer {

    /**
     * Sonderwert für „ohne Container einfügen". Entspricht der Konstante
     * NO_CONTAINER in assets/js/content-importer.js.
     */
    const OHNE_CONTAINER = '__none__';

    /** Blockname des optionalen Accordion-Blocks aus „Eigene WP Blocks". */
    const ACCORDION_BLOCK = 'modular-blocks/accordion';

    // -----------------------------------------------------------------
    // Öffentliche Schnittstelle
    // -----------------------------------------------------------------

    /**
     * Baut die Blockstruktur als verschachteltes Array im WordPress-Blockformat.
     *
     * @param array $sections       Abschnitte aus parse_markdown_content()
     * @param array $groups         Gruppen aus parse_markdown_content()
     * @param array $style_mappings Gruppenschlüssel => Design-Slug oder '__none__'
     * @param array $optionen       accordion_opt_out, page_title, known_slugs,
     *                              accordion_available, stable_id_factory
     * @return array
     */
    public static function to_block_array(array $sections, array $groups, array $style_mappings, array $optionen = array()) {
        $opt = self::optionen_vervollstaendigen($optionen);

        // Gruppen nach Schlüssel nachschlagbar machen
        $gruppen_nach_key = array();
        foreach ($groups as $g) {
            if (isset($g['key'])) {
                $gruppen_nach_key[$g['key']] = $g;
            }
        }

        $bloecke = array();
        $accordion_erledigt = array();
        $erster_abschnitt = true;

        foreach ($sections as $section) {
            $group_key = self::gruppenschluessel($section);
            $gruppe = isset($gruppen_nach_key[$group_key]) ? $gruppen_nach_key[$group_key] : null;

            // --- Accordion-Zweig ---------------------------------------
            if (self::nutzt_accordion($gruppe, $group_key, $opt)) {
                if (isset($accordion_erledigt[$group_key])) {
                    // Diese Gruppe wurde beim ersten ihrer Abschnitte
                    // vollständig eingefügt.
                    $erster_abschnitt = false;
                    continue;
                }
                $accordion_erledigt[$group_key] = true;

                $bloecke[] = self::baue_accordion_gruppe($sections, $group_key, $gruppe, $style_mappings, $opt);
                $erster_abschnitt = false;
                continue;
            }

            // --- Stil bestimmen ----------------------------------------
            $slug = self::ermittle_slug($group_key, $style_mappings, $opt['known_slugs']);

            // --- Inhalt umwandeln --------------------------------------
            $inhalt = self::inhaltsbloecke($section);

            if ($slug === self::OHNE_CONTAINER) {
                // Ohne Container: Überschrift voranstellen, damit die
                // Gliederung erhalten bleibt.
                $titel = isset($section['blockTitle']) ? trim($section['blockTitle']) : '';
                if ($titel !== '' && !self::titel_unterdruecken($section, $erster_abschnitt, $opt['page_title'])) {
                    $bloecke[] = self::block_heading($titel, 3);
                }
                foreach ($inhalt as $b) {
                    $bloecke[] = $b;
                }
            } else {
                $bloecke[] = self::block_container(
                    $slug,
                    isset($section['blockTitle']) ? $section['blockTitle'] : '',
                    $inhalt,
                    $opt
                );
            }

            $erster_abschnitt = false;
        }

        return $bloecke;
    }

    /**
     * Wie to_block_array(), zusätzlich serialisiert.
     *
     * @return string Markup für post_content; leerer String ohne Blöcke.
     */
    public static function to_post_content(array $sections, array $groups, array $style_mappings, array $optionen = array()) {
        $bloecke = self::to_block_array($sections, $groups, $style_mappings, $optionen);
        return self::serialisiere($bloecke);
    }

    /**
     * Serialisiert eine Blockliste der obersten Ebene.
     *
     * **Nicht** einfach serialize_blocks(): Der JavaScript-Serializer trennt
     * Geschwisterblöcke mit einer Leerzeile (`\n\n`), die PHP-Fassung mit
     * gar nichts. Ohne diese Angleichung wäre das Ergebnis zwar gültig, aber
     * nicht zeichengleich mit dem, was der Editor schreibt – und jeder
     * spätere Speichervorgang erzeugte einen unnötigen Unterschied.
     *
     * @param array $bloecke
     * @return string
     */
    public static function serialisiere(array $bloecke) {
        if (empty($bloecke)) {
            return '';
        }
        $teile = array();
        foreach ($bloecke as $block) {
            $teile[] = serialize_block($block);
        }
        return implode("\n\n", $teile);
    }

    /**
     * Wandelt ein HTML-Fragment in Blöcke um.
     *
     * Bildet htmlToGutenbergBlocks() aus assets/js/content-importer.js nach,
     * mit EINER bewussten Abweichung: Listen entstehen als core/list mit
     * core/list-item-Kindern statt mit dem veralteten Attribut `values`.
     * Das JavaScript verlässt sich darauf, dass der Editor beim Laden
     * migriert – in der Datenbank muss aber sofort die migrierte Form stehen,
     * sonst gilt der Block beim ersten Öffnen als ungültig.
     *
     * @param string $html
     * @return array Blockarrays; leeres Array, wenn nichts zu bauen war.
     */
    public static function html_to_blocks($html) {
        $html = (string) $html;
        if (trim($html) === '') {
            return array();
        }

        $dom = self::lade_html($html);
        if ($dom === null) {
            return array();
        }

        $bloecke = array();
        foreach ($dom->childNodes as $knoten) {
            // Der Encoding-Hinweis beim Laden erzeugt einen
            // Verarbeitungsanweisungs-Knoten – der gehört nicht in den Inhalt.
            if ($knoten->nodeType === XML_PI_NODE) {
                continue;
            }

            if ($knoten->nodeType === XML_TEXT_NODE) {
                // Text ohne umgebendes Element (kommt bei Fragmenten vor)
                $text = trim($knoten->textContent);
                if ($text !== '') {
                    $bloecke[] = self::block_paragraph(self::knoten_html($knoten));
                }
                continue;
            }

            if ($knoten->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $block = self::element_zu_block($knoten);
            if ($block !== null) {
                $bloecke[] = $block;
            }
        }

        return $bloecke;
    }

    // -----------------------------------------------------------------
    // Fragmentebene
    // -----------------------------------------------------------------

    /**
     * Lädt ein HTML-Fragment als DOMDocument.
     *
     * Zwei Fallen, beide hier entschärft:
     * 1. Ohne Encoding-Hinweis zerlegt libxml UTF-8 in Einzelbytes – „Größe"
     *    würde zu „GrÃ¶ÃŸe".
     * 2. Ohne LIBXML_HTML_NOIMPLIED/NODEFDTD baut libxml ein
     *    <html><body>-Gerüst um das Fragment.
     *
     * @param string $html
     * @return DOMDocument|null null, wenn nichts Verwertbares entstand.
     */
    private static function lade_html($html) {
        if (!class_exists('DOMDocument')) {
            return null;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $vorher = libxml_use_internal_errors(true);

        $flags = 0;
        if (defined('LIBXML_HTML_NOIMPLIED')) {
            $flags |= LIBXML_HTML_NOIMPLIED;
        }
        if (defined('LIBXML_HTML_NODEFDTD')) {
            $flags |= LIBXML_HTML_NODEFDTD;
        }

        $geladen = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, $flags);

        libxml_clear_errors();
        libxml_use_internal_errors($vorher);

        if (!$geladen || !$dom->childNodes || $dom->childNodes->length === 0) {
            return null;
        }
        return $dom;
    }

    /**
     * Bildet ein einzelnes Element auf einen Block ab.
     *
     * @param DOMElement $el
     * @return array|null
     */
    private static function element_zu_block($el) {
        $tag = strtolower($el->nodeName);

        // Überschriften h3–h6 (h1 und h2 sind vom Parser bereits verbraucht)
        if (preg_match('/^h([3-6])$/', $tag, $m)) {
            return self::block_heading(self::innen_html($el), (int) $m[1]);
        }

        if ($tag === 'p') {
            $innen = self::innen_html($el);
            if (trim(strip_tags($innen)) === '' && trim($innen) === '') {
                return null;
            }
            return self::block_paragraph($innen);
        }

        if ($tag === 'ul' || $tag === 'ol') {
            return self::block_list($el, $tag === 'ol');
        }

        if ($tag === 'table') {
            return self::block_table($el);
        }

        // Rückfall wie im JavaScript: alles andere wird zum Absatz.
        $innen = self::innen_html($el);
        if (trim($innen) === '') {
            return null;
        }
        return self::block_paragraph($innen);
    }

    /**
     * Innen-HTML eines Knotens.
     *
     * Bewusst über saveHTML() je Kindknoten statt über textContent – sonst
     * gingen <strong>, <em> und <a> verloren.
     *
     * @param DOMNode $knoten
     * @return string
     */
    private static function innen_html($knoten) {
        $html = '';
        foreach ($knoten->childNodes as $kind) {
            $html .= $knoten->ownerDocument->saveHTML($kind);
        }
        return $html;
    }

    /** Ein Knoten samt eigenem Tag als HTML. */
    private static function knoten_html($knoten) {
        return $knoten->ownerDocument->saveHTML($knoten);
    }

    // -----------------------------------------------------------------
    // Blockbausteine
    //
    // Die HTML-Hüllen entsprechen der save()-Ausgabe von WordPress 7.0.3.
    // Maßgeblich ist tools/fixtures/referenz-umgebung.md:
    //   core/paragraph  supports.className = false -> <p> ohne Klasse
    //   core/heading    supports.className = true  -> class="wp-block-heading"
    //   core/list       supports.className = true  -> class="wp-block-list"
    //   core/list-item  supports.className = false -> <li> ohne Klasse
    //   core/table      supports.className = true  -> <figure class="wp-block-table">
    // -----------------------------------------------------------------

    /**
     * Baut ein Blockarray im WordPress-Format.
     *
     * @param string     $name          Blockname
     * @param array      $attrs         Attribute (nur Nicht-Standardwerte!)
     * @param string     $inner_html    HTML ohne Kindblöcke
     * @param array      $inner_blocks  Kindblöcke
     * @param array|null $inner_content Eigene innerContent-Liste; null = aus
     *                                  $inner_html ableiten
     * @return array
     */
    private static function block($name, $attrs, $inner_html, $inner_blocks = array(), $inner_content = null) {
        if ($inner_content === null) {
            // Der JavaScript-Serializer setzt je einen Zeilenumbruch nach dem
            // öffnenden und vor dem schließenden Trenner.
            $inner_content = array("\n" . $inner_html . "\n");
            $inner_html = "\n" . $inner_html . "\n";
        }

        return array(
            'blockName'    => $name,
            'attrs'        => $attrs,
            'innerBlocks'  => $inner_blocks,
            'innerHTML'    => $inner_html,
            'innerContent' => $inner_content,
        );
    }

    /**
     * Baut innerContent für einen Block mit Kindern: öffnendes Tag, dann je
     * Kind ein null-Platzhalter, dazwischen die Leerzeile, die der
     * JavaScript-Serializer zwischen Geschwistern setzt, dann das
     * schließende Tag.
     *
     * @param string $oeffnend
     * @param int    $anzahl_kinder
     * @param string $schliessend
     * @return array
     */
    private static function inner_content_mit_kindern($oeffnend, $anzahl_kinder, $schliessend) {
        $liste = array("\n" . $oeffnend);
        for ($i = 0; $i < $anzahl_kinder; $i++) {
            if ($i > 0) {
                $liste[] = "\n\n";
            }
            $liste[] = null;
        }
        $liste[] = $schliessend . "\n";
        return $liste;
    }

    private static function block_paragraph($inhalt) {
        return self::block('core/paragraph', array(), '<p>' . $inhalt . '</p>');
    }

    private static function block_heading($inhalt, $level) {
        $level = (int) $level;
        if ($level < 1 || $level > 6) {
            $level = 3;
        }
        $tag = 'h' . $level;
        return self::block(
            'core/heading',
            array('level' => $level),
            '<' . $tag . ' class="wp-block-heading">' . $inhalt . '</' . $tag . '>'
        );
    }

    /**
     * core/list mit core/list-item-Kindern.
     *
     * @param DOMElement $el
     * @param bool       $geordnet
     * @return array
     */
    private static function block_list($el, $geordnet) {
        $eintraege = array();
        foreach ($el->childNodes as $kind) {
            if ($kind->nodeType === XML_ELEMENT_NODE && strtolower($kind->nodeName) === 'li') {
                $eintraege[] = self::block(
                    'core/list-item',
                    array(),
                    '<li>' . self::innen_html($kind) . '</li>'
                );
            }
        }

        $tag = $geordnet ? 'ol' : 'ul';
        $attrs = $geordnet ? array('ordered' => true) : array();

        $oeffnend = '<' . $tag . ' class="wp-block-list">';
        $schliessend = '</' . $tag . '>';

        return self::block(
            'core/list',
            $attrs,
            $oeffnend . $schliessend,
            $eintraege,
            self::inner_content_mit_kindern($oeffnend, count($eintraege), $schliessend)
        );
    }

    /**
     * core/table – die Zellen stehen als Attribute im Block, das HTML wird
     * daraus gebaut.
     *
     * @param DOMElement $el
     * @return array
     */
    private static function block_table($el) {
        $head = self::tabellen_zeilen($el, 'thead', 'th');
        $body = self::tabellen_zeilen($el, 'tbody', 'td');

        // Tabelle ganz ohne thead/tbody: alle tr als Rumpf behandeln
        if (empty($head) && empty($body)) {
            $body = self::zeilen_aus_knoten($el, 'td');
        }

        $html = '<figure class="wp-block-table"><table>';
        if (!empty($head)) {
            $html .= '<thead>' . self::zeilen_html($head) . '</thead>';
        }
        $html .= '<tbody>' . self::zeilen_html($body) . '</tbody>';
        $html .= '</table></figure>';

        return self::block(
            'core/table',
            array('hasFixedLayout' => false, 'head' => $head, 'body' => $body, 'foot' => array()),
            $html
        );
    }

    /** Zeilen eines Tabellenbereichs als Attributstruktur. */
    private static function tabellen_zeilen($el, $bereich, $zellen_tag) {
        $zeilen = array();
        foreach ($el->getElementsByTagName($bereich) as $abschnitt) {
            $zeilen = array_merge($zeilen, self::zeilen_aus_knoten($abschnitt, $zellen_tag));
            break; // nur der erste thead/tbody
        }
        return $zeilen;
    }

    /** Alle tr eines Knotens als Attributstruktur. */
    private static function zeilen_aus_knoten($knoten, $zellen_tag) {
        $zeilen = array();
        foreach ($knoten->getElementsByTagName('tr') as $tr) {
            $zellen = array();
            foreach ($tr->childNodes as $zelle) {
                if ($zelle->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }
                $tag = strtolower($zelle->nodeName);
                if ($tag !== 'th' && $tag !== 'td') {
                    continue;
                }
                // Zellen des gesuchten Typs; in einer Kopfzeile stehen th,
                // im Rumpf td.
                if ($tag !== $zellen_tag) {
                    continue;
                }
                $zellen[] = array(
                    'content' => self::innen_html($zelle),
                    'tag'     => $tag,
                );
            }
            if (!empty($zellen)) {
                $zeilen[] = array('cells' => $zellen);
            }
        }
        return $zeilen;
    }

    /** Baut das HTML der Tabellenzeilen aus der Attributstruktur. */
    private static function zeilen_html($zeilen) {
        $html = '';
        foreach ($zeilen as $zeile) {
            $html .= '<tr>';
            foreach ($zeile['cells'] as $zelle) {
                $tag = $zelle['tag'];
                $html .= '<' . $tag . '>' . $zelle['content'] . '</' . $tag . '>';
            }
            $html .= '</tr>';
        }
        return $html;
    }

    /**
     * Container-Block des Plugins.
     *
     * Das Markup ist der Fixture entnommen, nicht abgeleitet – der Container
     * hat als einziger beteiligter Block eine eigene statische save()-Funktion
     * (ContainerBlockSave in assets/js/block-editor.js):
     *
     *   <div class="wp-block-container-block-designer-container cbd-container"
     *        data-block="<slug>" data-stable-id="<id>">…</div>
     *
     * Die generierte Blockklasse wird NICHT zusätzlich angehängt – sie steckt
     * schon im übergebenen className und erscheint nur einmal.
     * `data-features` fehlt bei leeren Features ganz.
     * Im Attribut-JSON stehen nur Nicht-Standardwerte, in der Reihenfolge
     * selectedBlock, blockTitle, stableId.
     *
     * @param string $slug
     * @param string $titel
     * @param array  $innen
     * @param array  $opt
     * @return array
     */
    private static function block_container($slug, $titel, $innen, $opt) {
        $stable_id = call_user_func($opt['stable_id_factory']);

        $attrs = array(
            'selectedBlock' => $slug,
            'blockTitle'    => (string) $titel,
            'stableId'      => $stable_id,
        );

        $oeffnend = '<div class="wp-block-container-block-designer-container cbd-container"'
            . ' data-block="' . esc_attr($slug) . '"'
            . ' data-stable-id="' . esc_attr($stable_id) . '">';
        $schliessend = '</div>';

        return self::block(
            'container-block-designer/container',
            $attrs,
            $oeffnend . $schliessend,
            $innen,
            self::inner_content_mit_kindern($oeffnend, count($innen), $schliessend)
        );
    }

    /**
     * Accordion-Block aus dem Plugin „Eigene WP Blocks".
     *
     * Sein save() gibt ausschließlich InnerBlocks.Content aus – der Block hat
     * also KEINE eigene HTML-Hülle. innerContent besteht daher nur aus den
     * Platzhaltern der Kindblöcke.
     *
     * @param array $accordion Direktive mit level/multiple/…
     * @param array $innen     Kindblöcke
     * @return array
     */
    private static function block_accordion($accordion, $innen) {
        $attrs = array(
            'headingLevel'  => (int) $accordion['level'],
            'allowMultiple' => (bool) $accordion['multiple'],
            'openFirst'     => (bool) $accordion['openFirst'],
            'showNumbering' => (bool) $accordion['numbering'],
            'showExpandAll' => (bool) $accordion['expandAll'],
        );

        return self::block(
            self::ACCORDION_BLOCK,
            $attrs,
            '',
            $innen,
            self::inner_content_mit_kindern('', count($innen), '')
        );
    }

    // -----------------------------------------------------------------
    // Dokumentebene
    // -----------------------------------------------------------------

    /** Ergänzt fehlende Optionen um ihre Vorgabewerte. */
    private static function optionen_vervollstaendigen(array $optionen) {
        $opt = array_merge(array(
            'accordion_opt_out'   => array(),
            'page_title'          => '',
            'known_slugs'         => null,
            'accordion_available' => null,
            'stable_id_factory'   => null,
        ), $optionen);

        if ($opt['known_slugs'] === null) {
            $opt['known_slugs'] = self::aktive_slugs();
        }
        if (!is_array($opt['known_slugs'])) {
            $opt['known_slugs'] = array();
        }

        if ($opt['accordion_available'] === null) {
            $opt['accordion_available'] = self::accordion_registriert();
        }

        if (!is_callable($opt['stable_id_factory'])) {
            $opt['stable_id_factory'] = array(__CLASS__, 'erzeuge_stable_id');
        }

        return $opt;
    }

    /**
     * Aktive Design-Slugs aus der Datenbank.
     *
     * Der Bezeichner steht in der Spalte `name` (die Spalte `slug` ist auf
     * Altbeständen nur eine Kopie davon, siehe
     * CBD_Admin::handle_database_repair(); auf frisch angelegten Tabellen
     * fehlt sie ganz). Deshalb wird hier `name` gelesen.
     *
     * @return array
     */
    private static function aktive_slugs() {
        global $wpdb;
        if (!isset($wpdb) || !defined('CBD_TABLE_BLOCKS')) {
            return array();
        }
        $slugs = $wpdb->get_col("SELECT name FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'");
        return is_array($slugs) ? $slugs : array();
    }

    /** Ist der Accordion-Blocktyp registriert? */
    private static function accordion_registriert() {
        if (!class_exists('WP_Block_Type_Registry')) {
            return false;
        }
        return WP_Block_Type_Registry::get_instance()->is_registered(self::ACCORDION_BLOCK);
    }

    /**
     * Erzeugt eine stableId im Format von assets/js/block-editor.js:83
     * ('cbd-' + Date.now() + '-' + Math.random().toString(36).substr(2, 8)).
     *
     * Muss vergeben werden: Fehlt sie, ergänzt der Editor sie beim Öffnen und
     * markiert den Beitrag als geändert; bei Dubletten vergibt seine
     * Duplikaterkennung ohnehin neu.
     *
     * @return string
     */
    public static function erzeuge_stable_id() {
        $zeichen = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $zufall = '';
        for ($i = 0; $i < 8; $i++) {
            $zufall .= $zeichen[wp_rand(0, strlen($zeichen) - 1)];
        }
        return 'cbd-' . (string) round(microtime(true) * 1000) . '-' . $zufall;
    }

    /** Gruppenschlüssel eines Abschnitts (mit Rückfall auf die Kompetenzstufe). */
    private static function gruppenschluessel($section) {
        if (!empty($section['groupKey'])) {
            return $section['groupKey'];
        }
        return isset($section['competence']) ? $section['competence'] : 'other';
    }

    /** Soll diese Gruppe als Accordion eingefügt werden? */
    private static function nutzt_accordion($gruppe, $group_key, $opt) {
        if (!$opt['accordion_available']) {
            return false;
        }
        if (!$gruppe || empty($gruppe['accordion']) || empty($gruppe['accordion']['enabled'])) {
            return false;
        }
        // Vom Nutzer abgewählt?
        return empty($opt['accordion_opt_out'][$group_key]);
    }

    /**
     * Ermittelt den zu verwendenden Design-Slug.
     *
     * Ein Container mit unbekanntem Slug würde im Frontend „Block nicht
     * gefunden" rendern – deshalb fällt alles Unbekannte auf „ohne
     * Container" zurück, genau wie das JavaScript.
     */
    private static function ermittle_slug($group_key, $style_mappings, $known_slugs) {
        $slug = isset($style_mappings[$group_key]) ? $style_mappings[$group_key] : '';
        if ($slug === '' || $slug === self::OHNE_CONTAINER) {
            return self::OHNE_CONTAINER;
        }
        if (!in_array($slug, $known_slugs, true)) {
            return self::OHNE_CONTAINER;
        }
        return $slug;
    }

    /**
     * Inhaltsblöcke eines Abschnitts, mit Rückfall auf core/freeform.
     *
     * Der Rückfall verhindert Inhaltsverlust: Lässt sich aus dem HTML kein
     * Block bauen (etwa wenn es nur aus einem Kommentar besteht), landet das
     * Roh-HTML im klassischen Block.
     */
    private static function inhaltsbloecke($section) {
        $html = isset($section['content']) ? $section['content'] : '';
        $bloecke = self::html_to_blocks($html);

        if (empty($bloecke) && trim($html) !== '') {
            return array(self::block('core/freeform', array(), $html));
        }
        return $bloecke;
    }

    /**
     * Soll die Überschrift dieses Abschnitts entfallen?
     *
     * Nur beim ERSTEN Abschnitt, nur wenn er aus einer H1 stammt und sein
     * Titel dem Seitentitel entspricht. Sonst stünde der Seitentitel doppelt
     * auf der Seite – einmal als Titel, einmal als Überschriftenblock.
     */
    private static function titel_unterdruecken($section, $ist_erster, $page_title) {
        if (!$ist_erster) {
            return false;
        }
        if (!isset($section['titleSource']) || $section['titleSource'] !== 'h1') {
            return false;
        }
        $titel = isset($section['blockTitle']) ? trim($section['blockTitle']) : '';
        return ($titel !== '' && $titel === trim((string) $page_title));
    }

    /**
     * Baut aus allen Abschnitten einer Gruppe einen Accordion-Block,
     * gegebenenfalls in einen Container gehüllt.
     */
    private static function baue_accordion_gruppe($alle_sections, $group_key, $gruppe, $style_mappings, $opt) {
        $accordion = $gruppe['accordion'];
        $level = isset($accordion['level']) ? (int) $accordion['level'] : 3;

        $innen = array();
        foreach ($alle_sections as $s) {
            if (self::gruppenschluessel($s) !== $group_key) {
                continue;
            }
            $titel = isset($s['blockTitle']) ? $s['blockTitle'] : '';
            if (trim($titel) !== '') {
                $innen[] = self::block_heading($titel, $level);
            }
            foreach (self::inhaltsbloecke($s) as $b) {
                $innen[] = $b;
            }
        }

        $accordion_block = self::block_accordion($accordion, $innen);

        // Ist der Gruppe zusätzlich ein Design zugewiesen, liegt das
        // Accordion als einziger Innenblock im Container.
        $slug = self::ermittle_slug($group_key, $style_mappings, $opt['known_slugs']);
        if ($slug === self::OHNE_CONTAINER) {
            return $accordion_block;
        }

        $label = isset($gruppe['label']) ? $gruppe['label'] : '';
        return self::block_container($slug, $label, array($accordion_block), $opt);
    }
}
