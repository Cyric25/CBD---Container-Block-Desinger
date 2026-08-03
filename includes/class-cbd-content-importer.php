<?php
/**
 * Container Block Designer - Content Importer
 *
 * Parses Markdown files and creates CDB blocks with automatic K1/K2/K3 style assignment
 *
 * @package ContainerBlockDesigner
 * @since 2.9.3
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Importer Klasse
 */
class CBD_Content_Importer {

    /**
     * Singleton-Instanz
     */
    private static $instance = null;

    /**
     * Keywords für Kompetenzstufen
     */
    private $section_keywords = array(
        'k1' => array('basiswissen', 'basis', 'k1', 'grundwissen'),
        'k2' => array('erweitertes wissen', 'erweitertes', 'k2', 'erweitert'),
        'k3' => array('vertiefendes wissen', 'vertiefendes', 'k3', 'vertieft'),
        'sources' => array('quellenverzeichnis', 'quellen', 'literatur', 'literaturverzeichnis', 'referenzen', 'bibliographie')
    );

    /**
     * Singleton-Getter
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        // AJAX-Handler registrieren
        add_action('wp_ajax_cbd_parse_import_file', array($this, 'ajax_parse_import_file'));
        add_action('wp_ajax_cbd_get_style_mappings', array($this, 'ajax_get_style_mappings'));
    }

    /**
     * AJAX: Markdown-Datei parsen
     */
    public function ajax_parse_import_file() {
        // Sicherheitsprüfung
        check_ajax_referer('cbd_content_import', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
            return;
        }

        // Markdown-Content: Verwende wp_unslash statt wp_kses_post
        // wp_kses_post würde Backslashes escapen (LaTeX-Formeln werden zerstört)
        $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';

        if (empty($content)) {
            wp_send_json_error(array('message' => __('Kein Inhalt gefunden', 'container-block-designer')));
            return;
        }

        try {
            // Block-Designs einmal laden: der Parser braucht sie, um
            // Überschriften wie "## infotext_k1" als bewusste Style-Angabe
            // (eigene Gruppe) statt als Kompetenzstufe zu erkennen.
            $designs = $this->get_active_designs();

            $parsed_data = $this->parse_markdown_content($content, $designs);

            // Zu jeder Gruppe das passende Block-Design vorschlagen. Damit
            // werden auch H2-Überschriften wie "## Übungen" oder "## Hinweise"
            // automatisch dem gleichnamigen Design zugeordnet — nicht nur
            // die Kompetenzstufen K1/K2/K3.
            $parsed_data['groups'] = $this->attach_style_suggestions($parsed_data['groups'], $designs);

            wp_send_json_success($parsed_data);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Ergänzt die Gruppen um Style-Vorschläge aus den aktiven Block-Designs.
     *
     * @param array $groups Gruppen aus parse_markdown_content()
     * @return array
     */
    private function attach_style_suggestions($groups, $blocks = null) {
        if ($blocks === null) {
            $blocks = $this->get_active_designs();
        }

        // Komfort-Fallback für die klassischen Kompetenz-Überschriften
        // ("## Basiswissen" → infotext_k1). Greift nur, wenn KEIN Design den
        // Namen der Überschrift trägt — der Namensabgleich hat Vorrang.
        $legacy = array(
            'k1' => array('infotext_k1', 'k1'),
            'k2' => array('infotext_k2', 'k2'),
            'k3' => array('infotext_k3', 'k3'),
            'sources' => array('quellen', 'literatur', 'referenz', 'bibliographie')
        );

        $slugs = array();
        foreach ($blocks as $block) {
            $slugs[] = $block->slug;
        }

        foreach ($groups as $i => $group) {
            if ($group['key'] === 'other') {
                continue; // Abschnitte ohne Überschrift: kein Automatik-Vorschlag
            }

            // 1. HAUPTWEG: Die H2-Überschrift IST der Stilname.
            //    Automatisch zugewiesen wird nur bei exakter Namensgleichheit.
            $match = $this->match_style_for_label($group['label'], $blocks);
            if ($match['matchedBy'] === 'exact') {
                $groups[$i]['suggestedStyle'] = $match['slug'];
                $groups[$i]['matchedBy'] = 'exact';
                continue;
            }

            // 2. Klassische Kompetenz-Überschrift ohne gleichnamiges Design
            //    (Rückwärtskompatibilität für ältere Lernmaterial-Dateien)
            $competence = isset($group['competence']) ? $group['competence'] : 'other';
            if (isset($legacy[$competence])) {
                foreach ($legacy[$competence] as $needle) {
                    if (in_array($needle, $slugs, true)) {
                        $groups[$i]['suggestedStyle'] = $needle;
                        $groups[$i]['matchedBy'] = 'default';
                        continue 2;
                    }
                }
            }

            // 3. Nur unscharfer Treffer → als Hinweis anbieten, nicht zuweisen
            $groups[$i]['suggestedStyle'] = null;
            $groups[$i]['matchedBy'] = $match['matchedBy']; // stem|partial|null
            $groups[$i]['similarStyle'] = $match['slug'];
        }

        return $groups;
    }

    /**
     * AJAX: Verfügbare Styles laden
     */
    public function ajax_get_style_mappings() {
        // Sicherheitsprüfung
        check_ajax_referer('cbd_content_import', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
            return;
        }

        global $wpdb;

        // Hole alle aktiven Blöcke
        $blocks = $wpdb->get_results(
            "SELECT id, name, slug FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active' ORDER BY name ASC"
        );

        $style_options = array();

        // Standard-Voreinstellungen (User-Request: Immer Infotext K1/K2/K3)
        $suggestions = array(
            'k1' => 'infotext_k1',
            'k2' => 'infotext_k2',
            'k3' => 'infotext_k3',
            'sources' => null,
            'other' => null // wird unten auf den K1-Vorschlag gesetzt
        );

        // Validiere, ob die Standard-Styles existieren
        $available_slugs = array();
        foreach ($blocks as $block) {
            $available_slugs[] = $block->slug;
            $style_options[] = array(
                'value' => $block->slug,
                'label' => $block->name
            );
        }

        // Fallback: Wenn Standard-Styles nicht existieren, suche Alternativen
        if (!in_array('infotext_k1', $available_slugs)) {
            foreach ($blocks as $block) {
                $search_text = strtolower($block->name . ' ' . $block->slug);
                if (strpos($search_text, 'k1') !== false && !$suggestions['k1']) {
                    $suggestions['k1'] = $block->slug;
                    break;
                }
            }
        }

        if (!in_array('infotext_k2', $available_slugs)) {
            foreach ($blocks as $block) {
                $search_text = strtolower($block->name . ' ' . $block->slug);
                if (strpos($search_text, 'k2') !== false && !$suggestions['k2']) {
                    $suggestions['k2'] = $block->slug;
                    break;
                }
            }
        }

        if (!in_array('infotext_k3', $available_slugs)) {
            foreach ($blocks as $block) {
                $search_text = strtolower($block->name . ' ' . $block->slug);
                if (strpos($search_text, 'k3') !== false && !$suggestions['k3']) {
                    $suggestions['k3'] = $block->slug;
                    break;
                }
            }
        }

        // Suche nach Quellen/Literatur Keywords für sources
        foreach ($blocks as $block) {
            $search_text = strtolower($block->name . ' ' . $block->slug);
            if ((strpos($search_text, 'quellen') !== false ||
                 strpos($search_text, 'literatur') !== false ||
                 strpos($search_text, 'referenz') !== false ||
                 strpos($search_text, 'bibliographie') !== false) && !$suggestions['sources']) {
                $suggestions['sources'] = $block->slug;
                break;
            }
        }

        // Nur Slugs vorschlagen, die es wirklich gibt — sonst würde der
        // Container im Frontend als "Block nicht gefunden" rendern.
        foreach ($suggestions as $key => $slug) {
            if ($slug !== null && !in_array($slug, $available_slugs, true)) {
                $suggestions[$key] = null;
            }
        }

        // Abschnitte ohne erkennbare Kompetenzstufe ('other'): denselben Style
        // wie K1 vorschlagen (entspricht dem früheren stillen K1-Fallback),
        // im UI aber separat wählbar.
        if (empty($suggestions['other'])) {
            $suggestions['other'] = $suggestions['k1'];
        }

        wp_send_json_success(array(
            'styles' => $style_options,
            'suggestions' => $suggestions,
            // Ohne angelegte Block-Designs ist Import trotzdem möglich
            // (dann ohne Container) — das UI weist darauf hin.
            'hasStyles' => !empty($style_options)
        ));
    }

    /**
     * Hauptparser: Markdown → Strukturierte Daten
     *
     * Robust gegenüber beliebigen Markdown-Strukturen: Es geht KEIN Inhalt
     * verloren, auch wenn Überschriftenebenen fehlen (kein H1, kein H2,
     * kein H3, Text vor der ersten Überschrift). Abschnitte ohne erkennbare
     * Kompetenzstufe landen in der Kategorie 'other' und können im UI
     * gemappt oder ohne Container importiert werden.
     */
    public function parse_markdown_content($content, $known_designs = array()) {
        // Normalisiere Zeilenumbrüche
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        // Optional: YAML Front Matter entfernen
        $content = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);

        // In Zeilen aufteilen
        $lines = explode("\n", $content);

        $sections = array();
        $current_topic = null;
        $current_competence = null;   // null = noch keine H2 gesehen
        $current_heading = null;      // Überschrift des laufenden Abschnitts
        $current_title_source = null; // h3 | h2 | h1 | none
        $current_section_heading = null; // letzte H2 (bestimmt die Gruppe)
        $current_content = array();

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Überspringe horizontale Linien (---, ***, ___)
            // Diese werden oft als Separator verwendet und sollen nicht im Content erscheinen
            if (preg_match('/^([-*_]){3,}$/', $trimmed)) {
                continue;
            }

            // H1: Neues Thema
            if (preg_match('/^#\s+(.+)$/', $trimmed, $matches)) {
                $this->flush_section($sections, $current_topic, $current_competence,
                    $current_heading, $current_title_source, $current_content, $current_section_heading);

                $current_topic = trim($matches[1]);
                $current_competence = null;
                $current_section_heading = null;
                // H1 dient als Titel-Fallback, falls direkt Inhalt ohne H2/H3 folgt
                $current_heading = $current_topic;
                $current_title_source = 'h1';
                $current_content = array();
                continue;
            }

            // H2: Kompetenzstufe (oder beliebige andere Gliederungsüberschrift)
            if (preg_match('/^##\s+(.+)$/', $trimmed, $matches)) {
                $this->flush_section($sections, $current_topic, $current_competence,
                    $current_heading, $current_title_source, $current_content, $current_section_heading);

                $heading = trim($matches[1]);
                $current_competence = $this->detect_competence_level($heading, $known_designs);
                // H2 bestimmt die Gruppe — bleibt auch über folgende H3 gültig
                $current_section_heading = $heading;

                // H2 ist Titel-Fallback: greift für Quellenverzeichnisse (dort gibt
                // es kein H3) und generell für Inhalt direkt unter H2.
                $current_heading = $heading;
                $current_title_source = 'h2';
                $current_content = array();
                continue;
            }

            // H3: Block-Titel (wird NUR als Block-Titel verwendet, nicht im Inhalt)
            if (preg_match('/^###\s+(.+)$/', $trimmed, $matches)) {
                $this->flush_section($sections, $current_topic, $current_competence,
                    $current_heading, $current_title_source, $current_content, $current_section_heading);

                $current_heading = trim($matches[1]);
                $current_title_source = 'h3';
                $current_content = array();
                // H3 wird NICHT mehr zum Content hinzugefügt - nur als Block-Titel verwendet
                continue;
            }

            // Normaler Inhalt — wird IMMER gesammelt. Auch Text vor der ersten
            // Überschrift (Präambel) oder in Dateien ganz ohne Überschriften
            // ging früher verloren.
            $current_content[] = $line;
        }

        // Speichere letzten Block
        $this->flush_section($sections, $current_topic, $current_competence,
            $current_heading, $current_title_source, $current_content, $current_section_heading);

        // Nummerierte Ersatztitel für Abschnitte ohne jede Überschrift
        $untitled = 0;
        foreach ($sections as $i => $section) {
            if ($section['blockTitle'] === '') {
                $untitled++;
                $sections[$i]['blockTitle'] = sprintf(
                    /* translators: %d = laufende Nummer */
                    __('Abschnitt %d', 'container-block-designer'),
                    $untitled
                );
            }
        }

        // Gruppiere nach Kompetenzstufe für UI (Rückwärtskompatibilität)
        $grouped = array(
            'k1' => array(),
            'k2' => array(),
            'k3' => array(),
            'sources' => array(),
            'other' => array()
        );

        foreach ($sections as $section) {
            $key = isset($grouped[$section['competence']]) ? $section['competence'] : 'other';
            $grouped[$key][] = $section;
        }

        // Gruppen für die Style-Zuweisung: Kompetenzstufen + jede eigenständige
        // H2 (z. B. "Übungen", "Hinweise"). Reihenfolge = Auftreten im Dokument,
        // Kompetenzstufen aber immer zuerst.
        $groups = array();
        foreach ($sections as $section) {
            $key = $section['groupKey'];
            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'key' => $key,
                    'label' => $section['groupLabel'],
                    'competence' => $section['competence'],
                    'count' => 0,
                    // Folgt der H2 mindestens eine H3? Dann ist die H2 sicher
                    // eine Stil-Angabe und die H3 sind die Blocktitel.
                    'hasSubheadings' => false,
                    // Werden von ajax_parse_import_file() gefüllt (DB-Zugriff)
                    'suggestedStyle' => null, // nur bei exaktem Namenstreffer
                    'similarStyle' => null,   // unscharfer Treffer = Hinweis
                    'matchedBy' => null       // exact|stem|partial|default|keyword
                );
            }
            $groups[$key]['count']++;
            if (isset($section['titleSource']) && $section['titleSource'] === 'h3') {
                $groups[$key]['hasSubheadings'] = true;
            }
        }

        // Reihenfolge = Auftreten im Dokument (alle Gruppen sind gleichwertige
        // Stil-Slots), 'other' zuletzt.
        $groups = array_values($groups);
        $others = array();
        $ordered = array();
        foreach ($groups as $g) {
            if ($g['key'] === 'other') {
                $others[] = $g;
            } else {
                $ordered[] = $g;
            }
        }
        $groups = array_merge($ordered, $others);

        return array
            (
            'sections' => $sections,
            'grouped' => $grouped,
            'groups' => $groups,
            'stats' => array(
                'total' => count($sections),
                'k1' => count($grouped['k1']),
                'k2' => count($grouped['k2']),
                'k3' => count($grouped['k3']),
                'sources' => count($grouped['sources']),
                'other' => count($grouped['other'])
            )
        );
    }

    /**
     * Lädt die aktiven Block-Designs (id, name, slug).
     *
     * @return array
     */
    private function get_active_designs() {
        global $wpdb;
        $blocks = $wpdb->get_results(
            "SELECT id, name, slug FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active' ORDER BY name ASC"
        );
        return is_array($blocks) ? $blocks : array();
    }

    /**
     * Sucht zu einem Gruppen-Label (z. B. "Übungen") das passende Block-Design.
     *
     * Reihenfolge der Strategien:
     *   1. exakter Treffer auf normalisiertem Namen/Slug ("Übungen" = "uebungen")
     *   2. Stammform-Treffer (Singular/Plural: "Hinweise" ≈ "hinweis")
     *   3. Teilstring in beide Richtungen ("Übungen zum Kapitel" ⊃ "uebungen")
     *
     * @param string $label  Anzeigename der Gruppe (H2-Text)
     * @param array  $blocks Block-Zeilen mit ->name und ->slug
     * @return array{slug: string|null, matchedBy: string|null}
     */
    private function match_style_for_label($label, $blocks) {
        $needle = $this->normalize_key($label);
        if ($needle === '' || empty($blocks)) {
            return array('slug' => null, 'matchedBy' => null);
        }
        $needle_stem = $this->stem_key($needle);

        $candidates = array();
        foreach ($blocks as $block) {
            $candidates[] = array(
                'slug' => $block->slug,
                'keys' => array(
                    $this->normalize_key(isset($block->name) ? $block->name : ''),
                    $this->normalize_key($block->slug)
                )
            );
        }

        // 1. Exakt
        foreach ($candidates as $c) {
            foreach ($c['keys'] as $k) {
                if ($k !== '' && $k === $needle) {
                    return array('slug' => $c['slug'], 'matchedBy' => 'exact');
                }
            }
        }

        // 2. Stammform (Singular/Plural)
        foreach ($candidates as $c) {
            foreach ($c['keys'] as $k) {
                if ($k !== '' && $this->stem_key($k) === $needle_stem) {
                    return array('slug' => $c['slug'], 'matchedBy' => 'stem');
                }
            }
        }

        // 3. Teilstring (nur bei aussagekräftiger Länge, längster Treffer gewinnt)
        $best = null;
        $best_len = 0;
        foreach ($candidates as $c) {
            foreach ($c['keys'] as $k) {
                if (strlen($k) < 4) {
                    continue;
                }
                if (strpos($needle, $k) !== false || strpos($k, $needle) !== false) {
                    if (strlen($k) > $best_len) {
                        $best = $c['slug'];
                        $best_len = strlen($k);
                    }
                }
            }
        }
        if ($best !== null) {
            return array('slug' => $best, 'matchedBy' => 'partial');
        }

        return array('slug' => null, 'matchedBy' => null);
    }

    /**
     * Schreibt den laufenden Abschnitt in die Sections-Liste, sofern er
     * Inhalt hat. Ersetzt die früheren Vorbedingungen
     * (topic && competence && title), die Inhalt ohne vollständige
     * Überschriften-Hierarchie stillschweigend verworfen haben.
     */
    private function flush_section(&$sections, $topic, $competence, $heading, $title_source, $content, $section_heading = null) {
        // Nur Whitespace? Dann gibt es nichts zu speichern.
        if (empty($content) || trim(implode('', $content)) === '') {
            return;
        }

        $content_text = implode("\n", $content);
        $html_content = $this->markdown_to_html($content_text);
        $html_content = $this->strip_unsafe_html($html_content);

        // Ohne erkannte H2 gibt es keine Kompetenzstufe → 'other'
        $final_competence = $competence !== null ? $competence : 'other';

        // Gruppenschlüssel: JEDE H2 ist eine eigene Gruppe = ein Stil-Slot.
        // Die H2-Überschrift wird konsequent als Stil-Angabe behandelt
        // ("## infotext_k1", "## aufgaben_k1", "## Kontext" …). Die
        // Kompetenzstufe dient nur noch der Farbgebung/Statistik, NICHT der
        // Gruppierung — vorher fielen "aufgaben_k1" und "hilfen_k1" wegen des
        // Teilstrings "k1" mit "infotext_k1" in einen Topf.
        if ($section_heading !== null && trim($section_heading) !== '') {
            $normalized = $this->normalize_key($section_heading);
            $group_key = $normalized !== '' ? 'h2-' . $normalized : 'other';
            $group_label = trim($section_heading);
        } else {
            $group_key = 'other';
            $group_label = __('Ohne Überschrift', 'container-block-designer');
        }

        $sections[] = array(
            'topic' => $topic !== null ? $topic : '',
            'competence' => $final_competence,
            'blockTitle' => $heading !== null ? $heading : '',
            // Gruppe für die Style-Zuweisung im UI
            'groupKey' => $group_key,
            'groupLabel' => $group_label,
            // Metadaten für das UI: Woher kommt der Titel, war die Stufe explizit?
            'titleSource' => $title_source !== null ? $title_source : 'none',
            'hasExplicitCompetence' => ($competence !== null && $competence !== 'other'),
            'content' => $html_content
        );
    }

    /**
     * Anzeigename einer Kompetenzstufe
     */
    private function get_competence_label($competence) {
        $labels = array(
            'k1' => __('Basiswissen (K1)', 'container-block-designer'),
            'k2' => __('Erweitertes Wissen (K2)', 'container-block-designer'),
            'k3' => __('Vertiefendes Wissen (K3)', 'container-block-designer'),
            'sources' => __('Quellenangaben', 'container-block-designer')
        );
        return isset($labels[$competence]) ? $labels[$competence] : $competence;
    }

    /**
     * Erkennt Kompetenzstufe aus H2-Überschrift.
     *
     * WICHTIG (Fix v3.1.75): Ein Schlüsselwort zählt nur, wenn es das ERSTE
     * Wort der Überschrift ist bzw. die Überschrift komplett ausmacht.
     * Vorher wurde per Teilstring gesucht — dadurch landeten Überschriften wie
     * `## aufgaben_k1`, `## hilfen_k1` und `## infotext_k1` alle in der Gruppe
     * „k1" und waren nicht mehr einzeln zuweisbar.
     *
     * @param string $heading      H2-Text
     * @param array  $known_designs Aktive Block-Designs (Objekte mit name/slug).
     *                              Entspricht die Überschrift einem Design,
     *                              bleibt sie IMMER eine eigene Gruppe.
     * @return string k1|k2|k3|sources|other ('other' = keine Kompetenzstufe)
     */
    private function detect_competence_level($heading, $known_designs = array()) {
        // 1. Überschrift ist der Name/Slug eines Block-Designs
        //    (z. B. "## infotext_k1") → eigene Gruppe, nicht Kompetenzstufe.
        if ($this->heading_matches_design($heading, $known_designs)) {
            return 'other';
        }

        // 2. Kompetenz-Schlüsselwörter — nur am Wortanfang der Überschrift
        $normalized = $this->normalize_key($heading);          // "aufgaben-k1"
        $tokens = $normalized === '' ? array() : explode('-', $normalized);
        $first = isset($tokens[0]) ? $tokens[0] : '';

        foreach ($this->section_keywords as $level => $keywords) {
            foreach ($keywords as $keyword) {
                $key_norm = $this->normalize_key($keyword);    // "erweitertes-wissen"
                if ($key_norm === '') {
                    continue;
                }
                // Ganze Überschrift ist das Schlüsselwort ("Basiswissen", "K1")
                if ($normalized === $key_norm) {
                    return $level;
                }
                // Mehrwortiges Schlüsselwort am Anfang ("Erweitertes Wissen …")
                if (strpos($key_norm, '-') !== false && strpos($normalized, $key_norm . '-') === 0) {
                    return $level;
                }
                // Einwortiges Schlüsselwort als ERSTES Wort
                // ("Basiswissen (K1)" ja — "aufgaben_k1" nein)
                if (strpos($key_norm, '-') === false && $first === $key_norm) {
                    return $level;
                }
            }
        }

        // Keine Kompetenzstufe erkennbar: eigene Gruppe — im UI wird dafür
        // separat ein Style gewählt.
        return 'other';
    }

    /**
     * Prüft, ob eine Überschrift dem Namen oder Slug eines Block-Designs
     * entspricht (normalisiert). Solche Überschriften sind bewusst gesetzte
     * Style-Angaben und dürfen nie in eine Kompetenzstufe eingeordnet werden.
     *
     * @param string $heading
     * @param array  $known_designs
     * @return bool
     */
    private function heading_matches_design($heading, $known_designs) {
        if (empty($known_designs)) {
            return false;
        }
        $needle = $this->normalize_key($heading);
        if ($needle === '') {
            return false;
        }
        foreach ($known_designs as $design) {
            $name = is_object($design) ? (isset($design->name) ? $design->name : '') : (isset($design['name']) ? $design['name'] : '');
            $slug = is_object($design) ? (isset($design->slug) ? $design->slug : '') : (isset($design['slug']) ? $design['slug'] : '');
            if ($this->normalize_key($name) === $needle || $this->normalize_key($slug) === $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalisiert Text zu einem Vergleichs-Schlüssel: kleingeschrieben,
     * Umlaute/ß aufgelöst, Sonderzeichen zu Bindestrichen.
     *
     * "Übungen zum Kapitel" → "uebungen-zum-kapitel"
     *
     * @param string $text
     * @return string
     */
    private function normalize_key($text) {
        $text = trim((string) $text);

        // Umlaute und Sonderzeichen auflösen (Groß- vor Kleinschreibung)
        $map = array(
            'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'ß' => 'ss', 'É' => 'e', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'í' => 'i', 'ì' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ú' => 'u', 'ù' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        );
        $text = str_replace(array_keys($map), array_values($map), $text);

        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

        // Alles außer Buchstaben/Zahlen zu Bindestrich
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text);

        return trim($text, '-');
    }

    /**
     * Stammform für Singular/Plural-tolerante Vergleiche:
     * "hinweise" → "hinweis", "uebungen" → "uebung"
     *
     * @param string $key Bereits normalisierter Schlüssel
     * @return string
     */
    private function stem_key($key) {
        // Umlaut-Plural auflösen (Merksätze → merksaetze → merksatze):
        // die aus normalize_key() stammenden Digraphen zurückrollen, damit
        // "Merksätze" und "Merksatz" auf dieselbe Stammform fallen.
        $key = str_replace(array('ae', 'oe', 'ue'), array('a', 'o', 'u'), $key);

        // Längere Endungen zuerst prüfen
        foreach (array('nen', 'en', 'er', 'se', 'n', 'e', 's') as $suffix) {
            $len = strlen($suffix);
            if (strlen($key) > $len + 3 && substr($key, -$len) === $suffix) {
                return substr($key, 0, -$len);
            }
        }
        return $key;
    }

    /**
     * Gezielte Sicherheitsbereinigung der erzeugten HTML-Ausgabe.
     *
     * Kein wp_kses_post: das würde LaTeX-Formeln mit </> (z. B. "$a < b$")
     * zerstören. Der Markdown-Parser erzeugt selbst nur harmlose Struktur-Tags
     * (h1-h6, p, strong, em, ul/ol/li, table…), niemals <script>, Event-Handler
     * oder javascript:-URLs. Deren gezielte Entfernung ist daher verlustfrei für
     * legitime Inhalte und schließt XSS über importierte Fremd-Markdown
     * (die Ausgabe landet im Editor u. a. via innerHTML).
     *
     * @param string $html
     * @return string
     */
    private function strip_unsafe_html($html) {
        // <script>/<style>/<iframe>/<object>/<embed>-Blöcke komplett entfernen
        $html = preg_replace('#<\s*(script|style|iframe|object|embed)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
        // Selbstschließende/verwaiste gefährliche Tags
        $html = preg_replace('#<\s*/?\s*(script|style|iframe|object|embed)\b[^>]*>#i', '', $html);
        // Inline-Event-Handler (onerror=, onclick=, onload=, …)
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        // javascript:- und data:text/html-URLs in Attributen neutralisieren
        $html = preg_replace('#(href|src)\s*=\s*("|\')\s*(javascript|data)\s*:#i', '$1=$2#', $html);

        return $html;
    }

    /**
     * Konvertiert Markdown zu HTML
     */
    private function markdown_to_html($markdown) {
        // Einfacher Markdown-Parser (kann erweitert werden)
        $html = $markdown;

        // ---------------------------------------------------------------
        // SCHUTZPHASE: Bereiche sichern, in denen * _ ` KEINE Markdown-
        // Formatierung sind. Ohne das zerstörte z. B. eine Quellen-URL
        // wie ".../datei__autor__x.pdf" die Bold-Regel zu <strong> und die
        // URL war unbrauchbar. Gleiches gilt für LaTeX ($a_1 \cdot b^*$).
        // ---------------------------------------------------------------
        $protected = array();
        $protect = function ($matches) use (&$protected) {
            $token = '@@CBDPROT' . count($protected) . '@@';
            $protected[$token] = $matches[0];
            return $token;
        };

        // 1. Inline-Code `...`
        $html = preg_replace_callback('/`[^`\n]*`/', $protect, $html);
        // 2. Display-LaTeX $$...$$ (vor Inline-LaTeX!)
        $html = preg_replace_callback('/\$\$[^\$]{1,10000}?\$\$/s', $protect, $html);
        // 3. Inline-LaTeX $...$ (kein Whitespace direkt hinter/vor dem $)
        $html = preg_replace_callback('/\$(?!\s)[^\$\n]{1,500}?(?<!\s)\$/', $protect, $html);
        // 4. URLs (http/https/www) bis zum nächsten Whitespace oder ) " '
        $html = preg_replace_callback('#(?:https?://|www\.)[^\s<>"\')]+#i', $protect, $html);

        // H3-H6 (H1 und H2 sind bereits verwendet)
        $html = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);

        // Markdown-Links [Text](URL) → <a>; URLs sind bereits geschützt,
        // daher hier per Token-Auflösung im href.
        $html = preg_replace_callback(
            '/\[([^\]\n]+)\]\(\s*(\S+?)\s*\)/',
            function ($m) use (&$protected) {
                $href = isset($protected[$m[2]]) ? $protected[$m[2]] : $m[2];
                // Nur unbedenkliche Schemata verlinken
                if (!preg_match('#^(https?://|www\.|/|\#|mailto:)#i', $href)) {
                    return $m[0]; // unverändert lassen
                }
                if (stripos($href, 'www.') === 0) {
                    $href = 'https://' . $href;
                }
                return '<a href="' . esc_url($href) . '">' . $m[1] . '</a>';
            },
            $html
        );

        // Fett: **text** oder __text__
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);

        // Kursiv: *text* (nicht über Zeilengrenzen, nicht bei Listen-Markern)
        $html = preg_replace('/(?<![\*\w])\*(?!\s)([^\*\n]+?)(?<!\s)\*(?!\*)/', '<em>$1</em>', $html);
        // Achtung: _ in Tabellen oder LaTeX sollte nicht kursiv werden
        // Deaktiviert für jetzt, da _ häufig in chemischen Formeln vorkommt
        // $html = preg_replace('/_(.+?)_/', '<em>$1</em>', $html);

        // Tabellen-Verarbeitung (Markdown-Tables)
        $html = $this->convert_markdown_tables($html);

        // Listen-Verarbeitung (mit Unterstützung für mehrzeilige Items)
        $lines_array = explode("\n", $html);
        $processed_lines = array();
        $in_ul = false;
        $in_ol = false;
        $current_li_content = null;

        foreach ($lines_array as $line) {
            $trimmed = trim($line);
            $is_ul_item = preg_match('/^[\*\-]\s+(.+)$/', $line, $ul_matches);
            $is_ol_item = preg_match('/^\d+\.\s+(.+)$/', $line, $ol_matches);
            $is_empty = empty($trimmed);

            if ($is_ul_item) {
                // Schließe vorheriges Listen-Item
                if ($current_li_content !== null) {
                    $processed_lines[] = '<li>' . $current_li_content . '</li>';
                    $current_li_content = null;
                }
                // Öffne/wechsle Liste
                if (!$in_ul) {
                    $processed_lines[] = '<ul>';
                    $in_ul = true;
                }
                if ($in_ol) {
                    $processed_lines[] = '</ol>';
                    $in_ol = false;
                }
                $current_li_content = $ul_matches[1];
            } elseif ($is_ol_item) {
                // Schließe vorheriges Listen-Item
                if ($current_li_content !== null) {
                    $processed_lines[] = '<li>' . $current_li_content . '</li>';
                    $current_li_content = null;
                }
                // Öffne/wechsle Liste
                if (!$in_ol) {
                    $processed_lines[] = '<ol>';
                    $in_ol = true;
                }
                if ($in_ul) {
                    $processed_lines[] = '</ul>';
                    $in_ul = false;
                }
                $current_li_content = $ol_matches[1];
            } elseif ($is_empty) {
                // Leere Zeile: Schließe Listen-Item aber NICHT die Liste
                if ($current_li_content !== null) {
                    $processed_lines[] = '<li>' . $current_li_content . '</li>';
                    $current_li_content = null;
                }
                // Liste bleibt offen für nächstes Item
            } else {
                // Nicht-Listen-Zeile
                if ($current_li_content !== null) {
                    // Füge zu aktuellem Listen-Item hinzu (mehrzeiliger Content)
                    $current_li_content .= ' ' . $trimmed;
                } else {
                    // Schließe offene Listen
                    if ($in_ul) {
                        $processed_lines[] = '</ul>';
                        $in_ul = false;
                    }
                    if ($in_ol) {
                        $processed_lines[] = '</ol>';
                        $in_ol = false;
                    }
                    $processed_lines[] = $line;
                }
            }
        }

        // Schließe letztes Listen-Item und Listen am Ende
        if ($current_li_content !== null) {
            $processed_lines[] = '<li>' . $current_li_content . '</li>';
        }
        if ($in_ul) {
            $processed_lines[] = '</ul>';
        }
        if ($in_ol) {
            $processed_lines[] = '</ol>';
        }

        $html = implode("\n", $processed_lines);

        // Paragraphen
        $lines = explode("\n", $html);
        $paragraphs = array();
        $current_p = array();

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Überspringe HTML-Tags (inkl. schließende Tags und Tabellen)
            if (preg_match('/^<(h[1-6]|ul|ol|li|table|thead|tbody|tr|th|td|\/ul|\/ol|\/li|\/table|\/thead|\/tbody|\/tr|\/th|\/td)/', $trimmed)) {
                if (!empty($current_p)) {
                    $paragraphs[] = '<p>' . implode(' ', $current_p) . '</p>';
                    $current_p = array();
                }
                $paragraphs[] = $line;
            } elseif (empty($trimmed)) {
                if (!empty($current_p)) {
                    $paragraphs[] = '<p>' . implode(' ', $current_p) . '</p>';
                    $current_p = array();
                }
            } else {
                $current_p[] = $trimmed;
            }
        }

        if (!empty($current_p)) {
            $paragraphs[] = '<p>' . implode(' ', $current_p) . '</p>';
        }

        $html = implode("\n", $paragraphs);

        // ---------------------------------------------------------------
        // WIEDERHERSTELLUNG der geschützten Bereiche (Inline-Code, LaTeX,
        // URLs). LaTeX-Formeln bleiben damit unverändert ($...$ und $$...$$),
        // KaTeX rendert sie später im Frontend.
        // ---------------------------------------------------------------
        if (!empty($protected)) {
            $html = str_replace(array_keys($protected), array_values($protected), $html);
        }

        return $html;
    }

    /**
     * Konvertiert Markdown-Tabellen zu HTML
     */
    private function convert_markdown_tables($content) {
        $lines = explode("\n", $content);
        $result = array();
        $in_table = false;
        $table_lines = array();

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Erkenne Tabellen-Zeilen (beginnen mit | und enthalten mindestens ein weiteres |)
            // Robuster: Akzeptiert auch Zeilen ohne trailing | und mit Whitespace
            if (preg_match('/^\|.+\|/', $trimmed)) {
                $in_table = true;
                $table_lines[] = $trimmed; // Verwende trimmed statt line
            } else {
                // Nicht-Tabellen-Zeile
                if ($in_table) {
                    // Konvertiere gesammelte Tabelle
                    $result[] = $this->build_html_table($table_lines);
                    $table_lines = array();
                    $in_table = false;
                }
                $result[] = $line;
            }
        }

        // Letzte Tabelle, falls am Ende
        if ($in_table && !empty($table_lines)) {
            $result[] = $this->build_html_table($table_lines);
        }

        return implode("\n", $result);
    }

    /**
     * Baut HTML-Tabelle aus Markdown-Zeilen
     */
    private function build_html_table($lines) {
        if (empty($lines)) {
            return '';
        }

        $rows = array();
        $header_row = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Überspringe Separator-Zeile (|---|---|)
            if (preg_match('/^\|[\s\-:|]+\|$/', $trimmed)) {
                continue;
            }

            // Parse Zellen
            $cells = explode('|', $trimmed);
            array_shift($cells); // Erstes |
            array_pop($cells);   // Letztes |
            $cells = array_map('trim', $cells);

            if ($header_row === null) {
                $header_row = $cells;
            } else {
                $rows[] = $cells;
            }
        }

        // Erstelle HTML-Tabelle (wird von JavaScript zu Gutenberg-Block konvertiert)
        $html = array();
        $html[] = '<table>';

        // Header
        $html[] = '<thead>';
        $html[] = '<tr>';
        foreach ($header_row as $cell) {
            $html[] = '<th>' . $cell . '</th>';
        }
        $html[] = '</tr>';
        $html[] = '</thead>';

        // Body
        $html[] = '<tbody>';
        foreach ($rows as $row) {
            $html[] = '<tr>';
            foreach ($row as $cell) {
                $html[] = '<td>' . $cell . '</td>';
            }
            $html[] = '</tr>';
        }
        $html[] = '</tbody>';

        $html[] = '</table>';

        return implode("\n", $html);
    }
}

// Initialisierung
CBD_Content_Importer::get_instance();
