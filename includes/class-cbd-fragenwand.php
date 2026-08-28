<?php
/**
 * Container Block Designer - Fragenwand (klassenspezifische offene Fragen)
 *
 * Datenschicht für die Fragenwand: Lehrpersonen legen je Klasse Notizen an,
 * haken sie ab, bearbeiten und löschen sie. Der lesende Zugriff für Schüler
 * in einer laufenden Klassensitzung läuft NICHT über diese AJAX-Actions,
 * sondern über einen eigenen REST-Endpunkt (siehe AP-2.3).
 *
 * Sicherheitsmuster (identisch zu CBD_Classroom::ajax_save_drawing()):
 *   check_ajax_referer('cbd_classroom_nonce', 'nonce')
 *   -> current_user_can('cbd_edit_blocks')
 *   -> Parametervalidierung
 *   -> Zugriffsprüfung auf die Klasse
 *   -> Datenbankoperation
 *
 * @package ContainerBlockDesigner
 * @since Vorhaben „Fragenwand", Phase 2 (AP-2.2) — CBD_VERSION bei Anlage 3.1.106
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
     * Constructor - registriert die AJAX-Actions der Lehrpersonen.
     *
     * Bewusst KEIN wp_ajax_nopriv_*-Gegenstück: Diese fünf Endpunkte sind
     * ausschließlich für angemeldete Lehrpersonen mit `cbd_edit_blocks`.
     * Der Schüler-Lesezugriff ist ein eigener REST-Endpunkt (AP-2.3).
     */
    private function __construct() {
        add_action('wp_ajax_cbd_fragenwand_get_notes', array($this, 'ajax_fragenwand_get_notes'));
        add_action('wp_ajax_cbd_fragenwand_add_note', array($this, 'ajax_fragenwand_add_note'));
        add_action('wp_ajax_cbd_fragenwand_toggle_note', array($this, 'ajax_fragenwand_toggle_note'));
        add_action('wp_ajax_cbd_fragenwand_edit_note', array($this, 'ajax_fragenwand_edit_note'));
        add_action('wp_ajax_cbd_fragenwand_delete_note', array($this, 'ajax_fragenwand_delete_note'));
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

        global $wpdb;

        $wpdb->insert(CBD_TABLE_NOTES, array(
            'class_id'     => $class_id,
            'teacher_id'   => get_current_user_id(),
            'text'         => $text,
            'ist_erledigt' => 0,
        ));

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

        $wpdb->query($wpdb->prepare(
            'UPDATE ' . CBD_TABLE_NOTES . ' SET ist_erledigt = 1 - ist_erledigt, updated_at = %s WHERE id = %d',
            current_time('mysql'), $note_id
        ));

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

        global $wpdb;

        $wpdb->query($wpdb->prepare(
            'UPDATE ' . CBD_TABLE_NOTES . ' SET `text` = %s, updated_at = %s WHERE id = %d',
            $text, current_time('mysql'), $note_id
        ));

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

        $wpdb->delete(CBD_TABLE_NOTES, array('id' => $note_id));

        wp_send_json_success();
    }
}

// Initialize singleton
CBD_Fragenwand::get_instance();
