<?php
/**
 * Eigene SVG-Icon-Bibliothek (Kachel-Icons)
 *
 * Die Icons werden NICHT im Code gepflegt, sondern zur Laufzeit aus dem
 * Dateisystem gelesen. Neue Icons hinzufügen oder bestehende ersetzen heißt
 * also: SVG-Datei ablegen bzw. überschreiben — kein Code-Update nötig.
 *
 * Zwei Quellen, in dieser Reihenfolge:
 *   1. Plugin:   assets/icons/<gruppe>/<name>.svg   (mit dem Plugin ausgeliefert)
 *   2. Override: wp-content/uploads/cbd-icons/<gruppe>/<name>.svg
 *
 * Gleichnamige Dateien aus dem Override-Verzeichnis gewinnen. Damit lassen
 * sich Icons austauschen, ohne ein neues Plugin-ZIP zu bauen.
 *
 * Cache-Busting läuft über filemtime() als ?ver= — eine ersetzte Datei ist
 * sofort sichtbar, auch wenn der Browser die alte Version gecacht hat.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.77
 */

if (!defined('ABSPATH')) {
    exit;
}

class CBD_Icon_Library {

    /**
     * Bekannte Gruppen (= Unterordner). Bewusst eine feste Liste: sie
     * bestimmt Reihenfolge und Beschriftung der Kategorien im Icon-Picker
     * und verhindert nebenbei Verzeichnis-Traversal über den Gruppennamen.
     */
    const GROUPS = array(
        'kategorien' => 'Kategorien',
        'zahlen'     => 'Zahlen',
        'ui'         => 'Symbole',
    );

    const TRANSIENT = 'cbd_icon_library_index';

    /** @var array|null Laufzeit-Cache innerhalb eines Requests */
    private static $index = null;

    /**
     * Verzeichnisse, aus denen Icons gelesen werden.
     *
     * @return array Liste aus ['dir' => absoluter Pfad, 'url' => Basis-URL]
     */
    private static function get_sources() {
        $sources = array(
            array(
                'dir'  => CBD_PLUGIN_DIR . 'assets/icons/',
                'url'  => CBD_PLUGIN_URL . 'assets/icons/',
                'type' => 'plugin',
            ),
        );

        $override = self::get_override_dir();
        if ('' !== $override) {
            $uploads    = wp_get_upload_dir();
            $sources[] = array(
                'dir'  => $override,
                'url'  => trailingslashit($uploads['baseurl']) . 'cbd-icons/',
                'type' => 'override',
            );
        }

        /**
         * Filter: weitere Icon-Quellen (z. B. aus einem Child-Theme).
         *
         * @param array $sources
         */
        return apply_filters('cbd_icon_library_sources', $sources);
    }

    /**
     * Absoluter Pfad des Override-Verzeichnisses in uploads/.
     *
     * Hier landen die über die Admin-Seite hochgeladenen Icons. Sie
     * überschreiben gleichnamige Plugin-Icons und überleben ein
     * Plugin-Update, weil sie außerhalb des Plugin-Ordners liegen.
     *
     * @return string Leerstring, wenn das Upload-Verzeichnis nicht nutzbar ist
     */
    public static function get_override_dir() {
        $uploads = wp_get_upload_dir();

        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }

        return trailingslashit($uploads['basedir']) . 'cbd-icons/';
    }

    /**
     * Liest den Icon-Bestand aus dem Dateisystem.
     *
     * @return array [gruppe => [name => ['url' => …, 'ver' => …]]]
     */
    public static function get_index() {
        if (self::$index !== null) {
            return self::$index;
        }

        // Im Admin wird bewusst NICHT aus dem Cache gelesen, sondern immer neu
        // gescannt: dort werden Icons ausgetauscht, und der Picker muss den
        // aktuellen Stand zeigen. Der frische Scan schreibt den Transient neu
        // und aktualisiert damit nebenbei den Frontend-Cache — ein Aufruf der
        // Block-Verwaltung genügt also, damit neue Icons überall greifen.
        if (!is_admin()) {
            $cached = get_transient(self::get_transient_key());
            if (is_array($cached)) {
                self::$index = $cached;
                return self::$index;
            }
        }

        $index = array();

        foreach (array_keys(self::GROUPS) as $group) {
            $index[$group] = array();
        }

        foreach (self::get_sources() as $source) {
            foreach (array_keys(self::GROUPS) as $group) {
                $dir = trailingslashit($source['dir']) . $group;
                if (!is_dir($dir)) {
                    continue;
                }

                $files = glob($dir . '/*.svg');
                if (!is_array($files)) {
                    continue;
                }

                foreach ($files as $file) {
                    $name = basename($file, '.svg');

                    // Nur unbedenkliche Dateinamen zulassen — der Name landet
                    // später in einer URL und in gespeicherten Block-Designs.
                    if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $name)) {
                        continue;
                    }

                    $mtime = @filemtime($file);

                    // Spätere Quelle überschreibt frühere (Override-Prinzip).
                    $overrides = isset($index[$group][$name]);

                    $index[$group][$name] = array(
                        'url'       => trailingslashit($source['url']) . $group . '/' . $name . '.svg',
                        'ver'       => $mtime ? (string) $mtime : (string) CBD_VERSION,
                        'source'    => isset($source['type']) ? $source['type'] : 'plugin',
                        // true = ersetzt ein gleichnamiges Plugin-Icon
                        'overrides' => $overrides,
                    );
                }
            }
        }

        // Zahlen numerisch sortieren, alles andere alphabetisch.
        foreach ($index as $group => $icons) {
            if ('zahlen' === $group) {
                uksort($index[$group], function ($a, $b) {
                    return (int) $a - (int) $b;
                });
            } else {
                ksort($index[$group]);
            }
        }

        set_transient(self::get_transient_key(), $index, DAY_IN_SECONDS);
        self::$index = $index;

        return $index;
    }

    /**
     * Cache-Key inklusive Plugin-Version — ein Plugin-Update, das neue Icons
     * mitbringt, entwertet den alten Bestand damit automatisch.
     *
     * @return string
     */
    private static function get_transient_key() {
        return self::TRANSIENT . '_' . CBD_VERSION;
    }

    /**
     * Erlaubte Icon-Typen. 'emoji' ist Legacy (Auswahl entfernt in v3.1.77),
     * wird aber weiter gelesen, damit bestehende Designs rendern.
     */
    const TYPES = array('dashicons', 'fontawesome', 'material', 'lucide', 'custom', 'emoji');

    /**
     * Kanonisches Parsen des gespeicherten Icon-Werts.
     *
     * EINZIGE Stelle, an der das Format interpretiert wird — vorher lag die
     * Logik doppelt in CBD_Block_Registration und in der Admin-Vorschau.
     *
     * Format: JSON {"type":"…","value":"…"} oder Legacy "dashicons-foo".
     *
     * **Slash-Reparatur:** Bis v3.1.77 speicherte cbd_parse_features_from_post()
     * den Wert ohne wp_unslash(). In der Datenbank steht dort deshalb
     * {\"type\":\"custom\",…} — ungültiges JSON. Betroffen war jede Bibliothek
     * außer Dashicons (die haben keine Anführungszeichen). Schlägt json_decode
     * fehl, wird deshalb ein zweiter Versuch mit stripslashes() unternommen;
     * damit rendern Altbestände wieder korrekt, ohne dass sie neu gespeichert
     * werden müssen.
     *
     * @param string $raw
     * @return array ['type' => …, 'value' => …]
     */
    public static function parse_stored_value($raw) {
        $raw = (string) $raw;

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            $repaired = stripslashes($raw);
            if ($repaired !== $raw) {
                $decoded = json_decode($repaired, true);
            }
        }

        if (is_array($decoded) && isset($decoded['type'], $decoded['value'])
            && in_array($decoded['type'], self::TYPES, true)
        ) {
            return array(
                'type'  => $decoded['type'],
                'value' => (string) $decoded['value'],
            );
        }

        // Legacy: reiner Dashicons-Klassenname
        return array(
            'type'  => 'dashicons',
            'value' => (0 === strpos($raw, 'dashicons-')) ? $raw : 'dashicons-' . $raw,
        );
    }

    /**
     * Zerlegt einen gespeicherten Wert wie "kategorien/experimente".
     *
     * @param string $value
     * @return array|null ['group' => …, 'name' => …] oder null
     */
    public static function parse_value($value) {
        if (!is_string($value) || false === strpos($value, '/')) {
            return null;
        }

        $parts = explode('/', $value, 2);
        $group = $parts[0];
        $name  = $parts[1];

        if (!isset(self::GROUPS[$group])) {
            return null;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $name)) {
            return null;
        }

        return array('group' => $group, 'name' => $name);
    }

    /**
     * URL eines Icons inklusive Cache-Busting-Parameter.
     *
     * @param string $value z. B. "zahlen/7"
     * @return string Leerstring, wenn das Icon nicht existiert
     */
    public static function get_icon_url($value) {
        $parsed = self::parse_value($value);
        if (null === $parsed) {
            return '';
        }

        $index = self::get_index();
        if (!isset($index[$parsed['group']][$parsed['name']])) {
            return '';
        }

        $icon = $index[$parsed['group']][$parsed['name']];

        return add_query_arg('ver', $icon['ver'], $icon['url']);
    }

    /**
     * Existiert ein Zahlen-Icon für diese Zahl?
     *
     * @param int $number
     * @return string URL oder Leerstring
     */
    public static function get_number_icon_url($number) {
        $number = (int) $number;
        if ($number < 1) {
            return '';
        }

        return self::get_icon_url('zahlen/' . $number);
    }

    /**
     * Aufbereitete Daten für den Icon-Picker im Admin.
     *
     * @return array
     */
    public static function get_picker_data() {
        $index      = self::get_index();
        $categories = array();

        foreach (self::GROUPS as $group => $label) {
            if (empty($index[$group])) {
                continue;
            }

            $items = array();
            foreach ($index[$group] as $name => $icon) {
                $items[] = array(
                    'value' => $group . '/' . $name,
                    'label' => $name,
                    'url'   => add_query_arg('ver', $icon['ver'], $icon['url']),
                );
            }

            $categories[$group] = array(
                'label' => $label,
                'icons' => $items,
            );
        }

        return array(
            'categories'   => $categories,
            'numberIcons'  => isset($index['zahlen']) ? count($index['zahlen']) : 0,
        );
    }

    /**
     * Höchste verfügbare Zahlenkachel (für die Nummerierung im Frontend).
     *
     * @return int
     */
    public static function get_max_number() {
        $index = self::get_index();
        if (empty($index['zahlen'])) {
            return 0;
        }

        $numbers = array_map('intval', array_keys($index['zahlen']));

        return $numbers ? max($numbers) : 0;
    }

    /**
     * Daten der Zahlen-Icons für die JavaScript-seitige Nummerierung.
     *
     * Die Nummerierung passiert im Browser (WordPress rendert die Blöcke in
     * unvorhersehbarer Reihenfolge), deshalb bekommt das Script eine
     * Basis-URL statt fertiger Einzel-URLs. Als Cache-Buster dient der
     * jüngste Änderungszeitstempel der Gruppe — ersetzte Zahlenkacheln
     * schlagen damit sofort durch.
     *
     * @return array ['base' => …, 'ver' => …, 'max' => int]
     */
    public static function get_number_assets() {
        $index = self::get_index();
        $empty = array('base' => '', 'ver' => '', 'max' => 0);

        if (empty($index['zahlen'])) {
            return $empty;
        }

        $name  = key($index['zahlen']);
        $first = $index['zahlen'][$name];

        $pos = strrpos($first['url'], '/' . $name . '.svg');
        if (false === $pos) {
            return $empty;
        }

        $versions = array();
        foreach ($index['zahlen'] as $icon) {
            $versions[] = (int) $icon['ver'];
        }

        return array(
            'base' => substr($first['url'], 0, $pos) . '/',
            'ver'  => (string) max($versions),
            'max'  => self::get_max_number(),
        );
    }

    /**
     * Vorschau-HTML für die "aktuell gewähltes Icon"-Anzeige im Admin.
     *
     * Bewusst getrennt von CBD_Block_Registration::render_icon(): dort geht es
     * um das Frontend inklusive Farbe und ARIA, hier nur um eine Chip-Vorschau
     * im Formular, die das JavaScript nach der ersten Auswahl ohnehin ersetzt.
     *
     * @param string $stored_value Rohwert aus dem Formular (JSON oder Legacy)
     * @return string
     */
    public static function get_admin_preview_html($stored_value) {
        $decoded = self::parse_stored_value($stored_value);

        switch ($decoded['type']) {
            case 'custom':
                $url = self::get_icon_url($decoded['value']);
                if ('' === $url) {
                    return '<span class="dashicons dashicons-warning"></span>';
                }
                return '<img class="cbd-custom-icon-preview" src="' . esc_url($url) . '" alt="">';

            case 'fontawesome':
                return '<i class="' . esc_attr($decoded['value']) . '"></i>';

            case 'material':
                return '<span class="material-icons">' . esc_html($decoded['value']) . '</span>';

            case 'lucide':
                return '<i class="lucide lucide-' . esc_attr($decoded['value']) . '"></i>';

            // LEGACY: Auswahl entfernt (v3.1.77), Anzeige bleibt für bestehende Designs
            case 'emoji':
                return '<span class="cbd-emoji-icon" style="font-size:1.2em;">' . esc_html($decoded['value']) . '</span>';

            case 'dashicons':
            default:
                $class = (0 === strpos($decoded['value'], 'dashicons-'))
                    ? $decoded['value']
                    : 'dashicons-' . $decoded['value'];
                return '<span class="dashicons ' . esc_attr($class) . '"></span>';
        }
    }

    /**
     * Lesbare Beschriftung des gewählten Icons ("custom: zahlen/7").
     *
     * @param string $stored_value
     * @return string
     */
    public static function get_admin_label($stored_value) {
        $decoded = self::parse_stored_value($stored_value);

        return $decoded['type'] . ': ' . $decoded['value'];
    }

    /**
     * Cache leeren — nach dem Ablegen neuer Icons aufrufen.
     */
    public static function flush_cache() {
        delete_transient(self::get_transient_key());
        delete_transient(self::TRANSIENT); // Altlast aus Versionen ohne Key-Suffix
        self::$index = null;
    }
}
