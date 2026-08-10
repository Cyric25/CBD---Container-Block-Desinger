<?php
/**
 * Container-Designs als Markdown oder JSON exportieren und importieren
 *
 * Ersetzt die in v3.1.50 abgeschaltete Import/Export-Seite. Bewusst
 * beschränkt auf **einen** Eingabeweg: eine hochgeladene Datei.
 * Kein URL-Abruf (SSRF), kein ZIP-Entpacken, kein serverseitiges Ausführen
 * von irgendetwas. Das waren die Gründe, warum die alte Seite entfernt wurde.
 *
 * Zwei Dateiformate, ein Verarbeitungsweg:
 *
 * - **Markdown** (Standard, seit 3.1.79) — ein H2-Abschnitt je Design,
 *   Werte als flache Punkt-Pfade (`border.color: #e24614`). Lesbar,
 *   diff-bar, von Hand schreib- und änderbar.
 * - **JSON** — das ursprüngliche Format aus 3.1.78, bleibt lesbar und
 *   schreibbar, damit bereits exportierte Dateien nutzbar bleiben.
 *
 * Beim Import entscheidet der Dateiinhalt, nicht die Endung: beginnt die
 * Datei mit `{`, wird sie als JSON gelesen, sonst als Markdown. Beide Wege
 * münden in dieselbe Normalisierung (`normalize_designs()`) — die
 * Eingangsprüfung ist also für beide Formate identisch.
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
 * Die Markierung „Standard" (`is_default`) wird exportiert, aber beim Import
 * bewusst NICHT geschrieben: pro Installation darf es nur ein Standard-Design
 * geben, und welches das ist, entscheidet die Zielinstallation selbst
 * (siehe CBD_Ajax_Handler::…set_default…).
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

    /** Maximale Verschachtelung in config/styles/features. */
    const MAX_DEPTH    = 10;

    /** Spalten, die exportiert/importiert werden (id und Zeitstempel bewusst nicht). */
    private static $fields = array('name', 'title', 'description', 'config', 'styles', 'features', 'status', 'is_default');

    /** DB-Spalte => Überschrift des Wertabschnitts in der Markdown-Datei. */
    private static $md_sections = array(
        'config'   => 'Konfiguration',
        'styles'   => 'Stile',
        'features' => 'Funktionen',
    );

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
     * Designs für den Export einsammeln und aufbereiten.
     *
     * config/styles/features liegen als JSON-Strings in der DB und werden
     * hier dekodiert — beide Ausgabeformate wollen die echten Strukturen,
     * nicht JSON-in-JSON.
     *
     * @param array $selected Slugs; leer = alle
     * @return array
     */
    private static function collect_designs($selected = array()) {
        $designs = array();

        foreach (self::get_all_designs() as $row) {
            if (!empty($selected) && !in_array($row['name'], $selected, true)) {
                continue;
            }

            $design = array();
            foreach (self::$fields as $field) {
                $value = isset($row[$field]) ? $row[$field] : '';

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

        return $designs;
    }

    /**
     * Export als Download ausliefern (Markdown oder JSON).
     */
    public static function handle_export() {
        self::guard('cbd_designs_export');

        $format = (isset($_POST['cbd_format']) && 'json' === sanitize_key(wp_unslash($_POST['cbd_format'])))
            ? 'json'
            : 'md';

        $selected = isset($_POST['cbd_designs']) ? (array) wp_unslash($_POST['cbd_designs']) : array();
        $selected = array_map('sanitize_text_field', $selected);

        $designs = self::collect_designs($selected);

        if (empty($designs)) {
            wp_safe_redirect(self::page_url(array('cbd_error' => 'noexport')));
            exit;
        }

        $meta = array(
            'plugin'     => defined('CBD_VERSION') ? CBD_VERSION : '',
            'site'       => home_url('/'),
            'exportedAt' => gmdate('c'),
        );

        if ('json' === $format) {
            $payload = array_merge(
                array(
                    'format'        => self::FORMAT,
                    'formatVersion' => self::FORMAT_VERSION,
                ),
                $meta,
                array('designs' => $designs)
            );

            $body = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (false === $body) {
                wp_safe_redirect(self::page_url(array('cbd_error' => 'encode')));
                exit;
            }

            $extension = 'json';
            $mime      = 'application/json';
        } else {
            $body      = self::to_markdown($designs, $meta);
            $extension = 'md';
            $mime      = 'text/markdown';
        }

        $filename = 'cbd-designs-' . gmdate('Y-m-d') . '-' . count($designs) . '.' . $extension;

        nocache_headers();
        header('Content-Type: ' . $mime . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($body));

        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- Datei-Download, kein HTML
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

        $parsed = self::parse_file($raw);

        if (!empty($parsed['error'])) {
            set_transient(self::staging_key() . '_error', $parsed['error'], 60);
            self::redirect(array('cbd_error' => 'payload'));
        }

        set_transient(self::staging_key(), $parsed['designs'], 15 * MINUTE_IN_SECONDS);

        self::redirect(array('cbd_preview' => 1));
    }

    /**
     * Hochgeladene Datei lesen — Markdown oder JSON.
     *
     * Die Weiche stellt der Inhalt, nicht die Dateiendung: eine Datei, die
     * mit `{` beginnt, ist JSON, alles andere wird als Markdown gelesen.
     * Eine falsch benannte Datei soll nicht am Namen scheitern.
     *
     * @param string $raw
     * @return array ['designs' => array, 'error' => string]
     */
    public static function parse_file($raw) {
        $trimmed = self::strip_bom(trim((string) $raw));

        if ('' === $trimmed) {
            return array(
                'designs' => array(),
                'error'   => __('Die Datei ist leer.', 'container-block-designer'),
            );
        }

        return ('{' === $trimmed[0]) ? self::parse_payload($raw) : self::parse_markdown($raw);
    }

    /**
     * BOM entfernen — Editoren schreiben ihn gern mit.
     *
     * @param string $raw
     * @return string
     */
    private static function strip_bom($raw) {
        return preg_replace('/^\xEF\xBB\xBF/', '', (string) $raw);
    }

    /**
     * Fehlerantwort im einheitlichen Rückgabeformat.
     *
     * @param string $message
     * @return array
     */
    private static function fail($message) {
        return array('designs' => array(), 'error' => $message);
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
        $raw = self::strip_bom(trim((string) $raw));

        if ('' === $raw) {
            return self::fail(__('Die Datei ist leer.', 'container-block-designer'));
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return self::fail(__('Die Datei enthält kein gültiges JSON.', 'container-block-designer'));
        }

        if (!isset($data['format']) || self::FORMAT !== $data['format']) {
            return self::fail(__('Das ist keine CDB-Design-Datei (Feld "format" fehlt oder passt nicht).', 'container-block-designer'));
        }

        if (isset($data['formatVersion']) && (int) $data['formatVersion'] > self::FORMAT_VERSION) {
            return self::fail(__('Die Datei stammt aus einer neueren Plugin-Version und kann nicht gelesen werden.', 'container-block-designer'));
        }

        if (empty($data['designs']) || !is_array($data['designs'])) {
            return self::fail(__('Die Datei enthält keine Designs.', 'container-block-designer'));
        }

        if (count($data['designs']) > self::MAX_DESIGNS) {
            return self::fail(__('Die Datei enthält zu viele Designs.', 'container-block-designer'));
        }

        $designs = self::normalize_designs($data['designs']);

        if (empty($designs)) {
            return self::fail(__('Keines der enthaltenen Designs war verwertbar.', 'container-block-designer'));
        }

        return array('designs' => $designs, 'error' => '');
    }

    /**
     * Rohe Design-Einträge auf die DB-Form bringen.
     *
     * Einziger Normalisierungspfad für **beide** Dateiformate: unbekannte
     * Felder fliegen raus, Typen werden erzwungen, der Slug muss dem Muster
     * entsprechen, mit dem CBD Blöcke registriert. Was hier nicht durchkommt,
     * kommt nirgends durch.
     *
     * @param array $entries
     * @return array
     */
    private static function normalize_designs($entries) {
        $designs = array();
        $seen    = array();

        foreach ($entries as $entry) {
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

        return $designs;
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
        if ($depth > self::MAX_DEPTH) {
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

    // -------------------------------------------------------------- Markdown

    /**
     * Designs als Markdown-Dokument schreiben.
     *
     * Aufbau: ein `##`-Abschnitt je Design, darin die Stammdaten als
     * `- **Label:** Wert`, danach drei `###`-Abschnitte mit den Werten aus
     * config/styles/features als flache Punkt-Pfade.
     *
     * Verschachtelte Strukturen werden flachgeklopft (`border.color`), weil
     * eine eingerückte Baumdarstellung zwar hübscher aussähe, sich aber von
     * Hand kaum fehlerfrei schreiben lässt — und genau das ist der Zweck
     * dieses Formats.
     *
     * @param array $designs Designs mit dekodierten config/styles/features
     * @param array $meta    optional: plugin, site, exportedAt
     * @return string
     */
    public static function to_markdown($designs, $meta = array()) {
        $out = array();

        $out[] = '# Container-Designs';
        $out[] = '';
        $out[] = '<!-- ' . self::FORMAT . ' formatVersion=' . self::FORMAT_VERSION . ' -->';
        $out[] = '';

        $herkunft = array();
        if (!empty($meta['site'])) {
            $herkunft[] = $meta['site'];
        }
        if (!empty($meta['plugin'])) {
            $herkunft[] = 'Plugin ' . $meta['plugin'];
        }
        if (!empty($meta['exportedAt'])) {
            $herkunft[] = 'exportiert ' . substr((string) $meta['exportedAt'], 0, 10);
        }
        if (!empty($herkunft)) {
            $out[] = 'Quelle: ' . implode(' · ', $herkunft);
            $out[] = '';
        }

        $out[] = 'Design-Vorlagen des Container Block Designers. Diese Datei kann von Hand';
        $out[] = 'geändert und wieder importiert werden.';
        $out[] = '';
        $out[] = '- Jede `##`-Überschrift ist ein Design; der **Slug** darunter entscheidet,';
        $out[] = '  welches Design auf bestehenden Seiten getroffen wird.';
        $out[] = '- Werte stehen als `pfad.zum.schluessel: wert`.';
        $out[] = '- `true`/`false` (auch `ja`/`nein`) sind Wahrheitswerte, reine Zahlen sind';
        $out[] = '  Zahlen, alles andere ist Text. `"true"` in Anführungszeichen erzwingt Text.';
        $out[] = '- **Standard** wird beim Import nicht übernommen — welches Design das';
        $out[] = '  Standarddesign ist, bleibt Sache der Zielinstallation.';

        foreach ($designs as $design) {
            $title = isset($design['title']) ? trim((string) $design['title']) : '';
            $name  = isset($design['name']) ? (string) $design['name'] : '';

            $out[] = '';
            $out[] = '---';
            $out[] = '';
            $out[] = '## ' . ('' !== $title ? $title : $name);
            $out[] = '';
            $out[] = '- **Slug:** `' . $name . '`';
            $out[] = '- **Status:** ' . ((isset($design['status']) && 'inactive' === $design['status']) ? 'inaktiv' : 'aktiv');
            $out[] = '- **Standard:** ' . (!empty($design['is_default']) ? 'ja' : 'nein');

            $description = isset($design['description']) ? trim((string) $design['description']) : '';
            if ('' !== $description) {
                $out[] = '';
                foreach (preg_split('/\R/', $description) as $line) {
                    // Führendes # würde beim Wiedereinlesen als Überschrift
                    // gelten und den Abschnitt zerschneiden. Das \\\\ ergibt in
                    // der Ersetzung genau einen Backslash.
                    $out[] = preg_replace('/^(\s*)#/', '$1\\\\#', $line);
                }
            }

            foreach (self::$md_sections as $field => $heading) {
                $values = isset($design[$field]) ? $design[$field] : array();

                if (is_string($values)) {
                    $decoded = json_decode($values, true);
                    $values  = is_array($decoded) ? $decoded : array();
                }

                $flat = is_array($values) ? self::flatten($values) : array();

                $out[] = '';
                $out[] = '### ' . $heading;
                $out[] = '';

                if (empty($flat)) {
                    $out[] = '_(keine Angaben)_';
                    continue;
                }

                foreach ($flat as $key => $value) {
                    $out[] = '- `' . $key . '`: ' . self::md_write_value($value);
                }
            }
        }

        return implode("\n", $out) . "\n";
    }

    /**
     * Markdown-Datei lesen.
     *
     * Bewusst tolerant: fehlt der Slug, wird er aus der Überschrift gebildet;
     * unbekannte `###`-Abschnitte werden übergangen statt die Datei
     * abzulehnen. Streng wird erst `normalize_designs()`.
     *
     * @param string $raw
     * @return array ['designs' => array, 'error' => string]
     */
    public static function parse_markdown($raw) {
        $raw = self::strip_bom(trim((string) $raw));

        if ('' === $raw) {
            return self::fail(__('Die Datei ist leer.', 'container-block-designer'));
        }

        $lines = preg_split('/\R/', $raw);

        $entries = array();
        $heading = null;
        $buffer  = array();

        foreach ($lines as $line) {
            if (preg_match('/^##(?!#)\s*(.+)$/', $line, $match)) {
                if (null !== $heading) {
                    $entries[] = self::parse_markdown_section($heading, $buffer);
                }
                $heading = trim($match[1]);
                $buffer  = array();
                continue;
            }

            if (null !== $heading) {
                $buffer[] = $line;
            }
        }

        if (null !== $heading) {
            $entries[] = self::parse_markdown_section($heading, $buffer);
        }

        if (empty($entries)) {
            return self::fail(__('Die Datei enthält keine Designs — erwartet wird je Design eine Überschrift der Ebene 2 („## Name").', 'container-block-designer'));
        }

        if (count($entries) > self::MAX_DESIGNS) {
            return self::fail(__('Die Datei enthält zu viele Designs.', 'container-block-designer'));
        }

        $designs = self::normalize_designs($entries);

        if (empty($designs)) {
            return self::fail(__('Keines der enthaltenen Designs war verwertbar.', 'container-block-designer'));
        }

        return array('designs' => $designs, 'error' => '');
    }

    /**
     * Einen `##`-Abschnitt zu einem rohen Design-Eintrag verarbeiten.
     *
     * @param string $heading
     * @param array  $lines
     * @return array
     */
    private static function parse_markdown_section($heading, $lines) {
        $entry = array(
            'name'        => '',
            'title'       => trim($heading),
            'description' => '',
            'config'      => array(),
            'styles'      => array(),
            'features'    => array(),
            'status'      => 'active',
            'is_default'  => 0,
        );

        $pairs = array('config' => array(), 'styles' => array(), 'features' => array());
        $free  = array();
        $meta_description = null;
        $section = 'head';

        foreach ($lines as $line) {
            if (preg_match('/^###+\s*(.+)$/', $line, $match)) {
                $section = self::md_section_for(trim($match[1]));
                continue;
            }

            $trimmed = trim($line);

            if ('' === $trimmed || preg_match('/^([-*_])\1{2,}$/', $trimmed)) {
                if ('head' === $section && !empty($free)) {
                    $free[] = '';
                }
                continue;
            }

            if ('head' === $section) {
                // Stammdaten sind an der Fettschrift erkennbar; alles andere
                // im Kopfbereich ist Beschreibungstext. Dadurch überlebt eine
                // Beschreibung, die selbst mit „- " beginnt.
                // Kein /u: die Beschriftungen sind ASCII, und eine Datei mit
                // kaputtem UTF-8 soll nicht dazu führen, dass sämtliche
                // Stammdaten stillschweigend als Beschreibung gelesen werden.
                if (preg_match('/^[-*+]\s*\*\*\s*([^*:]+?)\s*:?\s*\*\*\s*:?\s*(.*)$/', $trimmed, $match)) {
                    $label = $match[1];
                    $value = self::md_strip_wrappers(trim($match[2]));

                    switch (self::md_label_key($label)) {
                        case 'slug':
                            $entry['name'] = $value;
                            break;
                        case 'titel':
                            if ('' !== $value) {
                                $entry['title'] = $value;
                            }
                            break;
                        case 'status':
                            $entry['status'] = self::md_is_inactive($value) ? 'inactive' : 'active';
                            break;
                        case 'standard':
                            $entry['is_default'] = self::md_is_true($value) ? 1 : 0;
                            break;
                        case 'beschreibung':
                            $meta_description = $value;
                            break;
                    }
                    continue;
                }

                $free[] = preg_replace('/^(\s*)\\\\#/', '$1#', $line);
                continue;
            }

            if (!isset($pairs[$section])) {
                continue; // unbekannter ###-Abschnitt
            }

            if (preg_match('/^[-*+]\s*`?([A-Za-z0-9_.\-]+)`?\s*:\s*(.*)$/', $trimmed, $match)) {
                $pairs[$section][$match[1]] = self::md_read_value($match[2]);
            }
        }

        if (null !== $meta_description) {
            $entry['description'] = $meta_description;
        } else {
            $entry['description'] = trim(implode("\n", $free));
        }

        if ('' === $entry['name']) {
            $entry['name'] = $entry['title'];
        }

        foreach ($pairs as $field => $flat) {
            $entry[$field] = self::unflatten($flat);
        }

        return $entry;
    }

    /**
     * Überschrift eines Wertabschnitts einem DB-Feld zuordnen.
     *
     * @param string $heading
     * @return string config|styles|features|ignore
     */
    private static function md_section_for($heading) {
        $key = strtolower(remove_accents($heading));

        if (false !== strpos($key, 'konfig') || false !== strpos($key, 'config')) {
            return 'config';
        }

        if (false !== strpos($key, 'stil') || false !== strpos($key, 'style')) {
            return 'styles';
        }

        if (false !== strpos($key, 'funktion') || false !== strpos($key, 'feature')) {
            return 'features';
        }

        return 'ignore';
    }

    /**
     * Beschriftung einer Stammdatenzeile auf einen Schlüssel normalisieren.
     *
     * @param string $label
     * @return string
     */
    private static function md_label_key($label) {
        $key = strtolower(trim(remove_accents($label)));

        $map = array(
            'slug'         => 'slug',
            'name'         => 'slug',
            'titel'        => 'titel',
            'title'        => 'titel',
            'status'       => 'status',
            'standard'     => 'standard',
            'standarddesign' => 'standard',
            'default'      => 'standard',
            'beschreibung' => 'beschreibung',
            'description'  => 'beschreibung',
        );

        return isset($map[$key]) ? $map[$key] : '';
    }

    /**
     * @param string $value
     * @return bool
     */
    private static function md_is_inactive($value) {
        $key = strtolower(trim(remove_accents($value)));

        return in_array($key, array('inaktiv', 'inactive', 'aus', 'deaktiviert', 'nein', 'false', '0'), true);
    }

    /**
     * @param string $value
     * @return bool
     */
    private static function md_is_true($value) {
        $key = strtolower(trim($value));

        return in_array($key, array('ja', 'yes', 'true', '1', 'wahr'), true);
    }

    /**
     * Backticks und Fettschrift um einen Stammdatenwert entfernen.
     *
     * @param string $value
     * @return string
     */
    private static function md_strip_wrappers($value) {
        $value = trim((string) $value);
        $value = preg_replace('/^\*\*(.*)\*\*$/s', '$1', $value);
        $value = trim($value);

        if (strlen($value) >= 2 && '`' === $value[0] && '`' === substr($value, -1)) {
            $value = trim(substr($value, 1, -1));
        }

        return $value;
    }

    /**
     * Wert für die Datei schreiben.
     *
     * Die Regel steht nicht doppelt im Code: geschrieben wird roh, und wenn
     * md_read_value() daraus nicht exakt denselben Text macht, kommen
     * Anführungszeichen drum. So können Schreiben und Lesen nicht
     * auseinanderlaufen — aus dem Text „true" wird beim Import garantiert
     * wieder der Text „true" und kein Wahrheitswert.
     *
     * Einzige bewusste Ausnahme: Zahlen bleiben unquotiert, auch wenn sie in
     * der DB als Zeichenkette liegen (aus $_POST kommt alles als String).
     * `padding.top: "20"` wäre in einer Datei, die von Hand lesbar sein soll,
     * schlicht Lärm; für die CSS-Erzeugung sind "20" und 20 dasselbe.
     *
     * @param mixed $value
     * @return string
     */
    private static function md_write_value($value) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (null === $value) {
            return '""';
        }

        $text = trim(str_replace(array("\r", "\n"), ' ', (string) $value));

        if ('' === $text) {
            return '""';
        }

        $read = self::md_read_value($text);

        if (is_int($read) || is_float($read)) {
            return $text;
        }

        return ($text === $read) ? $text : '"' . $text . '"';
    }

    /**
     * Wert aus der Datei lesen.
     *
     * Der Rest der Zeile ist der Wert — auch mit Doppelpunkten darin, sonst
     * überlebte der Icon-Wert `{"type":"custom",…}` das Wiedereinlesen nicht.
     *
     * @param string $raw
     * @return mixed
     */
    private static function md_read_value($raw) {
        $value = trim((string) $raw);

        if (strlen($value) >= 2 && '`' === $value[0] && '`' === substr($value, -1)) {
            $value = trim(substr($value, 1, -1));
        }

        // Anführungszeichen erzwingen Text
        if (strlen($value) >= 2 && '"' === $value[0] && '"' === substr($value, -1)) {
            return substr($value, 1, -1);
        }

        $lower = strtolower($value);

        if (in_array($lower, array('true', 'ja', 'wahr'), true)) {
            return true;
        }

        if (in_array($lower, array('false', 'nein'), true)) {
            return false;
        }

        // Der Rückweg muss exakt denselben Text ergeben — sonst bliebe aus
        // "0.10" eine 0.1 und aus einer sehr langen Ziffernfolge eine durch
        // den Wertebereich verstümmelte Zahl. filter_var statt (int)/(float):
        // der Cast einer zu großen Ziffernfolge warnt ab PHP 8.1 und schriebe
        // diese Warnung bei jedem Import ins Debug-Log.
        if (preg_match('/^-?(0|[1-9][0-9]*)$/', $value)) {
            $number = filter_var($value, FILTER_VALIDATE_INT);

            if (false !== $number && (string) $number === $value) {
                return $number;
            }
        }

        if (preg_match('/^-?(0|[1-9][0-9]*)\.[0-9]+$/', $value)) {
            $number = filter_var($value, FILTER_VALIDATE_FLOAT);

            if (false !== $number && (string) $number === $value) {
                return $number;
            }
        }

        return $value;
    }

    /**
     * Verschachteltes Array zu Punkt-Pfaden flachklopfen.
     *
     * Leere Zweige entfallen — eine Zeile `padding:` ohne Wert wäre beim
     * Wiedereinlesen bedeutungslos.
     *
     * @param mixed  $value
     * @param string $prefix
     * @param int    $depth
     * @return array
     */
    private static function flatten($value, $prefix = '', $depth = 0) {
        $out = array();

        if (!is_array($value) || $depth > self::MAX_DEPTH) {
            return $out;
        }

        foreach ($value as $key => $item) {
            $path = ('' === $prefix) ? (string) $key : $prefix . '.' . $key;

            if (is_array($item)) {
                foreach (self::flatten($item, $path, $depth + 1) as $sub_key => $sub_value) {
                    $out[$sub_key] = $sub_value;
                }
                continue;
            }

            $out[$path] = $item;
        }

        return $out;
    }

    /**
     * Punkt-Pfade wieder zu einem verschachtelten Array zusammensetzen.
     *
     * Jedes Pfadsegment wird auf `[A-Za-z0-9_-]` beschränkt — dieselbe
     * Absicht wie bei sanitize_slug(): aus einer Datei kommt kein Schlüssel,
     * der irgendwo anders hinzeigt.
     *
     * @param array $pairs
     * @return array
     */
    private static function unflatten($pairs) {
        $out = array();

        foreach ($pairs as $path => $value) {
            $segments = array();

            foreach (explode('.', (string) $path) as $segment) {
                $segment = preg_replace('/[^A-Za-z0-9_-]/', '', $segment);

                if ('' !== $segment) {
                    $segments[] = $segment;
                }
            }

            if (empty($segments) || count($segments) > self::MAX_DEPTH) {
                continue;
            }

            $leaf = array_pop($segments);
            $ref  = &$out;

            foreach ($segments as $segment) {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = array();
                }
                $ref = &$ref[$segment];
            }

            $ref[$leaf] = $value;
            unset($ref);
        }

        return $out;
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
