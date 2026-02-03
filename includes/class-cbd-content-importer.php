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

        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

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
        $suggestions = array('k1' => null, 'k2' => null, 'k3' => null, 'sources' => null);

        foreach ($blocks as $block) {
            $style_options[] = array(
                'value' => $block->slug,
                'label' => $block->name
            );

            // Auto-Suggest: Suche nach k1, k2, k3, quellen in Slug oder Name
            $search_text = strtolower($block->name . ' ' . $block->slug);
            if (strpos($search_text, 'k1') !== false && !$suggestions['k1']) {
                $suggestions['k1'] = $block->slug;
            }
            if (strpos($search_text, 'k2') !== false && !$suggestions['k2']) {
                $suggestions['k2'] = $block->slug;
            }
            if (strpos($search_text, 'k3') !== false && !$suggestions['k3']) {
                $suggestions['k3'] = $block->slug;
            }
            // Suche nach Quellen/Literatur Keywords
            if ((strpos($search_text, 'quellen') !== false ||
                 strpos($search_text, 'literatur') !== false ||
                 strpos($search_text, 'referenz') !== false ||
                 strpos($search_text, 'bibliographie') !== false) && !$suggestions['sources']) {
                $suggestions['sources'] = $block->slug;
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

        // Kursiv: *text* oder _text_
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/_(.+?)_/', '<em>$1</em>', $html);

        // Ungeordnete Listen
        $html = preg_replace_callback('/^[\*\-]\s+(.+)$/m', function($matches) {
            return '<li>' . $matches[1] . '</li>';
        }, $html);
        $html = preg_replace('/<li>/', '<ul><li>', $html, 1);
        $html = preg_replace('/(<li>.*<\/li>)\n(?!<li>)/s', '$1</ul>', $html);

        // Geordnete Listen
        $html = preg_replace_callback('/^\d+\.\s+(.+)$/m', function($matches) {
            return '<li>' . $matches[1] . '</li>';
        }, $html);
        $html = preg_replace('/<li>/', '<ol><li>', $html, 1);
        $html = preg_replace('/(<li>.*<\/li>)\n(?!<li>)/s', '$1</ol>', $html);

        // Paragraphen
        $lines = explode("\n", $html);
        $paragraphs = array();
        $current_p = array();

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Überspringe HTML-Tags
            if (preg_match('/^<(h[1-6]|ul|ol|li|\/ul|\/ol)/', $trimmed)) {
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
}

// Initialisierung
CBD_Content_Importer::get_instance();
