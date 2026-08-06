<?php
/**
 * Container-Designs als JSON exportieren und importieren
 *
 * Ersetzt die in v3.1.50 abgeschaltete Import/Export-Seite. Bewusst
 * beschränkt auf **einen** Eingabeweg: eine hochgeladene JSON-Datei.
 * Kein URL-Abruf (SSRF), kein ZIP-Entpacken, kein serverseitiges Ausführen
 * von irgendetwas. Das waren die Gründe, warum die alte Seite entfernt wurde.
 *
 * Der Import läuft zweistufig — hochladen und prüfen, dann bestätigen —,
 * weil er bestehende Designs überschreiben kann. Die Vorschau zeigt vorher,
 * was mit jedem einzelnen Design passiert.
 *
 * Was übertragen wird: die Design-Vorlagen aus {prefix}cbd_blocks.
 * Was NICHT: Seiteninhalte, platzierte Blöcke, Klassen, Zeichnungen.
 *
 * WICHTIG — der Slug (Spalte `name`) entscheidet: Bestehende Seiten
 * referenzieren ihr Design über den Slug. Ein Import unter abweichendem
 * Slug lässt die Container ungestylt.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.78
 */

if (!defined('ABSPATH')) {
    exit;
}

class CBD_Design_Transfer {

    const CAPABILITY   = 'cbd_admin_blocks';
    const PAGE_SLUG    = 'cbd-design-transfer';
    const FORMAT       = 'cbd-designs';
    const FORMAT_VERSION = 1;
    const MAX_BYTES    = 5242880; // 5 MB
    const MAX_DESIGNS  = 500;

    /** Spalten, die exportiert/importiert werden (id und Zeitstempel bewusst nicht). */
    private static $fields = array('name', 'title', 'description', 'config', 'styles', 'features', 'status', 'is_default');

    /**
     * Hooks registrieren.
     */
    public static function init() {
        add_action('admin_post_cbd_designs_export', array(__CLASS__, 'handle_export'));
        add_action('admin_post_cbd_designs_upload', array(__CLASS__, 'handle_upload'));
        add_action('admin_post_cbd_designs_import', array(__CLASS__, 'handle_import'));
    }

    /**
     * URL der Verwaltungsseite.
     *
     * @param array $args
     * @return string
     */
    public static function page_url($args = array()) {
        $url = admin_url('admin.php?page=' . self::PAGE_SLUG);

        return empty($args) ? $url : add_query_arg($args, $url);
    }

    /**
     * Capability und Nonce prüfen.
     *
     * @param string $nonce_action
     */
    private static function guard($nonce_action) {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Du hast keine Berechtigung, Designs zu übertragen.', 'container-block-designer'), 403);
        }

        check_admin_referer($nonce_action);
    }

    // ---------------------------------------------------------------- Export

    /**
     * Alle Designs aus der Datenbank lesen.
     *
     * @return array
     */
    public static function get_all_designs() {
        global $wpdb;

        $table = $wpdb->prefix . 'cbd_blocks';

        $rows = $wpdb->get_results(
            "SELECT id, name, title, description, config, styles, features, status, is_default
             FROM {$table} ORDER BY name ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * Export als JSON-Download ausliefern.
     */
    public static function handle_export() {
        self::guard('cbd_designs_export');

        $selected = isset($_POST['cbd_designs']) ? (array) wp_unslash($_POST['cbd_designs']) : array();
        $selected = array_map('sanitize_text_field', $selected);

        $designs = array();

        foreach (self::get_all_designs() as $row) {
            if (!empty($selected) && !in_array($row['name'], $selected, true)) {
                continue;
            }

            $design = array();
            foreach (self::$fields as $field) {
                $value = isset($row[$field]) ? $row[$field] : '';

                // config/styles/features liegen als JSON-Strings in der DB.
                // Für den Export werden sie dekodiert, damit die Datei
                // lesbar und diff-bar ist statt JSON-in-JSON zu enthalten.
                if (in_array($field, array('config', 'styles', 'features'), true)) {
                    $decoded = json_decode((string) $value, true);
                    $value   = is_array($decoded) ? $decoded : array();
                } elseif ('is_default' === $field) {
                    $value = (int) $value;
                }

                $design[$field] = $value;
            }

            $designs[] = $design;
        }

        if (empty($designs)) {
            wp_safe_redirect(self::page_url(array('cbd_error' => 'noexport')));
            exit;
        }

        $payload = array(
            'format'        => self::FORMAT,
            'formatVersion' => self::FORMAT_VERSION,
            'plugin'        => CBD_VERSION,
            'site'          => home_url('/'),
            'exportedAt'    => gmdate('c'),
            'designs'       => $designs,
        );

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            wp_safe_redirect(self::page_url(array('cbd_error' => 'encode')));
            exit;
        }

        $filename = 'cbd-designs-' . gmdate('Y-m-d') . '-' . count($designs) . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));

        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-Download, kein HTML
        exit;
    }

    // ---------------------------------------------------------------- Import

    /**
     * Transient-Key der zwischengespeicherten Vorschau (pro Benutzer).
     *
     * @return string
     */
    public static function staging_key() {
        return 'cbd_design_import_' . get_current_user_id();
    }

    /**
     * Hochgeladene Datei prüfen und Vorschau vorbereiten (Schritt 1 von 2).
     */
    public static function handle_upload() {
        self::guard('cbd_designs_upload');

        if (empty($_FILES['cbd_import_file']['name'])) {
            self::redirect(array('cbd_error' => 'nofile'));
        }

        $file = $_FILES['cbd_import_file'];

        if (!isset($file['error']) || UPLOAD_ERR_OK !== $file['error']) {
            self::redirect(array('cbd_error' => 'upload'));
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            self::redirect(array('cbd_error' => 'upload'));
        }

        if ($file['size'] > self::MAX_BYTES) {
            self::redirect(array('cbd_error' => 'toobig'));
        }

        $raw = file_get_contents($file['tmp_name']);
        if (false === $raw) {
            self::redirect(array('cbd_error' => 'unreadable'));
        }

        $parsed = self::parse_payload($raw);

        if (!empty($parsed['error'])) {
            set_transient(self::staging_key() . '_error', $parsed['error'], 60);
            self::redirect(array('cbd_error' => 'payload'));
        }

        set_transient(self::staging_key(), $parsed['designs'], 15 * MINUTE_IN_SECONDS);

        self::redirect(array('cbd_preview' => 1));
    }

    /**
     * JSON-Nutzlast prüfen und normalisieren.
     *
     * Streng: unbekannte Felder fliegen raus, Typen werden erzwungen, der
     * Slug muss dem Muster entsprechen, mit dem CBD Blöcke registriert.
     *
     * @param string $raw
     * @return array ['designs' => array, 'error' => string]
     */
    public static function parse_payload($raw) {
        $fail = function ($message) {
            return array('designs' => array(), 'error' => $message);
        };

        $raw = trim((string) $raw);

        if ('' === $raw) {
            return $fail(__('Die Datei ist leer.', 'container-block-designer'));
        }

        // BOM entfernen — Editoren schreiben ihn gern mit
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return $fail(__('Die Datei enthält kein gültiges JSON.', 'container-block-designer'));
        }

        if (!isset($data['format']) || self::FORMAT !== $data['format']) {
            return $fail(__('Das ist keine CDB-Design-Datei (Feld "format" fehlt oder passt nicht).', 'container-block-designer'));
        }

        if (isset($data['formatVersion']) && (int) $data['formatVersion'] > self::FORMAT_VERSION) {
            return $fail(__('Die Datei stammt aus einer neueren Plugin-Version und kann nicht gelesen werden.', 'container-block-designer'));
        }

        if (empty($data['designs']) || !is_array($data['designs'])) {
            return $fail(__('Die Datei enthält keine Designs.', 'container-block-designer'));
        }

        if (count($data['designs']) > self::MAX_DESIGNS) {
            return $fail(__('Die Datei enthält zu viele Designs.', 'container-block-designer'));
        }

        $designs = array();
        $seen    = array();

        foreach ($data['designs'] as $index => $entry) {
            if (!is_array($entry) || empty($entry['name'])) {
                continue;
            }

            $slug = self::sanitize_slug($entry['name']);

            if ('' === $slug) {
                continue;
            }

            // Doppelte Slugs innerhalb einer Datei: der erste gewinnt
            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            $designs[] = array(
                'name'        => $slug,
                'title'       => sanitize_text_field(isset($entry['title']) ? $entry['title'] : $slug),
                'description' => sanitize_textarea_field(isset($entry['description']) ? $entry['description'] : ''),
                'config'      => self::sanitize_json_field(isset($entry['config']) ? $entry['config'] : array()),
                'styles'      => self::sanitize_json_field(isset($entry['styles']) ? $entry['styles'] : array()),
                'features'    => self::sanitize_json_field(isset($entry['features']) ? $entry['features'] : array()),
                'status'      => (isset($entry['status']) && 'inactive' === $entry['status']) ? 'inactive' : 'active',
                'is_default'  => !empty($entry['is_default']) ? 1 : 0,
            );
        }

        if (empty($designs)) {
            return $fail(__('Keines der enthaltenen Designs war verwertbar.', 'container-block-designer'));
        }

        return array('designs' => $designs, 'error' => '');
    }

    /**
     * Slug auf das Muster bringen, das die Block-Registrierung erwartet.
     *
     * @param string $raw
     * @return string
     */
    public static function sanitize_slug($raw) {
        $slug = strtolower(remove_accents((string) $raw));
        $slug = str_replace(array('ä', 'ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $slug);
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-{2,}/', '-', $slug);
        $slug = trim($slug, '-');

        return substr($slug, 0, 100);
    }

    /**
     * config/styles/features normalisieren.
     *
     * Akzeptiert Array (aus einem Export) oder JSON-String (handgeschrieben)
     * und gibt immer einen JSON-String für die DB-Spalte zurück. Verschachtelte
     * Werte werden rekursiv als Text bereinigt — die Spalten landen später in
     * generiertem CSS und HTML.
     *
     * @param mixed $value
     * @return string
     */
    private static function sanitize_json_field($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : array();
        }

        if (!is_array($value)) {
            $value = array();
        }

        return wp_json_encode(self::sanitize_recursive($value));
    }

    /**
     * Rekursive Textbereinigung für die JSON-Spalten.
     *
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    private static function sanitize_recursive($value, $depth = 0) {
        if ($depth > 10) {
            return '';
        }

        if (is_array($value)) {
            $out = array();
            foreach ($value as $key => $item) {
                $clean_key       = is_int($key) ? $key : sanitize_text_field((string) $key);
                $out[$clean_key] = self::sanitize_recursive($item, $depth + 1);
            }
            return $out;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || null === $value) {
            return $value;
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Vorschau: was würde der Import mit jedem Design tun?
     *
     * @param array $designs
     * @return array
     */
    public static function build_preview($designs) {
        $existing = array();

        foreach (self::get_all_designs() as $row) {
            $existing[$row['name']] = $row;
        }

        $preview = array();

        foreach ($designs as $design) {
            $preview[] = array(
                'name'     => $design['name'],
                'title'    => $design['title'],
                'conflict' => isset($existing[$design['name']]),
                'existing' => isset($existing[$design['name']]) ? $existing[$design['name']]['title'] : '',
            );
        }

        return $preview;
    }

    /**
     * Import ausführen (Schritt 2 von 2).
     */
    public static function handle_import() {
        self::guard('cbd_designs_import');

        $designs = get_transient(self::staging_key());

        if (!is_array($designs) || empty($designs)) {
            self::redirect(array('cbd_error' => 'expired'));
        }

        $mode = isset($_POST['cbd_conflict_mode']) ? sanitize_key(wp_unslash($_POST['cbd_conflict_mode'])) : 'skip';
        if (!in_array($mode, array('skip', 'overwrite', 'copy'), true)) {
            $mode = 'skip';
        }

        $chosen = isset($_POST['cbd_designs']) ? (array) wp_unslash($_POST['cbd_designs']) : array();
        $chosen = array_map('sanitize_text_field', $chosen);

        global $wpdb;
        $table = $wpdb->prefix . 'cbd_blocks';

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($designs as $design) {
            if (!empty($chosen) && !in_array($design['name'], $chosen, true)) {
                $skipped++;
                continue;
            }

            $exists = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$table} WHERE name = %s", $design['name'])
            );

            if ($exists) {
                if ('skip' === $mode) {
                    $skipped++;
                    continue;
                }

                if ('overwrite' === $mode) {
                    $wpdb->update(
                        $table,
                        array(
                            'title'       => $design['title'],
                            'description' => $design['description'],
                            'config'      => $design['config'],
                            'styles'      => $design['styles'],
                            'features'    => $design['features'],
                            'status'      => $design['status'],
                        ),
                        array('id' => (int) $exists),
                        array('%s', '%s', '%s', '%s', '%s', '%s'),
                        array('%d')
                    );
                    $updated++;
                    continue;
                }

                // copy: neuen, freien Slug suchen
                $design['name']  = self::find_free_slug($design['name']);
                $design['title'] = $design['title'] . ' (Kopie)';
            }

            $wpdb->insert(
                $table,
                array(
                    'name'        => $design['name'],
                    'title'       => $design['title'],
                    'description' => $design['description'],
                    'config'      => $design['config'],
                    'styles'      => $design['styles'],
                    'features'    => $design['features'],
                    'status'      => $design['status'],
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            $created++;
        }

        delete_transient(self::staging_key());
        self::flush_caches();

        self::redirect(array(
            'cbd_created' => $created,
            'cbd_updated' => $updated,
            'cbd_skipped' => $skipped,
        ));
    }

    /**
     * Freien Slug finden (mein-design -> mein-design-2, -3, …).
     *
     * @param string $slug
     * @return string
     */
    private static function find_free_slug($slug) {
        global $wpdb;

        $table = $wpdb->prefix . 'cbd_blocks';
        $base  = substr($slug, 0, 90);

        for ($i = 2; $i < 500; $i++) {
            $candidate = $base . '-' . $i;

            $taken = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$table} WHERE name = %s", $candidate)
            );

            if (!$taken) {
                return $candidate;
            }
        }

        return $base . '-' . wp_rand(1000, 9999);
    }

    /**
     * Caches leeren, damit importierte Designs sofort greifen.
     *
     * Die Blockliste und das generierte CSS werden zwischengespeichert; ohne
     * das Leeren wären neue Designs erst nach Ablauf sichtbar.
     */
    private static function flush_caches() {
        if (class_exists('CBD_Style_Loader') && method_exists('CBD_Style_Loader', 'get_instance')) {
            $loader = CBD_Style_Loader::get_instance();
            if (method_exists($loader, 'clear_styles_cache')) {
                $loader->clear_styles_cache();
            }
        }

        // Blocklisten-Cache der Registrierung über deren eigene Methode leeren
        // (der Transient-Key ist dort eine Klassenkonstante).
        if (method_exists('CBD_Block_Registration', 'clear_blocks_cache')) {
            CBD_Block_Registration::clear_blocks_cache();
        }
    }

    /**
     * Zurück zur Verwaltungsseite.
     *
     * @param array $args
     */
    private static function redirect($args) {
        wp_safe_redirect(self::page_url($args));
        exit;
    }
}
