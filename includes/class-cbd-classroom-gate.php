<?php
/**
 * Container Block Designer — Klassen-Durchlass für gesperrte Seiten
 *
 * Das Theme „FOS Online Schulbuch" kann Seiten sperren („nur für
 * Lehrpersonen", Meta `_simple_clean_nur_lehrpersonen`). Für nicht
 * angemeldete Besucher verschwinden sie überall und liefern beim Aufruf eine
 * Hinweisseite mit HTTP 403.
 *
 * Diese Klasse öffnet diese Sperre in genau einem Fall: Es liegt eine gültige
 * Klassensitzung vor UND die Seite enthält Container, die für diese Klasse als
 * „behandelt" markiert sind.
 *
 * DIE NAHT ZUM THEME
 * Das Theme fragt per Filter nach:
 *
 *     apply_filters('simple_clean_lehrerseite_freigeben', false, $post_id)
 *
 * Der Standardwert ist `false`. Diese Klasse ist die einzige Stelle, die ihn
 * öffnet. Fehlt das Plugin, ist es abgeschaltet oder greift der Filter nicht,
 * bleibt die Seite gesperrt — ein Fehler in der Naht zeigt zu wenig, nie zu
 * viel.
 *
 * Umgekehrt gilt: Fehlt das Theme, gibt es gar keine Sperre. Alle Zugriffe auf
 * Theme-Funktionen sind deshalb mit `function_exists()` abgesichert.
 *
 * @package ContainerBlockDesigner
 * @since 3.1.87
 */

// Sicherheit: Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class CBD_Classroom_Gate {

    /**
     * Singleton-Instanz
     *
     * @var CBD_Classroom_Gate|null
     */
    private static $instance = null;

    /**
     * Gemerkte Sitzung für die Dauer eines Aufrufs.
     * false = noch nicht ermittelt, null = keine gültige Sitzung.
     *
     * @var array|null|false
     */
    private static $sitzung = false;

    /**
     * @return CBD_Classroom_Gate
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Der Filter wird IMMER registriert, auch wenn das Klassensystem
        // abgeschaltet ist. Die Prüfung steckt in sitzung(); so gibt es nur
        // einen Ort, an dem über die Freigabe entschieden wird.
        add_filter('simple_clean_lehrerseite_freigeben', array($this, 'seite_freigeben'), 10, 2);
    }

    /**
     * Gemerkte Sitzung verwerfen (Tests, langlaufende Prozesse).
     *
     * @return void
     */
    public static function sitzung_vergessen() {
        self::$sitzung = false;
    }

    /**
     * Die gültige Klassensitzung des aktuellen Aufrufs — oder null.
     *
     * Wie die Sitzung entsteht: Der Schüler meldet sich über den Shortcode
     * `[cbd_classroom]` mit dem Klassenpasswort an
     * (`CBD_Classroom::ajax_student_auth()`). Erfolgreich legt das ein Token
     * als Transient `cbd_classroom_<token>` für 24 Stunden ab. Danach hängen
     * alle Links `?classroom=<id>&token=<token>` an.
     *
     * MASSGEBLICH IST DER TRANSIENT, NICHT DER URL-PARAMETER. Stimmt die
     * `class_id` im Transient nicht mit `?classroom=` überein, gilt die
     * Sitzung als ungültig — sonst könnte man mit einem gültigen Token einer
     * beliebigen Klasse Freigaben einer anderen erschleichen.
     *
     * @return array|null array('class_id' => int, 'class_name' => string)
     */
    public static function sitzung() {
        if (false !== self::$sitzung) {
            return self::$sitzung;
        }

        self::$sitzung = null;

        if (!class_exists('CBD_Classroom') || !CBD_Classroom::is_enabled()) {
            return self::$sitzung;
        }

        $classroom = isset($_GET['classroom']) ? intval($_GET['classroom']) : 0;
        $token     = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

        if ($classroom <= 0 || '' === $token) {
            return self::$sitzung;
        }

        $daten = get_transient('cbd_classroom_' . $token);

        if (!is_array($daten) || !isset($daten['class_id'])) {
            return self::$sitzung;
        }

        if ((int) $daten['class_id'] !== $classroom) {
            return self::$sitzung;
        }

        self::$sitzung = array(
            'class_id'   => (int) $daten['class_id'],
            'class_name' => isset($daten['class_name']) ? (string) $daten['class_name'] : '',
        );

        return self::$sitzung;
    }

    /**
     * Bedient den Theme-Filter `simple_clean_lehrerseite_freigeben`.
     *
     * Gibt NUR dann `true` zurück, wenn beide Bedingungen erfüllt sind:
     * gültige Klassensitzung UND mindestens ein für diese Klasse behandelter
     * Container auf dieser Seite.
     *
     * @param bool $frei    bisheriger Wert (Standard des Themes: false)
     * @param int  $post_id
     * @return bool
     */
    public function seite_freigeben($frei, $post_id) {
        // Hat jemand anderes die Seite schon freigegeben, nicht widersprechen.
        if ($frei) {
            return true;
        }

        $sitzung = self::sitzung();
        if (null === $sitzung) {
            return false;
        }

        $behandelt = CBD_Classroom::behandelte_container($sitzung['class_id'], (int) $post_id);

        return !empty($behandelt);
    }
}

// Initialize singleton
CBD_Classroom_Gate::get_instance();
