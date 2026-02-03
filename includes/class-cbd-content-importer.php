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
            $parsed_data = $this->parse_markdown_content($content);
            wp_send_json_success($parsed_data);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
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
            'sources' => null
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

        wp_send_json_success(array(
            'styles' => $style_options,
            'suggestions' => $suggestions
        ));
    }

    /**
     * Hauptparser: Markdown → Strukturierte Daten
     */
    public function parse_markdown_content($content) {
        // Normalisiere Zeilenumbrüche
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        // Optional: YAML Front Matter entfernen
        $content = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);

        // In Zeilen aufteilen
        $lines = explode("\n", $content);

        $sections = array();
        $current_topic = null;
        $current_competence = null;
        $current_block_title = null;
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
                // Speichere vorherigen Block
                if ($current_topic && $current_competence && $current_block_title) {
                    $this->save_block($sections, $current_topic, $current_competence, $current_block_title, $current_content);
                }

                $current_topic = trim($matches[1]);
                $current_competence = null;
                $current_block_title = null;
                $current_content = array();
                continue;
            }

            // H2: Kompetenzstufe
            if (preg_match('/^##\s+(.+)$/', $trimmed, $matches)) {
                // Speichere vorherigen Block
                if ($current_topic && $current_competence && $current_block_title) {
                    $this->save_block($sections, $current_topic, $current_competence, $current_block_title, $current_content);
                }

                $heading = trim($matches[1]);
                $current_competence = $this->detect_competence_level($heading);

                // SPECIAL: Für Quellenverzeichnis ist H2 der Block-Titel (kein H3 erforderlich)
                if ($current_competence === 'sources') {
                    $current_block_title = $heading;
                } else {
                    $current_block_title = null;
                }

                $current_content = array();
                continue;
            }

            // H3: Block-Titel (wird NUR als Block-Titel verwendet, nicht im Inhalt)
            if (preg_match('/^###\s+(.+)$/', $trimmed, $matches)) {
                // Speichere vorherigen Block
                if ($current_topic && $current_competence && $current_block_title) {
                    $this->save_block($sections, $current_topic, $current_competence, $current_block_title, $current_content);
                }

                $current_block_title = trim($matches[1]);
                $current_content = array();
                // H3 wird NICHT mehr zum Content hinzugefügt - nur als Block-Titel verwendet
                continue;
            }

            // Normaler Inhalt
            if ($current_topic && $current_competence) {
                $current_content[] = $line;
            }
        }

        // Speichere letzten Block
        if ($current_topic && $current_competence && $current_block_title) {
            $this->save_block($sections, $current_topic, $current_competence, $current_block_title, $current_content);
        }

        // Gruppiere nach Kompetenzstufe für UI
        $grouped = array(
            'k1' => array(),
            'k2' => array(),
            'k3' => array(),
            'sources' => array()
        );

        foreach ($sections as $section) {
            $grouped[$section['competence']][] = $section;
        }

        return array(
            'sections' => $sections,
            'grouped' => $grouped,
            'stats' => array(
                'total' => count($sections),
                'k1' => count($grouped['k1']),
                'k2' => count($grouped['k2']),
                'k3' => count($grouped['k3']),
                'sources' => count($grouped['sources'])
            )
        );
    }

    /**
     * Speichert einen Block in der Sections-Liste
     */
    private function save_block(&$sections, $topic, $competence, $block_title, $content) {
        if (empty($content)) {
            return;
        }

        $content_text = implode("\n", $content);
        $html_content = $this->markdown_to_html($content_text);

        // NEU: Block-Titel ist immer die H3-Überschrift (oder H2 bei Quellenverzeichnis)
        // H1-Thema wird nicht mehr als Block-Titel verwendet
        $final_block_title = $block_title;

        $sections[] = array(
            'topic' => $topic,
            'competence' => $competence,
            'blockTitle' => $final_block_title,
            'content' => $html_content
        );
    }

    /**
     * Erkennt Kompetenzstufe aus H2-Überschrift
     */
    private function detect_competence_level($heading) {
        $heading_lower = strtolower(trim($heading));

        foreach ($this->section_keywords as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($heading_lower, $keyword) !== false) {
                    return $level;
                }
            }
        }

        // Fallback: K1
        return 'k1';
    }

    /**
     * Konvertiert Markdown zu HTML
     */
    private function markdown_to_html($markdown) {
        // Einfacher Markdown-Parser (kann erweitert werden)
        $html = $markdown;

        // H3-H6 (H1 und H2 sind bereits verwendet)
        $html = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);

        // Fett: **text** oder __text__
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);

        // Kursiv: *text* oder _text_ (ABER NICHT in LaTeX-Formeln oder Tabellen)
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
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

        // LaTeX-Formeln bleiben unverändert ($...$ und $$...$$)
        // KaTeX wird später im Frontend gerendert

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
