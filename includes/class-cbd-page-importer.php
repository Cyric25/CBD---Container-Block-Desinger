<?php
/**
 * Container Block Designer – Seitenimporter
 *
 * Legt aus mehreren Markdown-Dateien je eine WordPress-Seite als Entwurf auf
 * oberster Ebene an. Erreichbar als Untermenü **des Seitenmanagers**.
 *
 * ARBEITSTEILUNG
 *   - Parsen:        CBD_Content_Importer::parse_markdown_content() (unverändert)
 *   - Blockmarkup:   CBD_Block_Serializer::to_post_content()
 *   - Oberfläche:    admin/page-import.php + assets/js/page-importer.js
 *   - Diese Klasse:  Menü, Asset-Einbindung, AJAX-Endpunkte, Seitenerstellung
 *
 * @package ContainerBlockDesigner
 * @since 3.1.86
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seitenimporter
 */
class CBD_Page_Importer {

    /** Singleton-Instanz */
    private static $instance = null;

    /** Hook-Suffix der eigenen Adminseite (leer, wenn die Registrierung scheiterte) */
    private $hook_suffix = '';

    /** Menü-Slug der Importseite */
    const SEITEN_SLUG = 'cbd-page-import';

    /** Menü-Slug des Seitenmanagers (Theme „FOS Online Schulbuch") */
    const ELTERN_SLUG = 'page-manager';

    /** Rückfall, falls der Seitenmanager fehlt */
    const ELTERN_RUECKFALL = 'container-block-designer';

    /** Nonce-Aktion für Titelprüfung und Import */
    const NONCE_IMPORT = 'cbd_page_import';

    /** Nonce-Aktion des bestehenden Parser-Endpunkts (wird mitbenutzt) */
    const NONCE_PARSE = 'cbd_content_import';

    /** Obergrenzen, damit ein Aufruf den Server nicht überfährt */
    const MAX_TITEL = 200;

    /**
     * Singleton-Getter
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        // WICHTIG – Priorität 20, nicht die Vorgabe 10:
        // WordPress lädt Plugins VOR der functions.php des Themes. Bei
        // Priorität 10 liefe dieser Callback, bevor der Seitenmanager sein
        // Menü angelegt hat; add_submenu_page() gäbe dann stillschweigend
        // false zurück und der Menüpunkt fehlte ohne jede Fehlermeldung.
        add_action('admin_menu', array($this, 'menue_registrieren'), 20);
        add_action('admin_enqueue_scripts', array($this, 'assets_einbinden'));

        add_action('wp_ajax_cbd_check_page_titles', array($this, 'ajax_titel_pruefen'));
        add_action('wp_ajax_cbd_import_pages', array($this, 'ajax_seiten_importieren'));
    }

    // -----------------------------------------------------------------
    // Menü und Oberfläche
    // -----------------------------------------------------------------

    /**
     * Hängt die Importseite unter den Seitenmanager.
     */
    public function menue_registrieren() {
        $eltern = isset($GLOBALS['admin_page_hooks'][self::ELTERN_SLUG])
            ? self::ELTERN_SLUG
            : self::ELTERN_RUECKFALL;

        $hook = add_submenu_page(
            $eltern,
            __('Seiten aus Markdown importieren', 'container-block-designer'),
            __('Seiten importieren', 'container-block-designer'),
            'edit_pages',
            self::SEITEN_SLUG,
            array($this, 'seite_ausgeben')
        );

        if ($hook) {
            $this->hook_suffix = $hook;
        } else {
            // Bewusst NICHT hinter WP_DEBUG: Ein fehlender Menüpunkt ohne
            // jede Spur im Log wäre kaum zu diagnostizieren.
            error_log('[CBD Seitenimport] add_submenu_page() für "' . $eltern
                . '" fehlgeschlagen – der Menüpunkt fehlt.');
        }
    }

    /**
     * Bindet Skript und Gestaltung ausschließlich auf der eigenen Seite ein.
     *
     * @param string $hook
     */
    public function assets_einbinden($hook) {
        if ($this->hook_suffix === '' || $hook !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_script(
            'cbd-page-importer',
            CBD_PLUGIN_URL . 'assets/js/page-importer.js',
            array('wp-i18n'),
            CBD_VERSION,
            true
        );

        wp_localize_script('cbd-page-importer', 'cbdPageImport', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            // Derselbe Nonce, den CBD_Content_Importer::ajax_parse_import_file()
            // erwartet – so lässt sich der bestehende Parser-Endpunkt ohne
            // jede Änderung mitbenutzen.
            'nonceParse'  => wp_create_nonce(self::NONCE_PARSE),
            'nonceImport' => wp_create_nonce(self::NONCE_IMPORT),
            // Auf einer Nicht-Editor-Seite ist der Blocktyp über wp.blocks
            // nicht ermittelbar – deshalb serverseitig bestimmt.
            'accordionVerfuegbar' => (class_exists('WP_Block_Type_Registry')
                && WP_Block_Type_Registry::get_instance()->is_registered('modular-blocks/accordion')),
            'seitenmanagerUrl' => admin_url('admin.php?page=' . self::ELTERN_SLUG),
        ));

        wp_enqueue_style(
            'cbd-page-importer',
            CBD_PLUGIN_URL . 'assets/css/page-importer.css',
            array(),
            CBD_VERSION
        );
    }

    /**
     * Rendert die Importseite.
     */
    public function seite_ausgeben() {
        if (!current_user_can('edit_pages')) {
            wp_die(__('Sie haben keine Berechtigung für diese Seite.', 'container-block-designer'));
        }
        require CBD_PLUGIN_DIR . 'admin/page-import.php';
    }

    // -----------------------------------------------------------------
    // AJAX: Titel auf Dubletten prüfen
    // -----------------------------------------------------------------

    /**
     * Meldet, welche der übergebenen Titel bereits als Seite existieren.
     *
     * Importiert wird trotzdem – die Liste dient nur der Warnung im Dialog.
     */
    public function ajax_titel_pruefen() {
        check_ajax_referer(self::NONCE_IMPORT, 'nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
            return;
        }

        $roh = isset($_POST['titles']) ? wp_unslash($_POST['titles']) : array();
        if (!is_array($roh)) {
            $roh = array($roh);
        }
        $roh = array_slice($roh, 0, self::MAX_TITEL);

        $dubletten = array();
        foreach ($roh as $titel) {
            $titel = sanitize_text_field($titel);
            if ($titel === '') {
                continue;
            }

            // get_page_by_title() ist seit WordPress 6.2 als veraltet markiert.
            $treffer = get_posts(array(
                'post_type'        => 'page',
                'title'            => $titel,
                'post_status'      => array('publish', 'draft', 'pending', 'private'),
                'numberposts'      => 1,
                'fields'           => 'ids',
                'suppress_filters' => false,
            ));

            if (!empty($treffer)) {
                $id = (int) $treffer[0];
                $dubletten[$titel] = array(
                    'id'       => $id,
                    'editLink' => get_edit_post_link($id, 'raw'),
                );
            }
        }

        wp_send_json_success(array('dubletten' => $dubletten));
    }

    // -----------------------------------------------------------------
    // AJAX: eine Datei importieren
    // -----------------------------------------------------------------

    /**
     * Legt aus dem Markdown-Rohtext EINER Datei eine Seite als Entwurf an.
     *
     * Ein Aufruf je Datei: Der Fortschritt bleibt sichtbar, ein PHP-Timeout
     * bei vielen Dateien ist ausgeschlossen, und ein Fehler betrifft nur
     * eine Datei.
     *
     * **Der Server parst selbst.** Übernommen wird der Markdown-Rohtext, nicht
     * die im Browser geparste Struktur – so gelangt kein clientseitig
     * erzeugtes HTML in post_content.
     */
    public function ajax_seiten_importieren() {
        check_ajax_referer(self::NONCE_IMPORT, 'nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer')));
            return;
        }

        $titel = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        if ($titel === '') {
            wp_send_json_error(array('message' => __('Kein Seitentitel übergeben.', 'container-block-designer')));
            return;
        }

        // Ungültige Werte fallen still auf 0 (oberste Ebene) zurück - kein
        // Abbruch. Der Import läuft Datei für Datei; ein Fehler an dieser
        // Stelle ließe die Hälfte der Seiten angelegt und die andere nicht
        // (siehe Plan, Abschnitt 5).
        $eltern_id = $this->bereinige_elternseite(
            isset($_POST['parent_id']) ? $_POST['parent_id'] : null
        );

        // Markdown-Rohtext: NUR wp_unslash, kein wp_kses_post und kein
        // sanitize_textarea_field – beide würden Backslashes anfassen und
        // damit LaTeX-Formeln zerstören. Dieselbe Begründung wie in
        // CBD_Content_Importer::ajax_parse_import_file(). Die Entschärfung
        // des erzeugten HTML leistet der Parser selbst (strip_unsafe_html()).
        $inhalt = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        if (trim($inhalt) === '') {
            wp_send_json_error(array('message' => __('Die Datei ist leer.', 'container-block-designer')));
            return;
        }

        $mappings = $this->json_aus_post('mappings');
        $opt_out_roh = $this->json_aus_post('accordion_opt_out');

        // Zuweisungen bereinigen
        $sauber = array();
        foreach ($mappings as $key => $wert) {
            $sauber[sanitize_key($key)] = sanitize_text_field($wert);
        }

        $opt_out = array();
        foreach ($opt_out_roh as $key => $wert) {
            $opt_out[sanitize_key($key)] = (bool) $wert;
        }

        // Designs laden – der Bezeichner steht in der Spalte `name`
        // (siehe CBD_Block_Serializer::aktive_slugs()).
        global $wpdb;
        $zeilen = $wpdb->get_results(
            "SELECT id, name, title FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active' ORDER BY name ASC"
        );
        if (!is_array($zeilen)) {
            $zeilen = array();
        }

        $slugs = array();
        $designs = array();
        foreach ($zeilen as $zeile) {
            $slugs[] = $zeile->name;
            // parse_markdown_content() erwartet Objekte mit ->name und ->slug,
            // wobei ->slug der Bezeichner und ->name der Anzeigename ist.
            $designs[] = (object) array(
                'id'   => $zeile->id,
                'name' => $zeile->title !== '' ? $zeile->title : $zeile->name,
                'slug' => $zeile->name,
            );
        }

        try {
            $geparst = CBD_Content_Importer::get_instance()->parse_markdown_content($inhalt, $designs);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
            return;
        }

        if (empty($geparst['sections'])) {
            wp_send_json_error(array(
                'message' => __('Die Datei enthält keinen verwertbaren Inhalt.', 'container-block-designer'),
            ));
            return;
        }

        $post_content = CBD_Block_Serializer::to_post_content(
            $geparst['sections'],
            $geparst['groups'],
            $sauber,
            array(
                'accordion_opt_out'   => $opt_out,
                'page_title'          => $titel,
                'known_slugs'         => $slugs,
                'accordion_available' => (class_exists('WP_Block_Type_Registry')
                    && WP_Block_Type_Registry::get_instance()->is_registered('modular-blocks/accordion')),
            )
        );

        // wp_slash() ist zwingend: wp_insert_post() erwartet maskierte Daten
        // und ruft intern wp_unslash() auf. Ohne die Maskierung verschwindet
        // JEDER Backslash – aus \cdot würde cdot, jede LaTeX-Formel wäre
        // still zerstört.
        $page_id = wp_insert_post(array(
            'post_title'   => $titel,
            'post_content' => wp_slash($post_content),
            'post_type'    => 'page',
            'post_status'  => 'draft',
            'post_parent'  => $eltern_id,
            'menu_order'   => 0,
        ), true);

        if (is_wp_error($page_id)) {
            wp_send_json_error(array('message' => $page_id->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'pageId'   => $page_id,
            'title'    => $titel,
            'editLink' => get_edit_post_link($page_id, 'raw'),
            'blocks'   => substr_count($post_content, '<!-- wp:'),
        ));
    }

    /**
     * Bereinigt die gewünschte Elternseite aus $_POST['parent_id'].
     *
     * Ein ungültiger Wert ergibt still `0` (oberste Ebene) - kein Fehler,
     * kein Abbruch. Der Import läuft Datei für Datei; ein Abbruch mitten im
     * Lauf ließe die Hälfte der Seiten angelegt und die andere nicht. Eine
     * Seite auf oberster Ebene lässt sich dagegen im Seitenmanager
     * verschieben (siehe Plan, Abschnitt 5, Architekturentscheidungen).
     *
     * `filter_var(..., FILTER_VALIDATE_INT)` statt `(int)`-Cast: Eine
     * überlange Ziffernfolge besteht zwar eine reine Ziffernprüfung, ein
     * `(int)`-Cast bildet sie ab PHP 8.1 aber mit einer Warnung auf
     * PHP_INT_MAX ab, statt sie abzulehnen. Dieselbe Regel mit derselben
     * Begründung steht bereits in class-cbd-design-transfer.php,
     * `md_read_value()` (~Zeile 911-915).
     *
     * @param mixed $roh Rohwert aus $_POST, ggf. noch mit Slashes.
     * @return int Gültige Seiten-ID oder 0.
     */
    private function bereinige_elternseite($roh) {
        $roh = wp_unslash($roh);
        $id  = filter_var($roh, FILTER_VALIDATE_INT);

        if (false === $id || $id <= 0) {
            return 0;
        }

        $post = get_post($id);

        if (!$post || !isset($post->post_type) || 'page' !== $post->post_type) {
            return 0;
        }

        if (isset($post->post_status) && 'trash' === $post->post_status) {
            return 0;
        }

        return $id;
    }

    /**
     * Liest ein JSON-Feld aus $_POST.
     *
     * `wp_unslash()` vor `json_decode()` ist zwingend: WordPress versieht
     * $_POST mit Backslashes, ohne Entfernen scheitert das Dekodieren
     * stillschweigend. Genau dieser Fehler hat im Plugin schon einmal die
     * Icon-Werte zerstört (siehe CLAUDE.md, „Icon-Wert: kanonisches Parsen").
     *
     * @param string $feld
     * @return array
     */
    private function json_aus_post($feld) {
        if (!isset($_POST[$feld])) {
            return array();
        }
        $daten = json_decode(wp_unslash($_POST[$feld]), true);
        return is_array($daten) ? $daten : array();
    }
}

// Initialisierung
CBD_Page_Importer::get_instance();
