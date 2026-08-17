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
     * Blocktypen, deren Inhalt niemals als LaTeX gelesen wird (AP-1.fix2).
     *
     * In diesen Blöcken sind `\(` und `\[` gewöhnliche Zeichen – in
     * JavaScript-Regexen (`/\(([^)]+)\)/g`) sind sie alltäglich. Seit AP-1.1
     * die Delimiter `\(…\)` und `\[…\]` erkennt, hätte der Parser dort jedes
     * Skript und jedes Codebeispiel still zerschossen.
     *
     * Formeln gehen dadurch nicht verloren: Der gerenderte Inhalt läuft
     * anschließend ohnehin durch `the_content` (Priorität 11).
     *
     * WICHTIG: Ein Blockname `null` (Inhalt ohne Blockmarkup, klassischer
     * Editor) steht bewusst NICHT in dieser Liste und wird durch den
     * strikten Vergleich in `parse_latex_in_blocks()` auch nicht getroffen.
     */
    private const KEIN_LATEX_BLOCK = array(
        'core/html',
        'core/code',
        'core/preformatted',
        'core/freeform',
    );

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

        // Blocktypen ohne LaTeX-Deutung überspringen (AP-1.fix2, M1).
        // Strikter Vergleich: `null` (Freiform ohne Blockmarkup) trifft
        // keinen Eintrag und läuft weiter durch den Parser.
        if (is_array($block)
            && isset($block['blockName'])
            && in_array($block['blockName'], self::KEIN_LATEX_BLOCK, true)) {
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
        //
        // N1 (AP-1.fix5): Gezählt wird auf dem MASKIERTEN Text, nicht auf dem
        // Rohtext. Ein gewöhnliches jQuery-Skript
        // (`jQuery(function($){ … $(".x") … $(".y") … })`) bringt sonst eine
        // ungerade $-Bilanz zustande und bekommt eine rote Warnbox plus rot
        // hinterlegte <span> mitten hineingeschrieben — das Skript ist danach
        // kaputt. Der Blocknamen-Filter oben hilft hier nicht: Container-Blöcke
        // stehen bewusst nicht in KEIN_LATEX_BLOCK, und `isolate_inline_scripts()`
        // zeigt, dass Skripte in Blockinhalten gelebtes Muster sind.
        //
        // Die Warnung selbst bleibt erhalten — sie bezieht sich jetzt nur noch
        // auf $ ausserhalb von script/pre/code.
        $zaehl_speicher = array();
        $zaehl_counter  = 0;
        $ohne_geschuetztes = $this->mask_protected_regions(
            $block_content,
            $zaehl_speicher,
            $zaehl_counter,
            uniqid()
        );

        $dollar_count = substr_count($ohne_geschuetztes, '$');
        if ($dollar_count > 0 && $dollar_count % 2 !== 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CBD LaTeX Parser] Unbalanced $ signs detected in block (' . $dollar_count . ' total, ohne script/pre/code). Skipping LaTeX parsing to prevent regex issues.');
            }
            // Add visual warning for incomplete formulas (red box)
            $warning = '<div class="cbd-latex-warning" style="background: #fee; border-left: 4px solid #dc3545; padding: 12px; margin: 10px 0; color: #721c24;">'
                     . '<strong>⚠️ Unvollständige LaTeX-Formel erkannt</strong><br>'
                     . 'Dieser Block enthält ' . $dollar_count . ' $ Zeichen (muss gerade Anzahl sein). '
                     . 'Bitte prüfen Sie, ob alle Formeln korrekt mit $ oder $$ umschlossen sind.'
                     . '</div>';

            // Highlight incomplete $ signs in red
            // Find $ that are not part of $$
            // Läuft ebenfalls auf dem maskierten Text: Ein $ im Skript darf
            // keine rote Markierung bekommen.
            $highlighted_content = preg_replace(
                '/(?<!\$)\$(?!\$)/',
                '<span style="background: #dc3545; color: white; padding: 2px 4px; font-weight: bold; border-radius: 2px;">$</span>',
                $ohne_geschuetztes
            );

            if (null === $highlighted_content) {
                // PCRE-Fehler sofort auswerten (N4) und ungehighlightet
                // weiterarbeiten, statt den Blockinhalt zu verlieren.
                $this->log_preg_error('parse_latex_in_blocks(), $-Hervorhebung');
                $highlighted_content = $ohne_geschuetztes;
            }

            // Return block content with warning at the top and highlighted $ signs.
            // Die maskierten Bereiche kommen dabei unverändert zurück.
            return $warning . $this->restore_placeholders($highlighted_content, $zaehl_speicher);
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

        // EIN Platzhalter-Speicher für alles, was vor den Delimiter-Mustern
        // aus dem Text genommen wird: die geschützten Bereiche
        // (script/pre/code) und die bereits gerenderten Formeln. Ein einziger
        // Rücktausch am Ende – siehe restore_placeholders().
        $display_formulas = array();
        $display_counter = 0;
        $protected_counter = 0;

        // N3 (AP-1.fix5): Die Marken bekommen je Aufruf ein zufälliges Stück.
        // Mit festen Marken ersetzte der Rücktausch auch einen Nutzertext, der
        // zufällig `___CBD_PROTECTED_0___` bzw. `___CBD_DISPLAY_FORMULA_0___`
        // enthielt – das maskierte Skript stand danach doppelt im Text, der
        // Nutzertext war weg. Ein Text kann die Marke jetzt nicht mehr treffen.
        //
        // Der Doppelparse-Schutz oben ist davon NICHT betroffen: Er prüft die
        // Ausgabe (`cbd-latex-formula`), nicht die Marken.
        $marke = uniqid();

        // Error handling: Catch preg_replace_callback failures
        try {
            // AP-1.fix2 (M1, Ebene 2): Skripte und Codebeispiele ZUERST aus
            // dem Weg räumen. Ein Skript kann auch in einem gewöhnlichen
            // Absatz oder innerhalb eines Container-Blocks stehen – dort
            // greift der Blocknamen-Filter aus parse_latex_in_blocks() nicht.
            // Muss vor allen weiteren Ersetzungen laufen, auch vor der
            // <em>-Reparatur unten.
            $content = $this->mask_protected_regions($content, $display_formulas, $protected_counter, $marke);

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
        // ($display_formulas/$display_counter sind oben angelegt, damit der
        //  catch-Zweig die Maskierung ebenfalls zurücknehmen kann.)

        // OPTIMIZED: Use [^\$] instead of . to prevent catastrophic backtracking
        // Match anything except $ sign, up to 10000 chars per formula
        $content = preg_replace_callback(
            '/\$\$([^\$]{1,10000}?)\$\$/s',
            function($matches) use (&$display_formulas, &$display_counter, $marke) {
                $placeholder = '___CBD_DISPLAY_FORMULA_' . $marke . '_' . $display_counter . '___';
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
            function($matches) use (&$display_formulas, &$display_counter, $marke) {
                $placeholder = '___CBD_DISPLAY_FORMULA_' . $marke . '_' . $display_counter . '___';
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
            function($matches) use (&$display_formulas, &$display_counter, $marke) {
                if (trim($matches[1]) === '') {
                    return $matches[0]; // leere Klammer ist keine Formel
                }
                $placeholder = '___CBD_DISPLAY_FORMULA_' . $marke . '_' . $display_counter . '___';
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
            function($matches) use (&$display_formulas, &$display_counter, $marke) {
                if (trim($matches[1]) === '') {
                    return $matches[0]; // leere Klammer ist keine Formel
                }
                $placeholder = '___CBD_INLINE_FORMULA_' . $marke . '_' . $display_counter . '___';
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

            // Platzhalter zurück durch gerenderte Formeln bzw. die
            // geschützten Bereiche ersetzen
            $content = $this->restore_placeholders($content, $display_formulas);

        } catch (\Throwable $e) {
            // Throwable statt Exception: fängt auch PHP-Errors (TypeError etc.)
            // ab, damit eine kaputte Formel nicht die ganze Seite killt.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CBD LaTeX Parser] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            // Return original content if parsing fails. Angefangene
            // Maskierungen zwingend zurücknehmen – sonst stünden nackte
            // ___CBD_…___-Marken im ausgelieferten Text.
            return $this->restore_placeholders($content, $display_formulas);
        }

        // Check for PREG errors.
        //
        // ACHTUNG (N4): Das erfasst nur den ZULETZT gelaufenen preg_*-Aufruf.
        // Jede Stelle, die einen eigenen Fehlerfall abfangen will, muss
        // log_preg_error() selbst und unmittelbar aufrufen – so wie es
        // mask_protected_regions() tut.
        $this->log_preg_error('parse_latex(), letztes Muster');

        return $content;
    }

    /**
     * Nimmt Bereiche aus dem Text, in denen nichts geparst werden darf.
     *
     * Betroffen sind `<script>`, `<pre>` und `<code>` samt Inhalt. Dort sind
     * `\(`, `\[` und `$` gewöhnliche Zeichen; eine JavaScript-Regex wie
     * `/\(([^)]+)\)/g` wäre nach dem Parsen unbrauchbar.
     *
     * Nutzt dieselbe Platzhalter-Mechanik wie die Formeln selbst: Marke in
     * den Text, Original in den gemeinsamen Speicher, Rücktausch am Ende
     * über restore_placeholders(). Verschachtelung (`<pre><code>…`) ist
     * abgedeckt, weil der äußere Treffer den inneren mitnimmt.
     *
     * @param string $content   Zu maskierender Text
     * @param array  $store     Gemeinsamer Platzhalter-Speicher (Referenz)
     * @param int    $counter   Laufende Nummer der Marken (Referenz)
     * @param string $marke     Zufallsstück des Aufrufs (N3, siehe parse_latex())
     * @return string
     */
    private function mask_protected_regions($content, &$store, &$counter, $marke) {
        // Billige Vorprüfung: ohne eines dieser Tags gibt es nichts zu tun.
        if (stripos($content, '<script') === false
            && stripos($content, '<pre') === false
            && stripos($content, '<code') === false) {
            return $content;
        }

        $masked = preg_replace_callback(
            '#<(script|pre|code)\b[^>]*>.*?</\1\s*>#is',
            function ($matches) use (&$store, &$counter, $marke) {
                $placeholder = '___CBD_PROTECTED_' . $marke . '_' . $counter . '___';
                $store[$placeholder] = $matches[0];
                $counter++;
                return $placeholder;
            },
            $content
        );

        // preg_replace_callback() liefert bei einem PCRE-Fehler null. Dann
        // lieber ungeschützt weiterarbeiten als den Inhalt zu verlieren.
        //
        // N4 (AP-1.fix5): Der Fehler wird HIER protokolliert, nicht am Ende
        // von parse_latex(). Jeder nachfolgende erfolgreiche preg_*-Aufruf
        // setzt preg_last_error() auf PREG_NO_ERROR zurück – eine spätere
        // Auswertung hätte diesen Fehler nie zu sehen bekommen.
        if (null === $masked) {
            $this->log_preg_error('mask_protected_regions()');
            return $content;
        }

        return $masked;
    }

    /**
     * Tauscht alle Platzhalter wieder gegen ihren Inhalt.
     *
     * Eine Stelle für beide Sorten (geschützte Bereiche und gerenderte
     * Formeln) – der Speicher ist derselbe.
     *
     * ZURÜCK IN UMGEKEHRTER EINTRAGSREIHENFOLGE (N2, AP-1.fix5): Die
     * geschützten Bereiche werden vor den Formeln maskiert und liegen deshalb
     * vorn im Speicher. Tauschte man sie zuerst zurück, liefe ihr
     * wiederhergestellter Inhalt anschliessend noch durch die
     * Formel-Ersetzungen – ein `<code>` mit einer Formel-Marke darin bekäme
     * die Formel eingesetzt. Zuletzt eingetragen heisst deshalb: zuerst
     * zurückgetauscht.
     *
     * @param string $content
     * @param array  $store Platzhalter => Ersatztext
     * @return string
     */
    private function restore_placeholders($content, $store) {
        foreach (array_reverse($store, true) as $placeholder => $ersatz) {
            $content = str_replace($placeholder, $ersatz, $content);
        }
        return $content;
    }

    /**
     * Protokolliert einen anliegenden PCRE-Fehler.
     *
     * MUSS unmittelbar nach dem fehlgeschlagenen preg_*-Aufruf gerufen werden:
     * `preg_last_error()` hält nur den Zustand des letzten Aufrufs, jeder
     * erfolgreiche Aufruf danach setzt ihn auf PREG_NO_ERROR zurück.
     *
     * @param string $kontext Fundstelle für die Meldung
     * @return void
     */
    private function log_preg_error($kontext) {
        $preg_error = preg_last_error();
        if (PREG_NO_ERROR === $preg_error) {
            return;
        }

        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $error_messages = array(
            PREG_INTERNAL_ERROR        => 'Internal PCRE error',
            PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit exhausted',
            PREG_RECURSION_LIMIT_ERROR => 'Recursion limit exhausted',
            PREG_BAD_UTF8_ERROR        => 'Bad UTF8 data',
            PREG_BAD_UTF8_OFFSET_ERROR => 'Bad UTF8 offset',
        );
        $error_msg = isset($error_messages[$preg_error]) ? $error_messages[$preg_error] : 'Unknown PREG error';

        error_log('[CBD LaTeX Parser] PREG Error in ' . $kontext . ': ' . $error_msg);
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
        //
        // MUSS vor dem Dekodieren laufen (AP-1.fix2): Aus einem maskierten
        // `&lt;br /&gt;` würde sonst ein echtes Tag, das dann stehen bliebe.
        $stripped = preg_replace('#<br\s*/?>#i', '', $formula);
        if (null !== $stripped) {
            $formula = $stripped;
        }

        // wptexturize(): typografische Ersetzungen zurücknehmen.
        //
        // Diese Tabelle ist gegen die ENTITY-Schreibweise geschrieben, die
        // wptexturize() erzeugt – sie muss deshalb VOR dem Dekodieren
        // greifen. Andernfalls stünde dort bereits das typografische Zeichen
        // (’ statt &#8217;), die Tabelle liefe ins Leere und KaTeX bekäme in
        // der Ableitung f'(x) ein U+2019 statt des Apostrophs.
        $formula = strtr($formula, array(
            '&#8216;' => "'",
            '&#8217;' => "'",
            '&#8220;' => '"',
            '&#8221;' => '"',
            '&#8211;' => '--',
            '&#8212;' => '---',
            '&#8230;' => '...',
            '&#215;'  => 'x',
        ));

        // AP-1.fix2 (M2): Der Editor speichert `<`, `>` und `&` in Absätzen
        // immer als Entity. Unaufgelöst sind `\begin{aligned}…&=…`, `array`,
        // `matrix` und jeder Vergleich `a < b` in Formeln unbenutzbar –
        // KaTeX bekäme `&amp;lt;` als Rohtext.
        // ENT_HTML5 deckt auch benannte Entities wie `&eacute;` ab; die
        // Funktion gibt es mit dieser Konstante seit PHP 5.4.
        return html_entity_decode($formula, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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