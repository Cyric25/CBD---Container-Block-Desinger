<?php
/**
 * Container Block Designer - AJAX Handler Korrektur
 * Version: 2.5.3
 * 
 * Datei: includes/class-cbd-ajax-handler.php
 * 
 * KORRIGIERT: Lädt echte Blocks aus der Datenbank
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasse für AJAX-Anfragen
 */
class CBD_Ajax_Handler {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->init_ajax_handlers();
    }
    
    /**
     * AJAX-Handler initialisieren
     */
    private function init_ajax_handlers() {
        // Block-Abruf für Editor
        add_action('wp_ajax_cbd_get_blocks', array($this, 'get_blocks'));
        add_action('wp_ajax_nopriv_cbd_get_blocks', array($this, 'get_blocks_nopriv'));

        // Weitere Admin AJAX-Handler
        // Hinweis: cbd_save_block und cbd_delete_block werden von CBD_Admin
        // behandelt (Doppelregistrierung entfernt – verursachte Nonce-Konflikte)
        add_action('wp_ajax_cbd_duplicate_block', array($this, 'duplicate_block'));
        add_action('wp_ajax_cbd_set_default_block', array($this, 'set_default_block'));

        // PDF-Generierung (für eingeloggte Benutzer und Frontend)
        add_action('wp_ajax_cbd_generate_pdf', array($this, 'generate_pdf'));
        add_action('wp_ajax_nopriv_cbd_generate_pdf', array($this, 'generate_pdf'));

        // PDF Diagnose-Endpoint
        add_action('wp_ajax_cbd_pdf_diagnose', array($this, 'pdf_diagnose'));
        add_action('wp_ajax_nopriv_cbd_pdf_diagnose', array($this, 'pdf_diagnose'));

        // REST API Endpoints (alternative to admin-ajax.php)
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes for PDF generation
     */
    public function register_rest_routes() {
        register_rest_route('cbd/v1', '/pdf-diagnose', array(
            'methods'  => 'GET',
            'callback' => array($this, 'rest_pdf_diagnose'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('cbd/v1', '/generate-pdf', array(
            'methods'  => 'POST',
            'callback' => array($this, 'rest_generate_pdf'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * REST API: PDF Diagnose
     */
    public function rest_pdf_diagnose($request) {
        $info = array(
            'php_version'  => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'ext_mbstring' => extension_loaded('mbstring'),
            'ext_gd'       => extension_loaded('gd'),
            'ext_zlib'     => extension_loaded('zlib'),
            'ext_xml'      => extension_loaded('xml'),
        );

        $info['mpdf_available'] = false;
        $info['mpdf_error'] = '';
        try {
            if (class_exists('\\Mpdf\\Mpdf')) {
                $info['mpdf_available'] = true;
            }
        } catch (\Throwable $e) {
            $info['mpdf_error'] = $e->getMessage();
        }

        $info['tcpdf_available'] = class_exists('TCPDF');

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/cbd-temp-pdfs/';
        $info['temp_dir'] = $temp_dir;
        $info['temp_dir_exists'] = file_exists($temp_dir);
        $info['temp_dir_writable'] = is_writable($temp_dir) || is_writable($upload_dir['basedir']);

        return new \WP_REST_Response($info, 200);
    }

    /**
     * REST API: Generate PDF
     */
    public function rest_generate_pdf($request) {
        // Rate-limit: max 15 requests per minute per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_key = 'cbd_pdf_rate_' . md5($ip);
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count >= 15) {
            return new \WP_REST_Response(array('success' => false, 'message' => 'Zu viele Anfragen. Bitte warten Sie eine Minute.'), 429);
        }
        set_transient($rate_key, $rate_count + 1, 60);

        $params = $request->get_json_params();

        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $blocks = array();
        $blocks_json = isset($params['blocks_json']) ? $params['blocks_json'] : '';

        if (!empty($blocks_json)) {
            if (is_string($blocks_json)) {
                $blocks_data = json_decode(stripslashes($blocks_json), true);
            } else {
                $blocks_data = $blocks_json;
            }

            if (empty($blocks_data) || !is_array($blocks_data)) {
                return new \WP_REST_Response(array('success' => false, 'message' => 'Ungültiges Block-Datenformat'), 400);
            }

            foreach ($blocks_data as $block) {
                $sanitized = array(
                    'html'        => isset($block['html']) ? wp_kses_post($block['html']) : '',
                    'title'       => isset($block['title']) ? sanitize_text_field($block['title']) : '',
                    'formulas'    => array(),
                    'screenshots' => array(),
                );

                if (!empty($block['formulas']) && is_array($block['formulas'])) {
                    foreach ($block['formulas'] as $formula) {
                        $entry = array(
                            'id'           => sanitize_text_field($formula['id'] ?? ''),
                            'renderedHtml' => wp_kses_post($formula['renderedHtml'] ?? ''),
                            'latex'        => sanitize_text_field($formula['latex'] ?? ''),
                        );
                        // Formel als PNG-Bild (gleiche Validierung wie Screenshots)
                        $image = $formula['image'] ?? '';
                        if ($image && preg_match('/^(data:image\/(png|jpeg|jpg);base64,)?[A-Za-z0-9+\/=]+$/', $image)) {
                            $entry['image']     = $image;
                            $entry['width']     = intval($formula['width'] ?? 0);
                            $entry['height']    = intval($formula['height'] ?? 0);
                            $entry['isDisplay'] = !empty($formula['isDisplay']);
                        }
                        $sanitized['formulas'][] = $entry;
                    }
                }

                if (!empty($block['screenshots']) && is_array($block['screenshots'])) {
                    foreach ($block['screenshots'] as $screenshot) {
                        $base64 = $screenshot['base64'] ?? '';
                        if (preg_match('/^(data:image\/(png|jpeg|jpg);base64,)?[A-Za-z0-9+\/=]+$/', $base64)) {
                            $sanitized['screenshots'][] = array(
                                'id'     => sanitize_text_field($screenshot['id'] ?? ''),
                                'base64' => $base64,
                            );
                        }
                    }
                }

                $blocks[] = $sanitized;
            }
        }

        if (empty($blocks)) {
            return new \WP_REST_Response(array('success' => false, 'message' => 'Keine Blöcke gefunden'), 400);
        }

        $options = array(
            'filename' => isset($params['filename']) ? sanitize_file_name($params['filename']) : 'export-' . date('Y-m-d') . '.pdf',
            'mode'     => isset($params['mode']) ? sanitize_text_field($params['mode']) : 'visual',
        );

        if (!empty($params['css_variables'])) {
            $css_vars = is_string($params['css_variables']) ? json_decode(stripslashes($params['css_variables']), true) : $params['css_variables'];
            if (is_array($css_vars)) {
                $options['css_variables'] = array_map('sanitize_text_field', $css_vars);
            }
        }

        try {
            $pdf_generator = CBD_PDF_Generator::get_instance();
            $result = $pdf_generator->generate_pdf($blocks, $options);

            if ($result['success']) {
                return new \WP_REST_Response(array(
                    'success'  => true,
                    'url'      => $result['url'],
                    'filename' => $result['filename'],
                    'engine'   => $result['engine'] ?? 'unknown',
                ), 200);
            } else {
                return new \WP_REST_Response(array(
                    'success' => false,
                    'message' => $result['error'] ?? 'PDF-Generierung fehlgeschlagen'
                ), 500);
            }
        } catch (\Throwable $e) {
            error_log('[CBD PDF REST] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'PHP-Fehler: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Alle Blöcke abrufen - KORRIGIERTE VERSION
     */
    public function get_blocks() {
        // Debug-Log
        error_log('CBD: AJAX get_blocks aufgerufen');
        
        // Nonce-Überprüfung - flexibel für verschiedene Nonces
        $nonce_valid = false;
        
        // Prüfe verschiedene mögliche Nonce-Namen
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'cbd-nonce') ||
                wp_verify_nonce($_POST['nonce'], 'cbd-admin-nonce') ||
                wp_verify_nonce($_POST['nonce'], 'cbd_block_editor') ||
                wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
                $nonce_valid = true;
            }
        }
        
        // Falls kein gültiger Nonce, prüfe ob Benutzer berechtigt ist
        if (!$nonce_valid && !cbd_user_can_use_blocks()) {
            error_log('CBD: Nonce-Prüfung fehlgeschlagen oder keine Berechtigung für Container-Blocks');
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }
        
        global $wpdb;
        
        // Hole Blocks direkt aus der Datenbank
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        // Prüfe ob Tabelle existiert
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            error_log('CBD: Tabelle existiert nicht: ' . $table_name);
            wp_send_json_success(array());
            return;
        }
        
        // SQL-Query für aktive Blocks
        $sql = "SELECT
                id,
                COALESCE(title, name) as name,
                slug,
                description,
                config,
                features,
                status,
                COALESCE(is_default, 0) as is_default
            FROM $table_name
            WHERE status = 'active' OR status IS NULL
            ORDER BY COALESCE(title, name) ASC";
        
        $blocks = $wpdb->get_results($sql, ARRAY_A);
        
        error_log('CBD: Gefundene Blocks: ' . count($blocks));
        
        // Verarbeite die Blocks
        $processed_blocks = array();
        
        if ($blocks) {
            foreach ($blocks as $block) {
                // Stelle sicher dass slug existiert
                if (empty($block['slug'])) {
                    $block['slug'] = sanitize_title($block['name']);
                }
                
                // Parse JSON-Felder
                $config = !empty($block['config']) ? json_decode($block['config'], true) : array();
                $features = !empty($block['features']) ? json_decode($block['features'], true) : array();
                
                $processed_blocks[] = array(
                    'id' => intval($block['id']),
                    'name' => esc_html($block['name']),
                    'slug' => sanitize_title($block['slug']),
                    'description' => esc_html($block['description'] ?: ''),
                    'config' => $config ?: array(),
                    'features' => $features ?: array(),
                    'is_default' => intval($block['is_default'] ?? 0)
                );
            }
        }
        
        // Debug-Ausgabe
        error_log('CBD: Sende ' . count($processed_blocks) . ' Blocks zurück');
        
        // Sende erfolgreiche Antwort
        wp_send_json_success($processed_blocks);
    }
    
    /**
     * Blocks für nicht eingeloggte Benutzer (sollte nicht aufgerufen werden)
     */
    public function get_blocks_nopriv() {
        wp_send_json_error(array('message' => 'Nicht autorisiert'));
    }
    
    /**
     * Block speichern
     */
    public function save_block() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbd-admin-nonce')) {
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        // Daten vorbereiten
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'slug' => sanitize_title($_POST['slug'] ?: $_POST['name']),
            'description' => sanitize_textarea_field($_POST['description'] ?: ''),
            'config' => json_encode($_POST['config'] ?: array()),
            'features' => json_encode($_POST['features'] ?: array()),
            'status' => 'active'
        );
        
        // Speichern oder Update
        if (!empty($_POST['id'])) {
            $result = $wpdb->update($table_name, $data, array('id' => intval($_POST['id'])));
        } else {
            $result = $wpdb->insert($table_name, $data);
        }
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Fehler beim Speichern'));
            return;
        }
        
        wp_send_json_success(array('message' => 'Block gespeichert'));
    }
    
    /**
     * Block löschen
     */
    public function delete_block() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbd-admin-nonce')) {
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        $block_id = intval($_POST['block_id']);
        
        if (!$block_id) {
            wp_send_json_error(array('message' => 'Ungültige Block-ID'));
            return;
        }
        
        $result = $wpdb->delete($table_name, array('id' => $block_id));
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Fehler beim Löschen'));
            return;
        }
        
        wp_send_json_success(array('message' => 'Block gelöscht'));
    }
    
    /**
     * Block duplizieren
     */
    public function duplicate_block() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbd-admin-nonce')) {
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cbd_blocks';
        
        $block_id = intval($_POST['block_id']);
        
        if (!$block_id) {
            wp_send_json_error(array('message' => 'Ungültige Block-ID'));
            return;
        }
        
        // Original-Block holen
        $original = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $block_id),
            ARRAY_A
        );
        
        if (!$original) {
            wp_send_json_error(array('message' => 'Block nicht gefunden'));
            return;
        }
        
        // Kopie erstellen
        unset($original['id']);
        $original['name'] = $original['name'] . ' (Kopie)';
        $original['slug'] = $original['slug'] . '-copy-' . time();
        
        $result = $wpdb->insert($table_name, $original);
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Fehler beim Duplizieren'));
            return;
        }
        
        wp_send_json_success(array('message' => 'Block dupliziert', 'id' => $wpdb->insert_id));
    }

    /**
     * PDF Diagnose - Check server capabilities before generating PDF
     */
    public function pdf_diagnose() {
        $info = array(
            'php_version'  => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'ext_mbstring' => extension_loaded('mbstring'),
            'ext_gd'       => extension_loaded('gd'),
            'ext_zlib'     => extension_loaded('zlib'),
            'ext_xml'      => extension_loaded('xml'),
        );

        // Check if mPDF class can be loaded
        $info['mpdf_available'] = false;
        $info['mpdf_error'] = '';
        try {
            if (class_exists('\\Mpdf\\Mpdf')) {
                $info['mpdf_available'] = true;
            }
        } catch (\Throwable $e) {
            $info['mpdf_error'] = $e->getMessage();
        }

        // Check if TCPDF is available
        $info['tcpdf_available'] = class_exists('TCPDF');

        // Check temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/cbd-temp-pdfs/';
        $info['temp_dir'] = $temp_dir;
        $info['temp_dir_exists'] = file_exists($temp_dir);
        $info['temp_dir_writable'] = is_writable($temp_dir) || is_writable($upload_dir['basedir']);

        wp_send_json_success($info);
    }

    /**
     * PDF generieren - AJAX Handler
     *
     * Accepts two formats:
     * 1. New structured format: blocks_json with html, formulas, screenshots per block
     * 2. Legacy format: blocks[] as array of HTML strings
     */
    public function generate_pdf() {
        // PDF export is a public frontend feature (no login required)
        // Rate-limit: max 15 requests per minute per IP
        // Immer inkrementieren – der frühere is_rest_fallback-Bypass war
        // client-gesteuert und hebelte das Limit komplett aus.
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_key = 'cbd_pdf_rate_' . md5($ip);
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count >= 15) {
            wp_send_json_error(array('message' => 'Zu viele Anfragen. Bitte warten Sie eine Minute.'));
            return;
        }
        set_transient($rate_key, $rate_count + 1, 60);

        // Detect format: new structured (blocks_json) vs legacy (blocks[])
        $blocks = array();

        if (!empty($_POST['blocks_json'])) {
            // New structured format
            $blocks_data = json_decode(stripslashes($_POST['blocks_json']), true);

            if (empty($blocks_data) || !is_array($blocks_data)) {
                wp_send_json_error(array('message' => 'Ungültiges Block-Datenformat'));
                return;
            }

            foreach ($blocks_data as $block) {
                $sanitized = array(
                    'html'        => isset($block['html']) ? wp_kses_post($block['html']) : '',
                    'title'       => isset($block['title']) ? sanitize_text_field($block['title']) : '',
                    'formulas'    => array(),
                    'screenshots' => array(),
                );

                // Sanitize formulas
                if (!empty($block['formulas']) && is_array($block['formulas'])) {
                    foreach ($block['formulas'] as $formula) {
                        $entry = array(
                            'id'           => sanitize_text_field($formula['id'] ?? ''),
                            'renderedHtml' => wp_kses_post($formula['renderedHtml'] ?? ''),
                            'latex'        => sanitize_text_field($formula['latex'] ?? ''),
                        );
                        // Formel als PNG-Bild (gleiche Validierung wie Screenshots)
                        $image = $formula['image'] ?? '';
                        if ($image && preg_match('/^(data:image\/(png|jpeg|jpg);base64,)?[A-Za-z0-9+\/=]+$/', $image)) {
                            $entry['image']     = $image;
                            $entry['width']     = intval($formula['width'] ?? 0);
                            $entry['height']    = intval($formula['height'] ?? 0);
                            $entry['isDisplay'] = !empty($formula['isDisplay']);
                        }
                        $sanitized['formulas'][] = $entry;
                    }
                }

                // Sanitize screenshots (base64 data)
                if (!empty($block['screenshots']) && is_array($block['screenshots'])) {
                    foreach ($block['screenshots'] as $screenshot) {
                        $base64 = $screenshot['base64'] ?? '';
                        // Validate base64 image data
                        if (preg_match('/^(data:image\/(png|jpeg|jpg);base64,)?[A-Za-z0-9+\/=]+$/', $base64)) {
                            $sanitized['screenshots'][] = array(
                                'id'     => sanitize_text_field($screenshot['id'] ?? ''),
                                'base64' => $base64,
                            );
                        }
                    }
                }

                $blocks[] = $sanitized;
            }
        } elseif (!empty($_POST['blocks']) && is_array($_POST['blocks'])) {
            // Legacy format: array of HTML strings
            foreach ($_POST['blocks'] as $block_html) {
                $blocks[] = wp_kses_post($block_html);
            }
        } else {
            wp_send_json_error(array('message' => 'Keine Blöcke zum Exportieren gefunden'));
            return;
        }

        if (empty($blocks)) {
            wp_send_json_error(array('message' => 'Keine Blöcke zum Exportieren gefunden'));
            return;
        }

        // PDF-Optionen
        $options = array(
            'filename' => isset($_POST['filename'])
                ? sanitize_file_name($_POST['filename'])
                : 'container-blocks-' . date('Y-m-d') . '.pdf',
            'mode' => isset($_POST['mode'])
                ? sanitize_text_field($_POST['mode'])
                : 'visual',
        );

        // CSS Variables from client
        if (!empty($_POST['css_variables'])) {
            $css_vars_raw = json_decode(stripslashes($_POST['css_variables']), true);
            if (is_array($css_vars_raw)) {
                $options['css_variables'] = array_map('sanitize_text_field', $css_vars_raw);
            }
        }

        // PDF Generator instanziieren
        try {
            $pdf_generator = CBD_PDF_Generator::get_instance();

            // PDF generieren
            $result = $pdf_generator->generate_pdf($blocks, $options);

            if ($result['success']) {
                wp_send_json_success(array(
                    'message'  => 'PDF erfolgreich erstellt',
                    'url'      => $result['url'],
                    'filename' => $result['filename'],
                    'engine'   => $result['engine'] ?? 'unknown',
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['error'] ?? 'Unbekannter Fehler bei der PDF-Erstellung'
                ));
            }
        } catch (\Throwable $e) {
            error_log('[CBD PDF] Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            wp_send_json_error(array(
                'message' => 'PHP-Fehler: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
            ));
        }
    }

    /**
     * Block als Standard setzen
     */
    public function set_default_block() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cbd_set_default_block')) {
            wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen'));
            return;
        }

        // Berechtigungsprüfung
        if (!current_user_can('cbd_admin_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
            return;
        }

        // Block-ID erhalten
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;

        if ($block_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültige Block-ID'));
            return;
        }

        global $wpdb;
        $table_name = CBD_TABLE_BLOCKS;

        // Prüfen ob Block existiert
        $block = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $block_id
        ));

        if (!$block) {
            wp_send_json_error(array('message' => 'Block nicht gefunden'));
            return;
        }

        // Zuerst alle anderen Blocks auf is_default = 0 setzen
        $wpdb->update(
            $table_name,
            array('is_default' => 0),
            array(),
            array('%d'),
            array()
        );

        // Dann den ausgewählten Block als Standard setzen
        $result = $wpdb->update(
            $table_name,
            array('is_default' => 1),
            array('id' => $block_id),
            array('%d'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Block erfolgreich als Standard gesetzt'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Fehler beim Setzen des Standard-Blocks'
            ));
        }
    }
}