<?php
/**
 * CBD PDF Generator - Server-side PDF generation using mPDF (with TCPDF fallback)
 *
 * Hybrid approach:
 * - Client expands collapsed blocks, extracts clean HTML + formula SVGs + interactive screenshots
 * - Server composes structured PDF with mPDF (best CSS support, SVG rendering, page breaks)
 * - Falls back to TCPDF if mPDF is not installed
 *
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PDF Generator Class - mPDF primary, TCPDF fallback
 */
class CBD_PDF_Generator {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Which PDF engine is available
     * @var string 'mpdf'|'tcpdf'|'none'
     */
    private $engine = 'none';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - detect available PDF engine
     */
    private function __construct() {
        $autoload_file = CBD_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload_file)) {
            require_once $autoload_file;
        }

        // Priority 1: mPDF (best CSS support, SVG rendering, page breaks)
        if (class_exists('\\Mpdf\\Mpdf')) {
            $this->engine = 'mpdf';
        }
        // Priority 2: TCPDF (legacy fallback)
        elseif (class_exists('TCPDF')) {
            $this->engine = 'tcpdf';
        }
    }

    /**
     * Get current PDF engine name
     *
     * @return string
     */
    public function get_engine() {
        return $this->engine;
    }

    /**
     * Generate PDF from block data (new hybrid format)
     *
     * Accepts both new format (structured block data with images) and legacy format (HTML strings).
     *
     * @param array $blocks Array of block data or HTML strings
     * @param array $options PDF generation options
     * @return array Result with success status and file path or error message
     */
    public function generate_pdf($blocks, $options = array()) {
        if (empty($blocks) || !is_array($blocks)) {
            return array(
                'success' => false,
                'error' => 'Keine Blöcke zum Exportieren gefunden.'
            );
        }

        if ($this->engine === 'none') {
            return array(
                'success' => false,
                'error' => 'Keine PDF-Bibliothek verfügbar. Bitte mPDF oder TCPDF installieren (composer update).'
            );
        }

        // Detect format: new (structured) vs legacy (HTML strings)
        $is_structured = isset($blocks[0]) && is_array($blocks[0]) && isset($blocks[0]['html']);

        try {
            if ($this->engine === 'mpdf') {
                return $this->generate_with_mpdf($blocks, $options, $is_structured);
            } else {
                return $this->generate_with_tcpdf($blocks, $options, $is_structured);
            }
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => 'PDF-Generierung fehlgeschlagen: ' . $e->getMessage()
            );
        }
    }

    // =========================================================================
    // mPDF Engine
    // =========================================================================

    /**
     * Generate PDF using mPDF
     *
     * @param array $blocks Block data
     * @param array $options PDF options
     * @param bool $is_structured Whether blocks are in new structured format
     * @return array Result
     */
    private function generate_with_mpdf($blocks, $options, $is_structured) {
        $defaults = array(
            'filename'       => 'container-blocks-' . date('Y-m-d') . '.pdf',
            'author'         => get_bloginfo('name'),
            'title'          => 'Container Blocks Export',
            'css_variables'  => array(),
            'mode'           => 'visual', // visual, print, text
        );
        $options = wp_parse_args($options, $defaults);

        // Check required PHP extensions
        $missing_ext = array();
        if (!extension_loaded('mbstring')) { $missing_ext[] = 'mbstring'; }
        if (!extension_loaded('gd')) { $missing_ext[] = 'gd'; }
        if (!empty($missing_ext)) {
            return array(
                'success' => false,
                'error' => 'Fehlende PHP-Erweiterungen: ' . implode(', ', $missing_ext) . '. Bitte beim Hoster aktivieren lassen.'
            );
        }

        // Prepare temp directory for mPDF
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/cbd-temp-pdfs/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        if (!is_writable($temp_dir)) {
            return array(
                'success' => false,
                'error' => 'Temp-Verzeichnis nicht beschreibbar: ' . $temp_dir
            );
        }

        // Create mPDF instance
        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 20,
            'margin_left'   => 15,
            'margin_right'  => 15,
            'default_font'  => 'dejavusans',
            'tempDir'       => $temp_dir,
            'img_dpi'       => 150,
        ]);

        // Set document info
        $mpdf->SetCreator('Container Block Designer Plugin');
        $mpdf->SetAuthor($options['author']);
        $mpdf->SetTitle($options['title']);

        // Enable CSS page breaks
        $mpdf->autoPageBreak = true;

        // Write global CSS stylesheet
        $css = $this->get_mpdf_stylesheet($options);
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

        // Process each block individually so mPDF can properly handle page breaks.
        // Writing blocks one-by-one allows mPDF to check remaining space and
        // move a block to the next page if it won't fit on the current one.
        foreach ($blocks as $index => $block) {
            if ($is_structured) {
                $html = $this->prepare_structured_block($block, $options);
            } else {
                $html = $this->prepare_html_for_pdf($block, $options);
            }
            $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        }

        // Save PDF
        $temp_filename = 'cbd-pdf-' . uniqid() . '.pdf';
        $temp_filepath = $temp_dir . $temp_filename;
        $mpdf->Output($temp_filepath, \Mpdf\Output\Destination::FILE);

        // Cleanup old temp files
        $this->cleanup_temp_files($temp_dir, 3600);

        return array(
            'success'  => true,
            'filepath' => $temp_filepath,
            'filename' => $options['filename'],
            'url'      => $upload_dir['baseurl'] . '/cbd-temp-pdfs/' . $temp_filename,
            'engine'   => 'mpdf'
        );
    }

    /**
     * Prepare a structured block for mPDF rendering
     *
     * @param array $block Block data with html, formulas, screenshots, title
     * @param array $options PDF options
     * @return string Prepared HTML
     */
    private function prepare_structured_block($block, $options) {
        $html = isset($block['html']) ? $block['html'] : '';
        $title = isset($block['title']) ? $block['title'] : '';
        $formulas = isset($block['formulas']) ? $block['formulas'] : array();
        $screenshots = isset($block['screenshots']) ? $block['screenshots'] : array();
        $css_variables = isset($options['css_variables']) ? $options['css_variables'] : array();

        // Step 1: Clean HTML (remove interactive controls, collapsed states)
        $html = $this->clean_block_html($html);

        // Step 2: Replace CSS variables with concrete values
        $html = $this->replace_css_variables($html, $css_variables);

        // Step 3: Insert formula renderings
        foreach ($formulas as $formula) {
            if (!empty($formula['id']) && !empty($formula['renderedHtml'])) {
                // Replace the formula placeholder with rendered KaTeX HTML
                $html = $this->insert_formula($html, $formula);
            }
        }

        // Step 4: Insert interactive element screenshots
        foreach ($screenshots as $screenshot) {
            if (!empty($screenshot['id']) && !empty($screenshot['base64'])) {
                $html = $this->insert_screenshot($html, $screenshot);
            }
        }

        // Step 5: Fix image URLs (relative → absolute)
        $html = $this->fix_image_urls($html);

        // Step 6: Download and embed remote images as base64 for mPDF
        $html = $this->embed_remote_images($html);

        // Wrap in container for styling with inline page-break-inside for mPDF
        $output = '<div class="cbd-pdf-block" style="page-break-inside:avoid;">';
        $output .= $html;
        $output .= '</div>';

        return $output;
    }

    /**
     * Clean block HTML for PDF output
     *
     * @param string $html Raw block HTML
     * @return string Cleaned HTML
     */
    private function clean_block_html($html) {
        // Remove action buttons
        $html = preg_replace(
            '/<div[^>]*class="[^"]*cbd-action-buttons[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html
        );

        // Remove collapse toggles
        $html = preg_replace(
            '/<button[^>]*class="[^"]*cbd-collapse-toggle[^"]*"[^>]*>.*?<\/button>/is',
            '',
            $html
        );

        // Remove header menus
        $html = preg_replace(
            '/<div[^>]*class="[^"]*cbd-header-menu[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html
        );

        // Remove container numbers
        $html = preg_replace(
            '/<div[^>]*class="[^"]*cbd-container-number[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html
        );

        // Remove selection menus
        $html = preg_replace(
            '/<div[^>]*class="[^"]*cbd-selection-menu[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html
        );

        // Remove board mode elements
        $html = preg_replace(
            '/<div[^>]*class="[^"]*cbd-board-overlay[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html
        );
        $html = preg_replace(
            '/<canvas[^>]*class="[^"]*cbd-drawing-canvas[^"]*"[^>]*>.*?<\/canvas>/is',
            '',
            $html
        );

        // Remove Interactivity API data attributes (mPDF doesn't need them)
        $html = preg_replace('/\s*data-wp-[a-z-]+="[^"]*"/i', '', $html);

        // Remove display:none and collapsed states
        $html = preg_replace('/style="[^"]*display:\s*none[^"]*"/i', '', $html);
        $html = preg_replace('/style="[^"]*visibility:\s*hidden[^"]*"/i', '', $html);
        $html = str_replace('cbd-collapsed', '', $html);
        $html = preg_replace('/\s*aria-hidden="[^"]*"/', '', $html);

        // Remove KaTeX elements entirely (formulas are replaced with plain text client-side)
        $html = preg_replace('/<span[^>]*class="[^"]*katex-mathml[^"]*"[^>]*>.*?<\/span>/is', '', $html);
        $html = preg_replace('/<span[^>]*class="[^"]*katex[^"]*"[^>]*>.*?<\/span>/is', '', $html);

        // Remove border-radius from inline styles (mPDF renders them poorly)
        $html = preg_replace('/border-radius\s*:\s*[^;]+;?/i', '', $html);

        // Remove inline scripts (not needed in PDF)
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

        // Remove empty style attributes left over from cleaning
        $html = preg_replace('/\s*style="\s*"/', '', $html);

        // Clean up multiple spaces and empty class attributes
        $html = preg_replace('/\s*class="\s*"/', '', $html);
        $html = preg_replace('/\s{2,}/', ' ', $html);

        return $html;
    }

    /**
     * Replace CSS variables with concrete values
     *
     * @param string $html HTML content
     * @param array $css_vars CSS variable values from client
     * @return string HTML with replaced variables
     */
    private function replace_css_variables($html, $css_vars) {
        $replacements = array(
            'var(--color-special-text)'     => $css_vars['specialText'] ?? '#71230a',
            'var(--color-ui-surface)'       => $css_vars['uiSurface'] ?? '#e24614',
            'var(--color-ui-surface-dark)'  => $css_vars['uiSurfaceDark'] ?? '#c93d12',
            'var(--color-ui-surface-light)' => $css_vars['uiSurfaceLight'] ?? '#f5ede9',
            'var(--color-sidebar-border)'   => $css_vars['sidebarBorder'] ?? '#e0e0e0',
            'var(--color-primary-text)'     => $css_vars['primaryText'] ?? '#333333',
            'var(--color-background)'       => $css_vars['background'] ?? '#ffffff',
            'var(--color-light-background)' => $css_vars['lightBackground'] ?? '#f8f9fa',
        );

        foreach ($replacements as $var => $value) {
            $html = str_replace($var, $value, $html);
        }

        // Also catch var() with fallback values: var(--name, fallback)
        $html = preg_replace_callback(
            '/var\(--[a-z-]+(?:,\s*([^)]+))?\)/',
            function ($matches) {
                // Use the fallback value if available, otherwise use a default
                return isset($matches[1]) ? trim($matches[1]) : '#333333';
            },
            $html
        );

        return $html;
    }

    /**
     * Insert rendered formula into HTML
     *
     * @param string $html Block HTML
     * @param array $formula Formula data with id and renderedHtml
     * @return string Updated HTML
     */
    private function insert_formula($html, $formula) {
        $formula_id = preg_quote($formula['id'], '/');
        $rendered = $formula['renderedHtml'];

        // Replace the formula element content with rendered version
        // Match: <div|span ... id="formula-id" ...>...</div|span>
        $html = preg_replace(
            '/(<(?:div|span)[^>]*id="' . $formula_id . '"[^>]*>).*?(<\/(?:div|span)>)/is',
            '$1' . $rendered . '$2',
            $html
        );

        return $html;
    }

    /**
     * Insert screenshot image for interactive element
     *
     * @param string $html Block HTML
     * @param array $screenshot Screenshot data with id and base64
     * @return string Updated HTML
     */
    private function insert_screenshot($html, $screenshot) {
        $element_id = $screenshot['id'];
        $base64 = $screenshot['base64'];

        // Ensure base64 has proper data URI prefix
        if (strpos($base64, 'data:image/') === 0) {
            $src = $base64;
        } else {
            $src = 'data:image/jpeg;base64,' . $base64;
        }

        $img_tag = '<img src="' . $src . '" '
                 . 'style="max-width:100%; height:auto; page-break-inside:avoid;" />';

        $replacement = '<div style="page-break-inside:avoid; margin:8px 0; text-align:center;">' . $img_tag . '</div>';

        // Match the simple placeholder div inserted by the client JS
        $escaped_id = preg_quote($element_id, '/');
        $html = preg_replace(
            '/<div[^>]*data-cbd-screenshot-id="' . $escaped_id . '"[^>]*>.*?<\/div>/is',
            $replacement,
            $html
        );

        return $html;
    }

    /**
     * Fix relative image URLs to absolute
     *
     * @param string $html HTML content
     * @return string HTML with fixed URLs
     */
    private function fix_image_urls($html) {
        $site_url = get_site_url();

        // Fix src="/path" → src="https://site.com/path"
        $html = preg_replace(
            '/src="\/([^"]*)"/',
            'src="' . $site_url . '/$1"',
            $html
        );

        // Fix srcset relative URLs
        $html = preg_replace(
            '/srcset="\/([^"]*)"/',
            'srcset="' . $site_url . '/$1"',
            $html
        );

        return $html;
    }

    /**
     * Embed remote images as base64 data URIs for reliable PDF rendering
     *
     * mPDF can sometimes fail to fetch remote images. Embedding them as base64
     * ensures they always appear in the PDF.
     *
     * @param string $html HTML content
     * @return string HTML with embedded images
     */
    private function embed_remote_images($html) {
        // Find all img src URLs that are not already base64
        return preg_replace_callback(
            '/(<img[^>]*)\bsrc="(https?:\/\/[^"]+)"/',
            function ($matches) {
                $before_src = $matches[1];
                $url = $matches[2];

                // Only embed images from the same site (security + performance)
                $site_url = get_site_url();
                if (strpos($url, $site_url) !== 0) {
                    return $matches[0]; // Keep external images as-is
                }

                // Convert URL to file path
                $upload_dir = wp_upload_dir();
                $file_path = str_replace(
                    $upload_dir['baseurl'],
                    $upload_dir['basedir'],
                    $url
                );

                // Also try ABSPATH-based conversion
                if (!file_exists($file_path)) {
                    $file_path = str_replace($site_url, rtrim(ABSPATH, '/'), $url);
                }

                if (file_exists($file_path) && is_readable($file_path)) {
                    $mime = wp_check_filetype($file_path)['type'] ?? 'image/png';
                    $data = base64_encode(file_get_contents($file_path));
                    if ($data && strlen($data) < 5 * 1024 * 1024) { // Max 5MB per image
                        return $before_src . 'src="data:' . $mime . ';base64,' . $data . '"';
                    }
                }

                return $matches[0]; // Fallback: keep original URL
            },
            $html
        );
    }

    /**
     * Get mPDF stylesheet for PDF rendering
     *
     * @param array $options PDF options
     * @return string CSS
     */
    private function get_mpdf_stylesheet($options) {
        $mode = $options['mode'] ?? 'visual';
        $css_vars = $options['css_variables'] ?? array();

        // Resolve colors
        $ui_surface = $css_vars['uiSurface'] ?? '#e24614';
        $ui_surface_light = $css_vars['uiSurfaceLight'] ?? '#f5ede9';
        $special_text = $css_vars['specialText'] ?? '#71230a';
        $primary_text = $css_vars['primaryText'] ?? '#333333';
        $border_color = $css_vars['sidebarBorder'] ?? '#e0e0e0';
        $bg_color = ($mode === 'print') ? '#ffffff' : ($css_vars['background'] ?? '#ffffff');

        $css = '
/* Base */
body {
    font-family: dejavusans, sans-serif;
    font-size: 11pt;
    line-height: 1.6;
    color: ' . $primary_text . ';
}

/* PDF Block Container — keep whole blocks together on a page.
   mPDF will only split a block if it exceeds one full page. */
.cbd-pdf-block {
    margin-bottom: 10mm;
    page-break-inside: avoid;
}

/* Container Block Styling */
.cbd-container-block {
    padding: 12px 18px;
    border: 1px solid ' . $border_color . ';
    background-color: ' . $bg_color . ';
    margin-bottom: 12px;
    page-break-inside: avoid;
}

/* Block Header */
.cbd-block-header {
    margin-bottom: 10px;
    padding: 8px 0;
    page-break-after: avoid;
}

.cbd-block-title {
    font-size: 14pt;
    font-weight: bold;
    color: ' . $primary_text . ';
    margin: 0 0 6px 0;
    page-break-after: avoid;
}

/* Content Area - always visible in PDF */
.cbd-container-content,
.cbd-content,
.cbd-collapsible-content {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    max-height: none !important;
    overflow: visible !important;
}

/* Typography */
p {
    margin: 0 0 8px 0;
}

h1 { font-size: 20pt; font-weight: bold; margin: 14px 0 8px 0; page-break-after: avoid; }
h2 { font-size: 17pt; font-weight: bold; margin: 12px 0 6px 0; page-break-after: avoid; }
h3 { font-size: 14pt; font-weight: bold; margin: 10px 0 6px 0; page-break-after: avoid; }
h4 { font-size: 12pt; font-weight: bold; margin: 8px 0 4px 0; page-break-after: avoid; }
h5 { font-size: 11pt; font-weight: bold; margin: 6px 0 4px 0; }
h6 { font-size: 10pt; font-weight: bold; margin: 6px 0 4px 0; }

/* Lists */
ul, ol {
    margin: 0 0 10px 20px;
    padding: 0;
}
li {
    margin: 0 0 4px 0;
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 12px 0;
    page-break-inside: auto;
}
tr {
    page-break-inside: avoid;
}
th, td {
    border: 1px solid ' . $border_color . ';
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
}
th {
    background-color: #f2f2f2;
    font-weight: bold;
}

/* Code Blocks */
code {
    background-color: #f4f4f4;
    border: 1px solid #ddd;
    border-radius: 2px;
    padding: 1px 4px;
    font-family: dejavusansmono, monospace;
    font-size: 9pt;
}
pre {
    background-color: #f4f4f4;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px;
    font-family: dejavusansmono, monospace;
    font-size: 9pt;
    overflow: visible;
    white-space: pre-wrap;
    word-wrap: break-word;
    page-break-inside: auto;
}

/* Images */
img {
    max-width: 100%;
    height: auto;
    page-break-inside: avoid;
}

/* Figures */
figure {
    margin: 8px 0;
    page-break-inside: avoid;
}
figcaption {
    font-size: 9pt;
    color: #666;
    text-align: center;
    margin-top: 4px;
}

/* Links */
a {
    color: ' . $ui_surface . ';
    text-decoration: none;
}

/* Blockquote */
blockquote {
    border-left: 3px solid ' . $ui_surface . ';
    margin: 10px 0;
    padding: 8px 16px;
    background-color: ' . $ui_surface_light . ';
    page-break-inside: avoid;
}

/* LaTeX Formulas (replaced with plain text client-side) */
.cbd-pdf-formula {
    font-style: italic;
    page-break-inside: avoid;
}
/* Legacy KaTeX classes (in case any remain) */
.cbd-latex-formula {
    page-break-inside: avoid;
}
.cbd-latex-display {
    display: block;
    text-align: center;
    margin: 12px 0;
    page-break-inside: avoid;
}
.cbd-latex-inline {
    display: inline;
}

/* KaTeX rendered output */
.katex {
    font-size: 1.1em;
}

/* Interactive element screenshots */
.cbd-interactive-screenshot {
    page-break-inside: avoid;
    margin: 8px 0;
    text-align: center;
}

/* WordPress blocks common classes */
.wp-block-image {
    page-break-inside: avoid;
    margin: 8px 0;
}
.wp-block-table {
    page-break-inside: auto;
}
.wp-block-columns {
    display: table;
    width: 100%;
    table-layout: fixed;
}
.wp-block-column {
    display: table-cell;
    vertical-align: top;
    padding: 0 8px;
}

/* Nested container blocks */
.cbd-container .cbd-container {
    margin: 8px 0;
}

/* Hide elements that should not appear in PDF */
.cbd-action-buttons,
.cbd-collapse-toggle,
.cbd-header-menu,
.cbd-container-number,
.cbd-selection-menu,
.cbd-board-mode-toggle,
.cbd-board-overlay,
.cbd-drawing-canvas,
.cbd-behandelt-toggle {
    display: none !important;
}

/* Special text emphasis */
.has-special-text-color {
    color: ' . $special_text . ';
}
';

        // Print mode: remove background colors
        if ($mode === 'print') {
            $css .= '
.cbd-container-block {
    background-color: #ffffff !important;
    border: 1px solid #cccccc;
}
blockquote {
    background-color: #f9f9f9 !important;
}
';
        }

        // Text-only mode: minimal styling
        if ($mode === 'text') {
            $css .= '
.cbd-container-block {
    border: none;
    padding: 0;
    background: none;
}
img:not(.cbd-formula-img) {
    display: none;
}
';
        }

        return $css;
    }

    // =========================================================================
    // TCPDF Fallback Engine
    // =========================================================================

    /**
     * Generate PDF using TCPDF (legacy fallback)
     *
     * @param array $blocks Block data
     * @param array $options PDF options
     * @param bool $is_structured Whether blocks are in new structured format
     * @return array Result
     */
    private function generate_with_tcpdf($blocks, $options, $is_structured) {
        $defaults = array(
            'orientation' => 'P',
            'unit'        => 'mm',
            'format'      => 'A4',
            'unicode'     => true,
            'encoding'    => 'UTF-8',
            'filename'    => 'container-blocks-' . date('Y-m-d') . '.pdf',
            'margins'     => array(15, 15, 15, 15),
            'author'      => get_bloginfo('name'),
            'title'       => 'Container Blocks Export',
        );
        $options = wp_parse_args($options, $defaults);

        $pdf = new TCPDF(
            $options['orientation'],
            $options['unit'],
            $options['format'],
            $options['unicode'],
            $options['encoding'],
            false
        );

        $pdf->SetCreator('Container Block Designer Plugin');
        $pdf->SetAuthor($options['author']);
        $pdf->SetTitle($options['title']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        list($top, $right, $bottom, $left) = $options['margins'];
        $pdf->SetMargins($left, $top, $right);
        $pdf->SetAutoPageBreak(true, $bottom);
        $pdf->SetFont('helvetica', '', 11);

        foreach ($blocks as $block) {
            $pdf->AddPage();

            if ($is_structured) {
                // For structured blocks with TCPDF: use HTML + embedded screenshots
                $html = isset($block['html']) ? $block['html'] : '';
                $html = $this->prepare_html_for_pdf($html, $options);

                // Embed screenshots as images
                $screenshots = isset($block['screenshots']) ? $block['screenshots'] : array();
                foreach ($screenshots as $screenshot) {
                    if (!empty($screenshot['base64'])) {
                        $html = $this->insert_screenshot($html, $screenshot);
                    }
                }
            } else {
                $html = $this->prepare_html_for_pdf($block, $options);
            }

            $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, false, true, '', true);
        }

        // Save PDF
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/cbd-temp-pdfs/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $temp_filename = 'cbd-pdf-' . uniqid() . '.pdf';
        $temp_filepath = $temp_dir . $temp_filename;
        $pdf->Output($temp_filepath, 'F');

        $this->cleanup_temp_files($temp_dir, 3600);

        return array(
            'success'  => true,
            'filepath' => $temp_filepath,
            'filename' => $options['filename'],
            'url'      => $upload_dir['baseurl'] . '/cbd-temp-pdfs/' . $temp_filename,
            'engine'   => 'tcpdf'
        );
    }

    /**
     * Prepare raw HTML for PDF rendering (legacy TCPDF method)
     *
     * @param string $html Raw HTML content
     * @param array $options PDF options
     * @return string Cleaned HTML
     */
    private function prepare_html_for_pdf($html, $options = array()) {
        $html = $this->clean_block_html($html);
        $html = $this->fix_image_urls($html);

        $css_vars = isset($options['css_variables']) ? $options['css_variables'] : array();
        $html = $this->replace_css_variables($html, $css_vars);

        // Wrap with TCPDF-compatible CSS
        $css = $this->get_tcpdf_css();
        $html = '<style>' . $css . '</style>' . $html;

        return $html;
    }

    /**
     * Get TCPDF-compatible CSS (simplified subset)
     *
     * @return string CSS
     */
    private function get_tcpdf_css() {
        return '
* { font-family: helvetica, arial, sans-serif; line-height: 1.5; }
.cbd-container-block { margin: 0 0 15px 0; padding: 15px; border: 1px solid #e0e0e0; }
.cbd-block-title { font-size: 16px; font-weight: bold; margin: 0 0 8px 0; color: #333; }
.cbd-container-content, .cbd-content { display: block; font-size: 11px; color: #333; }
p { margin: 0 0 10px 0; }
h1 { font-size: 18px; } h2 { font-size: 16px; } h3 { font-size: 14px; }
table { width: 100%; border-collapse: collapse; margin: 0 0 15px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; font-weight: bold; }
code, pre { background-color: #f4f4f4; border: 1px solid #ddd; font-family: monospace; font-size: 10px; }
img { max-width: 100%; height: auto; }
a { color: #0073aa; text-decoration: none; }
.cbd-action-buttons, .cbd-collapse-toggle, .cbd-header-menu, .cbd-container-number { display: none; }
';
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Clean up old temporary PDF files
     *
     * @param string $dir Directory path
     * @param int $max_age Maximum file age in seconds
     */
    private function cleanup_temp_files($dir, $max_age = 3600) {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . 'cbd-pdf-*.pdf');
        if (!$files) {
            return;
        }

        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) > $max_age)) {
                @unlink($file);
            }
        }
    }

    /**
     * Download PDF file
     *
     * @param string $filepath Path to PDF file
     * @param string $filename Download filename
     */
    public function download_pdf($filepath, $filename) {
        if (!file_exists($filepath)) {
            wp_die('PDF-Datei nicht gefunden.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($filepath);
        @unlink($filepath);
        exit;
    }
}
