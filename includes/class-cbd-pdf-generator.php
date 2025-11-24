<?php
/**
 * CBD PDF Generator - Server-side PDF generation using TCPDF
 *
 * @package ContainerBlockDesigner
 * @since 2.8.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PDF Generator Class using TCPDF
 */
class CBD_PDF_Generator {

    /**
     * Singleton instance
     */
    private static $instance = null;

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
     * Constructor - private for singleton
     */
    private function __construct() {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            // Try to load via Composer autoloader
            $autoload_file = CBD_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload_file)) {
                require_once $autoload_file;
            }
        }
    }

    /**
     * Generate PDF from HTML blocks
     *
     * @param array $blocks Array of HTML block content
     * @param array $options PDF generation options
     * @return array Result with success status and file path or error message
     */
    public function generate_pdf($blocks, $options = array()) {
        // Validate input
        if (empty($blocks) || !is_array($blocks)) {
            return array(
                'success' => false,
                'error' => 'Keine Blöcke zum Exportieren gefunden.'
            );
        }

        // Check TCPDF availability
        if (!class_exists('TCPDF')) {
            return array(
                'success' => false,
                'error' => 'TCPDF-Bibliothek nicht gefunden. Bitte Composer-Abhängigkeiten installieren.'
            );
        }

        try {
            // Default options
            $defaults = array(
                'orientation' => 'P', // Portrait
                'unit' => 'mm',
                'format' => 'A4',
                'unicode' => true,
                'encoding' => 'UTF-8',
                'filename' => 'container-blocks-' . date('Y-m-d') . '.pdf',
                'margins' => array(15, 15, 15, 15), // top, right, bottom, left
                'author' => get_bloginfo('name'),
                'title' => 'Container Blocks Export',
                'subject' => 'WordPress Container Blocks',
            );

            $options = wp_parse_args($options, $defaults);

            // Create new PDF document
            $pdf = new TCPDF(
                $options['orientation'],
                $options['unit'],
                $options['format'],
                $options['unicode'],
                $options['encoding'],
                false
            );

            // Set document information
            $pdf->SetCreator('Container Block Designer Plugin');
            $pdf->SetAuthor($options['author']);
            $pdf->SetTitle($options['title']);
            $pdf->SetSubject($options['subject']);

            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Set margins
            list($top, $right, $bottom, $left) = $options['margins'];
            $pdf->SetMargins($left, $top, $right);
            $pdf->SetAutoPageBreak(true, $bottom);

            // Set font
            $pdf->SetFont('helvetica', '', 11);

            // Get CSS styles for PDF
            $css = $this->get_pdf_css();

            // Process each block
            foreach ($blocks as $index => $block_html) {
                // Add new page for each block
                $pdf->AddPage();

                // Clean and prepare HTML
                $clean_html = $this->prepare_html_for_pdf($block_html);

                // Write HTML to PDF with CSS
                $pdf->writeHTMLCell(
                    0, // width (0 = full width)
                    0, // height (0 = auto)
                    '', // x position
                    '', // y position
                    $clean_html,
                    0, // border
                    1, // ln (new line after)
                    false, // fill
                    true, // reseth
                    '', // align
                    true // autopadding
                );
            }

            // Save PDF to temporary file
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/cbd-temp-pdfs/';

            // Create temp directory if it doesn't exist
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }

            // Generate unique filename
            $temp_filename = 'cbd-pdf-' . uniqid() . '.pdf';
            $temp_filepath = $temp_dir . $temp_filename;

            // Output PDF to file
            $pdf->Output($temp_filepath, 'F');

            // Clean up old temp files (older than 1 hour)
            $this->cleanup_temp_files($temp_dir, 3600);

            return array(
                'success' => true,
                'filepath' => $temp_filepath,
                'filename' => $options['filename'],
                'url' => $upload_dir['baseurl'] . '/cbd-temp-pdfs/' . $temp_filename
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'PDF-Generierung fehlgeschlagen: ' . $e->getMessage()
            );
        }
    }

    /**
     * Prepare HTML for PDF rendering
     *
     * @param string $html Raw HTML content
     * @return string Cleaned HTML
     */
    private function prepare_html_for_pdf($html) {
        // Remove action buttons, menus, and controls
        $html = preg_replace('/<div[^>]*class="[^"]*cbd-action-buttons[^"]*"[^>]*>.*?<\/div>/is', '', $html);
        $html = preg_replace('/<button[^>]*class="[^"]*cbd-collapse-toggle[^"]*"[^>]*>.*?<\/button>/is', '', $html);
        $html = preg_replace('/<div[^>]*class="[^"]*cbd-header-menu[^"]*"[^>]*>.*?<\/div>/is', '', $html);
        $html = preg_replace('/<div[^>]*class="[^"]*cbd-container-number[^"]*"[^>]*>.*?<\/div>/is', '', $html);

        // Remove inline styles that might interfere
        $html = preg_replace('/style="[^"]*display:\s*none[^"]*"/i', '', $html);
        $html = preg_replace('/style="[^"]*visibility:\s*hidden[^"]*"/i', '', $html);

        // Ensure all content is visible
        $html = str_replace('class="cbd-collapsed"', '', $html);

        // Convert relative URLs to absolute (for images)
        $site_url = get_site_url();
        $html = preg_replace('/src="\//', 'src="' . $site_url . '/', $html);

        // Wrap in container with CSS
        $css = $this->get_pdf_css();
        $html = '<style>' . $css . '</style>' . $html;

        return $html;
    }

    /**
     * Get CSS styles for PDF
     *
     * @return string CSS styles
     */
    private function get_pdf_css() {
        $css = '
            /* Base styles */
            * {
                font-family: helvetica, arial, sans-serif;
                line-height: 1.5;
            }

            /* Container block styles */
            .cbd-container-block {
                margin: 0 0 15px 0;
                padding: 15px;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                background-color: #ffffff;
            }

            /* Header styles */
            .cbd-block-header {
                display: block;
                margin-bottom: 10px;
            }

            .cbd-block-title {
                font-size: 16px;
                font-weight: bold;
                margin: 0 0 8px 0;
                color: #333333;
            }

            /* Content styles */
            .cbd-container-content,
            .cbd-content {
                display: block;
                color: #333333;
                font-size: 11px;
            }

            /* Ensure all content is visible */
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
                margin: 0 0 10px 0;
            }

            h1, h2, h3, h4, h5, h6 {
                margin: 10px 0;
                font-weight: bold;
            }

            h1 { font-size: 18px; }
            h2 { font-size: 16px; }
            h3 { font-size: 14px; }
            h4 { font-size: 12px; }

            /* Lists */
            ul, ol {
                margin: 0 0 10px 20px;
                padding: 0;
            }

            li {
                margin: 0 0 5px 0;
            }

            /* Tables */
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 0 0 15px 0;
            }

            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }

            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }

            /* Code blocks */
            code, pre {
                background-color: #f4f4f4;
                border: 1px solid #ddd;
                border-radius: 3px;
                padding: 2px 5px;
                font-family: monospace;
                font-size: 10px;
            }

            pre {
                display: block;
                padding: 10px;
                overflow-x: auto;
            }

            /* Images */
            img {
                max-width: 100%;
                height: auto;
            }

            /* Links */
            a {
                color: #0073aa;
                text-decoration: none;
            }

            /* Hide interactive elements */
            .cbd-action-buttons,
            .cbd-collapse-toggle,
            .cbd-header-menu,
            .cbd-container-number,
            .cbd-selection-menu {
                display: none !important;
            }
        ';

        return $css;
    }

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
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) > $max_age) {
                    @unlink($file);
                }
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

        // Set headers for download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Output file
        readfile($filepath);

        // Delete temp file after download
        @unlink($filepath);

        exit;
    }
}
