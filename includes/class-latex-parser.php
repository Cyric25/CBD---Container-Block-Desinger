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
        add_filter('render_block', array($this, 'parse_latex_in_blocks'), 10, 2);

        // Legacy filter for classic editor content and non-block content
        add_filter('the_content', array($this, 'parse_latex'), 999);
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

        // Reset counter for each content block
        $this->formula_counter = 0;

        // WICHTIG: Parse $$formula$$ ZUERST (display math)
        // Dies muss vor $formula$ geparst werden, damit $$ nicht als zwei $ interpretiert wird
        // Temporär ersetzen mit Platzhalter um Konflikte zu vermeiden
        $display_formulas = array();
        $display_counter = 0;

        $content = preg_replace_callback(
            '/\$\$(.+?)\$\$/s',
            function($matches) use (&$display_formulas, &$display_counter) {
                $placeholder = '___CBD_DISPLAY_FORMULA_' . $display_counter . '___';
                $display_formulas[$placeholder] = $this->render_display_formula($matches);
                $display_counter++;
                return $placeholder;
            },
            $content
        );

        // Parse [latex]formula[/latex] shortcode syntax (display math)
        $content = preg_replace_callback(
            '/\[latex\](.+?)\[\/latex\]/si',
            function($matches) use (&$display_formulas, &$display_counter) {
                $placeholder = '___CBD_DISPLAY_FORMULA_' . $display_counter . '___';
                $display_formulas[$placeholder] = $this->render_display_formula($matches);
                $display_counter++;
                return $placeholder;
            },
            $content
        );

        // Parse $formula$ syntax (inline math) - nun ohne $$ Konflikte
        // Einfacher Regex: einzelnes $ gefolgt von non-$ content, gefolgt von einzelnem $
        $content = preg_replace_callback(
            '/\$([^\$]+?)\$/s',
            array($this, 'render_inline_formula'),
            $content
        );

        // Platzhalter zurück durch gerenderte Display-Formeln ersetzen
        foreach ($display_formulas as $placeholder => $formula_html) {
            $content = str_replace($placeholder, $formula_html, $content);
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