<?php
/**
 * Container Block Designer - Classroom System (Klassen-System)
 *
 * Handles class management, drawing persistence, and student access.
 * This feature is optionally activatable via plugin settings.
 *
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classroom System Class
 *
 * Provides:
 * - Teacher: Class CRUD (create, read, update, delete)
 * - Teacher: Server-side drawing save/load per class
 * - Teacher: Mark blocks as "behandelt" (covered)
 * - Student: Password-based access via shortcode
 */
class CBD_Classroom {

    /**
     * Debug-Log nur bei WP_DEBUG (AP25) — die AJAX-Pfade liefen sonst
     * mit Dutzenden Log-Zeilen pro Toggle/Abruf voll.
     */
    private static function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Zwischenspeicher: Hat `cbd_drawings` die Spalte `elemente_data`?
     * null = noch nicht geprüft. Siehe drawings_hat_elemente_spalte().
     *
     * @var bool|null
     */
    private static $elemente_spalte_vorhanden = null;

    /**
     * Obergrenze für die Elementliste einer Tafel in Bytes (Plan „Tafel-Lasso",
     * Risiko R6). Darüber wird nur das PNG gespeichert.
     */
    const ELEMENTE_MAX_BYTES = 8000000;

    /**
     * Obergrenze für die Punkte der Elementliste einer Tafel (AP-4.fix1,
     * Befund F1 aus AP-4.rev). Gemessen wird VOR json_decode() billig die Zahl
     * der '[' im Text – eine obere Schranke der Punktzahl (je Punkt eines,
     * dazu eines je Strich und eines für die Liste; Bild-`daten` sind Base64
     * und enthalten keines). Darüber wird nur das PNG gespeichert.
     *
     * Grund: json_decode() legt je Punkt ein eigenes PHP-Array an (~260 Byte).
     * Ohne Grenze brauchte die Prüfung ~33 MB je MB Strich-JSON; ab ~2–2,5 MB
     * riss die Anfrage bei memory_limit 128M samt WordPress-Grundlast mit
     * einem Fatal ab – VOR dem Schreiben, also auch ohne PNG. An der Grenze
     * liegt die Spitze jetzt bei ~26 MB (Harnisch tools/test-board-elemente.php,
     * Abschnitt D; Messung mit WordPress in der Übergabenotiz AP-4.fix1).
     * Gleicher Wert in assets/js/board-mode.js (ELEMENTE_MAX_PUNKTE): Der
     * Browser sendet darüber gar keine Liste. Bitte nur gemeinsam ändern.
     */
    const ELEMENTE_MAX_PUNKTE = 100000;

    /**
     * Option key for enabling/disabling the classroom system
     */
    const OPTION_ENABLED = 'cbd_classroom_enabled';

    /**
     * Shortcode-Name der Klassenverwaltung fuer Lehrpersonen im Frontend.
     *
     * Gegenstueck zu 'cbd_classroom' (Schueler-Zugang). Der Name steht als
     * Konstante da, weil er an drei Stellen gebraucht wird: add_shortcode(),
     * has_shortcode() im Enqueue und shortcode_atts().
     */
    const SHORTCODE_LEHRER_KLASSEN = 'cbd_lehrer_klassen';

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
     * Constructor - Register hooks only if classroom system is enabled
     */
    private function __construct() {
        // Always register the settings hook (so the toggle is available)
        add_action('admin_init', array($this, 'register_settings'));

        // Frontend-Klassenverwaltung fuer Lehrpersonen.
        //
        // BEWUSST VOR der is_enabled()-Weiche registriert — anders als
        // [cbd_classroom] weiter unten. Grund: Dieser Shortcode steht auf einer
        // ganz gewoehnlichen, veroeffentlichten Seite. Waere er nur bei
        // eingeschaltetem Klassen-System registriert, erschiene nach dem
        // Abschalten des Systems der rohe Text "[cbd_lehrer_klassen]" im
        // Seiteninhalt. Die Render-Methode zeigt in dem Fall denselben Hinweis
        // wie admin/classroom.php ("Klassen-System ist derzeit deaktiviert").
        // Der Enqueue-Zweig prueft is_enabled() selbst und laedt dann nichts.
        add_shortcode(self::SHORTCODE_LEHRER_KLASSEN, array($this, 'render_lehrer_klassen_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_lehrer_klassen_assets'));

        // Only register classroom functionality if enabled
        if (!self::is_enabled()) {
            return;
        }

        // AJAX handlers for teachers (logged-in users)
        add_action('wp_ajax_cbd_save_class', array($this, 'ajax_save_class'));
        add_action('wp_ajax_cbd_delete_class', array($this, 'ajax_delete_class'));
        add_action('wp_ajax_cbd_get_classes', array($this, 'ajax_get_classes'));
        add_action('wp_ajax_cbd_save_drawing', array($this, 'ajax_save_drawing'));
        add_action('wp_ajax_cbd_load_drawing', array($this, 'ajax_load_drawing'));
        add_action('wp_ajax_cbd_get_page_drawings', array($this, 'ajax_get_page_drawings'));
        add_action('wp_ajax_cbd_toggle_behandelt', array($this, 'ajax_toggle_behandelt'));
        add_action('wp_ajax_cbd_set_behandelt', array($this, 'ajax_set_behandelt'));
        add_action('wp_ajax_cbd_get_block_status', array($this, 'ajax_get_block_status'));
        add_action('wp_ajax_cbd_toggle_class_subscription', array($this, 'ajax_toggle_class_subscription'));

        // AJAX handlers for students (no login required)
        add_action('wp_ajax_nopriv_cbd_student_auth', array($this, 'ajax_student_auth'));
        add_action('wp_ajax_nopriv_cbd_student_get_data', array($this, 'ajax_student_get_data'));
        add_action('wp_ajax_nopriv_cbd_get_public_classes', array($this, 'ajax_get_public_classes'));
        add_action('wp_ajax_nopriv_cbd_get_page_classroom_data', array($this, 'ajax_get_page_classroom_data'));
        add_action('wp_ajax_nopriv_cbd_cleanup_invalid_containers', array($this, 'ajax_cleanup_invalid_containers'));
        // Also allow logged-in users to use student endpoints
        add_action('wp_ajax_cbd_student_auth', array($this, 'ajax_student_auth'));
        add_action('wp_ajax_cbd_student_get_data', array($this, 'ajax_student_get_data'));
        add_action('wp_ajax_cbd_get_public_classes', array($this, 'ajax_get_public_classes'));
        add_action('wp_ajax_cbd_get_page_classroom_data', array($this, 'ajax_get_page_classroom_data'));
        add_action('wp_ajax_cbd_cleanup_invalid_containers', array($this, 'ajax_cleanup_invalid_containers'));

        // Shortcode for student access
        add_shortcode('cbd_classroom', array($this, 'render_classroom_shortcode'));

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Rückweg zur Klassen-Zugangsseite (#7061, AP-2.1): Seite mit
        // [cbd_classroom] beim Speichern merken, siehe zugangsseite_url().
        add_action('save_post_page', array($this, 'merke_zugangsseite'), 20, 2);
    }

    // =========================================================================
    // RÜCKWEG ZUR KLASSEN-ZUGANGSSEITE (#7061, AP-2.1)
    // =========================================================================

    /**
     * Option, unter der die ID der Seite mit [cbd_classroom] gemerkt wird.
     */
    const OPTION_ZUGANGSSEITE = 'cbd_classroom_seite_id';

    /**
     * Adresse der Klassen-Zugangsseite (Seite mit [cbd_classroom]).
     *
     * Das JavaScript auf Inhaltsseiten im Klassenmodus kennt diese Seite
     * nicht; es bekommt die Adresse als cbdClassroomPageData.zugangUrl, damit
     * ein Klick auf den Klassennamen zur Seitenliste der Klasse zurückführt.
     *
     * Weg: zuerst die gemerkte ID (Option, gesetzt von merke_zugangsseite()
     * beim Speichern). Stimmt sie nicht (mehr) – Seite gelöscht, nicht
     * veröffentlicht, Shortcode entfernt –, sucht EINE Abfrage nach der
     * Seite und merkt das Ergebnis. Ohne Treffer wird NICHTS geschrieben,
     * sonst würde jede Klassenseite suchen UND schreiben; das Ergebnis ist
     * dann '' und der Klassenname bleibt reiner Text (Rückfall).
     *
     * has_shortcode() prüft jeden Treffer nach, weil LIKE '%[cbd_classroom%'
     * auch fremde Shortcodes wie [cbd_classroom_xyz] träfe.
     *
     * @return string Permalink oder '' (keine Zugangsseite vorhanden).
     */
    public static function zugangsseite_url() {
        static $ergebnis = null;
        if (null !== $ergebnis) {
            return $ergebnis;
        }

        $id = (int) get_option(self::OPTION_ZUGANGSSEITE, 0);
        if ($id > 0 && self::ist_zugangsseite($id)) {
            $ergebnis = (string) get_permalink($id);
            return $ergebnis;
        }

        global $wpdb;
        $treffer = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('page', 'post')
               AND post_content LIKE %s
             ORDER BY ID ASC LIMIT 5",
            '%' . $wpdb->esc_like('[cbd_classroom') . '%'
        ));

        foreach ((array) $treffer as $kandidat) {
            $kandidat = (int) $kandidat;
            if (self::ist_zugangsseite($kandidat)) {
                update_option(self::OPTION_ZUGANGSSEITE, $kandidat, false);
                $ergebnis = (string) get_permalink($kandidat);
                return $ergebnis;
            }
        }

        $ergebnis = '';
        return $ergebnis;
    }

    /**
     * Ist die Seite veröffentlicht und enthält sie [cbd_classroom]?
     *
     * @param int $id Beitrags-ID.
     * @return bool
     */
    private static function ist_zugangsseite($id) {
        if ($id <= 0 || 'publish' !== get_post_status($id)) {
            return false;
        }
        return has_shortcode((string) get_post_field('post_content', $id), 'cbd_classroom');
    }

    /**
     * save_post_page: Zugangsseite merken bzw. vergessen.
     *
     * Merkt die ID, sobald eine veröffentlichte Seite [cbd_classroom] trägt.
     * Verliert genau die gemerkte Seite den Shortcode (oder ihren
     * Veröffentlichungsstatus), wird die Option gelöscht – zugangsseite_url()
     * sucht dann beim nächsten Klassenseitenaufruf neu.
     *
     * @param int     $post_id Seiten-ID.
     * @param WP_Post $post    Seite.
     * @return void
     */
    public function merke_zugangsseite($post_id, $post) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $hat_shortcode = 'publish' === $post->post_status
            && has_shortcode((string) $post->post_content, 'cbd_classroom');

        if ($hat_shortcode) {
            update_option(self::OPTION_ZUGANGSSEITE, (int) $post_id, false);
        } elseif ((int) get_option(self::OPTION_ZUGANGSSEITE, 0) === (int) $post_id) {
            delete_option(self::OPTION_ZUGANGSSEITE);
        }
    }

    /**
     * Check if the classroom system is enabled
     */
    public static function is_enabled() {
        return (bool) get_option(self::OPTION_ENABLED, false);
    }

    // =========================================================================
    // GETEILTE HELFER (seit AP-2.1)
    // =========================================================================

    /**
     * Eine container_id in Basis-Bezeichner und Tafelseite zerlegen.
     *
     * Mehrseitige Tafelbilder werden als `<stableId>:p<N>` gespeichert — eine
     * Zeile je Tafelseite. Ohne Suffix ist die Tafelseite 0.
     *
     * DIES IST DIE EINZIGE STELLE IM PLUGIN, DIE DIESES FORMAT DEUTET.
     * Benutzt von `basis_container_id()`, `behandelte_container()`,
     * `ajax_get_page_classroom_data()` und dem Klassen-Durchlass
     * (`CBD_Classroom_Gate`). Eine zweite Fassung liefe früher oder später
     * auseinander — der Prüfharnisch `tools/test-classroom-gate.php` wacht
     * darüber, dass es beim einen regulären Ausdruck bleibt.
     *
     * @param string $container_id
     * @return array array('basis' => string, 'seite' => int)
     */
    public static function zerlege_container_id($container_id) {
        $container_id = (string) $container_id;

        if (preg_match('/^(.+):p(\d+)$/', $container_id, $treffer)) {
            return array('basis' => $treffer[1], 'seite' => (int) $treffer[2]);
        }

        return array('basis' => $container_id, 'seite' => 0);
    }

    /**
     * Nur der Basis-Bezeichner einer container_id.
     *
     * @param string $container_id
     * @return string
     */
    public static function basis_container_id($container_id) {
        $teile = self::zerlege_container_id($container_id);
        return $teile['basis'];
    }

    /**
     * Basis-Bezeichner aller Container, die für eine Klasse auf einer Seite
     * als „behandelt" markiert sind.
     *
     * Jeder Bezeichner erscheint genau einmal, auch wenn mehrere Tafelseiten
     * dazu gespeichert sind.
     *
     * @param int $class_id
     * @param int $page_id
     * @return string[] Liste der Basis-Bezeichner (kann leer sein)
     */
    public static function behandelte_container($class_id, $page_id) {
        $class_id = (int) $class_id;
        $page_id  = (int) $page_id;

        // Ohne gültige Kennungen gar nicht erst abfragen.
        if ($class_id <= 0 || $page_id <= 0) {
            return array();
        }

        global $wpdb;

        $roh = $wpdb->get_col($wpdb->prepare(
            "SELECT container_id FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND page_id = %d AND is_behandelt = 1",
            $class_id,
            $page_id
        ));

        $basis = array();
        foreach ((array) $roh as $container_id) {
            $id = self::basis_container_id($container_id);
            if ('' !== $id && !isset($basis[$id])) {
                $basis[$id] = true;
            }
        }

        return array_keys($basis);
    }

    /**
     * Die stabile Kennung eines Container-Blocks aus einem Eintrag von
     * `parse_blocks()`.
     *
     * DIES IST DER GETEILTE HELFER FÜR DIESE REGEL — keine vierte Fassung
     * anlegen. Die Extraktion stand ursprünglich wörtlich in
     * `CBD_Classroom_Gate::block_erlaubt()`; sie wurde für den
     * Flackerschutz (`enqueue_frontend_assets()`, Zweig „normale Seite mit
     * ?classroom=") hierher gezogen, weil sonst eine vierte Kopie derselben
     * Regel entstanden wäre. `CBD_Classroom_Gate::block_erlaubt()` ruft
     * seither diese Methode auf; die beiden übrigen Fassungen
     * (`CBD_Block_Registration::render_block()`,
     * `CBD_Blocks_REST_API::extract_stable_id()`) bleiben unberührt — siehe
     * CLAUDE.md, „Offener Punkt: `stableId`-Extraktion existiert dreifach".
     *
     * Reihenfolge (unverändert gegenüber der Fassung im Durchlass):
     *   1. Blockattribut `stableId`
     *   2. RÜCKFALL FÜR ALTBESTÄNDE: `data-stable-id` im gespeicherten HTML.
     *      Ältere Container tragen die Kennung nur dort. Ohne diesen Rückfall
     *      verschwänden korrekt markierte Blöcke stillschweigend aus der
     *      Klassenansicht.
     *
     * @param array $block Eintrag aus parse_blocks()
     * @return string Kennung oder '' wenn keine ermittelbar ist
     */
    public static function stabile_id_aus_block($block) {
        if (!is_array($block)) {
            return '';
        }

        if (!empty($block['attrs']['stableId'])) {
            return (string) $block['attrs']['stableId'];
        }

        $html = isset($block['innerHTML']) ? (string) $block['innerHTML'] : '';
        if ('' === $html && !empty($block['innerContent'])) {
            $html = implode('', array_filter((array) $block['innerContent'], 'is_string'));
        }

        if (preg_match('/data-stable-id="([^"]+)"/', $html, $treffer)) {
            return $treffer[1];
        }

        return '';
    }

    /**
     * Ist dieser Eintrag aus `parse_blocks()` ein Container des CDB-Plugins?
     *
     * Ein fremder Blocktyp mit zufällig passender Kennung zählt nicht —
     * dieselbe Regel wie in `CBD_Classroom_Gate::block_erlaubt()`.
     *
     * @param array $block
     * @return bool
     */
    public static function ist_container_block($block) {
        return is_array($block)
            && !empty($block['blockName'])
            && 0 === strpos((string) $block['blockName'], 'container-block-designer/');
    }

    /**
     * Kennungen ALLER Container-Blöcke eines Seiteninhalts, in
     * Dokumentreihenfolge und jede genau einmal.
     *
     * Steigt bewusst in `innerBlocks` ab: Container liegen regelmäßig
     * verschachtelt (in anderen Containern, in Gruppen, in Accordion-Panels).
     * Der Browser-Filter sammelt sie über
     * `[data-stable-id^="cbd-"]` ebenfalls über alle Ebenen ein.
     *
     * BEKANNTE GRENZE, bewusst: Container, die erst beim Rendern entstehen
     * (wiederverwendbare Blöcke `core/block`, Template-Teile, Shortcodes),
     * stehen nicht im `post_content` und fehlen daher in dieser Liste. Für
     * den Flackerschutz ist das die harmlose Fehlerrichtung — für einen
     * solchen Container fehlt lediglich die Versteckregel, der
     * Browser-Filter holt ihn wie bisher nach.
     *
     * @param string $post_content Roher Seiteninhalt (Blockmarkup)
     * @return string[]
     */
    public static function container_ids_aus_inhalt($post_content) {
        $post_content = (string) $post_content;

        if ('' === trim($post_content) || !function_exists('parse_blocks')) {
            return array();
        }

        $gesammelt = array();
        self::sammle_container_ids(parse_blocks($post_content), $gesammelt);

        return array_keys($gesammelt);
    }

    /**
     * Rekursionsschritt für container_ids_aus_inhalt().
     *
     * @param array $bloecke
     * @param array $gesammelt Kennung => true (Referenz, hält die Reihenfolge)
     * @return void
     */
    private static function sammle_container_ids($bloecke, &$gesammelt) {
        foreach ((array) $bloecke as $block) {
            if (self::ist_container_block($block)) {
                $id = self::stabile_id_aus_block($block);
                if ('' !== $id && !isset($gesammelt[$id])) {
                    $gesammelt[$id] = true;
                }
            }

            if (!empty($block['innerBlocks'])) {
                self::sammle_container_ids($block['innerBlocks'], $gesammelt);
            }
        }
    }

    /**
     * Register settings for the classroom toggle
     */
    public function register_settings() {
        register_setting('cbd_settings', self::OPTION_ENABLED, array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
    }

    // =========================================================================
    // TEACHER: Class CRUD
    // =========================================================================

    /**
     * AJAX: Save (create or update) a class
     */
    public function ajax_save_class() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = CBD_TABLE_CLASSES;

        $class_id = intval($_POST['class_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $page_ids = array_map('intval', (array) ($_POST['page_ids'] ?? array()));

        // Vorhaben „Schüler-Fragen": Pro Klasse einstellbar, ob Schüler selbst
        // Fragen auf die Fragenwand legen dürfen. IMMER auf 0/1 normalisiert —
        // das Feld kommt als Zeichenkette an, und ein "0" wäre in einer
        // ungeprüften Prüfung wahr. Ein FEHLENDES Feld bedeutet ausdrücklich
        // "aus": Eine abgehakte Checkbox schickt gar nichts mit, ein Ausschalten
        // muss aber ankommen.
        $schueler_fragen_erlaubt = !empty($_POST['schueler_fragen_erlaubt'])
            && '0' !== (string) $_POST['schueler_fragen_erlaubt'] ? 1 : 0;

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Klassenname ist erforderlich.'));
        }

        if ($class_id > 0) {
            // Update existing class
            $update_data = array(
                'name' => $name,
                'schueler_fragen_erlaubt' => $schueler_fragen_erlaubt,
                'updated_at' => current_time('mysql')
            );

            // Only update password if a new one was provided
            if (!empty($password)) {
                $update_data['password'] = wp_hash_password($password);
            }

            $wpdb->update($table, $update_data, array('id' => $class_id, 'teacher_id' => get_current_user_id()));
        } else {
            // Create new class
            if (empty($password)) {
                wp_send_json_error(array('message' => 'Passwort ist erforderlich.'));
            }

            $wpdb->insert($table, array(
                'name' => $name,
                'password' => wp_hash_password($password),
                'teacher_id' => get_current_user_id(),
                'status' => 'active',
                'schueler_fragen_erlaubt' => $schueler_fragen_erlaubt
            ));
            $class_id = $wpdb->insert_id;
        }

        if (!$class_id) {
            wp_send_json_error(array('message' => 'Fehler beim Speichern.'));
        }

        // Update page assignments
        $pages_table = CBD_TABLE_CLASS_PAGES;
        $wpdb->delete($pages_table, array('class_id' => $class_id));

        foreach ($page_ids as $sort => $page_id) {
            if ($page_id > 0) {
                $wpdb->insert($pages_table, array(
                    'class_id' => $class_id,
                    'page_id' => $page_id,
                    'sort_order' => $sort
                ));
            }
        }

        wp_send_json_success(array(
            'message' => 'Klasse gespeichert.',
            'class_id' => $class_id
        ));
    }

    /**
     * AJAX: Delete a class
     */
    public function ajax_delete_class() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $class_id = intval($_POST['class_id'] ?? 0);

        if ($class_id <= 0) {
            wp_send_json_error(array('message' => 'Ungueltige Klassen-ID.'));
        }

        // Besitzer ODER Seitenadmin darf löschen
        $class = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CBD_TABLE_CLASSES . " WHERE id = %d",
            $class_id
        ));

        if (!$class) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        if ((int) $class->teacher_id !== get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung zum Löschen dieser Klasse.'));
        }

        // Delete related data
        $wpdb->delete(CBD_TABLE_CLASS_PAGES, array('class_id' => $class_id));
        $wpdb->delete(CBD_TABLE_DRAWINGS, array('class_id' => $class_id));
        $wpdb->delete(CBD_TABLE_CLASSES, array('id' => $class_id));

        // Pulsdatei der geloeschten Klasse entfernen (AP-1.5). Ohne das
        // bliebe sie liegen und ihre Capability-URL gueltig. Hinter
        // class_exists()/method_exists(), damit ein Teilstand des Plugins
        // hier keinen Fatal Error erzeugt -- dasselbe Muster wie bei den
        // Theme-Aufrufen im Klassenmodus.
        if (class_exists('CBD_Klassenpuls')
            && method_exists('CBD_Klassenpuls', 'loesche_pulsdatei')) {
            CBD_Klassenpuls::loesche_pulsdatei((int) $class_id);
        }

        wp_send_json_success(array('message' => 'Klasse geloescht.'));
    }

    /**
     * AJAX: Get all classes (own + others) with subscription status
     */
    public function ajax_get_classes() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $teacher_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        // Abonnierte Klassen-IDs des aktuellen Lehrers
        $subscribed = get_user_meta($teacher_id, 'cbd_subscribed_classes', true);
        $subscribed_ids = is_array($subscribed) ? array_map('intval', $subscribed) : array();

        // Alle Klassen laden (nicht nur eigene)
        $classes = $wpdb->get_results(
            "SELECT c.id, c.name, c.status, c.teacher_id, c.created_at, c.updated_at,
                    c.schueler_fragen_erlaubt,
                    u.display_name AS teacher_name
             FROM " . CBD_TABLE_CLASSES . " c
             LEFT JOIN {$wpdb->users} u ON c.teacher_id = u.ID
             ORDER BY c.name ASC"
        );

        foreach ($classes as &$class) {
            // $wpdb liefert alles als Zeichenkette; ein "0" ist in JavaScript
            // wahr. Ohne diese Wandlung stünde die Checkbox in der Verwaltung
            // bei JEDER Klasse auf „an".
            $class->schueler_fragen_erlaubt = (bool) intval($class->schueler_fragen_erlaubt);

            $class->is_owner    = ((int) $class->teacher_id === $teacher_id);
            $class->is_subscribed = $class->is_owner || in_array((int) $class->id, $subscribed_ids, true);
            $class->can_delete  = $class->is_owner || $is_admin;
            $class->can_edit    = $class->is_owner;

            $class->pages = $wpdb->get_results($wpdb->prepare(
                "SELECT cp.page_id, cp.sort_order, p.post_title
                 FROM " . CBD_TABLE_CLASS_PAGES . " cp
                 LEFT JOIN {$wpdb->posts} p ON cp.page_id = p.ID
                 WHERE cp.class_id = %d
                 ORDER BY cp.sort_order ASC",
                $class->id
            ));
        }

        wp_send_json_success($classes);
    }

    /**
     * AJAX: Toggle subscription to a class
     */
    public function ajax_toggle_class_subscription() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $class_id   = intval($_POST['class_id'] ?? 0);
        $subscribe  = filter_var($_POST['subscribe'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $teacher_id = get_current_user_id();

        if ($class_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültige Klassen-ID.'));
        }

        // Eigene Klassen können nicht abonniert/abbestellt werden
        global $wpdb;
        $is_owner = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CBD_TABLE_CLASSES . " WHERE id = %d AND teacher_id = %d",
            $class_id, $teacher_id
        ));
        if ($is_owner) {
            wp_send_json_error(array('message' => 'Eigene Klassen sind automatisch aktiv.'));
        }

        $subscribed = get_user_meta($teacher_id, 'cbd_subscribed_classes', true);
        $subscribed_ids = is_array($subscribed) ? array_map('intval', $subscribed) : array();

        if ($subscribe) {
            if (!in_array($class_id, $subscribed_ids, true)) {
                $subscribed_ids[] = $class_id;
            }
        } else {
            $subscribed_ids = array_values(array_diff($subscribed_ids, array($class_id)));
        }

        update_user_meta($teacher_id, 'cbd_subscribed_classes', $subscribed_ids);

        wp_send_json_success(array(
            'subscribed' => $subscribe,
            'class_id'   => $class_id,
        ));
    }

    // =========================================================================
    // TEACHER: Drawing Save/Load
    // =========================================================================

    /**
     * AJAX: Save a drawing to the server
     */
    public function ajax_save_drawing() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = CBD_TABLE_DRAWINGS;

        $class_id = intval($_POST['class_id'] ?? 0);
        $page_id = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');
        // Leerer Canvas wird als leerer String gesendet -> NULL in DB speichern
        $drawing_data = !empty($_POST['drawing_data']) ? $_POST['drawing_data'] : null;

        if ($class_id <= 0 || $page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff prüfen: Besitzer oder Abonnent
        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // Elementliste der Tafel (Plan „Tafel-Lasso", AP-4.2, Architektur E8):
        // zusätzliche Bearbeitungsquelle neben dem PNG. Fehlt das Feld (alter
        // Client), wird die Spalte auf NULL gesetzt. Ein leerer Canvas hat nie
        // Elemente. Ungültig oder zu groß -> nur das PNG speichern und das
        // dem Client melden (`elemente_verworfen`).
        $elemente_verworfen = false;
        $elemente = $this->pruefe_elemente_json($_POST['elemente_data'] ?? '');
        if (null === $drawing_data) {
            $elemente = null;
        }
        if (false === $elemente) {
            $elemente = null;
            $elemente_verworfen = true;
        }
        // Fingerabdruck des PNG neben die Liste legen (AP-4.fix2, Befund F2):
        // Eine ältere Plugin-Version (Rückweg) schreibt nur drawing_data –
        // ajax_load_drawing() erkennt die dann veraltete Liste am Abdruck.
        if (is_string($elemente)) {
            $elemente = self::elemente_huelle($drawing_data, $elemente);
        }

        // Upsert: Insert or update drawing
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        if ($existing) {
            $daten = array(
                'drawing_data' => $drawing_data,
                'elemente_data' => $elemente,
                'updated_at' => current_time('mysql')
            );
            $schreibe = function ($d) use ($wpdb, $table, $existing) {
                return $wpdb->update($table, $d, array('id' => $existing->id));
            };
        } else {
            $daten = array(
                'class_id' => $class_id,
                'teacher_id' => get_current_user_id(),
                'page_id' => $page_id,
                'container_id' => $container_id,
                'drawing_data' => $drawing_data,
                'elemente_data' => $elemente
            );
            $schreibe = function ($d) use ($wpdb, $table) {
                return $wpdb->insert($table, $d);
            };
        }

        $ergebnis = $schreibe($daten);

        // Selbstheilung (Risiko R5): Fehlt die Spalte elemente_data auf dieser
        // Installation (run_updates()-Zweig noch nicht gelaufen), EINMAL
        // ensure_columns() und wiederholen. Scheitert es erneut, ohne die
        // Spalte speichern - das PNG geht nie verloren, Verhalten wie vor
        // AP-4.2.
        if (false === $ergebnis && false !== stripos((string) $wpdb->last_error, 'elemente_data')) {
            if (class_exists('CBD_Schema_Manager') && method_exists('CBD_Schema_Manager', 'ensure_columns')) {
                CBD_Schema_Manager::ensure_columns();
            }
            self::$elemente_spalte_vorhanden = null;
            $ergebnis = $schreibe($daten);

            if (false === $ergebnis && false !== stripos((string) $wpdb->last_error, 'elemente_data')) {
                unset($daten['elemente_data']);
                $ergebnis = $schreibe($daten);
                if (null !== $elemente) {
                    $elemente_verworfen = true;
                }
            }
        }

        // Rückfall bei JEDEM anderen Schreibfehler mit Elementliste
        // (AP-4.fix1, Befund F1 – z. B. max_allowed_packet, weil PNG und Liste
        // in EINER Anweisung stehen): einmal ohne Liste wiederholen. Das PNG
        // hat Vorrang (E7). elemente_data wird dabei auf NULL gesetzt, damit
        // keine ältere Liste neben dem neuen PNG stehen bleibt.
        if (false === $ergebnis && isset($daten['elemente_data'])) {
            $daten['elemente_data'] = null;
            $schreibe($daten);
            $elemente_verworfen = true;
        }

        // Pulsdatei der Klasse erneuern (Vorhaben „Schneller Klassenpuls“, AP-1.5).
        do_action('cbd_klassenmodus_geaendert', (int) $class_id);

        $antwort = array('message' => 'Zeichnung gespeichert.');
        if ($elemente_verworfen) {
            $antwort['elemente_verworfen'] = true;
        }
        wp_send_json_success($antwort);
    }

    /**
     * Elementliste einer Tafel aus `$_POST['elemente_data']` prüfen und
     * bereinigen (Plan „Tafel-Lasso", AP-4.2; Format: Plan, Abschnitt 4
     * „Elementmodell v1"; Gegenstück im Browser:
     * `CBDBoardElemente.pruefeModell()` in assets/js/board-elemente.js).
     *
     * Rückgabe:
     *   - null   kein Wert, leere Elementliste oder kein gültiges Element
     *            übrig -> Spalte auf NULL
     *   - false  unlesbar (kein Text, zu groß, kein JSON-Objekt, v != 1,
     *            Maße ungültig, elemente kein Array)
     *   - string bereinigte JSON (nur erlaubte Schlüssel, ungültige Elemente
     *            und doppelte IDs verworfen, Text auf 5000 Zeichen gekürzt)
     *
     * wp_unslash() MUSS vor json_decode() stehen (Risiko R7): WordPress
     * slasht alle $_POST-Werte, jedes Anführungszeichen der JSON käme als \"
     * an und json_decode() schlüge fehl. Harnisch: tools/test-board-elemente.php.
     *
     * @param mixed $roh Rohwert aus $_POST.
     * @return string|null|false
     */
    private function pruefe_elemente_json($roh) {
        if (null === $roh || '' === $roh) {
            return null;
        }
        if (!is_string($roh)) {
            return false;
        }

        $text = wp_unslash($roh);

        if (strlen($text) > self::ELEMENTE_MAX_BYTES) {
            return false;
        }

        // AP-4.fix1 (F1): zu viele Punkte -> gar nicht erst dekodieren.
        if (substr_count($text, '[') > self::ELEMENTE_MAX_PUNKTE) {
            return false;
        }

        $daten = json_decode($text, true);
        unset($text);
        if (!is_array($daten)
            || !array_key_exists('v', $daten) || 1 !== $daten['v']
            || !isset($daten['elemente']) || !is_array($daten['elemente'])) {
            return false;
        }

        $breite = self::elemente_zahl($daten['breite'] ?? null);
        $hoehe = self::elemente_zahl($daten['hoehe'] ?? null);
        if (null === $breite || null === $hoehe || $breite <= 0 || $hoehe <= 0) {
            return false;
        }

        // Gleiche Regel wie FARB_MUSTER in board-elemente.js.
        $farb_muster = '/^(#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6}|rgba?\(\s*[0-9.]+\s*,\s*[0-9.]+\s*,\s*[0-9.]+\s*(,\s*[0-9.]+\s*)?\))$/';
        $praefix = 'data:image/png;base64,';

        // Speicher sparen (AP-4.fix1, F1): Elemente einzeln abarbeiten, das
        // dekodierte Original sofort freigeben und jedes bereinigte Element
        // gleich als JSON-Text ablegen. So liegt nie die dekodierte UND die
        // bereinigte Liste gleichzeitig als PHP-Arrays im Speicher (halbiert
        // die Spitze). Ergebnis byte-gleich zu wp_json_encode() des Ganzen.
        $teile = array();
        $ids = array();
        $liste = $daten['elemente'];
        unset($daten);

        foreach (array_keys($liste) as $schluessel) {
            $el = $liste[$schluessel];
            unset($liste[$schluessel]);
            if (!is_array($el)) {
                continue;
            }
            $id = $el['id'] ?? null;
            if (!is_string($id) || !preg_match('/^e[0-9]+$/', $id) || isset($ids[$id])) {
                continue;
            }
            $typ = $el['typ'] ?? null;

            if ('strich' === $typ) {
                $werkzeug = $el['werkzeug'] ?? null;
                $farbe = $el['farbe'] ?? null;
                $b = self::elemente_zahl($el['breite'] ?? null);
                $punkte_roh = $el['punkte'] ?? null;
                if (('pen' !== $werkzeug && 'highlighter' !== $werkzeug)
                    || !is_string($farbe) || !preg_match($farb_muster, $farbe)
                    || null === $b || $b <= 0
                    || !is_array($punkte_roh) || count($punkte_roh) < 1 || count($punkte_roh) > 20000) {
                    continue;
                }
                $punkte = array();
                foreach ($punkte_roh as $p) {
                    if (!is_array($p) || 2 !== count($p) || !array_key_exists(0, $p) || !array_key_exists(1, $p)) {
                        $punkte = null;
                        break;
                    }
                    $px = self::elemente_zahl($p[0]);
                    $py = self::elemente_zahl($p[1]);
                    if (null === $px || null === $py) {
                        $punkte = null;
                        break;
                    }
                    $punkte[] = array($px, $py);
                }
                if (null === $punkte) {
                    continue;
                }
                $sauber = array('id' => $id, 'typ' => 'strich', 'werkzeug' => $werkzeug,
                    'farbe' => $farbe, 'breite' => $b, 'punkte' => $punkte);
            } elseif ('text' === $typ) {
                $farbe = $el['farbe'] ?? null;
                $b = self::elemente_zahl($el['breite'] ?? null);
                $x = self::elemente_zahl($el['x'] ?? null);
                $y = self::elemente_zahl($el['y'] ?? null);
                $inhalt = $el['text'] ?? null;
                if (!is_string($farbe) || !preg_match($farb_muster, $farbe)
                    || null === $b || $b <= 0 || null === $x || null === $y || !is_string($inhalt)) {
                    continue;
                }
                $sauber = array('id' => $id, 'typ' => 'text', 'farbe' => $farbe, 'breite' => $b,
                    'text' => mb_substr($inhalt, 0, 5000, 'UTF-8'), 'x' => $x, 'y' => $y);
            } elseif ('bild' === $typ) {
                $bild = $el['daten'] ?? null;
                $x = self::elemente_zahl($el['x'] ?? null);
                $y = self::elemente_zahl($el['y'] ?? null);
                $b = self::elemente_zahl($el['b'] ?? null);
                $h = self::elemente_zahl($el['h'] ?? null);
                if (!is_string($bild) || 0 !== strpos($bild, $praefix)
                    || !preg_match('/^[A-Za-z0-9+\/=]*$/', substr($bild, strlen($praefix)))
                    || null === $x || null === $y || null === $b || null === $h || $b <= 0 || $h <= 0) {
                    continue;
                }
                $sauber = array('id' => $id, 'typ' => 'bild', 'daten' => $bild,
                    'x' => $x, 'y' => $y, 'b' => $b, 'h' => $h);
            } else {
                continue; // unbekannter typ: nur dieses Element verwerfen
            }
            $teil = wp_json_encode($sauber);
            if (!is_string($teil)) {
                return false;
            }
            $teile[] = $teil;
            $ids[$id] = true;
            unset($el, $sauber, $punkte, $punkte_roh, $teil);
        }

        if (empty($teile)) {
            return null;
        }

        // Kopf wie bisher über wp_json_encode() (gleiche Zahlendarstellung),
        // die leere Liste "[]}" am Ende durch die Elemente ersetzt.
        $kopf = wp_json_encode(array('v' => 1, 'breite' => $breite, 'hoehe' => $hoehe, 'elemente' => array()));
        if (!is_string($kopf)) {
            return false;
        }
        return substr($kopf, 0, -2) . implode(',', $teile) . ']}';
    }

    /**
     * Zahl aus der Elementliste: is_numeric + endlich, sonst null. Numerische
     * Zeichenketten werden zu int/float (Ausgabe als JSON-Zahl).
     *
     * @param mixed $wert
     * @return int|float|null
     */
    private static function elemente_zahl($wert) {
        if (is_bool($wert) || !is_numeric($wert)) {
            return null;
        }
        $zahl = 0 + $wert;
        return is_finite((float) $zahl) ? $zahl : null;
    }

    /**
     * Hülle um die bereinigte Elementliste (AP-4.fix2, Befund F2 aus AP-4.rev):
     * {"png":"<md5 des gespeicherten drawing_data>","modell":<Liste>}.
     *
     * Grund: Beim Rückweg auf eine ältere Plugin-Version schreibt deren
     * ajax_save_drawing() nur drawing_data und updated_at, elemente_data bleibt
     * stehen. Ohne Abdruck lüde ein später wieder eingespieltes Phase-4-Plugin
     * die VERALTETE Liste, und das nächste Speichern machte daraus auch das
     * PNG für Schüler und PDF. Mit Abdruck erkennt ajax_load_drawing() die
     * Abweichung und liefert null – der Browser nimmt dann das PNG als EIN
     * Bild-Element (E10). Das Elementmodell v1 selbst bleibt unverändert; die
     * Hülle wird per Zeichenkette gebaut und beim Laden nur am festen Kopf
     * gelesen (kein json_decode der ganzen Liste – vgl. Speicherbedarf F1).
     * Gegenstück lokal: CBDBoardElemente.verpacke()/entpacke() (gleiche Form,
     * dort mit einem eigenen, synchronen Abdruck).
     *
     * @param string $png         Gespeichertes drawing_data (data-URL).
     * @param string $modell_json Bereinigte Liste aus pruefe_elemente_json().
     * @return string
     */
    private static function elemente_huelle($png, $modell_json) {
        return '{"png":"' . md5((string) $png) . '","modell":' . $modell_json . '}';
    }

    /**
     * Liste aus der Hülle, nur wenn ihr Abdruck zum aktuellen drawing_data
     * passt (AP-4.fix2). Werte ohne Hülle (Teststände vor AP-4.fix2), kaputte
     * Hüllen und eine Zeile ohne PNG liefern null.
     *
     * @param mixed $wert Spalte elemente_data.
     * @param mixed $png  Spalte drawing_data.
     * @return string|null
     */
    private static function elemente_aus_huelle($wert, $png) {
        if (!is_string($wert) || !is_string($png) || '' === $png) {
            return null;
        }
        $kopf = '{"png":"' . md5($png) . '","modell":';
        if (strlen($wert) <= strlen($kopf) + 2
            || 0 !== strncmp($wert, $kopf, strlen($kopf))
            || '}}' !== substr($wert, -2)) {
            return null;
        }
        return substr($wert, strlen($kopf), -1);
    }

    /**
     * Hat `cbd_drawings` die Spalte `elemente_data`? (Plan „Tafel-Lasso",
     * AP-4.2.) Auf einer Installation, deren run_updates()-Zweig noch nicht
     * gelaufen ist, würde ein SELECT mit der Spalte scheitern und das PNG
     * mitreißen. Einmal je Anfrage geprüft (statischer Zwischenspeicher).
     *
     * @return bool
     */
    private static function drawings_hat_elemente_spalte() {
        if (null === self::$elemente_spalte_vorhanden) {
            global $wpdb;
            $spalte = $wpdb->get_var($wpdb->prepare(
                "SHOW COLUMNS FROM `" . esc_sql(CBD_TABLE_DRAWINGS) . "` LIKE %s",
                'elemente_data'
            ));
            self::$elemente_spalte_vorhanden = !empty($spalte);
        }
        return self::$elemente_spalte_vorhanden;
    }

    /**
     * AJAX: Load a drawing from the server
     */
    public function ajax_load_drawing() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $class_id = intval($_POST['class_id'] ?? 0);
        $page_id = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');

        if ($class_id <= 0 || $page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // elemente_data (Plan „Tafel-Lasso", AP-4.2) nur abfragen, wenn die
        // Spalte existiert - auf einer noch nicht aktualisierten DB liefe das
        // SELECT sonst ins Leere und die Tafel käme leer an.
        $spalten = self::drawings_hat_elemente_spalte()
            ? 'drawing_data, elemente_data, is_behandelt'
            : 'drawing_data, is_behandelt';

        $drawing = $wpdb->get_row($wpdb->prepare(
            "SELECT " . $spalten . " FROM " . CBD_TABLE_DRAWINGS . " WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        wp_send_json_success(array(
            'drawing_data' => $drawing ? $drawing->drawing_data : null,
            'is_behandelt' => $drawing ? (bool) $drawing->is_behandelt : false,
            // String (JSON, Elementmodell v1) oder null; das JavaScript prüft
            // ihn mit CBDBoardElemente.deserialisiere(). Nur, wenn der
            // Abdruck in der Hülle zum PNG passt (AP-4.fix2) – sonst gilt das
            // PNG (E7); der Browser bekommt nie die Hülle, nur das Modell.
            'elemente_data' => ($drawing && isset($drawing->elemente_data))
                ? self::elemente_aus_huelle($drawing->elemente_data, $drawing->drawing_data)
                : null
        ));
    }

    /**
     * AJAX: Alle serverseitigen Tafelbilder einer Seite in EINEM Aufruf
     *
     * Für den PDF-Export gedacht: Eine Seite kann viele Container haben, ein
     * Request je Container skaliert schlecht (dieselbe N+1-Vermeidung wie bei
     * cbd/v1/blocks in class-cbd-blocks-rest-api.php).
     *
     * Das SQL-Muster ist von ajax_get_page_classroom_data() übernommen, das
     * Sicherheitsmodell aber ausdrücklich NICHT: Jene Methode prüft einen
     * Schüler-Transient-Token. Hier ruft eine regulär angemeldete Lehrperson
     * auf, deshalb dasselbe Muster wie ajax_load_drawing() — Nonce,
     * Capability cbd_edit_blocks und can_access_class(). Erst can_access_class()
     * verhindert, dass über eine fremde class_id Zeichnungen einer nicht
     * zugeordneten Klasse abgefragt werden; Capability allein genügt dafür
     * nicht, sie hat jede Lehrperson.
     *
     * Zwei Sicherheitsmodelle in einer Methode zu mischen wäre genau das
     * Muster, das das Projekt bei class-cbd-block-content-api.php bewusst
     * vermeidet.
     */
    public function ajax_get_page_drawings() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $class_id = intval($_POST['class_id'] ?? 0);
        $page_id = intval($_POST['page_id'] ?? 0);

        if ($class_id <= 0 || $page_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // drawing_data IS NOT NULL: Ein leerer Canvas wird von
        // ajax_save_drawing() als NULL abgelegt, die Zeile bleibt aber
        // stehen. Solche Container gehören nicht in den PDF-Export.
        $drawings = $wpdb->get_results($wpdb->prepare(
            "SELECT container_id, drawing_data FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND page_id = %d AND drawing_data IS NOT NULL",
            $class_id, $page_id
        ));

        // Antwortform bewusst ein flaches Array, nicht ein nach container_id
        // indiziertes Objekt — analog zu cbd/v1/blocks.
        $result = array();

        foreach ($drawings as $d) {
            $result[] = array(
                'container_id' => $d->container_id,
                'drawing_data' => $d->drawing_data
            );
        }

        wp_send_json_success(array('drawings' => $result));
    }

    // =========================================================================
    // TEACHER: Behandelt (Covered) Toggle
    // =========================================================================

    /**
     * AJAX: Toggle "behandelt" status for a block
     */
    public function ajax_toggle_behandelt() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = CBD_TABLE_DRAWINGS;

        $class_id = intval($_POST['class_id'] ?? 0);
        $page_id = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');

        if ($class_id <= 0 || $page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff prüfen: Besitzer oder Abonnent
        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // Get or create drawing record
        self::debug_log('[CBD Classroom] toggle_behandelt - Parameters: class_id=' . $class_id . ', page_id=' . $page_id . ', container_id=' . $container_id);

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_behandelt FROM $table WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        self::debug_log('[CBD Classroom] Existing drawing: ' . ($existing ? 'YES (id=' . $existing->id . ', is_behandelt=' . $existing->is_behandelt . ')' : 'NO'));

        if ($existing) {
            $new_status = $existing->is_behandelt ? 0 : 1;
            self::debug_log('[CBD Classroom] UPDATING existing drawing to status: ' . $new_status);

            $result = $wpdb->update($table, array(
                'is_behandelt' => $new_status,
                'updated_at' => current_time('mysql')
            ), array('id' => $existing->id));

            if ($result === false) {
                error_log('[CBD Classroom] UPDATE FAILED! Error: ' . $wpdb->last_error);
            } else {
                self::debug_log('[CBD Classroom] UPDATE successful. Rows affected: ' . $result);
            }
        } else {
            $new_status = 1;
            self::debug_log('[CBD Classroom] INSERTING new drawing with status: ' . $new_status);
            self::debug_log('[CBD Classroom] Insert data: ' . print_r(array(
                'class_id' => $class_id,
                'teacher_id' => get_current_user_id(),
                'page_id' => $page_id,
                'container_id' => $container_id,
                'is_behandelt' => 1
            ), true));

            $result = $wpdb->insert($table, array(
                'class_id' => $class_id,
                'teacher_id' => get_current_user_id(),
                'page_id' => $page_id,
                'container_id' => $container_id,
                'is_behandelt' => 1
            ));

            if ($result === false) {
                error_log('[CBD Classroom] INSERT FAILED! Error: ' . $wpdb->last_error);
                error_log('[CBD Classroom] Last query: ' . $wpdb->last_query);
            } else {
                self::debug_log('[CBD Classroom] INSERT successful. Insert ID: ' . $wpdb->insert_id);
            }
        }

        // Auto-assign page to class when first block is marked as behandelt
        if ($new_status == 1) {
            self::debug_log('[CBD Classroom] toggle_behandelt - New status is 1, checking if page should be auto-added');
            $page_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d AND page_id = %d",
                $class_id, $page_id
            ));

            self::debug_log('[CBD Classroom] Page exists in class_pages: ' . ($page_exists ? 'YES (ID: ' . $page_exists . ')' : 'NO'));

            if (!$page_exists) {
                // Get max sort_order for this class
                $max_order = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(sort_order) FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d",
                    $class_id
                ));

                $result = $wpdb->insert(CBD_TABLE_CLASS_PAGES, array(
                    'class_id' => $class_id,
                    'page_id' => $page_id,
                    'sort_order' => ($max_order !== null) ? ($max_order + 1) : 0
                ));

                if ($result) {
                    self::debug_log('[CBD Classroom] Successfully added page ' . $page_id . ' to class_pages for class ' . $class_id);
                } else {
                    error_log('[CBD Classroom] FAILED to add page to class_pages. Error: ' . $wpdb->last_error);
                }
            }
        }

        // Verify the drawing was created/updated
        $verify = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_behandelt FROM " . CBD_TABLE_DRAWINGS . " WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        // Pulsdatei der Klasse erneuern (Vorhaben „Schneller Klassenpuls“, AP-1.5).
        if ($verify) {
            do_action('cbd_klassenmodus_geaendert', (int) $class_id);
        }

        wp_send_json_success(array(
            'is_behandelt' => (bool) $new_status,
            'message' => $new_status ? 'Als behandelt markiert.' : 'Markierung entfernt.',
            'debug' => array(
                'drawing_id' => $verify ? $verify->id : null,
                'drawing_status' => $verify ? (bool) $verify->is_behandelt : null,
                'db_insert_result' => isset($result) ? $result : null,
                'db_last_error' => $wpdb->last_error ? $wpdb->last_error : null,
                'insert_id' => $wpdb->insert_id > 0 ? $wpdb->insert_id : null
            )
        ));
    }

    /**
     * AJAX: Set "behandelt" status to 1 (only sets, never clears)
     * Called automatically when a teacher draws for a class.
     */
    public function ajax_set_behandelt() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = CBD_TABLE_DRAWINGS;

        $class_id    = intval($_POST['class_id'] ?? 0);
        $page_id     = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');

        if ($class_id <= 0 || $page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff prüfen: Besitzer oder Abonnent
        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_behandelt FROM $table WHERE class_id = %d AND page_id = %d AND container_id = %s",
            $class_id, $page_id, $container_id
        ));

        if ($existing) {
            if (!$existing->is_behandelt) {
                $wpdb->update($table, array(
                    'is_behandelt' => 1,
                    'updated_at'   => current_time('mysql')
                ), array('id' => $existing->id));
            }
        } else {
            $wpdb->insert($table, array(
                'class_id'     => $class_id,
                'teacher_id'   => get_current_user_id(),
                'page_id'      => $page_id,
                'container_id' => $container_id,
                'is_behandelt' => 1
            ));
        }

        // Auto-assign page to class if not already assigned
        $page_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d AND page_id = %d",
            $class_id, $page_id
        ));

        if (!$page_exists) {
            $max_order = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(sort_order) FROM " . CBD_TABLE_CLASS_PAGES . " WHERE class_id = %d",
                $class_id
            ));
            $wpdb->insert(CBD_TABLE_CLASS_PAGES, array(
                'class_id'   => $class_id,
                'page_id'    => $page_id,
                'sort_order' => ($max_order !== null) ? ($max_order + 1) : 0
            ));
        }

        // Pulsdatei der Klasse erneuern (Vorhaben „Schneller Klassenpuls“, AP-1.5).
        do_action('cbd_klassenmodus_geaendert', (int) $class_id);

        wp_send_json_success(array('message' => 'Als behandelt markiert.'));
    }

    /**
     * AJAX: Get behandelt status for a block across all teacher's classes
     */
    public function ajax_get_block_status() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;

        $page_id = intval($_POST['page_id'] ?? 0);
        $container_id = sanitize_text_field($_POST['container_id'] ?? '');

        if ($page_id <= 0 || empty($container_id)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Eigene + abonnierte Klassen laden
        $teacher_id = get_current_user_id();
        $subscribed  = get_user_meta($teacher_id, 'cbd_subscribed_classes', true);
        $subscribed_ids = is_array($subscribed) ? array_map('intval', $subscribed) : array();

        if (!empty($subscribed_ids)) {
            $placeholders = implode(',', array_fill(0, count($subscribed_ids), '%d'));
            $params = array_merge(array($teacher_id), $subscribed_ids);
            $classes = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM " . CBD_TABLE_CLASSES . "
                 WHERE status = 'active' AND (teacher_id = %d OR id IN ($placeholders))
                 ORDER BY name ASC",
                $params
            ));
        } else {
            $classes = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM " . CBD_TABLE_CLASSES . "
                 WHERE teacher_id = %d AND status = 'active'
                 ORDER BY name ASC",
                $teacher_id
            ));
        }

        $status_data = array();

        foreach ($classes as $class) {
            // Check if this block is marked as behandelt for this class
            $is_behandelt = $wpdb->get_var($wpdb->prepare(
                "SELECT is_behandelt FROM " . CBD_TABLE_DRAWINGS . "
                 WHERE class_id = %d AND page_id = %d AND container_id = %s",
                $class->id, $page_id, $container_id
            ));

            $status_data[] = array(
                'id' => $class->id,
                'name' => $class->name,
                'is_behandelt' => (bool) $is_behandelt
            );
        }

        wp_send_json_success(array('classes' => $status_data));
    }

    // =========================================================================
    // STUDENT: Authentication & Data Access
    // =========================================================================

    /**
     * AJAX: Student authentication via class password
     */
    public function ajax_student_auth() {
        $class_id = intval($_POST['class_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $wp_nonce = $_POST['_wpnonce'] ?? '';

        // Nur Lehrer/Redakteure (Block-Verwaltungsrechte) dürfen das
        // Klassenpasswort überspringen – nicht jeder eingeloggte Benutzer.
        $wp_user_authenticated = false;
        if (is_user_logged_in()
            && current_user_can('cbd_edit_blocks')
            && wp_verify_nonce($wp_nonce, 'cbd_classroom_auth')) {
            $wp_user_authenticated = true;
        }

        if (!$wp_user_authenticated) {
            // Rate limiting check (only for password-based auth)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $rate_key = 'cbd_auth_attempts_' . md5($ip);
            $attempts = (int) get_transient($rate_key);

            if ($attempts >= 10) {
                wp_send_json_error(array('message' => 'Zu viele Versuche. Bitte spaeter erneut versuchen.'));
            }

            if ($class_id <= 0 || empty($password)) {
                wp_send_json_error(array('message' => 'Klasse und Passwort erforderlich.'));
            }
        } else {
            if ($class_id <= 0) {
                wp_send_json_error(array('message' => 'Bitte eine Klasse auswaehlen.'));
            }
        }

        global $wpdb;
        $class = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, password FROM " . CBD_TABLE_CLASSES . " WHERE id = %d AND status = 'active'",
            $class_id
        ));

        if (!$class) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // Password check only for non-WordPress-authenticated users
        if (!$wp_user_authenticated && !wp_check_password($password, $class->password)) {
            // Increment rate limit
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $rate_key = 'cbd_auth_attempts_' . md5($ip);
            $attempts = (int) get_transient($rate_key);
            set_transient($rate_key, $attempts + 1, 300); // 5 minutes
            wp_send_json_error(array('message' => 'Falsches Passwort.'));
        }

        // Generate session token
        $token = wp_generate_password(64, false);
        $token_key = 'cbd_classroom_' . $token;

        // Store token as transient (24 hours)
        set_transient($token_key, array(
            'class_id' => $class_id,
            'class_name' => $class->name,
            'created' => time()
        ), DAY_IN_SECONDS);

        wp_send_json_success(array(
            'token' => $token,
            'class_name' => $class->name,
            'class_id' => $class_id
        ));
    }

    /**
     * AJAX: Get class data for authenticated student
     */
    public function ajax_student_get_data() {
        $token = sanitize_text_field($_POST['token'] ?? '');

        if (empty($token)) {
            wp_send_json_error(array('message' => 'Nicht authentifiziert.'));
        }

        $token_key = 'cbd_classroom_' . $token;
        $session = get_transient($token_key);

        if (!$session) {
            wp_send_json_error(array('message' => 'Sitzung abgelaufen. Bitte erneut einloggen.'));
        }

        global $wpdb;
        $class_id = $session['class_id'];

        // NEW APPROACH: Return page list instead of rendering full content
        // Pages will be loaded individually when user clicks on them

        // STEP 1: Get ALL pages assigned to this class
        self::debug_log('[CBD Classroom] ajax_student_get_data - Class ID: ' . $class_id);

        $all_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT cp.page_id, p.post_title, p.post_parent, p.menu_order
             FROM " . CBD_TABLE_CLASS_PAGES . " cp
             INNER JOIN {$wpdb->posts} p ON cp.page_id = p.ID
             WHERE cp.class_id = %d AND p.post_status = 'publish'
             ORDER BY cp.sort_order ASC",
            $class_id
        ));

        self::debug_log('[CBD Classroom] Found ' . count($all_pages) . ' total pages in class');

        // STEP 2: Get list of page IDs that have behandelt blocks
        $treated_page_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT page_id FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND is_behandelt = 1",
            $class_id
        ));

        self::debug_log('[CBD Classroom] Found ' . count($treated_page_ids) . ' pages with behandelt blocks');

        // STEP 3: Build hierarchy and determine which pages to show
        $pages_to_show = array();
        $parent_ids_to_show = array(); // Parents of treated pages

        foreach ($all_pages as $page) {
            $is_treated = in_array($page->page_id, $treated_page_ids);

            if ($is_treated) {
                // This page has behandelt blocks - show it with link
                $pages_to_show[$page->page_id] = array(
                    'page_id' => $page->page_id,
                    'title' => $page->post_title,
                    'parent_id' => (int) $page->post_parent,
                    'menu_order' => (int) $page->menu_order,
                    'is_treated' => true,
                    'treated_count' => $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM " . CBD_TABLE_DRAWINGS . "
                         WHERE class_id = %d AND page_id = %d AND is_behandelt = 1",
                        $class_id, $page->page_id
                    )),
                    'url' => add_query_arg(array(
                        'classroom' => $class_id,
                        'token' => $token
                    ), get_permalink($page->page_id))
                );

                // Mark parent chain to show (grayed out)
                $current_parent_id = $page->post_parent;
                while ($current_parent_id > 0) {
                    $parent_ids_to_show[$current_parent_id] = true;
                    $parent_post = get_post($current_parent_id);
                    $current_parent_id = $parent_post ? $parent_post->post_parent : 0;
                }
            }
        }

        // Add parent pages (grayed out, no link)
        foreach ($parent_ids_to_show as $parent_id => $value) {
            if (!isset($pages_to_show[$parent_id])) {
                $parent_post = get_post($parent_id);
                if ($parent_post) {
                    $pages_to_show[$parent_id] = array(
                        'page_id' => $parent_id,
                        'title' => $parent_post->post_title,
                        'parent_id' => (int) $parent_post->post_parent,
                        'menu_order' => (int) $parent_post->menu_order,
                        'is_treated' => false,
                        'treated_count' => 0,
                        'url' => null, // No URL = grayed out
                        'is_parent_only' => true
                    );
                }
            }
        }

        // STEP 4: Build hierarchical structure with unlimited depth
        // First, organize pages by parent_id for quick lookup
        $children_by_parent = array();
        foreach ($pages_to_show as $page_data) {
            $parent_id = $page_data['parent_id'];
            if (!isset($children_by_parent[$parent_id])) {
                $children_by_parent[$parent_id] = array();
            }
            $children_by_parent[$parent_id][] = $page_data;
        }

        // Sort each group by WordPress menu_order (= Seitenreihenfolge der Website)
        foreach ($children_by_parent as &$children) {
            usort($children, function($a, $b) {
                return $a['menu_order'] - $b['menu_order'];
            });
        }
        unset($children);

        // Recursive function to build tree
        $build_tree = function($parent_id, $level = 0) use (&$build_tree, $children_by_parent, $pages_to_show) {
            $result = array();

            if (!isset($children_by_parent[$parent_id])) {
                return $result;
            }

            foreach ($children_by_parent[$parent_id] as $page) {
                // Add current page with level info
                $page['level'] = $level;
                $result[] = $page;

                // Recursively add children
                $children = $build_tree($page['page_id'], $level + 1);
                $result = array_merge($result, $children);
            }

            return $result;
        };

        // Build flat list starting from top-level pages (parent_id = 0)
        $flat_pages = $build_tree(0, 0);

        self::debug_log('[CBD Classroom] Built flat list with ' . count($flat_pages) . ' pages');

        // Convert flat list to format expected by frontend
        $grouped_pages = array();
        foreach ($flat_pages as $page) {
            $grouped_pages[] = array(
                'type' => 'page',
                'page' => $page
            );
        }

        wp_send_json_success(array(
            'class_name' => $session['class_name'],
            'pages' => $grouped_pages
        ));
    }

    /**
     * AJAX: Get list of public classes (names only, no passwords)
     */
    public function ajax_get_public_classes() {
        global $wpdb;

        $classes = $wpdb->get_results(
            "SELECT id, name FROM " . CBD_TABLE_CLASSES . " WHERE status = 'active' ORDER BY name ASC"
        );

        wp_send_json_success($classes);
    }

    // =========================================================================
    // SHORTCODE: Student Access Page
    // =========================================================================

    /**
     * Inline-CSS, das die Theme-Akzentfarbe (UI-Oberflaechen-Farbe) als
     * CSS-Custom-Property auf body setzt. Ersetzt den bislang nirgends
     * gesetzten Fallback-Wert von --cbd-classroom-accent
     * (classroom-frontend.css:946) durch einen echten Wert und speist
     * zusaetzlich die Randlinien-Regeln, die seit AP-1.1
     * var(--cbd-classroom-accent, #e24614) verwenden.
     */
    private function classroom_accent_inline_css(): string {
        $farbe = get_theme_mod('color_ui_surface', '#e24614');
        return 'body{--cbd-classroom-accent:' . esc_attr($farbe) . ';}';
    }

    /**
     * Render the [cbd_classroom] shortcode
     */
    public function render_classroom_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts);

        // Enqueue frontend assets
        wp_enqueue_style(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/css/classroom-frontend.css',
            array(),
            CBD_VERSION
        );
        wp_add_inline_style('cbd-classroom-frontend', $this->classroom_accent_inline_css());

        wp_enqueue_script(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/js/classroom-frontend.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        wp_localize_script('cbd-classroom-frontend', 'cbdClassroomFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'isUserLoggedIn' => is_user_logged_in(),
            'nonce' => wp_create_nonce('cbd_classroom_auth'),
            'i18n' => array(
                'selectClass' => __('Klasse auswaehlen', 'container-block-designer'),
                'enterPassword' => __('Passwort eingeben', 'container-block-designer'),
                'login' => __('Einloggen', 'container-block-designer'),
                'logout' => __('Abmelden', 'container-block-designer'),
                'wrongPassword' => __('Falsches Passwort.', 'container-block-designer'),
                'sessionExpired' => __('Sitzung abgelaufen.', 'container-block-designer'),
                'loading' => __('Lade...', 'container-block-designer'),
                'treated' => __('Behandelt', 'container-block-designer'),
                'notTreated' => __('Nicht behandelt', 'container-block-designer')
            )
        ));

        ob_start();
        ?>
        <div id="cbd-classroom-app" class="cbd-classroom-container">
            <div class="cbd-classroom-auth" id="cbd-classroom-auth">
                <h2><?php _e('Klassen-Zugang', 'container-block-designer'); ?></h2>
                <div class="cbd-classroom-form">
                    <div class="cbd-classroom-field">
                        <label for="cbd-class-select"><?php _e('Klasse:', 'container-block-designer'); ?></label>
                        <select id="cbd-class-select">
                            <option value=""><?php _e('-- Klasse waehlen --', 'container-block-designer'); ?></option>
                        </select>
                    </div>
                    <div class="cbd-classroom-field">
                        <label for="cbd-class-password"><?php _e('Passwort:', 'container-block-designer'); ?></label>
                        <input type="password" id="cbd-class-password" placeholder="<?php esc_attr_e('Passwort eingeben', 'container-block-designer'); ?>">
                    </div>
                    <button type="button" id="cbd-class-login" class="button button-primary">
                        <?php _e('Einloggen', 'container-block-designer'); ?>
                    </button>
                    <div class="cbd-classroom-error" id="cbd-classroom-error" style="display:none;"></div>
                </div>
            </div>
            <div class="cbd-classroom-content" id="cbd-classroom-content" style="display:none;">
                <div class="cbd-classroom-header">
                    <span class="cbd-classroom-class-name" id="cbd-classroom-class-name"></span>
                    <?php
                    // Nur ein X statt des Wortes "Abmelden": das Wort wurde in
                    // der Praxis nicht angezeigt (der Knopf blieb leer), und im
                    // farbigen Band reicht das Symbol. Der Text bleibt als
                    // aria-label/title erhalten — sonst haette der Knopf fuer
                    // Screenreader und beim Ueberfahren gar keine Beschriftung.
                    // Die Klasse cbd-classroom-logout ist wichtig: das CSS
                    // stylt darueber (und ueber die ID), vorher trug sie kein
                    // Element und die Regeln liefen ins Leere.
                    ?>
                    <button type="button" id="cbd-class-logout" class="button cbd-classroom-logout"
                            aria-label="<?php esc_attr_e('Abmelden', 'container-block-designer'); ?>"
                            title="<?php esc_attr_e('Abmelden', 'container-block-designer'); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="cbd-classroom-pages" id="cbd-classroom-pages"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // SHORTCODE: Klassenverwaltung fuer Lehrpersonen im Frontend
    // =========================================================================
    //
    // Dieselbe Verwaltung wie admin/classroom.php, aber auf einer ganz normalen
    // WordPress-Seite statt im wp-admin. Es entsteht dabei KEINE neue
    // Sicherheitsflaeche: Die Oberflaeche spricht ueber assets/js/classroom-admin.js
    // exakt dieselben vier AJAX-Actions an (cbd_get_classes, cbd_save_class,
    // cbd_delete_class, cbd_toggle_class_subscription), jede mit derselben
    // Nonce-Pruefung (cbd_classroom_nonce) und derselben Capability-Pruefung
    // (cbd_edit_blocks) wie bisher. Die ajax_*-Methoden oben sind unveraendert.
    //
    // WIEDERVERWENDUNG STATT ZWEITER FASSUNG: assets/js/classroom-admin.js wird
    // hier unveraendert mitgeladen. Die Datei macht keine adminspezifischen
    // Annahmen ausser drei Anknuepfungspunkten im Markup, die das Frontend-Markup
    // deshalb bewusst zeichengleich mitbringt:
    //   1. der Wrapper traegt die Klasse `cbd-classroom-admin` — daran erkennt
    //      der $(document).ready()-Zweig am Dateiende, dass er starten soll;
    //   2. im Wrapper steht ein <h1> — showNotice() haengt seine Meldung mit
    //      $('.cbd-classroom-admin h1').after() dahinter; ohne <h1> verschwaende
    //      jede Rueckmeldung ("gespeichert", "geloescht") stillschweigend;
    //   3. alle Element-IDs und Klassennamen des Formulars und der Tabelle sind
    //      dieselben wie in admin/classroom.php.
    // Eine zweite, schlanke JS-Fassung haette rund 500 Zeilen Logik verdoppelt
    // (Seitenzuordnung mit automatischen Unterseiten, Abonnieren, Bearbeiten) —
    // im Projekt gilt dafuer die Regel, dass zwei Fassungen frueher oder spaeter
    // auseinanderlaufen. Die verbleibenden WP-Admin-Klassennamen im Markup
    // (`button`, `notice`, `wp-list-table`, `spinner`) haben im Frontend keine
    // Gestaltung; die liefert assets/css/lehrer-klassen.css nach.
    //
    // BEKANNTE GRENZE: [cbd_lehrer_klassen] und [cbd_classroom] duerfen NICHT
    // auf derselben Seite stehen — beide Oberflaechen benutzen die Element-ID
    // `cbd-class-password` (hier das Klassenpasswort im Verwaltungsformular,
    // dort das Passwortfeld des Schueler-Logins). Zwei Seiten, wie vorgesehen,
    // sind unproblematisch.

    /**
     * Aktuelle Seiten-URL — Rueckkehrziel nach der Anmeldung.
     *
     * Der Host kommt bewusst NICHT aus $_SERVER['HTTP_HOST'] (vom Aufrufer
     * frei setzbar), sondern aus home_url(); nur der Pfad stammt aus der
     * Anfrage. So kann ein manipulierter Host-Header das Anmelde-Rueckziel
     * nicht auf eine fremde Adresse umbiegen.
     */
    private function lehrer_klassen_seiten_url(): string {
        $permalink = get_permalink();
        if (is_string($permalink) && '' !== $permalink) {
            return $permalink;
        }

        if (!empty($_SERVER['REQUEST_URI'])) {
            $pfad = wp_unslash($_SERVER['REQUEST_URI']);
            if (is_string($pfad) && '' !== $pfad) {
                return home_url($pfad);
            }
        }

        return home_url('/');
    }

    /**
     * Hinweiskasten (deaktiviertes System, fehlende Berechtigung).
     */
    private function lehrer_klassen_hinweis(string $text): string {
        return '<div class="cbd-lehrer-klassen cbd-lehrer-klassen--hinweis"><p>'
            . esc_html($text) . '</p></div>';
    }

    /**
     * Anmelde-Bereich fuer nicht angemeldete Besucher.
     *
     * WARUM EIN LINK AUF wp_login_url() UND KEIN EINGEBETTETES wp_login_form():
     * wp_login_url() laeuft durch den Filter `login_url`, wp_login_form() nicht —
     * dessen action-Attribut ist fest auf site_url('wp-login.php', 'login_post')
     * verdrahtet. Auf Installationen, die die Anmeldeseite verschieben oder
     * umbenennen (Sicherheits-Plugins), zeigt das eingebettete Formular also ins
     * Leere. Dazu kommt: Zwei-Faktor-Abfragen, Fehlermeldungen bei falschem
     * Passwort, "Passwort vergessen" und die Test-Cookie-Pruefung laufen alle
     * auf der kanonischen Anmeldeseite; ein eingebettetes Formular schickt den
     * Nutzer bei jedem dieser Faelle ohnehin dorthin, nur ohne Kontext.
     * `redirect_to` bringt ihn danach hierher zurueck.
     */
    private function render_lehrer_klassen_login(): string {
        $ziel = $this->lehrer_klassen_seiten_url();

        $html  = '<div class="cbd-lehrer-klassen cbd-lehrer-klassen--login">';
        $html .= '<h2 class="cbd-lehrer-klassen__titel">'
            . esc_html__('Anmeldung erforderlich', 'container-block-designer') . '</h2>';
        $html .= '<p>' . esc_html__('Diese Seite ist der Klassen-Verwaltung fuer Lehrpersonen vorbehalten. Nach der Anmeldung kehren Sie automatisch hierher zurueck.', 'container-block-designer') . '</p>';
        $html .= '<p><a class="button button-primary cbd-lehrer-klassen__login-link" href="'
            . esc_url(wp_login_url($ziel)) . '">'
            . esc_html__('Zur Anmeldung', 'container-block-designer') . '</a></p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Die Seitenliste der Zuordnung — als Daten, nicht als Markup.
     *
     * WARUM NICHT ALS <option>-MARKUP WIE IN admin/classroom.php: Dort steht die
     * Liste im Adminbereich, hier im Seiteninhalt — und der laeuft durch
     * `the_content`. Die Glossar-Autoverlinkung des Themes
     * (`Theme/functions.php`, Filter auf `the_content`, Prioritaet 10000)
     * zerlegt den Inhalt in Textstuecke und wendet auf jedes einen aus ALLEN
     * Glossarbegriffen gebauten regulaeren Ausdruck an. Auf der
     * Testinstallation (281 veroeffentlichte Seiten, 1155 Glossarbegriffe) sind
     * das 281 zusaetzliche Textstuecke bei einem rund 800 kB grossen Muster —
     * GEMESSEN: HTTP 500, „Maximum execution time of 30 seconds exceeded",
     * jedes Mal reproduzierbar. Die Adminseite kann das nicht passieren, dort
     * laeuft `the_content` nicht.
     *
     * Die Liste geht deshalb ueber `wp_localize_script()` in den Footer — also
     * an `the_content` vorbei — und `assets/js/lehrer-klassen.js` baut daraus
     * die `<option>`-Elemente, bevor `classroom-admin.js` startet. Nebeneffekt,
     * der die Entscheidung stuetzt: Der Titel wird dort ueber `textContent`
     * gesetzt, kann also gar kein Markup einschleusen.
     *
     * Ausserdem ohne die zwei Abfragen JE SEITE, die admin/classroom.php an
     * dieser Stelle braucht (`get_pages(array('child_of' => …))` und
     * `get_post_ancestors()`): Elternschaft und Tiefe werden einmal aus der
     * bereits geladenen Liste abgeleitet.
     *
     * Feiner Unterschied dabei, bewusst hingenommen: Liegt eine veroeffentlichte
     * Seite unter einem Entwurf, kennt diese Liste deren Elternkette nicht und
     * rueckt die Seite eine Stufe weniger ein. Rein optisch — die Seiten-ID ist
     * davon unberuehrt.
     *
     * @return array Liste aus array('id','parent','tiefe','titel','kinder')
     */
    private function lehrer_klassen_seitendaten(): array {
        $pages = get_pages(array(
            'sort_column'  => 'menu_order, post_title',
            'post_status'  => 'publish',
            'hierarchical' => true,
        ));

        if (!is_array($pages) || empty($pages)) {
            return array();
        }

        $eltern     = array();
        $hat_kinder = array();
        foreach ($pages as $page) {
            $eltern[(int) $page->ID] = (int) $page->post_parent;
            if ((int) $page->post_parent > 0) {
                $hat_kinder[(int) $page->post_parent] = true;
            }
        }

        $daten = array();
        foreach ($pages as $page) {
            // Tiefe ueber die Elternkette. Die Bremse bei 20 schuetzt gegen
            // verstuemmelte Daten (Zyklus in post_parent), sie ist keine
            // fachliche Aussage — dasselbe Vorgehen wie in
            // CBD_Blocks_REST_API::baue_seitenbaum().
            $tiefe = 0;
            $lauf  = (int) $page->post_parent;
            while ($lauf > 0 && $tiefe < 20 && isset($eltern[$lauf])) {
                $tiefe++;
                $lauf = $eltern[$lauf];
            }

            $daten[] = array(
                'id'     => (int) $page->ID,
                'parent' => (int) $page->post_parent,
                'tiefe'  => $tiefe,
                'titel'  => (string) $page->post_title,
                'kinder' => isset($hat_kinder[(int) $page->ID]),
            );
        }

        return $daten;
    }

    /**
     * Die eigentliche Verwaltungsoberflaeche.
     *
     * Markup-Zwilling von admin/classroom.php — jede Element-ID und jede von
     * classroom-admin.js gelesene Klasse ist zeichengleich uebernommen. Wer dort
     * eine ID aendert, muss hier mitziehen (und umgekehrt).
     */
    private function render_lehrer_klassen_verwaltung(): string {
        ob_start();
        ?>
        <div class="cbd-lehrer-klassen cbd-classroom-admin">
            <?php
            // Das <h1> ist Pflicht, nicht Zierde: showNotice() in
            // classroom-admin.js haengt seine Rueckmeldungen mit
            // $('.cbd-classroom-admin h1').after() dahinter.
            ?>
            <h1 class="cbd-lehrer-klassen__titel"><?php esc_html_e('Klassen-Verwaltung', 'container-block-designer'); ?></h1>

            <div class="cbd-classroom-wrapper">
                <!-- Neue Klasse erstellen -->
                <div class="cbd-classroom-form-section">
                    <h2 id="cbd-form-title"><?php esc_html_e('Neue Klasse erstellen', 'container-block-designer'); ?></h2>
                    <form id="cbd-class-form" class="cbd-class-form">
                        <input type="hidden" id="cbd-class-id" value="0">
                        <table class="form-table">
                            <tr>
                                <th><label for="cbd-class-name"><?php esc_html_e('Klassenname', 'container-block-designer'); ?></label></th>
                                <td>
                                    <input type="text" id="cbd-class-name" class="regular-text" required
                                           placeholder="<?php esc_attr_e('z.B. 3a Chemie 2026', 'container-block-designer'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbd-class-password"><?php esc_html_e('Passwort', 'container-block-designer'); ?></label></th>
                                <td>
                                    <input type="text" id="cbd-class-password" class="regular-text"
                                           placeholder="<?php esc_attr_e('Passwort fuer Schueler-Zugang', 'container-block-designer'); ?>">
                                    <p class="description" id="cbd-password-hint">
                                        <?php esc_html_e('Dieses Passwort benoetigen die Schueler zum Zugriff auf die Klasse.', 'container-block-designer'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbd-class-schueler-fragen"><?php esc_html_e('Fragenwand', 'container-block-designer'); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="cbd-class-schueler-fragen">
                                        <?php esc_html_e('Schueler duerfen hier Fragen zur Fragenwand stellen', 'container-block-designer'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Eingereichte Fragen sind anonym - es wird nicht gespeichert, wer sie gestellt hat. Abhaken, Bearbeiten und Loeschen bleiben ausschliesslich der Lehrperson vorbehalten.', 'container-block-designer'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('Zugeordnete Seiten', 'container-block-designer'); ?></label></th>
                                <td>
                                    <div id="cbd-class-pages" class="cbd-class-pages">
                                        <div class="cbd-page-selector">
                                            <?php
                                            // Nur der Platzhalter steht im Markup — die eigentliche
                                            // Seitenliste ergaenzt assets/js/lehrer-klassen.js aus
                                            // window.cbdLehrerKlassen.seiten. Begruendung:
                                            // lehrer_klassen_seitendaten().
                                            ?>
                                            <select class="cbd-page-select">
                                                <option value=""><?php esc_html_e('-- Seite waehlen --', 'container-block-designer'); ?></option>
                                            </select>
                                            <button type="button" class="button cbd-remove-page" title="<?php esc_attr_e('Entfernen', 'container-block-designer'); ?>">&times;</button>
                                        </div>
                                    </div>
                                    <div class="cbd-page-actions">
                                        <button type="button" id="cbd-add-page" class="button">
                                            + <?php esc_html_e('Seite hinzufuegen', 'container-block-designer'); ?>
                                        </button>
                                        <button type="button" id="cbd-add-all-pages" class="button">
                                            <?php esc_html_e('Alle Seiten auswaehlen', 'container-block-designer'); ?>
                                        </button>
                                        <label>
                                            <input type="checkbox" id="cbd-include-children" checked>
                                            <?php esc_html_e('Unterseiten automatisch einbeziehen', 'container-block-designer'); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary" id="cbd-save-class">
                                <?php esc_html_e('Klasse speichern', 'container-block-designer'); ?>
                            </button>
                            <button type="button" class="button" id="cbd-cancel-edit" style="display:none;">
                                <?php esc_html_e('Abbrechen', 'container-block-designer'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Klassen-Liste -->
                <div class="cbd-classroom-list-section">
                    <h2><?php esc_html_e('Alle Klassen', 'container-block-designer'); ?></h2>
                    <p class="description"><?php esc_html_e('★ = Ihre eigene Klasse. Fremde Klassen können abonniert werden, um sie im Tafel-Modus zu nutzen.', 'container-block-designer'); ?></p>
                    <div id="cbd-classes-loading" class="cbd-loading">
                        <span class="spinner is-active"></span>
                        <?php esc_html_e('Lade Klassen...', 'container-block-designer'); ?>
                    </div>
                    <table class="wp-list-table widefat fixed striped" id="cbd-classes-table" style="display:none;">
                        <thead>
                            <tr>
                                <th class="column-name"><?php esc_html_e('Name', 'container-block-designer'); ?></th>
                                <th class="column-owner"><?php esc_html_e('Ersteller', 'container-block-designer'); ?></th>
                                <th class="column-pages"><?php esc_html_e('Seiten', 'container-block-designer'); ?></th>
                                <th class="column-status"><?php esc_html_e('Status', 'container-block-designer'); ?></th>
                                <th class="column-created"><?php esc_html_e('Erstellt', 'container-block-designer'); ?></th>
                                <th class="column-actions"><?php esc_html_e('Aktionen', 'container-block-designer'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="cbd-classes-body">
                        </tbody>
                    </table>
                    <p id="cbd-no-classes" style="display:none;">
                        <?php esc_html_e('Noch keine Klassen vorhanden.', 'container-block-designer'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the [cbd_lehrer_klassen] shortcode
     *
     * Drei Zustaende, in dieser Reihenfolge:
     *   1. Klassen-System abgeschaltet  -> Hinweis (wie admin/classroom.php)
     *   2. nicht angemeldet             -> Anmelde-Bereich
     *   3. angemeldet ohne cbd_edit_blocks -> "Keine Berechtigung."
     *   4. angemeldet mit cbd_edit_blocks   -> Verwaltungsoberflaeche
     *
     * Diese Pruefung entscheidet nur ueber die ANZEIGE. Die eigentliche
     * Absicherung liegt unveraendert in den AJAX-Handlern (Nonce + Capability
     * + Besitzpruefung je Klasse); ein Aufruf ohne Berechtigung waere dort
     * genauso abgewiesen wie zuvor ueber die Adminseite.
     */
    public function render_lehrer_klassen_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts, self::SHORTCODE_LEHRER_KLASSEN);

        if (!self::is_enabled()) {
            return $this->lehrer_klassen_hinweis(
                __('Das Klassen-System ist derzeit deaktiviert.', 'container-block-designer')
            );
        }

        if (!is_user_logged_in()) {
            return $this->render_lehrer_klassen_login();
        }

        if (!current_user_can('cbd_edit_blocks')) {
            return $this->lehrer_klassen_hinweis(
                __('Keine Berechtigung.', 'container-block-designer')
            );
        }

        return $this->render_lehrer_klassen_verwaltung();
    }

    /**
     * Assets der Frontend-Klassenverwaltung — nur auf Seiten mit dem Shortcode.
     *
     * Zwei Stufen, absichtlich getrennt:
     *   - Das CSS wird geladen, sobald der Shortcode auf der Seite steht. Auch
     *     der Hinweis- und der Anmeldekasten brauchen Gestaltung.
     *   - Das JavaScript samt Nonce nur, wenn die Oberflaeche wirklich gerendert
     *     wird. Ein Nonce fuer jemanden auszugeben, der die Endpunkte ohnehin
     *     nicht benutzen darf, waere unnoetig.
     */
    public function enqueue_lehrer_klassen_assets() {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        if (!has_shortcode($post->post_content, self::SHORTCODE_LEHRER_KLASSEN)) {
            return;
        }

        wp_enqueue_style(
            'cbd-lehrer-klassen',
            CBD_PLUGIN_URL . 'assets/css/lehrer-klassen.css',
            array(),
            CBD_VERSION
        );

        if (!self::is_enabled() || !is_user_logged_in() || !current_user_can('cbd_edit_blocks')) {
            return;
        }

        // Fuellt die Seitenauswahl aus localisierten Daten. Muss VOR
        // classroom-admin.js laufen — dessen init() klont die fertige
        // .cbd-page-selector-Zeile als Vorlage. Die Reihenfolge sichert die
        // Abhaengigkeit unten, nicht die Aufrufreihenfolge hier.
        wp_enqueue_script(
            'cbd-lehrer-klassen',
            CBD_PLUGIN_URL . 'assets/js/lehrer-klassen.js',
            array(),
            CBD_VERSION,
            true
        );

        wp_localize_script('cbd-lehrer-klassen', 'cbdLehrerKlassen', array(
            'seiten' => $this->lehrer_klassen_seitendaten(),
        ));

        // DIESELBE Datei wie im Admin, unveraendert. Handle-Namensgleichheit ist
        // unkritisch: Admin und Frontend sind getrennte Anfragen.
        wp_enqueue_script(
            'cbd-classroom-admin',
            CBD_PLUGIN_URL . 'assets/js/classroom-admin.js',
            array('jquery', 'cbd-lehrer-klassen'),
            CBD_VERSION,
            true
        );

        wp_localize_script('cbd-classroom-admin', 'cbdClassroomAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('cbd_classroom_nonce'),
        ));
    }

    // =========================================================================
    // HELPER: Get classes for current teacher (used by board-mode)
    // =========================================================================

    // =========================================================================
    // FLACKERSCHUTZ AUF NORMALEN KLASSENSEITEN
    // =========================================================================

    /**
     * Alles, was der Browser fuer den Erstaufbau einer normalen Klassenseite
     * braucht — serverseitig aufgeloest, damit er nicht erst fragen muss.
     *
     * Rueckgabe `null`, wenn nichts ausgegeben werden darf. Das ist der
     * Regelfall fuer jeden Besucher OHNE gueltige Klassensitzung: Die
     * Token-Deutung besorgt AUSSCHLIESSLICH CBD_Classroom_Gate::sitzung()
     * (die einzige Stelle im Plugin, die das darf — hier entsteht KEINE
     * zweite Fassung), und die entscheidet allein nach dem Transient, nicht
     * nach `?classroom=`.
     *
     * @param int          $page_id
     * @param WP_Post|null $post
     * @return array|null array('freigegeben' => string[], 'hatTafelbilder' => bool,
     *                          'klasse' => string, 'regeln' => string)
     */
    private function klassen_vorfilter($page_id, $post) {
        $page_id = (int) $page_id;

        if ($page_id <= 0 || !is_a($post, 'WP_Post') || !class_exists('CBD_Classroom_Gate')) {
            return null;
        }

        $sitzung = CBD_Classroom_Gate::sitzung();
        if (!is_array($sitzung) || empty($sitzung['class_id'])) {
            return null;
        }

        $class_id    = (int) $sitzung['class_id'];
        $freigegeben = self::behandelte_container($class_id, $page_id);

        // Alle Container der Seite abzueglich der freigegebenen. Nur fuer
        // diese Differenz entstehen Regeln — NIEMALS eine Aufdeckenregel,
        // siehe versteck_regeln().
        $alle       = self::container_ids_aus_inhalt($post->post_content);
        $verstecken = array_values(array_diff($alle, $freigegeben));

        return array(
            'freigegeben'    => array_values($freigegeben),
            'hatTafelbilder' => $this->hat_tafelbilder($class_id, $page_id),
            'klasse'         => $this->klassenname($class_id),
            'regeln'         => $this->versteck_regeln($verstecken),
        );
    }

    /**
     * CSS, das die nicht freigegebenen Container versteckt.
     *
     * NUR VERSTECKEN-REGELN, NIEMALS AUFDECKEN-REGELN. Das ist der Kern
     * dieses Entwurfs, keine Stilfrage:
     *   - Der urspruengliche `display`-Wert eines Containers bleibt dadurch
     *     bedeutungslos — es muss nichts zurueckgesetzt werden.
     *   - Die Fehlerrichtung ist damit festgelegt: Eine fehlende oder falsche
     *     Regel kann hoechstens das Verstecken VERPASSEN (dann greift wie
     *     bisher der Filter im Browser). Sie kann niemals etwas faelschlich
     *     AUFDECKEN.
     *
     * `!important` ist noetig, weil `cbd-frontend-clean.css` an mehreren
     * Stellen selbst mit `!important` auf `.cbd-container` schreibt.
     *
     * DIESE REGELN GELTEN NUR BIS ZUM ERSTEN SKRIPTLAUF. `init()` in
     * `assets/js/classroom-page-filter.js` versteckt dieselben Container
     * synchron noch einmal per Inline-Stil und nimmt die Regeln danach
     * wieder aus dem Stylesheet (`loeseVorfilterRegelnAb()`). Das ist
     * ZWINGEND, keine Kosmetik: Der Filter erkennt den Zustandswechsel eines
     * Containers an dessen eigenem Inline-Stil, und ein `.show()` von jQuery
     * setzt `display` OHNE `!important` — blieben die Regeln stehen, kaeme
     * eine Live-Freigabe (Phase 2) beim Schueler nie an. Die Begruendung in
     * ganzer Laenge steht im Docblock von `loeseVorfilterRegelnAb()`; wer
     * hier etwas am Selektor aendert, muss die dortige Erkennung
     * (`selectorText` beginnt mit `[data-stable-id=`) mitziehen.
     *
     * Nur Kennungen aus einem engen Zeichenvorrat erzeugen eine Regel
     * (Buchstaben, Ziffern, `_ - . : +` — echte Kennungen sehen aus wie
     * `cbd-1771189754-VwC7kbc7`, aeltere Bestaende auch wie
     * `cbd-1.78642207462E+12-g9yov7bc`). Damit koennen weder ein
     * Anfuehrungszeichen noch `<`/`>` in den Selektor oder aus dem
     * `<style>`-Element heraus geraten. Auch hier die harmlose
     * Fehlerrichtung: eine ungewoehnliche Kennung bekommt keine Regel,
     * statt eine kaputte.
     *
     * @param string[] $ids
     * @return string
     */
    private function versteck_regeln($ids) {
        $regeln = array();

        foreach ((array) $ids as $id) {
            $id = (string) $id;
            if ('' === $id || !preg_match('/^[A-Za-z0-9_.:+-]+$/', $id)) {
                continue;
            }
            $regeln[] = '[data-stable-id="' . $id . '"]{display:none!important}';
        }

        return implode("\n", $regeln);
    }

    /**
     * Gibt es fuer diese Klasse auf dieser Seite ueberhaupt ein Tafelbild?
     *
     * Wenn nicht, kann der Browser die AJAX-Abfrage beim Erstaufbau ganz
     * ueberspringen (eine Anfrage weniger je Seitenaufruf) — alles Uebrige
     * steht bereits in `cbdClassroomPageData`. Gezaehlt werden ALLE Zeilen
     * mit Zeichnungsdaten, auch die zu nicht freigegebenen Containern:
     * Diese Zeilen wertet der Filter im Browser ohnehin nie aus, die
     * groszuegigere Zaehlung irrt also nur in Richtung „lieber doch
     * fragen" — nie in Richtung „Tafelbild verschwiegen".
     *
     * @param int $class_id
     * @param int $page_id
     * @return bool
     */
    private function hat_tafelbilder($class_id, $page_id) {
        global $wpdb;

        $anzahl = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND page_id = %d
               AND drawing_data IS NOT NULL AND drawing_data <> ''",
            (int) $class_id,
            (int) $page_id
        ));

        return ((int) $anzahl) > 0;
    }

    /**
     * Der Klassenname, wie ihn `ajax_get_page_classroom_data()` liefert.
     *
     * Bewusst aus CBD_TABLE_CLASSES und NICHT aus dem Sitzungs-Transient:
     * Der Transient haelt den Namen vom Zeitpunkt der Anmeldung fest: Wurde
     * die Klasse danach umbenannt, zeigte die Navigationsleiste einen
     * anderen Namen als bisher. Diese Abfrage haelt den Erstaufbau
     * unveraendert.
     *
     * @param int $class_id
     * @return string
     */
    private function klassenname($class_id) {
        global $wpdb;

        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM " . CBD_TABLE_CLASSES . " WHERE id = %d",
            (int) $class_id
        ));

        return null === $name ? '' : (string) $name;
    }

    /**
     * Enqueue frontend assets for classroom shortcode
     */
    public function enqueue_frontend_assets() {
        global $post;

        // Check if we're in classroom mode via URL parameter
        $classroom_id = isset($_GET['classroom']) ? intval($_GET['classroom']) : 0;
        $is_classroom_page = $classroom_id > 0 && is_singular();

        // Check if shortcode is present
        $has_shortcode = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'cbd_classroom');

        // If neither shortcode nor classroom parameter, do nothing
        if (!$has_shortcode && !$is_classroom_page) {
            return;
        }

        // If this is a normal page in classroom filter mode (not the shortcode page)
        if ($is_classroom_page && !$has_shortcode) {
            // Enqueue ONLY the classroom page filter script
            wp_enqueue_script(
                'cbd-classroom-page-filter',
                CBD_PLUGIN_URL . 'assets/js/classroom-page-filter.js',
                array('jquery'),
                CBD_VERSION,
                true
            );

            // Klassenpuls (AP-1.5): Taktgeber fuer die Live-Aktualisierung im
            // Klassenmodus (Phase 1 - noch abonniert niemand, siehe
            // PLAN-Klassenmodus-Live.md). Kein jQuery: das Skript nutzt
            // ausschliesslich fetch(). Bei Takt 0 wird gar nicht erst
            // eingereiht, damit der Zustand byteidentisch zu vorher bleibt.
            if (class_exists('CBD_Klassenpuls') && CBD_Klassenpuls::takt() > 0) {
                wp_enqueue_script(
                    'cbd-klassenpuls',
                    CBD_PLUGIN_URL . 'assets/js/klassenpuls.js',
                    array(),
                    CBD_VERSION,
                    true
                );
                wp_localize_script('cbd-klassenpuls', 'cbdKlassenpulsDaten', array(
                    'restUrl' => rest_url('cbd/v1/klassenpuls'),
                    'takt'    => CBD_Klassenpuls::takt(),
                ));
            }

            // Localize with page data
            // `reduziert` sagt dem Browser, dass der Server den Inhalt bereits
            // gefiltert hat (gesperrte Seite, nicht angemeldet — siehe
            // CBD_Classroom_Gate::inhalt_reduzieren()). Der Filter unterdrückt
            // dann seine Warnung über "markierte Blöcke nicht gefunden": Auf
            // einer reduzierten Seite ist alles Vorhandene freigegeben, und
            // freigegebene Container ANDERER Seiten fehlen naturgemäß.
            $page_id   = (int) get_the_ID();
            $reduziert = function_exists('simple_clean_seite_nur_lehrpersonen')
                && function_exists('simple_clean_ist_lehrperson')
                && !simple_clean_ist_lehrperson()
                && simple_clean_seite_nur_lehrpersonen($page_id);

            $seitendaten = array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'pageId' => $page_id,
                'reduziert' => (bool) $reduziert,
                // Rückweg zur Seitenliste der Klasse (#7061, AP-2.1). Leer,
                // wenn es keine Seite mit [cbd_classroom] gibt – dann bleibt
                // der Klassenname in Kopf- und Seitenleiste reiner Text.
                'zugangUrl' => self::zugangsseite_url()
            );

            // Flackerschutz (Nachtrag zu PLAN-Klassenmodus-Live.md): Der Server
            // weiss beim Ausliefern schon, welche Container freigegeben sind.
            // Bis hierher liess er die Seite trotzdem VOLLSTAENDIG sichtbar
            // hinausgehen und ueberliess das Verstecken dem Filter im Browser —
            // der aber erst nach $(document).ready() und einer AJAX-Rundreise
            // greift. Dazwischen sah der Schueler 200-500 ms lang ALLES.
            //
            // Deshalb wird die Freigabeliste hier aufgeloest und auf zwei Wegen
            // mitgegeben:
            //   1. als Inline-CSS im <head> (siehe versteck_regeln() weiter
            //      unten) — das greift VOR dem ersten Bildaufbau;
            //   2. als Feld `freigegeben` in cbdClassroomPageData, damit
            //      classroom-page-filter.js sofort filtern kann, ohne auf die
            //      AJAX-Antwort zu warten.
            //
            // NUR auf NICHT reduzierten Seiten. Auf einer reduzierten Seite hat
            // CBD_Classroom_Gate::inhalt_reduzieren() das HTML der nicht
            // freigegebenen Container gar nicht erst mit ausgeliefert — dort
            // gibt es nichts zu verstecken und nichts zu flackern.
            $vorfilter = $reduziert ? null : $this->klassen_vorfilter($page_id, $post);

            if (null !== $vorfilter) {
                $seitendaten['freigegeben']    = $vorfilter['freigegeben'];
                $seitendaten['hatTafelbilder'] = $vorfilter['hatTafelbilder'];
                $seitendaten['klasse']         = $vorfilter['klasse'];
            }

            wp_localize_script(
                'cbd-classroom-page-filter',
                'cbdClassroomPageData',
                $seitendaten
            );

            // Enqueue classroom CSS for badges and overlays
            wp_enqueue_style(
                'cbd-classroom-frontend',
                CBD_PLUGIN_URL . 'assets/css/classroom-frontend.css',
                array(),
                CBD_VERSION
            );
            wp_add_inline_style('cbd-classroom-frontend', $this->classroom_accent_inline_css());

            // Die Versteckregeln als ZWEITER Inline-Block, damit die
            // Akzentfarbe oben unberuehrt bleibt. Ist die Liste leer, wird
            // nichts ausgegeben.
            if (null !== $vorfilter && '' !== $vorfilter['regeln']) {
                wp_add_inline_style('cbd-classroom-frontend', $vorfilter['regeln']);
            }

            return; // Don't load all the other assets
        }

        // If we get here, we have the shortcode - enqueue all assets

        // ========================================================================
        // CORE CBD SCRIPTS (needed for container block features)
        // ========================================================================

        // Interactivity API Store (ESM Module) - WordPress 6.5+
        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                'cbd-interactivity-store',
                CBD_PLUGIN_URL . 'assets/js/interactivity-store.js',
                array('@wordpress/interactivity'),
                CBD_VERSION
            );
            wp_enqueue_script_module('cbd-interactivity-store');
        }

        // jQuery-based fallback (ALWAYS enqueue for reliability)
        wp_enqueue_script(
            'cbd-interactivity-fallback',
            CBD_PLUGIN_URL . 'assets/js/interactivity-fallback.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        // Frontend CSS
        wp_enqueue_style(
            'cbd-frontend-clean',
            CBD_PLUGIN_URL . 'assets/css/cbd-frontend-clean.css',
            array(),
            CBD_VERSION
        );

        wp_enqueue_style(
            'cbd-interactivity-api',
            CBD_PLUGIN_URL . 'assets/css/interactivity-api.css',
            array('cbd-frontend-clean'),
            CBD_VERSION
        );

        // Dashicons for frontend icons
        wp_enqueue_style('dashicons');

        // Icon Libraries - lokal gebündelt (DSGVO)
        wp_enqueue_style(
            'font-awesome',
            CBD_PLUGIN_URL . 'assets/vendor/font-awesome/css/all.min.css',
            array(),
            '6.5.1'
        );

        wp_enqueue_style(
            'material-icons',
            CBD_PLUGIN_URL . 'assets/vendor/material-icons/material-icons.css',
            array(),
            CBD_VERSION
        );

        wp_enqueue_style(
            'lucide-icons',
            CBD_PLUGIN_URL . 'assets/vendor/lucide/lucide.css',
            array(),
            '0.454.0'
        );

        // html2canvas for screenshot functionality
        //
        // AP-3.3: Zweite von zwei Einbindungsstellen - siehe die ausfuehrliche
        // Begruendung in class-cbd-block-registration.php. Beide muessen
        // zusammen geaendert werden, sonst laedt je nach Seitentyp eine
        // andere Fassung der Bibliothek.
        wp_enqueue_script(
            'html2canvas',
            CBD_PLUGIN_URL . 'assets/lib/html2canvas-pro.min.js',
            array(),
            '2.4.1',
            true
        );

        // jsPDF library - lokal gebündelt (DSGVO)
        wp_enqueue_script(
            'jspdf',
            CBD_PLUGIN_URL . 'assets/lib/jspdf.umd.min.js',
            array(),
            '2.5.1',
            true
        );

        // html2pdf.js loader
        wp_enqueue_script(
            'cbd-html2pdf-loader',
            CBD_PLUGIN_URL . 'assets/js/html2pdf-loader.js',
            array('html2canvas', 'jspdf'),
            CBD_VERSION,
            true
        );

        // PDF server-side generation
        wp_enqueue_script(
            'cbd-pdf-server-side',
            CBD_PLUGIN_URL . 'assets/js/pdf-server-side.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        wp_localize_script(
            'cbd-pdf-server-side',
            'cbdPDFData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cbd-pdf-nonce'),
                // AP-2.3: pdf-server-side.js braucht die aktuelle Seiten-ID und
                // einen fuer 'cbd_classroom_nonce' gueltigen Nonce, um den
                // Bulk-Endpoint cbd_get_page_drawings (AP-2.1) fuer
                // serverseitige Tafelbilder aufzurufen - anderer Nonce-Name als
                // 'nonce' oben (der gilt fuer 'cbd-pdf-nonce'/cbd_generate_pdf).
                'pageId' => get_the_ID(),
                'classroomNonce' => wp_create_nonce('cbd_classroom_nonce'),
                // AP-1.1 (PLAN-Formeln-als-Vektor-im-PDF.md): Basis fuer das
                // Nachladen von MathJax beim Export. Gegenstueck zur selben
                // Zeile in class-cbd-block-registration.php - cbdPDFData wird
                // an zwei unabhaengigen Stellen lokalisiert, je nachdem ob die
                // Seite den [cbd_classroom]-Shortcode traegt. Wer hier etwas
                // ergaenzt, muss die andere Stelle mitziehen.
                'pluginUrl' => CBD_PLUGIN_URL,
                // AP-2.3: der Abschalter, verschachtelt aus Typgruenden -
                // Begruendung an der Gegenstelle in
                // class-cbd-block-registration.php.
                'optionen' => array(
                    'formelnAlsVektor' => function_exists('cbd_formeln_als_vektor')
                        ? cbd_formeln_als_vektor() : true,
                )
            )
        );

        // Floating PDF Export Button
        wp_enqueue_script(
            'cbd-floating-pdf-button',
            CBD_PLUGIN_URL . 'assets/js/floating-pdf-button.js',
            array('jquery', 'cbd-html2pdf-loader'),
            CBD_VERSION,
            true
        );

        // ========================================================================
        // CLASSROOM-SPECIFIC SCRIPTS
        // ========================================================================

        // Classroom frontend CSS
        wp_enqueue_style(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/css/classroom-frontend.css',
            array(),
            CBD_VERSION
        );
        wp_add_inline_style('cbd-classroom-frontend', $this->classroom_accent_inline_css());

        // Classroom frontend JS
        wp_enqueue_script(
            'cbd-classroom-frontend',
            CBD_PLUGIN_URL . 'assets/js/classroom-frontend.js',
            array('jquery'),
            CBD_VERSION,
            true
        );

        // Localize classroom script with data
        wp_localize_script(
            'cbd-classroom-frontend',
            'cbdClassroomFrontend',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'pageId' => get_the_ID()
            )
        );

        // Klassenpuls (AP-1.5): derselbe Taktgeber wie auf der normalen
        // Klassenseite (siehe oben, Zweig fuer ?classroom=). Auf dieser
        // Shortcode-Seite gibt es keine page_id, daher liefert die Route
        // hier keine Felder 'seite'/'tafel'. Kein jQuery: fetch() genuegt.
        // Bei Takt 0 wird gar nicht erst eingereiht.
        if (class_exists('CBD_Klassenpuls') && CBD_Klassenpuls::takt() > 0) {
            wp_enqueue_script(
                'cbd-klassenpuls',
                CBD_PLUGIN_URL . 'assets/js/klassenpuls.js',
                array(),
                CBD_VERSION,
                true
            );
            wp_localize_script('cbd-klassenpuls', 'cbdKlassenpulsDaten', array(
                'restUrl' => rest_url('cbd/v1/klassenpuls'),
                'takt'    => CBD_Klassenpuls::takt(),
            ));
        }
    }

    /**
     * Get classes for the current logged-in teacher
     * Used for board-mode class selector
     */
    public static function get_teacher_classes() {
        if (!is_user_logged_in() || !current_user_can('cbd_edit_blocks')) {
            return array();
        }

        global $wpdb;
        $teacher_id = get_current_user_id();

        // Eigene Klassen + abonnierte Klassen
        $subscribed = get_user_meta($teacher_id, 'cbd_subscribed_classes', true);
        $subscribed_ids = is_array($subscribed) ? array_map('intval', $subscribed) : array();

        if (!empty($subscribed_ids)) {
            $placeholders = implode(',', array_fill(0, count($subscribed_ids), '%d'));
            $params = array_merge(array($teacher_id), $subscribed_ids);
            return $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM " . CBD_TABLE_CLASSES . "
                 WHERE status = 'active' AND (teacher_id = %d OR id IN ($placeholders))
                 ORDER BY name ASC",
                $params
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM " . CBD_TABLE_CLASSES . " WHERE teacher_id = %d AND status = 'active' ORDER BY name ASC",
            $teacher_id
        ));
    }

    /**
     * Prüfen ob der aktuelle Lehrer Zugriff auf eine Klasse hat (Besitzer oder Abonnent)
     */
    private function can_access_class(int $class_id): bool {
        $teacher_id = get_current_user_id();
        global $wpdb;
        $is_owner = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CBD_TABLE_CLASSES . " WHERE id = %d AND teacher_id = %d",
            $class_id, $teacher_id
        ));
        if ($is_owner) return true;

        $subscribed = get_user_meta($teacher_id, 'cbd_subscribed_classes', true);
        $subscribed_ids = is_array($subscribed) ? array_map('intval', $subscribed) : array();
        return in_array($class_id, $subscribed_ids, true);
    }

    // =========================================================================
    // NEW: AJAX endpoint for individual page classroom data
    // =========================================================================

    /**
     * AJAX: Get classroom data for a specific page
     * Used when loading normal WordPress pages with ?classroom parameter
     */
    public function ajax_get_page_classroom_data() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $page_id = intval($_POST['page_id'] ?? 0);

        if (empty($token) || $page_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Verify token
        $transient_key = 'cbd_classroom_' . $token;
        $session = get_transient($transient_key);

        // Debug logging
        self::debug_log('[CBD Classroom] ajax_get_page_classroom_data called');
        self::debug_log('[CBD Classroom] Token: ' . substr($token, 0, 20) . '...');
        self::debug_log('[CBD Classroom] Transient key: ' . $transient_key);
        self::debug_log('[CBD Classroom] Session found: ' . ($session ? 'YES' : 'NO'));
        if ($session) {
            self::debug_log('[CBD Classroom] Session data: ' . print_r($session, true));
        }

        if (!$session || !isset($session['class_id'])) {
            wp_send_json_error(array(
                'message' => 'Ungültiger oder abgelaufener Token. Bitte loggen Sie sich erneut ein.',
                'debug' => array(
                    'token_length' => strlen($token),
                    'session_exists' => $session ? true : false
                )
            ));
        }

        global $wpdb;
        $class_id = $session['class_id'];

        // Get class name
        $class = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM " . CBD_TABLE_CLASSES . " WHERE id = %d",
            $class_id
        ));

        // Get drawings and behandelt status for this page
        self::debug_log('[CBD Classroom] ajax_get_page_classroom_data - Querying page_id: ' . $page_id . ', class_id: ' . $class_id);

        $drawings = $wpdb->get_results($wpdb->prepare(
            "SELECT container_id, drawing_data, is_behandelt
             FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND page_id = %d",
            $class_id, $page_id
        ));

        self::debug_log('[CBD Classroom] ajax_get_page_classroom_data - Found ' . count($drawings) . ' drawings');
        if (count($drawings) > 0) {
            foreach ($drawings as $d) {
                self::debug_log('[CBD Classroom] Drawing: container_id=' . $d->container_id . ', is_behandelt=' . $d->is_behandelt);
            }
        }

        // Organize drawings by base container_id (grouping multi-page variants)
        $drawings_map = array();
        $treated_containers = array();

        foreach ($drawings as $drawing) {
            // Mehrseitige Tafelbilder: "<stableId>:pN". Die Zerlegung steht
            // seit AP-2.1 in zerlege_container_id() — hier NICHT erneut
            // ausprogrammieren, sonst laufen zwei Fassungen auseinander.
            $teile      = self::zerlege_container_id($drawing->container_id);
            $base_id    = $teile['basis'];
            $page_index = $teile['seite'];

            if (!isset($drawings_map[$base_id])) {
                $drawings_map[$base_id] = array(
                    'pages'        => array(),
                    'is_behandelt' => false,
                );
            }

            $drawings_map[$base_id]['pages'][$page_index] = array(
                'drawing_data' => $drawing->drawing_data,
                'is_behandelt' => (bool) $drawing->is_behandelt,
            );

            if ($drawing->is_behandelt) {
                $drawings_map[$base_id]['is_behandelt'] = true;
                if (!in_array($base_id, $treated_containers)) {
                    $treated_containers[] = $base_id;
                }
            }
        }

        self::debug_log('[CBD Classroom] ajax_get_page_classroom_data - Treated containers: ' . implode(', ', $treated_containers));

        wp_send_json_success(array(
            'class_name' => $class ? $class->name : '',
            'treated_containers' => $treated_containers,
            'drawings' => $drawings_map
        ));
    }

    /**
     * AJAX: Cleanup invalid container references (containers that no longer exist on page)
     */
    public function ajax_cleanup_invalid_containers() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $page_id = intval($_POST['page_id'] ?? 0);
        $invalid_containers = isset($_POST['invalid_containers']) ? (array) $_POST['invalid_containers'] : array();

        if (empty($token) || $page_id <= 0 || empty($invalid_containers)) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // This endpoint should ONLY be accessible to teachers, not students
        // Require proper WordPress capabilities
        if (!current_user_can('cbd_edit_blocks') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung für diese Aktion.'));
        }

        // Verify token (for context, but not for authorization)
        $transient_key = 'cbd_classroom_' . $token;
        $session = get_transient($transient_key);

        if (!$session || !isset($session['class_id'])) {
            wp_send_json_error(array('message' => 'Ungültiger oder abgelaufener Token.'));
        }

        global $wpdb;
        $class_id = $session['class_id'];

        // Sanitize container IDs
        $invalid_containers = array_map('sanitize_text_field', $invalid_containers);

        self::debug_log('[CBD Classroom] ajax_cleanup_invalid_containers - Removing ' . count($invalid_containers) . ' invalid containers from page ' . $page_id);

        // Delete invalid container references
        $deleted_count = 0;
        foreach ($invalid_containers as $container_id) {
            $result = $wpdb->delete(
                CBD_TABLE_DRAWINGS,
                array(
                    'class_id' => $class_id,
                    'page_id' => $page_id,
                    'container_id' => $container_id
                ),
                array('%d', '%d', '%s')
            );

            if ($result !== false) {
                $deleted_count += $result;
                self::debug_log('[CBD Classroom] Deleted drawing: class_id=' . $class_id . ', page_id=' . $page_id . ', container_id=' . $container_id);
            }
        }

        self::debug_log('[CBD Classroom] Cleanup complete - Deleted ' . $deleted_count . ' entries');

        // Check if any treated containers remain for this page
        $remaining_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . CBD_TABLE_DRAWINGS . "
             WHERE class_id = %d AND page_id = %d AND is_behandelt = 1",
            $class_id, $page_id
        ));

        self::debug_log('[CBD Classroom] Remaining treated containers: ' . $remaining_count);

        wp_send_json_success(array(
            'deleted_count' => $deleted_count,
            'remaining_count' => intval($remaining_count),
            'message' => $deleted_count . ' veraltete Container-Referenz(en) entfernt.'
        ));
    }

}

// Initialize singleton
CBD_Classroom::get_instance();
