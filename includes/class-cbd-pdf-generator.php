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
        $this->ensure_download_htaccess($temp_dir);

        if (!is_writable($temp_dir)) {
            return array(
                'success' => false,
                'error' => 'Temp-Verzeichnis nicht beschreibbar: ' . $temp_dir
            );
        }

        // Increase pcre limits for large HTML (interactive blocks like molecule viewers)
        $old_backtrack = ini_get('pcre.backtrack_limit');
        $old_recursion = ini_get('pcre.recursion_limit');
        ini_set('pcre.backtrack_limit', '5000000');
        ini_set('pcre.recursion_limit', '500000');

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

        // AP-1.2 (PLAN-PDF-Export-und-Tafelmodus-Fixes.md): Ohne dieses
        // Flag ersetzt mPDF ein nicht dekodierbares Bild lautlos durch
        // sein eigenes 14x16px-Platzhalterbild, ohne jede Log-Ausgabe -
        // genau das hat die fehlenden "Eigenen Notizen"/"Tafelbilder" im
        // PDF unauffindbar gemacht (siehe AP-1.1-Diagnose).
        //
        // AP-1.fix3 (Korrektur nach AP-1.rev-Befund F2): dauerhaft aktiviert
        // war das Flag selbst ein Regressionsrisiko - mPDF wirft bei JEDEM
        // nicht dekodierbaren Bild eine MpdfImageException
        // (vendor/mpdf/mpdf/src/Image/ImageProcessor.php::imageError()),
        // die hier nicht abgefangen wird und den GESAMTEN Export abbrechen
        // laesst (siehe catch (\Exception) in generate_pdf() oben) - auch
        // wenn nur ein einziges, mit der eigentlichen Notiz/dem Tafelbild
        // unzusammenhaengendes Fremdbild betroffen ist (z. B. eine nicht
        // erreichbare Remote-URL oder ein zu grosses Same-Site-Bild, siehe
        // embed_remote_images()). Vor AP-1.2 fuehrte genau dieser Fall nur
        // zu einem stillen Platzhalter, der Export selbst lief durch - das
        // war zwar schlecht diagnostizierbar, aber nie ein Totalausfall.
        // Das Flag ist als Diagnosewerkzeug fuer AP-1.1 entstanden, nicht
        // als Teil der eigentlichen Bildkorrektur (die liegt in
        // sanitize_pdf_block_html()/recompressBase64()/den korrigierten
        // CSS-Variablennamen) - es ist deshalb an dieselbe Debug-Konvention
        // gekoppelt wie die uebrigen Diagnose-Logs im Plugin (siehe
        // CLAUDE.md, Abschnitt "Debugging-Konventionen"): in der
        // Produktivumgebung bleibt der alte, sichere Rueckfall auf den
        // stillen Platzhalter erhalten, mit WP_DEBUG steht die laute
        // Diagnose weiterhin zur Verfuegung.
        $mpdf->showImageErrors = defined('WP_DEBUG') && WP_DEBUG;

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

        // Restore pcre limits
        ini_set('pcre.backtrack_limit', $old_backtrack);
        ini_set('pcre.recursion_limit', $old_recursion);

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
     * AP-1.3 (PLAN-PDF-Export-und-Tafelmodus-Fixes.md): Legt in
     * cbd-temp-pdfs/ eine .htaccess mit Content-Disposition: attachment fuer
     * .pdf-Dateien an, als defensive Absicherung fuer den Direktdownload.
     *
     * `downloadPDF()` in pdf-server-side.js nutzt bereits die korrekte
     * `<a download>`-Technik, die same-origin ohne Nachfrage funktioniert -
     * dieser Header ist eine zusaetzliche Absicherung (z. B. falls der
     * Download-Link mal direkt aufgerufen statt per Klick auf den Anchor
     * ausgeloest wird) und **kein** Ersatz dafuer. Eine vom Nutzer selbst in
     * seinem Browser aktivierte Einstellung „Vor jedem Download nachfragen,
     * wo die Datei gespeichert werden soll" kann dieser Header nicht
     * uebersteuern - das ist eine Browser-Entscheidung, keine, die eine
     * Website per HTTP-Header oder JavaScript aufheben kann.
     *
     * Schreibt die Datei nur, wenn sie noch nicht existiert (idempotent,
     * kein Schreibzugriff bei jedem Export). Schlaegt das Schreiben fehl
     * (z. B. Verzeichnis nicht beschreibbar), wird der PDF-Export dadurch
     * NICHT blockiert - rein defensive Ergaenzung.
     *
     * @param string $temp_dir Absoluter Pfad zu cbd-temp-pdfs/ (mit
     *                          abschliessendem Slash)
     */
    private function ensure_download_htaccess($temp_dir) {
        $htaccess_path = $temp_dir . '.htaccess';
        if (file_exists($htaccess_path)) {
            return;
        }
        $contents = "<IfModule mod_headers.c>\n"
            . "<FilesMatch \"\\.pdf$\">\n"
            . "Header set Content-Disposition \"attachment\"\n"
            . "</FilesMatch>\n"
            . "</IfModule>\n";
        @file_put_contents($htaccess_path, $contents);
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

        // Step 1: Insert screenshots FIRST (needs data-cbd-screenshot-id attributes)
        foreach ($screenshots as $screenshot) {
            if (!empty($screenshot['id']) && !empty($screenshot['base64'])) {
                $html = $this->insert_screenshot($html, $screenshot);
            }
        }

        // Step 1.5: Insert formula images (needs data-cbd-formula-id attributes,
        // die clean_block_html gleich strippt). Formeln OHNE Bild behalten den
        // lesbaren Fallback-Text aus dem Client.
        foreach ($formulas as $formula) {
            // AP-1.2: Seit dem Vektorweg genuegt AUCH ein 'svg'. Der Waechter
            // verlangte hier frueher zwingend ein 'image' - dadurch war der
            // SVG-Zweig in insert_formula_image() unerreichbar, und die
            // Formeln blieben stumm als Fallback-Text stehen. Beim Bauen des
            // Durchstichs genau so passiert.
            if (!empty($formula['id']) && (!empty($formula['image']) || !empty($formula['svg']))) {
                $html = $this->insert_formula_image($html, $formula);
            }
        }

        // Step 2: Clean HTML (remove data-*, scripts, interactive controls)
        $html = $this->clean_block_html($html);

        // Step 3: Replace CSS variables with concrete values
        $html = $this->replace_css_variables($html, $css_variables);

        // Step 4: Insert formula renderings
        foreach ($formulas as $formula) {
            if (!empty($formula['id']) && !empty($formula['renderedHtml'])) {
                $html = $this->insert_formula($html, $formula);
            }
        }

        // Step 5: Fix image URLs (relative → absolute)
        $html = $this->fix_image_urls($html);

        // Step 6: Download and embed remote images as base64 for mPDF
        $html = $this->embed_remote_images($html);

        // Step 7 (AP-3.2): Blocktitel fuer mPDF von <h3> auf <span> umstellen.
        $html = $this->kopfzeile_fuer_mpdf($html);

        // Wrap in container for styling with inline page-break-inside for mPDF
        $output = '<div class="cbd-pdf-block" style="page-break-inside:avoid;">';
        $output .= $html;
        $output .= '</div>';

        return $output;
    }

    /**
     * AP-3.2: Blocktitel fuer mPDF von <h3> auf <span> umstellen.
     *
     * WARUM das noetig ist, obwohl das Stylesheet schon `display: inline`
     * setzt: **mPDF setzt `display: inline` an einem <h3> nicht um.** Am
     * erzeugten PDF gemessen - mit `text-align: center` allein wurde der Kopf
     * zwar mittig, das Icon blieb aber in einer eigenen Zeile ueber dem
     * Titel, weil mPDF das <h3> weiterhin als Block behandelt und einen
     * Umbruch erzwingt. Ein <span> rendert es zuverlaessig inline.
     *
     * Die Umstellung passiert **ausschliesslich im PDF-Weg**, ganz am Ende
     * der Aufbereitung. Das gerenderte Frontend-HTML bleibt unangetastet -
     * dort ist das <h3> semantisch richtig und traegt die
     * Gliederungsstruktur der Seite. Im PDF gibt es keine Gliederung, die
     * ein <h3> tragen muesste; dort zaehlt allein das Aussehen, und das
     * regelt `.cbd-block-title` im Stylesheet (Schriftgroesse, Fettung,
     * Farbe) unveraendert weiter.
     *
     * Bewusst mit einem engen regulaeren Ausdruck statt eines Parsers: Es
     * geht um genau ein Element mit genau einer bekannten Klasse, das
     * `CBD_Block_Registration::render_block()` selbst erzeugt
     * (`<h3 class="cbd-block-title">`). Findet sich nichts, bleibt der
     * Inhalt unveraendert - die Fehlerrichtung ist "sieht aus wie bisher",
     * nicht "kaputt".
     *
     * @param string $html Aufbereitetes Block-HTML
     * @return string HTML mit <span> statt <h3> im Blocktitel
     */
    private function kopfzeile_fuer_mpdf($html) {
        if (false === strpos($html, 'cbd-block-title')) {
            return $html;
        }

        // Oeffnendes Tag samt etwaiger weiterer Attribute uebernehmen.
        //
        // Die Klasse wird mit (?=[\s"]) abgeschlossen statt mit \b: Ein
        // Bindestrich ist kein Wortzeichen, \b haette deshalb auch
        // "cbd-block-title-wrapper" o. Ae. getroffen und dessen <h3> still
        // umgeschrieben (Review-Befund 12 zu AP-3.3). Heute existiert keine
        // solche Klasse -- die Verschaerfung ist Vorsorge, kein Bugfix.
        $html = preg_replace(
            '#<h3(\s[^>]*class="[^"]*\bcbd-block-title(?=[\s"])[^"]*"[^>]*)>#i',
            '<span$1>',
            $html
        );

        // Das zugehoerige schliessende Tag. Container-Bloecke enthalten in
        // ihrem Kopf kein zweites <h3>, deshalb genuegt hier die einfache
        // Ersetzung; ein <h3> im Blockinhalt liegt ausserhalb der Kopfzeile
        // und wuerde von der Oeffnungs-Ersetzung oben gar nicht erfasst.
        //
        // Ohne Wachbedingung: Die frueher hier stehende Pruefung war tot --
        // ihr zweiter Zweig (strpos auf 'cbd-block-title') war immer wahr,
        // weil der vorzeitige Ausstieg oben diesen Fall bereits abfaengt
        // (Review-Befund 11 zu AP-3.3). Ein preg_replace ohne Treffer ist
        // ohnehin folgenlos.
        $html = preg_replace(
            '#(<span\s[^>]*class="[^"]*\bcbd-block-title(?=[\s"])[^"]*"[^>]*>.*?)</h3>#is',
            '$1</span>',
            $html
        );

        return $html;
    }

    /**
     * Clean block HTML for PDF output
     *
     * @param string $html Raw block HTML
     * @return string Cleaned HTML
     */
    private function clean_block_html($html) {
        // === Phase 1: Fast string-based removal to shrink HTML before regex ===

        // Strip ALL data-* attributes (data-wp-*, data-chemviz-*, data-action, etc.)
        // This is the biggest size reducer for interactive blocks
        $html = preg_replace('/\s+data-[a-z][a-z0-9_-]*="[^"]*"/i', '', $html);
        $html = preg_replace("/\s+data-[a-z][a-z0-9_-]*='[^']*'/i", '', $html);

        // Remove <svg>...</svg> blocks (icon SVGs in controls, not needed in PDF)
        //
        // OHNE AUSNAHME - und das ist seit AP-2.2 wieder so.
        //
        // AP-1.2 hatte hier eine Ausnahme fuer gesetzte Formeln eingebaut,
        // erkennbar an einem je Anfrage gewuerfelten Marker: Die Formeln
        // gingen damals als inline <svg> ins Block-HTML und wurden VOR
        // dieser Reinigung eingesetzt. Der Marker musste unerratbar sein,
        // sonst waere er eine Eintrittskarte an der Reinigung vorbei
        // gewesen (Review Phase 1, Befund 3).
        //
        // Seit AP-2.2 reist eine gesetzte Formel als
        // <img src="data:image/svg+xml;base64,..."> - im Block-HTML steht
        // also gar kein <svg> mehr, und diese Zeile darf wieder JEDES
        // entfernen. Der base64-Zeichenvorrat enthaelt kein '<', ein
        // eingebettetes SVG kann von dieser Regel also nicht getroffen
        // werden.
        //
        // WER HIER WIEDER INLINE-SVG EINSETZEN WILL, braucht den Marker
        // erneut - siehe die Begruendung oben.
        $html = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $html);

        // Remove inline scripts (not needed in PDF)
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

        // Remove <canvas> elements entirely (WebGL/drawing canvases)
        $html = preg_replace('/<canvas\b[^>]*>.*?<\/canvas>/is', '', $html);

        // Remove aria-* attributes
        $html = preg_replace('/\s+aria-[a-z-]+="[^"]*"/i', '', $html);

        // === Phase 2: Targeted element removal (now safe with smaller HTML) ===

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

        // Remove display:none and collapsed states
        $html = preg_replace('/style="[^"]*display:\s*none[^"]*"/i', '', $html);
        $html = preg_replace('/style="[^"]*visibility:\s*hidden[^"]*"/i', '', $html);
        $html = str_replace('cbd-collapsed', '', $html);

        // Remove KaTeX elements entirely (formulas are replaced with plain text client-side)
        $html = preg_replace('/<span[^>]*class="[^"]*katex-mathml[^"]*"[^>]*>.*?<\/span>/is', '', $html);
        $html = preg_replace('/<span[^>]*class="[^"]*katex[^"]*"[^>]*>.*?<\/span>/is', '', $html);

        // Remove border-radius from inline styles (mPDF renders them poorly)
        $html = preg_replace('/border-radius\s*:\s*[^;]+;?/i', '', $html);

        // === Phase 3: Cleanup ===

        // Remove empty style and class attributes
        $html = preg_replace('/\s*style="\s*"/', '', $html);
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
            // AP-1.2 (PLAN-PDF-Export-und-Tafelmodus-Fixes.md): Die beiden
            // Schluessel waren mit den vertauschten/falschen Variablennamen
            // aus pdf-server-side.js::collectCSSVariables() dupliziert (siehe
            // Fix dort) - trafen dadurch nie auf tatsaechlich im Blockinhalt
            // vorkommendes var(--color-text-primary)/var(--color-background-light).
            'var(--color-text-primary)'       => $css_vars['primaryText'] ?? '#333333',
            'var(--color-background)'         => $css_vars['background'] ?? '#ffffff',
            'var(--color-background-light)'   => $css_vars['lightBackground'] ?? '#f8f9fa',
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
    /**
     * Ersetzt einen Formel-Platzhalter durch das clientseitig gerenderte
     * PNG-Bild (KaTeX via html2canvas). width/height sind CSS-Pixel des
     * Originals; das Canvas ist mit scale 2 erfasst und wird auf die
     * Originalgröße skaliert (scharfe Darstellung in mPDF).
     *
     * @param string $html    Block-HTML mit <span|div data-cbd-formula-id="...">Fallback</...>
     * @param array  $formula ['id','image','width','height','isDisplay']
     * @return string
     */
    private function insert_formula_image($html, $formula) {
        $formula_id = preg_quote($formula['id'], '/');

        // Liefert die Nutzlast ein gesetztes SVG (Vektorweg, Regelweg seit
        // AP-2.2), wird es als <img src="data:image/svg+xml;base64,...">
        // eingesetzt. Der Zweig ist abwaertskompatibel - ein Eintrag mit
        // 'image' verhaelt sich unveraendert wie zuvor, was der Rueckfall je
        // Formel (A4) braucht.
        //
        // WARUM <img> UND NICHT INLINE <svg> - beides gemessen:
        //
        //   - mPDF wertet `vertical-align` an einem inline <svg> ueberhaupt
        //     nicht aus (auch nicht `margin-bottom`) und setzt dessen
        //     Unterkante auf die Textgrundlinie. Eine Inline-Formel schwebt
        //     dadurch um ihre Grundlinientiefe ueber der Zeile.
        //   - An einem <img> wertet mPDF `vertical-align` aus - allerdings
        //     NUR die Schluesselwoerter. Ein Laengenwert wirkt wie `bottom`:
        //     ueber vier Zeilenhoehen und vier Werte gemessen ist der
        //     Versatz je Zeilenhoehe konstant (7,21 / 5,01 / 2,81 / 0,61 pt
        //     bei line-height 1,4 / 1,8 / 2,2 / 2,6) und vom Wert voellig
        //     unabhaengig. `middle` dagegen wirkt konstant (+5,55 pt) und
        //     unabhaengig von der Zeilenhoehe.
        //   - Deshalb `middle` - dieselbe Regel, die der Rasterweg seit je
        //     benutzt. Die Formel sitzt damit mittig zur Zeile statt
        //     buchstabengenau auf der Grundlinie; genau umsetzen liesse sich
        //     das nur mit einer eigenen Schaetzung der Textgrundlinie, und
        //     das ist der Fehler, den dieses Vorhaben abschafft.
        //   - AP-2.1 hatte stattdessen den SVG-Kasten unten gekuerzt. Das
        //     war falsch: mPDF beschneidet an der viewBox (in AP-2.2 am
        //     Bild nachgewiesen), und der Nenner eines Inline-Bruchs fiel
        //     dabei weg. Die damalige Gegenmessung hatte Zeichenobjekte im
        //     Inhaltsstrom gezaehlt - die stehen auch dann darin, wenn sie
        //     beschnitten sind.
        //   - Ein <img> mit SVG-Inhalt bleibt in mPDF VEKTORIELL: im
        //     Versuch 0 Rasterbilder, 62 Zeichenobjekte.
        //
        // DIE MASSE MUESSEN AN DAS <img>, in CSS-Pixeln (AP-2.fix1).
        //
        // Die erste Fassung ueberliess mPDF die Groesse - es liest sie dann
        // aus dem SVG selbst. Das ergibt eine ANDERE Umrechnung als ein
        // CSS-`width` am <img>: Am selben Massstab gemessen (identisches
        // Textstueck 153,00 pt breit in beiden PDFs) war dieselbe
        // Inline-Formel im Vektorweg **49,89 pt** statt **70,50 pt** wie im
        // Rasterweg - rund ein Viertel zu klein, und im Fliesstext sichtbar.
        // Gefunden im unabhaengigen Review AP-2.rev, Befund B1.
        //
        // Der Rasterweg hat es von Anfang an richtig gemacht: Er setzt
        // `width:Npx; height:Npx` als CSS. Genau das tut dieser Zweig jetzt
        // auch - mit denselben Zahlen, die der Browser gemessen hat und die
        // im SVG stehen.
        $svg = $this->formel_svg_pruefen($formula);
        if (null !== $svg) {
            $quelle = 'data:image/svg+xml;base64,' . base64_encode($svg);
            $masse = $this->formel_svg_masse($svg);
            if (!empty($formula['isDisplay'])) {
                return $this->formel_ersetzen($html, $formula_id,
                    '<div style="text-align:center; margin:10px 0; page-break-inside:avoid;">'
                    . '<img src="' . $quelle . '" style="' . $masse . 'max-width:100%;" />'
                    . '</div>');
            }
            return $this->formel_ersetzen($html, $formula_id,
                '<img src="' . $quelle . '" style="' . $masse . 'vertical-align:middle;" />');
        }

        // Kein Rasterbild in der Nutzlast? Dann bleibt der lesbare
        // Fallback-Text des Clients stehen.
        //
        // WICHTIG, im Review von Phase 1 gefunden (Befund 2): Der Vektorweg
        // schickt `{id, svg, isDisplay}` OHNE `image`. Weist die Notbremse
        // oben das SVG ab, gab es hier frueher einen ungeprueften Zugriff
        // auf `$formula['image']` - PHP-Warning, leerer String,
        // `data:image/png;base64,` ohne Inhalt, und mPDF brach den GANZEN
        // Export mit "Could not find image file" ab (HTTP 500). Ohne
        // WP_DEBUG waere statt dessen mPDFs Fehler-Platzhalter im PDF
        // gelandet.
        //
        // Die Notbremse verspricht einen Rueckfall auf den Rasterweg. Den
        // kann sie nur einloesen, wenn ein Rasterbild da ist - sonst ist das
        // Beste, was sie tun kann, den Fallback-Text stehen zu lassen. Der
        // eigentliche Rueckfall gehoert an die andere Stelle: Der Client
        // entscheidet vor dem Senden, ob er SVG oder Bild liefert.
        if (empty($formula['image']) || !is_string($formula['image'])) {
            $this->log_svg_abweisung($formula,
                'weder brauchbares SVG noch Rasterbild - Fallback-Text bleibt stehen');
            return $html;
        }

        $image = $formula['image'];

        if (strpos($image, 'data:image/') !== 0) {
            $image = 'data:image/png;base64,' . $image;
        }

        $width  = max(0, intval($formula['width'] ?? 0));
        $height = max(0, intval($formula['height'] ?? 0));
        $size_style = '';
        if ($width > 0 && $height > 0) {
            $size_style = 'width:' . $width . 'px; height:' . $height . 'px; ';
        }

        if (!empty($formula['isDisplay'])) {
            $replacement = '<div style="text-align:center; margin:10px 0; page-break-inside:avoid;">'
                . '<img src="' . $image . '" style="' . $size_style . 'max-width:100%;" />'
                . '</div>';
        } else {
            $replacement = '<img src="' . $image . '" style="' . $size_style . 'vertical-align:middle;" />';
        }

        return $this->formel_ersetzen($html, $formula_id, $replacement);
    }

    /**
     * Platzhalter (span ODER div) samt Fallback-Text ersetzen. Der
     * Platzhalter-Inhalt ist reiner Text (kein verschachteltes Markup),
     * daher ist der non-greedy Match sicher.
     *
     * Herausgezogen in AP-1.2, damit der neue SVG-Zweig dieselbe Ersetzung
     * benutzt und nicht eine zweite, leicht abweichende Fassung entsteht.
     *
     * @param string $html
     * @param string $formula_id bereits durch preg_quote() gegangen
     * @param string $ersatz
     * @return string
     */
    private function formel_ersetzen($html, $formula_id, $ersatz) {
        $neu = preg_replace(
            '/<(?:div|span)[^>]*data-cbd-formula-id="' . $formula_id . '"[^>]*>.*?<\/(?:div|span)>/is',
            // Backslashes und $-Zeichen im Ersatz sind fuer preg_replace
            // Rueckverweise. SVG-Pfaddaten enthalten kein $, aber der
            // Blockinhalt koennte eines tragen - deshalb maskieren.
            str_replace(array('\\', '$'), array('\\\\', '\\$'), $ersatz),
            $html,
            1
        );

        // Bei einem PCRE-Fehler (Backtrack-Grenze, zu grosses Muster) liefert
        // preg_replace() null - der GANZE Blockinhalt waere dann weg, ohne
        // Meldung. Genau diese Fehlerklasse hat im Theme schon einmal ganze
        // Seiten geleert (CLAUDE.md, "Nebenbefund am Theme"). Mit mehreren
        // Kilobyte SVG je Formel ist sie hier naeher als frueher.
        if (null === $neu) {
            error_log('[CBD PDF] Formel-Ersetzung fehlgeschlagen (PCRE-Fehlercode '
                . preg_last_error() . '), Block bleibt unveraendert, id='
                . $formula_id);
            return $html;
        }

        return $neu;
    }

    /**
     * Liest `width`/`height` aus dem aufbereiteten SVG und gibt sie als
     * CSS-Angabe zurueck (mit abschliessendem Semikolon, oder leer).
     *
     * Der Browser hat die beiden Werte am Formelelement gemessen und von
     * `ex` in `px` umgerechnet (bereiteSvgFuerMpdfAuf(), Umformung 2). Hier
     * werden sie nur uebernommen - gerechnet wird nichts.
     *
     * Warum ueberhaupt: siehe die Begruendung in insert_formula_image().
     *
     * @param string $svg
     * @return string z. B. 'width:93.40px; height:17.20px; ' oder ''
     */
    private function formel_svg_masse($svg) {
        if (!preg_match('/<svg\b[^>]*\bwidth="([\d.]+)px"/i', $svg, $mw)) {
            return '';
        }
        if (!preg_match('/<svg\b[^>]*\bheight="([\d.]+)px"/i', $svg, $mh)) {
            return '';
        }
        $breite = (float) $mw[1];
        $hoehe  = (float) $mh[1];
        // Unplausibles lieber weglassen als eine Formel ueber die Seite
        // schieben - ohne Angabe skaliert mPDF wie zuvor.
        if ($breite <= 0 || $hoehe <= 0 || $breite > 5000 || $hoehe > 5000) {
            return '';
        }
        return 'width:' . $breite . 'px; height:' . $hoehe . 'px; ';
    }

    /**
     * Prueft, ob eine Formel-Nutzlast ein brauchbares, gesetztes SVG traegt.
     *
     * DIE NOTBREMSE DIESES WEGES (Risiko R4 im Plan). Beide Muster, gegen
     * die hier geprueft wird, scheitern in mPDF **still**:
     *
     * - `fill="currentColor"` kennt mPDF nicht. Es baut den Pfad und malt
     *   ihn nicht (PDF-Operator `n` statt `f`) - die Formel ist unsichtbar,
     *   ohne Fehler, ohne Platzhalter.
     * - `ex`-Masse versteht mPDF nicht. Eine einzige Formel fuellte im
     *   Versuch drei Viertel einer A4-Seite und verdraengte den Folgetext.
     *
     * Beides gehoert clientseitig umgeformt (AP-2.1). Kommt es trotzdem
     * hier an, ist die Umformung kaputt - dann ist ein Rasterbild allemal
     * besser als eine unsichtbare oder seitensprengende Formel.
     *
     * @param array $formula
     * @return string|null entschaerftes SVG, oder null fuer den Rasterweg
     */
    private function formel_svg_pruefen($formula) {
        if (empty($formula['svg']) || !is_string($formula['svg'])) {
            return null;
        }
        $svg = $formula['svg'];

        if (false === stripos($svg, '<svg')) {
            $this->log_svg_abweisung($formula, 'kein <svg>-Element');
            return null;
        }
        if (false !== stripos($svg, 'currentColor')) {
            $this->log_svg_abweisung($formula, 'currentColor nicht aufgeloest');
            return null;
        }
        // Beide Schreibweisen des Attributs UND die style-Form. Die erste
        // Fassung pruefte nur auf doppelte Anfuehrungszeichen; einfache
        // gingen durch, und DOMDocument im Sanitizer normalisiert sie
        // danach auf doppelte - das `ex` kam also doch bei mPDF an
        // (AP-2.rev, Befund B2).
        if (preg_match('/(?:width|height)\s*=\s*["\'][\d.]+ex["\']/i', $svg)
            || preg_match('/(?:width|height)\s*:\s*[\d.]+ex/i', $svg)) {
            $this->log_svg_abweisung($formula, 'Masse noch in ex');
            return null;
        }

        // Immer durch den vorhandenen Sanitizer. Seine Whitelist deckt die
        // von MathJax mit fontCache:'none' erzeugten Bausteine vollstaendig
        // ab (in AP-1.2 gemessen: path- und rect-Zahlen vorher wie nachher
        // identisch, ueber sieben Formelarten). Sie darf NICHT aufgeweicht
        // werden - <use>/xlink:href fehlen dort mit Absicht.
        // Der Sanitizer wird sonst nur im Adminbereich geladen (Icon-Upload)
        // und fehlt im PDF-Weg. Beim Bauen des Durchstichs genau so
        // aufgetreten - die Notbremse meldete "CBD_SVG_Sanitizer fehlt" und
        // jede Formel fiel auf ein leeres Rasterbild zurueck.
        if (!class_exists('CBD_SVG_Sanitizer')
            && defined('CBD_PLUGIN_DIR')
            && file_exists(CBD_PLUGIN_DIR . 'includes/class-cbd-svg-sanitizer.php')) {
            require_once CBD_PLUGIN_DIR . 'includes/class-cbd-svg-sanitizer.php';
        }
        if (!class_exists('CBD_SVG_Sanitizer')) {
            $this->log_svg_abweisung($formula, 'CBD_SVG_Sanitizer fehlt');
            return null;
        }
        $sauber = CBD_SVG_Sanitizer::sanitize($svg);
        if (is_array($sauber)) {
            $sauber = isset($sauber['svg']) ? $sauber['svg'] : reset($sauber);
        }
        if (!is_string($sauber) || false === stripos($sauber, '<svg')) {
            $this->log_svg_abweisung($formula, 'Sanitizer lieferte kein SVG');
            return null;
        }

        return $sauber;
    }

    /**
     * @param array  $formula
     * @param string $grund
     */
    private function log_svg_abweisung($formula, $grund) {
        // Wortlaut bewusst neutral: OB ein Rueckfall auf ein Rasterbild
        // moeglich ist, haengt daran, ob der Client eines mitgeschickt hat.
        // Die frueher hier stehende Zusage "Rueckfall auf Rasterbild" war
        // irrefuehrend - im Vektorweg liegt kein Bild in der Nutzlast
        // (Review Phase 1, Befund 2).
        error_log('[CBD PDF] Formel-SVG nicht verwendet (' . $grund . '), id='
            . (isset($formula['id']) ? $formula['id'] : '?'));
    }

    /**
     * TOTER CODE - wird nie erreicht (Stand N2, 2026-09-04).
     *
     * Dieser Zweig setzt gerendertes KaTeX-HTML (Schluessel renderedHtml) in
     * den Platzhalter ein. Die Nutzlast des Browsers enthaelt diesen
     * Schluessel jedoch nie: Sie stammt ausschliesslich aus
     * captureFormulaImages() in assets/js/pdf-server-side.js und traegt nur
     * id/image/width/height/isDisplay. Der Erzeuger auf der Browserseite
     * (extractFormulas()) wird seinerseits nirgends aufgerufen. Zusaetzlich
     * matcht der Ausdruck hier auf id="..." statt auf
     * data-cbd-formula-id="...", was der Platzhalter tatsaechlich traegt.
     *
     * NICHT wiederbeleben: mPDFs CSS-Maschine kann KaTeX-Markup nicht setzen.
     * Begruendung und Messwerte: docs/diagnose-pdf-formeln.md, Abschnitte 3
     * und 8 (Variante B, ausdruecklich nicht empfohlen).
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

/* Block Header
 *
 * AP-3.2 (PLAN-PDF-Formelfarbe-und-App-Download.md, 2026-09-06): Icon und
 * Titel standen im PDF UNTEREINANDER und linksbuendig, waehrend sie im
 * Frontend nebeneinander und mittig stehen.
 *
 * Ursache: assets/css/cbd-frontend-clean.css:1324 gestaltet die Kopfzeile mit
 * `display: flex; align-items: center; justify-content: center`. **mPDF
 * beherrscht kein Flexbox** und faellt auf Blocklayout zurueck - das <h3>
 * des Titels erzwingt dann einen Zeilenumbruch nach dem Icon-<span>.
 *
 * Der Nachbau kommt deshalb ohne Flexbox aus: `text-align: center` an der
 * Kopfzeile (mPDF setzt das zuverlaessig um und vererbt es an die
 * Inline-Kinder) und `display: inline` am Titel, damit er nicht mehr seine
 * eigene Zeile beansprucht. Das Icon-<span> ist ohnehin inline.
 *
 * `margin: 0` am Titel ist dabei nicht Kosmetik: Ein Blockabstand an einem
 * inline gesetzten Element ignoriert mPDF zwar, ein verbleibender unterer
 * Abstand wuerde die Zeile aber je nach mPDF-Fassung wieder aufreissen.
 */
.cbd-block-header {
    margin-bottom: 10px;
    padding: 8px 0;
    page-break-after: avoid;
    text-align: center;
}

.cbd-block-title {
    display: inline;
    font-size: 14pt;
    font-weight: bold;
    color: ' . $primary_text . ';
    margin: 0;
    page-break-after: avoid;
}

/* Das Icon sitzt in derselben Zeile links vom Titel. vertical-align: middle
 * richtet es an der Mittellinie des Titeltexts aus - dasselbe, was im
 * Frontend `align-items: center` leistet. */
.cbd-header-icon {
    display: inline;
    vertical-align: middle;
}

/* Der Abstand sitzt am Bild, NICHT am umgebenden <span>: mPDF setzt
 * `margin` an einem inline gerenderten <span> nicht um (am erzeugten PDF
 * gemessen - Icon und Titel klebten aneinander), an einem <img> dagegen
 * schon. */
.cbd-header-icon img,
.cbd-custom-icon {
    display: inline;
    vertical-align: middle;
    width: 24px;
    height: 24px;
    margin-right: 7px;
}

/* Dashicons und die uebrigen Symbolschriften sind Text, kein Bild - dort
 * traegt der Abstand ein Wortzwischenraum-Ersatz am <span> selbst nicht,
 * deshalb hier ueber padding, das mPDF auch inline anwendet. */
.cbd-header-icon .dashicons,
.cbd-header-icon .material-icons,
.cbd-header-icon .cbd-emoji-icon {
    vertical-align: middle;
    padding-right: 7px;
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
