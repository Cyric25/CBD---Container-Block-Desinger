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

        // Filter für allgemeinen WordPress Content (nur für normale Posts/Pages)
        // Nicht für Block-Content, da dieser manuell in class-cbd-block-registration.php geparst wird
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
     * Parse LaTeX formulas from content
     *
     * Supports:
     * - $$formula$$ syntax (display math)
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

        // Parse $$formula$$ syntax (display math)
        $content = preg_replace_callback(
            '/\$\$(.+?)\$\$/s',
            array($this, 'render_display_formula'),
            $content
        );

        // Parse [latex]formula[/latex] shortcode syntax
        $content = preg_replace_callback(
            '/\[latex\](.+?)\[\/latex\]/si',
            array($this, 'render_display_formula'),
            $content
        );

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
            '<div class="cbd-latex-formula" id="%s" data-latex="%s" data-formula-id="%s">
                <span class="cbd-latex-content"></span>
            </div>',
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