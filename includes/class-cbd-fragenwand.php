<?php
/**
 * Container Block Designer - Fragenwand (klassenspezifische offene Fragen)
 *
 * Datenschicht für die Fragenwand: Lehrpersonen legen je Klasse Notizen an,
 * haken sie ab, bearbeiten und löschen sie. Der lesende Zugriff für Schüler
 * in einer laufenden Klassensitzung läuft NICHT über diese AJAX-Actions,
 * sondern über den REST-Endpunkt `cbd/v1/fragenwand` weiter unten (AP-2.3).
 *
 * Sicherheitsmuster (identisch zu CBD_Classroom::ajax_save_drawing()):
 *   check_ajax_referer('cbd_classroom_nonce', 'nonce')
 *   -> current_user_can('cbd_edit_blocks')
 *   -> Parametervalidierung
 *   -> Zugriffsprüfung auf die Klasse
 *   -> Datenbankoperation
 *
 * ZWEI LESEWEGE, EINE REIHENFOLGE. `ajax_fragenwand_get_notes()` (Lehrperson,
 * angemeldet) und `rest_get_notes_for_student()` (Schüler, Klassensitzung)
 * lesen dieselbe Tabelle mit demselben `ORDER BY ist_erledigt ASC,
 * created_at ASC, id ASC`. Wer eine der beiden Sortierungen ändert, muss die
 * andere mitziehen — sonst sähen Lehrperson und Klasse dieselbe Wand in
 * unterschiedlicher Reihenfolge.
 *
 * SEIT AP-3.1 kommt eine dritte, mit der Datenschicht nicht verwandte Aufgabe
 * dazu: `register_editor_format()` meldet auf `enqueue_block_editor_assets`
 * das Textformat „Fragenwand-Verweis" (`assets/js/fragenwand-format.js`) an.
 * Es steht hier und nicht in einer eigenen Klasse, weil der Plan die Fragenwand
 * bewusst in EINER Klasse bündelt und der Editor-Teil nur aus einer
 * Script-Registrierung besteht.
 *
 * @package ContainerBlockDesigner
 * @since Vorhaben „Fragenwand", Phase 2 (AP-2.2/AP-2.3) — CBD_VERSION bei Anlage 3.1.106
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fragenwand-Datenschicht (AJAX für Lehrpersonen)
 */
class CBD_Fragenwand {

    /**
     * REST-Namensraum. Derselbe wie bei CBD_Block_Content_API — die Trennung
     * liegt in der Route, nicht im Namensraum.
     */
    const REST_NAMESPACE = 'cbd/v1';

    /** Die Route für den Schüler-Lesezugriff. */
    const REST_ROUTE = '/fragenwand';

    /**
     * Der EINZIGE Fehlercode dieses Endpunkts.
     *
     * Bewusst keine sprechenden Codes („Klasse unbekannt", „Sitzung
     * abgelaufen"): Jeder Unterschied wäre ein Werkzeug, um durch
     * Durchprobieren herauszufinden, welche Klassen es gibt. Vorbild ist
     * `CBD_Block_Content_API::FEHLERCODE`.
     */
    const REST_FEHLERCODE = 'cbd_fragenwand_not_available';

    /** HTTP-Status jeder Ablehnung. */
    const REST_FEHLERSTATUS = 404;

    /**
     * Handle des Editor-Scripts für das Textformat „Fragenwand-Verweis".
     *
     * @since AP-3.1
     */
    const FORMAT_HANDLE = 'cbd-fragenwand-format';

    /**
     * Pfad des Format-Scripts, relativ zum Plugin-Verzeichnis.
     *
     * @since AP-3.1
     */
    const FORMAT_SCRIPT = 'assets/js/fragenwand-format.js';

    /**
     * Singleton instance
     *
     * @var CBD_Fragenwand|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return CBD_Fragenwand
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - registriert die AJAX-Actions der Lehrpersonen und die
     * REST-Route für Schüler in einer laufenden Klassensitzung.
     *
     * Bewusst KEIN wp_ajax_nopriv_*-Gegenstück: Diese fünf Endpunkte sind
     * ausschließlich für angemeldete Lehrpersonen mit `cbd_edit_blocks`.
     * Der Schüler-Lesezugriff ist ein eigener REST-Endpunkt (AP-2.3) — er
     * braucht kein Login, weil Schüler sich nie anmelden, sondern über das
     * Klassenpasswort kommen.
     */
    private function __construct() {
        add_action('wp_ajax_cbd_fragenwand_get_notes', array($this, 'ajax_fragenwand_get_notes'));
        add_action('wp_ajax_cbd_fragenwand_add_note', array($this, 'ajax_fragenwand_add_note'));
        add_action('wp_ajax_cbd_fragenwand_toggle_note', array($this, 'ajax_fragenwand_toggle_note'));
        add_action('wp_ajax_cbd_fragenwand_edit_note', array($this, 'ajax_fragenwand_edit_note'));
        add_action('wp_ajax_cbd_fragenwand_delete_note', array($this, 'ajax_fragenwand_delete_note'));

        add_action('rest_api_init', array($this, 'register_rest_route'));

        // AP-3.1: Das Textformat „Fragenwand-Verweis" im Block-Editor.
        // `enqueue_block_editor_assets` feuert AUSSCHLIESSLICH im Editor —
        // das Script gelangt damit nie ins Frontend.
        add_action('enqueue_block_editor_assets', array($this, 'register_editor_format'));
    }

    // =========================================================================
    // EDITOR: DAS TEXTFORMAT „FRAGENWAND-VERWEIS" (AP-3.1)
    // =========================================================================

    /**
     * Das Format-Script anmelden und einreihen.
     *
     * ANMELDEN GENÜGT HIER NICHT — anders als beim Editor-Script eines Blocks:
     * Jenes nennt `block.json` unter `editorScript`, WordPress reiht es
     * deshalb selbst ein, sobald der Block im Editor gebraucht wird. Ein
     * TEXTFORMAT hängt an keinem Block; niemand würde das Handle je
     * einreihen. Deshalb beides in einem Schritt. (Vorbild:
     * `CBD_Inline_Reference::register_format_script()`.)
     *
     * WARUM DIE ABHÄNGIGKEITEN VON HAND STEHEN: Das Plugin hat KEINEN
     * Build-Schritt, also gibt es keine `index.asset.php`. Ohne die Liste
     * registrierte WordPress das Script ohne Abhängigkeiten, und
     * `wp.richText` wäre beim Ausführen womöglich noch nicht geladen.
     * `wp-rich-text` muss ausdrücklich dabeistehen, auch wenn `wp-block-editor`
     * es in der Praxis mitbringt — genau an dieser Auslassung hat das Plugin
     * schon einmal gelitten (`class-cbd-block-reference.php:155-158`).
     * Dasselbe gilt für `wp-data`: `fragenwand-format.js` ruft
     * `wp.data.dispatch('core/notices')` auf, wenn auf der Markierung bereits
     * ein `core/link` liegt. Auf die zufällige Mitlieferung durch
     * `wp-block-editor` zu bauen ist genau die Fehlerfamilie, die der obige
     * Kommentar beschreibt (Vorbild: AP-4.fix1 des Vorhabens
     * „Inline-Blockreferenz").
     *
     * BEWUSST NICHT in der Liste: `wp-components` (kein Modal, kein Button —
     * dieses Format hat keinen Auswahl-Dialog) und der Auswahlbaustein
     * `cbd-block-auswahl` (es gibt genau EINE Fragenwand je Klasse, also
     * nichts auszuwählen).
     *
     * @since AP-3.1
     * @return void
     */
    public function register_editor_format() {
        // Mehrfaches Einreihen ist harmlos, aber ein zweites
        // `wp_register_script()` auf dasselbe Handle wäre wirkungslos und
        // verschleierte einen Fehler — deshalb dieselbe Weiche wie im Vorbild.
        if (wp_script_is(self::FORMAT_HANDLE, 'registered')) {
            wp_enqueue_script(self::FORMAT_HANDLE);
            return;
        }

        $pfad = CBD_PLUGIN_DIR . self::FORMAT_SCRIPT;

        // Fehlt die Datei (unvollständiges Update), wird gar nichts
        // registriert. Ein Handle ohne Datei ergäbe im Editor einen 404.
        if (!file_exists($pfad)) {
            return;
        }

        wp_register_script(
            self::FORMAT_HANDLE,
            CBD_PLUGIN_URL . self::FORMAT_SCRIPT,
            array(
                'wp-rich-text',    // registerFormatType, applyFormat, removeFormat
                'wp-block-editor', // RichTextToolbarButton
                'wp-element',      // createElement
                'wp-i18n',         // __
                'wp-data',         // wp.data.dispatch('core/notices') bei core/link-Konflikt
            ),
            defined('CBD_VERSION') ? CBD_VERSION : false,
            true
        );

        wp_enqueue_script(self::FORMAT_HANDLE);
    }

    // =========================================================================
    // ZUGRIFFSPRÜFUNG
    // =========================================================================

    /**
     * Prüfen ob der aktuelle Lehrer Zugriff auf eine Klasse hat (Besitzer oder Abonnent)
     *
     * Bewusste, kleine Duplikation von CBD_Classroom::can_access_class():
     * die dortige Methode ist `private` und wird nicht public gemacht, um die
     * bestehende, produktiv genutzte Klasse nicht zu verändern.
     *
     * @param int $class_id
     * @return bool
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

    /**
     * Gemeinsamer Guard-Vorlauf: Nonce + Capability.
     *
     * Steht in jeder der fünf Methoden VOR jeder Parameterverarbeitung.
     * `check_ajax_referer()` und `wp_send_json_error()` beenden die Anfrage
     * selbst, die Methode kehrt in diesen Fällen nicht zurück.
     *
     * @return void
     */
    private function guard_lehrperson() {
        check_ajax_referer('cbd_classroom_nonce', 'nonce');

        if (!current_user_can('cbd_edit_blocks')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }
    }

    /**
     * Die Klasse einer Notiz ermitteln UND den Zugriff darauf prüfen.
     *
     * SICHERHEITSKERN DIESES ARBEITSPAKETS: Für Operationen auf EINER Notiz
     * (abhaken/bearbeiten/löschen) wird die `class_id` IMMER aus der
     * Datenbankzeile der Notiz gelesen, NIEMALS aus $_POST['class_id'].
     * Andernfalls könnte ein manipulierter Parameter die Berechtigungsprüfung
     * gegen eine eigene Klasse laufen lassen, während `note_id` zu einer
     * fremden Klasse gehört (Confused-Deputy).
     *
     * Beendet die Anfrage bei fehlender Notiz oder fehlendem Zugriff.
     *
     * @param int $note_id
     * @return int class_id der Notiz
     */
    private function require_note_access(int $note_id): int {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT class_id FROM ' . CBD_TABLE_NOTES . ' WHERE id = %d',
            $note_id
        ));

        if (!$row) {
            wp_send_json_error(array('message' => 'Notiz nicht gefunden.'));
        }

        if (!$this->can_access_class((int) $row->class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        return (int) $row->class_id;
    }

    // =========================================================================
    // AJAX: NOTIZEN LESEN / ANLEGEN (klassenbezogen)
    // =========================================================================

    /**
     * AJAX: Alle Notizen einer Klasse lesen.
     *
     * Reihenfolge fest: offene zuerst (älteste zuerst), erledigte danach.
     */
    public function ajax_fragenwand_get_notes() {
        $this->guard_lehrperson();

        $class_id = intval($_POST['class_id'] ?? 0);
        if ($class_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, `text`, ist_erledigt FROM ' . CBD_TABLE_NOTES .
            ' WHERE class_id = %d ORDER BY ist_erledigt ASC, created_at ASC, id ASC',
            $class_id
        ));

        $notes = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                // Typen normalisieren: $wpdb liefert alles als String zurück.
                // Ein String "0" ist in JavaScript truthy — eine abgehakte
                // Notiz wäre sonst im Frontend nicht von einer offenen zu
                // unterscheiden.
                $notes[] = array(
                    'id'           => (int) $row->id,
                    'text'         => (string) $row->text,
                    'ist_erledigt' => (bool) intval($row->ist_erledigt),
                );
            }
        }

        wp_send_json_success(array('notes' => $notes));
    }

    /**
     * AJAX: Neue Notiz in einer Klasse anlegen.
     */
    public function ajax_fragenwand_add_note() {
        $this->guard_lehrperson();

        $class_id = intval($_POST['class_id'] ?? 0);
        if ($class_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        if (!$this->can_access_class($class_id)) {
            wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
        }

        // wp_unslash() vor der Bereinigung: $_POST kommt in WordPress
        // maskiert an, ohne das Entfernen landete aus "Schüler's Frage"
        // ein "Schüler\'s Frage" in der Datenbank.
        $text = sanitize_textarea_field(wp_unslash($_POST['text'] ?? ''));
        if ('' === $text) {
            wp_send_json_error(array('message' => 'Text darf nicht leer sein.'));
        }
        // AP-2.fix1 (Befund AP-2.rev, Schweregrad mittel): Ohne Längenbegrenzung
        // konnte eine sehr lange Notiz je nach sql_mode entweder abgelehnt oder
        // stillschweigend abgeschnitten werden. Eine feste Obergrenze verhindert
        // beides und gibt eine klare Fehlermeldung statt eines DB-Fehlers.
        if (mb_strlen($text) > 5000) {
            wp_send_json_error(array('message' => 'Text ist zu lang (maximal 5000 Zeichen).'));
        }

        global $wpdb;

        $eingefuegt = $wpdb->insert(CBD_TABLE_NOTES, array(
            'class_id'     => $class_id,
            'teacher_id'   => get_current_user_id(),
            'text'         => $text,
            'ist_erledigt' => 0,
        ));

        // AP-2.fix1: $wpdb->insert() liefert false bei einem DB-Fehler. Ohne
        // diese Prüfung meldete der Endpunkt live nachgewiesen "success" mit
        // "id":0, obwohl gar keine Zeile entstanden war (Phantom-Notiz).
        if (false === $eingefuegt) {
            wp_send_json_error(array('message' => 'Speichern fehlgeschlagen.'));
        }

        wp_send_json_success(array('id' => (int) $wpdb->insert_id));
    }

    // =========================================================================
    // AJAX: EINZELNE NOTIZ (class_id IMMER aus der Datenbankzeile)
    // =========================================================================

    /**
     * AJAX: Notiz abhaken bzw. wieder öffnen (Umschalter).
     */
    public function ajax_fragenwand_toggle_note() {
        $this->guard_lehrperson();

        $note_id = intval($_POST['note_id'] ?? 0);
        if ($note_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff wird gegen die Klasse der Notiz geprüft, nicht gegen $_POST.
        $this->require_note_access($note_id);

        global $wpdb;

        $ergebnis = $wpdb->query($wpdb->prepare(
            'UPDATE ' . CBD_TABLE_NOTES . ' SET ist_erledigt = 1 - ist_erledigt, updated_at = %s WHERE id = %d',
            current_time('mysql'), $note_id
        ));

        // AP-2.fix1: $wpdb->query() liefert false bei einem DB-Fehler
        // (0 betroffene Zeilen ist dagegen kein Fehler, kann aber praktisch
        // nicht auftreten, da require_note_access() die Existenz bereits
        // geprüft hat).
        if (false === $ergebnis) {
            wp_send_json_error(array('message' => 'Speichern fehlgeschlagen.'));
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Text einer Notiz ändern.
     */
    public function ajax_fragenwand_edit_note() {
        $this->guard_lehrperson();

        $note_id = intval($_POST['note_id'] ?? 0);
        if ($note_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff wird gegen die Klasse der Notiz geprüft, nicht gegen $_POST.
        $this->require_note_access($note_id);

        $text = sanitize_textarea_field(wp_unslash($_POST['text'] ?? ''));
        if ('' === $text) {
            wp_send_json_error(array('message' => 'Text darf nicht leer sein.'));
        }
        if (mb_strlen($text) > 5000) {
            wp_send_json_error(array('message' => 'Text ist zu lang (maximal 5000 Zeichen).'));
        }

        global $wpdb;

        $ergebnis = $wpdb->query($wpdb->prepare(
            'UPDATE ' . CBD_TABLE_NOTES . ' SET `text` = %s, updated_at = %s WHERE id = %d',
            $text, current_time('mysql'), $note_id
        ));

        if (false === $ergebnis) {
            wp_send_json_error(array('message' => 'Speichern fehlgeschlagen.'));
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Notiz löschen.
     */
    public function ajax_fragenwand_delete_note() {
        $this->guard_lehrperson();

        $note_id = intval($_POST['note_id'] ?? 0);
        if ($note_id <= 0) {
            wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        }

        // Zugriff wird gegen die Klasse der Notiz geprüft, nicht gegen $_POST.
        $this->require_note_access($note_id);

        global $wpdb;

        $geloescht = $wpdb->delete(CBD_TABLE_NOTES, array('id' => $note_id));

        if (false === $geloescht) {
            wp_send_json_error(array('message' => 'Löschen fehlgeschlagen.'));
        }

        wp_send_json_success();
    }

    // =========================================================================
    // REST: SCHÜLER-LESEZUGRIFF (cbd/v1/fragenwand)
    // =========================================================================

    /**
     * Die REST-Route für Schüler anmelden (Hook `rest_api_init`).
     *
     * BEWUSST OHNE `args`-Deklaration. `classroom` und `token` werden nicht
     * als REST-Parameter deklariert, sondern von
     * `CBD_Classroom_Gate::sitzung()` unverändert aus `$_GET` gelesen. Eine
     * Deklaration mit `'type' => 'integer'` hätte bei einem unsinnigen Wert
     * (`?classroom=abc`) eine HTTP-400-Antwort `rest_invalid_param` erzeugt —
     * also eine ANDERE Antwort als die einheitliche 404-Ablehnung. Genau
     * diese Unterscheidbarkeit soll es hier nicht geben. Eine zweite Fassung
     * der Token-Prüfung entsteht dabei nicht: gedeutet wird das Token
     * ausschließlich im Gate.
     *
     * Der Methodenname deckt sich mit der WordPress-Funktion
     * `register_rest_route()`, die hier aufgerufen wird — PHP unterscheidet
     * Methoden und globale Funktionen sauber, ein Konflikt entsteht nicht.
     *
     * @return void
     */
    public function register_rest_route() {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods'  => 'GET',
            'callback' => array($this, 'rest_get_notes_for_student'),
            // Die gesamte Autorisierung steckt im Callback: Schüler sind nie
            // angemeldet, ein Capability-Callback schlösse sie aus. Vorbild:
            // CBD_Block_Content_API.
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * GET /wp-json/cbd/v1/fragenwand?classroom=<id>&token=<token>
     *
     * Liefert die Notizen DER KLASSE, DIE ZUR SITZUNG GEHÖRT — nie die einer
     * anderen. Die Klassen-ID stammt ausschließlich aus der vom Gate
     * geprüften Sitzung, niemals aus einem Request-Parameter: `?classroom=`
     * allein ist nur ein Anspruch, geprüft wird er gegen den Transient
     * `cbd_classroom_<token>`.
     *
     * DIE KETTE, IN DIESER REIHENFOLGE — jeder Fehlschlag endet sofort in
     * derselben Ablehnung:
     *
     *   1. `nocache_headers()`, IMMER und als Erstes, ohne Bedingung.
     *      Dieselbe URL liefert je nach Sitzung völlig andere Inhalte; ein
     *      Cache dürfte sie nie verwechseln. (Die REST-Schnittstelle sendet
     *      die Kopfzeilen von sich aus nur für Angemeldete — der Filter
     *      `rest_send_nocache_headers` hat `is_user_logged_in()` als Vorgabe.
     *      Für Schüler gäbe es sie also sonst gar nicht.)
     *   2. Der geteilte Helfer `CBD_Classroom_Gate::sitzung()` muss
     *      existieren — sonst Ablehnung statt eines Fatal Errors.
     *   3. Gültige Klassensitzung.
     *   4. Plausible `class_id` aus der Sitzung.
     *
     * KEINE ZUSÄTZLICHE FREIGABEPRÜFUNG JE NOTIZ. Anders als bei
     * `cbd/v1/block-html` (dort muss der einzelne Container für die Klasse
     * „behandelt" sein) gibt es für die Fragenwand kein Äquivalent zu einem
     * freigegebenen Objekt: Es existiert genau EINE Wand je Klasse, und jede
     * ihrer Notizen ist für deren Schüler bestimmt. Beim Lesen von
     * `CBD_Classroom_Gate::sitzung()` fand sich kein Hinweis, der dagegen
     * spräche — die Methode prüft ausschließlich die Sitzung selbst und trifft
     * keine Aussage über einzelne Objekte.
     *
     * @param WP_REST_Request $request Wird nicht ausgewertet — die Sitzung
     *                                 kommt aus dem Gate, nicht aus dem
     *                                 Request.
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_notes_for_student($request) {
        // ---- (1) Kein Zwischenspeicher, in JEDEM Antwortpfad ----------------
        // Steht ganz oben und ohne Bedingung, damit kein späteres `return`
        // daran vorbeikommt.
        nocache_headers();

        // ---- (2) Der geteilte Helfer muss da sein ---------------------------
        if (!class_exists('CBD_Classroom_Gate')
            || !method_exists('CBD_Classroom_Gate', 'sitzung')) {
            return $this->fragenwand_ablehnung();
        }

        // ---- (3) Gültige Klassensitzung -------------------------------------
        // Die Methode ist parameterlos und liest `?classroom=`/`?token=`
        // selbst aus $_GET. Rückgabe: array('class_id' => int,
        // 'class_name' => string) — oder null, wenn das Klassensystem
        // abgeschaltet ist, Parameter fehlen, der Transient
        // `cbd_classroom_<token>` fehlt/abgelaufen ist oder die `class_id`
        // darin nicht zu `?classroom=` passt.
        $sitzung = CBD_Classroom_Gate::sitzung();

        if (!is_array($sitzung) || !isset($sitzung['class_id'])) {
            return $this->fragenwand_ablehnung();
        }

        // ---- (4) Plausible Klassen-ID ---------------------------------------
        $class_id = intval($sitzung['class_id']);
        if ($class_id <= 0) {
            return $this->fragenwand_ablehnung();
        }

        // ---- Notizen lesen ---------------------------------------------------
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, `text`, ist_erledigt FROM ' . CBD_TABLE_NOTES .
            ' WHERE class_id = %d ORDER BY ist_erledigt ASC, created_at ASC, id ASC',
            $class_id
        ));

        $notes = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                // Minimalprinzip: NUR diese drei Felder. Kein `class_id`,
                // kein `teacher_id`, keine Zeitstempel — nichts davon braucht
                // die Leseansicht, und jedes zusätzliche Feld wäre eine
                // Auskunft, um die niemand gebeten hat.
                //
                // Typen wie in ajax_fragenwand_get_notes(): $wpdb liefert
                // alles als String, und ein String "0" ist in JavaScript
                // truthy — eine offene Notiz erschiene sonst als abgehakt.
                $notes[] = array(
                    'id'           => (int) $row->id,
                    'text'         => (string) $row->text,
                    'ist_erledigt' => (bool) intval($row->ist_erledigt),
                );
            }
        }

        return rest_ensure_response(array('notes' => $notes));
    }

    /**
     * Die einheitliche Ablehnung dieses Endpunkts.
     *
     * Zeichengleich für JEDEN Fehlschlag: Gate-Klasse fehlt, keine Sitzung,
     * abgelaufenes oder gefälschtes Token, `?classroom=` passt nicht zum
     * Token, unplausible Klassen-ID. Es darf sich nicht ablesen lassen, WORAN
     * es gelegen hat — sonst wäre der Endpunkt ein Prüfstand für geratene
     * Klassen-IDs und Tokens.
     *
     * @return WP_Error
     */
    private function fragenwand_ablehnung() {
        return new WP_Error(
            self::REST_FEHLERCODE,
            __('Die Fragenwand ist nicht verfügbar.', 'container-block-designer'),
            array('status' => self::REST_FEHLERSTATUS)
        );
    }
}

// Initialize singleton
CBD_Fragenwand::get_instance();
