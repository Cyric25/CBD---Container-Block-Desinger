<?php
/**
 * LaTeX Formula Parser
 *
 * Parses LaTeX formulas from content and converts them to KaTeX-rendered HTML
 * Supports both $$formula$$ and [latex]formula[/latex] syntax
 *
 * @package ContainerBlockDesigner
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CBD_LaTeX_Parser {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Counter for unique formula IDs
     */
    private $formula_counter = 0;

    /**
     * Store parsed formulas for PDF export
     */
    private $formulas = array();

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_katex'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_katex'));

        // GLOBAL BLOCK FILTER:Parse LaTeX in ALL WordPress blocks
        // This filter runs for every block before rendering (Gutenberg blocks)
        // Priority 5: Run BEFORE wpautop and other text formatting filters
        add_filter('render_block', array($this, 'parse_latex_in_blocks'), 5, 2);

        // Rückfall für klassische Inhalte (kein Blockmarkup).
        //
        // Priorität 11 – also NACH do_blocks() (Kernpriorität 9). Auf der
        // früheren Priorität 5 sah dieser Filter rohes Blockmarkup samt
        // `<!-- wp:… -->`-Kommentaren und veränderte es, obwohl der Filter
        // `render_block` dieselbe Arbeit bereits je Block leistet. Der
        // Doppelparse-Schutz in parse_latex() (Prüfung auf
        // `cbd-latex-formula`) sorgt dafür, dass bereits gerenderte Inhalte
        // hier unangetastet durchlaufen.
        //
        // Folge des späteren Zeitpunkts: wptexturize() und wpautop()
        // (beide Priorität 10) haben den Text dann schon angefasst. Deshalb
        // dreht normalize_formula_text() deren Spuren innerhalb einer Formel
        // wieder zurück.
        add_filter('the_content', array($this, 'parse_latex'), 11);
    }

    /**
     * Prüft, ob KaTeX auf der aktuellen Seite überhaupt benötigt wird.
     * Verhindert das Laden von ~350 KB auf Seiten ohne Formeln.
     *
     * @return bool
     */
    private function should_load_katex() {
        // Admin: nur im Block-Editor (dort wird die Live-Vorschau gerendert)
        if (is_admin()) {
            if (!function_exists('get_current_screen')) {
                return false;
            }
            $screen = get_current_screen();
            return $screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor();
        }

        // Listen-Ansichten (Startseite, Archive) zeigen viele Beiträge; deren
        // Inhalte lassen sich hier nicht einzeln prüfen -> konservativ laden.
        if (is_home() || is_archive()) {
            return true;
        }

        // Beitrags-ID robust ermitteln: get_queried_object_id() ist zur
        // wp_enqueue_scripts-Zeit zuverlässiger als das globale $post, das je
        // nach Theme noch nicht gesetzt ist. Gleiche Begründung wie in
        // CBD_Block_Registration::frontend_has_container_block().
        $post = null;
        $post_id = get_queried_object_id();
        if ($post_id > 0 && get_queried_object() instanceof WP_Post) {
            // Nur wenn das abgefragte Objekt wirklich ein Beitrag ist – auf
            // Term-Archiven liefert get_queried_object_id() eine Term-ID, die
            // als Beitrags-ID einen fremden Beitrag treffen könnte.
            $candidate = get_post($post_id);
            if ($candidate instanceof WP_Post) {
                $post = $candidate;
            }
        }
        if (!($post instanceof WP_Post)) {
            $post = get_post(); // Rückfall: globales $post
        }

        if ($post instanceof WP_Post) {
            return self::content_has_latex_markers($post->post_content);
        }

        // Keine Beitrags-ID ermittelbar (z. B. Template-Teil ohne Loop):
        // im Zweifel auf Einzelseiten laden.
        return is_singular();
    }

    /**
     * Enthält der Text überhaupt einen LaTeX-Marker?
     *
     * Einzige Stelle, an der die erkannten Delimiter für das Lade-Gate
     * aufgezählt werden – sie muss mit den Mustern in parse_latex()
     * übereinstimmen.
     *
     * @param string $content
     * @return bool
     */
    public static function content_has_latex_markers($content) {
        if (!is_string($content) || '' === $content) {
            return false;
        }

        return strpos($content, '$') !== false
            || strpos($content, '[latex]') !== false
            || strpos($content, '\\(') !== false
            || strpos($content, '\\[') !== false
            // Wiederverwendbare Blöcke können Formeln enthalten, deren
            // Inhalt hier nicht sichtbar ist – konservativ laden.
            || strpos($content, '<!-- wp:block ') !== false;
    }

    /**
     * Enqueue KaTeX library
     */
    public function enqueue_katex() {
        if (!$this->should_load_katex()) {
            return;
        }

        // KaTeX - lokal gebündelt (DSGVO, AP23). Der fonts/-Ordner liegt
        // relativ neben katex.min.css und wird von dort referenziert.
        wp_enqueue_style(
            'katex',
            CBD_PLUGIN_URL . 'assets/vendor/katex/katex.min.css',
            array(),
            '0.16.9'
        );

        // KaTeX JS
        wp_enqueue_script(
            'katex',
            CBD_PLUGIN_URL . 'assets/vendor/katex/katex.min.js',
            array(),
            '0.16.9',
            true
        );

        // KaTeX Auto-render extension
        wp_enqueue_script(
            'katex-auto-render',
            CBD_PLUGIN_URL . 'assets/vendor/katex/contrib/auto-render.min.js',
            array('katex'),
            '0.16.9',
            true
        );

        // Custom LaTeX CSS
        wp_enqueue_style(
            'cbd-latex',
            CBD_PLUGIN_URL . 'assets/css/latex-formulas.css',
            array('katex'),
            CBD_VERSION
        );

        // Custom LaTeX JS
        wp_enqueue_script(
            'cbd-latex',
            CBD_PLUGIN_URL . 'assets/js/latex-renderer.js',
            array('katex', 'katex-auto-render'),
            CBD_VERSION,
            true
        );
    }

    /**
     * Parse LaTeX formulas in ALL WordPress blocks (Gutenberg)
     *
     * This filter is called for EVERY block before it's rendered,
     * making LaTeX parsing available in ALL blocks (Paragraph, Heading,
     * Custom HTML, Container Blocks, etc.)
     *
     * @param string $block_content The block content about to be rendered
     * @param array $block The full block, including name and attributes
     * @return string Parsed block content with LaTeX converted to HTML
     */
    public function parse_latex_in_blocks($block_content, $block) {
        // Skip empty blocks
        if (empty($block_content)) {
            return $block_content;
        }

        // Performance: Skip if no LaTeX marker present at all
        if (!self::content_has_latex_markers($block_content)) {
            return $block_content;
        }

        // Performance: Skip very large blocks (>100KB) to prevent regex timeout
        if (strlen($block_content) > 102400) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CBD LaTeX Parser] Skipping large block (>100KB) to prevent timeout');
            }
            return $block_content;
        }

        // Validation: Check for balanced $ signs
        $dollar_count = substr_count($block_content, '$');
        if ($dollar_count > 0 && $dollar_count % 2 !== 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CBD LaTeX Parser] Unbalanced $ signs detected in block (' . $dollar_count . ' total). Skipping LaTeX parsing to prevent regex issues.');
            }
            // Add visual warning for incomplete formulas (red box)
            $warning = '<div class="cbd-latex-warning" style="background: #fee; border-left: 4px solid #dc3545; padding: 12px; margin: 10px 0; color: #721c24;">'
                     . '<strong>⚠️ Unvollständige LaTeX-Formel erkannt</strong><br>'
                     . 'Dieser Block enthält ' . $dollar_count . ' $ Zeichen (muss gerade Anzahl sein). '
                     . 'Bitte prüfen Sie, ob alle Formeln korrekt mit $ oder $$ umschlossen sind.'
                     . '</div>';

            // Highlight incomplete $ signs in red
            // Find $ that are not part of $$
            $highlighted_content = preg_replace(
                '/(?<!\$)\$(?!\$)/',
                '<span style="background: #dc3545; color: white; padding: 2px 4px; font-weight: bold; border-radius: 2px;">$</span>',
                $block_content
            );

            // Return block content with warning at the top and highlighted $ signs
            return $warning . $highlighted_content;
        }

        // Parse LaTeX in this block's content
        return $this->parse_latex($block_content);
    }

    /**
     * Parse LaTeX formulas from content
     *
     * Supports:
     * - $$formula$$ syntax (display math - block level, centered)
     * - $formula$ syntax (inline math - within text flow)
     * - [latex]formula[/latex] shortcode syntax
     *
     * @param string $content Content to parse
     * @return string Parsed content with LaTeX converted to HTML
     */
    public function parse_latex($content) {
        if (empty($content) || !is_string($content)) {
            return $content;
        }

        // Check if content was already parsed (prevent double parsing)
        if (strpos($content, 'cbd-latex-formula') !== false) {
            return $content;
        }

        // Set PCRE limits to prevent catastrophic backtracking
        @ini_set('pcre.backtrack_limit', '1000000');
        @ini_set('pcre.recursion_limit', '100000');

        // Error handling: Catch preg_replace_callback failures
        try {
            // CONSERVATIVE: Only decode specific HTML entities, NOT all
            // Be careful with backslashes - WordPress might strip them
            $content = str_replace('&bsol;', '\\', $content);
            $content = str_replace('&#92;', '\\', $content);

        // Fix corrupted LaTeX formulas where underscores were converted to <em> tags
        // ONLY within existing dollar signs - don't auto-wrap

        // Pattern 1: Remove <em> tags within dollar signs
        $content = preg_replace_callback(
            '/(\$[^$]*?)<em>([^<]+?)<\/em>([^$]*?\$)/i',
            function($matches) {
                return $matches[1] . '_' . $matches[2] . '_' . $matches[3];
            },
            $content
        );

        // Pattern 2: REMOVED - too aggressive, don't auto-wrap
        // Let user wrap their own formulas in $ signs

        // Reset counter for each content block
        $this->formula_counter = 0;

        // WICHTIG: Parse $$formula$$ ZUERST (display math)
        // Dies muss vor $formula$ geparst werden, damit $$ nicht als zwei $ interpretiert wird
        // Temporär ersetzen mit Platzhalter um Konflikte zu vermeiden
        $display_formulas = array();
        $display_counter = 0;

        // OPTIMIZED: Use [^\$] instead of . to prevent catastrophic backtracking
        // Match anything except $ sign, up to 10000 chars per formula
        $content = preg_replace_callback(
            '/\$\$([^\$]{1,10000}?)\$\$/s',
            function($matches) use (&$display_formulas, &$display_counter) {
                $placeholder = '___CBD_DISPLAY_FORMULA_' . $display_counter . '___';
                $display_formulas[$placeholder] = $this->render_display_formula($matches);
                $display_counter++;
                return $placeholder;
            },
            $content,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL
        );

        // Parse [latex]formula[/latex] shortcode syntax (display math)
        // OPTIMIZED: Limit length and use atomic grouping
        $content = preg_replace_callback(
            '/\[latex\]([^\]]{1,10000}?)\[\/latex\]/si',
            function($matches) use (&$display_formulas, &$display_counter) {
                $placeholder = '___CBD_DISPLAY_FORMULA_' . $display_counter . '___';
                $display_formulas[$placeholder] = $this->render_display_formula($matches);
                $display_counter++;
                return $placeholder;
            },
            $content,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL
        );

        // Parse \[formula\] syntax (display math, KaTeX-/MathJax-Konvention).
        // Muss wie $$…$$ VOR den Inline-Mustern laufen und wird ebenfalls
        // über Platzhalter geschützt.
        $content = preg_replace_callback(
            // {0,…} statt {1,…}: Sonst könnte der lazy Quantor den Backslash
            // eines unmittelbar folgenden \] verschlucken und über die
            // nächste Formel hinweggreifen. Der leere Treffer wird unten
            // verworfen.
            '/\\\\\[(.{0,10000}?)\\\\\]/s',
            function($matches) use (&$display_formulas, &$display_counter) {
                if (trim($matches[1]) === '') {
                    return $matches[0]; // leere Klammer ist keine Formel
                }
                $placeholder = '___CBD_DISPLAY_FORMULA_' . $display_counter . '___';
                $display_formulas[$placeholder] = $this->render_display_formula($matches);
                $display_counter++;
                return $placeholder;
            },
            $content,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL
        );

        // Parse \(formula\) syntax (inline math, KaTeX-/MathJax-Konvention).
        // Läuft VOR dem $…$-Muster und legt das Ergebnis in einem Platzhalter
        // ab: Enthielte eine so ausgezeichnete Formel ein $, würde der
        // nachfolgende $…$-Durchlauf sonst in das erzeugte Markup schneiden.
        // Die Whitespace-Regel von $…$ gilt hier bewusst NICHT – \( … \) ist
        // eindeutig, "\( x \)" ist gültiges LaTeX.
        $content = preg_replace_callback(
            // {0,…} aus demselben Grund wie bei \[…\] oben.
            '/\\\\\((.{0,500}?)\\\\\)/s',
            function($matches) use (&$display_formulas, &$display_counter) {
                if (trim($matches[1]) === '') {
                    return $matches[0]; // leere Klammer ist keine Formel
                }
                $placeholder = '___CBD_INLINE_FORMULA_' . $display_counter . '___';
                $display_formulas[$placeholder] = $this->build_inline_formula($matches[1]);
                $display_counter++;
                return $placeholder;
            },
            $content,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL
        );

        // Parse $formula$ syntax (inline math) - nun ohne $$ Konflikte
        // OPTIMIZED: Limit to reasonable formula length (500 chars for inline) and prevent backtracking
        // ROBUST: Inline formulas should be SHORT - most are < 100 chars. 500 is very generous.
        // This prevents matching across large text sections when $ is unbalanced
        $content = preg_replace_callback(
            '/\$([^\$]{1,500}?)\$/s',
            array($this, 'render_inline_formula'),
            $content,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL
        );

            // Platzhalter zurück durch gerenderte Formeln ersetzen
            foreach ($display_formulas as $placeholder => $formula_html) {
                $content = str_replace($placeholder, $formula_html, $content);
            }

        } catch (\Throwable $e) {
            // Throwable statt Exception: fängt auch PHP-Errors (TypeError etc.)
            // ab, damit eine kaputte Formel nicht die ganze Seite killt.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CBD LaTeX Parser] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            // Return original content if parsing fails
            return $content;
        }

        // Check for PREG errors
        $preg_error = preg_last_error();
        if ($preg_error !== PREG_NO_ERROR) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_messages = array(
                    PREG_INTERNAL_ERROR => 'Internal PCRE error',
                    PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit exhausted',
                    PREG_RECURSION_LIMIT_ERROR => 'Recursion limit exhausted',
                    PREG_BAD_UTF8_ERROR => 'Bad UTF8 data',
                    PREG_BAD_UTF8_OFFSET_ERROR => 'Bad UTF8 offset'
                );
                $error_msg = isset($error_messages[$preg_error]) ? $error_messages[$preg_error] : 'Unknown PREG error';
                error_log('[CBD LaTeX Parser] PREG Error: ' . $error_msg);
            }
        }

        return $content;
    }

    /**
     * Render display formula (centered, block-level)
     *
     * @param array $matches Regex matches
     * @return string Rendered HTML
     */
    private function render_display_formula($matches) {
        $formula = trim($this->normalize_formula_text($matches[1]));
        $this->formula_counter++;

        $formula_id = 'cbd-latex-' . uniqid() . '-' . $this->formula_counter;

        // Store formula for potential PDF export
        $this->formulas[$formula_id] = $formula;

        // Return HTML structure for KaTeX rendering
        // The inner span is empty - KaTeX will fill it with rendered content.
        //
        // ÄUSSERES ELEMENT IST EIN <span>, KEIN <div> (AP-1.1):
        // Ein <div> innerhalb eines <p> zwingt den HTML-Parser des Browsers,
        // den Absatz aufzuspalten. Dabei entstehen nackte Textknoten neben
        // dem Absatz, die z. B. der Accordion-Block nicht mehr in seine
        // Klappzeile verschieben kann – sie bleiben sichtbar daneben stehen.
        // Die Blockdarstellung leistet `display:block` in latex-formulas.css
        // (.cbd-latex-formula.cbd-latex-display) genauso.
        $html = sprintf(
            '<span class="cbd-latex-formula cbd-latex-display" id="%s" data-latex="%s" data-formula-id="%s"><span class="cbd-latex-content"></span></span>',
            esc_attr($formula_id),
            esc_attr($formula),
            esc_attr($formula_id)
        );

        return $html;
    }

    /**
     * Entfernt Spuren, die WordPress-Textfilter im Formeltext hinterlassen.
     *
     * Nötig, seit der `the_content`-Filter auf Priorität 11 läuft (AP-1.1):
     * wpautop() und wptexturize() (beide Priorität 10) haben den klassischen
     * Inhalt dann bereits angefasst. In Blockinhalten steht aus demselben
     * Grund `<br>` in Absätzen mit weichen Zeilenumbrüchen.
     *
     * Innerhalb einer Formel hat keines dieser Zeichen eine legitime
     * Bedeutung – KaTeX bekäme sie sonst als Rohtext und würde die Formel
     * rot als Fehler anzeigen.
     *
     * @param string $formula Roher Formeltext aus dem Regex-Treffer
     * @return string
     */
    private function normalize_formula_text($formula) {
        if (!is_string($formula) || '' === $formula) {
            return $formula;
        }

        // wpautop(): weiche Zeilenumbrüche. Das zugehörige \n bleibt stehen,
        // der ursprüngliche Text ist damit wiederhergestellt.
        $stripped = preg_replace('#<br\s*/?>#i', '', $formula);
        if (null !== $stripped) {
            $formula = $stripped;
        }

        // wptexturize(): typografische Ersetzungen zurücknehmen.
        return strtr($formula, array(
            '&#8216;' => "'",
            '&#8217;' => "'",
            '&#8220;' => '"',
            '&#8221;' => '"',
            '&#8211;' => '--',
            '&#8212;' => '---',
            '&#8230;' => '...',
            '&#215;'  => 'x',
        ));
    }

    /**
     * Render inline formula (within text flow)
     *
     * @param array $matches Regex matches
     * @return string Rendered HTML
     */
    private function render_inline_formula($matches) {
        // KaTeX-/Pandoc-Konvention (AP26): direkt nach dem öffnenden $ und
        // direkt vor dem schließenden $ darf KEIN Whitespace stehen.
        // Verhindert False Positives wie "Kosten: $5 bis $10" — echte
        // Formeln ($x^2$, $a + b$) sind nicht betroffen.
        if ($matches[1] === '' || preg_match('/^\s|\s$/u', $matches[1])) {
            return $matches[0]; // unverändert lassen
        }

        return $this->build_inline_formula($matches[1]);
    }

    /**
     * Baut das Markup einer Inline-Formel.
     *
     * Gemeinsamer Kern von `$…$` (render_inline_formula(), mit
     * Whitespace-Regel) und `\(…\)` (ohne, weil der Delimiter eindeutig ist).
     *
     * @param string $raw_formula Formeltext ohne Delimiter
     * @return string Markup
     */
    private function build_inline_formula($raw_formula) {
        $formula = trim($this->normalize_formula_text($raw_formula));
        $this->formula_counter++;

        $formula_id = 'cbd-latex-inline-' . uniqid() . '-' . $this->formula_counter;

        // Store formula for potential PDF export
        $this->formulas[$formula_id] = $formula;

        // Return HTML structure for inline KaTeX rendering
        // Uses span instead of div to stay within text flow
        $html = sprintf(
            '<span class="cbd-latex-formula cbd-latex-inline" id="%s" data-latex="%s" data-formula-id="%s"><span class="cbd-latex-content"></span></span>',
            esc_attr($formula_id),
            esc_attr($formula),
            esc_attr($formula_id)
        );

        return $html;
    }

    /**
     * Get all parsed formulas (for PDF export)
     *
     * @return array Array of formula_id => latex_code
     */
    public function get_formulas() {
        return $this->formulas;
    }

    /**
     * Render formula to SVG for PDF export
     *
     * @param string $latex LaTeX formula code
     * @return string SVG markup or fallback
     */
    public function render_to_svg($latex) {
        // This will be handled by JavaScript in the browser
        // For server-side PDF generation, we'll use KaTeX's server-side rendering

        // For now, return a placeholder that JavaScript will replace
        return '<div class="cbd-latex-pdf" data-latex="' . esc_attr($latex) . '"></div>';
    }
}