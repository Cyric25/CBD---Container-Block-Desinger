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

        // Legacy filter for classic editor content and non-block content
        // Priority 5: Run BEFORE wpautop (priority 10)
        add_filter('the_content', array($this, 'parse_latex'), 5);
    }

    /**
     * Enqueue KaTeX library
     */
    public function enqueue_katex() {
        // KaTeX CSS
        wp_enqueue_style(
            'katex',
            'https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css',
            array(),
            '0.16.9'
        );

        // KaTeX JS
        wp_enqueue_script(
            'katex',
            'https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js',
            array(),
            '0.16.9',
            true
        );

        // KaTeX Auto-render extension
        wp_enqueue_script(
            'katex-auto-render',
            'https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js',
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

        // Performance: Skip if no $ or [latex] markers present
        if (strpos($block_content, '$') === false && strpos($block_content, '[latex]') === false) {
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

            // Platzhalter zurück durch gerenderte Display-Formeln ersetzen
            foreach ($display_formulas as $placeholder => $formula_html) {
                $content = str_replace($placeholder, $formula_html, $content);
            }

        } catch (Exception $e) {
            // Log error and return original content
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CBD LaTeX Parser] Error parsing LaTeX: ' . $e->getMessage());
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
        $formula = trim($matches[1]);
        $this->formula_counter++;

        $formula_id = 'cbd-latex-' . uniqid() . '-' . $this->formula_counter;

        // Store formula for potential PDF export
        $this->formulas[$formula_id] = $formula;

        // Return HTML structure for KaTeX rendering
        // The span is empty - KaTeX will fill it with rendered content
        $html = sprintf(
            '<div class="cbd-latex-formula cbd-latex-display" id="%s" data-latex="%s" data-formula-id="%s">
                <span class="cbd-latex-content"></span>
            </div>',
            esc_attr($formula_id),
            esc_attr($formula),
            esc_attr($formula_id)
        );

        return $html;
    }

    /**
     * Render inline formula (within text flow)
     *
     * @param array $matches Regex matches
     * @return string Rendered HTML
     */
    private function render_inline_formula($matches) {
        $formula = trim($matches[1]);
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